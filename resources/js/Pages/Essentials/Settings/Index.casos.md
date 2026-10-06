---
id: resources-js-pages-essentials-settings-index-casos
casos: Essentials · Configurações do módulo · /hrm/settings
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
last_run: "2026-10-06"
---

# Casos de uso — /hrm/settings · Configurações do Essentials

> **Status:** ✅ passa (provado no manifesto G-7) · 🧪 teste cita o UC, sem veredito ainda ·
> ⬜ não verificado · ❌ quebrou.

> Os UC derivam da **US-ESS-014** (`memory/requisitos/Essentials/SPEC.md`: *"Grava em
> `business.essentials_settings` (JSON) do próprio business, e só o admin vê e edita"*) e do
> [`Index.charter.md`](Index.charter.md) §Goals/§Non-Goals, conferidos no
> `EssentialsSettingsController` (`edit`, `update`). **Nunca** do `.tsx` nem do protótipo
> (§5 2026-06-05).

> ⚖️ **Onde roda.** Teste: [`Modules/Essentials/Tests/Feature/SettingsIndexContratoTest.php`](../../../../../Modules/Essentials/Tests/Feature/SettingsIndexContratoTest.php),
> MySQL-only (pula no SQLite), na allowlist da lane `PHP / Pest (Essentials · MySQL)`
> (`.github/workflows/essentials-pest.yml`). Tenant 98 × adversário 99, nunca biz=4.
> A parte das 5 chaves de presença aposentadas já é defendida por
> `HrmPresencaCedeAoPontoTest.php` (UC-HRM-PRES-05) e não se repete aqui.

---

## UC-ESET-01 · O admin abre a tela e recebe as 5 chaves vivas, e só elas `[must]`
Status: 🧪 sem veredito
- **Persona:** administrador do business — confere os prefixos e as instruções antes de mudar.
- **Aceite:** Dado um admin do meu tenant · Quando abre `/hrm/settings` · Então a resposta é 200 com o component `Essentials/Settings/Index`, e `settings` traz exatamente `leave_ref_no_prefix`, `leave_instructions`, `payroll_ref_no_prefix`, `essentials_todos_prefix` e `calculate_sales_target_commission_without_tax` (booleano).
- **Teste:** `Modules/Essentials/Tests/Feature/SettingsIndexContratoTest.php` — `UC-ESET-01 · o admin abre a tela …`
- **Regressão que defende:** charter §Goals (os 5 campos). Chave a mais no payload é chave que alguém volta a ler; é o controle positivo do UC-ESET-03.

## UC-ESET-02 · Salvar grava no JSON do meu business e não toca o de outro `[must]` `[T0]`
Status: 🧪 sem veredito
- **Persona:** administrador — muda o prefixo das tarefas da empresa dele.
- **Aceite:** Dado o JSON `essentials_settings` do outro business guardado antes · Quando eu salvo um prefixo novo com "meta sem impostos" ligado · Então o meu JSON tem o prefixo e a flag gravada como `1`, e o do outro business segue igual.
- **Teste:** `Modules/Essentials/Tests/Feature/SettingsIndexContratoTest.php` — `UC-ESET-02 · salvar grava no JSON …`
- **Regressão que defende:** charter §Non-Goals *"NÃO altera settings de outro business"* (ADR 0093). A flag como `1` é o formato que o código legado lê (`? 1 : 0`).

## UC-ESET-03 · Quem não é admin recebe 403 ao abrir e ao salvar `[must]`
Status: 🧪 sem veredito
- **Persona:** colaborador sem papel de admin — não deve mudar a regra da empresa.
- **Aceite:** Dado um usuário do meu tenant sem `Admin#` · Quando abre `/hrm/settings` e quando tenta salvar · Então as duas respostas são 403 e o JSON do business não muda.
- **Teste:** `Modules/Essentials/Tests/Feature/SettingsIndexContratoTest.php` — `UC-ESET-03 · quem não é admin …`
- **Regressão que defende:** charter §Non-Goals *"NÃO é acessível a não-admin"*. O UC-ESET-01 passa pelo mesmo gate de assinatura, então o 403 aqui vem do teste de admin.

## UC-ESET-04 · Prefixo acima de 32 caracteres é recusado `[should]`
Status: 🧪 sem veredito
- **Persona:** administrador — cola um texto longo no campo de prefixo por engano.
- **Aceite:** Dado um admin · Quando salva `essentials_todos_prefix` com 33 caracteres · Então volta com erro de validação nesse campo e o JSON não muda.
- **Teste:** `Modules/Essentials/Tests/Feature/SettingsIndexContratoTest.php` — `UC-ESET-04 · prefixo acima …`
- **Regressão que defende:** o prefixo entra no `task_id` gerado em `ToDoController@store` (charter §Goals); sem o limite, o código da tarefa cresce sem teto.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Depois de salvar, uma tarefa nova já sai com o prefixo novo sem novo login (charter §Automation hooks: a sessão é atualizada no `update`).
- [BACKLOG] Mudar o prefixo não renumera tarefas antigas (charter §Anti-hooks).
