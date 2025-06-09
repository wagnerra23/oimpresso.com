@extends('layouts.app')
@section('title', 'Lista de CTe')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>
        <small>Gerencia CTe</small>
    </h1>
    <!-- <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
        <li class="active">Here</li>
    </ol> -->
</section>

<!-- Main content -->
<section class="content">
    
    @component('components.widget', ['class' => 'box-primary', 'title' => 'Todos os Documentos'])
    @can('user.create')
    @slot('tool')
    <div class="box-tools">
        <a class="btn btn-block btn-primary" 
        href="/cte/new" >
        <i class="fa fa-plus"></i> @lang( 'messages.add' )</a>
    </div>
    @endslot
    @endcan
    @can('user.view')
    <div class="table-responsive">
        <table class="table table-bordered table-striped" id="users_table">
            <thead>
                <tr>
                    <th>Valor de transporte</th>
                    <th>Valor a receber</th>
                    <th>Valor carga</th>
                    <th>Produto predominante</th>
                    <th>Data prevista de entrega</th>
                    <th>Estado</th>
                    <th>Ação</th>
                </tr>
            </thead>
        </table>
    </div>
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
    //Roles table
    $(document).ready( function(){
        var users_table = $('#users_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: '/cte',
            columnDefs: [ {
                "targets": [4],
                "orderable": false,
                "searchable": false
            } ],
            "columns":[
            {"data":"valor_transporte"},
            {"data":"valor_receber"},
            {"data":"valor_carga"},
            {"data":"produto_predominante"},
            {"data":"data_previsata_entrega"},
            {"data":"estado"},
            {"data":"action"}
            ]
        });

        
    });
    
    
</script>
@endsection
