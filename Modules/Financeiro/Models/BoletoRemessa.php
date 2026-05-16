<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Arquivos\Concerns\HasArquivos;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Financeiro\Models\Concerns\BusinessScope;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Boleto emitido por um Título. Estado independente do título: o título pode
 * estar "aberto" enquanto o boleto está "gerado", "pago", "cancelado", etc.
 */
class BoletoRemessa extends Model
{
    use HasFactory, HasArquivos, SoftDeletes, BusinessScope, LogsActivity;

    /**
     * D7.b Wave 14 — audit trail de mudanças de status do boleto (LGPD Art. 16
     * rastreabilidade pra compliance fiscal CTN Art. 195 — boletos compõem prova
     * de adimplência). NÃO loga linha_digitavel/codigo_barras integralmente em
     * audit (somente hash via metadata se vier a ser necessário); valor_total,
     * vencimento e status são suficientes pra trilha auditável.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'titulo_id', 'conta_bancaria_id',
                'valor_total', 'vencimento', 'status',
                'strategy', 'enviado_em', 'pago_em',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('financeiro.boleto_remessa');
    }
    // ADR 0123 Sprint 4 — adopcao trait. pdf_path coluna preservada
    // (double-write durante transicao US-RB-044 NFe-de-boleto-pago workflow).

    public const STATUS_GERADO_MOCK = 'gerado_mock';
    public const STATUS_GERADO = 'gerado';
    public const STATUS_ENVIADO = 'enviado';
    public const STATUS_REGISTRADO = 'registrado';
    public const STATUS_PAGO = 'pago';
    public const STATUS_VENCIDO = 'vencido';
    public const STATUS_CANCELADO = 'cancelado';

    public const STRATEGY_CNAB_DIRECT = 'cnab_direct';
    public const STRATEGY_GATEWAY = 'gateway';
    public const STRATEGY_HYBRID = 'hybrid';

    protected $table = 'fin_boleto_remessas';

    protected $fillable = [
        'business_id', 'titulo_id', 'conta_bancaria_id',
        'nosso_numero', 'linha_digitavel', 'codigo_barras',
        'valor_total', 'vencimento',
        'status', 'pdf_path', 'enviado_em', 'pago_em',
        'strategy', 'idempotency_key', 'metadata',
    ];

    protected $casts = [
        'valor_total' => 'decimal:4',
        'vencimento' => 'date',
        'enviado_em' => 'datetime',
        'pago_em' => 'datetime',
        'metadata' => 'array',
    ];

    public function titulo(): BelongsTo
    {
        return $this->belongsTo(Titulo::class, 'titulo_id');
    }

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class, 'conta_bancaria_id');
    }

    /**
     * Accessor — Arquivo PDF do boleto via backbone Modules/Arquivos (ADR 0123).
     *
     * Sprint 4 US-RB-044 (NFe-de-boleto-pago): TituloService gera PDF dinamicamente
     * via lib laravel-boleto. Após geração, chama $boleto->attachArquivo($pdfFile,
     * ['context' => 'fin-boleto-pdf']) ALÉM de salvar pdf_path coluna legacy
     * (double-write durante transição).
     *
     * Sub_destination convencional: 'fin-boleto-pdf'.
     */
    public function getPdfArquivoAttribute(): ?Arquivo
    {
        if (! method_exists($this, 'arquivos')) return null;
        return $this->arquivos()
            ->where('sub_destination', 'fin-boleto-pdf')
            ->where('bucket', 'active')
            ->latest('created_at')
            ->first();
    }
}
