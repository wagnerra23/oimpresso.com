---
date: "2026-07-28"
slug: 4o-eixo-visreg-residuo-pago
tldr: "Continuação do handoff 1750: os 4 PRs MERGEARAM, 42 → 13 arquivos com o nome velho e os 13 estão contabilizados. Achado: a classe 'tocar legado acorda gate diff-aware' tem um QUARTO eixo (fail-closed do `ui-impact`/visreg) — e o #4886 só escapou dele por ACIDENTE, por tocar um Component compartilhado que forçou `scope: global`. A emenda §5 mergeada cobre só 3 eixos; o 4º ficou pendente [W]. 7 vermelhos de CI na noite, ZERO defeito real."
time: "07:30"
topic: "O 4º eixo (visreg fail-closed) + resíduo de schema pago por transcrição + 7 vermelhos de CI que não eram defeito"
authors: [C, W]
type: handoff
module: Jana
pii: false
prs:
  - 4886
  - 4895
  - 4896
  - 4897
  - 4898
us: []
related_adrs:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0341-memory-schema-charter-spec-required
next_steps:
  - "[W] decidir se estende a lápide §5 com o 4º eixo (visreg) — a emenda mergeada documenta só 3"
  - "[W] decidir os 2 .tsx restantes: exigem contrato visreg = baseline de pixel + aprovação visual F1.5"
  - "[W] decidir os 5 charters sem related_us — exige CRIAR US novas (varredura nos 59 SPECs: nenhuma existe)"
---

# Handoff — o 4º eixo + resíduo pago

> Continuação de [`2026-07-27-1750`](2026-07-27-1750-permissao-jana-mcp-usage-doc-3-eixos.md). Aquele fechou com 4 PRs abertos; **os 4 mergearam**.

## Onde parou

| PR | conteúdo | estado |
|---|---|---|
| [#4886](https://github.com/wagnerra23/oimpresso.com/pull/4886) | 51 menções em 31 arquivos | **MERGED** (34/34 required) |
| [#4895](https://github.com/wagnerra23/oimpresso.com/pull/4895) | session log + handoff + índice | **MERGED** |
| [#4897](https://github.com/wagnerra23/oimpresso.com/pull/4897) | emenda §5 (3 eixos) | **MERGED** |
| [#4898](https://github.com/wagnerra23/oimpresso.com/pull/4898) | RUNBOOKs + BRIEFING | **MERGED** |
| [#4896](https://github.com/wagnerra23/oimpresso.com/pull/4896) | v1 do resíduo | fechado → substituído pelo #4898 |

**42 → 13 arquivos** com o nome velho, e os 13 estão **todos contabilizados**:

- **5 história** — 3 ADRs aceitas + 1 findings datado + 1 session log de abril (append-only; registram a permissão correta na data)
- **3 erratas** — `Forja/Cockpit.{casos,charter}.md` + `TeamMcp/SDD-tela-hub-team-mcp-v1.0.md` (este nasceu em 2026-07-28 no #4905, já como errata). Citam o nome antigo **de propósito**; trocar destruiria o sentido
- **3 docs meus** que descrevem a mudança — handoff 1750, session log, `proibicoes.md`
- **2 resíduo real** — `Forja/Cockpit.tsx` + `Scorecard/Index.tsx`, bloqueados pelo 4º eixo (abaixo)

## O achado: existe um 4º eixo, e eu quase não o veria

A [emenda §5 que mergeou](../proibicoes.md) documenta **3 eixos** da classe *"tocar legado acorda gate diff-aware"*. O CI achou um **quarto**:

```
ui-impact: fail-closed: telas afetadas sem contrato em
tests/Browser/visreg-screens.json: team-mcp/Forja/Cockpit, team-mcp/Scorecard
```

Só **7 das 280** Pages têm contrato visreg. A regra vale **só em `scope: targeted`** ([`ui-impact.mjs:283/402`](../../scripts/governance/ui-impact.mjs)); em `global` o pixel roda o núcleo-6 e ignora telas sem contrato.

**A parte que importa pro método:** o #4886 tocou **14 Pages `.tsx`** e passou o `visual-regression` verde. Eu usei isso como evidência de que *"tocar Page `.tsx` não dispara visreg"*. **Era correlação enganosa** — o verde vinha de ele ter tocado `resources/js/Components/MentionInput.tsx`, que força `scope: global` ([linha 147](../../scripts/governance/ui-impact.mjs), `frontend-compartilhado`). Pages **não** são isentas; o #4886 só tinha um componente compartilhado junto por acaso.

É o mesmo vício da varredura parcial que a própria emenda documenta, **uma camada mais fundo**: não bastou enumerar os gates — eu tirei conclusão de um PR verde sem checar **por que** ele estava verde.

**Pendente [W]:** estender a §5 com este 4º eixo. Não fiz — canon append-only, e a autorização *"pode fazer todos"* veio **antes** da descoberta.

## Resíduo pago sem inventar (#4898)

Os 2 RUNBOOKs da Jana exigiam `owner` + `last_validated` + `status` no enum. Paguei por **transcrição do que o próprio doc declara**:

| campo | valor | fonte |
|---|---|---|
| `owner` | `W` | SPEC da Jana declara `owner: wagner` |
| `status` | `ativo` | tradução de `active` pro enum |
| `last_validated` | `2026-05-05` / `2026-05-09` | prosa *"Validado: portado Cockpit"* / campo `date:` |

**Não bumpei pra hoje** — não rodei os runbooks, e o schema define o campo como *"data que rodou o RUNBOOK e o resultado bateu — dispara alerta se >30d"*. As datas reais **disparam o alerta**, e isso é o resultado **correto**.

O `BRIEFING` do ProjectMgmt não tinha dilema: os 4 campos já existiam sob nomes antigos (`modulo`, `owner: Wagner [W]`, `updated`). Rename puro.

**Correção de um julgamento meu:** recusei bumpar `last_run` dos `casos.md` por princípio, sem checar precedente. Medi depois: **13 de 13** commits que tocam `.tsx` com `casos.md` irmão bumpam o campo, inclusive mudanças visuais. Na prática ele significa *"trio reconciliado nesta data"*. A recusa era coerente mas fora de passo com a convenção real — diferente do `last_validated`, onde o schema define semântica de verificação e a recusa se sustenta.

## 7 vermelhos, 0 defeitos

| causa | ocorrências | assinatura |
|---|---|---|
| base móvel | 3 | `main` andou 6→8→18 commits; baseline do casos **encolheu** (catraca só-desce) e a branch carregava o anterior → parecia crescimento |
| infra Docker Hub | 3 | `Initialize containers` / `docker pull mysql:8.0` com `Client.Timeout` × 3 retries; **checkout SKIPPED** |
| evento de SHA morto | 1 | monitor reportou falha de commit já superado |

**Método que evitou retrabalho em todos:** comparar o SHA da falha com o `headRefOid`, ler **o step que realmente falhou** (não o rótulo do comentário), e conferir o mesmo check nos PRs irmãos. Dois rótulos mentiram: o `visual-regression` disse *"Screenshots têm diff > threshold"* quando **nenhum screenshot foi tirado**, e o `baseline-tamper-guard` acusou *"grandfatherou violação nova"* num PR que **não toca baseline nenhum**.

## Erros meus registrados

1. **Correlação enganosa do #4886** (acima) — a mais séria; fabricou confiança falsa sobre um gate.
2. **`grep -qF "$l"` com linha começando em `- `** — parseia o traço como opção e acusou **11 entradas "PERDIDAS"** do índice de handoffs que estavam intactas. Refeito em node: 472 no main, **0 perdidas**, 473 no total. Se eu tivesse agido no output, teria "consertado" um problema inexistente.
3. **Near-miss no índice** — minha edição do `08-handoff.md` ia **apagar a entrada de outra sessão** (19:35). Peguei conferindo `git diff origin/main` antes de commitar; depois o mesmo arquivo deu conflito real (17:55 vs 17:50) e resolvi mantendo **as duas**.

Os três são da mesma família **LC-08**: o instrumento respondeu, devolveu número, e respondia uma pergunta **parecida** com a feita.

## Estado MCP no fechamento

- `my-work` (@wagner): **10 tasks**, todas REVIEW — US-TR-309/310/305/306/311/307, US-PG-008, US-PROD-027/025, US-FIN-023 (inalterado vs 1750)
- `cycles-active`: não re-consultado nesta metade (deu timeout MCP -32001 no fechamento anterior)
- 4 PRs desta sessão: **todos MERGED**
- Proteção do main: 34 required · `strict: false` · `enforce_admins: true`

## Pendências [W]

1. Estender a §5 com o **4º eixo** (visreg fail-closed) — a emenda mergeada cobre 3
2. Os **2 `.tsx`** exigem contrato visreg = baseline de pixel + **aprovação visual F1.5**
3. Os **5 charters** sem `related_us` exigem **criar US novas** — varredura nos 59 SPECs confirmou que nenhuma existe (`DetailSheet` cai sob `PMG-004`, que não casa `^US-`)

Session log da sessão: [`2026-07-27-permissao-jana-mcp-usage-doc-3-eixos.md`](../sessions/2026-07-27-permissao-jana-mcp-usage-doc-3-eixos.md).
