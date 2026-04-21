@extends('pontowr2::layouts.module')

@section('title', 'Configurar colaborador')

@section('module_content')
    @php
        $nomeColab = trim(optional($colaborador->user)->first_name . ' '
                        . optional($colaborador->user)->last_name);
        if ($nomeColab === '') {
            $nomeColab = 'Colaborador #' . $colaborador->id;
        }

        $businessId = session('business.id') ?: (auth()->user() ? auth()->user()->business_id : null);
        $escalasLista = \Modules\PontoWr2\Entities\Escala::where('business_id', $businessId)
            ->orderBy('nome')
            ->get();
    @endphp

    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.menu.colaboradores') }}
            <small>{{ $nomeColab }}</small>
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
                <a href="{{ route('ponto.colaboradores.index') }}" class="btn btn-default">
                    <i class="fa fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                @component('components.widget', ['class' => 'box-solid box-info'])
                    @slot('title')
                        <i class="fa fas fa-id-card"></i>
                        Dados do HRM
                    @endslot

                    <p><strong>Nome:</strong> {{ $nomeColab }}</p>
                    <p><strong>E-mail:</strong> {{ optional($colaborador->user)->email ?: '—' }}</p>
                    <p><strong>ID HRM:</strong> <code>{{ optional($colaborador->user)->id ?: '—' }}</code></p>
                    <p class="text-muted">
                        <small>
                            <i class="fa fas fa-info-circle"></i>
                            Estes dados vêm do módulo Essentials/HRM. Para alterar, vá em
                            <strong>Funcionários</strong> no menu do UltimatePOS.
                        </small>
                    </p>
                @endcomponent
            </div>

            <div class="col-md-8">
                @component('components.widget', ['class' => 'box-warning'])
                    @slot('title')
                        <i class="fa fas fa-edit"></i>
                        Configuração de ponto
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

                    <form method="POST" action="{{ route('ponto.colaboradores.update', $colaborador->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="matricula">Matrícula</label>
                                    <input type="text"
                                           name="matricula"
                                           id="matricula"
                                           class="form-control"
                                           maxlength="30"
                                           value="{{ old('matricula', $colaborador->matricula) }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="cpf">CPF</label>
                                    <input type="text"
                                           name="cpf"
                                           id="cpf"
                                           class="form-control"
                                           maxlength="14"
                                           placeholder="000.000.000-00"
                                           value="{{ old('cpf', $colaborador->cpf) }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="pis">PIS</label>
                                    <input type="text"
                                           name="pis"
                                           id="pis"
                                           class="form-control"
                                           maxlength="14"
                                           value="{{ old('pis', $colaborador->pis) }}">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="escala_atual_id">Escala atual</label>
                            <select name="escala_atual_id" id="escala_atual_id" class="form-control">
                                <option value="">— Sem escala vinculada —</option>
                                @foreach ($escalasLista as $e)
                                    <option value="{{ $e->id }}"
                                            @if(old('escala_atual_id', $colaborador->escala_atual_id) == $e->id) selected @endif>
                                        {{ $e->nome }} ({{ $e->tipo }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="admissao">Admissão <span class="text-red">*</span></label>
                                    <input type="date"
                                           name="admissao"
                                           id="admissao"
                                           class="form-control"
                                           required
                                           value="{{ old('admissao', optional($colaborador->admissao)->format('Y-m-d')) }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="desligamento">Desligamento</label>
                                    <input type="date"
                                           name="desligamento"
                                           id="desligamento"
                                           class="form-control"
                                           value="{{ old('desligamento', optional($colaborador->desligamento)->format('Y-m-d')) }}">
                                    <small class="text-muted">Deixar em branco se ativo.</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label style="padding-top:8px;">
                                        <input type="hidden" name="controla_ponto" value="0">
                                        <input type="checkbox" name="controla_ponto" value="1"
                                               {{ old('controla_ponto', $colaborador->controla_ponto) ? 'checked' : '' }}>
                                        <strong>Controla ponto</strong> — registra marcações
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label style="padding-top:8px;">
                                        <input type="hidden" name="usa_banco_horas" value="0">
                                        <input type="checkbox" name="usa_banco_horas" value="1"
                                               {{ old('usa_banco_horas', $colaborador->usa_banco_horas) ? 'checked' : '' }}>
                                        <strong>Usa banco de horas</strong>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <div class="text-right">
                            <a href="{{ route('ponto.colaboradores.index') }}" class="btn btn-default">
                                <i class="fa fas fa-times"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fas fa-save"></i> Salvar configuração
                            </button>
                        </div>
                    </form>
                @endcomponent
            </div>
        </div>
    </section>
@endsection
