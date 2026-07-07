# Sessão 2026-07-06 — Sweep D-14 repo-wide: visitas Inertia sem `only:` (classe irmã do defer-sweep)

## Contexto / gatilho
Wagner 2026-07-06 cravou: **"reload da página inteira é o antipadrão do sistema, NÃO PODE EM TELA NENHUMA"**. O fix canônico saiu na Financeiro/Unificado ([PR #3889](https://github.com/wagnerra23/oimpresso.com/pull/3889)): frontend `router.get(..., { only: [props-que-mudam] })` + controller com props estáticas-por-business em **closures** (lazy — nem rodam a query no partial reload).

Esta sessão varreu o repo inteiro atrás da **mesma classe de bug**: `router.get/visit/reload` de **navegação-de-estado** (filtro/sort/paginação/aba/período) **sem `only:`** → cada interação re-buscava a página inteira.

> **Classe IRMÃ, não duplicata:** o [defer-sweep de manhã](../handoffs/2026-07-06-1055-defer-sweep-repo-wide-19-telas.md) tratou `Inertia::defer` desreferenciado (backend manda defer mas frontend não pede via partial). Este sweep trata o outro lado: o frontend faz visita cheia (sem `only:`), então o partial nunca é pedido e o defer não economiza nada. Os dois juntos fecham o SPA-feel.

## Método
1. Scanner (`scan-d14.mjs`) sobre 347 call-sites em 139 `.tsx` → **85 ofensores** reais (navegação-de-estado sem `only:`), separados de falsos-positivos (navegação pra outra página, `only` shorthand já presente, mutação via GET, comentários).
2. Base fresca: worktree `claude/d14-only-sweep` a partir de `origin/main` (o checkout ativo estava −4849 commits).
3. Fan-out de **11 agents** Write/Edit-only, um por módulo/área isolada (sem overlap), cada um com o padrão de referência #3889 + regras Tier 0 no prompt. Zero git ops nos agents; parent consolidou.
4. Consolidação: **1 PR por módulo** (commit-discipline ≤300 linhas), branch fresca de `origin/main` + add seletivo.

## Regra do padrão (o que cada agent aplicou)
- **Frontend:** `only: [<props que mudam com o filtro>]` (rows/pagination/kpis-filtradas/filters-echo/labels). Manteve `preserveState/preserveScroll/replace`. Em alguns casos adicionou `preserveState:true` que faltava (senão o partial remonta e perde rascunho local).
- **Backend:** props **estáticas-por-business** que fazem query (dropdowns de contas/categorias/fornecedores/locations, headers) viram `fn () => ...` (closure) com comentário `// closure D-14`. No partial (`only:` que não as pede) **nem rodam a query, nem trafegam**. Load cheio (F5) roda igual.
- **Não tocar:** props já em `Inertia::defer` (regra 6) — só referenciar no `only:` quando mudam.

## Resultado — 74 arquivos, 12 PRs
| PR | Módulo | Telas | Closures novas |
|---|---|---|---|
| #3894 | Financeiro | 8 (Caixa, ContasPagar/Receber, Dashboard, Dre, Extrato, Fluxo, Relatorios) | Caixa.stats, ContaPagar.contas, Extrato.conta; Dre/Fluxo `array_merge($shape,…)`→chaves explícitas memoizadas |
| #3895 | Ponto | 8 | 0 (tudo já era `defer` — Wave 26) |
| #3896 | ProjectMgmt | 6 | Activity (authors/eventTypes) |
| #3897 | Jana | 5 | Qualidade, Roadmap, Chat (businesses/conversas) |
| #3898 | Essentials | 2 | 0 (já defer) |
| #3899 | Atendimento+Whatsapp | 4 | 0 (já defer) |
| #3900 | OficinaAuto | 1 (Vehicles) | VehicleController.kpis |
| #3901 | Repair | 2 | DeviceModels (brands/devices) |
| #3902 | MemCofre+Nfse | 2 | Inbox.counts |
| #3903 | core/admin | 8 (Auditoria, governance, Home, Produto, Manufacturing, team-mcp, ads, Admin×2) | governance, Home, Produto, Manufacturing |
| #3904 | ⚠️ VALOR/ESTOQUE | 6 listagens (Compras, Purchase, Sells/Caixa, Stock×2, Payments) | Purchase, Sell(CashRegister), Stock×2 |
| #3906 | OficinaAuto/Board (DRAFT) | 1 | — |

## Cuidados Tier 0 aplicados
- **Regra Mestre (valor/estoque):** PR #3904 marcado **NÃO MERGEAR sem dupla confirmação Wagner** + tabela de impacto no body. Prova de que nenhum arquivo de cálculo foi tocado (nada de `TransactionUtil`/`ProductUtil`/`num_uf`/`numberPtBR`/Create/Edit); `getListPurchases`, SUMs e maps de total byte-idênticos — só muda QUANDO dropdowns/CashRegister carregam.
- **Home:** agent achou gap pré-existente (filtro de loja é no-op server-side na versão Inertia — só funciona no `?legacy=1`). NÃO corrigido (mexe em VALOR exibido) — reportado.
- **ConversationList.tsx (Whatsapp):** parece órfão (CaixaUnificada usa `ConversationListV4`) — fix inofensivo, sinalizado.

## Pegadinhas / lições da consolidação
1. **`casos-coverage` G-6 (stale):** ContasPagar/ContasReceber TÊM `.casos.md` (o agent do Financeiro não os pegou no Glob). Mexer no `.tsx` deixa `last_run < data-do-commit-do-tsx` → violação NOVA. **Fix:** bump `last_run` para a data do commit + linha na Trilha do tempo. (Guard compara `git log -1 --format=%cs` do tsx vs `last_run`.)
2. **`casos-coverage` G-7 (stale-results) é ORTOGONAL a G-6:** telas com UC `Status: ✅` provado por e2e (ex: OficinaAuto/Board) — mexer no `.tsx` invalida o veredito do manifesto (`ran_at < tsxDate`), e **bumpar `last_run` NÃO cura**. Só re-rodar `npm run e2e:check` + `casos:results` no CT100. → Board isolado num **draft (#3906)** fora do sweep verde. Lição: **antes de tocar um `.tsx`, checar se tem `.casos.md` com UC ✅** — se tiver, o fix precisa de re-run e2e (não cabe num sweep mecânico).
3. **Anti-padrão JSX que quebra o Vite build:** agent do ProjectMgmt/Activity pôs `{/* comentário */}` logo após `{cond && (` — em contexto de expressão JS o `{` abre objeto literal → `Expected ")" but found …`. **Comentário JSX só vive onde children JSX são esperados**; após `(`/`&&(` o comentário vai ANTES da linha `{cond && (`. Defesa barata: rodar `esbuild.transform(src,{loader:'tsx'})` em cada `.tsx` tocado antes de push (pegou o único erro em 54 arquivos; `node`, não Pest — roda local).

## Prova runtime pendente (Regra 0 PROTOCOLO-COMPARACAO-RUNTIME + R1)
Por tela, pós-deploy: clicar 1 filtro no Chrome e capturar header `X-Inertia-Partial-Data` + confirmar que o response NÃO traz as props estáticas (marker vivo). Registrado por PR — não feito nesta sessão (pré-deploy).

## Estado no fechamento
11 PRs abertos (#3894–#3904) + 1 draft (#3906). CI em verificação. Nada mergeado (R10) — Wagner aprova. #3904 (valor/estoque) e #3906 (Board) têm gates próprios antes de qualquer merge.
