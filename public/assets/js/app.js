(() => {
  window.setTimeout(() => {
    document.querySelectorAll('.toast').forEach((toast) => {
      toast.style.transition = 'opacity 0.4s ease';
      toast.style.opacity = '0';
      window.setTimeout(() => toast.remove(), 450);
    });
  }, 4500);

  const updateDropzoneText = (zone, input) => {
    const textNode = zone.querySelector('.dropzone-text');
    if (!textNode) {
      return;
    }

    const fallback = zone.getAttribute('data-default-text') || 'Arraste e solte arquivos aqui ou clique para selecionar.';
    const count = input.files ? input.files.length : 0;

    if (count <= 0) {
      textNode.textContent = fallback;
      return;
    }

    textNode.textContent = `${count} arquivo(s) selecionado(s).`;
  };

  document.querySelectorAll('.dropzone').forEach((zone) => {
    const inputId = zone.getAttribute('data-input-id');
    if (!inputId) {
      return;
    }

    const input = document.getElementById(inputId);
    if (!(input instanceof HTMLInputElement)) {
      return;
    }

    const textNode = zone.querySelector('.dropzone-text');
    if (textNode) {
      zone.setAttribute('data-default-text', textNode.textContent || '');
    }

    zone.addEventListener('click', (event) => {
      if (event.target === input) {
        return;
      }

      input.click();
    });

    zone.addEventListener('dragover', (event) => {
      event.preventDefault();
      zone.classList.add('is-dragover');
    });

    zone.addEventListener('dragleave', () => {
      zone.classList.remove('is-dragover');
    });

    zone.addEventListener('drop', (event) => {
      event.preventDefault();
      zone.classList.remove('is-dragover');

      if (!event.dataTransfer || event.dataTransfer.files.length === 0) {
        return;
      }

      try {
        if (typeof DataTransfer === 'function') {
          const transfer = new DataTransfer();
          Array.from(event.dataTransfer.files).forEach((file) => transfer.items.add(file));
          input.files = transfer.files;
        } else {
          input.files = event.dataTransfer.files;
        }
      } catch (error) {
        return;
      }

      updateDropzoneText(zone, input);
    });

    input.addEventListener('change', () => updateDropzoneText(zone, input));
  });
})();
