---
casos: Nova devolução · origem · /sell-return/create
irmaos: Create.charter.md (lei) · Create.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela decide QUAIS vendas a pessoa pode devolver — isso não muda num refactor visual.
owner: wagner
last_run: "2026-11-24"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Sells · MySQL)"
---

# Casos de Uso & Aceite — Nova devolução · origem

> **Fonte:** etapa 1 "Origem da devolução" do protótipo `prototipo-ui/cowork/Wagner/devolucao-v3.html`
> (modo venda) + o escopo do caminho Blade que acha a venda pelo número
> (`SellReturnController@validateInvoiceToReturn`).
>
> **Teste:** `tests/Feature/Sells/SellReturnCreateContratoTest.php` — tenant 98 × adversário 99,
> `DatabaseTransactions`. **Lane:** `PHP / Pest (Sells · MySQL)` — `.github/workflows/sells-pest.yml`.
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-SRCRE-01 · "+ Nova devolução" abre a escolha da venda `[must]`
- **Persona:** Larissa — está na lista de devoluções e precisa registrar uma nova.
- **Aceite:** Dado uma visita Inertia a `/sell-return/create` · Quando a pessoa tem `access_sell_return` · Então recebe o componente `SellReturn/Create`, com as vendas carregadas em seguida (prop deferida). O botão da lista aponta para essa rota.
- **Teste:** `SellReturnCreateContratoTest` — `UC-SRCRE-01`.
- **Regressão que defende:** o botão voltar a levar para `/sells` e a pessoa sair da área de devoluções (o defeito reportado em 2026-11-24).
- **Status: 🧪**

## UC-SRCRE-02 · Venda de outro business não aparece `[T0]` `[must]`
- **Aceite:** Dado uma venda final no tenant 99 · Quando o usuário do tenant 98 abre a tela · Então ela não está na lista, nem pela busca do número.
- **Teste:** `SellReturnCreateContratoTest` — `UC-SRCRE-02`.
- **Status: 🧪**

## UC-SRCRE-03 · Só vendas finalizadas `[must]`
- **Aceite:** Dado um rascunho e uma devolução na mesma empresa · Quando a tela lista · Então nenhum dos dois aparece; só `type=sell` com `status=final`.
- **Teste:** `SellReturnCreateContratoTest` — `UC-SRCRE-03`.
- **Status: 🧪**

## UC-SRCRE-04 · Filtro Com NF-e × Sem nota `[must]`
- **Aceite:** Dado uma venda com NF-e autorizada e outra sem nota · Quando filtra "Com NF-e" · Então só a primeira aparece, com o número da nota · Quando filtra "Sem nota" · Então só a segunda.
- **Teste:** `SellReturnCreateContratoTest` — `UC-SRCRE-04`.
- **Status: 🧪**

## UC-SRCRE-05 · Busca por número da venda `[must]`
- **Aceite:** Dado várias vendas · Quando busca pelo número de uma delas · Então só ela aparece.
- **Teste:** `SellReturnCreateContratoTest` — `UC-SRCRE-05`.
- **Status: 🧪**

## UC-SRCRE-06 · Sem permissão de devolução, 403 `[must]`
- **Aceite:** Dado usuário sem `access_sell_return` nem `access_own_sell_return` · Quando abre a tela · Então recebe 403.
- **Teste:** `SellReturnCreateContratoTest` — `UC-SRCRE-06`.
- **Status: 🧪**

## UC-SRCRE-07 · Venda já devolvida avisa "Abrir devolução" `[should]`
- **Aceite:** Dado uma venda que já tem devolução · Quando aparece na lista · Então a linha traz o número da devolução, e o botão abre o mesmo registro na edição.
- **Teste:** `SellReturnCreateContratoTest` — `UC-SRCRE-07`.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Consignação: filtro e "Fazer acerto" do protótipo, quando a remessa em consignação existir.
- **[BACKLOG]** "Não é devolução?": garantia com troca de peça e conserto/comodato, quando houver fluxo de destino.

## Trilha do tempo
- 2026-11-24 · [CC] tela nasce por criar-tela.mjs (PT-01) e é preenchida com a etapa 1 do protótipo Devolução V3. Refs: UI-0013 · ADR 0264 G-1/G-2 · ADR 0093.
