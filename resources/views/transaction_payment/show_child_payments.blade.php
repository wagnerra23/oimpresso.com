<table class="table table-condensed bg-gray">
  <tr>
    <th>@lang('purchase.ref_no')</th>
    <th>@lang('lang_v1.paid_on')</th>
    <th>@lang('sale.amount')</th>
    <th>@lang('contact.contact')</th>
    <th>@lang('lang_v1.payment_method')</th>
    <th>@if($child_payments->first()->transaction->type == 'purchase') @lang('purchase.ref_no') @else  @lang('sale.invoice_no') @endif</th>
    <th class="no-print">@lang('messages.action')</th>
  </tr>
  @forelse ($child_payments as $payment)
    <tr>
      <td>{{ $payment->payment_ref_no }}</td>
      <td>{{ @format_datetime($payment->paid_on) }}</td>
      <td><span class="display_currency" data-currency_symbol="true">{{ $payment->amount }}</span></td>
      <td>{{$payment->transaction->contact->name}}</td>
      <td>{{ $payment_types[$payment->method] ?? '' }}</td>
      <td>@if($payment->transaction->type == 'sell') <a data-href="{{action([\App\Http\Controllers\SellController::class, 'show'], [$payment->transaction_id]) }}" href="#" data-container=".view_modal" class="btn-modal">{{$payment->transaction->invoice_no}}</a> @elseif($payment->transaction->type != 'opening_balance') {{-- compra: a Blade do modal saiu em 2026-10-27; abre a tela React --}}<a href="{{action([\App\Http\Controllers\PurchaseController::class, 'show'], [$payment->transaction_id]) }}" target="_blank" rel="noopener">{{$payment->transaction->ref_no}}</a> @else
        @lang('lang_v1.opening_balance_payments')
      @endif</td>
      <td class="no-print">
        <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-primary view_payment" data-href="{{ action([\App\Http\Controllers\TransactionPaymentController::class, 'viewPayment'], [$payment->id]) }}" >@lang("messages.view")
                    </button>
      </td>
    </tr>
  @empty
    <tr class="text-center">
      <td colspan="6">@lang('purchase.no_records_found')</td>
    </tr>
  @endforelse
</table>