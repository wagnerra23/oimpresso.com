# Painel da Jana (`/ia`) — protótipo × tela viva, por região e componente

- **Data da medição:** 2026-08-17 · **âncora:** `prototipo-ui/cowork/jana-merge.jsx` (resolvida por `node prototipo-ui/ancora.mjs Jana/Index`)
- **Tela viva:** `resources/js/Pages/Jana/Index.tsx` + `_components/JanaCockpit.tsx` + `_components/JanaDrillDrawer.tsx`
- **Charter:** `resources/js/Pages/Jana/Index.charter.md` **v7** — o Non-Goal da análise Frota foi removido por decisão [W] em 2026-08-17
- **Gate F1.5:** esta tela está no manifesto `tests/Browser/visreg-screens.json` como `Jana`; toda mudança aqui gera diff de pixel e precisa de aprovação [W]

> **Como ler:** ✅ existe e equivale · 🟡 existe mas diverge · ❌ não existe na tela viva · ⛔ existe e **não deve** ser copiado.

---

## R1 · Header e identidade

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| barra de identidade | `JanaHeader` — empresa, `biz=`, "Atualizado HH:MM" | `JanaAreaHeader` (PageHeader canon) | ✅ equivalente |
| botão de reapuração | `onRefresh` → recalcula + toast | — | ❌ |
| selo de plano | `jm-plano` — "plano Pro/Grátis", clicável → abre Configurar | página separada `/ia/pro` | 🟡 diverge |
| ação Configurar | `JmConfigDrawer` (drawer real) | `<Button title="(em breve)">` sem rota | 🟡 promessa sem entrega |
| ação Exportar | dropdown — Painel PDF · Metas CSV · Fatos LGPD | `<Button title="(em breve)">` sem rota | 🟡 promessa sem entrega |

## R2 · Navegação da área

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| abas | `JmTabs` — Painel · Metas* · Conversa · Memória | `JanaSubNav` | ✅ equivalente |
| contador nas abas | `n` por aba (nº de conversas, nº de metas) | — | ❌ |
| aba Metas | opcional (`metasMode="aba"`); default é seção do Painel | Metas é bloco da própria tela | ✅ equivalente ao default |

## R3 · Brief diário

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| bloco do brief | `BriefDiario` — texto rico com ênfase por tom | bloco de brief no `JanaCockpit` | ✅ |
| chips de pergunta | 4 chips clicáveis que semeiam a conversa | — | ❌ |
| ouvir áudio (TTS) | `onAudio` condicionado ao toggle `cfg.audio` | botão presente, `title="(em breve)"` | 🟡 |
| gate por plano | sem Pro → card de upsell no lugar do brief | — | ❌ |

## R4 · KPIs

| # | protótipo | tela viva | veredito |
|---|---|---|---|
| 1 | Receita mês | Faturamento mês | ✅ |
| 2 | A receber vencido (com `emphasize`) | Inadimplência total | ✅ |
| 3 | Ticket médio | Ticket médio | ✅ |
| 4 | **Frota utilização** | PIX hoje | 🟡 divergem — ver nota |
| — | KPI clicável quando existe análise do mesmo dado (`JM_KPI_DRILL`) | 2 dos 4 abrem drill | ✅ |

> **Nota sobre o 4º KPI.** O protótipo retrata o cockpit do Martinho (`biz=164`), onde frota é o negócio. A tela `/ia` do núcleo atende ROTA LIVRE (vestuário). Com o Non-Goal removido (charter v7), **não há mais proibição** de construir — mas também **não há fonte**: `Modules/OficinaAuto/Entities/Vehicle` é do OficinaAuto. Copiar exige decidir de onde vem o dado para um business que não tem frota.

## R5 · Metas — **a maior divergência da tela**

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| seção | `JmMetasSecao` — "METAS ATIVAS" com seletor de período | bloco "Metas ativas" | 🟡 |
| seletor de período | 3 janelas clicáveis (`JM_PERIODOS`) | — | ❌ |
| "Nova meta" | botão no cabeçalho da seção | — | ❌ |
| card | `JmMetaCard` — farol + valor/alvo + **barra de progresso** + % + **projeção** | `MetaCard` — farol lateral + valor + alvo + % + sparkline | 🟡 |
| série histórica | `JmSerie` — 12 barras no drawer | `Sparkline` inline no card | 🟡 |
| abrir a meta | `JmMetaDrawer` — **drawer na própria tela** (situação, série, origem do número, CTA) | `<Link href="/ia/metas/{id}">` — **sai da página** | ❌ **é o buraco principal** |
| empty state | dois textos distintos (vazio × erro) + CTA correspondente | um empty state | 🟡 |

## R6 · Análises

| # | protótipo | tela viva | veredito |
|---|---|---|---|
| 1 | Inadimplência (buckets de aging) | Inadimplência | ✅ |
| 2 | Faturamento (sparkline 24m) | Faturamento (30 dias) | 🟡 janela diferente |
| 3 | Concentração (Pareto Top 10/50/100) | Top 5 clientes | 🟡 recorte diferente |
| 4 | Churn ouro (LTV alto inativos) | — | ❌ |
| 5 | Frota (donut) | — | ❌ (ver nota R4) |
| 6 | Cheques previsão | — | ❌ |
| — | — | Métodos de pagamento | 🟢 só na viva |
| — | cabeçalho "ANÁLISES PRINCIPAIS" + subtítulo "clique num card pra ver de onde vem o número" | `SectionTitle` | 🟡 sem o subtítulo |
| — | drill-down por card | `JanaDrillDrawer` | ✅ |
| — | gate por plano (sem Pro → upsell) | — | ❌ |

⛔ **Não copiar da âncora:** os 6 `Analise*Service` que o protótipo cita como fonte **não existem no repo** (medido em 2026-08-17 no espelho **e** no Cowork vivo: `SellsCockpitAggregator` aparece 0×). O anti-hook do charter continua valendo — a fonte citada no drill tem que existir. Use `app/Services/Sells/SellsCockpitAggregator.php`. Idem `MetricasApurador::farol`, citado no `JmMetaDrawer`: a classe existe, o método **não** — o real é `ApuracaoService::farol`.

## R7 · Ações sugeridas

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| seção | "AÇÕES QUE <NOME> SUGERE" | "Ações que <Nome> sugere" | ✅ |
| linha de ação | `AcaoRow` com CTA por tom | equivalente | ✅ |
| ao clicar o CTA | `JmAcaoModal` — confirma antes de disparar (HITL) | botão com `title="(HITL — em breve V2)"` | ❌ |
| gate por plano | só no Pro | — | ❌ |

## R8 · Overlays

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| drill "de onde vem o número" | `JmDrillDrawer` | `JanaDrillDrawer` | ✅ |
| drawer de meta | `JmMetaDrawer` | — | ❌ |
| modal de ação (HITL) | `JmAcaoModal` | — | ❌ |
| drawer Configurar | `JmConfigDrawer` — toggles das 6 análises, brief on/off + hora, áudio, retenção | — | ❌ |

## R9 · Estados e feedback

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| carregando | `JmPainelSkeleton` (variante compacta por aba) | — | ❌ |
| `<Deferred>` na prop deferida | — | `coworkAggregates` é `Inertia::defer` no controller e a Page **não** embrulha | 🟡 guarda por `?.`/`?? []` (allowlist do gate) |
| vazio | EmptyState com copy própria + CTA "Ir para a Conversa" | EmptyState "Nenhuma meta cadastrada ainda" | 🟡 |
| erro | EmptyState `variant="error"` + "Tentar de novo" com estado `tentando` | — | ❌ |
| toast | `jm-toast` em reapuração, export, ações | `sonner` disponível, sem uso no Painel | ❌ |
| aviso mobile | "O painel foi desenhado pro escritório (1280px)…" | — | ❌ |

## R10 · Plano e upsell

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| selo do plano | `jm-plano` no header | — | ❌ |
| upsell inline | card no lugar do brief / das análises quando fora do Pro | link "Jana Pro" → `/ia/pro` | 🟡 |
| persistência da config | `localStorage` `oimpresso.jana.cfg` | — | ❌ |

---

## Resumo — o que falta, por tamanho

| ordem | entrega | região | por que primeiro |
|---|---|---|---|
| 1 | **Drawer de meta** (`JmMetaDrawer`) | R5 | maior buraco visível; hoje o clique **tira o usuário da tela** |
| 2 | Seletor de período + projeção no card | R5 | completa a seção Metas |
| 3 | Skeleton + toast + estado de erro | R9 | camada de carregamento/feedback inteira ausente |
| 4 | Drawer Configurar + persistência | R8/R10 | destrava o botão "(em breve)" |
| 5 | Exportar (PDF/CSV/LGPD) | R1 | idem — hoje é promessa sem rota |
| 6 | Modal de ação HITL | R7 | precisa de backend |
| 7 | Análises 4-6 (churn/cheques/frota) | R6 | precisam de fonte de dado que não existe |
| 8 | Chips do brief + gate de plano | R3/R10 | produto |

## Fora de escopo — decisões [W] ainda abertas

- **Golden PT-04 `draft` → `live`** — aprovação de screenshot (F1.5); trava o `ciclo-completo` desta tela **e** de outras duas.
- **`related_prototype`** — hoje `jana-merge.jsx`; o check `pt_declarado` só casa `PT-0X`, então o ciclo reprova enquanto ficar assim.
- **Título "Dashboard" × "Painel"** — a aba diz Painel, a rota é `/ia`, mas título, breadcrumb e o componente exportado dizem Dashboard.
