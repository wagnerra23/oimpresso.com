#!/usr/bin/env node
/**
 * sdd-output-lint — mede a QUALIDADE do artefato que o agent `sdd-from-source` (ADR 0351) produz.
 *
 * POR QUE EXISTE (evidência, não teoria — sessão 2026-07-26):
 * as 5 regras adicionadas à definição do agent entre as corridas B0 e B1 foram violadas **5 de 5**
 * na corrida seguinte. Instrução em prompt é advisory por natureza: compete com o resto do contexto
 * e perde. É a doutrina da ADR 0256 (derivado+enforçado sobrevive; escrito+lembrado apodrece)
 * aplicada ao próprio agente.
 *
 * FRONTEIRA COM O DONO DO TEMA (não duplica régua — proibicoes §5 2026-07-09):
 *   - `casos-coverage-guard.mjs` (required) = COBERTURA e RASTREABILIDADE (G-1..G-7):
 *     trio existe? UC tem teste que o cita? metadata viva? status derivado do resultado?
 *   - este lint = QUALIDADE DO ASSERT e ANCORAGEM do que foi escrito. Nenhum check aqui
 *     responde uma pergunta que o casos-guard já responde.
 *
 * MODO PADRÃO É MEDIR, NÃO BLOQUEAR. `--measure` imprime o corpus inteiro com a classificação
 * pra que o falso-positivo seja CONTADO antes de qualquer proposta de promoção. Guard sintático
 * que bloqueia o legítimo é o erro catalogado em proibicoes §5 (allowlist-de-pasta 2026-06-30,
 * guard `@scope` 2026-07-09, gate de vocabulário 130 FP 2026-07-16).
 *
 * Uso:
 *   node scripts/governance/sdd-output-lint.mjs --measure     # censo do corpus + taxa de FP aparente
 *   node scripts/governance/sdd-output-lint.mjs <arquivos...>  # checa alvos específicos
 *   node scripts/governance/sdd-output-lint.mjs --selftest     # bite-test: fixture ruim MORDE, boa passa
 */

import { readFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { join, relative, basename } from 'node:path';

const ROOT = process.cwd();
const args = process.argv.slice(2);

// ── C1 · refs `arquivo:NNN` sem sha ────────────────────────────────────────────
// Um `Controller.php:443` vira mentira no primeiro refactor. A âncora durável é o SÍMBOLO
// (`ProductController@show`) + o grep que o re-localiza. `:NNN` só vale com sha declarado.
const RE_REF_LINHA = /\b[\w./-]+\.(php|tsx?|jsx?|blade\.php|ya?ml|mjs):\d+(-\d+)?\b/g;
const RE_SHA = /\b(verificado@|sha[: ]|@)[0-9a-f]{7,40}\b/i;

// ── C2 · assert de contrato acoplado a CHAVE literal ───────────────────────────
// ⛔ DESLIGADO POR PADRÃO — 100% de falso-positivo MEDIDO (2026-07-26, corpus real).
//
// A hipótese era boa: `not->toHaveKey('cost')` passa VERDE se alguém só renomear o campo, e
// `toHaveKey('location_name')` reprova o fix legítimo que emite a chave com outro nome.
// Rodado no corpus (8 testes de contrato), o check apontou 6 casos — e os 6 são LEGÍTIMOS:
//
//   ProdutoShowContratoTest:130   → a string está DENTRO DE UM COMENTÁRIO que explica por que
//                                    NÃO usar toHaveKey. O lint acusou a própria documentação da regra.
//   ProdutoIndexContratoTest:84   → `toHaveKey('props')` é sanidade do envelope Inertia.
//   StockHistoryContratoTest:101  → `toHaveKey('movements')`: o contrato É a prop existir.
//   StockHistoryContratoTest:147  → `not->toHaveKey('movements')`: contrato de `Inertia::defer`
//                                    — "a prop cara NÃO viaja no primeiro response". A ausência
//                                    da chave é literalmente o comportamento sob teste.
//   StockHistoryContratoTest:149/150 → filtros baratos vêm eager; presença é o contrato.
//
// A RAZÃO É CONCEITUAL, NÃO DE IMPLEMENTAÇÃO: quando o mecanismo sob teste é *presença de chave*
// (props deferidas, envelope), `toHaveKey` é o assert CERTO. Distinguir "chave como proxy de um
// valor de domínio" de "chave como o próprio contrato" exigiria uma denylist de nomes
// (cost/price/margin/…) — que é o critério sintático já morto no §5 (allowlist-de-pasta
// 2026-06-30 · guard `@scope` 2026-07-09 · gate de vocabulário 130 FP 2026-07-16).
//
// Fica como `--experimental` para quem quiser re-medir com outra ideia. NÃO instalar como gate.
const RE_TO_HAVE_KEY = /(?:->not)?->toHaveKey\(\s*['"]([^'"]+)['"]\s*\)/g;
// Pré-condição anti-vácuo é LEGÍTIMA: provar que a prop chegou antes de asserir sobre ela.
// Heurística conservadora: se as ~6 linhas acima falam de pré-condição/anti-vácuo/chegou, não conta.
const RE_PRECONDICAO = /anti-v[áa]cuo|pr[ée][- ]condi|prop .*chegou|CONTROLE:|sanidade|fixture/i;

function listarArquivos(dir, filtro, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const nome of readdirSync(dir)) {
    if (nome === 'node_modules' || nome === '.git' || nome === 'vendor') continue;
    const p = join(dir, nome);
    let st;
    try { st = statSync(p); } catch { continue; }
    if (st.isDirectory()) listarArquivos(p, filtro, acc);
    else if (filtro(p)) acc.push(p);
  }
  return acc;
}

function checarRefsDeLinha(rel, src) {
  const achados = [];
  const linhas = src.split(/\r?\n/);
  linhas.forEach((ln, i) => {
    const refs = ln.match(RE_REF_LINHA);
    if (!refs) return;
    if (RE_SHA.test(ln)) return;               // ref com sha declarado é durável
    for (const ref of refs) achados.push({ check: 'C1', linha: i + 1, alvo: ref });
  });
  return achados;
}

function checarAssertPorChave(rel, src) {
  const achados = [];
  const linhas = src.split(/\r?\n/);
  linhas.forEach((ln, i) => {
    RE_TO_HAVE_KEY.lastIndex = 0;
    let m;
    while ((m = RE_TO_HAVE_KEY.exec(ln)) !== null) {
      const contexto = linhas.slice(Math.max(0, i - 6), i + 1).join('\n');
      const ehPrecondicao = RE_PRECONDICAO.test(contexto);
      achados.push({
        check: 'C2', linha: i + 1, alvo: m[1],
        classe: ehPrecondicao ? 'precondicao(legitimo)' : 'contrato(acoplado)',
      });
    }
  });
  return achados;
}

const C2_LIGADO = args.includes('--experimental');

function analisar(rel, src) {
  const ehCasos = rel.endsWith('.casos.md');
  const ehTesteContrato = /Contrato\w*Test\.php$/.test(basename(rel));
  const out = [];
  if (ehCasos) out.push(...checarRefsDeLinha(rel, src));
  if (ehTesteContrato && C2_LIGADO) out.push(...checarAssertPorChave(rel, src));
  return out;
}

function ler(p) { try { return readFileSync(p, 'utf8'); } catch { return ''; } }

// ── SELFTEST (bite-test: prova que MORDE o ruim e NÃO morde o bom) ─────────────
if (args.includes('--selftest')) {
  const casos = [
    { nome: 'C1 MORDE: ref de linha sem sha', arq: 'x.casos.md',
      src: 'Ver `ProductController.php:443` para o builder.', espera: 1 },
    { nome: 'C1 NÃO morde: ref com sha declarado', arq: 'x.casos.md',
      src: 'Ver `ProductController.php:443` (verificado@a1b2c3d).', espera: 0 },
    { nome: 'C1 NÃO morde: símbolo sem número', arq: 'x.casos.md',
      src: 'Ver `ProductController@show` (grep `function show`).', espera: 0 },
    { nome: 'C2 MORDE: assert de contrato por chave', arq: 'FooContratoTest.php',
      src: "// O CONTRATO: o custo não viaja.\nexpect($linha)->not->toHaveKey('cost');", espera: 1 },
    { nome: 'C2 NÃO morde (classe): pré-condição anti-vácuo', arq: 'FooContratoTest.php',
      src: "// PRÉ-CONDIÇÃO ANTI-VÁCUO: a prop chegou.\nexpect($props)->toHaveKey('rows');", espera: 1 },
    { nome: 'C2 NÃO morde: assert por valor', arq: 'FooContratoTest.php',
      src: 'expect(in_array(137.77, $valores, true))->toBeFalse();', espera: 0 },
  ];
  let falhas = 0;
  for (const c of casos) {
    // C2 está desligado por padrão (100% FP medido) — o selftest o exercita diretamente,
    // pra que a lógica fique provada caso alguém tente re-habilitá-lo com outro critério.
    const r = c.nome.startsWith('C2')
      ? checarAssertPorChave(c.arq, c.src)
      : analisar(c.arq, c.src);
    const ok = r.length === c.espera;
    const detalhe = c.nome.includes('pré-condição')
      ? ` [classe=${r[0]?.classe ?? '—'}]` : '';
    console.log(`${ok ? '  ok  ' : ' FALHA'} ${c.nome} (achou ${r.length}, esperava ${c.espera})${detalhe}`);
    if (!ok) falhas++;
    // o caso da pré-condição é detectado mas CLASSIFICADO como legítimo — não vira violação
    if (c.nome.includes('pré-condição') && r[0]?.classe !== 'precondicao(legitimo)') {
      console.log('  FALHA classificação: pré-condição deveria ser marcada legítima'); falhas++;
    }
  }
  console.log(falhas === 0 ? '\n✅ selftest 6/6 — morde o ruim, poupa o legítimo' : `\n❌ ${falhas} falha(s)`);
  process.exit(falhas === 0 ? 0 : 1);
}

// ── MEASURE (censo do corpus — é isto que decide se vira gate) ─────────────────
if (args.includes('--measure')) {
  const casos = listarArquivos(join(ROOT, 'resources', 'js', 'Pages'), (p) => p.endsWith('.casos.md'));
  const testes = listarArquivos(join(ROOT, 'tests'), (p) => /Contrato\w*Test\.php$/.test(basename(p)));

  let c1Total = 0; const c1PorArquivo = [];
  for (const p of casos) {
    const rel = relative(ROOT, p).replace(/\\/g, '/');
    const r = checarRefsDeLinha(rel, ler(p));
    if (r.length) { c1Total += r.length; c1PorArquivo.push([rel, r.length]); }
  }

  let c2Contrato = 0, c2Pre = 0; const c2PorArquivo = [];
  for (const p of testes) {
    const rel = relative(ROOT, p).replace(/\\/g, '/');
    const r = checarAssertPorChave(rel, ler(p));
    const cont = r.filter((x) => x.classe === 'contrato(acoplado)').length;
    const pre = r.length - cont;
    c2Contrato += cont; c2Pre += pre;
    if (r.length) c2PorArquivo.push([rel, cont, pre]);
  }

  console.log('\n  SDD OUTPUT LINT — CENSO DO CORPUS (medição, não veredito)\n');
  console.log(`  Corpus: ${casos.length} arquivos .casos.md · ${testes.length} testes de contrato\n`);
  console.log(`  C1 · refs "arquivo:NNN" sem sha .......... ${c1Total} em ${c1PorArquivo.length} arquivo(s)`);
  c1PorArquivo.sort((a, b) => b[1] - a[1]).slice(0, 12).forEach(([f, n]) => console.log(`         ${String(n).padStart(4)}  ${f}`));
  console.log(`\n  C2 · toHaveKey em teste de contrato ...... ⛔ DESLIGADO (100% FP medido)`);
  console.log(`         ${String(c2Contrato).padStart(4)}  seriam apontados — e os ${c2Contrato} são LEGÍTIMOS (ver cabeçalho)`);
  console.log(`         ${String(c2Pre).padStart(4)}  já filtrados como pré-condição`);
  c2PorArquivo.forEach(([f, c, p]) => console.log(`         apontaria=${c} filtrado=${p}  ${f}`));
  console.log('\n  → C1 mede algo real (ref que apodrece). C2 foi MEDIDO e reprovado: presença de');
  console.log('    chave É o contrato quando a prop é deferida. Não instalar como gate.\n');
  process.exit(0);
}

// ── modo alvo ─────────────────────────────────────────────────────────────────
const alvos = args.filter((a) => !a.startsWith('--'));
if (!alvos.length) {
  console.log('uso: sdd-output-lint.mjs [--measure|--selftest] <arquivos...>');
  process.exit(0);
}
let viol = 0;
for (const a of alvos) {
  const rel = relative(ROOT, join(ROOT, a)).replace(/\\/g, '/');
  const r = analisar(rel, ler(join(ROOT, a))).filter((x) => x.classe !== 'precondicao(legitimo)');
  for (const x of r) {
    console.log(`  ${x.check}  ${rel}:${x.linha}  ${x.alvo}${x.classe ? ` [${x.classe}]` : ''}`);
    viol++;
  }
}
console.log(viol ? `\n  ${viol} apontamento(s) — advisory, não bloqueia.` : '  sem apontamentos.');
process.exit(0);
