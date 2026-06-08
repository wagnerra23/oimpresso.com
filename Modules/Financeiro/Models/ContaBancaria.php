<?php

namespace Modules\Financeiro\Models;

use App\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Financeiro\Models\Concerns\BusinessScope;
use Modules\RecurringBilling\Models\BoletoCredential;
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
        'rb_gateway_credential_id', 'saldo_cached', 'saldo_atualizado_em', 'tipo_conta',
        // Legacy migration (ADR 0118) — preenchidos pelo importer Python /
        // Modules/MigrationFactory futuro. Null em contas cadastradas nativas.
        'legacy_source', 'legacy_id', 'legacy_imported_at',
    ];

    protected $casts = [
        'ativo_para_boleto'   => 'boolean',
        'metadata'            => 'array',
        'saldo_cached'        => 'float',
        'saldo_atualizado_em' => 'datetime',
        'legacy_imported_at'  => 'datetime',
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

    public function gatewayCredential(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BoletoCredential::class, 'rb_gateway_credential_id');
    }

    public function getBancoNomeAttribute(): string
    {
        return match ($this->banco_codigo) {
            '077'  => 'Banco Inter',
            '336'  => 'C6 Bank',
            '274'  => 'Asaas',
            '001'  => 'Banco do Brasil',
            '033'  => 'Santander',
            '104'  => 'Caixa Econômica',
            '341'  => 'Itaú',
            '237'  => 'Bradesco',
            default => 'Banco ' . $this->banco_codigo,
        };
    }

    public function getSaldoFormatadoAttribute(): ?string
    {
        if ($this->saldo_cached === null) {
            return null;
        }
        return 'R$ ' . number_format($this->saldo_cached, 2, ',', '.');
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
