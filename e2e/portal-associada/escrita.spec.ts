import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { test, expect, type Page } from '@playwright/test';

// Área da associada — ações que GRAVAM (itens 12, 14, 15 e 20 do plano E2E). Contrato:
// Modules/Associacoes/Resources/js/Pages/Associacoes/Public/Membro.casos.md.
//
// Só roda no runner do CI, com as associadas que o VisregAssociacoesPortalSeeder cria para cada
// teste (link próprio: a trava e a revogação não vazam para os outros testes). Sem o arquivo de
// fixture — antes do #10880, ou no job noturno contra o site real — tudo se pula. Nunca em prod.

const FIXTURE = 'storage/app/e2e/portal-associada.json';

type Link = { token: string; pin: string; associada_id: number };
type Fixture = { trava?: Link; revoga?: Link; filiacao?: Link };

const fixture: Fixture | null = existsSync(FIXTURE) ? JSON.parse(readFileSync(FIXTURE, 'utf8')) : null;

test.use({ storageState: { cookies: [], origins: [] } });
test.skip(fixture?.trava === undefined, 'aguardando PR #10880 (área da associada) — sem link de teste semeado');

const TRAVADO = 'Acesso travado por 30 minutos depois de 5 tentativas. Se esqueceu o PIN, peça um novo à sua associação.';

async function digitarPin(page: Page, pin: string): Promise<void> {
  for (const n of pin) await page.getByRole('button', { name: `Número ${n}` }).click();
  await page.getByRole('button', { name: 'Entrar' }).click();
}

/** Pedido JSON igual ao que a página faz (CSRF da meta + cookie da sessão do contexto). */
async function pedir(page: Page, metodo: 'POST' | 'DELETE', url: string, data?: unknown) {
  const csrf = (await page.locator('meta[name="csrf-token"]').getAttribute('content')) ?? '';
  const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
  return metodo === 'POST' ? page.request.post(url, { data, headers }) : page.request.delete(url, { headers });
}

/** Linhas de consentimento da associada, lidas no banco do runner (o navegador não vê `revoked_at`). */
function consentimentosNoBanco(associadaId: number): { finalidade: string; revogado: boolean }[] {
  const php = `echo json_encode(DB::table('assoc_consentimentos')->where('business_id', 1)->where('associada_id', ${associadaId})`
    + `->orderBy('id')->get(['finalidade','revoked_at'])->map(fn ($c) => ['finalidade' => $c->finalidade, 'revogado' => $c->revoked_at !== null]));`;
  const saida = execFileSync('php', ['artisan', 'tinker', `--execute=${php}`], { encoding: 'utf8' });
  return JSON.parse(saida.trim().split('\n').pop() ?? '[]');
}

test('12 · PIN errado 5 vezes trava por 30 min, com mensagem genérica', async ({ page }) => {
  const { token, pin } = fixture!.trava!;
  const errado = pin === '0000' ? '1111' : '0000';

  await page.goto(`/associacoes/m/${token}`);
  for (let i = 1; i <= 4; i++) {
    await digitarPin(page, errado);
    await expect(page.getByText(`PIN incorreto. Restam ${5 - i} tentativa(s).`)).toBeVisible();
  }
  await digitarPin(page, errado);
  await expect(page.getByText(TRAVADO)).toBeVisible();

  // Travado, nem o PIN certo entra.
  await digitarPin(page, pin);
  await expect(page.getByText(TRAVADO)).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Meus dados' })).toHaveCount(0);

  // Sem enumeração: a resposta da trava não diz nada sobre a associada, só o estado do link.
  const r = await pedir(page, 'POST', `/associacoes/m/${token}/pin`, { pin });
  expect(r.status()).toBe(423);
  expect(await r.json()).toEqual({ erro: 'bloqueado', tentativas_restantes: 0 });
});

test('14 · retirar autorização grava revoked_at e mantém a linha', async ({ page }) => {
  const { token, pin, associada_id } = fixture!.revoga!;
  const antes = consentimentosNoBanco(associada_id);
  expect(antes.find((c) => c.finalidade === 'whatsapp')?.revogado).toBe(false);

  await page.goto(`/associacoes/m/${token}`);
  await digitarPin(page, pin);
  const item = page.locator('li[data-finalidade="whatsapp"]');
  await item.getByRole('button', { name: 'Retirar autorização' }).click();

  await expect(page.getByText('Autorização retirada.')).toBeVisible();
  await expect(item.getByText('Não autorizado')).toBeVisible();
  await expect(item.getByRole('button', { name: 'Retirar autorização' })).toHaveCount(0);

  // Recarregada, a página continua mostrando a autorização retirada.
  await page.reload();
  await expect(page.locator('li[data-finalidade="whatsapp"]').getByText('Não autorizado')).toBeVisible();

  // No banco: mesmas linhas, só a do WhatsApp marcada.
  const depois = consentimentosNoBanco(associada_id);
  expect(depois.length).toBe(antes.length);
  expect(depois.filter((c) => c.revogado).map((c) => c.finalidade)).toEqual(['whatsapp']);
});

test('15 · "Fazer parte da associação" não se retira pela associada (D5)', async ({ page }) => {
  const { token, pin, associada_id } = fixture!.filiacao!;

  await page.goto(`/associacoes/m/${token}`);
  await digitarPin(page, pin);
  const filiacao = page.locator('li[data-finalidade="associada"]');
  await expect(filiacao.getByText('Fazer parte da associação')).toBeVisible();
  await expect(filiacao.getByRole('button', { name: 'Retirar autorização' })).toHaveCount(0);

  // E pedindo direto, o servidor recusa.
  const r = await pedir(page, 'DELETE', `/associacoes/m/${token}/consentimentos/associada`);
  expect(r.status()).toBe(422);
  expect((await r.json()).erro).toBe('so_pela_associacao');
  expect(consentimentosNoBanco(associada_id).find((c) => c.finalidade === 'associada')?.revogado).toBe(false);
});

// Item 20: a associada ainda não tem linha do tempo de conversa na área dela (o #10880 entrega só
// "Meus dados" e "Minhas autorizações"). Fica como fixme, visível no relatório, até essa onda.
test.fixme('20 · admin abre conversa e a mensagem de sistema aparece na linha do tempo da associada', async ({ page }) => {
  const { token, pin } = fixture!.revoga!;
  await page.goto(`/associacoes/m/${token}`);
  await digitarPin(page, pin);
  await expect(page.getByRole('heading', { name: /conversa/i })).toBeVisible();
});
