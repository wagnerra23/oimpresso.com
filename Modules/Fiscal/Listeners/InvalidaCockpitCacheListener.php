<?php

declare(strict_types=1);

namespace Modules\Fiscal\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\NfeBrasil\Events\NFCeAutorizada;
use Modules\NfeBrasil\Events\NFeAutorizada;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeInutilizacao;

/**
 * GAP-FISCAL-002 — Invalida o cache dos KPIs do Cockpit Fiscal quando um dado que eles
 * contam muda.
 *
 * Cache key padrão: `fiscal:cockpit:kpis:biz:{businessId}` (uma por empresa — invalidar a
 * de uma empresa nunca toca a de outra).
 *
 * O QUE DISPARA (D14 da auditoria do Fiscal, 2026-11-05). Até essa data só NFeAutorizada e
 * NFCeAutorizada invalidavam; cancelamento e inutilização mudavam `nfe_emissoes.status` e o
 * painel seguia com o número velho até o TTL de 60s. Esses fluxos NÃO disparam evento de
 * domínio — medido: em `Modules/NfeBrasil` só existem os dois eventos de autorização. O que
 * eles disparam de fato são eventos do Eloquent:
 *
 *  - cancelamento (`NfeService::cancelar` → `$emissao->update(['status' => 'cancelada'])`)
 *    e inutilização de nota avulsa/retransmissão (`$existente->update([...'inutilizada'])`)
 *    → `eloquent.saved: NfeEmissao`;
 *  - inutilização por faixa (`NfeInutilizacaoService`) marca as notas com UPDATE em massa,
 *    que não dispara evento de model; o evento que existe é a criação do registro
 *    → `eloquent.saved: NfeInutilizacao`;
 *  - os dois outros números do cache: DF-e aguardando manifestação (`NfeDfeRecebido`) e
 *    validade do certificado (`NfeCertificado`).
 *
 * NFS-e fica de fora de propósito: os KPIs cacheados (`CockpitController::computeKpis`)
 * contam só `nfe_emissoes`; a NFS-e mora em `nfse_emissoes` e não entra no cache.
 *
 * Listener SÍNCRONO (não ShouldQueue): invalidar custa ~1ms.
 *
 * @see memory/requisitos/Fiscal/AUDIT-SENIOR-2026-05-25.md §GAP-FISCAL-002
 */
class InvalidaCockpitCacheListener
{
    /**
     * Cache key prefix — DEVE bater com CockpitController::kpisCacheKey().
     */
    public const KEY_PREFIX = 'fiscal:cockpit:kpis:biz:';

    /**
     * Models cujas gravações mudam um número do cache. O FiscalServiceProvider registra
     * `eloquent.saved` e `eloquent.deleted` de cada um.
     *
     * @var list<class-string<Model>>
     */
    public const MODELS = [
        NfeEmissao::class,
        NfeInutilizacao::class,
        NfeDfeRecebido::class,
        NfeCertificado::class,
    ];

    public function handle(NFeAutorizada|NFCeAutorizada $event): void
    {
        $this->esquecer((int) ($event->emissao->business_id ?? 0));
    }

    /** `eloquent.saved` / `eloquent.deleted` de um dos MODELS. */
    public function aoGravarModelo(Model $modelo): void
    {
        $this->esquecer((int) ($modelo->getAttribute('business_id') ?? 0));
    }

    private function esquecer(int $businessId): void
    {
        if ($businessId <= 0) {
            return;
        }

        $key = self::KEY_PREFIX . $businessId;
        Cache::forget($key);

        // Cancelamento grava dentro de DB::transaction: uma leitura do painel entre o
        // forget acima e o commit ainda veria o status antigo e o recolocaria no cache.
        // De novo depois do commit fecha essa janela (sem transação, roda na hora).
        DB::afterCommit(static fn () => Cache::forget($key));
    }
}
