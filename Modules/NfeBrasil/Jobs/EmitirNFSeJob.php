<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\NfeBrasil\Models\NfseEmissao;
use Throwable;

/**
 * US-NFE-060 · Emissão NFSe modelo 56 nacional (NT 2024-001) — STUB.
 *
 * Foundation pra emissão NFSe nacional padrão `nfse.gov.br/sefin`. Job
 * paralelo a `EmitirNfceJob` (modelo 65) — mesma forma, escopo distinto.
 *
 * **STATUS DESTA US (foundation):**
 *   - Cria registro `NfseEmissao` em DB (multi-tenant scoped).
 *   - Marca status `sent` simulando envio.
 *   - **NÃO** chama API real `nfse.gov.br/sefin`.
 *   - Pacote PHP (`nfephp-org/sped-nfse` ou `gust-bzz/php-nfse-nacional`)
 *     será adicionado em US futura via ADR (avaliação pendente).
 *
 * Multi-tenant Tier 0 (ADR 0093): `$businessId` SEMPRE passado no
 * constructor — `session()` não funciona em fila assíncrona.
 *
 * Caso prático: OS R$ [redacted Tier 0] = NFe55 R$ [redacted Tier 0] (banner produto) + NFSe56 R$ [redacted Tier 0]
 * (instalação fachada, item LC 116/2003 17.06 publicidade).
 *
 * @see memory/requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md
 * @see Modules\NfeBrasil\Jobs\EmitirNfceJob (pattern paralelo modelo 65)
 */
class EmitirNFSeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    /** ID da emissão criada — preservado pra `failed()` callback marcar `rejected`. */
    private ?int $emissaoId = null;

    public function __construct(
        public readonly int $businessId,
        public readonly int $transactionId,
        public readonly float $valorServico,
        public readonly string $itemLc116,
        public readonly string $tomadorDoc,
        public readonly string $tomadorNome,
    ) {
        $this->onQueue('nfe');
    }

    public function handle(): void
    {
        Log::info('NFSe stub — pacote sped-nfse pendente', [
            'business_id'    => $this->businessId,
            'transaction_id' => $this->transactionId,
            'item_lc116'     => $this->itemLc116,
            'valor_servico'  => $this->valorServico,
            'modelo'         => 56,
        ]);

        // STEP 1 · cria registro multi-tenant (scope global garante biz match)
        // SUPERADMIN: job em fila sem session; business_id => $this->businessId explícito (constructor).
        $emissao = NfseEmissao::withoutGlobalScope(ScopeByBusiness::class)->create([
            'business_id'    => $this->businessId,
            'transaction_id' => $this->transactionId,
            'item_lc116'     => $this->itemLc116,
            'value_servico'  => $this->valorServico,
            'tomador_doc'    => $this->tomadorDoc,
            'tomador_nome'   => $this->tomadorNome,
            'status'         => NfseEmissao::STATUS_PENDING,
        ]);

        $this->emissaoId = $emissao->id;

        // STEP 2 · STUB do envio — em US futura aqui chamará API
        // `nfse.gov.br/sefin` via pacote sped-nfse + assinatura A1.
        $emissao->status = NfseEmissao::STATUS_SENT;
        $emissao->save();

        Log::info('NFSe stub marcada como sent (sem chamada SEFIN real)', [
            'emissao_id' => $emissao->id,
            'status'     => $emissao->status,
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('NFSe emission failed após retries', [
            'business_id'    => $this->businessId,
            'transaction_id' => $this->transactionId,
            'error'          => $e->getMessage(),
        ]);

        if ($this->emissaoId !== null) {
            // SUPERADMIN: callback failed() em fila sem session; atualiza emissão criada por este próprio job.
            NfseEmissao::withoutGlobalScope(ScopeByBusiness::class)
                ->where('id', $this->emissaoId)
                ->update([
                    'status'    => NfseEmissao::STATUS_REJECTED,
                    'error_msg' => $e->getMessage(),
                ]);
        }
    }
}
