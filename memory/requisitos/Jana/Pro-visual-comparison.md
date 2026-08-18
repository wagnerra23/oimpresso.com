# Jana Pro (`/ia/pro`) — protótipo × tela viva, por região e componente

- **Data da medição:** 2026-08-17 · **âncora:** `Jana Pro - Paywall CC.html` — **não existe no repo**; lida no Cowork vivo via `DesignSync.get_file`, path `_arquivo/exploracoes-2026-06-04/Jana Pro - Paywall CC.html` (retorno `truncated: false`, arquivo completo)
- **Tela viva:** `resources/js/Pages/Jana/Pro.tsx` (+ `ProPage`, `CmpRow`, `TrustRow` no mesmo arquivo)
- **Backend:** `Modules\Jana\Http\Controllers\ProController@index` — rota `jana.pro.index`
- **Charter:** `resources/js/Pages/Jana/Pro.charter.md` v1 — `status: draft`
- **Contrato:** `resources/js/Pages/Jana/Pro.casos.md` — UC-PRO-01..06, `ProContractTest`, lane `jana-pest.yml`
- **Gate F1.5:** esta tela **NÃO** está em `tests/Browser/visreg-screens.json` (`Jana/Pro` → **0 ocorrências**). Sem baseline de pixel, mudança visual aqui passa sem diff — ao contrário da Memória e do Painel

> **Como ler:** ✅ existe e equivale · 🟡 existe mas diverge · ❌ não existe na tela viva · 🟢 **só na viva** (o protótipo não tem — apagar seria regressão) · ⛔ existe e **não deve** ser copiado.

> 💰 **Valores monetários deste documento saem como `R$ [redacted Tier 0]`** — regra Tier 0 de `proibicoes.md`, enforçada pelo hook `block-brl-values-in-memory` (que **bloqueou a 1ª gravação deste arquivo**, corretamente). Nenhum veredito abaixo depende do número em si: o que se compara é *"o protótipo e a viva dizem a mesma coisa?"*.

---

## ⚠️ Re-medido contra `origin/main` fresco antes de persistir

A 1ª redação saiu de um checkout **13 commits atrás** do `origin/main`. Ao persistir, as fontes desta tela
foram re-conferidas contra o main fresco (`bf3a533d0`): `Pro.tsx`, `Pro.charter.md`, `Pro.casos.md` e
`ProController.php` estão **idênticos** — nenhum veredito mudou.

O `tests/Browser/visreg-screens.json` **mudou** na janela (o `Jana/Chat` entrou), e por isso a afirmação
foi re-medida no arquivo fresco: `"Jana/Pro"` segue com **0 ocorrências**. Dos quatro alvos Jana com
charter, este é o único sem baseline de pixel.

---

## ⚠️ O que esta comparação É e o que ela NÃO É

Comparação **estrutural**, por leitura do HTML do protótipo e do `.tsx` da viva. Responde *"existe / não existe / diverge"*.

**Não mede fidelidade visual.** Fidelidade exige `cowork-mirror-freshness --compare --check` = **SYNC** + sonda `design-diff --probe` nos dois renders (skill `comparar-design-prod`). **Nenhum dos dois rodou** — o `--compare` recusou por falta de `snapshot.json`. Portanto **nada aqui afirma "fiel"**.

Nesta tela a ressalva pesa mais que na Memória, por dois motivos independentes:

1. **o protótipo não está no espelho local** (§Ponteiros podres abaixo), então não há o que o `--compare` compare;
2. o protótipo é **CSS cru com variáveis próprias** (`--primary`, `--line`, `--emerald`) e a viva é **Tailwind com tokens do DS** (`text-primary`, `border-border`, `text-success`). Cada par pode ser idêntico ou não — **isso não foi medido em nenhuma linha deste documento**.

---

## ⚠️ Ponteiros podres encontrados na medição

| ponteiro | onde | estado real |
|---|---|---|
| `prototipos/jana-pro/critique-score.json` | `ProController.php:17` (docblock) | **não existe no repo** — `prototipo-ui/prototipos/` tem só `compras-grade-matrix`, `inventario-migracao`, `perfil` |
| `prototipos/jana-pro/` + `COMPARISON.md` | `Pro.charter.md` §Refs e §Status | idem — existem **só** no Cowork, sob `_arquivo/repo-mirror/prototipos/jana-pro/` (3 arquivos: `COMPARISON.md`, `F2-aprovado.png`, `critique-score.json`) |
| `Jana Pro - Paywall CC.html` | `Pro.tsx:4` (`design:`) e charter | existe **só** no Cowork, em `_arquivo/exploracoes-2026-06-04/` — pasta `_arquivo`, isto é, **arquivado** |
| `related_prototype` | frontmatter do charter | **ausente** — `ancora.mjs Jana/Pro` devolve `⚠️ charter sem related_prototype nem -page.jsx` |
| `RUNBOOK` | resolução por nome | **⚠️ AMBÍGUO (2)**: `RUNBOOK-jana-advisor-proativo.md` · `RUNBOOK-jana-pro-concierge.md`. Nenhum é declarado no charter, e **nenhum dos dois é o RUNBOOK desta tela** (concierge é operação de brief; advisor é outra coisa) |

> Consequência prática: **o gate F1.5 desta tela cita evidência (`PASS 90`) que ninguém no repo consegue abrir.** O escore pode ter existido — o `critique-score.json` está lá, no Cowork, arquivado. Mas o repo afirma um recibo que ele não guarda.

---

## R1 · Shell e sidebar

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| grid do app | `.app` — `grid-template-columns: 230px 1fr` | `AppShellV2` | ✅ equivalente (a viva usa o shell real) |
| sidebar dark | `.side` `oklch(0.22 0.01 285)` | sidebar canon do `AppShellV2` (dark-fixo, UI-0023) | ✅ |
| itens do menu | Tarefas · **Jana** · Chat · OS · Clientes · Produtos · Vendas · Caixa | menu real do `DataController` | ⛔ **não copiar** — é o mock do protótipo |
| rodapé "Larissa · Administradora" · "ROTA LIVRE · biz 4" | hardcoded | usuário/business reais da sessão | ⛔ não copiar |
| **badge `PRO`** no item Jana da sidebar | `.badge-pro` roxo | — | ❌ — decisão de produto em aberto |

## R2 · Header (modo FOCO)

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| modo | sem sub-navegação de abas | idem — sem `JanaSubNav` | ✅ (é o Goal "modo FOCO" do charter) |
| breadcrumb | `<b>Jana</b> · Plano` | idêntico | ✅ literal |
| título + tag | `Jana Pro` + `.pro-tag` "UPGRADE" | idêntico | ✅ literal |
| ação de saída | botão "Voltar ao chat" com seta | idêntico (`ArrowLeft` + `router.visit('/ia')`) | ✅ |
| posicionamento | `position: sticky; top: 0` + `backdrop-filter: blur(8px)` | `shrink-0` em coluna flex + `backdrop-blur` | 🟡 mecanismo diferente, efeito equivalente |
| destino do "Voltar" | `#` (sem ação — é mock) | `/ia` | 🟢 só na viva |

> ⚠️ O botão diz **"Voltar ao chat"** mas vai pra **`/ia`**, que desde a fusão é o **Painel** — a Conversa é `/ia/conversa`. Copy e destino discordam. Não é divergência com o protótipo (lá o botão não vai a lugar nenhum); é defeito próprio da viva.

## R3 · Hero — pitch (coluna esquerda)

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| eyebrow | ícone + "A Jana já trabalha pra você" | idêntico (`Sparkles`) | ✅ literal |
| headline | "Ela conhece o seu negócio.<br>O *Pro* tira as amarras." com `em` colorido | idêntico (`<em className="not-italic text-primary">`) | ✅ literal |
| parágrafo | "No plano grátis a Jana responde com seus dados reais — mas esquece rápido e não age sozinha…" | idêntico | ✅ literal |
| linha do plano atual | `.nowline` — chip "Seu plano hoje: Grátis" + "· memória de 7 dias · 1 meta · sem brief automático" | idêntica, **condicionada a `plan === 'free'`** | ✅ copy literal + 🟢 o condicional |
| grid | `1.05fr 0.95fr` | `lg:grid-cols-[1.05fr_0.95fr]` | ✅ mesmas frações |

## R4 · Card de prova ("a Jana lendo seu ERP")

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| fundo dark + overlay radial | `.proof` + `::before` radial-gradient | `PROOF_BG` + div `aria-hidden` com `PROOF_OVERLAY` | ✅ mesmos valores oklch, inline |
| avatar "J" + gradiente | `.jav` linear-gradient 135° | idêntico, inline | ✅ |
| indicador "lendo seu ERP" | ponto verde com halo + texto | idêntico | ✅ literal |
| bolha do usuário | "Jana, como foi meu faturamento esse mês?" | idêntica | ✅ literal |
| bolha da Jana | "Maio fechou acima de abril. Veja pelos 3 ângulos:" | idêntica | ✅ literal |
| **os 3 ângulos** | rótulos Bruto · Líquido · Caixa, com os três valores **hardcoded no HTML** | mesmos rótulos, via `fmtBRL(proof.bruto/liquido/caixa)` — props do Controller. **Os três valores conferem, um a um, com os do protótipo** | ✅ + 🟢 parametrizado |
| Caixa em verde | `b.pos` `oklch(0.74 0.13 150)` | `NUM_POS` idêntico | ✅ |
| rodapé | "Números reais das suas tabelas — sem planilha, sem integração." | idêntico | ✅ literal |
| `overflow-hidden` no card | sim | sim | ✅ |

> **Nota de escopo (não é divergência com o protótipo).** `proof` é **mock** no `ProController` — o charter diz que a Onda B liga em `BriefDiarioService::snapshot()`. Enquanto isso a copy afirma *"Números reais das suas tabelas"* sobre números que não são do business. O protótipo tem a mesma copy e a mesma ausência, então **paridade não resolve isto** — é decisão de produto.

## R5 · Comparação Grátis × Pro

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| grid | `1fr 130px 150px` | `grid-cols-[1fr_130px_150px]` | ✅ idêntico |
| cabeçalho | "Recurso" · "Grátis" · "JANA PRO" + "tudo do Grátis, e mais" | idêntico | ✅ literal |
| linha 1 | Brief diário às 06h — ✗ / ✓ | idêntica | ✅ literal |
| linha 2 | Análises automáticas — ✗ / ✓ | idêntica | ✅ literal |
| linha 3 | Cockpit Saúde — ✗ / ✓ | idêntica | ✅ literal |
| linha 4 | Memória persistente — "7 dias" / "Ilimitada" + tag `PRO` | idêntica | ✅ literal |
| linha 5 | Metas governadas + alertas — "1 meta" / "Ilimitadas" | idêntica | ✅ literal |
| linha 6 | Chat com dados reais do ERP — ✓ / ✓ (fecha como base dos dois) | idêntica | ✅ literal |
| coluna Pro destacada | `--primary-soft` + borda esquerda | `bg-primary/[0.06]` + `border-l border-primary/15` | 🟡 aproximação por token (**não medido**) |
| semântica da tabela | `div` com `grid` — sem `<table>`, sem `<th>` | idem | 🟡 os dois erram junto — leitor de tela não lê como tabela |

## R6 · Preço

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| título da seção | "Preço honesto" + linha | idêntico | ✅ literal |
| valor | mensalidade em mono 38px, **hardcoded** | `fmtBRL(pricing.monthly)` — **mesmo valor**, `font-mono text-[38px]` | ✅ + 🟢 parametrizado |
| unidade | "/ mês · por empresa" | idêntica | ✅ literal |
| **comparação com concorrentes** | "Conta Azul Numia: ~~R$ [redacted Tier 0]/mês~~ · Copilot for Finance: ~~R$ [redacted Tier 0]/mês~~ · **você economiza ~50%**" — **com os números** | mesma frase, mas os dois preços saem como a **string literal** `R$ [redacted Tier 0]/mês` | ❌ **defeito visível** — ver nota |
| 3 bullets de garantia | sem fidelidade · IA inclusa · N dias pra testar | idênticos, com `{pricing.trialDays}` no 3º | ✅ literal + 🟢 |

> ### ❌ Nota — a redação de BRL vazou para a copy renderizada
>
> `Pro.tsx:315-316` contém **literalmente** o texto `R$ [redacted Tier 0]/mês` dentro de `<s>`. Não é comentário: é JSX que **vai pra tela**. O visitante do paywall lê *"Conta Azul Numia: R$ [redacted Tier 0]/mês"* — o sentinela de redação, no lugar do preço do concorrente.
>
> A origem é conhecida e está catalogada em `memory/proibicoes.md`: o `git filter-repo --replace-text` de 2026-06-08, aplicado a **5.033 commits**, atingiu **código**, não só documentação. Confirmado em `origin/main` (mesmas linhas, mesmo conteúdo).
>
> **A classe é maior que esta tela** — medido: **18 arquivos** sob `resources/js/**/*.tsx` contêm a string, e parte é copy visível (`Components/Site/PricingTiers.tsx`, `Components/Site/DashboardMockup.tsx`, `Pages/TransactionPayment/Edit.tsx:55` como *fallback de formatação*). Aqui só se **registra** o achado desta tela; diagnosticar e consertar a classe inteira é trabalho próprio, com varredura contada e decisão [W] sobre quais números podem voltar ao git.

## R7 · Confiança

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| título | "Por que confiar" | idêntico | ✅ literal |
| linha 1 | escudo · "Seus dados são só seus" · "Isolamento por empresa garantido no núcleo do sistema — ninguém vê o que é seu." | idêntica (`Shield`) | ✅ literal |
| linha 2 | check em círculo · "LGPD por padrão" · "Retenção declarada por tipo de dado…" | idêntica (`BadgeCheck`) | ✅ literal |
| linha 3 | cadeado · "Hospedado no Brasil" · "Infra nacional, custo transparente. Sem dado saindo do país." | idêntica (`Lock`) | ✅ literal |
| separador | `border-top` exceto no primeiro | prop `first` faz o mesmo | ✅ |

## R8 · Footer sticky e CTA

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| resumo | "**Jana Pro** · <mensalidade>/mês · N dias grátis" | idêntico, via `priceLabel` e `pricing.trialDays` | ✅ literal + 🟢 |
| ação secundária | "Falar com a Jana sobre o Pro" — `id="talkBtn"`, **sem listener** | mesmo rótulo → `router.visit('/ia')` | ✅ copy · 🟢 a ação |
| CTA primária | "Ativar Jana Pro" com seta, roxo | idêntica (`ArrowRight`) | ✅ literal |
| posicionamento | `position: sticky; bottom: 0` + blur | `shrink-0` em coluna flex + `backdrop-blur` | 🟡 mecanismo diferente, efeito equivalente |

## R9 · Interação, estados e teclado

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| CTA `idle → activating` | `opacity .85` + label "Ativando…" | `state === 'activating'` + `disabled` + `disabled:opacity-[0.85]` | ✅ |
| CTA `→ done` | vira verde (`--emerald`) + "Jana Pro ativo · N dias grátis" | `bg-success` + mesma copy | ✅ literal |
| delay do mock | `setTimeout(..., 900)` | `window.setTimeout(..., 900)` | ✅ idêntico |
| guarda contra duplo clique | `busy` + `dataset.done` | `if (state !== 'idle') return` | ✅ |
| atalho `⌘/Ctrl+Enter` | sim | sim | ✅ |
| **atalho `Esc` volta ao chat** | **não existe** no protótipo | `e.key === 'Escape'` → `voltar()` | 🟢 só na viva (é Goal do charter) |
| `aria-live` na CTA | — | `aria-live="polite"` | 🟢 só na viva |
| listener de teclado global | `document.addEventListener` sem cleanup (é mock) | `useEffect` com `removeEventListener` | 🟢 só na viva |

## R10 · Responsivo, tokens e a11y

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| breakpoint do colapso | `@media (max-width: 1080px)` → hero e preço viram 1 coluna | `lg:` do Tailwind = **1024px** | 🟡 **56px de diferença** — entre 1024 e 1080 as duas discordam |
| sidebar colapsada a 64px | sim, no mesmo media query | responsabilidade do `AppShellV2` | ⚠️ não medido (é do shell, não desta tela) |
| largura do canvas | `max-width: 1060px` | `max-w-[1060px]` | ✅ idêntico |
| `:focus-visible` | `outline: 2px solid var(--primary-ring); offset 2px` **global** | `focus-visible:outline-*` — **só no `btnGhost`** | 🟡 na viva o foco visível não é global; a CTA primária não o declara |
| números tabulares | `.mono` com `font-variant-numeric: tabular-nums` | `font-mono tabular-nums` | ✅ |
| fundo da página | `--bg: oklch(0.975 0.004 90)` | `bg-page-cream` | ⚠️ **não medido** — o token pode ou não bater |

---

## Inventário de cobertura — Goal do charter × implementado × tem contrato

| # | Goal / Anti-hook do charter | implementado | tem UC citado por teste |
|---|---|---|---|
| G1 | Shell `AppShellV2` (sidebar dark Cockpit V2) | ✅ | ❌ (⬜ visual) |
| G2 | **Modo FOCO** — sem `JanaSubNav` | ✅ | ❌ (⬜ visual) |
| G3 | Hero 2 colunas: pitch + card de prova 3 ângulos | ✅ | 🟡 UC-PRO-02 cobre o **contrato de props** (`proof.bruto/liquido/caixa`), não o render |
| G4 | Comparação Grátis vs Pro, 6 linhas | ✅ | ❌ |
| G5 | **Preço honesto** vs Numia / Copilot | 🟡 mensalidade própria ok; **os dois comparativos saem redigidos** (R6) | 🟡 UC-PRO-03 cobre `pricing.monthly` e `pricing.trialDays` — **não** a copy comparativa |
| G6 | Confiança: Tier 0 · LGPD · BR | ✅ | ❌ |
| G7 | Footer sticky + secundária + CTA | ✅ | ❌ |
| G8 | CTA `idle → Ativando… → Pro ativo` | ✅ | ❌ (⬜ client-side, declarado no casos.md) |
| G9 | Atalhos `⌘/Ctrl+Enter` e `Esc` | ✅ (o `Esc` **excede** o protótipo) | ❌ (⬜) |
| G10 | Tokens canon (roxo 295, `text-success`, zero `blue-*`/emoji) | ⚠️ não medido | ❌ |
| H1 | `ProController@index` entrega `plan`/`pricing`/`proof`/`business` | ✅ | ✅ UC-PRO-01/02 |
| H2 | `proof` liga em `BriefDiarioService::snapshot()` na Onda B | ❌ ainda mock | ✅ UC-PRO-04 (registra o mock como estado atual, com nota de escopo) |
| N1 | Sem billing real (Sprint JANA-B) | ✅ | ✅ UC-PRO-04/06 |
| N2 | Sem WhatsApp como canal | ✅ (`/ia`) | ❌ |
| N3 | Nunca mostrar outro `business_id` | ✅ sessão | ✅ **UC-PRO-05** (Tier 0) |
| N4 | Sem escrita no banco no render | ✅ | ✅ UC-PRO-06 |
| N5 | Sem email/SMS/WhatsApp/LLM no render | ✅ | 🟡 UC-PRO-06 mede **idempotência de props**, que é proxy — não prova ausência de dispatch |
| U1 | Cabe em 1280px sem rolar muito | ⚠️ não medido | ❌ |
| U2 | `:focus-visible` em **todo** interativo | 🟡 só no `btnGhost` (R10) | ❌ |

**Placar:** 19 itens · **6 cobertos por UC** (1 deles parcial) · 2 com cobertura só de contrato-de-props · 8 implementados-sem-contrato · 1 não implementado · 3 não medidos.

**Status dos 6 UCs:** todos `🧪` no `casos.md`. `screen-coverage --screen Jana/Pro` confirma o vínculo UC↔teste dos seis (`✓ UC-PRO-01..06`) — isso prova **linkagem**, não run verde.

**Assimetria que salta:** os 6 UCs cobrem **o Controller**, e nenhum toca a tela. É coerente com a honestidade de escopo já escrita no `Pro.casos.md` (*"tela de conversão majoritariamente visual"*) — mas some com a rede quando se lembra que **`Jana/Pro` não está no visreg**. Não há Pest de tela **nem** baseline de pixel: a camada visual desta tela está descoberta nas duas pontas.

---

## Resumo — o que falta, por tamanho

| ordem | entrega | região | por que primeiro |
|---|---|---|---|
| 1 | **Restaurar os 2 preços comparativos** (hoje o sentinela de redação) | R6 | é texto quebrado **em produção**, numa tela de venda. Exige decisão [W]: são preços de concorrente público, não BRL do negócio |
| 2 | Corrigir "Voltar ao chat" → `/ia/conversa`, **ou** a copy | R2 | rótulo e destino discordam desde a fusão |
| 3 | Pôr `Jana/Pro` no `visreg-screens.json` | gate | é a única tela Jana com charter e casos e **sem** baseline de pixel |
| 4 | Declarar `related_prototype` + resolver o RUNBOOK ambíguo | ponteiros | hoje `ancora.mjs` não resolve e o `screen-coverage` acusa `⚠ AMBÍGUO (2)` |
| 5 | Trazer o protótipo pro espelho (ou declarar que ele é arquivado) | fonte | a fonte de design desta tela vive **só** no Cowork, sob `_arquivo/` |
| 6 | `:focus-visible` na CTA primária e no "Voltar" | R10 | UX target do charter; hoje só o `btnGhost` declara |
| 7 | Ligar `proof` em `BriefDiarioService::snapshot()` | R4 | a copy promete "números reais das suas tabelas" sobre mock |
| 8 | Reconciliar breakpoint 1024 × 1080 | R10 | 56px onde as duas fontes discordam |

---

## Decisões [W] em aberto

- **Os preços de concorrente podem voltar ao git?** São valores públicos de terceiros, não do negócio — mas a proibição em `proibicoes.md` é redigida sobre o **padrão** `R$ <número>`, não sobre a origem do número, e o hook bloqueia igual (bloqueou este documento). Restaurar sem decisão reabre a discussão que gerou o filter-repo. Sem essa decisão, a alternativa é **remover a frase comparativa** da tela.
- **Badge `PRO` na sidebar** (R1) — o protótipo desenha; a viva não tem. É produto (sinalizar plano no menu global), não paridade de tela.
- **Charter `draft` → `live`** — `screen-coverage` responde *"✗ NÃO pode ligar (charter `draft` · zero sinal de prod)"*. A tela está roteada e renderiza; promover é ato [W].
- **O gate "PASS 90" continua valendo?** O recibo (`critique-score.json`) existe **só no Cowork arquivado**. Ou se traz a evidência pro repo, ou o charter para de afirmar o escore.
- **Enterprise / 3º plano** — Non-Goal explícito do charter ("só em GA, Sprint JANA-C"). Registrado para não ser reaberto por paridade.
