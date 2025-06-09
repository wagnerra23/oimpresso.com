@extends('layouts.app')

@section('title', 'Emitir CT-e')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
  <h1>Emitir CT-e</h1>
</section>

<!-- Main content -->
<section class="content">

  <div class="row">
    <div class="col-md-12">
      @component('components.widget')
      
      <input type="hidden" id="id" value="{{$cte->id}}" name="">
      <div class="col-md-5">
        <h4>Ultimo numero NF-e: <strong>{{$cte->lastCTe()}}</strong></h4>
        <h4>Remetente: <strong>{{$cte->remetente->cpf_cnpj}}</strong></h4>
        <h4>Destinatário: <strong>{{$cte->destinatario->name}}</strong></h4>
        <h4>Natureza de Operação: <strong>{{$cte->natureza->natureza}}</strong></h4>
        <h4>Tomador: <strong>{{$cte->getTomador()}}</strong></h4>
        <h4>Produto predominante: <strong>{{$cte->produto_predominante}}</strong></h4>
        <h4>Data precisa de entrega: <strong>{{ \Carbon\Carbon::parse($cte->data_previsata_entrega)->format('d/m/Y')}}</strong></h4>
      </div>
      
      <div class="clearfix"></div>


      <div class="col-md-12">
        <a class="btn btn-lg btn-primary" target="_blank" href="/cte/renderizar/{{$cte->id}}" id="submit_user_button">Renderizar</a>
        <a class="btn btn-lg btn-danger" target="_blank" href="/cte/gerarXml/{{$cte->id}}" id="submit_user_button">Gerar XML</a>
        <a class="btn btn-lg btn-success" id="send-sefaz">Transmitir para Sefaz</a>
      </div>

      
      @endcomponent
    </div>

  </div>

  

  <input type="hidden" id="token" value="{{csrf_token()}}" name="">

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

    @stop



    @section('javascript')
    <script type="text/javascript">
      // swal("Good job!", "You clicked the button!", "success");
      var path = window.location.protocol + '//' + window.location.host

      $('#send-sefaz').click(() => {
        $('#send-sefaz').addClass('disabled')
        $('#action').css('display', 'block')
        let token = $('#token').val();
        let id = $('#id').val();

        setTimeout(() => {
          $('#acao').html('Gerando XML');
        }, 50);

        setTimeout(() => {
          $('#acao').html('Assinando o arquivo');
        }, 800);

        setTimeout(() => {
          $('#acao').html('Transmitindo para sefaz');
        }, 1500);

        $.ajax
        ({
          type: 'POST',
          data: {
            id: id,
            _token: token
          },
          url: path + '/cte/transmitir',
          dataType: 'json',
          success: function(e){
            console.log(e)

            swal("sucesso", "CT-e emitida, recibo: " + e.recibo, "success")
            .then(() => {
              window.open(path + '/cte/imprimir/'+id)
              location.reload()
            });
            $('#action').css('display', 'none')
            

          }, error: function(e){
            // let jsError = JSON.parse(e.responseJSON);
            console.log(e)
            try{
              if(e.status == 402){
                swal("Erro ao transmitir", e.responseJSON, "error");
                $('#action').css('display', 'none')

              }else if(e.status == 407){
                swal("Erro ao criar Xml", e.responseJSON, "error");
                $('#action').css('display', 'none')

              }
              else if(e.status == 404){
                $('#action').css('display', 'none')
                swal("Erro", e, "error");

              }
              else{
                $('#action').css('display', 'none')
                let jsError = JSON.parse(e.responseJSON)
                console.log(jsError)
                swal("Erro ao transmitir", jsError.protCTe.infProt.xMotivo, "error");

              }
            }catch{
              try{
                swal("Erro", e.responseJSON, "error");
              }catch{
                let js = e.responseJSON
                swal("Erro", js.message, "error");


              }
            }
          }

        })
      })


    </script>
    @endsection
