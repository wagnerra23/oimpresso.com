export const meta = {
  name: 'sdd-fase-1',
  description: 'Reestruturação SDD — Fase 1: anchor-lint, codemod --write (4 renames aprovados), nightly CT 100, meta-catraca, selftest, decay flag-OFF, recall-eval, RAGAS destravado',
  whenToUse: 'Disparar após Semana 0 mergeada (PRs #2586-#2599, ADRs 0273/0274/0275 aceitas). GO Wagner 2026-06-12.',
  phases: [
    { title: 'Executar', detail: '8 frentes paralelas em worktrees isolados, 1+ PR draft cada' },
    { title: 'Verificar', detail: 'auditor adversarial: partição, evidência, Tier 0' },
  ],
}

const BASE = `Você é UMA frente da FASE 1 da reestruturação SDD do oimpresso (ERP Laravel 13.6 multi-tenant, business_id Tier 0 IRREVOGÁVEL).
CONTEXTO: Semana 0 mergeada em main — ADR 0273 (formato anchor, ACEITA), ADR 0274 (slug/alias), ADR 0275 (scorecard + calendário promoções), catraca anti-ghost, foundation-ratchet, protocolo refutador (governance/sdd-verification-ledger.json), agregador sdd-scorecard.mjs, hook red-first WARN. Wagner aprovou os 4 renames: Copiloto→Jana, PontoWr2→Ponto, MemCofre→SRS, DocVault→SRS.
LEIA PRIMEIRO: D:/oimpresso.com/memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md + os arquivos da sua frente listados abaixo.

ISOLAMENTO OBRIGATÓRIO (frentes paralelas no mesmo repo):
1. cd D:/oimpresso.com && git fetch origin main
2. git worktree add ../wt-f1-{KEY} -b sdd/f1-{KEY} origin/main
3. Trabalhe SOMENTE em D:/wt-f1-{KEY}, SOMENTE nos arquivos da sua ÁREA EXCLUSIVA. Exceção: scripts/governance/gates-registry.json se criar workflow novo (adicione SÓ sua entry).

REGRAS (Tier 0 — violar = PR rejeitado): PR ≤300 linhas 1-intent (dividir em lotes se exceder), conventional commit com "Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>", ADRs append-only (nunca editar existente), frontmatter valida contra scripts/memory-schemas/, ZERO credencial/PII (repo PÚBLICO), gates novos nascem ADVISORY, re-derive todo número do código real (nunca copie do plano).

FINALIZAÇÃO: commit, push -u origin sdd/f1-{KEY}, gh pr create --draft citando plano + ADR, git worktree remove ../wt-f1-{KEY} --force. NÃO mergeie. Devolva JSON: frente, branch, pr_url (se múltiplos PRs, o principal; liste os demais em files), files, dod_evidence (OUTPUT REAL de execução, não narração), blockers, needs_wagner.`

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
    key: 'sa-lint',
    prompt: `FRENTE SA-A2+A3 — anchor-lint.mjs (parser da gramática ADR 0273) + workflow advisory.
ÁREA EXCLUSIVA: scripts/governance/anchor-lint.mjs (novo) + .github/workflows/anchor-drift.yml (novo, ADVISORY, diff-aware nos PRs que tocam memory/requisitos/**/SPEC.md + cron semanal full-tree) + entry no gates-registry.json.
LEIA: memory/decisions/0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md (a gramática canônica + regex estão LÁ — implemente EXATAMENTE aquilo, incluindo sentinela _pendente_ e _parcial_).
CONTEÚDO: clone o idioma de knowledge-drift.mjs (node puro, tabela 🔴🟡🟢, --json determinístico). Classifica cada US dos 57 SPECs: sem_campo | placeholder | pendente | parcial | anchored_ok (path existe no disco) | anchored_dead. Emite anchor_coverage = (anchored_ok+pendente+parcial)/US_total, por módulo e global. DoD: rodar nos 57 SPECs reais, colar contagens (re-derive: ~57 SPECs, ~84 campos, ~49 placeholder), provar --json estável (2 runs = diff vazio), workflow sempre-verde (advisory).`,
  },
  {
    key: 'kl-codemod-write',
    prompt: `FRENTE KL-A3 — aplicar codemod ghost-fix --write com os 4 renames APROVADOS por Wagner (Copiloto→Jana, PontoWr2→Ponto, MemCofre→SRS, DocVault→SRS). NÃO aplique os 3 ambíguos do map.
ÁREA EXCLUSIVA: memory/requisitos/** (substituições) + governance/knowledge-ghosts-baseline/ (baselines SÓ diminuem) + governance/ghost-rename-map.json (marcar applied/data se o schema do map suportar).
LEIA: scripts/governance/ghost-fix.mjs (mergeado #2593) + governance/ghost-rename-map.json + scripts/governance/knowledge-drift.mjs.
PROCESSO: (1) dry-run, colar relatório; (2) --write APENAS dos 4 aprovados; (3) se o diff total >300 linhas, dividir em lotes por nome (ex: PR-a Copiloto→Jana, PR-b demais) — branches sdd/f1-kl-codemod-a/b; (4) re-run --write = 0 diffs (prova de idempotência COLADA no PR); (5) atualizar baselines anti-ghost removendo os nomes resolvidos; (6) rodar knowledge-drift.mjs antes/depois e colar ghost_count caindo. CUIDADO: substituição de CONTEÚDO de texto, não renomeia pastas (isso é trilha E, gated na tabela do Wagner). Não toque memory/decisions/.`,
  },
  {
    key: 'fv-ct100-nightly',
    prompt: `FRENTE FV-F3-CT100 — infra da full-suite MySQL no CT 100 + cron nightly + PRIMEIRO RUN disparado agora.
ÁREA EXCLUSIVA no repo: scripts/tests/ct100-fullsuite.sh (novo) + memory/requisitos/Infra/RUNBOOK-ct100-fullsuite.md (novo). No CT 100: /opt/oimpresso-fullsuite/** (novo, não toque NADA existente).
ACESSO: ssh -o BatchMode=yes root@100.99.207.66 (Tailscale, chave já configurada nesta máquina). REGRAS CT 100 (ADR 0062): não tocar containers existentes, não tocar Hostinger, NUNCA apontar a suite pra DB de produção.
PROCESSO: (1) inspecionar /opt (existe oimpresso-app? mysql-workers tem creds em /opt/oimpresso-workers/?); (2) montar /opt/oimpresso-fullsuite: clone do repo público + .env.testing com DB de TESTE dedicada (criar db oimpresso_fullsuite_test no container mysql-workers OU container mysql novo na network docker-host_default — NUNCA prod); (3) rodar via docker run com imagem oimpresso/mcp:latest (PHP 8.4) montando o clone: composer install + php artisan migrate no DB de teste + vendor/bin/pest com --log-junit /opt/oimpresso-fullsuite/runs/<ts>/junit.xml, chunked por diretório se necessário, nohup em background com log; (4) instalar cron root 02:00 BRT chamando o script; (5) DISPARAR o primeiro run AGORA em nohup — NÃO espere terminar; cole o tail do log provando testes executando. (6) PR no repo com o script (o do CT 100 é cópia do versionado) + RUNBOOK (como re-rodar, onde ficam artefatos, como coletar summary). DoD: cron instalado (crontab -l colado) + run em andamento (tail colado) + PR draft. needs_wagner: nenhum (infra de teste isolada).`,
  },
  {
    key: 'gt-meta-catraca',
    prompt: `FRENTE GT-G3 — meta-catraca do scorecard: workflow advisory + baseline versionado.
ÁREA EXCLUSIVA: .github/workflows/sdd-scorecard.yml (novo, ADVISORY) + governance/sdd-scorecard-baseline.json (novo) + entry gates-registry.json + (se precisar de flag/modo novo) scripts/governance/sdd-scorecard.mjs.
LEIA: scripts/governance/sdd-scorecard.mjs (mergeado #2597 — modo --ratchet documentado) + memory/decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md (regra de armamento: métrica só arma após 3 medições válidas).
CONTEÚDO: workflow roda o agregador em PR (diff-aware: paths das fontes) + cron diário pós-nightly (06:50 BRT); modo --ratchet falha se métrica ARMADA regredir vs baseline; baseline inicial capturado da medição REAL atual (rode o agregador e commite o resultado como baseline v1 — métricas not_yet_measured ficam desarmadas). DoD: simulação local de regressão (piorar ghost_count num fixture/temp) fica vermelha + PR normal verde + 2 runs = diff vazio. Workflow SEMPRE verde nesta fase (advisory, continue-on-error).`,
  },
  {
    key: 'gt-selftest',
    prompt: `FRENTE GT-G6 — gate-selftest: quem vigia os vigias, com fixtures VERSIONADAS (salda também a dívida de evidência do #2588 — fixtures do ledger-check não foram commitadas).
ÁREA EXCLUSIVA: scripts/governance/gate-selftest.mjs (novo) + tests/governance-fixtures/** (novo) + .github/workflows/gate-selftest.yml (novo, ADVISORY, barato <60s) + entry gates-registry.json.
CONTEÚDO: para cada catraca EXISTENTE em main — knowledge-drift --check (anti-ghost), foundation-ratchet (quarentena/RefreshDatabase/Business::first), ledger-check (refutador), sdd-scorecard --ratchet (se o modo já roda standalone) — criar par de fixtures (1 caso bom que passa + 1 caso ruim que DEVE falhar) e o harness que roda cada script contra as fixtures verificando exit codes. Selftest falha se alguma catraca parou de morder (caso ruim passou). DoD: rodar o selftest completo e colar output (N catracas × 2 fixtures, todas verificadas); sanity: comentar temporariamente um exit 1 de um script numa cópia temp e provar que o selftest pega.`,
  },
  {
    key: 'jana-c1',
    prompt: `FRENTE KL-C1 — consertar o duplo-OFF de config do peso_real (time-decay por lifecycle no recall) com FLAG OFF — zero mudança de comportamento em prod.
PRE-FLIGHT OBRIGATÓRIO: ler memory/requisitos/Jana/BRIEFING.md + SPEC.md + memory/decisions/0270-*.md (D-4 decaimento) ANTES de editar.
ÁREA EXCLUSIVA: o(s) arquivo(s) de config + Service do reranker (descubra: grep RrfReranker/peso_real em Modules/Jana e Modules/Copiloto) + tests/Unit correspondentes. NÃO toque Console/Commands nem tests/eval (são da frente jana-c2).
CONTEÚDO: o plano-mãe documenta vocabulário de lifecycle desalinhado + config peso_real resolvendo null (duplo-OFF). Corrigir: config resolve não-null, pesos por lifecycle (proposto=1.0, aceito=1.0, historical=0.5, superseded=0.3 — confirme os valores na ADR 0270/0275 se especificados), FLAG MESTRE PERMANECE OFF (kill-switch). Pest prova os pesos com flag ON em ambiente de teste E prova que flag OFF = comportamento idêntico ao atual. DoD: suite Pest local do módulo passando (colar output) + diff ≤300. Tier 0: nenhuma query cross-tenant nova; flag OFF garante prod intocada.`,
  },
  {
    key: 'jana-c2',
    prompt: `FRENTE KL-C2 — comando jana:recall-eval + golden set de recall determinístico (sem judge).
PRE-FLIGHT: ler memory/requisitos/Jana/BRIEFING.md + memory/decisions/0270-*.md (D-5 medir leitura) + governance/adr-alias-map.json (mergeado — par colidido DEVE retornar slug certo).
ÁREA EXCLUSIVA: Console/Command novo (jana:recall-eval, no módulo onde vivem os commands da Jana) + tests/eval/recall-golden.yaml (novo) + Pest do comando. NÃO toque config/Services do reranker (frente jana-c1).
CONTEÚDO: golden set de 25-30 queries determinísticas propostas lendo o índice real de ADRs/BRIEFINGs — cada query com expected: slugs que DEVEM aparecer no top-K e violations: slugs historical/superseded que NÃO podem aparecer no top-3; incluir ≥2 queries de pares colididos (alias map). Comando roda em modo mock local (estrutura validada, sem Meilisearch) e modo real (CT 100, fase 2). DoD: comando roda em mock local com output estruturado (colar); golden set valida contra YAML parse + slugs existem no disco. needs_wagner: skim das 25-30 queries (10 min, pode ser pós-merge — flag/uso só na fase 2).`,
  },
  {
    key: 'fv-d1',
    prompt: `FRENTE KL-D1 — destravar o modo REAL do RAGAS (sair do mock-teatro) sem ligar nada ainda.
ÁREA EXCLUSIVA: .github/workflows/jana-ragas-canary.yml + .github/workflows/jana-ragas-gate.yml (edições mínimas) + scripts/jana-ragas-runner.py SE precisar de ajuste do modo.
LEIA os 2 workflows + o runner ANTES (o plano documenta: mock-default com scores fixos 0.85/0.78; bloqueio só em RAGAS_MODE=real).
CONTEÚDO: (1) workflow_dispatch ganha input mode=mock|real (default mock); (2) RAGAS_FORCE_MOCK vira condicional ao input + à EXISTÊNCIA do secret OPENAI_API_KEY (sem secret → mock com warning claro no summary, nunca quebra); (3) cron continua mock até Wagner adicionar o secret (1 clique dele depois); (4) job summary mostra QUAL modo rodou em destaque (anti-teatro: impossível confundir mock com real). DoD: yaml lint + dry-parse + colar trecho do summary simulado dos 2 modos. needs_wagner: adicionar secret OPENAI_API_KEY no repo (Settings→Secrets) quando quiser ligar — custo ≈$1.80/mês.`,
  },
]

phase('Executar')
log(`Fase 1: ${FRENTES.length} frentes paralelas (inclui infra CT 100 + 1º run da suite disparado em background)`)

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
const audit = await agent(`Você é o auditor da Fase 1 da reestruturação SDD do oimpresso. Resultados (JSON): ${JSON.stringify(ok)}
VERIFIQUE (gh pr view/diff em D:/oimpresso.com — read-only; pro CT 100 use ssh -o BatchMode=yes root@100.99.207.66 read-only):
1. PARTIÇÃO: pares de PRs tocando o mesmo arquivo fora de gates-registry.json?
2. TAMANHO ≤300 linhas por PR?
3. dod_evidence é output real? Liste os fracos.
4. Workflows novos no gates-registry? Gates nasceram advisory? Nenhum required tocado?
5. Tier 0: ADR existente editada? Modules de produção com mudança de comportamento (flag OFF respeitada na jana-c1)? Codemod tocou só memory/requisitos?
6. CT 100: o run da suite está realmente rodando (ps/log tail)? O .env.testing aponta pra DB de teste (NUNCA prod)? Cron instalado? Containers pré-existentes intocados (docker ps igual ao baseline: 22 containers)?
Devolva JSON: { aprovados: [...], reprovados: [{key,motivo}], colisoes: [...], fila_wagner: [{key,pr_url,decisao_pendente}], ct100_status: "..." }`,
  { label: 'auditor:fase-1', phase: 'Verificar' })

return { prs: ok, auditoria: audit, falhas: FRENTES.length - ok.length }
