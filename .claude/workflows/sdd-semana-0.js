export const meta = {
  name: 'sdd-semana-0',
  description: 'Reestruturação SDD — Semana 0, lote 1: 12 frentes paralelas em worktrees isolados, cada uma vira 1 PR draft',
  whenToUse: 'Disparar quando Wagner aprovar o plano memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md. Lote 1 = tudo que NÃO depende de ADR aceito + os 3 ADRs como draft.',
  phases: [
    { title: 'Executar', detail: '12 agents paralelos, 1 área exclusiva cada, PR draft no final' },
    { title: 'Verificar', detail: 'refutador checa partição de arquivos + qualidade dos PRs' },
  ],
}

// ============================================================
// REGRAS COMUNS (cada agent nasce frio — contexto completo aqui)
// ============================================================
const BASE = `Você é UMA frente da Semana 0 da reestruturação SDD do oimpresso (ERP Laravel 13.6 multi-tenant, business_id Tier 0 IRREVOGÁVEL).
LEIA PRIMEIRO (read-only): D:/oimpresso.com/memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md (o plano-mãe) e D:/oimpresso.com/memory/sessions/2026-06-12-audit-sdd-pesquisa-reclassificacao.md (o porquê).

ISOLAMENTO OBRIGATÓRIO (outras frentes rodam em paralelo no mesmo repo):
1. cd D:/oimpresso.com && git fetch origin main
2. git worktree add ../wt-sdd-{KEY} -b sdd/{KEY} origin/main
3. Trabalhe SOMENTE dentro de D:/wt-sdd-{KEY}. NUNCA edite D:/oimpresso.com diretamente nem troque branch lá.
4. Toque SOMENTE os arquivos da sua ÁREA EXCLUSIVA (listada abaixo). Exceção única: scripts/governance/gates-registry.json se você criar workflow novo (regra do memory-health) — adicione SÓ a sua entry; conflito de merge ali é esperado e trivial.

REGRAS DO PROJETO (Tier 0 — violar = PR rejeitado):
- PR ≤300 linhas, 1 intent, conventional commit, mensagem termina com "Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>".
- ADRs são append-only: NUNCA edite ADR existente; nova decisão = ADR nova com supersedes.
- Frontmatter de memory/ segue scripts/memory-schemas/*.schema.json (dates/version como string quoted, status enum estrito) — valide antes de commitar.
- Re-derive TODO número/linha citado no plano a partir do código real em origin/main (regra anti-stale — o plano pode estar defasado).
- NADA de credencial/PII em código, commit ou doc (repo é PÚBLICO).
- Gates novos nascem ADVISORY (nunca required de nascença).

FINALIZAÇÃO: commit, push (git push -u origin sdd/{KEY}), gh pr create --draft com descrição citando o plano-mãe e o step do plano, depois git worktree remove ../wt-sdd-{KEY} --force. NÃO mergeie. NÃO marque ready-for-review.
Devolva JSON: frente, branch, pr_url, files (tocados), dod_evidence (como você PROVOU que funciona — output de teste/execução, não narração), blockers, needs_wagner (a decisão humana pendente, se houver).`

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

// ============================================================
// AS 12 FRENTES DO LOTE 1 (áreas de arquivo DISJUNTAS)
// ============================================================
const FRENTES = [
  {
    key: 'adr-anchor',
    prompt: `FRENTE SA-A1 — ADR do formato canônico do anchor spec↔código + regra do FLUXO NOVO.
ÁREA EXCLUSIVA: memory/decisions/<NNNN>-anchor-spec-codigo-*.md (número novo livre via scripts/governance/adr-index-generate.mjs) + scripts/memory-schemas/spec.schema.json (key OPCIONAL, grace-period conforme README do diretório) + o template de SPEC se existir em memory/requisitos/_templates ou equivalente.
CONTEÚDO: gramática machine-parseable do campo por US — "**Implementado em:** \`<path>\`[ · \`<Symbol|Controller@method>\`] · verificado@<sha7> (<YYYY-MM-DD>)"; sentinela "_pendente_" como estado de 1ª classe (tela não construída CONTA como coberta); estado "_parcial_" opcional; plano ratchet advisory→required em 3 fases; regra do fluxo novo (SPEC criado a partir de agora já nasce com o campo, schema valida com grace-period pros 57 legados). Cite ADR 0270 e o plano-mãe.`,
  },
  {
    key: 'adr-slug',
    prompt: `FRENTE KL-B1 — ADR de referência canônica por SLUG + alias map das 13 colisões de número de ADR.
ÁREA EXCLUSIVA: memory/decisions/<NNNN>-referencia-adr-por-slug-*.md + governance/adr-alias-map.json (novo).
CONTEÚDO: referência canônica = slug completo (NNNN-titulo), não número cru; alias map com as 13 colisões REAIS (descubra rodando scripts/governance/adr-index-generate.mjs ou knowledge-drift; liste cada par colidido com slug desambiguador); regra: ADR nova citando número colidido sem slug = bloqueio futuro (gate em fase 2, aqui só a decisão). NÃO renumere nada (append-only).`,
  },
  {
    key: 'adr-scorecard',
    prompt: `FRENTE GT-G1 — ADR do scorecard SDD canônico (10 métricas) + calendário único de promoções a required.
ÁREA EXCLUSIVA: memory/decisions/<NNNN>-scorecard-sdd-*.md.
CONTEÚDO: as 10 métricas com fonte/baseline/alvo/cadência (tabela do plano-mãe §2 — re-derive os baselines que já são mensuráveis hoje: ghost_count, front_door_coverage, anchor_coverage estrito); fórmula da composta v1/v2 (regimes não comparáveis); direção de catraca por métrica; regra de armamento (métrica só arma após 3 medições válidas); CALENDÁRIO DE PROMOÇÕES: máx 1 promoção a required/semana, critérios objetivos pré-escritos por gate (7 nightlies verdes, 14d advisory FP<5%, etc), Wagner é o único que flipa branch protection.`,
  },
  {
    key: 'fv-junit',
    prompt: `FRENTE FV-F1 — consertar a coleta de resultados JUnit da suite.
ÁREA EXCLUSIVA: .github/workflows/ci.yml (e SÓ as linhas de log/artefato — não mude o subset de testes) ou workflow nightly se preferir arquivo novo fullsuite-diag-prep.
ATENÇÃO ANTI-STALE: verifique primeiro — origin/main JÁ tem --log-junit em algum workflow; o que falta é upload-artifact com if: always() (a única tentativa anterior rendeu artefato 0 bytes). Adicione também um sumário JSON por arquivo de teste (script inline ou scripts/tests/junit-summary.mjs novo). DoD: explicar como um workflow_dispatch produziria artefato >0 bytes com n_testcases coerente.`,
  },
  {
    key: 'fv-action',
    prompt: `FRENTE FV-F2 — extrair composite action .github/actions/pest-mysql-setup.
ÁREA EXCLUSIVA: .github/actions/pest-mysql-setup/** (novo) + .github/workflows/financeiro-pest.yml (refactor para consumir a action).
CONTEÚDO: extrair do financeiro-pest.yml o setup MySQL service container + migrate + seed (seed DEVE criar biz=1 E biz=2 — Tier 0 cross-tenant). DoD: financeiro-pest.yml continua verde consumindo a action (yaml-lint + dry parse; declare no PR que o gate existente é a prova no CI). Comportamento antes/depois IDÊNTICO — refactor puro.`,
  },
  {
    key: 'fv-quarentena',
    prompt: `FRENTE FV-Q1 — mecanismo de quarentena + catracas "só diminui".
ÁREA EXCLUSIVA: scripts/tests/foundation-ratchet.mjs (novo) + scripts/tests/baselines/*.json (novos) + .github/workflows/foundation-ratchet.yml (novo, ADVISORY) + registro em scripts/governance/gates-registry.json.
CONTEÚDO: convenção @group('legacy-quarantine') com comentário-razão obrigatório; ratchet determinístico (segundos, sem MySQL): n_quarantine só diminui, n_RefreshDatabase só diminui, contador Business::first() em testes só diminui (conte hoje via grep e congele baselines REAIS medidos agora). Workflow advisory com job summary mostrando os contadores em todo PR. Inclua fixtures boa/ruim + teste do ratchet (padrão hooks .test do repo).`,
  },
  {
    key: 'fv-redfirst',
    prompt: `FRENTE FV-T0 — hook red-first em modo WARN (advisory de nascença).
ÁREA EXCLUSIVA: .claude/hooks/warn-red-first.ps1 + .claude/hooks/warn-red-first.test.ps1 (novos) + registro no .claude/settings.json (SÓ a entry do hook novo).
CONTEÚDO: hook PreToolUse em Edit/Write de arquivo de PRODUÇÃO (app/**, Modules/**/Services|Entities|Http/**, excluindo *Test.php e .md) que WARN (exit 0 sempre, nunca bloqueia nesta fase) quando não há teste correspondente tocado/criado na sessão recente — siga o padrão exato do nudge-test-contract-anchor.ps1 existente. Mensagem ensina: "escreva o teste que falha primeiro". Critério de promoção a bloqueador escrito em comentário no topo (taxa de falso-positivo <10% medida em 14d). DoD: rodar o .test.ps1 e colar output.`,
  },
  {
    key: 'kl-ghostgate',
    prompt: `FRENTE KL-A2 — catraca anti-ghost (baseline POR MÓDULO, não global).
ÁREA EXCLUSIVA: scripts/governance/knowledge-drift.mjs (adicionar modo --check --baseline) + governance/knowledge-ghosts-baseline/ (diretório novo, 1 JSON por módulo citante — anti conflito de 6 streams num arquivo só) + .github/workflows/knowledge-ghost-gate.yml (novo, ADVISORY, diff-only) + entry em scripts/governance/gates-registry.json.
CONTEÚDO: o script já detecta identity-drift; adicione: baseline congela os nomes-fantasma ATUAIS por módulo (re-derive a lista real agora — plano cita 39 módulos/27 nomes, confirme); modo --check falha SÓ se PR introduz ghost NOVO fora do baseline; remover nome do baseline = só diminui. DoD: rodar --check num caso simulado bom e ruim, colar output.`,
  },
  {
    key: 'kl-codemod',
    prompt: `FRENTE KL-A1 — codemod ghost-fix: script + tabela de mapeamento CURADA (dry-run only nesta fase; aplicação é fase 2).
ÁREA EXCLUSIVA: scripts/governance/ghost-fix.mjs (novo) + governance/ghost-rename-map.json (novo).
CONTEÚDO: tabela com os renames REAIS validados em ADR/git (ex Copiloto→Jana por ADR 0088 — confirme cada um lendo as ADRs de rename; NÃO invente mapeamento: nome sem evidência fica FORA da tabela com comentário). Script: escopo HARDCODED a memory/requisitos/**, --dry-run default, --write opt-in, idempotente (re-run pós-apply = 0 diffs), relatório por módulo. DoD: output do dry-run real colado no PR (N ocorrências mapeáveis por nome). needs_wagner: revisar a tabela de renames antes de qualquer --write.`,
  },
  {
    key: 'gt-agregador',
    prompt: `FRENTE GT-G2 — agregador scripts/governance/sdd-scorecard.mjs → governance/sdd-scorecard.json.
ÁREA EXCLUSIVA: scripts/governance/sdd-scorecard.mjs (novo) + governance/sdd-scorecard.json (1ª medição commitada).
CONTEÚDO: node puro sem deps (clone do idioma knowledge-drift.mjs); lê as fontes que JÁ existem (knowledge-drift --json pra ghost_count e front_door_coverage; grep dos SPECs pra anchor_coverage estrito) e marca o resto not_yet_measured; output determinístico (re-run sem mudança = diff vazio — sem timestamps no corpo, _meta separado); modo --ratchet preparado mas desarmado. DoD: rodar 2× e provar diff vazio; colar o scorecard v1 real.`,
  },
  {
    key: 'gt-refutador',
    prompt: `FRENTE GT-G5 — protocolo adversarial do backfill + ledger (BLOQUEIA os lotes IA das outras ondas — tem que mergear antes deles).
ÁREA EXCLUSIVA: memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md (novo) + governance/sdd-verification-ledger.json (novo, vazio com schema) + scripts/governance/ledger-check.mjs (novo).
CONTEÚDO: protocolo — todo PR de lote de backfill IA (>10 arquivos em memory/requisitos/**) exige entry no ledger ANTES do merge: agente refutador em sessão FRESCA, modelo ≥ gerador, prompt adversarial ("prove que este anchor/claim/BRIEFING está ERRADO"), amostra 100% em anchors / ≥30% em prosa, critério de aceite backfill_error_rate <2%, checklist inclui scan PII (CPF/CNPJ/nomes de cliente — repo público). ledger-check.mjs: detecta PR-de-lote sem entry (pra plugar no workflow do scorecard na fase 2). DoD: rodar ledger-check contra um caso simulado.`,
  },
  {
    key: 'ch-frontmatter',
    prompt: `FRENTE CH-1 — backfill mecânico de frontmatter nos charters incompletos (melhora a evidência do batch de anchors da fase 2).
ÁREA EXCLUSIVA: resources/js/Pages/**/*.charter.md (SÓ frontmatter; NUNCA o corpo — Non-Goals/Anti-hooks são Wagner-only).
CONTEÚDO: ~32 charters sem component:/page: (re-derive a lista real). Preencha APENAS quando inequívoco: page: = caminho do .tsx ao lado (mecânico, sempre seguro); component: só se o .tsx existir e o nome bater; us: SÓ se o corpo do charter já citar US-XXX-NNN explícito. Ambíguo = pula e lista no PR. Valide contra o charter.schema.json se existir. DoD: tabela no PR — preenchidos vs pulados com razão. Se >300 linhas, divida em 2 PRs por módulo.`,
  },
  {
    key: 'kl-identidade',
    prompt: `FRENTE KL-E1 — tabela de triagem de identidade das pastas órfãs (decisão é 100% Wagner; você só PREPARA).
ÁREA EXCLUSIVA: memory/requisitos/_TRIAGEM-IDENTIDADE-2026-06.md (novo).
CONTEÚDO: para cada pasta de memory/requisitos/ sem BRIEFING.md e cada par suspeito de duplicata/rename (re-derive a lista real — plano cita 22 órfãos, 7 decisões de identidade): 1 linha com docs count, último commit, módulo código correspondente existe?, e proposta fundamentada (FUNDIR em X / RENOMEAR pra Y / MATAR com lápide / GENUÍNO criar porta). Coluna "decisão Wagner" VAZIA. needs_wagner: preencher a coluna (~15 min). Nenhuma execução aqui.`,
  },
]

// ============================================================
phase('Executar')
log(`Disparando ${FRENTES.length} frentes paralelas — worktrees isolados, 1 PR draft cada`)

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
log(`${ok.length}/${FRENTES.length} frentes entregaram PR`)

// ============================================================
phase('Verificar')
const audit = await agent(`Você é o auditor da Semana 0 da reestruturação SDD do oimpresso. Resultados dos ${ok.length} PRs draft (JSON): ${JSON.stringify(ok)}
VERIFIQUE (use gh pr view/diff em D:/oimpresso.com — read-only):
1. PARTIÇÃO: algum par de PRs toca o mesmo arquivo fora de gates-registry.json? (colisão = violação da área exclusiva)
2. TAMANHO: algum PR >300 linhas?
3. dod_evidence é EVIDÊNCIA (output real) ou narração? Liste os fracos.
4. Workflows novos registraram entry no gates-registry.json? Gates novos nasceram advisory?
5. Algum PR tocou ADR existente (violação append-only) ou área Tier 0 (Modules de produção)?
Devolva JSON: { aprovados: [...keys], reprovados: [{key, motivo}], colisoes: [...], fila_wagner: [{key, pr_url, decisao_pendente}] }`,
  { label: 'auditor:semana-0', phase: 'Verificar' })

return { prs: ok, auditoria: audit, falhas: FRENTES.length - ok.length }
