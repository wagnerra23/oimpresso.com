import { test, expect } from '@playwright/test';

// E2E de comportamento — GOVERNANÇA, painel (thread 02 do playbook de governança).
//
// Contrato: resources/js/Pages/governance/Dashboard.charter.md (a tela ainda NÃO tem
// `casos.md` — é a frente 03 do playbook). Por isso NENHUM título aqui cita `UC-id`:
// inventar um id que não existe no `casos.md` fabricaria âncora, e o `casos-gate` mede
// a direção UC→teste. Quando a 03 criar `Dashboard.casos.md`, os ids entram nos títulos.
//
// Oráculos do lado PHP (não se duplicam aqui, se citam):
//   GovernanceRotasCanGateTest  — o `can:` ARMADO por rota (registry, não texto)
//   InertiaDeferAuditTest       — `Inertia::defer` nas props caras do controller
// O que só o navegador prova, e é o que este arquivo cobre: o redirect de entrada
// acontecendo de fato, o componente que o servidor resolveu, e a tela sobrevivendo à
// resolução das props deferidas — que é o incidente de 2026-05-25 (TypeError em prod).
//
// Locators RESILIENTES (role/text), nunca classe CSS (L-24). Os rótulos de KPI usam
// regex case-insensitive de propósito: `KpiCard` aplica `uppercase` por CSS, e o
// `innerText` do Chromium devolve o texto JÁ transformado.

test('a raiz /governance redireciona 302 pra /ia', async ({ page }) => {
  // Canon [W] 2026-05-22: o entry-point do oimpresso é o hub IA/Jana. É a primeira coisa
  // que quebra numa refatoração de rota, e o `GovernanceRotasCanGateTest` prova por
  // controle negativo que esta rota segue SEM `can:` — quem não tem permission tem que
  // receber o redirect, não um 403.
  const resposta = await page.request.get('/governance', { maxRedirects: 0 });

  expect(resposta.status()).toBe(302);
  // O destino importa: um 302 pro /login também seria 302, e passaria por acidente.
  expect(resposta.headers()['location'] ?? '').toContain('/ia');
});

test('/governance/dashboard renderiza o componente governance/Dashboard', async ({ page }) => {
  // O painel legado tem endereço próprio (`governance.admin.dashboard.legacy`).
  // O nome do componente vem do payload que o SERVIDOR resolveu (`data-page` do
  // `@inertia`) — é o contrato literal do caso, e o `g` minúsculo é o caminho REAL
  // (exceção declarada ao `Pages/<Mod>/` do resto do app; não normalizar).
  await page.goto('/governance/dashboard');
  await page.waitForLoadState('networkidle');

  // `exact: true` + `level: 1` não é zelo: o `name` do getByRole casa por SUBSTRING, e esta
  // tela tem 3 headings contendo "Governança" (h1 "Governança", h2 "Governança MCP", h3
  // "Atalhos de governança") — sem isso o locator é ambíguo e o strict mode reprova.
  await expect(page.getByRole('heading', { name: 'Governança', exact: true, level: 1 })).toBeVisible({ timeout: 15_000 });

  // O nome do componente é lido da RESPOSTA DO SERVIDOR, não do DOM já hidratado.
  // Medido no run 34270197342: `#app[data-page]` expira em 30s enquanto o h1 acima
  // aparece normalmente — com o cliente Inertia 3.0.3 o atributo não sobrevive no DOM
  // depois do mount. O `data-page` do HTML servido é a fonte que interessa de todo
  // jeito: ele é o que o SERVIDOR resolveu, e não muda com o que o cliente faz depois.
  // Não se compara a STRING crua do HTML: o `json_encode` do PHP escapa a barra
  // (o atributo traz `governance` + barra-escapada + `Dashboard`) e o Blade escapa as
  // aspas como `&quot;`. Medido no run 34271194228, que reprovou por isso. Então
  // extrai-se o atributo, desfaz-se o escape de entidades e compara-se o CAMPO, com
  // igualdade exata — o `JSON.parse` já normaliza a barra escapada.
  const html = await (await page.request.get('/governance/dashboard')).text();
  const atributo = html.match(/data-page="([^"]+)"/);
  expect(atributo, 'o HTML servido deveria carregar o data-page do Inertia').not.toBeNull();

  const pagina = JSON.parse(
    atributo![1].replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&amp;/g, '&'),
  );
  expect(pagina.component).toBe('governance/Dashboard');
});

test('o painel resolve as props deferidas sem exceção de runtime', async ({ page }) => {
  // Incidente literal de 2026-05-25 na tela de origem: as props eram desestruturadas
  // direto e deu `TypeError undefined.find` EM PRODUÇÃO. O anti-hook do charter proíbe
  // consumir a prop `mcp` fora do `<Deferred>`. Aqui a prova é pela CONSEQUÊNCIA — uma
  // exceção não-tratada na página —, não pela presença do wrapper no fonte: quem mede
  // presença mede o disco, e o incidente foi de runtime.
  //
  // Este spec OBSERVA. Se ficar vermelho, o conserto é do dono da tela — não daqui.
  const excecoes: string[] = [];
  page.on('pageerror', (erro) => excecoes.push(erro.message));

  await page.goto('/governance/dashboard');
  // Sincronismo por REDE (o `<Deferred>` dispara um request próprio), nunca por relógio.
  await page.waitForLoadState('networkidle');

  // A tela continua de pé depois que o deferido chegou. (`exact`+`level` pelo mesmo
  // motivo do teste acima: 3 headings desta tela contêm "Governança".)
  await expect(page.getByRole('heading', { name: 'Governança', exact: true, level: 1 })).toBeVisible();
  expect(excecoes, `exceções de runtime na página: ${excecoes.join(' | ')}`).toEqual([]);
});

test('o KPI de conformidade da Constituição aparece com a régua que a tela declara', async ({ page }) => {
  // MEDIDO em 2026-09-08, e é menos do que o playbook supunha: a tela mostra
  // `label="Compliance Constitution"` + `description="v1.1.0 — próx revisão {data}"`.
  // O valor é a soma LITERAL `(7 * 10) + (2 * 5) + 0` = 80, escrita à mão no
  // DashboardController (linhas 65 e 268) — não é apurado de fonte nenhuma.
  // O `80%` NÃO é fixado aqui de propósito: pinar a constante transformaria uma
  // decisão de régua ([W]) em teste vermelho. O que se trava é o CONTRATO da tela.
  await page.goto('/governance/dashboard');
  await page.waitForLoadState('networkidle');

  await expect(page.getByText(/compliance constitution/i)).toBeVisible({ timeout: 15_000 });
  await expect(page.getByText(/próx revisão/i).first()).toBeVisible();
});

test.fixme('o número auto-declarado se apresenta como auto-declarado', async ({ page }) => {
  // PENDENTE porque a copy NÃO EXISTE — medido, não suposto:
  //   rg "auto-declarad|autodeclarad" Dashboard.tsx  → 0 ocorrências
  //   rg "pleno|parcia|régua"          Dashboard.tsx → 0 ocorrências relevantes
  // O playbook (§5 item 3 do 00-INDICE.md) afirma "a UI rotula 'auto-declarado' e
  // mostra a régua". Hoje ela NÃO rotula: exibe `80%` com rótulo neutro, do mesmo jeito
  // que exibiria um número apurado. Escrever o assert vermelho seria transformar um
  // achado em ruído de CI; escrever a copy seria consertar a tela, e este spec observa.
  // Fica registrado no _saida-02.md como achado, para decisão [W].
  await page.goto('/governance/dashboard');
  await expect(page.getByText(/auto-declarado/i)).toBeVisible();
  await expect(page.getByText(/7 plenos/i)).toBeVisible();
});
