<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\AppMobileOAuth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cria (uma vez) o client OAuth PÚBLICO do app das lojas e imprime o client_id.
 *
 * Idempotente: se o client já existe, só mostra o id. O client_id não é segredo — vai na
 * variável de repo OAUTH_CLIENT_ID do oimpresso-app (VITE_OAUTH_CLIENT_ID no build).
 *
 *   php artisan app-mobile:oauth-client --dry-run
 *   php artisan app-mobile:oauth-client
 */
class AppMobileOAuthClientCommand extends Command
{
    protected $signature = 'app-mobile:oauth-client {--dry-run : Mostra o que faria, sem gravar}';

    protected $description = 'Cria (idempotente) o client OAuth público do app das lojas e imprime o client_id';

    public function handle(): int
    {
        $existente = DB::table('oauth_clients')
            ->where('name', AppMobileOAuth::CLIENT_NAME)
            ->orderBy('id')
            ->first();

        if ($existente) {
            if ($existente->revoked) {
                $this->error("Client {$existente->id} existe mas está REVOGADO — não reativo sozinho.");

                return self::FAILURE;
            }
            $this->info("Client do app já existe: client_id={$existente->id}");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('[dry-run] Criaria o client público "'.AppMobileOAuth::CLIENT_NAME.'" (password grant, sem secret).');

            return self::SUCCESS;
        }

        // Schema legado de oauth_clients (ver AuthServiceProvider): id INT auto-increment,
        // secret NOT NULL. Secret vazio = client público para o Passport 13
        // (Client::confidential() olha `! empty(secret)`).
        $id = DB::table('oauth_clients')->insertGetId([
            'user_id' => null,
            'name' => AppMobileOAuth::CLIENT_NAME,
            'secret' => '',
            'provider' => 'users',
            'redirect' => '',
            'personal_access_client' => 0,
            'password_client' => 1,
            'revoked' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info("Client do app criado: client_id={$id}");

        return self::SUCCESS;
    }
}
