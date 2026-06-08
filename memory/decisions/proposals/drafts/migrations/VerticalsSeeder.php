<?php

/**
 * DRAFT — NÃO EXECUTAR DIRETO.
 *
 * Seeder: top 52 verticais oimpresso Insights.
 * Origem: gap-schema-oimpresso-multi-cliente-multi-vertical.md (F18 schema).
 *
 * Local final sugerido: Modules/Insights/Database/Seeders/VerticalsSeeder.php
 *
 * Felipe:
 *   - Conferir se cnae_codes batem com IBGE 2.3 (ex: 1813-0/01 = "Impressão de material para uso publicitário").
 *   - Rodar via `php artisan module:seed Insights --class=VerticalsSeeder`.
 *   - Idempotente via `insertOrIgnore` na unique key `slug`.
 */

namespace Modules\Insights\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VerticalsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $verticals = [
            // Top 10 (foco curto-prazo oimpresso Insights)
            ['slug' => 'comunicacao_visual', 'name' => 'Comunicação Visual', 'name_plural' => 'Comunicação Visual', 'cnae_codes' => json_encode(['1813-0/01', '1813-0/99']), 'sort_order' => 1],
            ['slug' => 'oficina_auto', 'name' => 'Oficina Auto', 'name_plural' => 'Oficinas Auto', 'cnae_codes' => json_encode(['4520-0/01', '4520-0/02', '4520-0/03']), 'sort_order' => 2],
            ['slug' => 'vestuario', 'name' => 'Vestuário', 'name_plural' => 'Vestuário', 'cnae_codes' => json_encode(['4781-4/00', '4782-2/01', '4782-2/02']), 'sort_order' => 3],
            ['slug' => 'salao_beleza', 'name' => 'Salão de Beleza', 'name_plural' => 'Salões de Beleza', 'cnae_codes' => json_encode(['9602-5/01', '9602-5/02']), 'sort_order' => 4],
            ['slug' => 'construcao_civil', 'name' => 'Construção Civil', 'name_plural' => 'Construção Civil', 'cnae_codes' => json_encode(['4399-1/03', '4120-4/00']), 'sort_order' => 5],
            ['slug' => 'autopecas', 'name' => 'Autopeças', 'name_plural' => 'Autopeças', 'cnae_codes' => json_encode(['4530-7/03', '4530-7/01']), 'sort_order' => 6],
            ['slug' => 'farmacia', 'name' => 'Farmácia', 'name_plural' => 'Farmácias', 'cnae_codes' => json_encode(['4771-7/01', '4771-7/02']), 'sort_order' => 7],
            ['slug' => 'contabilidade', 'name' => 'Contabilidade', 'name_plural' => 'Contabilidade', 'cnae_codes' => json_encode(['6920-6/01', '6920-6/02']), 'sort_order' => 8],
            ['slug' => 'odontologia', 'name' => 'Odontologia', 'name_plural' => 'Odontologia', 'cnae_codes' => json_encode(['8630-5/04', '8650-0/06']), 'sort_order' => 9],
            ['slug' => 'materiais_construcao', 'name' => 'Materiais de Construção', 'name_plural' => 'Materiais de Construção', 'cnae_codes' => json_encode(['4744-0/05', '4744-0/01']), 'sort_order' => 10],

            // 11-25 — médio prazo
            ['slug' => 'restaurante', 'name' => 'Restaurante', 'name_plural' => 'Restaurantes', 'cnae_codes' => json_encode(['5611-2/01', '5611-2/03']), 'sort_order' => 11],
            ['slug' => 'lanchonete', 'name' => 'Lanchonete', 'name_plural' => 'Lanchonetes', 'cnae_codes' => json_encode(['5611-2/03', '5620-1/04']), 'sort_order' => 12],
            ['slug' => 'padaria', 'name' => 'Padaria', 'name_plural' => 'Padarias', 'cnae_codes' => json_encode(['1091-1/02', '4721-1/02']), 'sort_order' => 13],
            ['slug' => 'pet_shop', 'name' => 'Pet Shop', 'name_plural' => 'Pet Shops', 'cnae_codes' => json_encode(['4789-0/04', '9609-2/07']), 'sort_order' => 14],
            ['slug' => 'mercearia', 'name' => 'Mercearia / Mini Mercado', 'name_plural' => 'Mercearias', 'cnae_codes' => json_encode(['4712-1/00']), 'sort_order' => 15],
            ['slug' => 'supermercado', 'name' => 'Supermercado', 'name_plural' => 'Supermercados', 'cnae_codes' => json_encode(['4711-3/02', '4711-3/01']), 'sort_order' => 16],
            ['slug' => 'academia', 'name' => 'Academia', 'name_plural' => 'Academias', 'cnae_codes' => json_encode(['9313-1/00']), 'sort_order' => 17],
            ['slug' => 'clinica_estetica', 'name' => 'Clínica Estética', 'name_plural' => 'Clínicas Estéticas', 'cnae_codes' => json_encode(['9602-5/02', '8690-9/04']), 'sort_order' => 18],
            ['slug' => 'medicina', 'name' => 'Medicina', 'name_plural' => 'Medicina', 'cnae_codes' => json_encode(['8630-5/01', '8630-5/02', '8630-5/03']), 'sort_order' => 19],
            ['slug' => 'optica', 'name' => 'Ótica', 'name_plural' => 'Óticas', 'cnae_codes' => json_encode(['4774-1/00', '3250-7/04']), 'sort_order' => 20],
            ['slug' => 'joalheria', 'name' => 'Joalheria', 'name_plural' => 'Joalherias', 'cnae_codes' => json_encode(['4783-1/01', '4783-1/02']), 'sort_order' => 21],
            ['slug' => 'papelaria', 'name' => 'Papelaria', 'name_plural' => 'Papelarias', 'cnae_codes' => json_encode(['4761-0/03']), 'sort_order' => 22],
            ['slug' => 'livraria', 'name' => 'Livraria', 'name_plural' => 'Livrarias', 'cnae_codes' => json_encode(['4761-0/01', '4761-0/02']), 'sort_order' => 23],
            ['slug' => 'floricultura', 'name' => 'Floricultura', 'name_plural' => 'Floriculturas', 'cnae_codes' => json_encode(['4789-0/02']), 'sort_order' => 24],
            ['slug' => 'lavanderia', 'name' => 'Lavanderia', 'name_plural' => 'Lavanderias', 'cnae_codes' => json_encode(['9601-7/01', '9601-7/02']), 'sort_order' => 25],

            // 26-40 — long tail
            ['slug' => 'serralheria', 'name' => 'Serralheria', 'name_plural' => 'Serralherias', 'cnae_codes' => json_encode(['2542-0/00', '2599-3/01']), 'sort_order' => 26],
            ['slug' => 'marcenaria', 'name' => 'Marcenaria', 'name_plural' => 'Marcenarias', 'cnae_codes' => json_encode(['1622-6/02', '3101-2/00']), 'sort_order' => 27],
            ['slug' => 'graficas_rapidas', 'name' => 'Gráficas Rápidas', 'name_plural' => 'Gráficas Rápidas', 'cnae_codes' => json_encode(['1813-0/99', '1822-9/00']), 'sort_order' => 28],
            ['slug' => 'editora', 'name' => 'Editora', 'name_plural' => 'Editoras', 'cnae_codes' => json_encode(['5811-5/00', '5813-1/00']), 'sort_order' => 29],
            ['slug' => 'agencia_publicidade', 'name' => 'Agência de Publicidade', 'name_plural' => 'Agências de Publicidade', 'cnae_codes' => json_encode(['7311-4/00']), 'sort_order' => 30],
            ['slug' => 'estudio_fotografia', 'name' => 'Estúdio de Fotografia', 'name_plural' => 'Estúdios de Fotografia', 'cnae_codes' => json_encode(['7420-0/01', '7420-0/02']), 'sort_order' => 31],
            ['slug' => 'eventos', 'name' => 'Eventos / Buffet', 'name_plural' => 'Eventos', 'cnae_codes' => json_encode(['8230-0/01', '8230-0/02', '5620-1/02']), 'sort_order' => 32],
            ['slug' => 'transportadora', 'name' => 'Transportadora', 'name_plural' => 'Transportadoras', 'cnae_codes' => json_encode(['4930-2/02', '4930-2/01']), 'sort_order' => 33],
            ['slug' => 'imobiliaria', 'name' => 'Imobiliária', 'name_plural' => 'Imobiliárias', 'cnae_codes' => json_encode(['6821-8/01', '6821-8/02']), 'sort_order' => 34],
            ['slug' => 'consultoria_ti', 'name' => 'Consultoria TI', 'name_plural' => 'Consultoria TI', 'cnae_codes' => json_encode(['6202-3/00', '6209-1/00']), 'sort_order' => 35],
            ['slug' => 'desenvolvimento_software', 'name' => 'Desenvolvimento de Software', 'name_plural' => 'Desenvolvimento de Software', 'cnae_codes' => json_encode(['6201-5/01', '6201-5/02']), 'sort_order' => 36],
            ['slug' => 'escritorio_advocacia', 'name' => 'Escritório de Advocacia', 'name_plural' => 'Escritórios de Advocacia', 'cnae_codes' => json_encode(['6911-7/01', '6911-7/02']), 'sort_order' => 37],
            ['slug' => 'arquitetura', 'name' => 'Arquitetura', 'name_plural' => 'Arquitetura', 'cnae_codes' => json_encode(['7111-1/00']), 'sort_order' => 38],
            ['slug' => 'engenharia', 'name' => 'Engenharia', 'name_plural' => 'Engenharia', 'cnae_codes' => json_encode(['7112-0/00']), 'sort_order' => 39],
            ['slug' => 'industria_metalurgica', 'name' => 'Indústria Metalúrgica', 'name_plural' => 'Indústria Metalúrgica', 'cnae_codes' => json_encode(['2511-0/00', '2512-8/00']), 'sort_order' => 40],

            // 41-52 — verticais long-tail
            ['slug' => 'calcados', 'name' => 'Calçados', 'name_plural' => 'Calçados', 'cnae_codes' => json_encode(['4782-2/01', '1531-9/02']), 'sort_order' => 41],
            ['slug' => 'cosmeticos', 'name' => 'Cosméticos', 'name_plural' => 'Cosméticos', 'cnae_codes' => json_encode(['4772-5/00', '2063-1/00']), 'sort_order' => 42],
            ['slug' => 'brindes_promocional', 'name' => 'Brindes / Promocional', 'name_plural' => 'Brindes / Promocional', 'cnae_codes' => json_encode(['4789-0/99', '1813-0/01']), 'sort_order' => 43],
            ['slug' => 'fachadas_letreiros', 'name' => 'Fachadas e Letreiros', 'name_plural' => 'Fachadas e Letreiros', 'cnae_codes' => json_encode(['4329-1/03', '7319-0/02']), 'sort_order' => 44],
            ['slug' => 'plotagem', 'name' => 'Plotagem', 'name_plural' => 'Plotagem', 'cnae_codes' => json_encode(['1813-0/01']), 'sort_order' => 45],
            ['slug' => 'revenda_veiculos', 'name' => 'Revenda de Veículos', 'name_plural' => 'Revenda de Veículos', 'cnae_codes' => json_encode(['4511-1/01', '4511-1/02']), 'sort_order' => 46],
            ['slug' => 'casa_construcao', 'name' => 'Casa & Construção (varejo)', 'name_plural' => 'Casa & Construção', 'cnae_codes' => json_encode(['4744-0/01', '4744-0/05']), 'sort_order' => 47],
            ['slug' => 'eletronica_assistencia', 'name' => 'Eletrônica / Assistência Técnica', 'name_plural' => 'Eletrônica', 'cnae_codes' => json_encode(['9521-5/00', '9512-6/00']), 'sort_order' => 48],
            ['slug' => 'bar_choperia', 'name' => 'Bar / Choperia', 'name_plural' => 'Bares', 'cnae_codes' => json_encode(['5611-2/02']), 'sort_order' => 49],
            ['slug' => 'pizzaria', 'name' => 'Pizzaria', 'name_plural' => 'Pizzarias', 'cnae_codes' => json_encode(['5611-2/03']), 'sort_order' => 50],
            ['slug' => 'transportes_app', 'name' => 'Transportes App / Frete', 'name_plural' => 'Transportes', 'cnae_codes' => json_encode(['4930-2/02', '5320-2/02']), 'sort_order' => 51],
            ['slug' => 'outros', 'name' => 'Outros / Não Categorizado', 'name_plural' => 'Outros', 'cnae_codes' => json_encode([]), 'sort_order' => 99],
        ];

        foreach ($verticals as $v) {
            DB::table('verticals')->insertOrIgnore(array_merge($v, [
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->command->info('Verticals seeded: ' . count($verticals) . ' verticais (insertOrIgnore — idempotente).');
    }
}
