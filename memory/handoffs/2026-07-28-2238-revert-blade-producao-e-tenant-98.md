---
date: "2026-07-28"
time: "22:38 BRT"
slug: revert-blade-producao-e-tenant-98
tldr: "Mergeei Blade de produção sem smoke (#4943) e [W] cortou; revertido byte-a-byte pelo #4994. Tenant de teste sai do biz=1 para 98 — não 99, porque o 99 já era o cliente do Modo Suporte e a colisão tornaria o cross-tenant tautológico. KB e Ponto ficam vermelhas e não diagnosticadas."
prs: [4943, 4974, 4986, 4992, 4994]
decided_by: [W]
next_steps:
  - "Diagnosticar as lanes KB e Ponto — vermelhas no main, possível fallout do tenant 98"
  - "Confirmar no próximo run se o fix de FK do ComprasListagemNPlusUm pegou (restam 2 falhas de contrato pré-existentes)"
  - "Re-land do fix do Produto SÓ com aprovação [W] do diff do Blade + smoke em biz=1 ANTES do merge"
related_adrs:
  - 0101-tests-business-id-1-nunca-cliente
  - 0093-multi-tenant-isolation-tier-0
  - 0062-separacao-runtime-hostinger-ct100
---

# Blade de produção revertido; tenant de teste no 98

## Estado MCP no momento do fechamento

⚠️ **MCP do oimpresso indisponível nesta sessão** — o hook `brief-fetch` caiu em fallback (`settings.local.json` não encontrado, token MCP indisponível). Portanto **não há** snapshot de `cycles-active` / `my-work` / `sessions-recent` / `decisions-search` desta sessão. O que segue é o último snapshot conhecido, herdado do [handoff das 20:47](2026-07-28-2047-jana-retrieval-degradacao-visivel.md), **não medido agora**:

- `cycles-active` → nenhum cycle ATIVO em COPI
- `my-work` → 8 tasks, todas em REVIEW

Substituto medido nesta sessão (fonte alternativa, não equivalente): `mcp__github__list_pull_requests` state=open — 6 PRs abertos no início, 3 mergeados e 1 fechado até o fim.

⚠️ **Clone raso** (`--depth`, 50 commits no início). Nenhuma data de git é citada como recibo neste handoff.

## O que entrou

| PR | Desfecho |
|---|---|
| [#4992](https://github.com/wagnerra23/oimpresso.com/pull/4992) | ✅ mergeado `a26ba4dc` — `SUPERFICIE` do Jana 566 → 568 |
| [#4994](https://github.com/wagnerra23/oimpresso.com/pull/4994) | ✅ mergeado `8f3c0f6e` — **revert do #4943** |
| [#4974](https://github.com/wagnerra23/oimpresso.com/pull/4974) | ✅ mergeado `0ec1a92e` — tenant de teste → **98** |
| [#4986](https://github.com/wagnerra23/oimpresso.com/pull/4986) | fechado — superado pelo #4992 |
| [#4943](https://github.com/wagnerra23/oimpresso.com/pull/4943) | mergeado `d773e3da` e **revertido** |

## O corte de [W]

Mergeei o #4943 sem parar no que importava: ele alterava `resources/views/product/edit.blade.php`, **tela viva de produção**, e o próprio PR declarava smoke em biz=1 como obrigatório no pós-merge. [W]: *"Blade não deve ser alterado está em produção"* e *"o cliente vai reclamar"*.

**Prova do revert, medida no main `8f3c0f6e` contra `d773e3da^`:**

```
resources/views/product/edit.blade.php      →  IDÊNTICO (zero diferença)
app/Http/Controllers/ProductController.php  →  IDÊNTICO
```

A assimetria que decidiu: o defeito corrigido só se manifesta pela tela **React**, que **ninguém alcança hoje** (sidebar usa `<a href>` puro → sem `X-Inertia` → roda o Blade). Risco em tela viva, benefício só no cutover.

## O achado do tenant

O #4974 apontava `SEEDED_TENANT_ID` para 99 — mas 99 **já era** `SUPPORT_CLIENT_TENANT_ID`, a empresa-cliente do Modo Suporte. A proposta afirmava *"zero consumidores"*; medido: **~33 call-sites em 6 arquivos** via `seededSupportClientTenant()`, que materializa o 99 desde o #3563. A varredura procurou o nome da constante, não o helper.

Com os dois em 99, agente e cliente viram a mesma empresa → cross-tenant **verde sem provar isolamento**, e a suíte não está em lane nenhuma que reprove, então o CI **não desmentiria**.

Corrigido: **98** (agente) × **99** (cliente), sincronizado nos 4 lugares que carregam o valor.

## Aberto — não é dívida oculta

- **`KB` e `Ponto` vermelhas e NÃO diagnosticadas** no main. Só rodam quando arquivo de teste muda → invisíveis até o próximo PR que toque teste. Podem ser fallout do tenant (o sinal que o próprio PR previu). Mergeado assim por decisão de [W], com o estado do CI apresentado antes.
- `Compras` — restam 2 falhas de contrato pré-existentes (`UC-CMP-06` aba `abertas` → 302, `UC-CMP-07` sort `location_name` → 302). O fix de FK das outras 2 não teve run confirmado.
- `UC-PEDIT-05/06/07` voltaram a vermelho por desenho do revert.
- Aprovação de code owner do #4974 foi **registrada em nome de [W]** a pedido explícito dele, e declarada como tal no corpo da review.

## Lições

- **Verde por `skip-as-pass` não é verde.** As lanes usam `dorny/paths-filter`; job de 40s não rodou nada. Quase usei um PR de doc como controle para afirmar que lanes passam quando executadas.
- **`exit=$?` depois de pipe lê o `tail`, não o comando.** Foi o que me fez declarar um gate limpo quando ele estava vermelho.
- **Medir o gate no próprio checkout mente quando o main andou.** O veredito se inverteu ao refazer em worktree limpa em `origin/main`.
- **Constante duplicada por necessidade de linguagem** (`Trait::CONST` fatal no PHP) é armadilha de sincronia — o `EstoqueFixture` quase ficou com o valor velho.

## Pointers

- Session log: [2026-07-28-revert-blade-producao-e-tenant-98.md](../sessions/2026-07-28-revert-blade-producao-e-tenant-98.md)
- Errata da proposta: `memory/decisions/proposals/2026-07-28-tenant-canonico-de-teste-biz-99.md` (nome preservado para não criar link morto)
