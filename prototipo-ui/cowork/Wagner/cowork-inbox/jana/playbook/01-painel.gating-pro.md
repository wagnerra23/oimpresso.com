# ONDA 01 — Painel · **gating Pro** (brief · análises · ações)

Prefixo: `feat/jana-painel-gating-pro` · 1 PR · 2 arquivos · ~120 linhas.
Passa no teste do estranho: quem não viu a conversa executa sem perguntar.

---

## 1 · Pedido — o que e por quê

A âncora (`jana-merge.jsx` §`JanaPage`) desenha um produto de **dois planos**: com `pro` falso, **brief diário**, **análises** e **ações sugeridas** não renderizam — no lugar do brief e das análises entra um card de upsell, e a faixa de ações some inteira. Metas, conversa e memória são dos dois planos.

A produção renderiza **tudo pra todo mundo**. O `useJanaPro()` já é lido em `Index.tsx`, mas só alimenta o `JanaPlanoBadge` no header — o tier não governa nenhuma seção.

Não é cosmética: a tela hoje entrega de graça o que o `/ia/pro` vende (ADR 0140), e o selo "Grátis" aparece ao lado do conteúdo Pro renderizado. Quem paga não recebe nada a mais nesta tela.

**O dado já existe — nada de fundação.** `jana.pro` é shared prop lazy em `HandleInertiaRequests.php:169-170` (`'pro' => fn () => $user && $businessId ? $this->janaPlanoPro((int) $businessId) : false`), e `janaPlanoPro` lê `jana_pro_module` na assinatura ativa (`:554`, `:585`). O default é `false` — fail-safe: na dúvida, Grátis.

## 2 · A11y do alvo (o que NÃO exportar)

O alvo falha e **corrige-se no build daqui**, não vira pedido:

- KPI e card de análise do protótipo são `div[role="button"]` + `onKeyDown` manual (`jana-merge.jsx` §`JanaPage`, `jm-an-hit`). A produção já usa `<button>` nativo com `aria-label` — **é a produção que está certa; manter**.
- O card de upsell do protótipo é um bloco estático sem foco. Na produção ele nasce como `EmptyState` shared, que já traz hierarquia de título/descrição/ação — o botão de ação é focável por construção.
- Bateria A1–A12 não rodada neste turno no alvo (§8).

## 3 · Alvo (protótipo) — o que renderiza em cada plano

| seção | Grátis | Pro |
|---|---|---|
| header + abas + nota-mob | renderiza | renderiza |
| **brief diário** | **upsell** "O brief diário é do plano Pro" | `BriefDiario` (sujeito a `cfg.brief`) |
| KPIs (3) | renderizam, **sem drill** (`alvo` só quando `pro`) | renderizam, clicáveis |
| **METAS ATIVAS** | renderiza | renderiza |
| h2 `ANÁLISES PRINCIPAIS` | renderiza (sem a sub-linha) | renderiza + sub-linha |
| **as 5 análises** | **upsell** "As 5 análises são do plano Pro" | grade |
| **AÇÕES QUE JANA SUGERE** | **ausente** — h2 e faixa não renderizam | renderiza |

Copy literal do alvo, a portar sem reescrever:

- brief → título `O brief diário é do plano Pro` · ícone `calendar` · descrição `Toda manhã às 06h a Jana escreve o que aconteceu, o que está crítico e o que fazer hoje — com os números da sua empresa. No Grátis, você pergunta; no Pro, ela adianta.`
- análises → título `As 5 análises são do plano Pro` · ícone `chart` (lucide `bar-chart-3`) · descrição `Inadimplência, faturamento, concentração, churn ouro e métodos de pagamento — recalculadas todo dia, com drill-down até a origem do número.`
- ação dos dois cards → `Ver Jana Pro`, destino `/ia/pro`.

Geometria medida no ciclo anterior (2026-09-07, dark, conteúdo 1650px) — **carregada, não re-medida neste turno**: brief `top 173 · h 246`, `jc-kpis top 437 · h 98`, `jm-metas top 552`, h2 ANÁLISES `top 710`, `jc-grid top 734 · h 477`, h2 AÇÕES `top 1230`, `jc-acoes top 1254 · h 184`.

## 4 · Comportamento + invariantes

| elemento | estados | gatilho | efeito | persistência | reversível | prova |
|---|---|---|---|---|---|---|
| card de upsell | único | — | leva a `/ia/pro` | não persiste | — | `EmptyState` shared + `Link` Inertia |
| drill do KPI | ativo · inerte | `pro` | Grátis: KPI não abre drawer | — | — | mesmo ramo do alvo (`alvo = pro ? … : null`) |
| faixa de ações | presente · ausente | `pro` | Grátis: h2 + faixa não montam | — | — | `acoes.length > 0 && pro` |
| toggles de análise | on · off | `JanaConfigDrawer` | inalterado | `localStorage oimpresso.jana.cfg` | sim | a config **não** vira gate de plano |

**Invariantes:** METAS, conversa e memória **nunca** são gated. Nada de consulta nova — o `SellsCockpitAggregator` segue apurando igual (esconder card não economiza cálculo, e o drawer já diz isso ao usuário). `pro === false` é o default de um usuário legítimo, não estado de erro: nada de tom `danger`, nada de cadeado.

## 5 · Não inventar (reusar, não recriar)

- **Componentes:** `Components/shared/EmptyState` (`icon`/`title`/`description`/`action`/`className`/`variant`) · `Components/ui/button` · `@inertiajs/react` `Link` · `_components/useJanaPro` · `_components/JanaPlanoBadge` (já montado no header). **Não criar** `JanaUpsellCard`, `PaywallBlock` nem nada do gênero — o alvo é um empty-state com ação, e o shared já é isso.
- **Tokens:** os do `EmptyState`. Nenhum token novo, nenhuma cor crua, nada de gradiente ou faixa dourada de "premium".
- **Dados:** `useJanaPro()`. **Não** ler `usePage().props.jana.pro` direto — o hook existe exatamente pra não haver N leitores (ver o cabeçalho de `useJanaPro.ts`).
- **Rota:** `/ia/pro` (`jana.pro.index`), que já está de pé.
- **Copy:** literal do §3, PT-BR, sentence case.

## 6 · Instrução de execução

```
ONDA 01 — o tier Pro passa a governar brief, análises e ações do Painel
  ARQUIVOS A EDITAR   : resources/js/Pages/Jana/Index.tsx
                        resources/js/Pages/Jana/_components/JanaCockpit.tsx
  REUSAR              : useJanaPro() (já importado no Index.tsx) · EmptyState shared ·
                        Button · Link · a prop `analisesVisiveis` (mecanismo separado,
                        NÃO reaproveitar como gate de plano)
  CRIAR               : nada — nenhum componente, nenhum token, nenhum endpoint
  NÃO TOCAR           : METAS (aposKpis) · KpiGrid e os 3 KPIs · JanaAreaHeader ·
                        JanaPlanoBadge · nota-mob · JanaConfigDrawer · JanaMetaDrawer ·
                        JanaAcaoModal · AcaoHitlController · useJanaConfig ·
                        as 5 âncoras data-contract
  PASSO A PASSO       : 1) `JanaCockpit` ganha UMA prop nova: `pro?: boolean`, default
                           `true`. Default true de propósito — o `Chat.tsx` também monta
                           este componente, e quem não passa a prop não muda de
                           comportamento (blast radius zero).
                        2) `Index.tsx` passa `pro={pro}` (a const já existe, linha do
                           `useJanaPro()`).
                        3) No cockpit, o `<Card>` do brief vira:
                           `pro ? <Card …brief…/> : <EmptyState … copy do §3 …/>`
                        4) A grade das análises idem — o upsell substitui a grade, e o
                           `<SectionTitle>` de "Análises principais" FICA nos dois casos;
                           só a sub-linha "clique num card pra ver de onde vem o número"
                           é condicionada a `pro` (é promessa de drill).
                        5) Drill: `onClick={abrirFat}` / `abrirInad` só quando `pro`
                           (passar `undefined` — o AnalysisCard/JanaKpiCard já degrada
                           pro card não-clicável, sem botão, sem aria-label de ação).
                        6) Ações: a condição `acoes.length > 0` vira
                           `pro && acoes.length > 0` — h2 e faixa somem juntos.
                        7) `nenhumaAnalise` (todas escondidas no Configurar) continua
                           valendo SÓ dentro do ramo `pro`; no Grátis o upsell manda.
                        8) contar flex/grid antes e depois: `layout:check` é ratchet POR
                           ARQUIVO. O EmptyState não é flex no call-site; se a contagem
                           subir, reaproveite o container existente em vez de embrulhar.
  DADO                : nenhum campo novo, nenhuma query nova. `jana.pro` já chega.
  PARAR SE            : `PainelContratoTest` pinar seção de brief/análises/ações sem
                        ramo de plano (NÃO LI o arquivo — ler no turno: ele é o dono das
                        âncoras `data-contract`) · ou se o `jana-painel.contract.json`
                        declarar essas seções como sempre-presentes. Nos dois casos o
                        contrato é atualizado NO MESMO PR, senão o gate reprova.
                        PARAR TAMBÉM se [W] disser que o Painel é liberado por decisão
                        de produto — aí a divergência é do protótipo e some daqui.
  NÃO FAZER           : não gatear METAS · não usar `analisesVisiveis` como paywall ·
                        não pôr cadeado/ícone de premium (o alvo não tem) · não desligar
                        o cálculo no aggregator (é preferência de EXIBIÇÃO, e o custo é
                        o mesmo) · não mexer no `/ia/pro`.
```

## 7 · O que a ancoragem NÃO resolve

- **`cfg.brief` / `cfg.audio`.** No alvo o brief ainda passa por um toggle de config (`cfg.brief`) e o áudio por `cfg.audio`. Na produção o `useJanaConfig` só governa as 5 análises. Ampliar a config é onda própria — esta onda só olha o tier.
- **Quem vira Pro.** Billing real (Asaas, trial→pago) é Sprint JANA-B (ADR 0140, US-COPI-211/212). Hoje `/ia/pro` "ativa estado mock" (comentário do `routes.php`). Esta onda **não** toca nisso.
- **Conversa, memória, alertas.** O alvo também plano-gateia coisas fora do Painel. Fora desta onda — outra view, outro PR.
- **Superadmin/`Gate::before`.** `jana_pro_module` é tier de assinatura, não permissão — o bypass de `AuthServiceProvider` não se aplica. Se [W] quiser que superadmin veja sempre o conteúdo Pro, é decisão dele e vira uma linha, não uma onda.

## 8 · Não medido, declarado

- **Zero medição de DOM vivo da produção** — sem sessão autenticada, e `https://oimpresso.com/ia` não abre daqui. Tudo do `main` é leitura de código.
- **Zero medição de render do protótipo neste turno** — o §3 traz geometria carregada do ciclo de 2026-09-07. Quem executar re-mede se precisar do pixel.
- **`PainelContratoTest.php` e `jana-painel.contract.json` não lidos** — daí o `PARAR SE`.
- **`Pro.tsx` não lido** (23.223 B): não afirmo como a tela de paywall apresenta o mesmo argumento, então a copy do upsell vem do protótipo e não foi conferida contra ela. Se divergir, a do `/ia/pro` é a soberana (é copy cliente-facing de venda).
- **Bateria a11y A1–A12 não rodada** no alvo neste ciclo.
- **Contraste AA em número** não calculado.

## 9 · DoD

1. Screenshot autenticado 1280px, **dark e light**, em `/ia`, com `jana.pro` **true** e **false** (4 imagens).
2. Grátis: sem brief, sem grade de análises, **sem** h2 de ações; dois upsells com a copy literal do §3 e botão pra `/ia/pro`.
3. Grátis: KPI não abre drawer e não é `<button>` (sem `aria-label` de ação pendurado em card inerte).
4. Pro: tela byte-idêntica à de hoje — brief, 5 análises, 5 ações, drill, sub-linha.
5. METAS presentes nos dois planos; `painel-cta-conversar` = 1 ocorrência nos dois.
6. `Chat.tsx` (outro consumidor do cockpit) inalterado no render — é o teste do default `true`.
7. `layout:check` com a mesma contagem de flex/grid por arquivo; `ui:lint` sem R7 novo.
8. `PainelContratoTest` verde; `Index.casos.md` com ≥1 UC novo (Grátis vê upsell / Pro vê conteúdo), citado por teste, **no mesmo PR**; bloco destilado no charter.
9. `github.md` com a linha do ciclo + `bundle regenerado (<data> · N arquivos)`.

## 10 · Recibo

- **Nada foi commitado** — as tools de GitHub deste lado são read-only.
- **Build do Cowork não alterado nesta onda**: o protótipo já implementa o gating (`pro` em `JanaPage`), é a produção que está atrás. Nenhuma correção de alvo foi necessária aqui.
- **Absorve** a ONDA 2.1 do antigo `COLAR-NO-CODE-jana-tabs-cor-e-icone.md` como **fechada** (verificada no `main` em 2026-09-21).
