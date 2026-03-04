<?php
/** @var array<int, array<string, mixed>> $plan */
/** @var array<int, array<string, mixed>> $results */
/** @var array<int, string> $errors */
/** @var string $success */
/** @var string $selectedMode */
/** @var bool $continueOnError */
/** @var array<string, mixed>|null $unifiedReport */
/** @var string $unifiedUploadName */
/** @var string $unifiedMode */
/** @var bool $unifiedDryRun */
/** @var bool $unifiedContinueOnError */
/** @var bool $unifiedStrict */
/** @var bool $unifiedSkipUnchanged */
/** @var bool $unifiedIdempotencyMap */
/** @var array<string, mixed>|null $mediaReport */
/** @var string $mediaUploadName */
/** @var bool $mediaDryRun */
/** @var bool $mediaContinueOnError */
/** @var bool $mediaUpdateProducts */
/** @var bool $mediaUpdateMediaFiles */
/** @var string $mediaSourceSystem */
/** @var callable $esc */
?>
<?php
  $plan = is_array($plan ?? null) ? $plan : [];
  $results = is_array($results ?? null) ? $results : [];
  $errors = is_array($errors ?? null) ? $errors : [];
  $selectedMode = in_array(($selectedMode ?? 'upsert'), ['insert', 'upsert'], true) ? (string) $selectedMode : 'upsert';
  $continueOnError = (bool) ($continueOnError ?? false);
  $unifiedReport = is_array($unifiedReport ?? null) ? $unifiedReport : null;
  $unifiedUploadName = (string) ($unifiedUploadName ?? '');
  $unifiedMode = in_array(($unifiedMode ?? 'upsert'), ['insert', 'upsert'], true) ? (string) $unifiedMode : 'upsert';
  $unifiedDryRun = (bool) ($unifiedDryRun ?? true);
  $unifiedContinueOnError = (bool) ($unifiedContinueOnError ?? false);
  $unifiedStrict = (bool) ($unifiedStrict ?? true);
  $unifiedSkipUnchanged = (bool) ($unifiedSkipUnchanged ?? true);
  $unifiedIdempotencyMap = (bool) ($unifiedIdempotencyMap ?? true);
  $mediaReport = is_array($mediaReport ?? null) ? $mediaReport : null;
  $mediaUploadName = (string) ($mediaUploadName ?? '');
  $mediaDryRun = (bool) ($mediaDryRun ?? true);
  $mediaContinueOnError = (bool) ($mediaContinueOnError ?? false);
  $mediaUpdateProducts = (bool) ($mediaUpdateProducts ?? true);
  $mediaUpdateMediaFiles = (bool) ($mediaUpdateMediaFiles ?? true);
  $mediaSourceSystem = (string) ($mediaSourceSystem ?? '');
  $resultByTable = [];
  foreach ($results as $row) {
    $tableName = (string) ($row['table'] ?? '');
    if ($tableName !== '') {
      $resultByTable[$tableName] = $row;
    }
  }
?>
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Migração de tabelas via upload</h1>
    <div class="subtitle">Envie arquivos por tabela e execute na ordem de dependências (FK).</div>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="alert muted">
  <strong>Passo a passo resumido:</strong>
  1) importe dados estruturados no bloco JSONL (etapa 1); 2) importe fotos no bloco ZIP de mídia (etapa 2);
  3) se necessário, use uploads por tabela para ajustes pontuais.
  <br>
  <strong>Modelos:</strong> cada linha oferece download de CSV/JSON detalhado. Os arquivos de modelo trazem metadados e exemplo;
  para importação, mantenha apenas o cabeçalho + dados reais (CSV) ou o array de objetos (JSON).
</div>

<section style="margin-bottom:16px;border:1px solid #e5e7eb;border-radius:10px;padding:14px;background:#fafafa;">
  <h2 style="margin:0 0 8px;font-size:18px;">Importação unificada (arquivo único JSONL)</h2>
  <p style="margin:0 0 10px;color:var(--muted);font-size:13px;">
    Upload de <code>.jsonl</code> / <code>.jsonl.gz</code> no contrato unificado (manifest + schema + data + section_end + summary).
  </p>

  <form method="post" enctype="multipart/form-data" style="display:grid;gap:10px;">
    <input type="hidden" name="action" value="import_unified_jsonl">
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
      <div class="field" style="min-width:300px;flex:1;">
        <label for="unified_jsonl_file">Arquivo único JSONL</label>
        <input id="unified_jsonl_file"
               type="file"
               name="unified_jsonl_file"
               accept=".jsonl,.jsonl.gz,.gz"
               required>
      </div>
      <div class="field" style="min-width:200px;">
        <label for="unified_mode">Modo de importação</label>
        <select id="unified_mode" name="unified_mode">
          <option value="upsert" <?php echo $unifiedMode === 'upsert' ? 'selected' : ''; ?>>Upsert (recomendado)</option>
          <option value="insert" <?php echo $unifiedMode === 'insert' ? 'selected' : ''; ?>>Insert</option>
        </select>
      </div>
      <button class="btn primary" type="submit">Processar arquivo único</button>
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap;">
      <label style="display:flex;align-items:center;gap:6px;">
        <input type="hidden" name="unified_dry_run" value="0">
        <input type="checkbox" name="unified_dry_run" value="1" <?php echo $unifiedDryRun ? 'checked' : ''; ?>>
        Dry-run (validar sem gravar)
      </label>
      <label style="display:flex;align-items:center;gap:6px;">
        <input type="hidden" name="unified_continue_on_error" value="0">
        <input type="checkbox" name="unified_continue_on_error" value="1" <?php echo $unifiedContinueOnError ? 'checked' : ''; ?>>
        Continuar com erro
      </label>
      <label style="display:flex;align-items:center;gap:6px;">
        <input type="hidden" name="unified_strict" value="0">
        <input type="checkbox" name="unified_strict" value="1" <?php echo $unifiedStrict ? 'checked' : ''; ?>>
        Modo estrito
      </label>
      <label style="display:flex;align-items:center;gap:6px;">
        <input type="hidden" name="unified_skip_unchanged" value="0">
        <input type="checkbox" name="unified_skip_unchanged" value="1" <?php echo $unifiedSkipUnchanged ? 'checked' : ''; ?>>
        Pular payload já importado
      </label>
      <label style="display:flex;align-items:center;gap:6px;">
        <input type="hidden" name="unified_idempotency_map" value="0">
        <input type="checkbox" name="unified_idempotency_map" value="1" <?php echo $unifiedIdempotencyMap ? 'checked' : ''; ?>>
        Usar mapa de idempotência
      </label>
    </div>
  </form>

  <?php if ($unifiedReport): ?>
    <?php
      $unifiedStatus = (string) ($unifiedReport['status'] ?? 'error');
      $unifiedTotals = is_array($unifiedReport['totals'] ?? null) ? $unifiedReport['totals'] : [];
      $unifiedEntities = is_array($unifiedReport['entities'] ?? null) ? $unifiedReport['entities'] : [];
      $unifiedWarnings = is_array($unifiedReport['warnings'] ?? null) ? $unifiedReport['warnings'] : [];
      $unifiedErrors = is_array($unifiedReport['errors'] ?? null) ? $unifiedReport['errors'] : [];
      $statusColor = in_array($unifiedStatus, ['success', 'success_with_warnings'], true) ? '#166534' : '#991b1b';
      $statusBg = in_array($unifiedStatus, ['success', 'success_with_warnings'], true) ? '#ecfdf3' : '#fef2f2';
    ?>
    <div style="margin-top:12px;padding:10px;border-radius:8px;background:<?php echo $statusBg; ?>;color:<?php echo $statusColor; ?>;">
      <strong>Status:</strong> <?php echo $esc($unifiedStatus); ?>
      <?php if ($unifiedUploadName !== ''): ?>
        <div style="font-size:12px;margin-top:4px;">Arquivo: <?php echo $esc($unifiedUploadName); ?></div>
      <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin-top:10px;">
      <div class="alert muted">Linhas lidas: <strong><?php echo number_format((int) ($unifiedTotals['lines_read'] ?? 0), 0, ',', '.'); ?></strong></div>
      <div class="alert muted">Data rows: <strong><?php echo number_format((int) ($unifiedTotals['data_rows'] ?? 0), 0, ',', '.'); ?></strong></div>
      <div class="alert muted">Importadas: <strong><?php echo number_format((int) ($unifiedTotals['imported_rows'] ?? 0), 0, ',', '.'); ?></strong></div>
      <div class="alert muted">Afetadas DB: <strong><?php echo number_format((int) ($unifiedTotals['db_affected_rows'] ?? 0), 0, ',', '.'); ?></strong></div>
      <div class="alert muted">Puladas: <strong><?php echo number_format((int) ($unifiedTotals['skipped_rows'] ?? 0), 0, ',', '.'); ?></strong></div>
      <div class="alert muted">Warnings/Erros: <strong><?php echo number_format((int) ($unifiedTotals['warnings'] ?? 0), 0, ',', '.'); ?>/<?php echo number_format((int) ($unifiedTotals['errors'] ?? 0), 0, ',', '.'); ?></strong></div>
    </div>

    <?php if (!empty($unifiedEntities)): ?>
      <div style="overflow:auto;margin-top:10px;">
        <table>
          <thead>
            <tr>
              <th>Entidade</th>
              <th>Data rows</th>
              <th>Importadas</th>
              <th>Afetadas DB</th>
              <th>Puladas</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($unifiedEntities as $entity => $entityReport): ?>
            <?php if (!is_array($entityReport)): continue; endif; ?>
            <tr>
              <td><code><?php echo $esc((string) $entity); ?></code></td>
              <td><?php echo number_format((int) ($entityReport['data_rows'] ?? 0), 0, ',', '.'); ?></td>
              <td><?php echo number_format((int) ($entityReport['imported_rows'] ?? 0), 0, ',', '.'); ?></td>
              <td><?php echo number_format((int) ($entityReport['db_affected_rows'] ?? 0), 0, ',', '.'); ?></td>
              <td><?php echo number_format((int) ($entityReport['skipped_rows'] ?? 0), 0, ',', '.'); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if (!empty($unifiedWarnings)): ?>
      <div style="margin-top:10px;">
        <h3 style="margin:0 0 6px;font-size:15px;">Warnings (até 20)</h3>
        <ul style="margin:0;padding-left:20px;">
          <?php foreach (array_slice($unifiedWarnings, 0, 20) as $warning): ?>
            <li>
              Linha <?php echo (int) ($warning['line'] ?? 0); ?>:
              <?php echo $esc((string) ($warning['message'] ?? 'warning')); ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (!empty($unifiedErrors)): ?>
      <div style="margin-top:10px;">
        <h3 style="margin:0 0 6px;font-size:15px;color:#991b1b;">Erros (até 20)</h3>
        <ul style="margin:0;padding-left:20px;color:#991b1b;">
          <?php foreach (array_slice($unifiedErrors, 0, 20) as $errorItem): ?>
            <li>
              Linha <?php echo (int) ($errorItem['line'] ?? 0); ?>:
              <?php echo $esc((string) ($errorItem['message'] ?? 'erro')); ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>

<section style="margin-bottom:16px;border:1px solid #e5e7eb;border-radius:10px;padding:14px;background:#fafafa;">
  <h2 style="margin:0 0 8px;font-size:18px;">Etapa 2 - Importação de mídia (bundle ZIP segregado)</h2>
  <p style="margin:0 0 10px;color:var(--muted);font-size:13px;">
    Envie um <code>.zip</code> com <code>media-manifest.json</code> + blobs. Recomendado para fotos grandes após concluir a etapa 1 (JSONL).
  </p>

  <form method="post" enctype="multipart/form-data" style="display:grid;gap:10px;">
    <input type="hidden" name="action" value="import_media_bundle">
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
      <div class="field" style="min-width:300px;flex:1;">
        <label for="media_bundle_file">Bundle ZIP de mídia</label>
        <input id="media_bundle_file"
               type="file"
               name="media_bundle_file"
               accept=".zip,application/zip"
               required>
      </div>
      <div class="field" style="min-width:220px;">
        <label for="media_source_system">Source system (opcional)</label>
        <input id="media_source_system"
               type="text"
               name="media_source_system"
               value="<?php echo $esc($mediaSourceSystem); ?>"
               placeholder="LEGACY_ERP_X">
      </div>
      <button class="btn primary" type="submit">Processar bundle de mídia</button>
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap;">
      <label style="display:flex;align-items:center;gap:6px;">
        <input type="hidden" name="media_dry_run" value="0">
        <input type="checkbox" name="media_dry_run" value="1" <?php echo $mediaDryRun ? 'checked' : ''; ?>>
        Dry-run (validar sem gravar)
      </label>
      <label style="display:flex;align-items:center;gap:6px;">
        <input type="hidden" name="media_continue_on_error" value="0">
        <input type="checkbox" name="media_continue_on_error" value="1" <?php echo $mediaContinueOnError ? 'checked' : ''; ?>>
        Continuar com erro
      </label>
      <label style="display:flex;align-items:center;gap:6px;">
        <input type="hidden" name="media_update_products" value="0">
        <input type="checkbox" name="media_update_products" value="1" <?php echo $mediaUpdateProducts ? 'checked' : ''; ?>>
        Atualizar products.metadata.images
      </label>
      <label style="display:flex;align-items:center;gap:6px;">
        <input type="hidden" name="media_update_media_files" value="0">
        <input type="checkbox" name="media_update_media_files" value="1" <?php echo $mediaUpdateMediaFiles ? 'checked' : ''; ?>>
        Atualizar tabela media_files
      </label>
    </div>
  </form>

  <?php if ($mediaReport): ?>
    <?php
      $mediaStatus = (string) ($mediaReport['status'] ?? 'error');
      $mediaTotals = is_array($mediaReport['totals'] ?? null) ? $mediaReport['totals'] : [];
      $mediaWarnings = is_array($mediaReport['warnings'] ?? null) ? $mediaReport['warnings'] : [];
      $mediaErrors = is_array($mediaReport['errors'] ?? null) ? $mediaReport['errors'] : [];
      $mediaItems = is_array($mediaReport['items'] ?? null) ? $mediaReport['items'] : [];
      $statusColor = in_array($mediaStatus, ['success', 'success_with_warnings'], true) ? '#166534' : '#991b1b';
      $statusBg = in_array($mediaStatus, ['success', 'success_with_warnings'], true) ? '#ecfdf3' : '#fef2f2';
    ?>
    <div style="margin-top:12px;padding:10px;border-radius:8px;background:<?php echo $statusBg; ?>;color:<?php echo $statusColor; ?>;">
      <strong>Status:</strong> <?php echo $esc($mediaStatus); ?>
      <?php if ($mediaUploadName !== ''): ?>
        <div style="font-size:12px;margin-top:4px;">Arquivo: <?php echo $esc($mediaUploadName); ?></div>
      <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;margin-top:10px;">
      <div class="alert muted">Itens no manifesto: <strong><?php echo number_format((int) ($mediaTotals['items_total'] ?? 0), 0, ',', '.'); ?></strong></div>
      <div class="alert muted">Processados: <strong><?php echo number_format((int) ($mediaTotals['processed'] ?? 0), 0, ',', '.'); ?></strong></div>
      <div class="alert muted">Arquivos importados: <strong><?php echo number_format((int) ($mediaTotals['imported_files'] ?? 0), 0, ',', '.'); ?></strong></div>
      <div class="alert muted">Arquivos pulados: <strong><?php echo number_format((int) ($mediaTotals['skipped_files'] ?? 0), 0, ',', '.'); ?></strong></div>
      <div class="alert muted">Produtos atualizados: <strong><?php echo number_format((int) ($mediaTotals['updated_products'] ?? 0), 0, ',', '.'); ?></strong></div>
      <div class="alert muted">Media files atualizados: <strong><?php echo number_format((int) ($mediaTotals['updated_media_files'] ?? 0), 0, ',', '.'); ?></strong></div>
      <div class="alert muted">Warnings/Erros: <strong><?php echo number_format((int) ($mediaTotals['warnings'] ?? 0), 0, ',', '.'); ?>/<?php echo number_format((int) ($mediaTotals['errors'] ?? 0), 0, ',', '.'); ?></strong></div>
    </div>

    <?php if (!empty($mediaWarnings)): ?>
      <div style="margin-top:10px;">
        <h3 style="margin:0 0 6px;font-size:15px;">Warnings (até 20)</h3>
        <ul style="margin:0;padding-left:20px;">
          <?php foreach (array_slice($mediaWarnings, 0, 20) as $warning): ?>
            <li>
              Item <?php echo (int) ($warning['item_index'] ?? 0); ?>:
              <?php echo $esc((string) ($warning['message'] ?? 'warning')); ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (!empty($mediaErrors)): ?>
      <div style="margin-top:10px;">
        <h3 style="margin:0 0 6px;font-size:15px;color:#991b1b;">Erros (até 20)</h3>
        <ul style="margin:0;padding-left:20px;color:#991b1b;">
          <?php foreach (array_slice($mediaErrors, 0, 20) as $errorItem): ?>
            <li>
              Item <?php echo (int) ($errorItem['item_index'] ?? 0); ?>:
              <?php echo $esc((string) ($errorItem['message'] ?? 'erro')); ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (!empty($mediaItems)): ?>
      <div style="overflow:auto;margin-top:10px;">
        <table>
          <thead>
            <tr>
              <th>Item</th>
              <th>Blob</th>
              <th>Destino</th>
              <th>Status</th>
              <th>Mensagem</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($mediaItems, 0, 50) as $item): ?>
              <?php if (!is_array($item)): continue; endif; ?>
              <tr>
                <td><?php echo (int) ($item['index'] ?? 0); ?></td>
                <td><?php echo $esc((string) ($item['blob_path'] ?? '')); ?></td>
                <td><code><?php echo $esc((string) ($item['target_path'] ?? '')); ?></code></td>
                <td><?php echo $esc((string) ($item['status'] ?? '')); ?></td>
                <td><?php echo $esc((string) ($item['message'] ?? '')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>

<form method="post" enctype="multipart/form-data" style="display:grid;gap:12px;">
  <input type="hidden" name="action" value="table_upload">
  <section style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
    <div class="field" style="min-width:260px;">
      <label for="csv_mode">Modo para CSV/JSON</label>
      <select id="csv_mode" name="csv_mode">
        <option value="upsert" <?php echo $selectedMode === 'upsert' ? 'selected' : ''; ?>>Upsert (insere e atualiza duplicados)</option>
        <option value="insert" <?php echo $selectedMode === 'insert' ? 'selected' : ''; ?>>Insert (somente inserção)</option>
      </select>
    </div>
    <label style="display:flex;align-items:center;gap:8px;padding-bottom:6px;">
      <input type="checkbox" name="continue_on_error" value="1" <?php echo $continueOnError ? 'checked' : ''; ?>>
      Continuar execução mesmo com erro em uma tabela
    </label>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-left:auto;">
      <button class="btn primary" type="submit">Executar uploads selecionados</button>
    </div>
  </section>

  <div class="table-tools">
    <input type="search" data-filter-global placeholder="Buscar tabela" aria-label="Busca geral por tabela">
    <span style="color:var(--muted);font-size:13px;">Formatos aceitos: <code>.sql</code>, <code>.csv</code>, <code>.json</code></span>
  </div>

  <div style="overflow:auto;">
    <table data-table="interactive">
      <thead>
      <tr>
        <th data-sort-key="position" aria-sort="none">Ordem</th>
        <th data-sort-key="table" aria-sort="none">Tabela</th>
        <th data-sort-key="depends" aria-sort="none">Dependências</th>
        <th data-sort-key="rows" aria-sort="none">Linhas atuais (estimado)</th>
        <th>Arquivo e modelos</th>
        <th data-sort-key="status" aria-sort="none">Último resultado</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="position" placeholder="Ordem" aria-label="Filtrar ordem"></th>
        <th><input type="search" data-filter-col="table" placeholder="Tabela" aria-label="Filtrar tabela"></th>
        <th><input type="search" data-filter-col="depends" placeholder="Dependências" aria-label="Filtrar dependências"></th>
        <th><input type="search" data-filter-col="rows" placeholder="Linhas" aria-label="Filtrar linhas"></th>
        <th></th>
        <th><input type="search" data-filter-col="status" placeholder="Status" aria-label="Filtrar status"></th>
      </tr>
      </thead>
      <tbody>
      <?php if (empty($plan)): ?>
        <tr class="no-results">
          <td colspan="6">Nenhuma tabela disponível no banco para montar o plano de execução.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($plan as $row): ?>
          <?php
            $tableName = (string) ($row['table'] ?? '');
            $result = $resultByTable[$tableName] ?? null;
            $status = (string) ($result['status'] ?? '');
            $dependsOn = $row['depends_on'] ?? [];
            $dependsText = empty($dependsOn) ? 'sem dependências' : implode(', ', array_map('strval', $dependsOn));
            $templateCsvUrl = 'migracao-tabelas-upload.php?download_template=1&table=' . rawurlencode($tableName) . '&format=csv';
            $templateJsonUrl = 'migracao-tabelas-upload.php?download_template=1&table=' . rawurlencode($tableName) . '&format=json';
          ?>
          <tr>
            <td data-value="<?php echo (int) ($row['position'] ?? 0); ?>"><?php echo (int) ($row['position'] ?? 0); ?></td>
            <td data-value="<?php echo $esc($tableName); ?>"><code><?php echo $esc($tableName); ?></code></td>
            <td data-value="<?php echo $esc($dependsText); ?>">
              <?php if (empty($dependsOn)): ?>
                <span class="muted">sem dependências</span>
              <?php else: ?>
                <?php echo $esc($dependsText); ?>
              <?php endif; ?>
            </td>
            <td data-value="<?php echo (int) ($row['rows_estimate'] ?? 0); ?>">
              <?php echo number_format((int) ($row['rows_estimate'] ?? 0), 0, ',', '.'); ?>
            </td>
            <td>
              <input type="file"
                     name="table_files[<?php echo $esc($tableName); ?>]"
                     accept=".sql,.csv,.json"
                     aria-label="Upload para <?php echo $esc($tableName); ?>">
              <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">
                <a class="btn" href="<?php echo $esc($templateCsvUrl); ?>">Modelo CSV</a>
                <a class="btn" href="<?php echo $esc($templateJsonUrl); ?>">Modelo JSON</a>
              </div>
              <div style="font-size:11px;color:var(--muted);margin-top:6px;max-width:360px;line-height:1.35;">
                Modelo com tipo, obrigatoriedade, defaults, índices e FKs da tabela atual.
              </div>
            </td>
            <td data-value="<?php echo $esc($status); ?>">
              <?php if (!$result): ?>
                <span class="muted">não executado</span>
              <?php elseif ($status === 'success'): ?>
                <span class="pill" style="background:#ecfdf3;color:#166534;">ok</span>
                <div style="font-size:12px;color:var(--muted);margin-top:6px;">
                  <?php echo number_format((int) ($result['affected_rows'] ?? 0), 0, ',', '.'); ?> linhas afetadas
                </div>
              <?php else: ?>
                <span class="pill" style="background:#fef2f2;color:#991b1b;">erro</span>
                <div style="font-size:12px;color:#991b1b;margin-top:6px;max-width:340px;white-space:normal;">
                  <?php echo $esc((string) ($result['message'] ?? 'Falha na importação.')); ?>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</form>

<?php if (!empty($results)): ?>
  <section style="margin-top:16px;">
    <h2 style="margin:0 0 8px;font-size:18px;">Relatório da execução</h2>
    <div style="overflow:auto;">
      <table>
        <thead>
        <tr>
          <th>Tabela</th>
          <th>Arquivo</th>
          <th>Modo</th>
          <th>Status</th>
          <th>Linhas afetadas</th>
          <th>Mensagem</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($results as $row): ?>
          <tr>
            <td><code><?php echo $esc((string) ($row['table'] ?? '')); ?></code></td>
            <td><?php echo $esc((string) ($row['file_name'] ?? '')); ?></td>
            <td><?php echo $esc((string) ($row['mode'] ?? '')); ?></td>
            <td><?php echo $esc((string) ($row['status'] ?? '')); ?></td>
            <td><?php echo number_format((int) ($row['affected_rows'] ?? 0), 0, ',', '.'); ?></td>
            <td><?php echo $esc((string) ($row['message'] ?? '')); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
