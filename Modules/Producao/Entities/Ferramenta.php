<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_ferramentas` — ferramenta com código QR (E03). Cadastros do PCP (ADR 0431, thread 03). Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property string $codigo_qr
 * @property string $nome
 * @property bool $ativo
 */
class Ferramenta extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_ferramentas';

    protected $guarded = ['id'];

    protected $casts = ['ativo' => 'boolean'];
}
