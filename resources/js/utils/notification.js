/**
 * Send a native OS desktop notification.
 *
 * - In Electron: delegates to the main process via IPC (reliable on all platforms).
 * - In browser: uses ServiceWorker showNotification, falling back to legacy Notification API.
 *
 * @param {string} title
 * @param {{ body?, icon?, tag?, silent?, fallback? }} options
 * @returns {Promise<boolean>} true if the notification was shown
 */
export async function sendDesktopNotification(title, options = {}) {
    const { body = '', icon = '/favicon.ico', tag, silent = false, fallback } = options;

    // Electron: delegate to main process which has reliable OS notification access
    if (window.electronAPI?.notify) {
        window.electronAPI.notify({ title, body, icon });
        return true;
    }

    // Browser: check API availability
    if (!('Notification' in window)) {
        fallback?.();
        return false;
    }

    if (Notification.permission === 'default') {
        try {
            await Notification.requestPermission();
        } catch {
            // May throw outside a user gesture in some browsers
        }
    }

    if (Notification.permission !== 'granted') {
        fallback?.();
        return false;
    }

    const notifOptions = { body, icon, silent };
    if (tag) notifOptions.tag = tag;

    // Prefer ServiceWorker notification (works reliably even when tab is focused)
    if ('serviceWorker' in navigator) {
        try {
            const reg = await navigator.serviceWorker.ready;
            await reg.showNotification(title, notifOptions);
            return true;
        } catch {
            // SW not available, fall through to legacy API
        }
    }

    // Legacy Notification API
    try {
        new Notification(title, notifOptions);
        return true;
    } catch {
        fallback?.();
        return false;
    }
}

/**
 * Request notification permission if not yet decided.
 * Returns 'granted' | 'denied' | 'default' | 'unsupported'.
 */
export async function requestNotificationPermission() {
    if (!('Notification' in window)) return 'unsupported';
    if (Notification.permission !== 'default') return Notification.permission;

    try {
        return await Notification.requestPermission();
    } catch {
        return 'default';
    }
}

/** Returns true if OS notifications are currently permitted. */
export function isNotificationGranted() {
    return 'Notification' in window && Notification.permission === 'granted';
}
