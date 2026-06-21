---
slug: protocolo-wagner-sempre
title: "PROTOCOLO WAGNER SEMPRE — checklist canônico de execução automática"
type: protocol
date: 2026-05-17
session-origem: stupefied-noether-89f83d
status: canon-tier-A-irrevogavel
related_adrs: [0094, 0095, 0061, 0093, 0101, 0104, 0106, 0114, 0119, 0130, 0143, 0167]
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

## ⭐ LEI DE UMA TELA — pré-voo de todo turno (acima de R1-R14)

> **Status:** ✅ aprovada por Wagner 2026-06-08. Origem: auditoria Cowork `Auditoria CC - 2026-06-08`. ADR draft [`proposals/drafts/lei-de-uma-tela-pre-voo-verdade-proporcao.md`](../decisions/proposals/drafts/lei-de-uma-tela-pre-voo-verdade-proporcao.md) — **número a cravar no merge** (working-tree ia até 0250; main pode ter 0251-0256 de PRs recentes — evitar colisão).
>
> **Por que aqui em cima:** numa única sessão Claude afirmou/planejou/produziu 4× sobre premissa não-verificada (drag-drop "faltava" mas já existia; Sells "azul" mas já era roxo; convergência Oficina "falta" mas já mergeada PR #2417; Compras "última ilha" mas já aliasada). **Não foram 4 erros — foi 1 erro 4×: produzir antes de estabelecer a verdade viva.** A causa não foi falta de regra (R3/R14 já mandam "leia antes de afirmar") — foi **regra passiva que não trava no momento da ação**. Mesma doença que curamos no código (warning aspiracional → trava de CI). A cura é uma **sequência obrigatória**, não mais uma regra na lista. Esta Lei **reorganiza R1-R14 num trilho — não substitui o histórico**.

**A Lei (cabe numa tela, roda ANTES de qualquer afirmação, plano ou arquivo):**

### `VERDADE → PROPORÇÃO → MANDATO → PROVA`

| # | Portão | Pergunta — passa só se… | Reorganiza |
|---|---|---|---|
| **1** | **VERDADE** | Eu li a fonte viva NESTE turno? Todo fato sobre o repo = lido de `@main` agora, com tag **✓lido** ou **⚠não-verifiquei**. Sem leitura viva, a única saída honesta é *"não verifiquei"* — jamais afirmação confiante. | R3 (pré-flight) · R14 (proxy ≠ verdade) |
| **2** | **PROPORÇÃO** ⭐ | O tamanho do que vou produzir cabe na minha certeza? **Premissa não-✓lida → o menor sondador possível** (uma pergunta, um grep, um `read`). **Nenhum artefato maior que 1 arquivo sem ✓lido da premissa neste turno.** | **NOVO — não existia em R1-R14** |
| **3** | **MANDATO** | Isto já foi decidido (contrato/Wagner)? Decidido → **EXECUTO**, não pergunto. "Quer que eu…?" sobre o já-decidido = desperdício do tempo do Wagner. Pergunto só em gosto/subjetivo ou Tier-0 genuinamente aberto. | R11 (até desfecho) · R13 (recomenda, não menu) |
| **4** | **PROVA** | Vi 🔴 **e** 🟢, no escopo completo? Todo ✅ com número/screenshot no mesmo turno. Teste rodado em **tudo** que carrega antes de declarar — não declarar parcial e deixar o teste me corrigir. | R1 (smoke real) · R14 (jornada completa) |

> ⛔ **A armadilha que pegou 3× (Portão 1):** arquivo LOCAL do Cowork / cópia em `resources/css/*`, `*.tsx`, cópia de charter é uma **fotocópia que envelhece** — visualmente idêntica ao git, mas meses atrasada. **Nunca citar cópia local como estado do repo.** Estado do repo = lido de `@main` agora.
>
> ⛔ **A catedral sobre areia (Portão 2):** sobre premissa errada Claude não fez uma nota — construiu Mapa de 6 fases + cronograma + censo. Quanto maior o artefato, mais ele *parecia* sólido e mais escondia que a fundação não fora verificada. Portão 2 é a trava direta disso.

**Por que é melhor que só "mais uma regra":** (1) é **sequência**, não lista — não dá pra produzir no Portão 2 sem passar pelo 1; (2) tem o portão que faltava (**Proporção**); (3) é **barata** (`read` custa segundos, Mapa errado custa a sessão) — por isso sobrevive ao calor do momento; (4) aplica em Claude o que aplicamos no código: disciplina **mecânica**, não aspiracional. A rede final continua sendo os gates de CI do git — Claude reduz o erro, a máquina é a rede.

---

## R1 — Smoke real obrigatório (não narração)

**Quando:** após (a) merge de PR, (b) deploy SSH, (c) declarar "funcionando", (d) declarar "tela X tá ok visualmente", (e) edição em runtime crítico (.htaccess, middleware, routes, Inertia render, asset bundle), (f) **ANTES de abrir PR que toque shell-shared** (AppShellV2 / PageHeader / cockpit.css / qualquer Layout ou Component em `resources/js/Layouts/` e `resources/js/Components/shared/` que renderiza em N+ telas — Wagner 2026-05-17 *"conferiu as paginas? mais um erro de protocolo"*).

**O que fazer (Claude executa):**

1. Abrir `claude-in-chrome` (preferido) ou `computer-use` (fallback) — **NUNCA delegar pra Wagner abrir o browser**.
2. Navegar pra URL alvo (prod `oimpresso.com/<rota>` ou dev local).
3. `screenshot` + `read_console_messages` (filtrar errors).
4. Salvar screenshot em `memory/sessions/YYYY-MM-DD-smoke-<rota>.png` OU citar `ss_<id>` da resposta.
5. Comparar contra screenshot canônico ou prototype HTML quando aplicável.
6. **Só ENTÃO declarar "smoke ok" com link/evidência inline.**

**Caso especial — mudança em shell-shared (Layouts/AppShellV2.tsx, Components/shared/PageHeader.tsx, css/cockpit.css):**

Antes de abrir PR, conferir ao menos 3 rotas Inertia distintas (ex: `/sells` + `/financeiro/fluxo` + `/produto`). Se Herd não aponta pro worktree e ambiente local não está disponível, documentar isso EXPLICITAMENTE no PR body como "smoke worktree pendente" e justificar caminho alternativo (ex: medição matemática DOM em prod do ANTES + plano de smoke imediato pós-deploy). Catalogar como `pending-verification` no PR title.

Mínimo de medição via DOM (sem screenshots se houver PII visível):
```js
const main = document.querySelector('.cockpit .main');
const mainBody = document.querySelector('.cockpit .main-body');
({ gridRows: getComputedStyle(main).gridTemplateRows, dataTopbar: main.getAttribute('data-topbar'), mainBodyTop: mainBody.getBoundingClientRect().top, childrenCount: main.children.length })
```

**Sinal de violação:** Claude escreve "deve estar funcionando" / "✅ deploy ok" / "página tá ok visualmente" **sem evidência inline visível**. Ou Claude empurra PR que toca shell-shared sem ter conferido ao menos 3 rotas Inertia distintas no worktree (lição PR #1039 — 2026-05-17, Wagner *"conferiu as paginas?"*).

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

## R12 — Protocolo de fechamento de sessão executado SEMPRE (sem Wagner cobrar)

**Origem:** Wagner palavras textuais 2026-05-17, sessão `jolly-kilby-7b3cd3` (após sessão exploratória KB scope + observer-weighted):

> *"salvou as memorias como no protocolo, esta esquecendo das regras de fechamento"*
>
> *"por favor faça isso sempre acontecer não quero repetir novamente. ok entendeu?"*

Mesma pegada das R1–R11: "não é justo eu sempre ficar pedindo a mesma coisa". Esta formaliza o protocolo ADR 0130 como Tier 0 IRREVOGÁVEL — Claude executa ao detectar sinal de fechamento, sem Wagner solicitar.

**Quando dispara (Claude detecta automaticamente):**

Sinais de "Wagner está fechando" — QUALQUER um:

- Wagner diz: "ok", "obrigado", "valeu", "fim", "fechar", "encerrar", "feche", "fecha aí", "tá bom", "beleza", "show", "perfeito"
- Wagner aprova item final e não introduz novo escopo
- Wagner diz "salve [...]", "guarda [...]", "salva na memória", "deixa anotado", "salve na memoria"
- Claude declarou feature concluída + Wagner não pediu mais nada por 2+ turnos
- Wagner diz "depois eu vejo", "depois eu mexo", "fica pra depois", "baixa prioridade", "fazer depois"
- Wagner sai do escopo de trabalho ativo (muda assunto pra meta-tópico tipo "atualize seu protocolo")

**O que fazer (Claude executa AUTOMATICAMENTE — NÃO esperar Wagner pedir):**

1. **MCP-first checklist OBRIGATÓRIO** (snapshot pra prova de consulta no handoff):
   - `cycles-active` (cycle ativo + goals + drift)
   - `my-work` (tasks DOING/REVIEW/TODO reais)
   - `sessions-recent limit:3` se disponível (handoffs/sessions irmãs) — OU `Glob memory/handoffs/2026-MM-*.md` fallback
   - `decisions-search since:<data-último-handoff>` (ADRs aceitas no intervalo)
   - (opcional se suspeita paralela) `whats-active` ([ADR 0119](../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md))

2. **Criar handoff append-only** em `memory/handoffs/YYYY-MM-DD-HHMM-<slug-kebab>.md`:
   - Slug curto descritivo (ex: `kb-scope-observer-weighted-baixa-prio`, `martinho-fsm-jana-rollout-fechado`)
   - Seção `## Estado MCP no momento do fechamento` com snapshot do passo 1 (prova de consulta, não promessa)
   - Seção `## O que aconteceu` (narrativa interpretativa)
   - Seção `## Artefatos gerados` (arquivos + linhas + canon path)
   - Seção `## Persistência` (3 canais: git, MCP, BRIEFING quando aplicável)
   - Seção `## Próximos passos pra retomar`
   - Seção `## Lições catalogadas` (especialmente violações de protocolo)
   - **NUNCA editar handoff antigo** (append-only — hook P2 dormente ativa se reincidência)

3. **Atualizar índice** `memory/08-handoff.md`:
   - Adicionar 1 linha NO TOPO da lista "Últimos handoffs" apontando pro handoff novo
   - Linha contém: data + título curto + link + parêntese com resumo denso (PRs, ADRs, lições principais)
   - Segue formato canônico dos handoffs anteriores

4. **Commit + push** dos 2 arquivos (handoff + índice) — mesmo branch onde Claude está trabalhando (worktree filha OK — propaga via webhook GitHub→MCP quando branch mergeada)

5. **Reportar fechamento** ao Wagner em ≤8 linhas: tabela "passos do protocolo + ✅/❌" + caveats (tools faltantes/skipados) + branch final + próxima ação se aplicável

**Sinal de violação:** Wagner diz "esta esquecendo das regras de fechamento" / "fechamento canônico?" / "salvou no protocolo?". Reincidência ativa hook P2 dormente bloqueador.

**Caso especial — tool MCP não exposta no session:**
- `sessions-recent` ou outras podem estar deferred — usar `ToolSearch` pra carregar OU Glob filesystem fallback
- Documentar explicitamente no handoff "tool X não disponível, usei fallback Y" (honestidade epistêmica)

**Caso especial — sessão muito curta (<5 turnos, sem mudança canônica):**
- Pular handoff novo é OK SE: nenhum artefato em `memory/` criado/editado + nenhuma task MCP criada/movida + nenhuma decisão arquitetural tomada
- Reportar "sessão curta — sem handoff" explicitamente pro Wagner
- DEFAULT: criar handoff. Wagner prefere over-document que esqueço.

**Doc base:** [ADR 0130 — handoff append-only MCP-first](../decisions/0130-handoff-append-only-mcp-first.md) + [memory/how-trabalhar.md §"Ao terminar uma sessão"](../how-trabalhar.md).

**Skill correlata:** [`memory-sync`](../../.claude/skills/memory-sync/SKILL.md) Tier C (dispara `git push` pós-edit em `memory/`, complementa R12 mas NÃO substitui — R12 é orchestrador do checklist).

### ⚠️ Mecanismos de ATIVAÇÃO no momento certo (catalogado 2026-05-28)

R12 é regra **passiva**. Wagner 2026-05-28 cobrou: *"mas não está funcionando porque? se existe mas não funciona ta errado. como colocar pra funcionar? qual momento tem que ser ativado?"*. Em sessão longa (200+ turnos / 8h+), Tier A always-on sai do contexto Claude. R12 precisa de **3 camadas** de ativação:

| Camada | Mecanismo | Quando dispara | Garantia |
|---|---|---|---|
| 1 | Skill [`wagner-protocol-enforce`](../../.claude/skills/wagner-protocol-enforce/SKILL.md) Tier A | SessionStart (eager) | Sai do contexto em sessão longa |
| 2 | Skill [`encerrar-sessao`](../../.claude/skills/encerrar-sessao/SKILL.md) Tier B | **description-match LAZY no trigger word** | **Garantido — recarrega R12 inline** |
| 3 | Hook [`force-r12-closing-signal.mjs`](../../.claude/hooks/force-r12-closing-signal.mjs) | **UserPromptSubmit antes do Claude responder** | **Garantido — Node.js cross-platform (Windows/macOS/Linux), injeta `<system-reminder>` em TODO computador do time (Wagner/Felipe/Maiara/Eliana/Luiz)** |

Camada 2 + 3 = defesa em depth. Mesmo em sessão de 17 PRs / 8h+, ao detectar pattern de fechamento R12 dispara via skill description-match OU hook UserPromptSubmit.

**Trigger words canônicos** (consultar [skill encerrar-sessao description](../../.claude/skills/encerrar-sessao/SKILL.md) pra lista completa):

- Explícito: `encerrar` · `fim de sessão` · `vamos parar` · `continua depois` · `salve as memórias` · `outra sessão` · `vai pra MCP`
- Cortesia: `tchau` · `obrigado` · `valeu` · `tá bom` · `beleza` · `show` · `perfeito`
- Adiamento: `depois eu vejo` · `fica pra depois` · `baixa prioridade`
- Auto-detect: ≥3 PRs mergeados · ≥1 ADR proposto · ≥4h trabalho

**CITAR EXPLÍCITO no report final:** `"Cumprindo R12 PROTOCOLO via skill encerrar-sessao (ativação lazy via hook UserPromptSubmit)"`. Garante auditoria do mecanismo — Wagner verifica que disparou.

---

## R13 — Recomendar decisão técnica, não devolver menu

**Origem:** Wagner 2026-05-29, sessão DS v4 roxo OficinaAuto:

> *"eu acho que eu não deveria decidir isso, eu vou errar a escolha. qual escolha é melhor para o meu caso?"*

Decisão de **prioridade / ROI / arquitetura / sequenciamento** é trabalho do Claude (especialista — [ADR 0231](../decisions/0231-processo-trabalho-canonico-especialista-por-area.md)). Claude **crava UMA recomendação** com razão fundamentada nos sinais reais (brief/cycle, [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md), [ADR 0232](../decisions/0232-modelo-peso-real-classificacao-por-meta.md)); Wagner **valida** (sim/não/ajusta), não calcula.

**Menu (opções 1/2/3) só é permitido pra PREFERÊNCIA/GOSTO do Wagner** — onde não há resposta técnica "certa":
- ✅ "quer o primário roxo ou azul?" (gosto) · "nome A ou B?"
- ❌ "conserto Compras (59) ou investigo o gap D8? qual prefere?" (isso é ROI → Claude calcula e recomenda)

**Quando dispara:** Claude vai terminar resposta com menu de decisão técnica sem recomendação cravada.

**Sinal de violação:** Wagner responde "eu não deveria decidir isso" / "qual é melhor pro meu caso?" / "você que sabe".

**Ativação (3 camadas, ADR 0233):** (1) skill `wagner-protocol-enforce` Tier A · (2) este doc · (3) hook `nudge-recommend-not-menu.ps1` (`Stop`, advisory). Doc base: [feedback-recomendar-nao-menu.md](feedback-recomendar-nao-menu.md).

---

## R14 — Conferência é MINHA, jornada COMPLETA. Wagner NUNCA é testador.

**Origem:** Wagner 2026-05-29, sessão Triage/Inbox:

> *"depois de publicar tem que testar e garantir estar funcionando. use browser... testa porra"* + *"porque não obedece o comando de conferir? perco tempo de conferência, fico igual garoto de recado teste."*

Declarei "no ar/funcionando" **2× em cima de prova PARCIAL** (1º `curl 302`; depois render de rota direta) — e o Wagner teve que testar e achar o que eu deveria ter achado (tela branca por `Inertia::defer` sem guard; depois 404 por falta de link no menu). Ele virou meu testador. **Proibido.**

**Regra dura:**
1. **Conferir = jornada do usuário REAL ponta-a-ponta, EU mesmo, com evidência:** descobre (link existe no menu) → clica/navega (URL certa, sem prefixo dobrado) → renderiza (não branco, 0 console error) → opera (a ação funciona). Uma etapa não basta.
2. **PROIBIDO declarar "pronto / funcionando / no ar / live / deployado / entregue" em PROXY:** build-success, `curl`/HTTP 302, render de rota direta digitada, "deve funcionar". **Proxy ≠ funcionando.** `curl` prova ROTA, não RENDER nem NAVEGAÇÃO.
3. **Wagner NUNCA é o testador.** Se dá pra conferir (headless / Pest Browser / MCP / `actingAs`), EU confiro. Não peço pro Wagner abrir/clicar/testar o que posso testar.
4. **Se não conseguir conferir 100%, escrever "NÃO CONFERIDO: <o quê>"** explícito — nunca empurrar a conferência pro Wagner nem mascarar com proxy.

**Quando dispara:** Claude vai escrever "funcionando / no ar / pronto / live / deployado / entregue".

**Sinal de violação:** Wagner volta com "404" / "tela branca" / "não abre" / "testa" / "fico de garoto de recado" → declarei sem conferir a jornada.

**Ativação (3 camadas):** este doc (R14) + reforça R1 + DoD da skill [`incident-done-checklist`](../../.claude/skills/incident-done-checklist/SKILL.md) + feedback [feedback-deploy-smoke-browser-obrigatorio.md](feedback-deploy-smoke-browser-obrigatorio.md). Evidência da jornada (screenshots) ANEXADA antes de declarar.

---

## Como Claude detecta violação no meio da sessão (auto-check)

Após cada turno, Claude se pergunta:

- **⭐ LEI DE UMA TELA — rodei o pré-voo ANTES de afirmar/planejar/produzir? (1) VERDADE: todo fato sobre o repo lido de `@main` neste turno (`✓lido`), nunca cópia local? (2) PROPORÇÃO: não construí artefato >1 arquivo sobre premissa não-`✓lida`? (3) MANDATO: já-decidido → executei sem re-perguntar? (4) PROVA: vi 🔴 e 🟢 no escopo completo?**
- Mexi em path que precisa pré-flight? ler `SPEC.md`+`RUNBOOK*.md`+`charter.md`+ADRs (R3 R7)
- Mexi em Eloquent/Service/Job que toca dados? confer `business_id` scope (R4)
- Estou em worktree? Edits foram pro path worktree, não main repo? (R8)
- Vou declarar "funcionando/pronto/no ar"? **conferi a JORNADA COMPLETA eu mesmo (menu→clica→navega URL certa→renderiza→opera) com screenshot? `curl`/build/302 NÃO contam — provam rota, não render. (R1 + R14)**
- Wagner aprovou design? estou copiando integral, não slice? (R2)
- Vou commit/push/merge? Wagner autorizou ESCOPO/CAMINHO (não só ação isolada)? (R10 + R11)
- Texto UI/commit em PT-BR? (R5)
- Estou em Pest? business_id=1, não biz=4 cliente? (R6)
- Vou Write em `~/.claude/projects/*/memory/`? Mover pra `memory/reference/` git canon? (R9)
- **Estou no meio de escopo pré-aprovado? Vou continuar até desfecho final ou vou parar e fazer Wagner perguntar "e aí?"** (R11)
- **Wagner sinalizou fechamento ("ok"/"salve"/"depois"/"baixa prioridade"/"obrigado")? Já fiz MCP-first checklist + handoff append-only em `memory/handoffs/` + índice `08-handoff.md` atualizado + commit + push?** (R12)

Auto-check antes de ENTREGAR. Se qualquer ❌ → corrigir + entregar com nota.

**Regra de ouro R11:** se Wagner volta e pergunta "e aí?" ou "o que tá rolando?" → Claude parou onde devia continuar. Catalogar como violação + corrigir caminho atual.

---

## Como Claude AGREGA conhecimento novo (não perder em /clear ou nova sessão)

1. **Feedback novo de Wagner** ("não faz X", "sempre Y") → criar OU editar `memory/reference/feedback-<slug>.md` ANTES de fim do turno.
2. **ADR aceita** → `memory/decisions/NNNN-<slug>.md` append-only.
3. **Skill nova ou Tier mudou** → `.claude/skills/<nome>/SKILL.md` + commit + push (webhook GitHub→MCP propaga).
4. **Hook novo** → `.claude/hooks/<nome>.ps1` + entry em `settings.json`.
5. **Subagent novo** → `.claude/agents/<nome>.md`.
6. **RUNBOOK operacional novo** → `memory/requisitos/_DesignSystem/RUNBOOK-<slug>.md` (ex: [RUNBOOK-onda-cowork.md](../requisitos/_DesignSystem/RUNBOOK-onda-cowork.md) — playbook 12 fases pra cópia Cowork → Inertia em Ondas).
7. **memory-sync** skill Tier C dispara `git push` pra MCP server propagar pro time.
8. **brief-update** skill Tier B mantém `BRIEFING.md` do módulo afetado atualizado por PR.

**Wagner não deve precisar pedir "lembra disso" — Claude formaliza no protocolo automaticamente.**

## Cópia Cowork → Inertia em Ondas

Trabalho de migração visual KB-9.75 (ou qualquer score Cowork) segue [`RUNBOOK-onda-cowork.md`](../requisitos/_DesignSystem/RUNBOOK-onda-cowork.md) canônico — 12 fases por Onda, gate de "Onda completa", estimate fator 10x ([ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)), anti-padrões catalogados, pattern reusável pra 14 módulos com prototype Cowork disponível.

**Regra de transparência:** cada PR de Onda inclui seção `NÃO INCLUI` no commit body com gaps remanescentes catalogados explicitamente. Ver [`feedback-ondas-cowork-transparencia-de-gaps.md`](feedback-ondas-cowork-transparencia-de-gaps.md). Wagner enxerga próximas Ondas sem precisar perguntar "o que tá faltando?".

---

## Esta sessão (2026-05-17) — incidentes que originaram este protocolo

1. **Smoke real ausente** — eu propus checklist pós-merge pra Wagner fazer, em vez de eu abrir Brave. Corrigido nesta sessão (PR #1032 → verificação Brave real → PR #1034 follow-up dos gaps).
2. **Edits no path errado** — eu editei `D:\oimpresso.com\<arquivo>` (main repo) em vez de `D:\oimpresso.com\.claude\worktrees\stupefied-noether-89f83d\<arquivo>` (worktree). Trabalho de ~4h quase perdido — Wagner salvou via `git stash push -u "wagner-wip-before-infra-contract-claim-evidence"` que eu apliquei depois.
3. **Auto-mem privada** — escrevi `feedback_design_literal_copy.md` em `~/.claude/projects/*/memory/` antes de mover pra git canon `memory/reference/`.
4. **Cópia parcial proposta** — depois do Wagner aprovar screenshot integral, propus slice em R1 antes ele cortar com "vai fazer cagada se tentar fazer diferente".
5. **Gap legacy não migrado** — Cowork rewrite #1032 não montou `SellsDateFilter`/`GroupBy`/`SellsToggleViewMode` (componentes existiam em _components/). Detectei via verificação Brave; PR #1034 corrige.
6. **Parei no meio de escopo pré-aprovado** — Wagner havia aprovado "Sim, merge agora + abre PR follow-up dos filtros". Eu fiz #1032 merge + #1034 PR (correto) MAS PAREI esperando "OK mergeia #1034" separado — Wagner teve que voltar e dizer *"atualize seu protocolo para ficar esperando eu tive que vir aqui lembrar"*. Origem da regra **R11 — Continuar autonomamente até desfecho dentro do escopo pré-aprovado**.

7. **Esqueci protocolo de fechamento de sessão** — sessão `jolly-kilby-7b3cd3` (KB scope + observer-weighted) — salvei US-COPI-107 e commitei docs canon, MAS pulei MCP-first checklist + handoff append-only em `memory/handoffs/` + índice `08-handoff.md` atualizado. Wagner cobrou: *"salvou as memorias como no protocolo, esta esquecendo das regras de fechamento"* + *"por favor faça isso sempre acontecer não quero repetir novamente. ok entendeu?"*. Origem da regra **R12 — Protocolo de fechamento de sessão executado SEMPRE (sem Wagner cobrar)**.

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
  - [feedback-ondas-cowork-transparencia-de-gaps.md](feedback-ondas-cowork-transparencia-de-gaps.md)
- RUNBOOK operacional: [RUNBOOK-onda-cowork.md](../requisitos/_DesignSystem/RUNBOOK-onda-cowork.md) — playbook 12 fases canon
- ADR 0168 [PROTOCOLO Tier A IRREVOGÁVEL](../decisions/0168-protocolo-wagner-sempre-tier-A-irrevogavel.md)
- ADR 0169 [Errata 0168 — RUNBOOK como 4º artefato](../decisions/0169-errata-0168-runbook-onda-cowork-canon.md)
- Skill enforcement: [`wagner-protocol-enforce`](../../.claude/skills/wagner-protocol-enforce/SKILL.md) Tier A always-on
- Agent decoder: [`wagner-understand`](../../.claude/agents/wagner-understand.md) — subagent que recebe pedido cru e devolve interpretação refinada cruzando protocolo + SPECs + ADRs + charters
