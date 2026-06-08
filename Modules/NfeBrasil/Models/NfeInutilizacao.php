<?php

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Inutilização de range de numeração — fechamento legal de "lacuna" na sequência.
 *
 * Quando emite ex. notas 1, 2, 5 (perdeu 3 e 4), tem que inutilizar [3..4]
 * via processo SEFAZ. Sem isso, ano fiscal não fecha.
 *
 * Status: pendente → enviado → autorizado | rejeitado.
 */
class NfeInutilizacao extends Model
{
    use HasBusinessScope;
    use LogsActivity; // D7 LGPD — audit trail accountability Art. 37. PII fiscal preservada por exceção CONFAZ (PII-LGPD-FISCAL.md)

    protected $table = 'nfe_inutilizacoes';

    protected $fillable = [
        'business_id', 'modelo', 'serie',
        'numero_de', 'numero_ate',
        'justificativa',
        'status', 'cstat', 'autorizada_em', 'payload_json',
    ];

    protected $casts = [
        'numero_de'      => 'integer',
        'numero_ate'     => 'integer',
        'autorizada_em'  => 'datetime',
        'payload_json'   => 'array',
    ];

    public function quantidadeNumeros(): int
    {
        return ($this->numero_ate - $this->numero_de) + 1;
    }

    /**
     * D7 audit trail (Spatie\Activitylog) — accountability LGPD Art. 37.
     * Loga status/cstat/justificativa (range numérico já é payload curto, sem PII).
     * Ver memory/requisitos/NfeBrasil/PII-LGPD-FISCAL.md §3.1
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'cstat', 'numero_de', 'numero_ate', 'justificativa', 'autorizada_em'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('nfe_inutilizacao');
    }
}
