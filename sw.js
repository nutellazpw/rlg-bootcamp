/* RLG Bootcamp service worker — push notifications */
const SCOPE = self.registration.scope.replace(/\/$/, '');
const ICON = SCOPE + '/assets/img/icon-192.png';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// Network only — the app is always online; this handler keeps the app installable.
self.addEventListener('fetch', () => {});

self.addEventListener('push', (event) => {
  event.waitUntil(handlePush());
});

async function handlePush() {
  let items = [];
  let unread = null;
  try {
    const sub = await self.registration.pushManager.getSubscription();
    if (sub) {
      const res = await fetch(SCOPE + '/api/push/pending', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ endpoint: sub.endpoint }),
        credentials: 'omit',
        cache: 'no-store',
      });
      if (res.ok) {
        const data = await res.json();
        items = data.items || [];
        unread = typeof data.unread === 'number' ? data.unread : null;
      }
    }
  } catch (e) {
    // fall through to generic notification
  }

  if (unread !== null && self.navigator && 'setAppBadge' in self.navigator) {
    try { unread > 0 ? await self.navigator.setAppBadge(unread) : await self.navigator.clearAppBadge(); } catch (e) {}
  }

  if (!items.length) {
    // Browsers require a visible notification for every push.
    const shown = await self.registration.getNotifications();
    if (shown.length) return;
    return self.registration.showNotification('RLG Bootcamp', {
      body: 'You have a new notification.',
      icon: ICON,
      badge: ICON,
      data: { url: SCOPE + '/notifications' },
      tag: 'rlg-generic',
    });
  }

  await Promise.all(items.map((n) => self.registration.showNotification(n.title, {
    body: n.body || '',
    icon: ICON,
    badge: ICON,
    data: { url: n.url },
    tag: 'rlg-' + n.id,
    renotify: true,
    vibrate: [120, 60, 120],
  })));
}

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification.data && event.notification.data.url) || SCOPE + '/';
  event.waitUntil((async () => {
    const all = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const c of all) {
      if (c.url.startsWith(SCOPE) && 'focus' in c) {
        await c.focus();
        if ('navigate' in c) return c.navigate(target);
        return;
      }
    }
    return self.clients.openWindow(target);
  })());
});
