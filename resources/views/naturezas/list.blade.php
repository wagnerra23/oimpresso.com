@extends('layouts.app')
@section('title', 'Lista de Naturezas')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>Naturezas
        <small>Gerencia naturezas</small>
    </h1>
    <!-- <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
        <li class="active">Here</li>
    </ol> -->
</section>

<!-- Main content -->
<section class="content">
    @component('components.widget', ['class' => 'box-primary', 'title' => 'Todas as Naturezas'])
        @can('user.create')
            @slot('tool')
                <div class="box-tools">
                    <a class="btn btn-block btn-primary" 
                    href="/naturezas/new" >
                    <i class="fa fa-plus"></i> @lang( 'messages.add' )</a>
                 </div>
            @endslot
        @endcan
        @can('user.view')
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="users_table">
                    <thead>
                        <tr>
                            <th>Natureza</th>
                            <th>CFOP saida estadual</th>
                            <th>CFOP entrada estadual</th>
                            <th>CFOP saida interestadual</th>
                            <th>CFOP entrada interestadual</th>
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
                    ajax: '/naturezas',
                    columnDefs: [ {
                        "targets": [4],
                        "orderable": false,
                        "searchable": false
                    } ],
                    "columns":[
                        {"data":"natureza"},
                        {"data":"cfop_entrada_estadual"},
                        {"data":"cfop_entrada_inter_estadual"},
                        {"data":"cfop_saida_estadual"},
                        {"data":"cfop_saida_inter_estadual"},
                        {"data":"action"}
                    ]
                });
        $(document).on('click', 'button.delete_user_button', function(){
            swal({
              title: LANG.sure,
              text: LANG.confirm_delete_user,
              icon: "warning",
              buttons: true,
              dangerMode: true,
            }).then((willDelete) => {
                if (willDelete) {
                    var href = $(this).data('href');
                    var data = $(this).serialize();
                    $.ajax({
                        method: "DELETE",
                        url: href,
                        dataType: "json",
                        data: data,
                        success: function(result){
                            if(result.success == true){
                                toastr.success(result.msg);
                                users_table.ajax.reload();
                            } else {
                                toastr.error(result.msg);
                            }
                        }
                    });
                }
             });
        });
        
    });
    
    
</script>
@endsection
