---
tela: Home/Index
rota: /dashboard-legacy
modulo: Dashboard
ancora: prototipo-ui/cowork/dash-legacy-page.jsx
ancora_frescor: "verificado 2026-08-27T21:56:54Z — MAS fidelidade da origem NAO PROVADA (ver §0)"
comparado_em: "2026-09-03"
metodo: sonda-DOM-identica-nos-dois-lados
status: divergente
---

# Inventário `Home/Index` × `dash-legacy-page.jsx`

## Como foi medido (e por que assim)

A **mesma sonda JS** foi injetada nos **dois renders**, e o veredito sai da medição, não do olho:

- **Âncora**: `prototipo-ui/cowork/` servido em `127.0.0.1:5599`, tela `dash-legacy`
  (o portão `cowork-mirror-freshness --preview-ds` passou: 10 dependências repostas, bundle OK).
- **Produção**: `oimpresso.com/dashboard-legacy?aba=venc-venda`, sessão real, commit `01bee7581e`.

Comparar por screenshot foi tentado e **descartado**: iframe de produção não carrega logado
(cookie `SameSite`), e o olho já falhou duas vezes nesta classe de tarefa — é a razão de o
`design-diff.mjs` existir.

## §0 — A cadeia de origem tem um elo fraco DECLARADO

O arquivo-âncora entrou no espelho por **transcrição**, não por download fiel:

| Elo | Fato | Recibo |
|---|---|---|
| Fonte | projeto Cowork `019dcfd3-…`, via `DesignSync.get_file` | — |
| Transporte | **TRANSCRIÇÃO** (nenhum arquivo persistiu no `get_file`) | commit `77f9a28ce7`, 2026-08-21, PR #6123 |
| Fidelidade | **"NAO PROVADA"** | `scripts/design-sync/transcribed-provenance.json` → `sha256 dcf4d23c…`, `parse_esbuild: OK`, `fidelidade: "NAO PROVADA"` |
| Frescor | verificado 2026-08-27T21:56Z | `node prototipo-ui/ancora.mjs Home/Index` |

**Frescor ≠ fidelidade.** Frescor diz "não mudou desde então"; fidelidade diz "é igual à fonte".
Toda divergência abaixo é contra um espelho cuja fidelidade a origem nunca provou.

## Inventário medido — camada a camada

| # | Camada | Âncora | Produção | Veredito |
|---|---|---|---|---|
| 1 | Título `h1` | "Visão geral" | "Visão geral" | ✅ |
| 2 | Painel Contrapartidas | presente | presente | ✅ |
| 3 | Gráfico Vendas por dia | presente | presente | ✅ |
| 4 | Gráfico Vendas por mês | presente | presente | ✅ |
| 5 | Colunas da grade | Nota · Cliente · Vencimento · Situação · Devido | idem | ✅ |
| 6 | **Ordenação de coluna** | **4 colunas com `↕`** (`sortable: true`) | **0** — todo `th` com `botao:0`, `cursor:auto` | ❌ **funcional some** |
| 7 | **Busca na grade** | **0** (não existe) | **1** (`input[placeholder*=Buscar]`) | ❌ **adição indevida — e MORTA: o controller não trata `?q=`** |
| 8 | **Painel Pendências** | presente (5 itens com badge) | **ausente** | ❌ |
| 9 | **Contagem por aba** | **9 abas com número** (7·5·12·4·6·3·2·8·9) | 5 abas, sem número | ❌ contagem · ⚠️ abas: ver nota |
| 10 | **Sparkline no KPI Líquido** | presente | ausente | ❌ (2 evidências: `grep spark` 1×0 + render) — **SEGUE ABERTO em 2026-09-03**, ver Errata |
| 11 | **Rodapé da grade** ("N de M · clique para abrir") | presente | ausente | ❌ **é a única affordance do drawer** |
| 12 | **Alert de rodapé** | "Herança do Blade que não foi portada" | "Precisa dos gráficos de vendas…" | ❌ texto trocado **e factualmente falso** (os gráficos estão logo acima) |
| 13 | Tom semântico dos KPI | `success` (Vendas) · `warning` · `info` (Despesas) | só `warning` | ❌ 2 de 3 |
| 14 | Densidade/altura da tabela | `density="compact"` + `height={300}` | `shared/DataTable` **não tem** essas props | ⚠️ limite do componente canon |
| 15 | Coluna "Loja" em Lotes a vencer | não existe | **existe** | ⚠️ adição minha |
| 16 | "Setor solicitante" (Requisições) | "Setor solicitante" | "Solicitante" | ⚠️ copy encurtada |
| 17 | Simular falha / Papel simulado | presentes | ausentes | ✅ correto (instrumentos de protótipo) |
| 18 | Filtro de loja | "Todas as lojas" | ausente | ✅ correto (o negócio tem 1 loja; a UI exige > 1) |

**Nota sobre o #9 (abas):** as 4 abas a menos **não** são falta de implementação — 3 estão atrás
de setting do negócio (`enable_product_expiry`, `enable_purchase_order`,
`enable_purchase_requisition`, desligados neste tenant) e "Fluxo de caixa" não tem fonte no
Blade. O que falta de verdade ali é a **contagem**.

## O que isso soma

- **6 regressões de fidelidade** não declaradas: #6, #7, #10, #11, #12, #13
- **1 controle morto em produção**: a busca (#7) — o usuário digita e nada acontece
- **3 divergências menores**: #14, #15, #16
- **1 texto factualmente errado na tela**: #12

Os itens #6, #7, #11 e #12 são os que mudam o **comportamento** percebido, não só a aparência.

## Por que os gates não pegaram nada disso

O `visual-regression` e o `contrato-de-tela` passaram **verdes** no PR que entregou esta tela.
Eles não cobrem esta classe: o primeiro compara a tela **contra ela mesma** (baseline anterior),
não contra a âncora; o segundo confere copy e ordem declaradas no contrato — e esta tela **não
tem** `## Contrato visual` no charter, então não havia o que conferir.

A comparação contra a âncora **não tem gate**: ela depende de alguém rodar a sonda, e a sonda
depende de sessão autenticada em produção. É o buraco estrutural que este inventário expõe.

---

## Rodada 2 — 2026-08-28 · o ATALHO DE TOPO desta tela (shell), não o corpo

> **Escopo, dito antes do veredito:** esta rodada compara o **atalho de sidebar que leva a esta
> tela** — não o corpo dela (a Rodada 1 acima cobriu o corpo; o #6392 fechou **5 das 6** — ver a Errata da Rodada 3, no fim). É comparação
> de **CONTRATO** (fonte do design × fonte da implementação), **não de runtime**: eu **não** rodei
> a sonda nos dois renders nesta rodada, porque não havia render de prod disponível na sessão.
> Logo **D2/D4/D6/D8 seguem NÃO MEDIDOS aqui** — nada abaixo vale como veredito de pixel, cor ou
> contraste. O que está medido é o que o design **declara** contra o que o código **faz**.

**Fonte:** `prototipo-ui/cowork/data.jsx` (bloco `MENU`, atalhos de topo) + `sidebar.jsx` (L270-285).
⚠️ Frescor **não** re-provado nesta rodada (`--compare --check` não rodado) — vale contra o
espelho como está no git hoje, e a ressalva de fidelidade do §0 continua valendo.

O design declara, literalmente:

```js
// ── Shortcuts de topo (não-grupos) — [W] 2026-08: IA · Forja · Atendimento (Equipe → MAIS) ──
{ id: "chat",        icon: "chat",  label: "IA",          shortcut: true },
{ id: "dash-legacy", icon: "chart", label: "Visão geral", shortcut: true },
{ id: "inbox",       icon: "inbox", label: "Atendimento", shortcut: true },
```

| # | Camada | Âncora | Produção (antes desta rodada) | Veredito |
|---|---|---|---|---|
| 19 | Label do atalho | "Visão geral" | "Dashboard" (pt) · "Home" (en) | ❌ → **corrigido** (chave própria `home.visao_geral`) |
| 20 | **Posição no topo** | **2ª** (`chat` → `dash-legacy` → `inbox`) | inexistente; minha 1ª versão pôs em **1º**, em bloco próprio | ❌ → **corrigido** (2ª, nos dois modos) |
| 21 | **Ícone** | `chart` (eixo + 3 barras — `icons.jsx:21`) | `Home` na minha 1ª versão | ❌ → **corrigido** (`BarChart3`) |
| 22 | **`aria-current="page"`** | presente (`sidebar.jsx:278`) | ausente (nem o `SidebarMenuItem` marcava) | ❌ → **corrigido** no atalho |
| 23 | Alocação | entry **sem grupo** → topo | caía em **SISTEMA**, o último grupo, por match do label 'Dashboard' | ❌ → **corrigido** (`group: 'landing'`) |
| 24 | **Bloco de atalhos** | `.sb-item` inline no `sb-menu`; **`sb-shortcuts` não existe** no espelho inteiro | `.sb-shortcut` em bloco `.sb-shortcuts` próprio | ⚠️ **PROD DIVERGE — pré-existente, NÃO corrigido** |
| 25 | Forja no topo | 3 atalhos; comentário diz "Equipe → MAIS" | 4 atalhos (IA · Forja · Atendimento · Visão geral) | ⚠️ **PROD-À-FRENTE** ou design atrasado — não tocado |

**#24 e #25 não são desta rodada.** O #24 afeta IA/Forja/Atendimento **igualmente** — é divergência
de shell anterior a este trabalho, e reconciliar (trocar `.sb-shortcut` por `.sb-item` inline)
mexeria no topo de **todas** as telas. É decisão [W], não carona de um PR de dashboard.

### Adaptação declarada — importei a forma, não a premissa

O design marca atalho de topo pela **ausência** de `group` (`if (!entry.group)`). Copiar isso
literalmente **quebraria aqui**: no `Sidebar.tsx` real, item sem `group` cai no match por label e,
não casando nenhum `SIDEBAR_GROUPS.items[]`, termina em **MAIS** — o fim do menu. O design não tem
esse fallback porque o `MOCK.MENU` é estático. Por isso a implementação declara `group: 'landing'`
explícito: mesma intenção, mecanismo compatível com a premissa daqui.

### O que continua NÃO MEDIDO (e por quê)

1. `cowork-mirror-freshness --compare --check` — frescor da fonte, **não rodado** nesta rodada.
2. Sonda `design-diff.mjs --probe` nos dois renders (D2/D4/D6/D8) — exige prod logada.
3. D1 (rede) e D3 (ícones) no runtime.
4. Baseline VRT: `visreg-screens.json` tem **35 entradas / 35 `.snap`, zero sem par**; somar a Home
   exige `--update-snapshots` no CT 100/CI **+ aprovação [W] (gate F1.5)**.

---

## Rodada 3 — 2026-08-28 · RUNTIME, os dois lados medidos ([W]: "pode abrir e fazer o comparativo")

> **Esta rodada corrige o escopo da Rodada 2.** Lá eu comparei CONTRATO (fonte × fonte) e declarei
> D2/D4/D6/D8 NÃO MEDIDOS. Agora foram: design servido em `localhost:5601` (portão
> `--preview-ds` OK, 10 deps repostas) × **produção logada** via Chrome real. Mesmo tema (**dark**
> nos dois — conferido, não suposto). Sonda `design-diff.mjs --selftest` passou 6/6 antes de
> qualquer veredito (sonda quebrada ≠ tela igual).
>
> ⚠️ **O lado prod é o estado ANTES deste PR** — a mudança ainda não está deployada. Logo a tabela
> mede **o gap que o PR fecha**, não o resultado dele. O "depois" só se mede pós-deploy.
> Leitura estabilizada: li `innerText.length` duas vezes com intervalo nos dois lados e só
> concluí com o número parado (§5 2026-08-24 — não medir durante lazy-load).

### Atalhos de topo — o que o PR fecha

| # | Camada | Design (medido) | Prod hoje (medido) | Veredito |
|---|---|---|---|---|
| 26 | Sequência | `IA` → **`Visão geral`** → `Atendimento` | `IA` → `Forja` → `Atendimento` | ❌ falta a entry — **é o que o PR entrega** |
| 27 | `aria-current` no ativo | `"page"` | **nenhum atalho tem** | ❌ — o PR passa a marcar |
| 28 | Classe `active` | presente no item da rota | nenhuma | ❌ — o PR passa a aplicar |
| 29 | Ícone da Visão geral | paths `M4 19V5M4 19h16` + `M8 15v-4M12 15V9M16 15v-7` (eixo + 3 barras) | — | ✅ o PR usa `BarChart3`, mesma forma |

### Divergências de FUNDAÇÃO — pré-existentes, NÃO tocadas por este PR

Afetam `IA`/`Forja`/`Atendimento` **igualmente**; existem antes deste trabalho.

| # | Propriedade | Design | Prod | Nota |
|---|---|---|---|---|
| 30 | **Altura do atalho** | **34px** | **30px** | ❌ 4px |
| 31 | **Cor do texto** | `oklch(0.8 0.008 295)` | `oklch(0.78 0.005 90)` | ❌ **hue 295 × 90** — não é ajuste fino, é outro eixo de cor |
| 32 | `font-size` | 13px | 13.5px | ⚠️ 0,5px |
| 33 | `padding` · `gap` | `0px 10px` · `10px` | `0px 10px` · `10px` | ✅ |

**O achado que explica o #30 e fecha o #24 da Rodada 2:** em produção o `.sb-item` de **grupo** mede
**34px** — exatamente o do design. Só o `.sb-shortcut` mede 30. Ou seja, o design não tem duas
classes: o atalho de topo **é** um `.sb-item`, e prod inventou uma segunda forma pro mesmo papel.
A Rodada 2 já registrava que `sb-shortcuts` **não existe em lugar nenhum do espelho** — agora a
divergência tem número.

**Consequência para este PR, dita sem rodeio:** a "Visão geral" entrou como `.sb-shortcut`, então
nasce com **30px** — coerente com os vizinhos dela em produção, e **divergente dos 34px do design**.
Foi escolha consciente: usar `.sb-item` deixaria o item novo 4px mais alto que `IA` e `Atendimento`
ao lado, o que é pior visualmente do que a divergência herdada. Reconciliar de verdade é converter
o bloco inteiro (`.sb-shortcut` → `.sb-item`), que mexe no topo de **todas** as telas — decisão [W],
PR próprio.

### Não comparado nesta rodada (e por quê)

- **Largura do sidebar**: medi seletores DIFERENTES nos dois lados (`aside` no design,
  `.sb-shortcuts.parentElement` em prod) — os números (260 × 249) **não são comparáveis** e não
  viram veredito. Refazer com o mesmo seletor.
- **Corpo da tela** (KPI/contrapartidas/gráficos/grades): o design **não tem** `[data-contract]`
  (medido: 0 âncoras) — é instrumentação só-de-prod, então essa metade da sonda não tem par.
  O corpo já foi coberto pela Rodada 1 (sonda pareada) e as 6 regressões dela foram fechadas no #6392.
- **D1 (rede/partial-reload)** e **contraste par-a-par**: fora do escopo desta rodada.


---

## Rodada 3 — 2026-09-03 · ERRATA: o #6392 fechou 5 das 6, não 6

> **Escopo:** re-mediu-se o CORPO da tela (a Rodada 1) com a **mesma sonda nos dois renders**,
> a pedido de [W] (*"abra a âncora e olhe visualmente com a produção"*). Isto **corrige uma
> afirmação deste próprio documento**, não um defeito novo.

**A afirmação corrigida.** O cabeçalho da Rodada 2 dizia *"o #6392 fechou as 6"*. Medido hoje:
ele fechou **5**. O item **#10 (sparkline no KPI Líquido) nunca foi implementado** —
`git show 29f889c570 -- resources/js/Pages/Home/Index.tsx` não contém uma linha de sparkline
(o único bloco de gráfico que ele move é o `GraficosVendas`, que é outro componente). O mesmo
PR **escreveu** a linha 10 desta tabela marcando o item como ❌ e não o corrigiu.

### Como foi medido (sonda idêntica nos dois lados)

- **Âncora**: espelho servido em `127.0.0.1:5602`, shell `oimpresso.com.html`, tela `dash-legacy`
  (portão `cowork-mirror-freshness --preview-ds` passou: 10 dependências repostas, `rc=0`).
- **Produção**: `oimpresso.com/dashboard-legacy`, sessão real, viewport 2560 × 951.
- **Canário** (a sonda discrimina?): `pendencias` devolveu `PRESENTE` na âncora e `AUSENTE` na
  produção — uma diferença **conhecida de antemão**, logo a sonda não é carimbo.

| Item | Âncora | Produção | Veredito hoje |
|---|---|---|---|
| #6 Colunas ordenáveis | 4 | **4** | ✅ **fechado** pelo #6395 |
| #7 Campo de busca | 0 | **0** | ✅ **fechado** |
| #8 Painel Pendências | PRESENTE | AUSENTE | ⏸️ **declarado** — Backlog do charter, decisão [W] |
| **#10 Sparkline no KPI Líquido** | **PRESENTE** | **AUSENTE** | ❌ **ABERTO e NÃO DECLARADO** |
| #11 Rodapé da grade | presente | **presente** | ✅ **fechado** |
| #12 Alert de rodapé | texto próprio | **ausente** | ✅ o texto falso saiu |
| #9 Contagem por aba | 9 abas com número | 5 abas, 0 números | ⏸️ **Non-Goal declarado** |
| Exportar CSV | PRESENTE | AUSENTE | ⏸️ **Non-Goal declarado** |
| a11y — tabela `sr-only` | 0 | **2** | ✅ **produção à frente** |
| Âncoras `data-contract` no DOM | — | `cabecalho>kpis>contrapartidas>graficos>grades` | ✅ ordem canônica |

### Por que o #10 é diferente dos outros que faltam

Contagem por aba, Exportar CSV e a aba "Fluxo de caixa" estão como **Non-Goal no charter**, com
razão escrita. Pendências está no **Backlog** do charter (*"entra se [W] quiser o atalho"*).
Todos são **decisão registrada**.

O sparkline não é: `grep -i "spark"` devolve **zero** ocorrências em `Index.charter.md` e em
`Index.casos.md`. Ele ficou órfão entre "corrigido" e "Non-Goal" — detectado, contado entre as
6, e nunca implementado nem declarado. **Fechar isso é decisão [W]:** implementar (o
`charts.dia` já existe, mas chega por `Inertia::defer` e o KPI é síncrono — haveria flash, ou
uma segunda fonte de série) **ou** declarar Non-Goal com razão. Este documento não escolhe.

### O que isto diz sobre os gates (e não mudou desde a Rodada 1)

A seção *"Por que os gates não pegaram nada disso"* segue **inteira**. O sparkline sobreviveu a
três PRs de fidelidade com CI verde porque a comparação **contra a âncora** não tem gate: o
`visual-regression` compara a tela contra ela mesma, e o `contrato-de-tela` só confere o que o
charter declara — e esta tela **segue sem `## Contrato visual`**. Enquanto for assim, achado
desta classe depende de alguém rodar a sonda à mão, como foi hoje.
