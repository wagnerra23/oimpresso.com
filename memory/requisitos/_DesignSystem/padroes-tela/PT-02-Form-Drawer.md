---
pattern_id: PT-02
nome: Form/Drawer
camada: 3-padroes-tela
status: draft
versao: 0.1
created: 2026-05-30
parent_adr: UI-0013
related_adrs: [179, 185, 110, 149, 235]
golden_screen: resources/js/Pages/Cliente/Create.tsx
applied_in:
  - Pages/Cliente/Create.tsx
  - Pages/Cliente/Edit.tsx (via _form/ClienteForm compartilhado)
  - Pages/Cliente/Index.tsx (ClienteSheet drawer 760)
---

# PT-02 · Form/Drawer — padrão canônico de tela-cadastro

> **Camada 3 · Padrão de Tela.** Herda das [Fundações](../README.md) + [Shell](../README.md) + segue o golden de form do [GOLDEN-REFERENCE.md](../../../../prototipo-ui/GOLDEN-REFERENCE.md). Módulo configura os campos/seções, **não** muda a estrutura.

## Quando aplicar

Sempre que o módulo precisa **criar/editar uma entidade cadastral** (cliente, fornecedor, produto, conta, categoria, etc) — seja em página full (`Create.tsx`/`Edit.tsx`) ou em **drawer lateral** disparado da Lista (PT-01 Slot 6). Não aplicar pra: Lista (PT-01), Detalhe read-only (PT-03), Dashboard (PT-04), Config/Kanban (PT-05).

## A tela-ouro: `Cliente/Create` + `_form/ClienteForm`

[`resources/js/Pages/Cliente/Create.tsx`](../../../../resources/js/Pages/Cliente/Create.tsx) (página fina, 121 linhas) + corpo compartilhado [`_form/ClienteForm.tsx`](../../../../resources/js/Pages/Cliente/_form/ClienteForm.tsx) + charter [`Create.charter.md`](../../../../resources/js/Pages/Cliente/Create.charter.md).

**Por que esta é o golden do arquétipo:**
- **Score 89** no piloto SCREEN-GRADE — a tela DS-migrada de referência (Onda F).
- **SoC exemplar:** a página só monta `useForm` + submit + chrome (`Create.tsx:28`, `:79-82`, `:84-118`); o corpo (seções, grid, ações, rail) vive em `_form/ClienteForm` reusado **Create + Edit** (~90%, `ClienteForm.tsx:26-32`).
- Consome só `@/Components/ui` certo: `Input`/`Segmented`/`Select`/`Button`/`FormSection`/`FormGrid` (`ClienteForm.tsx:4-14`) + `Field`/`FieldError`/`InputGroup` (`Field.tsx:3-4`, `DadosFiscaisBRSection.tsx:26-27`).
- Drawer canon 760px ([ADR 0179](../../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)/[0185](../../../decisions/0185-drawer-760-canon-entidades-cadastrais.md)) em `Index.tsx:1838`.

## 10 regras binárias (sim/não) — ancoradas em linha real

| # | Regra (pergunta sim/não) | Evidência na golden |
|---|---|---|
| **R1** | **Página é fina?** Só `useForm` + `transform` + `handleSubmit` + chrome — o corpo do form mora em `_form/<Entidade>Form` reusado por Create+Edit. | `Create.tsx:28` (useForm) · `:79-82` (submit) · `:103-115` (delega `<ClienteForm>`) |
| **R2** | **Corpo é `<form>` com layout 2-col `.cw-form-layout` (form 1fr + rail 300px sticky)?** Colapsa pra 1 col abaixo de 900px. | `ClienteForm.tsx:65` (`className="cw-form-layout"`) · `:266` (`<aside className="cw-form-rail">`) · CSS `cowork-fields.css:470-480` |
| **R3** | **Seções usam `<FormSection title icon>` + `<FormGrid>` (NÃO `<section>`/`<div>` hand-rolled)?** Ícone lucide. | `ClienteForm.tsx:68` (`<FormSection title="Identificação" icon={<User2 />}>`) · `:69` (`<FormGrid>`) · `DadosFiscaisBRSection.tsx:138` |
| **R4** | **Todo campo é `<Field label error>` envolvendo controle `@/Components/ui` (`Input variant="cowork"`/`Select`/`Segmented`/`Checkbox`) — zero `<input>`/`<select>` nativo?** | `Field.tsx:15-53` · `ClienteForm.tsx:104` (`<Input variant="cowork">`) · `:71` (`<Select>`) · `:86` (`<Segmented>`) |
| **R5** | **Escolha binária PF/PJ usa `<Segmented accent>` (NÃO radio nativo)?** | `ClienteForm.tsx:86-95` (`<Segmented accent ... options={[Física, Jurídica]}>`) · charter Goals "Segmented PF/PJ substitui o radio nativo" |
| **R6** | **Erro aparece inline no campo via `<FieldError role="alert">`, com `aria-invalid`+`aria-describedby` injetados no controle?** Sem alerta global solto. | `Field.tsx:30-50` (clona child com a11y + `<FieldError id={errorId}>`) · `field-state.tsx:12-20` (`role="alert"`) |
| **R7** | **Campos BR têm máscara visual + dígitos limpos no submit?** CPF/CNPJ formata na digitação (`formatCpfCnpj`) e desmascarado (`unmaskDigits`) antes do POST. | `DadosFiscaisBRSection.tsx:132` (`formatCpfCnpj` no onChange) · `Create.tsx:77` (`transform(... cpf_cnpj: unmaskDigits ...)`) |
| **R8** | **Lookup assistido (CNPJ→BrasilAPI) usa `<InputGroup>`+`<InputGroupButton loading done>` e devolve `FieldError`/`FieldSuccess` — preenchimento, nunca Receita inline?** | `DadosFiscaisBRSection.tsx:144-169` (`InputGroup`+`InputGroupButton`) · `:182-184` (`FieldError`/`FieldSuccess`) · charter Non-Goal "validação CNPJ via Receita NÃO inline" |
| **R9** | **Ações ficam num footer único alinhado à direita: `Cancelar` (`variant="cowork-ghost"`) + `Salvar` (`variant="cowork-primary"` com spinner `disabled={processing}`)?** Sem botão duplicado. | `ClienteForm.tsx:254-262` (footer `justify-end gap-2`) · `:258` (primary + `disabled={processing}` + "Salvando…") |
| **R10** | **Cor vem dos tokens DS v4 (roxo `primary`) — rail usa `bg-primary/10 text-primary`, prontidão usa `cw-ok-*`, obrigatório usa `cw-req` — zero hex/`bg-blue-N` cru?** | `ClienteRail.tsx:53` (`bg-primary/10 text-primary`) · `:101` (`cw-ok-text`) · `:110` (`cw-ok-badge`) · `Create.tsx:97` (`<span className="cw-req">*`) · [ADR 0235](../../../decisions/0235-ds-v4-roxo-primary.md) |

**Placar:** 10/10 = canon. 8-9 = 1 round de ajuste. <8 = volta pro Claude Design.

## Regras de ouro

### ✅ Sempre

- **PT-BR** em todo label, placeholder, mensagem (`ClienteForm.tsx:99` "Nome completo", `:153` "(00) 00000-0000")
- Header de página: `h1 text-2xl font-semibold` (NÃO `font-bold`) + breadcrumb "Voltar" (`Create.tsx:88-95`)
- Campos PJ-only escondidos quando PF (`DadosFiscaisBRSection.tsx:199` `isJuridica &&`); seção Financeiro só quando cliente/ambos (`ClienteForm.tsx:62`, `:210`)
- Rail de contexto **client-side** (preview vivo + prontidão fiscal computada do form, sem backend) — `ClienteRail.tsx:6-13`
- Cabe em **1280px** sem scroll horizontal (charter UX Target; drawer 760 + sidebar 240 ≈ 1024 — `Index.tsx:1834-1836`)
- Drawer cadastral = **760px** (`Index.tsx:1838` `w-[760px] sm:max-w-[760px]`), fullscreen abaixo de 1100px

### ❌ Nunca

- `<input>`/`<select>`/`<input type="radio|checkbox">` nativo — use `@/Components/ui` (R4/R5)
- Radio PF/PJ em vez de `<Segmented>` (anti-padrão Onda F)
- Alerta de erro global solto em vez de `<FieldError>` inline por campo (R6)
- Enviar CPF/CNPJ mascarado pro backend — sempre `unmaskDigits` no `transform` (R7)
- Validar CNPJ na Receita inline ao salvar (charter Non-Goal — só lookup BrasilAPI assistido)
- Botão de ação duplicado / fora do footer (R9)
- Cor crua (`#hex`, `bg-blue-500`) — token `primary` roxo + `cw-*` (R10, [ADR 0235](../../../decisions/0235-ds-v4-roxo-primary.md))
- Modal full-screen — cadastro disparado da Lista usa **drawer/Sheet 760**, nunca modal sobre modal

## Estados obrigatórios

1. **Vazio/novo** — form limpo, defaults do `useForm` (`Create.tsx:28-57`)
2. **Pre-fill** — via query `?prefill_name=` vindo de outra tela (`Create.tsx:32`, charter Goals)
3. **Erro server-side** — `useForm.errors` mapeado pra `<FieldError>` por campo (`ClienteForm.tsx:61`)
4. **Lookup carregando/ok/erro** — `InputGroupButton loading/done` + `FieldError`/`FieldSuccess` (`DadosFiscaisBRSection.tsx:155-184`)
5. **Submetendo** — `disabled={processing}` + label "Salvando…" (`ClienteForm.tsx:258-260`)
6. **Prontidão** — rail mostra `N de M` checklist + badge "Pronto pra emitir NF-e" quando completo (`ClienteRail.tsx:42-43`, `:109-113`)

## Drift conhecido do golden (honesto — corrija ao copiar)

A `Cliente/Create` é ouro em **SoC/DS/máscaras BR/a11y**, mas tem débitos catalogados — **não copie cegamente:**

- ✅ **Charter `Create.charter.md` já é `status: live`** (verificado 2026-07-11) — o blocker original ("Create elevado pendente") **foi resolvido**. O único gate restante pra PT-02 virar `live` é a **aprovação de screenshot do golden pelo Wagner** (F1.5 · [ADR 0107](../../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)).
- ⚠️ **Máscara só em CPF/CNPJ** — `mobile`/`landline`/`zip_code` seguem sem `formatPhone`/`formatCep` no onChange (`ClienteForm.tsx:153,162,203`), apesar de `format-br.ts` exportar ambos. Advisory: aplicar ao replicar.
- ⚠️ **Submit hardcoded `post('/contacts')`** (`Create.tsx:82`) + **`tax_number` legado UPOS** (`ClienteForm.tsx:129`) — advisories "não copie cego": parametrize a action e use só `cpf_cnpj` em módulos novos.
- ⚠️ **Submit hardcoded `post('/contacts')`** (`Create.tsx:81`) — rota literal na página; ao reusar o pattern, parametrize a action por prop (o corpo `ClienteForm` já recebe `onSubmit`, mas a página fixa a URL).
- ⚠️ **`tax_number` legado UPOS** coexiste com `cpf_cnpj` BR (`ClienteForm.tsx:124-131`) — campo morto-vivo do UltimatePOS; novos módulos cadastrais **não** devem replicar esse par, só `cpf_cnpj`.
- ⚠️ **Máscara só em CPF/CNPJ** — celular/CEP têm `placeholder` mas sem `formatPhone`/`formatCep` no onChange (`ClienteForm.tsx:153`, `:200`), apesar de `format-br.ts` exportar ambos (`format-br.ts:51`, `:65`). Aplicar ao replicar.
- ⚠️ **Copiloto IA inerte** — slot do rail é stub sem endpoint (`ClienteRail.tsx:116-125`, PR-A2 pendente). É placeholder honesto, não feature.

## Aplicado em (estado real)

| Tela | R1 fina | R2 layout+rail | R3 FormSection | R4 @/ui | R5 Segmented | R6 FieldError | R7 máscara BR | R8 lookup | R9 footer | R10 token | Charter | Score |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `Cliente/Create.tsx` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | draft v2 | **89** |
| `Cliente/Edit.tsx` | ✓ | ✓ (via `_form`) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — | herda |
| `Cliente/Index.tsx` (ClienteSheet) | — | drawer 760 | parcial | ✓ | parcial | ✓ | ✓ | — | ✓ | ✓ | ✓ v3 | — |

**Métrica adoção PT-02 (2026-05-30):** 1 família cadastral (Cliente · Create/Edit/Sheet) é golden completo. Próximos candidatos: Produto, Fornecedor, contas Financeiro — todos devem nascer reusando o pattern `_form/<Entidade>Form` + drawer 760.

## Referências

- **ADR-mãe:** [UI-0013 Constituição UI v2](../adr/ui/0013-constituicao-ui-v2-camadas.md)
- **Golden de form (código):** [GOLDEN-REFERENCE.md](../../../../prototipo-ui/GOLDEN-REFERENCE.md) (`Sells/Create` — 10 regras R1-R10 visuais; PT-02 estende pra cadastro BR)
- **Drawer canon:** [ADR 0179](../../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md) + [ADR 0185](../../../decisions/0185-drawer-760-canon-entidades-cadastrais.md)
- **Tokens v4 roxo:** [ADR 0235](../../../decisions/0235-ds-v4-roxo-primary.md)
- **Pattern reuse / pares:** [PT-01 Lista](PT-01-Lista.md) (Slot 6 dispara este drawer) · [ADR 0149](../../../decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- **Índice de design:** [INDEX-DESIGN-MEMORIAS.md](../INDEX-DESIGN-MEMORIAS.md) §2b

## Versão

**v0.1** · 2026-05-30 · primeira formalização (draft). Documenta o pattern já vivo na família Cliente (Create/Edit/Sheet).

**v0.2** · 2026-07-11 · re-âncora no `origin/main` atual. Charter `Create` já `live`; **19 telas** declaram PT-02 (`pt-conformance` verde). Blocker de charter resolvido — só falta o gate de screenshot.
**Bump v1.0 (→ live)** quando Wagner aprovar o screenshot do golden `Cliente/Create` (F1.5). Adoção por 2º módulo já satisfeita (19 declarações).
