<?php

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Arquivos\Concerns\HasArquivos;
use Modules\Arquivos\Entities\Arquivo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Emissão fiscal — NFe (55), NFC-e (65), CT-e (67).
 *
 * Idempotência por (business_id, transaction_id): re-emitir mesma venda
 * UPos = no-op. Sequência única por (business_id, modelo, serie, numero) —
 * regra fiscal (não pode haver lacuna sem inutilização registrada).
 *
 * Status canônicos:
 *   pendente     — aguardando envio SEFAZ
 *   autorizada   — SEFAZ retornou cstat=100 (NF-e) ou 104 (NFC-e)
 *   rejeitada    — cstat 200..600 (erro de validação) — pode retentar
 *   denegada     — cstat 110, 301, 302, 205 — emitente irregular, NÃO emite
 *   cancelada    — evento 110111 com cstat=135
 *   inutilizada  — número pulado via processo de inutilização
 */
class NfeEmissao extends Model
{
    use HasBusinessScope;
    use HasArquivos; // ADR 0123 — adopcao trait Sprint 3 US-ARQ-019
    use LogsActivity; // D7 LGPD — audit trail accountability Art. 37. PII fiscal preservada por exceção CONFAZ (PII-LGPD-FISCAL.md)
    use SoftDeletes;

    protected $table = 'nfe_emissoes';

    protected $fillable = [
        'business_id', 'transaction_id',
        'modelo', 'serie', 'numero', 'chave_44',
        'status', 'cstat', 'motivo',
        'xml_path', 'danfe_path',
        'valor_total', 'emitido_em', 'metadata',
    ];

    protected $casts = [
        'numero'      => 'integer',
        'valor_total' => 'decimal:2',
        'emitido_em'  => 'datetime',
        'metadata'    => 'array',
    ];

    public function eventos(): HasMany
    {
        return $this->hasMany(NfeEvento::class, 'emissao_id');
    }

    /**
     * D7 audit trail (Spatie\Activitylog) — accountability LGPD Art. 37.
     * Loga apenas status/cstat/motivo/numero/chave_44 (sem XML body — XML fica em arquivos table).
     * Ver memory/requisitos/NfeBrasil/PII-LGPD-FISCAL.md §3.1
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'cstat', 'motivo', 'numero', 'chave_44', 'emitido_em'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('nfe_emissao');
    }

    public function scopeAutorizadas(Builder $q): Builder
    {
        return $q->where('status', 'autorizada');
    }

    public function scopeDoBusinessAtual(Builder $q): Builder
    {
        return $q->where('business_id', session('business.id'));
    }

    public function isAutorizada(): bool
    {
        return $this->status === 'autorizada';
    }

    public function isCancelavel(): bool
    {
        if ($this->status !== 'autorizada') return false;

        // NFC-e: 24h. NFe: 168h (7 dias). Após prazo, só carta de correção.
        $prazoHoras = $this->modelo === '65' ? 24 : 168;
        return $this->emitido_em
            && $this->emitido_em->diffInHours(now()) <= $prazoHoras;
    }

    /**
     * Accessor — retorna Arquivo XML preferindo arquivos table (ADR 0123),
     * fallback null se ainda usando coluna legacy xml_path.
     *
     * Sprint 3 US-ARQ-019. Migration backfill US-ARQ-020 popula arquivos rows
     * pra xml_path existentes. Após estabilização, US-ARQ-021 remove coluna
     * legacy.
     *
     * Uso:
     *   $emissao->xml_arquivo  // ?Arquivo
     *   $emissao->xml_arquivo?->signedUrl()  // download URL temporario
     */
    public function getXmlArquivoAttribute(): ?Arquivo
    {
        if (! method_exists($this, 'arquivos')) return null;
        return $this->arquivos()
            ->where('sub_destination', 'nfe-xml')
            ->where('bucket', 'active')
            ->latest('created_at')
            ->first();
    }

    /**
     * Accessor — retorna Arquivo DANFE (PDF). Idem xml_arquivo.
     */
    public function getDanfeArquivoAttribute(): ?Arquivo
    {
        if (! method_exists($this, 'arquivos')) return null;
        return $this->arquivos()
            ->where('sub_destination', 'nfe-danfe')
            ->where('bucket', 'active')
            ->latest('created_at')
            ->first();
    }
}
