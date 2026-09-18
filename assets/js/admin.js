document.querySelectorAll('.adm-nav__link').forEach(function (a) {
  if (location.pathname.startsWith(new URL(a.href).pathname)) a.classList.add('active');
});

// Copiază link
document.addEventListener('click', function (ev) {
  var b = ev.target.closest('[data-copiaza]');
  if (!b) return;
  navigator.clipboard.writeText(location.origin + b.dataset.copiaza).then(function () {
    b.textContent = 'Copiat!'; setTimeout(function () { b.textContent = 'Copiază link'; }, 1500);
  });
});
// Picker: trimite alegerea către fereastra părinte (modal din editorul de intrare)
document.addEventListener('click', function (ev) {
  if (ev.target.closest('a')) return; // linkurile din rând rămân linkuri
  var r = ev.target.closest('.adm-picker__rand');
  if (!r || !window.parent || window.parent === window) return;
  window.parent.postMessage({ tip: 'fisier', id: r.dataset.fisierId, cale: r.dataset.fisierCale, nume: r.dataset.fisierNume, url: r.dataset.fisierUrl }, location.origin);
});
