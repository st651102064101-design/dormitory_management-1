/**
 * Particle Effects - Floating Colorful Orbs
 * JavaScript controller for dynamic particle creation
 * 
 * Usage:
 * 
 * 1. Auto-initialize on page load:
 *    <div class="particle-container" data-particles="20"></div>
 * 
 * 2. Manual initialization:
 *    ParticleEffects.init(element, options);
 * 
 * 3. Global initialization:
 *    ParticleEffects.initAll();
 */

(function(window) {
  'use strict';

  const ParticleEffects = {
    /**
     * Default configuration
     */
    defaults: {
      count: 15,              // จำนวน particles
      minSize: 1,            // ขนาดต่ำสุด (px)
      maxSize: 6,            // ขนาดสูงสุด (px)
      minDuration: 15,        // ระยะเวลาต่ำสุด (seconds)
      maxDuration: 50,        // ระยะเวลาสูงสุด (seconds)
      colors: 12,             // จำนวนสีที่มี (1-12)
      animations: 6,          // จำนวน animation variations (1-6)
      pulseChance: 0.2,       // โอกาสที่จะมี pulse effect (0-1)
      spawnDelay: 50,        // ระยะเวลาระหว่างการสร้าง particle แต่ละตัว (ms)
      autoInit: true          // เริ่มต้นอัตโนมัติเมื่อโหลดหน้า
    },

    /**
     * Initialize particles in a container
     * @param {HTMLElement} container - Container element
     * @param {Object} options - Configuration options
     */
    init: function(container, options = {}) {
      if (!container) return;

      // Merge options with defaults
      const config = Object.assign({}, this.defaults, options);

      // Get particle count from data attribute or config
      const count = parseInt(container.dataset.particles) || config.count;

      // Clear existing particles
      this.clear(container);

      // Create particles with staggered delay
      for (let i = 0; i < count; i++) {
        setTimeout(() => {
          this.createParticle(container, config);
        }, i * config.spawnDelay);
      }

      // Mark as initialized
      container.dataset.particlesInitialized = 'true';
    },

    /**
     * Create a single particle
     * @param {HTMLElement} container - Container element
     * @param {Object} config - Configuration
     */
    createParticle: function(container, config) {
      const particle = document.createElement('div');
      particle.className = 'particle-orb';

      // Random position (horizontal)
      const left = Math.random() * 100;
      particle.style.left = `${left}%`;

      // Random vertical start position
      const startY = Math.random() * 20 + 100; // Start below viewport
      particle.style.bottom = `-${startY}px`;

      // Random size
      const size = Math.random() * (config.maxSize - config.minSize) + config.minSize;
      particle.style.width = `${size}px`;
      particle.style.height = `${size}px`;

      // Random color (1-12)
      const colorIndex = Math.floor(Math.random() * config.colors) + 1;
      particle.classList.add(`color-${colorIndex}`);

      // Random animation (1-6)
      const animIndex = Math.floor(Math.random() * config.animations) + 1;
      particle.classList.add(`anim-${animIndex}`);

      // Random duration
      const duration = Math.random() * (config.maxDuration - config.minDuration) + config.minDuration;
      particle.style.setProperty('--duration', `${duration}s`);

      // Random delay
      const delay = Math.random() * 5;
      particle.style.animationDelay = `${delay}s`;

      // Add pulse effect randomly
      if (Math.random() < config.pulseChance) {
        particle.classList.add('has-pulse');
      }

      // Add size class for better control
      if (size < 50) {
        particle.classList.add('size-sm');
      } else if (size < 70) {
        particle.classList.add('size-md');
      } else if (size < 90) {
        particle.classList.add('size-lg');
      } else {
        particle.classList.add('size-xl');
      }

      // Append to container
      container.appendChild(particle);

      // Remove and recreate after animation completes for infinite loop
      particle.addEventListener('animationiteration', () => {
        // Randomize properties on each iteration
        const newLeft = Math.random() * 100;
        particle.style.left = `${newLeft}%`;
        
        const newDuration = Math.random() * (config.maxDuration - config.minDuration) + config.minDuration;
        particle.style.setProperty('--duration', `${newDuration}s`);
      });
    },

    /**
     * Clear all particles from container
     * @param {HTMLElement} container - Container element
     */
    clear: function(container) {
      if (!container) return;
      const particles = container.querySelectorAll('.particle-orb');
      particles.forEach(p => p.remove());
      delete container.dataset.particlesInitialized;
    },

    /**
     * Reinitialize particles in a container
     * @param {HTMLElement} container - Container element
     * @param {Object} options - Configuration options
     */
    reinit: function(container, options = {}) {
      this.clear(container);
      this.init(container, options);
    },

    /**
     * Initialize all containers with particle classes
     */
    initAll: function() {
      // Initialize elements with .particle-container
      const containers = document.querySelectorAll('.particle-container:not([data-particles-initialized])');
      containers.forEach(container => {
        this.init(container);
      });

      // Initialize elements with .has-particles
      const hasParticles = document.querySelectorAll('.has-particles:not([data-particles-initialized])');
      hasParticles.forEach(container => {
        this.init(container);
      });
    },

    /**
     * Add particles to an existing element
     * @param {HTMLElement|string} target - Element or selector
     * @param {Object} options - Configuration options
     */
    add: function(target, options = {}) {
      const element = typeof target === 'string' ? document.querySelector(target) : target;
      if (!element) return;

      element.classList.add('particle-container');
      this.init(element, options);
    },

    /**
     * Remove particles from an element
     * @param {HTMLElement|string} target - Element or selector
     */
    remove: function(target) {
      const element = typeof target === 'string' ? document.querySelector(target) : target;
      if (!element) return;

      this.clear(element);
      element.classList.remove('particle-container', 'has-particles');
    }
  };

  // Auto-initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      if (ParticleEffects.defaults.autoInit) {
        ParticleEffects.initAll();
      }
    });
  } else {
    if (ParticleEffects.defaults.autoInit) {
      ParticleEffects.initAll();
    }
  }

  // Expose to global scope
  window.ParticleEffects = ParticleEffects;

})(window);
