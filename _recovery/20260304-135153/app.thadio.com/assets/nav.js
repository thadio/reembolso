(() => {
  const toggles = document.querySelectorAll('.nav-toggle');
  const nav = document.getElementById('mainNav');
  if (!nav) return;

  const backdrop = document.querySelector('.nav-backdrop');
  const body = document.body;
  const triggers = nav.querySelectorAll('[data-nav-trigger]');
  const canHover = window.matchMedia('(hover: hover)').matches;
  const mobileQuery = window.matchMedia('(max-width: 980px)');
  const isMobile = () => mobileQuery.matches;

  const closeNav = () => {
    body.classList.remove('nav-open');
    toggles.forEach((button) => {
      button.setAttribute('aria-expanded', 'false');
    });
  };
  const openNav = () => {
    body.classList.add('nav-open');
    toggles.forEach((button) => {
      button.setAttribute('aria-expanded', 'true');
    });
  };

  toggles.forEach((toggle) => {
    toggle.addEventListener('click', () => {
      if (isMobile()) {
        if (body.classList.contains('nav-open')) {
          closeNav();
        } else {
          openNav();
        }
        return;
      }
      // Desktop: collapse/expand the nav. Keep aria-expanded in sync with
      // the collapsed state so both the inside toggle and the global toggle
      // reflect the actual visibility state.
      body.classList.toggle('nav-collapsed');
      const expanded = !body.classList.contains('nav-collapsed');
      toggles.forEach((button) => {
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      });
    });
  });

  const closeSiblings = (node) => {
    const parent = node.parentElement;
    if (!parent) return;
    parent.querySelectorAll(':scope > .nav-node.is-open').forEach((sibling) => {
      if (sibling !== node) {
        sibling.classList.remove('is-open');
        const trigger = sibling.querySelector(':scope > .nav-link[data-nav-trigger]');
        if (trigger) {
          trigger.setAttribute('aria-expanded', 'false');
        }
      }
    });
  };

  triggers.forEach((trigger) => {
    trigger.addEventListener('click', (event) => {
      const node = trigger.closest('.nav-node');
      if (!node) return;
      if (isMobile() || !canHover) {
        event.preventDefault();
        const isOpen = node.classList.contains('is-open');
        closeSiblings(node);
        if (isOpen) {
          node.classList.remove('is-open');
          trigger.setAttribute('aria-expanded', 'false');
        } else {
          node.classList.add('is-open');
          trigger.setAttribute('aria-expanded', 'true');
        }
      }
    });
  });

  if (backdrop) {
    backdrop.addEventListener('click', closeNav);
  }
  const closeAllFlyouts = () => {
    nav.querySelectorAll('.nav-node.is-open').forEach((node) => {
      node.classList.remove('is-open');
    });
    nav.querySelectorAll('[data-nav-trigger]').forEach((trigger) => {
      trigger.setAttribute('aria-expanded', 'false');
    });
  };

  nav.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      closeAllFlyouts();
      if (isMobile()) {
        closeNav();
      }
    });
  });
  window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      if (isMobile()) {
        closeNav();
      } else {
        body.classList.remove('nav-collapsed');
      }
      closeAllFlyouts();
    }
  });

  if (mobileQuery.addEventListener) {
    mobileQuery.addEventListener('change', () => {
      body.classList.remove('nav-open');
      if (isMobile()) {
        body.classList.remove('nav-collapsed');
      }
    });
  }
})();
