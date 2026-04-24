@extends('layouts.app')

@section('title', 'Status de Login por Máquina — Office Impresso')

@section('css')
@include('officeimpresso::layouts.partials.design-system')
@endsection

@section('content')
@include('officeimpresso::layouts.nav')

<section class="content-header">
    <h1>Status de Login por Máquina
        <small>últimas 24h</small>
    </h1>
</section>

<section class="content">
    {{-- KPIs --}}
    <div class="row oi-kpi-row">
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="oi-kpi">
                <div class="icon bg-blue"><i class="fa fa-desktop"></i></div>
                <div>
                    <div class="label">Máquinas ativas</div>
                    <div class="value">{{ $maquinas->count() }}</div>
                    <div class="delta">logaram nas últimas 24h</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="oi-kpi">
                <div class="icon bg-green"><i class="fa fa-check"></i></div>
                <div>
                    <div class="label">Logins OK</div>
                    <div class="value">{{ number_format($kpis['login_success']) }}</div>
                    <div class="delta">total 24h</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="oi-kpi">
                <div class="icon bg-red"><i class="fa fa-lock"></i></div>
                <div>
                    <div class="label">Bloqueadas logando</div>
                    <div class="value">{{ $maquinas->where('was_blocked_last', true)->count() }}</div>
                    <div class="delta">acessos com bloqueio ativo</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="oi-kpi">
                <div class="icon bg-amber"><i class="fa fa-exclamation-triangle"></i></div>
                <div>
                    <div class="label">Erros auth</div>
                    <div class="value">{{ number_format($kpis['login_error']) }}</div>
                    <div class="delta">credencial inválida</div>
                </div>
            </div>
        </div>
    </div>

    {{-- STATUS POR MÁQUINA (view principal) --}}
    <div class="oi-card">
        <div class="hdr">
            <h3><i class="fa fa-desktop"></i> Máquinas que logaram <small style="color:#6b7280;">({{ $maquinas->count() }})</small></h3>
            <small style="color: #6b7280;">Status mostra o bloqueio <strong>no momento do último login</strong></small>
        </div>
        <div class="body no-pad">
            <table class="oi-table">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Máquina (HD)</th>
                        <th>IP</th>
                        <th>Último Login</th>
                        <th style="text-align: center;">Logins 24h</th>
                        <th>Estado no Último Login</th>
                        <th>Estado Atual</th>
                        <th style="width: 100px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($maquinas as $m)
                        <tr>
                            <td>
                                @if($m->business_id)
                                    <a href="{{ url('/officeimpresso/licenca_computado/licencas/' . $m->business_id) }}" class="text-primary">
                                        <strong>{{ $m->business_name }}</strong>
                                    </a>
                                @else
                                    <em class="text-muted">desconhecido</em>
                                @endif
                            </td>
                            <td>
                                @if($m->hd)
                                    <strong class="text-mono">{{ $m->hd }}</strong>
                                @else
                                    <em class="text-muted" title="Delphi ainda não envia o hd — aguardando atualização">sem hd</em>
                                @endif
                            </td>
                            <td class="text-mono">{{ $m->ip ?: '—' }}</td>
                            <td class="text-mono">{{ \Carbon\Carbon::parse($m->last_login)->format('d/m/Y H:i:s') }}</td>
                            <td style="text-align: center;">
                                <span class="oi-pill oi-pill-neutral">{{ $m->login_count_24h }}</span>
                            </td>
                            <td>
                                @if($m->was_blocked_last)
                                    <span class="oi-pill oi-pill-blocked"><i class="fa fa-lock"></i> Bloqueada quando logou</span>
                                @else
                                    <span class="oi-pill oi-pill-ok"><i class="fa fa-check"></i> Liberada</span>
                                @endif
                            </td>
                            <td>
                                @if($m->business_blocked)
                                    <span class="oi-pill oi-pill-blocked">🔒 Empresa bloqueada</span>
                                @else
                                    <span class="oi-pill oi-pill-ok">Ativa</span>
                                @endif
                            </td>
                            <td>
                                @if($m->business_id)
                                    <a href="{{ url('/officeimpresso/licenca_log?business_id=' . $m->business_id) }}#timeline" class="oi-btn oi-btn-ghost oi-btn-xs" title="Ver timeline detalhada">
                                        <i class="fa fa-list"></i> Timeline
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: #9ca3af;">
                                Nenhuma máquina logou nas últimas 24h.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin: 24px 0 12px;" id="timeline">
        <h3 style="font-size: 16px; color: #374151;"><i class="fa fa-list"></i> Timeline detalhada de eventos</h3>
        <small style="color: #6b7280;">Todos os eventos (login, api_call, block, etc.) — filtros abaixo</small>
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
