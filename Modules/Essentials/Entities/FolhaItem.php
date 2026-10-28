<?php

namespace Modules\Essentials\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Verba lançada na folha CLT (ADR-mãe da folha §2.5 e §2.7).
 *
 * Carrega DUAS competências: paga-se na de pagamento, calcula-se na alíquota e no teto da de
 * apuração. Num retroativo (dissídio de maio pago em outubro) elas diferem; um motor que as trate
 * como iguais acerta toda folha normal e erra o primeiro retroativo.
 *
 * Proveniência é obrigatória: ponto (com o id da apuração), manual (com o autor) ou motor (com a
 * versão da regra). Sem ela, conferir divergência com o contador vira arqueologia (§2.7).
 *
 * @property int $id
 * @property int $business_id
 * @property int $user_id
 * @property int $rubrica_id
 * @property string $valor
 * @property string $competencia_pagamento
 * @property string $competencia_apuracao
 * @property string $proveniencia
 * @property string|null $proveniencia_ref
 * @property-read FolhaRubrica|null $rubrica
 */
class FolhaItem extends Model
{
    use HasBusinessScope;

    public const PROVENIENCIAS = ['ponto', 'manual', 'motor'];

    protected $table = 'essentials_folha_itens';

    protected $fillable = [
        'business_id', 'user_id', 'rubrica_id', 'valor',
        'competencia_pagamento', 'competencia_apuracao', 'proveniencia', 'proveniencia_ref',
    ];

    protected $casts = ['valor' => 'decimal:4'];

    /** @return BelongsTo<FolhaRubrica, $this> */
    public function rubrica(): BelongsTo
    {
        return $this->belongsTo(FolhaRubrica::class, 'rubrica_id');
    }

    /** A vigência da rubrica na competência de APURAÇÃO, nunca na de pagamento (§2.5). */
    public function vigencia(): ?FolhaRubricaVigencia
    {
        return $this->rubrica?->vigenciaEm($this->competencia_apuracao);
    }

    protected static function booted(): void
    {
        static::saving(function (self $i) {
            FolhaCompetencia::validar($i->competencia_pagamento, 'competencia_pagamento');
            FolhaCompetencia::validar($i->competencia_apuracao, 'competencia_apuracao');
            if (! in_array($i->proveniencia, self::PROVENIENCIAS, true)) {
                throw new InvalidArgumentException("proveniencia inválida: '{$i->proveniencia}'.");
            }

            // Tier 0 (ADR 0093): a FK só prova que a rubrica existe, não que é da mesma empresa.
            $rubricaBiz = FolhaRubrica::withoutGlobalScopes() // SUPERADMIN: conferir o dono da rubrica, não listar
                ->whereKey($i->rubrica_id)->value('business_id');
            if ((int) $rubricaBiz !== (int) $i->business_id) {
                throw new InvalidArgumentException('A rubrica não pertence à empresa do item.');
            }
        });
    }
}
