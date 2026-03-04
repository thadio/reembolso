<?php

declare(strict_types=1);

$document = is_array($document ?? null) ? $document : [];
?>
<article class="card">
  <header>
    <p class="muted">Oficio #<?= e((string) ($document['id'] ?? 0)) ?> - <?= e((string) ($document['template_name'] ?? '-')) ?> - V<?= e((string) ($document['version_number'] ?? '-')) ?></p>
    <h1><?= e((string) ($document['rendered_subject'] ?? 'Oficio')) ?></h1>
    <p class="muted">Pessoa: <?= e((string) ($document['person_name'] ?? '-')) ?> | Orgao: <?= e((string) ($document['organ_name'] ?? '-')) ?></p>
  </header>

  <section>
    <?= (string) ($document['rendered_html'] ?? '') ?>
  </section>
</article>
