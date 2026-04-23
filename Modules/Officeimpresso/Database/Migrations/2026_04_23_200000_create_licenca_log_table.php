<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLicencaLogTable extends Migration
{
    public function up()
    {
        Schema::create('licenca_log', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Relacionamentos (nullable — nem todo evento tem todos os FKs)
            $table->unsignedBigInteger('licenca_id')->nullable();   // licenca_computador.id (bigInt)
            $table->unsignedInteger('business_id')->nullable();     // business.id
            $table->unsignedBigInteger('user_id')->nullable();      // users.id (quem fez a acao, admin ou dono do token)

            // Tipo do evento
            // Valores: login_attempt, login_success, login_error, api_call,
            //          create_licenca, update_licenca, block, unblock, businessupdate
            $table->string('event', 50)->index();

            // OAuth / API context
            $table->string('client_id', 191)->nullable();   // oauth_clients.id
            $table->string('token_hint', 32)->nullable();   // primeiros 8 + ultimos 4 do access_token
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('endpoint', 255)->nullable();
            $table->string('http_method', 10)->nullable();
            $table->smallInteger('http_status')->nullable();
            $table->integer('duration_ms')->nullable();

            // Erro (quando aplicavel)
            $table->string('error_code', 100)->nullable();  // invalid_credentials, revoked, etc
            $table->text('error_message')->nullable();

            // Dados ricos do evento (hd, hostname, serial, exe_versao, etc)
            $table->json('metadata')->nullable();

            // Origem do registro (pra debug futuro)
            // Valores: trigger_mysql, log_parser, admin_action, desktop_audit
            $table->string('source', 30)->default('desktop_audit');

            // Append-only — sem updated_at
            $table->timestamp('created_at')->useCurrent()->index();

            // Indexes compostos pra queries tipicas
            $table->index(['business_id', 'created_at']);
            $table->index(['licenca_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('licenca_log');
    }
}
