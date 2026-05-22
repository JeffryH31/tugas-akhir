import { ref, onMounted, onUnmounted } from 'vue';

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
