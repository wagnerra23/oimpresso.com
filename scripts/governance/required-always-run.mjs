#!/usr/bin/env node
// required-always-run.mjs — todo context REQUIRED nasce em TODO PR?
//
// ── O BURACO QUE ISTO FECHA (e por que nenhum gate existente pega) ───────────
// Um context required cujo workflow tem `paths:` no `pull_request` NÃO FALHA quando
// o PR não toca aqueles paths: ele simplesmente NÃO NASCE. O PR fica `Expected —
// Waiting for status to be reported`, para sempre, e o merge trava pro repo inteiro.
//
// É o incidente de 2026-07-02 (`main` BLOCKED com 54/54 checks verdes, proibicoes.md
// §Ambiente). E quase repetiu em 2026-08-05: o `module-surface` era advisory COM
// `paths:` (otimização legítima nessa fase), virou required pela ADR 0370 — e nesse
// momento precisava virar always-run. O #5069, de base antiga, ainda carrega o `paths:`;
// reconciliar preservando aquele lado ressuscita o deadlock.
//
// POR QUE O `protection-drift` NÃO COBRE: ele compara NOMES (baseline × vivo) e caça
// mojibake. Não pergunta se o workflow que PRODUZ cada context é alcançável por todo PR.
// E o próprio workflow filtrado não pode denunciar que não rodou — o `paths:` impede
// que ele nasça. Ausência não emite sinal: por isso a verificação tem que ser ESTÁTICA.
//
// ── O QUE MEDE ──────────────────────────────────────────────────────────────
//   cada context required (governance/required-checks-baseline.json)
//     → localiza o job produtor nos workflows
//     → confirma `pull_request` SEM `paths:`/`paths-ignore:`
//     → confirma que o `types:` inclui `synchronize` (ou que não há `types:`)
//     → FALHA se filtrado (o context não nasceria em PR fora do path) ou se o
//       context nasce no `opened` e não RE-nasce no push seguinte
//
// Resolve o job por 3 formas, porque o GitHub nomeia context de 3 jeitos:
//   (a) `name:` literal no job
//   (b) job SEM `name:` → o context é o próprio job id
//   (c) `name: ${{ matrix.<campo> }}` → expande os valores da matrix
//
// ── HONESTIDADE (o limite, declarado) ───────────────────────────────────────
// Context que não casa com job nenhum sai como AVISO, nunca como falha: acusar o que
// não consegui resolver seria inventar veredito. E passar calado seria gate mudo — a
// doença que este repo caça (§Sempre fazer #5). O aviso é o meio-termo honesto.
//
// FP MEDIDO ANTES DE ARMAR (main, 2026-08-05): 40 contexts · 34 resolvidos ·
// **0 path-triggered** · 0 sem pull_request. Zero falso-positivo no corpus real.
//
// ── `types:` sem `synchronize` — o 4º jeito, e o único que NÃO some no `opened` ──
// Os 3 eixos acima matam o check-run em TODO evento do PR. Este mata só a partir do
// SEGUNDO push: `types: [opened, reopened, ready_for_review]` faz o context nascer
// quando o PR abre e NUNCA MAIS. A branch protection avalia o **head SHA**; no head
// novo o check-run não existe, e o PR fica `BLOCKED` com 0 falhas e 0 pendentes — a
// mesma assinatura do incidente de 2026-07-02 (proibicoes.md §Ambiente), por outra porta.
//
// POR QUE ISTO APARECEU AGORA: até 2026-09-02 era 1 workflow. O #6622 (sessão "Fila do
// CI: o gargalo era o teto de 20 jobs simultâneos") tirou `synchronize` de 68 advisory
// para cortar 49% do volume por push — decisão medida, correta, e que NÃO se toca aqui.
// Ela criou junto uma regra de promoção, escrita em prosa em 3 lugares (a sessão, o
// `gates-registry.json` do backup-pest, e um comentário no próprio backup-pest.yml):
// **"quem promover um deles a required reinsere `synchronize` ANTES do flip"**.
// Regra escrita+lembrada apodrece (ADR 0256); esta perna é ela derivada+enforçada.
//
// FP MEDIDO ANTES DE ARMAR (main 9101f86af5, 2026-09-08): 139 workflows · 119 disparam
// em `pull_request` · 80 declaram `types:` · **69 sem `synchronize`** · 45 contexts
// required (união classic+rulesets) · **0 required sem `synchronize`** ⇒ zero
// falso-positivo. Controle positivo do matcher: 41 dos 45 casaram com um job por nome;
// os 4 restantes são `name: ${{ matrix.label }}` de `memory-schema-gate`/`anchor-drift`,
// conferidos à mão — os dois declaram `pull_request:` SEM `types:`, logo herdam o
// default `[opened, synchronize, reopened]`. Balde perigoso (não-required hoje): 69
// workflows / 79 jobs, ~17× o balde do eixo `if:` — mesmo desenho preventivo, risco maior.
//
// Parsing TEXTUAL de propósito (sem js-yaml): o `governance-script-tests.yml` não
// instala deps, e um lint que só roda onde há `npm ci` é um lint que não roda.
//
// Uso:
//   node scripts/governance/required-always-run.mjs            # relatório (exit 0/1)
//   node scripts/governance/required-always-run.mjs --json
//   node scripts/governance/required-always-run.mjs --selftest # fixtures herméticas
//
// Exit: 0 = todo required nasce em todo PR e re-nasce em todo push
//       1 = há required filtrado, sem gatilho, com `if:` falso em PR, ou sem
//           `synchronize` no `types:` — todos deadlock latente

import { readFileSync, readdirSync, existsSync, mkdtempSync, writeFileSync, mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { execFileSync } from 'node:child_process';

const ROOT = process.cwd();

/**
 * Normaliza CRLF→LF. TODA função exportada chama isto na entrada.
 *
 * Por quê (medido 2026-08-05, não suposto): este repo é Windows e os workflows têm
 * `\r\n`. O `$` do JS em modo `m` casa antes de `\n` — **nunca** antes de `\r\n`.
 * Então `on:\r` não casa `/^on:[ \t]*$/m` e o bloco vem VAZIO: o lint concluiria
 * "sem gatilho pull_request" e liberaria um workflow que na verdade tem `paths:`.
 * Falso-NEGATIVO silencioso, exatamente o que este gate existe pra impedir.
 * Normalizar na borda > remendar `\r?` em cada regex (um esquecido volta o bug).
 */
const nl = (s) => String(s).replace(/\r\n/g, '\n');

/** o bloco `on:` (até a próxima chave de topo) — texto cru. */
export function blocoOn(srcCru) {
  const src = nl(srcCru);
  // `[ \t]*$\n` e NÃO `\s*$`: o `\s*` é greedy e ENGOLE os newlines seguintes, fazendo
  // o bloco começar depois do sub-bloco que interessa. Mesmo bug em todo regex de
  // indentação deste arquivo — `\s` inclui `\n`, e YAML é sensível a linha.
  const m = src.match(/^on:[ \t]*$\n([\s\S]*?)(?=^\S)/m);
  if (m) return m[1];
  const inline = src.match(/^on:[ \t]+\S[\s\S]*?(?=^\S)/m);   // `on: [push]` / `on: push`
  return inline ? inline[0] : '';
}

/**
 * Os valores de `types:` dentro do corpo de um `pull_request:` — ou `null` quando não
 * há `types:` (que é o caso BOM: o default do GitHub já é `[opened, synchronize,
 * reopened]`).
 *
 * Aceita as duas formas do YAML mesmo o corpus sendo 100% inline hoje (medido: 81 de
 * 81 com `[...]`, 0 em bloco). Suportar só a forma vigente deixaria o lint cego no dia
 * em que alguém escrever em bloco — falso-NEGATIVO silencioso, a mesma doença que o
 * CRLF causou aqui em 2026-08-05.
 */
export function typesDoPR(corpo) {
  // `^[ \t]+types:` e não `types:` solto: as 3 linhas do repo que citam `types:` em
  // PROSA vivem em comentário (`# ... types: ...`) e não podem virar veredito.
  const m = String(corpo).match(/^[ \t]+types:[ \t]*(.*)$/m);
  if (!m) return null;
  const resto = m[1].trim();
  if (resto.startsWith('[')) {
    // inline: `types: [opened, reopened]` — corta em `]` e ignora comentário à direita
    const fecha = resto.indexOf(']');
    const dentro = fecha === -1 ? resto.slice(1) : resto.slice(1, fecha);
    return dentro.split(',').map((s) => s.trim().replace(/^["']|["']$/g, '')).filter(Boolean);
  }
  // bloco: `types:` sozinho, seguido de `- valor` mais indentados
  const bloco = String(corpo).match(/^([ \t]+)types:[ \t]*$([\s\S]*?)(?=^\1[^\s-]|$(?![\s\S]))/m);
  const out = [];
  if (bloco) {
    for (const l of bloco[2].split('\n')) {
      const v = l.match(/^[ \t]*-[ \t]*([^#\n]+)/);
      if (v) out.push(v[1].trim().replace(/^["']|["']$/g, ''));
    }
  }
  return out;
}

/**
 * O workflow dispara em TODO pull_request?
 * Retorna { temPR, filtrado, semSync, types, motivo }.
 */
export function gatilhoPR(srcCru) {
  const src = nl(srcCru);
  const on = blocoOn(src);
  if (!on) return { temPR: false, filtrado: false, semSync: false, types: null, motivo: 'sem bloco on:' };
  if (/^on:\s*\[?[^\n]*pull_request/m.test(src) && !/^\s+pull_request:/m.test(on)) {
    // `on: [pull_request]` não tem onde declarar `types:` → default, tem `synchronize`.
    return { temPR: true, filtrado: false, semSync: false, types: null, motivo: 'on: inline com pull_request' };
  }
  // `[ \t]` e NÃO `\s`: \s engloba \n, e aí a captura de indentação atravessa linhas —
  // o `\1` do lookahead vira "\n  " e o sub-bloco nunca fecha. O selftest pegou isso.
  // `$(?![\s\S])` e NÃO `\Z`: **JavaScript não tem `\Z`** — ele vira o literal "Z", e o
  // lookahead nunca casa o fim da string, então o match inteiro falha em silêncio.
  // Âncora de fim-absoluto em JS é `$(?![\s\S])`.
  const pr = on.match(/^([ \t]+)pull_request:[ \t]*$([\s\S]*?)(?=^\1[^\s]|$(?![\s\S]))/m);
  if (!pr) {
    // `pull_request:` sem sub-bloco → sem `types:` → default, tem `synchronize`.
    if (/pull_request/.test(on)) return { temPR: true, filtrado: false, semSync: false, types: null, motivo: 'pull_request sem sub-bloco' };
    return { temPR: false, filtrado: false, semSync: false, types: null, motivo: 'sem pull_request no gatilho' };
  }
  const corpo = pr[2] || '';
  const filtro = corpo.match(/^[ \t]+(paths|paths-ignore):/m);
  const types = typesDoPR(corpo);
  // `types: null` = sem `types:` declarado = default do GitHub, que JÁ inclui
  // `synchronize`. Só a lista EXPLÍCITA que o omite é que mata o re-nascimento.
  const semSync = Array.isArray(types) && !types.includes('synchronize');
  const motivos = [];
  if (filtro) motivos.push(`pull_request com \`${filtro[1]}:\``);
  if (semSync) motivos.push(`\`types:\` sem \`synchronize\` (${types.join(', ')})`);
  return {
    temPR: true,
    filtrado: Boolean(filtro),
    semSync,
    types,
    motivo: motivos.join(' + ') || 'pull_request always-run',
  };
}

/**
 * Contexts que um workflow produz → [{ context, jobId, via }].
 * (a) name literal · (b) job sem name → job id · (c) name: ${{ matrix.X }} → valores.
 */
export function contextsDoWorkflow(srcCru) {
  const src = nl(srcCru);
  const out = [];
  const jobsBloco = src.match(/^jobs:[ \t]*$\n([\s\S]*)/m);
  if (!jobsBloco) return out;
  // cada job: chave com 2 espaços de indentação
  const partes = jobsBloco[1].split(/^  (?=[A-Za-z0-9_-]+:[ \t]*$)/m).filter(Boolean);
  for (const parte of partes) {
    const idm = parte.match(/^([A-Za-z0-9_-]+):[ \t]*$/m);
    if (!idm) continue;
    const jobId = idm[1];
    const nm = parte.match(/^[ \t]{4}name:[ \t]*(.+)$/m);
    if (!nm) { out.push({ context: jobId, jobId, via: 'job-id (sem name:)' }); continue; }
    const nome = nm[1].trim().replace(/^["']|["']$/g, '');
    const mx = nome.match(/^\$\{\{\s*matrix\.([A-Za-z0-9_]+)\s*\}\}$/);
    if (!mx) { out.push({ context: nome, jobId, via: 'name literal' }); continue; }
    // expande a matrix: procura `campo:` dentro de strategy.matrix e pega os itens
    const campo = mx[1];
    const valores = [];
    // idem: `[ \t]` pra indentação. Forma A — `campo:` seguido de lista `- valor`.
    const bloco = parte.match(new RegExp(`^([ \\t]+)${campo}:[ \\t]*$([\\s\\S]*?)(?=^\\1[^\\s-]|$(?![\\s\\S]))`, 'm'));
    if (bloco) {
      for (const l of bloco[2].split('\n')) {
        const v = l.match(/^[ \t]*-[ \t]*(.+)$/);
        if (v) valores.push(v[1].trim().replace(/^["']|["']$/g, ''));
      }
    }
    // Forma B — matrix como lista de objetos: `- label: X`
    if (!valores.length) {
      for (const l of parte.split('\n')) {
        const v = l.match(new RegExp(`^[ \\t]*-?[ \\t]*${campo}:[ \\t]*(.+)$`));
        if (v) valores.push(v[1].trim().replace(/^["']|["']$/g, ''));
      }
    }
    for (const v of valores) out.push({ context: v, jobId, via: `matrix.${campo}` });
  }
  return out;
}

// ── `name:` que quebra o parser do GitHub — o caso EXTREMO deste mesmo lint ──
// Este script pergunta "o context required NASCE?". Um workflow que **não parseia**
// é a forma mais severa de não-nascer: o run sai `startup_failure` com ZERO jobs,
// nenhum step reporta nada porque nenhum step chega a existir, e a lane inteira
// morre em silêncio — em toda branch, inclusive `main`.
//
// Aconteceu em 2026-08-08 (#5424): `- name: Censo Blade->React (bite: resource-string
// … NEG: json/ciclo/mailable)`. Escalar YAML **puro** (não citado) não aceita
// dois-pontos-espaço: o `bite: ` vira chave de mapa e o arquivo todo deixa de
// parsear (`bad indentation of a mapping entry (53:39)`). 46 `success` no dia
// anterior → 47 `failure` no dia. E a lane morta escondeu 2 outras dívidas do
// próprio PR, porque era ela que as vigiava.
//
// POR QUE TEXTUAL, e não `js-yaml`: o `js-yaml` **não é dependência declarada**
// (nem `dependencies` nem `devDependencies` — resolve só transitivamente) e o
// `governance-script-tests.yml` não roda `npm ci`. Vale aqui a mesma razão do
// cabeçalho: um lint que só roda onde há `npm ci` é um lint que não roda.
//
// FP MEDIDO ANTES DE ARMAR (2026-08-08, 121 workflows): escopo `name:` → **0 hits**.
// O escopo largo (qualquer chave) daria 17, TODOS legítimos — comentário depois do
// valor (`true   # advisory: …`) e flow mapping (`{ fetch-depth: 0 }`) —, por isso
// o escopo é `name:`, que é onde mora prosa livre. Comentário é removido antes de
// medir (em escalar puro, ` #` inicia comentário, então o `: ` de dentro dele não conta).
//
// LIMITE HONESTO: pega esta família (dois-pontos-espaço em `name:` não citado), não
// "todo YAML inválido" — indentação torta, tab, aspas não fechadas passam. E se o
// workflow QUEBRADO for o que hospeda este lint, ele não roda pra se denunciar.
export function nomesQueQuebramOParser(src, arquivo = '') {
  const achados = [];
  src.split('\n').forEach((linha, i) => {
    const m = linha.replace(/\r$/, '').match(/^(\s*-?\s*)name:\s+(.+)$/);
    if (!m) return;
    const valor = m[2].replace(/\s+#.*$/, '').trim();     // ` #` = comentário em escalar puro
    if (!valor || /^["'|>&*]/.test(valor)) return;        // citado, block scalar ou âncora = seguro
    if (!/: /.test(valor)) return;
    achados.push({ arquivo, linha: i + 1, valor });
  });
  return achados;
}

// ── `if:` de JOB — o 3º jeito de um required NÃO nascer ─────────────────────
// Este lint media 2 vetores (`paths:` filtrado · sem gatilho de PR) e era CEGO ao
// terceiro: um job com `if:` que é FALSO em contexto de pull_request nunca produz
// check-run, e o PR fica em `Expected — waiting for status` pra sempre — mesmo com o
// workflow always-run. Achado por revisão adversarial em 2026-08-10, logo após o flip
// da ADR 0373: `grep -c "if:"` no próprio script dava **0**.
//
// NÃO confundir com `if:` de STEP (indentação de 6+): step que não roda não impede o
// check-run do job de existir. Só o `if:` de JOB (indentação de 4) mata o check-run.
//
// FP MEDIDO ANTES DE ARMAR (2026-08-10, 121 workflows): **13** `if:` de job no repo.
// Destes, **2 são required** e os DOIS são seguros — `DS gate` (`!cancelled()`) e
// `ADR 0216 PR scan` (`== 'pull_request'`). O balde perigoso EXISTE (4 jobs: o
// `refresh` do system-map, o `submit` do handoff-sign, e 2 do governance-drift) mas
// **nenhum é required hoje** ⇒ 0 falso-positivo, e valor preventivo real: o dia em que
// alguém promover um desses, o lint morde antes do deadlock.
//
// TRÊS baldes, e o do meio é o único que reprova — INDECIDÍVEL nunca derruba, porque
// um lint que chuta no que não sabe é a família de guard sintático que o §5 já matou 5×.
export function classificaIfEmPR(expr) {
  const e = String(expr).replace(/\$\{\{|\}\}/g, ' ').replace(/\s+/g, ' ').trim();
  // depende de runtime (needs/outputs/env) → não dá pra decidir estaticamente
  if (/\bneeds\./.test(e) || /\benv\./.test(e) || /\binputs\./.test(e)) return 'INDECIDIVEL';
  const citaPR = /event_name\s*==\s*'pull_request'/.test(e);
  const negaPR = /event_name\s*!=\s*'pull_request'/.test(e);
  const outroEvento = /event_name\s*==\s*'(?:push|schedule|workflow_dispatch|release|issues|issue_comment)'/.test(e);
  if (negaPR) return 'PERIGOSO';                       // literalmente falso em PR
  if (outroEvento && !citaPR) return 'PERIGOSO';       // só casa outro evento
  if (citaPR) return 'SEGURO';                         // verdadeiro em PR
  if (/^!?\s*(cancelled|always|success)\(\)$/.test(e)) return 'SEGURO';
  return 'INDECIDIVEL';
}

/** Extrai `if:` de JOB (indentação 4) por job, com o `name:` quando houver. */
export function ifsDeJob(srcCru) {
  const src = nl(srcCru);
  const out = [];
  const jobsBloco = src.match(/^jobs:[ \t]*$\n([\s\S]*)/m);
  if (!jobsBloco) return out;
  const partes = jobsBloco[1].split(/^  (?=[A-Za-z0-9_-]+:[ \t]*$)/m).filter(Boolean);
  for (const parte of partes) {
    const idm = parte.match(/^([A-Za-z0-9_-]+):[ \t]*$/m);
    if (!idm) continue;
    const mIf = parte.match(/^[ \t]{4}if:[ \t]*(.+)$/m);
    if (!mIf) continue;
    const nm = parte.match(/^[ \t]{4}name:[ \t]*(.+)$/m);
    out.push({
      jobId: idm[1],
      context: nm ? nm[1].trim().replace(/^["']|["']$/g, '') : idm[1],
      ifExpr: mIf[1].trim(),
    });
  }
  return out;
}

function auditar(root = ROOT) {
  const baseP = join(root, 'governance', 'required-checks-baseline.json');
  // O baseline guarda o required em DUAS chaves — `classic_protection` (branch protection
  // clássica) e `rulesets` (ruleset novo do GitHub). Ler só a primeira deixava 1 context
  // fora da guarda: `Governance Gate (índice + memory-health + meta-teste)`, que vive em
  // `rulesets` e é justamente quem carrega o GT-G5. Ele está always-run hoje, então não
  // havia exposição viva — mas um `paths:` ali passaria batido, que é o defeito exato que
  // este lint existe pra impedir. União deduplicada: os dois conjuntos bloqueiam merge.
  const bl = JSON.parse(readFileSync(baseP, 'utf8'));
  const required = [...new Set([
    ...(bl.classic_protection?.contexts || []),
    ...(bl.rulesets?.contexts || []),
  ])];
  const dir = join(root, '.github', 'workflows');
  const mapa = new Map();                                   // context → {arquivo, gatilho, via}
  const parserQuebrado = [];                                // `name:` que mata o workflow inteiro
  const ifPorContext = new Map();                           // context → {arquivo, ifExpr, veredito}
  for (const f of readdirSync(dir).filter((x) => /\.ya?ml$/.test(x))) {
    const src = readFileSync(join(dir, f), 'utf8');
    parserQuebrado.push(...nomesQueQuebramOParser(src, f));
    const g = gatilhoPR(src);
    for (const c of contextsDoWorkflow(src)) {
      if (!mapa.has(c.context)) mapa.set(c.context, { arquivo: f, gatilho: g, via: c.via });
    }
    for (const j of ifsDeJob(src)) {
      if (!ifPorContext.has(j.context)) {
        ifPorContext.set(j.context, { arquivo: f, ifExpr: j.ifExpr, veredito: classificaIfEmPR(j.ifExpr) });
      }
    }
  }
  const filtrados = [], semPR = [], naoResolvidos = [], ok = [], semSync = [];
  const ifPerigoso = [], ifIndecidivel = [];
  for (const ctx of required) {
    const j = ifPorContext.get(ctx);
    if (j?.veredito === 'PERIGOSO') ifPerigoso.push({ ctx, ...j });
    else if (j?.veredito === 'INDECIDIVEL') ifIndecidivel.push({ ctx, ...j });
    const m = mapa.get(ctx);
    if (!m) { naoResolvidos.push(ctx); continue; }
    // `semSync` é ORTOGONAL a `filtrado`: um workflow pode ter os dois defeitos, e
    // reportar só o primeiro esconderia metade do conserto de quem for arrumar.
    if (m.gatilho.semSync) semSync.push({ ctx, ...m });
    if (m.gatilho.filtrado) filtrados.push({ ctx, ...m });
    else if (!m.gatilho.temPR) semPR.push({ ctx, ...m });
    else if (!m.gatilho.semSync) ok.push({ ctx, ...m });
  }
  return { required, filtrados, semPR, semSync, naoResolvidos, ok, parserQuebrado, ifPerigoso, ifIndecidivel };
}

// ── selftest: fixtures herméticas, com controle negativo ────────────────────
function selftest() {
  let falhas = 0;
  const ok = (c, n, extra = '') => { console.log((c ? '[OK]   ' : '[FALHOU] ') + n + (c ? '' : ` — ${extra}`)); if (!c) falhas++; };

  const semPaths = 'name: X\n\non:\n  pull_request:\n  workflow_dispatch:\n\njobs:\n  a:\n    name: Job A\n';
  const comPaths = "name: X\n\non:\n  pull_request:\n    paths:\n      - 'Modules/**'\n\njobs:\n  a:\n    name: Job A\n";
  const comIgnore = "name: X\n\non:\n  pull_request:\n    paths-ignore:\n      - 'docs/**'\n\njobs:\n  a:\n    name: Job A\n";
  const semNome = 'name: X\n\non:\n  pull_request:\n\njobs:\n  meu-job:\n    runs-on: ubuntu-latest\n';
  const comMatrix = 'name: X\n\non:\n  pull_request:\n\njobs:\n  v:\n    name: ${{ matrix.label }}\n    strategy:\n      matrix:\n        label:\n          - "ADR (memory/decisions/*.md)"\n          - "SPEC (memory/requisitos/*/SPEC.md)"\n';

  ok(gatilhoPR(semPaths).filtrado === false, 'always-run → NÃO filtrado');
  ok(gatilhoPR(comPaths).filtrado === true, 'MORDE: pull_request com paths → filtrado', JSON.stringify(gatilhoPR(comPaths)));
  ok(gatilhoPR(comIgnore).filtrado === true, 'MORDE: paths-ignore também filtra', JSON.stringify(gatilhoPR(comIgnore)));
  ok(gatilhoPR(semPaths).temPR === true, 'detecta que há pull_request');

  ok(contextsDoWorkflow(semPaths)[0]?.context === 'Job A', 'resolve context por name literal');
  ok(contextsDoWorkflow(semNome)[0]?.context === 'meu-job', 'resolve context por job id quando não há name:');
  const mx = contextsDoWorkflow(comMatrix).map((c) => c.context);
  ok(mx.includes('ADR (memory/decisions/*.md)') && mx.length === 2, 'expande name: ${{ matrix.label }}', JSON.stringify(mx));

  // E2E: sandbox com um required FILTRADO → exit 1; depois always-run → exit 0
  const dir = mkdtempSync(join(tmpdir(), 'rar-'));
  try {
    mkdirSync(join(dir, '.github', 'workflows'), { recursive: true });
    mkdirSync(join(dir, 'governance'), { recursive: true });
    writeFileSync(join(dir, 'governance', 'required-checks-baseline.json'),
      JSON.stringify({ classic_protection: { contexts: ['Job A'] } }));
    writeFileSync(join(dir, '.github', 'workflows', 'w.yml'), comPaths);
    ok(auditar(dir).filtrados.length === 1, 'BITE E2E: required com paths é acusado');
    writeFileSync(join(dir, '.github', 'workflows', 'w.yml'), semPaths);
    ok(auditar(dir).filtrados.length === 0, 'LIBERA E2E: required always-run passa');
    // controle: context que não resolve vira AVISO, não falha
    writeFileSync(join(dir, 'governance', 'required-checks-baseline.json'),
      JSON.stringify({ classic_protection: { contexts: ['Job A', 'Fantasma'] } }));
    const r = auditar(dir);
    ok(r.naoResolvidos.length === 1 && r.filtrados.length === 0,
      'context não-resolvido vira AVISO, nunca falha (não inventa veredito)', JSON.stringify(r.naoResolvidos));

    // CRLF E2E — este repo é Windows e os workflows têm `\r\n`. Sem este par, um
    // selftest 100% LF fica VERDE com o lint cego: o `$` do JS não casa antes de
    // `\r\n`, o bloco `on:` vem vazio e um workflow COM `paths:` passaria batido.
    // Foi exatamente o que aconteceu (financeiro-pest.yml, medido 2026-08-05).
    // Fixture idêntica à que já morde, só que com CRLF: tem que morder igual.
    writeFileSync(join(dir, 'governance', 'required-checks-baseline.json'),
      JSON.stringify({ classic_protection: { contexts: ['Job A'] } }));
    writeFileSync(join(dir, '.github', 'workflows', 'w.yml'), comPaths.replace(/\n/g, '\r\n'));
    ok(auditar(dir).filtrados.length === 1, 'CRLF: workflow \\r\\n com paths é acusado igual');
    writeFileSync(join(dir, '.github', 'workflows', 'w.yml'), semPaths.replace(/\n/g, '\r\n'));
    const rc = auditar(dir);
    ok(rc.filtrados.length === 0 && rc.naoResolvidos.length === 0,
      'CRLF: always-run resolve o context (não vira "não-resolvido" fantasma)', JSON.stringify(rc.naoResolvidos));
    // RULESETS E2E — o baseline tem duas chaves de required e o lint lia só
    // `classic_protection`. O context de `rulesets` (hoje: o umbrella do Governance Gate,
    // dono do GT-G5) ficava fora da guarda. Sem este par, o lint fica VERDE com um
    // required filtrado, que é o cenário que ele existe pra impedir.
    writeFileSync(join(dir, 'governance', 'required-checks-baseline.json'),
      JSON.stringify({ classic_protection: { contexts: [] }, rulesets: { contexts: ['Job A'] } }));
    writeFileSync(join(dir, '.github', 'workflows', 'w.yml'), comPaths);
    ok(auditar(dir).filtrados.length === 1, 'BITE E2E: required vindo de `rulesets` com paths é acusado');
    writeFileSync(join(dir, '.github', 'workflows', 'w.yml'), semPaths);
    ok(auditar(dir).filtrados.length === 0, 'LIBERA E2E: required de `rulesets` always-run passa');
    // controle: o mesmo context nas DUAS chaves conta uma vez só (união dedupada)
    writeFileSync(join(dir, 'governance', 'required-checks-baseline.json'),
      JSON.stringify({ classic_protection: { contexts: ['Job A'] }, rulesets: { contexts: ['Job A'] } }));
    ok(auditar(dir).required.length === 1, 'context repetido nas 2 chaves conta 1× (união, não concatenação)');
    // ── `name:` que quebra o parser (2026-08-08 · #5424) ────────────────────
    // A linha REAL que matou a lane, verbatim. Se este assert cair, o lint parou
    // de pegar o incidente que o originou.
    const quebra = '      - name: Censo Blade->React (bite: resource-string, namespace de grupo, indirecao $this-> · NEG: json/ciclo/mailable)';
    ok(nomesQueQuebramOParser(quebra).length === 1,
      'MORDE: `name:` não citado com `: ` (a linha real do #5424)');
    ok(nomesQueQuebramOParser(quebra.replace(/\r?$/, '\r')).length === 1,
      'MORDE em CRLF também (o repo é Windows)');
    // controles negativos — cada um é uma forma LEGÍTIMA que não pode acusar
    ok(nomesQueQuebramOParser("      - name: 'Censo (bite: x · NEG: y)'").length === 0,
      'LIBERA: o mesmo texto CITADO (é o conserto — não pode seguir vermelho)');
    ok(nomesQueQuebramOParser('      - name: Roda o guard   # advisory: não derruba').length === 0,
      'LIBERA: `: ` dentro de COMENTÁRIO (em escalar puro ` #` inicia comentário)');
    ok(nomesQueQuebramOParser('      - name: ${{ matrix.label }}').length === 0,
      'LIBERA: expressão de matrix (sem dois-pontos-espaço)');
    ok(nomesQueQuebramOParser('      - name: Passo simples\n  name: Workflow X').length === 0,
      'LIBERA: `name:` comum, no step e no topo do workflow');
    ok(nomesQueQuebramOParser('        run: node x.mjs --a b: c').length === 0,
      'LIBERA: outra chave que não `name:` (escopo medido — 17 hits legítimos fora dele)');
    // E2E: o arquivo quebrado derruba o auditar() inteiro, não só a função pura
    writeFileSync(join(dir, '.github', 'workflows', 'w.yml'),
      semPaths.replace('name: Job A', 'name: Job A (bite: x)'));
    ok(auditar(dir).parserQuebrado.length === 1,
      'BITE E2E: auditar() acusa o workflow que não parsearia');
    writeFileSync(join(dir, '.github', 'workflows', 'w.yml'), semPaths);
    ok(auditar(dir).parserQuebrado.length === 0, 'LIBERA E2E: árvore limpa não acusa');

    // controle: baseline sem a chave `rulesets` segue funcionando (retrocompat)
    writeFileSync(join(dir, 'governance', 'required-checks-baseline.json'),
      JSON.stringify({ classic_protection: { contexts: ['Job A'] } }));
    ok(auditar(dir).filtrados.length === 0, 'baseline sem `rulesets` não quebra (retrocompat)');
    // ── eixo 3: `if:` de JOB (achado adversarial 2026-08-10, pós-flip ADR 0373) ──
    // classificador puro — os 3 baldes, com as expressões REAIS medidas no repo
    ok(classificaIfEmPR("github.event_name != 'pull_request'") === 'PERIGOSO',
      'BITE: `!= pull_request` é falso em PR → PERIGOSO');
    ok(classificaIfEmPR("github.event_name == 'push' || github.event_name == 'workflow_dispatch'") === 'PERIGOSO',
      'BITE: só casa push/dispatch → PERIGOSO');
    ok(classificaIfEmPR("github.event_name == 'pull_request'") === 'SEGURO',
      'CN: `== pull_request` é verdadeiro em PR → SEGURO');
    ok(classificaIfEmPR('${{ !cancelled() }}') === 'SEGURO',
      'CN: `!cancelled()` (o DS gate real) → SEGURO');
    ok(classificaIfEmPR("github.event_name == 'push' || github.event_name == 'pull_request'") === 'SEGURO',
      'CN: cobre push E pull_request → SEGURO (o `||` não pode virar PERIGOSO)');
    ok(classificaIfEmPR("needs.detect.outputs.should_smoke == 'true'") === 'INDECIDIVEL',
      'CN: depende de `needs` → INDECIDÍVEL, nunca reprova');

    // extrator: `if:` de JOB (4 espaços) conta; `if:` de STEP (6+) NÃO
    const comIfJob = ['name: W', 'on:', '  pull_request:', 'jobs:', '  a:', '    name: Job A',
      "    if: github.event_name != 'pull_request'", '    steps:', '      - run: x'].join('\n');
    ok(ifsDeJob(comIfJob).length === 1 && ifsDeJob(comIfJob)[0].context === 'Job A',
      'extrator: pega `if:` de JOB com o name: do job');
    const comIfStep = ['name: W', 'on:', '  pull_request:', 'jobs:', '  a:', '    name: Job A',
      '    steps:', '      - run: x', '        if: failure()'].join('\n');
    ok(ifsDeJob(comIfStep).length === 0,
      'CN: `if:` de STEP (6 espaços) NÃO conta — step pulado ainda reporta o job');

    // E2E pelo auditar(): required com if: perigoso entra em ifPerigoso
    writeFileSync(join(dir, 'governance', 'required-checks-baseline.json'),
      JSON.stringify({ classic_protection: { contexts: ['Job A'] }, rulesets: { contexts: [] } }));
    writeFileSync(join(dir, '.github', 'workflows', 'w.yml'), comIfJob);
    ok(auditar(dir).ifPerigoso.length === 1, 'BITE: required com `if:` falso em PR → ifPerigoso');

    // ⚠️ Assert de DADO não prova FIAÇÃO. Medido por mutação nesta própria sessão:
    // tirar o `process.exit(1)` do ramo `ifPerigoso` deixava o lint de morder e o
    // selftest seguia VERDE (o `.length===1` acima continua verdadeiro). É o
    // sub-padrão do LC-11 ("bite-test cego à fiação"). Estes 2 rodam o CLI DE FORA
    // e olham o exit code — o mutante morre neles.
    const rodarCli = () => {
      try {
        execFileSync(process.execPath, [fileURLToPath(import.meta.url)], { cwd: dir, stdio: 'ignore' });
        return 0;
      } catch (e) { return e.status ?? 1; }
    };
    ok(rodarCli() === 1, 'BITE E2E (CLI): `if:` perigoso em required → exit 1');
    writeFileSync(join(dir, '.github', 'workflows', 'w.yml'), comIfJob.replace("!= 'pull_request'", "== 'pull_request'"));
    ok(auditar(dir).ifPerigoso.length === 0, 'LIBERA: `if: == pull_request` não acusa');
    ok(rodarCli() === 0, 'LIBERA E2E (CLI): `if:` seguro → exit 0');

    // ── eixo 4: `types:` sem `synchronize` (#6622 · 2026-09-08) ─────────────
    // extrator puro — as duas formas do YAML, e o comentário que NÃO pode virar veredito
    ok(JSON.stringify(typesDoPR('    types: [opened, reopened]\n')) === '["opened","reopened"]',
      'extrator: `types:` inline');
    ok(typesDoPR('    types: [opened, synchronize]  # nota à direita\n').includes('synchronize'),
      'extrator: comentário à DIREITA do `]` não entra na lista');
    ok(JSON.stringify(typesDoPR('    types:\n      - opened\n      - synchronize\n')) === '["opened","synchronize"]',
      'extrator: `types:` em BLOCO (forma que o corpus não usa hoje — cegueira futura)');
    ok(typesDoPR('    branches: [main]\n') === null,
      'extrator: sem `types:` → null (default do GitHub JÁ tem synchronize)');
    ok(typesDoPR('    # `types:` sem synchronize é o padrão dos advisory\n') === null,
      'CN: `types:` dentro de COMENTÁRIO não vira veredito (3 linhas assim no repo)');

    const semSyncW  = "name: X\n\non:\n  pull_request:\n    types: [opened, reopened, ready_for_review]\n\njobs:\n  a:\n    name: Job A\n";
    const comSyncW  = "name: X\n\non:\n  pull_request:\n    types: [opened, reopened, synchronize]\n\njobs:\n  a:\n    name: Job A\n";
    ok(gatilhoPR(semSyncW).semSync === true,  'MORDE: `types:` sem synchronize', JSON.stringify(gatilhoPR(semSyncW)));
    ok(gatilhoPR(comSyncW).semSync === false, 'LIBERA: `types:` COM synchronize');
    ok(gatilhoPR(semPaths).semSync === false, 'CN: sem `types:` → default, NÃO acusa (senão 39 workflows viram FP)');
    // o eixo é ortogonal ao `paths:` — os dois defeitos juntos têm que aparecer juntos
    const ambos = semSyncW.replace('    types:', "    paths:\n      - 'x/**'\n    types:");
    const gAmbos = gatilhoPR(ambos);
    ok(gAmbos.semSync === true && gAmbos.filtrado === true,
      'ortogonal: `paths:` + `types:` sem sync acusa os DOIS', JSON.stringify(gAmbos.motivo));

    writeFileSync(join(dir, 'governance', 'required-checks-baseline.json'),
      JSON.stringify({ classic_protection: { contexts: ['Job A'] } }));
    writeFileSync(join(dir, '.github', 'workflows', 'w.yml'), semSyncW);
    ok(auditar(dir).semSync.length === 1, 'BITE E2E: required sem synchronize é acusado');
    ok(auditar(dir).ok.length === 0, 'e NÃO entra em `ok` (senão o resumo diz "always-run" e mente)');
    // fiação: sem o `process.exit(1)` no ramo semSync o assert de dado acima segue
    // verde e o lint deixa de morder — o mutante só morre rodando o CLI de fora.
    ok(rodarCli() === 1, 'BITE E2E (CLI): required sem synchronize → exit 1');
    writeFileSync(join(dir, '.github', 'workflows', 'w.yml'), comSyncW);
    ok(auditar(dir).semSync.length === 0, 'LIBERA E2E: required com synchronize passa');
    ok(rodarCli() === 0, 'LIBERA E2E (CLI): required com synchronize → exit 0');
    // CRLF: este repo é Windows — sem este par o eixo nasce cego, como o `paths:` nasceu
    writeFileSync(join(dir, '.github', 'workflows', 'w.yml'), semSyncW.replace(/\n/g, '\r\n'));
    ok(auditar(dir).semSync.length === 1, 'CRLF: `types:` sem synchronize em \\r\\n é acusado igual');
    writeFileSync(join(dir, '.github', 'workflows', 'w.yml'), semPaths);
  } finally { rmSync(dir, { recursive: true, force: true }); }

  console.log(falhas ? `\n✗ ${falhas} falha(s)` : '\n✅ required-always-run: acusa filtrado, libera always-run, avisa o não-resolvido.');
  process.exit(falhas ? 1 : 0);
}

// guarda de main: sem isto, `import { gatilhoPR }` num teste roda a auditoria inteira
// (foi o que aconteceu ao depurar — o import cuspiu o relatório em vez do valor).
import { fileURLToPath } from 'node:url';
const ehMain = process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1];

// Flag desconhecida ABORTA (exit 2), nunca é ignorada em silêncio.
// Custou caro nesta própria sessão: rodei `--raiz <sandbox>` (flag que não existe),
// o script mediu o cwd errado e saiu 0 — um verde que provava nada. É a mesma classe
// do hook que aceitava `--selftest` sem ter selftest. Instrumento que aceita input
// que não entende produz resultado que ninguém pode auditar.
const CONHECIDAS = new Set(['--selftest', '--json']);
const desconhecidas = process.argv.slice(2).filter((a) => a.startsWith('-') && !CONHECIDAS.has(a));

if (!ehMain) { /* importado como módulo: só exporta */ }
else if (desconhecidas.length) {
  console.error(`✗ flag desconhecida: ${desconhecidas.join(' ')}\n  conhecidas: ${[...CONHECIDAS].join(' ')}\n  (a raiz auditada é sempre o cwd — rode a partir da árvore que quer medir)`);
  process.exit(2);
}
else if (process.argv.includes('--selftest')) selftest();
else main();

function main() {
const r = auditar();
if (process.argv.includes('--json')) {
  console.log(JSON.stringify(r, null, 2));
  process.exit(r.filtrados.length || r.parserQuebrado.length || r.semSync.length ? 1 : 0);
}

if (r.parserQuebrado.length) {
  console.log(`\n  ❌ ${r.parserQuebrado.length} \`name:\` NÃO CITADO com dois-pontos-espaço — o workflow inteiro deixa de parsear:\n`);
  for (const p of r.parserQuebrado) console.log(`     ${p.arquivo}:${p.linha}\n       name: ${p.valor}`);
  console.log(`\n  Escalar YAML puro não aceita \`: \` — vira chave de mapa e o run sai \`startup_failure\``);
  console.log(`  com ZERO jobs: nenhum step reporta nada porque nenhum step chega a existir, em toda`);
  console.log(`  branch inclusive \`main\` (incidente 2026-08-08 · #5424).`);
  console.log(`  CONSERTO: cite o valor — \`- name: 'Texto (com: dois-pontos)'\`.\n`);
  process.exit(1);
}

console.log(`\n  REQUIRED ALWAYS-RUN — ${r.required.length} contexts required · ${r.ok.length} always-run · ${r.filtrados.length} FILTRADO(s) · ${r.semSync.length} SEM \`synchronize\` · ${r.naoResolvidos.length} não-resolvido(s)\n`);
for (const f of r.filtrados) console.log(`  ❌ ${f.ctx}\n       ${f.arquivo} — ${f.gatilho.motivo}`);
for (const s of r.semPR) console.log(`  ❌ ${s.ctx}\n       ${s.arquivo} — ${s.gatilho.motivo}`);
for (const s of r.semSync) console.log(`  ❌ ${s.ctx}\n       ${s.arquivo} — ${s.gatilho.motivo}`);
if (r.naoResolvidos.length) {
  console.log(`\n  ⚠️  não casei com job nenhum (AVISO — limite conhecido, não veredito):`);
  for (const n of r.naoResolvidos) console.log(`     ${n}`);
}
if (r.ifIndecidivel.length) {
  console.log(`\n  ⚠️  \`if:\` de job que não dá pra decidir estaticamente (AVISO — não derruba):`);
  for (const j of r.ifIndecidivel) console.log(`     ${j.ctx}\n       ${j.arquivo} — if: ${j.ifExpr}`);
}
if (r.ifPerigoso.length) {
  console.log(`\n  ❌ ${r.ifPerigoso.length} required com \`if:\` de JOB que é FALSO em pull_request:\n`);
  for (const j of r.ifPerigoso) console.log(`     ${j.ctx}\n       ${j.arquivo} — if: ${j.ifExpr}`);
  console.log(`\n  Job com \`if:\` falso não produz check-run — mesmo com o workflow always-run. É o`);
  console.log(`  3º jeito de um required não nascer (os outros 2: \`paths:\` filtrado · sem gatilho de PR),`);
  console.log(`  e o efeito é idêntico: "Expected — waiting for status" pra sempre.`);
  console.log(`  CONSERTO: tire o \`if:\` do JOB (o de STEP é inofensivo — step pulado ainda reporta o job),`);
  console.log(`  ou não promova este context a required.\n`);
  process.exit(1);
}
if (r.semSync.length) {
  console.log(`\n  ❌ ${r.semSync.length} required cujo \`types:\` omite \`synchronize\`:\n`);
  for (const s of r.semSync) console.log(`     ${s.ctx}\n       ${s.arquivo} — types: [${(s.gatilho.types || []).join(', ')}]`);
  console.log(`\n  Este context nasce quando o PR ABRE e nunca mais. A branch protection avalia o`);
  console.log(`  **head SHA**: no segundo push o check-run não existe naquele SHA e o PR fica`);
  console.log(`  \`BLOCKED\` com 0 falhas e 0 pendentes (lápide 2026-08-08 · RUNBOOK-branch-protection`);
  console.log(`  §Promoção). É o 4º jeito de um required não nascer — e o único que passa no PR`);
  console.log(`  recém-aberto, então não aparece em teste manual de 1 push.`);
  console.log(`  CONSERTO: acrescente \`synchronize\` ao \`types:\` deste workflow (o #6622 tirou de 68`);
  console.log(`  advisory de propósito, pra cortar fila de CI — mas required precisa re-nascer).\n`);
  process.exit(1);
}
if (r.filtrados.length || r.semPR.length) {
  console.log(`\n  Um context required cujo workflow é filtrado NÃO fica vermelho — ele NÃO NASCE.`);
  console.log(`  O PR fica "Expected — waiting for status" pra sempre e o merge trava pro repo inteiro`);
  console.log(`  (incidente 2026-07-02, proibicoes.md §Ambiente).`);
  console.log(`  CONSERTO: tire o \`paths:\` do \`pull_request\` e, se o custo preocupar, use`);
  console.log(`  \`dorny/paths-filter\` INTERNO com skip-as-pass — o gatilho fica always-run e o job sai cedo.`);
  process.exit(1);
}
console.log('  ✅ todo context required nasce em todo PR — e RE-nasce em todo push.\n');
process.exit(0);
}
