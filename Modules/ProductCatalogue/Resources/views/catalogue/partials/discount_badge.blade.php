{{-- Selo de desconto da vitrine. O texto (`selo`) vem pronto do CatalogueService, já no tipo
     do desconto: "%" só para `percentage`; os demais são abatimento em dinheiro, como no PDV. --}}
@if(!empty($discount))
	<span class="label label-warning {{ $class ?? 'discount-badge' }}">{{ $discount->selo }}</span>
@endif
