@extends('layouts.app')

@section('title', 'Máquinas Cadastradas — Office Impresso')

@section('css')
@include('officeimpresso::layouts.partials.design-system')
@endsection

@section('content')
@include('officeimpresso::layouts.nav')

<div class="oi-page">
    <div class="oi-page-header">
        <h1>Máquinas Cadastradas</h1>
        <div class="subtitle">
            Registro da máquina é a chave desta tela. A rotina
            <code class="text-mono">/connector/api/processa-dados-cliente</code>
            popula/atualiza o cadastro; o log do último acesso aparece em cada linha.
        </div>
    </div>

    {{-- KPIs --}}
    <div class="row oi-kpi-row">
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="oi-kpi">
                <div class="icon bg-blue"><i class="fa fa-desktop"></i></div>
                <div>
                    <div class="label">Máquinas cadastradas</div>
                    <div class="value">{{ number_format($kpis['total_maquinas']) }}</div>
                    <div class="delta">total de equipamentos</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="oi-kpi">
                <div class="icon bg-amber"><i class="fa fa-lock"></i></div>
                <div>
                    <div class="label">Máquinas bloqueadas</div>
                    <div class="value">{{ number_format($kpis['maquinas_bloqueadas']) }}</div>
                    <div class="delta">bloqueio individual</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="oi-kpi">
                <div class="icon bg-red"><i class="fa fa-ban"></i></div>
                <div>
                    <div class="label">Empresas bloqueadas</div>
                    <div class="value">{{ number_format($kpis['empresas_bloqueadas']) }}</div>
                    <div class="delta">bloqueio em massa</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="oi-kpi">
                <div class="icon bg-green"><i class="fa fa-refresh"></i></div>
                <div>
                    <div class="label">Acessos 24h</div>
                    <div class="value">{{ number_format($kpis['chamadas_24h']) }}</div>
                    <div class="delta">processa-dados-cliente</div>
                </div>
            </div>
        </div>
    </div>

    {{-- FILTROS: busca livre + estado atual. Empresa/Equipamento via hyperlinks nas linhas. --}}
    @php
        $hasAnyFilter = ($filter_q ?? '') !== ''
            || ($filter_business_id ?? null)
            || ($filter_licenca_id ?? null)
            || ($filter_estado_atual ?? null);
    @endphp
    <form method="GET" action="{{ route('licenca_log.index') }}" class="oi-filter-bar" style="margin-bottom: 12px;">
        <div class="row">
            <div class="col-md-7">
                <label>🔍 Empresa / Máquina</label>
                <input type="text" name="q" value="{{ $filter_q ?? '' }}" class="form-control"
                       placeholder="Nome, CNPJ, HD, hostname, IP…"
                       autocomplete="off">
            </div>
            <div class="col-md-3">
                <label>Estado atual</label>
                <select name="estado_atual" class="form-control">
                    <option value="">Todos</option>
                    <option value="ativa"     @if(($filter_estado_atual ?? null) === 'ativa') selected @endif>Ativa</option>
                    <option value="bloqueada" @if(($filter_estado_atual ?? null) === 'bloqueada') selected @endif>Bloqueada</option>
                </select>
            </div>
            <div class="col-md-2" style="padding-top: 24px;">
                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Aplicar</button>
                @if($hasAnyFilter)
                    <a href="{{ route('licenca_log.index') }}" class="btn btn-default"><i class="fa fa-times"></i> Limpar</a>
                @endif
            </div>
        </div>
    </form>

    @if($filter_business_id ?? null)
        <div class="alert alert-info" style="margin-bottom: 12px;">
            <i class="fa fa-filter"></i> Filtrado por <strong>empresa #{{ $filter_business_id }}</strong>.
            <a href="{{ route('licenca_log.index', array_filter(['q' => $filter_q, 'estado_atual' => $filter_estado_atual])) }}">Remover</a>
        </div>
    @endif
    @if($filter_licenca_id ?? null)
        <div class="alert alert-info" style="margin-bottom: 12px;">
            <i class="fa fa-filter"></i> Filtrado por <strong>equipamento #{{ $filter_licenca_id }}</strong>.
            <a href="{{ route('licenca_log.index', array_filter(['q' => $filter_q, 'estado_atual' => $filter_estado_atual])) }}">Remover</a>
        </div>
    @endif
    @if(($filter_hd ?? '') !== '')
        <div class="alert alert-info" style="margin-bottom: 12px;">
            <i class="fa fa-filter"></i> Filtrado por <strong>HD {{ $filter_hd }}</strong> — mostrando todas as empresas onde esse HD tem cadastro.
            <a href="{{ route('licenca_log.index', array_filter(['q' => $filter_q, 'estado_atual' => $filter_estado_atual])) }}">Remover</a>
        </div>
    @endif

    {{-- GRID — timeline de maquinas (1 linha por maquina cadastrada, ordem por ultimo login) --}}
    <div class="oi-card">
        <div class="hdr">
            <h3><i class="fa fa-history"></i> Timeline de Máquinas <small style="color:#6b7280;">({{ $maquinas->count() }})</small></h3>
            <small style="color: #6b7280;">1 linha por máquina · última atividade primeiro · clique em empresa ou máquina para filtrar</small>
        </div>
        <div class="body no-pad">
            <table id="maquinas_table" class="oi-table table">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Location / CNPJ</th>
                        <th>Máquina</th>
                        <th>HD</th>
                        <th>Versão</th>
                        <th>IP</th>
                        <th data-order-type="num">Último Login</th>
                        <th>Estado no Último Login</th>
                        <th>Estado Atual</th>
                        <th style="width: 160px;" class="no-sort">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($maquinas as $m)
                        <tr>
                            <td>
                                @if($m->business_id)
                                    <a href="{{ route('licenca_log.index', array_filter(['business_id' => $m->business_id, 'q' => $filter_q, 'estado_atual' => $filter_estado_atual])) }}"
                                       class="text-primary" title="Filtrar por esta empresa">
                                        <strong>{{ $m->business_name }}</strong>
                                    </a>
                                @else
                                    <em class="text-muted">—</em>
                                @endif
                            </td>
                            <td>
                                @if($m->last_location)
                                    <strong>{{ $m->last_location->name }}</strong>
                                    @if($m->last_location->cnpj)
                                        <br><small class="text-muted text-mono">CNPJ {{ $m->last_location->cnpj }}</small>
                                    @endif
                                @else
                                    <em class="text-muted">—</em>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('licenca_log.index', array_filter(['licenca_id' => $m->licenca_id, 'q' => $filter_q, 'estado_atual' => $filter_estado_atual])) }}"
                                   class="text-primary" title="Filtrar por este equipamento">
                                    <strong class="text-mono">{{ $m->user_win ?: $m->hostname ?: '(sem hostname)' }}</strong>
                                </a>
                            </td>
                            <td class="text-mono">
                                @if($m->hd)
                                    <a href="{{ route('licenca_log.index', array_filter(['hd' => $m->hd, 'q' => $filter_q, 'estado_atual' => $filter_estado_atual])) }}"
                                       class="text-primary" title="Ver todas empresas com este HD">
                                        {{ $m->hd }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-mono">
                                @if($m->versao_exe || $m->versao_banco)
                                    @if($m->versao_exe)<span title="Versão do .exe">{{ $m->versao_exe }}</span>@endif
                                    @if($m->versao_banco)<small class="text-muted" title="Versão do banco">/ {{ $m->versao_banco }}</small>@endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-mono">{{ $m->last_ip ?: $m->ip_interno ?: '—' }}</td>
                            <td class="text-mono" data-order="{{ $m->effective_ts ? \Carbon\Carbon::parse($m->effective_ts)->getTimestamp() : 0 }}">
                                @if($m->last_login)
                                    {{ \Carbon\Carbon::parse($m->last_login)->format('d/m/Y H:i:s') }}
                                @elseif($m->dt_ultimo_acesso)
                                    {{ \Carbon\Carbon::parse($m->dt_ultimo_acesso)->format('d/m/Y H:i:s') }}
                                    <br><small class="text-muted">(cadastro)</small>
                                @else
                                    <span class="text-muted">nunca</span>
                                @endif
                            </td>
                            <td>
                                @if($m->was_blocked_last === null)
                                    <span class="text-muted">—</span>
                                @elseif($m->was_blocked_last)
                                    <span class="oi-pill oi-pill-blocked"><i class="fa fa-lock"></i> Bloqueada</span>
                                @else
                                    <span class="oi-pill oi-pill-ok"><i class="fa fa-check"></i> Liberada</span>
                                @endif
                            </td>
                            <td>
                                @if($m->business_blocked)
                                    <span class="oi-pill oi-pill-blocked">🔒 Empresa bloqueada</span>
                                @elseif($m->machine_blocked)
                                    <span class="oi-pill oi-pill-blocked">🔒 Máquina bloqueada</span>
                                @else
                                    <span class="oi-pill oi-pill-ok">Ativa</span>
                                @endif
                            </td>
                            <td>
                                @if($m->business_blocked && $m->business_id)
                                    <a href="{{ route('business.bloqueado', $m->business_id) }}"
                                       class="oi-btn oi-btn-success oi-btn-xs"
                                       onclick="return confirm('Desbloquear empresa {{ addslashes($m->business_name) }} ?')"
                                       title="Desbloquear empresa inteira">
                                        <i class="fa fa-unlock"></i> Desbloq. empresa
                                    </a>
                                @elseif($m->machine_blocked)
                                    <a href="{{ route('licenca_computador.toggleBlock', $m->licenca_id) }}"
                                       class="oi-btn oi-btn-success oi-btn-xs"
                                       onclick="return confirm('Desbloquear máquina {{ addslashes($m->user_win ?? '') }} ?')"
                                       title="Desbloquear essa máquina">
                                        <i class="fa fa-unlock"></i> Desbloq. máquina
                                    </a>
                                @else
                                    <a href="{{ route('licenca_computador.toggleBlock', $m->licenca_id) }}"
                                       class="oi-btn oi-btn-danger oi-btn-xs"
                                       onclick="return confirm('Bloquear máquina {{ addslashes($m->user_win ?? '') }} ?')"
                                       title="Bloquear essa máquina">
                                        <i class="fa fa-lock"></i> Bloq. máquina
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 30px; color: #9ca3af;">
                                @if($hasAnyFilter)
                                    Nenhuma máquina encontrada com os filtros aplicados.
                                @else
                                    <div style="max-width: 520px; margin: 0 auto; text-align: left;">
                                        <p style="margin: 0 0 10px; color: #374151;">
                                            <strong>Nenhuma máquina cadastrada ainda.</strong>
                                        </p>
                                        <p style="margin: 0; font-size: 12px;">
                                            A tabela é populada pela rotina
                                            <code class="text-mono">/connector/api/processa-dados-cliente</code>
                                            quando o Delphi envia CNPJ + HD.
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
</div>

@endsection

@section('javascript')
<script>
$(function () {
    if (! $.fn.DataTable) return;
    $('#maquinas_table').DataTable({
        pageLength: 25,
        order: [[6, 'desc']], // Último Login desc
        columnDefs: [{ targets: 'no-sort', orderable: false }],
        language: {
            processing: 'Carregando…',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_',
            info: '_START_–_END_ de _TOTAL_',
            infoEmpty: 'Nenhum registro',
            infoFiltered: '(filtrado de _MAX_)',
            zeroRecords: 'Nenhum registro encontrado',
            emptyTable: 'Nenhuma máquina cadastrada',
            paginate: { first: '«', previous: '‹', next: '›', last: '»' }
        }
    });
});
</script>
@endsection
