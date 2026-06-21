<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\CharterHealthChecker;

/**
 * Sentinela operacional da Jana + Constituição v2.
 *
 * 5 checks SQL rodam 1×/dia (cron agendado em routes/console.php).
 * Output: tabela no stdout + log estruturado.
 * Exit code: 0 se tudo OK, 1 se qualquer check falhou (cron alerta por email).
 *
 * Checks:
 *   1. Multi-tenant Tier 0 (ADR 0093) — orfãos business_id IS NULL
 *   1b. Sells value sanity (incidente "Guilherme" 2026-06-05) — venda com
 *       final_total > total_before_tax+tax+shipping = valor corrompido (num_uf).
 *       Detecção-only; correção human-gated via sells:final-total-audit.
 *   2. Brief uptime (S1) — ≥1 brief gerado nas últimas 24h
 *   3. Custo Brain B 24h — alvo ≤ R$ [redacted Tier 0]/dia
 *   4. PII leak detection (COPI-43) — mensagens user com regex CPF/email
 *   5. ProfileDistiller drift (COPI-26) — profiles >7d sem regenerar
 *   6. Procedure drift (US-COPI-092) — hash deployed vs migration canônica
 *   7. Spec ID drift (ADR 0134) — colisão DB↔SPEC.md (mesmo ID, title diferente)
 *   8. Whatsapp media pending 1h (Guardião 6 Camada 6) — mídia órfã > 1h
 *   8b. Whatsapp inbound flow (sentinela de fluxo real · incidente 2026-06-16
 *       #2726) — por canal ativo com histórico, último inbound > Nh em horário
 *       comercial = ALERTA. Pega QUALQUER causa de silêncio no recebimento.
 *   8c. Whatsapp inbound canary (auto-teste do alarme · Fase 1 perda-zero) — lê
 *       o último tick do cron `whatsapp:webhook-canary`; stale/falha em horário
 *       comercial = ALERTA. Prova que a VIA responde 200 E que o monitor não
 *       apodreceu mentindo verde (raiz do #2726).
 *   9. MCP webhook 5xx 2h (US-FIN-043 incident 2026-05-21) — webhook GitHub
 *      retornando 5xx nas últimas 2h indica drift no DB sync (tasks/memory)
 *  10. Memoria recall backend (resiliência Meilisearch) — MCP/Meilisearch
 *      reachable; alerta ANTES da degradação silenciosa (chat responde sem
 *      recall, NÃO estoura 500). McpMemoriaDriver já degrada gracioso.
 *  11. Lesson ledger graduation (ADVISORY · Reflexion runtime) — toda lição de
 *      operação em LICOES-OPERACAO.md nasceu graduada (MEC→check / JULG→regra);
 *      acende amarelo se há entrada malformada ou `status:pendente`.
 *  11b. governanca_graduation_ratio (ADVISORY) — espelha (11) pros 2 ledgers.
 *  11c. protocol_freshness (ADVISORY · espelha review-freshness #2078) — UC das
 *      telas canon (uc-registry.json) amarrado a GUARD Pest `uc-<id>`; acende UC
 *      sem cobertura / GUARD sumido / charter ausente / UC morto. Ratchet baseline.
 *  12-17. Charter/loop health (ADVISORY · CharterHealthChecker, PROTOCOL §6) —
 *      charter_missing, charter_stale (>90d), charter_refs_broken,
 *      charter_method_missing, readme_handoff_block_missing (L-18),
 *      design_return_skipped (retorno §10.2 atrás do SYNC_LOG · G4).
 *      Reportam mas NÃO falham o exit code nem o ALERT de cron (viram
 *      ratchet depois que o baseline de charters existir).
 *
 * Uso:
 *   php artisan jana:health-check
 *   php artisan jana:health-check --json (output machine-readable)
 *   php artisan jana:health-check --notify (escreve em log alert)
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'jana:health-check
                            {--json : Output JSON em vez de tabela}
                            {--notify : Loga ALERT em jana-health channel se algo falhou}';

    protected $description = 'Health check diário Jana + Constituição v2 (12 checks DUROS incl. distiller_freshness + ledger de lições + charter/loop advisory)';

    /**
     * Margem de segurança da invariante de valor (check 1b). Desconto só REDUZ;
     * acima de (total_before_tax + tax + shipping) × margem = valor corrompido.
     * 1.5 evita falso-positivo por arredondamento/edge. Const compartilhada entre
     * o SQL (whereRaw) e o predicado puro `valueExceedsCeiling` — não divergem.
     */
    public const VALUE_SANITY_MARGIN = 1.5;

    /**
     * Predicado puro da invariante de valor do check 1b (incidente "Guilherme").
     * Fonte da verdade do contrato — o SQL do check espelha esta regra via a mesma
     * const. Testável sem DB (contrato, não tautologia da implementação).
     */
    public static function valueExceedsCeiling(
        float $finalTotal,
        float $totalBeforeTax,
        float $tax = 0.0,
        float $shipping = 0.0,
    ): bool {
        if ($totalBeforeTax <= 0 || $finalTotal <= 0) {
            return false;
        }
        $ceiling = ($totalBeforeTax + $tax + $shipping) * self::VALUE_SANITY_MARGIN;

        return $finalTotal > $ceiling;
    }

    public function handle(): int
    {
        $checks = [
            $this->checkMultiTenant(),
            $this->checkSellsValueSanity(),
            $this->checkBriefUptime(),
            $this->checkCustoBrainB(),
            $this->checkPiiLeak(),
            $this->checkProfileDrift(),
            $this->checkDistillerFreshness(),
            $this->checkProcedureDrift(),
            $this->checkSpecIdDrift(),
            $this->checkWhatsappMediaPending1h(),
            $this->checkWhatsappInboundFlow(),
            $this->checkWhatsappInboundCanary(),
            $this->checkMcpWebhookHealth2h(),
            $this->checkMemoriaRecallBackend(),
            $this->checkLessonLedgerGraduation(),
            $this->checkGovernancaGraduationRatio(),
            $this->checkProtocolFreshness(),
            // Charter health (advisory — não falha exit/cron). PROTOCOL §6.
            ...CharterHealthChecker::fromApp()->checks(),
        ];

        // Advisory checks (charter) reportam mas não derrubam o exit code:
        // contam como "ok" pro gate enquanto não viram ratchet.
        $allOk = self::allChecksOk($checks);

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => $allOk,
                'checked_at' => now()->toIso8601String(),
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTable($checks, $allOk);
        }

        // Log estruturado pra Kibana/Datadog/grep posterior
        Log::channel('single')->info('jana:health-check', [
            'ok' => $allOk,
            'checks' => collect($checks)->mapWithKeys(fn ($c) => [
                $c['name'] => [
                    'ok' => $c['ok'],
                    'value' => $c['value'],
                    'threshold' => $c['threshold'] ?? null,
                ],
            ])->toArray(),
        ]);

        if ($this->option('notify') && ! $allOk) {
            // Só falha DURA (não-advisory) dispara o ALERT de cron.
            $failed = collect($checks)
                ->filter(fn ($c) => ! $c['ok'] && ! ($c['advisory'] ?? false))
                ->pluck('name')->implode(', ');
            Log::channel('single')->error("jana:health-check ALERT — falhou: {$failed}");
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Veredito do gate: OK a menos que algum check NÃO-advisory tenha ok=false.
     * Extraído pra ser testável SEM DB (bite-test SentinelBiteTest) — prova que o
     * exit code RESPONDE ao estado dos checks, não é constante (auditoria de
     * sentinelas 2026-06-20). A regra advisory é a parte sutil: advisory false NÃO
     * derruba o gate; check duro false derruba.
     */
    public static function allChecksOk(array $checks): bool
    {
        return collect($checks)->every(fn ($c) => ($c['ok'] ?? false) || ($c['advisory'] ?? false));
    }

    /**
     * Check 1 — Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL).
     * Vazar business_id é o pior bug possível. Tolerância: 0.
     */
    protected function checkMultiTenant(): array
    {
        $tabelas = ['jana_memoria_facts', 'jana_business_profile', 'jana_mensagens'];
        $orfaos = 0;
        $detalhes = [];

        foreach ($tabelas as $tabela) {
            try {
                $count = DB::table($tabela)->whereNull('business_id')->count();
                if ($count > 0) {
                    $detalhes[] = "{$tabela}: {$count} órfãos";
                }
                $orfaos += $count;
            } catch (\Throwable $e) {
                $detalhes[] = "{$tabela}: ERRO ({$e->getMessage()})";
            }
        }

        return [
            'name' => 'multi_tenant_isolation',
            'ok' => $orfaos === 0,
            'value' => $orfaos,
            'threshold' => 0,
            'message' => $orfaos === 0
                ? 'Zero órfãos business_id IS NULL'
                : 'ALERTA Tier 0: ' . implode(' · ', $detalhes),
        ];
    }

    /**
     * Check 1b — Sanidade de VALOR em vendas (sensor do incidente "Guilherme" 2026-06-05).
     *
     * Invariante dura: `final_total` NUNCA pode exceder
     * `total_before_tax + tax_amount + shipping_charges` — desconto só REDUZ o total,
     * jamais aumenta. Qualquer venda que viole isso tem valor corrompido (caso canônico:
     * `Util::num_uf` strippando o ponto decimal de total fracionado → final_total inflado
     * ~×100.000). Fator 1.5 de margem evita falso-positivo por arredondamento/edge.
     *
     * Por que existe: no incidente 2026-06-05 o erro só foi descoberto 8 dias depois,
     * quando o cliente (Guilherme/Larissa) reportou no WhatsApp — apesar de a lógica de
     * detecção JÁ existir no `App\Console\Commands\SellsFinalTotalAuditCommand` (Heurística A),
     * que ninguém roda diariamente. Este check pluga essa detecção no sentinela 06:00 BRT:
     * o valor corrompido se auto-identifica em ≤24h, ANTES do cliente perceber.
     *
     * DETECÇÃO-ONLY (read-only). A CORREÇÃO permanece human-gated per Regra Mestre Tier 0
     * (`memory/proibicoes.md` §"CÁLCULO DE VALOR ou ESTOQUE") — via `sells:final-total-audit`
     * com dupla confirmação + antes→depois + aprovação humana.
     *
     * @see app/Console/Commands/SellsFinalTotalAuditCommand.php (correção per-row)
     * @see memory/sessions/2026-06-05-veiculo-na-venda-e-incidente-numuf-valor-inflado.md
     */
    protected function checkSellsValueSanity(): array
    {
        try {
            $schema = \Illuminate\Support\Facades\Schema::class;
            if (! $schema::hasTable('transactions') || ! $schema::hasColumn('transactions', 'final_total')) {
                return [
                    'name' => 'sells_value_sanity',
                    'ok' => true,
                    'value' => 'n/a',
                    'threshold' => 0,
                    'message' => 'Tabela transactions ausente — UltimatePOS core não instalado',
                ];
            }

            // Janela 48h pra cobrir vendas da véspera (cron 06:00). Invariante:
            // final_total ≤ (total_before_tax + tax + shipping). Violação = corrupção.
            $base = DB::table('transactions')
                ->where('type', 'sell')
                ->where('created_at', '>=', now()->subHours(48))
                ->where('total_before_tax', '>', 0)
                ->where('final_total', '>', 0)
                ->whereRaw(
                    'final_total > (total_before_tax + COALESCE(tax_amount, 0) + COALESCE(shipping_charges, 0)) * ?',
                    [self::VALUE_SANITY_MARGIN],
                );

            $count = (int) (clone $base)->count();

            if ($count === 0) {
                return [
                    'name' => 'sells_value_sanity',
                    'ok' => true,
                    'value' => 0,
                    'threshold' => 0,
                    'message' => 'Zero vendas com final_total acima do teto (invariante de desconto OK)',
                ];
            }

            // Enriquecimento do alerta: quais businesses + pior exemplo (sem expor PII/valor literal).
            $bizAfetados = (clone $base)
                ->select('business_id', DB::raw('COUNT(*) as qtd'))
                ->groupBy('business_id')
                ->orderByDesc('qtd')
                ->get()
                ->map(fn ($r) => "biz={$r->business_id}({$r->qtd})")
                ->implode(', ');

            return [
                'name' => 'sells_value_sanity',
                'ok' => false,
                'value' => $count,
                'threshold' => 0,
                'message' => "ALERTA Tier 0 VALOR: {$count} venda(s) 48h com final_total > teto — {$bizAfetados}. "
                    . 'Rode `sells:final-total-audit --business=<id>` (DRY-RUN) p/ diagnóstico. Correção é human-gated (Regra Mestre).',
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'sells_value_sanity',
                'ok' => false,
                'value' => null,
                'threshold' => 0,
                'message' => 'ERRO: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check 2 — Brief uptime (Sprint 1 L7).
     * Esperado: ≥1 brief gerado nas últimas 24h (cron 6×/dia ideal).
     */
    protected function checkBriefUptime(): array
    {
        try {
            $count = DB::table('mcp_briefs')->where('generated_at', '>', now()->subHours(24))->count();
            $ultimo = DB::table('mcp_briefs')->max('generated_at');
            $threshold = 1; // mínimo 1 nas últimas 24h

            return [
                'name' => 'brief_uptime_24h',
                'ok' => $count >= $threshold,
                'value' => $count,
                'threshold' => ">= {$threshold}",
                'message' => $count >= $threshold
                    ? "{$count} briefs gerados em 24h (último: {$ultimo})"
                    : "STALE: último brief em {$ultimo}",
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'brief_uptime_24h',
                'ok' => false,
                'value' => null,
                'threshold' => '>= 1',
                'message' => 'ERRO: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check 3 — Custo Brain B nas últimas 24h.
     * Alvo: ≤ R$ [redacted Tier 0]/dia em produção atual.
     * Pricing gpt-4o-mini: $0.15/1M input + $0.60/1M output → R$ ~5 = 1M tokens/dia.
     */
    protected function checkCustoBrainB(): array
    {
        try {
            // Cotação USD→BRL aproximada (atualizar se mudar muito)
            $usdBrl = 5.0;
            $tokensIn = (int) DB::table('jana_mensagens')
                ->where('created_at', '>', now()->subHours(24))
                ->sum('tokens_in');
            $tokensOut = (int) DB::table('jana_mensagens')
                ->where('created_at', '>', now()->subHours(24))
                ->sum('tokens_out');

            $custoUsd = ($tokensIn * 0.15 / 1_000_000) + ($tokensOut * 0.60 / 1_000_000);
            $custoBrl = $custoUsd * $usdBrl;
            $threshold = 5.0; // R$/dia

            return [
                'name' => 'custo_brain_b_24h',
                'ok' => $custoBrl <= $threshold,
                'value' => round($custoBrl, 2),
                'threshold' => "<= R\$ {$threshold}",
                'message' => sprintf(
                    'R$ %.2f (in=%s out=%s tokens)',
                    $custoBrl, number_format($tokensIn), number_format($tokensOut)
                ),
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'custo_brain_b_24h',
                'ok' => false,
                'value' => null,
                'threshold' => '<= R$ [redacted Tier 0]',
                'message' => 'ERRO: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check 4 — PII leak detection nas mensagens persistidas.
     * Detecta CPF formatado, email, telefone BR em mensagens user role.
     * Espera-se ZERO PII em respostas assistant (PII redactor PR #126+#127).
     * Tolerância em mensagens user: dados reais OK (LGPD permite armazenar
     * com consentimento do tenant), só alerta se >100/dia (sinal de uso anormal).
     */
    protected function checkPiiLeak(): array
    {
        try {
            // Conta mensagens assistant com PII (deveria ser zero)
            $piiInAssistant = DB::table('jana_mensagens')
                ->where('role', 'assistant')
                ->where('created_at', '>', now()->subHours(24))
                ->where(function ($q) {
                    $q->where('content', 'REGEXP', '[0-9]{3}\\.[0-9]{3}\\.[0-9]{3}-[0-9]{2}')
                      ->orWhere('content', 'REGEXP', '[0-9]{2}\\.[0-9]{3}\\.[0-9]{3}/[0-9]{4}-[0-9]{2}');
                })
                ->count();

            return [
                'name' => 'pii_leak_in_assistant_responses',
                'ok' => $piiInAssistant === 0,
                'value' => $piiInAssistant,
                'threshold' => 0,
                'message' => $piiInAssistant === 0
                    ? 'Zero CPF/CNPJ ecoado pelo LLM em 24h'
                    : "ALERTA LGPD: {$piiInAssistant} respostas assistant com CPF/CNPJ exposto",
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'pii_leak_in_assistant_responses',
                'ok' => false,
                'value' => null,
                'threshold' => 0,
                'message' => 'ERRO: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check 5 — ProfileDistiller drift.
     * Profiles devem ser regenerados pelo job diário (max 7d sem regen).
     */
    protected function checkProfileDrift(): array
    {
        try {
            $stale = DB::table('jana_business_profile')
                ->where('gerado_em', '<', now()->subDays(7))
                ->count();

            return [
                'name' => 'profile_distiller_drift',
                'ok' => $stale === 0,
                'value' => $stale,
                'threshold' => 0,
                'message' => $stale === 0
                    ? 'Todos profiles regenerados nos últimos 7d'
                    : "STALE: {$stale} profiles >7d sem regenerar",
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'profile_distiller_drift',
                'ok' => false,
                'value' => null,
                'threshold' => 0,
                'message' => 'ERRO: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check 5b — Distiller freshness (ADR 0291 D-D · peça 3 do keystone SDD×memória).
     *
     * Gêmeo DURO da métrica `distiller_freshness` do sdd-scorecard. Lá (commitado,
     * determinístico) a staleness é medida contra a data-git do doc mais novo do
     * módulo; AQUI (runtime, não-commitado) o alarme usa "hoje" — espelha
     * checkProfileDrift (`gerado_em` < now()->subDays(7)). Conta portas
     * memory/requisitos/<Mod>/BRIEFING.md cujo `distilled_at` ficou > 7d vs agora.
     *
     * Anti-stale: SKIP (ok) enquanto NENHUMA porta tiver `distilled_at` — o distiller
     * (PR-C) só roda em prod sob gate Wagner/CT100; não acende vermelho antes do 1º
     * carimbo. DURO (não-advisory): destilação que parou derruba o exit/cron (D-3).
     *
     * @see Modules/Jana/Services/Memoria/DistillerModuloVerdade.php (PR-C — escreve o carimbo)
     */
    protected function checkDistillerFreshness(): array
    {
        $name = 'distiller_freshness';
        $reqDir = base_path('memory/requisitos');
        if (! is_dir($reqDir)) {
            return [
                'name' => $name, 'ok' => true, 'value' => 'n/a', 'threshold' => '<= 7d',
                'message' => 'Skipped (memory/requisitos/ ausente)',
            ];
        }

        $contents = array_map(
            static fn ($f) => (string) @file_get_contents($f),
            glob($reqDir . '/*/BRIEFING.md') ?: [],
        );
        $r = self::distillerFreshnessStats($contents, now()->toDateString());

        if ($r['stamped'] === 0) {
            return [
                'name' => $name, 'ok' => true, 'value' => 'n/a', 'threshold' => '<= 7d',
                'message' => 'Skipped (nenhuma porta com distilled_at — distiller não rodou; gate Wagner/CT100 · ADR 0291 D-D)',
            ];
        }

        return [
            'name' => $name,
            'ok' => $r['stale'] === 0,
            'value' => $r['stale'],
            'threshold' => 0,
            'message' => $r['stale'] === 0
                ? "Todas as {$r['stamped']} portas destiladas frescas (<= 7d)"
                : "STALE: {$r['stale']}/{$r['stamped']} portas com distilled_at > 7d (mais velha: {$r['stalest_days']}d) — rodar jana:distill-module-truth",
        ];
    }

    /**
     * Estatística pura de frescor das portas (testável sem filesystem — espelha
     * parseLessonLedger). Parseia `distilled_at:` do frontmatter de cada conteúdo
     * de BRIEFING e conta carimbadas + as que ficaram > $staleDays vs $now.
     *
     * @param  array<int, string>  $briefingContents  conteúdo de cada BRIEFING.md
     * @return array{total:int, stamped:int, stale:int, stalest_days:int}
     */
    public static function distillerFreshnessStats(array $briefingContents, string $now, int $staleDays = 7): array
    {
        $reference = \Carbon\Carbon::parse($now);
        $cutoff = $reference->copy()->subDays($staleDays);
        $stamped = 0;
        $stale = 0;
        $stalestDays = 0;

        foreach ($briefingContents as $content) {
            if (! preg_match('/^distilled_at:\s*["\']?(\d{4}-\d{2}-\d{2})/m', (string) $content, $m)) {
                continue;
            }
            $stamped++;
            $date = \Carbon\Carbon::parse($m[1]);
            if ($date->lt($cutoff)) {
                $stale++;
                $stalestDays = max($stalestDays, (int) $date->diffInDays($reference));
            }
        }

        return ['total' => count($briefingContents), 'stamped' => $stamped, 'stale' => $stale, 'stalest_days' => $stalestDays];
    }

    /**
     * Check 6 — Procedure drift (US-COPI-092).
     * Compara hash do procedure deployed vs migration canônica.
     * Falha se alguém rodou DDL direto em prod sem migration (ADR 0094 §5 SoC brutal).
     * Skipped em drivers não-MySQL (SQLite CI).
     */
    protected function checkProcedureDrift(): array
    {
        if (DB::getDriverName() !== 'mysql') {
            return [
                'name' => 'procedure_drift',
                'ok' => true,
                'value' => 'n/a',
                'threshold' => 'mysql only',
                'message' => 'Skipped (driver não-MySQL)',
            ];
        }

        try {
            $migrationFile = base_path(
                'database/migrations/2026_05_07_120000_fix_brief_aggregator_in_flight_adrs_activity.php'
            );

            $content = file_get_contents($migrationFile);
            preg_match("/<<<'SQL'\n(.+?)SQL\)/s", $content, $m);
            $canonicalSql = $m[1] ?? '';

            $rows = DB::select('SHOW CREATE PROCEDURE refresh_brief_inputs_cache');
            $deployedSql = $rows[0]->{'Create Procedure'} ?? '';

            // MySQL's SHOW CREATE PROCEDURE backtick-quotes the routine name +
            // identifiers (`CREATE ... PROCEDURE `refresh_brief_inputs_cache`()`),
            // while the migration source declares them bare (`CREATE PROCEDURE
            // refresh_brief_inputs_cache()`). Backtick quoting is never semantic, so
            // strip DEFINER first (its regex anchors on backticks) THEN drop the
            // remaining backticks on both sides — otherwise identical DDL reads as
            // drift forever (false positive US-COPI-092, caught in prod 2026-06-20).
            $normalize = static fn (string $sql): string => preg_replace(
                '/\s+/',
                ' ',
                strtolower(str_replace(
                    '`',
                    '',
                    preg_replace('/DEFINER\s*=\s*`[^`]*`@`[^`]*`\s*/i', '', trim($sql))
                ))
            );

            $drifted = md5($normalize($canonicalSql)) !== md5($normalize($deployedSql));

            return [
                'name' => 'procedure_drift',
                'ok' => ! $drifted,
                'value' => $drifted ? 'DRIFT' : 'OK',
                'threshold' => 'match',
                'message' => $drifted
                    ? 'ALERTA: refresh_brief_inputs_cache divergiu da migration — crie migration pra sincronizar'
                    : 'refresh_brief_inputs_cache bate com migration canônica',
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'procedure_drift',
                'ok' => false,
                'value' => null,
                'threshold' => 'match',
                'message' => 'ERRO: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check 7 — Spec ID drift (ADR 0134).
     * Detecta colisões duras entre DB MCP e SPEC.md (mesmo ID, title diferente).
     *
     * Caso recorrente: alguém detalha placeholder bullet "- US-XX-NNN — X" no SPEC,
     * mas `tasks-create` já criou US-XX-NNN no DB com title diferente em outra sessão.
     * Próximo git push do SPEC → conflito UNIQUE no webhook sync.
     *
     * Prevenção: TaskCrudService::gerarProximoIdCanonical agora lê headers E bullets
     * (2026-05-11). Este check é defesa em profundidade: pega drift que escapou.
     */
    protected function checkSpecIdDrift(): array
    {
        try {
            $reqDir = base_path('memory/requisitos');
            if (! is_dir($reqDir)) {
                return [
                    'name' => 'spec_id_drift',
                    'ok' => true,
                    'value' => 'n/a',
                    'threshold' => 0,
                    'message' => 'memory/requisitos/ ausente — sem SPECs pra checar',
                ];
            }

            $hardDrifts = [];
            foreach (glob($reqDir . '/*/SPEC.md') as $specPath) {
                $content = (string) @file_get_contents($specPath);

                // Pega só section headers (têm title comparável após `·`).
                // Bullets não têm title estruturado pra comparar com DB.
                if (! preg_match_all(
                    '/^###\s+(?:\S+\s+)?US-([A-Z]+)-(\d+)\s*·\s*(.+?)$/m',
                    $content,
                    $matches,
                    PREG_SET_ORDER,
                )) {
                    continue;
                }

                foreach ($matches as $m) {
                    $prefix = $m[1];
                    $n = (int) $m[2];
                    $specTitle = trim($m[3]);
                    $taskId = "US-{$prefix}-" . str_pad((string) $n, 3, '0', STR_PAD_LEFT);

                    $dbRow = DB::table('mcp_tasks')->where('task_id', $taskId)->first();
                    if ($dbRow && self::titleDriftKey((string) $dbRow->title) !== self::titleDriftKey($specTitle)) {
                        $hardDrifts[] = "{$taskId}";
                    }
                }
            }

            $count = count($hardDrifts);
            return [
                'name' => 'spec_id_drift',
                'ok' => $count === 0,
                'value' => $count,
                'threshold' => 0,
                'message' => $count === 0
                    ? 'Zero colisões duras DB↔SPEC'
                    : 'ALERTA drift duro: ' . implode(' · ', array_slice($hardDrifts, 0, 5))
                        . ($count > 5 ? " (+" . ($count - 5) . " mais)" : ''),
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'spec_id_drift',
                'ok' => false,
                'value' => null,
                'threshold' => 0,
                'message' => 'ERRO: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Chave de comparação de title pro spec_id_drift.
     *
     * Tira o prefixo de não-letra/não-dígito (emoji de status, mid-dot `·`,
     * pontuação) antes de comparar DB↔SPEC. Sem isso, 631/646 colisões eram
     * falso-positivo: o título no `mcp_tasks` carrega um emoji de status no
     * começo que o MySQL truncou pra `?` (coluna utf8mb3 × emoji 4-byte), e o
     * parser do SPEC já come o emoji antes do `US-` — então `? Listar Budget`
     * (DB) vs `Listar Budget` (SPEC) é a MESMA story, diferença só cosmética
     * (incidente health-check 2026-06-20, prod = 646 alvo 0).
     *
     * Aplicado simetricamente nos dois lados: drift REAL difere no conteúdo
     * alfanumérico (não só no enfeite inicial), então segue sendo pego — as 15
     * colisões duras genuínas (RecurringBilling-001 ×9, TR-301..303, SELL-010,
     * WA-002/010/045) continuam reportadas.
     */
    public static function titleDriftKey(string $title): string
    {
        return trim((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', trim($title)));
    }

    /**
     * Check 8 — Whatsapp media pending 1h (Guardião 6 Camada 6).
     *
     * Detecta mídia órfã > 1h (pending|downloading + media_url=null + não failed_permanent).
     * Tolerância: 0 — qualquer mídia pending > 1h significa que Observer (Camada 1)
     * + Retry hourly (Camada 4) não fecharam o caso → daemon offline, MIME novo
     * bloqueado, ou bug introduzido.
     *
     * Skip silencioso quando tabela `messages` não existe (ambientes sem módulo
     * Whatsapp instalado).
     *
     * @see Modules/Whatsapp/Jobs/DownloadMediaJob.php (Camada 3)
     * @see Modules/Whatsapp/Console/Commands/ScanMediaDriftCommand.php (Camada 5)
     */
    protected function checkWhatsappMediaPending1h(): array
    {
        try {
            // Skip se tabela não existe (módulo Whatsapp ausente).
            if (! \Illuminate\Support\Facades\Schema::hasTable('messages')) {
                return [
                    'name' => 'whatsapp_media_pending_1h',
                    'ok' => true,
                    'value' => 'n/a',
                    'threshold' => 0,
                    'message' => 'Tabela messages ausente — módulo Whatsapp não instalado',
                ];
            }

            // Skip se coluna media_download_status não existe (migration pendente).
            if (! \Illuminate\Support\Facades\Schema::hasColumn('messages', 'media_download_status')) {
                return [
                    'name' => 'whatsapp_media_pending_1h',
                    'ok' => true,
                    'value' => 'n/a',
                    'threshold' => 0,
                    'message' => 'Coluna media_download_status ausente — guardião 6 não migrado',
                ];
            }

            $count = (int) DB::table('messages')
                ->whereNotNull('media_mime')
                ->whereNull('media_url')
                ->where('media_download_status', '!=', 'failed_permanent')
                ->where('created_at', '<', now()->subHour())
                ->count();

            return [
                'name' => 'whatsapp_media_pending_1h',
                'ok' => $count === 0,
                'value' => $count,
                'threshold' => 0,
                'message' => $count === 0
                    ? 'Zero mídia órfã > 1h'
                    : "ALERTA: {$count} mídia(s) pending > 1h — checar daemon CT 100 + RetryFailedMediaDownloadsJob",
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'whatsapp_media_pending_1h',
                'ok' => false,
                'value' => null,
                'threshold' => 0,
                'message' => 'ERRO: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check 8b — Whatsapp INBOUND FLOW (sentinela de fluxo real · incidente 2026-06-16 #2726).
     *
     * O #2726 (hardening de auth do webhook) deixou o recebimento de WhatsApp MORTO
     * por 3 dias SEM ninguém ver: todo monitor media degradação DENTRO de um fluxo
     * vivo (mídia órfã, SLA, custo), nunca a AUSÊNCIA do fluxo. Com 401 antes do
     * dispatch, zero rows nasciam → zero órfãs → tudo "verde"; channel_health dizia
     * "paired" (proxy de conexão, não de recebimento).
     *
     * Este check mede o que importa e é DRIVER-AGNÓSTICO: por canal ATIVO com
     * histórico de inbound, a última mensagem recebida não pode ser mais velha que
     * o threshold em horário comercial. Pega QUALQUER causa de silêncio
     * (auth/rota/worker/firewall/daemon/UUID), sem depender de saber a causa. Hard
     * check — derruba o exit code + dispara o ALERT do cron.
     *
     * Baseline por canal: só vigia quem JÁ recebeu inbound algum dia (evita
     * falso-positivo em linha quieta/recém-criada). Fora do horário comercial BRT
     * não acende (canal quieto à noite/domingo é normal).
     */
    protected function checkWhatsappInboundFlow(): array
    {
        $name = 'whatsapp_inbound_flow';
        $thresholdHours = (int) config('whatsapp.inbound_silence_alert_hours', 6);

        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('channels')
                || ! \Illuminate\Support\Facades\Schema::hasTable('conversations')
                || ! \Illuminate\Support\Facades\Schema::hasTable('messages')) {
                return [
                    'name' => $name,
                    'ok' => true,
                    'value' => 'n/a',
                    'threshold' => "<= {$thresholdHours}h",
                    'message' => 'Tabelas de canal ausentes — módulo Whatsapp não instalado',
                ];
            }

            $channels = DB::table('channels')
                ->where('status', 'active')
                ->get(['id', 'label', 'business_id'])
                ->map(fn ($ch) => [
                    'label' => $ch->label ?? "canal {$ch->id}",
                    'business_id' => (int) $ch->business_id,
                    'last_inbound' => DB::table('messages')
                        ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
                        ->where('conversations.channel_id', $ch->id)
                        ->where('messages.direction', 'inbound')
                        ->max('messages.created_at'),
                ])
                ->all();

            $r = self::evaluateInboundFlow($channels, now()->setTimezone('America/Sao_Paulo'), $thresholdHours);

            if ($r['fora_horario']) {
                return [
                    'name' => $name,
                    'ok' => true,
                    'value' => 'fora-horário',
                    'threshold' => "<= {$thresholdHours}h",
                    'message' => 'Fora do horário comercial BRT — silêncio de inbound não alarma agora',
                ];
            }

            $count = count($r['mudos']);

            return [
                'name' => $name,
                'ok' => $count === 0,
                'value' => $count === 0 ? "{$r['vigiados']} canal(is) com fluxo" : $count,
                'threshold' => "<= {$thresholdHours}h",
                'message' => $count === 0
                    ? "Todos os {$r['vigiados']} canais ativos receberam inbound nas últimas {$thresholdHours}h"
                    : 'ALERTA recebimento parado: ' . implode(' · ', $r['mudos'])
                        . ' — checar webhook/daemon/auth (classe do incidente #2726)',
            ];
        } catch (\Throwable $e) {
            return [
                'name' => $name,
                'ok' => false,
                'value' => null,
                'threshold' => "<= {$thresholdHours}h",
                'message' => 'ERRO: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Lógica pura da sentinela de inbound — pública e estática pra ser testável sem
     * tocar DB nem o relógio real (mesmo padrão de parseLessonLedger).
     *
     * @param  array<int, array{label: string, business_id: int, last_inbound: ?string}>  $channels
     * @return array{ok: bool, vigiados: int, mudos: list<string>, fora_horario: bool}
     */
    public static function evaluateInboundFlow(array $channels, \Illuminate\Support\Carbon $nowBrt, int $thresholdHours): array
    {
        // Janela comercial BRT (08–20, seg–sáb). Fora dela, silêncio é normal.
        $horaComercial = $nowBrt->hour >= 8 && $nowBrt->hour < 20 && $nowBrt->dayOfWeek !== \Carbon\Carbon::SUNDAY;
        if (! $horaComercial) {
            return ['ok' => true, 'vigiados' => 0, 'mudos' => [], 'fora_horario' => true];
        }

        $mudos = [];
        $vigiados = 0;
        foreach ($channels as $ch) {
            $last = $ch['last_inbound'] ?? null;
            if ($last === null || $last === '') {
                continue; // sem histórico → sem baseline, não vigia
            }
            $vigiados++;
            $lastBrt = \Illuminate\Support\Carbon::parse((string) $last, 'America/Sao_Paulo');
            if ($lastBrt->greaterThanOrEqualTo($nowBrt)) {
                continue; // timestamp futuro (clock skew) → trata como fresco
            }
            $idade = (int) $lastBrt->diffInHours($nowBrt);
            if ($idade > $thresholdHours) {
                $mudos[] = "{$ch['label']} (biz {$ch['business_id']}): {$idade}h sem inbound";
            }
        }

        return ['ok' => $mudos === [], 'vigiados' => $vigiados, 'mudos' => $mudos, 'fora_horario' => false];
    }

    /**
     * Check 8c — Whatsapp INBOUND CANARY (auto-teste do alarme · camada 5 da
     * proposta perda-zero · Fase 1).
     *
     * Onde o inbound_flow (8b) mede o RESULTADO (chegou mensagem?), este mede a
     * VIA (o webhook responde 200?) — e, principalmente, prova que o próprio
     * canário (`whatsapp:webhook-canary`, cron 5min) está VIVO. Sem isso o monitor
     * pode apodrecer mentindo "verde" (a raiz do #2726: tudo verde com o
     * recebimento morto). Lê o último tick do canário em cache:
     *   - fresco + ok      → verde (caminho provado <maxAge)
     *   - fresco + não-200 → ALERTA (webhook quebrado AGORA)
     *   - velho (stale)    → ALERTA (cron do canário morreu = perdeu o sinal)
     *   - cold (nunca rodou) → verde com grace (pós-deploy; o tick popula em ≤5min)
     *
     * Hard check em horário comercial BRT (mesma janela do canário); fora dela e
     * em ambiente sem segredo (dev/CI) não acende.
     */
    protected function checkWhatsappInboundCanary(): array
    {
        $name = 'whatsapp_inbound_canary';
        $maxAge = (int) config('whatsapp.canary.freshness_max_age_minutes', 15);
        $configured = (bool) config('whatsapp.canary.enabled', true)
            && (string) config('whatsapp.whatsmeow.webhook_url_secret', '') !== '';

        try {
            $last = \Illuminate\Support\Facades\Cache::get('whatsapp:webhook-canary:last');
            $r = self::evaluateCanaryFreshness(
                $configured,
                is_array($last) ? $last : null,
                now()->setTimezone('America/Sao_Paulo'),
                $maxAge,
            );

            $messages = [
                'nao-configurado' => 'Skipped (canário do webhook não configurado — dev/CI)',
                'fora-horario' => 'Fora do horário comercial BRT — canário em pausa, não alarma agora',
                'cold' => 'Canário ainda não reportou (cold-start pós-deploy — popula em ≤5min)',
                'fresco' => "Webhook provado há {$r['age_min']}min (≤ {$maxAge}min) — caminho de recebimento vivo",
                'stale' => "ALERTA: canário sem reportar há {$r['age_min']}min (> {$maxAge}min) — cron whatsapp:webhook-canary pode estar morto (sinal perdido, classe #2726)",
                'falha' => 'ALERTA: último canário do webhook FALHOU — recebimento WhatsApp pode estar parado (classe #2726)',
            ];

            return [
                'name' => $name,
                'ok' => $r['ok'],
                'value' => $r['age_min'] !== null ? "{$r['age_min']}min" : $r['state'],
                'threshold' => "<= {$maxAge}min",
                'message' => $messages[$r['state']] ?? $r['state'],
            ];
        } catch (\Throwable $e) {
            return [
                'name' => $name,
                'ok' => false,
                'value' => null,
                'threshold' => "<= {$maxAge}min",
                'message' => 'ERRO: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Lógica pura do frescor do canário — pública e estática pra ser testável sem
     * cache nem relógio real (mesmo padrão de evaluateInboundFlow).
     *
     * @param  array{ok?: bool, status?: ?int, reason?: string, at?: string}|null  $last
     * @return array{ok: bool, state: string, age_min: ?int}
     */
    public static function evaluateCanaryFreshness(bool $configured, ?array $last, \Illuminate\Support\Carbon $nowBrt, int $maxAgeMinutes): array
    {
        if (! $configured) {
            return ['ok' => true, 'state' => 'nao-configurado', 'age_min' => null];
        }

        // Mesma janela do cron do canário (08–20, seg–sáb). Fora dela ele não roda.
        $horaComercial = $nowBrt->hour >= 8 && $nowBrt->hour < 20 && $nowBrt->dayOfWeek !== \Carbon\Carbon::SUNDAY;
        if (! $horaComercial) {
            return ['ok' => true, 'state' => 'fora-horario', 'age_min' => null];
        }

        // Cold-start: chave ausente = canário nunca reportou (pós-deploy). Grace
        // pra não falso-alarmar antes do primeiro tick (≤5min). O cenário de
        // apodrecimento (cron morre DEPOIS de rodar) cai em 'stale', não aqui.
        if ($last === null || empty($last['at'])) {
            return ['ok' => true, 'state' => 'cold', 'age_min' => null];
        }

        $at = \Illuminate\Support\Carbon::parse((string) $last['at']);
        $ageMin = (int) abs($at->diffInMinutes($nowBrt));
        if ($ageMin > $maxAgeMinutes) {
            return ['ok' => false, 'state' => 'stale', 'age_min' => $ageMin];
        }
        if (($last['ok'] ?? false) !== true) {
            return ['ok' => false, 'state' => 'falha', 'age_min' => $ageMin];
        }

        return ['ok' => true, 'state' => 'fresco', 'age_min' => $ageMin];
    }

    /**
     * Check 9 — MCP webhook health nas últimas 2h (US-FIN-043 incident 2026-05-21).
     *
     * Detecta entregas do webhook GitHub → /api/mcp/sync-memory retornando 5xx.
     * Causa raiz do incident: config cache stale + `'mcp' => [...]` duplicado no
     * Modules/Jana/Config/config.php fez `config('copiloto.mcp.sync_webhook_token')`
     * retornar NULL → controller responde {"error":"Misconfigured"} 500.
     *
     * Como funciona: lê últimas 50 entregas via GitHub API (gh CLI tem token salvo
     * em $env:GH_TOKEN ou via gh auth status), conta 5xx nas últimas 2h.
     *
     * Tolerância: 0. Qualquer 5xx significa que SPEC.md/memory pushados
     * NÃO chegaram no DB MCP — Maiara/Felipe/Eliana ficam sem ver tasks atribuídas.
     *
     * Skip silencioso se `GH_TOKEN` ausente OU `COPILOTO_MCP_WEBHOOK_ID` não setado
     * (dev/CI/test environments — só faz sentido em prod live).
     */
    protected function checkMcpWebhookHealth2h(): array
    {
        $webhookId = env('COPILOTO_MCP_WEBHOOK_ID');
        $ghToken = env('GH_TOKEN') ?: env('GITHUB_TOKEN');
        $repo = env('COPILOTO_GITHUB_REPO', 'wagnerra23/oimpresso.com');

        if (! $webhookId || ! $ghToken) {
            return [
                'name' => 'mcp_webhook_5xx_2h',
                'ok' => true,
                'value' => 'n/a',
                'threshold' => 0,
                'message' => 'Skipped (COPILOTO_MCP_WEBHOOK_ID ou GH_TOKEN ausente — dev/CI)',
            ];
        }

        try {
            $url = "https://api.github.com/repos/{$repo}/hooks/{$webhookId}/deliveries?per_page=50";
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$ghToken}",
                    'Accept: application/vnd.github+json',
                    'User-Agent: jana-health-check',
                ],
                CURLOPT_TIMEOUT => 10,
            ]);
            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return [
                    'name' => 'mcp_webhook_5xx_2h',
                    'ok' => false,
                    'value' => null,
                    'threshold' => 0,
                    'message' => "GitHub API retornou {$httpCode} — checar GH_TOKEN scopes (precisa admin:repo_hook)",
                ];
            }

            $deliveries = json_decode((string) $body, true) ?: [];
            $cutoff = now()->subHours(2);
            $count5xx = 0;
            $sample = null;

            foreach ($deliveries as $d) {
                $deliveredAt = isset($d['delivered_at']) ? \Carbon\Carbon::parse($d['delivered_at']) : null;
                if (! $deliveredAt || $deliveredAt->lt($cutoff)) {
                    continue;
                }
                $code = (int) ($d['status_code'] ?? 0);
                if ($code >= 500 && $code < 600) {
                    $count5xx++;
                    $sample ??= "id={$d['id']} {$code} @ {$d['delivered_at']}";
                }
            }

            return [
                'name' => 'mcp_webhook_5xx_2h',
                'ok' => $count5xx === 0,
                'value' => $count5xx,
                'threshold' => 0,
                'message' => $count5xx === 0
                    ? 'Webhook GitHub MCP saudável nas últimas 2h'
                    : "ALERTA: {$count5xx} entrega(s) 5xx em 2h — SPEC.md push pode não estar virando task no DB (ex: {$sample})",
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'mcp_webhook_5xx_2h',
                'ok' => false,
                'value' => null,
                'threshold' => 0,
                'message' => 'ERRO: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check 10 — Memoria recall backend (resiliência Meilisearch, ponto único de falha).
     *
     * O recall (tool `memoria-search`) é servido pelo MCP server CT 100 que consulta
     * Meilisearch. Se Meilisearch/MCP cai, o McpMemoriaDriver degrada SILENCIOSAMENTE
     * (retorna [] → chat responde sem memória, NÃO estoura 500). Bom pro usuário,
     * cego pro ops. Este check é o alarme: prova reachability 1×/dia e alerta ANTES
     * de alguém perceber "a Jana esqueceu tudo". Check DURO (não-advisory): recall
     * down derruba o exit code e dispara o ALERT de cron.
     *
     * Skip silencioso em dev/CI (sem COPILOTO_MCP_SYSTEM_TOKEN → fallback local,
     * não há backend remoto pra checar).
     *
     * @see Modules/Jana/Services/Memoria/McpMemoriaDriver.php (degradação graciosa)
     */
    protected function checkMemoriaRecallBackend(): array
    {
        $name = 'memoria_recall_backend';
        $url = (string) config('copiloto.mcp.url', 'https://mcp.oimpresso.com/api/mcp');
        $token = (string) config('copiloto.mcp.system_token', env('COPILOTO_MCP_SYSTEM_TOKEN', ''));

        // Sem token = ambiente sem recall remoto (dev/CI). Não é falha — pula.
        if ($url === '' || $token === '') {
            return [
                'name' => $name,
                'ok' => true,
                'value' => 'n/a',
                'threshold' => 'reachable',
                'message' => 'Skipped (recall MCP não configurado — dev/CI usa fallback local)',
            ];
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withToken($token, 'Bearer')
                ->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json'])
                ->timeout((int) config('copiloto.mcp.timeout_seconds', 5))
                ->post($url, [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'tools/call',
                    'params' => [
                        'name' => 'memoria-search',
                        'arguments' => ['query' => 'health', 'business_id' => 1, 'limit' => 1],
                    ],
                ]);

            $ok = $response->ok();

            return [
                'name' => $name,
                'ok' => $ok,
                'value' => $ok ? 'up' : (string) $response->status(),
                'threshold' => 'reachable',
                'message' => $ok
                    ? 'Recall backend (MCP/Meilisearch) respondendo — memória ativa'
                    : "ALERTA: recall backend não-OK ({$response->status()}) — chat degrada SEM memória (não estoura 500). Checar MCP server + Meilisearch CT 100.",
            ];
        } catch (\Throwable $e) {
            return [
                'name' => $name,
                'ok' => false,
                'value' => 'down',
                'threshold' => 'reachable',
                'message' => 'ALERTA: recall backend inacessível (' . mb_substr($e->getMessage(), 0, 80)
                    . ') — chat degrada SEM memória. Checar Meilisearch/MCP CT 100.',
            ];
        }
    }

    /**
     * Check 11 — Ledger de lições de operação (Reflexion runtime · advisory).
     *
     * Lê Modules/Jana/LICOES-OPERACAO.md e valida o LOOP DE GRADUAÇÃO: toda lição
     * de operação registrada precisa nascer graduada (MEC → vira check · JULG → vira
     * regra sempre-lida). Acende amarelo (advisory, não derruba cron) se alguma entrada
     * está malformada ou ainda `status:pendente` — fechando o loop por métrica
     * (Constituição v2 §4) sem construir mecanismo novo: é só mais um check.
     *
     * Advisory de propósito: drift de processo de governança não deve paginar à noite
     * nem falhar o exit/cron; reporta e fica visível no scorecard.
     *
     * Skip silencioso se o ledger ainda não existe (ambiente sem o doc / pré-merge).
     *
     * @see Modules/Jana/LICOES-OPERACAO.md
     * @see memory/decisions/proposals/jana-ledger-licoes-operacao-reflexion.md
     */
    protected function checkLessonLedgerGraduation(): array
    {
        $name = 'jana_lesson_ledger_graduation';
        $path = base_path('Modules/Jana/LICOES-OPERACAO.md');

        if (! is_file($path)) {
            return [
                'name' => $name,
                'ok' => true,
                'advisory' => true,
                'value' => 'n/a',
                'threshold' => 0,
                'message' => 'Skipped (ledger LICOES-OPERACAO.md ausente — pré-merge/dev)',
            ];
        }

        $r = self::parseLessonLedger((string) file_get_contents($path));
        $problemas = array_merge(
            array_map(fn ($id) => "{$id} (malformada)", $r['malformed']),
            array_map(fn ($id) => "{$id} (pendente)", $r['overdue']),
        );
        $count = count($problemas);

        return [
            'name' => $name,
            'ok' => $count === 0,
            'advisory' => true,
            'value' => $count,
            'threshold' => 0,
            'message' => $count === 0
                ? "{$r['total']} lição(ões) de operação — todas graduadas (MEC→check / JULG→regra)"
                : 'Loop de graduação aberto: ' . implode(' · ', array_slice($problemas, 0, 6))
                    . ($count > 6 ? ' (+' . ($count - 6) . ' mais)' : ''),
        ];
    }

    /**
     * Os DOIS ledgers de lições que a governança mecaniza (camada [CC]×Jana).
     * `path` relativo ao base_path; `header` = regex de header (grupo 1 = ID).
     *
     * @var array<string, array{path:string, header:string}>
     */
    public const GOVERNANCA_LEDGERS = [
        'operacao' => ['path' => 'Modules/Jana/LICOES-OPERACAO.md', 'header' => '/^###\s+(L-OP-\d+)\b/m'],
        'cc'       => ['path' => 'memory/LICOES_CC.md',             'header' => '/^##\s+(L-\d+)\b/m'],
    ];

    /**
     * Estatística de graduação de UM ledger (reusa parseLessonLedger).
     *
     * `graduadas` = lições com Graduação válida + status:done · `pendentes` = resto
     * (status:pendente, malformada OU sem linha de Graduação). `graduation_ratio` =
     * graduadas / total (ledger vazio = 1.0, vacuosamente "fechado").
     *
     * @return array{total:int, graduadas:int, pendentes:int, pendentes_ids:list<string>, graduation_ratio:float}|null  null se o arquivo não existe
     */
    public static function ledgerGraduationStats(string $absPath, string $headerPattern): ?array
    {
        if (! is_file($absPath)) {
            return null;
        }

        $r = self::parseLessonLedger((string) file_get_contents($absPath), $headerPattern);
        $pendentesIds = array_values(array_unique(array_merge($r['overdue'], $r['malformed'])));
        $graduadas = max(0, $r['total'] - count($pendentesIds));
        $ratio = $r['total'] > 0 ? round($graduadas / $r['total'], 4) : 1.0;

        return [
            'total'            => $r['total'],
            'graduadas'        => $graduadas,
            'pendentes'        => count($pendentesIds),
            'pendentes_ids'    => $pendentesIds,
            'graduation_ratio' => $ratio,
        ];
    }

    /**
     * Check governanca_graduation_ratio (ADVISORY · camada 3 do placar [CC]×Jana).
     *
     * Espelha `jana_lesson_ledger_graduation` mas pros DOIS ledgers (operação + [CC]).
     * Acende amarelo se algum ledger tem `graduation_ratio < 1.0` ou lição pendente —
     * é a métrica contável da meta 9.7 (placar que regenera sozinho, não prosa digitada).
     * Advisory de propósito: drift de governança não derruba cron nem exit code.
     *
     * @see governanca:scorecard (Modules/Governance — agrega isto num JSON pro report [CC])
     */
    protected function checkGovernancaGraduationRatio(): array
    {
        $name = 'governanca_graduation_ratio';

        $partes = [];
        $algumProblema = false;
        $algumPresente = false;

        foreach (self::GOVERNANCA_LEDGERS as $key => $cfg) {
            $stats = self::ledgerGraduationStats(base_path($cfg['path']), $cfg['header']);
            if ($stats === null) {
                continue;
            }
            $algumPresente = true;
            $partes[] = sprintf(
                '%s %d/%d (%d%%)',
                $key,
                $stats['graduadas'],
                $stats['total'],
                (int) round($stats['graduation_ratio'] * 100)
            );
            if ($stats['graduation_ratio'] < 1.0 || $stats['pendentes'] > 0) {
                $algumProblema = true;
            }
        }

        if (! $algumPresente) {
            return [
                'name' => $name,
                'ok' => true,
                'advisory' => true,
                'value' => 'n/a',
                'threshold' => '100%',
                'message' => 'Skipped (nenhum ledger de lições presente — pré-merge/dev)',
            ];
        }

        return [
            'name' => $name,
            'ok' => ! $algumProblema,
            'advisory' => true,
            'value' => implode(' · ', $partes),
            'threshold' => '100%',
            'message' => $algumProblema
                ? 'Graduação incompleta (meta 9.7 = ambos 100%): ' . implode(' · ', $partes)
                : 'Ambos ledgers 100% graduados — ' . implode(' · ', $partes),
        ];
    }

    /**
     * Check protocol_freshness (ADVISORY · frescor do protocolo UC→charter→GUARD).
     *
     * Espelha em PHP o `prototipo-ui/audit/protocol-freshness.mjs` (que espelha o
     * molde `review-freshness.mjs` #2078). Acende amarelo quando:
     *   (a) UC com `guard:true` no registro sem GUARD linkado nos testes (regressão);
     *   (b) tela canon sem charter;
     *   (c) charter cita UC que não existe mais no registro (UC morto);
     *   + lista (advisory, ratchet via baseline) os UCs sem cobertura (gaps).
     *
     * A LEI (PROTOCOL/charter) continua de [W]; o check só ACENDE e o [CL] propõe
     * a reconciliação (§10.4). Emite storage/reports/protocol-freshness.json pro
     * ciclo diário (governanca:ciclo-diario) ler. Advisory: não derruba cron.
     *
     * @see prototipo-ui/audit/uc-registry.json  (fonte única UC→tela→GUARD)
     */
    protected function checkProtocolFreshness(): array
    {
        $name = 'protocol_freshness';
        $registryPath = base_path('prototipo-ui/audit/uc-registry.json');

        if (! is_file($registryPath)) {
            return [
                'name' => $name, 'ok' => true, 'advisory' => true, 'value' => 'n/a',
                'threshold' => '0 regressão', 'message' => 'Skipped (uc-registry.json ausente — ponte UC-guards não landada)',
            ];
        }

        $registry = json_decode((string) file_get_contents($registryPath), true);
        $baseline = is_file($b = base_path('prototipo-ui/audit/protocol-freshness-baseline.json'))
            ? (json_decode((string) file_get_contents($b), true)['sem_cobertura'] ?? [])
            : [];
        $baseSem = array_flip(is_array($baseline) ? $baseline : []);

        $testsText = $this->concatTestsText();
        $geradorWired = str_contains($testsText, 'uc-registry.json');

        $cobertos = $semCobertura = $guardQuebrado = $charterAusente = $ucMorto = [];
        $idsRegistro = [];
        foreach (($registry['screens'] ?? []) as $s) {
            foreach (($s['ucs'] ?? []) as $uc) {
                $idsRegistro[$uc['uc']] = true;
            }
        }

        foreach (($registry['screens'] ?? []) as $s) {
            $charterAbs = base_path($s['charter']);
            if (! is_file($charterAbs)) {
                $charterAusente[] = "{$s['id']}:{$s['charter']}";
            } else {
                preg_match_all('/UC-[A-Z0-9]+/', (string) file_get_contents($charterAbs), $m);
                foreach (array_unique($m[0] ?? []) as $cit) {
                    if (! isset($idsRegistro[$cit])) {
                        $ucMorto[] = "{$s['id']}:{$cit}";
                    }
                }
            }
            foreach (($s['ucs'] ?? []) as $uc) {
                $tag = 'uc-' . strtolower((string) preg_replace('/^UC-/', '', $uc['uc']));
                if ($uc['guard'] ?? false) {
                    if ($geradorWired || str_contains($testsText, "'{$tag}'") || str_contains($testsText, "\"{$tag}\"")) {
                        $cobertos[] = "{$s['id']}:{$uc['uc']}";
                    } else {
                        $guardQuebrado[] = "{$s['id']}:{$uc['uc']}";
                    }
                } else {
                    $semCobertura[] = "{$s['id']}:{$uc['uc']}";
                }
            }
        }

        $novoSem = array_values(array_filter($semCobertura, fn ($x) => ! isset($baseSem[$x])));
        $acende = array_merge(
            array_map(fn ($x) => ['tipo' => 'sem_cobertura', 'ref' => $x], $semCobertura),
            array_map(fn ($x) => ['tipo' => 'guard_quebrado', 'ref' => $x], $guardQuebrado),
            array_map(fn ($x) => ['tipo' => 'charter_ausente', 'ref' => $x], $charterAusente),
            array_map(fn ($x) => ['tipo' => 'uc_morto', 'ref' => $x], $ucMorto),
        );
        $regressao = array_merge($guardQuebrado, $charterAusente, $ucMorto, $novoSem);

        $this->emitProtocolReport([
            'generated_against_sha' => null,
            'cobertos' => $cobertos,
            'acende' => $acende,
            'regressao_count' => count($regressao),
            'baseline_sem_cobertura' => array_keys($baseSem),
        ]);

        $value = sprintf('%d cobertos · %d gaps · %d regressão', count($cobertos), count($semCobertura), count($regressao));

        return [
            'name' => $name,
            'ok' => $regressao === [],
            'advisory' => true,
            'value' => $value,
            'threshold' => '0 regressão (gaps ⊆ baseline)',
            'message' => $regressao === []
                ? "Protocolo fresco — {$value} (gaps conhecidos no baseline)"
                : 'Regressão de protocolo (GUARD/charter/UC): ' . implode(' · ', array_slice($regressao, 0, 6)),
        ];
    }

    /** Concatena o texto dos testes Pest (procura wiring do gerador + tags uc-<id>). */
    private function concatTestsText(): string
    {
        $dir = base_path('tests');
        if (! is_dir($dir)) {
            return '';
        }
        $buf = '';
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $buf .= (string) file_get_contents($f->getPathname()) . "\n";
            }
        }

        return $buf;
    }

    /** @param array<string, mixed> $payload */
    private function emitProtocolReport(array $payload): void
    {
        try {
            $dir = storage_path('reports');
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents(
                $dir . '/protocol-freshness.json',
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
            );
        } catch (\Throwable) {
            // advisory — falha ao escrever o report não derruba o check.
        }
    }

    /**
     * Parser determinístico do ledger de lições de operação.
     *
     * Contrato (ver Modules/Jana/LICOES-OPERACAO.md → "Formato canônico"):
     *   - cada lição = bloco iniciado por `### L-OP-NNN`
     *   - linha `- **Graduação:** <MEC|JULG> · <check:`x`|regra:`y`> · status:<done|pendente>`
     *
     * Regras de validação:
     *   - sem linha Graduação OU tipo ∉ {MEC,JULG} → malformed
     *   - MEC com status done sem `check:` → malformed
     *   - JULG com status done sem `regra:` → malformed
     *   - status pendente (qualquer tipo) → overdue (loop ainda aberto)
     *
     * Público + estático pra ser testável sem tocar o filesystem real.
     *
     * Generalizado (W28 governanca:scorecard) pra rodar nos DOIS ledgers via
     * `$headerPattern`: default = ledger de operação (`### L-OP-NNN`). O ledger [CC]
     * (`memory/LICOES_CC.md`) usa `## L-NN` — passa o pattern correspondente. O grupo
     * de captura 1 do regex tem que ser o ID da lição.
     *
     * @return array{total:int, overdue:list<string>, malformed:list<string>}
     */
    public static function parseLessonLedger(string $content, string $headerPattern = '/^###\s+(L-OP-\d+)\b/m'): array
    {
        $overdue = [];
        $malformed = [];

        // Quebra em blocos por header de lição (### L-OP-NNN | ## L-NN ...).
        $blocos = preg_split($headerPattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        // $blocos = [prefixo, id1, corpo1, id2, corpo2, ...]
        $total = 0;
        for ($i = 1; $i < count($blocos); $i += 2) {
            $id = $blocos[$i];
            $corpo = $blocos[$i + 1] ?? '';
            $total++;

            if (! preg_match('/\*\*Gradua[çc][ãa]o:\*\*\s*(MEC|JULG)\b(.*)$/mu', $corpo, $m)) {
                $malformed[] = $id;
                continue;
            }
            $tipo = $m[1];
            $resto = $m[2];

            $pendente = (bool) preg_match('/status:\s*pendente/iu', $resto);
            if ($pendente) {
                $overdue[] = $id;
                continue;
            }

            // status:done (default) — exige o binding do tipo.
            if ($tipo === 'MEC' && ! preg_match('/check:\s*`?[\w-]+`?/u', $resto)) {
                $malformed[] = $id;
            } elseif ($tipo === 'JULG' && ! preg_match('/regra:\s*`?[^`·\s][^`·]*`?/u', $resto)) {
                $malformed[] = $id;
            }
        }

        return ['total' => $total, 'overdue' => $overdue, 'malformed' => $malformed];
    }

    protected function renderTable(array $checks, bool $allOk): void
    {
        $this->newLine();
        $this->line('┌─────────────────────────────────────────────────────────────────────────┐');
        $this->line('│  JANA HEALTH CHECK — ' . str_pad(now()->toDateTimeString(), 51) . '│');
        $this->line('└─────────────────────────────────────────────────────────────────────────┘');
        $this->newLine();

        $rows = collect($checks)->map(fn ($c) => [
            $c['ok'] ? '✓' : (($c['advisory'] ?? false) ? '⚠' : '✗'),
            $c['name'],
            $c['value'] !== null ? (string) $c['value'] : '—',
            (string) ($c['threshold'] ?? '—'),
            substr($c['message'], 0, 60),
        ])->toArray();

        $this->table(['', 'Check', 'Valor', 'Alvo', 'Status'], $rows);

        $this->newLine();
        $advisoryWarn = collect($checks)->filter(fn ($c) => ! $c['ok'] && ($c['advisory'] ?? false))->count();
        if ($allOk) {
            $total = count($checks);
            $msg = "✓ {$total} checks sem falha dura. Sistema saudável.";
            if ($advisoryWarn > 0) {
                $msg .= " ⚠ {$advisoryWarn} advisory (charter) pra revisar.";
            }
            $this->info($msg);
        } else {
            $failed = collect($checks)->filter(fn ($c) => ! $c['ok'] && ! ($c['advisory'] ?? false))->count();
            $this->error("✗ {$failed} check(s) falharam — investigar acima."
                . ($advisoryWarn > 0 ? " (+{$advisoryWarn} advisory ⚠)" : ''));
        }
        $this->newLine();
    }
}
