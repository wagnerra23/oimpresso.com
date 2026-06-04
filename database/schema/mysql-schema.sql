/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
DROP TABLE IF EXISTS `_bkp_bad_compras_20260602`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `_bkp_bad_compras_20260602` (
  `id` int(10) unsigned NOT NULL DEFAULT 0,
  `business_id` int(10) unsigned NOT NULL,
  `process_id` bigint(20) unsigned DEFAULT NULL,
  `current_stage_id` bigint(20) unsigned DEFAULT NULL,
  `is_grouped_invoice` tinyint(1) NOT NULL DEFAULT 0,
  `location_id` int(10) unsigned DEFAULT NULL,
  `is_kitchen_order` tinyint(1) NOT NULL DEFAULT 0,
  `journal_entry_id` bigint(20) unsigned DEFAULT NULL,
  `res_table_id` int(10) unsigned DEFAULT NULL COMMENT 'fields to restaurant module',
  `res_waiter_id` int(10) unsigned DEFAULT NULL COMMENT 'fields to restaurant module',
  `res_order_status` enum('received','cooked','served') DEFAULT NULL,
  `type` varchar(191) DEFAULT NULL,
  `sub_type` varchar(20) DEFAULT NULL,
  `status` varchar(191) NOT NULL,
  `sub_status` varchar(191) DEFAULT NULL,
  `is_quotation` tinyint(1) NOT NULL DEFAULT 0,
  `payment_status` enum('paid','due','partial') DEFAULT NULL,
  `adjustment_type` enum('normal','abnormal') DEFAULT NULL,
  `contact_id` int(11) unsigned DEFAULT NULL,
  `customer_group_id` int(11) DEFAULT NULL COMMENT 'used to add customer group while selling',
  `invoice_no` varchar(191) DEFAULT NULL,
  `ref_no` varchar(191) DEFAULT NULL,
  `source` varchar(191) DEFAULT NULL,
  `os_ref` varchar(20) DEFAULT NULL COMMENT 'Referência cross-módulo OS-NNNN quando source=oficina · ADR 0192',
  `commission_split` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Split { mecanico_id, mecanico_pct, balcao_id, balcao_pct } total=100 · ADR 0192',
  `subscription_no` varchar(191) DEFAULT NULL,
  `subscription_repeat_on` varchar(191) DEFAULT NULL,
  `transaction_date` datetime NOT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL COMMENT 'Marcador de cancelamento · NULL = ativa · timestamp = cancelada (preserva row + audit) · ADR 0192 reverse hook',
  `invoiced_at` datetime DEFAULT NULL COMMENT 'US-SELL-021 · DT_FATURAMENTO legacy — quando a venda foi faturada',
  `invoice_sent_at` datetime DEFAULT NULL COMMENT 'US-SELL-021 · FATURAMENTO_DT_ENVIO legacy — quando a fatura foi enviada ao cliente',
  `competence_date` date DEFAULT NULL COMMENT 'US-SELL-021 · DT_COMPETENCIA legacy — mês contábil de competência (≠ emissão)',
  `due_date` date DEFAULT NULL COMMENT 'US-SELL-021 · PROJETO_DT_FIM legacy — data prometida pro cliente (entrega/serviço)',
  `total_before_tax` decimal(22,4) NOT NULL DEFAULT 0.0000 COMMENT 'Total before the purchase/invoice tax, this includeds the indivisual product tax',
  `tax_id` int(10) unsigned DEFAULT NULL,
  `tax_amount` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `discount_type` enum('fixed','percentage') DEFAULT NULL,
  `discount_amount` decimal(22,4) DEFAULT 0.0000,
  `rp_redeemed` int(11) NOT NULL DEFAULT 0 COMMENT 'rp is the short form of reward points',
  `rp_redeemed_amount` decimal(22,4) NOT NULL DEFAULT 0.0000 COMMENT 'rp is the short form of reward points',
  `shipping_details` varchar(191) DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `delivery_date` datetime DEFAULT NULL,
  `shipping_status` varchar(191) DEFAULT NULL,
  `delivered_to` varchar(191) DEFAULT NULL,
  `delivery_person` bigint(20) DEFAULT NULL,
  `shipping_charges` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `shipping_custom_field_1` varchar(191) DEFAULT NULL,
  `shipping_custom_field_2` varchar(191) DEFAULT NULL,
  `shipping_custom_field_3` varchar(191) DEFAULT NULL,
  `shipping_custom_field_4` varchar(191) DEFAULT NULL,
  `shipping_custom_field_5` varchar(191) DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `staff_note` text DEFAULT NULL,
  `is_export` tinyint(1) NOT NULL DEFAULT 0,
  `export_custom_fields_info` longtext DEFAULT NULL,
  `round_off_amount` decimal(22,4) NOT NULL DEFAULT 0.0000 COMMENT 'Difference of rounded total and actual total',
  `additional_expense_key_1` varchar(191) DEFAULT NULL,
  `additional_expense_value_1` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `additional_expense_key_2` varchar(191) DEFAULT NULL,
  `additional_expense_value_2` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `additional_expense_key_3` varchar(191) DEFAULT NULL,
  `additional_expense_value_3` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `additional_expense_key_4` varchar(191) DEFAULT NULL,
  `additional_expense_value_4` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `final_total` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `expense_category_id` int(10) unsigned DEFAULT NULL,
  `expense_sub_category_id` int(11) DEFAULT NULL,
  `expense_for` int(10) unsigned DEFAULT NULL,
  `commission_agent` int(11) DEFAULT NULL,
  `document` varchar(191) DEFAULT NULL,
  `is_direct_sale` tinyint(1) NOT NULL DEFAULT 0,
  `is_suspend` tinyint(1) NOT NULL DEFAULT 0,
  `exchange_rate` decimal(20,3) NOT NULL DEFAULT 1.000,
  `total_amount_recovered` decimal(22,4) DEFAULT NULL COMMENT 'Used for stock adjustment.',
  `transfer_parent_id` int(11) DEFAULT NULL,
  `return_parent_id` int(11) DEFAULT NULL,
  `opening_stock_product_id` int(11) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `purchase_requisition_ids` text DEFAULT NULL,
  `prefer_payment_method` varchar(191) DEFAULT NULL,
  `prefer_payment_account` int(11) DEFAULT NULL,
  `sales_order_ids` text DEFAULT NULL,
  `purchase_order_ids` text DEFAULT NULL,
  `custom_field_1` varchar(191) DEFAULT NULL,
  `custom_field_2` varchar(191) DEFAULT NULL,
  `custom_field_3` varchar(191) DEFAULT NULL,
  `custom_field_4` varchar(191) DEFAULT NULL,
  `crm_is_order_request` tinyint(1) NOT NULL DEFAULT 0,
  `essentials_duration` decimal(8,2) NOT NULL,
  `essentials_duration_unit` varchar(20) DEFAULT NULL,
  `essentials_amount_per_unit_duration` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `essentials_allowances` text DEFAULT NULL,
  `essentials_deductions` text DEFAULT NULL,
  `mfg_parent_production_purchase_id` int(11) DEFAULT NULL,
  `mfg_wasted_units` decimal(22,4) DEFAULT NULL,
  `mfg_production_cost` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `mfg_production_cost_type` varchar(191) DEFAULT 'percentage',
  `mfg_is_final` tinyint(1) NOT NULL DEFAULT 0,
  `woocommerce_order_id` int(11) DEFAULT NULL,
  `repair_completed_on` datetime DEFAULT NULL,
  `repair_warranty_id` int(11) DEFAULT NULL,
  `repair_brand_id` int(11) DEFAULT NULL,
  `repair_status_id` int(11) DEFAULT NULL,
  `repair_model_id` int(11) DEFAULT NULL,
  `repair_job_sheet_id` int(10) unsigned DEFAULT NULL,
  `repair_defects` text DEFAULT NULL,
  `repair_serial_no` varchar(191) DEFAULT NULL,
  `repair_checklist` text DEFAULT NULL,
  `repair_security_pwd` varchar(191) DEFAULT NULL,
  `repair_security_pattern` varchar(191) DEFAULT NULL,
  `repair_due_date` datetime DEFAULT NULL,
  `repair_device_id` int(11) DEFAULT NULL,
  `repair_updates_notif` tinyint(1) NOT NULL DEFAULT 0,
  `import_batch` int(11) DEFAULT NULL,
  `import_time` datetime DEFAULT NULL,
  `types_of_service_id` int(11) DEFAULT NULL,
  `packing_charge` decimal(22,4) DEFAULT NULL,
  `packing_charge_type` enum('fixed','percent') DEFAULT NULL,
  `service_custom_field_1` text DEFAULT NULL,
  `service_custom_field_2` text DEFAULT NULL,
  `service_custom_field_3` text DEFAULT NULL,
  `service_custom_field_4` text DEFAULT NULL,
  `service_custom_field_5` text DEFAULT NULL,
  `service_custom_field_6` text DEFAULT NULL,
  `is_created_from_api` tinyint(1) NOT NULL DEFAULT 0,
  `rp_earned` int(11) NOT NULL DEFAULT 0 COMMENT 'rp is the short form of reward points',
  `order_addresses` text DEFAULT NULL,
  `is_recurring` tinyint(1) NOT NULL DEFAULT 0,
  `recur_interval` double(22,4) DEFAULT NULL,
  `recur_interval_type` enum('days','months','years') DEFAULT NULL,
  `recur_repetitions` int(11) DEFAULT NULL,
  `recur_stopped_on` datetime DEFAULT NULL,
  `recur_parent_id` int(11) DEFAULT NULL,
  `invoice_token` varchar(191) DEFAULT NULL,
  `pay_term_number` int(11) DEFAULT NULL,
  `pay_term_type` enum('days','months') DEFAULT NULL,
  `selling_price_group_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `natureza_id` int(10) unsigned DEFAULT NULL,
  `placa` varchar(9) NOT NULL DEFAULT '',
  `uf` varchar(2) NOT NULL DEFAULT '',
  `valor_frete` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tipo` int(11) NOT NULL DEFAULT 0,
  `qtd_volumes` int(11) NOT NULL DEFAULT 0,
  `numeracao_volumes` varchar(20) NOT NULL DEFAULT '',
  `especie` varchar(20) NOT NULL DEFAULT '',
  `peso_liquido` decimal(8,3) NOT NULL DEFAULT 0.000,
  `peso_bruto` decimal(8,3) NOT NULL DEFAULT 0.000,
  `numero_nfe` int(11) NOT NULL DEFAULT 0,
  `numero_nfce` int(11) NOT NULL DEFAULT 0,
  `numero_nfe_entrada` int(11) NOT NULL DEFAULT 0,
  `chave` varchar(48) NOT NULL DEFAULT '',
  `chave_entrada` varchar(48) NOT NULL DEFAULT '',
  `sequencia_cce` int(11) NOT NULL DEFAULT 0,
  `cpf_nota` varchar(15) NOT NULL DEFAULT '',
  `troco` decimal(10,2) NOT NULL DEFAULT 0.00,
  `valor_recebido` decimal(10,2) NOT NULL DEFAULT 0.00,
  `transportadora_id` int(10) unsigned DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'NOVO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `_bkp_devolucao_softdel_20260603`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `_bkp_devolucao_softdel_20260603` (
  `id` int(10) unsigned NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `status` enum('aberto','parcial','quitado','cancelado') NOT NULL DEFAULT 'aberto',
  `tipo` enum('receber','pagar') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `_bkp_fin_titulos_20260602`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `_bkp_fin_titulos_20260602` (
  `id` int(10) unsigned NOT NULL DEFAULT 0,
  `business_id` int(10) unsigned NOT NULL,
  `numero` varchar(20) NOT NULL COMMENT 'Sequencial business-isolado; lockForUpdate em geração',
  `legacy_id` varchar(32) DEFAULT NULL COMMENT 'Chave natural FINANCEIRO.CODIGO Delphi pra dedup importer.',
  `tipo` enum('receber','pagar') NOT NULL,
  `status` enum('aberto','parcial','quitado','cancelado') NOT NULL DEFAULT 'aberto',
  `aprovacao_status` enum('pendente','aprovado','rejeitado') DEFAULT NULL,
  `aprovado_by` int(10) unsigned DEFAULT NULL,
  `aprovado_at` timestamp NULL DEFAULT NULL,
  `aprovacao_motivo` varchar(500) DEFAULT NULL,
  `cliente_id` int(10) unsigned DEFAULT NULL COMMENT 'FK soft -> contacts.id',
  `cliente_descricao` varchar(255) DEFAULT NULL COMMENT 'Fallback se cliente não cadastrado',
  `valor_total` decimal(22,4) NOT NULL,
  `valor_aberto` decimal(22,4) NOT NULL COMMENT 'valor_total - sum(baixas.valor); auto via observer',
  `moeda` char(3) NOT NULL DEFAULT 'BRL',
  `emissao` date NOT NULL,
  `vencimento` date NOT NULL,
  `competencia_mes` char(7) NOT NULL COMMENT 'YYYY-MM regime competência',
  `origem` enum('manual','venda','compra','despesa','recurring','folha','caixa') NOT NULL,
  `origem_id` int(10) unsigned DEFAULT NULL COMMENT 'transaction.id, recurring_invoice.id, etc.',
  `parcela_numero` tinyint(3) unsigned DEFAULT NULL,
  `parcela_total` tinyint(3) unsigned DEFAULT NULL,
  `titulo_pai_id` int(10) unsigned DEFAULT NULL COMMENT 'Self-FK para parcelas',
  `plano_conta_id` int(10) unsigned DEFAULT NULL,
  `categoria_id` int(10) unsigned DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Shape específico por origem (ex: nfe_chave)',
  `created_by` int(10) unsigned NOT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `conferido_by` int(10) unsigned DEFAULT NULL COMMENT 'FK users.id — quem marcou como conferido (per-user audit)',
  `conferido_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp da conferência',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `_bkp_prod_codigo_backfill_20260602`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `_bkp_prod_codigo_backfill_20260602` (
  `id` int(10) unsigned NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `_bkp_tsl_biz164_20260601`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `_bkp_tsl_biz164_20260601` (
  `id` int(10) unsigned NOT NULL DEFAULT 0,
  `transaction_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `variation_id` int(10) unsigned NOT NULL,
  `quantity` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `secondary_unit_quantity` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `mfg_waste_percent` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `mfg_ingredient_group_id` int(11) DEFAULT NULL,
  `quantity_returned` decimal(20,4) NOT NULL DEFAULT 0.0000,
  `unit_price_before_discount` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `unit_price` decimal(22,4) DEFAULT NULL COMMENT 'Sell price excluding tax',
  `line_discount_type` enum('fixed','percentage') DEFAULT NULL,
  `line_discount_amount` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `unit_price_inc_tax` decimal(22,4) DEFAULT NULL COMMENT 'Sell price including tax',
  `item_tax` decimal(22,4) NOT NULL COMMENT 'Tax for one quantity',
  `tax_id` int(10) unsigned DEFAULT NULL,
  `discount_id` int(11) DEFAULT NULL,
  `lot_no_line_id` int(11) DEFAULT NULL,
  `sell_line_note` text DEFAULT NULL,
  `so_line_id` int(11) DEFAULT NULL,
  `so_quantity_invoiced` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `woocommerce_line_items_id` int(11) DEFAULT NULL,
  `res_service_staff_id` int(11) DEFAULT NULL,
  `res_line_order_status` varchar(191) DEFAULT NULL,
  `parent_sell_line_id` int(11) DEFAULT NULL,
  `children_type` varchar(191) NOT NULL DEFAULT '' COMMENT 'Type of children for the parent, like modifier or combo',
  `sub_unit_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `_bkp_undelete_prod_20260602`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `_bkp_undelete_prod_20260602` (
  `id` int(10) unsigned NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `_tmp_skus_ativos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `_tmp_skus_ativos` (
  `sku` varchar(191) NOT NULL,
  PRIMARY KEY (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account_detail_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_detail_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `account_subtype_id` bigint(20) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account_subtypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_subtypes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `account_type` varchar(191) NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `type` enum('debit','credit') NOT NULL,
  `sub_type` enum('opening_balance','fund_transfer','deposit') DEFAULT NULL,
  `amount` decimal(22,4) NOT NULL,
  `reff_no` varchar(191) DEFAULT NULL,
  `operation_date` datetime NOT NULL,
  `created_by` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `transaction_payment_id` int(11) DEFAULT NULL,
  `transfer_transaction_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_transactions_account_id_index` (`account_id`),
  KEY `account_transactions_transaction_id_index` (`transaction_id`),
  KEY `account_transactions_transaction_payment_id_index` (`transaction_payment_id`),
  KEY `account_transactions_transfer_transaction_id_index` (`transfer_transaction_id`),
  KEY `account_transactions_created_by_index` (`created_by`),
  KEY `account_transactions_type_index` (`type`),
  KEY `account_transactions_sub_type_index` (`sub_type`),
  KEY `account_transactions_operation_date_index` (`operation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `parent_account_type_id` int(11) DEFAULT NULL,
  `business_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_types_parent_account_type_id_index` (`parent_account_type_id`),
  KEY `account_types_business_id_index` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `name` varchar(191) NOT NULL,
  `account_number` varchar(191) NOT NULL,
  `account_details` text DEFAULT NULL,
  `account_type_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `accounts_business_id_index` (`business_id`),
  KEY `accounts_account_type_id_index` (`account_type_id`),
  KEY `accounts_created_by_index` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `accounts_legacy_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts_legacy_map` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL COMMENT 'FK accounts.id (core UltimatePOS)',
  `legacy_source` varchar(50) NOT NULL COMMENT 'Identifica sistema origem: wr-comercial-delphi, bling, tiny, sankhya, etc',
  `legacy_id` varchar(100) NOT NULL COMMENT 'PK original no sistema legacy (CODIGO Delphi, ID Bling, etc) — string pra acomodar tipos diversos',
  `legacy_imported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `legacy_importer_version` varchar(20) DEFAULT NULL COMMENT 'Ex: import-contas-bancarias-py-0.1.0 — pra rastrear qual versão importou',
  `legacy_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Snapshot do registro original em JSON pra audit/debug' CHECK (json_valid(`legacy_metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_biz_source_legacy` (`business_id`,`legacy_source`,`legacy_id`),
  KEY `accounts_legacy_map_account_id_foreign` (`account_id`),
  KEY `idx_source_biz` (`legacy_source`,`business_id`),
  KEY `accounts_legacy_map_business_id_index` (`business_id`),
  CONSTRAINT `accounts_legacy_map_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `accounts_legacy_map_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(191) DEFAULT NULL,
  `description` text NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `subject_type` varchar(191) DEFAULT NULL,
  `event` varchar(191) DEFAULT NULL,
  `business_id` int(11) DEFAULT NULL,
  `causer_id` int(11) DEFAULT NULL,
  `causer_type` varchar(191) DEFAULT NULL,
  `causer_kind` enum('user','agent','system','api') NOT NULL DEFAULT 'user',
  `agent_run_id` bigint(20) unsigned DEFAULT NULL,
  `properties` text DEFAULT NULL,
  `reverted_at` timestamp NULL DEFAULT NULL,
  `reverted_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `revert_reason` varchar(500) DEFAULT NULL,
  `batch_uuid` char(36) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_log_log_name_index` (`log_name`),
  KEY `idx_business_kind_created` (`business_id`,`causer_kind`,`created_at`),
  KEY `idx_subject_reverted` (`subject_type`,`subject_id`,`reverted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `advisor_business_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `advisor_business_access` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `advisor_id` bigint(20) unsigned NOT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `revoked_at` timestamp NULL DEFAULT NULL,
  `granted_by` int(10) unsigned NOT NULL COMMENT 'users.id que concedeu (owner do business)',
  `revoked_by` int(10) unsigned DEFAULT NULL,
  `scope_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '{"can_view_reports": true, "can_view_unificado": true, "consented_at": "...", "consented_by": user_id}' CHECK (json_valid(`scope_json`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_aba_business_revoked` (`business_id`,`revoked_at`),
  KEY `idx_aba_advisor_revoked` (`advisor_id`,`revoked_at`),
  CONSTRAINT `fk_aba_advisor` FOREIGN KEY (`advisor_id`) REFERENCES `advisors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aba_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `advisors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `advisors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cnpj_contador` varchar(14) NOT NULL COMMENT 'CNPJ contador 14 dígitos numéricos',
  `nome` varchar(200) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL COMMENT 'bcrypt — null = ainda não definiu senha',
  `telefone` varchar(20) DEFAULT NULL,
  `referral_code` varchar(8) NOT NULL COMMENT 'código compartilhável /advisors/register?ref=XXXX',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `remember_token` varchar(100) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `advisors_cnpj_contador_unique` (`cnpj_contador`),
  UNIQUE KEY `advisors_email_unique` (`email`),
  UNIQUE KEY `advisors_referral_code_unique` (`referral_code`),
  KEY `advisors_ativo_index` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_usage_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_usage_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `feature` varchar(64) NOT NULL,
  `provider` varchar(32) NOT NULL,
  `model` varchar(64) NOT NULL,
  `operation` varchar(32) NOT NULL DEFAULT 'extract',
  `input_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `output_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `cost_usd` decimal(10,6) NOT NULL,
  `idempotency_hash` varchar(64) DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'ok',
  `error_message` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_usage_log_biz_feature_idx` (`business_id`,`feature`,`created_at`),
  KEY `ai_usage_log_biz_status_idx` (`business_id`,`status`),
  KEY `ai_usage_log_business_id_index` (`business_id`),
  KEY `ai_usage_log_idempotency_hash_index` (`idempotency_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `aiassistance_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `aiassistance_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tool_type` varchar(191) NOT NULL,
  `input_data` text DEFAULT NULL,
  `tokens_used` int(11) NOT NULL DEFAULT 0,
  `output_data` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `anotacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `anotacoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` bigint(20) unsigned NOT NULL,
  `subject_type` varchar(191) NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `anotacoes_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `anotacoes_business_id_index` (`business_id`),
  KEY `anotacoes_biz_subject_index` (`business_id`,`subject_type`,`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `arquivos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `arquivos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `arquivable_type` varchar(255) DEFAULT NULL,
  `arquivable_id` bigint(20) unsigned DEFAULT NULL,
  `disk` varchar(32) NOT NULL,
  `storage_path` varchar(512) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(127) NOT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL,
  `md5` char(32) NOT NULL,
  `bucket` enum('sensitive','memory','user','spec','ambiguous','discard','active') NOT NULL DEFAULT 'active',
  `sub_destination` varchar(255) DEFAULT NULL,
  `sensitive_flags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sensitive_flags`)),
  `classified_by` varchar(64) DEFAULT NULL,
  `classified_at` timestamp NULL DEFAULT NULL,
  `uploaded_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `visibility` enum('private','business','public') NOT NULL DEFAULT 'private',
  `encrypted` tinyint(1) NOT NULL DEFAULT 0,
  `retention_days` int(10) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `metadata_recalculated_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp da última recalculação de md5+size_bytes (Sprint 7 ADR 0123)',
  PRIMARY KEY (`id`),
  KEY `idx_arquivos_business` (`business_id`),
  KEY `idx_arquivos_arquivable` (`arquivable_type`,`arquivable_id`),
  KEY `idx_arquivos_md5` (`md5`),
  KEY `idx_arquivos_bucket` (`bucket`),
  KEY `idx_arquivos_deleted` (`deleted_at`),
  KEY `idx_arquivos_recalculated_at` (`metadata_recalculated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `arquivos_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `arquivos_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `arquivo_id` bigint(20) unsigned NOT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` enum('upload','download','classify','reclassify','soft_delete','restore','hard_delete','signed_url_issued') NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_arquivos_audit_arquivo` (`arquivo_id`),
  KEY `idx_arquivos_audit_biz_action_ts` (`business_id`,`action`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `arquivos_dedupe`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `arquivos_dedupe` (
  `md5` char(32) NOT NULL,
  `first_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `occurrences` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`md5`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_maintenances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_maintenances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `maitenance_id` varchar(191) DEFAULT NULL,
  `status` varchar(191) DEFAULT NULL,
  `priority` varchar(191) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `maintenance_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `asset_maintenances_business_id_index` (`business_id`),
  KEY `asset_maintenances_asset_id_index` (`asset_id`),
  KEY `asset_maintenances_status_index` (`status`),
  KEY `asset_maintenances_priority_index` (`priority`),
  KEY `asset_maintenances_created_by_index` (`created_by`),
  KEY `asset_maintenances_assigned_to_index` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `asset_id` int(10) unsigned DEFAULT NULL,
  `transaction_type` varchar(191) NOT NULL,
  `ref_no` varchar(191) NOT NULL,
  `receiver` int(10) unsigned DEFAULT NULL COMMENT 'id from users table, who receives asset',
  `quantity` decimal(22,4) NOT NULL,
  `transaction_datetime` datetime NOT NULL,
  `allocated_upto` date DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL COMMENT 'id from asset_transactions table',
  `created_by` int(10) unsigned NOT NULL COMMENT 'id from users table, who allocated asset',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `asset_transactions_business_id_foreign` (`business_id`),
  KEY `asset_transactions_asset_id_foreign` (`asset_id`),
  KEY `asset_transactions_receiver_foreign` (`receiver`),
  KEY `asset_transactions_parent_id_foreign` (`parent_id`),
  KEY `asset_transactions_created_by_foreign` (`created_by`),
  CONSTRAINT `asset_transactions_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_transactions_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `asset_transactions_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `asset_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_transactions_receiver_foreign` FOREIGN KEY (`receiver`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_warranties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_warranties` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `additional_cost` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `additional_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `asset_code` varchar(191) NOT NULL,
  `name` varchar(191) NOT NULL,
  `quantity` decimal(22,4) NOT NULL,
  `model` varchar(191) DEFAULT NULL,
  `serial_no` varchar(191) DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `location_id` int(10) unsigned DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_type` varchar(191) DEFAULT NULL,
  `unit_price` decimal(22,4) NOT NULL,
  `depreciation` decimal(22,4) DEFAULT NULL,
  `is_allocatable` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assets_business_id_foreign` (`business_id`),
  KEY `assets_category_id_foreign` (`category_id`),
  KEY `assets_created_by_foreign` (`created_by`),
  CONSTRAINT `assets_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assets_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `assets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `barcodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `barcodes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `width` double(22,4) DEFAULT NULL,
  `height` double(22,4) DEFAULT NULL,
  `paper_width` double(22,4) DEFAULT NULL,
  `paper_height` double(22,4) DEFAULT NULL,
  `top_margin` double(22,4) DEFAULT NULL,
  `left_margin` double(22,4) DEFAULT NULL,
  `row_distance` double(22,4) DEFAULT NULL,
  `col_distance` double(22,4) DEFAULT NULL,
  `stickers_in_one_row` int(11) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_continuous` tinyint(1) NOT NULL DEFAULT 0,
  `stickers_in_one_sheet` int(11) DEFAULT NULL,
  `business_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `barcodes_business_id_foreign` (`business_id`),
  CONSTRAINT `barcodes_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` int(10) unsigned NOT NULL,
  `waiter_id` int(10) unsigned DEFAULT NULL,
  `table_id` int(10) unsigned DEFAULT NULL,
  `correspondent_id` int(11) DEFAULT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `location_id` int(10) unsigned NOT NULL,
  `booking_start` datetime NOT NULL,
  `booking_end` datetime NOT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `booking_status` varchar(191) NOT NULL,
  `booking_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bookings_contact_id_foreign` (`contact_id`),
  KEY `bookings_business_id_foreign` (`business_id`),
  KEY `bookings_created_by_foreign` (`created_by`),
  KEY `bookings_table_id_index` (`table_id`),
  KEY `bookings_waiter_id_index` (`waiter_id`),
  KEY `bookings_location_id_index` (`location_id`),
  KEY `bookings_booking_status_index` (`booking_status`),
  KEY `bookings_correspondent_id_index` (`correspondent_id`),
  CONSTRAINT `bookings_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `branch_capital`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `branch_capital` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `created_by_id` int(11) NOT NULL,
  `debit` decimal(11,2) DEFAULT NULL,
  `credit` decimal(11,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `date` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `brands` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `use_for_repair` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'brands to be used on repair module',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `officeimpresso_codigo` int(11) DEFAULT NULL,
  `officeimpresso_dt_alteracao` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `brands_business_id_foreign` (`business_id`),
  KEY `brands_created_by_foreign` (`created_by`),
  CONSTRAINT `brands_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `brands_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `budgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `budgets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `chart_of_account_id` bigint(20) unsigned NOT NULL,
  `financial_year` varchar(191) NOT NULL,
  `month_1` double(8,2) NOT NULL,
  `month_2` double(8,2) NOT NULL,
  `month_3` double(8,2) NOT NULL,
  `month_4` double(8,2) NOT NULL,
  `month_5` double(8,2) NOT NULL,
  `month_6` double(8,2) NOT NULL,
  `month_7` double(8,2) NOT NULL,
  `month_8` double(8,2) NOT NULL,
  `month_9` double(8,2) NOT NULL,
  `month_10` double(8,2) NOT NULL,
  `month_11` double(8,2) NOT NULL,
  `month_12` double(8,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `business`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `business` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `versao_obrigatoria` varchar(191) DEFAULT NULL,
  `versao_disponivel` varchar(191) DEFAULT NULL,
  `caminho_banco_servidor` varchar(191) DEFAULT NULL,
  `dt_ultimo_acesso` timestamp NULL DEFAULT NULL,
  `is_officeimpresso` tinyint(1) NOT NULL DEFAULT 0,
  `officeimpresso_bloqueado` tinyint(1) NOT NULL DEFAULT 0,
  `legacy_origin` varchar(32) DEFAULT NULL COMMENT 'Procedência legacy: ''officeimpresso''|''wr2''|''cowork''|null. Usado por HandleInertiaRequests pra default de viewMode da Lista de Vendas (ADR 0136).',
  `os_default_per_line` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Default mode pra criar OS a partir de venda: false=1 OS venda toda (Martinho), true=1 OS por linha (ComunicacaoVisual). CriarOsPorVendaService::criar() lê quando mode=''auto''.',
  `name` varchar(191) NOT NULL,
  `uuid` char(36) DEFAULT NULL,
  `currency_id` int(10) unsigned NOT NULL,
  `start_date` date DEFAULT NULL,
  `tax_number_1` varchar(100) DEFAULT NULL,
  `tax_label_1` varchar(10) DEFAULT NULL,
  `tax_number_2` varchar(100) DEFAULT NULL,
  `tax_label_2` varchar(10) DEFAULT NULL,
  `code_label_1` varchar(191) DEFAULT NULL,
  `code_1` varchar(191) DEFAULT NULL,
  `code_label_2` varchar(191) DEFAULT NULL,
  `code_2` varchar(191) DEFAULT NULL,
  `default_sales_tax` int(10) unsigned DEFAULT NULL,
  `default_profit_percent` double(5,2) NOT NULL DEFAULT 0.00,
  `owner_id` int(10) unsigned NOT NULL,
  `time_zone` varchar(191) NOT NULL DEFAULT 'America/Sao_Paulo',
  `fy_start_month` tinyint(4) NOT NULL DEFAULT 1,
  `accounting_method` enum('fifo','lifo','avco') NOT NULL DEFAULT 'fifo',
  `default_sales_discount` decimal(5,2) DEFAULT NULL,
  `sell_price_tax` enum('includes','excludes') NOT NULL DEFAULT 'includes',
  `logo` varchar(191) DEFAULT NULL,
  `sku_prefix` varchar(191) DEFAULT NULL,
  `enable_product_expiry` tinyint(1) NOT NULL DEFAULT 0,
  `expiry_type` enum('add_expiry','add_manufacturing') NOT NULL DEFAULT 'add_expiry',
  `on_product_expiry` enum('keep_selling','stop_selling','auto_delete') NOT NULL DEFAULT 'keep_selling',
  `stop_selling_before` int(11) NOT NULL COMMENT 'Stop selling expied item n days before expiry',
  `enable_tooltip` tinyint(1) NOT NULL DEFAULT 1,
  `purchase_in_diff_currency` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Allow purchase to be in different currency then the business currency',
  `purchase_currency_id` int(10) unsigned DEFAULT NULL,
  `p_exchange_rate` decimal(20,3) NOT NULL DEFAULT 1.000,
  `transaction_edit_days` int(10) unsigned NOT NULL DEFAULT 30,
  `stock_expiry_alert_days` int(10) unsigned NOT NULL DEFAULT 30,
  `keyboard_shortcuts` text DEFAULT NULL,
  `pos_settings` text DEFAULT NULL,
  `essentials_settings` longtext DEFAULT NULL,
  `fiscal_settings` text DEFAULT NULL,
  `boleto_settings` text DEFAULT NULL,
  `manufacturing_settings` text DEFAULT NULL,
  `woocommerce_settings` text DEFAULT NULL,
  `woocommerce_api_settings` text DEFAULT NULL,
  `woocommerce_skipped_orders` text DEFAULT NULL,
  `woocommerce_wh_oc_secret` varchar(191) DEFAULT NULL,
  `woocommerce_wh_ou_secret` varchar(191) DEFAULT NULL,
  `woocommerce_wh_od_secret` varchar(191) DEFAULT NULL,
  `woocommerce_wh_or_secret` varchar(191) DEFAULT NULL,
  `weighing_scale_setting` text NOT NULL COMMENT 'used to store the configuration of weighing scale',
  `enable_brand` tinyint(1) NOT NULL DEFAULT 1,
  `enable_category` tinyint(1) NOT NULL DEFAULT 1,
  `enable_sub_category` tinyint(1) NOT NULL DEFAULT 1,
  `enable_price_tax` tinyint(1) NOT NULL DEFAULT 1,
  `enable_purchase_status` tinyint(1) DEFAULT 1,
  `enable_lot_number` tinyint(1) NOT NULL DEFAULT 0,
  `default_unit` int(11) DEFAULT NULL,
  `enable_sub_units` tinyint(1) NOT NULL DEFAULT 0,
  `enable_racks` tinyint(1) NOT NULL DEFAULT 0,
  `enable_row` tinyint(1) NOT NULL DEFAULT 0,
  `enable_position` tinyint(1) NOT NULL DEFAULT 0,
  `enable_editing_product_from_purchase` tinyint(1) NOT NULL DEFAULT 1,
  `sales_cmsn_agnt` enum('logged_in_user','user','cmsn_agnt') DEFAULT NULL,
  `item_addition_method` tinyint(1) NOT NULL DEFAULT 1,
  `enable_inline_tax` tinyint(1) NOT NULL DEFAULT 1,
  `currency_symbol_placement` enum('before','after') NOT NULL DEFAULT 'before',
  `enabled_modules` text DEFAULT NULL,
  `date_format` varchar(191) NOT NULL DEFAULT 'm/d/Y',
  `time_format` enum('12','24') NOT NULL DEFAULT '24',
  `currency_precision` tinyint(4) NOT NULL DEFAULT 2,
  `quantity_precision` tinyint(4) NOT NULL DEFAULT 2,
  `ref_no_prefixes` text DEFAULT NULL,
  `theme_color` char(20) NOT NULL DEFAULT 'purple',
  `created_by` int(11) DEFAULT NULL,
  `asset_settings` text DEFAULT NULL,
  `crm_settings` text DEFAULT NULL,
  `repair_settings` text DEFAULT NULL,
  `repair_jobsheet_settings` text DEFAULT NULL,
  `enable_rp` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'rp is the short form of reward points',
  `rp_name` varchar(191) DEFAULT NULL COMMENT 'rp is the short form of reward points',
  `amount_for_unit_rp` decimal(22,4) NOT NULL DEFAULT 1.0000 COMMENT 'rp is the short form of reward points',
  `min_order_total_for_rp` decimal(22,4) NOT NULL DEFAULT 1.0000 COMMENT 'rp is the short form of reward points',
  `max_rp_per_order` int(11) DEFAULT NULL COMMENT 'rp is the short form of reward points',
  `redeem_amount_per_unit_rp` decimal(22,4) NOT NULL DEFAULT 1.0000 COMMENT 'rp is the short form of reward points',
  `min_order_total_for_redeem` decimal(22,4) NOT NULL DEFAULT 1.0000 COMMENT 'rp is the short form of reward points',
  `min_redeem_point` int(11) DEFAULT NULL COMMENT 'rp is the short form of reward points',
  `max_redeem_point` int(11) DEFAULT NULL COMMENT 'rp is the short form of reward points',
  `rp_expiry_period` int(11) DEFAULT NULL COMMENT 'rp is the short form of reward points',
  `rp_expiry_type` enum('month','year') NOT NULL DEFAULT 'year' COMMENT 'rp is the short form of reward points',
  `email_settings` text DEFAULT NULL,
  `sms_settings` text DEFAULT NULL,
  `custom_labels` text DEFAULT NULL,
  `common_settings` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `razao_social` varchar(120) NOT NULL DEFAULT '*',
  `cnpj` varchar(20) NOT NULL DEFAULT '00.000.000/0000-00',
  `ie` varchar(15) NOT NULL DEFAULT '00000000000',
  `senha_certificado` varchar(100) NOT NULL DEFAULT '1234',
  `certificado` blob NOT NULL,
  `cidade_id` int(10) unsigned DEFAULT NULL,
  `rua` varchar(60) NOT NULL DEFAULT '*',
  `numero` varchar(10) NOT NULL DEFAULT '*',
  `bairro` varchar(30) NOT NULL DEFAULT '*',
  `cep` varchar(10) NOT NULL DEFAULT '00000-000',
  `telefone` varchar(14) NOT NULL DEFAULT '00 00000-0000',
  `ultimo_numero_nfe` int(11) NOT NULL DEFAULT 0,
  `ultimo_numero_nfce` int(11) NOT NULL DEFAULT 0,
  `ultimo_numero_cte` int(11) NOT NULL DEFAULT 0,
  `numero_serie_nfe` int(11) NOT NULL DEFAULT 1,
  `numero_serie_nfce` int(11) NOT NULL DEFAULT 1,
  `ambiente` int(11) NOT NULL DEFAULT 2,
  `regime` int(11) NOT NULL DEFAULT 1,
  `cst_csosn_padrao` int(11) NOT NULL DEFAULT 101,
  `cst_cofins_padrao` int(11) NOT NULL DEFAULT 49,
  `cst_pis_padrao` int(11) NOT NULL DEFAULT 49,
  `cst_ipi_padrao` int(11) NOT NULL DEFAULT 99,
  `perc_icms_padrao` decimal(5,2) NOT NULL DEFAULT 0.00,
  `perc_pis_padrao` decimal(5,2) NOT NULL DEFAULT 0.00,
  `perc_cofins_padrao` decimal(5,2) NOT NULL DEFAULT 0.00,
  `perc_ipi_padrao` decimal(5,2) NOT NULL DEFAULT 0.00,
  `ncm_padrao` varchar(12) NOT NULL DEFAULT '',
  `cfop_saida_estadual_padrao` varchar(4) NOT NULL DEFAULT '',
  `cfop_saida_inter_estadual_padrao` varchar(4) NOT NULL DEFAULT '',
  `csc` varchar(70) NOT NULL DEFAULT '',
  `csc_id` varchar(10) NOT NULL DEFAULT '',
  `officeimpresso_numerodemaquinas` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `business_owner_id_foreign` (`owner_id`),
  KEY `business_currency_id_foreign` (`currency_id`),
  KEY `business_cidade_id_foreign` (`cidade_id`),
  KEY `business_default_sales_tax_foreign` (`default_sales_tax`),
  KEY `business_legacy_origin_idx` (`legacy_origin`),
  CONSTRAINT `business_cidade_id_foreign` FOREIGN KEY (`cidade_id`) REFERENCES `cities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `business_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `business_default_sales_tax_foreign` FOREIGN KEY (`default_sales_tax`) REFERENCES `tax_rates` (`id`),
  CONSTRAINT `business_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `business_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `business_locations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `location_id` varchar(191) DEFAULT NULL,
  `name` varchar(256) NOT NULL,
  `cnpj` varchar(20) DEFAULT NULL,
  `razao_social` varchar(150) DEFAULT NULL,
  `nome_fantasia` varchar(150) DEFAULT NULL,
  `inscricao_estadual` varchar(30) DEFAULT NULL,
  `inscricao_municipal` varchar(30) DEFAULT NULL,
  `landmark` text DEFAULT NULL,
  `country` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `zip_code` char(7) NOT NULL,
  `invoice_scheme_id` int(10) unsigned NOT NULL,
  `sale_invoice_scheme_id` int(11) DEFAULT NULL,
  `invoice_layout_id` int(10) unsigned NOT NULL,
  `sale_invoice_layout_id` int(11) DEFAULT NULL,
  `selling_price_group_id` int(11) DEFAULT NULL,
  `print_receipt_on_invoice` tinyint(1) DEFAULT 1,
  `receipt_printer_type` enum('browser','printer') NOT NULL DEFAULT 'browser',
  `printer_id` int(11) DEFAULT NULL,
  `mobile` varchar(191) DEFAULT NULL,
  `alternate_number` varchar(191) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `website` varchar(191) DEFAULT NULL,
  `featured_products` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `default_payment_accounts` text DEFAULT NULL,
  `custom_field1` varchar(191) DEFAULT NULL,
  `custom_field2` varchar(191) DEFAULT NULL,
  `custom_field3` varchar(191) DEFAULT NULL,
  `custom_field4` varchar(191) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `business_locations_business_id_index` (`business_id`),
  KEY `business_locations_invoice_scheme_id_foreign` (`invoice_scheme_id`),
  KEY `business_locations_invoice_layout_id_foreign` (`invoice_layout_id`),
  KEY `business_locations_sale_invoice_layout_id_index` (`sale_invoice_layout_id`),
  KEY `business_locations_selling_price_group_id_index` (`selling_price_group_id`),
  KEY `business_locations_receipt_printer_type_index` (`receipt_printer_type`),
  KEY `business_locations_printer_id_index` (`printer_id`),
  KEY `business_locations_cnpj_index` (`cnpj`),
  CONSTRAINT `business_locations_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `business_locations_invoice_layout_id_foreign` FOREIGN KEY (`invoice_layout_id`) REFERENCES `invoice_layouts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `business_locations_invoice_scheme_id_foreign` FOREIGN KEY (`invoice_scheme_id`) REFERENCES `invoice_schemes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_denominations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_denominations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `amount` decimal(22,4) NOT NULL,
  `total_count` int(11) NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cash_denominations_model_type_model_id_index` (`model_type`,`model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_register_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_register_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cash_register_id` int(10) unsigned NOT NULL,
  `amount` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `pay_method` varchar(191) DEFAULT NULL,
  `type` enum('debit','credit') NOT NULL,
  `transaction_type` varchar(191) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cash_register_transactions_cash_register_id_foreign` (`cash_register_id`),
  KEY `cash_register_transactions_transaction_id_index` (`transaction_id`),
  KEY `cash_register_transactions_type_index` (`type`),
  KEY `cash_register_transactions_transaction_type_index` (`transaction_type`),
  CONSTRAINT `cash_register_transactions_cash_register_id_foreign` FOREIGN KEY (`cash_register_id`) REFERENCES `cash_registers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_registers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_registers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `location_id` int(11) DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `status` enum('close','open') NOT NULL DEFAULT 'open',
  `closed_at` datetime DEFAULT NULL,
  `closing_amount` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `total_card_slips` int(11) NOT NULL DEFAULT 0,
  `total_cheques` int(11) NOT NULL DEFAULT 0,
  `denominations` text DEFAULT NULL,
  `closing_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cash_registers_business_id_foreign` (`business_id`),
  KEY `cash_registers_user_id_foreign` (`user_id`),
  KEY `cash_registers_location_id_index` (`location_id`),
  CONSTRAINT `cash_registers_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_registers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `short_code` varchar(191) DEFAULT NULL,
  `parent_id` int(11) NOT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `woocommerce_cat_id` int(11) DEFAULT NULL,
  `category_type` varchar(191) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `slug` varchar(191) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `officeimpresso_codigo` int(11) DEFAULT NULL,
  `officeimpresso_dt_alteracao` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `categories_business_id_foreign` (`business_id`),
  KEY `categories_created_by_foreign` (`created_by`),
  KEY `categories_woocommerce_cat_id_index` (`woocommerce_cat_id`),
  KEY `categories_parent_id_index` (`parent_id`),
  CONSTRAINT `categories_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `categories_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categorizables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorizables` (
  `category_id` int(11) NOT NULL,
  `categorizable_type` varchar(191) NOT NULL,
  `categorizable_id` bigint(20) unsigned NOT NULL,
  KEY `categorizables_categorizable_type_categorizable_id_index` (`categorizable_type`,`categorizable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `channel_user_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `channel_user_access` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `channel_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `granted_by_user_id` int(10) unsigned NOT NULL,
  `granted_at` timestamp NOT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL COMMENT 'soft revoke — NULL = ativo. Preserva audit history.',
  `revoked_by_user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cua_channel_user_unq` (`channel_id`,`user_id`,`revoked_at`),
  KEY `cua_biz_user_idx` (`business_id`,`user_id`),
  KEY `cua_biz_channel_idx` (`business_id`,`channel_id`),
  CONSTRAINT `cua_channel_fk` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `channels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `channel_uuid` char(36) NOT NULL COMMENT 'usado em webhook URL e Centrifugo channel granular',
  `label` varchar(80) NOT NULL COMMENT 'apelido livre: Comercial / Suporte / @lojaoficial / vendas@',
  `type` enum('whatsapp_meta','whatsapp_zapi','whatsapp_baileys','whatsapp_whatsmeow','instagram','messenger','email_imap','email_smtp','mercadolivre') NOT NULL,
  `status` enum('active','inactive','setup','disconnected','banned') NOT NULL DEFAULT 'setup',
  `display_identifier` varchar(100) DEFAULT NULL COMMENT 'preenchido após primeiro check bem-sucedido',
  `config_json` text DEFAULT NULL COMMENT 'encrypted JSON — shape depende de type (meta_phone_number_id, zapi_instance_id, baileys_phone_e164, email_host, ml_oauth_token, etc)',
  `handles_repair_status` tinyint(1) NOT NULL DEFAULT 0,
  `handles_billing` tinyint(1) NOT NULL DEFAULT 0,
  `handles_jana_bot` tinyint(1) NOT NULL DEFAULT 1,
  `handles_outbound_default` tinyint(1) NOT NULL DEFAULT 0,
  `bot_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `template_repair_ready_name` varchar(64) DEFAULT NULL,
  `template_repair_waiting_parts_name` varchar(64) DEFAULT NULL,
  `template_billing_due_name` varchar(64) DEFAULT NULL,
  `template_billing_paid_name` varchar(64) DEFAULT NULL,
  `channel_health` enum('healthy','degraded','disconnected','banned','never_checked') NOT NULL DEFAULT 'never_checked',
  `channel_health_consecutive_failures` int(10) unsigned NOT NULL DEFAULT 0,
  `last_health_check_at` timestamp NULL DEFAULT NULL,
  `last_health_message` text DEFAULT NULL,
  `lgpd_acknowledged_at` timestamp NULL DEFAULT NULL,
  `lgpd_acknowledged_by_user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `channels_channel_uuid_unique` (`channel_uuid`),
  UNIQUE KEY `channels_biz_type_id_unq` (`business_id`,`type`,`display_identifier`),
  KEY `channels_biz_idx` (`business_id`),
  KEY `channels_type_health_idx` (`type`,`channel_health`),
  KEY `channels_display_identifier_type_idx` (`display_identifier`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chart_of_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chart_of_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `business_id` int(11) NOT NULL,
  `currency_id` int(11) NOT NULL DEFAULT 133,
  `payment_type_id` bigint(20) unsigned NOT NULL DEFAULT 1,
  `account_subtype_id` bigint(20) unsigned DEFAULT NULL,
  `detail_type_id` bigint(20) unsigned DEFAULT NULL,
  `name` text DEFAULT NULL,
  `gl_code` int(11) DEFAULT NULL,
  `account_type` enum('asset','expense','equity','liability','income') NOT NULL DEFAULT 'asset',
  `opening_balance` decimal(11,2) NOT NULL DEFAULT 0.00,
  `reconcile_opening_balance` int(11) DEFAULT NULL,
  `allow_manual` tinyint(4) NOT NULL DEFAULT 0,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cidades` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(50) NOT NULL,
  `uf` varchar(2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `officeimpresso_codigo` int(11) DEFAULT NULL,
  `officeimpresso_dt_alteracao` timestamp NULL DEFAULT NULL,
  `business_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `cidades_business_id_foreign` (`business_id`),
  CONSTRAINT `cidades_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(40) NOT NULL,
  `uf` varchar(2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `officeimpresso_codigo` varchar(15) DEFAULT NULL,
  `business_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cities_business_id_index` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clients_feedbacks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients_feedbacks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `contact_id` bigint(20) unsigned DEFAULT NULL,
  `source_message_id` bigint(20) unsigned DEFAULT NULL,
  `conversation_id` bigint(20) unsigned DEFAULT NULL,
  `persona_slug` varchar(80) DEFAULT NULL,
  `cliente_slug` varchar(80) DEFAULT NULL,
  `signature` varchar(40) DEFAULT NULL,
  `relevance_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `relevance_score_at` timestamp NULL DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `canal` varchar(32) NOT NULL DEFAULT 'whatsapp',
  `literal` text NOT NULL,
  `contexto` text DEFAULT NULL,
  `modulo_afetado` varchar(80) DEFAULT NULL,
  `tela_afetada` varchar(160) DEFAULT NULL,
  `acao_afetada` varchar(80) DEFAULT NULL,
  `job` varchar(255) DEFAULT NULL,
  `motivacao_tipo` varchar(24) DEFAULT NULL,
  `workaround_o_que_faz` varchar(255) DEFAULT NULL,
  `workaround_custo` varchar(255) DEFAULT NULL,
  `severity_nng` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `primeira_vez` tinyint(1) NOT NULL DEFAULT 1,
  `recorrente_count` smallint(5) unsigned NOT NULL DEFAULT 1,
  `pattern_emergente` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'novo',
  `responder_cliente` text DEFAULT NULL,
  `mcp_task_id` varchar(80) DEFAULT NULL,
  `dev_task_requested` tinyint(1) NOT NULL DEFAULT 0,
  `data_resolvido` timestamp NULL DEFAULT NULL,
  `pr_link` varchar(255) DEFAULT NULL,
  `cliente_confirmou` tinyint(1) DEFAULT NULL,
  `re_reclamacao` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_biz_status` (`business_id`,`status`),
  KEY `idx_biz_persona` (`business_id`,`persona_slug`),
  KEY `idx_biz_severity` (`business_id`,`severity_nng`),
  KEY `clients_feedbacks_business_id_index` (`business_id`),
  KEY `clients_feedbacks_contact_id_index` (`contact_id`),
  KEY `clients_feedbacks_source_message_id_index` (`source_message_id`),
  KEY `clients_feedbacks_conversation_id_index` (`conversation_id`),
  KEY `clients_feedbacks_persona_slug_index` (`persona_slug`),
  KEY `clients_feedbacks_status_index` (`status`),
  KEY `idx_biz_dev_task_pending` (`business_id`,`dev_task_requested`),
  KEY `idx_biz_signature` (`business_id`,`signature`),
  KEY `idx_biz_relevance` (`business_id`,`relevance_score`),
  KEY `idx_biz_last_seen` (`business_id`,`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cms_page_metas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cms_page_metas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cms_page_id` bigint(20) unsigned NOT NULL,
  `meta_key` varchar(191) NOT NULL,
  `meta_value` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cms_page_metas_cms_page_id_foreign` (`cms_page_id`),
  CONSTRAINT `cms_page_metas_cms_page_id_foreign` FOREIGN KEY (`cms_page_id`) REFERENCES `cms_pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cms_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cms_pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(191) NOT NULL,
  `layout` varchar(191) DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `content` longtext DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `tags` varchar(191) DEFAULT NULL,
  `feature_image` varchar(191) DEFAULT NULL,
  `priority` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cms_site_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cms_site_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `site_key` varchar(191) NOT NULL,
  `site_value` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cnab_retorno_uploads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cnab_retorno_uploads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `payment_gateway_credential_id` bigint(20) unsigned NOT NULL,
  `arquivo_path` varchar(191) NOT NULL,
  `arquivo_nome_original` varchar(191) NOT NULL,
  `arquivo_tamanho_bytes` int(10) unsigned NOT NULL DEFAULT 0,
  `processado_em` timestamp NULL DEFAULT NULL,
  `qtd_paga` int(10) unsigned NOT NULL DEFAULT 0,
  `qtd_cancelada` int(10) unsigned NOT NULL DEFAULT 0,
  `qtd_vencida` int(10) unsigned NOT NULL DEFAULT 0,
  `qtd_registrada` int(10) unsigned NOT NULL DEFAULT 0,
  `erros_json` text DEFAULT NULL,
  `processado_por_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cnab_ret_biz_cred_idx` (`business_id`,`payment_gateway_credential_id`),
  KEY `cnab_retorno_uploads_business_id_index` (`business_id`),
  KEY `cnab_retorno_uploads_payment_gateway_credential_id_index` (`payment_gateway_credential_id`),
  KEY `cnab_retorno_uploads_processado_em_index` (`processado_em`),
  KEY `cnab_retorno_uploads_processado_por_user_id_index` (`processado_por_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cobrancas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cobrancas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `payment_gateway_credential_id` bigint(20) unsigned DEFAULT NULL,
  `gateway_external_id` varchar(191) DEFAULT NULL,
  `tipo` enum('boleto','pix_cob','pix_cobv','pix_recv','card') NOT NULL,
  `status` enum('pending','emitida','paga','vencida','cancelada','erro') NOT NULL DEFAULT 'pending',
  `valor_centavos` int(10) unsigned NOT NULL,
  `valor_pago_centavos` int(10) unsigned DEFAULT NULL,
  `vencimento` date NOT NULL,
  `paga_em` timestamp NULL DEFAULT NULL,
  `contact_id` bigint(20) unsigned DEFAULT NULL,
  `payer_cpf_cnpj` varchar(14) DEFAULT NULL,
  `payer_name` varchar(191) DEFAULT NULL,
  `payer_email` varchar(191) DEFAULT NULL,
  `descricao` text NOT NULL,
  `idempotency_key` varchar(191) NOT NULL,
  `origem_type` enum('sale','invoice','subscription_license','avulsa') DEFAULT NULL,
  `origem_id` bigint(20) unsigned DEFAULT NULL,
  `linha_digitavel` varchar(60) DEFAULT NULL,
  `codigo_barras` varchar(60) DEFAULT NULL,
  `pix_emv` text DEFAULT NULL,
  `pix_qr_code_path` varchar(191) DEFAULT NULL,
  `boleto_pdf_url` varchar(191) DEFAULT NULL,
  `nosso_numero` varchar(30) DEFAULT NULL,
  `forma_pagamento` enum('boleto','pix','cartao') DEFAULT NULL,
  `payload_gateway` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_gateway`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cobrancas_biz_idem_unique` (`business_id`,`idempotency_key`),
  KEY `cobrancas_biz_status_venc` (`business_id`,`status`,`vencimento`),
  KEY `cobrancas_origem_idx` (`origem_type`,`origem_id`),
  KEY `cobrancas_business_id_index` (`business_id`),
  KEY `cobrancas_payment_gateway_credential_id_index` (`payment_gateway_credential_id`),
  KEY `cobrancas_gateway_external_id_index` (`gateway_external_id`),
  KEY `cobrancas_tipo_index` (`tipo`),
  KEY `cobrancas_status_index` (`status`),
  KEY `cobrancas_contact_id_index` (`contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `componente_ctes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `componente_ctes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(30) NOT NULL,
  `valor` decimal(10,4) NOT NULL,
  `cte_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `componente_ctes_cte_id_foreign` (`cte_id`),
  CONSTRAINT `componente_ctes_cte_id_foreign` FOREIGN KEY (`cte_id`) REFERENCES `ctes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comvis_apontamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comvis_apontamentos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `os_id` bigint(20) unsigned NOT NULL,
  `orcamento_item_id` bigint(20) unsigned DEFAULT NULL,
  `operador_id` int(10) unsigned NOT NULL,
  `maquina` varchar(80) DEFAULT NULL,
  `iniciado_em` timestamp NOT NULL,
  `finalizado_em` timestamp NULL DEFAULT NULL,
  `duracao_segundos` int(10) unsigned DEFAULT NULL,
  `m2_produzido` decimal(10,3) DEFAULT NULL,
  `m2_orcado` decimal(10,3) DEFAULT NULL,
  `drift_percent` decimal(6,2) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_comvis_apt_business` (`business_id`),
  KEY `idx_comvis_apt_business_os` (`business_id`,`os_id`),
  KEY `idx_comvis_apt_operador` (`operador_id`),
  KEY `idx_comvis_apt_iniciado_em` (`iniciado_em`),
  KEY `fk_comvis_apt_os` (`os_id`),
  KEY `fk_comvis_apt_orcamento_item` (`orcamento_item_id`),
  CONSTRAINT `fk_comvis_apt_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comvis_apt_orcamento_item` FOREIGN KEY (`orcamento_item_id`) REFERENCES `comvis_orcamento_itens` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_comvis_apt_os` FOREIGN KEY (`os_id`) REFERENCES `comvis_os` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comvis_materiais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comvis_materiais` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `nome` varchar(150) NOT NULL,
  `categoria` varchar(50) NOT NULL,
  `unidade` enum('m2','unidade','metro_linear') NOT NULL DEFAULT 'm2',
  `gramatura_g_m2` int(11) DEFAULT NULL,
  `preco_custo_m2` decimal(10,2) NOT NULL DEFAULT 0.00,
  `preco_venda_m2` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estoque_minimo_m2` decimal(10,2) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `observacoes` text DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_comvis_mat_business` (`business_id`),
  KEY `idx_comvis_mat_business_ativo` (`business_id`,`ativo`),
  CONSTRAINT `fk_comvis_mat_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comvis_orcamento_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comvis_orcamento_itens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `orcamento_id` bigint(20) unsigned NOT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `descricao` varchar(255) NOT NULL,
  `largura_m` decimal(8,3) DEFAULT NULL,
  `altura_m` decimal(8,3) DEFAULT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 1,
  `area_m2` decimal(10,3) DEFAULT NULL,
  `preco_unitario_m2` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `observacoes` text DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_comvis_orc_itens_orc` (`orcamento_id`),
  KEY `idx_comvis_orc_itens_business` (`business_id`),
  KEY `fk_comvis_orc_itens_material` (`material_id`),
  CONSTRAINT `fk_comvis_orc_itens_material` FOREIGN KEY (`material_id`) REFERENCES `comvis_materiais` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_comvis_orc_itens_orcamento` FOREIGN KEY (`orcamento_id`) REFERENCES `comvis_orcamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comvis_orcamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comvis_orcamentos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `numero` varchar(20) NOT NULL,
  `contato_id` bigint(20) unsigned DEFAULT NULL,
  `vendedor_id` int(10) unsigned DEFAULT NULL,
  `data_emissao` date NOT NULL,
  `data_validade` date DEFAULT NULL,
  `status` enum('rascunho','enviado','aprovado','recusado','expirado') NOT NULL DEFAULT 'rascunho',
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `desconto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `extras` decimal(15,2) NOT NULL DEFAULT 0.00,
  `custo_instalacao` decimal(15,2) NOT NULL DEFAULT 0.00,
  `custo_entrega` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `observacoes` text DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comvis_orc_business_numero` (`business_id`,`numero`),
  KEY `idx_comvis_orc_business` (`business_id`),
  KEY `idx_comvis_orc_business_status` (`business_id`,`status`),
  KEY `idx_comvis_orc_data_emissao` (`data_emissao`),
  CONSTRAINT `fk_comvis_orc_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comvis_os`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comvis_os` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `orcamento_id` bigint(20) unsigned DEFAULT NULL,
  `numero` varchar(20) NOT NULL,
  `status_etapa` enum('arte','producao','finalizando','entrega','instalacao','concluida','cancelada') NOT NULL DEFAULT 'arte',
  `data_inicio` date DEFAULT NULL,
  `data_prazo` date DEFAULT NULL,
  `data_conclusao` date DEFAULT NULL,
  `vendedor_id` int(10) unsigned DEFAULT NULL,
  `responsavel_producao_id` int(10) unsigned DEFAULT NULL,
  `valor_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `observacoes` text DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comvis_os_business_numero` (`business_id`,`numero`),
  KEY `idx_comvis_os_business` (`business_id`),
  KEY `idx_comvis_os_business_etapa` (`business_id`,`status_etapa`),
  KEY `idx_comvis_os_data_prazo` (`data_prazo`),
  KEY `fk_comvis_os_orcamento` (`orcamento_id`),
  CONSTRAINT `fk_comvis_os_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comvis_os_orcamento` FOREIGN KEY (`orcamento_id`) REFERENCES `comvis_orcamentos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `condicaopagto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `condicaopagto` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(30) DEFAULT NULL,
  `tipo` char(1) DEFAULT NULL,
  `parcelas` int(11) DEFAULT NULL,
  `intervalo` int(11) DEFAULT NULL,
  `entrada` char(1) DEFAULT NULL,
  `desconto_acrescimo` double DEFAULT NULL,
  `tipopagto` varchar(50) DEFAULT NULL,
  `tipo_utilizacao` varchar(15) DEFAULT NULL,
  `perc_entrada` double DEFAULT NULL,
  `codplanocontas` varchar(30) DEFAULT NULL,
  `codplanocontas_pagto` varchar(30) DEFAULT NULL,
  `fator_comercial` double DEFAULT NULL,
  `ativo` char(1) DEFAULT NULL,
  `dt_alteracao` timestamp NULL DEFAULT NULL,
  `intervalo_mensal` char(1) DEFAULT NULL COMMENT 'DOM_BOOLEAN',
  `is_cartao` char(1) DEFAULT NULL,
  `pode_substituir_desconto_venda` char(1) DEFAULT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `officeimpresso_codigo` varchar(15) DEFAULT NULL,
  `officeimpresso_dt_alteracao` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `condicaopagto_business_id_foreign` (`business_id`),
  CONSTRAINT `condicaopagto_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contact_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_addresses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `contact_id` int(10) unsigned NOT NULL,
  `label` varchar(80) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `address_line_1` varchar(255) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `address_line_2` varchar(255) DEFAULT NULL,
  `neighborhood` varchar(120) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `state` varchar(191) DEFAULT NULL,
  `city_code` varchar(7) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_shipping` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contact_addresses_biz_contact_idx` (`business_id`,`contact_id`),
  KEY `contact_addresses_business_id_index` (`business_id`),
  KEY `contact_addresses_contact_id_index` (`contact_id`),
  CONSTRAINT `contact_addresses_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_addresses_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `city_id` int(10) unsigned DEFAULT NULL,
  `cpf_cnpj` varchar(20) DEFAULT NULL,
  `ie_rg` varchar(18) DEFAULT NULL,
  `consumidor_final` int(11) DEFAULT 1,
  `contribuinte` int(11) DEFAULT 1,
  `regime` varchar(191) DEFAULT NULL,
  `rua` varchar(80) DEFAULT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `bairro` varchar(40) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `type` varchar(191) NOT NULL,
  `tipo` enum('PF','PJ') DEFAULT NULL,
  `is_customer` tinyint(1) NOT NULL DEFAULT 0,
  `is_supplier` tinyint(1) NOT NULL DEFAULT 0,
  `is_employee` tinyint(1) NOT NULL DEFAULT 0,
  `is_representative` tinyint(1) NOT NULL DEFAULT 0,
  `is_other` tinyint(1) NOT NULL DEFAULT 0,
  `contact_type` varchar(191) DEFAULT NULL,
  `land_mark` varchar(191) DEFAULT NULL,
  `street_name` varchar(191) DEFAULT NULL,
  `building_number` varchar(191) DEFAULT NULL,
  `additional_number` varchar(191) DEFAULT NULL,
  `supplier_business_name` varchar(191) DEFAULT NULL,
  `name` varchar(191) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `email_billing` varchar(320) DEFAULT NULL,
  `email_nfe` varchar(320) DEFAULT NULL,
  `email_consent` tinyint(1) DEFAULT NULL,
  `consent_updated_at` timestamp NULL DEFAULT NULL,
  `contact_id` varchar(191) DEFAULT NULL,
  `legacy_id` varchar(32) DEFAULT NULL COMMENT 'Chave natural legacy (CNPJ normalizado p/ Martinho/v1404, ou EMPRESA.CODIGO p/ WR2). Bridge importer-officeimpresso.',
  `contact_status` varchar(191) NOT NULL DEFAULT 'active',
  `tax_number` varchar(191) DEFAULT NULL,
  `city` varchar(191) DEFAULT NULL,
  `state` varchar(191) DEFAULT NULL,
  `city_code` varchar(7) DEFAULT NULL,
  `country` varchar(191) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `address_line_1` varchar(191) DEFAULT NULL,
  `address_line_2` varchar(191) DEFAULT NULL,
  `mobile` varchar(191) NOT NULL,
  `whatsapp_consent` tinyint(1) DEFAULT NULL,
  `landline` varchar(191) DEFAULT NULL,
  `alternate_number` varchar(191) DEFAULT NULL,
  `pay_term_number` int(11) DEFAULT NULL,
  `pay_term_type` enum('days','months') DEFAULT NULL,
  `credit_limit` decimal(22,4) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `converted_by` int(11) DEFAULT NULL,
  `converted_on` datetime DEFAULT NULL,
  `balance` decimal(22,4) DEFAULT NULL,
  `total_rp` int(11) NOT NULL DEFAULT 0 COMMENT 'rp is the short form of reward points',
  `total_rp_used` int(11) NOT NULL DEFAULT 0 COMMENT 'rp is the short form of reward points',
  `total_rp_expired` int(11) NOT NULL DEFAULT 0 COMMENT 'rp is the short form of reward points',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `shipping_address` text DEFAULT NULL,
  `shipping_custom_field_details` longtext DEFAULT NULL,
  `is_export` tinyint(1) NOT NULL DEFAULT 0,
  `export_custom_field_1` varchar(191) DEFAULT NULL,
  `export_custom_field_2` varchar(191) DEFAULT NULL,
  `export_custom_field_3` varchar(191) DEFAULT NULL,
  `export_custom_field_4` varchar(191) DEFAULT NULL,
  `export_custom_field_5` varchar(191) DEFAULT NULL,
  `export_custom_field_6` varchar(191) DEFAULT NULL,
  `position` varchar(191) DEFAULT NULL,
  `customer_group_id` int(11) DEFAULT NULL,
  `crm_source` varchar(191) DEFAULT NULL,
  `crm_life_stage` varchar(191) DEFAULT NULL,
  `custom_field1` varchar(191) DEFAULT NULL,
  `custom_field2` varchar(191) DEFAULT NULL,
  `custom_field3` varchar(191) DEFAULT NULL,
  `custom_field4` varchar(191) DEFAULT NULL,
  `custom_field5` varchar(191) DEFAULT NULL,
  `custom_field6` varchar(191) DEFAULT NULL,
  `custom_field7` varchar(191) DEFAULT NULL,
  `custom_field8` varchar(191) DEFAULT NULL,
  `custom_field9` varchar(191) DEFAULT NULL,
  `custom_field10` varchar(191) DEFAULT NULL,
  `is_sincronizado` tinyint(1) DEFAULT NULL,
  `office_oimpresso_updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `rg` varchar(20) DEFAULT NULL,
  `inscricao_estadual` varchar(30) DEFAULT NULL,
  `inscricao_municipal` varchar(30) DEFAULT NULL,
  `indicador_ie` tinyint(3) unsigned DEFAULT NULL,
  `nome_fantasia` varchar(150) DEFAULT NULL,
  `suframa` varchar(20) DEFAULT NULL,
  `fantasia` varchar(255) DEFAULT NULL,
  `ie` varchar(20) DEFAULT NULL,
  `ind_ie_dest` tinyint(4) DEFAULT NULL,
  `nascimento` date DEFAULT NULL,
  `cargo` varchar(80) DEFAULT NULL,
  `contato` varchar(100) DEFAULT NULL COMMENT 'Nome do responsavel principal (PJ) — drawer IdentificacaoTab. Daniela 2026-05-27.',
  `tel2` varchar(20) DEFAULT NULL,
  `canal_preferido` enum('whatsapp','email','telefone','presencial') DEFAULT NULL,
  `tabela_preco_padrao` enum('padrao','varejo','atacado','parceiro') DEFAULT 'padrao',
  `pgto_padrao` enum('pix','boleto','cartao','dinheiro','transferencia') DEFAULT NULL,
  `obs_comercial` text DEFAULT NULL,
  `mensagem_venda` text DEFAULT NULL,
  `segmento` enum('varejo','atacado','agencia','corporativo','evento','governo') DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `vip` tinyint(1) NOT NULL DEFAULT 0,
  `favorito_users` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`favorito_users`)),
  `site_url` varchar(120) DEFAULT NULL,
  `neighborhood` varchar(120) DEFAULT NULL,
  `sefaz_cad_sit` varchar(20) DEFAULT NULL,
  `sefaz_cad_ind_cred_nfe` tinyint(4) DEFAULT NULL,
  `sefaz_cad_consultado_em` timestamp NULL DEFAULT NULL,
  `complemento` varchar(120) DEFAULT NULL,
  `bloqueado` tinyint(1) NOT NULL DEFAULT 0,
  `limite_desconto_percentual` decimal(5,2) DEFAULT NULL,
  `boleto_desconto_pontualidade_pct` decimal(5,2) DEFAULT NULL,
  `cobrar_custo_boleto` tinyint(1) NOT NULL DEFAULT 0,
  `fatura_previsao` date DEFAULT NULL,
  `prioridade_producao` tinyint(3) unsigned DEFAULT NULL,
  `iss_retido` tinyint(3) unsigned DEFAULT NULL,
  `aniversario_mmdd` varchar(5) DEFAULT NULL,
  `parent_contact_id` bigint(20) unsigned DEFAULT NULL,
  `sales_rep_contact_id` bigint(20) unsigned DEFAULT NULL,
  `primary_role` enum('customer','supplier','employee','representative') DEFAULT NULL,
  `situacao` varchar(30) DEFAULT NULL,
  `legacy_raw` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`legacy_raw`)),
  `officeimpresso_codigo` varchar(255) DEFAULT NULL,
  `officeimpresso_dt_alteracao` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_contacts_biz_tax` (`business_id`,`tax_number`),
  KEY `contacts_business_id_foreign` (`business_id`),
  KEY `contacts_created_by_foreign` (`created_by`),
  KEY `contacts_type_index` (`type`),
  KEY `contacts_contact_status_index` (`contact_status`),
  KEY `contacts_crm_source_index` (`crm_source`),
  KEY `contacts_crm_life_stage_index` (`crm_life_stage`),
  KEY `contacts_converted_by_index` (`converted_by`),
  KEY `contacts_city_id_foreign` (`city_id`),
  KEY `contacts_business_legacy_idx` (`business_id`,`legacy_id`),
  KEY `idx_contacts_biz_customer` (`business_id`,`is_customer`),
  KEY `idx_contacts_biz_supplier` (`business_id`,`is_supplier`),
  KEY `idx_contacts_biz_employee` (`business_id`,`is_employee`),
  KEY `idx_contacts_biz_representative` (`business_id`,`is_representative`),
  KEY `contacts_business_id_vip_index` (`business_id`,`vip`),
  KEY `idx_contacts_biz_bloqueado` (`business_id`,`bloqueado`),
  KEY `idx_contacts_biz_parent` (`business_id`,`parent_contact_id`),
  KEY `idx_contacts_biz_sales_rep` (`business_id`,`sales_rep_contact_id`),
  KEY `idx_contacts_biz_officeimpresso_codigo` (`business_id`,`officeimpresso_codigo`),
  KEY `idx_contacts_biz_other` (`business_id`,`is_other`),
  CONSTRAINT `contacts_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contacts_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contacts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `channel_id` bigint(20) unsigned NOT NULL,
  `contact_id` int(10) unsigned DEFAULT NULL COMMENT 'contacts.id se já cadastrado, NULL se provisional',
  `customer_external_id` varchar(150) NOT NULL COMMENT 'E.164 phone | fb_user_id | email | ml_buyer_id — discriminado pelo channel.type',
  `lid` varchar(100) DEFAULT NULL,
  `phone_e164` varchar(30) DEFAULT NULL,
  `bsuid` varchar(100) DEFAULT NULL,
  `contact_name` varchar(120) DEFAULT NULL COMMENT 'cache — UI mostra sem N+1 query contacts',
  `status` enum('open','awaiting_human','resolved','archived') NOT NULL DEFAULT 'open',
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `bot_handling` tinyint(1) NOT NULL DEFAULT 0,
  `is_blocked` tinyint(1) NOT NULL DEFAULT 0,
  `last_inbound_at` timestamp NULL DEFAULT NULL COMMENT 'última msg cliente — janela 24h Meta para WA',
  `last_outbound_at` timestamp NULL DEFAULT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL COMMENT 'max(in, out) — sort lista',
  `unread_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_message_preview` varchar(120) DEFAULT NULL,
  `last_message_direction` enum('inbound','outbound') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conv_biz_ch_ext_uniq` (`business_id`,`channel_id`,`customer_external_id`),
  KEY `conv_biz_lastmsg_idx` (`business_id`,`last_message_at`),
  KEY `conv_biz_status_idx` (`business_id`,`status`),
  KEY `conv_ch_status_idx` (`channel_id`,`status`),
  KEY `conversations_biz_lid_idx` (`business_id`,`lid`),
  KEY `conversations_biz_phone_idx` (`business_id`,`phone_e164`),
  KEY `conversations_biz_bsuid_idx` (`business_id`,`bsuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `copiloto_business_profile`;
/*!50001 DROP VIEW IF EXISTS `copiloto_business_profile`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `copiloto_business_profile` AS SELECT
 1 AS `id`,
  1 AS `business_id`,
  1 AS `profile_text`,
  1 AS `tokens_estimated`,
  1 AS `raw_context_tokens`,
  1 AS `gerado_em`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `copiloto_cache_semantico`;
/*!50001 DROP VIEW IF EXISTS `copiloto_cache_semantico`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `copiloto_cache_semantico` AS SELECT
 1 AS `id`,
  1 AS `cache_key`,
  1 AS `business_id`,
  1 AS `user_id`,
  1 AS `query_original`,
  1 AS `query_normalizada`,
  1 AS `query_embedding`,
  1 AS `resposta`,
  1 AS `metadata`,
  1 AS `hits`,
  1 AS `ultimo_hit_em`,
  1 AS `tokens_in`,
  1 AS `tokens_out`,
  1 AS `custo_brl_original`,
  1 AS `expira_em`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `copiloto_conversas`;
/*!50001 DROP VIEW IF EXISTS `copiloto_conversas`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `copiloto_conversas` AS SELECT
 1 AS `id`,
  1 AS `business_id`,
  1 AS `user_id`,
  1 AS `titulo`,
  1 AS `status`,
  1 AS `iniciada_em`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `copiloto_memoria_facts`;
/*!50001 DROP VIEW IF EXISTS `copiloto_memoria_facts`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `copiloto_memoria_facts` AS SELECT
 1 AS `id`,
  1 AS `business_id`,
  1 AS `user_id`,
  1 AS `fato`,
  1 AS `metadata`,
  1 AS `valid_from`,
  1 AS `valid_until`,
  1 AS `hits_count`,
  1 AS `ultimo_hit_em`,
  1 AS `core_memory`,
  1 AS `created_at`,
  1 AS `updated_at`,
  1 AS `deleted_at` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `copiloto_memoria_gabarito`;
/*!50001 DROP VIEW IF EXISTS `copiloto_memoria_gabarito`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `copiloto_memoria_gabarito` AS SELECT
 1 AS `id`,
  1 AS `business_id`,
  1 AS `categoria`,
  1 AS `subcategoria`,
  1 AS `pergunta`,
  1 AS `memoria_esperada_keys`,
  1 AS `resposta_esperada_pattern`,
  1 AS `contexto_setup`,
  1 AS `dificuldade`,
  1 AS `ativo`,
  1 AS `notas`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `copiloto_memoria_metricas`;
/*!50001 DROP VIEW IF EXISTS `copiloto_memoria_metricas`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `copiloto_memoria_metricas` AS SELECT
 1 AS `id`,
  1 AS `apurado_em`,
  1 AS `business_id`,
  1 AS `recall_at_3`,
  1 AS `precision_at_3`,
  1 AS `mrr`,
  1 AS `latencia_p95_ms`,
  1 AS `tokens_medio_interacao`,
  1 AS `memory_bloat_ratio`,
  1 AS `taxa_contradicoes_pct`,
  1 AS `cross_tenant_violations`,
  1 AS `faithfulness`,
  1 AS `answer_relevancy`,
  1 AS `context_precision`,
  1 AS `total_interacoes_dia`,
  1 AS `total_memorias_ativas`,
  1 AS `detalhes`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `copiloto_mensagens`;
/*!50001 DROP VIEW IF EXISTS `copiloto_mensagens`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `copiloto_mensagens` AS SELECT
 1 AS `id`,
  1 AS `conversa_id`,
  1 AS `role`,
  1 AS `content`,
  1 AS `tokens_in`,
  1 AS `tokens_out`,
  1 AS `created_at` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `copiloto_meta_apuracoes`;
/*!50001 DROP VIEW IF EXISTS `copiloto_meta_apuracoes`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `copiloto_meta_apuracoes` AS SELECT
 1 AS `id`,
  1 AS `meta_id`,
  1 AS `data_ref`,
  1 AS `valor_realizado`,
  1 AS `calculado_em`,
  1 AS `fonte_query_hash`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `copiloto_meta_fontes`;
/*!50001 DROP VIEW IF EXISTS `copiloto_meta_fontes`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `copiloto_meta_fontes` AS SELECT
 1 AS `id`,
  1 AS `meta_id`,
  1 AS `driver`,
  1 AS `config_json`,
  1 AS `cadencia`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `copiloto_meta_periodos`;
/*!50001 DROP VIEW IF EXISTS `copiloto_meta_periodos`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `copiloto_meta_periodos` AS SELECT
 1 AS `id`,
  1 AS `meta_id`,
  1 AS `tipo_periodo`,
  1 AS `data_ini`,
  1 AS `data_fim`,
  1 AS `valor_alvo`,
  1 AS `trajetoria`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `copiloto_metas`;
/*!50001 DROP VIEW IF EXISTS `copiloto_metas`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `copiloto_metas` AS SELECT
 1 AS `id`,
  1 AS `business_id`,
  1 AS `slug`,
  1 AS `nome`,
  1 AS `unidade`,
  1 AS `tipo_agregacao`,
  1 AS `ativo`,
  1 AS `criada_por_user_id`,
  1 AS `origem`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `copiloto_negative_cache`;
/*!50001 DROP VIEW IF EXISTS `copiloto_negative_cache`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `copiloto_negative_cache` AS SELECT
 1 AS `id`,
  1 AS `cache_key`,
  1 AS `business_id`,
  1 AS `user_id`,
  1 AS `query_normalizada`,
  1 AS `hits_negativos`,
  1 AS `expira_em`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `copiloto_sugestoes`;
/*!50001 DROP VIEW IF EXISTS `copiloto_sugestoes`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `copiloto_sugestoes` AS SELECT
 1 AS `id`,
  1 AS `conversa_id`,
  1 AS `meta_id`,
  1 AS `payload_json`,
  1 AS `escolhida_em`,
  1 AS `rejeitada_em`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sortname` varchar(191) NOT NULL,
  `name` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_call_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_call_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `call_type` varchar(191) DEFAULT NULL,
  `mobile_number` varchar(191) NOT NULL,
  `mobile_name` varchar(191) DEFAULT NULL,
  `contact_id` int(11) DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_call_logs_business_id_index` (`business_id`),
  KEY `crm_call_logs_user_id_index` (`user_id`),
  KEY `crm_call_logs_contact_id_index` (`contact_id`),
  KEY `crm_call_logs_created_by_index` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_campaigns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `campaign_type` enum('sms','email') NOT NULL DEFAULT 'email',
  `subject` varchar(191) DEFAULT NULL,
  `email_body` text DEFAULT NULL,
  `sms_body` text DEFAULT NULL,
  `sent_on` datetime DEFAULT NULL,
  `contact_ids` text NOT NULL,
  `additional_info` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_campaigns_business_id_foreign` (`business_id`),
  KEY `crm_campaigns_created_by_index` (`created_by`),
  CONSTRAINT `crm_campaigns_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_contact_person_commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_contact_person_commissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contact_person_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `commission_amount` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_deals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_deals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` bigint(20) unsigned NOT NULL,
  `contact_id` bigint(20) unsigned DEFAULT NULL,
  `proposal_id` bigint(20) unsigned DEFAULT NULL,
  `titulo` varchar(191) NOT NULL,
  `stage` enum('lead','qualificacao','proposta','negociacao','ganho','perdido') NOT NULL DEFAULT 'lead',
  `valor_estimado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `data_fechamento_prevista` date DEFAULT NULL,
  `owner_user_id` bigint(20) unsigned NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_followup_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_followup_invoices` (
  `follow_up_id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_lead_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_lead_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` int(10) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_lead_users_user_id_index` (`user_id`),
  KEY `crm_lead_users_contact_id_index` (`contact_id`),
  CONSTRAINT `crm_lead_users_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_marketplaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_marketplaces` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `marketplace` varchar(191) DEFAULT NULL,
  `site_key` varchar(191) DEFAULT NULL,
  `site_id` varchar(191) DEFAULT NULL,
  `assigned_users` text DEFAULT NULL,
  `crm_source_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_proposal_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_proposal_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `subject` text NOT NULL,
  `body` longtext NOT NULL,
  `cc` text DEFAULT NULL,
  `bcc` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_proposal_templates_business_id_foreign` (`business_id`),
  KEY `crm_proposal_templates_created_by_index` (`created_by`),
  CONSTRAINT `crm_proposal_templates_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_proposals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_proposals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `contact_id` int(10) unsigned NOT NULL,
  `subject` text NOT NULL,
  `body` longtext NOT NULL,
  `cc` text DEFAULT NULL,
  `bcc` text DEFAULT NULL,
  `sent_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_proposals_business_id_foreign` (`business_id`),
  KEY `crm_proposals_contact_id_foreign` (`contact_id`),
  KEY `crm_proposals_sent_by_index` (`sent_by`),
  CONSTRAINT `crm_proposals_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `crm_proposals_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_schedule_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_schedule_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `schedule_id` bigint(20) unsigned NOT NULL,
  `log_type` enum('call','sms','meeting','email') NOT NULL DEFAULT 'email',
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `subject` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_schedule_logs_schedule_id_foreign` (`schedule_id`),
  KEY `crm_schedule_logs_created_by_index` (`created_by`),
  CONSTRAINT `crm_schedule_logs_schedule_id_foreign` FOREIGN KEY (`schedule_id`) REFERENCES `crm_schedules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_schedule_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_schedule_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `schedule_id` bigint(20) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_schedule_users_schedule_id_foreign` (`schedule_id`),
  KEY `crm_schedule_users_user_id_index` (`user_id`),
  CONSTRAINT `crm_schedule_users_schedule_id_foreign` FOREIGN KEY (`schedule_id`) REFERENCES `crm_schedules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `contact_id` int(10) DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `status` varchar(191) DEFAULT NULL,
  `start_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `description` text DEFAULT NULL,
  `schedule_type` enum('call','sms','meeting','email') NOT NULL DEFAULT 'email',
  `followup_category_id` int(11) DEFAULT NULL,
  `allow_notification` tinyint(1) NOT NULL DEFAULT 1,
  `notify_via` text DEFAULT NULL,
  `notify_before` int(11) DEFAULT NULL,
  `notify_type` enum('minute','hour','day') NOT NULL DEFAULT 'hour',
  `created_by` int(11) NOT NULL,
  `is_recursive` tinyint(1) NOT NULL DEFAULT 0,
  `recursion_days` int(11) DEFAULT NULL,
  `followup_additional_info` text DEFAULT NULL,
  `follow_up_by` varchar(191) DEFAULT NULL,
  `follow_up_by_value` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_schedules_created_by_index` (`created_by`),
  KEY `crm_schedules_business_id_index` (`business_id`),
  KEY `crm_schedules_contact_id_index` (`contact_id`),
  KEY `crm_schedules_schedule_type_index` (`schedule_type`),
  KEY `crm_schedules_notify_type_index` (`notify_type`),
  CONSTRAINT `crm_schedules_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ctes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ctes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `chave_nfe` varchar(45) NOT NULL,
  `remetente_id` int(10) unsigned NOT NULL,
  `destinatario_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `natureza_id` int(10) unsigned NOT NULL,
  `tomador` int(11) NOT NULL,
  `municipio_envio` int(10) unsigned NOT NULL,
  `municipio_inicio` int(10) unsigned NOT NULL,
  `municipio_fim` int(10) unsigned NOT NULL,
  `logradouro_tomador` varchar(80) DEFAULT NULL,
  `numero_tomador` varchar(20) DEFAULT NULL,
  `bairro_tomador` varchar(40) DEFAULT NULL,
  `cep_tomador` varchar(10) DEFAULT NULL,
  `municipio_tomador` int(10) unsigned DEFAULT NULL,
  `valor_transporte` decimal(10,2) NOT NULL,
  `valor_receber` decimal(10,2) NOT NULL,
  `valor_carga` decimal(10,2) NOT NULL,
  `produto_predominante` varchar(30) NOT NULL,
  `data_previsata_entrega` date NOT NULL,
  `observacao` varchar(191) NOT NULL,
  `sequencia_cce` int(11) NOT NULL,
  `cte_numero` int(11) NOT NULL DEFAULT 0,
  `chave` varchar(48) NOT NULL,
  `path_xml` varchar(51) NOT NULL,
  `estado` varchar(20) NOT NULL,
  `data_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `retira` tinyint(1) NOT NULL,
  `detalhes_retira` varchar(100) NOT NULL,
  `modal` varchar(2) NOT NULL,
  `veiculo_id` int(10) unsigned NOT NULL,
  `tpDoc` varchar(2) NOT NULL,
  `descOutros` varchar(100) NOT NULL,
  `nDoc` int(11) NOT NULL,
  `vDocFisc` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ctes_business_id_foreign` (`business_id`),
  KEY `ctes_remetente_id_foreign` (`remetente_id`),
  KEY `ctes_destinatario_id_foreign` (`destinatario_id`),
  KEY `ctes_usuario_id_foreign` (`usuario_id`),
  KEY `ctes_natureza_id_foreign` (`natureza_id`),
  KEY `ctes_municipio_envio_foreign` (`municipio_envio`),
  KEY `ctes_municipio_inicio_foreign` (`municipio_inicio`),
  KEY `ctes_municipio_fim_foreign` (`municipio_fim`),
  KEY `ctes_municipio_tomador_foreign` (`municipio_tomador`),
  KEY `ctes_veiculo_id_foreign` (`veiculo_id`),
  CONSTRAINT `ctes_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ctes_destinatario_id_foreign` FOREIGN KEY (`destinatario_id`) REFERENCES `contacts` (`id`),
  CONSTRAINT `ctes_municipio_envio_foreign` FOREIGN KEY (`municipio_envio`) REFERENCES `cities` (`id`),
  CONSTRAINT `ctes_municipio_fim_foreign` FOREIGN KEY (`municipio_fim`) REFERENCES `cities` (`id`),
  CONSTRAINT `ctes_municipio_inicio_foreign` FOREIGN KEY (`municipio_inicio`) REFERENCES `cities` (`id`),
  CONSTRAINT `ctes_municipio_tomador_foreign` FOREIGN KEY (`municipio_tomador`) REFERENCES `cities` (`id`),
  CONSTRAINT `ctes_natureza_id_foreign` FOREIGN KEY (`natureza_id`) REFERENCES `natureza_operacaos` (`id`),
  CONSTRAINT `ctes_remetente_id_foreign` FOREIGN KEY (`remetente_id`) REFERENCES `contacts` (`id`),
  CONSTRAINT `ctes_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ctes_veiculo_id_foreign` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `country` varchar(100) NOT NULL,
  `currency` varchar(100) NOT NULL,
  `code` varchar(25) NOT NULL,
  `symbol` varchar(25) NOT NULL,
  `thousand_separator` varchar(10) NOT NULL,
  `decimal_separator` varchar(10) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `amount` double(5,2) NOT NULL,
  `price_calculation_type` varchar(50) NOT NULL DEFAULT 'percentage',
  `selling_price_group_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_groups_business_id_foreign` (`business_id`),
  KEY `customer_groups_created_by_index` (`created_by`),
  CONSTRAINT `customer_groups_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_memory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_memory` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `customer_external_id` varchar(40) NOT NULL COMMENT 'E.164 sem + (ex: 5548999872822) — chave do cliente no canal',
  `phone_normalized` varchar(20) DEFAULT NULL COMMENT 'Só dígitos do customer_external_id — match rápido contra contacts.mobile',
  `contact_id` int(10) unsigned DEFAULT NULL,
  `identity_match_method` varchar(24) DEFAULT NULL COMMENT 'exact|suffix_8|manual|ambiguous_picked_first|unknown',
  `identity_match_confidence` decimal(3,2) DEFAULT NULL COMMENT '0.00..1.00 — 1.0=unique match, 0.5=ambíguo, NULL=não tentado',
  `identity_match_at` timestamp NULL DEFAULT NULL,
  `display_name` varchar(120) DEFAULT NULL,
  `n_conversations` int(10) unsigned NOT NULL DEFAULT 0,
  `n_msgs_inbound` int(10) unsigned NOT NULL DEFAULT 0,
  `n_msgs_outbound` int(10) unsigned NOT NULL DEFAULT 0,
  `first_interaction_at` timestamp NULL DEFAULT NULL,
  `last_interaction_at` timestamp NULL DEFAULT NULL,
  `temas_recorrentes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '["nfe","boleto","atualizacao_bug"] — top 3-5 temas histórico 90d' CHECK (json_valid(`temas_recorrentes`)),
  `sentimento_score` decimal(3,2) DEFAULT NULL COMMENT '-1.00..+1.00 — média ponderada sentimento msgs inbound 90d',
  `churn_risk_score` decimal(3,2) DEFAULT NULL COMMENT '0.00..1.00 — heurística + ML futuro',
  `comunicacao_preferida` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '{"hora_pico":"14-17h","canal":"whatsapp","tom":"formal"}' CHECK (json_valid(`comunicacao_preferida`)),
  `notas_jana` text DEFAULT NULL COMMENT 'Notas livres acumuladas — max ~2KB',
  `notas_atualizada_em` timestamp NULL DEFAULT NULL,
  `flags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '[{"tipo":"vip","since":"2026-05-10","motivo":"alto LTV"}]' CHECK (json_valid(`flags`)),
  `consent_status` varchar(16) DEFAULT NULL COMMENT 'given|withdrawn|unknown — cache de contacts.whatsapp_consent',
  `erasure_requested_at` timestamp NULL DEFAULT NULL COMMENT 'LGPD Art. 18 — soft delete; purge job apaga após retention period',
  `last_rebuilt_at` timestamp NULL DEFAULT NULL,
  `rebuilt_via` varchar(24) DEFAULT NULL COMMENT 'backfill|cron_daily|listener|manual|webhook',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL COMMENT 'User.id do último funcionário que respondeu (outbound mais recente)',
  `most_active_user_id` int(10) unsigned DEFAULT NULL COMMENT 'User.id do funcionário com mais msgs outbound histórico',
  `most_active_user_count` int(10) unsigned DEFAULT NULL COMMENT 'N msgs outbound do most_active_user_id (pra ranking)',
  `reclamacoes_recentes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '[{date,msg_id,severity,preview}] — top 5 reclamações heurística 30d' CHECK (json_valid(`reclamacoes_recentes`)),
  `total_reclamacoes` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Count msgs flagged reclamação (heurística keywords) últimos 30d',
  `external_sources` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '[{source:"firebird_office",cliente_id,name,fone1,fone2,email,bloqueado}]' CHECK (json_valid(`external_sources`)),
  `external_sources_enriched_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_memory_biz_ext_uniq` (`business_id`,`customer_external_id`),
  KEY `customer_memory_biz_contact_idx` (`business_id`,`contact_id`),
  KEY `customer_memory_biz_lastint_idx` (`business_id`,`last_interaction_at`),
  KEY `customer_memory_biz_churn_idx` (`business_id`,`churn_risk_score`),
  KEY `customer_memory_phone_idx` (`phone_normalized`),
  KEY `customer_memory_contact_fk` (`contact_id`),
  KEY `cm_biz_assigned_idx` (`business_id`,`assigned_user_id`),
  KEY `cm_biz_reclamacoes_idx` (`business_id`,`total_reclamacoes`),
  CONSTRAINT `customer_memory_business_fk` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_memory_contact_fk` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cv_acabamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cv_acabamentos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `nome` varchar(150) NOT NULL,
  `tipo` enum('m_linear','unitario','m2','fixo') NOT NULL DEFAULT 'unitario',
  `preco` decimal(8,2) NOT NULL DEFAULT 0.00,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cv_acab_business` (`business_id`),
  KEY `idx_cv_acab_business_ativo` (`business_id`,`ativo`),
  CONSTRAINT `fk_cv_acab_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cv_instalacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cv_instalacoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `ordem_id` bigint(20) unsigned NOT NULL,
  `catalogo_id` bigint(20) unsigned DEFAULT NULL,
  `equipe_user_ids_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`equipe_user_ids_json`)),
  `data_agendada` timestamp NULL DEFAULT NULL,
  `data_realizada` timestamp NULL DEFAULT NULL,
  `endereco_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`endereco_json`)),
  `foto_pre_url` varchar(500) DEFAULT NULL,
  `foto_pos_url` varchar(500) DEFAULT NULL,
  `assinatura_cliente_url` varchar(500) DEFAULT NULL,
  `lat_lng_inicio` varchar(50) DEFAULT NULL,
  `lat_lng_fim` varchar(50) DEFAULT NULL,
  `nfse_emissao_id` bigint(20) unsigned DEFAULT NULL,
  `comissao_calculada` decimal(10,2) DEFAULT NULL,
  `status` enum('agendada','em_execucao','concluida','cancelada','aguardando_reagendamento') NOT NULL DEFAULT 'agendada',
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cv_inst_business` (`business_id`),
  KEY `idx_cv_inst_business_status` (`business_id`,`status`),
  KEY `idx_cv_inst_business_ordem` (`business_id`,`ordem_id`),
  KEY `idx_cv_inst_data_agendada` (`data_agendada`),
  KEY `fk_cv_inst_ordem` (`ordem_id`),
  KEY `fk_cv_inst_catalogo` (`catalogo_id`),
  CONSTRAINT `fk_cv_inst_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cv_inst_catalogo` FOREIGN KEY (`catalogo_id`) REFERENCES `cv_instalacoes_catalogo` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cv_inst_ordem` FOREIGN KEY (`ordem_id`) REFERENCES `cv_ordens_producao` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cv_instalacoes_catalogo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cv_instalacoes_catalogo` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `nome` varchar(150) NOT NULL,
  `preco_base` decimal(10,2) NOT NULL DEFAULT 0.00,
  `preco_m2` decimal(8,2) NOT NULL DEFAULT 0.00,
  `preco_km` decimal(8,2) NOT NULL DEFAULT 0.00,
  `exige_nr35` tinyint(1) NOT NULL DEFAULT 0,
  `ferramentas_necessarias_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ferramentas_necessarias_json`)),
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cv_inst_cat_business` (`business_id`),
  KEY `idx_cv_inst_cat_business_ativo` (`business_id`,`ativo`),
  CONSTRAINT `fk_cv_inst_cat_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cv_ordens_producao`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cv_ordens_producao` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `orcamento_id` bigint(20) unsigned DEFAULT NULL,
  `contato_id` bigint(20) unsigned DEFAULT NULL,
  `transaction_id` bigint(20) unsigned DEFAULT NULL,
  `current_stage_id` bigint(20) unsigned DEFAULT NULL,
  `substrato_id` bigint(20) unsigned DEFAULT NULL,
  `largura_m` decimal(8,3) DEFAULT NULL,
  `altura_m` decimal(8,3) DEFAULT NULL,
  `qtd` int(10) unsigned NOT NULL DEFAULT 1,
  `area_m2` decimal(10,3) DEFAULT NULL,
  `acabamento_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`acabamento_json`)),
  `instalacao_tipo` enum('cliente_busca','fachada_simples','fachada_andaime','fachada_nr35','entrega_apenas') NOT NULL DEFAULT 'cliente_busca',
  `endereco_instalacao_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`endereco_instalacao_json`)),
  `equipamentos_necessarios_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`equipamentos_necessarios_json`)),
  `arte_url` varchar(500) DEFAULT NULL,
  `arte_aprovada_em` timestamp NULL DEFAULT NULL,
  `prazo_prometido` date DEFAULT NULL,
  `estimated_completion` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `commission_distribution_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`commission_distribution_json`)),
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `extras` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cv_op_business_codigo` (`business_id`,`codigo`),
  KEY `idx_cv_op_business` (`business_id`),
  KEY `idx_cv_op_business_stage` (`business_id`,`current_stage_id`),
  KEY `idx_cv_op_business_substrato` (`business_id`,`substrato_id`),
  KEY `idx_cv_op_business_contato` (`business_id`,`contato_id`),
  KEY `idx_cv_op_prazo_prometido` (`prazo_prometido`),
  KEY `fk_cv_op_stage` (`current_stage_id`),
  KEY `fk_cv_op_substrato` (`substrato_id`),
  CONSTRAINT `fk_cv_op_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cv_op_stage` FOREIGN KEY (`current_stage_id`) REFERENCES `sale_process_stages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cv_op_substrato` FOREIGN KEY (`substrato_id`) REFERENCES `cv_substratos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cv_substratos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cv_substratos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `nome` varchar(150) NOT NULL,
  `categoria` enum('lona','vinil','adesivo','acm','tela','mdf','neon','letra_caixa','outro') NOT NULL DEFAULT 'outro',
  `gramatura_g_m2` int(10) unsigned DEFAULT NULL,
  `preco_custo_m2` decimal(10,2) NOT NULL DEFAULT 0.00,
  `preco_venda_m2` decimal(10,2) NOT NULL DEFAULT 0.00,
  `minimo_m2` decimal(8,3) DEFAULT NULL,
  `ncm` varchar(10) DEFAULT NULL,
  `cfop_padrao` varchar(4) DEFAULT NULL,
  `csosn_padrao` varchar(3) DEFAULT NULL,
  `fornecedor_id` bigint(20) unsigned DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cv_subs_business` (`business_id`),
  KEY `idx_cv_subs_business_ativo` (`business_id`,`ativo`),
  KEY `idx_cv_subs_business_categoria` (`business_id`,`categoria`),
  CONSTRAINT `fk_cv_subs_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dashboard_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dashboard_configurations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `created_by` int(11) NOT NULL,
  `name` varchar(191) NOT NULL,
  `color` varchar(191) NOT NULL,
  `configuration` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dashboard_configurations_business_id_foreign` (`business_id`),
  CONSTRAINT `dashboard_configurations_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `devolucaos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `devolucaos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` int(10) unsigned NOT NULL,
  `natureza_id` int(10) unsigned NOT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `valor_integral` decimal(10,2) NOT NULL,
  `valor_devolvido` decimal(10,2) NOT NULL,
  `motivo` varchar(100) NOT NULL,
  `observacao` varchar(50) NOT NULL,
  `estado` int(11) NOT NULL,
  `devolucao_parcial` tinyint(1) NOT NULL,
  `chave_nf_entrada` varchar(48) NOT NULL,
  `nNf` int(11) NOT NULL,
  `vFrete` decimal(10,2) NOT NULL,
  `vDesc` decimal(10,2) NOT NULL,
  `chave_gerada` varchar(44) NOT NULL,
  `numero_gerado` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `devolucaos_contact_id_foreign` (`contact_id`),
  KEY `devolucaos_natureza_id_foreign` (`natureza_id`),
  KEY `devolucaos_business_id_foreign` (`business_id`),
  CONSTRAINT `devolucaos_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `devolucaos_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`),
  CONSTRAINT `devolucaos_natureza_id_foreign` FOREIGN KEY (`natureza_id`) REFERENCES `natureza_operacaos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `discount_variations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `discount_variations` (
  `discount_id` int(11) NOT NULL,
  `variation_id` int(11) NOT NULL,
  KEY `discount_variations_discount_id_index` (`discount_id`),
  KEY `discount_variations_variation_id_index` (`variation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `discounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `discounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `business_id` int(11) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `priority` int(11) DEFAULT NULL,
  `discount_type` varchar(191) DEFAULT NULL,
  `discount_amount` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `spg` varchar(100) DEFAULT NULL COMMENT 'Applicable in specified selling price group only. Use of applicable_in_spg column is discontinued',
  `applicable_in_cg` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `discounts_business_id_index` (`business_id`),
  KEY `discounts_brand_id_index` (`brand_id`),
  KEY `discounts_category_id_index` (`category_id`),
  KEY `discounts_location_id_index` (`location_id`),
  KEY `discounts_priority_index` (`priority`),
  KEY `discounts_spg_index` (`spg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `docs_chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `docs_chat_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `role` enum('user','assistant','system') NOT NULL,
  `content` text NOT NULL,
  `module_context` varchar(64) DEFAULT NULL,
  `sources` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sources`)),
  `mode` enum('offline','ai') NOT NULL DEFAULT 'offline',
  `tokens_used` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `docs_chat_messages_business_id_user_id_created_at_index` (`business_id`,`user_id`,`created_at`),
  KEY `docs_chat_messages_business_id_index` (`business_id`),
  KEY `docs_chat_messages_user_id_index` (`user_id`),
  KEY `docs_chat_messages_session_id_index` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `docs_evidences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `docs_evidences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` bigint(20) unsigned NOT NULL,
  `source_id` bigint(20) unsigned NOT NULL,
  `module_target` varchar(64) DEFAULT NULL,
  `kind` varchar(24) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `content` text NOT NULL,
  `ai_confidence` decimal(3,2) DEFAULT NULL,
  `extracted_by_ai` tinyint(1) NOT NULL DEFAULT 0,
  `suggested_story_id` varchar(32) DEFAULT NULL,
  `suggested_rule_id` varchar(32) DEFAULT NULL,
  `triaged_by` bigint(20) unsigned DEFAULT NULL,
  `triaged_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `docs_evidences_business_id_status_index` (`business_id`,`status`),
  KEY `docs_evidences_module_target_kind_index` (`module_target`,`kind`),
  KEY `docs_evidences_business_id_index` (`business_id`),
  KEY `docs_evidences_source_id_index` (`source_id`),
  KEY `docs_evidences_module_target_index` (`module_target`),
  KEY `docs_evidences_status_index` (`status`),
  FULLTEXT KEY `docs_evidences_fulltext` (`content`,`notes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `docs_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `docs_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `evidence_id` bigint(20) unsigned NOT NULL,
  `requirement_id` bigint(20) unsigned NOT NULL,
  `role` varchar(16) NOT NULL DEFAULT 'supports',
  `linked_by` bigint(20) unsigned DEFAULT NULL,
  `linked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `docs_links_unique` (`evidence_id`,`requirement_id`,`role`),
  KEY `docs_links_evidence_id_index` (`evidence_id`),
  KEY `docs_links_requirement_id_index` (`requirement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `docs_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `docs_pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `path` varchar(191) NOT NULL,
  `component` varchar(255) NOT NULL,
  `module` varchar(64) NOT NULL,
  `status` enum('planejada','em-dev','implementada','deprecated') NOT NULL DEFAULT 'planejada',
  `stories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`stories`)),
  `rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rules`)),
  `adrs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`adrs`)),
  `tests` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tests`)),
  `file_path` varchar(500) NOT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `docs_pages_path_unique` (`path`),
  KEY `docs_pages_module_status_index` (`module`,`status`),
  KEY `docs_pages_module_index` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `docs_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `docs_requirements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` bigint(20) unsigned NOT NULL,
  `module_target` varchar(64) NOT NULL,
  `external_id` varchar(32) NOT NULL,
  `kind` varchar(16) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'draft',
  `implementado_em` varchar(500) DEFAULT NULL,
  `testado_em` varchar(500) DEFAULT NULL,
  `dod_total` int(11) NOT NULL DEFAULT 0,
  `dod_done` int(11) NOT NULL DEFAULT 0,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `docs_requirements_external_id_unique` (`external_id`),
  KEY `docs_requirements_module_target_kind_index` (`module_target`,`kind`),
  KEY `docs_requirements_status_module_target_index` (`status`,`module_target`),
  KEY `docs_requirements_business_id_index` (`business_id`),
  KEY `docs_requirements_module_target_index` (`module_target`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `docs_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `docs_sources` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `module_target` varchar(64) DEFAULT NULL,
  `type` varchar(16) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `storage_path` varchar(500) DEFAULT NULL,
  `source_url` varchar(500) DEFAULT NULL,
  `body_text` text DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `docs_sources_business_id_module_target_index` (`business_id`,`module_target`),
  KEY `docs_sources_type_created_at_index` (`type`,`created_at`),
  KEY `docs_sources_business_id_index` (`business_id`),
  KEY `docs_sources_created_by_index` (`created_by`),
  KEY `docs_sources_module_target_index` (`module_target`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `docs_validation_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `docs_validation_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_at` timestamp NOT NULL,
  `module` varchar(64) DEFAULT NULL,
  `issues_total` int(10) unsigned NOT NULL DEFAULT 0,
  `issues_critical` int(10) unsigned NOT NULL DEFAULT 0,
  `issues` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`issues`)),
  `health_score` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `docs_validation_runs_run_at_index` (`run_at`),
  KEY `docs_validation_runs_module_index` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_and_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_and_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `notable_id` int(11) NOT NULL,
  `notable_type` varchar(191) NOT NULL,
  `heading` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_and_notes_business_id_index` (`business_id`),
  KEY `document_and_notes_notable_id_index` (`notable_id`),
  KEY `document_and_notes_created_by_index` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_performance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_performance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL COMMENT 'FK users.id — NULL quando identidade só por heurística nome',
  `heuristic_name` varchar(60) DEFAULT NULL COMMENT 'Nome detectado via body *Nome:* — quando sender_user_id NULL',
  `display_name` varchar(120) DEFAULT NULL COMMENT 'Nome canônico — users.first_name ou heuristic_name',
  `n_msgs_total` int(10) unsigned NOT NULL DEFAULT 0,
  `n_conversations_atendidas` int(10) unsigned NOT NULL DEFAULT 0,
  `n_clientes_diferentes` int(10) unsigned NOT NULL DEFAULT 0,
  `tempo_resposta_mediana_s` int(10) unsigned DEFAULT NULL COMMENT 'Mediana segundos entre inbound anterior → outbound do atendente',
  `tempo_resposta_p90_s` int(10) unsigned DEFAULT NULL,
  `sla_breach_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Conversas com primeira resposta > SLA (default 4h)',
  `reclamacoes_recebidas` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Soma de total_reclamacoes dos clientes que esse atendente atendeu',
  `csat_avg` decimal(3,2) DEFAULT NULL COMMENT 'CSAT médio quando integração futura WhatsappCsatResponse pronta',
  `horas_ativas_distintas` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Count DISTINCT HOUR(created_at) — 0-24',
  `hora_pico` tinyint(3) unsigned DEFAULT NULL COMMENT 'Hora 0-23 com maior volume outbound do atendente',
  `dias_ativos_30d` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `primeira_atividade_at` timestamp NULL DEFAULT NULL,
  `ultima_atividade_at` timestamp NULL DEFAULT NULL,
  `temas_dominantes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '["nfe","boleto","caixa"] — inferido temas_recorrentes dos clientes atendidos' CHECK (json_valid(`temas_dominantes`)),
  `nota_geral` tinyint(3) unsigned DEFAULT NULL,
  `nota_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '{volume:25,diversidade:20,velocidade:25,profundidade:15,cobertura:10,engajamento:5}' CHECK (json_valid(`nota_breakdown`)),
  `nota_calculada_em` timestamp NULL DEFAULT NULL,
  `flags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '[{tipo:"top_performer",since},{tipo:"baixo_volume_30d"},{tipo:"ferias",until}]' CHECK (json_valid(`flags`)),
  `last_rebuilt_at` timestamp NULL DEFAULT NULL,
  `rebuilt_via` varchar(24) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ep_biz_user_idx` (`business_id`,`user_id`),
  KEY `ep_biz_nota_idx` (`business_id`,`nota_geral`),
  KEY `ep_biz_heur_idx` (`business_id`,`heuristic_name`),
  KEY `employee_performance_user_fk` (`user_id`),
  CONSTRAINT `employee_performance_business_fk` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_performance_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_allowances_and_deductions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_allowances_and_deductions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `description` varchar(191) NOT NULL,
  `type` enum('allowance','deduction') NOT NULL,
  `amount` decimal(22,4) NOT NULL,
  `amount_type` enum('fixed','percent') NOT NULL,
  `applicable_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `essentials_allowances_and_deductions_business_id_index` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_attendances` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `clock_in_time` datetime DEFAULT NULL,
  `clock_out_time` datetime DEFAULT NULL,
  `essentials_shift_id` int(11) DEFAULT NULL,
  `ip_address` varchar(191) DEFAULT NULL,
  `clock_in_note` text DEFAULT NULL,
  `clock_out_note` text DEFAULT NULL,
  `clock_in_location` text DEFAULT NULL,
  `clock_out_location` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `essentials_attendances_user_id_index` (`user_id`),
  KEY `essentials_attendances_business_id_index` (`business_id`),
  KEY `essentials_attendances_essentials_shift_id_index` (`essentials_shift_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_document_shares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_document_shares` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `value_type` enum('user','role') NOT NULL,
  `value` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `essentials_document_shares_document_id_index` (`document_id`),
  KEY `essentials_document_shares_value_type_index` (`value_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(191) DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `description` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_holidays` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `business_id` int(11) NOT NULL,
  `location_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `essentials_holidays_business_id_index` (`business_id`),
  KEY `essentials_holidays_location_id_index` (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_kb`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_kb` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` bigint(20) unsigned NOT NULL,
  `title` varchar(191) NOT NULL,
  `content` longtext DEFAULT NULL,
  `status` varchar(191) NOT NULL,
  `kb_type` varchar(191) NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL COMMENT 'id from essentials_kb table',
  `share_with` varchar(191) DEFAULT NULL COMMENT 'public, private, only_with',
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `essentials_kb_business_id_index` (`business_id`),
  KEY `essentials_kb_parent_id_index` (`parent_id`),
  KEY `essentials_kb_created_by_index` (`created_by`),
  CONSTRAINT `essentials_kb_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `essentials_kb` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_kb_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_kb_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kb_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `essentials_kb_users_kb_id_index` (`kb_id`),
  KEY `essentials_kb_users_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_leave_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_leave_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `leave_type` varchar(191) NOT NULL,
  `max_leave_count` int(11) DEFAULT NULL,
  `leave_count_interval` enum('month','year') DEFAULT NULL,
  `business_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `essentials_leave_types_business_id_index` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_leaves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_leaves` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `essentials_leave_type_id` int(11) DEFAULT NULL,
  `business_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `ref_no` varchar(191) DEFAULT NULL,
  `status` enum('pending','approved','cancelled') DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `essentials_leaves_essentials_leave_type_id_index` (`essentials_leave_type_id`),
  KEY `essentials_leaves_business_id_index` (`business_id`),
  KEY `essentials_leaves_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `location_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `essentials_messages_business_id_index` (`business_id`),
  KEY `essentials_messages_user_id_index` (`user_id`),
  KEY `essentials_messages_location_id_index` (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_payroll_group_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_payroll_group_transactions` (
  `payroll_group_id` bigint(20) unsigned NOT NULL,
  `transaction_id` int(11) NOT NULL,
  KEY `essentials_payroll_group_transactions_payroll_group_id_foreign` (`payroll_group_id`),
  CONSTRAINT `essentials_payroll_group_transactions_payroll_group_id_foreign` FOREIGN KEY (`payroll_group_id`) REFERENCES `essentials_payroll_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_payroll_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_payroll_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `location_id` int(11) DEFAULT NULL COMMENT 'payroll for work location',
  `name` varchar(191) NOT NULL,
  `status` varchar(191) NOT NULL,
  `payment_status` varchar(191) NOT NULL DEFAULT 'due',
  `gross_total` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_reminders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(191) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `repeat` enum('one_time','every_day','every_week','every_month') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `essentials_reminders_business_id_index` (`business_id`),
  KEY `essentials_reminders_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_shifts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `type` enum('fixed_shift','flexible_shift') NOT NULL DEFAULT 'fixed_shift',
  `business_id` int(11) NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_allowed_auto_clockout` tinyint(1) NOT NULL DEFAULT 0,
  `auto_clockout_time` time DEFAULT NULL,
  `holidays` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `essentials_shifts_type_index` (`type`),
  KEY `essentials_shifts_business_id_index` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_to_dos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_to_dos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `task` text NOT NULL,
  `date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `task_id` varchar(191) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(191) DEFAULT NULL,
  `estimated_hours` varchar(191) DEFAULT NULL,
  `priority` varchar(191) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `essentials_to_dos_status_index` (`status`),
  KEY `essentials_to_dos_priority_index` (`priority`),
  KEY `essentials_to_dos_created_by_index` (`created_by`),
  KEY `essentials_to_dos_business_id_index` (`business_id`),
  KEY `essentials_to_dos_task_id_index` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_todo_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_todo_comments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `comment` text NOT NULL,
  `task_id` int(11) NOT NULL,
  `comment_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `essentials_todo_comments_task_id_index` (`task_id`),
  KEY `essentials_todo_comments_comment_by_index` (`comment_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_todos_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_todos_users` (
  `todo_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_user_allowance_and_deductions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_user_allowance_and_deductions` (
  `user_id` int(11) NOT NULL,
  `allowance_deduction_id` int(11) NOT NULL,
  KEY `essentials_user_allowance_and_deductions_user_id_index` (`user_id`),
  KEY `allow_deduct_index` (`allowance_deduction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_user_sales_targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_user_sales_targets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `target_start` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `target_end` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `commission_percent` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `essentials_user_shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `essentials_user_shifts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `essentials_shift_id` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `essentials_user_shifts_user_id_index` (`user_id`),
  KEY `essentials_user_shifts_essentials_shift_id_index` (`essentials_shift_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `expense_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `code` varchar(191) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expense_categories_business_id_foreign` (`business_id`),
  CONSTRAINT `expense_categories_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feature_flag_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `feature_flag_audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `actor_id` int(10) unsigned DEFAULT NULL COMMENT 'users.id quando ação veio do painel web; null pra CLI/MCP server-side',
  `actor_label` varchar(80) NOT NULL COMMENT 'Identificador human-readable: web:email@x.com, cli:flag:set, mcp:flag-set',
  `flag_key` varchar(100) NOT NULL COMMENT 'GrowthBook feature key (ex: useV2SellsCreate)',
  `action` enum('rule_upsert','rule_remove','env_toggle','feature_create','feature_delete','default_value_change') NOT NULL,
  `environment` varchar(50) DEFAULT NULL COMMENT 'production | dev | staging — null se ação não tem escopo de env',
  `payload_before` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Snapshot relevante antes da mudança (ex: rules antigas)' CHECK (json_valid(`payload_before`)),
  `payload_after` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Snapshot relevante depois da mudança' CHECK (json_valid(`payload_after`)),
  `diff_summary` text DEFAULT NULL COMMENT 'Resumo human-readable da mudança (1 linha)',
  PRIMARY KEY (`id`),
  KEY `ffa_flag_ts_idx` (`flag_key`,`created_at`),
  KEY `ffa_actor_ts_idx` (`actor_id`,`created_at`),
  KEY `ffa_action_ts_idx` (`action`,`created_at`),
  KEY `feature_flag_audits_created_at_index` (`created_at`),
  CONSTRAINT `feature_flag_audits_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_bank_statement_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_bank_statement_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `conta_bancaria_id` int(10) unsigned DEFAULT NULL,
  `fitid` varchar(100) DEFAULT NULL COMMENT 'OFX FITID — identificador único da transação no banco',
  `data_movimento` date NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(15,4) NOT NULL COMMENT 'positivo = crédito, negativo = débito',
  `tipo` enum('credit','debit','fee','transfer','unknown') NOT NULL DEFAULT 'unknown',
  `memo` varchar(500) DEFAULT NULL COMMENT 'descrição complementar OFX MEMO',
  `status` enum('pendente','sugerido','conciliado','ignorado') NOT NULL DEFAULT 'pendente',
  `titulo_id` int(10) unsigned DEFAULT NULL COMMENT 'FK pro Titulo quando conciliado',
  `conciliado_by` int(10) unsigned DEFAULT NULL COMMENT 'FK users.id quem aprovou match',
  `conciliado_at` timestamp NULL DEFAULT NULL,
  `match_score` decimal(5,2) DEFAULT NULL COMMENT '0.00-1.00 confiança do match',
  `source_file` varchar(255) DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_fitid_per_biz` (`business_id`,`fitid`),
  KEY `fin_bank_statement_lines_business_id_status_index` (`business_id`,`status`),
  KEY `fin_bank_statement_lines_business_id_data_movimento_index` (`business_id`,`data_movimento`),
  KEY `fin_bank_statement_lines_titulo_id_foreign` (`titulo_id`),
  KEY `fin_bank_statement_lines_conta_bancaria_id_foreign` (`conta_bancaria_id`),
  KEY `fin_bank_statement_lines_business_id_index` (`business_id`),
  CONSTRAINT `fin_bank_statement_lines_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fin_bank_statement_lines_conta_bancaria_id_foreign` FOREIGN KEY (`conta_bancaria_id`) REFERENCES `fin_contas_bancarias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fin_bank_statement_lines_titulo_id_foreign` FOREIGN KEY (`titulo_id`) REFERENCES `fin_titulos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_boleto_remessas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_boleto_remessas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `titulo_id` int(10) unsigned NOT NULL,
  `conta_bancaria_id` int(10) unsigned NOT NULL,
  `nosso_numero` varchar(30) NOT NULL COMMENT 'Sequencial gerado pelo sistema; banco confirma no retorno',
  `linha_digitavel` varchar(60) NOT NULL COMMENT '47 ou 48 digitos — formato visual com pontos/espacos',
  `codigo_barras` char(44) NOT NULL,
  `valor_total` decimal(22,4) NOT NULL,
  `vencimento` date NOT NULL,
  `status` enum('gerado_mock','gerado','enviado','registrado','pago','vencido','cancelado') NOT NULL DEFAULT 'gerado_mock',
  `pdf_path` varchar(255) DEFAULT NULL COMMENT 'Caminho relativo ao storage; gerado sob demanda',
  `enviado_em` datetime DEFAULT NULL,
  `pago_em` datetime DEFAULT NULL,
  `strategy` varchar(30) NOT NULL COMMENT 'cnab_direct | gateway_asaas | gateway_iugu | hybrid',
  `idempotency_key` varchar(36) NOT NULL COMMENT 'Trava re-emissao do mesmo titulo (TECH-0001)',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_idempotency` (`business_id`,`titulo_id`,`idempotency_key`),
  KEY `fin_boleto_remessas_titulo_id_foreign` (`titulo_id`),
  KEY `fin_boleto_remessas_conta_bancaria_id_foreign` (`conta_bancaria_id`),
  KEY `idx_biz_status_venc` (`business_id`,`status`,`vencimento`),
  KEY `fin_boleto_remessas_business_id_index` (`business_id`),
  KEY `fin_boleto_remessas_status_index` (`status`),
  CONSTRAINT `fin_boleto_remessas_conta_bancaria_id_foreign` FOREIGN KEY (`conta_bancaria_id`) REFERENCES `fin_contas_bancarias` (`id`),
  CONSTRAINT `fin_boleto_remessas_titulo_id_foreign` FOREIGN KEY (`titulo_id`) REFERENCES `fin_titulos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_caixa_movimentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_caixa_movimentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `conta_bancaria_id` int(10) unsigned DEFAULT NULL,
  `tipo` enum('entrada','saida','ajuste','transferencia') NOT NULL,
  `valor` decimal(22,4) NOT NULL COMMENT 'Sempre positivo; tipo define o sinal contábil',
  `data` date NOT NULL,
  `saldo_apos` decimal(22,4) NOT NULL COMMENT 'Snapshot do saldo após este movimento',
  `origem_tipo` varchar(50) DEFAULT NULL COMMENT 'Ex: titulo_baixa, transferencia, manual',
  `origem_id` int(10) unsigned DEFAULT NULL,
  `descricao` varchar(255) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_business_data` (`business_id`,`data`),
  KEY `idx_conta_data` (`conta_bancaria_id`,`data`),
  KEY `idx_origem` (`origem_tipo`,`origem_id`),
  KEY `fin_caixa_movimentos_created_by_foreign` (`created_by`),
  KEY `fin_caixa_movimentos_business_id_index` (`business_id`),
  CONSTRAINT `fin_caixa_movimentos_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fin_caixa_movimentos_conta_bancaria_id_foreign` FOREIGN KEY (`conta_bancaria_id`) REFERENCES `fin_contas_bancarias` (`id`),
  CONSTRAINT `fin_caixa_movimentos_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_categorias` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cor` varchar(7) DEFAULT NULL COMMENT 'Hex: #FF6B6B',
  `plano_conta_id` int(10) unsigned DEFAULT NULL COMMENT 'Vínculo opcional ao plano contábil',
  `tipo` enum('receita','despesa','ambos') NOT NULL DEFAULT 'ambos',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fin_categorias_business_id_tipo_index` (`business_id`,`tipo`),
  KEY `fin_categorias_plano_conta_id_foreign` (`plano_conta_id`),
  KEY `fin_categorias_business_id_index` (`business_id`),
  CONSTRAINT `fin_categorias_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fin_categorias_plano_conta_id_foreign` FOREIGN KEY (`plano_conta_id`) REFERENCES `fin_planos_conta` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_contas_bancarias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_contas_bancarias` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL COMMENT 'FK 1-1 accounts.id (core UltimatePOS)',
  `banco_codigo` char(3) NOT NULL COMMENT 'FEBRABAN: 001=BB, 033=Santander, 104=Caixa, 237=Bradesco, 341=Itau, 748=Sicredi, 756=Bancoob, ...',
  `agencia` varchar(10) NOT NULL,
  `agencia_dv` char(2) DEFAULT NULL,
  `conta_dv` char(2) DEFAULT NULL COMMENT 'Numero da conta vem de accounts.account_number; aqui só o digito separado',
  `carteira` varchar(10) NOT NULL COMMENT 'Carteira CNAB — depende do banco (ex: BB=18, Itau=109, Sicoob=1)',
  `convenio` varchar(30) DEFAULT NULL COMMENT 'Convenio CNAB (BB/Sicoob/Caixa) — null para bancos sem',
  `codigo_cedente` varchar(30) DEFAULT NULL COMMENT 'Codigo cedente / beneficiário no banco — alguns bancos pedem',
  `variacao_carteira` varchar(10) DEFAULT NULL,
  `beneficiario_documento` varchar(18) NOT NULL COMMENT 'CPF ou CNPJ formatado (XX.XXX.XXX/XXXX-XX)',
  `beneficiario_razao_social` varchar(150) NOT NULL,
  `beneficiario_logradouro` varchar(150) DEFAULT NULL,
  `beneficiario_bairro` varchar(80) DEFAULT NULL,
  `beneficiario_cidade` varchar(80) DEFAULT NULL,
  `beneficiario_uf` char(2) DEFAULT NULL,
  `beneficiario_cep` char(9) DEFAULT NULL COMMENT 'Formato XXXXX-XXX',
  `certificado_path` varchar(255) DEFAULT NULL COMMENT 'Caminho relativo ao storage; null em modo mock',
  `certificado_password_encrypted` varchar(255) DEFAULT NULL,
  `ativo_para_boleto` tinyint(1) NOT NULL DEFAULT 1,
  `rb_gateway_credential_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK para rb_boleto_credentials — null quando conta não tem cobrança ativa',
  `payment_gateway_credential_id` bigint(20) unsigned DEFAULT NULL,
  `saldo_cached` decimal(15,2) DEFAULT NULL COMMENT 'Saldo sincronizado via API do banco (Inter/Asaas) — null = não sincronizado',
  `saldo_atualizado_em` timestamp NULL DEFAULT NULL COMMENT 'Quando o saldo foi sincronizado pela última vez',
  `tipo_conta` varchar(20) NOT NULL DEFAULT 'corrente' COMMENT 'corrente | poupanca | virtual_pj',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Specifics por banco (ex: PIX dict_key, webhook_url) — shape livre' CHECK (json_valid(`metadata`)),
  `legacy_source` varchar(50) DEFAULT NULL COMMENT 'Origem legacy: wr-comercial-delphi, bling, etc — null se cadastrada nativa no oimpresso',
  `legacy_id` varchar(100) DEFAULT NULL COMMENT 'PK original no sistema legacy (string pra acomodar tipos diversos)',
  `legacy_imported_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fin_contas_bancarias_account_id_unique` (`account_id`),
  UNIQUE KEY `uq_fin_cb_biz_source_legacy` (`business_id`,`legacy_source`,`legacy_id`),
  KEY `idx_biz_ativo` (`business_id`,`ativo_para_boleto`),
  KEY `fin_contas_bancarias_business_id_index` (`business_id`),
  KEY `fin_contas_bancarias_banco_codigo_index` (`banco_codigo`),
  KEY `fin_conta_rb_cred_idx` (`rb_gateway_credential_id`),
  KEY `fin_conta_pg_cred_idx` (`payment_gateway_credential_id`),
  CONSTRAINT `fin_conta_pg_cred_fk` FOREIGN KEY (`payment_gateway_credential_id`) REFERENCES `payment_gateway_credentials` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fin_conta_rb_cred_fk` FOREIGN KEY (`rb_gateway_credential_id`) REFERENCES `rb_boleto_credentials` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fin_contas_bancarias_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fin_contas_bancarias_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_extrato_lancamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_extrato_lancamentos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `conta_bancaria_id` int(10) unsigned NOT NULL,
  `origem` enum('api','ofx','manual') NOT NULL DEFAULT 'api' COMMENT 'Fase 2 ADR 0236 — origem do dado de extrato',
  `data` date NOT NULL,
  `valor` decimal(15,2) NOT NULL,
  `tipo` char(1) NOT NULL COMMENT 'C = credito, D = debito',
  `descricao` varchar(500) NOT NULL,
  `source_file` varchar(255) DEFAULT NULL COMMENT 'nome do arquivo OFX quando origem=ofx',
  `status` enum('pendente','sugerido','conciliado','ignorado') DEFAULT NULL,
  `titulo_id` int(10) unsigned DEFAULT NULL COMMENT 'FK pro Titulo quando conciliado (Fase 1 ADR 0236)',
  `match_score` decimal(5,2) DEFAULT NULL,
  `conciliado_by` int(10) unsigned DEFAULT NULL,
  `conciliado_at` timestamp NULL DEFAULT NULL,
  `contraparte_documento` varchar(20) DEFAULT NULL,
  `contraparte_nome` varchar(255) DEFAULT NULL,
  `idempotency_key` varchar(100) NOT NULL COMMENT 'Inter: idTransacao ou endToEndId; fallback hash do payload',
  `external_id` varchar(160) DEFAULT NULL COMMENT 'chave unificada: ofx:<fitid> / api:<idempotency_key>',
  `raw_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Response bruto do banco pra análise futura' CHECK (json_valid(`raw_payload`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fin_extrato_idem_unique` (`conta_bancaria_id`,`idempotency_key`),
  UNIQUE KEY `fin_extrato_external_unique` (`business_id`,`conta_bancaria_id`,`external_id`),
  KEY `fin_extrato_biz_data_idx` (`business_id`,`data`),
  KEY `fin_extrato_concil_status_idx` (`business_id`,`status`),
  KEY `fin_extrato_titulo_fk` (`titulo_id`),
  KEY `fin_extrato_external_id_idx` (`business_id`,`external_id`),
  CONSTRAINT `fin_extrato_biz_fk` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fin_extrato_conta_fk` FOREIGN KEY (`conta_bancaria_id`) REFERENCES `fin_contas_bancarias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fin_extrato_titulo_fk` FOREIGN KEY (`titulo_id`) REFERENCES `fin_titulos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_planos_conta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_planos_conta` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `codigo` varchar(20) NOT NULL COMMENT 'Ex: 1.1.01.001',
  `nome` varchar(100) NOT NULL,
  `tipo` enum('ativo','passivo','patrimonio','receita','despesa','custo') NOT NULL,
  `nivel` tinyint(3) unsigned NOT NULL COMMENT '1 = sintética raiz; folhas tipicamente 4',
  `parent_id` int(10) unsigned DEFAULT NULL,
  `natureza` enum('debito','credito') NOT NULL,
  `aceita_lancamento` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'false em contas sintéticas',
  `protegido` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Não pode ser deletado (Caixa, Receita Bruta...)',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fin_planos_conta_business_id_codigo_unique` (`business_id`,`codigo`),
  KEY `fin_planos_conta_business_id_tipo_index` (`business_id`,`tipo`),
  KEY `fin_planos_conta_parent_id_foreign` (`parent_id`),
  KEY `fin_planos_conta_business_id_index` (`business_id`),
  CONSTRAINT `fin_planos_conta_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fin_planos_conta_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `fin_planos_conta` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_titulo_anexos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_titulo_anexos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `titulo_id` int(10) unsigned NOT NULL,
  `nome` varchar(255) NOT NULL COMMENT 'nome original do arquivo',
  `path` varchar(500) NOT NULL COMMENT 'caminho relativo em storage/app/private/financeiro/anexos',
  `mime` varchar(100) DEFAULT NULL,
  `tamanho_bytes` int(10) unsigned DEFAULT NULL,
  `hash_sha256` varchar(64) DEFAULT NULL COMMENT 'idempotência de upload',
  `uploaded_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fin_titulo_anexos_business_id_titulo_id_index` (`business_id`,`titulo_id`),
  KEY `fin_titulo_anexos_business_id_hash_sha256_index` (`business_id`,`hash_sha256`),
  KEY `fin_titulo_anexos_titulo_id_foreign` (`titulo_id`),
  KEY `fin_titulo_anexos_business_id_index` (`business_id`),
  CONSTRAINT `fin_titulo_anexos_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fin_titulo_anexos_titulo_id_foreign` FOREIGN KEY (`titulo_id`) REFERENCES `fin_titulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_titulo_baixas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_titulo_baixas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `titulo_id` int(10) unsigned NOT NULL,
  `conta_bancaria_id` int(10) unsigned DEFAULT NULL,
  `valor_baixa` decimal(22,4) NOT NULL,
  `juros` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `multa` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `desconto` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `data_baixa` date NOT NULL,
  `meio_pagamento` enum('dinheiro','pix','boleto','cartao_credito','cartao_debito','transferencia','cheque','compensacao','outro') NOT NULL,
  `idempotency_key` char(36) NOT NULL COMMENT 'UUID gerado pelo frontend; protege contra dupla',
  `transaction_payment_id` int(10) unsigned DEFAULT NULL COMMENT 'FK soft -> transaction_payments.id',
  `estorno_de_id` int(10) unsigned DEFAULT NULL COMMENT 'Self-FK para estornos (ledger style)',
  `observacoes` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_baixa_idempotency` (`business_id`,`idempotency_key`),
  KEY `idx_titulo` (`titulo_id`),
  KEY `idx_business_data` (`business_id`,`data_baixa`),
  KEY `idx_conta_data` (`conta_bancaria_id`,`data_baixa`),
  KEY `fin_titulo_baixas_estorno_de_id_foreign` (`estorno_de_id`),
  KEY `fin_titulo_baixas_created_by_foreign` (`created_by`),
  KEY `fin_titulo_baixas_business_id_index` (`business_id`),
  CONSTRAINT `fin_titulo_baixas_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fin_titulo_baixas_conta_bancaria_id_foreign` FOREIGN KEY (`conta_bancaria_id`) REFERENCES `fin_contas_bancarias` (`id`),
  CONSTRAINT `fin_titulo_baixas_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_titulo_baixas_estorno_de_id_foreign` FOREIGN KEY (`estorno_de_id`) REFERENCES `fin_titulo_baixas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fin_titulo_baixas_titulo_id_foreign` FOREIGN KEY (`titulo_id`) REFERENCES `fin_titulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_titulo_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_titulo_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `titulo_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL COMMENT 'FK users.id — quem comentou (Eliana / Wagner / Bruna / ...)',
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_business_titulo` (`business_id`,`titulo_id`),
  KEY `idx_business_created` (`business_id`,`created_at`),
  KEY `fin_titulo_comments_titulo_id_foreign` (`titulo_id`),
  KEY `fin_titulo_comments_user_id_foreign` (`user_id`),
  CONSTRAINT `fin_titulo_comments_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fin_titulo_comments_titulo_id_foreign` FOREIGN KEY (`titulo_id`) REFERENCES `fin_titulos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fin_titulo_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_titulos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_titulos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `numero` varchar(20) NOT NULL COMMENT 'Sequencial business-isolado; lockForUpdate em geração',
  `legacy_id` varchar(32) DEFAULT NULL COMMENT 'Chave natural FINANCEIRO.CODIGO Delphi pra dedup importer.',
  `tipo` enum('receber','pagar') NOT NULL,
  `status` enum('aberto','parcial','quitado','cancelado') NOT NULL DEFAULT 'aberto',
  `aprovacao_status` enum('pendente','aprovado','rejeitado') DEFAULT NULL,
  `aprovado_by` int(10) unsigned DEFAULT NULL,
  `aprovado_at` timestamp NULL DEFAULT NULL,
  `aprovacao_motivo` varchar(500) DEFAULT NULL,
  `cliente_id` int(10) unsigned DEFAULT NULL COMMENT 'FK soft -> contacts.id',
  `cliente_descricao` varchar(255) DEFAULT NULL COMMENT 'Fallback se cliente não cadastrado',
  `valor_total` decimal(22,4) NOT NULL,
  `valor_aberto` decimal(22,4) NOT NULL COMMENT 'valor_total - sum(baixas.valor); auto via observer',
  `moeda` char(3) NOT NULL DEFAULT 'BRL',
  `emissao` date NOT NULL,
  `vencimento` date NOT NULL,
  `forma_pagamento` enum('dinheiro','pix','boleto','cartao_credito','cartao_debito','transferencia','cheque','compensacao','outro') DEFAULT NULL,
  `conta_bancaria_id` int(10) unsigned DEFAULT NULL,
  `competencia_mes` char(7) NOT NULL COMMENT 'YYYY-MM regime competência',
  `origem` enum('manual','venda','compra','despesa','recurring','folha','caixa') NOT NULL,
  `origem_id` int(10) unsigned DEFAULT NULL COMMENT 'transaction.id, recurring_invoice.id, etc.',
  `parcela_numero` tinyint(3) unsigned DEFAULT NULL,
  `parcela_total` tinyint(3) unsigned DEFAULT NULL,
  `titulo_pai_id` int(10) unsigned DEFAULT NULL COMMENT 'Self-FK para parcelas',
  `plano_conta_id` int(10) unsigned DEFAULT NULL,
  `categoria_id` int(10) unsigned DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Shape específico por origem (ex: nfe_chave)' CHECK (json_valid(`metadata`)),
  `created_by` int(10) unsigned NOT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `conferido_by` int(10) unsigned DEFAULT NULL COMMENT 'FK users.id — quem marcou como conferido (per-user audit)',
  `conferido_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp da conferência',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_titulo_origem` (`business_id`,`origem`,`origem_id`,`parcela_numero`),
  KEY `idx_business_status_venc` (`business_id`,`status`,`vencimento`),
  KEY `idx_business_tipo_status` (`business_id`,`tipo`,`status`),
  KEY `idx_business_cliente` (`business_id`,`cliente_id`),
  KEY `fin_titulos_plano_conta_id_foreign` (`plano_conta_id`),
  KEY `fin_titulos_categoria_id_foreign` (`categoria_id`),
  KEY `fin_titulos_titulo_pai_id_foreign` (`titulo_pai_id`),
  KEY `fin_titulos_created_by_foreign` (`created_by`),
  KEY `fin_titulos_business_id_index` (`business_id`),
  KEY `fin_titulos_business_legacy_idx` (`business_id`,`legacy_id`),
  KEY `fk_titulo_conferido_by` (`conferido_by`),
  KEY `idx_business_conferido` (`business_id`,`conferido_by`),
  KEY `fin_titulos_business_id_aprovacao_status_index` (`business_id`,`aprovacao_status`),
  KEY `idx_fin_titulos_conta` (`business_id`,`conta_bancaria_id`),
  CONSTRAINT `fin_titulos_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fin_titulos_categoria_id_foreign` FOREIGN KEY (`categoria_id`) REFERENCES `fin_categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fin_titulos_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_titulos_plano_conta_id_foreign` FOREIGN KEY (`plano_conta_id`) REFERENCES `fin_planos_conta` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fin_titulos_titulo_pai_id_foreign` FOREIGN KEY (`titulo_pai_id`) REFERENCES `fin_titulos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_titulo_conferido_by` FOREIGN KEY (`conferido_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gateway_webhook_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gateway_webhook_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `payment_gateway_credential_id` bigint(20) unsigned DEFAULT NULL,
  `gateway_key` varchar(20) NOT NULL,
  `evento` varchar(60) NOT NULL,
  `gateway_event_id` varchar(191) NOT NULL,
  `cobranca_id` bigint(20) unsigned DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `signature_valid` tinyint(1) NOT NULL DEFAULT 0,
  `processed_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gw_wh_biz_key_extid_unique` (`business_id`,`gateway_key`,`gateway_event_id`),
  KEY `gateway_webhook_events_business_id_index` (`business_id`),
  KEY `gateway_webhook_events_payment_gateway_credential_id_index` (`payment_gateway_credential_id`),
  KEY `gateway_webhook_events_gateway_key_index` (`gateway_key`),
  KEY `gateway_webhook_events_evento_index` (`evento`),
  KEY `gateway_webhook_events_cobranca_id_index` (`cobranca_id`),
  KEY `gateway_webhook_events_signature_valid_index` (`signature_valid`),
  KEY `gateway_webhook_events_processed_at_index` (`processed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_sub_taxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_sub_taxes` (
  `group_tax_id` int(10) unsigned NOT NULL,
  `tax_id` int(10) unsigned NOT NULL,
  KEY `group_sub_taxes_group_tax_id_foreign` (`group_tax_id`),
  KEY `group_sub_taxes_tax_id_foreign` (`tax_id`),
  CONSTRAINT `group_sub_taxes_group_tax_id_foreign` FOREIGN KEY (`group_tax_id`) REFERENCES `tax_rates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_sub_taxes_tax_id_foreign` FOREIGN KEY (`tax_id`) REFERENCES `tax_rates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inter_webhook_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inter_webhook_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `payment_gateway_credential_id` bigint(20) unsigned NOT NULL,
  `txid` varchar(64) NOT NULL,
  `endToEndId` varchar(64) DEFAULT NULL,
  `cobranca_id` bigint(20) unsigned DEFAULT NULL,
  `titulo_id` bigint(20) unsigned DEFAULT NULL,
  `gateway_webhook_event_id` bigint(20) unsigned DEFAULT NULL,
  `valor_centavos` int(11) DEFAULT NULL,
  `payer_cpf_cnpj_redacted` varchar(32) DEFAULT NULL,
  `data_pagamento` datetime DEFAULT NULL,
  `signature_valid` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(24) NOT NULL DEFAULT 'received',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `error_message` text DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `iwl_cred_txid_unique` (`payment_gateway_credential_id`,`txid`),
  KEY `iwl_biz_idx` (`business_id`),
  KEY `iwl_cred_idx` (`payment_gateway_credential_id`),
  KEY `iwl_cob_idx` (`cobranca_id`),
  KEY `iwl_titulo_idx` (`titulo_id`),
  KEY `inter_webhook_log_signature_valid_index` (`signature_valid`),
  KEY `inter_webhook_log_status_index` (`status`),
  KEY `inter_webhook_log_processed_at_index` (`processed_at`),
  CONSTRAINT `iwl_biz_fk` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `iwl_cred_fk` FOREIGN KEY (`payment_gateway_credential_id`) REFERENCES `payment_gateway_credentials` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_layouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_layouts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `header_text` text DEFAULT NULL,
  `invoice_no_prefix` varchar(191) DEFAULT NULL,
  `quotation_no_prefix` varchar(191) DEFAULT NULL,
  `invoice_heading` varchar(191) DEFAULT NULL,
  `sub_heading_line1` varchar(191) DEFAULT NULL,
  `sub_heading_line2` varchar(191) DEFAULT NULL,
  `sub_heading_line3` varchar(191) DEFAULT NULL,
  `sub_heading_line4` varchar(191) DEFAULT NULL,
  `sub_heading_line5` varchar(191) DEFAULT NULL,
  `invoice_heading_not_paid` varchar(191) DEFAULT NULL,
  `invoice_heading_paid` varchar(191) DEFAULT NULL,
  `quotation_heading` varchar(191) DEFAULT NULL,
  `sub_total_label` varchar(191) DEFAULT NULL,
  `discount_label` varchar(191) DEFAULT NULL,
  `tax_label` varchar(191) DEFAULT NULL,
  `total_label` varchar(191) DEFAULT NULL,
  `round_off_label` varchar(191) DEFAULT NULL,
  `total_due_label` varchar(191) DEFAULT NULL,
  `paid_label` varchar(191) DEFAULT NULL,
  `show_client_id` tinyint(1) NOT NULL DEFAULT 0,
  `client_id_label` varchar(191) DEFAULT NULL,
  `client_tax_label` varchar(191) DEFAULT NULL,
  `date_label` varchar(191) DEFAULT NULL,
  `date_time_format` varchar(191) DEFAULT NULL,
  `show_time` tinyint(1) NOT NULL DEFAULT 1,
  `show_brand` tinyint(1) NOT NULL DEFAULT 0,
  `show_sku` tinyint(1) NOT NULL DEFAULT 1,
  `show_cat_code` tinyint(1) NOT NULL DEFAULT 1,
  `show_expiry` tinyint(1) NOT NULL DEFAULT 0,
  `show_lot` tinyint(1) NOT NULL DEFAULT 0,
  `show_image` tinyint(1) NOT NULL DEFAULT 0,
  `show_sale_description` tinyint(1) NOT NULL DEFAULT 0,
  `sales_person_label` varchar(191) DEFAULT NULL,
  `show_sales_person` tinyint(1) NOT NULL DEFAULT 0,
  `table_product_label` varchar(191) DEFAULT NULL,
  `table_qty_label` varchar(191) DEFAULT NULL,
  `table_unit_price_label` varchar(191) DEFAULT NULL,
  `table_subtotal_label` varchar(191) DEFAULT NULL,
  `cat_code_label` varchar(191) DEFAULT NULL,
  `logo` varchar(191) DEFAULT NULL,
  `show_logo` tinyint(1) NOT NULL DEFAULT 0,
  `show_business_name` tinyint(1) NOT NULL DEFAULT 0,
  `show_location_name` tinyint(1) NOT NULL DEFAULT 1,
  `show_landmark` tinyint(1) NOT NULL DEFAULT 1,
  `show_city` tinyint(1) NOT NULL DEFAULT 1,
  `show_state` tinyint(1) NOT NULL DEFAULT 1,
  `show_zip_code` tinyint(1) NOT NULL DEFAULT 1,
  `show_country` tinyint(1) NOT NULL DEFAULT 1,
  `show_mobile_number` tinyint(1) NOT NULL DEFAULT 1,
  `show_alternate_number` tinyint(1) NOT NULL DEFAULT 0,
  `show_email` tinyint(1) NOT NULL DEFAULT 0,
  `show_tax_1` tinyint(1) NOT NULL DEFAULT 1,
  `show_tax_2` tinyint(1) NOT NULL DEFAULT 0,
  `show_barcode` tinyint(1) NOT NULL DEFAULT 0,
  `show_payments` tinyint(1) NOT NULL DEFAULT 0,
  `show_customer` tinyint(1) NOT NULL DEFAULT 0,
  `customer_label` varchar(191) DEFAULT NULL,
  `commission_agent_label` varchar(191) DEFAULT NULL,
  `show_commission_agent` tinyint(1) NOT NULL DEFAULT 0,
  `show_reward_point` tinyint(1) NOT NULL DEFAULT 0,
  `highlight_color` varchar(10) DEFAULT NULL,
  `footer_text` text DEFAULT NULL,
  `module_info` text DEFAULT NULL,
  `common_settings` text DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `business_id` int(10) unsigned NOT NULL,
  `show_letter_head` tinyint(1) NOT NULL DEFAULT 0,
  `letter_head` varchar(191) DEFAULT NULL,
  `show_qr_code` tinyint(1) NOT NULL DEFAULT 0,
  `qr_code_fields` text DEFAULT NULL,
  `design` varchar(190) DEFAULT 'classic',
  `cn_heading` varchar(191) DEFAULT NULL COMMENT 'cn = credit note',
  `cn_no_label` varchar(191) DEFAULT NULL,
  `cn_amount_label` varchar(191) DEFAULT NULL,
  `table_tax_headings` text DEFAULT NULL,
  `show_previous_bal` tinyint(1) NOT NULL DEFAULT 0,
  `prev_bal_label` varchar(191) DEFAULT NULL,
  `change_return_label` varchar(191) DEFAULT NULL,
  `product_custom_fields` text DEFAULT NULL,
  `contact_custom_fields` text DEFAULT NULL,
  `location_custom_fields` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_layouts_business_id_foreign` (`business_id`),
  CONSTRAINT `invoice_layouts_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_schemes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_schemes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `scheme_type` enum('blank','year') NOT NULL,
  `number_type` varchar(100) NOT NULL DEFAULT 'sequential',
  `prefix` varchar(191) DEFAULT NULL,
  `start_number` int(11) DEFAULT NULL,
  `invoice_count` int(11) NOT NULL DEFAULT 0,
  `total_digits` int(11) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_schemes_business_id_foreign` (`business_id`),
  KEY `invoice_schemes_scheme_type_index` (`scheme_type`),
  KEY `invoice_schemes_number_type_index` (`number_type`),
  CONSTRAINT `invoice_schemes_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `item_devolucaos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_devolucaos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod` varchar(10) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `ncm` varchar(10) NOT NULL,
  `cfop` varchar(10) NOT NULL,
  `codBarras` varchar(13) NOT NULL,
  `valor_unit` decimal(10,2) NOT NULL,
  `quantidade` decimal(8,2) NOT NULL,
  `item_parcial` tinyint(1) NOT NULL,
  `unidade_medida` varchar(8) NOT NULL,
  `devolucao_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_devolucaos_devolucao_id_foreign` (`devolucao_id`),
  CONSTRAINT `item_devolucaos_devolucao_id_foreign` FOREIGN KEY (`devolucao_id`) REFERENCES `devolucaos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `item_dves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_dves` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `numero_nfe` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_dves_business_id_foreign` (`business_id`),
  CONSTRAINT `item_dves_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jana_business_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jana_business_profile` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL COMMENT '1 profile por business — UNIQUE pra updateOrInsert',
  `profile_text` text NOT NULL COMMENT 'Narrativa compacta destilada pelo LLM (~200 tokens)',
  `tokens_estimated` int(10) unsigned NOT NULL DEFAULT 0,
  `raw_context_tokens` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Tokens dos dados crus que originaram (pra calcular compression)',
  `gerado_em` timestamp NULL DEFAULT NULL COMMENT 'Última vez que o LLM destilou — usar pra TTL',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `copiloto_business_profile_business_id_unique` (`business_id`),
  KEY `cbp_gerado_idx` (`gerado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jana_cache_semantico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jana_cache_semantico` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cache_key` char(64) NOT NULL COMMENT 'SHA256(biz + user + query_normalizada)',
  `business_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `query_original` text NOT NULL COMMENT 'Texto original do user (max 5000 chars)',
  `query_normalizada` text NOT NULL COMMENT 'Normalizada pra comparação fuzzy (lowercase + sem acentos + sem espaços extras)',
  `query_embedding` blob DEFAULT NULL COMMENT '1536 floats × 4 bytes = 6KB (text-embedding-3-small)',
  `resposta` mediumtext NOT NULL COMMENT 'Resposta completa do LLM (markdown)',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Modelo, tokens originais, contexto recall, etc.' CHECK (json_valid(`metadata`)),
  `hits` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Quantas vezes essa entrada foi reutilizada (cache hit)',
  `ultimo_hit_em` timestamp NULL DEFAULT NULL,
  `tokens_in` int(10) unsigned DEFAULT NULL,
  `tokens_out` int(10) unsigned DEFAULT NULL,
  `custo_brl_original` decimal(10,6) DEFAULT NULL COMMENT 'Custo da PRIMEIRA chamada (sem cache). Hits subsequentes economizam isso.',
  `expira_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `copiloto_cache_semantico_cache_key_unique` (`cache_key`),
  KEY `cs_biz_user_exp_idx` (`business_id`,`user_id`,`expira_em`),
  KEY `copiloto_cache_semantico_business_id_index` (`business_id`),
  KEY `copiloto_cache_semantico_user_id_index` (`user_id`),
  KEY `copiloto_cache_semantico_expira_em_index` (`expira_em`),
  FULLTEXT KEY `cs_query_ft` (`query_normalizada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jana_conversas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jana_conversas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `titulo` varchar(200) DEFAULT NULL,
  `status` enum('ativa','arquivada') NOT NULL DEFAULT 'ativa',
  `iniciada_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `copiloto_conversas_user_id_status_index` (`user_id`,`status`),
  KEY `copiloto_conversas_business_id_index` (`business_id`),
  CONSTRAINT `copiloto_conversas_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `copiloto_conversas_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jana_health_narratives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jana_health_narratives` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `generated_at` timestamp NOT NULL,
  `severity` enum('info','warning','critical') NOT NULL DEFAULT 'info',
  `narrative` text NOT NULL,
  `snapshot_hash` varchar(64) NOT NULL,
  `model` varchar(50) NOT NULL DEFAULT 'gpt-4o-mini',
  `tokens_in` int(10) unsigned DEFAULT NULL,
  `tokens_out` int(10) unsigned DEFAULT NULL,
  `custo_brl` decimal(10,6) DEFAULT NULL,
  `payload_summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_summary`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `jana_health_narratives_generated_at_index` (`generated_at`),
  KEY `jana_health_narratives_severity_index` (`severity`),
  KEY `jana_health_narratives_snapshot_hash_index` (`snapshot_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jana_memoria_facts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jana_memoria_facts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `fato` text NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `valid_from` timestamp NOT NULL DEFAULT current_timestamp(),
  `valid_until` timestamp NULL DEFAULT NULL COMMENT 'NULL = ativo; preenchido = superseded',
  `hits_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Quantas vezes esse fato foi usado em resposta',
  `ultimo_hit_em` timestamp NULL DEFAULT NULL COMMENT 'Última vez que foi referenciado pelo agent',
  `core_memory` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'hits >= 5: promovido a core_memory (injetado direto no prompt)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'LGPD opt-out via esquecer()',
  PRIMARY KEY (`id`),
  KEY `cmf_biz_user_idx` (`business_id`,`user_id`),
  KEY `cmf_validity_idx` (`valid_from`,`valid_until`),
  KEY `cmf_biz_core_idx` (`business_id`,`core_memory`),
  KEY `cmf_hits_idx` (`hits_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jana_memoria_gabarito`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jana_memoria_gabarito` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned DEFAULT NULL COMMENT 'Business da pergunta (null = pergunta universal). FK soft.',
  `categoria` varchar(50) NOT NULL COMMENT 'LongMemEval: info-extraction|multi-session|temporal|knowledge-update|abstention',
  `subcategoria` varchar(50) DEFAULT NULL COMMENT 'Domínio: faturamento|clientes|metas|despesas|capability|cross-tenant|lgpd',
  `pergunta` text NOT NULL COMMENT 'Pergunta no estilo do user real (Larissa, Wagner, etc.)',
  `memoria_esperada_keys` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array de strings/snippets que DEVERIAM aparecer no recall. Match por contains.' CHECK (json_valid(`memoria_esperada_keys`)),
  `resposta_esperada_pattern` text DEFAULT NULL COMMENT 'Regex ou substring que valida resposta do agente (NULL = só checa recall)',
  `contexto_setup` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Fatos/dados que precisam estar no DB pra pergunta fazer sentido' CHECK (json_valid(`contexto_setup`)),
  `dificuldade` tinyint(3) unsigned NOT NULL DEFAULT 2 COMMENT '1=trivial, 2=média, 3=difícil (multi-hop ou temporal)',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `notas` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cmg_biz_ativo_idx` (`business_id`,`ativo`),
  KEY `cmg_categoria_idx` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jana_memoria_metricas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jana_memoria_metricas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `apurado_em` date NOT NULL COMMENT 'Dia da apuração (YYYY-MM-DD)',
  `business_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = plataforma agregada (todos os tenants)',
  `recall_at_3` decimal(4,3) DEFAULT NULL COMMENT 'Meta > 0.80 — % das vezes que a memória correta apareceu nos top 3',
  `precision_at_3` decimal(4,3) DEFAULT NULL COMMENT 'Meta > 0.60 — % dos top 3 que eram realmente relevantes',
  `mrr` decimal(4,3) DEFAULT NULL COMMENT 'Meta > 0.70 — Mean Reciprocal Rank',
  `latencia_p95_ms` int(10) unsigned DEFAULT NULL COMMENT 'Meta < 2000 ms — ciclo recall + LLM + resposta',
  `tokens_medio_interacao` int(10) unsigned DEFAULT NULL COMMENT 'Meta < 3000 tokens/msg — custo operacional',
  `memory_bloat_ratio` decimal(4,3) DEFAULT NULL COMMENT 'Meta > 0.60 — memórias úteis (com hit em 30d) / total',
  `taxa_contradicoes_pct` decimal(5,2) DEFAULT NULL COMMENT 'Meta < 2.00 % — fatos contraditórios sem valid_until',
  `cross_tenant_violations` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Meta = 0 — recall que retornou business_id alheio',
  `faithfulness` decimal(4,3) DEFAULT NULL COMMENT 'RAGAS — resposta vs contexto (sem alucinação); meta > 0.85',
  `answer_relevancy` decimal(4,3) DEFAULT NULL COMMENT 'RAGAS — resposta vs pergunta (relevância semântica); meta > 0.80',
  `context_precision` decimal(4,3) DEFAULT NULL COMMENT 'RAGAS — chunks recuperados ranqueados por relevância; meta > 0.70',
  `total_interacoes_dia` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Mensagens role=user no dia',
  `total_memorias_ativas` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'CopilotoMemoriaFato ativos no fim do dia',
  `detalhes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detalhes`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mem_metr_ux` (`apurado_em`,`business_id`),
  KEY `mem_metr_apurado_em_idx` (`apurado_em`),
  KEY `mem_metr_biz_idx` (`business_id`),
  CONSTRAINT `copiloto_memoria_metricas_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jana_mensagens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jana_mensagens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversa_id` bigint(20) unsigned NOT NULL,
  `role` enum('user','assistant','system') NOT NULL,
  `content` text NOT NULL,
  `tokens_in` int(10) unsigned DEFAULT NULL,
  `tokens_out` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `copiloto_mensagens_conversa_id_created_at_index` (`conversa_id`,`created_at`),
  CONSTRAINT `copiloto_mensagens_conversa_id_foreign` FOREIGN KEY (`conversa_id`) REFERENCES `jana_conversas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jana_meta_apuracoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jana_meta_apuracoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `meta_id` bigint(20) unsigned NOT NULL,
  `data_ref` date NOT NULL,
  `valor_realizado` decimal(15,2) NOT NULL,
  `calculado_em` timestamp NOT NULL,
  `fonte_query_hash` varchar(64) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `copiloto_apur_unico` (`meta_id`,`data_ref`,`fonte_query_hash`),
  KEY `copiloto_meta_apuracoes_meta_id_data_ref_index` (`meta_id`,`data_ref`),
  CONSTRAINT `copiloto_meta_apuracoes_meta_id_foreign` FOREIGN KEY (`meta_id`) REFERENCES `jana_metas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jana_meta_fontes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jana_meta_fontes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `meta_id` bigint(20) unsigned NOT NULL,
  `driver` enum('sql','php','http') NOT NULL DEFAULT 'sql',
  `config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`config_json`)),
  `cadencia` enum('diaria','horaria','manual') NOT NULL DEFAULT 'diaria',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `copiloto_meta_fontes_meta_id_unique` (`meta_id`),
  CONSTRAINT `copiloto_meta_fontes_meta_id_foreign` FOREIGN KEY (`meta_id`) REFERENCES `jana_metas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jana_meta_periodos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jana_meta_periodos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `meta_id` bigint(20) unsigned NOT NULL,
  `tipo_periodo` enum('mes','trim','ano','custom') NOT NULL DEFAULT 'mes',
  `data_ini` date NOT NULL,
  `data_fim` date NOT NULL,
  `valor_alvo` decimal(15,2) NOT NULL,
  `trajetoria` enum('linear','sazonal','exponencial','manual') NOT NULL DEFAULT 'linear',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `copiloto_meta_periodos_meta_id_data_ini_index` (`meta_id`,`data_ini`),
  CONSTRAINT `copiloto_meta_periodos_meta_id_foreign` FOREIGN KEY (`meta_id`) REFERENCES `jana_metas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jana_metas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jana_metas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned DEFAULT NULL,
  `slug` varchar(80) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `unidade` enum('R$','qtd','%','dias') NOT NULL DEFAULT 'R$',
  `tipo_agregacao` enum('soma','media','ultimo','contagem') NOT NULL DEFAULT 'soma',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criada_por_user_id` int(10) unsigned DEFAULT NULL,
  `origem` enum('chat_ia','manual','seed') NOT NULL DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `copiloto_metas_business_id_slug_unique` (`business_id`,`slug`),
  KEY `copiloto_metas_criada_por_user_id_foreign` (`criada_por_user_id`),
  KEY `copiloto_metas_business_id_index` (`business_id`),
  CONSTRAINT `copiloto_metas_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `copiloto_metas_criada_por_user_id_foreign` FOREIGN KEY (`criada_por_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jana_negative_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jana_negative_cache` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cache_key` char(64) NOT NULL COMMENT 'SHA256(biz + user + query_normalizada) — mesma chave do positive cache',
  `business_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `query_normalizada` text NOT NULL,
  `hits_negativos` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Quantas vezes evitamos round-trip Meilisearch graças a essa entrada',
  `expira_em` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `copiloto_negative_cache_cache_key_unique` (`cache_key`),
  KEY `cnc_biz_user_exp_idx` (`business_id`,`user_id`,`expira_em`),
  KEY `copiloto_negative_cache_expira_em_index` (`expira_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jana_sugestoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jana_sugestoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversa_id` bigint(20) unsigned NOT NULL,
  `meta_id` bigint(20) unsigned DEFAULT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload_json`)),
  `escolhida_em` timestamp NULL DEFAULT NULL,
  `rejeitada_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `copiloto_sugestoes_conversa_id_escolhida_em_index` (`conversa_id`,`escolhida_em`),
  KEY `copiloto_sugestoes_meta_id_foreign` (`meta_id`),
  CONSTRAINT `copiloto_sugestoes_conversa_id_foreign` FOREIGN KEY (`conversa_id`) REFERENCES `jana_conversas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `copiloto_sugestoes_meta_id_foreign` FOREIGN KEY (`meta_id`) REFERENCES `jana_metas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `janaia_connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `janaia_connections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` bigint(20) unsigned NOT NULL,
  `location_id` bigint(20) unsigned NOT NULL,
  `machine_id` varchar(191) NOT NULL,
  `machine_name` varchar(191) NOT NULL,
  `ip_address` varchar(191) NOT NULL,
  `operating_system` varchar(191) NOT NULL,
  `system_version` varchar(191) NOT NULL,
  `connection_type` varchar(191) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `user_name` varchar(191) NOT NULL,
  `user_email` varchar(191) NOT NULL,
  `user_permissions` varchar(191) NOT NULL,
  `license_key` varchar(191) NOT NULL,
  `api_version` varchar(191) NOT NULL,
  `status` varchar(191) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `transaction_number` varchar(191) DEFAULT NULL,
  `payment_detail_id` bigint(20) unsigned DEFAULT NULL,
  `location_id` int(10) unsigned DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `chart_of_account_id` bigint(20) unsigned DEFAULT NULL,
  `transaction_type` varchar(191) DEFAULT NULL,
  `transaction_sub_type` varchar(191) DEFAULT NULL,
  `name` text DEFAULT NULL,
  `date` date DEFAULT NULL,
  `month` varchar(191) DEFAULT NULL,
  `year` varchar(191) DEFAULT NULL,
  `reference` varchar(191) DEFAULT NULL,
  `contact_id` int(10) unsigned DEFAULT NULL,
  `debit` decimal(65,4) DEFAULT NULL,
  `credit` decimal(65,4) DEFAULT NULL,
  `balance` decimal(65,4) DEFAULT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `reversed` tinyint(4) NOT NULL DEFAULT 0,
  `reversible` tinyint(4) NOT NULL DEFAULT 1,
  `manual_entry` tinyint(4) NOT NULL DEFAULT 0,
  `receipt` varchar(191) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chart_of_account_id_index` (`chart_of_account_id`),
  KEY `currency_id_index` (`currency_id`),
  KEY `created_by_id_index` (`created_by_id`),
  KEY `journal_entries_contact_id_index` (`contact_id`),
  KEY `journal_entries_location_id_index` (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kb_bridge_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kb_bridge_state` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `last_bridge_at` timestamp NULL DEFAULT NULL,
  `docs_processed_last_run` int(10) unsigned NOT NULL DEFAULT 0,
  `edges_derived_last_run` int(10) unsigned NOT NULL DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kb_bridge_state_business` (`business_id`),
  CONSTRAINT `fk_kb_bridge_state_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kb_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kb_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `slug` varchar(60) NOT NULL,
  `label` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `hue` smallint(5) unsigned NOT NULL DEFAULT 240 COMMENT '0-360 OKLCH chroma',
  `icon` varchar(80) DEFAULT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kb_cat_business_slug` (`business_id`,`slug`),
  KEY `idx_kb_cat_business_sort` (`business_id`,`sort_order`),
  CONSTRAINT `fk_kb_cat_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kb_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kb_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `node_id` bigint(20) unsigned NOT NULL,
  `block_idx` smallint(5) unsigned NOT NULL COMMENT 'index do bloco em body_blocks (0-based)',
  `text` text NOT NULL,
  `author_user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_kb_comments_biz_node_block` (`business_id`,`node_id`,`block_idx`),
  KEY `idx_kb_comments_author` (`author_user_id`),
  KEY `idx_kb_comments_deleted` (`deleted_at`),
  KEY `fk_kb_comments_node` (`node_id`),
  CONSTRAINT `fk_kb_comments_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kb_comments_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kb_comments_node` FOREIGN KEY (`node_id`) REFERENCES `kb_nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kb_decision_tree_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kb_decision_tree_steps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `tree_id` bigint(20) unsigned NOT NULL,
  `position` smallint(5) unsigned NOT NULL,
  `question` varchar(500) NOT NULL,
  `yes_next_step_id` bigint(20) unsigned DEFAULT NULL,
  `yes_fix` text DEFAULT NULL COMMENT 'pode citar #kb-NNN pra cross-link',
  `yes_fix_node_id` bigint(20) unsigned DEFAULT NULL COMMENT 'edge fix-of-decision opcional',
  `no_next_step_id` bigint(20) unsigned DEFAULT NULL,
  `no_fix` text DEFAULT NULL,
  `no_fix_node_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kb_dts_tree_pos` (`tree_id`,`position`),
  KEY `idx_kb_dts_biz_tree` (`business_id`,`tree_id`),
  KEY `fk_kb_dts_yes_next` (`yes_next_step_id`),
  KEY `fk_kb_dts_no_next` (`no_next_step_id`),
  KEY `fk_kb_dts_yes_fix_node` (`yes_fix_node_id`),
  KEY `fk_kb_dts_no_fix_node` (`no_fix_node_id`),
  CONSTRAINT `fk_kb_dts_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kb_dts_no_fix_node` FOREIGN KEY (`no_fix_node_id`) REFERENCES `kb_nodes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kb_dts_no_next` FOREIGN KEY (`no_next_step_id`) REFERENCES `kb_decision_tree_steps` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kb_dts_tree` FOREIGN KEY (`tree_id`) REFERENCES `kb_decision_trees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kb_dts_yes_fix_node` FOREIGN KEY (`yes_fix_node_id`) REFERENCES `kb_nodes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kb_dts_yes_next` FOREIGN KEY (`yes_next_step_id`) REFERENCES `kb_decision_tree_steps` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kb_decision_trees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kb_decision_trees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `slug` varchar(120) NOT NULL,
  `title` varchar(180) NOT NULL,
  `equip` varchar(80) DEFAULT NULL,
  `when_to_use` varchar(500) DEFAULT NULL COMMENT 'descrição do sintoma',
  `hue` smallint(5) unsigned NOT NULL DEFAULT 240,
  `status` varchar(40) NOT NULL DEFAULT 'published' COMMENT 'draft|published|archived',
  `root_step_id` bigint(20) unsigned DEFAULT NULL COMMENT 'primeiro passo (entry point) — populado após criação',
  `author_user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kb_dt_biz_slug` (`business_id`,`slug`),
  KEY `idx_kb_dt_biz_status` (`business_id`,`status`),
  KEY `fk_kb_dt_author` (`author_user_id`),
  KEY `fk_kb_dt_root_step` (`root_step_id`),
  CONSTRAINT `fk_kb_dt_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kb_dt_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kb_dt_root_step` FOREIGN KEY (`root_step_id`) REFERENCES `kb_decision_tree_steps` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kb_edges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kb_edges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `from_node_id` bigint(20) unsigned NOT NULL,
  `to_node_id` bigint(20) unsigned NOT NULL,
  `edge_type` varchar(40) NOT NULL COMMENT 'next-in-path|fix-of-decision|supersedes|charter-of|references-data|ai-related|cross-link|related-by-tag',
  `weight` decimal(5,3) NOT NULL DEFAULT 1.000 COMMENT 'pra ai-related/related-by-tag, score 0-1',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'metadata específico (ex: ai-related → embedding score; cross-link → block_idx)' CHECK (json_valid(`payload`)),
  `generated_by` varchar(40) NOT NULL DEFAULT 'manual' COMMENT 'manual|bridge_job|ai_embed|tag_overlap|user_action',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kb_edges_triple` (`business_id`,`from_node_id`,`to_node_id`,`edge_type`),
  KEY `idx_kb_edges_biz_from` (`business_id`,`from_node_id`),
  KEY `idx_kb_edges_biz_to` (`business_id`,`to_node_id`),
  KEY `idx_kb_edges_biz_type` (`business_id`,`edge_type`),
  KEY `fk_kb_edges_from` (`from_node_id`),
  KEY `fk_kb_edges_to` (`to_node_id`),
  CONSTRAINT `fk_kb_edges_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kb_edges_from` FOREIGN KEY (`from_node_id`) REFERENCES `kb_nodes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kb_edges_to` FOREIGN KEY (`to_node_id`) REFERENCES `kb_nodes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_kb_edges_no_self` CHECK (`from_node_id` <> `to_node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kb_favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kb_favorites` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `node_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kb_favorites_user_node` (`user_id`,`node_id`),
  KEY `idx_kb_favorites_biz_user` (`business_id`,`user_id`),
  KEY `fk_kb_favorites_node` (`node_id`),
  CONSTRAINT `fk_kb_favorites_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kb_favorites_node` FOREIGN KEY (`node_id`) REFERENCES `kb_nodes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kb_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kb_node_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kb_node_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `node_id` bigint(20) unsigned NOT NULL,
  `version_at` timestamp NOT NULL,
  `author_user_id` int(10) unsigned DEFAULT NULL,
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '{title, excerpt, body_blocks, tags, status, category_id, subcategory_id, nivel, equip}' CHECK (json_valid(`snapshot`)),
  `change_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_kb_versions_biz_node_when` (`business_id`,`node_id`,`version_at`),
  KEY `fk_kb_versions_node` (`node_id`),
  KEY `fk_kb_versions_author` (`author_user_id`),
  CONSTRAINT `fk_kb_versions_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kb_versions_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kb_versions_node` FOREIGN KEY (`node_id`) REFERENCES `kb_nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kb_nodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kb_nodes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `type` varchar(40) NOT NULL COMMENT 'article|adr|session|charter|runbook|briefing|spec|comparativo|reference|os|customer|product|nfe|equipment|external_file',
  `slug` varchar(180) NOT NULL,
  `title` varchar(255) NOT NULL,
  `excerpt` varchar(500) DEFAULT NULL,
  `body_blocks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'array de blocks [{kind: para|h2|list|callout|image, ...}]' CHECK (json_valid(`body_blocks`)),
  `source_doc_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK mcp_memory_documents.id quando type in (adr|session|charter|...)',
  `source_entity_type` varchar(80) DEFAULT NULL COMMENT 'App\\Transaction, App\\Contact, etc — quando type in (os|customer|nfe|...)',
  `source_entity_id` bigint(20) unsigned DEFAULT NULL,
  `is_editable` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'true só pra type=article (operacional). bridges = false.',
  `status` varchar(40) NOT NULL DEFAULT 'ok' COMMENT 'draft|ok|outdated|deleted|deprecated',
  `pinned` tinyint(1) NOT NULL DEFAULT 0,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `subcategory_id` bigint(20) unsigned DEFAULT NULL,
  `nivel` varchar(20) DEFAULT NULL COMMENT 'iniciante|intermediario|avancado (operacional)',
  `equip` varchar(80) DEFAULT NULL COMMENT 'Roland VS-540, HP Latex 365, etc',
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'array de strings' CHECK (json_valid(`tags`)),
  `reads_count` int(10) unsigned NOT NULL DEFAULT 0,
  `helpful_count` int(10) unsigned NOT NULL DEFAULT 0,
  `outdated_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `os_linked_count` int(10) unsigned NOT NULL DEFAULT 0,
  `author_user_id` int(10) unsigned DEFAULT NULL,
  `read_time_min` smallint(5) unsigned DEFAULT NULL,
  `last_verified_at` timestamp NULL DEFAULT NULL COMMENT 'última re-verificação pelo dono (botão "Re-verificar")',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kb_nodes_business_slug` (`business_id`,`slug`),
  KEY `idx_kb_nodes_business_type` (`business_id`,`type`),
  KEY `idx_kb_nodes_biz_status_pin` (`business_id`,`status`,`pinned`),
  KEY `idx_kb_nodes_source_doc` (`source_doc_id`),
  KEY `idx_kb_nodes_source_entity` (`source_entity_type`,`source_entity_id`),
  KEY `idx_kb_nodes_category` (`category_id`),
  KEY `idx_kb_nodes_subcategory` (`subcategory_id`),
  KEY `idx_kb_nodes_author` (`author_user_id`),
  KEY `idx_kb_nodes_deleted` (`deleted_at`),
  CONSTRAINT `fk_kb_nodes_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kb_nodes_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kb_nodes_category` FOREIGN KEY (`category_id`) REFERENCES `kb_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kb_nodes_source_doc` FOREIGN KEY (`source_doc_id`) REFERENCES `mcp_memory_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kb_nodes_subcategory` FOREIGN KEY (`subcategory_id`) REFERENCES `kb_subcategories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kb_path_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kb_path_steps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `path_id` bigint(20) unsigned NOT NULL,
  `node_id` bigint(20) unsigned NOT NULL,
  `position` smallint(5) unsigned NOT NULL COMMENT '1-based',
  `step_type` varchar(40) NOT NULL DEFAULT 'leitura' COMMENT 'leitura|pratica|decisao',
  `note` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kb_path_steps_pos` (`path_id`,`position`),
  KEY `idx_kb_path_steps_biz_path` (`business_id`,`path_id`),
  KEY `idx_kb_path_steps_node` (`node_id`),
  CONSTRAINT `fk_kb_path_steps_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kb_path_steps_node` FOREIGN KEY (`node_id`) REFERENCES `kb_nodes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kb_path_steps_path` FOREIGN KEY (`path_id`) REFERENCES `kb_paths` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kb_paths`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kb_paths` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `slug` varchar(120) NOT NULL,
  `title` varchar(180) NOT NULL,
  `audience` varchar(180) DEFAULT NULL COMMENT 'Larissa primeiro mês, Wagner onboarding governança, etc.',
  `description` varchar(500) DEFAULT NULL,
  `hue` smallint(5) unsigned NOT NULL DEFAULT 240,
  `status` varchar(40) NOT NULL DEFAULT 'published' COMMENT 'draft|published|archived',
  `author_user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kb_paths_biz_slug` (`business_id`,`slug`),
  KEY `idx_kb_paths_biz_status` (`business_id`,`status`),
  KEY `fk_kb_paths_author` (`author_user_id`),
  CONSTRAINT `fk_kb_paths_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kb_paths_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kb_subcategories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kb_subcategories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `slug` varchar(60) NOT NULL,
  `label` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `auto_match` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'ex: {field: "equip", op: "=", value: "Roland VS-540"}' CHECK (json_valid(`auto_match`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kb_sub_biz_cat_slug` (`business_id`,`category_id`,`slug`),
  KEY `idx_kb_sub_biz_cat` (`business_id`,`category_id`),
  KEY `fk_kb_sub_category` (`category_id`),
  CONSTRAINT `fk_kb_sub_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kb_sub_category` FOREIGN KEY (`category_id`) REFERENCES `kb_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `licenca_computador`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `licenca_computador` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `hd` varchar(50) DEFAULT NULL,
  `user_win` varchar(191) DEFAULT NULL,
  `bloqueado` tinyint(1) NOT NULL DEFAULT 0,
  `tipodeacesso` varchar(50) DEFAULT NULL,
  `conexao` varchar(100) DEFAULT NULL,
  `usuario` varchar(15) DEFAULT NULL,
  `senha` varchar(15) DEFAULT NULL,
  `sistema_operacional` varchar(50) DEFAULT NULL,
  `ip_interno` varchar(15) DEFAULT NULL,
  `antivirus` varchar(15) DEFAULT NULL,
  `pasta_instalacao` varchar(255) DEFAULT NULL,
  `versao_exe` varchar(15) DEFAULT NULL,
  `versao_banco` varchar(15) DEFAULT NULL,
  `data` timestamp NULL DEFAULT NULL,
  `dt_ultima_assistencia` timestamp NULL DEFAULT NULL,
  `backup_automatico` char(1) DEFAULT NULL,
  `paf` char(1) DEFAULT NULL,
  `processador` varchar(50) DEFAULT NULL,
  `memoria` varchar(20) DEFAULT NULL,
  `velocidade_conexao` varchar(20) DEFAULT NULL,
  `impressora_fiscal` varchar(50) DEFAULT NULL,
  `leitor_barras` varchar(50) DEFAULT NULL,
  `gera_mensalidade` char(1) DEFAULT NULL,
  `hostname` varchar(50) DEFAULT NULL,
  `liberado` char(1) DEFAULT NULL,
  `dt_validade` timestamp NULL DEFAULT NULL,
  `serial` varchar(20) DEFAULT NULL,
  `contra_senha` varchar(20) DEFAULT NULL,
  `oculto` char(1) DEFAULT NULL,
  `valor` double DEFAULT NULL,
  `motivo` varchar(500) DEFAULT NULL,
  `caminho_banco` varchar(255) DEFAULT NULL,
  `dt_ultimo_acesso` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `descricao` varchar(191) DEFAULT NULL,
  `sistema` varchar(191) DEFAULT NULL,
  `dt_cadastro` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `licenca_computador_business_id_foreign` (`business_id`),
  KEY `licenca_computador_hd_index` (`hd`),
  KEY `licenca_computador_dt_ultimo_acesso_index` (`dt_ultimo_acesso`),
  KEY `lcomp_business_dt_acesso_idx` (`business_id`,`dt_ultimo_acesso`),
  KEY `lcomp_business_bloqueado_idx` (`business_id`,`bloqueado`),
  CONSTRAINT `licenca_computador_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `licenca_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `licenca_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `licenca_id` bigint(20) unsigned DEFAULT NULL,
  `business_id` int(10) unsigned DEFAULT NULL,
  `business_location_id` int(10) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(50) NOT NULL,
  `client_id` varchar(191) DEFAULT NULL,
  `token_hint` varchar(32) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `endpoint` varchar(255) DEFAULT NULL,
  `http_method` varchar(10) DEFAULT NULL,
  `http_status` smallint(6) DEFAULT NULL,
  `duration_ms` int(11) DEFAULT NULL,
  `error_code` varchar(100) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `source` varchar(30) NOT NULL DEFAULT 'desktop_audit',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `licenca_log_business_id_created_at_index` (`business_id`,`created_at`),
  KEY `licenca_log_licenca_id_created_at_index` (`licenca_id`,`created_at`),
  KEY `licenca_log_event_created_at_index` (`event`,`created_at`),
  KEY `licenca_log_event_index` (`event`),
  KEY `licenca_log_created_at_index` (`created_at`),
  KEY `licenca_log_business_location_id_index` (`business_location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `macro_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `macro_variants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `macro_id` bigint(20) unsigned NOT NULL COMMENT 'FK macros.id (cascade)',
  `label` varchar(80) NOT NULL COMMENT 'ex: "Versao A formal"',
  `body` text NOT NULL COMMENT 'override de macros.body — corpo da variante',
  `weight` smallint(5) unsigned NOT NULL DEFAULT 50 COMMENT 'peso pra distribuicao ponderada (50/50 = padrao A+B)',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sent_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'contador de envios via daemon (status=sent)',
  `response_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'contador de inbound em 24h da outbound (idempotente)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mv_biz_macro_active_idx` (`business_id`,`macro_id`,`active`),
  KEY `macro_variants_macro_id_foreign` (`macro_id`),
  CONSTRAINT `macro_variants_macro_id_foreign` FOREIGN KEY (`macro_id`) REFERENCES `macros` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `macros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `macros` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `label` varchar(80) NOT NULL COMMENT 'rotulo visivel no dropdown (ex: Pedir CNPJ)',
  `shortcut` varchar(30) DEFAULT NULL COMMENT 'atalho slash opcional (ex: /cnpj)',
  `body` text NOT NULL COMMENT 'corpo da mensagem, suporta {{vars}} em PR futuro',
  `actions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '[{"type":"add_tag","tag_id":3},{"type":"set_status","status":"awaiting_human"}]' CHECK (json_valid(`actions_json`)),
  `created_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `used_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'contador de uso (analytics top-N)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `macros_business_shortcut_uniq` (`business_id`,`shortcut`),
  KEY `macros_business_idx` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manifesto_limites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `manifesto_limites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `manifesto_limites_business_id_foreign` (`business_id`),
  CONSTRAINT `manifesto_limites_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manifestos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `manifestos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `chave` varchar(44) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `documento` varchar(20) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `num_prot` varchar(20) NOT NULL,
  `data_emissao` varchar(25) NOT NULL,
  `sequencia_evento` int(11) NOT NULL,
  `fatura_salva` tinyint(1) NOT NULL,
  `tipo` int(11) NOT NULL,
  `nsu` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `manifestos_business_id_foreign` (`business_id`),
  CONSTRAINT `manifestos_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_actors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_actors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) NOT NULL,
  `type` enum('human','ai_agent','service') NOT NULL,
  `trust_level` enum('L0','L1','L2','L3','L4') NOT NULL,
  `parent_actor_id` bigint(20) unsigned DEFAULT NULL,
  `modules_write` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '["Jana","KB"] ou ["*"]' CHECK (json_valid(`modules_write`)),
  `modules_read` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '["*"]' CHECK (json_valid(`modules_read`)),
  `modules_blocked` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '["Connector","Superadmin"]' CHECK (json_valid(`modules_blocked`)),
  `skills_required` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '["oimpresso-stack","multi-tenant-patterns"]' CHECK (json_valid(`skills_required`)),
  `actions_blocked` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '["drop_table","schema_destructive"]' CHECK (json_valid(`actions_blocked`)),
  `audit_required` tinyint(1) NOT NULL DEFAULT 1,
  `user_id` int(10) unsigned DEFAULT NULL,
  `display_name` varchar(120) NOT NULL,
  `created_by_actor_id` bigint(20) unsigned DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoked_by_actor_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_actors_slug_unique` (`slug`),
  KEY `mcp_actors_type_index` (`type`),
  KEY `mcp_actors_trust_level_index` (`trust_level`),
  KEY `mcp_actors_parent_actor_id_index` (`parent_actor_id`),
  KEY `mcp_actors_user_id_index` (`user_id`),
  KEY `mcp_actors_revoked_at_index` (`revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_admin_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_admin_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `business_id` int(10) unsigned NOT NULL DEFAULT 0,
  `action` varchar(64) NOT NULL,
  `route` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_audit_biz_action_ts` (`business_id`,`action`,`created_at`),
  KEY `idx_admin_audit_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_alertas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_alertas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL COMMENT 'null = alerta global da plataforma',
  `business_id` int(10) unsigned DEFAULT NULL,
  `kind` enum('cota_excedida','tool_destrutiva','ip_suspeito','taxa_errors','cliente_externo') NOT NULL,
  `threshold` decimal(14,4) DEFAULT NULL COMMENT 'Para cota_excedida: % da quota; para taxa_errors: %; etc',
  `canal` enum('in_app','email','whatsapp') NOT NULL DEFAULT 'in_app',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `config_extra` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Configuração específica do kind (ex: ip_allowlist)' CHECK (json_valid(`config_extra`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mcp_alt_kind_ativo_idx` (`kind`,`ativo`),
  KEY `mcp_alt_user_idx` (`user_id`),
  KEY `mcp_alertas_business_id_foreign` (`business_id`),
  CONSTRAINT `mcp_alertas_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mcp_alertas_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_alertas_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_alertas_eventos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `business_id` int(10) unsigned DEFAULT NULL,
  `tipo` varchar(50) NOT NULL COMMENT 'quota_threshold|tool_destrutiva|cross_tenant|taxa_errors|...',
  `severidade` varchar(20) NOT NULL DEFAULT 'medium' COMMENT 'low|medium|high|critical',
  `titulo` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `chave_idempotencia` varchar(200) NOT NULL COMMENT 'Hash semântico — evita dispatch duplicado',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `status` enum('aberto','notificado','ack','arquivado') NOT NULL DEFAULT 'aberto',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `notificado_em` timestamp NULL DEFAULT NULL,
  `ack_em` timestamp NULL DEFAULT NULL,
  `ack_by_user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_alertas_eventos_chave_idempotencia_unique` (`chave_idempotencia`),
  KEY `mae_user_criado_idx` (`user_id`,`criado_em`),
  KEY `mae_tipo_sev_idx` (`tipo`,`severidade`),
  KEY `mae_status_criado_idx` (`status`,`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` char(36) NOT NULL COMMENT 'UUID gerado por chamada — correlaciona com Claude Code session',
  `user_id` int(10) unsigned NOT NULL,
  `business_id` int(10) unsigned DEFAULT NULL,
  `ts` timestamp NOT NULL DEFAULT current_timestamp(),
  `endpoint` enum('tools/list','tools/call','resources/list','resources/read','prompts/list','prompts/get','initialize') NOT NULL,
  `tool_or_resource` varchar(200) DEFAULT NULL COMMENT 'Nome da tool ou URI do resource invocado',
  `scope_required` varchar(100) DEFAULT NULL,
  `status` enum('ok','denied','error','quota_exceeded') NOT NULL,
  `error_code` varchar(50) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `tokens_in` int(10) unsigned DEFAULT NULL,
  `tokens_out` int(10) unsigned DEFAULT NULL,
  `cache_read` int(10) unsigned DEFAULT NULL,
  `cache_write` int(10) unsigned DEFAULT NULL,
  `custo_brl` decimal(10,6) DEFAULT NULL,
  `duration_ms` int(10) unsigned DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(200) DEFAULT NULL,
  `claude_code_session` varchar(36) DEFAULT NULL COMMENT 'SessionId do Claude Code (correlation com JSONLs locais)',
  `mcp_token_id` bigint(20) unsigned DEFAULT NULL,
  `payload_summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Resumo redactado dos args (sem PII)' CHECK (json_valid(`payload_summary`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_audit_log_request_id_unique` (`request_id`),
  KEY `mcp_al_user_ts_idx` (`user_id`,`ts`),
  KEY `mcp_al_biz_ts_idx` (`business_id`,`ts`),
  KEY `mcp_al_tool_ts_idx` (`tool_or_resource`,`ts`),
  KEY `mcp_al_status_ts_idx` (`status`,`ts`),
  KEY `mcp_audit_log_mcp_token_id_foreign` (`mcp_token_id`),
  KEY `mcp_audit_log_ts_index` (`ts`),
  CONSTRAINT `mcp_audit_log_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mcp_audit_log_mcp_token_id_foreign` FOREIGN KEY (`mcp_token_id`) REFERENCES `mcp_tokens` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mcp_audit_log_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`u906587222_oimpresso`@`localhost`*/ /*!50003 TRIGGER trg_mcp_audit_log_no_update
            BEFORE UPDATE ON mcp_audit_log
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'mcp_audit_log is append-only (Constitution v1.1.0 Article 9). UPDATE forbidden.';
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`u906587222_oimpresso`@`localhost`*/ /*!50003 TRIGGER trg_mcp_audit_log_no_delete
            BEFORE DELETE ON mcp_audit_log
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'mcp_audit_log is append-only (Constitution v1.1.0 Article 9). DELETE forbidden.';
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `mcp_automation_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_automation_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `automation_id` bigint(20) unsigned NOT NULL,
  `ran_at` timestamp NOT NULL,
  `status` enum('ok','warn','fail','skip') NOT NULL,
  `detail` text DEFAULT NULL,
  `actor` varchar(100) DEFAULT NULL COMMENT 'quem/o-que disparou: "scheduler", "claude-code:SessionStart", username...',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_runs_automation_ran` (`automation_id`,`ran_at`),
  KEY `idx_runs_status` (`status`),
  CONSTRAINT `fk_runs_automation` FOREIGN KEY (`automation_id`) REFERENCES `mcp_automations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_automations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_automations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) NOT NULL COMMENT 'Identificador único da automação (= basename do hook / comando do cron / slug do manifesto)',
  `business_id` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL = global (registry de infra de plataforma, sem tenant — ADR 0093)',
  `tipo` enum('hook_sessionstart','hook_pretooluse','hook_posttooluse','cron','routine','webhook') NOT NULL COMMENT 'Classe da automação — gatilho determina o tipo',
  `gatilho` varchar(255) NOT NULL COMMENT 'texto livre: matcher do hook (ex Edit|Write) OU expressao cron (ex 0 6 * * *) OU "SessionStart pos brief-fetch"',
  `descricao` text DEFAULT NULL,
  `arquivo` varchar(300) NOT NULL COMMENT 'path relativo ao repo (ex .claude/hooks/pii-redactor.ps1)',
  `owner` varchar(100) DEFAULT NULL,
  `governed_by_adr` varchar(100) DEFAULT NULL COMMENT 'slug do ADR que governa esta automacao (nullable)',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `last_run_at` timestamp NULL DEFAULT NULL,
  `last_status` enum('ok','warn','fail','skip') DEFAULT NULL,
  `last_detail` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_automations_slug_unique` (`slug`),
  KEY `idx_automations_tipo` (`tipo`),
  KEY `idx_automations_enabled` (`enabled`),
  KEY `idx_automations_last_status` (`last_status`),
  KEY `idx_automations_business` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_brief_inputs_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_brief_inputs_cache` (
  `singleton_id` tinyint(4) NOT NULL DEFAULT 1,
  `computed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `active_cycle` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`active_cycle`)),
  `hitl_pending` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`hitl_pending`)),
  `brain_b_budget` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`brain_b_budget`)),
  `in_flight` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`in_flight`)),
  `recent_24h` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recent_24h`)),
  `skills_7d` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`skills_7d`)),
  `skills_candidatas_poda` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`skills_candidatas_poda`)),
  `charters_stale` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`charters_stale`)),
  `flags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`flags`)),
  PRIMARY KEY (`singleton_id`),
  CONSTRAINT `mcp_brief_inputs_cache_singleton` CHECK (`singleton_id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_briefs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_briefs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `content` mediumtext NOT NULL,
  `token_count` int(11) NOT NULL,
  `source_hash` varchar(64) NOT NULL,
  `generator_ver` varchar(16) NOT NULL DEFAULT 'v1',
  `cost_usd` decimal(8,4) DEFAULT NULL,
  `valid` tinyint(1) NOT NULL DEFAULT 1,
  `error_msg` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mcp_briefs_generated_at` (`generated_at` DESC),
  KEY `idx_mcp_briefs_valid_recent` (`valid`,`generated_at` DESC),
  CONSTRAINT `mcp_briefs_token_limit` CHECK (`token_count` <= 3500)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_cc_blobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_cc_blobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `hash_sha256` char(64) NOT NULL COMMENT 'SHA256 do conteúdo ORIGINAL (antes de comprimir) — dedup',
  `blob_type` enum('stdout','stderr','attachment','image','json') NOT NULL DEFAULT 'stdout',
  `mime_type` varchar(100) DEFAULT NULL,
  `size_original_bytes` int(10) unsigned NOT NULL,
  `size_compressed_bytes` int(10) unsigned NOT NULL,
  `compressed_data` blob NOT NULL COMMENT 'zlib::compress($content, 6) — MEDIUMBLOB via DBAL',
  `refs_count` int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'Quantas mcp_cc_messages apontam pra este blob',
  `first_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_cc_blobs_hash_sha256_unique` (`hash_sha256`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_cc_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_cc_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint(20) unsigned NOT NULL COMMENT 'FK mcp_cc_sessions.id',
  `msg_uuid` varchar(36) NOT NULL COMMENT 'UUID gerado pelo Claude Code (dedup global)',
  `parent_uuid` varchar(36) DEFAULT NULL COMMENT 'parentUuid pra reconstruir thread',
  `user_id` int(10) unsigned NOT NULL,
  `business_id` int(10) unsigned DEFAULT NULL,
  `msg_type` enum('user','assistant','tool_use','tool_result','attachment','hook','system') NOT NULL,
  `role` varchar(20) DEFAULT NULL,
  `tool_name` varchar(100) DEFAULT NULL COMMENT 'Bash|Edit|Read|Grep|Glob|Write|WebSearch|Agent|...',
  `content_text` mediumtext DEFAULT NULL COMMENT 'Texto plano (FULLTEXT)',
  `content_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Payload estruturado completo do JSONL' CHECK (json_valid(`content_json`)),
  `blob_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK mcp_cc_blobs.id se conteúdo > 4KB (compactado + dedup)',
  `tokens_in` int(10) unsigned DEFAULT NULL,
  `tokens_out` int(10) unsigned DEFAULT NULL,
  `cache_read` int(10) unsigned DEFAULT NULL,
  `cache_write` int(10) unsigned DEFAULT NULL,
  `cost_usd` decimal(10,8) DEFAULT NULL,
  `ts` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_cc_messages_msg_uuid_unique` (`msg_uuid`),
  KEY `cc_msg_sess_ts_idx` (`session_id`,`ts`),
  KEY `cc_msg_user_ts_idx` (`user_id`,`ts`),
  KEY `cc_msg_type_tool_idx` (`msg_type`,`tool_name`,`ts`),
  KEY `mcp_cc_messages_user_id_index` (`user_id`),
  KEY `mcp_cc_messages_business_id_index` (`business_id`),
  KEY `mcp_cc_messages_msg_type_index` (`msg_type`),
  KEY `mcp_cc_messages_tool_name_index` (`tool_name`),
  KEY `mcp_cc_messages_ts_index` (`ts`),
  FULLTEXT KEY `cc_msg_content_ft` (`content_text`),
  CONSTRAINT `mcp_cc_messages_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `mcp_cc_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mcp_cc_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_cc_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_cc_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_uuid` varchar(36) NOT NULL COMMENT 'UUID da session do Claude Code (gerado pelo CC)',
  `user_id` int(10) unsigned NOT NULL COMMENT 'User do oimpresso que ingeriu — RBAC scope',
  `business_id` int(10) unsigned DEFAULT NULL,
  `project_path` varchar(500) NOT NULL COMMENT 'cwd: D:\\oimpresso.com, /home/x/proj, etc.',
  `git_branch` varchar(150) DEFAULT NULL,
  `cc_version` varchar(20) DEFAULT NULL COMMENT 'Versão do Claude Code: 2.1.119, etc.',
  `entrypoint` varchar(50) DEFAULT NULL COMMENT 'claude-desktop|claude-code-cli|claude-agent-sdk|...',
  `started_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `total_messages` int(10) unsigned NOT NULL DEFAULT 0,
  `total_tokens` bigint(20) unsigned NOT NULL DEFAULT 0,
  `total_cost_usd` decimal(12,6) NOT NULL DEFAULT 0.000000,
  `total_cost_brl` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `status` enum('active','closed','archived') NOT NULL DEFAULT 'active',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Tags, notes, summary auto-gerado, etc.' CHECK (json_valid(`metadata`)),
  `summary_auto` text DEFAULT NULL COMMENT 'Sumário compacto LLM-gerado (~200 tokens) pra search rápido',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_cc_sessions_session_uuid_unique` (`session_uuid`),
  KEY `cc_sess_user_started_idx` (`user_id`,`started_at`),
  KEY `cc_sess_proj_started_idx` (`project_path`,`started_at`),
  KEY `cc_sess_biz_started_idx` (`business_id`,`started_at`),
  KEY `mcp_cc_sessions_user_id_index` (`user_id`),
  KEY `mcp_cc_sessions_business_id_index` (`business_id`),
  FULLTEXT KEY `cc_sess_summary_ft` (`summary_auto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_components` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `key` varchar(24) NOT NULL COMMENT 'Ex: FE, BE, INFRA, MEM — único dentro do projeto',
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `lead_user_id` bigint(20) unsigned DEFAULT NULL,
  `color` varchar(16) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mcp_components_project_key` (`project_id`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_confidence_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_confidence_scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(50) NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `score` decimal(4,3) NOT NULL DEFAULT 0.500,
  `sample_size` smallint(5) unsigned NOT NULL DEFAULT 0,
  `hitl_level` tinyint(4) NOT NULL DEFAULT 2,
  `last_outcome` enum('success','fail','wagner_modified','wagner_rejected','cancelled') DEFAULT NULL,
  `consecutive_approvals` smallint(5) unsigned NOT NULL DEFAULT 0,
  `consecutive_failures` smallint(5) unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_confidence_domain_type` (`domain`,`event_type`),
  KEY `mcp_confidence_scores_score_index` (`score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_cycle_goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_cycle_goals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` bigint(20) unsigned NOT NULL,
  `description` text NOT NULL,
  `metric_name` varchar(80) DEFAULT NULL COMMENT 'Slug da métrica trackable (ex: memoria_recall_chars)',
  `target_value` varchar(80) DEFAULT NULL COMMENT 'Valor alvo em string (suporta números, "true", "yes", etc)',
  `achieved_value` varchar(80) DEFAULT NULL COMMENT 'Valor atual/atingido — atualizado via cycle-goals-track',
  `status` enum('open','done','missed') NOT NULL DEFAULT 'open',
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mcp_cycle_goals_cycle_status` (`cycle_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_cycles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_cycles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `key` varchar(24) NOT NULL COMMENT 'Ex: CYCLE-01, CYCLE-02 — único dentro do projeto',
  `name` varchar(100) DEFAULT NULL COMMENT 'Label descritivo opcional',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `goal` text DEFAULT NULL COMMENT 'Outcome-oriented goal do cycle (1-2 frases)',
  `status` enum('planning','active','closed') NOT NULL DEFAULT 'planning',
  `retro` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Retro JSON: {sucessos:[...], falhas:[...], licao_prox:""}' CHECK (json_valid(`retro`)),
  `owner_user_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK lógico users.id — quem fechou/conduziu o cycle',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mcp_cycles_project_key` (`project_id`,`key`),
  KEY `idx_mcp_cycles_project_status` (`project_id`,`status`),
  KEY `idx_mcp_cycles_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_decision_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_decision_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `target_type` varchar(30) NOT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  `adr_slug` varchar(200) NOT NULL,
  `relation` varchar(30) NOT NULL DEFAULT 'referenced',
  `created_by` varchar(50) NOT NULL DEFAULT 'system',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_decision_link` (`target_type`,`target_id`,`adr_slug`,`relation`),
  KEY `idx_link_reverse` (`adr_slug`,`target_type`),
  KEY `idx_link_forward` (`target_type`,`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_decision_patterns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_decision_patterns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `domain` varchar(50) NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `pattern_hash` char(64) NOT NULL,
  `description` text NOT NULL,
  `example_decision_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`example_decision_ids`)),
  `success_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `total_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `success_rate` decimal(4,3) NOT NULL DEFAULT 0.000,
  `is_hardcoded` tinyint(1) NOT NULL DEFAULT 0,
  `approved_by_wagner` tinyint(1) NOT NULL DEFAULT 0,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_decision_patterns_pattern_hash_unique` (`pattern_hash`),
  KEY `idx_dp_domain_type` (`domain`,`event_type`),
  KEY `idx_dp_rate` (`success_rate`),
  KEY `mcp_decision_patterns_business_id_foreign` (`business_id`),
  CONSTRAINT `mcp_decision_patterns_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_decision_thresholds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_decision_thresholds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(50) NOT NULL DEFAULT '*',
  `event_type` varchar(80) NOT NULL DEFAULT '*',
  `brain_a_risk_max` decimal(4,3) NOT NULL DEFAULT 0.300,
  `brain_a_conf_min` decimal(4,3) NOT NULL DEFAULT 0.700,
  `brain_b_risk_max` decimal(4,3) NOT NULL DEFAULT 0.700,
  `approved_by` varchar(50) NOT NULL DEFAULT 'system',
  `approved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_threshold_domain_type` (`domain`,`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_doc_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_doc_summaries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `content_hash` char(32) NOT NULL,
  `original_size` int(10) unsigned NOT NULL,
  `summary` text NOT NULL,
  `tokens_in` int(10) unsigned NOT NULL DEFAULT 0,
  `tokens_out` int(10) unsigned NOT NULL DEFAULT 0,
  `cost_brl` decimal(10,6) NOT NULL DEFAULT 0.000000 COMMENT 'Custo R$ acumulado (USD * câmbio config)',
  `model` varchar(50) NOT NULL DEFAULT 'gpt-4o-mini',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_doc_summary_hash_model` (`content_hash`,`model`),
  KEY `idx_doc_summary_hash` (`content_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_dual_brain_decisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_dual_brain_decisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_decision_id` bigint(20) unsigned DEFAULT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `part_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(80) NOT NULL,
  `event_source` enum('brain_a','evolution_agent','wagner','scheduler') NOT NULL,
  `auto_generated` tinyint(1) NOT NULL DEFAULT 0,
  `domain` varchar(50) NOT NULL,
  `files_affected` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`files_affected`)),
  `event_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`event_metadata`)),
  `risk_score` decimal(4,3) NOT NULL,
  `confidence_score` decimal(4,3) NOT NULL,
  `policy_applied` varchar(50) DEFAULT NULL,
  `destination` enum('brain_a','brain_b','pending_wagner','blocked','queued') NOT NULL,
  `hitl_level` tinyint(4) NOT NULL DEFAULT 2,
  `brain_used` enum('brain_a','brain_b','human','none') NOT NULL,
  `model_used` varchar(50) DEFAULT NULL,
  `instruction_generated` text DEFAULT NULL,
  `tokens_used` int(10) unsigned DEFAULT NULL,
  `cost_usd` decimal(8,6) DEFAULT NULL,
  `execution_ms` int(10) unsigned DEFAULT NULL,
  `outcome` enum('success','fail','wagner_modified','wagner_rejected','cancelled','expired') NOT NULL DEFAULT 'cancelled',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `review_score` tinyint(3) unsigned DEFAULT NULL,
  `review_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`review_breakdown`)),
  `review_confidence` decimal(4,3) DEFAULT NULL,
  `next_retry_at` timestamp NULL DEFAULT NULL,
  `wagner_modified_to` text DEFAULT NULL,
  `diff_size_pct` tinyint(4) DEFAULT NULL,
  `pr_url` varchar(255) DEFAULT NULL,
  `commit_sha` char(40) DEFAULT NULL,
  `conflict_type` varchar(80) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dbd_domain_type` (`domain`,`event_type`),
  KEY `idx_dbd_outcome` (`outcome`),
  KEY `idx_dbd_biz_created` (`business_id`,`created_at`),
  KEY `idx_dbd_conflict` (`conflict_type`),
  KEY `idx_dbd_project` (`project_id`),
  KEY `idx_dbd_part` (`part_id`),
  KEY `idx_dbd_parent` (`parent_decision_id`),
  KEY `idx_dbd_next_retry` (`next_retry_at`),
  KEY `idx_dbd_review` (`review_score`),
  KEY `idx_dbd_auto_gen` (`auto_generated`),
  CONSTRAINT `mcp_dual_brain_decisions_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_epics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_epics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `key` varchar(24) NOT NULL COMMENT 'Ex: COPI-EP-001 — único dentro do projeto',
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `owner` varchar(60) DEFAULT NULL,
  `target_quarter` varchar(16) DEFAULT NULL COMMENT 'Ex: Q2-2026, Q3-2026',
  `status` enum('planning','active','done','cancelled') NOT NULL DEFAULT 'planning',
  `color` varchar(16) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mcp_epics_project_key` (`project_id`,`key`),
  KEY `idx_mcp_epics_project_status` (`project_id`,`status`),
  KEY `idx_mcp_epics_owner` (`owner`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_file_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_file_locks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file_path` varchar(500) NOT NULL,
  `locked_by` varchar(50) NOT NULL,
  `decision_id` bigint(20) unsigned DEFAULT NULL,
  `locked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_file_locks_file_path_unique` (`file_path`),
  KEY `mcp_file_locks_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_git_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_git_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` varchar(40) NOT NULL,
  `action` enum('refs','fixes','branch','pr_opened','pr_reviewed','pr_merged','pr_closed') NOT NULL,
  `repo_full_name` varchar(120) DEFAULT NULL COMMENT 'Ex: wagnerra23/oimpresso.com',
  `commit_sha` varchar(40) DEFAULT NULL,
  `pr_number` int(10) unsigned DEFAULT NULL,
  `branch` varchar(200) DEFAULT NULL,
  `author_username` varchar(60) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `occurred_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mcp_git_links_task` (`task_id`),
  KEY `idx_mcp_git_links_commit` (`commit_sha`),
  KEY `idx_mcp_git_links_pr` (`repo_full_name`,`pr_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_governance_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_governance_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rule_key` varchar(80) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `category` enum('promotion','archival','escalation','retry','budget','review') NOT NULL,
  `condition` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`condition`)),
  `action` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`action`)),
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `version` smallint(5) unsigned NOT NULL DEFAULT 1,
  `triggered_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `last_triggered_at` timestamp NULL DEFAULT NULL,
  `created_by` varchar(50) NOT NULL DEFAULT 'system',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_governance_rules_rule_key_unique` (`rule_key`),
  KEY `idx_gov_enabled` (`enabled`),
  KEY `idx_gov_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_handoff_diffs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_handoff_diffs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `since` varchar(50) NOT NULL,
  `events_hash` char(32) NOT NULL,
  `output_md` longtext DEFAULT NULL,
  `output_json` longtext DEFAULT NULL,
  `tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `cost_brl` decimal(10,6) NOT NULL DEFAULT 0.000000 COMMENT 'Custo R$ acumulado (zero se síntese rule-based)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_handoff_diff_since_hash` (`since`,`events_hash`),
  KEY `idx_handoff_diff_since` (`since`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_handoff_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_handoff_summaries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(200) NOT NULL,
  `content_hash` char(32) NOT NULL,
  `summary_compact` text DEFAULT NULL,
  `summary_detailed` text DEFAULT NULL,
  `tokens_in` int(10) unsigned NOT NULL DEFAULT 0,
  `tokens_out` int(10) unsigned NOT NULL DEFAULT 0,
  `cost_brl` decimal(10,6) NOT NULL DEFAULT 0.000000 COMMENT 'Custo R$ acumulado (compact + detailed)',
  `model` varchar(50) NOT NULL DEFAULT 'gpt-4o-mini',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_handoff_filename_hash` (`filename`,`content_hash`),
  KEY `idx_handoff_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_inbox_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_inbox_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'Destinatário',
  `type` enum('mention','assigned','review_requested','status_changed','commented','due_soon','blocked_resolved') NOT NULL,
  `task_id` varchar(40) DEFAULT NULL COMMENT 'Ref a mcp_tasks.task_id (ou identifier)',
  `actor_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Quem causou (NULL = system)',
  `body` text DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Contexto extra: from/to status, comment_id, etc' CHECK (json_valid(`payload`)),
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mcp_inbox_user_read` (`user_id`,`read_at`),
  KEY `idx_mcp_inbox_task` (`task_id`),
  KEY `idx_mcp_inbox_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_issue_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_issue_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL = template global (todos projetos)',
  `type` enum('story','task','bug','spike','chore') NOT NULL DEFAULT 'task',
  `name` varchar(80) NOT NULL,
  `body_template` text NOT NULL COMMENT 'Markdown pré-preenchido (suporta ## Steps to reproduce, etc)',
  `default_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '{priority,labels,estimate_unit,estimate_value,custom_fields}' CHECK (json_valid(`default_fields`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mcp_issue_templates_scope_name` (`project_id`,`type`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_jira_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_jira_projects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(16) NOT NULL COMMENT 'Linear-style: COPI, NFSE, FIN. Maiúsculo, sem espaço.',
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `lead_user_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Owner do projeto (FK lógico pra users.id, sem FK física)',
  `color` varchar(16) DEFAULT NULL COMMENT 'Hex ou nome Tailwind para UI',
  `icon` varchar(32) DEFAULT NULL COMMENT 'Lucide icon name',
  `status` enum('active','archived') NOT NULL DEFAULT 'active',
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Settings por projeto: default_workflow_id, default_estimate_unit, etc' CHECK (json_valid(`settings`)),
  `default_workflow_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK lógico mcp_workflows.id — workflow default das tasks deste projeto',
  `custom_field_schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Schema dos custom fields aceitos: [{key,type,label,options}]' CHECK (json_valid(`custom_field_schema`)),
  `next_task_number` int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'Contador atômico — próximo número de identifier',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_jira_projects_key_unique` (`key`),
  KEY `idx_mcp_jira_projects_status` (`status`),
  KEY `idx_mcp_jira_projects_lead` (`lead_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_memory_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_memory_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Empresa dona deste documento. NULL = global. 1 = oimpresso dev (ADRs).',
  `slug` varchar(200) NOT NULL COMMENT 'Identificador único, ex: 0046-chat-agent-gap-contexto-rico',
  `type` enum('adr','session','reference','spec','handoff','current','tasks','other','comparativo','audit','runbook','changelog') NOT NULL,
  `module` varchar(50) DEFAULT NULL COMMENT 'copiloto | financeiro | core | infra | null',
  `status` varchar(20) DEFAULT NULL COMMENT 'rascunho|proposto|aceito|deprecated|superseded — frontmatter status',
  `authority` varchar(20) DEFAULT NULL COMMENT 'canonical|reference|exploratory — peso da fonte',
  `lifecycle` varchar(20) DEFAULT NULL COMMENT 'ativo|arquivado|substituido',
  `quarter` varchar(10) DEFAULT NULL COMMENT 'YYYY-Qn — ex: 2026-Q2',
  `decided_at` date DEFAULT NULL COMMENT 'Data da decisão (frontmatter decided_at)',
  `decided_by` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Iniciais do TEAM.md, ex: ["W", "F"]' CHECK (json_valid(`decided_by`)),
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Tags semânticas, ex: ["mcp", "lgpd"]' CHECK (json_valid(`tags`)),
  `supersedes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Slugs de ADRs substituídas (full + partial mergeados)' CHECK (json_valid(`supersedes`)),
  `superseded_by` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Slugs de ADRs que substituíram esta' CHECK (json_valid(`superseded_by`)),
  `related` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Slugs de ADRs relacionadas (não-substituidoras)' CHECK (json_valid(`related`)),
  `has_pii` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'true se corpo contém PII redactada',
  `title` varchar(250) NOT NULL,
  `content_md` mediumtext NOT NULL COMMENT 'Conteúdo redactado (sem PII) em Markdown',
  `contextual_context` text DEFAULT NULL COMMENT 'Contextual Retrieval Anthropic: contexto 50-100 tokens descrevendo doc',
  `contextual_indexed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Flag se contextualização Anthropic já rodou (backfill control)',
  `contextualized_at` timestamp NULL DEFAULT NULL COMMENT 'Quando contextual_context foi gerado (cache invalidation)',
  `scope_required` varchar(100) DEFAULT NULL COMMENT 'Se setado, exige Spatie permission. null = público pra autenticados',
  `admin_only` tinyint(1) NOT NULL DEFAULT 0,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Frontmatter parseado: data, autor, status ADR, etc' CHECK (json_valid(`metadata`)),
  `git_sha` varchar(40) DEFAULT NULL COMMENT 'SHA do commit que gerou esta versão',
  `git_path` varchar(300) NOT NULL COMMENT 'Caminho original no repo: memory/decisions/0046-...',
  `pii_redactions_count` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Quantos campos foram redactados pelo PII redactor',
  `embedding` blob DEFAULT NULL COMMENT 'Vector embedding (futuro) — text-embedding-3-small 1536-dim',
  `indexed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_memory_documents_slug_unique` (`slug`),
  KEY `mcp_md_type_idx` (`type`),
  KEY `mcp_md_module_idx` (`module`),
  KEY `mcp_md_sha_idx` (`git_sha`),
  KEY `mcp_md_perms_idx` (`scope_required`,`admin_only`),
  KEY `mcp_memory_documents_type_index` (`type`),
  KEY `mcp_md_biz_idx` (`business_id`),
  KEY `mcp_md_status_idx` (`status`),
  KEY `mcp_md_authority_idx` (`authority`),
  KEY `mcp_md_lifecycle_idx` (`lifecycle`),
  KEY `mcp_md_quarter_idx` (`quarter`),
  KEY `mcp_md_decided_at_idx` (`decided_at`),
  KEY `mcp_md_type_status_life_idx` (`type`,`status`,`lifecycle`),
  KEY `mcp_md_ctx_idx` (`contextual_indexed`),
  FULLTEXT KEY `mcp_md_fulltext_idx` (`title`,`content_md`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_memory_documents_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_memory_documents_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint(20) unsigned NOT NULL,
  `slug` varchar(200) NOT NULL,
  `git_sha` varchar(40) DEFAULT NULL,
  `title` varchar(250) NOT NULL,
  `content_md` mediumtext NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `changed_by_user_id` int(10) unsigned DEFAULT NULL COMMENT 'User que fez o git push (null se via CI/cron sync)',
  `change_reason` varchar(100) DEFAULT NULL COMMENT 'webhook | manual | cron_fallback',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `mcp_mh_doc_changed_idx` (`document_id`,`changed_at`),
  KEY `mcp_mh_slug_changed_idx` (`slug`,`changed_at`),
  KEY `mcp_mh_sha_idx` (`git_sha`),
  CONSTRAINT `mcp_memory_documents_history_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `mcp_memory_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_project_parts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_project_parts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `ordem` smallint(5) unsigned NOT NULL DEFAULT 1,
  `codigo` varchar(50) NOT NULL,
  `nome` varchar(200) NOT NULL,
  `objetivo` text NOT NULL,
  `dependencias` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dependencias`)),
  `arquivos_estimados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`arquivos_estimados`)),
  `status` enum('pending','planning','in_progress','done','blocked','cancelled') NOT NULL DEFAULT 'pending',
  `viability_score` tinyint(3) unsigned DEFAULT NULL,
  `risco` tinyint(3) unsigned DEFAULT NULL,
  `estimativa_horas` smallint(5) unsigned DEFAULT NULL,
  `valor_estimado_brl` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_part_project_codigo` (`project_id`,`codigo`),
  KEY `mcp_project_parts_status_index` (`status`),
  CONSTRAINT `mcp_project_parts_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `mcp_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_projects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nome` varchar(200) NOT NULL,
  `objetivo_macro` text NOT NULL,
  `metricas_sucesso` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`metricas_sucesso`)),
  `constraints` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`constraints`)),
  `status` enum('draft','active','paused','completed','killed') NOT NULL DEFAULT 'draft',
  `decision` enum('pending','proceed','pivot','kill') NOT NULL DEFAULT 'pending',
  `viability_score` tinyint(3) unsigned DEFAULT NULL,
  `viability_factors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`viability_factors`)),
  `custo_estimado_brl` decimal(12,2) DEFAULT NULL,
  `valor_estimado_brl` decimal(12,2) DEFAULT NULL,
  `prazo_estimado_dias` smallint(5) unsigned DEFAULT NULL,
  `owner` varchar(50) NOT NULL DEFAULT 'wagner',
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_projects_codigo_unique` (`codigo`),
  KEY `mcp_projects_business_id_foreign` (`business_id`),
  KEY `mcp_projects_status_index` (`status`),
  KEY `mcp_projects_decision_index` (`decision`),
  CONSTRAINT `mcp_projects_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_quotas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_quotas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `period` enum('daily','weekly','monthly') NOT NULL DEFAULT 'monthly',
  `kind` enum('tokens','brl','calls') NOT NULL DEFAULT 'brl',
  `limit` decimal(14,4) NOT NULL COMMENT 'Limite no período (em tokens, BRL ou nº de calls)',
  `current_usage` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `reset_at` timestamp NOT NULL COMMENT 'Próximo reset automático (calculado por period)',
  `block_on_exceed` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'true = retorna 429; false = só alerta, deixa passar',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_qt_user_period_kind_ux` (`user_id`,`period`,`kind`),
  CONSTRAINT `mcp_quotas_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_scopes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_scopes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) NOT NULL COMMENT 'Spatie permission name, ex: copiloto.mcp.tasks.read',
  `nome` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `resources_pattern` varchar(200) DEFAULT NULL COMMENT 'Glob/regex: oimpresso://memory/decisions/* — null=nenhum',
  `tools_pattern` varchar(200) DEFAULT NULL COMMENT 'Glob/regex: tasks.*, decisions.* — null=nenhum',
  `is_destructive` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Operação destrutiva exige approval flow',
  `business_required` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Exige business_id no contexto da chamada',
  `admin_only` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Só users com role superadmin podem invocar',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_scopes_slug_unique` (`slug`),
  KEY `mcp_scopes_slug_idx` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_scorecard_ai_suggestions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_scorecard_ai_suggestions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(100) NOT NULL,
  `deterministic_score` smallint(5) unsigned NOT NULL,
  `ai_suggested_delta` smallint(6) NOT NULL,
  `ai_justificativa` text NOT NULL,
  `ai_model` varchar(50) NOT NULL,
  `confidence` decimal(3,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_module_created` (`module`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_skill_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_skill_approvals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version_id` bigint(20) unsigned NOT NULL,
  `approver_id` bigint(20) unsigned NOT NULL,
  `decision` enum('approve','reject','request_changes') NOT NULL,
  `comment` text DEFAULT NULL,
  `decided_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `test_runs_count` int(10) unsigned NOT NULL DEFAULT 0,
  `test_runs_pass` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_approvals_version_decided` (`version_id`,`decided_at`),
  CONSTRAINT `mcp_skill_approvals_version_id_foreign` FOREIGN KEY (`version_id`) REFERENCES `mcp_skill_versions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_skill_labels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_skill_labels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `skill_id` bigint(20) unsigned NOT NULL,
  `label` enum('production','staging','dev') NOT NULL,
  `version_id` bigint(20) unsigned NOT NULL,
  `moved_by` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL na criação inicial via seeder/import',
  `moved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `previous_version_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Versão anterior antes de mover label (audit rollback)',
  `reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_skill_label` (`skill_id`,`label`),
  KEY `idx_labels_version` (`version_id`),
  CONSTRAINT `mcp_skill_labels_skill_id_foreign` FOREIGN KEY (`skill_id`) REFERENCES `mcp_skills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mcp_skill_labels_version_id_foreign` FOREIGN KEY (`version_id`) REFERENCES `mcp_skill_versions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_skill_telemetry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_skill_telemetry` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `skill_name` varchar(128) NOT NULL,
  `agent_id` varchar(128) NOT NULL,
  `triggered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) NOT NULL DEFAULT 1,
  `tokens_saved_estimate` int(11) DEFAULT NULL,
  `context_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context_payload`)),
  PRIMARY KEY (`id`),
  KEY `idx_skill_telemetry_recent` (`skill_name`,`triggered_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_skill_test_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_skill_test_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version_id` bigint(20) unsigned NOT NULL,
  `input_source` enum('manual','real_conversations','fixture') NOT NULL DEFAULT 'manual',
  `input_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Input enviado: prompt + contexto. PII redacted antes de gravar' CHECK (json_valid(`input_json`)),
  `output` mediumtext DEFAULT NULL,
  `output_tokens` int(10) unsigned DEFAULT NULL,
  `latency_ms` int(10) unsigned DEFAULT NULL,
  `business_id_scope` bigint(20) unsigned DEFAULT NULL COMMENT 'Se input_source=real_conversations, qual business_id foi usado',
  `pii_redactions_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `passed` tinyint(1) DEFAULT NULL COMMENT 'Manual pelo dev: clicou approve no resultado? null=não avaliado',
  `pass_reason` text DEFAULT NULL,
  `executed_by` bigint(20) unsigned DEFAULT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_test_runs_version` (`version_id`,`executed_at`),
  CONSTRAINT `mcp_skill_test_runs_version_id_foreign` FOREIGN KEY (`version_id`) REFERENCES `mcp_skill_versions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_skill_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_skill_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `skill_id` bigint(20) unsigned NOT NULL,
  `version` int(10) unsigned NOT NULL COMMENT 'Auto-increment por skill (v1, v2, ...)',
  `body_markdown` mediumtext NOT NULL,
  `frontmatter_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`frontmatter_json`)),
  `rationale_problem` text DEFAULT NULL,
  `rationale_hypothesis` text DEFAULT NULL,
  `rationale_success_metric` text DEFAULT NULL,
  `rationale_rollback` text DEFAULT NULL,
  `origin` enum('ui','git_drift','git_seed') NOT NULL DEFAULT 'ui' COMMENT 'ui=editor humano; git_drift=webhook detectou; git_seed=import inicial',
  `status` enum('draft','review','published','drift_pending','archived') NOT NULL DEFAULT 'draft',
  `git_sha` varchar(40) DEFAULT NULL,
  `pr_number` int(10) unsigned DEFAULT NULL COMMENT 'Número do PR criado pelo Publish-to-git (NULL pra versões só em DB)',
  `published_to_git_at` timestamp NULL DEFAULT NULL,
  `pii_redactions_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL pra versões origin=git_drift criadas pelo webhook',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_skill_version` (`skill_id`,`version`),
  KEY `idx_versions_skill_status` (`skill_id`,`status`),
  KEY `idx_versions_origin` (`origin`),
  CONSTRAINT `mcp_skill_versions_skill_id_foreign` FOREIGN KEY (`skill_id`) REFERENCES `mcp_skills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_skills` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) NOT NULL COMMENT 'Nome da skill = pasta = name do frontmatter',
  `business_id` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL = global (visível pra todo tenant); setado = só esse business',
  `source` enum('claude-code','plugin','custom') NOT NULL DEFAULT 'claude-code',
  `status` enum('draft','review','published','archived') NOT NULL DEFAULT 'draft',
  `current_version_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK pra mcp_skill_versions; NULL antes da v1',
  `module` varchar(50) DEFAULT NULL,
  `origin` enum('imported','created') NOT NULL DEFAULT 'imported' COMMENT 'imported = veio do git no seed; created = criada via UI',
  `git_sync_mode` enum('auto','manual','pinned') NOT NULL DEFAULT 'manual' COMMENT 'auto=aceita drift sem revisar; manual=drift alert; pinned=ignora git',
  `auto_publish_to_git` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Approve UI + TRUE = cria PR git auto; FALSE = ação manual separada',
  `git_path` varchar(300) DEFAULT NULL COMMENT 'NULL pra skills criadas na UI antes do primeiro publish',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_skills_slug_unique` (`slug`),
  KEY `idx_skills_status_biz` (`status`,`business_id`),
  KEY `idx_skills_module` (`module`),
  KEY `idx_skills_source` (`source`),
  KEY `idx_skills_sync_mode` (`git_sync_mode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_task_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_task_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` varchar(40) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_url` varchar(500) NOT NULL,
  `sha256` varchar(64) NOT NULL,
  `mime_type` varchar(80) DEFAULT NULL,
  `size_bytes` bigint(20) unsigned DEFAULT NULL,
  `uploaded_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mcp_task_attach_task` (`task_id`),
  KEY `idx_mcp_task_attach_sha256` (`sha256`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_task_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_task_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` varchar(40) NOT NULL COMMENT 'US-NNN-MMM — ref à mcp_tasks.task_id (sem FK pra tolerância)',
  `author` varchar(60) NOT NULL COMMENT 'Username de quem comentou (ex: wagner, eliana)',
  `body` text NOT NULL COMMENT 'Texto do comentário em markdown',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mcp_task_comments_task_id` (`task_id`),
  KEY `idx_mcp_task_comments_author` (`author`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_task_dependencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_task_dependencies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` varchar(40) NOT NULL COMMENT 'Task que tem a dependência',
  `depends_on_task_id` varchar(40) NOT NULL COMMENT 'Task da qual depende',
  `type` enum('blocks','relates','duplicates','clones') NOT NULL DEFAULT 'blocks',
  `created_by` varchar(60) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mcp_task_dep_pair` (`task_id`,`depends_on_task_id`,`type`),
  KEY `idx_mcp_task_dep_task` (`task_id`),
  KEY `idx_mcp_task_dep_target` (`depends_on_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_task_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_task_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` varchar(40) NOT NULL COMMENT 'US-NNN-MMM — ref à mcp_tasks.task_id',
  `event_type` enum('created','status_changed','assigned','field_updated','commented','cancelled') NOT NULL COMMENT 'Tipo do evento para filtro/timeline',
  `from_value` varchar(255) DEFAULT NULL COMMENT 'Valor anterior (ex: "todo", "wagner")',
  `to_value` varchar(255) DEFAULT NULL COMMENT 'Valor novo (ex: "doing", "eliana")',
  `author` varchar(60) DEFAULT NULL COMMENT 'Quem causou o evento (username ou "system")',
  `note` text DEFAULT NULL COMMENT 'Descrição livre (ex: campo atualizado, motivo)',
  `occurred_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Quando ocorreu — imutável',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mcp_task_events_task_timeline` (`task_id`,`occurred_at`),
  KEY `idx_mcp_task_events_type` (`event_type`),
  KEY `idx_mcp_task_events_author` (`author`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_task_memory_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_task_memory_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` varchar(40) NOT NULL,
  `memory_document_id` bigint(20) unsigned NOT NULL COMMENT 'FK lógico mcp_memory_documents.id',
  `link_type` enum('relates','spec','adr','session','comparativo','runbook') NOT NULL DEFAULT 'relates',
  `created_by` varchar(60) DEFAULT NULL COMMENT 'Username ou "ai-suggest" se gerado por D2',
  `confirmed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se ai-suggest, precisa confirmação humana antes de exibir como canônico',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mcp_task_memory_links` (`task_id`,`memory_document_id`,`link_type`),
  KEY `idx_mcp_task_memory_links_task` (`task_id`),
  KEY `idx_mcp_task_memory_links_doc` (`memory_document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_task_watchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_task_watchers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` varchar(40) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mcp_task_watchers_task_user` (`task_id`,`user_id`),
  KEY `idx_mcp_task_watchers_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` varchar(40) NOT NULL COMMENT 'Identificador canônico, ex: US-NFSE-001',
  `identifier` varchar(24) DEFAULT NULL COMMENT 'Linear-style: <PROJECT_KEY>-<NNNN>, ex: COPI-123',
  `project_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK lógico mcp_jira_projects.id',
  `epic_id` bigint(20) unsigned DEFAULT NULL,
  `cycle_id` bigint(20) unsigned DEFAULT NULL,
  `component_id` bigint(20) unsigned DEFAULT NULL,
  `parent_task_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Para subtasks — FK lógico mcp_tasks.id',
  `type` enum('story','task','bug','spike','chore','epic-stub') NOT NULL DEFAULT 'story',
  `module` varchar(60) NOT NULL COMMENT 'Módulo do source (NFSe, Copiloto, Financeiro...)',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('backlog','todo','doing','review','done','blocked','cancelled') NOT NULL DEFAULT 'todo',
  `owner` varchar(60) DEFAULT NULL COMMENT 'Username dev ou null se não atribuído',
  `sprint` varchar(40) DEFAULT NULL COMMENT 'Sprint A/B/C/D ou semana ISO 2026-W18',
  `priority` enum('p0','p1','p2','p3') DEFAULT 'p2',
  `estimate_h` decimal(5,1) DEFAULT NULL COMMENT 'Estimativa em horas',
  `story_points` decimal(5,1) DEFAULT NULL,
  `estimate_unit` enum('points','hours','days','tshirt','fibonacci') NOT NULL DEFAULT 'points',
  `estimate_value` decimal(8,2) DEFAULT NULL COMMENT 'Valor numérico ou index (tshirt: 1=XS,2=S,3=M,4=L,5=XL)',
  `due_date` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `labels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array de strings: ["lgpd","perf","tier-a"]' CHECK (json_valid(`labels`)),
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Schema validável via mcp_jira_projects.custom_field_schema' CHECK (json_valid(`custom_fields`)),
  `blocked_by` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array de task_ids que bloqueiam esta' CHECK (json_valid(`blocked_by`)),
  `source_path` varchar(500) NOT NULL COMMENT 'Path relativo do SPEC, ex: memory/requisitos/NFSe/SPEC.md#US-NFSE-001',
  `source_git_sha` varchar(40) DEFAULT NULL COMMENT 'Commit SHA do último parse',
  `parsed_at` timestamp NOT NULL COMMENT 'Quando foi parseado pela última vez',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_tasks_task_id_unique` (`task_id`),
  UNIQUE KEY `uq_mcp_tasks_identifier` (`identifier`),
  KEY `idx_mcp_tasks_module_status` (`module`,`status`),
  KEY `idx_mcp_tasks_owner_status` (`owner`,`status`),
  KEY `idx_mcp_tasks_sprint` (`sprint`),
  KEY `idx_mcp_tasks_priority` (`priority`),
  KEY `idx_mcp_tasks_proj_cycle_status` (`project_id`,`cycle_id`,`status`),
  KEY `idx_mcp_tasks_epic` (`epic_id`),
  KEY `idx_mcp_tasks_component` (`component_id`),
  KEY `idx_mcp_tasks_parent` (`parent_task_id`),
  KEY `idx_mcp_tasks_due` (`due_date`),
  KEY `idx_mcp_tasks_completed` (`completed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL COMMENT 'Identificador human-readable: "Wagner laptop", "Felipe desktop"',
  `sha256_token` varchar(64) NOT NULL COMMENT 'SHA256 do token raw — NUNCA armazena claro',
  `scopes_cache` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Snapshot dos scopes na geração (cache pra evitar JOIN em cada chamada)' CHECK (json_valid(`scopes_cache`)),
  `user_agent` varchar(200) DEFAULT NULL,
  `last_used_ip` varchar(45) DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'null = não expira (revogar manual); setado = expira automático',
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoked_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_tokens_sha256_token_unique` (`sha256_token`),
  KEY `mcp_tk_user_idx` (`user_id`,`revoked_at`),
  KEY `mcp_tokens_actor_id_index` (`actor_id`),
  CONSTRAINT `mcp_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_tool_executions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_tool_executions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL DEFAULT 1,
  `decision_id` bigint(20) unsigned DEFAULT NULL,
  `tool_name` varchar(80) NOT NULL,
  `is_read_only` tinyint(1) NOT NULL,
  `input` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`input`)),
  `ok` tinyint(1) NOT NULL,
  `output` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`output`)),
  `error` text DEFAULT NULL,
  `duration_ms` int(10) unsigned DEFAULT NULL,
  `triggered_by` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tool_exec_name_time` (`tool_name`,`created_at`),
  KEY `idx_tool_exec_decision` (`decision_id`),
  KEY `idx_tool_exec_ok` (`ok`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_usage_diaria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_usage_diaria` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dia` date NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `business_id` int(10) unsigned DEFAULT NULL COMMENT 'null = agregado plataforma',
  `total_calls` int(10) unsigned NOT NULL DEFAULT 0,
  `calls_ok` int(10) unsigned NOT NULL DEFAULT 0,
  `calls_denied` int(10) unsigned NOT NULL DEFAULT 0,
  `calls_quota_exceeded` int(10) unsigned NOT NULL DEFAULT 0,
  `calls_error` int(10) unsigned NOT NULL DEFAULT 0,
  `total_tokens_in` bigint(20) unsigned NOT NULL DEFAULT 0,
  `total_tokens_out` bigint(20) unsigned NOT NULL DEFAULT 0,
  `total_cache_read` bigint(20) unsigned NOT NULL DEFAULT 0,
  `total_cache_write` bigint(20) unsigned NOT NULL DEFAULT 0,
  `custo_brl` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `top_tools` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '[{"tool":"decisions.fetch","calls":42}, ...] top 5' CHECK (json_valid(`top_tools`)),
  `alertas_disparados` int(10) unsigned NOT NULL DEFAULT 0,
  `excedeu_quota` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_ud_dia_user_biz_ux` (`dia`,`user_id`,`business_id`),
  KEY `mcp_ud_dia_idx` (`dia`),
  KEY `mcp_ud_user_dia_idx` (`user_id`,`dia`),
  KEY `mcp_usage_diaria_business_id_foreign` (`business_id`),
  CONSTRAINT `mcp_usage_diaria_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mcp_usage_diaria_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_user_module_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_user_module_access` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `module` varchar(50) NOT NULL,
  `can_read` tinyint(1) NOT NULL DEFAULT 1,
  `can_write` tinyint(1) NOT NULL DEFAULT 0,
  `can_execute_tools` tinyint(1) NOT NULL DEFAULT 0,
  `can_commit` tinyint(1) NOT NULL DEFAULT 0,
  `granted_by` varchar(50) NOT NULL,
  `reason` text DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_module` (`user_id`,`module`),
  KEY `idx_module_lookup` (`module`),
  CONSTRAINT `mcp_user_module_access_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_user_scopes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_user_scopes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `scope_id` bigint(20) unsigned NOT NULL,
  `business_id` int(10) unsigned DEFAULT NULL COMMENT 'null = todos os businesses; setado = limita a esse tenant',
  `granted_by` int(10) unsigned DEFAULT NULL COMMENT 'user_id que concedeu o acesso (audit)',
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoked_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mcp_us_user_idx` (`user_id`,`revoked_at`),
  KEY `mcp_us_scope_biz_idx` (`scope_id`,`business_id`),
  KEY `mcp_user_scopes_business_id_foreign` (`business_id`),
  CONSTRAINT `mcp_user_scopes_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mcp_user_scopes_scope_id_foreign` FOREIGN KEY (`scope_id`) REFERENCES `mcp_scopes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mcp_user_scopes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `scope` enum('personal','shared','system') NOT NULL DEFAULT 'personal',
  `filter` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '{module,status,owner,priority,labels,...} estrutura interpretada por TasksListTool' CHECK (json_valid(`filter`)),
  `sort` varchar(60) NOT NULL DEFAULT '-due_date' COMMENT 'Campo de sort com prefixo + ou - (ex: -priority,due_date)',
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mcp_views_user` (`user_id`),
  KEY `idx_mcp_views_project` (`project_id`),
  KEY `idx_mcp_views_scope` (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_weekly_digests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_weekly_digests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `week` varchar(8) NOT NULL,
  `range_start` date NOT NULL,
  `range_end` date NOT NULL,
  `digest_markdown` longtext NOT NULL,
  `metrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON: {commits, prs_merged, us_closed, adrs_new, handoffs, cycle_progress_pct}' CHECK (json_valid(`metrics`)),
  `tokens_in` int(10) unsigned NOT NULL DEFAULT 0,
  `tokens_out` int(10) unsigned NOT NULL DEFAULT 0,
  `cost_brl` decimal(10,6) NOT NULL DEFAULT 0.000000 COMMENT 'Custo R$ deste digest (gpt-4o-mini)',
  `model` varchar(50) NOT NULL DEFAULT 'gpt-4o-mini',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_weekly_digest_week` (`week`),
  KEY `idx_weekly_digest_range_end` (`range_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_workflows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_workflows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL = workflow global (default fallback)',
  `name` varchar(100) NOT NULL,
  `statuses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '[{key,name,category:todo|in-progress|done,color}]' CHECK (json_valid(`statuses`)),
  `transitions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '{"<from>": ["<to>", "<to>"], ...}' CHECK (json_valid(`transitions`)),
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Workflow default do projeto (1 por projeto)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mcp_workflows_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `media` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `file_name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `model_type` varchar(191) NOT NULL,
  `model_media_type` varchar(191) DEFAULT NULL,
  `woocommerce_media_id` int(11) DEFAULT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `media_model_type_model_id_index` (`model_type`,`model_id`),
  KEY `media_woocommerce_media_id_index` (`woocommerce_media_id`),
  KEY `media_business_id_index` (`business_id`),
  KEY `media_uploaded_by_index` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medida_ctes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `medida_ctes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_unidade` varchar(2) NOT NULL,
  `tipo_medida` varchar(20) NOT NULL,
  `quantidade_carga` decimal(10,4) NOT NULL,
  `cte_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medida_ctes_cte_id_foreign` (`cte_id`),
  CONSTRAINT `medida_ctes_cte_id_foreign` FOREIGN KEY (`cte_id`) REFERENCES `ctes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `direction` enum('inbound','outbound') NOT NULL,
  `provider` varchar(30) NOT NULL COMMENT 'whatsapp_meta|whatsapp_zapi|whatsapp_baileys|instagram|messenger|email_imap|email_smtp|mercadolivre',
  `provider_message_id` varchar(128) DEFAULT NULL COMMENT 'wamid.XYZ (Meta) | messageId (Z-API/Baileys) | ig_dm_id | Message-ID header (email) | ml_message_id',
  `type` enum('text','template','image','document','audio','video','interactive','location','contacts','email','ml_question','ml_answer') NOT NULL DEFAULT 'text',
  `template_name` varchar(64) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL COMMENT 'só email',
  `body` text DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'raw provider payload — auditoria + reprocess' CHECK (json_valid(`payload`)),
  `macro_variant_id` bigint(20) unsigned DEFAULT NULL COMMENT 'US-WA-049: variante de macro usada no envio (NULL = nao via macro variant)',
  `media_url` varchar(500) DEFAULT NULL COMMENT 'US-WA-072: path relativo no disco public',
  `media_mime` varchar(100) DEFAULT NULL,
  `media_size_bytes` bigint(20) unsigned DEFAULT NULL,
  `media_duration_s` smallint(5) unsigned DEFAULT NULL COMMENT 'audio/video duration em segundos',
  `media_thumbnail_url` varchar(500) DEFAULT NULL,
  `media_download_status` enum('pending','downloading','success','failed_permanent') NOT NULL DEFAULT 'pending',
  `media_download_attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `media_download_last_attempt_at` timestamp NULL DEFAULT NULL,
  `media_download_failed_reason` varchar(255) DEFAULT NULL,
  `media_transcription` text DEFAULT NULL COMMENT 'Whisper output (audio only)',
  `media_filename` varchar(255) DEFAULT NULL,
  `status` enum('queued','sent','delivered','read','failed','received') NOT NULL,
  `failed_reason` varchar(255) DEFAULT NULL,
  `sender_user_id` int(10) unsigned DEFAULT NULL COMMENT 'só outbound humano',
  `sender_kind` enum('human','bot','system') DEFAULT NULL,
  `is_internal_note` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'US-WA-071: true = nota interna, NUNCA dispatch driver (Tier 0)',
  `cost_centavos` int(10) unsigned DEFAULT NULL COMMENT 'custo provider quando aplicável (Meta janela 24h, ML messaging fee)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `msgs_provider_msg_uniq` (`provider_message_id`),
  KEY `msgs_biz_conv_idx` (`business_id`,`conversation_id`,`created_at`),
  KEY `msgs_biz_status_idx` (`business_id`,`status`,`created_at`),
  KEY `msgs_provider_idx` (`provider`,`created_at`),
  KEY `msgs_biz_conv_internal_idx` (`business_id`,`conversation_id`,`is_internal_note`),
  KEY `msgs_media_pending_idx` (`media_download_status`,`created_at`),
  KEY `msgs_macro_variant_idx` (`macro_variant_id`),
  CONSTRAINT `messages_macro_variant_id_foreign` FOREIGN KEY (`macro_variant_id`) REFERENCES `macro_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mfg_ingredient_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mfg_ingredient_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `business_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mfg_recipe_ingredients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mfg_recipe_ingredients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mfg_recipe_id` int(10) unsigned NOT NULL,
  `variation_id` int(11) NOT NULL,
  `mfg_ingredient_group_id` int(11) DEFAULT NULL,
  `quantity` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `waste_percent` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `sub_unit_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mfg_recipe_ingredients_mfg_recipe_id_foreign` (`mfg_recipe_id`),
  KEY `mfg_recipe_ingredients_mfg_recipe_id_index` (`mfg_recipe_id`),
  KEY `mfg_recipe_ingredients_variation_id_index` (`variation_id`),
  KEY `mfg_recipe_ingredients_sub_unit_id_index` (`sub_unit_id`),
  CONSTRAINT `mfg_recipe_ingredients_mfg_recipe_id_foreign` FOREIGN KEY (`mfg_recipe_id`) REFERENCES `mfg_recipes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mfg_recipes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mfg_recipes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `variation_id` int(11) NOT NULL,
  `instructions` text DEFAULT NULL,
  `waste_percent` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ingredients_cost` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `extra_cost` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `production_cost_type` varchar(191) DEFAULT 'percentage',
  `total_quantity` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `final_price` decimal(22,4) NOT NULL,
  `sub_unit_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mfg_recipes_product_id_index` (`product_id`),
  KEY `mfg_recipes_variation_id_index` (`variation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` int(10) unsigned NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_type_model_id_index` (`model_type`,`model_id`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` int(10) unsigned NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_type_model_id_index` (`model_type`,`model_id`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `natureza_operacaos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `natureza_operacaos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `natureza` varchar(80) NOT NULL,
  `cfop_entrada_estadual` varchar(5) NOT NULL DEFAULT '',
  `cfop_entrada_inter_estadual` varchar(5) NOT NULL DEFAULT '',
  `cfop_saida_estadual` varchar(5) NOT NULL DEFAULT '',
  `cfop_saida_inter_estadual` varchar(5) NOT NULL DEFAULT '',
  `business_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `natureza_operacaos_business_id_foreign` (`business_id`),
  CONSTRAINT `natureza_operacaos_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nf_natureza_operacao`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nf_natureza_operacao` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(200) DEFAULT NULL,
  `tipo_nf` varchar(10) DEFAULT NULL,
  `nfse_codigo` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `dt_alteracao` timestamp NULL DEFAULT NULL,
  `consumidor_final` char(1) DEFAULT NULL,
  `entrada_saida` char(1) DEFAULT NULL,
  `operacao` varchar(50) DEFAULT NULL,
  `tem_tributacao_padrao` char(1) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `officeimpresso_codigo` int(11) DEFAULT NULL,
  `officeimpresso_dt_alteracao` timestamp NULL DEFAULT NULL,
  `business_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nf_natureza_operacao_prodgrupo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nf_natureza_operacao_prodgrupo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` bigint(20) unsigned NOT NULL,
  `nf_natureza_operacao_id` int(10) unsigned NOT NULL,
  `produto_grupo_id` int(10) unsigned NOT NULL,
  `codnf_cst` varchar(4) DEFAULT NULL,
  `codnf_cfop` varchar(9) DEFAULT NULL,
  `codnf_cfop_fora` varchar(9) DEFAULT NULL,
  `picms` double DEFAULT NULL,
  `picmsst` double DEFAULT NULL,
  `pmvast` double DEFAULT NULL,
  `predbc` double DEFAULT NULL,
  `predbcst` double DEFAULT NULL,
  `pis_st` varchar(4) DEFAULT NULL,
  `cofins_st` varchar(4) DEFAULT NULL,
  `ipi_st` varchar(4) DEFAULT NULL,
  `ipi_vbc` double DEFAULT NULL,
  `ipi_qunid` double DEFAULT NULL,
  `ipi_vunid` double DEFAULT NULL,
  `ipi_pipi` double DEFAULT NULL,
  `ipi_vipi` double DEFAULT NULL,
  `ii_vbc` double DEFAULT NULL,
  `ii_vdespadu` double DEFAULT NULL,
  `ii_pii` double DEFAULT NULL,
  `ii_piof` double DEFAULT NULL,
  `pis_vbc` double DEFAULT NULL,
  `pis_ppis` double DEFAULT NULL,
  `pis_vpis` double DEFAULT NULL,
  `pis_qbcprod` double DEFAULT NULL,
  `pis_valiqprod` double DEFAULT NULL,
  `pisst_vbc` double DEFAULT NULL,
  `pisst_ppis` double DEFAULT NULL,
  `pisst_vpis` double DEFAULT NULL,
  `pisst_qbcprod` double DEFAULT NULL,
  `pisst_valiqprod` double DEFAULT NULL,
  `cofins_vbc` double DEFAULT NULL,
  `cofins_pcofins` double DEFAULT NULL,
  `cofins_vbcprod` double DEFAULT NULL,
  `cofins_valiqprod` double DEFAULT NULL,
  `cofins_vcofins` double DEFAULT NULL,
  `cofinsst_vbc` double DEFAULT NULL,
  `cofinsst_pcofins` double DEFAULT NULL,
  `cofinsst_qbcprod` double DEFAULT NULL,
  `cofinsst_valiqprod` double DEFAULT NULL,
  `cofinsst_vcofins` double DEFAULT NULL,
  `issqn_vbc` double DEFAULT NULL,
  `issqn_pvaliq` double DEFAULT NULL,
  `issqn_vissqn` double DEFAULT NULL,
  `issqn_cmunfg` double DEFAULT NULL,
  `issqn_listserv` double DEFAULT NULL,
  `ii_vii` double DEFAULT NULL,
  `ii_viof` double DEFAULT NULL,
  `issqn_valiq` double DEFAULT NULL,
  `icms_paf` varchar(3) DEFAULT NULL,
  `codnf_cfop_entrada` varchar(9) DEFAULT NULL,
  `codnf_cfop_entrada_fora` varchar(9) DEFAULT NULL,
  `mantem_online` char(1) DEFAULT NULL,
  `icms_modbc` int(11) DEFAULT NULL,
  `icms_modbcst` int(11) DEFAULT NULL,
  `pis_cofins_por_quant` char(1) DEFAULT NULL,
  `ipi_por_quant` char(1) DEFAULT NULL,
  `calcula_pis` char(1) DEFAULT NULL,
  `calcula_ipi` char(1) DEFAULT NULL,
  `calcula_cofins` char(1) DEFAULT NULL,
  `calcula_icms_st` char(1) DEFAULT NULL,
  `dt_alteracao` timestamp NULL DEFAULT NULL,
  `calcula_icms` char(1) DEFAULT NULL,
  `issqn_tipotributacao` int(11) DEFAULT NULL,
  `nf_pcredsn` double DEFAULT NULL,
  `servico_natureza_operacao` int(11) DEFAULT NULL,
  `servico_regime_especial_tribut` int(11) DEFAULT NULL,
  `servico_incentivador_cultural` char(1) DEFAULT NULL,
  `servico_iss_retido` int(11) DEFAULT NULL,
  `servico_aliquota` double DEFAULT NULL,
  `calcula_ii` char(1) DEFAULT NULL,
  `referencia` varchar(15) DEFAULT NULL,
  `ipi_cenq` int(11) DEFAULT NULL,
  `codnf_cest` varchar(7) DEFAULT NULL,
  `codvenda_tipo` int(11) DEFAULT NULL,
  `issqn_incentivador_cultural` int(11) DEFAULT NULL,
  `comisao` double DEFAULT NULL,
  `vbcst_frete` char(1) DEFAULT NULL,
  `vbcst_ipi` char(1) DEFAULT NULL,
  `vbcst_confins` char(1) DEFAULT NULL,
  `vbcst_ii` char(1) DEFAULT NULL,
  `vbcst_pis` char(1) DEFAULT NULL,
  `vbc_frete` char(1) DEFAULT NULL,
  `vbc_ipi` char(1) DEFAULT NULL,
  `vbc_confins` char(1) DEFAULT NULL,
  `vbc_ii` char(1) DEFAULT NULL,
  `vbc_pis` char(1) DEFAULT NULL,
  `predmvast` double DEFAULT NULL,
  `calcula_issqn` char(1) DEFAULT NULL,
  `vbc_desconto` char(1) DEFAULT NULL,
  `vbcst_desconto` char(1) DEFAULT NULL,
  `nao_calcula_valor_iss` char(1) NOT NULL DEFAULT 'N',
  `ativo` char(1) NOT NULL DEFAULT 'S',
  `tem_diferimento` char(1) DEFAULT NULL,
  `pdif` double DEFAULT NULL,
  `cbenef` varchar(50) DEFAULT NULL,
  `operacao` varchar(50) DEFAULT NULL,
  `consumidor_final` char(1) NOT NULL DEFAULT 'N',
  `entrada_saida` char(1) DEFAULT NULL,
  `codplanocontas` varchar(15) DEFAULT NULL,
  `picms_nconsumidor_final` double DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `officeimpresso_dt_alteracao` timestamp NULL DEFAULT NULL,
  `officeimpresso_codnf_natureza_operacao` int(11) DEFAULT NULL,
  `officeimpresso_codproduto_grupo` varchar(15) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nf_natureza_operacao_prodgrupo_nf_natureza_operacao_id_foreign` (`nf_natureza_operacao_id`),
  KEY `nf_natureza_operacao_prodgrupo_produto_grupo_id_foreign` (`produto_grupo_id`),
  CONSTRAINT `nf_natureza_operacao_prodgrupo_nf_natureza_operacao_id_foreign` FOREIGN KEY (`nf_natureza_operacao_id`) REFERENCES `nf_natureza_operacao` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nf_natureza_operacao_prodgrupo_produto_grupo_id_foreign` FOREIGN KEY (`produto_grupo_id`) REFERENCES `produto_grupo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nfe_business_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfe_business_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL COMMENT '1:1 com business — cada empresa tem 1 config fiscal',
  `regime` enum('mei','simples','lucro_presumido','lucro_real') NOT NULL DEFAULT 'simples',
  `auto_emission_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Per-business gate: emite NFe/NFC-e auto quando true. Default false (opt-in explicito Wagner). ADR 0093 multi-tenant Tier 0.',
  `tributacao_default` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Cascade Nível 4 ADR 0006: csosn|cst, aliquotas default. JSON não-NULL pra simplificar service.' CHECK (json_valid(`tributacao_default`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nfe_business_configs_business_id_unique` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nfe_certificados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfe_certificados` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `uuid` char(36) DEFAULT NULL,
  `cnpj_titular` varchar(14) NOT NULL,
  `valido_ate` date NOT NULL,
  `encrypted_password` text DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nfe_certificados_uuid_unique` (`uuid`),
  KEY `nfe_certificados_business_id_ativo_index` (`business_id`,`ativo`),
  KEY `nfe_certificados_business_id_index` (`business_id`),
  KEY `nfe_certificados_cnpj_titular_index` (`cnpj_titular`),
  CONSTRAINT `nfe_certificados_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nfe_dfe_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfe_dfe_eventos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `dfe_recebido_id` bigint(20) unsigned NOT NULL,
  `tipo` varchar(6) NOT NULL COMMENT '210210=ciência, 210200=confirmação, 210220=desconhecimento, 210240=não realizada',
  `justificativa` text DEFAULT NULL COMMENT 'Obrigatória ≥15 chars pra 210220 e 210240 (NT 2014.002)',
  `status` enum('pendente','enviado','autorizado','rejeitado') NOT NULL DEFAULT 'pendente',
  `cstat_evento` varchar(5) DEFAULT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_json`)),
  `nseq_evento` smallint(5) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nfe_dfe_eventos_uq` (`business_id`,`dfe_recebido_id`,`tipo`,`nseq_evento`),
  KEY `nfe_dfe_eventos_dfe_tipo_idx` (`dfe_recebido_id`,`tipo`),
  KEY `nfe_dfe_eventos_business_id_index` (`business_id`),
  KEY `nfe_dfe_eventos_status_index` (`status`),
  CONSTRAINT `nfe_dfe_eventos_dfe_recebido_id_foreign` FOREIGN KEY (`dfe_recebido_id`) REFERENCES `nfe_dfe_recebidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nfe_dfe_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfe_dfe_itens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `dfe_recebido_id` bigint(20) unsigned NOT NULL,
  `ncm` varchar(8) DEFAULT NULL,
  `cfop` varchar(4) DEFAULT NULL,
  `descricao` text NOT NULL,
  `quantidade` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `valor_unitario` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `valor_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nfe_dfe_itens_dfe_recebido_id_foreign` (`dfe_recebido_id`),
  KEY `nfe_dfe_itens_biz_dfe_idx` (`business_id`,`dfe_recebido_id`),
  KEY `nfe_dfe_itens_business_id_index` (`business_id`),
  CONSTRAINT `nfe_dfe_itens_dfe_recebido_id_foreign` FOREIGN KEY (`dfe_recebido_id`) REFERENCES `nfe_dfe_recebidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nfe_dfe_nsu_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfe_dfe_nsu_state` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `last_nsu` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'Cursor SEFAZ — IRREVERSÍVEL. Não decrementa.',
  `ultimo_check_em` timestamp NULL DEFAULT NULL,
  `total_xmls_processados` bigint(20) unsigned NOT NULL DEFAULT 0,
  `ultimo_lote_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nfe_dfe_nsu_state_business_id_unique` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nfe_dfe_recebidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfe_dfe_recebidos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `chave_44` varchar(44) NOT NULL,
  `nsu` bigint(20) unsigned NOT NULL COMMENT 'Número Sequencial Único SEFAZ — cursor de DistribuicaoDFe',
  `cnpj_emitente` varchar(14) NOT NULL,
  `nome_emitente` varchar(200) DEFAULT NULL,
  `valor_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `num_protocolo` varchar(30) DEFAULT NULL,
  `data_emissao` datetime NOT NULL,
  `xml_path` varchar(255) DEFAULT NULL COMMENT 'Path em storage(nfe_dfes_recebidos) — NÃO no disk dos certificados',
  `status_manifestacao` enum('pendente','ciencia','confirmada','desconhecida','nao_realizada') NOT NULL DEFAULT 'pendente',
  `manifestado_em` timestamp NULL DEFAULT NULL,
  `prazo_confirmacao_em` date DEFAULT NULL COMMENT 'data_emissao + 180d (NT 2014.002) — countdown UI',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nfe_dfe_recebidos_biz_chave_uq` (`business_id`,`chave_44`),
  KEY `nfe_dfe_recebidos_biz_prazo_idx` (`business_id`,`prazo_confirmacao_em`),
  KEY `nfe_dfe_recebidos_business_id_index` (`business_id`),
  KEY `nfe_dfe_recebidos_nsu_index` (`nsu`),
  KEY `nfe_dfe_recebidos_cnpj_emitente_index` (`cnpj_emitente`),
  KEY `nfe_dfe_recebidos_status_manifestacao_index` (`status_manifestacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nfe_emissoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfe_emissoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `transaction_id` int(10) unsigned DEFAULT NULL COMMENT 'FK transactions.id (UPos legado, int unsigned). Null em emissões manuais sem venda',
  `modelo` enum('55','65','67') NOT NULL COMMENT '55=NFe B2B, 65=NFC-e B2C, 67=CT-e (futuro)',
  `serie` varchar(3) NOT NULL,
  `numero` int(10) unsigned NOT NULL,
  `chave_44` varchar(44) DEFAULT NULL COMMENT 'Chave de acesso 44 dígitos — populada após autorização SEFAZ',
  `status` enum('pendente','enviando','autorizada','rejeitada','cancelada','denegada','inutilizada','erro_envio') NOT NULL DEFAULT 'pendente',
  `cstat` varchar(5) DEFAULT NULL COMMENT 'Código status SEFAZ (100=autorizada, 217=NFe não consta, etc.)',
  `motivo` text DEFAULT NULL,
  `xml_path` varchar(255) DEFAULT NULL,
  `danfe_path` varchar(255) DEFAULT NULL,
  `valor_total` decimal(15,2) NOT NULL,
  `emitido_em` datetime DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nfe_emissoes_biz_seq_unique` (`business_id`,`modelo`,`serie`,`numero`),
  UNIQUE KEY `nfe_emissoes_biz_tx_unique` (`business_id`,`transaction_id`),
  KEY `nfe_emissoes_biz_status_idx` (`business_id`,`status`),
  KEY `nfe_emissoes_business_id_index` (`business_id`),
  KEY `nfe_emissoes_chave_44_index` (`chave_44`),
  KEY `nfe_emissoes_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nfe_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfe_eventos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `emissao_id` bigint(20) unsigned NOT NULL,
  `tipo` varchar(6) NOT NULL COMMENT '110110=CCe, 110111=cancelamento, 210200=confirmação, 210210=ciência',
  `justificativa` text DEFAULT NULL COMMENT 'Cancelamento exige 15-255 chars; CCe exige descrição da correção',
  `status` enum('pendente','enviado','autorizado','rejeitado') NOT NULL DEFAULT 'pendente',
  `cstat_evento` varchar(5) DEFAULT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Request + response SEFAZ pra debug' CHECK (json_valid(`payload_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `nfe_eventos_emi_tipo_idx` (`emissao_id`,`tipo`),
  KEY `nfe_eventos_business_id_index` (`business_id`),
  KEY `nfe_eventos_tipo_index` (`tipo`),
  KEY `nfe_eventos_status_index` (`status`),
  CONSTRAINT `nfe_eventos_emissao_id_foreign` FOREIGN KEY (`emissao_id`) REFERENCES `nfe_emissoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nfe_fiscal_rule_tax_rate_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfe_fiscal_rule_tax_rate_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `fiscal_rule_id` bigint(20) unsigned NOT NULL COMMENT 'FK nfe_fiscal_rules.id (módulo NfeBrasil)',
  `tax_rate_id` int(10) unsigned NOT NULL COMMENT 'FK tax_rates.id (core UPos — int unsigned, não bigint)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nfe_fr_tr_links_fiscal_rule_unique` (`fiscal_rule_id`),
  UNIQUE KEY `nfe_fr_tr_links_tax_rate_unique` (`tax_rate_id`),
  KEY `nfe_fr_tr_links_biz_rule_idx` (`business_id`,`fiscal_rule_id`),
  KEY `nfe_fiscal_rule_tax_rate_links_business_id_index` (`business_id`),
  CONSTRAINT `nfe_fr_tr_links_fiscal_rule_fk` FOREIGN KEY (`fiscal_rule_id`) REFERENCES `nfe_fiscal_rules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nfe_fr_tr_links_tax_rate_fk` FOREIGN KEY (`tax_rate_id`) REFERENCES `tax_rates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nfe_fiscal_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfe_fiscal_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `ncm` char(8) NOT NULL COMMENT 'Nomenclatura Comum do Mercosul — 8 dígitos',
  `uf_origem` char(2) NOT NULL,
  `uf_destino` char(2) DEFAULT NULL COMMENT 'NULL = "todas as UFs destino" (nível 3 do cascade ADR 0006)',
  `cfop` char(4) NOT NULL,
  `csosn` char(3) DEFAULT NULL COMMENT 'Simples Nacional (CRT 1)',
  `cst` char(3) DEFAULT NULL COMMENT 'Regime Normal (CRT 3)',
  `c_class_trib` char(6) DEFAULT NULL COMMENT 'cClassTrib NT 2025.002 — classificação tributária IBS/CBS',
  `cst_ibs` char(3) DEFAULT NULL COMMENT 'CST IBS — Código Situação Tributária IBS (NT 2025.002 Anexo I)',
  `cst_cbs` char(3) DEFAULT NULL COMMENT 'CST CBS — Código Situação Tributária CBS (NT 2025.002 Anexo I)',
  `aliquota_icms` decimal(7,4) NOT NULL DEFAULT 0.0000,
  `aliquota_pis` decimal(7,4) NOT NULL DEFAULT 0.0000,
  `aliquota_cofins` decimal(7,4) NOT NULL DEFAULT 0.0000,
  `aliquota_ipi` decimal(7,4) NOT NULL DEFAULT 0.0000,
  `aliquota_ibs` decimal(7,4) NOT NULL DEFAULT 0.0000 COMMENT 'Alíquota IBS — decimal (0.18 = 18%)',
  `aliquota_cbs` decimal(7,4) NOT NULL DEFAULT 0.0000 COMMENT 'Alíquota CBS — decimal (0.009 = 0.9% highlight 2026)',
  `mva` decimal(7,4) DEFAULT NULL,
  `fcp` decimal(7,4) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nfe_fiscal_rules_biz_ncm_idx` (`business_id`,`ncm`),
  KEY `nfe_fiscal_rules_cascade_idx` (`business_id`,`ncm`,`uf_origem`,`uf_destino`),
  KEY `nfe_fiscal_rules_business_id_index` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nfe_inutilizacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfe_inutilizacoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `modelo` enum('55','65') NOT NULL,
  `serie` varchar(3) NOT NULL,
  `numero_de` int(10) unsigned NOT NULL,
  `numero_ate` int(10) unsigned NOT NULL,
  `justificativa` text NOT NULL,
  `status` enum('pendente','enviado','autorizado','rejeitado') NOT NULL DEFAULT 'pendente',
  `cstat` varchar(5) DEFAULT NULL,
  `autorizada_em` datetime DEFAULT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_json`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nfe_inut_biz_mod_serie_idx` (`business_id`,`modelo`,`serie`),
  KEY `nfe_inutilizacoes_business_id_index` (`business_id`),
  KEY `nfe_inutilizacoes_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nfse_emissoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfse_emissoes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `numero` varchar(20) DEFAULT NULL COMMENT 'Número atribuído pela prefeitura após emissão',
  `serie` varchar(10) NOT NULL DEFAULT 'RPS',
  `rps_numero` varchar(20) DEFAULT NULL COMMENT 'Número do RPS gerado pelo prestador',
  `competencia` date NOT NULL COMMENT 'Mês/ano de prestação do serviço',
  `tomador_cnpj` varchar(18) DEFAULT NULL,
  `tomador_cpf` varchar(14) DEFAULT NULL,
  `tomador_nome` varchar(150) NOT NULL,
  `tomador_email` varchar(150) DEFAULT NULL,
  `tomador_municipio_ibge` varchar(7) DEFAULT NULL,
  `lc116_codigo` varchar(5) DEFAULT NULL COMMENT 'Ex: 1.05',
  `cnae` varchar(10) DEFAULT NULL,
  `descricao` text NOT NULL,
  `valor_servicos` decimal(15,2) NOT NULL,
  `valor_deducoes` decimal(15,2) NOT NULL DEFAULT 0.00,
  `valor_base_calculo` decimal(15,2) NOT NULL DEFAULT 0.00,
  `aliquota_iss` decimal(5,4) DEFAULT NULL,
  `municipio_codigo_ibge` varchar(7) DEFAULT NULL COMMENT 'IBGE 7 dígitos do município emitente — resolve driver de cancelamento',
  `valor_iss` decimal(15,2) NOT NULL DEFAULT 0.00,
  `iss_retido` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('rascunho','processando','emitida','cancelada','erro') NOT NULL DEFAULT 'rascunho',
  `provider_protocolo` varchar(100) DEFAULT NULL,
  `provider_codigo_verificacao` varchar(100) DEFAULT NULL,
  `pdf_url` varchar(500) DEFAULT NULL,
  `xml_envio` longtext DEFAULT NULL,
  `xml_retorno` longtext DEFAULT NULL,
  `erro_mensagem` text DEFAULT NULL,
  `idempotency_key` varchar(64) NOT NULL,
  `recurring_invoice_id` int(10) unsigned DEFAULT NULL,
  `transaction_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nfse_emissoes_idempotency_key_unique` (`idempotency_key`),
  KEY `nfse_emissoes_business_id_status_index` (`business_id`,`status`),
  KEY `nfse_emissoes_business_id_competencia_index` (`business_id`,`competencia`),
  KEY `nfse_emissoes_business_id_index` (`business_id`),
  KEY `nfse_emissoes_status_index` (`status`),
  KEY `nfse_emissoes_recurring_invoice_id_index` (`recurring_invoice_id`),
  KEY `nfse_emissoes_transaction_id_index` (`transaction_id`),
  KEY `nfse_emissoes_biz_mun_idx` (`business_id`,`municipio_codigo_ibge`),
  CONSTRAINT `nfse_emissoes_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nfse_eventos_cancelamento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfse_eventos_cancelamento` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `nfse_emissao_id` bigint(20) unsigned NOT NULL,
  `driver_key` varchar(32) NOT NULL COMMENT 'ABRASF_V1, ABRASF_V2.04, GINFES, IPM, TIPLAN, NFSE_GOV_BR',
  `motivo` text NOT NULL COMMENT 'Justificativa 15-255 chars (semelhante NFe55 tpEvento 110111)',
  `status` enum('pendente','enviado','autorizado','rejeitado') NOT NULL DEFAULT 'pendente',
  `protocolo_municipal` varchar(100) DEFAULT NULL COMMENT 'Protocolo/recibo retornado pela prefeitura/sefin',
  `codigo_retorno` varchar(20) DEFAULT NULL COMMENT 'Código de status municipal (varia por padrão)',
  `mensagem_retorno` text DEFAULT NULL,
  `autorizado_em` datetime DEFAULT NULL,
  `payload_request` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Request enviado (XML SOAP, JSON REST etc) — debug' CHECK (json_valid(`payload_request`)),
  `payload_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Resposta crua do município — debug' CHECK (json_valid(`payload_response`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nfse_provider_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfse_provider_configs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `provider` varchar(30) NOT NULL DEFAULT 'sn_nfse_federal' COMMENT 'sn_nfse_federal | abrasf (municípios não-aderidos ao SN-NFSe)',
  `prestador_cnpj` varchar(18) DEFAULT NULL,
  `prestador_im` varchar(20) DEFAULT NULL,
  `municipio_codigo_ibge` varchar(7) NOT NULL,
  `serie_default` varchar(10) NOT NULL DEFAULT 'RPS',
  `cnae` varchar(10) DEFAULT NULL COMMENT 'Ex: 6201-5/00',
  `lc116_codigo_default` varchar(5) DEFAULT NULL COMMENT 'Ex: 1.05',
  `aliquota_iss` decimal(5,4) DEFAULT NULL COMMENT 'Ex: 0.0200 = 2%',
  `ambiente` enum('homologacao','producao') NOT NULL DEFAULT 'homologacao',
  `cert_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nfse_provider_configs_business_id_municipio_codigo_ibge_unique` (`business_id`,`municipio_codigo_ibge`),
  KEY `nfse_provider_configs_cert_id_foreign` (`cert_id`),
  CONSTRAINT `nfse_provider_configs_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nfse_provider_configs_cert_id_foreign` FOREIGN KEY (`cert_id`) REFERENCES `nfe_certificados` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notification_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `template_for` varchar(191) NOT NULL,
  `email_body` text DEFAULT NULL,
  `sms_body` text DEFAULT NULL,
  `whatsapp_text` text DEFAULT NULL,
  `subject` varchar(191) DEFAULT NULL,
  `cc` varchar(191) DEFAULT NULL,
  `bcc` varchar(191) DEFAULT NULL,
  `auto_send` tinyint(1) NOT NULL DEFAULT 0,
  `auto_send_sms` tinyint(1) NOT NULL DEFAULT 0,
  `auto_send_wa_notif` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(191) NOT NULL,
  `notifiable_type` varchar(191) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oa_inspection_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oa_inspection_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `service_order_id` bigint(20) unsigned NOT NULL,
  `categoria` enum('motor','freios','correia','bateria','pneus','suspensao','direcao','eletrica','fluidos','outro') NOT NULL,
  `descricao` varchar(150) NOT NULL,
  `severity` enum('ok','atencao','critico') NOT NULL,
  `recomendacao` varchar(255) DEFAULT NULL,
  `valor_recomendado` decimal(10,2) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `photo_url` varchar(500) DEFAULT NULL,
  `client_decision` enum('pending','approved','rejected') DEFAULT 'pending',
  `client_decided_at` timestamp NULL DEFAULT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oai_biz_so` (`business_id`,`service_order_id`),
  KEY `idx_oai_biz_sev` (`business_id`,`severity`),
  KEY `idx_oai_so_sort` (`service_order_id`,`sort_order`),
  KEY `idx_oai_client_decision` (`client_decision`,`client_decided_at`),
  CONSTRAINT `fk_oai_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oai_service_order` FOREIGN KEY (`service_order_id`) REFERENCES `service_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_access_tokens` (
  `id` varchar(100) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `scopes` text DEFAULT NULL,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_access_tokens_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_auth_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_auth_codes` (
  `id` varchar(100) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `scopes` text DEFAULT NULL,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `secret` varchar(100) NOT NULL,
  `provider` varchar(191) DEFAULT NULL,
  `redirect` text NOT NULL,
  `personal_access_client` tinyint(1) NOT NULL,
  `password_client` tinyint(1) NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_clients_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_personal_access_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_personal_access_clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_personal_access_clients_client_id_index` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_refresh_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_refresh_tokens` (
  `id` varchar(100) NOT NULL,
  `access_token_id` varchar(100) NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_refresh_tokens_access_token_id_index` (`access_token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oficina_service_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oficina_service_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `service_order_id` bigint(20) unsigned NOT NULL,
  `tipo` enum('peca','mao_obra','servico_terceiro') NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `quantidade` decimal(10,3) NOT NULL DEFAULT 1.000,
  `valor_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `valor_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `product_id` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_osi_biz_so` (`business_id`,`service_order_id`),
  KEY `idx_osi_biz_tipo` (`business_id`,`tipo`),
  KEY `idx_osi_so_tipo` (`service_order_id`,`tipo`),
  CONSTRAINT `fk_osi_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_osi_service_order` FOREIGN KEY (`service_order_id`) REFERENCES `service_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `packages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `description` text NOT NULL,
  `location_count` int(11) NOT NULL COMMENT 'No. of Business Locations, 0 = infinite option.',
  `user_count` int(11) NOT NULL,
  `product_count` int(11) NOT NULL,
  `bookings` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enable/Disable bookings',
  `kitchen` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enable/Disable kitchen',
  `order_screen` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enable/Disable order_screen',
  `tables` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enable/Disable tables',
  `invoice_count` int(11) NOT NULL,
  `interval` enum('days','months','years') NOT NULL,
  `interval_count` int(11) NOT NULL,
  `trial_days` int(11) NOT NULL,
  `price` decimal(22,4) NOT NULL,
  `custom_permissions` longtext NOT NULL,
  `created_by` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT 0,
  `is_one_time` tinyint(1) NOT NULL DEFAULT 0,
  `enable_custom_link` tinyint(1) NOT NULL DEFAULT 0,
  `custom_link` varchar(191) DEFAULT NULL,
  `custom_link_text` varchar(191) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `officeimpresso_limitemaquinas` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(191) NOT NULL,
  `token` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_by_id` int(11) DEFAULT NULL,
  `payment_type_id` varchar(11) DEFAULT NULL,
  `transaction_type` varchar(191) DEFAULT NULL,
  `reference` int(11) DEFAULT NULL,
  `cheque_number` varchar(191) DEFAULT NULL,
  `receipt` varchar(191) DEFAULT NULL,
  `account_number` varchar(191) DEFAULT NULL,
  `bank_name` varchar(191) DEFAULT NULL,
  `routing_code` varchar(191) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_gateway_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_gateway_credentials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `gateway_key` enum('inter','c6','asaas','bcb_pix','pesapal','pagarme','bradesco_cnab','itau_cnab','bb_cnab','santander_cnab','caixa_cnab','sicoob_cnab','ailos_cnab','sicredi_cnab','cresol_cnab','banrisul_cnab','btg_cnab','bradesco_api','itau_api','bb_api','santander_api','sicoob_api') NOT NULL,
  `ambiente` enum('production','sandbox') NOT NULL DEFAULT 'production',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `nome_display` varchar(191) DEFAULT NULL,
  `config_json` longtext DEFAULT NULL,
  `conta_bancaria_id` int(10) unsigned DEFAULT NULL,
  `health_status` enum('ok','degraded','down','unknown') NOT NULL DEFAULT 'unknown',
  `health_checked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pg_cred_biz_gw_amb_unique` (`business_id`,`gateway_key`,`ambiente`),
  KEY `payment_gateway_credentials_business_id_index` (`business_id`),
  KEY `payment_gateway_credentials_gateway_key_index` (`gateway_key`),
  KEY `payment_gateway_credentials_ativo_index` (`ativo`),
  KEY `payment_gateway_credentials_conta_bancaria_id_index` (`conta_bancaria_id`),
  KEY `payment_gateway_credentials_health_status_index` (`health_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `name` varchar(191) NOT NULL,
  `system_name` varchar(191) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_cash` tinyint(4) NOT NULL DEFAULT 0,
  `is_online` tinyint(4) NOT NULL DEFAULT 0,
  `is_system` tinyint(4) NOT NULL DEFAULT 0,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `position` int(11) DEFAULT NULL,
  `options` text DEFAULT NULL,
  `unique_id` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `guard_name` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pessoas_grupo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pessoas_grupo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `descricao` varchar(150) DEFAULT NULL,
  `officeimpresso_codigo` int(11) DEFAULT NULL,
  `officeimpresso_dt_alteracao` timestamp NULL DEFAULT NULL,
  `business_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pessoas_grupo_business_id_foreign` (`business_id`),
  CONSTRAINT `pessoas_grupo_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pg_webhook_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pg_webhook_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `provider` varchar(30) NOT NULL,
  `event_id` varchar(100) NOT NULL,
  `event_type` varchar(60) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `processed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pg_webhook_idempotency` (`provider`,`event_id`),
  KEY `pg_webhook_events_business_id_index` (`business_id`),
  KEY `pg_webhook_events_provider_index` (`provider`),
  KEY `pg_webhook_events_processed_index` (`processed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ponto_apuracao_dia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ponto_apuracao_dia` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `colaborador_config_id` int(10) unsigned NOT NULL,
  `data` date NOT NULL,
  `escala_id` int(10) unsigned DEFAULT NULL,
  `prevista_entrada` time DEFAULT NULL,
  `prevista_saida` time DEFAULT NULL,
  `prevista_carga_minutos` smallint(5) unsigned NOT NULL DEFAULT 0,
  `realizada_entrada` time DEFAULT NULL,
  `realizada_saida` time DEFAULT NULL,
  `realizada_trabalhada_minutos` smallint(5) unsigned NOT NULL DEFAULT 0,
  `realizada_intrajornada_minutos` smallint(5) unsigned NOT NULL DEFAULT 0,
  `atraso_minutos` smallint(6) NOT NULL DEFAULT 0,
  `saida_antecipada_minutos` smallint(6) NOT NULL DEFAULT 0,
  `falta_minutos` smallint(6) NOT NULL DEFAULT 0,
  `he_diurna_minutos` smallint(6) NOT NULL DEFAULT 0,
  `he_noturna_minutos` smallint(6) NOT NULL DEFAULT 0,
  `adicional_noturno_minutos` smallint(6) NOT NULL DEFAULT 0,
  `dsr_repercussao_minutos` smallint(6) NOT NULL DEFAULT 0,
  `interjornada_violacao_minutos` smallint(6) NOT NULL DEFAULT 0,
  `intrajornada_violacao_minutos` smallint(6) NOT NULL DEFAULT 0,
  `banco_horas_credito_minutos` smallint(6) NOT NULL DEFAULT 0,
  `banco_horas_debito_minutos` smallint(6) NOT NULL DEFAULT 0,
  `estado` enum('PENDENTE','CALCULADO','DIVERGENCIA','AJUSTADO','CONSOLIDADO','FECHADO') NOT NULL DEFAULT 'PENDENTE',
  `qtd_intercorrencias` smallint(5) unsigned NOT NULL DEFAULT 0,
  `qtd_marcacoes` smallint(5) unsigned NOT NULL DEFAULT 0,
  `divergencias` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`divergencias`)),
  `calculado_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ponto_apuracao_dia_colaborador_config_id_data_unique` (`colaborador_config_id`,`data`),
  KEY `ponto_apuracao_dia_escala_id_foreign` (`escala_id`),
  KEY `ponto_apuracao_dia_business_id_data_estado_index` (`business_id`,`data`,`estado`),
  KEY `ponto_apuracao_dia_business_id_index` (`business_id`),
  KEY `ponto_apuracao_dia_colaborador_config_id_index` (`colaborador_config_id`),
  CONSTRAINT `ponto_apuracao_dia_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`),
  CONSTRAINT `ponto_apuracao_dia_colaborador_config_id_foreign` FOREIGN KEY (`colaborador_config_id`) REFERENCES `ponto_colaborador_config` (`id`),
  CONSTRAINT `ponto_apuracao_dia_escala_id_foreign` FOREIGN KEY (`escala_id`) REFERENCES `ponto_escalas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ponto_banco_horas_movimentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ponto_banco_horas_movimentos` (
  `id` char(36) NOT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `colaborador_config_id` int(10) unsigned NOT NULL,
  `data_referencia` date NOT NULL,
  `tipo` enum('CREDITO','DEBITO','PAGAMENTO','EXPIRACAO','AJUSTE') NOT NULL,
  `minutos` int(11) NOT NULL,
  `multiplicador` decimal(4,2) NOT NULL DEFAULT 1.00,
  `saldo_posterior_minutos` int(11) NOT NULL,
  `apuracao_dia_id` int(10) unsigned DEFAULT NULL,
  `intercorrencia_id` char(36) DEFAULT NULL,
  `observacao` text DEFAULT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ponto_banco_horas_movimentos_apuracao_dia_id_foreign` (`apuracao_dia_id`),
  KEY `ponto_banco_horas_movimentos_intercorrencia_id_foreign` (`intercorrencia_id`),
  KEY `ponto_banco_horas_movimentos_usuario_id_foreign` (`usuario_id`),
  KEY `ponto_bh_mov_colab_data_idx` (`colaborador_config_id`,`data_referencia`),
  KEY `ponto_banco_horas_movimentos_business_id_index` (`business_id`),
  KEY `ponto_banco_horas_movimentos_colaborador_config_id_index` (`colaborador_config_id`),
  CONSTRAINT `ponto_banco_horas_movimentos_apuracao_dia_id_foreign` FOREIGN KEY (`apuracao_dia_id`) REFERENCES `ponto_apuracao_dia` (`id`),
  CONSTRAINT `ponto_banco_horas_movimentos_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`),
  CONSTRAINT `ponto_banco_horas_movimentos_colaborador_config_id_foreign` FOREIGN KEY (`colaborador_config_id`) REFERENCES `ponto_colaborador_config` (`id`),
  CONSTRAINT `ponto_banco_horas_movimentos_intercorrencia_id_foreign` FOREIGN KEY (`intercorrencia_id`) REFERENCES `ponto_intercorrencias` (`id`),
  CONSTRAINT `ponto_banco_horas_movimentos_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ponto_banco_horas_saldo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ponto_banco_horas_saldo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `colaborador_config_id` int(10) unsigned NOT NULL,
  `saldo_minutos` int(11) NOT NULL DEFAULT 0,
  `ultima_movimentacao` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ponto_banco_horas_saldo_colaborador_config_id_unique` (`colaborador_config_id`),
  KEY `ponto_banco_horas_saldo_business_id_index` (`business_id`),
  CONSTRAINT `ponto_banco_horas_saldo_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`),
  CONSTRAINT `ponto_banco_horas_saldo_colaborador_config_id_foreign` FOREIGN KEY (`colaborador_config_id`) REFERENCES `ponto_colaborador_config` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ponto_colaborador_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ponto_colaborador_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `matricula` varchar(30) DEFAULT NULL,
  `pis` varchar(14) DEFAULT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `escala_atual_id` int(10) unsigned DEFAULT NULL,
  `controla_ponto` tinyint(1) NOT NULL DEFAULT 1,
  `usa_banco_horas` tinyint(1) NOT NULL DEFAULT 0,
  `admissao` date NOT NULL,
  `desligamento` date DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ponto_colaborador_config_user_id_unique` (`user_id`),
  KEY `ponto_colaborador_config_business_id_index` (`business_id`),
  KEY `ponto_colaborador_config_matricula_index` (`matricula`),
  KEY `ponto_colaborador_config_pis_index` (`pis`),
  KEY `ponto_colaborador_config_cpf_index` (`cpf`),
  KEY `ponto_colaborador_config_escala_atual_id_foreign` (`escala_atual_id`),
  CONSTRAINT `ponto_colaborador_config_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ponto_colaborador_config_escala_atual_id_foreign` FOREIGN KEY (`escala_atual_id`) REFERENCES `ponto_escalas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ponto_colaborador_config_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ponto_escala_turnos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ponto_escala_turnos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `escala_id` int(10) unsigned NOT NULL,
  `dia_semana` tinyint(3) unsigned NOT NULL,
  `hora_entrada` time NOT NULL,
  `hora_almoco_inicio` time DEFAULT NULL,
  `hora_almoco_fim` time DEFAULT NULL,
  `hora_saida` time NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ponto_escala_turnos_escala_id_foreign` (`escala_id`),
  CONSTRAINT `ponto_escala_turnos_escala_id_foreign` FOREIGN KEY (`escala_id`) REFERENCES `ponto_escalas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ponto_escalas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ponto_escalas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `nome` varchar(120) NOT NULL,
  `codigo` varchar(30) DEFAULT NULL,
  `tipo` enum('FIXA','FLEXIVEL','ESCALA_12X36','ESCALA_6X1','ESCALA_5X2') NOT NULL,
  `carga_diaria_minutos` smallint(5) unsigned NOT NULL DEFAULT 480,
  `carga_semanal_minutos` smallint(5) unsigned NOT NULL DEFAULT 2640,
  `permite_banco_horas` tinyint(1) NOT NULL DEFAULT 0,
  `dias_semana` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dias_semana`)),
  `horarios_padrao` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`horarios_padrao`)),
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ponto_escalas_business_id_index` (`business_id`),
  KEY `ponto_escalas_codigo_index` (`codigo`),
  CONSTRAINT `ponto_escalas_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ponto_importacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ponto_importacoes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `tipo` enum('AFD','AFDT','CSV_CADASTRO','CSV_ESCALA') NOT NULL,
  `nome_arquivo` varchar(255) NOT NULL,
  `arquivo_path` varchar(512) NOT NULL,
  `hash_arquivo` varchar(64) NOT NULL COMMENT 'SHA-256 do arquivo para dedup',
  `tamanho_bytes` bigint(20) unsigned NOT NULL,
  `estado` enum('PENDENTE','PROCESSANDO','CONCLUIDA','CONCLUIDA_COM_ERROS','FALHOU') NOT NULL DEFAULT 'PENDENTE',
  `linhas_total` int(10) unsigned NOT NULL DEFAULT 0,
  `linhas_processadas` int(10) unsigned NOT NULL DEFAULT 0,
  `linhas_sucesso` int(10) unsigned NOT NULL DEFAULT 0,
  `linhas_erro` int(10) unsigned NOT NULL DEFAULT 0,
  `erros_amostra` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`erros_amostra`)),
  `log` text DEFAULT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `iniciado_em` timestamp NULL DEFAULT NULL,
  `concluido_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ponto_importacoes_business_id_hash_arquivo_unique` (`business_id`,`hash_arquivo`),
  KEY `ponto_importacoes_usuario_id_foreign` (`usuario_id`),
  KEY `ponto_importacoes_business_id_estado_created_at_index` (`business_id`,`estado`,`created_at`),
  KEY `ponto_importacoes_business_id_index` (`business_id`),
  CONSTRAINT `ponto_importacoes_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`),
  CONSTRAINT `ponto_importacoes_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ponto_intercorrencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ponto_intercorrencias` (
  `id` char(36) NOT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `colaborador_config_id` int(10) unsigned NOT NULL,
  `codigo` varchar(40) NOT NULL COMMENT 'INC-YYYY-MMDD-NNN',
  `tipo` enum('CONSULTA_MEDICA','ATESTADO_MEDICO','REUNIAO_EXTERNA','VISITA_CLIENTE','HORA_EXTRA_AUTORIZADA','ESQUECIMENTO_MARCACAO','PROBLEMA_EQUIPAMENTO','OUTRO') NOT NULL,
  `data` date NOT NULL,
  `intervalo_inicio` time DEFAULT NULL,
  `intervalo_fim` time DEFAULT NULL,
  `dia_todo` tinyint(1) NOT NULL DEFAULT 0,
  `justificativa` text NOT NULL,
  `anexo_path` varchar(255) DEFAULT NULL,
  `estado` enum('RASCUNHO','PENDENTE','APROVADA','REJEITADA','APLICADA','CANCELADA') NOT NULL DEFAULT 'RASCUNHO',
  `prioridade` enum('NORMAL','URGENTE') NOT NULL DEFAULT 'NORMAL',
  `impacta_apuracao` tinyint(1) NOT NULL DEFAULT 1,
  `descontar_banco_horas` tinyint(1) NOT NULL DEFAULT 0,
  `solicitante_id` int(10) unsigned NOT NULL,
  `aprovador_id` int(10) unsigned DEFAULT NULL,
  `aprovado_em` timestamp NULL DEFAULT NULL,
  `motivo_rejeicao` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ponto_intercorrencias_codigo_unique` (`codigo`),
  KEY `ponto_intercorrencias_solicitante_id_foreign` (`solicitante_id`),
  KEY `ponto_intercorrencias_aprovador_id_foreign` (`aprovador_id`),
  KEY `ponto_intercorrencias_business_id_estado_data_index` (`business_id`,`estado`,`data`),
  KEY `ponto_intercorrencias_colaborador_config_id_data_index` (`colaborador_config_id`,`data`),
  KEY `ponto_intercorrencias_business_id_index` (`business_id`),
  KEY `ponto_intercorrencias_colaborador_config_id_index` (`colaborador_config_id`),
  CONSTRAINT `ponto_intercorrencias_aprovador_id_foreign` FOREIGN KEY (`aprovador_id`) REFERENCES `users` (`id`),
  CONSTRAINT `ponto_intercorrencias_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`),
  CONSTRAINT `ponto_intercorrencias_colaborador_config_id_foreign` FOREIGN KEY (`colaborador_config_id`) REFERENCES `ponto_colaborador_config` (`id`),
  CONSTRAINT `ponto_intercorrencias_solicitante_id_foreign` FOREIGN KEY (`solicitante_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ponto_marcacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ponto_marcacoes` (
  `id` char(36) NOT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `colaborador_config_id` int(10) unsigned NOT NULL,
  `rep_id` char(36) DEFAULT NULL,
  `nsr` bigint(20) unsigned NOT NULL COMMENT 'Número Sequencial de Registro por REP/origem',
  `momento` datetime NOT NULL COMMENT 'Data/hora da marcação — imutável',
  `origem` enum('REP_P','AFD','AFDT','MANUAL','INTEGRACAO','ANULACAO') NOT NULL,
  `tipo` enum('ENTRADA','SAIDA','ALMOCO_INICIO','ALMOCO_FIM','INTERCORRENCIA') NOT NULL,
  `marcacao_anulada_id` char(36) DEFAULT NULL COMMENT 'Preenchido quando origem=ANULACAO',
  `dispositivo_id` varchar(64) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `hash_anterior` char(64) DEFAULT NULL COMMENT 'SHA-256 da marcação anterior no REP',
  `hash` char(64) NOT NULL COMMENT 'SHA-256 desta marcação (encadeamento)',
  `assinatura_digital` text DEFAULT NULL COMMENT 'PKCS#7 com certificado ICP A1',
  `usuario_criador_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ponto_marcacoes_rep_id_nsr_unique` (`rep_id`,`nsr`),
  KEY `ponto_marcacoes_business_id_colaborador_config_id_momento_index` (`business_id`,`colaborador_config_id`,`momento`),
  KEY `ponto_marcacoes_marcacao_anulada_id_index` (`marcacao_anulada_id`),
  KEY `ponto_marcacoes_usuario_criador_id_foreign` (`usuario_criador_id`),
  KEY `ponto_marcacoes_business_id_index` (`business_id`),
  KEY `ponto_marcacoes_colaborador_config_id_index` (`colaborador_config_id`),
  KEY `ponto_marcacoes_rep_id_index` (`rep_id`),
  CONSTRAINT `ponto_marcacoes_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`),
  CONSTRAINT `ponto_marcacoes_colaborador_config_id_foreign` FOREIGN KEY (`colaborador_config_id`) REFERENCES `ponto_colaborador_config` (`id`),
  CONSTRAINT `ponto_marcacoes_rep_id_foreign` FOREIGN KEY (`rep_id`) REFERENCES `ponto_reps` (`id`),
  CONSTRAINT `ponto_marcacoes_usuario_criador_id_foreign` FOREIGN KEY (`usuario_criador_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`u906587222_oimpresso`@`localhost`*/ /*!50003 TRIGGER trg_ponto_marcacoes_no_update
    BEFORE UPDATE ON ponto_marcacoes
    FOR EACH ROW
    BEGIN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'ponto_marcacoes é append-only (Portaria 671/2021). Use origem=ANULACAO.';
    END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`u906587222_oimpresso`@`localhost`*/ /*!50003 TRIGGER trg_ponto_marcacoes_no_delete
    BEFORE DELETE ON ponto_marcacoes
    FOR EACH ROW
    BEGIN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'ponto_marcacoes é append-only (Portaria 671/2021).';
    END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `ponto_reps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ponto_reps` (
  `id` char(36) NOT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `tipo` enum('REP_P','REP_C','REP_A') NOT NULL,
  `identificador` varchar(17) NOT NULL,
  `descricao` varchar(120) NOT NULL,
  `local` varchar(120) DEFAULT NULL,
  `cnpj` varchar(14) DEFAULT NULL,
  `ultimo_nsr` bigint(20) unsigned NOT NULL DEFAULT 0,
  `certificado_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`certificado_info`)),
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ponto_reps_identificador_unique` (`identificador`),
  KEY `ponto_reps_business_id_tipo_index` (`business_id`,`tipo`),
  KEY `ponto_reps_business_id_index` (`business_id`),
  CONSTRAINT `ponto_reps_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `printers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `printers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `connection_type` enum('network','windows','linux') NOT NULL,
  `capability_profile` enum('default','simple','SP2000','TEP-200M','P822D') NOT NULL DEFAULT 'default',
  `char_per_line` varchar(191) DEFAULT NULL,
  `ip_address` varchar(191) DEFAULT NULL,
  `port` varchar(191) DEFAULT NULL,
  `path` varchar(191) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `printers_business_id_foreign` (`business_id`),
  CONSTRAINT `printers_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_bom`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_bom` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `parent_product_id` int(10) unsigned NOT NULL,
  `parent_variation_id` int(10) unsigned DEFAULT NULL,
  `component_product_id` int(10) unsigned NOT NULL,
  `component_variation_id` int(10) unsigned DEFAULT NULL,
  `qty_required` decimal(22,4) NOT NULL DEFAULT 1.0000,
  `is_optional` tinyint(1) NOT NULL DEFAULT 0,
  `allow_substitution` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pbom_biz_parent_idx` (`business_id`,`parent_product_id`),
  KEY `pbom_biz_component_idx` (`business_id`,`component_product_id`),
  KEY `pbom_parent_order_idx` (`parent_product_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_locations` (
  `product_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  KEY `product_locations_product_id_index` (`product_id`),
  KEY `product_locations_location_id_index` (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_racks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_racks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `location_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `rack` varchar(191) DEFAULT NULL,
  `row` varchar(191) DEFAULT NULL,
  `position` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_racks_business_id_index` (`business_id`),
  KEY `product_racks_location_id_index` (`location_id`),
  KEY `product_racks_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_variations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_variations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `variation_template_id` int(11) DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `is_dummy` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_variations_name_index` (`name`),
  KEY `product_variations_product_id_index` (`product_id`),
  CONSTRAINT `product_variations_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `type` enum('single','variable','modifier','combo') DEFAULT NULL,
  `unit_id` int(11) unsigned DEFAULT NULL,
  `secondary_unit_id` int(11) DEFAULT NULL,
  `sub_unit_ids` text DEFAULT NULL,
  `brand_id` int(10) unsigned DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `sub_category_id` int(10) unsigned DEFAULT NULL,
  `tax` int(10) unsigned DEFAULT NULL,
  `tax_type` enum('inclusive','exclusive') NOT NULL,
  `enable_stock` tinyint(1) NOT NULL DEFAULT 0,
  `alert_quantity` decimal(22,4) DEFAULT NULL,
  `sku` varchar(191) NOT NULL,
  `barcode_type` enum('C39','C128','EAN13','EAN8','UPCA','UPCE') DEFAULT 'C128',
  `expiry_period` decimal(4,2) DEFAULT NULL,
  `expiry_period_type` enum('days','months') DEFAULT NULL,
  `enable_sr_no` tinyint(1) NOT NULL DEFAULT 0,
  `weight` varchar(191) DEFAULT NULL,
  `product_custom_field1` varchar(191) DEFAULT NULL,
  `product_custom_field2` varchar(191) DEFAULT NULL,
  `product_custom_field3` varchar(191) DEFAULT NULL,
  `product_custom_field4` varchar(191) DEFAULT NULL,
  `product_custom_field5` varchar(191) DEFAULT NULL,
  `product_custom_field6` varchar(191) DEFAULT NULL,
  `product_custom_field7` varchar(191) DEFAULT NULL,
  `product_custom_field8` varchar(191) DEFAULT NULL,
  `product_custom_field9` varchar(191) DEFAULT NULL,
  `product_custom_field10` varchar(191) DEFAULT NULL,
  `product_custom_field11` varchar(191) DEFAULT NULL,
  `product_custom_field12` varchar(191) DEFAULT NULL,
  `product_custom_field13` varchar(191) DEFAULT NULL,
  `product_custom_field14` varchar(191) DEFAULT NULL,
  `product_custom_field15` varchar(191) DEFAULT NULL,
  `product_custom_field16` varchar(191) DEFAULT NULL,
  `product_custom_field17` varchar(191) DEFAULT NULL,
  `product_custom_field18` varchar(191) DEFAULT NULL,
  `product_custom_field19` varchar(191) DEFAULT NULL,
  `product_custom_field20` varchar(191) DEFAULT NULL,
  `image` varchar(191) DEFAULT NULL,
  `woocommerce_media_id` int(11) DEFAULT NULL,
  `product_description` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `preparation_time_in_minutes` int(11) DEFAULT NULL,
  `woocommerce_product_id` int(11) DEFAULT NULL,
  `woocommerce_disable_sync` tinyint(1) NOT NULL DEFAULT 0,
  `warranty_id` int(11) DEFAULT NULL,
  `is_inactive` tinyint(1) NOT NULL DEFAULT 0,
  `repair_model_id` int(10) unsigned DEFAULT NULL,
  `not_for_selling` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `perc_icms` decimal(4,2) NOT NULL DEFAULT 0.00,
  `perc_pis` decimal(4,2) NOT NULL DEFAULT 0.00,
  `perc_cofins` decimal(4,2) NOT NULL DEFAULT 0.00,
  `perc_ipi` decimal(4,2) NOT NULL DEFAULT 0.00,
  `cfop_interno` varchar(4) NOT NULL DEFAULT '5101',
  `cfop_externo` varchar(4) NOT NULL DEFAULT '6101',
  `cst_csosn` varchar(4) NOT NULL DEFAULT '101',
  `cst_pis` varchar(4) NOT NULL DEFAULT '49',
  `cst_cofins` varchar(4) NOT NULL DEFAULT '49',
  `cst_ipi` varchar(4) NOT NULL DEFAULT '99',
  `ncm` varchar(10) NOT NULL DEFAULT '0',
  `cest` varchar(10) DEFAULT NULL,
  `officeimpresso_codigo` varchar(255) DEFAULT NULL,
  `officeimpresso_dt_alteracao` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `products_brand_id_foreign` (`brand_id`),
  KEY `products_category_id_foreign` (`category_id`),
  KEY `products_sub_category_id_foreign` (`sub_category_id`),
  KEY `products_tax_foreign` (`tax`),
  KEY `products_name_index` (`name`),
  KEY `products_business_id_index` (`business_id`),
  KEY `products_unit_id_index` (`unit_id`),
  KEY `products_created_by_index` (`created_by`),
  KEY `products_warranty_id_index` (`warranty_id`),
  KEY `products_repair_model_id_foreign` (`repair_model_id`),
  KEY `products_woocommerce_product_id_index` (`woocommerce_product_id`),
  KEY `products_woocommerce_media_id_index` (`woocommerce_media_id`),
  KEY `products_repair_model_id_index` (`repair_model_id`),
  KEY `products_type_index` (`type`),
  KEY `products_tax_type_index` (`tax_type`),
  KEY `products_barcode_type_index` (`barcode_type`),
  KEY `products_secondary_unit_id_index` (`secondary_unit_id`),
  CONSTRAINT `products_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_repair_model_id_foreign` FOREIGN KEY (`repair_model_id`) REFERENCES `repair_device_models` (`id`),
  CONSTRAINT `products_sub_category_id_foreign` FOREIGN KEY (`sub_category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_tax_foreign` FOREIGN KEY (`tax`) REFERENCES `tax_rates` (`id`),
  CONSTRAINT `products_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produto_grupo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produto_grupo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(40) DEFAULT NULL,
  `referencia` varchar(15) DEFAULT NULL,
  `codplanocontas` varchar(15) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `officeimpresso_codigo` int(11) DEFAULT NULL,
  `officeimpresso_dt_alteracao` timestamp NULL DEFAULT NULL,
  `business_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `produto_grupo_referencia_unique` (`referencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `variation_id` int(10) unsigned NOT NULL,
  `quantity` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `secondary_unit_quantity` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `pp_without_discount` decimal(22,4) NOT NULL DEFAULT 0.0000 COMMENT 'Purchase price before inline discounts',
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Inline discount percentage',
  `purchase_price` decimal(22,4) NOT NULL,
  `purchase_price_inc_tax` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `item_tax` decimal(22,4) NOT NULL COMMENT 'Tax for one quantity',
  `tax_id` int(10) unsigned DEFAULT NULL,
  `purchase_requisition_line_id` int(11) DEFAULT NULL,
  `purchase_order_line_id` int(11) DEFAULT NULL,
  `quantity_sold` decimal(22,4) NOT NULL DEFAULT 0.0000 COMMENT 'Quanity sold from this purchase line',
  `quantity_adjusted` decimal(22,4) NOT NULL DEFAULT 0.0000 COMMENT 'Quanity adjusted in stock adjustment from this purchase line',
  `quantity_returned` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `po_quantity_purchased` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `mfg_quantity_used` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `mfg_date` date DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `lot_number` varchar(191) DEFAULT NULL,
  `sub_unit_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_lines_transaction_id_foreign` (`transaction_id`),
  KEY `purchase_lines_product_id_foreign` (`product_id`),
  KEY `purchase_lines_variation_id_foreign` (`variation_id`),
  KEY `purchase_lines_tax_id_foreign` (`tax_id`),
  KEY `purchase_lines_sub_unit_id_index` (`sub_unit_id`),
  KEY `purchase_lines_lot_number_index` (`lot_number`),
  CONSTRAINT `purchase_lines_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_lines_tax_id_foreign` FOREIGN KEY (`tax_id`) REFERENCES `tax_rates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_lines_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_lines_variation_id_foreign` FOREIGN KEY (`variation_id`) REFERENCES `variations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rb_boleto_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rb_boleto_credentials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `conta_bancaria_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK para fin_contas_bancarias — null quando for gateway puro (Asaas)',
  `banco` enum('inter','c6','asaas') NOT NULL,
  `ambiente` enum('production','sandbox') NOT NULL DEFAULT 'production',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `nome_display` varchar(191) DEFAULT NULL,
  `config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`config_json`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rb_boleto_cred_biz_banco_unique` (`business_id`,`banco`),
  KEY `rb_boleto_credentials_business_id_index` (`business_id`),
  KEY `rb_boleto_credentials_ativo_index` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rb_charge_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rb_charge_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `gateway` varchar(30) NOT NULL,
  `attempt_n` smallint(5) unsigned NOT NULL COMMENT '1ª tentativa = 1, retry = 2, 3, ...',
  `status` enum('pending','sent','succeeded','failed','soft_decline','hard_decline') NOT NULL,
  `request_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_json`)),
  `response_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_json`)),
  `error_code` varchar(50) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rb_charge_inv_attempt_unique` (`invoice_id`,`attempt_n`),
  KEY `rb_charge_inv_attempt_idx` (`invoice_id`,`attempt_n`),
  KEY `rb_charge_attempts_business_id_index` (`business_id`),
  KEY `rb_charge_attempts_gateway_index` (`gateway`),
  KEY `rb_charge_attempts_status_index` (`status`),
  CONSTRAINT `rb_charge_attempts_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `rb_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rb_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rb_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `subscription_id` bigint(20) unsigned DEFAULT NULL,
  `contact_id` int(10) unsigned NOT NULL COMMENT 'Denormalizado pra query rápida (subscriptions podem mover)',
  `numero_documento` varchar(50) NOT NULL COMMENT 'Display p/ cliente: "INV-2026-0001"',
  `valor` decimal(15,2) NOT NULL,
  `status` enum('open','paid','overdue','canceled','refunded') NOT NULL DEFAULT 'open',
  `vencimento` date NOT NULL,
  `pago_em` datetime DEFAULT NULL,
  `gateway` varchar(30) DEFAULT NULL COMMENT 'inter | c6 | asaas — null antes da 1ª charge attempt',
  `gateway_ref` varchar(100) DEFAULT NULL COMMENT 'ID do pagamento no gateway (ex: pay_xyz no Asaas, codigoSolicitacao Inter)',
  `conta_bancaria_id` int(10) unsigned DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rb_invoices_biz_num_unique` (`business_id`,`numero_documento`),
  KEY `rb_invoices_subscription_id_foreign` (`subscription_id`),
  KEY `rb_invoices_biz_status_venc_idx` (`business_id`,`status`,`vencimento`),
  KEY `rb_invoices_conta_fk` (`conta_bancaria_id`),
  KEY `rb_invoices_business_id_index` (`business_id`),
  KEY `rb_invoices_contact_id_index` (`contact_id`),
  KEY `rb_invoices_numero_documento_index` (`numero_documento`),
  KEY `rb_invoices_status_index` (`status`),
  KEY `rb_invoices_vencimento_index` (`vencimento`),
  CONSTRAINT `rb_invoices_conta_fk` FOREIGN KEY (`conta_bancaria_id`) REFERENCES `fin_contas_bancarias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rb_invoices_contact_fk` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rb_invoices_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `rb_subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rb_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rb_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `descricao_curta` varchar(200) DEFAULT NULL,
  `valor` decimal(15,2) NOT NULL,
  `ciclo` enum('monthly','quarterly','semiannual','yearly','custom') NOT NULL,
  `ciclo_dias` smallint(5) unsigned DEFAULT NULL COMMENT 'Apenas quando ciclo=custom',
  `trial_days` smallint(5) unsigned NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `fiscal_type` enum('nfe','nfse','none') NOT NULL DEFAULT 'none',
  `fiscal_cfop` varchar(8) DEFAULT NULL COMMENT 'CFOP NFe 55 quando fiscal_type=nfe',
  `fiscal_servico` varchar(8) DEFAULT NULL COMMENT 'Código serviço NFS-e quando fiscal_type=nfse',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rb_plans_biz_slug_unique` (`business_id`,`slug`),
  KEY `rb_plans_business_id_index` (`business_id`),
  KEY `rb_plans_ativo_index` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rb_subscription_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rb_subscription_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `subscription_id` bigint(20) unsigned NOT NULL,
  `kind` enum('event-create','event-status','event-plan','event-charge','event-retry','event-nf','note') NOT NULL,
  `by_actor` varchar(64) NOT NULL COMMENT 'Quem disparou: sistema, SEFAZ, Eliana, Wagner, contact.name, etc',
  `body` text NOT NULL,
  `occurred_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rb_events_sub_at_idx` (`subscription_id`,`occurred_at`),
  KEY `rb_events_biz_at_idx` (`business_id`,`occurred_at`),
  KEY `rb_subscription_events_business_id_index` (`business_id`),
  KEY `rb_subscription_events_occurred_at_index` (`occurred_at`),
  CONSTRAINT `rb_subscription_events_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `rb_subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rb_subscription_favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rb_subscription_favorites` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `subscription_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL COMMENT 'Favorito pessoal — FK users.id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rb_fav_user_sub_unique` (`user_id`,`subscription_id`),
  KEY `rb_subscription_favorites_subscription_id_foreign` (`subscription_id`),
  KEY `rb_subscription_favorites_business_id_index` (`business_id`),
  CONSTRAINT `rb_fav_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rb_subscription_favorites_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `rb_subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rb_subscription_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rb_subscription_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `subscription_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL COMMENT 'Autor — FK users.id (UPos legado int unsigned)',
  `body` text NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rb_notes_sub_pin_idx` (`subscription_id`,`is_pinned`),
  KEY `rb_notes_biz_created_idx` (`business_id`,`created_at`),
  KEY `rb_notes_user_fk` (`user_id`),
  KEY `rb_subscription_notes_business_id_index` (`business_id`),
  CONSTRAINT `rb_notes_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rb_subscription_notes_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `rb_subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rb_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rb_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `plan_id` bigint(20) unsigned NOT NULL,
  `contact_id` int(10) unsigned NOT NULL COMMENT 'FK contacts.id (UltimatePOS legado: int unsigned)',
  `status` enum('trialing','active','paused','canceled','past_due') NOT NULL DEFAULT 'active',
  `start_date` date NOT NULL,
  `next_due_date` date NOT NULL,
  `billing_anchor_date` date NOT NULL COMMENT 'Dia do mês que vira a fatura (ex: 5 = fatura todo dia 5)',
  `canceled_at` datetime DEFAULT NULL,
  `paused_at` datetime DEFAULT NULL,
  `conta_bancaria_id` int(10) unsigned DEFAULT NULL COMMENT 'Override: gateway específico pra cobrar este contrato',
  `payment_method` enum('pix','boleto','card') DEFAULT NULL,
  `last_jobsheet_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Soft link Modules/Repair JobSheet — sem FK pra preservar SoC Tier 0 (ADR 0094 §5)',
  `total_paid_cached` smallint(5) unsigned NOT NULL DEFAULT 0,
  `failed_count_cached` smallint(5) unsigned NOT NULL DEFAULT 0,
  `total_revenue_cached` decimal(14,2) NOT NULL DEFAULT 0.00,
  `paused_until` date DEFAULT NULL,
  `churn_reason` varchar(64) DEFAULT NULL,
  `contact_phone_cached` varchar(32) DEFAULT NULL COMMENT 'Denormalizado pra lista rápida — atualizado via Observer Contact',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rb_subscriptions_plan_id_foreign` (`plan_id`),
  KEY `rb_subs_biz_status_idx` (`business_id`,`status`),
  KEY `rb_subs_contact_idx` (`contact_id`),
  KEY `rb_subs_conta_fk` (`conta_bancaria_id`),
  KEY `rb_subscriptions_business_id_index` (`business_id`),
  KEY `rb_subscriptions_status_index` (`status`),
  KEY `rb_subscriptions_next_due_date_index` (`next_due_date`),
  CONSTRAINT `rb_subs_conta_fk` FOREIGN KEY (`conta_bancaria_id`) REFERENCES `fin_contas_bancarias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rb_subs_contact_fk` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rb_subscriptions_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `rb_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reference_counts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reference_counts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ref_type` varchar(191) NOT NULL,
  `ref_count` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reference_counts_business_id_index` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `repair_device_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `repair_device_models` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `repair_checklist` text DEFAULT NULL,
  `brand_id` int(10) unsigned DEFAULT NULL,
  `device_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `repair_device_models_business_id_foreign` (`business_id`),
  KEY `repair_device_models_brand_id_foreign` (`brand_id`),
  KEY `repair_device_models_device_id_foreign` (`device_id`),
  KEY `repair_device_models_created_by_foreign` (`created_by`),
  KEY `repair_device_models_business_id_index` (`business_id`),
  KEY `repair_device_models_brand_id_index` (`brand_id`),
  KEY `repair_device_models_device_id_index` (`device_id`),
  KEY `repair_device_models_created_by_index` (`created_by`),
  CONSTRAINT `repair_device_models_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  CONSTRAINT `repair_device_models_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `repair_device_models_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `repair_device_models_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `repair_job_sheets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `repair_job_sheets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `location_id` int(10) unsigned DEFAULT NULL,
  `contact_id` int(10) unsigned NOT NULL,
  `job_sheet_no` varchar(191) NOT NULL,
  `service_type` enum('carry_in','pick_up','on_site') NOT NULL,
  `pick_up_on_site_addr` text DEFAULT NULL,
  `brand_id` int(10) unsigned DEFAULT NULL,
  `device_id` int(10) unsigned DEFAULT NULL,
  `device_model_id` int(10) unsigned DEFAULT NULL,
  `checklist` text DEFAULT NULL,
  `security_pwd` varchar(191) DEFAULT NULL,
  `security_pattern` varchar(191) DEFAULT NULL,
  `serial_no` varchar(191) NOT NULL,
  `status_id` int(11) NOT NULL,
  `current_stage_id` bigint(20) unsigned DEFAULT NULL,
  `delivery_date` datetime DEFAULT NULL,
  `product_configuration` text DEFAULT NULL,
  `defects` text DEFAULT NULL,
  `product_condition` text DEFAULT NULL,
  `service_staff` int(10) unsigned DEFAULT NULL,
  `comment_by_ss` text DEFAULT NULL COMMENT 'comment made by technician',
  `estimated_cost` decimal(22,4) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `parts` text DEFAULT NULL,
  `custom_field_1` varchar(191) DEFAULT NULL,
  `custom_field_2` varchar(191) DEFAULT NULL,
  `custom_field_3` varchar(191) DEFAULT NULL,
  `custom_field_4` varchar(191) DEFAULT NULL,
  `custom_field_5` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `repair_job_sheets_business_id_foreign` (`business_id`),
  KEY `repair_job_sheets_contact_id_foreign` (`contact_id`),
  KEY `repair_job_sheets_brand_id_foreign` (`brand_id`),
  KEY `repair_job_sheets_device_id_foreign` (`device_id`),
  KEY `repair_job_sheets_device_model_id_foreign` (`device_model_id`),
  KEY `repair_job_sheets_service_staff_foreign` (`service_staff`),
  KEY `repair_job_sheets_created_by_foreign` (`created_by`),
  KEY `repair_job_sheets_business_id_index` (`business_id`),
  KEY `repair_job_sheets_location_id_index` (`location_id`),
  KEY `repair_job_sheets_contact_id_index` (`contact_id`),
  KEY `repair_job_sheets_brand_id_index` (`brand_id`),
  KEY `repair_job_sheets_device_id_index` (`device_id`),
  KEY `repair_job_sheets_device_model_id_index` (`device_model_id`),
  KEY `repair_job_sheets_status_id_index` (`status_id`),
  KEY `repair_job_sheets_service_staff_index` (`service_staff`),
  KEY `repair_job_sheets_created_by_index` (`created_by`),
  KEY `job_sheets_biz_stage_idx` (`business_id`,`current_stage_id`),
  CONSTRAINT `repair_job_sheets_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  CONSTRAINT `repair_job_sheets_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `repair_job_sheets_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `repair_job_sheets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `repair_job_sheets_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `repair_job_sheets_device_model_id_foreign` FOREIGN KEY (`device_model_id`) REFERENCES `repair_device_models` (`id`),
  CONSTRAINT `repair_job_sheets_service_staff_foreign` FOREIGN KEY (`service_staff`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `repair_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `repair_statuses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `color` varchar(191) DEFAULT NULL,
  `sort_order` int(11) DEFAULT NULL,
  `business_id` int(11) NOT NULL,
  `is_completed_status` tinyint(1) NOT NULL DEFAULT 0,
  `sms_template` text DEFAULT NULL,
  `email_subject` text DEFAULT NULL,
  `email_body` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `res_product_modifier_sets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `res_product_modifier_sets` (
  `modifier_set_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL COMMENT 'Table use to store the modifier sets applicable for a product',
  KEY `res_product_modifier_sets_modifier_set_id_foreign` (`modifier_set_id`),
  CONSTRAINT `res_product_modifier_sets_modifier_set_id_foreign` FOREIGN KEY (`modifier_set_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `res_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `res_tables` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `location_id` int(10) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `res_tables_business_id_foreign` (`business_id`),
  CONSTRAINT `res_tables_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` int(10) unsigned NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `guard_name` varchar(191) NOT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_service_staff` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `roles_business_id_foreign` (`business_id`),
  CONSTRAINT `roles_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_process_stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_process_stages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `process_id` bigint(20) unsigned NOT NULL,
  `key` varchar(80) NOT NULL,
  `name` varchar(150) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_initial` tinyint(1) NOT NULL DEFAULT 0,
  `is_terminal` tinyint(1) NOT NULL DEFAULT 0,
  `color` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sale_stages_proc_key_uq` (`process_id`,`key`),
  KEY `sale_stages_proc_sort_idx` (`process_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_processes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_processes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `key` varchar(80) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `default_for_contact_type` enum('cf','pf','pj','any') NOT NULL DEFAULT 'any',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sale_processes_biz_key_uq` (`business_id`,`key`),
  KEY `sale_processes_biz_active_idx` (`business_id`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_stage_action_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_stage_action_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `action_id` bigint(20) unsigned NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sale_action_roles_uq` (`action_id`,`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_stage_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_stage_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stage_id` bigint(20) unsigned NOT NULL,
  `key` varchar(80) NOT NULL,
  `label` varchar(150) NOT NULL,
  `target_stage_id` bigint(20) unsigned DEFAULT NULL,
  `event_class` varchar(255) DEFAULT NULL,
  `side_effect_class` varchar(255) DEFAULT NULL,
  `side_effect_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`side_effect_payload`)),
  `requires_confirmation` tinyint(1) NOT NULL DEFAULT 0,
  `is_critical` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sale_actions_stage_key_uq` (`stage_id`,`key`),
  KEY `sale_actions_target_idx` (`target_stage_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_stage_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_stage_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `transaction_id` bigint(20) unsigned NOT NULL,
  `action_id` bigint(20) unsigned DEFAULT NULL,
  `from_stage_id` bigint(20) unsigned DEFAULT NULL,
  `to_stage_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `payload_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_snapshot`)),
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sale_history_biz_tx_idx` (`business_id`,`transaction_id`),
  KEY `sale_history_biz_when_idx` (`business_id`,`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sell_line_warranties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sell_line_warranties` (
  `sell_line_id` int(11) NOT NULL,
  `warranty_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `selling_price_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `selling_price_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `selling_price_groups_business_id_foreign` (`business_id`),
  CONSTRAINT `selling_price_groups_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `service_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `transaction_id` bigint(20) unsigned DEFAULT NULL,
  `transaction_sell_line_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Linha de produto (transaction_sell_lines.id) que originou a OS. NULL = OS cobre venda toda (modo single, caso Martinho). Sem FK pra evitar cascade soft-delete.',
  `vehicle_id` bigint(20) unsigned NOT NULL,
  `contact_id` int(10) unsigned DEFAULT NULL COMMENT 'FK lógica pra contacts.id (cliente da OS — pode diferir de vehicle.contact_id em locações)',
  `order_type` enum('locacao','manutencao','mecanica') NOT NULL DEFAULT 'manutencao' COMMENT 'Tipo OS — locacao (sub-vertical 3 hipotético) | manutencao (legado cacamba) | mecanica (fluxo real reparo caminhão ADR 0194 · oficina_mecanica_os)',
  `delivery_address` varchar(255) DEFAULT NULL COMMENT 'Endereço entrega/coleta da caçamba (locação)',
  `expected_return_date` date DEFAULT NULL COMMENT 'Data prometida de devolução — base do alerta is_overdue',
  `daily_rate` decimal(10,2) DEFAULT NULL COMMENT 'Valor diária locação caçamba (BRL) — multiplicado por dias_locacao',
  `mileage_at_service` int(10) unsigned DEFAULT NULL,
  `fuel_level_at_entry` tinyint(3) unsigned DEFAULT NULL COMMENT 'Nível de combustível na entrada (0–100%) — barra no check-in do hero (US-OFICINA-039)',
  `entry_damages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Avarias marcadas na entrada — array de rótulos curtos (US-OFICINA-038)' CHECK (json_valid(`entry_damages`)),
  `box_label` varchar(50) DEFAULT NULL COMMENT 'Box físico onde OS está sendo executada (ex "Elevador 1"). MVP texto livre — sem tabela boxes até sinal qualificado ADR 0105.',
  `assigned_user_id` int(10) unsigned DEFAULT NULL COMMENT 'FK lógica users.id — mecânico responsável pela OS. Sem FK física (pattern Wave 5-A).',
  `status` varchar(30) NOT NULL DEFAULT 'aberta',
  `current_stage_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FSM stage atual (FK lógica sale_process_stages — sem FK física pra evitar cascade)',
  `entered_at` timestamp NULL DEFAULT NULL,
  `expected_completion` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_so_business_status` (`business_id`,`status`),
  KEY `idx_so_business_vehicle` (`business_id`,`vehicle_id`),
  KEY `idx_so_business_transaction` (`business_id`,`transaction_id`),
  KEY `fk_so_vehicle` (`vehicle_id`),
  KEY `idx_so_business_order_type` (`business_id`,`order_type`),
  KEY `idx_so_biz_tx_sell_line` (`business_id`,`transaction_id`,`transaction_sell_line_id`),
  KEY `idx_service_orders_business_current_stage` (`business_id`,`current_stage_id`),
  KEY `idx_service_orders_business_contact` (`business_id`,`contact_id`),
  KEY `idx_service_orders_business_assigned_user` (`business_id`,`assigned_user_id`),
  CONSTRAINT `fk_so_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_so_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(191) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` text NOT NULL,
  `last_activity` int(11) NOT NULL,
  UNIQUE KEY `sessions_id_unique` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sheet_spreadsheet_shares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_spreadsheet_shares` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sheet_spreadsheet_id` bigint(20) unsigned NOT NULL,
  `shared_with` varchar(191) NOT NULL COMMENT 'Shared with like user/role/todo',
  `shared_id` int(11) NOT NULL COMMENT 'Id of shared with like user_id/role_id/todo_id',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sheet_spreadsheet_shares_sheet_spreadsheet_id_foreign` (`sheet_spreadsheet_id`),
  KEY `sheet_spreadsheet_shares_shared_with_index` (`shared_with`),
  KEY `sheet_spreadsheet_shares_shared_id_index` (`shared_id`),
  CONSTRAINT `sheet_spreadsheet_shares_sheet_spreadsheet_id_foreign` FOREIGN KEY (`sheet_spreadsheet_id`) REFERENCES `sheet_spreadsheets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sheet_spreadsheets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_spreadsheets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `sheet_data` longtext NOT NULL,
  `created_by` int(11) NOT NULL,
  `folder_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sheet_spreadsheets_business_id_foreign` (`business_id`),
  KEY `sheet_spreadsheets_created_by_index` (`created_by`),
  CONSTRAINT `sheet_spreadsheets_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sla_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sla_policies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `label` varchar(80) NOT NULL COMMENT 'legível humano — ex "First response 1h"',
  `threshold_minutes` int(10) unsigned NOT NULL COMMENT 'tempo (min) sem resposta até disparar action',
  `triggers_on` enum('first_inbound_no_reply','open_aging','awaiting_human_aging') NOT NULL COMMENT 'condição de disparo — ver SlaEnforcer',
  `channel_id` bigint(20) unsigned DEFAULT NULL COMMENT 'null = aplica a TODOS canais do business',
  `tag_id` bigint(20) unsigned DEFAULT NULL COMMENT 'null = aplica a TODAS tags do business',
  `action_kind` enum('centrifugo_notify','reassign','set_status') NOT NULL COMMENT 'o que faz quando dispara',
  `action_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'ex {to_user_id:5} pra reassign | {status:"awaiting_human"} pra set_status' CHECK (json_valid(`action_params`)),
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sla_policies_biz_active_idx` (`business_id`,`active`),
  CONSTRAINT `sla_policies_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_adjustment_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_adjustment_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `variation_id` int(10) unsigned NOT NULL,
  `quantity` decimal(22,4) NOT NULL,
  `secondary_unit_quantity` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `unit_price` decimal(22,4) DEFAULT NULL COMMENT 'Last purchase unit price',
  `removed_purchase_line` int(11) DEFAULT NULL,
  `lot_no_line_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_adjustment_lines_product_id_foreign` (`product_id`),
  KEY `stock_adjustment_lines_variation_id_foreign` (`variation_id`),
  KEY `stock_adjustment_lines_transaction_id_index` (`transaction_id`),
  KEY `stock_adjustment_lines_lot_no_line_id_index` (`lot_no_line_id`),
  CONSTRAINT `stock_adjustment_lines_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_adjustment_lines_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_adjustment_lines_variation_id_foreign` FOREIGN KEY (`variation_id`) REFERENCES `variations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_adjustments_temp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_adjustments_temp` (
  `id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_reservations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `transaction_id` bigint(20) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `variation_id` int(10) unsigned NOT NULL,
  `location_id` int(10) unsigned NOT NULL,
  `qty_reserved` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `status` enum('active','consumed','released','expired') NOT NULL DEFAULT 'active',
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_res_biz_tx_idx` (`business_id`,`transaction_id`),
  KEY `stock_res_avail_idx` (`business_id`,`product_id`,`variation_id`,`status`),
  KEY `stock_res_expire_idx` (`status`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `package_id` int(10) unsigned NOT NULL,
  `start_date` date DEFAULT NULL,
  `trial_end_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `package_price` decimal(22,4) NOT NULL,
  `package_details` longtext NOT NULL,
  `created_id` int(10) unsigned NOT NULL,
  `paid_via` varchar(191) DEFAULT NULL,
  `payment_transaction_id` varchar(191) DEFAULT NULL,
  `status` enum('approved','waiting','declined') NOT NULL DEFAULT 'waiting',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscriptions_business_id_foreign` (`business_id`),
  KEY `subscriptions_package_id_index` (`package_id`),
  KEY `subscriptions_created_id_index` (`created_id`),
  CONSTRAINT `subscriptions_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `superadmin_communicator_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `superadmin_communicator_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_ids` text DEFAULT NULL,
  `subject` varchar(191) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `superadmin_frontend_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `superadmin_frontend_pages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) DEFAULT NULL,
  `slug` varchar(191) NOT NULL,
  `content` longtext NOT NULL,
  `is_shown` tinyint(1) NOT NULL,
  `menu_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `system` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(191) NOT NULL,
  `value` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tax_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `amount` double(22,4) NOT NULL,
  `is_tax_group` tinyint(1) NOT NULL DEFAULT 0,
  `for_tax_group` tinyint(4) NOT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `woocommerce_tax_rate_id` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_rates_business_id_foreign` (`business_id`),
  KEY `tax_rates_created_by_foreign` (`created_by`),
  KEY `tax_rates_woocommerce_tax_rate_id_index` (`woocommerce_tax_rate_id`),
  CONSTRAINT `tax_rates_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tax_rates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transaction_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `transaction_id` bigint(20) unsigned NOT NULL,
  `doc_type` enum('nfe55','nfce65','nfse56','nfcom62','mdfe58','cte57','boleto_asaas','boleto_inter') NOT NULL,
  `doc_class` varchar(255) NOT NULL,
  `doc_id` bigint(20) unsigned NOT NULL,
  `value_total` decimal(22,4) NOT NULL,
  `emitted_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','authorized','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tx_docs_tx_type_doc_uq` (`transaction_id`,`doc_type`,`doc_id`),
  KEY `tx_docs_biz_tx_idx` (`business_id`,`transaction_id`),
  KEY `tx_docs_biz_type_status_idx` (`business_id`,`doc_type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transaction_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) unsigned DEFAULT NULL,
  `business_id` int(11) DEFAULT NULL,
  `is_return` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Used during sales to return the change',
  `amount` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `method` varchar(191) DEFAULT NULL,
  `payment_type` varchar(191) DEFAULT NULL,
  `transaction_no` varchar(191) DEFAULT NULL,
  `card_transaction_number` varchar(191) DEFAULT NULL,
  `card_number` varchar(191) DEFAULT NULL,
  `card_type` varchar(191) DEFAULT NULL,
  `card_holder_name` varchar(191) DEFAULT NULL,
  `card_month` varchar(191) DEFAULT NULL,
  `card_year` varchar(191) DEFAULT NULL,
  `card_security` varchar(5) DEFAULT NULL,
  `cheque_number` varchar(191) DEFAULT NULL,
  `bank_account_number` varchar(191) DEFAULT NULL,
  `paid_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `paid_through_link` tinyint(1) NOT NULL DEFAULT 0,
  `gateway` varchar(191) DEFAULT NULL,
  `is_advance` tinyint(1) DEFAULT 0,
  `payment_for` int(11) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `note` varchar(191) DEFAULT NULL,
  `document` varchar(191) DEFAULT NULL,
  `payment_ref_no` varchar(191) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_payments_transaction_id_foreign` (`transaction_id`),
  KEY `transaction_payments_created_by_index` (`created_by`),
  KEY `transaction_payments_parent_id_index` (`parent_id`),
  KEY `transaction_payments_payment_type_index` (`payment_type`),
  CONSTRAINT `transaction_payments_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transaction_sell_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_sell_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `variation_id` int(10) unsigned NOT NULL,
  `quantity` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `secondary_unit_quantity` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `mfg_waste_percent` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `mfg_ingredient_group_id` int(11) DEFAULT NULL,
  `quantity_returned` decimal(20,4) NOT NULL DEFAULT 0.0000,
  `unit_price_before_discount` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `unit_price` decimal(22,4) DEFAULT NULL COMMENT 'Sell price excluding tax',
  `line_discount_type` enum('fixed','percentage') DEFAULT NULL,
  `line_discount_amount` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `unit_price_inc_tax` decimal(22,4) DEFAULT NULL COMMENT 'Sell price including tax',
  `item_tax` decimal(22,4) NOT NULL COMMENT 'Tax for one quantity',
  `tax_id` int(10) unsigned DEFAULT NULL,
  `discount_id` int(11) DEFAULT NULL,
  `lot_no_line_id` int(11) DEFAULT NULL,
  `sell_line_note` text DEFAULT NULL,
  `so_line_id` int(11) DEFAULT NULL,
  `so_quantity_invoiced` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `woocommerce_line_items_id` int(11) DEFAULT NULL,
  `res_service_staff_id` int(11) DEFAULT NULL,
  `res_line_order_status` varchar(191) DEFAULT NULL,
  `parent_sell_line_id` int(11) DEFAULT NULL,
  `children_type` varchar(191) NOT NULL DEFAULT '' COMMENT 'Type of children for the parent, like modifier or combo',
  `sub_unit_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tsl_dup_prevent` (`transaction_id`,`product_id`,`variation_id`),
  KEY `transaction_sell_lines_transaction_id_foreign` (`transaction_id`),
  KEY `transaction_sell_lines_product_id_foreign` (`product_id`),
  KEY `transaction_sell_lines_variation_id_foreign` (`variation_id`),
  KEY `transaction_sell_lines_tax_id_foreign` (`tax_id`),
  KEY `transaction_sell_lines_children_type_index` (`children_type`),
  KEY `transaction_sell_lines_parent_sell_line_id_index` (`parent_sell_line_id`),
  KEY `transaction_sell_lines_woocommerce_line_items_id_index` (`woocommerce_line_items_id`),
  KEY `transaction_sell_lines_line_discount_type_index` (`line_discount_type`),
  KEY `transaction_sell_lines_discount_id_index` (`discount_id`),
  KEY `transaction_sell_lines_lot_no_line_id_index` (`lot_no_line_id`),
  KEY `transaction_sell_lines_sub_unit_id_index` (`sub_unit_id`),
  CONSTRAINT `transaction_sell_lines_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaction_sell_lines_tax_id_foreign` FOREIGN KEY (`tax_id`) REFERENCES `tax_rates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaction_sell_lines_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaction_sell_lines_variation_id_foreign` FOREIGN KEY (`variation_id`) REFERENCES `variations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transaction_sell_lines_purchase_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_sell_lines_purchase_lines` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `sell_line_id` int(10) unsigned DEFAULT NULL COMMENT 'id from transaction_sell_lines',
  `stock_adjustment_line_id` int(10) unsigned DEFAULT NULL COMMENT 'id from stock_adjustment_lines',
  `purchase_line_id` int(10) unsigned NOT NULL COMMENT 'id from purchase_lines',
  `quantity` decimal(22,4) NOT NULL,
  `qty_returned` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sell_line_id` (`sell_line_id`),
  KEY `stock_adjustment_line_id` (`stock_adjustment_line_id`),
  KEY `purchase_line_id` (`purchase_line_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `process_id` bigint(20) unsigned DEFAULT NULL,
  `current_stage_id` bigint(20) unsigned DEFAULT NULL,
  `is_grouped_invoice` tinyint(1) NOT NULL DEFAULT 0,
  `location_id` int(10) unsigned DEFAULT NULL,
  `is_kitchen_order` tinyint(1) NOT NULL DEFAULT 0,
  `journal_entry_id` bigint(20) unsigned DEFAULT NULL,
  `res_table_id` int(10) unsigned DEFAULT NULL COMMENT 'fields to restaurant module',
  `res_waiter_id` int(10) unsigned DEFAULT NULL COMMENT 'fields to restaurant module',
  `res_order_status` enum('received','cooked','served') DEFAULT NULL,
  `type` varchar(191) DEFAULT NULL,
  `sub_type` varchar(20) DEFAULT NULL,
  `status` varchar(191) NOT NULL,
  `sub_status` varchar(191) DEFAULT NULL,
  `is_quotation` tinyint(1) NOT NULL DEFAULT 0,
  `payment_status` enum('paid','due','partial') DEFAULT NULL,
  `adjustment_type` enum('normal','abnormal') DEFAULT NULL,
  `contact_id` int(11) unsigned DEFAULT NULL,
  `customer_group_id` int(11) DEFAULT NULL COMMENT 'used to add customer group while selling',
  `invoice_no` varchar(191) DEFAULT NULL,
  `ref_no` varchar(191) DEFAULT NULL,
  `source` varchar(191) DEFAULT NULL,
  `os_ref` varchar(20) DEFAULT NULL COMMENT 'Referência cross-módulo OS-NNNN quando source=oficina · ADR 0192',
  `commission_split` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Split { mecanico_id, mecanico_pct, balcao_id, balcao_pct } total=100 · ADR 0192' CHECK (json_valid(`commission_split`)),
  `subscription_no` varchar(191) DEFAULT NULL,
  `subscription_repeat_on` varchar(191) DEFAULT NULL,
  `transaction_date` datetime NOT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL COMMENT 'Marcador de cancelamento · NULL = ativa · timestamp = cancelada (preserva row + audit) · ADR 0192 reverse hook',
  `invoiced_at` datetime DEFAULT NULL COMMENT 'US-SELL-021 · DT_FATURAMENTO legacy — quando a venda foi faturada',
  `invoice_sent_at` datetime DEFAULT NULL COMMENT 'US-SELL-021 · FATURAMENTO_DT_ENVIO legacy — quando a fatura foi enviada ao cliente',
  `competence_date` date DEFAULT NULL COMMENT 'US-SELL-021 · DT_COMPETENCIA legacy — mês contábil de competência (≠ emissão)',
  `due_date` date DEFAULT NULL COMMENT 'US-SELL-021 · PROJETO_DT_FIM legacy — data prometida pro cliente (entrega/serviço)',
  `total_before_tax` decimal(22,4) NOT NULL DEFAULT 0.0000 COMMENT 'Total before the purchase/invoice tax, this includeds the indivisual product tax',
  `tax_id` int(10) unsigned DEFAULT NULL,
  `tax_amount` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `discount_type` enum('fixed','percentage') DEFAULT NULL,
  `discount_amount` decimal(22,4) DEFAULT 0.0000,
  `rp_redeemed` int(11) NOT NULL DEFAULT 0 COMMENT 'rp is the short form of reward points',
  `rp_redeemed_amount` decimal(22,4) NOT NULL DEFAULT 0.0000 COMMENT 'rp is the short form of reward points',
  `shipping_details` varchar(191) DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `delivery_date` datetime DEFAULT NULL,
  `shipping_status` varchar(191) DEFAULT NULL,
  `delivered_to` varchar(191) DEFAULT NULL,
  `delivery_person` bigint(20) DEFAULT NULL,
  `shipping_charges` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `shipping_custom_field_1` varchar(191) DEFAULT NULL,
  `shipping_custom_field_2` varchar(191) DEFAULT NULL,
  `shipping_custom_field_3` varchar(191) DEFAULT NULL,
  `shipping_custom_field_4` varchar(191) DEFAULT NULL,
  `shipping_custom_field_5` varchar(191) DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `staff_note` text DEFAULT NULL,
  `is_export` tinyint(1) NOT NULL DEFAULT 0,
  `export_custom_fields_info` longtext DEFAULT NULL,
  `round_off_amount` decimal(22,4) NOT NULL DEFAULT 0.0000 COMMENT 'Difference of rounded total and actual total',
  `additional_expense_key_1` varchar(191) DEFAULT NULL,
  `additional_expense_value_1` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `additional_expense_key_2` varchar(191) DEFAULT NULL,
  `additional_expense_value_2` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `additional_expense_key_3` varchar(191) DEFAULT NULL,
  `additional_expense_value_3` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `additional_expense_key_4` varchar(191) DEFAULT NULL,
  `additional_expense_value_4` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `final_total` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `expense_category_id` int(10) unsigned DEFAULT NULL,
  `expense_sub_category_id` int(11) DEFAULT NULL,
  `expense_for` int(10) unsigned DEFAULT NULL,
  `commission_agent` int(11) DEFAULT NULL,
  `document` varchar(191) DEFAULT NULL,
  `is_direct_sale` tinyint(1) NOT NULL DEFAULT 0,
  `is_suspend` tinyint(1) NOT NULL DEFAULT 0,
  `exchange_rate` decimal(20,3) NOT NULL DEFAULT 1.000,
  `total_amount_recovered` decimal(22,4) DEFAULT NULL COMMENT 'Used for stock adjustment.',
  `transfer_parent_id` int(11) DEFAULT NULL,
  `return_parent_id` int(11) DEFAULT NULL,
  `opening_stock_product_id` int(11) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `purchase_requisition_ids` text DEFAULT NULL,
  `prefer_payment_method` varchar(191) DEFAULT NULL,
  `prefer_payment_account` int(11) DEFAULT NULL,
  `sales_order_ids` text DEFAULT NULL,
  `purchase_order_ids` text DEFAULT NULL,
  `custom_field_1` varchar(191) DEFAULT NULL,
  `custom_field_2` varchar(191) DEFAULT NULL,
  `custom_field_3` varchar(191) DEFAULT NULL,
  `custom_field_4` varchar(191) DEFAULT NULL,
  `crm_is_order_request` tinyint(1) NOT NULL DEFAULT 0,
  `essentials_duration` decimal(8,2) NOT NULL,
  `essentials_duration_unit` varchar(20) DEFAULT NULL,
  `essentials_amount_per_unit_duration` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `essentials_allowances` text DEFAULT NULL,
  `essentials_deductions` text DEFAULT NULL,
  `mfg_parent_production_purchase_id` int(11) DEFAULT NULL,
  `mfg_wasted_units` decimal(22,4) DEFAULT NULL,
  `mfg_production_cost` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `mfg_production_cost_type` varchar(191) DEFAULT 'percentage',
  `mfg_is_final` tinyint(1) NOT NULL DEFAULT 0,
  `woocommerce_order_id` int(11) DEFAULT NULL,
  `repair_completed_on` datetime DEFAULT NULL,
  `repair_warranty_id` int(11) DEFAULT NULL,
  `repair_brand_id` int(11) DEFAULT NULL,
  `repair_status_id` int(11) DEFAULT NULL,
  `repair_model_id` int(11) DEFAULT NULL,
  `repair_job_sheet_id` int(10) unsigned DEFAULT NULL,
  `repair_defects` text DEFAULT NULL,
  `repair_serial_no` varchar(191) DEFAULT NULL,
  `repair_checklist` text DEFAULT NULL,
  `repair_security_pwd` varchar(191) DEFAULT NULL,
  `repair_security_pattern` varchar(191) DEFAULT NULL,
  `repair_due_date` datetime DEFAULT NULL,
  `repair_device_id` int(11) DEFAULT NULL,
  `repair_updates_notif` tinyint(1) NOT NULL DEFAULT 0,
  `import_batch` int(11) DEFAULT NULL,
  `import_time` datetime DEFAULT NULL,
  `types_of_service_id` int(11) DEFAULT NULL,
  `packing_charge` decimal(22,4) DEFAULT NULL,
  `packing_charge_type` enum('fixed','percent') DEFAULT NULL,
  `service_custom_field_1` text DEFAULT NULL,
  `service_custom_field_2` text DEFAULT NULL,
  `service_custom_field_3` text DEFAULT NULL,
  `service_custom_field_4` text DEFAULT NULL,
  `service_custom_field_5` text DEFAULT NULL,
  `service_custom_field_6` text DEFAULT NULL,
  `is_created_from_api` tinyint(1) NOT NULL DEFAULT 0,
  `rp_earned` int(11) NOT NULL DEFAULT 0 COMMENT 'rp is the short form of reward points',
  `order_addresses` text DEFAULT NULL,
  `is_recurring` tinyint(1) NOT NULL DEFAULT 0,
  `recur_interval` double(22,4) DEFAULT NULL,
  `recur_interval_type` enum('days','months','years') DEFAULT NULL,
  `recur_repetitions` int(11) DEFAULT NULL,
  `recur_stopped_on` datetime DEFAULT NULL,
  `recur_parent_id` int(11) DEFAULT NULL,
  `invoice_token` varchar(191) DEFAULT NULL,
  `pay_term_number` int(11) DEFAULT NULL,
  `pay_term_type` enum('days','months') DEFAULT NULL,
  `selling_price_group_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `natureza_id` int(10) unsigned DEFAULT NULL,
  `placa` varchar(9) NOT NULL DEFAULT '',
  `uf` varchar(2) NOT NULL DEFAULT '',
  `valor_frete` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tipo` int(11) NOT NULL DEFAULT 0,
  `qtd_volumes` int(11) NOT NULL DEFAULT 0,
  `numeracao_volumes` varchar(20) NOT NULL DEFAULT '',
  `especie` varchar(20) NOT NULL DEFAULT '',
  `peso_liquido` decimal(8,3) NOT NULL DEFAULT 0.000,
  `peso_bruto` decimal(8,3) NOT NULL DEFAULT 0.000,
  `numero_nfe` int(11) NOT NULL DEFAULT 0,
  `numero_nfce` int(11) NOT NULL DEFAULT 0,
  `numero_nfe_entrada` int(11) NOT NULL DEFAULT 0,
  `chave` varchar(48) NOT NULL DEFAULT '',
  `chave_entrada` varchar(48) NOT NULL DEFAULT '',
  `sequencia_cce` int(11) NOT NULL DEFAULT 0,
  `cpf_nota` varchar(15) NOT NULL DEFAULT '',
  `troco` decimal(10,2) NOT NULL DEFAULT 0.00,
  `valor_recebido` decimal(10,2) NOT NULL DEFAULT 0.00,
  `transportadora_id` int(10) unsigned DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'NOVO',
  PRIMARY KEY (`id`),
  KEY `transactions_tax_id_foreign` (`tax_id`),
  KEY `transactions_business_id_index` (`business_id`),
  KEY `transactions_type_index` (`type`),
  KEY `transactions_contact_id_index` (`contact_id`),
  KEY `transactions_transaction_date_index` (`transaction_date`),
  KEY `transactions_created_by_index` (`created_by`),
  KEY `transactions_natureza_id_foreign` (`natureza_id`),
  KEY `transactions_transportadora_id_foreign` (`transportadora_id`),
  KEY `transactions_location_id_index` (`location_id`),
  KEY `transactions_expense_for_foreign` (`expense_for`),
  KEY `transactions_expense_category_id_index` (`expense_category_id`),
  KEY `transactions_sub_type_index` (`sub_type`),
  KEY `transactions_return_parent_id_index` (`return_parent_id`),
  KEY `type` (`type`),
  KEY `transactions_repair_model_id_index` (`repair_model_id`),
  KEY `transactions_repair_job_sheet_id_foreign` (`repair_job_sheet_id`),
  KEY `transactions_status_index` (`status`),
  KEY `transactions_woocommerce_order_id_index` (`woocommerce_order_id`),
  KEY `transactions_repair_warranty_id_index` (`repair_warranty_id`),
  KEY `transactions_repair_brand_id_index` (`repair_brand_id`),
  KEY `transactions_repair_status_id_index` (`repair_status_id`),
  KEY `transactions_repair_device_id_index` (`repair_device_id`),
  KEY `transactions_repair_job_sheet_id_index` (`repair_job_sheet_id`),
  KEY `transactions_sub_status_index` (`sub_status`),
  KEY `transactions_res_table_id_index` (`res_table_id`),
  KEY `transactions_res_waiter_id_index` (`res_waiter_id`),
  KEY `transactions_res_order_status_index` (`res_order_status`),
  KEY `transactions_payment_status_index` (`payment_status`),
  KEY `transactions_discount_type_index` (`discount_type`),
  KEY `transactions_commission_agent_index` (`commission_agent`),
  KEY `transactions_transfer_parent_id_index` (`transfer_parent_id`),
  KEY `transactions_types_of_service_id_index` (`types_of_service_id`),
  KEY `transactions_packing_charge_type_index` (`packing_charge_type`),
  KEY `transactions_recur_parent_id_index` (`recur_parent_id`),
  KEY `transactions_selling_price_group_id_index` (`selling_price_group_id`),
  KEY `transactions_mfg_parent_production_purchase_id_index` (`mfg_parent_production_purchase_id`),
  KEY `transactions_delivery_date_index` (`delivery_date`),
  KEY `transactions_delivery_person_index` (`delivery_person`),
  KEY `idx_repair_biz_status_due` (`business_id`,`sub_type`,`repair_status_id`,`repair_due_date`),
  KEY `idx_repair_biz_contact_created` (`business_id`,`sub_type`,`contact_id`,`created_at`),
  KEY `idx_repair_biz_waiter_status` (`business_id`,`sub_type`,`res_waiter_id`,`repair_status_id`),
  KEY `idx_repair_biz_creator_status` (`business_id`,`sub_type`,`created_by`,`repair_status_id`),
  KEY `idx_repair_biz_location_status` (`business_id`,`sub_type`,`location_id`,`repair_status_id`),
  KEY `transactions_biz_stage_idx` (`business_id`,`current_stage_id`),
  KEY `transactions_biz_invoiced_idx` (`business_id`,`invoiced_at`),
  KEY `transactions_biz_due_date_idx` (`business_id`,`due_date`),
  KEY `transactions_biz_grouped_idx` (`business_id`,`is_grouped_invoice`),
  KEY `idx_transactions_source` (`business_id`,`source`,`transaction_date`),
  CONSTRAINT `transactions_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_expense_category_id_foreign` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_expense_for_foreign` FOREIGN KEY (`expense_for`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `business_locations` (`id`),
  CONSTRAINT `transactions_natureza_id_foreign` FOREIGN KEY (`natureza_id`) REFERENCES `natureza_operacaos` (`id`),
  CONSTRAINT `transactions_repair_job_sheet_id_foreign` FOREIGN KEY (`repair_job_sheet_id`) REFERENCES `repair_job_sheets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_tax_id_foreign` FOREIGN KEY (`tax_id`) REFERENCES `tax_rates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_transportadora_id_foreign` FOREIGN KEY (`transportadora_id`) REFERENCES `transportadoras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `journal_transaction_number` varchar(191) NOT NULL,
  `transfer_from_id` bigint(20) unsigned NOT NULL,
  `transfer_to_id` bigint(20) unsigned NOT NULL,
  `transfer_by_id` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transportadoras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transportadoras` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `razao_social` varchar(100) NOT NULL,
  `cnpj_cpf` varchar(19) NOT NULL DEFAULT '000.000.000-00',
  `logradouro` varchar(80) NOT NULL,
  `cidade_id` int(10) unsigned NOT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transportadoras_cidade_id_foreign` (`cidade_id`),
  KEY `transportadoras_business_id_foreign` (`business_id`),
  CONSTRAINT `transportadoras_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transportadoras_cidade_id_foreign` FOREIGN KEY (`cidade_id`) REFERENCES `cities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `types_of_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `types_of_services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `business_id` int(11) NOT NULL,
  `location_price_group` text DEFAULT NULL,
  `packing_charge` decimal(22,4) DEFAULT NULL,
  `packing_charge_type` enum('fixed','percent') DEFAULT NULL,
  `enable_custom_fields` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `types_of_services_business_id_index` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `units` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `actual_name` varchar(191) NOT NULL,
  `short_name` varchar(191) NOT NULL,
  `allow_decimal` tinyint(1) NOT NULL,
  `base_unit_id` int(11) DEFAULT NULL,
  `base_unit_multiplier` decimal(20,4) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `exibir_comprimento` smallint(6) DEFAULT 0,
  `exibir_largura` smallint(6) DEFAULT 0,
  `exibir_espessura` smallint(6) DEFAULT 0,
  `calc_comprimento` smallint(6) DEFAULT 0,
  `calc_largura` smallint(6) DEFAULT 0,
  `calc_espessura` smallint(6) DEFAULT 0,
  `gera_lote` smallint(6) DEFAULT 0,
  `exibir_qtdmetricaunitaria` smallint(6) DEFAULT 0,
  `officeimpresso_codigo` int(11) DEFAULT NULL,
  `officeimpresso_dt_alteracao` timestamp NULL DEFAULT NULL,
  `formula` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `units_business_id_foreign` (`business_id`),
  KEY `units_created_by_foreign` (`created_by`),
  KEY `units_base_unit_id_index` (`base_unit_id`),
  CONSTRAINT `units_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `units_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_contact_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_contact_access` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_contact_access_user_id_index` (`user_id`),
  KEY `user_contact_access_contact_id_index` (`contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_lockouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_lockouts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `business_id` int(10) unsigned DEFAULT NULL,
  `locked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `locked_by` int(10) unsigned NOT NULL,
  `reason` varchar(500) NOT NULL,
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Snapshot do estado: roles, permissions, mcp_tokens, mcp_user_scopes ANTES do lock.' CHECK (json_valid(`snapshot`)),
  `unlocked_at` timestamp NULL DEFAULT NULL,
  `unlocked_by` int(10) unsigned DEFAULT NULL,
  `unlock_note` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ulck_user_locked_idx` (`user_id`,`locked_at`),
  KEY `ulck_biz_locked_idx` (`business_id`,`locked_at`),
  KEY `ulck_user_unlocked_idx` (`user_id`,`unlocked_at`),
  KEY `user_lockouts_locked_by_foreign` (`locked_by`),
  KEY `user_lockouts_unlocked_by_foreign` (`unlocked_by`),
  CONSTRAINT `user_lockouts_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`),
  CONSTRAINT `user_lockouts_unlocked_by_foreign` FOREIGN KEY (`unlocked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_lockouts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_type` varchar(191) NOT NULL DEFAULT 'user',
  `surname` char(10) DEFAULT NULL,
  `first_name` varchar(191) NOT NULL,
  `last_name` varchar(191) DEFAULT NULL,
  `username` varchar(191) DEFAULT NULL,
  `mcp_actor_id` bigint(20) unsigned DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `password` varchar(191) DEFAULT NULL,
  `language` char(7) NOT NULL DEFAULT 'pt',
  `contact_no` char(15) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `ui_theme` varchar(10) DEFAULT NULL,
  `ui_sidebar_collapsed` tinyint(1) NOT NULL DEFAULT 0,
  `business_id` int(10) unsigned DEFAULT NULL,
  `available_at` datetime DEFAULT NULL COMMENT 'Service staff avilable at. Calculated from product preparation_time_in_minutes',
  `paused_at` datetime DEFAULT NULL COMMENT 'Service staff available time paused at, Will be nulled on resume.',
  `essentials_department_id` int(11) DEFAULT NULL,
  `essentials_designation_id` int(11) DEFAULT NULL,
  `essentials_salary` decimal(22,4) DEFAULT NULL,
  `essentials_pay_period` varchar(191) DEFAULT NULL,
  `essentials_pay_cycle` varchar(191) DEFAULT NULL,
  `max_sales_discount_percent` decimal(5,2) DEFAULT NULL,
  `allow_login` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('active','inactive','terminated') NOT NULL DEFAULT 'active',
  `is_enable_service_staff_pin` tinyint(1) NOT NULL DEFAULT 0,
  `service_staff_pin` text DEFAULT NULL,
  `crm_contact_id` int(10) unsigned DEFAULT NULL,
  `is_cmmsn_agnt` tinyint(1) NOT NULL DEFAULT 0,
  `cmmsn_percent` decimal(4,2) NOT NULL DEFAULT 0.00,
  `selected_contacts` tinyint(1) NOT NULL DEFAULT 0,
  `dob` date DEFAULT NULL,
  `gender` varchar(191) DEFAULT NULL,
  `marital_status` enum('married','unmarried','divorced') DEFAULT NULL,
  `blood_group` char(10) DEFAULT NULL,
  `contact_number` char(20) DEFAULT NULL,
  `alt_number` varchar(191) DEFAULT NULL,
  `family_number` varchar(191) DEFAULT NULL,
  `fb_link` varchar(191) DEFAULT NULL,
  `twitter_link` varchar(191) DEFAULT NULL,
  `social_media_1` varchar(191) DEFAULT NULL,
  `social_media_2` varchar(191) DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `current_address` text DEFAULT NULL,
  `guardian_name` varchar(191) DEFAULT NULL,
  `custom_field_1` varchar(191) DEFAULT NULL,
  `custom_field_2` varchar(191) DEFAULT NULL,
  `custom_field_3` varchar(191) DEFAULT NULL,
  `custom_field_4` varchar(191) DEFAULT NULL,
  `bank_details` longtext DEFAULT NULL,
  `id_proof_name` varchar(191) DEFAULT NULL,
  `id_proof_number` varchar(191) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL COMMENT 'user primary work location',
  `crm_department` varchar(191) DEFAULT NULL COMMENT 'Contact person''s department',
  `crm_designation` varchar(191) DEFAULT NULL COMMENT 'Contact person''s designation',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `officeimpresso_codigo` int(11) DEFAULT NULL,
  `officeimpresso_dt_alteracao` timestamp NULL DEFAULT NULL,
  `officeimpresso_senha` varchar(191) DEFAULT NULL,
  `google_id` varchar(64) DEFAULT NULL,
  `microsoft_id` varchar(64) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_business_id_foreign` (`business_id`),
  KEY `users_user_type_index` (`user_type`),
  KEY `users_essentials_department_id_index` (`essentials_department_id`),
  KEY `users_essentials_designation_id_index` (`essentials_designation_id`),
  KEY `users_crm_contact_id_index` (`crm_contact_id`),
  KEY `users_google_id_index` (`google_id`),
  KEY `users_microsoft_id_index` (`microsoft_id`),
  CONSTRAINT `users_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_crm_contact_id_foreign` FOREIGN KEY (`crm_contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `variation_group_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `variation_group_prices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `variation_id` int(10) unsigned NOT NULL,
  `price_group_id` int(10) unsigned NOT NULL,
  `price_inc_tax` decimal(22,4) NOT NULL,
  `price_type` varchar(191) NOT NULL DEFAULT 'fixed',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `variation_group_prices_variation_id_foreign` (`variation_id`),
  KEY `variation_group_prices_price_group_id_foreign` (`price_group_id`),
  CONSTRAINT `variation_group_prices_price_group_id_foreign` FOREIGN KEY (`price_group_id`) REFERENCES `selling_price_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `variation_group_prices_variation_id_foreign` FOREIGN KEY (`variation_id`) REFERENCES `variations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `variation_location_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `variation_location_details` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `product_variation_id` int(10) unsigned NOT NULL COMMENT 'id from product_variations table',
  `variation_id` int(10) unsigned NOT NULL,
  `location_id` int(10) unsigned NOT NULL,
  `qty_available` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `variation_location_details_location_id_foreign` (`location_id`),
  KEY `variation_location_details_product_id_index` (`product_id`),
  KEY `variation_location_details_product_variation_id_index` (`product_variation_id`),
  KEY `variation_location_details_variation_id_index` (`variation_id`),
  CONSTRAINT `variation_location_details_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `business_locations` (`id`),
  CONSTRAINT `variation_location_details_variation_id_foreign` FOREIGN KEY (`variation_id`) REFERENCES `variations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `variation_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `variation_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `business_id` int(10) unsigned NOT NULL,
  `woocommerce_attr_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `variation_templates_business_id_foreign` (`business_id`),
  KEY `variation_templates_woocommerce_attr_id_index` (`woocommerce_attr_id`),
  CONSTRAINT `variation_templates_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `variation_value_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `variation_value_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `variation_template_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `variation_value_templates_name_index` (`name`),
  KEY `variation_value_templates_variation_template_id_index` (`variation_template_id`),
  CONSTRAINT `variation_value_templates_variation_template_id_foreign` FOREIGN KEY (`variation_template_id`) REFERENCES `variation_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `variations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `variations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `sub_sku` varchar(191) DEFAULT NULL,
  `product_variation_id` int(10) unsigned NOT NULL,
  `woocommerce_variation_id` int(11) DEFAULT NULL,
  `variation_value_id` int(11) DEFAULT NULL,
  `default_purchase_price` decimal(22,4) DEFAULT NULL,
  `dpp_inc_tax` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `profit_percent` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `default_sell_price` decimal(22,4) DEFAULT NULL,
  `sell_price_inc_tax` decimal(22,4) DEFAULT NULL COMMENT 'Sell price including tax',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `combo_variations` text DEFAULT NULL COMMENT 'Contains the combo variation details',
  PRIMARY KEY (`id`),
  KEY `variations_product_id_foreign` (`product_id`),
  KEY `variations_product_variation_id_foreign` (`product_variation_id`),
  KEY `variations_name_index` (`name`),
  KEY `variations_sub_sku_index` (`sub_sku`),
  KEY `variations_variation_value_id_index` (`variation_value_id`),
  KEY `variations_woocommerce_variation_id_index` (`woocommerce_variation_id`),
  CONSTRAINT `variations_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `variations_product_variation_id_foreign` FOREIGN KEY (`product_variation_id`) REFERENCES `product_variations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `contact_id` bigint(20) unsigned DEFAULT NULL,
  `plate` varchar(10) NOT NULL,
  `secondary_plate` varchar(10) DEFAULT NULL,
  `chassis` varchar(30) DEFAULT NULL,
  `secondary_chassis` varchar(30) DEFAULT NULL,
  `manufacture_year` smallint(6) DEFAULT NULL,
  `model_year` smallint(6) DEFAULT NULL,
  `renavam` varchar(11) DEFAULT NULL,
  `vehicle_type` enum('caminhao','cavalo','semi_reboque','cacamba_estacionaria','cacamba_avulsa','cacamba_caminhao','recapagem','automovel','motocicleta','outros','outro') NOT NULL DEFAULT 'cacamba_avulsa' COMMENT 'Tipo do veículo — ENUM expandido pra acomodar sub-vertical caçamba (Martinho)',
  `capacity_m3` decimal(5,2) DEFAULT NULL COMMENT 'Capacidade da caçamba em m³ (3.00, 5.00, 7.00 — caso Martinho)',
  `current_status` enum('disponivel','locada','manutencao','indisponivel') NOT NULL DEFAULT 'disponivel' COMMENT 'Estado denormalizado da caçamba — sincronizado com FSM via side-effect',
  `current_rental_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK soft pra service_orders.id quando locada (evita join nested em listagem)',
  `engine` varchar(50) DEFAULT NULL,
  `mileage_at_entry` int(10) unsigned DEFAULT NULL,
  `fuel_type` varchar(30) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `legacy_id` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vehicles_business_plate` (`business_id`,`plate`),
  KEY `idx_vehicles_business_contact` (`business_id`,`contact_id`),
  KEY `idx_vehicles_business_legacy` (`business_id`,`legacy_id`),
  KEY `idx_vehicles_business_status` (`business_id`,`current_status`),
  KEY `idx_vehicles_current_rental` (`current_rental_id`),
  CONSTRAINT `fk_vehicles_business` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `veiculos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `veiculos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `placa` varchar(8) NOT NULL,
  `uf` varchar(2) NOT NULL,
  `cor` varchar(10) NOT NULL,
  `marca` varchar(20) NOT NULL,
  `modelo` varchar(20) NOT NULL,
  `rntrc` varchar(12) NOT NULL,
  `tipo` varchar(2) NOT NULL,
  `tipo_carroceira` varchar(2) NOT NULL,
  `tipo_rodado` varchar(2) NOT NULL,
  `tara` varchar(10) NOT NULL,
  `capacidade` varchar(10) NOT NULL,
  `proprietario_documento` varchar(20) NOT NULL,
  `proprietario_nome` varchar(40) NOT NULL,
  `proprietario_ie` varchar(13) NOT NULL,
  `proprietario_uf` varchar(2) NOT NULL,
  `proprietario_tp` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `veiculos_business_id_foreign` (`business_id`),
  CONSTRAINT `veiculos_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vestuario_creditos_cliente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vestuario_creditos_cliente` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `contact_id` bigint(20) unsigned NOT NULL,
  `saldo_credito` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expira_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vest_credito_biz_contact` (`business_id`,`contact_id`),
  KEY `idx_vest_credito_business` (`business_id`),
  KEY `idx_vest_credito_expira` (`expira_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vestuario_devolucoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vestuario_devolucoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `transaction_id` bigint(20) unsigned NOT NULL,
  `transaction_sell_line_id` bigint(20) unsigned NOT NULL,
  `quantidade_devolvida` smallint(5) unsigned NOT NULL,
  `valor_devolvido` decimal(10,2) NOT NULL,
  `tipo` enum('troca_mesmo_produto','troca_outro_produto','credito_ficha','estorno_dinheiro') NOT NULL,
  `motivo` text NOT NULL,
  `processed_by_user_id` bigint(20) unsigned NOT NULL,
  `processed_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vest_devol_business` (`business_id`),
  KEY `idx_vest_devol_biz_tx` (`business_id`,`transaction_id`),
  KEY `idx_vest_devol_biz_tipo` (`business_id`,`tipo`),
  KEY `idx_vest_devol_processed_at` (`processed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vestuario_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vestuario_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vestuario_settings_business_id_unique` (`business_id`),
  KEY `idx_vestuario_settings_business` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warranties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `warranties` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `business_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `duration` int(11) NOT NULL,
  `duration_type` enum('days','months','years') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warranties_business_id_index` (`business_id`),
  KEY `warranties_duration_type_index` (`duration_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webhook_nonces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_nonces` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nonce` varchar(64) NOT NULL,
  `source` varchar(32) NOT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `webhook_nonces_nonce_unique` (`nonce`),
  KEY `webhook_nonces_created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_business_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_business_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `business_uuid` char(36) NOT NULL COMMENT 'usado no webhook URL',
  `driver` varchar(20) NOT NULL DEFAULT 'zapi',
  `fallback_driver` varchar(20) NOT NULL DEFAULT 'meta_cloud',
  `display_phone` varchar(20) DEFAULT NULL COMMENT 'preenchido após primeiro ping bem-sucedido',
  `meta_phone_number_id` varchar(64) DEFAULT NULL,
  `meta_waba_id` varchar(64) DEFAULT NULL COMMENT 'WhatsApp Business Account ID (Embedded Signup v4)',
  `meta_access_token` text DEFAULT NULL COMMENT 'encrypted cast Laravel',
  `meta_app_secret` text DEFAULT NULL COMMENT 'encrypted — usado pra HMAC webhook',
  `meta_webhook_verify_token` varchar(64) DEFAULT NULL,
  `zapi_instance_id` varchar(64) DEFAULT NULL,
  `zapi_instance_token` text DEFAULT NULL COMMENT 'encrypted',
  `zapi_client_token` text DEFAULT NULL COMMENT 'encrypted — header Client-Token + valida webhook',
  `lgpd_acknowledged_at` timestamp NULL DEFAULT NULL,
  `lgpd_acknowledged_by_user_id` int(10) unsigned DEFAULT NULL,
  `bot_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `template_repair_ready_name` varchar(64) DEFAULT NULL,
  `template_repair_waiting_parts_name` varchar(64) DEFAULT NULL,
  `template_billing_due_name` varchar(64) DEFAULT NULL,
  `template_billing_paid_name` varchar(64) DEFAULT NULL,
  `driver_health` enum('healthy','degraded','disconnected','banned','never_checked') NOT NULL DEFAULT 'never_checked',
  `driver_health_consecutive_failures` int(10) unsigned NOT NULL DEFAULT 0,
  `last_health_check_at` timestamp NULL DEFAULT NULL,
  `last_health_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_business_configs_business_uuid_unique` (`business_uuid`),
  KEY `wbc_biz_idx` (`business_id`),
  KEY `wbc_drv_health_idx` (`driver`,`driver_health`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_business_phones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_business_phones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `phone_uuid` char(36) NOT NULL COMMENT 'usado em webhook URL e Centrifugo channel granular',
  `label` varchar(80) NOT NULL COMMENT 'apelido livre Comercial/Financeiro/etc — Q4 ADR 0115',
  `driver` varchar(20) NOT NULL DEFAULT 'zapi' COMMENT 'zapi|meta_cloud|baileys|null — Evolution PROIBIDO Tier 0',
  `fallback_driver` varchar(20) NOT NULL DEFAULT 'meta_cloud',
  `display_phone` varchar(20) DEFAULT NULL COMMENT 'preenchido após primeiro ping bem-sucedido',
  `meta_phone_number_id` varchar(64) DEFAULT NULL,
  `meta_access_token` text DEFAULT NULL COMMENT 'encrypted cast Laravel',
  `meta_app_secret` text DEFAULT NULL COMMENT 'encrypted — usado pra HMAC webhook',
  `meta_webhook_verify_token` varchar(64) DEFAULT NULL,
  `zapi_instance_id` varchar(64) DEFAULT NULL,
  `zapi_instance_token` text DEFAULT NULL COMMENT 'encrypted',
  `zapi_client_token` text DEFAULT NULL COMMENT 'encrypted — header Client-Token + valida webhook',
  `lgpd_acknowledged_at` timestamp NULL DEFAULT NULL,
  `lgpd_acknowledged_by_user_id` int(10) unsigned DEFAULT NULL,
  `handles_repair_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'listener NotifyRepairCustomer dispara por este número?',
  `handles_billing` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'listeners RecurringBilling (InvoicePaid/Due) disparam por este número?',
  `handles_jana_bot` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'mensagens entrantes processadas pelo Jana bot saem por este número?',
  `handles_outbound_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'fallback se nenhum outro flag bate evento',
  `bot_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `template_repair_ready_name` varchar(64) DEFAULT NULL,
  `template_repair_waiting_parts_name` varchar(64) DEFAULT NULL,
  `template_billing_due_name` varchar(64) DEFAULT NULL,
  `template_billing_paid_name` varchar(64) DEFAULT NULL,
  `driver_health` enum('healthy','degraded','disconnected','banned','never_checked') NOT NULL DEFAULT 'never_checked',
  `driver_health_consecutive_failures` int(10) unsigned NOT NULL DEFAULT 0,
  `last_health_check_at` timestamp NULL DEFAULT NULL,
  `last_health_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_business_phones_phone_uuid_unique` (`phone_uuid`),
  KEY `wbp_biz_idx` (`business_id`),
  KEY `wbp_drv_health_idx` (`driver`,`driver_health`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_contact_bot_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_contact_bot_overrides` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `contact_id` int(10) unsigned NOT NULL COMMENT 'FK contacts UltimatePOS — sem FK formal (core table)',
  `bot_enabled` tinyint(1) NOT NULL COMMENT 'Override do flag global do canal/business — true reativa, false desativa',
  `set_by_user_id` int(10) unsigned NOT NULL COMMENT 'Atendente que executou /config (audit)',
  `reason` text DEFAULT NULL COMMENT 'Razão opcional pra audit (ex: "cliente reclamou que bot é chato")',
  `set_at` timestamp NOT NULL COMMENT 'Quando o override foi (re)definido',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wcbo_biz_contact_unq` (`business_id`,`contact_id`),
  KEY `wcbo_set_by_idx` (`set_by_user_id`,`set_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_conversation_metricas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_conversation_metricas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `metric_date` date NOT NULL,
  `channel_id` bigint(20) unsigned DEFAULT NULL COMMENT 'null = agregado do business inteiro',
  `conversations_opened` int(10) unsigned NOT NULL DEFAULT 0,
  `conversations_resolved` int(10) unsigned NOT NULL DEFAULT 0,
  `messages_inbound` int(10) unsigned NOT NULL DEFAULT 0,
  `messages_outbound` int(10) unsigned NOT NULL DEFAULT 0,
  `avg_first_response_seconds` int(10) unsigned DEFAULT NULL COMMENT 'tempo médio até 1ª resposta humana outbound',
  `avg_resolution_seconds` int(10) unsigned DEFAULT NULL COMMENT 'tempo médio até conversation.status=resolved',
  `total_cost_centavos` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'soma messages.cost_centavos do dia',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wa_metrics_uniq` (`business_id`,`metric_date`,`channel_id`),
  KEY `wa_metrics_biz_date_idx` (`business_id`,`metric_date`),
  KEY `whatsapp_conversation_metricas_channel_id_foreign` (`channel_id`),
  CONSTRAINT `whatsapp_conversation_metricas_channel_id_foreign` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_conversation_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_conversation_tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `tag_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by_user_id` int(10) unsigned DEFAULT NULL COMMENT 'atendente que aplicou a tag',
  PRIMARY KEY (`id`),
  UNIQUE KEY `wa_conv_tags_uniq` (`conversation_id`,`tag_id`),
  KEY `wa_conv_tags_tag_idx` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `whatsapp_business_phone_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK whatsapp_business_phones — nullable até data migration rodar',
  `contact_id` int(10) unsigned DEFAULT NULL COMMENT 'contacts.id, NULL se provisional',
  `customer_phone` varchar(20) NOT NULL COMMENT 'normalizado +5511987654321',
  `status` enum('open','awaiting_human','resolved','archived') NOT NULL DEFAULT 'open',
  `assigned_user_id` int(10) unsigned DEFAULT NULL COMMENT 'users.id atendente',
  `bot_handling` tinyint(1) NOT NULL DEFAULT 0,
  `last_inbound_at` timestamp NULL DEFAULT NULL COMMENT 'última msg cliente — usado pra janela 24h Meta',
  `last_outbound_at` timestamp NULL DEFAULT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL COMMENT 'maior(in,out) — sort lista',
  `unread_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `lid` varchar(100) DEFAULT NULL,
  `phone_e164` varchar(30) DEFAULT NULL,
  `bsuid` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wc_biz_phone_uniq` (`business_id`,`customer_phone`),
  KEY `wc_biz_lastmsg_idx` (`business_id`,`last_message_at`),
  KEY `wc_biz_status_idx` (`business_id`,`status`),
  KEY `wc_phone_idx` (`whatsapp_business_phone_id`),
  KEY `whatsapp_conversations_biz_lid_idx` (`business_id`,`lid`),
  KEY `whatsapp_conversations_biz_phone_idx` (`business_id`,`phone_e164`),
  KEY `whatsapp_conversations_biz_bsuid_idx` (`business_id`,`bsuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_csat_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_csat_responses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `resolved_message_id` bigint(20) unsigned NOT NULL COMMENT 'msg outbound que disparou a pergunta CSAT',
  `response_message_id` bigint(20) unsigned DEFAULT NULL COMMENT 'msg inbound onde cliente respondeu (nota)',
  `score` tinyint(3) unsigned DEFAULT NULL COMMENT '1-5; null=pending resposta',
  `comment` text DEFAULT NULL COMMENT 'cauda livre opcional ("5 obrigado")',
  `resolved_by_user_id` int(10) unsigned DEFAULT NULL COMMENT 'atendente que marcou conversa como resolved',
  `asked_at` timestamp NOT NULL COMMENT 'quando o dispatch da msg CSAT ocorreu',
  `responded_at` timestamp NULL DEFAULT NULL COMMENT 'quando parser populou score',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `csat_biz_created_idx` (`business_id`,`created_at`),
  KEY `csat_conv_idx` (`conversation_id`),
  KEY `csat_conv_pending_idx` (`conversation_id`,`score`,`asked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_jana_correcoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_jana_correcoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `message_id_errada` bigint(20) unsigned NOT NULL COMMENT 'FK pra messages(id) — msg do bot que foi corrigida',
  `correcao_texto` text NOT NULL COMMENT '"Deveria ter dito X" — fornecido pelo atendente humano',
  `contact_id` int(10) unsigned DEFAULT NULL COMMENT 'FK opcional contacts UltimatePOS — denormalizado da conv',
  `atendente_user_id` int(10) unsigned NOT NULL COMMENT 'User que corrigiu (sender_user_id da nota)',
  `training_status` varchar(20) NOT NULL DEFAULT 'pending_review' COMMENT 'pending_review | exported_for_fine_tune | rejected | applied',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'tokens, modelo usado, source_message_id da nota, etc' CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wjc_biz_status_idx` (`business_id`,`training_status`),
  KEY `wjc_msg_idx` (`message_id_errada`),
  CONSTRAINT `whatsapp_jana_correcoes_message_id_errada_foreign` FOREIGN KEY (`message_id_errada`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_lid_pn_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_lid_pn_map` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL COMMENT 'ADR 0093 Tier 0 — global scope obrigatório',
  `lid` varchar(100) NOT NULL COMMENT 'ex: "5196915463394@lid" cru OU "+519691546333945" se normalizado pelo controller',
  `phone_e164` varchar(32) DEFAULT NULL COMMENT 'null enquanto não descoberto — preenche quando WA envia senderPn',
  `source` enum('webhook_senderPn','manual','baileys_lookup') NOT NULL DEFAULT 'webhook_senderPn' COMMENT 'rastreia origem do mapping pra auditoria + decidir confiança',
  `first_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wa_lid_pn_business_lid_uniq` (`business_id`,`lid`),
  KEY `wa_lid_pn_business_phone_idx` (`business_id`,`phone_e164`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `whatsapp_business_phone_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK whatsapp_business_phones — nullable até data migration rodar',
  `conversation_id` bigint(20) unsigned NOT NULL,
  `direction` enum('inbound','outbound') NOT NULL,
  `provider` varchar(20) NOT NULL COMMENT 'zapi|meta_cloud|baileys|null — driver que enviou/recebeu',
  `provider_message_id` varchar(128) DEFAULT NULL COMMENT 'wamid.XYZ (Meta) ou messageId (Z-API/Baileys)',
  `type` enum('text','template','image','document','audio','interactive','location','contacts') NOT NULL DEFAULT 'text',
  `template_name` varchar(64) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'raw provider payload — auditoria' CHECK (json_valid(`payload`)),
  `status` enum('queued','sent','delivered','read','failed','received') NOT NULL,
  `failed_reason` varchar(255) DEFAULT NULL,
  `sender_user_id` int(10) unsigned DEFAULT NULL COMMENT 'só outbound humano',
  `sender_kind` enum('human','bot','system') DEFAULT NULL COMMENT 'só outbound',
  `is_internal_note` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'US-WA-071: defense-in-depth legacy schema',
  `cost_centavos` int(10) unsigned DEFAULT NULL COMMENT 'custo Meta da conversa (1ª msg da janela); zero pra zapi/baileys',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'só pra status updates do mesmo provider_message_id',
  `media_url` varchar(500) DEFAULT NULL,
  `media_mime` varchar(100) DEFAULT NULL,
  `media_size_bytes` bigint(20) unsigned DEFAULT NULL,
  `media_duration_s` smallint(5) unsigned DEFAULT NULL,
  `media_thumbnail_url` varchar(500) DEFAULT NULL,
  `media_transcription` text DEFAULT NULL,
  `media_filename` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wm_provider_msg_uniq` (`provider_message_id`),
  KEY `wm_biz_conv_created_idx` (`business_id`,`conversation_id`,`created_at`),
  KEY `wm_biz_status_idx` (`business_id`,`status`,`created_at`),
  KEY `wm_phone_idx` (`whatsapp_business_phone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_phone_user_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_phone_user_access` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `whatsapp_business_phone_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wpua_phone_user_unq` (`whatsapp_business_phone_id`,`user_id`),
  KEY `wpua_biz_user_idx` (`business_id`,`user_id`),
  CONSTRAINT `wpua_phone_fk` FOREIGN KEY (`whatsapp_business_phone_id`) REFERENCES `whatsapp_business_phones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_reminders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `contact_id` int(10) unsigned DEFAULT NULL,
  `atendente_user_id` int(10) unsigned NOT NULL COMMENT 'quem deve ser notificado (default = quem criou)',
  `created_by_user_id` int(10) unsigned NOT NULL COMMENT 'audit — quem escreveu /lembrete na nota',
  `due_at` timestamp NOT NULL,
  `body` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending|notified|done|cancelled',
  `notified_at` timestamp NULL DEFAULT NULL COMMENT 'preenchido pelo ProcessRemindersJob ao publicar Centrifugo',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'atendente clica Concluir → done',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wr_due_pending_idx` (`status`,`due_at`),
  KEY `wr_user_status_idx` (`atendente_user_id`,`status`),
  KEY `wr_biz_idx` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `slug` varchar(40) NOT NULL COMMENT 'slug imutável pra seeds reseed (ex: vendas, suporte)',
  `label` varchar(80) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT 'slate' COMMENT 'Tailwind palette key: blue|green|amber|red|slate|purple|emerald|rose',
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wa_tags_biz_slug_uniq` (`business_id`,`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(10) unsigned NOT NULL,
  `provider` varchar(20) NOT NULL DEFAULT 'zapi' COMMENT 'zapi|meta_cloud|baileys (locais) ou meta_cloud (HSM)',
  `meta_template_id` varchar(64) DEFAULT NULL COMMENT 'só pra provider=meta_cloud',
  `name` varchar(64) NOT NULL,
  `language` varchar(10) NOT NULL DEFAULT 'pt_BR',
  `category` enum('UTILITY','MARKETING','AUTHENTICATION') NOT NULL,
  `status` enum('PENDING','APPROVED','REJECTED','PAUSED','DISABLED','LOCAL') NOT NULL COMMENT 'LOCAL = template Z-API/Baileys sempre disponível',
  `components` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'estrutura header/body/footer/buttons' CHECK (json_valid(`components`)),
  `rejection_reason` varchar(255) DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wt_biz_prov_name_lang_uniq` (`business_id`,`provider`,`name`,`language`),
  KEY `wt_biz_status_idx` (`business_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `woocommerce_sync_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `woocommerce_sync_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `sync_type` varchar(191) NOT NULL,
  `operation_type` varchar(191) DEFAULT NULL,
  `data` longtext DEFAULT NULL,
  `details` longtext DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `get_current_brief` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`u906587222_oimpresso`@`localhost` PROCEDURE `get_current_brief`()
BEGIN
        SELECT
            b.id,
            b.generated_at,
            b.content,
            b.token_count,
            TIMESTAMPDIFF(MINUTE, b.generated_at, NOW()) AS staleness_minutes
        FROM mcp_briefs b
        WHERE b.valid = 1
        ORDER BY b.generated_at DESC
        LIMIT 1;
    END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `refresh_brief_inputs_cache` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`u906587222_oimpresso`@`localhost` PROCEDURE `refresh_brief_inputs_cache`()
BEGIN
        DECLARE v_active_cycle JSON;
        DECLARE v_hitl_pending JSON;
        DECLARE v_in_flight JSON;
        DECLARE v_recent_24h JSON;
        DECLARE v_skills_7d JSON;
        DECLARE v_skills_poda JSON;

        -- Cycle ativo
        SELECT JSON_OBJECT(
            'id', id,
            'key', `key`,
            'name', name,
            'start_date', start_date,
            'end_date', end_date,
            'goal', goal
        ) INTO v_active_cycle
        FROM mcp_cycles
        WHERE status = 'active'
        ORDER BY start_date DESC
        LIMIT 1;

        -- HITL pending: tasks blocked do Wagner
        SELECT JSON_ARRAYAGG(JSON_OBJECT(
            'id', id,
            'identifier', identifier,
            'title', title,
            'module', module,
            'priority', priority,
            'created_at', created_at
        )) INTO v_hitl_pending
        FROM (
            SELECT id, identifier, title, module, priority, created_at
            FROM mcp_tasks
            WHERE status = 'blocked'
              AND owner = 'wagner'
            ORDER BY priority DESC, created_at ASC
            LIMIT 10
        ) t;

        -- BRIEF-A1 fix #3: in_flight populado de mcp_tasks doing+review.
        -- Pivot do TODO Sprint 3 (mcp_design_locks ainda não existe).
        SELECT JSON_ARRAYAGG(JSON_OBJECT(
            'identifier', identifier,
            'title', title,
            'status', status,
            'owner', owner,
            'module', module,
            'priority', priority,
            'aging_hours', TIMESTAMPDIFF(HOUR, COALESCE(started_at, updated_at), NOW())
        )) INTO v_in_flight
        FROM (
            SELECT identifier, title, status, owner, module, priority, started_at, updated_at
            FROM mcp_tasks
            WHERE status IN ('doing', 'review')
            ORDER BY updated_at DESC
            LIMIT 10
        ) wf;

        -- BRIEF-A1 fix #1+#2: recent_24h corrigido.
        -- adrs_approved: CURDATE() - 1 DAY (cobre ontem+hoje, evita bug DATE-vs-DATETIME).
        -- commits_count → mcp_activity_24h (audit log real, não github inexistente).
        SET v_recent_24h = JSON_OBJECT(
            'adrs_approved', (
                SELECT JSON_ARRAYAGG(JSON_OBJECT('id', id, 'slug', slug, 'title', title))
                FROM mcp_memory_documents
                WHERE type = 'adr'
                  AND status = 'aceito'
                  AND decided_at >= CURDATE() - INTERVAL 1 DAY
            ),
            'mcp_activity_24h', (
                SELECT COUNT(*)
                FROM mcp_audit_log
                WHERE created_at > NOW() - INTERVAL 24 HOUR
                  AND status = 'ok'
                  AND tool_or_resource IS NOT NULL
            ),
            'mcp_distinct_tools_24h', (
                SELECT COUNT(DISTINCT tool_or_resource)
                FROM mcp_audit_log
                WHERE created_at > NOW() - INTERVAL 24 HOUR
                  AND status = 'ok'
                  AND tool_or_resource IS NOT NULL
            ),
            'mcp_distinct_users_24h', (
                SELECT COUNT(DISTINCT user_id)
                FROM mcp_audit_log
                WHERE created_at > NOW() - INTERVAL 24 HOUR
                  AND user_id IS NOT NULL
            ),
            'ads_escalations', 0,
            'incidents', 0
        );

        -- Skills uso 7d
        SELECT JSON_ARRAYAGG(JSON_OBJECT(
            'skill_name', skill_name,
            'trigger_count', trigger_count,
            'success_count', success_count,
            'tokens_saved', tokens_saved
        )) INTO v_skills_7d
        FROM (
            SELECT
                skill_name,
                COUNT(*) AS trigger_count,
                SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) AS success_count,
                SUM(tokens_saved_estimate) AS tokens_saved
            FROM mcp_skill_telemetry
            WHERE triggered_at > NOW() - INTERVAL 7 DAY
            GROUP BY skill_name
            ORDER BY trigger_count DESC
            LIMIT 10
        ) s;

        -- Skills candidatas a poda (zero disparos 30d)
        SELECT JSON_ARRAYAGG(skill_name) INTO v_skills_poda
        FROM (
            SELECT DISTINCT skill_name
            FROM mcp_skill_telemetry
            WHERE skill_name NOT IN (
                SELECT DISTINCT skill_name
                FROM mcp_skill_telemetry
                WHERE triggered_at > NOW() - INTERVAL 30 DAY
            )
        ) s;

        SET @v_flags = JSON_OBJECT(
            'migration_aging_critical', 0,
            'prs_stale_3d', 0,
            'visual_regression_failures_24h', 0
        );

        SET @v_brain_b_budget = JSON_OBJECT(
            'spent_usd', 0,
            'cap_usd', 50,
            'pct_used', 0
        );

        TRUNCATE TABLE mcp_brief_inputs_cache;

        INSERT INTO mcp_brief_inputs_cache (
            singleton_id, computed_at, active_cycle, hitl_pending,
            brain_b_budget, in_flight, recent_24h, skills_7d,
            skills_candidatas_poda, charters_stale, flags
        ) VALUES (
            1, NOW(), v_active_cycle, v_hitl_pending,
            @v_brain_b_budget, v_in_flight, v_recent_24h, v_skills_7d,
            v_skills_poda, NULL, @v_flags
        );
    END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50001 DROP VIEW IF EXISTS `copiloto_business_profile`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`u906587222_oimpresso`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `copiloto_business_profile` AS select `jana_business_profile`.`id` AS `id`,`jana_business_profile`.`business_id` AS `business_id`,`jana_business_profile`.`profile_text` AS `profile_text`,`jana_business_profile`.`tokens_estimated` AS `tokens_estimated`,`jana_business_profile`.`raw_context_tokens` AS `raw_context_tokens`,`jana_business_profile`.`gerado_em` AS `gerado_em`,`jana_business_profile`.`created_at` AS `created_at`,`jana_business_profile`.`updated_at` AS `updated_at` from `jana_business_profile` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `copiloto_cache_semantico`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`u906587222_oimpresso`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `copiloto_cache_semantico` AS select `jana_cache_semantico`.`id` AS `id`,`jana_cache_semantico`.`cache_key` AS `cache_key`,`jana_cache_semantico`.`business_id` AS `business_id`,`jana_cache_semantico`.`user_id` AS `user_id`,`jana_cache_semantico`.`query_original` AS `query_original`,`jana_cache_semantico`.`query_normalizada` AS `query_normalizada`,`jana_cache_semantico`.`query_embedding` AS `query_embedding`,`jana_cache_semantico`.`resposta` AS `resposta`,`jana_cache_semantico`.`metadata` AS `metadata`,`jana_cache_semantico`.`hits` AS `hits`,`jana_cache_semantico`.`ultimo_hit_em` AS `ultimo_hit_em`,`jana_cache_semantico`.`tokens_in` AS `tokens_in`,`jana_cache_semantico`.`tokens_out` AS `tokens_out`,`jana_cache_semantico`.`custo_brl_original` AS `custo_brl_original`,`jana_cache_semantico`.`expira_em` AS `expira_em`,`jana_cache_semantico`.`created_at` AS `created_at`,`jana_cache_semantico`.`updated_at` AS `updated_at` from `jana_cache_semantico` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `copiloto_conversas`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`u906587222_oimpresso`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `copiloto_conversas` AS select `jana_conversas`.`id` AS `id`,`jana_conversas`.`business_id` AS `business_id`,`jana_conversas`.`user_id` AS `user_id`,`jana_conversas`.`titulo` AS `titulo`,`jana_conversas`.`status` AS `status`,`jana_conversas`.`iniciada_em` AS `iniciada_em`,`jana_conversas`.`created_at` AS `created_at`,`jana_conversas`.`updated_at` AS `updated_at` from `jana_conversas` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `copiloto_memoria_facts`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`u906587222_oimpresso`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `copiloto_memoria_facts` AS select `jana_memoria_facts`.`id` AS `id`,`jana_memoria_facts`.`business_id` AS `business_id`,`jana_memoria_facts`.`user_id` AS `user_id`,`jana_memoria_facts`.`fato` AS `fato`,`jana_memoria_facts`.`metadata` AS `metadata`,`jana_memoria_facts`.`valid_from` AS `valid_from`,`jana_memoria_facts`.`valid_until` AS `valid_until`,`jana_memoria_facts`.`hits_count` AS `hits_count`,`jana_memoria_facts`.`ultimo_hit_em` AS `ultimo_hit_em`,`jana_memoria_facts`.`core_memory` AS `core_memory`,`jana_memoria_facts`.`created_at` AS `created_at`,`jana_memoria_facts`.`updated_at` AS `updated_at`,`jana_memoria_facts`.`deleted_at` AS `deleted_at` from `jana_memoria_facts` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `copiloto_memoria_gabarito`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`u906587222_oimpresso`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `copiloto_memoria_gabarito` AS select `jana_memoria_gabarito`.`id` AS `id`,`jana_memoria_gabarito`.`business_id` AS `business_id`,`jana_memoria_gabarito`.`categoria` AS `categoria`,`jana_memoria_gabarito`.`subcategoria` AS `subcategoria`,`jana_memoria_gabarito`.`pergunta` AS `pergunta`,`jana_memoria_gabarito`.`memoria_esperada_keys` AS `memoria_esperada_keys`,`jana_memoria_gabarito`.`resposta_esperada_pattern` AS `resposta_esperada_pattern`,`jana_memoria_gabarito`.`contexto_setup` AS `contexto_setup`,`jana_memoria_gabarito`.`dificuldade` AS `dificuldade`,`jana_memoria_gabarito`.`ativo` AS `ativo`,`jana_memoria_gabarito`.`notas` AS `notas`,`jana_memoria_gabarito`.`created_at` AS `created_at`,`jana_memoria_gabarito`.`updated_at` AS `updated_at` from `jana_memoria_gabarito` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `copiloto_memoria_metricas`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`u906587222_oimpresso`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `copiloto_memoria_metricas` AS select `jana_memoria_metricas`.`id` AS `id`,`jana_memoria_metricas`.`apurado_em` AS `apurado_em`,`jana_memoria_metricas`.`business_id` AS `business_id`,`jana_memoria_metricas`.`recall_at_3` AS `recall_at_3`,`jana_memoria_metricas`.`precision_at_3` AS `precision_at_3`,`jana_memoria_metricas`.`mrr` AS `mrr`,`jana_memoria_metricas`.`latencia_p95_ms` AS `latencia_p95_ms`,`jana_memoria_metricas`.`tokens_medio_interacao` AS `tokens_medio_interacao`,`jana_memoria_metricas`.`memory_bloat_ratio` AS `memory_bloat_ratio`,`jana_memoria_metricas`.`taxa_contradicoes_pct` AS `taxa_contradicoes_pct`,`jana_memoria_metricas`.`cross_tenant_violations` AS `cross_tenant_violations`,`jana_memoria_metricas`.`faithfulness` AS `faithfulness`,`jana_memoria_metricas`.`answer_relevancy` AS `answer_relevancy`,`jana_memoria_metricas`.`context_precision` AS `context_precision`,`jana_memoria_metricas`.`total_interacoes_dia` AS `total_interacoes_dia`,`jana_memoria_metricas`.`total_memorias_ativas` AS `total_memorias_ativas`,`jana_memoria_metricas`.`detalhes` AS `detalhes`,`jana_memoria_metricas`.`created_at` AS `created_at`,`jana_memoria_metricas`.`updated_at` AS `updated_at` from `jana_memoria_metricas` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `copiloto_mensagens`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`u906587222_oimpresso`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `copiloto_mensagens` AS select `jana_mensagens`.`id` AS `id`,`jana_mensagens`.`conversa_id` AS `conversa_id`,`jana_mensagens`.`role` AS `role`,`jana_mensagens`.`content` AS `content`,`jana_mensagens`.`tokens_in` AS `tokens_in`,`jana_mensagens`.`tokens_out` AS `tokens_out`,`jana_mensagens`.`created_at` AS `created_at` from `jana_mensagens` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `copiloto_meta_apuracoes`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`u906587222_oimpresso`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `copiloto_meta_apuracoes` AS select `jana_meta_apuracoes`.`id` AS `id`,`jana_meta_apuracoes`.`meta_id` AS `meta_id`,`jana_meta_apuracoes`.`data_ref` AS `data_ref`,`jana_meta_apuracoes`.`valor_realizado` AS `valor_realizado`,`jana_meta_apuracoes`.`calculado_em` AS `calculado_em`,`jana_meta_apuracoes`.`fonte_query_hash` AS `fonte_query_hash`,`jana_meta_apuracoes`.`created_at` AS `created_at`,`jana_meta_apuracoes`.`updated_at` AS `updated_at` from `jana_meta_apuracoes` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `copiloto_meta_fontes`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`u906587222_oimpresso`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `copiloto_meta_fontes` AS select `jana_meta_fontes`.`id` AS `id`,`jana_meta_fontes`.`meta_id` AS `meta_id`,`jana_meta_fontes`.`driver` AS `driver`,`jana_meta_fontes`.`config_json` AS `config_json`,`jana_meta_fontes`.`cadencia` AS `cadencia`,`jana_meta_fontes`.`created_at` AS `created_at`,`jana_meta_fontes`.`updated_at` AS `updated_at` from `jana_meta_fontes` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `copiloto_meta_periodos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`u906587222_oimpresso`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `copiloto_meta_periodos` AS select `jana_meta_periodos`.`id` AS `id`,`jana_meta_periodos`.`meta_id` AS `meta_id`,`jana_meta_periodos`.`tipo_periodo` AS `tipo_periodo`,`jana_meta_periodos`.`data_ini` AS `data_ini`,`jana_meta_periodos`.`data_fim` AS `data_fim`,`jana_meta_periodos`.`valor_alvo` AS `valor_alvo`,`jana_meta_periodos`.`trajetoria` AS `trajetoria`,`jana_meta_periodos`.`created_at` AS `created_at`,`jana_meta_periodos`.`updated_at` AS `updated_at` from `jana_meta_periodos` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `copiloto_metas`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`u906587222_oimpresso`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `copiloto_metas` AS select `jana_metas`.`id` AS `id`,`jana_metas`.`business_id` AS `business_id`,`jana_metas`.`slug` AS `slug`,`jana_metas`.`nome` AS `nome`,`jana_metas`.`unidade` AS `unidade`,`jana_metas`.`tipo_agregacao` AS `tipo_agregacao`,`jana_metas`.`ativo` AS `ativo`,`jana_metas`.`criada_por_user_id` AS `criada_por_user_id`,`jana_metas`.`origem` AS `origem`,`jana_metas`.`created_at` AS `created_at`,`jana_metas`.`updated_at` AS `updated_at` from `jana_metas` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `copiloto_negative_cache`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`u906587222_oimpresso`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `copiloto_negative_cache` AS select `jana_negative_cache`.`id` AS `id`,`jana_negative_cache`.`cache_key` AS `cache_key`,`jana_negative_cache`.`business_id` AS `business_id`,`jana_negative_cache`.`user_id` AS `user_id`,`jana_negative_cache`.`query_normalizada` AS `query_normalizada`,`jana_negative_cache`.`hits_negativos` AS `hits_negativos`,`jana_negative_cache`.`expira_em` AS `expira_em`,`jana_negative_cache`.`created_at` AS `created_at`,`jana_negative_cache`.`updated_at` AS `updated_at` from `jana_negative_cache` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `copiloto_sugestoes`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`u906587222_oimpresso`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `copiloto_sugestoes` AS select `jana_sugestoes`.`id` AS `id`,`jana_sugestoes`.`conversa_id` AS `conversa_id`,`jana_sugestoes`.`meta_id` AS `meta_id`,`jana_sugestoes`.`payload_json` AS `payload_json`,`jana_sugestoes`.`escolhida_em` AS `escolhida_em`,`jana_sugestoes`.`rejeitada_em` AS `rejeitada_em`,`jana_sugestoes`.`created_at` AS `created_at`,`jana_sugestoes`.`updated_at` AS `updated_at` from `jana_sugestoes` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

/*M!999999\- enable the sandbox mode */ 
SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2014_10_12_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2014_10_12_003455_create_cities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2014_10_12_100000_create_password_resets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2017_07_05_071953_create_currencies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2017_07_05_073658_create_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2017_07_05_074333_create_natureza_operacaos_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2017_07_22_075923_add_business_id_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2017_07_23_113209_create_brands_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2017_07_26_083429_create_permission_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2017_07_26_110000_create_tax_rates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2017_07_26_122313_create_units_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2017_07_27_075706_create_contacts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2017_08_04_071038_create_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2017_08_08_115903_create_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2017_08_09_061616_create_variation_templates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2017_08_09_061638_create_variation_value_templates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2017_08_10_061146_create_product_variations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2017_08_10_061216_create_variations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2017_08_18_054827_create_transportadoras_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2017_08_19_054827_create_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2017_08_31_073533_create_purchase_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2017_10_15_064638_create_transaction_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2017_10_31_065621_add_default_sales_tax_to_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2017_11_20_051930_create_table_group_sub_taxes',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2017_11_20_063603_create_transaction_sell_lines',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2017_11_21_064540_create_barcodes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2017_11_23_181237_create_invoice_schemes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2017_12_25_122822_create_business_locations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2017_12_25_160253_add_location_id_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2017_12_25_163227_create_variation_location_details_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2018_01_04_115627_create_sessions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2018_01_05_112817_create_invoice_layouts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2018_01_06_112303_add_invoice_scheme_id_and_invoice_layout_id_to_business_locations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2018_01_08_104124_create_expense_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2018_01_08_123327_modify_transactions_table_for_expenses',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2018_01_09_111005_modify_payment_status_in_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2018_01_09_111109_add_paid_on_column_to_transaction_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2018_01_25_172439_add_printer_related_fields_to_business_locations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2018_01_27_184322_create_printers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2018_01_30_181442_create_cash_registers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2018_01_31_125836_create_cash_register_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2018_02_07_173326_modify_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2018_02_08_105425_add_enable_product_expiry_column_to_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2018_02_08_111027_add_expiry_period_and_expiry_period_type_columns_to_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2018_02_08_131118_add_mfg_date_and_exp_date_purchase_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2018_02_08_155348_add_exchange_rate_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2018_02_09_124945_modify_transaction_payments_table_for_contact_payments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2018_02_12_113640_create_transaction_sell_lines_purchase_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2018_02_12_114605_add_quantity_sold_in_purchase_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2018_02_13_183323_alter_decimal_fields_size',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2018_02_14_161928_add_transaction_edit_days_to_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2018_02_15_161032_add_document_column_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2018_02_17_124709_add_more_options_to_invoice_layouts',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2018_02_19_111517_add_keyboard_shortcut_column_to_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2018_02_19_121537_stock_adjustment_move_to_transaction_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2018_02_20_165505_add_is_direct_sale_column_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2018_02_21_105329_create_system_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2018_02_23_100549_version_1_2',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2018_02_23_125648_add_enable_editing_sp_from_purchase_column_to_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2018_02_26_103612_add_sales_commission_agent_column_to_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2018_02_26_130519_modify_users_table_for_sales_cmmsn_agnt',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2018_02_26_134500_add_commission_agent_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2018_02_27_121422_add_item_addition_method_to_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2018_02_27_170232_modify_transactions_table_for_stock_transfer',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2018_03_05_153510_add_enable_inline_tax_column_to_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2018_03_06_210206_modify_product_barcode_types',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2018_03_13_181541_add_expiry_type_to_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2018_03_16_113446_product_expiry_setting_for_business',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2018_03_19_113601_add_business_settings_options',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2018_03_26_125334_add_pos_settings_to_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2018_03_26_165350_create_customer_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2018_03_27_122720_customer_group_related_changes_in_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2018_03_29_110138_change_tax_field_to_nullable_in_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2018_03_29_115502_add_changes_for_sr_number_in_products_and_sale_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2018_03_29_134340_add_inline_discount_fields_in_purchase_lines',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2018_03_31_140921_update_transactions_table_exchange_rate',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2018_04_03_103037_add_contact_id_to_contacts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2018_04_03_122709_add_changes_to_invoice_layouts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2018_04_09_135320_change_exchage_rate_size_in_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2018_04_17_123122_add_lot_number_to_business',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2018_04_17_160845_add_product_racks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2018_04_20_182015_create_res_tables_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2018_04_24_105246_restaurant_fields_in_transaction_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2018_04_24_114149_add_enabled_modules_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2018_04_24_133704_add_modules_fields_in_invoice_layout_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2018_04_27_132653_quotation_related_change',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2018_05_02_104439_add_date_format_and_time_format_to_business',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2018_05_02_111939_add_sell_return_to_transaction_payments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2018_05_14_114027_add_rows_positions_for_products',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2018_05_14_125223_add_weight_to_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2018_05_14_164754_add_opening_stock_permission',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2018_05_15_134729_add_design_to_invoice_layouts',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2018_05_16_183307_add_tax_fields_invoice_layout',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2018_05_18_191956_add_sell_return_to_transaction_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2018_05_21_131349_add_custom_fileds_to_contacts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2018_05_21_131607_invoice_layout_fields_for_sell_return',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2018_05_21_131949_add_custom_fileds_and_website_to_business_locations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2018_05_22_123527_create_reference_counts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2018_05_22_154540_add_ref_no_prefixes_column_to_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2018_05_24_132620_add_ref_no_column_to_transaction_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2018_05_24_161026_add_location_id_column_to_business_location_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2018_05_25_180603_create_modifiers_related_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2018_05_29_121714_add_purchase_line_id_to_stock_adjustment_line_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2018_05_31_114645_add_res_order_status_column_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2018_06_05_103530_rename_purchase_line_id_in_stock_adjustment_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2018_06_05_111905_modify_products_table_for_modifiers',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2018_06_06_110524_add_parent_sell_line_id_column_to_transaction_sell_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2018_06_07_152443_add_is_service_staff_to_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2018_06_07_182258_add_image_field_to_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2018_06_13_133705_create_bookings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2018_06_15_173636_add_email_column_to_contacts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2018_06_27_182835_add_superadmin_related_fields_business',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2018_06_27_185405_create_packages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2018_06_28_182803_create_subscriptions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2018_07_10_101913_add_custom_fields_to_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2018_07_17_103434_add_sales_person_name_label_to_invoice_layouts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2018_07_17_163920_add_theme_skin_color_column_to_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2018_07_17_182021_add_rows_to_system_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2018_07_19_131721_add_options_to_packages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2018_07_24_160319_add_lot_no_line_id_to_transaction_sell_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2018_07_25_110004_add_show_expiry_and_show_lot_colums_to_invoice_layouts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2018_07_25_172004_add_discount_columns_to_transaction_sell_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2018_07_26_124720_change_design_column_type_in_invoice_layouts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2018_07_26_170424_add_unit_price_before_discount_column_to_transaction_sell_line_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2018_07_28_103614_add_credit_limit_column_to_contacts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2018_08_08_110755_add_new_payment_methods_to_transaction_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2018_08_08_122225_modify_cash_register_transactions_table_for_new_payment_methods',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2018_08_14_104036_add_opening_balance_type_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2018_08_17_155534_add_min_termination_alert_days',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2018_08_28_105945_add_business_based_username_settings_to_system_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2018_08_30_105906_add_superadmin_communicator_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2018_09_04_155900_create_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2018_09_06_114438_create_selling_price_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2018_09_06_154057_create_variation_group_prices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2018_09_07_102413_add_permission_to_access_default_selling_price',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2018_09_07_134858_add_selling_price_group_id_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2018_09_10_112448_update_product_type_to_single_if_null_in_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2018_09_10_152703_create_account_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2018_09_10_173656_add_account_id_column_to_transaction_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2018_09_19_123914_create_notification_templates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2018_09_22_110504_add_sms_and_email_settings_columns_to_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2018_09_24_134942_add_lot_no_line_id_to_stock_adjustment_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2018_09_26_105557_add_transaction_payments_for_existing_expenses',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2018_09_27_111609_modify_transactions_table_for_purchase_return',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2018_09_27_131154_add_quantity_returned_column_to_purchase_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2018_10_02_131401_add_return_quantity_column_to_transaction_sell_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2018_10_03_104918_add_qty_returned_column_to_transaction_sell_lines_purchase_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2018_10_03_185947_add_default_notification_templates_to_database',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2018_10_09_153105_add_business_id_to_transaction_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2018_10_16_135229_create_permission_for_sells_and_purchase',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2018_10_22_114441_add_columns_for_variable_product_modifications',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2018_10_22_134428_modify_variable_product_data',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2018_10_30_181558_add_table_tax_headings_to_invoice_layout',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2018_10_31_122619_add_pay_terms_field_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2018_10_31_161328_add_new_permissions_for_pos_screen',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2018_10_31_174752_add_access_selected_contacts_only_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2018_10_31_175627_add_user_contact_access',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2018_10_31_180559_add_auto_send_sms_column_to_notification_templates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2018_11_02_130636_add_custom_permissions_to_packages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2018_11_02_171949_change_card_type_column_to_varchar_in_transaction_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2018_11_05_161848_add_more_fields_to_packages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2018_11_08_105621_add_role_permissions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2018_11_26_114135_add_is_suspend_column_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2018_11_28_104410_modify_units_table_for_multi_unit',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2018_11_28_170952_add_sub_unit_id_to_purchase_lines_and_sell_lines',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2018_11_29_115918_add_primary_key_in_system_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2018_12_03_185546_add_product_description_column_to_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2018_12_06_114937_modify_system_table_and_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2018_12_10_124621_modify_system_table_values_null_default',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2018_12_13_160007_add_custom_fields_display_options_to_invoice_layouts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2018_12_14_103307_modify_system_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2018_12_18_133837_add_prev_balance_due_columns_to_invoice_layouts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (173,'2018_12_18_170656_add_invoice_token_column_to_transaction_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2018_12_20_133639_add_date_time_format_column_to_invoice_layouts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2018_12_21_120659_add_recurring_invoice_fields_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2018_12_24_154933_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (177,'2019_01_08_112015_add_document_column_to_transaction_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (178,'2019_01_10_124645_add_account_permission',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (179,'2019_01_16_125825_add_subscription_no_column_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2019_01_28_111647_add_order_addresses_column_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2019_02_13_173821_add_is_inactive_column_to_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2019_02_19_103118_create_discounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2019_02_21_120324_add_discount_id_column_to_transaction_sell_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2019_02_21_134324_add_permission_for_discount',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2019_03_04_170832_add_service_staff_columns_to_transaction_sell_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (186,'2019_03_09_102425_add_sub_type_column_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2019_03_09_124457_add_indexing_transaction_sell_lines_purchase_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (188,'2019_03_12_120336_create_activity_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (189,'2019_03_15_132925_create_media_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (190,'2019_05_08_130339_add_indexing_to_parent_id_in_transaction_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (191,'2019_05_10_132311_add_missing_column_indexing',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (192,'2019_05_10_135434_add_missing_database_column_indexes',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (193,'2019_05_14_091812_add_show_image_column_to_invoice_layouts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2019_05_25_104922_add_view_purchase_price_permission',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2019_06_17_103515_add_profile_informations_columns_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2019_06_18_135524_add_permission_to_view_own_sales_only',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2019_06_19_112058_add_database_changes_for_reward_points',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (198,'2019_06_28_133732_change_type_column_to_string_in_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (199,'2019_07_13_111420_add_is_created_from_api_column_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (200,'2019_07_15_165136_add_fields_for_combo_product',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (201,'2019_07_19_103446_add_mfg_quantity_used_column_to_purchase_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (202,'2019_07_22_152649_add_not_for_selling_in_product_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (203,'2019_07_29_185351_add_show_reward_point_column_to_invoice_layouts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (204,'2019_08_08_162302_add_sub_units_related_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (205,'2019_08_16_115300_create_superadmin_frontend_pages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (206,'2019_08_26_133419_update_price_fields_decimal_point',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (207,'2019_09_02_160054_remove_location_permissions_from_roles',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (208,'2019_09_03_185259_add_permission_for_pos_screen',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (209,'2019_09_04_163141_add_location_id_to_cash_registers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (210,'2019_09_04_184008_create_types_of_services_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (211,'2019_09_06_131445_add_types_of_service_fields_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (212,'2019_09_09_134810_add_default_selling_price_group_id_column_to_business_locations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (213,'2019_09_12_105616_create_product_locations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (214,'2019_09_17_122522_add_custom_labels_column_to_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (215,'2019_09_18_164319_add_shipping_fields_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (216,'2019_09_19_170927_close_all_active_registers',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (217,'2019_09_23_161906_add_media_description_cloumn_to_media_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (218,'2019_10_18_155633_create_account_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (219,'2019_10_22_163335_add_common_settings_column_to_business_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (220,'2019_10_29_132521_add_update_purchase_status_permission',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (221,'2019_11_09_110522_add_indexing_to_lot_number',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (222,'2019_11_19_170824_add_is_active_column_to_business_locations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (223,'2019_11_21_162913_change_quantity_field_types_to_decimal',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (224,'2019_11_25_160340_modify_categories_table_for_polymerphic_relationship',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (225,'2019_12_02_105025_create_warranties_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (226,'2019_12_03_180342_add_common_settings_field_to_invoice_layouts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (227,'2019_12_05_183955_add_more_fields_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (228,'2019_12_06_174904_add_change_return_label_column_to_invoice_layouts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (229,'2019_12_11_121307_add_draft_and_quotation_list_permissions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (230,'2019_12_12_180126_copy_expense_total_to_total_before_tax',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (231,'2019_12_19_181412_make_alert_quantity_field_nullable_on_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (232,'2019_12_25_173413_create_dashboard_configurations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (233,'2020_01_08_133506_create_document_and_notes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (234,'2020_01_09_113252_add_cc_bcc_column_to_notification_templates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (235,'2020_01_16_174818_add_round_off_amount_field_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (236,'2020_01_28_162345_add_weighing_scale_settings_in_business_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (237,'2020_02_18_172447_add_import_fields_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (238,'2020_03_13_135844_add_is_active_column_to_selling_price_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (239,'2020_03_16_115449_add_contact_status_field_to_contacts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (240,'2020_03_26_124736_add_allow_login_column_in_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (241,'2020_04_13_154150_add_feature_products_column_to_business_loactions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (242,'2020_04_15_151802_add_user_type_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (243,'2020_04_22_153905_add_subscription_repeat_on_column_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (244,'2020_04_28_111436_add_shipping_address_to_contacts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (245,'2020_06_01_094654_add_max_sale_discount_column_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (246,'2020_08_16_154813_create_devolucaos_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (247,'2020_08_16_155443_create_item_devolucaos_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (248,'2020_11_14_143711_create_veiculos_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (249,'2020_11_15_142315_create_ctes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (250,'2020_11_15_142325_create_componente_ctes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (251,'2020_11_15_142337_create_medida_ctes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (252,'2020_11_21_090013_create_manifestos_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (253,'2020_11_21_090053_create_manifesto_limites_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (254,'2020_11_21_090341_create_item_dves_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (255,'2019_03_07_155813_make_repair_statuses_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (256,'2019_03_08_120634_add_repair_columns_to_transactions_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (257,'2019_03_14_182704_add_repair_permissions',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (258,'2019_03_29_110241_add_repair_version_column_to_system_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (259,'2019_04_12_113901_add_repair_settings_column_to_business_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (260,'2020_05_05_125008_create_device_models_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (261,'2020_05_06_103135_add_repair_model_id_column_to_products_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (262,'2020_07_11_120308_add_columns_to_repair_statuses_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (263,'2020_07_31_130737_create_job_sheets_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (264,'2020_08_07_124241_add_job_sheet_id_to_transactions_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (265,'2020_08_22_104640_add_email_template_field_to_repair_status_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (266,'2020_10_19_131934_add_job_sheet_custom_fields_to_repair_job_sheets_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (267,'2018_10_10_110400_add_module_version_to_system_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (268,'2018_10_10_122845_add_woocommerce_api_settings_to_business_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (269,'2018_10_10_162041_add_woocommerce_category_id_to_categories_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (270,'2018_10_11_173839_create_woocommerce_sync_logs_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (271,'2018_10_16_123522_add_woocommerce_tax_rate_id_column_to_tax_rates_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (272,'2018_10_23_111555_add_woocommerce_attr_id_column_to_variation_templates_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (273,'2018_12_03_163945_add_woocommerce_permissions',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (274,'2019_02_18_154414_change_woocommerce_sync_logs_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (275,'2019_04_19_174129_add_disable_woocommerce_sync_column_to_products_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (276,'2019_06_08_132440_add_woocommerce_wh_oc_secret_column_to_business_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (277,'2019_10_01_171828_add_woocommerce_media_id_columns',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (278,'2020_09_07_124952_add_woocommerce_skipped_orders_fields_to_business_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (279,'2019_07_15_114211_add_manufacturing_module_version_to_system_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (280,'2019_07_15_114403_create_mfg_recipes_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (281,'2019_07_18_180217_add_production_columns_to_transactions_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (282,'2019_07_26_110753_add_manufacturing_settings_column_to_business_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (283,'2019_07_26_170450_add_manufacturing_permissions',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (284,'2019_08_08_110035_create_mfg_recipe_ingredients_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (285,'2019_08_08_172837_add_recipe_add_edit_permissions',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (286,'2019_08_12_114610_add_ingredient_waste_percent_columns',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (287,'2019_11_05_115136_create_ingredient_groups_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (288,'2020_02_22_120303_add_column_to_mfg_recipe_ingredients_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (289,'2020_08_19_103831_add_production_cost_type_to_recipe_and_transaction_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (290,'2019_11_12_163135_create_projects_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (291,'2019_11_12_164431_create_project_members_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (292,'2019_11_14_112230_create_project_tasks_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (293,'2019_11_14_112258_create_project_task_members_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (294,'2019_11_18_154617_create_project_task_comments_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (295,'2019_11_19_134807_create_project_time_logs_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (296,'2019_12_11_102549_add_more_fields_in_transactions_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (297,'2019_12_11_102735_create_invoice_lines_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (298,'2020_01_07_172852_add_project_permissions',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (299,'2020_01_08_115422_add_project_module_version_to_system_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (300,'2020_07_10_114514_set_location_id_on_existing_invoice',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (301,'2019_07_15_114211_add_boleto_module_version_to_system_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (302,'2019_07_26_110753_add_boleto_settings_column_to_business_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (303,'2019_07_26_170450_add_boleto_permissions',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (304,'2016_06_01_000001_create_oauth_auth_codes_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (305,'2016_06_01_000002_create_oauth_access_tokens_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (306,'2016_06_01_000003_create_oauth_refresh_tokens_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (307,'2016_06_01_000004_create_oauth_clients_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (308,'2016_06_01_000005_create_oauth_personal_access_clients_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (309,'2019_07_15_114211_add_fiscal_module_version_to_system_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (310,'2019_07_26_110753_add_fiscal_settings_column_to_business_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (311,'2019_07_26_170450_add_fiscal_permissions',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (312,'2020_06_12_162245_modify_contacts_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (313,'2020_06_22_103104_change_recur_interval_default_to_one',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (314,'2020_07_09_174621_add_balance_field_to_contacts_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (315,'2020_07_23_104933_change_status_column_to_varchar_in_transaction_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (316,'2020_09_07_171059_change_completed_stock_transfer_status_to_final',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (317,'2020_09_21_123224_modify_booking_status_column_in_bookings_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (318,'2020_09_22_121639_create_discount_variations_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (319,'2020_10_05_121550_modify_business_location_table_for_invoice_layout',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (320,'2020_10_16_175726_set_status_as_received_for_opening_stock',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (321,'2020_10_23_170823_add_for_group_tax_column_to_tax_rates_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (322,'2020_11_04_130940_add_more_custom_fields_to_contacts_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (323,'2020_11_10_152841_add_cash_register_permissions',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (324,'2020_11_17_164041_modify_type_column_to_varchar_in_contacts_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (325,'2021_01_26_155139_add_image_table_users',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (326,'2021_01_26_155423_add_regime_table_contacts',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (327,'2020_08_18_123107_add_connector_module_version_to_system_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (328,'2020_09_29_184909_add_product_catalogue_version',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (329,'2021_02_16_190608_add_woocommerce_module_indexing',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (330,'2018_10_01_151252_create_documents_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (331,'2018_10_02_151803_create_document_shares_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (332,'2018_10_09_134558_create_reminders_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (333,'2018_11_16_170756_create_to_dos_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (334,'2019_02_22_120329_essentials_messages',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (335,'2019_02_22_161513_add_message_permissions',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (336,'2019_03_29_164339_add_essentials_version_to_system_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (337,'2019_05_17_153306_create_essentials_leave_types_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (338,'2019_05_17_175921_create_essentials_leaves_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (339,'2019_05_21_154517_add_essentials_settings_columns_to_business_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (340,'2019_05_21_181653_create_table_essentials_attendance',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (341,'2019_05_30_110049_create_essentials_payrolls_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (342,'2019_06_04_105723_create_essentials_holidays_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (343,'2019_06_28_134217_add_payroll_columns_to_transactions_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (344,'2019_08_26_103520_add_approve_leave_permission',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (345,'2019_08_27_103724_create_essentials_allowance_and_deduction_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (346,'2019_08_27_105236_create_essentials_user_allowances_and_deductions',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (347,'2019_09_20_115906_add_more_columns_to_essentials_to_dos_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (348,'2019_09_23_120439_create_essentials_todo_comments_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (349,'2019_12_05_170724_add_hrm_columns_to_users_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (350,'2019_12_09_105809_add_allowance_and_deductions_permission',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (351,'2020_03_28_152838_create_essentials_shift_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (352,'2020_03_30_162029_create_user_shifts_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (353,'2020_03_31_134558_add_shift_id_to_attendance_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (354,'2020_11_05_105157_modify_todos_date_column_type',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (355,'2020_11_11_174852_add_end_time_column_to_essentials_reminders_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (356,'2020_11_26_170527_create_essentials_kb_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (357,'2020_11_30_112615_create_essentials_kb_users_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (358,'2021_02_12_185514_add_clock_in_location_to_essentials_attendances_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (359,'2021_02_16_190203_add_essentials_module_indexing',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (360,'2021_02_27_133448_add_columns_to_users_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (361,'2021_03_04_174857_create_payroll_groups_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (362,'2021_03_04_175025_create_payroll_group_transactions_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (363,'2021_03_09_123914_add_auto_clockout_to_essentials_shifts',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (364,'2020_11_25_111050_add_parts_column_to_repair_job_sheets_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (365,'2020_12_30_101842_add_use_for_repair_column_to_brands_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (366,'2021_02_16_190423_add_repair_module_indexing',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (369,'2024_10_16_161122_create_licenca_log_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (373,'2024_11_05_101935_create_licenca_computador_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (374,'2024_11_07_083505_update_licenca_computador_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (375,'2020_03_19_130231_add_contact_id_to_users_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (376,'2020_03_27_133605_create_schedules_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (377,'2020_03_27_133628_create_schedule_users_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (378,'2020_03_30_112834_create_schedule_logs_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (379,'2020_04_02_182331_add_crm_module_version_to_system_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (380,'2020_04_08_153231_modify_cloumn_in_contacts_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (381,'2020_04_09_101052_create_lead_users_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (382,'2020_04_16_114747_create_crm_campaigns_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (383,'2021_01_07_155757_add_followup_additional_info_column_to_crm_schedules_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (384,'2021_02_02_140021_add_additional_info_to_crm_campaigns_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (385,'2021_02_02_173651_add_new_columns_to_contacts_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (386,'2021_02_04_120439_create_call_logs_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (387,'2021_02_08_172047_add_mobile_name_column_to_crm_call_logs_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (388,'2021_02_16_190038_add_crm_module_indexing',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (389,'2021_02_19_120846_create_crm_followup_invoices',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (390,'2021_02_22_132125_add_follow_up_by_to_crm_schedules_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (391,'2021_03_24_160736_add_department_and_designation_to_users_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (392,'2021_06_15_152924_create_proposal_templates_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (393,'2021_06_16_114448_add_recursive_fields_to_crm_schedules_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (394,'2021_06_16_125740_create_proposals_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (395,'2021_09_24_065738_add_crm_settings_column_to_business_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (397,'2024_11_07_111500_add_fields_to_business_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (398,'2024_11_08_075242_add_officeimpresso_limitemaquinas_to_packages_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (399,'2024_11_11_162652_add_officeimpresso_migration_to_brands_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (401,'2024_11_21_164209_add_officeimpresso_fields_to_users_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (402,'2024_11_28_134259_add_officeimpresso_senha_to_users_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (403,'2024_12_16_065654_add_officeimpresso_categories_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (404,'2024_12_16_075504_add_officeimpresso_fields_to_units_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (405,'2024_12_17_070922_add_officeimpresso_fields_to_products_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (406,'2024_12_17_133215_fix_officeimpresso_fields_to_cities_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (407,'2024_12_17_163927_add_officeimpresso_business_to_cities_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (409,'2024_12_18_145324_create_condicaopagto_table',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (410,'2024_12_19_092905_create_cidades_table',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (411,'2024_12_19_174606_create_pessoas_grupo_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (412,'2024_12_30_093956_create_nf_natureza_operacao_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (413,'2024_12_30_095801_create_produto_grupo_table',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (414,'2024_12_30_142344_create_nf_natureza_operacao_prodgrupo_table',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (415,'2025_01_06_073702_add_sync_fields_to_products_table',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (424,'2026_04_18_000001_create_ponto_colaborador_config_table',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (425,'2026_04_18_000002_create_ponto_reps_table',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (426,'2026_04_18_000003_create_ponto_escalas_table',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (427,'2026_04_18_000004_create_ponto_marcacoes_table',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (428,'2026_04_18_000005_create_ponto_intercorrencias_table',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (429,'2026_04_18_000006_create_ponto_apuracao_dia_table',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (430,'2026_04_18_000007_create_ponto_banco_horas_table',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (431,'2026_04_18_000008_create_ponto_importacoes_table',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (432,'2019_07_07_093258_create_chart_of_accounts_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (433,'2019_07_07_093648_create_journal_entries_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (434,'2019_07_07_110645_create_payment_types_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (435,'2020_08_19_175842_add_asset_management_module_version_to_system_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (436,'2020_08_20_114339_create_assets_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (437,'2020_08_20_173031_create_asset_transactions_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (438,'2020_08_21_180138_add_asset_settings_column_to_business_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (439,'2020_12_18_181447_add_shipping_custom_fields_to_transactions_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (440,'2020_12_22_164303_add_sub_status_column_to_transactions_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (441,'2020_12_23_125610_add_spreadsheet_version_to_system_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (442,'2020_12_23_153255_create_spreadsheets_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (443,'2020_12_24_153050_add_custom_fields_to_transactions_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (444,'2020_12_28_105403_add_whatsapp_text_column_to_notification_templates_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (445,'2020_12_29_165925_add_model_document_type_to_media_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (446,'2021_02_08_175632_add_contact_number_fields_to_users_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (447,'2021_02_11_172217_add_indexing_for_multiple_columns',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (448,'2021_02_16_190302_add_manufacturing_module_indexing',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (449,'2021_02_23_122043_add_more_columns_to_customer_groups_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (450,'2021_02_24_175551_add_print_invoice_permission_to_all_roles',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (451,'2021_03_03_162021_add_purchase_order_columns_to_purchase_lines_and_transactions_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (452,'2021_03_11_120229_add_sales_order_columns',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (453,'2021_03_12_175416_create_spreadsheet_shares_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (454,'2021_03_16_120705_add_business_id_to_activity_log_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (455,'2021_03_16_153427_add_code_columns_to_business_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (456,'2021_03_18_173308_add_account_details_column_to_accounts_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (457,'2021_03_18_183119_add_prefer_payment_account_columns_to_transactions_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (458,'2021_03_22_120810_add_more_types_of_service_custom_fields',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (459,'2021_03_24_183132_add_shipping_export_custom_field_details_to_contacts_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (460,'2021_03_25_170715_add_export_custom_fields_info_to_transactions_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (461,'2021_04_07_154331_add_mfg_ingredient_group_id_to_transaction_sell_lines_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (462,'2021_04_15_063449_add_denominations_column_to_cash_registers_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (463,'2021_05_22_083426_add_indexing_to_account_transactions_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (464,'2021_06_17_121451_add_location_id_to_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (465,'2021_07_08_065808_add_additional_expense_columns_to_transaction_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (466,'2021_07_13_082918_add_qr_code_columns_to_invoice_layouts_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (467,'2021_07_21_061615_add_fields_to_show_commission_agent_in_invoice_layout',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (468,'2021_08_13_105549_add_crm_contact_id_to_users_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (469,'2021_08_23_175321_add_contact_and_location_id_to_journal_entries_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (470,'2021_08_25_114932_add_payment_link_fields_to_transaction_payments_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (471,'2021_09_01_063110_add_spg_column_to_discounts_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (472,'2021_09_03_061528_modify_cash_register_transactions_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (473,'2021_09_28_091541_create_essentials_user_sales_targets_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (474,'2021_10_05_061658_add_source_column_to_transactions_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (475,'2021_10_29_110841_create_asset_warranties_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (476,'2021_11_29_170819_add_business_id_to_chart_of_accounts_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (477,'2021_12_16_121851_add_parent_id_column_to_expense_categories_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (478,'2022_01_17_202319_create_payment_details_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (479,'2022_01_19_034231_create_countries_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (480,'2022_02_01_031031_create_transfers_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (481,'2022_02_03_215602_create_budgets_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (482,'2022_02_08_113906_add_opening_balance_to_chart_of_accounts_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (483,'2022_02_08_121045_add_currency_id_to_chart_of_accounts_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (484,'2022_02_09_002406_add_payment_type_id_to_chart_of_accounts_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (485,'2022_02_09_055012_create_crm_marketplaces_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (486,'2022_02_09_125328_create_account_detail_types_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (487,'2022_02_09_223848_create_account_subtypes_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (488,'2022_02_09_223849_add_account_subtype_id_and_detail_type_id_to_chart_of_accounts_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (489,'2022_02_17_113045_add_source_id_to_marketplace',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (490,'2022_02_23_130555_add_journal_entry_id_to_transactions_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (491,'2022_03_02_180929_add_followup_category_id',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (492,'2022_03_17_140457_add_reconcile_opening_balance_to_chart_of_accounts_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (493,'2022_03_26_062215_create_asset_maintenances_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (494,'2022_04_11_163625_populate_account_subtypes_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (495,'2022_04_11_165143_populate_account_detail_types_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (496,'2022_04_14_075120_add_payment_type_column_to_transaction_payments_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (497,'2022_04_21_083327_create_cash_denominations_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (498,'2022_05_10_055307_add_delivery_date_column_to_transactions_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (499,'2022_05_11_070711_add_maintenance_note_column_to_asset_maintenances_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (500,'2022_05_26_061553_create_crm_contact_person_commissions_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (501,'2022_06_06_073006_add_cc_and_bcc_columns_to_crm_proposals_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (502,'2022_06_08_105942_create_branch_capital_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (503,'2022_06_13_123135_add_currency_precision_and_quantity_precision_fields_to_business_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (504,'2022_06_28_133342_add_secondary_unit_columns_to_products_sell_line_purchase_lines_tables',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (505,'2022_07_13_114307_create_purchase_requisition_related_columns',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (506,'2022_07_25_100234_change_payment_type_id_column_from_int_to_string_in_payment_details_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (507,'2022_08_04_143146_create_cms_pages_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (508,'2022_08_25_132707_add_service_staff_timer_fields_to_products_and_users_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (509,'2022_09_10_161849_add_layout_column_to_cms_pages_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (510,'2022_09_10_163209_create_cms_site_details_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (511,'2022_09_15_122547_create_cms_page_metas_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (512,'2022_09_16_130337_create_default_data_for_cms',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (513,'2022_12_23_150311_is_sincronizado_contacts',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (514,'2022_12_23_162847_add_repair_jobsheet_settings_column_to_business_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (515,'2023_01_16_124948_add_folder_id_column_to_sheet_spreadsheets_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (516,'2023_01_28_114255_add_letter_head_column_to_invoice_layouts_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (517,'2023_02_11_161510_add_event_column_to_activity_log_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (518,'2023_02_11_161511_add_batch_uuid_column_to_activity_log_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (519,'2023_02_17_140135_AddVersionForAiAssistance',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (520,'2023_02_21_182321_create_aiassistance_generation_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (521,'2023_03_02_170312_add_provider_to_oauth_clients_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (522,'2023_03_21_122731_add_sale_invoice_scheme_id_business_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (523,'2023_03_21_170446_add_number_type_to_invoice_scheme',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (524,'2023_04_17_155216_add_custom_fields_to_products',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (525,'2023_04_28_130247_add_price_type_to_group_price_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (526,'2023_06_21_033923_add_delivery_person_in_transactions',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (527,'2023_09_13_153555_add_service_staff_pin_columns_in_users',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (528,'2023_09_15_154404_add_is_kitchen_order_in_transactions',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (529,'2023_12_06_152840_add_contact_type_in_contacts',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (530,'2024_10_03_151459_modify_transaction_sell_lines_purchase_lines_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (531,'2025_03_07_114637_add_more_addresh_column_in_contact',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (532,'2025_02_07_184909_add_officeimpresso_version',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (533,'2026_04_21_000001_add_office_oimpresso_updated_at_to_contacts',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (534,'2026_04_22_000001_create_docs_sources_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (535,'2026_04_22_000002_create_docs_evidences_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (536,'2026_04_22_000003_create_docs_requirements_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (537,'2026_04_22_000004_create_docs_links_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (538,'2026_04_22_000005_create_docs_chat_messages_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (539,'2026_04_22_000006_create_docs_pages_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (540,'2026_04_22_000007_create_docs_validation_runs_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (541,'2026_04_22_000008_add_fulltext_index_to_docs_evidences',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (542,'2026_04_22_180000_add_ui_preferences_to_users_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (543,'2026_04_23_200000_create_licenca_log_table',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (544,'2026_04_23_200100_create_licenca_log_triggers',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (545,'2026_04_23_200200_add_indexes_to_licenca_computador',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (546,'2026_04_24_000000_drop_licenca_log_triggers',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (547,'2026_04_24_100000_add_fiscal_fields_to_business_locations',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (548,'2026_04_24_100500_add_business_location_id_to_licenca_log',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (549,'2026_04_24_140001_create_fin_planos_conta_table',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (550,'2026_04_24_140002_create_fin_categorias_table',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (551,'2026_04_24_140003_create_fin_contas_bancarias_table',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (552,'2026_04_24_140004_create_fin_titulos_table',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (553,'2026_04_24_140005_create_fin_titulo_baixas_table',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (554,'2026_04_24_140006_create_fin_caixa_movimentos_table',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (555,'2026_04_25_140101_create_fin_boleto_remessas_table',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (556,'2026_04_24_000001_create_copiloto_metas_table',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (557,'2026_04_24_000002_create_copiloto_meta_periodos_table',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (558,'2026_04_24_000003_create_copiloto_meta_fontes_table',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (559,'2026_04_24_000004_create_copiloto_meta_apuracoes_table',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (560,'2026_04_24_000005_create_copiloto_conversas_table',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (561,'2026_04_24_000006_create_copiloto_mensagens_table',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (562,'2026_04_24_000007_create_copiloto_sugestoes_table',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (563,'2026_04_26_120000_add_social_auth_columns_to_users_table',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (564,'2026_04_27_000001_create_copiloto_memoria_facts_table',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (565,'2026_04_29_000001_create_copiloto_memoria_metricas_table',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (566,'2026_04_29_100001_create_mcp_scopes_table',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (567,'2026_04_29_100002_create_mcp_user_scopes_table',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (568,'2026_04_29_100003_create_mcp_tokens_table',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (569,'2026_04_29_100004_create_mcp_quotas_table',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (570,'2026_04_29_100005_create_mcp_audit_log_table',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (571,'2026_04_29_100006_create_mcp_usage_diaria_table',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (572,'2026_04_29_100007_create_mcp_alertas_table',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (573,'2026_04_29_100008_create_mcp_memory_documents_table',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (574,'2026_04_29_100009_create_mcp_memory_documents_history_table',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (575,'2026_04_29_200001_create_copiloto_memoria_gabarito_table',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (576,'2026_04_29_400001_create_copiloto_cache_semantico_table',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (577,'2026_04_29_500001_create_copiloto_business_profile_table',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (578,'2026_04_29_500002_add_promotion_to_memoria_facts',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (579,'2026_04_29_500003_create_copiloto_negative_cache_table',60);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (580,'2026_04_29_600001_create_mcp_alertas_eventos_table',61);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (581,'2026_04_29_300001_create_mcp_cc_sessions_table',62);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (582,'2026_04_29_300002_create_mcp_cc_messages_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (583,'2026_04_29_300003_create_mcp_cc_blobs_table',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (584,'2026_04_30_120001_expand_mcp_memory_documents_type_enum',65);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (585,'2026_04_30_200001_add_business_id_to_mcp_memory_documents',66);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (586,'2026_04_30_180001_create_mcp_tasks_table',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (587,'2026_05_01_100001_add_typed_cols_to_mcp_memory_documents',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (588,'2026_05_01_120001_create_mcp_task_comments_table',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (589,'2026_05_01_120002_create_mcp_task_events_table',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (590,'2026_05_01_000001_create_nfe_certificados_table',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (591,'2026_05_01_000002_create_nfse_provider_configs_table',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (592,'2026_05_01_000003_create_nfse_emissoes_table',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (593,'2026_05_01_000004_add_prestador_cnpj_to_nfse_provider_configs',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (599,'2026_05_03_000001_add_transaction_id_to_nfse_emissoes',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (600,'2026_05_03_180001_add_dismissed_at_to_dual_brain_decisions',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (601,'2026_05_03_200001_add_learning_loop_columns',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (609,'2026_05_03_100001_create_user_lockouts_table',79);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (610,'2026_05_03_000001_create_mcp_file_locks_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (611,'2026_05_03_000002_create_mcp_decision_thresholds_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (612,'2026_05_03_000003_create_mcp_confidence_scores_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (613,'2026_05_03_000004_create_mcp_dual_brain_decisions_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (614,'2026_05_03_000005_create_mcp_decision_patterns_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (615,'2026_05_03_220001_create_mcp_governance_rules_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (616,'2026_05_03_230001_create_mcp_projects_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (617,'2026_05_03_230002_create_mcp_project_parts_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (618,'2026_05_03_230003_link_decisions_to_projects',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (619,'2026_05_03_240001_create_mcp_tool_executions_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (620,'2026_05_03_250001_create_mcp_decision_links_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (621,'2026_05_03_260001_create_mcp_user_module_access_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (622,'2026_05_04_180001_create_mcp_jira_projects_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (623,'2026_05_04_180002_create_mcp_epics_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (624,'2026_05_04_180003_create_mcp_cycles_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (625,'2026_05_04_180004_create_mcp_cycle_goals_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (626,'2026_05_04_180005_create_mcp_components_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (627,'2026_05_04_180006_create_mcp_workflows_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (628,'2026_05_04_180007_create_mcp_issue_templates_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (629,'2026_05_04_180008_create_mcp_views_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (630,'2026_05_04_180009_create_mcp_inbox_notifications_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (631,'2026_05_04_180010_create_mcp_task_dependencies_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (632,'2026_05_04_180011_create_mcp_task_watchers_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (633,'2026_05_04_180012_create_mcp_task_attachments_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (634,'2026_05_04_180013_create_mcp_task_memory_links_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (635,'2026_05_04_180014_create_mcp_git_links_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (636,'2026_05_04_180015_extend_mcp_tasks_for_jira_style',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (637,'2026_05_05_220001_create_mcp_skills_table',81);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (638,'2026_05_05_220002_create_mcp_skill_versions_table',81);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (639,'2026_05_05_220003_create_mcp_skill_labels_table',81);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (640,'2026_05_05_220004_create_mcp_skill_test_runs_table',82);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (641,'2026_05_05_220005_create_mcp_skill_approvals_table',83);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (642,'2026_05_05_230001_add_immutability_triggers_to_mcp_audit_log',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (643,'2026_05_05_240001_create_mcp_actors_and_link_tokens',85);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (644,'2026_05_05_240002_seed_initial_actors',85);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (645,'2026_05_06_000001_create_rb_boleto_credentials_table',86);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (647,'2026_05_06_000001_add_rb_gateway_credential_to_fin_contas_bancarias',87);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (648,'2026_05_06_000002_add_conta_bancaria_fk_to_rb_boleto_credentials',88);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (649,'2026_05_06_000002_add_saldo_cached_to_fin_contas_bancarias',88);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (650,'2026_05_06_000003_create_pg_webhook_events_table',88);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (651,'2026_05_06_001000_create_rb_plans_table',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (652,'2026_05_06_001001_create_rb_subscriptions_table',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (653,'2026_05_06_001002_create_rb_invoices_table',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (654,'2026_05_06_001003_create_rb_charge_attempts_table',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (655,'2026_05_06_002000_create_nfe_certificados_table',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (656,'2026_05_06_002001_create_nfe_emissoes_table',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (657,'2026_05_06_002002_create_nfe_eventos_table',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (658,'2026_05_06_002003_create_nfe_inutilizacoes_table',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (659,'2026_05_06_170045_create_daily_brief_schema',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (660,'2026_05_06_172445_fix_brief_procedure_real_schema',90);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (661,'2026_05_06_010000_create_nfe_fiscal_rules_table',91);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (662,'2026_05_06_010001_create_nfe_business_configs_table',91);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (663,'2026_05_06_120000_rename_copiloto_tables_to_jana',92);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (664,'2026_05_06_020000_create_nfe_fiscal_rule_tax_rate_links_table',93);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (665,'2026_05_06_180000_add_repair_listing_indexes',93);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (666,'2026_05_07_120000_fix_brief_aggregator_in_flight_adrs_activity',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (667,'2026_05_07_000001_create_whatsapp_business_configs_table',95);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (668,'2026_05_07_000002_create_whatsapp_conversations_table',95);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (669,'2026_05_07_000003_create_whatsapp_messages_table',95);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (670,'2026_05_07_000004_create_whatsapp_templates_table',95);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (671,'2026_05_07_210000_migrate_nfe_certificados_nfse_to_nfe_brasil_schema',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (672,'2026_05_07_220000_move_nfe_cert_files_outside_webroot',97);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (673,'2026_05_07_230000_drop_modules_project_legacy_tables',98);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (674,'2026_05_07_140000_update_actor_display_name_maiara',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (675,'2026_05_07_220000_create_fin_extrato_lancamentos_table',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (676,'2026_05_08_000000_add_auto_emission_enabled_to_nfe_business_configs',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (677,'2026_05_09_000001_simplify_baileys_columns_in_whatsapp_business_configs',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (678,'2026_05_09_140000_rename_copiloto_permissions_to_jana',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (679,'2026_05_09_120000_create_whatsapp_business_phones_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (680,'2026_05_09_120100_create_whatsapp_phone_user_access_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (681,'2026_05_09_120200_add_phone_id_to_whatsapp_conversations_and_messages',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (682,'2026_05_09_120300_seed_whatsapp_business_phones_from_configs',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (683,'2026_05_09_120000_create_jana_health_narratives_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (684,'2026_05_10_150000_seed_auditoria_mcp_jira_project',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (685,'2026_05_09_100000_create_nfe_dfe_recebidos_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (686,'2026_05_09_100001_create_nfe_dfe_itens_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (687,'2026_05_09_100002_create_nfe_dfe_eventos_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (688,'2026_05_09_100003_create_nfe_dfe_nsu_state_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (689,'2026_05_09_210000_create_accounts_legacy_map_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (690,'2026_05_09_210001_add_legacy_columns_to_fin_contas_bancarias',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (691,'2026_05_10_000001_create_arquivos_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (692,'2026_05_10_000001_create_mcp_admin_audit_log_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (693,'2026_05_10_000001_create_vestuario_settings_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (694,'2026_05_10_000002_create_arquivos_audit_log_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (695,'2026_05_10_000003_create_arquivos_dedupe_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (696,'2026_05_10_000010_backfill_nfe_xml_arquivos',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (697,'2026_05_10_000020_backfill_consumers_arquivos',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (698,'2026_05_10_000030_add_metadata_recalculated_at_to_arquivos',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (699,'2026_05_10_000040_create_comvis_materiais_table',105);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (700,'2026_05_10_000041_create_comvis_orcamentos_table',105);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (701,'2026_05_10_000042_create_comvis_os_table',105);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (702,'2026_05_10_000043_create_comvis_apontamentos_table',105);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (703,'2026_05_10_120000_alter_nfe_emissoes_status_enum_add_enviando_erro_envio',105);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (704,'2026_05_10_120000_seed_modulos_verticais_mcp_jira_projects',105);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (705,'2026_05_10_160000_add_causer_kind_and_revert_to_activity_log',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (706,'2026_05_11_000001_create_omnichannel_tables',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (707,'2026_05_11_120001_create_sale_processes_table',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (708,'2026_05_11_120002_create_sale_process_stages_table',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (709,'2026_05_11_120003_create_sale_stage_actions_table',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (710,'2026_05_11_120004_create_sale_stage_action_roles_table',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (711,'2026_05_11_120005_create_sale_stage_history_table',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (712,'2026_05_11_130001_create_stock_reservations_table',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (713,'2026_05_11_140001_create_transaction_documents_table',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (714,'2026_05_11_150001_create_nfse_emissoes_table',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (715,'2026_05_11_160001_add_fsm_columns_to_transactions',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (716,'2026_05_11_000010_create_vehicles_table',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (717,'2026_05_11_000020_create_service_orders_table',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (718,'2026_05_11_170001_add_legacy_date_fields_to_transactions',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (719,'2026_05_11_190001_repair_dual_brain_learning_loop_drift',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (720,'2026_05_11_120000_create_conversation_tags_tables',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (721,'2026_05_11_130000_add_is_blocked_to_conversations',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (722,'2026_05_11_200000_add_updated_at_to_whatsapp_conversation_tags',109);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (723,'2026_05_12_000001_add_last_message_denormalized_to_conversations',110);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (724,'2026_05_12_010001_add_is_critical_to_sale_stage_actions',111);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (725,'2026_05_12_020001_alter_transaction_documents_add_boleto_types',112);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (726,'2026_05_12_030001_add_current_stage_id_to_transactions',113);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (727,'2026_05_12_040001_alter_sale_stage_history_action_id_nullable',114);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (728,'2026_05_12_140000_add_is_internal_note_to_messages',114);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (729,'2026_05_12_050001_add_current_stage_id_to_job_sheets',115);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (730,'2026_05_12_060001_add_consent_columns_to_contacts',115);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (731,'2026_05_12_120000_create_nfse_eventos_cancelamento_table',116);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (732,'2026_05_12_150000_add_media_to_messages',116);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (733,'2026_05_12_160000_create_channel_user_access_table',116);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (734,'2026_05_12_170000_create_whatsapp_jana_correcoes_table',117);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (735,'2026_05_12_180000_create_whatsapp_reminders_table',117);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (736,'2026_05_12_190000_create_whatsapp_contact_bot_overrides_table',117);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (737,'2026_05_12_200000_add_media_download_tracking_to_messages',118);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (738,'2026_05_12_000010_create_cv_substratos_table',119);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (739,'2026_05_12_000011_create_cv_acabamentos_table',119);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (740,'2026_05_12_000012_create_cv_instalacoes_catalogo_table',119);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (741,'2026_05_12_000013_create_cv_ordens_producao_table',119);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (742,'2026_05_12_000014_create_cv_instalacoes_table',119);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (743,'2026_05_12_080001_create_product_bom_table',119);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (744,'2026_05_12_180000_add_legacy_origin_to_business',119);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (745,'2026_05_12_210000_create_whatsapp_lid_pn_map_table',119);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (746,'2026_05_12_140001_add_is_grouped_invoice_to_transactions',120);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (747,'2026_05_12_220000_create_sla_policies_table',121);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (748,'2026_05_13_000001_create_macros_table',121);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (749,'2026_05_12_220000_create_whatsapp_conversation_metricas_table',122);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (750,'2026_05_12_220000_create_whatsapp_csat_responses_table',123);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (751,'2026_05_12_200000_create_whatsapp_baileys_auth_state_table',124);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (752,'2026_05_13_100001_create_macro_variants_table',125);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (753,'2026_05_13_100002_add_macro_variant_id_to_messages',125);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (754,'2026_05_12_220001_add_cacamba_fields_to_vehicles',126);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (755,'2026_05_12_220002_add_rental_fields_to_service_orders',126);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (756,'2026_05_12_230001_add_transaction_sell_line_id_to_service_orders',126);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (757,'2026_05_12_230002_add_os_default_per_line_to_business',126);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (758,'2026_05_13_010001_add_current_stage_id_to_service_orders',127);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (759,'2026_05_13_010002_add_contact_id_to_service_orders',128);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (760,'2026_05_13_120000_create_mcp_handoff_summaries_table',129);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (761,'2026_05_13_130000_create_mcp_handoff_diffs_table',129);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (762,'2026_05_13_140000_create_mcp_weekly_digests_table',129);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (763,'2026_05_13_150000_create_mcp_doc_summaries_table',129);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (764,'2026_05_13_170001_add_legacy_id_to_contacts',130);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (765,'2026_05_13_201220_create_feature_flag_audits_table',131);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (766,'2026_05_13_205208_add_index_display_identifier_to_channels',131);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (767,'2026_05_14_010001_create_jobs_table_for_whatsapp_queue',131);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (768,'2026_05_14_020001_create_webhook_nonces_table',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (769,'2026_05_14_010001_add_legacy_id_to_fin_titulos',133);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (770,'2026_05_15_010000_add_identity_columns_to_conversations',134);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (771,'2026_05_15_020000_fix_identity_columns_whatsapp_conversations',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (772,'2026_05_15_120000_add_contextual_context_to_mcp_memory_documents',136);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (773,'2026_05_15_230000_create_customer_memory_table',136);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (774,'2026_05_15_240000_add_employee_complaints_external_to_customer_memory',136);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (775,'2026_05_15_250000_create_employee_performance_table',136);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (776,'2026_05_15_100001_create_kb_categories_table',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (777,'2026_05_15_100002_create_kb_subcategories_table',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (778,'2026_05_15_100003_create_kb_nodes_table',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (779,'2026_05_15_100004_create_kb_edges_table',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (780,'2026_05_15_100005_create_kb_paths_table',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (781,'2026_05_15_100006_create_kb_path_steps_table',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (782,'2026_05_15_100007_create_kb_decision_trees_table',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (783,'2026_05_15_100008_create_kb_decision_tree_steps_table',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (784,'2026_05_15_100009_create_kb_node_versions_table',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (785,'2026_05_15_100010_create_kb_favorites_table',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (786,'2026_05_15_100011_create_kb_comments_table',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (787,'2026_05_15_100012_create_kb_bridge_state_table',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (788,'2026_05_16_120000_recurring_v975_schema',138);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (789,'2026_05_16_220001_create_mcp_scorecard_ai_suggestions_table',138);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (790,'2026_05_17_000001_create_vestuario_devolucoes_table',138);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (791,'2026_05_17_000002_create_vestuario_creditos_cliente_table',138);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (792,'2026_05_17_000010_create_oficina_service_order_items_table',138);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (793,'2026_05_17_120000_create_crm_deals_table',139);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (794,'2026_05_18_180000_add_conferido_to_fin_titulos',140);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (795,'2026_05_19_120000_create_payment_gateway_credentials_table',141);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (796,'2026_05_19_120001_create_cobrancas_table',141);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (797,'2026_05_19_120002_create_gateway_webhook_events_table',141);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (798,'2026_05_19_130000_add_payment_gateway_credential_id_to_fin_contas_bancarias',141);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (799,'2026_05_18_190000_create_fin_titulo_comments_table',142);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (800,'2026_05_19_220000_create_fin_bank_statement_lines_table',143);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (801,'2026_05_19_220001_create_fin_titulo_anexos_table',143);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (802,'2026_05_19_220002_add_aprovacao_to_fin_titulos',143);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (803,'2026_05_20_120000_create_inter_webhook_log_table',144);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (804,'2026_05_20_140000_create_advisors_table',144);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (805,'2026_05_20_140001_create_advisor_business_access_table',144);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (806,'2026_05_20_180000_create_ai_usage_log_table',144);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (807,'2026_05_20_200000_make_titulo_baixa_conta_bancaria_optional',145);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (808,'2026_05_21_140000_restore_br_fields_to_contacts',146);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (809,'2026_05_24_200000_add_role_flags_to_contacts',147);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (810,'2026_05_25_140000_add_source_and_os_ref_to_transactions',148);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (811,'2026_05_25_180000_add_cancelled_at_to_transactions',149);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (812,'2026_05_21_220000_add_caixa_bridge_to_fin_titulos_and_contas',150);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (813,'2026_05_22_000000_extend_contacts_for_cliente_drawer',150);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (814,'2026_05_22_000001_create_anotacoes_table',151);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (815,'2026_05_22_120000_add_numero_to_contacts',151);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (816,'2026_05_22_180000_add_city_code_to_contacts_table',151);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (817,'2026_05_23_120000_add_sefaz_consulta_fields_to_contacts',151);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (818,'2026_05_26_000001_add_ibs_cbs_to_nfe_fiscal_rules',152);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (819,'2026_05_26_120000_expand_payment_gateway_credentials_gateway_key_for_cnab',152);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (820,'2026_05_26_120100_create_cnab_retorno_uploads_table',152);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (821,'2026_05_26_120001_add_box_and_assigned_user_to_service_orders',153);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (822,'2026_05_26_120002_create_oa_inspection_items_table',153);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (823,'2026_05_27_000010_add_client_decision_to_oa_inspection_items',154);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (824,'2026_05_26_140000_add_emails_extras_to_contacts',155);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (825,'2026_05_27_180000_create_clients_feedbacks_table',156);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (826,'2026_05_27_220000_add_dev_task_requested_to_clients_feedbacks',156);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (827,'2026_05_27_240000_add_signature_relevance_to_clients_feedbacks',156);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (828,'2026_05_27_120000_add_sicoob_api_to_payment_gateway_credentials',157);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (829,'2026_05_27_120000_extend_contacts_bucket_a_legacy_absorption',158);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (830,'2026_05_27_140000_contacts_bucket_b_legacy_raw_json',158);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (831,'2026_05_27_160000_contacts_consolidate_officeimpresso_sync_canon',159);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (832,'2026_05_27_140000_drop_mtls_columns_sicoob_reusa_nfecertificado',160);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (833,'2026_05_28_000001_drop_baileys_columns_from_whatsapp_business_configs',161);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (834,'2026_05_28_000002_drop_whatsapp_baileys_auth_state_table',161);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (835,'2026_05_28_000003_add_meta_waba_id_to_whatsapp_business_configs',162);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (836,'2026_05_27_180000_add_contato_to_contacts',163);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (837,'2026_05_28_120000_add_mensagem_venda_to_contacts',164);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (838,'2026_05_29_100001_create_mcp_automations_table',164);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (839,'2026_05_29_100002_create_mcp_automation_runs_table',164);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (840,'2026_05_29_120000_add_mensagem_venda_to_contacts',164);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (841,'2026_05_31_230000_add_conciliacao_cols_to_fin_extrato_lancamentos',164);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (842,'2026_06_01_000000_add_unificacao_cols_to_fin_extrato_lancamentos',165);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (843,'2026_06_01_000001_add_external_id_unique_to_fin_extrato_lancamentos',166);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (844,'2026_06_01_120000_create_contact_addresses_table',167);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (845,'2026_06_02_000001_add_mecanica_to_service_orders_order_type',168);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (846,'2026_06_02_000010_add_checkin_fields_to_service_orders',168);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (847,'2026_06_03_120000_add_forma_pagamento_to_fin_titulos',168);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (848,'2026_06_03_120000_add_is_other_flag_to_contacts',169);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (849,'2026_06_04_120000_add_conta_bancaria_id_to_fin_titulos',169);
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
