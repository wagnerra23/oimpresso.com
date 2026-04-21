@extends('pontowr2::layouts.module')

@section('title', __('pontowr2::ponto.menu.importacoes'))

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
            {{ __('pontowr2::ponto.module_label') }}
            <small>{{ __('pontowr2::ponto.menu.importacoes') }}</small>
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
                <a href="{{ route('ponto.importacoes.create') }}" class="btn btn-primary">
                    <i class="fa fas fa-upload"></i> Nova importação AFD
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-file-import"></i>
                        Histórico de importações
                        <small class="text-muted">({{ $importacoes->total() }} arquivos)</small>
                    @endslot

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Arquivo</th>
                                    <th>Tipo</th>
                                    <th>Tamanho</th>
                                    <th>Estado</th>
                                    <th>Usuário</th>
                                    <th>Importado em</th>
                                    <th class="text-right">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($importacoes as $imp)
                                    <tr>
                                        <td><code>#{{ $imp->id }}</code></td>
                                        <td>
                                            <i class="fa fas fa-file-alt text-muted"></i>
                                            <small>{{ $imp->nome_arquivo }}</small>
                                        </td>
                                        <td>
                                            <span class="label label-info">{{ $imp->tipo }}</span>
                                        </td>
                                        <td>
                                            <small>{{ $fmtBytes($imp->tamanho_bytes) }}</small>
                                        </td>
                                        <td>
                                            <span class="label {{ $labelEstado[$imp->estado] ?? 'label-default' }}">
                                                {{ $imp->estado }}
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                {{ optional($imp->usuario)->first_name }}
                                                {{ optional($imp->usuario)->last_name }}
                                            </small>
                                        </td>
                                        <td>
                                            <small>{{ optional($imp->created_at)->format('d/m/Y H:i') }}</small>
                                        </td>
                                        <td class="text-right">
                                            <a href="{{ route('ponto.importacoes.show', $imp->id) }}"
                                               class="btn btn-default btn-xs">
                                                <i class="fa fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('ponto.importacoes.original', $imp->id) }}"
                                               class="btn btn-default btn-xs"
                                               title="Baixar original">
                                                <i class="fa fas fa-download"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted" style="padding:24px;">
                                            <i class="fa fas fa-folder-open" style="font-size:24px;"></i><br>
                                            Nenhuma importação AFD realizada ainda.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($importacoes->hasPages())
                        <div class="text-center" style="margin-top:10px;">
                            {!! $importacoes->appends(request()->query())->links() !!}
                        </div>
                    @endif
                @endcomponent
            </div>
        </div>
    </section>
@endsection
