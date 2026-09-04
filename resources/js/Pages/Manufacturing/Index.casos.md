---
casos: Manufacturing/Index — ordens de produção
irmaos: Index.charter.md (lei) · memory/requisitos/Manufacturing/RUNBOOK-producao.md (F1)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
fonte: handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" §4.5 + §15.1 — os UC abaixo DERIVAM dele
owner: wagner
last_run: "2026-09-04"
---

# Casos de Uso & Aceite — Manufacturing/Index

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> A tela existe desde a Wave J (2026-05); estes UC nascem com a **US-MANU-004**, que é emenda
> (5 → 8 colunas). Derivados do handoff, **não do `.tsx`** (§5 tautológico das proibições).

---

## UC-OP-01 · O custo unitário nunca vira infinito numa ordem sem quantidade
- **Persona:** Eliana (produção) — ordem legada com `quantity` zerado não pode explodir a tela.
- **Aceite:** Dado uma ordem cuja linha de compra tem `quantity = 0` · Quando o Service monta a
  linha · Então `custo_unitario` é `0.0` — nunca `INF`, nunca `NaN`.
- **Fonte:** §7.3 do handoff (divisão por zero) + `RUNBOOK-producao.md §2`.
- **Teste:** `Wave32ProducaoColunasTest.php`
- **Regressão que defende:** dividir por `quantity` sem guard — o PHP devolve `INF`, que
  atravessa o JSON como `null`/erro de serialização e quebra a coluna inteira.
- **Status: 🧪**

---

## UC-OP-02 · O custo mostrado é o GRAVADO, não um recalculado novo
- **Persona:** Wagner — o número da lista tem que ser o da ordem, não uma segunda fórmula.
- **Aceite:** Dado o `enrichProductionRows()` · Quando ele monta `final_total` e
  `custo_unitario` · Então os dois derivam de `transactions.final_total` (valor gravado na
  criação) — **sem** chamar `RecipeBomService`, que é a fórmula do Relatório.
- **Fonte:** decisão registrada em `RUNBOOK-producao.md §1` (o `custoSnap` do protótipo não
  existe no banco; US-MANU-007 é quem o introduz).
- **Teste:** `Wave32ProducaoColunasTest.php`
- **Regressão que defende:** alguém "unificar" o custo desta tela com o do Relatório e criar
  uma segunda fórmula de valor na base — §5 2026-06-05 (derivar do lugar errado) + REGRA
  MESTRE de valor.
- **Status: 🧪**

---

## UC-OP-03 · O enriquecimento é em LOTE — não uma query por ordem
- **Persona:** qualquer business com muitas ordens no período.
- **Aceite:** Dado N ordens listadas · Quando o Service enriquece as linhas · Então o nº de
  queries **não cresce com N** (produtos, receitas e usuários são resolvidos em 3 consultas
  `whereIn`, e as linhas de compra vêm por eager-load).
- **Fonte:** padrão do módulo (`listRecipesWithCost`, `reportByProduct`) + ADR 0093 (a cadeia
  de tenant é JOIN, não loop).
- **Teste:** `Wave32ProducaoColunasTest.php` — conta queries com `DB::listen` sobre um
  conjunto sintético.
- **Regressão que defende:** o N+1 clássico (resolver produto/usuário dentro do `map`).
- **Status: 🧪**

---

## UC-OP-04 · Ordem sem receita não some da lista — mostra 0 ingredientes
- **Persona:** Eliana — ordem antiga cujo produto perdeu a receita continua visível.
- **Aceite:** Dado uma ordem cuja variação produzida não tem `mfg_recipes` · Quando a lista
  carrega · Então a linha aparece com `n_ingredientes = 0` e `produto` resolvido (ou `—`),
  nunca sumindo do resultado.
- **Fonte:** §4.5 (a lista é de ORDENS, não de receitas) + sintoma catalogado no
  `RUNBOOK-producao.md §4`.
- **Teste:** `Wave32ProducaoColunasTest.php`
- **Regressão que defende:** trocar o `leftJoin` da contagem de ingredientes por `join`, o que
  faria a ordem sem receita desaparecer silenciosamente da tela.
- **Status: 🧪**

---

## UC-OP-05 · Tier 0 — a lista não vaza ordem de outro business
- **Persona:** qualquer tenant.
- **Aceite:** Dado um tenant fictício sem ordens · Quando `listProductions` roda pra ele ·
  Então devolve vazio; e o enriquecimento revalida a cadeia do produto por
  `products.business_id`.
- **Fonte:** [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Teste:** `Wave32ProducaoColunasTest.php`
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** O sufixo `fix` aparece só em ordem finalizada, com o `title` verbatim (R-21) —
  comportamento de navegador; lugar é spec Playwright.
- **[BACKLOG]** O checkbox "Só finalizadas" e o KPI "Finalizadas" governam o MESMO filtro (um
  reflete o outro).
- **[BACKLOG]** O rodapé soma exatamente as ordens exibidas.

## Trilha do tempo
- 2026-09-04 · [F+C] US-MANU-004 (emenda: 5 → 8 colunas). 5 UC com teste Pest. A tela é da
  Wave J; este é o primeiro `casos.md` dela. Refs: UI-0013 · ADR 0264 G-1/G-2 · ADR 0093.
- 2026-09-04 · [F+C] **CUTOVER**: a tela passou a ser servida no endereço CANÔNICO do módulo
  (nasceu em `/manufacturing/v2/*`, que virou 301). Pedido [F]: *"módulo inteiro em produção,
  com os links e vínculos reais, sem rotas alternativas"*, sobre a aprovação [W] da família.
  Pré-condição medida: a regra F5 (cutover exige aviso a cliente) nomeia a ROTA LIVRE, e [F]
  confirmou que ela não usa Fabricação. **Nenhuma rota removida ou renomeada** — `?legacy=1`
  devolve o Blade no MESMO endereço e o ramo AJAX do DataTables segue intacto. Guarda nova:
  `Modules/Manufacturing/Tests/Feature/CutoverRotasCanonicasTest.php` (9 asserts). Nenhum UC
  acima mudou de comportamento; os asserts de ROTA de Wave30/31/33 foram reapontados pro
  canônico porque o `/v2/` agora responde `RedirectController`, não o controller da tela.
