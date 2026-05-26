<?php

declare(strict_types=1);

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Models\NfeEmissao;

/**
 * US-FISCAL-015 — Busca global ⌘K palette cross-fiscal (PR #7 Wave).
 *
 * Endpoint: GET /fiscal/palette/search?q={query}
 *
 * Retorna 2 categorias (top 5 cada):
 *  - notas: NfeEmissao por (número parcial, chave_44 últimos 6, motivo, dest_name metadata)
 *  - dfe: NfeDfeRecebido por (chave_44 últimos 6, nome_emitente, cnpj_emitente)
 *
 * Frontend complementa com:
 *  - Navegação rápida (7 sub-páginas) — derivado client-side
 *  - Ações rápidas (Cancelar/CC-e/Manifestar/Retransmitir) — derivado client-side
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *  - HasBusinessScope automático nos 2 Models (NfeEmissao + NfeDfeRecebido)
 *  - Sem cross-tenant guard explícito necessário (global scope blinda)
 *
 * Performance:
 *  - LIKE %query% nas 4 colunas — sem index pra dest_name (metadata JSON)
 *  - Query mínima 3 chars (anti-DOS leading wildcard — GAP-FISCAL-002
 *    audit sênior 2026-05-25 — 2 chars permitia full scan em biz=4 com
 *    50k+ NFe)
 *  - LIMIT 5 cada categoria (10 results max)
 *  - Sem paginação — palette é "ação rápida", não navegação
 *
 * Permission: `fiscal.access` (gate único — leitura agregada).
 */
class PaletteSearchController extends Controller
{
    /**
     * GET /fiscal/palette/search?q={query}
     */
    public function search(Request $request): JsonResponse
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.access')) {
            abort(403, 'Sem permissão fiscal.access');
        }

        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:50'],
        ]);

        $query = trim($data['q']);

        return response()->json([
            'q'     => $query,
            'notas' => $this->searchNotas($query),
            'dfe'   => $this->searchDfe($query),
        ]);
    }

    /**
     * Busca em NfeEmissao por número (parcial), chave_44 (últimos 6), motivo.
     *
     * HasBusinessScope automático — ADR 0093 multi-tenant Tier 0.
     */
    private function searchNotas(string $query): array
    {
        $isNumeric = ctype_digit($query);

        return NfeEmissao::query()
            ->select(['id', 'numero', 'serie', 'modelo', 'chave_44', 'status', 'cstat', 'motivo', 'valor_total', 'emitido_em', 'transaction_id'])
            ->where(function ($q) use ($query, $isNumeric) {
                if ($isNumeric) {
                    $q->where('numero', 'LIKE', "%{$query}%");
                }
                $q->orWhere('chave_44', 'LIKE', "%{$query}%")
                    ->orWhere('motivo', 'LIKE', "%{$query}%");
            })
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function (NfeEmissao $e) {
                return [
                    'id'           => $e->id,
                    'tipo'         => $e->modelo === '65' ? 'NFC-e' : 'NF-e',
                    'numero'       => $e->numero,
                    'serie'        => $e->serie,
                    'chave_short'  => $e->chave_44 ? '…' . substr($e->chave_44, -6) : null,
                    'status'       => $e->status,
                    'cstat'        => $e->cstat,
                    'motivo'       => $e->motivo ? mb_substr($e->motivo, 0, 60) : null,
                    'valor'        => (float) $e->valor_total,
                    'emitido_em'   => $e->emitido_em?->format('d/m/Y'),
                    'url'          => '/fiscal/nfe?focus=' . $e->id,
                ];
            })
            ->toArray();
    }

    /**
     * Busca em NfeDfeRecebido por chave_44 + nome/CNPJ emitente.
     *
     * Schema canon: `nsu` (NSU SEFAZ — não tem `numero`), `status_manifestacao`
     * (não `status`). HasBusinessScope automático ADR 0093.
     */
    private function searchDfe(string $query): array
    {
        return NfeDfeRecebido::query()
            ->select(['id', 'chave_44', 'nsu', 'nome_emitente', 'cnpj_emitente', 'valor_total', 'status_manifestacao', 'data_emissao'])
            ->where(function ($q) use ($query) {
                $q->where('chave_44', 'LIKE', "%{$query}%")
                    ->orWhere('nome_emitente', 'LIKE', "%{$query}%")
                    ->orWhere('cnpj_emitente', 'LIKE', "%{$query}%");
            })
            ->orderByDesc('data_emissao')
            ->limit(5)
            ->get()
            ->map(function (NfeDfeRecebido $d) {
                return [
                    'id'           => $d->id,
                    'nsu'          => $d->nsu,
                    'chave_short'  => $d->chave_44 ? '…' . substr($d->chave_44, -6) : null,
                    'emitente'     => $d->nome_emitente,
                    'cnpj'         => $d->cnpj_emitente,
                    'valor'        => (float) $d->valor_total,
                    'status'       => $d->status_manifestacao,
                    'data_emissao' => $d->data_emissao?->format('d/m/Y'),
                    'url'          => '/fiscal/dfe?focus=' . $d->id,
                ];
            })
            ->toArray();
    }
}
