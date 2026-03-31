/**
 * ✨ Futuristic Bright UI — JavaScript Enhancements
 * Floating particles, scroll reveal, counter animations, ripple effects
 */
(function () {
  'use strict';

  /* === Force light-theme class on <html> === */
  document.documentElement.classList.add('light-theme');

  /* === Floating Particles Layer === */
  function createParticlesLayer() {
    if (document.getElementById('fu-particles')) return;
    const container = document.createElement('div');
    container.id = 'fu-particles';
    container.setAttribute('aria-hidden', 'true');
    Object.assign(container.style, {
      position: 'fixed',
      inset: '0',
      pointerEvents: 'none',
      zIndex: '0',
      overflow: 'hidden'
    });

    const colors = [
      'rgba(99,102,241,0.25)',
      'rgba(139,92,246,0.20)',
      'rgba(168,85,247,0.18)',
      'rgba(236,72,153,0.15)',
      'rgba(6,182,212,0.18)'
    ];

    for (let i = 0; i < 30; i++) {
      const p = document.createElement('span');
      const size = 2 + Math.random() * 4;
      const startX = Math.random() * 100;
      const startY = Math.random() * 100;
      const dur = 15 + Math.random() * 25;
      const delay = Math.random() * dur;
      const color = colors[Math.floor(Math.random() * colors.length)];

      Object.assign(p.style, {
        position: 'absolute',
        width: size + 'px',
        height: size + 'px',
        borderRadius: '50%',
        background: color,
        left: startX + '%',
        top: startY + '%',
        animation: `fuFloatParticle ${dur}s ease-in-out ${delay}s infinite`,
        willChange: 'transform, opacity'
      });
      container.appendChild(p);
    }

    // Inject particle keyframes if not present
    if (!document.getElementById('fu-particle-keyframes')) {
      const style = document.createElement('style');
      style.id = 'fu-particle-keyframes';
      style.textContent = `
        @keyframes fuFloatParticle {
          0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.6; }
          25% { transform: translate(${15 + Math.random() * 20}px, -${20 + Math.random() * 30}px) scale(1.2); opacity: 0.9; }
          50% { transform: translate(-${10 + Math.random() * 15}px, -${40 + Math.random() * 40}px) scale(0.8); opacity: 0.5; }
          75% { transform: translate(${5 + Math.random() * 10}px, -${15 + Math.random() * 20}px) scale(1.1); opacity: 0.8; }
        }
      `;
      document.head.appendChild(style);
    }

    document.body.appendChild(container);
  }

  /* === Scroll Reveal with IntersectionObserver === */
  function initScrollReveal() {
    const targets = document.querySelectorAll(
      '.manage-panel, .todo-card, .meter-card, .expense-stat-card, .stat-card, ' +
      '.payment-stat-card, .room-card, .booking-stat-card, .filter-bar, ' +
      '.expense-stats, .section-header'
    );

    if (!targets.length) return;

    // Add initial hidden state
    const style = document.createElement('style');
    style.textContent = `
      .fu-reveal {
        opacity: 0;
        transform: translateY(24px);
        transition: opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1),
                    transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
      }
      .fu-reveal.fu-visible {
        opacity: 1;
        transform: translateY(0);
      }
    `;
    document.head.appendChild(style);

    targets.forEach((el, i) => {
      el.classList.add('fu-reveal');
      el.style.transitionDelay = (i * 0.06) + 's';
    });

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('fu-visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.08, rootMargin: '0px 0px -40px 0px' }
    );

    targets.forEach(el => observer.observe(el));
  }

  /* === Stat Value Counter Animation === */
  function animateCounters() {
    const statValues = document.querySelectorAll('.stat-value, .stat-money');
    statValues.forEach(el => {
      const text = el.textContent.trim();
      // Match numbers (possibly with commas and decimals)
      const match = text.match(/[\d,]+(\.\d+)?/);
      if (!match) return;

      const numStr = match[0].replace(/,/g, '');
      const target = parseFloat(numStr);
      if (isNaN(target) || target === 0) return;

      const prefix = text.substring(0, text.indexOf(match[0]));
      const suffix = text.substring(text.indexOf(match[0]) + match[0].length);
      const hasDecimals = match[0].includes('.');
      const duration = 1200;
      const startTime = performance.now();

      function tick(now) {
        const elapsed = now - startTime;
        const progress = Math.min(elapsed / duration, 1);
        // Ease out cubic
        const ease = 1 - Math.pow(1 - progress, 3);
        const current = target * ease;

        if (hasDecimals) {
          el.firstChild.textContent = prefix + current.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
          }) + suffix;
        } else {
          el.firstChild.textContent = prefix + Math.round(current).toLocaleString('en-US') + suffix;
        }

        if (progress < 1) {
          requestAnimationFrame(tick);
        }
      }

      // Only animate if element is in the first viewport
      const rect = el.getBoundingClientRect();
      if (rect.top < window.innerHeight) {
        requestAnimationFrame(tick);
      }
    });
  }

  /* === Ripple Effect on Buttons === */
  function initRippleEffects() {
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('button, .btn-action, .todo-tab, .submit-btn-animated');
      if (!btn) return;

      const ripple = document.createElement('span');
      const rect = btn.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const x = e.clientX - rect.left - size / 2;
      const y = e.clientY - rect.top - size / 2;

      Object.assign(ripple.style, {
        position: 'absolute',
        width: size + 'px',
        height: size + 'px',
        borderRadius: '50%',
        background: 'rgba(255,255,255,0.35)',
        left: x + 'px',
        top: y + 'px',
        transform: 'scale(0)',
        animation: 'fuRipple 0.6s ease-out',
        pointerEvents: 'none',
        zIndex: '1000'
      });

      // Make sure parent has relative/overflow hidden
      if (getComputedStyle(btn).position === 'static') {
        btn.style.position = 'relative';
      }
      btn.style.overflow = 'hidden';

      btn.appendChild(ripple);
      ripple.addEventListener('animationend', () => ripple.remove());
    });

    // Inject ripple keyframes
    if (!document.getElementById('fu-ripple-keyframes')) {
      const style = document.createElement('style');
      style.id = 'fu-ripple-keyframes';
      style.textContent = `
        @keyframes fuRipple {
          to { transform: scale(4); opacity: 0; }
        }
      `;
      document.head.appendChild(style);
    }
  }

  /* === Magnetic hover effect on stat cards === */
  function initMagneticCards() {
    const cards = document.querySelectorAll('.expense-stat-card, .stat-card, .payment-stat-card, .booking-stat-card');
    cards.forEach(card => {
      card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        const rotateX = (y - centerY) / 15;
        const rotateY = (centerX - x) / 15;
        card.style.transform = `perspective(600px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-4px)`;
        card.style.transition = 'transform 0.1s ease-out';
      });
      card.addEventListener('mouseleave', () => {
        card.style.transform = '';
        card.style.transition = 'transform 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
      });
    });
  }

  /* === Initialize on DOM Ready === */
  function init() {
    createParticlesLayer();
    initScrollReveal();
    animateCounters();
    initRippleEffects();
    initMagneticCards();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
