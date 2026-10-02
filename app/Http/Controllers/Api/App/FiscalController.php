<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use App\Utils\ModuleUtil;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fiscal do app das lojas (tela 14). Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §10.2.
 * Só leitura: NF-e e NFC-e (`nfe_emissoes`, modelos 55/65) + NFS-e (`nfse_emissoes`) numa lista só.
 *
 * Acesso = o da web: módulo Fiscal no plano (Camada 1) e, por tipo, a permissão da tela web dele —
 * `fiscal.nfe.view` (Fiscal/Nfe) para NF-e/NFC-e, `fiscal.nfse.view` (Fiscal/Nfse) para NFS-e.
 * Quem só vê um tipo recebe só aquele tipo (lista e contadores).
 *
 * Tier 0 (ADR 0093): os models do NfeBrasil/NFSe filtram pela sessão, que a API não tem — aqui toda
 * consulta filtra o business do token explicitamente, nas duas tabelas e nos joins.
 */
class FiscalController extends Controller
{
    private const POR_PAGINA = 20;

    private const STATUS = ['rascunho', 'processando', 'autorizado', 'cancelado', 'rejeitado'];

    /** Status do NfeBrasil → status do app. */
    private const MAPA_NFE = [
        'pendente' => 'processando', // criada, aguardando transmissão (a web conta como "processando")
        'enviando' => 'processando',
        'autorizada' => 'autorizado',
        'cancelada' => 'cancelado',
        'inutilizada' => 'cancelado', // número inutilizado: a nota não vale
        'rejeitada' => 'rejeitado',
        'denegada' => 'rejeitado', // a web junta com rejeitada
        'erro_envio' => 'rejeitado',
    ];

    /** Status do NFSe → status do app. */
    private const MAPA_NFSE = [
        'rascunho' => 'rascunho',
        'processando' => 'processando',
        'emitida' => 'autorizado',
        'cancelada' => 'cancelado',
        'erro' => 'rejeitado',
    ];

    public function __construct(private ModuleUtil $moduleUtil)
    {
    }

    /** @return array{nfe: bool, nfse: bool} o que o usuário pode ver de cada tipo. */
    public function tiposVisiveis(User $user): array
    {
        $super = $user->can('superadmin');
        $plano = $super || $this->moduleUtil->hasThePermissionInSubscription((int) $user->business_id, 'fiscal_module');

        return [
            'nfe' => $plano && ($super || $user->can('fiscal.nfe.view')) && Schema::hasTable('nfe_emissoes'),
            'nfse' => $plano && ($super || $user->can('fiscal.nfse.view')) && Schema::hasTable('nfse_emissoes'),
        ];
    }

    /** A regra da área `fiscal` do Início (§6). */
    public function podeVerFiscal(User $user): bool
    {
        return in_array(true, $this->tiposVisiveis($user), true);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tipos = $this->tiposVisiveis($user);
        if (! in_array(true, $tipos, true)) {
            return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Seu usuário não tem acesso ao fiscal.'], 403);
        }

        $status = (string) $request->query('status', 'todos');
        if (! in_array($status, self::STATUS, true)) {
            $status = 'todos';
        }
        $pagina = max(1, (int) $request->query('pagina', 1));
        $bizId = (int) $user->business_id;

        $todas = $this->todas($bizId, $tipos);
        $filtrado = DB::query()->fromSub($todas, 'n');
        if ($status !== 'todos') {
            $filtrado->where('n.status_app', $status);
        }
        $linhas = $filtrado->orderByDesc('n.quando')->orderByDesc('n.id')
            ->forPage($pagina, self::POR_PAGINA + 1)->get();

        $porStatus = DB::query()->fromSub($this->todas($bizId, $tipos), 'n')
            ->groupBy('n.status_app')
            ->selectRaw('n.status_app as s, COUNT(*) as total')
            ->get()
            ->pluck('total', 's');
        $contadores = ['todos' => (int) $porStatus->sum()];
        foreach (self::STATUS as $s) {
            $contadores[$s] = (int) ($porStatus[$s] ?? 0);
        }

        return response()->json([
            'itens' => $linhas->take(self::POR_PAGINA)->map(fn ($n) => $this->item($n))->values(),
            'contadores' => $contadores,
            'pagina' => $pagina,
            'tem_mais' => $linhas->count() > self::POR_PAGINA,
        ]);
    }

    /** UNION ALL das notas visíveis, já com o status do app e a data de ordenação. */
    private function todas(int $bizId, array $tipos): Builder
    {
        $partes = [];
        if ($tipos['nfe']) {
            $partes[] = DB::table('nfe_emissoes as e')
                ->leftJoin('transactions as t', function ($j) use ($bizId) {
                    $j->on('t.id', '=', 'e.transaction_id')->where('t.business_id', '=', $bizId);
                })
                ->leftJoin('contacts as c', function ($j) use ($bizId) {
                    $j->on('c.id', '=', 't.contact_id')->where('c.business_id', '=', $bizId);
                })
                ->where('e.business_id', $bizId)
                ->whereNull('e.deleted_at')
                ->whereIn('e.modelo', ['55', '65'])
                ->select([
                    'e.id',
                    DB::raw("CASE e.modelo WHEN '65' THEN 'NFCe' ELSE 'NFe' END as tipo"),
                    DB::raw('CAST(e.numero AS CHAR) as numero'),
                    't.invoice_no',
                    DB::raw("COALESCE(c.name, JSON_UNQUOTE(JSON_EXTRACT(e.metadata, '$.dest_name'))) as parte"),
                    'e.valor_total as valor',
                    DB::raw($this->caseStatus('e.status', self::MAPA_NFE) . ' as status_app'),
                    'e.chave_44 as chave',
                    'e.cstat',
                    'e.motivo as erro',
                    DB::raw('COALESCE(e.emitido_em, e.created_at) as quando'),
                ]);
        }
        if ($tipos['nfse']) {
            $partes[] = DB::table('nfse_emissoes as s')
                ->leftJoin('transactions as t', function ($j) use ($bizId) {
                    $j->on('t.id', '=', 's.transaction_id')->where('t.business_id', '=', $bizId);
                })
                ->where('s.business_id', $bizId)
                ->whereNull('s.deleted_at')
                ->select([
                    's.id',
                    DB::raw("'NFSe' as tipo"),
                    's.numero',
                    't.invoice_no',
                    's.tomador_nome as parte',
                    's.valor_servicos as valor',
                    DB::raw($this->caseStatus('s.status', self::MAPA_NFSE) . ' as status_app'),
                    's.provider_codigo_verificacao as chave',
                    DB::raw('NULL as cstat'),
                    's.erro_mensagem as erro',
                    's.created_at as quando',
                ]);
        }

        $q = array_shift($partes);
        foreach ($partes as $p) {
            $q->unionAll($p);
        }

        return $q;
    }

    /** @param array<string, string> $mapa */
    private function caseStatus(string $coluna, array $mapa): string
    {
        $sql = "CASE {$coluna}";
        foreach ($mapa as $de => $para) {
            $sql .= " WHEN '{$de}' THEN '{$para}'";
        }

        return $sql . " ELSE 'processando' END";
    }

    private function item(object $n): array
    {
        $status = (string) $n->status_app;
        $referencia = $n->invoice_no ? 'Pedido #' . $n->invoice_no : null;
        if ($n->parte) {
            $referencia = $referencia ? $referencia . ' · ' . $n->parte : (string) $n->parte;
        }
        $erro = null;
        if ($status === 'rejeitado' && $n->erro) {
            $erro = $n->cstat ? 'Rejeição ' . $n->cstat . ': ' . $n->erro : (string) $n->erro;
        }

        return [
            'id' => (int) $n->id,
            'tipo' => (string) $n->tipo,
            'numero' => $n->numero !== null && $n->numero !== '' ? (string) $n->numero : null,
            'referencia' => $referencia,
            'valor' => round((float) $n->valor, 2),
            'status' => $status,
            'chave' => $status === 'autorizado' && $n->chave ? (string) $n->chave : null,
            'erro' => $erro,
            'emitido_em' => $n->quando ? Carbon::parse((string) $n->quando)->toIso8601String() : null,
        ];
    }
}
