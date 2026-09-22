#!/usr/bin/env node
// @ts-check
/**
 * design-lock.mjs — a FONTE de uma comparação é DECLARADA e PROVADA, nunca resolvida por sorte.
 *
 * Por que existe ([W] 2026-09-22, complemento ao #7703): o #7703 fechou o aceite POR ITEM
 * (matriz, T00 identidade, sameTheme). Faltava o outro eixo: **garantir que a comparação usa
 * o protótipo certo e o Design System certo**. [W], textual: *"o que não pode existir é […]
 * a ferramenta escolher a fonte por basename […] usar 'o primeiro arquivo encontrado'"*.
 *
 * MEDIDO em 2026-09-22, antes de escrever uma linha (o defeito é ATIVO, não hipotético):
 *   46 charters declaram `-page.jsx` em bundle_source/visual_source
 *    6 declaram com CAMINHO COMPLETO  -> `ancora.mjs:512` compara basename(f) === valor CRU,
 *                                        então o caminho NUNCA casa: resolvem NADA, em silêncio
 *   40 são AMBÍGUOS (2+ arquivos com aquele basename)
 *   38 destes têm CONTEÚDO DIVERGENTE -> `.find()` escolhe o primeiro da varredura (Felipe,
 *                                        por ordem alfabética) e nada declara a escolha
 *    0 resolvem exatamente 1 candidato
 * Ou seja: a perna do bundle NUNCA resolve corretamente hoje — ou escolhe entre divergentes,
 * ou ignora quem declarou o caminho certo. Comandos de medição no --selftest e no PR.
 *
 * FRONTEIRA COM OS DONOS (LC-19 — não abrir paralelo):
 *   - `ancora.mjs`   é o dono de "QUAL é a fonte de design desta tela" (resolve do charter).
 *                    Este script NÃO resolve âncora: ele PROVA que a fonte resolvida é única,
 *                    versionada e igual à declarada no lock.
 *   - `ds-guard.mjs` é o dono de "este CSS inventou paleta / pôs tela na raiz" (§8). Outro tema.
 *   - `cowork-mirror-freshness` é o dono de "o espelho está fresco". Outro tema.
 *   - Este script é o dono de: LOCK (proveniência declarada), DS ÚNICO e AMBIGUIDADE DE FONTE.
 *
 * FORMATO DO LOCK: JSON, não YAML. [W] admitiu "pode seguir os padrões existentes do
 * repositório" — e `js-yaml` NÃO está em dependencies nem devDependencies (medido), logo um
 * lock YAML exigiria dependência nova para um gate, que é o defeito que o gate existe pra
 * impedir. `governance/` já é JSON (gates-registry, required-checks-baseline).
 *
 * ENFORCEMENT: nasce ADVISORY e forward-only (ADR 0275). Ele NÃO promove a si mesmo — quem
 * é required vive em `governance/required-checks-baseline.json`, e este arquivo não afirma
 * o próprio enforcement em tempo presente (LC-10).
 *
 * Uso:
 *   node scripts/design/design-lock.mjs --check                 # valida lock + DS único + ambiguidade
 *   node scripts/design/design-lock.mjs --check --strict        # mesmo, mas exit 1 em achado
 *   node scripts/design/design-lock.mjs --ds                    # só o eixo DS único
 *   node scripts/design/design-lock.mjs --ambiguidade           # só o eixo de fonte ambígua
 *   node scripts/design/design-lock.mjs --hash <arquivo>        # sha256 de um arquivo (pra montar o lock)
 *   node scripts/design/design-lock.mjs --selftest              # fixtures: caso BOM e caso RUIM de cada regra
 *
 * Exit: 0 = ok (ou advisory com achados) · 1 = achado sob --strict · 2 = NÃO MEDI · 3 = uso
 *
 * Vocabulário de exit herdado do `--check-shell` do design-diff.mjs (auditoria 2026-08-28):
 *   1 = medi e reprovou · 2 = NÃO consegui medir. Nunca colapsar os dois (LC-13/LC-33).
 */

import { readFileSync, readdirSync, existsSync, mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, basename, relative, resolve, dirname } from 'node:path';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
export const REPO_DEFAULT = resolve(HERE, '../..');

/** Caminho canônico do Design System. Uma fonte, declarada aqui e no lock. */
export const DS_CANON = 'prototipo-ui/design-system/';

/** Caminho do lock. */
export const LOCK_PATH = 'governance/design/design-lock.json';

/** Extensões que contam como "arquivo de DS" para o detector de duplicata. */
const DS_RE = /(^|\/)(design-system|tokens|colors_and_type|ds-v6)[^/]*\.(css|js|mjs)$|\/ds-v6\/[^/]+\.(css|js|mjs)$|\/ds-galerias\/[^/]+\.(css|js|mjs)$/i;

// ─────────────────────────────────────────────────────────────────────────────
// helpers
// ─────────────────────────────────────────────────────────────────────────────

export function sha256(file) {
  return createHash('sha256').update(readFileSync(file)).digest('hex');
}

export function walk(dir, out = []) {
  let ents = [];
  try { ents = readdirSync(dir, { withFileTypes: true }); } catch { return out; }
  for (const e of ents) {
    if (e.name === '.git' || e.name === 'node_modules' || e.name === 'vendor') continue;
    const p = join(dir, e.name);
    if (e.isDirectory()) walk(p, out);
    else out.push(p);
  }
  return out;
}

const rel = (root, p) => relative(root, p).replace(/\\/g, '/');

// ─────────────────────────────────────────────────────────────────────────────
// REGRA 1 — DS ÚNICO
//
// DOIS eixos, com FORÇA DIFERENTE — e a diferença foi MEDIDA, não estimada:
//
//   `duplicatas` (ACHADO)     mesmo nome de arquivo com CONTEÚDO divergente.
//                             Não depende do nome ser "de DS": duas cópias que
//                             discordam são problema em qualquer leitura. Foi
//                             este eixo que pegou o `ds-v6/tokens.css`.
//
//   `fora`       (INFORMATIVO) arquivo cujo NOME parece de DS e está fora da raiz
//                             canônica. FP MEDIDO em 2026-09-22: dos 4 acusados,
//                             `venda-v3/01-fundacoes/css/tokens-tema-escuro.css`
//                             NÃO é cópia — é override de CONTRASTE em escopo
//                             (`.cockpit[data-theme="dark"]`, 35 linhas, cabeçalho
//                             declarando *"NÃO cria token novo (ADR-0050)"*),
//                             carregado por `venda-v3/index.html:47`, e o venda-v3
//                             é a âncora viva de `Sells/CreateV3`. Apagá-lo
//                             reintroduz 8 de 29 pares reprovando WCAG AA.
//
// Classificar por NOME é o guard sintático que o §5 de `proibicoes.md` enterra 8×
// (allowlist-de-pasta · `@scope` · vocabulário 130 FP · `toHaveKey` 100% FP · …).
// Tentei um critério melhor — "declara token em `:root` = fundação" — e ele FALHOU
// NO CONTROLE POSITIVO: o próprio canônico `colors_and_type.css` mede `tokensRoot=0`
// (245 tokens, nenhum em `:root`). Sonda que não discrimina no controle não entra.
// Por isso o eixo `fora` REPORTA e não conta como achado, até existir critério que
// separe cópia de override sem ler a prosa do cabeçalho.
// ─────────────────────────────────────────────────────────────────────────────

export function checarDsUnico(repoRoot = REPO_DEFAULT) {
  const base = join(repoRoot, 'prototipo-ui');
  if (!existsSync(base)) {
    return { medi: false, motivo: `não achei ${rel(repoRoot, base)} — nada a medir`, fora: [], duplicatas: [] };
  }
  const arquivos = walk(base).map((p) => rel(repoRoot, p));
  const dsLike = arquivos.filter((f) => DS_RE.test(f));
  const fora = dsLike.filter((f) => !f.startsWith(DS_CANON));

  // duplicata por NOME com conteúdo divergente (dentro e fora da canônica)
  const porNome = new Map();
  for (const f of dsLike) {
    const b = basename(f).toLowerCase();
    if (!porNome.has(b)) porNome.set(b, []);
    porNome.get(b).push(f);
  }
  const duplicatas = [];
  for (const [nome, lista] of porNome) {
    if (lista.length < 2) continue;
    const hashes = new Map();
    for (const f of lista) {
      try { hashes.set(f, sha256(join(repoRoot, f)).slice(0, 12)); } catch { hashes.set(f, 'ILEGIVEL'); }
    }
    const distintos = new Set(hashes.values());
    if (distintos.size > 1) duplicatas.push({ nome, arquivos: [...hashes].map(([f, h]) => ({ f, h })) });
  }
  return { medi: true, total: dsLike.length, fora, duplicatas };
}

// ─────────────────────────────────────────────────────────────────────────────
// REGRA 2 — FONTE SEM AMBIGUIDADE
//   Reproduz o predicado de `ancora.mjs:512` e mede quantas telas ele resolve
//   por basename/primeiro-encontrado. Aceita o lock como desambiguador.
// ─────────────────────────────────────────────────────────────────────────────

export function fmValor(texto, chave) {
  const m = texto.match(new RegExp('^' + chave + ':\\s*(.+)$', 'm'));
  return m ? m[1].trim().replace(/^["']|["']$/g, '') : null;
}

export function mockupJsx(v) {
  if (!v) return null;
  const m = String(v).match(/[\w./-]+-page\.jsx/);
  return m ? m[0] : null;
}

/**
 * Resolve candidatos de um valor declarado.
 * REGRA: se o valor tem "/", é CAMINHO e casa por sufixo de caminho (determinístico).
 * Se é só basename, casa por basename — e AÍ pode ser ambíguo, que é o ponto.
 */
export function candidatosPara(declarado, arquivos) {
  const alvo = String(declarado).toLowerCase();
  if (alvo.includes('/')) {
    return arquivos.filter((f) => f.toLowerCase() === alvo || f.toLowerCase().endsWith('/' + alvo));
  }
  return arquivos.filter((f) => basename(f).toLowerCase() === alvo);
}

export function checarAmbiguidade(repoRoot = REPO_DEFAULT, lock = null) {
  const cowork = join(repoRoot, 'prototipo-ui', 'cowork');
  if (!existsSync(cowork)) {
    return { medi: false, motivo: `não achei ${rel(repoRoot, cowork)} — nada a medir`, ambiguas: [], orfas: [] };
  }
  const stFiles = walk(cowork).map((p) => rel(repoRoot, p));

  const raizes = [join(repoRoot, 'resources', 'js', 'Pages'), join(repoRoot, 'Modules')];
  const charters = raizes.flatMap((r) => walk(r)).filter((f) => /\.charter\.md$/.test(f));

  const travadas = new Map();
  for (const s of lock?.screens ?? []) travadas.set(String(s.id).toLowerCase(), s);

  const ambiguas = [];
  const orfas = [];
  let declaram = 0;
  let resolvidasPorLock = 0;

  for (const cf of charters) {
    let txt = '';
    try { txt = readFileSync(cf, 'utf8'); } catch { continue; }
    const declarado = mockupJsx(fmValor(txt, 'bundle_source')) || mockupJsx(fmValor(txt, 'visual_source'));
    if (!declarado) continue;
    declaram++;

    const tela = rel(repoRoot, cf).replace(/^.*?Pages\//, '').replace(/\.charter\.md$/, '');
    const travada = travadas.get(tela.toLowerCase());
    if (travada?.prototype_path) {
      // o lock manda: resolve por caminho e confere o hash
      const alvo = travada.prototype_path;
      const existe = existsSync(join(repoRoot, alvo));
      const hashOk = existe && travada.content_hash
        ? sha256(join(repoRoot, alvo)) === travada.content_hash
        : null;
      if (!existe) orfas.push({ tela, motivo: `lock aponta ${alvo}, que não existe` });
      else if (hashOk === false) orfas.push({ tela, motivo: `hash do lock não bate com ${alvo}` });
      else resolvidasPorLock++;
      continue;
    }

    const cands = candidatosPara(declarado, stFiles);
    if (cands.length === 0) { orfas.push({ tela, motivo: `declara "${declarado}" e nenhum arquivo casa` }); continue; }
    if (cands.length === 1) continue;

    const hashes = new Set(cands.map((f) => { try { return sha256(join(repoRoot, f)).slice(0, 12); } catch { return 'ERR'; } }));
    ambiguas.push({ tela, declarado, candidatos: cands, conteudosDistintos: hashes.size, escolhidoHoje: cands[0] });
  }

  return { medi: true, declaram, resolvidasPorLock, ambiguas, orfas };
}

// ─────────────────────────────────────────────────────────────────────────────
// REGRA 3 — LOCK VÁLIDO
// ─────────────────────────────────────────────────────────────────────────────

export function validarLock(lock, repoRoot = REPO_DEFAULT) {
  const erros = [];
  if (!lock || typeof lock !== 'object') return { ok: false, erros: ['lock ausente ou não é objeto'] };

  const ds = lock.design_system;
  if (!ds) erros.push('falta design_system');
  else {
    for (const campo of ['path', 'git_revision', 'content_hash', 'runtime_global', 'approved_by', 'approved_at']) {
      if (!ds[campo]) erros.push(`design_system.${campo} ausente`);
    }
    if (ds.path && !String(ds.path).startsWith(DS_CANON)) {
      erros.push(`design_system.path "${ds.path}" está fora da fonte canônica ${DS_CANON}`);
    }
    if (ds.path && ds.content_hash) {
      const abs = join(repoRoot, ds.path);
      if (!existsSync(abs)) erros.push(`design_system.path "${ds.path}" não existe`);
      else if (sha256(abs) !== ds.content_hash) erros.push(`design_system.content_hash não bate com o arquivo em "${ds.path}"`);
    }
  }

  for (const [i, s] of (lock.screens ?? []).entries()) {
    const onde = `screens[${i}]${s?.id ? ` (${s.id})` : ''}`;
    for (const campo of ['id', 'prototype_path', 'git_revision', 'content_hash', 'accepted_by', 'accepted_at']) {
      if (!s?.[campo]) erros.push(`${onde}.${campo} ausente`);
    }
    if (s?.prototype_path && !String(s.prototype_path).includes('/')) {
      erros.push(`${onde}.prototype_path "${s.prototype_path}" é basename — exige CAMINHO COMPLETO`);
    }
    if (s?.prototype_path && s?.content_hash) {
      const abs = join(repoRoot, s.prototype_path);
      if (!existsSync(abs)) erros.push(`${onde}.prototype_path "${s.prototype_path}" não existe`);
      else if (sha256(abs) !== s.content_hash) erros.push(`${onde}.content_hash não bate com o arquivo`);
    }
  }
  return { ok: erros.length === 0, erros };
}

export function lerLock(repoRoot = REPO_DEFAULT) {
  const p = join(repoRoot, LOCK_PATH);
  if (!existsSync(p)) return { existe: false, lock: null };
  try { return { existe: true, lock: JSON.parse(readFileSync(p, 'utf8')) }; }
  catch (e) { return { existe: true, lock: null, erro: String(e.message).split('\n')[0] }; }
}

// ─────────────────────────────────────────────────────────────────────────────
// CLI
// ─────────────────────────────────────────────────────────────────────────────

function imprimir(repoRoot, { ds, amb, lockInfo, lockVal }) {
  console.log('\n  DESIGN-LOCK — a fonte é declarada e provada, nunca resolvida por sorte\n');

  // LOCK
  if (!lockInfo.existe) {
    console.log(`  ▫ LOCK      ausente (${LOCK_PATH}) — nenhuma tela travada ainda`);
  } else if (!lockInfo.lock) {
    console.log(`  ⛔ LOCK      ILEGÍVEL: ${lockInfo.erro}`);
  } else if (lockVal.ok) {
    console.log(`  ✓ LOCK      válido — ${(lockInfo.lock.screens ?? []).length} tela(s) travada(s), DS aprovado por ${lockInfo.lock.design_system?.approved_by}`);
  } else {
    console.log(`  ⛔ LOCK      ${lockVal.erros.length} problema(s):`);
    for (const e of lockVal.erros.slice(0, 12)) console.log(`       - ${e}`);
  }

  // DS ÚNICO
  if (!ds.medi) console.log(`  ⛔ DS        NÃO MEDI — ${ds.motivo}`);
  else {
    if (ds.duplicatas.length === 0) console.log(`  ✓ DS ÚNICO  nenhum nome de DS com conteúdo divergente (${ds.total} arquivo(s) varrido(s))`);
    else console.log(`  ⛔ DS ÚNICO  ${ds.duplicatas.length} nome(s) com conteúdo divergente`);
    if (ds.fora.length) {
      console.log(`  ▫ informativo — ${ds.fora.length} arquivo(s) de nome "de DS" fora de ${DS_CANON}.`);
      console.log(`     NÃO é achado: o nome não prova cópia. Medido em 2026-09-22 — o`);
      console.log(`     venda-v3/tokens-tema-escuro.css é override de CONTRASTE em escopo`);
      console.log(`     (.cockpit[data-theme=dark], 35 ln, "NÃO cria token novo" · ADR-0050),`);
      console.log(`     carregado por venda-v3/index.html:47. Apagá-lo reintroduz 8 pares`);
      console.log(`     reprovando WCAG AA. Confira um a um antes de agir sobre esta lista.`);
      for (const f of ds.fora.slice(0, 10)) console.log(`       ${f}`);
    }
    {
      for (const d of ds.duplicatas.slice(0, 6)) {
        console.log(`       dup:  ${d.nome}`);
        for (const a of d.arquivos) console.log(`             ${a.h}  ${a.f}`);
      }
    }
  }

  // AMBIGUIDADE
  if (!amb.medi) console.log(`  ⛔ FONTE     NÃO MEDI — ${amb.motivo}`);
  else {
    console.log(`  ▫ FONTE     ${amb.declaram} tela(s) declaram -page.jsx · ${amb.resolvidasPorLock} resolvida(s) pelo lock`);
    if (amb.ambiguas.length === 0 && amb.orfas.length === 0) console.log('  ✓ FONTE     nenhuma ambiguidade, nenhuma órfã');
    if (amb.ambiguas.length) {
      console.log(`  ⛔ AMBÍGUA   ${amb.ambiguas.length} tela(s) com 2+ candidatos — hoje resolve o PRIMEIRO da varredura:`);
      for (const a of amb.ambiguas.slice(0, 10)) {
        console.log(`       ${a.tela}  declara "${a.declarado}"  (${a.candidatos.length} cands, ${a.conteudosDistintos} conteúdos)`);
        for (const c of a.candidatos.slice(0, 4)) console.log(`         ${c === a.escolhidoHoje ? '->' : '  '} ${c}`);
      }
      if (amb.ambiguas.length > 10) console.log(`       … +${amb.ambiguas.length - 10}`);
      console.log(`\n       SAÍDA: travar a tela no ${LOCK_PATH} com prototype_path COMPLETO + content_hash.`);
    }
    if (amb.orfas.length) {
      console.log(`  ⛔ ÓRFÃ      ${amb.orfas.length} tela(s) cuja fonte declarada não resolve:`);
      for (const o of amb.orfas.slice(0, 10)) console.log(`       ${o.tela}: ${o.motivo}`);
    }
  }
  console.log('');
}

function main() {
  const argv = process.argv.slice(2);
  const repoRoot = REPO_DEFAULT;

  if (argv.includes('--selftest')) return selftest();

  const iHash = argv.indexOf('--hash');
  if (iHash >= 0) {
    const f = argv[iHash + 1];
    if (!f) { console.error('uso: --hash <arquivo>'); process.exit(3); }
    if (!existsSync(f)) { console.error(`⛔ NÃO MEDI — "${f}" não existe`); process.exit(2); }
    console.log(sha256(f));
    return;
  }

  const soDs = argv.includes('--ds');
  const soAmb = argv.includes('--ambiguidade');
  const strict = argv.includes('--strict');

  const lockInfo = lerLock(repoRoot);
  const lockVal = lockInfo.lock ? validarLock(lockInfo.lock, repoRoot) : { ok: !lockInfo.existe, erros: lockInfo.existe ? [`lock ilegível: ${lockInfo.erro}`] : [] };

  const ds = soAmb ? { medi: true, total: 0, fora: [], duplicatas: [] } : checarDsUnico(repoRoot);
  const amb = soDs ? { medi: true, declaram: 0, resolvidasPorLock: 0, ambiguas: [], orfas: [] } : checarAmbiguidade(repoRoot, lockInfo.lock);

  imprimir(repoRoot, { ds, amb, lockInfo, lockVal });

  // NÃO MEDI tem código próprio — nunca colapsar em "ok" (LC-33)
  if (ds.medi === false || amb.medi === false) {
    console.error('  ⛔ NÃO MEDI — entrada ausente. Isto NÃO é "está tudo certo".\n');
    process.exit(2);
  }

  // `ds.fora` NÃO entra: é INFORMATIVO, com FP medido (ver checarDsUnico). O achado
  // que conta no eixo DS é a duplicata por CONTEÚDO, que não depende do nome.
  const achados = ds.duplicatas.length + amb.ambiguas.length + amb.orfas.length + lockVal.erros.length;
  if (achados && strict) {
    console.error(`  ⛔ ${achados} achado(s) sob --strict\n`);
    process.exit(1);
  }
  if (achados) console.log(`  ▫ ${achados} achado(s) — advisory (use --strict para falhar)\n`);
  process.exit(0);
}

// ─────────────────────────────────────────────────────────────────────────────
// SELFTEST — cada regra com caso BOM e caso RUIM, em sandbox hermético.
// O pedido é explícito: "os casos negativos precisam falhar de modo observável".
// ─────────────────────────────────────────────────────────────────────────────

function selftest() {
  let ok = 0, fail = 0;
  const t = (nome, cond, detalhe = '') => {
    if (cond) { ok++; console.log(`  ✓ ${nome}`); }
    else { fail++; console.log(`  ✗ ${nome}${detalhe ? '  — ' + detalhe : ''}`); }
  };

  const raiz = mkdtempSync(join(tmpdir(), 'design-lock-'));
  const put = (p, txt) => { mkdirSync(dirname(join(raiz, p)), { recursive: true }); writeFileSync(join(raiz, p), txt); };

  console.log('\n  design-lock --selftest\n');

  // ── DS ÚNICO ──
  put(DS_CANON + 'colors_and_type.css', ':root{--a:1}');
  let r = checarDsUnico(raiz);
  t('DS BOM: uma única cópia sob a canônica passa', r.medi && r.fora.length === 0 && r.duplicatas.length === 0,
    `fora=${r.fora.length} dup=${r.duplicatas.length}`);

  put('prototipo-ui/cowork/Felipe/ds-galerias/tokens.css', ':root{--a:2}');
  r = checarDsUnico(raiz);
  t('DS: arquivo de nome "de DS" fora da canônica é REPORTADO (informativo)', r.fora.length === 1,
    `fora=${JSON.stringify(r.fora)}`);

  put(DS_CANON + 'tokens.css', ':root{--a:9}');
  r = checarDsUnico(raiz);
  t('DS RUIM: mesmo nome com conteúdo DIVERGENTE vira duplicata', r.duplicatas.length === 1,
    `dup=${r.duplicatas.length}`);

  // CONTROLE DO FP REAL (incidente 2026-09-22): override de contraste em escopo,
  // com nome "tokens-*", NÃO pode virar achado — apagá-lo reintroduz falha WCAG AA.
  const raizO = mkdtempSync(join(tmpdir(), 'design-lock-fp-'));
  const putO = (p, txt) => { mkdirSync(dirname(join(raizO, p)), { recursive: true }); writeFileSync(join(raizO, p), txt); };
  putO(DS_CANON + 'colors_and_type.css', ':root{--fg:oklch(.2 0 0)}');
  putO('prototipo-ui/cowork/Wagner/venda-v3/01-fundacoes/css/tokens-tema-escuro.css',
    '/* NAO cria token novo (ADR-0050) */\n.cockpit[data-theme="dark"]{--fg:oklch(.94 .005 90)}');
  const rO = checarDsUnico(raizO);
  t('DS CONTROLE-FP: override escopado com nome "tokens-*" NÃO vira achado (duplicatas=0)',
    rO.duplicatas.length === 0, `dup=${rO.duplicatas.length}`);
  t('DS CONTROLE-FP: ele aparece no informativo, não no achado', rO.fora.length === 1,
    `fora=${rO.fora.length}`);

  // controle negativo: mesmo nome, MESMO conteúdo, não é duplicata
  const raiz2 = mkdtempSync(join(tmpdir(), 'design-lock-b-'));
  const put2 = (p, txt) => { mkdirSync(dirname(join(raiz2, p)), { recursive: true }); writeFileSync(join(raiz2, p), txt); };
  put2(DS_CANON + 'tokens.css', 'IGUAL');
  put2('prototipo-ui/design-system/sub/tokens.css', 'IGUAL');
  const r2 = checarDsUnico(raiz2);
  t('DS CONTROLE: mesmo nome + MESMO conteúdo NÃO é duplicata', r2.duplicatas.length === 0, `dup=${r2.duplicatas.length}`);

  // ── AMBIGUIDADE ──
  const raizA = mkdtempSync(join(tmpdir(), 'design-lock-c-'));
  const putA = (p, txt) => { mkdirSync(dirname(join(raizA, p)), { recursive: true }); writeFileSync(join(raizA, p), txt); };
  putA('resources/js/Pages/Mod/Tela.charter.md', '---\nbundle_source: foo-page.jsx\n---\n');
  putA('prototipo-ui/cowork/Felipe/foo-page.jsx', 'A');
  let a = checarAmbiguidade(raizA, null);
  t('FONTE BOM: 1 candidato resolve sem ambiguidade', a.medi && a.ambiguas.length === 0 && a.orfas.length === 0,
    `amb=${a.ambiguas.length} orfa=${a.orfas.length}`);

  putA('prototipo-ui/cowork/Wagner/foo-page.jsx', 'B-DIFERENTE');
  a = checarAmbiguidade(raizA, null);
  t('FONTE RUIM: 2 candidatos divergentes = AMBÍGUA', a.ambiguas.length === 1 && a.ambiguas[0].conteudosDistintos === 2,
    `amb=${a.ambiguas.length}`);

  // o lock desambigua
  const lockOk = {
    design_system: { path: DS_CANON + 'x.css', git_revision: 'abc', content_hash: 'z', runtime_global: 'window.OIDS', approved_by: 'Wagner', approved_at: '2026-09-22' },
    screens: [{ id: 'Mod/Tela', prototype_path: 'prototipo-ui/cowork/Wagner/foo-page.jsx', git_revision: 'abc', content_hash: sha256(join(raizA, 'prototipo-ui/cowork/Wagner/foo-page.jsx')), accepted_by: 'Wagner', accepted_at: '2026-09-22' }],
  };
  a = checarAmbiguidade(raizA, lockOk);
  t('FONTE BOM: o LOCK desambigua (0 ambíguas, 1 resolvida por lock)',
    a.ambiguas.length === 0 && a.resolvidasPorLock === 1, `amb=${a.ambiguas.length} lock=${a.resolvidasPorLock}`);

  // lock com hash divergente
  const lockRuim = JSON.parse(JSON.stringify(lockOk));
  lockRuim.screens[0].content_hash = 'f'.repeat(64);
  a = checarAmbiguidade(raizA, lockRuim);
  t('LOCK RUIM: hash divergente vira ÓRFÃ (não passa calado)', a.orfas.length === 1, `orfa=${a.orfas.length}`);

  // ── CAMINHO COMPLETO (o conserto do :512) ──
  const raizP = mkdtempSync(join(tmpdir(), 'design-lock-d-'));
  const putP = (p, txt) => { mkdirSync(dirname(join(raizP, p)), { recursive: true }); writeFileSync(join(raizP, p), txt); };
  putP('resources/js/Pages/Mod/T2.charter.md', '---\nbundle_source: prototipo-ui/cowork/Wagner/bar-page.jsx\n---\n');
  putP('prototipo-ui/cowork/Felipe/bar-page.jsx', 'A');
  putP('prototipo-ui/cowork/Wagner/bar-page.jsx', 'B');
  const p = checarAmbiguidade(raizP, null);
  t('CAMINHO: valor com "/" resolve 1 só (não vira ambíguo nem órfão)',
    p.ambiguas.length === 0 && p.orfas.length === 0, `amb=${p.ambiguas.length} orfa=${p.orfas.length}`);

  // controle: o predicado ANTIGO (basename cru) NÃO resolveria esse caso
  const antigo = walk(join(raizP, 'prototipo-ui')).map((x) => rel(raizP, x))
    .filter((f) => basename(f).toLowerCase() === 'prototipo-ui/cowork/wagner/bar-page.jsx');
  t('CONTROLE: o predicado ANTIGO (basename === valor cru) resolvia ZERO aqui', antigo.length === 0,
    `antigo=${antigo.length}`);

  // ── LOCK: validação de campos ──
  let v = validarLock({ design_system: {}, screens: [] }, raizA);
  t('LOCK RUIM: design_system sem campos é reprovado', !v.ok && v.erros.length >= 6, `erros=${v.erros.length}`);

  v = validarLock({ design_system: { path: 'outro/lugar/x.css', git_revision: 'a', content_hash: 'b', runtime_global: 'c', approved_by: 'd', approved_at: 'e' }, screens: [] }, raizA);
  t('LOCK RUIM: DS fora da canônica é reprovado', !v.ok && v.erros.some((e) => /fora da fonte canônica/.test(e)));

  v = validarLock({ design_system: { path: DS_CANON + 'x.css', git_revision: 'a', content_hash: 'b', runtime_global: 'c', approved_by: 'd', approved_at: 'e' },
    screens: [{ id: 'A/B', prototype_path: 'foo-page.jsx', git_revision: 'a', content_hash: 'b', accepted_by: 'c', accepted_at: 'd' }] }, raizA);
  t('LOCK RUIM: prototype_path por BASENAME é reprovado', !v.ok && v.erros.some((e) => /basename/.test(e)));

  // ── NÃO MEDI ≠ OK ──
  const vazio = mkdtempSync(join(tmpdir(), 'design-lock-e-'));
  const nm = checarDsUnico(vazio);
  t('NÃO MEDI: árvore sem prototipo-ui devolve medi=false (não "ok")', nm.medi === false, `medi=${nm.medi}`);
  const nmA = checarAmbiguidade(vazio, null);
  t('NÃO MEDI: sem cowork/ o eixo de fonte devolve medi=false', nmA.medi === false, `medi=${nmA.medi}`);

  for (const d of [raiz, raiz2, raizA, raizP, raizO, vazio]) { try { rmSync(d, { recursive: true, force: true }); } catch {} }

  console.log(`\n  ${ok} ok · ${fail} falha(s)\n`);
  process.exit(fail ? 1 : 0);
}

if (process.argv[1]?.endsWith('design-lock.mjs')) main();
