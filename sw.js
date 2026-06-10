/**
 * CRC Service Worker
 * Handles Web Push notifications and notification clicks.
 * Served from the site root so its scope covers the whole origin.
 */

self.addEventListener('install', function() {
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function(event) {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'CRC', body: event.data ? event.data.text() : '' };
    }

    const title = data.title || 'CRC';
    const options = {
        body: data.body || '',
        icon: '/apple-touch-icon.png',
        badge: '/favicon.svg',
        tag: data.tag || 'crc-notification',
        data: { url: data.url || '/notifications/' }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/notifications/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if ('focus' in client) {
                    if ('navigate' in client) {
                        client.navigate(url).catch(function() {});
                    }
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});
