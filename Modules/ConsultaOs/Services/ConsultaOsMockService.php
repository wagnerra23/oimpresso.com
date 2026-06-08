<?php

declare(strict_types=1);

namespace Modules\ConsultaOs\Services;

use App\Util\OtelHelper;
use Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface;

/**
 * ConsultaOsMockService — Service de consulta publica OS (mock-only).
 *
 * Wave 18 D4 — Extract Service (SoC brutal — Constituicao v2 principio 5):
 * Controller delega busca + filtragem por estagio a este Service. Trocar fonte
 * (mock → query Repair real US-CONSULTA-001) altera somente Service +
 * implementacao do ConsultaOsRepositoryInterface — Controller permanece.
 *
 * Multi-tenant Tier 0 (ADR 0093): rota publica NAO scopa por business_id hoje
 * (cliente externo nao tem sessao). Quando US-CONSULTA-001 ativar query real,
 * Repository deve resolver business_id via lookup do protocolo + rate-limit IP.
 *
 * Observabilidade D9: spans OTel via OtelHelper canonico (App\Util\OtelHelper)
 * com `consultaos.busca_publica` — facilita correlacao com auditoria/throttle.
 *
 * @see Modules\ConsultaOs\Http\Controllers\ConsultaOsController
 * @see memory/requisitos/ConsultaOs/SPEC.md US-CONSULTA-001 (migrar mock → real)
 * @see ADR 0155 module-grade v3 D4 (extract service/repo pattern)
 */
class ConsultaOsMockService
{
    public function __construct(
        private readonly ConsultaOsRepositoryInterface $repository,
    ) {
    }

    /**
     * Busca OS por numero + filtro de estagio.
     *
     * @return array{found: bool, os?: array<string, mixed>, reason?: string}
     */
    public function buscar(string $numero, string $estagio = 'todos'): array
    {
        return OtelHelper::span('consultaos.busca_publica', [
            'estagio' => $estagio,
            // Sem business_id intencionalmente — rota publica (ADR 0093 escape comentado)
        ], function () use ($numero, $estagio) {
            $os = $this->repository->buscarPorNumero($numero);

            if ($os === null) {
                return ['found' => false, 'reason' => 'not_found'];
            }

            if ($estagio !== '' && $estagio !== 'todos' && $os['stage'] !== $estagio) {
                return ['found' => false, 'reason' => 'stage_mismatch'];
            }

            return ['found' => true, 'os' => $os];
        });
    }
}
