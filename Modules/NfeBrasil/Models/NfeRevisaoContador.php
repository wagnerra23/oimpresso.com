<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Revisão do contador de uma versão de regra (playbook Fiscal thread 15 · D-CONTADOR).
 *
 * @property int $id
 * @property int $business_id
 * @property int $regra_id
 * @property int|null $regra_anterior_id
 * @property string $origem
 * @property int|null $autor_id
 * @property array<string, array{0: mixed, 1: mixed}> $diff
 * @property string $status
 * @property string|null $comentario
 * @property string|null $aceito_por_nome
 * @property string|null $aceito_por_email
 * @property string|null $aceito_por_crc
 * @property int|null $aceito_por_user_id
 * @property \Illuminate\Support\Carbon|null $aceito_em
 * @property string|null $aceito_ip
 */
class NfeRevisaoContador extends Model
{
    use HasBusinessScope;

    protected $table = 'nfe_revisoes_contador';

    protected $fillable = [
        'business_id', 'regra_id', 'regra_anterior_id', 'origem', 'autor_id', 'diff', 'status', 'comentario',
        'aceito_por_nome', 'aceito_por_email', 'aceito_por_crc', 'aceito_por_user_id', 'aceito_em', 'aceito_ip',
    ];

    protected $casts = [
        'diff'      => 'array',
        'aceito_em' => 'datetime',
    ];
}
