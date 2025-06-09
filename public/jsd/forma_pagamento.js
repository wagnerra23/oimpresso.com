
let enderecoSelecionado = null;
var TOTAL = 0;
var VALORENTREGA = 0;
var ENTREGA = false;
var DESCONTO = 0;
var DESCONTOAPLICADO = false;
var latPadrao = 0;
var lngPadrao = 0;
var BANDEIRA = "";
var INSTALLMENTS = [];

let lat = latPadrao;
let lng = lngPadrao;

var HASHCLIENTE = '';
var TOKENCARTAO = '';

$(function(){

	MAXIMOPARCELAMENTO = $('#maximo_parcelamento').val();

	lat = latPadrao = $('#lat_padrao').val()
	lng = lngPadrao = $('#lng_padrao').val()

	TOTAL = $('#total-init').val();
	TOTAL = parseFloat(TOTAL);
	$('#total').html("R$ "+TOTAL.toFixed(2))

	let cupom = $('#cupom').val();
	if(cupom.length == 6){
		calculaCupom(cupom);
	}else{
		if(cupom.length > 1){
			$('#cupom-invalido').css('display', 'block');
			$('#desconto').css('display', 'none');
		}
	}

	getCurrentLocation((crd) => {

		if(crd){
			initMap(crd.latitude, crd.longitude);
		}else{
			alert('Não foi possivel recuperar sua localização, ative e recarregue a pagina!');
			initMap(latPadrao, lngPadrao);
		}
	});

	$.get(path + 'pagseguro/getSessao')
	.done((success) => {
		let token = success.id
		// console.log('token da compra', token)
		let res = PagSeguroDirectPayment.setSessionId(token);

	})
	.fail((err) => {
		console.log(err)
	})

})

$("#cpf").focus(function(){ 

	HASHCLIENTE = PagSeguroDirectPayment.getSenderHash();
	console.log('HASHCLIENTE', HASHCLIENTE)
});

$("#cvc").keyup(function(){ 

	if($("#cvc").val().length > 2){
		let numCartao = $("#number").val().replace(" ", "").replace(" ", "").replace(" ", "");
		let cvvCartao = $("#cvc").val();
		let validade = $("#validade").val().split('/');
		expiracaoMes = validade[0].replace(" ", "");
		expiracaoAno = validade[1].replace(" ", "");
		console.log(numCartao)
		console.log(cvvCartao)
		console.log(validade)
		PagSeguroDirectPayment.createCardToken({
			cardNumber: numCartao,
			cvv: cvvCartao,
			expirationMonth: expiracaoMes,
			expirationYear: expiracaoAno,

			success: function(response){ 
				TOKENCARTAO = response['card']['token'];
				console.log(TOKENCARTAO)
			},
			error: function(response){ 
				console.log(response); 
				// alert("Data de validade incorreta")
			}
		});
	}

});

function getTokenCartao(){
	if($("#cvc").val().length > 2){
		let numCartao = $("#number").val().replace(" ", "").replace(" ", "").replace(" ", "");
		let cvvCartao = $("#cvc").val();
		let validade = $("#validade").val().split('/');
		expiracaoMes = validade[0].replace(" ", "");
		expiracaoAno = validade[1].replace(" ", "");
		console.log(numCartao)
		console.log(cvvCartao)
		console.log(validade)
		PagSeguroDirectPayment.createCardToken({
			cardNumber: numCartao,
			cvv: cvvCartao,
			expirationMonth: expiracaoMes,
			expirationYear: expiracaoAno,

			success: function(response){ 
				TOKENCARTAO = response['card']['token'];
				console.log(TOKENCARTAO)
			},
			error: function(response){ 
				console.log(response); 
				// alert("Data de validade incorreta")
			}
		});
	}
}
function getParcelas(){

	PagSeguroDirectPayment.getInstallments({
		amount: TOTAL,
		brand: BANDEIRA,
		maxInstallmentNoInterest: 1,
		success: function(response) {
			installments = response.installments

			INSTALLMENTS = installments[BANDEIRA];
			$('#fator').html('');
			INSTALLMENTS.map((v) => {
				// console.log(v)
				$('#fator').append('<option value="'+v.quantity+'">'+v.quantity+'x R$ ' + 
					parseFloat(v.installmentAmount).toFixed(2) + '</option>'); 
			})
		},
		error: function(response) {
			console.log(response);
		}
	})
}

$('#number').keyup(() => {
	let numero = $('#number').val().replace(" ", "").replace(" ", "").replace(" ", "");
	if(numero.length > 5){
		getBrand(numero)
	}else{
	}
})

function getBrand(numero){

	PagSeguroDirectPayment.getBrand( {
		cardBin: numero,
		success: function(response) {
			BANDEIRA = response['brand']['name'];
			getParcelas(BANDEIRA)
			
		},
		error: function(response) {
			console.log(response)
		}
	});
}

$('#cupom').on('keyup', () => {
	let cupom = $('#cupom').val();
	if(cupom.length == 6){
		calculaCupom(cupom);

	}else{
		DESCONTOAPLICADO = false;
		TOTAL += DESCONTO;
		DESCONTO = 0;
		if(cupom.length > 3){
			$('#total').html("R$ "+parseFloat(TOTAL).toFixed(2))
			$('#cupom-invalido').css('display', 'block');
			$('#desconto').css('display', 'none');
		}else{
			$('#cupom-invalido').css('display', 'none');
		}
	}

})

function calculaCupom(cupom){

	getCupom(cupom, (res) => {
		let js = JSON.parse(res)
		console.log(js)
		if(js){
			$('#cupom-invalido').css('display', 'none');
			$('#desconto').css('display', 'block');
			$('#valor-cupom').html((js.tipo == 'valor' ? 'R$': '') + parseFloat(js.valor).toFixed(2) 
				+ (js.tipo == 'percentual' ? '%': ''))
			if(js.tipo == 'valor'){
				DESCONTO = parseFloat(js.valor);
				if(!DESCONTOAPLICADO) TOTAL = TOTAL - DESCONTO;
				DESCONTOAPLICADO = true;
			}else{
				let v = parseFloat(js.valor);
				DESCONTO = ((TOTAL*v)/100);
				if(!DESCONTOAPLICADO) TOTAL = TOTAL - DESCONTO;
				DESCONTOAPLICADO = true;
			}
			console.log(TOTAL)
			$('#total').html("R$ "+parseFloat(TOTAL).toFixed(2))
		}
	})
}

$('#maquineta').click(() => {
	verificaBotaoFinalizarSemCartao();
	$('#div_do_troco').css('display', 'none');
})
$('#pagseguro').click(() => {
	verificaBotaoFinalizarSemCartao();

	let formaPagamento = $('#maquineta').is(':checked') ? 'maquineta' :  
	$('#dinheiro').is(':checked') ? 'dinheiro' : $('#pagseguro').is(':checked') ?
	'pagseguro' : '';

	let troco = $('#troco_para').val();
	let telefone = $('#telefone').val();
	let cupom = $('#cupom').val();

	if(telefone.length > 12 && enderecoSelecionado != null){
		$('#div_do_troco').css('display', 'none');
		$('#total-cartao').html("R$ "+parseFloat(TOTAL).toFixed(2))
		// $('#nome').val($('#nome-cliente').val())

		location.href="#modal-pagseguro"
		$('#abre-modal').modal('click');
		HASHCLIENTE = PagSeguroDirectPayment.getSenderHash();
		console.log('HASHCLIENTE', HASHCLIENTE)
	}else{
		
		if(!enderecoSelecionado){
			alert("Por favor selecione a forma de entrega")

		}
		else if(telefone.length < 12){
			alert("Por favor informe um telefone de contato (11) 99999-9999")
		}
	}
});

$('#dinheiro').click(() => {
	verificaBotaoFinalizarSemCartao();
	$('#div_do_troco').css('display', 'block');
})

function getCupom(cupom, call){
	$.get(path+'carrinho/cupom/'+cupom)
	.done((sucess) => {
		call(sucess)
	})
	.fail((err) => {
		call(err)
	})
}

$('#salvar_endereco').click(() => {
	let rua = $('#rua').val()
	let numero = $('#numero').val()
	let bairro = $('#bairro').val()
	let referencia = $('#referencia').val()
	let cliente_id = $('#cliente_id').val()
	let tk = $('#_token').val()

	let js = {
		rua: rua,
		numero: numero,
		bairro: bairro,
		referencia: referencia,
		cliente_id: cliente_id,
		latitude: lat,
		longitude: lng
	}

	console.log(js)

	$.post(path+'enderecoDelivery/save', {_token : tk, data: js})
	.done(function(data){
		data = JSON.parse(data)
		console.log(data)
		let ht = '<div class="col-lg-4 col-md-6" onclick="set_endereco('+data.id+')">'+
		'<div id="endereco_select_'+data.id+'" class="card border-0 med-blog">'+

		'<div class="card-body border border-top-0">'+
		'<h5 class="blog-title card-title m-0">'+
		rua + ', ' + numero+
		'</h5>'+
		'<h5>'+bairro+'</h5>'+
		'<p>Referencia: '+ referencia +'</p>'+
		'</div>'+
		'</div>'+
		'</div>';

		$( ".ends" ).append( ht );
	})
	.fail( function(err) {
		console.log(err)

	});

})

function set_endereco(id){

	$('#endereco_select_'+enderecoSelecionado).css('background', '#fff')

	if(id == 'balcao'){
		$('#endereco_select_balcao').css('background', '#81c784')
		$('#acrescimo-entrega').css('display', 'none')
		if(ENTREGA == true){
			TOTAL -= parseFloat(VALORENTREGA)
			$('#total').html("R$ "+parseFloat(TOTAL).toFixed(2))
		}
		ENTREGA = false;

	}else{
		getValorEntrega((d) => {
			let j = JSON.parse(d)
			if(ENTREGA == false){
				VALORENTREGA = j.valor_entrega;
				TOTAL += parseFloat(VALORENTREGA)
				$('#acrescimo-entrega').css('display', 'block')
				$('#valor-entrega').html(VALORENTREGA)
				$('#total').html("R$ "+parseFloat(TOTAL).toFixed(2))
			}
			ENTREGA = true;
		})
		$('#endereco_select_balcao').css('background', '#fff')

		$('#endereco_select_'+id).css('background', '#81c784')
		
	}
	enderecoSelecionado = id;
	verificaBotaoFinalizarSemCartao();
}


$('#finalizar-venda').click(() => {
	let formaPagamento = $('#maquineta').is(':checked') ? 'maquineta' :  
	$('#dinheiro').is(':checked') ? 'dinheiro' : $('#pagseguro').is(':checked') ?
	'pagseguro' : '';

	let troco = $('#troco_para').val();
	let telefone = $('#telefone').val();
	let cupom = $('#cupom').val();
	let js = {
		forma_pagamento: formaPagamento,
		troco: troco.replace(",", "."),
		observacao: $('#observacao').val(),
		endereco_id: enderecoSelecionado,
		pedido_id: $('#pedido_id').val(),
		telefone: telefone,
		desconto: DESCONTO,
		cupom: DESCONTO > 0 ? cupom : ''
	}
	console.log(js)
	if(!formaPagamento){
		alert("Por favor selecione a forma de pagamento")
	}
	else if(!enderecoSelecionado){
		alert("Por favor selecione a forma de entrega")

	}
	else if(telefone.length <= 12){
		alert("Por favor informe um telefone de contato (11) 99999-9999")
	}

	else if(formaPagamento == 'dinheiro' && troco.length == 0 || parseFloat(troco.replace(",", ".")) == 0){
		alert("Por favor insira o valor de troco para")
	}

	else if(formaPagamento == 'dinheiro' && parseFloat(troco.replace(",", ".")) < TOTAL){
		alert("Valor do troco deve ser maior que o valor total do pedido")
		if(ENTREGA) alert('Total com entrega: R$' + TOTAL.toFixed(2))
			else alert('Total: R$' + TOTAL.toFixed(2))
		}

	else{
		console.log(js)
		let tk = $('#_token').val()

		$.post(path+'carrinho/finalizarPedido', {_token : tk, data: js})
		.done(function(data){
			data = JSON.parse(data)
			console.log(data)

			sucesso(data.id)

		})
		.fail( function(err) {
			console.log(err)

		});

	}

})

function getValorEntrega(call){
	$.get(path+'carrinho/configDelivery')
	.done(function(data){
		console.log(data)
		call(data)
	})
	.fail(function(err){
		console.log(err)
		call(err)
	})
}

function sucesso(id){
	$('#content').css('display', 'none');
	$('#anime').css('display', 'block');
	setTimeout(() => {
		location.href = path + 'carrinho/finalizado/'+id;
	}, 4000)
}


function getCurrentLocation(call){
	var options = {
		enableHighAccuracy: true,
		timeout: 5000,
		maximumAge: 0
	};

	function success(pos) {
		var crd = pos.coords;
		call(crd);
	};

	function error(err) {
		console.warn('ERROR(' + err.code + '): ' + err.message);
		call(false)
	};

	navigator.geolocation.getCurrentPosition(success, error, options);
}


function initMap(lat, lng){
	lat = lat;
	lng = lng;
	const position = new google.maps.LatLng(lat, lng);

	var map = new google.maps.Map(document.getElementById('map'), {
		zoom: 16,
		center: position,
		disableDefaultUI: false
	});	

	console.log(lat)

	const marker = new google.maps.Marker({
		position: position,
		map: map,
		animation: google.maps.Animation.BOUNCE,
		draggable: true
	})

	getEnderecoByCoords(lat, lng, (res) => {

		if(res == false){

		}else{
			$('#rua').val(res.rua)
			$('#numero').val(res.numero)
			validaCamposNovoEndereco();
		}
	})

	google.maps.event.addListener(marker, 'dragend', (event) => {
		var myLatLng = event.latLng;
		var lat = myLatLng.lat();
		var lng = myLatLng.lng();

		lat = lat;
		lng = lng;
		console.log(lat)

		getEnderecoByCoords(lat, lng, (res) => {
			if(res == false){

			}else{
				$('#rua').val(res.rua)
				$('#numero').val(res.numero)
				validaCamposNovoEndereco();
			}
		})
	})

}

$('#telefone').keyup(() => {
	verificaBotaoFinalizarSemCartao();
})

function verificaBotaoFinalizarSemCartao(){
	let telefone = $('#telefone').val();
	if(telefone.length > 12 && $('#pagseguro').is(':checked') == false && enderecoSelecionado != null){
		$('#finalizar-venda').removeClass('disabled')
	}else{
		$('#finalizar-venda').addClass('disabled')
	}
}

function getEnderecoByCoords(lat, lng, call){
	geocoder = new google.maps.Geocoder();
	var latlng = new google.maps.LatLng(lat, lng);
	geocoder.geocode({
		'latLng': latlng
	}, function (results, status) {
		if (status === google.maps.GeocoderStatus.OK) {
			if (results[1]) {
				let res = results[1].address_components;
				let numero = res[0].long_name;
				let rua = res[1].long_name;

				let js = {
					numero: res[0].long_name,
					rua: res[1].long_name
				};

				call(js);
				
			} else {
				console.log('No results found');
				call(false);
			}
		} else {
			console.log('Geocoder failed due to: ' + status);
			call(false);
		}
	});
}

$('#btn-end-map').click(() => {
	$('#info-mapa').css('display', 'none');
	$('#form-endereco').css('display', 'block');
})

$('#novo-endereco').click(() => {
	$('#info-mapa').css('display', 'block');
	$('#form-endereco').css('display', 'none');
})

$('.fr').keyup(() => {
	validaCamposNovoEndereco()
})

function validaCamposNovoEndereco(){
	let rua = $('#rua').val();
	let numero = $('#numero').val();
	let bairro = $('#bairro').val();
	let referencia = $('#referencia').val();

	if(rua.length > 0 && numero.length > 0 && 
		bairro.length > 0 && referencia.length){
		$('#salvar_endereco').removeClass('disabled')
}else{
	$('#salvar_endereco').addClass('disabled')
}
}

$('#abrir-mapa').click(() => {
	$('#info-mapa').css('display', 'block');
	$('#form-endereco').css('display', 'none');
})

$('#finalizar-venda-cartao').click(() => {
	getTokenCartao();
	$('#icon-spin').css('display', 'inline-block')

	let formaPagamento = $('#maquineta').is(':checked') ? 'maquineta' :  
	$('#dinheiro').is(':checked') ? 'dinheiro' : $('#pagseguro').is(':checked') ?
	'pagseguro' : '';

	let troco = $('#troco_para').val();
	let telefone = $('#telefone').val();
	let cupom = $('#cupom').val();
	let cpf = $('#cpf').val().replace("-", "").replace(".", "").replace(".", "");
	let nome_cartao = $('#nome').val();
	let email = $('#email-cliente').val();
	let numCartao = $("#number").val().replace(" ", "").replace(" ", "").replace(" ", "");

	getInstallment((installment) => {
		let js = {
			forma_pagamento: formaPagamento,
			troco: troco.replace(",", "."),
			observacao: $('#observacao').val(),
			endereco_id: enderecoSelecionado,
			pedido_id: $('#pedido_id').val(),
			telefone: telefone,
			desconto: DESCONTO,
			total: parseFloat(installment.totalAmount).toFixed(2),
			cupom: DESCONTO > 0 ? cupom : '',

			produto_nome: "Refeicao",
			valor: parseFloat(installment.installmentAmount).toFixed(2),
			telefone: telefone.replace(" ", "").replace("-", ""),
			cpf: cpf,
			email: email,
			hashCliente: HASHCLIENTE,
			creditCardToken: TOKENCARTAO,
			nome_cartao: nome_cartao,
			parcelas: installment.quantity,
			numero_cartao: numCartao,
			bandeira: BANDEIRA
		}
		console.log(js)

		let tk = $('#_token').val()
		$.post(path+'pagseguro/efetuaPagamento', {_token : tk, data: js})
		.done(function(data){
			console.log(data)
			$('#icon-spin').css('display', 'none')
			if(data.consulta.original.status == "3"){
				alert("Pagamento Aprovado")
				location.href = path + 'carrinho/finalizado/'+data.pedido_id;
			}

		})
		.fail(function(err){
			$('#icon-spin').css('display', 'none')
			if(err.status == 403){
				json = err.responseJSON;
				console.log(json)
				alert("403: Erro de pagamento!");

			}else if(err.status == 404){
				json = err.responseJSON;
				console.log(json)
				alert("404: Pagamento não autorizado!");
			}
			else if(err.status == 402){
				json = err.responseJSON;
				console.log(json)
				alert("402: Pagamento ainda não aprovado pelo getway!");
			}
			console.log(err)
		});

	});



})

function getInstallment(call){
	let fator = $('#fator').val();
	INSTALLMENTS.map((v) => {
		if(v.quantity == fator){
			console.log(v)
			call(v)
		}
	})
}

$("input[name=escolha-cartao]").change(() => {
	$('#div-cartao-antigo').css('display', 'none');
	$('#div-pagar').css('display', 'block');

	let escolha = JSON.parse($("input[name=escolha-cartao]:checked").val())
	if(escolha != null){
		$('#nome').val(escolha.nome_impresso)
		$('#cpf').val(escolha.cpf)
		$('#number').val(escolha.numero_cartao)

		var keyupEvent= new Event('keyup');
		document.getElementById('number').dispatchEvent(keyupEvent);
		document.getElementById('nome').dispatchEvent(keyupEvent);
		// $('#number').focus().blur();
		$('#cpf').focus().blur();
		// $('#number').focus()
		
		let numero = escolha.numero_cartao;
		if(numero.length > 5){
			getBrand(numero)
			getParcelas();
		}else{
		}
	}else{
		$('#nome').val('')
		$('#cpf').val('')
		$('#number').val('')
		console.log("nova cartao")

	}
	console.log(escolha)
})

$('#voltar').click(() => {
	$('#nome').val("")
	$('#cpf').val("")
	$('#number').val("")
	$('#cvc').val("")
	$('#validade').val("")
	$('#fator').html('');
	$('#div-cartao-antigo').css('display', 'block');
	$('#div-pagar').css('display', 'none');

})
