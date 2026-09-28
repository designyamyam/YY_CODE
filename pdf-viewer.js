(function () {
  // Vom Personal hochgeladene Karte (admin/) hat Vorrang, sonst das eingecheckte menue.pdf.
  const UPLOAD_URL = 'uploads/menue.pdf';
  const FALLBACK_URL = 'menue.pdf';
  const WORKER_URL = 'vendor/pdfjs/pdf.worker.min.js'; // PDF.js 3.11.174, selbst gehostet (kein CDN-Request → DSGVO)
  const container = document.getElementById('pdf-viewer');
  if (!container || typeof pdfjsLib === 'undefined') return;

  pdfjsLib.GlobalWorkerOptions.workerSrc = WORKER_URL;

  let pdfUrl = FALLBACK_URL;

  // HEAD auf den Upload: existiert er, wird er mit Last-Modified als Cache-Buster geladen,
  // damit Besucher nach einem Upload nicht die alte Karte aus dem Browser-Cache sehen.
  function resolvePdfUrl() {
    return fetch(UPLOAD_URL, { method: 'HEAD', cache: 'no-store' })
      .then((res) => {
        if (!res.ok) return FALLBACK_URL;
        const modified = Date.parse(res.headers.get('last-modified') || '');
        return UPLOAD_URL + '?v=' + (isNaN(modified) ? Date.now() : modified);
      })
      .catch(() => FALLBACK_URL);
  }

  resolvePdfUrl().then((url) => {
    pdfUrl = url;
    return pdfjsLib.getDocument(url).promise;
  }).then(async (pdf) => {
    const dpr = window.devicePixelRatio || 1;
    const slots = [];

    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
      const page = await pdf.getPage(pageNum);
      const viewport = page.getViewport({ scale: 1 });
      const wrapper = document.createElement('div');
      wrapper.className = 'pdf-page';
      wrapper.style.aspectRatio = viewport.width + ' / ' + viewport.height;
      container.appendChild(wrapper);
      slots.push({ page, wrapper, rendered: false });
    }

    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        const slot = slots.find((s) => s.wrapper === entry.target);
        if (!slot || slot.rendered) return;
        slot.rendered = true;
        observer.unobserve(slot.wrapper);
        renderPage(slot);
      });
    }, { rootMargin: '400px 0px' });

    slots.forEach((s) => observer.observe(s.wrapper));

    async function renderPage(slot) {
      const targetWidth = slot.wrapper.clientWidth;
      const baseViewport = slot.page.getViewport({ scale: 1 });
      const scale = (targetWidth / baseViewport.width) * dpr;
      const viewport = slot.page.getViewport({ scale });
      const canvas = document.createElement('canvas');
      canvas.width = viewport.width;
      canvas.height = viewport.height;
      slot.wrapper.appendChild(canvas);
      await slot.page.render({
        canvasContext: canvas.getContext('2d'),
        viewport,
        background: 'rgba(0,0,0,0)'
      }).promise;
    }
  }).catch((err) => {
    console.error('PDF load failed:', err);
    container.innerHTML = '<p class="pdf-error">PDF konnte nicht geladen werden. <a href="' + pdfUrl + '" target="_blank" rel="noopener">Hier herunterladen</a>.</p>';
  });
})();
