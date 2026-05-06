<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rb_boleto_credentials', function (Blueprint $table) {
            $table->unsignedBigInteger('conta_bancaria_id')
                ->nullable()
                ->after('business_id')
                ->comment('FK para fin_contas_bancarias — null quando for gateway puro (Asaas)');

            $table->foreign('conta_bancaria_id', 'rb_boleto_cred_conta_fk')
                ->references('id')
                ->on('fin_contas_bancarias')
                ->nullOnDelete();

            $table->index('conta_bancaria_id', 'rb_boleto_cred_conta_idx');
        });
    }

    public function down(): void
    {
        Schema::table('rb_boleto_credentials', function (Blueprint $table) {
            $table->dropForeign('rb_boleto_cred_conta_fk');
            $table->dropIndex('rb_boleto_cred_conta_idx');
            $table->dropColumn('conta_bancaria_id');
        });
    }
};
