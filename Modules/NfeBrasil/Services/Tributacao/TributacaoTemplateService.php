<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\NfeBrasil\Models\NfeBusinessConfig;

/**
 * US-NFE-TPL-001 · Templates tributários por setor + regime + UF.
 *
 * **Por que existe:**
 * Operador médio (Larissa POS, gráfica nova) não sabe qual CSOSN/CST/CFOP
 * escolher entre as ~60 opções da legislação. Templates pré-cozidos por
 * setor (comércio varejo / atacado / indústria) + regime (Simples / Presumido /
 * Real) + UF eliminam essa decisão. 1 clique = config completa.
 *
 * **L1 da estratégia de simplificação tributária** (camada mais simples).
 * L2 = wizard 5 perguntas. L3 = auto-fill por NCM. L4 = editor avançado.
 *
 * **Templates atuais:**
 * - `comercio-varejo-simples-sp` — gráfica POS, papelaria balcão B2C
 * - `comercio-atacado-simples-sp` — distribuidor B2B
 * - `industria-grafica-simples-sp` — indústria sob encomenda
 *
 * **Como adicionar:**
 * 1. Criar arquivo PHP em `Modules/NfeBrasil/Resources/templates/{slug}.php`
 *    retornando array com chaves: slug, titulo, descricao, icon, setor, regime,
 *    uf, modelo_nfe, recomendado_para, tributacao_default, observacoes[]
 * 2. Service auto-descobre via glob — não precisa registrar manualmente.
 *
 * @see memory/requisitos/NfeBrasil/SPEC.md US-NFE-010 (motor tributário)
 * @see ADR ARQ-0006 (cascade tributário 4 níveis)
 */
class TributacaoTemplateService
{
    private const TEMPLATES_DIR_RELATIVE = 'Resources/templates';

    /**
     * Lista todos os templates disponíveis (lê arquivos PHP do diretório).
     *
     * @return array<int, array{
     *     slug: string,
     *     titulo: string,
     *     descricao: string,
     *     icon: string,
     *     setor: string,
     *     regime: string,
     *     uf: string,
     *     modelo_nfe: string,
     *     recomendado_para: string,
     *     tributacao_default: array<string, mixed>,
     *     observacoes: array<int, string>
     * }>
     */
    public function listar(): array
    {
        $dir = $this->templatesDir();
        if (! is_dir($dir)) {
            return [];
        }

        $templates = [];
        foreach (glob($dir.'/*.php') ?: [] as $file) {
            $tpl = require $file;
            if (! is_array($tpl) || empty($tpl['slug'])) {
                continue;
            }
            $templates[] = $tpl;
        }

        // Ordenar por setor → regime → uf pra exibição consistente.
        usort($templates, function ($a, $b) {
            return [$a['setor'], $a['regime'], $a['uf']] <=> [$b['setor'], $b['regime'], $b['uf']];
        });

        return $templates;
    }

    /**
     * Busca um template específico por slug.
     *
     * @return array<string, mixed>|null
     */
    public function buscar(string $slug): ?array
    {
        foreach ($this->listar() as $tpl) {
            if ($tpl['slug'] === $slug) {
                return $tpl;
            }
        }
        return null;
    }

    /**
     * Aplica o template no business — cria ou atualiza `nfe_business_configs`
     * com regime + tributacao_default do template.
     *
     * NÃO modifica regras NCM existentes (`nfe_fiscal_rules`) — usuário pode
     * ter regras customizadas que precisam ser preservadas.
     *
     * Idempotente: re-aplicar mesmo template = no-op (compara JSON).
     *
     * Exige NCM padrão válido (UC-NFTR-17) e grava `activity` `template.aplicado` com
     * o autor quando a config muda.
     *
     * @return array{config: NfeBusinessConfig, criou: bool, mudou: bool}
     *
     * @throws InvalidArgumentException se template não existe
     * @throws ValidationException sem NCM padrão válido (422 — a config não muda)
     */
    public function aplicar(int $businessId, string $slug, ?string $ncmDefault = null): array
    {
        $tpl = $this->buscar($slug);
        if ($tpl === null) {
            throw new InvalidArgumentException("Template tributário '{$slug}' não encontrado.");
        }

        $existing = NfeBusinessConfig::where('business_id', $businessId)->first();

        // Auditoria 2026-05 bug #3: o template substituía `tributacao_default` inteiro e
        // levava junto o `ncm_default` — empresa "configurada" que não emitia a 1ª nota
        // (NfeService exige NCM de 8 dígitos). Agora o NCM é obrigatório e entra no default.
        $ncm = $this->resolverNcmDefault($businessId, $existing, $ncmDefault);
        $tributacao = array_merge($tpl['tributacao_default'], ['ncm_default' => $ncm]);

        $payload = [
            'business_id'         => $businessId,
            'regime'              => $tpl['regime'],
            'tributacao_default'  => $tributacao,
        ];

        if ($existing === null) {
            $config = NfeBusinessConfig::create($payload);
            Log::info('Template tributário aplicado (config criada)', [
                'business_id' => $businessId,
                'slug'        => $slug,
                'config_id'   => $config->id,
            ]);
            $this->registrarAtividade($businessId, $slug, null, $tpl['regime'], $ncm);

            return ['config' => $config, 'criou' => true, 'mudou' => true];
        }

        // Idempotente: comparar antes de updar. `==` no array decodificado (insensível à
        // ordem das chaves — o MySQL normaliza a ordem num campo `json`).
        $mudou = $existing->regime !== $tpl['regime']
            || (array) $existing->tributacao_default != $tributacao;

        if (! $mudou) {
            Log::info('Template tributário re-aplicado idempotente (sem mudança)', [
                'business_id' => $businessId,
                'slug'        => $slug,
            ]);
            return ['config' => $existing, 'criou' => false, 'mudou' => false];
        }

        $regimeAnterior = $existing->regime;

        $existing->update([
            'regime'             => $tpl['regime'],
            'tributacao_default' => $tributacao,
        ]);

        Log::info('Template tributário aplicado (config atualizada)', [
            'business_id' => $businessId,
            'slug'        => $slug,
            'config_id'   => $existing->id,
            'regime_anterior' => $regimeAnterior,
            'regime_novo'     => $tpl['regime'],
        ]);
        $this->registrarAtividade($businessId, $slug, $regimeAnterior, $tpl['regime'], $ncm);

        return ['config' => $existing->fresh(), 'criou' => false, 'mudou' => true];
    }

    /**
     * Ordena os templates por aderência à empresa (UC-NFTR-15) — **não aplica nada**.
     *
     * Pontos: regime (3) + UF (2) + setor do CNAE (1). `regime = normal` (presumido ou
     * real — as fontes não distinguem) casa os dois. Cada item traz o template inteiro,
     * com `tributacao_default` (CFOP, CSOSN/CST, alíquotas), pra tela mostrar ANTES de
     * aplicar (`ConfigDefault.charter` — "mostra valores antes de aplicar").
     *
     * @param  array<int, string|int>  $cnaes  `1813-0/01` ou `1813001`
     * @return array<int, array<string, mixed>>
     */
    public function sugerir(?string $regime, ?string $uf, array $cnaes = []): array
    {
        $regime = $regime !== null ? strtolower(trim($regime)) : null;
        $uf = $uf !== null ? strtoupper(trim($uf)) : null;

        $setores = [];
        foreach ($cnaes as $cnae) {
            $divisao = (int) substr(preg_replace('/\D/', '', (string) $cnae) ?? '', 0, 2);
            // CNAE 2.0: seção C (indústria de transformação) = divisões 10–33;
            // seção G (comércio) = 45–47.
            if ($divisao >= 10 && $divisao <= 33) {
                $setores['industria'] = true;
            } elseif ($divisao >= 45 && $divisao <= 47) {
                $setores['comercio'] = true;
            }
        }

        $itens = [];
        foreach ($this->listar() as $ordem => $tpl) {
            $casaRegime = $regime !== null && ($tpl['regime'] === $regime
                || ($regime === 'normal' && in_array($tpl['regime'], ['lucro_presumido', 'lucro_real'], true)));
            $casaUf = $uf !== null && $tpl['uf'] === $uf;
            $casaSetor = isset($setores[$tpl['setor']]);

            $itens[] = $tpl + [
                'aderencia' => [
                    'regime' => $casaRegime,
                    'uf'     => $casaUf,
                    'setor'  => $casaSetor,
                    'pontos' => ($casaRegime ? 3 : 0) + ($casaUf ? 2 : 0) + ($casaSetor ? 1 : 0),
                ],
                '_ordem' => $ordem,
            ];
        }

        usort($itens, fn ($a, $b) => [$b['aderencia']['pontos'], $a['_ordem']] <=> [$a['aderencia']['pontos'], $b['_ordem']]);

        return array_map(function ($i) {
            unset($i['_ordem']);

            return $i;
        }, $itens);
    }

    /**
     * NCM padrão pra aplicar: o informado; senão o que a config já tem; senão o
     * `business.ncm_padrao` (D-SUPORTE: "a saída é o NCM padrão da empresa, que já existe").
     * Informado e inválido NÃO cai pro fallback — o operador pediu aquele valor.
     *
     * @throws ValidationException 422 quando nenhum NCM válido (8 dígitos, ≠ 00000000)
     */
    private function resolverNcmDefault(int $businessId, ?NfeBusinessConfig $existing, ?string $informado): string
    {
        $valido = fn (?string $n): bool => $n !== null && preg_match('/^\d{8}$/', $n) === 1 && $n !== '00000000';

        if ($informado !== null && trim($informado) !== '') {
            $n = preg_replace('/\D/', '', $informado) ?? '';
            if ($valido($n)) {
                return $n;
            }
        } else {
            foreach ([
                $existing?->tributacao_default['ncm_default'] ?? null,
                DB::table('business')->where('id', $businessId)->value('ncm_padrao'),
            ] as $candidato) {
                $n = preg_replace('/\D/', '', (string) $candidato) ?? '';
                if ($valido($n)) {
                    return $n;
                }
            }
        }

        throw ValidationException::withMessages([
            'ncm_default' => 'Informe o NCM padrão da empresa (8 dígitos, diferente de 00000000) antes de aplicar o template.',
        ]);
    }

    /** US-NFE-062: o aplicar era o único caminho de mutação da tela sem `activity()`. */
    private function registrarAtividade(int $businessId, string $slug, ?string $regimeAnterior, string $regimeNovo, string $ncm): void
    {
        activity('nfe.tributacao')
            ->causedBy(auth()->user())
            ->withProperties([
                'business_id'     => $businessId,
                'slug'            => $slug,
                'regime_anterior' => $regimeAnterior,
                'regime_novo'     => $regimeNovo,
                'ncm_default'     => $ncm,
            ])
            ->log('template.aplicado');
    }

    private function templatesDir(): string
    {
        return module_path('NfeBrasil', self::TEMPLATES_DIR_RELATIVE);
    }
}
