---
date: "2026-06-21"
topic: "Auditoria de saúde & integridade (13 dim · 28 agentes) + 5 consertos autônomos seguros adversarialmente verificados (governança/CI/docs)"
authors: [W, C]
prs: [3134, 3136, 3137, 3147, 3152]
related_adrs:
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0155-module-grade-v3-sub-dimensoes-gate-ci
  - 0240-task-ledger-git-native-cowork-code
  - 0261-enforcement-faseado-gates-ci
  - 0274-referencia-adr-por-slug-alias-map-13-colisoes
  - 0294-metodo-dual-track-shapeup-catraca
  - 0294-mcp-audit-log-hash-chain-tamper-evident
---

# Auditoria de saúde & integridade + consertos autônomos — 2026-06-21

> Wagner pediu uma auditoria completa de saúde/integridade do sistema usando threads/tokens "de forma sábia", depois "criar threads e ver o que consegue arrumar, automático e com adversários". Resultado: 1 auditoria multi-agente (read-only) + 5 PRs de conserto seguros, cada um verificado por adversário independente.

## Parte 1 — Auditoria (workflow multi-agente, read-only)

**Método:** workflow de 13 finders paralelos → 1 verificador cético por dimensão → síntese-chefe → crítico de completude. **28 agentes, ~2.6M tokens, 819 tool-uses, ~29 min** (run `wf_9cee7c8c-ade`). Caveat: o ambiente não tinha MySQL/PHP-no-PATH/SSH-prod/token-MCP → ~metade dos números é *as-of-baseline versionado*, não medição fresca.

### Veredito
- **Nota de percepção: 61/100.** O crítico de completude argumentou (corretamente) que, para um ERP multi-tenant, a saúde deve ser **gated pelo pior risco irreversível, não pela média** → faixa real **40–50** até existir (a) dump de DB restaurado off-host, (b) prova *comportamental* de isolamento em CI, (c) segredos rotacionados.
- **Integridade = parcialmente confiável.** Processo genuinamente honesto consigo (catracas mordem no self-test, baselines não afrouxam sem diff, limitações documentadas em texto nos próprios workflows). Fragilidade = distância entre **documentado** e **enforçado**.

### Scorecard (13 dimensões)
Memória/Conhecimento 83 🟢 · Ciclo de vida de ADRs 76 🟢 · Runtime/Infra 76 🟢 · Dependências & Build 73 🟡 · Processo SDD 69 🟡 · Qualidade de código 68 🟡 · Multi-tenant Tier 0 64 🟡 · Ponte Sells→Financeiro 64 🟡 · Honestidade dos gates CI 63 🟡 · Higiene de git 60 🟡 · Maturidade de módulos 60 🟡 · **Segurança & Segredos 55 🔴** · **Suíte de testes 37 🔴**

### Riscos críticos (ordenados por blast-radius)
0. **Backup/DR quebrado** (o crítico achou; nem estava nas 13): o passo "Backup (arquivos + DB)" do `deploy.yml` **não faz `mysqldump`**, grava no mesmo host, sem cópia off-host, sem restore testado — e houve incidente de cota de disco neste mesmo dia. RPO desconhecido, RTO não testado.
1. **Segredos vivos em repo PÚBLICO** sem rotação (Meilisearch master key no HEAD + tokens comprometidos no histórico append-only).
2. **Verde do PR superestima a saúde**: required de teste rodam ~6% da suíte em SQLite (allowlist); suíte real tem 274 arquivos cronicamente vermelhos (CT100, fora do GitHub Actions).
3. **Multi-tenant**: checker em falso-clean (bug path Windows), guards advisory falhando no main com `business_id=4` reaparecendo; "nenhum vazamento confirmado" nunca foi provado **comportamentalmente** (testes MySQL-only não rodaram).
4. **Órfão `frosty-greider-83ab2f`** resolve git pro repo principal → risco de mass-delete com git mutativo (near-miss PR #2691).
5. **Observers Sells→Financeiro síncronos sem try/catch** podem estourar o commit de venda do core.

### O que o crítico de completude adicionou
- **Bus-factor = 1** (3958 commits Wagner vs ≤50 dos demais em 90d) — causa-raiz única atrás de quase todos os top-risks, tratada como "owner de remediação" em vez de risco sistêmico.
- Dimensões ausentes: **Backup/DR**, **LGPD/privacidade** (além de multi-tenant), **observabilidade/MTTR** (detecção, não só prevenção), **custo/sustentabilidade**.

## Parte 2 — Consertos autônomos (com adversários)

### Lição de mecanismo (importante)
O workflow **em background que MUTA git deadlocou** nesta máquina (cap de concorrência aparente = 2; agentes que fazem `git checkout/commit/push` congelam em background — sem slot pra aprovar prompt → não progride; 0 branch/PR produzido, nada quebrado). A auditoria **read-only** de 28 agentes rodou lisa. **Conclusão: workflow em background é ótimo pra LER, frágil pra ESCREVER.** Troquei pra **execução inline** (controle total, prompts visíveis) + **adversários foreground read-only** (que provaram funcionar). Cada fix numa worktree git isolada de `origin/main` — nunca no cwd órfão nem no main direto.

### Os 5 PRs (cada um: edit → validação local → adversário independente → PR)
| PR | Fix | Validação + adversário |
|---|---|---|
| #3134 ✅merged | colisão ADR **0294** no `adr-alias-map` (13→14) | gerador canônico `0 alertas`; adversário re-derivou 14 colisões do disco, reconciliou 1:1 |
| #3136 ✅merged | comentário do **visual-regression** distingue enforcing vs advisory | YAML válido (python); adversário confirmou as 4 afirmações vs `continue-on-error` real |
| #3137 ✅merged | regenera **`_BACKLOG-GENERATED.md`** stale (696→744 abertas) | `--check` exit 1→0; adversário provou idempotência (SHA igual) + dados vs SPECs |
| #3147 ✅merged | 5 links quebrados (rule + 2 SPECs: slug 0240 + profundidade `../../`→`../../../`) | todos resolvem; adversário confirmou link correto preservado, zero canon tocado |
| #3152 ✅merged | 12 links `decisions/` slug-drift em 5 SPECs | re-discovery 13→1 broken (1 placeholder excluído); adversário validou colisão 0119 + 77/77 links |

**Princípio aplicado:** só conjunto auto-validável (governança/CI/JSON/texto) — nada de lógica PHP (php/composer não estão no PATH, não dá pra rodar Pest local). Sem merge forçado, sem segredos, sem prod, sem deletar worktrees alheias.

### Batedores de descoberta (4 threads read-only paralelas)
Varreram drift de artefatos gerados, texto desonesto em CI, JSON de governança stale, e links/schema quebrados. Achado de maior valor: **`sdd-scorecard.json` committed diz `front_door_coverage=100` mas a verdade é 98.6** — porque `memory/requisitos/_Governanca/` (trabalho untracked do Wagner) tem ≥2 `.md` e **não tem `BRIEFING.md`**. NÃO está vivo no main (o dir ainda não foi commitado); vira problema de catraca **quando for commitado sem o BRIEFING**.

## Parte 3 — Residual (depende do Wagner — não é auto-fixável)
1. **Rotacionar segredos** (repo público) — risco mais agudo.
2. **Backup/DR** (`mysqldump` no deploy + restore testado off-host) — risco mais grave.
3. **Fixes PHP-runtime** (Observers Sells→Financeiro try/catch · checker multi-tenant path-bug + canário anti-falso-clean · 96 models só `where()`) — precisam de ambiente PHP/Pest.
4. **Latente/canon** (quando for mexer): `BRIEFING.md` do `_Governanca` antes de commitar o dir · 2 links no `0296` untracked · slugs em corpos de ADR (0250/0253/0254, append-only) · dead-links de alvo incerto (`NfeBrasil`→`app/Manifesto.php`, `.claude/rules/README.md`→session-log, `Connector/SPEC.md:124` placeholder).

## Lições operacionais (pro time)
- **Background workflow:** read-only ✅ confiável (28 agentes OK); mutação git ❌ deadlock nesta máquina. Para escrever, inline + adversário foreground.
- **Re-verificar o ambiente do batedor:** o drift de `_INDEX-GENERATED.md` que um batedor reportou era do branch local `docs/blueprint`, não de `origin/main` (lá estava em dia) → sempre confirmar contra `origin/main` limpo antes de abrir PR.
- **Worktree órfã `frosty-greider`:** nunca rodar git mutativo com cwd ali; sempre operar em worktree isolada de `origin/main` com `git -C`.
- **Adversário por fix** (read-only, cético, default-reprovado) pegou nuances reais (ex.: o `adr-index-generate` não lê o alias-map; colisão 0119 mapeável só por contexto) — barato e de alto valor.

## Pós-merge (estado final · 2026-06-21)
Todos os **6 PRs da sessão mergeados**, zero falha de CI, `main` verde (adr-index · tasks-index · sdd-scorecard `--ratchet` OK; 359/360 links `decisions/` em SPECs resolvem — o 1 restante é o placeholder do `Connector:124`, deixado de propósito):

- #3134 · #3136 · #3137 · #3147 · #3152 (15:43Z) · #3154 (15:47Z) — todos **merged** via auto-merge SQUASH.
- **Time já agindo no residual #1:** PR #3148 `gitleaks full-history scan + .gitleaks.toml (Onda 1 · segredos)` mergeado no main — endereçando o risco de segredos do repo público apontado na Parte 1.
- Residual restante (Parte 3): backup/DR, fixes PHP-runtime, itens canon/latentes — seguem dependendo do Wagner.
