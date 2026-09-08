import { test, expect } from '@playwright/test';

// E2E de comportamento — GOVERNANÇA, políticas (thread 02 do playbook de governança).
//
// Contrato: resources/js/Pages/governance/Policies.charter.md (a tela ainda NÃO tem
// `casos.md` — frente 03a do playbook). Sem `UC-id` nos títulos pelo mesmo motivo do
// spec irmão: id que não existe no `casos.md` é âncora fabricada.
//
// Oráculos do lado PHP: GovernanceRotasCanGateTest (o `can:` e o `throttle:10,1`
// ARMADOS na rota) e PoliciesToggleTest (o toggle grava `enabled` + `updated_at`).
//
// ⚠️ ESTADO DA LANE, medido em 2026-09-08 e não suposto: NENHUM seeder popula
// `mcp_governance_rules` (varredura no repo inteiro: 0 seeders). A tabela existe no
// schema-squash, mas nasce VAZIA — então a tela cai no `<EmptyState>` "Sem rules ainda"
// e não há switch para alternar. Os casos que dependem de uma regra existir usam
// `test.skip` explícito com a razão (o README deste diretório: skip explícito, NUNCA
// falso-verde) e passam a provar sozinhos no dia em que houver seed.
//
// Locators RESILIENTES (role/text), nunca classe CSS (L-24).

test('a tela de políticas abre com os 4 KPIs do MVP', async ({ page }) => {
  // A rota exige `can:governance.dashboard.view` desde a ADR 0392 §D-D passo 2.
  await page.goto('/governance/policies');
  await page.waitForLoadState('networkidle');

  await expect(page.getByRole('heading', { name: 'Policies (Governança)' })).toBeVisible({ timeout: 15_000 });

  // Regex case-insensitive: `KpiCard` aplica `uppercase` por CSS e o `innerText` do
  // Chromium devolve o texto já transformado.
  for (const rotulo of [/rules total/i, /ativas/i, /triggered total/i, /categorias/i]) {
    await expect(page.getByText(rotulo).first()).toBeVisible();
  }
});

test('alternar uma política não abre modal de confirmação', async ({ page }) => {
  // O charter lista modal de confirmação como ANTI-PADRÃO explícito: o toggle é
  // reversível, e o modal só atrita. Canon = ação direta + flash.
  await page.goto('/governance/policies');
  await page.waitForLoadState('networkidle');

  const interruptores = page.getByRole('switch');
  const quantas = await interruptores.count();

  test.skip(
    quantas === 0,
    'sem regra em mcp_governance_rules nesta lane — nenhum seeder a popula (medido 2026-09-08). '
      + 'O caso ativa sozinho quando houver seed; marcar verde agora seria afirmar cobertura inexistente.',
  );

  await interruptores.first().click();
  // Nem no clique, nem depois que a resposta do POST voltou.
  await expect(page.getByRole('dialog')).toHaveCount(0);
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('dialog')).toHaveCount(0);
});

test.fixme('a tela avisa que alternar não deixa rastro', async ({ page }) => {
  // PENDENTE porque a copy NÃO EXISTE — medido, não suposto:
  //   rg "rastro|históric" Policies.tsx + Policies.charter.md → 0 ocorrências.
  // O playbook pede este aviso porque `mcp_governance_rule_history` não existe (§5
  // item 4 do 00-INDICE.md) — alternar uma política de enforcement de runtime hoje é
  // uma mudança SEM trilha. O charter até lista "toggle sem registrar histórico" como
  // anti-padrão, mas a tela não conta isso a quem opera. Escrever a copy é consertar a
  // tela, e este spec observa: vai pro _saida-02.md como achado, decisão [W].
  await page.goto('/governance/policies');
  await expect(page.getByText(/não deixa rastro/i)).toBeVisible();
});

test.fixme('o toggle respeita o throttle de 10 por minuto', async ({ page }) => {
  // PENDENTE por ESCOLHA DE ORÁCULO, não por falta de seed. Provar `throttle:10,1` no
  // navegador exige disparar 11 POSTs reais de toggle — 11 escritas numa tabela de
  // enforcement, para medir uma propriedade que é do REGISTRY de rotas e que o
  // `GovernanceRotasCanGateTest` já lê do `gatherMiddleware()` (o lugar certo: o
  // registry vivo, não o texto do routes.php, e sem efeito colateral).
  // Registrado aqui para que a ausência seja legível, não silenciosa.
  await page.goto('/governance/policies');
});

test.fixme('usuário sem governance.policies.edit não consegue alternar', async ({ page }) => {
  // PENDENTE por BLOQUEIO ESTRUTURAL conhecido — D-GATE, e não é defeito deste spec.
  // Duas razões independentes, cada uma suficiente:
  //  (1) `AuthServiceProvider` registra um `Gate::before` que devolve `true` para a role
  //      `Admin#{business_id}` em qualquer ability fora de backup/superadmin/manage_modules.
  //      O `can:` da rota barra o não-admin sem a permission, e NÃO barra o admin de um
  //      business. O próprio `Modules/Governance/Http/routes.php` declara isso, e o
  //      `GovernanceRotasCanGateTest` repete no docblock: "o que ele NÃO prova".
  //  (2) A lane E2E loga por `E2E_BYPASS_LOGIN_ID=1` — o admin do VisregTenantSeeder,
  //      role `Admin#1`. É o ÚNICO usuário disponível, e ele passa pelo Gate::before.
  //      Montar o negativo exigiria seed novo ou mexer no AuthServiceProvider: os dois
  //      fora do prefixo desta thread.
  // Fechar aquele caminho é o passo 1 da ADR 0392 (conflito A×B da CONCESSÃO),
  // decisão [W] em aberto — thread 05 do playbook, bloqueada.
  await page.goto('/governance/policies');
  await expect(page.getByRole('switch')).toHaveCount(0);
});
