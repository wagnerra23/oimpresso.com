<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_contas_bancarias', function (Blueprint $table) {
            $table->unsignedBigInteger('rb_gateway_credential_id')
                ->nullable()
                ->after('ativo_para_boleto')
                ->comment('FK para rb_boleto_credentials — null quando conta não tem cobrança ativa');

            $table->foreign('rb_gateway_credential_id', 'fin_conta_rb_cred_fk')
                ->references('id')
                ->on('rb_boleto_credentials')
                ->nullOnDelete();

            $table->index('rb_gateway_credential_id', 'fin_conta_rb_cred_idx');
        });
    }

    public function down(): void
    {
        Schema::table('fin_contas_bancarias', function (Blueprint $table) {
            $table->dropForeign('fin_conta_rb_cred_fk');
            $table->dropIndex('fin_conta_rb_cred_idx');
            $table->dropColumn('rb_gateway_credential_id');
        });
    }
};
