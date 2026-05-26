// Helper compartilhado pra disparar impressão de recibo de venda.
//
// Necessário porque /sells/{id}/print (SellPosController::printInvoice) só
// responde a requests AJAX (return $output dentro de `if (request()->ajax())`).
// Abrir target="_blank" devolve tela em branco. Pattern legacy equivalente:
// public/js/app.js a.print-invoice → __print_receipt (AJAX → inject html → window.print).
//
// Uso em Pages/Sells/Show.tsx + Pages/Sells/_components/SaleSheet.tsx.

export interface PrintSaleReceiptOptions {
  printUrl: string;
  invoiceNo?: string | number;
}

export async function printSaleReceipt({ printUrl, invoiceNo }: PrintSaleReceiptOptions): Promise<void> {
  const response = await fetch(printUrl, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
  });

  const payload = await response.json().catch(() => null);
  const htmlContent: string | undefined = payload?.receipt?.html_content;
  if (!payload?.success || !htmlContent) {
    const reason = typeof payload?.msg === 'string' ? payload.msg : 'Recibo indisponível';
    throw new Error(reason);
  }

  const printWindow = window.open('', '_blank', 'width=820,height=900');
  if (!printWindow) {
    throw new Error('Bloqueador de pop-up impediu a impressão. Libere e tente de novo.');
  }

  const title =
    (typeof payload?.receipt?.print_title === 'string' && payload.receipt.print_title) ||
    (typeof payload?.print_title === 'string' && payload.print_title) ||
    `Venda ${invoiceNo ?? ''}`.trim();

  printWindow.document.open();
  printWindow.document.write(
    `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${escapeHtml(title)}</title>` +
      `<style>@media print{@page{margin:8mm;}}body{font-family:Arial,sans-serif;margin:0;padding:12px;}</style>` +
      `</head><body>${htmlContent}` +
      `<script>window.addEventListener('load',function(){setTimeout(function(){window.focus();window.print();},250);});<\/script>` +
      `</body></html>`,
  );
  printWindow.document.close();
}

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
