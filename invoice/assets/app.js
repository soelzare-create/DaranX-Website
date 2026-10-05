/* DaranX — small page helpers shared by every screen. Loaded in <head>. */
(function () {
  'use strict';
  var root = document.documentElement;

  // Theme: restore the saved choice before first paint (the sheet stays paper-white).
  try {
    var saved = localStorage.getItem('dx-doc-theme');
    if (saved) root.setAttribute('data-theme', saved);
  } catch (e) { /* storage blocked */ }

  var FA = '۰۱۲۳۴۵۶۷۸۹';
  function toFa(s) { return String(s).replace(/\d/g, function (d) { return FA[d]; }); }
  function parse(s) {
    s = String(s == null ? '' : s)
      .replace(/[۰-۹]/g, function (c) { return c.charCodeAt(0) - 1776; })
      .replace(/[٠-٩]/g, function (c) { return c.charCodeAt(0) - 1632; })
      .replace(/٫/g, '.').replace(/[,٬،\s]/g, '');
    var n = parseFloat(s);
    return isFinite(n) ? n : 0;
  }
  var nf;
  try { nf = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 }); } catch (e) { nf = null; }
  function fmt(n) {
    return nf ? nf.format(n) : toFa(String(Math.round(n * 100) / 100).replace(/\B(?=(\d{3})+(?!\d))/g, '٬'));
  }
  window.DX = { toFa: toFa, parse: parse, fmt: fmt };

  // The browser chrome / status bar follows the page: with a theme chosen by
  // hand, both theme-color tags take that theme's top colour.
  function syncThemeColor() {
    var t = root.getAttribute('data-theme');
    if (!t) return;
    Array.prototype.forEach.call(document.querySelectorAll('meta[name="theme-color"]'), function (m) {
      m.setAttribute('content', t === 'dark' ? '#0A1522' : '#F3F5F9');
    });
  }
  syncThemeColor();

  document.addEventListener('DOMContentLoaded', function () {
    var tt = document.getElementById('tt');
    if (tt) {
      tt.addEventListener('click', function () {
        var cur = root.getAttribute('data-theme');
        var dark = cur ? cur === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
        var next = dark ? 'light' : 'dark';
        root.setAttribute('data-theme', next);
        syncThemeColor();
        try { localStorage.setItem('dx-doc-theme', next); } catch (e) { /* ignore */ }
      });
    }

    // Menu drawer (phones and tablets): «منو» opens the sidebar from the start
    // side; the scrim, the close button, Escape or picking a link closes it.
    // Focus moves into the drawer and back to the button that opened it.
    var side = document.querySelector('[data-menu]');
    var scrim = document.querySelector('.scrim');
    var opener = null;
    function setOpen(open, from) {
      if (!side) return;
      if (open) {
        opener = from || null;
        side.setAttribute('data-open', '');
        if (scrim) scrim.setAttribute('data-open', '');
        root.classList.add('menu-open');
        var first = side.querySelector('.side-nav a[aria-current="page"]') || side.querySelector('.side-nav a');
        if (first) first.focus({ preventScroll: true });
      } else {
        side.removeAttribute('data-open');
        if (scrim) scrim.removeAttribute('data-open');
        root.classList.remove('menu-open');
        if (opener) opener.focus({ preventScroll: true });
      }
      Array.prototype.forEach.call(document.querySelectorAll('[data-menu-open]'), function (b) {
        b.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }
    Array.prototype.forEach.call(document.querySelectorAll('[data-menu-open]'), function (b) {
      b.addEventListener('click', function () { setOpen(!side.hasAttribute('data-open'), b); });
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-menu-close]'), function (b) {
      b.addEventListener('click', function () { setOpen(false); });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && side && side.hasAttribute('data-open')) setOpen(false);
    });
    if (side) {
      side.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('.side-nav a') && side.hasAttribute('data-open')) setOpen(false);
      });
    }
    // Desktop width: the drawer state must not linger when the sidebar is pinned.
    if (window.matchMedia) {
      var wide = matchMedia('(min-width: 1100px)');
      var onWide = function () { if (wide.matches && side && side.hasAttribute('data-open')) setOpen(false); };
      if (wide.addEventListener) wide.addEventListener('change', onWide);
    }

    // The keyboard's return key says what it does.
    Array.prototype.forEach.call(document.querySelectorAll('form.search input[name="q"]'), function (i) {
      i.setAttribute('enterkeyhint', 'search');
    });

    // <form data-confirm="…"> asks before submitting.
    document.addEventListener('submit', function (e) {
      var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
      if (msg && !window.confirm(msg)) e.preventDefault();
    }, true);

    // Money inputs: Persian digits + thousands separators when leaving the field.
    document.addEventListener('focusout', function (e) {
      var t = e.target;
      if (t.classList && t.classList.contains('money') && t.value.trim() !== '') {
        t.value = fmt(parse(t.value));
      }
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-print]'), function (b) {
      b.addEventListener('click', function () { window.print(); });
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-autosubmit]'), function (el) {
      el.addEventListener('change', function () { el.form.submit(); });
    });

    // Purchase form: the chosen invoice's lines as tickable cards. A ticked line
    // asks for quantity and unit purchase price and shows its profit; the total
    // under the list is the purchase amount (the server recomputes it).
    Array.prototype.forEach.call(document.querySelectorAll('form[data-buy-form]'), function (form) {
      var box = form.querySelector('[data-buy]');
      var docSel = form.querySelector('[data-buy-doc]');
      var totalEl = form.querySelector('[data-buy-total]');
      var extra = form.querySelector('[data-buy-extra]');
      if (!box || !docSel) return;
      var unit = box.getAttribute('data-unit') || '';
      var picked = {};
      try { picked = JSON.parse(box.getAttribute('data-picked') || '{}') || {}; } catch (e) { picked = {}; }
      function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
      }
      function field(label, name, value, money) {
        var l = el('label', 'field');
        l.appendChild(el('span', null, label));
        var i = el('input');
        i.name = name;
        i.inputMode = 'decimal';
        i.value = value == null ? '' : value;
        if (money) { i.className = 'money'; i.placeholder = '۰'; }
        l.appendChild(i);
        return l;
      }
      function total() {
        var sum = 0, sale = 0, any = false;
        Array.prototype.forEach.call(box.querySelectorAll('.buy-item.is-on'), function (card) {
          var q = parse(card.querySelector('[name$="[qty]"]').value);
          var p = parse(card.querySelector('[name$="[price]"]').value);
          var line = q * p;
          sum += line;
          sale += +card.getAttribute('data-sale') * (q / (+card.getAttribute('data-qty') || q || 1));
          any = true;
          var out = card.querySelector('.bi-line');
          if (p > 0) {
            var profit = (+card.getAttribute('data-sale') * (q / (+card.getAttribute('data-qty') || q || 1))) - line;
            out.textContent = 'خرید ' + fmt(line) + ' ' + unit + '، ' + (profit >= 0 ? 'سود ' : 'زیان ') + fmt(Math.abs(profit));
            out.classList.toggle('neg', profit < 0);
          } else {
            out.textContent = 'قیمت خرید را بنویسید';
            out.classList.remove('neg');
          }
        });
        var ex = extra ? parse(extra.value) : 0;
        if (ex > 0) { sum += ex; any = true; }
        if (!totalEl) return;
        totalEl.textContent = any ? 'جمع خرید: ' + fmt(sum) + ' ' + unit : '';
        totalEl.hidden = !any;
      }
      function render(lines) {
        box.textContent = '';
        if (!lines || !lines.length) {
          box.appendChild(el('p', 'muted buy-empty', docSel.value ? 'این فاکتور قلمی ندارد؛ از «چیز دیگری هم خریدید» استفاده کنید.' : 'اول فاکتور را انتخاب کنید تا اقلامش اینجا بیاید.'));
          total();
          return;
        }
        lines.forEach(function (l, i) {
          var pick = picked[l.pos];
          var card = el('div', 'buy-item' + (pick ? ' is-on' : ''));
          card.setAttribute('data-sale', l.sale || 0);
          card.setAttribute('data-qty', l.qty == null ? 1 : l.qty);
          var head = el('label', 'bi-head');
          var cb = el('input');
          cb.type = 'checkbox';
          cb.name = 'items[' + i + '][on]';
          cb.value = '1';
          cb.checked = !!pick;
          var pos = el('input');
          pos.type = 'hidden';
          pos.name = 'items[' + i + '][pos]';
          pos.value = l.pos;
          var txt = el('span', 'bi-txt');
          txt.appendChild(el('b', null, l.title));
          var sold = 'فروش: ' + (l.qty == null ? '' : toFa(String(l.qty)).replace('.', '٫') + ' × ')
            + (l.price == null ? '' : fmt(l.price)) + (l.sale ? ' = ' + fmt(l.sale) + ' ' + unit : '');
          txt.appendChild(el('small', null, sold));
          if (l.bought && l.bought.length) {
            txt.appendChild(el('small', 'bi-bought', 'قبلاً خریده شده در ' + l.bought.map(function (b) { return b.number; }).join('، ')));
          }
          head.appendChild(cb);
          head.appendChild(pos);
          head.appendChild(txt);
          card.appendChild(head);
          var inputs = el('div', 'bi-inputs');
          inputs.appendChild(field('تعداد', 'items[' + i + '][qty]', pick ? pick.qty : (l.qty == null ? '1' : toFa(String(l.qty)).replace('.', '٫')), false));
          inputs.appendChild(field('قیمت واحد خرید (' + unit + ')', 'items[' + i + '][price]', pick ? pick.price : '', true));
          inputs.appendChild(el('div', 'bi-line'));
          inputs.hidden = !pick;
          card.appendChild(inputs);
          cb.addEventListener('change', function () {
            card.classList.toggle('is-on', cb.checked);
            inputs.hidden = !cb.checked;
            if (cb.checked) { var pr = inputs.querySelector('[name$="[price]"]'); if (pr && !pr.value) pr.focus(); }
            total();
          });
          box.appendChild(card);
        });
        total();
      }
      var initial = [];
      try { initial = JSON.parse(box.getAttribute('data-lines') || '[]'); } catch (e) { initial = []; }
      render(initial);
      box.addEventListener('input', total);
      if (extra) extra.addEventListener('input', total);
      docSel.addEventListener('change', function () {
        picked = {};
        if (!docSel.value) { render([]); return; }
        box.textContent = '';
        box.appendChild(el('p', 'muted buy-empty', 'در حال خواندن اقلام فاکتور…'));
        fetch(box.getAttribute('data-url') + '&doc=' + encodeURIComponent(docSel.value), { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (d) { render(d && d.lines); })
          .catch(function () {
            box.textContent = '';
            box.appendChild(el('p', 'muted buy-empty', 'اقلام فاکتور خوانده نشد. صفحه را دوباره باز کنید.'));
          });
      });
    });

    // User form: «مدیر» switches the per-area choices off; preset buttons fill them in.
    Array.prototype.forEach.call(document.querySelectorAll('form[data-user-form]'), function (form) {
      var admin = form.querySelector('[data-admin-toggle]');
      var box = form.querySelector('[data-perms]');
      if (admin && box) {
        admin.addEventListener('change', function () { box.disabled = admin.checked; });
      }
      Array.prototype.forEach.call(form.querySelectorAll('[data-preset]'), function (b) {
        b.addEventListener('click', function () {
          var set = JSON.parse(b.getAttribute('data-preset'));
          Object.keys(set).forEach(function (area) {
            var r = form.querySelector('input[name="perm_' + area + '"][value="' + set[area] + '"]');
            if (r) r.checked = true;
          });
        });
      });
    });

    // Payment-out form: «سربار» hides the invoice/purchase part and switches the
    // category suggestions; picking a purchase fills in its invoice and supplier.
    Array.prototype.forEach.call(document.querySelectorAll('form[data-expense-form]'), function (form) {
      var direct = form.querySelector('.direct-only');
      var cat = form.querySelector('input[name="category"]');
      var payee = form.querySelector('input[name="payee"]');
      var doc = form.querySelector('select[name="doc_id"]');
      var pur = form.querySelector('select[name="purchase_id"]');
      function sync() {
        var checked = form.querySelector('input[name="kind"]:checked');
        var overhead = checked && checked.value === 'overhead';
        if (direct) direct.hidden = overhead;
        if (cat) {
          cat.setAttribute('list', overhead ? 'catOverhead' : 'catDirect');
          cat.placeholder = overhead ? 'مثلاً اجاره' : 'مثلاً پیک';
        }
      }
      Array.prototype.forEach.call(form.querySelectorAll('input[name="kind"]'), function (r) {
        r.addEventListener('change', sync);
      });
      if (pur) {
        pur.addEventListener('change', function () {
          var opt = pur.options[pur.selectedIndex];
          if (!opt || !opt.value) return;
          if (doc && opt.getAttribute('data-doc')) doc.value = opt.getAttribute('data-doc');
          if (payee && !payee.value.trim()) payee.value = opt.getAttribute('data-supplier') || '';
          if (cat && !cat.value.trim()) cat.value = 'پرداخت بابت خرید';
        });
      }
      sync();
    });

    // Close an open "more" menu when clicking elsewhere.
    document.addEventListener('click', function (e) {
      Array.prototype.forEach.call(document.querySelectorAll('details.more[open]'), function (d) {
        if (!d.contains(e.target)) d.removeAttribute('open');
      });
    });
  });
})();
