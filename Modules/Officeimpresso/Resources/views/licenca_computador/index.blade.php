@extends('layouts.app')

@section('title', __('officeimpresso::lang.officeimpresso'))

@section('content')
@include('officeimpresso::layouts.partials.design-system')
@include('officeimpresso::layouts.nav')

<div class="oi-page">
    <div class="oi-page-header">
        <h1>@lang('officeimpresso::lang.computadores_cadastrados')</h1>
        <div class="subtitle">Todas as licenças de desktop cadastradas no sistema</div>
    </div>

    {{-- KPIs --}}
    @php
        $total = is_countable($licencas) ? count($licencas) : 0;
        $bloqueadas = collect($licencas)->where('bloqueado', true)->count();
        $ativas = $total - $bloqueadas;
    @endphp
    <div class="row oi-kpi-row">
        <div class="col-md-4 col-sm-6 col-xs-12">
            <div class="oi-kpi">
                <div class="icon bg-blue"><i class="fa fa-desktop"></i></div>
                <div>
                    <div class="label">Total</div>
                    <div class="value">{{ $total }}</div>
                    <div class="delta">máquinas registradas</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 col-xs-12">
            <div class="oi-kpi">
                <div class="icon bg-green"><i class="fa fa-check"></i></div>
                <div>
                    <div class="label">Liberadas</div>
                    <div class="value">{{ $ativas }}</div>
                    <div class="delta">em operação</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 col-xs-12">
            <div class="oi-kpi">
                <div class="icon bg-red"><i class="fa fa-lock"></i></div>
                <div>
                    <div class="label">Bloqueadas</div>
                    <div class="value">{{ $bloqueadas }}</div>
                    <div class="delta">requerem ação</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="oi-card">
        <div class="hdr">
            <h3><i class="fa fa-key"></i> Licenças</h3>
            <a href="{{ route('licenca_computador.create') }}" class="oi-btn oi-btn-primary">
                <i class="fa fa-plus"></i> Cadastrar
            </a>
        </div>
        <div class="body no-pad">
            <div class="table-responsive">
                <table class="oi-table" id="licencas_table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>HD</th>
                            <th>@lang('officeimpresso::lang.user_win')</th>
                            <th>@lang('officeimpresso::lang.processador')</th>
                            <th>@lang('officeimpresso::lang.memoria')</th>
                            <th>@lang('officeimpresso::lang.versao_exe')</th>
                            <th>@lang('officeimpresso::lang.bloqueado')</th>
                            <th>@lang('officeimpresso::lang.action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($licencas as $licenca)
                            <tr>
                                <td class="text-mono">{{ $licenca->id }}</td>
                                <td class="text-mono">{{ $licenca->hd }}</td>
                                <td><strong>{{ $licenca->user_win }}</strong></td>
                                <td class="text-mono" title="{{ $licenca->processador }}">{{ \Illuminate\Support\Str::limit($licenca->processador, 35) }}</td>
                                <td class="text-mono">{{ $licenca->memoria }}</td>
                                <td class="text-mono">{{ $licenca->versao_exe }}</td>
                                <td>
                                    @if($licenca->bloqueado)
                                        <span class="oi-pill oi-pill-blocked">Bloqueada</span>
                                    @else
                                        <span class="oi-pill oi-pill-ok">Liberada</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('licenca_computador.toggleBlock', $licenca->id) }}"
                                       class="oi-btn {{ $licenca->bloqueado ? 'oi-btn-danger' : 'oi-btn-success' }} oi-btn-xs">
                                        <i class="fas fa-{{ $licenca->bloqueado ? 'lock' : 'unlock' }}"></i>
                                        {{ $licenca->bloqueado ? 'Desbloquear' : 'Bloquear' }}
                                    </a>
                                    <a href="{{ url('/officeimpresso/licenca_log?licenca_id=' . $licenca->id) }}"
                                       class="oi-btn oi-btn-ghost oi-btn-xs">
                                        <i class="fas fa-clipboard-list"></i> Log
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px; color: #9ca3af;">
                                    Nenhuma licença cadastrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('javascript')
<script>
    $(document).ready(function() {
        $('#licencas_table').DataTable({
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                search: 'Buscar:',
                lengthMenu: 'Mostrar _MENU_',
                info: 'Mostrando _START_ a _END_ de _TOTAL_',
                zeroRecords: 'Nenhuma licença encontrada',
                paginate: { first: '«', previous: '‹', next: '›', last: '»' }
            }
        });
    });
</script>
@endsection
