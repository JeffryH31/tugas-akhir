const { app, BrowserWindow, powerMonitor, Notification, ipcMain } = require("electron");
const path = require("path");

let mainWindow = null;
const IDLE_THRESHOLD_SECONDS = 5 * 60;
const IDLE_POLL_INTERVAL_MS = 5_000;   // poll every 5s for reasonable responsiveness

function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1280,
        height: 800,
        webPreferences: {
            preload: path.join(__dirname, "preload.cjs"),
            contextIsolation: true,
            nodeIntegration: false,
            sandbox: false,
        },
    });

    const appUrl = process.env.APP_URL || "http://localhost:8000";
    mainWindow.loadURL(appUrl);

    mainWindow.on("closed", () => {
        mainWindow = null;
    });
}

app.whenReady().then(() => {
    createWindow();

    // Handle notification requests from the renderer process.
    // Electron's Notification API runs in the main process and works reliably on all platforms.
    ipcMain.on('notify', (_, { title, body, icon }) => {
        if (!Notification.isSupported()) return;
        new Notification({ title, body, icon }).show();
    });

    let wasIdle = false;

    const idleInterval = setInterval(() => {
        if (!mainWindow || mainWindow.isDestroyed()) return;

        const idleSeconds = powerMonitor.getSystemIdleTime();
        const isNowIdle = idleSeconds >= IDLE_THRESHOLD_SECONDS;

        mainWindow.webContents.send("idle-update", {
            idleSeconds,
            isIdle: isNowIdle,
            becameIdle: isNowIdle && !wasIdle,
            becameActive: !isNowIdle && wasIdle,
        });

        wasIdle = isNowIdle;
    }, IDLE_POLL_INTERVAL_MS);

    app.on("window-all-closed", () => {
        clearInterval(idleInterval);
        if (process.platform !== "darwin") app.quit();
    });
});

app.on("activate", () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow();
});
