<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Macros;

use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Macro;
use Modules\Whatsapp\Entities\MacroVariant;

/**
 * MacroVariantPicker — sorteia variante ativa de uma Macro via weighted
 * random (US-WA-049, gap P2 #18 A/B testing).
 *
 * Regras:
 *   - 0 variantes ativas       → retorna null (caller usa macro.body padrão).
 *   - 1 variante ativa         → retorna ela (sem aleatoriedade).
 *   - N variantes ativas       → weighted random baseado em `weight`.
 *   - Variante com weight=0    → excluída da loteria (pause sem delete).
 *
 * Uso canônico (MacroExecutor):
 *
 *   $variant = $picker->pickFor($macro);
 *   $body = $variant?->body ?? $macro->body;
 *   // ... envia $body via daemon ...
 *   if ($variant) {
 *       $message->macro_variant_id = $variant->id;
 *       $variant->increment('sent_count');
 *   }
 *
 * `random_int` é CSPRNG — overkill mas evita modulo bias do `rand`. Custo
 * trivial e nos protege contra atacante interno tentando explorar
 * previsibilidade (improvável mas zero motivo pra usar `rand`).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-049
 */
class MacroVariantPicker
{
    /**
     * Sorteia variante ativa pra macro. Retorna null se nenhuma elegível.
     *
     * Filtra:
     *  - mesmo `business_id` da macro (Tier 0 — defesa em profundidade)
     *  - `active=true`
     *  - `weight > 0` (weight=0 = pausada manualmente)
     */
    public function pickFor(Macro $macro): ?MacroVariant
    {
        $variants = MacroVariant::query()
            ->withoutGlobalScope(ScopeByBusiness::class) // SUPERADMIN: scope manual via where business_id (CLI/test friendly)
            ->where('business_id', $macro->business_id)
            ->where('macro_id', $macro->id)
            ->where('active', true)
            ->where('weight', '>', 0)
            ->get();

        if ($variants->isEmpty()) {
            return null;
        }

        if ($variants->count() === 1) {
            return $variants->first();
        }

        return $this->weightedPick($variants->all());
    }

    /**
     * Weighted random pick entre N variantes.
     *
     * Algoritmo clássico (cumulative weight + roll 1..total):
     *   1. Soma todos pesos → total
     *   2. Roll random_int(1, total) → R
     *   3. Itera somando weight de cada variante; quando soma >= R, retorna.
     *
     * Distribuição estatística aproximada à proporção dos pesos.
     *
     * @param array<int, MacroVariant> $variants
     */
    private function weightedPick(array $variants): MacroVariant
    {
        $total = 0;
        foreach ($variants as $v) {
            $total += max(0, (int) $v->weight);
        }

        // Edge guard — todos zerados (não deveria, query já filtra > 0)
        if ($total <= 0) {
            return $variants[0];
        }

        $roll = random_int(1, $total);
        $cumulative = 0;
        foreach ($variants as $v) {
            $cumulative += (int) $v->weight;
            if ($roll <= $cumulative) {
                return $v;
            }
        }

        // Fallback teórico (não deveria atingir)
        return $variants[count($variants) - 1];
    }
}
