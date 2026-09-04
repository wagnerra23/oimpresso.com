---
id: resources-js-pages-repair-settings-index-casos
casos: Configurações do Repair · /repair/repair-settings
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o contrato de gravação é durável — "são duas colunas disjuntas" e "submit parcial apaga" valem em qualquer refactor da tela
owner: wagner
autor: "[C] 2026-09-04"
last_run: "2026-09-04"
---

# Casos de Uso & Aceite — Configurações do Repair

> Derivados do contrato de gravação medido em `RepairSettingsController` e do
> [RUNBOOK-repair-settings.md](../../../../memory/requisitos/Repair/RUNBOOK-repair-settings.md) —
> **não do `.tsx`**. A Page é consumidora do contrato, não a fonte dele.
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
>
> Suíte: `Modules/Repair/Tests/Feature/RepairSettingsContratoTest.php` · lane **Modules Pest** ·
> tenant **98** ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).

---

## UC-RSET-01 · Os padrões da folha são gravados

- **Persona:** admin do negócio ajustando como toda nova folha de OS nasce.
- **Aceite:** Dado um admin com permissão · Quando salva a seção "Folha de OS" · Então prefixo,
  tipo de código de barras, os textos longos, o checklist e os 5 campos personalizados ficam em
  `business.repair_settings`, e o valor lido de volta bate campo a campo — inclusive o
  `job_sheet_custom_field_5`, que fica no fim do conjunto.
- **Status: ⬜** _(teste existe e cita o UC; ainda não rodou — Pest só no CT 100)_

---

## UC-RSET-02 · Salvar a folha não mexe na impressão

- **Persona:** o mesmo admin, que não deveria perder a etiqueta ao mexer na folha.
- **Aceite:** Dado que `business.repair_jobsheet_settings` já tem rótulo e chaves `show_*` ·
  Quando um submit para `POST /repair/repair-settings` inclui — por engano — campos de impressão ·
  Então a coluna da impressão fica **intacta** e só a da folha muda.
- **Por que existe:** é o erro que o pacote de export induzia ao afirmar que havia um endpoint só.
  Uma Page que mandasse a impressão para o `store()` salvaria sem erro e não persistiria nada —
  tela inerte, e nenhum gate estático pegaria.
- **Status: ⬜** _(teste existe e cita o UC; ainda não rodou — Pest só no CT 100)_

---

## UC-RSET-03 · Submit parcial apaga o que não foi enviado

- **Persona:** qualquer um que salve o formulário sem que a UI tenha reenviado tudo.
- **Aceite:** Dado um `repair_settings` com `job_sheet_custom_field_2` preenchido · Quando chega
  um submit que não inclui essa chave · Então ela **some** do JSON gravado.
- **Este UC descreve o contrato vigente, não o desejado:** os dois métodos fazem
  `$request->only([...])` seguido de `Business::update([... => json_encode($input)])` e substituem
  o documento inteiro. A consequência para a UI está no charter como anti-hook — cada formulário
  envia o conjunto completo do seu endpoint a cada submit.
- **Status: ⬜** _(teste existe e cita o UC; ainda não rodou — Pest só no CT 100)_

---

## UC-RSET-04 · Sem permissão, nada é gravado

- **Persona:** usuário do negócio sem `repair.create` tentando alterar configuração.
- **Aceite:** Dado um usuário sem `superadmin` e sem `repair.create` · Quando ele posta em
  qualquer um dos dois endpoints · Então a operação é negada e **as duas** colunas mantêm o valor
  anterior.
- A autoridade é o Controller; a Page apenas reflete. Desabilitar o botão na UI não é a defesa.
- **Status: ⬜** _(teste existe e cita o UC; ainda não rodou — Pest só no CT 100)_

---

## UC-RSET-05 · Tier 0 — a gravação não vaza para outro business

- **Persona:** qualquer tenant vizinho no mesmo banco.
- **Aceite:** Dado o tenant canônico de teste · Quando ele grava as duas colunas · Então as
  configurações de **todos** os demais businesses ficam byte a byte iguais ao snapshot anterior.
- O caso compara o conjunto inteiro dos outros tenants (não um adversário escolhido a dedo), para
  que o isolamento seja medido sobre a população real do banco.
- **Status: ⬜** _(teste existe e cita o UC; ainda não rodou — Pest só no CT 100)_

---

## UC-RSET-06 · Salvar a impressão não mexe na folha

- **Persona:** admin ajustando a etiqueta sem querer perder os padrões da folha.
- **Aceite:** Dado um `repair_settings` com prefixo gravado · Quando um submit vai para
  `POST /repair/update-repair-jobsheet-settings` com rótulos, dimensões da etiqueta e chaves
  `show_*` · Então a coluna da impressão recebe os valores e `repair_settings` fica **intacta**.
- É a metade simétrica do UC-RSET-02. Os dois juntos travam a disjunção nos dois sentidos — é isso
  que impede a próxima sessão de "simplificar" a tela para um formulário só.
- **Status: ⬜** _(teste existe e cita o UC; ainda não rodou — Pest só no CT 100)_

---

## UC-RSET-07 · Flag desligada mantém a tela antiga

- **Persona:** quem já usa a tela hoje e não pediu mudança nenhuma.
- **Aceite:** Dado que `mwart.repair_settings_index` está OFF (o default) · Quando o admin abre
  `/repair/repair-settings` · Então ele recebe o Blade legado, sem cabeçalho `X-Inertia`.
- Enquanto [W] não ligar a flag, o merge deste código **não muda nada** para quem usa a tela.
- **Status: ⬜** _(teste existe e cita o UC; ainda não rodou — Pest só no CT 100)_

---

## UC-RSET-08 · Flag ligada renderiza a tela nova com o contrato completo

- **Persona:** [W] ligando a flag depois de ver o screenshot.
- **Aceite:** Dado que a flag está ON para o business · Quando o admin abre a mesma rota · Então o
  Inertia renderiza `Repair/Settings/Index` com `repairSettings`, `jobsheetPdfSettings` e — as duas
  que o Blade **nunca** recebeu — `contactCustomFields` e `customLabels`.
- **Status: ⬜** _(teste existe e cita o UC; ainda não rodou — Pest só no CT 100)_

---

## [BACKLOG] · ainda sem teste (prosa honesta, não conta como coberto)

- [BACKLOG] Confirmar em render real se a aba de impressão do Blade legado está quebrada hoje
  (`$contact_custom_fields` é dereferenciada em `jobsheet_settings_tab.blade.php:56` e o
  `compact()` do `index()` não a passa; varredura contada não achou `View::share`). Se estiver,
  a migração conserta um erro vivo e isso precisa ser declarado, não silencioso.
- [BACKLOG] Smoke real autenticado em prod, dark, 1280px, com screenshot — pré-requisito de
  `status: live` no charter e de a flag ser ligada.
