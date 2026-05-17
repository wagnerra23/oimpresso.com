<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Console\Commands;

use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpToken;
use Modules\TeamMcp\Services\McpTokenIssuer;
use Throwable;

/**
 * RotateTokenCommand — G3 FICHA Wave 22 self-service token rotation CLI.
 *
 * Permite que devs do time MCP (Felipe/Maiara/Eliana/Luiz) rotacionem
 * próprio token MCP sem precisar admin Wagner intervir, mantendo o
 * comportamento de revoke-then-issue atômico (DB transaction).
 *
 * **Tier 0 Segredo IRREVOGÁVEL** (ADR 0081):
 *   - raw token é impresso 1× via $this->warn() (stdout) — NUNCA logado
 *   - mensagens normais via $this->info() não incluem o raw
 *   - opção `--copy-warning` reforça que raw é descartado após copy
 *
 * Uso:
 *   php artisan teammcp:token:rotate --token=ID                # rotaciona token específico
 *   php artisan teammcp:token:rotate --user=ID --note="..."    # rotaciona TODOS tokens do user (force)
 *   php artisan teammcp:token:rotate --user=ID --token=ID --dry-run
 *   php artisan teammcp:token:rotate --user=ID --token=ID --detail
 *
 * Convenções (.claude/rules/commands.md):
 *   - `--detail` em vez de `--verbose` (Symfony reservado)
 *   - PT-BR no output
 *   - Exit 0 sucesso, 1 erro, 2 token não encontrado
 *
 * Multi-tenant: mcp_tokens é cross-tenant (token vincula a user, user pertence a
 * business). RotateTokenCommand respeita ownership via Service.rotate guard.
 *
 * @see Modules\TeamMcp\Services\McpTokenIssuer::rotate
 * @see Modules\Jana\Entities\Mcp\McpToken
 * @see memory/decisions/0081-identity-mesh-actor-trust-mcp.md
 */
final class RotateTokenCommand extends Command
{
    protected $signature = 'teammcp:token:rotate
        {--user= : ID do user dono (obrigatório se sem --token)}
        {--token= : ID do token MCP a rotacionar (se omitido com --user, rotaciona TODOS)}
        {--note= : Nome custom pro novo token (default: copia + " — rotated DATA")}
        {--dry-run : Lista o que seria rotacionado, NÃO executa}
        {--detail : Imprime metadados do token novo (sem raw — Tier 0)}';

    protected $description = 'Rotaciona token(s) MCP self-service: revoke + issue atômico (G3 FICHA W22).';

    public function handle(McpTokenIssuer $issuer): int
    {
        if (! Schema::hasTable('mcp_tokens')) {
            $this->error('Tabela mcp_tokens ausente — rode migrations primeiro.');

            return self::FAILURE;
        }

        $userIdInput = $this->option('user');
        $tokenIdInput = $this->option('token');
        $note = (string) ($this->option('note') ?: '');
        $dryRun = (bool) $this->option('dry-run');
        $detail = (bool) $this->option('detail');

        // Resolve target tokens
        if ($tokenIdInput !== null) {
            return $this->rotateSingle($issuer, (int) $tokenIdInput, $userIdInput !== null ? (int) $userIdInput : null, $note, $dryRun, $detail);
        }

        if ($userIdInput !== null) {
            return $this->rotateAllForUser($issuer, (int) $userIdInput, $note, $dryRun, $detail);
        }

        $this->error('Forneça --token=ID OU --user=ID (sem ambos, comando não sabe o alvo).');

        return self::FAILURE;
    }

    private function rotateSingle(McpTokenIssuer $issuer, int $tokenId, ?int $userIdHint, string $note, bool $dryRun, bool $detail): int
    {
        $token = McpToken::find($tokenId);

        if ($token === null) {
            $this->error("Token #{$tokenId} não encontrado.");

            return 2;
        }

        $userId = $userIdHint ?? (int) $token->user_id;

        // Defesa: se --user passado, deve bater com owner
        if ($userIdHint !== null && (int) $token->user_id !== $userIdHint) {
            $this->error("Token #{$tokenId} pertence ao user_id={$token->user_id}, não bate com --user={$userIdHint}.");

            return self::FAILURE;
        }

        $userLabel = $this->userLabel($userId);

        if ($dryRun) {
            $this->info("[DRY-RUN] Rotacionaria token #{$tokenId} ({$token->name}) do user #{$userId} ({$userLabel}).");

            return self::SUCCESS;
        }

        try {
            $result = $issuer->rotate($userId, $tokenId, $note ?: null);
        } catch (Throwable $e) {
            $this->error('Falha na rotação: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($result === null) {
            $this->error("Rotação rejeitada: token #{$tokenId} não pertence ao user_id={$userId}.");

            return 2;
        }

        $this->info("Token #{$tokenId} rotacionado com sucesso pro user #{$userId} ({$userLabel}).");
        $this->info('Novo token ID: '.$result['new_token']->id);

        if ($detail) {
            $this->line('  Nome:       '.$result['new_token']->name);
            $this->line('  Criado em:  '.$result['new_token']->created_at);
            $this->line('  Expira:     '.($result['new_token']->expires_at ?? 'sem expiração'));
        }

        // Tier 0: raw via WARN (stderr) — fica visível no console mas separado
        // do stdout estruturado. Mensagem reforça descarte pós-copy.
        $this->newLine();
        $this->warn('==== TOKEN RAW (COPIE AGORA — não será mostrado de novo) ====');
        $this->warn($result['raw']);
        $this->warn('============================================================');
        $this->warn('Hash sha256 gravado em mcp_tokens, raw descartado em memória.');

        return self::SUCCESS;
    }

    private function rotateAllForUser(McpTokenIssuer $issuer, int $userId, string $note, bool $dryRun, bool $detail): int
    {
        $active = McpToken::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();

        if ($active->isEmpty()) {
            $this->info("User #{$userId} não tem tokens ativos pra rotacionar.");

            return self::SUCCESS;
        }

        $userLabel = $this->userLabel($userId);
        $this->info("User #{$userId} ({$userLabel}) tem {$active->count()} token(s) ativo(s).");

        if ($dryRun) {
            foreach ($active as $t) {
                $this->line("  [DRY-RUN] #{$t->id} ({$t->name})");
            }

            return self::SUCCESS;
        }

        if (! $this->confirm("Rotacionar TODOS os {$active->count()} tokens do user #{$userId}? (operação destrutiva)", false)) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        $rotated = 0;
        foreach ($active as $t) {
            try {
                $result = $issuer->rotate($userId, (int) $t->id, $note ?: null);
                if ($result !== null) {
                    $rotated++;
                    $this->info("  OK: #{$t->id} → novo #{$result['new_token']->id}");
                    $this->newLine();
                    $this->warn('TOKEN RAW #'.$result['new_token']->id.':');
                    $this->warn($result['raw']);
                }
            } catch (Throwable $e) {
                $this->error("  FALHA #{$t->id}: ".$e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Total rotacionado: {$rotated}/{$active->count()}");

        return $rotated > 0 ? self::SUCCESS : self::FAILURE;
    }

    private function userLabel(int $userId): string
    {
        $u = User::find($userId);
        if ($u === null) {
            return '?';
        }

        return trim((string) ($u->first_name ?? '').' '.(string) ($u->last_name ?? '')) ?: ($u->username ?? "#{$userId}");
    }
}
