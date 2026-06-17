<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services;

use Modules\TeamMcp\Entities\CoworkHandoff;

/**
 * HandoffIngestService — PR-6 Loop de Handoff Zero-Paste (Fase 0 · ADR 0283).
 *
 * Núcleo COMPARTILHADO de validação + persistência de um handoff de design,
 * extraído de {@see \Modules\TeamMcp\Console\Commands\HandoffIngestCommand}
 * (PR-1) pra ser reusado pelo landing-pad HTTP
 * {@see \Modules\TeamMcp\Mcp\Tools\HandoffSubmitTool} (PR-6a). MESMA checagem HMAC,
 * MESMO `source_hash`, MESMO append-only — uma fonte só de verdade pra ingest,
 * venha de arquivo (command, server-side) ou de HTTP (tool, on-push).
 *
 * **Contrato do `body` assinado** (idêntico ao command): `sig = HMAC-SHA256(body, secret)`
 * com CRLF normalizado pra LF ANTES do HMAC — determinístico cross-OS
 * ({@see lição CRLF em writes}). `source_hash = sha256(body)` (mesma normalização).
 *
 * **Append-only** (A6 · {@see ADR 0130}/0003): revisão de um slug já 'applied' vira
 * NOVA version 'pending' + a anterior 'superseded'. Re-ingest idêntico (mesmo
 * `source_hash`) é no-op. NUNCA delete.
 *
 * Stateless e SEM efeito colateral além do insert/update em `cowork_handoffs`. NÃO
 * faz auto-merge (ADR 0283); NÃO toca o heartbeat — quem chama decide (o
 * {@see \Modules\TeamMcp\Mcp\Tools\HandoffSubmitTool} pulsa; o command não, igual
 * ao comportamento original do PR-1).
 *
 * @see Modules\TeamMcp\Console\Commands\HandoffIngestCommand
 * @see Modules\TeamMcp\Mcp\Tools\HandoffSubmitTool
 * @see memory/decisions/0283-handoff-loop-zero-paste.md
 */
final class HandoffIngestService
{
    /**
     * Valida a assinatura e aplica o handoff (append-only). Determinístico e
     * idempotente: mesmo `source_hash` → no-op.
     *
     * @param  array{
     *     slug?: string, body_md?: string, sig?: string, files?: array<mixed>,
     *     tela?: string, created_by?: string, audited_against?: string|null
     * }  $input  campos do handoff (já fora do frontmatter)
     * @param  string  $secret  HANDOFF_SECRET (HMAC) — o caller garante não-vazio
     * @param  bool  $dryRun  valida e calcula o plano, mas NÃO persiste
     * @return array{
     *     outcome: 'rejected'|'no_op'|'created'|'revised',
     *     reason: string|null, version: int, supersede: bool
     * }
     */
    public function ingest(array $input, string $secret, bool $dryRun = false): array
    {
        // Normaliza CRLF→LF: o HMAC e o source_hash têm que bater cross-OS.
        $body = str_replace("\r\n", "\n", (string) ($input['body_md'] ?? ''));

        // A1: assinatura obrigatória — rejeita unsigned/forjado (timing-safe).
        $sig = (string) ($input['sig'] ?? '');
        $expected = hash_hmac('sha256', $body, $secret);
        if ($sig === '' || ! hash_equals($expected, $sig)) {
            return ['outcome' => 'rejected', 'reason' => 'sig', 'version' => 0, 'supersede' => false];
        }

        $slug = (string) ($input['slug'] ?? '');
        if ($slug === '') {
            return ['outcome' => 'rejected', 'reason' => 'slug', 'version' => 0, 'supersede' => false];
        }

        $hash = hash('sha256', $body);
        $existing = CoworkHandoff::where('slug', $slug)->orderByDesc('version')->first();

        // Re-ingest idêntico = no-op (dedup por source_hash).
        if ($existing !== null && $existing->source_hash === $hash) {
            return ['outcome' => 'no_op', 'reason' => null, 'version' => (int) $existing->version, 'supersede' => false];
        }

        $version = $existing !== null ? ((int) $existing->version + 1) : 1;
        $isRevisao = $existing !== null;
        $supersede = $existing !== null && $existing->status === 'applied';

        if ($dryRun) {
            return ['outcome' => $isRevisao ? 'revised' : 'created', 'reason' => null, 'version' => $version, 'supersede' => $supersede];
        }

        // A6: revisão de algo já aplicado = lápide na anterior (append-only).
        if ($existing !== null && $existing->status === 'applied') {
            CoworkHandoff::where('id', $existing->id)->update(['status' => 'superseded']);
        }

        CoworkHandoff::create([
            'slug'            => $slug,
            'version'         => $version,
            'tela'            => (string) ($input['tela'] ?? ''),
            'status'          => 'pending',
            'audited_against' => $input['audited_against'] ?? null,
            'body_md'         => $body,
            'files_json'      => (array) ($input['files'] ?? []),
            'source_hash'     => $hash,
            'sig'             => $sig,
            'created_by'      => (string) ($input['created_by'] ?? 'CC'),
            'created_at'      => now(),
        ]);

        return ['outcome' => $isRevisao ? 'revised' : 'created', 'reason' => null, 'version' => $version, 'supersede' => $supersede];
    }
}
