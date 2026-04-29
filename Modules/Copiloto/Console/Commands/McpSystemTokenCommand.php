<?php

namespace Modules\Copiloto\Console\Commands;

use Illuminate\Console\Command;
use Modules\Copiloto\Entities\Mcp\McpToken;

/**
 * MEM-MEM-MCP-1 (ADR 0056) — Gera token MCP "system" pro Copiloto chat.
 *
 * Diferente do token user-bound:
 *   - System token = Wagner em si, mas é assinatura "do Copiloto chat"
 *   - Usado server-side pelo McpMemoriaDriver via .env
 *   - Não tem TTL curto (vive até revogar manualmente)
 *
 * Uso:
 *   php artisan copiloto:mcp:system-token
 *   → Token criado: mcp_xxxxxxxx
 *   → Adicione ao .env do Hostinger:
 *     COPILOTO_MEMORIA_DRIVER=mcp
 *     COPILOTO_MCP_SYSTEM_TOKEN=mcp_xxxxxxxx
 *
 * Permission requerida no user (default: Admin#1 via McpScopesSeeder):
 *   - copiloto.mcp.use
 *   - copiloto.mcp.tasks.read (pra outras tools, se quiser)
 *
 * Se quiser scope reduzido pra system token (só read memória), revoga e gera
 * novo via Team Admin com permission scope=copiloto.mcp.memoria.read (pendente
 * implementar essa permission específica).
 */
class McpSystemTokenCommand extends Command
{
    protected $signature = 'copiloto:mcp:system-token
                            {--user-email=wagnerra@gmail.com : Email do user dono do token}';

    protected $description = 'Gera token MCP "system" pro Copiloto chat (server-side, não-user)';

    public function handle(): int
    {
        $email = (string) $this->option('user-email');
        $user = \App\User::where('email', $email)->first();

        if ($user === null) {
            $this->error("User com email {$email} não encontrado.");
            return self::FAILURE;
        }

        // Gera raw + hash
        $raw = 'mcp_' . bin2hex(random_bytes(32));
        $token = McpToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $raw),
            'note' => "SYSTEM TOKEN Copiloto chat (ADR 0056) — gerado " . now()->toDateString(),
            'expires_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info('Token system gerado:');
        $this->line('');
        $this->line('  Token raw (COPIE AGORA — não será mostrado de novo):');
        $this->warn('  ' . $raw);
        $this->line('');
        $this->line("  Token ID: {$token->id} (user #{$user->id} {$user->email})");
        $this->line('');
        $this->info('Adicione ao .env do Hostinger:');
        $this->line('');
        $this->line('  COPILOTO_MEMORIA_DRIVER=mcp');
        $this->line("  COPILOTO_MCP_SYSTEM_TOKEN={$raw}");
        $this->line('');
        $this->info('Após salvar:');
        $this->line('  php artisan config:clear');
        $this->line('  php artisan optimize');
        $this->line('');

        return self::SUCCESS;
    }
}
