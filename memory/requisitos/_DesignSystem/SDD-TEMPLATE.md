---
id: requisitos-design-system-sdd-template
---

# SDD-TEMPLATE.md — Template canônico de Software Design Document (por domínio/família de telas)

> **Extraído do exemplar** [`Produto/SDD-tela-cadastro-produto-v1.0.md`](../Produto/SDD-tela-cadastro-produto-v1.0.md) (v1.0.2) — formato provado 1×, não inventado aqui.
>
> **Onde vive:** `memory/requisitos/<Mod>/SDD-<escopo>-v<N>.md` (1 por domínio/família de telas, **não** 1 por tela).
> **Quem preenche:** agent [`sdd-from-source`](../../../.claude/agents/sdd-from-source.md) ([ADR 0351](../../decisions/0351-sdd-from-source.md)) gera §5 e §6 derivando das 3 fontes; **[W] confere** §6 e é o único que preenche Non-Goals (§6.4).
> **Quem confere:** `casos-gate` ([ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)) + `anchor-lint` ([ADR 0273](../../decisions/0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md)) — ambos **required**.
>
> **O SDD não substitui nada.** Ele é o mapa de cima: o `SPEC.md` guarda as US, os `*.charter.md` guardam a lei por tela, os `*.casos.md` guardam o contrato de teste. O SDD amarra os três e é **de onde o UC deriva** — nunca do `.tsx`.

## Duas coisas chamadas “SDD” — não misturar

- **SDD como processo** (*Spec-Driven Development*) é a cadeia inteira: US no `SPEC` → contrato
  da feature (`requirements/plan/tasks`) → CU deste documento → UC de tela → teste → âncora da US.
- **`SDD-*.md` como documento** é somente o mapa durável do domínio/família: arquitetura (§5),
  fluxos e casos de uso (§6). Ele **não** é o plano de execução de uma feature.

O trio `requirements.md` + `plan.md` + `tasks.md` nasce por **feature**, não por tela. Uma feature
pode atravessar várias telas; uma tela pode receber várias features. Automatização:

```bash
npm run sdd:init -- <Mod>/<slug> --us US-<MOD>-<NNN> --sdd auto --cu CU-<MOD>-NN --screen <Mod>/<Tela>
npm run sdd:flow -- <Mod>/<slug>
```

`sdd:flow` é um recibo/orquestrador: não reimplementa `feature-lint`, `anchor-lint`,
`ancora-codigo-sync` ou `casos-gate`; ele mostra os elos e consome a máquina autoritativa de
cada prova. O hash é o smart token Git já adotado no projeto: `verificado@<sha7>`. O recibo usa
`anchor-lint --stale` (com base derivada quando houve squash) na US e
`ancora-codigo-sync --check --require-stamp` nas referências `arquivo:linha` dos documentos
exclusivos da feature/tela. Não existe um segundo hash de conteúdo concorrente.

> **Nome legado:** vários arquivos ainda se chamam `SDD-tela-*`. O nome não muda a unidade e não
> autoriza criar um por tela. Novos documentos devem preferir `SDD-<dominio>-v<N>.md`; renomear os
> legados em massa é proibido sem plano de realocação, porque quebraria ponteiros vivos.

---

## Regras duras (violá-las torna o SDD pior que a ausência dele)

| # | Regra | Por quê |
|---|---|---|
| 1 | **§6 CU deriva das 3 fontes**, nunca só do React atual | documentar só o presente carimba a feature perdida na migração como se fosse o correto ([ADR 0351](../../decisions/0351-sdd-from-source.md) D-A) |
| 2 | **UC do `casos.md` deriva do §6 CU**, nunca do `.tsx` | teste derivado do código é tautológico e **trava o desvio** ([proibicoes §5](../../proibicoes.md) 2026-06-05) |
| 3 | **ZERO arquivo novo** — fluxo mora no §5, CU no §6 | gerar `ANALISE-*.md`/`FLUXO-*.md` paralelo é bug ([ADR 0351](../../decisions/0351-sdd-from-source.md) D-B) |
| 4 | **Número que outro sistema sabe → ponteiro ou recibo datado** | "3.016 documentos" à mão eram 1.408 ([proibicoes §5](../../proibicoes.md) 2026-07-17) |
| 5 | **Status vem do veredito do teste, não da leitura** | o `✅ (reusa guard)` do CU-PROD-10 era falso; reprovou na 1ª execução |
| 6 | **Falta fonte → PERGUNTAR ao [W]** | anti-padrão inventado é pior que ausente: parece canon |
| 7 | **Correção não apaga — vira changelog** | o erro fica visível (append-only de fato, não de forma) |

**Ordem de fonte** (fixa — [how-trabalhar §Pedido de tela](../../how-trabalhar.md)): documentação canon (SPEC/charter/ADR) → código oimpresso (confirma, nunca deriva) → Delphi legado (`ANTI-REGRESSAO-*.md`, contrato de paridade) → concorrentes (traduzir premissa, **nunca copiar solução** — [proibicoes §5](../../proibicoes.md) 2026-07-16).

---

## Frontmatter canônico (YAML)

```yaml
---
id: requisitos-<modulo>-sdd-<escopo>-v<N>-<M>      # kebab, estável (linkagem por id)
slug: <modulo>-sdd
title: "SDD — <Escopo> (domínio <Modulo>)"
type: sdd
module: <Modulo>
status: ativo                                      # ativo | rascunho | historical
owner: W
version: 1.0.0
last_updated: "YYYY-MM-DD"
related_docs:                                      # ponteiros, não cópias
  - SPEC.md
  - BRIEFING.md
  - _telas/RUNBOOK-<tela>.md
related_adrs:
  - NNNN-slug-da-adr
---
```

---

## Estrutura — 12 seções

Cada seção declara **de onde deriva** e **quem preenche**. Seção sem fonte fica com `⬜ não-verificado`; **não se inventa preenchimento**.

| § | Seção | Deriva de | Preenche | Badge |
|---|---|---|---|---|
| 0 | **Base empírica** — benchmark + o que ele expôs | `CAPTERRA-FICHA.md` + medição datada | agente | 🖐 curado (foto que envelhece) |
| 1 | **Visão geral** — o que é, família de telas, verticais | BRIEFING + rotas reais | agente | ⚙️ derivado |
| 2 | **Público-alvo e personas** | `memory/clientes/*/personas/` | [W] valida | 🖐 curado |
| 3 | **Governança aplicável** — Tier 0 que morde AQUI | `proibicoes.md` + ADRs | agente | ⚙️ derivado |
| 4 | **Design system aplicável** | `_DesignSystem/` + PT-0X + charter | agente | ⚙️ derivado |
| 5 | **Arquitetura** — camadas, modelo de dados, **fluxos críticos**, dívida | Controller→Service→Model (fonte 1) | agente | ⚙️ derivado |
| 6 | **Casos de uso** (`CU-<MOD>-NN`) | **as 3 fontes** (React + Blade + Delphi) | agente propõe / **[W] confere** | ⚙️+🖐 |
| 7 | **Requisitos não-funcionais** | `ux_targets` do charter + `OBSERVABILITY.md` | agente | ⚙️ derivado |
| 8 | **Estratégia de qualidade e rollout** | `casos.md` + gates + canary | agente | ⚙️ derivado |
| 9 | **Riscos e dívidas conhecidas** | gaps do INVENTARIO + `[BACKLOG]` dos casos | agente | 🖐 curado |
| 10 | **Roadmap de evolução** (por trilha) | SPEC US pendentes | [W] prioriza | 🖐 curado |
| 11 | **Referências** | ponteiros | agente | ⚙️ derivado |

> **Badge obrigatório por seção** (`⚙️ derivado` = re-rodável do fonte · `🖐 curado` = foto datada que envelhece). Sem badge, o leitor não sabe o que confiar depois de 3 meses — é o anti-apodrecimento do [ADR 0351](../../decisions/0351-sdd-from-source.md) Fase 2.5.

---

## §5 — Arquitetura (o esqueleto que o agente preenche)

```markdown
### 5.1 Visão em camadas
<rota → Controller@metodo → Service → Model → tabela. Caminho REAL, medido no fonte.>

### 5.2 Modelo de dados (núcleo)
<tabelas + colunas que importam + onde mora o business_id (Tier 0).>

### 5.3 Fluxos críticos
F1 <nome> — <passo a passo do que acontece de verdade, com arquivo:linha>
F2 ...

### 5.4 Onde os dois mundos ainda não se conversam
<dívida Blade↔React, legado↔novo. É aqui que a regressão da migração aparece.>
```

## §6 — Casos de uso (o coração — e o que o `casos.md` vai citar)

**Convenção dos marcadores:**

| Marcador | Significa | Consequência dura |
|---|---|---|
| `[must]` / `[should]` | prioridade | `must` **exige** teste ancorado |
| `[T0]` | invariante multi-tenant ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) | carimba `business_id` scope; teste biz=1, **nunca** biz=4 |
| `[V0]` | **REGRA MESTRE valor/estoque** | dupla-confirmação por 2 caminhos + tabela antes→depois antes de mergear |
| `[reg]` | paridade com o legado | some sem Non-Goal explícito = regressão |
| `[perf]` / `[ux]` | não-funcional | mede contra `ux_targets` do charter |

**Estado de cada CU** — vem do **veredito**, não da leitura:

`✅` provado por teste verde que o cita · `🟡` parcial (diga o quê) · `🔴` falso/quebrado · `⬜` **não-verificado** (nenhum teste o cita)

```markdown
#### CU-<MOD>-01 — <verbo + objeto> `[must]` ⬜
*Dado* <precondição>; *quando* <ação>; *então* <resultado observável>.
1. `[must]` <asserção verificável — vira nome de teste>
2. `[V0]` <se toca preço/custo/estoque>
3. `[T0]` <isolamento: cross-tenant por ID → 404>
```

**§6.4 Non-Goals** — **só [W] preenche.** O que a tela deliberadamente **não** faz. Cada item vira Pest GUARD; o agente é *proibido* de inferir.

---

## Fluxo de uso (1 PR por tela, forward-only)

```bash
npm run screen:files -- <Mod>/<Tela>   # mapa derivado — nunca Glob à mão (LC-08)
npm run casos:report                   # dívida de UC/teste
```

1. `sdd-from-source <Mod>/<Tela>` → §5 + §6 + `casos.md` + âncoras `**Implementado em:**` propostas
2. **[W] confere §6** contra as 3 fontes e preenche §6.4 Non-Goals
3. teste citando o UC, verde no **CT 100** (nunca local — [proibicoes](../../proibicoes.md))
4. `casos-gate` + `anchor-lint` → veredito ✓/🧪/❌ por US
5. dúvida que sobrou vira **tópico vivo** (`topicos/<id>.md`, [ADR 0345](../../decisions/0345-topicos-vivos-aprendizado-por-critica-revisada.md)) — não prosa no BRIEFING

**Nunca big-bang.** Tocar SPEC legado em massa acorda `anchor-lint` + scorecard e o PR morre ([proibicoes §5](../../proibicoes.md) 2026-07-12).

---

## O que NÃO fazer com este template

- ❌ **Gate "módulo tem SDD?"** — presence-gate, proibido ([proibicoes §5](../../proibicoes.md) 2026-07-01/09). A régua honesta já é required: US com âncora + UC com teste verde.
- ❌ **Doc paralelo de casos de uso** (`CASOS-USO-*.md` fora do §6) — nenhum gate o enxerga; o CU fica invisível.
- ❌ **1 SDD por tela** — o SDD é do domínio/família; a tela tem charter + casos.
- ❌ **Nota/percentual de completude do SDD** — número agregado sobre vereditos incomensuráveis ([proibicoes §5](../../proibicoes.md) 2026-07-17).
