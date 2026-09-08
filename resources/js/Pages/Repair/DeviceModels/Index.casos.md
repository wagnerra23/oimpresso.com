---
id: resources-js-pages-repair-device-models-index-casos
casos: Catálogo de modelos de aparelho · /repair/device-models
irmaos: Index.charter.md (lei) · Create.casos.md · Edit.casos.md
tecnica: Caso de uso = narrativa do operador + criterio de aceite verificavel (Dado/Quando/Entao)
por_que: comportamento e duravel — "o filtro corta no servidor" e "o KPI conta so o meu tenant" valem em qualquer refactor
owner: wagner
autor: "[C] 2026-09-05"
last_run: "2026-09-05"
---

# Casos de Uso & Aceite — Catálogo de modelos de aparelho

> Derivados do [Index.charter.md](Index.charter.md) e do
> [RUNBOOK-device-models.md](../../../../../memory/requisitos/Repair/RUNBOOK-device-models.md) (F4 QA)
> — não do `.tsx` ([§5 2026-06-05](../../../../../memory/proibicoes.md)).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
>
> Defendidos por [`DeviceModelsContratoTest`](../../../../../Modules/Repair/Tests/Feature/DeviceModelsContratoTest.php).
> Recibo do run: CT 100, 2026-09-05 — **14 passed, 58 assertions, 0 skipped**.

---

## UC-DMIDX-01 · Abrir a lista com um filtro já aplicado pela URL
- **Persona:** operador volta ao catálogo por um link salvo que já traz a marca filtrada.
- **Aceite:** Dado a flag `MWART_REPAIR_DEVICE_MODELS_INDEX` ligada · Quando faço
  `GET /repair/device-models?brand_id=7` · Então a tela é servida por Inertia
  (`Repair/DeviceModels/Index`) com `filters.brand_id = 7` e as listas de marcas e categorias.
- **Regressão que defende:** perder o eco do filtro faria a tela abrir "limpa" mostrando o catálogo
  inteiro, enquanto a URL diz que está filtrada — o operador confia na URL.
- **Teste:** `DeviceModelsContratoTest` — *"UC-DMIDX-01: com a flag ligada a listagem é servida por Inertia e ecoa o filtro da URL"*.
- **Status: 🧪**

## UC-DMIDX-02 · Com a migração desligada, a lista continua sendo do hub Blade
- **Persona:** ninguém — este caso protege a coexistência, que é o contrato da migração MWART.
- **Aceite:** Dado a flag desligada · Quando faço `GET /repair/device-models` · Então a resposta
  **não** é Inertia (sem header `X-Inertia`), e quem desenha a lista é o hub
  `settings/index.blade.php` via `@includeIf('repair::device_model.index')`.
- **⚠️ Achado medido (2026-09-05, CT 100):** nesse caminho o controller devolve **200 com corpo de
  zero byte**. `DeviceModelController::index()` só tem `return` no ramo ajax (DataTables) e no ramo
  Inertia; com a flag desligada e requisição não-ajax, o método termina sem retornar nada. Não há
  dano no fluxo real — o hub Blade é que renderiza a lista, e a DataTable dela chama esta rota
  **via ajax** —, mas a rota, sozinha, serve uma página em branco. `create()` e `edit()` têm
  `return view(...)`; só o `index()` não tem. Corrigir é decisão de [W]: mexer no controller sai
  do intent deste PR, que é escrever o contrato.
- **Nota sobre o que este UC NÃO prova:** ausência de header `X-Inertia` é satisfeita tanto por
  "Blade renderizou" quanto por "não veio nada". A distinção acima veio de medir o **tamanho do
  corpo**, não do assert.
- **Teste:** `DeviceModelsContratoTest` — *"UC-DMIDX-02: com a flag desligada a rota não devolve Inertia — o hub Blade segue dono da lista"*.
- **Status: 🧪**

## UC-DMIDX-03 · O canário por empresa não vaza para quem está fora dele
- **Persona:** [W] liga a tela nova para uma empresa só e precisa que as outras sigam no Blade.
- **Aceite:** Dado a flag ligada com `business_ids` contendo **outra** empresa · Quando abro a lista
  · Então recebo o caminho legado, não o Inertia.
- **Regressão que defende:** whitelist ignorada = cutover acidental para todos os tenants, que é
  exatamente o que a coexistência opt-in do RUNBOOK (F5) existe para impedir.
- **Teste:** `DeviceModelsContratoTest` — *"UC-DMIDX-03: a whitelist de business_ids exclui quem está fora e a tela não vira Inertia"*.
- **Status: 🧪**

## UC-DMIDX-04 · Filtrar por marca traz só a marca pedida
- **Persona:** operador com catálogo grande procurando os modelos de uma marca.
- **Aceite:** Dado dois modelos de marcas diferentes no meu tenant · Quando filtro por uma delas ·
  Então o payload da listagem traz **um** item, o da marca pedida.
- **Regressão que defende:** filtrar só no cliente — a tela pareceria certa enquanto o servidor
  mandaria o catálogo inteiro pela rede, e a paginação (quando entrar) contaria errado.
- **Teste:** `DeviceModelsContratoTest` — *"UC-DMIDX-04: o filtro de marca corta o payload no servidor, não só na tela"*.
- **Status: 🧪**

## UC-DMIDX-05 · Os números do topo são do meu negócio
- **Persona:** operador lê "Total de modelos" para saber o tamanho do próprio catálogo.
- **Aceite:** Dado 2 modelos meus e 1 de outra empresa · Quando a tela resolve os KPIs · Então
  `kpis.total` é **2**.
- **Regressão que defende:** KPI sem escopo de tenant conta o catálogo alheio — vazamento de
  informação de negócio por agregado, que passa despercebido porque não mostra nome nenhum.
- **Teste:** `DeviceModelsContratoTest` — *"UC-DMIDX-05: os KPIs contam só o tenant da sessão, nunca o catálogo do vizinho"*.
- **Status: 🧪**

## UC-DMIDX-06 · Modelo de outra empresa não aparece na lista (Tier 0)
- **Persona:** qualquer operador — o isolamento não depende de quem está olhando.
- **Aceite:** Dado um modelo meu e um de outra empresa · Quando abro a lista · Então vejo o meu e
  **não** vejo o alheio.
- **Regressão que defende:** perder o escopo por `business_id` no payload da listagem
  ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), Tier 0 irrevogável).
- **Controle positivo embutido:** o aceite exige que o **meu** modelo apareça, então o teste não
  pode passar por vacuidade — uma lista vazia o reprova.
- **Teste:** `DeviceModelsContratoTest` — *"UC-DMIDX-06: modelo de outro tenant não aparece na listagem (Tier 0 · ADR 0093)"*.
- **Status: 🧪**

---

## Rastreabilidade

| UC | Defendido por |
|---|---|
| 01, 02, 03, 04, 05, 06 | `Modules/Repair/Tests/Feature/DeviceModelsContratoTest.php` |

Os testes rodam no CT 100, nunca local ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)),
no tenant fictício 98 ([ADR 0358](../../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).

## Bite-test — a prova de que estes casos mordem

Verde não prova nada sozinho. Em 2026-09-05, no CT 100, um arquivo descartável espelhou os asserts
com a expectativa **invertida**: os 5 invertidos falharam e o controle positivo passou
(`5 failed, 1 passed`). Isso mostra que os asserts são alcançados e discriminam, em vez de passar
por vacuidade. O arquivo não foi commitado — é instrumento de prova, não cobertura.
