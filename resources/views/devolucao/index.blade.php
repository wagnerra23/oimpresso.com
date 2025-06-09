@extends('layouts.app')
@section('title', 'Importação de XML')

@section('content')

<style type="text/css">
input[type='file'] {
  display: none
}

/* Aparência que terá o seletor de arquivo */
label {
  background-color: #3498db;
  border-radius: 5px;
  color: #fff;
  cursor: pointer;
  margin: 10px;
  padding: 16px 40px
}
</style>

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>Devolução
        <small>Importar XML - Arquivo</small>
    </h1>
    <!-- <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
        <li class="active">Here</li>
    </ol> -->
</section>

<!-- Main content -->
<section class="content">
    @component('components.widget', ['class' => 'box-primary', 'title' => 'Importação de XML'])

    <form method="post" action="" enctype='multipart/form-data'>
        @csrf
        <div class="col-sm-4">
            <div class="form-group">
                <label for="business_logo">Selecione um arquivo XML &#187;</label>
                <input accept=".xml" name="file" type="file" id="business_logo" onchange="form.submit()">
            </div>
        </div>
    </form>


    @endcomponent

    <div class="modal fade user_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>

</section>
<!-- /.content -->
@stop

