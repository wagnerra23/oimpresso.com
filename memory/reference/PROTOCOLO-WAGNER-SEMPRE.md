---
slug: protocolo-wagner-sempre
title: "PROTOCOLO WAGNER SEMPRE — checklist canônico de execução automática"
type: protocol
date: 2026-05-17
session-origem: stupefied-noether-89f83d
status: canon-tier-A-irrevogavel
related_adrs: [0094, 0095, 0061, 0093, 0101, 0104, 0106, 0114, 0143, 0167]
related_skills: [brief-first, mcp-first, multi-tenant-patterns, commit-discipline, preflight-modulo, smoke-prod-evidence, charter-first, wagner-request-refiner, wagner-protocol-enforce, mwart-comparative, brief-update]
related_agents: [wagner-understand]
supersedes: null
trigger_condition: TODA SESSÃO Claude no oimpresso (SessionStart hook)
---

# PROTOCOLO WAGNER SEMPRE

> **Wagner palavras textuais 2026-05-17, sessão `stupefied-noether-89f83d`:**
>
> *"quero que prepare o protocolo. e sempre faça. não é justo eu sempre ficar pedindo a mesma coisa. mantenha o conhecimento agregado e automatize não me irrite. apreenda. se torne especialista. crie maneira de entender e lembra do que tem que executar. crie um agente especializado em entender."*

Este documento é a **lei canônica** que enumera as regras que Wagner sempre solicita. Carregado automaticamente via [skill `wagner-protocol-enforce`](../../.claude/skills/wagner-protocol-enforce/SKILL.md) Tier A always-on no SessionStart de toda sessão Claude no oimpresso.

**A regra é simples:** Claude executa cada item ABAIXO automaticamente, no momento certo, **sem Wagner ter que pedir**. Esquecer = violação de protocolo = post-mortem registrado.

---

## R1 — Smoke real obrigatório (não narração)

**Quando:** após (a) merge de PR, (b) deploy SSH, (c) declarar "funcionando", (d) declarar "tela X tá ok visualmente", (e) edição em runtime crítico (.htaccess, middleware, routes, Inertia render, asset bundle).

**O que fazer (Claude executa):**

1. Abrir `claude-in-chrome` (preferido) ou `computer-use` (fallback) — **NUNCA delegar pra Wagner abrir o browser**.
2. Navegar pra URL alvo (prod `oimpresso.com/<rota>` ou dev local).
3. `screenshot` + `read_console_messages` (filtrar errors).
4. Salvar screenshot em `memory/sessions/YYYY-MM-DD-smoke-<rota>.png` OU citar `ss_<id>` da resposta.
5. Comparar contra screenshot canônico ou prototype HTML quando aplicável.
6. **Só ENTÃO declarar "smoke ok" com link/evidência inline.**

**Sinal de violação:** Claude escreve "deve estar funcionando" / "✅ deploy ok" / "página tá ok visualmente" **sem evidência inline visível**.

**Skill correlata:** [`smoke-prod-evidence`](../../.claude/skills/smoke-prod-evidence/SKILL.md) Tier B (exige `curl -sv` antes de declarar funcionando — origem 3 PRs cascata 2026-05-17).

---

## R2 — Cópia literal quando design aprovado (não slice)

**Quando:** Wagner aprovou screenshot/visual do prototype Cowork (`prototipo-ui/prototipos/<modulo>/`), mesmo informalmente ("isso aí é o resultado esperado", "copia isso", screenshot colado no chat sem objeção).

**O que fazer (Claude executa):**

1. Copia cópia integral em 1 PR — **NÃO** propor slice em 4 refinos (R1/R2/R3/R4).
2. Override de `commit-discipline ≤300 linhas` é OK, com label `design-literal-copy` justificando + link pro screenshot aprovado + visual-comparison.md.
3. Backend deltas necessários (campos derivados) fazem parte da cópia — não inventar que "frontend computa".
4. Plug-points (mock → real) acontecem DURANTE a cópia visual, não em PR separado.
5. Verifica via Brave após (R1).

**Sinal de violação:** Claude propõe "vou só fazer o R1 SLA pill primeiro" depois de Wagner ter aprovado o screenshot completo. Inflar "versão refinada" pós-corte é violação.

**Doc base:** [`feedback-design-literal-copy-quando-aprovado.md`](feedback-design-literal-copy-quando-aprovado.md).

---

## R3 — Workflow 3 fases obrigatório (Mexeu → Registra)

**Quando:** qualquer Edit/Write em `Modules/<X>/`, daemon CT 100, schema DB, config infra.

**O que fazer (Claude executa):**

1. **PRÉ-FLIGHT** (ANTES do Edit):
   - Ler `memory/requisitos/<X>/SPEC.md`
   - Ler `memory/requisitos/<X>/RUNBOOK*.md`
   - Ler `memory/requisitos/<X>/CAPTERRA*.md`
   - Ler `<Tela>.charter.md` ao lado do `.tsx` se aplicável
   - `decisions-search since:<últimos 30 dias>` filtrado por módulo
   - Invocar skill `como-integrar` se feature parcial existente
2. **DURING** (mexendo):
   - Commit incremental por step lógico
   - `git push` WIP a cada ~30min se feature >1h
   - `TodoWrite` marca completed após cada step atômico
   - **NUNCA** `git checkout` outra branch sem `stash` ou `commit` (lição 2026-05-17 — 4h de trabalho quase perdidas em stash recovery)
3. **POST** (mexeu):
   - PR no git + CI verde + merge + docs canon atualizados
   - Skill `brief-update` Tier B atualiza BRIEFING.md automaticamente

**Sinal de violação:** Claude faz Edit sem ler SPEC/RUNBOOK ANTES, ou "ajuste rápido" sem commit no fim.

**Doc base:** [`feedback-modulo-mexeu-registra-sempre.md`](feedback-modulo-mexeu-registra-sempre.md) + [proibições.md §REGRA PRIMÁRIA](../proibicoes.md).

---

## R4 — Multi-tenant Tier 0 IRREVOGÁVEL

**Quando:** qualquer Edit em Eloquent Model, Controller, Service, Job, Command, Migration que toca dados de negócio.

**O que fazer (Claude executa):**

1. Garantir `business_id` global scope ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)).
2. Jobs/Commands recebem `$businessId` no constructor (session() não funciona em fila).
3. Migration nova: `business_id` indexado + FK obrigatórios.
4. Pest cross-tenant biz=1 vs biz=99 obrigatório.
5. PII real (CPF/CNPJ cliente) NUNCA em PR/commit/log — usar `[REDACTED]` ou `PiiRedactor`.

**Sinal de violação:** `withoutGlobalScopes()` sem comentário `// SUPERADMIN: <razão>` ou Pest sem isolamento.

**Skill correlata:** [`multi-tenant-patterns`](../../.claude/skills/multi-tenant-patterns/SKILL.md) Tier A always-on.

---

## R5 — PT-BR + Economia de crédito (escopo antes massivo)

**Quando:** SEMPRE no oimpresso (Wagner+Eliana brasileiros).

**O que fazer (Claude executa):**

1. PT-BR em tudo: texto UI, commit, comentário, label, error message. Código em inglês ok; domínio negócio em PT (Marcacao, Intercorrencia, BancoHoras, Faturada, Pendente).
2. **Confirmar escopo ANTES de implementar massivamente** — se pedido é grande (>500 LOC est., 4+ arquivos, refator estrutural), confirmar escopo com perguntas curtas (`AskUserQuestion`) ANTES.
3. Wagner valoriza economia — não invente refinamentos não pedidos.
4. **Não responder em inglês.**

**Sinal de violação:** Claude responde "Sure, I'll implement..." (inglês) ou implementa 1500 LOC sem confirmar escopo + tem que refazer.

**Skill correlata:** [`wagner-request-refiner`](../../.claude/skills/wagner-request-refiner/SKILL.md) (Tier B — decompõe pedidos multi-item).

---

## R6 — Cliente ROTA LIVRE biz=4 NUNCA em teste/smoke

**Quando:** Pest fixtures, smoke produção, geração de dados teste.

**O que fazer (Claude executa):**

1. Pest sempre `business_id=1` (Wagner WR2 SC) — [ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md).
2. Smoke prod abre `oimpresso.com` logado como Wagner (biz=1 ou biz=164) — NÃO Larissa (biz=4 ROTA LIVRE 99% volume vendas, qualquer click pode disparar email/whatsapp pra cliente real).
3. Mock/fixture com nome "João Silva" (genérico) — NÃO "Larissa Rotti" / dados reais.
4. Cliente piloto Larissa monitor 1280px — validar layout não quebra nesse breakpoint ([proibições §Cliente piloto](../proibicoes.md)).

**Sinal de violação:** Pest com `Business::factory()->create(['id' => 4])` ou comentário "validar biz=4 cliente".

---

## R7 — Charter `live` + visual-comparison antes de Edit Page Inertia

**Quando:** Edit/Write em `resources/js/Pages/<Mod>/<Tela>.tsx`.

**O que fazer (Claude executa):**

1. **Charter check** ([skill `charter-first`](../../.claude/skills/charter-first/SKILL.md) Tier A always-on): se `<Tela>.charter.md` existe ao lado, **ler ANTES** de tocar `.tsx`. Mission/Goals/Non-Goals/UX targets/Anti-hooks.
2. **MWART F1.5 visual-comparison.md** obrigatório se migração Blade→Inertia ([ADR 0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md) + [ADR 0107](../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)).
3. **Cowork prototype** se aplicável: confere `prototipo-ui/prototipos/<modulo>/` ([ADR 0114](../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)).
4. **Inertia::defer DEFAULT** em props caras (skill `inertia-defer-default` Tier B).
5. Pós-merge: skill `tela-smoke-pos-merge` Tier B + `brief-update` Tier B disparam.

**Sinal de violação:** Edit em `.tsx` sem ler `.charter.md` ao lado OU sem `visual-comparison.md` quando migração nova.

---

## R8 — Branch + worktree disciplina (NUNCA editar main repo paths em worktree)

**Quando:** Claude trabalha em worktree filha de `D:\oimpresso.com\.claude\worktrees\<nome>`.

**O que fazer (Claude executa):**

1. **Edits sempre via path absoluto do WORKTREE** — `D:\oimpresso.com\.claude\worktrees\<nome>\<arquivo>` — NUNCA `D:\oimpresso.com\<arquivo>` (esse é main repo, outra branch).
2. Bash `cwd` reset automaticamente pro worktree, mas Edit aceita absoluto — checar 2x antes de cada Edit.
3. Pre-flight `pwd` ou `git branch --show-current` no worktree pra confirmar onde está.
4. Se Edit foi pro path errado (main repo), recuperar via `cp <main-repo>/<file> <worktree>/<file>` + `git checkout HEAD -- <file>` no main repo pra restaurar.

**Sinal de violação:** Edit reporta sucesso mas `git status` no worktree mostra arquivo não modificado — sinal que foi pro main repo. Lição catalogada sessão 2026-05-17 (PR #1032 — 4h pra recuperar via stash@{0} salvo por Wagner).

---

## R9 — ZERO auto-mem privada (canon vai pra git)

**Quando:** Claude pensa em "vou guardar isso na memória pra lembrar" ou tenta Write em `~/.claude/projects/*/memory/*.md`.

**O que fazer (Claude executa):**

1. **Conhecimento canônico** vai pra `memory/reference/` ou `memory/decisions/` ou `memory/requisitos/` no git ([ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)).
2. **Local pessoal Wagner** vai pra `~/.claude/oimpresso-local/` ([ADR 0131](../decisions/0131-tiering-memoria-canonico-local-segredo.md) categoria LOCAL).
3. **Segredo** vai pra Vaultwarden (`vault.oimpresso.com`).
4. Hook `block-automem.ps1` BLOQUEIA Write em `~/.claude/projects/*/memory/*.md` desde Constituição v2.

**Sinal de violação:** Edit/Write em auto-mem privada. Catalogado pela própria sessão 2026-05-17 (escrevi 1x feedback canon em auto-mem, hook não bloqueou, tive que mover pra git).

---

## R10 — Aprovação humana antes de COMMIT/PUSH/MERGE (calibrada com R11)

**Quando:** todo git push, gh pr create, gh pr merge.

**O que fazer (Claude executa):**

1. Implementa tudo localmente (Edit/Write/Pest verde/TS check).
2. `git status` + `git diff --stat` pra Wagner inspeciona ANTES do commit.
3. **Espera "sim pode" / "pode mergear" / "manda"** explícito **uma vez** pra autorizar o caminho.
4. **A autorização cobre o ESCOPO PRÉ-APROVADO inteiro** — incluindo PRs follow-up que Wagner explicitamente mandou abrir ("sim, merge + abre PR follow-up"). Ver R11.
5. Branch protection de `main` exige PR + review — nunca push direto em main.
6. NÃO usar `--no-verify` / `--no-gpg-sign` sem autorização explícita. `--admin` SIM quando Wagner autorizou caminho que precisa bypassar "branch up-to-date" check.

**Sinal de violação:** Claude faz `gh pr merge` em PR que Wagner NÃO autorizou OU `git push --force` sem permissão.

**Anti-padrão calibrado (R11):** Claude faz `gh pr create` autorizado e PARA esperando ENTÃO aprovação SEPARADA do merge do mesmo PR. Wagner já autorizou o caminho — Claude continua até desfecho.

**Doc base:** [skill `publication-policy`](../../.claude/skills/publication-policy/SKILL.md) — matriz Tier 0/1/2 do que escala.

---

## R11 — Continuar autonomamente até desfecho dentro do escopo pré-aprovado

**Origem:** Wagner palavras textuais 2026-05-17, sessão `stupefied-noether-89f83d`, segundo turno:

> *"atualize seu protocolo para ficar esperando eu tive que vir aqui lembrar"*

Wagner havia autorizado o caminho completo ("Sim, merge agora + abre PR follow-up dos filtros") mas Claude parou nos PRs #1034 e #1035 esperando aprovação **separada** de cada merge — em vez de continuar até o desfecho.

**Quando:** Wagner aprovou explicitamente um CAMINHO (não só uma ação isolada). Frases-gatilho:

- "sim pode"
- "manda"
- "Sim, merge agora + abre PR follow-up"
- "vai" / "faz isso"
- "pode" + descrição de N passos

**O que fazer (Claude executa):**

1. **Identificar o desfecho final do escopo pré-aprovado** — não a primeira ação isolada.
2. **Executar do começo ao fim** sem pausa pra re-aprovação interna desde que cada passo esteja dentro do caminho aprovado:
   - Implementação → tests → commit → push → PR → CI watch → merge → smoke pos-deploy → BRIEFING update → cleanup
3. **CI watch ativo** — se CI rodando, fica em loop curto (`gh pr checks <N>` polling cada 30-60s OU usa run_in_background) até verde ou red.
4. **CI verde + aprovação prévia → merge automático** com `--admin` se necessário pra bypassar branch-up-to-date (mesma autorização do passo 3 acima).
5. **Pos-merge → smoke real (R1)** automático, não delegar pra Wagner.
6. **Pos-smoke → relatório resumido pro Wagner** quando ele voltar — não aguardar pergunta dele.

**Sinal de violação:** Wagner volta e pergunta "e aí?", "o que tá rolando?", "tive que vir lembrar" — significa Claude parou no meio de caminho pré-aprovado.

**Quando PARAR mesmo dentro de escopo pré-aprovado (NÃO violação):**

- **CI fica RED** — coleta evidência (link do job, erro inline) e ESPERA decisão Wagner (não tente "fix automático" sem aprovação)
- **Detecta gap visual em smoke** — reporta gap + propõe hotfix + ESPERA aprovação Wagner (foi o caso do PR #1034 detectado via smoke Brave de #1032 — comportamento certo)
- **Escopo se expande** além do que Wagner aprovou — pausa e pede aprovação extension
- **Resultado contradiz hipótese** (ex: smoke mostra layout quebrado) — pausa, reporta, espera
- **Erro Tier 0** (vazamento `business_id`, PII em log) — bloqueia e reporta imediato

**Como decidir "continuar" vs "parar":**

| Situação | Continuar (R11) | Parar (R10 ou outro) |
|---|---|---|
| Wagner aprovou caminho completo + CI verde | ✅ merge automático | — |
| Wagner aprovou caminho + CI rodando | ✅ watch + continua | — |
| Wagner aprovou caminho + CI red | — | ✅ reporta + espera decisão |
| Wagner aprovou PR1 mas PR2 não explicitamente | ✅ se PR2 está no caminho aprovado | ✅ se PR2 é novo escopo |
| Detectou gap visual via smoke | — | ✅ reporta + propõe hotfix |
| Tier 0 violado em algum passo | — | ✅ bloqueia + reporta |

**Doc base:** este ADR + [feedback-continuar-ate-desfecho.md](feedback-continuar-ate-desfecho.md) (a criar como follow-up se reincidir).

---

## Como Claude detecta violação no meio da sessão (auto-check)

Após cada turno, Claude se pergunta:

- Mexi em path que precisa pré-flight? ler `SPEC.md`+`RUNBOOK*.md`+`charter.md`+ADRs (R3 R7)
- Mexi em Eloquent/Service/Job que toca dados? confer `business_id` scope (R4)
- Estou em worktree? Edits foram pro path worktree, não main repo? (R8)
- Vou declarar "funcionando"? tenho `curl -sv` ou `screenshot` salvo? (R1)
- Wagner aprovou design? estou copiando integral, não slice? (R2)
- Vou commit/push/merge? Wagner autorizou ESCOPO/CAMINHO (não só ação isolada)? (R10 + R11)
- Texto UI/commit em PT-BR? (R5)
- Estou em Pest? business_id=1, não biz=4 cliente? (R6)
- Vou Write em `~/.claude/projects/*/memory/`? Mover pra `memory/reference/` git canon? (R9)
- **Estou no meio de escopo pré-aprovado? Vou continuar até desfecho final ou vou parar e fazer Wagner perguntar "e aí?"** (R11)

Auto-check antes de ENTREGAR. Se qualquer ❌ → corrigir + entregar com nota.

**Regra de ouro R11:** se Wagner volta e pergunta "e aí?" ou "o que tá rolando?" → Claude parou onde devia continuar. Catalogar como violação + corrigir caminho atual.

---

## Como Claude AGREGA conhecimento novo (não perder em /clear ou nova sessão)

1. **Feedback novo de Wagner** ("não faz X", "sempre Y") → criar OU editar `memory/reference/feedback-<slug>.md` ANTES de fim do turno.
2. **ADR aceita** → `memory/decisions/NNNN-<slug>.md` append-only.
3. **Skill nova ou Tier mudou** → `.claude/skills/<nome>/SKILL.md` + commit + push (webhook GitHub→MCP propaga).
4. **Hook novo** → `.claude/hooks/<nome>.ps1` + entry em `settings.json`.
5. **Subagent novo** → `.claude/agents/<nome>.md`.
6. **memory-sync** skill Tier C dispara `git push` pra MCP server propagar pro time.
7. **brief-update** skill Tier B mantém `BRIEFING.md` do módulo afetado atualizado por PR.

**Wagner não deve precisar pedir "lembra disso" — Claude formaliza no protocolo automaticamente.**

---

## Esta sessão (2026-05-17) — incidentes que originaram este protocolo

1. **Smoke real ausente** — eu propus checklist pós-merge pra Wagner fazer, em vez de eu abrir Brave. Corrigido nesta sessão (PR #1032 → verificação Brave real → PR #1034 follow-up dos gaps).
2. **Edits no path errado** — eu editei `D:\oimpresso.com\<arquivo>` (main repo) em vez de `D:\oimpresso.com\.claude\worktrees\stupefied-noether-89f83d\<arquivo>` (worktree). Trabalho de ~4h quase perdido — Wagner salvou via `git stash push -u "wagner-wip-before-infra-contract-claim-evidence"` que eu apliquei depois.
3. **Auto-mem privada** — escrevi `feedback_design_literal_copy.md` em `~/.claude/projects/*/memory/` antes de mover pra git canon `memory/reference/`.
4. **Cópia parcial proposta** — depois do Wagner aprovar screenshot integral, propus slice em R1 antes ele cortar com "vai fazer cagada se tentar fazer diferente".
5. **Gap legacy não migrado** — Cowork rewrite #1032 não montou `SellsDateFilter`/`GroupBy`/`SellsToggleViewMode` (componentes existiam em _components/). Detectei via verificação Brave; PR #1034 corrige.
6. **Parei no meio de escopo pré-aprovado** — Wagner havia aprovado "Sim, merge agora + abre PR follow-up dos filtros". Eu fiz #1032 merge + #1034 PR (correto) MAS PAREI esperando "OK mergeia #1034" separado — Wagner teve que voltar e dizer *"atualize seu protocolo para ficar esperando eu tive que vir aqui lembrar"*. Origem da regra **R11 — Continuar autonomamente até desfecho dentro do escopo pré-aprovado**.

---

## Refs

- ADR 0094 [Constituição v2 — 7 camadas + 8 princípios duros](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) (documento mãe)
- ADR 0095 [Skills tiers convenção interna](../decisions/0095-skills-tiers-convencao-interna.md)
- ADR 0167 [Errata 0130 — índice handoff histórico longo](../decisions/0167-errata-0130-indice-handoff-historico-longo.md)
- Feedback canon irmãos:
  - [feedback-design-literal-copy-quando-aprovado.md](feedback-design-literal-copy-quando-aprovado.md)
  - [feedback-modulo-mexeu-registra-sempre.md](feedback-modulo-mexeu-registra-sempre.md)
  - [feedback-nunca-publicar-credenciais.md](feedback-nunca-publicar-credenciais.md)
  - [feedback-baileys-7x-decisao-irreversivel.md](feedback-baileys-7x-decisao-irreversivel.md)
- Skill enforcement: [`wagner-protocol-enforce`](../../.claude/skills/wagner-protocol-enforce/SKILL.md) Tier A always-on
- Agent decoder: [`wagner-understand`](../../.claude/agents/wagner-understand.md) — subagent que recebe pedido cru e devolve interpretação refinada cruzando protocolo + SPECs + ADRs + charters
