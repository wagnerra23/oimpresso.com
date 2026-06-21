// Orquestrador da FASE 2 da reestruturação SDD (US-GOV-017) — subset executável SEM CT 100.
// Rodado 2026-06-12 da máquina Felipe em 2 passadas: run 1 (wf_ae804008) caiu no limite de
// sessão deixando trabalho parcial nos worktrees D:/wt-f2-*; run 2 ("conclusão") retomou os
// worktrees existentes sem recriar nada. Este arquivo registra a passada 1 (canônica).
// Fase 2b (Q2/Q3/B1-B3/C3-C5) exige CT 100 — só máquina Wagner (share Tailscale ADIADO).
export const meta = {
  name: 'sdd-fase-2',
  description: 'Reestruturação SDD — Fase 2 (subset sem CT 100): SA-A4 backfill mecânico anchors, SA-A5 piloto batch IA + refutador G5, GT-G4 protection-drift, GT-G7/G8 snapshot+brief, FV-B4 fundação testes',
  whenToUse: 'Disparar após Fase 1 mergeada (PRs #2587-#2610). GO Wagner 2026-06-12 (US-GOV-017 fase 1+2). Q2/Q3/B1-B3/C3-C5 bloqueadas até acesso CT 100.',
  phases: [
    { title: 'Executar', detail: '5 frentes paralelas em worktrees isolados, 1+ PR draft cada' },
    { title: 'Refutar', detail: 'refutador G5 em sessão fresca no lote IA SA-A5' },
    { title: 'Verificar', detail: 'auditor adversarial: partição, evidência, Tier 0' },
  ],
}

const BASE = `Você é UMA frente da FASE 2 da reestruturação SDD do oimpresso (ERP Laravel 13.6 multi-tenant, business_id Tier 0 IRREVOGÁVEL). Data: 2026-06-12. Responda e commite em PT-BR.
CONTEXTO: Fase 1 mergeada em main (PRs #2587-#2610): anchor-lint.mjs + anchor-drift.yml (gramática ADR 0273), codemod 4 renames aplicado (#2603: Copiloto→Jana, PontoWr2→Ponto, MemCofre→SRS, DocVault→SRS), nightly full-suite CT 100 (FV-F3), meta-catraca scorecard (GT-G3, governance/sdd-scorecard-baseline.json), gate-selftest (GT-G6), protocolo refutador G5 (governance/sdd-verification-ledger.json + ledger-check.mjs), jana:recall-eval + golden set (KL-C2), peso_real flag-OFF (KL-C1).
ESTADO REAL (re-derive TUDO do worktree; números são só referência): anchor_coverage 2.8% (22/781 US estritos), ghost_count 27 (armed), 57 SPECs, full_suite_pass_rate not_yet_measured.
BLOQUEIO DESTA MÁQUINA: CT 100 INACESSÍVEL (tailnet sem peer) — NÃO tente SSH pra 100.99.207.66 nem ct100-mcp. Se sua frente precisar, registre em blockers.
LEIA PRIMEIRO: memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md (plano-mãe) + os arquivos da sua frente.

ISOLAMENTO OBRIGATÓRIO (frentes paralelas no mesmo repo):
1. cd D:/ompresso.com && git fetch origin main (se index.lock de worktree concorrente, aguarde 10s e re-tente)
2. git worktree add ../wt-f2-{KEY} -b sdd/f2-{KEY} origin/main
3. Trabalhe SOMENTE em D:/wt-f2-{KEY}, SOMENTE nos arquivos da sua ÁREA EXCLUSIVA. Exceção: scripts/governance/gates-registry.json se criar workflow novo (adicione SÓ sua entry).
4. NÃO rode composer/php artisan/pest/phpstan local (hook bloqueia; máquina não suporta) — verificação de código PHP é o CI do PR. Scripts node (.mjs) PODE e DEVE rodar local.
5. Windows: escreva arquivos SEMPRE via tool Write/Edit (nunca Set-Content — BOM quebra PHP). Valide encoding se em dúvida.

REGRAS (Tier 0 — violar = PR rejeitado): PR ≤300 linhas 1-intent (dividir em lotes se exceder), conventional commit PT-BR com "Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>", ADRs append-only (nunca editar existente), frontmatter valida contra scripts/memory-schemas/, ZERO credencial/PII (repo PÚBLICO), gates novos nascem ADVISORY, re-derive todo número do código real (nunca copie do plano).

FINALIZAÇÃO: commit, push -u origin sdd/f2-{KEY}, gh pr create --draft citando US-GOV-017 fase 2 + plano-mãe, depois git worktree remove ../wt-f2-{KEY} (worktree não terá vendor/ — se remoção falhar, deixe e registre). NÃO mergeie. Devolva JSON: frente, branch, pr_url (principal; demais em files), files, dod_evidence (OUTPUT REAL de execução, não narração), blockers, needs_wagner.`

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
    prompt: `FRENTE SA-A4 — backfill MECÂNICO (zero-IA, determinístico) dos placeholders do campo 'Implementado em' nos SPECs.
ÁREA EXCLUSIVA: memory/requisitos/**/SPEC.md — SOMENTE o campo 'Implementado em' de US que JÁ TEM o campo com valor placeholder. NÃO adicione campo em US sem o campo (isso é a frente SA-A5 — partição por construção). NÃO toque SPECs de pastas com proposta FUNDIR ou MATAR em memory/requisitos/_TRIAGEM-IDENTIDADE-2026-06.md (aguardam trilha E gated no Wagner).
LEIA: memory/decisions/0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md (gramática + sentinelas _pendente_/_parcial_) + scripts/governance/anchor-lint.mjs.
PROCESSO: (1) node scripts/governance/anchor-lint.mjs --json → colar contagens iniciais (re-derive: ~48-49 placeholders); (2) pra cada placeholder COM path embutido: se o path existe no disco → promover pra anchor estrito com provenance sha7 (git log -n1 --format=%h -- <path>) no formato exato da ADR 0273; se path não existe OU placeholder sem path → sentinela _pendente_; (3) re-rodar anchor-lint --json → colar anchor_coverage antes/depois; (4) idempotência: re-aplicar a lógica = 0 diffs (provar com output); (5) se diff total >300 linhas, dividir em PRs por grupo de módulos (branches sdd/f2-sa-a4-a, sdd/f2-sa-a4-b, ...).
DoD: anchor-lint --json antes/depois colados + prova de idempotência + cada PR ≤300 linhas.`,
  },
  {
    key: 'sa-a5',
    prompt: `FRENTE SA-A5 PILOTO — batch IA de anchors nos 5 PRIMEIROS módulos (universo: ~42 SPECs SEM o campo 'Implementado em'), com evidência dura. O plano-mãe manda publicar a TAXA DE AMBIGUIDADE após 5 módulos e recalibrar ANTES dos restantes — seu lote é SÓ o piloto de 5.
ÁREA EXCLUSIVA: memory/requisitos/<5 módulos escolhidos>/SPEC.md (adicionar campo 'Implementado em' nas US) + memory/requisitos/_ANCHOR-REVIEW-QUEUE.md (novo) + governance/sdd-verification-ledger.json (SÓ sua entry, append-only).
LEIA: ADR 0273 + memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md + plano-mãe §1 camada 2.
ESCOLHA dos 5 módulos: SPEC sem campo cuja pasta (a) NÃO está marcada FUNDIR/MATAR na _TRIAGEM-IDENTIDADE-2026-06.md, (b) tem código real (Modules/<X>/ ou resources/js/Pages/<X>/) e (c) tem charters com frontmatter page:/component: preenchido (melhor evidência). Liste os 5 escolhidos e o porquê no PR.
PROCESSO por US: cruzar charter frontmatter + árvore resources/js/Pages/ + git log --grep=<US-id> filtrado feat|fix com diff tocando o path candidato. REGRA DURA: anchor só entra com path EXISTENTE no disco + ≥1 evidência independente; US de tela/feature nunca construída → _pendente_ (estado de 1ª classe, NÃO inventar anchor); dúvida real → item em _ANCHOR-REVIEW-QUEUE.md (formato: módulo · US · candidatos · evidência conflitante · o que o humano decide).
PUBLIQUE no PR: taxa de ambiguidade (itens na fila / US processadas) — plano recalibra se >9%.
PII scan diff-only (CPF/CNPJ/nomes de cliente) — obrigatório 0 hits, colar prova.
LEDGER: adicione entry lote_id SA-A5-piloto-01 com gerador (seu modelo), refutação PENDENTE (campos do refutador ficam pro agente refutador da fase seguinte — siga o schema do _meta do arquivo).
DoD: anchor-lint --json dos 5 módulos antes/depois + taxa de ambiguidade + fila criada + entry no ledger + PR(s) draft ≤300 linhas (1 PR por 1-2 módulos se precisar).
needs_wagner: skim da fila A6 se houver itens.`,
  },
  {
    key: 'gt-g4',
    prompt: `FRENTE GT-G4 — protection-drift + watchdog de staleness (plano-mãe §2 GARANTIDA: fecha o único buraco real — demoção invisível de required check em 1 clique do admin).
ÁREA EXCLUSIVA: scripts/governance/protection-drift.mjs (novo) + governance/required-checks-baseline.json (novo) + .github/workflows/protection-drift.yml (novo, ADVISORY, cron diário + workflow_dispatch) + entry no gates-registry.json.
LEIA: memory/decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md + scripts/governance/sdd-scorecard.mjs (clone o idioma: node puro sem deps, tabela 🔴🟡🟢, --json determinístico) + plano-mãe §2.
PROCESSO: (1) capturar required checks REAIS: gh api repos/wagnerra23/oimpresso.com/branches/main/protection (required_status_checks.contexts + enforce_admins) — colar a lista (plano citou 16, re-derive); (2) governance/required-checks-baseline.json versionado com lista + data + sha de captura; (3) protection-drift.mjs: compara estado vivo (gh api) vs baseline — required que SUMIU = exit 1 (demoção só via PR editando baseline + ADR, diff visível); required NOVO = ok com aviso (entrar é permitido, sugere PR de baseline); (4) watchdog staleness: pra cada métrica status=measured do governance/sdd-scorecard-baseline.json, fonte parada >48h = vermelho — implemente no protection-drift.mjs ou justifique onde colocou; (5) workflow: cron diário 10:10 UTC + diff-aware em PR que toca governance/required-checks-baseline.json.
DoD: rodar protection-drift.mjs local com gh api real (colar output); simular demoção via fixture (colar exit 1); 2 runs --json = diff vazio; entry gates-registry; workflow advisory (continue-on-error).`,
  },
  {
    key: 'gt-g7g8',
    prompt: `FRENTE GT-G7+G8 — SERIAL: 2 PRs na MESMA frente, um DEPOIS do outro (Kernel.php é infra-lane serializada — você é o único dono dele nesta fase).
PRE-FLIGHT OBRIGATÓRIO: memory/requisitos/Governance/SPEC.md + ADR 0275 + ADR 0091 (daily brief) + migrations mcp_* existentes (imitar padrão — tabela de governança global SEM business_id segue precedente mcp_briefs; confirme lendo a migration de mcp_briefs) + descobrir onde vive o gerador do brief (grep brief:generate / mcp_briefs em Modules/ e app/) + descobrir a Page do dashboard de Governance (grep GovernanceV4 ou Governance em resources/js/Pages/).
PR-1 (G7 — branch sdd/f2-gt-g7): migration mcp_sdd_scorecard_history (1 row/dia: snapshot_date unique, payload JSON do scorecard, composta decimal, timestamps) + comando artisan governance:sdd-scorecard-snapshot (executa node scripts/governance/sdd-scorecard.mjs --json via Symfony Process e persiste; idempotente por dia — re-run do mesmo dia substitui) + schedule daily em app/Console/Kernel.php (imitar entries existentes, 07:00 America/Sao_Paulo) + card SDD no dashboard Governance (composta atual + Δ vs ontem + métricas vermelhas; prop via Inertia::defer) + Pest do comando (persiste + idempotência).
PR-2 (G8 — branch sdd/f2-gt-g8 criada a partir da branch do PR-1, PR marcado 'depends-on PR-1' no body): linha SDD no brief diário — aparece SÓ quando (a) composta mudou vs último snapshot OU (b) métrica armed regrediu/fonte vermelha. Formato 1 linha: 'SDD: composta NN (ΔN) · X/10 vivas · alerta: <métrica>'. Pest do trecho.
NÃO rode php/pest local (hook bloqueia) — escreva os Pest e o CI do PR roda.
DoD: 2 PRs draft encadeados, migration segue padrão mcp_*, schedule no Kernel, card no dashboard, Pest escritos, cada PR ≤300 linhas.`,
  },
  {
    key: 'fv-b4',
    prompt: `FRENTE FV-B4 — fundação de testes da raiz: trait WithSeededTenant + saneamento Business::first() cru em tests/ raiz + destravar loader-blockers (PR isolado ANTES dos lotes de burn-down, que estão bloqueados no CT 100).
ÁREA EXCLUSIVA: tests/** (raiz do repo — NÃO Modules/*/Tests) incluindo tests/Pest.php se preciso.
LEIA: plano-mãe §4 (B4) + tests/Pest.php + .github/actions/pest-mysql-setup/action.yml (seed canônico) + memory/decisions/0101-tests-business-id-1-nunca-cliente.md + memory/requisitos/Infra/RUNBOOK-ct100-fullsuite.md (cita 4 loader-blockers conhecidos: uses(TestCase) file-level em pasta já vinculada no tests/Pest.php).
PROCESSO: (1) re-derive: grep -rn "Business::first()" tests/ → colar contagem da SUA área; (2) criar trait WithSeededTenant (descubra onde traits de teste vivem; senão tests/Support/) que resolve o tenant canônico de teste (biz=1 do seed, ADR 0101) com helper explícito e mensagem clara se o seed faltar; (3) substituir Business::first() cru nos arquivos de tests/ raiz pelo helper — comportamento idêntico, sem mudar asserções; (4) loader-blockers: localizar via grep os arquivos em tests/Feature com uses() file-level conflitando com o binding de pasta do tests/Pest.php e consertar (re-derive a lista, são ~4); (5) dividir: PR-a trait + loader-fixes, PR-b+ substituições em lotes ≤300 (branches sdd/f2-fv-b4-a/b/...).
NÃO rode pest local (hook bloqueia) — o CI dos PRs é o gate.
DoD: greps antes/depois colados + lista dos loader-blockers consertados + PRs draft ≤300.`,
  },
]

phase('Executar')
log(`Fase 2 (subset sem CT 100): ${FRENTES.length} frentes paralelas — SA-A4, SA-A5 piloto, GT-G4, GT-G7+G8, FV-B4`)

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

phase('Refutar')
const a5 = ok.find(r => (r.frente || '').toLowerCase().includes('a5'))
let refutacao = null
if (a5 && a5.pr_url) {
  const REFUT_SCHEMA = {
    type: 'object',
    properties: {
      lote: { type: 'string' },
      itens_verificados: { type: 'number' },
      erros_confirmados: { type: 'number' },
      error_rate_pct: { type: 'number' },
      pii_hits: { type: 'number' },
      veredito: { type: 'string' },
      pr_comment_url: { type: 'string' },
    },
    required: ['lote', 'itens_verificados', 'erros_confirmados', 'error_rate_pct', 'pii_hits', 'veredito', 'pr_comment_url'],
  }
  refutacao = await agent(`Você é o agente REFUTADOR do protocolo G5 (SDD) em SESSÃO FRESCA — você NÃO tem contexto do gerador e seu papel é ADVERSARIAL: provar que os anchors do lote estão ERRADOS. Responda em PT-BR.
Lote: SA-A5-piloto-01. Resultado do gerador (JSON): ${JSON.stringify(a5)}
SETUP: cd D:/ompresso.com && git fetch origin. Use gh pr diff/view no PR acima (e nos PRs extras em files, se houver). NÃO rode php/pest local.
LEIA ANTES: memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md + memory/decisions/0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md + governance/sdd-verification-ledger.json (schema do _meta).
VERIFIQUE 100% dos anchors do diff: (a) path existe em origin/main? (b) o arquivo REALMENTE implementa aquela US — leia o código, não confie no nome; (c) a evidência citada confere (charter/commit)? (d) sentinelas _pendente_ corretas (a tela realmente não existe)? (e) PII scan no diff completo (CPF/CNPJ/nomes de cliente — repo público; obrigatório 0).
SAÍDA: (1) gh pr comment no PR com tabela item-a-item confirmado/REFUTADO + evidência; (2) entry de veredito no governance/sdd-verification-ledger.json commitada NA MESMA BRANCH do PR (append-only, schema do _meta: amostra_pct 100, sessao_fresca true, error_rate aceite <2%, veredito aprovado/reprovado); (3) devolva o JSON pedido.`,
    { label: 'refutador:sa-a5', phase: 'Refutar', schema: REFUT_SCHEMA })
} else {
  log('SA-A5 sem PR — refutação pulada')
}

phase('Verificar')
const audit = await agent(`Você é o auditor adversarial da FASE 2 da reestruturação SDD do oimpresso. Responda em PT-BR. Resultados das frentes (JSON): ${JSON.stringify(ok)}
Refutação G5 do SA-A5: ${JSON.stringify(refutacao)}
VERIFIQUE (gh pr view/diff em D:/ompresso.com — read-only; NÃO mergeie nada; CT 100 está inacessível, não tente SSH):
1. PARTIÇÃO: pares de PRs tocando o mesmo arquivo fora de gates-registry.json? (atenção SA-A4 × SA-A5: A4 só edita campo existente, A5 só adiciona campo ausente — qualquer SPEC.md tocado pelos DOIS é colisão)
2. TAMANHO ≤300 linhas por PR?
3. dod_evidence é output real de execução? Liste os fracos.
4. Workflows novos registrados no gates-registry.json? Gates nasceram ADVISORY? Nenhum required tocado?
5. Tier 0: ADR existente editada? Migration com business_id faltando onde devia OU global indevido? PII no diff (repo público)? Kernel.php tocado por mais de uma frente?
6. G5: entry do ledger existe pro lote SA-A5 + veredito do refutador commitado? error_rate <2%?
7. anchor-lint: rode node scripts/governance/anchor-lint.mjs --json num checkout de origin/main + nas branches sa-a4/sa-a5 e confirme anchor_coverage subiu sem anchored_dead novo.
Devolva JSON: { aprovados: [...], reprovados: [{key,motivo}], colisoes: [...], fila_wagner: [{key,pr_url,decisao_pendente}], metricas: {anchor_coverage_antes, anchor_coverage_depois} }`,
  { label: 'auditor:fase-2', phase: 'Verificar' })

return {
  prs: ok,
  refutacao,
  auditoria: audit,
  falhas: FRENTES.length - ok.length,
  bloqueado_ct100: ['FV-Q2 triage (precisa summary.json do 1º run)', 'FV-Q3 quarentena', 'B1 Financeiro', 'B2 NfeBrasil', 'B3 matrix SQLite→MySQL', 'KL-C3/C4/C5 (Meilisearch/decay/cron)'],
  gated_wagner: ['KL-E2/E2b (tabela _TRIAGEM-IDENTIDADE sem decisões)', 'RAGAS real (secret OPENAI_API_KEY)', 'skim golden set + fila A6'],
}