@extends('layouts.app')

@section('title', 'Adicionar CTe')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
  <h1>Adicionar </h1>
</section>

<!-- Main content -->
<section class="content">
  {!! Form::open(['url' => action('CteController@save'), 'method' => 'post', 'id' => 'cte_add_form' ]) !!}
  <div class="row">
    <div class="col-md-12">
      @component('components.widget')

      <div class="col-md-2">
        <div class="form-group">
          <h4>Ultima CTe: <strong>{{$lastCte}}</strong></h4>

        </div>
      </div>

      <input type="hidden" id="clientesAux" value="{{json_encode($clientesAux)}}" name="">

      <div class="col-md-2">
        <div class="form-group">
          <button type="button" class="btn btn-info pull-right">
            Importar Xml
          </button>

        </div>
      </div>

      <div class="clearfix"></div>

      
      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('natureza_id', 'Natureza de operação' . ':*') !!}
          {!! Form::select('natureza_id', $naturezas, '', ['class' => 'form-control select2', 'id' => 'contact_type', 'required']); !!}
        </div>
      </div>

      <div class="clearfix"></div>

      <div class="col-md-6">
        <div class="form-group">
          {!! Form::label('remetente_id', 'Remetente' . ':*') !!}
          {!! Form::select('remetente_id', $clientes, '', ['class' => 'form-control select2', 'id' => 'remetente_id', 'required', 'placeholder' => 'Selecione o remetente']); !!}
        </div>

        <div class="box box-success" id="box-remetente" style="display: none">
          <div class="box-body">
            <h5>Nome: <strong id="remetente-nome"></strong></h5>
            <h5>CNPJ: <strong id="remetente-cnpj"></strong></h5>
            <h5>IE: <strong id="remetente-ie"></strong></h5>
            <h5>Endereço: <strong id="remetente-endereco"></strong></h5>
            <h5>Cidade: <strong id="remetente-cidade"></strong></h5>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="form-group">
          {!! Form::label('destinatario_id', 'Destinatário' . ':*') !!}
          {!! Form::select('destinatario_id', $clientes, '', ['class' => 'form-control select2', 'id' => 'destinatario_id', 'required', 'placeholder' => 'Selecione o destinatário']); !!}
        </div>
        <div class="box box-danger" id="box-destinatario" style="display: none">
          <div class="box-body">
            <h5>Nome: <strong id="destinatario-nome"></strong></h5>
            <h5>CNPJ: <strong id="destinatario-cnpj"></strong></h5>
            <h5>IE: <strong id="destinatario-ie"></strong></h5>
            <h5>Endereço: <strong id="destinatario-endereco"></strong></h5>
            <h5>Cidade: <strong id="destinatario-cidade"></strong></h5>

          </div>
        </div>

      </div>

      <div class="clearfix"></div>

      <div class="row">
        <div class="col-md-12">
          <div class="nav-tabs-custom">
            <ul class="nav nav-tabs nav-justified">
              <li class="active">
                <a href="#ledger_tab" data-toggle="tab" aria-expanded="true">NF-e</a>
              </li>
              <li class="''">
                <a href="#documents_and_notes_tab" data-toggle="tab" aria-expanded="false">Outros</a>
              </li>

            </ul>

            <div class="tab-content">
              <div class="tab-pane active" id="ledger_tab">
                <div class="row">
                  <div class="col-md-12">
                    <div class="col-md-10">
                      <div class="form-group">
                        <label for="ledger_date_range">Chave NFe:</label>
                        <input placeholder="Chave NFe" class="form-control type-ref" data-mask="00000000000000000000000000000000000000000000" name="chave_nfe" type="text" id="chave_nfe">
                      </div>
                    </div>
                    
                  </div>
                  <div id="contact_ledger_div"></div>
                </div>                    
              </div>
              <div class="tab-pane ''" id="documents_and_notes_tab">
                <!-- model id like project_id, user_id -->
                <!-- model name like App\User -->

                <?php 
                $tipos = [
                  '00' => 'Declaração',
                  '10' => 'Dutoviário',
                  '59' => 'CF-e SAT',
                  '65' => 'NFC-e',
                  '99' => 'Outros'
                ];
                ?>
                <div class="row">
                  <div class="col-md-12">

                    <div class="col-md-3">
                      <div class="form-group">
                        {!! Form::label('tpDoc', 'Tipo documento' . ':*') !!}
                        {!! Form::select('tpDoc', $tipos, '', ['class' => 'form-control', 'id' => 'tpDoc', 'required']); !!}
                      </div>
                    </div>

                    <div class="col-md-4">
                      <div class="form-group">
                        {!! Form::label('descOutros', 'Descrição do Doc.' . ':*') !!}
                        {!! Form::text('descOutros', null, ['class' => 'form-control type-ref', 'placeholder' => 'Descrição do Doc.' ]); !!}
                      </div>
                    </div>

                    <div class="col-md-2">
                      <div class="form-group">
                        {!! Form::label('nDoc', 'Numero do Doc.' . ':*') !!}
                        {!! Form::text('nDoc', null, ['class' => 'form-control type-ref', 'placeholder' => 'Numero do Doc.' ]); !!}
                      </div>
                    </div>

                    <div class="col-md-2">
                      <div class="form-group">
                        {!! Form::label('vDocFisc', 'Valor do Documento' . ':*') !!}
                        {!! Form::text('vDocFisc', null, ['class' => 'form-control type-ref', 'placeholder' => 'Valor do Documento' ]); !!}
                      </div>
                    </div>

                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="clearfix"></div>
      <div class="col-md-12">
        <h4>INFORMAÇÕES DA CARGA</h4>
      </div>

      <div class="col-md-4">

        <div class="form-group">
          {!! Form::label('veiculo_id', 'Veiculo' . ':*') !!}
          {!! Form::select('veiculo_id', $veiculos, '', ['class' => 'form-control select2', 'id' => 'veiculo_id', 'required', 'placeholder' => 'Veiculo']); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('prod_predominante', 'Produto predominante' . ':*') !!}
          {!! Form::text('prod_predominante', null, ['class' => 'form-control type-ref', 'required', 'placeholder' => 'Produto predominante' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('tomador', 'Tomador' . ':*') !!}
          {!! Form::select('tomador', $tiposTomador, '', ['class' => 'form-control select2', 'id' => 'tomador', 'required']); !!}
        </div>
      </div>

      <div class="clearfix"></div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('valor_carga', 'Valor da Carga' . ':*') !!}
          {!! Form::text('valor_carga', null, ['class' => 'form-control', 'required type-ref', 'placeholder' => 'Valor da Carga', 'data-mask="000000.00", data-mask-reverse="true"' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('modal_transp', 'Modelo de Transporte' . ':*') !!}
          {!! Form::select('modal_transp', $modals, '', ['class' => 'form-control select2', 'id' => 'modal_transp', 'required']); !!}
        </div>
      </div>

      <div class="col-md-12">
        <h5 class="text-primary">INFORMAÇÕES DE QUANTIDADE</h5>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('unidade_medida', 'Unidade medida' . ':*') !!}
          {!! Form::select('unidade_medida', $unidadesMedida, '', ['class' => 'form-control select2', 'id' => 'unidade_medida', 'required']); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('tipo_medida', 'Tipo de medida' . ':*') !!}
          {!! Form::select('tipo_medida', $tiposMedida, '', ['class' => 'form-control select2', 'id' => 'tipo_medida', 'required']); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('quantidade_carga', 'Quantidade' . ':*') !!}
          {!! Form::text('quantidade_carga', null, ['class' => 'form-control type-ref', 'placeholder' => 'Quantidade',  'data-mask="0000.000", data-mask-reverse="true"' ]); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          <br>
          <a id="addMedida" class="btn btn-primary" style="margin-top: 3px;">
            <i class="fa fa-plus"></i>
            Adicionar
          </a>
        </div>
      </div>
      <div class="clearfix"></div>

      <div class="col-md-12">
        <div class="table-responsive">
          <table class="table table-bordered table-striped" id="prod">
            <thead>
              <tr>
                <th>Item</th>
                <th>Código Unidade</th>
                <th>Tipo de Medida</th>
                <th>Quantidade</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              <tr>

              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="clearfix"></div>

      <div class="col-md-12">
        <h5 class="text-primary">COMPONENTES DA CARGA</h5>
        <p class="text-red">*A soma dos valores dos componentes deve ser igual ao valor a receber</p>
      </div>

      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('nome_componente', 'Nome do componente' . ':*') !!}
          {!! Form::text('nome_componente', null, ['class' => 'form-control', 'required', 'placeholder' => 'Nome do componente' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('valor_componente', 'Valor do componente' . ':*') !!}
          {!! Form::text('valor_componente', null, ['class' => 'form-control', 'required', 'placeholder' => 'Valor do componente', 'data-mask="000000.00", data-mask-reverse="true"' ]); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          <br>
          <a id="addComponente" class="btn btn-primary" style="margin-top: 3px;">
            <i class="fa fa-plus"></i>
            Adicionar
          </a>
        </div>
      </div>
      <div class="clearfix"></div>


      <div class="col-md-12">
        <div class="table-responsive">
          <table class="table table-bordered table-striped" id="componentes">
            <thead>
              <tr>
                <th>Item</th>
                <th>Componente</th>
                <th>Valor</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              <tr>

              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="col-md-12">
        <h4>INFORMAÇÕES DA ENTREGA</h4>
      </div>

      <div class="col-md-12">

        <h6>Endereço do Tomador</h6>
        <p>
          <input type="checkbox" id="endereco-destinatario" />
          <label for="endereco-destinatario">Endereço do Destinatário</label>
        </p>

        <p>
          <input type="checkbox" id="endereco-remetente" />
          <label for="endereco-remetente">Endereço do Rementente</label>
        </p>
      </div>

      <div class="col-md-12">
        <h5>Endereço do Tomador</h5>
      </div>
      <div class="col-md-5">
        <div class="form-group">
          {!! Form::label('rua_tomador', 'Rua' . ':*') !!}
          {!! Form::text('rua_tomador', null, ['class' => 'form-control', 'required', 'placeholder' => 'Rua' ]); !!}
        </div>
      </div>
      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('numero_tomador', 'Número' . ':*') !!}
          {!! Form::text('numero_tomador', null, ['class' => 'form-control', 'required', 'placeholder' => 'Número' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('cep_tomador', 'CEP' . ':*') !!}
          {!! Form::text('cep_tomador', null, ['class' => 'form-control', 'required', 'placeholder' => 'CEP' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('bairro_tomador', 'Bairro' . ':*') !!}
          {!! Form::text('bairro_tomador', null, ['class' => 'form-control', 'required', 'placeholder' => 'Bairro' ]); !!}
        </div>
      </div>

      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('cidade_tomador', 'Cidade' . ':*') !!}
          {!! Form::select('cidade_tomador', $cidades, '', ['class' => 'form-control select2', 'id' => 'cidade_tomador', 'required']); !!}
        </div>
      </div>

      <div class="clearfix"></div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('data_prevista_entrega', 'Data previsa de entrega' . ':*') !!}
          <div class="input-group">
            <span class="input-group-addon">
              <i class="fa fa-calendar"></i>
            </span>
            {!! Form::text('data_prevista_entrega', '', ['class' => 'form-control type-ref', 'required', 'data-mask="00/00/0000"']); !!}
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('valor_transporte', 'Valor da Prestação de Serviço' . ':*') !!}
          {!! Form::text('valor_transporte', null, ['class' => 'form-control type-ref', 'required', 'placeholder' => 'Valor da Prestação de Serviço' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('valor_receber', 'Valor a Receber' . ':*') !!}
          {!! Form::text('valor_receber', null, ['class' => 'form-control', 'required', 'placeholder' => 'Valor a Receber' ]); !!}
        </div>
      </div>

      <div class="clearfix"></div>

      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('cidade_envio', 'Municipio envio' . ':*') !!}
          {!! Form::select('cidade_envio', $cidades, '', ['class' => 'form-control select2', 'id' => 'cidade_envio', 'required']); !!}
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('cidade_inicio', 'Municipio Inicio' . ':*') !!}
          {!! Form::select('cidade_inicio', $cidades, '', ['class' => 'form-control select2', 'id' => 'cidade_inicio', 'required']); !!}
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('cidade_fim', 'Municipio Fim' . ':*') !!}
          {!! Form::select('cidade_fim', $cidades, '', ['class' => 'form-control select2', 'id' => 'cidade_fim', 'required']); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('retira', 'Retira' . ':*') !!}
          {!! Form::select('retira', [1 => 'sim', 0 => 'não'], '', ['class' => 'form-control select2', 'id' => 'retira', 'required']); !!}
        </div>
      </div>

      <div class="col-md-5">
        <div class="form-group">
          {!! Form::label('detalhes_retira', 'Detalhes(opcional)' . ':*') !!}
          {!! Form::text('detalhes_retira', null, ['class' => 'form-control type-ref', 'placeholder' => 'Detalhes(opcional)' ]); !!}
        </div>
      </div>

      <div class="col-md-7">
        <div class="form-group">
          {!! Form::label('obs', 'Informação Adicional' . ':*') !!}
          {!! Form::text('obs', null, ['class' => 'form-control type-ref', 'placeholder' => 'Informação Adicional' ]); !!}
        </div>
      </div>
      <input type="hidden" name="componentes" id="comps">
      <input type="hidden" name="medidas" id="meds">

      @endcomponent
    </div>


  </div>

  @if(!empty($form_partials))
  @foreach($form_partials as $partial)
  {!! $partial !!}
  @endforeach
  @endif
  <div class="row">
    <div class="col-md-12">
      <button id="finalizar" type="submit" class="btn btn-primary pull-right disabled" id="submit_user_button">@lang( 'messages.save' ) CTe</button>
    </div>
  </div>
  {!! Form::close() !!}
  @stop
  @section('javascript')
  <script type="text/javascript">
    var CLIENTES = []
    var MEDIDAS = []
    var COMPONENTES = []
    var REMETENTE = null;
    var DESTINATARIO = null
    $('#remetente_id').change(() => {
      let id =  $('#remetente_id').val()
      CLIENTES.map((c) => {
        if(c.id == id){
          REMETENTE = c
          $('#remetente-nome').html(c.name)
          $('#remetente-cnpj').html(c.cpf_cnpj)
          $('#remetente-ie').html(c.ie_rg)
          $('#remetente-endereco').html(c.rua + ', ' + c.numero)
          $('#remetente-cidade').html(c.cidade.nome + ' (' + c.cidade.uf + ')')

          $('#box-remetente').css('display', 'block')
        }


      })
    })
    $('#destinatario_id').change(() => {
      let id =  $('#destinatario_id').val()
      CLIENTES.map((c) => {
        if(c.id == id){
          DESTINATARIO = c
          $('#destinatario-nome').html(c.name)
          $('#destinatario-cnpj').html(c.cpf_cnpj)
          $('#destinatario-ie').html(c.ie_rg)
          $('#destinatario-endereco').html(c.rua + ', ' + c.numero)
          $('#destinatario-cidade').html(c.cidade.nome + ' (' + c.cidade.uf + ')')

          $('#box-destinatario').css('display', 'block')
        }


      })
    })

    // MEDIDAS CTE >>>>>>>

    $('#addMedida').click(() => {
      let unidade_medida = $('#unidade_medida').val();
      let tipo_medida = $('#tipo_medida').val();
      let quantidade = $('#quantidade_carga').val();
      MEDIDAS.push({id: (MEDIDAS.length+1), unidade_medida: unidade_medida,
        tipo_medida: tipo_medida, quantidade: quantidade});
      console.log(MEDIDAS)

      let t = montaTabela();
      $('#prod tbody').html(t)
    })

    function montaTabela(){
      let t = ""; 
      MEDIDAS.map((v) => {
        t += "<tr>";
        t += "<td>"+v.id+"</td>";
        t += "<td>"+unidadeMedidaExibe(v.unidade_medida)+"</td>";
        t += "<td>"+v.tipo_medida+"</td>";
        t += "<td>"+v.quantidade+"</td>";
        t += "<td><a href='#!' class='btn btn-danger btn-sm' onclick='deleteItem("+v.id+")'>"
        t += "<i class='fa fa-trash'></i></a></td>";
        t+= "</tr>";
      });
      $('#meds').val(JSON.stringify(MEDIDAS))

      habilitaBtnSalarCTe()
      return t;
    }

    function deleteItem(id){
      let temp = [];
      MEDIDAS.map((v) => {
        if(v.id != id){
          temp.push(v)
        }
      });
      MEDIDAS = temp;
      refatoreItens()
      let t = montaTabela(); 
      $('#prod tbody').html(t)

    }

    function refatoreItens(){
      let cont = 1;
      let temp = [];
      MEDIDAS.map((v) => {
        v.id = cont;
        temp.push(v)
        cont++;
      })
      MEDIDAS = temp;
    }

    function unidadeMedidaExibe(cod){
      if(cod == '00'){ 
        return 'M3'
      }else if(cod == '01'){ 
        return 'KG' 
      }else if(cod == '02'){
        return 'TON'
      }else if(cod == '03') {
        return 'UNIDADE'
      }else if(cod == '04') {
        return 'M2'
      }
    }

    // MEDIDAS CTE FIM >>>>>>>

    // COMPONENTES CTE >>>>>>>

    $('#addComponente').click(() => {
      let nome_componente = $('#nome_componente').val();
      let valor_componente = $('#valor_componente').val();
      COMPONENTES.push({id: (COMPONENTES.length+1), valor: valor_componente,
        nome: nome_componente});
      let t = montaTabelaComponentes();
      $('#componentes tbody').html(t)
      console.log(JSON.stringify(COMPONENTES))
      
      habilitaBtnSalarCTe();
    });

    function montaTabelaComponentes(){
      let t = ""; 
      SOMACOMPONENTES = 0;
      COMPONENTES.map((v) => {
        t += "<tr>";
        t += "<td>"+v.id+"</td>";
        t += "<td>"+v.nome+"</td>";
        t += "<td>"+v.valor+"</td>";
        t += "<td><a href='#!' class='btn btn-danger btn-sm'  onclick='deleteComponente("+v.id+")'>"
        t += "<i class='fa fa-trash'></i></a></td>";
        t+= "</tr>";

        SOMACOMPONENTES += parseFloat(v.valor.replace(',', '.'));
      });
      $('#comps').val(JSON.stringify(COMPONENTES))
      $('#valor_receber').val(SOMACOMPONENTES.toFixed(2));
      $('#valor_transporte').val(SOMACOMPONENTES.toFixed(2));
      habilitaBtnSalarCTe()
      return t;
    }

    function deleteComponente(id){
      let temp = [];
      COMPONENTES.map((v) => {
        if(v.id != id){
          temp.push(v)
        }
      });
      COMPONENTES = temp;
      refatoreComponentes()
      let t = montaTabelaComponentes(); 
      $('#componentes tbody').html(t)

    }

    function refatoreComponentes(){
      let cont = 1;
      let temp = [];
      COMPONENTES.map((v) => {
        v.id = cont;
        temp.push(v)
        cont++;
      })
      COMPONENTES = temp;
    }

    // COMPONENTES CTE  FIM >>>>>>>

    function habilitaBtnSalarCTe(){
      console.log("testando")
      let tipoDocumento = false;
      let inputs = false;
      let chave_nfe = $('#chave_nfe').val();

      if(chave_nfe.length != 44 && $('#descOutros').val() != "" && $('#nDoc').val() != "" && $('#vDocFisc').val() != ""){
        tipoDocumento = true;
      }else if(chave_nfe.length == 44 && $('#descOutros').val() == "" && $('#nDoc').val() == "" && 
        $('#vDocFisc').val() == ""){
        tipoDocumento = true
      }

      if($('#prod_predominante').val() != "" && $('#valor_carga').val() != "" && $('#valor_transporte').val() != "" && $('#valor_receber').val() != ""){
        inputs = true;
      }

      console.log(tipoDocumento)

      if(MEDIDAS.length > 0 && COMPONENTES.length > 0 && DESTINATARIO != null && REMETENTE != null &&tipoDocumento && inputs){
        $('#finalizar').removeClass('disabled')

      }
    }

    $('.type-ref').keyup(() => {
      habilitaBtnSalarCTe()
    })


    $('#endereco-destinatario').click(() => {
      let v = $('#endereco-destinatario').is(':checked');
      $('#endereco-remetente').prop('checked', false);
      if(v){
        if(DESTINATARIO){
          $('#rua_tomador').val(DESTINATARIO.rua)
          $('#numero_tomador').val(DESTINATARIO.numero)
          $('#bairro_tomador').val(DESTINATARIO.bairro)
          $('#cep_tomador').val(DESTINATARIO.cep)
          $('#cidade_tomador').val(DESTINATARIO.cidade.id).change()

          habilitaCampos();

        }else{

          swal("Erro!", "Destinatário não selecionado!", "warning")

          $('#endereco-destinatario').prop('checked', false); 

        }
      }else{
        desabilitaCampos();
      }
    })

    $('#endereco-remetente').click(() => {
      let v = $('#endereco-remetente').is(':checked');
      $('#endereco-destinatario').prop('checked', false);
      if(v){
        if(REMETENTE){
          $('#rua_tomador').val(REMETENTE.rua)
          $('#numero_tomador').val(REMETENTE.numero)
          $('#bairro_tomador').val(REMETENTE.bairro)
          $('#cep_tomador').val(REMETENTE.cep)
          $('#cidade_tomador').val(REMETENTE.cidade.id).change()

          habilitaCampos();

        }else{

          swal("Erro!", "Remetente não selecionado!", "warning")

          $('#endereco-remetente').prop('checked', false); 
        }
      }else{
        desabilitaCampos();
      }
    })

    function habilitaCampos(){
      // $('#rua_tomador').prop('disabled', true)
      // $('#numero_tomador').prop('disabled', true)
      // $('#bairro_tomador').prop('disabled', true)
      // $('#cep_tomador').prop('disabled', true)
      // $('#autocomplete-cidade-tomador').prop('disabled', true)
    }

    function desabilitaCampos(){
      // $('#rua_tomador').removeAttr('disabled')
      // $('#numero_tomador').removeAttr('disabled')
      // $('#bairro_tomador').removeAttr('disabled')
      // $('#cep_tomador').removeAttr('disabled')
      // $('#autocomplete-cidade-tomador').removeAttr('disabled')
    }



    $(document).ready(function(){

      CLIENTES = JSON.parse($('#clientesAux').val())

      $('#selected_contacts').on('ifChecked', function(event){
        $('div.selected_contacts_div').removeClass('hide');
      });
      $('#selected_contacts').on('ifUnchecked', function(event){
        $('div.selected_contacts_div').addClass('hide');
      });

      $('#allow_login').on('ifChecked', function(event){
        $('div.user_auth_fields').removeClass('hide');
      });
      $('#allow_login').on('ifUnchecked', function(event){
        $('div.user_auth_fields').addClass('hide');
      });
    });

    $('form#veiculo_add_form').validate({
      rules: {
        placa: {
          required: true,
          minlength: 8
        },
        rntrc: {
          required: true,
          minlength: 8
        },
      },
      messages: {
        placa: {
          required: 'Campo obrigatório',
          minlength: 'Valor inválido'

        },
        modelo: {
          required: 'Campo obrigatório' ,
        },
        modelo: {
          required: 'Campo obrigatório' ,
        },
        marca: {
          required: 'Campo obrigatório' ,
        },
        cor: {
          required: 'Campo obrigatório' ,
        },
        tara: {
          required: 'Campo obrigatório' ,
        },
        uf: {
          required: 'Campo obrigatório' ,
        },
        capacidade: {
          required: 'Campo obrigatório' ,
        },
        proprietario_nome: {
          required: 'Campo obrigatório' ,
        },
        proprietario_documento: {
          required: 'Campo obrigatório' ,
        },
        proprietario_ie: {
          required: 'Campo obrigatório' ,
        },
        rntrc: {
          required: 'Campo obrigatório',
          minlength: 'Informe no minimo 8 caracteres'

        },
      }
    });
    $('#username').change( function(){
      if($('#show_username').length > 0){
        if($(this).val().trim() != ''){
          $('#show_username').html("{{__('lang_v1.your_username_will_be')}}: <b>" + $(this).val() + "{{$username_ext}}</b>");
        } else {
          $('#show_username').html('');
        }
      }
    });
  </script>
  @endsection
