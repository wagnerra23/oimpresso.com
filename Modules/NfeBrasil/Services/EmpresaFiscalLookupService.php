<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Models\NfeCertificado;

/**
 * Configurar pelo certificado — LEITURA dos dados fiscais da PRÓPRIA empresa
 * (playbook Fiscal thread 21 · UC-NFTR-14 · UC-NFTR-16 · R-NFE-028).
 *
 * Read-only por contrato: devolve `{campo, valor, fonte}` e **não grava nada**
 * (`Index.charter.md` — "❌ Auto-aplicar template sem clique"). Quem aplica é
 * `TributacaoTemplateService::aplicar`, por clique.
 *
 * Fontes e autoridade (ADR 0186 invariante 5, sem mudar invariante — só um
 * consumidor novo de `SefazConsultaCadastroService::consultar`):
 *   - IE, situação, `regime_apuracao` → SEFAZ (única fonte)
 *   - Simples/MEI, CNAEs → BrasilAPI (SEFAZ não devolve)
 *   - razão social → SEFAZ se presente, BrasilAPI fallback
 *   - regime: SEFAZ × BrasilAPI discordando volta `divergente` com as opções,
 *     **sem escolher** (UC-NFTR-16 · D-SUPORTE: o risco é de quem aplica).
 *
 * Por que a chamada BrasilAPI é própria aqui: os dois lookups existentes
 * (`Modules\Crm\Services\BrLookupService::lookupCnpj` e
 * `App\Services\BR\BrasilApiService::lookupCnpj`) NÃO expõem CNAE nem
 * `opcao_pelo_simples` — medido 2026-10-06. Estendê-los muda o shape de um
 * cache de 30d em outro módulo; registrado em `_saida-21.md` para unificar.
 *
 * Multi-tenant Tier 0 (ADR 0093): `$businessId` vem SEMPRE da sessão (no
 * controller); o certificado é lido com `where('business_id')` explícito além
 * do global scope. A chain de cert (ADR 0186 inv. 1) é a do serviço SEFAZ —
 * aqui só se chama, nunca se reordena.
 */
class EmpresaFiscalLookupService
{
    private const BRASILAPI_URL = 'https://brasilapi.com.br/api/cnpj/v1/';

    private const CACHE_TTL_DIAS = 30;

    public function __construct(
        private readonly SefazConsultaCadastroService $sefaz,
    ) {}

    /**
     * @return array{campos: array<string, array{campo: string, valor: mixed, fonte: ?string, motivo?: string, divergente?: bool, opcoes?: array<int, array{valor: string, fonte: string}>}>}
     */
    public function ler(int $businessId): array
    {
        [$cnpj, $fonteCnpj] = $this->cnpjDaEmpresa($businessId);

        if ($cnpj === null) {
            return ['campos' => [
                'cnpj' => ['campo' => 'cnpj', 'valor' => null, 'fonte' => null, 'motivo' => 'sem_cnpj'],
            ]];
        }

        $api = $this->brasilApi($cnpj);

        $uf = $api['uf'] ?? null;
        $fonteUf = $uf ? 'brasilapi' : null;
        if ($uf === null) {
            $uf = $this->ufDoCadastro($businessId);
            $fonteUf = $uf ? 'cadastro' : null;
        }

        $sefazMotivo = null;
        $sefaz = $uf !== null
            ? $this->sefaz->consultar($cnpj, $uf, $businessId, $sefazMotivo)
            : null;
        if ($uf === null) {
            $sefazMotivo = 'sem_uf';
        }

        $campos = [
            'cnpj' => ['campo' => 'cnpj', 'valor' => $cnpj, 'fonte' => $fonteCnpj],
            'uf' => ['campo' => 'uf', 'valor' => $uf, 'fonte' => $fonteUf],
            'razao_social' => $this->razaoSocial($sefaz, $api),
            'ie' => $this->soSefaz('ie', $sefaz['ie'] ?? null, $sefaz, $sefazMotivo),
            'situacao' => $this->soSefaz('situacao', $sefaz['situacao_label'] ?? null, $sefaz, $sefazMotivo),
            'cnaes' => [
                'campo' => 'cnaes',
                'valor' => $api['cnaes'] ?? null,
                'fonte' => isset($api['cnaes']) ? 'brasilapi' : null,
            ],
            'regime' => $this->regime($sefaz, $api),
        ];

        return ['campos' => $campos];
    }

    /** @return array{0: ?string, 1: ?string} */
    private function cnpjDaEmpresa(int $businessId): array
    {
        $cert = NfeCertificado::where('business_id', $businessId)
            ->where('ativo', true)
            ->orderByDesc('id')
            ->value('cnpj_titular');
        $cert = preg_replace('/\D/', '', (string) $cert) ?? '';
        if (strlen($cert) === 14) {
            return [$cert, 'certificado'];
        }

        $cadastro = preg_replace('/\D/', '', (string) DB::table('business')
            ->where('id', $businessId)
            ->value('tax_number_1')) ?? '';

        return strlen($cadastro) === 14 ? [$cadastro, 'cadastro'] : [null, null];
    }

    private function ufDoCadastro(int $businessId): ?string
    {
        $state = strtoupper(trim((string) DB::table('business_locations')
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->value('state')));

        return preg_match('/^[A-Z]{2}$/', $state) ? $state : null;
    }

    /** @return array{razao_social: ?string, uf: ?string, cnaes: array<int, string>, simples: ?bool, mei: ?bool}|null */
    private function brasilApi(string $cnpj): ?array
    {
        return Cache::remember(
            "nfe_empresa_fiscal:brasilapi:{$cnpj}",
            now()->addDays(self::CACHE_TTL_DIAS),
            function () use ($cnpj): ?array {
                try {
                    $resp = Http::timeout(8)->acceptJson()->get(self::BRASILAPI_URL.$cnpj);
                    $data = $resp->successful() ? $resp->json() : null;
                    if (! is_array($data)) {
                        return null;
                    }

                    $cnaes = [];
                    if (! empty($data['cnae_fiscal'])) {
                        $cnaes[] = $this->formatarCnae((string) $data['cnae_fiscal']);
                    }
                    foreach ((array) ($data['cnaes_secundarios'] ?? []) as $sec) {
                        if (! empty($sec['codigo'])) {
                            $cnaes[] = $this->formatarCnae((string) $sec['codigo']);
                        }
                    }

                    $uf = strtoupper(trim((string) ($data['uf'] ?? '')));

                    return [
                        'razao_social' => trim((string) ($data['razao_social'] ?? '')) ?: null,
                        'uf' => preg_match('/^[A-Z]{2}$/', $uf) ? $uf : null,
                        'cnaes' => array_values(array_unique($cnaes)),
                        'simples' => isset($data['opcao_pelo_simples']) ? (bool) $data['opcao_pelo_simples'] : null,
                        'mei' => isset($data['opcao_pelo_mei']) ? (bool) $data['opcao_pelo_mei'] : null,
                    ];
                } catch (\Throwable $e) {
                    Log::warning('EmpresaFiscalLookupService: BrasilAPI falhou', ['msg' => $e->getMessage()]);

                    return null;
                }
            }
        );
    }

    /** `1813001` → `1813-0/01` (formato CNAE 2.0). */
    private function formatarCnae(string $codigo): string
    {
        $d = str_pad(preg_replace('/\D/', '', $codigo) ?? '', 7, '0', STR_PAD_LEFT);

        return substr($d, 0, 4).'-'.$d[4].'/'.substr($d, 5, 2);
    }

    private function razaoSocial(?array $sefaz, ?array $api): array
    {
        if (! empty($sefaz['nome'])) {
            return ['campo' => 'razao_social', 'valor' => $sefaz['nome'], 'fonte' => $sefaz['fonte'] ?? 'sefaz'];
        }

        return [
            'campo' => 'razao_social',
            'valor' => $api['razao_social'] ?? null,
            'fonte' => isset($api['razao_social']) ? 'brasilapi' : null,
        ];
    }

    /** IE e situação: só SEFAZ (ADR 0186 inv. 5). Sem SEFAZ → vazio com o motivo, para digitar. */
    private function soSefaz(string $campo, ?string $valor, ?array $sefaz, ?string $motivo): array
    {
        if ($sefaz === null) {
            return ['campo' => $campo, 'valor' => null, 'fonte' => null, 'motivo' => $motivo ?? 'sefaz_error'];
        }

        return ['campo' => $campo, 'valor' => $valor, 'fonte' => $sefaz['fonte'] ?? 'sefaz'];
    }

    /**
     * Regime em 3 valores comparáveis entre as fontes: `mei` · `simples` · `normal`
     * (normal = presumido ou real — nenhuma das fontes distingue; o contador decide).
     */
    private function regime(?array $sefaz, ?array $api): array
    {
        $opcoes = [];

        $apur = mb_strtoupper((string) ($sefaz['regime_apuracao'] ?? ''));
        if ($apur !== '') {
            $opcoes[] = ['valor' => match (true) {
                str_contains($apur, 'SIMEI') || str_contains($apur, 'MEI') => 'mei',
                str_contains($apur, 'SIMPLES') => 'simples',
                default => 'normal',
            }, 'fonte' => $sefaz['fonte'] ?? 'sefaz'];
        }

        if ($api !== null && ($api['mei'] !== null || $api['simples'] !== null)) {
            $opcoes[] = ['valor' => match (true) {
                $api['mei'] === true => 'mei',
                $api['simples'] === true => 'simples',
                default => 'normal',
            }, 'fonte' => 'brasilapi'];
        }

        $valores = array_values(array_unique(array_column($opcoes, 'valor')));

        if (count($valores) > 1) {
            return ['campo' => 'regime', 'valor' => null, 'fonte' => null, 'divergente' => true, 'opcoes' => $opcoes];
        }

        return [
            'campo' => 'regime',
            'valor' => $valores[0] ?? null,
            'fonte' => $opcoes === [] ? null : implode('+', array_column($opcoes, 'fonte')),
            'divergente' => false,
            'opcoes' => $opcoes,
        ];
    }
}
