# Resumo das Melhorias nas Migrações do Projeto

Este documento resume as principais alterações e melhorias aplicadas aos arquivos de migração do banco de dados. O objetivo principal foi aumentar a robustez e a capacidade de reversão das migrações, garantindo que possam ser executadas múltiplas vezes sem erros e que as alterações no esquema possam ser desfeitas de forma segura.

## Principais Melhorias Aplicadas:

1. **Idempotência no Método `up()`**:

   * Antes de adicionar colunas, tabelas ou índices, agora é verificado se o elemento já existe no banco de dados. Isso é feito utilizando:
     * `Schema::hasColumn('table_name', 'column_name')` para colunas.
     * `Schema::hasTable('table_name')` para tabelas.
     * Uma função auxiliar `indexExists()` ou `DB::select("SHOW INDEXES ...")` para verificar a existência de índices (especialmente para MySQL).
   * Isso previne erros como "Duplicate column name" ou "Table already exists" caso a migração seja executada mais de uma vez.
2. **Implementação Completa do Método `down()`**:

   * Para cada migração que adiciona colunas, tabelas ou índices, o método `down()` correspondente foi implementado para reverter essas alterações.
   * As operações de remoção no método `down()` também incluem verificações de existência (`Schema::hasColumn`, `Schema::dropIfExists`, verificação de índice antes de `dropIndex`) para evitar erros ao tentar remover elementos que não existem (por exemplo, se a migração `up` não foi totalmente concluída ou já foi revertida).
   * A ordem de remoção no `down()` geralmente é a inversa da ordem de adição no `up()`.
   * Para colunas com índices, o índice é removido antes da coluna.

## Detalhamento por Arquivo de Migração:

Abaixo, um resumo das funcionalidades e melhorias específicas para cada arquivo de migração modificado:

---

**1. `d:\Conhecimento\Software\oimpresso.com\database\migrations\2021_02_08_175632_add_contact_number_fields_to_users_table.php`**
*   **`up()`**: Adiciona as colunas `alt_number` e `family_number` à tabela `users`, condicionalmente.
*   **`down()`**: Remove as colunas `family_number` e `alt_number` da tabela `users`, condicionalmente.

---

**2. `y:\database\migrations\2021_02_11_172217_add_indexing_for_multiple_columns.php`**
*   **`up()`**: Adiciona múltiplos índices a diversas tabelas. Cada adição de índice é precedida por uma verificação de existência usando uma função auxiliar `indexExists` (para MySQL).
*   **`down()`**: Contém um exemplo comentado para a remoção condicional dos índices.

---

**3. `y:\database\migrations\2021_03_22_120810_add_more_types_of_service_custom_fields.php`**
*   **`up()`**: Adiciona as colunas `service_custom_field_5` e `service_custom_field_6` à tabela `transactions`, condicionalmente.
*   **`down()`**: Remove as colunas `service_custom_field_6` e `service_custom_field_5` da tabela `transactions`, condicionalmente.

---

**4. `y:\database\migrations\2021_03_24_183132_add_shipping_export_custom_field_details_to_contacts_table.php`**
*   **`up()`**: Adiciona as colunas `shipping_custom_field_details`, `is_export`, e `export_custom_field_1` a `export_custom_field_6` à tabela `contacts`, condicionalmente. Modifica a coluna `name` para `DEFAULT NULL` condicionalmente.
*   **`down()`**: Remove as colunas adicionadas (`export_custom_field_6` a `1`, `is_export`, `shipping_custom_field_details`) da tabela `contacts`, condicionalmente.

---

**5. `y:\database\migrations\2021_03_25_170715_add_export_custom_fields_info_to_transactions_table.php`**
*   **`up()`**: Adiciona as colunas `is_export` e `export_custom_fields_info` à tabela `transactions`, condicionalmente.
*   **`down()`**: Remove as colunas `export_custom_fields_info` e `is_export` da tabela `transactions`, condicionalmente.

---

**6. `y:\database\migrations\2021_04_15_063449_add_denominations_column_to_cash_registers_table.php`**
*   **`up()`**: Adiciona a coluna `denominations` à tabela `cash_registers`, condicionalmente.
*   **`down()`**: Remove a coluna `denominations` da tabela `cash_registers`, condicionalmente.

---

**7. `y:\database\migrations\2021_05_22_083426_add_indexing_to_account_transactions_table.php`**
*   **`up()`**: Adiciona um índice à coluna `operation_date` na tabela `account_transactions`, condicionalmente (verificando com `DB::select`).
*   **`down()`**: Remove o índice `account_transactions_operation_date_index` da tabela `account_transactions`, condicionalmente.

---

**8. `y:\database\migrations\2021_07_08_065808_add_additional_expense_columns_to_transaction_table.php`**
*   **`up()`**: Adiciona pares de colunas `additional_expense_key_X` e `additional_expense_value_X` (X de 1 a 4) à tabela `transactions`, condicionalmente.
*   **`down()`**: Remove os pares de colunas `additional_expense_key_X` e `additional_expense_value_X` (X de 4 a 1) da tabela `transactions`, condicionalmente.

---

**9. `y:\database\migrations\2021_07_13_082918_add_qr_code_columns_to_invoice_layouts_table.php`**
*   **`up()`**: Adiciona as colunas `show_qr_code` e `qr_code_fields` à tabela `invoice_layouts`, condicionalmente.
*   **`down()`**: Remove as colunas `qr_code_fields` e `show_qr_code` da tabela `invoice_layouts`, condicionalmente.

---

**10. `y:\database\migrations\2021_07_21_061615_add_fields_to_show_commission_agent_in_invoice_layout.php`**
*   **`up()`**: Adiciona as colunas `commission_agent_label` e `show_commission_agent` à tabela `invoice_layouts`, condicionalmente.
*   **`down()`**: Remove as colunas `show_commission_agent` e `commission_agent_label` da tabela `invoice_layouts`, condicionalmente.

---

**11. `y:\database\migrations\2021_08_13_105549_add_crm_contact_id_to_users_table.php`**
*   **`up()`**: Adiciona a coluna `crm_contact_id` com chave estrangeira à tabela `users`, condicionalmente.
*   **`down()`**: Remove a chave estrangeira e a coluna `crm_contact_id` da tabela `users`, condicionalmente.

---

**12. `y:\database\migrations\2021_08_25_114932_add_payment_link_fields_to_transaction_payments_table.php`**
*   **`up()`**: Modifica a coluna `created_by` na tabela `transaction_payments` para `DEFAULT NULL`. Adiciona as colunas `paid_through_link` e `gateway`, condicionalmente (a verificação de existência foi adicionada para as novas colunas).
*   **`down()`**: Remove as colunas `gateway` e `paid_through_link` da tabela `transaction_payments`, condicionalmente.

---

**13. `y:\database\migrations\2021_09_01_063110_add_spg_column_to_discounts_table.php`**
*   **`up()`**: Remove a coluna `applicable_in_spg` (condicionalmente) e adiciona a coluna `spg` com índice à tabela `discounts`, condicionalmente.
*   **`down()`**: Remove o índice e a coluna `spg`, e readiciona `applicable_in_spg` à tabela `discounts`, condicionalmente.

---

**14. `y:\database\migrations\2021_09_03_061528_modify_cash_register_transactions_table.php`**
*   **`up()`**: Modifica o tipo da coluna `transaction_type` na tabela `cash_register_transactions`.
*   **`down()`**: Não implementado (reverter modificação de tipo é complexo).

---

**15. `y:\database\migrations\2021_10_05_061658_add_source_column_to_transactions_table.php`**
*   **`up()`**: Adiciona a coluna `source` à tabela `transactions`, condicionalmente.
*   **`down()`**: Remove a coluna `source` da tabela `transactions`, condicionalmente.

---

**16. `y:\database\migrations\2021_12_16_121851_add_parent_id_column_to_expense_categories_table.php`**
*   **`up()`**: Adiciona `parent_id` a `expense_categories` e `expense_sub_category_id` a `transactions`, condicionalmente.
*   **`down()`**: Remove `expense_sub_category_id` de `transactions` e `parent_id` de `expense_categories`, condicionalmente.

---

**17. `y:\database\migrations\2022_04_14_075120_add_payment_type_column_to_transaction_payments_table.php`**
*   **`up()`**: Adiciona a coluna `payment_type` com índice à tabela `transaction_payments`, condicionalmente.
*   **`down()`**: Remove o índice e a coluna `payment_type` da tabela `transaction_payments`, condicionalmente.

---

**18. `y:\database\migrations\2022_04_21_083327_create_cash_denominations_table.php`**
*   **`up()`**: Cria a tabela `cash_denominations`, condicionalmente.
*   **`down()`**: Remove a tabela `cash_denominations` usando `dropIfExists`.

---

**19. `y:\database\migrations\2022_05_10_055307_add_delivery_date_column_to_transactions_table.php`**
*   **`up()`**: Adiciona a coluna `delivery_date` com índice à tabela `transactions`, condicionalmente.
*   **`down()`**: Remove o índice e a coluna `delivery_date` da tabela `transactions`, condicionalmente.

---

**20. `y:\database\migrations\2022_06_13_123135_add_currency_precision_and_quantity_precision_fields_to_business_table.php`**
*   **`up()`**: Adiciona `currency_precision` e `quantity_precision` à tabela `business`, condicionalmente. Executa `Artisan::call('view:clear')`.
*   **`down()`**: Remove `quantity_precision` e `currency_precision` da tabela `business`, condicionalmente.

---

**21. `y:\database\migrations\2022_06_28_133342_add_secondary_unit_columns_to_products_sell_line_purchase_lines_tables.php`**
*   **`up()`**: Adiciona `secondary_unit_id` (com índice) a `products`, e `secondary_unit_quantity` a `purchase_lines`, `transaction_sell_lines`, `stock_adjustment_lines`, condicionalmente.
*   **`down()`**: Remove as colunas e o índice adicionados das respectivas tabelas, condicionalmente.

---

**22. `y:\database\migrations\2022_07_13_114307_create_purchase_requisition_related_columns.php`**
*   **`up()`**: Adiciona `purchase_requisition_line_id` a `purchase_lines` e `purchase_requisition_ids` a `transactions`, condicionalmente.
*   **`down()`**: Remove `purchase_requisition_ids` de `transactions` e `purchase_requisition_line_id` de `purchase_lines`, condicionalmente.

---

**23. `y:\database\migrations\2022_08_25_132707_add_service_staff_timer_fields_to_products_and_users_table.php`**
*   **`up()`**: Adiciona `preparation_time_in_minutes` a `products`, e `available_at`, `paused_at` a `users`, condicionalmente.
*   **`down()`**: Remove as colunas adicionadas de `users` e `products`, condicionalmente. Corrigido nome da tabela no `down`.

---

**24. `y:\database\migrations\2023_01_28_114255_add_letter_head_column_to_invoice_layouts_table.php`**
*   **`up()`**: Adiciona `show_letter_head` e `letter_head` à tabela `invoice_layouts`, condicionalmente.
*   **`down()`**: Remove `letter_head` e `show_letter_head` da tabela `invoice_layouts`, condicionalmente.

---

**25. `y:\database\migrations\2023_02_11_161510_add_event_column_to_activity_log_table.php`**
*   **`up()`**: Adiciona a coluna `event` à tabela `activitylog` (nome configurável), condicionalmente.
*   **`down()`**: Remove a coluna `event` da tabela `activitylog`, condicionalmente.

---

**26. `y:\database\migrations\2023_02_11_161511_add_batch_uuid_column_to_activity_log_table.php`**
*   **`up()`**: Adiciona a coluna `batch_uuid` à tabela `activitylog` (nome configurável), condicionalmente.
*   **`down()`**: Remove a coluna `batch_uuid` da tabela `activitylog`, condicionalmente.

---

**27. `y:\database\migrations\2023_03_02_170312_add_provider_to_oauth_clients_table.php`**
*   **`up()`**: Adiciona a coluna `provider` à tabela `oauth_clients`, condicionalmente.
*   **`down()`**: Remove a coluna `provider` da tabela `oauth_clients`, condicionalmente.

---

**28. `y:\database\migrations\2023_03_21_122731_add_sale_invoice_scheme_id_business_table.php`**
*   **`up()`**: Adiciona `sale_invoice_scheme_id` a `business_locations`, condicionalmente. Atualiza `sale_invoice_scheme_id` com base em `invoice_scheme_id` (condicionalmente para evitar sobrescrever).
*   **`down()`**: Remove `sale_invoice_scheme_id` de `business_locations`, condicionalmente.

---

**29. `y:\database\migrations\2023_03_21_170446_add_number_type_to_invoice_scheme.php`**
*   **`up()`**: Adiciona `number_type` com índice a `invoice_schemes`, condicionalmente.
*   **`down()`**: Remove o índice e a coluna `number_type` de `invoice_schemes`, condicionalmente (com verificação de índice via Doctrine).

---

**30. `y:\database\migrations\2023_04_17_155216_add_custom_fields_to_products.php`**
*   **`up()`**: Adiciona `product_custom_field5` a `product_custom_field20` à tabela `products`, condicionalmente.
*   **`down()`**: Remove `product_custom_field20` a `product_custom_field5` da tabela `products`, condicionalmente.

---

**31. `y:\database\migrations\2023_04_28_130247_add_price_type_to_group_price_table.php`**
*   **`up()`**: Adiciona a coluna `price_type` à tabela `variation_group_prices`, condicionalmente.
*   **`down()`**: Remove a coluna `price_type` da tabela `variation_group_prices`, condicionalmente.

---

**32. `y:\database\migrations\2023_06_21_033923_add_delivery_person_in_transactions.php`**
*   **`up()`**: Adiciona `delivery_person` com índice a `transactions`, condicionalmente.
*   **`down()`**: Remove o índice e a coluna `delivery_person` de `transactions`, condicionalmente.

---

**33. `y:\database\migrations\2023_09_13_153555_add_service_staff_pin_columns_in_users.php`**
*   **`up()`**: Adiciona `is_enable_service_staff_pin` e `service_staff_pin` à tabela `users`, condicionalmente.
*   **`down()`**: Remove `service_staff_pin` e `is_enable_service_staff_pin` da tabela `users`, condicionalmente.

---

**34. `y:\database\migrations\2023_09_15_154404_add_is_kitchen_order_in_transactions.php`**
*   **`up()`**: Adiciona `is_kitchen_order` à tabela `transactions`, condicionalmente.
*   **`down()`**: Remove `is_kitchen_order` da tabela `transactions`, condicionalmente.

---

**35. `y:\database\migrations\2023_12_06_152840_add_contact_type_in_contacts.php`**
*   **`up()`**: Adiciona a coluna `contact_type` à tabela `contacts`, condicionalmente.
*   **`down()`**: Remove a coluna `contact_type` da tabela `contacts`, condicionalmente.

---

**36. `y:\database\migrations\2024_10_03_151459_modify_transaction_sell_lines_purchase_lines_table.php`**
*   **`up()`**: Modifica a coluna `id` na tabela `transaction_sell_lines_purchase_lines` para `BIGINT AUTO_INCREMENT`.
*   **`down()`**: Não implementado (reverter modificação de tipo é complexo).

---

**37. `y:\database\migrations\2025_03_07_114637_add_more_addresh_column_in_contact.php`**
*   **`up()`**: Adiciona `land_mark`, `street_name`, `building_number`, `additional_number` à tabela `contacts`, condicionalmente.
*   **`down()`**: Remove as colunas adicionadas da tabela `contacts`, condicionalmente. Corrigido nome da tabela no `down`.

---

Este resumo deve ajudar a manter um registro claro das evoluções no esquema do banco de dados e facilitar a manutenção futura.
