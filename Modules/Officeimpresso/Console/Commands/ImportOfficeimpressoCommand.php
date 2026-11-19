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
 *   OFFICEIMPRESSO_FB_PASSWORD=<senha> php artisan officeimpresso:import 7 --source-fb=C:/legacy/wr.fdb
 *
 * ## Credencial:
 *   - Senha SÓ por env `OFFICEIMPRESSO_FB_PASSWORD` (config `officeimpresso.firebird_importer`).
 *     Não há `--pass`: na linha de comando a senha fica no histórico do shell e na lista
 *     de processos. Não há senha padrão.
 *   - Usuário: `--user`, senão `OFFICEIMPRESSO_FB_USER`, senão SYSDBA.
 *
 * ## Convenção (Tier 0):
 *   - `--detail` em vez de `--verbose` (Symfony reserved — lição PR #851)
 *   - `{biz}` argumento obrigatório (multi-tenant ADR 0093)
 *   - `--dry-run` default-on-not-set é FALSE pra evitar import acidental
 *
 * ## Fail-closed (2026-11-18, Lote 2 da auditoria Officeimpresso):
 *   - Sem `--source-fb`, ou sem a extensão `pdo_firebird`, o connector entra em mock e
 *     devolve FIXTURES fictícias. Mock só roda com `--dry-run`; sem ele o comando recusa
 *     antes de tocar o banco. Antes ele gravava as fixtures na empresa real.
 *   - Linha que falha conta em `Failed`; linha de tabela sem `legacy_id` conta em
 *     `Blocked`. Qualquer uma das duas > 0 → exit 1.
 *
 * ## Output:
 *   - Tabela: tabela | read | migrated | skipped | failed | blocked
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
        {--user= : Usuário Firebird (padrão: OFFICEIMPRESSO_FB_USER ou SYSDBA). Senha só por env OFFICEIMPRESSO_FB_PASSWORD}
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
        $user = (string) ($this->option('user') ?: config('officeimpresso.firebird_importer.user', 'SYSDBA'));
        $pass = config('officeimpresso.firebird_importer.password');
        $pass = is_string($pass) && $pass !== '' ? $pass : null;
        $detail = (bool) $this->option('detail');

        $semFonte = $sourceFb === '';
        if ($semFonte) {
            $sourceFb = ':mock:';
        }

        $connector = new FirebirdConnector(
            fdbPath: $sourceFb,
            username: $user,
            password: $pass,
            forceMock: $semFonte ? true : null,
        );

        if ($connector->isMock() && ! $dryRun) {
            $motivo = $semFonte ? '--source-fb não informado' : 'extensão pdo_firebird ausente (ou OFFICEIMPRESSO_FB_FORCE_MOCK ligado)';
            $this->error("Modo mock ({$motivo}): os dados seriam FIXTURES fictícias, não o .fdb do cliente.");
            $this->error('Mock só roda com --dry-run. Nada foi gravado.');

            return self::FAILURE;
        }

        if (! $connector->isMock() && $pass === null) {
            $this->error('Senha do Firebird ausente: defina OFFICEIMPRESSO_FB_PASSWORD no ambiente. Não há senha padrão nem --pass.');

            return self::FAILURE;
        }

        $this->line('');
        $this->info('officeimpresso:import — W28-4 G1 vertical bucket');
        $this->table(['Param', 'Valor'], [
            ['business_id', (string) $bizId],
            ['dry-run', $dryRun ? 'SIM' : 'NÃO (PERSISTE)'],
            ['source .fdb', $sourceFb],
            ['driver pdo_firebird', FirebirdConnector::driverAvailable() ? 'disponível' : 'AUSENTE → mock mode'],
            ['modo', $connector->isMock() ? 'MOCK (fixtures fictícias)' : 'live'],
        ]);

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
        $fases = [
            'clientes' => 'CLIENTES → contacts',
            'produtos' => 'PRODUTOS → products',
            'vendas' => 'VENDAS → transactions',
            'licencas' => 'LICENCA_COMPUTADOR → licenca_computador',
            'financeiros' => 'FINANCEIRO → fin_titulos [W29-1]',
            'notas_fiscais' => 'NOTA_FISCAL → nfe_emissoes [W29-1]',
        ];
        $linhas = [];
        $totalMigrated = $totalFailed = $totalBlocked = 0;
        foreach ($fases as $chave => $rotulo) {
            $f = $report[$chave];
            $linhas[] = [$rotulo, $f['read'], $f['migrated'], $f['skipped'], $f['failed'], $f['blocked']];
            $totalMigrated += $f['migrated'];
            $totalFailed += $f['failed'];
            $totalBlocked += $f['blocked'];
        }
        $this->table(['Tabela Firebird → oimpresso', 'Read', 'Migrated', 'Skipped', 'Failed', 'Blocked'], $linhas);

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

        if ($totalFailed > 0 || $totalBlocked > 0) {
            $this->line('');
            $this->error("Importação INCOMPLETA: {$totalFailed} linha(s) com falha, {$totalBlocked} bloqueada(s).");
            foreach ($report['errors'] as $erro) {
                $this->line(sprintf('  - %s · legacy_id=%s · %s · %d linha(s)',
                    $erro['tabela'], $erro['legacy_id'] ?? '-', $erro['erro'], $erro['linhas']));
            }
            if ($totalBlocked > 0) {
                $this->line('  Bloqueada = tabela sem coluna legacy_id: sem ela um 2º run duplicaria tudo.');
            }
        }

        if ($detail) {
            $this->line('');
            $this->info('Detalhe completo (JSON):');
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return ($totalFailed > 0 || $totalBlocked > 0) ? self::FAILURE : self::SUCCESS;
    }
}
