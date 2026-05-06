@extends('pontowr2::layouts.module')

@section('title', __('pontowr2::ponto.menu.intercorrencias'))

@section('module_content')
    @php
        $labelsEstado = [
            'RASCUNHO'  => 'label-default',
            'PENDENTE'  => 'label-warning',
            'APROVADA'  => 'label-success',
            'REJEITADA' => 'label-danger',
            'APLICADA'  => 'label-primary',
            'CANCELADA' => 'label-default',
        ];
    @endphp

    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.module_label') }}
            <small>{{ __('pontowr2::ponto.menu.intercorrencias') }}</small>
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
                <a href="{{ route('ponto.intercorrencias.create') }}" class="btn btn-primary">
                    <i class="fa fas fa-plus"></i> Nova intercorrência
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-exclamation-circle"></i>
                        {{ __('pontowr2::ponto.menu.intercorrencias') }}
                        <small class="text-muted">
                            ({{ $intercorrencias->total() }} {{ $intercorrencias->total() === 1 ? 'item' : 'itens' }})
                        </small>
                    @endslot

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Colaborador</th>
                                    <th>Tipo</th>
                                    <th>Data</th>
                                    <th>Estado</th>
                                    <th>Prioridade</th>
                                    <th class="text-right">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($intercorrencias as $i)
                                    <tr>
                                        <td><code>{{ $i->codigo ?: substr($i->id, 0, 8) }}</code></td>
                                        <td>
                                            <i class="fa fas fa-user text-muted"></i>
                                            {{ optional(optional($i->colaborador)->user)->first_name }}
                                            {{ optional(optional($i->colaborador)->user)->last_name }}
                                        </td>
                                        <td>{{ __('pontowr2::ponto.intercorrencia.tipos.' . $i->tipo) }}</td>
                                        <td>
                                            {{ $i->data->format('d/m/Y') }}
                                            @if ($i->dia_todo)
                                                <br><small class="text-muted"><i class="fa fas fa-calendar-day"></i> Dia todo</small>
                                            @elseif ($i->intervalo_inicio)
                                                <br><small class="text-muted">
                                                    {{ substr($i->intervalo_inicio, 0, 5) }}–{{ substr($i->intervalo_fim, 0, 5) }}
                                                </small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="label {{ $labelsEstado[$i->estado] ?? 'label-default' }}">
                                                {{ __('pontowr2::ponto.intercorrencia.estados.' . $i->estado) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if ($i->prioridade === 'URGENTE')
                                                <span class="label label-danger">Urgente</span>
                                            @else
                                                <span class="label label-default">Normal</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <a href="{{ route('ponto.intercorrencias.show', $i->id) }}" class="btn btn-default btn-xs">
                                                <i class="fa fas fa-eye"></i>
                                            </a>
                                            @if ($i->estado === 'RASCUNHO')
                                                <a href="{{ route('ponto.intercorrencias.edit', $i->id) }}" class="btn btn-warning btn-xs">
                                                    <i class="fa fas fa-edit"></i>
                                                </a>
                                                <form method="POST"
                                                      action="{{ route('ponto.intercorrencias.submeter', $i->id) }}"
                                                      style="display:inline-block;"
                                                      onsubmit="return confirm('Submeter para aprovação?');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-primary btn-xs">
                                                        <i class="fa fas fa-paper-plane"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted" style="padding:24px;">
                                            <i class="fa fas fa-inbox" style="font-size:24px;"></i><br>
                                            Nenhuma intercorrência registrada ainda.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($intercorrencias->hasPages())
                        <div class="text-center" style="margin-top:10px;">
                            {!! $intercorrencias->appends(request()->query())->links() !!}
                        </div>
                    @endif
                @endcomponent
            </div>
        </div>
    </section>
@endsection
