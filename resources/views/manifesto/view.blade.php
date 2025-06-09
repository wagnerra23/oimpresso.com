@extends('layouts.app')
@section('title', 'Manifesto XML')

@section('content')
<!-- Content Header (Page header) -->


<!-- Main content -->
<section class="content">


	@component('components.widget', ['class' => 'box-primary'])

	@if(count($business_locations) == 1)
	@php 
	$default_location = current(array_keys($business_locations->toArray()));
	$search_disable = false; 
	@endphp
	@else
	@php $default_location = null;
	$search_disable = true;
	@endphp
	@endif
	<div class="col-sm-3">
		<div class="form-group">
			{!! Form::label('location_id', 'Atribuição de estoque em'.':*') !!}
			@show_tooltip(__('tooltip.purchase_location'))
			{!! Form::select('location_id', $business_locations, $default_location, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'required']); !!}
		</div>
	</div>

	<input type="hidden" value="{{json_encode($contact)}}" name="contact">
	<input type="hidden" value="{{json_encode($itens)}}" name="itens">
	<input type="hidden" value="{{json_encode($fatura)}}" name="fatura">
	<input type="hidden" value="{{json_encode($dadosNf)}}" name="dadosNf">

	<div class="row">
		<div class="col-sm-12">
			<div class="form-group">
				<h3 class="box-title">Fornecedor</h3>
				@if($dadosNf['novoFornecedor'])
				<p class="text-danger">*Este fornecedor não esta cadastrado no sistema!</p>
				@endif
				<div class="row">
					<div class="col-sm-6">

						<span>Nome: <strong>{{$contact['name']}}</strong></span><br>
						<span>CNPJ/CPF: <strong>{{$contact['cpf_cnpj']}}</strong></span><br>
						<span>IE/RG: <strong>{{$contact['ie_rg']}}</strong></span>


					</div>

					<div class="col-sm-6">

						<span>Rua: <strong>{{$contact['rua']}}, {{$contact['numero']}}</strong></span><br>
						<span>Bairro: <strong>{{$contact['bairro']}}</strong></span><br>
						<span>Cidade: <strong>{{$cidade->nome}} ({{$cidade->uf}})</strong></span>

					</div>
					<div class="col-sm-6">


						@if($dadosNf['novoFornecedor'])
						<form method="post" action="/manifesto/salvarFornecedor">
							@csrf
							<input type="hidden" value="{{json_encode($contact)}}" name="contato">
							<button class="btn btn-success">
								Incluir fornecedor
							</button>
						</form>
						@endif
					</div>
					
				</div>
			</div>
		</div>

		<div class="col-sm-12">
			<div class="form-group">
				<h3 class="box-title">Dados do Documento</h3>

				<div class="row">
					<div class="col-sm-12">

						<span>Chave: <strong>{{$dadosNf['chave']}}</strong></span><br>
						<span>Valor Integral: <strong>{{$dadosNf['vProd']}}</strong></span><br>
						<span>Número: <strong>{{$dadosNf['nNf']}}</strong></span><br>
						<span>Valor do frete: <strong>{{$dadosNf['vFrete']}}</strong></span><br>
						<span>Valor de desconto: <strong>{{$dadosNf['vDesc']}}</strong></span><br>
						<span>Valor Final: <strong>{{$dadosNf['vFinal']}}</strong></span><br>
					</div>

				</div>
			</div>
		</div>

		<div class="col-sm-12">
			<div class="form-group">
				<h3 class="box-title">Produtos</h3>


				<div class="">

					<!-- Inicio tabela -->
					<div class="nav-tabs-custom">

						<div class="tab-content">
							<div class="tab-pane active" id="product_list_tab">
								<br><br>
								<p class="text-danger">* Produtos em vermelho não possuem cadastro no sistema</p>
								<p class="text-info">* Produtos em azul já atribuidos no estoque</p>

								<div class="table-responsive">
									<div id="product_table_wrapper" class="dataTables_wrapper form-inline dt-bootstrap no-footer">
										<div class="row margin-bottom-20 text-center">

											<div class="row">
												<div class="col-sm-3">
													<div class="form-group">
														{!! Form::label('perc_venda', '% de acrescimo para valor de venda, sobre o valor de compra' . ':') !!}
														{!! Form::text('perc_venda', $lucro, ['id' => 'perc_venda', 'class' => 'form-control']); !!}
													</div>
												</div>
											</div>

											<table class="table table-bordered table-striped ajax_view hide-footer dataTable no-footer" id="product_table" role="grid" aria-describedby="product_table_info" style="width: 1300px;">
												<thead>
													<tr role="row">

														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 200px;" aria-label="Produto">Produto</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 80px;" aria-label="Produto">Código</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 80px;" aria-label="Produto">NCM</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 80px;" aria-label="Produto">CFOP</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 80px;" aria-label="Produto">Quantidade</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 80px;" aria-label="Produto">Valor Unit.</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 100px;" aria-label="Produto">Cod. Barras</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 80px;" aria-label="Produto">Unidade</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 80px;" aria-label="Produto">Conversão Unitária</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 80px;" aria-label="Produto">Ações</th>
													</tr>
												</thead>

												<tbody id="itens">

													@foreach($itens as $key => $i)

													<tr>
														<form @if(!$i['produtoNovo']) action="/manifesto/atribuirEstoque" @else action="/manifesto/cadProd" @endif method="get">
															<td @if($i['produtoNovo']) class="text-danger" @elseif($i['tp']) class="text-info" @endif style="width: 200px;">{{$i['xProd']}}</td>
															<td style="width: 80px;">{{$i['codigo']}}</td>
															<td style="width: 80px;">{{$i['NCM']}}</td>
															<td style="width: 80px;">{{$i['CFOP']}}</td>
															<td style="width: 80px;">{{$i['qCom']}}</td>
															<td style="width: 80px;">{{$i['vUnCom']}}</td>
															<td style="width: 100px;">{{$i['codBarras']}}</td>
															<td style="width: 100px;">{{$i['uCom']}}</td>
															<td style="width: 80px;">
																<input value="1" type="" name="tx_conv">
																<label>Somente números</label>
															</td>

															<input type="hidden" value="{{$i['xProd']}}" name="nome">
															<input type="hidden" value="{{$i['NCM']}}" name="ncm">
															<input type="hidden" value="{{$i['CFOP']}}" name="cfop">
															<input type="hidden" value="{{$i['qCom']}}" name="quantidade">
															<input type="hidden" value="{{$i['vUnCom']}}" name="valor">
															<input type="hidden" value="{{$i['codBarras']}}" name="codBarras">
															<input type="hidden" value="{{$i['uCom']}}" name="unidade">
															<input type="hidden" value="{{$lucro}}" name="perc" class="pc">
															<input type="hidden" value="" name="location_id" class="location">
															<input type="hidden" value="{{$dadosNf['nNf']}}" name="numero_nfe">

															<td style="width: 80px;">
																@if($i['produtoNovo'])
																<button class="btn btn-success" type="submit">
																	Adicionar
																</button>
																@endif

																@if(!$i['produtoNovo'] && !$i['tp'])
																<button class="btn btn-danger" type="submit">
																	Atribuir estoque
																</button>
																@endif

																@if($i['tp'])
																<a class="btn btn-danger" disabled >
																	Atribuir estoque
																</a>
																@endif
															</td>
														</form>

													</tr>
													@endforeach

												</tbody>
											</table>

											
										</div>

									</div>


								</div>
							</div>
						</div>
					</div>

					<!-- fim tabela -->
				</div>
			</div>
		</div>

		<div class="col-sm-12">
			<div class="form-group">
				<h3 class="box-title">Fatura</h3>
				<div class="">
					
					<div class="nav-tabs-custom">


						<div class="tab-content">
							<div class="tab-pane active" id="product_list_tab">
								<br><br>
								<div class="table-responsive">
									<div id="product_table_wrapper" class="dataTables_wrapper form-inline dt-bootstrap no-footer">
										<div class="row margin-bottom-20 text-center">
											<table class="table table-bordered table-striped ajax_view hide-footer dataTable no-footer" id="product_table" role="grid" aria-describedby="product_table_info" style="width: 700px;">
												<thead>
													<tr role="row">

														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 100px;" aria-label="Produto">Número</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 100px;" aria-label="Produto">Vencimento</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 100px;" aria-label="Produto">Valor</th>
													</tr>
												</thead>

												<tbody>

													@if(sizeof($fatura) > 0)

													@foreach($fatura as $f)

													<tr>
														<td style="width: 200px;">{{$f['numero']}}</td>
														<td style="width: 80px;">{{$f['vencimento']}}</td>
														<td style="width: 80px;">{{$f['valor_parcela']}}</td>

													</tr>
													@endforeach

													
													@else
													<tr>
														<td colspan="3">Nenhuma fatura neste XML</td>
													</tr>
													@endif

												</tbody>
											</table>


										</div>
										@if(sizeof($fatura) > 0)
										@if($dadosNf['novoFornecedor'])

										<p class="text-danger">*Inclua o fornecedor para salvar a fatura</p>

										@else

										@if($faturaAnterior == null)
										<div class="row">
											<form method="post" action="/manifesto/salvarFatura">
												@csrf
												<input type="hidden" value="{{json_encode($fatura)}}" name="fatura">
												<input type="hidden" value="{{$dadosNf['vDesc']}}" name="desconto">
												<input type="hidden" value="{{$dadosNf['nNf']}}" name="numero_nfe">
												<input type="hidden" value="{{$fornecedor->id}}" name="fornecedor_id">
												<input type="hidden" value="" name="location_id" class="location">

												<button class="btn btn-danger">Salvar Fatura</button>
											</form>
										</div>
										@else
										<p class="text-danger">Esta fatura já foi salva!!</p>
										@endif
										
										@endif
										@endif

									</div>

								</div>
							</div>
						</div>
					</div>

				</div>
			</div>
		</div>

		<div class="row">


			<div class="col-sm-12">
				<a href="/manifesto/baixarXml/{{$chave}}" class="btn btn-primary pull-right btn-flat">Baixar XML</a>
			</div>
		</div>


	</div>

	@endcomponent



</section>

@section('javascript')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.11/jquery.mask.min.js"></script>
<script type="text/javascript">

	$(document).ready( function(){
		let id = $('#location_id').val()
		$('.location').val(id)

	});

	$('#location_id').change(() => {
		let id = $('#location_id').val()
		$('.location').val(id)
	})

	$('#perc_venda').mask('000.00')

	$('#perc_venda').keyup((v) => {
		v = v.target.value
		$('.pc').val(v)
	})

	$('.cn').keyup(() => {
		percorreTabela()
	})


	function percorreTabela(){
		let valores = '';
		let valido = true;
		$('#itens tr').each(function(){
			if(!$(this).find('.cn').val()) valido = false;
			valores += ($(this).find('.cn').val()) + ',';
		});

		if(valido){
			valores = valores.substring(0, valores.length-1);
			$('#conversao').val(valores)
		}else{

		}
	}
</script>
@endsection


<!-- /.content -->

@endsection
