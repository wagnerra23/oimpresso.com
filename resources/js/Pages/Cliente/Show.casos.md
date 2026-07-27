---
id: resources-js-pages-cliente-show-casos
casos: Detalhe do cliente (deprecated) · /cliente/{id}
irmaos: Show.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: Show está deprecated (drawer 760 substituiu, ADR 0179), mas os _show/*Tab são REUSADOS pelo drawer — o comportamento deles continua vivo.
owner: wagner
last_run: "2026-07-08"
last_run_ci: "UC-CSHW-03 nasce em 2026-07-27 — veredito pendente da lane PHP / Pest (Cliente · MySQL)"
---

# Casos de Uso & Aceite — Detalhe do cliente (Show · deprecated)

> **⚠️ Tela `deprecated`** ([ADR 0179](../../../../memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)): o drawer 760 da Index substituiu o Show full-page. Mas os componentes `_show/*Tab` são **reusados pela aba Operações do drawer** — verificado: `_drawer/OssTab.tsx` importa `LedgerTab`, `SalesTab`, `PaymentsTab`, `DocumentsTab`, `PessoasContatoTab`, `SubscriptionsTab` e `RewardPointsTab` de `../_show/`. O contrato deles segue defendido.
>
> ⚖️ **Onde estes UC rodam, e com que força** (medido 2026-07-27; declarado porque prosa vaga soa mais forte que o enforcement real):
> - **lane:** `PHP / Pest (Cliente · MySQL)` — [`.github/workflows/cliente-pest.yml`](../../../../.github/workflows/cliente-pest.yml), criada em 2026-07-27.
> - **força:** **advisory** — a lane **não** está em [`governance/required-checks-baseline.json`](../../../../governance/required-checks-baseline.json). Reprova visível, **não bloqueia merge**.
> - **antes desta lane** os testes destes UC rodavam **só no full-suite nightly do CT 100** (`phpunit.xml` inclui `./tests/Feature` recursivo) e em **nenhuma** lane de PR — `.github/ci-sqlite-pest.list` tinha 0 entradas de Cliente. A redação anterior deste bloco dizia "em lane ativa"; era falso e foi corrigido.
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

## UC-CSHW-03 · A aba Pagamentos mostra os pagamentos sem entregar a conta bancária `[T0]`
- **Persona:** Larissa — confere os pagamentos daquele cliente na aba Pagamentos do drawer. Ela precisa reconhecer o pagamento; **não** precisa (nem deve) receber o número da conta inteiro.
- **Aceite:** Dado um pagamento por transferência com número de conta gravado · Quando abro `GET /cliente/{id}/payments-json` · Então o pagamento aparece na lista **e** o número da conta não sai do servidor em ponto nenhum da resposta — só uma forma redigida terminando nos 4 últimos dígitos. E pagamento de outro contato (mesmo tenant) não entra; contato de outro `business_id` responde **404**.
- **Teste:** `tests/Feature/Cliente/ClientePagamentosPiiTest.php` — 4 casos (`entrega os pagamentos` · `número da conta não sai do servidor` · `pagamento de outro contato não entra` · `contato de outro business → 404`).
- **Regressão que defende:** duas de uma vez. (1) **PII bancária** — este é o **único** ponto do módulo Cliente com redação real (`'****' . substr($n, -4)`, `ContactController::paymentsJson`); todo o resto chama `maskTaxNumber`, que só **formata** (SDD §5.4.3). (2) **Tier 0 sem rede** — `App\Contact` não tem global scope (0 ocorrências de `addGlobalScope` em `app/Contact.php`); o isolamento aqui é o `where('business_id')->findOrFail()` manual do controller, e nada o defendia.
- **Nota de método:** a asserção varre o **JSON cru inteiro**, não a chave `bank_account_number` — o contrato é *"a conta não vaza"*, não *"a chave se chama X"* (renomear a chave não pode fazer o vazamento passar). E cada asserção de ausência é precedida da prova de que o pagamento **viajou** — asserção de ausência sobre lista vazia é verde por não-execução ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-24).
- **Deriva de:** SDD [§6.3 CU-CLI-10](../../../../memory/requisitos/Cliente/SDD-cadastro-cliente-v1.0.md) + US-CRM-063 (*"PII bancária mascarada"*) + Anti-hook dos charters `Index`/`Show` (*"Dados bancários nunca plain"*) — **não** do payload atual.
- **Status: 🧪** — teste nasce neste PR; veredito da lane `PHP / Pest (Cliente · MySQL)` (**advisory**). Se vier vermelho, o vermelho **é o achado**.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG] Dropdown Ações (Pagar/Excluir/Ativar/Desconto)** — anchor em `Show/ActionsMenuTest`.
- **[BACKLOG] Contato `type='both'` perde as abas Compras e Relatório de estoque** — a Blade `contact/show.blade.php:66-82` as serve pra `type ∈ {both, supplier}`, e `/cliente/{id}` aceita `both`; nenhum dos 13 arquivos de `_show/` as implementa (SDD §5.4.1 · CU-CLI-15). Sem id de propósito: **não há implementação a defender** — UC agora nasceria órfão e travaria o merge de quem for atendê-lo. Vira UC quando [W] decidir implementar, ou some quando [W] declarar Non-Goal.
- **[BACKLOG] CPF/CNPJ censurado no payload** — `maskTaxNumber` **formata** (`123.456.789-01`), não redige; os 4 testes que "provam" o masking fazem `file_get_contents` do Controller (presença de chamada, não efeito). Censurar de verdade é decisão de produto+jurídico, não do agente (SDD §5.4.3). Sem id até [W] decidir — e **deliberadamente sem teste que trave o comportamento atual** (travar o desvio é o anti-padrão de [proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05).
- **[BACKLOG] Migrar cobertura para a Index+drawer** — como Show é deprecated, o alvo canônico de novos casos é a aba Operações do drawer 760 (Index.casos.md), não esta tela.

## Rastreabilidade (UC → CU do SDD → US do SPEC)

| UC | CU (SDD §6) | US (SPEC) |
|---|---|---|
| UC-CSHW-01 | CU-CLI-08 | US-CRM-064 |
| UC-CSHW-02 | CU-CLI-06 | US-CRM-065 |
| UC-CSHW-03 | CU-CLI-10 · CU-CLI-11 | US-CRM-063 |

> As 3 US são declaradas porque o SPEC aponta **estes** arquivos no `**Testado em:**` delas (US-CRM-064 → `Show/LedgerTabTest.php`; US-CRM-065 → a aba Vendas; US-CRM-063 → a aba Pagamentos, cujo endpoint o `ClientePagamentosPiiTest` exercita com `// @covers-us US-CRM-063`). Vínculo por semelhança de tema **não** entra.

## Como rodar a suíte
1. **Pest:** `docker exec oimpresso-staging php artisan test --filter="LedgerTabTest|SalesTabTest|ClientePagamentosPiiTest"` no CT100 (nunca local/Hostinger).
2. **CI:** a lane `PHP / Pest (Cliente · MySQL)` roda os 3 no PR (advisory) — [`cliente-pest.yml`](../../../../.github/workflows/cliente-pest.yml).
3. **Manifesto:** `npm run casos:results` → 🧪 vira ✅.
4. **Cadência:** ao mexer nos `_show/*Tab` (que o drawer reusa), revalidar aqui E na aba Operações do drawer.

## Trilha do tempo
- 2026-07-27 · [CC] chip S-Cliente do passo 5 (agent `sdd-from-source`, [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md)). **+UC-CSHW-03** (PII bancária + Tier 0 da aba Pagamentos), promovendo o `[BACKLOG]` mais antigo deste arquivo; teste novo `ClientePagamentosPiiTest`. **Correção factual:** o cabeçalho dizia "em lane ativa" — medido, não havia lane nenhuma de PR pro Cliente (`ci-sqlite-pest.list` = 0 entradas); os testes rodavam só no nightly CT 100. Lane criada no mesmo PR, **advisory**, com a força declarada. **+2 `[BACKLOG]`** derivados do SDD §5.4.1 e §5.4.3, sem id de propósito (UC sem implementação = órfão, G-2). Refs: [SDD Cliente](../../../../memory/requisitos/Cliente/SDD-cadastro-cliente-v1.0.md) §5.4.1/§5.4.3/§6.3 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- 2026-07-08 · [CC] criado — Fase 2 (lanes Cliente), fecha o trio da última tela roteada (Show, deprecated). UCs ancorados nos `Show/*Tab` reusados pelo drawer. Novos casos devem ir pra Index.casos.md (aba Operações). Refs: [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-1/G-2 · [ADR 0179](../../../../memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md).
