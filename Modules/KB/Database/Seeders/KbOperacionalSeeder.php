<?php

declare(strict_types=1);

namespace Modules\KB\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\KB\Entities\KbCategory;
use Modules\KB\Entities\KbDecisionTree;
use Modules\KB\Entities\KbDecisionTreeStep;
use Modules\KB\Entities\KbNode;
use Modules\KB\Entities\KbPath;
use Modules\KB\Entities\KbPathStep;

/**
 * KbOperacionalSeeder — V1 piloto: 3 artigos seed + 1 trilha + 1 troubleshooter
 * pra business piloto (biz=4 ROTA LIVRE recomendado).
 *
 * Contrato: SCHEMA-DB-V1.md §13
 *
 * **V1 minimal:** 3 artigos exemplares (não 18 do Cowork) pra validar pipeline
 * Wagner→bridge→read. Os 18 artigos completos virão em PR separado quando
 * Larissa começar a usar (ONDA 6+).
 *
 * Idempotente: firstOrCreate por (business_id, slug).
 */
class KbOperacionalSeeder extends Seeder
{
    public function run(int $businessId): void
    {
        $producaoCat = KbCategory::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('slug', 'producao')->first();

        $equipCat = KbCategory::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('slug', 'equipamentos')->first();

        $fiscalCat = KbCategory::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('slug', 'fiscal')->first();

        if (! $producaoCat || ! $equipCat || ! $fiscalCat) {
            $this->command?->warn("KbOperacionalSeeder [biz={$businessId}]: rode KbCategoriesSeeder antes.");
            return;
        }

        // ---- 3 artigos seed ----
        $arts = [
            [
                'slug'     => 'como-configurar-perfil-cor-roland',
                'title'    => 'Como configurar perfil de cor ICC na Roland VS-540',
                'excerpt'  => 'Passo a passo para ajustar perfil ICC pra impressão CMYK na Roland VS-540.',
                'cat'      => $equipCat,
                'equip'    => 'Roland VS-540',
                'nivel'    => 'intermediario',
                'tags'     => ['icc', 'cor', 'roland', 'cmyk'],
                'body'     => [
                    ['kind' => 'h2',   'text' => 'Pré-requisitos'],
                    ['kind' => 'list', 'items' => ['Perfil ICC do fornecedor da lona', 'VersaWorks atualizado', 'Roland VS-540 calibrada nos últimos 30d']],
                    ['kind' => 'h2',   'text' => 'Passo a passo'],
                    ['kind' => 'list', 'items' => [
                        'Abra VersaWorks → Color Management',
                        'Importe o ICC do fornecedor',
                        'Selecione no perfil de impressão da lona',
                        'Imprima target colorimétrico antes do job real',
                    ]],
                ],
            ],
            [
                'slug'     => 'tabela-sangria-banner-lona',
                'title'    => 'Tabela de sangria pra banner em lona',
                'excerpt'  => 'Margens de sangria recomendadas por tipo de acabamento (ilhós, bainha, costura).',
                'cat'      => $producaoCat,
                'tags'     => ['sangria', 'medida', 'lona', 'banner'],
                'body'     => [
                    ['kind' => 'h2',   'text' => 'Sangria por acabamento'],
                    ['kind' => 'list', 'items' => [
                        'Ilhós metálico → 5cm sangria + 2cm dobra',
                        'Bainha simples → 3cm sangria',
                        'Costura termofusiva → 2cm sangria',
                    ]],
                    ['kind' => 'callout', 'tone' => 'warn', 'text' => 'Sempre confirmar com cliente antes de cortar.'],
                ],
            ],
            [
                'slug'     => 'nfe-cancelar-antes-180min',
                'title'    => 'Como cancelar NFe (prazo SEFAZ 180min)',
                'excerpt'  => 'Procedimento pra cancelar NFe dentro do prazo legal SEFAZ.',
                'cat'      => $fiscalCat,
                'tags'     => ['nfe', 'cancelar', 'sefaz'],
                'body'     => [
                    ['kind' => 'h2',   'text' => 'Quando posso cancelar'],
                    ['kind' => 'para', 'text' => 'Você tem 180 minutos após emissão pra cancelar NFe sem multa. Depois desse prazo, é Carta de Correção ou substituição.'],
                    ['kind' => 'h2',   'text' => 'Passo'],
                    ['kind' => 'list', 'items' => [
                        'Acesse /nfe-brasil',
                        'Encontre a NFe (use o filtro por número)',
                        'Clique em "Cancelar" → SEFAZ → confirme com justificativa',
                    ]],
                ],
            ],
        ];

        $articleNodes = [];
        foreach ($arts as $art) {
            $node = KbNode::withoutGlobalScopes()->firstOrCreate(
                ['business_id' => $businessId, 'slug' => $art['slug']],
                [
                    'type'        => 'article',
                    'title'       => $art['title'],
                    'excerpt'     => $art['excerpt'],
                    'body_blocks' => $art['body'],
                    'is_editable' => true,
                    'status'      => 'ok',
                    'category_id' => $art['cat']->id,
                    'equip'       => $art['equip'] ?? null,
                    'nivel'       => $art['nivel'] ?? null,
                    'tags'        => $art['tags'] ?? null,
                ],
            );
            $articleNodes[$art['slug']] = $node;
        }

        // ---- 1 trilha "Primeiro dia da Larissa no balcão" ----
        $trilha = KbPath::withoutGlobalScopes()->firstOrCreate(
            ['business_id' => $businessId, 'slug' => 'larissa-primeiro-dia'],
            [
                'title'       => 'Larissa — primeiro dia no balcão',
                'audience'    => 'Larissa (vestuário operacional)',
                'description' => 'Trilha de onboarding pra primeiro dia de uso do oimpresso ERP.',
                'hue'         => 320,
                'status'      => 'published',
            ],
        );

        // Se ainda não tem steps, popula.
        if ($trilha->wasRecentlyCreated || $trilha->steps()->count() === 0) {
            // Steps apontam pros 3 artigos seed (V1 — outros viram bridge).
            $position = 1;
            foreach (['tabela-sangria-banner-lona', 'como-configurar-perfil-cor-roland', 'nfe-cancelar-antes-180min'] as $slug) {
                if (! isset($articleNodes[$slug])) {
                    continue;
                }
                KbPathStep::withoutGlobalScopes()->firstOrCreate(
                    ['path_id' => $trilha->id, 'position' => $position],
                    [
                        'business_id' => $businessId,
                        'node_id'     => $articleNodes[$slug]->id,
                        'step_type'   => 'leitura',
                    ],
                );
                $position++;
            }
        }

        // ---- 1 troubleshooter "Roland VS-540 não imprime" ----
        $tree = KbDecisionTree::withoutGlobalScopes()->firstOrCreate(
            ['business_id' => $businessId, 'slug' => 'roland-vs540-nao-imprime'],
            [
                'title'       => 'Roland VS-540 não imprime',
                'equip'       => 'Roland VS-540',
                'when_to_use' => 'Job mandado pra Roland VS-540 não inicia ou para no meio.',
                'hue'         => 280,
                'status'      => 'published',
            ],
        );

        if ($tree->wasRecentlyCreated || $tree->steps()->count() === 0) {
            // Step 1: tinta?
            $s1 = KbDecisionTreeStep::withoutGlobalScopes()->create([
                'business_id' => $businessId,
                'tree_id'     => $tree->id,
                'position'    => 1,
                'question'    => 'O painel da Roland mostra alerta de tinta baixa?',
                // SIM → fix imediato
                'yes_fix'     => 'Troque o cartucho de tinta da cor sinalizada. Aguarde a Roland reconhecer (~30s) e tente reimprimir.',
                // NÃO → próximo step
                // (linkamos no segundo passe)
            ]);

            // Step 2: cabeça?
            $s2 = KbDecisionTreeStep::withoutGlobalScopes()->create([
                'business_id' => $businessId,
                'tree_id'     => $tree->id,
                'position'    => 2,
                'question'    => 'O test pattern de cabeça mostra falhas?',
                'yes_fix'     => 'Rode "Cleaning Médio" pela VersaWorks ou painel da Roland. Se persistir após 3 cleanings, chame o técnico.',
                'no_fix'      => 'Verifique a conexão VersaWorks ↔ Roland (USB ou rede). Se OK, reinicie o computador e Roland. Se persistir, abra OS de manutenção.',
            ]);

            // Link s1.no_next_step_id → s2
            $s1->no_next_step_id = $s2->id;
            $s1->no_fix = null;
            $s1->save();

            // Root step.
            $tree->root_step_id = $s1->id;
            $tree->save();
        }

        $this->command?->info("KbOperacionalSeeder [biz={$businessId}]: 3 artigos + 1 trilha + 1 troubleshooter seed OK.");
    }
}
