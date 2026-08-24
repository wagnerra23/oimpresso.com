<?php

namespace Modules\Arquivos\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Arquivos\Http\Requests\ListArquivosRequest;

/**
 * ArquivosAdminController — a tela administrativa do acervo (US-ARQ-013).
 *
 * Liga a `ListArquivosRequest`, que já existia órfã desde a Sprint 1, à Page Inertia
 * `Arquivos/Index`. Nenhum endpoint novo foi inventado: o contrato de entrada (bucket,
 * owner_type, mime, from/to, per_page, q, with_trashed) é o que a Request já valida.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093]): o `business_id` vem da SESSÃO, nunca do
 * request. O `Arquivo` já carrega global scope por business — este controller não o quebra
 * e não usa `withoutGlobalScopes`.
 *
 * LEITURA PURA: nenhum caminho aqui escreve, apaga ou dispara job. Classificar, excluir e
 * restaurar entram na onda 2; retenção/purge dependem de decisão [W] (proposta de ADR
 * `arquivos-retencao-ui-aviso-titular`).
 *
 * @see resources/js/Pages/Arquivos/Index.charter.md  (lei)
 * @see resources/js/Pages/Arquivos/Index.casos.md    (contrato de teste)
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 */
class ArquivosAdminController extends Controller
{
    /** Acervo — lista administrativa por dono, com prazo e base legal. */
    public function index(ListArquivosRequest $request): Response
    {
        $filtros = [
            'bucket'       => $request->input('bucket'),
            'owner_type'   => $request->input('owner_type'),
            'mime'         => $request->input('mime'),
            'from'         => $request->input('from'),
            'to'           => $request->input('to'),
            'q'            => $request->input('q'),
            'with_trashed' => (bool) $request->boolean('with_trashed'),
            'per_page'     => (int) ($request->input('per_page') ?: 25),
        ];

        return Inertia::render('Arquivos/Index', [
            // Estado de UI — barato, vai eager (RUNBOOK-inertia-defer-pattern §Exceções).
            'filtros'  => $filtros,
            'politica' => $this->politica(),

            // Prop cara (paginate + eager load do arquivable): defer é o DEFAULT do projeto.
            'acervo' => Inertia::defer(fn () => $this->buildAcervoPayload($filtros)),
        ]);
    }

    /**
     * Política de retenção por contexto, com a BASE LEGAL ao lado do prazo.
     *
     * Vem de `Config/retention.php` — número solto não ensina o domínio, e o charter
     * declara isso como Goal ("prazo sempre acompanhado da lei").
     *
     * @return array<int, array{sub:string, dias:int, lei:string}>
     */
    private function politica(): array
    {
        $entities = (array) config('retention.entities', []);
        $leis = [
            'nfe-xml'            => 'Lei 8.846/94 Art. 23 + SINIEF 07/2005 Art. 8',
            'nfse-xml'           => 'idem NF-e',
            'documentos-fiscais' => 'CTN Art. 173 — prescrição tributária',
            'contratos'          => 'CDC Art. 27 (cíveis decenais por override)',
            'repair-foto'        => 'evidência de reparo pós-encerramento',
            'os-anexo'           => 'anexo de OS pós-encerramento',
            'ticket-anexo'       => 'pós-fechamento do ticket',
            'default'            => 'LGPD Art. 15-16 — eliminação tempestiva',
        ];

        $out = [];
        foreach ($entities as $sub => $dias) {
            $out[] = [
                'sub'  => (string) $sub,
                'dias' => (int) $dias,
                'lei'  => $leis[$sub] ?? '—',
            ];
        }

        return $out;
    }

    /**
     * Acervo paginado do PRÓPRIO business.
     *
     * `Arquivo` aplica global scope por `business_id` (ADR 0093) — não há `where` manual
     * aqui de propósito: duplicar o filtro esconderia uma eventual quebra do scope.
     */
    private function buildAcervoPayload(array $filtros): array
    {
        $q = Arquivo::query()->with('arquivable');

        if ($filtros['with_trashed']) {
            $q->withTrashed();
        }
        if ($filtros['bucket']) {
            $q->where('bucket', $filtros['bucket']);
        }
        if ($filtros['owner_type']) {
            $q->where('arquivable_type', $filtros['owner_type']);
        }
        if ($filtros['mime']) {
            $q->where('mime_type', $filtros['mime']);
        }
        if ($filtros['from']) {
            $q->whereDate('created_at', '>=', $filtros['from']);
        }
        if ($filtros['to']) {
            $q->whereDate('created_at', '<=', $filtros['to']);
        }
        if ($filtros['q']) {
            $termo = '%' . $filtros['q'] . '%';
            $q->where(fn ($w) => $w->where('original_name', 'like', $termo)
                ->orWhere('sub_destination', 'like', $termo));
        }

        $pagina = $q->orderByDesc('id')->paginate($filtros['per_page'])->withQueryString();

        $pagina->getCollection()->transform(fn (Arquivo $a) => $this->linha($a));

        return $pagina->toArray();
    }

    /**
     * Uma linha do acervo.
     *
     * `storage_path` e `md5` NÃO saem daqui: o charter proíbe PII/caminho em vista de
     * governança (LGPD Art. 37 — esses vivem só em `arquivos_audit_log`).
     */
    private function linha(Arquivo $a): array
    {
        $dias = $a->retention_days ?: (int) (config('retention.entities.' . $a->sub_destination)
            ?: config('retention.entities.default', 90));

        $vence = $a->created_at?->copy()->addDays($dias);

        return [
            'id'              => $a->id,
            'nome'            => $a->original_name,
            'sub_destination' => $a->sub_destination,
            'bucket'          => $a->bucket,
            'visibility'      => $a->visibility,
            'disk'            => $a->disk,
            'encrypted'       => (bool) $a->encrypted,
            'size_bytes'      => (int) $a->size_bytes,
            'classified_by'   => $a->classified_by,
            // Sem dono = ÓRFÃO. O charter trata como achado, não como item de lista.
            'orfao'           => $a->arquivable_type === null,
            'dono_tipo'       => $a->arquivable_type ? class_basename($a->arquivable_type) : null,
            'dono_id'         => $a->arquivable_id,
            'vence_em'        => $vence?->toDateString(),
            'dias_restantes'  => $vence ? (int) now()->startOfDay()->diffInDays($vence, false) : null,
            'excluido_em'     => $a->deleted_at?->toDateString(),
        ];
    }
}
