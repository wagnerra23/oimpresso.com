---
id: resources-js-pages-financeiro-dre-index-casos
casos: DRE gerencial · /financeiro/dre
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /financeiro/dre

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

Personas: **Wagner [W]** ("deu lucro?") + **Eliana [E]**. Read-only; 3 abas (Demonstrativo · Balanço · Balancete).

## UC-DRE-01 — "Deu lucro este mês?" em <60s
Status: 🧪 (`DreControllerTest` — shape do demonstrativo + subtotais)
Quando Wagner abre o Demonstrativo · Então lê a hierarquia clássica (Receita bruta → Deduções → Receita líquida → Custos → Lucro bruto → Despesas → **Resultado operacional** destacado na última linha).
**Pronto quando:** cada subtotal é a soma exata das linhas acima dele.

## [BACKLOG] % RL preserva o sinal
Status: ⬜ sem prova — o regression guard prova que pct_rl é NUMÉRICO, não que preserva o sinal. Vira `UC-DRE-02` quando existir teste citando o id (G-2).
Dado dedução/custo/despesa (valor negativo) · Quando a coluna % RL calcula · Então mostra `-9,3%` (nunca o módulo). Anti-hook literal do charter.

## UC-DRE-03 — Comparativo com o mês anterior por linha
Status: 🧪 (`DreControllerTest` — coluna prev + Δ%)
Cada linha traz o valor de M-1 e o Δ% com tom semântico (positivo/negativo). Histórico > M-1 é Non-Goal.

## [BACKLOG] Períodos não entregues não mentem
Status: ⬜ sem prova — período Trim/Ano/12m desabilitado — comportamento visual sem asserção. Vira `UC-DRE-04` quando existir teste citando o id (G-2).
Só **Mês** funciona; os outros ficam desabilitados, nunca renderizando número inventado.

## UC-DRE-05 — Balanço patrimonial gerencial fecha a equação
Status: 🧪 (`DreBalancoBalanceteTest` — A = P + PL)
Quando abre a aba **Balanço** · Então vê Ativo (bancos + a receber) | Passivo (a pagar) + PL derivado, com o rodapé provando `Ativo = Passivo + PL` e o **banner "Versão gerencial"** obrigatório ([W] 2026-05-21).

## UC-DRE-06 — Balancete soma D = C por natureza
Status: 🧪 (`DreBalancoBalanceteTest` — totais débito/crédito)
Aba **Balancete**: contas do plano com saldo ≠ 0, indentadas por nível, com D/C e total geral; contas sem movimento não aparecem.

## UC-DRE-07 — Troca de aba só re-busca a aba
Status: 🧪 (`DreBalancoBalanceteTest` — partial reload `only: ['aba','balanco','balancete']`)
Trocar de aba não re-carrega o demonstrativo inteiro (D-14).

## UC-DRE-08 — Tier 0 e read-only
Status: 🧪 (`DreControllerTest` — business_id da session, nunca do query param)
Nenhum `business_id` aceito por querystring ([ADR 0093]); GET não muta; export PDF/Excel não recalcula valor.

## Backlog de casos (sem id)
- **[BACKLOG] Export PDF/Excel bate com a tela** — `DreExport`/`pdf/dre.blade.php` devolvem os mesmos números do demonstrativo exibido.
- **[BACKLOG] Meta de margem 12%** — card inferior compara margem com a meta hardcode F1 (config por tenant é backlog).

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork (leva 1). A tela é `canon_source` do protótipo, tem 2 arquivos de teste e **não tinha `casos.md`**.

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
