<?php

namespace Modules\Essentials\Entities\Folha;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rubrica da folha CLT, por business (ADR-mãe da folha §2.7).
 *
 * A rubrica não carrega incidência: incidência muda por lei sem a rubrica mudar de identidade,
 * então mora na vigência (§2.3). Pergunte sempre "desta rubrica, na competência X".
 */
class FolhaRubrica extends Model
{
    use HasBusinessScope;

    public const SENTIDOS = ['provento', 'desconto', 'base', 'informativa'];

    protected $table = 'essentials_folha_rubricas';

    protected $fillable = ['business_id', 'codigo', 'descricao', 'natureza', 'sentido'];

    public function vigencias(): HasMany
    {
        return $this->hasMany(FolhaRubricaVigencia::class, 'rubrica_id');
    }

    /** A vigência que vale na competência AAAA-MM, ou null se a rubrica não vigora nela. */
    public function vigenciaEm(string $competencia): ?FolhaRubricaVigencia
    {
        FolhaCompetencia::validar($competencia, 'competencia');

        return $this->vigencias()
            ->where('vigencia_inicio', '<=', $competencia)
            ->where(fn ($q) => $q->whereNull('vigencia_fim')->orWhere('vigencia_fim', '>=', $competencia))
            ->orderByDesc('vigencia_inicio')
            ->first();
    }
}
