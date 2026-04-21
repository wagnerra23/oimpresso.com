@extends('pontowr2::layouts.module')

@section('module_content')
    {{-- Cabeçalho padrão UltimatePOS/AdminLTE --}}
    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.module_label') }}
            <small>{{ __('pontowr2::ponto.menu.dashboard') }}</small>
        </h1>
    </section>

    <section class="content">
        {{-- KPIs em small-box AdminLTE --}}
        <div class="row">
            @php
                $cards = [
                    [
                        'label' => __('pontowr2::ponto.menu.colaboradores') . ' ativos',
                        'value' => $kpis['colaboradores_ativos'] ?? 0,
                        'icon'  => 'fa fas fa-users',
                        'color' => 'bg-aqua',
                        'link'  => route('ponto.colaboradores.index'),
                    ],
                    [
                        'label' => 'Presentes agora',
                        'value' => $kpis['presentes_agora'] ?? 0,
                        'icon'  => 'fa fas fa-user-check',
                        'color' => 'bg-green',
                        'link'  => route('ponto.espelho.index'),
                    ],
                    [
                        'label' => 'Atrasos hoje',
                        'value' => $kpis['atrasos_hoje'] ?? 0,
                        'icon'  => 'fa fas fa-clock',
                        'color' => 'bg-yellow',
                        'link'  => route('ponto.espelho.index'),
                    ],
                    [
                        'label' => 'Faltas hoje',
                        'value' => $kpis['faltas_hoje'] ?? 0,
                        'icon'  => 'fa fas fa-user-slash',
                        'color' => 'bg-red',
                        'link'  => route('ponto.espelho.index'),
                    ],
                    [
                        'label' => 'HE do mês (min)',
                        'value' => $kpis['he_mes_minutos'] ?? 0,
                        'icon'  => 'fa fas fa-hourglass-half',
                        'color' => 'bg-purple',
                        'link'  => route('ponto.banco-horas.index'),
                    ],
                    [
                        'label' => __('pontowr2::ponto.menu.aprovacoes') . ' pendentes',
                        'value' => $kpis['aprovacoes_pendentes'] ?? 0,
                        'icon'  => 'fa fas fa-check-double',
                        'color' => 'bg-maroon',
                        'link'  => route('ponto.aprovacoes.index'),
                    ],
                ];
            @endphp

            @foreach ($cards as $c)
                <div class="col-md-2 col-sm-4 col-xs-6">
                    <div class="small-box {{ $c['color'] }}">
                        <div class="inner">
                            <h3>{{ $c['value'] }}</h3>
                            <p>{{ $c['label'] }}</p>
                        </div>
                        <div class="icon">
                            <i class="{{ $c['icon'] }}"></i>
                        </div>
                        <a href="{{ $c['link'] }}" class="small-box-footer">
                            Mais info <i class="fa fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Fila de aprovações + Atividade recente --}}
        <div class="row">
            <div class="col-md-8">
                @component('components.widget')
                    @slot('title')
                        <i class="fa fas fa-check-double"></i>
                        {{ __('pontowr2::ponto.menu.aprovacoes') }}
                        <a href="{{ route('ponto.aprovacoes.index') }}" class="btn btn-default btn-xs pull-right">
                            Ver fila completa
                        </a>
                    @endslot

                    @include('pontowr2::aprovacoes._tabela', ['aprovacoes' => $aprovacoes ?? collect()])
                @endcomponent
            </div>

            <div class="col-md-4">
                @component('components.widget')
                    @slot('title')
                        <i class="fa fas fa-bell"></i>
                        Atividade recente
                    @endslot

                    @forelse ($atividadeRecente ?? [] as $m)
                        <div class="callout callout-info" style="margin-bottom:8px; padding:8px 10px;">
                            <small class="text-muted pull-right">{{ optional($m->momento)->format('H:i') }}</small>
                            <strong>{{ optional(optional($m->colaborador)->user)->first_name }} {{ optional(optional($m->colaborador)->user)->last_name }}</strong><br>
                            <span class="text-muted">{{ $m->tipo }} — NSR {{ $m->nsr }}</span>
                        </div>
                    @empty
                        <p class="text-muted text-center" style="padding:20px 0;">
                            <i class="fa fas fa-inbox" style="font-size:24px;"></i><br>
                            Sem atividade recente.
                        </p>
                    @endforelse
                @endcomponent
            </div>
        </div>
    </section>
@endsection
