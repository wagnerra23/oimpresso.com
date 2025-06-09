@extends('layouts.app')

@section('title', __('Dashboard') . ' | ' . __('Dashboard'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>Dashboards</h1>
</section>

    {{-- <button id="carregar"> Click para carregar </button>
    <button id="clients"> Click buscar online </button> --}}

    <div class="table-responsive">
        <table class="table table-bordered table-striped" id="dashboard_table">
            <thead>
                <tr>
                    <th>id</th>
                    <th>text</th>
                    <th>Empresa</th>
                    <th>Data</th>
                    <th>Gerente</th>
                    <th>Dono</th>
                    <th>ParentID</th>
                    <th>Prioridade</th>
                    <th>Progresso</th>
                    <th>Start Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready( async function(){
        var dashboard_table = await $("#dashboard_table").DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                    method: "GET",
                    url: 'http://sistema.wr2.com.br:9000/socket/api/Employees/AllTasks',
                    beforeSend: function(e) {
                        e.setRequestHeader('socket_client', 'Test');
                    },
                    dataType: "json",
                    dataSrc: ''
                },
            select: true,
            columnDefs: [ {
                "orderable": false,
                "searchable": false
            } ],
            columns:[
                {"data":"id"},
                {"data":"text"},
                {"data":"company"},                    
                {"data":"dueDate"},
                {"data":"manager"},
                {"data":"owner"},
                {"data":"parentId"},
                {"data":"priority"},
                {"data":"progress"},
                {"data":"startDate"},
                {"data":"status"}
            ],
        });
            $("#carregar").on("click",function(){
            try {
                $.ajax({
                    method: "GET",
                    url: 'http://sistema.wr2.com.br:9000/socket/api/Employees/AllTasks',
                    beforeSend: function(e) {
                        e.setRequestHeader('socket_client', 'Test');
                    },
                    dataType: "json",
                    success: function(result){
                        if(result.success == true){
                            console.log(result);
                            
                        } else {
                            console.log(result)
                        }
                
                    }}
                );
            } catch {
                console.log(e);
            }
        });
        $("#clients").on("click",function(){
            try {
                $.ajax({
                    method: "GET",
                    url: 'http://sistema.wr2.com.br:9000/socket_clients',
                    dataType: "json",
                    success: function(result){
                        if(result.success == true){
                            console.log(result)
                            dashboard_table.ajax.reload();
                        } else {
                            console.log(result)
                        }
                
                    }}
                );
            } catch {
                console.log(e);
            }
        });
        $("#refresh").on("click",function(){
            dashboard_table.ajax.reload();
        });

    });
</script>
@endsection
