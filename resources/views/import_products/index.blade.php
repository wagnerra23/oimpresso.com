@extends('layouts.app')
@section('title', __('product.import_products'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('product.import_products')
    </h1>
</section>

<!-- Main content -->
<section class="content">
    
    @if (session('notification') || !empty($notification))
        <div class="row">
            <div class="col-sm-12">
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    @if(!empty($notification['msg']))
                        {{$notification['msg']}}
                    @elseif(session('notification.msg'))
                        {{ session('notification.msg') }}
                    @endif
                </div>
            </div>  
        </div>     
    @endif
    
    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['class' => 'box-primary'])
                {!! Form::open(['url' => action('ImportProductsController@store'), 'method' => 'post', 'enctype' => 'multipart/form-data' ]) !!}
                    <div class="row">
                        <div class="col-sm-6">
                        <div class="col-sm-8">
                            <div class="form-group">
                                {!! Form::label('name', __( 'product.file_to_import' ) . ':') !!}
                                {!! Form::file('products_csv', ['accept'=> '.xls, .xlsx, .csv', 'required' => 'required']); !!}
                              </div>
                        </div>
                        <div class="col-sm-4">
                        <br>
                            <button type="submit" class="btn btn-primary">@lang('messages.submit')</button>
                        </div>
                        </div>
                    </div>

                {!! Form::close() !!}
                <br><br>
                <div class="row">
                    <div class="col-sm-4">
                        <a href="{{ asset('files/import_products_csv_template.xls') }}" class="btn btn-success" download><i class="fa fa-download"></i> @lang('lang_v1.download_template_file')</a>
                    </div>
                </div>
            @endcomponent
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.instructions')])
                <strong>@lang('lang_v1.instruction_line1')</strong><br>
                    @lang('lang_v1.instruction_line2')
                    <br><br>
                <table class="table table-striped">
                    <tr>
                        <th>@lang('lang_v1.col_no')</th>
                        <th>@lang('lang_v1.col_name')</th>
                        <th>@lang('lang_v1.instruction')</th>
                    </tr>
                    <tr>
                        <td>1</td>
                        <td>@lang('product.product_name') <small class="text-muted">(@lang('lang_v1.required'))</small></td>
                        <td>@lang('lang_v1.name_ins')</td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>@lang('product.brand') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>@lang('lang_v1.brand_ins') <br><small class="text-muted">(@lang('lang_v1.brand_ins2'))</small></td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>@lang('product.unit') <small class="text-muted">(@lang('lang_v1.required'))</small></td>
                        <td>@lang('lang_v1.unit_ins')</td>
                    </tr>
                    <tr>
                        <td>4</td>
                        <td>@lang('product.category') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>@lang('lang_v1.category_ins') <br><small class="text-muted">(@lang('lang_v1.category_ins2'))</small></td>
                    </tr>
                    <tr>
                        <td>5</td>
                        <td>@lang('product.sub_category') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>@lang('lang_v1.sub_category_ins') <br><small class="text-muted">({!! __('lang_v1.sub_category_ins2') !!})</small></td>
                    </tr>
                    <tr>
                        <td>6</td>
                        <td>@lang('product.sku') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>@lang('lang_v1.sku_ins')</td>
                    </tr>
                    <tr>
                        <td>7</td>
                        <td>@lang('product.barcode_type') <small class="text-muted">(@lang('lang_v1.optional'), @lang('lang_v1.default'): C128)</small></td>
                        <td>@lang('lang_v1.barcode_type_ins') <br>
                            <strong>@lang('lang_v1.barcode_type_ins2'): C128, C39, EAN-13, EAN-8, UPC-A, UPC-E, ITF-14</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>8</td>
                        <td>@lang('product.manage_stock') <small class="text-muted">(@lang('lang_v1.required'))</small></td>
                        <td>@lang('lang_v1.manage_stock_ins')<br>
                            <strong>1 = @lang('messages.yes')<br>
                            0 = @lang('messages.no')</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>9</td>
                        <td>@lang('product.alert_quantity') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>@lang('product.alert_quantity')</td>
                    </tr>
                    <tr>
                        <td>10</td>
                        <td>@lang('product.expires_in') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>@lang('lang_v1.expires_in_ins')</td>
                    </tr>
                    <tr>
                        <td>11</td>
                        <td>@lang('lang_v1.expire_period_unit') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>@lang('lang_v1.expire_period_unit_ins')<br>
                            <strong>@lang('lang_v1.available_options'): days, months</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>12</td>
                        <td>@lang('product.applicable_tax') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>@lang('lang_v1.applicable_tax_ins') {!! __('lang_v1.applicable_tax_help') !!}</td>
                    </tr>
                    <tr>
                        <td>13</td>
                        <td>@lang('product.selling_price_tax_type') <small class="text-muted">(@lang('lang_v1.required'))</small></td>
                        <td>@lang('product.selling_price_tax_type') <br>
                            <strong>@lang('lang_v1.available_options'): inclusive, exclusive</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>14</td>
                        <td>@lang('product.product_type') <small class="text-muted">(@lang('lang_v1.required'))</small></td>
                        <td>@lang('product.product_type') <br>
                            <strong>@lang('lang_v1.available_options'): single, variable</strong></td>
                    </tr>
                    <tr>
                        <td>15</td>
                        <td>@lang('product.variation_name') <small class="text-muted">(@lang('lang_v1.variation_name_ins'))</small></td>
                        <td>@lang('lang_v1.variation_name_ins2')</td>
                    </tr>
                    <tr>
                        <td>16</td>
                        <td>@lang('product.variation_values') <small class="text-muted">(@lang('lang_v1.variation_values_ins'))</small></td>
                        <td>{!! __('lang_v1.variation_values_ins2') !!}</td>
                    </tr>
                    <tr>
                        <td>17</td>
                        <td> @lang('lang_v1.purchase_price_inc_tax')<br><small class="text-muted">(@lang('lang_v1.purchase_price_inc_tax_ins1'))</small></td>
                        <td>{!! __('lang_v1.purchase_price_inc_tax_ins2') !!}</td>
                    </tr>
                    <tr>
                        <td>18</td>
                        <td>@lang('lang_v1.purchase_price_exc_tax')  <br><small class="text-muted">(@lang('lang_v1.purchase_price_exc_tax_ins1'))</small></td>
                        <td>{!! __('lang_v1.purchase_price_exc_tax_ins2') !!}</td>
                    </tr>
                    <tr>
                        <td>19</td>
                        <td>@lang('lang_v1.profit_margin') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>@lang('lang_v1.profit_margin_ins')<br>
                            <small class="text-muted">{!! __('lang_v1.profit_margin_ins1') !!}</small></td>
                    </tr>
                    <tr>
                        <td>20</td>
                        <td>@lang('lang_v1.selling_price') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>@lang('lang_v1.selling_price_ins')<br>
                         <small class="text-muted">{!! __('lang_v1.selling_price_ins1') !!}</small></td>
                    </tr>
                    <tr>
                        <td>21</td>
                        <td>@lang('lang_v1.opening_stock') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>@lang('lang_v1.opening_stock_ins') {!! __('lang_v1.opening_stock_help_text') !!}<br>
                        </td>
                    </tr>
                    <tr>
                        <td>22</td>
                        <td>@lang('lang_v1.opening_stock_location') <small class="text-muted">(@lang('lang_v1.optional')) <br>@lang('lang_v1.location_ins')</small></td>
                        <td>@lang('lang_v1.location_ins1')<br>
                        </td>
                    </tr>
                    <tr>
                        <td>23</td>
                        <td>@lang('lang_v1.expiry_date') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>{!! __('lang_v1.expiry_date_ins') !!}<br>
                        </td>
                    </tr>
                    <tr>
                        <td>24</td>
                        <td>@lang('lang_v1.enable_imei_or_sr_no') <small class="text-muted">(@lang('lang_v1.optional'), @lang('lang_v1.default'): 0)</small></td>
                        <td><strong>1 = @lang('messages.yes')<br>
                            0 = @lang('messages.no')</strong><br>
                        </td>
                    </tr>
                    <tr>
                        <td>25</td>
                        <td>@lang('lang_v1.weight') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>@lang('lang_v1.optional')<br>
                        </td>
                    </tr>
                    <tr>
                        <td>26</td>
                        <td>@lang('lang_v1.rack') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>{!! __('lang_v1.rack_help_text') !!}</td>
                    </tr>
                    <tr>
                        <td>27</td>
                        <td>@lang('lang_v1.row') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>{!! __('lang_v1.row_help_text') !!}</td>
                    </tr>
                    <tr>
                        <td>28</td>
                        <td>@lang('lang_v1.position') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>{!! __('lang_v1.position_help_text') !!}</td>
                    </tr>
                    <tr>
                        <td>29</td>
                        <td>@lang('lang_v1.image') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>{!! __('lang_v1.image_help_text', ['path' => 'public/uploads/'.config('constants.product_img_path')]) !!}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>30</td>
                        <td>@lang('lang_v1.product_description') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>31</td>
                        <td>@lang('lang_v1.product_custom_field1') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>32</td>
                        <td>@lang('lang_v1.product_custom_field2') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                    </tr>
                    <tr>
                        <td>33</td>
                        <td>@lang('lang_v1.product_custom_field3') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>34</td>
                        <td>@lang('lang_v1.product_custom_field4') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                    </tr>
                    <tr>
                        <td>35</td>
                        <td>@lang('lang_v1.not_for_selling') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td><strong>1 = @lang('messages.yes')<br>
                            0 = @lang('messages.no')</strong><br>
                        </td>
                    </tr>
                    <tr>
                        <td>36</td>
                        <td>@lang('lang_v1.product_locations') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>@lang('lang_v1.product_locations_ins')
                        </td>
                    </tr>

                <tr>
                    <td>37</td>
                    <td>%ICMS <small class="text-muted">(Obrigatório)</small></td>
                    <td>Percentual de ICMS para o produto
                    </td>
                </tr>
                <tr>
                    <td>38</td>
                    <td>%PIS <small class="text-muted">(Obrigatório)</small></td>
                    <td>Percentual de PIS para o produto
                    </td>
                </tr>
                <tr>
                    <td>39</td>
                    <td>%COFINS <small class="text-muted">(Obrigatório)</small></td>
                    <td>Percentual de COFINS para o produto
                    </td>
                </tr>
                <tr>
                    <td>40</td>
                    <td>%IPI <small class="text-muted">(Obrigatório)</small></td>
                    <td>Percentual de IPI para o produto
                    </td>
                </tr>

                <tr>
                    <td>41</td>
                    <td>CST/CSOSN <small class="text-muted"></small></td>
                    <td>CST/CSOSN para o produto padrão 101
                    </td>
                </tr>

                <tr>
                    <td>42</td>
                    <td>CST/PIS <small class="text-muted"></small></td>
                    <td>CST/PIS para o produto padrão 49
                    </td>
                </tr>

                <tr>
                    <td>43</td>
                    <td>CST/COFINS <small class="text-muted"></small></td>
                    <td>CST/COFINS para o produto padrão 49
                    </td>
                </tr>

                <tr>
                    <td>44</td>
                    <td>CST/IPI <small class="text-muted"></small></td>
                    <td>CST/IPI para o produto padrão 99
                    </td>
                </tr>

                <tr>
                    <td>45</td>
                    <td>NCM <small class="text-muted">(Obrigatório)</small></td>
                    <td>NCM para o produto
                    </td>
                </tr>

                <tr>
                    <td>46</td>
                    <td>CEST <small class="text-muted"></small></td>
                    <td>CEST para o produto
                    </td>
                </tr>

                <tr>
                    <td>47</td>
                    <td>CFOP Saida Estadual <small class="text-muted">(Obrigatório)</small></td>
                    <td>CFOP Saida Estadual para o produto
                    </td>
                </tr>

                <tr>
                    <td>48</td>
                    <td>CFOP Saida Inter Estadual <small class="text-muted">(Obrigatório)</small></td>
                    <td>CFOP Saida Inter Estadual para o produto
                    </td>
                </tr>

                </table>
            @endcomponent
        </div>
    </div>
</section>
<!-- /.content -->

@endsection