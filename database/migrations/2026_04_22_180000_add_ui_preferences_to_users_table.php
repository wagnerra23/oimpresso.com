<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferências de UI por usuário — atualmente só tema (light/dark/null=sistema).
 * Pronto para adicionar mais colunas no futuro (idioma, fuso, atalhos custom).
 *
 * Não usa JSON para facilitar queries/índices e evitar parse em cada request.
 */
class AddUiPreferencesToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'ui_theme')) {
                // null = segue preferência do sistema; 'light' | 'dark' = override explícito
                $table->string('ui_theme', 10)->nullable()->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'ui_sidebar_collapsed')) {
                $table->boolean('ui_sidebar_collapsed')->default(false)->after('ui_theme');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'ui_sidebar_collapsed')) {
                $table->dropColumn('ui_sidebar_collapsed');
            }
            if (Schema::hasColumn('users', 'ui_theme')) {
                $table->dropColumn('ui_theme');
            }
        });
    }
}
