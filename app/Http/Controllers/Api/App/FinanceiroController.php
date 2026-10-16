<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use App\Utils\ModuleUtil;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Financeiro do app das lojas (tela 06). Contrato: memory/requisitos/AppMobile/api/tela-06-financeiro.md (§10.1).
 * Só leitura — baixar e pagar ficam fora daqui (regra mestre).
 *
 * - resumo.a_receber / a_pagar: Financeiro\UnificadoService::kpis, o mesmo número do bloco
 *   financeiro do /api/app/inicio e do cockpit web /financeiro/unificado.
 * - resumo.vencido: parte vencida do a_receber (mesmos filtros, vencimento < hoje), já contida nele.
 * - resumo.recebido / pago: baixas do mês sem as de estorno, regra do FluxoRealizadoService.
 * - lista: caixas (`situacao=`) ou aba, com o filtro avançado do app (src/financeiro-filtros.ts). O filtro
 *   muda a lista e os contadores; resumo e contas nunca.
 *
 * Tier 0 (ADR 0093): o global scope dos models do Financeiro lê a sessão, que a API não tem — por
 * isso toda consulta aqui filtra business_id do token explicitamente, em cada tabela.
 */
class FinanceiroController extends Controller
{
    private const POR_PAGINA = 20;

    /** Caixas de seleção do app, na ordem dele. */
    private const SITUACOES = ['a_receber', 'a_pagar', 'recebidas', 'pagas'];

    /** Chave do ERP (Titulo::FORMAS_PAGAMENTO) → rótulo que o app mostra e devolve no filtro. */
    public const FORMAS = [
        'pix' => 'Pix', 'boleto' => 'Boleto', 'cartao_credito' => 'Cartão de crédito', 'cartao_debito' => 'Cartão de débito',
        'dinheiro' => 'Dinheiro', 'transferencia' => 'Transferência', 'cheque' => 'Cheque', 'compensacao' => 'Compensação',
        'outro' => 'Outro',
    ];

    /** O que a linha mostra em `valor`: o que falta (em aberto) ou o que foi baixado (liquidado). `b` = baixas sem estorno. */
    private const VALOR = "(CASE WHEN t.status = 'quitado' THEN b.pago ELSE t.valor_aberto END)";

    private const PAGO_EM = "(CASE WHEN t.status = 'quitado' THEN b.pago_em END)";

    /** Forma e conta: a da última baixa (realizada) manda; sem baixa, a prevista no título — a regra do cockpit web. */
    private const FORMA = '(COALESCE((SELECT x.meio_pagamento FROM fin_titulo_baixas x WHERE x.titulo_id = t.id'
        . ' AND x.business_id = t.business_id AND x.estorno_de_id IS NULL' . self::NAO_ESTORNADA_X
        . ' ORDER BY x.data_baixa DESC, x.id DESC LIMIT 1), t.forma_pagamento))';

    private const CONTA = '(COALESCE((SELECT x.conta_bancaria_id FROM fin_titulo_baixas x WHERE x.titulo_id = t.id'
        . ' AND x.business_id = t.business_id AND x.estorno_de_id IS NULL' . self::NAO_ESTORNADA_X
        . ' ORDER BY x.data_baixa DESC, x.id DESC LIMIT 1), t.conta_bancaria_id))';

    /**
     * Baixa ativa = não é linha de estorno E não foi estornada. O estorno do Financeiro é ledger-style (linha
     * negativa com estorno_de_id); ignorar só a linha negativa contaria de novo a baixa que ela desfez.
     */
    private const NAO_ESTORNADA_X = ' AND NOT EXISTS (SELECT 1 FROM fin_titulo_baixas xe WHERE xe.estorno_de_id = x.id)';

    /** Nº do contrato = id da assinatura da Cobrança Recorrente que gerou o título (origem recurring → rb_invoices). */
    private const CONTRATO = "(SELECT ri.subscription_id FROM rb_invoices ri WHERE ri.id = t.origem_id"
        . " AND ri.business_id = t.business_id AND t.origem = 'recurring')";

    private const VENDA_EM = '(SELECT DATE(v.transaction_date) FROM transactions v WHERE v.id = t.origem_id'
        . " AND v.business_id = t.business_id AND t.origem = 'venda')";

    private static ?bool $temRecorrencia = null;

    public function __construct(private ModuleUtil $moduleUtil)
    {
    }

    /** A regra da área `financeiro` do Início e do bloco financeiro dele (§6). */
    public function podeVerFinanceiro(User $user): bool
    {
        if (! class_exists(\Modules\Financeiro\Services\UnificadoService::class)) {
            return false;
        }
        if ($user->can('superadmin')) {
            return true;
        }

        return $this->moduleUtil->hasThePermissionInSubscription((int) $user->business_id, 'financeiro_module')
            && $user->can('financeiro.access');
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->podeVerFinanceiro($user)) {
            return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Seu usuário não tem acesso ao financeiro.'], 403);
        }

        $pagina = max(1, (int) $request->query('pagina', 1));
        $bizId = (int) $user->business_id;
        $hoje = CarbonImmutable::today();
        $ini = $hoje->startOfMonth()->toDateString();
        $fim = $hoje->endOfMonth()->toDateString();
        $f = $this->lerFiltros($request);
        // Liquidados: os do mês, como o extrato sempre foi; com período no filtro, o período manda.
        $janela = ($f['de'] === null && $f['ate'] === null) ? [$ini, $fim] : null;

        // Em aberto primeiro, pelo vencimento mais antigo; depois os liquidados, do pagamento mais recente.
        $linhas = $this->universo($bizId, $this->lerSituacoes($request), $f, $janela)
            ->orderByRaw("CASE WHEN t.status = 'quitado' THEN 1 ELSE 0 END")
            ->orderByRaw("CASE WHEN t.status = 'quitado' THEN NULL ELSE t.vencimento END")
            ->orderByRaw("CASE WHEN t.status = 'quitado' THEN b.pago_em END DESC")
            ->orderByRaw("CASE WHEN t.status = 'quitado' THEN 0 ELSE t.id END")
            ->orderByRaw("CASE WHEN t.status = 'quitado' THEN t.id ELSE 0 END DESC")
            ->forPage($pagina, self::POR_PAGINA + 1)->get();
        $temMais = $linhas->count() > self::POR_PAGINA;
        $hojeIso = $hoje->toDateString();

        $porSituacao = [];
        foreach (self::SITUACOES as $s) {
            $porSituacao[$s] = $this->universo($bizId, [$s], $f, $janela)->count();
        }

        return response()->json([
            'resumo' => $this->resumo($bizId, $hoje, $ini, $fim),
            'contas' => $this->contas($bizId),
            'itens' => $linhas->take(self::POR_PAGINA)->map(fn ($t) => $this->item($t, $hojeIso))->values(),
            'contadores' => [
                'receber' => $porSituacao['a_receber'],
                'pagar' => $porSituacao['a_pagar'],
                'extrato' => $porSituacao['recebidas'] + $porSituacao['pagas'],
            ],
            'contadores_situacao' => $porSituacao,
            'pagina' => $pagina,
            'tem_mais' => $temMais,
            'planos_conta' => $this->planosConta($bizId),
            'formas_pagamento' => array_values(self::FORMAS),
        ]);
    }

    /**
     * Caixas de seleção. `situacao=a_receber,pagas` vale sobre `aba`; valor desconhecido é ignorado
     * (`situacao=` vazio ou só com desconhecidos = nenhuma caixa = lista vazia). Sem `situacao`, a aba.
     *
     * @return list<string>
     */
    private function lerSituacoes(Request $request): array
    {
        if ($request->query->has('situacao')) {
            $pedidas = explode(',', (string) $request->query('situacao'));

            return array_values(array_filter(self::SITUACOES, fn ($s) => in_array($s, $pedidas, true)));
        }

        return match ((string) $request->query('aba', 'receber')) {
            'pagar' => ['a_pagar'],
            'extrato' => ['recebidas', 'pagas'],
            default => ['a_receber'],
        };
    }

    /**
     * Filtro avançado do app (src/financeiro-filtros.ts → paramsFiltros). Parâmetro malformado é ignorado,
     * como o app faz ao ler de volta (lerParams); forma de pagamento desconhecida não casa nada.
     *
     * @return array<string, mixed>
     */
    private function lerFiltros(Request $request): array
    {
        $txt = function (string $k) use ($request): ?string {
            $v = trim((string) $request->query($k, ''));

            return $v === '' ? null : $v;
        };
        $data = function (string $k) use ($txt): ?string {
            $v = $txt($k);

            return $v !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
        };
        $num = function (string $k) use ($txt): ?float {
            $v = $txt($k);

            return $v !== null && is_numeric($v) ? (float) $v : null;
        };
        $digitos = function (string $k) use ($txt): ?string {
            $v = (string) preg_replace('/\D/', '', (string) $txt($k));

            return $v === '' ? null : $v;
        };
        // Documento e contrato: o app compara sem pontuação ("88.213" acha "88213").
        $compacto = function (string $k) use ($txt): ?string {
            $v = (string) preg_replace('/[^\pL\pN]/u', '', (string) $txt($k));

            return $v === '' ? null : $v;
        };

        $campo = (string) $request->query('data_campo', 'vencimento');
        $forma = $txt('forma_pagamento');
        $formaChave = $forma === null ? null : self::chaveForma($forma);
        $conta = $txt('conta_id');
        $plano = $txt('plano_conta');
        $registro = (string) $request->query('registro', 'ativos');

        return [
            'campo' => in_array($campo, ['vencimento', 'pagamento', 'emissao', 'competencia', 'venda'], true) ? $campo : 'vencimento',
            'de' => $data('de'),
            'ate' => $data('ate'),
            'busca' => $txt('busca'),
            'forma' => $formaChave,
            'forma_invalida' => $forma !== null && $formaChave === null,
            'conta_id' => $conta !== null && ctype_digit($conta) ? (int) $conta : null,
            'nf' => $digitos('nf'),
            'nosso_numero' => $digitos('nosso_numero'),
            'valor_min' => $num('valor_min'),
            'valor_max' => $num('valor_max'),
            'plano' => $plano !== null && preg_match('/^[0-9A-Za-z]+(\.[0-9A-Za-z]+)*$/', $plano) ? $plano : null,
            'documento' => $txt('documento'),
            'documento_compacto' => $compacto('documento'),
            'registro' => in_array($registro, ['ativos', 'excluidos', 'todos'], true) ? $registro : 'ativos',
            'contrato' => $compacto('contrato'),
        ];
    }

    /** "Pix" (rótulo de `formas_pagamento`) ou "pix" (chave do ERP) → chave; desconhecida → null. */
    public static function chaveForma(string $forma): ?string
    {
        $n = mb_strtolower(trim($forma));
        foreach (self::FORMAS as $chave => $rotulo) {
            if ($n === $chave || $n === mb_strtolower($rotulo)) {
                return $chave;
            }
        }

        return null;
    }

    /**
     * Em aberto (aberto|parcial; valor = valor_aberto) e liquidados (quitados; valor = soma das baixas sem
     * estorno; pago_em = última delas) das caixas pedidas, com o filtro avançado. Cancelados ficam fora.
     *
     * @param  list<string>  $situacoes
     * @param  array<string, mixed>  $f
     * @param  array{0: string, 1: string}|null  $janela  datas aceitas da última baixa dos liquidados
     */
    private function universo(int $bizId, array $situacoes, array $f, ?array $janela): Builder
    {
        $abertos = array_values(array_filter([
            in_array('a_receber', $situacoes, true) ? 'receber' : null,
            in_array('a_pagar', $situacoes, true) ? 'pagar' : null,
        ]));
        $liquidados = array_values(array_filter([
            in_array('recebidas', $situacoes, true) ? 'receber' : null,
            in_array('pagas', $situacoes, true) ? 'pagar' : null,
        ]));

        $q = $this->comBaixas($this->base($bizId, (string) $f['registro']), $bizId);

        if ($abertos === [] && $liquidados === []) {
            return $q->whereRaw('1 = 0');
        }
        $q->where(function (Builder $w) use ($abertos, $liquidados, $janela) {
            if ($abertos !== []) {
                $w->orWhere(fn (Builder $a) => $a->whereIn('t.tipo', $abertos)->whereIn('t.status', ['aberto', 'parcial']));
            }
            if ($liquidados !== []) {
                $w->orWhere(function (Builder $l) use ($liquidados, $janela) {
                    $l->whereIn('t.tipo', $liquidados)->where('t.status', 'quitado')->whereNotNull('b.pago_em');
                    if ($janela !== null) {
                        $l->whereBetween('b.pago_em', $janela);
                    }
                });
            }
        });

        $this->aplicarFiltros($q, $bizId, $f);

        return $q;
    }

    /**
     * A mesma regra de `filtrarLancamentos` do app, no servidor (a lista é paginada: filtrar só a página
     * esconderia títulos das outras).
     *
     * @param  array<string, mixed>  $f
     */
    private function aplicarFiltros(Builder $q, int $bizId, array $f): void
    {
        if ($f['de'] !== null || $f['ate'] !== null) {
            $de = $f['de'];
            $ate = $f['ate'];
            if ($f['campo'] === 'competencia') {
                // Competência é mês (AAAA-MM): compara pelo mês.
                $col = 't.competencia_mes';
                $de = $de === null ? null : substr($de, 0, 7);
                $ate = $ate === null ? null : substr($ate, 0, 7);
            } else {
                $col = match ($f['campo']) {
                    'pagamento' => self::PAGO_EM,
                    'emissao' => 't.emissao',
                    'venda' => self::VENDA_EM,
                    default => 't.vencimento',
                };
            }
            // Sem a data escolhida (ex.: pagamento de um título em aberto), o título não entra no período.
            $q->whereRaw("{$col} IS NOT NULL");
            if ($de !== null) {
                $q->whereRaw("{$col} >= ?", [$de]);
            }
            if ($ate !== null) {
                $q->whereRaw("{$col} <= ?", [$ate]);
            }
        }

        if ($f['busca'] !== null) {
            $like = '%' . $this->escaparLike((string) $f['busca']) . '%';
            $q->where(fn (Builder $w) => $w->where('t.numero', 'like', $like)
                ->orWhere('t.metadata->descricao', 'like', $like)
                ->orWhere('t.cliente_descricao', 'like', $like)
                ->orWhere('c.name', 'like', $like));
        }

        if ($f['forma_invalida']) {
            $q->whereRaw('1 = 0');
        } elseif ($f['forma'] !== null) {
            $q->whereRaw(self::FORMA . ' = ?', [$f['forma']]);
        }

        if ($f['conta_id'] !== null) {
            $q->whereRaw(self::CONTA . ' = ?', [$f['conta_id']]);
        }

        if ($f['nf'] !== null) {
            $like = '%' . $f['nf'] . '%';
            $q->where(fn (Builder $w) => $w->where('t.metadata->nfe_numero', 'like', $like)
                ->orWhere('t.metadata->nfe_chave', 'like', $like));
        }

        if ($f['nosso_numero'] !== null) {
            $nosso = '%' . $f['nosso_numero'] . '%';
            $q->whereExists(fn (Builder $e) => $e->from('fin_boleto_remessas as r')
                ->whereColumn('r.titulo_id', 't.id')
                ->where('r.business_id', $bizId)
                ->whereNull('r.deleted_at')
                ->where('r.nosso_numero', 'like', $nosso));
        }

        if ($f['valor_min'] !== null) {
            $q->whereRaw(self::VALOR . ' >= ?', [$f['valor_min']]);
        }
        if ($f['valor_max'] !== null) {
            $q->whereRaw(self::VALOR . ' <= ?', [$f['valor_max']]);
        }

        if ($f['plano'] !== null) {
            // Um grupo ("2.1") pega as contas de baixo ("2.1.01", "2.1.02").
            $plano = (string) $f['plano'];
            $q->where(fn (Builder $w) => $w->where('p.codigo', $plano)->orWhere('p.codigo', 'like', $plano . '.%'));
        }

        if ($f['documento'] !== null) {
            // Como digitado e sem pontuação: "PED-77" acha "PED-77"; "88.213" acha "88213".
            $likes = array_unique(array_map(fn ($x) => '%' . $this->escaparLike($x) . '%',
                array_filter([(string) $f['documento'], (string) $f['documento_compacto']], fn ($x) => $x !== '')));
            $q->where(function (Builder $w) use ($likes) {
                foreach ($likes as $like) {
                    $w->orWhere('t.numero', 'like', $like)->orWhere('t.metadata->documento', 'like', $like);
                }
            });
        }

        if ($f['contrato'] !== null) {
            // Contrato da Cobrança Recorrente (id da assinatura) ou o nº digitado no lançamento (metadata.contrato).
            $like = '%' . $this->escaparLike((string) $f['contrato']) . '%';
            $q->where(function (Builder $w) use ($like) {
                $w->where('t.metadata->contrato', 'like', $like);
                if ($this->temRecorrencia()) {
                    $w->orWhereRaw('CAST(' . self::CONTRATO . ' AS CHAR) LIKE ?', [$like]);
                }
            });
        }
    }

    private function escaparLike(string $v): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $v);
    }

    private function temRecorrencia(): bool
    {
        return self::$temRecorrencia ??= Schema::hasTable('rb_invoices');
    }

    /** Junta as baixas ativas do título (`b`): `pago` = soma, `pago_em` = a última. Dá `valor` e `pago_em` da linha. */
    private function comBaixas(Builder $q, int $bizId): Builder
    {
        $baixas = DB::table('fin_titulo_baixas as bx')
            ->where('bx.business_id', $bizId)
            ->whereNull('bx.estorno_de_id')
            ->whereNotExists(fn ($e) => $e->from('fin_titulo_baixas as be')->whereColumn('be.estorno_de_id', 'bx.id'))
            ->groupBy('bx.titulo_id')
            ->select(['bx.titulo_id', DB::raw('MAX(bx.data_baixa) as pago_em'), DB::raw('SUM(bx.valor_baixa) as pago')]);

        return $q->leftJoinSub($baixas, 'b', 'b.titulo_id', '=', 't.id')
            ->addSelect([DB::raw(self::PAGO_EM . ' as pago_em'), DB::raw(self::VALOR . ' as valor')]);
    }

    /**
     * Um lançamento no formato da lista, excluído ou não (o detalhe do app parte dele). null se não é do business.
     *
     * @return array<string, mixed>|null
     */
    public function itemPorId(int $bizId, int $id): ?array
    {
        $t = $this->comBaixas($this->base($bizId, 'todos'), $bizId)->where('t.id', $id)->first();

        return $t === null ? null : $this->item($t, CarbonImmutable::today()->toDateString());
    }

    /** `registro`: ativos (padrão), excluidos (apagados no ERP, que seguem no histórico) ou todos. */
    private function base(int $bizId, string $registro): Builder
    {
        $q = DB::table('fin_titulos as t')
            ->leftJoin('contacts as c', function ($j) use ($bizId) {
                $j->on('c.id', '=', 't.cliente_id')->where('c.business_id', '=', $bizId);
            })
            ->leftJoin('fin_planos_conta as p', function ($j) use ($bizId) {
                $j->on('p.id', '=', 't.plano_conta_id')->where('p.business_id', '=', $bizId);
            })
            ->where('t.business_id', $bizId)
            ->select([
                't.id', 't.tipo', 't.status', 't.numero', 't.vencimento', 't.parcela_numero', 't.parcela_total',
                't.emissao', 't.competencia_mes', 't.metadata', 't.deleted_at',
                'p.codigo as plano_codigo', 'p.nome as plano_nome',
                DB::raw("COALESCE(NULLIF(t.cliente_descricao, ''), c.name) as parte"),
                DB::raw(self::FORMA . ' as forma_pagamento'),
                DB::raw(self::CONTA . ' as conta_id'),
                DB::raw('(SELECT r.nosso_numero FROM fin_boleto_remessas r WHERE r.titulo_id = t.id'
                    . ' AND r.business_id = t.business_id AND r.deleted_at IS NULL ORDER BY r.id DESC LIMIT 1) as nosso_numero'),
                DB::raw(self::VENDA_EM . ' as venda_em'),
                DB::raw(($this->temRecorrencia() ? self::CONTRATO : 'NULL') . ' as contrato'),
            ]);

        return match ($registro) {
            'excluidos' => $q->whereNotNull('t.deleted_at'),
            'todos' => $q,
            default => $q->whereNull('t.deleted_at'),
        };
    }

    private function item(object $t, string $hojeIso): array
    {
        $vencimento = $t->vencimento ? substr((string) $t->vencimento, 0, 10) : null;
        $status = $t->status === 'quitado'
            ? 'liquidado'
            : ($vencimento !== null && $vencimento < $hojeIso ? 'vencido' : 'aberto');
        $meta = is_string($t->metadata) ? (json_decode($t->metadata, true) ?: []) : [];
        $nf = $meta['nfe_numero'] ?? null;
        // Descrição digitada (metadata.descricao, a mesma do cockpit web); sem ela, "Título <número>".
        $digitada = trim((string) ($meta['descricao'] ?? ''));
        $descricao = $digitada !== '' ? $digitada : 'Título ' . $t->numero;
        if ($digitada === '' && (int) $t->parcela_total > 1) {
            $descricao .= ' · parcela ' . (int) $t->parcela_numero . '/' . (int) $t->parcela_total;
        }
        $documento = trim((string) ($meta['documento'] ?? ''));

        return [
            'id' => (int) $t->id,
            'tipo' => (string) $t->tipo,
            'descricao' => $descricao,
            'parte' => $t->parte !== null && $t->parte !== '' ? (string) $t->parte : null,
            'vencimento' => $vencimento,
            'pago_em' => $t->pago_em ? substr((string) $t->pago_em, 0, 10) : null,
            'valor' => round((float) $t->valor, 2),
            'status' => $status,
            // Campos do filtro avançado (o app mostra e filtra por eles).
            'emissao' => $t->emissao ? substr((string) $t->emissao, 0, 10) : null,
            'competencia' => $t->competencia_mes ? (string) $t->competencia_mes : null,
            'venda_em' => $t->venda_em ? substr((string) $t->venda_em, 0, 10) : null,
            'forma_pagamento' => $t->forma_pagamento ? (self::FORMAS[(string) $t->forma_pagamento] ?? null) : null,
            'conta_id' => $t->conta_id !== null ? (int) $t->conta_id : null,
            'nf_numero' => $nf !== null && $nf !== '' ? (string) $nf : null,
            'nosso_numero' => $t->nosso_numero ? (string) $t->nosso_numero : null,
            'plano_codigo' => $t->plano_codigo !== null ? (string) $t->plano_codigo : null,
            'plano_nome' => $t->plano_nome !== null ? (string) $t->plano_nome : null,
            'documento' => $documento !== '' ? $documento : (string) $t->numero,
            'excluido' => $t->deleted_at !== null,
            'contrato' => $t->contrato !== null ? (string) $t->contrato : ((string) ($meta['contrato'] ?? '') !== '' ? (string) $meta['contrato'] : null),
        ];
    }

    /**
     * Plano de contas da empresa para o filtro (grupos e contas ativos, na ordem do código).
     *
     * @return list<array{codigo: string, nome: string, tipo?: string}>
     */
    private function planosConta(int $bizId): array
    {
        if (! Schema::hasTable('fin_planos_conta')) {
            return [];
        }

        return DB::table('fin_planos_conta')
            ->where('business_id', $bizId)
            ->where('ativo', true)
            ->whereNull('deleted_at')
            ->get(['codigo', 'nome', 'tipo'])
            ->sort(fn ($a, $b) => strnatcmp((string) $a->codigo, (string) $b->codigo))
            ->map(function ($p) {
                $linha = ['codigo' => (string) $p->codigo, 'nome' => (string) $p->nome];
                $tipo = match ((string) $p->tipo) {
                    'receita' => 'receita',
                    'despesa', 'custo' => 'despesa',
                    default => null,
                };

                return $tipo === null ? $linha : $linha + ['tipo' => $tipo];
            })->values()->all();
    }

    /** @return array{mes: string, recebido: float, pago: float, saldo: float, a_receber: float, vencido: float, a_pagar: float} */
    private function resumo(int $bizId, CarbonImmutable $hoje, string $ini, string $fim): array
    {
        $k = app(\Modules\Financeiro\Services\UnificadoService::class)->kpis($bizId, $hoje);

        $vencido = $this->vencidoReceber($bizId, $hoje);

        $mov = DB::table('fin_titulo_baixas as b')
            ->join('fin_titulos as t', function ($j) use ($bizId) {
                $j->on('t.id', '=', 'b.titulo_id')->where('t.business_id', '=', $bizId);
            })
            ->where('b.business_id', $bizId)
            ->whereBetween('b.data_baixa', [$ini, $fim])
            ->whereNull('b.estorno_de_id')
            ->whereNotExists(fn ($e) => $e->from('fin_titulo_baixas as e')->whereColumn('e.estorno_de_id', 'b.id'))
            ->whereNull('t.deleted_at')
            ->groupBy('t.tipo')
            ->selectRaw('t.tipo as tipo, SUM(b.valor_baixa) as total')
            ->get()
            ->mapWithKeys(fn ($l) => [$l->tipo => $l->total]);

        $recebido = round((float) ($mov['receber'] ?? 0), 2);
        $pago = round((float) ($mov['pagar'] ?? 0), 2);

        return [
            'mes' => $hoje->format('Y-m'),
            'recebido' => $recebido,
            'pago' => $pago,
            'saldo' => round($recebido - $pago, 2),
            'a_receber' => round((float) $k['total_receber'], 2),
            'vencido' => $vencido,
            'a_pagar' => round((float) $k['total_pagar'], 2),
        ];
    }

    /**
     * Parte vencida do a receber (aberto/parcial, vencimento < hoje) — os filtros do total_receber
     * do UnificadoService::kpis, então já contida nele. Também é o `vencido` do Dashboard (§10.4).
     */
    public function vencidoReceber(int $bizId, ?CarbonImmutable $hoje = null): float
    {
        $hoje = $hoje ?? CarbonImmutable::today();

        return round((float) DB::table('fin_titulos')
            ->where('business_id', $bizId)
            ->where('tipo', 'receber')
            ->whereIn('status', ['aberto', 'parcial'])
            ->where('vencimento', '<', $hoje->toDateString())
            ->whereNull('deleted_at')
            ->sum('valor_aberto'), 2);
    }

    /** Contas bancárias do Financeiro; saldo `null` quando o ERP não tem o saldo em cache. */
    private function contas(int $bizId): array
    {
        return DB::table('fin_contas_bancarias as f')
            ->leftJoin('accounts as a', function ($j) use ($bizId) {
                $j->on('a.id', '=', 'f.account_id')->where('a.business_id', '=', $bizId);
            })
            ->where('f.business_id', $bizId)
            ->whereNull('f.deleted_at')
            ->orderBy('f.id')
            ->get(['f.id', 'f.banco_codigo', 'f.agencia', 'f.saldo_cached', 'a.name'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'nome' => (string) ($c->name ?? 'Conta ' . $c->id),
                'detalhe' => trim((new \Modules\Financeiro\Models\ContaBancaria(['banco_codigo' => (string) $c->banco_codigo]))->banco_nome . ($c->agencia ? ' · ag. ' . $c->agencia : '')),
                'saldo' => $c->saldo_cached === null ? null : round((float) $c->saldo_cached, 2),
            ])->all();
    }
}
