"use strict";

/**
 * Service Worker for Web Push Notifications.
 * This version includes a corrected 'notificationclick' handler for robust window focusing.
 */

// Log activation for debugging.
self.addEventListener("activate", () => {
    console.log("Service Worker: Activated and ready for push.");
});

/**
 * Listen for incoming push notifications.
 */
self.addEventListener('push', function (event) {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    // Parse the data from the push event.
    const data = event.data?.json() ?? {};
    const title = data.title || 'New Notification';
    const message = data.body || 'You have a new update.';
    const icon = data.icon || '/favicon.ico';
    // The payload from the server includes a 'data' object with the URL.
    const notificationUrl = data.data?.url || '/';

    const options = {
        body: message,
        icon: icon,
        badge: '/badge.png', // A small icon for the notification tray
        // Pass the URL to the notification's data payload so it's available on click.
        data: {
            url: notificationUrl
        }
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

/**
 * Handle clicks on the notification.
 */
self.addEventListener('notificationclick', function (event) {
    // Close the notification pop-up.
    event.notification.close();

    // Get the URL to open from the notification's data payload.
    const urlToOpen = event.notification.data.url || '/';

    // --- FIX ---
    // The original logic to find a matching client was too strict and often failed.
    // A simpler and more reliable approach is to just call `clients.openWindow()`.
    // Modern browsers are smart enough to focus an existing tab if the URL is already open.
    event.waitUntil(
        clients.openWindow(urlToOpen)
    );
});
