<?php

namespace App\Console\Commands;

use App\Business;
use Illuminate\Console\Command;

/**
 * Set arbitrary key in `business.pos_settings` JSON via CLI.
 *
 * Wagner 2026-05-27 — criado pra ativar/desativar feature flags POS
 * sem precisar abrir UI de Settings (útil pra hotfixes prod + automação
 * via workflow_dispatch.extra_artisan). Multi-tenant safe: business_id
 * é arg obrigatório.
 *
 * Casos de uso atual:
 *   - enable_msp (preço mínimo de venda) — Larissa @ Rota Livre, smoke 2026-05-27
 *   - allow_overselling, hide_product_suggestion, etc
 *
 * Exemplo:
 *   php artisan business:set-pos-setting 4 enable_msp 1
 *   php artisan business:set-pos-setting 1 allow_overselling 0
 *
 * Ref: ADR 0093 (multi-tenant Tier 0 — business_id explícito).
 */
class SetPosSettingCommand extends Command
{
    protected $signature = 'business:set-pos-setting
                            {business_id : ID do business OU "all" pra todos (Tier 0 ADR 0093)}
                            {key : Chave do pos_settings (ex: enable_msp)}
                            {value : Valor (1/0 boolean ou string)}';

    protected $description = 'Atualiza chave em business.pos_settings JSON pra 1 business ou TODOS (all).';

    public function handle(): int
    {
        $businessIdArg = (string) $this->argument('business_id');
        $key = (string) $this->argument('key');
        $value = $this->argument('value');

        // Cast 0/1 → int pra match com formato UltimatePOS
        if ($value === '0' || $value === '1') {
            $value = (int) $value;
        }

        // Wagner 2026-05-27: aceitar "all" pra iterar TODOS os businesses
        // (ativar enable_msp pra biz=1, biz=4 e quaisquer futuros sem
        // rodar comando N vezes).
        if (strtolower($businessIdArg) === 'all') {
            $businesses = Business::all();
            if ($businesses->isEmpty()) {
                $this->warn('Nenhum business encontrado.');
                return self::SUCCESS;
            }
            foreach ($businesses as $b) {
                $this->applyToBusiness($b, $key, $value);
            }
            $this->info("✓ Aplicado em {$businesses->count()} businesses.");
            return self::SUCCESS;
        }

        $businessId = (int) $businessIdArg;
        $business = Business::find($businessId);
        if (!$business) {
            $this->error("business_id={$businessId} não encontrado");
            return self::FAILURE;
        }

        $this->applyToBusiness($business, $key, $value);
        return self::SUCCESS;
    }

    protected function applyToBusiness(Business $business, string $key, $value): void
    {
        $settings = $business->pos_settings ? json_decode($business->pos_settings, true) : [];
        if (!is_array($settings)) {
            $settings = [];
        }

        $before = $settings[$key] ?? null;
        $settings[$key] = $value;
        $business->pos_settings = json_encode($settings);
        $business->save();

        $this->info("✓ business_id={$business->id} ({$business->name}) pos_settings['{$key}']: " . json_encode($before) . ' → ' . json_encode($value));
    }
}
