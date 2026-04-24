@extends('layouts.app')

@section('title', 'Status de Login por Máquina — Office Impresso')

@section('css')
@include('officeimpresso::layouts.partials.design-system')
@endsection

@section('content')
@include('officeimpresso::layouts.nav')

<div class="oi-page">
    <div class="oi-page-header">
        <h1>Status de Login por Máquina</h1>
        <div class="subtitle">Máquinas que logaram nas últimas 24h — status mostra o bloqueio no momento do login</div>
    </div>

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
                <div class="icon bg-amber"><i class="fa fa-lock"></i></div>
                <div>
                    <div class="label">Bloqueadas logando</div>
                    <div class="value">{{ $maquinas->where('was_blocked_last', true)->count() }}</div>
                    <div class="delta">acessos com bloqueio ativo</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="oi-kpi">
                <div class="icon bg-red"><i class="fa fa-times"></i></div>
                <div>
                    <div class="label">Erros auth</div>
                    <div class="value">{{ number_format($kpis['login_error']) }}</div>
                    <div class="delta">credencial inválida ou bloqueada</div>
                </div>
            </div>
        </div>
    </div>

    {{-- BUSCA RÁPIDA — filtra empresa por name/cnpj OU máquina por hd/hostname --}}
    <form method="GET" action="{{ route('licenca_log.index') }}" class="oi-filter-bar" style="margin-bottom: 12px;">
        <div class="row">
            <div class="col-md-8">
                <label>🔍 Buscar por empresa ou máquina</label>
                <input type="text" name="q" value="{{ $filter_q ?? '' }}" class="form-control"
                       placeholder="Nome, CNPJ, HD (F0A24779), hostname (BOOK-GV80BF5507)…"
                       autocomplete="off">
            </div>
            <div class="col-md-4" style="padding-top: 24px;">
                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Buscar</button>
                @if($filter_q ?? null)
                    <a href="{{ route('licenca_log.index') }}" class="btn btn-default"><i class="fa fa-times"></i> Limpar</a>
                @endif
            </div>
        </div>
        @if($filter_q ?? null)
            <div class="alert alert-info" style="margin: 10px 0 0;">
                <i class="fa fa-filter"></i> Buscando por <strong>"{{ $filter_q }}"</strong> — mostrando empresas/máquinas que batem.
            </div>
        @endif
    </form>

    {{-- Filtros da timeline --}}
    <div class="oi-filter-bar">
        <div class="row">
            <div class="col-md-3">
                <label>Tipo</label>
                <select id="filter_event" class="form-control">
                    <option value="">Todas</option>
                    @foreach ($events as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
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
        @if($filter_business_id ?? null)
            <div class="alert alert-info" style="margin-top: 10px; margin-bottom: 0;">
                <i class="fa fa-filter"></i> Filtrado por <strong>empresa #{{ $filter_business_id }}</strong>.
                <a href="{{ route('licenca_log.index') }}">Remover filtro</a>
            </div>
        @endif
    </div>

    {{-- STATUS POR MÁQUINA --}}
    <div class="oi-card">
        <div class="hdr">
            <h3><i class="fa fa-desktop"></i> Máquinas que logaram <small style="color:#6b7280;">({{ $maquinas->count() }})</small></h3>
            <small style="color: #6b7280;">Status mostra o bloqueio <strong>no momento do login</strong></small>
        </div>
        <div class="body no-pad">
            <table class="oi-table">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Máquina</th>
                        <th>IP</th>
                        <th>Último Login</th>
                        <th style="text-align: center;">Logins 24h</th>
                        <th style="text-align: center;">Erros 24h</th>
                        <th>Estado no Último Login</th>
                        <th>Estado Atual</th>
                        <th style="width: 150px;">Ação rápida</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($maquinas as $m)
                        <tr @if($m->errors_24h > 0) style="background: #fef3c7;" @endif>
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
                                @if($m->hd && $m->guessed_machine)
                                    <strong class="text-mono">{{ $m->guessed_machine->user_win ?: '(sem hostname)' }}</strong>
                                    <br><small class="text-muted text-mono">HD {{ $m->hd }}</small>
                                @elseif($m->total_maquinas > 0)
                                    <details class="oi-guess">
                                        <summary>
                                            <em class="text-warning"><i class="fa fa-question-circle"></i> 1 de {{ $m->total_maquinas }} {{ $m->total_maquinas == 1 ? 'máquina' : 'máquinas' }}</em>
                                            <small class="text-muted" style="display:block; font-size:10px;">Delphi ainda sem hd — clique pra ver</small>
                                        </summary>
                                        <ul style="margin: 6px 0 0; padding-left: 14px; font-size: 11px;">
                                            @foreach($m->known_machines as $known)
                                                <li>
                                                    <strong>{{ $known->user_win ?: '(sem hostname)' }}</strong>
                                                    <span class="text-muted text-mono">· HD {{ $known->hd }}</span>
                                                    @if($known->ip_interno)
                                                        <span class="text-muted">· IP int {{ $known->ip_interno }}</span>
                                                    @endif
                                                    @if($known->bloqueado)
                                                        <span class="oi-pill oi-pill-blocked" style="font-size:10px;">bloq</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @else
                                    <em class="text-muted">sem cadastro</em>
                                @endif
                            </td>
                            <td class="text-mono">{{ $m->ip ?: '—' }}</td>
                            <td class="text-mono">{{ \Carbon\Carbon::parse($m->last_login)->format('d/m/Y H:i:s') }}</td>
                            <td style="text-align: center;">
                                <span class="oi-pill oi-pill-neutral">{{ $m->login_count_24h }}</span>
                            </td>
                            <td style="text-align: center;">
                                @if($m->errors_24h > 0)
                                    <span class="oi-pill oi-pill-blocked" title="Tentativas rejeitadas" style="font-weight: 700;">{{ $m->errors_24h }}</span>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
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
                                @if($m->business_blocked && $m->business_id)
                                    <a href="{{ route('business.bloqueado', $m->business_id) }}"
                                       class="oi-btn oi-btn-success oi-btn-xs"
                                       onclick="return confirm('Liberar empresa {{ addslashes($m->business_name) }} ?')"
                                       title="Desbloquear empresa inteira">
                                        <i class="fa fa-unlock"></i> Liberar empresa
                                    </a>
                                @elseif($m->guessed_machine && $m->guessed_machine->bloqueado)
                                    <a href="{{ route('licenca_computador.toggleBlock', $m->guessed_machine->id) }}"
                                       class="oi-btn oi-btn-success oi-btn-xs"
                                       onclick="return confirm('Liberar máquina {{ addslashes($m->guessed_machine->user_win ?? '') }} ?')"
                                       title="Desbloquear essa máquina">
                                        <i class="fa fa-unlock"></i> Liberar máquina
                                    </a>
                                @elseif($m->business_id)
                                    <a href="{{ url('/officeimpresso/licenca_log?business_id=' . $m->business_id) }}"
                                       class="oi-btn oi-btn-ghost oi-btn-xs"
                                       title="Filtrar timeline por esta empresa">
                                        <i class="fa fa-filter"></i> Timeline
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px; color: #9ca3af;">
                                @if($filter_q ?? null)
                                    Nenhuma máquina encontrada com <strong>"{{ $filter_q }}"</strong> nas últimas 24h.
                                @else
                                    <div style="max-width: 520px; margin: 0 auto; text-align: left;">
                                        <p style="margin: 0 0 10px; color: #374151;">
                                            <strong>Nenhum cliente Delphi identificado nas últimas 24h.</strong>
                                        </p>
                                        <p style="margin: 0 0 8px; font-size: 12px;">
                                            Esta lista mostra máquinas que chamaram
                                            <code class="text-mono">/connector/api/processa-dados-cliente</code>
                                            (ou <code class="text-mono">salvar-equipamento</code>) — onde CNPJ + HD chegam no body.
                                        </p>
                                        <p style="margin: 0; font-size: 12px; color: #6b7280;">
                                            Se o Delphi está apenas autenticando
                                            (<code class="text-mono">/oauth/token</code>), consulte a
                                            <strong>timeline de autorização de uso</strong> abaixo.
                                        </p>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- TIMELINE DETALHADA DE AUTORIZAÇÃO --}}
    <div class="oi-card" style="margin-top: 24px;">
        <div class="hdr">
            <h3><i class="fa fa-history"></i> Timeline detalhada da autorização de uso</h3>
            <small style="color: #6b7280;">Autorizações concedidas e negadas</small>
        </div>
        <div class="body no-pad">
            <table id="licenca_log_table" class="table table-hover">
                <thead>
                    <tr>
                        <th style="width: 150px;">Data/Hora</th>
                        <th style="width: 170px;">Tipo</th>
                        <th style="width: 140px;">Estado no Login</th>
                        <th>Empresa</th>
                        <th style="width: 120px;">IP</th>
                        <th>Motivo (se negada)</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

@endsection

@section('javascript')
<script>
$(function () {
    var initialLicencaId  = {!! json_encode($filter_licenca_id ?? null) !!};
    var initialBusinessId = {!! json_encode($filter_business_id ?? null) !!};
    var initialQ          = {!! json_encode($filter_q ?? '') !!};

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
            zeroRecords: 'Nenhum registro',
            emptyTable: 'Nenhuma autorização registrada ainda.',
            paginate: { first: '«', previous: '‹', next: '›', last: '»' }
        },
        ajax: {
            url: "{{ route('licenca_log.index') }}",
            data: function (d) {
                // Timeline: so login_success + login_error (autorizacao de uso)
                d.event_in    = 'login_success,login_error';
                d.event       = $('#filter_event').val();
                d.licenca_id  = initialLicencaId;
                d.business_id = initialBusinessId;
                d.q           = initialQ;
                d.from        = $('#filter_from').val();
                d.to          = $('#filter_to').val();
            }
        },
        columns: [
            { data: 'created_at',    name: 'created_at', className: 'text-mono' },
            { data: 'event',         name: 'event' },
            { data: 'blocked_info',  name: 'blocked',    orderable: false, searchable: false },
            { data: 'business_info', name: 'business_id', orderable: false, searchable: false },
            { data: 'ip',            name: 'ip', className: 'text-mono' },
            { data: 'endpoint',      name: 'endpoint', orderable: false },
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
