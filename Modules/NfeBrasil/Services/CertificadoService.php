<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use App\Util\OtelHelper;
use Closure;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\NfeBrasil\Models\NfeCertificado;
use RuntimeException;

/**
 * US-NFE-041 · CertificadoService.
 *
 * Operações sobre certificados A1 (.pfx) por business — encrypted-at-rest.
 *
 * Garantias:
 *   - .pfx em disco: encrypt via Crypt::encrypt(file_contents)
 *   - Senha em DB: encrypt via Crypt::encryptString
 *   - Senha NUNCA em log/audit
 *   - CNPJ do cert tem que bater com CNPJ do business (segurança)
 *   - apenas 1 cert ativo por business (rotação cega o anterior)
 *
 * Multi-tenant: business_id sempre escopa (skill multi-tenant-patterns).
 */
class CertificadoService
{
    /**
     * @param Closure|null $pkcs12Reader Override do leitor pkcs12 — útil em testes
     *                                   pra evitar precisar de .pfx real.
     *                                   Assinatura: fn(string $content, string $senha): array
     */
    public function __construct(
        private readonly ?Closure $pkcs12Reader = null,
    ) {}

    /**
     * Valida o .pfx + senha + extrai metadados (CN/CNPJ + valido_ate).
     *
     * @param string $pfxBase64 Conteúdo binário do .pfx em base64 (vindo de upload)
     * @param string $senha     Senha do .pfx
     * @return array{cnpj_titular: string, valido_ate: \DateTime, subject_cn: string}
     * @throws InvalidArgumentException Quando pfx inválido / senha errada / cert expirado
     */
    public function validar(string $pfxBase64, string $senha): array
    {
<<<<<<< HEAD
        return OtelHelper::span('nfe.certificado_validar', [
            'pfx_size_bytes' => strlen($pfxBase64),
        ], fn () => $this->validarInterno($pfxBase64, $senha));
    }

=======
        // D9 Wave 26 — span no parse PKCS12 (hot-path SEFAZ — senha/pfx NUNCA em attributes).
        // Defesa Tier 0: attributes carregam APENAS booleans/length, nunca conteudo do .pfx ou senha.
        return OtelHelper::spanBiz('nfe.certificado.validar', function () use ($pfxBase64, $senha): array {
            return $this->validarInterno($pfxBase64, $senha);
        }, ['has_senha' => $senha !== '', 'pfx_len' => strlen($pfxBase64)]);
    }

    /**
     * Implementacao interna de validar — wrap span OTel acima (D9 Wave 26).
     */
>>>>>>> origin/main
    private function validarInterno(string $pfxBase64, string $senha): array
    {
        $binary = base64_decode($pfxBase64, true);
        if ($binary === false || strlen($binary) === 0) {
            throw new InvalidArgumentException('Certificado .pfx em formato base64 inválido.');
        }

        $info = $this->readPkcs12($binary, $senha);

        if (! isset($info['cert'])) {
            throw new InvalidArgumentException('Certificado não contém cert público — arquivo inválido.');
        }

        $parsed = $this->parseCert($info['cert']);
        if (! $parsed) {
            throw new InvalidArgumentException('Falha ao parsear certificado X.509.');
        }

        $cn = (string) ($parsed['subject']['CN'] ?? '');
        $cnpj = $this->extractCnpjFromCN($cn);
        if (! $cnpj) {
            throw new InvalidArgumentException("Certificado sem CNPJ no Subject CN. CN={$cn}");
        }

        $validoAte = (new \DateTimeImmutable())->setTimestamp((int) ($parsed['validTo_time_t'] ?? 0));
        $now = new \DateTimeImmutable();
        if ($validoAte <= $now) {
            throw new InvalidArgumentException(
                "Certificado expirado em {$validoAte->format('Y-m-d')}."
            );
        }

        return [
            'cnpj_titular' => $cnpj,
            'valido_ate'   => $validoAte,
            'subject_cn'   => $cn,
        ];
    }

    /**
     * Persiste o cert para o business — desativa anterior se houver.
     *
     * @param array{cnpj_titular?: string} $businessContext  Opcional: força CNPJ esperado
     *                                                       pra batalhar contra cert pertencendo
     *                                                       a outra empresa.
     */
    public function salvar(
        int $businessId,
        string $pfxBase64,
        string $senha,
        array $businessContext = [],
    ): NfeCertificado {
        $meta = $this->validar($pfxBase64, $senha);

        if (
            isset($businessContext['cnpj_titular'])
            && $this->normalizeCnpj($businessContext['cnpj_titular']) !== $meta['cnpj_titular']
        ) {
            throw new InvalidArgumentException(
                "CNPJ do certificado ({$meta['cnpj_titular']}) não bate com CNPJ do business ({$businessContext['cnpj_titular']})."
            );
        }

        $uuid = (string) Str::uuid();
        $diskPath = sprintf('%d/cert/%s.pfx.enc', $businessId, $uuid);

        $binary = base64_decode($pfxBase64, true);
        Storage::disk('nfe_certs')->put($diskPath, Crypt::encrypt($binary));

        // Desativa anterior — só 1 cert ativo por business
        NfeCertificado::where('business_id', $businessId)
            ->where('ativo', true)
            ->update(['ativo' => false]);

        return NfeCertificado::create([
            'business_id'        => $businessId,
            'uuid'               => $uuid,
            'cnpj_titular'       => $meta['cnpj_titular'],
            'valido_ate'         => $meta['valido_ate']->format('Y-m-d'),
            'encrypted_password' => Crypt::encryptString($senha),
            'ativo'              => true,
        ]);
    }

    /**
     * Carrega cert + senha em memória pra usar com lib SEFAZ.
     *
     * IMPORTANTE: retorno NUNCA persiste em disco em texto. Caller usa o conteúdo
     * direto no construtor da lib (sped-nfe), que mantém em memória.
     *
     * @return array{pfx_binary: string, senha: string, valido_ate: \DateTimeInterface}
     * @throws RuntimeException Quando business sem cert ativo
     */
    public function carregarParaSefaz(int $businessId): array
    {
        $cert = NfeCertificado::where('business_id', $businessId)
            ->where('ativo', true)
            ->first();

        if ($cert) {
            $diskPath = sprintf('%d/cert/%s.pfx.enc', $businessId, $cert->uuid);
            if (! Storage::disk('nfe_certs')->exists($diskPath)) {
                throw new RuntimeException("Arquivo do certificado ausente em disco: {$diskPath}");
            }

            return [
                'pfx_binary' => Crypt::decrypt(Storage::disk('nfe_certs')->get($diskPath)),
                'senha'      => Crypt::decryptString($cert->encrypted_password),
                'valido_ate' => $cert->valido_ate,
                'source'     => 'nfe_brasil',
            ];
        }

        // Fallback ADR 0090 — lê do legado business.certificado durante coexistência
        $legado = $this->lerCertLegado($businessId);
        if ($legado) {
            Log::warning('CertificadoService: FALLBACK_LEGACY usado', [
                'business_id' => $businessId,
                'todo'        => 'Rode `php artisan nfe:migrate-cert-business ' . $businessId . '` pra subir o cert pra nfe_certificados (encrypted)',
            ]);
            return $legado;
        }

        throw new RuntimeException(
            "Business {$businessId} não tem certificado A1 ativo (nem em nfe_certificados nem em business.certificado legado)."
        );
    }

    /**
     * Lê cert do legado `business.certificado` (BLOB) + `business.senha_certificado` (base64).
     * Retorna null se ausente. ADR 0090.
     *
     * @return array{pfx_binary: string, senha: string, valido_ate: ?\DateTimeInterface, source: string}|null
     */
    public function lerCertLegado(int $businessId): ?array
    {
        // Tabela ausente em testes isolados ou ambiente sem UltimatePOS core.
        if (! \Illuminate\Support\Facades\Schema::hasTable('business')) {
            return null;
        }

        $row = DB::table('business')
            ->select(['certificado', 'senha_certificado'])
            ->where('id', $businessId)
            ->first();

        if (! $row || empty($row->certificado) || empty($row->senha_certificado)) {
            return null;
        }

        $senha = base64_decode((string) $row->senha_certificado, true);
        if ($senha === false) {
            $senha = (string) $row->senha_certificado;
        }

        return [
            'pfx_binary' => (string) $row->certificado,
            'senha'      => (string) $senha,
            'valido_ate' => null,
            'source'     => 'business_legado',
        ];
    }

    /**
     * Dias até vencimento do cert ativo, ou null se sem cert.
     * Negativo = vencido.
     */
    public function verificarVencimento(int $businessId): ?int
    {
        $cert = NfeCertificado::where('business_id', $businessId)
            ->where('ativo', true)
            ->first();

        return $cert?->diasAteVencimento();
    }

    // ---------- internos ----------

    private function readPkcs12(string $content, string $senha): array
    {
        if ($this->pkcs12Reader) {
            return ($this->pkcs12Reader)($content, $senha);
        }

        $info = [];
        if (! openssl_pkcs12_read($content, $info, $senha)) {
            $err = openssl_error_string() ?: 'sem mensagem';
            throw new InvalidArgumentException(
                "Falha ao ler .pfx (senha errada ou arquivo corrompido). [{$err}]"
            );
        }
        return $info;
    }

    /**
     * Parseia cert X.509 + retorna subject/validity. Protected pra ser
     * override-able em testes (openssl_x509_parse é função nativa, não
     * mockável diretamente).
     *
     * @return array|false
     */
    protected function parseCert(string $certPem): array|false
    {
        return openssl_x509_parse($certPem);
    }

    private function extractCnpjFromCN(string $cn): ?string
    {
        // CN típico: "EMPRESA ALPHA LTDA:12345678000199"
        if (preg_match('/(\d{14})/', $cn, $m)) {
            return $m[1];
        }
        return null;
    }

    private function normalizeCnpj(string $cnpj): string
    {
        return preg_replace('/\D/', '', $cnpj) ?? '';
    }
}
