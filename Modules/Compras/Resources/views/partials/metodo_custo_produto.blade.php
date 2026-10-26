{{-- US-COM-024 · UC-FLX-38 — método de custo do produto, nos formulários de novo e de editar.
     Desenhado por DataController::product_form_part e gravado por after_product_saved
     (MetodoCustoDoProduto). Sem `purchase.update` o select vem desabilitado: campo desabilitado
     não vai no envio, e campo ausente não muda nada. --}}
@php
  $metodosEmpresa = ['medio' => 'Custo médio ponderado', 'ultimo' => 'Último preço de compra', 'por_insumo' => 'Definido em cada insumo'];
  $opcoesMetodo = ['' => 'Não definido', 'medio' => 'Médio', 'ultimo' => 'Último'];
  if ($compras_metodo_custo_atual === 'misto') {
      $opcoesMetodo = ['misto' => 'Variações com métodos diferentes (manter)'] + $opcoesMetodo;
  }
@endphp
<input type="hidden" name="has_module_data" value="1">
<div class="col-sm-4">
  <div class="form-group">
    {!! Form::label('compras_metodo_custo', 'Método de custo:') !!}
    {!! Form::select('compras_metodo_custo', $opcoesMetodo, $compras_metodo_custo_atual, array_filter([
        'class' => 'form-control',
        'id' => 'compras_metodo_custo',
        'disabled' => $compras_metodo_custo_editavel ? null : 'disabled',
    ])); !!}
    <p class="help-block">
      Vale quando a empresa escolheu "por insumo" em Fluxos → Compra. Vale para todas as variações do produto.
      Hoje a empresa usa: {{ $metodosEmpresa[$compras_metodo_custo_empresa] ?? $compras_metodo_custo_empresa }}.
    </p>
  </div>
</div>
