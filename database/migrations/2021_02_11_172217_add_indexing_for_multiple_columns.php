<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Adicionado para DB::select

return new class extends Migration
{
    /**
     * Helper function to check if an index exists for MySQL.
     *
     * @param string $tableName
     * @param string $indexName
     * @return bool
     */
    protected function indexExists(string $tableName, string $indexName): bool
    {
        // Backtick the table name for safety
        $query = "SHOW INDEXES FROM `" . str_replace('`', '', $tableName) . "` WHERE Key_name = ?";
        $indexes = DB::select($query, [$indexName]);
        return !empty($indexes);
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $tableName = 'accounts';
            if (!$this->indexExists($tableName, 'accounts_business_id_index')) {
                $table->index('business_id');
            }
            if (!$this->indexExists($tableName, 'accounts_account_type_id_index')) {
                $table->index('account_type_id');
            }
            if (!$this->indexExists($tableName, 'accounts_created_by_index')) {
                $table->index('created_by');
            }
        });

        Schema::table('account_transactions', function (Blueprint $table) {
            $tableName = 'account_transactions';
            if (!$this->indexExists($tableName, 'account_transactions_type_index')) {
                $table->index('type');
            }
            if (!$this->indexExists($tableName, 'account_transactions_sub_type_index')) {
                $table->index('sub_type');
            }
        });

        Schema::table('account_types', function (Blueprint $table) {
            $tableName = 'account_types';
            if (!$this->indexExists($tableName, 'account_types_parent_account_type_id_index')) {
                $table->index('parent_account_type_id');
            }
            if (!$this->indexExists($tableName, 'account_types_business_id_index')) {
                $table->index('business_id');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            $tableName = 'bookings';
            if (!$this->indexExists($tableName, 'bookings_correspondent_id_index')) {
                $table->index('correspondent_id');
            }
        });

        Schema::table('business_locations', function (Blueprint $table) {
            $tableName = 'business_locations';
            if (!$this->indexExists($tableName, 'business_locations_sale_invoice_layout_id_index')) {
                $table->index('sale_invoice_layout_id');
            }
            if (!$this->indexExists($tableName, 'business_locations_selling_price_group_id_index')) {
                $table->index('selling_price_group_id');
            }
            if (!$this->indexExists($tableName, 'business_locations_receipt_printer_type_index')) {
                $table->index('receipt_printer_type');
            }
            if (!$this->indexExists($tableName, 'business_locations_printer_id_index')) {
                $table->index('printer_id');
            }
        });

        Schema::table('cash_register_transactions', function (Blueprint $table) {
            $tableName = 'cash_register_transactions';
            if (!$this->indexExists($tableName, 'cash_register_transactions_type_index')) {
                $table->index('type');
            }
            if (!$this->indexExists($tableName, 'cash_register_transactions_transaction_type_index')) {
                $table->index('transaction_type');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            $tableName = 'categories';
            if (!$this->indexExists($tableName, 'categories_parent_id_index')) {
                $table->index('parent_id');
            }
        });

        Schema::table('customer_groups', function (Blueprint $table) {
            $tableName = 'customer_groups';
            if (!$this->indexExists($tableName, 'customer_groups_created_by_index')) {
                $table->index('created_by');
            }
        });

        Schema::table('discount_variations', function (Blueprint $table) {
            $tableName = 'discount_variations';
            if (!$this->indexExists($tableName, 'discount_variations_discount_id_index')) {
                $table->index('discount_id');
            }
            if (!$this->indexExists($tableName, 'discount_variations_variation_id_index')) {
                $table->index('variation_id');
            }
        });

        Schema::table('invoice_schemes', function (Blueprint $table) {
            $tableName = 'invoice_schemes';
            if (!$this->indexExists($tableName, 'invoice_schemes_scheme_type_index')) {
                $table->index('scheme_type');
            }
        });

        Schema::table('media', function (Blueprint $table) {
            $tableName = 'media';
            if (!$this->indexExists($tableName, 'media_business_id_index')) {
                $table->index('business_id');
            }
            if (!$this->indexExists($tableName, 'media_uploaded_by_index')) {
                $table->index('uploaded_by');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $tableName = 'products';
            if (!$this->indexExists($tableName, 'products_type_index')) {
                $table->index('type');
            }
            if (!$this->indexExists($tableName, 'products_tax_type_index')) {
                $table->index('tax_type');
            }
            if (!$this->indexExists($tableName, 'products_barcode_type_index')) {
                $table->index('barcode_type');
            }
        });

        Schema::table('product_racks', function (Blueprint $table) {
            $tableName = 'product_racks';
            if (!$this->indexExists($tableName, 'product_racks_business_id_index')) {
                $table->index('business_id');
            }
            if (!$this->indexExists($tableName, 'product_racks_location_id_index')) {
                $table->index('location_id');
            }
            if (!$this->indexExists($tableName, 'product_racks_product_id_index')) {
                $table->index('product_id');
            }
        });

        Schema::table('reference_counts', function (Blueprint $table) {
            $tableName = 'reference_counts';
            if (!$this->indexExists($tableName, 'reference_counts_business_id_index')) {
                $table->index('business_id');
            }
        });
        Schema::table('stock_adjustment_lines', function (Blueprint $table) {
            $tableName = 'stock_adjustment_lines';
            if (!$this->indexExists($tableName, 'stock_adjustment_lines_lot_no_line_id_index')) {
                $table->index('lot_no_line_id');
            }
        });
        Schema::table('transactions', function (Blueprint $table) {
            $tableName = 'transactions';
            if (!$this->indexExists($tableName, 'transactions_res_table_id_index')) {
                $table->index('res_table_id');
            }
            if (!$this->indexExists($tableName, 'transactions_res_waiter_id_index')) {
                $table->index('res_waiter_id');
            }
            if (!$this->indexExists($tableName, 'transactions_res_order_status_index')) {
                $table->index('res_order_status');
            }
            if (!$this->indexExists($tableName, 'transactions_payment_status_index')) {
                $table->index('payment_status');
            }
            if (!$this->indexExists($tableName, 'transactions_discount_type_index')) {
                $table->index('discount_type');
            }
            if (!$this->indexExists($tableName, 'transactions_commission_agent_index')) {
                $table->index('commission_agent');
            }
            if (!$this->indexExists($tableName, 'transactions_transfer_parent_id_index')) {
                $table->index('transfer_parent_id');
            }
            if (!$this->indexExists($tableName, 'transactions_types_of_service_id_index')) {
                $table->index('types_of_service_id');
            }
            if (!$this->indexExists($tableName, 'transactions_packing_charge_type_index')) {
                $table->index('packing_charge_type');
            }
            if (!$this->indexExists($tableName, 'transactions_recur_parent_id_index')) {
                $table->index('recur_parent_id');
            }
            if (!$this->indexExists($tableName, 'transactions_selling_price_group_id_index')) {
                $table->index('selling_price_group_id');
            }
        });

        Schema::table('transaction_sell_lines', function (Blueprint $table) {
            $tableName = 'transaction_sell_lines';
            if (!$this->indexExists($tableName, 'transaction_sell_lines_line_discount_type_index')) {
                $table->index('line_discount_type');
            }
            if (!$this->indexExists($tableName, 'transaction_sell_lines_discount_id_index')) {
                $table->index('discount_id');
            }
            if (!$this->indexExists($tableName, 'transaction_sell_lines_lot_no_line_id_index')) {
                $table->index('lot_no_line_id');
            }
            if (!$this->indexExists($tableName, 'transaction_sell_lines_sub_unit_id_index')) {
                $table->index('sub_unit_id');
            }
        });

        Schema::table('user_contact_access', function (Blueprint $table) {
            $tableName = 'user_contact_access';
            if (!$this->indexExists($tableName, 'user_contact_access_user_id_index')) {
                $table->index('user_id');
            }
            if (!$this->indexExists($tableName, 'user_contact_access_contact_id_index')) {
                $table->index('contact_id');
            }
        });

        Schema::table('warranties', function (Blueprint $table) {
            $tableName = 'warranties';
            if (!$this->indexExists($tableName, 'warranties_business_id_index')) {
                $table->index('business_id');
            }
            if (!$this->indexExists($tableName, 'warranties_duration_type_index')) {
                $table->index('duration_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // O método down() pode ser preenchido para remover os índices,
        // também com verificações para evitar erros se os índices não existirem.
        // Exemplo:
        // Schema::table('accounts', function (Blueprint $table) {
        //     if ($this->indexExists('accounts', 'accounts_business_id_index')) {
        //         $table->dropIndex('accounts_business_id_index');
        //     }
        //     // ... e assim por diante para outros índices
        // });
    }
};
