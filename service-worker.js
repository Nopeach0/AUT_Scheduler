const CACHE_NAME = 'kea-buddy-v1';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));

// Triggered by the main page via postMessage to fire a scheduled notification
self.addEventListener('message', event => {
    if (!event.data || event.data.type !== 'SHOW_NOTIFICATION') return;
    const { title, body, icon, tag } = event.data;
    event.waitUntil(
        self.registration.showNotification(title, {
            body,
            icon: icon || '/assets/images/logo.png',
            badge: icon || '/assets/images/logo.png',
            tag: tag || 'kea-buddy-reminder',
            requireInteraction: true,
            vibrate: [200, 100, 200]
        })
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
            const calendarUrl = '/files/calendar.html';
            for (const client of list) {
                if (client.url.includes('calendar') && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) return clients.openWindow(calendarUrl);
        })
    );
});
