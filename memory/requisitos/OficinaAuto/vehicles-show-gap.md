---
id: requisitos-oficinaauto-vehicles-show-gap
tela: OficinaAuto/Vehicles/Show (/oficina-auto/veiculos/{id})
prototipo: n/a — nenhum protótipo Cowork cobre esta tela (medido 2026-09-09; a exclusão é declarada pela própria fonte da Oficina — recibo no corpo)
map_json: n/a (sem protótipo — idem vehicles-index-gap.md: map por região exige o lado-protótipo, que não existe)
padrao_tela: PT-03 Detalhe
tela_viva: resources/js/Pages/OficinaAuto/Vehicles/Show.tsx
gerado_em: 2026-09-09
---

# GAP-SPEC — OficinaAuto/Vehicles/Show

> **Sem protótipo — a referência é o canon.** Natureza do documento e recibo da ausência no
> cabeçalho de [`vehicles-index-gap.md`](vehicles-index-gap.md). Lei aplicável:
> [PT-03 Detalhe](../_DesignSystem/padroes-tela/PT-03-Detalhe.md) (8 regras binárias, golden
> `Sells/Show`) + `Show.charter.md`.
>
> ⚠️ **Esta é a única das 4 com `status: draft`.** O próprio charter diz: *"Wagner aprova
> Non-Goals + Anti-hooks antes de `live`"*. Então aqui há uma decisão [W] **já pendente antes**
> deste documento, e nada abaixo a substitui.

## Placar PT-03 medido — 5 de 8, e 1 é n/a por decisão de charter

| # | Regra | Veredito | Evidência |
|---|---|---|---|
| **R1** | Header com Voltar ghost + identidade + subtítulo | ✅ | `Show.tsx:49-52` — `PageHeader` com `title={vehicle.plate}`, descrição tratando a placa secundária (*"+ XXX (reboque)"*), `icon="car"`. |
| **R2** | Ações à direita, primária `default`, secundárias ghost | ✅ | `:57-75` — `Voltar` ghost · `Editar` ghost · `Nova OS` primária. Três ações, não precisa de dropdown. |
| **R3** | KPIs em grid com valor grande `tabular-nums` via `<KpiCard>` | ❌ | Não existe slot de KPI. O PT-03 já registrava isso na tabela "Aplicado em": *"`OficinaAuto/.../Show.tsx` — V0 scaffold, **sem KPIs grandes**"*. |
| **R4** | Layout 2-col `lg:grid-cols-12` (8/4) | ⚠️ | `:80` é `grid-cols-1 md:grid-cols-2` — 6/6 em vez de 8/4. Colapsa corretamente no mobile, mas não é a proporção do padrão (conteúdo dominante à esquerda). |
| **R5** | Props caras via `Inertia::defer` + `<Deferred fallback>` | ❌ | `service_orders` chega eager na prop `vehicle` (`:40-42`); zero `<Deferred>`. Num veículo com histórico longo, é a query que cresce. |
| **R6** | Zona de ações contextuais por estado (FSM) | ✅ **n/a** | **Não é gap.** O charter declara Non-Goal explícito: *"Transição de FSM do veículo aqui (mudança de `current_status` é efeito de OS, não ação manual nesta tela)"*. A ausência é decisão registrada, não omissão. |
| **R7** | Seção Histórico/Timeline cronológica | ⚠️ | `:105-129` tem o histórico de OS como `<ul>` com link + `ServiceOrderStatusBadge` + data. É lista, não timeline — suficiente para o escopo, aquém do golden. |
| **R8** | Seção = card `rounded-lg border bg-card` + estado vazio via `<EmptyState>` | ⚠️ | Cards corretos (`:81`, `:104` — `rounded-md border bg-card p-4`). Mas o vazio é `<p className="text-sm text-muted-foreground">Nenhuma OS registrada...</p>` (`:108`), texto solto — o PT-03 pede `<EmptyState>` shared. |

**Placar 5/8** contando o R6 como cumprido-por-decisão. Pela régua do PT-03 (*"6-7 = 1 round de
ajuste"*), está a um round — desde que o R3 e o R5 sejam decididos.

## O achado de exposição — `legacy_id` sem gate de permissão

`Show.tsx:99` renderiza `{vehicle.legacy_id && <Row label="Legacy ID" value={vehicle.legacy_id} mono />}`
— **sem checar permissão**.

O charter do **Index** trata isso como regra em dois lugares:
- Goal: *"Coluna `legacy_id` (**visível apenas pra superadmin**) — rastreabilidade pós-importer Firebird"*
- UX Anti-pattern: *"Mostrar `legacy_id` pra usuário comum (apenas superadmin debug)"*

⚠️ **Sendo preciso sobre o alcance:** essa regra está escrita no charter do **Index**, não no do
Show. O `Show.charter.md` lista `legacy_id` no contrato de props sem restrição. Então **não afirmo
que o Show viola o próprio charter** — o que existe é uma regra declarada numa tela irmã, sobre o
mesmo campo, que aqui não aparece.

**Ação: decidir**, e a decisão é de uma linha: a restrição vale para o campo (e então o Show
também gateia, e o `Show.charter.md` ganha o anti-pattern), ou vale só para a coluna da lista.
Como o Show é `status: draft` e o charter dele pede aprovação [W] dos anti-hooks, este é
exatamente o tipo de item que a aprovação pendente deveria resolver.

## Quadro por parte

| Parte | Estado no vivo | Ação |
|---|---|---|
| **Placa no cabeçalho** | `:50` — `title={vehicle.plate}`, texto puro. | **Decidir.** Mesma família do achado do Index: a placa é a identidade do registro nesta tela, e `MercosulPlate` é canon com 8 consumidores no repo. Aqui `size="md"` (precedente: `ServiceOrderRichSheet.tsx:366` e `Sells/Show.tsx:458` usam `md` em contexto de detalhe). Diferença vs Index: lá o charter **manda** (Goal + Anti-pattern explícitos), aqui não há regra escrita — por isso é decidir, não construir. |
| **KPIs (slot 2)** | Ausente. | **Decidir.** O charter não pede, o PT-03 pede. Candidatos que a tela já tem dado para: **OS abertas** · **Total de OS** · **KM de entrada** · **Última entrada**. Os dois primeiros saem de `service_orders`; nenhum exige query nova. Construir ou rejeitar por escrito — e se rejeitar, vale registrar como Non-Goal no charter para o PT-03 parar de contar como gap. |
| **Histórico eager** | `service_orders` na prop `vehicle`, sem `Deferred` (`:40-42`, `:105`). | **Construir.** É o mesmo débito do Index (`Inertia::defer` no count de OS) e a mesma causa: o módulo ainda não adotou o defer. O `RUNBOOK-inertia-defer-pattern` é o dono do padrão. Veículo do Martinho com anos de manutenção é exatamente o caso que degrada. |
| **Estado vazio do histórico** | `<p>` solto (`:108`). | **Construir.** `<EmptyState>` shared, com CTA "Abrir a primeira OS" apontando para a rota que o header já usa (`/oficina-auto/ordens-servico/create?vehicle_id=`). Fecha R8 e transforma um beco sem saída em caminho. |
| **Proporção do grid** | `md:grid-cols-2` (6/6) em `:80`. | **Decidir.** `lg:grid-cols-12` com 8/4 (R4) dá mais respiro à ficha técnica, que tem 12 linhas de `<dl>`. Baixo risco, ganho moderado. |
| **`legacy_id`** | Exibido sem gate (`:99`). | **Decidir** — ver acima. É o item mais barato de resolver e o único com componente de exposição. |
| **Ficha técnica** | `<dl>` com 12 linhas + `mono` nos identificadores (`:84-99`), observações em bloco próprio (`:101-106`). | Nada — está correto e usa `mono` exatamente onde o PT manda (placa, chassi, RENAVAM, legacy). |
| **CTA "Nova OS"** | `:69-74`, primária, pré-preenchendo `vehicle_id`. | Nada — cumpre o Goal do charter e é a ação certa como primária nesta tela. |

## O que este documento NÃO decide

- **Não** promove a tela de `draft` para `live` — isso depende da aprovação [W] dos Non-Goals e
  Anti-hooks que o próprio charter já declara pendente.
- **Não** transporta a regra de `legacy_id` do charter do Index para cá por conta própria —
  está registrada como decisão, com o alcance explicitado.
- **Não** trata a ausência de FSM nesta tela como gap: é Non-Goal declarado (R6).
