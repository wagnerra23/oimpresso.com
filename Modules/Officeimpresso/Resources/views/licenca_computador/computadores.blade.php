@extends('layouts.app')

@section('title', __('officeimpresso::lang.licenca_officeimpresso'))

@section('content')
@include('officeimpresso::layouts.partials.design-system')
@include('officeimpresso::layouts.nav')

<div class="oi-page">
    <div class="oi-page-header">
        <h1>Licenças Office Impresso</h1>
        <div class="subtitle">Gestão de desktops Delphi por empresa</div>
    </div>

    {{-- Dados da empresa --}}
    <div class="oi-card">
        <div class="hdr">
            <h3><i class="fa fa-building"></i> Dados da Empresa</h3>
            @if($empresa->officeimpresso_bloqueado)
                <span class="oi-pill oi-pill-blocked"><i class="fa fa-lock"></i> Bloqueada</span>
            @else
                <span class="oi-pill oi-pill-ok"><i class="fa fa-check-circle"></i> Ativa</span>
            @endif
        </div>
        <div class="body">
            <div class="oi-company">
                <h2>{{ $empresa->name }}</h2>
                <p><i class="fa fa-user"></i> {{ $empresa->razao_social }}</p>
                <p><i class="fa fa-map-marker"></i> {{ $empresa->rua }}</p>
                <p><i class="fa fa-phone"></i> {{ $empresa->telefone }}</p>
                <p><i class="fa fa-code-branch"></i> Versão Obrigatória: <strong>{{ $empresa->versao_obrigatoria }}</strong></p>
                <p><i class="fa fa-download"></i> Versão Disponível: <strong>{{ $empresa->versao_disponivel }}</strong></p>
                <p><i class="fa fa-database"></i> Caminho Banco: <strong>{{ $empresa->caminho_banco_servidor ?: '—' }}</strong></p>
                <p><i class="fa fa-clock"></i> Último Acesso: <strong>{{ $empresa->dt_ultimo_acesso ?: '—' }}</strong></p>

                @if(isset($package) && !empty($package))
                    <p><i class="fa fa-desktop"></i> @lang('superadmin::lang.officeimpresso_limitemaquinas'):
                        <strong>{{ $package->officeimpresso_limitemaquinas == 0 ? 'Ilimitado' : $package->officeimpresso_limitemaquinas }}</strong>
                    </p>
                @endif

                @if(isset($active) && !empty($active))
                    <p><i class="fa fa-calendar"></i> @lang('superadmin::lang.end_date'): {{ @format_date($active->end_date) }}</p>
                    <p><i class="fa fa-hourglass-half"></i> @lang('superadmin::lang.remaining', ['days' => \Carbon::today()->diffInDays($active->end_date)])</p>
                @endif

                <div class="actions">
                    <a href="{{ action('\Modules\Superadmin\Http\Controllers\SubscriptionController@index', [$empresa->id]) }}" class="oi-btn oi-btn-primary">
                        <i class="fa fa-box"></i> Ver pacote
                    </a>
                    <a href="#" class="oi-btn oi-btn-ghost" data-toggle="modal" data-target="#editBusinessModal">
                        <i class="fa fa-edit"></i> Editar
                    </a>
                    @if($empresa->officeimpresso_bloqueado)
                        <a href="{{ route('business.bloqueado', $empresa->id) }}" class="oi-btn oi-btn-danger">
                            <i class="fa fa-lock"></i> Bloqueada
                        </a>
                    @else
                        <a href="{{ route('business.bloqueado', $empresa->id) }}" class="oi-btn oi-btn-success">
                            <i class="fa fa-unlock"></i> Liberada
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Tabela de computadores --}}
    <div class="oi-card">
        <div class="hdr">
            <h3><i class="fa fa-desktop"></i> Computadores <small style="color:#6b7280;">({{ count($licencas) }})</small></h3>
            <a href="{{ url('/officeimpresso/licenca_log?business_id=' . $empresa->id) }}" class="oi-btn oi-btn-ghost oi-btn-xs">
                <i class="fa fa-clipboard-list"></i> Ver log da empresa
            </a>
        </div>
        <div class="body no-pad">
            <div class="table-responsive">
                <table class="oi-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cadastro</th>
                            <th>Máquina</th>
                            <th>Executável</th>
                            <th>Versão</th>
                            <th>IP</th>
                            <th>Banco</th>
                            <th>Último Acesso</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($licencas as $licenca)
                            <tr>
                                <td class="text-mono">{{ $licenca->id }}</td>
                                <td class="text-mono">{{ $licenca->dt_cadastro }}</td>
                                <td><strong>{{ $licenca->user_win ?: '—' }}</strong></td>
                                <td class="text-mono" title="{{ $licenca->pasta_instalacao }}">{{ \Illuminate\Support\Str::limit($licenca->pasta_instalacao, 30) }}</td>
                                <td class="text-mono">{{ $licenca->versao_exe ?: '—' }}</td>
                                <td class="text-mono">{{ $licenca->ip_interno ?: '—' }}</td>
                                <td class="text-mono" title="{{ $licenca->caminho_banco }}">{{ \Illuminate\Support\Str::limit($licenca->caminho_banco, 20) }}</td>
                                <td class="text-mono">{{ $licenca->dt_ultimo_acesso ?: '—' }}</td>
                                <td>
                                    @if($licenca->bloqueado)
                                        <span class="oi-pill oi-pill-blocked">Bloqueada</span>
                                    @else
                                        <span class="oi-pill oi-pill-ok">Ativa</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('licenca_computador.toggleBlock', $licenca->id) }}"
                                       class="oi-btn {{ $licenca->bloqueado ? 'oi-btn-danger' : 'oi-btn-success' }} oi-btn-xs"
                                       title="{{ $licenca->bloqueado ? 'Clique pra desbloquear' : 'Clique pra bloquear' }}">
                                        <i class="fas fa-{{ $licenca->bloqueado ? 'lock' : 'unlock' }}"></i>
                                    </a>
                                    <a href="{{ url('/officeimpresso/licenca_log?licenca_id=' . $licenca->id) }}"
                                       class="oi-btn oi-btn-ghost oi-btn-xs"
                                       title="Ver log de acesso desta máquina">
                                        <i class="fas fa-clipboard-list"></i> Log
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 30px; color: #9ca3af;">
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

{{-- Modal de edição --}}
<div class="modal fade" id="editBusinessModal" tabindex="-1" role="dialog" aria-labelledby="editBusinessModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            {!! Form::open(['url' => action('\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController@businessupdate', [$empresa->id]), 'method' => 'POST', 'id' => 'editBusinessForm']) !!}
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="editBusinessModalLabel">Editar Dados da Empresa</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    {!! Form::label('caminho_banco_servidor', 'Caminho Banco do Servidor') !!}
                    {!! Form::text('caminho_banco_servidor', $empresa->caminho_banco_servidor, ['class' => 'form-control', 'placeholder' => 'Digite o caminho do banco']) !!}
                </div>
                <div class="form-group">
                    {!! Form::label('versao_obrigatoria', 'Versão Obrigatória') !!}
                    {!! Form::text('versao_obrigatoria', $empresa->versao_obrigatoria, ['class' => 'form-control', 'placeholder' => 'Digite a versão obrigatória']) !!}
                </div>
                <div class="form-group">
                    {!! Form::label('versao_disponivel', 'Versão Disponível') !!}
                    {!! Form::text('versao_disponivel', $empresa->versao_disponivel, ['class' => 'form-control', 'placeholder' => 'Digite a versão disponível']) !!}
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>

@endsection

@section('javascript')
<script>
    @if(session('status'))
        $('<div class="alert alert-info">{{ session('status') }}</div>').insertBefore('.oi-page-header');
    @endif

    $('#editBusinessModal').on('hidden.bs.modal', function () {
        $(this).find('form')[0].reset();
    });
</script>
@endsection
