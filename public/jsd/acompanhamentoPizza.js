
let adicionais = [];
function selet_add(id, adicional){
	console.log(adicional.valor)

	verificaAdicionado(id, (res) => {

		if(res == true){
			$('#adicional_'+id).css('background', '#fff')
			removeElemento(id)
		}else{
			$('#adicional_'+id).css('background', '#81c784')
			adicionais.push({
				'id': adicional.id,
				'valor': adicional.valor
			})
		}

		console.log(adicionais)
		somaTotal();
	})

}

function removeElemento(elem_id){
	let temp = [];
	adicionais.map((v) => {
		if(v.id != elem_id){
			temp.push(v)
		}
	});

	adicionais = temp;
}

function verificaAdicionado(elem_id, call){
	let b= false;
	adicionais.map((v) => {
		if(v.id == elem_id){
			console.log(elem_id, v.id)

			b = true;
		}
	});
	call(b);
}

function somaTotal(){
	let valorProduto = $('#valor_produto').html();
	valorProduto = parseFloat(valorProduto)
	adicionais.map((v) => {
		console.log(v.valor)
		valorProduto += parseFloat(v.valor);
	})
	$('#valor_total').html(convertMoney(valorProduto))
}

function convertMoney(v){
	return v.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}


function adicionar(){
	let tk = $('#_token').val();
	let sabores = JSON.parse($('#sabores').val());
	let quantidade = $('#quantidade').val();
	let observacao = $('#observacao').val();
	let tamanho = $('#tamanho').val();

	let js = {
		_token: tk, 
		sabores: sabores,
		tamanho: tamanho,
		adicionais: adicionais,
		quantidade: quantidade,
		observacao: observacao
	};
	console.log(js)

	$.post(path + "carrinho/addPizza", js
	)
	.done(function(data) {
		console.log(data)
		if(data == '401'){
			alert("Você precisa estar logado");
		}
		else if(data == 'false'){
			alert("Você está com um pedido pendente, aguarde o processamento");
		}else{
			sucesso();
		}
	})
	.fail( function(err) {
		console.log(err)

	});

}

function sucesso(){
	$('#content').css('display', 'none');
	$('#anime').css('display', 'block');
	setTimeout(() => {
		location.href = path + 'carrinho';
	}, 3000)
}
