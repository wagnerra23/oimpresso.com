<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Sugestão da Jana na tributação (playbook Fiscal thread 10 · D-IA). Nasce `pendente` e só uma
 * pessoa com `nfe.tributacao.manage` a aplica — ver `SugestaoFiscalService`.
 *
 * @property int $id
 * @property int $business_id
 * @property string $tipo
 * @property string $alvo_tipo
 * @property int $alvo_id
 * @property array<string, mixed> $valor_sugerido
 * @property float $confianca
 * @property string $risco
 * @property string $motivo
 * @property string $status
 * @property int|null $decidido_por
 * @property \Illuminate\Support\Carbon|null $decidido_em
 * @property bool $confirmou_leitura
 */
class NfeSugestaoFiscal extends Model
{
    use HasBusinessScope;

    public const TIPOS = ['ncm', 'natureza', 'regra', 'inconsistencia'];

    public const RISCOS = ['baixo', 'medio', 'alto'];

    protected $table = 'nfe_sugestoes_fiscais';

    protected $fillable = [
        'business_id', 'tipo', 'alvo_tipo', 'alvo_id', 'valor_sugerido', 'confianca', 'risco',
        'motivo', 'status', 'decidido_por', 'decidido_em', 'confirmou_leitura',
    ];

    protected $casts = [
        'valor_sugerido'    => 'array',
        'confianca'         => 'float',
        'confirmou_leitura' => 'boolean',
        'decidido_em'       => 'datetime',
    ];
}
