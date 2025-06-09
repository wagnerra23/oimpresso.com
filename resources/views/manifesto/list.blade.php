@extends('layouts.app')
@section('title', 'Lista de Documentos')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>Manifesto
        <small>Gerencia documentos</small>
    </h1>
    <!-- <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
        <li class="active">Here</li>
    </ol> -->
</section>

<!-- Main content -->
<section class="content">
    @component('components.widget', ['class' => 'box-primary', 'title' => 'Todas os documentos'])
    @can('user.create')
    @slot('tool')
    <div class="box-tools">
        <a class="btn btn-block btn-success" 
        href="/manifesto/buscarNovosDocumentos" >
        <i class="fa fa-retweet"></i> Novos documentos</a>
    </div>
    @endslot
    @endcan
    @can('user.view')
    <div class="table-responsive">
        <table class="table table-bordered table-striped" id="users_table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Documento</th>
                    <th>Valor</th>
                    <th>Data emissão</th>
                    <th>Num. Protocolo</th>
                    <th>Chave</th>
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




<div class="modal fade" id="modal1" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="staticBackdrop" aria-hidden="true">
    <form method="get" action="/manifesto/manifestar">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

                    <h5 class="modal-title" id="exampleModalLabel">Manifestação de Destinatário</h5>

                </div>
                <div class="modal-body">
                    <input type="hidden" id="id" name="id" />
                    <div class="row">
                        <div class="form-group validated col-sm-6 col-lg-6">
                            <label class="col-form-label">Unidade de venda</label>
                            <select class="form-control" name="evento" id="tipo_evento">
                                <option value="1">Ciencia de operção</option>
                                <option value="2">Confirmação</option>
                                <option value="3">Desconhecimento</option>
                                <option value="4">Operação não realizada</option>
                            </select>
                        </div>

                        <div class="form-group validated col-sm-12 col-lg-12" id="div-just" style="display: none">
                            <label class="col-form-label">Justificativa</label>
                            <div class="">
                                <input id="justificativa" type="text" class="form-control" name="justificativa" value="">

                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-danger font-weight-bold" data-dismiss="modal">Fechar</button>
                    <button type="submit" id="salvarEdit" class="btn btn-success font-weight-bold spinner-white spinner-right">Manifestar</button>
                </div>
            </div>
        </div>
    </form>
</div>



<!-- /.content -->
@stop
@section('javascript')
<script type="text/javascript">

    function openModal(id){
        $('#modal1').modal('show')
        $('#id').val(id)
    }

    $('#tipo_evento').change(() => {
        let tipo = $('#tipo_evento').val();
        if(tipo == 3 || tipo == 4){
            $('#div-just').css('display', 'block')
        }else{
            $('#div-just').css('display', 'none')
        }
    })
    //Roles table
    $(document).ready( function(){
        var users_table = $('#users_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: '/manifesto',
            columnDefs: [ {
                "targets": [4],
                "orderable": false,
                "searchable": false
            } ],
            "columns":[
            {"data":"nome"},
            {"data":"documento"},
            {"data":"valor"},
            {"data":"data_emissao"},
            {"data":"num_prot"},
            {"data":"chave"},
            {"data":"tipo"},
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
