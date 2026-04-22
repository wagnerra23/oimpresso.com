@extends('layouts.app')
@section('title', __('officeimpresso::lang.businessall'))

@section('vue')
<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('officeimpresso::lang.businessall')</h1>
</section>

<section class="content">
    @component('components.widget', ['class' => 'box-solid', 'title' => __('officeimpresso::lang.businessall')])
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="business_table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>@lang('officeimpresso::lang.fantasia')</th>
                        <th>@lang('officeimpresso::lang.razao_social')</th>
                        <th>@lang('officeimpresso::lang.cnpjcpf')</th>
                        <th>@lang('officeimpresso::lang.versaodisponivel')</th>
                        <th>@lang('officeimpresso::lang.versaominima')</th>
                        <th>@lang('officeimpresso::lang.quantidademaquinas')</th>
                        <th>@lang('officeimpresso::lang.client_secret')</th>
                        <th>@lang('officeimpresso::lang.caminho_banco')</th>
                        <th>@lang('officeimpresso::lang.ultimoacesso')</th>
                        <th>@lang('officeimpresso::lang.bloqueado')</th>
                        <th>@lang('messages.action')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($business as $busine)
                        <tr>
                            <td>{{ $busine->id }}</td>
                            <td>{{ $busine->name }}</td>
                            <td>{{ $busine->razao_social }}</td>
                            <td>{{ $busine->cnpj }}</td>
                            <td>{{ $busine->versao_disponivel }}</td>
                            <td>{{ $busine->versao_minima }}</td>
                            <td>{{ $busine->quantidade_maquinas }}</td>
                            <td>{{ $busine->caminho_banco }}</td>
                            <td>{{ $busine->client_secret }}</td>
                            <td>{{ $busine->ultimo_acesso }}</td>
                            <td>{{ $busine->bloqueado ? 'Sim' : 'Não' }}</td>
                            <td>
                                <!-- Botão para abrir licenças dos computadores -->
                                <a href="{{ action('\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController@viewLicencas', [$busine->id]) }}" class="btn btn-info btn-xs">
                                    <i class="fas fa-desktop"></i> Ver Licenças
                                </a>
                                @if($busine->officeimpresso_bloqueado)
                                    <a href="{{ action('\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController@toggleBlock', [$busine->id]) }}" class="btn btn-danger btn-xs">
                                        <i class="fas fa-lock"></i> @lang('officeimpresso::lang.bloquear')
                                    </a>
                                @else
                                    <a href="{{ action('\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController@toggleBlock', [$busine->id]) }}" class="btn btn-success btn-xs">
                                        <i class="fas fa-unlock"></i> @lang('officeimpresso::lang.liberado')
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center">@lang('officeimpresso::lang.no_records_found')</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endcomponent
</section>
@stop

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        business_table = $('#business_table').DataTable();
    });
</script>
@endsection
