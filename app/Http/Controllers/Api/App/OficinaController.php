<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Contracts\Oficina\AcoesOs;
use App\Domain\Oficina\TiposVeiculo;
use App\Http\Controllers\Controller;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Oficina do app das lojas (oimpresso-app) — SÓ LEITURA. Contrato:
 * memory/requisitos/AppMobile/API-CONTRATO-v1.md §11 (Onda D).
 *
 * Mesmo universo da tela web /oficina-auto/ordens-servico (ServiceOrderController::board):
 * OS no processo FSM `oficina_mecanica_os` em etapa NÃO-terminal, ou OS de mecânica ainda sem
 * pipeline (contam na etapa inicial). Terminais (entregue, cancelado, garantia) não entram.
 * Valor = soma dos itens da OS (peças + mão de obra), como o card web.
 *
 * Tier 0 (ADR 0093): business_id do usuário do token em toda consulta, explícito — o global
 * scope do ServiceOrder lê a SESSÃO, que não existe numa chamada com token.
 */
class OficinaController extends Controller
{
    public const PROCESSO = 'oficina_mecanica_os';

    /** Etapas em que a OS espera alguém de fora (cliente aprovar, peça chegar). */
    public const TRAVADAS = ['aguardando_aprovacao', 'aguardando_pecas'];

    private const POR_PAGINA = 20;

    /** Teto do histórico do veículo (sem paginação no app): as mais recentes. */
    private const HISTORICO_MAX = 200;

    public function __construct(private ModuleUtil $moduleUtil)
    {
    }

    /** GET /api/app/os?etapa=<chave|todas>&pagina=N — tela 07. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVerOficina($user)) {
            return $this->semPermissao();
        }

        $bizId = (int) $user->business_id;
        $etapas = $this->etapas($bizId);
        $pagina = max((int) $request->query('pagina', 1), 1);
        $etapa = (string) $request->query('etapa', 'todas');
        if ($etapa !== 'todas' && ! $etapas->contains('key', $etapa)) {
            $etapa = 'todas';
        }

        if ($etapas->isEmpty()) {
            return response()->json([
                'itens' => [], 'etapas' => [], 'total' => 0, 'travadas' => 0,
                'pagina' => $pagina, 'tem_mais' => false,
                // Sem o processo da oficina a OS nova nasceria fora do quadro: não oferece criar.
                'pode_criar' => false,
            ]);
        }

        $inicial = $etapas->first();
        $porId = $etapas->keyBy('id');

        $linhas = $this->filtrarEtapa($this->base($bizId, $etapas), $etapa, $etapas)
            ->orderByRaw('COALESCE(sps.sort_order, ?) DESC', [(int) $inicial->sort_order])
            ->orderByDesc('so.id')
            ->offset(($pagina - 1) * self::POR_PAGINA)
            ->limit(self::POR_PAGINA + 1)
            ->get([
                'so.id', 'so.current_stage_id', 'v.plate', 'v.vehicle_type', 'c.name as cliente',
                DB::raw('(SELECT SUM(i.valor_total) FROM oficina_service_order_items i'
                    . ' WHERE i.service_order_id = so.id AND i.business_id = so.business_id'
                    . ' AND i.deleted_at IS NULL) as valor'),
            ]);

        // Contagem por etapa (OS sem pipeline contam na inicial, como no quadro web).
        $contagem = $this->base($bizId, $etapas)
            ->groupBy('so.current_stage_id')
            ->selectRaw('so.current_stage_id, COUNT(*) as n')
            ->pluck('n', 'current_stage_id');
        $totais = [];
        foreach ($contagem as $stageId => $n) {
            $chave = $stageId === '' || $stageId === null ? $inicial->key : ($porId[(int) $stageId]->key ?? null);
            if ($chave !== null) {
                $totais[$chave] = ($totais[$chave] ?? 0) + (int) $n;
            }
        }

        $total = array_sum($totais);
        $travadas = array_sum(array_intersect_key($totais, array_flip(self::TRAVADAS)));

        return response()->json([
            'itens' => $linhas->take(self::POR_PAGINA)->map(fn ($l) => $this->resumo($l, $etapas))->values(),
            'etapas' => $etapas->map(fn ($s) => [
                'chave' => $s->key,
                'rotulo' => $s->name,
                'total' => $totais[$s->key] ?? 0,
            ])->values(),
            'total' => $total,
            'travadas' => $travadas,
            'pagina' => $pagina,
            'tem_mais' => $linhas->count() > self::POR_PAGINA,
            'pode_criar' => $this->podeCriarOs($user),
        ]);
    }

    /**
     * POST /api/app/os — nova OS de mecânica (tela 07). Mesmo create da web
     * (ServiceOrderController@store): nasce `aberta`, entra no pipeline na Recepção, liga o
     * veículo livre; não gera item, valor, venda nem WhatsApp. 201 = o JSON do GET /os/{id}.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVerOficina($user) || ! $this->podeCriarOs($user)) {
            return $this->semPermissao();
        }

        $bizId = (int) $user->business_id;
        $v = Validator::make($request->all(), [
            'vehicle_id' => ['required', 'integer'],
            'contact_id' => ['nullable', 'integer'],
            'mileage_at_service' => ['nullable', 'integer', 'min:0'],
            'box_label' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'vehicle_id.required' => 'Escolha o veículo.',
            'mileage_at_service.min' => 'O km não pode ser negativo.',
        ]);
        $campos = $v->fails() ? collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all() : [];

        // Tier 0 (ADR 0093): veículo e cliente só do business do token (a validação da web
        // não escopa o veículo; aqui escopa os dois).
        if (! isset($campos['vehicle_id']) && ! DB::table('vehicles')->where('business_id', $bizId)
            ->whereNull('deleted_at')->where('id', (int) $request->input('vehicle_id'))->exists()) {
            $campos['vehicle_id'] = 'Veículo não encontrado.';
        }
        if (! isset($campos['contact_id']) && $request->filled('contact_id') && ! DB::table('contacts')
            ->where('business_id', $bizId)->where('id', (int) $request->input('contact_id'))->exists()) {
            $campos['contact_id'] = 'Cliente não encontrado.';
        }
        if ($campos !== []) {
            return response()->json(['erro' => 'validacao', 'campos' => $campos], 422);
        }

        $d = $v->validated();
        $texto = fn (?string $s) => is_string($s) && trim($s) !== '' ? trim($s) : null;
        $id = app(AcoesOs::class)->criar($user, $bizId, [
            'vehicle_id' => (int) $d['vehicle_id'],
            'contact_id' => isset($d['contact_id']) ? (int) $d['contact_id'] : null,
            'mileage_at_service' => isset($d['mileage_at_service']) ? (int) $d['mileage_at_service'] : null,
            'box_label' => $texto($d['box_label'] ?? null),
            'notes' => $texto($d['notes'] ?? null),
        ]);
        if ($id === null) {
            return response()->json(['erro' => 'sem_configuracao', 'mensagem' => 'A oficina não está disponível.'], 503);
        }

        return $this->show($request, $id)->setStatusCode(201);
    }

    /** Criar OS: permissão da web (`oficinaauto.service_order.create`) com o processo da oficina cadastrado. */
    private function podeCriarOs(?User $user): bool
    {
        return $user !== null
            && ($user->can('superadmin') || $user->can('oficinaauto.service_order.create'))
            && $this->etapas((int) $user->business_id)->isNotEmpty();
    }

    /**
     * GET /api/app/os/{id} — tela 03 (detalhe). Qualquer OS do business (inclusive terminal e
     * fora do pipeline, abertas pelo histórico do veículo). Outra empresa ou inexistente → 404.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVerOficina($user)) {
            return $this->semPermissao();
        }

        $bizId = (int) $user->business_id;
        $os = DB::table('service_orders as so')
            ->leftJoin('vehicles as v', function ($j) {
                $j->on('v.id', '=', 'so.vehicle_id')->on('v.business_id', '=', 'so.business_id');
            })
            ->leftJoin('contacts as c', function ($j) {
                $j->on('c.id', '=', 'so.contact_id')->on('c.business_id', '=', 'so.business_id');
            })
            ->where('so.business_id', $bizId)
            ->whereNull('so.deleted_at')
            ->where('so.id', $id)
            ->first([
                'so.id', 'so.order_type', 'so.current_stage_id', 'so.box_label', 'so.notes',
                'so.mileage_at_service', 'v.plate', 'v.vehicle_type', 'c.id as cliente_id', 'c.name as cliente',
            ]);

        if (! $os) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'OS não encontrada.'], 404);
        }

        $etapa = $this->etapaDetalhe($bizId, $os);

        $itens = DB::table('oficina_service_order_items')
            ->where('business_id', $bizId)
            ->where('service_order_id', $id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['tipo', 'descricao', 'quantidade', 'valor_unitario', 'valor_total']);

        $soma = fn (string $tipo) => round((float) $itens->where('tipo', $tipo)->sum('valor_total'), 2);

        $vistoria = DB::table('oa_inspection_items')
            ->where('business_id', $bizId)
            ->where('service_order_id', $id)
            ->whereNull('deleted_at')
            ->groupBy('severity')
            ->selectRaw('severity, COUNT(*) as n')
            ->pluck('n', 'severity');

        $fotos = DB::table('arquivos')
            ->where('business_id', $bizId)
            // valor gravado em arquivos.arquivable_type (morph sem morphMap = FQCN do model da OS)
            ->where('arquivable_type', 'Modules\OficinaAuto\Entities\ServiceOrder')
            ->where('arquivable_id', $id)
            ->whereNull('deleted_at')
            ->count();

        return response()->json([
            'id' => (int) $os->id,
            'numero' => 'OS-' . str_pad((string) $os->id, 5, '0', STR_PAD_LEFT),
            'local' => is_string($os->box_label) && trim($os->box_label) !== '' ? $os->box_label : null,
            'etapa' => $etapa,
            'travada' => $etapa !== null && in_array($etapa['chave'], self::TRAVADAS, true),
            'veiculo' => $os->plate === null ? null : [
                'placa' => $os->plate,
                'descricao' => $this->tipoVeiculo($os->vehicle_type),
                'km' => $os->mileage_at_service !== null ? (int) $os->mileage_at_service : null,
            ],
            'cliente' => $os->cliente_id === null ? null : ['id' => (int) $os->cliente_id, 'nome' => (string) $os->cliente],
            'observacoes' => is_string($os->notes) && trim($os->notes) !== '' ? $os->notes : null,
            'vistoria' => [
                'ok' => (int) ($vistoria['ok'] ?? 0),
                'atencao' => (int) ($vistoria['atencao'] ?? 0),
                'critico' => (int) ($vistoria['critico'] ?? 0),
            ],
            'itens' => $itens->map(fn ($i) => [
                'tipo' => $i->tipo,
                'descricao' => (string) $i->descricao,
                'quantidade' => (float) $i->quantidade,
                'valor_unitario' => round((float) $i->valor_unitario, 2),
                'valor' => round((float) $i->valor_total, 2),
            ])->values(),
            'totais' => [
                'pecas' => $soma('peca'),
                'mao_de_obra' => $soma('mao_obra'),
                'terceiros' => $soma('servico_terceiro'),
                'total' => round((float) $itens->sum('valor_total'), 2),
            ],
            'fotos_laudo' => $fotos,
            // Ações de avanço da etapa atual (só a linha principal; o resto fica na web).
            'acoes' => app(AcoesOs::class)->acoes($user, $bizId, (int) $os->id) ?? [],
        ]);
    }

    /**
     * POST /api/app/os/{id}/acoes/{chave} — avança ou encerra a OS pela ação (tela 03). Corpo
     * { motivo } (≤ 500, vai para a trilha): opcional, exceto nas ações de AcoesOs::ACOES_COM_MOTIVO
     * (acionar garantia). Sem override do gate. 200 = o mesmo JSON do GET /os/{id}, já na etapa nova.
     */
    public function executarAcao(Request $request, int $id, string $chave): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVerOficina($user)) {
            return $this->semPermissao();
        }

        $exigeMotivo = in_array($chave, AcoesOs::ACOES_COM_MOTIVO, true);
        $v = Validator::make($request->all(), ['motivo' => [$exigeMotivo ? 'required' : 'nullable', 'string', 'max:500']], [
            'motivo.required' => 'Informe o motivo da garantia.',
            'motivo.max' => 'O motivo tem no máximo 500 caracteres.',
            'motivo.string' => 'O motivo precisa ser texto.',
        ]);
        if ($v->fails()) {
            return response()->json([
                'erro' => 'validacao',
                'campos' => collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all(),
            ], 422);
        }
        $motivo = trim((string) ($v->validated()['motivo'] ?? ''));

        $r = app(AcoesOs::class)->executar($user, (int) $user->business_id, $id, $chave, $motivo !== '' ? $motivo : null);

        return match ($r['resultado']) {
            'ok' => $this->show($request, $id),
            'nao_encontrado' => response()->json(['erro' => 'nao_encontrado', 'mensagem' => $r['mensagem']], 404),
            'sem_permissao' => response()->json(['erro' => 'sem_permissao', 'mensagem' => $r['mensagem']], 403),
            'etapa_mudou' => response()->json(['erro' => 'etapa_mudou', 'mensagem' => $r['mensagem']], 409),
            'bloqueado' => response()->json(['erro' => 'bloqueado', 'mensagem' => $r['mensagem']], 422),
            default => response()->json(['erro' => 'nao_suportada', 'mensagem' => $r['mensagem']], 422),
        };
    }

    /**
     * Etapa da OS no processo da oficina. Não-terminal → índice na lista da 07; terminal →
     * `indice: null, terminal: true`; OS de mecânica sem pipeline → etapa inicial; fora do
     * processo da oficina (ex.: as importadas sem pipeline) → null.
     *
     * @return array<string, mixed>|null
     */
    private function etapaDetalhe(int $bizId, object $os): ?array
    {
        $etapas = $this->etapas($bizId);
        if ($etapas->isEmpty()) {
            return null;
        }
        if ($os->current_stage_id === null) {
            if ($os->order_type !== 'mecanica') {
                return null;
            }
            $s = $etapas->first();

            return ['chave' => $s->key, 'rotulo' => $s->name, 'indice' => 1, 'total_etapas' => $etapas->count(), 'terminal' => false];
        }

        $idx = $etapas->search(fn ($s) => (int) $s->id === (int) $os->current_stage_id);
        if ($idx !== false) {
            $s = $etapas[$idx];

            return ['chave' => $s->key, 'rotulo' => $s->name, 'indice' => $idx + 1, 'total_etapas' => $etapas->count(), 'terminal' => false];
        }

        $terminal = DB::table('sale_process_stages as s')
            ->join('sale_processes as p', 'p.id', '=', 's.process_id')
            ->where('p.business_id', $bizId)
            ->where('p.key', self::PROCESSO)
            ->where('s.id', (int) $os->current_stage_id)
            ->first(['s.key', 's.name']);

        return $terminal === null ? null : [
            'chave' => $terminal->key, 'rotulo' => $terminal->name, 'indice' => null,
            'total_etapas' => $etapas->count(), 'terminal' => true,
        ];
    }

    /**
     * GET /api/app/veiculos?q=&pagina=N — tela 08. Veículos de cliente do business (tabela
     * `vehicles`); permissão da tela web de veículos. Busca por placa (principal e reboque),
     * rótulo do tipo e nome do dono. `km` = maior km conhecido (cadastro ou OS do veículo).
     */
    public function veiculos(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVerVeiculos($user)) {
            return $this->semPermissao();
        }

        $bizId = (int) $user->business_id;
        $pagina = max((int) $request->query('pagina', 1), 1);
        $busca = trim((string) $request->query('q', ''));

        $q = $this->consultaVeiculos($bizId);

        if ($busca !== '') {
            $like = '%' . $busca . '%';
            $tipos = array_keys(array_filter(
                TiposVeiculo::ROTULOS,
                fn ($rotulo) => mb_stripos($rotulo, $busca) !== false
            ));
            $q->where(function ($w) use ($like, $tipos) {
                $w->where('v.plate', 'like', $like)
                    ->orWhere('v.secondary_plate', 'like', $like)
                    ->orWhere('c.name', 'like', $like);
                if ($tipos !== []) {
                    $w->orWhereIn('v.vehicle_type', $tipos);
                }
            });
        }

        $total = (clone $q)->count();
        $linhas = $q->orderBy('v.plate')
            ->orderBy('v.id')
            ->offset(($pagina - 1) * self::POR_PAGINA)
            ->limit(self::POR_PAGINA + 1)
            ->get($this->colunasVeiculo());

        return response()->json([
            'itens' => $linhas->take(self::POR_PAGINA)->map(fn ($v) => $this->itemVeiculo($v))->values(),
            'total' => $total,
            'pagina' => $pagina,
            'tem_mais' => $linhas->count() > self::POR_PAGINA,
            // "+ Veículo" no app: permissão de criar da web.
            'pode_criar' => $this->podeCriarVeiculo($user),
        ]);
    }

    /** GET /api/app/veiculos/opcoes — tipos de veículo do ERP, na ordem da web (novo veículo). */
    public function opcoesVeiculo(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVerVeiculos($user)) {
            return $this->semPermissao();
        }

        return response()->json([
            'tipos' => collect(TiposVeiculo::ROTULOS)->map(fn ($rotulo, $chave) => ['chave' => $chave, 'rotulo' => $rotulo])->values(),
        ]);
    }

    /**
     * POST /api/app/veiculos — novo veículo (pedido [W] 2026-10-05), como o store da web: só insere em
     * vehicles. Diferenças, para restringir: o dono tem de ser do business do token (a web valida só
     * `integer`), e placa já usada em outro veículo ativo do business é recusada (decisão [W]
     * 2026-10-05: "o erp deve recusar duas placa ativas"), com o id dele para o app abrir.
     * 201 = o item no formato da lista GET /api/app/veiculos.
     */
    public function storeVeiculo(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVerVeiculos($user) || ! $this->podeCriarVeiculo($user)) {
            return $this->semPermissao();
        }

        $bizId = (int) $user->business_id;
        $v = Validator::make($request->all(), [
            'placa' => ['required', 'string', 'max:10'],
            'tipo' => ['required', 'in:' . implode(',', array_keys(TiposVeiculo::ROTULOS))],
            'placa_secundaria' => ['nullable', 'string', 'max:10'],
            'ano_fabricacao' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'ano_modelo' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'cor' => ['nullable', 'string', 'max:30'],
            'km' => ['nullable', 'integer', 'min:0'],
            'chassi' => ['nullable', 'string', 'max:30'],
            'renavam' => ['nullable', 'string', 'max:11'],
            'contact_id' => ['nullable', 'integer'],
        ], [
            'placa.required' => 'A placa do veículo é obrigatória.',
            'tipo.required' => 'Selecione o tipo do veículo.',
            'tipo.in' => 'Tipo de veículo inválido.',
            'renavam.max' => 'RENAVAM aceita no máximo 11 caracteres (padrão DENATRAN).',
            'km.min' => 'O km não pode ser negativo.',
        ]);
        $campos = $v->fails() ? collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all() : [];

        // Como a consulta de placa da web: maiúsculas, só letras e números.
        $placa = self::placa((string) $request->input('placa', ''));
        $secundaria = self::placa((string) $request->input('placa_secundaria', ''));
        if (! isset($campos['placa']) && $placa === '') {
            $campos['placa'] = 'A placa do veículo é obrigatória.';
        }
        if (! isset($campos['placa_secundaria']) && $secundaria !== '' && $secundaria === $placa) {
            $campos['placa_secundaria'] = 'A placa do reboque não pode ser igual à principal.';
        }

        // Tier 0 (ADR 0093): dono só do business do token.
        if (! isset($campos['contact_id']) && $request->filled('contact_id') && ! DB::table('contacts')
            ->where('business_id', $bizId)->where('id', (int) $request->input('contact_id'))->exists()) {
            $campos['contact_id'] = 'Cliente não encontrado.';
        }

        $existente = null;
        foreach (['placa' => $placa, 'placa_secundaria' => $secundaria] as $campo => $valor) {
            if (isset($campos[$campo]) || $valor === '') {
                continue;
            }
            $id = $this->veiculoAtivoComPlaca($bizId, $valor);
            if ($id !== null) {
                $campos[$campo] = 'Esta placa já está em outro veículo ativo.';
                $existente ??= $id;
            }
        }

        if ($campos !== []) {
            return response()->json(array_filter([
                'erro' => 'validacao',
                'campos' => $campos,
                'veiculo_existente_id' => $existente,
            ], fn ($x) => $x !== null), 422);
        }

        $d = $v->validated();
        $id = app(AcoesOs::class)->criarVeiculo($user, $bizId, [
            'plate' => $placa,
            'vehicle_type' => (string) $d['tipo'],
            'secondary_plate' => $secundaria !== '' ? $secundaria : null,
            'manufacture_year' => isset($d['ano_fabricacao']) ? (int) $d['ano_fabricacao'] : null,
            'model_year' => isset($d['ano_modelo']) ? (int) $d['ano_modelo'] : null,
            'color' => $this->texto($d['cor'] ?? null),
            'mileage_at_entry' => isset($d['km']) ? (int) $d['km'] : null,
            'chassis' => $this->texto($d['chassi'] ?? null),
            'renavam' => $this->texto($d['renavam'] ?? null),
            'contact_id' => isset($d['contact_id']) ? (int) $d['contact_id'] : null,
        ]);
        if ($id === null) {
            return response()->json(['erro' => 'sem_configuracao', 'mensagem' => 'A oficina não está disponível.'], 503);
        }

        $linha = $this->consultaVeiculos($bizId)->where('v.id', $id)->first($this->colunasVeiculo());
        if ($linha === null) {
            return response()->json(['erro' => 'falha', 'mensagem' => 'Não foi possível ler o veículo criado.'], 500);
        }

        return response()->json($this->itemVeiculo($linha), 201);
    }

    /** Criar veículo: permissão da web (`oficinaauto.vehicle.create`). */
    private function podeCriarVeiculo(?User $user): bool
    {
        return $user !== null && ($user->can('superadmin') || $user->can('oficinaauto.vehicle.create'));
    }

    /** "rba-2h78 " → "RBA2H78" (mesma regra do VehicleLookupService::normalizePlate da web). */
    private static function placa(string $bruta): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $bruta));
    }

    /**
     * Id do veículo ATIVO (não excluído) do business que já usa a placa, como principal ou de reboque.
     * Compara normalizado dos dois lados: o legado gravou placa com hífen, espaço e minúscula.
     */
    private function veiculoAtivoComPlaca(int $bizId, string $placa): ?int
    {
        $norm = "UPPER(REPLACE(REPLACE(REPLACE(COALESCE(%s, ''), '-', ''), ' ', ''), '.', ''))";
        $id = DB::table('vehicles')
            ->where('business_id', $bizId)
            ->whereNull('deleted_at')
            ->where(fn ($w) => $w->whereRaw(sprintf($norm, 'plate') . ' = ?', [$placa])
                ->orWhereRaw(sprintf($norm, 'secondary_plate') . ' = ?', [$placa]))
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /** Veículos ativos do business com o dono (só contato do mesmo business). */
    private function consultaVeiculos(int $bizId): \Illuminate\Database\Query\Builder
    {
        return DB::table('vehicles as v')
            ->leftJoin('contacts as c', function ($j) {
                $j->on('c.id', '=', 'v.contact_id')->on('c.business_id', '=', 'v.business_id');
            })
            ->where('v.business_id', $bizId)
            ->whereNull('v.deleted_at');
    }

    /** @return list<mixed> */
    private function colunasVeiculo(): array
    {
        return [
            'v.id', 'v.plate', 'v.secondary_plate', 'v.vehicle_type', 'v.color',
            'v.manufacture_year', 'v.model_year', 'c.id as cliente_id', 'c.name as cliente',
            DB::raw('GREATEST(COALESCE(v.mileage_at_entry, 0), COALESCE((SELECT MAX(so.mileage_at_service)'
                . ' FROM service_orders so WHERE so.vehicle_id = v.id AND so.business_id = v.business_id'
                . ' AND so.deleted_at IS NULL), 0)) as km'),
        ];
    }

    /**
     * Item da lista de veículos (o mesmo formato no 201 do POST).
     *
     * @return array<string, mixed>
     */
    private function itemVeiculo(object $v): array
    {
        return [
            'id' => (int) $v->id,
            'placa' => (string) $v->plate,
            'placa_secundaria' => $this->texto($v->secondary_plate),
            'descricao' => $this->tipoVeiculo($v->vehicle_type),
            'ano' => $this->ano($v->manufacture_year, $v->model_year),
            'cliente' => $v->cliente,
            'cliente_id' => $v->cliente_id !== null ? (int) $v->cliente_id : null,
            'km' => (int) $v->km > 0 ? (int) $v->km : null,
            'cor' => $this->texto($v->color),
        ];
    }

    /**
     * GET /api/app/veiculos/{id}/os — histórico do veículo na tela 08. Todas as OS do veículo,
     * inclusive encerradas e fora do fluxo da oficina, da entrada mais nova para a mais antiga.
     * Mesma permissão da lista de veículos; veículo de outra empresa ou inexistente → 404.
     */
    public function veiculoOs(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVerVeiculos($user)) {
            return $this->semPermissao();
        }

        $bizId = (int) $user->business_id;
        $existe = DB::table('vehicles')
            ->where('business_id', $bizId)
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->exists();
        if (! $existe) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Veículo não encontrado.'], 404);
        }

        // Rótulo de toda etapa do processo da oficina (terminais incluídas); OS de mecânica ainda
        // sem pipeline fica na etapa inicial, como no quadro web; fora do processo → null.
        $rotulos = DB::table('sale_process_stages as s')
            ->join('sale_processes as p', 'p.id', '=', 's.process_id')
            ->where('p.business_id', $bizId)
            ->where('p.key', self::PROCESSO)
            ->pluck('s.name', 's.id');
        $inicial = $this->etapas($bizId)->first();

        $linhas = DB::table('service_orders as so')
            ->leftJoin('contacts as c', function ($j) {
                $j->on('c.id', '=', 'so.contact_id')->on('c.business_id', '=', 'so.business_id');
            })
            ->where('so.business_id', $bizId)
            ->where('so.vehicle_id', $id)
            ->whereNull('so.deleted_at')
            ->orderByRaw('COALESCE(so.entered_at, so.created_at) DESC')
            ->orderByDesc('so.id')
            ->limit(self::HISTORICO_MAX)
            ->get([
                'so.id', 'so.order_type', 'so.current_stage_id', 'c.name as cliente',
                DB::raw('COALESCE(so.entered_at, so.created_at) as data'),
                DB::raw('(SELECT SUM(i.valor_total) FROM oficina_service_order_items i'
                    . ' WHERE i.service_order_id = so.id AND i.business_id = so.business_id'
                    . ' AND i.deleted_at IS NULL) as valor'),
            ]);

        return response()->json([
            'itens' => $linhas->map(function ($o) use ($rotulos, $inicial) {
                if ($o->current_stage_id !== null) {
                    $etapa = $rotulos[(int) $o->current_stage_id] ?? null;
                } else {
                    $etapa = $o->order_type === 'mecanica' && $inicial !== null ? $inicial->name : null;
                }

                return [
                    'os_id' => (int) $o->id,
                    'numero' => 'OS-' . str_pad((string) $o->id, 5, '0', STR_PAD_LEFT),
                    'data' => $o->data !== null ? substr((string) $o->data, 0, 10) : null,
                    'etapa_rotulo' => $etapa,
                    'cliente' => $o->cliente,
                    'valor' => $o->valor === null ? null : round((float) $o->valor, 2),
                ];
            })->values(),
        ]);
    }

    /** Mesma regra da tela web de veículos: pacote da Oficina + `oficinaauto.vehicle.view`. */
    public function podeVerVeiculos(?User $user): bool
    {
        return $user !== null
            && $this->moduloHabilitado($user)
            && ($user->can('superadmin') || $user->can('oficinaauto.vehicle.view'));
    }

    private function texto(?string $v): ?string
    {
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }

    /** "2019/2020" (fabricação/modelo); um só ano quando só um existe ou os dois são iguais. */
    private function ano($fab, $mod): ?string
    {
        $f = $fab ? (int) $fab : null;
        $m = $mod ? (int) $mod : null;
        if ($f && $m) {
            return $f === $m ? (string) $f : $f . '/' . $m;
        }

        return $f ? (string) $f : ($m ? (string) $m : null);
    }

    /**
     * Mesma regra do menu web da Oficina (DataController::modifyAdminMenu): módulo no pacote do
     * business (superadmin: módulo instalado) + permissão de ver OS. Usado também pela área
     * `oficina` do /api/app/inicio.
     */
    public function podeVerOficina(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->moduloHabilitado($user)
            && ($user->can('superadmin') || $user->can('oficinaauto.service_order.view'));
    }

    /** Módulo da Oficina no pacote do business (Camada 1); superadmin: módulo instalado. */
    private function moduloHabilitado(User $user): bool
    {
        return $user->can('superadmin')
            ? (bool) $this->moduleUtil->isModuleInstalled('OficinaAuto')
            : (bool) $this->moduleUtil->hasThePermissionInSubscription(
                (int) $user->business_id,
                'oficina_auto_module',
                'superadmin_package'
            );
    }

    /** Etapas NÃO-terminais do processo da oficina no business, na ordem do ERP. */
    private function etapas(int $bizId): Collection
    {
        return DB::table('sale_process_stages as s')
            ->join('sale_processes as p', 'p.id', '=', 's.process_id')
            ->where('p.business_id', $bizId)
            ->where('p.key', self::PROCESSO)
            ->where('s.is_terminal', false)
            ->orderBy('s.sort_order')
            ->orderBy('s.id')
            ->get(['s.id', 's.key', 's.name', 's.sort_order']);
    }

    /** OS ativas do business (universo do quadro web). */
    private function base(int $bizId, Collection $etapas): \Illuminate\Database\Query\Builder
    {
        $ids = $etapas->pluck('id')->all();

        return DB::table('service_orders as so')
            ->leftJoin('sale_process_stages as sps', 'sps.id', '=', 'so.current_stage_id')
            ->leftJoin('vehicles as v', function ($j) {
                $j->on('v.id', '=', 'so.vehicle_id')->on('v.business_id', '=', 'so.business_id');
            })
            ->leftJoin('contacts as c', function ($j) {
                $j->on('c.id', '=', 'so.contact_id')->on('c.business_id', '=', 'so.business_id');
            })
            ->where('so.business_id', $bizId)
            ->whereNull('so.deleted_at')
            ->where(function ($w) use ($ids) {
                $w->whereIn('so.current_stage_id', $ids)
                    ->orWhere(fn ($w2) => $w2->where('so.order_type', 'mecanica')->whereNull('so.current_stage_id'));
            });
    }

    private function filtrarEtapa($q, string $etapa, Collection $etapas)
    {
        if ($etapa === 'todas') {
            return $q;
        }
        $s = $etapas->firstWhere('key', $etapa);
        $inicial = $etapas->first();

        return $q->where(function ($w) use ($s, $inicial) {
            $w->where('so.current_stage_id', $s->id);
            if ($s->id === $inicial->id) {
                $w->orWhereNull('so.current_stage_id');
            }
        });
    }

    /** @return array<string, mixed> */
    private function resumo(object $l, Collection $etapas): array
    {
        $idx = $l->current_stage_id === null
            ? 0
            : (int) $etapas->search(fn ($s) => (int) $s->id === (int) $l->current_stage_id);
        $s = $etapas[$idx];

        return [
            'id' => (int) $l->id,
            'numero' => 'OS-' . str_pad((string) $l->id, 5, '0', STR_PAD_LEFT),
            'placa' => $l->plate ?: null,
            'veiculo' => $this->tipoVeiculo($l->vehicle_type),
            'cliente' => $l->cliente,
            'valor' => $l->valor === null ? null : round((float) $l->valor, 2),
            'etapa' => [
                'chave' => $s->key,
                'rotulo' => $s->name,
                'indice' => $idx + 1,
                'total_etapas' => $etapas->count(),
            ],
            'travada' => in_array($s->key, self::TRAVADAS, true),
        ];
    }

    /** Rótulo do tipo de veículo como a web mostra; tipo fora da lista da web → null. */
    private function tipoVeiculo(?string $tipo): ?string
    {
        return $tipo === null ? null : (TiposVeiculo::ROTULOS[$tipo] ?? null);
    }

    private function semPermissao(): JsonResponse
    {
        return response()->json([
            'erro' => 'sem_permissao',
            'mensagem' => 'Seu usuário não tem acesso à oficina.',
        ], 403);
    }
}
