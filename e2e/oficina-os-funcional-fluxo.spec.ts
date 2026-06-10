import { test, expect, type Page } from '@playwright/test';

// E2E de comportamento — OS FUNCIONAL fim-a-fim (PACOTE QUALIDADE-9 PR-1 · ADR 0264 G-3).
// Fonte do contrato: resources/js/Pages/OficinaAuto/ProducaoOficina/Index.casos.md
//   UC-11 (caminho da Larissa): criar OS (cliente+veículo) → DVI com semáforo → foto no
//   laudo → pedir aprovação → avançar etapa (executar) → imprimir A4.
//
// A APROVAÇÃO DO CLIENTE em si acontece fora da tela (link público + PIN via WhatsApp,
// rota /aprovar-os/{token} — AprovacaoOsController). Aqui provamos o lado Larissa:
// o gate muda pra "Aguardando aprovação" e a execução avança via gate de etapa
// (override de responsável registrado na trilha quando a checklist bloqueia).
//
// Locators RESILIENTES — label/role/text que já EXISTEM nos componentes. NUNCA classe
// CSS (L-24 / _PROPOSTA-0244). ZERO edição nas telas vivas (charter-protected).
//
// Auto-suficiente em dados: cria o veículo e a OS via UI (o seed mínimo VisregTenantSeeder
// só tem o contato "Walk-In Customer" biz=1 — NÃO mexemos em seeder, Tier 0).

const PLACA = `QA9${String(Date.now()).slice(-4)}`; // única por run; DB do CI nasce zerado

test.describe.serial('UC-11 · OS funcional fim-a-fim (caminho da Larissa)', () => {
  test.setTimeout(180_000);

  test('UC-11 · criar veículo → criar OS → DVI semáforo → foto → pedir aprovação → avançar → imprimir', async ({ page }) => {
    // Stub de window.print em TODOS os frames (o print real dispara no iframe oculto
    // via contentWindow.print() — headless não tem diálogo de impressão).
    await page.addInitScript(() => {
      window.print = () => {
        (window as unknown as { __printCalled?: boolean }).__printCalled = true;
      };
    });

    // ── 1. Cadastrar o veículo que chegou no balcão ────────────────────────────
    await page.goto('/oficina-auto/veiculos/create');
    await page.getByLabel(/Placa principal/i).fill(PLACA);
    await page.locator('#vehicle_type').click();
    await page.getByRole('option').first().click();
    await page.getByRole('button', { name: /Salvar veículo/i }).click();
    await page.waitForLoadState('networkidle');

    // ── 2. Criar a OS (veículo + tipo + cliente) ───────────────────────────────
    await page.goto('/oficina-auto/ordens-servico/create');

    // Veículo (combobox shadcn Command)
    await page.getByRole('combobox').filter({ hasText: /Selecione um veículo/i }).click();
    await page.getByPlaceholder(/Buscar por placa ou tipo/i).fill(PLACA);
    await page.getByRole('option', { name: new RegExp(PLACA, 'i') }).first().click();

    // Tipo de OS = Mecânica (usa o quadro de etapas Recepção → Pronto p/ retirar)
    await page.locator('#order_type').click();
    await page.getByRole('option', { name: /Mecânica/i }).click();

    // Cliente (gate de aprovação WhatsApp precisa de destinatário)
    const clientePicker = page.getByRole('combobox').filter({ hasText: /Selecione o cliente/i });
    test.skip(
      (await clientePicker.count()) === 0,
      'Campo Cliente ausente (controller não enviou contacts — seed sem contato biz=1).',
    );
    await clientePicker.click();
    await page.getByPlaceholder(/Buscar cliente/i).fill('Walk');
    await page.getByRole('option', { name: /Walk-In Customer/i }).first().click();

    await page.getByLabel(/Defeito \/ Observações/i).fill('E2E UC-11 — barulho no freio dianteiro.');
    await page.getByRole('button', { name: /Criar OS/i }).click();
    await page.waitForLoadState('networkidle');

    // ── 3. Achar o card no kanban e abrir o documento vivo (drawer) ────────────
    await page.goto('/oficina-auto/producao-oficina');
    await page.waitForLoadState('networkidle');
    const busca = page.getByPlaceholder(/Buscar OS, ve[ií]culo ou cliente/i);
    await expect(busca).toBeVisible();
    await busca.fill(PLACA);

    const recepcao = page.getByLabel('Coluna Recepção');
    await expect(recepcao).toBeVisible();
    const card = recepcao.getByRole('button').filter({ hasText: new RegExp(PLACA, 'i') }).first();
    await expect(card, `card da OS recém-criada (${PLACA}) deve aparecer em Recepção`).toBeVisible();
    await card.click();

    // Drawer rico aberto — seções canônicas do charter visíveis.
    const drawer = page.getByRole('dialog');
    await expect(drawer.getByText('Fotos & Laudo')).toBeVisible();

    // ── 4. Vistoria DVI com semáforo 1-toque (OS-V2-2) ─────────────────────────
    await drawer.getByRole('button', { name: /Adicionar primeiro item|^Item$/ }).first().click();
    await drawer.getByLabel('Sistema a vistoriar').click();
    await page.getByRole('option').first().click();
    // Severidade "Atenção" no semáforo (radiogroup do item novo)
    await drawer
      .getByRole('radiogroup', { name: /Severidade de novo item/i })
      .getByRole('radio', { name: 'Atenção' })
      .click();
    await drawer.getByRole('button', { name: 'Confirmar item' }).click();
    // Item entra na lista editável com semáforo próprio
    await expect(drawer.getByLabel('Sistema vistoriado').first()).toBeVisible();

    // ── 5. Foto no laudo (OS-V2-1 — upload real via Modules/Arquivos) ──────────
    const pngPixel = Buffer.from(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
      'base64',
    );
    await drawer
      .locator('input[type="file"][accept*="image/jpeg"]')
      .setInputFiles({ name: 'uc11-laudo.png', mimeType: 'image/png', buffer: pngPixel });
    await expect(
      drawer.getByLabel(/Ampliar foto|Legenda da foto/).first(),
      'foto enviada deve aparecer no grid do laudo',
    ).toBeVisible({ timeout: 30_000 });

    // ── 6. Pedir aprovação do cliente (OS-V2-3 — gate hero) ────────────────────
    await drawer.getByRole('button', { name: /Pedir aprovação/i }).click();
    await expect(drawer.getByText(/Aguardando aprovação/i)).toBeVisible({ timeout: 15_000 });

    // ── 7. Executar: avançar a etapa pelo gate (checklist ou override) ─────────
    // Caminho feliz: CTA "Avançar p/ …" habilitado. Checklist bloqueando (ex.: aprovação
    // pendente — o cliente aprova por WhatsApp fora desta tela): override de responsável,
    // registrado na trilha (contrato do ServiceOrderStageGate).
    const avancar = drawer.getByRole('button', { name: /^Avançar p\// });
    const override = drawer.getByRole('button', { name: /Avançar mesmo assim/i });
    if (await avancar.isEnabled().catch(() => false)) {
      await avancar.click();
    } else {
      await expect(override, 'sem CTA habilitado, o override de responsável deve existir').toBeVisible();
      await override.click();
    }
    await expect(page.getByText(/Etapa avançada/i).first()).toBeVisible({ timeout: 15_000 });

    // Timeline auditável (OS-V2-4) registra a transição (quem · quando · de→pra).
    await expect(drawer.getByText('Linha do tempo')).toBeVisible();

    // ── 8. Imprimir A4 (iframe oculto + print pelo parent) ─────────────────────
    await drawer.getByRole('button', { name: /Imprimir OS/i }).click();
    await expect(page.locator('#service-order-print-iframe')).toBeAttached({ timeout: 15_000 });
    await expect(page.getByText(/Falha ao gerar impressão/i)).toHaveCount(0);
  });
});
