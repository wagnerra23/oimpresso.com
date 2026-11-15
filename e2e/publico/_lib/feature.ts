import type { APIRequestContext } from '@playwright/test';

// Sonda de existência de rota para specs que dependem de PR ainda não mergeado.
//
// Uso (no beforeEach ou no próprio teste):
//   test.skip(!(await rotaExiste(request, '/direitos')), 'aguardando PR #10908');
//
// "Existe" = a rota responde 2xx SEM seguir redirect. No main de hoje, qualquer caminho
// desconhecido do domínio da Uniafibro devolve 302 para a home (rota uniafibro.outros), e o
// ERP devolve 404 — os dois contam como ausente.
//
// Regra de honestidade (LC-13): skip NÃO é verde. O relatório conta os skipped à parte, e o
// motivo do skip sempre cita o PR que entrega a feature. Quando o PR mergear, a sonda passa a
// responder 2xx e o teste roda sozinho — sem editar o spec.
export async function rotaExiste(request: APIRequestContext, caminho: string): Promise<boolean> {
  const resposta = await request.get(caminho, { maxRedirects: 0, failOnStatusCode: false });
  const status = resposta.status();
  return status >= 200 && status < 300;
}
