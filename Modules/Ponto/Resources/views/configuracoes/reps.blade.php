@extends('pontowr2::layouts.module')

@section('title', 'REPs cadastrados')

@section('module_content')
    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.menu.configuracoes') }}
            <small>Dispositivos REP (Registrador Eletrônico de Ponto)</small>
        </h1>
    </section>

    <section class="content">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fas fa-check"></i> {{ session('success') }}
            </div>
        @endif

        <div class="row" style="margin-bottom:10px;">
            <div class="col-md-12">
                <a href="{{ route('ponto.configuracoes.index') }}" class="btn btn-default">
                    <i class="fa fas fa-arrow-left"></i> Voltar às configurações
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-5">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-plus-circle"></i>
                        Cadastrar novo REP
                    @endslot

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul style="margin-bottom:0;">
                                @foreach ($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('ponto.configuracoes.reps.store') }}">
                        @csrf
                        <div class="form-group">
                            <label for="tipo">Tipo <span class="text-red">*</span></label>
                            <select name="tipo" id="tipo" class="form-control" required>
                                <option value="REP_P" @if(old('tipo') === 'REP_P') selected @endif>REP-P (Programa/mobile)</option>
                                <option value="REP_C" @if(old('tipo') === 'REP_C') selected @endif>REP-C (Convencional)</option>
                                <option value="REP_A" @if(old('tipo') === 'REP_A') selected @endif>REP-A (Alternativo)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="identificador">Identificador (17 chars) <span class="text-red">*</span></label>
                            <input type="text"
                                   name="identificador"
                                   id="identificador"
                                   class="form-control"
                                   required
                                   minlength="17"
                                   maxlength="17"
                                   placeholder="AAAAMMDDHHMMSSNNN"
                                   value="{{ old('identificador') }}">
                            <small class="text-muted">Formato conforme Portaria 671/2021 Anexo I.</small>
                        </div>

                        <div class="form-group">
                            <label for="descricao">Descrição <span class="text-red">*</span></label>
                            <input type="text"
                                   name="descricao"
                                   id="descricao"
                                   class="form-control"
                                   required
                                   maxlength="120"
                                   value="{{ old('descricao') }}">
                        </div>

                        <div class="form-group">
                            <label for="local">Local</label>
                            <input type="text"
                                   name="local"
                                   id="local"
                                   class="form-control"
                                   maxlength="120"
                                   placeholder="Ex.: Recepção matriz"
                                   value="{{ old('local') }}">
                        </div>

                        <div class="form-group">
                            <label for="cnpj">CNPJ</label>
                            <input type="text"
                                   name="cnpj"
                                   id="cnpj"
                                   class="form-control"
                                   maxlength="14"
                                   placeholder="Somente números (14 dígitos)"
                                   value="{{ old('cnpj') }}">
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fa fas fa-save"></i> Cadastrar REP
                        </button>
                    </form>
                @endcomponent
            </div>

            <div class="col-md-7">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-microchip"></i>
                        REPs cadastrados
                        <small class="text-muted">({{ $reps->total() }})</small>
                    @endslot

                    <div class="table-responsive">
                        <table class="table table-striped table-condensed">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Identificador</th>
                                    <th>Descrição</th>
                                    <th>Local</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($reps as $r)
                                    <tr>
                                        <td>
                                            <span class="label label-info">{{ $r->tipo }}</span>
                                        </td>
                                        <td><code style="font-size:11px;">{{ $r->identificador }}</code></td>
                                        <td>{{ $r->descricao }}</td>
                                        <td><small class="text-muted">{{ $r->local ?: '—' }}</small></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted" style="padding:24px;">
                                            <i class="fa fas fa-microchip" style="font-size:24px;"></i><br>
                                            Nenhum REP cadastrado ainda.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($reps->hasPages())
                        <div class="text-center" style="margin-top:10px;">
                            {!! $reps->appends(request()->query())->links() !!}
                        </div>
                    @endif
                @endcomponent
            </div>
        </div>
    </section>
@endsection
