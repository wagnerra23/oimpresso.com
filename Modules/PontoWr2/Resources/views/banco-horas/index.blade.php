@extends('pontowr2::layouts.module')

@section('title', __('pontowr2::ponto.menu.banco_horas'))

@section('module_content')
    @php
        $fmtMin = function ($min) {
            $min = (int) $min;
            $sinal = $min < 0 ? '−' : '';
            $min = abs($min);
            $h = intdiv($min, 60);
            $m = $min % 60;
            return $sinal . sprintf('%02d:%02d', $h, $m);
        };
    @endphp

    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.module_label') }}
            <small>{{ __('pontowr2::ponto.menu.banco_horas') }}</small>
        </h1>
    </section>

    <section class="content">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fas fa-check"></i> {{ session('success') }}
            </div>
        @endif

        {{-- Totais do business --}}
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-green">
                    <div class="inner">
                        <h3 style="font-size:24px;">{{ $fmtMin($totais['credito_total']) }}</h3>
                        <p>Crédito total</p>
                    </div>
                    <div class="icon"><i class="fa fas fa-plus-circle"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-red">
                    <div class="inner">
                        <h3 style="font-size:24px;">{{ $fmtMin($totais['debito_total']) }}</h3>
                        <p>Débito total</p>
                    </div>
                    <div class="icon"><i class="fa fas fa-minus-circle"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-aqua">
                    <div class="inner">
                        <h3>{{ $totais['colaboradores_credito'] }}</h3>
                        <p>Colaboradores com crédito</p>
                    </div>
                    <div class="icon"><i class="fa fas fa-user-plus"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-maroon">
                    <div class="inner">
                        <h3>{{ $totais['colaboradores_debito'] }}</h3>
                        <p>Colaboradores com débito</p>
                    </div>
                    <div class="icon"><i class="fa fas fa-user-minus"></i></div>
                </div>
            </div>
        </div>

        {{-- Lista de saldos --}}
        <div class="row">
            <div class="col-md-12">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-balance-scale"></i>
                        Saldos por colaborador
                        <small class="text-muted">({{ $saldos->total() }} registros)</small>
                    @endslot

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Colaborador</th>
                                    <th>Matrícula</th>
                                    <th class="text-right">Saldo</th>
                                    <th>Última movimentação</th>
                                    <th class="text-right">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($saldos as $s)
                                    @php
                                        $sm = (int) $s->saldo_minutos;
                                        if ($sm > 0)      { $cor = 'text-green'; }
                                        elseif ($sm < 0)  { $cor = 'text-red'; }
                                        else              { $cor = 'text-muted'; }
                                    @endphp
                                    <tr>
                                        <td>
                                            <i class="fa fas fa-user text-muted"></i>
                                            {{ optional(optional($s->colaborador)->user)->first_name }}
                                            {{ optional(optional($s->colaborador)->user)->last_name }}
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                {{ optional($s->colaborador)->matricula ?: '—' }}
                                            </small>
                                        </td>
                                        <td class="text-right">
                                            <strong class="{{ $cor }}">{{ $fmtMin($sm) }}</strong>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                {{ optional($s->updated_at)->format('d/m/Y H:i') ?: '—' }}
                                            </small>
                                        </td>
                                        <td class="text-right">
                                            <a href="{{ route('ponto.banco-horas.show', $s->colaborador_config_id) }}"
                                               class="btn btn-default btn-xs">
                                                <i class="fa fas fa-eye"></i> Detalhes
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted" style="padding:24px;">
                                            <i class="fa fas fa-inbox" style="font-size:24px;"></i><br>
                                            Nenhum saldo registrado ainda.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($saldos->hasPages())
                        <div class="text-center" style="margin-top:10px;">
                            {!! $saldos->appends(request()->query())->links() !!}
                        </div>
                    @endif
                @endcomponent
            </div>
        </div>
    </section>
@endsection
