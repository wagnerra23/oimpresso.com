@extends('layouts.app')
@section('title', __('officeimpresso::lang.licencas'))

@section('vue')
<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('officeimpresso::lang.licencas')</h1>
</section>

@if(empty($is_demo))
<section class="content">
    @component('components.widget', ['class' => 'box-solid', 'title' => __('officeimpresso::lang.licencas')])
        @slot('tool')
            <div class="box-tools">
                @can('superadmin')
                    <a href="{{action('\Modules\Officeimpresso\Http\Controllers\LicencaController@regenerate')}}" class="btn btn-block btn-default">
                        <i class="fas fa-plus"></i> @lang('officeimpresso::lang.regenerate_doc')
                    </a>
                @endcan

                <button type="button" class="btn btn-block btn-primary btn-modal"
                    data-toggle="modal"
                    data-target="#create_client_modal">
                    <i class="fas fa-plus"></i> @lang('officeimpresso::lang.create_client')
                </button>
            </div>
        @endslot
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="licencas_table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>@lang('officeimpresso::lang.ipinterno')</th>
                        <th>@lang('officeimpresso::lang.ultimoacesso')</th>
                        <th>@lang('officeimpresso::lang.cliente')</th>
                        <th>@lang('officeimpresso::lang.cnpjcpf')</th>
                        <th>@lang('officeimpresso::lang.versaodisponivel')</th>
                        <th>@lang('officeimpresso::lang.versaominima')</th>
                        <th>@lang('officeimpresso::lang.bloqueado')</th>
                        <th>@lang('officeimpresso::lang.quantidademaquinas')</th>
                        <th>@lang('officeimpresso::lang.client_secret')</th>
                        <th>@lang('messages.action')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($licencas as $licenca)
                        <tr>
                            <td>{{ $licenca->id }}</td>
                            <td>{{ $licenca->ip_interno }}</td>
                            <td>{{ $licenca->ultimo_acesso }}</td>
                            <td>{{ $licenca->cliente }}</td>
                            <td>{{ $licenca->cnpj_cpf }}</td>
                            <td>{{ $licenca->versao_disponivel }}</td>
                            <td>{{ $licenca->versao_minima }}</td>
                            <td>{{ $licenca->bloqueado ? 'Sim' : 'Não' }}</td>
                            <td>{{ $licenca->quantidade_maquinas }}</td>
                            <td>{{ $licenca->client_secret }}</td>
                            <td>
                                {!! Form::open(['url' => action('\Modules\Officeimpresso\Http\Controllers\LicencaController@destroy', [$licenca->id]), 'method' => 'delete', 'id' => 'delete_client_form_' . $licenca->id ]) !!}
                                    <button type="submit" class="btn btn-danger btn-xs">
                                        <i class="fas fa-trash"></i> @lang('messages.delete')
                                    </button>
                                {!! Form::close() !!}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endcomponent
</section>
@else
<section>
    <div class="col-md-12 text-danger">
        <br/>
        @lang('lang_v1.disabled_in_demo')
    </div>
</section>
@endif

<!-- Create Client Modal -->
<div class="modal fade" id="create_client_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">

            {!! Form::open(['url' => action('\Modules\Officeimpresso\Http\Controllers\LicencaController@store'), 'method' => 'post', 'id' => 'create_client_form' ]) !!}

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
        licencas_table = $('#licencas_table').DataTable();
    });
</script>
@endsection
