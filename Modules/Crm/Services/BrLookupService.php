<?php

declare(strict_types=1);

namespace Modules\Crm\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BrLookupService — proxy server-side ViaCEP + BrasilAPI com cache Redis.
 *
 * Wave C (ADR 0179 — Cliente drawer 760px). OBRIGATORIO server-side: ViaCEP
 * e BrasilAPI tem rate limit por IP (~30 req/min/IP). Larissa biz=4 ROTA
 * LIVRE faz ~30 cadastros/dia em pico -- sem cache, fura rate limit no ato
 * compartilhado de IP NAT da empresa.
 *
 * Cache TTL:
 *   - CEP: 90 dias (logradouro nao muda na pratica)
 *   - CNPJ: 30 dias (situacao cadastral pode mudar)
 *
 * Timeout: 4s. Larissa biz=4 nao espera mais. Se rate limit ou timeout,
 * controller responde 503 + Front cai em "preencher manual" graceful.
 *
 * Retry: 2 tentativas com backoff 200ms. NUNCA 3+ (degrada UX).
 *
 * Multi-tenant: chaves de cache NAO namespaceadas por business_id porque
 * dados sao publicos federais (CEP/CNPJ). Cache compartilhado entre tenants
 * = OK e desejavel (1 cache hit vale pra todos).
 *
 * @see Modules\Crm\Http\Controllers\ClienteLookupController
 * @see memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md
 * @see memory/requisitos/Crm/RUNBOOK-Cliente-drawer-760px.md §4 Wave C
 */
class BrLookupService
{
    /** Cache TTL CEP: 90 dias em segundos. */
    private const CACHE_TTL_CEP = 60 * 60 * 24 * 90;

    /** Cache TTL CNPJ: 30 dias em segundos. */
    private const CACHE_TTL_CNPJ = 60 * 60 * 24 * 30;

    /** Timeout HTTP em segundos. */
    private const TIMEOUT_S = 4;

    /** Retries com backoff 200ms (total max 2 tentativas extras). */
    private const RETRY_TIMES = 2;

    private const RETRY_BACKOFF_MS = 200;

    /**
     * Consulta CEP via ViaCEP com cache Redis 90 dias.
     *
     * `complemento` adicionado 2026-05-23 -- ViaCEP retorna pra CEPs com
     * faixas de numeracao (ex 01310-100 "lado impar 612 a 1510" SP, comum
     * em Av Paulista / Rua Augusta). Cobre gap 80% -> 100% aderência ViaCEP.
     * Politica feedback-lookup-cnpj-sobrescreve-dados: dado oficial publico
     * -> SOBRESCREVE (Receita/Correios e fonte da verdade pro endereco).
     *
     * @param  string  $cep  CEP em qualquer formato (so digitos sao usados)
     * @return array{logradouro:string,complemento:string,bairro:string,cidade:string,uf:string}|null
     *                                                                                              null = CEP invalido (formato), CEP nao existe, ou erro upstream
     */
    public function lookupCep(string $cep): ?array
    {
        $cep = preg_replace('/\D/', '', $cep) ?? '';

        if (strlen($cep) !== 8) {
            return null;
        }

        return Cache::remember(
            "br_lookup:cep:{$cep}",
            self::CACHE_TTL_CEP,
            function () use ($cep): ?array {
                try {
                    $response = Http::timeout(self::TIMEOUT_S)
                        ->retry(self::RETRY_TIMES, self::RETRY_BACKOFF_MS)
                        ->get("https://viacep.com.br/ws/{$cep}/json/");

                    if ($response->failed()) {
                        Log::warning('BrLookupService::lookupCep upstream failed', [
                            'cep' => $cep,
                            'status' => $response->status(),
                        ]);

                        return null;
                    }

                    // ViaCEP retorna { "erro": true } pra CEP inexistente (HTTP 200).
                    if ($response->json('erro')) {
                        return null;
                    }

                    return [
                        'logradouro' => (string) ($response->json('logradouro') ?? ''),
                        // `complemento` ViaCEP -- ex "lado impar 612 a 1510",
                        // "do km 12 ao km 18", "lote 4". Frontend EnderecoTab
                        // mapeia pra `address_line_2` (canon UPOS).
                        'complemento' => (string) ($response->json('complemento') ?? ''),
                        'bairro' => (string) ($response->json('bairro') ?? ''),
                        'cidade' => (string) ($response->json('localidade') ?? ''),
                        'uf' => (string) ($response->json('uf') ?? ''),
                    ];
                } catch (\Throwable $e) {
                    Log::warning('BrLookupService::lookupCep exception', [
                        'cep' => $cep,
                        'message' => $e->getMessage(),
                    ]);

                    return null;
                }
            }
        );
    }

    /**
     * Consulta CNPJ via BrasilAPI com cache Redis 30 dias.
     *
     * Retorno inclui dados de endereco normalizados pro contrato
     * ClienteAutosaveController::endereco (canon UPOS: zip_code,
     * address_line_1, neighborhood, city, state). Wagner 2026-05-22 --
     * antes so trazia razao_social/fantasia, drawer 760 ficava com aba
     * Endereco vazia mesmo a BrasilAPI tendo respondido tudo na mesma
     * chamada. Agora IdentificacaoTab.handleCnpjLookup propaga endereco
     * pra PATCH /cliente/{id}/endereco no mesmo fluxo.
     *
     * Politica de preenchimento client-side (Wagner 2026-05-22, ver
     * memory/reference/feedback-lookup-cnpj-sobrescreve-dados.md):
     *   - Dados cadastrais oficiais (razao_social, fantasia, endereco,
     *     city_code IBGE) -> SOBRESCREVE no contact (Receita e fonte da
     *     verdade pra esses campos).
     *   - Contatos pessoais (email, mobile) -> SO PREENCHE se vazio
     *     (telefone publico da Receita pode estar desatualizado/diferente
     *     do contato real digitado pelo user).
     *
     * IE: BrasilAPI NAO retorna IE (responsabilidade Sintegra/SEFAZ -- 27
     * sistemas estaduais diferentes). ie=null sempre. ADR futura avalia
     * provider pago (cnpj.ws / CNPJa! / ReceitaWS Plus ~R$ [redacted Tier 0]/mes 5k consultas).
     *
     * @param  string  $cnpj  CNPJ em qualquer formato (so digitos sao usados)
     * @return array{razao_social:string,fantasia:string,ie:string|null,situacao:string,zip_code:string,address_line_1:string,neighborhood:string,city:string,state:string,city_code:string,email:string,mobile:string}|null
     *                                                                                                                                                                                                              null = CNPJ invalido (formato), CNPJ nao existe, ou erro upstream
     */
    public function lookupCnpj(string $cnpj): ?array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj) ?? '';

        if (strlen($cnpj) !== 14) {
            return null;
        }

        return Cache::remember(
            "br_lookup:cnpj:{$cnpj}",
            self::CACHE_TTL_CNPJ,
            function () use ($cnpj): ?array {
                try {
                    $response = Http::timeout(self::TIMEOUT_S)
                        ->retry(self::RETRY_TIMES, self::RETRY_BACKOFF_MS)
                        ->get("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");

                    if ($response->failed()) {
                        Log::warning('BrLookupService::lookupCnpj upstream failed', [
                            'cnpj' => substr($cnpj, 0, 8) . '******', // mascara PII parcial em log
                            'status' => $response->status(),
                        ]);

                        return null;
                    }

                    // Endereco: BrasilAPI retorna logradouro + numero separados,
                    // backend UPOS guarda combinado em address_line_1 ("Rua X, 123").
                    $logradouro = trim((string) ($response->json('logradouro') ?? ''));
                    $numero = trim((string) ($response->json('numero') ?? ''));
                    $addressLine1 = match (true) {
                        $logradouro !== '' && $numero !== '' => "{$logradouro}, {$numero}",
                        $logradouro !== '' => $logradouro,
                        default => '',
                    };

                    // CEP: BrasilAPI ja retorna 8 digitos sem formatacao mas
                    // garantimos normalizacao defensiva.
                    $cepRaw = (string) ($response->json('cep') ?? '');
                    $zipCode = preg_replace('/\D/', '', $cepRaw) ?? '';

                    // city_code IBGE: BrasilAPI retorna `codigo_municipio`
                    // como inteiro 7 digitos (ex 3550308 = SP). Defensivo:
                    // normaliza pra string so com digitos. Wagner 2026-05-22:
                    // obrigatorio em NFe/NFSe (campos enderEmit/cMun, enderDest/cMun).
                    $cityCodeRaw = $response->json('codigo_municipio');
                    $cityCode = '';
                    if (is_numeric($cityCodeRaw) || is_string($cityCodeRaw)) {
                        $cityCode = preg_replace('/\D/', '', (string) $cityCodeRaw) ?? '';
                    }

                    // Telefone: BrasilAPI retorna ddd_telefone_1 como string
                    // sem mascara (ex "1133334444" ou "11933334444"). Front
                    // aplica maskCellPhone/maskPhone na exibicao.
                    $mobile = trim((string) ($response->json('ddd_telefone_1') ?? ''));
                    $mobileDigits = preg_replace('/\D/', '', $mobile) ?? '';

                    return [
                        'razao_social' => (string) ($response->json('razao_social') ?? ''),
                        // BrasilAPI usa `nome_fantasia` (snake_case), Cowork blueprint
                        // usa `fantasia`. Normalizamos pro contrato Cowork.
                        'fantasia' => (string) ($response->json('nome_fantasia') ?? ''),
                        // BrasilAPI nao retorna IE (responsabilidade Sintegra/SEFAZ).
                        // Front mostra campo IE manual quando lookupCnpj responde.
                        // ADR futura avalia provider pago (cnpj.ws / CNPJa! / ReceitaWS Plus).
                        'ie' => null,
                        'situacao' => (string) ($response->json('descricao_situacao_cadastral') ?? ''),
                        // Endereco -- chaves canon ClienteAutosaveController::endereco.
                        // Wagner 2026-05-22 -- preenchimento automatico tab Endereco do
                        // drawer 760 a partir do lookup CNPJ. SOBRESCREVE no client.
                        'zip_code' => $zipCode,
                        'address_line_1' => $addressLine1,
                        'neighborhood' => trim((string) ($response->json('bairro') ?? '')),
                        'city' => trim((string) ($response->json('municipio') ?? '')),
                        'state' => trim((string) ($response->json('uf') ?? '')),
                        // Codigo IBGE municipio (7 digitos) -- obrigatorio NFe/NFSe.
                        // Migration 2026_05_22_180000 adiciona coluna `city_code`.
                        'city_code' => $cityCode,
                        // Contatos -- regra so-vazio no front. BrasilAPI traz dados
                        // publicos da Receita; pode estar desatualizado/diferente do
                        // contato real digitado pelo user (Wagner 2026-05-22).
                        'email' => trim((string) ($response->json('email') ?? '')),
                        'mobile' => $mobileDigits,
                    ];
                } catch (\Throwable $e) {
                    Log::warning('BrLookupService::lookupCnpj exception', [
                        'cnpj' => substr($cnpj, 0, 8) . '******',
                        'message' => $e->getMessage(),
                    ]);

                    return null;
                }
            }
        );
    }
}
