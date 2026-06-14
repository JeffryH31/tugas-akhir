const { app, BrowserWindow, powerMonitor } = require("electron");
const path = require("path");

let mainWindow = null;
const IDLE_THRESHOLD_SECONDS = 10; // dianggap idle setelah 10 detik tidak ada aktivitas
const IDLE_POLL_INTERVAL_MS = 1_000; // cek status idle tiap 1 detik

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
