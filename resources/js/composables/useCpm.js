import { ref } from 'vue';
import { useSnackbar } from '@/composables/useSnackbar';

export function useCpm(refs) {
    const { showSnackbar } = useSnackbar();
    const cpmData = ref(null);
    const loading = ref(false);

    const resolveIds = () => {
        const workspace = refs.workspace?.value ?? refs.workspace;
        const space = refs.space?.value ?? refs.space;
        const list = refs.list?.value ?? refs.list;
        const parentTask = refs.parentTask?.value ?? refs.parentTask;
        if (!workspace || !space || !list || !parentTask) return null;
        return [workspace.id, space.id, list.id, parentTask.id];
    };

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content;

    const fetchCpmData = async () => {
        const ids = resolveIds();
        if (!ids) return;

        loading.value = true;
        try {
            const response = await fetch(route('tasks.cpm.analyze', ids), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                cpmData.value = await response.json();
            } else {
                cpmData.value = { success: false, message: 'Failed to fetch CPM data' };
            }
        } catch (error) {
            console.error('Error fetching CPM data:', error);
            cpmData.value = { success: false, message: 'An error occurred while calculating CPM' };
        } finally {
            loading.value = false;
        }
    };

    const mutateDependency = async ({ subtaskId, dependsOnId, mode }) => {
        const ids = resolveIds();
        if (!ids) return;

        const routeName = mode === 'add'
            ? 'tasks.cpm.dependencies.add'
            : 'tasks.cpm.dependencies.remove';

        const body = mode === 'add'
            ? { subtask_id: subtaskId, depends_on_id: dependsOnId, type: 'blocks' }
            : { subtask_id: subtaskId, depends_on_id: dependsOnId };

        try {
            const response = await fetch(route(routeName, ids), {
                method: mode === 'add' ? 'POST' : 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(body),
            });

            const result = await response.json();
            if (result.success) {
                await fetchCpmData();
                showSnackbar(mode === 'add' ? 'Dependency added!' : 'Dependency removed!', 'success');
            } else {
                showSnackbar(result.message || 'Failed', 'error');
            }
        } catch {
            showSnackbar(
                mode === 'add' ? 'Failed to add dependency' : 'Failed to remove dependency',
                'error'
            );
        }
    };

    const addDependency = (payload) => mutateDependency({ ...payload, mode: 'add' });
    const removeDependency = (payload) => mutateDependency({ ...payload, mode: 'remove' });

    const reset = () => {
        cpmData.value = null;
    };

    return {
        cpmData,
        loading,
        fetchCpmData,
        addDependency,
        removeDependency,
        reset,
    };
}
