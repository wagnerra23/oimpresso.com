@extends('layouts.app')

@section('title', 'Log de Acesso — Office Impresso')

@section('css')
@include('officeimpresso::layouts.partials.design-system')
@endsection

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
        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 14px;">
            <div class="oi-kpi">
                <div class="icon bg-green"><i class="fa fa-check"></i></div>
                <div>
                    <div class="label">Sucessos</div>
                    <div class="value">{{ number_format($kpis['login_success']) }}</div>
                    <div class="delta">logins nas últimas 24h</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 14px;">
            <div class="oi-kpi">
                <div class="icon bg-red"><i class="fa fa-times"></i></div>
                <div>
                    <div class="label">Erros</div>
                    <div class="value">{{ number_format($kpis['login_error']) }}</div>
                    <div class="delta">tentativas falhas</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 14px;">
            <div class="oi-kpi">
                <div class="icon bg-blue"><i class="fa fa-exchange-alt"></i></div>
                <div>
                    <div class="label">Chamadas API</div>
                    <div class="value">{{ number_format($kpis['api_call']) }}</div>
                    <div class="delta">/api/officeimpresso/*</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 14px;">
            <div class="oi-kpi">
                <div class="icon bg-amber"><i class="fa fa-lock"></i></div>
                <div>
                    <div class="label">Bloqueios</div>
                    <div class="value">{{ number_format($kpis['block']) }}</div>
                    <div class="delta">licenças bloqueadas</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="oi-filter-bar">
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
            <div class="col-md-3" style="padding-top: 24px;">
                <button id="btn_apply" class="btn btn-primary"><i class="fa fa-search"></i> Aplicar</button>
                <button id="btn_clear" class="btn btn-default"><i class="fa fa-eraser"></i> Limpar</button>
            </div>
        </div>
        @if($filter_licenca_id ?? null)
            <div class="alert alert-info" style="margin-top: 10px; margin-bottom: 0;">
                <i class="fa fa-filter"></i> Filtrado por <strong>licença #{{ $filter_licenca_id }}</strong>.
                <a href="{{ route('licenca_log.index') }}">Remover filtro</a>
            </div>
        @endif
    </div>

    {{-- Tabela --}}
    <div class="box box-primary" style="border-top-color: #3b82f6;">
        <div class="box-body">
            <table id="licenca_log_table" class="table table-hover">
                <thead>
                    <tr>
                        <th style="width: 150px;">Data/Hora</th>
                        <th style="width: 120px;">Evento</th>
                        <th style="width: 100px;">Bloqueado?</th>
                        <th style="width: 170px;">Empresa</th>
                        <th style="width: 150px;">Máquina</th>
                        <th style="width: 100px;">IP</th>
                        <th>Endpoint / Erro</th>
                        <th style="width: 65px;">Status</th>
                        <th style="width: 75px;">Duração</th>
                        <th style="width: 90px;">Origem</th>
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
    var initialLicencaId  = {!! json_encode($filter_licenca_id ?? null) !!};
    var initialBusinessId = {!! json_encode($filter_business_id ?? null) !!};

    var table = $('#licenca_log_table').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 25,
        order: [[0, 'desc']],
        language: {
            processing: 'Carregando…',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_ registros',
            info: 'Mostrando _START_ a _END_ de _TOTAL_',
            infoEmpty: 'Nenhum registro',
            infoFiltered: '(filtrado de _MAX_)',
            zeroRecords: 'Nenhum log encontrado',
            emptyTable: 'Nenhum log ainda. Triggers MySQL irão preencher assim que um desktop autenticar.',
            paginate: { first: '«', previous: '‹', next: '›', last: '»' }
        },
        ajax: {
            url: "{{ route('licenca_log.index') }}",
            data: function (d) {
                d.event       = $('#filter_event').val();
                d.licenca_id  = initialLicencaId;
                d.business_id = initialBusinessId;
                d.from        = $('#filter_from').val();
                d.to          = $('#filter_to').val();
            }
        },
        columns: [
            { data: 'created_at',    name: 'created_at', className: 'text-mono' },
            { data: 'event',         name: 'event' },
            { data: 'blocked_info',  name: 'blocked',    orderable: false, searchable: false },
            { data: 'business_info', name: 'business_id', orderable: false, searchable: false },
            { data: 'machine_info',  name: 'licenca_id',  orderable: false, searchable: false },
            { data: 'ip',            name: 'ip', className: 'text-mono' },
            { data: 'endpoint',      name: 'endpoint' },
            { data: 'http_status',   name: 'http_status' },
            { data: 'duration_ms',   name: 'duration_ms', orderable: false },
            { data: 'source_badge',  name: 'source', orderable: false, searchable: false },
        ]
    });

    $('#btn_apply').click(function () { table.ajax.reload(); });
    $('#btn_clear').click(function () {
        $('#filter_event').val('');
        $('#filter_from').val('');
        $('#filter_to').val('');
        table.ajax.reload();
    });
});
</script>
@endsection
