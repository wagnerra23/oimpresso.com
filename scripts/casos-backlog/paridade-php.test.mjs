// paridade-php.test.mjs — os dois leitores dos casos.md leem a mesma coisa.
//
// A página "Casos de uso por tela" tem dois leitores: este (Node, gerar.mjs, que gera o artefato e
// o resumo do `npm run casos:pagina`) e o PHP (app/Services/Documentacao/CasosPorTela.php, que a
// página dentro do sistema usa em cada requisição). Este teste roda os dois sobre o repositório
// inteiro e exige o mesmo resultado, campo a campo: pedidos, decisões, contrato de cada tela,
// regras, resumo e problemas.
//
// Sem `php` no ambiente o teste FALHA, não pula: um teste de paridade que não roda deixaria os dois
// leitores divergirem sem ninguém ver (proibicoes §5, LC-13). Fora do CI, aponte o binário em PHP_BIN.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { montarDados } from './gerar.mjs';
import { lerArquivo } from './leitor.mjs';

const AQUI = path.dirname(fileURLToPath(import.meta.url));
const RAIZ = path.resolve(AQUI, '..', '..');
const PHP = process.env.PHP_BIN || 'php';

function lerPhp(argumento = RAIZ) {
  const out = execFileSync(PHP, [path.join(AQUI, 'dados.php'), argumento], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
  return JSON.parse(out);
}

function lerNode() {
  const { dados, problemas } = montarDados(RAIZ);
  const { geradoEm, ...resto } = dados;
  return { ...resto, problemas };
}

test('PHP e Node leem os mesmos pedidos, decisões e contratos no repositório inteiro', () => {
  const php = lerPhp();
  const node = lerNode();
  // Controle positivo: o repositório tem pedidos e contratos; comparar dois vazios não provaria nada.
  assert.ok(node.casos.length > 0, 'o Node não leu nenhum pedido');
  assert.ok(node.contratos.length > 0, 'o Node não leu nenhum contrato');
  for (const chave of ['casos', 'decisoes', 'contratos', 'telas', 'areas', 'regras', 'proximoPedido', 'problemas']) {
    assert.deepStrictEqual(php[chave], node[chave], `os dois leitores divergem em "${chave}"`);
  }
});

test('o resumo da página no sistema é o mesmo do npm run casos:pagina -- --resumo', () => {
  const resumo = JSON.parse(execFileSync(process.execPath, [path.join(AQUI, 'gerar.mjs'), '--resumo'], { encoding: 'utf8', cwd: RAIZ }).split('\n')[0]);
  assert.deepStrictEqual(lerPhp().resumo, resumo);
});

test('as grafias de backlog do leitor.test são lidas igual pelo PHP', () => {
  // Arquivo sintético com as grafias que o leitor Node aceita: prova o PHP também fora do corpus real.
  const md = [
    '- **[BACKLOG] Filtrar por vendedor** — pedido da loja (pedido Maiara 23/10)',
    '  - Pedido: P-001',
    '  - Clientes: Loja A, Loja B',
    '  - Hoje: parcial | só por data | ver depois',
    '  - Texto livre sem chave.',
    '- **[BACKLOG · ⬜ sem teste · decisão [W]]** Duplicar venda `[V0]`',
    '  - Pedido: P-002',
    '  - Opção: A — fazer já',
    '## UC-ZZ-01 · Caso com `código` e pedido',
    '- **Pedido de origem:** P-003',
    '- **Status:** ✅ verde',
    '## Decisões',
    '- **[DECISÃO] D-01 · Pode duplicar?**',
    '  - Destrava: P-002',
    '  - Opção: Sim — duplica',
  ].join('\n');
  const node = lerArquivo(md, 'x/Pages/Y.casos.md');
  const php = JSON.parse(execFileSync(PHP, ['-r', `require ${JSON.stringify(path.join(RAIZ, 'app/Services/Documentacao/CasosPorTela.php'))};`
    + 'echo json_encode(App\\Services\\Documentacao\\CasosPorTela::lerArquivo(stream_get_contents(STDIN), "x/Pages/Y.casos.md"), JSON_UNESCAPED_UNICODE);'],
  { input: md, encoding: 'utf8' }));
  assert.equal(node.pedidos.length, 3);
  assert.deepStrictEqual(php[0], node.pedidos);
  assert.deepStrictEqual(php[1], node.decisoes);
});
