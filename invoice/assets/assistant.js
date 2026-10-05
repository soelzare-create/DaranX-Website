/* DaranX invoice assistant — chat client.
   Keeps the conversation client-side and posts it to index.php?p=assistant_api,
   which runs the Claude tool-use loop server-side and returns {reply, actions}. */
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

  function addMsg(role, text, actions, isErr) {
    var el = document.createElement('div');
    el.className = 'msg ' + (role === 'user' ? 'user' : 'bot') + (isErr ? ' err' : '');
    el.innerHTML = esc(text);
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
