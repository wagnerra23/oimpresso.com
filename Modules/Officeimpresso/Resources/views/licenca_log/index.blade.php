@extends('layouts.app')

@section('title', 'Log de Acesso — Office Impresso')

@section('content')
@include('officeimpresso::layouts.nav')

<section class="content-header">
    <h1>Log de Acesso do Desktop
        <small>últimas 24h</small>
    </h1>
</section>

<section class="content">
    {{-- KPIs --}}
    <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fas fa-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Logins com sucesso</span>
                    <span class="info-box-number">{{ number_format($kpis['login_success']) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-red"><i class="fa fas fa-times"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Logins com erro</span>
                    <span class="info-box-number">{{ number_format($kpis['login_error']) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fas fa-exchange-alt"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Chamadas API</span>
                    <span class="info-box-number">{{ number_format($kpis['api_call']) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-yellow"><i class="fa fas fa-lock"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Bloqueios</span>
                    <span class="info-box-number">{{ number_format($kpis['block']) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Filtros</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-md-3">
                    <label>Evento</label>
                    <select id="filter_event" class="form-control">
                        <option value="">Todos</option>
                        @foreach ($events as $ev)
                            <option value="{{ $ev }}">{{ $ev }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label>De</label>
                    <input type="date" id="filter_from" class="form-control">
                </div>
                <div class="col-md-3">
                    <label>Até</label>
                    <input type="date" id="filter_to" class="form-control">
                </div>
                <div class="col-md-3" style="padding-top: 25px;">
                    <button id="btn_apply" class="btn btn-primary"><i class="fa fa-search"></i> Aplicar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="box box-primary">
        <div class="box-body">
            <table id="licenca_log_table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Evento</th>
                        <th>Origem</th>
                        <th>IP</th>
                        <th>Endpoint</th>
                        <th>Status</th>
                        <th>Duração</th>
                        <th>Erro</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</section>

@endsection

@section('javascript')
<script>
$(function () {
    var table = $('#licenca_log_table').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 25,
        order: [[0, 'desc']],
        ajax: {
            url: "{{ route('licenca_log.index') }}",
            data: function (d) {
                d.event = $('#filter_event').val();
                d.from  = $('#filter_from').val();
                d.to    = $('#filter_to').val();
            }
        },
        columns: [
            { data: 'created_at',   name: 'created_at' },
            { data: 'event',        name: 'event' },
            { data: 'source_badge', name: 'source', orderable: false, searchable: false },
            { data: 'ip',           name: 'ip' },
            { data: 'endpoint',     name: 'endpoint' },
            { data: 'http_status',  name: 'http_status' },
            { data: 'duration_ms',  name: 'duration_ms', orderable: false },
            { data: 'error_message',name: 'error_message' },
        ]
    });

    $('#btn_apply').click(function () { table.ajax.reload(); });
});
</script>
@endsection
