<?php

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Modules\Jana\Entities\Mcp\McpToken;

/**
 * MEM-MCP-1.b (ADR 0053) — Gera token MCP pra um usuário.
 *
 * Token raw é exibido UMA VEZ no terminal — depois disso, só hash SHA256
 * fica no DB. Se perder, gerar novo (revogar antigo).
 *
 * Uso:
 *   php artisan mcp:token:gerar --user=1 --name="Wagner laptop"
 *   php artisan mcp:token:gerar --user=2 --name="Felipe desktop" --expires=2027-01-01
 */
class McpTokenGerarCommand extends Command
{
    protected $signature = 'mcp:token:gerar
                            {--user= : ID do usuário (UltimatePOS)}
                            {--name= : Identificador human-readable (ex: "Wagner laptop")}
                            {--expires= : Data de expiração YYYY-MM-DD. Omitido = ciclo semestral (McpToken::VALIDADE_PADRAO_DIAS)}
                            {--sem-validade : Token PERPÉTUO. Só pra integração sem dono humano — o default é expirar}';

    protected $description = 'Gera novo token MCP (validade semestral por padrão — ADR 0057). Raw exibido uma vez — copie agora.';

    public function handle(): int
    {
        $userId = (int) $this->option('user');
        $name = (string) $this->option('name');
        $expires = $this->option('expires');

        if ($userId <= 0 || $name === '') {
            $this->error('Use: php artisan mcp:token:gerar --user=ID --name="Identificador"');
            return self::FAILURE;
        }

        // Valida que user existe (best-effort — DB pode estar offline em smoke)
        try {
            $userExists = \DB::table('users')->where('id', $userId)->exists();
            if (! $userExists) {
                $this->error("User ID $userId não existe na tabela users");
                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->warn('Não conseguiu validar user_id (DB offline?): ' . $e->getMessage());
        }

        $expiresAt = $expires ? \Carbon\Carbon::parse($expires) : null;
        $semValidade = (bool) $this->option('sem-validade');

        if ($semValidade && $expiresAt !== null) {
            $this->error('--sem-validade e --expires são mutuamente exclusivos: escolha um.');
            return self::FAILURE;
        }

        [$token, $raw] = McpToken::gerar($userId, $name, $expiresAt, $semValidade);

        $this->info("Token gerado com sucesso!");
        $this->newLine();
        $this->line("ID interno:  {$token->id}");
        $this->line("Nome:        {$token->name}");
        $this->line("User ID:     {$token->user_id}");
        // Lê do MODEL, não da variável local: quando `--expires` é omitido o valor
        // vem do default do `gerar()` (ciclo semestral, ADR 0057) e `$expiresAt`
        // ainda é null aqui — imprimir a variável diria "nunca" pra um token que
        // expira em 180 dias. Mensagem que mente sobre o próprio efeito é LC-15.
        $this->line("Expira em:   " . ($token->expires_at
            ? $token->expires_at->toDateString() . ' (' . (int) now()->diffInDays($token->expires_at) . ' dias)'
            : 'NUNCA — token perpétuo, revogue manual'));
        $this->newLine();
        $this->warn("=================================================================");
        $this->warn(" RAW TOKEN (copie agora — não será exibido de novo):");
        $this->warn("=================================================================");
        $this->line("");
        $this->line("  $raw");
        $this->line("");
        $this->warn("=================================================================");
        $this->newLine();
        $this->info("Configure no .claude/settings.local.json do dev:");
        $this->line('  {"mcpServers": {"oimpresso": {');
        $this->line('    "url": "https://mcp.oimpresso.com/api/mcp",');
        $this->line('    "headers": {"Authorization": "Bearer ' . $raw . '"}');
        $this->line('  }}}');

        return self::SUCCESS;
    }
}
