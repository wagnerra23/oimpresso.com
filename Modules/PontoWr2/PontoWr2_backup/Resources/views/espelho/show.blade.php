@extends('pontowr2::layouts.module')

@section('title', __('pontowr2::ponto.menu.espelho'))

@section('module_content')
    {{--
        Espelho de ponto individual do colaborador no mês selecionado.
        Mostra cabeçalho com dados, totalizadores do mês, tabela dia-a-dia
        com apuração + marcações. Padrão AdminLTE 2.x + Bootstrap 3.
        Controller: EspelhoController@show.

        Variáveis esperadas:
          - $colaborador : Modules\PontoWr2\Entities\Colaborador
          - $apuracoes   : Collection<ApuracaoDia>
          - $marcacoes   : Collection<Marcacao> agrupada por data (string 'Y-m-d')
          - $mes         : string 'Y-m'
    --}}

    @php
        // Totalizadores do mês (sum em minutos)
        $totTrabalhado   = 0;
        $totAtraso       = 0;
        $totSaidaAntec   = 0;
        $totFalta        = 0;
        $totHeDiurna     = 0;
        $totHeNoturna    = 0;
        $totAdicNoturno  = 0;
        $totBhCredito    = 0;
        $totBhDebito     = 0;
        $qtdDivergencia  = 0;

        foreach ($apuracoes as $ap) {
            $totTrabalhado  += (int) $ap->realizada_trabalhada_minutos;
            $totAtraso      += (int) $ap->atraso_minutos;
            $totSaidaAntec  += (int) $ap->saida_antecipada_minutos;
            $totFalta       += (int) $ap->falta_minutos;
            $totHeDiurna    += (int) $ap->he_diurna_minutos;
            $totHeNoturna   += (int) $ap->he_noturna_minutos;
            $totAdicNoturno += (int) $ap->adicional_noturno_minutos;
            $totBhCredito   += (int) $ap->banco_horas_credito_minutos;
            $totBhDebito    += (int) $ap->banco_horas_debito_minutos;
            if ($ap->estado === 'DIVERGENCIA') {
                $qtdDivergencia++;
            }
        }

        // Converte minutos → "HH:MM"
        $fmtMin = function ($min) {
            $min = (int) $min;
            $h   = intdiv($min, 60);
            $m   = $min % 60;
            return sprintf('%02d:%02d', $h, $m);
        };

        $labelEstadoApuracao = [
            'PENDENTE'    => 'label-default',
            'CALCULADO'   => 'label-info',
            'DIVERGENCIA' => 'label-warning',
            'AJUSTADO'    => 'label-primary',
            'CONSOLIDADO' => 'label-success',
            'FECHADO'     => 'label-success',
        ];

        // Navegação mês anterior / próximo
        list($anoAtual, $mesAtual) = explode('-', $mes);
        $anoAtual = (int) $anoAtual;
        $mesAtual = (int) $mesAtual;

        $mesAnteriorNum = $mesAtual - 1;
        $anoAnterior    = $anoAtual;
        if ($mesAnteriorNum < 1) {
            $mesAnteriorNum = 12;
            $anoAnterior--;
        }

        $mesProximoNum = $mesAtual + 1;
        $anoProximo    = $anoAtual;
        if ($mesProximoNum > 12) {
            $mesProximoNum = 1;
            $anoProximo++;
        }

        $mesAnteriorStr = sprintf('%04d-%02d', $anoAnterior, $mesAnteriorNum);
        $mesProximoStr  = sprintf('%04d-%02d', $anoProximo,  $mesProximoNum);

        $mesesPt = [1=>'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        $mesExtenso = $mesesPt[$mesAtual] . '/' . $anoAtual;

        // Abreviações dos dias da semana em PT (Carbon::format('w') → 0=dom..6=sáb)
        $diasSemanaPt = [0 => 'Dom', 1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];

        $nomeColab = trim(optional($colaborador->user)->first_name . ' ' . optional($colaborador->user)->last_name);
        if ($nomeColab === '') {
            $nomeColab = 'Colaborador #' . $colaborador->id;
        }
    @endphp

    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.menu.espelho') }}
            <small>{{ $nomeColab }} — {{ $mesExtenso }}</small>
        </h1>
    </section>

    <section class="content">

        {{-- Navegação de mês e botão voltar --}}
        <div class="row" style="margin-bottom:10px;">
            <div class="col-md-12">
                <a href="{{ route('ponto.espelho.index', ['mes' => $mes]) }}" class="btn btn-default">
                    <i class="fa fas fa-arrow-left"></i> Voltar à lista
                </a>
                &nbsp;&nbsp;
                <div class="btn-group" role="group">
                    <a href="{{ route('ponto.espelho.show', ['colaborador' => $colaborador->id, 'mes' => $mesAnteriorStr]) }}"
                       class="btn btn-default">
                        <i class="fa fas fa-chevron-left"></i> Mês anterior
                    </a>
                    <a href="{{ route('ponto.espelho.show', ['colaborador' => $colaborador->id, 'mes' => $mesProximoStr]) }}"
                       class="btn btn-default">
                        Próximo mês <i class="fa fas fa-chevron-right"></i>
                    </a>
                </div>
                <a href="{{ route('ponto.espelho.imprimir', ['colaborador' => $colaborador->id, 'mes' => $mes]) }}"
                   class="btn btn-info pull-right">
                    <i class="fa fas fa-print"></i> Imprimir PDF
                </a>
            </div>
        </div>

        {{-- Cabeçalho do colaborador --}}
        @component('components.widget', ['class' => 'box-solid box-primary'])
            @slot('title')
                <i class="fa fas fa-id-card"></i>
                Dados do colaborador
            @endslot

            <div class="row">
                <div class="col-md-4">
                    <strong>Nome:</strong> {{ $nomeColab }}<br>
                    <strong>Matrícula:</strong> {{ $colaborador->matricula ?: '—' }}<br>
                    <strong>E-mail:</strong> {{ optional($colaborador->user)->email ?: '—' }}
                </div>
                <div class="col-md-4">
                    <strong>Escala atual:</strong>
                    {{ optional($colaborador->escalaAtual)->nome ?: '—' }}<br>
                    <strong>Carga diária:</strong>
                    @if (optional($colaborador->escalaAtual)->carga_diaria_minutos)
                        {{ $fmtMin($colaborador->escalaAtual->carga_diaria_minutos) }}
                    @else
                        —
                    @endif
                    <br>
                    <strong>Controla ponto:</strong>
                    @if ($colaborador->controla_ponto)
                        <span class="label label-success">Sim</span>
                    @else
                        <span class="label label-default">Não</span>
                    @endif
                </div>
                <div class="col-md-4">
                    <strong>Admissão:</strong>
                    {{ optional($colaborador->admissao)->format('d/m/Y') ?: '—' }}<br>
                    <strong>Desligamento:</strong>
                    {{ optional($colaborador->desligamento)->format('d/m/Y') ?: '—' }}
                </div>
            </div>
        @endcomponent

        {{-- Totalizadores do mês --}}
        <div class="row">
            <div class="col-md-2 col-sm-4 col-xs-6">
                <div class="small-box bg-aqua">
                    <div class="inner">
                        <h3 style="font-size:24px;">{{ $fmtMin($totTrabalhado) }}</h3>
                        <p>Trabalhado</p>
                    </div>
                    <div class="icon"><i class="fa fas fa-briefcase"></i></div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6">
                <div class="small-box bg-yellow">
                    <div class="inner">
                        <h3 style="font-size:24px;">{{ $fmtMin($totAtraso) }}</h3>
                        <p>Atraso</p>
                    </div>
                    <div class="icon"><i class="fa fas fa-clock"></i></div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6">
                <div class="small-box bg-red">
                    <div class="inner">
                        <h3 style="font-size:24px;">{{ $fmtMin($totFalta) }}</h3>
                        <p>Falta</p>
                    </div>
                    <div class="icon"><i class="fa fas fa-user-slash"></i></div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6">
                <div class="small-box bg-purple">
                    <div class="inner">
                        <h3 style="font-size:24px;">{{ $fmtMin($totHeDiurna + $totHeNoturna) }}</h3>
                        <p>Hora extra</p>
                    </div>
                    <div class="icon"><i class="fa fas fa-hourglass-half"></i></div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6">
                <div class="small-box bg-green">
                    <div class="inner">
                        <h3 style="font-size:24px;">{{ $fmtMin($totBhCredito) }}</h3>
                        <p>Banco hrs (+)</p>
                    </div>
                    <div class="icon"><i class="fa fas fa-plus-circle"></i></div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6">
                <div class="small-box bg-maroon">
                    <div class="inner">
                        <h3 style="font-size:24px;">{{ $fmtMin($totBhDebito) }}</h3>
                        <p>Banco hrs (−)</p>
                    </div>
                    <div class="icon"><i class="fa fas fa-minus-circle"></i></div>
                </div>
            </div>
        </div>

        {{-- Alerta de divergências --}}
        @if ($qtdDivergencia > 0)
            <div class="alert alert-warning">
                <i class="fa fas fa-exclamation-triangle"></i>
                <strong>{{ $qtdDivergencia }}</strong>
                {{ $qtdDivergencia === 1 ? 'dia apresenta divergência' : 'dias apresentam divergência' }}
                no mês. Verificar marcações abaixo.
            </div>
        @endif

        {{-- Tabela dia-a-dia --}}
        <div class="row">
            <div class="col-md-12">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-calendar-alt"></i>
                        Apuração diária — {{ $mesExtenso }}
                    @endslot

                    <div class="table-responsive">
                        <table class="table table-striped table-condensed">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Previsto</th>
                                    <th>Realizado</th>
                                    <th>Marcações</th>
                                    <th class="text-right">Atraso</th>
                                    <th class="text-right">HE</th>
                                    <th class="text-right">BH (+/−)</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($apuracoes as $ap)
                                    @php
                                        $dataStr  = $ap->data->format('Y-m-d');
                                        $marcDia  = isset($marcacoes[$dataStr]) ? $marcacoes[$dataStr] : collect();
                                        $heTotal  = (int) $ap->he_diurna_minutos + (int) $ap->he_noturna_minutos;
                                        $bhLiquido = (int) $ap->banco_horas_credito_minutos - (int) $ap->banco_horas_debito_minutos;
                                    @endphp
                                    <tr @if ($ap->estado === 'DIVERGENCIA') class="bg-warning" @endif>
                                        <td>
                                            <strong>{{ $ap->data->format('d/m') }}</strong><br>
                                            <small class="text-muted">
                                                {{ $diasSemanaPt[(int) $ap->data->format('w')] }}
                                            </small>
                                        </td>
                                        <td>
                                            @if ($ap->prevista_entrada && $ap->prevista_saida)
                                                {{ substr($ap->prevista_entrada, 0, 5) }}
                                                –
                                                {{ substr($ap->prevista_saida, 0, 5) }}
                                            @else
                                                <small class="text-muted">—</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($ap->realizada_entrada && $ap->realizada_saida)
                                                {{ substr($ap->realizada_entrada, 0, 5) }}
                                                –
                                                {{ substr($ap->realizada_saida, 0, 5) }}
                                            @elseif ($ap->realizada_trabalhada_minutos > 0)
                                                <small class="text-muted">{{ $fmtMin($ap->realizada_trabalhada_minutos) }}</small>
                                            @else
                                                <small class="text-muted">—</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($marcDia->count() > 0)
                                                @foreach ($marcDia as $m)
                                                    <small class="label label-default" style="margin-right:3px; display:inline-block; margin-bottom:2px;">
                                                        {{ $m->momento->format('H:i') }}
                                                    </small>
                                                @endforeach
                                                <br>
                                                <small class="text-muted">{{ $marcDia->count() }} marcação(ões)</small>
                                            @else
                                                <small class="text-muted">—</small>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if ($ap->atraso_minutos > 0)
                                                <span class="text-warning">{{ $fmtMin($ap->atraso_minutos) }}</span>
                                            @else
                                                <small class="text-muted">—</small>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if ($heTotal > 0)
                                                <span class="text-purple">{{ $fmtMin($heTotal) }}</span>
                                            @else
                                                <small class="text-muted">—</small>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if ($bhLiquido > 0)
                                                <span class="text-green">+{{ $fmtMin($bhLiquido) }}</span>
                                            @elseif ($bhLiquido < 0)
                                                <span class="text-red">−{{ $fmtMin(abs($bhLiquido)) }}</span>
                                            @else
                                                <small class="text-muted">—</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="label {{ $labelEstadoApuracao[$ap->estado] ?? 'label-default' }}">
                                                {{ $ap->estado }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted" style="padding:24px;">
                                            <i class="fa fas fa-calendar-times" style="font-size:24px;"></i><br>
                                            Nenhuma apuração encontrada em {{ $mesExtenso }}.<br>
                                            <small>
                                                O processamento de apuração pode ainda não ter rodado,
                                                ou o colaborador não tinha escala vigente no período.
                                            </small>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endcomponent
            </div>
        </div>

        {{-- Rodapé legal --}}
        <div class="text-center text-muted" style="margin:12px 0;">
            <small>
                <i class="fa fas fa-shield-alt"></i>
                Registros protegidos pela Portaria MTP 671/2021 — marcações são imutáveis (append-only).
            </small>
        </div>

    </section>
@endsection
