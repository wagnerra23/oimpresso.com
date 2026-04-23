<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Triggers MySQL que alimentam `licenca_log` passivamente.
 *
 * Observa INSERTs em oauth_access_tokens e oauth_refresh_tokens e
 * grava login_success/token_refresh sem tocar no request path.
 *
 * Abordagem explicita no plano de log de acesso do Officeimpresso:
 * NAO intercepta /oauth/token (quebraria Delphi legado).
 */
class CreateLicencaLogTriggers extends Migration
{
    public function up()
    {
        // Drop primeiro pra ser idempotente em re-runs
        DB::unprepared('DROP TRIGGER IF EXISTS licenca_log_after_oauth_access_token_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS licenca_log_after_oauth_refresh_token_insert');

        // Trigger: toda vez que um access_token eh criado, grava login_success.
        DB::unprepared("
            CREATE TRIGGER licenca_log_after_oauth_access_token_insert
            AFTER INSERT ON oauth_access_tokens
            FOR EACH ROW
            BEGIN
                INSERT INTO licenca_log (
                    user_id,
                    event,
                    client_id,
                    token_hint,
                    source,
                    created_at
                ) VALUES (
                    NEW.user_id,
                    'login_success',
                    NEW.client_id,
                    CONCAT(SUBSTRING(NEW.id, 1, 8), '…', SUBSTRING(NEW.id, -4)),
                    'trigger_mysql',
                    NOW()
                );
            END
        ");

        // Trigger: refresh token criado = login_refresh.
        DB::unprepared("
            CREATE TRIGGER licenca_log_after_oauth_refresh_token_insert
            AFTER INSERT ON oauth_refresh_tokens
            FOR EACH ROW
            BEGIN
                INSERT INTO licenca_log (
                    event,
                    token_hint,
                    source,
                    created_at
                ) VALUES (
                    'token_refresh',
                    CONCAT(SUBSTRING(NEW.id, 1, 8), '…', SUBSTRING(NEW.id, -4)),
                    'trigger_mysql',
                    NOW()
                );
            END
        ");
    }

    public function down()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS licenca_log_after_oauth_access_token_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS licenca_log_after_oauth_refresh_token_insert');
    }
}
