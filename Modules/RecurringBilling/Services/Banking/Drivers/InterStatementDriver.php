<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services\Banking\Drivers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\RecurringBilling\Contracts\BankStatementDriverContract;
use Modules\RecurringBilling\Dto\StatementLineDto;
use Modules\RecurringBilling\Services\Banking\InterBankingClient;

/**
 * Driver de extrato Banco Inter — adapta `InterBankingClient::getExtrato()`
 * pro formato genérico `StatementLineDto[]`.
 *
 * Inter v2 retorna shape variável por `tipoDetalhe` (PIX/BOLETO/TED/etc).
 * Parse defensivo: campos comuns no DTO, payload completo em `raw` pra
 * análise futura (conciliação, dispute resolution).
 *
 * @see US-RB-046
 */
class InterStatementDriver implements BankStatementDriverContract
{
    public function __construct(
        private readonly InterBankingClient $client,
    ) {}

    public function fetchStatement(Carbon $from, Carbon $to): Collection
    {
        $transacoes = $this->client->getExtrato($from, $to);

        return collect($transacoes)->map(fn (array $t) => $this->parse($t));
    }

    private function parse(array $t): StatementLineDto
    {
        $detalhes = $t['detalhes'] ?? [];

        return new StatementLineDto(
            data: Carbon::parse($t['dataInclusao'] ?? $t['dataTransacao'] ?? now()),
            valor: (float) ($t['valor'] ?? 0),
            tipo: ($t['tipoOperacao'] ?? 'C') === 'C' ? 'C' : 'D',
            descricao: trim(
                ($t['titulo'] ?? '').' - '.($t['descricao'] ?? ''),
                ' -'
            ),
            contraparteDocumento: $detalhes['cpfCnpjPagador']
                ?? $detalhes['cpfCnpjFavorecido']
                ?? null,
            contraparteNome: $detalhes['nomePagador']
                ?? $detalhes['nomeFavorecido']
                ?? null,
            idempotencyKey: (string) (
                $t['idTransacao']
                ?? $detalhes['endToEndId']
                ?? sha1((string) json_encode($t))
            ),
            raw: $t,
        );
    }
}
