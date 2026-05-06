@extends('pontowr2::layouts.module')

@section('title', __('pontowr2::ponto.menu.escalas'))

@section('module_content')
    @php
        $tiposLabel = [
            'FIXA'          => 'Fixa',
            'FLEXIVEL'      => 'Flexível',
            'ESCALA_12X36'  => '12x36',
            'ESCALA_6X1'    => '6x1',
            'ESCALA_5X2'    => '5x2',
        ];

        $fmtMin = function ($min) {
            $min = (int) $min;
            $h = intdiv($min, 60);
            $m = $min % 60;
            return sprintf('%02d:%02d', $h, $m);
        };
    @endphp

    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.module_label') }}
            <small>{{ __('pontowr2::ponto.menu.escalas') }}</small>
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
                <a href="{{ route('ponto.escalas.create') }}" class="btn btn-primary">
                    <i class="fa fas fa-plus"></i> Nova escala
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-calendar-check"></i>
                        Escalas cadastradas
                        <small class="text-muted">({{ $escalas->total() }} no business)</small>
                    @endslot

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nome</th>
                                    <th>Tipo</th>
                                    <th class="text-right">Carga diária</th>
                                    <th class="text-right">Carga semanal</th>
                                    <th>Banco de horas</th>
                                    <th class="text-right">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($escalas as $e)
                                    <tr>
                                        <td><code>{{ $e->codigo ?: '—' }}</code></td>
                                        <td><strong>{{ $e->nome }}</strong></td>
                                        <td>
                                            <span class="label label-info">
                                                {{ $tiposLabel[$e->tipo] ?? $e->tipo }}
                                            </span>
                                        </td>
                                        <td class="text-right">{{ $fmtMin($e->carga_diaria_minutos) }}</td>
                                        <td class="text-right">{{ $fmtMin($e->carga_semanal_minutos) }}</td>
                                        <td>
                                            @if ($e->permite_banco_horas)
                                                <span class="label label-success">Permite</span>
                                            @else
                                                <span class="label label-default">Não</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <a href="{{ route('ponto.escalas.edit', $e->id) }}"
                                               class="btn btn-warning btn-xs">
                                                <i class="fa fas fa-edit"></i>
                                            </a>
                                            <form method="POST"
                                                  action="{{ route('ponto.escalas.destroy', $e->id) }}"
                                                  style="display:inline-block;"
                                                  onsubmit="return confirm('Remover esta escala? Colaboradores vinculados perderão a referência.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-xs">
                                                    <i class="fa fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted" style="padding:24px;">
                                            <i class="fa fas fa-calendar-plus" style="font-size:24px;"></i><br>
                                            Nenhuma escala cadastrada.<br>
                                            <a href="{{ route('ponto.escalas.create') }}">Criar a primeira</a>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($escalas->hasPages())
                        <div class="text-center" style="margin-top:10px;">
                            {!! $escalas->appends(request()->query())->links() !!}
                        </div>
                    @endif
                @endcomponent
            </div>
        </div>
    </section>
@endsection
