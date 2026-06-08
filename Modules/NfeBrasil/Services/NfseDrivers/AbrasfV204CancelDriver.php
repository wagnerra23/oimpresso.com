<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\NfseDrivers;

use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Contracts\NfseCancelDriverInterface;
use Modules\NfeBrasil\Models\NfseEmissao;
use Modules\NfeBrasil\Models\NfseEventoCancelamento;
use RuntimeException;

/**
 * US-NFSE-CANCEL-002 — Stub Driver ABRASF v2.04.
 *
 * ABRASF é o padrão mais comum (~60% dos municípios BR adotaram alguma versão).
 * v2.04 é a recomendada pela CONFAZ — operação SOAP `CancelarNfse` (lote
 * unitário). Implementação real exige:
 *   - Geração XML CancelarNfseEnvio (RPS chave + número + motivo)
 *   - Assinatura digital A1 (certificado business em nfe_certificados)
 *   - SOAP request pro endpoint municipal (URL varia por município)
 *   - Parse retorno CancelarNfseResposta + persistir protocolo
 *
 * Por ora STUB: declara o padrão, lista vazia de municípios suportados, e
 * `cancelar()` lança RuntimeException informativa. Permite que outras drivers
 * (GINFES, IPM, Tiplan, nfse.gov.br) sejam adicionadas em PRs separadas SEM
 * mexer no framework.
 *
 * Roadmap: popular `supportedMunicipios()` à medida que businesses ROTA LIVRE,
 * ComunicacaoVisual etc forem ativando NFSe nas respectivas prefeituras.
 *
 * @see NfseCancelDriverInterface
 * @see memory/requisitos/NfeBrasil/SPEC-NFSE-CANCEL.md §US-NFSE-CANCEL-002
 */
class AbrasfV204CancelDriver implements NfseCancelDriverInterface
{
    public const KEY = 'ABRASF_V2.04';

    public function getDriverKey(): string
    {
        return self::KEY;
    }

    /**
     * Lista de IBGEs suportados — vazia por ora.
     *
     * Popular em US-NFSE-CANCEL-XXX per-município quando integrar com prefeitura
     * real (cada município ABRASF v2.04 tem endpoint SOAP próprio). Exemplos:
     *   - 4218400 (Termas do Gravatal/SC, ROTA LIVRE biz=4)
     *   - 3550308 (São Paulo/SP)
     *   - 3304557 (Rio de Janeiro/RJ)
     */
    public function supportedMunicipios(): array
    {
        return [];
    }

    /**
     * STUB — lança RuntimeException com instrução pro dev.
     *
     * Quando integração real chegar, implementar:
     *   1. Persistir NfseEventoCancelamento status=pendente (auditoria)
     *   2. Montar XML CancelarNfseEnvio (PedidoCancelamento + InfPedidoCancelamento)
     *   3. Assinar com cert A1 do business (CertificadoService::carregarPorBusiness)
     *   4. SOAP request pro endpoint do município (config nfse.gov.br/sefin lookup)
     *   5. Parse CancelarNfseResposta — sucesso → status=autorizado + protocolo
     *   6. Atualizar NfseEmissao.status=cancelled
     */
    public function cancelar(NfseEmissao $nfse, string $motivo): NfseEventoCancelamento
    {
        Log::warning('AbrasfV204CancelDriver.cancelar: stub — implementação SOAP ABRASF v2.04 pendente', [
            'driver_key'      => self::KEY,
            'nfse_emissao_id' => $nfse->id,
            'business_id'     => $nfse->business_id,
            'municipio'       => $nfse->municipio_codigo_ibge ?? null,
            'todo'            => 'US-NFSE-CANCEL-002: SOAP CancelarNfseEnvio + assinatura A1 + parse resposta',
        ]);

        throw new RuntimeException(
            'Driver ABRASF v2.04 ainda não implementa SOAP CancelarNfseEnvio (US-NFSE-CANCEL-002 pendente). ' .
            'NfseEmissao=' . $nfse->id . ' motivo="' . mb_substr($motivo, 0, 30) . '...". ' .
            'Veja memory/requisitos/NfeBrasil/SPEC-NFSE-CANCEL.md.'
        );
    }
}
