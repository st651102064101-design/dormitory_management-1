
  // Fade-in animation on theme change/page load
  document.addEventListener('DOMContentLoaded', function() {
    document.body.classList.add('theme-fade');
    setTimeout(() => document.body.classList.remove('theme-fade'), 500);
  });
