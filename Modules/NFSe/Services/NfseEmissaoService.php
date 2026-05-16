<?php

namespace Modules\NFSe\Services;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\NFSe\Contracts\NfseProviderInterface;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\Exceptions\CertificadoInvalidoException;
use Modules\NFSe\Exceptions\NfseException;
use Modules\NFSe\Exceptions\ProviderTimeoutException;
use Modules\NFSe\Exceptions\RpsDuplicadoException;
use Modules\NFSe\Jobs\EmitirNfseJob;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Models\NfseProviderConfig;

class NfseEmissaoService
{
    private const MAX_RETRIES = 3;

    /**
     * Contador estático per-request para RPS — formato YmdHis + sufixo 4-dig.
     * Reseta entre requests (request-scoped do PHP-FPM).
     */
    private static int $rpsCounter = 0;

    public function __construct(private readonly NfseProviderInterface $provider) {}

    /**
     * Constrói o payload a partir dos dados validados do form + business_id.
     *
     * Extrai lógica de assembly que vivia no NfseController::store() —
     * separa concern (HTTP) de regra de domínio (montar DTO + carregar cert + gerar RPS).
     *
     * @param array<string,mixed> $data Dados validados do FormRequest
     */
    public function montarPayload(array $data, int $businessId): NfseEmissaoPayload
    {
        $rpsNumero = now()->format('YmdHis') . str_pad(
            (string) ++self::$rpsCounter,
            4,
            '0',
            STR_PAD_LEFT,
        );

        // Carrega cert do DB pro payload — cert PFX vai em base64 + senha decriptada
        // SUPERADMIN: monta payload sem context tenant (chamado por Controller após auth);
        // business_id explícito como param garante scope correto
        $config = NfseProviderConfig::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->with('certificado')
            ->first();

        $certPfxBase64 = $config?->certificado?->pfxDecriptado()
            ? base64_encode($config->certificado->pfxDecriptado())
            : null;
        $certSenha = $config?->certificado?->senhaDecriptada();

        return new NfseEmissaoPayload(
            businessId: $businessId,
            rpsNumero: $rpsNumero,
            competencia: Carbon::createFromFormat('Y-m', $data['competencia']),
            tomadorNome: $data['tomador_nome'],
            tomadorCnpj: $data['tomador_cnpj'] ?? null,
            tomadorCpf: $data['tomador_cpf'] ?? null,
            tomadorEmail: $data['tomador_email'] ?? null,
            descricao: $data['descricao'],
            lc116Codigo: $data['lc116_codigo'],
            valorServicos: (float) $data['valor_servicos'],
            aliquotaIss: (float) $data['aliquota_iss'],
            issRetido: (bool) ($data['iss_retido'] ?? false),
            certPfxBase64: $certPfxBase64,
            certSenha: $certSenha,
            prestadorCnpj: $config?->prestador_cnpj,
            prestadorIm: $config?->prestador_im,
            transactionId: !empty($data['transaction_id']) ? (int) $data['transaction_id'] : null,
        );
    }

    /**
     * Despacha emissão assíncrona — atalho pra Controller chamar 1 linha.
     */
    public function despacharEmissaoAsync(NfseEmissaoPayload $payload): void
    {
        EmitirNfseJob::dispatch($payload)->onQueue('nfse');
    }

    /**
     * Emite uma NFSe com idempotência, retry e log de erros.
     *
     * @throws NfseException se a emissão falhar após todas as tentativas
     */
    public function emitir(NfseEmissaoPayload $payload): NfseEmissao
    {
        // OtelHelper::spanBiz — observability webservice prefeitura (HTTP externo p99 crítico).
        // D9.a Wave 14: span por emissão envolve adapter HTTP + idempotência + retries.
        return OtelHelper::spanBiz('nfse.emissao', function () use ($payload) {
            return $this->emitirInterno($payload);
        }, $payload->businessId);
    }

    private function emitirInterno(NfseEmissaoPayload $payload): NfseEmissao
    {
        // Idempotência: retorna nota existente se já foi emitida com mesmo payload
        // SUPERADMIN: service de emissão NFSe sem context tenant — business_id vem do payload (DTO recebido); idempotência cross-session
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
            'transaction_id'  => $payload->transactionId,
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
        // SUPERADMIN: service chamado por job sem session — business_id explícito como param
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
        // D7.a Wave 14: PiiRedactor em erro_mensagem + log — webservice prefeitura pode
        // ecoar CPF/CNPJ tomador no payload SOAP de erro (LGPD Art. 6º IX — minimização).
        $mensagemSegura = $e?->getMessage()
            ? app(PiiRedactor::class)->redact($e->getMessage())
            : null;

        $emissao->update([
            'status'        => 'erro',
            'erro_mensagem' => $mensagemSegura,
        ]);

        Log::channel('nfse')->error('NFSe erro', [
            'business_id' => $emissao->business_id,
            'codigo'      => $e?->codigo,
            'mensagem'    => $mensagemSegura,
        ]);
    }
}
