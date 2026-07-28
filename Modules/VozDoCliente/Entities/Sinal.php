<?php

namespace Modules\VozDoCliente\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Sinal — uma dor relatada por quem usa o sistema (ADR 0105 "cliente como sinal").
 *
 * @property int         $id
 * @property int         $business_id
 * @property int|null    $user_id
 * @property string|null $autor_nome
 * @property string      $canal
 * @property string      $texto
 * @property int|null    $severidade
 * @property string|null $url_vista
 * @property string|null $modulo_sugerido
 * @property string      $status
 * @property string|null $triado_para_us
 * @property int|null    $triado_por
 * @property string      $hash_origem
 *
 * Tier 0 ({@see ADR 0093}): global scope de `business_id` obrigatório. O scope
 * segue o padrão canônico ({@see Modules\Arquivos\Entities\Arquivo}) — quando
 * NÃO há business em sessão (CLI, job, superadmin) ele não filtra, e a proteção
 * passa a ser responsabilidade de quem chama. Por isso a ESCRITA nunca confia em
 * auto-fill: `SinalController` resolve o business explicitamente.
 *
 * Append-only na prática: `texto` é a prova do que a pessoa disse e não é
 * reescrito. A triagem só acrescenta (`status`, `triado_para_us`, `triado_em`).
 */
class Sinal extends Model
{
    protected $table = 'voz_sinais';

    public const UPDATED_AT = null;

    public const STATUS_PENDENTE = 'pending';

    public const STATUS_TRIADO = 'triaged';

    public const STATUS_FECHADO = 'closed';

    protected $fillable = [
        'business_id',
        'user_id',
        'autor_nome',
        'canal',
        'texto',
        'severidade',
        'url_vista',
        'modulo_sugerido',
        'status',
        'triado_para_us',
        'triado_por',
        'hash_origem',
        'triado_em',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'user_id'     => 'integer',
        'severidade'  => 'integer',
        'created_at'  => 'datetime',
        'triado_em'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('voz_sinais.business_id', $businessId);
            }
        });
    }

    /**
     * Dedup de reenvio idêntico: mesmo business + mesmo texto = mesmo sinal.
     * Normaliza espaço e caixa pra que "Tá lento" e "ta lento  " não virem dois.
     */
    public static function hashDe(int $businessId, string $texto): string
    {
        $normalizado = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $texto)));

        return hash('sha256', $businessId . '|' . $normalizado);
    }

    public function scopePendentes(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDENTE);
    }

    /**
     * Severidade que, por configuração, exige triagem ativa em vez de só
     * registro. Espelha o limiar da skill `feedback-capture` (>= 3 abre task).
     */
    public function exigeTriagem(): bool
    {
        return $this->severidade !== null
            && $this->severidade >= (int) config('vozdocliente.severidade_que_exige_triagem', 3);
    }
}
