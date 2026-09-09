# FRESCOR — Produção (`resources/js/Pages/**`) vs Protótipo (Cowork export)

> **Canal reverso Code → Design.** Onde a **produção passou** o protótipo, o design deve **puxar o estado vivo** (parar de tratar o export como fonte). Onde a **produção está atrás**, é catch-up real (build).
> Mantido pelo **Claude Code** ao fim de cada sync/Fase 1. Lido pelo **Cowork** no início da sessão (junto de `CODE_NOTES.md`).
> Regra-mãe: **camada de design** = Cowork manda; **memory/ADR/resources/** = repo manda, design só lê (ver ADR-proposta `2026-06-23-prototipo-ssot-unico-com-historico`).

**Legenda:** 🔵 produção À FRENTE (design puxa) · 🟠 produção ATRÁS (catch-up real) · ⚪ fundação/empate de governança · ✅ em paridade.

## Quadro (Fase 1 · 2026-06-23)

| Tela / camada | Protótipo (export) | Produção viva | Frescor | O design precisa saber |
|---|---|---|---|---|
| **Atendimento/CaixaUnificada** | `inbox-page.jsx` (15/mai, @88%) | `Atendimento/CaixaUnificada/` charter v19, Ondas 1–4 + PR-1..10 | 🔵 **À FRENTE** | A Caixa viva REALIZA tudo do protótipo + filas em DB, broadcast LGPD, IA real, SLA, Customer360. **É o OURO (LEI 18/jun): não repintar.** Único catch-up opcional: `linkifyMessage` (cross-refs `#os4821` clicáveis). |
| **Cliente (Crm)** | bundle `clientes-975.jsx` + docs US-078 | `Cliente/` Index/Show/Edit/Create/Ledger/Map · US-CRM-063..076 done | 🟠 **REVISADO 2026-08-26 — era 🔵** | ⚠️ O 🔵 de 23/06 media contra `prototipo-ui/prototipos/clientes/`, hoje com **0 arquivos versionados**, e numa janela em que o espelho tinha METADE do arquivo vivo (58.331 vs 112.096 bytes, corpo do #5743). Segue verdade que a viva passou o protótipo no **drawer 760** (o `cliente-drawer760.jsx` foi derivado DA produção). **Não vale** para listagem/Import/Map: dono vigente = `memory/requisitos/Cliente/PARIDADE-area-cliente-diagnostico-e-ondas.md` (18/08), com itens a adotar e Ondas 3/5/6 abertas. Fidelidade visual: **NÃO MEDIDA** (Onda 6). |
| **PageHeader (fundação)** | `pageheader-canon-v3/` (4 HTMLs conflitantes) | `Components/PageHeader/` v3.8 (3 zonas, roxo 295) | ⚪ **~85%, já é canon** | Canon vivo = roxo `oklch(0.55 0.15 295)` universal. **Descartar** `index.html` (hue-per-grupo) e `3-familias.html` (navy) — regridem. Só `b-v2-roxo-kpis.html` reflete o vivo. |
| **Sidebar/Shell (fundação)** | `sidebar-v3-unificado/visual-source.html` | `AppShellV2` + Sidebar (8 grupos, glyph) | ⚪ **empate de governança** | Vivo evoluiu além em 3 eixos (8 grupos, glyph, sem topbar) — **não regredir**. 3 itens de catch-up: tema light/dark (**trava: precisa [W] desempatar dark `cockpit.css` × light UI-0014**), Cmd+K visível, seção Fixados. |
| **Compras grade-matrix** | `compras-grade-matrix/page.jsx` | `Compras/components/GradeMatrixInput.tsx` (ÓRFÃO) | 🟠 **ATRÁS** | Único gap de produto real. Componente existe mas **não plugado** em tela; falta endpoint backend (matriz tam×cor). Valor alto p/ vestuário (Larissa/ROTA LIVRE). Gated: charter `/compras` lista inline como anti-hook; caller canônico = `Purchase/Create.tsx`. |

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
| **OficinaAuto/Vehicles/Index** | **não existe** (exclusão declarada na fonte) | `Vehicles/Index.tsx` — PT-01 com header+KPIs+pills+busca+paginação e 2 empty states corretos | 🟠 **SEM FONTE** | A tela **viola o próprio charter** no ponto que o cliente elogiou: `MercosulPlate` é canon com **8 arquivos consumidores** no repo (Sells, Board, Kanban, RichSheet) e as 4 telas de Vehicles têm **zero** — a placa sai como texto puro (`Index.tsx:398`), enquanto `Index.charter.md:34` a exige e `:56` proíbe o contrário. Detalhe: [`vehicles-index-gap.md`](../memory/requisitos/OficinaAuto/vehicles-index-gap.md). |
| **OficinaAuto/Vehicles/Create** | **não existe** (idem) | `Vehicles/Create.tsx` — 13 campos, `@/Components/ui`, consulta de placa (v2) funcionando | 🟠 **SEM FONTE** | **PT-02 = 3/10.** Sem `_form/` compartilhado, sem `FormSection`/`FormGrid`, sem `<Field>`, sem rail. E o charter promete *"1 col stack em 360px"* que **não acontece**: os 5 grids são `grid-cols-2/3` sem breakpoint. Detalhe: [`vehicles-create-gap.md`](../memory/requisitos/OficinaAuto/vehicles-create-gap.md). |
| **OficinaAuto/Vehicles/Edit** | **não existe** (idem) | `Vehicles/Edit.tsx` — paridade exata com Create (13 e 13 campos, medido) | 🟠 **SEM FONTE** | Mesmo placar do gêmeo, porque é **cópia**, não compartilhamento — e já divergiu uma vez (o charter registra a "restauração de paridade" manual). Gap próprio: placa editável mesmo com OS ativa, rejeição só no submit. Detalhe: [`vehicles-edit-gap.md`](../memory/requisitos/OficinaAuto/vehicles-edit-gap.md). |
| **OficinaAuto/Vehicles/Show** | **não existe** (idem) | `Vehicles/Show.tsx` — header+ações+ficha+histórico de OS, `status: draft` | 🟠 **SEM FONTE** | **PT-03 = 5/8**, e o R6 (FSM) é `n/a` por Non-Goal declarado — não é gap. Faltam KPIs, `Deferred` e `EmptyState`. Expõe `legacy_id` sem gate, campo que o charter do **Index** restringe a superadmin. Detalhe: [`vehicles-show-gap.md`](../memory/requisitos/OficinaAuto/vehicles-show-gap.md). |

**Ordem sugerida (menor custo → maior):** `MercosulPlate` nas 4 telas (usa componente que já existe,
fecha a violação de charter e é o que o cliente já elogiou) → colapso responsivo dos grids (uma
linha por grid, fecha promessa do charter) → `_form/VehicleForm` compartilhado (uma correção fecha
Create e Edit) → `Deferred` no Index e no Show → decisões de KPI/rail/máscara.

## Como o design usa isto
1. Antes de exportar uma tela 🔵, **leia o estado vivo no `main`** (`resources/js/Pages/<Mod>/`) — o export local é fotocópia que envelhece (PORTÃO 1 do `STATUS.md`).
2. Telas 🟠 são as que valem export novo (produção precisa do design).
3. ⚪ fundação = PR sequencial isolado, nunca em paralelo com telas (incidente #2495).

---
_Semente: Fase 1 de `aplicar-prototipo` 2026-06-23. Detalhe por parte: `memory/requisitos/{Atendimento,Crm,Compras,_DesignSystem}/*-gap.md`._
