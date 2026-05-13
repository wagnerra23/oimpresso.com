{{-- Wrapper standalone para contacts/create — usado quando acessado diretamente
     (não via AJAX/modal). Inclui layout completo com CSS/JS do AdminLTE.
     Rota: GET /contacts/create-page?type=customer&prefill_name=XXX
     Criado para: link "Cadastrar novo cliente" da tela Sells/Create.tsx (v2 Inertia)
     que precisa de página com CSS — contact/create.blade.php é fragmento de modal. --}}
@extends('layouts.app')

@section('title', __('contact.add_contact'))

@section('content')
<section class="content-header">
    <h1>@lang('contact.add_contact')</h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-10 col-md-offset-1">
            {{-- Reutiliza o fragmento de modal do contact/create --}}
            <div class="box box-primary">
                <div class="box-body no-padding">
                    @php
                        // Variáveis esperadas pelo fragmento contact/create
                        $quick_add = false;
                    @endphp
                    @include('contact.create')
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
    // Remove comportamento de modal (close button, backdrop) já que estamos em página standalone
    $(document).ready(function() {
        // Botão close do modal redireciona de volta à lista de clientes
        $('.modal-header .close').on('click', function() {
            window.location.href = '/contacts?type=customer';
        });

        // Remove classes de modal para layout correto em página full
        $('.modal-dialog').removeClass('modal-dialog').addClass('standalone-form-wrap');
        $('.modal-content').removeClass('modal-content');
        $('.modal-header').addClass('box-header with-border');
        $('.modal-body').addClass('box-body');
        $('.modal-footer').addClass('box-footer');

        // Intercepta o submit do form para redirecionar após salvar
        $('#contact_add_form').on('submit-success', function() {
            // Fecha a aba e volta pra janela anterior (sells/create)
            window.close();
        });
    });
</script>
@endsection
