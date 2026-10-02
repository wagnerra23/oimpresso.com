---
page: /discount
component: resources/js/Pages/Discount/Index.tsx
owner: wagner
status: draft
last_validated: "2026-10-02"
parent_module: Sells
related_prototype: prototipo-ui/cowork/Wagner/venda-blade.jsx
related_runbook: memory/requisitos/Sells/RUNBOOK-discount.md
tier: B
related_us: [US-SELL-065]
charter_version: 1
---

# Page Charter — Descontos (`/discount`)

> Texto revisado em `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/Descontos.charter.md`
> (frescor medido em 2026-08-22: a tela ainda era Blade), ajustado à **decisão D1 de [W]
> (2026-10-02): ver × editar em permissões separadas**. Thread 04 do playbook `venda-menu`.
> **Fonte legado:** `discount/index + create + edit` · **Permissões:** `discount.view` (ver a
> lista) · `discount.manage` (criar, editar, desativar, reativar, excluir).
> **Protótipo:** `TelaDescontos` em `venda-blade.jsx` · alvo medido
> `governance/design/targets/vendas--descontos--index.secoes.json` (header · tabs · aviso · lista).

## Mission

Regras de desconto que o PDV aplica sozinho enquanto valem.

## Regras

- R1 Prioridade menor ganha da maior quando duas regras batem no mesmo produto (regra do PDV,
  não desta tela — aqui só se cadastra).
- R2 Escolher produtos **apaga** marca e categoria — o servidor guarda um ou outro, nunca os dois.
- R3 Massa só **desativa**; não existe exclusão em lote.
- R4 Desconto inativo some do PDV e ganha a ação "Reativar".
- R5 Tipo é fixo ou percentual.
- R6 Quem só tem `discount.view` vê a lista inteira; criar, editar, desativar, reativar e excluir
  aparecem **desabilitados com o motivo**, não somem.

## Goals — Features (faz)

- Lista de descontos com nome, período, valor, prioridade, marca, categoria, produtos, local e situação.
- Busca local por nome.
- Criar e editar pelo drawer lateral (PT-02); desativar selecionados; reativar; excluir com confirmação.
- Aviso no topo dizendo quem pode ver e quem pode gravar.
- PT-BR em todo label/placeholder/mensagem.

## Non-Goals — Features (NÃO faz)

- ❌ Rota nova: lista, gravação, exclusão e (des)ativação usam os endpoints que já existem.
- ❌ Mudar como o desconto é calculado ou aplicado na venda.
- ❌ Validar no servidor o que hoje passa (achado A2 — decisão [W]).

## Achados (estado em 2026-10-02)

- A1 ✅ resolvido — a Blade checava `brand.view`/`brand.create` nos botões; passou a checar
  `discount.view`/`discount.manage`, como o controller.
- A2 🟡 aberto — `store()`/`update()` não validam: prioridade não numérica e datas ausentes
  passam. Medido: nome vazio **não** grava (o banco recusa `NULL` e o servidor responde
  `success:false`); a tela recusa nome vazio antes de enviar.
- A3 ✅ resolvido por D1 — `discount.view` × `discount.manage`.

## UX Targets

- Cabe em 1280px sem scroll horizontal da página (a tabela rola dentro do card).

## Refs

- Casos: `Index.casos.md` ao lado.
- Padrão de Tela: PT-01 Lista · drawer de cadastro PT-02.
- Constituição UI v2: UI-0013.
