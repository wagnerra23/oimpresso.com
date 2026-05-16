<?php

namespace Modules\Ponto\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Bridge entre users (UltimatePOS) e o domínio de Ponto.
 * Não é o User em si — é a configuração de ponto associada a ele.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093): trait HasBusinessScope
 * aplica global scope automático por business_id. Tabela tem coluna
 * business_id (vide migration 2026_04_18_000001).
 *
 * Wave 11 D7.b — LogsActivity Spatie pra audit trail CADASTRAL (não-marcação).
 * Cadastros podem ser alterados legitimamente (matrícula, escala, admissão) — Marcacao NÃO.
 * Complementa append-only de `ponto_marcacoes`. NUNCA logar `cpf`/`pis` brutos: trait
 * só registra `logOnly()` com lista explícita, e CPF/PIS são informações sensíveis que
 * precisam ficar disponíveis pro RH mas o histórico de MUDANÇA basta sem expor diff.
 */
class Colaborador extends Model
{
    use HasBusinessScope;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    /**
     * Wave 11 D7.b — audit trail cadastral LGPD.
     *
     * Loga apenas mudanças de:
     * - escala_atual_id (vincula colaborador a regime de trabalho)
     * - controla_ponto / usa_banco_horas (flags operacionais)
     * - admissao / desligamento (datas trabalhistas — eSocial relevância)
     *
     * NÃO loga: cpf, pis, matricula, metadata (LGPD minimização — registro de mudança
     * sem espelhar valor sensível em activity_log que tem visibilidade mais ampla).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'escala_atual_id',
                'controla_ponto',
                'usa_banco_horas',
                'admissao',
                'desligamento',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('ponto_colaborador');
    }

    protected $table = 'ponto_colaborador_config';

    protected $fillable = [
        'business_id',
        'user_id',
        'matricula',
        'pis',
        'cpf',
        'escala_atual_id',
        'controla_ponto',
        'usa_banco_horas',
        'admissao',
        'desligamento',
        'metadata',
    ];

    protected $casts = [
        'controla_ponto'   => 'boolean',
        'usa_banco_horas'  => 'boolean',
        'admissao'         => 'date',
        'desligamento'     => 'date',
        'metadata'         => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('pontowr2.ultimatepos.user_model'), 'user_id');
    }

    public function escalaAtual(): BelongsTo
    {
        return $this->belongsTo(Escala::class, 'escala_atual_id');
    }

    public function marcacoes(): HasMany
    {
        return $this->hasMany(Marcacao::class, 'colaborador_config_id');
    }

    public function intercorrencias(): HasMany
    {
        return $this->hasMany(Intercorrencia::class, 'colaborador_config_id');
    }

    public function apuracoes(): HasMany
    {
        return $this->hasMany(ApuracaoDia::class, 'colaborador_config_id');
    }

    public function bancoHorasSaldo()
    {
        return $this->hasOne(BancoHorasSaldo::class, 'colaborador_config_id');
    }

    public function bancoHorasMovimentos(): HasMany
    {
        return $this->hasMany(BancoHorasMovimento::class, 'colaborador_config_id');
    }
}
