@php 
    $colspan = 12;
    $custom_labels = json_decode(session('business.custom_labels'), true);
@endphp
<div class="table-responsive">
    <table class="table table-bordered table-striped ajax_view hide-footer" id="product_table">
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all-row"></th>
                <th>&nbsp;</th>
                <th>@lang('messages.action')</th>
                <th>@lang('sale.product')</th>
                <th>@lang('purchase.business_location') @show_tooltip(__('lang_v1.product_business_location_tooltip'))</th>
                @can('view_purchase_price')
                    @php 
                        $colspan++;
                    @endphp
                    <th>@lang('lang_v1.unit_perchase_price')</th>
                @endcan
                @can('access_default_selling_price')
                    @php 
                        $colspan++;
                    @endphp
                    <th>@lang('lang_v1.selling_price')</th>
                @endcan
                <th>@lang('report.current_stock')</th>
                <th>@lang('product.product_type')</th>
                <th>@lang('product.category')</th>
                <th>@lang('product.brand')</th>
                <th>@lang('product.tax')</th>
                <th>@lang('product.sku')</th>
                <th>{{ $custom_labels['product']['custom_field_1'] ?? __('lang_v1.product_custom_field1') }}</th>
                <th>NCM</th>
                <th>CFOP</th>
                <th>CEST</th>
            </tr>
        </thead>
        <tfoot>
            <tr>
                <td colspan="{{$colspan}}">
                <div style="display: flex; width: 100%;">
                    @can('product.delete')
                        {!! Form::open(['url' => action('ProductController@massDestroy'), 'method' => 'post', 'id' => 'mass_delete_form' ]) !!}
                        {!! Form::hidden('selected_rows', null, ['id' => 'selected_rows']); !!}
                        {!! Form::submit(__('lang_v1.delete_selected'), array('class' => 'btn btn-xs btn-danger', 'id' => 'delete-selected')) !!}
                        {!! Form::close() !!}
                    @endcan
                    @can('product.update')
                    &nbsp;
                        {!! Form::open(['url' => action('ProductController@bulkEdit'), 'method' => 'post', 'id' => 'bulk_edit_form' ]) !!}
                        {!! Form::hidden('selected_products', null, ['id' => 'selected_products_for_edit']); !!}
                        <button type="submit" class="btn btn-xs btn-primary" id="edit-selected"> <i class="fa fa-edit"></i>{{__('lang_v1.bulk_edit')}}</button>
                        {!! Form::close() !!}
                        &nbsp;
                        <button type="button" class="btn btn-xs btn-success update_product_location" data-type="add">@lang('lang_v1.add_to_location')</button>
                        &nbsp;
                        <button type="button" class="btn btn-xs bg-navy update_product_location" data-type="remove">@lang('lang_v1.remove_from_location')</button>
                    @endcan
                    &nbsp;
                    {!! Form::open(['url' => action('ProductController@massDeactivate'), 'method' => 'post', 'id' => 'mass_deactivate_form' ]) !!}
                    {!! Form::hidden('selected_products', null, ['id' => 'selected_products']); !!}
                    {!! Form::submit(__('lang_v1.deactivate_selected'), array('class' => 'btn btn-xs btn-warning', 'id' => 'deactivate-selected')) !!}
                    {!! Form::close() !!} @show_tooltip(__('lang_v1.deactive_product_tooltip'))
                    </div>
                </td>
            </tr>
        </tfoot>
    </table>
</div>