document.querySelectorAll('.adm-nav__link').forEach(function (a) {
  if (location.pathname.startsWith(new URL(a.href).pathname)) a.classList.add('active');
});
