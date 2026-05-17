<?php

declare(strict_types=1);

namespace Modules\Officeimpresso\Console\Commands;

use Illuminate\Console\Command;
use Modules\Officeimpresso\Services\FirebirdImporter\FirebirdConnector;
use Modules\Officeimpresso\Services\FirebirdImporter\OfficeimpressoImporterService;

/**
 * officeimpresso:import — Comando artisan W28-4 (G1 vertical bucket).
 *
 * Migra Firebird (Delphi WR Comercial) → oimpresso multi-tenant.
 *
 * ## Uso:
 *   php artisan officeimpresso:import 7 --dry-run --source-fb=/path/to/wr_comercial.fdb
 *   php artisan officeimpresso:import 7 --source-fb=C:/legacy/wr.fdb --user=SYSDBA --pass=masterkey
 *
 * ## Convenção (Tier 0):
 *   - `--detail` em vez de `--verbose` (Symfony reserved — lição PR #851)
 *   - `{biz}` argumento obrigatório (multi-tenant ADR 0093)
 *   - `--dry-run` default-on-not-set é FALSE pra evitar import acidental
 *
 * ## Output:
 *   - Tabela: tabela | read | migrated | skipped
 *   - Resumo final + indicação Wagner reviewer (ADR 0019 one-way)
 *
 * @see Modules\Officeimpresso\Services\FirebirdImporter\OfficeimpressoImporterService
 */
class ImportOfficeimpressoCommand extends Command
{
    protected $signature = 'officeimpresso:import
        {biz : Business ID destino (multi-tenant Tier 0 ADR 0093)}
        {--dry-run : Não persiste — só lê e reporta o que migraria}
        {--source-fb= : Caminho absoluto pro .fdb Firebird legado (obrigatório em modo live)}
        {--user=SYSDBA : Usuário Firebird}
        {--pass=masterkey : Senha Firebird}
        {--detail : Output detalhado por tabela}';

    protected $description = 'Importa Firebird (Delphi WR Comercial) → oimpresso (W28-4 G1 vertical bucket)';

    public function handle(): int
    {
        $bizId = (int) $this->argument('biz');
        if ($bizId <= 0) {
            $this->error('Argumento {biz} obrigatório (multi-tenant Tier 0 ADR 0093).');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $sourceFb = (string) ($this->option('source-fb') ?? '');
        $user = (string) ($this->option('user') ?? 'SYSDBA');
        $pass = (string) ($this->option('pass') ?? 'masterkey');
        $detail = (bool) $this->option('detail');

        if ($sourceFb === '') {
            $sourceFb = ':mock:'; // sinaliza connector pra mock auto
        }

        $this->line('');
        $this->info('officeimpresso:import — W28-4 G1 vertical bucket');
        $this->table(['Param', 'Valor'], [
            ['business_id', (string) $bizId],
            ['dry-run', $dryRun ? 'SIM' : 'NÃO (PERSISTE)'],
            ['source .fdb', $sourceFb],
            ['driver pdo_firebird', FirebirdConnector::driverAvailable() ? 'disponível' : 'AUSENTE → mock mode'],
        ]);

        $connector = new FirebirdConnector(
            fdbPath: $sourceFb,
            username: $user,
            password: $pass,
        );

        // Health check antes de tudo
        $health = $connector->healthCheck();
        $this->line('');
        $this->info("Health check: mode={$health['mode']} ok=" . ($health['ok'] ? 'true' : 'false'));
        if (! $health['ok']) {
            $this->error('Health check Firebird FALHOU: ' . ($health['error'] ?? 'unknown'));
            return self::FAILURE;
        }

        $service = new OfficeimpressoImporterService($connector);
        $report = $service->runFullImport($bizId, $dryRun);

        $this->line('');
        $this->info('Resultado migração:');
        $this->table(
            ['Tabela Firebird → oimpresso', 'Read', 'Migrated', 'Skipped'],
            [
                ['CLIENTES → contacts', $report['clientes']['read'], $report['clientes']['migrated'], $report['clientes']['skipped']],
                ['PRODUTOS → products', $report['produtos']['read'], $report['produtos']['migrated'], $report['produtos']['skipped']],
                ['VENDAS → transactions', $report['vendas']['read'], $report['vendas']['migrated'], $report['vendas']['skipped']],
                ['LICENCA_COMPUTADOR → licenca_computador', $report['licencas']['read'], $report['licencas']['migrated'], $report['licencas']['skipped']],
            ]
        );

        $totalMigrated = $report['clientes']['migrated']
            + $report['produtos']['migrated']
            + $report['vendas']['migrated']
            + $report['licencas']['migrated'];

        $this->line('');
        if ($dryRun) {
            $this->warn("DRY-RUN: {$totalMigrated} registros migrariam (nada persistido).");
            $this->line('  Pra executar de verdade: omita --dry-run.');
        } else {
            $this->info("LIVE: {$totalMigrated} registros migrados em business_id={$bizId}.");
        }

        $this->line('');
        $this->info('Tier 0 (ADR 0019): one-way Firebird → oimpresso. Cliente continua usando Delphi paralelo ~30d.');
        $this->info('Reviewer obrigatório: Wagner [W] antes de cutover. Lei Software 9.609/98 retention preservada.');

        if ($detail) {
            $this->line('');
            $this->info('Detalhe completo (JSON):');
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
