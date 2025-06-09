@extends('layouts.app')

@section('title', 'Adicionar Natureza de Operação')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
  <h1>Adicionar Natureza de Operação</h1>
</section>

<!-- Main content -->
<section class="content">
  {!! Form::open(['url' => action('NaturezaController@save'), 'method' => 'post', 'id' => 'natureza_add_form' ]) !!}
  <div class="row">
    <div class="col-md-12">
      @component('components.widget')
      
      <div class="col-md-5">
        <div class="form-group">
          {!! Form::label('natureza', 'Natureza' . ':*') !!}
          {!! Form::text('natureza', null, ['class' => 'form-control', 'required', 'placeholder' => 'Natureza' ]); !!}
        </div>
      </div>
      
      <div class="clearfix"></div>


      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('cfop_entrada_estadual', 'CFOP entrada estadual' . '*:') !!}
          {!! Form::text('cfop_entrada_estadual', null, ['class' => 'form-control', 'required', 'placeholder' => 'CFOP entrada estadual' ]); !!}
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('cfop_saida_estadual', 'CFOP saida estadual' . '*:') !!}
          {!! Form::text('cfop_saida_estadual', null, ['class' => 'form-control', 'required', 'placeholder' => 'CFOP saida estadual' ]); !!}
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('cfop_entrada_inter_estadual', 'CFOP entrada outro estado' . '*:') !!}
          {!! Form::text('cfop_entrada_inter_estadual', null, ['class' => 'form-control', 'required', 'placeholder' => 'CFOP entrada outro estado' ]); !!}
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('cfop_saida_inter_estadual', 'CFOP saida outro estado' . '*:') !!}
          {!! Form::text('cfop_saida_inter_estadual', null, ['class' => 'form-control', 'required', 'placeholder' => 'CFOP saida outro estado' ]); !!}
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

    $('form#user_add_form').validate({
      rules: {
        first_name: {
          required: true,
        },
        email: {
          email: true,
          remote: {
            url: "/business/register/check-email",
            type: "post",
            data: {
              email: function() {
                return $( "#email" ).val();
              }
            }
          }
        },
        password: {
          required: true,
          minlength: 5
        },
        confirm_password: {
          equalTo: "#password"
        },
        username: {
          minlength: 5,
          remote: {
            url: "/business/register/check-username",
            type: "post",
            data: {
              username: function() {
                return $( "#username" ).val();
              },
              @if(!empty($username_ext))
              username_ext: "{{$username_ext}}"
              @endif
            }
          }
        }
      },
      messages: {
        password: {
          minlength: 'Password should be minimum 5 characters',
        },
        confirm_password: {
          equalTo: 'Should be same as password'
        },
        username: {
          remote: 'Invalid username or User already exist'
        },
        email: {
          remote: '{{ __("validation.unique", ["attribute" => __("business.email")]) }}'
        }
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
