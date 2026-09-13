#!/usr/bin/env node
// @ts-check
/**
 * importar-bundle.test.mjs — MORDE/SOLTA do import do bundle Cowork, exercitando o CLI DE FORA.
 *
 * Por que existe: o `--selftest` do `importar-bundle.mjs` asserta SÓ `decidirSwap` — 4 casos
 * sobre uma função pura. Assert sobre helper puro exportado não prova contrato de pipeline
 * (§5 2026-07-30, LC-15): a guarda `if (!veredito.ok) return 1` podia sumir de `importar()` e
 * os 4 asserts seguiriam verdes. A divisão de trabalho fica assim, e é deliberada pra não
 * duplicar régua consolidada (§5 2026-07-09):
 *   · `--selftest` → `decidirSwap` (a função que DECIDE);
 *   · este arquivo → `acharBundleRoot` + o contrato de PROCESSO (quem chama obedece?).
 *
 * Hermético e cross-platform: nenhum caso extrai zip de verdade, então não depende do
 * PowerShell — o script é Windows-only na extração e o CI roda `ubuntu-latest`. Os casos de CLI
 * asseguram o EFEITO observável (exit code + destino intacto), NUNCA a mensagem, que difere por
 * plataforma: no Windows a extração morre em `ZipFile::OpenRead`, no Linux o `powershell` nem
 * existe (ENOENT). Mesma conclusão por caminhos distintos — a lição de §5 2026-08-07 é
 * exatamente essa, não afirmar de uma plataforma o que se mediu na outra.
 *
 * Defeitos que ficam travados aqui:
 *   · `acharBundleRoot` voltar a assumir `<destino>/project`: o zip Cowork abre em
 *     `<slug>/project/`, e assumir o topo ANINHA o slug inteiro (bug 2026-07-01, descrito no
 *     cabeçalho do próprio script e sem um único teste até hoje);
 *   · ambiguidade (2+ raízes com host+app) resolvida em silêncio, em vez de avisar;
 *   · marca fraca ignorada, caindo pro destino cru e aninhando;
 *   · `importar()` tocar o destino ANTES de a integridade fechar — o "delete-before-verify" que
 *     é a razão de existir do script. O `--selftest` não prova isso: prova que a função que
 *     decide sabe decidir, não que quem a chama respeita o veredito.
 *
 * Segurança do próprio teste (não é zelo, é pré-requisito): todo caso de CLI passa
 * `--dir <tmp>` — jamais o staging real `~/Downloads/_cowork-handoff-staging` — e
 * `--no-sync-cowork`, porque o sync escreve com /PURGE em `prototipo-ui/cowork/Wagner/`, que é
 * o SSOT do repo. Um teste que chegasse lá por acidente apagaria a fonte de design.
 */
import { execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, existsSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { acharBundleRoot } from './importar-bundle.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(HERE, 'importar-bundle.mjs');

let falhas = 0;
const ok = (cond, nome) => {
  console.log(`  ${cond ? 'ok  ' : 'FALHOU'} ${nome}`);
  if (!cond) falhas++;
};

const novoTmp = (p) => mkdtempSync(join(tmpdir(), p));

/** Marca FORTE = host + app juntos (o que `acharBundleRoot` exige pra cravar a raiz). */
function marcaForte(dir) {
  mkdirSync(dir, { recursive: true });
  writeFileSync(join(dir, 'oimpresso.com.html'), '<html></html>');
  writeFileSync(join(dir, 'app.jsx'), '// app');
  return dir;
}
/** Marca FRACA = só um dos dois. */
function marcaFraca(dir) {
  mkdirSync(dir, { recursive: true });
  writeFileSync(join(dir, 'app.jsx'), '// app');
  return dir;
}

/** Roda `acharBundleRoot` capturando o stderr dele (o aviso é parte do contrato). */
function acharCapturando(destino) {
  const avisos = [];
  const orig = console.error;
  console.error = (...a) => avisos.push(a.map(String).join(' '));
  try { return { raiz: acharBundleRoot(destino), avisos }; } finally { console.error = orig; }
}

/** Roda o CLI DE FORA (subprocesso) — é o ponto do exercício. */
function rodarCli(args) {
  try {
    const out = execFileSync(process.execPath, [SCRIPT, ...args], {
      encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'],
    });
    return { code: 0, out, err: '' };
  } catch (e) {
    return { code: typeof e.status === 'number' ? e.status : -1, out: e.stdout || '', err: e.stderr || '' };
  }
}

/** Staging de mentira com uma sentinela — se ela sumir, o destino foi tocado. */
function stagingComSentinela(prefixo) {
  const dir = novoTmp(prefixo);
  const sentinela = join(dir, 'NAO-APAGUE.txt');
  writeFileSync(sentinela, 'staging antigo');
  return { dir, sentinela };
}
const sentinelaIntacta = (s) => existsSync(s) && readFileSync(s, 'utf8') === 'staging antigo';

console.log('\nimportar-bundle — acharBundleRoot (o bug 2026-07-01 que nunca teve teste)\n');

// A1 — o formato REAL do zip Cowork. Assumir `<destino>/project` aninharia o slug inteiro.
{
  const d = novoTmp('ib-a1-');
  const alvo = marcaForte(join(d, 'Oimpresso ERP', 'project'));
  ok(acharBundleRoot(d) === alvo, 'MORDE: raiz real em <slug>/project — assumir <destino>/project aninha o slug');
}

// A2/A3 — as outras duas topologias aceitas, pra provar que A1 não passou por acidente.
// A2 — `project/` no topo é o caso MAIS SIMPLES, e era onde a lista de candidatos duplicava:
// `join(destino,'project')` e o `project` vindo de `subs.map(...)` são o MESMO path, então
// `fortes` vinha com 2 entradas iguais e o script gritava "raiz ambígua" sem ambiguidade
// nenhuma. O retorno saía certo por sorte; o diagnóstico é que mentia — e aviso que grita
// falso treina o operador a ignorar aviso verdadeiro. Achado por este teste em 2026-09-13.
{
  const d = novoTmp('ib-a2-');
  const alvo = marcaForte(join(d, 'project'));
  const { raiz, avisos } = acharCapturando(d);
  ok(raiz === alvo, 'SOLTA: project/ direto no topo');
  ok(!avisos.some((m) => m.includes('ambígua')), 'MORDE: topo simples NÃO é ambiguidade (candidato duplicado deduplicado)');
}
{
  const d = novoTmp('ib-a3-');
  const alvo = marcaForte(join(d, 'bundle-x'));
  ok(acharBundleRoot(d) === alvo, 'SOLTA: <slug>/ sem project/ dentro');
}

// A4 — ambiguidade. O aviso é determinístico; a ESCOLHA (1º ordenado) só difere do first-wins
// do readdir quando o FS devolve fora de ordem, então é o aviso que carrega a mordida aqui.
{
  const d = novoTmp('ib-a4-');
  marcaForte(join(d, 'zeta', 'project'));
  const esperado = marcaForte(join(d, 'alfa', 'project'));
  const { raiz, avisos } = acharCapturando(d);
  ok(avisos.some((m) => m.includes('ambígua')), 'MORDE: 2 raízes fortes AVISAM em vez de escolher caladas');
  ok(raiz === esperado, 'SOLTA: e a escolhida é a 1ª ORDENADA (determinística, não a que o FS listou antes)');
}

// A5 — marca fraca: cai pro fraco COM aviso, nunca pro destino cru em silêncio.
{
  const d = novoTmp('ib-a5-');
  const alvo = marcaFraca(join(d, 'so-app'));
  const { raiz, avisos } = acharCapturando(d);
  ok(raiz === alvo, 'SOLTA: sem raiz forte, usa a marca fraca (não o destino cru)');
  ok(avisos.some((m) => m.includes('nenhuma raiz FORTE')), 'MORDE: a queda pra marca fraca é declarada');
}

// A6 — nada reconhecível: devolve o destino cru, mas avisa que pode aninhar.
{
  const d = novoTmp('ib-a6-');
  mkdirSync(join(d, 'sem-marca'));
  const { raiz, avisos } = acharCapturando(d);
  ok(raiz === d, 'SOLTA: sem marker nenhum, devolve o destino cru');
  ok(avisos.some((m) => m.includes('nenhum host-marker')), 'MORDE: o destino cru é declarado, não silencioso');
}

console.log('\nimportar-bundle — contrato de PROCESSO (CLI de fora, o que o --selftest não alcança)\n');

// B1 — uso. Exit 2 tem que ser DISTINTO do 1 de integridade, senão o CLI não discrimina (LC-13).
const semArg = rodarCli([]);
ok(semArg.code === 2, 'MORDE: sem zip -> exit 2 (uso)');

// B2 — zip inexistente: falha ANTES de qualquer extração, e o destino não é tocado.
{
  const { dir, sentinela } = stagingComSentinela('ib-b2-');
  const r = rodarCli([join(dir, 'nao-existe.zip'), '--dir', dir, '--no-detect', '--no-sync-cowork']);
  ok(r.code === 1, 'MORDE: zip inexistente -> exit 1');
  ok(sentinelaIntacta(sentinela), 'MORDE: staging antigo PRESERVADO (nada foi trocado)');
  ok(semArg.code !== r.code, 'MORDE: uso(2) e integridade(1) são exits DISTINTOS — o CLI discrimina');
}

// B3 — o caso que o --selftest NÃO cobre: a extração falha e o destino segue intacto.
// Windows: `ZipFile::OpenRead` lança num arquivo que não é zip. Linux: `powershell` é ENOENT.
// Caminhos diferentes, mesmo efeito observável — por isso a asserção é no exit + sentinela.
{
  const { dir, sentinela } = stagingComSentinela('ib-b3-');
  const falsoZip = join(novoTmp('ib-b3z-'), 'bundle.zip');
  writeFileSync(falsoZip, 'isto nao e um zip');
  const r = rodarCli([falsoZip, '--dir', dir, '--no-detect', '--no-sync-cowork']);
  ok(r.code === 1, 'MORDE: extração falha -> exit 1');
  ok(sentinelaIntacta(sentinela), 'MORDE: delete-before-verify FECHADO — destino intacto após falha de extração');
}

console.log(`\n  ${falhas === 0 ? 'OK' : 'FALHAS: ' + falhas}\n`);
if (falhas) process.exit(1);
