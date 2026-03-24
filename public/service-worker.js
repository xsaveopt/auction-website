const DEFAULT_TITLE = "Auction House";

self.addEventListener("install", () => {
    self.skipWaiting();
});

self.addEventListener("activate", (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener("push", (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch {
        payload = {
            body: event.data ? event.data.text() : "",
        };
    }

    event.waitUntil(handlePush(payload));
});

self.addEventListener("notificationclick", (event) => {
    event.notification.close();

    event.waitUntil(openNotificationTarget(event.notification.data?.url ?? "/"));
});

async function handlePush(payload) {
    const clients = await self.clients.matchAll({
        type: "window",
        includeUncontrolled: true,
    });

    if (clients.some((client) => client.visibilityState === "visible")) {
        return;
    }

    await self.registration.showNotification(payload.title ?? DEFAULT_TITLE, {
        body: payload.body ?? "",
        icon: payload.icon ?? "/favicon.ico",
        badge: payload.badge ?? "/favicon.ico",
        tag: payload.tag,
        data: payload.data ?? {},
        renotify: true,
    });
}

async function openNotificationTarget(targetUrl) {
    const clients = await self.clients.matchAll({
        type: "window",
        includeUncontrolled: true,
    });
    const absoluteTarget = new URL(targetUrl, self.location.origin).href;

    for (const client of clients) {
        const clientUrl = new URL(client.url);

        if (clientUrl.origin !== self.location.origin) {
            continue;
        }

        if ("focus" in client) {
            await client.focus();
        }

        if ("navigate" in client && client.url !== absoluteTarget) {
            await client.navigate(absoluteTarget);
        }

        return;
    }

    await self.clients.openWindow(absoluteTarget);
}
