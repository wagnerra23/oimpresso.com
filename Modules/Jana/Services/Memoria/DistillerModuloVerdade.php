<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Memoria;

use App\Util\OtelHelper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * PR-C do keystone distiller-módulo-verdade ([ADR 0291] · emenda 0270 F3 · peça 2).
 *
 * O motor "diário → manual": lê os eventos recentes de um módulo (sessions,
 * handoffs, PRs, audits — já coletados pelo COMANDO) e REESCREVE a porta
 * `memory/requisitos/<Mod>/BRIEFING.md` como verdade mastigada ≤1 página, MUTÁVEL
 * (sobrescreve, NÃO append — D-1/D-2), carimbando `distilled_at:` + proveniência
 * no rodapé. Reusa a chamada LLM do ProfileDistiller (AnonymousAgent) — não reinventa.
 *
 * Tier 0 ([ADR 0291] D-E): o output passa pelo PiiRedactor ANTES de escrever e a
 * destilação é RECUSADA se houver PII estruturada (CPF/CNPJ/email/telefone/CEP) —
 * repo público; não envenena a canon. A SELEÇÃO de "o que distilar" é pura/testável
 * (ModuleTruthEventCollector, PR-B). A EXECUÇÃO em prod é gate Wagner/CT100 (não aqui).
 *
 * Recebe os eventos já coletados (impuro = FS/git fica no comando) → testável com
 * Ai::fakeAgent + path de BRIEFING temporário, sem git nem prod.
 */
final class DistillerModuloVerdade
{
    /** Teto de eventos alimentados à LLM (recência prioriza — ADR 0291 D-A). */
    public const MAX_EVENTS = 40;

    public function __construct(private PiiRedactor $pii) {}

    /**
     * Destila a verdade-do-módulo e (re)escreve a porta.
     *
     * @param  array<int, array<string, mixed>>  $candidateEvents  eventos crus do módulo
     * @return array{status:string, written:bool, selected?:int, content?:string, pii?:array<string,int>, path?:string}
     *         status ∈ {written, dry, refused_pii, no_events}
     */
    public function destilar(
        string $module,
        array $candidateEvents,
        string $briefingPath,
        ?string $lastDistilledAt = null,
        bool $dryRun = false,
        ?string $now = null,
    ): array {
        $now ??= CarbonImmutable::now()->toDateString();

        return OtelHelper::span('jana.distiller.modulo_verdade', [
            'module' => $module,
            'dry_run' => $dryRun,
        ], fn () => $this->destilarInternal($module, $candidateEvents, $briefingPath, $lastDistilledAt, $dryRun, $now));
    }

    /** @param array<int, array<string, mixed>> $candidateEvents */
    private function destilarInternal(
        string $module,
        array $candidateEvents,
        string $briefingPath,
        ?string $lastDistilledAt,
        bool $dryRun,
        string $now,
    ): array {
        $selected = ModuleTruthEventCollector::select(
            $candidateEvents, $module, $lastDistilledAt, ModuleTruthEventCollector::DEFAULT_WINDOW_DAYS, $now, self::MAX_EVENTS,
        );

        if ($selected === []) {
            return ['status' => 'no_events', 'written' => false, 'selected' => 0];
        }

        $existing = is_file($briefingPath) ? (string) file_get_contents($briefingPath) : null;

        // LLM destila a verdade atual (reusa AnonymousAgent — idem ProfileDistiller).
        $agent = new AnonymousAgent(
            instructions: $this->systemPrompt($module),
            messages: [],
            tools: [],
        );
        $body = trim((string) $agent->prompt($this->userPrompt($module, $selected, $existing)));

        // Tier 0 D-E: recusa se a LLM emitiu PII estruturada — NÃO sobrescreve a porta
        // (preserva o que estava lá; um humano destila de novo). Não silencia redactando.
        $detected = $this->pii->detect($body);
        if ($detected !== []) {
            Log::channel('copiloto-ai')->warning('DistillerModuloVerdade: recusado por PII', [
                'module' => $module,
                'pii_types' => array_keys($detected),
            ]);

            return ['status' => 'refused_pii', 'written' => false, 'selected' => count($selected), 'pii' => $detected];
        }

        $content = $this->montarBriefing($module, $body, $selected, $now, $existing);

        if ($dryRun) {
            return ['status' => 'dry', 'written' => false, 'selected' => count($selected), 'content' => $content];
        }

        file_put_contents($briefingPath, $content);
        Log::channel('copiloto-ai')->info('DistillerModuloVerdade: porta reescrita', [
            'module' => $module,
            'path' => $briefingPath,
            'eventos' => count($selected),
        ]);

        return ['status' => 'written', 'written' => true, 'selected' => count($selected), 'content' => $content, 'path' => $briefingPath];
    }

    /** Enum fechado de `status` (espelha briefing.schema.json — mantenha em sincronia). */
    private const STATUS_ENUM = [
        'producao', 'piloto', 'em-construcao', 'parcial', 'backlog', 'shared-infra', 'meta', 'deprecated',
    ];

    /**
     * Monta a porta final: frontmatter (carimbo) + corpo destilado + proveniência
     * no RODAPÉ (não inline — D-2). Sobrescreve por completo (mutável).
     *
     * Frontmatter emite os 3 campos `required` do briefing.schema.json — `module`,
     * `status`, `updated_at` — além dos aliases do gerador (`distilled_at`/`distilled_by`).
     * Sem eles todo BRIEFING re-destilado dispara o memory-schema-gate (hoje grace/warning;
     * required após backfill — ADR 0314). `updated_at` == `distilled_at` (o schema unifica
     * last_review/updated/last_pr/distilled_at). `status` PRESERVA o valor anterior da porta
     * (não re-infere a cada destilação: o distiller não pode rebaixar um módulo em produção
     * só porque re-rodou); sem valor prévio válido → 'em-construcao' (conservador; um humano promove).
     *
     * @param  array<int, array<string, mixed>>  $selected
     */
    private function montarBriefing(string $module, string $body, array $selected, string $now, ?string $existing): string
    {
        $status = $this->statusPreservado($existing);
        $fm = "---\n"
            . "module: {$module}\n"
            . "status: {$status}\n"
            . "updated_at: \"{$now}\"\n"
            . "distilled_at: \"{$now}\"\n"
            . "distilled_by: jana:distill-module-truth\n"
            . "---\n";

        $prov = "\n\n## Proveniência (destilado de)\n\n";
        foreach ($selected as $e) {
            $type = (string) ($e['type'] ?? 'evento');
            $ref = (string) ($e['ref'] ?? '?');
            $date = $e['date'] ?? null;
            $title = trim((string) ($e['title'] ?? ''));
            $prov .= "- {$type} `{$ref}`" . ($date ? " ({$date})" : '') . ($title !== '' ? " — {$title}" : '') . "\n";
        }

        return $fm . "\n# BRIEFING — {$module} (verdade destilada)\n\n" . $body . $prov;
    }

    /**
     * Status pro frontmatter: preserva o `status:` da porta anterior se for um valor
     * válido do enum; senão devolve o default conservador 'em-construcao'. Casa só
     * `status:` (não `status_nota:`) no início de linha, tolerando aspas.
     */
    private function statusPreservado(?string $existing): string
    {
        if ($existing !== null
            && preg_match('/^status:[ \t]*["\']?([a-z][a-z-]*)/m', $existing, $m)
            && in_array($m[1], self::STATUS_ENUM, true)
        ) {
            return $m[1];
        }

        return 'em-construcao';
    }

    private function systemPrompt(string $module): string
    {
        return <<<PROMPT
        Você destila a VERDADE ATUAL de um módulo de software num BRIEFING de ≤1 página.

        OBJETIVO: ler os eventos recentes (sessions/handoffs/PRs/audits) + a porta atual
        (se houver) e produzir a verdade mastigada de HOJE do módulo "{$module}".

        ESTRUTURA obrigatória (markdown, seções `##`, nesta ordem):
          ## Estado atual    — 2-4 frases: o que o módulo faz e em que pé está
          ## Capacidades     — bullets curtos do que já funciona
          ## Gaps            — bullets do que falta (próxima onda)
          ## Última mudança  — 1-2 frases do que mudou nos eventos recentes

        REGRAS DURAS:
        - Português brasileiro. Conciso. No MÁXIMO ~1 página.
        - É VERDADE DESTILADA, não índice de links: NÃO cole links/paths no corpo
          (a proveniência é anexada automaticamente fora do seu texto).
        - NUNCA inclua PII: nada de CPF, CNPJ, e-mail, telefone, CEP. Repo é PÚBLICO.
        - Não invente: se um fato não está nos eventos nem na porta atual, omita.
        - Sobrescreve a verdade anterior — escreva o estado de AGORA, não um diário.
        PROMPT;
    }

    /**
     * Prompt do usuário: porta atual (contexto) + eventos selecionados.
     *
     * @param  array<int, array<string, mixed>>  $selected
     */
    private function userPrompt(string $module, array $selected, ?string $existing): string
    {
        $linhas = [];
        foreach ($selected as $e) {
            $type = (string) ($e['type'] ?? 'evento');
            $date = $e['date'] ?? 's/data';
            $title = trim((string) ($e['title'] ?? ($e['ref'] ?? '')));
            $linhas[] = "- [{$type} · {$date}] {$title}";
        }
        $eventos = implode("\n", $linhas);

        $atual = $existing !== null
            ? "PORTA ATUAL (refine/atualize, não copie cega):\n\n" . mb_substr($existing, 0, 4000) . "\n\n"
            : "PORTA ATUAL: (nenhuma — primeira destilação)\n\n";

        return "Módulo: {$module}\n\n{$atual}EVENTOS RECENTES a destilar:\n{$eventos}";
    }
}
