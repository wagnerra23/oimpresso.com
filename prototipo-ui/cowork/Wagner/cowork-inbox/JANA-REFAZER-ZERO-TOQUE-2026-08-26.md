# Jana — refazer o módulo (pacote zero-toque)

**Pedido de [W] 2026-08-26:** refazer o módulo Jana completo. **O protótipo Cowork manda** — o vivo não é referência de desenho, só de contrato (rotas, props, gates).
**Motivo declarado:** "sempre quando tento falta algo". Este pacote existe para fechar TODA decisão antes de escrever código. Se algo aqui estiver ambíguo, **o Code para e devolve a pergunta** — não improvisa. Improvisar é a causa do "falta algo".

**Rodada 1 (este arquivo):** fonte exportada + inventário fechado + contrato de dados + regras + ordem dos PRs.
**Rodadas 2–5:** os arquivos `.tsx` completos, prontos pra substituir, um PR por tela, com charter + casos reescritos. Nenhuma rodada depende de leitura minha do vivo — o mapa já está aqui.

---

## 1. Fonte da verdade

`prototipo-ui/cowork/jana/` — exportado hoje, é o build que manda:

| Arquivo | Tamanho | Contém |
| --- | --- | --- |
| `jana-merge.jsx` | 56,5 KB · 1099 L | `JanaPage` (Painel · Metas · Conversa · Memória) + 6 tabelas de dados + 4 overlays |
| `jana-merge.css` | 21,9 KB | estilo das 4 vistas |
| `chat-jana.jsx` | 33,0 KB · 721 L | `JanaCockpit`, `JanaHeader`, `BriefDiario`, `KPICard`, `Sparkline`, `Donut`, `AnaliseCard`, `AcaoRow`, `JanaBubble`, `ConverseComJana` |
| `chat-jana.css` | 24,2 KB | estilo do cockpit + chat |
| `jana-pro.jsx` | 8,1 KB · 163 L | `JanaProPage`, `JpMarca`, tabelas Grátis×Pro |
| `jana-pro.css` | 7,5 KB | estilo do modo foco |

Cópias antigas de `chat-jana.*`/`jana-merge.*` na raiz de `prototipo-ui/cowork/` foram **removidas** (duplicata é o que o `cowork-ssot-guard` reprova, e era espelho de 24/08).

## 2. Mapa fechado: unidade do protótipo → arquivo do repo

Verbo: **estender** = o arquivo existe e recebe o desenho novo · **criar** = não existe · **matar** = sai do repo.

| Protótipo | Destino no `main` | Verbo |
| --- | --- | --- |
| `JanaPage` tab=`painel` (brief + KPIs + análises + ações) | `Pages/Jana/Index.tsx` | estender |
| `JmMetasSecao` + `JmMetaCard` + `JmFarol` + `JmSerie` | seção DENTRO de `Index.tsx` | estender |
| `JanaPage` tab=`conversa` + `ConverseComJana` + `JanaBubble` + `JmThreadItem` + `JmConversa` + `JmPropostas` | `Pages/Jana/Chat.tsx` | estender |
| `JmMemoria` + `JM_CAT_LABELS` | `Pages/Jana/Memoria.tsx` | estender |
| `JanaProPage` + `JpMarca` + `JP_*` | `Pages/Jana/Pro.tsx` | estender (é o arquivo com 13 blocos `style` inline — eles saem) |
| `JmTabs` | `Pages/Jana/_shared/JanaSubNav.tsx` | estender |
| `JanaHeader` | `Pages/Jana/_components/JanaAreaHeader.tsx` | estender |
| `BriefDiario` · `KPICard` · `Sparkline` · `Donut` · `AnaliseCard` · `AcaoRow` | `Pages/Jana/_components/JanaCockpit.tsx` | estender, **quebrando em ≤1000 linhas por arquivo** |
| `JmMetaDrawer` | `_components/JanaMetaDrawer.tsx` | estender |
| `JmDrillDrawer` | `_components/JanaDrillDrawer.tsx` | estender |
| `JmConfigDrawer` + `cfg`/`Toggle` | `_components/JanaConfigDrawer.tsx` + `useJanaConfig.ts` | estender |
| `JmAcaoModal` | `_components/JanaAcaoModal.tsx` | estender |
| `JmPainelSkeleton` | `_components/JanaCockpitSkeleton.tsx` | estender |
| `jmFmt` · `jmMeta` · `DIF` · `FONTE` | `_components/metaFormat.ts` | estender |
| `JcIcon` (mapa de SVG inline) | **nada** — o repo usa `lucide-react` | matar |
| — | `Pages/Jana/components/` (pasta minúscula, com `FabJana` + `JanaAreaHeader`) | **matar a pasta**: `FabJana` e `JanaAreaHeader` passam para `_components/`, duas pastas para a mesma coisa é a doença |
| — | `_components/AssistantUiChat.tsx` | **conferir**: se nenhuma das 4 telas importa, sai no PR-2 (16 KB de caminho paralelo ao `ConverseComJana`) |

**Aba própria de Metas não vai pro vivo.** No protótipo ela é Tweak (`janaMetas`); o canon é seção do Painel. O vivo recebe só o canon — Tweak é ferramenta de exploração, não configuração de produto.

## 3. Contrato de dados por tela (para o controller — nomes fechados, não inventar)

Derivado das tabelas do protótipo; o nome do campo é o do protótipo, e é ele que vale.

**Index (Painel):**
- `metas: {id, nome, alvo, fmt:'brlk'|'brl'|'pct'|'int', acumula?:bool, serie:number[12], nota:string}[]`
- `periodos: {key:'mai/2026', idx:number, corrente?:bool}[]`
- `kpis` + `analises` + `acoes` (do `getJanaData` do protótipo — o Code mapeia 1:1 e **não renomeia**)
- **`farol` é veredito do servidor** (`ApuracaoService::farol`), nunca calculado no front — está escrito no protótipo, linha do `jmMeta`.

**Chat:** `threads: {id, title, preview, quando, n, escopo:'minhas'|…}[]` · `filtros: ['todas','arquivadas']` · `sugestoesPendentes: {id, nome, metrica, valor_alvo, periodo, dificuldade, racional, dependencias:string[]}[]` · mensagens `{from:'user'|'jana', kind:'text'|'markdown'|'tool_use', …}`.

**Memoria:** `fatos: {id, fato, cat:'meta'|'preferencia'|'restricao'|'contexto'|'acao_pendente', origem, desde, rel:number}[]`.

**Pro:** `mensal:number` · `trial_dias:number` · `prova:{bruto,liquido,caixa}` · `linhas` · `confianca`. **Preço de concorrente não entra** (Tier 0 redigido na fonte).

## 4. Regras duras — é aqui que "falta algo"

1. **PT-BR** em todo label, placeholder, mensagem, empty-state e erro. Enum do banco só no `title`.
2. **Zero `style={{…}}`** — o `Pro.tsx` vivo tem 13 blocos e é o pior ofensor. Tokens do DS ou classe; nunca cor crua.
3. **Ícone = `lucide-react`.** O `JcIcon` do protótipo existe porque o Cowork não tem lucide; portá-lo pro repo seria criar um segundo sistema de ícones.
4. **`AppShellV2` + `PageHeader`** por tela, `Index.layout` como as 4 telas já fazem. **Um `<main>` por documento** (AP9) e **chain de overflow** (AP10: nó `flex-1` em coluna precisa de `h-full` ou pai `flex flex-col min-h-0`).
5. **Sidebar preta nos dois modos** — não "corrigir" para claro (UI-0023).
6. **Sem emoji** no app. Sem raio acima do `rounded-xl` do Card canônico.
7. **Nenhum arquivo acima de 1000 linhas.** O `JanaCockpit.tsx` vivo tem 44 KB — no PR-2 ele sai quebrado por bloco (brief · kpis · análises · ações).
8. **`data-contract` em cada bloco** e `data-screen-label` por tela — os dois já estão no protótipo, copiar os mesmos valores ("Jana — Painel", "Jana — Conversa", "Jana — Memória", "Jana — Pro (modo foco)").
9. **Frota não existe** — matada por [W] em 2026-08-07 e já removida do protótipo. Se aparecer KPI, análise, ação ou resposta de caçamba parada, é regressão.
10. **Estado vazio, carregando e erro** em toda tela (o protótipo tem `estado` como Tweak: dados/vazio/carregando/erro). Cada `EmptyState` diz **por que** e **o que fazer**.

## 5. Ordem dos PRs (um por rodada minha)

| PR | Fecha | Gates |
| --- | --- | --- |
| **PR-1** | Faxina estrutural: mata `Pages/Jana/components/`, move `FabJana`/`JanaAreaHeader` pra `_components/`, decide `AssistantUiChat` | `npm run lint` · `typecheck` · rotas abrindo |
| **PR-2** | `Index.tsx` + seção Metas + `JanaCockpit` quebrado + `JanaCockpitSkeleton` + `JanaSubNav` | + visreg do Painel · `prototipo-readiness` |
| **PR-3** | `Chat.tsx` + `ConverseComJana` + propostas | + visreg do Chat |
| **PR-4** | `Memoria.tsx` | + visreg |
| **PR-5** | `Pro.tsx` (fim dos 13 inline) + os 4 `contrato/jana-*.contract.json` | + contrato de tela no CI |

Charter + `casos.md` de cada tela vão **no mesmo PR da tela** — reescritos, não remendados ([W] escolheu "reescrever junto").

## 6. O que eu entrego na rodada 2

`Index.tsx` completo (pronto pra substituir), `_components/JanaCockpit*.tsx` quebrado, `_shared/JanaSubNav.tsx`, `Index.charter.md` e `Index.casos.md` reescritos, e `contrato/jana-painel.contract.json`.
O que preciso de volta do Code, **só isso**: o nome real dos props que o controller da rota `/jana` já injeta hoje (se divergirem dos nomes da §3, o mapa 1:1 vira uma linha de adaptação no controller — não renomeio o desenho).
