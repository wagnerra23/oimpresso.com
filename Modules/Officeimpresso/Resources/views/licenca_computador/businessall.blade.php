@extends('layouts.app')
@section('title', __('officeimpresso::lang.businessall'))

@section('css')
@include('officeimpresso::layouts.partials.design-system')
@endsection

@section('content')
@include('officeimpresso::layouts.nav')

<div class="oi-page">
    <div class="oi-page-header">
        <h1>@lang('officeimpresso::lang.businessall')</h1>
        <div class="subtitle">Todas as empresas com licença Office Impresso ativa</div>
    </div>

    {{-- KPIs --}}
    @php
        $total = is_countable($business) ? count($business) : 0;
        $bloqueadas = collect($business)->where('officeimpresso_bloqueado', true)->count();
        $ativas = $total - $bloqueadas;
    @endphp
    <div class="row">
        <div class="col-md-4 col-sm-6 col-xs-12" style="margin-bottom: 14px;">
            <div class="oi-kpi">
                <div class="icon bg-blue"><i class="fa fa-building"></i></div>
                <div>
                    <div class="label">Empresas</div>
                    <div class="value">{{ $total }}</div>
                    <div class="delta">com módulo Office Impresso</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 col-xs-12" style="margin-bottom: 14px;">
            <div class="oi-kpi">
                <div class="icon bg-green"><i class="fa fa-check"></i></div>
                <div>
                    <div class="label">Ativas</div>
                    <div class="value">{{ $ativas }}</div>
                    <div class="delta">em operação</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 col-xs-12" style="margin-bottom: 14px;">
            <div class="oi-kpi">
                <div class="icon bg-red"><i class="fa fa-lock"></i></div>
                <div>
                    <div class="label">Bloqueadas</div>
                    <div class="value">{{ $bloqueadas }}</div>
                    <div class="delta">requer ação</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="oi-card">
        <div class="hdr">
            <h3><i class="fa fa-list"></i> Empresas Licenciadas</h3>
        </div>
        <div class="body no-pad">
            <div class="table-responsive">
                <table class="oi-table" id="business_table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>Razão Social</th>
                            <th>CNPJ</th>
                            <th>Versão Disp.</th>
                            <th>Versão Mín.</th>
                            <th>Máquinas</th>
                            <th>Banco</th>
                            <th>Último Acesso</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($business as $busine)
                            <tr>
                                <td class="text-mono">{{ $busine->id }}</td>
                                <td><strong>{{ $busine->name }}</strong></td>
                                <td>{{ $busine->razao_social }}</td>
                                <td class="text-mono">{{ $busine->cnpj }}</td>
                                <td class="text-mono">{{ $busine->versao_disponivel ?: '—' }}</td>
                                <td class="text-mono">{{ $busine->versao_minima ?: '—' }}</td>
                                <td class="text-mono">{{ $busine->quantidade_maquinas ?? '—' }}</td>
                                <td class="text-mono" title="{{ $busine->caminho_banco }}">{{ \Illuminate\Support\Str::limit($busine->caminho_banco, 20) }}</td>
                                <td class="text-mono">{{ $busine->ultimo_acesso ?: '—' }}</td>
                                <td>
                                    @if($busine->officeimpresso_bloqueado)
                                        <span class="oi-pill oi-pill-blocked">Bloqueada</span>
                                    @else
                                        <span class="oi-pill oi-pill-ok">Ativa</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ action('\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController@viewLicencas', [$busine->id]) }}"
                                       class="oi-btn oi-btn-primary oi-btn-xs"
                                       title="Ver computadores desta empresa">
                                        <i class="fas fa-desktop"></i> Computadores
                                    </a>
                                    <a href="{{ url('/officeimpresso/licenca_log?business_id=' . $busine->id) }}"
                                       class="oi-btn oi-btn-ghost oi-btn-xs"
                                       title="Ver log desta empresa">
                                        <i class="fas fa-clipboard-list"></i> Log
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" style="text-align: center; padding: 30px; color: #9ca3af;">
                                    @lang('officeimpresso::lang.no_records_found')
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        $('#business_table').DataTable({
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                search: 'Buscar:',
                lengthMenu: 'Mostrar _MENU_',
                info: 'Mostrando _START_ a _END_ de _TOTAL_',
                infoEmpty: 'Nenhum registro',
                zeroRecords: 'Nenhuma empresa encontrada',
                paginate: { first: '«', previous: '‹', next: '›', last: '»' }
            }
        });
    });
</script>
@endsection
