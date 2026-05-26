// Helper compartilhado pra disparar impressão de recibo de venda.
//
// Padrão IFRAME OCULTO + CSS legacy embutido:
//   - /sells/{id}/print (SellPosController::printInvoice) só responde a requests AJAX.
//   - Devolve {receipt:{html_content}} com HTML baseado em Bootstrap 3 (col-xs-12,
//     pull-left, text-center) + classes da app legacy (.print_section, .invoice).
//   - Usar `window.open()` puro descarrega o CSS → layout fica quebrado.
//   - Em vez disso, criamos um <iframe> oculto na própria página Inertia carregando
//     `/css/app.css` (tem `@media print { .print_section { display: inline !important } }`
//     + classes Bootstrap 3 legacy + invoice CSS) e injetamos o html_content dentro
//     da section `#receipt_section`. Depois chamamos `iframe.contentWindow.print()`.
//   - Equivale ao legacy `public/js/app.js:1656` (a.print-invoice → __print_receipt),
//     que injetava em `#receipt_section` do próprio `<body>` e chamava window.print().
//
// 3 modos do legacy (sale_pos/show.blade.php:413/416 + SellController:437-440):
//   - 'invoice'       → GET /sells/{id}/print
//   - 'packing_slip'  → GET /sells/{id}/print?package_slip=true
//   - 'delivery_note' → GET /sells/{id}/print?delivery_note=true
//
// Uso em Pages/Sells/Show.tsx + Pages/Sells/_components/SaleSheet.tsx.

export type PrintSaleMode = 'invoice' | 'packing_slip' | 'delivery_note';

export interface PrintSaleReceiptOptions {
  printUrl: string;
  invoiceNo?: string | number;
  mode?: PrintSaleMode;
}

const MODE_QUERY: Record<PrintSaleMode, string> = {
  invoice: '',
  packing_slip: '?package_slip=true',
  delivery_note: '?delivery_note=true',
};

export async function printSaleReceipt({
  printUrl,
  invoiceNo,
  mode = 'invoice',
}: PrintSaleReceiptOptions): Promise<void> {
  const url = printUrl + MODE_QUERY[mode];
  const response = await fetch(url, {
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

  const title =
    (typeof payload?.receipt?.print_title === 'string' && payload.receipt.print_title) ||
    (typeof payload?.print_title === 'string' && payload.print_title) ||
    `Venda ${invoiceNo ?? ''}`.trim();

  await renderInHiddenIframe({ htmlContent, title });
}

interface RenderArgs {
  htmlContent: string;
  title: string;
}

function renderInHiddenIframe({ htmlContent, title }: RenderArgs): Promise<void> {
  return new Promise((resolve) => {
    // Remove iframe anterior se houver (segurança contra duplo-click).
    const previous = document.getElementById('sale-receipt-print-iframe');
    if (previous) previous.remove();

    const iframe = document.createElement('iframe');
    iframe.id = 'sale-receipt-print-iframe';
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.style.visibility = 'hidden';

    // Stylesheets do legacy que dão Bootstrap 3 + .print_section + .invoice CSS.
    // (`/css/app.css` linha 703-719: .print_section{display:none} + @media print { display:inline !important }).
    // Também carrega init.css (resets) e tailwind/app.css (utilitários tw-* usados em alguns templates).
    const stylesheets = ['/css/app.css', '/css/init.css', '/css/tailwind/app.css']
      .map((href) => `<link rel="stylesheet" href="${href}">`)
      .join('');

    const srcdoc = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${escapeHtml(title)}</title>${stylesheets}
<style>
  /* Mostra a section também na tela do iframe (legacy só mostra em @media print) */
  body { margin: 0; padding: 12px; background: white; }
  .print_section { display: block !important; }
  .no-print { display: none !important; }
  @page { margin: 8mm; }
</style>
</head><body>
<section class="invoice print_section" id="receipt_section">${htmlContent}</section>
<script>
(function(){
  function go(){
    try {
      if (typeof window.parent !== 'undefined' && typeof window.parent.__currency_convert_recursively === 'function') {
        try { window.parent.__currency_convert_recursively(document.getElementById('receipt_section')); } catch(e){}
      }
      setTimeout(function(){ window.focus(); window.print(); }, 200);
    } catch(e) { console.error('print error', e); }
  }
  if (document.readyState === 'complete') { go(); } else { window.addEventListener('load', go); }
})();
<\/script>
</body></html>`;

    iframe.srcdoc = srcdoc;
    iframe.addEventListener('load', () => {
      // Resolve cedo — o print() é disparado pelo script dentro do iframe.
      // Limpa iframe ~30s depois (suficiente pro user fechar a janela nativa de impressão).
      setTimeout(() => {
        try { iframe.remove(); } catch {}
      }, 30000);
      resolve();
    }, { once: true });

    document.body.appendChild(iframe);
  });
}

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
