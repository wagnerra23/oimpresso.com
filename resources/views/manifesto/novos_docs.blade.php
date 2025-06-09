@extends('layouts.app')

<style type="text/css">
.loader {
  border: 16px solid #f3f3f3; /* Light grey */
  border-top: 16px solid #3498db; /* Blue */
  border-radius: 50%;
  width: 120px;
  height: 120px;
  animation: spin 2s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
@section('title', 'Busca de documentos')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>Manifesto

    </h1>
    <!-- <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
        <li class="active">Here</li>
    </ol> -->
</section>

<!-- Main content -->
<section class="content">
    @component('components.widget', ['class' => 'box-danger', 'title' => 'Busca de documentos'])

    <p id="aguarde" class="text-info">Consultado novos documentos, aguarde ...</p>
    <p id="sem-resultado" style="display: none" class="center-align text-danger">Nenhum novo resultado...</p>
    <div class="loader" id="loader"></div> 

    <div class="table-responsive" id="tbl" style="display: none">

        <p class="text-danger">*Documentos inseridos!!</p>
        <a href="/manifesto" class="btn btn-info">Voltar</a>
        <br>
        <table class="table table-bordered table-striped" id="users_table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Documento</th>
                    <th>Valor</th>
                    <th>Chave</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

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
    var path = window.location.protocol + '//' + window.location.host

    $(document).ready( function(){
        filtrar()
    });

    function filtrar(){
        $.get(path + '/manifesto/getDocumentosNovos')
        .done(value => {
            console.log(value)
            $('#aguarde').css('display', 'none')
            $('#loader').css('display', 'none')

            if(value.length > 0){
                montaTabela(value, (html) => {

                    console.log(html)
                    $('#users_table tbody').html(html)
                    $('#tbl').css('display', 'block')
                })
                swal("Sucesso", "Foram encontrados " + value.length + " novos registros!", "success")
            }else{
                swal("Sucesso", "A requisição obteve sucesso, porém sem novos registros!!", "success")
                $('#sem-resultado').css('display', 'block')

            }

        })
        .fail(err => {
            console.log(err)
            $('#loader').css('display', 'none')
            $('#aguarde').css('display', 'none')
            swal("Erro", "Erro ao realizar consulta", "warning")
        })
    }

    function montaTabela(array, call){
        let html = '';
        array.map(v => {
            console.log(v)
            html += '<tr>';
            html += '<td>' + v.nome[0] +'</td>';
            html += '<td>' + v.documento[0] +'</td>';
            html += '<td>' + v.valor[0] +'</td>';
            html += '<td>' + v.chave[0] +'</td>';
            html += '</tr>';
        })

        call(html)
    }

    
</script>
@endsection
