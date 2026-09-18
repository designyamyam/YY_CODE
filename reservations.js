/* Tebi-Reservierung — Variante "nur Nav-Link":
   Die Pill unten rechts, die widget-manager.js einhängt, wird ausgeblendet. Geöffnet wird das
   Widget über den Nav-Link RESERVE (href="#tebi-reservations", bindet Tebi selbst).
   Das iframe bleibt geladen, damit die Öffnen-Nachricht ankommt; sichtbar wird der Rahmen
   nur im aufgeklappten Zustand — erkennbar an der Höhe (Pill = 48px, Panel deutlich mehr). */
(function () {
  var WRAPPER_ID = 'tebi_rs_01';
  var PILL_HEIGHT = 48;

  function watch(wrapper) {
    function update() {
      var h = parseFloat(wrapper.style.height);
      var collapsed = !isNaN(h) && h <= PILL_HEIGHT;
      var want = collapsed ? 'hidden' : 'visible';
      if (wrapper.style.visibility !== want) wrapper.style.visibility = want;
    }
    update();
    new MutationObserver(update).observe(wrapper, { attributes: true, attributeFilter: ['style'] });
  }

  var wrapper = document.getElementById(WRAPPER_ID);
  if (wrapper) { watch(wrapper); return; }

  // Tebi hängt den Rahmen erst beim load-Event ein — auf das Einfügen warten
  var bodyObserver = new MutationObserver(function () {
    var el = document.getElementById(WRAPPER_ID);
    if (!el) return;
    bodyObserver.disconnect();
    watch(el);
  });
  bodyObserver.observe(document.documentElement, { childList: true, subtree: true });
})();
