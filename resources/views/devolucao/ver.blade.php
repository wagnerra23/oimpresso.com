@extends('layouts.app')

@section('title', 'Manter Devolução')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
  <h1>Manter Devolução</h1>
</section>

<!-- Main content -->
<section class="content">

  <div class="row">
    <div class="col-md-12">
      @component('components.widget')
      
      <input type="hidden" id="id" value="{{$devolucao->id}}" name="">
      <div class="col-md-12">
        <h4>Número NF-e Entrada: <strong>{{$devolucao->nNf}}</strong></h4>
        <h4>Chave NF-e Entrada: <strong>{{$devolucao->chave_nf_entrada}}</strong></h4>
        <h4>Estado: <strong>{{$devolucao->estado()}}</strong></h4>
        <h4>Fornecedor: <strong>{{$devolucao->contact->name}}</strong></h4>
        <h4>CPF/CNPJ: <strong>{{$devolucao->contact->cpf_cnpj}}</strong></h4>
        <h4>Cidade: <strong>{{$devolucao->contact->cidade->nome}} ({{$devolucao->contact->cidade->uf}})</strong></h4>
      </div>

      <input type="hidden" id="devolucao_id" value="{{$devolucao->id}}" name="">
      
      <div class="clearfix"></div>


      @if($devolucao->estado == 0 || $devolucao->estado == 2)

      <div class="col-md-12">
        <a class="btn btn-lg btn-primary" target="_blank" href="/devolucao/renderizar/{{$devolucao->id}}" id="submit_user_button">Renderizar</a>
        <a class="btn btn-lg btn-danger" target="_blank" href="/devolucao/gerarXml/{{$devolucao->id}}" id="submit_user_button">Gerar XML</a>
        <a class="btn btn-lg btn-success" id="send-sefaz">Transmitir para Sefaz</a>
      </div>
      @elseif($devolucao->estado == 1)

      <div class="col-md-12">
        <a class="btn btn-lg btn-primary" target="_blank" href="/devolucao/imprimir/{{$devolucao->id}}" id="submit_user_button">Imprimir</a>
        <a class="btn btn-lg btn-info" target="_blank" href="/devolucao/baixarXml/{{$devolucao->id}}" id="submit_user_button">Baixar XML</a>
        <a class="btn btn-lg btn-danger" id="cancelar">Cancelar NF-e</a>

      </div>
      @elseif($devolucao->estado == 3)

      <div class="col-md-12">
        <a class="btn btn-lg btn-primary" target="_blank" href="/devolucao/imprimirCancelamento/{{$devolucao->id}}" id="submit_user_button">Imprimir Cancelamento</a>
        <a class="btn btn-lg btn-info" target="_blank" href="/devolucao/baixarXmlCancelamento/{{$devolucao->id}}" id="submit_user_button">Baixar XML de Cancelamento</a>

      </div>

      @endif
      
      @endcomponent
    </div>

  </div>

  

  <input type="hidden" id="token" value="{{csrf_token()}}" name="">
  <input type="hidden" id="numero_nfe" value="{{$devolucao->numero_gerado}}" name="">


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
          text: 'Cancelamento de Devolução '+numero_nfe+'.',
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
              url: path + '/devolucao/cancelar',
              dataType: 'json',
              success: function(e){
                console.log(e)

                swal("sucesso", e.retEvento.infEvento.xMotivo, "success")
                .then(() => {
                  location.reload()
                });

              }, error: function(e){
                console.log(e)

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

      
      $('#send-sefaz').click(() => {
        let token = $('#token').val();
        let devolucao_id = $('#devolucao_id').val();
        
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
            _token: token,
            devolucao_id: devolucao_id
          },
          url: path + '/devolucao/transmitir',
          dataType: 'json',
          success: function(e){
            console.log(e)

            swal("sucesso", "Devolução emitida, recibo: " + e, "success")
            .then(() => {
              window.open(path + '/devolucao/imprimir/'+devolucao_id)
              location.reload()
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

      })


    </script>
    @endsection
