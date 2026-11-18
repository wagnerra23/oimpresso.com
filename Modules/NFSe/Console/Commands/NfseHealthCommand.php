<?php

declare(strict_types=1);

namespace Modules\NFSe\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * nfse:health — 6 sinais do módulo NFSe.
 *
 *   1. emissoes_table         — nfse_emissoes presente
 *   2. provider_config_table  — nfse_provider_configs presente
 *   3. certificado_table      — nfe_certificados presente (o certificado da NFS-e
 *                               mora na tabela do NfeBrasil)
 *   4. providers_ativos       — empresas com config NFS-e (sem as apagadas)
 *   5. cert_vencimento_alarme — por config: a empresa tem certificado ATIVO (o mesmo
 *                               da NF-e) e ele não está vencido nem vencendo em menos
 *                               de 30 dias
 *   6. erros_recentes         — emissões em status 'erro' nas últimas 24h
 *
 * Até 2026-11-16 o check 3 lia `nfse_certificados` (tabela que nunca existiu) e o 6
 * contava status 'rejeitada' (que o módulo nunca grava; o enum de nfse_emissoes é
 * rascunho/processando/emitida/cancelada/erro). Os dois sinais eram cegos.
 *
 * Desde 2026-11-18 a NFS-e emite com o certificado ativo da empresa
 * (CertificadoService::certificadoAtivo), o mesmo do `nfe:health`. Até então o alerta
 * olhava `nfse_provider_configs.cert_id`, ponteiro próprio que só o `nfse:importar-cert`
 * atualizava — e havia o FAIL "certificado de outra empresa", que deixou de ser possível.
 *
 * "Vencido" segue a regra da emissão: NfeCertificado::isVencido() usa
 * valido_ate->isPast(), e valido_ate é data — no próprio dia do vencimento a
 * emissão já é bloqueada. Aqui: vencido ⇔ valido_ate <= hoje.
 *
 * Não é agendado. Quem recebe alerta hoje é o `nfe:health --notify` (NfeBrasil).
 *
 * Convenção `--detail` (NUNCA --verbose — Symfony reserved).
 *
 * Multi-tenant Tier 0 (ADR 0093): comando de leitura, CLI sem sessão. Usa DB::table
 * para varrer todas as empresas (ou só a de --business); não escreve nada.
 */
class NfseHealthCommand extends Command
{
    protected $signature = 'nfse:health
        {--business= : Filtra por business_id}
        {--alert : Exit 2 FAIL, 1 WARN (cron)}
        {--json : Output JSON}
        {--detail : Log detalhado (NUNCA --verbose: Symfony reserved)}';

    protected $description = 'Health check Modules/NFSe — 6 sinais (tabelas, configs, certificado por config, erros 24h).';

    public const CERT_WARN_DIAS = 30;

    public const ERRO_LOOKBACK_HORAS = 24;

    public const ERRO_LIMIAR = 5;

    public function handle(): int
    {
        $businessId = $this->option('business') !== null
            ? (int) $this->option('business')
            : null;

        $asJson = (bool) $this->option('json');
        $alert  = (bool) $this->option('alert');
        $detail = (bool) $this->option('detail');

        $checks = [
            $this->checkEmissoesTable(),
            $this->checkProviderConfigTable(),
            $this->checkCertificadoTable(),
            $this->checkProvidersAtivos($businessId),
            $this->checkCertVencimento($businessId),
            $this->checkErrosRecentes($businessId),
        ];

        if ($detail && ! $asJson) {
            foreach ($checks as $c) {
                $this->line("  [{$c['status']}] {$c['name']}: {$c['details']}");
            }
        }

        $summary = [
            'ok'    => collect($checks)->filter(fn ($c) => $c['status'] === 'OK')->count(),
            'warn'  => collect($checks)->filter(fn ($c) => $c['status'] === 'WARN')->count(),
            'fail'  => collect($checks)->filter(fn ($c) => $c['status'] === 'FAIL')->count(),
            'total' => count($checks),
        ];

        if ($asJson) {
            $this->line(json_encode([
                'timestamp'       => now()->toIso8601String(),
                'business_filter' => $businessId,
                'checks'          => $checks,
                'summary'         => $summary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $this->resolveExitCode($summary, $alert);
        }

        $this->info('NFSe Health Check — ' . now()->toDateTimeString());
        $bizLabel = $businessId !== null ? "business_id={$businessId}" : 'todos businesses';
        $this->line("   Filtro: {$bizLabel}");
        $this->newLine();

        $this->table(['Check', 'Status', 'Details'], collect($checks)->map(fn ($c) => [
            $c['name'],
            $c['status'],
            mb_strimwidth((string) $c['details'], 0, 80, '…'),
        ])->toArray());

        $line = sprintf('%d OK, %d WARN, %d FAIL de %d', $summary['ok'], $summary['warn'], $summary['fail'], $summary['total']);
        $summary['fail'] > 0 ? $this->error("  Resumo: $line") : ($summary['warn'] > 0 ? $this->warn("  Resumo: $line") : $this->info("  Resumo: $line"));

        return $this->resolveExitCode($summary, $alert);
    }

    private function checkEmissoesTable(): array
    {
        return Schema::hasTable('nfse_emissoes')
            ? $this->makeCheck('emissoes_table', 'OK', 'nfse_emissoes presente')
            : $this->makeCheck('emissoes_table', 'FAIL', 'nfse_emissoes ausente — rode module:migrate NFSe');
    }

    private function checkProviderConfigTable(): array
    {
        return Schema::hasTable('nfse_provider_configs')
            ? $this->makeCheck('provider_config_table', 'OK', 'nfse_provider_configs presente')
            : $this->makeCheck('provider_config_table', 'FAIL', 'nfse_provider_configs ausente');
    }

    private function checkCertificadoTable(): array
    {
        return Schema::hasTable('nfe_certificados')
            ? $this->makeCheck('certificado_table', 'OK', 'nfe_certificados presente')
            : $this->makeCheck('certificado_table', 'FAIL', 'nfe_certificados ausente — rode module:migrate NfeBrasil');
    }

    private function checkProvidersAtivos(?int $businessId): array
    {
        if (! Schema::hasTable('nfse_provider_configs')) {
            return $this->makeCheck('providers_ativos', 'WARN', 'Tabela ausente');
        }

        $q = DB::table('nfse_provider_configs')->whereNull('deleted_at');
        if ($businessId !== null) {
            $q->where('business_id', $businessId);
        }

        $count = (clone $q)->count();
        $distintos = (clone $q)->distinct()->count('business_id');

        if ($count === 0) {
            return $this->makeCheck('providers_ativos', 'WARN', 'Nenhum provider configurado');
        }

        return $this->makeCheck('providers_ativos', 'OK', "{$count} config(s) em {$distintos} business(es)");
    }

    /**
     * Uma linha por config NFS-e ativa, com o certificado ativo da empresa dela (o mais
     * recente, como CertificadoService::certificadoAtivo). O join é pelo business_id da
     * config: nunca casa certificado de outra empresa. O pior estado decide o status.
     */
    private function checkCertVencimento(?int $businessId): array
    {
        if (! Schema::hasTable('nfse_provider_configs') || ! Schema::hasTable('nfe_certificados')) {
            return $this->makeCheck('cert_vencimento_alarme', 'WARN', 'Tabela ausente — não medido');
        }

        $ativoMaisRecente = DB::table('nfe_certificados')
            ->selectRaw('business_id, MAX(id) as id')
            ->where('ativo', true)
            ->whereNull('deleted_at')
            ->groupBy('business_id');

        $q = DB::table('nfse_provider_configs as p')
            ->leftJoinSub($ativoMaisRecente, 'a', 'a.business_id', '=', 'p.business_id')
            ->leftJoin('nfe_certificados as c', 'c.id', '=', 'a.id')
            ->whereNull('p.deleted_at')
            ->select('p.id', 'p.business_id', 'c.id as cert_id', 'c.valido_ate');

        if ($businessId !== null) {
            $q->where('p.business_id', $businessId);
        }

        $configs = $q->get();

        if ($configs->isEmpty()) {
            return $this->makeCheck('cert_vencimento_alarme', 'OK', 'Nenhuma config NFS-e a verificar');
        }

        $hoje   = now()->toDateString();
        $limite = now()->addDays(self::CERT_WARN_DIAS)->toDateString();

        $grupos = ['vencido' => [], 'sem_cert' => [], 'vencendo' => []];

        foreach ($configs as $c) {
            if ($c->cert_id === null) {
                $grupos['sem_cert'][] = $c->business_id;
            } elseif (substr((string) $c->valido_ate, 0, 10) <= $hoje) {
                $grupos['vencido'][] = $c->business_id;
            } elseif (substr((string) $c->valido_ate, 0, 10) < $limite) {
                $grupos['vencendo'][] = $c->business_id;
            }
        }

        $rotulos = [
            'vencido'       => 'certificado vencido',
            'sem_cert'      => 'sem certificado',
            'vencendo'      => 'vencendo em < ' . self::CERT_WARN_DIAS . 'd',
        ];

        $partes = [];
        foreach ($grupos as $chave => $bizIds) {
            if ($bizIds !== []) {
                $partes[] = count($bizIds) . " config(s) {$rotulos[$chave]} (business " . implode(',', array_unique($bizIds)) . ')';
            }
        }

        if ($partes === []) {
            return $this->makeCheck('cert_vencimento_alarme', 'OK', "{$configs->count()} config(s) com certificado vigente");
        }

        $status = $grupos['vencido'] !== [] ? 'FAIL' : 'WARN';

        return $this->makeCheck('cert_vencimento_alarme', $status, implode('; ', $partes));
    }

    private function checkErrosRecentes(?int $businessId): array
    {
        if (! Schema::hasTable('nfse_emissoes')) {
            return $this->makeCheck('erros_recentes', 'WARN', 'Tabela ausente');
        }

        $cutoff = now()->subHours(self::ERRO_LOOKBACK_HORAS);

        $q = DB::table('nfse_emissoes')
            ->where('status', 'erro')
            ->where('updated_at', '>=', $cutoff);

        if ($businessId !== null) {
            $q->where('business_id', $businessId);
        }

        $erros = $q->count();

        if ($erros >= self::ERRO_LIMIAR) {
            return $this->makeCheck(
                'erros_recentes',
                'WARN',
                "{$erros} emissão(ões) com erro nas últimas " . self::ERRO_LOOKBACK_HORAS . 'h — veja erro_mensagem'
            );
        }

        return $this->makeCheck('erros_recentes', 'OK', "{$erros} emissão(ões) com erro nas últimas " . self::ERRO_LOOKBACK_HORAS . 'h');
    }

    /**
     * @return array{name: string, status: string, details: string}
     */
    private function makeCheck(string $name, string $status, string $details): array
    {
        return compact('name', 'status', 'details');
    }

    private function resolveExitCode(array $summary, bool $alert): int
    {
        if (! $alert) {
            return 0;
        }

        return $summary['fail'] > 0 ? 2 : ($summary['warn'] > 0 ? 1 : 0);
    }
}
