#!/usr/bin/env node
// aguardar-bundle-live.mjs — fecha o residual #7 do screen-smoke-after-merge:
// "deploy concluiu" não é o mesmo que "produção serve o código novo".
//
// O deploy publica o bundle Inertia e só então termina, mas entre o fim do job e a
// primeira resposta com o bundle novo há cache (LSCache, CDN, navegador do runner não,
// este é curl). O smoke antigo esperava com `sleep 10` cego e podia fotografar a tela
// VELHA e chamar de verde.
//
// O que este script mede: o arquivo de entrada `resources/js/app.tsx` que o PRÓPRIO
// build do deploy gerou (lido do manifest.json do artefato `vite-build`) aparece no HTML
// de /login em produção. Hash do Vite muda a cada conteúdo, então o nome do arquivo é a
// assinatura do build.
//
// O que ele NÃO mede, e fica declarado: o PHP. O bundle vivo prova que o git reset e a
// publicação dos assets aconteceram (o deploy faz os dois antes), mas não prova que o
// opcache recarregou o código PHP (§5 2026-09-18). O smoke é de render, e render sai do
// bundle; o residual de PHP segue fora do alcance deste passo.
//
// Saídas: 0 = bundle do deploy está no ar · 1 = esgotou as tentativas servindo outro
// bundle · 2 = não consegui medir (manifest ausente/ilegível, rede falhando em todas as
// tentativas). O 2 é separado do 1 de propósito: "não medi" não vira acusação nem verde.
//
// Uso:  node aguardar-bundle-live.mjs --manifest-dir <dir> [--url URL] [--tentativas N] [--intervalo S]
//       node aguardar-bundle-live.mjs --selftest

import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const ENTRADA = 'resources/js/app.tsx';

/** Acha manifest.json (ou .vite/manifest.json) em qualquer profundidade sob `dir`. */
export function acharManifest(dir) {
  const pilha = [dir];
  while (pilha.length) {
    const atual = pilha.pop();
    let nomes;
    try { nomes = readdirSync(atual); } catch { continue; }
    for (const nome of nomes) {
      const p = join(atual, nome);
      let st;
      try { st = statSync(p); } catch { continue; }
      if (st.isDirectory()) { if (nome !== 'assets') pilha.push(p); }
      else if (nome === 'manifest.json') return p;
    }
  }
  return null;
}

/** Nome do arquivo de entrada (`assets/app-<hash>.js`) a partir do JSON do manifest. */
export function arquivoDeEntrada(manifest) {
  const e = manifest && manifest[ENTRADA];
  return e && typeof e.file === 'string' && e.file ? e.file : null;
}

/** Referências `build-inertia/assets/app-*.js` presentes no HTML. */
export function entradasNoHtml(html) {
  return [...new Set(String(html).match(/build-inertia\/assets\/app-[A-Za-z0-9_-]+\.js/g) || [])];
}

/** O HTML serve este build? `file` vem do manifest (`assets/app-X.js`). */
export function htmlServe(html, file) {
  return entradasNoHtml(html).some((r) => r.endsWith('/' + file));
}

function args(argv) {
  const o = { url: 'https://oimpresso.com/login', tentativas: 20, intervalo: 15 };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--manifest-dir') o.dir = argv[++i];
    else if (a === '--url') o.url = argv[++i];
    else if (a === '--tentativas') o.tentativas = Number(argv[++i]);
    else if (a === '--intervalo') o.intervalo = Number(argv[++i]);
    else if (a === '--selftest') o.selftest = true;
  }
  return o;
}

async function main() {
  const o = args(process.argv.slice(2));
  if (o.selftest) return selftest();
  if (!o.dir) { console.error('uso: --manifest-dir <dir>'); return 2; }

  const mp = acharManifest(o.dir);
  if (!mp) { console.error(`NÃO MEDIDO: nenhum manifest.json sob ${o.dir}`); return 2; }
  let file;
  try { file = arquivoDeEntrada(JSON.parse(readFileSync(mp, 'utf8'))); } catch (e) {
    console.error(`NÃO MEDIDO: manifest ilegível (${mp}): ${e.message}`); return 2;
  }
  if (!file) { console.error(`NÃO MEDIDO: manifest sem a entrada ${ENTRADA} (${mp})`); return 2; }
  console.log(`build do deploy: ${file} (de ${mp})`);

  let respondeu = false;
  for (let i = 1; i <= o.tentativas; i++) {
    const u = `${o.url}${o.url.includes('?') ? '&' : '?'}_smoke_live=${Date.now()}`;
    try {
      const r = await fetch(u, { headers: { 'cache-control': 'no-cache', pragma: 'no-cache' } });
      const html = await r.text();
      respondeu = true;
      const vistos = entradasNoHtml(html);
      if (htmlServe(html, file)) { console.log(`tentativa ${i}: produção serve ${file} — código do deploy no ar.`); return 0; }
      console.log(`tentativa ${i}: HTTP ${r.status}, produção serve ${vistos.join(', ') || '(nenhuma entrada app-*.js)'}`);
    } catch (e) {
      console.log(`tentativa ${i}: sem resposta (${e.message})`);
    }
    if (i < o.tentativas) await new Promise((r) => setTimeout(r, o.intervalo * 1000));
  }
  if (!respondeu) { console.error('NÃO MEDIDO: produção não respondeu em nenhuma tentativa.'); return 2; }
  console.error(`produção seguiu servindo outro bundle após ${o.tentativas} tentativas; esperado ${file}.`);
  return 1;
}

function selftest() {
  let falhas = 0;
  const ok = (cond, nome) => { if (!cond) { falhas++; console.error(`FALHOU: ${nome}`); } else console.log(`ok: ${nome}`); };
  const m = { 'resources/js/app.tsx': { file: 'assets/app-Cbnnt7cw.js', isEntry: true }, 'resources/css/inertia.css': { file: 'assets/inertia-X.css' } };
  ok(arquivoDeEntrada(m) === 'assets/app-Cbnnt7cw.js', 'lê a entrada app.tsx do manifest');
  ok(arquivoDeEntrada({}) === null, 'manifest sem a entrada devolve null (vira NÃO MEDIDO, não verde)');
  const html = '<script type="module" src="https://oimpresso.com/build-inertia/assets/app-Cbnnt7cw.js"></script>';
  ok(htmlServe(html, 'assets/app-Cbnnt7cw.js'), 'HTML com o hash do deploy = no ar');
  // Controle: bundle anterior no HTML não pode passar por prefixo nem por outro hash.
  ok(!htmlServe(html, 'assets/app-OUTRO123.js'), 'HTML com outro hash = ainda não está no ar');
  ok(!htmlServe(html.replace('app-Cbnnt7cw', 'app-Cbnnt7cwXX'), 'assets/app-Cbnnt7cw.js'), 'hash que só começa igual não conta');
  ok(!htmlServe('<html>manutenção</html>', 'assets/app-Cbnnt7cw.js'), 'HTML sem entrada nenhuma não conta');
  return falhas ? 1 : 0;
}

// exitCode, não process.exit(): no Windows, sair à força com o socket keep-alive do fetch
// ainda fechando aborta o node (assert do libuv, rc 127) — medido no teste local deste script.
main().then((c) => { process.exitCode = c; }, (e) => { console.error(`NÃO MEDIDO: ${e.stack || e}`); process.exitCode = 2; });
