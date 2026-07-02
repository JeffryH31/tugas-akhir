const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
    onIdleUpdate: (callback) => {
        const handler = (_, data) => callback(data);
        ipcRenderer.on('idle-update', handler);
        return () => ipcRenderer.removeListener('idle-update', handler);
    },

    // Send a native OS notification via the main process
    notify: ({ title, body, icon }) => {
        ipcRenderer.send('notify', { title, body, icon });
    },
});
