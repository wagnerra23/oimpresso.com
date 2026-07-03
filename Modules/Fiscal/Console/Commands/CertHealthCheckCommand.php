<?php

declare(strict_types=1);

namespace Modules\Fiscal\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\NfeBrasil\Models\NfeCertificado;

/**
 * US-FISCAL-022 (cap #13 CAPTERRA-INVENTARIO Fiscal 2026-07-03) — Health-check
 * proativo do certificado A1 por business.
 *
 * O mercado (todos middlewares + Bling/Omie) alerta vencimento de cert; o oimpresso
 * só exibia validade estática no ConfigController. Este comando fecha o gap: roda
 * diariamente (cron 06:30 BRT em app/Console/Kernel.php), calcula dias-a-vencer por
 * business com cert A1 ativo, e — quando ≤ 30 dias — cria/atualiza um evento em
 * `mcp_alertas_eventos` (ADR 0055), escopado ao business (multi-tenant ADR 0093).
 *
 * Distinção de tabelas (ver migration create_mcp_alertas_eventos_table):
 *   - `mcp_alertas`         = regras/config (kind enum fixo, sem "cert")
 *   - `mcp_alertas_eventos` = instâncias disparadas (é aqui que escrevemos)
 *
 * Dedup por business+cert via `chave_idempotencia` UNIQUE — SEM data no hash, então
 * o alerta NÃO é reemitido todo dia: a 1ª execução insere, as seguintes só atualizam
 * o payload (dias/severidade) mantendo o `status` (respeita ack do usuário). Cert
 * renovado gera uuid novo → chave nova → alerta novo (comportamento desejado).
 *
 * Convenção `--detail` em vez de `--verbose` (.claude/rules/commands.md — Symfony
 * reserva --verbose).
 *
 * Uso:
 *   php artisan fiscal:cert-health-check            # apura + persiste alertas
 *   php artisan fiscal:cert-health-check --dry-run  # apura, NADA persiste
 *   php artisan fiscal:cert-health-check --json      # output machine-readable
 *   php artisan fiscal:cert-health-check --detail    # lista cert-a-cert
 */
class CertHealthCheckCommand extends Command
{
    /** Limiar de dias-a-vencer que dispara alerta. */
    private const LIMIAR_DIAS = 30;

    protected $signature = 'fiscal:cert-health-check
                            {--dry-run : Apura mas NÃO persiste alertas}
                            {--json : Output JSON machine-readable em vez de tabela}
                            {--detail : Lista cert-a-cert (substitui --verbose por convenção Symfony)}';

    protected $description = 'US-FISCAL-022 — Health-check do certificado A1: alerta vencimento ≤30d em mcp_alertas_eventos (dedup por business+cert)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // SUPERADMIN: cron cross-business — itera certs A1 ativos de TODOS os tenants.
        // Dropa apenas o ScopeByBusiness (mantém SoftDeletes ativo). Cada alerta
        // resultante é escopado ao business_id do próprio cert (ADR 0093).
        $certs = NfeCertificado::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('ativo', true)
            ->whereNotNull('valido_ate')
            ->get();

        $stats = [
            'certs_ativos'      => $certs->count(),
            'vencendo'          => 0, // dias <= LIMIAR (inclui vencidos)
            'alertas_criados'   => 0,
            'alertas_atualizados' => 0,
            'dry_run'           => $dryRun,
        ];

        $detalhes = [];

        foreach ($certs as $cert) {
            $dias = $cert->diasAteVencimento();

            $detalhes[] = [
                'business_id' => (int) $cert->business_id,
                'uuid'        => $cert->uuid,
                'valido_ate'  => $cert->valido_ate?->format('Y-m-d'),
                'dias'        => $dias,
                'alertavel'   => $dias <= self::LIMIAR_DIAS,
            ];

            if ($dias > self::LIMIAR_DIAS) {
                continue;
            }

            $stats['vencendo']++;

            if ($dryRun) {
                continue;
            }

            if ($this->persistirAlerta($cert, $dias) === 'inserido') {
                $stats['alertas_criados']++;
            } else {
                $stats['alertas_atualizados']++;
            }
        }

        // Log estruturado (auditável pós-cron).
        Log::info('fiscal:cert-health-check', $stats);

        $this->render($stats, $detalhes);

        return self::SUCCESS;
    }

    /**
     * Cria ou atualiza o evento de alerta em `mcp_alertas_eventos`, escopado ao
     * business do cert. Retorna 'inserido' ou 'atualizado'.
     */
    private function persistirAlerta(NfeCertificado $cert, int $dias): string
    {
        $businessId = (int) $cert->business_id;
        // Ref estável do cert: uuid (sempre presente em cert criado via
        // CertificadoService); fallback pra PK protege cert legado com uuid vazio.
        $certRef = $cert->uuid ?: ('id' . $cert->getKey());
        $cnpjTitular = $cert->cnpj_titular ?: '—';
        $chave = "cert_a1_vencimento:{$businessId}:{$certRef}";
        $severidade = $this->severidade($dias);
        $agora = now();

        $titulo = $dias < 0
            ? "Certificado A1 VENCIDO há " . abs($dias) . 'd — emissão fiscal bloqueada'
            : "Certificado A1 vence em {$dias}d — renovar antes do vencimento";

        $validoAteBr = $cert->valido_ate?->format('d/m/Y') ?? '—';
        $descricao = "O certificado digital A1 (CNPJ {$cnpjTitular}) do business {$businessId} "
            . "tem validade até {$validoAteBr} (dias-a-vencer: {$dias}). "
            . 'Sem cert válido a emissão de NF-e/NFC-e/NFS-e é bloqueada pela SEFAZ.';

        $metadata = json_encode([
            'cert_uuid'    => $cert->uuid,
            'cnpj_titular' => $cnpjTitular,
            'valido_ate'   => $cert->valido_ate?->toIso8601String(),
            'dias'         => $dias,
            'limiar_dias'  => self::LIMIAR_DIAS,
            'origem'       => 'fiscal:cert-health-check',
        ], JSON_UNESCAPED_UNICODE);

        $existente = DB::table('mcp_alertas_eventos')
            ->where('chave_idempotencia', $chave)
            ->first();

        if ($existente !== null) {
            // Atualiza payload (dias/severidade mudam ao longo do tempo) sem
            // reabrir/mexer no status — respeita ack do usuário.
            DB::table('mcp_alertas_eventos')
                ->where('id', $existente->id)
                ->update([
                    'severidade' => $severidade,
                    'titulo'     => $titulo,
                    'descricao'  => $descricao,
                    'metadata'   => $metadata,
                    'updated_at' => $agora,
                ]);

            return 'atualizado';
        }

        DB::table('mcp_alertas_eventos')->insert([
            'user_id'            => null,
            'business_id'        => $businessId,
            'tipo'               => 'cert_a1_vencimento',
            'severidade'         => $severidade,
            'titulo'             => $titulo,
            'descricao'          => $descricao,
            'chave_idempotencia' => $chave,
            'metadata'           => $metadata,
            'status'             => 'aberto',
            'criado_em'          => $agora,
            'created_at'         => $agora,
            'updated_at'         => $agora,
        ]);

        return 'inserido';
    }

    /** Escala a severidade conforme a proximidade do vencimento. */
    private function severidade(int $dias): string
    {
        return match (true) {
            $dias < 0   => 'critical', // já vencido
            $dias <= 7  => 'high',
            default     => 'medium',   // 8..30
        };
    }

    /** @param array<int, array<string, mixed>> $detalhes */
    private function render(array $stats, array $detalhes): void
    {
        if ($this->option('json')) {
            $this->line(json_encode([
                'checked_at' => now()->toIso8601String(),
                'stats'      => $stats,
                'detalhes'   => $this->option('detail') ? $detalhes : null,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return;
        }

        $this->info('═══ Health-check Certificado A1 ═══');
        $this->line("Certs ativos:        {$stats['certs_ativos']}");
        $this->line("Vencendo (≤30d):     {$stats['vencendo']}");

        if ($stats['dry_run']) {
            $this->warn('DRY-RUN — nenhum alerta persistido.');
        } else {
            $this->line("Alertas criados:     {$stats['alertas_criados']}");
            $this->line("Alertas atualizados: {$stats['alertas_atualizados']}");
        }

        if ($this->option('detail')) {
            $this->line('');
            $this->info('--- Certificados ---');
            foreach ($detalhes as $d) {
                $flag = $d['alertavel'] ? '⚠️ ' : '   ';
                $this->line(sprintf(
                    '  %sbiz=%d  vence=%s  dias=%d',
                    $flag,
                    $d['business_id'],
                    $d['valido_ate'] ?? '—',
                    $d['dias'],
                ));
            }
        }
    }
}
