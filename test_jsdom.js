const { JSDOM } = require("jsdom");
const fs = require('fs');

const html = fs.readFileSync('test_wizard.html', 'utf8');

const dom = new JSDOM(html, { runScripts: "dangerously" });

setTimeout(() => {
    try {
        const btn = dom.window.document.querySelector('button[onclick^="openBookingModal"]');
        if (btn) {
            console.log("Found button, clicking...");
            btn.click();
            console.log("Modal class:", dom.window.document.getElementById('bookingModal').className);
        } else {
            console.log("No openBookingModal button found.");
        }
    } catch(e) {
        console.error("Error during click:", e);
    }
}, 100);
