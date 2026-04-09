const fs = require('fs');
const file = 'Reports/settings/section_rates.php';
let content = fs.readFileSync(file, 'utf8');

const startIdx = content.indexOf('function bindSheetHandleDragClose(sheetId) {');
const endFuncStr = "    target.addEventListener('touchend', finishDrag);\n    target.addEventListener('touchcancel', finishDrag);\n  });\n}";
const endIdx = content.indexOf(endFuncStr, startIdx);

if (startIdx !== -1 && endIdx !== -1) {
  const toReplace = content.substring(startIdx, endIdx + endFuncStr.length);
  content = content.replace(toReplace, "function bindSheetHandleDragClose(sheetId) {\n  // Handled globally by AppleSettings.initSheetHandles() in apple-settings.js\n}");
  fs.writeFileSync(file, content);
  console.log("Success");
} else {
  console.log("Could not find bounds", startIdx, endIdx);
}
