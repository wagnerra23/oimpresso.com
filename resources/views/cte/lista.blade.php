@extends('layouts.app')
@section('title', 'Lista de CT-e')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>Ct-e
        <small>Lista</small>
    </h1>
    <!-- <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
        <li class="active">Here</li>
    </ol> -->
</section>

<!-- Main content -->
<section class="content">
    <p>
        <i class="fa fas fa-arrow-circle-down text-success"></i>
        Xml Aprovado
    </p>

    <p>
        <i class="fa fas fa-arrow-circle-down text-danger"></i>
        Xml Cancelado
    </p>
    @component('components.widget', ['class' => 'box-primary', 'title' => 'CT-e Lista'])

    @if(isset($msg) && sizeof($msg) > 0)
    @foreach($msg as $m)
    <h5 style="color: red">{{$m}}</h5>
    @endforeach
    @endif

    <form action="/cte/filtroXml" method="get">
        <div class="row">
            <div class="col-sm-2 col-lg-4">
                <div class="form-group">
                  <label for="product_custom_field2">Data inicial:</label>
                  <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                    <input required class="form-control start-date-picker" placeholder="Data inicial" value="{{{ isset($data_inicio) ? $data_inicio : ''}}}" data-mask="00/00/0000" name="data_inicio" type="text" id="">
                </div>

            </div>
        </div>
        <div class="col-sm-2 col-lg-4">
            <div class="form-group">
              <label for="product_custom_field2">Data final:</label>
              <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                <input required class="form-control start-date-picker" placeholder="Data final" data-mask="00/00/0000" name="data_final" type="text" value="{{{ isset($data_final) ? $data_final : ''}}}">
            </div>

        </div>
    </div>

    <div class="col-sm-2 col-lg-4">
        <div class="form-group"><br>
            <button style="margin-top: 5px;" class="btn btn-block btn-primary">Filtrar</button>
        </div>
    </div>

</div>
</form>
@can('user.view')
@if(sizeof($aprovadas) > 0)

<div class="table-responsive">
    <table class="table table-bordered table-striped" id="users_table">
        <thead>
            <tr>
                <th>Data</th>
                <th>Número</th>
                <th>Chave</th>
                <th>Estado</th>
                <th>Ação</th>
            </tr>
        </thead>
        <tbody>
            @foreach($aprovadas as $n)
            <tr>
                <td>{{ \Carbon\Carbon::parse($n->created_at)->format('d/m/Y H:i:s')}}</td>
                <td>{{$n->cte_numero}}</td>
                <td>{{$n->chave}}</td>
                <td>{{$n->estado}}</td>
                <td>
                    @if($n->estado == 'APROVADO')
                    <a title="Baixar XML Aprovado" target="_blank" href="/cte/baixarXml/{{$n->id}}">
                        <i class="fa fas fa-arrow-circle-down text-success"></i>
                    </a>

                    <a title="Imprimir" target="_blank" href="/cte/imprimir/{{$n->id}}">
                        <i class="fa fa-print" aria-hidden="true"></i>
                    </a>
                    @elseif($n->estado == 'CANCELADO')
                    <a title="Baixar XML Cancelado" target="_blank" href="/cte/baixarXmlCancelado/{{$n->id}}">
                        <i class="fa fas fa-arrow-circle-down text-danger"></i>
                    </a>
                    @endif


                </td>
            </tr>
            @endforeach
        </tbody>
    </table>


</div>

<div class="row">
    <div class="col-sm-2 col-lg-4">
        <a target="_blank" href="/cte/baixarZipXmlAprovado" style="margin-top: 5px;" class="btn btn-block btn-success">Download XML Aprovado</a>
    </div>

</div>

@endif

<div class="clearfix"></div>
<br>

@if(sizeof($canceladas) > 0)
<div class="table-responsive">
    <table class="table table-bordered table-striped" id="users_table">
        <thead>
            <tr>
                <th>Data</th>
                <th>Número</th>
                <th>Chave</th>
                <th>Estado</th>
                <th>Ação</th>
            </tr>
        </thead>
        <tbody>
            @foreach($canceladas as $n)
            <tr>
                <td>{{ \Carbon\Carbon::parse($n->created_at)->format('d/m/Y H:i:s')}}</td>
                <td>{{$n->cte_numero}}</td>
                <td>{{$n->chave}}</td>
                <td>{{$n->estado}}</td>
                <td>
                    @if($n->estado == 'APROVADO')
                    <a title="Baixar XML Aprovado" target="_blank" href="/cte/baixarXml/{{$n->id}}">
                        <i class="fa fas fa-arrow-circle-down text-success"></i>
                    </a>

                    <a title="Imprimir" target="_blank" href="/cte/imprimir/{{$n->id}}">
                        <i class="fa fa-print" aria-hidden="true"></i>
                    </a>
                    @elseif($n->estado == 'CANCELADO')
                    <a title="Baixar XML Cancelado" target="_blank" href="/cte/baixarXmlCancelado/{{$n->id}}">
                        <i class="fa fas fa-arrow-circle-down text-danger"></i>
                    </a>
                    @endif


                </td>
            </tr>
            @endforeach
        </tbody>
    </table>


</div>

<div class="row">

    <div class="col-sm-2 col-lg-4">
        <a target="_blank" href="/cte/baixarZipXmlReprovado" style="margin-top: 5px;" class="btn btn-block btn-danger">Download XML Cancelado</a>
    </div>
</div>

@endif

@if(sizeof($canceladas) == 0 && sizeof($aprovadas) == 0)
    <p>Filtro por data para encontrar os arquivos!</p>
@endif

@endcan
@endcomponent

<div class="modal fade user_modal" tabindex="-1" role="dialog" 
aria-labelledby="gridSystemModalLabel">
</div>

</section>
<!-- /.content -->
@stop
@section('javascript')
<script type="text/javascript">


</script>
@endsection
