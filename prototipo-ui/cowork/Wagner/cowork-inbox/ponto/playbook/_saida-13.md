---
sessao: "13"
titulo: Paridade de FORMA — Painel (Dashboard/Index.tsx)
dono: "[CL]"
base: 5606344ca
---
# _saida-13 · Painel — a forma do protótipo na Page real

## Pré-condições (medidas no `main` 5606344ca antes de editar)
- ds-atomos 01 no `main`: `Components/ui/card.tsx` tem `badge`/`note`/`flush` e `CardAction`. ✅
- ds-atomos 02 no `main`: `Components/shared/KpiCard.tsx` tem `variant="filter"` + `filterTone`. ✅
- "antes" da thread: `Dashboard/Index.tsx` **sem** `variant="filter"` (0 ocorrências). ✅ — a âncora mudou desde a escrita da thread (era 20.313 B / `19c5ad41dc7c`; medi 19.644 B / `f4d2e9408a32`, pelo #8116), mas o "antes" que importa continuava valendo.
- guarda: `_components/{PresenceStrip,ActivityFeed,AlertInbox}.tsx` presentes e **sem diff** neste PR. ✅
- contrato `ponto-painel.contract.json` presente, sem conflito com o alvo (copy e ordem inalteradas). ✅

## Contagem por `data-contract` (fonte `.tsx`)
| `data-contract` | antes | depois |
|---|---:|---:|
| `painel-nota-fechamento` | 1 | 1 |
| `painel-kpis` | 1 | 1 |
| `painel-fila-aprovacoes` | 1 | 1 |
| `painel-atividade` | 1 | 1 |

Ordem igual ao contrato nos dois lados. Nenhuma seção saiu (o gráfico 7 dias, a presença e os alertas, que só existem na produção, ficam).

## O que mudou (só `resources/js/Pages/Ponto/Dashboard/Index.tsx`)
1. **6 tiles → `KpiCard variant="filter"`**, com o tom na placa do ícone (`filterTone`), não no card. A âncora (`KpiFilterTile` do DS) pinta fundo e borda neutros e o valor em `foreground`; por isso `tone` e `size="compact"` saíram.
2. **Fila de aprovações:** a contagem `(N pendentes)` saiu do `CardDescription` e foi para o `badge` do `CardTitle`; o "Ver fila completa" foi para o `CardAction`. O título agora não disputa a linha com a contagem.

## Tons aplicados — e onde divergem do texto da thread
Fonte: o `tom` de cada `<Kpi>` em `ponto-page.jsx` passado pelo `TOM_KPI_FILTRO` de `ponto-ui.jsx`.

| tile | protótipo (código) | texto da thread | aplicado |
|---|---|---|---|
| Colaboradores ativos | sem `tom` → `primary` | primary | primary |
| Presentes agora | `ok` → emerald | emerald | emerald |
| Atrasos hoje | `warn` → amber (fixo) | amber | amber |
| Faltas hoje | `neg` → rose (fixo) | rose | rose |
| HE do mês | `acc` → violet | violet | violet (renderiza igual a primary — ver docblock do `KpiCard`, token de roxo secundário é decisão [W]) |
| Aprovações pendentes | `pendentes ? warn : ok` → **amber / emerald** | **primary** | **amber / emerald** |

**Divergência no último tile:** o texto da thread diz `primary`; o código do protótipo, que é o alvo medido pela thread 32 (2 pendentes no build), sai amber. Segui o protótipo (eixo FORMA, UI-0029). Se a intenção era `primary`, a mudança nasce no Cowork e desce.

## O que ficou de fora, e por quê
- **`aria-pressed` nos tiles (D5):** não passei `selected`. No Painel os tiles **navegam** para outra tela, não filtram a própria — não há estado de filtro para refletir, e anunciar "botão de alternância, não pressionado" num botão que troca de página é semântica falsa. Onde houver filtro de verdade (Espelho, Aprovações), `selected` entra.
- **Card "Atividade recente" com `badge`/`note`:** o card é o `ActivityFeed` (`_components/**`, fora do escopo por `nao_toca`). Segue com título/subtítulo próprios.
- **Nota de fechamento como `Alert` tintado (C1):** o passo a passo da thread não a inclui; ficou como está (`bg-warning-soft`). Candidata a thread própria.
- **Grade `gap:10px` / trilha ~194px (C2):** o `KpiGrid cols={6}` só abre 6 colunas em `2xl` por medição de 2026-08-24 (rótulos truncavam a 1280). Não mexi no `KpiGrid` (fora do prefixo). A 1280 o painel segue em 3 colunas.
- **Ícones:** mantidos os já usados pela Page (`users`, `user-check`, `clock-alert`, `user-x`, `trending-up`, `check-check`); o protótipo usa nomes genéricos do `JcIcon`.

## Provas
- `Dashboard/Index.tsx` contém `variant="filter"`: 6 ocorrências.
- `_components/PresenceStrip.tsx` presente, sem diff.
- `PontoDashboardContratoTest` e `contrato-de-tela` (`ponto-painel`): no CI do PR.
- `tsc --noEmit`: nenhum erro em `Ponto/Dashboard` (os erros restantes do projeto são anteriores, ex. `ssr.tsx`).

## PLACAR
entregue 2 de 4 itens da forma (tiles filtro com tom · badge da fila) · ausentes: `aria-pressed` por semântica (tiles navegam) · badge/note da Atividade por `nao_toca` (`_components/**`) · Alert da nota fora do passo a passo · trilha de 6 colunas a 1280 por `KpiGrid` fora do prefixo.
