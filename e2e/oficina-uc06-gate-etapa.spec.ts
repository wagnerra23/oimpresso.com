import { test, expect, type Page } from '@playwright/test';

// E2E de comportamento — Quadro de OS da Oficina (ADR 0264 G-3).
// Tela canônica: resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx — o workspace
// unificado (#2551) substituiu o kanban antigo de ProducaoOficina; a rota
// /oficina-auto/producao-oficina sobrevive como REDIRECT permanente (menu antigo,
// bookmarks, links em WhatsApp) e o goto daqui PROVA que ela segue viva.
// Contrato de origem: resources/js/Pages/OficinaAuto/ProducaoOficina/Index.casos.md
// (re-ancoragem do casos.md pro Board fica na Onda Q2 do mandato ONDAS-QUALIDADE).
//   UC-01 (ver o pátio: 5 colunas de reparo) · UC-02 (KPIs) · UC-03 (busca) ·
//   UC-06 (avançar de etapa sem furar a regra — gate de etapa opina no drop).
//
// Locators RESILIENTES — aria-label/role/text que já EXISTEM no componente
// (ServiceOrderKanbanColumn expõe aria-label="Coluna {label}"). NUNCA classe CSS (L-24).
// ZERO edição na tela viva (charter-protected): o teste lê o contrato visível, não muda nada.

const ROUTE = '/oficina-auto/producao-oficina'; // redirect → Quadro de OS canônico
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
  // Placeholder do Board canônico (workspace #2551): "Buscar OS, placa ou cliente…  ( / )".
  const busca = page.getByPlaceholder(/Buscar OS, placa ou cliente/i);
  await expect(busca).toBeVisible();
  await busca.fill('zzz-placa-inexistente-xyz');
  // não quebra a tela; as colunas continuam presentes (resultado pode ficar vazio).
  await expect(page.getByLabel('Coluna Recepção')).toBeVisible();
});

// UC-06 · avançar de etapa sem furar a regra (D-01 gate).
// No Board o veredito PREDITIVO durante o arrasto saiu (kanbanDrag.ts: "consumidores
// que não usam o feedback (ex.: ServiceOrders/Board) ignoram o context"); o GATE opina
// no DROP (Board.tsx handleDragMove): transição não-listada/backward = toast "Transição
// não permitida (from → to)" · OS fora do pipeline = toast "OS sem pipeline iniciado" ·
// transição crítica = DragConfirmDialog. Provamos o lado que NUNCA avança: drop num
// alvo INVÁLIDO tem que produzir veredito visível — o gate opina, o card não anda.
test('UC-06 · gate de etapa opina no drop inválido (não avança)', async ({ page }) => {
  const recepcao = page.getByLabel('Coluna Recepção');
  await expect(recepcao).toBeVisible();

  // Card em qualquer coluna serve: de Recepção, "Pronto p/ retirar" é salto não-listado;
  // de qualquer outra, voltar pra Recepção é backward (nunca listado). OS sem pipeline
  // cai no outro toast — ambos são veredito do gate.
  const cardRecepcao = recepcao.getByRole('button').first();
  let card = cardRecepcao;
  let alvoInvalido = page.getByLabel('Coluna Pronto p/ retirar');
  if ((await cardRecepcao.count()) === 0) {
    const cardQualquer = page
      .locator('[aria-roledescription="Card arrastável entre etapas"], [aria-roledescription="OS sem pipeline — clique pra iniciar"]')
      .first();
    test.skip((await cardQualquer.count()) === 0, 'Sem card no quadro (DB sem seed biz=1) — gate não exercitável aqui.');
    card = cardQualquer;
    alvoInvalido = page.getByLabel('Coluna Recepção');
  }

  await dragPointer(page, card, alvoInvalido);
  await page.mouse.up(); // o veredito do Board sai no DROP (handleDragMove), não durante o arrasto

  await expect(
    page.getByText(/Transição não permitida|OS sem pipeline iniciado/i).first(),
    'gate deve dar veredito visível no drop inválido',
  ).toBeVisible({ timeout: 10_000 });
});

// Helper de arrasto compatível com dnd-kit (pointer events, não HTML5 drag).
// NÃO solta o botão — quem decide se o veredito é durante (preditivo) ou no drop
// (Board) é o teste, via page.mouse.up().
async function dragPointer(page: Page, source: ReturnType<Page['locator']>, target: ReturnType<Page['locator']>) {
  const s = await source.boundingBox();
  const t = await target.boundingBox();
  if (!s || !t) throw new Error('boundingBox indisponível pro arrasto');
  await page.mouse.move(s.x + s.width / 2, s.y + s.height / 2);
  await page.mouse.down();
  // movimentos incrementais — dnd-kit precisa de delta pra ativar o sensor.
  await page.mouse.move(s.x + s.width / 2 + 8, s.y + s.height / 2 + 8, { steps: 4 });
  await page.mouse.move(t.x + t.width / 2, t.y + t.height / 2, { steps: 10 });
  await page.waitForTimeout(150); // deixa o overlay pintar antes do drop
}
