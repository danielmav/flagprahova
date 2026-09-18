// FLAG Prahova — JS public: submeniuri pe touch, lightbox galerie. Bootstrap face offcanvas + dropdown.
(function () {
  'use strict';
  // Pe ecrane cu touch, primul tap pe un părinte cu submeniu îl deschide, al doilea urmează linkul.
  document.querySelectorAll('.fp-nav .fp-has-sub > a').forEach(function (a) {
    a.addEventListener('touchstart', function (ev) {
      var li = a.parentElement;
      if (!li.classList.contains('fp-open')) {
        ev.preventDefault();
        li.parentElement.querySelectorAll('.fp-open').forEach(function (o) { o.classList.remove('fp-open'); });
        li.classList.add('fp-open');
      }
    }, { passive: false });
  });
  // Submeniurile care ar ieși din ecran se deschid spre stânga.
  document.querySelectorAll('.fp-nav .fp-submenu').forEach(function (sub) {
    var r = sub.parentElement.getBoundingClientRect();
    if (r.right + 280 > window.innerWidth) { sub.classList.add('fp-submenu--stanga'); }
  });
  // Lightbox: orice <a class="fp-galerie__item" data-full="..."> dintr-o .fp-galerie.
  var box = document.querySelector('.fp-lightbox');
  if (!box) { return; }
  var img = box.querySelector('img'), cap = box.querySelector('.fp-lightbox__legenda');
  var lista = [], idx = 0;
  function arata(i) {
    idx = (i + lista.length) % lista.length;
    img.src = lista[idx].getAttribute('data-full');
    img.alt = lista[idx].getAttribute('data-legenda') || '';
    cap.textContent = img.alt;
    box.hidden = false; document.body.style.overflow = 'hidden';
  }
  function inchide() { box.hidden = true; document.body.style.overflow = ''; }
  document.querySelectorAll('.fp-galerie').forEach(function (g) {
    var items = Array.prototype.slice.call(g.querySelectorAll('.fp-galerie__item'));
    items.forEach(function (a, i) {
      a.addEventListener('click', function (ev) { ev.preventDefault(); lista = items; arata(i); });
    });
  });
  box.querySelector('[data-inchide]').addEventListener('click', inchide);
  box.querySelector('[data-prev]').addEventListener('click', function () { arata(idx - 1); });
  box.querySelector('[data-next]').addEventListener('click', function () { arata(idx + 1); });
  box.addEventListener('click', function (ev) { if (ev.target === box) { inchide(); } });
  document.addEventListener('keydown', function (ev) {
    if (box.hidden) { return; }
    if (ev.key === 'Escape') { inchide(); }
    if (ev.key === 'ArrowLeft') { arata(idx - 1); }
    if (ev.key === 'ArrowRight') { arata(idx + 1); }
  });
})();
