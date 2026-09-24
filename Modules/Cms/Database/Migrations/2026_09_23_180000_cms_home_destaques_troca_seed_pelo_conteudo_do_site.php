<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread Cms/01 fase 2b (caminho A, [W] 2026-09-23) — a home pública passa a ler os destaques
 * do registro `feature` da página `layout=home`, que agora se edita no painel.
 *
 * Medido em produção 2026-09-23: esse registro é o seed do UltimatePOS de 2022-10-20, em inglês
 * e nunca editado ("Features to skyrocket 🚀 your business growth", "Access Anywhere!"…),
 * enquanto o site mostra o texto fixo em português do FeatureGrid.tsx. Ligar a home sem esta
 * troca publicaria o inglês. Aqui o seed vira EXATAMENTE o que o site já mostra — o site fica
 * idêntico e passa a ser editável.
 *
 * Idempotente e conservadora: só troca se o registro AINDA for o seed (título e 1º item batem).
 * Qualquer edição feita por alguém fica intocada. `down()` não restaura o inglês de propósito.
 */
return new class extends Migration
{
    private const SEED_TITULO = 'Features to skyrocket 🚀 your business growth';

    private const SEED_PRIMEIRO_ITEM = 'Access Anywhere!';

    public function up(): void
    {
        if (! Schema::hasTable('cms_page_metas') || ! Schema::hasTable('cms_pages')) {
            return;
        }

        $homeId = DB::table('cms_pages')->where('type', 'page')->where('layout', 'home')->value('id');
        if ($homeId === null) {
            return;
        }

        $meta = DB::table('cms_page_metas')->where('cms_page_id', $homeId)->where('meta_key', 'feature')->first();
        if ($meta === null) {
            return;
        }

        $atual = json_decode((string) $meta->meta_value, true) ?: [];
        $ehSeed = ($atual['title'] ?? null) === self::SEED_TITULO
            && (($atual['content'][0]['title'] ?? null) === self::SEED_PRIMEIRO_ITEM);

        if (! $ehSeed) {
            return;
        }

        DB::table('cms_page_metas')->where('id', $meta->id)->update([
            'meta_value' => json_encode([
                'id' => (string) $meta->id,
                'title' => 'Oito módulos. Uma plataforma.',
                'description' => 'Pare de pular entre 5 sistemas pra fechar o mês. Do orçamento à entrega — o oimpresso integra a operação de ponta a ponta.',
                // Cópia literal do FALLBACK_FEATURES de resources/js/Components/Site/FeatureGrid.tsx.
                'content' => [
                    ['icon' => '📐', 'title' => 'Orçamento por m² (com. visual)', 'description' => 'Cálculo automático por m² com tabelas próprias por substrato, acabamento e instalação. Adeus planilha.'],
                    ['icon' => '🏭', 'title' => 'Ordem de produção (OP)', 'description' => 'Do orçamento aprovado direto pra OP. Acompanha produção em tempo real, alerta atraso e fecha entrega.'],
                    ['icon' => '🛒', 'title' => 'PDV completo', 'description' => 'Frente de caixa rápida, com leitor de código de barras, múltiplas formas de pagamento e impressão direta.'],
                    ['icon' => '📦', 'title' => 'Estoque em tempo real', 'description' => 'Controle multi-loja com lotes, validade, transferência entre filiais e relatórios de giro.'],
                    ['icon' => '🧾', 'title' => 'NF-e, NFC-e e NFS-e', 'description' => 'Emissão fiscal homologada para todo o Brasil. CT-e, MDF-e e devoluções incluídos.'],
                    ['icon' => '⏱️', 'title' => 'Ponto e RH', 'description' => 'Marcação digital, espelho de ponto, escala e folha simplificada — pronto pra fiscalização.'],
                    ['icon' => '💳', 'title' => 'Financeiro & boletos', 'description' => 'Contas a pagar, a receber, conciliação bancária e geração de boletos em mais de 20 bancos.'],
                    ['icon' => '📊', 'title' => 'BI & dashboards', 'description' => 'Veja o que importa em segundos. Vendas, margem, ticket médio, ruptura — tudo num lugar.'],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Sem volta ao seed em inglês: ele nunca foi o que o site mostrou.
    }
};
