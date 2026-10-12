// FLAG Prahova — acord pentru cookie-uri: Google Analytics se încarcă doar după „Accept".
(function () {
  'use strict';
  var script = document.currentScript;
  var id = script && script.getAttribute('data-ga4');
  var banner = document.querySelector('.fp-consent');
  if (!id || !banner) { return; }
  var CHEIE = 'fp-consent', pornit = false;
  // localStorage poate arunca (mod privat, stocare blocată): atunci întrebăm la fiecare vizită.
  function citeste() { try { return window.localStorage.getItem(CHEIE); } catch (e) { return null; } }
  function scrie(v) { try { window.localStorage.setItem(CHEIE, v); } catch (e) { /* fără stocare */ } }
  function porneste() {
    window['ga-disable-' + id] = false;
    if (pornit) { return; }
    pornit = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', id);
    var g = document.createElement('script');
    g.async = true;
    g.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    document.head.appendChild(g);
  }
  // La retragerea acordului: oprim măsurarea și ștergem cookie-urile _ga deja puse.
  function opreste() {
    window['ga-disable-' + id] = true;
    var host = window.location.hostname;
    document.cookie.split(';').forEach(function (c) {
      var nume = c.split('=')[0].trim();
      if (nume.indexOf('_ga') !== 0) { return; }
      [host, '.' + host].forEach(function (d) {
        document.cookie = nume + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; domain=' + d;
      });
      document.cookie = nume + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
    });
  }
  banner.querySelectorAll('[data-consent]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = b.getAttribute('data-consent');
      scrie(v);
      banner.hidden = true;
      if (v === 'da') { porneste(); } else { opreste(); }
    });
  });
  // Linkul din subsol redeschide bannerul, ca alegerea să poată fi schimbată oricând.
  document.querySelectorAll('[data-consent-deschide]').forEach(function (b) {
    b.addEventListener('click', function () { banner.hidden = false; });
  });
  var ales = citeste();
  if (ales === 'da') { porneste(); } else if (ales !== 'nu') { banner.hidden = false; }
})();
