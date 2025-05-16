<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFieldsToBusinessTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('business', function (Blueprint $table) {
            $table->string('versao_obrigatoria')->nullable()->after('id'); // ou após um campo específico existente
            $table->string('versao_disponivel')->nullable()->after('versao_obrigatoria');
            $table->string('caminho_banco_servidor')->nullable()->after('versao_disponivel');
            $table->timestamp('dt_ultimo_acesso')->nullable()->after('caminho_banco_servidor');

            // Adiciona os novos campos booleanos
            $table->boolean('is_officeimpresso')->default(false)->after('dt_ultimo_acesso');
            $table->boolean('officeimpresso_bloqueado')->default(false)->after('is_officeimpresso');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('business', function (Blueprint $table) {
            $table->dropColumn('versao_obrigatoria');
            $table->dropColumn('versao_disponivel');
            $table->dropColumn('caminho_banco_servidor');
            $table->dropColumn('dt_ultimo_acesso');
            $table->dropColumn('is_officeimpresso');
            $table->dropColumn('officeimpresso_bloqueado');
        });
    }
}
