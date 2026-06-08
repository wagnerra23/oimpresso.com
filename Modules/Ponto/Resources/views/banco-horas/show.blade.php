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

        $nomeColab = trim(optional(optional($saldo->colaborador)->user)->first_name . ' '
                        . optional(optional($saldo->colaborador)->user)->last_name);
        if ($nomeColab === '') {
            $nomeColab = 'Colaborador #' . $saldo->colaborador_config_id;
        }

        $sm = (int) $saldo->saldo_minutos;
        if ($sm > 0)     { $corBox = 'bg-green';  $corTxt = 'text-green'; }
        elseif ($sm < 0) { $corBox = 'bg-red';    $corTxt = 'text-red'; }
        else             { $corBox = 'bg-gray';   $corTxt = 'text-muted'; }
    @endphp

    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.menu.banco_horas') }}
            <small>{{ $nomeColab }}</small>
        </h1>
    </section>

    <section class="content">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fas fa-check"></i> {{ session('success') }}
            </div>
        @endif

        <div class="row" style="margin-bottom:10px;">
            <div class="col-md-12">
                <a href="{{ route('ponto.banco-horas.index') }}" class="btn btn-default">
                    <i class="fa fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="small-box {{ $corBox }}">
                    <div class="inner">
                        <h3>{{ $fmtMin($sm) }}</h3>
                        <p>Saldo atual</p>
                    </div>
                    <div class="icon"><i class="fa fas fa-balance-scale"></i></div>
                </div>
            </div>

            <div class="col-md-8">
                @component('components.widget', ['class' => 'box-warning'])
                    @slot('title')
                        <i class="fa fas fa-sliders-h"></i>
                        Ajuste manual
                        <small class="text-muted">— registra lançamento no ledger (imutável)</small>
                    @endslot

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul style="margin-bottom:0;">
                                @foreach ($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST"
                          action="{{ route('ponto.banco-horas.ajuste', $saldo->colaborador_config_id) }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="minutos">Minutos <span class="text-red">*</span></label>
                                    <input type="number"
                                           name="minutos"
                                           id="minutos"
                                           class="form-control"
                                           required
                                           placeholder="Use negativo para débito">
                                    <small class="text-muted">Ex.: 60 (crédito 1h), −30 (débito 30min)</small>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="observacao">Observação <span class="text-red">*</span></label>
                                    <input type="text"
                                           name="observacao"
                                           id="observacao"
                                           class="form-control"
                                           required
                                           maxlength="500"
                                           placeholder="Motivo do ajuste (obrigatório)…">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-warning"
                                onclick="return confirm('Registrar ajuste manual no ledger?');">
                            <i class="fa fas fa-save"></i> Registrar ajuste
                        </button>
                    </form>
                @endcomponent
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-list"></i>
                        Histórico de movimentos
                        <small class="text-muted">({{ $movimentos->total() }} lançamentos)</small>
                    @endslot

                    <div class="table-responsive">
                        <table class="table table-striped table-condensed">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Referência</th>
                                    <th>Origem</th>
                                    <th class="text-right">Minutos</th>
                                    <th>Observação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($movimentos as $m)
                                    @php
                                        $mm = (int) $m->minutos;
                                        if ($mm > 0)     { $corLinha = 'text-green'; $sinal = '+'; }
                                        elseif ($mm < 0) { $corLinha = 'text-red';   $sinal = ''; }
                                        else             { $corLinha = 'text-muted'; $sinal = ''; }
                                    @endphp
                                    <tr>
                                        <td>
                                            <small>
                                                {{ optional($m->created_at)->format('d/m/Y H:i') }}
                                            </small>
                                        </td>
                                        <td>
                                            @if ($m->data_referencia)
                                                {{ \Carbon\Carbon::parse($m->data_referencia)->format('d/m/Y') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            <span class="label label-default">{{ $m->origem ?: '—' }}</span>
                                        </td>
                                        <td class="text-right {{ $corLinha }}">
                                            <strong>{{ $sinal }}{{ $fmtMin($mm) }}</strong>
                                        </td>
                                        <td>
                                            <small>{{ $m->observacao ?: '—' }}</small>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted" style="padding:24px;">
                                            Nenhuma movimentação registrada.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($movimentos->hasPages())
                        <div class="text-center" style="margin-top:10px;">
                            {!! $movimentos->appends(request()->query())->links() !!}
                        </div>
                    @endif
                @endcomponent
            </div>
        </div>

        <div class="text-center text-muted" style="margin:12px 0;">
            <small>
                <i class="fa fas fa-shield-alt"></i>
                Movimentos de banco de horas são append-only e imutáveis (Portaria MTP 671/2021).
            </small>
        </div>
    </section>
@endsection
