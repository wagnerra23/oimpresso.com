<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Console\Commands;

use App\Contact;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\OficinaAuto\Entities\OaInspectionItem;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;

/**
 * Popula o Quadro de OS (Board) com dados de DEMONSTRAÇÃO realistas pra um business,
 * cobrindo TODAS as etapas do FSM `oficina_mecanica_os` — pra Wagner ver o board
 * "cheio" (paridade visual com o protótipo Cowork) sem depender de OS reais.
 *
 * Tudo é marcado com placa prefixo `DEMO-` + nota `[DEMO]`, então `--clean` apaga
 * 100% do que foi semeado (OS + itens + DVI + histórico FSM + veículos) sem tocar
 * em nada real. NÃO cria contatos nem usuários — REUSA os existentes do business
 * (evita poluir tabelas sensíveis). Veículos são criados (e removidos no --clean).
 *
 * Idempotente: rodar 2× não duplica (firstOrCreate por placa DEMO-).
 *
 * Cobertura semeada (por etapa do board):
 *   recepcao(2) · em_diagnostico(2) · aguardando_aprovacao(2) · aguardando_pecas(2)
 *   · em_execucao(2, com box+mecânico) · pronto_retirada(2) + 1 entregue (terminal,
 *   não aparece no board — prova que o --clean também limpa terminal).
 *
 * Cada OS recebe: km de entrada, valor (item de serviço), prazo (alguns vencidos →
 * KPI Urgentes), itens DVI (alguns decididos → barra de progresso) e 1 linha de
 * histórico FSM (→ linha "últ." do card). Box/mecânico nas etapas de execução.
 *
 * Multi-tenant Tier 0 ([ADR 0093]): `biz` argumento obrigatório (CLI sem session).
 * Toda escrita carrega business_id explícito; leituras de apoio filtram por biz.
 *
 * NOTA: `--detail` (NÃO `--verbose` — Symfony reserved, lição PR #851).
 *
 * Uso:
 *   php artisan oficina:board-demo 1            # semeia no business 1
 *   php artisan oficina:board-demo 1 --clean    # remove TUDO marcado DEMO- do biz 1
 *   php artisan oficina:board-demo 1 --detail   # log linha-a-linha
 *
 * @see resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx
 * @see Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php (oficina_mecanica_os)
 */
class OficinaBoardDemoCommand extends Command
{
    protected $signature = 'oficina:board-demo
                            {biz : business_id obrigatório (CLI sem session — ADR 0093)}
                            {--clean : Remove TODOS os dados DEMO- deste business (em vez de semear)}
                            {--detail : Log linha-a-linha}';

    protected $description = 'Popula (ou limpa com --clean) o Quadro de OS com dados de demonstração DEMO- num business.';

    private const PLATE_PREFIX = 'DEMO-';

    private const PROCESS_KEY = 'oficina_mecanica_os';

    public function handle(): int
    {
        $biz = (int) $this->argument('biz');
        if ($biz <= 0) {
            $this->error('business_id inválido.');

            return self::FAILURE;
        }

        // Habilita os global scopes baseados em session pros models do módulo.
        session(['user.business_id' => $biz, 'business.id' => $biz]);

        if ($this->option('clean')) {
            return $this->clean($biz);
        }

        return $this->seed($biz);
    }

    private function seed(int $biz): int
    {
        // Etapas reais do processo deste business (sem hardcode de id).
        $stages = SaleProcessStage::query()
            ->whereHas('process', function ($p) use ($biz) {
                $p->withoutGlobalScope(ScopeByBusiness::class)
                    ->where('business_id', $biz)
                    ->where('key', self::PROCESS_KEY);
            })
            ->get(['id', 'key'])
            ->keyBy('key');

        if ($stages->isEmpty()) {
            $this->error("Processo " . self::PROCESS_KEY . " não está semeado pro business {$biz}. Rode OficinaAutoFsmSeeder antes.");

            return self::FAILURE;
        }

        // Ação que LEVA a cada etapa (pra montar a linha de histórico "últ.").
        // target_stage_id → [action_id, from_stage_id]
        $actionByTarget = SaleStageAction::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->whereIn('target_stage_id', $stages->pluck('id'))
            ->whereIn('stage_id', $stages->pluck('id'))
            ->get(['id', 'stage_id', 'target_stage_id'])
            ->keyBy('target_stage_id');

        // Reusa contatos + usuários existentes do business (não cria — evita poluir).
        $contacts = Contact::query()->where('business_id', $biz)->limit(6)->pluck('id')->all();
        $mechanics = User::query()->where('business_id', $biz)->limit(4)->pluck('id')->all();

        $pickContact = fn (int $i): ?int => $contacts !== [] ? $contacts[$i % count($contacts)] : null;
        $pickMechanic = fn (int $i): ?int => $mechanics !== [] ? $mechanics[$i % count($mechanics)] : null;

        $now = Carbon::now();

        // Dataset de demonstração — 1 entrada por card. (stage, modelo, km, sintoma,
        // valor, prazo_em_horas[neg=vencido], box, mech?, dvi[severity:decision]).
        $specs = $this->demoSpecs();

        $created = 0;
        $skipped = 0;

        foreach ($specs as $i => $spec) {
            $plate = self::PLATE_PREFIX . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
            $stage = $stages[$spec['stage']] ?? null;
            if ($stage === null) {
                $this->warnDetail("Etapa {$spec['stage']} ausente — pulando {$plate}.");

                continue;
            }

            // Idempotência: veículo por placa DEMO-.
            $vehicle = Vehicle::query()->where('business_id', $biz)->where('plate', $plate)->first();
            if ($vehicle === null) {
                $vehicle = new Vehicle();
                $vehicle->business_id = $biz;
                $vehicle->plate = $plate;
                $vehicle->vehicle_type = $spec['modelo'];
                $vehicle->mileage_at_entry = $spec['km'];
                $vehicle->current_status = 'manutencao';
                $vehicle->contact_id = $pickContact($i);
                $vehicle->notes = '[DEMO] dado de demonstração do board';
                $vehicle->save();
            }

            // Idempotência: 1 OS DEMO por veículo.
            $exists = ServiceOrder::query()->where('business_id', $biz)->where('vehicle_id', $vehicle->id)->exists();
            if ($exists) {
                $skipped++;
                $this->warnDetail("OS DEMO já existe pra {$plate} — pulando.");

                continue;
            }

            $isExec = in_array($spec['stage'], ['em_execucao', 'pronto_retirada'], true);

            $order = new ServiceOrder();
            $order->business_id = $biz;
            $order->vehicle_id = $vehicle->id;
            $order->contact_id = $pickContact($i);
            $order->order_type = 'mecanica';
            $order->status = $spec['stage'] === 'entregue' ? 'concluida' : 'aberta';
            // current_stage_id direto no CREATE é permitido (o guard observer só
            // bloqueia UPDATE — TransactionFsmObserver::updating). Padrão dos testes.
            $order->current_stage_id = $stage->id;
            $order->entered_at = $now->copy()->subHours($spec['entrada_h']);
            $order->expected_completion = $now->copy()->addHours($spec['prazo_h']);
            $order->box_label = $isExec ? $spec['box'] : null;
            $order->assigned_user_id = $isExec ? $pickMechanic($i) : null;
            $order->notes = '[DEMO] ' . $spec['sintoma'];
            $order->save();

            // Valor (item de serviço) — alimenta o accessor total_items do card.
            ServiceOrderItem::create([
                'business_id'    => $biz,
                'service_order_id' => $order->id,
                'tipo'           => 'mao_obra', // enum válido (peca|mao_obra|servico_terceiro)
                'descricao'      => $spec['sintoma'],
                'quantidade'     => 1,
                'valor_unitario' => $spec['valor'],
                'valor_total'    => $spec['valor'],
                'notes'          => '[DEMO]',
            ]);

            // Itens DVI — alguns decididos (barra de progresso) + críticos.
            foreach ($spec['dvi'] as $k => $dvi) {
                OaInspectionItem::create([
                    'business_id'     => $biz,
                    'service_order_id' => $order->id,
                    'categoria'       => $dvi['cat'],
                    'descricao'       => $dvi['desc'],
                    'severity'        => $dvi['sev'],
                    'client_decision' => $dvi['dec'],
                    'sort_order'      => $k,
                ]);
            }

            // Histórico FSM (linha "últ.") — 1 transição pra etapa atual, se houver
            // ação que a alcança. Inserção direta (demo); cleanup remove por OS id.
            $action = $actionByTarget[$stage->id] ?? null;
            if ($action !== null) {
                DB::table('sale_stage_history')->insert([
                    'business_id'    => $biz,
                    'transaction_id' => $order->id,
                    'action_id'      => $action->id,
                    'from_stage_id'  => $action->stage_id,
                    'to_stage_id'    => $stage->id,
                    'user_id'        => $pickMechanic($i),
                    'executed_at'    => $now->copy()->subHours((int) round($spec['entrada_h'] / 2)),
                ]);
            }

            $created++;
            $this->warnDetail("OK {$plate} → {$spec['stage']} (R$ {$spec['valor']})");
        }

        $this->info("✅ Demo board biz={$biz}: {$created} OS criadas, {$skipped} já existiam.");
        $this->line("   Remover depois: php artisan oficina:board-demo {$biz} --clean");

        return self::SUCCESS;
    }

    private function clean(int $biz): int
    {
        $vehicleIds = Vehicle::query()
            ->where('business_id', $biz)
            ->where('plate', 'like', self::PLATE_PREFIX . '%')
            ->pluck('id');

        if ($vehicleIds->isEmpty()) {
            $this->info("Nada a limpar — nenhum veículo DEMO- no business {$biz}.");

            return self::SUCCESS;
        }

        $orderIds = ServiceOrder::query()
            ->where('business_id', $biz)
            ->whereIn('vehicle_id', $vehicleIds)
            ->pluck('id');

        $histDel = 0;
        if ($orderIds->isNotEmpty()) {
            $histDel = DB::table('sale_stage_history')
                ->where('business_id', $biz)
                ->whereIn('transaction_id', $orderIds)
                ->delete();

            OaInspectionItem::query()->whereIn('service_order_id', $orderIds)->forceDelete();
            ServiceOrderItem::query()->whereIn('service_order_id', $orderIds)->delete();
            ServiceOrder::query()->whereIn('id', $orderIds)->forceDelete();
        }

        Vehicle::query()->whereIn('id', $vehicleIds)->forceDelete();

        $this->info("🧹 Limpeza DEMO biz={$biz}: {$orderIds->count()} OS, {$vehicleIds->count()} veículos, {$histDel} históricos removidos.");

        return self::SUCCESS;
    }

    private function warnDetail(string $msg): void
    {
        if ($this->option('detail')) {
            $this->line('   · ' . $msg);
        }
    }

    /**
     * Conjunto de demonstração — realista pra oficina de mecânica pesada (Martinho).
     *
     * @return list<array{stage:string, modelo:string, km:int, sintoma:string, valor:float, entrada_h:int, prazo_h:int, box:string, dvi:list<array{cat:string, desc:string, sev:string, dec:string}>}>
     */
    private function demoSpecs(): array
    {
        return [
            // ── Recepção ──
            ['stage' => 'recepcao', 'modelo' => 'Honda Civic 2019', 'km' => 84220, 'sintoma' => 'Barulho nas rodas dianteiras em curva', 'valor' => 0, 'entrada_h' => 3, 'prazo_h' => 30, 'box' => '', 'dvi' => []],
            ['stage' => 'recepcao', 'modelo' => 'VW Saveiro 2021', 'km' => 62140, 'sintoma' => 'Revisão 60.000 km + troca de óleo', 'valor' => 0, 'entrada_h' => 1, 'prazo_h' => 48, 'box' => '', 'dvi' => []],

            // ── Em diagnóstico ──
            ['stage' => 'em_diagnostico', 'modelo' => 'Fiat Strada 2022', 'km' => 31580, 'sintoma' => 'Luz de injeção acendendo intermitente', 'valor' => 320, 'entrada_h' => 6, 'prazo_h' => 8, 'box' => '', 'dvi' => [
                ['cat' => 'eletrica', 'desc' => 'Scanner OBD-II — código P0301', 'sev' => 'atencao', 'dec' => 'pending'],
                ['cat' => 'motor', 'desc' => 'Velas de ignição', 'sev' => 'atencao', 'dec' => 'pending'],
            ]],
            ['stage' => 'em_diagnostico', 'modelo' => 'Ford Ka 2018', 'km' => 108900, 'sintoma' => 'Embreagem patinando em rampa', 'valor' => 280, 'entrada_h' => 5, 'prazo_h' => 6, 'box' => '', 'dvi' => [
                ['cat' => 'outro', 'desc' => 'Kit embreagem', 'sev' => 'critico', 'dec' => 'pending'],
            ]],

            // ── Aguardando aprovação ──
            ['stage' => 'aguardando_aprovacao', 'modelo' => 'Renault Sandero 2017', 'km' => 96300, 'sintoma' => 'Suspensão dianteira completa', 'valor' => 2250, 'entrada_h' => 26, 'prazo_h' => 5, 'box' => '', 'dvi' => [
                ['cat' => 'suspensao', 'desc' => 'Amortecedor dianteiro (par)', 'sev' => 'critico', 'dec' => 'approved'],
                ['cat' => 'suspensao', 'desc' => 'Bandeja LE/LD', 'sev' => 'atencao', 'dec' => 'pending'],
                ['cat' => 'direcao', 'desc' => 'Pivô', 'sev' => 'atencao', 'dec' => 'pending'],
            ]],
            ['stage' => 'aguardando_aprovacao', 'modelo' => 'Toyota Corolla 2020', 'km' => 54000, 'sintoma' => 'Troca de correia dentada + tensor', 'valor' => 980, 'entrada_h' => 20, 'prazo_h' => -4, 'box' => '', 'dvi' => [
                ['cat' => 'correia', 'desc' => 'Correia dentada trincada', 'sev' => 'critico', 'dec' => 'approved'],
            ]],

            // ── Aguardando peças ──
            ['stage' => 'aguardando_pecas', 'modelo' => 'Toyota Hilux 2020', 'km' => 97300, 'sintoma' => 'Troca de pastilhas + disco dianteiro', 'valor' => 1840, 'entrada_h' => 30, 'prazo_h' => 14, 'box' => '', 'dvi' => [
                ['cat' => 'freios', 'desc' => 'Disco freio dianteiro', 'sev' => 'critico', 'dec' => 'approved'],
                ['cat' => 'freios', 'desc' => 'Pastilha cerâmica', 'sev' => 'atencao', 'dec' => 'approved'],
            ]],
            ['stage' => 'aguardando_pecas', 'modelo' => 'GM S10 2019', 'km' => 120400, 'sintoma' => 'Bomba d\'água + correia', 'valor' => 760, 'entrada_h' => 40, 'prazo_h' => -8, 'box' => '', 'dvi' => [
                ['cat' => 'motor', 'desc' => 'Bomba d\'água', 'sev' => 'critico', 'dec' => 'approved'],
            ]],

            // ── Em execução (com box + mecânico) ──
            ['stage' => 'em_execucao', 'modelo' => 'Hyundai HB20 2019', 'km' => 76400, 'sintoma' => 'Substituição de correia dentada', 'valor' => 1020, 'entrada_h' => 28, 'prazo_h' => 19, 'box' => 'Elevador 1', 'dvi' => [
                ['cat' => 'correia', 'desc' => 'Polia da bomba d\'água', 'sev' => 'atencao', 'dec' => 'approved'],
                ['cat' => 'fluidos', 'desc' => 'Fluido de arrefecimento', 'sev' => 'ok', 'dec' => 'approved'],
            ]],
            ['stage' => 'em_execucao', 'modelo' => 'Jeep Renegade 2021', 'km' => 42110, 'sintoma' => 'Alinhamento + balanceamento + pneus', 'valor' => 1450, 'entrada_h' => 10, 'prazo_h' => 18, 'box' => 'Box 1', 'dvi' => [
                ['cat' => 'pneus', 'desc' => '4 pneus 215/55 R17', 'sev' => 'critico', 'dec' => 'approved'],
            ]],

            // ── Pronto p/ retirar ──
            ['stage' => 'pronto_retirada', 'modelo' => 'Nissan Versa 2020', 'km' => 58800, 'sintoma' => 'Revisão completa concluída', 'valor' => 780, 'entrada_h' => 50, 'prazo_h' => 24, 'box' => 'Box 2', 'dvi' => [
                ['cat' => 'motor', 'desc' => 'Óleo + filtros', 'sev' => 'ok', 'dec' => 'approved'],
            ]],
            ['stage' => 'pronto_retirada', 'modelo' => 'Fiat Mobi 2019', 'km' => 39900, 'sintoma' => 'Troca de bateria + revisão elétrica', 'valor' => 1020, 'entrada_h' => 55, 'prazo_h' => 24, 'box' => 'Elevador 2', 'dvi' => [
                ['cat' => 'bateria', 'desc' => 'Bateria 60Ah', 'sev' => 'ok', 'dec' => 'approved'],
            ]],

            // ── Entregue (terminal — NÃO aparece no board; prova o --clean) ──
            ['stage' => 'entregue', 'modelo' => 'VW Gol 2016', 'km' => 162800, 'sintoma' => 'Cabeçote retificado — entregue', 'valor' => 2400, 'entrada_h' => 120, 'prazo_h' => -40, 'box' => '', 'dvi' => []],
        ];
    }
}
