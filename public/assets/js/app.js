(() => {
  window.setTimeout(() => {
    document.querySelectorAll('.toast').forEach((toast) => {
      toast.style.transition = 'opacity 0.4s ease';
      toast.style.opacity = '0';
      window.setTimeout(() => toast.remove(), 450);
    });
  }, 4500);
})();
