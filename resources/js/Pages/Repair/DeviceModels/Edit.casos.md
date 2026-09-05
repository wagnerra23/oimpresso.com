---
id: resources-js-pages-repair-device-models-edit-casos
casos: Editar modelo de aparelho · /repair/device-models/{id}/edit
irmaos: Edit.charter.md (lei) · Index.casos.md · Create.casos.md
tecnica: Caso de uso = narrativa do operador + criterio de aceite verificavel (Dado/Quando/Entao)
por_que: comportamento e duravel — "modelo de outra empresa da 404, nao o dado dela" vale em qualquer refactor
owner: wagner
autor: "[C] 2026-09-05"
last_run: "2026-09-05"
---

# Casos de Uso & Aceite — Editar modelo de aparelho

> Derivados do [Edit.charter.md](Edit.charter.md) e do
> [RUNBOOK-device-models.md](../../../../../memory/requisitos/Repair/RUNBOOK-device-models.md)
> — não do `.tsx` ([§5 2026-06-05](../../../../../memory/proibicoes.md)).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
>
> Defendidos por [`DeviceModelsContratoTest`](../../../../../Modules/Repair/Tests/Feature/DeviceModelsContratoTest.php).
> Recibo do run: CT 100, 2026-09-05 — **14 passed, 58 assertions, 0 skipped**.
>
> Os três casos de isolamento (02, 03, 04) cobrem as **três portas** por onde um tenant poderia
> alcançar o outro nesta tela: ler o alheio, empurrar o meu para fora, e escrever no alheio.

---

## UC-DMEDT-01 · Abrir um modelo do meu catálogo já preenchido
- **Persona:** operador corrigindo o checklist de um modelo que já atende.
- **Aceite:** Dado a flag `MWART_REPAIR_DEVICE_MODELS_EDIT` ligada e um modelo meu · Quando abro
  `/repair/device-models/{id}/edit` · Então a página `Repair/DeviceModels/Edit` vem com `model.id`
  igual ao pedido, mais as listas de marcas e categorias.
- **Regressão que defende:** formulário que abre vazio faz o operador re-digitar tudo e salvar por
  cima — perda silenciosa dos campos que ele não lembrava.
- **Teste:** `DeviceModelsContratoTest` — *"UC-DMEDT-01: com a flag ligada o formulário abre preenchido com o modelo do tenant"*.
- **Status: 🧪**

## UC-DMEDT-02 · Modelo de outra empresa não abre (Tier 0)
- **Persona:** atacante autenticado trocando o id na URL, ou um link colado de outro contexto.
- **Aceite:** Dado um modelo que pertence a outra empresa · Quando tento abrir a edição dele ·
  Então recebo **404** — nunca o dado dela.
- **Regressão que defende:** trocar o `findOrFail` escopado por `find` global entregaria o catálogo
  de outro tenant pela URL
  ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), Tier 0 irrevogável).
  O charter já promete este 404; aqui ele passa a ser exigido por máquina.
- **Teste:** `DeviceModelsContratoTest` — *"UC-DMEDT-02: abrir modelo de outro tenant devolve 404, não o dado do vizinho (Tier 0)"*.
- **Status: 🧪**

## UC-DMEDT-03 · Salvar não muda o dono do registro (Tier 0)
- **Persona:** payload adulterado, ou um campo `business_id` que vaze do formulário por engano.
- **Aceite:** Dado um modelo meu · Quando salvo enviando `business_id` de **outra** empresa · Então o
  registro continua na **minha**.
- **Regressão que defende:** aceitar `business_id` no `update()` deixaria mover registros entre
  tenants — o dado some do catálogo de quem o criou e aparece no de outro, sem rastro na tela.
- **Teste:** `DeviceModelsContratoTest` — *"UC-DMEDT-03: salvar a edição não move o registro para outro tenant (Tier 0)"*.
- **Status: 🧪**

## UC-DMEDT-04 · Não consigo alterar o modelo de outra empresa (Tier 0)
- **Persona:** atacante autenticado que já sabe que o `GET` dá 404 e tenta o `PUT` direto.
- **Aceite:** Dado um modelo de outra empresa · Quando envio um `PUT` renomeando-o · Então o nome
  dele permanece **exatamente** o que era.
- **Regressão que defende:** proteger só a leitura e esquecer a escrita — o `update()` tem o próprio
  `findOrFail` escopado, e este caso existe para que ele não se perca num refactor que "simplifique"
  o método confiando na proteção do `edit()`.
- **Teste:** `DeviceModelsContratoTest` — *"UC-DMEDT-04: atualizar modelo de outro tenant não altera o dado do vizinho (Tier 0)"*.
- **Status: 🧪**

---

## Rastreabilidade

| UC | Defendido por |
|---|---|
| 01, 02, 03, 04 | `Modules/Repair/Tests/Feature/DeviceModelsContratoTest.php` |

Os testes rodam no CT 100, nunca local ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)),
no tenant fictício 98 ([ADR 0358](../../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
