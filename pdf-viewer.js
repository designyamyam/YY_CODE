/* Speisekarte: rendert das PDF mit PDF.js seitenweise auf <canvas>.
   Wichtig für die Schärfe: ein Canvas hat eine feste Pixelauflösung. Wird nur einmal
   in Layoutgröße gezeichnet, sieht die Karte beim Zoomen (Pinch am Handy, Browser-Zoom)
   verpixelt aus, obwohl das PDF echten Vektortext enthält. Deshalb wird hier bei Zoom
   und Größenänderung neu gezeichnet — und weit entfernte Seiten werden wieder
   freigegeben, damit 24 hochauflösende Seiten den Speicher nicht sprengen. */
(function () {
  // Vom Personal hochgeladene Karte (admin/) hat Vorrang, sonst das eingecheckte menue.pdf.
  const UPLOAD_URL = 'uploads/menue.pdf';
  const FALLBACK_URL = 'menue.pdf';
  const WORKER_URL = 'vendor/pdfjs/pdf.worker.min.js'; // PDF.js 3.11.174, selbst gehostet (kein CDN-Request → DSGVO)

  const MAX_CANVAS_WIDTH = 2600;   // Obergrenze je Seite — deckt Retina + kräftigen Zoom ab
  const RERENDER_FACTOR  = 1.2;    // erst neu zeichnen, wenn spürbar mehr Auflösung nötig ist
  const RENDER_MARGIN    = '400px';  // so früh wird gezeichnet
  const KEEP_MARGIN      = '1500px'; // so weit außerhalb bleibt das Canvas erhalten

  const container = document.getElementById('pdf-viewer');
  if (!container || typeof pdfjsLib === 'undefined') return;

  pdfjsLib.GlobalWorkerOptions.workerSrc = WORKER_URL;

  let pdfUrl = FALLBACK_URL;
  const slots = [];

  function zoomFactor() {
    return (window.visualViewport && window.visualViewport.scale) || 1;
  }

  // Zielauflösung in echten Gerätepixeln: Layoutbreite × Pixeldichte × aktueller Zoom
  function targetWidth(slot) {
    const css = slot.wrapper.clientWidth;
    if (!css) return 0;
    const dpr = window.devicePixelRatio || 1;
    return Math.min(Math.round(css * dpr * zoomFactor()), MAX_CANVAS_WIDTH);
  }

  function render(slot) {
    const width = targetWidth(slot);
    if (!width || slot.pending === width) return;

    if (slot.task) { slot.task.cancel(); slot.task = null; }

    const base = slot.page.getViewport({ scale: 1 });
    const viewport = slot.page.getViewport({ scale: width / base.width });

    // In ein frisches Canvas zeichnen und erst nach Fertigstellung tauschen — kein Flackern.
    const canvas = document.createElement('canvas');
    canvas.width = Math.floor(viewport.width);
    canvas.height = Math.floor(viewport.height);

    slot.pending = width;
    const task = slot.page.render({
      canvasContext: canvas.getContext('2d'),
      viewport,
      background: 'rgba(0,0,0,0)'
    });
    slot.task = task;

    task.promise.then(function () {
      if (slot.canvas) slot.canvas.replaceWith(canvas);
      else slot.wrapper.appendChild(canvas);
      slot.canvas = canvas;
      slot.renderedWidth = width;
    }).catch(function () {
      /* abgebrochen, weil inzwischen eine andere Auflösung gefragt ist */
    }).then(function () {
      if (slot.pending === width) slot.pending = 0;
      if (slot.task === task) slot.task = null;
    });
  }

  function ensureRendered(slot) {
    const width = targetWidth(slot);
    if (!width) return;
    if (!slot.renderedWidth || width > slot.renderedWidth * RERENDER_FACTOR) render(slot);
  }

  function release(slot) {
    if (slot.task) { slot.task.cancel(); slot.task = null; }
    slot.pending = 0;
    if (slot.canvas) {
      slot.canvas.width = 0;   // Speicher wirklich freigeben, nicht nur aus dem DOM nehmen
      slot.canvas.height = 0;
      slot.canvas.remove();
      slot.canvas = null;
    }
    slot.renderedWidth = 0;
  }

  function start(pdf) {
    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
      const wrapper = document.createElement('div');
      wrapper.className = 'pdf-page';
      container.appendChild(wrapper);
      const slot = { wrapper, page: null, canvas: null, task: null, pending: 0, renderedWidth: 0, visible: false };
      slots.push(slot);

      pdf.getPage(pageNum).then(function (page) {
        const viewport = page.getViewport({ scale: 1 });
        // Seitenverhältnis sofort setzen: Layout steht, bevor gezeichnet wird, und bleibt
        // auch stehen, wenn ein Canvas später wieder freigegeben wird.
        slot.wrapper.style.aspectRatio = viewport.width + ' / ' + viewport.height;
        slot.page = page;
        if (slot.visible) ensureRendered(slot);
      });
    }

    const renderObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        const slot = slots.find(function (s) { return s.wrapper === entry.target; });
        if (!slot) return;
        slot.visible = entry.isIntersecting;
        if (entry.isIntersecting && slot.page) ensureRendered(slot);
      });
    }, { rootMargin: RENDER_MARGIN + ' 0px' });

    const keepObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) return;
        const slot = slots.find(function (s) { return s.wrapper === entry.target; });
        if (slot) release(slot);
      });
    }, { rootMargin: KEEP_MARGIN + ' 0px' });

    slots.forEach(function (s) { renderObserver.observe(s.wrapper); keepObserver.observe(s.wrapper); });

    // Zoom und Größenänderung: sichtbare Seiten in der neuen Auflösung nachziehen
    let timer = null;
    function refresh() {
      clearTimeout(timer);
      timer = setTimeout(function () {
        slots.forEach(function (s) { if (s.visible && s.page) ensureRendered(s); });
      }, 200);
    }
    window.addEventListener('resize', refresh);
    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', refresh);
      window.visualViewport.addEventListener('scroll', refresh);
    }
  }

  // HEAD auf den Upload: existiert er, wird er mit Last-Modified als Cache-Buster geladen,
  // damit Besucher nach einem Upload nicht die alte Karte aus dem Browser-Cache sehen.
  function resolvePdfUrl() {
    return fetch(UPLOAD_URL, { method: 'HEAD', cache: 'no-store' })
      .then(function (res) {
        if (!res.ok) return FALLBACK_URL;
        const modified = Date.parse(res.headers.get('last-modified') || '');
        return UPLOAD_URL + '?v=' + (isNaN(modified) ? Date.now() : modified);
      })
      .catch(function () { return FALLBACK_URL; });
  }

  resolvePdfUrl().then(function (url) {
    pdfUrl = url;
    return pdfjsLib.getDocument(url).promise;
  }).then(start).catch(function (err) {
    console.error('PDF load failed:', err);
    container.innerHTML = '<p class="pdf-error">PDF konnte nicht geladen werden. <a href="' + pdfUrl + '" target="_blank" rel="noopener">Hier herunterladen</a>.</p>';
  });
})();
