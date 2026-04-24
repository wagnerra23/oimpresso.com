<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Drop dos triggers MySQL que gravavam login_success/token_refresh.
 *
 * Substituidos por event listener do Passport + middleware de API:
 *   - LogPassportAccessToken (Modules/Officeimpresso/Listeners)
 *   - LogDesktopAccess (Modules/Officeimpresso/Http/Middleware)
 *
 * Motivo: triggers nao tem acesso a IP, user-agent, endpoint, duration
 * — so user_id + token. Listener PHP tem contexto completo e e mais
 * facil de evoluir.
 */
class DropLicencaLogTriggers extends Migration
{
    public function up()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS licenca_log_after_oauth_access_token_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS licenca_log_after_oauth_refresh_token_insert');
    }

    public function down()
    {
        // Recria triggers se precisar reverter (copia da migration original).
        DB::unprepared("
            CREATE TRIGGER licenca_log_after_oauth_access_token_insert
            AFTER INSERT ON oauth_access_tokens
            FOR EACH ROW
            BEGIN
                INSERT INTO licenca_log (user_id, event, client_id, token_hint, source, created_at)
                VALUES (
                    NEW.user_id,
                    'login_success',
                    NEW.client_id,
                    CONCAT(SUBSTRING(NEW.id, 1, 8), '…', SUBSTRING(NEW.id, -4)),
                    'trigger_mysql',
                    NOW()
                );
            END
        ");
        DB::unprepared("
            CREATE TRIGGER licenca_log_after_oauth_refresh_token_insert
            AFTER INSERT ON oauth_refresh_tokens
            FOR EACH ROW
            BEGIN
                INSERT INTO licenca_log (event, token_hint, source, created_at)
                VALUES (
                    'token_refresh',
                    CONCAT(SUBSTRING(NEW.id, 1, 8), '…', SUBSTRING(NEW.id, -4)),
                    'trigger_mysql',
                    NOW()
                );
            END
        ");
    }
}
