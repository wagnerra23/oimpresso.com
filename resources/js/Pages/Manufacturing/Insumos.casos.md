---
casos: Manufacturing/Insumos — impacto reverso e simulador de preço
irmaos: Insumos.charter.md (lei) · memory/requisitos/Manufacturing/RUNBOOK-insumos.md (F1)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
fonte: handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" §4.4 + §18.3 — os UC abaixo DERIVAM dele
owner: wagner
last_run: "2026-09-04"
---

# Casos de Uso & Aceite — Manufacturing/Insumos

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> Derivados do handoff, **não do `.tsx`** (§5 tautológico das proibições).

---

## UC-INS-01 · Só entram receitas do meu business (Tier 0)
- **Persona:** qualquer tenant.
- **Aceite:** Dado um tenant fictício sem receitas · Quando `usosDoInsumo`/`listInsumosComUso`
  rodam pra ele · Então devolvem vazio — nunca insumo ou receita de outro business.
- **Fonte:** [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) +
  §18.3 ("precisa de um método com o JOIN de tenant").
- **Teste:** `Wave33InsumosTest.php`
- **Regressão que defende:** o módulo não tem global scope; trocar o JOIN por um `where`
  ingênuo vaza custo de outro tenant.
- **Status: 🧪**

---

## UC-INS-02 · A simulação recalcula pela fórmula real — não soma delta por fora
- **Persona:** Wagner — o número simulado precisa ser o custo de verdade, não uma aproximação.
- **Aceite:** Dado uma receita com `production_cost_type = percentage` (custo extra é % dos
  ingredientes) · Quando o insumo sobe X% · Então o custo simulado é o que
  `calculateCost()` devolve com o preço novo — **maior** que o atalho aditivo do protótipo
  (`total + qtd × preço × pct`), porque o extra percentual também sobe.
- **Fonte:** §7 (fórmulas) + `RUNBOOK-insumos.md §1` (o desvio declarado).
- **Teste:** `Wave33InsumosTest.php` — compara os dois caminhos com números concretos e
  exige que difiram no caso `percentage` e coincidam em `fixed`.
- **Regressão que defende:** alguém "simplificar" a simulação copiando o protótipo e passar a
  subestimar o custo exatamente nas receitas de custo percentual.
- **Status: 🧪**

---

## UC-INS-03 · Consumo conta a sub-unidade, e insumo repetido soma
- **Persona:** Wagner — a receita usa 2 linhas do mesmo insumo, em galão de 5 L.
- **Aceite:** Dado uma receita com duas linhas do mesmo insumo (0,02 e 0,03) numa sub-unidade
  de multiplicador 5 · Quando a tela calcula o consumo · Então `qtd` = (0,02 + 0,03) × 5 =
  0,25 na unidade base — não 0,05, nem só a primeira linha.
- **Fonte:** §4.4 (nota "consumo convertido à unidade base") + `calculateCost`, que já aplica
  o multiplicador.
- **Teste:** `Wave33InsumosTest.php`
- **Regressão que defende:** somar sem multiplicador (subestima) ou sobrescrever em vez de
  somar quando o insumo aparece em mais de uma linha.
- **Status: 🧪**

---

## UC-INS-04 · Denominador zero devolve 0, nunca NaN/Infinity
- **Persona:** qualquer — receita recém-criada tem `total_quantity = 0` e sem preço de venda.
- **Aceite:** Dado receita com `total_quantity = 0` e `final_price = 0` · Quando o insumo é
  simulado · Então `unit_novo = 0` e `margem_nova = 0`, ambos finitos.
- **Fonte:** §7.3 — mesma defesa das telas de Receitas e Relatório.
- **Teste:** `Wave33InsumosTest.php`
- **Status: 🧪**

---

## UC-INS-05 · O `variacao_pct` do cliente é CLAMPADO no servidor
- **Persona:** qualquer — a faixa do §4.4 é −30%..+60%.
- **Aceite:** Dado um `variacao_pct` fora da faixa (ex: 999) · Quando o controller monta o
  payload · Então o valor usado é o limite (60), não o que veio na URL.
- **Fonte:** §4.4 (faixa do slider) + a regra de nunca confiar no range do cliente.
- **Teste:** `Wave33InsumosTest.php` — assert estrutural sobre o clamp no controller.
- ⚠️ **O que este teste NÃO prova:** que uma requisição HTTP real devolve o payload clampado —
  isso é smoke (`RUNBOOK-insumos.md §5`), não Pest.
- **Status: 🧪** — do que ele mede: o clamp existe no código.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** O drawer fecha com `Esc`, com o ✕ e com clique no scrim — comportamento de
  navegador; lugar é spec Playwright.
- **[BACKLOG]** A linha do insumo é acionável por teclado (Enter/Espaço), não só por mouse.
- **[BACKLOG]** A busca casa nome e SKU.
- **[BACKLOG]** A cor da pílula de peso muda em 25% e 50%; a de margem em 45% e 55%.

## Ambiguidade declarada (não inventei desempate)

O protótipo lista insumos "sem receita" (`—` + não-clicável), porque a lista dele é um catálogo
curado. **No app essa lista é derivada dos ingredientes**, então todo item tem ≥1 receita e o
estado não ocorre. A tela mantém o caminho de render, e o motivo está no `RUNBOOK-insumos.md §2`.
Se [W] quiser o catálogo inteiro de produtos (aí o estado passa a existir), é outra consulta e
outra decisão de escopo — não é ajuste de código.

## Trilha do tempo
- 2026-09-04 · [F+C] US-MANU-005. 5 UC com teste Pest; 4 no backlog aguardando e2e. É a onda
  que trouxe o backend que o §18.3 declarava faltar. Refs: UI-0013 · ADR 0264 G-1/G-2 · ADR 0093.
