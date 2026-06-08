<?php

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Certificado A1 (.pfx) por business — encrypted-at-rest.
 *
 * Senha NUNCA em texto: encrypted_password é Crypt::encryptString.
 * .pfx em disco também encrypted: storage/app/nfe-brasil/{biz}/cert/{uuid}.pfx.enc.
 *
 * Multi-tenant: queries DEVEM escopear por business_id (skill multi-tenant-patterns).
 *
 * Regra de negócio: apenas 1 cert ativo por business (CertificadoService::salvar
 * desativa anterior antes de criar novo). Sem unique constraint no DB porque
 * `ativo=false` históricos coexistem com `ativo=true` atual.
 */
class NfeCertificado extends Model
{
    use HasBusinessScope;

    use SoftDeletes;

    protected $table = 'nfe_certificados';

    protected $fillable = [
        'business_id', 'uuid', 'cnpj_titular',
        'valido_ate', 'encrypted_password', 'ativo',
    ];

    protected $casts = [
        'valido_ate' => 'date',
        'ativo'      => 'boolean',
    ];

    /**
     * Esconde a senha em qualquer serialização (JSON, log, audit).
     * Defesa em profundidade: além do Crypt::encryptString.
     */
    protected $hidden = ['encrypted_password'];

    public function scopeAtivos(Builder $q): Builder
    {
        return $q->where('ativo', true);
    }

    public function scopeDoBusinessAtual(Builder $q): Builder
    {
        return $q->where('business_id', session('business.id'));
    }

    public function diasAteVencimento(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->valido_ate, false);
    }

    public function isVencido(): bool
    {
        return $this->valido_ate->isPast();
    }
}
