<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\SupportAgent;
use App\User;
use Illuminate\Console\Command;

/**
 * Modo Suporte (ADR 0305/0308) — concede/revoga a capability "suporte" por conta (RF4).
 *
 * CLI interino até a tela de concessão existir. NÃO concede acesso a uma empresa específica:
 * concede a capability GLOBAL "suporte". QUEM o agente pode acessar é resolvido pelo
 * SupportAccessService (todas as empresas-cliente EXCETO a operadora biz=1). O agente NÃO
 * vira superadmin — é ortogonal.
 *
 * Uso:
 *   php artisan suporte:conceder 42                      # concede ao user id 42
 *   php artisan suporte:conceder fulano                  # concede ao username 'fulano'
 *   php artisan suporte:conceder 42 --revogar            # revoga a concessão ativa
 *   php artisan suporte:conceder 42 --motivo="piloto"    # com motivo registrado
 *
 * @see app/Services/Support/SupportAccessService.php (isSupportAgent)
 * @see memory/decisions/0305-modo-suporte-cross-tenant-exceto-operador.md
 */
class SuporteConcederCommand extends Command
{
    protected $signature = 'suporte:conceder
        {user : id numérico OU username do usuário}
        {--revogar : Revoga a concessão ativa em vez de conceder}
        {--motivo= : Motivo registrado na concessão}';

    protected $description = 'Concede/revoga a capability "suporte" (Modo Suporte) por conta. CLI interino até a tela de concessão (RF4).';

    public function handle(): int
    {
        $arg = (string) $this->argument('user');

        $user = ctype_digit($arg)
            ? User::find((int) $arg)
            : User::where('username', $arg)->first();

        if ($user === null) {
            $this->error("Usuário não encontrado: {$arg} (use id numérico ou username exato).");

            return self::FAILURE;
        }

        if ($this->option('revogar')) {
            $afetadas = SupportAgent::query()
                ->where('user_id', $user->id)
                ->active()
                ->update(['revoked_at' => now()]);

            if ($afetadas > 0) {
                $this->info("Capability de suporte REVOGADA de {$user->username} (#{$user->id}).");
            } else {
                $this->warn("{$user->username} (#{$user->id}) não tinha concessão ativa — nada a revogar.");
            }

            return self::SUCCESS;
        }

        $motivo = $this->option('motivo');
        $reason = is_string($motivo) && $motivo !== '' ? $motivo : 'concedido via suporte:conceder (CLI)';

        SupportAgent::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['revoked_at' => null, 'granted_at' => now(), 'reason' => $reason],
        );

        $this->info("Capability de suporte CONCEDIDA a {$user->username} (#{$user->id}).");
        $this->line('  Alcance: todas as empresas-cliente EXCETO a operadora (biz=1). NÃO é superadmin.');

        return self::SUCCESS;
    }
}
