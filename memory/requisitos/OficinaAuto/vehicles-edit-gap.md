---
id: requisitos-oficinaauto-vehicles-edit-gap
tela: OficinaAuto/Vehicles/Edit (/oficina-auto/veiculos/{id}/edit)
prototipo: n/a — nenhum protótipo Cowork cobre esta tela (medido 2026-09-09; a exclusão é declarada pela própria fonte da Oficina — recibo no corpo)
map_json: n/a (sem protótipo — idem vehicles-index-gap.md: map por região exige o lado-protótipo, que não existe)
padrao_tela: PT-02 Form/Drawer
tela_viva: resources/js/Pages/OficinaAuto/Vehicles/Edit.tsx
gerado_em: 2026-09-09
---

# GAP-SPEC — OficinaAuto/Vehicles/Edit

> **Sem protótipo — a referência é o canon.** Natureza do documento e recibo da ausência no
> cabeçalho de [`vehicles-index-gap.md`](vehicles-index-gap.md). Lei aplicável:
> [PT-02 Form/Drawer](../_DesignSystem/padroes-tela/PT-02-Form-Drawer.md) + `Edit.charter.md` (v1).
>
> **Gêmeo de [`vehicles-create-gap.md`](vehicles-create-gap.md) — e a duplicação é o achado.**

## A paridade é exata, e é por isso que é problema

Medido nos dois arquivos: **13 campos em cada** (`grep -c "<Label htmlFor"` → 13 e 13), mesmo
`max-w-3xl mx-auto` (`Edit.tsx:98` × `Create.tsx:157`), mesmos cinco grids na mesma ordem —
`grid-cols-2` (`:114`, `:142`, `:220`), `grid-cols-3` (`:169`, `:245`).

Isso significa que **o placar PT-02 do Edit é o mesmo do Create** (3/10 — R5, R9, R10; ver a
tabela completa no gap do gêmeo, que não repito aqui). Inclusive o defeito responsivo:
**os cinco grids do Edit também não têm breakpoint**, então a promessa de *"1 col stack em
360px"* falha nas duas telas pela mesma causa.

A paridade hoje é mantida **por cópia**, não por compartilhamento — o charter do Create registra
que a restauração dessa paridade foi trabalho manual (*"Criado junto da restauracao de paridade
Edit<->Create (mesmos 13 campos, DS Select/Textarea, erros em todos os campos)"*). Ou seja: já
divergiu uma vez e precisou ser reconciliada à mão. `_form/VehicleForm.tsx` (R1) elimina a classe
inteira do problema, e é a mesma ação já listada no gap do Create — **uma correção fecha as duas telas**.

## O gap próprio do Edit — o usuário descobre a trava tarde demais

`Edit.charter.md` (Non-Goals) declara:

> *"NAO mudar `plate` apos veiculo ter OS aberta (FSM tracks por `vehicle_id` — server-side
> enforce, **Edit nao impede mas backend rejeita se houver OS ativa**)"*

O charter é honesto sobre o desenho atual, e o desenho atual custa uma volta ao usuário: o
atendente edita a placa, salva, e **só então** recebe a rejeição. Em `Edit.tsx:114-140` o campo
`plate` não tem `readOnly`, nem `disabled`, nem aviso — é idêntico ao do Create.

**Ação: decidir.** Duas formas, ambas dentro do PT-02:

1. **Preventiva** — quando o veículo tem OS ativa, `plate` vira `readOnly` com `<FieldError>`
   explicando (*"Placa travada: há OS em andamento"*) e link para a OS. Exige o controller enviar
   um booleano de OS ativa, que ele já sabe (a relação `serviceOrders` é carregada no `show`).
2. **Informativa** — mantém editável e mostra aviso antes do submit.

A (1) é a que respeita o princípio do PT-02 de erro guiado. Nenhuma das duas muda o enforcement
server-side, que continua sendo a fonte da verdade — a mudança é só **quando** o usuário fica sabendo.

## Quadro por parte

| Parte | Estado no vivo | Ação |
|---|---|---|
| **Colapso responsivo** | Cinco grids sem breakpoint (`:114`, `:142`, `:169`, `:220`, `:245`). | **Construir** — mesma correção do gêmeo (`grid-cols-1 sm:grid-cols-N`). Se `_form/VehicleForm` for feito primeiro, esta correção acontece **uma vez** para as duas telas. |
| **Corpo compartilhado** | 13 campos duplicados de `Create.tsx`. | **Construir** `_form/VehicleForm.tsx` (R1) — ação única compartilhada com o gap do Create. O Edit passa `comLookup={false}` (o charter declara que Edit não tem o botão Buscar). |
| **Placa com OS ativa** | Editável sem aviso; rejeição só no POST (`Edit.charter.md` Non-Goal). | **Decidir** entre preventiva e informativa — ver acima. Recomendação: preventiva. |
| **Hierarquia, `<Field>`, máscaras** | Idêntico ao Create. | Ver [`vehicles-create-gap.md`](vehicles-create-gap.md) — as ações são as mesmas e não se repetem aqui de propósito (um número/decisão, um dono). |
| **Título do cabeçalho** | `PageHeader` modo FOCO com `"Editar {plate}"` (charter G1). | **Decidir.** A placa no título é a identidade do registro — candidata natural a `<MercosulPlate size="sm">` inline, mesmo argumento do Index. Baixo custo, alto reconhecimento. Construir ou rejeitar por escrito. |
| **Anti-hooks de telemetria** | Charter proíbe logar `notes` em telemetria e mudar `business_id`. | Nada — são anti-hooks de backend/observabilidade, fora do eixo forma. Registrados aqui só para que a próxima leitura não os confunda com gap de UI. |

## O que este documento NÃO decide

- **Não** altera o enforcement server-side da placa travada — só quando o usuário é avisado.
- **Não** promove `n/a` a âncora.
- **Não** duplica as decisões do gêmeo: hierarquia, `<Field>`, rail e máscaras têm um dono só,
  o [`vehicles-create-gap.md`](vehicles-create-gap.md).
