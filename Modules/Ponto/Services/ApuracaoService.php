<?php

namespace Modules\Ponto\Services;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Ponto\Entities\ApuracaoDia;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Entities\Marcacao;

/**
 * Serviço de apuração diária.
 * Aplica regras CLT (Art. 58, 59, 66, 71, 73), intercorrências e banco de horas.
 *
 * Pattern: Chain of Responsibility — cada regra aplicada em sequência
 * sobre o objeto ApuracaoDia sendo montado.
 */
class ApuracaoService
{
    /** @var BancoHorasService */
    protected $bancoHoras;

    /** Hora noturna urbana: 22h00 até 05h00 (Art. 73 CLT). */
    const NOTURNO_INICIO_H = 22;
    const NOTURNO_FIM_H    = 5;

    public function __construct(BancoHorasService $bancoHoras)
    {
        $this->bancoHoras = $bancoHoras;
    }

    /**
     * Apura um dia específico para um colaborador.
     */
    public function apurar(Colaborador $colaborador, Carbon $data)
    {
        $self = $this;

        // D9.a OTel — apuração diária aplica regras CLT (Art. 58/59/66/71/73).
        // Cria/atualiza ApuracaoDia (NÃO toca Marcacao — só lê). Hot-path mensal.
        return OtelHelper::span('ponto.apuracao.apurar', [
            'module'      => 'Ponto',
            'business_id' => (int) $colaborador->business_id,
            'employee_id' => (int) $colaborador->id,
            'data'        => $data->toDateString(),
        ], function () use ($colaborador, $data, $self) {
        return DB::transaction(function () use ($colaborador, $data, $self) {
            $apuracao = ApuracaoDia::firstOrNew([
                'colaborador_config_id' => $colaborador->id,
                'data'                  => $data->toDateString(),
            ]);

            $apuracao->business_id = $colaborador->business_id;
            $apuracao->escala_id   = $colaborador->escala_atual_id;
            $apuracao->estado      = ApuracaoDia::ESTADO_PENDENTE;
            $apuracao->divergencias = [];

            // Zerar campos calculáveis antes da reapuração
            foreach ([
                'realizada_trabalhada_minutos', 'realizada_intrajornada_minutos',
                'atraso_minutos', 'saida_antecipada_minutos', 'falta_minutos',
                'he_diurna_minutos', 'he_noturna_minutos', 'adicional_noturno_minutos',
                'dsr_repercussao_minutos',
                'interjornada_violacao_minutos', 'intrajornada_violacao_minutos',
                'banco_horas_credito_minutos', 'banco_horas_debito_minutos',
                'qtd_intercorrencias',
            ] as $campo) {
                $apuracao->{$campo} = 0;
            }

            $self->carregarHorariosPrevistos($apuracao, $colaborador, $data);
            $self->carregarMarcacoes($apuracao, $colaborador, $data);
            $self->aplicarIntercorrencias($apuracao, $colaborador, $data);
            $self->aplicarRegraTolerancia($apuracao);
            $self->aplicarRegraIntrajornada($apuracao);
            $self->aplicarRegraInterjornada($apuracao, $colaborador, $data);
            $self->aplicarRegraHoraExtra($apuracao, $colaborador, $data);
            $self->aplicarRegraAdicionalNoturno($apuracao, $colaborador, $data);
            $self->aplicarRegraDsr($apuracao, $colaborador, $data);
            // Licença aprovada abona o dia — HRM-O6 PR-7 / Onda 2 do plano de integração.
            // POSIÇÃO MEDIDA, não escolhida: tem de vir DEPOIS de aplicarRegraTolerancia()
            // (que é quem SETA `falta_minutos = prevista_carga` quando qtd_marcacoes === 0,
            // linha ~206) e ANTES de calcularBancoHoras() (que converte falta em DÉBITO de
            // banco, linha ~420). O plano de integração pedia "antes de aplicarIntercorrencias";
            // ali o zero seria desfeito pela tolerância logo em seguida, justamente no caso
            // principal — licença sem batida nenhuma. Ver LicencaAbonaDiaContratoTest::UC-LIC-06.
            $self->aplicarLicencas($apuracao, $colaborador, $data);
            $self->calcularBancoHoras($apuracao, $colaborador);

            $temDivergencia = is_array($apuracao->divergencias) && count($apuracao->divergencias) > 0;
            $apuracao->estado = $temDivergencia
                ? ApuracaoDia::ESTADO_DIVERGENCIA
                : ApuracaoDia::ESTADO_CALCULADO;
            $apuracao->calculado_em = now();
            $apuracao->save();

            return $apuracao;
        });
        }); // fecha OtelHelper::span outer
    }

    /**
     * Reapuração em lote (período).
     */
    public function reapurarPeriodo(Colaborador $colaborador, Carbon $inicio, Carbon $fim)
    {
        $count = 0;
        $cursor = $inicio->copy();
        while ($cursor <= $fim) {
            $this->apurar($colaborador, $cursor);
            $cursor->addDay();
            $count++;
        }
        return $count;
    }

    public function carregarHorariosPrevistos(ApuracaoDia $a, Colaborador $c, Carbon $data)
    {
        $escala = $c->escalaAtual;
        if (!$escala) {
            return;
        }

        $turno = $escala->turnos()
            ->where('dia_semana', $data->dayOfWeek)
            ->first();

        if ($turno) {
            $a->prevista_entrada       = $turno->hora_entrada;
            $a->prevista_saida         = $turno->hora_saida;
            $a->prevista_carga_minutos = $escala->carga_diaria_minutos;
        }
    }

    public function carregarMarcacoes(ApuracaoDia $a, Colaborador $c, Carbon $data)
    {
        $marcacoes = Marcacao::where('colaborador_config_id', $c->id)
            ->whereDate('momento', $data)
            ->whereNotIn('origem', [Marcacao::ORIGEM_ANULACAO])
            ->orderBy('momento')
            ->get();

        $a->qtd_marcacoes = $marcacoes->count();

        $entrada = $marcacoes->firstWhere('tipo', Marcacao::TIPO_ENTRADA);
        $saida   = $marcacoes->where('tipo', Marcacao::TIPO_SAIDA)->last();

        if ($entrada) {
            $a->realizada_entrada = $entrada->momento->format('H:i:s');
        }
        if ($saida) {
            $a->realizada_saida = $saida->momento->format('H:i:s');
        }

        if ($entrada && $saida) {
            $trabalhado = $entrada->momento->diffInMinutes($saida->momento);
            $a->realizada_trabalhada_minutos = $trabalhado;
        }

        $almocoInicio = $marcacoes->firstWhere('tipo', Marcacao::TIPO_ALMOCO_INICIO);
        $almocoFim    = $marcacoes->firstWhere('tipo', Marcacao::TIPO_ALMOCO_FIM);
        if ($almocoInicio && $almocoFim) {
            $intra = $almocoInicio->momento->diffInMinutes($almocoFim->momento);
            $a->realizada_intrajornada_minutos = $intra;
            $a->realizada_trabalhada_minutos = max(0, $a->realizada_trabalhada_minutos - $intra);
        }

        // Divergência: qtd ímpar (marcação faltando)
        if ($a->qtd_marcacoes > 0 && ($a->qtd_marcacoes % 2) !== 0) {
            $this->addDivergencia($a, 'marcacoes_impares', 'Número ímpar de marcações no dia.');
        }
    }

    /**
     * RN-001: Tolerância Art. 58 §1º CLT — 5 min por marcação, 10 min/dia.
     */
    public function aplicarRegraTolerancia(ApuracaoDia $a)
    {
        if (!$a->prevista_entrada || !$a->realizada_entrada) {
            return;
        }

        $toleranciaMinutos = (int) config('pontowr2.clt.tolerancia_minutos_por_marcacao', 5);
        $toleranciaMaxDia  = (int) config('pontowr2.clt.tolerancia_maxima_diaria_minutos', 10);

        $prevista = Carbon::parse($a->data->toDateString() . ' ' . $a->prevista_entrada);
        $real     = Carbon::parse($a->data->toDateString() . ' ' . $a->realizada_entrada);

        // Carbon 3 / timezone edge-cases: usar diff "signed" e clamp para >= 0
        $atraso = max(0, $prevista->diffInMinutes($real, false));

        if ($atraso > $toleranciaMinutos) {
            $a->atraso_minutos = $atraso;
            if ($atraso > $toleranciaMaxDia) {
                $this->addDivergencia($a, 'atraso_acima_tolerancia',
                    "Atraso de {$atraso}min excede tolerância diária de {$toleranciaMaxDia}min.");
            }
        }

        // Saída antecipada
        if ($a->prevista_saida && $a->realizada_saida) {
            $prevSaida = Carbon::parse($a->data->toDateString() . ' ' . $a->prevista_saida);
            $realSaida = Carbon::parse($a->data->toDateString() . ' ' . $a->realizada_saida);
            $antecipada = max(0, $realSaida->diffInMinutes($prevSaida, false));
            if ($antecipada > $toleranciaMinutos) {
                $a->saida_antecipada_minutos = $antecipada;
            }
        }

        // Falta: sem nenhuma marcação em dia previsto
        if ($a->prevista_carga_minutos > 0 && $a->qtd_marcacoes === 0) {
            $a->falta_minutos = $a->prevista_carga_minutos;
            $this->addDivergencia($a, 'falta', 'Sem marcações em dia previsto de trabalho.');
        }
    }

    /**
     * RN-003: Intrajornada mínima Art. 71 CLT (60min se jornada > 6h).
     */
    public function aplicarRegraIntrajornada(ApuracaoDia $a)
    {
        $minimoMinutos = (int) config('pontowr2.clt.intrajornada_minima_minutos', 60);

        if ($a->realizada_trabalhada_minutos > 360 &&
            $a->realizada_intrajornada_minutos < $minimoMinutos) {
            $a->intrajornada_violacao_minutos = $minimoMinutos - $a->realizada_intrajornada_minutos;
            $this->addDivergencia($a, 'intrajornada_insuficiente',
                "Intrajornada de {$a->realizada_intrajornada_minutos}min abaixo do mínimo de {$minimoMinutos}min (Art. 71 CLT).");
        }
    }

    /**
     * RN-004: Interjornada mínima Art. 66 CLT (11h entre jornadas).
     */
    public function aplicarRegraInterjornada(ApuracaoDia $a, Colaborador $c, Carbon $data)
    {
        $minimoHoras = (int) config('pontowr2.clt.interjornada_minima_horas', 11);

        $ultimaSaidaOntem = Marcacao::where('colaborador_config_id', $c->id)
            ->whereDate('momento', $data->copy()->subDay())
            ->where('tipo', Marcacao::TIPO_SAIDA)
            ->whereNotIn('origem', [Marcacao::ORIGEM_ANULACAO])
            ->orderByDesc('momento')
            ->first();

        if (!$ultimaSaidaOntem || !$a->realizada_entrada) {
            return;
        }

        $entradaHoje = Carbon::parse($data->toDateString() . ' ' . $a->realizada_entrada);
        $minutosInter = $ultimaSaidaOntem->momento->diffInMinutes($entradaHoje);
        $minimoMinutos = $minimoHoras * 60;

        if ($minutosInter < $minimoMinutos) {
            $a->interjornada_violacao_minutos = $minimoMinutos - $minutosInter;
            $this->addDivergencia($a, 'interjornada_insuficiente',
                "Interjornada de " . intdiv($minutosInter, 60) . "h abaixo do mínimo de {$minimoHoras}h (Art. 66 CLT).");
        }
    }

    /**
     * RN-006: Hora extra Art. 59 CLT — até 2h/dia; separa diurna e noturna.
     */
    public function aplicarRegraHoraExtra(ApuracaoDia $a, Colaborador $c, Carbon $data)
    {
        if (!$a->prevista_carga_minutos) {
            return;
        }

        $excedente = $a->realizada_trabalhada_minutos - $a->prevista_carga_minutos;
        if ($excedente <= 0) {
            return;
        }

        // Obter janela efetivamente trabalhada (entrada real → saída real)
        if (!$a->realizada_entrada || !$a->realizada_saida) {
            $a->he_diurna_minutos = $excedente;
            return;
        }

        $entrada = Carbon::parse($data->toDateString() . ' ' . $a->realizada_entrada);
        $saida   = Carbon::parse($data->toDateString() . ' ' . $a->realizada_saida);
        if ($saida->lt($entrada)) {
            $saida->addDay();
        }

        // O excedente é na "cauda" da jornada (parte final após cumprimento da carga)
        $inicioHe = $entrada->copy()->addMinutes($a->prevista_carga_minutos + $a->realizada_intrajornada_minutos);
        if ($inicioHe->gt($saida)) {
            $inicioHe = $saida->copy()->subMinutes($excedente);
        }

        $divisao = $this->dividirDiurnoNoturno($inicioHe, $saida);
        $a->he_diurna_minutos  = (int) $divisao['diurno_minutos'];
        $a->he_noturna_minutos = (int) $divisao['noturno_minutos'];

        $limiteHeHoras = (int) config('pontowr2.clt.limite_he_diaria_horas', 2);
        if ($excedente > $limiteHeHoras * 60) {
            $this->addDivergencia($a, 'he_acima_limite',
                "Hora extra de " . intdiv($excedente, 60) . "h excede limite diário de {$limiteHeHoras}h (Art. 59 CLT).");
        }
    }

    /**
     * RN-005: Adicional noturno Art. 73 CLT — 20% sobre minutos entre 22h e 5h.
     * A "hora ficta" de 52'30'' não altera quantidade de minutos legais;
     * afeta cálculo de remuneração na folha (conversão na camada financeira).
     */
    public function aplicarRegraAdicionalNoturno(ApuracaoDia $a, Colaborador $c, Carbon $data)
    {
        if (!$a->realizada_entrada || !$a->realizada_saida) {
            return;
        }

        $entrada = Carbon::parse($data->toDateString() . ' ' . $a->realizada_entrada);
        $saida   = Carbon::parse($data->toDateString() . ' ' . $a->realizada_saida);
        if ($saida->lt($entrada)) {
            $saida->addDay();
        }

        $divisao = $this->dividirDiurnoNoturno($entrada, $saida);
        $noturnoBruto = (int) $divisao['noturno_minutos'];

        // Desconta intrajornada proporcional (assumindo almoço no período diurno quando aplicável)
        // Caso simplificado: se intrajornada < 60min ou totalmente diurna, não desconta do noturno.
        $a->adicional_noturno_minutos = $noturnoBruto;
    }

    /**
     * RN-008: DSR Art. 9º Lei 605/49 — repercussão de HE sobre repouso semanal remunerado.
     * Cálculo proporcional: (HE_semana * DSRs_mes / dias_úteis_mes).
     * Aqui guardamos apenas o componente do dia — consolidação mensal acontece em ReportService.
     */
    public function aplicarRegraDsr(ApuracaoDia $a, Colaborador $c, Carbon $data)
    {
        // Domingos e feriados não geram DSR sobre si mesmos
        if ($data->isSunday()) {
            return;
        }

        // Componente diário = HE do dia (diurna + noturna). A repercussão real é calculada
        // em base mensal pelo ReportService (divide soma pelo número de dias úteis do mês).
        $a->dsr_repercussao_minutos = $a->he_diurna_minutos + $a->he_noturna_minutos;
    }

    /**
     * Aplica intercorrências APROVADAS do dia, ajustando trabalhado/falta/atraso conforme tipo.
     */
    public function aplicarIntercorrencias(ApuracaoDia $a, Colaborador $c, Carbon $data)
    {
        $intercorrencias = Intercorrencia::where('colaborador_config_id', $c->id)
            ->whereDate('data', $data)
            ->whereIn('estado', [Intercorrencia::ESTADO_APROVADA, Intercorrencia::ESTADO_APLICADA])
            ->get();

        $a->qtd_intercorrencias = $intercorrencias->count();
        if ($a->qtd_intercorrencias === 0) {
            return;
        }

        foreach ($intercorrencias as $inc) {
            if (!$inc->impacta_apuracao) {
                continue;
            }

            $minutos = $inc->duracaoMinutos();

            switch ($inc->tipo) {
                case 'ATESTADO_MEDICO':
                case 'CONSULTA_MEDICA':
                    // Considera como trabalhado (abona)
                    $a->realizada_trabalhada_minutos += $minutos;
                    $a->falta_minutos = max(0, $a->falta_minutos - $minutos);
                    $a->atraso_minutos = 0;
                    break;

                case 'REUNIAO_EXTERNA':
                case 'VISITA_CLIENTE':
                    // Trabalho externo — conta como trabalhado
                    $a->realizada_trabalhada_minutos += $minutos;
                    break;

                case 'HORA_EXTRA_AUTORIZADA':
                    // Já deve estar refletida nas marcações; marca flag
                    // (HE será calculada pela regra padrão; aqui apenas marca)
                    break;

                case 'ESQUECIMENTO_MARCACAO':
                case 'PROBLEMA_EQUIPAMENTO':
                    // Ajusta trabalhado pelo intervalo informado
                    if ($inc->intervalo_inicio && $inc->intervalo_fim) {
                        $a->realizada_trabalhada_minutos += $minutos;
                        $a->atraso_minutos = 0;
                    }
                    break;

                case 'OUTRO':
                default:
                    // Sem ajuste automático — operador revisa
                    $this->addDivergencia($a, 'intercorrencia_outro',
                        "Intercorrência código {$inc->codigo} do tipo OUTRO requer revisão manual.");
                    break;
            }

            if ($inc->descontar_banco_horas && $a->prevista_carga_minutos > 0) {
                $a->banco_horas_debito_minutos += $minutos;
            }
        }
    }

    public function calcularBancoHoras(ApuracaoDia $a, Colaborador $c)
    {
        if (!$c->usa_banco_horas) {
            return;
        }

        $multCredito = (float) config('pontowr2.banco_horas.multiplicador_credito', 1.0);

        // Crédito: HE convertida em BH (diurna + noturna se config permitir)
        $heTotal = $a->he_diurna_minutos + $a->he_noturna_minutos;
        if ($heTotal > 0 && config('pontowr2.banco_horas.converter_he_em_bh_default', true)) {
            $a->banco_horas_credito_minutos = (int) round($heTotal * $multCredito);
        }

        // Débito: falta ou saída antecipada
        if ($a->falta_minutos > 0 || $a->saida_antecipada_minutos > 0) {
            $a->banco_horas_debito_minutos += $a->falta_minutos + $a->saida_antecipada_minutos;
        }
    }

    /**
     * Divide um intervalo [inicio, fim] em minutos diurnos e noturnos (22h-5h).
     * Suporta intervalos que cruzam a meia-noite.
     *
     * @return array ['diurno_minutos' => int, 'noturno_minutos' => int]
     */
    public function dividirDiurnoNoturno(Carbon $inicio, Carbon $fim)
    {
        if ($fim->lte($inicio)) {
            return ['diurno_minutos' => 0, 'noturno_minutos' => 0];
        }

        $noturno = 0;
        $total = $inicio->diffInMinutes($fim);

        // Percorre minuto a minuto seria caro — dividimos por janelas.
        $cursor = $inicio->copy();
        while ($cursor->lt($fim)) {
            $fimJanela = $cursor->copy()->addDay()->startOfDay();
            if ($fimJanela->gt($fim)) {
                $fimJanela = $fim->copy();
            }

            // Dentro desse dia: noturno é [00:00..05:00] ∪ [22:00..24:00]
            $dia = $cursor->copy()->startOfDay();
            $madrugadaIni = $dia->copy();                 // 00:00
            $madrugadaFim = $dia->copy()->setTime(5, 0);  // 05:00
            $noiteIni     = $dia->copy()->setTime(22, 0); // 22:00
            $noiteFim     = $dia->copy()->addDay();       // 24:00

            $noturno += $this->intersecaoMinutos($cursor, $fimJanela, $madrugadaIni, $madrugadaFim);
            $noturno += $this->intersecaoMinutos($cursor, $fimJanela, $noiteIni, $noiteFim);

            $cursor = $fimJanela;
        }

        $diurno = max(0, $total - $noturno);
        return ['diurno_minutos' => $diurno, 'noturno_minutos' => $noturno];
    }

    protected function intersecaoMinutos(Carbon $aIni, Carbon $aFim, Carbon $bIni, Carbon $bFim)
    {
        $ini = $aIni->gt($bIni) ? $aIni : $bIni;
        $fim = $aFim->lt($bFim) ? $aFim : $bFim;
        if ($fim->lte($ini)) {
            return 0;
        }
        return $ini->diffInMinutes($fim);
    }

    protected function addDivergencia(ApuracaoDia $a, $chave, $mensagem)
    {
        $lista = is_array($a->divergencias) ? $a->divergencias : [];
        $lista[] = ['chave' => $chave, 'mensagem' => $mensagem];
        $a->divergencias = $lista;
    }

    /**
     * Licença aprovada no HRM abona o dia (HRM-O6 PR-7 · Onda 2 do plano de integração).
     *
     * DECISÃO [W] 2026-09-05 (emenda de D3, no PEDIDO-CL-hrm.md): a licença **NÃO bloqueia**
     * a marcação — ela SINALIZA divergência e SAI da conta de ausência. Nenhuma origem é
     * recusada, porque `REP_P`/`AFD` são leitura do que já aconteceu no equipamento e no
     * arquivo fiscal: apagar do sistema um registro que existe no REP cria exatamente o buraco
     * que a auditoria do MTE procura. A marcação entra marcada, e o gestor trata.
     *
     * ⚠️ Essa emenda SUPERSEDE a tabela §4.1 do plano de integração (session log de 2026-09-05),
     * que ainda propunha `MANUAL`/`INTEGRACAO` → RECUSA 422. O plano foi a MEDIÇÃO que motivou a
     * decisão; a decisão veio depois e é do dono. Por isso não existe guard de criação aqui.
     *
     * Leitura DIRETA de `essentials_leaves`, sem materializar Intercorrência — recomendação do
     * plano §3.3: dado materializado drifta (a licença pode ser cancelada depois e deixaria a
     * Intercorrência órfã abonando um dia sem licença); leitura direta se corrige sozinha na
     * próxima reapuração.
     *
     * NÃO credita `realizada_trabalhada_minutos`: não houve trabalho. Zera o que é PENALIDADE
     * por ausência (falta/atraso/saída antecipada) e nada mais — HE e adicional noturno de quem
     * de fato bateu ponto ficam intactos, e a divergência manda o caso ao RH.
     *
     * Tier 0 (ADR 0093): a query filtra por `business_id` do colaborador. Licença de outro
     * tenant não abona dia nenhum — `UC-LIC-05` prova com o adversário 99.
     *
     * `DB::table()` e não `EssentialsLeave::class` DE PROPÓSITO: medido em 2026-09-05, o
     * `Modules/Ponto` não tem UM `use Modules\<Outro>` — este seria o primeiro acoplamento de
     * classe do módulo. Acoplar à TABELA (contrato de schema, estável) em vez de à Entity de
     * outro módulo mantém a fronteira e não obriga o Ponto a carregar o boot do Essentials.
     * Se a ADR sucessora da 0014 (Onda 0, decisão [W]) declarar a dependência, trocar por
     * Entity vira refactor de uma linha.
     */
    public function aplicarLicencas(ApuracaoDia $a, Colaborador $c, Carbon $data)
    {
        if (empty($c->user_id)) {
            return; // colaborador sem vínculo com usuário do HRM — nada a abonar
        }

        $licenca = DB::table('essentials_leaves')
            ->where('business_id', $c->business_id)
            ->where('user_id', $c->user_id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $data->toDateString())
            ->whereDate('end_date', '>=', $data->toDateString())
            ->orderBy('id')
            ->first();

        if ($licenca === null) {
            return;
        }

        // A tolerância já rodou e pode ter marcado falta/atraso como se fosse ausência
        // injustificada. Num dia de licença aprovada isso é falso-positivo: some com as
        // duas chaves para o RH não ver "sem marcações" num dia legitimamente abonado.
        $a->divergencias = array_values(array_filter(
            is_array($a->divergencias) ? $a->divergencias : [],
            static function ($d) {
                $chave = is_array($d) ? ($d['chave'] ?? null) : null;

                return ! in_array($chave, ['falta', 'atraso_acima_tolerancia'], true);
            }
        ));

        $a->falta_minutos             = 0;
        $a->atraso_minutos            = 0;
        $a->saida_antecipada_minutos  = 0;

        $this->addDivergencia(
            $a,
            'licenca_abonou_dia',
            "Dia abonado por licença aprovada #{$licenca->id} — não conta como falta."
        );

        // A sinalização da D3: bateu ponto em dia de licença. Não recusamos a batida (ela pode
        // vir do REP e existir no arquivo fiscal), mas o dia vai para DIVERGENCIA e o RH decide.
        if ((int) $a->qtd_marcacoes > 0) {
            $this->addDivergencia(
                $a,
                'marcacao_em_dia_de_licenca',
                "Há {$a->qtd_marcacoes} marcação(ões) em dia coberto pela licença aprovada #{$licenca->id}."
            );
        }
    }
}
