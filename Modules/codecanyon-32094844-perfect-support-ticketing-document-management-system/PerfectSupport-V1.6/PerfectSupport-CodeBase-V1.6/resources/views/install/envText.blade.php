@extends('install.layout', ['no_header' => 1])

@section('content')
<div class="container">
    <h2 class="text-center">{{ config('app.name') }}</h2>

    <div class="row justify-content-md-center">
        <div class="col-md-10">
            <hr/>
          
            @include('install.partials.nav', ['active' => 'app_details'])

            <div class="card">
                <div class="card-header">
                    <h3 class="text-success install_instuction">
                        Hey, I need your help. 
                    </h3>
                </div>

                <div class="box-body">

                    @if(session('error'))
                        <div class="alert alert-danger">
                        {{ session('error') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                <form class="form" method="post" 
                    action="{{route('install.installAlternate')}}" 
                    id="env_details_form">
                    {{ csrf_field() }}

                    <p class="install_instuction">
                        Please create a file with name <code>.env</code> at <strong>{{$envPath}}</strong> with <code>read & write permission</code> and paste the below content. <br/> Press install after it.
                    </p>
                    <hr/>

                    <div class="col-md-12">
                        <div class="form-group">
                            <textarea rows="25" cols="50">{{$envContent}}</textarea>
                        </div>
                    </div>
                  
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary float-right" id="install_button">Install</button>
                    </div>

                    <div class="col-md-12 text-center text-danger install_msg hide">
                        <h3>Installation in progress, Please do not refresh, go back or close the browser.</strong></h3>
                    </div>
                </form>
            </div>
          <!-- /.box-body -->
          </div>
        </div>

    </div>
</div>

<style type="text/css">
  .hide{
    display: none;
  }
</style>
@endsection

@section('javascript')
  <script type="text/javascript">
    $(document).ready(function(){

      $('form#env_details_form').submit(function(){
        $('button#install_button').attr('disabled', true).text('Installing...');
        $(".install_instuction").addClass('hide');
        $('div.install_msg').removeClass('hide');
        $('textarea').addClass('hide');
        $('.back_button').hide();
      });

    })
  </script>
@endsection