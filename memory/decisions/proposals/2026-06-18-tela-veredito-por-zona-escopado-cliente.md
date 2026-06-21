---
title: "Conhecimento de tela = veredito (aprovado/recusado) ancorado na ZONA e escopado por cliente — a ponte que une o contrato-de-tela ao ledger e ao 'recusado-com-motivo'"
status: proposed
date: "2026-06-18"
decisores: [Wagner (aprova), Claude Code (autor)]
related_adrs:
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0233-ativacao-memoria-momento-decisao
  - 0236-governanca-evolucao-doc-design
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0286-channel-health-corroborado-por-mensagem-real
origem: "Wagner 2026-06-18: 'quem vai fazer o resumo dos conhecimentos positivos e negativos? como vai funcionar aquilo que já foi aprovado ou reprovado, por quê, para qual cliente? como vai ficar esse tipo de conhecimento?' — durante a discussão da catraca semântica (#2986)."
---

# Conhecimento de tela = veredito ancorado na zona, escopado por cliente

> **Não é sistema novo.** É a **ponte** entre três peças que já existem/estão desenhadas:
> o **contrato-de-tela** (gate `scripts/contrato-de-tela.mjs`), o **Design Request Ledger**
> (proposal `design-request-ledger-incremental.md` — o SIM) e **Recusado-com-motivo**
> (proposal `2026-06-11-recusado-com-motivo-status-primeira-classe.md` — o NÃO).

## Contexto

A discussão da catraca semântica ([ADR 0286](../0286-channel-health-corroborado-por-mensagem-real.md) §5, PR #2986) expôs que um veredito **sem escopo e sem reuso é bespoke** — vale pra uma tela e morre ali. Wagner perguntou o que falta de verdade: **quem resume o conhecimento positivo (aprovado) e negativo (recusado), por quê, e pra QUAL cliente** — e como esse conhecimento "fica".

As duas metades já existem, mas **soltas e não-ancoradas na tela**:

- **O SIM** tem casa: o **Design Request Ledger** (`memory/governance/design-requests/REQ-NNN.md` + `LEDGER.md`, file-based em git porque o Cowork/Design **só vê arquivos** — [ADR 0061](../0061-conhecimento-canonico-git-mcp-zero-automem.md)). `REQ done` = pedido aprovado + aplicado + `resultado` (PR/hash).
- **O NÃO** tem casa: **Recusado-com-motivo** (`status: recusado` + `rejected_reason` + critério de reabertura). O NÃO vira consultável: *"recusado em DATA, por MOTIVO, ver link"*.

O que **falta** é o eixo que Wagner nomeou: (1) o veredito não está ancorado na **ZONA da tela** (header/contexto/lista/footer), então não herda nem reusa; (2) não está **escopado por cliente** ([ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)) — "aprovado pra RotaLivre" vaza silencioso pra outro tenant; (3) ninguém definiu **quem faz o resumo** — risco de virar prosa que apodrece (o `SYNC_LOG` vazio é o aviso).

## Decisão proposta

### 1. A unidade de conhecimento = VEREDITO, ancorado na zona, escopado por cliente

Cada fato é um veredito atômico, append-only, carregando:

| Campo | Significado |
|---|---|
| `verdict` | `aprovado` (vira REQ done) \| `recusado` (status recusado) |
| `zona` | âncora `data-contract` (`pageheader`/`contexto`/`lista`/`footer`/`saude-canal`…) — o eixo que falta |
| `escopo` | `global` \| `vertical:<x>` \| `cliente:biz=<n>` \| `persona:<p>` \| `tela:<rota>` ([ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)) |
| `porque` | SIM → `resultado` (PR/screenshot aprovado); NÃO → `rejected_reason` + **critério de reabertura** |
| `prova` | screenshot aprovado ([ADR 0114](../0114-prototipo-ui-cowork-loop-formalizado.md)) / PR / frase textual do Wagner |

### 2. Quem faz o resumo — três papéis, nenhum escreve à mão

A resposta direta ao Wagner. O resumo **não é redigido**, é **derivado** (mesma régua do `--map` do gate: *"GERADO, não editar à mão"*; senão rota igual o `SYNC_LOG`):

1. **Agente rascunha** o veredito no **momento da decisão** ([ADR 0233](../0233-ativacao-memoria-momento-decisao.md)) — abre `REQ-NNN` (aprovado) ou registro `recusado` (negado), com `zona`+`escopo`+`porque` pré-preenchidos.
2. **Wagner ratifica** — aprova o **screenshot** ([ADR 0114](../0114-prototipo-ui-cowork-loop-formalizado.md)), ou crava a recusa com a frase textual (igual `accepted_via`/`rejected_via`). Sem ratificação = rascunho, não conhecimento (anti-auto-mem, [ADR 0061](../0061-conhecimento-canonico-git-mcp-zero-automem.md)).
3. **Máquina deriva o resumo** — `LEDGER.md` (gerado) + seção "Recusadas" do índice (`adr-index-generate.mjs`) + um modo `contrato-de-tela.mjs --ledger <tela>` que imprime *"aprovado / recusado por zona, por escopo"*. Se o resumo divergir dos vereditos, o gate falha.

### 3. Escopo resolve por especificidade (herança, igual a Constituição UI)

Ao checar uma tela pra um cliente, o veredito aplicável resolve `global < vertical < cliente < tela` — o mais específico vence, e **nunca contradiz** o de baixo sem um veredito explícito que o `supersedes`. É a regra-mestre da Constituição UI v2 (`herda das inferiores, nunca contradiz`) aplicada ao conhecimento, não só ao layout. Assim "aprovado pra RotaLivre (biz=4)" **não vaza** pra outro tenant.

### 4. A estrutura: contrato em camadas + a catraca como 1ª invariante de zona

A zona compartilhada (PageHeader, Shell, PT) vira **contrato canônico herdável**; a tela declara só o **delta**. A **catraca semântica** (#2986) vira a **primeira invariante de zona** — o *vocabulário de estado* da zona `saude-canal`, declarado 1× e travado em toda tela que o herda (CaixaUnificada · `Channels/Show` · `Channels/Index`). É o reuso que faltava — resolve o veredito "bespoke" do adversário.

### 5. O que NÃO muda / NÃO duplica

- **Não cria tabela no MCP** — file-based em git (o Cowork só lê arquivos). MCP só indexa por webhook.
- **Não duplica** o REQ-Ledger nem o Recusado-com-motivo — **reusa** os dois, só adiciona `zona`+`escopo`.
- **Não migra histórico** — só daqui pra frente (retro-catalogação é passada opcional do Zelador).

## Consequências

- ✅ **Resposta ao Wagner:** o resumo positivo/negativo existe, é **gerado** (não apodrece), e responde *"aprovado/recusado, por quê, pra qual cliente"* por tela E por zona.
- ✅ Conhecimento de 1 cliente não vaza pra outro (escopo). Recusa carrega o que a **reabriria** (funil de sinal, não cemitério — [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)).
- ✅ A lição aprendida 1× numa zona protege toda tela que herda (multiplicador).
- ⚠️ Mais um eixo (`zona`+`escopo`) nos atoms — mitigado: o agente pré-preenche; Wagner só ratifica.
- ⚠️ Exige o contrato em camadas (herança) pra valer — sem isso, vira rótulo. Adotar incremental: 1 zona (`saude-canal`) primeiro.

## Aprovação

Wagner ratifica (ou edita) → vira ADR numerada + PR. Sugestão: o adversário (painel que julgou o #2986) julga **esta ideia da camada**, não o PR de 72 linhas — e o trim da catraca entra como a 1ª invariante de zona.
