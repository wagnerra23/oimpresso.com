// Helper compartilhado pra disparar impressão de recibo de venda.
//
// Padrão IFRAME fora-da-vista + CSS legacy via <link>:
//   - /sells/{id}/print (SellPosController::printInvoice) só responde a requests AJAX.
//   - Devolve {receipt:{html_content}} com HTML baseado em Bootstrap 3 (col-xs-12,
//     pull-left, text-center) + classes da app legacy (.print_section, .invoice).
//   - Usar `window.open()` puro descarrega o CSS → layout fica quebrado.
//   - Em vez disso, criamos um <iframe> fora da viewport carregando `/css/app.css`
//     (tem `@media print { .print_section { display: inline !important } }` + classes
//     Bootstrap 3 legacy + invoice CSS) e injetamos o html_content dentro da section
//     `#receipt_section`. O `load` do iframe AGUARDA os <link> de CSS — só então
//     disparamos o print.
//
// FIX 2026-06-09 (espelha printServiceOrder.ts — avaliação [CC]):
//   O mecanismo anterior usava IFRAME com `visibility:hidden` + auto-`window.print()`
//   injetado por <script> DENTRO do srcdoc. Chromium/Brave recentes rasterizam iframe
//   invisível como página em branco — ou escalam o print pro frame pai (imprime a SPA
//   inteira). Agora: iframe fora-da-vista SEM visibility:hidden, e o PRINT é disparado
//   PELO PARENT via `iframe.contentWindow.print()` após load + stylesheets + fontes.
//   Fallback: `window.open` com o mesmo documento (inclui os <link> de CSS).
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

const IFRAME_ID = 'sale-receipt-print-iframe';

/** Documento completo do iframe: wrapper legacy (Bootstrap 3 + .print_section + .invoice)
 *  com os <link> de CSS — o html_content do recibo entra na section #receipt_section. */
function buildReceiptDocument({ htmlContent, title }: RenderArgs): string {
  // Stylesheets do legacy que dão Bootstrap 3 + .print_section + .invoice CSS.
  // (`/css/app.css` linha 703-719: .print_section{display:none} + @media print { display:inline !important }).
  // Também carrega init.css (resets) e tailwind/app.css (utilitários tw-* usados em alguns templates).
  const stylesheets = ['/css/app.css', '/css/init.css', '/css/tailwind/app.css']
    .map((href) => `<link rel="stylesheet" href="${href}">`)
    .join('');

  // NOTA: sem <script> de auto-print — o parent dispara o print (ver renderInHiddenIframe).
  return `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${escapeHtml(title)}</title>${stylesheets}
<style>
  /* Mostra a section também na tela do iframe (legacy só mostra em @media print) */
  body { margin: 0; padding: 12px; background: white; }
  .print_section { display: block !important; }
  .no-print { display: none !important; }
  @page { margin: 8mm; }
</style>
</head><body>
<section class="invoice print_section" id="receipt_section">${htmlContent}</section>
</body></html>`;
}

/** Legacy: converte símbolos de moeda recursivamente na section antes de imprimir. */
function applyCurrencyConvert(win: Window): void {
  const convert = (window as unknown as {
    __currency_convert_recursively?: (el: Element | null) => void;
  }).__currency_convert_recursively;
  if (typeof convert === 'function') {
    try {
      convert(win.document.getElementById('receipt_section'));
    } catch {
      /* noop */
    }
  }
}

function renderInHiddenIframe({ htmlContent, title }: RenderArgs): Promise<void> {
  return new Promise((resolve, reject) => {
    // Remove iframe anterior se houver (segurança contra duplo-click).
    document.getElementById(IFRAME_ID)?.remove();

    const fullDocument = buildReceiptDocument({ htmlContent, title });

    const iframe = document.createElement('iframe');
    iframe.id = IFRAME_ID;
    iframe.setAttribute('aria-hidden', 'true');
    iframe.title = title;
    // Fora da vista mas SEM visibility:hidden/display:none — Chromium precisa do
    // frame "renderizável" pra rasterizar o conteúdo no print (senão imprime branco).
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.style.overflow = 'hidden';

    iframe.srcdoc = fullDocument;

    const cleanup = () => {
      try {
        iframe.remove();
      } catch {
        /* noop */
      }
    };

    iframe.addEventListener(
      'load',
      () => {
        // O `load` do iframe já aguarda os <link rel="stylesheet"> — o CSS legacy
        // está aplicado aqui. Mesmo assim damos uma folga + fontes prontas pra
        // evitar print com layout em fallback.
        const win = iframe.contentWindow;
        if (!win) {
          cleanup();
          openPrintWindowFallback(fullDocument, title).then(resolve, reject);
          return;
        }
        const doPrint = () => {
          try {
            applyCurrencyConvert(win);
            win.focus();
            win.print();
            // Sem sinal confiável de fechamento do diálogo cross-browser — limpa
            // após folga generosa.
            setTimeout(cleanup, 60_000);
            resolve();
          } catch {
            cleanup();
            openPrintWindowFallback(fullDocument, title).then(resolve, reject);
          }
        };
        const fonts = (win.document as Document & { fonts?: FontFaceSet }).fonts;
        if (fonts?.ready) {
          fonts.ready.then(() => setTimeout(doPrint, 200)).catch(() => doPrint());
        } else {
          setTimeout(doPrint, 300);
        }
      },
      { once: true },
    );

    document.body.appendChild(iframe);
  });
}

/**
 * Fallback: janela dedicada com o MESMO documento (inclui os <link> de CSS legacy).
 * Mais intrusivo (abre tab/janela) mas funciona onde o browser bloqueia print()
 * programático em iframe fora da vista.
 */
function openPrintWindowFallback(fullDocument: string, title: string): Promise<void> {
  return new Promise((resolve, reject) => {
    const win = window.open('', '_blank', 'noopener=no,width=900,height=1100');
    if (!win) {
      reject(new Error('Impressão bloqueada pelo navegador — permita pop-ups pra imprimir o recibo.'));
      return;
    }
    win.document.open();
    win.document.write(fullDocument);
    win.document.close();
    win.document.title = title;
    const doPrint = () => {
      try {
        applyCurrencyConvert(win);
        win.focus();
        win.print();
      } finally {
        resolve();
      }
    };
    if (win.document.readyState === 'complete') {
      setTimeout(doPrint, 300);
    } else {
      win.addEventListener('load', () => setTimeout(doPrint, 200), { once: true });
    }
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
