---
casos: Manufacturing/Settings — configurações do módulo
irmaos: Settings.charter.md (lei) · memory/requisitos/Manufacturing/RUNBOOK-settings.md (F1)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
fonte: handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" §4.7 + §16 — os UC abaixo DERIVAM dele
owner: wagner
last_run: "2026-09-03"
---

# Casos de Uso & Aceite — Manufacturing/Settings

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> Os casos abaixo **não foram derivados do `.tsx`** (§5 tautológico das proibições). Cada um
> aponta o requisito do handoff normativo de onde saiu.

---

## UC-CFG-01 · A rota `/manufacturing/v2/settings` serve a tela nova
- **Persona:** Wagner — chega pela aba "Configurações" das telas v2.
- **Aceite:** Dado o app carregado · Quando se pergunta ao **registry de rotas** quem serve
  `GET manufacturing/v2/settings` · Então existe rota e a ação é o `SettingsController`.
- **Fonte:** §4.7 do handoff + decisão de rota aditiva (`RUNBOOK-settings.md`).
- **Teste:** `Wave31SettingsInertiaTest.php`
- **Regressão que defende:** alguém remover a rota nova achando que duplica o Blade.
- **Status: 🧪**

---

## UC-CFG-02 · A escrita continua no endpoint legado — `store()` NÃO muda
- **Persona:** Wagner — a tela Blade antiga e a nova postam no MESMO lugar.
- **Aceite:** Dado o `SettingsController` · Quando se lê o `store()` · Então ele segue lendo
  exatamente as 3 chaves (`ref_no_prefix`, `disable_editing_ingredient_qty`,
  `enable_updating_product_price`), escrevendo com `where('id', $business_id)` e devolvendo
  `redirect()->back()`.
- **Fonte:** decisão registrada no charter (Non-Goal "não cria endpoint de escrita novo").
- **Teste:** `Wave31SettingsInertiaTest.php`
- **Regressão que defende:** "melhorar" o `store()` pro front novo e quebrar a tela Blade
  legada, que posta no mesmo endpoint — é a classe LC-15/LC-30 (mudar o mecanismo comum
  achando que só o consumidor novo depende dele).
- **Status: 🧪**

---

## UC-CFG-03 · Business sem configuração salva NÃO quebra a tela
- **Persona:** business novo — `manufacturing_settings` nunca foi gravado.
- **Aceite:** Dado `ManufacturingUtil::getSettings()` devolvendo `[]` · Quando o controller
  monta o payload · Então `ref_no_prefix` vira `''` e as duas travas viram `false` — nunca
  `undefined` chegando ao React.
- **Fonte:** §16 do handoff (chaves reais) + comportamento medido de `getSettings()`, que
  devolve `[]` sem defaults.
- **Teste:** `Wave31SettingsInertiaTest.php`
- **Regressão que defende:** tela branca / `undefined` no `value` do input (input controlado
  do React vira não-controlado e o console grita) num business que nunca salvou config.
- **Status: 🧪**

---

## UC-CFG-04 · Escrita scoped por business — nunca escreve no business errado
- **Persona:** qualquer tenant — Tier 0.
- **Aceite:** Dado o `store()` · Quando ele grava · Então o update é
  `Business::where('id', $business_id)->update(...)` — nunca um update sem cláusula de tenant.
- **Fonte:** [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) +
  SPEC.md DoD ("Escrita scoped por business_id").
- **Teste:** `Wave31SettingsInertiaTest.php`
- **Regressão que defende:** alguém trocar por `Business::update(...)` em massa (o Eloquent
  permite) e reconfigurar o módulo de TODOS os tenants de uma vez.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** O botão "Atualizar" fica desabilitado enquanto nada mudou e reabilita ao mexer
  em qualquer um dos 3 campos (R-24) — é comportamento de navegador; lugar é spec Playwright.
- **[BACKLOG]** Submeter persiste e a tela volta com os valores novos (o `redirect()->back()`
  do Inertia re-busca as props). Precisa de fixture autenticada + write real — hoje a suíte
  do módulo não tem; a prova é o smoke do `RUNBOOK-settings.md §3`.
- **[BACKLOG]** O rodapé mostra a versão vinda de `System::getProperty('manufacturing_version')`.

## Trilha do tempo
- 2026-09-03 · [F+C] US-MANU-003, terceira onda da família. 4 UC com teste Pest.
  Refs: UI-0013 · ADR 0264 G-1/G-2 · ADR 0104 · ADR 0093.
- 2026-09-04 · [F+C] Barra de abas corrigida: "Configurações" apontava pra rota Blade legada
  (âncora crua, saía do SPA) e a aba "Insumos" não existia. [F] reportou clicando na aba e
  caindo na tela antiga. **Segunda ocorrência do mesmo defeito** — em 2026-09-03 a aba
  "Relatório" foi corrigida do mesmo jeito e a "Configurações" ficou pra trás na mesma leva,
  porque **nenhum UC cobre a barra de navegação** e nada guardava isso. A guarda agora existe:
  `Modules/Manufacturing/Tests/Feature/AbasTelasV2Test.php` (4 asserts; 3 provados por bite
  test contra cópia adulterada, o 4º usa o registry de rotas em runtime). Nenhum UC acima
  mudou de comportamento. ⚠️ O cutover da rota legada segue PENDENTE e é decisão [W].
