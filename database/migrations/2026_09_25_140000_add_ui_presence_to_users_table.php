<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presença do usuário no menu da sidebar (thread 13 do playbook da sidebar,
 * decisão [W] 2026-09-25). Mesma forma do `ui_theme`: coluna simples em
 * `users`, lida pela prop compartilhada `auth.user` e gravada por
 * `POST /user/preferences/presence`.
 *
 * Valores: disponivel · ocupado · ausente · invisivel. null = disponivel.
 * Ninguém consome a presença ainda (Atendimento/Equipe) — só grava e mostra.
 */
class AddUiPresenceToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'ui_presence')) {
                $table->string('ui_presence', 12)->nullable()->after('ui_sidebar_collapsed');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'ui_presence')) {
                $table->dropColumn('ui_presence');
            }
        });
    }
}
