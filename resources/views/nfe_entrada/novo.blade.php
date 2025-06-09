@extends('layouts.app')
@section('title', 'Gerar NF-e de Entrada')

@section('content')
<!-- Content Header (Page header) -->


<!-- Main content -->
<section class="content">

	@component('components.widget', ['class' => 'box-primary'])


	<input type="hidden" value="{{$purchase->id}}" name="purchase_id">
	<h2 class="box-title">Emitir NF-e Entrada</h2>


	<div class="row">
		<div class="col-sm-12">
			<div class="form-group">
				<h3 class="box-title">Fornecedor</h3>

				<div class="row">
					<div class="col-sm-6">

						<span>Nome: <strong>{{$purchase->contact->name}}</strong></span><br>
						<span>CNPJ/CPF: <strong>{{$purchase->contact->cpf_cnpj}}</strong></span><br>
						<span>IE/RG: <strong>{{$purchase->contact->ie_rg}}</strong></span>
					</div>

					<div class="col-sm-6">

						<span>Rua: <strong>{{$purchase->contact->rua}}, {{$purchase->contact->numero}}</strong></span><br>
						<span>Bairro: <strong>{{$purchase->contact->bairro}}</strong></span><br>
						<span>Cidade: <strong>{{$purchase->contact->cidade->nome}} ({{$purchase->contact->cidade->uf}})</strong></span>

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
								<div class="table-responsive">
									<div id="product_table_wrapper" class="dataTables_wrapper form-inline dt-bootstrap no-footer">
										<div class="row margin-bottom-20 text-center">
											<table class="table table-bordered table-striped ajax_view hide-footer dataTable no-footer" id="product_table" role="grid" aria-describedby="product_table_info" style="width: 1300px;">
												<thead>
													<tr role="row">

														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 200px;" aria-label="Produto">Produto</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 80px;" aria-label="Produto">Código</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 80px;" aria-label="Produto">NCM</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 80px;" aria-label="Produto">Quantidade</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 80px;" aria-label="Produto">Valor Unit.</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 100px;" aria-label="Produto">Cod. Barras</th>
														<th class="sorting_disabled" rowspan="1" colspan="1" style="width: 80px;" aria-label="Produto">Unidade</th>
													</tr>
												</thead>

												<tbody>

													@foreach($purchase->purchase_lines as $i)

													<tr>
														<td style="width: 200px;">{{$i->product->name}}</td>
														<td style="width: 200px;">{{$i->product->id}}</td>
														<td style="width: 200px;">{{$i->product->ncm}}</td>
														<td style="width: 200px;">{{$i->quantity}}</td>
														<td style="width: 200px;">{{$i->purchase_price}}</td>
														<td style="width: 200px;">{{$i->product->sku}}</td>
														<td style="width: 200px;">{{$i->product->unit->short_name}}</td>

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

													@if(sizeof($purchase->payment_lines) > 0)

													@foreach($purchase->payment_lines as $key => $f)

													<tr>
														<td style="width: 80px;">{{$key+1}}</td>
														<td style="width: 80px;">{{ \Carbon\Carbon::parse($f->paid_on)->format('d/m/Y')}}</td>
														<td style="width: 80px;">{{number_format($f->amount, 2)}}</td>
														<tr>
															@endforeach
															@else
															<tr>
																<td colspan="3">Fatura Unica</td>
															</tr>
															@endif

														</tbody>
													</table>


												</div>

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

						<div class="form-group">

							<div class="col-sm-3">
								<div class="form-group">
									{!! Form::label('natureza_id', 'Natureza de Operação'. ':*') !!}
									{!! Form::select('natureza_id', $naturezas, null, ['id' => 'natureza_id', 'class' => 'form-control select2', 'placeholder' => __('messages.please_select')]); !!}
								</div>
							</div>

							<div class="col-sm-3">
								<div class="form-group">
									{!! Form::label('tipo_pagamento', 'Tipo de Pagamento'. ':*') !!}
									{!! Form::select('tipo_pagamento', $tiposPagamento, null, ['id' => 'tipo_pagamento', 'class' => 'form-control select2', 'placeholder' => __('messages.please_select')]); !!}
								</div>
							</div>

							
							<div class="col-sm-3"></div>

							<div class="col-sm-3">
								<h2>TOTAL: R$ <strong>{{number_format($purchase->total_before_tax, 2)}}</strong></h2>
							</div>

						</div>
					</div>

				</div>




				<div class="row">

					<div class="col-md-2">
						<form method="get" action="/nfeEntrada/renderizarDanfe" target="_blank">
							<input type="hidden" value="{{$purchase->id}}" name="purchase_id">
							<input type="hidden" value="" id="natureza_renderizar" name="natureza">
							<input type="hidden" value="" id="tipo_pagamento_renderizar" name="tipo_pagamento">
							<button style="width: 100%;" class="btn btn-lg btn-primary" type="submit">Renderizar</button>
						</form>
					</div>
					<div class="col-md-2">
						<form method="get" action="/nfeEntrada/gerarXml" target="_blank">
							<input type="hidden" value="{{$purchase->id}}" name="purchase_id" id="purchase_id">
							<input type="hidden" value="" id="natureza_xml" name="natureza">
							<input type="hidden" value="" id="tipo_pagamento_xml" name="tipo_pagamento">
							<button style="width: 100%;" class="btn btn-lg btn-danger" type="submit">Gerar XML</button>
						</form>
					</div>

					<input type="hidden" id="token" value="{{csrf_token()}}" name="">
					<a class="btn btn-lg btn-success" id="send-sefaz">Transmitir NF-e Entrada</a>


					<br>
					<div class="row" id="action" style="display: none">
						<div class="col-md-12">
							@component('components.widget')
							<div class="info-box-content">
								<div class="col-md-4 col-md-offset-4">

									<span class="info-box-number total_purchase">
										<strong id="acao"></strong>
										<i class="fas fa-spinner fa-pulse fa-spin fa-fw margin-bottom"></i></span>
									</div>
								</div>
								@endcomponent

							</div>
						</div>

					</div>

				</div>


			</div>

			@endcomponent


		</section>

		@section('javascript')
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.11/jquery.mask.min.js"></script>
		<script type="text/javascript">
			$('#perc_venda').mask('000.00')

			$('#natureza_id').change(() => {
				let natureza_id = $('#natureza_id').val();
				$('#natureza_xml').val(natureza_id)
				$('#natureza_renderizar').val(natureza_id)
			})

			$('#tipo_pagamento').change(() => {
				let tipo_pagamento = $('#tipo_pagamento').val();

				$('#tipo_pagamento_xml').val(tipo_pagamento)
				$('#tipo_pagamento_renderizar').val(tipo_pagamento)
			})


			$('#send-sefaz').click(() => {
				let token = $('#token').val();
				let purchase_id = $('#purchase_id').val();
				let natureza_id = $('#natureza_id').val();
				let tipo_pagamento = $('#tipo_pagamento').val();

				if(!natureza_id){
					swal("Erro", "Informe a natureza de operação", "warning")
				}
				if(!tipo_pagamento){
					swal("Erro", "Informe o tipo de pagamento", "warning")
				}
				else{
					$('#action').css('display', 'block')

					setTimeout(() => {
						$('#acao').html('Gerando XML');
					}, 50);

					setTimeout(() => {
						$('#acao').html('Assinando o arquivo');
					}, 800);

					setTimeout(() => {
						$('#acao').html('Transmitindo para sefaz');
					}, 1500);
					var path = window.location.protocol + '//' + window.location.host

					$.ajax
					({
						type: 'POST',
						data: {
							purchase_id: purchase_id,
							_token: token,
							natureza: natureza_id,
							tipo_pagamento: tipo_pagamento
						},
						url: path + '/nfeEntrada/transmitir',
						dataType: 'json',
						success: function(e){
							console.log(e)

							swal("sucesso", "NF-e emitida, recibo: " + e, "success")
							.then(() => {
								window.open(path + '/nfeEntrada/imprimir/'+purchase_id)
								location.href = '/nfeEntrada/ver/'+purchase_id
							});
							$('#action').css('display', 'none')


						}, error: function(e){

							console.log(e)
							if(e.status == 402){
								swal("Erro ao transmitir", e.responseJSON, "error");
								$('#action').css('display', 'none')

							}else{
								$('#action').css('display', 'none')
								let jsError = JSON.parse(e.responseJSON)
								console.log(jsError)
								swal("Erro ao transmitir", jsError.protNFe.infProt.xMotivo, "error");

							}
						}

					})
				}
			})



		</script>
		@endsection


		<!-- /.content -->

		@endsection
