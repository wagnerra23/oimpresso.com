<?php

use App\Transaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'sub_status')) {
                $table->string('sub_status')->after('status')->nullable()->index();
            }
        });

        // Atualizar os registros apenas se a coluna existir
        if (Schema::hasColumn('transactions', 'sub_status')) {
            Transaction::where('is_quotation', 1)->update(['sub_status' => 'quotation']);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'sub_status')) {
                $table->dropColumn('sub_status');
            }
        });
    }
};