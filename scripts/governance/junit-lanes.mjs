#!/usr/bin/env node
// junit-lanes.mjs — fonte ÚNICA e DERIVADA das lanes de CI que alimentam o manifesto por-UC
//                    (G-7, ADR 0264): quais workflows emitem JUnit e sob qual artifact.
// (caminho: scripts/governance/junit-lanes.mjs)
//
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// O `casos-results-publish.yml` colhia os JUnit de uma lista `lane:artifact` ESCRITA À MÃO
// no shell do step. Lista à mão apodrece — e apodreceu: medido 2026-08-04, QUATRO lanes
// emitiam `--log-junit`, publicavam o artifact e **ninguém as lia**:
//
//   essentials-pest · kb-pest · officeimpresso-pest · ponto-pest
//
// O efeito não é cosmético: os UCs provados nessas lanes ficam com `Status` sem prova no
// painel para sempre — o teste roda verde e vale 0. Só o Ponto tinha 19 UCs com o id no
// título esperando (medido no JUnit do run 30845904967).
//
// Este é EXATAMENTE o bug que o `anchor-lint.mjs` já sofreu e consertou em 2026-07-27
// (a lista de lanes dele era 3 nomes à mão, ficou stale, e produziu falso-positivo em gate
// REQUIRED). A lição de lá vale aqui, literal: "lista escrita à mão apodrece; derivada
// acompanha" (ADR 0256 — derivado+enforçado sobrevive, escrito+lembrado apodrece).
//
// =====================================================================================
// CRITÉRIO (dois caminhos, ambos derivados — nenhum nome de módulo escrito à mão)
// =====================================================================================
//   (a) lane Pest: o workflow passa `--log-junit <path>.xml` E publica um `upload-artifact`
//       cujo `path:` contém esse mesmo XML → o `name:` daquele artifact é o transporte.
//   (b) lane que já produz o MANIFESTO pronto (não JUnit): publica um artifact cujo `path:`
//       contém `scripts/casos-test-results.json` (hoje: e2e-gate). O coletor aceita os dois.
//
// Deliberadamente NÃO deriva de nome de arquivo/pasta (`*-pest.yml`): critério sintático de
// nome é a família de guards já morta no §5 das proibições (allowlist-de-pasta 2026-06-30,
// guard `@scope` 2026-07-09). O que importa é o FATO — o workflow emite e transporta.
//
// USO
//   node scripts/governance/junit-lanes.mjs            # imprime `workflow.yml:artifact`
//   node scripts/governance/junit-lanes.mjs --json     # idem, JSON
//   node scripts/governance/junit-lanes.mjs --selftest # bite-test (morde E solta)
//
// Refs: ADR 0264 (G-7) · ADR 0256 (catraca) · anchor-lint.mjs `laneEntries()` (mesmo padrão).

import { readFileSync, existsSync, readdirSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { resolve, join } from 'node:path';
import { tmpdir } from 'node:os';

const ROOT = process.cwd();
const WF_DIR = resolve(ROOT, '.github', 'workflows');
const MANIFEST_REL = 'scripts/casos-test-results.json';

/**
 * Blocos `upload-artifact` de um workflow → [{ name, paths[] }].
 * Parser de indentação (não YAML completo): dentro do bloco `with:` de um step
 * `uses: actions/upload-artifact`, colhe `name:` e `path:` (escalar OU bloco `|`).
 */
function uploadBlocks(yml) {
  const linhas = yml.split(/\r?\n/);
  const blocos = [];
  for (let i = 0; i < linhas.length; i += 1) {
    if (!/uses:\s*actions\/upload-artifact/.test(linhas[i])) continue;
    const bloco = { name: null, paths: [] };
    let emPath = false;
    let indentPath = 0;
    // varre até o próximo step (linha com `- ` no mesmo nível ou menos) ou fim
    for (let j = i + 1; j < linhas.length; j += 1) {
      const ln = linhas[j];
      if (/^\s*-\s+(name|uses):/.test(ln)) break; // próximo step
      const mName = ln.match(/^\s*name:\s*(.+?)\s*$/);
      if (mName && !bloco.name) { bloco.name = mName[1].replace(/^["']|["']$/g, ''); emPath = false; continue; }
      const mPathEscalar = ln.match(/^\s*path:\s*(\S.*?)\s*$/);
      if (mPathEscalar && mPathEscalar[1] !== '|' && !mPathEscalar[1].startsWith('|')) {
        bloco.paths.push(mPathEscalar[1].replace(/^["']|["']$/g, '')); emPath = false; continue;
      }
      const mPathBloco = ln.match(/^(\s*)path:\s*\|\s*$/);
      if (mPathBloco) { emPath = true; indentPath = mPathBloco[1].length; continue; }
      if (emPath) {
        if (ln.trim() === '') continue;
        const ind = ln.match(/^\s*/)[0].length;
        if (ind <= indentPath) { emPath = false; }
        else { bloco.paths.push(ln.trim()); continue; }
      }
    }
    if (bloco.name) blocos.push(bloco);
  }
  return blocos;
}

/** Lanes derivadas: [{ workflow, artifact, via, junit }] — ordenado, estável. */
export function derivarLanes(dir = WF_DIR) {
  const out = [];
  if (!existsSync(dir)) return out;
  for (const arq of readdirSync(dir).filter((f) => /\.ya?ml$/.test(f)).sort()) {
    const yml = readFileSync(join(dir, arq), 'utf8');
    const blocos = uploadBlocks(yml);

    // (a) XMLs realmente EMITIDOS como JUnit (ignora menção em comentário: exige o .xml).
    //
    // DUAS formas de emissão, porque a casa tem DOIS runners que produzem JUnit:
    //   Pest   → `--log-junit <path>.xml`
    //   vitest → `--reporter=junit` + `--outputFile[=| ]<path>.xml`
    //
    // POR QUE A SEGUNDA ENTROU (medido 2026-08-11): o critério só conhecia a flag do Pest,
    // então uma lane vitest podia emitir JUnit, publicar o artifact e NUNCA ser colhida — o
    // publisher itera SÓ o que este arquivo deriva. Seria chokepoint fantasma: defesa
    // acoplada a um caminho que o fluxo real não atravessa (§5 proibicoes, 2026-07-09
    // `flag:set`). Os 4 testes de domínio do preview `/sells/create-v3` (parcelas · fiscal ·
    // comissão · colunas) são vitest, e sem isto o veredito deles jamais chegaria ao G-7.
    //
    // FP MEDIDO ANTES de entrar: `--reporter=junit` não aparecia em NENHUM workflow, logo a
    // lista derivada é BYTE-IDÊNTICA antes e depois desta mudança (14 lanes). A perna nova só
    // passa a casar quando uma lane vitest de fato emitir — é adição, não reclassificação.
    //
    // O critério segue sendo o FATO (emite E transporta), nunca o nome do arquivo/pasta — a
    // família "critério sintático de nome" está morta no §5 (allowlist-de-pasta 2026-06-30,
    // guard `@scope` 2026-07-09).
    const linhasVivas = yml.split(/\r?\n/).filter((l) => !/^\s*#/.test(l));
    const emiteForaDeComentario = (needle) => linhasVivas.some((l) => l.includes(needle));
    // `--reporter=junit` OU `--reporter junit`. Exigi-lo é o que impede o `--outputFile` de
    // um reporter QUALQUER (json/coverage) virar lane: sem reporter junit o .xml não é JUnit.
    const temReporterJunit = linhasVivas.some((l) => /--reporter[=\s]+junit\b/.test(l));

    const xmls = new Set();
    for (const m of yml.matchAll(/--log-junit\s+(\S+\.xml)/g)) {
      if (emiteForaDeComentario(`--log-junit ${m[1]}`)) xmls.add(m[1]);
    }
    if (temReporterJunit) {
      for (const m of yml.matchAll(/--outputFile[=\s]+(\S+\.xml)/g)) {
        if (emiteForaDeComentario(m[1])) xmls.add(m[1]);
      }
    }
    for (const xml of xmls) {
      const b = blocos.find((x) => x.paths.some((p) => p.includes(xml)));
      if (b) out.push({ workflow: arq, artifact: b.name, via: 'junit', junit: xml });
    }

    // (b) lane que publica o MANIFESTO pronto
    const bMan = blocos.find((x) => x.paths.some((p) => p.includes(MANIFEST_REL)));
    if (bMan && !out.some((o) => o.workflow === arq && o.artifact === bMan.name)) {
      out.push({ workflow: arq, artifact: bMan.name, via: 'manifesto', junit: MANIFEST_REL });
    }
  }
  // o próprio publisher não é lane (ele consome; citar-se seria laço)
  return out.filter((o) => o.workflow !== 'casos-results-publish.yml');
}

// ---------------------------------------------------------------------------
// selftest — a régua tem que MORDER (achar a lane) e SOLTAR (não inventar)
// ---------------------------------------------------------------------------
function selftest() {
  const dir = mkdtempSync(join(tmpdir(), 'junit-lanes-'));
  const casos = [];
  const escrever = (n, c) => writeFileSync(join(dir, n), c, 'utf8');

  // 1. BOM: emite junit + publica o XML → tem que achar
  escrever('boa-pest.yml', `jobs:\n  t:\n    steps:\n      - run: |\n          vendor/bin/pest --log-junit test-results/pest-boa-junit.xml\n      - name: up\n        uses: actions/upload-artifact@v4\n        with:\n          name: pest-boa-junit\n          path: test-results/pest-boa-junit.xml\n`);
  // 2. RUIM: emite junit mas NÃO publica artifact → não pode aparecer (não há transporte)
  escrever('sem-artifact.yml', `jobs:\n  t:\n    steps:\n      - run: vendor/bin/pest --log-junit test-results/pest-orfa-junit.xml\n`);
  // 3. path em bloco literal `|` (caso do ci.yml) → tem que achar
  escrever('multi.yml', `jobs:\n  t:\n    steps:\n      - run: vendor/bin/pest --log-junit test-results/junit.xml\n      - name: up\n        uses: actions/upload-artifact@v4\n        with:\n          name: pest-multi-junit\n          path: |\n            test-results/junit.xml\n            test-results/junit-summary.json\n`);
  // 4. lane que publica o MANIFESTO pronto (caso do e2e-gate) → tem que achar via manifesto
  escrever('e2e.yml', `jobs:\n  t:\n    steps:\n      - name: up\n        uses: actions/upload-artifact@v4\n        with:\n          name: casos-test-results\n          path: ${MANIFEST_REL}\n`);
  // 5. menção em COMENTÁRIO só → não pode virar lane
  escrever('so-comentario.yml', `jobs:\n  t:\n    steps:\n      # doc: as lanes usam --log-junit test-results/pest-fantasma-junit.xml\n      - run: echo oi\n`);
  // 6. VITEST: reporter junit + outputFile + artifact → tem que achar (runner != Pest)
  escrever('boa-vitest.yml', `jobs:\n  t:\n    steps:\n      - run: npx vitest run tests/js/x.test.ts --reporter=junit --outputFile=test-results/vitest-boa-junit.xml\n      - name: up\n        uses: actions/upload-artifact@v4\n        with:\n          name: vitest-boa-junit\n          path: test-results/vitest-boa-junit.xml\n`);
  // 7. CONTROLE NEGATIVO: --outputFile SEM reporter junit → o .xml não é JUnit, não é lane
  escrever('outputfile-sem-junit.yml', `jobs:\n  t:\n    steps:\n      - run: npx alguma-coisa --reporter=json --outputFile=test-results/nao-e-junit.xml\n      - name: up\n        uses: actions/upload-artifact@v4\n        with:\n          name: nao-e-junit\n          path: test-results/nao-e-junit.xml\n`);
  // 8. CONTROLE NEGATIVO: vitest junit citado só em COMENTÁRIO → não vira lane
  escrever('vitest-so-comentario.yml', `jobs:\n  t:\n    steps:\n      # local: npx vitest run --reporter=junit --outputFile=test-results/vitest-fantasma-junit.xml\n      - run: echo oi\n`);

  const r = derivarLanes(dir);
  const achou = (wf) => r.some((x) => x.workflow === wf);
  casos.push(['MORDE: lane com junit + artifact', achou('boa-pest.yml')]);
  casos.push(['MORDE: path em bloco literal |', achou('multi.yml')]);
  casos.push(['MORDE: lane que publica o manifesto', achou('e2e.yml')]);
  casos.push(['MORDE: lane VITEST (reporter=junit + outputFile)', achou('boa-vitest.yml')]);
  casos.push(['SOLTA: junit sem artifact (sem transporte)', !achou('sem-artifact.yml')]);
  casos.push(['SOLTA: menção só em comentário', !achou('so-comentario.yml')]);
  casos.push(['SOLTA: --outputFile sem reporter junit', !achou('outputfile-sem-junit.yml')]);
  casos.push(['SOLTA: vitest junit só em comentário', !achou('vitest-so-comentario.yml')]);
  const b = r.find((x) => x.workflow === 'boa-pest.yml');
  casos.push(['artifact certo (não o -summary)', b && b.artifact === 'pest-boa-junit']);
  const v = r.find((x) => x.workflow === 'boa-vitest.yml');
  casos.push(['vitest: artifact + xml certos', v && v.artifact === 'vitest-boa-junit' && v.junit === 'test-results/vitest-boa-junit.xml']);

  rmSync(dir, { recursive: true, force: true });
  let ok = true;
  for (const [nome, passou] of casos) { console.log(`${passou ? '  ok  ' : '  FAIL'} ${nome}`); if (!passou) ok = false; }
  console.log(ok ? `\n✅ selftest ${casos.length}/${casos.length}` : '\n❌ selftest FALHOU');
  process.exit(ok ? 0 : 1);
}

// ---------------------------------------------------------------------------
if (process.argv.includes('--selftest')) selftest();
const lanes = derivarLanes();
if (process.argv.includes('--json')) {
  console.log(JSON.stringify(lanes, null, 2));
} else {
  for (const l of lanes) console.log(`${l.workflow}:${l.artifact}`);
}
