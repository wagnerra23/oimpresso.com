<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peso líquido e dimensões da embalagem por produto (UC-PCAD-16 · UC-PEDIT-13 · CU-PROD-17).
 *
 * O cadastro tinha um só peso (`weight`, texto), que o WooCommerce usa como peso de frete. Ele
 * passa a ser mostrado como "Peso bruto (kg)" e não muda aqui. Entram o peso líquido (kg) e as
 * três dimensões da embalagem (cm), que frete e marketplace pedem. Todas nulas: nenhum produto
 * que já existe muda.
 *
 * `products` já carrega `business_id` (Tier 0, ADR 0093) — as colunas são atributos do produto.
 */
return new class extends Migration
{
    /** @var array<string, array{0: int, 1: int, 2: string}> coluna => [precisão, escala, comentário] */
    private array $colunas = [
        'peso_liquido' => [10, 3, 'Peso líquido em kg, sem embalagem'],
        'largura_cm' => [10, 2, 'Largura da embalagem em cm'],
        'altura_cm' => [10, 2, 'Altura da embalagem em cm'],
        'comprimento_cm' => [10, 2, 'Comprimento da embalagem em cm'],
    ];

    public function up(): void
    {
        foreach ($this->colunas as $coluna => [$precisao, $escala, $comentario]) {
            if (Schema::hasColumn('products', $coluna)) {
                continue;
            }

            Schema::table('products', function (Blueprint $table) use ($coluna, $precisao, $escala, $comentario) {
                $table->decimal($coluna, $precisao, $escala)->nullable()->comment($comentario);
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->colunas) as $coluna) {
            if (! Schema::hasColumn('products', $coluna)) {
                continue;
            }

            Schema::table('products', function (Blueprint $table) use ($coluna) {
                $table->dropColumn($coluna);
            });
        }
    }
};
