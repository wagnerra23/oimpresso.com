@extends('layouts.app')

@section('title', 'Erro NF-e')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
  <h1>Erro(s) NF-e</h1>
</section>

<!-- Main content -->
<section class="content">

  <div class="row">
    <div class="col-md-12">
      @component('components.widget')
      
      <div class="col-md-12">
        @foreach($erros as $r)
        <h3 class="text-danger">*{{$r}}</h3>
        @endforeach
      </div>
      
      <div class="clearfix"></div>

      
      @endcomponent
    </div>


  </div>

 
  

  @stop
  @section('javascript')
  <script type="text/javascript">
    
  </script>
  @endsection
