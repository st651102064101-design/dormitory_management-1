const fs = require('fs');
const html = fs.readFileSync('dash_output.html', 'utf-8');
const scriptRegex = /<script\b[^>]*>([\s\S]*?)<\/script>/gi;
let match;
let i = 1;
while ((match = scriptRegex.exec(html)) !== null) {
  if (match[1].trim()) {
    fs.writeFileSync(`script_${i}.js`, match[1]);
    try {
        new Function(match[1]);
    } catch(e) {
        console.error(`Script ${i} Error:`, e.message);
        // Find line number
        const lines = match[1].split('\n');
        for(let l = 0; l < lines.length; l++) {
            try { new Function(lines.slice(0, l+1).join('\n')); }
            catch(err) {
                 // if this line causes an error it might be the syntax error line
                 // actually node stack trace is better 
                 try { new Function(match[1]); } catch(err2) {
                     console.log(err2.stack.split('\n').filter(s => s.includes('eval')).join('\n'));
                 }
                 break;
            }
        }
    }
  }
  i++;
}
