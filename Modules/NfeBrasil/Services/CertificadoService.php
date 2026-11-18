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
        $meta = $this->validarComBinario($pfxBase64, $senha);
        unset($meta['pfx_binario']);

        return $meta;
    }

    /**
     * validar() + o binário do .pfx que deve ir pro disco: o original, ou o
     * convertido pro formato atual quando o arquivo veio no formato antigo
     * (RC2-40). Gravar o convertido é o que deixa a emissão (NFePHP readPfx)
     * ler o certificado depois.
     */
    private function validarComBinario(string $pfxBase64, string $senha): array
    {
        // D9 Wave 26 — span no parse PKCS12 (hot-path SEFAZ — senha/pfx NUNCA em attributes).
        // Defesa Tier 0: attributes carregam APENAS booleans/length, nunca conteudo do .pfx ou senha.
        return OtelHelper::spanBiz('nfe.certificado.validar', function () use ($pfxBase64, $senha): array {
            return $this->validarInterno($pfxBase64, $senha);
        }, ['has_senha' => $senha !== '', 'pfx_len' => strlen($pfxBase64)]);
    }

    /**
     * Implementacao interna de validar — wrap span OTel acima (D9 Wave 26).
     */
    private function validarInterno(string $pfxBase64, string $senha): array
    {
        $binary = base64_decode($pfxBase64, true);
        if ($binary === false || strlen($binary) === 0) {
            throw new InvalidArgumentException('Certificado .pfx em formato base64 inválido.');
        }

        [$info, $binary] = $this->readPkcs12($binary, $senha);

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
            'pfx_binario'  => $binary,
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
        $meta = $this->validarComBinario($pfxBase64, $senha);

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

        Storage::disk('nfe_certs')->put($diskPath, Crypt::encrypt($meta['pfx_binario']));

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
            return $this->carregarCertificado($cert);
        }

        // ADR 0449 (Fase 4 da ADR 0090, lote 1.5 · N5-08): o fallback para `business.certificado`
        // (pfx em claro, senha em base64) saiu. Medido em produção em 2026-11-10: 40 empresas com
        // cert legado, nenhum utilizável (39 ilegíveis pelo NFePHP, 1 vencido) e nenhum uso no log.
        // Quem tem só o legado envia o A1 de novo na tela do certificado.
        throw new RuntimeException(
            "Business {$businessId} não tem certificado A1 ativo. Envie o certificado em /nfe-brasil/configuracao/certificado."
        );
    }

    /**
     * Decripta UM certificado já resolvido: o .pfx do disco (`{biz}/cert/{uuid}.pfx.enc`)
     * e a senha de `encrypted_password`. É a mesma leitura do `carregarParaSefaz`, exposta
     * para quem já tem o certificado em mãos — a NFS-e usa o `cert_id` da sua config, que
     * não é necessariamente o ativo do business. O caminho no disco usa o `business_id`
     * DO PRÓPRIO certificado (ADR 0093).
     *
     * Substitui `pfxDecriptado()`/`senhaDecriptada()`, removidos do modelo do NFSe na
     * unificação do schema (4e772bcb46, 2026-05-07) sem que o chamador fosse migrado.
     *
     * @return array{pfx_binary: string, senha: string, valido_ate: \DateTimeInterface, source: string}
     * @throws RuntimeException Quando o arquivo do certificado não está no disco
     */
    public function carregarCertificado(NfeCertificado $cert): array
    {
        $diskPath = sprintf('%d/cert/%s.pfx.enc', $cert->business_id, $cert->uuid);
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

    /**
     * Carrega cert pra SEFAZ com chain de 3 camadas (ADR 0186).
     *
     * Estende `carregarParaSefaz` adicionando 3ª camada de fallback:
     * cert institucional do oimpresso operacional (config `fiscal.fallback_business_id`,
     * default biz=1) quando o business consumidor não tem cert próprio ativo nem
     * legado. Usado pelo `SefazConsultaCadastroService` no drawer 760 Cliente
     * (lookup CNPJ → IE automática via SEFAZ ConsultaCadastro).
     *
     * Chain:
     *   1. cert primário business (`nfe_certificados`)
     *   2. (removida pela ADR 0449: cert legado `business.certificado`)
     *   3. cert institucional (config fiscal.fallback_business_id)
     *   4. RuntimeException — caller renderiza badge UI "configure cert"
     *
     * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): camada #3 usa
     * `withoutGlobalScope(ScopeByBusiness::class)` INTENCIONAL — única query
     * em `nfe_certificados` autorizada a escapar do scope. Cada uso loga em
     * audit log com `sha256(cnpj_consultado)` (LGPD Art. 6º III — minimização).
     *
     * @return array{pfx_binary: string, senha: string, valido_ate: ?\DateTimeInterface, source: string, cert_business_id: int}
     * @throws RuntimeException Quando nenhuma das 3 camadas retorna cert
     */
    public function carregarParaSefazComFallback(int $businessId, ?string $contextoConsulta = null): array
    {
        // Camada 1: cert primário (a camada 2, legado, saiu pela ADR 0449).
        try {
            $cert = $this->carregarParaSefaz($businessId);
            $cert['cert_business_id'] = $businessId;
            return $cert;
        } catch (RuntimeException $e) {
            // Cai pra camada 3 — fallback institucional.
        }

        // Camada 3: cert institucional do oimpresso operacional.
        $fallbackBusinessId = (int) config('fiscal.fallback_business_id', 1);

        if ($fallbackBusinessId === $businessId) {
            // Próprio business já é o institucional E não tem cert → não há fallback adicional.
            throw new RuntimeException(
                "Business institucional {$businessId} não tem certificado A1 ativo. Configure em /fiscal/config."
            );
        }

        // ⚠️ withoutGlobalScope INTENCIONAL — ADR 0186 §Decisão camada #3.
        // Único lugar autorizado no codebase a escapar do tenant scope em nfe_certificados.
        // Pest test `CertificadoFallbackInstitucionalTest` valida que não há outras
        // ocorrências de `withoutGlobalScope(ScopeByBusiness::class)` apontando pra esse model.
        // SUPERADMIN: cross-tenant DELIBERADO — lê cert institucional (biz=$fallbackBusinessId) pra
        // tenants sem cert próprio; filtra por business_id explícito + audita em mcp_audit_log.
        $certInstitucional = NfeCertificado::withoutGlobalScope(\App\Scopes\ScopeByBusiness::class)
            ->where('business_id', $fallbackBusinessId)
            ->where('ativo', true)
            ->where('valido_ate', '>', now())
            ->first();

        if (! $certInstitucional) {
            throw new RuntimeException(
                "Business {$businessId} sem cert ativo E cert institucional fallback (biz={$fallbackBusinessId}) também ausente/vencido."
            );
        }

        $diskPath = sprintf('%d/cert/%s.pfx.enc', $fallbackBusinessId, $certInstitucional->uuid);
        if (! Storage::disk('nfe_certs')->exists($diskPath)) {
            throw new RuntimeException(
                "Cert institucional registrado mas arquivo ausente em disco: {$diskPath}"
            );
        }

        // Audit log — ADR 0186 §Decisão camada #3. LGPD Art. 6º III: sha256(cnpj), nunca plain.
        // `mcp_audit_log` table existe pelo schema MCP (ADR 0053). Graceful skip se não disponível.
        try {
            DB::table('mcp_audit_log')->insert([
                'business_id' => $businessId,
                'event' => 'sefaz.cert.fallback_institutional_used',
                'metadata' => json_encode([
                    'cert_business_id' => $fallbackBusinessId,
                    'reason' => 'business_sem_cert_ativo_nem_legado',
                    'contexto_consulta_hash' => $contextoConsulta !== null
                        ? hash('sha256', $contextoConsulta)
                        : null,
                ]),
                'created_at' => now(),
            ]);
        } catch (\Throwable $auditErr) {
            // Audit log graceful — não bloqueia consulta se MCP audit falhar.
            Log::warning('CertificadoService: audit log fallback institucional falhou', [
                'business_id' => $businessId,
                'cert_business_id' => $fallbackBusinessId,
                'audit_error' => $auditErr->getMessage(),
            ]);
        }

        Log::info('CertificadoService: FALLBACK_INSTITUCIONAL usado', [
            'business_id' => $businessId,
            'cert_business_id' => $fallbackBusinessId,
        ]);

        return [
            'pfx_binary' => Crypt::decrypt(Storage::disk('nfe_certs')->get($diskPath)),
            'senha'      => Crypt::decryptString($certInstitucional->encrypted_password),
            'valido_ate' => $certInstitucional->valido_ate,
            'source'     => 'institutional_fallback',
            'cert_business_id' => $fallbackBusinessId,
        ];
    }

    /**
     * Lê cert do legado `business.certificado` (BLOB) + `business.senha_certificado` (base64).
     * Retorna null se ausente. ADR 0090.
     *
     * Desde a ADR 0449 nenhum caminho de emissão usa isto: o `carregarParaSefaz` não cai mais no
     * legado. Fica só como leitor da linha antiga, para inspeção e migração.
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

    /**
     * Lê o .pfx. Devolve [info do openssl_pkcs12_read, binário a gravar].
     *
     * O OpenSSL 3 não carrega o provider "legacy", então .pfx no formato
     * antigo (pbeWithSHA1And40BitRC2-CBC, comum em A1 emitido no Brasil) falha
     * com "unsupported" mesmo com a senha certa. Nesse caso convertemos com o
     * binário `openssl pkcs12 -legacy` e devolvemos o .pfx regravado no formato
     * atual. Senha errada é detectada antes (o MAC é verificado antes de
     * decifrar) e sai com mensagem própria.
     *
     * @return array{0: array, 1: string}
     */
    private function readPkcs12(string $content, string $senha): array
    {
        if ($this->pkcs12Reader) {
            return [($this->pkcs12Reader)($content, $senha), $content];
        }

        $this->limparErrosOpenssl();
        $info = [];
        if (openssl_pkcs12_read($content, $info, $senha)) {
            return [$info, $content];
        }

        $erro = $this->coletarErrosOpenssl();
        if (self::ehSenhaErrada($erro)) {
            throw new InvalidArgumentException(self::MSG_SENHA_ERRADA);
        }
        if (! self::ehFormatoNaoSuportado($erro)) {
            throw new InvalidArgumentException(self::MSG_ARQUIVO_INVALIDO." [{$erro}]");
        }

        $convertido = $this->converterPfxLegado($content, $senha);
        $info = [];
        if (! openssl_pkcs12_read($convertido, $info, $senha)) {
            $erro = $this->coletarErrosOpenssl();
            throw new InvalidArgumentException(self::MSG_FORMATO_NAO_SUPORTADO." [{$erro}]");
        }

        Log::info('nfe.certificado.pfx_legado_convertido', ['bytes_original' => strlen($content)]);

        return [$info, $convertido];
    }

    public const MSG_SENHA_ERRADA = 'Senha do certificado incorreta.';

    public const MSG_ARQUIVO_INVALIDO = 'Arquivo .pfx inválido ou corrompido.';

    public const MSG_FORMATO_NAO_SUPORTADO = 'O certificado usa um formato antigo que este servidor não conseguiu converter.';

    public static function ehSenhaErrada(string $erroOpenssl): bool
    {
        return stripos($erroOpenssl, 'mac verify failure') !== false;
    }

    public static function ehFormatoNaoSuportado(string $erroOpenssl): bool
    {
        return stripos($erroOpenssl, 'unsupported') !== false;
    }

    /**
     * Converte .pfx antigo pro formato atual via `openssl pkcs12 -legacy`.
     *
     * Segurança: o .pfx entra por stdin e a senha por variável de ambiente
     * (`-passin env:`) — nada de senha em argv, arquivo temporário ou log.
     * A chave em PEM só existe no pipe e em memória, e é re-empacotada na hora
     * com a mesma senha (openssl_pkcs12_export usa o formato atual, AES).
     */
    private function converterPfxLegado(string $content, string $senha): string
    {
        if (! function_exists('proc_open')) {
            throw new InvalidArgumentException(self::MSG_FORMATO_NAO_SUPORTADO.' [proc_open indisponível]');
        }

        $env = ['NFE_PFX_SENHA' => $senha];
        foreach (['PATH', 'HOME', 'OPENSSL_MODULES', 'OPENSSL_CONF'] as $var) {
            $valor = getenv($var);
            if ($valor !== false && $valor !== '') {
                $env[$var] = $valor;
            }
        }

        $cmd = [self::opensslBin(), 'pkcs12', '-legacy', '-nodes', '-passin', 'env:NFE_PFX_SENHA'];
        $proc = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (! is_resource($proc)) {
            throw new InvalidArgumentException(self::MSG_FORMATO_NAO_SUPORTADO.' [falha ao iniciar openssl]');
        }

        fwrite($pipes[0], $content);
        fclose($pipes[0]);
        $pem = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $rc = proc_close($proc);

        if ($rc !== 0) {
            if (self::ehSenhaErrada($stderr)) {
                throw new InvalidArgumentException(self::MSG_SENHA_ERRADA);
            }
            $resumo = trim(strtok($stderr, "\n") ?: "rc={$rc}");
            throw new InvalidArgumentException(self::MSG_FORMATO_NAO_SUPORTADO." [{$resumo}]");
        }

        $pkey = preg_match('/-----BEGIN (?:RSA |EC |ENCRYPTED )?PRIVATE KEY-----.+?-----END (?:RSA |EC |ENCRYPTED )?PRIVATE KEY-----/s', $pem, $mk)
            ? openssl_pkey_get_private($mk[0])
            : false;
        preg_match_all('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', $pem, $mc);
        $pem = '';
        if ($pkey === false || $mc[0] === []) {
            throw new InvalidArgumentException(self::MSG_FORMATO_NAO_SUPORTADO.' [saída do openssl sem chave ou certificado]');
        }

        $leaf = null;
        $cadeia = [];
        foreach ($mc[0] as $certPem) {
            if ($leaf === null && openssl_x509_check_private_key($certPem, $pkey)) {
                $leaf = $certPem;
            } else {
                $cadeia[] = $certPem;
            }
        }
        if ($leaf === null) {
            throw new InvalidArgumentException(self::MSG_FORMATO_NAO_SUPORTADO.' [nenhum certificado casa com a chave]');
        }

        $saida = '';
        $opts = $cadeia === [] ? [] : ['extracerts' => $cadeia];
        if (! openssl_pkcs12_export($leaf, $saida, $pkey, $senha, $opts)) {
            throw new InvalidArgumentException(self::MSG_FORMATO_NAO_SUPORTADO.' ['.$this->coletarErrosOpenssl().']');
        }

        return $saida;
    }

    private static function opensslBin(): string
    {
        $configurado = (string) config('nfebrasil.openssl_bin', '');
        if ($configurado !== '') {
            return $configurado;
        }
        foreach (['/usr/bin/openssl', '/usr/local/bin/openssl'] as $caminho) {
            if (is_executable($caminho)) {
                return $caminho;
            }
        }

        return 'openssl';
    }

    private function limparErrosOpenssl(): void
    {
        while (openssl_error_string() !== false) {
            // drena a fila de erros de chamadas anteriores
        }
    }

    private function coletarErrosOpenssl(): string
    {
        $erros = [];
        while (($e = openssl_error_string()) !== false) {
            $erros[] = $e;
        }

        return $erros === [] ? 'sem mensagem' : implode(' | ', $erros);
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
