---
id: resources-js-pages-settings-payment-gateways-index-casos
casos: Gateways de Pagamento · /settings/payment-gateways
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-09-11"
---

> ℹ️ **`last_run` 2026-08-25 → 2026-09-11 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Index.tsx` no [#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224) foi **1 linha(s) de COMENTÁRIO** —
> o path do protótipo (`prototipo-ui/cowork/…` → `prototipo-ui/cowork/Wagner/…`, topologia por dono da ADR 0397).
> Zero JSX estrutural, zero handler, zero prop, zero copy alterada (verificado: `git diff origin/main...HEAD -- Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.tsx`
> só tem linhas iniciadas por `//`, `*` ou `{/*`). **Nenhum UC desta tela foi reexecutado nem revalidado**; o bump é o que o campo
> significa na prática (*trio reconciliado com a tela nesta data*), não afirmação de re-run — mesmo tratamento do #6913.

# Casos de Uso & Aceite — Gateways de Pagamento

> Tela Tier-0 (**segredos/credenciais** + toggle que libera/trava emissão de cobrança). Persona: **Wagner** (superadmin).
> Passo 3 do [template-onda-modulo] (régua por tela) — complementa a [CAPTERRA-FICHA](../../../../../memory/requisitos/PaymentGateway/CAPTERRA-FICHA.md) (nota 67) sem roadmap paralelo.
>
> **Status:** ✅ passa (UC-id citado por teste, no manifesto) · 🧪 comportamento tem teste Feature mas **sem UC-id** (débito de rastreabilidade G-2) · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Débito real desta tela = rastreabilidade, não ausência de teste.** O comportamento já é defendido por `PaymentGatewaysControllerTest` + `PaymentGatewaysControllerStoreTest` (Feature/MySQL, verdes no CT100). O que falta é a G-2 ([ADR 0264]): nenhum teste **cita** um `UC-PG-NN`, então nenhum caso está no manifesto. Cada item vira `UC-PG-NN` **no mesmo PR** que adicionar o id ao teste que já existe (edição de 1 linha) — [ADR 0062] CT100.

> ℹ️ **`last_run` 2026-08-12 → 2026-08-25 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Index.tsx` foi **comentário de ponteiro de design**: a linha citava um
> caminho de protótipo que não existe mais no repo, e passou a citar o vivo (ou a declarar a
> ausência com data). Zero JSX, zero handler, zero prop, zero copy alterada — `git diff --numstat`
> do PR não tem uma linha sequer fora de comentário. **Nenhum UC desta tela foi reexecutado nem
> revalidado**; o bump é o que o campo significa na prática (*trio reconciliado com a tela nesta
> data*), não afirmação de re-run.
---

## Casos com id (promovidos do backlog em 2026-09-23)

> **Fonte de cada UC:** charter (§Goals + §Automation Anti-hooks) **cruzado** com o controller real `PaymentGatewaysController@index` (`Inertia::render('Settings/PaymentGateways/Index')` + `defer` de `gateways`/`kpis`) e `@toggle`. Nenhum UC vem do protótipo.
>
> **Prefixo `UC-PGSET-`, não `UC-PG-`:** o `CnabRetorno.casos.md` irmão também anuncia promoção a `UC-PG-NN`; dois donos com o mesmo id fazem a prova de um creditar o outro (§5 2026-09-04). Prefixo por tela evita a colisão.
>
> **Teste:** `tests/Feature/PaymentGateway/PaymentGatewaysSettingsContratoTest.php` — tenant fictício **98**, adversário **99** ([ADR 0358]), nunca biz=4; nenhuma chamada a API de gateway; cada caso negativo tem controle positivo ao lado.
>
> ⚖️ **Onde roda, e com que força** (medido 2026-09-23): **nenhuma lane de PR** executa este arquivo — `test-lane-coverage` e `.github/ci-sqlite-pest.list` não o listam (o módulo inteiro está fora das lanes: 45 de 48 arquivos órfãos). Ele entra no **nightly CT 100** via `phpunit.xml` (`./tests/Feature` recursivo) + `scripts/tests/shards-plan.mjs`. Sem lane de PR não há veredito por PR — por isso todo status abaixo é 🧪 **sem veredito**.

## UC-PGSET-01 · Abrir a tela e ver as credenciais do próprio business
- **Persona:** Wagner (superadmin) — abre `/settings/payment-gateways` para conferir o que está configurado.
- **Aceite:** Dado uma credencial do business da sessão · Quando abro a tela e a lista (prop deferida `gateways`) carrega · Então o componente é `Settings/PaymentGateways/Index` e a credencial aparece com o apelido, o driver e o estado `ativo` dela.
- **Teste:** `PaymentGatewaysSettingsContratoTest` — `UC-PGSET-01 abre a tela e a credencial do PROPRIO business aparece…`
- **Regressão que defende:** lista vazia/quebrada na tela de config (ninguém consegue gerir gateway).
- **Status: 🧪** sem veredito — pendente do nightly CT 100.

## UC-PGSET-02 · `[T0]` Credencial de outro business nunca aparece na lista
- **Persona:** Wagner — vê só as credenciais do próprio business ([ADR 0093]).
- **Aceite:** Dado uma credencial no business 98 e outra no 99 · Quando o 98 carrega a lista · Então a própria aparece (controle positivo) e a alheia não.
- **Teste:** `PaymentGatewaysSettingsContratoTest` — `UC-PGSET-02 [T0] …`
- **Regressão que defende:** vazamento cross-tenant de credencial de cobrança.
- **Status: 🧪** sem veredito.

## UC-PGSET-03 · Toggle inverte `ativo` e persiste
- **Persona:** Wagner — confirma o toggle no modal Trust L3 para ligar/desligar um gateway.
- **Aceite:** Dado uma credencial ativa · Quando `POST …/{id}/toggle` · Então a resposta traz `ativo=false` e o banco grava `false`; um segundo toggle volta a `true`.
- **Teste:** `PaymentGatewaysSettingsContratoTest` — `UC-PGSET-03 …`
- **Regressão que defende:** toggle que responde mas não persiste (gateway "desligado" que continua emitindo).
- **Status: 🧪** sem veredito.

## UC-PGSET-04 · `[T0]` Toggle cross-tenant devolve 404 e não altera o registro
- **Persona:** Wagner — não consegue ligar/desligar credencial de outro business, nem por id direto.
- **Aceite:** Dado uma credencial ativa do business 99 · Quando o 98 faz `POST …/{id}/toggle` · Então 404 e a credencial alheia continua `ativo=true`.
- **Teste:** `PaymentGatewaysSettingsContratoTest` — `UC-PGSET-04 [T0] …`
- **Regressão que defende:** IDOR de escrita em credencial de cobrança de outro tenant.
- **Status: 🧪** sem veredito.

## UC-PGSET-05 · O payload da lista nunca carrega `config_json`
- **Persona:** Wagner — a tela mostra apelido/driver/estado, nunca o segredo do gateway (charter §Anti-hooks).
- **Aceite:** Dado uma credencial com `config_json` contendo um marcador inerte · Quando a lista carrega · Então o apelido está no payload (controle positivo) e o marcador não.
- **Teste:** `PaymentGatewaysSettingsContratoTest` — `UC-PGSET-05 …`
- **Regressão que defende:** segredo de gateway (api_key/client_secret) exposto no Inertia payload.
- **Status: 🧪** sem veredito.

## UC-PGSET-06 · KPIs contam só o business da sessão
- **Persona:** Wagner — os cards "ativos / health / total" refletem só o business dele.
- **Aceite:** Dado +1 ativa ok, +1 ativa não-ok, +1 inativa não-ok no 98 (e ruído no 99) · Quando recarrego `kpis` · Então `ativos` sobe 2, `total` sobe 3, `fail` sobe 1 (fail = ativa **e** health ≠ ok).
- **Teste:** `PaymentGatewaysSettingsContratoTest` — `UC-PGSET-06 …`
- **Regressão que defende:** KPI contando outro tenant ou contando inativa como falha.
- **Status: 🧪** sem veredito. `cobs_hoje` não é asserido por valor (fica no backlog).

## UC-PGSET-07 · Abrir a tela é read-only
- **Persona:** Wagner — abrir a tela não cria credencial nem dispara cobrança (charter §Anti-hooks).
- **Aceite:** Dado as contagens de `payment_gateway_credentials` e `cobrancas` · Quando renderizo a tela e recarrego `gateways` e `kpis` · Então as duas contagens não mudam.
- **Teste:** `PaymentGatewaysSettingsContratoTest` — `UC-PGSET-07 …`
- **Regressão que defende:** efeito colateral de escrita no GET.
- **Status: 🧪** sem veredito.

---

## Backlog de casos (sem id — entram quando um teste citar o UC-id)

> Regra G-2: UC declarado em heading `## UC-*` sem teste que o cite = órfão → quebra `casos-gate`. Mantidos como bullets até o id ser wired no teste correspondente.

- **[BACKLOG · 🧪 tem teste] Lista gateways do business + warn deprecated** — PesaPal aparece com label `warn`. _Coberto por `...ControllerTest::lista gateways + warn deprecated PesaPal` (arquivo órfão de lane, biz=1). Não promovido: o `warn` é copy do controller, sem segunda fonte no charter._
- **[BACKLOG] KPI `cobs_hoje` por valor** — cobranças criadas hoje no business. Precisa fixture de `cobrancas`; não asserido por valor no UC-PGSET-06.
- **[BACKLOG · 🧪 tem teste] Novo gateway (wizard) cria credencial Inter sandbox** — e rejeita duplicata `(business_id, gateway_key, ambiente)`, rejeita conta de outro business (Tier 0), valida enum de `gateway_key`. _Coberto por `PaymentGatewaysControllerStoreTest` (4 casos)._
- **[BACKLOG · ⬜ sem teste] Health check on-demand atualiza `health_status`** — Dado botão "Testar todos" / "Rodar agora" · Quando aciono · Então o endpoint atualiza `health_status`/`latencia`/`last_check` no DB. _Charter prevê Pest GUARD `health-check endpoint atualiza health_status` mas o teste ainda não existe — candidato a UC + Pest._
- **[BACKLOG · ⬜ sem teste · UI] Ações de linha não-wired** — os botões por-linha `RefreshCw` (rodar health check) e `MoreHorizontal` (mais ações) não têm `onClick` (Index.tsx:248 (verificado@d4afe95),250). Candidato a UC E2E quando forem ligados.

## Como rodar a suíte
1. **Pest (MySQL real):** lane do PaymentGateway no CT100 ([ADR 0062]) — `PaymentGatewaysControllerTest` + `...StoreTest` já verdes.
2. **Cadência:** rodar ao fim de toda mexida na tela. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-09-23 · [CL] Prontidão thread 05: 5 bullets do backlog + 2 anti-hooks do charter promovidos a `UC-PGSET-01..07`, citados por `tests/Feature/PaymentGateway/PaymentGatewaysSettingsContratoTest.php` (tenant 98). Health check, ações de linha, wizard e warn PesaPal seguem no backlog.
- 2026-07-03 · [CC] criado no Passo 3 do programa de ondas (régua por tela), complementando a CAPTERRA-FICHA. Débito exposto = **UC-traceability** (0 UC-id apesar de baseline Feature forte); a nota de UX (80 Advanced) coexiste com comportamento tested-mas-não-traçado.

[template-onda-modulo]: ../../../../../memory/requisitos/_Governanca/programa-ondas/template-onda-modulo.md
[ADR 0264]: ../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
[ADR 0062]: ../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md
