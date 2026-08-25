<?php

namespace Modules\Arquivos\Http\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Arquivos\Http\Requests\ListArquivosRequest;

/**
 * ArquivosAdminController — a tela administrativa do acervo (US-ARQ-013).
 *
 * Liga a `ListArquivosRequest`, que já existia órfã desde a Sprint 1, à Page Inertia
 * `Arquivos/Index`. Nenhum endpoint novo foi inventado: o contrato de entrada (bucket,
 * owner_type, mime, from/to, per_page, q, with_trashed) é o que a Request já valida —
 * a onda 1 · PR-2 acrescentou só `tab` e `acao`, pra escolher a vista e filtrar a trilha.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093]) — e as duas vistas se defendem de formas
 * DIFERENTES, o que é a única sutileza real deste arquivo:
 *
 *   - **acervo** lê pelo model `Arquivo`, que tem global scope por business. Aqui NÃO
 *     existe `where` manual de propósito: duplicá-lo esconderia uma quebra do scope.
 *   - **trilha** lê `arquivos_audit_log`, que **não tem model** — logo não tem scope
 *     nenhum. Ali o `where` explícito não é redundância: é a ÚNICA defesa Tier 0, e a
 *     ausência dele seria vazamento cross-tenant. O teste de contrato foi refinado pra
 *     cobrar exatamente isso de cada caminho (ver `ArquivosAdminControllerTest`).
 *
 * Nenhum dos dois usa `withoutGlobalScopes`.
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
    /**
     * A tela — duas vistas hoje: acervo (PR-1) e trilha (PR-2).
     *
     * A vista ativa vem de `?tab=`, o vocabulário de URL que o projeto já usa
     * (Financeiro, Fiscal/Dfe, Cliente). A barra de abas nasce AGORA, com a
     * segunda vista: aba que não leva a lugar nenhum é promessa, não navegação.
     */
    public function index(ListArquivosRequest $request): Response
    {
        $tab = $request->input('tab') === 'trilha' ? 'trilha' : 'acervo';

        $filtros = [
            'tab'          => $tab,
            'bucket'       => $request->input('bucket'),
            'owner_type'   => $request->input('owner_type'),
            'mime'         => $request->input('mime'),
            'from'         => $request->input('from'),
            'to'           => $request->input('to'),
            'q'            => $request->input('q'),
            'acao'         => $request->input('acao'),
            'with_trashed' => (bool) $request->boolean('with_trashed'),
            'per_page'     => (int) ($request->input('per_page') ?: 25),
        ];

        $props = [
            // Estado de UI — barato, vai eager (RUNBOOK-inertia-defer-pattern §Exceções).
            'filtros'  => $filtros,
            'politica' => $this->politica(),
        ];

        // Só a prop da vista ABERTA é registrada. `Inertia::defer` adia a execução,
        // mas o cliente busca TODAS as props deferidas no segundo request — registrar
        // as duas faria a tela pagar `paginate` do acervo pra quem está na trilha.
        // A vista fechada chega no front como `undefined`, e a Page não a renderiza.
        if ($tab === 'trilha') {
            $props['trilha'] = Inertia::defer(fn () => $this->buildTrilhaPayload($filtros));
        } else {
            // Prop cara (paginate + eager load do arquivable): defer é o DEFAULT do projeto.
            $props['acervo'] = Inertia::defer(fn () => $this->buildAcervoPayload($filtros));
        }

        return Inertia::render('Arquivos/Index', $props);
    }

    /**
     * Política de retenção por contexto, com a BASE LEGAL ao lado do prazo.
     *
     * O PRAZO vem de `config('arquivos.retention_days_policy')` — a chave operacional do
     * módulo (`Config/config.php`), que o próprio shim `Config/retention.php` aponta como
     * fonte da verdade. **Não** leia do shim: ele não é registrado.
     *
     * A LEI vive aqui porque no config ela só existe como comentário PHP (docblock de
     * `retention_days_policy`), formato que `config()` não alcança. As 8 chaves abaixo são
     * exatamente as 8 da policy — número solto não ensina o domínio, e o charter declara
     * "prazo sempre acompanhado da lei" como Goal.
     *
     * @return array<int, array{sub:string, dias:int, lei:string}>
     */
    private function politica(): array
    {
        $entities = (array) config('arquivos.retention_days_policy', []);
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
        $dias = $a->retention_days ?: (int) (config('arquivos.retention_days_policy.' . $a->sub_destination)
            ?: config('arquivos.retention_days_default', 90));

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

    // -------------------------------------------------------------------------
    // Trilha (onda 1 · PR-2) — `arquivos_audit_log`, READ-ONLY.
    //
    // A tabela é append-only e NUNCA purgada, nem quando o arquivo é (ADR 0123 §8).
    // A tela não oferece editar nem apagar linha: alterar auditoria é incidente,
    // não conserto — e é por isso que aqui não existe caminho de escrita nenhum.
    // -------------------------------------------------------------------------

    /**
     * O `business_id` da sessão — MESMA fonte que o global scope do `Arquivo` usa.
     *
     * Existe porque `arquivos_audit_log` não tem model: não há global scope pra
     * herdar, e a trilha precisa filtrar por conta própria (ADR 0093, Tier 0).
     *
     * **Fail-closed de propósito**, e é onde ele diverge do scope do model: lá, sem
     * sessão, o `if ($businessId !== null)` deixa a query passar SEM filtro (fail-open
     * atenuado pelo `authorize()` da Request, que exige business_id na sessão). Aqui
     * não se repete essa aposta — sem business_id, a trilha devolve vazio.
     */
    private function businessIdDaSessao(): ?int
    {
        $id = session('user.business_id') ?? session('business.id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Trilha paginada do PRÓPRIO business + as ações que ele realmente tem.
     *
     * As opções de filtro saem de um GROUP BY do próprio log — não de uma lista
     * escrita aqui. O vocabulário é do ENUM da coluna, que já mudou 2× por migration
     * (`signed_url_consumed` 2026-07-02, `exported` 2026-08-10); uma cópia em PHP
     * ficaria defasada calada na 3ª. O efeito colateral é bom: o chip só existe se
     * houver evento daquele tipo, então o filtro nunca oferece um beco sem saída.
     *
     * O `from`/`to` da `ListArquivosRequest` vale pros dois — lista e contadores —,
     * mas o filtro de AÇÃO não entra nos contadores: chip que só conta a si mesmo
     * deixa de ser faceta.
     *
     * @return array{eventos:array<string,mixed>, acoes:array<int, array{acao:string, total:int}>}
     */
    private function buildTrilhaPayload(array $filtros): array
    {
        $businessId = $this->businessIdDaSessao();

        // Sem tenant resolvido ou sem a tabela (módulo não instalado): vazio, nunca 500.
        if ($businessId === null || ! Schema::hasTable('arquivos_audit_log')) {
            return ['eventos' => $this->paginadorVazio((int) $filtros['per_page']), 'acoes' => []];
        }

        // Tier 0: o `where` por business_id É a defesa aqui — a tabela não tem model,
        // logo não tem global scope. Toda query desta seção nasce deste closure.
        $doBusiness = function () use ($businessId, $filtros) {
            $q = DB::table('arquivos_audit_log as aal')
                ->where('aal.business_id', $businessId);

            if ($filtros['from']) {
                $q->whereDate('aal.created_at', '>=', $filtros['from']);
            }
            if ($filtros['to']) {
                $q->whereDate('aal.created_at', '<=', $filtros['to']);
            }

            return $q;
        };

        // Facetas — o que este business tem, e quanto de cada.
        $acoes = $doBusiness()
            ->select('aal.action', DB::raw('COUNT(*) as total'))
            ->groupBy('aal.action')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['acao' => (string) $r->action, 'total' => (int) $r->total])
            ->all();

        $q = $doBusiness()
            ->select([
                'aal.id',
                'aal.created_at',
                'aal.action',
                'aal.arquivo_id',
                'aal.payload',
                // `users` do UltimatePOS NÃO tem coluna `name` — o nome se monta de
                // first_name+last_name, com fallback username e por fim o id cru.
                // Mesmo COALESCE do `arquivos:audit-log`, que descobriu isso em prod
                // (2026-05-10); divergir daqui faria a tela dizer outro nome que o CLI.
                DB::raw("COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username, CAST(aal.user_id AS CHAR)) as quem"),
            ])
            ->leftJoin('users as u', 'u.id', '=', 'aal.user_id');

        if ($filtros['acao']) {
            $q->where('aal.action', $filtros['acao']);
        }

        // Cronologia: a trilha é lida do mais recente pro mais antigo. O desempate
        // pelo id importa porque o `created_at` tem precisão de segundo — dois
        // eventos no mesmo segundo sairiam em ordem arbitrária sem ele.
        $pagina = $q->orderByDesc('aal.created_at')
            ->orderByDesc('aal.id')
            ->paginate((int) $filtros['per_page'])
            ->withQueryString();

        $pagina->setCollection(
            $pagina->getCollection()->map(fn ($r) => $this->linhaTrilha($r))
        );

        return ['eventos' => $pagina->toArray(), 'acoes' => $acoes];
    }

    /**
     * Uma linha da trilha.
     *
     * O arquivo aparece como `#id`, NUNCA pelo nome — é o que o protótipo desenha
     * (`arq: "#" + t.arq`) e o que mantém a vista alinhada ao Non-Goal do charter.
     * Quem precisa do nome tem o acervo ao lado, na outra aba.
     *
     * O payload SAI, resumido: ele é o conteúdo da auditoria (por que o link foi
     * emitido, de qual IP foi consumido, que política apagou o quê). É o mesmo que
     * o `arquivos:audit-log` já mostra a quem tem acesso — e esta tela é justamente
     * o controle de acesso que o docblock do `Arquivo` cita ("fica isolado no audit
     * log dedicado com controles de acesso"): a permission `arquivos.access` nasce
     * `false` e é de quem responde por conformidade.
     */
    private function linhaTrilha(object $r): array
    {
        return [
            'id'       => (int) $r->id,
            'quando'   => Carbon::parse($r->created_at)->format('Y-m-d H:i'),
            'acao'     => (string) $r->action,
            'arquivo'  => (int) $r->arquivo_id,
            'quem'     => $r->quem !== null && $r->quem !== '' ? (string) $r->quem : null,
            'detalhe'  => $this->resumoPayload($r->payload),
        ];
    }

    /**
     * Payload JSON → uma linha legível `chave=valor · chave=valor`.
     *
     * Truncar aqui, e não no front, limita o que trafega: payload é campo livre e
     * cada gravador escreve o seu (`{ip, agent}` no download, política e contagens
     * no retention, caminho de saída no export).
     */
    private function resumoPayload(?string $json): ?string
    {
        if ($json === null || $json === '') {
            return null;
        }

        $dados = json_decode($json, true);

        if (! is_array($dados)) {
            return mb_strimwidth($json, 0, 160, '…');
        }

        $partes = [];

        foreach ($dados as $chave => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }

            if (is_bool($valor)) {
                $texto = $valor ? 'sim' : 'não';
            } elseif (is_scalar($valor)) {
                $texto = (string) $valor;
            } else {
                $texto = (string) json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $partes[] = $chave . '=' . $texto;
        }

        return $partes === [] ? null : mb_strimwidth(implode(' · ', $partes), 0, 160, '…');
    }

    /**
     * Paginador vazio no MESMO formato do `paginate()->toArray()`.
     *
     * Construído pelo próprio `LengthAwarePaginator` em vez de um array escrito à
     * mão: o shape é do Laravel, e uma cópia manual divergiria dele em silêncio.
     *
     * @return array<string, mixed>
     */
    private function paginadorVazio(int $perPage): array
    {
        return (new LengthAwarePaginator([], 0, max(1, $perPage), 1, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]))->toArray();
    }
}
