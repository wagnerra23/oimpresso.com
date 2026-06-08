<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona suporte a login social (Google + Microsoft) na tabela users.
 *
 * Não renomeia/remove nada do schema UltimatePOS — só anexa colunas nullable.
 * Ver SocialAuthController e ADR PR3 (claude/cms-pr3-auth-social).
 */
class AddSocialAuthColumnsToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'google_id')) {
                $table->string('google_id', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'microsoft_id')) {
                $table->string('microsoft_id', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'avatar_url')) {
                $table->string('avatar_url', 500)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'avatar_url')) {
                $table->dropColumn('avatar_url');
            }
            if (Schema::hasColumn('users', 'microsoft_id')) {
                $table->dropColumn('microsoft_id');
            }
            if (Schema::hasColumn('users', 'google_id')) {
                $table->dropColumn('google_id');
            }
        });
    }
}
