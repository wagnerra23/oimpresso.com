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
                        <th>@lang('officeimpresso::lang.ultimoacesso')</th>
                        <th>@lang('officeimpresso::lang.fantasia')</th>
                        <th>@lang('officeimpresso::lang.razao_social')</th>
                        <th>@lang('officeimpresso::lang.cnpjcpf')</th>
                        <th>@lang('officeimpresso::lang.versaodisponivel')</th>
                        <th>@lang('officeimpresso::lang.versaominima')</th>
                        <th>@lang('officeimpresso::lang.quantidademaquinas')</th>
                        <th>@lang('officeimpresso::lang.client_secret')</th>
                        <th>@lang('officeimpresso::lang.caminho_banco')</th>
                        <th>@lang('officeimpresso::lang.bloqueado')</th>
                        <th>@lang('messages.action')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($business as $busine)
                        <tr>
                            <td>{{ $busine->id }}</td>
                            <td>{{ $busine->ultimo_acesso }}</td>
                            <td>{{ $busine->name }}</td>
                            <td>{{ $busine->razao_social }}</td>
                            <td>{{ $busine->cnpj_cpf }}</td>
                            <td>{{ $busine->versao_disponivel }}</td>
                            <td>{{ $busine->versao_minima }}</td>
                            <td>{{ $busine->quantidade_maquinas }}</td>
                            <td>{{ $busine->caminho_banco }}</td>
                            <td>{{ $busine->client_secret }}</td>
                            <td>{{ $busine->bloqueado ? 'Sim' : 'Não' }}</td>
                           <td>
                                {!! Form::open(['url' => action('\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController@bloquear', [$busine->id]), 'method' => 'bloquear', 'id' => 'bloquear_busine_form_' . $busine->id ]) !!}
                                    <button type="submit" class="btn btn-danger btn-xs">
                                        <i class="fas fa-trash"></i> @lang('messages.bloquear')
                                    </button>
                                {!! Form::close() !!}
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


<!-- Create Client Modal -->
<div class="modal fade" id="create_client_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">

            {!! Form::open(['url' => action('\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController@store'), 'method' => 'post', 'id' => 'create_client_form' ]) !!}

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">@lang('officeimpresso::lang.create_client')</h4>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    {!! Form::label('name', __('user.name') . ':*') !!}
                    {!! Form::text('name', null, ['class' => 'form-control', 'required', 'placeholder' => __('user.name')]) !!}
                </div>
            </div>

            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">@lang('messages.save')</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
            </div>

            {!! Form::close() !!}

        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div>
@stop

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        business_table = $('#business_table').DataTable();
    });
</script>
@endsection
