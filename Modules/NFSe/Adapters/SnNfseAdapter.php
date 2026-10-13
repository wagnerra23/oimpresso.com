<?php

namespace Modules\NFSe\Adapters;

use Illuminate\Support\Facades\Http;
use Modules\NFSe\Contracts\NfseProviderInterface;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\DTO\NfseResultado;
use Modules\NFSe\Exceptions\CodigoServicoInvalidoException;
use Modules\NFSe\Exceptions\IssInvalidoException;
use Modules\NFSe\Exceptions\NfseException;
use Modules\NFSe\Exceptions\PrestadorNaoAutorizadoException;
use Modules\NFSe\Exceptions\ProviderTimeoutException;
use Modules\NFSe\Exceptions\RpsDuplicadoException;
use Modules\NFSe\Exceptions\TomadorInvalidoException;

/**
 * Adapter para o Sistema Nacional NFSe (webservice sefin.nfse.gov.br).
 *
 * TODO US-NFSE-004: integrar lib nfse-nacional/nfse-php quando composer.json
 * for splitado (ADR 0062 — laravel/mcp impede composer install no Hostinger).
 * Por ora: HTTP direto ao webservice REST do SN-NFSe.
 */
class SnNfseAdapter implements NfseProviderInterface
{
    private string $baseUrl;

    public function __construct(private readonly string $ambiente = 'homologacao')
    {
        // Fallback global usado APENAS por consultar()/cancelar() (sem contexto de
        // payload por-business). A EMISSÃO resolve o ambiente POR-BUSINESS via
        // $payload->ambiente — ver emitir()/resolveBaseUrl().
        $this->baseUrl = $this->resolveBaseUrl($ambiente);
    }

    /**
     * Resolve o endpoint SN-NFSe a partir do ambiente.
     *
     * Cutover fiscal por-business: o ambiente da EMISSÃO vem do payload
     * (nfse_provider_configs.ambiente do tenant), NÃO do bind global
     * config('nfse.ambiente'). Ligar produção pra um business (ex: biz=164)
     * NÃO pode afetar outros tenants (ROTA LIVRE etc).
     */
    private function resolveBaseUrl(string $ambiente): string
    {
        return $ambiente === 'producao'
            ? config('nfse.endpoints.producao', 'https://sefin.nfse.gov.br/sefinnacional')
            : config('nfse.endpoints.homologacao', 'https://sefin.producaorestrita.nfse.gov.br/sefinnacional');
    }

    public function emitir(NfseEmissaoPayload $payload): NfseResultado
    {
        // Ambiente POR-BUSINESS: endpoint e tpAmb derivam do payload do tenant,
        // não do bind global. Isola o cutover de produção a 1 business.
        $baseUrl = $this->resolveBaseUrl($payload->ambiente);
        try {
            $response = Http::timeout(30)
                ->withOptions(['cert' => $this->certPath($payload)])
                ->post("{$baseUrl}/nfse", $this->buildDps($payload));

            if ($response->serverError() || $response->clientError()) {
                $this->parseErro($response->json());
            }

            $data = $response->json();

            return NfseResultado::sucesso(
                numero: $data['nfseId'] ?? $data['numero'] ?? '',
                protocolo: $data['protocolo'] ?? '',
                codigoVerificacao: $data['codigoVerificacao'] ?? null,
                pdfUrl: $data['urlDanfse'] ?? null,
                xmlRetorno: $response->body(),
            );
        } catch (NfseException $e) {
            throw $e;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new ProviderTimeoutException(1, $e);
        }
    }

    public function consultar(string $protocolo): NfseResultado
    {
        $response = Http::timeout(15)
            ->get("{$this->baseUrl}/nfse/{$protocolo}");

        if ($response->failed()) {
            return NfseResultado::erro('Erro ao consultar nota: ' . $response->status());
        }

        $data = $response->json();
        return NfseResultado::sucesso(
            numero: $data['nfseId'] ?? '',
            protocolo: $protocolo,
            pdfUrl: $data['urlDanfse'] ?? null,
        );
    }

    public function cancelar(string $numero, string $motivo): bool
    {
        $response = Http::timeout(15)
            ->delete("{$this->baseUrl}/nfse/{$numero}", ['motivo' => $motivo]);

        return $response->successful();
    }

    /**
     * Público para o teste do contrato da DPS (R-NFSE-003) ler o que vai à prefeitura
     * sem precisar de HTTP nem certificado.
     */
    public function buildDps(NfseEmissaoPayload $payload): array
    {
        $dps = [
            'infDps' => [
                // tpAmb POR-BUSINESS (1=produção, 2=homologação) — do payload do tenant.
                'tpAmb'    => $payload->ambiente === 'producao' ? 1 : 2,
                'serie'    => 'RPS',
                'nDPS'     => $payload->rpsNumero,
                'dhEmi'    => now()->toIso8601String(),
                'dCompet'  => $payload->competencia->format('Y-m-d'),
                'prest'    => array_filter([
                    'CNPJ' => $payload->prestadorCnpj,
                    'IM'   => $payload->prestadorIm,
                ]),
                'toma'     => array_filter([
                    'CNPJ'  => $payload->tomadorCnpj,
                    'CPF'   => $payload->tomadorCpf,
                    'xNome' => $payload->tomadorNome,
                    'email' => $payload->tomadorEmail,
                ]),
                'serv'     => [
                    'xDescServ' => $payload->descricao,
                    'cServ'     => [
                        'cLC116' => $payload->lc116Codigo,
                        'cTribNac' => '010100',
                    ],
                ],
                'valores'  => [
                    'vServPrest' => ['vServ' => $payload->valorServicos],
                    'trib'       => [
                        'tribMun'  => [
                            'tribISSQN' => 1,
                            'pAliq'     => $payload->aliquotaIss * 100,
                        ],
                    ],
                ],
            ],
        ];

        // R-NFSE-003 — retenções federais, leiaute da NT SE/CGNFS-e nº 007 (grupo
        // valores/trib/tribFed): "Se houver valores de retenções de PIS, de COFINS e/ou
        // de CSLL, eles deverão ser SOMADOS e informados no campo 'vRetCSLL' de acordo
        // com o que foi informado no campo 'tpRetPisCofins'" — e 'vPis'/'vCofins' NÃO
        // levam valor retido (são débito próprio). tpRetPisCofins 3 = "PIS/COFINS/CSLL
        // Retidos". Sem retenção (não marcada, Simples, dispensa do art. 31 § 3º) o grupo
        // não vai: ele é 0-1 e o débito próprio não é calculado aqui.
        // CST 01 ("Operação Tributável com Alíquota Básica") é o enquadramento do
        // prestador em lucro presumido/real, único caso em que há retenção — a confirmar
        // com o contador (declarado no _saida da thread).
        $retido = $payload->retencaoFederalCentavos();
        if ($retido > 0) {
            $dps['infDps']['valores']['trib']['tribFed'] = [
                'piscofins' => [
                    'CST'            => '01',
                    'tpRetPisCofins' => 3,
                ],
                'vRetCSLL' => round($retido / 100, 2),
            ];
        }

        return $dps;
    }

    private function parseErro(array $response): void
    {
        $codigo   = $response['codigo'] ?? $response['code'] ?? '';
        $mensagem = $response['mensagem'] ?? $response['message'] ?? 'Erro desconhecido';

        match (true) {
            str_starts_with((string) $codigo, 'E501') => throw new IssInvalidoException($mensagem),
            str_starts_with((string) $codigo, 'E17')  => throw new NfseException($mensagem, (string) $codigo),
            str_contains($mensagem, 'duplicad')       => throw new RpsDuplicadoException(),
            str_contains($mensagem, 'CNPJ') || str_contains($mensagem, 'CPF') => throw new TomadorInvalidoException($mensagem),
            str_contains($mensagem, 'LC116') || str_contains($mensagem, 'serviço') => throw new CodigoServicoInvalidoException($mensagem),
            str_starts_with((string) $codigo, 'L1')   => throw new PrestadorNaoAutorizadoException(),
            default => throw new NfseException($mensagem, (string) $codigo),
        };
    }

    private function certPath(NfseEmissaoPayload $payload): array
    {
        // Grava cert temporário para o cURL (Laravel Http usa Guzzle)
        $tmpPfx = tempnam(sys_get_temp_dir(), 'nfse_cert_');
        file_put_contents($tmpPfx, base64_decode($payload->certPfxBase64 ?? ''));
        return [$tmpPfx, $payload->certSenha ?? ''];
    }
}
