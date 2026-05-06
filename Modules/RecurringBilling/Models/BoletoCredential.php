<?php

namespace Modules\RecurringBilling\Models;

use Illuminate\Database\Eloquent\Model;

class BoletoCredential extends Model
{
    protected $table = 'rb_boleto_credentials';

    protected $fillable = [
        'business_id',
        'banco',       // inter | c6 | asaas
        'ambiente',    // production | sandbox
        'ativo',
        'config_json', // JSON criptografado (client_id, client_secret, caminhos cert, etc.)
        'nome_display',
    ];

    protected $casts = [
        'config_json' => 'array',
        'ativo'       => 'boolean',
    ];
}
