---
slug: 0324-sentinela-frescor-espelho-cowork-designsync-read
number: 324
title: "Sentinela de frescor do espelho Cowork (repo ↔ claude.ai/design via DesignSync-read) — fecha o ponto cego #2 da âncora-de-conteúdo"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-06"
module: design-system
tags: [design, governanca, cowork, design-sync, fonte-da-verdade, frescor, advisory, sentinela, tier-0]
supersedes: []
superseded_by: []
related:
  - 0299-figma-nao-e-fonte-de-design
  - 0315-design-sync-claude-design-vs-cowork-charter
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
pii: false
---

> **Proposta por [CL] (Claude Code) em 2026-07-06.** Ratificação formal = merge por [W].
> **Evolução — NÃO supersede.** Cowork sempre foi a fonte ([ADR 0299](../0299-figma-nao-e-fonte-de-design.md)); claude.ai/design nunca vira fonte ([ADR 0315](../0315-design-sync-claude-design-vs-cowork-charter.md)). Isto só ADICIONA um uso de **leitura** legítimo dessa integração: detectar que a cópia do design no repo apodreceu vs o vivo.

# ADR 0324 — Sentinela de frescor do espelho Cowork (fecha o ponto cego #2 da âncora-de-conteúdo)

## Contexto (verificado em `origin/main`)

Em 2026-07-06 nasceu o `anchor-content-check.mjs` ([session](../../sessions/2026-07-06-ancora-podre-sentinela-conteudo.md)) — sentinela que abre a âncora de design de cada tela e prova que ela aponta pro **arquivo certo** do repo (não pro shell do app, não pra arquivo-fantasma). Ele fecha **CORREÇÃO da âncora**. A própria session registrou **2 pontos cegos** que ele **não** cobre:

1. **Âncora-PROSA** — 3 telas cujo `related_prototype` não resolve a um caminho de arquivo (ex.: `prototipo Cowork "payment-gateway-ui"`). O sentinela as pula.
2. **"Protótipo de bubble velha"** — o arquivo do espelho **existe**, tem conteúdo do módulo, **passa** no anchor-content — mas é **design ANTIGO**. A cópia do repo (`prototipo-ui/cowork/<arq>`) driftou do design **vivo** no Cowork (claude.ai/design) e ninguém re-exportou. *"Só o olho humano ou um diff pega."*

Este ADR ataca o **ponto cego #2**. A alavanca nova: a integração oficial **DesignSync** (tool nativa + skill `/design-sync`, avaliada na [ADR 0315](../0315-design-sync-claude-design-vs-cowork-charter.md)) hoje **permite ler** o design vivo (`list_projects`/`get_file`). Se dá pra ler o vivo, dá pra tirar o md5 dele e comparar com o md5 da cópia do repo. **Divergiu = espelho STALE.**

Medido nesta sessão (lado repo, `origin/main`): `prototipo-ui/cowork/financeiro-page.jsx` = **`ae3a2cfe8855fc41e25354fcaa03de84`** (116 KB). O caminho de **leitura** do DesignSync foi provado end-to-end (`get_file` no projeto "Office Impresso — Design System" devolveu conteúdo real de `components/Button/Button.jsx`).

## Decisão

### 1. Um sentinela de **frescor** (freshness), irmão do de conteúdo — leitura, nunca escrita

`scripts/governance/cowork-mirror-freshness.mjs` compara, por arquivo-âncora do espelho, **md5(repo) vs md5(vivo)** e classifica:

| Veredito | Significado | Ação |
|---|---|---|
| `SYNC` | md5 iguais | espelho fresco — nada a fazer |
| `STALE` | md5 diferem | **o espelho do repo divergiu do vivo → re-exportar do Cowork** (o sinal DURO) |
| `LIVE-ABSENT` | o agente buscou e não achou no projeto vivo | rename/delete upstream **ou** mapa de projeto errado — aviso, NÃO stale |
| `UNCHECKED` | o arquivo do manifesto não veio no snapshot | o agente não buscou — aviso; **nunca** vira `SYNC` no silêncio |

Ele **só GRITA** "o espelho divergiu" — **humano decide** re-exportar. **Nunca** puxa design PRA dentro do git (a direção `nuvem → git` segue proibida por [0315](../0315-design-sync-claude-design-vs-cowork-charter.md) §Eixo A / [0299](../0299-figma-nao-e-fonte-de-design.md) §1). Usa **só métodos de LEITURA** do DesignSync (`list_projects`/`get_file`), que a [0315 §Eixo B](../0315-design-sync-claude-design-vs-cowork-charter.md) deixa **livres** (o gate protege a escrita, não a inspeção). É exatamente o uso "read-mostly, git continua a fonte" que a 0315 previu — só que a favor da governança (detectar drift), não como vitrine.

### 2. Split honesto (o node não fala MCP)

O node não chama tool MCP. A responsabilidade é dividida:

- **LOCAL (puro, roda em qualquer lugar):** `--manifest` enumera os arquivos-âncora do espelho — **mesmo conjunto** que o anchor-content enxerga (reusa `anchorFile`, fonte única de "como extrair o arquivo do `related_prototype`") — calcula o md5 do repo e emite a "lista de compras" JSON.
- **VIVO (agente/dispatch, FORA do CI):** o agente lê o manifesto, chama `DesignSync.get_file` por arquivo (LEITURA), tira o md5 do conteúdo e monta o snapshot `{ "<basename>": "<md5-hex>" }` (`null` = não achou no projeto vivo).
- **COMPARE (puro):** `--compare snapshot.json [--check]` reclassifica cada arquivo contra o snapshot. `--check` sai 1 **só em STALE**.

### 3. Advisory de nascença + **dispatch-only** (não é gate de PR)

A leitura viva exige `/design-login` (auth claude.ai) que **não roda em CI headless** ([0315 §Furos](../0315-design-sync-claude-design-vs-cowork-charter.md), tentativa 2). Logo:

- **SÓ o `--selftest`** (node puro, prova a classificação) roda no CI — wirado no `design-memory-gate.yml` **advisory** (`continue-on-error`), ao lado do anchor-content selftest ([ADR 0314](0314-poda-gates-onda-2-lei-fusoes.md): advisory de nascença; não entra em branch protection).
- **A checagem VIVA (`--compare`) é ROTINA local/dispatch** conduzida por um agente com sessão logada. Nunca bloqueia merge. Cadência sugerida: sob demanda ao mexer numa tela cujo espelho é âncora, ou varredura periódica manual.

## Rotina de dispatch (como um agente roda a checagem viva)

1. `node scripts/governance/cowork-mirror-freshness.mjs --manifest` → lista de compras (hoje: `compras-page.jsx`, `financeiro-page.jsx`, `financeiro-telas-extras.jsx`).
2. `DesignSync.list_projects` (ou UUID fornecido pelo Wagner) → resolver o projeto que espelha `prototipo-ui/cowork/` (o "Oimpresso ERP Conunicação Visual", `019dcfd3…`). `list_files` → mapear cada basename do manifesto ao caminho vivo.
3. `DesignSync.get_file` por arquivo → md5 do `content` → montar `snapshot.json` (`{basename: md5}`; `null` se ausente).
4. `node scripts/governance/cowork-mirror-freshness.mjs --compare snapshot.json --check` → relatório + exit 1 se STALE.
5. Se STALE: re-exportar a tela do Cowork pro espelho do repo (fluxo `aplicar-prototipo`/import), abrir PR. **Não** editar o espelho à mão a partir do vivo sem o fluxo.

## Não-goals

- ❌ **Não é gate de PR** — a metade viva não roda em CI headless; só o selftest roda (advisory).
- ❌ **Não escreve nada** no claude.ai/design (`finalize_plan`/`write_files`/`delete_files`/`create_project` **ausentes** do fonte — provado no selftest). É leitura defensiva, não publicação.
- ❌ **Não puxa design pra dentro do git** — verdicto STALE é um SINAL pra humano re-exportar pelo fluxo canônico, não um sync automático (`nuvem → git` proibido, 0299/0315).
- ❌ **Não supersede 0299 nem 0315** — estende (novo uso de leitura). A política "claude.ai/design não é fonte" segue intacta.
- ❌ **Não cobre o ponto cego #1** (âncora-prosa) — segue aberto; normalizar prosa→caminho é trabalho à parte.

## Gaps residuais conhecidos (honestidade)

1. ~~**Mapa de projeto vivo pendente.**~~ **RESOLVIDO 2026-07-06 (mesma sessão).** UUID pleno achado no repo: `019dcfd3-6ef2-7ee6-8512-b1b0e5544e58` (em `COWORK_HANDOFF.paymentgateway-ui.md` + `mwart-quality/SKILL.md`). `get_project` confirmou "Oimpresso ERP Conunicação Visual." (`type: PROJECT_TYPE_PROJECT` — por isso ficava fora do `list_projects`, que filtra graváveis). Leitura viva PROVADA end-to-end **sem prompt de `/design-login`** (esta sessão logada já tem o escopo; o furo headless da 0315 era CI puro): `list_files` (342 paths) + `get_file`. 1º veredito real: `financeiro-page.jsx` vivo = `ae3a2cfe…` (116165 bytes) = idêntico ao repo → **SYNC**. **Limite que persiste:** a leitura roda em **sessão logada** (Claude Code), **não em CI headless** → a checagem viva segue **dispatch**, não gate de PR. E o projeto vivo é superset com **lixo próprio** (`_arquivo/`, `memory/`, `benchmark/`) — regeneração precisa FILTRAR (alimenta a [0325](0325-fonte-prototipo-migra-para-api-cowork-git-vira-cache-gerado.md)).
2. **Sem oráculo perfeito de "vivo == esta tela".** Se dois arquivos vivos compartilham basename, o agente precisa desambiguar ao montar o snapshot (documentado no header do script). O md5 governa o veredito, não o nome.
3. **Caveat de bytes/quebra-de-linha.** md5 é byte-exato. Se o `get_file` normalizar EOL diferente do git (`\n` vs `\r\n`), um arquivo idêntico daria STALE falso — mitigar hasheando o `content` utf8 cru; caso apareça ruído, normalizar EOL antes do md5 (registrar se acontecer).
4. **A confiança termina no que o agente buscou.** `UNCHECKED` existe justamente pra não fingir `SYNC` quando o snapshot está incompleto (a suite não mente por silêncio).

## Consequências

✅ **Boas:**
- O ponto cego #2 sai do "só o olho do Wagner pega" pra uma checagem byte-provada (da próxima vez a máquina grita antes do instinto). Mesmo padrão da catraca/sentinela da [ADR 0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md).
- Usa a integração nova **na direção segura** (leitura defensiva) — sem virar backdoor de fonte-de-verdade que a 0315 combate.
- Reusa `anchorFile` → o conjunto "quais arquivos são âncora" nunca diverge entre o sentinela de conteúdo e o de frescor.

⚠️ **Tradeoffs:**
- A metade viva depende de sessão logada (dispatch), não de CI — cobertura por disciplina + rotina, não por gate. Aceito por plataforma (0315 provou que a auth não roda headless).
- Mais um script + selftest pra manter (custo de governança — mitigado: node puro, segundos, advisory).

## Validação (executada — `node`, não Pest)

- ✅ `node scripts/governance/cowork-mirror-freshness.test.mjs` — **26/26** checks de contrato (md5 byte-sensível · SYNC/STALE/LIVE-ABSENT/UNCHECKED · `--check` só morde em STALE · READ-ONLY provado no fonte · manifesto hermético reusando `anchorFile`).
- ✅ `--manifest` real: 3 arquivos-âncora (`compras-page.jsx` `cc3a8075…` · `financeiro-page.jsx` `ae3a2cfe…` · `financeiro-telas-extras.jsx` `46159c9c…`).
- ✅ Caminho de LEITURA do DesignSync provado end-to-end (`get_file` devolveu `components/Button/Button.jsx` real).
- ✅ Selftests existentes intactos: `anchor-content-check.test.mjs` verde · `design-memory-gate.test.mjs` verde (o wire do step novo não quebrou o gate-selftest).
- ✅ **Checagem VIVA (`--compare` contra o projeto ComVis) PROVADA 2026-07-06**: UUID `019dcfd3-6ef2-7ee6-8512-b1b0e5544e58`, `get_file` real → `financeiro-page.jsx` = **SYNC** (md5 `ae3a2cfe…` idêntico repo↔vivo). `--compare` real rodado: 1 sync, 2 unchecked. Roda em sessão logada (dispatch), não em CI headless.

## Notas

- Sequência de sentinelas de design do projeto: `ancora.mjs` (proveniência: âncora vem do charter) → `anchor-content-check` (correção: aponta pro arquivo certo do repo) → **`cowork-mirror-freshness`** (frescor: a cópia do repo bate com o vivo).
- Conformidade [ADR 0224](../0224-hooks-block-vs-advisory-claude-4.8-aware.md): o veredito é determinístico (md5), advisory — não rebaixa critério de block.
- A lição perene (a mesma do dia): todo gate que prova **presença/proveniência/correção-estática** e não **frescor/conteúdo-vs-vivo** deixa um buraco esperando um refactor pra abrir. Este fecha a camada de frescor do espelho.
