<?php

declare(strict_types=1);

namespace Modules\KB\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\KB\Entities\KbCategory;
use Modules\KB\Entities\KbSubcategory;

/**
 * KbSubcategoriesSeeder — 18 subcats (port do KB_SUBCATS Cowork + governance; +reference/comparativo 2026-07-17).
 *
 * Contrato: SCHEMA-DB-V1.md §13
 *
 * `auto_match` segue padrão JSON:
 *   {"field": "<col>", "op": "=" | "regex" | "in", "value": "<val>"}
 *
 * Idempotente via firstOrCreate (business_id, category_id, slug).
 */
class KbSubcategoriesSeeder extends Seeder
{
    private const SUBCATEGORIES = [
        // -- producao --
        ['cat' => 'producao', 'slug' => 'plotter-impressao', 'label' => 'Plotter / Impressão grande formato',
         'auto_match' => ['field' => 'equip', 'op' => 'regex', 'value' => '/(?:plotter|roland|hp\s*latex|epson)/i']],
        ['cat' => 'producao', 'slug' => 'corte-acabamento',  'label' => 'Corte / Acabamento',
         'auto_match' => ['field' => 'tags', 'op' => 'regex', 'value' => '/(?:corte|laminar|recorte)/i']],
        ['cat' => 'producao', 'slug' => 'instalacao',        'label' => 'Instalação no cliente',
         'auto_match' => ['field' => 'tags', 'op' => 'regex', 'value' => '/(?:instala|fachada|montar|cola)/i']],

        // -- equipamentos --
        ['cat' => 'equipamentos', 'slug' => 'roland', 'label' => 'Roland VS-540',
         'auto_match' => ['field' => 'equip', 'op' => '=', 'value' => 'Roland VS-540']],
        ['cat' => 'equipamentos', 'slug' => 'hp-latex', 'label' => 'HP Latex 365',
         'auto_match' => ['field' => 'equip', 'op' => '=', 'value' => 'HP Latex 365']],
        ['cat' => 'equipamentos', 'slug' => 'plotter-recorte', 'label' => 'Plotter de Recorte',
         'auto_match' => ['field' => 'tags', 'op' => 'regex', 'value' => '/plotter[\s-]*recorte/i']],

        // -- pre-impressao --
        ['cat' => 'pre-impressao', 'slug' => 'sangria',      'label' => 'Sangria e medidas',
         'auto_match' => ['field' => 'tags', 'op' => 'regex', 'value' => '/(?:sangria|medida|tama)/i']],
        ['cat' => 'pre-impressao', 'slug' => 'perfil-cor',   'label' => 'Perfil de cor / ICC',
         'auto_match' => ['field' => 'tags', 'op' => 'regex', 'value' => '/(?:icc|cor|cmyk)/i']],

        // -- atendimento --
        ['cat' => 'atendimento', 'slug' => 'orcamento',      'label' => 'Orçamento',
         'auto_match' => ['field' => 'tags', 'op' => 'regex', 'value' => '/orçamento|orcamento/i']],
        ['cat' => 'atendimento', 'slug' => 'briefing',       'label' => 'Briefing'],

        // -- fiscal --
        ['cat' => 'fiscal', 'slug' => 'nfe',                 'label' => 'NF-e',
         'auto_match' => ['field' => 'tags', 'op' => 'regex', 'value' => '/(?:nfe|nf-e|notafiscal)/i']],
        ['cat' => 'fiscal', 'slug' => 'nfse',                'label' => 'NFS-e',
         'auto_match' => ['field' => 'tags', 'op' => 'regex', 'value' => '/nfse/i']],

        // -- governance (Wagner — corpus canon) --
        ['cat' => 'governance', 'slug' => 'adr',      'label' => 'ADR (Decisão Arquitetural)',
         'auto_match' => ['field' => 'type', 'op' => '=', 'value' => 'adr']],
        ['cat' => 'governance', 'slug' => 'session',  'label' => 'Session Log',
         'auto_match' => ['field' => 'type', 'op' => '=', 'value' => 'session']],
        ['cat' => 'governance', 'slug' => 'charter',  'label' => 'Page Charter',
         'auto_match' => ['field' => 'type', 'op' => '=', 'value' => 'charter']],
        ['cat' => 'governance', 'slug' => 'runbook',  'label' => 'Runbook operacional',
         'auto_match' => ['field' => 'type', 'op' => '=', 'value' => 'runbook']],
        ['cat' => 'governance', 'slug' => 'briefing', 'label' => 'Briefing executivo',
         'auto_match' => ['field' => 'type', 'op' => '=', 'value' => 'briefing']],
        ['cat' => 'governance', 'slug' => 'spec',     'label' => 'Spec / US-XXX-NNN',
         'auto_match' => ['field' => 'type', 'op' => '=', 'value' => 'spec']],
        // + reference/comparativo ([W] 2026-07-17: "todos são da empresa 1, reclassifique").
        // São corpus interno de governança (biz=1); antes ficavam sem casa (110+10 nós) porque
        // não tinham subcat. Agora entram sob Governança como as demais, via type-match.
        ['cat' => 'governance', 'slug' => 'reference',   'label' => 'Referência',
         'auto_match' => ['field' => 'type', 'op' => '=', 'value' => 'reference']],
        ['cat' => 'governance', 'slug' => 'comparativo', 'label' => 'Comparativo (nós vs mercado)',
         'auto_match' => ['field' => 'type', 'op' => '=', 'value' => 'comparativo']],
    ];

    public function run(int $businessId): void
    {
        $criados = 0;
        foreach (self::SUBCATEGORIES as $sub) {
            $cat = KbCategory::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('slug', $sub['cat'])
                ->first();

            if (! $cat) {
                $this->command?->warn("KbSubcategoriesSeeder: categoria '{$sub['cat']}' não encontrada pra biz={$businessId}. Rode KbCategoriesSeeder primeiro.");
                continue;
            }

            $row = KbSubcategory::withoutGlobalScopes()->firstOrCreate(
                [
                    'business_id' => $businessId,
                    'category_id' => $cat->id,
                    'slug'        => $sub['slug'],
                ],
                [
                    'label'      => $sub['label'],
                    'auto_match' => $sub['auto_match'] ?? null,
                ],
            );
            if ($row->wasRecentlyCreated) {
                $criados++;
            }
        }

        $this->command?->info("KbSubcategoriesSeeder [biz={$businessId}]: criados {$criados} de ".count(self::SUBCATEGORIES));
    }
}
