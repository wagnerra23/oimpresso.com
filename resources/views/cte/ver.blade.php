@extends('layouts.app')

@section('title', 'Emitir NF-e')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
  <h1>Manter CT-e</h1>
</section>

<!-- Main content -->
<section class="content">

  <div class="row">
    <div class="col-md-12">
      @component('components.widget')
      
      <input type="hidden" id="id" value="{{$cte->id}}" name="">
      <div class="col-md-12">
        <h4>Número CT-e: <strong>{{$cte->cte_numero}}</strong></h4>
        <h4>Natureza de Operação: <strong>{{$cte->natureza->natureza}}</strong></h4>
        <h4>Remetente: <strong>{{$cte->remetente->cpf_cnpj}}</strong></h4>
        <h4>Destinatário: <strong>{{$cte->destinatario->name}}</strong></h4>
        <h4>Tomador: <strong>{{$cte->getTomador()}}</strong></h4>
        <h4>Produto predominante: <strong>{{$cte->produto_predominante}}</strong></h4>
        <h4>Data precisa de entrega: <strong>{{ \Carbon\Carbon::parse($cte->data_previsata_entrega)->format('d/m/Y')}}</strong></h4>
        <h4>Estado: <strong>{{$cte->estado}}</strong></h4>
        <h4>Chave: <strong>{{$cte->chave}}</strong></h4>
      </div>

      <input type="hidden" id="cte_numero" value="{{$cte->cte_numero}}" name="">
      
      <div class="clearfix"></div>


      <div class="col-md-12">
        <a class="btn btn-lg btn-primary" target="_blank" href="/cte/imprimir/{{$cte->id}}" id="submit_user_button">Imprimir</a>
        <a class="btn btn-lg btn-info" target="_blank" href="/cte/baixarXml/{{$cte->id}}" id="submit_user_button">Baixar XML</a>

        <a class="btn btn-lg btn-question" style="background: #673ab7; color: #fff" id="consultar">
        Consultar</a>
        
        @if($cte->estado != 'CANCELADO')
        <a class="btn btn-lg btn-danger" id="cancelar">Cancelar CT-e</a>
        <!-- <a class="btn btn-lg btn-warning" id="corrigir">Corrigir CT-e</a> -->
        @endif


        @if($cte->sequencia_cce > 0)
        <a class="btn btn-lg btn-question" style="background: #90caf9; color: #fff" target="_blank" href="/cte/imprimirCorrecao/{{$cte->id}}" id="submit_user_button">Imprimir Correção</a>
        @endif

        @if($cte->estado == 'CANCELADO')
        <a class="btn btn-lg btn-question" style="background: #d84315; color: #fff" target="_blank" href="/cte/imprimirCancelamento/{{$cte->id}}" id="submit_user_button">Imprimir Cancelamento</a>
        @endif
      </div>
      
      @endcomponent
    </div>

  </div>

  

  <input type="hidden" id="token" value="{{csrf_token()}}" name="">
  <input type="hidden" id="id" value="{{$cte->id}}" name="">

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
        let cte_numero = $('#cte_numero').val();
        swal({
          text: 'Cancelamento de CT-e '+cte_numero+'.',
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
              url: path + '/cte/cancelar',
              dataType: 'json',
              success: function(e){
                console.log(e)

                swal("sucesso", e.retEvento.infEvento.xMotivo, "success")
                .then(() => {
                  window.open(path + '/cte/imprimirCancelamento/'+id)
                  location.reload()
                });

              }, error: function(e){
                console.log(e)
                console.log(e.responseJSON.data.infEvento.xMotivo)

                swal("Erro ao cancelar", e.responseJSON.data.infEvento.xMotivo, "error");

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

      $('#corrigir').click(() => {
        let cte_numero = $('#cte_numero').val();
        swal({
          text: 'Carta de correção para CT-e '+cte_numero+'.',
          content: "input",
          button: {
            text: "Corrigir!",
            closeModal: false,
            type: 'error'
          },
          confirmButtonColor: "#DD6B55",
        })
        .then(v => {
          if (!v) swal("Erro!", "Informe a correção!", "error");
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
              url: path + '/cte/corrigir',
              dataType: 'json',
              success: function(e){
                console.log(e)

                swal("sucesso", e.retEvento.infEvento.xMotivo, "success")
                .then(() => {
                  window.open(path + '/cte/imprimirCorrecao/'+id)
                  location.reload()
                });
                

              }, error: function(e){
                console.log(e)
                console.log(e.responseJSON.data.retEvento.infEvento.xMotivo)

                swal("Erro ao corrigir", e.responseJSON.data.retEvento.infEvento.xMotivo, "error");

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

      $('#consultar').click(() => {

        let token = $('#token').val();
        let id = $('#id').val();

        $.ajax
        ({
          type: 'POST',
          data: {
            id: id,
            _token: token
          },
          url: path + '/cte/consultar',
          dataType: 'json',
          success: function(e){
            console.log(e)

            swal("sucesso", "Resultado: " + e.protCTe.infProt.xMotivo + " - Chave: " + e.protCTe.infProt.chCTe, "success")
            .then(() => {
            });


          }, error: function(e){
            console.log(e)
            swal("Erro ao consultar", e.responseJSON, "error");

          }

        })
      })


    </script>
    @endsection
