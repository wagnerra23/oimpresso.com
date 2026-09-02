#!/usr/bin/env node
/**
 * Teste de IDENTIDADE do codemod `glifo-para-lucide.mjs`.
 *
 * Por que existe (lápide §5 2026-08-02 e 2026-08-12): reescrita automática de
 * arquivo só é confiável quando se prova que ela NÃO comeu o vizinho. "Rodou
 * sem erro" e "o script ficou verde" são compatíveis com dano intacto — a prova
 * é o diff, e ela tem que ser capaz de ficar vermelha.
 *
 * COMO PROVA (duas metades, porque o codemod muda duas coisas):
 *   1. CORPO — desfaz `repl -> needle` em cada site e exige que o resto seja
 *      byte-idêntico ao blob de HEAD.
 *   2. IMPORT — NÃO normaliza a linha. Recalcula qual linha o codemod DEVIA ter
 *      produzido (rodando o mesmo `mergeImport` sobre o fonte de HEAD) e exige
 *      igualdade de string exata. A 1ª versão deste arquivo trocava a linha
 *      atual pela de HEAD antes de comparar — com isso qualquer byte estranho
 *      NAQUELA linha ficava invisível, e o bite-test provou: o teste passou
 *      verde com dano injetado no import. O conserto é este: verificar, nunca
 *      normalizar o que se quer medir.
 *
 * Uso: node scripts/codemods/glifo-para-lucide.identidade.mjs
 *      (depois do --apply, com as mudanças ainda não commitadas)
 *      --bite-test  injeta dano nos 2 eixos e exige que ESTE teste reprove
 */
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import MagicString from 'magic-string';
import { SITES, mergeImport } from './glifo-para-lucide.mjs';

// execFile (não shell): no Git Bash do Windows o MSYS mangleia o `:` de
// `HEAD:<path>` e o comando volta vazio (lápide §5 2026-08-23).
const blobEmHead = (f) =>
  execFileSync('git', ['show', 'HEAD:' + f], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });

const linhaImport = (src) => src.split('\n').find((l) => l.includes("from 'lucide-react'"));

function verificar({ silencioso = false } = {}) {
  const porArquivo = new Map();
  for (const s of SITES) {
    if (!porArquivo.has(s.file)) porArquivo.set(s.file, []);
    porArquivo.get(s.file).push(s);
  }

  const log = (m) => { if (!silencioso) console.log(m); };
  let falhas = 0;

  for (const [file, sites] of porArquivo) {
    let atual = readFileSync(file, 'utf8');
    const original = blobEmHead(file);

    // ── metade 2: a linha de import é VERIFICADA (string exata), não normalizada
    const icons = new Set();
    sites.forEach((s) => s.icons.forEach((i) => icons.add(i)));
    const msEsperado = new MagicString(original);
    mergeImport(original, msEsperado, [...icons]);
    const importEsperado = linhaImport(msEsperado.toString());
    const importAtual = linhaImport(atual);
    if (importAtual !== importEsperado) {
      falhas++;
      log('X  ' + file + '  import DIVERGE do esperado');
      log('     esperado: ' + JSON.stringify(importEsperado));
      log('     atual:    ' + JSON.stringify(importAtual));
      continue;
    }
    // já verificada: agora pode voltar pra de HEAD, pra comparar o corpo
    atual = atual.replace(importAtual, linhaImport(original));

    // ── metade 1: corpo revertido tem que bater byte a byte
    for (const s of sites) {
      const n = atual.split(s.repl).length - 1;
      if (n !== 1) {
        falhas++;
        log('X  ' + file + ': repl aparece ' + n + 'x ao reverter (esperado 1)');
      }
      atual = atual.replace(s.repl, s.needle);
    }

    if (atual === original) {
      log('ok ' + file + '  (import verificado + corpo revertido = HEAD, byte a byte)');
    } else {
      falhas++;
      log('X  ' + file + '  corpo DIVERGE — o codemod tocou algo fora do contrato');
      const a = original.split('\n');
      const b = atual.split('\n');
      for (let i = 0; i < Math.max(a.length, b.length); i++) {
        if (a[i] !== b[i]) {
          log('     L' + (i + 1) + ' HEAD:  ' + JSON.stringify(a[i]));
          log('     L' + (i + 1) + ' AGORA: ' + JSON.stringify(b[i]));
          break;
        }
      }
    }
  }
  return { falhas, nArquivos: porArquivo.size };
}

// ── bite-test: prova que ESTE teste consegue ficar vermelho ───────────────────
async function biteTest() {
  const { writeFileSync } = await import('node:fs');
  const file = SITES[0].file;
  const salvo = readFileSync(file, 'utf8');
  const casos = [
    ['dano NA LINHA DO IMPORT', (s) => s.replace("from 'lucide-react'", "from 'lucide-react' /* dano */")],
    ['dano em linha VIZINHA', (s) => s.replace('\nexport default', '\n// dano\nexport default')],
  ];
  let ok = 0;
  try {
    for (const [nome, mutar] of casos) {
      const mutado = mutar(salvo);
      if (mutado === salvo) { console.log('X   ' + nome + ' :: mutacao nao aplicou (fixture invalida)'); continue; }
      writeFileSync(file, mutado, 'utf8');
      const { falhas } = verificar({ silencioso: true });
      if (falhas > 0) { console.log('ok  ' + nome + ' -> teste REPROVOU (esperado)'); ok++; }
      else console.log('X   ' + nome + ' -> teste passou VERDE com dano (cego neste eixo)');
      writeFileSync(file, salvo, 'utf8');
    }
  } finally {
    writeFileSync(file, salvo, 'utf8');
  }
  const { falhas } = verificar({ silencioso: true });
  if (falhas === 0) { console.log('ok  restauracao: arquivo voltou ao estado bom'); ok++; }
  else console.log('X   restauracao FALHOU — arquivo ficou sujo');
  console.log(ok === casos.length + 1 ? 'ok bite-test: ' + ok + '/' + (casos.length + 1) : 'X bite-test: ' + ok + '/' + (casos.length + 1));
  return ok === casos.length + 1 ? 0 : 1;
}

if (process.argv[2] === '--bite-test') {
  process.exit(await biteTest());
} else {
  const { falhas, nArquivos } = verificar();
  console.log('');
  console.log(
    falhas
      ? 'X identidade: ' + falhas + ' divergencia(s) fora do contrato'
      : 'ok identidade: ' + nArquivos + ' arquivo(s) — import verificado e corpo byte-identico a HEAD'
  );
  process.exit(falhas ? 1 : 0);
}
