#!/usr/bin/env node
// permissao-renomeada-lint.mjs — barra o nome VELHO de permissão renomeada em linha NOVA.
//
// POR QUE EXISTE (medido 2026-07-28): a família `copiloto.mcp.*` → `jana.mcp.*` foi
// renomeada no código em #4853 e a documentação ficou pra trás. O [W] cobrou: "vai
// precisar corrigir tudo que referencia errado, chato já retornou 3 vezes isso".
// A recorrência NÃO era apodrecimento aleatório — dois arquivos de onboarding
// (`MEMORY_TEAM_ONBOARDING.md`, `TaskRegistry/SPEC.md`) mandavam rodar
// `givePermissionTo('copiloto.mcp.use')`, permissão que NÃO EXISTE → Spatie lança
// PermissionDoesNotExist. Todo onboarding de pessoa nova reencontrava o bug.
//
// O QUE ELE NÃO É: um denylist de string escrito à mão. O canon é DERIVADO do
// seeder (ADR 0256 — derivado+enforçado sobrevive; escrito+lembrado apodrece). Se
// alguém renomear outra permissão no seeder, o par velho→novo aparece sozinho.
//
// FP MEDIDO ANTES DE INSTALAR (§5 tem 4 lápides de guard sintático que reprovava o
// legítimo). Corpus de `origin/main` em 2026-07-28: 54 ocorrências literais do nome
// velho, das quais 32 LEGÍTIMAS —
//   • 23 em memory/{decisions,sessions,handoffs}/ = história append-only (a ADR
//     registra a permissão correta NA DATA em que foi escrita)
//   • 3 errata na mesma linha ("era `copiloto.X`; virou `jana.X`")
//   • o resto, prosa que cita as duas
// Um guard que só casasse a string reprovaria 59% de conteúdo correto. Daí as 2
// isenções abaixo — e o modo --diff, que é o único ligado no CI.
//
// ISENÇÕES (todas medidas, nenhuma especulativa):
//   1. path append-only: memory/{decisions,sessions,handoffs}/ — nunca se reescreve
//   2. a linha (ou a vizinha) cita TAMBÉM o nome novo → padrão errata. A janela de
//      ±2 linhas existe porque a errata real do `SDD-tela-hub-team-mcp` quebra em
//      2 linhas, e a 1ª medição (só-mesma-linha) deu FP nela.
//
// MODOS
//   --diff [base]  (CI) só linhas ADICIONADAS vs base — forward-only. Legado fica
//                  grandfatherado: tocar arquivo velho não acorda o gate (lápide
//                  §5 2026-07-12). Exit 1 se achar.
//   --report       (humano) varre a árvore inteira, exit 0 SEMPRE. É o mapa da
//                  dívida, não uma catraca.
//   --selftest     fixture boa + ruim. Prova que MORDE e que LIBERA.
//
// NÃO É REQUIRED. Nasce advisory (ADR 0275/0336): promoção exige mordida provada.

import { execSync } from 'node:child_process';

const SEEDER = 'Modules/Jana/Database/Seeders/McpScopesSeeder.php';
const APPEND_ONLY = /^memory\/(decisions|sessions|handoffs)\//;
const JANELA_ERRATA = 2;
// 3ª isenção, descoberta rodando o bite-test: o lint flagrou A SI MESMO (7 hits) —
// ele PRECISA conter o nome velho nas fixtures pra provar que morde. Mesma exceção
// do gate-selftest. É o arquivo que DEFINE a regra; sem isso, a regra se autoproíbe.
const SELF = 'scripts/governance/permissao-renomeada-lint.mjs';

function sh(cmd) {
  try { return execSync(cmd, { encoding: 'utf8', maxBuffer: 1e8 }); }
  catch (e) { return e.stdout || ''; }
}

/** Canon DERIVADO do seeder — nunca uma lista escrita à mão. */
export function paresRenomeados(conteudoSeeder) {
  const novos = [...new Set([...conteudoSeeder.matchAll(/'(jana\.[a-z0-9_.]+)'/g)].map((m) => m[1]))];
  return novos
    .filter((n) => n.startsWith('jana.mcp.'))
    .map((novo) => ({ novo, velho: novo.replace(/^jana\./, 'copiloto.') }))
    // mais longo primeiro: senão `copiloto.mcp.use` casa dentro de `copiloto.mcp.usage.all`
    .sort((a, b) => b.velho.length - a.velho.length);
}

/** Uma ocorrência é legítima? (as 2 isenções medidas) */
export function isento(path, linhas, idx, pares) {
  if (path === SELF) return 'self';
  if (APPEND_ONLY.test(path)) return 'append-only';
  const ini = Math.max(0, idx - JANELA_ERRATA);
  const fim = Math.min(linhas.length - 1, idx + JANELA_ERRATA);
  for (let i = ini; i <= fim; i++) {
    if (pares.some((p) => linhas[i].includes(p.novo))) return 'errata';
  }
  return null;
}

/** Acha o nome velho num conjunto de linhas. Devolve [{n, velho, novo}]. */
export function varrer(path, texto, pares, apenasLinhas = null) {
  const linhas = texto.split('\n');
  const achados = [];
  linhas.forEach((linha, idx) => {
    if (apenasLinhas && !apenasLinhas.has(idx + 1)) return;
    const par = pares.find((p) => linha.includes(p.velho));
    if (!par) return;
    if (isento(path, linhas, idx, pares)) return;
    achados.push({ path, n: idx + 1, velho: par.velho, novo: par.novo, linha: linha.trim() });
  });
  return achados;
}

/** Linhas ADICIONADAS por arquivo no diff vs base. */
function linhasAdicionadas(base) {
  const diff = sh(`git diff --unified=0 ${base}...HEAD`);
  const porArquivo = new Map();
  let atual = null;
  for (const l of diff.split('\n')) {
    const mFile = l.match(/^\+\+\+ b\/(.+)$/);
    if (mFile) { atual = mFile[1]; porArquivo.set(atual, new Set()); continue; }
    const mHunk = l.match(/^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@/);
    if (mHunk && atual) {
      const ini = Number(mHunk[1]);
      const qtd = mHunk[2] === undefined ? 1 : Number(mHunk[2]);
      for (let i = 0; i < qtd; i++) porArquivo.get(atual).add(ini + i);
    }
  }
  return porArquivo;
}

function relatar(achados, modo) {
  if (!achados.length) {
    console.log(`✅ permissao-renomeada-lint (${modo}): nenhum nome velho em linha nova.`);
    return 0;
  }
  console.log(`\n🔴 permissao-renomeada-lint (${modo}): ${achados.length} uso(s) de permissão RENOMEADA:\n`);
  for (const a of achados) {
    console.log(`  ${a.path}:${a.n}`);
    console.log(`     ${a.velho}  →  ${a.novo}`);
    console.log(`     ${a.linha.slice(0, 100)}`);
  }
  console.log(`
  O nome canônico vem de ${SEEDER}. Instrução com o nome velho FALHA em runtime
  (Spatie: PermissionDoesNotExist) — foi assim que o bug voltou 3×.

  Se a citação é HISTÓRICA de propósito, use o padrão errata: cite o nome NOVO
  na mesma linha ou a ≤${JANELA_ERRATA} linhas ("era \`X\`; virou \`Y\` no #NNNN").
`);
  return 1;
}

function selftest() {
  const seederFake = `
    'slug' => 'jana.mcp.usage.all',
    'slug' => 'jana.mcp.use',
    'slug' => 'jana.access',
  `;
  const pares = paresRenomeados(seederFake);
  const eq = (a, b, m) => { if (JSON.stringify(a) !== JSON.stringify(b)) { console.error(`✗ ${m}\n  got ${JSON.stringify(a)}\n  want ${JSON.stringify(b)}`); process.exitCode = 1; } else console.log(`✓ ${m}`); };

  eq(pares.map((p) => p.velho), ['copiloto.mcp.usage.all', 'copiloto.mcp.use'],
    'canon derivado do seeder, mais longo primeiro (jana.access fica fora — só mcp.*)');

  // MORDE: fixture ruim (a instrução real que falhava no onboarding)
  eq(varrer('MEMORY_TEAM_ONBOARDING.md', "> $user->givePermissionTo('copiloto.mcp.use');", pares).length, 1,
    'MORDE: givePermissionTo com nome velho');

  // LIBERA 1: história append-only
  eq(varrer('memory/decisions/0057-x.md', 'permission `copiloto.mcp.usage.all` só Wagner', pares).length, 0,
    'LIBERA: ADR append-only (registra o correto NA DATA)');

  // LIBERA 2: errata na mesma linha
  eq(varrer('doc.md', 'era `copiloto.mcp.use`; virou `jana.mcp.use` no #4853', pares).length, 0,
    'LIBERA: errata na mesma linha');

  // LIBERA 3: errata quebrada em 2 linhas — o FP real que a 1ª medição pegou
  eq(varrer('doc.md', '> A permissão mudou. Era `copiloto.mcp.usage.all`;\n> agora é `jana.mcp.usage.all`.', pares).length, 0,
    'LIBERA: errata em linha vizinha (o FP medido no SDD-tela-hub-team-mcp)');

  // MORDE: substring não pode mascarar — `use` dentro de `usage.all`
  const sub = varrer('x.md', 'sem `copiloto.mcp.usage.all` recebe 403', pares);
  eq(sub.length && sub[0].velho, 'copiloto.mcp.usage.all',
    'MORDE com o par CERTO: usage.all não é confundido com use');

  // LIBERA: nome novo sozinho
  eq(varrer('x.md', 'exige `jana.mcp.usage.all`', pares).length, 0, 'LIBERA: só o nome novo');

  // LIBERA: config `copiloto.mcp.url` NÃO é permissão (o FP que matou a 1ª regra)
  eq(varrer('x.php', "config('copiloto.mcp.url')", pares).length, 0,
    'LIBERA: config copiloto.mcp.* não é permissão (só o seeder define o que é)');

  // LIBERA 4: o próprio lint. Sem isto ele se autoproíbe — descoberto no bite-test
  // (7 hits contra si mesmo), não previsto no desenho.
  eq(varrer(SELF, "eq(varrer('x.md', 'sem `copiloto.mcp.usage.all`', pares))", pares).length, 0,
    'LIBERA: o proprio lint (precisa da string velha nas fixtures pra provar que morde)');

  // MORDE: mas só ELE é isento — outro script de governança não herda a isenção
  eq(varrer('scripts/governance/outro.mjs', "'copiloto.mcp.use'", pares).length, 1,
    'MORDE: a isencao e SO do arquivo dono da regra, nao de scripts/governance/ inteiro');

  console.log(process.exitCode ? '\n✗ selftest FALHOU' : '\n✓ selftest 10/10');
}

// ── main ────────────────────────────────────────────────────────────────────
const argv = process.argv.slice(2);
if (argv.includes('--selftest')) { selftest(); }
else {
  const pares = paresRenomeados(sh(`git show HEAD:${SEEDER}`) || '');
  if (!pares.length) {
    console.log('⚠️  nenhum par jana.mcp.* derivado do seeder — nada a checar (fail-open consciente).');
    process.exit(0);
  }
  if (argv.includes('--diff')) {
    const base = argv[argv.indexOf('--diff') + 1] || 'origin/main';
    const porArquivo = linhasAdicionadas(base);
    const achados = [];
    for (const [path, linhas] of porArquivo) {
      if (!linhas.size) continue;
      const texto = sh(`git show HEAD:${JSON.stringify(path)}`);
      if (texto) achados.push(...varrer(path, texto, pares, linhas));
    }
    process.exit(relatar(achados, 'diff'));
  } else {
    const args = pares.map((p) => `-e ${JSON.stringify(p.velho)}`).join(' ');
    const arquivos = [...new Set(sh(`git grep -F -l ${args} HEAD`).split('\n')
      .filter(Boolean).map((l) => l.replace(/^HEAD:/, '')))];
    const achados = [];
    for (const path of arquivos) {
      const texto = sh(`git show HEAD:${JSON.stringify(path)}`);
      if (texto) achados.push(...varrer(path, texto, pares));
    }
    relatar(achados, 'report');
    process.exit(0); // report NUNCA avermelha — é mapa de dívida
  }
}
