<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fisioterapeuta responsável pelo paciente.
 *
 * Cada paciente tem um usuário da mesma empresa como responsável. O cadastro grava quem
 * cadastrou; a ficha permite transferir para outro profissional da empresa, e cada transferência
 * fica no `activity_log` (quem, de quem, para quem, quando). Nada aqui apaga histórico.
 *
 * Coluna nula: os pacientes que já existem ficam sem responsável até alguém atribuir. Não há
 * como saber, pelo banco, quem cadastrou cada um (a tabela não guardava o autor), então não se
 * inventa um valor.
 *
 * Aditiva e idempotente (`Schema::hasColumn`). Índice composto com `business_id` porque o filtro
 * "meus pacientes" sempre vem junto do escopo da empresa. `down()` remove FK, índice e coluna.
 */
return new class extends Migration
{
    private const INDICE = 'fisio_patients_biz_resp_idx';

    private const FK = 'fisio_patients_resp_user_fk';

    public function up(): void
    {
        if (! Schema::hasTable('fisio_patients') || Schema::hasColumn('fisio_patients', 'responsible_user_id')) {
            return;
        }

        Schema::table('fisio_patients', function (Blueprint $t) {
            $t->unsignedInteger('responsible_user_id')->nullable()->after('contact_id');
            $t->index(['business_id', 'responsible_user_id'], self::INDICE);
            $t->foreign('responsible_user_id', self::FK)->references('id')->on('users');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fisio_patients') || ! Schema::hasColumn('fisio_patients', 'responsible_user_id')) {
            return;
        }

        Schema::table('fisio_patients', function (Blueprint $t) {
            $t->dropForeign(self::FK);
            $t->dropIndex(self::INDICE);
            $t->dropColumn('responsible_user_id');
        });
    }
};
