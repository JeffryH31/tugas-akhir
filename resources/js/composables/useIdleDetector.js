import { ref, onMounted, onUnmounted } from 'vue';

/**
 * OS-level idle detection.
 * Only activated when app is running in Electron wrapper.
 *
 * @param {Object} options
 * @param {Function} options.onIdle  
 * @param {Function} options.onActive
 */
export function useIdleDetector({ onIdle, onActive } = {}) {
    const isIdle = ref(false);
    let cleanup = null;

    onMounted(() => {
        if (!window.electronAPI) return;

        cleanup = window.electronAPI.onIdleUpdate(({ becameIdle, becameActive }) => {
            if (becameIdle && !isIdle.value) {
                isIdle.value = true;
                onIdle?.();
            } else if (becameActive && isIdle.value) {
                isIdle.value = false;
                onActive?.();
            }
        });
    });

    onUnmounted(() => {
        cleanup?.();
    });

    return { isIdle };
}
