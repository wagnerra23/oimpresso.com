// Teste do leitor de pedidos e decisões dos *.casos.md (node --test).
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { lerArquivo, lerRepositorio, tituloDoBullet } from './leitor.mjs';
import { ucScanRe } from '../lib/uc-regex.mjs';

const SUBS = [
  '  - Pedido: P-001',
  '  - Âncora: `UC-FLX-28` · US-PCP-025',
  '  - Fazer: Levar o sinal para a consulta.',
  '  - Hoje: part | Em parte | A OS já espera o sinal',
  '  - Comunicação visual: ok | Sim | sinal em serviço sob encomenda',
  '  - Clientes: Ecopias, Fixar',
  '  - Etiquetas: valor, part, cv',
].join('\n');

test('as seis grafias de backlog em uso dão o mesmo título', () => {
  const grafias = [
    '- **[BACKLOG] Sinal de entrada na linha** `[V0]` — detalha o sinal',
    '- **[BACKLOG]** Sinal de entrada na linha (pedido de 2026-10-23) `[V0]` — Dado',
    '- [BACKLOG] Sinal de entrada na linha (pedido de 2026-10-23) `[V0]` — Dado',
    '- `[BACKLOG]` **Sinal de entrada na linha** `[V0]` — Dado',
    '- **[BACKLOG · ⬜ sem teste] Sinal de entrada na linha** (pedido de 2026-10-23) `[V0]` — Dado',
    '- **Sinal de entrada na linha** (pedido de 2026-10-27, US-PROD-032) `[V0]` — Dado',
  ];
  for (const g of grafias) assert.equal(tituloDoBullet(g), 'Sinal de entrada na linha', g);
});

test('bullet com Pedido vira pedido com todos os campos; bullet sem Pedido é ignorado', () => {
  const md = [
    '## Backlog', '',
    '- **[BACKLOG] Sinal de entrada na linha** `[V0]` — detalha o sinal pago e o saldo.',
    '  continua a prosa do autor.',
    SUBS,
    '- **[BACKLOG] Outro caso sem pedido** — não entra.',
  ].join('\n');
  const { pedidos } = lerArquivo(md, 'resources/js/Pages/Sells/Index.casos.md');
  assert.equal(pedidos.length, 1);
  const p = pedidos[0];
  assert.equal(p.id, 'P-001');
  assert.equal(p.estagio, 'pedido');
  assert.equal(p.linha, 3);
  assert.deepEqual(p.marcas, ['V0']);
  assert.deepEqual(p.clientes, ['Ecopias', 'Fixar']);
  assert.deepEqual(p.tags, ['valor', 'part', 'cv']);
  assert.deepEqual(p.hoje, { tipo: 'part', texto: 'Em parte', nota: 'A OS já espera o sinal' });
  assert.equal(p.ancora, '`UC-FLX-28` · US-PCP-025');
  assert.equal(p.texto, 'detalha o sinal pago e o saldo. continua a prosa do autor.');
});

test('pedido que virou UC: estágio uc, e testado só com Status ✅', () => {
  const md = [
    '## UC-DRAFT-05 · Rascunho com margem abaixo do mínimo só vira venda com aprovação `[must]`', '',
    '- **Pedido de origem:** P-051',
    '- **Status: 🧪**',
    '',
    '## UC-PIDX-33 · Aba Estoque do painel: quantidade vendida por local · `should`', '',
    '- **Pedido de origem:** P-065',
    '- **Status:** ✅ verde',
  ].join('\n');
  const { pedidos } = lerArquivo(md, 'x.casos.md');
  assert.deepEqual(pedidos.map(p => [p.id, p.uc, p.estagio]), [['P-051', 'UC-DRAFT-05', 'uc'], ['P-065', 'UC-PIDX-33', 'testado']]);
  assert.equal(pedidos[0].titulo, 'Rascunho com margem abaixo do mínimo só vira venda com aprovação');
});

test('Pedido de origem fora de bloco de UC não conta', () => {
  const { pedidos } = lerArquivo('## Backlog\n\n- **Pedido de origem:** P-009', 'x.casos.md');
  assert.equal(pedidos.length, 0);
});

test('decisão com opções e casos que destrava', () => {
  const md = [
    '## Decisões pendentes [W]', '',
    '- **[DECISÃO] D-02 · Acima do limite de crédito: avisar ou bloquear?**',
    '  - Estado: Em aberto',
    '  - Opção: Avisar — A venda salva e mostra quanto passou.',
    '  - Opção: Bloquear — A venda não salva.',
    '  - Destrava: P-005, P-010',
  ].join('\n');
  const { decisoes } = lerArquivo(md, 'x.casos.md');
  assert.equal(decisoes.length, 1);
  assert.equal(decisoes[0].pergunta, 'Acima do limite de crédito: avisar ou bloquear?');
  assert.deepEqual(decisoes[0].opcoes.map(o => o.nome), ['Avisar', 'Bloquear']);
  assert.deepEqual(decisoes[0].destrava, ['P-005', 'P-010']);
});

test('o id P-NNN não casa o regex de UC do casos-gate', () => {
  assert.equal(('P-001 P-114 D-02'.match(ucScanRe()) || []).length, 0);
  assert.equal(('UC-FLX-28'.match(ucScanRe()) || []).length, 1, 'controle positivo: o regex casa um UC real');
});

test('lerRepositorio lê só arquivos versionados, ignora o espelho do protótipo e recusa id repetido', () => {
  const raiz = fs.mkdtempSync(path.join(os.tmpdir(), 'casos-backlog-'));
  const git = (...a) => execFileSync('git', a, { cwd: raiz, stdio: 'pipe' });
  git('init', '-q');
  const escrever = (rel, txt) => { fs.mkdirSync(path.join(raiz, path.dirname(rel)), { recursive: true }); fs.writeFileSync(path.join(raiz, rel), txt); };
  const bullet = '- **[BACKLOG] Um caso** — x\n' + SUBS + '\n';
  escrever('resources/js/Pages/A/Index.casos.md', bullet);
  escrever('prototipo-ui/cowork/A.casos.md', bullet);
  escrever('resources/js/Pages/B/Index.casos.md', '- **[BACKLOG] Fora do git** — x\n  - Pedido: P-002\n');
  git('add', 'resources/js/Pages/A/Index.casos.md', 'prototipo-ui/cowork/A.casos.md');
  const r = lerRepositorio(raiz);
  assert.deepEqual(r.pedidos.map(p => p.id), ['P-001'], 'o espelho e o arquivo não versionado ficam de fora');
  escrever('resources/js/Pages/C/Index.casos.md', bullet);
  git('add', 'resources/js/Pages/C/Index.casos.md');
  assert.throws(() => lerRepositorio(raiz), /P-001 aparece em dois lugares/);
  fs.rmSync(raiz, { recursive: true, force: true });
});
