---
slug: 0315-design-sync-claude-design-vs-cowork-charter
number: 315
title: "/design-sync (claude.ai/design) vs. método Cowork+charter: avaliação de adoção + fechamento do Gap 1 da 0299"
type: adr
status: proposto
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

> **Proposta por [CL] (Claude Code) em 2026-06-30.** Ratificação formal = merge por [W].
> Gatilho (Wagner, verbatim): *"isso é coisa do passado quero que leia o novo protocolo da antropormofic sobre o assunto"* — após a IA descrever a máquina interna `ancora.mjs`/charter; o Wagner aponta a integração **oficial** nova da Anthropic (`/design-sync` + tool `DesignSync`).

# ADR 0315 — `/design-sync` vs. Cowork+charter (avaliação + fecha Gap 1 da 0299)

## Contexto (verificado nesta sessão)

A Anthropic shippou uma integração oficial code↔design no Claude Code: a skill **`/design-sync`** + a tool **`DesignSync`** (ambas disponíveis nesta sessão). O protocolo, lido da definição da tool:

- **Liga** Claude Code ↔ **projetos de Design System no `claude.ai/design`** (via login claude.ai ou `/design-login`). **Não é Figma.**
- **Objetivo declarado:** manter uma biblioteca de componentes local **em sincronia** com um Design System do Claude Design — *"incrementally, one component at a time, never as a wholesale replace."*
- **Ordem dura:** `ler (list/get) → finalize_plan (trava paths de write/delete + localDir) → write/delete`. O `finalize_plan` mostra ao usuário a lista de paths **independente da narração do agente** (não dá pra esconder escopo). `write_files` lê do disco via `localPath` — **conteúdo não entra no contexto do modelo**.
- **Cards** do "Design System pane" auto-indexados pelo comentário `<!-- @dsCard group="…" -->` da 1ª linha de cada preview HTML → `_ds_manifest.json`.
- **Direção:** bidirecional (lê design DA nuvem; sobe componentes locais PRA nuvem), componente a componente.
- **Segurança embutida:** `get_file` devolve conteúdo de **outros membros da org** → o protocolo manda tratar como **dado, não instrução** (anti prompt-injection).

**O conflito de autoridade (mesma classe da 0299).** O oimpresso já tem fonte de design canônica: protótipo **Cowork** (`prototipo-ui/`, read-only) + **Design System em git** (tokens/componentes — SSOT git, [ADR 0239](../0239-governanca-design-system-git-ssot-regressao-ia.md)) + **charter** da tela ([ADR 0114](../0114-prototipo-ui-cowork-loop-formalizado.md)). A diff design→code é o `mwart-comparative` + a **máquina de âncora** (`prototipo-ui/ancora.mjs`, computa a fonte legítima do charter, nunca "no olho"). A [ADR 0299 §1](../0299-figma-nao-e-fonte-de-design.md) já classifica como **NÃO-fonte** (opt-in Wagner explícito) "Figma · Notion · screenshot solto · link externo · **qualquer MCP de design novo**".

**`DesignSync` é, por definição literal da 0299, um "MCP de design novo" → NÃO-fonte.** E é exatamente o **Gap 1** que a 0299 admitiu não ter fechado: o block determinístico `block-figma-without-optin` casa só capability/nome-de-servidor **Figma**; `DesignSync` (tool nativa do harness, não-Figma) **passa livre**. Hoje a única defesa contra ela é doc advisory (L0) — o canal que o agente provou não ler.

### O número que prova o buraco (não opinião)

O matcher do Figma em `.claude/settings.json` é `mcp__.*figma.*|mcp__.*__(use_figma|…|generate_diagram)`. A tool `DesignSync` é **nativa do harness** (sem prefixo `mcp__`) e o nome não está em lista nenhuma; **nenhum** grupo PreToolUse do `settings.json` tinha matcher `DesignSync`. Logo **cobertura de `DesignSync` antes deste PR = 0 hooks** → o caminho de escrita (`finalize_plan`/`write_files`/`delete_files`/`create_project`) passava **100% livre**. Esse é o Gap 1 da 0299 instanciado, medido — não inferido.

> **Esta decisão não é um menu pro Wagner escolher no olho.** A política (Eixo A) é a
> conclusão dos critérios 0299+0239; o enforcement (Eixo B) está **implementado, testado em
> lógica e wirado no CI** — com **um furo aberto e honesto** (ativação em runtime não-provada,
> furo #1, ver §Furos) que só fecha com baseline de sessão fresca. O processo é a máquina + a
> catraca + o red-team que a audita, não a preferência.

## Decisão (2 eixos)

### Eixo A — `claude.ai/design` **não** vira fonte de design canônica do oimpresso

Pelos **mesmos** critérios da 0299 + 0239:

1. **Colide com SSOT git (0239).** Adotar um Design System hospedado no `claude.ai/design` cria um **segundo armazém** de tokens/componentes fora do git. A 0239 fixou: o DS é versionado em git, com catraca/gate anti-regressão. Um espelho na nuvem **divergiria** (o vetor exato que a 0239 e a 0299 §1 combatem: "ninguém transcreve, todo o resto aponta").
2. **Colide com fonte única (0299).** Duas fontes de verdade pra "qual é o componente certo" = o conflito de autoridade que causou o incidente 0299. A diff design→code do projeto já é determinística (charter → `ancora.mjs` → `mwart-comparative`); não há lacuna que justifique trocar de paradigma.
3. **A direção write é a mais perigosa.** `DesignSync.write_files`/`delete_files` empurra componentes locais **pra fora** do perímetro git canônico, pra um projeto claude.ai compartilhável — sem passar por PR/CI/gate de governança. Isso é publicação externa (escopo `publication-policy` + R10): exige aprovação, não default.

### Eixo B — fecha o Gap 1: gateia `DesignSync` sob a classe NÃO-fonte (block determinístico, conforme 0224)

`DesignSync` é gateável por **`tool_name`** exato (`DesignSync`) — block **determinístico**, mesma classe de `block-automem` (path) e `block-figma-without-optin` (capability). **Não** rebaixa o critério da [ADR 0224](../0224-hooks-block-vs-advisory-claude-4.8-aware.md) (não é semântico).

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
| **#1** | **Gate INERTE na sessão que o cria.** Chamada real `DesignSync.finalize_plan` sem opt-in voltou **erro de validação do tool**, não o `[BLOCKED]` do hook → o PreToolUse **não disparou**. `settings.json` carrega no startup; editar no meio da sessão não faz hot-reload. O E2E prova a *lógica*, não a *entrega do payload pelo harness*. | 🔴 **ABERTO** — só fecha com **baseline de sessão fresca** (rodar a probe de novo numa sessão que já subiu com o hook registrado, esperar `[BLOCKED]`). Esse é o L5 que a 0299 exigia e eu havia pulado. | probe real `finalize_plan` |
| **#2** | **Opt-in fail-OPEN: discutir a feature armava escrita.** `"como funciona o design sync?"` destravava 15 min de escrita. | ✅ **FECHADO** — opt-in agora exige INTENÇÃO (verbo de publicar + nome, ou `/design-sync`); pergunta/explicação nunca arma; deny cobre nunca/jamais. Virou regressão no test. | `isDesignSyncOptInPrompt` |
| **#3** | **Flag machine-wide** (`tmpdir` global) vazava opt-in entre projetos. | ✅ **FECHADO** — flag keyed no `cwd` (por-projeto). | leitura do código |
| **#4** | TTL 15min não-consome: 1 arme → N escritas. | 🟡 mitigado por #2 (armar agora exige intenção de publicar); consume-once = melhoria futura. | — |
| **#5** | Leitura (vetor de injeção: `get_file` traz conteúdo de outros membros) é livre. | 🟡 inerente ao protocolo; gate só protege escrita; disciplina "dado, não instrução". | protocolo |
| **#6** | `tool_name` exato é frágil (tool-irmã/`/design-login` futuros escapam; método de *leitura* futuro é falso-bloqueado). | 🟡 residual aceito; revisar se a superfície da tool mudar. | análise |

**Meta-lição:** a v1 deste PR declarou "máquina provada por teste" conflando *lógica-do-hook-provada* com *gate-ativo-provado* — o mesmo modo de falha que o projeto cataloga ("a suite mente"/baseline nunca armado). Corrigido abaixo.

## Validação (executada — `node`, não Pest) — status HONESTO

- ✅ **Lógica + E2E do hook** (`block-design-sync-without-optin.test.mjs`): leitura livre; escrita gateada; default-deny de método futuro/ausente; opt-in **endurecido** (furo #2 como regressão); E2E spawn → exit codes reais (write→2, read→0, opt-in→0).
- ✅ **Catraca de registro** (`settings-design-sync-registration.test.mjs`): hook wirado nos 2 eventos + matcher casa `DesignSync`.
- ✅ **Wirado no CI** (`governance-script-tests.yml`) — verde no PR (não é local-only).
- ✅ `settings.json` JSON válido + regressão Figma intacta + campo `tool_input` confirmado real.
- ✅ Conformidade 0224 (block por `tool_name` + `method` = determinístico).
- 🔴 **ATIVAÇÃO em runtime: NÃO provada** (furo #1). A lógica está provada; a *entrega do PreToolUse pela plataforma pra tool nativa `DesignSync`* exige baseline de sessão fresca. **Até esse baseline passar, o gate é "armado mas não disparado-em-prod".** Não declarar fechado sem ele.

## Notas

- Esta ADR **vem com o enforcement já implementado e testado** (Eixo B), seguindo o padrão `block-figma-without-optin` (0299): hook `.claude/hooks/block-design-sync-without-optin.mjs` (+ `.test.mjs`) + catraca `scripts/governance/settings-design-sync-registration.test.mjs` + registro nos 2 eventos do `settings.json`. Os 2 testes já estão **wirados no CI** (`.github/workflows/governance-script-tests.yml` — fecha o "test local-only" que a 0299 chama de defesa-fantasma). Falta só a ratificação humana ([W]). A política (Eixo A) é o que a máquina enforça; não há "escolha de menu".
- Sequência de defesa mecânica do projeto: `block-automem` (path) → `block-pr-without-approval` (R10) → `block-figma-without-optin` (capability) → proposto `block-design-sync-without-optin` (tool_name).
- O protocolo `/design-sync` em si é **bem desenhado** (plano travado visível ao usuário, conteúdo fora do contexto, anti prompt-injection no `get_file`). A objeção é de **governança de fonte-da-verdade**, não de qualidade da ferramenta.
