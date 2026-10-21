<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use DateTimeInterface;
use Modules\Fisio\Entities\Checkin;
use Modules\Fisio\Entities\GameLedger;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Program;

/**
 * Painel do profissional (US-FISIO-010): lê o banco da empresa da sessão e entrega as contas
 * aos calculadores puros (AdesaoCalculator, AtencaoDetector, NivelCalculator, SequenciaCalculator).
 * Nenhuma regra de adesão, atenção, nível ou sequência mora aqui — só a leitura e a montagem.
 *
 * Escopo: todo model usa HasBusinessScope; as consultas não recebem business_id do request.
 *
 * Só SINALIZA para o profissional (ADR 0437 §5): não sugere conduta, não muda programa e não
 * fala com o paciente. A série de dor sai só por aqui, na visão do profissional (ADR 0437 §5,
 * P11 da Anvisa) — o portal não a recebe.
 */
final class PainelFisio
{
    /** Histórico lido para a sequência (corrente viva raramente passa disso na F1). */
    private const HISTORICO_DIAS = 400;

    /** Janela da aba Evolução. */
    public const EVOLUCAO_DIAS = 30;

    public function __construct(
        private readonly AdesaoCalculator $adesao,
        private readonly AtencaoDetector $atencao,
        private readonly NivelCalculator $nivel,
        private readonly SequenciaCalculator $sequencia,
        private readonly JogoService $jogo,
    ) {}

    /**
     * @return array{
     *   kpis: array{adesao_media_7d: ?int, pacientes_ativos: int, dor_media: ?float, checkins_7d: int},
     *   atencao: list<array<string, mixed>>,
     *   pacientes: list<array<string, mixed>>
     * }
     */
    public function montar(?DateTimeInterface $agora = null): array
    {
        $hoje = DiaLocal::de($agora ?? now());
        $inicioJanela = DiaLocal::somar($hoje, -(AdesaoCalculator::JANELA_DIAS - 1));

        $pacientes = Patient::query()
            ->whereNull('blocked_at')
            ->with('contact:id,name')
            ->orderBy('id')
            ->get();
        $ids = array_map('intval', $pacientes->pluck('id')->all());

        $programas = $this->programasAtivos($ids);
        $checkins = $this->checkins($ids, DiaLocal::somar($hoje, -self::HISTORICO_DIAS), $hoje);
        $xp = $ids === [] ? collect() : GameLedger::query()
            ->whereIn('patient_id', $ids)
            ->groupBy('patient_id')
            ->selectRaw('patient_id, SUM(xp) as total')
            ->pluck('total', 'patient_id');
        $limiares = JogoService::tabelaDeNiveis();

        $linhas = [];
        $fila = [];
        $checkins7d = 0;
        foreach ($pacientes as $p) {
            $prog = $programas[(int) $p->id] ?? null;
            $dele = $checkins[(int) $p->id] ?? [];
            // Mesmo limiar que pausa o jogo no portal: absoluto, ou sobre a dor de base (protocolo 11).
            $limiar = $this->jogo->limiarDeDor($prog, $dele);

            $adesao = $this->adesao->calcular($dele, (int) $p->sessions_per_week, $hoje, $prog !== null);
            $seq = $this->sequencia->calcular($dele, (int) $p->sessions_per_week, $hoje, $limiar);
            $motivos = $this->atencao->motivos($dele, $prog ? $this->inicioPrograma($prog) : null, $hoje, $limiar);

            $ultimaDor = null;
            $ultimoRegistro = null;
            $dorJanela = null;
            foreach ($dele as $c) {
                $ultimoRegistro = $c['date'];
                if ($c['pain'] !== null) {
                    $ultimaDor = $c['pain'];
                    if ($c['date'] >= $inicioJanela) {
                        $dorJanela = $c['pain'];
                    }
                }
                if ($c['date'] >= $inicioJanela) {
                    $checkins7d++;
                }
            }

            $linha = [
                'id' => (int) $p->id,
                'nome' => $p->contact?->name,
                'sessions_per_week' => (int) $p->sessions_per_week,
                'programa_ativo' => $prog !== null,
                'adesao_7d' => $adesao,
                'ultima_dor' => $ultimaDor,
                'dor_7d' => $dorJanela,
                'ultimo_registro' => $ultimoRegistro,
                'nivel' => $this->nivel->nivel((int) ($xp[$p->id] ?? 0), $limiares),
                'sequencia' => $seq['sequencia'],
                'em_pausa' => $seq['em_pausa'],
            ];
            $linhas[] = $linha;

            if ($motivos !== []) {
                $filas = $this->atencao->separar($motivos);
                $fila[] = [
                    'patient_id' => (int) $p->id,
                    'nome' => $linha['nome'],
                    'motivos' => $motivos,
                    'clinico' => $filas['clinico'],
                    'abandono' => $filas['abandono'],
                    'ultima_dor' => $ultimaDor,
                    'ultimo_registro' => $ultimoRegistro,
                ];
            }
        }

        // Alerta clínico antes do abandono; dentro de cada grupo, a ordem do cadastro.
        usort($fila, fn (array $a, array $b) => (int) ($b['clinico'] !== []) <=> (int) ($a['clinico'] !== []));

        return [
            'kpis' => [
                'adesao_media_7d' => $this->mediaInteira(array_column($linhas, 'adesao_7d')),
                'pacientes_ativos' => count($linhas),
                'dor_media' => $this->mediaDecimal(array_column($linhas, 'dor_7d')),
                'checkins_7d' => $checkins7d,
            ],
            'atencao' => $fila,
            'pacientes' => $linhas,
        ];
    }

    /**
     * Série do paciente para a aba Evolução (visão do profissional). Dias sem check-in ficam
     * de fora da série de dor; cada ponto é um registro real, nada interpolado.
     *
     * `serie_dor` é só o que tem dor informada (o mesmo nome que o UC-FPP-20 usa como controle
     * negativo do portal); `pontos` traz todo check-in da janela, para os dias com exercício.
     *
     * @return array{
     *   dias: int, adesao_7d: ?int, sessions_per_week: int,
     *   pontos: list<array{date:string, pain:?int, feitos:int, red_flags:list<string>}>,
     *   serie_dor: list<array{date:string, pain:int}>
     * }
     */
    public function evolucao(Patient $p, ?DateTimeInterface $agora = null): array
    {
        $hoje = DiaLocal::de($agora ?? now());
        $prog = $this->programasAtivos([(int) $p->id])[(int) $p->id] ?? null;
        $dele = $this->checkins([(int) $p->id], DiaLocal::somar($hoje, -(self::EVOLUCAO_DIAS - 1)), $hoje)[(int) $p->id] ?? [];

        return [
            'dias' => self::EVOLUCAO_DIAS,
            'adesao_7d' => $this->adesao->calcular($dele, (int) $p->sessions_per_week, $hoje, $prog !== null),
            'sessions_per_week' => (int) $p->sessions_per_week,
            'pontos' => array_map(fn (array $c) => [
                'date' => $c['date'],
                'pain' => $c['pain'],
                'feitos' => $c['done_count'],
                'red_flags' => $c['red_flags'],
            ], $dele),
            'serie_dor' => array_values(array_map(
                fn (array $c) => ['date' => $c['date'], 'pain' => (int) $c['pain']],
                array_filter($dele, fn (array $c) => $c['pain'] !== null),
            )),
        ];
    }

    /**
     * Programa ativo mais recente de cada paciente.
     *
     * @param  array<int, int>  $ids
     * @return array<int, Program>
     */
    private function programasAtivos(array $ids): array
    {
        $porPaciente = [];
        if ($ids === []) {
            return $porPaciente;
        }
        $programas = Program::query()
            ->whereIn('patient_id', $ids)
            ->where('status', Program::STATUS_ATIVO)
            ->orderBy('id')
            ->get();
        foreach ($programas as $prog) {
            $porPaciente[(int) $prog->patient_id] = $prog;
        }

        return $porPaciente;
    }

    /**
     * Check-ins por paciente, em ordem de data, no formato dos calculadores puros.
     *
     * @param  array<int, int>  $ids
     * @return array<int, list<array{date:string, done_count:int, pain:?int, red_flags:list<string>, crise:bool}>>
     */
    private function checkins(array $ids, string $de, string $ate): array
    {
        $porPaciente = [];
        if ($ids === []) {
            return $porPaciente;
        }

        $linhas = Checkin::query()
            ->whereIn('patient_id', $ids)
            ->whereBetween('date', [$de, $ate])
            ->orderBy('date')
            ->get();
        foreach ($linhas as $c) {
            $porPaciente[(int) $c->patient_id][] = JogoService::paraCalculo($c);
        }

        return $porPaciente;
    }

    private function inicioPrograma(Program $prog): string
    {
        return $prog->starts_at !== null
            ? $prog->starts_at->format('Y-m-d')
            : DiaLocal::de($prog->created_at ?? now());
    }

    /** @param  array<int, int|null>  $valores  média arredondada meio para cima; null se não houver valor */
    private function mediaInteira(array $valores): ?int
    {
        $m = $this->mediaBruta($valores);

        return $m === null ? null : (int) round($m, 0, PHP_ROUND_HALF_UP);
    }

    /** @param  array<int, int|null>  $valores  média com 1 casa; null se não houver valor */
    private function mediaDecimal(array $valores): ?float
    {
        $m = $this->mediaBruta($valores);

        return $m === null ? null : round($m, 1, PHP_ROUND_HALF_UP);
    }

    /** @param  array<int, int|null>  $valores */
    private function mediaBruta(array $valores): ?float
    {
        $validos = array_values(array_filter($valores, fn ($v) => $v !== null));

        return $validos === [] ? null : array_sum($validos) / count($validos);
    }
}
