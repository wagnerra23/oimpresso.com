<?php

declare(strict_types=1);

namespace App\Backup\ExportacaoEmpresa;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quais tabelas e colunas entram na exportação de UMA empresa (US-BKP-001).
 * Lista confirmada por [W] em 2026-10-27.
 *
 * Duas formas de uma tabela pertencer à empresa:
 *  - tem coluna `business_id` → filtra por ela (lista lida do banco, não escrita à mão);
 *  - é tabela de linha SEM `business_id` (FILHAS) → filtra pelo pai, recursivamente.
 *    Medido no schema de 2026-10-26: `transaction_sell_lines`, `purchase_lines`,
 *    `variations` e outras não têm `business_id`. Um `WHERE business_id` nelas
 *    seria erro; um SELECT sem filtro vazaria todas as empresas (Tier 0, ADR 0093).
 *
 * O que nunca sai: credencial, senha, tabela de plataforma/governança e cache.
 */
final class TabelasDaEmpresa
{
    /** Filha => [coluna que aponta pro pai, tabela pai]. O pai pode ser outra filha. */
    public const FILHAS = [
        'transaction_sell_lines' => ['transaction_id', 'transactions'],
        'purchase_lines' => ['transaction_id', 'transactions'],
        'stock_adjustment_lines' => ['transaction_id', 'transactions'],
        'transaction_sell_lines_purchase_lines' => ['sell_line_id', 'transaction_sell_lines'],
        'sell_line_warranties' => ['sell_line_id', 'transaction_sell_lines'],
        'variations' => ['product_id', 'products'],
        'product_variations' => ['product_id', 'products'],
        'variation_location_details' => ['location_id', 'business_locations'],
        'variation_group_prices' => ['price_group_id', 'selling_price_groups'],
        'product_locations' => ['location_id', 'business_locations'],
        'res_product_modifier_sets' => ['product_id', 'products'],
        'cash_register_transactions' => ['cash_register_id', 'cash_registers'],
        'account_transactions' => ['account_id', 'accounts'],
        'group_sub_taxes' => ['group_tax_id', 'tax_rates'],
        'item_devolucaos' => ['devolucao_id', 'devolucaos'],
        'crm_lead_users' => ['contact_id', 'contacts'],
        'crm_schedule_users' => ['schedule_id', 'crm_schedules'],
        'crm_schedule_logs' => ['schedule_id', 'crm_schedules'],
        'ponto_escala_turnos' => ['escala_id', 'ponto_escalas'],
    ];

    /** Têm `business_id` mas não saem: credencial, governança, cache, log técnico. */
    public const TABELAS_FORA = [
        'nfe_certificados', 'payment_gateway_credentials', 'rb_boleto_credentials',
        'nfse_provider_configs', 'whatsapp_business_configs', 'janaia_connections',
        'jana_cache_semantico', 'jana_negative_cache', 'ai_usage_log', 'kb_bridge_state',
        'gateway_webhook_events', 'pg_webhook_events', 'inter_webhook_log', 'user_lockouts',
    ];

    /** Prefixos de tabela que não saem (governança MCP, cópias de reparo, rascunho, SRS, licença). */
    public const PREFIXOS_FORA = ['mcp_', '_bkp_', '_tmp_', 'docs_', 'licenca_'];

    /** Colunas que nunca saem, por tabela. */
    public const COLUNAS_FORA = [
        'users' => ['password', 'remember_token', 'service_staff_pin', 'officeimpresso_senha'],
        'repair_job_sheets' => ['security_pwd', 'security_pattern'],
        'business' => [
            'email_settings', 'sms_settings', 'woocommerce_api_settings', 'woocommerce_settings',
            'boleto_settings', 'fiscal_settings', 'caminho_banco_servidor',
        ],
    ];

    /** Qualquer coluna com cara de credencial sai, em qualquer tabela (ex.: tokens do WhatsApp). */
    public const PADRAO_COLUNA_FORA = '/(password|senha|secret|token|api_?key|private|certificado|cert_|pfx|csc)/i';

    /** @return list<string> tabelas exportáveis, em ordem alfabética */
    public function tabelas(): array
    {
        // Uma consulta só ao catálogo do MySQL (em vez de hasColumn tabela a tabela).
        $diretas = array_map(
            fn ($r) => (string) $r->tabela,
            DB::select(
                "SELECT c.TABLE_NAME AS tabela FROM information_schema.COLUMNS c
                 JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
                 WHERE c.TABLE_SCHEMA = DATABASE() AND c.COLUMN_NAME = 'business_id' AND t.TABLE_TYPE = 'BASE TABLE'"
            ),
        );
        $filhas = array_filter(array_keys(self::FILHAS), fn (string $t) => Schema::hasTable($t));
        $todas = array_values(array_filter(
            array_unique(array_merge(['business'], $diretas, $filhas)),
            fn (string $t) => $this->entra($t),
        ));
        sort($todas);

        return $todas;
    }

    public function entra(string $tabela): bool
    {
        if (in_array($tabela, self::TABELAS_FORA, true)) {
            return false;
        }
        foreach (self::PREFIXOS_FORA as $p) {
            if (str_starts_with($tabela, $p)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    public function colunas(string $tabela): array
    {
        $fora = self::COLUNAS_FORA[$tabela] ?? [];

        return array_values(array_filter(
            Schema::getColumnListing($tabela),
            fn (string $c) => ! in_array($c, $fora, true) && ! preg_match(self::PADRAO_COLUNA_FORA, $c),
        ));
    }

    /** Consulta só com as linhas da empresa. Nunca devolve consulta sem filtro. */
    public function consulta(string $tabela, int $businessId): Builder
    {
        $q = DB::table($tabela)->select($this->colunas($tabela));

        if ($tabela === 'business') {
            return $q->where('id', $businessId);
        }
        if (isset(self::FILHAS[$tabela])) {
            [$coluna, $pai] = self::FILHAS[$tabela];

            return $q->whereIn($coluna, $this->idsDoPai($pai, $businessId));
        }
        if (Schema::hasColumn($tabela, 'business_id')) {
            return $q->where('business_id', $businessId);
        }

        throw new \LogicException("Tabela {$tabela} sem regra de filtro por empresa.");
    }

    private function idsDoPai(string $pai, int $businessId): Builder
    {
        if (isset(self::FILHAS[$pai])) {
            [$coluna, $avo] = self::FILHAS[$pai];

            return DB::table($pai)->select('id')->whereIn($coluna, $this->idsDoPai($avo, $businessId));
        }

        return DB::table($pai)->select('id')->where('business_id', $businessId);
    }
}
