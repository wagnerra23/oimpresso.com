{{--
    Partial: form de escala (create e edit).
    Variáveis esperadas:
      - $escala : Escala|null
      - $action : string
      - $metodo : string
--}}
@php
    $isEdit = isset($escala) && $escala;
    $tipos = [
        'FIXA'          => 'Fixa',
        'FLEXIVEL'      => 'Flexível',
        'ESCALA_12X36'  => '12x36',
        'ESCALA_6X1'    => '6x1',
        'ESCALA_5X2'    => '5x2',
    ];
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @if (($metodo ?? 'POST') === 'PUT')
        @method('PUT')
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
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
                <label for="nome">Nome <span class="text-red">*</span></label>
                <input type="text"
                       name="nome"
                       id="nome"
                       class="form-control"
                       required
                       maxlength="120"
                       value="{{ old('nome', $isEdit ? $escala->nome : '') }}">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label for="codigo">Código</label>
                <input type="text"
                       name="codigo"
                       id="codigo"
                       class="form-control"
                       maxlength="30"
                       value="{{ old('codigo', $isEdit ? $escala->codigo : '') }}">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label for="tipo">Tipo <span class="text-red">*</span></label>
                <select name="tipo" id="tipo" class="form-control" required>
                    @foreach ($tipos as $k => $v)
                        <option value="{{ $k }}"
                                @if(old('tipo', $isEdit ? $escala->tipo : '') === $k) selected @endif>
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
                <label for="carga_diaria_minutos">Carga diária (minutos) <span class="text-red">*</span></label>
                <input type="number"
                       name="carga_diaria_minutos"
                       id="carga_diaria_minutos"
                       class="form-control"
                       required
                       min="60"
                       max="600"
                       value="{{ old('carga_diaria_minutos', $isEdit ? $escala->carga_diaria_minutos : 480) }}">
                <small class="text-muted">Ex.: 480 = 8h, 360 = 6h. Entre 60 e 600.</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label for="carga_semanal_minutos">Carga semanal (minutos) <span class="text-red">*</span></label>
                <input type="number"
                       name="carga_semanal_minutos"
                       id="carga_semanal_minutos"
                       class="form-control"
                       required
                       min="0"
                       max="3600"
                       value="{{ old('carga_semanal_minutos', $isEdit ? $escala->carga_semanal_minutos : 2640) }}">
                <small class="text-muted">Ex.: 2640 = 44h (CLT padrão).</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label>&nbsp;</label><br>
                <label style="padding-top:8px;">
                    <input type="hidden" name="permite_banco_horas" value="0">
                    <input type="checkbox" name="permite_banco_horas" value="1"
                           {{ old('permite_banco_horas', $isEdit ? $escala->permite_banco_horas : true) ? 'checked' : '' }}>
                    Permite acúmulo em banco de horas
                </label>
            </div>
        </div>
    </div>

    @if ($isEdit && $escala->turnos && $escala->turnos->count() > 0)
        <hr>
        <h4><i class="fa fas fa-clock"></i> Turnos configurados</h4>
        <div class="table-responsive">
            <table class="table table-striped table-condensed">
                <thead>
                    <tr>
                        <th>Dia da semana</th>
                        <th>Entrada</th>
                        <th>Saída almoço</th>
                        <th>Retorno almoço</th>
                        <th>Saída</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($escala->turnos as $t)
                        <tr>
                            <td>{{ $t->dia_semana ?? '—' }}</td>
                            <td>{{ $t->entrada ?? '—' }}</td>
                            <td>{{ $t->saida_almoco ?? '—' }}</td>
                            <td>{{ $t->retorno_almoco ?? '—' }}</td>
                            <td>{{ $t->saida ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-muted">
            <small>
                <i class="fa fas fa-info-circle"></i>
                Gestão detalhada de turnos por dia será adicionada em fase posterior.
            </small>
        </p>
    @endif

    <hr>
    <div class="text-right">
        <a href="{{ route('ponto.escalas.index') }}" class="btn btn-default">
            <i class="fa fas fa-times"></i> Cancelar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="fa fas fa-save"></i> {{ $isEdit ? 'Atualizar' : 'Criar escala' }}
        </button>
    </div>
</form>
