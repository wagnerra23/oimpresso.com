# FRESCOR — Produção (`resources/js/Pages/**`) vs Protótipo (Cowork export)

> **Canal reverso Code → Design.** Onde a **produção passou** o protótipo, o design deve **puxar o estado vivo** (parar de tratar o export como fonte). Onde a **produção está atrás**, é catch-up real (build).
> Mantido pelo **Claude Code** ao fim de cada sync/Fase 1. Lido pelo **Cowork** no início da sessão (junto de `CODE_NOTES.md`).
> Regra-mãe: **camada de design** = Cowork manda; **memory/ADR/resources/** = repo manda, design só lê (ver ADR-proposta `2026-06-23-prototipo-ssot-unico-com-historico`).

**Legenda:** 🔵 produção À FRENTE (design puxa) · 🟠 produção ATRÁS (catch-up real) · ⚪ fundação/empate de governança · ✅ em paridade.

## Quadro (Fase 1 · 2026-06-23 · revisado 2026-09-23 @`1061dbf2e0f6`)

> A revisão de 23/09 veio do pedido `prototipo-ui/cowork/Wagner/cowork-inbox/frescor/PEDIDO-FRESCOR-ATUALIZAR-2026-09-23.md` (thread 01 do playbook Frescor). Cada evidência foi relida no repo em `1061dbf2e0f6`; onde a linha diz "medido", a medição é de outro, citada. Nada aqui passou pelo T7.

| Tela / camada | Protótipo (export) | Produção viva | Frescor | O design precisa saber |
|---|---|---|---|---|
| **Atendimento/CaixaUnificada** | `inbox-page.jsx` + `inbox-{extras,ai,cur,out}.jsx` | `Modules/Whatsapp/Resources/js/Pages/Atendimento/CaixaUnificada/` (Index.tsx + charter + casos) | ✅ **PARIDADE — 2026-09-23 @`1061dbf2e0f6`** (era 🔵 em 23/06; resta 1 item de fundação) | ⚠️ O caminho citado em 23/06 (`Pages/Atendimento/…` na raiz de `resources/js`) **não existe** — a tela vive no módulo Whatsapp. O protótipo cobre os `_components/` da tela viva (Filas, Canais, Broadcast, Nova conversa, Templates Jana+HSM, Reconnect, Guia, CheatSheet, MobileTabs, Contexto em drawer, `linkifyMessage`, comentários, transcript, apresentação, favoritos). Medição de outro: `governance/design/targets/medidas/Atendimento--CaixaUnificada--Index/resultado.json` (`medidoEm` 2026-09-18) = **IGUAL** em D2/D6/D8/D9 (D4 e SHELL saíram SEM-DADO — não são "igual"). Único item aberto: h1 14px (prod) × 22px (protótipo), já decidido por [W] = 22px → `prototipo-ui/cowork/Wagner/cowork-inbox/pageheader/PEDIDO-PAGEHEADER-DECISOES-W-2026-09-23.md` item 2. **Continua o OURO: não repintar.** |
| **Cliente (Crm)** | bundle `clientes-975.jsx` + docs US-078 | `Cliente/` Index/Show/Edit/Create/Ledger/Map · US-CRM-063..076 done | 🟠 **REVISADO 2026-08-26 — era 🔵** | ⚠️ O 🔵 de 23/06 media contra `prototipo-ui/cowork/Wagner/legado/clientes/`, hoje com **0 arquivos versionados**, e numa janela em que o espelho tinha METADE do arquivo vivo (58.331 vs 112.096 bytes, corpo do #5743). Segue verdade que a viva passou o protótipo no **drawer 760** (o `cliente-drawer760.jsx` foi derivado DA produção). **Não vale** para listagem/Import/Map: dono vigente = `memory/requisitos/Cliente/PARIDADE-area-cliente-diagnostico-e-ondas.md` (18/08), com itens a adotar e Ondas 3/5/6 abertas. Fidelidade visual: **NÃO MEDIDA** (Onda 6). **Drawer 760: ✅ PARIDADE — 2026-09-23 @`1061dbf2e0f6`.** A única lacuna achada (`_drawer/EnderecosEntregaList.tsx`, US-CRM-078 f2) já foi trazida ao protótipo no `CliEnderecoSection` (`clientes-page.jsx?v=end3`: editar, remover exceto o principal, CEP → autopreenche, UF por lista, entrega = nota fiscal). Listagem/Import/Map: sem mudança. |
| **PageHeader (fundação)** | `pageheader-canon-v3/` (4 HTMLs conflitantes) | `Components/PageHeader/` v3.8 (3 zonas, roxo 295) | ⚪ **DECIDIDO [W] 2026-09-23 @`1061dbf2e0f6`** — execução no pedido | 7 decisões (h1 600 · subtítulo 12px · aba 36px · setas + `role="banner"` no DS · Caixa 22px · ADR 0395 aprovada · matriz arquivada) → `prototipo-ui/cowork/Wagner/cowork-inbox/pageheader/PEDIDO-PAGEHEADER-DECISOES-W-2026-09-23.md`. Canon vivo segue roxo `oklch(0.55 0.15 295)` universal. **Descartar** `index.html` (hue-per-grupo) e `3-familias.html` (navy) continua valendo — regridem. Só `b-v2-roxo-kpis.html` reflete o vivo. |
| **Sidebar/Shell (fundação)** | `sidebar-v3-unificado/visual-source.html` | `AppShellV2` + Sidebar (8 grupos, glyph) | ⚪ **SEM PENDÊNCIA DE PUXAR — 2026-09-23 @`1061dbf2e0f6`** (era "empate de governança") | Os 3 itens de 23/06 eram catch-up **para a produção**, não para o protótipo. Tema: a trava "dark `cockpit.css` × light UI-0014" foi **resolvida pela UI-0023** (dark fixo nos dois modos). ⌘K: existe nos dois (`AppShellV2.tsx:398` atalho, `:734` palette). "Fixados": a produção não tem na sidebar — o `fixadas` de `AppShellV2.tsx:132` é da lista de conversas do chat. Vivo segue além em 8 grupos + glyph + sem topbar — **não regredir**. |
| **Compras grade-matrix** | `compras-grade-matrix/page.jsx` | `Purchase/_components/GradeMatrixInput.tsx`, usado por `Purchase/Create.tsx:26` (import) e `:459` (render) | ⏳ **IMPLEMENTADO, AGUARDA SMOKE [W]/[W2] — 2026-09-23 @`1061dbf2e0f6`** (era 🟠 órfão) | O caminho de 23/06 (`Compras/components/…`) não existe mais. O que falta é o canary biz=4 (Larissa/ROTA LIVRE), não código → `prototipo-ui/cowork/Wagner/cowork-inbox/compras/playbook/05-grade-smoke-bloqueada.md`. O anti-hook do charter `/compras` (não renderizar inline no Index) continua valendo. |

## Quadro (OficinaAuto · Vehicles — 2026-09-09)

> **Por que esta seção existe:** o [`COWORK-ESTRUTURA-E-TELAS.md`](COWORK-ESTRUTURA-E-TELAS.md)
> classificava a Oficina no bucket *"RESTO DO WORKSPACE … não foi feito frescor por-tela ainda —
> **não assuma**; rode o frescor antes de tratar como 'a desenvolver'"*. Esta é a rodada.
>
> **Caso especial: as 4 não têm protótipo nenhum**, e a ausência é **declarada pela própria fonte**
> (`oficina-forms.jsx:7` — *"FORA DE ESCOPO: Veículos CRUD"*), confirmada nos dois donos do
> inventário em 2026-09-09: repo (`ancora.mjs` devolve `n/a` legítimo nas 4) e projeto Cowork por ID
> (`list_files` → 876 paths, zero `veiculos-*`/`vehicles-*`; `--live-only` → **0 protótipos de tela**
> no vivo fora do espelho). Então o eixo aqui **não é** "produção à frente/atrás do protótipo" — é
> **produção vs. o DS canon**, e o veredito 🟠 significa *a fonte visual precisa nascer*.

| Tela / camada | Protótipo (export) | Produção viva | Frescor | O design precisa saber |
|---|---|---|---|---|
| **OficinaAuto/Vehicles/Index** | **não existe** (exclusão declarada na fonte) | `Vehicles/Index.tsx` — PT-01 com header+KPIs+pills+busca+paginação e 2 empty states corretos | 🟠 **SEM FONTE** | A tela **viola o próprio charter** no ponto que o cliente elogiou: `MercosulPlate` é canon com **8 arquivos consumidores** no repo (Sells, Board, Kanban, RichSheet) e as 4 telas de Vehicles têm **zero** — a placa sai como texto puro (`Index.tsx:398`), enquanto `Index.charter.md:34` a exige e `:56` proíbe o contrário. Detalhe: [`vehicles-index-gap.md`](../../../memory/requisitos/OficinaAuto/vehicles-index-gap.md). |
| **OficinaAuto/Vehicles/Create** | **não existe** (idem) | `Vehicles/Create.tsx` — 13 campos, `@/Components/ui`, consulta de placa (v2) funcionando | 🟠 **SEM FONTE** | **PT-02 = 3/10.** Sem `_form/` compartilhado, sem `FormSection`/`FormGrid`, sem `<Field>`, sem rail. E o charter promete *"1 col stack em 360px"* que **não acontece**: os 5 grids são `grid-cols-2/3` sem breakpoint. Detalhe: [`vehicles-create-gap.md`](../../../memory/requisitos/OficinaAuto/vehicles-create-gap.md). |
| **OficinaAuto/Vehicles/Edit** | **não existe** (idem) | `Vehicles/Edit.tsx` — paridade exata com Create (13 e 13 campos, medido) | 🟠 **SEM FONTE** | Mesmo placar do gêmeo, porque é **cópia**, não compartilhamento — e já divergiu uma vez (o charter registra a "restauração de paridade" manual). Gap próprio: placa editável mesmo com OS ativa, rejeição só no submit. Detalhe: [`vehicles-edit-gap.md`](../../../memory/requisitos/OficinaAuto/vehicles-edit-gap.md). |
| **OficinaAuto/Vehicles/Show** | **não existe** (idem) | `Vehicles/Show.tsx` — header+ações+ficha+histórico de OS, `status: draft` | 🟠 **SEM FONTE** | **PT-03 = 5/8**, e o R6 (FSM) é `n/a` por Non-Goal declarado — não é gap. Faltam KPIs, `Deferred` e `EmptyState`. Expõe `legacy_id` sem gate, campo que o charter do **Index** restringe a superadmin. Detalhe: [`vehicles-show-gap.md`](../../../memory/requisitos/OficinaAuto/vehicles-show-gap.md). |

> **Revisão 2026-09-23 @`1061dbf2e0f6`: 🟠 sem mudança nas 4.** `Vehicles/Index.tsx:398` ainda renderiza a placa como texto puro (`{v.vehicle_number ?? v.plate}`) e há **0** `MercosulPlate` nos `.tsx` de `Vehicles/` (o nome só aparece em `Index.charter.md`). Pedido aberto: `prototipo-ui/cowork/Wagner/cowork-inbox/oficina-auto/PEDIDO-VEHICLES-MERCOSULPLATE-2026-09-23.md`.

**Ordem sugerida (menor custo → maior):** `MercosulPlate` nas 4 telas (usa componente que já existe,
fecha a violação de charter e é o que o cliente já elogiou) → colapso responsivo dos grids (uma
linha por grid, fecha promessa do charter) → `_form/VehicleForm` compartilhado (uma correção fecha
Create e Edit) → `Deferred` no Index e no Show → decisões de KPI/rail/máscara.

## Quadro (Financeiro · DRE e Fluxo — 2026-09-23)

Medido com a mesma sonda nos dois lados e o DS resolvido por `servirEspelho` (ADR 0401). Detalhe e método em [`dre-visual-comparison.md`](../../requisitos/Financeiro/dre-visual-comparison.md) e [`fluxo-visual-comparison.md`](../../requisitos/Financeiro/fluxo-visual-comparison.md), seções *FIN-0a*.

| Tela / camada | Protótipo (export) | Produção viva | Frescor | O design precisa saber |
|---|---|---|---|---|
| **Financeiro/Dre/Index** | `financeiro-telas-extras.jsx` `TelaDRE` | `Dre/Index.tsx` | 🟠 **QUASE ✅ — 2026-09-23 @`1061dbf2e0f6`** (resta 1 ponto) | Rótulo "Novo título" e tokens do tema entraram com o FIN-1 (`Dre/Index.tsx:220/224`, `Index.charter.md:32`). **Resta:** 1ª coluna (Conta) em fonte mono — `font-mono` não aparece no `Dre/Index.tsx`; o único hit sob `Dre/` é `_components/BalanceteView.tsx:147`. A cor do primário (L 0,55 × 0,70) é fundação, não desta tela. |
| **Financeiro/Fluxo/Index** | `financeiro-telas-extras.jsx` `TelaFluxo` | `Fluxo/Index.tsx` | ✅ **PARIDADE DE FORMA (FIN-2) — 2026-09-23 @`1061dbf2e0f6`** (era 🟠) | Errata + resultado FIN-2 em `memory/requisitos/Financeiro/fluxo-visual-comparison.md` §"Errata da FIN-0a e resultado da FIN-2": faixa de 4 KPIs em 28px, título "Financeiro · Fluxo de caixa", primário "Novo título" (`Fluxo/Index.tsx:590`). O `div × table` de "Próximos eventos" era estado vazio (dado), não forma. |

## Como o design usa isto
1. Antes de exportar uma tela 🔵, **leia o estado vivo no `main`** (`resources/js/Pages/<Mod>/`) — o export local é fotocópia que envelhece (PORTÃO 1 do `STATUS.md`).
2. Telas 🟠 são as que valem export novo (produção precisa do design).
3. ⚪ fundação = PR sequencial isolado, nunca em paralelo com telas (incidente #2495).
4. **Um 🔵 ou 🟠 não substitui ler o `main` no turno.** Em 2026-09-23, 4 de 5 veredictos conferidos tinham mudado sem que o quadro registrasse. Todo veredicto leva **data e sha** da leitura; o leitor trata como **pista** (não como fato) qualquer linha com mais de **14 dias**.

---
_Semente: Fase 1 de `aplicar-prototipo` 2026-06-23. Detalhe por parte: `memory/requisitos/{Atendimento,Crm,Compras,_DesignSystem}/*-gap.md`._
