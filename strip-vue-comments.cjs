const fs = require("fs");
const path = require("path");

const root = path.join(__dirname, "resources", "js");
const modified = [];

function walk(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            walk(full);
        } else if (entry.isFile() && entry.name.endsWith(".vue")) {
            processFile(full);
        }
    }
}

function processFile(file) {
    const original = fs.readFileSync(file, "utf8");

    // Match a /** ... */ block that is the first thing inside <script setup>,
    // allowing optional whitespace/newlines around it.
    const re = /(<script setup>\s*\r?\n)\s*\/\*\*[\s\S]*?\*\/[ \t]*\r?\n/;

    if (re.test(original)) {
        const updated = original.replace(re, "$1");
        if (updated !== original) {
            fs.writeFileSync(file, updated, "utf8");
            modified.push(path.relative(__dirname, file));
        }
    }
}

walk(root);
console.log(`Modified ${modified.length} file(s):`);
modified.forEach((f) => console.log("  " + f));
