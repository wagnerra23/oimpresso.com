<table align="center" style="border-spacing: {{$barcode_details->col_distance * 1}}cm {{$barcode_details->row_distance * 1}}cm; overflow: hidden !important;">
@foreach($page_products as $page_product)
	@if($loop->index % $barcode_details->stickers_in_one_row == 0)
		<!-- create a new row -->
		<tr>
		<!-- <columns column-count="{{$barcode_details->stickers_in_one_row}}" column-gap="{{$barcode_details->col_distance*1}}"> -->
	@endif
		<td align="center" valign="center">
			<div style="justify-content:center; overflow: hidden !important;display: flex; flex-wrap: wrap;align-content: center;width: {{$barcode_details->width * 1}}cm; height: {{$barcode_details->height * 1}}cm;">		
				<div>
					{{-- Business Name --}}
					@if(!empty($print['business_name']))
						<b style="margin-top: -12px; display: block !important; font-size: {{13*$factor}}px">{{$business_name}}</b>
					@endif
					{{-- Product Name --}}
					@if(!empty($print['name']))
					@if (strlen($page_product->product_actual_name) <= 46 ) 
						<span style=" display: block !important;  font-size: {{10*$factor}}px">
							{{$page_product->product_actual_name}}
						</span>
					@endif
					@endif
					{{-- Variation --}}
					@if(!empty($print['variations']) && $page_product->is_dummy != 1)
						<span style="margin-top: -1px; display: block !important; font-size: {{13*$factor}}px">
							<b>{{$page_product->product_variation_name}}</b>:{{$page_product->variation_name}}
						</span>
					@endif
					{{-- Price --}}
					@if(!empty($print['price']))
					<span style="font-size: {{13*$factor}}px">
						{{-- <b>@lang('lang_v1.price'):</b> --}}
						{{session('currency')['symbol'] ?? ''}}						
						@if($print['price_type'] == 'inclusive')
							{{@num_format($page_product->sell_price_inc_tax)}}
						@else
							{{@num_format($page_product->default_sell_price)}}
						@endif
					</span>
					@endif
					{{-- <br> --}}
					{{-- Barcode --}}
					<img style="max-width:85% !important;height: {{$barcode_details->height*0.34}}cm !important;" src="data:image/png;base64,{{DNS1D::getBarcodePNG($page_product->sub_sku, $page_product->barcode_type, 4,100,array(39, 48, 54), true)}}">	
					<span style="display: block !important; font-size: {{13*$factor}}px; margin-top: -3px;">
						{{$page_product->sub_sku}}
					</span>
				</div>
			</div>		
		</td>
	@if($loop->iteration % $barcode_details->stickers_in_one_row == 0)
		</tr>
	@endif
@endforeach
</table>
<style type="text/css">
	@media print{		
		/* padding-top: -20cm; */
		body { margin: 0cm; }
		table{
			page-break-after: always;
			font-family: Arial, Helvetica, sans-serif;
		} 
		@page {
	
		size: {{$paper_width}}cm {{$paper_height}}cm;
	
		/* width: {{$barcode_details->paper_width}}in !important;
		height:@if($barcode_details->paper_height != 0){{$barcode_details->paper_height}}in !important @else auto @endif; */

		margin-top: {{$margin_top}}cm !important;
		margin-bottom: {{$margin_top}}cm !important;
		margin-left: {{$margin_left}}cm !important;
		margin-right: {{$margin_left}}cm !important;		
	}
	*{
	/* margin:0;
    padding:0; */
    /* border:0; */
	}

	}
</style>