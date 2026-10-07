<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Link de revisão do contador (playbook Fiscal thread 15b · D-CONTADOR, caminho 1).
 *
 * A página do link é pública (sem login), então o escopo global de business não filtra nada lá:
 * o controller do link busca SEMPRE pelo `business_id` do próprio link.
 *
 * @property int $id
 * @property int $business_id
 * @property string $nome
 * @property string $email
 * @property string|null $crc
 * @property string|null $codigo_hash
 * @property \Illuminate\Support\Carbon|null $codigo_expira_em
 * @property int $tentativas
 * @property \Illuminate\Support\Carbon|null $bloqueado_em
 * @property \Illuminate\Support\Carbon $expira_em
 * @property int|null $criado_por
 */
class NfeContadorLink extends Model
{
    use HasBusinessScope;

    /** Erros de código que bloqueiam o link. */
    public const MAX_TENTATIVAS = 5;

    protected $table = 'nfe_contador_links';

    protected $fillable = [
        'business_id', 'nome', 'email', 'crc', 'codigo_hash', 'codigo_expira_em',
        'tentativas', 'bloqueado_em', 'expira_em', 'criado_por',
    ];

    protected $hidden = ['codigo_hash'];

    protected $casts = [
        'codigo_expira_em' => 'datetime',
        'bloqueado_em'     => 'datetime',
        'expira_em'        => 'datetime',
        'tentativas'       => 'integer',
    ];

    public function aberto(): bool
    {
        return $this->bloqueado_em === null && $this->expira_em->isFuture();
    }
}
