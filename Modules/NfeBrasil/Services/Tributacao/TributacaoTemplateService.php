<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

use Illuminate\Support\Facades\Log;
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
     * @return array{config: NfeBusinessConfig, criou: bool, mudou: bool}
     *
     * @throws InvalidArgumentException se template não existe
     */
    public function aplicar(int $businessId, string $slug): array
    {
        $tpl = $this->buscar($slug);
        if ($tpl === null) {
            throw new InvalidArgumentException("Template tributário '{$slug}' não encontrado.");
        }

        $existing = NfeBusinessConfig::where('business_id', $businessId)->first();

        $payload = [
            'business_id'         => $businessId,
            'regime'              => $tpl['regime'],
            'tributacao_default'  => $tpl['tributacao_default'],
        ];

        if ($existing === null) {
            $config = NfeBusinessConfig::create($payload);
            Log::info('Template tributário aplicado (config criada)', [
                'business_id' => $businessId,
                'slug'        => $slug,
                'config_id'   => $config->id,
            ]);
            return ['config' => $config, 'criou' => true, 'mudou' => true];
        }

        // Idempotente: comparar antes de updar.
        $mudou = $existing->regime !== $tpl['regime']
            || json_encode($existing->tributacao_default) !== json_encode($tpl['tributacao_default']);

        if (! $mudou) {
            Log::info('Template tributário re-aplicado idempotente (sem mudança)', [
                'business_id' => $businessId,
                'slug'        => $slug,
            ]);
            return ['config' => $existing, 'criou' => false, 'mudou' => false];
        }

        $existing->update([
            'regime'             => $tpl['regime'],
            'tributacao_default' => $tpl['tributacao_default'],
        ]);

        Log::info('Template tributário aplicado (config atualizada)', [
            'business_id' => $businessId,
            'slug'        => $slug,
            'config_id'   => $existing->id,
            'regime_anterior' => $existing->getOriginal('regime'),
            'regime_novo'     => $tpl['regime'],
        ]);

        return ['config' => $existing->fresh(), 'criou' => false, 'mudou' => true];
    }

    private function templatesDir(): string
    {
        return module_path('NfeBrasil', self::TEMPLATES_DIR_RELATIVE);
    }
}
