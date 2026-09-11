---
id: resources-js-pages-repair-device-models-create-casos
casos: Novo modelo de aparelho · /repair/device-models/create
irmaos: Create.charter.md (lei) · Index.casos.md · Edit.casos.md
tecnica: Caso de uso = narrativa do operador + criterio de aceite verificavel (Dado/Quando/Entao)
por_que: comportamento e duravel — "o registro nasce no meu tenant, nao no que o payload pedir" vale em qualquer refactor
owner: wagner
autor: "[C] 2026-09-05"
last_run: "2026-09-05"
---

# Casos de Uso & Aceite — Novo modelo de aparelho

> Derivados do [Create.charter.md](Create.charter.md) e do
> [RUNBOOK-device-models.md](../../../../../memory/requisitos/Repair/RUNBOOK-device-models.md)
> — não do `.tsx` ([§5 2026-06-05](../../../../../memory/proibicoes.md)).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
>
> Defendidos por [`DeviceModelsContratoTest`](../../../../../Modules/Repair/Tests/Feature/DeviceModelsContratoTest.php).
> Recibo do run: CT 100, 2026-09-05 — **14 passed, 58 assertions, 0 skipped**.

---

## UC-DMCRE-01 · Abrir o formulário de cadastro
- **Persona:** operador vai cadastrar um modelo novo que a oficina passou a atender.
- **Aceite:** Dado a flag `MWART_REPAIR_DEVICE_MODELS_CREATE` ligada · Quando faço
  `GET /repair/device-models/create` · Então a página `Repair/DeviceModels/Create` é servida com as
  listas de marcas e de categorias do meu negócio.
- **Regressão que defende:** formulário sem as duas listas obriga o operador a salvar sem marca ou
  categoria, e o catálogo perde justamente os campos pelos quais depois se filtra (UC-DMIDX-04).
- **Teste:** `DeviceModelsContratoTest` — *"UC-DMCRE-01: com a flag ligada o formulário é servido por Inertia com marcas e categorias"*.
- **Status: 🧪**

## UC-DMCRE-02 · Nenhuma opção de select chega sem valor
- **Persona:** ninguém — este caso existe porque a falha é **catastrófica e silenciosa na origem**.
- **Aceite:** Dado marcas e categorias cadastradas · Quando abro o formulário · Então **nenhuma**
  chave das opções de marca ou de categoria é string vazia.
- **Regressão que defende:** um `<SelectItem value="">` derruba a árvore React inteira — a tela some,
  não degrada. É a lápide de [§5 2026-06-29](../../../../../memory/proibicoes.md): o valor vazio veio
  de dado, não de código, então nenhum teste de componente pega. Aqui a guarda fica do lado do
  **dado**, antes de virar tela.
- **Cuidado de arranjo:** o aceite exige que as listas venham **não-vazias**. Sem isso o teste passaria
  por vacuidade — um array vazio não contém chave vazia.
- **Teste:** `DeviceModelsContratoTest` — *"UC-DMCRE-02: as opções dos selects nunca chegam com chave vazia"*.
- **Status: 🧪**

## UC-DMCRE-03 · O modelo nasce na minha empresa, não na que o formulário pedir (Tier 0)
- **Persona:** atacante autenticado, ou um payload adulterado por engano.
- **Aceite:** Dado que envio `business_id` de **outra** empresa junto do cadastro · Quando salvo ·
  Então o registro nasce com o `business_id` **da minha sessão**.
- **Regressão que defende:** o controller monta o `business_id` a partir da sessão e o sobrescreve no
  input; passar a confiar no payload permitiria semear catálogo dentro de outro tenant
  ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), Tier 0 irrevogável).
- **Teste:** `DeviceModelsContratoTest` — *"UC-DMCRE-03: o novo modelo nasce no tenant da sessão mesmo quando o payload pede outro (Tier 0)"*.
- **Status: 🧪**

## UC-DMCRE-04 · Salvar grava, mas a página não recebe resposta que a faça navegar
- **Persona:** operador que clica "Salvar" e fica olhando o formulário parado.
- **Aceite (comportamento vigente, medido):** Dado o formulário preenchido · Quando salvo · Então o
  registro **é gravado** e a resposta é `200` com `{"success":true}` em JSON — **sem** redirect
  (`Location` nulo) e **sem** header `X-Inertia`.
- **⚠️ Isto contradiz o RUNBOOK.** O risco R1 do
  [RUNBOOK-device-models.md](../../../../../memory/requisitos/Repair/RUNBOOK-device-models.md) afirma:
  *"Inertia branch usa redirect padrão Laravel via `useForm.post()`"*. Não usa — `store()` devolve o
  mesmo array nos dois caminhos, sem nenhum ramo de flag. Como o adapter Inertia só sabe seguir 3xx
  ou consumir resposta Inertia, a página fica parada: **salva no banco e nada muda na tela**, sem
  erro visível. É a classe [LC-30](../../../../../memory/LICOES_CODE.md) — verde no CI, inerte no runtime.
- **Por que o teste fixa o comportamento vigente em vez de exigir o prometido:** um vermelho aqui
  quebraria a catraca da lane `verticais-pest`, e consertar o `store()` seria conserto silencioso de
  contrato. **Decisão de [W]:** corrigir o controller (passar a redirecionar) **ou** corrigir o R1 do
  RUNBOOK. Se o controller mudar, este teste quebra de propósito — atualize este UC e o R1 no mesmo PR.
- **Teste:** `DeviceModelsContratoTest` — *"UC-DMCRE-04: salvar grava no banco mas responde JSON cru — a página não recebe redirect"*.
- **Status: 🧪** _(prova o comportamento vigente; a divergência com o RUNBOOK está aberta para [W])_

---

## Rastreabilidade

| UC | Defendido por |
|---|---|
| 01, 02, 03, 04 | `Modules/Repair/Tests/Feature/DeviceModelsContratoTest.php` |

Os testes rodam no CT 100, nunca local ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)),
no tenant fictício 98 ([ADR 0358](../../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
