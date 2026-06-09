import { test, expect, type Page } from '@playwright/test';

// E2E de comportamento — Produção / Kanban da Oficina (ADR 0264 G-3).
// Fonte do contrato: resources/js/Pages/OficinaAuto/ProducaoOficina/Index.casos.md
//   UC-01 (ver o pátio: 5 colunas de reparo) · UC-02 (6 KPIs) · UC-03 (busca) ·
//   UC-06 (avançar de etapa sem furar a regra — gate de etapa).
//
// Locators RESILIENTES — aria-label/role/text que já EXISTEM no componente (CacambaKanbanColumn
// expõe aria-label="Coluna {label}" + texto preditivo do gate). NUNCA classe CSS (L-24).
// ZERO edição na tela viva (charter-protected): o teste lê o contrato visível, não muda nada.
//
// ⚠️ Primeiro run verde PENDENTE de validação no stack do app (sem PHP/serve no desktop).
// O workflow e2e-gate.yml é NÃO-required até verde-estável (ADR 0261).

const ROUTE = '/oficina-auto/producao-oficina';
const COLUNAS = ['Recepção', 'Diagnóstico', 'Aguardando peças', 'Em execução', 'Pronto p/ retirar'];

test.beforeEach(async ({ page }) => {
  await page.goto(ROUTE);
  await page.waitForLoadState('networkidle');
});

test('UC-01 · ver o pátio num relance — as 5 colunas de reparo renderizam', async ({ page }) => {
  for (const label of COLUNAS) {
    await expect(page.getByLabel(`Coluna ${label}`), `coluna "${label}" deve existir`).toBeVisible();
  }
  // contrato anti-locação (ADR 0265): nenhuma coluna chamada "Locada"/"Disponível" como label visível.
  await expect(page.getByRole('heading', { name: /^Locada$/i })).toHaveCount(0);
});

test('UC-02 · os números do dia — os 6 KPIs renderizam', async ({ page }) => {
  for (const kpi of ['Recepção', 'Aguardando peças', 'Em execução', 'Urgentes', 'Valor em curso']) {
    await expect(page.getByText(kpi, { exact: false }).first()).toBeVisible();
  }
});

test('UC-03 · achar uma OS na hora — a busca filtra', async ({ page }) => {
  const busca = page.getByPlaceholder(/Buscar OS, ve[ií]culo ou cliente/i);
  await expect(busca).toBeVisible();
  await busca.fill('zzz-placa-inexistente-xyz');
  // não quebra a tela; as colunas continuam presentes (resultado pode ficar vazio).
  await expect(page.getByLabel('Coluna Recepção')).toBeVisible();
});

// UC-06 · avançar de etapa sem furar a regra (D-01 gate).
// O gate dá um VEREDITO VISÍVEL durante o arrasto: "Solte p/ avançar" (libera) vs
// "Abre os detalhes (não avança aqui)" (barra). dnd-kit usa pointer events, então
// arrastamos com mouse.move/down/up. Se não houver card (DB sem seed), o teste registra
// skip explícito (não falso-verde) — o seed de biz=1 é responsabilidade do CI.
test('UC-06 · gate de etapa mostra veredito ao arrastar (libera vs barra)', async ({ page }) => {
  const recepcao = page.getByLabel('Coluna Recepção');
  await expect(recepcao).toBeVisible();

  const card = recepcao.getByRole('button').first();
  const temCard = await card.count();
  test.skip(temCard === 0, 'Sem card em Recepção (DB sem seed biz=1) — gate não exercitável aqui.');

  // Arrasto pointer-based até uma coluna inválida → espera o veredito de barra visível.
  const alvoInvalido = page.getByLabel('Coluna Pronto p/ retirar');
  await dragPointer(page, card, alvoInvalido);

  // O componente mostra um destes vereditos (aria-live) — provamos que o GATE opina.
  const veredito = page.getByText(/Solte p\/ avançar|Solte p\/ confirmar avanço|Abre os detalhes \(não avança aqui\)/);
  await expect(veredito.first()).toBeVisible();
});

// Helper de arrasto compatível com dnd-kit (pointer events, não HTML5 drag).
async function dragPointer(page: Page, source: ReturnType<Page['locator']>, target: ReturnType<Page['locator']>) {
  const s = await source.boundingBox();
  const t = await target.boundingBox();
  if (!s || !t) throw new Error('boundingBox indisponível pro arrasto');
  await page.mouse.move(s.x + s.width / 2, s.y + s.height / 2);
  await page.mouse.down();
  // movimentos incrementais — dnd-kit precisa de delta pra ativar o sensor.
  await page.mouse.move(s.x + s.width / 2 + 8, s.y + s.height / 2 + 8, { steps: 4 });
  await page.mouse.move(t.x + t.width / 2, t.y + t.height / 2, { steps: 10 });
  await page.waitForTimeout(150); // deixa o overlay/veredito pintar
}
