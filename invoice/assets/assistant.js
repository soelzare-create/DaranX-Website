/* DaranX invoice assistant: chat client.
   Keeps the conversation client-side and posts it to index.php?p=assistant_api,
   which runs the tool-use loop server-side and returns {reply, actions}.
   Replies are rendered from a small, safe Markdown subset (tables, lists,
   bold, code): the text is HTML-escaped first, then only our own tags are added. */
(function () {
  'use strict';
  var log = document.getElementById('chat');
  var form = document.getElementById('chatForm');
  var box = document.getElementById('chatMsg');
  if (!log || !form || !box) return;

  var CSRF = form.dataset.csrf || '';
  var API = form.dataset.api || 'index.php?p=assistant_api';
  var history = []; // [{role:'user'|'assistant', text}]
  var busy = false;

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function inline(s) {
    return s
      .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
      .replace(/`([^`]+)`/g, '<code>$1</code>');
  }

  function cells(line) {
    return line.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map(function (c) { return c.trim(); });
  }

  // Markdown subset → HTML. Input is escaped before any tag is added.
  function md(text) {
    var lines = esc(text).split(/\r?\n/);
    var out = [];
    var para = [];
    var hasTable = false;
    function flush() {
      if (para.length) out.push('<p>' + para.map(inline).join('<br>') + '</p>');
      para = [];
    }
    for (var i = 0; i < lines.length; i++) {
      var ln = lines[i];
      if (/^\s*\|.*\|\s*$/.test(ln)) {
        flush();
        var rows = [];
        while (i < lines.length && /^\s*\|.*\|\s*$/.test(lines[i])) rows.push(lines[i++]);
        i--;
        var head = cells(rows[0]);
        var body = rows.slice(1).filter(function (r) { return !/^\s*\|?[\s:|-]+\|?\s*$/.test(r); });
        var h = '<div class="md-tbl"><table><thead><tr>' + head.map(function (c) { return '<th>' + inline(c) + '</th>'; }).join('') + '</tr></thead><tbody>';
        body.forEach(function (r) {
          h += '<tr>' + cells(r).map(function (c) { return '<td>' + inline(c) + '</td>'; }).join('') + '</tr>';
        });
        out.push(h + '</tbody></table></div>');
        hasTable = true;
      } else if (/^\s*([-*•]|\d+[.)])\s+/.test(ln)) {
        flush();
        var ordered = /^\s*\d/.test(ln);
        var items = [];
        while (i < lines.length && /^\s*([-*•]|\d+[.)])\s+/.test(lines[i])) {
          items.push('<li>' + inline(lines[i++].replace(/^\s*([-*•]|\d+[.)])\s+/, '')) + '</li>');
        }
        i--;
        out.push((ordered ? '<ol>' : '<ul>') + items.join('') + (ordered ? '</ol>' : '</ul>'));
      } else if (/^\s*#{1,4}\s+/.test(ln)) {
        flush();
        out.push('<p class="md-h">' + inline(ln.replace(/^\s*#{1,4}\s+/, '')) + '</p>');
      } else if (/^\s*(---+|\*\*\*+)\s*$/.test(ln)) {
        flush();
      } else if (ln.trim() === '') {
        flush();
      } else {
        para.push(ln);
      }
    }
    flush();
    return { html: out.join(''), wide: hasTable };
  }

  function addMsg(role, text, actions, isErr) {
    var el = document.createElement('div');
    el.className = 'msg ' + (role === 'user' ? 'user' : 'bot') + (isErr ? ' err' : '');
    if (role === 'user' || isErr) {
      el.textContent = text;
    } else {
      var r = md(text);
      el.innerHTML = r.html;
      if (r.wide) el.classList.add('wide');
    }
    if (actions && actions.length) {
      var box = document.createElement('div');
      box.className = 'acts';
      actions.forEach(function (a) {
        if (!a || !a.url) return;
        var link = document.createElement('a');
        link.href = a.url;
        link.textContent = a.label || 'باز کردن';
        box.appendChild(link);
      });
      el.appendChild(box);
    }
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
    return el;
  }

  function typing(on) {
    var t = document.getElementById('chatTyping');
    if (on && !t) {
      t = document.createElement('div');
      t.id = 'chatTyping';
      t.className = 'chat-typing';
      t.textContent = 'در حال انجام…';
      log.appendChild(t);
      log.scrollTop = log.scrollHeight;
    } else if (!on && t) {
      t.remove();
    }
  }

  function send(text) {
    busy = true;
    typing(true);
    history.push({ role: 'user', text: text });
    var params = new URLSearchParams();
    params.set('csrf', CSRF);
    params.set('payload', JSON.stringify({ messages: history }));
    fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params.toString()
    }).then(function (r) { return r.json(); }).then(function (d) {
      typing(false);
      busy = false;
      var reply = (d && d.reply) || 'پاسخی دریافت نشد.';
      addMsg('assistant', reply, (d && d.actions) || [], !(d && d.ok));
      if (d && d.ok) history.push({ role: 'assistant', text: reply });
    }).catch(function () {
      typing(false);
      busy = false;
      addMsg('assistant', 'خطا در ارتباط با سرور. دوباره تلاش کنید.', [], true);
    });
  }

  // One-tap report buttons.
  document.querySelectorAll('[data-ask]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (busy) return;
      var text = b.getAttribute('data-ask');
      addMsg('user', text);
      send(text);
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = box.value.trim();
    if (!text || busy) return;
    addMsg('user', text);
    box.value = '';
    box.style.height = 'auto';
    send(text);
  });

  // Enter to send, Shift+Enter for a newline; grow the textarea.
  box.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
  });
  box.addEventListener('input', function () {
    box.style.height = 'auto';
    box.style.height = Math.min(box.scrollHeight, 140) + 'px';
  });
})();
