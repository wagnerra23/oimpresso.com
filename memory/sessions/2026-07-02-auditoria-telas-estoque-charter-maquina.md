---
date: "2026-07-02"
topic: "Auditoria das telas do domínio Estoque — inventário (movem/seguram/definem saldo) + cobertura de charter por máquina (15/17 Inertia) + veredito: efeito-no-saldo não é verificado hoje; mecanismo certo = teste que quebra, não gate de declaração"
authors: [C]
type: auditoria
metodo: "verificação read-only contra origin/main (git ls-tree/cat-file) — inventário de telas + git cat-file por charter"
related_adrs:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0093-multi-tenant-isolation-tier-0
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0104-processo-mwart-canonico-unico-caminho
base: memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md (§4 fluxos por origem — não duplicado; aqui a lente é charter+máquina)
---

# Auditoria — telas do domínio Estoque × charter × verificação por máquina (2026-07-02)

> **Gatilho (Wagner):** "vai ter devolução? compra, venda, oficina, PDV? fabricação também mexe em
> estoque? ajuste, cadastro? está no page charter, corretamente, e verificado por máquina?"
>
> **Lente:** o `DOC-RAIZ §4` já mapeia *fluxo por origem (controller + direção do saldo)*. Aqui a lente é
> outra — **cobertura de charter + o que é enforçado por máquina** — pra responder a pergunta de
> governança. O inventário abaixo enriquece o §4 com a coluna charter (candidato a fundir no DOC-RAIZ).

## 1. Inventário — toda tela que toca estoque

### Movem o saldo (`variation_location_details.qty_available`)

| Tela | Arquivo | Tech | Charter | Efeito |
|---|---|---|---|---|
| PDV / venda | `Sells/Create.tsx` (flag) + `sale_pos/create.blade` | Inertia + Blade | ✅ | **sai** (final) |
| Venda (lista/caixa) | `Sells/Index·Show·Edit·Caixa` | Inertia | ✅ | sai (delega ao PDV) |
| Devolução de venda | `sell_return/*` | ⚠️ Blade | ❌ sem charter | **entra** |
| Compra | `Purchase/Create·Edit·Index·Show.tsx` + Blade fallback | Inertia + Blade | ⚠️ parcial (Index/Show ❌) | **entra** (received) |
| Devolução de compra | `purchase_return/*` | ⚠️ Blade | ❌ sem charter | **sai** |
| Ajuste de estoque | `StockAdjustment/Create·Index.tsx` + Blade | Inertia + Blade | ✅ | **sai** / reverte |
| Transferência entre locais | `StockTransfer/Create·Index.tsx` + Blade | Inertia + Blade | ✅ | **sai** origem + **entra** destino |
| Estoque inicial (opening) | `opening_stock/*`, `import_opening_stock` | ⚠️ Blade | ❌ sem charter | **entra** |
| Fabricação / produção | `Manufacturing/Index.tsx` + `ProductionController` | Inertia | ✅ | consome componentes (**sai**) + produz (**entra**) |
| OS oficina auto | `OficinaAuto/ServiceOrders/Create·Edit·Show.tsx` | Inertia | ✅ | **sai** — baixa peça ao concluir (LIVE) |
| Repair (JobSheet) | `Repair/JobSheet/*.tsx` | Inertia | ✅ | não mexe direto (fatura venda derivada) |
| Reserva FSM | sem tela (side-effect) | — | — | **hold** — segura, não baixa |

### Definem o item (não movem saldo, exceto opening)

| Tela | Arquivo | Tech | Charter |
|---|---|---|---|
| Cadastro de produto | `product/index·create·edit·show·bulk-edit·add-selling-prices` | ⚠️ Blade | ❌ (RUNBOOKs MWART em `Produto/_telas`) |
| Histórico de estoque do produto | `product/stock_history` | ⚠️ Blade | ❌ |

**Confirmações pras dúvidas do Wagner:** devolução existe (2 — venda e compra, ambas Blade). Fabricação
**mexe em estoque sim** (`ProductionController` usa `ProductUtil`/`TransactionUtil`). Além das telas que
ele citou, movem saldo também: transferência entre locais e estoque inicial.

## 2. Cobertura de charter — checado por máquina (git cat-file @origin/main)

- **Telas Inertia com charter: 15 de 17.** Faltam: `Purchase/Index`, `Purchase/Show`.
- **Telas Blade legacy: 0 charter** (por natureza — charter cobre Page Inertia). São: `sell_return`,
  `purchase_return`, `opening_stock`, `product/*`, `stock_adjustment` (fallback Blade). Candidatas a MWART.

## 3. Veredito — "está no charter, corretamente, verificado por máquina?"

| Pergunta | Resposta |
|---|---|
| **Está no charter?** | Parcial. Cada tela Inertia tem charter (15/17), mas o **mapa** (qual tela move estoque e como) **não está em charter nenhum** — vive no `DOC-RAIZ §4`. Metade das telas (Blade) sem charter. |
| **Corretamente?** | O charter descreve design/UX — **não afirma o efeito no saldo** (entra/sai). O fato "PDV baixa estoque" não está no charter; está no DOC-RAIZ. Charter garante existência+design, não a semântica de estoque. |
| **Verificado por máquina?** | **Existência do charter, sim** (gate `charter_refs` required). **Este mapa, não** — não há gate que afirme "tela X mexe no saldo na direção Y". DOC-RAIZ §4 é referência, não catraca. Hoje é asserção humana, não máquina. |

## 4. Como tornar isso máquina-verdadeiro — TESTE, não gate de declaração

> **Correção (Wagner, 2026-07-02):** a 1ª versão desta seção propôs um gate `stock-effect:check` que
> conferia uma string `stock_effect` declarada no charter. **Descartado** — é "presença ≠ correção", o
> anti-padrão já rejeitado nas proibições (§ideias descartadas). O mecanismo certo é **teste que quebra
> quando o comportamento some** (ADR 0264 casos-gate + ADR 0256), não um gate que confere declaração.

O efeito no saldo não se prova declarando no charter; prova-se com **Pest que assere o delta de
`qty_available`** por caso de uso, rodando em MySQL (não skip). A régua já existe no `DOC-RAIZ` — a
**matriz de movimentação (§3)** + as **invariantes (§7)** são os ~13-15 UCs. Cada UC vira um teste que
quebra se a venda deixar de baixar, a compra de entrar, a devolução de reintegrar. Enforçado pelo
`casos-gate` que já existe (UC↔teste), sem gate bespoke.

**Estado da cobertura hoje (checado):** só a camada reserva-FSM está testada (StockReservationsTest,
ConsumirEstoqueAuditTest, ReservarEstoqueBomTest); INV-1 (audit) tem teste; a baixa da OS tem teste mas
**skipa no sqlite**. Os movimentos-core (venda/compra/devolução/ajuste/transferência/opening/fabricação)
têm **zero** teste de saldo. Chip aberto pra construir a suíte (fixtures + UCs + Pest MySQL) — se um teste
revelar bug real (ex.: devolução de venda não reintegra, gap Vestuario), vira red-spec + decisão Tier 0 do
Wagner (regra mestre VALOR/ESTOQUE), não fix silencioso.

## 5. Follow-ups (não-bloqueantes)
- Fundir a coluna charter deste inventário no `DOC-RAIZ §4` (higiene — parte da Onda 0, Chip A).
- 2 charters faltantes: `Purchase/Index`, `Purchase/Show`.
- Telas Blade de estoque → fila MWART (devoluções, opening, cadastro produto, histórico).

## Diagrama
Visual do domínio (telas → efeito no saldo) renderizado na sessão (`dominio_estoque_telas_efeito_saldo`).
