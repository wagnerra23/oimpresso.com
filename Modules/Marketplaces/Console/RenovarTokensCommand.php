<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Console;

use Illuminate\Console\Command;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Jobs\RenovarTokenContaJob;

/**
 * `marketplaces:renovar-tokens` — enfileira a renovação das contas ativas cujo token vence na
 * próxima hora (US-MKT-003). Agendado de hora em hora: o access token do Mercado Livre vale ~6 h,
 * e renovar com folga mantém o refresh token em uso. Um job por conta, com a empresa da conta.
 */
class RenovarTokensCommand extends Command
{
    protected $signature = 'marketplaces:renovar-tokens';

    protected $description = 'Renova os tokens das contas de marketplace que vencem na próxima hora.';

    public function handle(): int
    {
        // SUPERADMIN: rotina de plataforma varre todas as empresas; cada job recebe a empresa da conta.
        $contas = ContaVendedor::withoutGlobalScopes()
            ->where('status', ContaVendedor::STATUS_ATIVA)
            // Só o Mercado Livre renova. A Nuvemshop (ADR 0443) não tem vencimento nem refresh token;
            // sem este filtro o `whereNull` abaixo a mandaria para a renovação do ML e marcaria token_expired.
            ->whereHas('marketplace', fn ($q) => $q->where('slug', Marketplace::MERCADO_LIVRE))
            ->where(fn ($q) => $q->whereNull('access_token_expires_at')->orWhere('access_token_expires_at', '<', now()->addHour()))
            ->get(['id', 'business_id']);

        foreach ($contas as $conta) {
            RenovarTokenContaJob::dispatch((int) $conta->business_id, (int) $conta->id);
        }

        $this->info("Renovações enfileiradas: {$contas->count()} conta(s).");

        return self::SUCCESS;
    }
}
