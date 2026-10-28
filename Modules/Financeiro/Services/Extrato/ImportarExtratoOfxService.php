<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services\Extrato;

use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\RecurringBilling\Services\Banking\Drivers\OfxStatementDriver;
use Modules\RecurringBilling\Services\Banking\StatementPersister;

/**
 * Importa um arquivo OFX no extrato de UMA conta bancária — o caminho dos bancos sem
 * API de extrato (medido 2026-10-27: Bradesco, Caixa, BB, C6 e Cora sem credencial).
 *
 * Grava em `fin_extrato_lancamentos` pelo mesmo `StatementPersister` do sync do Inter,
 * então a Conciliação lê a linha sem mudar nada. Atrás da flag
 * `financeiro.extrato_bancos.ofx_por_conta` (FINANCEIRO_EXTRATO_OFX_CONTA, desligada).
 *
 * @see US-FIN-084
 */
class ImportarExtratoOfxService
{
    public function __construct(
        private readonly StatementPersister $persister,
    ) {}

    public static function ligado(): bool
    {
        return (bool) config('financeiro.extrato_bancos.ofx_por_conta', false);
    }

    /**
     * @return array{lidas:int, novas:int, existentes:int, creditos:float, debitos:float}
     */
    public function importar(int $businessId, int $contaBancariaId, string $conteudo, ?string $nomeArquivo = null, bool $dry = false): array
    {
        // Tier 0 (ADR 0093): a conta tem de ser do business — explícito, além do scope,
        // porque o comando roda sem sessão (o scope não se aplica em CLI).
        $conta = ContaBancaria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class) // SUPERADMIN: CLI sem sessão; o where abaixo isola o business
            ->where('business_id', $businessId)
            ->whereKey($contaBancariaId)
            ->firstOrFail();

        $linhas = (new OfxStatementDriver($conteudo))->linhas();

        $resumo = [
            'lidas'    => $linhas->count(),
            'creditos' => round((float) $linhas->where('tipo', 'C')->sum('valor'), 2),
            'debitos'  => round((float) $linhas->where('tipo', 'D')->sum('valor'), 2),
        ];

        $gravou = $dry
            ? ['novas' => 0, 'existentes' => 0]
            : $this->persister->gravar($conta, $linhas, 'ofx', $nomeArquivo ? mb_substr($nomeArquivo, 0, 255) : null);

        return $resumo + $gravou;
    }
}
