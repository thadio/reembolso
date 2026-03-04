<?php
/**
 * MERGE SAME-NAME DUPLICATES — 🟢 + 🟡 groups.
 *
 * For each pair:
 *   - Keeper = lower ID (absorbs FKs, roles, best data from clone).
 *   - Fix typos on emails.
 *   - If clone has a different valid email, store it in email2 on keeper.
 *   - Enrich keeper with phone, city, etc. from clone when keeper is empty.
 *   - Delete clone.
 *
 * Usage: php _merge_samename.php [--dry-run]
 */
putenv('DB_LOCAL_HOST=162.241.203.145');
putenv('DB_LOCAL_PORT=3306');
putenv('DB_LOCAL_NAME=thadio58_app');
putenv('DB_LOCAL_USER=thadio58_app');
putenv('DB_LOCAL_PASS=do3gt2mOzP_E');

require __DIR__ . '/bootstrap.php';
[$pdo, $err] = bootstrapPdo();
if (!$pdo) { die("DB error: $err\n"); }
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$dryRun = in_array('--dry-run', $argv ?? []);
echo "=== MERGE SAME-NAME DUPLICATES ===\n";
echo "Mode: " . ($dryRun ? "DRY-RUN" : "EXECUÇÃO REAL") . "\n\n";

// Helper: fix common email typos
function fixEmailTypo(string $email): string {
    $e = trim($email);
    // .comm → .com, .comma → .com, .comj → .com, .coml → .com, .copm → .com
    $e = preg_replace('/\.comm?a?$/', '.com', $e);
    $e = preg_replace('/\.comj$/', '.com', $e);
    $e = preg_replace('/\.coml$/', '.com', $e);
    $e = preg_replace('/\.copm\./', '.com.', $e);
    // .hotamail → .hotmail
    $e = str_replace('@hotamail.', '@hotmail.', $e);
    // yahoo.com.bt → yahoo.com.br
    $e = preg_replace('/\.com\.bt$/', '.com.br', $e);
    // Remove trailing garbage from emails like "lucilene.duraes@gmail.com70144702134"
    $e = preg_replace('/\.com\d+$/', '.com', $e);
    return $e;
}

function isPlaceholder(?string $email): bool {
    if ($email === null || trim($email) === '') return true;
    $e = strtolower(trim($email));
    return str_contains($e, '@placeholder.retratoapp.local')
        || str_contains($e, '@cadastrarumemailvalido.com')
        || str_contains($e, '@atualizarcadastro.com.br')
        || preg_match('/^\d+@/', $e)
        || str_contains($e, 'esseemailnaoexiste');
}

// ═══════════════════════════════════════════════════
// MERGE PAIRS — [keeper_id, clone_id(s), notes]
// ═══════════════════════════════════════════════════
// Format: each entry = [keeper_id, [clone_ids...]]

$mergePairs = [
    // ── 🟢 VERY LIKELY SAME PERSON ──
    // 1. Alessandra Segatelli (x3) — same phone
    [4000000542, [4000001351, 4000001696]],
    // 5. Ana Gabrielle Ramos — same phone
    [4000001533, [4000002323]],
    // 10. Camila Holanda — same phone, typo
    [4000000449, [4000001167]],
    // 11. Carla Silva — same phone
    [4000000582, [4000001430]],
    // 12. Carolina Alves — same handle different provider
    [4000000722, [4000001435]],
    // 14. Christiane Silva Ribeiro — same phone
    [4000000877, [4000001255]],
    // 15. Cinthia Brito Souza Luz — same phone
    [4000000346, [4000001061]],
    // 16. Clara Melo — same phone, same handle
    [4000000260, [4000000312]],
    // 17. Debora Machado — same phone
    [4000000165, [4000000735]],
    // 18. Denise Iara da Silva — same handle
    [4000000619, [4000001305]],
    // 19. Duilio Pena Jr — same phone, typo .copm
    [4000001800, [4000001801]],
    // 25. Hérica Nascimento — same handle
    [4000001592, [4000002012]],
    // 26. Ismalia Afonso — same phone
    [4000001717, [4000001719]],
    // 27. jacquelinemiguel1234 — same handle, typo .comj
    [4000001564, [4000001721]],
    // 28. Jania Tondolo — same phone, typo daque1/darque1
    [4000002145, [4000002296]],
    // 30. Karollyne Fernandes — same handle
    [4000001517, [4000001524]],
    // 32. Larissa Godoi — typo .coml
    [4000000195, [4000000196]],
    // 35. Leni do Vale — same phone, typo .bt
    [4000002199, [4000002302]],
    // 37. luciabrilhantee — typo .coml
    [4000000561, [4000001188]],
    // 39. Luciane Sarkis — same phone
    [4000000891, [4000001368]],
    // 40. Lucilene Durães — same email base + garbage
    [4000002226, [4000002560]],
    // 45. marinacacao — both typos .comm .comma
    [4000001415, [4000001498]],
    // 48. Paula Lima — typo hotamail
    [4000000348, [4000000830]],
    // 49. Paula Medeiros — same phone
    [4000001474, [4000001476]],
    // 50. Raquel Coelho — same phone (keeper is 3B system user)
    [3000000006, [4000002367]],
    // 55. Talita Oliveira — same phone
    [4000000050, [4000000054]],
    // 59. Valentina Maciel — same handle
    [4000000867, [4000001046]],
    // 60. Vania Brandao — same handle, typo w/v
    [4000000300, [4000002672]],
    // 61. Victória Danuta — same phone, same city
    [4000001638, [4000001640]],

    // ── 🟡 POSSIBLY SAME PERSON ──
    // 6. Ana Julia Resende — very similar emails
    [4000001375, [4000001376]],
    // 7. Andy Mattarazzo — same phone
    [4000001586, [4000001814]],
    // 13. Chendailem Sousa — same first name (unique)
    [4000001223, [4000001326]],
    // 20. Elenice Tavares — similar handles, same city
    [4000000447, [4000002618]],
    // 21. Gabriela Cruz — similar handles gabrielacilda / gabi.cilda
    [4000000646, [4000000885]],
    // 33. Laryssa Gabrielle Silva — same city
    [4000001650, [4000002651]],
    // 34. Laura Naves — same phone
    [4000001576, [4000001579]],
    // 41. Luis Ferreira — very similar phone
    [4000001901, [4000001915]],
    // 53. Stéfanie Lima — anagram handles
    [4000001086, [4000001098]],
    // 54. Sueley Barbosa — similar handles
    [4000001500, [4000001508]],
    // 56. Tatiana Castro — UERJ/UFRJ pattern
    [4000000342, [4000000343]],
    // 58. Úrsula Maia — danely handle
    [4000000904, [4000001454]],
];

echo "Total merge groups: " . count($mergePairs) . "\n";
$totalClones = 0;
foreach ($mergePairs as $p) { $totalClones += count($p[1]); }
echo "Total clones to merge: {$totalClones}\n\n";

// Collect all clone IDs for backup
$allCloneIds = [];
foreach ($mergePairs as $p) {
    foreach ($p[1] as $cid) {
        $allCloneIds[] = $cid;
    }
}

// Backup
if (!$dryRun) {
    $ts = date('Ymd_His');
    $idList = implode(',', $allCloneIds);
    $backupTable = "pessoas_samename_backup_{$ts}";
    $pdo->exec("CREATE TABLE {$backupTable} AS SELECT * FROM pessoas WHERE id IN ({$idList})");
    $bCnt = $pdo->query("SELECT COUNT(*) FROM {$backupTable}")->fetchColumn();
    echo "Backup: {$backupTable} ({$bCnt} rows)\n\n";
}

// FK tables
$fkTables = [
    ['cupons_creditos', 'pessoa_id'],
    ['cupons_creditos_movimentos', 'vendor_pessoa_id'],
    ['consignacao_creditos', 'customer_pessoa_id'],
    ['consignacao_creditos', 'vendor_pessoa_id'],
    ['credit_accounts', 'pessoa_id'],
    ['cliente_historico', 'pessoa_id'],
    ['sacolinhas', 'pessoa_id'],
    ['order_returns', 'pessoa_id'],
    ['financeiro_lancamentos', 'supplier_pessoa_id'],
    ['orders', 'pessoa_id'],
    ['consignments', 'supplier_pessoa_id'],
];

$totalMerged = 0;
$totalFk = 0;
$totalEmailsSaved = 0;
$totalTyposFixed = 0;

foreach ($mergePairs as [$keeperId, $cloneIds]) {
    $keeper = $pdo->query("SELECT * FROM pessoas WHERE id = {$keeperId}")->fetch(PDO::FETCH_ASSOC);
    if (!$keeper) { echo "  ⚠ keeper #{$keeperId} not found, skip\n"; continue; }

    echo "Merging #{$keeperId} ({$keeper['full_name']}) ← ";

    $collectedEmails = []; // all valid emails from this group
    $keeperEmail = fixEmailTypo($keeper['email'] ?? '');
    if (!isPlaceholder($keeperEmail) && $keeperEmail !== '') {
        $collectedEmails[] = strtolower($keeperEmail);
    }

    // Also include keeper email2 if exists
    $keeperEmail2 = trim($keeper['email2'] ?? '');
    if ($keeperEmail2 !== '' && !isPlaceholder($keeperEmail2)) {
        $collectedEmails[] = strtolower(fixEmailTypo($keeperEmail2));
    }

    $bestPhone = trim($keeper['phone'] ?? '');
    $bestCpf = trim($keeper['cpf_cnpj'] ?? '');
    $bestInsta = trim($keeper['instagram'] ?? '');
    $bestCity = trim($keeper['city'] ?? '');
    $bestPix = trim($keeper['pix_key'] ?? '');

    // Address fields
    $addrFields = ['country','state','city','neighborhood','number','street','street2','zip'];
    $bestAddr = [];
    foreach ($addrFields as $f) { $bestAddr[$f] = trim($keeper[$f] ?? ''); }

    foreach ($cloneIds as $cloneId) {
        $clone = $pdo->query("SELECT * FROM pessoas WHERE id = {$cloneId}")->fetch(PDO::FETCH_ASSOC);
        if (!$clone) { echo "#{$cloneId}(not found) "; continue; }
        echo "#{$cloneId} ";

        // Collect clone email
        $cloneEmail = fixEmailTypo($clone['email'] ?? '');
        if (!isPlaceholder($cloneEmail) && $cloneEmail !== '') {
            $lower = strtolower($cloneEmail);
            if (!in_array($lower, $collectedEmails)) {
                $collectedEmails[] = $lower;
            }
        }

        // Enrich: phone
        $cPhone = trim($clone['phone'] ?? '');
        if (($bestPhone === '' || $bestPhone === '00000000' || $bestPhone === '0') && $cPhone !== '' && $cPhone !== '00000000' && $cPhone !== '0') {
            $bestPhone = $cPhone;
        }
        // cpf
        if ($bestCpf === '' && trim($clone['cpf_cnpj'] ?? '') !== '') { $bestCpf = trim($clone['cpf_cnpj']); }
        // instagram
        if ($bestInsta === '' && trim($clone['instagram'] ?? '') !== '') { $bestInsta = trim($clone['instagram']); }
        // pix
        if ($bestPix === '' && trim($clone['pix_key'] ?? '') !== '') { $bestPix = trim($clone['pix_key']); }
        // address
        if ($bestAddr['city'] === '' && trim($clone['city'] ?? '') !== '') {
            foreach ($addrFields as $f) {
                $v = trim($clone[$f] ?? '');
                if ($v !== '' && $bestAddr[$f] === '') { $bestAddr[$f] = $v; }
            }
        }

        // Update FKs
        foreach ($fkTables as [$t, $c]) {
            try {
                $affected = $dryRun ? 0 : (int)$pdo->exec("UPDATE {$t} SET {$c} = {$keeperId} WHERE {$c} = {$cloneId}");
                $totalFk += $affected;
            } catch (Exception $e) {}
        }

        // Handle roles
        $cloneRoles = $pdo->query("SELECT id, role, context FROM pessoas_papeis WHERE pessoa_id = {$cloneId}")->fetchAll(PDO::FETCH_ASSOC);
        $keeperRoles = $pdo->query("SELECT role, context FROM pessoas_papeis WHERE pessoa_id = {$keeperId}")->fetchAll(PDO::FETCH_ASSOC);
        $keeperRoleSet = [];
        foreach ($keeperRoles as $kr) { $keeperRoleSet[$kr['role'] . '|' . ($kr['context'] ?? '')] = true; }

        foreach ($cloneRoles as $cr) {
            $key = $cr['role'] . '|' . ($cr['context'] ?? '');
            if (isset($keeperRoleSet[$key])) {
                if (!$dryRun) $pdo->exec("DELETE FROM pessoas_papeis WHERE id = {$cr['id']}");
            } else {
                if (!$dryRun) $pdo->exec("UPDATE pessoas_papeis SET pessoa_id = {$keeperId} WHERE id = {$cr['id']}");
            }
        }

        // Delete clone
        if (!$dryRun) {
            $pdo->exec("DELETE FROM pessoas WHERE id = {$cloneId}");
        }
        $totalMerged++;
    }

    // Now update keeper with best data
    $updates = [];

    // Assign emails: first = primary, second = email2
    // Fix keeper's own email typo
    $fixedKeeperEmail = fixEmailTypo($keeper['email'] ?? '');
    if ($fixedKeeperEmail !== ($keeper['email'] ?? '')) {
        $totalTyposFixed++;
    }

    if (count($collectedEmails) >= 1) {
        // Primary = first collected (usually keeper's own, fixed)
        $primaryEmail = $collectedEmails[0];
        $updates[] = "email = " . $pdo->quote($primaryEmail);

        if (count($collectedEmails) >= 2) {
            // Secondary = second collected
            $secondaryEmail = $collectedEmails[1];
            $updates[] = "email2 = " . $pdo->quote($secondaryEmail);
            $totalEmailsSaved++;
        }
        if (count($collectedEmails) > 2) {
            // 3rd+ emails — store in email2 as comma-separated
            $extras = array_slice($collectedEmails, 1);
            $updates[] = "email2 = " . $pdo->quote(implode(', ', $extras));
            $totalEmailsSaved++;
        }
    } elseif ($fixedKeeperEmail !== ($keeper['email'] ?? '')) {
        $updates[] = "email = " . $pdo->quote($fixedKeeperEmail);
    }

    // Other enrichments
    if ($bestPhone !== '' && $bestPhone !== trim($keeper['phone'] ?? '')) {
        $updates[] = "phone = " . $pdo->quote($bestPhone);
    }
    if ($bestCpf !== '' && $bestCpf !== trim($keeper['cpf_cnpj'] ?? '')) {
        $updates[] = "cpf_cnpj = " . $pdo->quote($bestCpf);
    }
    if ($bestInsta !== '' && $bestInsta !== trim($keeper['instagram'] ?? '')) {
        $updates[] = "instagram = " . $pdo->quote($bestInsta);
    }
    if ($bestPix !== '' && $bestPix !== trim($keeper['pix_key'] ?? '')) {
        $updates[] = "pix_key = " . $pdo->quote($bestPix);
    }
    foreach ($addrFields as $f) {
        if ($bestAddr[$f] !== '' && $bestAddr[$f] !== trim($keeper[$f] ?? '')) {
            $updates[] = "{$f} = " . $pdo->quote($bestAddr[$f]);
        }
    }

    if (!empty($updates) && !$dryRun) {
        $setClause = implode(', ', $updates);
        $pdo->exec("UPDATE pessoas SET {$setClause}, updated_at = NOW() WHERE id = {$keeperId}");
    }

    echo "✓\n";
}

// Also fix typos on remaining emails that aren't part of merges
echo "\n--- Fixing standalone email typos ---\n";
$typoRows = $pdo->query("
    SELECT id, email FROM pessoas
    WHERE email LIKE '%.comm'
       OR email LIKE '%.comma'
       OR email LIKE '%.comj'
       OR email LIKE '%.coml'
       OR email REGEXP '\.com[0-9]'
       OR email LIKE '%@hotamail.%'
       OR email LIKE '%.com.bt'
       OR email LIKE '%.copm.%'
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($typoRows as $r) {
    $fixed = fixEmailTypo($r['email']);
    if ($fixed !== $r['email']) {
        echo "  #{$r['id']}: {$r['email']} → {$fixed}\n";
        if (!$dryRun) {
            $pdo->exec("UPDATE pessoas SET email = " . $pdo->quote($fixed) . ", updated_at = NOW() WHERE id = {$r['id']}");
        }
        $totalTyposFixed++;
    }
}

echo "\n=== RESUMO ===\n";
echo "Clones merged: {$totalMerged}\n";
echo "FK rows updated: {$totalFk}\n";
echo "Secondary emails saved: {$totalEmailsSaved}\n";
echo "Email typos fixed: {$totalTyposFixed}\n";
echo "\nDone.\n";
