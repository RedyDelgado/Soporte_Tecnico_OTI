self.addEventListener('push', (e) => {
    const d = e.data ? e.data.json() : {};
    e.waitUntil(self.registration.showNotification(d.title || 'Llama OTI', {
        body: d.body,
        icon: d.icon,
        tag: d.tag,
        renotify: true,
        data: d.data,
    }));
});

self.addEventListener('notificationclick', (e) => {
    e.notification.close();
    const url = e.notification.data?.url || '/';
    e.waitUntil(clients.matchAll({ type: 'window' }).then((ws) => {
        const w = ws.find((c) => c.url.startsWith(self.location.origin));
        return w ? w.navigate(url).then((c) => c.focus()) : clients.openWindow(url);
    }));
});
