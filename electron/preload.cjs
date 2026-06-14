const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
    onIdleUpdate: (callback) => {
        const handler = (_, data) => callback(data);
        ipcRenderer.on('idle-update', handler);
        // Return cleanup function for removeListener
        return () => ipcRenderer.removeListener('idle-update', handler);
    },
});
