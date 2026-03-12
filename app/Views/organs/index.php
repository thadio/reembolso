<?php

declare(strict_types=1);

$filters = $filters ?? [
    'q' => '',
    'sort' => 'name',
    'dir' => 'asc',
    'per_page' => 25,
    'type' => '',
    'state' => '',
];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 1];
$organs = is_array($organs ?? null) ? $organs : [];

$query = trim((string) ($filters['q'] ?? ''));
$sort = (string) ($filters['sort'] ?? 'name');
$dir = (string) ($filters['dir'] ?? 'asc');
$perPage = (int) ($filters['per_page'] ?? 25);
$typeFilter = trim((string) ($filters['type'] ?? ''));
$stateFilter = trim((string) ($filters['state'] ?? ''));

$typeOptions = [
    '' => 'Todos os tipos',
    'administracao_direta' => 'Administração direta',
    'autarquia' => 'Autarquia',
    'autarquia_especial' => 'Autarquia especial',
    'fundacao_publica' => 'Fundação pública',
    'empresa_publica' => 'Empresa pública',
    'sociedade_economia_mista' => 'Sociedade de economia mista',
];

$stateOptions = [
    '' => 'Brasil',
    'AC' => 'AC',
    'AL' => 'AL',
    'AP' => 'AP',
    'AM' => 'AM',
    'BA' => 'BA',
    'CE' => 'CE',
    'DF' => 'DF',
    'ES' => 'ES',
    'GO' => 'GO',
    'MA' => 'MA',
    'MT' => 'MT',
    'MS' => 'MS',
    'MG' => 'MG',
    'PA' => 'PA',
    'PB' => 'PB',
    'PR' => 'PR',
    'PE' => 'PE',
    'PI' => 'PI',
    'RJ' => 'RJ',
    'RN' => 'RN',
    'RS' => 'RS',
    'RO' => 'RO',
    'RR' => 'RR',
    'SC' => 'SC',
    'SP' => 'SP',
    'SE' => 'SE',
    'TO' => 'TO',
];

$levelLabelMap = [
    'federal' => 'Federal',
    'estadual' => 'Estadual',
    'municipal' => 'Municipal',
    'distrital' => 'Distrital',
];

$buildUrl = static function (array $replace = []) use ($query, $sort, $dir, $perPage, $typeFilter, $stateFilter, $pagination): string {
    $params = [
        'q' => $query,
        'type' => $typeFilter,
        'state' => $stateFilter,
        'sort' => $sort,
        'dir' => $dir,
        'per_page' => (string) $perPage,
        'page' => (string) ($pagination['page'] ?? 1),
    ];

    foreach ($replace as $key => $value) {
        $params[$key] = (string) $value;
    }

    return url('/organs?' . http_build_query($params));
};

$nextDir = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return 'asc';
    }

    return $dir === 'asc' ? 'desc' : 'asc';
};

$sortIndicator = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return '';
    }

    return $dir === 'asc' ? '▲' : '▼';
};

$formatToken = static function (string $value): string {
    $normalized = trim(str_replace('_', ' ', $value));
    if ($normalized === '') {
        return '';
    }

    return mb_convert_case(mb_strtolower($normalized), MB_CASE_TITLE, 'UTF-8');
};

$formatName = static function (string $value): string {
    $clean = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    if ($clean === '') {
        return '';
    }

    if (preg_match('/^[\p{Lu}\s\d\-\.\/&]+$/u', $clean) === 1) {
        return mb_convert_case(mb_strtolower($clean), MB_CASE_TITLE, 'UTF-8');
    }

    return $clean;
};

$formatCnpj = static function (string $value): string {
    $clean = trim($value);
    if ($clean === '') {
        return '';
    }

    $digits = preg_replace('/\D+/', '', $clean);
    if (!is_string($digits) || mb_strlen($digits) !== 14) {
        return $clean;
    }

    return substr($digits, 0, 2)
        . '.' . substr($digits, 2, 3)
        . '.' . substr($digits, 5, 3)
        . '/' . substr($digits, 8, 4)
        . '-' . substr($digits, 12, 2);
};

$total = max(0, (int) ($pagination['total'] ?? 0));
$currentPage = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['pages'] ?? 1));
$firstItem = $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
$lastItem = $total > 0 ? min($total, $currentPage * $perPage) : 0;
$hasActiveFilters = $query !== '' || $typeFilter !== '' || $stateFilter !== '';

$visiblePages = 5;
$startPage = max(1, $currentPage - intdiv($visiblePages, 2));
$endPage = min($totalPages, $startPage + $visiblePages - 1);
$startPage = max(1, $endPage - $visiblePages + 1);
?>
<div class="card organs-list-card">
  <div class="header-row organs-header-row">
    <div>
      <h2>Órgãos</h2>
      <p class="muted">Gerencie os órgãos cadastrados no sistema.</p>
    </div>
    <?php if (($canManage ?? false) === true): ?>
      <a class="btn btn-primary organs-new-btn" href="<?= e(url('/organs/create')) ?>">
        <span class="organs-new-btn-icon" aria-hidden="true">+</span>
        <span>Novo órgão</span>
      </a>
    <?php endif; ?>
  </div>

  <form method="get" action="<?= e(url('/organs')) ?>" class="organs-filters" data-organs-auto-submit>
    <input type="hidden" name="page" value="1">

    <div class="organs-search-shell">
      <span class="organs-search-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" role="presentation" focusable="false">
          <circle cx="11" cy="11" r="7"></circle>
          <path d="M20 20l-4-4"></path>
        </svg>
      </span>
      <input
        id="organs-search-input"
        name="q"
        type="text"
        value="<?= e($query) ?>"
        placeholder="Buscar órgão (nome, sigla, CNPJ ou SIAPE)"
        autocomplete="off"
      >
      <button
        type="button"
        class="organs-search-clear <?= $query !== '' ? 'is-visible' : '' ?>"
        data-organs-clear-search
        aria-label="Limpar busca"
        title="Limpar busca"
      >
        <svg viewBox="0 0 24 24" role="presentation" focusable="false">
          <path d="M6 6l12 12"></path>
          <path d="M18 6L6 18"></path>
        </svg>
      </button>
    </div>

    <div class="organs-controls-row">
      <label class="organs-control">
        <span>Ordenar</span>
        <select name="sort" data-organs-submit-on-change>
          <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Nome</option>
          <option value="acronym" <?= $sort === 'acronym' ? 'selected' : '' ?>>Sigla</option>
          <option value="cnpj" <?= $sort === 'cnpj' ? 'selected' : '' ?>>CNPJ</option>
          <option value="siape_code" <?= $sort === 'siape_code' ? 'selected' : '' ?>>SIAPE</option>
          <option value="organ_type" <?= $sort === 'organ_type' ? 'selected' : '' ?>>Tipo</option>
          <option value="state" <?= $sort === 'state' ? 'selected' : '' ?>>UF</option>
          <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Cadastro</option>
        </select>
      </label>

      <label class="organs-control organs-control-sm">
        <span>Direção</span>
        <select name="dir" data-organs-submit-on-change>
          <option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Ascendente</option>
          <option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Descendente</option>
        </select>
      </label>

      <label class="organs-control">
        <span>Tipo</span>
        <select name="type" data-organs-submit-on-change>
          <?php foreach ($typeOptions as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $typeFilter === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="organs-control organs-control-sm">
        <span>UF</span>
        <select name="state" data-organs-submit-on-change>
          <?php foreach ($stateOptions as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $stateFilter === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="organs-control organs-control-sm">
        <span>Mostrar</span>
        <select name="per_page" data-organs-submit-on-change>
          <?php foreach ([10, 25, 50] as $size): ?>
            <option value="<?= e((string) $size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e((string) $size) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </form>

  <?php if ($organs === []): ?>
    <div class="empty-state organs-empty-state">
      <p>Nenhum órgão encontrado.</p>
      <?php if ($hasActiveFilters): ?>
        <a class="btn btn-outline" href="<?= e(url('/organs')) ?>">Limpar filtros</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-wrap organs-table-wrap">
      <table>
        <thead>
          <tr>
            <th>
              <a class="organs-sort-link" href="<?= e($buildUrl(['sort' => 'name', 'dir' => $nextDir('name'), 'page' => 1])) ?>">
                Nome <span class="organs-sort-indicator"><?= e($sortIndicator('name')) ?></span>
              </a>
            </th>
            <th>
              <a class="organs-sort-link" href="<?= e($buildUrl(['sort' => 'acronym', 'dir' => $nextDir('acronym'), 'page' => 1])) ?>">
                Sigla <span class="organs-sort-indicator"><?= e($sortIndicator('acronym')) ?></span>
              </a>
            </th>
            <th>
              <a class="organs-sort-link" href="<?= e($buildUrl(['sort' => 'cnpj', 'dir' => $nextDir('cnpj'), 'page' => 1])) ?>">
                CNPJ <span class="organs-sort-indicator"><?= e($sortIndicator('cnpj')) ?></span>
              </a>
            </th>
            <th>
              <a class="organs-sort-link" href="<?= e($buildUrl(['sort' => 'siape_code', 'dir' => $nextDir('siape_code'), 'page' => 1])) ?>">
                SIAPE <span class="organs-sort-indicator"><?= e($sortIndicator('siape_code')) ?></span>
              </a>
            </th>
            <th>
              <a class="organs-sort-link" href="<?= e($buildUrl(['sort' => 'organ_type', 'dir' => $nextDir('organ_type'), 'page' => 1])) ?>">
                Tipo <span class="organs-sort-indicator"><?= e($sortIndicator('organ_type')) ?></span>
              </a>
            </th>
            <th>
              <a class="organs-sort-link" href="<?= e($buildUrl(['sort' => 'state', 'dir' => $nextDir('state'), 'page' => 1])) ?>">
                UF <span class="organs-sort-indicator"><?= e($sortIndicator('state')) ?></span>
              </a>
            </th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($organs as $organ): ?>
            <?php
            $rawName = trim((string) ($organ['name'] ?? ''));
            $nameLabel = $formatName($rawName);
            $acronym = trim((string) ($organ['acronym'] ?? ''));
            $cnpj = $formatCnpj((string) ($organ['cnpj'] ?? ''));
            $siapeCode = trim((string) ($organ['siape_code'] ?? ''));
            $organTypeCode = trim((string) ($organ['organ_type'] ?? ''));
            $organTypeLabel = $typeOptions[$organTypeCode] ?? $formatToken($organTypeCode);
            $levelCode = trim((string) ($organ['government_level'] ?? ''));
            $levelLabel = $levelLabelMap[$levelCode] ?? $formatToken($levelCode);
            $typeBadge = trim($organTypeLabel . ($levelLabel !== '' ? ' ' . $levelLabel : ''));
            if ($typeBadge === '') {
                $typeBadge = 'Não informado';
            }
            $state = trim(mb_strtoupper((string) ($organ['state'] ?? '')));
            $city = trim((string) ($organ['city'] ?? ''));
            $locationTitle = $city !== '' && $state !== ''
                ? $city . '/' . $state
                : ($city !== '' ? $city : ($state !== '' ? $state : 'Brasil'));
            ?>
            <tr>
              <td class="organs-name-cell">
                <span class="organs-name-text" title="<?= e($rawName !== '' ? $rawName : '-') ?>"><?= e($nameLabel !== '' ? $nameLabel : '-') ?></span>
              </td>
              <td class="organs-acronym-cell"><?= e($acronym !== '' ? $acronym : '-') ?></td>
              <td><span class="organs-cnpj" title="<?= e($cnpj !== '' ? $cnpj : '-') ?>"><?= e($cnpj !== '' ? $cnpj : '-') ?></span></td>
              <td><span class="organs-cnpj" title="<?= e($siapeCode !== '' ? $siapeCode : '-') ?>"><?= e($siapeCode !== '' ? $siapeCode : '-') ?></span></td>
              <td><span class="badge badge-neutral organs-type-badge" title="<?= e($typeBadge) ?>"><?= e($typeBadge) ?></span></td>
              <td><span class="organs-uf" title="<?= e($locationTitle) ?>"><?= e($state !== '' ? $state : 'BR') ?></span></td>
              <td class="actions-cell organs-actions-cell">
                <a
                  class="organs-icon-btn"
                  href="<?= e(url('/organs/show?id=' . (int) ($organ['id'] ?? 0))) ?>"
                  title="Ver órgão"
                  aria-label="Ver órgão"
                >
                  <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                    <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </a>

                <?php if (($canManage ?? false) === true): ?>
                  <a
                    class="organs-icon-btn"
                    href="<?= e(url('/organs/edit?id=' . (int) ($organ['id'] ?? 0))) ?>"
                    title="Editar órgão"
                    aria-label="Editar órgão"
                  >
                    <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                      <path d="M3 21h6"></path>
                      <path d="M5 16l11-11 3 3-11 11-4 1 1-4z"></path>
                    </svg>
                  </a>

                  <details class="organs-row-menu">
                    <summary class="organs-icon-btn organs-menu-trigger" title="Mais ações" aria-label="Mais ações" aria-haspopup="menu">
                      <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                        <circle cx="12" cy="5" r="1.7"></circle>
                        <circle cx="12" cy="12" r="1.7"></circle>
                        <circle cx="12" cy="19" r="1.7"></circle>
                      </svg>
                    </summary>
                    <div class="organs-row-menu-panel" role="menu">
                      <form method="post" action="<?= e(url('/organs/delete')) ?>" onsubmit="return confirm('Confirmar remoção deste órgão?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e((string) ($organ['id'] ?? '0')) ?>">
                        <button type="submit" class="organs-row-menu-item is-danger">Excluir órgão</button>
                      </form>
                    </div>
                  </details>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pagination-row organs-pagination-row">
      <span class="muted">Mostrando <?= e((string) $firstItem) ?>–<?= e((string) $lastItem) ?> de <?= e((string) $total) ?> órgão(s)</span>

      <?php if ($totalPages > 1): ?>
        <nav class="pagination-links organs-pagination-links" aria-label="Paginação de órgãos">
          <?php if ($currentPage > 1): ?>
            <a class="btn btn-outline" href="<?= e($buildUrl(['page' => $currentPage - 1])) ?>">&lt;</a>
          <?php endif; ?>

          <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
            <a
              class="btn <?= $page === $currentPage ? 'btn-primary' : 'btn-outline' ?>"
              href="<?= e($buildUrl(['page' => $page])) ?>"
              <?= $page === $currentPage ? 'aria-current="page"' : '' ?>
            >
              <?= e((string) $page) ?>
            </a>
          <?php endfor; ?>

          <?php if ($currentPage < $totalPages): ?>
            <a class="btn btn-outline" href="<?= e($buildUrl(['page' => $currentPage + 1])) ?>">&gt;</a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<script>
(() => {
  const form = document.querySelector('[data-organs-auto-submit]');
  if (!(form instanceof HTMLFormElement)) {
    return;
  }

  const searchInput = form.querySelector('#organs-search-input');
  const clearButton = form.querySelector('[data-organs-clear-search]');
  const submitOnChange = Array.from(form.querySelectorAll('[data-organs-submit-on-change]'));

  const submitForm = () => {
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit();
      return;
    }

    form.submit();
  };

  const updateClearButton = () => {
    if (!(clearButton instanceof HTMLButtonElement) || !(searchInput instanceof HTMLInputElement)) {
      return;
    }

    clearButton.classList.toggle('is-visible', searchInput.value.trim() !== '');
  };

  let debounceTimer = null;
  if (searchInput instanceof HTMLInputElement) {
    searchInput.addEventListener('input', () => {
      updateClearButton();
      if (debounceTimer !== null) {
        window.clearTimeout(debounceTimer);
      }

      debounceTimer = window.setTimeout(() => {
        debounceTimer = null;
        submitForm();
      }, 300);
    });
  }

  submitOnChange.forEach((element) => {
    if (!(element instanceof HTMLSelectElement)) {
      return;
    }

    element.addEventListener('change', () => {
      submitForm();
    });
  });

  if (clearButton instanceof HTMLButtonElement && searchInput instanceof HTMLInputElement) {
    clearButton.addEventListener('click', () => {
      searchInput.value = '';
      updateClearButton();
      submitForm();
    });
  }

  updateClearButton();

  const rowMenus = Array.from(document.querySelectorAll('.organs-row-menu'));
  rowMenus.forEach((menu) => {
    if (!(menu instanceof HTMLDetailsElement)) {
      return;
    }

    menu.addEventListener('toggle', () => {
      if (!menu.open) {
        return;
      }

      rowMenus.forEach((otherMenu) => {
        if (otherMenu instanceof HTMLDetailsElement && otherMenu !== menu) {
          otherMenu.open = false;
        }
      });
    });
  });

  document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Node)) {
      return;
    }

    rowMenus.forEach((menu) => {
      if (!(menu instanceof HTMLDetailsElement)) {
        return;
      }

      if (menu.open && !menu.contains(target)) {
        menu.open = false;
      }
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }

    rowMenus.forEach((menu) => {
      if (menu instanceof HTMLDetailsElement) {
        menu.open = false;
      }
    });
  });
})();
</script>
