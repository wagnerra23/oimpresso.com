#!/usr/bin/env node
// @ts-check
/**
 * anchor-content-check.mjs — sentinela de CONTEÚDO da âncora de design.
 *
 * Fecha o buraco que o Wagner pegou no instinto (2026-07-06): o `ancora.mjs` +
 * `block-ancora-no-olho.mjs` provam que a âncora está DECLARADA no charter
 * (proveniência), mas NUNCA abrem o arquivo pra ver se o conteúdo BATE com a tela
 * (correção). Charter apontando pro shell do app ou pra arquivo que sumiu passava limpo.
 * Medido no dia da criação: 2 de 9 âncoras reais estavam podres e nenhum gate pegou —
 * Financeiro/Unificado→oimpresso.com.html (shell) · Financeiro/Fluxo→Financeiro.html (sumiu).
 *
 * "Presença ≠ correção" (L-24 / adversário 2026-07-06). Este sentinela abre a âncora.
 *
 * Classificação (determinística, zero LLM):
 *   MISSING  — o arquivo da âncora não existe (sumiu num refactor). Podre.
 *   SHELL    — .html que linka ≥ SHELL_MIN_CSS stylesheets = índice/container do app
 *              inteiro, não a tela. Podre (deve apontar pro fonte da tela específica).
 *   NO-MODULE— o arquivo existe mas não menciona NENHUMA vez o nome do módulo da tela.
 *              Suspeita (âncora provavelmente errada).
 *   NO-SECTION— o related_prototype DECLARA uma seção no parêntese (`arquivo.jsx (TelaX)`),
 *              mas `TelaX` NÃO resolve a nenhum export real (`const/window.TelaX`) no arquivo.
 *              Dead-anchor de FRAGMENTO — a mesma classe do MISSING, um nível abaixo (o arquivo
 *              existe, mas a seção nomeada não). Warn. Fecha o buraco dos N charters que
 *              compartilham UM arquivo (ex.: 4 telas Financeiro → financeiro-telas-extras.jsx):
 *              hoje o gate só via "arquivo existe + cita o módulo" e passava as N IDÊNTICAS,
 *              cego a qual seção cada uma aponta. Só olha a seção que o charter DECLAROU no
 *              parêntese — NUNCA deriva o nome da tela (isso seria adivinhar-por-nome, §5
 *              2026-06-30). E é WARN, não hard-fail "seção presente = OK" (L-24; presença ≠
 *              correção) — sinal de drift hoje = zero (os parênteses estão 1:1 corretos).
 *   OK       — arquivo existe, é fonte de tela (não-shell), menciona o módulo, e (se declara
 *              seção) a seção resolve.
 *
 * REQUIRED desde 2026-07-08 (ADR 0327 — emenda à 0314, exceção consciente à "required = só
 * Tier-0", Wagner autorizado). Revoga o "advisory" antigo, que deixou a âncora podre REINCIDIR
 * (07-06→07-08). Com --check sai 1 se houver âncora MISSING/SHELL (o sinal duro); NO-MODULE e
 * NO-SECTION são warn (nomenclatura / drift latente). Job dedicado hard-fail em
 * anchor-content-required.yml; o design-memory-gate.yml segue advisory pros outros steps.
 *
 * ── STAGING (2026-08-24) — o universo que este gate NÃO enxergava ────────────────────────
 *
 * O gate varre `raizesDePages()` = charter VIVO (núcleo + módulos). Os charters que o Cowork
 * emite pousam antes em `prototipo-ui/design-docs/` (decisão [W] 2026-08-21), e lá ninguém
 * olhava: MEDIDO em 2026-08-24 no corpus real — 0 âncoras podres no vivo, **15 no staging**,
 * com o gate saindo rc=0. O padrão é único e mecânico: o Cowork escreve a âncora com pasta de
 * módulo (`cowork/ponto/ponto-telas.jsx`) e o arquivo pousa plano (`cowork/ponto-telas.jsx`).
 *
 * POR QUE ISSO **NÃO** ENTRA NO CAMINHO REQUIRED, e a decisão é medida, não cautela:
 *   ampliar o universo do `--check` levaria o job de 0 → 15 podres. Como ele roda em TODO PR
 *   (sem `paths:`), o merge do repo inteiro travaria por dívida que nenhum PR causou. É a
 *   forma exata que o §5 já enterrou duas vezes em 2026-08-24 — predicado ABSOLUTO onde cabia
 *   DELTA, e gate reprovando por artefato fora do raio do PR.
 *
 * Então o staging entra por flag PRÓPRIA, advisory, com exit code próprio:
 *   --incluir-staging   amplia só o RELATÓRIO (o `--check` segue mordendo apenas no vivo)
 *   --check-staging     exit 1 nos podres do staging — para uso deliberado em job advisory
 *
 * `prototipo-ui/fixtures/**` fica FORA por desenho: são fixtures sintéticas do selftest do
 * `detectar-telas`, com âncora deliberadamente inválida. Cobrá-las seria medir o dado de teste.
 *
 * O conserto de raiz é upstream e já está feito: o `aplicar-payload.mjs` normaliza a âncora na
 * ENTRADA (forward-only). Este modo é a rede — mede o passivo e flagra se a normalização
 * regredir. Corrigir os 15 arquivos à mão seria editar ESPELHO: some no próximo `--export-from`
 * (§5 2026-08-13).
 *
 * Uso:
 *   node scripts/governance/anchor-content-check.mjs                    # relatório (vivo)
 *   node scripts/governance/anchor-content-check.mjs --check            # exit 1 se MISSING/SHELL no VIVO
 *   node scripts/governance/anchor-content-check.mjs --incluir-staging  # relatório vivo + staging
 *   node scripts/governance/anchor-content-check.mjs --check-staging    # exit 1 se podre no staging (advisory)
 */

import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, sep } from 'node:path';
// DUAS RAÍZES desde o PR #5686 — a tela pode morar no núcleo ou dentro do módulo dono. Varrer só
// o núcleo media 15 âncoras resolvíveis de 19: as 4 de fora (3 `forja-page.jsx` + 1
// `pg-payment-gateways-page.jsx`) não eram auditadas por gate nenhum, e este é REQUIRED
// (ADR 0327). `page-path.mjs` é o dono da lista de raízes — não reimplementar a 2ª aqui.
import { raizesDePages, pageNamespacePath } from '../qa/page-path.mjs';

const ROOT = process.cwd();
const COWORK = join(ROOT, 'prototipo-ui', 'cowork');
export const SHELL_MIN_CSS = 10; // ≥10 <link stylesheet> = índice do app, não uma tela

/** Extrai o caminho de arquivo (.jsx/.html) do valor de related_prototype, ou null se é prosa. */
export function anchorFile(val) {
  if (!val) return null;
  if (/^n\/a\b/i.test(val) || /MIS-ANCHOR|removido/i.test(val)) return null;
  const m = val.match(/([\w.\-]+\.(?:jsx|html))/i); // nome do arquivo (com ou sem dir)
  return m ? m[1] : null;
}

/** Caminho RELATIVO dentro do espelho (`prototipo-ui/cowork/`), preservando subdiretórios.
 *  Identidade de arquivo é por PATH COMPLETO, nunca basename — dois arquivos homônimos em
 *  subdirs diferentes são arquivos DIFERENTES (adversário 2026-07-06: colisão por basename
 *  fazia "byte-provado" mentir; arte 2026-07-06: hash normalizado keyed por path completo). */
export function anchorRelPath(val) {
  if (!val) return null;
  if (/^n\/a\b/i.test(val) || /MIS-ANCHOR|removido/i.test(val)) return null;
  const m = val.match(/([\w.\-\/]+\.(?:jsx|html))/i); // caminho (com / de subdir) ou nome solto
  if (!m) return null;
  return m[1].replace(/^.*?cowork\//i, ''); // corta o prefixo até cowork/ — resto é o rel path
}

/** Conta <link rel="stylesheet"> num HTML (assinatura de shell/índice do app). */
export function stylesheetCount(text) {
  return (text.match(/<link[^>]+rel=["']stylesheet["']/gi) || []).length;
}

/** Seção DECLARADA no parêntese logo após o path: `arquivo.jsx (TelaX)` → 'TelaX', ou null.
 *  Só reconhece o padrão `(Identificador)` colado ao arquivo (o convention dos charters que
 *  compartilham 1 .jsx). Parêntese de PROSA — `(design real da Visão Unificada; ...)`,
 *  `(formalizado 2026-07-09 ...)`, `(linhas 123-354)` — devolve null (não é seção). Regras que
 *  separam seção de prosa: (a) o parêntese vem IMEDIATAMENTE após o path (só espaço no meio);
 *  (b) começa com identificador PascalCase; (c) esse identificador é "fechado" — seguido de
 *  `;`, `,` ou fim-do-parêntese (não `Palavra mais palavras` de prosa). NUNCA deriva o nome da
 *  tela (isso seria adivinhar-por-nome, §5 2026-06-30) — lê SÓ o que o charter escreveu. */
export function anchorFragment(val) {
  if (!val) return null;
  if (/^n\/a\b/i.test(val) || /MIS-ANCHOR|removido/i.test(val)) return null;
  const fileM = val.match(/[\w.\-\/]+\.(?:jsx|html)/i);
  if (!fileM) return null;
  const after = val.slice(fileM.index + fileM[0].length);
  const paren = after.match(/^\s*\(([^)]*)\)/); // parêntese COLADO ao path (só espaço permitido)
  if (!paren) return null;
  const frag = paren[1].match(/^\s*([A-Z][A-Za-z0-9_$]*)\s*(?:[;,]|$)/); // PascalCase "fechado"
  return frag ? frag[1] : null;
}

/** A seção declarada resolve a um export REAL no corpo do arquivo? `const/let/var/function/
 *  class TelaX`, `window.TelaX`, `export ... TelaX`, ou `TelaX =`/`TelaX:`. Dead-anchor de
 *  fragmento = declara `(TelaX)` mas o arquivo não tem `TelaX`. Sem seção declarada → true
 *  (nada a resolver). É integridade de PONTEIRO (como o MISSING checa o arquivo), não juízo de
 *  conteúdo — a fidelidade seção⇔tela segue advisory/local por lei (ADR 0290). */
export function fragmentResolves(body, frag) {
  if (!frag) return true;
  const f = frag.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const re = new RegExp(
    '\\b(?:const|let|var|function|class)\\s+' + f + '\\b' +
    '|window\\.' + f + '\\b' +
    '|\\bexport\\b[^\\n]*\\b' + f + '\\b' +
    '|\\b' + f + '\\s*[=:]',
  );
  return re.test(body || '');
}

/** Classifica (pura, testável) a partir dos fatos. `sectionDeclared`/`sectionResolves` são
 *  opcionais (default false/false → "sem seção declarada", retrocompatível): só entram no
 *  veredito NO-SECTION quando o charter DECLARA uma seção que não resolve. */
export function classifyAnchor({ exists, isHtml, stylesheetLinks, moduleHits, sectionDeclared = false, sectionResolves = false }) {
  if (!exists) return 'MISSING';
  if (isHtml && stylesheetLinks >= SHELL_MIN_CSS) return 'SHELL';
  if (moduleHits === 0) return 'NO-MODULE';
  if (sectionDeclared && !sectionResolves) return 'NO-SECTION';
  return 'OK';
}

function walkCharters(dir, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir)) {
    const f = join(dir, e);
    if (statSync(f).isDirectory()) walkCharters(f, acc);
    else if (f.endsWith('.charter.md')) acc.push(f);
  }
  return acc;
}

/** Raiz do staging: onde o `aplicar-payload` pousa os `.md` do Cowork (decisão [W] 2026-08-21). */
export const STAGING_DOCS = join('prototipo-ui', 'design-docs');

function main() {
  const strict = process.argv.includes('--check');
  const checkStaging = process.argv.includes('--check-staging');
  const comStaging = checkStaging || process.argv.includes('--incluir-staging');
  const rows = [];
  const raizes = raizesDePages(ROOT);
  const stagingAbs = join(ROOT, STAGING_DOCS);
  if (comStaging) raizes.push(stagingAbs);
  for (const charter of raizes.flatMap((raiz) => walkCharters(raiz))) {
    const ehStaging = charter.startsWith(stagingAbs);
    const t = readFileSync(charter, 'utf8');
    const m = t.match(/^related_prototype:\s*(.+)$/m);
    if (!m) continue;
    const rawAnchor = m[1].trim();
    const file = anchorRelPath(rawAnchor);
    if (!file) continue; // prosa não-resolvível — fora do escopo deste sentinela
    // Namespace da tela (`Forja/Inbox/Index`), idêntico nas duas raízes — descascar por
    // `PAGES.length` deixaria o prefixo `Modules/<X>/Resources/js/Pages/` colado no nome.
    // No staging o charter vem embrulhado na árvore do vivo (`design-docs/<qualquer>/resources/
    // js/Pages/Ponto/Index.charter.md`), e sem descascar o prefixo o "módulo" viraria
    // `prototipo-ui` — o que geraria NO-MODULE FALSO em toda âncora do staging assim que elas
    // deixassem de ser MISSING. Defeito latente, invisível hoje só porque as 15 são MISSING.
    const relBruto = charter.slice(ROOT.length + 1).split(sep).join('/');
    const semPrefixo = ehStaging ? relBruto.replace(/^.*?(?:resources\/js\/Pages\/|Resources\/js\/Pages\/)/, '') : relBruto;
    const rel = pageNamespacePath(semPrefixo).replace(/\.charter\.md$/, '');
    const modulo = rel.split('/')[0].toLowerCase();
    const frag = anchorFragment(rawAnchor); // seção declarada no parêntese, ou null
    const abs = join(COWORK, file); // path completo dentro do espelho (subdir preservado)
    const exists = existsSync(abs);
    let isHtml = /\.html$/i.test(file), stylesheetLinks = 0, moduleHits = 0, sectionResolves = false;
    if (exists) {
      const body = readFileSync(abs, 'utf8');
      stylesheetLinks = stylesheetCount(body);
      moduleHits = (body.toLowerCase().match(new RegExp(modulo, 'g')) || []).length;
      sectionResolves = fragmentResolves(body, frag);
    }
    // `plano` = o basename existe no espelho? Só faz sentido pra âncora com subdiretório, e é
    // o que separa "o Cowork escreveu a pasta de módulo" de "o arquivo sumiu de verdade" —
    // duas causas com conserto diferente, que sem esta coluna sairiam com a mesma cara.
    const plano = !exists && file.includes('/') && existsSync(join(COWORK, file.slice(file.lastIndexOf('/') + 1)));
    rows.push({ tela: rel, file, frag, staging: ehStaging, plano, veredito: classifyAnchor({ exists, isHtml, stylesheetLinks, moduleHits, sectionDeclared: !!frag, sectionResolves }) });
  }

  const ehPodre = (r) => r.veredito === 'MISSING' || r.veredito === 'SHELL';
  const vivas = rows.filter((r) => !r.staging);
  const stg = rows.filter((r) => r.staging);
  const podre = vivas.filter(ehPodre);
  const suspeita = vivas.filter((r) => r.veredito === 'NO-MODULE');
  const naSecao = vivas.filter((r) => r.veredito === 'NO-SECTION');
  const ok = vivas.filter((r) => r.veredito === 'OK');
  const podreStg = stg.filter(ehPodre);

  console.log(`\n  ÂNCORA DE DESIGN — checagem de conteúdo (${vivas.length} charters vivos com âncora resolvível)\n`);
  for (const r of podre) console.log(`  ⛔ ${r.veredito.padEnd(10)} ${r.tela}  →  ${r.file}`);
  for (const r of suspeita) console.log(`  🟡 ${r.veredito.padEnd(10)} ${r.tela}  →  ${r.file}`);
  for (const r of naSecao) console.log(`  🟡 ${r.veredito.padEnd(10)} ${r.tela}  →  ${r.file} (${r.frag})`);
  console.log(`\n  ⛔ podre (sumiu/shell): ${podre.length} · 🟡 0 módulo: ${suspeita.length} · 🟡 seção morta: ${naSecao.length} · ✓ ok: ${ok.length}\n`);

  if (comStaging) {
    const colapsavel = podreStg.filter((r) => r.plano);
    console.log(`  STAGING (${STAGING_DOCS.split(sep).join('/')}) — ${stg.length} charter(s) com âncora resolvível`);
    for (const r of podreStg) {
      const base = r.file.slice(r.file.lastIndexOf('/') + 1);
      console.log(`  ${r.plano ? '🟠' : '⛔'} ${r.veredito.padEnd(10)} ${r.tela}  →  ${r.file}${r.plano ? `   (existe plano: ${base})` : ''}`);
    }
    console.log(`\n  ⛔ podre no staging: ${podreStg.length} · dos quais ${colapsavel.length} resolvem pela forma PLANA`);
    if (colapsavel.length) {
      console.log('  ↳ causa: o Cowork emite a âncora com pasta de módulo e o arquivo pousa plano.');
      console.log('    Conserto = upstream, no `aplicar-payload.mjs` (normaliza na entrada, forward-only).');
      console.log('    Editar estes arquivos à mão é mexer em ESPELHO — some no próximo --export-from.\n');
    } else console.log('');
  }

  if (strict && podre.length) {
    console.error(`✗ ${podre.length} âncora(s) PODRE(s) — charter aponta pro arquivo errado. Corrija o related_prototype pro fonte real da tela.`);
    process.exit(1);
  }
  // Exit code PRÓPRIO do staging — nunca no caminho do `--check`, que é required e travaria o
  // repo por dívida herdada (ver o docblock: 0 podre no vivo × 15 no staging, medido).
  if (checkStaging && podreStg.length) {
    console.error(`✗ ${podreStg.length} âncora(s) PODRE(s) no staging (advisory — não é o gate required).`);
    process.exit(1);
  }
  console.log(`✓ sem âncora podre (MISSING/SHELL)${comStaging && !podreStg.length ? ' — vivo e staging' : ''}.`);
}

if (process.argv[1] && process.argv[1].endsWith('anchor-content-check.mjs')) main();
