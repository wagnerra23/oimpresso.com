<?php

namespace Modules\Essentials\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Competência da folha CLT, no formato AAAA-MM (ADR-mãe da folha §2.7).
 *
 * Também guarda a validação do formato, que as outras três entidades usam: competência é texto
 * `char(7)` e só ordena cronologicamente se tiver exatamente esse formato.
 *
 * @property int $id
 * @property int $business_id
 * @property string $competencia
 * @property string $estado
 */
class FolhaCompetencia extends Model
{
    use HasBusinessScope;

    public const ESTADO_ABERTA = 'aberta';
    public const ESTADO_FECHADA = 'fechada';

    protected $table = 'essentials_folha_competencias';

    protected $fillable = ['business_id', 'competencia', 'estado'];

    public static function validar(?string $competencia, string $campo): void
    {
        if ($competencia === null || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $competencia)) {
            throw new InvalidArgumentException("{$campo} deve estar no formato AAAA-MM; recebido '{$competencia}'.");
        }
    }

    protected static function booted(): void
    {
        static::saving(function (self $c) {
            self::validar($c->competencia, 'competencia');
        });
    }
}
