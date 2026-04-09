
  document.addEventListener('DOMContentLoaded', function() {
    const tooltipSelector = '[data-bs-toggle="tooltip"]';

    function initTooltips() {
      if (!window.bootstrap || !window.bootstrap.Tooltip) {
        return;
      }

      document.querySelectorAll(tooltipSelector).forEach(function(el) {
        if (!window.bootstrap.Tooltip.getInstance(el)) {
          new window.bootstrap.Tooltip(el, {
            container: 'body'
          });
        }
      });
    }

    if (window.bootstrap && window.bootstrap.Tooltip) {
      initTooltips();
      return;
    }

    const existingBundle = document.querySelector('script[data-bootstrap-tooltip-bundle="true"]');
    if (existingBundle) {
      existingBundle.addEventListener('load', initTooltips, { once: true });
      return;
    }

    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
    script.defer = true;
    script.dataset.bootstrapTooltipBundle = 'true';
    script.addEventListener('load', initTooltips, { once: true });
    document.head.appendChild(script);
  });
