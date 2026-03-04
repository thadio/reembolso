(() => {
  const DEFAULT_EXPORT_SIZE = window.RetratoThumbnail?.viewerMax || 2868;
  const MAX_EXPORT_SIZE = Number(DEFAULT_EXPORT_SIZE) || 2868;
  const stateDefaults = () => ({
    rotation: 0,
    flipX: false,
    flipY: false,
    zoom: 1,
    brightness: 1,
    cropSquare: false,
  });

  let viewer = null;

  const resolveOrigin = (value) => {
    try {
      return new URL(value, window.location.href).origin;
    } catch {
      return '';
    }
  };

  const isCrossOrigin = (value) => {
    const origin = resolveOrigin(value);
    return origin !== '' && origin !== window.location.origin;
  };

  const resolveImageSrc = (src) => {
    if (!src) return '';
    if (!isCrossOrigin(src)) return src;
    if (window.RetratoThumbnail && typeof window.RetratoThumbnail.imageProxyUrl === 'function') {
      return window.RetratoThumbnail.imageProxyUrl(src, MAX_EXPORT_SIZE);
    }
    return src;
  };

  const loadImage = (src) =>
    new Promise((resolve, reject) => {
      const img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('Falha ao carregar imagem.'));
      img.src = resolveImageSrc(src);
    });

  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

  const computeOutputSize = (imgW, imgH, cropSquare, rotation, maxSize) => {
    const isRotated = Math.abs(rotation) % 180 === 90;
    let outW = imgW;
    let outH = imgH;

    if (cropSquare) {
      const size = Math.min(imgW, imgH);
      outW = size;
      outH = size;
    } else if (isRotated) {
      outW = imgH;
      outH = imgW;
    }

    const scale = Math.min(1, maxSize / Math.max(outW, outH));
    return {
      outW: Math.max(1, Math.round(outW * scale)),
      outH: Math.max(1, Math.round(outH * scale)),
    };
  };

  const computeScale = (outW, outH, imgW, imgH, rotation, zoom, cropSquare) => {
    const isRotated = Math.abs(rotation) % 180 === 90;
    const baseW = isRotated ? imgH : imgW;
    const baseH = isRotated ? imgW : imgH;
    const fit = cropSquare ? Math.max(outW / baseW, outH / baseH) : Math.min(outW / baseW, outH / baseH);
    return fit * zoom;
  };

  const drawImage = (canvas, img, state, maxSize) => {
    const { outW, outH } = computeOutputSize(
      img.naturalWidth,
      img.naturalHeight,
      state.cropSquare,
      state.rotation,
      maxSize
    );
    canvas.width = outW;
    canvas.height = outH;

    const ctx = canvas.getContext('2d');
    ctx.save();
    ctx.clearRect(0, 0, outW, outH);
    ctx.translate(outW / 2, outH / 2);
    ctx.scale(state.flipX ? -1 : 1, state.flipY ? -1 : 1);
    ctx.rotate((state.rotation * Math.PI) / 180);
    ctx.filter = `brightness(${state.brightness})`;

    const scale = computeScale(
      outW,
      outH,
      img.naturalWidth,
      img.naturalHeight,
      state.rotation,
      state.zoom,
      state.cropSquare
    );
    const drawW = img.naturalWidth * scale;
    const drawH = img.naturalHeight * scale;
    ctx.drawImage(img, -drawW / 2, -drawH / 2, drawW, drawH);
    ctx.restore();
  };

  const buildViewer = () => {
    const modal = document.createElement('div');
    modal.className = 'image-viewer';
    modal.innerHTML = `
      <div class="image-viewer__backdrop" data-close></div>
      <div class="image-viewer__panel" role="dialog" aria-modal="true" aria-label="Visualizacao da imagem">
        <div class="image-viewer__header">
          <div class="image-viewer__title" data-title>Imagem</div>
          <button type="button" class="icon-btn neutral" data-close aria-label="Fechar">
            <svg aria-hidden="true"><use href="#icon-x"></use></svg>
          </button>
        </div>
        <div class="image-viewer__content">
          <div class="image-viewer__preview">
            <canvas data-canvas></canvas>
            <div class="image-viewer__loading" data-loading>Carregando...</div>
          </div>
          <div class="image-viewer__controls">
            <div class="image-viewer__row">
              <button type="button" class="image-viewer__btn" data-action="rotate-left">Girar -90</button>
              <button type="button" class="image-viewer__btn" data-action="rotate-right">Girar +90</button>
              <button type="button" class="image-viewer__btn" data-action="flip-x">Espelhar H</button>
              <button type="button" class="image-viewer__btn" data-action="flip-y">Espelhar V</button>
              <button type="button" class="image-viewer__btn" data-action="reset">Reset</button>
            </div>
            <div class="image-viewer__row">
              <label class="image-viewer__label">
                Zoom
                <input type="range" min="0.5" max="2.5" step="0.05" value="1" data-zoom>
              </label>
              <span class="image-viewer__value" data-zoom-label>100%</span>
            </div>
            <div class="image-viewer__row">
              <label class="image-viewer__label">
                Brilho
                <input type="range" min="0.7" max="1.3" step="0.05" value="1" data-brightness>
              </label>
              <span class="image-viewer__value" data-brightness-label>100%</span>
            </div>
            <div class="image-viewer__row">
              <label class="image-viewer__toggle">
                <input type="checkbox" data-crop>
                Corte quadrado
              </label>
              <label class="image-viewer__toggle" data-edit-only>
                <input type="checkbox" data-replace checked>
                Substituir imagem original
              </label>
            </div>
            <div class="image-viewer__row">
              <button type="button" class="image-viewer__btn" data-action="cover" data-cover-only>Definir como capa</button>
              <button type="button" class="btn primary" data-action="save" data-edit-only>Salvar edicao</button>
            </div>
            <div class="image-viewer__note" data-note></div>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);

    const elements = {
      modal,
      title: modal.querySelector('[data-title]'),
      canvas: modal.querySelector('[data-canvas]'),
      loading: modal.querySelector('[data-loading]'),
      zoom: modal.querySelector('[data-zoom]'),
      zoomLabel: modal.querySelector('[data-zoom-label]'),
      brightness: modal.querySelector('[data-brightness]'),
      brightnessLabel: modal.querySelector('[data-brightness-label]'),
      crop: modal.querySelector('[data-crop]'),
      replace: modal.querySelector('[data-replace]'),
      note: modal.querySelector('[data-note]'),
      buttons: {
        rotateLeft: modal.querySelector('[data-action="rotate-left"]'),
        rotateRight: modal.querySelector('[data-action="rotate-right"]'),
        flipX: modal.querySelector('[data-action="flip-x"]'),
        flipY: modal.querySelector('[data-action="flip-y"]'),
        reset: modal.querySelector('[data-action="reset"]'),
        cover: modal.querySelector('[data-action="cover"]'),
        save: modal.querySelector('[data-action="save"]'),
      },
    };

    modal.querySelectorAll('[data-close]').forEach((btn) => {
      btn.addEventListener('click', () => closeViewer(elements));
    });

    elements.state = stateDefaults();
    return elements;
  };

  const setEditVisibility = (elements, editable) => {
    elements.modal.classList.toggle('is-editable', editable);
    elements.modal.querySelectorAll('[data-edit-only]').forEach((el) => {
      el.style.display = editable ? '' : 'none';
    });
  };

  const setCoverVisibility = (elements, hasCover) => {
    elements.modal.querySelectorAll('[data-cover-only]').forEach((el) => {
      el.style.display = hasCover ? '' : 'none';
    });
  };

  const resetState = (elements, state) => {
    const fresh = stateDefaults();
    Object.assign(state, fresh);
    elements.zoom.value = String(fresh.zoom);
    elements.brightness.value = String(fresh.brightness);
    elements.crop.checked = fresh.cropSquare;
    if (elements.replace) {
      elements.replace.checked = true;
    }
  };

  const renderPreview = (elements, image, state) => {
    if (!image) return;
    const maxW = clamp(window.innerWidth - 120, 280, 980);
    const maxH = clamp(window.innerHeight - 220, 280, 760);
    const previewMax = Math.min(maxW, maxH);
    drawImage(elements.canvas, image, state, Math.floor(previewMax));
    elements.zoomLabel.textContent = `${Math.round(state.zoom * 100)}%`;
    elements.brightnessLabel.textContent = `${Math.round(state.brightness * 100)}%`;
  };

  const openViewer = async (options) => {
    if (!viewer) {
      viewer = buildViewer();
      bindViewerControls();
    }

    const elements = viewer;
    elements.note.textContent = '';
    elements.loading.style.display = 'block';
    elements.modal.classList.add('is-open');
    document.body.classList.add('image-viewer-open');
    elements.title.textContent = options.label || 'Imagem';

    const resolvedSrc = resolveImageSrc(options.src || '');
    const canEdit = Boolean(options.editable) && (!isCrossOrigin(options.src || '') || resolvedSrc !== options.src);

    elements.active = {
      card: options.card || null,
      editable: canEdit,
      fileInput: options.fileInput || null,
      removeInput: options.removeInput || null,
      coverInput: options.coverInput || null,
    };

    setEditVisibility(elements, elements.active.editable);
    setCoverVisibility(elements, Boolean(elements.active.coverInput));
    resetState(elements, elements.state);

    try {
      const image = await loadImage(options.src);
      elements.image = image;
      elements.loading.style.display = 'none';
      renderPreview(elements, image, elements.state);
    } catch (err) {
      elements.loading.style.display = 'none';
      elements.note.textContent = err.message || 'Falha ao carregar imagem.';
    }
  };

  const closeViewer = (elements) => {
    if (!elements) return;
    elements.modal.classList.remove('is-open');
    document.body.classList.remove('image-viewer-open');
    elements.note.textContent = '';
  };

  const updateNote = (elements, message) => {
    elements.note.textContent = message;
  };

  const applyEditToFile = (elements) => {
    if (!elements.image || !elements.active.editable || !elements.active.fileInput) {
      return;
    }

    const exportCanvas = document.createElement('canvas');
    drawImage(exportCanvas, elements.image, elements.state, MAX_EXPORT_SIZE);

    try {
      exportCanvas.toBlob(
        (blob) => {
          if (!blob) {
            updateNote(elements, 'Nao foi possivel gerar a imagem editada.');
            return;
          }
          const fileInput = elements.active.fileInput;
          const dt = new DataTransfer();
          Array.from(fileInput.files || []).forEach((file) => dt.items.add(file));
          const fileName = `imagem-editada-${Date.now()}.jpg`;
          const file = new File([blob], fileName, { type: 'image/jpeg' });
          dt.items.add(file);
          fileInput.files = dt.files;

          if (elements.active.removeInput && elements.replace.checked) {
            elements.active.removeInput.checked = true;
          }

          if (elements.active.card) {
            const imgEl = elements.active.card.querySelector('img');
            if (imgEl) {
              const previousUrl = imgEl.dataset.previewUrl || '';
              if (previousUrl) {
                URL.revokeObjectURL(previousUrl);
              }
              const previewUrl = URL.createObjectURL(file);
              imgEl.src = previewUrl;
              imgEl.dataset.previewUrl = previewUrl;
            }
            elements.active.card.classList.add('is-edited');
          }

          updateNote(elements, 'Edicao adicionada ao upload.');
        },
        'image/jpeg',
        0.9
      );
    } catch (err) {
      updateNote(elements, 'Edicao bloqueada por restricao de imagem (CORS).');
    }
  };

  const attachListeners = () => {
    document.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof Element)) return;
      const card = target.closest('[data-image-viewer]');
      if (!card) return;
      if (target.closest('[data-image-viewer-ignore]')) return;
      event.preventDefault();
      const src = card.dataset.imageSrc || '';
      if (!src) return;
      const label = card.dataset.imageLabel || card.getAttribute('aria-label') || '';
      const editable = card.dataset.imageEditable === 'true';
      const form = card.closest('form');
      const fileInput = form ? form.querySelector('input[type="file"][name="product_images[]"]') : null;
      const removeInput = card.querySelector('input[type="checkbox"][name^="remove_image_"]');
      const coverInput = card.querySelector('input[type="radio"][name="cover_image"]');
      openViewer({
        src,
        label,
        card,
        editable: editable && Boolean(fileInput),
        fileInput,
        removeInput,
        coverInput,
      });
    });

    document.addEventListener('keydown', (event) => {
      if (!viewer || !viewer.modal.classList.contains('is-open')) return;
      if (event.key === 'Escape') {
        closeViewer(viewer);
      }
    });

    window.addEventListener('resize', () => {
      if (!viewer || !viewer.modal.classList.contains('is-open')) return;
      if (!viewer.image) return;
      renderPreview(viewer, viewer.image, viewer.state);
    });
  };

  const bindViewerControls = () => {
    if (!viewer) return;
    const elements = viewer;
    const state = elements.state;

    elements.buttons.rotateLeft.addEventListener('click', () => {
      state.rotation = (state.rotation - 90 + 360) % 360;
      renderPreview(elements, elements.image, state);
    });
    elements.buttons.rotateRight.addEventListener('click', () => {
      state.rotation = (state.rotation + 90) % 360;
      renderPreview(elements, elements.image, state);
    });
    elements.buttons.flipX.addEventListener('click', () => {
      state.flipX = !state.flipX;
      renderPreview(elements, elements.image, state);
    });
    elements.buttons.flipY.addEventListener('click', () => {
      state.flipY = !state.flipY;
      renderPreview(elements, elements.image, state);
    });
    elements.buttons.reset.addEventListener('click', () => {
      resetState(elements, state);
      renderPreview(elements, elements.image, state);
    });
    elements.buttons.cover.addEventListener('click', () => {
      if (elements.active.coverInput) {
        elements.active.coverInput.checked = true;
        updateNote(elements, 'Imagem definida como capa.');
      }
    });
    elements.buttons.save.addEventListener('click', () => {
      applyEditToFile(elements);
    });

    elements.zoom.addEventListener('input', () => {
      state.zoom = parseFloat(elements.zoom.value) || 1;
      renderPreview(elements, elements.image, state);
    });
    elements.brightness.addEventListener('input', () => {
      state.brightness = parseFloat(elements.brightness.value) || 1;
      renderPreview(elements, elements.image, state);
    });
    elements.crop.addEventListener('change', () => {
      state.cropSquare = elements.crop.checked;
      renderPreview(elements, elements.image, state);
    });
  };

  const init = () => {
    attachListeners();
    document.addEventListener('DOMContentLoaded', () => {
      if (!viewer) {
        viewer = buildViewer();
        viewer.state = stateDefaults();
        bindViewerControls();
        closeViewer(viewer);
      }
    });
  };

  init();
})();
