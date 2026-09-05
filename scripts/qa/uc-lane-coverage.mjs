#!/usr/bin/env node
// uc-lane-coverage.mjs — o teste que o `casos.md` cita EXISTE, e alguma lane de CI o RODA?
//
// =====================================================================================
// POR QUE EXISTE (achado medido 2026-08-23)
// =====================================================================================
// A lane `ponto-pest.yml` roda uma ALLOWLIST de 11 arquivos; o módulo tem 37 no disco.
// Os 26 de fora não são "menos importantes" — são INVISÍVEIS: existem, podem estar
// vermelhos há meses, e nenhum PR os acorda. Rodados à mão no CT 100 naquele dia, 5 deles
// devolveram `2 failed · 13 skipped · 8 passed`, incluindo:
//
//   · `DashboardTest` × `DashboardDeferredContractTest` — um afirma que `kpis` está no
//     payload inicial, o irmão afirma que a prop é DEFERIDA. Os dois no mesmo módulo,
//     contradizendo-se, verdes na consciência de todo mundo porque nenhum roda por PR.
//   · `MultiTenantIsolationTest` — `@dataProvider` foi REMOVIDA no PHPUnit 12 (o repo está
//     no 12.5.23), então o teste que varre as 10 rotas do Ponto nunca executou nenhuma.
//   · os 5 casos cross-tenant do `CrossTenantMarcacaoTest`: 100% SKIP por fixture ausente.
//
// Um `casos.md` que cite qualquer um desses está apontando pra uma prova que o CI não
// colhe. O UC parece coberto e não está.
//
// =====================================================================================
// DOIS FORMATOS DE CITAÇÃO (o 2º entrou em 2026-08-26 — antes era um ponto CEGO)
// =====================================================================================
// A 1ª versão lia SÓ a tabela de Rastreabilidade (`| UC-XX | … | <Teste> | <Status> |`).
// O corpus tem um segundo formato igualmente vivo — cabeçalho `## UC-XX` com o teste num
// campo dedicado do bloco (`- **Teste:** …` ou `Status: 🧪 (FooTest …)`) — e ele não
// produzia par NENHUM: o UC não virava `órfão` nem `na-lane`, simplesmente não existia
// pra esta ferramenta. Silêncio, aqui, é indistinguível de "está tudo bem".
//
// Medido em 2026-08-26 nos 143 `.casos.md` que o script varre: 57 arquivos, com 386
// cabeçalhos `## UC-`, eram invisíveis; mais 27 UCs de cabeçalho viviam dentro de
// arquivos que TÊM tabela mas não os listam. Ler os dois formatos levou as citações de
// 481 pra 844 e os órfãos de 37 pra 117 — dívida que sempre existiu e só ficou visível.
//
// O caso que originou: `Jana/Pro.casos.md` cita `ProContractTest` nos 6 UCs; o arquivo
// não está em lane alguma (confirmado no DONO, `test-lane-coverage --json`). O eixo
// vizinho `uc-sem-lane.mjs` também não o via, por razão INDEPENDENTE — lá a chave é o
// UC-id no TÍTULO do `it()`, e nenhum dos 6 `it()` daquele arquivo cita UC. Dois donos
// cegos por motivos diferentes é o que torna este conserto necessário AQUI, e não um
// "o outro já cobre". O detalhe do FP medido está no docblock de `citacoesEm`.
//
// =====================================================================================
// ESCOPO — DUAS perguntas, não quatro (o resto JÁ TEM DONO)
// =====================================================================================
// O pedido original trazia 4 perguntas. Duas delas são do `casos-coverage-guard` (G-7,
// REQUIRED), que já mede "Status MENTE (✅ declarado vs teste FALHOU)" e "Status SEM PROVA"
// contra o manifesto `scripts/casos-test-results.json` — e os dois estavam zerados no dia.
// Reimplementá-las aqui seria abrir um 2º dono do mesmo tema (§5 2026-07-09: "duplica régua
// consolidada"). Então este script responde só o que ninguém responde:
//
//   (1) o arquivo de teste citado EXISTE no disco?
//   (2) alguma lane de CI o RODA?
//
// O `Status` declarado e o `verdict` do manifesto entram como COLUNA DE CONTEXTO — lidos do
// dono, nunca recalculados aqui. `skip` é destacado porque foi o mecanismo de mascaramento
// medido (13 de 23 casos), e porque skip sai com exit 0 (LC-13).
//
// =====================================================================================
// OS DOIS DONOS VIZINHOS — e a duplicação que eu cometi aqui (2026-08-23)
// =====================================================================================
// `scripts/governance/test-lane-coverage.mjs` (desde 2026-08-07) é o DONO da derivação do
// run-set: "quais testes EXISTEM × quais o CI realmente EXECUTA". A 1ª versão deste arquivo
// REIMPLEMENTOU aquilo — parser de workflow, matriz, `.list`, `find`, quarentena — e ainda
// trazia uma seção provando que não duplicava o `anchor-lint --check-lane`. Eu conferi UM
// dono e escrevi como se tivesse conferido o campo. A §5 2026-07-28 exige varredura no repo
// inteiro E o dono do inventário; parei no primeiro. LC-19, dentro do PR que se gabava dele.
//
// Hoje a derivação é IMPORTADA. O que resta aqui é o join que ninguém faz: a coluna `Teste`
// da tabela de Rastreabilidade de um `casos.md` × esse run-set. O dono responde "que
// ARQUIVOS ficam fora do PR"; este responde "que UC aponta pra um deles" — o que torna o
// número acionável por tela.
//
// `anchor-lint --check-lane` (G1c) é o TERCEIRO vizinho, no eixo **US → teste**, e resolve
// lane por PREFIXO DE DIRETÓRIO. Medido replicando `inLane()` linha a linha: ele diz que
// `DashboardTest`, `MultiTenantIsolationTest` e `CrossTenantMarcacaoTest` estão "em lane" —
// e a `ponto-pest.yml` roda 12 dos 39 arquivos daquela pasta. Não é defeito dele: a
// granularidade de pasta basta pro que ele mede.
//
// =====================================================================================
// TRÊS ESTADOS, NÃO DOIS — a semântica é do dono
// =====================================================================================
// `na-lane` · `quarentena` · `órfão`. A quarentena declarada (`.github/*-quarantine.list`)
// NÃO é órfã, e a razão está no dono, literal:
//
//   "Alguém decidiu conscientemente que não rodam, e a lane imprime a lista. Órfão é o que
//    ninguém decidiu — some sem ninguém saber. Misturar os dois apagaria justamente a
//    diferença que este script existe pra mostrar."
//
// A 1ª versão daqui SUBTRAÍA a quarentena do run-set, o que faz teste conscientemente parado
// aparecer como órfão. Medido: 19 dos 56 "órfãos" que este script reportava eram quarentena.
// O número honesto é 37.
//
// USO (na raiz do repo):
//   node scripts/qa/uc-lane-coverage.mjs                 # tabela + resumo (exit 0)
//   node scripts/qa/uc-lane-coverage.mjs --check         # exit 1 se houver UC com teste órfão
//   node scripts/qa/uc-lane-coverage.mjs --check --baseline governance/uc-lane-baseline.json
//   node scripts/qa/uc-lane-coverage.mjs --write-baseline governance/uc-lane-baseline.json
//   node scripts/qa/uc-lane-coverage.mjs --json          # determinístico
//   node scripts/qa/uc-lane-coverage.mjs --lanes         # resumo do run-set (detalhe é no dono)
//
// BITE-TEST: node scripts/qa/uc-lane-coverage.test.mjs (irmão) — exercita o CLI de fora, com
// controle negativo, incluindo "quarentena não vira órfão" e, desde 2026-08-26, o par
// mínimo do FORMATO 2: cabeçalho citando teste EM LANE não acrescenta órfão · cabeçalho
// citando teste ÓRFÃO morde o no-new-lie. Os asserts do formato novo são de DELTA, não
// absolutos — a fixture já carrega dívida deliberada das seções anteriores, e assert que o
// estado herdado satisfaz sozinho não discrimina (§5 2026-08-24). Aferido rodando este
// bite-test contra o parser ANTIGO: 8 asserções caem; com o novo, 0.

import { readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join, sep } from 'node:path';
import { pathToFileURL } from 'node:url';
import { ucBlocksInCasos } from '../lib/uc-regex.mjs';

const argv = process.argv.slice(2);
const has = (f) => argv.includes(f);
const valOf = (f) => { const i = argv.indexOf(f); return i >= 0 ? argv[i + 1] : null; };

const CHECK = has('--check');
const JSON_OUT = has('--json');
const LANES_ONLY = has('--lanes');
const BASELINE_PATH = valOf('--baseline');
const WRITE_BASELINE = valOf('--write-baseline');

const ROOT = valOf('--root') || process.cwd();
const posix = (p) => p.split(sep).join('/');
const abs = (p) => join(ROOT, p);

// ═════════════════════════════════════════════════════════════════════════════════════
// 1. RUN-SET POR LANE — derivado do workflow
// ═════════════════════════════════════════════════════════════════════════════════════

// ═════════════════════════════════════════════════════════════════════════════════════
// 1. RUN-SET POR LANE — CONSUMIDO do dono, não reimplementado
// ═════════════════════════════════════════════════════════════════════════════════════
//
// A 1ª versão deste script REIMPLEMENTOU a derivação do run-set: parser de workflow,
// expansão de `strategy.matrix`, leitura de `.list`, `find -name` e quarentena. ~150 linhas
// que já existiam em `scripts/governance/test-lane-coverage.mjs` desde 2026-08-07, cujo
// próprio cabeçalho diz: "quais testes EXISTEM × quais o CI realmente EXECUTA".
//
// Foi LC-19 (máquina paralela a um tema que já tem dono) cometido dentro de um PR que
// trazia uma seção provando não duplicar o `anchor-lint --check-lane`. Eu conferi UM dono
// e escrevi como se tivesse conferido o campo — a §5 2026-07-28 exige varredura no repo
// inteiro E o dono do inventário, e eu parei no primeiro.
//
// E a cópia não era só redundante: era PIOR num ponto que importa. Eu SUBTRAÍA a quarentena
// do run-set, o que faz teste conscientemente parado aparecer como órfão. O dono trata
// quarentena como TERCEIRA CATEGORIA, e explica por quê:
//
//   "NÃO são órfãos: alguém decidiu conscientemente que não rodam, e a lane imprime a
//    lista. Órfão é o que ninguém decidiu — some sem ninguém saber."
//
// Medido: 19 dos 56 "órfãos" que eu reportava estavam em quarentena declarada. O número
// honesto é 37.
//
// O que sobra aqui, e é o que ninguém mede: o JOIN entre a coluna `Teste` da tabela de
// Rastreabilidade de um `casos.md` e esse run-set. O dono responde "que ARQUIVOS ficam fora
// do PR"; este responde "que UC aponta pra um deles" — que é o que torna acionável por tela.
import { coletarAlvos, estaCoberto, emQuarentena } from '../governance/test-lane-coverage.mjs';

// ═════════════════════════════════════════════════════════════════════════════════════
// 2. UC → TESTE CITADO (tabela de Rastreabilidade do casos.md)
// ═════════════════════════════════════════════════════════════════════════════════════

// Instância FRESCA por chamada: regex /g é stateful (lastIndex) e compartilhar uma global
// entre chamadas é footgun — mesma disciplina de `ucScanRe()` em scripts/lib/uc-regex.mjs.
const nomeDeTesteRe = () => /`?\b(\w*Test)\b`?/g;

// Campos DEDICADOS de um bloco `## UC-XX` — o bullet `- **Teste:** …` e a linha `Status: …`.
// A restrição a estes dois é MEDIDA, não gosto (ver "POR QUE SÓ OS CAMPOS DEDICADOS").
const campoDedicadoRe = /\*\*\s*Testes?\s*[:：]|Status\s*[:：]/i;

/**
 * Pares UC→teste citados por um `casos.md`, nos DOIS formatos vivos do corpus.
 *
 * FORMATO 1 — TABELA: `| UC-XX | … | <Teste> | <Status> |`. As duas últimas colunas são
 * Teste e Status por contrato do template.
 *
 * FORMATO 2 — CABEÇALHO: `## UC-XX — título` + campos no bloco (`- **Teste:** …` e/ou
 * `Status: 🧪 (FooTest …)`). É tão vivo quanto o primeiro e ficava INVISÍVEL: não virava
 * `órfão` nem `na-lane` — simplesmente não existia pra esta ferramenta, e silêncio aqui é
 * indistinguível de "está tudo bem". Medido em 2026-08-26 sobre os 143 `.casos.md` que o
 * script varre (o corpus bruto tem 189; 46 vivem em `design-docs/cowork-inbox/`, que
 * `foraDoInbox` exclui por design): 57 arquivos com 386 cabeçalhos `## UC-` não
 * contribuíam UM par sequer.
 *
 * O caso que originou: `Jana/Pro.casos.md` declara nos 6 UCs `Status: 🧪 (ProContractTest
 * …)` e o `ProContractTest` não está em lane nenhuma — confirmado no DONO
 * (`test-lane-coverage --json` → `arquivos_orfaos`). O eixo vizinho `uc-sem-lane.mjs`
 * também não o vê, por razão INDEPENDENTE: lá a chave é o UC-id no TÍTULO do `it()`, e
 * nenhum dos 6 `it()` daquele arquivo cita UC. Dois donos cegos por motivos diferentes —
 * por isso o conserto é aqui, e não "o outro já cobre".
 *
 * POR QUE SÓ OS CAMPOS DEDICADOS, e não o bloco inteiro — FP medido ANTES (o §5 de
 * `memory/proibicoes.md` tem 5 lápides de guard sintático que reprovava o legítimo):
 * varrendo o BLOCO todo saem 254 nomes; restrito aos dedicados, 243 (95,7%). Os 11
 * descartados vinham de prosa solta — inclusive comentário META sobre lane
 * ("`ScorecardContratoTest.php` **não está** em …"), que nomeia um teste sem que aquele UC
 * o reivindique como prova. Atribuir prosa a UC seria inventar citação. Nos 254 e nos 243,
 * ZERO nome deixou de resolver pra arquivo real — o `\w*Test` é conservador (controle
 * negativo: `test.tsx` minúsculo e `TestKit` não casam).
 *
 * LIMITE HONESTO: o run-set desta ferramenta é de lane PHP, então citação de teste
 * front-end (`tests/jana-pro-voltar.test.tsx`, em UC-PRO-07) não casa `\w*Test`. Com o
 * bullet `**Teste:**` preenchido isso sai como `sem-nome-de-teste` (citou algo que este
 * eixo não resolve); sem bullet, como `sem-teste`. Nos dois casos NÃO é órfão e NÃO é
 * prova. Fechar o eixo front-end é outro trabalho, com outro run-set.
 *
 * DEDUPE: o bloco só é lido pro UC que a TABELA não cobriu. Num arquivo com os dois
 * formatos, contar duas vezes inflaria `citacoes` e quebraria a invariante
 * "soma dos estados == citações" que o bite-test trava.
 */
export function citacoesEm(content) {
  const out = [];
  const blocos = [...ucBlocksInCasos(content)];
  const declarados = new Set(blocos.map((b) => b.uc));
  const naTabela = new Set();

  // ── FORMATO 1: tabela de Rastreabilidade ─────────────────────────────────────────
  for (const linha of String(content).split('\n')) {
    if (!linha.trim().startsWith('|')) continue;
    const cols = linha.split('|').map((c) => c.trim());
    if (cols.length < 5) continue;
    const uc = (/\b(UC-[A-Z0-9-]+)\b/.exec(cols[1] || '') || [])[1];
    if (!uc) continue;
    const status = cols[cols.length - 2];
    const teste = cols[cols.length - 3];
    if (/^-+$/.test(status)) continue;                       // linha separadora
    const nomes = [...String(teste).matchAll(nomeDeTesteRe())].map((m) => m[1]);
    naTabela.add(uc);
    out.push({ uc, teste: teste.replace(/`/g, '').trim(), nomes, status, declarado: declarados.has(uc), origem: 'tabela' });
  }

  // ── FORMATO 2: cabeçalho `## UC-XX` + campos dedicados do bloco ──────────────────
  for (const { uc, block } of blocos) {
    if (naTabela.has(uc)) continue;                          // dedupe — a tabela já falou por ele
    const nomes = new Set();
    let linhaTeste = '';
    let status = '';
    for (const l of block.split(/\r?\n/)) {
      if (!campoDedicadoRe.test(l)) continue;
      const limpa = l.replace(/^[\s>\-*]+/, '').trim();
      if (!linhaTeste && /\*\*\s*Testes?\s*[:：]/i.test(l)) linhaTeste = limpa;
      if (!status && /Status\s*[:：]/i.test(l)) status = limpa;
      for (const m of l.matchAll(nomeDeTesteRe())) nomes.add(m[1]);
    }
    const lista = [...nomes];
    const teste = lista.length ? lista.join(', ') : linhaTeste.replace(/`/g, '').trim();
    out.push({ uc, teste, nomes: lista, status, declarado: true, origem: 'bloco' });
  }
  return out;
}

// ═════════════════════════════════════════════════════════════════════════════════════
// 3. MAIN
// ═════════════════════════════════════════════════════════════════════════════════════

const lerArquivo = (rel) => { try { return readFileSync(abs(rel), 'utf8'); } catch { return null; } };
const listarWorkflows = () => {
  const d = abs('.github/workflows');
  if (!existsSync(d)) return [];
  return readdirSync(d).filter((f) => /\.ya?ml$/.test(f)).map((f) => `.github/workflows/${f}`).sort();
};

// `design-docs/cowork-inbox/` é CAIXA DE ENTRADA do Cowork (pedido que o Code ainda NÃO adotou),
// não artefato ativo do repo. Um `Test.php` que chega no inbox está fora de lane POR DEFINIÇÃO —
// pôr em lane é trabalho de quem ADOTA o pedido, e cobrar isso do inbox reprova o mensageiro.
// Medido 2026-08-24: o inbox era 93 arquivos 100% `.md` e passava; ao descer 87 novos do vivo veio
// `FiscalOndasF1Test.php`, legítimo COMO PEDIDO e sem lane alguma que o rode.
const foraDoInbox = (p) => !String(p).split(String.fromCharCode(92)).join('/').includes('design-docs/cowork-inbox/');

function indiceDeTestes() {
  // git ls-files: o universo é o VERSIONADO (não uma travessia que pega worktree alheio).
  const raw = execFileSync('git', ['ls-files', '--', '*Test.php'], { cwd: ROOT, encoding: 'utf8' });
  const porNome = new Map();
  for (const p of raw.split('\n').map((s) => s.trim()).filter(Boolean)) {
    const nome = p.split('/').pop().replace(/\.php$/, '');
    if (!porNome.has(nome)) porNome.set(nome, []);
    porNome.get(nome).push(p);
  }
  return porNome;
}

function manifestoG7() {
  const c = lerArquivo('scripts/casos-test-results.json');
  if (!c) return {};
  try { return JSON.parse(c).ucs || {}; } catch { return {}; }
}

function carregaBaseline(p) {
  if (!p) return null;
  if (!existsSync(abs(p))) { console.error(`uc-lane-coverage: --baseline ${p} não existe.`); process.exit(2); }
  try { return new Set(JSON.parse(readFileSync(abs(p), 'utf8')).grandfathered || []); }
  catch (e) { console.error(`uc-lane-coverage: --baseline ${p} não parseia (${e.message}).`); process.exit(2); }
}

function main() {
  // Run-set e quarentena vêm do DONO (test-lane-coverage.mjs), nunca recalculados aqui.
  const { alvos, lanesLidas } = coletarAlvos();
  const quarentena = new Set(emQuarentena());

  if (LANES_ONLY) {
    console.log('\n  run-set derivado pelo DONO — scripts/governance/test-lane-coverage.mjs\n');
    console.log(`   lanes com pest: ${lanesLidas} · alvos: ${alvos.length} · em quarentena declarada: ${quarentena.size}\n`);
    console.log('   Detalhe por lane/módulo: node scripts/governance/test-lane-coverage.mjs\n');
    return 0;
  }

  const testesPorNome = indiceDeTestes();
  const manifesto = manifestoG7();
  const arquivosCasos = execFileSync('git', ['ls-files', '--', '*.casos.md'], { cwd: ROOT, encoding: 'utf8' })
    .split('\n').map((s) => s.trim()).filter(Boolean).filter(foraDoInbox).sort();

  const linhas = [];
  for (const f of arquivosCasos) {
    for (const c of citacoesEm(readFileSync(abs(f), 'utf8'))) {
      if (!c.nomes.length) {
        linhas.push({ ...c, arquivo: posix(f), estado: c.teste ? 'sem-nome-de-teste' : 'sem-teste', paths: [] });
        continue;
      }
      const paths = c.nomes.flatMap((n) => testesPorNome.get(n) || []);
      const ausentes = c.nomes.filter((n) => !testesPorNome.has(n));
      // TRÊS estados, não dois — e a semântica é do dono: quarentena declarada NÃO é órfã.
      // "Alguém decidiu conscientemente que não roda, e a lane imprime a lista. Órfão é o
      // que ninguém decidiu — some sem ninguém saber." Misturar os dois apaga a diferença
      // que torna o número acionável (medido: 19 dos 56 que eu reportava eram quarentena).
      // ORDEM IMPORTA, e ela custou uma rodada. A quarentena é checada ANTES de `na-lane`
      // porque o run-set do dono guarda DIRETÓRIOS (`find tests/Feature/Estoque`) — um
      // arquivo em quarentena mora dentro de um diretório coberto, então `estaCoberto` diz
      // sim e ele NÃO roda. Checar cobertura primeiro carimbaria `na-lane` num teste parado.
      let estado;
      if (ausentes.length) estado = 'arquivo-ausente';
      else if (paths.length && paths.every((p) => quarentena.has(p))) estado = 'quarentena';
      else if (paths.some((p) => estaCoberto(p, alvos) && !quarentena.has(p))) estado = 'na-lane';
      else estado = 'orfao';
      linhas.push({ ...c, arquivo: posix(f), estado, paths, ausentes, verdict: manifesto[c.uc]?.verdict || null });
    }
  }

  const orfaos = linhas.filter((l) => l.estado === 'orfao' || l.estado === 'arquivo-ausente');
  const chave = (l) => `${l.arquivo}::${l.uc}`;

  if (WRITE_BASELINE) {
    writeFileSync(abs(WRITE_BASELINE), `${JSON.stringify({
      _meta: {
        schema: 'uc-lane-baseline/v1',
        purpose: 'Grandfathera UC cujo teste citado já era órfão de lane. `--check --baseline` morde só citação NOVA (no-new-lie).',
        contrato: 'grandfathered[] = "<casos.md>::<UC>". CRESCER = grandfatherar órfão novo, o oposto do propósito. DIMINUIR (teste entrou na lane) é livre.',
        salto_legitimo: 'Há UM caso em que crescer NÃO é violação: quando o PARSER passa a enxergar formato que antes era invisível. A dívida não é nova — sempre existiu e só ficou visível. Em 2026-08-26 o corpo foi de 37 pra 117 assim, e a decomposição foi MEDIDA (não estimada): dos 37 commitados, 12 eram entradas STALE de `design-docs/cowork-inbox/` que o filtro `foraDoInbox` (2026-08-24) já excluía — regenerar com o parser ANTIGO naquele dia dava 25, não 37. Os 92 que entraram vêm de ler o FORMATO 2 (cabeçalho `## UC-XX` + campo dedicado): 57 arquivos com 386 cabeçalhos que não produziam par nenhum, mais 27 UCs de cabeçalho dentro de arquivos que TÊM tabela e não os listam. Quem crescer por esse motivo DIZ isso no PR e mostra o antes→depois medido; quem crescer sem essa justificativa está grandfatherando dívida nova.',
        crescimento_2026_09_05: 'SEGUNDO crescimento declarado, e ele NÃO é o `salto_legitimo` acima — o parser não mudou, o CORPUS mudou. +12 UC do Purchase (PURIDX 01/04/05/06 · PURSHW 02/03/04/06 · PURCRE 05/07 · PUREDT 06/07), por decisão [W] explícita em 2026-09-05. O que é NOVO é a CITAÇÃO (os 4 `.casos.md` do Purchase nasceram em 2026-09-04 no #6787 e 2026-09-05), não a dívida: os arquivos de teste que eles citam já eram órfãos de lane antes disso — medido no cabeçalho de `purchase-pest.yml` em 09-04. Penalizar quem ESCREVE o casos.md honesto criaria o incentivo de não escrever. Os 12 restantes apontam pros 4 arquivos com vermelho REAL em main, e a causa está verificada arquivo a arquivo: 5 casos cobram artefato AUSENTE (`memory/requisitos/Purchase/{RUNBOOK-create,index-visual-comparison,show-visual-comparison,create-visual-comparison}.md` e `memory/requisitos/Inventory/{RUNBOOK-purchase-edit,purchase-edit-visual-comparison}.md` — a pasta Purchase só tem BRIEFING.md) e 1 é falso-positivo de presence-gate (`ShowPageTest` exige que `Show.tsx` não contenha "Barcode"; o que casa é o COMENTÁRIO da linha 9 que documenta o próprio bug-fix — LC-11). ANTES→DEPOIS medido no mesmo PR-par: os outros 6 UC do Purchase NÃO entraram aqui, saíram por LANE (os 2 `Wave2*BaselineTest` entraram na allowlist do `purchase-pest.yml` com 11 passed/14 assertions no CT 100 e mordida provada por mutação) — `na lane` 550→556, órfão 87→81, ativos 18→12. SAÍDA destas 12: contrato de COMPORTAMENTO numa lane que já roda, como fizeram UC-PURIDX-02/03 e UC-PURSHW-01/05 — não é ligar teste vermelho nem criar os 6 artefatos só pra fazer presence-gate passar. ⚠️ O CORPO ENCOLHEU no mesmo run (117 → 85), e a decomposição foi MEDIDA item a item, não estimada: +12 (Purchase, acima) e −44, sendo −23 que DEIXARAM de ser órfãos (0 dos 23 segue órfão ao rodar sem baseline: Backup 19 e Jana/Chat 4, cujas lanes foram ligadas por outra sessão em 2026-09-05) e −21 STALE cujo `.casos.md` não existe mais (as telas Board/Inbox/Triage da Forja foram revogadas em 2026-09-03, `refactor(forja): Onda 11`; `UC-BOARD-01` não aparece em casos.md nenhum hoje). Quem auditar este número: rode `--json` sem `--baseline` e cruze — foi assim que a decomposição saiu.',
        regenerar: `node scripts/qa/uc-lane-coverage.mjs --write-baseline ${WRITE_BASELINE}`,
        nao_e: 'NÃO é permissão pra deixar teste fora da lane — é dívida datada. Por que a allowlist existe (custo de CI? teste instável escondido?) é decisão [W].',
      },
      grandfathered: orfaos.map(chave).sort(),
    }, null, 2)}\n`);
    console.log(`uc-lane-coverage: baseline escrito em ${WRITE_BASELINE} (${orfaos.length} UC grandfatherado).`);
    return 0;
  }

  const base = carregaBaseline(BASELINE_PATH);
  const ativos = base ? orfaos.filter((l) => !base.has(chave(l))) : orfaos;

  const resumo = {
    casos_md: arquivosCasos.length,
    citacoes: linhas.length,
    na_lane: linhas.filter((l) => l.estado === 'na-lane').length,
    orfao: linhas.filter((l) => l.estado === 'orfao').length,
    arquivo_ausente: linhas.filter((l) => l.estado === 'arquivo-ausente').length,
    sem_teste: linhas.filter((l) => l.estado === 'sem-teste' || l.estado === 'sem-nome-de-teste').length,
    quarentena: linhas.filter((l) => l.estado === 'quarentena').length,
    lanes_lidas: lanesLidas,
    alvos: alvos.length,
    ativos: ativos.length,
  };

  if (JSON_OUT) {
    console.log(JSON.stringify({ _meta: { schema: 'uc-lane-coverage/v1' }, resumo, violacoes: ativos }, null, 2));
    return CHECK && ativos.length ? 1 : 0;
  }

  console.log('\n  uc-lane-coverage — o teste citado pelo casos.md roda em alguma lane?\n');
  console.log(`  casos.md: ${resumo.casos_md} · citações UC→teste: ${resumo.citacoes}`);
  console.log(`  na lane: ${resumo.na_lane} · ÓRFÃO: ${resumo.orfao} · em QUARENTENA declarada: ${resumo.quarentena} · arquivo ausente: ${resumo.arquivo_ausente} · sem teste citado: ${resumo.sem_teste}`);
  console.log(`  run-set: ${resumo.alvos} alvo(s) de ${resumo.lanes_lidas} lane(s) — derivado por scripts/governance/test-lane-coverage.mjs\n`);

  if (resumo.quarentena) {
    console.log(`  ℹ️  ${resumo.quarentena} citação(ões) apontam pra teste em QUARENTENA declarada — não são órfãs.`);
    console.log('      Alguém decidiu que não rodam e a lane imprime a lista; órfão é o que ninguém decidiu.\n');
  }

  if (!ativos.length) {
    console.log(`  ✅ nenhuma citação ativa aponta pra teste órfão de lane.${base ? ' (legado no baseline)' : ''}\n`);
    return 0;
  }

  let ultimo = null;
  for (const l of ativos) {
    if (l.arquivo !== ultimo) { console.log(`  ${l.arquivo.replace(/^resources\/js\/Pages\//, '')}`); ultimo = l.arquivo; }
    const marca = l.estado === 'arquivo-ausente' ? '∅ arquivo não existe' : '⛔ existe e NENHUMA lane roda';
    // O `status` do FORMATO 2 é a linha `Status:` INTEIRA, e no corpus ela chega a um
    // parágrafo (o `UC-BOARD-01` do Forja tem ~380 chars). Sem encurtar, uma citação
    // empurra o relatório pra fora da tela e esconde as outras — o relatório existe pra
    // ser lido. O `--json` continua servindo o valor íntegro pra quem consome por máquina.
    const statusCurto = (l.status || '—').replace(/^Status\s*[:：]\s*/i, '').replace(/\s+/g, ' ');
    console.log(`     ${l.uc.padEnd(16)} ${marca}   teste: ${l.teste || '—'}   status declarado: ${statusCurto.length > 72 ? `${statusCurto.slice(0, 69)}…` : statusCurto}`);
    for (const p of l.paths) console.log(`        ${p}`);
  }
  console.log('\n  Teste fora de toda lane é "verde impossível": existe, pode estar vermelho');
  console.log('  há meses, e nenhum PR o acorda. O UC que o cita parece coberto e não está.');
  console.log('  Conserto NÃO é mexer na allowlist por conta própria — por que ela existe');
  console.log('  (custo de CI? teste instável escondido?) é decisão [W].\n');
  return CHECK ? 1 : 0;
}

if (import.meta.url === pathToFileURL(process.argv[1]).href) process.exit(main());
