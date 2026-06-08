@extends('pontowr2::layouts.module')

@section('title', 'Importação #' . $importacao->id)

@section('module_content')
    @php
        $labelEstado = [
            'PENDENTE'            => 'label-default',
            'PROCESSANDO'         => 'label-info',
            'CONCLUIDA'           => 'label-success',
            'CONCLUIDA_COM_ERROS' => 'label-warning',
            'FALHOU'              => 'label-danger',
        ];

        $fmtBytes = function ($b) {
            $b = (int) $b;
            if ($b < 1024)       { return $b . ' B'; }
            if ($b < 1048576)    { return round($b / 1024, 1) . ' KB'; }
            if ($b < 1073741824) { return round($b / 1048576, 1) . ' MB'; }
            return round($b / 1073741824, 2) . ' GB';
        };
    @endphp

    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.menu.importacoes') }}
            <small>Importação #{{ $importacao->id }}</small>
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
                <a href="{{ route('ponto.importacoes.index') }}" class="btn btn-default">
                    <i class="fa fas fa-arrow-left"></i> Voltar
                </a>
                <a href="{{ route('ponto.importacoes.original', $importacao->id) }}" class="btn btn-info">
                    <i class="fa fas fa-download"></i> Baixar arquivo original
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-file-alt"></i>
                        Dados do arquivo
                    @endslot

                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>ID:</strong> <code>#{{ $importacao->id }}</code></p>
                            <p><strong>Nome do arquivo:</strong><br>
                                <small>{{ $importacao->nome_arquivo }}</small>
                            </p>
                            <p><strong>Tipo:</strong>
                                <span class="label label-info">{{ $importacao->tipo }}</span>
                            </p>
                            <p><strong>Tamanho:</strong> {{ $fmtBytes($importacao->tamanho_bytes) }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Estado:</strong>
                                <span class="label {{ $labelEstado[$importacao->estado] ?? 'label-default' }}">
                                    {{ $importacao->estado }}
                                </span>
                            </p>
                            <p><strong>Usuário:</strong>
                                {{ optional($importacao->usuario)->first_name }}
                                {{ optional($importacao->usuario)->last_name }}
                            </p>
                            <p><strong>Importado em:</strong>
                                {{ optional($importacao->created_at)->format('d/m/Y H:i:s') }}
                            </p>
                            <p><strong>Iniciado em:</strong>
                                {{ optional($importacao->iniciado_em)->format('d/m/Y H:i:s') ?: '—' }}
                            </p>
                            <p><strong>Concluído em:</strong>
                                {{ optional($importacao->concluido_em)->format('d/m/Y H:i:s') ?: '—' }}
                            </p>
                        </div>
                    </div>

                    <p><strong>Hash SHA-256:</strong><br>
                        <code style="word-break:break-all; font-size:11px;">{{ $importacao->hash_arquivo }}</code>
                    </p>
                @endcomponent

                @if (!empty($importacao->log))
                    @component('components.widget', ['class' => 'box-warning'])
                        @slot('title')
                            <i class="fa fas fa-info-circle"></i>
                            Diagnóstico do processamento
                        @endslot

                        <pre style="white-space:pre-wrap; background:#f9f9f9; padding:10px; border-radius:4px; margin:0;">{{ $importacao->log }}</pre>
                    @endcomponent
                @endif

                @if (!empty($importacao->erros_amostra))
                    @component('components.widget', ['class' => 'box-danger'])
                        @slot('title')
                            <i class="fa fas fa-exclamation-triangle"></i>
                            Amostra de erros ({{ count($importacao->erros_amostra) }} primeiros)
                        @endslot

                        <table class="table table-condensed table-striped" style="margin-bottom:0;">
                            <thead>
                                <tr>
                                    <th style="width:70px;">Linha</th>
                                    <th style="width:90px;">NSR</th>
                                    <th style="width:55px;">Tipo</th>
                                    <th>Mensagem</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($importacao->erros_amostra as $erro)
                                    <tr>
                                        <td>{{ $erro['linha'] ?? '—' }}</td>
                                        <td>{{ $erro['nsr'] ?? '—' }}</td>
                                        <td>{{ $erro['tipo'] ?? '—' }}</td>
                                        <td><small>{{ $erro['erro'] ?? '' }}</small></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endcomponent
                @endif
            </div>

            <div class="col-md-4">
                @component('components.widget', ['class' => 'box-info'])
                    @slot('title')
                        <i class="fa fas fa-chart-bar"></i>
                        Resumo do processamento
                    @endslot

                    <p>
                        <i class="fa fas fa-list text-muted"></i>
                        <strong>Linhas totais:</strong>
                        {{ $importacao->linhas_total ?: 0 }}
                    </p>
                    <p>
                        <i class="fa fas fa-check-circle text-green"></i>
                        <strong>Linhas processadas:</strong>
                        {{ $importacao->linhas_processadas ?: 0 }}
                    </p>
                    <p>
                        <i class="fa fas fa-plus-circle text-green"></i>
                        <strong>Marcações criadas:</strong>
                        {{ $importacao->linhas_sucesso ?: 0 }}
                    </p>
                    <p>
                        <i class="fa fas fa-exclamation-triangle text-red"></i>
                        <strong>Erros:</strong>
                        {{ $importacao->linhas_erro ?: 0 }}
                    </p>

                    @if ($importacao->linhas_total > 0)
                        @php
                            $pct = (int) round(($importacao->linhas_processadas / $importacao->linhas_total) * 100);
                        @endphp
                        <div class="progress" style="margin-top:10px;">
                            <div class="progress-bar progress-bar-success"
                                 role="progressbar"
                                 style="width: {{ $pct }}%;">
                                {{ $pct }}%
                            </div>
                        </div>
                    @endif
                @endcomponent
            </div>
        </div>
    </section>
@endsection
