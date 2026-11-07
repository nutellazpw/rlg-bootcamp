/* RLG Bootcamp — front-end behaviour */
(function () {
  'use strict';

  const meta = (n) => (document.querySelector('meta[name="' + n + '"]') || {}).content || '';
  const BASE = meta('base-path');
  const CSRF = meta('csrf-token');
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
  const store = {
    get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
  };

  function api(path, opts) {
    opts = opts || {};
    const headers = Object.assign({ 'X-CSRF-Token': CSRF, 'X-Requested-With': 'fetch', Accept: 'application/json' }, opts.headers || {});
    let body = opts.body;
    if (body && !(body instanceof FormData) && typeof body !== 'string') {
      body = JSON.stringify(body);
      headers['Content-Type'] = 'application/json';
    }
    return fetch(BASE + path, { method: opts.method || 'GET', headers, body, credentials: 'same-origin' })
      .then((r) => r.json().catch(() => ({})).then((d) => { if (!r.ok) throw Object.assign(new Error(d.error || 'Request failed'), { data: d }); return d; }));
  }

  // ---------- Navigation ----------
  const burger = $('#hamburger');
  const nav = $('#mainnav');
  if (burger && nav) {
    burger.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      burger.classList.toggle('open', open);
      document.body.classList.toggle('nav-open', open);
    });
  }
  $$('.nav-group-toggle').forEach((b) => b.addEventListener('click', (e) => {
    e.stopPropagation();
    b.parentElement.classList.toggle('open');
  }));
  const um = $('.usermenu');
  if (um) {
    $('.avatar', um).addEventListener('click', (e) => { e.stopPropagation(); um.classList.toggle('open'); });
  }
  document.addEventListener('click', () => {
    if (um) um.classList.remove('open');
    $$('.nav-group.open').forEach((g) => g.classList.remove('open'));
  });

  // Light / dark theme (saved per device)
  $$('[data-theme-toggle]').forEach((b) => b.addEventListener('click', () => {
    const t = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', t);
    store.set('rlg-theme', t);
    const m = $('meta[name="theme-color"]');
    if (m) m.content = t === 'light' ? '#ffffff' : '#0b0b0b';
  }));

  // Dropdowns (print menu)
  $$('.dropdown-toggle').forEach((t) => t.addEventListener('click', (e) => {
    e.stopPropagation();
    t.parentElement.classList.toggle('open');
  }));
  document.addEventListener('click', () => $$('.dropdown.open').forEach((d) => d.classList.remove('open')));

  // Checklist: toggle without reloading
  $$('.check-toggle-form').forEach((f) => f.addEventListener('submit', (e) => {
    e.preventDefault();
    const li = f.closest('.check-item');
    const btn = $('.check-box', f);
    btn.disabled = true;
    api(f.getAttribute('action').replace(BASE, ''), { method: 'POST', body: new FormData(f) }).then((d) => {
      li.classList.toggle('done', d.done);
      btn.setAttribute('aria-pressed', d.done ? 'true' : 'false');
      $('.check-by', li).textContent = d.by || '';
      const box = f.closest('.checklist');
      const [done, total] = d.progress;
      $('[data-progress-text]', box).textContent = done + '/' + total;
      $('.progress i', box).style.width = (total ? Math.round(done / total * 100) : 0) + '%';
      if (box.dataset.checklist === 'setup' && done === total && total > 0) setTimeout(() => location.reload(), 400);
    }).catch((err) => alertMsg(err.message)).finally(() => { btn.disabled = false; });
  }));

  // Clickable table rows
  $$('tr[data-href]').forEach((tr) => tr.addEventListener('click', (e) => {
    if (e.target.closest('a,button,input,select,label,form')) return;
    window.location = tr.dataset.href;
  }));

  // Confirm before destructive submits
  $$('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => {
    if (!window.confirm(f.dataset.confirm)) e.preventDefault();
  }));

  // Fade success messages
  $$('.alert-success[data-autohide]').forEach((a) => setTimeout(() => a.classList.add('fade'), 5000));

  // Prevent double submits
  $$('form:not(#chat-form):not(.check-toggle-form)').forEach((f) => f.addEventListener('submit', (e) => {
    setTimeout(() => {
      if (e.defaultPrevented) return;
      $$('button[type=submit], button:not([type])', f).forEach((b) => { b.disabled = true; });
    }, 0);
  }));

  window.addEventListener('pageshow', (e) => {
    if (e.persisted) $$('form button[disabled]').forEach((b) => { b.disabled = false; });
  });

  // ---------- Service worker + push ----------
  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  const pushSupported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  let swReg = null;

  function b64ToUint8(b64) {
    const pad = '='.repeat((4 - (b64.length % 4)) % 4);
    const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
  }

  async function subscribe() {
    const perm = await Notification.requestPermission();
    if (perm !== 'granted') throw new Error('Notifications are blocked. Allow them in the browser settings for this site.');
    const { key } = await api('/api/push/key');
    let sub = await swReg.pushManager.getSubscription();
    if (!sub) {
      sub = await swReg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToUint8(key) });
    }
    await api('/api/push/subscribe', { method: 'POST', body: sub.toJSON() });
    return sub;
  }

  async function unsubscribe() {
    const sub = await swReg.pushManager.getSubscription();
    if (sub) {
      await api('/api/push/unsubscribe', { method: 'POST', body: { endpoint: sub.endpoint } });
      await sub.unsubscribe();
    }
  }

  async function initPush() {
    if (!pushSupported || !document.body.classList.contains('is-staff') && !document.body.classList.contains('is-client')) {
      renderPushPanel(null);
      return;
    }
    try {
      swReg = await navigator.serviceWorker.register(BASE + '/sw.js', { scope: BASE + '/' });
      await navigator.serviceWorker.ready;
    } catch (e) {
      renderPushPanel(null);
      return;
    }
    const sub = await swReg.pushManager.getSubscription();
    // Keep the server copy in sync (e.g. after re-login on the same device)
    if (sub && Notification.permission === 'granted' && !sessionStorage.getItem('rlg-sub-synced')) {
      api('/api/push/subscribe', { method: 'POST', body: sub.toJSON() }).then(() => sessionStorage.setItem('rlg-sub-synced', '1')).catch(() => {});
    }
    renderPushPanel(sub);
    maybeShowBanner(sub);
  }

  function maybeShowBanner(sub) {
    const banner = $('#push-banner');
    if (!banner || sub || Notification.permission !== 'default') return;
    if (isIOS && !standalone) return;
    const dismissed = parseInt(store.get('rlg-push-dismissed') || '0', 10);
    if (Date.now() - dismissed < 3 * 86400000) return;
    banner.hidden = false;
    $('#push-enable').addEventListener('click', async () => {
      try { await subscribe(); banner.hidden = true; renderPushPanel(await swReg.pushManager.getSubscription()); } catch (e) { alertMsg(e.message); }
    });
    $('#push-dismiss').addEventListener('click', () => { banner.hidden = true; store.set('rlg-push-dismissed', String(Date.now())); });
  }

  function renderPushPanel(sub) {
    const state = $('#push-state');
    if (!state) return;
    const on = $('#push-on'), off = $('#push-off'), test = $('#push-test'), how = $('#ios-howto');
    if (isIOS && !standalone) {
      state.innerHTML = '<span class="text-warn">Install the app to get notifications on iPhone.</span>';
      how.hidden = false;
      return;
    }
    if (!pushSupported || !swReg) {
      state.innerHTML = '<span class="text-warn">This browser does not support push notifications.</span>';
      return;
    }
    if (Notification.permission === 'denied') {
      state.innerHTML = '<span class="text-danger">Notifications are blocked for this site.</span> Allow them in the browser / phone settings, then reload.';
      return;
    }
    if (sub && Notification.permission === 'granted') {
      state.innerHTML = '<span class="text-ok">● Notifications are on for this device.</span>';
      on.hidden = true; off.hidden = false; test.hidden = false;
    } else {
      state.innerHTML = 'Notifications are off on this device.';
      on.hidden = false; off.hidden = true; test.hidden = true;
    }
  }

  function alertMsg(msg, type) {
    const el = document.createElement('div');
    el.className = 'alert alert-' + (type || 'error') + ' toast';
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.classList.add('fade'), 4000);
    setTimeout(() => el.remove(), 5000);
  }

  const pushOn = $('#push-on');
  if (pushOn) {
    pushOn.addEventListener('click', async () => {
      try { const s = await subscribe(); renderPushPanel(s); alertMsg('Notifications enabled.', 'success'); } catch (e) { alertMsg(e.message); }
    });
    $('#push-off').addEventListener('click', async () => {
      try { await unsubscribe(); renderPushPanel(null); location.reload(); } catch (e) { alertMsg(e.message); }
    });
    $('#push-test').addEventListener('click', async () => {
      try { await api('/api/push/test', { method: 'POST' }); alertMsg('Test sent — it should arrive in a few seconds.', 'success'); } catch (e) { alertMsg(e.message); }
    });
  }
  $$('[data-remove-device]').forEach((b) => b.addEventListener('click', async () => {
    try { await api('/api/push/unsubscribe', { method: 'POST', body: { id: b.dataset.removeDevice } }); b.closest('.list-row').remove(); } catch (e) { alertMsg(e.message); }
  }));

  if (document.body.classList.contains('is-staff') || document.body.classList.contains('is-client')) {
    initPush();
  }

  // ---------- Unread badge polling ----------
  const countEl = $('#notif-count');
  function setCount(n) {
    if (!countEl) return;
    countEl.hidden = !n;
    countEl.textContent = n > 99 ? '99+' : String(n);
    if ('setAppBadge' in navigator) {
      try { n ? navigator.setAppBadge(n) : navigator.clearAppBadge(); } catch (e) {}
    }
  }
  if (countEl) {
    setCount(parseInt(countEl.textContent, 10) || 0);
    setInterval(() => {
      if (document.hidden) return;
      api('/api/status').then((d) => setCount(d.unread || 0)).catch(() => {});
    }, 30000);
  }

  // ---------- Ticket chat ----------
  const chat = $('#chat');
  if (chat) {
    const id = chat.dataset.ticket;
    const form = $('#chat-form');
    const ta = $('textarea', form);
    const scrollDown = () => { chat.scrollTop = chat.scrollHeight; };
    scrollDown();
    let busy = false;

    function apply(d) {
      if (d.html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = d.html;
        Array.from(tmp.children).forEach((node) => {
          if (!chat.querySelector('[data-id="' + node.dataset.id + '"]')) chat.appendChild(node);
        });
        scrollDown();
      }
      if (d.last_id) chat.dataset.last = d.last_id;
      const st = $('#ticket-status');
      if (st && d.status) st.innerHTML = '<span class="badge badge-' + d.status + '">' + d.status_label + '</span>';
    }

    function poll() {
      if (busy || document.hidden) return;
      busy = true;
      api('/tickets/' + id + '/messages?after=' + chat.dataset.last).then(apply).catch(() => {}).finally(() => { busy = false; });
    }
    setInterval(poll, 4000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const text = ta.value.trim();
      if (!text) return;
      const btn = $('button', form);
      btn.disabled = true;
      const fd = new FormData();
      fd.append('body', text);
      fd.append('_csrf', CSRF);
      api('/tickets/' + id + '/reply?after=' + chat.dataset.last, { method: 'POST', body: fd })
        .then((d) => { ta.value = ''; apply(d); })
        .catch((err) => alertMsg(err.message))
        .finally(() => { btn.disabled = false; ta.focus(); });
    });
    ta.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); form.requestSubmit(); }
    });
  }

  // ---------- Bootcamp form: live inventory check ----------
  const bf = $('#bootcamp-form');
  if (bf) {
    const box = $('#availability-warnings');
    let t = null;
    const check = () => {
      const fd = new FormData(bf);
      fd.append('bootcamp_id', bf.dataset.bootcampId || '');
      if (!fd.get('start_date') || !fd.get('end_date')) return;
      api('/api/availability', { method: 'POST', body: fd }).then((d) => {
        $$('[data-free-for]', bf).forEach((el) => {
          const free = d.free ? d.free[el.dataset.freeFor] : undefined;
          el.textContent = free === undefined ? '' : 'free ' + Math.max(0, free);
          el.classList.toggle('none', free !== undefined && free <= 0);
        });
        if (d.warnings && d.warnings.length) {
          box.innerHTML = '<strong>Not enough in stock</strong><br>' + d.warnings.map((w) => w.replace(/</g, '&lt;')).join('<br>');
          box.hidden = false;
        } else {
          box.hidden = true;
        }
      }).catch(() => {});
    };
    bf.addEventListener('input', (e) => {
      if (!e.target.matches('input[type=number], input[type=date]')) return;
      clearTimeout(t);
      t = setTimeout(check, 350);
    });
    check();
  }
})();
