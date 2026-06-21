export const meta = {
  name: 'sdd-fase-2a',
  description: 'Reestruturação SDD — Fase 2a: backfill mecânico de anchors (SA-A4), protection-drift (G4), snapshot histórico (G7), linha SDD no brief (G8)',
  whenToUse: 'Disparar após Fase 1 mergeada (PRs #2593, #2601-#2610). Fase 2b (triage/quarentena/burn-down) espera o summary.json do run CT 100.',
  phases: [
    { title: 'Executar', detail: '4 frentes paralelas em worktrees isolados' },
    { title: 'Verificar', detail: 'auditor adversarial' },
  ],
}

const BASE = `Você é UMA frente da FASE 2a da reestruturação SDD do oimpresso (ERP Laravel 13.6 multi-tenant, business_id Tier 0 IRREVOGÁVEL).
CONTEXTO em main: ADRs 0273/0274/0275 aceitas · anchor-lint.mjs + anchor-drift.yml advisory · codemod ghost-fix aplicado (4 renames; **/adr/** é hard-skip — ADR histórico é fato) · sdd-scorecard.mjs + baseline + workflow advisory · gate-selftest + fixtures · protocolo refutador (memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md + governance/sdd-verification-ledger.json) · foundation-ratchet · catraca anti-ghost. ATENÇÃO: um PR de coerência (branch sdd/f1-coerencia) pode estar em voo tocando scripts/governance/{gate-selftest,knowledge-drift,sdd-scorecard}.mjs — NÃO toque esses 3 arquivos.
LEIA PRIMEIRO: D:/oimpresso.com/memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md + arquivos da sua frente.

ISOLAMENTO: cd D:/oimpresso.com && git fetch origin main && git worktree add ../wt-f2a-{KEY} -b sdd/f2a-{KEY} origin/main. Trabalhe SÓ em D:/wt-f2a-{KEY}, SÓ na sua ÁREA EXCLUSIVA (exceção: gates-registry.json entry própria se criar workflow).

REGRAS Tier 0: PR ≤300 linhas 1-intent (dividir em lotes se exceder) · conventional commit + "Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>" · ADRs append-only · ZERO credencial/PII (repo PÚBLICO) · gates advisory · re-derive números do código real · migrations idempotentes com down() · queries com business_id global scope (tabelas de GOVERNANÇA global são exceção documentada — comente no código por quê).

FINALIZAÇÃO: commit, push, gh pr create --draft, git worktree remove --force. NÃO mergeie. Devolva JSON: frente, branch, pr_url, files, dod_evidence (output real), blockers, needs_wagner.`

const PR_SCHEMA = {
  type: 'object',
  properties: {
    frente: { type: 'string' },
    branch: { type: 'string' },
    pr_url: { type: 'string' },
    files: { type: 'array', items: { type: 'string' } },
    dod_evidence: { type: 'string' },
    blockers: { type: 'array', items: { type: 'string' } },
    needs_wagner: { type: 'string' },
  },
  required: ['frente', 'branch', 'pr_url', 'files', 'dod_evidence', 'blockers', 'needs_wagner'],
}

const FRENTES = [
  {
    key: 'sa-a4',
    prompt: `FRENTE SA-A4 — backfill MECÂNICO de anchors: 50 placeholders + 15 anchored_dead + 5 órfãos → estados válidos da gramática ADR 0273. NUNCA inventar path.
ÁREA EXCLUSIVA: scripts/governance/anchor-backfill.mjs (novo) + memory/requisitos/**/SPEC.md (SÓ linhas "**Implementado em:**") + governance/sdd-verification-ledger.json (entry do lote, conforme protocolo).
LEIA: memory/decisions/0273-*.md (gramática) + scripts/governance/anchor-lint.mjs (classificador e --json) + PROTOCOLO-REFUTADOR-BACKFILL.md (lote >10 arquivos em memory/requisitos exige entry no ledger — este lote é MECÂNICO/existsSync, registre a natureza da verificação).
SCRIPT (idempotente, --dry-run default, --write opt-in): para cada US: (a) placeholder com path embutido → path existe no disco? anchored_ok com sha7 (git log -1 --format=%h -- <path>) : _pendente_; (b) placeholder sem path → match ÚNICO via frontmatter dos charters do módulo (page:/component: — backfilled no #2599)? anchored : _pendente_; (c) anchored_dead → o path morto tem sucessor INEQUÍVOCO (git log --follow do path antigo, ou rename de pasta do codemod)? corrige com sha7 novo : _pendente_ com nota; (d) 5 campos órfãos fora de bloco US → normalizar pro bloco da US certa se inequívoco, senão reportar. Ambíguo = NUNCA escreve, vai pra lista no PR.
APLICAÇÃO: 2-3 PRs por grupos de módulos, cada ≤300 linhas (branches sdd/f2a-sa-a4-a/b/c).
DoD COLADO: anchor-lint ANTES (placeholder 50 · dead 15 · coverage ~1.8%) vs DEPOIS (placeholder 0 · dead 0 · coverage real atingida) · re-run --write = 0 diffs · anchor-lint --json 2 runs = diff vazio · entry no ledger · lista dos ambíguos que ficaram _pendente_.`,
  },
  {
    key: 'gt-g4',
    prompt: `FRENTE GT-G4 — catraca da catraca: protection-drift (required checks só ENTRAM) + watchdog de staleness das fontes do scorecard.
ÁREA EXCLUSIVA: scripts/governance/protection-drift.mjs (novo) + .github/workflows/sdd-scorecard.yml (ADICIONAR job — não mexa no job existente) + governance/required-checks-baseline.json (se precisar ajustar formato, preservando conteúdo) + entry gates-registry se criar workflow separado.
LEIA: governance/required-checks-baseline.json (existe em main — lista congelada) + memory/decisions/0275-*.md + .github/workflows/sdd-scorecard.yml.
CONTEÚDO: (1) protection-drift.mjs: via gh api repos/{owner}/{repo}/branches/main/protection compara contexts atuais vs baseline — check NOVO no required = ok E atualiza baseline (só cresce); check REMOVIDO = vermelho com nome do gate sumido (demoção exige PR+ADR); enforce_admins false = vermelho. (2) watchdog staleness: fontes do governance/sdd-scorecard.json com _meta/generated_at mais velho que o limiar da cadência declarada → métrica vira "fonte parada" (vermelho ≤48h). Job roda no cron diário do workflow existente. ADVISORY nesta fase. DoD: simulação local dos 3 cenários (remoção de context, enforce_admins off, fonte stale) colada — cada uma detectada; cenário normal verde.`,
  },
  {
    key: 'gt-g7',
    prompt: `FRENTE GT-G7 — histórico do scorecard: snapshot diário em DB + check no jana:health-check.
ÁREA EXCLUSIVA: app/Console/Commands/SddScorecardSnapshotCommand.php (novo) + database/migrations/*_create_mcp_sdd_scorecard_history_table.php (nova) + app/Console/Kernel.php (SÓ a linha do schedule 06:55 BRT) + o Command do health-check (adicionar check verificacao_sdd) + tests/Feature/Console correspondentes.
LEIA ANTES: o padrão de ModuleGradeSnapshotCommand + migration de mcp_module_grades_history (precedente exato: tabela de governança GLOBAL sem business_id — replique o comentário justificando a exceção Tier 0) + .claude/rules/migrations.md + como checks são adicionados no health-check (jana:health-check, 5 checks SQL existentes).
CONTEÚDO: comando lê governance/sdd-scorecard.json e persiste 1 row/dia (10 métricas + composta + generated_at; idempotente por data — re-run no mesmo dia atualiza, não duplica); schedule 06:55 BRT; check verificacao_sdd no health-check: vermelho se último snapshot >48h OU alguma métrica armada regrediu vs snapshot anterior (ALERT em storage/logs). DoD: Pest local passando (migration up/down + comando idempotente + check) — colar output do run.`,
  },
  {
    key: 'gt-g8',
    prompt: `FRENTE GT-G8 — leitura sem esforço: linha SDD no Daily Brief (brief-fetch ~3k tokens, ADR 0091).
ÁREA EXCLUSIVA: o builder/generator do Daily Brief em Modules/Jana (descubra: grep -r "Daily Brief\\|brief" Modules/Jana --include=*.php -l e leia o gerador v2 ADR 0226) + config flag + Pest do formatter. NÃO toque Kernel.php (frente gt-g7 usa) nem Console/Commands de snapshot.
PRE-FLIGHT: memory/requisitos/Jana/BRIEFING.md + ADR 0226 (brief v2 1M-aware ≤8k tokens).
CONTEÚDO: seção/linha determinística lida de governance/sdd-scorecard.json: "SDD: <composta> (Δ7d ±N) · 🔴 <K> métricas: <nomes>" — REGRA DE SILÊNCIO: só aparece quando houve mudança vs último brief OU existe métrica vermelha/fonte parada (silêncio = saudável); ≤100 tokens; flag de config como kill-switch (default ON); se o arquivo JSON não existir, omite sem erro. DoD: Pest do formatter (com mudança, sem mudança, com vermelho, sem arquivo) — colar output verde.`,
  },
]

phase('Executar')
log(`Fase 2a: ${FRENTES.length} frentes paralelas (backfill mecânico + 3 peças de garantia)`)

const results = await parallel(
  FRENTES.map(f => () =>
    agent(BASE.replaceAll('{KEY}', f.key) + '\n\n' + f.prompt, {
      label: `frente:${f.key}`,
      phase: 'Executar',
      schema: PR_SCHEMA,
    })
  )
)

const ok = results.filter(Boolean)
log(`${ok.length}/${FRENTES.length} frentes entregaram`)

phase('Verificar')
const audit = await agent(`Auditor da Fase 2a SDD oimpresso. Resultados (JSON): ${JSON.stringify(ok)}
VERIFIQUE via gh pr view/diff em D:/oimpresso.com (read-only):
1. Partição: PRs tocando mesmo arquivo fora de gates-registry.json? (atenção: sa-a4 SÓ linhas "Implementado em" de SPECs; gt-g7 vs gt-g8 não podem colidir em Modules/Jana/Kernel)
2. ≤300 linhas reais por PR?
3. dod_evidence é output real (anchor-lint antes/depois, Pest verde, simulações)? Fracos?
4. Tier 0: migration sem business_id JUSTIFICADA em comentário? ADRs intactos? Backfill inventou algum path (amostra 10 anchors novos: path existe no disco em main+branch)?
5. Ledger: lote sa-a4 tem entry no sdd-verification-ledger.json?
Devolva JSON: { aprovados, reprovados:[{key,motivo}], colisoes, fila_wagner:[{key,pr_url,decisao_pendente}] }`,
  { label: 'auditor:fase-2a', phase: 'Verificar' })

return { prs: ok, auditoria: audit, falhas: FRENTES.length - ok.length }
