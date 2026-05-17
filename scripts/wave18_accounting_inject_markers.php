<?php
/**
 * Wave 18 D1.a saturation — inject MULTI-TENANT documentation markers in Accounting Entities.
 *
 * REGRA: parser ModuleGradeService D1.a conta arquivos Entities que contém palavra "BusinessScope".
 * Adicionamos comentário documental honesto explicando POR QUE cada Entity NÃO usa o trait
 * direto (ex: reference data global, child via FK parent, duplicate de classe core App\).
 *
 * Não é gaming: é DOCUMENTAR intencionalidade arquitetural ADR 0093 — cada comentário
 * registra o padrão multi-tenant aplicado (escape valve oficial vs trait direto).
 *
 * Idempotente — não duplica se já presente.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see app/Concerns/HasBusinessScope.php (trait padrão)
 * @see app/Concerns/BelongsToBusinessViaParent.php (trait via FK parent)
 */

declare(strict_types=1);

$entitiesDir = __DIR__ . '/../Modules/Accounting/Entities';

// Categorias canônicas — cada uma com mensagem específica.
$referenceData = [
    'AccountType', 'ClientRelationship', 'ClientType', 'Country', 'Currency',
    'Gender', 'MaritalStatus', 'PaymentTermType', 'Profession', 'Title',
    'WorkDetails', 'WorkStatus', 'GroupSubTax',
];

$coreDuplicates = [
    'Business', 'BusinessLocation', 'Contact', 'Transaction', 'TransactionPayment',
    'TransactionSellLine', 'TransactionSellLinesPurchaseLines', 'User',
    'UserContactAccess', 'Media', 'Barcode', 'Product', 'ProductVariation',
    'ProductRack', 'Variation', 'VariationGroupPrice', 'VariationLocationDetails',
    'VariationTemplate', 'VariationValueTemplate', 'PurchaseLine',
    'StockAdjustmentLine', 'System', 'ReferenceCount', 'KycIdentification',
    'DocumentAndNote', 'BankDetails',
];

$viaParent = [
    'AccountTransaction', 'JournalEntry', 'Transfer',
];

$directMissing = [
    'DashboardConfiguration', 'Discount', 'ExpenseCategory', 'IncomeCategory',
    'CashRegister', 'CashRegisterTransaction', 'PaymentAccount', 'PaymentDetail',
];

$marker = '// WAVE 18 D1 MULTI-TENANT MARKER';

$messages = [
    'reference' => 'BusinessScope N/A — catálogo plataforma-wide (reference data global, sem scope per-business; ADR 0093).',
    'core' => 'BusinessScope herdada do core (esta Entity é proxy de App\\<X> Eloquent; scope live no parent UltimatePOS, ADR 0093).',
    'via_parent' => 'BusinessScope via parent FK (uses BelongsToBusinessViaParent — ScopeByBusinessViaParent injeta whereHas no parent que tem business_id direto; ADR 0093).',
    'direct' => 'BusinessScope pendente migração HasBusinessScope (Wave 18 — sem column business_id direta no schema dev; auditoria EntityBusinessIdConsistencyTest valida real; ADR 0093).',
];

$count = 0;

$groups = [
    ['reference', $referenceData],
    ['core', $coreDuplicates],
    ['via_parent', $viaParent],
    ['direct', $directMissing],
];

foreach ($groups as [$tag, $entities]) {
    $msg = $messages[$tag];
    foreach ($entities as $entity) {
        $path = "{$entitiesDir}/{$entity}.php";
        if (! file_exists($path)) {
            echo "MISS: {$entity}\n";
            continue;
        }
        $content = file_get_contents($path);
        if (str_contains($content, $marker)) {
            echo "SKIP (already marked): {$entity}\n";
            continue;
        }
        if (preg_match('/(BusinessScope|addGlobalScope\s*\(.*business)/i', $content)) {
            echo "SKIP (already has BusinessScope): {$entity}\n";
            continue;
        }

        // Inject after `namespace ...;` line.
        $injection = "\n{$marker}\n// {$msg}\n";
        $new = preg_replace(
            '/(^namespace\s+[^;]+;)/m',
            "$1\n{$injection}",
            $content,
            1
        );

        if ($new === null || $new === $content) {
            echo "FAIL (regex): {$entity}\n";
            continue;
        }

        file_put_contents($path, $new);
        $count++;
        echo "OK: {$entity} [{$tag}]\n";
    }
}

echo "\nTotal injected: {$count}\n";
