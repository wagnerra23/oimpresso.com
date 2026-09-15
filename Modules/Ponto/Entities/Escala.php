<?php

namespace Modules\Ponto\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Wave 12 — Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093).
 *
 * Tabela `ponto_escalas` tem coluna `business_id`. Trait `HasBusinessScope`
 * garante isolamento Model-level. Marcacao (append-only) NÃO recebe trait —
 * portaria 671/2021 protege via trigger MySQL diferente; Escala é dado cadastral
 * (Wave 11 D7.b LogsActivity já presente — agora Tier 0 também).
 */
class Escala extends Model
{
    use HasBusinessScope;
    use HasFactory;
    use LogsActivity;

    protected $table = 'ponto_escalas';

    /**
     * Wave 11 D7.b — audit trail LGPD pra escalas de trabalho.
     *
     * Escalas afetam jornada CLT (Art. 58, 59, 71) — mudanças precisam ser auditáveis
     * pro RH e pra fiscalização MTE. Não é append-only (escala pode ser corrigida),
     * mas TODO update vira histórico via spatie/activity_log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'nome',
                'codigo',
                'tipo',
                'carga_diaria_minutos',
                'carga_semanal_minutos',
                'permite_banco_horas',
                'ativo',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('ponto_escala');
    }

    protected $fillable = [
        'business_id',
        'nome',
        'codigo',
        'tipo',
        'carga_diaria_minutos',
        'carga_semanal_minutos',
        'permite_banco_horas',
        'dias_semana',
        'horarios_padrao',
        'ativo',
    ];

    protected $casts = [
        'dias_semana'         => 'array',
        'horarios_padrao'     => 'array',
        'permite_banco_horas' => 'boolean',
        'ativo'               => 'boolean',
    ];

    public const TIPO_FIXA         = 'FIXA';
    public const TIPO_FLEXIVEL     = 'FLEXIVEL';
    public const TIPO_ESCALA_12X36 = 'ESCALA_12X36';
    public const TIPO_ESCALA_6X1   = 'ESCALA_6X1';
    public const TIPO_ESCALA_5X2   = 'ESCALA_5X2';

    /**
     * US-PONTO-012 — generic anotado para o PHPStan enxergar `EscalaTurno` em vez de
     * `Model` genérico. Sem isto, `$escala->turnos->map(fn ($t) => $t->hora_entrada)`
     * vira "Access to an undefined property Model::$hora_entrada".
     *
     * ⚠️ E isso não é cosmético: os 4 erros equivalentes das leituras ANTIGAS
     * (`$t->entrada`, `$t->saida`, `$t->almoco_inicio`, `$t->almoco_fim`) estavam
     * SUPRIMIDOS no `phpstan-baseline.neon`. Ou seja — a análise estática tinha
     * apontado o atributo fantasma, e a supressão calou o aviso. Anotar o generic
     * remove as 4 entradas do baseline em vez de trocá-las por 4 novas.
     *
     * @return HasMany<EscalaTurno, $this>
     */
    public function turnos(): HasMany
    {
        return $this->hasMany(EscalaTurno::class, 'escala_id');
    }

    public function colaboradores(): HasMany
    {
        return $this->hasMany(Colaborador::class, 'escala_atual_id');
    }

    /**
     * Quantos colaboradores ainda usam esta escala.
     *
     * Existe como METODO, e nao inline no controller, por uma razao de teste: o controller roda
     * sob `HasBusinessScope` + sessao, o que amarra qualquer caso ao tenant do usuario logado. A
     * decisao em si nao depende de sessao nenhuma, e por isso ela mora aqui — onde da pra exercitar
     * num tenant ficticio, que e o que a doutrina de teste permite (proibicoes §5 2026-08-24:
     * fixture no tenant REAL e do seed, no ficticio e livre).
     */
    public function vinculosAtivos(): int
    {
        return $this->colaboradores()->count();
    }

    /**
     * D-ESC-DESTROY ([W] 2026-09-14): remover e permitido SO sem vinculo.
     *
     * Apagar escala em uso deixa `ponto_colaborador_config.escala_atual_id` apontando pra linha
     * morta — e a coluna NAO tem FK (migration 2026_04_18_000001, `unsigned()->nullable()` sem
     * `foreign()`), entao o banco nao segura nada. Escala e o que define a jornada ESPERADA na
     * apuracao: sem ela o calculo de HE e intrajornada perde a referencia (CLT Art. 58/59).
     * Integridade de dado com efeito em folha, nao UX.
     */
    public function podeSerRemovida(): bool
    {
        return $this->vinculosAtivos() === 0;
    }
}
