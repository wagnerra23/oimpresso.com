<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item de NF-e recebida (parsed do XML).
 *
 * Sucessor canônico de App\ItemDfe (legacy órfão removido).
 */
class NfeDfeItem extends Model
{
    use HasBusinessScope;

    protected $table = 'nfe_dfe_itens';

    protected $fillable = [
        'business_id',
        'dfe_recebido_id',
        'codigo_fornecedor',
        'ean',
        'unidade',
        'ncm',
        'cfop',
        'cst_csosn',
        'descricao',
        'quantidade',
        'valor_unitario',
        'valor_total',
        'icms_valor',
        'variation_id',
    ];

    protected $casts = [
        'quantidade'     => 'decimal:4',
        'valor_unitario' => 'decimal:4',
        'valor_total'    => 'decimal:2',
        'icms_valor'     => 'decimal:2',
    ];

    /**
     * Grava os itens lidos por LeitorNfeEntrada. Um lugar só para a DF-e por NSU e para o
     * upload (ADR entrada por XML, fase 2).
     *
     * @param  list<array<string, mixed>>  $itens
     */
    public static function gravarDaNota(int $businessId, int $dfeRecebidoId, array $itens): void
    {
        foreach ($itens as $item) {
            static::create([
                'business_id'       => $businessId,
                'dfe_recebido_id'   => $dfeRecebidoId,
                'codigo_fornecedor' => $item['codigo_fornecedor'] ?? null,
                'ean'               => $item['ean'] ?? null,
                'unidade'           => $item['unidade'] ?? null,
                'ncm'               => $item['ncm'] ?? null,
                'cfop'              => $item['cfop'] ?? null,
                'cst_csosn'         => $item['cst_csosn'] ?? null,
                'descricao'         => $item['descricao'] ?? '',
                'quantidade'        => $item['quantidade'] ?? 0,
                'valor_unitario'    => $item['valor_unitario'] ?? 0,
                'valor_total'       => $item['valor_total'] ?? 0,
                'icms_valor'        => $item['icms_valor'] ?? null,
            ]);
        }
    }

    public function dfeRecebido(): BelongsTo
    {
        return $this->belongsTo(NfeDfeRecebido::class, 'dfe_recebido_id');
    }
}
