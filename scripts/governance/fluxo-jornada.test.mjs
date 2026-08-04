#!/usr/bin/env node
// Bateria do FLUXO — a jornada de um item pelos elos, e as variações dela.
//
// ÂNCORA EXTERNA (não derivado do código — proibicoes §5 anti-tautológico):
//   ADR 0368 (funil de admissão) · ADR 0273 §1 (gramática da âncora) · ADR 0306 (trio de
//   feature) · ADR 0264 (casos-gate G-1/G-2). O contrato que isto verifica é o desses
//   documentos: um item atravessa `US → feature → tela → casos → teste → âncora` e o
//   IDENTIFICADOR de cada elo aparece no elo seguinte.
//
// POR QUE EXISTE (e por que não duplica os 72 bite-tests do gate-selftest):
//   O `gate-selftest` responde "cada catraca morde?" — 72 de 72, por peça. O `fluxo-morde`
//   responde "a mordida chega ao merge?" (acusa → transmite → bloqueia). Nenhum dos dois
//   responde **"um item consegue atravessar o fluxo inteiro, e pular um elo é detectado?"**.
//   Medido em 2026-08-04: busca por teste de jornada no repo → nenhum.
//
//   A metade que impede isto de virar teatro é a PARTE B: para cada elo, remover e afirmar
//   QUEM acusa. Elo cuja remoção não muda veredito nenhum é decorativo — e isso é achado,
//   não falha do teste.
//
// O QUE ISTO NÃO É:
//   · NÃO é gate — nasce advisory. Com 3 features no repo, gate de jornada mediria 3.
//   · NÃO reimplementa os linters: roda os REAIS como subprocess contra fixture sintética
//     (`cwd` = tmpdir), que é o idioma do anchor-stale.test.mjs.
//   · NÃO toca `Modules/` nem `memory/` reais.
//
// Uso: node scripts/governance/fluxo-jornada.test.mjs
import { execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync, existsSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const SCRIPTS = dirname(fileURLToPath(import.meta.url));
const REPO = join(SCRIPTS, '..', '..');
const ANCHOR = join(SCRIPTS, 'anchor-lint.mjs');
const FEATURE = join(SCRIPTS, 'feature-lint.mjs');
const CASOS = join(REPO, 'scripts', 'casos-coverage-guard.mjs');

let fails = 0;
const ok = (nome, cond, extra = '') => {
  console.log(`  ${cond ? '[OK]' : '[FAIL]'} ${nome}${cond ? '' : `  ${extra}`}`);
  if (!cond) fails++;
};

/** Roda um script real com cwd na fixture. Devolve {code, out} — nunca lança. */
function rodar(script, args, cwd) {
  try {
    const out = execFileSync(process.execPath, [script, ...args], { cwd, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'pipe'] });
    return { code: 0, out };
  } catch (e) {
    return { code: e.status ?? 1, out: `${e.stdout ?? ''}${e.stderr ?? ''}` };
  }
}

const escrever = (raiz, rel, txt) => {
  mkdirSync(join(raiz, dirname(rel)), { recursive: true });
  writeFileSync(join(raiz, rel), txt);
};

// ── a jornada COMPLETA, em dados. Cada peça cita o identificador da anterior ──────────────
const US = 'US-FAKE-001';
const UC = 'UC-FAKE-01';
const TELA = 'resources/js/Pages/Fake/Tela.tsx';

function montarJornada(raiz, { comDod = true, ancora = TELA, comCasos = true, comTeste = true,
  usDaFeature = US, comTaskCobrindoAc = true, comRelatedUs = true, ancoraPendente = false,
  ancoraParcial = false, semTela = false } = {}) {
  if (semTela) ancora = 'Modules/Fake/Services/FakeService.php';
  const linhaAncora = ancoraPendente
    ? '**Implementado em:** _pendente_ — tela não construída'
    : ancoraParcial
      ? `**Implementado em:** _parcial_ · \`${ancora}\` · verificado@abc1234 (2026-08-04) — falta o filtro`
      : `**Implementado em:** \`${ancora}\` · verificado@abc1234 (2026-08-04)`;

  escrever(raiz, 'memory/requisitos/Fake/SPEC.md',
    `---\nmodule: Fake\nstatus: ativo\n---\n\n# SPEC Fake\n\n### ${US} · Tela de teste do fluxo\n\n`
    + `> owner: wagner · priority: p2 · estimate: 1h · status: done · type: story\n\n`
    + (comDod ? '**DoD:** a tela lista os itens e o UC tem teste verde.\n\n' : '')
    + `${linhaAncora}\n`);

  escrever(raiz, 'memory/requisitos/Fake/features/minha-feature/requirements.md',
    `---\nus: ${usDaFeature}\nowner: W\n---\n\n# Requirements\n\n## Acceptance criteria\n\n`
    + `- **AC-1** — QUANDO o usuário abre a tela, O SISTEMA DEVE listar os itens. _Prova: Pest._\n`);
  escrever(raiz, 'memory/requisitos/Fake/features/minha-feature/plan.md', '# Plan\n\nabordagem.\n');
  escrever(raiz, 'memory/requisitos/Fake/features/minha-feature/tasks.md',
    `# Tasks\n\n### T-01 · construir a tela\n\n`
    + `> blocked_by: — · covers: ${comTaskCobrindoAc ? 'AC-1' : '—'} · us: ${usDaFeature}\n\n`
    + `**DoD:** a tela renderiza e o teste do ${UC} passa.\n`);

  // `semTela` = feature backend-only: não existe .tsx, logo não pode haver trio de tela.
  // A âncora aponta um Service, não uma Page — é jornada legítima, não dívida.
  if (semTela) {
    escrever(raiz, 'Modules/Fake/Services/FakeService.php', "<?php\n\nclass FakeService {}\n");
    return;
  }
  escrever(raiz, TELA, 'export default function Tela() { return null; }\n');
  escrever(raiz, 'resources/js/Pages/Fake/Tela.charter.md',
    `---\npage: Fake/Tela\ncomponent: ${TELA}\nstatus: live\n`
    + (comRelatedUs ? `related_us: [${US}]\n` : '') + `---\n\n# Charter — Fake/Tela\n`);

  if (comCasos) {
    // Formato REAL exigido pelo guard (descoberto rodando o guard, não presumido):
    // frontmatter com `owner` + `last_run` (data), e cada UC como HEADING `## UC-… ·`
    // com `Status:` no bloco. Bullet `- **UC-…**` NÃO conta — `ucBlocksInCasos` faz
    // split por `## `. A 1ª versão desta fixture usava bullet e o guard reportou
    // `ucs_declared: 0`; o teste achou o erro da própria fixture antes de mim.
    escrever(raiz, 'resources/js/Pages/Fake/Tela.casos.md',
      `---\npage: Fake/Tela\nowner: W\nlast_run: "2026-08-04"\n---\n\n# Casos — Fake/Tela\n\n`
      + `## ${UC} · o usuário lista os itens\n\nStatus: ✅ coberto\n\n`
      + `Teste: \`tests/Feature/Fake/TelaContratoTest.php\`\n`);
  }
  if (comTeste) {
    escrever(raiz, 'tests/Feature/Fake/TelaContratoTest.php',
      `<?php\n\nit('${UC} · lista os itens', function () {\n    expect(true)->toBeTrue();\n});\n`);
  }
}

const novaRaiz = () => mkdtempSync(join(tmpdir(), 'fluxo-jornada-'));

// ═══════════════════════════════════════════════════════════════════════════════════════
// PARTE A — FLUXO DE DADOS: o identificador de cada elo aparece no elo seguinte.
// ═══════════════════════════════════════════════════════════════════════════════════════
// ⚠️ ARMADILHA EVITADA: verificar aqui que `req.includes(US) && spec.includes(US)` seria
// TAUTOLÓGICO — eu monto a fixture com a mesma constante e depois confirmo que ela está lá.
// Prova zero (é a lápide §5 2026-06-05 "teste que deriva do código", na forma "teste que
// deriva da própria fixture"). Então a Parte A não faz string-match: ela pergunta a cada
// MECANISMO REAL se a aresta se sustenta, e a prova de que a resposta significa algo é a
// Parte B — onde a mesma aresta é quebrada e o mesmo mecanismo tem que acusar.
//
// Aresta sem mecanismo é reportada como tal, em vez de fingida com um `includes()`.
console.log('\n  PARTE A — fluxo de dados: cada aresta é aceita pelo MECANISMO que a governa\n');
const raizA = novaRaiz();
try {
  montarJornada(raizA);

  const a1 = rodar(ANCHOR, ['--json'], raizA);
  const jsonA = a1.code === 0 ? JSON.parse(a1.out) : null;
  const cov = jsonA?.summary?.anchor_coverage_pct ?? -1;
  const semDead = jsonA ? jsonA.modules.every((m) => m.dead.length === 0) : false;

  ok('US → código: anchor-lint resolve a âncora (coverage 100, zero anchored_dead)',
    a1.code === 0 && cov === 100 && semDead, `exit=${a1.code} cov=${cov} semDead=${semDead}`);

  const f1 = rodar(FEATURE, ['--check'], raizA);
  ok('US → feature → tasks: feature-lint aceita o trio (us resolve no SPEC, AC coberto por task)',
    f1.code === 0, `exit=${f1.code}`);

  // ⚠️ ler o JSON, não regex sobre o texto: `telas: 1 · casos.md: 1` na saída humana são
  // ESTATÍSTICAS (stats.pages / stats.casos_files), não contagem de violação. A 1ª versão
  // deste assert leu o número errado e reprovou uma jornada íntegra.
  const c1 = rodar(CASOS, ['--json'], raizA);
  const jc = (() => { try { return JSON.parse(c1.out); } catch { return null; } })();
  ok('tela → casos → teste: casos-gate não acusa violação na jornada íntegra',
    jc !== null && jc.total === 0,
    jc ? `total=${jc.total} stats=${JSON.stringify(jc.stats)}` : 'json ilegível');

  // A aresta charter→US não tem mecanismo que a leia. A versão anterior "documentava" isso
  // com `ok(..., true)` — assert que não pode reprovar em condição nenhuma, exatamente o
  // tipo de decoração que este arquivo existe pra denunciar (o adversário o apontou como o
  // único dos 12 que ele NÃO conseguiu derrubar, e isso é acusação, não elogio).
  // Agora a ausência é MEDIDA: com a aresta quebrada, nenhum dos 3 linters muda de veredito.
  const raizSemRelated = novaRaiz();
  try {
    montarJornada(raizSemRelated, { comRelatedUs: false });
    const semRel = [
      rodar(ANCHOR, ['--json'], raizSemRelated).code,
      rodar(FEATURE, ['--check'], raizSemRelated).code,
      rodar(CASOS, ['--json'], raizSemRelated).code,
    ];
    const comRel = [a1.code, f1.code, c1.code];
    ok('US → tela (related_us): SEM mecanismo — quebrar a aresta não muda veredito de ninguém',
      JSON.stringify(semRel) === JSON.stringify(comRel),
      `com=${JSON.stringify(comRel)} sem=${JSON.stringify(semRel)}`);
  } finally {
    rmSync(raizSemRelated, { recursive: true, force: true });
  }
} finally {
  rmSync(raizA, { recursive: true, force: true });
}

// ═══════════════════════════════════════════════════════════════════════════════════════
// PARTE B — VARIAÇÕES. Cada uma roda DUAS VEZES, e é isso que a torna um teste:
//
//   íntegra  → a sonda TEM que ficar silenciosa   (controle negativo, obrigatório)
//   mutada   → a sonda acusa (`esperado: 'acusa'`) ou segue silenciosa (`'silencia'`)
//
// POR QUE DUAS. Revisão adversarial (2026-08-04) derrubou 11 dos 12 asserts da 1ª versão.
// O achado central: a bateria ficava 12/12 VERDE com o `casos-gate` desligado, porque a
// sonda era `/viola[çc][õo]es/i` sobre o stdout — e o guard imprime a palavra SEMPRE,
// inclusive em `0 violações`. Sonda que casa no estado ÍNTEGRO não mede nada: 3 das 5
// variações `detecta:true` passavam com o elo PRESENTE. Rodar contra a jornada íntegra
// mata a classe inteira — sonda viciada reprova na primeira execução.
//
// E as sondas passam a ler CAMPO ESTRUTURADO específico (`dead[].us`, `req_sem_aceite`,
// `stats.missing_casos`), nunca texto humano: o texto muda sem aviso e foi ele que produziu
// a tautologia. É a mesma lição do §5 2026-07-28 (ler o veredito, não a prosa).
// ═══════════════════════════════════════════════════════════════════════════════════════
console.log('\n  PARTE B — variações (cada uma medida contra a jornada íntegra E a mutada)\n');

/** Campo estruturado do anchor-lint, por US. `null` = o linter não respondeu. */
function anchorSinal(r, chave) {
  const { out, code } = rodar(ANCHOR, ['--json'], r);
  if (code !== 0) return null;
  try {
    const j = JSON.parse(out);
    return j.modules.some((m) => (m[chave] ?? []).some((x) => (x.us ?? x) === US));
  } catch { return null; }
}
/** Campo do casos-guard. `null` = o guard não respondeu com JSON legível. */
function casosStat(r, campo) {
  const { out } = rodar(CASOS, ['--json'], r);
  try { return (JSON.parse(out).stats ?? {})[campo] ?? null; } catch { return null; }
}

const VARIACOES = [
  {
    nome: 'US sem DoD → gate de entrada acusa (req_sem_aceite, não "exit != 0")',
    mut: { comDod: false }, esperado: 'acusa',
    // `--check-entry` já saía 1 na jornada íntegra por OUTRA regra (`req_sem_covering_test`).
    // Usar o exit como sinal fazia esta variação passar sem medir DoD nenhum.
    sonda: (r) => anchorSinal(r, 'req_sem_aceite'),
  },
  {
    nome: 'âncora aponta path inexistente → anchored_dead nomeia a US',
    mut: { ancora: 'resources/js/Pages/Fake/NaoExiste.tsx' }, esperado: 'acusa',
    sonda: (r) => anchorSinal(r, 'dead'),
  },
  {
    nome: 'âncora _parcial_ → é estado LEGÍTIMO da gramática, não dívida (ADR 0273 §1)',
    mut: { ancoraParcial: true }, esperado: 'silencia',
    sonda: (r) => anchorSinal(r, 'dead'),
  },
  {
    nome: 'âncora _pendente_ → tela não construída é estado legítimo (ADR 0273 §2)',
    mut: { ancoraPendente: true, comCasos: false, comTeste: false }, esperado: 'silencia',
    sonda: (r) => anchorSinal(r, 'dead'),
  },
  {
    nome: 'feature aponta US que não existe no SPEC → feature-lint --check morde',
    mut: { usDaFeature: 'US-FAKE-999' }, esperado: 'acusa',
    sonda: (r) => rodar(FEATURE, ['--check'], r).code !== 0,
  },
  {
    nome: 'AC sem task que o cubra → feature-lint AVISA, não morde (advisory por desenho)',
    mut: { comTaskCobrindoAc: false }, esperado: 'silencia',
    sonda: (r) => rodar(FEATURE, ['--check'], r).code !== 0,
  },
  {
    nome: 'feature sem tela (backend-only) → NÃO vira dívida de trio',
    mut: { semTela: true }, esperado: 'silencia',
    sonda: (r) => {
      const n = casosStat(r, 'missing_casos');
      return n === null ? null : n > 0;
    },
  },
  {
    nome: 'tela sem casos.md → casos-gate acusa (stats.missing_casos, não regex no stdout)',
    mut: { comCasos: false }, esperado: 'acusa',
    sonda: (r) => {
      const n = casosStat(r, 'missing_casos');
      return n === null ? null : n > 0;
    },
  },
  {
    nome: 'UC sem teste que o cite → casos-gate acusa (teto/exec-backed cai)',
    mut: { comTeste: false }, esperado: 'acusa',
    // Só `orphan_ucs`. A 1ª versão desta sonda também olhava `exec_backed_ucs < ucs_declared`
    // — e acusava na jornada ÍNTEGRA, porque `exec_backed` depende do manifesto
    // (`scripts/casos-test-results.json`), que não existe na fixture: 0 < 1 sempre. O
    // controle negativo pegou; sem ele, esta variação passaria "detectando" o tempo todo.
    sonda: (r) => {
      const n = casosStat(r, 'orphan_ucs');
      return n === null ? null : n > 0;
    },
  },
  {
    nome: 'charter sem related_us → NINGUÉM acusa (buraco medido: 38 de 209 no main)',
    mut: { comRelatedUs: false }, esperado: 'silencia',
    sonda: (r) => rodar(ANCHOR, ['--json'], r).code !== 0 || rodar(FEATURE, ['--check'], r).code !== 0,
  },
];

for (const v of VARIACOES) {
  const rIntegra = novaRaiz();
  const rMutada = novaRaiz();
  try {
    montarJornada(rIntegra);              // sem mutação — o controle negativo
    montarJornada(rMutada, v.mut);
    const naIntegra = v.sonda(rIntegra);
    const naMutada = v.sonda(rMutada);

    // Sonda que não respondeu (linter morto / JSON ilegível) é FALHA, nunca "detectou".
    // Sem isto, apontar o linter pra um path inexistente deixava a variação verde.
    if (naIntegra === null || naMutada === null) {
      ok(v.nome, false, `sonda sem resposta (linter morto?) integra=${naIntegra} mutada=${naMutada}`);
      continue;
    }
    const deveAcusar = v.esperado === 'acusa';
    ok(`${v.nome}  [silencia na íntegra]`, naIntegra === false, `acusou na jornada ÍNTEGRA → sonda viciada`);
    ok(`${v.nome}  [${v.esperado} na mutada]`, naMutada === deveAcusar, `mutada=${naMutada} esperado=${deveAcusar}`);
  } finally {
    rmSync(rIntegra, { recursive: true, force: true });
    rmSync(rMutada, { recursive: true, force: true });
  }
}

console.log(fails
  ? `\n  ${fails} FALHA(S) — o fluxo não se comporta como o contrato diz.\n`
  : `\n  OK — a jornada íntegra passa, cada sonda fica silenciosa nela, e cada elo removido é acusado por quem deve.\n`);
process.exit(fails ? 1 : 0);
