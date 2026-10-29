// Teste do novo.mjs: o pedido registrado volta inteiro pelo leitor da página (node --test).
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { proximoPedido, registrar, validar, montarBullet } from './novo.mjs';
import { lerArquivo, lerRepositorio } from './leitor.mjs';

const AQUI = path.dirname(fileURLToPath(import.meta.url));
const TELA = 'resources/js/Pages/Sells/Index.casos.md';

function repo(arquivos, ultimoReservado = 114) {
  const raiz = fs.mkdtempSync(path.join(os.tmpdir(), 'casos-novo-'));
  execFileSync('git', ['init', '-q'], { cwd: raiz });
  const telas = JSON.stringify({ ultimoReservado, areas: [['vendas', 'Vendas']], telas: {} });
  for (const [rel, txt] of Object.entries({ 'scripts/casos-backlog/telas.json': telas, ...arquivos })) {
    fs.mkdirSync(path.join(raiz, path.dirname(rel)), { recursive: true });
    fs.writeFileSync(path.join(raiz, rel), txt);
    execFileSync('git', ['add', rel], { cwd: raiz });
  }
  return raiz;
}

const pedido = id => `- **[BACKLOG] Caso ${id}** — texto\n  - Pedido: ${id}\n  - Âncora: US-X\n`;
const CASO = {
  tela: 'Sells/Index', titulo: 'Juntar produtos duplicados', marcas: ['V0', 'E0'],
  texto: 'Dado a lona cadastrada três vezes, Quando junto as três, Então o estoque soma e nada se apaga.',
  decisao: 'quem pode juntar', ancora: 'Consulta de produto', fazer: 'Ação de juntar com prévia.',
  hoje: 'miss | Não tem | o duplicado só pode ser inativado', cv: 'ok | Sim | material repetido',
  clientes: ['PraMoto', 'Techpress'], etiquetas: ['valor', 'miss', 'cv'], autor: '[F+C]', data: '2026-10-29',
};

test('o próximo pedido nunca fica abaixo do reservado e passa do maior que está no git', () => {
  assert.equal(proximoPedido(repo({ [TELA]: pedido('P-005') })), 'P-115');
  assert.equal(proximoPedido(repo({ [TELA]: pedido('P-005') }, 152)), 'P-153');
  assert.equal(proximoPedido(repo({ [TELA]: pedido('P-160') }, 152)), 'P-161');
});

test('registra o pedido numa seção nova antes da trilha, com a linha na trilha, e o leitor lê todos os campos', () => {
  const md = '# Casos\n\n- **[BACKLOG] Antigo** — x\n  - Pedido: P-010\n  - Âncora: A\n\n## Trilha do tempo\n\n- 2026-10-01 · [C] criado.\n';
  const raiz = repo({ [TELA]: md });
  const r = registrar(CASO, { raiz });
  assert.equal(r.id, 'P-115');
  const texto = fs.readFileSync(path.join(raiz, TELA), 'utf8');
  assert.ok(texto.indexOf('## Pedidos de clientes') < texto.indexOf('## Trilha do tempo'), 'a seção nasce antes da trilha');
  assert.match(texto, /- 2026-10-29 · \[F\+C\] Pedido P-115 "Juntar produtos duplicados" \(PraMoto, Techpress\)/);
  const p = lerArquivo(texto, TELA).pedidos.find(x => x.id === 'P-115');
  assert.equal(p.titulo, 'Juntar produtos duplicados');
  assert.deepEqual(p.marcas, ['V0', 'E0']);
  assert.deepEqual(p.clientes, ['PraMoto', 'Techpress']);
  assert.deepEqual(p.tags, ['valor', 'miss', 'cv']);
  assert.equal(p.hoje.tipo, 'miss');
  assert.equal(p.cv.nota, 'material repetido');
  assert.match(p.texto, /Falta decisão \[W\]: quem pode juntar\. Mexe em valor ou estoque: Regra Mestre\./);
  assert.deepEqual(lerRepositorio(raiz).pedidos.map(x => x.id), ['P-010', 'P-115'], 'o pedido antigo fica intacto');
});

test('o segundo pedido entra no fim da seção que já existe, e arquivo sem trilha não ganha trilha', () => {
  const raiz = repo({ [TELA]: '# Casos\n\n## Outra seção\n\n- texto\n' });
  registrar(CASO, { raiz });
  registrar({ ...CASO, titulo: 'Segundo pedido' }, { raiz });
  const texto = fs.readFileSync(path.join(raiz, TELA), 'utf8');
  assert.equal((texto.match(/## Pedidos de clientes/g) || []).length, 1, 'uma seção só');
  assert.ok(!texto.includes('Trilha do tempo'), 'não inventa trilha');
  assert.deepEqual(lerArquivo(texto, TELA).pedidos.map(x => [x.id, x.titulo]), [['P-115', 'Juntar produtos duplicados'], ['P-116', 'Segundo pedido']]);
  assert.ok(texto.indexOf('## Outra seção') < texto.indexOf('## Pedidos de clientes'), 'a seção vai para o fim');
});

test('recusa caso sem cliente, com tipo errado em Hoje ou com valor em R$', () => {
  assert.deepEqual(validar(CASO), []);
  assert.match(validar({ ...CASO, clientes: [] }).join(), /clientes/);
  assert.match(validar({ ...CASO, hoje: 'talvez | x' }).join(), /"hoje" começa pelo tipo/);
  assert.match(validar({ ...CASO, marcas: ['X9'] }).join(), /marca "X9"/);
  // O valor é montado em partes para o próprio teste não carregar um valor em R$ no git.
  assert.match(validar({ ...CASO, fazer: 'cobrar R' + '$ 10' }).join(), /valor em R\$/);
  const raiz = repo({ [TELA]: '# Casos\n' });
  assert.throws(() => registrar({ ...CASO, clientes: [] }, { raiz }), /caso inválido/);
  assert.equal(fs.readFileSync(path.join(raiz, TELA), 'utf8'), '# Casos\n', 'nada é gravado');
});

test('recusa tela sem casos.md e número repetido no repositório', () => {
  assert.throws(() => registrar({ ...CASO, tela: 'Nao/Existe' }, { raiz: repo({ [TELA]: '# Casos\n' }) }), /criar-tela/);
  const dup = repo({ [TELA]: pedido('P-120') + pedido('P-120') });
  assert.throws(() => registrar(CASO, { raiz: dup }), /P-120 aparece em dois lugares/);
});

test('linha longa quebra sem perder palavra, e o leitor junta de volta', () => {
  const longo = { ...CASO, texto: 'Dado '.concat('palavra '.repeat(60), 'fim.') };
  const bullet = montarBullet(longo, 'P-200');
  assert.ok(bullet.split('\n').every(l => l.length <= 110 || !l.includes(' ')), 'nenhuma linha passa da largura');
  const p = lerArquivo(bullet + '\n', TELA).pedidos[0];
  assert.equal((p.texto.match(/palavra/g) || []).length, 60);
});

test('--dry-run não grava e --proximo só imprime o número', () => {
  const raiz = repo({ [TELA]: '# Casos\n' });
  const json = path.join(raiz, 'caso.json');
  fs.writeFileSync(json, JSON.stringify(CASO));
  const novo = path.join(AQUI, 'novo.mjs');
  // O CLI usa a raiz do próprio repositório; aqui o teste chama a função com a raiz temporária.
  const r = registrar(CASO, { raiz, gravar: false });
  assert.equal(fs.readFileSync(path.join(raiz, TELA), 'utf8'), '# Casos\n', 'dry-run não grava');
  assert.match(r.texto, /- Pedido: P-115/);
  const saida = execFileSync(process.execPath, [novo, '--proximo'], { encoding: 'utf8' }).trim();
  assert.match(saida, /^P-\d{3}$/);
});
