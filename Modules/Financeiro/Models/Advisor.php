<?php

namespace Modules\Financeiro\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Advisor — Contador parceiro (Onda 31 #57 US-FIN-037).
 *
 * Tabela GLOBAL (sem business_id) — contador é cross-tenant. Login isolado
 * via guard `web-advisor` (config/auth.php). Atende N businesses via grants
 * em `advisor_business_access`.
 *
 * Implementa Authenticatable manualmente — não estende App\User (mistura
 * acidental com user UltimatePOS quebraria isolamento Tier 0).
 *
 * PII: cnpj_contador NUNCA em log direto — usar accessor mascarado.
 */
class Advisor extends Model implements Authenticatable
{
    use SoftDeletes, AuthenticatableTrait;

    protected $table = 'advisors';

    protected $fillable = [
        'cnpj_contador', 'nome', 'email', 'password_hash',
        'telefone', 'referral_code', 'ativo', 'email_verified_at',
    ];

    protected $hidden = [
        'password_hash', 'remember_token',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    /**
     * Authenticatable: nome do campo de senha persistido.
     * Usamos `password_hash` em vez de `password` pra deixar explícito
     * que é hash bcrypt e não confundir com Eloquent default.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash ?? '';
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * Acessos ativos (não revogados).
     */
    public function accesses(): HasMany
    {
        return $this->hasMany(AdvisorBusinessAccess::class);
    }

    /**
     * Apenas acessos ATIVOS (revoked_at IS NULL, soft-deleted excluído).
     */
    public function activeAccesses(): HasMany
    {
        return $this->hasMany(AdvisorBusinessAccess::class)->whereNull('revoked_at');
    }

    /**
     * Gera referral_code único 8-chars upper alphanumeric.
     * Chamar antes do save() inicial.
     */
    public static function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
            $exists = static::where('referral_code', $code)->exists();
        } while ($exists);

        return $code;
    }

    /**
     * CNPJ mascarado pra log/UI (LGPD-safe).
     * Exemplo: 14 digitos "12345678000190" vira "12.345.xxx-0001-xx" (com x no lugar de digito sensivel).
     */
    public function getCnpjMaskedAttribute(): string
    {
        $cnpj = $this->cnpj_contador ?? '';
        if (strlen($cnpj) !== 14) {
            return '[REDACTED]';
        }
        return sprintf('%s.%s.***/%s-**',
            substr($cnpj, 0, 2),
            substr($cnpj, 2, 3),
            substr($cnpj, 8, 4)
        );
    }
}
