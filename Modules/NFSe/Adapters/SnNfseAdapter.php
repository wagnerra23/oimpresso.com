<?php

namespace Modules\NFSe\Adapters;

use Illuminate\Support\Facades\Http;
use Modules\NFSe\Contracts\NfseProviderInterface;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\DTO\NfseResultado;
use Modules\NFSe\Exceptions\CertificadoInvalidoException;
use Modules\NFSe\Exceptions\CodigoServicoInvalidoException;
use Modules\NFSe\Exceptions\IssInvalidoException;
use Modules\NFSe\Exceptions\NfseException;
use Modules\NFSe\Exceptions\PrestadorNaoAutorizadoException;
use Modules\NFSe\Exceptions\ProviderTimeoutException;
use Modules\NFSe\Exceptions\RpsDuplicadoException;
use Modules\NFSe\Exceptions\TomadorInvalidoException;
use Modules\NfeBrasil\Services\Nfse\DpsXml;
use Modules\NfeBrasil\Services\Nfse\ListaServicoNacional;
use NFePHP\Common\Certificate;

/**
 * Adapter para o Sistema Nacional NFS-e (Sefin Nacional, API do Emissor Público Nacional).
 *
 * R-NFSE-005 (2026-10-13): a DPS vai em XML assinado no leiaute dos XSDs oficiais v1.01,
 * comprimida em GZip e codificada em Base64 no campo `dpsXmlGZipB64` do POST /nfse
 * (Manual dos Contribuintes — API do Emissor Público Nacional). Até aqui o adapter
 * mandava JSON, que a Sefin não reconhece. Serialização, assinatura e empacotamento
 * moram em `Modules\NfeBrasil\Services\Nfse\DpsXml` (D-NFSE-CONSOLIDA).
 *
 * A conexão é mTLS com o mesmo certificado A1 que assina a DPS.
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
        return rtrim($ambiente === 'producao'
            ? config('nfse.endpoints.producao', 'https://sefin.nfse.gov.br/SefinNacional')
            : config('nfse.endpoints.homologacao', 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional'), '/');
    }

    public function emitir(NfseEmissaoPayload $payload): NfseResultado
    {
        // Ambiente POR-BUSINESS: endpoint e tpAmb derivam do payload do tenant,
        // não do bind global. Isola o cutover de produção a 1 business.
        $baseUrl = $this->resolveBaseUrl($payload->ambiente);
        $pfx = base64_decode((string) $payload->certPfxBase64, true);
        if ($pfx === false || $pfx === '' || $payload->certSenha === null) {
            throw new CertificadoInvalidoException('Nenhum certificado A1 no payload para assinar a DPS.');
        }

        try {
            $xmlAssinado = DpsXml::assinar(
                DpsXml::serializar($this->buildDps($payload)),
                $pfx,
                $payload->certSenha,
                config('nfse.assinatura_algoritmo') === 'sha1' ? OPENSSL_ALGO_SHA1 : OPENSSL_ALGO_SHA256,
            );
        } catch (\Throwable $e) {
            throw new CertificadoInvalidoException('Não foi possível assinar a DPS: ' . $e->getMessage());
        }

        [$certPem, $keyPem] = $this->pemTemporarios($pfx, $payload->certSenha);
        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->withOptions(['cert' => $certPem, 'ssl_key' => $keyPem])
                ->post("{$baseUrl}/nfse", ['dpsXmlGZipB64' => DpsXml::empacotar($xmlAssinado)]);

            if ($response->serverError() || $response->clientError()) {
                $this->parseErro((array) $response->json());
            }

            $data = (array) $response->json();
            $nfseXml = isset($data['nfseXmlGZipB64']) ? DpsXml::desempacotar((string) $data['nfseXmlGZipB64']) : null;

            return NfseResultado::sucesso(
                numero: $this->numeroNfse($nfseXml),
                protocolo: (string) ($data['chaveAcesso'] ?? ''),
                codigoVerificacao: null,
                pdfUrl: null,
                xmlRetorno: $nfseXml ?? $response->body(),
            );
        } catch (NfseException $e) {
            throw $e;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new ProviderTimeoutException(1, $e);
        } finally {
            @unlink($certPem);
            @unlink($keyPem);
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
     * Estrutura da DPS na ORDEM do XSD v1.01 (TCInfDPS e filhos), pronta para o
     * `DpsXml::serializar`. Pública para os testes de contrato lerem o que vai à Sefin.
     *
     * @return array<string, mixed>
     */
    public function buildDps(NfseEmissaoPayload $payload): array
    {
        $cnpjPrestador = $this->digitos($payload->prestadorCnpj);
        if (strlen($cnpjPrestador) !== 14) {
            throw new NfseException('CNPJ do prestador não configurado na NFS-e.', 'PRESTADOR_SEM_CNPJ');
        }

        $nDps = ltrim($payload->rpsNumero, '0');
        if (! preg_match('/^[1-9][0-9]{0,14}$/', $nDps)) {
            throw new NfseException("Número da DPS inválido: {$payload->rpsNumero}.", 'NDPS_INVALIDO');
        }

        $toma = ['CNPJ' => null, 'CPF' => null];
        $docToma = $this->digitos($payload->tomadorCnpj);
        if (strlen($docToma) === 14) {
            $toma['CNPJ'] = $docToma;
        } elseif (strlen($cpf = $this->digitos($payload->tomadorCpf)) === 11) {
            $toma['CPF'] = $cpf;
        }

        $retido = $payload->retencaoFederalCentavos();

        return [
            'infDPS' => [
                // Manual da API: Id = município emissor (7) + tipo de inscrição (1) +
                // inscrição federal (14) + série (5) + número da DPS (15).
                '@Id'      => 'DPS' . $payload->municipioIbge . '2' . $cnpjPrestador
                    . str_pad($payload->serieDps, 5, '0', STR_PAD_LEFT)
                    . str_pad($nDps, 15, '0', STR_PAD_LEFT),
                // tpAmb POR-BUSINESS (1=produção, 2=homologação) — do payload do tenant.
                'tpAmb'    => $payload->ambiente === 'producao' ? '1' : '2',
                'dhEmi'    => now('America/Sao_Paulo')->format('Y-m-d\TH:i:sP'),
                'verAplic' => 'oimpresso',
                'serie'    => $payload->serieDps,
                'nDPS'     => $nDps,
                'dCompet'  => $payload->competencia->format('Y-m-d'),
                'tpEmit'   => '1', // 1 - Prestador
                'cLocEmi'  => $payload->municipioIbge,
                'prest'    => [
                    'CNPJ'    => $cnpjPrestador,
                    'IM'      => $payload->prestadorIm ?: null,
                    'regTrib' => [
                        'opSimpNac'  => $this->opSimpNac($payload->regimePrestador),
                        'regEspTrib' => '0', // 0 - Nenhum
                    ],
                ],
                // toma é 0-1, mas quando vai exige CNPJ/CPF/NIF/cNaoNIF (xs:choice) — sem
                // documento do tomador o grupo não é enviado.
                'toma' => ($toma['CNPJ'] ?? $toma['CPF']) === null ? null : [
                    ...$toma,
                    'xNome' => $payload->tomadorNome,
                    'email' => $payload->tomadorEmail ?: null,
                ],
                'serv' => [
                    'locPrest' => ['cLocPrestacao' => $payload->municipioIbge],
                    'cServ'    => [
                        'cTribNac'  => $this->cTribNac($payload),
                        'xDescServ' => $payload->descricao,
                    ],
                ],
                'valores' => [
                    'vServPrest' => ['vServ' => $this->dec($payload->valorServicos)],
                    'trib' => [
                        'tribMun' => [
                            'tribISSQN'  => '1', // 1 - Operação tributável
                            'tpRetISSQN' => $payload->issRetido ? '2' : '1', // 2 - Retido pelo tomador
                            // pAliq omitido: no município do Sistema Nacional a alíquota é
                            // parametrizada e "será fornecida pelo sistema" (XSD, TCTribMunicipal).
                        ],
                        // R-NFSE-003 — retenções federais, NT SE/CGNFS-e nº 007: PIS, COFINS e
                        // CSLL retidos vão SOMADOS em vRetCSLL; vPis/vCofins são débito próprio e
                        // não levam retido; tpRetPisCofins 3 = "PIS/COFINS/CSLL Retidos". Sem
                        // retenção o grupo (0-1) não vai. CST 01 = prestador em presumido/real,
                        // único caso com retenção — a confirmar com o contador.
                        'tribFed' => $retido > 0 ? [
                            'piscofins' => ['CST' => '01', 'tpRetPisCofins' => '3'],
                            'vRetCSLL'  => $this->dec($retido / 100),
                        ] : null,
                        // indTotTrib = 0: "Não informar nenhum valor estimado para os Tributos
                        // (Decreto 8.264/2014)" — valor fixo no XSD.
                        'totTrib' => ['indTotTrib' => '0'],
                    ],
                ],
            ],
        ];
    }

    /** `nfe_business_configs.regime` → TSOpSimpNac (1 não optante · 2 MEI · 3 ME/EPP). */
    private function opSimpNac(?string $regime): string
    {
        return match ($regime) {
            'mei'                          => '2',
            'simples'                      => '3',
            'lucro_presumido', 'lucro_real' => '1',
            default => throw new NfseException(
                'Regime tributário da empresa não configurado (Fiscal › Configuração) — a DPS exige opSimpNac.',
                'REGIME_AUSENTE',
            ),
        };
    }

    /**
     * cTribNac pela tabela nacional (R-NFSE-006, Anexo B oficial): item + subitem do
     * LC 116 + desdobro. Subitem com um desdobro resolve sozinho; com vários, vale o
     * escolhido na nota — sem escolha, a emissão falha dizendo as opções. Até o
     * R-NFSE-006 o desdobro era "01" fixo, errado em 62 dos 201 subitens.
     */
    private function cTribNac(NfseEmissaoPayload $payload): string
    {
        try {
            return app(ListaServicoNacional::class)->resolver($payload->lc116Codigo, $payload->cTribNac);
        } catch (\InvalidArgumentException $e) {
            throw new NfseException($e->getMessage(), 'CTRIBNAC');
        }
    }

    private function dec(float $valor): string
    {
        return number_format($valor, 2, '.', '');
    }

    private function digitos(?string $v): string
    {
        return preg_replace('/\D/', '', (string) $v) ?? '';
    }

    private function numeroNfse(?string $nfseXml): string
    {
        if ($nfseXml === null || $nfseXml === '') {
            return '';
        }
        $doc = new \DOMDocument();
        if (! @$doc->loadXML($nfseXml)) {
            return '';
        }

        return (string) ($doc->getElementsByTagName('nNFSe')->item(0)?->textContent ?? '');
    }

    /**
     * Certificado e chave em PEM, em arquivos temporários, para o mTLS do Guzzle (a
     * opção `cert` não lê PKCS#12). Apagados no `finally` do emitir().
     *
     * @return array{0: string, 1: string}
     */
    private function pemTemporarios(string $pfx, string $senha): array
    {
        $certificado = Certificate::readPfx($pfx, $senha);
        $certPem = (string) tempnam(sys_get_temp_dir(), 'nfse_crt_');
        $keyPem = (string) tempnam(sys_get_temp_dir(), 'nfse_key_');
        file_put_contents($certPem, (string) $certificado->publicKey);
        file_put_contents($keyPem, (string) $certificado->privateKey);

        return [$certPem, $keyPem];
    }

    private function parseErro(array $response): void
    {
        // Sefin Nacional devolve `erros: [{Codigo, Descricao, Complemento}]`; o formato
        // antigo (codigo/mensagem soltos) segue aceito.
        $erro = $response['erros'][0] ?? $response['erro'] ?? $response;
        $codigo   = $erro['Codigo'] ?? $erro['codigo'] ?? $erro['code'] ?? '';
        $mensagem = $erro['Descricao'] ?? $erro['descricao'] ?? $erro['mensagem'] ?? $erro['message'] ?? 'Erro desconhecido';

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
}
