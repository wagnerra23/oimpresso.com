<?php

namespace Modules\Associacoes\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Documento da associação (assoc_documentos): estatuto, ata da diretoria, certidão. Não se apaga:
 * sai da lista com `removido_em` + motivo (ADR 0451 D3). O arquivo mora no Modules/Arquivos.
 *
 * @property int $id
 * @property int $business_id
 * @property int $associacao_id
 * @property string $tipo
 * @property string|null $descricao
 * @property int|null $arquivo_id
 * @property \Illuminate\Support\Carbon|null $validade
 * @property \Illuminate\Support\Carbon $enviado_em
 * @property \Illuminate\Support\Carbon|null $removido_em
 * @property string|null $removido_motivo
 */
class DocumentoAssociacao extends Model
{
    use HasBusinessScope;

    public const TIPOS = ['estatuto', 'ata_diretoria', 'certidao', 'outro'];

    protected $table = 'assoc_documentos';

    protected $fillable = [
        'business_id', 'associacao_id', 'tipo', 'descricao', 'arquivo_id', 'validade',
        'enviado_em', 'enviado_por', 'removido_em', 'removido_motivo', 'removido_por',
    ];

    protected $casts = [
        'validade' => 'date',
        'enviado_em' => 'datetime',
        'removido_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(static fn () => throw new LogicException('Documento da associação não se apaga: preencha removido_em e removido_motivo.'));
    }

    public function associacao(): BelongsTo
    {
        return $this->belongsTo(Associacao::class, 'associacao_id');
    }
}
