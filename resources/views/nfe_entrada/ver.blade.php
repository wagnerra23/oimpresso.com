@extends('layouts.app')

@section('title', 'Emitir NF-e')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
  <h1>Manter NF-e de Entrada</h1>
</section>

<!-- Main content -->
<section class="content">

  <div class="row">
    <div class="col-md-12">
      @component('components.widget')
      
      <input type="hidden" id="id" value="{{$transaction->id}}" name="">
      <div class="col-md-12">
        <h4>Número NF-e de Entrada: <strong>{{$transaction->numero_nfe}}</strong></h4>
        <h4>Natureza de Operação: <strong>{{$transaction->natureza->natureza}}</strong></h4>
        <h4>Cliente: <strong>{{$transaction->contact->name}}</strong></h4>
        <h4>CNPJ: <strong>{{$transaction->contact->cpf_cnpj}}</strong></h4>
        <h4>Estado: <strong>{{$transaction->estado}}</strong></h4>
        <h4>Chave: <strong>{{$transaction->chave}}</strong></h4>
      </div>

      <input type="hidden" id="numero_nfe" value="{{$transaction->numero_nfe}}" name="">
      
      <div class="clearfix"></div>


      <div class="col-md-12">
        <a class="btn btn-lg btn-primary" target="_blank" href="/nfeEntrada/imprimir/{{$transaction->id}}" id="submit_user_button">Imprimir</a>
        <a class="btn btn-lg btn-info" target="_blank" href="/nfeEntrada/baixarXml/{{$transaction->id}}" id="submit_user_button">Baixar XML</a>
        @if($transaction->estado != 'CANCELADO')
        <a class="btn btn-lg btn-danger" id="cancelar">Cancelar NF-e</a>
        @endif

        @if($transaction->estado == 'CANCELADO')
        <a class="btn btn-lg btn-question" style="background: #d84315; color: #fff" target="_blank" href="/nfeEntrada/imprimirCancelamento/{{$transaction->id}}" id="submit_user_button">Imprimir Cancelamento</a>
        @endif
      </div>
      
      @endcomponent
    </div>

  </div>

  

  <input type="hidden" id="token" value="{{csrf_token()}}" name="">
  <input type="hidden" id="id" value="{{$transaction->id}}" name="">

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

      $('#cancelar').click(() => {
        let numero_nfe = $('#numero_nfe').val();
        swal({
          text: 'Cancelamento de NF-e '+numero_nfe+'.',
          content: "input",
          button: {
            text: "Cancelar!",
            closeModal: false,
            type: 'error'
          },
          confirmButtonColor: "#DD6B55",
        })
        .then(v => {
          if (!v) swal("Erro!", "Informe um motivo para Cancelamento!", "error");
          else{
            let token = $('#token').val();
            let id = $('#id').val();
            $.ajax
            ({
              type: 'POST',
              data: {
                id: id,
                _token: token,
                justificativa: v
              },
              url: path + '/nfeEntrada/cancelar',
              dataType: 'json',
              success: function(e){
                console.log(e)

                swal("sucesso", e.retEvento.infEvento.xMotivo, "success")
                .then(() => {
                  window.open(path + '/nfeEntrada/imprimirCancelamento/'+id)
                  location.reload()
                });

              }, error: function(e){
                console.log(e.responseJSON.data.retEvento.infEvento.xMotivo)

                swal("Erro ao cancelar", e.responseJSON.data.retEvento.infEvento.xMotivo, "error");

              }

            })
          }         

        })
        
        .catch(err => {
          if (err) {
            swal("Erro", "Algo não ocorreu bem!", "error");
          } else {
            swal.stopLoading();
            swal.close();
          }
        });
      })

    </script>
    @endsection
