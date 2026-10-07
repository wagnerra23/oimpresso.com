import { test, expect } from '@playwright/test';

// E2E — "Configurar pelo certificado" (`/nfe-brasil/tributacao`). Gate G-3 (ADR 0264).
// Playbook Fiscal thread 22 · UC-NFTR-18 (`resources/js/Pages/NfeBrasil/Tributacao/Index.casos.md`).
//
// A leitura da empresa (`GET /nfe-brasil/tributacao/empresa-fiscal`, thread 21) é MOCKADA:
// ela consulta SEFAZ e BrasilAPI, e o caso aqui é o caminho de tela, não a rede externa. O
// backend da leitura tem prova própria (EmpresaFiscalLookupTest · UC-NFTR-14/16).
// O POST de aplicar é só CONTADO — este caso nunca aplica (não muda a config do tenant).
//
// Locators resilientes (L-24), zero `waitForTimeout`, não roda local (ADR 0062).

const LEITURA = (regime: string | null) => ({
  campos: {
    cnpj: { valor: '00000000000191', fonte: 'certificado' },
    uf: { valor: 'SP', fonte: 'sefaz' },
    razao_social: { valor: 'EMPRESA TESTE LTDA', fonte: 'sefaz' },
    ie: { valor: null, fonte: null, motivo: 'env_homolog' },
    situacao: { valor: null, fonte: null, motivo: 'env_homolog' },
    cnaes: { valor: ['1813-0/01'], fonte: 'brasilapi' },
    regime: {
      valor: null, fonte: null, divergente: true,
      opcoes: [{ valor: 'simples', fonte: 'brasilapi' }, { valor: 'normal', fonte: 'sefaz' }],
    },
  },
  sugestoes: [
    { slug: 'industria-grafica-simples-sp', titulo: 'Indústria gráfica · Simples · SP', regime: 'simples', uf: 'SP',
      aderencia: { pontos: regime === 'simples' ? 6 : 3 } },
    { slug: 'industria-grafica-presumido-sp', titulo: 'Indústria gráfica · Presumido · SP', regime: 'lucro_presumido', uf: 'SP',
      aderencia: { pontos: 2 } },
  ],
});

test('UC-NFTR-18 · onboarding não avança sem regime e NCM', async ({ page }) => {
  let posts = 0;
  await page.route('**/nfe-brasil/tributacao/empresa-fiscal**', (route) => {
    const regime = new URL(route.request().url()).searchParams.get('regime');
    return route.fulfill({ json: LEITURA(regime) });
  });
  await page.route('**/nfe-brasil/tributacao/templates/*/aplicar', (route) => {
    posts += 1;
    return route.abort();
  });

  await page.goto('/nfe-brasil/tributacao');
  await page.getByRole('button', { name: 'Configurar pelo certificado' }).click();

  const drawer = page.getByRole('dialog', { name: 'Configurar pelo certificado' });
  await expect(drawer).toBeVisible();
  const continuar = drawer.getByRole('button', { name: 'Continuar' });
  const aplicar = drawer.getByRole('button', { name: 'Aplicar template' });

  // Passo 1 → 2. "Aplicar" não existe antes do passo 4.
  await expect(drawer.getByText('EMPRESA TESTE LTDA')).toBeVisible();
  await expect(aplicar).toHaveCount(0);
  await continuar.click();

  // Passo 2: regime divergente e não escolhido → não avança.
  await expect(drawer.getByText('As fontes divergem. Confirme com o contador:')).toBeVisible();
  await expect(continuar).toBeDisabled();
  await expect(aplicar).toHaveCount(0);
  await drawer.getByRole('radio', { name: /Simples Nacional/ }).check();
  await expect(continuar).toBeEnabled();
  await continuar.click();

  // Passo 3: NCM vazio e 00000000 não avançam; 8 dígitos válidos avançam.
  const ncm = drawer.getByPlaceholder('8 dígitos');
  await expect(continuar).toBeDisabled();
  await ncm.fill('00000000');
  await expect(continuar).toBeDisabled();
  await ncm.fill('4911109');
  await expect(continuar).toBeDisabled();
  await ncm.fill('49111090');
  await expect(continuar).toBeEnabled();
  await expect(aplicar).toHaveCount(0);
  await continuar.click();

  // Passo 4: o resumo mostra regime, template e NCM — e só aqui existe "Aplicar".
  await expect(drawer.getByText('Vai ser aplicado')).toBeVisible();
  await expect(drawer.getByText('Simples Nacional', { exact: true })).toBeVisible();
  await expect(drawer.getByText('Indústria gráfica · Simples · SP')).toBeVisible();
  await expect(drawer.getByText('49111090')).toBeVisible();
  await expect(aplicar).toBeVisible();

  // Controle: fechar sem clicar em "Aplicar" não grava nada.
  await page.keyboard.press('Escape');
  await expect(drawer).toHaveCount(0);
  expect(posts, 'fechar o drawer disparou o POST de aplicar').toBe(0);
});
