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

// --- Arbore meniu: drag & drop imbricat, salvare automată ---
(function () {
  var root = document.getElementById('adm-arbore');
  if (!root || !window.Sortable) return;
  var stare = document.getElementById('adm-arbore-stare');
  function citeste(ol) {
    if (!ol) return [];
    return Array.prototype.filter.call(ol.children, function (li) { return li.classList.contains('adm-nod'); })
      .map(function (li) { return { id: parseInt(li.dataset.id, 10), copii: citeste(li.querySelector(':scope > ol')) }; });
  }
  var inCurs = false, dinNou = false;
  function salveaza() {
    // O singură cerere în zbor; dacă vine altă tragere între timp, retrimitem la final.
    if (inCurs) { dinNou = true; return; }
    inCurs = true;
    stare.textContent = 'Se salvează…';
    fetch(root.dataset.url, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': window.CSRF },
      body: JSON.stringify({ sectiune_id: parseInt(root.dataset.sectiuneId, 10), arbore: citeste(root.querySelector(':scope > ol')) })
    }).then(function (r) {
      if (r.status === 401 || r.status === 403 || (r.status >= 300 && r.status < 400)) {
        stare.textContent = 'Sesiunea a expirat. Reîncarcă pagina.';
        return null;
      }
      if (!r.ok) {
        stare.textContent = 'Eroare la salvare (HTTP ' + r.status + ').';
        return null;
      }
      // Un 302 către ecranul de autentificare e urmat automat de fetch și ajunge aici ca HTML.
      if ((r.headers.get('Content-Type') || '').indexOf('application/json') === -1) {
        stare.textContent = 'Sesiunea a expirat. Reîncarcă pagina.';
        return null;
      }
      return r.json();
    }).then(function (j) {
      if (!j) return;
      stare.textContent = j.ok ? 'Ordinea a fost salvată.' : ('Eroare: ' + (j.eroare || 'necunoscută'));
    }).catch(function () {
      stare.textContent = 'Eroare de rețea. Reîncarcă pagina.';
    }).then(function () {
      inCurs = false;
      if (dinNou) { dinNou = false; salveaza(); }
    });
  }
  root.querySelectorAll('ol.adm-arbore').forEach(function (ol) {
    new Sortable(ol, { group: 'meniu', handle: '.adm-nod__grip', animation: 150, fallbackOnBody: true, swapThreshold: 0.65, onEnd: salveaza });
  });
})();

// --- Formular intrare: câmpuri după tip + picker + Quill ---
(function () {
  var f = document.getElementById('f-intrare');
  if (!f) return;
  var tip = document.getElementById('f-tip');
  function arata() {
    f.querySelectorAll('.adm-camp').forEach(function (c) { c.hidden = c.dataset.pentru !== tip.value; });
  }
  tip.addEventListener('change', arata); arata();

  var modalEl = document.getElementById('adm-picker'), frame = document.getElementById('adm-picker-frame');
  var modal = modalEl ? new bootstrap.Modal(modalEl) : null, tinta = null;
  function deschidePicker(care, imagini) {
    if (!modal || !frame) return;
    tinta = care;
    frame.src = window.ADMIN_BASE + '/fisiere?picker=1' + (imagini ? '&imagini=1' : '');
    modal.show();
  }
  document.querySelectorAll('[data-picker]').forEach(function (b) {
    b.addEventListener('click', function () { deschidePicker(b.dataset.picker, b.dataset.picker === 'imagine'); });
  });
  window.addEventListener('message', function (ev) {
    // acceptăm doar mesajele venite din iframe-ul nostru, de pe aceeași origine
    if (ev.origin !== location.origin) return;
    if (!frame || ev.source !== frame.contentWindow) return;
    if (!ev.data || ev.data.tip !== 'fisier') return;
    if (tinta === 'fisier') {
      document.getElementById('f-fisier-id').value = ev.data.id;
      document.getElementById('f-fisier-nume').textContent = ev.data.nume;
    } else if (tinta === 'imagine' && window._quill) {
      var range = window._quill.getSelection(true);
      window._quill.insertEmbed(range ? range.index : 0, 'image', ev.data.url, 'user');
    } else if (tinta === 'galerie' && window._galerieAdauga) {
      window._galerieAdauga(ev.data);
    }
    modal.hide();
  });

  var gal = document.getElementById('adm-galerie');
  if (gal) {
    if (window.Sortable) new Sortable(gal, { handle: '.adm-nod__grip', animation: 150 });
    window._galerieAdauga = function (d) {
      if (gal.querySelector('[data-fisier-id="' + d.id + '"]')) return;
      var li = document.getElementById('adm-galerie-sablon').content.firstElementChild.cloneNode(true);
      li.querySelectorAll('[data-nume]').forEach(function (i) { i.name = i.dataset.nume; });
      li.dataset.fisierId = d.id; li.querySelector('img').src = d.url; li.querySelector('input[type=hidden]').value = d.id;
      gal.appendChild(li);
    };
    gal.addEventListener('click', function (ev) { var b = ev.target.closest('[data-scoate]'); if (b) b.closest('li').remove(); });
  }

  var ed = document.getElementById('f-editor');
  if (ed && window.Quill) {
    var q = new Quill(ed, { theme: 'snow', modules: { toolbar: {
      container: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link', 'image'], ['clean']],
      handlers: { image: function () { deschidePicker('imagine', true); } }
    } } });
    window._quill = q;
    f.addEventListener('submit', function () { document.getElementById('f-continut').value = q.root.innerHTML; });
  }
})();
