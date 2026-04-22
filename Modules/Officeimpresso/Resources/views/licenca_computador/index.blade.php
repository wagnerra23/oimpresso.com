@extends('layouts.app')
@section('title', __('officeimpresso::lang.licencas'))

@section('vue')
<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('officeimpresso::lang.licencas')</h1>
</section>

<section class="content">
    @component('components.widget', ['class' => 'box-solid', 'title' => __('officeimpresso::lang.licencas')])
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="licencas_table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>@lang('officeimpresso::lang.descricao')</th>
                        <th>@lang('officeimpresso::lang.ip_interno')</th>
                        <th>@lang('officeimpresso::lang.exe_versao')</th>
                        <th>@lang('officeimpresso::lang.dt_ultimoacesso')</th>
                        <th>@lang('officeimpresso::lang.bloqueado')</th>
                        <th>@lang('messages.action')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($licencas as $licenca)
                        <tr>
                            <td>{{ $licenca->id }}</td>
                            <td>{{ $licenca->descricao }}</td>
                            <td>{{ $licenca->ip_interno }}</td>
                            <td>{{ $licenca->versao_exe }}</td>
                            <td>{{ $licenca->dt_ultimoacesso }}</td>
                            <td>
                                @if($licenca->bloqueado)
                                    <span class="label label-danger">@lang('officeimpresso::lang.bloqueado')</span>
                                @else
                                    <span class="label label-success">@lang('officeimpresso::lang.liberado')</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ url('officeimpresso/licenca_computador/' . $licenca->id . '/toggle-block') }}"
                                   class="btn btn-xs {{ $licenca->bloqueado ? 'btn-success' : 'btn-danger' }}">
                                    {{ $licenca->bloqueado ? __('officeimpresso::lang.liberar') : __('officeimpresso::lang.bloquear') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endcomponent
</section>
@stop

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        $('#licencas_table').DataTable();
    });
</script>
@endsection
