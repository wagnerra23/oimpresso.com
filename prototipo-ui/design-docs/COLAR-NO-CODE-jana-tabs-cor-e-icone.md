# COLAR NO CODE — Jana · **Painel** (`/ia`) — paridade produção × protótipo, seção por seção

> **Alvo deste ciclo:** `Jana.Painel` (página única ⇒ **onda = seção**, §Granularidade do PROTOCOLO).
> **Este arquivo se reescreve** (anti-scatter, 3ª vez): nasceu "faixa de abas — cor e ícone" (2026-09-04 manhã), virou "mapa de seções + resíduo de token" (2026-09-04 tarde) e agora absorve a **medição de paridade do interior** (2026-09-07). Nenhum doc novo foi criado pro módulo; a onda das abas continua viva aqui como **§1.1**.
> Ponte, não canon. Destino no `main`: `prototipo-ui/` (root) — nunca `prototipo-ui/cowork/` (guard R1). **Eu não escrevo no git**: desce por `cowork-inbox`/Issue → PR.

## Leitura do `main` feita NESTE turno (2026-09-07, tree `43b76c1ec327`)
1. `Modules/Jana/Http/Controllers/IndexController.php` ← **contrato do payload** (`metas`, `sellKpis`, `insightsAggregates`, `coworkAggregates`, `janaContext`)
2. `resources/js/Pages/Jana/Index.tsx` ← **âncora de implementação** do cabeçalho METAS + `MetaCard` + empty state
3. `resources/js/Pages/Jana/_components/JanaKpiCard.tsx` ← réplica declarada do `.jc-kpi` (ADR 0388)
4. `resources/js/Pages/Jana/_components/useJanaConfig.ts` ← **conjunto canônico das 5 análises** e a chave `oimpresso.jana.cfg`
5. `resources/js/Pages/Jana/_components/JanaCockpit.tsx` — **lido por BUSCA, não linha a linha** (47.668 B): faixas `:176-215` (AnalysisCard/pill), `:310-436` (agregados + drills + ações), `:726-935` (render de análises e ações). Números de linha são de `43b76c1ec327`.
6. `resources/js/Pages/Jana/_components/{JanaConfigDrawer,JanaDrillDrawer}.tsx` — por busca (mapa de análises e de fontes)

**Não lido ⇒ "não verifiquei"** (e por isso **não vira pedido**): `Index.charter.md` (34.851 B), `Index.casos.md` (69.891 B), `Modules/Jana/Tests/Feature/PainelContratoTest.php` (50.577 B — **é ele que pina as âncoras `data-contract`**), `SellsCockpitAggregator`, `AcaoHitlService`, `app/Sidebar/SidebarGhost.php`, `prototipo-ui/FRESCOR-*`.

**Veredito curto:** de 11 divergências medidas, **2 eram produção à frente** (CTAs `Revisar …` nas ações HITL · conjunto de análises com `metodos`) e **1 era defeito do meu build** (upsell falando em "cheques", sem fonte no vivo) — **as três foram corrigidas AQUI neste ciclo** (§2), não exportadas. **3 são fundação** (janela de período, cadastro em React, contador de aba) e **2 são copy pinada em contrato** (não se pede remoção). Sobra pedido pequeno: **2 ondas · 2 arquivos**.

---

## 0 · Leis DESTE módulo

constituição: `CONSTITUICAO-COWORK.md` (C1–C13) + `memory/proibicoes.md` — **citadas, não copiadas**. Abaixo, só o que é lei DESTE módulo.

1. **Apresentação sim, cálculo não.** O protótipo calcula projeção no front (`jmMeta`: `atualN*1.3` e uma extrapolação de tendência). **Não copiar.** Produção consome `meta.projecao` do `ApuracaoService::projecao` — mesma porta do farol. *(É a C7 na sua forma mais fácil de violar: o número existe, mas a autoridade dele não.)*
2. **Copy pinada por `data-contract` não se mexe** sem [W]: `painel-metas-header`, `painel-metas-vazio`, `painel-cta-conversar`, `painel-meta-apurando`, `painel-meta-sem-historico`.
3. **Análise sem agregado no `SellsCockpitAggregator` não existe** — foi o caso de "Cheques", removido do build em 2026-09-07 *(C7)*.

## 1 · Ordem das ondas + âncora dupla

| onda | seção | alvo de layout (protótipo medido) | âncora de implementação (`main`, lida hoje) | tamanho |
|---|---|---|---|---|
| **1.1** | faixa de abas — pill do contador **inativo** | `_ds_bundle.js` §`TabBar` (ramo `t.count != null`) | `Components/shared/PageHeaderTabs.tsx`, bloco `{ghost.badge != null && …}` | **1 linha** |
| **2.1** | **METAS — valor e rodapé do card** | `jana-merge.jsx` §`JmMetaCard` (`jm-meta-v` = `<b>{atual}</b><small>de {alvo}</small>` · `jm-meta-f` = `{pct}% do alvo` + `jm-meta-proj` à direita) | `Pages/Jana/Index.tsx` §`MetaCard` → `CardContent`, bloco `{alvo !== null && …}` | **1 arquivo · ~12 linhas** |
| **—** | ordem das seções (KPIs → METAS → análises → ações) | medido: header → tabs → brief → KPIs → METAS → análises → ações | `Index.tsx` `aposKpis={…}` | **0** — paridade |
| **—** | KPIs (`.jc-kpi`) | `chat-jana.css` §KPIs | `_components/JanaKpiCard.tsx` (réplica declarada, ADR 0388) | **0** — paridade |
| **—** | sub-linha das análises ("clique num card pra ver de onde vem o número") | `jana-merge.jsx:1094` | `JanaCockpit.tsx:730` | **0** — paridade literal |
| **—** | nota de viewport 768px | `jm-nota-mob` (`@media max-width:768px`) | `Index.tsx` `md:hidden` | **0** — paridade de gatilho |
| **🔵 puxar** | CTAs das ações (`Disparar`/`Preparar` → `Revisar …`) | — | `JanaCockpit.tsx:323-394` | corrigir **aqui** (§2) |
| **🔵 puxar** | análise `metodos` (Métodos de pagamento) | — | `useJanaConfig.ts:39` + `JanaCockpit.tsx:845-850` | corrigir **aqui** (§2) |
| **⛔ [W]** | janela de período nas metas (mai/abr/mar) · `Cadastro` em React · farol bolinha × faixa lateral | `JmMetasSecao` | payload tem só `periodo_atual`; `MetasController@create` devolve Blade | ver §7 |

---

## 1-bis · Instrução de execução — ONDA 1.1 (resíduo, inalterada)

```
ONDA 1.1 — pill do contador da aba (INATIVO) passa a usar o token que o DS manda
  ARQUIVOS A EDITAR   : resources/js/Components/shared/PageHeaderTabs.tsx
                        (1 arquivo · 1 linha · só o ramo inativo do `style` do badge)
  REUSAR (não recriar): o próprio bloco `{ghost.badge != null && …}` — já tem
                        `rounded-full px-1.5 min-w-[18px] text-center text-[10.5px]
                        leading-[1.4] font-semibold tabular-nums` e o ramo ATIVO
                        correto (`--accent` / `--accent-fg`).
  CRIAR               : nada
  NÃO TOCAR           : ramo `isActive` do badge · className das tabs · bloco
                        `ghost.icon` · overflow `⋯ Mais` · JanaSubNav.tsx ·
                        JanaAreaHeader.tsx · Index.tsx · cockpit.css · outras telas
  PASSO A PASSO       : 1) no ramo inativo, `backgroundColor: 'var(--border-2)'`
                           → `'var(--bg-2)'` (o `color: 'var(--text-dim)'` já está
                           certo e NÃO muda)
                        2) sonda: `getComputedStyle` do span inativo, dark E light,
                           após `__oiLazyDone` + duas leituras iguais de
                           `querySelectorAll('*').length` — o par tem que MUDAR
                           entre os temas
                        3) screenshot autenticado 1280px numa tela que declare badge
                           (`Cliente/Index.tsx:962`) + `/ia` como controle (sem badge)
  DADO                : nenhum — é só estilo
  PARAR SE            : `--bg-2` não resolver dentro do `.cockpit` (é problema de
                        camada: volta pro Cowork, NÃO se hardcoda) · ou se [W]/ADR
                        fixar `--border-2` como token do pill (o DS se corrige e a
                        onda morre)
```

---

## 1-ter · Instrução de execução — ONDA 2.1 (nova)

```
ONDA 2.1 — card de meta: valor com "de <alvo>" e rodapé "<pct>% do alvo"
  POR QUE             : medido lado a lado. Alvo (protótipo): valor grande + `de R$ 145k`
                        na mesma linha, e rodapé `32% do alvo` à esquerda com a projeção
                        empurrada pra direita. Produção hoje: `Alvo: R$ 145.000` com o
                        `32%` solto em negrito depois — o alvo aparece DUAS vezes na
                        leitura (linha do valor não o tem, rodapé o repete como rótulo)
                        e o "% do alvo" perde o substantivo.
  ARQUIVOS A EDITAR   : resources/js/Pages/Jana/Index.tsx   (§MetaCard → CardContent)
  REUSAR (não recriar): `formatValue` de `_components/metaFormat.ts` · `FAROL_CLASSES`
                        · a barra `jm-meta-track` que já existe · `meta.projecao` do
                        payload · `tabular-nums` já aplicado
  CRIAR               : nada — nenhum componente, nenhum CSS, nenhum token novo
  NÃO TOCAR           : `farolDaMeta` · a faixa lateral do farol · o `Badge` de unidade
                        · o `Sparkline` · `painel-meta-apurando` e
                        `painel-meta-sem-historico` (copy pinada) · o cabeçalho da
                        seção · JanaCockpit.tsx · o drawer
  PASSO A PASSO       : 1) na linha do valor, acrescentar o alvo como sufixo fraco
                           (`de {formatValue(alvo, meta.unidade)}`), só quando
                           `alvo !== null` — mesmo `text-muted-foreground`, um degrau
                           abaixo do valor, na MESMA linha (o alvo é a régua do número,
                           e é assim que a âncora lê)
                        2) no rodapé, `Alvo: … <b>32%</b>` → `{progresso.toFixed(0)}% do
                           alvo`; a projeção segue `ml-auto shrink-0 font-mono
                           text-[10.5px] tabular-nums` como já está
                        3) `alvo === null` ⇒ nada de rodapé (hoje já é assim); sem
                           apuração, o card continua em `painel-meta-apurando`
                        4) contar flex/grid do arquivo antes e depois: o `layout:check`
                           é ratchet POR ARQUIVO (baseline 11) — esta onda tem de
                           fechar com o MESMO número (reaproveitar os flex existentes,
                           não embrulhar em `<div>` novo)
  DADO                : nenhum campo novo. `periodo_atual.valor_alvo` e `projecao` já
                        vêm do `IndexController::buildMetasPayload`.
  PARAR SE            : o `PainelContratoTest` casar string literal do rodapé antigo
                        (não verifiquei o arquivo — LER NO TURNO); ou se `progresso`
                        for `null` com `alvo` presente (então o texto some, não
                        escreve "0% do alvo")
  NÃO FAZER           : não portar a projeção do protótipo (`atualN*1.3`, sufixo
                        "(tendência)") — é cálculo de front, proibido pela lei 4.
```

---

## 2 · Onda 0a — o que falhou no ALVO se corrige AQUI (não vira pedido)

Corrigido em ciclos anteriores (mantido como recibo): `svg.jc-spark` ganhou `aria-hidden`; `cli-tabs.jsx` puxou da produção `role="tablist"`/`role="tab"`/`aria-selected`/roving `tabIndex` + nav ←/→/Home/End (medido depois: 6/6 `role="tab"`, 0 svg anônimo, 1103 nós em duas leituras iguais, dark).

**Aplicado neste ciclo — 4 correções no build daqui (medidas depois, no render):**

| # | defeito do alvo | evidência no `main` | correção (no meu build) |
|---|---|---|---|
| P1 | CTAs das ações diziam `Disparar` / `Preparar` — prometiam envio | `JanaCockpit.tsx:323-394`: os rótulos viraram `Revisar régua / Revisar proposta / Revisar recorte / Revisar leitura / Revisar lembrete` **porque o endpoint registra aprovação e não envia nada** | ✅ `chat-jana.jsx` §`acoes` → `Revisar régua` · `Revisar proposta` · `Revisar recorte`; e o aviso pós-aprovação deixou de concatenar o rótulo ("Aprovação registrada — nada sai daqui…"). O chip do brief **continua** `Disparar régua 8 clientes`, que é o que a produção também escreve (`JanaCockpit.tsx:579`) |
| P2 | análise **`cheq` (Cheques)** não tinha fonte | `JanaConfigDrawer.tsx:18` registra que "frota e cheques NÃO existem"; `useJanaConfig.ts:26` fixa `inad · fat · conc · metodos · churn` | ✅ `cheq` saiu; entrou **`metodos` (Métodos de pagamento)** como card `bars` (5 formas + participação) em `chat-jana.jsx` §`analises`, no toggle do `JmConfigDrawer` e no `cfg` default. O drill já tinha a fonte (`transaction_payments + transactions`) |
| P3 | upsell dizia "As 5 análises … e cheques" e a tela renderizava 4 | — | ✅ copy passa a nomear as 5 reais (…"churn ouro e métodos de pagamento") |
| P4 | faltavam `Jana Pro` e `Conversar com a Jana` no cabeçalho de METAS | `Index.tsx` §cabeçalho de METAS; `painel-cta-conversar` é **copy pinada** | ✅ os dois botões entraram no `JmMetasSecao`, o segundo com `data-contract="painel-cta-conversar"` e ligado ao `onGoTab("conversa")` real (nenhum global novo) |

**Medido depois, no render do alvo (dark, sem erro de console):** `Métodos de pagamento` presente · `Cheques previsão` ausente · CTAs = `Revisar régua`/`Revisar proposta`/`Revisar recorte` · `[data-contract="painel-cta-conversar"]` = 1 · `Jana Pro` presente · os 3 `h2` na ordem `METAS ATIVAS` → `ANÁLISES PRINCIPAIS` → `AÇÕES QUE JANA SUGERE`. O único `Disparar` que sobra é o chip do brief, que a produção também tem.

---

## 3 · ALVO por seção — Painel (medido, dark, conteúdo 1650px)

```
header (PageHeader)      top   24 · h  99 · left 284 · w 1650
nav.ds-tabbar.jm-tabs    top  137 · h  36 · faixa própria, largura toda · ativa 13px/600
section.jc-brief         top  173 · h 246 · pad-x 23
div.jc-kpis              top  437 · h  98 · 3 col de 405, passo 415 (gap 10)
section.jm-metas         top  552 · h 152   (h2.jc-h2 32 + jm-metas-grid 110)
h2 ANÁLISES              top  710 · h  14 · ícone 14×14 · sub à direita (w 286)
div.jc-grid              top  734 · h 477 · 3 col de 542, passo 554 (gap 12) · cards 230/235
h2 AÇÕES                 top 1230 · h  14
div.jc-acoes             top 1254 · h 184 · linhas h 61
```

Tipografia dos h2 (`.jc-h2`): `700 11px/1` mono, `uppercase`, `letter-spacing .08em`, `--text-3`, ícone 14px — é o que produz `METAS ATIVAS` / `ANÁLISES PRINCIPAIS` / `AÇÕES QUE JANA SUGERE`. Produção escreve o mesmo texto em sentence case (`Análises principais`, `Ações que JANA sugere`) via `SectionTitle` — **a comparação de aparência do h2 não foi medida na produção** (§8).

Card de meta, alvo: `jm-meta-h` (bolinha de farol + nome | período mono) → `jm-meta-v` (`<b>` valor + `<small>de {alvo}</small>`) → `jm-meta-track` (barra, cor do farol) → `jm-meta-f` (`{pct}% do alvo` | projeção mono à direita).

---

## 4 · Comportamento + invariantes

| elemento | estados | gatilho | efeito | persistência | reversível | prova |
|---|---|---|---|---|---|---|
| card de meta | default · hover · focus · aberto | clique · Enter/Espaço | abre `JanaMetaDrawer` (PT-02) | não persiste | sim (`esc`) | é `<button>` nativo; `aria-label` "Abrir a meta {nome}" |
| card de análise | default · hover · focus · aberto | clique · Enter/Espaço | abre `JanaDrillDrawer` | não persiste | sim (`esc`) | `aria-label` "Ver origem de …" |
| toggle de análise | on · off | clique no `JanaConfigDrawer` | esconde/mostra o card | `localStorage oimpresso.jana.cfg` (chave idêntica nos dois lados) | sim | escrita preserva chaves alheias |
| ação HITL | idle · aberta · aprovada | clique no CTA | abre modal; **aprovação é registrada, nada é enviado** | server-side | — | `AcaoHitlService::ACOES` valida a chave |
| farol / projeção | — | — | vêm do servidor | — | — | `ApuracaoService::farol/projecao` |

A ONDA 2.1 **não muda comportamento** — só a leitura do valor e do rodapé.

---

## 5 · Não inventar (reusar, não recriar)

- **Componentes:** `Components/ui/{card,badge,button}` · `Components/shared/EmptyState` · `Components/Icon` · `Pages/Jana/_components/{JanaKpiCard,JanaMetaDrawer,JanaDrillDrawer,JanaConfigDrawer,JanaPlanoBadge,JanaAreaHeader,JanaCockpit}` · `_components/metaFormat.ts`. Nada hand-roll, nenhuma segunda barra de header (a fusão de 2026-08-07 matou a 2ª — não ressuscitar).
- **Tokens:** `--accent` · `--accent-soft` · `--bg-2` · `--text` · `--text-dim` · `--border` · `--radius` (8px dentro do `.cockpit`; `rounded-lg` lá vale **12px** — armadilha medida no `JanaKpiCard`).
- **Dados:** `IndexController::buildMetasPayload` (farol, projeção, `periodo_atual`, `apuracoes_recentes`) · `SellsCockpitAggregator::{buildSellKpis,buildInsightsAggregates,buildCoworkAggregates}`. Nenhuma consulta nova nestas ondas.
- **Navegação:** `Nova meta` é `<a href="/ia/metas/create">` **nativo** — `MetasController@create` devolve Blade; `<Link>` viraria no-op silencioso.
- **Copy:** literal, PT-BR, sentence case; as 5 âncoras `data-contract` da lei 6 não se tocam.

---

## 6 · DoD + PLACAR

**ONDA 2.1**
1. Screenshot autenticado 1280px, dark + light, `/ia` com ≥3 metas — uma com projeção, uma sem, uma sem apuração.
2. Linha do valor mostra `de <alvo>`; rodapé mostra `<pct>% do alvo`; projeção segue à direita em mono 10.5px.
3. Meta sem apuração continua em `painel-meta-apurando`; meta sem alvo não renderiza rodapé; meta com <2 apurações continua em `painel-meta-sem-historico`.
4. `layout:check` com a MESMA contagem de flex/grid do arquivo (baseline 11).
5. `PainelContratoTest` verde — e, se ele casar a string antiga, o UC é atualizado **no mesmo PR**.
6. `Index.casos.md` com ≥1 UC citado por teste, mesmo PR; bloco de contrato destilado no charter da tela.
7. `github.md`: linha do ciclo + `bundle regenerado (<data> · N arquivos)` (ADR 0387).

**PLACAR deste ciclo**
```
divergências medidas ....................... 11
paridade confirmada por leitura ............ 6 (ordem das seções · KPIs · sub-linha das análises ·
                                              nota 768px · drill · chave oimpresso.jana.cfg)
main À FRENTE .............................. 2 (CTAs `Revisar …` · conjunto com `metodos`) — PUXADOS
defeito do meu build ....................... 1 (upsell prometia 5 análises, incl. "cheques" sem fonte) — corrigido
vira pedido ................................ 2 ondas · 2 arquivos (1.1 · 2.1)
corrigido no build daqui ................... 4 (P1 · P2 · P3 · P4) — verificado no render
fundação / ⛔ [W] .......................... 3 (janela de período · Cadastro em React · contador de aba)
copy pinada, não se pede ................... 2 (painel-metas-header · painel-metas-vazio)
"0 bug" ..................................... NÃO. Só o T7 (design-diff --compare --check nos dois
                                              renders, prod deployada) afirma paridade — não rodou.
```

---

## 7 · O que a ancoragem NÃO resolve

- **Janela de período das metas (mai/abr/mar).** O alvo troca a janela e recalcula farol/projeção; o payload manda **só `periodo_atual`**. Fechar exige (a) parâmetro de janela na rota, (b) consulta por `MetaPeriodo` + apuração da janela escolhida, (c) decisão de [W] sobre quantas janelas ficam à mão. **PR de fundação, não esta onda.**
- **`Cadastro de metas` em React.** No alvo é um alternador `Farol/Cadastro` dentro da própria seção (`jana-metas.jsx` absorveu os 4 Blades). No vivo, `MetasController` devolve Blade — migrar é MWART, e enquanto não for, o `Nova meta` continua `<a href>` nativo.
- **Farol: bolinha antes do nome (alvo) × faixa lateral de 1px (produção).** Duas leituras legítimas do mesmo estado; ADR 0385 diz que "diferente não é erro". **⛔ [W] decide** — não pedi mudança.
- **Cabeçalho da seção METAS.** Alvo: `h2` mono `METAS ATIVAS` + segmented. Produção: badge `METAS` + "Acompanhamento contínuo" + `h2` "Metas ativas" + contagem — **copy pinada** (`painel-metas-header`). Fora de pedido.
- **Subtítulos e escala das análises.** Alvo diz `Top 20 devedores`, `Curva 24 meses`, `Top 10/50/100` (Pareto) e números de demo (R$ 4,5M · R$ 107M · 8.856 clientes). O agregador real entrega **30 dias** (`buildCoworkAggregates`) e **top 5** clientes. Pedir o texto do alvo seria **exportar promessa sem dado** — não pedido; se [W] quiser 24 meses e Pareto, é onda de agregador (SQL), com custo por request a medir.
- **Pills das análises.** Produção usa `Badge` soft com `uppercase tracking-wide` e rótulos `Crítico`/`OK`; o alvo tem também `QUEDA` e `REATIVAR`. A condição do pill de `fat` (`JanaCockpit.tsx:775`) **não foi lida** — não afirmo divergência.
- **Contador nas abas (⛔ [W]).** Nenhum ghost da Jana preenche `PageHeaderGhost.badge`; exige campo no contrato PHP + fonte de contagem + decisão de quais abas. PR de fundação.
- **`role="tab"` no `TabBar` do DS**: pendência **do DS** (bundle é espelho). **Alvo de toque <24px** e **`<main>` único (AP9)** do host do Cowork: filas próprias.

---

## 8 · Não medido, declarado

- **Nenhuma medição de DOM vivo da produção** neste turno (sem sessão autenticada). Tudo que digo do `main` vem de **leitura de código**; do `JanaCockpit.tsx` vem de **busca com faixa de linhas nomeada**, não de leitura integral — quem executar a onda 2.1 ou qualquer onda do interior **lê o arquivo no turno**.
- **`PainelContratoTest.php` não lido** (50.577 B). Ele é o dono das âncoras `data-contract`; por isso o `PARAR SE` da onda 2.1 cita a possibilidade de string casada.
- **Aparência dos h2 na produção** (`SectionTitle`) não medida — sentence case no código pode estar sendo uppercase no CSS. Não afirmo gap.
- **Contraste AA em número** não calculado (exigiria sonda OKLCH→sRGB com caso de sanidade).
- **Frescor da Jana** não é declarado pelo repo (`FRESCOR-PRODUCAO-vs-PROTOTIPO.md` não tem linha da Jana). O que este ciclo mediu é 🔵 em 2 pontos.

---

## 9 · Recibo

- **Build alterado neste ciclo** (Cowork → `prototipo-ui/cowork/`): `chat-jana.jsx` (CTAs das ações → `Revisar …`; card `cheq` → `metodos`) · `jana-merge.jsx` (toggle e `cfg` de `metodos`; copy do upsell; `Jana Pro` + `Conversar com a Jana` no cabeçalho de METAS, com `onGoTab` como prop nova do `JmMetasSecao`). Verificado no render (dark, 0 erro de console) — resultados no fim do §2. **O alvo exportado já não promete envio nem mostra análise sem fonte.**
- **Pacote/bundle:** este ciclo fecha **sem** `sync/bundle.manifest.json` regenerado — o gerador exige os arquivos em disco e **não roda do lado do agente** (ADR 0374), então **não afirmo que regenerei**:
  ```
  node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json
  ```
- **Pedido:** este arquivo → `prototipo-ui/COLAR-NO-CODE-jana-tabs-cor-e-icone.md` (root, nunca `cowork/`). **Não commitei nada** — as tools de GitHub aqui são read-only.
