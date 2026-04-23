@extends('layouts.app')

@section('title', __('officeimpresso::lang.officeimpresso'))

@section('content')
<section class="content-header">
    <h1>@lang('officeimpresso::lang.computadores_cadastrados', [], 'Computadores Cadastrados')</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-header with-border">
            <a href="{{ route('licenca_computador.create') }}" class="btn btn-primary">
                <i class="fa fa-plus"></i> @lang('officeimpresso::lang.create_client', [], 'Cadastrar')
            </a>
        </div>
        <div class="box-body">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>HD</th>
                        <th>@lang('officeimpresso::lang.user_win', [], 'User Win')</th>
                        <th>@lang('officeimpresso::lang.processador', [], 'Processador')</th>
                        <th>@lang('officeimpresso::lang.memoria', [], 'Memória')</th>
                        <th>@lang('officeimpresso::lang.versao_exe', [], 'Versão Executável')</th>
                        <th>@lang('officeimpresso::lang.bloqueado', [], 'Bloqueado')</th>
                        <th>@lang('officeimpresso::lang.action', [], 'Ação')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($licencas as $licenca)
                        <tr>
                            <td>{{ $licenca->id }}</td>
                            <td>{{ $licenca->hd }}</td>
                            <td>{{ $licenca->user_win }}</td>
                            <td>{{ $licenca->processador }}</td>
                            <td>{{ $licenca->memoria }}</td>
                            <td>{{ $licenca->versao_exe }}</td>
                            <td>
                                @if($licenca->bloqueado)
                                    <span class="label label-danger">Bloqueado</span>
                                @else
                                    <span class="label label-success">Liberado</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('licenca_computador.toggleBlock', $licenca->id) }}" class="btn btn-xs btn-warning">
                                    {{ $licenca->bloqueado ? 'Desbloquear' : 'Bloquear' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">Nenhuma licença cadastrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
