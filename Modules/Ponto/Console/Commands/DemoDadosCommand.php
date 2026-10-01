<?php

declare(strict_types=1);

namespace Modules\Ponto\Console\Commands;

use App\Business;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ponto:demo-dados — dados FICTÍCIOS do ERP no business demo da revisão das lojas.
 *
 * [W] 2026-10-01: "pode colocar dados fictícios em prod no 235". O app das lojas mostra as telas
 * do Mobile (/m) e o revisor da Apple/Google precisa abri-las com conteúdo. O que cada tela lê está
 * em memory/requisitos/AppMobile/MAPA-DE-DADOS-v1.md §10; aqui entram só Início e Pessoas (o mapa
 * recomenda esperar as decisões D4–D6 para Tarefas, Pedidos e Produção).
 *
 * NÃO cria marcação de ponto: [W] 2026-10-01 "marcações só no staging" — marcação é append-only.
 *
 * Só escreve no business demo (achado pelo nome; nunca 1/4/98). Tudo marcado com `DEMO-` (contact_id,
 * invoice_no, sku, numero do título). Idempotente: rodar de novo não duplica e REFAZ as datas
 * relativas (vendas de hoje/ontem, vencimentos), para o Início não esvaziar com o passar dos dias.
 * Inserts diretos de propósito: não dispara observers (Financeiro, FSM, estoque) — é retrato
 * fictício para a tela, não operação.
 *
 * --limpar: apaga vendas, produtos e pessoas DEMO- do business demo; títulos viram `cancelado`
 * (fin_titulos não permite delete por regra de domínio).
 *
 * Uso:
 *   php artisan ponto:demo-dados --dry-run   # mostra a tabela de valores, não grava
 *   php artisan ponto:demo-dados
 *   php artisan ponto:demo-dados --limpar
 */
class DemoDadosCommand extends Command
{
    protected $signature = 'ponto:demo-dados
        {--dry-run : Mostra o que seria gravado (tabela de valores), sem escrever}
        {--limpar : Remove os dados DEMO- do business demo}';

    protected $description = 'Semeia dados fictícios do ERP (Início e Pessoas do app) no business demo da revisão das lojas.';

    /** [contact_id, nome, tipo PF/PJ, papéis] — nomes inventados; sem CPF/CNPJ, sem telefone real. */
    public const PESSOAS = [
        ['DEMO-P1', 'Padaria Sol Nascente (demo)', 'PJ', ['customer']],
        ['DEMO-P2', 'Ana Ribeiro (demo)', 'PF', ['customer']],
        ['DEMO-P3', 'Gráfica Ponto Azul (demo)', 'PJ', ['customer', 'supplier']],
        ['DEMO-P4', 'Distribuidora Vale Verde (demo)', 'PJ', ['supplier']],
        ['DEMO-P5', 'Bruno Costa (demo)', 'PF', ['employee']],
        ['DEMO-P6', 'Carla Mendes (demo)', 'PF', ['customer']],
    ];

    /** [invoice_no, contact_id, dias atrás, total, payment_status] */
    public const VENDAS = [
        ['DEMO-V1', 'DEMO-P1', 0, 180.00, 'paid'],
        ['DEMO-V2', 'DEMO-P2', 0, 95.50, 'paid'],
        ['DEMO-V3', 'DEMO-P6', 1, 240.00, 'due'],
    ];

    /** [numero, tipo, contact_id, valor, dias até o vencimento (negativo = vencido)] */
    public const TITULOS = [
        ['DEMO-T1', 'receber', 'DEMO-P2', 180.00, 7],
        ['DEMO-T2', 'receber', 'DEMO-P6', 320.00, -5],
        ['DEMO-T3', 'pagar', 'DEMO-P4', 150.00, 3],
    ];

    /** [sku, nome, estoque, mínimo] — os dois abaixo do mínimo (KPI "Estoque baixo"). */
    public const PRODUTOS = [
        ['DEMO-SKU1', 'Camiseta básica P (demo)', 2, 5],
        ['DEMO-SKU2', 'Caneca personalizada (demo)', 3, 10],
    ];

    public function handle(): int
    {
        $business = Business::query()->where('name', DemoRevisorCommand::BUSINESS_NOME)->first();
        if (! $business || in_array((int) $business->id, DemoRevisorCommand::BIZ_PROIBIDOS, true)) {
            $this->error('Business demo não encontrado (ou id protegido). Rode ponto:demo-revisor antes.');

            return 1;
        }
        $bizId = (int) $business->id;
        $autor = User::query()->where('username', DemoRevisorCommand::GESTOR_USERNAME)->where('business_id', $bizId)->first();
        $local = DB::table('business_locations')->where('business_id', $bizId)->orderBy('id')->first();
        if (! $autor || ! $local) {
            $this->error('Business demo sem gestor.demo ou sem local. Rode ponto:demo-revisor antes.');

            return 1;
        }

        if ($this->option('limpar')) {
            return $this->limpar($bizId);
        }

        $this->mostrarTabela($bizId);
        if ($this->option('dry-run')) {
            $this->info('[dry-run] nada foi gravado.');

            return 0;
        }

        DB::transaction(function () use ($bizId, $autor, $local) {
            $contatos = $this->semearPessoas($bizId, (int) $autor->id);
            $this->semearVendas($bizId, (int) $autor->id, (int) $local->id, $contatos);
            $this->semearTitulos($bizId, (int) $autor->id, $contatos);
            $this->semearProdutos($bizId, (int) $autor->id, (int) $local->id);
        });

        $this->info("OK — dados fictícios no business demo {$bizId}.");

        return 0;
    }

    private function mostrarTabela(int $bizId): void
    {
        $hoje = now();
        $linhas = [];
        foreach (self::VENDAS as [$inv, $c, $dias, $total, $pago]) {
            $linhas[] = ['venda', $inv, $c, number_format($total, 2, ',', '.'), $hoje->copy()->subDays($dias)->toDateString(), $pago];
        }
        foreach (self::TITULOS as [$num, $tipo, $c, $valor, $dias]) {
            $linhas[] = ["título {$tipo}", $num, $c, number_format($valor, 2, ',', '.'), 'vence ' . $hoje->copy()->addDays($dias)->toDateString(), 'aberto'];
        }
        foreach (self::PRODUTOS as [$sku, $nome, $qtd, $min]) {
            $linhas[] = ['produto', $sku, $nome, '—', "estoque {$qtd} · mínimo {$min}", '—'];
        }
        $this->info("Business demo {$bizId} — o que este comando grava (valores fictícios):");
        $this->table(['tipo', 'chave', 'pessoa/nome', 'valor', 'data', 'situação'], $linhas);
    }

    /** @return array<string,int> contact_id DEMO-Px => contacts.id */
    private function semearPessoas(int $bizId, int $autorId): array
    {
        $ids = [];
        foreach (self::PESSOAS as $i => [$codigo, $nome, $tipo, $papeis]) {
            $dados = [
                'business_id' => $bizId, 'contact_id' => $codigo, 'name' => $nome,
                'type' => in_array('supplier', $papeis, true) && ! in_array('customer', $papeis, true) ? 'supplier'
                    : (in_array('supplier', $papeis, true) ? 'both' : 'customer'),
                'mobile' => '0000000000' . ($i + 1), 'created_by' => $autorId, 'updated_at' => now(),
            ];
            if ($papeis === ['employee']) {
                $dados['type'] = 'customer';
            }
            foreach (['customer', 'supplier', 'employee'] as $papel) {
                if (Schema::hasColumn('contacts', "is_{$papel}")) {
                    $dados["is_{$papel}"] = in_array($papel, $papeis, true) ? 1 : 0;
                }
            }
            if (Schema::hasColumn('contacts', 'tipo')) {
                $dados['tipo'] = $tipo;
            }
            if ($tipo === 'PJ') {
                $dados['supplier_business_name'] = $nome;
            }

            $existente = DB::table('contacts')->where('business_id', $bizId)->where('contact_id', $codigo)->value('id');
            if ($existente) {
                DB::table('contacts')->where('id', $existente)->update($dados);
                $ids[$codigo] = (int) $existente;
            } else {
                $ids[$codigo] = (int) DB::table('contacts')->insertGetId($dados + ['created_at' => now()]);
            }
        }

        return $ids;
    }

    private function semearVendas(int $bizId, int $autorId, int $localId, array $contatos): void
    {
        foreach (self::VENDAS as [$inv, $c, $dias, $total, $pago]) {
            $dados = [
                'business_id' => $bizId, 'location_id' => $localId, 'type' => 'sell', 'status' => 'final',
                'payment_status' => $pago, 'contact_id' => $contatos[$c], 'invoice_no' => $inv,
                'transaction_date' => now()->subDays($dias)->setTime(10 + $dias, 30),
                'total_before_tax' => $total, 'final_total' => $total, 'created_by' => $autorId,
                'is_direct_sale' => 1, 'essentials_duration' => 0, 'updated_at' => now(),
            ];
            $existente = DB::table('transactions')->where('business_id', $bizId)->where('invoice_no', $inv)->value('id');
            if ($existente) {
                DB::table('transactions')->where('id', $existente)->update($dados);
            } else {
                DB::table('transactions')->insert($dados + ['created_at' => now()]);
            }
        }
    }

    private function semearTitulos(int $bizId, int $autorId, array $contatos): void
    {
        foreach (self::TITULOS as [$num, $tipo, $c, $valor, $dias]) {
            $venc = now()->addDays($dias);
            $dados = [
                'business_id' => $bizId, 'numero' => $num, 'tipo' => $tipo, 'status' => 'aberto',
                'cliente_id' => $contatos[$c], 'valor_total' => $valor, 'valor_aberto' => $valor,
                'emissao' => now()->subDays(10)->toDateString(), 'vencimento' => $venc->toDateString(),
                'competencia_mes' => $venc->format('Y-m'), 'origem' => 'manual', 'created_by' => $autorId,
                'updated_at' => now(),
            ];
            $existente = DB::table('fin_titulos')->where('business_id', $bizId)->where('numero', $num)->value('id');
            if ($existente) {
                DB::table('fin_titulos')->where('id', $existente)->update($dados);
            } else {
                DB::table('fin_titulos')->insert($dados + ['created_at' => now()]);
            }
        }
    }

    private function semearProdutos(int $bizId, int $autorId, int $localId): void
    {
        $unidade = DB::table('units')->where('business_id', $bizId)->orderBy('id')->value('id')
            ?? DB::table('units')->insertGetId([
                'business_id' => $bizId, 'actual_name' => 'Unidade', 'short_name' => 'Un', 'allow_decimal' => 0,
                'created_by' => $autorId, 'created_at' => now(), 'updated_at' => now(),
            ]);

        foreach (self::PRODUTOS as [$sku, $nome, $qtd, $min]) {
            $produtoId = DB::table('products')->where('business_id', $bizId)->where('sku', $sku)->value('id');
            if (! $produtoId) {
                $produtoId = DB::table('products')->insertGetId([
                    'name' => $nome, 'business_id' => $bizId, 'type' => 'single', 'unit_id' => $unidade,
                    'tax_type' => 'exclusive', 'enable_stock' => 1, 'alert_quantity' => $min, 'sku' => $sku,
                    'barcode_type' => 'C128', 'created_by' => $autorId, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $pvId = DB::table('product_variations')->insertGetId([
                    'name' => 'DUMMY', 'product_id' => $produtoId, 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $varId = DB::table('variations')->insertGetId([
                    'name' => 'DUMMY', 'product_id' => $produtoId, 'sub_sku' => $sku, 'product_variation_id' => $pvId,
                    'default_purchase_price' => 0, 'dpp_inc_tax' => 0, 'profit_percent' => 0,
                    'default_sell_price' => 0, 'sell_price_inc_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('product_locations')->insertOrIgnore(['product_id' => $produtoId, 'location_id' => $localId]);
            } else {
                DB::table('products')->where('id', $produtoId)->update(['alert_quantity' => $min, 'updated_at' => now()]);
                $varId = DB::table('variations')->where('product_id', $produtoId)->value('id');
                $pvId = DB::table('variations')->where('id', $varId)->value('product_variation_id');
            }

            DB::table('variation_location_details')->updateOrInsert(
                ['product_id' => $produtoId, 'variation_id' => $varId, 'location_id' => $localId],
                ['product_variation_id' => $pvId, 'qty_available' => $qtd, 'updated_at' => now()]
            );
        }
    }

    private function limpar(int $bizId): int
    {
        DB::transaction(function () use ($bizId) {
            $vendas = DB::table('transactions')->where('business_id', $bizId)->where('invoice_no', 'like', 'DEMO-%')->delete();
            $titulos = DB::table('fin_titulos')->where('business_id', $bizId)->where('numero', 'like', 'DEMO-%')
                ->update(['status' => 'cancelado', 'valor_aberto' => 0, 'updated_at' => now()]);
            $produtos = DB::table('products')->where('business_id', $bizId)->where('sku', 'like', 'DEMO-%')->pluck('id');
            DB::table('variation_location_details')->whereIn('product_id', $produtos)->delete();
            DB::table('product_locations')->whereIn('product_id', $produtos)->delete();
            DB::table('variations')->whereIn('product_id', $produtos)->delete();
            DB::table('product_variations')->whereIn('product_id', $produtos)->delete();
            DB::table('products')->whereIn('id', $produtos)->delete();
            $pessoas = DB::table('contacts')->where('business_id', $bizId)->where('contact_id', 'like', 'DEMO-%')->delete();
            $this->info("Limpo no business {$bizId}: {$vendas} venda(s), {$produtos->count()} produto(s), {$pessoas} pessoa(s) apagadas; {$titulos} título(s) cancelado(s).");
        });

        return 0;
    }
}
