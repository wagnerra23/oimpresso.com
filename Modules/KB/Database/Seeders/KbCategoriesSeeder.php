<?php

declare(strict_types=1);

namespace Modules\KB\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\KB\Entities\KbCategory;

/**
 * KbCategoriesSeeder — 8 categorias default (7 operacionais + 1 governance).
 *
 * Contrato: SCHEMA-DB-V1.md §13
 *
 * Idempotente: firstOrCreate por (business_id, slug).
 * Aceita $businessId explícito — NÃO usa session() pra suportar CLI/queue.
 *
 * `hue` é a chroma OKLCH usada pelo design system Cowork V2.
 */
class KbCategoriesSeeder extends Seeder
{
    private const CATEGORIES = [
        // operacionais (gráfica/vestuário/oficina)
        ['slug' => 'producao',      'label' => 'Produção',       'hue' => 200, 'icon' => 'factory',  'sort' => 10, 'desc' => 'Fluxo de produção, OS, materiais, qualidade'],
        ['slug' => 'equipamentos',  'label' => 'Equipamentos',   'hue' => 280, 'icon' => 'cpu',      'sort' => 20, 'desc' => 'Roland, HP Latex, plotters, impressoras, manuais técnicos'],
        ['slug' => 'pre-impressao', 'label' => 'Pré-impressão',  'hue' => 100, 'icon' => 'image',    'sort' => 30, 'desc' => 'Arquivos, sangria, perfil de cor, prova'],
        ['slug' => 'atendimento',   'label' => 'Atendimento',    'hue' => 30,  'icon' => 'message',  'sort' => 40, 'desc' => 'Cliente, orçamento, prazo, expectativa, briefing'],
        ['slug' => 'fiscal',        'label' => 'Fiscal',         'hue' => 0,   'icon' => 'shield',   'sort' => 50, 'desc' => 'NFe, NFSe, NFCe, ICMS, CFOP, SEFAZ'],
        ['slug' => 'sistema',       'label' => 'Sistema',        'hue' => 240, 'icon' => 'monitor',  'sort' => 60, 'desc' => 'Como usar oimpresso ERP — caixa, vendas, kanban'],
        ['slug' => 'pessoas',       'label' => 'Pessoas',        'hue' => 320, 'icon' => 'users',    'sort' => 70, 'desc' => 'Onboarding, rotina, ponto, regulamento'],

        // governança (Wagner — corpus canon)
        ['slug' => 'governance',    'label' => 'Governança',     'hue' => 260, 'icon' => 'book',     'sort' => 0,  'desc' => 'ADRs, sessions, charters, runbooks, briefings, specs'],
    ];

    public function run(int $businessId): void
    {
        $criados = 0;
        foreach (self::CATEGORIES as $cat) {
            $row = KbCategory::withoutGlobalScopes()->firstOrCreate(
                ['business_id' => $businessId, 'slug' => $cat['slug']],
                [
                    'label'       => $cat['label'],
                    'description' => $cat['desc'],
                    'hue'         => $cat['hue'],
                    'icon'        => $cat['icon'],
                    'sort_order'  => $cat['sort'],
                ],
            );
            if ($row->wasRecentlyCreated) {
                $criados++;
            }
        }

        $this->command?->info("KbCategoriesSeeder [biz={$businessId}]: criados {$criados} de ".count(self::CATEGORIES));
    }
}
