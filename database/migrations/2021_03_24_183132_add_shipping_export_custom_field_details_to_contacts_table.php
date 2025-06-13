<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('contacts', 'shipping_custom_field_details')) {
                $table->longText('shipping_custom_field_details')
                    ->nullable()
                    ->after('shipping_address');
            }
            if (!Schema::hasColumn('contacts', 'is_export')) {
                $table->boolean('is_export')
                    ->default(false)
                    ->after('shipping_custom_field_details');
            }
            if (!Schema::hasColumn('contacts', 'export_custom_field_1')) {
                $table->string('export_custom_field_1')
                    ->nullable()
                    ->after('is_export');
            }
            if (!Schema::hasColumn('contacts', 'export_custom_field_2')) {
                $table->string('export_custom_field_2')
                    ->nullable()
                    ->after('export_custom_field_1');
            }
            if (!Schema::hasColumn('contacts', 'export_custom_field_3')) {
                $table->string('export_custom_field_3')
                    ->nullable()
                    ->after('export_custom_field_2');
            }
            if (!Schema::hasColumn('contacts', 'export_custom_field_4')) {
                $table->string('export_custom_field_4')
                    ->nullable()
                    ->after('export_custom_field_3');
            }
            if (!Schema::hasColumn('contacts', 'export_custom_field_5')) {
                $table->string('export_custom_field_5')
                    ->nullable()
                    ->after('export_custom_field_4');
            }
            if (!Schema::hasColumn('contacts', 'export_custom_field_6')) {
                $table->string('export_custom_field_6')
                    ->nullable()
                    ->after('export_custom_field_5');
            }
        });

        // Verificar se a alteração na coluna 'name' é necessária antes de executá-la
        $column = DB::select("SHOW COLUMNS FROM contacts WHERE Field = 'name'");
        if ($column && $column[0]->Default !== 'NULL') {
            DB::statement('ALTER TABLE contacts MODIFY COLUMN name VARCHAR(191) DEFAULT NULL');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
