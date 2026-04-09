
  // Force override inline styles for Light Mode
  document.addEventListener('DOMContentLoaded', function() {
    const allElements = document.querySelectorAll('[style*="background"], [style*="linear-gradient"]');
    allElements.forEach(el => {
      const style = el.getAttribute('style');
      if (style && (style.includes('background') || style.includes('linear-gradient'))) {
        el.style.setProperty('background', '#ffffff', 'important');
        el.style.setProperty('background-color', '#ffffff', 'important');
        el.style.setProperty('color', '#111827', 'important');
      }
    });
  });
  