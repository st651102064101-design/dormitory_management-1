const fs = require('fs');
const file = 'Reports/settings/apple-settings.js';
let content = fs.readFileSync(file, 'utf8');

content = content.replace(
  "const handle = e.target.closest('.apple-sheet-handle');",
  "const handle = e.target.closest('.apple-sheet-handle') || e.target.closest('.apple-sheet-header');\n      if (e.target.closest('button, a, input, select, textarea, [data-close-sheet]')) return;"
);

fs.writeFileSync(file, content);
console.log("Updated apple-settings.js");
