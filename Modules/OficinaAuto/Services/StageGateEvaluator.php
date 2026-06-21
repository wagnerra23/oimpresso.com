<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services;

use Modules\OficinaAuto\Entities\ServiceOrder;

/**
 * StageGateEvaluator — checklist de bloqueio por etapa (F3 OS-V2-5).
 *
 * Avalia, pra uma ServiceOrder + uma transição FSM (`action_key`), os requisitos que
 * precisam estar satisfeitos ANTES da OS poder avançar. É o ESPELHO servidor do
 * "Checklist de etapa" do drawer — a UI mostra, o backend ENFORÇA
 * (`ServiceOrderFsmActionController::execute` bloqueia 422 quando há requisito
 * bloqueante pendente, salvo override explícito de gerente/superadmin).
 *
 * Regras DATA-DRIVEN por transição (config `RULES` abaixo, keyed por process_key +
 * action_key) — NUNCA hardcoded no componente React. Hoje a config vive como constante
 * de módulo (revisável, versionada, server-side); migrar pra tabela/seeder fica trivial
 * quando houver sinal de customização per-business (ADR 0105 — sem feature sem sinal).
 *
 * Tipos de requisito:
 *   - `auto`   → calculado do estado real (DVI/fotos/itens/aprovação). BLOQUEIA.
 *   - `manual` → conferência humana (advisory). NÃO bloqueia o servidor (o operador
 *     confirma na UI via checkbox persistido local); fica como lembrete na checklist.
 *
 * Multi-tenant Tier 0 [ADR 0093]: opera sobre uma ServiceOrder já scopada por
 * business_id (Route Model Binding + global scope). Só lê relações.
 *
 * @see Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php (processo oficina_mecanica_os)
 * @see app/Http/Controllers/ServiceOrderFsmActionController.php
 * @see resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderStageGate.tsx
 */
class StageGateEvaluator
{
    /**
     * Config de requisitos por process_key → action_key → list de regras.
     *
     * Cobre o fluxo real de reparo `oficina_mecanica_os` (ADR 0194 · [W] 2026-06-02).
     * Transições sem regra cadastrada => gate satisfeito (não bloqueia).
     *
     * @var array<string, array<string, list<array{key:string, label:string, type:string, blocking:bool}>>>
     */
    private const RULES = [
        'oficina_mecanica_os' => [
            // Diagnóstico → Aguardando aprovação: precisa da vistoria + foto + orçamento.
            'enviar_orcamento' => [
                ['key' => 'dvi_min',   'label' => 'Vistoria DVI com ≥ 1 item',        'type' => 'auto', 'blocking' => true],
                ['key' => 'dvi_foto',  'label' => '≥ 1 foto na vistoria/laudo',        'type' => 'auto', 'blocking' => true],
                ['key' => 'orcamento', 'label' => 'Orçamento com ≥ 1 item (peça/MO)',  'type' => 'auto', 'blocking' => true],
            ],
            // Aguardando aprovação → (peças|execução): exige aprovação do cliente registrada.
            'aprovar_pedir_pecas' => [
                ['key' => 'aprovado', 'label' => 'Aprovação do cliente registrada', 'type' => 'auto', 'blocking' => true],
            ],
            'aprovar_executar' => [
                ['key' => 'aprovado', 'label' => 'Aprovação do cliente registrada', 'type' => 'auto', 'blocking' => true],
            ],
            // Execução → Pronto p/ retirar: conferência humana dos itens executados.
            // Sem flag de "item concluído" no schema (ADR 0105 — sem sinal) → manual/advisory.
            'concluir_servico' => [
                ['key' => 'itens_exec', 'label' => 'Itens do serviço executados (conferir)', 'type' => 'manual', 'blocking' => false],
                ['key' => 'tem_item',   'label' => 'Orçamento com ≥ 1 item lançado',        'type' => 'auto',   'blocking' => true],
            ],
        ],
    ];

    /**
     * Avalia o gate de uma transição.
     *
     * @return array{
     *   action_key:string,
     *   requirements:list<array{key:string,label:string,type:string,ok:bool,blocking:bool}>,
     *   blocking_unmet:int,
     *   total:int,
     *   done:int,
     *   satisfied:bool
     * }
     */
    public function evaluate(ServiceOrder $order, ?string $processKey, string $actionKey): array
    {
        $rules = self::RULES[$processKey ?? ''][$actionKey] ?? [];

        if (empty($rules)) {
            return [
                'action_key'     => $actionKey,
                'requirements'   => [],
                'blocking_unmet' => 0,
                'total'          => 0,
                'done'           => 0,
                'satisfied'      => true,
            ];
        }

        $ctx = $this->context($order);

        $requirements = [];
        $blockingUnmet = 0;
        $done = 0;

        foreach ($rules as $rule) {
            $ok = $this->checkRule($rule['key'], $ctx);
            if ($ok) {
                $done++;
            }
            if ($rule['blocking'] && ! $ok) {
                $blockingUnmet++;
            }
            $requirements[] = [
                'key'      => $rule['key'],
                'label'    => $rule['label'],
                'type'     => $rule['type'],
                'ok'       => $ok,
                'blocking' => $rule['blocking'],
            ];
        }

        return [
            'action_key'     => $actionKey,
            'requirements'   => $requirements,
            'blocking_unmet' => $blockingUnmet,
            'total'          => count($rules),
            'done'           => $done,
            'satisfied'      => $blockingUnmet === 0,
        ];
    }

    /**
     * Snapshot do estado real da OS pros requisitos `auto`.
     *
     * @return array{dvi_count:int, dvi_photos:int, items_count:int, approval_state:string}
     */
    private function context(ServiceOrder $order): array
    {
        // loadMissing é idempotente — não re-busca relações já carregadas pelo Controller.
        $order->loadMissing([
            'dviInspectionItems',
            'dviInspectionItems.arquivos',
            'items',
            'arquivos',
        ]);

        $dvi = $order->dviInspectionItems;

        // Fotos: por item DVI (HasArquivos) OU foto OS-level (laudo). Qualquer uma conta.
        $dviItemPhotos = (int) $dvi->sum(fn ($item) => $item->arquivos->count());
        $laudoPhotos = (int) $order->arquivos->count();

        return [
            'dvi_count'      => $dvi->count(),
            'dvi_photos'     => $dviItemPhotos + $laudoPhotos,
            'items_count'    => $order->items->count(),
            'approval_state' => $order->approval_state,
        ];
    }

    /**
     * @param  array{dvi_count:int, dvi_photos:int, items_count:int, approval_state:string}  $ctx
     */
    private function checkRule(string $key, array $ctx): bool
    {
        return match ($key) {
            'dvi_min'    => $ctx['dvi_count'] >= 1,
            'dvi_foto'   => $ctx['dvi_photos'] >= 1,
            'orcamento'  => $ctx['items_count'] >= 1,
            'tem_item'   => $ctx['items_count'] >= 1,
            'aprovado'   => $ctx['approval_state'] === 'approved',
            'itens_exec' => true, // manual/advisory — operador confere; não bloqueia servidor
            default      => true,
        };
    }
}
