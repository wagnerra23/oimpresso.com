@extends('layouts.app')
@section('title', __( 'tax_rate.tax_rates' ))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang( 'tax_rate.tax_rates' )
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang( 'tax_rate.manage_your_tax_rates' )</small>
    </h1>
</section>

<!-- Main content -->
<section class="content">
    @php
        // ADR ARQ-0005 — banner pra direcionar tenant que tem NfeBrasil ativo
        // pra UI fiscal avançada (cascade NCM + alíquotas detalhadas).
        // Defensivo: try/catch porque tabela pode não existir em ambientes
        // sem o módulo NfeBrasil instalado.
        $nfe_brasil_ativo = false;
        try {
            $nfe_brasil_ativo = \Illuminate\Support\Facades\Schema::hasTable('nfe_business_configs')
                && \Illuminate\Support\Facades\DB::table('nfe_business_configs')
                    ->where('business_id', session('user.business_id'))
                    ->exists();
        } catch (\Throwable $e) {
            $nfe_brasil_ativo = false;
        }
    @endphp

    @if ($nfe_brasil_ativo)
        <div class="tw-mb-4 tw-rounded-lg tw-border tw-border-blue-200 tw-bg-gradient-to-r tw-from-blue-50 tw-to-indigo-50 tw-p-4 tw-shadow-sm dark:tw-border-blue-800 dark:tw-from-blue-900/20 dark:tw-to-indigo-900/20">
            <div class="tw-flex tw-items-start tw-gap-3">
                <div class="tw-flex-shrink-0">
                    <span class="tw-text-2xl">🇧🇷</span>
                </div>
                <div class="tw-flex-1">
                    <h4 class="tw-text-sm tw-font-semibold tw-text-blue-900 dark:tw-text-blue-200 tw-mb-1">
                        Configuração Fiscal Avançada disponível
                    </h4>
                    <p class="tw-text-xs tw-text-blue-800 dark:tw-text-blue-300 tw-mb-2">
                        Para NF-e/NFC-e brasileira, use o cascade tributário (NCM × UF × CSOSN/CST + alíquotas
                        ICMS/PIS/COFINS/IPI) em vez de cadastrar taxas avulsas aqui.
                    </p>
                    <a href="{{ url('/nfe-brasil/tributacao') }}"
                       class="tw-inline-flex tw-items-center tw-gap-1.5 tw-text-xs tw-font-medium tw-text-blue-700 hover:tw-text-blue-900 dark:tw-text-blue-300 dark:hover:tw-text-blue-100">
                        Acessar Tributação NF-e Brasil →
                    </a>
                </div>
            </div>
        </div>
    @endif

    @component('components.widget', ['class' => 'box-primary', 'title' => __( 'tax_rate.all_your_tax_rates' )])
        @can('tax_rate.create')
            @slot('tool')
                <div class="box-tools">
                    <button class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full btn-modal pull-right"
                        data-href="{{action([\App\Http\Controllers\TaxRateController::class, 'create'])}}" 
                        data-container=".tax_rate_modal">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 5l0 14" />
                            <path d="M5 12l14 0" />
                        </svg> @lang('messages.add')
                    </button>
                </div>
            @endslot
        @endcan
        @can('tax_rate.view')
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="tax_rates_table">
                    <thead>
                        <tr>
                            <th>@lang( 'tax_rate.name' )</th>
                            <th>@lang( 'tax_rate.rate' )</th>
                            <th>@lang( 'messages.action' )</th>
                        </tr>
                    </thead>
                </table>
            </div>
        @endcan
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
        @slot('title')
            @lang( 'tax_rate.tax_groups' ) ( @lang('lang_v1.combination_of_taxes') ) @show_tooltip(__('tooltip.tax_groups'))
        @endslot
        @can('tax_rate.create')
            @slot('tool')
                <div class="box-tools">
                    {{-- <button type="button" class="btn btn-block btn-primary btn-modal" 
                    data-href="{{action([\App\Http\Controllers\GroupTaxController::class, 'create'])}}" 
                    data-container=".tax_group_modal">
                    <i class="fa fa-plus"></i> @lang( 'messages.add' )</button> --}}
                    <button class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full btn-modal pull-right"
                        data-href="{{action([\App\Http\Controllers\GroupTaxController::class, 'create'])}}" 
                        data-container=".tax_group_modal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 5l0 14" />
                        <path d="M5 12l14 0" />
                    </svg> @lang('messages.add')
                </button>
                </div>
            @endslot
        @endcan
        @can('tax_rate.view')
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="tax_groups_table">
                    <thead>
                        <tr>
                            <th>@lang( 'tax_rate.name' )</th>
                            <th>@lang( 'tax_rate.rate' )</th>
                            <th>@lang( 'tax_rate.sub_taxes' )</th>
                            <th>@lang( 'messages.action' )</th>
                        </tr>
                    </thead>
                </table>
            </div>
        @endcan
    @endcomponent
    
    <div class="modal fade tax_rate_modal" tabindex="-1" role="dialog" 
    	aria-labelledby="gridSystemModalLabel">
    </div>
    <div class="modal fade tax_group_modal" tabindex="-1" role="dialog" 
        aria-labelledby="gridSystemModalLabel">
    </div>

</section>
<!-- /.content -->
@endsection
