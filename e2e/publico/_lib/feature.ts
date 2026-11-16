import type { Page } from '@playwright/test';

// Sonda de existência de rota para specs que dependem de PR ainda não mergeado.
//
// Uso (no beforeEach ou no próprio teste):
//   test.skip(!(await rotaExiste(page, '/direitos')), 'aguardando PR #10908');
//
// "Existe" = a rota responde 2xx SEM seguir redirect. No main de hoje, qualquer caminho
// desconhecido do domínio da Uniafibro devolve 302 para a home (rota uniafibro.outros), e o
// ERP devolve 404 — os dois contam como ausente.
//
// Por que a sonda roda DENTRO da página (fetch) e não pelo fixture `request`: no CI o domínio
// só chega ao app local pelo --host-resolver-rules do Chromium. O `request` do Playwright faz
// HTTP pelo Node, ignora essa regra, resolve o DNS real e pendura na porta 8000 do site de
// produção (medido no 1º run do PR #10922: timeout de 30s nos 2 projetos). Pelo fetch da
// página, a sonda passa pela mesma resolução que o teste usa.
//
// Regra de honestidade (LC-13): skip NÃO é verde. O relatório conta os skipped à parte, e o
// motivo do skip sempre cita o PR que entrega a feature. Quando o PR mergear, a sonda passa a
// responder 2xx e o teste roda sozinho — sem editar o spec.
export async function rotaExiste(page: Page, caminho: string): Promise<boolean> {
  // fetch relativo precisa de uma origem: abre a home do domínio se a página ainda está vazia.
  if (!page.url().startsWith('http')) {
    await page.goto('/');
  }
  return page.evaluate(async (alvo) => {
    const resposta = await fetch(alvo, {
      redirect: 'manual',
      cache: 'no-store',
      signal: AbortSignal.timeout(10_000),
    });
    // redirect: 'manual' devolve type 'opaqueredirect' (status 0) para qualquer 3xx.
    return resposta.type !== 'opaqueredirect' && resposta.status >= 200 && resposta.status < 300;
  }, caminho);
}
