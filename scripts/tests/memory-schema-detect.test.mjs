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

// O ambiente vai CRU de propósito. De 2026-09-10 a 2026-09-15 esta linha forçava
// `PYTHONIOENCODING: 'utf-8'` porque o validador imprimia o valor do frontmatter com
// `print()`, e no Windows (stdout cp1252) uma seta no tldr explodia — o teste ficava
// verde contornando o defeito em vez de medi-lo. O defeito foi consertado no script
// (escrita por `sys.stdout.buffer`), então a muleta saiu e a PERNA 4 abaixo passou a
// forçar cp1252 para provar o conserto nas duas plataformas.
const env = { ...process.env };
const git = (cwd, ...argv) => spawnSync('git', argv, { cwd, encoding: 'utf8', env });

/**
 * Extrai do YAML o PIPELINE de detecção daquele job — da atribuição que roda o
 * `git diff` até a linha que escreve no GITHUB_OUTPUT. É o chokepoint real: quem
 * decide o rc do step. Testar só o pathspec (perna 1) não pega o `|| true`.
 */
function pipelineDoWorkflow(pasta) {
  const linhas = readFileSync(WORKFLOW, 'utf8').split('\n');
  const ini = linhas.findIndex((l) => l.includes(`:(glob)memory/${pasta}`));
  if (ini < 0) throw new Error(`nao achei o pipeline de memory/${pasta}`);
  const fim = linhas.findIndex((l, k) => k > ini && l.includes('echo "files<<EOF"'));
  if (fim < 0) throw new Error(`nao achei o fim do pipeline de memory/${pasta}`);
  return linhas.slice(ini, fim).filter((l) => !l.trim().startsWith('#')).join('\n');
}

/** Roda o pipeline do workflow com BASE/HEAD dados, no shell que o `run:` usa (`bash -e`). */
function rodaPipeline(cwd, pasta, base, head) {
  const script = [
    'set -e',
    `BASE="${base}"`,
    `HEAD="${head}"`,
    pipelineDoWorkflow(pasta),
    'printf "FILES=[%s]" "$FILES"',
  ].join('\n');
  const r = spawnSync('bash', ['-e', '-c', script], { cwd, encoding: 'utf8', env });
  return { rc: r.status, out: (r.stdout || '').trim() };
}

/**
 * Lê do YAML o pathspec que o job realmente usa — a fonte é o workflow, não esta cópia.
 *
 * 2026-09-15 — o regex passou a exigir THREE-DOT (`"${BASE}...${HEAD}"`). Antes casava
 * o two-dot (`"${BASE}" "${HEAD}"`), que era a forma do workflow e é a DOENÇA: two-dot
 * compara duas árvores, então quando a branch não contém o `base.sha` a lista traz o que
 * main ganhou e a branch não tem (medido: 27 arquivos contra 6), e quando o `base.sha` já
 * contém o conteúdo do PR ela vem VAZIA e o job valida nada (0 contra 6). Exigir a forma
 * nova aqui é o que impede a regressão: reverter o workflow pro two-dot faz este teste
 * estourar, do mesmo jeito que reverter o `:(glob)` pro pathspec cru já fazia.
 * §5 2026-09-15, eixo BASE-DE-PR.
 */
function pathspecDoWorkflow(pasta) {
  const yml = readFileSync(WORKFLOW, 'utf8');
  const re = new RegExp(
    `git diff --name-only --diff-filter=A[M]? "\\$\\{BASE\\}\\.\\.\\.\\$\\{HEAD\\}" -- '([^']*memory/${pasta}[^']*)'`,
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

  // Handoff BOM cujo tldr tem chars FORA do cp1252 (→ ≥ ↔). Fixture da PERNA 4: com o
  // `print(val)` antigo o extractor estourava e o campo PRESENTE virava "ausente".
  writeFileSync(
    join(dir, 'memory/handoffs/2026-01-02-1100-fora-do-cp1252.md'),
    fm('date: "2026-01-02"\nslug: "fora-do-cp1252"\ntldr: "E1→E6 concluído — recall ≥ 80% e ida↔volta ok"')
      + '# x\n\n## TL;DR\nok\n\n## Estado MCP no momento do fechamento\ncycles-active: nada\n',
  );
  // CONTROLE NEGATIVO da PERNA 4: tldr GENUINAMENTE ausente. Sem esta fixture, um fix
  // que simplesmente desligasse a checagem deixaria a de cima verde.
  writeFileSync(
    join(dir, 'memory/handoffs/2026-01-02-1200-sem-tldr.md'),
    fm('date: "2026-01-02"\nslug: "sem-tldr"')
      + '# x\n\n## TL;DR\nok\n\n## Estado MCP no momento do fechamento\ncycles-active: nada\n',
  );
  // Frontmatter ilegível (flow sequence não fechada → ParserError no PyYAML). "Não
  // consegui ler" é um fato DIFERENTE de "o campo não está lá", e a acusação tem de dizer
  // qual dos dois é. Precisa de PyYAML instalado; o fallback regex não parseia YAML.
  writeFileSync(
    join(dir, 'memory/handoffs/2026-01-02-1300-yaml-quebrado.md'),
    '---\ndate: "2026-01-02"\nslug: "yaml-quebrado"\ntldr: [1, 2\n---\n'
      + '# x\n\n## TL;DR\nok\n\n## Estado MCP no momento do fechamento\ncycles-active: nada\n',
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
  // CONTROLE NEGATIVO do eixo BASE — roda ANTES da perna 1 de propósito. O
  // `pathspecDoWorkflow` também estoura se o workflow voltar ao two-dot, mas com a
  // mensagem "nao achei o pathspec", que acusa o lugar errado e manda o leitor procurar
  // `:(glob)` (verificado por mutação: revertendo 1 site, o throw vinha primeiro e este
  // assert nunca era alcançado). Aqui ele nomeia a regressão de verdade, e o throw
  // continua como rede de segurança. §5 2026-09-15, eixo BASE-DE-PR.
  console.log('PERNA 0 — a base do diff nao voltou ao two-dot');
  {
    const yml = readFileSync(WORKFLOW, 'utf8');
    const twoDot = (yml.match(/"\$\{BASE\}" "\$\{HEAD\}"/g) || []).length;
    ok(twoDot === 0,
      `nenhum job usa two-dot "\${BASE}" "\${HEAD}" (achou ${twoDot}) — two-dot lista o que main ganhou e a branch nao tem, e vem VAZIO quando a base ja contem o PR`);
  }

  console.log('PERNA 1 — a deteccao enxerga pasta plana (o pathspec vem do workflow)');
  for (const [pasta, filtro, esperado] of [['handoffs', 'A', 6], ['sessions', 'AM', 2]]) {
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

  // PERNA 3 — o rc do `git diff` chega ao step. Antes de 2026-09-10 o `|| true` no fim
  // do pipeline engolia a falha do diff e o step saía VERDE com a lista vazia: "diff
  // quebrado" e "nada a validar" eram indistinguíveis (§5 2026-08-11). Mas tirar o
  // `|| true` cru NÃO servia — o `grep` sai 1 quando não casa nada, então o caso mais
  // comum (0 arquivos) viraria vermelho. Os 3 casos abaixo pinam a distinção; qualquer
  // um deles sozinho aceitaria uma das duas formas erradas.
  console.log('PERNA 3 — o rc do git diff chega ao step, e o grep vazio nao');
  const SHA_RUIM = '0'.repeat(40);
  for (const pasta of ['handoffs', 'sessions']) {
    const comArquivos = rodaPipeline(dir, pasta, BASE, HEAD);
    ok(comArquivos.rc === 0 && /2026-01-02/.test(comArquivos.out),
      `${pasta}: diff ok + arquivos -> rc=${comArquivos.rc} ${comArquivos.out.slice(0, 46)}`);

    const semArquivos = rodaPipeline(dir, pasta, BASE, BASE);
    ok(semArquivos.rc === 0 && semArquivos.out === 'FILES=[]',
      `${pasta}: diff ok + 0 arquivos -> rc=${semArquivos.rc} (o caso comum segue VERDE)`);

    const diffQuebrado = rodaPipeline(dir, pasta, SHA_RUIM, HEAD);
    ok(diffQuebrado.rc !== 0,
      `${pasta}: diff FALHA -> rc=${diffQuebrado.rc} (VERMELHO, nao mais verde-com-lista-vazia)`);
  }

  // PERNA 4 — o extractor nao colapsa "nao consegui ler" em "o campo nao esta la".
  //
  // Forcar cp1252 reproduz o console do Windows em QUALQUER plataforma: no runner Linux
  // o stdout do python ja e UTF-8, entao a fixture passaria sem provar nada — verde num
  // contexto de execucao nao e veredito quando o comportamento DEPENDE do contexto
  // (§5 2026-08-07). Medido por mutacao em 2026-09-15: com o `print(val)` antigo, o
  // 1o assert cai (exit 1, acusando falso "campo 'tldr' obrigatorio ausente").
  console.log('PERNA 4 — o extractor le o valor e separa ausencia de falha-de-leitura');
  const rodaCp1252 = (tipo, arquivo) => spawnSync('bash', [VALIDADOR, tipo, arquivo], {
    cwd: dir,
    encoding: 'utf8',
    env: { ...env, PYTHONIOENCODING: 'cp1252', VIOLATIONS_JSON: join(dir, 'v.json') },
  });

  const hForaCp = rodaCp1252('handoff', 'memory/handoffs/2026-01-02-1100-fora-do-cp1252.md');
  ok(hForaCp.status === 0,
    `tldr com char fora do cp1252 e LIDO (exit ${hForaCp.status}) — antes virava falso "campo ausente"`);

  // CONTROLE NEGATIVO: o conserto nao pode ter DESLIGADO a checagem de ausencia.
  const hSemTldr = rodaCp1252('handoff', 'memory/handoffs/2026-01-02-1200-sem-tldr.md');
  ok(hSemTldr.status === 1 && /campo 'tldr' obrigat/.test(hSemTldr.stderr),
    `tldr GENUINAMENTE ausente segue reprovando (exit ${hSemTldr.status})`);

  // A acusacao tem de nomear qual dos dois fatos ocorreu — e NAO pode dizer "ausente".
  const hYaml = rodaCp1252('handoff', 'memory/handoffs/2026-01-02-1300-yaml-quebrado.md');
  ok(hYaml.status === 1 && /falha ao LER o frontmatter/.test(hYaml.stderr) && !/ ausente/.test(hYaml.stderr),
    `YAML ilegivel acusa falha de LEITURA, nao ausencia (exit ${hYaml.status})`);

  // PERNA 5 — o RODAPE nao afirma ter validado o que nem existe.
  //
  // Ate 2026-09-15 o `[[ ! -f ]]` saia do laco sem contar em nada, e como o rodape
  // faz `TOTAL - SKIPPED`, um path inexistente entrava na conta de VALIDADOS. Medido
  // no dia: 50 paths com CR de CRLF renderam `Arquivos validados: 50 (skipados: 0)
  // — erros: 0`, e a comparacao que dependia daquele numero mediu o nada.
  // Os 3 baldes SOMAM o total; o assert confere a conta, nao so a palavra.
  console.log('PERNA 5 — o rodape conta o inexistente em vez de chama-lo de validado');
  const fantasma = roda('handoff', 'memory/handoffs/2026-01-02-0000-nao-existe-mesmo.md');
  ok(fantasma.status === 1,
    `arquivo inexistente REPROVA (exit ${fantasma.status}) — antes saia 0, indistinguivel de "nada regrediu"`);
  ok(/Arquivos validados: 0 de 1 \(pulados: 0 · inexistentes: 1\)/.test(fantasma.stderr),
    'o rodape declara 0 validados e 1 inexistente (validados + pulados + inexistentes = total)');
  ok(/arquivo n[aã]o existe/.test(fantasma.stderr) && /::error/.test(fantasma.stderr),
    'a acusacao sai como ERRO anotado, nao como um [SKIP] de aparencia benigna');

  // CONTROLE NEGATIVO da PERNA 5: arquivo que EXISTE e passa segue com rodape de 1 validado.
  const vivo = roda('handoff', 'memory/handoffs/2026-01-02-0930-handoff-bom.md');
  ok(vivo.status === 0 && /Arquivos validados: 1 de 1 \(pulados: 0 · inexistentes: 0\)/.test(vivo.stderr),
    `handoff existente segue contando como 1 validado (exit ${vivo.status})`);

  // PERNA 6 — tipo invalido nao sai VERDE, nem quando vem sem arquivos.
  //
  // Ate 2026-09-15 a validacao do TYPE morava so dentro do laco de arquivos, entao
  // tipo invalido SEM arquivos nunca chegava nela: caia no "[OK] nada a validar" e
  // saia exit 0. `validate-memory-schema.sh --selftest` devolvia VERDE sem ter medido
  // nada — quem digitasse isso achando que rodou um selftest levava um verde de graca.
  console.log('PERNA 6 — tipo invalido reprova na ENTRADA, mesmo sem arquivos');
  const semArgs = (...argv) => spawnSync('bash', [VALIDADOR, ...argv], {
    cwd: dir, encoding: 'utf8', env: { ...env, VIOLATIONS_JSON: join(dir, 'v.json') },
  });

  const self = semArgs('--selftest');
  ok(self.status === 2, `--selftest reprova (exit ${self.status}) — antes saia 0 com "[OK] nada a validar"`);
  ok(/memory-schema-detect\.test\.mjs/.test(self.stderr),
    'a mensagem aponta o bite-test REAL em vez de fingir ter um proprio');

  const tipoTorto = semArgs('sessionn');
  ok(tipoTorto.status === 2 && /type inv/.test(tipoTorto.stderr),
    `typo de tipo reprova sem arquivos (exit ${tipoTorto.status})`);

  // CONTROLE NEGATIVO — este e o caso que o CI DEPENDE: tipo VALIDO e zero arquivos
  // segue VERDE (skip-as-pass). Sem este assert, "reprovar tipo invalido" poderia ter
  // sido implementado reprovando tambem o caminho legitimo.
  const vazioOk = semArgs('handoff');
  ok(vazioOk.status === 0 && /nenhum arquivo passado/.test(vazioOk.stderr),
    `tipo VALIDO com 0 arquivos segue verde (exit ${vazioOk.status}) — skip-as-pass do CI intacto`);
} finally {
  rmSync(dir, { recursive: true, force: true });
}

console.log(falhas === 0 ? '\nOK — deteccao + mordida provadas.' : `\n${falhas} assercao(oes) falharam.`);
process.exit(falhas === 0 ? 0 : 1);
