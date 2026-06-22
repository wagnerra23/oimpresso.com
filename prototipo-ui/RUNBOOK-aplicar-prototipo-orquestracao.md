# RUNBOOK — Aplicar protótipo → tela (orquestração multi-tela)

> **Camada de ORQUESTRAÇÃO** (detectar → mapear → registrar → aplicar → fechar) que fica **acima** do RUNBOOK por-tela. Para a mecânica de UMA tela, ver [`RUNBOOK-replicar-prototipo-cowork.md`](../memory/requisitos/_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md) (7 fases F0–F7) + skills `cowork-prototype-replication` e `mwart-process`.
>
> **Origem:** Wagner 2026-06-22 — "sempre vai analisar o que mudou no protótipo, dividir a tela em partes, gravar o quê/porquê, gerar changelog, atualizar a SPEC... qual o fluxo completo? depois de analisar abre task em sessão limpa e aplica em paralelo (economiza token)". Este doc responde e fixa o método.

## Por que ESTE método (vs ad-hoc)

O que dava errado: pular direto pra aplicação, sem mapa, carregando contexto gigante, sem registro do porquê. O método certo separa duas coisas com custos opostos:

| | Análise | Aplicação |
|---|---|---|
| Custo | barata, read-only, **1x** | cara, escreve código, **por tela** |
| Risco | zero (não toca nada) | alto (toca tela-mãe, Tier 0) |
| Contexto | precisa ver o todo | precisa ver **só 1 tela** |

→ **Regra de ouro:** analisa o todo de uma vez (paralelo, read-only) e **aplica cada tela numa SESSÃO LIMPA** que recebe só o GAP-SPEC daquela tela. Isso economiza token (a sessão de aplicação não arrasta a análise das outras 6 telas) e isola o risco (1 worktree por tela = zero conflito).

## O fluxo completo (6 fases)

### FASE 0 — Detectar (1x, barato)
- `git diff` do `prototipo-ui/prototipos/` desde o último sync (sha registrado no [`SYNC_LOG.md`](SYNC_LOG.md)) → **quais telas mudaram**.
- Se nada mudou no git: o protótipo **é** o alvo → compara protótipo × tela viva (gap de implementação, não de mudança).
- Entrada nova de protótipo chega via Issue `cowork-intake` (ADR 0282, protocolo v2) — não é mais Wagner colar.
- **Saída:** lista de telas a processar.

### FASE 1 — Mapear (paralelo, read-only) ⭐ economia de token mora aqui
- **1 agente por tela, em paralelo** (`general-purpose`, READ-ONLY — proibido Edit/Write/commit).
- Cada agente:
  1. Lê o protótipo da tela + a tela viva (`Pages/<Mod>/<Tela>`) + charter + `<tela>-visual-comparison.md`.
  2. **Divide a tela em PARTES** (header, KPIs, filtros, lista/tabela, drawer, modais, footer…).
  3. Por parte: **o que mudou/falta** + **POR QUÊ** + esforço (P/M/G) + risco (só visual / backend / Tier 0 / governança).
  4. % de paridade + ordem de aplicação.
- **Saída por tela:** um **GAP-SPEC** gravado em `memory/requisitos/<Mod>/<tela>-gap.md` (template abaixo). É o artefato pequeno e auto-contido que a sessão de aplicação vai consumir.

### FASE 2 — Consolidar + decidir (Wagner)
- Tabela mestre: tela × paridade × maior gap × risco × onda.
- **Flags de governança** (PARA aqui se bater):
  - módulo **silenciado** (BRIEFING) → não evoluir sem OK explícito Wagner.
  - **Tier 0** (multi-tenant / dado) → não inventar; segue ADR 0093.
  - tela **"ouro"/contract-locked** (`contrato-de-tela.yml`) → mudança visual exige zero-diff.
  - **ADR-mãe não aprovada** → bloqueado (ex: CRM funil).
  - cliente-como-sinal (ADR 0105): feature sem sinal vira backlog, não onda.
- Wagner aprova o backlog + a ordem das ondas.

### FASE 3 — Registrar (barato, fecha rastreabilidade)
Por tela aprovada:
- **Task no MCP** (`tasks-create`) com o GAP-SPEC embutido (vira a "ordem de serviço" da sessão limpa).
- **CHANGELOG** da tela atualizado (`memory/requisitos/<Mod>/CHANGELOG.md`): o quê + porquê, por parte.
- **SPEC** atualizada: US correspondente + campo `**Implementado em:**` (vai pra `_pendente_` ou `_parcial_` até aplicar; vira `anchored_ok` no fim — validado pelo `anchor-lint`, ADR 0297).

### FASE 4 — Aplicar (SESSÃO LIMPA por tela, paralela, com portão) ⭐ ideia do Wagner
- **1 sessão/worktree ISOLADA por tela** (não a sessão da análise — economia de token + isolamento).
  - Mecanismo: task MCP retomada em sessão nova, OU `Agent(isolation: "worktree")`, OU `coordenador-paralelo`.
  - A sessão limpa carrega **só**: o `<tela>-gap.md` + as skills que auto-disparam (`mwart-process`, `cowork-prototype-replication`, `charter-first`, `multi-tenant-patterns`, `preflight-modulo`). Não arrasta a análise das outras telas.
- Dentro de cada sessão: segue o RUNBOOK por-tela (F0–F7) — backend baseline → frontend incremental por PARTE → Pest → ds-guard.
- **Paralelo** entre telas independentes (worktrees separadas = zero conflito de arquivo).
- **Portão obrigatório:** screenshot 1280/1440 light+dark → **Wagner aprova o SCREENSHOT** (não a tabela) → merge. `pr-ui-judge` + `visual-regression` + `contrato-de-tela` no CI.

### FASE 5 — Fechar o loop (barato)
- `SYNC_LOG.md` append (o que foi aplicado, sha).
- Charter: `status`/`version` atualizados.
- `node scripts/governance/anchor-lint.mjs --check memory/requisitos/<Mod>/SPEC.md` → fidelidade spec↔código (0 dead/zombie/teste-fantasma).
- `brief-update` do módulo.

## Template do GAP-SPEC (`<tela>-gap.md`)

```markdown
---
tela: <Mod>/<Tela>
prototipo: prototipo-ui/prototipos/<dir>/
tela_viva: resources/js/Pages/<Mod>/<Tela>.tsx
paridade_atual: NN%
gerado_em: YYYY-MM-DD
governanca: [silenciado? tier0? contract-locked? adr-pendente?]
---
# GAP — <Tela>

| Parte | O que mudou/falta | Por quê | Esforço | Risco | Ação |
|---|---|---|---|---|---|
| Header | ... | ... | P | só visual | ... |
| Lista | ... | ... | M | backend | ... |
| Drawer | ... | ... | G | tier0 | ... |

**Ordem:** 1) ... 2) ...
**Veredito:** perto / longe / greenfield · paridade NN%
```

## Resumo de 1 linha (cole na sessão de aplicação)
> "Aplica o `<Mod>/<Tela>-gap.md` na tela viva, parte por parte, seguindo mwart + charter + Tier 0. Para no screenshot pro Wagner aprovar. Não inventa; gap incerto = pergunta."

## Refs
- [`PROTOCOL.md`](PROTOCOL.md) (loop Cowork↔Code, ADR 0282 v2) · [`PROCESSO_MEMORIA_CC.md`](PROCESSO_MEMORIA_CC.md) · [`LICOES_F3_FINANCEIRO_REJEITADO.md`](LICOES_F3_FINANCEIRO_REJEITADO.md)
- [`RUNBOOK-replicar-prototipo-cowork.md`](../memory/requisitos/_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md) — mecânica por-tela
- ADR 0104 (MWART) · ADR 0114 (loop Cowork) · ADR 0282 (protocolo v2) · ADR 0297 (anchor-lint fidelidade) · ADR 0093 (Tier 0) · ADR 0105 (cliente-sinal)
- Skill `aplicar-prototipo` (dispara este RUNBOOK)
