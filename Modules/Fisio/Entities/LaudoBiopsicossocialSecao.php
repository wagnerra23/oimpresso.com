<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;

/**
 * `fisio_laudos_bps_secoes` — uma seção do laudo biopsicossocial, com autor e assinatura
 * próprios. Escopo por empresa (ADR 0093).
 *
 * Rascunho (`registrado_em` nulo) pode ser editado. Seção registrada não aceita UPDATE nem
 * DELETE — aqui no model e no banco (trigger da migration). Correção é seção nova com
 * `amends_id` apontando a original, que fica intacta.
 *
 * @property int $id
 * @property int $business_id
 * @property int $laudo_id
 * @property string $item
 * @property ?string $texto
 * @property ?string $autor_nome
 * @property ?string $conselho
 * @property ?string $registro_conselho
 * @property string $assinatura
 * @property ?int $registrado_por
 * @property ?\Illuminate\Support\Carbon $registrado_em
 * @property ?int $amends_id
 */
class LaudoBiopsicossocialSecao extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    /** Os quatro itens do art. 2º, §1º, da LBI, na ordem da lei. */
    public const ITENS = ['impedimentos', 'atividades', 'fatores', 'participacao'];

    /** Conselho que costuma escrever cada item. É sugestão para a tela, não restrição. */
    public const CONSELHO_SUGERIDO = [
        'impedimentos' => 'CRM',
        'atividades' => 'CREFITO',
        'fatores' => 'CRP',
        'participacao' => 'CRESS',
    ];

    public const CONSELHOS = ['CRM', 'CREFITO', 'CRP', 'CRESS'];

    public const ASSINATURA_SISTEMA = 'sistema';

    public const ASSINATURA_EXTERNA = 'externa';

    protected $table = 'fisio_laudos_bps_secoes';

    protected $guarded = ['id'];

    protected $casts = [
        'registrado_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $secao): void {
            if ($secao->getOriginal('registrado_em') !== null) {
                throw DadoClinicoImutavelException::alteracao($secao->getTable());
            }
        });
    }

    public function estaRegistrada(): bool
    {
        return $this->registrado_em !== null;
    }

    /** @return BelongsTo<LaudoBiopsicossocial, $this> */
    public function laudo(): BelongsTo
    {
        return $this->belongsTo(LaudoBiopsicossocial::class, 'laudo_id');
    }

    /** Seção que esta corrige. @return BelongsTo<LaudoBiopsicossocialSecao, $this> */
    public function amends(): BelongsTo
    {
        return $this->belongsTo(self::class, 'amends_id');
    }
}
