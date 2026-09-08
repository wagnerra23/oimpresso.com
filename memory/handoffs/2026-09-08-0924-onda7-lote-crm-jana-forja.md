---
date: "2026-09-08"
time: "09:24 BRT"
slug: "onda7-lote-crm-jana-forja"
tldr: "Onda 7 lote Crm+Jana+Forja fechado: parityLinked 18 → 27 no PR (#6974, merged), 9 vínculos declarados e 8 órfãos mantidos por veredito. A medição de RUNTIME (passo 5 do 7a) NÃO rodou — sem lado vivo no ambiente e Jana fora do universo do design-diff-lote — logo nenhum veredito de paridade foi emitido. Os 3 PRs mergearam; fica aberto a dívida de related_us em 5 charters e 3 achados que são decisão [W]."
decided_by: ["W"]
cycle: null
prs: [6974, 6979, 6984]
next_steps:
  - "Decidir se Cliente/Index passa a apontar pro inventário do drawer 760 (achado C)"
  - "Decidir a US de cada um dos 5 charters sem related_us (dívida advisory declarada)"
  - "Passo 5 do 7a segue aberto: medição de runtime exige lado vivo + Jana no application-report"
related_adrs: ["0130-handoff-append-only-mcp-first", "0294-metodo-dual-track-shapeup-catraca", "0344-two-strikes-cobre-processo"]
---

# Onda 7 — lote Crm+Jana+Forja: o contador andou, a paridade não foi medida

> Trabalho detalhado no [session log](../sessions/2026-09-08-onda7-paridade-crm-jana-forja.md).
> Método da onda: [7a](../requisitos/_Governanca/programa-ondas/onda-7-paridade-prototipo/7a-inventario-e-metodo.md) ·
> resultado do lote: [7b](../requisitos/_Governanca/programa-ondas/onda-7-paridade-prototipo/7b-lote-crm-jana-forja.md).

## Estado dos PRs

| PR | o quê | estado |
|---|---|---|
| [#6974](https://github.com/wagnerra23/oimpresso.com/pull/6974) | lote Crm+Jana+Forja — 9 vínculos + doc 7b | **merged** (`265ac37b9a`) |
| [#6979](https://github.com/wagnerra23/oimpresso.com/pull/6979) | linha da Onda 7 no `PLANO-MESTRE` | **merged** (`5ebcc91f4a`) |
| [#6984](https://github.com/wagnerra23/oimpresso.com/pull/6984) | ledger LC-08 145 → 146 | **merged** |


> ⚠️ **O contador do LC-08 não é 146 no main — é 148.** Uma sessão paralela incrementou a mesma
> classe no mesmo dia. Os DOIS lados do merge diziam `146` com recibos **diferentes**; a
> reconciliação uniu e **recontou com sonda** (não somou, não herdou), e o comentário do campo
> registra isso. Campo 148 = 148 recibos contados. Quem ler o corpo do #6984 vai ver `146`:
> era verdade na base daquele PR, e deixou de ser no merge.

## O que a próxima sessão precisa NÃO confundir

**O eixo que andou é VÍNCULO DECLARADO, não paridade MEDIDA.** O `design-coverage` responde
*"a tela declara de onde vem o design e o inventário tem dono?"* — não *"a tela bate com o design?"*.
Ler "Onda 7 andou" como "as telas estão em paridade" é o erro que o 7b e a linha do PLANO-MESTRE
foram redigidos pra impedir. As telas estão **rastreáveis**, não conferidas.

**Não repita a contagem daqui.** O número muda a cada merge — subiu de 64 pra 66 dentro desta
mesma sessão, enquanto eu escrevia. A porta viva é `node scripts/qa/design-coverage.mjs`
(§5 2026-07-17). O que este handoff afirma é fato datado: no fechamento de 2026-09-08 o
`parityLinked` saiu de **18** e os quebrados ficaram em **0**.

## Três decisões que são [W], não minhas

1. **`Cliente/Index` aponta pro inventário superado.** O charter declara o de 2026-05-15 (drawer
   **480px**, gate pendente); medido no código, `Cliente/Index.tsx:1953` renderiza
   `w-[760px] sm:max-w-[760px]`. Não mexido porque [W] proibiu tocar nos charters de Cliente já
   vinculados — e trocar **não move o contador** (um sobe, o outro vira órfão).
2. **A US de 5 charters sem `related_us`.** Tocar charter legado acordou o
   `charter related_us join` (**advisory**). Os 5 inventários de `TeamMcp/` não têm campo
   `stories:` — sem fonte pra derivar. Inventar id seria anti-padrão que parece canon.
3. **Se a Jana entra no `application-report`** ou se as 3 medições saem uma a uma por
   `design-diff.mjs --probe`. Hoje o harness não a enxerga (achado D).

## Armadilhas medidas nesta sessão (custam tempo a quem repetir)

- `git ls-files 'memory/requisitos/Crm/**/*visual-comparison*.md'` **cega pasta** — devolveu 4 no
  Crm e 0 em Jana/Forja. A varredura completa achou 20.
- `application-report.json` tem `screens[93]` **e** `transportChanges[127]`: pegar "o primeiro
  array" devolve o errado, e `jana-merge` aparece no segundo sem existir no primeiro.
- `gh pr checks --watch` sai **exit 0** com checks ainda `pending`. O veredito são os check-runs
  do SHA atual.
- O `charter-us-gate` tem `types: [opened, reopened, ready_for_review]` — **sem `synchronize`**.
  Ele não re-roda em push, e o check-run fica preso ao 1º SHA. `gh pr checks` verde não significa
  "sem dívida": significa que o gate que a mediria não está olhando. O `paths:` dele também cobre
  só `resources/js/Pages/**`, não `Modules/*/Resources/js/Pages/**`.

## Estado MCP no momento do fechamento

⚠️ **As tools MCP não estão conectadas como tools nesta sessão** — o checklist foi rodado por HTTP
contra `mcp.oimpresso.com/api/mcp`, reusando `resolveSettingsPath`/`readAuthHeader`/`extractBrief`
exportados pelo hook `brief-fetch-curl.mjs` (o dono), não por implementação paralela.

- **`cycles-active`** → *"Nenhum cycle ATIVO em COPI."*
- **`whats-active`** → *"Nenhuma sessão Claude Code vista nas últimas 2h — MAS o pipeline de ingest
  está SEM heartbeat fresco (fresh=0 · stale=0 · dead=272)."* ⚠️ Ele mesmo declara que pode estar
  **cego**: não tratei isso como prova de escopo livre. As sessões irmãs do lote existiram e
  mergearam (7 PRs, #6971–#6977) sem que o `whats-active` as visse.
- **`my-work` @wagner** → 30 tasks ativas: **1 DOING** (US-DOC-001), **10 REVIEW**, **14 BLOCKED**
  (topo: `HITL-LLM-QUOTA` p0 — provedor LLM sem crédito, Jana/brief/PR UI Judge mudos).
- **`sessions-recent limit:3`** → os 3 últimos indexados são de 2026-08-22 (estado-da-arte de
  agentes/escala/fidelidade), indexados em 2026-09-08.
- **`decisions-search "paridade prototipo producao design-coverage"`** → 4 ADRs: 0388 (réplica
  primeiro), 0247, 0325, **0290 (fidelity lock RECUSADO)**.

## Não fiz, de propósito

Não criei task no MCP pro que ficou aberto: as três pendências acima são **decisão [W]**, e
`tasks-create` sem o dono ter decidido geraria backlog de hipótese (ADR 0105).
