<?php
putenv('DB_LOCAL_HOST=162.241.203.145');
putenv('DB_LOCAL_PORT=3306');
putenv('DB_LOCAL_NAME=thadio58_app');
putenv('DB_LOCAL_USER=thadio58_app');
putenv('DB_LOCAL_PASS=do3gt2mOzP_E');
require __DIR__ . '/bootstrap.php';
[$p] = bootstrapPdo();

echo "=== VERIFICAÇÃO FINAL ===\n\n";

// 1. Name dups remaining
$groups = $p->query("
    SELECT LOWER(TRIM(full_name)) AS n, GROUP_CONCAT(id ORDER BY id) AS ids, COUNT(*) AS cnt
    FROM pessoas WHERE full_name IS NOT NULL AND TRIM(full_name) != ''
    GROUP BY LOWER(TRIM(full_name)) HAVING COUNT(*) > 1
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);
echo "Same-name groups remaining: " . count($groups) . " (these are the 🔴 DIFFERENT people)\n";
foreach ($groups as $g) {
    echo "  \"{$g['n']}\" (x{$g['cnt']}): IDs {$g['ids']}\n";
}

// 2. Email dups
$emailDups = $p->query("
    SELECT LOWER(TRIM(email)) AS e, COUNT(*) AS cnt
    FROM pessoas WHERE email IS NOT NULL AND TRIM(email) != ''
    GROUP BY LOWER(TRIM(email)) HAVING COUNT(*) > 1
")->fetchAll(PDO::FETCH_ASSOC);
echo "\nEmail duplicates: " . count($emailDups) . "\n";
foreach ($emailDups as $d) echo "  {$d['e']}\n";

// 3. email2 usage
$e2 = $p->query("SELECT COUNT(*) FROM pessoas WHERE email2 IS NOT NULL AND TRIM(email2) != ''")->fetchColumn();
echo "\nPessoas with email2: {$e2}\n";

// 4. Sample email2 entries
$samples = $p->query("SELECT id, full_name, email, email2 FROM pessoas WHERE email2 IS NOT NULL AND TRIM(email2) != '' LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
foreach ($samples as $s) {
    echo "  #{$s['id']} {$s['full_name']}: email={$s['email']}, email2={$s['email2']}\n";
}

// 5. Orphan FKs
$fkTables = [
    ['cupons_creditos', 'pessoa_id'],
    ['credit_accounts', 'pessoa_id'],
    ['orders', 'pessoa_id'],
    ['pessoas_papeis', 'pessoa_id'],
];
$orphans = 0;
foreach ($fkTables as [$t, $c]) {
    $cnt = $p->query("SELECT COUNT(*) FROM {$t} fk LEFT JOIN pessoas p ON p.id = fk.{$c} WHERE p.id IS NULL AND fk.{$c} IS NOT NULL AND fk.{$c} != 0")->fetchColumn();
    if ($cnt > 0) { echo "  ⚠ orphan: {$t}.{$c} = {$cnt}\n"; $orphans += $cnt; }
}
if ($orphans === 0) echo "\nOrphan FKs: 0 ✓\n";

// 6. Remaining typo emails
$typos = $p->query("
    SELECT COUNT(*) FROM pessoas
    WHERE email LIKE '%.comm'
       OR email LIKE '%.comma'
       OR email LIKE '%.comj'
       OR email LIKE '%.coml'
       OR email LIKE '%@hotamail.%'
       OR email LIKE '%.com.bt'
       OR email LIKE '%.copm.%'
")->fetchColumn();
echo "Remaining email typos: {$typos}\n";

$total = $p->query('SELECT COUNT(*) FROM pessoas')->fetchColumn();
echo "\nTotal pessoas: {$total}\n";
echo "\nDone.\n";
