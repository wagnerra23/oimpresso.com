<?php

declare(strict_types=1);

namespace Modules\KB\Services;

use Illuminate\Support\Collection;
use Modules\KB\Entities\KbNode;
use Modules\KB\Entities\KbSubcategory;

/**
 * KbAutoClassifierService — aplica as regras `auto_match` das subcategorias pra preencher
 * `category_id` + `subcategory_id` dos nós que estão sem categoria.
 *
 * =====================================================================================
 * POR QUE EXISTE
 * =====================================================================================
 * As regras de classificação JÁ existem como DADO (`kb_subcategories.auto_match`) e tinham
 * ZERO leitores em PHP (medido 2026-07-17): o front deriva client-side (mockData) e o bridge
 * (`KbBridgeFromMcpJob::bridgeDocument`) grava 9 campos e NENHUM é `category_id`. Resultado:
 * 1.412 de 1.415 nós com `category_id` NULL → o tri-pane filtra `n.category_id === cat.id`
 * (`Index.v2.tsx:147`) e renderiza a lateral VAZIA. Este serviço é o classificador que faltava.
 *
 * Não classifica "no chute": aplica EXATAMENTE a regra `auto_match` seedada. Nó cujo `type`
 * (ou `equip`/`tags`) não casa NENHUMA regra fica NULL — honesto (é dívida de taxonomia, não
 * invenção). Ex. medido em biz=1: `reference` (366) e `comparativo` (19) não têm subcategoria
 * que os receba → ficam sem casa até [W] decidir (charter §7 D-taxonomia).
 *
 * =====================================================================================
 * MULTI-TENANT Tier 0 (ADR 0093) — CUIDADO CRÍTICO
 * =====================================================================================
 * Rodado por comando artisan / job, onde `session()` é VAZIA → o global scope de business_id
 * do KbNode NÃO resolve o tenant. Por isso este serviço recebe `$businessId` EXPLÍCITO e filtra
 * com `->where('business_id', $businessId)` + `withoutGlobalScopes()` — nunca depende da sessão.
 * Uma regra de business A JAMAIS casa um nó de business B: subcategoria e nó são filtrados pelo
 * MESMO `$businessId`. Test: cross-tenant (biz=99) intacto.
 *
 * @see Modules/KB/Console/Commands/KbClassifyCommand.php — o comando (dry-run default)
 * @see resources/js/Pages/kb/Index.v2.charter.md §3/§8-bis — o bloqueador que isto resolve
 * @see resources/js/Pages/kb/_lib/mockData.ts — o mesmo contrato auto_match {field,op,value}
 */
class KbAutoClassifierService
{
    /**
     * Classifica os nós SEM categoria de um business aplicando as regras auto_match.
     *
     * @param  int   $businessId  tenant EXPLÍCITO (session não vale em CLI/job — Tier 0).
     * @param  bool  $apply       false (default) = dry-run: conta, NÃO grava. true = grava.
     * @return array{business_id:int, classified:int, homeless:int, by_subcategory:array<string,int>, homeless_by_type:array<string,int>, applied:bool}
     */
    public function classify(int $businessId, bool $apply = false): array
    {
        // Regras do MESMO business (Tier 0: nunca cruza tenant). Só as que têm auto_match.
        // SUPERADMIN: rodado em CLI/job (session vazia → global scope não resolve o tenant);
        // o tenant é reimposto explicitamente pelo ->where('business_id', $businessId) abaixo (ADR 0093).
        $rules = KbSubcategory::query()
            ->withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereNotNull('auto_match')
            ->get();

        // Nós SEM categoria do MESMO business.
        // SUPERADMIN: idem — CLI sem session; o business_id explícito abaixo é o único filtro de tenant (ADR 0093).
        $nodes = KbNode::query()
            ->withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereNull('category_id')
            ->get();

        $bySubcategory   = [];
        $homelessByType  = [];
        $classified      = 0;

        foreach ($nodes as $node) {
            $sub = $this->firstMatchingSubcategory($node, $rules);

            if ($sub === null) {
                $type = (string) ($node->type ?? '(sem type)');
                $homelessByType[$type] = ($homelessByType[$type] ?? 0) + 1;
                continue;
            }

            if ($apply) {
                // Grava explícito. subcategory_id + a category pai.
                // SUPERADMIN: CLI sem session; o UPDATE é duplo-scopado por business_id + chave do nó,
                // então nunca toca nó de outro tenant mesmo sem o global scope (ADR 0093).
                KbNode::query()
                    ->withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->whereKey($node->getKey())
                    ->update([
                        'category_id'    => $sub->category_id,
                        'subcategory_id' => $sub->getKey(),
                    ]);
            }

            $key = $sub->slug ?? (string) $sub->getKey();
            $bySubcategory[$key] = ($bySubcategory[$key] ?? 0) + 1;
            $classified++;
        }

        ksort($homelessByType);
        arsort($bySubcategory);

        return [
            'business_id'      => $businessId,
            'classified'       => $classified,
            'homeless'         => array_sum($homelessByType),
            'by_subcategory'   => $bySubcategory,
            'homeless_by_type' => $homelessByType,
            'applied'          => $apply,
        ];
    }

    /**
     * Primeira subcategoria cuja regra auto_match casa o nó. null se nenhuma casa.
     * Regra: {field: type|equip|tags, op: '='|'regex' (default '='), value: string}.
     */
    private function firstMatchingSubcategory(KbNode $node, Collection $rules): ?KbSubcategory
    {
        foreach ($rules as $sub) {
            if ($this->matches($node, (array) $sub->auto_match)) {
                return $sub;
            }
        }

        return null;
    }

    /** Casa um nó contra UMA regra auto_match. */
    private function matches(KbNode $node, array $rule): bool
    {
        $field = $rule['field'] ?? null;
        $value = $rule['value'] ?? null;
        $op    = $rule['op'] ?? '=';

        if ($field === null || $value === null) {
            return false;
        }

        // `tags` é array; `type`/`equip` são escalares.
        $subject = $field === 'tags'
            ? (array) ($node->tags ?? [])
            : $node->{$field} ?? null;

        if ($op === 'regex') {
            $re = '/' . str_replace('/', '\/', (string) $value) . '/i';

            if (is_array($subject)) {
                foreach ($subject as $item) {
                    if (@preg_match($re, (string) $item) === 1) {
                        return true;
                    }
                }

                return false;
            }

            return $subject !== null && @preg_match($re, (string) $subject) === 1;
        }

        // op '=' (exato). Pra tags (array), casa se ALGUM item bate exato.
        if (is_array($subject)) {
            return in_array($value, $subject, true);
        }

        return $subject !== null && (string) $subject === (string) $value;
    }
}
