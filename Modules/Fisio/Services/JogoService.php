<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Checkin;
use Modules\Fisio\Entities\Desafio;
use Modules\Fisio\Entities\GameLedger;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Entities\ProgramItem;

/**
 * Jogo de constância (US-FISIO-008, ADR 0437 §6 e §13.3) — a parte que toca o banco.
 *
 * Regra nenhuma mora aqui: as contas são dos calculadores puros (XpDoDiaCalculator,
 * NivelCalculator, SequenciaCalculator, LimiarDorCalculator). Este serviço lê check-in,
 * programa e ledger, chama as contas e grava no `fisio_game_ledger`.
 *
 * PONTO DE ENTRADA ÚNICO para lançar XP: o evento `Modules\Fisio\Events\CheckinRegistrado`,
 * ouvido por `Modules\Fisio\Listeners\LancarXpDoCheckin`, que chama registrarCheckin(). O
 * controller do check-in do portal dispara o evento depois de gravar (criar OU atualizar) o
 * check-in do dia; ninguém mais grava no ledger.
 *
 * Invariantes:
 *  - XP só pelo PRESCRITO: item fora do programa e vezes acima de `times_per_day` valem 0.
 *  - Ledger append-only (trigger MySQL + model): reenvio lança só a diferença positiva entre
 *    o alvo do dia e o já lançado; desmarcar não estorna. XP e nível são somas do ledger.
 *  - Dor ≥ limiar ou sinal de alerta PAUSA o jogo (estado()['em_pausa']) sem tirar XP nem
 *    quebrar a sequência; o registro do dia pontua sempre. A clínica vê o paciente na fila de
 *    atenção do painel pelo mesmo limiar (limiarDeDor()).
 *  - Missões e desafio semanal (T-08c) entram pela MESMA transação do check-in, só pelo
 *    prescrito (MissoesCalculator), até `teto_semanal_bonus` por semana e dentro do teto
 *    semanal. Recusar o desafio não lança nada nem tira nada. Medalhas são derivadas (0 XP).
 *  - Escopo: tudo filtrado pelo business_id do próprio check-in/paciente — no portal não há
 *    sessão de usuário e o global scope não age; o filtro explícito age sempre.
 */
final class JogoService
{
    /** Histórico lido para a sequência (corrente viva raramente passa disso na F1). */
    private const HISTORICO_DIAS = 400;

    public function __construct(
        private readonly XpDoDiaCalculator $xp,
        private readonly NivelCalculator $niveis,
        private readonly SequenciaCalculator $sequencia,
        private readonly LimiarDorCalculator $limiar,
        private readonly MissoesCalculator $missoes,
        private readonly MedalhasCalculator $medalhas,
    ) {}

    /**
     * Lança no ledger o XP do check-in. Idempotente: o mesmo relato de novo não lança nada.
     *
     * @param  array<int|string,int|string>  $vezes  item_id => vezes feitas hoje (item marcado
     *                                               como feito sem número = 1 vez)
     * @return array{exercicio_concluido:int, checkin:int} XP lançado AGORA, por razão
     */
    public function registrarCheckin(Checkin $checkin, array $vezes = []): array
    {
        $biz = (int) $checkin->business_id;
        $paciente = Patient::query()->where('business_id', $biz)->whereKey($checkin->patient_id)->first();
        if ($paciente === null || $paciente->blocked_at !== null) {
            return [XpDoDiaCalculator::RAZAO_EXERCICIO => 0, XpDoDiaCalculator::RAZAO_CHECKIN => 0];
        }

        $dia = $checkin->date->format('Y-m-d');
        $alvo = $this->xp->calcular(
            $this->prescritos($biz, $checkin->program_id),
            $this->vezesFeitas((array) ($checkin->done_item_ids ?? []), $vezes),
            (int) $paciente->sessions_per_week,
            (int) config('fisio.jogo.xp.exercicio_concluido', 70),
            (int) config('fisio.jogo.xp.checkin', 30),
            (int) config('fisio.jogo.normalizacao_dias_base', 4),
        );

        return DB::transaction(function () use ($checkin, $biz, $paciente, $dia, $alvo, $vezes): array {
            // Serializa reenvios simultâneos do mesmo dia: quem chega depois espera e vê o lançado.
            Checkin::query()->where('business_id', $biz)->whereKey($checkin->id)->lockForUpdate()->first();

            $noDia = [];
            $linhas = GameLedger::query()->where('business_id', $biz)->where('patient_id', $paciente->id)
                ->whereDate('date', $dia)->groupBy('reason')->selectRaw('reason, SUM(xp) as total')->get();
            foreach ($linhas as $l) {
                $noDia[(string) $l->reason] = (int) $l->getAttribute('total');
            }
            [$seg, $dom] = $this->semana($dia);
            $naSemana = (int) GameLedger::query()->where('business_id', $biz)->where('patient_id', $paciente->id)
                ->whereBetween('date', [$seg, $dom])->sum('xp');

            $lancar = $this->xp->aLancar($alvo, $noDia, $naSemana, (int) config('fisio.jogo.teto_semanal', 500));
            foreach ($lancar as $razao => $xp) {
                if ($xp > 0) {
                    GameLedger::query()->create([
                        'business_id' => $biz,
                        'patient_id' => (int) $paciente->id,
                        'date' => $dia,
                        'reason' => $razao,
                        'xp' => $xp,
                    ]);
                }
            }

            $this->lancarBonus($checkin, $biz, (int) $paciente->id, $dia, $seg, $dom, $vezes);

            return [
                XpDoDiaCalculator::RAZAO_EXERCICIO => $lancar[XpDoDiaCalculator::RAZAO_EXERCICIO],
                XpDoDiaCalculator::RAZAO_CHECKIN => $lancar[XpDoDiaCalculator::RAZAO_CHECKIN],
            ];
        });
    }

    /**
     * Missões cumpridas no dia e desafio da semana batido. Cada um lança uma vez (pelo `ref`);
     * o XP é o configurado, limitado ao que sobra do bônus e do teto da semana. Pode ser 0: a
     * linha fica como registro de que a missão foi cumprida.
     *
     * @param  array<int|string,int|string>  $vezes
     */
    private function lancarBonus(Checkin $checkin, int $biz, int $pacienteId, string $dia, string $seg, string $dom, array $vezes): void
    {
        $semana = GameLedger::query()->where('business_id', $biz)->where('patient_id', $pacienteId)->whereBetween('date', [$seg, $dom]);
        $total = (int) (clone $semana)->sum('xp');
        $bonus = (int) (clone $semana)->whereIn('reason', [GameLedger::REASON_MISSAO, GameLedger::REASON_DESAFIO])->sum('xp');
        $tetoBonus = (int) config('fisio.jogo.teto_semanal_bonus', 100);
        $teto = (int) config('fisio.jogo.teto_semanal', 500);
        $lancar = function (string $razao, string $ref, int $xp) use ($biz, $pacienteId, $dia, &$total, &$bonus, $tetoBonus, $teto): void {
            $valor = max(0, min($xp, $tetoBonus - $bonus, $teto - $total));
            GameLedger::query()->create(['business_id' => $biz, 'patient_id' => $pacienteId, 'date' => $dia, 'reason' => $razao, 'ref' => $ref, 'xp' => $valor]);
            $total += $valor;
            $bonus += $valor;
        };

        $xpMissao = (int) config('fisio.jogo.xp.missao', 10);
        if ($xpMissao > 0) {
            $prescritos = $this->prescritosComNome($biz, $checkin->program_id);
            $feitas = $this->vezesFeitas((array) ($checkin->done_item_ids ?? []), $vezes);
            $vezesPrescritas = array_column($prescritos, 'times_per_day', 'id');
            $jaFeitas = $this->missoesDoDia($biz, $pacienteId, $dia);
            foreach ($this->missoes->doDia($prescritos, self::semente($biz, $pacienteId, $dia)) as $m) {
                if (! in_array($m['chave'], $jaFeitas, true) && $this->missoes->cumprida($m, true, $feitas, $vezesPrescritas)) {
                    $lancar(GameLedger::REASON_MISSAO, $m['chave'], $xpMissao);
                }
            }
        }

        $desafio = $this->desafioDaSemana($biz, $pacienteId, $seg);
        if ($desafio !== null && $desafio->accepted && ! $this->desafioPago($biz, $pacienteId, $seg)
            && $this->diasCumpridosNaSemana($biz, $pacienteId, $seg, $dom) >= (int) $desafio->target_days) {
            $lancar(GameLedger::REASON_DESAFIO, $seg, (int) config('fisio.jogo.xp.desafio_semanal', 40));
        }
    }

    /**
     * Missões de hoje, desafio da semana e medalhas, para o portal. Sem ranking.
     *
     * @return array{missoes: list<array{chave:string, texto:string, cumprida:bool}>, desafio: array{oferecido:bool, decidido:bool, aceito:bool, meta:?int, opcoes:list<int>, cumpridos:int, pago:bool}, medalhas: list<array{codigo:string, nome:string}>}
     */
    public function constancia(Patient $p, ?DateTimeInterface $agora = null): array
    {
        $biz = (int) $p->business_id;
        $pid = (int) $p->id;
        $hoje = DiaLocal::de($agora ?? now());
        [$seg, $dom] = $this->semana($hoje);
        $programa = $this->programaAtivo($p);
        $feitas = $this->missoesDoDia($biz, $pid, $hoje);
        $missoes = [];
        foreach ($this->missoes->doDia($this->prescritosComNome($biz, $programa?->id), self::semente($biz, $pid, $hoje)) as $m) {
            $missoes[] = ['chave' => $m['chave'], 'texto' => $m['texto'], 'cumprida' => in_array($m['chave'], $feitas, true)];
        }

        $desafio = $this->desafioDaSemana($biz, $pid, $seg);
        $n = (int) $p->sessions_per_week;
        $checkins = $this->checkinsDoPaciente($p, DiaLocal::somar($hoje, -self::HISTORICO_DIAS), $hoje);
        $medalhas = $this->medalhas->ganhas($checkins, $n, $this->limiarDeDor($programa, $checkins), $this->nivel($p));

        return [
            'missoes' => $missoes,
            // Sem data no payload: o portal não carrega datas do histórico (UC-FPP-20).
            'desafio' => [
                // Oferta só na segunda (etapa 02 §3.4); a decisão vale a semana toda.
                'oferecido' => $desafio === null && $hoje === $seg && $programa !== null,
                'decidido' => $desafio !== null,
                'aceito' => (bool) ($desafio?->accepted ?? false),
                'meta' => $desafio?->target_days !== null ? (int) $desafio->target_days : null,
                'opcoes' => self::opcoesDeMeta($n),
                'cumpridos' => $this->diasCumpridosNaSemana($biz, $pid, $seg, $dom),
                'pago' => $this->desafioPago($biz, $pid, $seg),
            ],
            'medalhas' => array_map(fn (string $c) => ['codigo' => $c, 'nome' => MedalhasCalculator::MEDALHAS[$c]], $medalhas),
        ];
    }

    /**
     * Aceitar (com meta n−1 ou n) ou recusar o desafio desta semana. Só na segunda e uma decisão
     * por semana. Recusar grava a recusa e não toca no ledger.
     *
     * @return bool false quando não há oferta hoje, a meta não é das opções, ou a semana já foi decidida
     */
    public function decidirDesafio(Patient $p, bool $aceitar, ?int $meta, ?DateTimeInterface $agora = null): bool
    {
        $biz = (int) $p->business_id;
        $hoje = DiaLocal::de($agora ?? now());
        [$seg] = $this->semana($hoje);
        if ($hoje !== $seg || $this->programaAtivo($p) === null || $this->desafioDaSemana($biz, (int) $p->id, $seg) !== null) {
            return false;
        }
        if ($aceitar && ! in_array($meta, self::opcoesDeMeta((int) $p->sessions_per_week), true)) {
            return false;
        }
        Desafio::query()->create([
            'business_id' => $biz,
            'patient_id' => (int) $p->id,
            'week_start' => $seg,
            'accepted' => $aceitar,
            'target_days' => $aceitar ? $meta : null,
        ]);

        return true;
    }

    /** @return list<int> "Cumprir n−1 ou n dos n dias" (com meta 1, só 1) */
    public static function opcoesDeMeta(int $n): array
    {
        return array_values(array_unique(array_filter([$n - 1, $n], fn (int $v) => $v >= 1)));
    }

    private static function semente(int $biz, int $pacienteId, string $dia): string
    {
        return "{$biz}:{$pacienteId}:{$dia}";
    }

    /** @return list<string> chaves das missões já lançadas no dia */
    private function missoesDoDia(int $biz, int $pacienteId, string $dia): array
    {
        return GameLedger::query()->where('business_id', $biz)->where('patient_id', $pacienteId)->whereDate('date', $dia)
            ->where('reason', GameLedger::REASON_MISSAO)->pluck('ref')->map(fn ($r) => (string) $r)->all();
    }

    private function desafioDaSemana(int $biz, int $pacienteId, string $seg): ?Desafio
    {
        return Desafio::query()->where('business_id', $biz)->where('patient_id', $pacienteId)->whereDate('week_start', $seg)->first();
    }

    private function desafioPago(int $biz, int $pacienteId, string $seg): bool
    {
        return GameLedger::query()->where('business_id', $biz)->where('patient_id', $pacienteId)
            ->where('reason', GameLedger::REASON_DESAFIO)->where('ref', $seg)->exists();
    }

    /** Dias da semana que somam (exercício feito ou versão de crise), como na sequência. */
    private function diasCumpridosNaSemana(int $biz, int $pacienteId, string $seg, string $dom): int
    {
        $n = 0;
        foreach (Checkin::query()->where('business_id', $biz)->where('patient_id', $pacienteId)->whereBetween('date', [$seg, $dom])->get() as $c) {
            $calc = self::paraCalculo($c);
            $n += ($calc['done_count'] >= 1 || $calc['crise']) ? 1 : 0;
        }

        return $n;
    }

    /** @return list<array{id:int, nome:string, times_per_day:int}> */
    private function prescritosComNome(int $biz, ?int $programId): array
    {
        if ($programId === null) {
            return [];
        }
        $out = [];
        foreach (ProgramItem::query()->where('fisio_program_items.business_id', $biz)->where('fisio_program_items.program_id', $programId)
            ->join('fisio_exercises', 'fisio_exercises.id', '=', 'fisio_program_items.exercise_id')
            ->orderBy('fisio_program_items.position')->orderBy('fisio_program_items.id')->get(['fisio_program_items.id', 'fisio_program_items.times_per_day', 'fisio_exercises.name']) as $i) {
            $out[] = ['id' => (int) $i->id, 'nome' => (string) $i->getAttribute('name'), 'times_per_day' => (int) $i->times_per_day];
        }

        return $out;
    }

    /** XP total do paciente: soma do ledger (não existe coluna de XP). */
    public function xpTotal(Patient $p): int
    {
        return (int) GameLedger::query()->where('business_id', (int) $p->business_id)->where('patient_id', $p->id)->sum('xp');
    }

    public function nivel(Patient $p): int
    {
        return $this->niveis->nivel($this->xpTotal($p), self::tabelaDeNiveis());
    }

    /**
     * Estado do jogo para o portal. Sem ranking: o paciente contra ele mesmo (ADR 0437 §6).
     *
     * @return array{xp:int, nivel:int, xp_no_nivel:int, xp_para_proximo:?int, sequencia:int, em_pausa:bool, dias_cumpridos:int}
     */
    public function estado(Patient $p, ?DateTimeInterface $agora = null): array
    {
        $hoje = DiaLocal::de($agora ?? now());
        $checkins = $this->checkinsDoPaciente($p, DiaLocal::somar($hoje, -self::HISTORICO_DIAS), $hoje);
        $xp = $this->xpTotal($p);
        $prog = $this->niveis->progresso($xp, self::tabelaDeNiveis());
        $seq = $this->sequencia->calcular($checkins, (int) $p->sessions_per_week, $hoje, $this->limiarDeDor($this->programaAtivo($p), $checkins));

        return [
            'xp' => $xp,
            'nivel' => $prog['nivel'],
            'xp_no_nivel' => $prog['xp_no_nivel'],
            'xp_para_proximo' => $prog['xp_para_proximo'],
            'sequencia' => $seq['sequencia'],
            'em_pausa' => $seq['em_pausa'],
            // Total de dias cumpridos: nunca zera quando a sequência quebra (etapa 02 §3.4).
            'dias_cumpridos' => count(array_filter($checkins, fn (array $c) => $c['done_count'] >= 1 || $c['crise'])),
        ];
    }

    /**
     * Limiar de dor efetivo: o absoluto do programa, ou dor de base + margem quando o
     * profissional escolheu `sobre_basal` (protocolo 11). Sem programa ativo: o padrão.
     *
     * @param  list<array{date:string, pain:?int}>  $checkins  janela que cubra o início do programa
     */
    public function limiarDeDor(?Program $programa, array $checkins): int
    {
        if ($programa === null) {
            return (int) config('fisio.pain_alert_threshold_padrao', SequenciaCalculator::LIMIAR_DOR_PADRAO);
        }
        $cfg = (array) config('fisio.jogo.dor_de_base', []);
        $base = $programa->pain_alert_mode === LimiarDorCalculator::MODO_SOBRE_BASAL
            ? $this->limiar->base($checkins, self::inicioDoPrograma($programa), (int) ($cfg['janela_dias'] ?? 7), (int) ($cfg['minimo_registros'] ?? 3))
            : null;

        return $this->limiar->limiar((string) $programa->pain_alert_mode, $programa->limiarDeDor(), $base, (int) ($cfg['margem'] ?? 2));
    }

    /** @return array<int,int> nível => XP mínimo, de `config('fisio.jogo.niveis')` */
    public static function tabelaDeNiveis(): array
    {
        return array_map('intval', (array) config('fisio.jogo.niveis', [1 => 0]));
    }

    public static function inicioDoPrograma(Program $programa): string
    {
        return $programa->starts_at !== null
            ? $programa->starts_at->format('Y-m-d')
            : DiaLocal::de($programa->created_at ?? now());
    }

    /**
     * Check-in do banco no formato dos calculadores puros.
     *
     * @return array{date:string, done_count:int, pain:?int, red_flags:list<string>, crise:bool}
     */
    public static function paraCalculo(Checkin $c): array
    {
        return [
            'date' => $c->date->format('Y-m-d'),
            'done_count' => count(array_unique(array_map('intval', (array) ($c->done_item_ids ?? [])))),
            'pain' => $c->pain !== null ? (int) $c->pain : null,
            'red_flags' => array_values(array_map('strval', (array) ($c->red_flags ?? []))),
            'crise' => (bool) $c->crisis,
        ];
    }

    /** @return list<array{id:int, times_per_day:int}> */
    private function prescritos(int $biz, ?int $programId): array
    {
        if ($programId === null) {
            return [];
        }
        $out = [];
        foreach (ProgramItem::query()->where('business_id', $biz)->where('program_id', $programId)->get(['id', 'times_per_day']) as $i) {
            $out[] = ['id' => (int) $i->id, 'times_per_day' => (int) $i->times_per_day];
        }

        return $out;
    }

    /**
     * Vezes por item feito: o número informado, ou 1 quando o item foi marcado sem número.
     * Item não marcado como feito não conta, mesmo que venha número para ele.
     *
     * @param  array<int|string,mixed>  $feitos
     * @param  array<int|string,int|string>  $vezes
     * @return array<int,int>
     */
    private function vezesFeitas(array $feitos, array $vezes): array
    {
        $out = [];
        foreach (array_unique(array_map('intval', $feitos)) as $id) {
            $out[$id] = max(1, (int) ($vezes[$id] ?? $vezes[(string) $id] ?? 1));
        }

        return $out;
    }

    /** @return array{0:string, 1:string} segunda e domingo da semana do dia */
    private function semana(string $dia): array
    {
        $seg = DiaLocal::somar($dia, 1 - (int) DiaLocal::parse($dia)->format('N'));

        return [$seg, DiaLocal::somar($seg, 6)];
    }

    private function programaAtivo(Patient $p): ?Program
    {
        return Program::query()->where('business_id', (int) $p->business_id)->where('patient_id', $p->id)
            ->where('status', Program::STATUS_ATIVO)->orderByDesc('id')->first();
    }

    /** @return list<array{date:string, done_count:int, pain:?int, red_flags:list<string>, crise:bool}> */
    private function checkinsDoPaciente(Patient $p, string $de, string $ate): array
    {
        $out = [];
        foreach (Checkin::query()->where('business_id', (int) $p->business_id)->where('patient_id', $p->id)
            ->whereBetween('date', [$de, $ate])->orderBy('date')->get() as $c) {
            $out[] = self::paraCalculo($c);
        }

        return $out;
    }
}