---
id: requisitos-oficinaauto-vehicles-create-gap
tela: OficinaAuto/Vehicles/Create (/oficina-auto/veiculos/create)
prototipo: n/a — nenhum protótipo Cowork cobre esta tela (medido 2026-09-09; a exclusão é declarada pela própria fonte da Oficina — recibo no corpo)
map_json: n/a (sem protótipo — idem vehicles-index-gap.md: map por região exige o lado-protótipo, que não existe)
padrao_tela: PT-02 Form/Drawer
tela_viva: resources/js/Pages/OficinaAuto/Vehicles/Create.tsx
gerado_em: 2026-09-09
---

# GAP-SPEC — OficinaAuto/Vehicles/Create

> **Sem protótipo — a referência é o canon.** Ver o cabeçalho de
> [`vehicles-index-gap.md`](vehicles-index-gap.md) para a natureza deste documento e o recibo
> da ausência. Aqui a lei é [PT-02 Form/Drawer](../_DesignSystem/padroes-tela/PT-02-Form-Drawer.md)
> (10 regras binárias R1-R10, golden `Cliente/Create`) + `Create.charter.md` (v2, com a consulta
> de placa promovida em 2026-06-09).
>
> **Gêmeo:** [`vehicles-edit-gap.md`](vehicles-edit-gap.md). O próprio charter declara o
> parentesco (*"Gemeo de `Edit.charter.md` — mesmo conjunto de 13 campos, diferença = POST vs PUT"*),
> e é justamente esse parentesco que o código **não** materializa — ver R1 abaixo.

## Placar PT-02 medido — 3 de 10

| # | Regra | Veredito | Evidência |
|---|---|---|---|
| **R1** | Página fina + corpo em `_form/<Entidade>Form` reusado Create+Edit | ❌ | Não existe `_form/`. Os 13 campos estão inline em `Create.tsx:172-395` **e duplicados** em `Edit.tsx`. O charter promete o parentesco; o código o copia em vez de compartilhar. |
| **R2** | Layout 2-col `.cw-form-layout` (form 1fr + rail 300px sticky) | ❌ | `Create.tsx:157` é `max-w-3xl mx-auto`; zero `cw-form-layout`, zero `<aside className="cw-form-rail">`. |
| **R3** | Seções via `<FormSection title icon>` + `<FormGrid>` | ❌ | Os grupos são `<div className="grid grid-cols-2 gap-4">` soltos (`:173`, `:240`, `:318`) — sem título, sem ícone, sem `FormGrid`. O form é uma parede de 13 campos sem hierarquia. |
| **R4** | Todo campo é `<Field label error>` sobre controle `@/Components/ui` | ⚠️ parcial | Usa `@/Components/ui` corretamente (`Input`/`Label`/`Textarea`/`Select` — `:10-20`), zero controle nativo. Mas **não** usa `<Field>`: o par `<Label>`+`<Input>` é montado à mão em cada um dos 13 campos. |
| **R5** | Escolha binária via `<Segmented>` | ✅ n/a | Não há escolha binária. `vehicle_type` é `Select` compound do DS — correto para 11 valores. |
| **R6** | Erro inline via `<FieldError role="alert">` + `aria-invalid`/`aria-describedby` | ⚠️ parcial | `aria-invalid` está presente (`:190`) e o charter G3 promete foco/scroll no 1º campo inválido via `FIELD_ORDER`. Falta o `<FieldError>` do DS com `role="alert"` e o `aria-describedby` ligando controle↔mensagem. |
| **R7** | Máscara visual BR + dígitos limpos no submit | ⚠️ parcial | `plate` tem auto-uppercase (`:180`), que é a transformação certa. **Chassi (17), RENAVAM (11) e placa não têm máscara de formato** — e são os três campos que o atendente digita olhando o documento. |
| **R8** | Lookup assistido via `<InputGroup>`+`<InputGroupButton loading done>` | ❌ | A consulta de placa (G5 do charter) existe e funciona, mas é montada com `<Inline>` + `<Button variant="secondary">` + `<Loader2>` (`:176-205`) em vez do par canon do DS. O comportamento está certo; a forma não é a do golden. |
| **R9** | Footer único à direita: Cancelar + Salvar com spinner | ✅ | `:398-402` — `Cancelar` outline + `Salvar veículo` com `disabled={processing}`. Variantes são `outline`/default em vez de `cowork-ghost`/`cowork-primary`, diferença de nome de variante, não de estrutura. |
| **R10** | Cor só por token, zero hex/`bg-blue-N` cru | ✅ | Sem cor crua no arquivo. |

**Placar 3/10** (R5, R9, R10) + 3 parciais. Pela régua do PT-02 (*"<8 = volta pro Claude Design"*),
esta tela está na faixa que pede redesenho do corpo, não ajuste pontual.

## O achado que contradiz o próprio charter — o form não colapsa no celular

`Create.charter.md` (UX Targets) promete:

> *"**Mobile 360px:** form usavel em 1 col stack (max-w-3xl mx-auto + grid colapsa via Tailwind responsive)"*

**Medido: não colapsa.** Os cinco grids do form são `grid-cols-2` (`:173`, `:240`, `:318`) e
`grid-cols-3` (`:267`, `:343`) — **sem nenhum prefixo de breakpoint**. Em Tailwind, `grid-cols-2`
sem `sm:`/`md:` vale em *toda* largura, inclusive 360px. O `max-w-3xl mx-auto` limita o teto,
não cria o stack.

O charter também registra por que isso importa: *"Placa auto-uppercase: transformacao onChange
previne typos **low-end Android** Martinho clientes"* — ou seja, o dispositivo-alvo declarado é
justamente o que a promessa cobre e o código não entrega. Em 360px, três colunas de campo
(`:267` — ano fabricação · ano modelo · RENAVAM) ficam com ~100px cada.

**Ação: construir.** É `grid-cols-1 sm:grid-cols-2` / `grid-cols-1 sm:grid-cols-3`. Correção de
uma linha por grid, e fecha uma promessa que o charter já fez.

## Quadro por parte

| Parte | Estado no vivo | Ação |
|---|---|---|
| **Colapso responsivo** | Cinco grids sem breakpoint (`:173`, `:240`, `:267`, `:318`, `:343`). | **Construir.** `grid-cols-1 sm:grid-cols-N`. Fecha a promessa de UX Target do charter; sem isso a linha do charter é afirmação falsa. |
| **Hierarquia do form** | 13 campos em fileira, sem agrupamento visual. | **Construir** com `<FormSection title icon>` (R3). Agrupamento que o domínio já sugere: **Identificação** (placa + placa secundária + chassi + chassi secundário + RENAVAM) · **Classificação** (tipo + ano fab. + ano modelo) · **Ficha técnica** (motor + combustível + cor + KM entrada) · **Observações** (notes). Ícones lucide coerentes com o resto do módulo (`Truck`, `Wrench`). |
| **Corpo compartilhado Create/Edit** | Duplicado nos dois arquivos. | **Construir** `_form/VehicleForm.tsx` (R1), no molde de `Cliente/_form/ClienteForm.tsx`. **Ganho concreto e verificável:** o charter do Create declara o botão Buscar como diferença deliberada vs Edit (*"Edit nao tem o botao Buscar"*) — com corpo compartilhado essa diferença vira uma prop (`comLookup`), explícita, em vez de divergência silenciosa entre duas cópias. |
| **Lookup de placa** | Funciona (G5): `POST /oficina-auto/veiculos/consulta-placa`, throttle, feedback `role=status`, driver `stub` default. Forma montada à mão (`:176-205`). | **Decidir.** Migrar para `<InputGroup>`+`<InputGroupButton loading done>` (R8) é ganho de consistência, não de função. O comportamento — incluindo os Non-Goals de PII do proprietário — **não muda**. Construir ou rejeitar por escrito. |
| **Máscara de chassi e RENAVAM** | Sem máscara; só `maxLength` (`:242` chassi 30, `:320` renavam 11). | **Decidir.** Chassi/VIN tem 17 caracteres e não usa as letras I, O e Q (evita confusão com 1 e 0) — uma validação de forma barata que evita retrabalho no cadastro. RENAVAM tem 11 dígitos com dígito verificador. Nenhum dos dois está em `format-br.ts` hoje; adicionar é criar helper novo no DS, não configurar tela. Construir ou rejeitar por escrito. |
| **`<Field>` + `<FieldError>`** | `<Label>`+`<Input>` à mão, com `aria-invalid` (`:190`). | **Construir** junto com R3 — é a mesma refatoração do corpo. Ganha `role="alert"` e `aria-describedby` de graça (R6). |
| **Rail de contexto (R2)** | Não existe. | **Decidir.** No golden, o rail mostra preview vivo + prontidão. Aqui o candidato natural é o **preview da placa** (`<MercosulPlate>` renderizando o que está sendo digitado) + checklist do que falta para abrir OS. Alto valor para conferência contra o documento do veículo — e usa componente que já existe. Construir ou rejeitar por escrito. |
| **Vocabulário do `vehicle_type`** | Select com opções vindas da prop `vehicleTypes` (`:269-285`). | Nada na tela — mas **atenção Tier 0** ao mexer: os valores `cacamba_*` e `recapagem` do enum são resíduo vestigial (`memory/dominio/oficina-auto.md`) e `forbidden_ui_terms` inclui `cacamba`. Se a lista chegar do backend com esses rótulos, o `dominio:check` acusa. A tela só renderiza o que recebe — a curadoria é do controller. |

## O que este documento NÃO decide

- **Não** promove `n/a` a âncora — a tela segue sem protótipo, e isso é declaração legítima.
- **Não** muda nada do escopo da consulta de placa: o Non-Goal *"NAO consultar nem armazenar
  dados do PROPRIETARIO"* (decisão [W] 2026-06-09) é intocado por tudo acima.
- **Não** cria helper de máscara no DS por conta própria — está listado como decisão.
