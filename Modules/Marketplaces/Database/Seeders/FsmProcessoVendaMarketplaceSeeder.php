<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Database\Seeders;

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageActionRole;
use App\Domain\Fsm\Support\PassosDoProcesso;
use App\Scopes\ScopeByBusiness;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Processo FSM "Venda de marketplace" (US-MKT-007 · ADR 0439 D2 opção A · ADR 0143).
 *
 * Paralelo ao `venda_com_producao`, para não mexer no pipeline do balcão. A venda do marketplace
 * só nasce quando o pedido está PAGO (decisão [W] 2026-10-26), então o estágio inicial já é
 * "pago, a enviar":
 *
 *   pedido_recebido (inicial) → enviado → entregue (T)
 *   lateral: cancelado (T) — a partir de pedido_recebido e enviado
 *
 * Ações críticas (🔒, RBAC obrigatório): marcar_enviado, confirmar_entrega, cancelar_pedido.
 * `cancelar_pedido` usa o CancelarVendaCascade do núcleo (estorno de estoque, NF-e, avisos).
 * A NF-e do pedido (US-MKT-009) entra em `marcar_enviado` atrás de flag, em PR próprio.
 *
 * Papéis por empresa (`roles.business_id` NOT NULL no UltimatePOS → sufixo `#{biz}`):
 * marketplaces.atendimento, marketplaces.gerente, marketplaces.system (o próprio sistema, quando a
 * notificação do ML confirma envio/entrega).
 *
 * Idempotente: roda quantas vezes for preciso, sem duplicar etapa, ação ou papel.
 */
class FsmProcessoVendaMarketplaceSeeder extends Seeder
{
    public const PROCESSO = 'venda_marketplace';

    public const INICIAL = 'pedido_recebido';

    /** @var array<string, array{0:string,1:int,2:string,3?:bool}> chave => [rótulo, ordem, cor, terminal] */
    private const ETAPAS = [
        'pedido_recebido' => ['Pago — a enviar', 0, 'blue'],
        'enviado' => ['Enviado', 1, 'violet'],
        'entregue' => ['Entregue', 2, 'green', true],
        'cancelado' => ['Cancelado', 3, 'red', true],
    ];

    private const PAPEIS = ['marketplaces.atendimento', 'marketplaces.gerente', 'marketplaces.system'];

    public function run(): void
    {
        foreach (DB::table('mkt_account_credentials')->distinct()->pluck('business_id') as $biz) {
            $this->runForBusiness((int) $biz);
        }
    }

    public function runForBusiness(int $businessId): SaleProcess
    {
        $papeis = $this->papeis($businessId);

        $processo = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->firstOrCreate(
            ['business_id' => $businessId, 'key' => self::PROCESSO],
            [
                'name' => 'Venda de marketplace',
                'description' => 'Pedido pago do marketplace (Mercado Livre): a enviar → enviado → entregue (ADR 0439)',
                'default_for_contact_type' => 'any',
                'active' => true,
            ],
        );

        $etapas = [];
        foreach (self::ETAPAS as $chave => $def) {
            $etapas[$chave] = SaleProcessStage::firstOrCreate(
                ['process_id' => $processo->id, 'key' => $chave],
                [
                    'name' => $def[0],
                    'sort_order' => $def[1],
                    'is_initial' => $chave === self::INICIAL,
                    'is_terminal' => $def[3] ?? false,
                    'color' => $def[2],
                ],
            );
        }

        $cascata = 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade';
        // [etapa de origem, chave, rótulo, destino, papéis, crítica, efeito]
        $acoes = [
            ['pedido_recebido', 'marcar_enviado', 'Marcar como enviado', 'enviado', ['marketplaces.atendimento', 'marketplaces.gerente', 'marketplaces.system'], true, null],
            ['enviado', 'confirmar_entrega', 'Confirmar entrega', 'entregue', ['marketplaces.atendimento', 'marketplaces.gerente', 'marketplaces.system'], true, null],
            ['pedido_recebido', 'cancelar_pedido', 'Cancelar pedido', 'cancelado', ['marketplaces.gerente', 'marketplaces.system'], true, $cascata],
            ['enviado', 'cancelar_pedido', 'Cancelar pedido', 'cancelado', ['marketplaces.gerente', 'marketplaces.system'], true, $cascata],
        ];

        foreach ($acoes as [$origem, $chave, $rotulo, $destino, $quem, $critica, $efeito]) {
            $acao = SaleStageAction::firstOrCreate(
                ['stage_id' => $etapas[$origem]->id, 'key' => $chave],
                [
                    'label' => $rotulo,
                    'target_stage_id' => $etapas[$destino]->id,
                    'side_effect_class' => $efeito,
                    'requires_confirmation' => $critica,
                    'is_critical' => $critica,
                ],
            );
            $existentes = $acao->roles->pluck('role_name')->all();
            foreach ($quem as $papel) {
                $nome = $papeis[$papel];
                if (! in_array($nome, $existentes, true)) {
                    SaleStageActionRole::create(['action_id' => $acao->id, 'role_name' => $nome]);
                }
            }
        }

        PassosDoProcesso::aplicar(self::PROCESSO, (int) $processo->id);

        return $processo;
    }

    /** @return array<string, string> papel canônico => nome resolvido (com `#{biz}` quando o schema pede) */
    private function papeis(int $businessId): array
    {
        $porEmpresa = Schema::hasColumn('roles', 'business_id');
        $resolvido = [];
        foreach (self::PAPEIS as $papel) {
            $nome = $porEmpresa ? "{$papel}#{$businessId}" : $papel;
            $attrs = ['name' => $nome, 'guard_name' => 'web'] + ($porEmpresa ? ['business_id' => $businessId] : []);
            Role::firstOrCreate($attrs);
            $resolvido[$papel] = $nome;
        }

        return $resolvido;
    }
}
