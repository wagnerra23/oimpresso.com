<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Contador da empresa (playbook Fiscal thread 15c · D-CONTADOR). Um por business.
 *
 * @property int $id
 * @property int $business_id
 * @property string $nome
 * @property string $email
 * @property string|null $crc
 * @property string|null $cpf  só dígitos; exigido pelo 0100 da EFD-Contribuições
 * @property int|null $atualizado_por
 */
class NfeContador extends Model
{
    use HasBusinessScope;

    protected $table = 'nfe_contadores';

    protected $fillable = ['business_id', 'nome', 'email', 'crc', 'cpf', 'atualizado_por'];
}
