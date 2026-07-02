import { ref, onMounted, onUnmounted } from 'vue';

export function useIdleDetector({ onIdle, onActive, idleThresholdSeconds = 300 } = {}) {
    const isIdle = ref(false);
    let cleanup = null;

    const goIdle = () => {
        if (isIdle.value) return;
        isIdle.value = true;
        onIdle?.();
    };

    const goActive = () => {
        if (!isIdle.value) return;
        isIdle.value = false;
        onActive?.();
    };

    onMounted(() => {
        // Use Electron's OS-level idle API if available
        if (window.electronAPI?.onIdleUpdate) {
            cleanup = window.electronAPI.onIdleUpdate(({ becameIdle, becameActive }) => {
                if (becameIdle) goIdle();
                else if (becameActive) goActive();
            });
            return;
        }

        // Browser fallback: track user activity via DOM events
        let idleTimer = null;

        const resetTimer = () => {
            goActive();
            if (idleTimer) clearTimeout(idleTimer);
            idleTimer = setTimeout(goIdle, idleThresholdSeconds * 1000);
        };

        const activityEvents = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'wheel'];

        // Throttle activity handling to once per second
        let throttled = false;
        const handleActivity = () => {
            if (throttled) return;
            throttled = true;
            resetTimer();
            // Broadcast activity to other tabs on the same origin
            channel.postMessage('activity');
            setTimeout(() => { throttled = false; }, 1000);
        };

        activityEvents.forEach((evt) =>
            window.addEventListener(evt, handleActivity, { passive: true })
        );

        // BroadcastChannel: receive activity signals from other tabs/windows on the same origin.
        // This ensures that interacting with another tab of this app resets the idle timer here too.
        const channel = new BroadcastChannel('idle-detector');
        channel.onmessage = () => {
            // Another tab detected activity — reset our timer without re-broadcasting
            goActive();
            if (idleTimer) clearTimeout(idleTimer);
            idleTimer = setTimeout(goIdle, idleThresholdSeconds * 1000);
        };

        // Pause timer when tab is hidden, resume when visible again.
        // Tab switching is not considered idle — only inactivity within the app counts.
        const handleVisibility = () => {
            if (document.hidden) {
                if (idleTimer) {
                    clearTimeout(idleTimer);
                    idleTimer = null;
                }
            } else {
                resetTimer();
            }
        };
        document.addEventListener('visibilitychange', handleVisibility);

        resetTimer();

        cleanup = () => {
            if (idleTimer) clearTimeout(idleTimer);
            activityEvents.forEach((evt) =>
                window.removeEventListener(evt, handleActivity)
            );
            document.removeEventListener('visibilitychange', handleVisibility);
            channel.close();
        };
    });

    onUnmounted(() => {
        cleanup?.();
    });

    return { isIdle };
}
