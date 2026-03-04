(() => {
  const root = document.documentElement;
  const body = document.body;
  const applyMode = () => {
    const next = 'tabbar';
    root.dataset.mobileShell = next;
    root.classList.remove('mobile-shell-tabbar');
    body.classList.remove('mobile-shell-tabbar');
    root.classList.add(`mobile-shell-${next}`);
    body.classList.add(`mobile-shell-${next}`);
  };

  const openNav = () => {
    body.classList.add('nav-open');
    body.classList.remove('nav-collapsed');
    document.querySelectorAll('.nav-toggle').forEach((btn) => btn.setAttribute('aria-expanded', 'true'));
  };
  const closeNav = () => {
    body.classList.remove('nav-open');
    document.querySelectorAll('.nav-toggle').forEach((btn) => btn.setAttribute('aria-expanded', 'false'));
  };

  const isMobile = window.matchMedia('(max-width: 980px)');

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && isMobile.matches) {
      closeNav();
    }
  });

  // Tabbar handling (unused, kept for compatibility)
  const tabbar = document.querySelector('[data-mobile-tabbar]');

  // Table enhancement: add data-label to cells for stacked mobile view
  const enhanceTablesForMobile = () => {
    document.querySelectorAll('table[data-table="interactive"]').forEach((table) => {
      const headers = Array.from(table.querySelectorAll('thead th')).map((th) => th.textContent.trim());
      table.querySelectorAll('tbody tr').forEach((row) => {
        row.querySelectorAll('td').forEach((cell, idx) => {
          if (!cell.dataset.label && headers[idx]) {
            cell.dataset.label = headers[idx];
          }
        });
      });
      table.classList.add('is-mobile-ready');
    });
  };
  enhanceTablesForMobile();

  // Mobile menu open buttons
  document.querySelectorAll('[data-mobile-open-nav]').forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      openNav();
    });
  });

  // Ensure body carries initial mode
  applyMode();

  // Close nav when resizing from mobile to desktop
  if (isMobile.addEventListener) {
    isMobile.addEventListener('change', (event) => {
      if (!event.matches) {
        closeNav();
      }
    });
  }
})();
