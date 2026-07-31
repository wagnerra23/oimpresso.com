# TEAM.md — Equipe oimpresso e atribuição de tasks

> **Pra quem é:** todo agente IA (Claude/Cursor/outro) que vai sugerir/atribuir tarefas E todo humano do time que vai pegar uma US via tool MCP `tasks-list owner:NOME` ou `my-work`.
>
> **Regra base:** toda task tem dono **antes** de virar ativa. Sem dono = fica em triage (`triage` tool) ou backlog. Donos só puxam tasks compatíveis com seu perfil (matriz §3).
>
> **Source-of-truth tasks (ADR 0070):** hierarquia Project → Epic → Cycle → Story → Subtask em tabelas `mcp_*` no MCP server. SPECs canônicos em `memory/requisitos/<Mod>/SPEC.md` (formato `### US-XXX-NNN`) → cache `mcp_tasks` via webhook. Mutação via tools MCP (`tasks-list`, `tasks-detail`, `tasks-create`, `tasks-update`, `tasks-comment`, `cycles-*`, `epics-*`). **`CURRENT.md`/`TASKS.md` REMOVIDOS** em 2026-05-04.

---

## 1. Perfis (5 pessoas)

### Wagner [W] — Líder / Administrador
- **Responsabilidade primária:** decisões estratégicas (roadmap, ADRs, posicionamento), comercial, contato com cliente focal (Larissa do ROTA LIVRE)
- **Pode mexer em:** qualquer arquivo do repo, mas idealmente delega execução
- **Não deve fazer:** tasks de execução pura quando equipe consegue (gargalo do líder)
- **WIP máximo:** **2 tasks ativas** (1 estratégica + 1 técnica). Acima disso vira gargalo do projeto inteiro
- **Hora ativa:** alta agilidade — pode fechar tasks técnicas em <1 dia
- **Decisão final em:** ADRs, política de eval, posicionamento, cobrança, deploy produção

### Maiara [M] — Suporte + Desenvolvimento
- **Responsabilidade primária:** suporte direto a clientes ativos (ROTA LIVRE / Larissa em primeira linha) + dev de complexidade média
- **Pode mexer em:** Cms, Financeiro (módulo, tela, view), UI Inertia, suporte triage
- **Não deve fazer:** decisões de arquitetura, ADRs, sprints de Copiloto LGPD-críticos
- **WIP máximo:** **2 tasks ativas** (1 suporte + 1 dev típico)
- **Hora ativa:** média — fecha task de complexidade média em 1-3 dias
- **Decisão final em:** suporte tier 1, refactor de UI dentro do padrão Chat Cockpit (ADR 0039)

### Felipe [F] — Desenvolvedor + Suporte (backup)
- **Responsabilidade primária:** dev de complexidade alta (Copiloto sprints 7-9, infra, integrações), backup de suporte
- **Pode mexer em:** qualquer módulo técnico, prefere Copiloto/Infra/Integrations
- **Não deve fazer:** decisão final de ADR (propõe e Wagner aprova), suporte cliente direto exceto urgência
- **WIP máximo:** **2 tasks ativas** (1 sprint + 1 paralelo)
- **Hora ativa:** alta — pode fechar tasks complexas em 2-5 dias
- **Decisão final em:** implementação técnica dentro de ADR já aprovado, code review de Maiara/Luiz

### Luiz [L] — Suporte iniciante + Dev com IA pair
- **Responsabilidade primária:** triagem de suporte tier 1 + tasks dev **SIMPLES emparelhado com Claude/IA**
- **Pode mexer em:** frontend Inertia (Pages novas baseadas em padrão existente), copy/i18n, refactors guiados, blade views legadas, tests Pest simples
- **NÃO deve fazer (zona vermelha):**
  - Tasks com risco LGPD (PII redactor, golden set com dados reais)
  - Eval de IA (judge prompts, métricas)
  - Payment / cobrança (gateway, boleto CNAB)
  - Deploy SSH produção
  - Migrations destrutivas
  - ADRs novos
- **WIP máximo:** **1 task ativa** (com Claude/Cursor pareado)
- **Hora ativa:** baixa-média — fecha task simples em 2-4 dias com pair
- **Plano de evolução:** após 3 cycles bem (sem hotfix em prod por task dele), promove WIP=2 e libera tasks de complexidade média
- **Sempre revisado por:** Felipe ou Wagner antes de PR mergear

### Eliana [E] — Financeiro + NFSe + Boletos + Recorrência (Esposa Wagner)
- **Responsabilidade primária:** faturamento e cobrança da empresa oimpresso, ops financeiro, módulos Financeiro/NfeBrasil/RecurringBilling, validação de cliente WR2 (PontoWr2 — Eliana é cliente, não confundir com Eliana-time)
- **Aviso semântico:** o time tem **2 Elianas distintas** — **Eliana[E]** é a esposa do Wagner (time interno) e **Eliana-WR2** é a cliente (externa). Em commits/notas usar `Eliana[E]` interno e `Eliana(WR2)` externa
- **Pode mexer em:** Módulo Financeiro completo (tela, relatórios, configuração, boleto CNAB, gateway), Módulo NfeBrasil (NFSe/NF-e), Módulo RecurringBilling (recorrência/assinatura), CMS copy revisão, validação UX como usuária real
- **Não deve fazer:** Copiloto sprints técnicos (LGPD), ADRs, deploy produção
- **WIP máximo:** **1 task ativa** (financeiro principal + opcional 1 task IA-pareada)
- **Hora ativa:** baixa (compartilha agenda com financeiro real do casal/empresa)
- **Diferencial:** **única que vivencia o produto como usuária final** — feedback dela vale ouro pra UX. Owner canônica dos módulos fiscais/financeiros.

---

## 2. Capacidade do time por cycle (10 dias úteis = 2 semanas)

| Pessoa | WIP | Tasks/cycle realista | Carga horária assumida |
|---|---|---|---|
| Wagner [W] | 2 | 4-6 (2 estratégicas + 4 técnicas curtas) | 4-6h/dia |
| Felipe [F] | 2 | 5-8 (2 grandes + 3-6 médias) | 6-8h/dia |
| Maiara [M] | 2 | 4-6 (suporte contínuo + 2-3 dev) | 6-8h/dia |
| Luiz [L] | 1 | 2-3 (com pair Claude) | 4-6h/dia |
| Eliana [E] | 1 | 1-2 (foco financeiro + opcional IA) | 2-4h/dia |
| **TOTAL** | **8** | **16-25 tasks/cycle** | — |

**Pressuposto:** se for time funcionando bem, **20 tasks fechadas em 2 semanas é meta agressiva mas factível**. Se ficar abaixo de 12, gargalo está em capacidade individual ou WIP mal calibrado.

---

## 3. Matriz de quem-pode-fazer-o-quê

**Legenda:** ✅ owner · 🟢 pode pegar · 🟡 com supervisão · ❌ não pegar (risco)

> **✅ é plural.** Uma linha pode ter mais de um ✅ — são **co-donos**, ambos autoritativos pra aquele tipo de task. Não é ambiguidade a resolver no desempate: é co-ownership declarado. Medido em 2026-07-28 (`awk` contando ✅ por linha desta tabela): **15 das 24 linhas** têm 2+ ✅. A redação anterior dizia "owner típico", que lia como singular e contradizia 62% da própria tabela.
>
> **Alinhado com o schema canônico:** [`scripts/memory-schemas/spec.schema.json`](scripts/memory-schemas/spec.schema.json) define `owners` como **array** (`minItems: 1`), e o caso real `memory/requisitos/Ponto/SPEC.md` já usa `owners: [W, E]`. (Os schemas de charter/runbook/BRIEFING ainda usam `owner` string singular — divergência conhecida, ver §3.1.)
>
> **Fonte única de ownership por módulo = esta matriz.** [ADR 0070](memory/decisions/0070-jira-style-task-management-current-md-removed.md) §"TEAM.md · Continua canônico — perfis + WIP + matriz". Quem precisar do dono aponta pra cá, não restateia.

| Tipo de task | W | M | F | L | E |
|---|---|---|---|---|---|
| **Decisão / ADR** | ✅ | 🟡 | 🟢 (propõe) | ❌ | ❌ |
| **Copiloto sprints 7-9 (LGPD, eval, judge)** | 🟢 | 🟡 (acompanha) | ✅ | ❌ | ❌ |
| **Copiloto features comum (drivers, jobs)** | 🟢 | 🟡 | ✅ | 🟡 (pair) | ❌ |
| **PII redactor BR (LGPD)** | 🟢 | ❌ | ✅ | ❌ | ❌ |
| **Frontend Inertia (Page nova)** | 🟢 | ✅ | ✅ | 🟢 (pair) | 🟡 (UX feedback) |
| **Frontend Inertia (refactor existente)** | 🟢 | ✅ | 🟢 | ✅ (pair) | 🟡 |
| **Blade views legacy (UltimatePOS)** | 🟢 | ✅ | 🟢 | ✅ (pair) | ❌ |
| **CMS copy / blog / landing** | 🟢 | ✅ | 🟢 | ✅ (pair) | ✅ |
| **Modulo Financeiro (relatório, tela)** | 🟢 | ✅ | 🟢 | 🟡 (pair) | ✅ |
| **Modulo Financeiro (boleto CNAB, gateway)** | 🟢 | 🟡 | ✅ | ❌ | ✅ |
| **Módulo NfeBrasil (NFSe/NF-e)** | 🟢 | 🟡 | ✅ | ❌ | ✅ |
| **Módulo RecurringBilling (recorrência)** | 🟢 | 🟡 | ✅ | ❌ | ✅ |
| **PontoWr2 Tier A** | 🟢 | 🟢 | ✅ | 🟡 | ❌ |
| **MemCofre (UI evidência)** | 🟢 | ✅ | 🟢 | 🟢 (pair) | ❌ |
| **Suporte tier 1 (triage cliente)** | 🟢 | ✅ | ✅ (backup) | ✅ | ❌ |
| **Suporte tier 2 (incident, hotfix)** | ✅ | 🟢 | ✅ | ❌ | ❌ |
| **Validação UX (cliente final)** | 🟢 | ✅ | 🟡 | 🟡 | ✅ (chave) |
| **Cleanup workflows / YAML** | 🟢 | ✅ | ✅ | 🟢 (pair) | ❌ |
| **Deploy SSH Hostinger** | ✅ | 🟡 (supervisão) | ✅ | ❌ | ❌ |
| **Migration destrutiva (DROP, ALTER prod)** | ✅ | 🟡 | ✅ | ❌ | ❌ |
| **Eval / RAGAS / golden set** | 🟢 | 🟡 | ✅ | ❌ | ❌ |
| **Memory consolidation (skill)** | ✅ (aprova) | 🟡 | 🟢 | 🟡 (pair) | ❌ |
| **Pricing / cobrança / Stripe** | ✅ | ❌ | 🟡 | ❌ | ✅ |
| **Code review PR técnico** | 🟢 | 🟢 | ✅ | ❌ | 🟡 |

**Regras duras (não-negociáveis):**

1. **Luiz NÃO mergeia PR sozinho.** Sempre Felipe ou Wagner aprovam.
2. **Eliana[E] NÃO mexe em Copiloto sprints LGPD.** Risco regulatório.
3. **Maiara NÃO faz deploy produção sozinha.** Sempre supervisão Wagner ou Felipe.
4. **Wagner deve evitar virar bottleneck** — delegar code review pra Felipe quando puder.
5. **PIIs reais (CPF/CNPJ de clientes) NUNCA aparecem em PR ou commit.** Logs de teste com `[REDACTED]` mesmo em dev.

---

## 3.1 O campo `owner:` do frontmatter NÃO é fonte de ownership

> ⚠️ **Não leia `owner:` de `memory/requisitos/<Mod>/SPEC.md` como "quem é o dono do módulo".** Ele é boilerplate legado, não afirmação de posse — e quem o lê como posse fabrica um conflito que não existe.

**Recibo (medido 2026-07-28, varrendo os 91 `memory/requisitos/*/SPEC.md` de `origin/main`):**

| forma no frontmatter | SPECs | leitura honesta |
|---|---|---|
| `owner: wagner` | 46 | default pré-schema, idêntico em todos — não distingue módulo nenhum |
| *(campo ausente)* | 32 | nunca teve |
| `owner: [W]` | 6 | híbrido: chave velha, valor novo |
| `owners: [W]` | 4 | canônico |
| `owners: [W, E]` | 1 | canônico e **plural** (Ponto) |
| `owners:` (vazio) | 1 | viola `minItems: 1` do próprio schema |
| `owner: "[E] Eliana"` | 1 | texto livre (NFSe) |

Três sinais de que `owner: wagner` não é declaração de posse: **(a)** aparece em 46 dos 91 SPECs com o mesmo valor; **(b)** `wagner` é o vocabulário de [`charter.schema.json`](scripts/memory-schemas/charter.schema.json) (`wagner|felipe|…`), não o de [`spec.schema.json`](scripts/memory-schemas/spec.schema.json) (`W|F|M|L|E`), e a chave canônica de SPEC nem é `owner` — é `owners`; **(c)** varredura em `scripts/`, `.github/`, `.claude/`, `Modules/`, `app/` não achou consumidor desse campo pra decisão de ownership (o `owners` do `catalog-graph.mjs` é posse de **tabela por módulo**, outro eixo).

**Corolário — não normalize isso em massa.** O codemod que trocava `owner: wagner` → `owners: [W]` nos 52 SPECs legados já foi tentado e **reprovado** ([PR #4156](https://github.com/wagnerra23/oimpresso.com/pull/4156), lápide §5 2026-07-12 em [`memory/proibicoes.md`](memory/proibicoes.md)): tocar SPEC legado acorda gate diff-aware que o grandfather protegia. O caminho é **forward-only** (arquivo novo nasce certo) **+ oportunístico** (normaliza só quando trabalho real já vai tocar aquele arquivo e paga a dívida dele).

**Divergência conhecida, ainda aberta:** os 4 schemas usam 3 vocabulários e 2 cardinalidades — `spec` = `owners` array `W|F|M|L|E`; `briefing` e `runbook` = `owner` string `W|F|M|L|E`; `charter` = `owner` string `wagner|felipe|…`. Unificar é decisão [W] (ADR), não normalização silenciosa.

---

## 3.2 Dono por PROCESSO (cadeia de escalação) — **PROPOSTA, pendente de [W]**

> ⚠️ **Esta tabela ainda não é canon.** As colunas *Responde* e *Aprova* são **derivadas da matriz
> §3** (que é por *tipo de task*), não decididas. Nomear pessoa é ato do [W] — confirmar, trocar ou
> cortar linha por linha no review deste PR. Enquanto não for confirmada, vale a §3.
>
> **Por que existe:** a §3 responde *"quem pode pegar este tipo de task"*. Não responde *"quem
> responde quando este processo degrada"*. São perguntas diferentes, e a segunda não tinha fonte —
> por isso todo alarme sobe direto pro [W]. Origem: [W] 2026-07-31, textual — *"eu não consigo
> garantir o funcionamento integral do sistema como um todo e não enxergo como cobrar de cada parte
> do sistema a sua responsabilidade"*.

### A cadeia — cinco degraus

| Degrau | Quem | Faz o quê |
|---|---|---|
| **0 · máquina** | o gate | Primeiro respondente, sem humano. 34 barram merge, 29 avisam. É o degrau que deve engordar. |
| **1 · executor** | [L] [M] [F] [E] | Recebe o vermelho e conserta. Não decide se a regra faz sentido; cumpre. |
| **2 · dono** | [F] [M] [E] | Responde quando a **métrica do processo** cai ou o gate vive vermelho. **É o degrau que hoje não existe.** |
| **3 · aprovador** | [F] no técnico | Libera exceção: label de regressão consentida, code review, implementação dentro de ADR aceita. |
| **4 · soberano** | [W] | Merge, ADR canônica, promoção de gate, deploy, migration destrutiva, preço, produto. Não delega. |

### As 11 etapas do fluxo

| # | Processo | Executa | **Responde** | Aprova exceção | Derivado de |
|---|---|---|---|---|---|
| 1 | Pedido | [W] | [W] | [W] | §1 — decisões estratégicas |
| 2 | Contrato de tela (charter · casos · SDD) | [M] · [L] pareado | **[M]** ⚠️ | [F] | "Frontend Inertia (Page nova)" — sem linha própria |
| 3 | Design e Design System | [M] | **[M]** + [E] em UX ⚠️ | [W] | "Frontend Inertia" + "Validação UX" ✅[E] |
| 4 | Código de módulo | varia | **ver §3.3** 🔴 | [F] | só 5 dos 34 módulos têm linha |
| 5 | Teste e eval | [F] · [L] em Pest simples | **[F]** | [F] | "Eval / RAGAS / golden set" ✅[F] |
| 6 | Segredo e dado pessoal | [F] | **[F]** | [W] | "PII redactor BR (LGPD)" — única linha com dono exclusivo |
| 7 | Conhecimento canônico (ADR · SPEC · âncora) | [F] propõe | **[F]** | [W] | "Decisão / ADR" ✅[W], [F] propõe |
| 8 | Meta-governança (as máquinas que vigiam máquinas) | — | **sem dono** 🔴 | [W] | nenhuma linha na §3 |
| 9 | Merge | [W] | [F] revisa | [W] | "Code review PR técnico" ✅[F] |
| 10 | Deploy e smoke em produção | [W] · [F] | deploy **[W]+[F]** · smoke **sem dono** 🔴 | [W] | "Deploy SSH Hostinger" ✅[W]✅[F] |
| 11 | Registro e aprendizado | agente IA | **[W] aprova** | [W] | "Memory consolidation (skill)" ✅[W] |

⚠️ = derivado por proximidade, a §3 não tem linha exata · 🔴 = **órfã, exige decisão [W]**

**Regras duras que já limitam esta cadeia** (não são novas — vêm da §1 e da §3):

1. **[L] nunca passa do degrau 1.** Não mergeia sozinho, não pega LGPD, eval, cobrança, deploy nem
   migration destrutiva. Sempre revisado por [F] ou [W].
2. **[M] não sobe sozinha no deploy.** Produção exige [W] ou [F] junto.
3. **[E] não entra na Jana em sprints LGPD.** E é a única que usa o produto como cliente final —
   vale mais em UX do que em código.
4. **[W] não deveria ocupar o degrau 1.** Está na §1: *"não deve fazer tasks de execução pura quando
   a equipe consegue"*. Hoje ocupa, porque o degrau 2 está vazio.

### O que esta tabela NÃO resolve

Ela destrava as perguntas *quem responde*, *qual o risco de não agir* e *qual a prioridade*. **Não
muda a concentração.** Medido em 2026-07-31 (`git log --since=180.days`, repo completo, 5.998
commits alcançáveis): **96,7% dos commits** saem de uma identidade só, e **18 dos 34 módulos** têm
autor único em 180 dias. Esse número só cai quando outra pessoa efetivamente commitar — nomear dono
não commita por ninguém.

## 3.3 Dono por MÓDULO — o buraco medido

A §3 nomeia módulo em **5 das 24 linhas**: Financeiro, NfeBrasil/NFSe, RecurringBilling, PontoWr2 e
MemCofre (hoje SRS). Os outros **29 módulos vivos não têm dono declarado em lugar nenhum** — nem na
matriz, nem no `module.json` (que guarda `governance.bucket`, LGPD e retenção, mas **não** tem campo
de dono), nem no frontmatter dos SPEC (ver §3.1).

**Tentei derivar do git e não dá.** Varredura de autoria por módulo em 180 dias, excluindo a
identidade dominante e os bots, devolve sinal humano real em **um** módulo:

| Módulo | Autor humano distinto (180d) | Bate com a §3? |
|---|---|---|
| **NFSe** | ELIANAMARCELINOALVES (6 commits) | ✅ sim — "Módulo NfeBrasil (NFSe/NF-e)" ✅[E] |

Todo o resto fica entre 1 e 5 commits — ruído, não posse. **Conclusão honesta: git não deriva dono
de módulo neste repo.** A atribuição dos 29 é ato do [W], não inferência do agente. Deixar em branco
é mais correto do que preencher com palpite: dono inventado parece canon e é pior que ausente.

---

## 4. Convenção de identificação em commits / PRs / SPEC.md

Use **iniciais entre colchetes** sempre que mencionar dono:

```
[W]   Wagner
[M]   Maiara
[F]   Felipe
[L]   Luiz
[E]   Eliana (esposa, time interno)
[W+F] Wagner pareado com Felipe
[L+C] Luiz pareado com Claude
[F+C] Felipe pareado com Claude
[E+C] Eliana pareado com Claude (modo IA-assistido)
```

**Em commits:**
```
feat(copiloto): PII redactor BR regex CPF/CNPJ [F]
fix(financeiro): relatorio DRE coluna ordenada [M]
chore(memcofre): copy do botão "Anexar evidência" [E+C]
```

**Em ADR final / decisão registrada:**
- Autor declarado: sempre nome completo + papel (`Wagner Líder`, `Felipe Dev`)
- Aprovador: Wagner (default) — exceto se ADR delegada (raro)

---

## 5. Onboarding pra nova task (checklist mental)

Quando alguém vai pegar uma US (via `my-work` ou `tasks-list owner:NOME`):

1. Olhe a coluna "Pode pegar?" da matriz §3 — sua inicial está em ✅ ou 🟢?
2. Olhe seu WIP atual — está abaixo do máximo (§1)?
3. **Bloqueio?** Se task depende de algo de outro dono, marque ⛔ e mova pra On-deck até desbloquear
4. **Compreensão?** Se a task tem palavra que você não entende (ex.: "faithfulness", "shadow deployment"), pergunta antes de começar — NUNCA googla por 30 min sem checar com Wagner/Felipe
5. **Pair com Claude/Cursor?** Default sim pra Luiz e Eliana. Felipe/Maiara/Wagner usam quando quiser acelerar
6. **Definition of Done explícito?** Cada US no SPEC.md tem campo `Acceptance:` em 1 frase. Se a US que você puxou não tem, peça pro owner adicionar via `tasks-comment` antes de começar

---

## 6. Anti-padrões (não fazer no time)

- ❌ **Pegar 5 tasks de uma vez "pra fazer aos poucos"** — viola WIP, contexto switching come 20-40% do tempo (research)
- ❌ **"Vou só dar uma olhada nessa também"** sem mover do On-deck pro Active
- ❌ **Mergear PR sem code review por estar com pressa** — Wagner/Felipe fazem review same-day se for urgente
- ❌ **Pular daily-async** — `tasks-update US-XXX status:doing/done` toma 10s, não economiza nada não fazer
- ❌ **Luiz aceitar task fora da zona** verde dele "pra aprender" — aprender em PR de produção é ruim, melhor pair-program em task verde
- ❌ **Eliana[E] aceitar task técnica complexa "porque o Wagner pediu"** — defender o WIP=1 dela é responsabilidade do Wagner
- ❌ **Trabalhar em múltiplos cycles ao mesmo tempo** — só o cycle ativo, resto fica On-deck/Backlog

---

## 7. Quando o time cresce

Quando entrar 6ª pessoa, atualizar este arquivo. Padrão de novo perfil:

```
### Nome [Iniciais] — Papel principal / Papel secundário
- **Responsabilidade primária:** ...
- **Pode mexer em:** ...
- **Não deve fazer:** ...
- **WIP máximo:** N
- **Hora ativa:** baixa/média/alta
- **Decisão final em:** ...
```

E atualizar a matriz §3 + capacidade §2.

---

> **Frescor deste doc = derivado, não escrito aqui.** Rode `node scripts/governance/doc-freshness-score.mjs` — ele deriva a data do commit git que tocou o arquivo, que é o oráculo. Uma linha "Última atualização" mantida à mão apodrece calada (lei-mãe [ADR 0256](memory/decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md): *derivado+enforçado sobrevive; escrito+lembrado apodrece*) — e apodreceu: ficou 2,5 meses cravada em `2026-04-28` prometendo revisão em `12-mai-2026`.
>
> **Trilha de mudanças** (fato datado em passado, não afirmação em presente):
> - **2026-07-28** — legenda `✅` corrigida pra plural (co-donos) + §3.1 desarmando `owner:` de SPEC como falsa fonte de ownership. Não mexeu em quem é dono de quê: a matriz já declarava co-ownership em 15 das 24 linhas.
> - **2026-04-28** — criação inicial (Cycle 01, time de 5).
