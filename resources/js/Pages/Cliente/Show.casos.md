---
casos: Detalhe do cliente (deprecated) · /cliente/{id}
irmaos: Show.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: Show está deprecated (drawer 760 substituiu, ADR 0179), mas os _show/*Tab são REUSADOS pelo drawer — o comportamento deles continua vivo.
owner: wagner
last_run: "2026-07-08"
---

# Casos de Uso & Aceite — Detalhe do cliente (Show · deprecated)

> **⚠️ Tela `deprecated`** ([ADR 0179](../../../../memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)): o drawer 760 da Index substituiu o Show full-page. Mas os componentes `_show/*Tab` (Extrato/Vendas/Pagamentos/Docs) são **reusados pela aba Operações do drawer** — então o contrato deles segue defendido. UCs ancorados nos testes de tab existentes (em lane ativa, `quarantine=0`). São guards estruturais dos componentes — a prova de render/dado real = smoke.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (guard estrutural / manifesto não regravado) · ⬜ não verificado · ❌ quebrou.

---

## UC-CSHW-01 · A aba Extrato do cliente mostra o saldo (débito/crédito/all-time)
- **Persona:** Larissa — abre o extrato do cliente e vê os lançamentos com resumo do período + resumo geral.
- **Aceite:** Dado o detalhe do cliente · Quando abro a aba **Extrato** · Então o componente `LedgerTab` renderiza com filtros (range de datas + formato 1/2/3 + local) e os resumos período/all-time.
- **Teste:** `tests/Feature/Cliente/Show/LedgerTabTest.php` — `LedgerTab.tsx — estrutura mínima componente` + `LedgerTab.tsx — resumo período + resumo geral all-time`.
- **Nota:** o cálculo do saldo em si é travado por [Ledger.casos.md](Ledger.casos.md) (UC-CLED-*), dente `CalculoValorClienteTest`.
- **Status: 🧪** — guard estrutural passa; render/dado real = smoke; ✅ com o manifesto regravado.

---

## UC-CSHW-02 · A aba Vendas do cliente lista as vendas com as colunas certas
- **Persona:** Larissa — quer ver as vendas daquele cliente com data/documento/valor/status.
- **Aceite:** Dado o detalhe do cliente · Quando abro a aba **Vendas** · Então o componente `SalesTab` renderiza com as 7 colunas requisitadas + filtros (range de datas + status de pagamento + busca).
- **Teste:** `tests/Feature/Cliente/Show/SalesTabTest.php` — `SalesTab.tsx — todas 7 colunas requisitadas` + `SalesTab.tsx — filtros range datas + status pagamento + busca`.
- **Status: 🧪** — guard estrutural passa; render/dado real = smoke; ✅ com o manifesto regravado.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG] Aba Pagamentos (self-fetch AJAX)** — anchor em `Show/PaymentsTabTest`.
- **[BACKLOG] Dropdown Ações (Pagar/Excluir/Ativar/Desconto)** — anchor em `Show/ActionsMenuTest`.
- **[BACKLOG] Migrar cobertura para a Index+drawer** — como Show é deprecated, o alvo canônico de novos casos é a aba Operações do drawer 760 (Index.casos.md), não esta tela.

## Como rodar a suíte
1. **Pest:** `docker exec oimpresso-staging php artisan test --filter="LedgerTabTest|SalesTabTest"` no CT100.
2. **Manifesto:** `npm run casos:results` → 🧪 vira ✅.
3. **Cadência:** ao mexer nos `_show/*Tab` (que o drawer reusa), revalidar aqui E na aba Operações do drawer.

## Trilha do tempo
- 2026-07-08 · [CC] criado — Fase 2 (lanes Cliente), fecha o trio da última tela roteada (Show, deprecated). UCs ancorados nos `Show/*Tab` reusados pelo drawer. Novos casos devem ir pra Index.casos.md (aba Operações). Refs: [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-1/G-2 · [ADR 0179](../../../../memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md).
