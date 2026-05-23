<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use App\Business;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use NFePHP\Common\Standardize;
use NFePHP\NFe\Tools;
use RuntimeException;

/**
 * SefazConsultaCadastroService — consulta IE + situação cadastral via SEFAZ
 * ConsultaCadastro2 (WS NFe `cConsCad`). ADR 0186.
 *
 * Substitui o legacy `NFeService::consultaCadastro($cnpj, $uf)` (linha 580
 * app/Services/NFeService.php, `echo $json` direto não-Inertia) por canon
 * Inertia-friendly multi-tenant.
 *
 * Chain de cert via `CertificadoService::carregarParaSefazComFallback`:
 *   1. cert primário do business consumidor
 *   2. cert legado business.certificado (ADR 0090)
 *   3. cert institucional oimpresso (config fiscal.fallback_business_id)
 *
 * Cache Redis 30d por `(cnpj, uf)` — chave compartilhada entre businesses
 * (dado público, mesma justificativa BrasilAPI em BrLookupService).
 *
 * Cobertura UFs: matriz em `config('fiscal.sefaz_consulta_cadastro_ufs_supported')` —
 * UFs fora da lista retornam null + caller renderiza badge "preencha manual".
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id sempre escopa o cert na chain;
 * fallback institucional usa `withoutGlobalScope` AUDITADO em audit log.
 *
 * @see memory/decisions/0186-chain-certificado-sefaz-consulta-cadastro.md
 * @see Modules\NfeBrasil\Services\CertificadoService::carregarParaSefazComFallback
 * @see Modules\Crm\Http\Controllers\ClienteLookupController::cnpjSefaz
 */
class SefazConsultaCadastroService
{
    public function __construct(
        private readonly CertificadoService $certService,
    ) {}

    /**
     * Map `cSit` SEFAZ → label PT-BR canônico pra coluna `contacts.sefaz_cad_sit`.
     *
     * Fonte: schema NFe ConsultaCadastro2 + comunidade nfephp-org.
     */
    private const C_SIT_LABELS = [
        '0' => 'habilitado',
        '1' => 'nao_habilitado',
        '2' => 'suspenso',
        '3' => 'cancelado',
        '4' => 'paralisado',
        '5' => 'baixado',
    ];

    /**
     * Deriva `indIEDest` (1/2/9) a partir do retorno SEFAZ.
     *   1 = contribuinte ICMS   → IE válida + cSit ∈ {habilitado, suspenso, paralisado}
     *   2 = contribuinte isento → IE = "ISENTO" (string literal)
     *   9 = não contribuinte    → sem IE OU cSit em {cancelado, baixado}
     */
    private function derivarIndIeDest(?string $ie, ?string $cSit): int
    {
        $ieClean = trim((string) $ie);

        if ($ieClean === '' || $ieClean === '0') {
            return 9;
        }

        if (strcasecmp($ieClean, 'ISENTO') === 0) {
            return 2;
        }

        // cSit ∈ {3 cancelado, 5 baixado} → tratar como não contribuinte (não pode receber NFe com IE).
        if (in_array((string) $cSit, ['3', '5'], true)) {
            return 9;
        }

        return 1;
    }

    /**
     * Consulta cadastro ICMS na SEFAZ da UF informada usando cert chain (Técnica C).
     *
     * Retorno expandido (ADR 0186 §Evolução Técnica C):
     *   - IE + cSit + xNome (base — sempre)
     *   - **ind_ie_dest** derivado (1/2/9) — pronto pra coluna `contacts.ind_ie_dest`
     *   - **ind_cred_nfe** (0/1/2/3/4) — informativo + warning UI antecipado
     *   - **regime_apuracao** (xRegApur) — informativo
     *   - **endereco_sefaz** array — endereço fiscal SEFAZ (pode diferir do Receita)
     *   - **alerta_warning** array — gatilhos UI pra evitar rejeição NFe ("cliente com IE inativa", etc)
     *
     * @param  string  $cnpj  CNPJ em qualquer formato (só dígitos usados)
     * @param  string  $uf    UF de 2 letras (RS, SP, etc)
     * @param  int     $businessId  Business consumidor (multi-tenant Tier 0)
     * @return array|null  null = UF não suportada, sem cert, ou erro SEFAZ
     */
    public function consultar(string $cnpj, string $uf, int $businessId): ?array
    {
        $cnpjDigits = preg_replace('/\D/', '', $cnpj) ?? '';
        $uf = strtoupper(trim($uf));

        if (strlen($cnpjDigits) !== 14) {
            return null;
        }

        // Validação UF suportada — config canônica.
        $ufsSupported = config('fiscal.sefaz_consulta_cadastro_ufs_supported', []);
        if (! isset($ufsSupported[$uf])) {
            Log::info('SefazConsultaCadastroService: UF não suportada — skip', [
                'uf' => $uf,
                'business_id' => $businessId,
            ]);
            return null;
        }

        // Feature flag global on/off — ADR 0186 reversível.
        if (! config('fiscal.sefaz_consulta_cadastro_enabled', true)) {
            Log::info('SefazConsultaCadastroService: feature flag desligada — skip', [
                'business_id' => $businessId,
            ]);
            return null;
        }

        // Cache Redis 30d — dado público compartilhado entre businesses.
        $cacheKey = "sefaz_cadastro:{$uf}:{$cnpjDigits}";
        $cacheTtl = (int) config('fiscal.sefaz_consulta_cadastro_cache_ttl_seconds', 60 * 60 * 24 * 30);

        return Cache::remember(
            $cacheKey,
            $cacheTtl,
            function () use ($cnpjDigits, $uf, $businessId): ?array {
                try {
                    // Carrega cert via chain (ADR 0186) — pode lançar RuntimeException se sem cert nenhum.
                    $certData = $this->certService->carregarParaSefazComFallback(
                        $businessId,
                        // Contexto pro audit log fallback (LGPD sha256, nunca plain).
                        $cnpjDigits . ':' . $uf
                    );

                    // Carrega config do business consumidor pra montar Tools.
                    // Mesmo padrão usado em NFeController::consultaCadastro legacy
                    // (cert é do business, request também — Tools precisa cnpj do solicitante).
                    $business = Business::find($businessId);
                    if (! $business) {
                        Log::warning('SefazConsultaCadastroService: business não encontrado', [
                            'business_id' => $businessId,
                        ]);
                        return null;
                    }

                    $cnpjBusiness = preg_replace('/\D/', '', (string) ($business->cnpj ?? '')) ?? '';
                    if (strlen($cnpjBusiness) !== 14) {
                        // Business sem CNPJ próprio (cadastro incompleto) → não pode consultar SEFAZ.
                        Log::warning('SefazConsultaCadastroService: business sem CNPJ válido', [
                            'business_id' => $businessId,
                        ]);
                        return null;
                    }

                    $ufBusiness = strtoupper((string) ($business->state ?? optional($business->cidade)->uf ?? ''));

                    $tools = new Tools(
                        json_encode([
                            'atualizacao' => date('Y-m-d H:i:s'),
                            'tpAmb'       => 1, // Produção — ConsultaCadastro só funciona em prod.
                            'razaosocial' => (string) ($business->razao_social ?? $business->name ?? 'oimpresso'),
                            'siglaUF'     => $ufBusiness !== '' ? $ufBusiness : $uf,
                            'cnpj'        => $cnpjBusiness,
                            'schemes'     => 'PL_009_V4',
                            'versao'      => '4.00',
                            'tokenIBPT'   => 'AAAAAAA',
                            'CSC'         => (string) ($business->csc ?? ''),
                            'CSCid'       => (string) ($business->csc_id ?? ''),
                        ]),
                        $certData['pfx_binary'],
                        // sped-nfe Tools espera Certificate object — usar Common\Certificate
                        // OU passar pfx_binary + senha. Lib aceita ambos via Tools::loadCertificate.
                    );

                    // Lib sped-nfe: signatures suportadas — Tools tem método ->loadCertificate(Certificate $cert)
                    // OU construtor com array. Pra simplificar usamos approach do NFeService legacy:
                    // construtor com config JSON, depois loadCertificate.
                    $certificate = \NFePHP\Common\Certificate::readPfx(
                        $certData['pfx_binary'],
                        $certData['senha']
                    );
                    $tools->loadCertificate($certificate);

                    // ADR 0186 §Invariante #11 (hardening 2026-05-23) — timeout
                    // backend agressivo: 4s connect + 24s total (sped-common
                    // adiciona +20s ao soaptimeout pro CURLOPT_TIMEOUT total).
                    // Default era 20s/40s — drawer travava em SEFAZ lerda.
                    // Frontend complementa com AbortController 8s (cancela ANTES
                    // do backend terminar se SEFAZ conectou mas demora demais).
                    $timeoutSeconds = (int) config('fiscal.sefaz_consulta_cadastro_timeout_seconds', 4);
                    if (isset($tools->soap) && method_exists($tools->soap, 'timeout')) {
                        $tools->soap->timeout($timeoutSeconds);
                    }

                    $iest = '';
                    $cpf = '';
                    $response = $tools->sefazCadastro($uf, $cnpjDigits, $iest, $cpf);

                    $std = new Standardize($response);
                    $arr = $std->toArray();

                    // Parse defensivo — schema SEFAZ varia por UF, mas o canon
                    // ConsultaCadastro2 retorna `infCons` com `IE`, `cSit` (situação),
                    // `xNome` (razão social). Schemas alternativos têm aninhamentos.
                    $infCons = $arr['infCons'] ?? $arr['retConsCad']['infCons'] ?? null;
                    if (! is_array($infCons)) {
                        Log::info('SefazConsultaCadastroService: resposta SEFAZ sem infCons', [
                            'uf' => $uf,
                            'business_id' => $businessId,
                        ]);
                        return null;
                    }

                    // Extrai campos base.
                    $ie = isset($infCons['IE']) ? (string) $infCons['IE'] : null;
                    $cSit = isset($infCons['cSit']) ? (string) $infCons['cSit'] : null;
                    $nome = isset($infCons['xNome']) ? (string) $infCons['xNome'] : null;

                    // Técnica C — campos fiscais expandidos.
                    $indCredNfe = isset($infCons['indCredNFe']) ? (int) $infCons['indCredNFe'] : null;
                    $regimeApuracao = isset($infCons['xRegApur']) ? (string) $infCons['xRegApur'] : null;
                    $indIeDest = $this->derivarIndIeDest($ie, $cSit);
                    $cSitLabel = $cSit !== null && isset(self::C_SIT_LABELS[$cSit])
                        ? self::C_SIT_LABELS[$cSit]
                        : null;

                    // Endereço SEFAZ (cadastro ICMS — pode estar mais atualizado que Receita).
                    // Schema cConsCad/infCons/ender (alguns SEFAZ usam aninhamento, outros flat).
                    $ender = $infCons['ender'] ?? [];
                    $enderecoSefaz = [];
                    if (is_array($ender) && ! empty($ender)) {
                        $enderecoSefaz = [
                            'logradouro' => isset($ender['xLgr']) ? (string) $ender['xLgr'] : '',
                            'numero'     => isset($ender['nro']) ? (string) $ender['nro'] : '',
                            'complemento'=> isset($ender['xCpl']) ? (string) $ender['xCpl'] : '',
                            'bairro'     => isset($ender['xBairro']) ? (string) $ender['xBairro'] : '',
                            'cep'        => isset($ender['CEP']) ? (string) $ender['CEP'] : '',
                            'cidade'     => isset($ender['xMun']) ? (string) $ender['xMun'] : '',
                            'cmun'       => isset($ender['cMun']) ? (string) $ender['cMun'] : '',
                            'uf'         => isset($ender['UF']) ? (string) $ender['UF'] : $uf,
                        ];
                    }

                    // Warnings antecipados — gatilhos UI pra evitar rejeição NFe.
                    $alertas = [];
                    if ($cSit === '1') {
                        $alertas[] = ['code' => 'cad_nao_habilitado', 'severity' => 'high',
                            'msg' => 'Destinatário não habilitado SEFAZ — NFe pode ser rejeitada (rej. 478/487)'];
                    } elseif ($cSit === '2') {
                        $alertas[] = ['code' => 'cad_suspenso', 'severity' => 'medium',
                            'msg' => 'IE suspensa — verifique antes de emitir NFe'];
                    } elseif ($cSit === '3') {
                        $alertas[] = ['code' => 'cad_cancelado', 'severity' => 'high',
                            'msg' => 'IE CANCELADA — NFe será rejeitada (use indIEDest=9 não contribuinte)'];
                    } elseif ($cSit === '5') {
                        $alertas[] = ['code' => 'cad_baixado', 'severity' => 'high',
                            'msg' => 'IE BAIXADA — empresa fora de operação'];
                    }
                    if ($indCredNfe === 4) {
                        $alertas[] = ['code' => 'nao_credenciado_nfe', 'severity' => 'low',
                            'msg' => 'Destinatário não credenciado a EMITIR NFe (recebe normalmente)'];
                    }

                    return [
                        // Base — sempre presente.
                        'ie'                => $ie,
                        'situacao'          => $cSit,         // valor numérico raw
                        'situacao_label'    => $cSitLabel,    // label PT-BR pra coluna sefaz_cad_sit
                        'nome'              => $nome,
                        'uf'                => $uf,
                        'fonte'             => 'sefaz_' . strtolower($uf),
                        'cert_source'       => (string) ($certData['source'] ?? 'unknown'),
                        'cert_business_id'  => (int) ($certData['cert_business_id'] ?? $businessId),
                        // Técnica C — expansão.
                        'ind_ie_dest'       => $indIeDest,
                        'ind_cred_nfe'      => $indCredNfe,
                        'regime_apuracao'   => $regimeApuracao,
                        'endereco_sefaz'    => $enderecoSefaz,
                        'alertas'           => $alertas,
                        'consultado_em'     => now()->toIso8601String(),
                    ];
                } catch (RuntimeException $certErr) {
                    // Cert chain vazio — caller renderiza badge "configure cert".
                    Log::info('SefazConsultaCadastroService: sem cert na chain', [
                        'business_id' => $businessId,
                        'uf' => $uf,
                        'reason' => $certErr->getMessage(),
                    ]);
                    return null;
                } catch (\Throwable $e) {
                    Log::warning('SefazConsultaCadastroService: erro SEFAZ ou parsing', [
                        'uf' => $uf,
                        'business_id' => $businessId,
                        'cnpj_len' => strlen($cnpjDigits),
                        'message' => $e->getMessage(),
                    ]);
                    return null;
                }
            }
        );
    }
}
