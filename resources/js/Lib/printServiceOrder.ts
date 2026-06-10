// Helper compartilhado pra disparar impressão de OS (Ordem de Serviço) OficinaAuto.
//
// Gap 3 US-OFICINA-037 — GET {id}/print só responde AJAX (X-Requested-With);
// devolve {success:1, receipt:{html_content,print_title}} com HTML auto-contido
// (CSS inline no <style> do Blade).
//
// FIX 2026-06-09 (avaliação [CC] — "Imprimir OS sai feio/vazio"):
// O mecanismo anterior usava IFRAME 0×0 com visibility:hidden e auto-window.print()
// disparado por <script> injetado DENTRO do srcdoc. Chromium/Brave recentes
// rasterizam iframe invisível como página em branco — ou escalam o print pro
// frame pai (imprime o AppShellV2 inteiro = "sistema porco").
// Novo mecanismo:
//   1. IFRAME continua fora da vista (fixed, 0×0, sem visibility:hidden — isso
//      é o que mata a rasterização), srcdoc com o doc completo do Blade.
//   2. O PRINT é chamado PELO PARENT via iframe.contentWindow.print() após o
//      load + fontes prontas — alvo do diálogo é garantidamente o documento da OS.
//   3. Fallback: se contentWindow.print() falhar (política do browser), abre
//      window.open dedicada com o mesmo HTML e imprime lá.
//
// Multi-tenant Tier 0 (ADR 0093): defesa server-side — frontend só passa a rota.
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

  await renderAndPrint({ htmlContent, title });
}

interface RenderArgs {
  htmlContent: string;
  title: string;
}

/**
 * Mecanismo canônico de impressão de HTML auto-contido (iframe fora da vista +
 * print pelo parent + fallback window.open). Exportado pra reuso por outros
 * documentos da Oficina (ex.: printOficinaFila.ts) — estender, não recriar.
 */
export function printHtmlDocument(args: RenderArgs): Promise<void> {
  return renderAndPrint(args);
}

const IFRAME_ID = 'service-order-print-iframe';

function renderAndPrint({ htmlContent, title }: RenderArgs): Promise<void> {
  return new Promise((resolve, reject) => {
    // Remove IFRAME anterior (proteção contra duplo-click).
    document.getElementById(IFRAME_ID)?.remove();

    const iframe = document.createElement('iframe');
    iframe.id = IFRAME_ID;
    iframe.setAttribute('aria-hidden', 'true');
    iframe.title = title;
    // Fora da vista mas SEM visibility:hidden/display:none — Chromium precisa
    // do frame "renderizável" pra rasterizar o conteúdo no print.
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.style.overflow = 'hidden';

    // htmlContent é o doc completo do Blade (CSS inline). NÃO injetamos mais
    // <script> de auto-print — o parent dispara o print (ver abaixo).
    iframe.srcdoc = htmlContent;

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
        const win = iframe.contentWindow;
        if (!win) {
          cleanup();
          openPrintWindowFallback({ htmlContent, title }).then(resolve, reject);
          return;
        }
        const doPrint = () => {
          try {
            win.focus();
            win.print();
            // Não dá pra saber quando o diálogo fecha de forma confiável
            // cross-browser — limpa após folga generosa.
            setTimeout(cleanup, 60_000);
            resolve();
          } catch {
            cleanup();
            openPrintWindowFallback({ htmlContent, title }).then(resolve, reject);
          }
        };
        // Espera fontes/imagens do doc interno antes de abrir o diálogo
        // (evita print com layout em fallback de fonte).
        const fonts = (win.document as Document & { fonts?: FontFaceSet }).fonts;
        if (fonts?.ready) {
          fonts.ready.then(() => setTimeout(doPrint, 150)).catch(() => doPrint());
        } else {
          setTimeout(doPrint, 250);
        }
      },
      { once: true },
    );

    document.body.appendChild(iframe);
  });
}

/**
 * Fallback: janela dedicada. Mais intrusivo (abre tab/janela), mas funciona
 * mesmo onde o browser bloqueia print() programático em iframe fora da vista.
 */
function openPrintWindowFallback({ htmlContent, title }: RenderArgs): Promise<void> {
  return new Promise((resolve, reject) => {
    const win = window.open('', '_blank', 'noopener=no,width=900,height=1100');
    if (!win) {
      reject(new Error('Impressão bloqueada pelo navegador — permita pop-ups pra imprimir a OS.'));
      return;
    }
    win.document.open();
    win.document.write(htmlContent);
    win.document.close();
    win.document.title = title;
    const doPrint = () => {
      try {
        win.focus();
        win.print();
      } finally {
        resolve();
      }
    };
    if (win.document.readyState === 'complete') {
      setTimeout(doPrint, 250);
    } else {
      win.addEventListener('load', () => setTimeout(doPrint, 150), { once: true });
    }
  });
}
