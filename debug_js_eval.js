const fs = require('fs');
const html = fs.readFileSync('test_wizard.html', 'utf-8');

const regex = /<script>([\s\S]*?)<\/script>/g;
let match;
while ((match = regex.exec(html)) !== null) {
   let scriptCode = match[1];
   try {
       new Function(scriptCode);
   } catch(e) {
       console.log("SYNTAX ERROR IN SCRIPT TAG:");
       console.log(e.message);
       // let's print line number of error if possible
   }
}
