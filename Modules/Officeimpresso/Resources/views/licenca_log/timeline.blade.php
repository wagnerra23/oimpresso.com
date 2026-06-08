@extends('layouts.app')

@section('title', 'Timeline — ' . ($maquina->user_win ?: $maquina->hostname ?: 'Máquina #' . $maquina->id))

@section('css')
@include('officeimpresso::layouts.partials.design-system')
@endsection

@section('content')
@include('officeimpresso::layouts.nav')

<div class="oi-page">
    <div class="oi-page-header">
        <h1>Timeline — {{ $maquina->user_win ?: $maquina->hostname ?: 'sem hostname' }}</h1>
        <div class="subtitle">
            <strong>{{ $maquina->business_name ?: '—' }}</strong>
            · HD <code class="text-mono">{{ $maquina->hd ?: '—' }}</code>
            · IP <code class="text-mono">{{ $maquina->ip_interno ?: '—' }}</code>
        </div>
    </div>

    <div style="margin-bottom: 12px;">
        <a href="{{ route('licenca_log.index') }}" class="btn btn-default"><i class="fa fa-arrow-left"></i> Voltar</a>
        @if($maquina->business_blocked)
            <span class="oi-pill oi-pill-blocked">🔒 Empresa bloqueada</span>
        @elseif($maquina->machine_blocked)
            <span class="oi-pill oi-pill-blocked">🔒 Máquina bloqueada</span>
        @else
            <span class="oi-pill oi-pill-ok">Ativa</span>
        @endif
    </div>

    <div class="oi-card">
        <div class="hdr">
            <h3><i class="fa fa-history"></i> Últimos 200 acessos a processa-dados-cliente
                <small style="color:#6b7280;">({{ $logs->count() }})</small>
            </h3>
        </div>
        <div class="body no-pad">
            <table class="oi-table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Status HTTP</th>
                        <th>Estado no Login</th>
                        <th>IP</th>
                        <th>Duração</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        @php
                            $meta = is_string($log->metadata) ? json_decode($log->metadata, true) : ($log->metadata ?: []);
                            $was_blocked = (bool) ($meta['was_blocked'] ?? false);
                        @endphp
                        <tr>
                            <td class="text-mono">{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') }}</td>
                            <td>
                                @if($log->http_status < 400)
                                    <span class="oi-pill oi-pill-ok">{{ $log->http_status }}</span>
                                @else
                                    <span class="oi-pill oi-pill-blocked">{{ $log->http_status }}</span>
                                @endif
                            </td>
                            <td>
                                @if($was_blocked)
                                    <span class="oi-pill oi-pill-blocked"><i class="fa fa-lock"></i> Bloqueada</span>
                                @else
                                    <span class="oi-pill oi-pill-ok"><i class="fa fa-check"></i> Liberada</span>
                                @endif
                            </td>
                            <td class="text-mono">{{ $log->ip ?: '—' }}</td>
                            <td class="text-mono">{{ $log->duration_ms ? $log->duration_ms . 'ms' : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: #9ca3af;">
                                Nenhum acesso registrado para esta máquina.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
