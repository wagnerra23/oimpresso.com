<?php

namespace Modules\ComunicacaoVisual\Http\Controllers;

use App\Services\AppLojas\AjustesDaEmpresa;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\ComunicacaoVisual\Entities\Material;
use Modules\ComunicacaoVisual\Services\OrcamentoCalculator;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;

/**
 * Orçamento rápido do app das lojas (tela 04 · P4) — REGRA MESTRE (valor).
 *
 * Contrato: memory/requisitos/AppMobile/api/tela-04-orcamento-rapido.md.
 *
 * As MESMAS funções da calculadora web (`/comunicacao-visual/api/*`): o cálculo é o
 * OrcamentoCalculator e a gravação é o OrcamentoGravador. Este controller só troca a sessão pelo
 * token e acrescenta o que o app exige: números como texto, Idempotency-Key e a DUPLA PROVA (o app
 * manda o total que mostrou; o ERP recalcula e recusa se divergir, sem gravar nada).
 *
 * Mora NO MÓDULO, não em app/Http/Controllers/Api/App: a seta de dependência vai módulo → núcleo
 * (tests/Feature/Architecture/DependencyDirectionTest). A rota fica em routes/api/app/orcamentos.php.
 *
 * Tier 0 (ADR 0093): business_id do usuário do token, explícito em toda consulta. Os global scopes
 * de Material/Orcamento leem a SESSÃO e, sem ela, não filtram nada; por isso o preço do material é
 * resolvido aqui, com business_id, e entra no Calculator como preço do item (o Calculator nunca faz
 * o lookup de Material nesta rota).
 */
class AppOrcamentoRapidoController extends Controller
{
    /** Medida em metros: texto com ponto, até 3 casas (coluna decimal 8,3). */
    private const MEDIDA = '/^\d{1,4}(\.\d{1,3})?$/';

    /** Quantidade: inteiro (coluna integer). */
    private const QUANTIDADE = '/^\d{1,6}$/';

    /** Dinheiro: texto com ponto, até 2 casas. Nunca passa por Util::num_uf (incidente 2026-06-05). */
    private const DINHEIRO = '/^\d{1,13}(\.\d{1,2})?$/';

    public function __construct(
        private ModuleUtil $moduleUtil,
        private OrcamentoCalculator $calculator,
        private OrcamentoGravador $gravador,
    ) {
    }

    /** GET /api/app/orcamentos/materiais — o catálogo da calculadora web, do business do token. */
    public function materiais(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVer($user)) {
            return $this->semPermissao();
        }
        $itens = $this->catalogo((int) $user->business_id);
        if ($itens->isEmpty()) {
            return $this->semConfiguracao();
        }

        return response()->json(['itens' => $itens->map(fn ($m) => [
            'id' => (int) $m->id,
            'nome' => $m->nome,
            'categoria' => $m->categoria,
            'unidade' => $m->unidade,
            'preco_m2' => (float) $m->preco_venda_m2,
        ])->values()]);
    }

    /** POST /api/app/orcamentos/calcular — prévia de UM item, autoritativa, sem gravar. */
    public function calcular(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVer($user)) {
            return $this->semPermissao();
        }
        $v = Validator::make($request->all(), $this->regrasItem(''), $this->mensagens());
        if ($v->fails()) {
            return $this->invalido($this->campos($v));
        }
        $bizId = (int) $user->business_id;
        $catalogo = $this->catalogo($bizId);
        if ($catalogo->isEmpty()) {
            return $this->semConfiguracao();
        }

        $d = $v->validated();
        $item = $this->itemParaCalculo($catalogo, $d);
        if (is_string($item)) {
            return $this->invalido(['material_id' => $item]);
        }
        $erro = $this->medidasInvalidas($d, '');
        if ($erro !== []) {
            return $this->invalido($erro);
        }

        $calc = $this->calculator->calcular($this->payload($user, null, [$item]))['itens'][0];

        return response()->json([
            'area_m2' => (float) $calc['area_m2'],
            'preco_m2' => (float) $calc['preco_unitario_m2'],
            'total' => (float) $calc['subtotal'],
        ]);
    }

    /**
     * POST /api/app/orcamentos — grava o orçamento como rascunho. REGRA MESTRE: o ERP recalcula cada
     * item e recusa (422, nada gravado) se `total_previsto` divergir. A reserva da Idempotency-Key vai
     * na MESMA transação da gravação, como na venda rápida (§2.2).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeCriar($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;
        if (AjustesDaEmpresa::valor($bizId, 'app_orcamento_rapido') !== true) {
            return response()->json([
                'erro' => 'sem_permissao',
                'mensagem' => 'O orçamento rápido pelo app não está ligado nesta empresa.',
            ], 403);
        }

        $chave = trim((string) $request->header('Idempotency-Key', ''));
        $regras = ['idempotency_key' => ['required', 'string', 'max:100'], 'cliente_id' => ['nullable', 'integer', 'min:1'],
            'itens' => ['required', 'array', 'min:1', 'max:50']] + $this->regrasItem('itens.*.')
            + ['itens.*.total_previsto' => ['required', 'string', 'regex:' . self::DINHEIRO]];
        $v = Validator::make(array_merge($request->all(), ['idempotency_key' => $chave]), $regras, $this->mensagens());
        if ($v->fails()) {
            return $this->invalido($this->campos($v));
        }
        $d = $v->validated();
        $clienteId = isset($d['cliente_id']) ? (int) $d['cliente_id'] : null;
        $hash = hash('sha256', (string) json_encode([$clienteId, array_map(
            fn ($i) => [(int) $i['material_id'], (string) $i['largura_m'], (string) $i['altura_m'], (string) $i['quantidade'], (string) $i['total_previsto']],
            array_values($d['itens'])
        )]));

        $ja = $this->idempotencia($bizId, (int) $user->id, $chave);
        if ($ja !== null) {
            return $this->repetida($ja, $hash);
        }

        $catalogo = $this->catalogo($bizId);
        if ($catalogo->isEmpty()) {
            return $this->semConfiguracao();
        }
        if ($clienteId !== null && ! DB::table('contacts')->where('business_id', $bizId)->where('id', $clienteId)
            ->whereIn('type', ['customer', 'both'])->whereNull('deleted_at')->exists()) {
            return $this->invalido(['cliente_id' => 'Cliente não encontrado.']);
        }

        $itens = [];
        $campos = [];
        foreach (array_values($d['itens']) as $n => $i) {
            $item = $this->itemParaCalculo($catalogo, $i);
            if (is_string($item)) {
                $campos["itens.{$n}.material_id"] = $item;
                continue;
            }
            $campos += $this->medidasInvalidas($i, "itens.{$n}.");
            $itens[] = $item;
        }
        if ($campos !== []) {
            return $this->invalido($campos);
        }

        $calculado = $this->calculator->calcular($this->payload($user, $clienteId, $itens));

        // DUPLA PROVA: o total que o app mostrou × o que o ERP calculou, item a item, em centavos.
        foreach (array_values($d['itens']) as $n => $i) {
            $previsto = self::centavos((string) $i['total_previsto']);
            $erp = (int) round(((float) $calculado['itens'][$n]['subtotal']) * 100);
            if ($previsto !== $erp) {
                $campos["itens.{$n}.total_previsto"] = 'O total mudou: o ERP calculou ' . number_format($erp / 100, 2, '.', '') . '. Confira e envie de novo.';
            }
        }
        if ($campos !== []) {
            return $this->invalido($campos);
        }

        DB::beginTransaction();
        try {
            try {
                $reservaId = DB::table('app_idempotencia')->insertGetId([
                    'business_id' => $bizId, 'user_id' => (int) $user->id, 'rota' => 'orcamentos', 'chave' => $chave,
                    'hash_corpo' => $hash, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                DB::rollBack();
                $ja = $this->idempotencia($bizId, (int) $user->id, $chave);

                return $ja !== null ? $this->repetida($ja, $hash) : $this->emAndamento();
            }

            $orc = $this->gravador->gravar($bizId, $calculado);
            $resposta = [
                'id' => (int) $orc->id,
                'numero' => $orc->numero,
                'status' => $orc->status,
                'total' => (float) $orc->total,
            ];
            DB::table('app_idempotencia')->where('id', $reservaId)->update([
                'resposta' => json_encode($resposta),
                'updated_at' => now(),
            ]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('API app orcamentos.store: ' . $e->getMessage(), ['business_id' => $bizId]);

            return response()->json(['erro' => 'falha', 'mensagem' => 'Não foi possível gravar o orçamento.'], 500);
        }

        return response()->json($resposta, 201);
    }

    /** "12.30" → 1230, sem float no caminho (o texto já passou na regex DINHEIRO). */
    public static function centavos(string $valor): int
    {
        [$inteiro, $fracao] = array_pad(explode('.', $valor, 2), 2, '');

        return ((int) $inteiro) * 100 + (int) str_pad(substr($fracao, 0, 2), 2, '0');
    }

    /** Materiais ativos do business, business_id explícito (o global scope não filtra sem sessão). */
    private function catalogo(int $bizId): Collection
    {
        return Material::withoutGlobalScope('business_id') // SUPERADMIN: scope lê a sessão; o token não tem — filtro explícito abaixo
            ->where('comvis_materiais.business_id', $bizId)
            ->where('comvis_materiais.ativo', true)
            ->orderBy('categoria')->orderBy('nome')
            ->get(['id', 'nome', 'categoria', 'unidade', 'preco_venda_m2']);
    }

    /**
     * Item no formato do Calculator, com o preço do catálogo DESTE business.
     *
     * @return array<string, mixed>|string  o item, ou a mensagem de erro do material
     */
    private function itemParaCalculo(Collection $catalogo, array $i): array|string
    {
        $m = $catalogo->firstWhere('id', (int) $i['material_id']);
        if ($m === null) {
            return 'Material não encontrado.';
        }
        if ((float) $m->preco_venda_m2 <= 0) {
            return 'Este material está sem preço por m² no ERP.';
        }

        return [
            'material_id' => (int) $m->id,
            'descricao' => $m->nome,
            'largura_m' => (float) $i['largura_m'],
            'altura_m' => (float) $i['altura_m'],
            'quantidade' => (int) $i['quantidade'],
            'preco_unitario_m2' => (float) $m->preco_venda_m2,
        ];
    }

    /** Medida ≤ 0 e quantidade < 1: a regex aceita "0.00" e "0". */
    private function medidasInvalidas(array $i, string $prefixo): array
    {
        $campos = [];
        foreach (['largura_m' => 'A largura', 'altura_m' => 'A altura'] as $campo => $rotulo) {
            if ((float) $i[$campo] <= 0) {
                $campos[$prefixo . $campo] = "{$rotulo} deve ser maior que zero.";
            }
        }
        if ((int) $i['quantidade'] < 1) {
            $campos[$prefixo . 'quantidade'] = 'A quantidade deve ser pelo menos 1.';
        }

        return $campos;
    }

    private function payload(User $user, ?int $clienteId, array $itens): array
    {
        return [
            'data_emissao' => now()->toDateString(),
            'data_validade' => null,
            'contato_id' => $clienteId,
            'vendedor_id' => (int) $user->id,
            'observacoes' => null,
            'itens' => $itens,
        ];
    }

    private function regrasItem(string $p): array
    {
        return [
            $p . 'material_id' => ['required', 'integer', 'min:1'],
            $p . 'largura_m' => ['required', 'string', 'regex:' . self::MEDIDA],
            $p . 'altura_m' => ['required', 'string', 'regex:' . self::MEDIDA],
            $p . 'quantidade' => ['required', 'string', 'regex:' . self::QUANTIDADE],
        ];
    }

    private function mensagens(): array
    {
        return [
            'idempotency_key.required' => 'Envie o header Idempotency-Key.',
            'itens.required' => 'Adicione ao menos um item.',
            'itens.min' => 'Adicione ao menos um item.',
            '*.material_id.required' => 'Escolha o material.',
            'material_id.required' => 'Escolha o material.',
            'regex' => 'Use número com ponto (ex.: 3.00).',
            'string' => 'Envie o número como texto (ex.: "3.00").',
        ];
    }

    private function campos(\Illuminate\Validation\Validator $v): array
    {
        return collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all();
    }

    /** Módulo de Comunicação Visual no pacote do business; superadmin: módulo instalado. */
    private function moduloHabilitado(User $user): bool
    {
        return $user->can('superadmin')
            ? (bool) $this->moduleUtil->isModuleInstalled('ComunicacaoVisual')
            : (bool) $this->moduleUtil->hasThePermissionInSubscription((int) $user->business_id, 'comunicacao_visual_module', 'superadmin_package');
    }

    private function podeVer(?User $user): bool
    {
        return $user !== null && $this->moduloHabilitado($user)
            && ($user->can('superadmin') || $user->can('comvis.orcamento.view') || $user->can('comvis.orcamento.create'));
    }

    private function podeCriar(?User $user): bool
    {
        return $user !== null && $this->moduloHabilitado($user)
            && ($user->can('superadmin') || $user->can('comvis.orcamento.create'));
    }

    private function idempotencia(int $bizId, int $userId, string $chave): ?object
    {
        return DB::table('app_idempotencia')->where('business_id', $bizId)->where('user_id', $userId)
            ->where('rota', 'orcamentos')->where('chave', $chave)->first();
    }

    private function repetida(object $ja, string $hash): JsonResponse
    {
        if (! hash_equals((string) $ja->hash_corpo, $hash)) {
            return response()->json([
                'erro' => 'idempotencia_conflito',
                'mensagem' => 'Esta chave já foi usada para outro orçamento. Gere uma chave nova.',
            ], 422);
        }
        if ($ja->resposta === null) {
            return $this->emAndamento();
        }

        return response()->json(json_decode((string) $ja->resposta, true), 200);
    }

    private function emAndamento(): JsonResponse
    {
        return response()->json(['erro' => 'em_andamento', 'mensagem' => 'Este orçamento ainda está sendo gravado. Tente de novo.'], 409);
    }

    /** @param array<string,string> $campos */
    private function invalido(array $campos): JsonResponse
    {
        return response()->json(['erro' => 'validacao', 'campos' => $campos], 422);
    }

    private function semPermissao(): JsonResponse
    {
        return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Você não tem permissão para orçamentos.'], 403);
    }

    private function semConfiguracao(): JsonResponse
    {
        return response()->json(['erro' => 'sem_configuracao', 'mensagem' => 'A empresa não tem material cadastrado para orçamento.'], 503);
    }
}
