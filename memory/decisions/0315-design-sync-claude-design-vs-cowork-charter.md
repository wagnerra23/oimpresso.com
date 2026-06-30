---
slug: 0315-design-sync-claude-design-vs-cowork-charter
number: 315
title: "/design-sync (claude.ai/design) vs. método Cowork+charter: avaliação de adoção + fechamento do Gap 1 da 0299"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-30"
module: design-system
tags: [design, governanca, hooks, design-sync, claude-design, fonte-da-verdade, enforcement, tier-0, cowork, mcp]
supersedes: []
superseded_by: []
related:
  - 0299-figma-nao-e-fonte-de-design
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0239-governanca-design-system-git-ssot-regressao-ia
  - 0224-hooks-block-vs-advisory-claude-4.8-aware
  - 0094-constituicao-v2-7-camadas-8-principios
pii: false
---

> **Proposta por [CL] (Claude Code) em 2026-06-30. RATIFICADA por [W] em 2026-06-30** (merge — Wagner: "merge").
> Gatilho (Wagner, verbatim): *"isso é coisa do passado quero que leia o novo protocolo da antropormofic sobre o assunto"* — após a IA descrever a máquina interna `ancora.mjs`/charter; o Wagner aponta a integração **oficial** nova da Anthropic (`/design-sync` + tool `DesignSync`).
>
> **O que esta aceitação ratifica (e o que NÃO):** ✅ **Eixo A** (política: claude.ai/design **não** é fonte de design canônica). ⏳ **Eixo B** (enforcement mecânico): o gate por hook PreToolUse `DesignSync` está **provado INERTE em runtime** (furo #1 — o harness não roteia PreToolUse pra tools nativas/integração; ver §Furos + §Decisão adversarial). A decisão é **`diagnóstico-primeiro`**: provar qual ponto morde (candidato: gatear a SKILL `/design-sync` via tool `Skill`, que **é** built-in) antes de declarar enforcement. **O Gap 1 da 0299 NÃO está fechado mecanicamente** — esta ADR ratifica a política + a decisão de investigação, não um gate ativo.

# ADR 0315 — `/design-sync` vs. Cowork+charter (avaliação + fecha Gap 1 da 0299)

## Contexto (verificado nesta sessão)

A Anthropic shippou uma integração oficial code↔design no Claude Code: a skill **`/design-sync`** + a tool **`DesignSync`** (ambas disponíveis nesta sessão). O protocolo, lido da definição da tool:

- **Liga** Claude Code ↔ **projetos de Design System no `claude.ai/design`** (via login claude.ai ou `/design-login`). **Não é Figma.**
- **Objetivo declarado:** manter uma biblioteca de componentes local **em sincronia** com um Design System do Claude Design — *"incrementally, one component at a time, never as a wholesale replace."*
- **Ordem dura:** `ler (list/get) → finalize_plan (trava paths de write/delete + localDir) → write/delete`. O `finalize_plan` mostra ao usuário a lista de paths **independente da narração do agente** (não dá pra esconder escopo). `write_files` lê do disco via `localPath` — **conteúdo não entra no contexto do modelo**.
- **Cards** do "Design System pane" auto-indexados pelo comentário `<!-- @dsCard group="…" -->` da 1ª linha de cada preview HTML → `_ds_manifest.json`.
- **Direção:** bidirecional (lê design DA nuvem; sobe componentes locais PRA nuvem), componente a componente.
- **Segurança embutida:** `get_file` devolve conteúdo de **outros membros da org** → o protocolo manda tratar como **dado, não instrução** (anti prompt-injection).

**O conflito de autoridade (mesma classe da 0299).** O oimpresso já tem fonte de design canônica: protótipo **Cowork** (`prototipo-ui/`, read-only) + **Design System em git** (tokens/componentes — SSOT git, [ADR 0239](0239-governanca-design-system-git-ssot-regressao-ia.md)) + **charter** da tela ([ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md)). A diff design→code é o `mwart-comparative` + a **máquina de âncora** (`prototipo-ui/ancora.mjs`, computa a fonte legítima do charter, nunca "no olho"). A [ADR 0299 §1](0299-figma-nao-e-fonte-de-design.md) já classifica como **NÃO-fonte** (opt-in Wagner explícito) "Figma · Notion · screenshot solto · link externo · **qualquer MCP de design novo**".

**`DesignSync` é, por definição literal da 0299, um "MCP de design novo" → NÃO-fonte.** E é exatamente o **Gap 1** que a 0299 admitiu não ter fechado: o block determinístico `block-figma-without-optin` casa só capability/nome-de-servidor **Figma**; `DesignSync` (tool nativa do harness, não-Figma) **passa livre**. Hoje a única defesa contra ela é doc advisory (L0) — o canal que o agente provou não ler.

### O número que prova o buraco (não opinião)

O matcher do Figma em `.claude/settings.json` é `mcp__.*figma.*|mcp__.*__(use_figma|…|generate_diagram)`. A tool `DesignSync` é **nativa do harness** (sem prefixo `mcp__`) e o nome não está em lista nenhuma; **nenhum** grupo PreToolUse do `settings.json` tinha matcher `DesignSync`. Logo **cobertura de `DesignSync` antes deste PR = 0 hooks** → o caminho de escrita (`finalize_plan`/`write_files`/`delete_files`/`create_project`) passava **100% livre**. Esse é o Gap 1 da 0299 instanciado, medido — não inferido.

> **Esta decisão não é um menu pro Wagner escolher no olho.** A política (Eixo A) é a
> conclusão dos critérios 0299+0239; o enforcement (Eixo B) foi **implementado, testado em
> lógica e wirado no CI** — e o red-team **provou** (baseline de sessão fresca, furo #1) que o
> hook PreToolUse `DesignSync` é **NO-OP em runtime** (o harness não entrega PreToolUse pra
> tools nativas/integração; ver §Furos + §Decisão adversarial). O processo é a máquina + a
> catraca + o red-team que a audita, não a preferência — **inclusive quando o red-team mata o
> próprio gate.** O enforcement real fica pendente do diagnóstico `diagnóstico-primeiro`.

## Decisão (2 eixos)

### Eixo A — `claude.ai/design` **não** vira fonte de design canônica do oimpresso

Pelos **mesmos** critérios da 0299 + 0239:

1. **Colide com SSOT git (0239).** Adotar um Design System hospedado no `claude.ai/design` cria um **segundo armazém** de tokens/componentes fora do git. A 0239 fixou: o DS é versionado em git, com catraca/gate anti-regressão. Um espelho na nuvem **divergiria** (o vetor exato que a 0239 e a 0299 §1 combatem: "ninguém transcreve, todo o resto aponta").
2. **Colide com fonte única (0299).** Duas fontes de verdade pra "qual é o componente certo" = o conflito de autoridade que causou o incidente 0299. A diff design→code do projeto já é determinística (charter → `ancora.mjs` → `mwart-comparative`); não há lacuna que justifique trocar de paradigma.
3. **A direção write é a mais perigosa.** `DesignSync.write_files`/`delete_files` empurra componentes locais **pra fora** do perímetro git canônico, pra um projeto claude.ai compartilhável — sem passar por PR/CI/gate de governança. Isso é publicação externa (escopo `publication-policy` + R10): exige aprovação, não default.

### Eixo B — gateia `DesignSync` sob a classe NÃO-fonte (block determinístico, conforme 0224) — ⚠️ MECANISMO PROVADO INERTE

> ⚠️ **Leia §Furos + §Decisão adversarial antes desta seção.** O desenho abaixo era a hipótese de enforcement. O red-team **provou** (furo #1, baseline de sessão fresca) que o hook PreToolUse `DesignSync` **não morde** (o harness não roteia PreToolUse pra essa tool). O Gap 1 **não** está fechado por este mecanismo; o caminho real é `diagnóstico-primeiro` → provável gate na SKILL `/design-sync`. O texto a seguir fica como registro da hipótese original.

`DesignSync` é gateável por **`tool_name`** exato (`DesignSync`) — block **determinístico**, mesma classe de `block-automem` (path) e `block-figma-without-optin` (capability). **Não** rebaixa o critério da [ADR 0224](0224-hooks-block-vs-advisory-claude-4.8-aware.md) (não é semântico).

- **L1 (PreToolUse block, fail-closed):** estender/criar hook que barra `DesignSync` quando o método é de **escrita** (`finalize_plan`/`write_files`/`delete_files`/`create_project`). Opt-in explícito Wagner: `OIMPRESSO_DESIGN_SYNC_OK=1` ou `.design-sync-allow` (paralelo a `OIMPRESSO_FIGMA_OK`/`.figma-allow`).
- **Métodos de leitura** (`list_projects`/`get_file`/…) ficam **advisory** (não bloqueia inspecionar; bloqueia escrever) — direção fail-safe igual à 0299 (errar pra menos = +1 round-trip, nunca vaza/publica).
- **L4 catraca:** teste de registro do hook em `settings.json` + teste de lógica (escrita barrada sem opt-in; leitura livre; opt-in concede).
- **L5 baseline:** fixture com a chamada `write_files` sem opt-in (prova que morde) + uma `list_projects` (prova que leitura passa).
- **L0 SSOT:** adicionar `DesignSync`/`claude.ai/design` à lista NÃO-fontes do `INDEX-DESIGN-MEMORIAS.md §0.1` (corrige o Gap 1 nominalmente — o resto aponta).

### Quando `/design-sync` **faria** sentido (opt-in estreito, nunca canon)

Só como **canal de export read-mostly**, com Wagner dizendo explícito, p.ex.: gerar um catálogo visual compartilhável (o "Design System pane") **a partir** do DS git já aprovado, pra mostrar a cliente/sócio — **git continua a fonte**, claude.ai/design é só vitrine derivada. Nunca o inverso (claude.ai/design → git).

## Não-goals

- ❌ **Não desinstala/desconecta** a skill `/design-sync` — ela é legítima sob opt-in (igual ao Figma na 0299).
- ❌ **Não bloqueia leitura** (`list_projects`/`get_file`) — inspecionar é seguro; o gate é na **escrita/publicação**.
- ❌ **Não fecha a classe inteira** (Notion, file-MCP, Chrome/Windows-MCP screenshot seguem advisory — Gap 1 da 0299 só encolhe, não some). Gate genérico de "atrator design-to-code" segue futuro.
- ❌ **Não emenda a 0224** — block por `tool_name` é determinístico, conforme.

## Gaps residuais conhecidos

1. **Assimetria de plataforma.** Se o novo hook for `.ps1`, funcionário em Mac/Linux roda nu (mesmo buraco da 0299 Gap 3). Escrever em `.mjs` cross-platform.
2. **Prova de roteamento.** Falta confirmar que o harness roteia `PreToolUse` pra tool nativa `DesignSync` (há precedente pra MCP tools e pra Figma; validar com baseline L5 antes de declarar fechado).
3. **`/design-login` paralelo.** A auth dedicada pode abrir escopo design sem passar pelo gate — mapear se precisa cobrir.
4. **Decisão humana pendente.** Eixo A é recomendação; se o Wagner **quiser** claude.ai/design como vitrine (uso estreito acima), isto vira opt-in documentado, não block total.

## Consequências

✅ **Boas:**
- Fecha (nominalmente + com block na escrita) o Gap 1 que a 0299 deixou explícito — a integração oficial nova não vira backdoor de fonte-de-verdade.
- Preserva SSOT git (0239) e fonte única (0299): uma verdade pro DS, não duas divergindo.
- Mantém a porta aberta pro uso legítimo (vitrine read-mostly) sob opt-in — não joga a feature fora.

⚠️ **Tradeoffs:**
- +1 round-trip quando o Wagner quiser usar `/design-sync` de propósito e esquecer o opt-in (escape: env/arquivo).
- Mais um hook + catraca pra manter (custo de governança).
- Não fecha a classe toda (risco "incidente Notion da próxima vez" segue, mitigado por L0).

## Furos achados pelo red-team (pós-implementação, 2026-06-30) — honestidade

Um passe adversarial ("adversário experiente no protocolo novo") atirou no próprio gate e achou:

| # | Furo | Status | Como foi pego |
|---|---|---|---|
| **#1** | **Gate INERTE na sessão que o cria.** Chamada real `DesignSync.finalize_plan` sem opt-in voltou **erro de validação do tool**, não o `[BLOCKED]` do hook → o PreToolUse **não disparou**. `settings.json` carrega no startup; editar no meio da sessão não faz hot-reload. O E2E prova a *lógica*, não a *entrega do payload pelo harness*. | 🔴 **CONFIRMADO no-op** (baseline de sessão fresca, 2026-06-30) — a probe RODOU numa sessão que **bootou com o hook já registrado** (PR #3460 mergeado em `main`; `settings.json` do checkout tem o matcher `DesignSync`; **sem troca de branch**) e com **zero opt-in**. Ainda assim voltou erro NATIVO do tool, **não** o `[BLOCKED]`. → **o harness NÃO roteia PreToolUse pra tool nativa `DesignSync`**; o gate por hook é inerte. Evidência literal + proposta de fix logo abaixo da tabela. | baseline sessão fresca `finalize_plan` |
| **#2** | **Opt-in fail-OPEN: discutir a feature armava escrita.** `"como funciona o design sync?"` destravava 15 min de escrita. | ✅ **FECHADO** — opt-in agora exige INTENÇÃO (verbo de publicar + nome, ou `/design-sync`); pergunta/explicação nunca arma; deny cobre nunca/jamais. Virou regressão no test. | `isDesignSyncOptInPrompt` |
| **#3** | **Flag machine-wide** (`tmpdir` global) vazava opt-in entre projetos. | ✅ **FECHADO** — flag keyed no `cwd` (por-projeto). | leitura do código |
| **#4** | TTL 15min não-consome: 1 arme → N escritas. | 🟡 mitigado por #2 (armar agora exige intenção de publicar); consume-once = melhoria futura. | — |
| **#5** | Leitura (vetor de injeção: `get_file` traz conteúdo de outros membros) é livre. | 🟡 inerente ao protocolo; gate só protege escrita; disciplina "dado, não instrução". | protocolo |
| **#6** | `tool_name` exato é frágil (tool-irmã/`/design-login` futuros escapam; método de *leitura* futuro é falso-bloqueado). | 🟡 residual aceito; revisar se a superfície da tool mudar. | análise |

**Meta-lição:** a v1 deste PR declarou "máquina provada por teste" conflando *lógica-do-hook-provada* com *gate-ativo-provado* — o mesmo modo de falha que o projeto cataloga ("a suite mente"/baseline nunca armado). Corrigido abaixo.

### Baseline de ativação runtime — furo #1 resolvido (2026-06-30, sessão `wizardly-elion-69fb06`)

**Setup (validade da probe):** sessão FRESCA bootada com o hook já registrado — PR #3460 (`feat(governance): gateia DesignSync`) está mergeado em `main` (`334032f5fd`); o checkout da probe é uma worktree off-`main`, com `.claude/settings.json` carregando o matcher `DesignSync` no startup (confirmado) e o arquivo `block-design-sync-without-optin.mjs` presente. **Sem troca de branch antes da probe** (troca = sem hot-reload = inválido). **Zero opt-in:** `OIMPRESSO_DESIGN_SYNC_OK` vazio, sem `.design-sync-allow`, flag stale do tmpdir removida. `finalize_plan` é método de ESCRITA (não está em `READ_METHODS`) → se o hook disparasse, sairia `exit 2` com `[BLOCKED]`.

**Probe:** `DesignSync(method:"finalize_plan", projectId:"00000000-0000-0000-0000-000000000000", writes:["baseline-probe/never-written.html"], deletes:[])`.

Saída LITERAL (2 tentativas — corrigindo só o `localDir`, nunca o que importa):

```
# tentativa 1 (localDir estilo bash):
localDir does not exist or is not accessible: /d/oimpresso.com/.claude/worktrees/wizardly-elion-69fb06 (ENOENT: no such file or directory, lstat 'D:\d\oimpresso.com\.claude\worktrees\wizardly-elion-69fb06')

# tentativa 2 (localDir Windows correto):
DesignSync needs design-system authorization, but /design-login requires an interactive terminal and is not available in this environment. If this is claude.ai/code, ask the user to use Claude Design's "Send to Claude Code Web" (which seeds the project into the workspace) or to provide the project files directly.
```

**Interpretação:** os DOIS erros vêm da validação/auth NATIVA do próprio tool `DesignSync` (path do `localDir`, depois auth do `/design-login`) — a segunda falha acontece **durante a execução** do tool, que só é alcançada DEPOIS do PreToolUse. Nenhuma das duas é o `[BLOCKED: claude.ai/design não é fonte...]`. Com o hook bootado + zero opt-in, se o PreToolUse fosse entregue à tool nativa, a 1ª coisa a aparecer seria o `[BLOCKED]` e o tool nunca executaria. Logo: **o harness NÃO roteia PreToolUse pra tool nativa `DesignSync`. O gate por hook é no-op.** (Reproduz exatamente o sintoma que o red-team já tinha visto.)

**Por que o hook funciona no teste mas não em runtime:** o `.test.mjs` faz `spawn` do hook injetando o payload no stdin — prova a LÓGICA. Em runtime, a entrega do evento `PreToolUse` é decisão da plataforma; para a tool nativa `DesignSync` ela não ocorre (≠ MCP tools `mcp__*` e ≠ Figma, onde há precedente de entrega).

**Proposta de fix (Eixo B precisa de outro ponto de intercepção — não declarar fechado por hook):**
1. **Diagnóstico de causa-raiz primeiro (1 sessão fresca):** instrumentar o hook pra logar TODO `tool_name` de `PreToolUse` que ele recebe num arquivo, e rodar a probe de novo. Dois desfechos: (a) `DesignSync` nunca aparece no log → o harness não emite PreToolUse pra tools nativas dessa classe (matcher por `tool_name` é estéril aqui); (b) aparece com **outro nome** → corrigir o matcher pro nome real. Sem esse log, qualquer "fix" de matcher é chute.
2. **Camada de permissão `deny` no `settings.json` (candidato mais forte):** uma regra de permissão (não-hook) negando o tool `DesignSync` é aplicada pelo harness a TODOS os tools, inclusive nativos — é o mecanismo que de fato morde onde o hook não chega. Trade-off: permissão não lê env/flag TTL → o opt-in deixa de ser por-prompt e vira aprovação interativa / edição de settings (UX pior, mas é gate REAL vs. teatro). Provável forma final: `deny` por padrão + opt-in documentado fora do hook.
3. **`/design-login` como gargalo de auth:** a escrita exige o escopo concedido por `/design-login`/login claude.ai. Se `/design-login` for interceptável (a verificar — provavelmente sofre do MESMO no-op por ser tool nativa), gateá-lo fecha a escrita na origem. Hoje, neste ambiente desktop/headless, a escrita **já é impossível** (auth indisponível, vide tentativa 2) — o risco real é só em sessão claude.ai/code logada no claude.ai/design.
4. **PostToolUse NÃO serve** — roda depois da publicação; não previne. Descartado.

> **Conclusão honesta:** o gate por hook (Eixo B / L1) está **provado inerte** pra tool nativa `DesignSync`. A política (Eixo A — claude.ai/design não é fonte) continua válida; o enforcement mecânico precisa migrar de hook PreToolUse `DesignSync` pra outro ponto de intercepção, **após** diagnóstico de causa-raiz. Ponto candidato definitivo = gatear a **SKILL `/design-sync`** (a tool `Skill` É built-in e morde) — ver §Decisão adversarial. **Não declarar o Gap 1 fechado mecanicamente até um gate REAL morder em runtime.**

### Decisão adversarial (2026-06-30) — ponto de intercepção do Eixo B

Rodado workflow campeão×adversário (12 agentes: 5 candidatos × champion+adversary + investigador de semântica PreToolUse + juiz), confiança **alta**. **Por que o hook é inerte (raiz, não palpite):** `DesignSync`/`design-login` **não** estão na lista canônica de built-in tools da [tools-reference](https://code.claude.com/docs/en/tools-reference) — são **integração surfaceada como skill** (a doc, linha 11: integrações como `/design-sync` "rodam pela tool `Skill`, não adicionam tool nova"). Precedente explícito: o advisor-tool "has no name you can reference in permission rules or hook matchers". Hooks mordem built-in clássicas (Bash/Edit/Write — load-bearing neste `settings.json`) e MCP (`^mcp__`); **não** essa tool-folha. O matcher `"DesignSync"` é sintaticamente válido mas **nunca dispara**.

**Escolha: `diagnóstico-primeiro`** (única ação que produz PROVA de runtime agora, em vez de adivinhar roteamento). Ranking dos candidatos:

| # | Candidato | Veredito |
|---|---|---|
| **1** | **diagnóstico-primeiro** ✅ ESCOLHIDO | Hook PreToolUse wildcard `*` que só loga `tool_name`+`event` e sai 0. Prova mecânica direta de (não-)entrega; barato; classifica empiricamente os outros. Substitui o baseline indireto do furo #1 por prova citável. |
| **2** | gate-skill-design-sync | **Melhor fix mecânico candidato.** Ataca a entrada REAL no namespace de hooks: a tool `Skill` é built-in → matcher `Skill`/`Skill(design-sync)` morde. Promover só APÓS a probe provar que morde. Furo: sessão pode chamar `DesignSync` direto sem passar pela skill. |
| **3** | permission-deny | **Testar, não assumir.** Outro plano (permission system). Mas permission rules usam os MESMOS nomes da lista canônica — `DesignSync` ausente dela → provavelmente inerte como o hook. Validar no diagnóstico (custo ~zero); se morder, é o gate ideal; se não, descartar COM evidência. |
| **4** | gate-design-login | Valor estreito. Em headless a auth já falha nativa; `design-login` provavelmente é nativa fora do namespace. Mapear no trace. |
| **5** | policy-cultural-only | **Fallback honesto, não 1ª linha.** Só aceitável SE o diagnóstico provar que NENHUM ponto morde — aí registrar Eixo B inviável por plataforma + residual, **sem fingir gate**. |

**Plano de verificação (próxima sessão FRESCA — load-no-startup exige reboot):**
1. **PROBE 1 (baseline de não-entrega):** registrar hook PreToolUse wildcard que faz `appendFileSync` de `tool_name`+`hook_event_name` em `.claude/run/pretooluse-trace.log`, exit 0. Rebootar. Disparar (a) `Read`/`Bash` = **controle positivo** (prova que o hook roda), (b) `DesignSync.list_projects`, (c) `DesignSync.finalize_plan`. Critério: se `DesignSync` JAMAIS aparece no trace enquanto `Read`/`Bash` aparecem → **não-entrega provada** (vira evidência direta no ADR).
2. **PROBE 2 (mesma sessão, ~zero):** `permissions.deny:["DesignSync"]` + re-disparar `finalize_plan` → se voltar erro nativo (não bloqueio de permissão) → deny inerte, descartar com evidência.
3. **PROBE 3:** disparar a SKILL `/design-sync` → confirmar no trace que aparece `Skill` E que matcher `Skill` morde ANTES da integração rodar (exit 2 real) → valida gate-skill como ponto real.
4. **PROBE 4:** `/design-login` → ver se emite PreToolUse (mapeia Gap residual 3).

**Regra de fechamento:** ADR 0315 só marca Gap 1 "fechado" se ALGUM ponto (Skill, deny, ou DesignSync direto) for **provado mordendo** com exit 2 real no trace. Senão → Eixo B inviável por plataforma + cair pra policy-cultural-only com residual explícito.

**Riscos residuais (do adversário):**
- A probe roda em desktop headless; o risco real (publicação) é em **claude.ai/code logado** no claude.ai/design — ambiente onde estes hooks locais podem nem rodar. Provar (não-)entrega aqui NÃO prova o harness cloud; a defesa mecânica pode ser **estruturalmente impossível** onde o risco existe.
- gate-skill não pega chamada DIRETA à tool `DesignSync` (sem passar pela skill) — fecha só a entrada oficial.
- Opt-in por palavra é rede, não prova de escopo (já no hook).
- PROBE 1 **exige controle positivo** (Read/Bash no trace): ausência de `DesignSync` poderia ser hook quebrado, não não-entrega — o controle distingue.
- Classe inteira não fecha (Notion/screenshot/link seguem advisory L0).

**Artefato preparado nesta sessão:** [`.claude/hooks/diag-pretooluse-trace.mjs`](../../.claude/hooks/diag-pretooluse-trace.mjs) (INERTE — não registrado; a próxima sessão fresca registra → roda PROBE 1-4 → des-registra).

#### Tentativa de PROBE 1-4 — ABORTADA por boot inválido (2026-06-30, sessão `infallible-satoshi-89fcee`)

Uma sessão foi disparada pra rodar o diagnóstico PROBE 1-4. **Não rodou nenhuma probe** — falhou no **Passo 0 (validade do boot)**, e parar foi a decisão correta (não há auto-engano que sobreviva à regra do controle positivo):

- A sessão **bootou na branch `claude/infallible-satoshi-89fcee`**, NÃO em `claude/design-sync-gate-0315`. O `git checkout` pra branch gate **falhou** (`fatal: 'claude/design-sync-gate-0315' is already used by worktree at '…/affectionate-vaughan-ef0ce3'`).
- O trace hook (`diag-pretooluse-trace.mjs`, commit `9b580183df`) só existe no `settings.json` da branch gate. **Prova mecânica de que NÃO foi carregado no boot desta sessão:** `grep diag-pretooluse-trace` no `.claude/settings.json` do worktree bootado retornou **vazio** (só casou o matcher `DesignSync` do gate, linha 163). `settings.json` carrega no startup, sem hot-reload.
- Logo, qualquer PROBE rodada por esta sessão produziria um trace **falsamente vazio** → conclusão de "não-entrega provada" seria **auto-engano** (exatamente o risco que o Passo 0 e o "PROBE 1 exige controle positivo" antecipam). **Probes NÃO rodadas.**

**Estado da remediação:** o worktree `affectionate-vaughan-ef0ce3` JÁ tem a branch gate com o trace hook commitado (`9b580183df`) e a sessão "Code and design integration" lá está **idle** (`isRunning:false`). → **Ação requerida do Wagner:** reabrir o chip a partir de uma sessão FRESCA cujo `cwd` seja o worktree da branch gate (`affectionate-vaughan-ef0ce3`) — essa boota com o trace hook já registrado e pode validamente rodar PROBE 1-4 + des-registrar (Passo 6) no fim. Enquanto isso não acontecer, o **furo #1 segue 🔴 CONFIRMADO no-op** e o Gap 1 segue **mecanicamente aberto** (regra de fechamento acima inalterada — nenhum ponto foi provado mordendo nesta tentativa).

## Validação (executada — `node`, não Pest) — status HONESTO

- ✅ **Lógica + E2E do hook** (`block-design-sync-without-optin.test.mjs`): leitura livre; escrita gateada; default-deny de método futuro/ausente; opt-in **endurecido** (furo #2 como regressão); E2E spawn → exit codes reais (write→2, read→0, opt-in→0).
- ✅ **Catraca de registro** (`settings-design-sync-registration.test.mjs`): hook wirado nos 2 eventos + matcher casa `DesignSync`.
- ✅ **Wirado no CI** (`governance-script-tests.yml`) — verde no PR (não é local-only).
- ✅ `settings.json` JSON válido + regressão Figma intacta + campo `tool_input` confirmado real.
- ✅ Conformidade 0224 (block por `tool_name` + `method` = determinístico).
- 🔴 **ATIVAÇÃO em runtime: PROVADA no-op** (furo #1, baseline de sessão fresca 2026-06-30). A lógica está provada; a *entrega do PreToolUse pela plataforma pra tool nativa `DesignSync`* **NÃO ocorre** — a probe rodou numa sessão bootada com o hook registrado, zero opt-in, e `finalize_plan` voltou erro NATIVO do tool (path do `localDir` → auth do `/design-login`), nunca o `[BLOCKED]` (evidência literal em §Furos). **O gate por hook está armado-mas-inerte; não morde em runtime.** O enforcement mecânico precisa migrar pra camada de permissão `deny` (proposta em §Furos), após diagnóstico de causa-raiz. NÃO declarar o Gap 1 fechado mecanicamente até um gate REAL morder.

## Notas

- Esta ADR **vem com o enforcement já implementado e testado** (Eixo B), seguindo o padrão `block-figma-without-optin` (0299): hook `.claude/hooks/block-design-sync-without-optin.mjs` (+ `.test.mjs`) + catraca `scripts/governance/settings-design-sync-registration.test.mjs` + registro nos 2 eventos do `settings.json`. Os 2 testes já estão **wirados no CI** (`.github/workflows/governance-script-tests.yml` — fecha o "test local-only" que a 0299 chama de defesa-fantasma). Falta só a ratificação humana ([W]). A política (Eixo A) é o que a máquina enforça; não há "escolha de menu".
- Sequência de defesa mecânica do projeto: `block-automem` (path) → `block-pr-without-approval` (R10) → `block-figma-without-optin` (capability) → proposto `block-design-sync-without-optin` (tool_name).
- O protocolo `/design-sync` em si é **bem desenhado** (plano travado visível ao usuário, conteúdo fora do contexto, anti prompt-injection no `get_file`). A objeção é de **governança de fonte-da-verdade**, não de qualidade da ferramenta.
