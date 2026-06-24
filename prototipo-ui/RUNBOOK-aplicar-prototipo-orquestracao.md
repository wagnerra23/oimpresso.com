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

→ **Regra de ouro:** analisa o todo de uma vez (paralelo, read-only) e **aplica cada tela numa SESSÃO LIMPA** seedada só pelo GAP-SPEC daquela tela. Economia é **O(1 tela) em vez de O(N telas)** por sessão — NÃO "gap minúsculo": a sessão ainda lê o protótipo+tela daquela tela (por isso o GAP-SPEC aponta ranges de linha). Isolamento é **PARCIAL**: arquivos compartilhados (DS components + `config/*baseline*.json`) NÃO paralelizam — ver **Zonas de serialização** na Fase 4.

## O fluxo completo (6 fases)

### FASE 0 — Detectar (1x, barato)
- **0.0 Pré-voo de sanidade do checkout (antes de qualquer `git log`/Glob):** confirme que o cwd é um checkout **completo**, não worktree órfã/husk:
  - `git rev-parse --is-inside-work-tree` = `true` **e** `git rev-parse --show-toplevel` aponta pra raiz esperada **e** existem `resources/`, `Modules/`, `prototipo-ui/`.
  - Se falhar → **PARE**. Mude pra worktree boa (`git worktree add <path> <branch-que-tem-os-artefatos>`). **Por quê:** Glob/`git log`/`ls-tree` rodados de um husk sem código devolvem **falso negativo silencioso** (`arquivo não existe` / `diff vazio`) — não erro — e induzem a inventar/duplicar artefato que já existe. Lição `licao-git-lstree-grep-rev-cwd-scope`; incidente real 2026-06-24 (sessão "perfil" abriu na worktree órfã `frosty-greider` → reportou RUNBOOK e skill como "inexistentes" sendo que estavam no checkout bom).
  - **Bônus:** confirme também que os artefatos do protocolo (a skill, este RUNBOOK) existem na branch-base; se não, podem estar numa feature branch ainda-não-mergeada (mesmo incidente: skill `aplicar-prototipo` vivia só em `feat/vendas-link-caixa-do-dia`, ausente de `main`).
- **Ponto de referência por tela = último commit que tocou o protótipo** (o `SYNC_LOG` NÃO guarda sha por tela — não dependa dele): `git log -1 --format=%H -- prototipo-ui/prototipos/<dir>/` → diff desse sha até HEAD.
- **Mapa nome↔Page NÃO é 1:1** — resolva o alvo real antes: `prototipos/crm` → tela viva é `Pages/Cliente`; `prototipos/vendas` só tem charter (sem html/jsx); a tela viva às vezes está **à frente** do protótipo. Reconciliação no GAP-SPEC.
- Se o protótipo não mudou (ou está vazio): o protótipo **é** o alvo → compara protótipo × tela viva (gap de implementação).
- **Intake**: canal canônico = Issue `cowork-intake` (`.github/ISSUE_TEMPLATE/cowork-intake.yml`, ADR 0282) — mas adoção ainda é **zero**; na prática o intake hoje é handoff/bundle. Trate ambos. (≠ `workflows/cowork-inbox.yml`, mecanismo push-em-paths.)
- **Saída:** lista de telas + o Page real (alvo) de cada.

### FASE 1 — Mapear (paralelo, read-only) ⭐ economia de token mora aqui
- **1 agente por tela, em paralelo** (`general-purpose`, READ-ONLY — proibido Edit/Write/commit).
- Cada agente:
  1. Lê o protótipo da tela + a tela viva (`Pages/<Mod>/<Tela>`) + charter + `<tela>-visual-comparison.md`.
  2. **Divide a tela em PARTES** (header, KPIs, filtros, lista/tabela, drawer, modais, footer…).
  3. Por parte: **o que mudou/falta** + **POR QUÊ** + esforço (P/M/G) + risco (só visual / backend / Tier 0 / governança).
  4. % de paridade + ordem de aplicação.
- **4º veredito obrigatório** (além de perto/longe/greenfield): **tela À FRENTE do protótipo** → NÃO regride; o protótipo vira backlog de catch-up, não fonte (caso real: Cliente já passou o protótipo).
- **Saída por tela:** (a) **GAP-SPEC** em `memory/requisitos/<Mod>/<tela>-gap.md` (template abaixo); (b) **mapa design↔código** `<tela>.map.json` — análogo ao **Figma Code Connect**: por PARTE, o bloco do protótipo (+range de linha) ↔ arquivo/range da tela viva, carregando o **sha do protótipo** que o gerou. O map.json faz a sessão de aplicação ler só os trechos (economia real) e permite **invalidar** o gap quando o protótipo re-exporta (Fase 4 aborta se o sha mudou → regenera).

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
- **Pré-flight de gates ANTES de abrir o PR (tela nova morre no portão se pular):** rode local e zere —
  - `node scripts/layout-primitives-guard.mjs` (ADR 0253 — compõe `Stack/Inline/Grid`, zero `flex`/`grid` solto · `grid place-`/`inline-flex` não contam),
  - `node scripts/casos-coverage-guard.mjs` (trio `<Tela>.tsx`+`.charter.md`+`.casos.md`; UC novo cita o id no Pest; Status ✅ só com teste verde, senão 🧪/⬜ — ADR 0264),
  - `npm run lint:baseline:check` (ESLint ratchet — ex: `ds/no-native-select` → use `<Select>` de `@/Components/ui/select`),
  - `node_modules/.bin/tsc --noEmit` (typecheck; cuidado com `noUncheckedIndexedAccess` em `Record` → tipe chaves explícitas),
  - PHPStan ratchet (controller: `firstOrFail`/`findOrFail` + guards `is_array`/`is_string` evitam erros de null/mixed-offset),
  - `pageheader-gate` (tela nova usa `@/Components/PageHeader` canon, NÃO o `shared/` congelado),
  - PII scan (sem CPF/CNPJ literal tipo `000.000.000-00` em placeholder),
  - se tocar `routes/`/middleware/ServiceProvider/Kernel: seção `## Infra Contract` no corpo do PR + evidência curl (`< HTTP/1.1 …`).
  - **Por quê:** incidente perfil 2026-06-24 — tela verificada AO VIVO no staging (render + save persistindo) tripou **6 gates** no PR (layout/casos/eslint/phpstan/PII/infra-contract). "Funciona no staging" ≠ "passa no portão"; rode os gates como parte da Fase 4, não como surpresa no PR.
- **Zonas de SERIALIZAÇÃO (saem do paralelo) ⚠️:** (1) DS compartilhado (`resources/js/Components/**`, `Layouts/AppShellV2.tsx`) e (2) rebaseline de `config/*baseline*.json` / `.*-baseline.json` → viram **1 PR de FUNDAÇÃO sequencial ANTES** das telas (padrão FA-1..5). Telas só paralelizam DEPOIS que a fundação estabilizou — senão merge-conflict determinístico no baseline (incidente real #2495).
- **Paralelo** só entre telas de arquivos disjuntos (suas próprias Pages + controllers distintos).
- **Portão escalável:** 1ª aplicação de cada tela = screenshot 1280/1440 light+dark → **Wagner aprova o SCREENSHOT** (não a tabela). Re-aplicação de tela já aprovada = gate automático `contrato-de-tela`/pixel-diff; olho do Wagner só quando o diff excede limiar (senão o portão serializa TUDO no Wagner). `pr-ui-judge` + `visual-regression` + `contrato-de-tela` no CI.
- **Canary + rollback (4-bis):** cada onda atrás de flag (`APLICAR_<TELA>=true`), canary biz=1 antes de biz=4; rollback = LIFO da fila de merge. Payload `Inertia::defer` novo SEMPRE nasce com guard (lição caixa-unificada #2515).

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

## Limitações conhecidas + maturação (adversário + benchmark 2026-06-22)
Endurecido por red-team adversarial + comparação com métodos consagrados:
- **Esqueleto sólido / SOTA** no que é caro de copiar: orquestração agêntica (~90%, espelha orchestrator-worker Anthropic) + spec-anchored (~95% — o `anchor-lint` com estado `zombie` **supera** o paper arXiv 2602.00180).
- **Atrás** no que é commodity comprável: ponte design↔código (~30% — sem Figma Code Connect; mitigado pelo `<tela>.map.json` da Fase 1) e tokens (~35% — `oklch→Tailwind` na cabeça do agente, sem DTCG/Style Dictionary). Detalhe: [memory/sessions/2026-06-22-arte-design-to-code-sdd.md](../memory/sessions/2026-06-22-arte-design-to-code-sdd.md).
- **Gaps de MECANISMO (a fazer):** das 5 flags de governança da Fase 2, só 2 têm gate (Tier 0 required, contrato-de-tela advisory); 3 são lembrete sem check (silenciado/ADR-pendente/cliente-sinal). Fix: `silenced: true` no front-matter do BRIEFING + check CI que barra PR tocando `Pages/<Mod>/` de módulo silenciado.
- **Roadmap de adoção (impacto×esforço):** #1 `<tela>.map.json` (já no RUNBOOK) → #2 tokens DTCG/Style Dictionary → #3 Storybook + VRT como pré-filtro do gate humano → #4 tornar `contrato-de-tela` required quando maduro.

## Refs
- [`PROTOCOL.md`](PROTOCOL.md) (loop Cowork↔Code, ADR 0282 v2) · [`PROCESSO_MEMORIA_CC.md`](PROCESSO_MEMORIA_CC.md) · [`LICOES_F3_FINANCEIRO_REJEITADO.md`](LICOES_F3_FINANCEIRO_REJEITADO.md)
- [`RUNBOOK-replicar-prototipo-cowork.md`](../memory/requisitos/_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md) — mecânica por-tela
- ADR 0104 (MWART) · ADR 0114 (loop Cowork) · ADR 0282 (protocolo v2) · ADR 0297 (anchor-lint fidelidade) · ADR 0093 (Tier 0) · ADR 0105 (cliente-sinal)
- Skill `aplicar-prototipo` (dispara este RUNBOOK)
