{{--
    Partial: form de intercorrência (usado em create e edit).
    Variáveis esperadas:
      - $intercorrencia : Intercorrencia|null (null em create)
      - $action         : string (URL do submit)
      - $metodo         : string ('POST' ou 'PUT')
--}}
@php
    $businessId = session('business.id') ?: (auth()->user() ? auth()->user()->business_id : null);
    $colaboradoresLista = \Modules\PontoWr2\Entities\Colaborador::where('business_id', $businessId)
        ->where('controla_ponto', true)
        ->whereNull('desligamento')
        ->with('user')
        ->orderBy('matricula')
        ->get();

    $tipos     = __('pontowr2::ponto.intercorrencia.tipos');
    $isEdit    = isset($intercorrencia) && $intercorrencia;
    $diaTodo   = $isEdit ? (bool) $intercorrencia->dia_todo : false;
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if (($metodo ?? 'POST') === 'PUT')
        @method('PUT')
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="fa fas fa-exclamation-triangle"></i>
            <strong>Corrija os seguintes erros:</strong>
            <ul style="margin-bottom:0;">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="colaborador_config_id">Colaborador <span class="text-red">*</span></label>
                <select name="colaborador_config_id" id="colaborador_config_id" class="form-control" required>
                    <option value="">Selecione…</option>
                    @foreach ($colaboradoresLista as $c)
                        @php
                            $nome = trim(optional($c->user)->first_name . ' ' . optional($c->user)->last_name);
                            if ($nome === '') { $nome = 'Colaborador #' . $c->id; }
                            $selected = old('colaborador_config_id', $isEdit ? $intercorrencia->colaborador_config_id : '') == $c->id;
                        @endphp
                        <option value="{{ $c->id }}" @if($selected) selected @endif>
                            {{ $c->matricula ? '[' . $c->matricula . '] ' : '' }}{{ $nome }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label for="tipo">Tipo <span class="text-red">*</span></label>
                <select name="tipo" id="tipo" class="form-control" required>
                    <option value="">Selecione…</option>
                    @foreach ($tipos as $k => $v)
                        <option value="{{ $k }}"
                                @if(old('tipo', $isEdit ? $intercorrencia->tipo : '') === $k) selected @endif>
                            {{ $v }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                <label for="data">Data <span class="text-red">*</span></label>
                <input type="date"
                       name="data"
                       id="data"
                       class="form-control"
                       required
                       max="{{ now()->format('Y-m-d') }}"
                       value="{{ old('data', $isEdit ? $intercorrencia->data->format('Y-m-d') : '') }}">
            </div>
        </div>

        <div class="col-md-2">
            <div class="form-group">
                <label>&nbsp;</label><br>
                <label style="padding-top:8px;">
                    <input type="hidden" name="dia_todo" value="0">
                    <input type="checkbox"
                           name="dia_todo"
                           id="dia_todo"
                           value="1"
                           {{ old('dia_todo', $diaTodo) ? 'checked' : '' }}>
                    Dia todo
                </label>
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                <label for="intervalo_inicio">Início</label>
                <input type="time"
                       name="intervalo_inicio"
                       id="intervalo_inicio"
                       class="form-control"
                       value="{{ old('intervalo_inicio', $isEdit && $intercorrencia->intervalo_inicio ? substr($intercorrencia->intervalo_inicio, 0, 5) : '') }}">
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                <label for="intervalo_fim">Fim</label>
                <input type="time"
                       name="intervalo_fim"
                       id="intervalo_fim"
                       class="form-control"
                       value="{{ old('intervalo_fim', $isEdit && $intercorrencia->intervalo_fim ? substr($intercorrencia->intervalo_fim, 0, 5) : '') }}">
            </div>
        </div>
    </div>

    <div class="form-group">
        <label for="justificativa">Justificativa <span class="text-red">*</span></label>
        <textarea name="justificativa"
                  id="justificativa"
                  class="form-control"
                  rows="4"
                  minlength="10"
                  maxlength="2000"
                  required
                  placeholder="Descreva o motivo da intercorrência (mín. 10 caracteres)…">{{ old('justificativa', $isEdit ? $intercorrencia->justificativa : '') }}</textarea>
        <small class="text-muted">Mínimo 10, máximo 2000 caracteres.</small>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                <label for="prioridade">Prioridade</label>
                <select name="prioridade" id="prioridade" class="form-control">
                    @php $pr = old('prioridade', $isEdit ? $intercorrencia->prioridade : 'NORMAL'); @endphp
                    <option value="NORMAL" @if($pr === 'NORMAL') selected @endif>Normal</option>
                    <option value="URGENTE" @if($pr === 'URGENTE') selected @endif>Urgente</option>
                </select>
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                <label>&nbsp;</label><br>
                <label style="padding-top:8px;">
                    <input type="hidden" name="impacta_apuracao" value="0">
                    <input type="checkbox" name="impacta_apuracao" value="1"
                           {{ old('impacta_apuracao', $isEdit ? $intercorrencia->impacta_apuracao : true) ? 'checked' : '' }}>
                    Impacta apuração
                </label>
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                <label>&nbsp;</label><br>
                <label style="padding-top:8px;">
                    <input type="hidden" name="descontar_banco_horas" value="0">
                    <input type="checkbox" name="descontar_banco_horas" value="1"
                           {{ old('descontar_banco_horas', $isEdit ? $intercorrencia->descontar_banco_horas : false) ? 'checked' : '' }}>
                    Descontar do banco de horas
                </label>
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                <label for="anexo">Anexo (PDF, JPG, PNG — máx 5MB)</label>
                <input type="file" name="anexo" id="anexo" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
            </div>
        </div>
    </div>

    <hr>
    <div class="text-right">
        <a href="{{ route('ponto.intercorrencias.index') }}" class="btn btn-default">
            <i class="fa fas fa-times"></i> Cancelar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="fa fas fa-save"></i> {{ $isEdit ? 'Atualizar' : 'Salvar rascunho' }}
        </button>
    </div>
</form>
