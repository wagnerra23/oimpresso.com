@extends('pontowr2::layouts.module')

@section('title', 'Intercorrência ' . ($intercorrencia->codigo ?: substr($intercorrencia->id, 0, 8)))

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

        $nomeColab = trim(optional(optional($intercorrencia->colaborador)->user)->first_name . ' '
                        . optional(optional($intercorrencia->colaborador)->user)->last_name);
        if ($nomeColab === '') {
            $nomeColab = 'Colaborador #' . $intercorrencia->colaborador_config_id;
        }
    @endphp

    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.menu.intercorrencias') }}
            <small>{{ $intercorrencia->codigo ?: substr($intercorrencia->id, 0, 8) }}</small>
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
                <a href="{{ route('ponto.intercorrencias.index') }}" class="btn btn-default">
                    <i class="fa fas fa-arrow-left"></i> Voltar
                </a>

                @if ($intercorrencia->estado === 'RASCUNHO')
                    <a href="{{ route('ponto.intercorrencias.edit', $intercorrencia->id) }}" class="btn btn-warning">
                        <i class="fa fas fa-edit"></i> Editar
                    </a>
                    <form method="POST"
                          action="{{ route('ponto.intercorrencias.submeter', $intercorrencia->id) }}"
                          style="display:inline-block;"
                          onsubmit="return confirm('Submeter para aprovação?');">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fas fa-paper-plane"></i> Submeter para aprovação
                        </button>
                    </form>
                @endif

                @if (in_array($intercorrencia->estado, ['RASCUNHO', 'PENDENTE']))
                    <form method="POST"
                          action="{{ route('ponto.intercorrencias.cancelar', $intercorrencia->id) }}"
                          style="display:inline-block;"
                          onsubmit="return confirm('Tem certeza que deseja cancelar?');">
                        @csrf
                        <button type="submit" class="btn btn-danger">
                            <i class="fa fas fa-ban"></i> Cancelar
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-info-circle"></i>
                        Dados da intercorrência
                    @endslot

                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Colaborador:</strong> {{ $nomeColab }}</p>
                            <p><strong>Tipo:</strong>
                                {{ __('pontowr2::ponto.intercorrencia.tipos.' . $intercorrencia->tipo) }}
                            </p>
                            <p><strong>Data:</strong>
                                {{ $intercorrencia->data->format('d/m/Y') }}
                            </p>
                            <p><strong>Intervalo:</strong>
                                @if ($intercorrencia->dia_todo)
                                    <span class="label label-info">Dia todo</span>
                                @else
                                    {{ substr($intercorrencia->intervalo_inicio, 0, 5) }}
                                    –
                                    {{ substr($intercorrencia->intervalo_fim, 0, 5) }}
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Estado:</strong>
                                <span class="label {{ $labelsEstado[$intercorrencia->estado] ?? 'label-default' }}">
                                    {{ __('pontowr2::ponto.intercorrencia.estados.' . $intercorrencia->estado) }}
                                </span>
                            </p>
                            <p><strong>Prioridade:</strong>
                                @if ($intercorrencia->prioridade === 'URGENTE')
                                    <span class="label label-danger">Urgente</span>
                                @else
                                    <span class="label label-default">Normal</span>
                                @endif
                            </p>
                            <p><strong>Impacta apuração:</strong>
                                {{ $intercorrencia->impacta_apuracao ? 'Sim' : 'Não' }}
                            </p>
                            <p><strong>Descontar banco de horas:</strong>
                                {{ $intercorrencia->descontar_banco_horas ? 'Sim' : 'Não' }}
                            </p>
                        </div>
                    </div>

                    <div class="form-group">
                        <strong>Justificativa:</strong>
                        <div class="well well-sm" style="margin-top:5px;">
                            {{ $intercorrencia->justificativa }}
                        </div>
                    </div>

                    @if ($intercorrencia->anexo_path)
                        <p>
                            <strong>Anexo:</strong>
                            <a href="#" class="btn btn-default btn-xs">
                                <i class="fa fas fa-paperclip"></i> {{ basename($intercorrencia->anexo_path) }}
                            </a>
                        </p>
                    @endif
                @endcomponent
            </div>

            <div class="col-md-4">
                @component('components.widget', ['class' => 'box-info'])
                    @slot('title')
                        <i class="fa fas fa-history"></i>
                        Rastreio
                    @endslot

                    <p>
                        <strong>Solicitante:</strong><br>
                        {{ optional($intercorrencia->solicitante)->first_name }}
                        {{ optional($intercorrencia->solicitante)->last_name }}
                        <br>
                        <small class="text-muted">
                            Criada em {{ optional($intercorrencia->created_at)->format('d/m/Y H:i') }}
                        </small>
                    </p>

                    @if ($intercorrencia->aprovador_id)
                        <hr>
                        <p>
                            <strong>Aprovador:</strong><br>
                            {{ optional($intercorrencia->aprovador)->first_name }}
                            {{ optional($intercorrencia->aprovador)->last_name }}
                            <br>
                            <small class="text-muted">
                                Decisão em {{ optional($intercorrencia->aprovado_em)->format('d/m/Y H:i') }}
                            </small>
                        </p>
                    @endif

                    @if ($intercorrencia->motivo_rejeicao)
                        <hr>
                        <div class="alert alert-danger" style="margin-bottom:0;">
                            <strong>Motivo da rejeição:</strong><br>
                            {{ $intercorrencia->motivo_rejeicao }}
                        </div>
                    @endif
                @endcomponent
            </div>
        </div>
    </section>
@endsection
