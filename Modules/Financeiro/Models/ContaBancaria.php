<?php

namespace Modules\Financeiro\Models;

use App\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Financeiro\Models\Concerns\BusinessScope;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Complemento 1-1 da `App\Account` (core UltimatePOS).
 *
 * Larissa cadastra a conta no admin POS; quando precisa emitir boleto, vem
 * aqui preencher carteira/convênio/cedente/beneficiário. Sem duplicar dados
 * já em `accounts` (nome via $this->account->name; saldo via account_transactions).
 *
 * Decisão: ADR ARQ-0001 + ADR TECH-0003 (complemento 1-1, fork eduardokum).
 */
class ContaBancaria extends Model
{
    use HasFactory, SoftDeletes, BusinessScope, LogsActivity;

    protected $table = 'fin_contas_bancarias';

    protected $fillable = [
        'business_id', 'account_id', 'banco_codigo',
        'agencia', 'agencia_dv', 'conta_dv',
        'carteira', 'convenio', 'codigo_cedente', 'variacao_carteira',
        'beneficiario_documento', 'beneficiario_razao_social',
        'beneficiario_logradouro', 'beneficiario_bairro',
        'beneficiario_cidade', 'beneficiario_uf', 'beneficiario_cep',
        'certificado_path', 'certificado_password_encrypted',
        'ativo_para_boleto', 'metadata',
    ];

    protected $casts = [
        'ativo_para_boleto' => 'boolean',
        'metadata' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'banco_codigo', 'agencia', 'carteira', 'convenio',
                'codigo_cedente', 'beneficiario_documento', 'ativo_para_boleto',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('financeiro.conta_bancaria');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function baixas(): HasMany
    {
        return $this->hasMany(TituloBaixa::class, 'conta_bancaria_id');
    }

    public function boletoRemessas(): HasMany
    {
        return $this->hasMany(BoletoRemessa::class, 'conta_bancaria_id');
    }

    /**
     * Atalhos pra evitar acessar account em todo getter.
     */
    public function getNomeAttribute(): string
    {
        return $this->account?->name ?? '(conta sem account vinculada)';
    }

    public function getNumeroContaAttribute(): ?string
    {
        return $this->account?->account_number;
    }
}
