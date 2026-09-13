import { test, expect } from '@playwright/test';

// E2E de comportamento — NF-e · NFC-e (`/fiscal/nfe`). Gate G-3 (ADR 0264).
// Irmão de `fiscal-cockpit.spec.ts`, que carrega o porquê dos dois. Resumo: a tela já tem 3
// suítes jsdom e 8 UC provados, e o valor daqui é o resíduo que elas declaram fora do
// próprio alcance — UC-FNFE-14, "a navegação HTTP real entre as rotas, o jsdom não a faz
// (...) o Cockpit não é renderizado (recebe ~15 props de payload)"; UC-FNFE-10, "o jsdom não
// implementa a travessia por Tab do browser". Nada aqui reimplementa asserção de domínio: o
// E2E prova CAMINHO DE TELA, e o oráculo de regra segue nos `casos.md` + Pest.
//
// CONTRATO (lido inteiro): `governance/design/contracts/fiscal-nfe.contract.json` — seção
// `fiscal-nfe-filters` com 4 rótulos (`Todas`, `Autorizadas`, `Rejeitadas`, `Processando`),
// âncora viva em `Nfe.tsx:212`. Ele declara que a copy saiu da TELA VIVA, logo não prova
// FORMA (ADR UI-0029 — ali o protótipo é soberano): serve de âncora de caminho.
//
// Locators RESILIENTES (L-24), zero `waitForTimeout`, testes NÃO rodam local (ADR 0062 — a
// lane é `e2e-gate.yml`), casing legal preservado (`NF-e`/`NFC-e`, nunca "NFe").

test('a lista de NF-e abre com os filtros do contrato, e o chip leva o filtro para a querystring', async ({ page }) => {
  await page.goto('/fiscal/nfe');
  await page.waitForLoadState('networkidle');

  await expect(page.getByRole('heading', { name: 'NF-e · NFC-e' })).toBeVisible({ timeout: 15_000 });

  const filtros = page.locator('[data-contract="fiscal-nfe-filters"]');
  await expect(filtros).toBeVisible();

  // Os 4 chips de status do contrato. O nome acessível de cada um traz o contador colado
  // (`Todas 0`), então a âncora é o começo do rótulo — casar o texto inteiro acoplaria o
  // caso à contagem do tenant, que é dado, não contrato.
  for (const rotulo of ['Todas', 'Autorizadas', 'Rejeitadas', 'Processando']) {
    await expect(
      filtros.getByRole('button', { name: new RegExp(`^${rotulo}`) }),
      `o chip "${rotulo}" do contrato não está na barra de filtros`,
    ).toBeVisible();
  }

  // O eixo que o jsdom não tem: `applyFilters` faz `router.visit('/fiscal/nfe', { data })`
  // (`Nfe.tsx:87`), então o filtro precisa VIAJAR na querystring — é o que torna a visão
  // compartilhável por link e sobrevivente a um F5. Trocado por estado local, a tela continua
  // "funcionando" e este caso cai.
  await filtros.getByRole('button', { name: /^Rejeitadas/ }).click();
  await expect(page).toHaveURL(/[?&]status=rejeitadas\b/);
  await expect(filtros.getByRole('button', { name: /^Rejeitadas/ })).toHaveAttribute('aria-pressed', 'true');
  await expect(filtros.getByRole('button', { name: /^Todas/ })).toHaveAttribute('aria-pressed', 'false');
});

test('UC-FNFE-12: o rodapé de atalhos não anuncia tecla que a tela ignora', async ({ page }) => {
  await page.goto('/fiscal/nfe');
  await page.waitForLoadState('networkidle');

  const atalhos = page.getByRole('region', { name: 'Atalhos de teclado' });
  await expect(atalhos).toBeVisible();

  // As teclas ANUNCIADAS são exatamente as que o `keydown` desta tela trata: `j`/`k` (+ setas)
  // e `Enter` (`Nfe.tsx:97-116`). Asserir a lista COMPLETA, e não a ausência de `R`/`X`, é o
  // que torna o caso discriminante: devolver qualquer tecla morta derruba aqui nomeando a
  // intrusa, enquanto um "não contém R" casaria com qualquer palavra que tenha a letra. As
  // duas mortas saíram em 2026-09-04 — a barra é onde o operador APRENDE as teclas.
  const teclas = (await atalhos.locator('kbd').allInnerTexts()).map((t) => t.trim());
  expect(teclas, 'a barra de atalhos anuncia um conjunto de teclas diferente do que a tela trata').toEqual([
    'J',
    'K',
    '⏎',
  ]);
  await expect(atalhos).not.toContainText('em breve');

  // CONTROLE NEGATIVO, e no browser ele vale mais que no jsdom: os listeners de `window`
  // aqui são os reais. Se `r`/`x` tivessem handler vivo, removê-los da barra teria
  // ESCONDIDO um atalho em vez de descrever a realidade — e este bloco reprovaria.
  const urlAntes = page.url();
  for (const tecla of ['r', 'R', 'x', 'X']) {
    await page.keyboard.press(tecla);
  }
  await expect(page.getByRole('dialog')).toHaveCount(0);
  expect(page.url(), 'uma tecla sem handler anunciado mudou a navegação').toBe(urlAntes);
});

test('UC-FNFE-14: a densidade escolhida atravessa a navegação HTTP entre NF-e e Cockpit', async ({ page }) => {
  // O RESÍDUO declarado do UC-FNFE-14: a suíte jsdom prova que a tela nova LÊ o que a
  // anterior gravou, montando uma depois da outra; ela não faz a troca de página com o
  // Inertia no meio, e não renderiza o Cockpit (que recebe ~15 props de payload). Aqui as
  // duas rotas são visitadas de verdade — a preferência é do OPERADOR, não da tela.
  await page.goto('/fiscal/nfe');
  await page.waitForLoadState('networkidle');

  const densidadeNfe = page.getByRole('radiogroup', { name: 'Densidade da tabela' });
  await expect(densidadeNfe).toBeVisible();

  const compactaNfe = densidadeNfe.getByRole('button', { name: 'Densidade compacta' });
  await compactaNfe.click();
  await expect(compactaNfe).toHaveAttribute('aria-pressed', 'true');

  // Navegação HTTP real para a tela irmã — não remontagem de componente.
  await page.goto('/fiscal');
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('heading', { name: 'Notas Fiscais' })).toBeVisible({ timeout: 15_000 });

  const densidadeCockpit = page.getByRole('radiogroup', { name: 'Densidade da tabela' });
  await expect(
    densidadeCockpit.getByRole('button', { name: 'Densidade compacta' }),
    'o Cockpit ignorou a densidade escolhida na NF-e — a preferência não atravessou a navegação',
  ).toHaveAttribute('aria-pressed', 'true');

  // E o contrário também: a preferência é UMA (`oimpresso.fiscal.densidade`), então escolher no
  // Cockpit tem de valer ao voltar — sem este trecho, chave divergente passaria em meia travessia.
  await densidadeCockpit.getByRole('button', { name: 'Densidade relaxada' }).click();
  await page.goto('/fiscal/nfe');
  await page.waitForLoadState('networkidle');
  await expect(
    page.getByRole('radiogroup', { name: 'Densidade da tabela' }).getByRole('button', { name: 'Densidade relaxada' }),
    'a NF-e ignorou a densidade escolhida no Cockpit — as duas telas usam chaves diferentes',
  ).toHaveAttribute('aria-pressed', 'true');
});

// PENDENTE POR FALTA DE SUJEITO — não por falta de código. Medido em 2026-09-13 (`git grep`
// em `database/seeders/` + `Modules/*/Database/Seeders/`): NENHUM seeder cria `nfe_emissoes`,
// e o `Nfe.casos.md` já registrava o mesmo — "nenhuma lane de hoje tem `nfe_emissoes`". Sem
// emissão o `Deferred` resolve no empty state (`Nfe.tsx:280`) e a `<table>` não existe: não
// há linha para focar nem cursor para mover. Fixture de nota aqui abriria um segundo jeito de
// semear, paralelo ao Pest — então o caso fica `fixme`, com o motivo no lugar do verde.

test.fixme('UC-FNFE-10: Tab alcança a linha, J/K movem o cursor e Enter abre a nota focada', async ({ page }) => {
  await page.goto('/fiscal/nfe');
  await page.waitForLoadState('networkidle');

  const linhas = page.locator('[data-keyboard="true"] tbody tr');
  await expect(linhas.first()).toBeVisible();

  // Travessia física por Tab — o eixo que o jsdom não implementa.
  await linhas.first().focus();
  await expect(linhas.first()).toBeFocused();

  // `J` desce o cursor e `Enter` abre a nota DAQUELA linha, não a primeira da lista.
  await page.keyboard.press('j');
  await page.keyboard.press('Enter');
  await expect(page.getByRole('dialog')).toBeVisible();

  // O anel do Tab é o MESMO cursor do J/K — um anel, não dois (`onFocus` sincroniza).
  await page.keyboard.press('Escape');
  await linhas.nth(1).focus();
  await page.keyboard.press('Enter');
  await expect(page.getByRole('dialog')).toBeVisible();
});
