import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

function safeFetch(url, options = {}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    return fetch(url, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers,
        },
        credentials: 'same-origin',
    });
}

/**
 * @param {object} props  - component props (task, parentTask, workspace, space, list)
 * @param {import('vue').Ref} localTask - reactive local task copy
 */
export function useTaskTimer(props, localTask) {
    const page = usePage();

    const isTracking = ref(false);
    const trackingDuration = ref(0);
    const trackingInterval = ref(null);
    const runningEntryId = ref(null);
    const isTimerLoading = ref(false);

    const formatTrackingDuration = computed(() => {
        const h = Math.floor(trackingDuration.value / 3600);
        const m = Math.floor((trackingDuration.value % 3600) / 60);
        const s = trackingDuration.value % 60;
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    });

    const startInterval = () => {
        if (trackingInterval.value) clearInterval(trackingInterval.value);
        trackingInterval.value = setInterval(() => { trackingDuration.value++; }, 1000);
    };

    const stopInterval = () => {
        if (trackingInterval.value) {
            clearInterval(trackingInterval.value);
            trackingInterval.value = null;
        }
    };

    const init = async () => {
        if (!props.parentTask) return;
        const currentId = props.task?.id;

        const running = props.task?.time_entries?.find(e => e.is_running);
        if (running?.subtask_id === currentId) {
            isTracking.value = true;
            runningEntryId.value = running.id;
            trackingDuration.value = Math.floor((Date.now() - new Date(running.started_at).getTime()) / 1000);
            startInterval();
            return;
        }

        const global = page.props.runningTimer;
        if (global?.subtask_id === currentId && global?.is_running) {
            isTracking.value = true;
            runningEntryId.value = global.id;
            trackingDuration.value = Math.floor((Date.now() - new Date(global.started_at).getTime()) / 1000);
            startInterval();
            return;
        }

        try {
            const res = await safeFetch(route('time-tracking.running'), { method: 'GET' });
            if (res.ok && props.task?.id === currentId) {
                const data = await res.json().catch(() => ({}));
                const t = data.timer;
                if (t?.subtask_id === currentId && (t?.is_running ?? false)) {
                    isTracking.value = true;
                    runningEntryId.value = t.id;
                    trackingDuration.value = Math.floor((Date.now() - new Date(t.started_at).getTime()) / 1000);
                    startInterval();
                }
            }
        } catch {}
    };

    const start = async () => {
        if (!props.parentTask || isTimerLoading.value) return;
        isTimerLoading.value = true;
        try {
            const url = route('tasks.timer.start', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id]);
            const res = await safeFetch(url, {
                method: 'POST',
                body: JSON.stringify({ subtask_id: props.task.id }),
            });
            if (res.ok || res.status === 302 || res.status === 303) {
                const data = await res.json().catch(() => ({}));
                isTracking.value = true;
                trackingDuration.value = 0;
                runningEntryId.value = data.timeEntry?.id || data.time_entry?.id || data.id || null;
                startInterval();
                router.reload({ preserveScroll: true, only: ['task', 'tasksByStatus', 'runningTimer'] });
                window.showSnackbar?.('Timer started!', 'success');
            } else if (res.status === 419) {
                window.location.reload();
            } else {
                const data = await res.json().catch(() => ({}));
                window.showSnackbar?.(data.message || 'Failed to start timer', 'error');
            }
        } catch {
            window.showSnackbar?.('Failed to start timer', 'error');
        } finally {
            isTimerLoading.value = false;
        }
    };

    const stop = async () => {
        if (isTimerLoading.value) return;
        isTimerLoading.value = true;
        stopInterval();

        let entryId = runningEntryId.value;
        if (!entryId) entryId = props.task?.time_entries?.find(e => e.is_running)?.id;
        if (!entryId) {
            const g = page.props.runningTimer;
            if (g?.subtask_id === props.task?.id && g?.is_running) entryId = g.id;
        }
        if (!entryId) {
            try {
                const res = await safeFetch(route('time-tracking.running'), { method: 'GET' });
                if (res.ok) {
                    const d = await res.json().catch(() => ({}));
                    if (d.timer?.id) entryId = d.timer.id;
                }
            } catch {}
        }

        if (!entryId) {
            window.showSnackbar?.('No running timer found.', 'warning');
            isTracking.value = false;
            trackingDuration.value = 0;
            isTimerLoading.value = false;
            router.reload({ preserveScroll: true });
            return;
        }

        try {
            const url = route('tasks.timer.stop', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id, entryId]);
            const res = await safeFetch(url, { method: 'POST' });
            if (res.ok || res.status === 302 || res.status === 303) {
                const data = await res.json().catch(() => ({}));
                isTracking.value = false;
                runningEntryId.value = null;
                window.showSnackbar?.(`Timer stopped: ${formatTrackingDuration.value}`, 'success');
                if (data.timeEntry) {
                    const entry = data.timeEntry;
                    localTask.value.time_spent = (localTask.value.time_spent || 0) + (entry.duration || 0);
                    if (localTask.value.time_entries) {
                        const idx = localTask.value.time_entries.findIndex(e => e.id === entry.id);
                        if (idx >= 0) localTask.value.time_entries[idx] = entry;
                        else localTask.value.time_entries.push(entry);
                    }
                } else {
                    localTask.value.time_spent = (localTask.value.time_spent || 0) + Math.max(1, Math.ceil(trackingDuration.value / 60));
                }
                trackingDuration.value = 0;
                router.reload({ preserveScroll: true, only: ['task', 'tasksByStatus', 'runningTimer'] });
            } else if (res.status === 419) {
                window.location.reload();
            } else {
                window.showSnackbar?.('Failed to stop timer', 'error');
            }
        } catch {
            window.showSnackbar?.('Failed to stop timer', 'error');
        } finally {
            isTimerLoading.value = false;
        }
    };

    const reset = () => {
        stopInterval();
        isTracking.value = false;
        trackingDuration.value = 0;
        runningEntryId.value = null;
        isTimerLoading.value = false;
    };

    watch(() => props.task?.time_entries, () => {
        if (!isTracking.value) {
            init();
        } else {
            const running = props.task?.time_entries?.find(e => e.is_running);
            if (running) {
                runningEntryId.value = running.id;
            } else {
                stopInterval();
                isTracking.value = false;
                trackingDuration.value = 0;
                runningEntryId.value = null;
            }
        }
    }, { deep: true });

    return { isTracking, trackingDuration, formatTrackingDuration, isTimerLoading, start, stop, init, reset, stopInterval };
}
