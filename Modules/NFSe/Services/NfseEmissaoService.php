<?php

namespace Modules\NFSe\Services;

use Illuminate\Support\Facades\Log;
use Modules\NFSe\Contracts\NfseProviderInterface;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\Exceptions\CertificadoInvalidoException;
use Modules\NFSe\Exceptions\NfseException;
use Modules\NFSe\Exceptions\ProviderTimeoutException;
use Modules\NFSe\Exceptions\RpsDuplicadoException;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Models\NfseProviderConfig;

class NfseEmissaoService
{
    private const MAX_RETRIES = 3;

    public function __construct(private readonly NfseProviderInterface $provider) {}

    /**
     * Emite uma NFSe com idempotência, retry e log de erros.
     *
     * @throws NfseException se a emissão falhar após todas as tentativas
     */
    public function emitir(NfseEmissaoPayload $payload): NfseEmissao
    {
        // Idempotência: retorna nota existente se já foi emitida com mesmo payload
        $existente = NfseEmissao::withoutGlobalScopes()
            ->where('idempotency_key', $payload->idempotencyKey())
            ->where('business_id', $payload->businessId)
            ->whereIn('status', ['emitida', 'processando'])
            ->first();

        if ($existente) {
            return $existente;
        }

        $config = $this->getConfig($payload->businessId);
        $this->validarCertificado($config);

        $emissao = NfseEmissao::create([
            'business_id'     => $payload->businessId,
            'serie'           => $config->serie_default,
            'rps_numero'      => $payload->rpsNumero,
            'competencia'     => $payload->competencia,
            'tomador_cnpj'    => $payload->tomadorCnpj,
            'tomador_cpf'     => $payload->tomadorCpf,
            'tomador_nome'    => $payload->tomadorNome,
            'tomador_email'   => $payload->tomadorEmail,
            'lc116_codigo'    => $payload->lc116Codigo,
            'cnae'            => $config->cnae,
            'descricao'       => $payload->descricao,
            'valor_servicos'  => $payload->valorServicos,
            'aliquota_iss'    => $payload->aliquotaIss,
            'valor_iss'       => $payload->valorIss(),
            'iss_retido'      => $payload->issRetido,
            'status'          => 'processando',
            'idempotency_key' => $payload->idempotencyKey(),
        ]);

        $tentativa = 0;
        $ultimoErro = null;

        while ($tentativa < self::MAX_RETRIES) {
            $tentativa++;

            try {
                $resultado = $this->provider->emitir($payload);

                $emissao->update([
                    'status'                    => 'emitida',
                    'numero'                    => $resultado->numero,
                    'provider_protocolo'        => $resultado->protocolo,
                    'provider_codigo_verificacao' => $resultado->codigoVerificacao,
                    'pdf_url'                   => $resultado->pdfUrl,
                    'xml_retorno'               => $resultado->xmlRetorno,
                    'erro_mensagem'             => null,
                ]);

                Log::channel('nfse')->info('NFSe emitida', [
                    'business_id' => $payload->businessId,
                    'numero'      => $resultado->numero,
                    'valor'       => $payload->valorServicos,
                ]);

                return $emissao->fresh();
            } catch (RpsDuplicadoException $e) {
                // Duplicado = nota já foi aceita; atualiza e retorna sem retry
                $emissao->update(['status' => 'emitida', 'erro_mensagem' => $e->getMessage()]);
                throw $e;
            } catch (CertificadoInvalidoException $e) {
                // Cert inválido = não adianta retry
                $this->marcarErro($emissao, $e);
                throw $e;
            } catch (ProviderTimeoutException $e) {
                $ultimoErro = $e;
                // Backoff exponencial: 1s, 2s, 4s
                sleep(2 ** ($tentativa - 1));
                continue;
            } catch (NfseException $e) {
                $this->marcarErro($emissao, $e);
                throw $e;
            }
        }

        // Esgotou retries de timeout
        $this->marcarErro($emissao, $ultimoErro);
        throw $ultimoErro ?? new ProviderTimeoutException($tentativa);
    }

    public function cancelar(NfseEmissao $emissao, string $motivo): void
    {
        if ($emissao->isCancelada()) {
            throw new \Modules\NFSe\Exceptions\NfseJaCanceladaException($emissao->numero ?? '');
        }

        $this->provider->cancelar($emissao->numero, $motivo);

        $emissao->update(['status' => 'cancelada']);

        Log::channel('nfse')->info('NFSe cancelada', [
            'business_id' => $emissao->business_id,
            'numero'      => $emissao->numero,
            'motivo'      => $motivo,
        ]);
    }

    private function getConfig(int $businessId): NfseProviderConfig
    {
        $config = NfseProviderConfig::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->first();

        if (! $config) {
            throw new NfseException(
                'Configuração NFSe não encontrada. Acesse Configurações → NFSe.',
                'CONFIG_AUSENTE',
            );
        }

        return $config;
    }

    private function validarCertificado(NfseProviderConfig $config): void
    {
        $cert = $config->certificado;

        if (! $cert) {
            throw new CertificadoInvalidoException('Nenhum certificado A1 configurado.');
        }

        if ($cert->isExpirado()) {
            throw new CertificadoInvalidoException(
                "Certificado expirado em {$cert->valido_ate->format('d/m/Y')}."
            );
        }
    }

    private function marcarErro(NfseEmissao $emissao, ?NfseException $e): void
    {
        $emissao->update([
            'status'        => 'erro',
            'erro_mensagem' => $e?->getMessage(),
        ]);

        Log::channel('nfse')->error('NFSe erro', [
            'business_id' => $emissao->business_id,
            'codigo'      => $e?->codigo,
            'mensagem'    => $e?->getMessage(),
        ]);
    }
}
