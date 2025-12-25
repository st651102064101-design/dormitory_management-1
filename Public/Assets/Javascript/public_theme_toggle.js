(function(){
  try {
    if (document.getElementById('globalThemeToggle')) return; // avoid duplicate
    var mode = document.body.getAttribute('data-theme-mode') || 'auto';
    // Ensure a floating pill column container exists (stack pills vertically)
    var pillColumn = document.getElementById('publicFloatingPills');
    if (!pillColumn) {
        pillColumn = document.createElement('div');
        pillColumn.id = 'publicFloatingPills';
        pillColumn.style.position = 'fixed';
        pillColumn.style.right = '20px';
        pillColumn.style.bottom = '20px';
        pillColumn.style.zIndex = '9999';
        pillColumn.style.display = 'flex';
        pillColumn.style.flexDirection = 'column';
        pillColumn.style.gap = '20px';
        pillColumn.style.alignItems = 'flex-end';
        pillColumn.style.pointerEvents = 'none';
        document.body.appendChild(pillColumn);
    }

    var btn = document.createElement('button');
    btn.id = 'globalThemeToggle';
    btn.setAttribute('aria-label', 'Toggle theme');
    btn.style.padding = '10px 14px';
    btn.style.borderRadius = '999px';
    btn.style.border = '1px solid rgba(0,0,0,0.12)';
    btn.style.background = 'rgba(255,255,255,0.92)';
    btn.style.color = '#0f172a';
    btn.style.boxShadow = '0 8px 22px rgba(0,0,0,0.12)';
    btn.style.display = 'inline-flex';
    btn.style.alignItems = 'center';
    btn.style.gap = '8px';
    btn.style.cursor = 'pointer';
    btn.style.whiteSpace = 'nowrap';
    btn.style.pointerEvents = 'auto';

    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('width', '18');
    svg.setAttribute('height', '18');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2');
    var c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    c.setAttribute('cx','12'); c.setAttribute('cy','12'); c.setAttribute('r','4');
    var p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    p.setAttribute('d','M21 12a9 9 0 1 1-9-9');
    svg.appendChild(c); svg.appendChild(p);

    var label = document.createElement('span');
    label.textContent = 'สลับธีม';
    btn.appendChild(svg);
    btn.appendChild(label);

    // Append theme toggle at the end (billing pill will be above it)
    pillColumn.appendChild(btn);

    // Apply saved preference only when auto
    var applySaved = function(){
      var saved = localStorage.getItem('public_theme');
      if (mode === 'auto') {
        if (saved === 'light') document.body.classList.add('theme-light');
        else if (saved === 'dark') document.body.classList.remove('theme-light');
      } else if (mode === 'light') {
        document.body.classList.add('theme-light');
      } else {
        document.body.classList.remove('theme-light');
      }
    };

    applySaved();

    btn.addEventListener('click', function(){
      if (mode !== 'auto') {
        // Fixed mode: do nothing, could show tooltip later
        return;
      }
      var isLight = document.body.classList.toggle('theme-light');
      localStorage.setItem('public_theme', isLight ? 'light' : 'dark');
    });
  } catch(e) {}
})();
