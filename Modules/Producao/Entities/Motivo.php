<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_motivos` — motivos de pausa e de refugo por posto (UC-PCV-33, E03). Cadastros do PCP (ADR 0431, thread 03). Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property int $posto_id
 * @property string $tipo
 * @property string $nome
 * @property int $ordem
 * @property bool $ativo
 */
class Motivo extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_motivos';

    protected $guarded = ['id'];

    protected $casts = ['ativo' => 'boolean'];

    public const PAUSA = 'pausa';

    public const REFUGO = 'refugo';

    /** Padrão sugerido ao criar o posto (E03); o gestor edita a lista do posto. */
    public const PADRAO = [
        self::PAUSA => ['Troca de bobina', 'Setup', 'Falta de material', 'Manutenção', 'Dúvida na arte', 'Intervalo'],
        self::REFUGO => ['Defeito de mídia', 'Cor', 'Cabeça', 'Corte', 'Arte'],
    ];
}
