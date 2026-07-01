export const meta = {
  name: 'sdd-avaliador-processo',
  description: 'Avaliador ADVERSARIAL do programa SDD: 1 skeptic por stream verifica o estado REAL em git+gh+MCP (não o plano), pontua sucesso 0-100, lista modos de falha. Síntese = scorecard + ondas que faltam + riscos sistêmicos. Re-rodável; gate de processo antes de promover required / ao fechar onda.',
  whenToUse: 'Antes de promover qualquer gate a required (ADR 0275), ao fechar cada onda (Sem 0/1-2/2-4/4-6), ou em checkpoint quinzenal de honestidade do processo. Read-only.',
  phases: [
    { title: 'Avaliar', detail: '7 streams verificados contra git/gh, não contra o plano' },
    { title: 'Síntese', detail: 'scorecard + ondas restantes + riscos sistêmicos' },
  ],
}

const REPO = 'wagnerra23/oimpresso.com'

// CONTEXTO genérico: o avaliador DESCOBRE o estado atual (não confia em PRs hard-coded).
const CONTEXTO = `PROGRAMA: reestruturação SDD do oimpresso. Plano-mãe: memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md (ondas Semana 0/1-2/2-4/4-6). ADRs fundação: 0273 (anchor), 0274 (slug/alias), 0275 (scorecard 10 métricas + calendário promoções), 0276 (decisão-pelo-fluxo). US guarda-chuva: US-GOV-016 (Semana 0), US-GOV-017 (Fase 1+2), US-GOV-018 (P0 harness Fase 2b).
DESCUBRA o estado ATUAL (não confie em números de documento — eles envelhecem):
- gh pr list -R ${REPO} --state all --search "<termo>" + git log origin/main --grep + git show origin/main:<arquivo> pra ver se o artefato existe e FAZ o que diz.
- gh api repos/${REPO}/branches/main/protection pra ver quais checks são required (advisory ≠ required).
- Onde existir: rode o script local (anchor-lint.mjs, sdd-scorecard.mjs, foundation-ratchet.mjs, gate-selftest.mjs) pra medir LIVE.
- Nightly full-suite: artefatos em /opt/oimpresso-fullsuite/runs/* no CT 100 (SSH key-based root@100.99.207.66) — o número é NÃO-determinístico (medir o FLOOR = interseção de ≥2 runs, não 1 run).`

const STREAMS = [
  { key: 'sa-anchors', nome: 'SA — Anchors spec↔código', passos: 'A1 ADR 0273 formato+sentinela · A2/A3 anchor-lint.mjs + anchor-drift · A4 backfill mecânico placeholders · A5 batch IA + refutador G5 · A6 fila Wagner · A10 anchor-gate required.' },
  { key: 'fv-fullsuite', nome: 'FV — Full-suite / testes', passos: 'F1 JUnit · F2 composite pest-mysql · F3 nightly MySQL CT 100 (não-determinístico — medir floor) · Q1 foundation-ratchet · Q2 triage · Q3 quarentena · B1-B4 burn-down · C1 fix harness MySQL · US-GOV-018 (harness re-diagnosticado).' },
  { key: 'kl-knowledge', nome: 'KL — Knowledge / ghost / decay', passos: 'anti-ghost por módulo · codemod renames · ADR 0274 slug/alias · E1 identidade órfãs · E2 renames · E2b re-seed Meilisearch · E3 BRIEFINGs · trilha C decay C1-C5 · trilha D RAGAS D1-D4.' },
  { key: 'gt-governance', nome: 'GT — Governance scorecard', passos: 'G1 ADR 0275 · G2 agregador · G3 meta-catraca · G4 protection-drift+watchdog · G5 refutador+ledger · G6 gate-selftest · G7 snapshot history · G8 linha SDD no brief.' },
  { key: 'charters-fluxo', nome: 'Charters + fluxo-novo', passos: 'backfill us:/component: nos charters · template/schema fluxo-novo (SPEC nasce com anchor) · grace-period memory-schemas · skill memory-schema-preflight.' },
  { key: 'fase2b-harness', nome: 'Fase 2b — P0 harness (US-GOV-018)', passos: 'Frente A harness/imagem (mysql-client + teardown FK-off + PSR-4 migrations puladas, ~850) · Frente B config_json json→longtext (212) · Frente C testes era-sqlite isolamento · dívida residual ~490 (assertions+app-bugs).' },
  { key: 'promocoes-required', nome: 'Semanas 4-6 — promoções a required', passos: 'R1 full-suite não-quarentenado · C2 coverage · T1 mapa teste↔arquivo · T2 TDAD-lite · SA-A10 anchor-gate · GT-G3 required. Máx 1 promoção/semana.' },
]

const SCHEMA = {
  type: 'object',
  properties: {
    stream: { type: 'string' },
    etapas: { type: 'array', items: { type: 'object', properties: {
      etapa: { type: 'string' },
      status: { type: 'string', enum: ['FEITO-VERIFICADO', 'FEITO-NÃO-VERIFICADO/ILUSÓRIO', 'PARCIAL', 'BLOQUEADO', 'NÃO-INICIADO', 'REVERTIDO'] },
      score: { type: 'number' },
      evidencia: { type: 'string' },
      modos_de_falha: { type: 'array', items: { type: 'string' } },
    }, required: ['etapa', 'status', 'score', 'evidencia', 'modos_de_falha'] } },
    score_stream: { type: 'number' },
    o_que_falta: { type: 'string' },
    risco_sistemico_top: { type: 'string' },
  },
  required: ['stream', 'etapas', 'score_stream', 'o_que_falta', 'risco_sistemico_top'],
}

const prompt = (s) => `Você é um AVALIADOR ADVERSARIAL do stream "${s.nome}" do programa SDD do oimpresso. Working dir D:/oimpresso.com. READ-ONLY: NÃO edita/commita. VERIFIQUE o estado REAL — não confie no plano nem no que dizem que foi feito.

${CONTEXTO}

SEU STREAM — passos: ${s.passos}

MÉTODO (verificar, não opinar — análise sozinha já errou 3× onde reprodução acertou):
0. GUARD DE CHECKOUT (anti falso-positivo 2026-07-01, errata da avaliação 67): antes de QUALQUER claim "rodei live / auditor dá N", rode \`git fetch origin main --quiet && git rev-parse HEAD origin/main\` no cwd onde vai medir. Se HEAD != origin/main, o cwd está STALE: crie worktree limpa (\`git worktree add <scratchpad>/aval-snap origin/main\`) e meça LÁ, ou leia via git show origin/main:<path>. Claim de medição sem essa prova = achado inválido (precedente: "12 tier-A vivos" medido num checkout 114 commits atrás fabricou risco sistêmico falso).
1. Confirme cada etapa pelo ARTEFATO em origin/main (gh/git/git show); não marque FEITO sem ver. Rode o script local pra medir LIVE onde existir.
2. CETICISMO "a suite mente": procure gate que não morde (advisory perene? fixture vazia?), catraca cujo baseline nunca foi armado de verdade (nº-de-ADR ≠ medição), métrica de FORMA não de CORREÇÃO, "feito" que depende de algo que nunca rodou (CT 100/secret/máquina externa), número não-determinístico vendido como estável.
3. score 0-100/etapa: 90-100 FEITO-VERIFICADO (morde/funciona, provado); 60-85 PARCIAL/advisory-ok; 30-55 ILUSÓRIO ou BLOQUEADO; 0-25 NÃO-INICIADO/REVERTIDO. Na dúvida, pontue baixo + diga por quê.
4. modos_de_falha específicos (arquivo:linha, dependência, premissa).

Retorne JSON no schema. score_stream = média ponderada honesta. o_que_falta = onda/passo que resta. risco_sistemico_top = maior risco do stream.`

phase('Avaliar')
log(`${STREAMS.length} avaliadores adversariais (verificam git/gh, não o plano)`)
const avals = await parallel(STREAMS.map(s => () => agent(prompt(s), { label: `aval:${s.key}`, phase: 'Avaliar', schema: SCHEMA, model: 'opus' })))
const ok = avals.filter(Boolean)

phase('Síntese')
const sintese = await agent(`Sintetize a avaliação ADVERSARIAL do programa SDD num SCORECARD executável. ${ok.length} streams verificados (JSON): ${JSON.stringify(ok)}
Markdown enxuto (sem frontmatter): 1) Scorecard por stream (tabela score+status+maior-risco) + score_composto ponderado (peso maior pros streams que destravam o resto: FV/Fase2b ×1.8, SA/GT ×1.3). 2) Etapas que importam com nota+modo-de-falha (FEITO-VERIFICADO vs ILUSÓRIO vs FALTA). 3) O QUE FALTA DE ONDAS (mapa Sem 0/1-2/2-4/4-6 → rodou vs resta + caminho crítico). 4) TOP 5 RISCOS SISTÊMICOS. 5) VEREDITO (1 parágrafo: no caminho? nota honesta do processo + maior alavanca). Direto ao Wagner.`, { label: 'sintese:avaliador', phase: 'Síntese' })

return { streams: ok, sintese, score_medio: Math.round(ok.reduce((a, r) => a + (r.score_stream || 0), 0) / (ok.length || 1)) }
