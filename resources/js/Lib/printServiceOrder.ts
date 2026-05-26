// Helper compartilhado pra disparar impressão de OS (Ordem de Serviço) OficinaAuto.
//
// Gap 3 US-OFICINA-037 — espelha pattern printSaleReceipt.ts (Sells legacy):
//   - GET /oficina-auto/ordens-servico/{id}/print só responde a requests AJAX
//     (X-Requested-With: XMLHttpRequest); sem o header retorna 404 (Controller
//     `ServiceOrderController::printInvoice` aborta — evita AppShellV2 vazado).
//   - Devolve {success:1, receipt:{html_content,print_title}} com HTML
//     auto-contido (CSS inline no <style> do Blade) — funciona em IFRAME
//     cross-origin sem depender de stylesheets do parent.
//   - Criamos um <iframe> oculto via srcdoc, e o HTML do template ja inclui
//     `<style>` + `<script>` que dispara window.print() on-load.
//   - Cleanup do IFRAME após delay (suficiente pro user fechar a janela nativa).
//
// Diferença vs printSaleReceipt.ts: printSaleReceipt carrega /css/app.css legacy
// dentro do IFRAME (Bootstrap 3 + invoice classes). Aqui o Blade já vem com TODO
// CSS necessário inline — IFRAME 100% self-contained.
//
// V0 modo único "invoice" (A4 papel-balcão). +packing_slip / +delivery_note
// em wave futura se necessário.
//
// Multi-tenant Tier 0 (ADR 0093): defesa server-side — frontend só precisa passar
// a route correta. Controller faz defensive guard cross-business.
//
// Uso em ServiceOrderRichSheet.tsx (drawer footer) + Show.tsx (PageHeader actions).

export interface PrintServiceOrderOptions {
  /** URL absoluta ou relativa do endpoint print (ex /oficina-auto/ordens-servico/123/print). */
  printUrl: string;
  /** Número da OS pra fallback de title se backend não devolver print_title. */
  osNumber?: string | number;
}

export async function printServiceOrder({
  printUrl,
  osNumber,
}: PrintServiceOrderOptions): Promise<void> {
  const response = await fetch(printUrl, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    throw new Error(`Falha ao gerar impressão da OS (HTTP ${response.status})`);
  }

  const payload = await response.json().catch(() => null);
  const htmlContent: string | undefined = payload?.receipt?.html_content;
  if (!payload?.success || !htmlContent) {
    const reason = typeof payload?.msg === 'string' ? payload.msg : 'Recibo indisponível';
    throw new Error(reason);
  }

  const title =
    (typeof payload?.receipt?.print_title === 'string' && payload.receipt.print_title) ||
    `OS ${osNumber ?? ''}`.trim();

  await renderInHiddenIframe({ htmlContent, title });
}

interface RenderArgs {
  htmlContent: string;
  title: string;
}

function renderInHiddenIframe({ htmlContent, title }: RenderArgs): Promise<void> {
  return new Promise((resolve) => {
    // Remove IFRAME anterior se houver (segurança contra duplo-click rapidíssimo).
    const previous = document.getElementById('service-order-print-iframe');
    if (previous) previous.remove();

    const iframe = document.createElement('iframe');
    iframe.id = 'service-order-print-iframe';
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.style.visibility = 'hidden';

    // O Blade já vem com TODO CSS necessário inline (não carrega /css/app.css).
    // Só envelopamos o html_content (que é o <!DOCTYPE html>...</html> completo do
    // template) e injetamos um <script> auto-print no final do <body>.
    //
    // htmlContent já é doc completo do Blade — só injetamos script de print antes
    // de fechar </body>. Title já vem no <head> do template.
    const printScript = `<script>
(function(){
  function go(){
    try {
      setTimeout(function(){ window.focus(); window.print(); }, 200);
    } catch(e) { console.error('print error', e); }
  }
  if (document.readyState === 'complete') { go(); } else { window.addEventListener('load', go); }
})();
<\/script>`;

    // Injeta script antes de </body> (template Blade já tem </body></html> bem-formado).
    const srcdoc = htmlContent.replace(/<\/body>/i, `${printScript}</body>`);

    iframe.srcdoc = srcdoc;
    iframe.title = title;
    iframe.addEventListener(
      'load',
      () => {
        // Resolve cedo — print() é disparado pelo script dentro do IFRAME.
        // Limpa IFRAME ~30s depois (suficiente pro user fechar janela nativa).
        setTimeout(() => {
          try {
            iframe.remove();
          } catch {
            /* noop */
          }
        }, 30000);
        resolve();
      },
      { once: true },
    );

    document.body.appendChild(iframe);
  });
}
