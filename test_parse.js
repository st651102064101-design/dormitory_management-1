const html = `<button type="button" class="action-btn btn-primary" onclick="openBookingModal(775775588, &quot;1775444378&quot;, 1, &quot;\\u0e40\\u0e01\\u0e23\\u0e35\\u0e22\\u0e07\\u0e44\\u0e01\\u0e23 \\u0e04\\u0e07\\u0e40\\u0e21\\u0e37\\u0e2d\\u0e07&quot;, &quot;0980102587&quot;, &quot;1&quot;, &quot;\\u0e1d\\u0e31\\u0e48\\u0e07\\u0e40\\u0e01\\u0e48\\u0e32&quot;, 1500, &quot;10 \\u0e40\\u0e21.\\u0e22. 2569&quot;)">ยืนยันการชำระการจอง</button>`;
// In HTML, \u0e40 translates to exactly the characters "\" "u" "0" "e" "4" "0" if encoded as JSON, wait.
// If it's literally \u0e40, in JS it's parsed as the unicode char!
const jsCode = `openBookingModal(775775588, "1775444378", 1, "\\u0e40\\u0e01\\u0e23\\u0e35\\u0e22\\u0e07\\u0e44\\u0e01\\u0e23 \\u0e04\\u0e07\\u0e40\\u0e21\\u0e37\\u0e2d\\u0e07", "0980102587", "1", "\\u0e1d\\u0e31\\u0e48\\u0e07\\u0e40\\u0e01\\u0e48\\u0e32", 1500, "10 \\u0e40\\u0e21.\\u0e22. 2569")`;
eval(`function openBookingModal() { console.log(arguments); }; ` + jsCode);
