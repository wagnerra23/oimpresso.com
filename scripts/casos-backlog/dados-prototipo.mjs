#!/usr/bin/env node
// dados-prototipo.mjs — os dados da tela "Casos de uso por tela" para o protótipo do shell.
//
// A tela do protótipo (prototipo-ui/cowork/Wagner/casos-por-tela-page.jsx) não lê os casos.md:
// ela roda no navegador. Este script lê os mesmos dados que a página publicada e a tela do sistema
// (montarDados, de gerar.mjs) e grava um .js que define window.__CASOS_POR_TELA__.
// O arquivo gerado NÃO entra no git: ele é derivado e fica parado no instante em que foi gerado.
// Ele vai junto do Artefato do shell, ao lado do index.html.
//
// Uso:
//   node scripts/casos-backlog/dados-prototipo.mjs --out <dir-do-artefato>/casos-por-tela-dados.js
// Sai 1 se os casos.md têm problema de ligação (mesma regra do gerar.mjs).
import fs from 'node:fs';
import { montarDados } from './gerar.mjs';

const i = process.argv.indexOf('--out');
const out = i > -1 ? process.argv[i + 1] : '';
if (!out) {
  console.error('uso: dados-prototipo.mjs --out <arquivo.js>');
  process.exit(2);
}
const { dados, problemas } = montarDados();
// '<' vira o escape u003c do JSON: nenhum texto de caso fecha um <script>.
const json = JSON.stringify({ ...dados, problemas }).split('<').join(String.fromCharCode(92) + 'u003c');
fs.writeFileSync(out, 'window.__CASOS_POR_TELA__ = ' + json + ';\n');
const ucs = dados.contratos.reduce((s, t) => s + t.ucs.length, 0);
console.log(`dados gravados em ${out}: ${dados.casos.length} pedidos · ${dados.telas.length} telas · ${dados.decisoes.length} decisões · ${dados.contratos.length} telas com contrato · ${ucs} UC`);
for (const p of problemas) console.error('PROBLEMA: ' + p);
process.exit(problemas.length ? 1 : 0);
