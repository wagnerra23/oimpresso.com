---
id: resources-js-pages-repair-settings-index-casos
casos: Configurações do Repair · /repair/repair-settings
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o contrato de gravação é durável — "são duas colunas disjuntas" e "submit parcial apaga" valem em qualquer refactor da tela
owner: wagner
autor: "[C] 2026-09-04"
last_run: "2026-09-05"
---

# Casos de Uso & Aceite — Configurações do Repair

> Derivados do contrato de gravação medido em `RepairSettingsController` e do
> [RUNBOOK-repair-settings.md](../../../../../memory/requisitos/Repair/RUNBOOK-repair-settings.md) —
> **não do `.tsx`**. A Page é consumidora do contrato, não a fonte dele.
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
>
> Suíte: `Modules/Repair/Tests/Feature/RepairSettingsContratoTest.php` ·
> tenant **98** ([ADR 0358](../../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
>
> **Onde ela roda de verdade** — corrigido em 2026-09-05, a redação anterior dizia só "lane Modules
> Pest" e isso a fazia parecer coberta quando não estava:
>
> | lane | driver | o que acontece |
> |---|---|---|
> | `modules-pest` (job *Pest Repair*) | **sqlite** | os 8 UCs **pulam** no 1º guard do `beforeEach` (o schema de `business` exige MySQL) e o job dá `success` — falso-verde (LC-13). Verificado no run `33938642020`: `WARN`, 73 skipped / 83 passed no job. |
> | `verticais-pest` (*Verticais · MySQL*) | **mysql** | é a lane com MySQL real — mas roda uma **allowlist explícita** de arquivos, e este não estava nela. |
>
> Ou seja: até 2026-09-05 o contrato **não era exercido por lane nenhuma**. O arquivo foi incluído
> na allowlist do `verticais-pest` no mesmo PR desta medição.

---

## UC-RSET-01 · Os padrões da folha são gravados

- **Persona:** admin do negócio ajustando como toda nova folha de OS nasce.
- **Aceite:** Dado um admin com permissão · Quando salva a seção "Folha de OS" · Então prefixo,
  tipo de código de barras, os textos longos, o checklist e os 5 campos personalizados ficam em
  `business.repair_settings`, e o valor lido de volta bate campo a campo — inclusive o
  `job_sheet_custom_field_5`, que fica no fim do conjunto.
- **Status: ✅** _(`pass` no manifesto — lane `verticais-pest`, [run 33969895224](https://github.com/wagnerra23/oimpresso.com/actions/runs/33969895224), MySQL real, 2026-09-05)_

---

## UC-RSET-02 · Salvar a folha não mexe na impressão

- **Persona:** o mesmo admin, que não deveria perder a etiqueta ao mexer na folha.
- **Aceite:** Dado que `business.repair_jobsheet_settings` já tem rótulo e chaves `show_*` ·
  Quando um submit para `POST /repair/repair-settings` inclui — por engano — campos de impressão ·
  Então a coluna da impressão fica **intacta** e só a da folha muda.
- **Por que existe:** é o erro que o pacote de export induzia ao afirmar que havia um endpoint só.
  Uma Page que mandasse a impressão para o `store()` salvaria sem erro e não persistiria nada —
  tela inerte, e nenhum gate estático pegaria.
- **Status: ✅** _(`pass` no manifesto — lane `verticais-pest`, [run 33969895224](https://github.com/wagnerra23/oimpresso.com/actions/runs/33969895224), MySQL real, 2026-09-05)_

---

## UC-RSET-03 · Submit parcial apaga o que não foi enviado

- **Persona:** qualquer um que salve o formulário sem que a UI tenha reenviado tudo.
- **Aceite:** Dado um `repair_settings` com `job_sheet_custom_field_2` preenchido · Quando chega
  um submit que não inclui essa chave · Então ela **some** do JSON gravado.
- **Este UC descreve o contrato vigente, não o desejado:** os dois métodos fazem
  `$request->only([...])` seguido de `Business::update([... => json_encode($input)])` e substituem
  o documento inteiro. A consequência para a UI está no charter como anti-hook — cada formulário
  envia o conjunto completo do seu endpoint a cada submit.
- **Status: ✅** _(`pass` no manifesto — lane `verticais-pest`, [run 33969895224](https://github.com/wagnerra23/oimpresso.com/actions/runs/33969895224), MySQL real, 2026-09-05)_

---

## UC-RSET-04 · Sem permissão, nada é gravado

- **Persona:** usuário do negócio sem `repair.create` tentando alterar configuração.
- **Aceite:** Dado um usuário sem `superadmin` e sem `repair.create` · Quando ele posta em
  qualquer um dos dois endpoints · Então a operação é negada e **as duas** colunas mantêm o valor
  anterior.
- A autoridade é o Controller; a Page apenas reflete. Desabilitar o botão na UI não é a defesa.
- **Status: ✅** _(`pass` no manifesto — lane `verticais-pest`, [run 33969895224](https://github.com/wagnerra23/oimpresso.com/actions/runs/33969895224), MySQL real, 2026-09-05)_

---

## UC-RSET-05 · Tier 0 — a gravação não vaza para outro business

- **Persona:** qualquer tenant vizinho no mesmo banco.
- **Aceite:** Dado o tenant canônico de teste · Quando ele grava as duas colunas · Então as
  configurações de **todos** os demais businesses ficam byte a byte iguais ao snapshot anterior.
- O caso compara o conjunto inteiro dos outros tenants (não um adversário escolhido a dedo), para
  que o isolamento seja medido sobre a população real do banco.
- **Status: ✅** _(`pass` no manifesto — lane `verticais-pest`, [run 33969895224](https://github.com/wagnerra23/oimpresso.com/actions/runs/33969895224), MySQL real, 2026-09-05)_

---

## UC-RSET-06 · Salvar a impressão não mexe na folha

- **Persona:** admin ajustando a etiqueta sem querer perder os padrões da folha.
- **Aceite:** Dado um `repair_settings` com prefixo gravado · Quando um submit vai para
  `POST /repair/update-repair-jobsheet-settings` com rótulos, dimensões da etiqueta e chaves
  `show_*` · Então a coluna da impressão recebe os valores e `repair_settings` fica **intacta**.
- É a metade simétrica do UC-RSET-02. Os dois juntos travam a disjunção nos dois sentidos — é isso
  que impede a próxima sessão de "simplificar" a tela para um formulário só.
- **Status: ✅** _(`pass` no manifesto — lane `verticais-pest`, [run 33969895224](https://github.com/wagnerra23/oimpresso.com/actions/runs/33969895224), MySQL real, 2026-09-05)_

---

## UC-RSET-07 · Flag desligada mantém a tela antiga

- **Persona:** quem já usa a tela hoje e não pediu mudança nenhuma.
- **Aceite:** Dado que `mwart.repair_settings_index` está OFF (o default) · Quando o admin abre
  `/repair/repair-settings` · Então ele recebe o Blade legado, sem cabeçalho `X-Inertia`.
- Enquanto [W] não ligar a flag, o merge deste código **não muda nada** para quem usa a tela.
- **Status: ✅** _(`pass` no manifesto — lane `verticais-pest`, [run 33969895224](https://github.com/wagnerra23/oimpresso.com/actions/runs/33969895224), MySQL real, 2026-09-05)_
- **Como o `⬜` virou `✅` (história, não estado atual):** este UC e o UC-RSET-08 são os únicos que
  passam por `index()`, e `index()` chama `ModuleUtil::getTaxonomyData('device')` (linha 80), que
  termina em `exit` quando o módulo não consta instalado (`app/Utils/ModuleUtil.php:549-551`).
  `exit` não é exception: mata o processo e **derrubava a suíte inteira sem uma linha de output**
  (medido em 2026-09-05: `rc=2`, stdout e stderr com 0 byte). O gatilho era o seed —
  `isModuleInstalled('Repair')` lê `system.repair_version`, e o `pest-mysql-setup` não escrevia
  nessa tabela. O [#6843](https://github.com/wagnerra23/oimpresso.com/pull/6843) semeou a linha e o
  run acima registra `seed system.repair_version` antes de o caso passar em 0.98s. O guard
  `repairSettingsPrecisaDoModuloInstalado()` continua no teste: ele é a rede que troca morte muda
  por skip visível se o seed regredir.

---

## UC-RSET-08 · Flag ligada renderiza a tela nova com o contrato completo

- **Persona:** [W] ligando a flag depois de ver o screenshot.
- **Aceite:** Dado que a flag está ON para o business · Quando o admin abre a mesma rota · Então o
  Inertia renderiza `Repair/Settings/Index` com `repairSettings`, `jobsheetPdfSettings` e — as duas
  que o Blade **nunca recebeu como prop** — `contactCustomFields` e `customLabels`.
- ⚠️ **Precisão (2026-09-05):** "nunca recebeu como prop" é verdade e continua sendo o motivo de o
  branch Inertia passar as duas — React não tem o `@php` do Blade. O que **não** é verdade é a
  leitura de que o Blade estivesse quebrado por isso; ver o item de BACKLOG resolvido abaixo.
- **Status: ✅** _(`pass` no manifesto — lane `verticais-pest`, [run 33969895224](https://github.com/wagnerra23/oimpresso.com/actions/runs/33969895224), MySQL real, 2026-09-05)_
- **Mesma história do UC-RSET-07** (o `exit` de `getTaxonomyData`, disparado por
  `system.repair_version` ausente no seed até o [#6843](https://github.com/wagnerra23/oimpresso.com/pull/6843)).
  Este é o UC que prova o render de verdade, e o assert **morde**: em 2026-09-05 ele começou
  falhando com `Inertia page component file [Repair/Settings/Index] does not exist` enquanto o
  `.tsx` não estava no lugar — não é carimbo.
- ⚠️ **Duas correções que este UC exigiu, medidas no mesmo dia — a redação anterior nunca teria
  passado:** o teste enviava `X-Inertia-Version: 'test'` e recebia **409** (conflito de versão de
  asset); mandar a versão REAL também dá 409, porque nesta lane ela é string vazia. O caminho que
  devolve 200 é o GET normal, sem simular XHR — `assertInertia` lê o `data-page` da root view.

---

## RESOLVIDO em 2026-09-05 · a aba de impressão do Blade **NÃO está quebrada** — premissa refutada

O BACKLOG anterior pedia confirmar em render real se `jobsheet_settings_tab.blade.php:56`
estourava por `$contact_custom_fields` indefinida. **Renderizado no CT 100 (MySQL real): o partial
renderiza, 9143 bytes, com o checkbox `custom_field1` presente e nenhum warning sobre a variável**
(16 warnings capturados, todos deprecations alheias — `TransactionUtil`, Woocommerce, Console).

A causa da premissa errada é instrutiva e vale mais que o resultado: **a variável é definida pelo
próprio partial**, na linha 4, 52 linhas ACIMA do uso —

```php
@php
$custom_labels = json_decode(session('business.custom_labels'), true);
$contact_custom_fields = !empty($jobsheet_pdf_settings['contact_custom_fields']) ? $jobsheet_pdf_settings['contact_custom_fields'] : [];
@endphp
```

A varredura que sustentava a hipótese procurou `View::share` em `.php` **fora de views** e nunca
abriu o arquivo acusado até o fim. O partial é auto-suficiente: deriva as duas de
`$jobsheet_pdf_settings`, que o `compact()` **passa**. Provas independentes de que não é sorte de
cache: `view:clear` antes do render; o path resolvido pelo finder é o mesmo arquivo (md5
`a86a7a83…`, sem override em `custom_views/`); a mesma linha 56 **isolada** num blade sem o `@php`
lança `ViewException: Undefined variable` — ou seja, o guard real é a linha 4.

**Consequência para o PR:** a migração **não** conserta um erro vivo aqui, e não há mudança de
comportamento a declarar por este motivo. Passar `contactCustomFields`/`customLabels` como props
segue **necessário** — React não tem o `@php` do Blade —, mas a justificativa é essa, não "o
legado está quebrado". O RUNBOOK §4(b), o charter e o comentário do Controller foram corrigidos no
mesmo PR (append-only: a afirmação antiga fica, com errata datada).

## [BACKLOG] · ainda sem teste (prosa honesta, não conta como coberto)

- [BACKLOG] Smoke real autenticado, dark, 1280px, com screenshot — pré-requisito de `status: live`
  no charter e de a flag ser ligada. **Não executado em 2026-09-05, e a razão é medida:** o
  staging (`staging.oimpresso.com`, HTTP 200) **não tem assets buildados** (`public/build/manifest.json`
  ausente), logo não renderiza Inertia; o container não tem `node`/`npm` no PATH; e seu checkout
  está **432 commits atrás** (`c1abe9548`, 2026-08-26) com trabalho não-commitado de outra sessão
  em Fiscal/NfeBrasil/Ponto — buildar ali produziria um retrato de código de agosto + os arquivos
  desta onda, que não representa nem produção nem o `main`. Um screenshot assim seria pior que
  nenhum: teria cara de prova e mediria outra coisa.
- ~~[BACKLOG] Semear `system.repair_version` no `pest-mysql-setup` para destravar UC-RSET-07/08 no
  CI.~~ **FEITO** no [#6843](https://github.com/wagnerra23/oimpresso.com/pull/6843) (PR próprio, como
  previsto — o seed é compartilhado por 16 lanes). Os dois UCs passaram no
  [run 33969895224](https://github.com/wagnerra23/oimpresso.com/actions/runs/33969895224).
- [BACKLOG] O teste `UC-PAC-08` (`Modules/Superadmin/Tests/Feature/SuperadminPacotesContratoTest.php:325`)
  usa prefixo divergente dos irmãos de Pacotes, que são `UC-SAPAC-*`, e **nenhum `casos.md` declara
  esse id** — varredura contada no repo inteiro: 1 site, o próprio teste. Ele já rodava verde e só
  ficou visível quando a lane passou a ser colhida; é teste sem contrato, não regressão. Renomear
  para `UC-SAPAC-*` é decisão do dono do Superadmin, não deste PR.
