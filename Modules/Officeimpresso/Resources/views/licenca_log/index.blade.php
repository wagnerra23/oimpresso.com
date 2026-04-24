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

    @if($filter_business_id ?? null)
        <div class="alert alert-info" style="margin-bottom: 12px;">
            <i class="fa fa-filter"></i> Filtrado por <strong>empresa #{{ $filter_business_id }}</strong>.
            <a href="{{ route('licenca_log.index') }}">Remover filtro</a>
        </div>
    @endif

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
                        <th>Login</th>
                        <th>Estado no Último Login</th>
                        <th>Estado Atual</th>
                        <th style="width: 160px;">Ação rápida</th>
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
                                @if($m->guessed_machine)
                                    <strong class="text-mono">{{ $m->guessed_machine->user_win ?: '(sem hostname)' }}</strong>
                                    <br><small class="text-muted text-mono">HD {{ $m->hd }}</small>
                                @elseif($m->hd)
                                    <em class="text-warning"><i class="fa fa-exclamation-triangle"></i> HD não cadastrado</em>
                                    <br><small class="text-muted text-mono">HD {{ $m->hd }}</small>
                                @else
                                    <em class="text-muted">sem HD no log</em>
                                @endif
                            </td>
                            <td class="text-mono">{{ $m->ip ?: '—' }}</td>
                            <td class="text-mono">{{ \Carbon\Carbon::parse($m->last_login)->format('d/m/Y H:i:s') }}</td>
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
                                @elseif($m->guessed_machine && $m->guessed_machine->bloqueado)
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
                                        <i class="fa fa-unlock"></i> Desbloquear empresa
                                    </a>
                                @elseif($m->guessed_machine && $m->guessed_machine->bloqueado)
                                    <a href="{{ route('licenca_computador.toggleBlock', $m->guessed_machine->id) }}"
                                       class="oi-btn oi-btn-success oi-btn-xs"
                                       onclick="return confirm('Desbloquear máquina {{ addslashes($m->guessed_machine->user_win ?? '') }} ?')"
                                       title="Desbloquear essa máquina">
                                        <i class="fa fa-unlock"></i> Desbloquear máquina
                                    </a>
                                @elseif($m->guessed_machine)
                                    <a href="{{ route('licenca_computador.toggleBlock', $m->guessed_machine->id) }}"
                                       class="oi-btn oi-btn-danger oi-btn-xs"
                                       onclick="return confirm('Bloquear máquina {{ addslashes($m->guessed_machine->user_win ?? '') }} ?')"
                                       title="Bloquear essa máquina">
                                        <i class="fa fa-lock"></i> Bloquear máquina
                                    </a>
                                @elseif($m->business_id)
                                    <a href="{{ route('business.bloqueado', $m->business_id) }}"
                                       class="oi-btn oi-btn-danger oi-btn-xs"
                                       onclick="return confirm('Bloquear empresa {{ addslashes($m->business_name) }} ?')"
                                       title="Bloquear empresa inteira">
                                        <i class="fa fa-lock"></i> Bloquear empresa
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #9ca3af;">
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

