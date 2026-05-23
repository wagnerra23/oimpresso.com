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
     * Consulta cadastro ICMS na SEFAZ da UF informada usando cert chain.
     *
     * @param  string  $cnpj  CNPJ em qualquer formato (só dígitos usados)
     * @param  string  $uf    UF de 2 letras (RS, SP, etc)
     * @param  int     $businessId  Business consumidor (multi-tenant Tier 0)
     * @return array{ie:?string,situacao:?string,nome:?string,uf:string,fonte:string,cert_source:string,cert_business_id:int}|null
     *                              null = UF não suportada, sem cert, ou erro SEFAZ
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

                    return [
                        'ie'                => isset($infCons['IE']) ? (string) $infCons['IE'] : null,
                        'situacao'          => isset($infCons['cSit']) ? (string) $infCons['cSit'] : null,
                        'nome'              => isset($infCons['xNome']) ? (string) $infCons['xNome'] : null,
                        'uf'                => $uf,
                        'fonte'             => 'sefaz_' . strtolower($uf),
                        'cert_source'       => (string) ($certData['source'] ?? 'unknown'),
                        'cert_business_id'  => (int) ($certData['cert_business_id'] ?? $businessId),
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
