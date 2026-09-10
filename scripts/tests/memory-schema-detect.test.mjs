#!/usr/bin/env node
// memory-schema-detect.test.mjs — bite-test da DETECÇÃO do memory-schema-gate.
//
// POR QUE EXISTE (medido 2026-09-10): os jobs `validate-handoff-schema` e
// `validate-session-schema` eram MUDOS — detectavam 0 arquivos SEMPRE. Usavam o
// pathspec CRU do git (`-- 'memory/handoffs/**/*.md'`), e ali o `*` casa `/`, logo
// `**/` exige um componente de diretório INTERMEDIÁRIO. `memory/handoffs/` e
// `memory/sessions/` são PLANAS (`git ls-tree -d origin/main <pasta>` = vazio) →
// nenhum arquivo casava. Consequência real: dois handoffs fora do regex de nome e sem
// a seção `## Estado MCP` (as 2 regras duras do ADR 0130) mergearam VERDES; quem pegou
// foi o `doc-id-index --check-collisions`, por acidente.
//
// AS DUAS PERNAS (uma sem a outra não fecha a doença):
//   1. DETECÇÃO — o pathspec é LIDO DO WORKFLOW e EXECUTADO contra um repo sintético
//      de pasta plana. Reverter pro cru faz este teste falhar. Conferir a presença da
//      string `:(glob)` no YAML seria presence-gate (LC-11) e não provaria nada.
//   2. MORDIDA — o validador reprova o arquivo ruim e libera o bom.
// Sem a perna 1, alguém reverte o pathspec, o validador segue mordendo em fixture e o
// gate volta a ser mudo com o teste verde (LC-15: assert no satélite, não no chokepoint).
//
// USO: node scripts/tests/memory-schema-detect.test.mjs
// Node puro + git + bash. Sem deps de npm, sem rede.

import { spawnSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const ROOT = process.cwd();
const WORKFLOW = join(ROOT, '.github', 'workflows', 'memory-schema-gate.yml');
const VALIDADOR = join(ROOT, '.github', 'scripts', 'validate-memory-schema.sh');

let falhas = 0;
const ok = (cond, msg) => {
  console.log(`${cond ? '  ok      ' : '  FALHOU  '}${msg}`);
  if (!cond) falhas++;
};

// PYTHONIOENCODING: o validador imprime o valor do frontmatter via python. No runner
// Linux o stdout já é UTF-8; no Windows é cp1252 e uma seta no tldr explode com
// UnicodeEncodeError, virando um falso "campo ausente". Igualar aqui faz o teste medir
// o MESMO comportamento nas duas plataformas (§5 2026-08-07).
const env = { ...process.env, PYTHONIOENCODING: 'utf-8' };
const git = (cwd, ...argv) => spawnSync('git', argv, { cwd, encoding: 'utf8', env });

/** Lê do YAML o pathspec que o job realmente usa — a fonte é o workflow, não esta cópia. */
function pathspecDoWorkflow(pasta) {
  const yml = readFileSync(WORKFLOW, 'utf8');
  const re = new RegExp(
    `git diff --name-only --diff-filter=A[M]? "\\$\\{BASE\\}" "\\$\\{HEAD\\}" -- '([^']*memory/${pasta}[^']*)'`,
  );
  const m = yml.match(re);
  if (!m) throw new Error(`nao achei o pathspec de memory/${pasta} em ${WORKFLOW}`);
  return m[1];
}

/** Repo sintético com memory/handoffs e memory/sessions PLANAS (sem subdiretório). */
function sandbox() {
  const dir = mkdtempSync(join(tmpdir(), 'memschema-'));
  git(dir, 'init', '-q', '-b', 'main');
  git(dir, 'config', 'user.email', 't@t');
  git(dir, 'config', 'user.name', 't');
  writeFileSync(join(dir, 'README.md'), '# base\n');
  git(dir, 'add', '-A');
  git(dir, 'commit', '-qm', 'base');
  const BASE = git(dir, 'rev-parse', 'HEAD').stdout.trim();

  mkdirSync(join(dir, 'memory', 'handoffs'), { recursive: true });
  mkdirSync(join(dir, 'memory', 'sessions'), { recursive: true });

  const fm = (campos) => `---\n${campos}\n---\n`;

  // Handoff BOM: nome com HHMM + frontmatter completo + a seção do ADR 0130 §6.
  writeFileSync(
    join(dir, 'memory/handoffs/2026-01-02-0930-handoff-bom.md'),
    fm('date: "2026-01-02"\nslug: "handoff-bom"\ntldr: "Fixture boa do bite-test — passa."')
      + '# Bom\n\n## TL;DR\nok\n\n## Estado MCP no momento do fechamento\ncycles-active: nada\n',
  );
  // Handoff RUIM: nome sem HHMM (1a regra dura do ADR 0130).
  writeFileSync(
    join(dir, 'memory/handoffs/2026-01-02-handoff-ruim.md'),
    fm('date: "2026-01-02"\nslug: "handoff-ruim"\ntldr: "Fixture ruim — o nome nao tem HHMM."')
      + '# Ruim\n\n## TL;DR\nnada\n',
  );
  // Handoff sem a seção MCP: o validador dá `return` ao reprovar o filename, então a
  // 2a regra dura só é alcançada com o nome VÁLIDO. Fixture própria pra medi-la.
  writeFileSync(
    join(dir, 'memory/handoffs/2026-01-02-1000-sem-mcp.md'),
    fm('date: "2026-01-02"\nslug: "sem-mcp"\ntldr: "Nome valido, mas sem a secao do ADR 0130."')
      + '# x\n\n## TL;DR\nok\n',
  );

  writeFileSync(
    join(dir, 'memory/sessions/2026-01-02-session-boa.md'),
    fm('date: "2026-01-02"\ntopic: "Fixture boa do bite-test"') + '# Boa\n\n## TL;DR\nok\n',
  );
  writeFileSync(
    join(dir, 'memory/sessions/2026-01-02-session-ruim.md'),
    fm('date: "2026-01-02"\ntopic: "Fixture ruim do bite-test"')
      + '# Ruim\n\n## Outra coisa\nsem TL;DR nem Contexto\n',
  );

  git(dir, 'add', '-A');
  git(dir, 'commit', '-qm', 'adiciona handoffs e sessions');
  return { dir, BASE, HEAD: git(dir, 'rev-parse', 'HEAD').stdout.trim() };
}

const { dir, BASE, HEAD } = sandbox();
try {
  console.log('PERNA 1 — a deteccao enxerga pasta plana (o pathspec vem do workflow)');
  for (const [pasta, filtro, esperado] of [['handoffs', 'A', 3], ['sessions', 'AM', 2]]) {
    const spec = pathspecDoWorkflow(pasta);
    const achados = git(dir, 'diff', '--name-only', `--diff-filter=${filtro}`, BASE, HEAD, '--', spec)
      .stdout.split('\n').filter(Boolean);
    ok(achados.length === esperado,
      `memory/${pasta}: o pathspec do workflow acha ${achados.length} de ${esperado} — '${spec}'`);

    // CONTROLE NEGATIVO: a forma CRUA (a doença) tem de achar 0 nesta mesma árvore. Se
    // um dia achar, a premissa mudou (a pasta ganhou subdiretório) e este teste passou
    // a medir outra coisa — melhor falhar do que ficar verde por acidente.
    const cru = spec.replace(':(glob)', '');
    const cegos = git(dir, 'diff', '--name-only', `--diff-filter=${filtro}`, BASE, HEAD, '--', cru)
      .stdout.split('\n').filter(Boolean);
    ok(cegos.length === 0, `memory/${pasta}: a forma crua segue cega (achou ${cegos.length}, esperado 0)`);
  }

  console.log('PERNA 2 — o validador morde o ruim e libera o bom');
  const roda = (tipo, arquivo) => spawnSync('bash', [VALIDADOR, tipo, arquivo], {
    cwd: dir, encoding: 'utf8', env: { ...env, VIOLATIONS_JSON: join(dir, 'v.json') },
  });

  const hBom = roda('handoff', 'memory/handoffs/2026-01-02-0930-handoff-bom.md');
  ok(hBom.status === 0, `handoff bom libera (exit ${hBom.status})`);

  const hRuim = roda('handoff', 'memory/handoffs/2026-01-02-handoff-ruim.md');
  ok(hRuim.status === 1, `handoff com nome sem HHMM reprova (exit ${hRuim.status}, esperado 1 — nao crash)`);
  ok(/fora do regex/.test(hRuim.stderr), 'handoff ruim: a acusacao e o filename (ADR 0130)');

  const hSemMcp = roda('handoff', 'memory/handoffs/2026-01-02-1000-sem-mcp.md');
  ok(hSemMcp.status === 1, `handoff sem Estado MCP reprova (exit ${hSemMcp.status})`);
  ok(/Estado MCP no momento do fechamento/.test(hSemMcp.stderr),
    'handoff sem MCP: a acusacao e a secao ausente (ADR 0130 §6)');

  const sBoa = roda('session', 'memory/sessions/2026-01-02-session-boa.md');
  ok(sBoa.status === 0, `session boa libera (exit ${sBoa.status})`);
  const sRuim = roda('session', 'memory/sessions/2026-01-02-session-ruim.md');
  ok(sRuim.status === 1, `session sem TL;DR/Contexto reprova (exit ${sRuim.status})`);
} finally {
  rmSync(dir, { recursive: true, force: true });
}

console.log(falhas === 0 ? '\nOK — deteccao + mordida provadas.' : `\n${falhas} assercao(oes) falharam.`);
process.exit(falhas === 0 ? 0 : 1);
