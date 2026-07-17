<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use RuntimeException;

/**
 * DADO do Quadro de OS pro gate visual (estado `default` do oficina-os).
 *
 * O BURACO QUE FECHA — "o `default` fotografa uma tela VAZIA" (medido 2026-07-16):
 *   O docblock do IsolatedStatesBaselineTest promete que `default` é "o estado SEEDADO de cada
 *   tela". Para o oficina-os isso NÃO era verdade: o VisregOficinaFsmSeeder (#4366) estabelece a
 *   pré-condição do PIPELINE (as 6 etapas), mas nenhum seeder semeia OS — então a baseline
 *   `default` mostrava o kanban com as 6 colunas e **0 OS, todos os KPIs zerados**.
 *
 *   Consequência (o defeito real, não o sintoma): com o quadro vazio, `default` ≡ `empty` a menos
 *   da flag `process_seeded`, e a ÚNICA coisa que pode se mover entre dois renders é ela — kanban
 *   vazio ⇄ "Quadro ainda não configurado". É exatamente a faixa (bbox y≈263..407 ≈ 10% dos
 *   pixels) que vinha oscilando e sendo lida como flakiness. Não era não-determinismo: é que não
 *   havia mais nada pra fotografar. Um gate que fotografa o vazio não pega regressão em card,
 *   contagem de KPI, placa, box, mecânico ou moeda — só guarda o chrome.
 *
 * O DADO É A SONDA (por que este seeder dispensa um probe separado):
 *   Com 6 cards no quadro, perder a pré-condição FSM deixa de ser invisível — o render cai no
 *   EmptyProcessState e o diff explode muito além do τ_alto (2%), então o gate GRITA em vez de
 *   trocar um vazio por outro vazio. A pré-condição passa a ser verificada no momento do RENDER
 *   (que é o que importa) e não só no momento do seed (que é onde a sonda
 *   `oficina_stages_biz1=` do workflow vive, e que por isso não conseguia flagrar isto).
 *
 * DETERMINISMO — a regra: SÓ datas PASSADAS ou NULL. NUNCA futuras.
 *   `Carbon::setTestNow()` do teste NÃO alcança o browser (subprocesso separado) — o render usa o
 *   relógio REAL. `is_overdue` é `$expected->isPast()` (ServiceOrderController). Logo:
 *     - data passada  → segue passada pra sempre  → estável;
 *     - NULL          → segue NULL                → estável;
 *     - data futura   → vira passada no dia X     → a baseline APODRECE sozinha.
 *   Por isso exatamente 1 OS tem `expected_completion` no passado (KPI "Urgentes" = 1, trava o
 *   estilo de atraso) e as outras 5 têm NULL. Nenhuma data futura entra aqui.
 *
 * SEM VALOR: as OS nascem sem itens → `total_items` = 0 → `valor` do card = 0 e "Valor em curso"
 *   segue R$ 0,00. É deliberado: manter este fixture LONGE da regra Tier 0 "CÁLCULO DE VALOR ou
 *   ESTOQUE" (memory/proibicoes.md). O shell do KPI e sua `sub` renderizam igual — que é o que o
 *   #4373 (par dark dos 6 tons) corrigiu. Semear item pra exercitar `valor` é follow-up com
 *   dupla-confirmação, não carona neste PR.
 *
 * SÓ biz=1 (ADR 0101 — nunca biz=4/cliente). O biz=98 (VisregEmptyTenantSeeder) fica INTOCADO:
 *   ele é o estado `empty` do oficina-os e deve seguir mostrando "Quadro ainda não configurado".
 *   Semear lá apagaria a diferença entre `default` e `empty` de novo, pelo outro lado.
 *
 * ANTI-COLISÃO: prefixos `VRGB`/`VISREG-BOARD` não cruzam com os do ConformanceProbesTest
 *   (`PRBG%` / `PROBE-G%`), cujo afterEach faz forceDelete por prefixo — ele não leva estas OS
 *   junto, e este seeder não ressuscita as dele.
 *
 * IDEMPOTENTE: firstOrCreate por placa/OS (browser tests não usam RefreshDatabase — tests/Pest.php,
 *   o dado precisa estar COMMITADO pro subprocesso enxergar). Re-rodar é no-op.
 *
 * ⚠️ MUDA BASELINE (ao contrário do VisregOficinaFsmSeeder, que era zero-delta por construção):
 *   `oficina-os · default`, `oficina-os · dark` e o PixelBaseline "Oficina OS" passam a mostrar 6
 *   cards. Exige regen no modo update (workflow_dispatch) + [W] aprovar o screenshot (R2/F1.5).
 *
 * @see database/seeders/VisregOficinaFsmSeeder.php (pré-condição do pipeline — roda ANTES deste)
 * @see tests/Browser/CoreScreens/IsolatedStatesBaselineTest.php (docblock: `default` = estado SEEDADO)
 * @see database/seeders/VisregFinanceiroFlowSeeder.php (exemplar: única tela cujo `default` tinha dado)
 * @see Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php (shapeBoardCard/buildBoardKpis)
 * @see memory/decisions/0265-oficina-reparo-erradica-locacao.md (order_type ∈ {manutencao, mecanica})
 */
class VisregOficinaBoardSeeder extends Seeder
{
    /** Business self canônico (ADR 0101). O estado `default`/`dark` do gate visual usa este. */
    public const BIZ_SELF = 1;

    /** Prefixo de placa — disjunto de `PRBG%` (ConformanceProbesTest). */
    private const PLATE_PREFIX = 'VRGB';

    /** Marca das OS deste fixture — disjunta de `PROBE-G%` (ConformanceProbesTest). */
    private const NOTES_MARK = 'VISREG-BOARD';

    /**
     * 1 OS por coluna do quadro — cobre as 6 colunas e as 6 contagens de KPI.
     * `overdue` só na primeira: KPI "Urgentes" = 1, determinístico (data no passado).
     *
     * SEM `cliente`: todas as OS apontam pro contato Walk-In que o VisregTenantSeeder já cria.
     * A 1ª versão deste seeder inseria 2 contatos próprios e isso VAZOU pra baseline de OUTRAS
     * telas (medido no dispatch 29535091137: `clientes · default` foi de "Todos 1" → "Todos 3",
     * + `clientes · dark`, PixelBaseline Clientes/Sells_Create e um fluxo do Financeiro). Pior:
     * um dos nomes ("Cliente de prova visual") colidia com o do VisregFinanceiroFlowSeeder.
     * Fixture de gate NÃO inventa dado que outra tela lista — reusa o que já existe.
     *
     * @var list<array{stage:string,plate:string,tipo:string,box:?string,km:int,overdue:bool}>
     */
    private const ORDENS = [
        // `tipo` ∈ enum vehicle_type da migration create_vehicles_table (caminhao · cavalo ·
        // semi_reboque · cacamba_estacionaria · automovel · motocicleta · outro). A mecânica
        // pesada do piloto (Martinho, biz=164) é caminhão/cavalo/semi-reboque — o fixture usa
        // esses. `cacamba_estacionaria` existe no enum como resíduo de schema do legado e NÃO
        // entra aqui: Oficina é reparo, não locação (ADR 0265 + RUNBOOK-erradicacao-locacao).
        ['stage' => 'recepcao',             'plate' => 'VRGB101', 'tipo' => 'caminhao',     'box' => null,         'km' => 184320, 'overdue' => true],
        ['stage' => 'em_diagnostico',       'plate' => 'VRGB202', 'tipo' => 'caminhao',     'box' => 'Elevador 1', 'km' => 96540,  'overdue' => false],
        ['stage' => 'aguardando_aprovacao', 'plate' => 'VRGB303', 'tipo' => 'cavalo',       'box' => 'Elevador 2', 'km' => 43210,  'overdue' => false],
        ['stage' => 'aguardando_pecas',     'plate' => 'VRGB404', 'tipo' => 'semi_reboque', 'box' => null,         'km' => 271800, 'overdue' => false],
        ['stage' => 'em_execucao',          'plate' => 'VRGB505', 'tipo' => 'caminhao',     'box' => 'Box 3',      'km' => 15075,  'overdue' => false],
        ['stage' => 'pronto_retirada',      'plate' => 'VRGB606', 'tipo' => 'cavalo',       'box' => 'Box 3',      'km' => 62190,  'overdue' => false],
    ];

    public function run(): void
    {
        // Tier 0: fixture de gate — jamais em produção (mesmo idioma dos irmãos Visreg*).
        if (app()->isProduction()) {
            throw new RuntimeException(static::class . ': seeder de fixture de gate visual NAO roda em producao (APP_ENV=production).');
        }

        if (! DB::table('business')->where('id', self::BIZ_SELF)->exists()) {
            $this->command?->warn(static::class . ': business ' . self::BIZ_SELF . ' ausente — nada a semear.');

            return;
        }

        // As etapas vêm do VisregOficinaFsmSeeder (roda antes). Sem elas o quadro nem renderiza,
        // então semear OS aqui seria dado órfão — falha barulhenta em vez de baseline silenciosa.
        $stageIdByKey = DB::table('sale_process_stages')
            ->join('sale_processes', 'sale_processes.id', '=', 'sale_process_stages.process_id')
            ->where('sale_processes.business_id', self::BIZ_SELF)
            ->where('sale_processes.key', 'oficina_mecanica_os')
            ->pluck('sale_process_stages.id', 'sale_process_stages.key');

        if ($stageIdByKey->isEmpty()) {
            throw new RuntimeException(
                static::class . ': processo FSM oficina_mecanica_os ausente no business ' . self::BIZ_SELF
                . ' — rode VisregOficinaFsmSeeder antes. Sem as etapas o Quadro cai no EmptyProcessState'
                . ' e a baseline `default` volta a fotografar tela vazia.'
            );
        }

        $adminId = DB::table('users')->where('business_id', self::BIZ_SELF)->orderBy('id')->value('id');

        // Reusa o contato que JÁ existe (Walk-In do VisregTenantSeeder) — este seeder não
        // insere contato pra não vazar pra baseline da tela de Clientes (ver §ORDENS).
        $contactId = DB::table('contacts')
            ->where('business_id', self::BIZ_SELF)
            ->orderBy('id')
            ->value('id');

        if ($contactId === null) {
            throw new RuntimeException(
                static::class . ': nenhum contato no business ' . self::BIZ_SELF
                . ' — rode VisregTenantSeeder antes (ele cria o Walk-In).'
            );
        }

        foreach (self::ORDENS as $spec) {
            $stageId = $stageIdByKey[$spec['stage']] ?? null;
            if ($stageId === null) {
                throw new RuntimeException(
                    static::class . ": etapa '{$spec['stage']}' ausente no pipeline oficina_mecanica_os"
                    . ' — o dicionário de etapas mudou? (ver OficinaAutoFsmSeeder)'
                );
            }

            // SUPERADMIN: fixture de gate visual — o global scope de business não vale no seeder.
            $vehicle = Vehicle::withoutGlobalScopes()->firstOrCreate(
                ['business_id' => self::BIZ_SELF, 'plate' => $spec['plate']],
                [
                    'contact_id'       => $contactId,
                    'vehicle_type'     => $spec['tipo'],
                    'mileage_at_entry' => $spec['km'],
                ],
            );

            // SUPERADMIN: idem. `current_stage_id` pode ser gravado no CREATE — o
            // GuardsFsmTransitions só engancha `updating`, e ServiceOrder nem usa o trait
            // (ele vive em Transaction + JobSheet). Ver memory/proibicoes.md §FSM.
            ServiceOrder::withoutGlobalScopes()->firstOrCreate(
                ['business_id' => self::BIZ_SELF, 'vehicle_id' => $vehicle->id],
                [
                    'contact_id'         => $contactId,
                    'order_type'         => 'mecanica', // ADR 0265 — reparo, nunca locação.
                    'current_stage_id'   => $stageId,
                    'status'             => 'aberta',
                    'box_label'          => $spec['box'],
                    'assigned_user_id'   => $spec['box'] !== null ? $adminId : null,
                    'mileage_at_service' => $spec['km'],
                    // Datas: passado ou NULL — jamais futuro (ver §DETERMINISMO no topo).
                    'entered_at'          => '2026-06-01 08:00:00',
                    'expected_completion' => $spec['overdue'] ? '2026-06-05 18:00:00' : null,
                    'notes'               => self::NOTES_MARK . ' — fixture do gate visual (' . $spec['stage'] . ')',
                ],
            );
        }
    }

}
