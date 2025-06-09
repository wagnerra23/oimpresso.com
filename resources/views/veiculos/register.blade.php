@extends('layouts.app')

@section('title', 'Adicionar Veiculo')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
  <h1>Adicionar </h1>
</section>

<!-- Main content -->
<section class="content">
  {!! Form::open(['url' => action('VeiculoController@save'), 'method' => 'post', 'id' => 'veiculo_add_form' ]) !!}
  <div class="row">
    <div class="col-md-12">
      @component('components.widget')
      
      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('placa', 'Placa' . ':*') !!}
          {!! Form::text('placa', null, ['class' => 'form-control', 'required', 'placeholder' => 'Placa', 'data-mask="AAA-AAAA"' ]); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('uf', 'UF' . ':*') !!}
          {!! Form::select('uf', $ufs, '', ['class' => 'form-control select2', 'id' => 'contact_type', 'required']); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('modelo', 'Modelo' . ':*') !!}
          {!! Form::text('modelo', null, ['class' => 'form-control', 'required', 'placeholder' => 'Modelo' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('marca', 'Marca' . ':*') !!}
          {!! Form::text('marca', null, ['class' => 'form-control', 'required', 'placeholder' => 'Marca' ]); !!}
        </div>
      </div>
      
      <div class="clearfix"></div>


      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('cor', 'Cor' . ':*') !!}
          {!! Form::text('cor', null, ['class' => 'form-control', 'required', 'placeholder' => 'Cor' ]); !!}
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('tipo_carroceira', 'Tipo da carroceria' . ':*') !!}
          {!! Form::select('tipo_carroceira', $tiposCarroceria, '', ['class' => 'form-control select2', 'id' => 'contact_type', 'required']); !!}
        </div>
      </div>

      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('tipo_rodado', 'Tipo de rodado' . ':*') !!}
          {!! Form::select('tipo_rodado', $tiposRodado, '', ['class' => 'form-control select2', 'id' => 'contact_type', 'required']); !!}
        </div>
      </div>

      <div class="clearfix"></div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('tara', 'Tara' . ':*') !!}
          {!! Form::text('tara', null, ['class' => 'form-control', 'required', 'placeholder' => 'Tara', 'data-mask="0000000"' ]); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('capacidade', 'Capacidade' . ':*') !!}
          {!! Form::text('capacidade', null, ['class' => 'form-control', 'required', 'placeholder' => 'Capacidade', 'data-mask="0000000"' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('proprietario_nome', 'Nome Proprietário' . ':*') !!}
          {!! Form::text('proprietario_nome', null, ['class' => 'form-control', 'required', 'placeholder' => 'Nome Proprietário' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('proprietario_documento', 'Documento Proprietário' . ':*') !!}
          {!! Form::text('proprietario_documento', null, ['class' => 'form-control', 'required', 'placeholder' => 'Documento Proprietário' ]); !!}
        </div>
      </div>

      <div class="clearfix"></div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('proprietario_ie', 'I.E Proprietário' . ':') !!}
          {!! Form::text('proprietario_ie', null, ['class' => 'form-control', 'required', 'placeholder' => 'I.E Proprietário' ]); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('proprietario_uf', 'Proprietário UF' . ':*') !!}
          {!! Form::select('proprietario_uf', $ufs, '', ['class' => 'form-control select2', 'id' => 'contact_type', 'required']); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('proprietario_tp', 'Tipo de Proprietário' . ':*') !!}
          {!! Form::select('proprietario_tp', $tiposProprietario, '', ['class' => 'form-control select2', 'id' => 'contact_type', 'required']); !!}
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('rntrc', 'RNTRC' . ':') !!}
          {!! Form::text('rntrc', null, ['class' => 'form-control', 'required, minlength:8', 'placeholder' => 'RNTRC' ]); !!}
        </div>
      </div>

      
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
      <button type="submit" class="btn btn-primary pull-right" id="submit_user_button">@lang( 'messages.save' )</button>
    </div>
  </div>
  {!! Form::close() !!}
  @stop
  @section('javascript')
  <script type="text/javascript">
    $(document).ready(function(){
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
