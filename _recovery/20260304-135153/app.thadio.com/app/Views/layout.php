<?php
// Variáveis disponíveis: $title, $content
$assetVersion = static function (string $relativePath): string {
    $fullPath = dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
    $mtime = is_file($fullPath) ? filemtime($fullPath) : null;
    return $mtime ? (string) $mtime : '1';
};
$thumbnailMax = (int) (getenv('THUMBNAIL_MAX_SIZE') ?: getenv('PRODUCT_IMAGE_MAX_DIMENSION') ?: 600);
$thumbnailMax = max(64, min(4000, $thumbnailMax));
$viewerMax = (int) (getenv('PRODUCT_IMAGE_MAX_DIMENSION') ?: $thumbnailMax);
$viewerMax = max(64, min(4000, $viewerMax));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($title ?? 'Retrato App', ENT_QUOTES, 'UTF-8'); ?></title>
<?php $appCssVersion = $assetVersion('assets/app.css'); ?>
  <link rel="icon" href="/favicon.ico">
  <style>
    :root {
      --ink: #0f172a;
      --muted: #667085;
      --panel: #ffffff;
      --line: #e4e7ec;
      --accent: #3f7cff;
      --accent-2: #00c6ae;
      --bg: linear-gradient(135deg, #f7f9ff 0%, #eef7ff 50%, #f7fffa 100%);
      --radius: 18px;
      --shadow: 0 16px 48px rgba(15, 23, 42, 0.12);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: "Space Grotesk","Segoe UI",system-ui,sans-serif;
      background: var(--bg);
      color: var(--ink);
      min-height: 100vh;
      padding: 12px;
    }

    .layout {
      max-width: 100%;
      margin: 0;
      display: grid;
      grid-template-columns: var(--nav-width, minmax(200px, 240px)) minmax(0, 1fr);
      gap: 12px;
    }
  </style>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="preload" href="assets/app.css?v=<?php echo $appCssVersion; ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link rel="stylesheet" href="assets/app.css?v=<?php echo $appCssVersion; ?>">
  </noscript>
  <script>
    (function () {
      const defaultSize = 150;
      const thumbMax = 500;
      const viewerMax = <?php echo $viewerMax; ?>;
      const hasSource = (value) => {
        const source = String(value || '').trim();
        return source !== '';
      };
      const buildThumbUrl = (src, size = defaultSize) => {
        const trimmed = String(src || '').trim();
        if (!hasSource(trimmed)) {
          return '';
        }
        return buildProxyUrl(trimmed, size);
      };
      const encodeBase64Url = (value) => {
        try {
          const base = btoa(unescape(encodeURIComponent(String(value || ''))));
          return base.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
        } catch {
          return '';
        }
      };

      const buildProxyUrl = (src, size, maxSize = thumbMax) => {
        if (!hasSource(src)) {
          return '';
        }
        const normalizedSize = Math.max(32, Math.min(maxSize, Number(size) || defaultSize));
        const encoded = encodeBase64Url(src);
        if (encoded) {
          return `thumbnail.php?u=${encoded}&size=${normalizedSize}`;
        }
        return `thumbnail.php?src=${encodeURIComponent(String(src || ''))}&size=${normalizedSize}`;
      };

      const attach = (img, options = {}) => {
        if (!(img instanceof HTMLImageElement)) {
          return;
        }
        const fullSrc = String(options.fullSrc || img.dataset.thumbFull || '').trim();
        if (!fullSrc) {
          return;
        }
        const size = Number(options.size || img.dataset.thumbSize || defaultSize) || defaultSize;
        const thumbSrc = String(options.thumbSrc || img.dataset.thumbSrc || '').trim();
        const proxySrc = String(options.thumbProxy || img.dataset.thumbProxy || buildProxyUrl(fullSrc, size)).trim();
        const resolvedThumb = thumbSrc || buildThumbUrl(fullSrc, size) || '';

        if (!img.src) {
          img.src = resolvedThumb || fullSrc;
        }
        img.dataset.thumbFull = fullSrc;
        img.dataset.thumbSize = String(size);
        img.dataset.thumbProxy = proxySrc;
        img.dataset.thumbSrc = resolvedThumb;
        img.loading = img.loading || 'lazy';
        img.decoding = img.decoding || 'async';

        const handleError = () => {
          if (proxySrc && img.src !== proxySrc && !img.dataset.thumbProxyTried) {
            img.dataset.thumbProxyTried = '1';
            img.src = proxySrc;
            return;
          }
          if (fullSrc && img.src !== fullSrc && !img.dataset.thumbFullTried) {
            img.dataset.thumbFullTried = '1';
            img.src = fullSrc;
          }
        };

        img.addEventListener('error', handleError);
      };

      const createElement = (options = {}) => {
        const img = document.createElement('img');
        const fullSrc = String(options.fullSrc || '').trim();
        const size = Number(options.size || defaultSize) || defaultSize;
        const thumbSrc = String(options.thumbSrc || '').trim();
        const alt = String(options.alt || '');
        if (alt !== '') {
          img.alt = alt;
        }
        if (thumbSrc) {
          img.src = thumbSrc;
        }
        attach(img, {
          fullSrc,
          thumbSrc,
          size,
        });
        return img;
      };

      const autoAttach = () => {
        document.querySelectorAll('img[data-thumb-full]').forEach((img) => attach(img));
      };

      window.RetratoThumbnail = {
        buildThumbUrl,
        thumbProxyUrl: buildProxyUrl,
        imageProxyUrl: (src, size) => buildProxyUrl(src, size, viewerMax),
        viewerMax,
        attach,
        createElement,
        autoAttach,
      };

      document.addEventListener('DOMContentLoaded', autoAttach);
    })();
  </script>
  <script src="assets/nav.js?v=<?php echo $assetVersion('assets/nav.js'); ?>" defer></script>
  <script src="assets/number.js?v=<?php echo $assetVersion('assets/number.js'); ?>" defer></script>
  <script src="assets/table.js?v=<?php echo $assetVersion('assets/table.js'); ?>" defer></script>
  <script src="assets/image-viewer.js?v=<?php echo $assetVersion('assets/image-viewer.js'); ?>" defer></script>
  <script src="assets/cep-lookup.js?v=<?php echo $assetVersion('assets/cep-lookup.js'); ?>" defer></script>
  <script src="assets/mobile-shell.js?v=<?php echo $assetVersion('assets/mobile-shell.js'); ?>" defer></script>
</head>
<body>
  <svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden;pointer-events:none;">
    <symbol id="icon-edit" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 20h4l10-10-4-4L4 16z"></path>
      <path d="M14 6 18 10"></path>
    </symbol>
    <symbol id="icon-trash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 7h16"></path>
      <path d="M10 11v6m4-6v6"></path>
      <path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12"></path>
      <path d="M9 7V4h6v3"></path>
    </symbol>
    <symbol id="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12Z"></path>
      <circle cx="12" cy="12" r="3"></circle>
    </symbol>
    <symbol id="icon-external" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M15 3h6v6"></path>
      <path d="M10 14 21 3"></path>
      <path d="M5 5h5"></path>
      <path d="M5 21h14V9"></path>
    </symbol>
    <symbol id="icon-restore" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 10a8 8 0 0 1 14.31-4.66"></path>
      <path d="M20 4v6h-6"></path>
      <path d="M20 14a8 8 0 0 1-14.31 4.66"></path>
      <path d="M10 20H4v-6"></path>
    </symbol>
    <symbol id="icon-filter-multi" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 5h16L14 13v4l-4 4v-8z"></path>
      <path d="M9 5l3 4 3-4"></path>
      <path d="M10 15h4"></path>
    </symbol>
    <symbol id="icon-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="m5 13 4 4L19 7"></path>
    </symbol>
    <symbol id="icon-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M18 6 6 18"></path>
      <path d="M6 6l12 12"></path>
    </symbol>
    <symbol id="icon-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="8"></circle>
      <path d="M12 8v5l3 3"></path>
    </symbol>
    <symbol id="icon-file" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M6 2h8l4 4v16H6z"></path>
      <path d="M14 2v6h6"></path>
    </symbol>
    <symbol id="nav-icon-home" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M3 10.5L12 3l9 7.5"></path>
      <path d="M5 9.5V21h14V9.5"></path>
    </symbol>
    <symbol id="nav-icon-orders" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="4" width="18" height="16" rx="2"></rect>
      <path d="M7 8h10"></path>
      <path d="M7 12h10"></path>
      <path d="M7 16h6"></path>
    </symbol>
    <symbol id="nav-icon-products" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 7l-8 4-8-4"></path>
      <path d="M4 7l8-4 8 4v10l-8 4-8-4z"></path>
    </symbol>
    <symbol id="nav-icon-vendors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 21h16"></path>
      <path d="M6 21V7h12v14"></path>
      <path d="M8 11h2"></path>
      <path d="M14 11h2"></path>
      <path d="M8 15h2"></path>
      <path d="M14 15h2"></path>
    </symbol>
    <symbol id="nav-icon-customers" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"></path>
      <path d="M16 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"></path>
      <path d="M2 21c0-4 4-7 8-7"></path>
      <path d="M13 21c0-3 3-5 6-5"></path>
    </symbol>
    <symbol id="nav-icon-misc" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="5" r="1.8"></circle>
      <circle cx="12" cy="12" r="1.8"></circle>
      <circle cx="12" cy="19" r="1.8"></circle>
    </symbol>
    <symbol id="nav-icon-params" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 6h16"></path>
      <path d="M4 12h16"></path>
      <path d="M4 18h16"></path>
      <circle cx="9" cy="6" r="2"></circle>
      <circle cx="15" cy="12" r="2"></circle>
      <circle cx="7" cy="18" r="2"></circle>
    </symbol>
    <symbol id="nav-icon-admin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 3l8 4v6c0 4.5-3 7.5-8 9-5-1.5-8-4.5-8-9V7l8-4z"></path>
      <path d="M9 12l2 2 4-4"></path>
    </symbol>
    <symbol id="nav-icon-time" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="8"></circle>
      <path d="M12 8v5l3 3"></path>
    </symbol>
  </svg>
  <button class="nav-toggle nav-toggle--outside" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="mainNav">
    <span></span>
    <span></span>
    <span></span>
  </button>
  <div class="nav-backdrop" data-nav-backdrop aria-hidden="true"></div>
  <div class="layout">
    <?php include dirname(__DIR__, 2) . '/nav.php'; ?>
    <main class="panel">
      <?php echo $content; ?>
    </main>
  </div>
</body>
</html>
