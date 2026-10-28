// Teste do gerador da página "Casos de uso por tela" (node --test).
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { montarDados, renderizar, lerRegras } from './gerar.mjs';

const PHP = `<?php
return [
        ['UC-FLX-28', 'Sinal mínimo para liberar a produção', 'producao', 'Liberada', 'Segura a OS até o sinal', ['mubisys'], 'pequena', 'parcial', 'US-PCP-025'],
        ['UC-FLX-30', 'ISS retido', 'venda', 'Faturado', 'Ajusta o título', ['omie'], 'micro', 'producao', 'US-FIN-072'],
];`;

function repo(arquivos) {
  const raiz = fs.mkdtempSync(path.join(os.tmpdir(), 'casos-gerar-'));
  execFileSync('git', ['init', '-q'], { cwd: raiz });
  for (const [rel, txt] of Object.entries({ 'app/Domain/Fsm/Modelos/CasosDoPrototipo.php': PHP, ...arquivos })) {
    fs.mkdirSync(path.join(raiz, path.dirname(rel)), { recursive: true });
    fs.writeFileSync(path.join(raiz, rel), txt);
    execFileSync('git', ['add', rel], { cwd: raiz });
  }
  return raiz;
}

const caso = (id, ancora, extra = '') => `- **[BACKLOG] Caso ${id}** — texto ${extra}\n  - Pedido: ${id}\n  - Âncora: ${ancora}\n`;

test('lê as regras do catálogo do código', () => {
  const raiz = repo({});
  assert.deepEqual(lerRegras(raiz).map(r => r.id), ['UC-FLX-28', 'UC-FLX-30']);
});

test('monta casos, telas e áreas a partir do caminho do casos.md', () => {
  const raiz = repo({ 'resources/js/Pages/Sells/Index.casos.md': caso('P-001', '`UC-FLX-28`') + caso('P-002', 'US-X') });
  const { dados, problemas } = montarDados(raiz);
  assert.deepEqual(problemas, []);
  assert.deepEqual(dados.casos.map(c => [c.id, c.n, c.tela, c.regras.join()]), [['P-001', 1, 'Sells/Index', 'UC-FLX-28'], ['P-002', 2, 'Sells/Index', '']]);
  assert.deepEqual(dados.telas.map(t => [t.id, t.nome, t.area]), [['Sells/Index', 'Consulta de vendas', 'vendas']]);
  assert.deepEqual(dados.areas.map(a => a.id), ['vendas']);
});

test('regra inexistente e decisão apontando para pedido fora do git viram problema', () => {
  const md = caso('P-001', '`UC-FLX-99`')
    + '\n- **[DECISÃO] D-01 · Avisar ou bloquear?**\n  - Destrava: P-001, P-077\n';
  const { problemas } = montarDados(repo({ 'resources/js/Pages/Sells/Index.casos.md': md }));
  assert.deepEqual(problemas, ['P-001 cita UC-FLX-99, que não existe no catálogo', 'D-01 destrava P-077, que não está no git']);
});

test('tela fora do dicionário cai em Outras telas pelo caminho', () => {
  const { dados } = montarDados(repo({ 'Modules/X/Resources/js/Pages/X/Painel.casos.md': caso('P-005', 'US-X') }));
  assert.deepEqual(dados.telas.map(t => [t.id, t.nome, t.area]), [['X/Painel', 'X/Painel', 'outras']]);
});

test('texto com </script> não fecha o script da página', () => {
  const { dados } = montarDados(repo({ 'resources/js/Pages/Sells/Index.casos.md': caso('P-001', 'US-X', '</script><b>x</b>') }));
  const html = renderizar(dados);
  assert.equal((html.match(/<\/script>/g) || []).length, 1, 'só o fechamento do próprio script');
  const json = html.match(/const DADOS = (.*);/)[1];
  assert.match(JSON.parse(json).casos[0].texto, /<\/script>/, 'o texto volta intacto ao ler o JSON');
});

test('o próximo pedido livre respeita os números já dados pelo documento de origem', () => {
  const { dados } = montarDados(repo({ 'resources/js/Pages/Sells/Index.casos.md': caso('P-005', 'US-X') }));
  assert.equal(dados.proximoPedido, 'P-115', 'P-001 a P-114 ficam reservados, mesmo os que ainda não estão no git');
  const { dados: d2 } = montarDados(repo({ 'resources/js/Pages/Sells/Index.casos.md': caso('P-120', 'US-X') }));
  assert.equal(d2.proximoPedido, 'P-121');
});
