---
id: requisitos-asset-management-runbook-configuracoes
title: "RUNBOOK — Patrimônio · Configurações (`/asset/settings`)"
module: AssetManagement
tela: Patrimonio/Configuracoes
owner: W
status: rascunho
last_validated: "2026-09-08"
preconditions:
  - "Usuário **admin do business** — `Util::is_admin()` é `hasRole('Admin#'.$business_id)` (`app/Utils/Util.php:486`). É a única tela do módulo com esse gate"
  - "`business_id` na sessão — as settings vivem numa coluna JSON de `business`, lida por `business_id` explícito (ADR 0093, Tier 0)"
  - "Módulo `assetmanagement_module` habilitado no pacote do business (Camada 1 — superadmin/packages), ou `can('superadmin')`"
  - "Middleware `AdminSidebarMenu` na rota — dispara `DataController::modifyAdminMenu()`, dono dos ghosts que a sub-navegação lê"
preconditions_short: admin do business (Admin#biz), business_id na sessão, módulo habilitado, AdminSidebarMenu na rota
related_adrs: [0104-processo-mwart-canonico-unico-caminho, 0093-multi-tenant-isolation-tier-0, 0180-sidebar-v3-5-grupos-ghosts-header, 0394-endereco-de-ui-do-patrimonio-pages-patrimonio]
---

# RUNBOOK — Patrimônio · Configurações (`/asset/settings`)

> **F1 PLAN do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).**
> Escrito ANTES do `.tsx`, como o hook `block-mwart-violation` exige — ele não tem override
> (medido 2026-08-08: zero `process.env`, única saída é `process.exit(2)`).
>
> **Sexta tela da frente do Patrimônio, e a menor.** Ela não funda nada: herda o `_shared/`
> fundado por [Bens](RUNBOOK-bens.md) (PR #7035) e o padrão de charter/casos/teste de lá.

## 1. Objetivo

Deixar o **administrador** do business ajustar as duas coisas que o módulo guarda por empresa:
os **prefixos** com que os códigos de bem, alocação, devolução e manutenção são gerados, e as
**notificações de manutenção** — quem recebe, se sai e-mail além do sino, e o texto de cada
mensagem.

## 2. Persona principal

O **gestor do patrimônio**, que na prática é o administrador da empresa. Não é tela de
operação diária: mexe-se nela na configuração inicial e raramente depois. É por isso que ela é
a única do módulo restrita a `is_admin` — mudar prefixo altera a numeração de tudo que vier
depois, e mudar destinatário muda quem recebe e-mail em nome da empresa.

## 3. Pré-requisitos

Ver `preconditions` no frontmatter. O que mais surpreende quem chega:

- **A guarda é dupla e diferente das irmãs.** As outras telas param em
  `superadmin || assinatura`; esta exige **também** `is_admin`, com `|| ! $is_admin` no fim do
  mesmo `if` (`AssetSettingsController.php:43` e `:110`). Confirmado por leitura das duas
  linhas, idênticas: 4 ocorrências de `is_admin` no arquivo (2 atribuições + 2 usos), e nenhum
  terceiro `abort(403)`.
- **`Admin#{business_id}` é o nome literal do role.** Sufixo por business é a convenção da casa
  (`roles.business_id` é NOT NULL + FK) — role global viola a FK.

## 4. Fluxo principal (golden path)

1. O admin abre `/asset/settings` (ghost **Configurações** do menu do módulo).
2. O `index()` lê `AssetUtil::getAssetSettings($business_id)` — o JSON da coluna
   `business.asset_settings` — mais os dois `NotificationTemplate` do business
   (`send_for_maintenance` e `assigned_for_maintenance`) e a lista de usuários para
   destinatário (`User::forDropdown`).
3. A tela mostra duas seções: **Prefixos** (4 campos) e **Notificações** (2 blocos).
4. O admin edita e envia. `POST /asset/settings` (`store()`) regrava o JSON inteiro e faz
   `updateOrCreate` de cada template preenchido.
5. Redireciona de volta com `status` de sucesso.

## 5. Onda desta entrega, e o que fica pra depois

**Entra — paridade com as 3 blades, sem exceção:** os **4** prefixos (`asset_code_prefix`,
`allocation_code_prefix`, `revoke_code_prefix`, `asset_maintenance_prefix`), o multi-select de
destinatários, os 2 interruptores de e-mail, e os 4 campos de template (assunto + corpo de cada
uma das duas notificações), com a lista de tags disponíveis de cada bloco.

**Fica pra depois, com motivo:**

| Adiado | Motivo |
|---|---|
| **Editor WYSIWYG** no corpo do e-mail | o Blade usa TinyMCE (`index.blade.php:44-50`). Trazer editor rich-text para o React é **dependência nova** — exige ADR ([proibicoes](../../proibicoes.md) §Código). Nesta onda o corpo é textarea monoespaçada, com o rótulo dizendo que o conteúdo é HTML |
| os **3 interruptores do protótipo** (`patrimonio-page.jsx:589`) | "Garantia expirando em 30 dias" e "Alocação registrada pro colaborador" **não têm backend** — nenhuma coluna, nenhum job, nenhuma `Notification`. Interruptor que não liga nada é afordância falsa |
| a linha **"Retenção: 5 anos"** do rodapé do protótipo | aponta `Config/retention.php`, que é da **thread 05, BARRADA** pela lápide §5 de 2026-07-27 (num ERP não se apaga PII) — e as tabelas que ele declara (`am_assets`, `am_maintenance_logs`) **não existem**. A própria ficha da thread 11 manda parar e declarar, nunca criar a tabela |

## 6. Estados (loading / empty / error / success)

- **Carregando:** só a lista de **destinatários**. `usuarios` é `Inertia::defer` — é a única
  prop que cresce com o tamanho do tenant — e o bloco dela renderiza um skeleton até chegar
  ([RUNBOOK-inertia-defer-pattern](_DesignSystem/RUNBOOK-inertia-defer-pattern.md)). As outras
  (um `value()` de coluna e dois `first()`) vêm eager: deferi-las também só somaria um
  ida-e-volta para economizar ~1ms.
- **Vazio:** business novo tem `asset_settings` nulo; `getAssetSettings` devolve `[]` e os
  campos nascem em branco, com `placeholder`. Os templates nascem com o texto-padrão que o
  próprio `index()` monta.
- **Erro:** o `store()` captura e devolve `status.success = false` com mensagem genérica; a tela
  mostra o retorno do backend, sem inventar texto próprio.
- **Sucesso:** volta com `status.success = true`.
- **Sem permissão:** 403 do gate — não-admin nunca vê a tela.

## 7. Atalhos de teclado

Nenhum nesta onda. Anunciar atalho que a tela não implementa é afordância falsa.

## 8. Dependências de API/backend

| O quê | Onde |
|---|---|
| leitura das settings | `AssetUtil::getAssetSettings($business_id)` — `json_decode` da coluna, `[]` quando vazia |
| gravação | `AssetSettingsController::store()` — `Business::where('id',$biz)->update(['asset_settings' => json_encode($input)])` |
| templates de e-mail | `App\NotificationTemplate`, chaves `send_for_maintenance` e `assigned_for_maintenance` |
| destinatários | `User::forDropdown($business_id, false)` — servida como `Inertia::defer` |
| consumidores dos prefixos | `AssetService`, `AssetAllocationService`, `AssetMaintenanceService`, `RevokeAllocatedAssetController` |

⚠️ **`store()` regrava o JSON inteiro, não faz merge.** `$request->only(...)` monta um array com
5 chaves e o `json_encode` dele substitui a coluna. Chave que existisse no JSON e não estivesse
no formulário seria **perdida no primeiro save** — hoje não há nenhuma, porque o formulário
cobre tudo que o módulo lê, mas quem acrescentar chave nova tem de acrescentá-la **também** ao
`only()`.

⚠️ **Interruptor: a ausência é o "desligado".** O controller usa `$request->has(...)`, não
`boolean(...)`. Checkbox HTML desmarcado **não envia a chave**, e é assim que ela some do JSON.
Um cliente que mandasse `enable_...: false` faria `has()` devolver **true** e gravaria **1** —
desligar deixaria de funcionar. Por isso a tela **omite a chave** quando desmarcada, em vez de
mandar `false`: preserva o contrato sem tocar no `store()`.

## 9. Multi-tenant + LGPD

- **Tier 0:** `business.asset_settings` é lido e escrito com `business_id` **explícito** nas duas
  pontas (`getAssetSettings` e o `where('id', $business_id)` do `update`). Não há global scope
  aqui — o isolamento é o filtro manual, e ele existe.
- **LGPD — medido, não presumido:** as duas notificações vão para **`User` interno**, nunca para
  `Contact`. A primeira notifica os `send_for_maintenence_recipients`, que vêm de
  `User::forDropdown($business_id)`; a segunda notifica `$maintenance->assignedTo`
  (`AssetUtil.php:84`), que é o colaborador designado. O opt-in
  `Contact::canReceiveEmailNotification()` das [proibições](../../proibicoes.md) é sobre
  **cliente** e **não se aplica** a esta tela. Se algum dia um destinatário externo entrar aqui,
  o opt-in passa a valer e este parágrafo caduca.
- **Sem PII nova na tela:** o dropdown mostra nome de usuário interno, que já é a superfície do
  módulo inteiro.

## 10. Smoke check pós-deploy

1. `GET /asset/settings` como **admin** do business → 200, componente `Patrimonio/Configuracoes`.
2. Como usuário **não-admin** do mesmo business → **403** (é a guarda desta tela).
3. Alterar `asset_code_prefix`, salvar, reabrir → o valor voltou.
4. Desmarcar um interruptor de e-mail, salvar, reabrir → continua desmarcado (é o caso que a
   armadilha do §8 quebraria).
5. Cadastrar um bem novo e conferir que o código nasceu com o prefixo corrente.

## 11. O que NÃO fazer

- ❌ **Não "normalizar" a guarda** para o padrão das telas irmãs. O `|| ! $is_admin` é desenho:
  configuração de módulo é admin-only.
- ❌ **Não mudar o shape do JSON** de `asset_settings` — nem renomear chave, nem "consertar" o
  typo de `send_for_maintenence_recipients`. Os prefixos alimentam a geração de código dos bens
  já cadastrados; mexer no formato exige migration + backfill e decisão do [W].
- ❌ **Não tocar `Config/retention.php`** — é da thread 05, barrada.
- ❌ **Não mandar `false`** para as chaves `enable_*` (ver §8).
- ❌ **Não criar rota nova.** `Route::resource('settings', …)` já serve `GET`/`POST`
  `/asset/settings`. Rota nova seria segundo dono da mesma tela.

## 12. Diagnóstico / Troubleshoot

| Sintoma | Causa provável |
|---|---|
| 403 num usuário que "deveria poder" | falta o role `Admin#{business_id}` — permission `asset.*` **não** basta nesta tela |
| interruptor volta ligado depois de desligar | o payload mandou `enable_...: false` em vez de omitir a chave (§8) |
| prefixo salvo mas código do bem não mudou | o prefixo vale para o **próximo** código; não renumera o que existe |
| destinatários somem depois de salvar | o `select` mandou vazio e o `only()` gravou vazio — comportamento do Blade, preservado |

## 13. Refs

- Charter: [`resources/js/Pages/Patrimonio/Configuracoes.charter.md`](../../../resources/js/Pages/Patrimonio/Configuracoes.charter.md)
- Casos: [`resources/js/Pages/Patrimonio/Configuracoes.casos.md`](../../../resources/js/Pages/Patrimonio/Configuracoes.casos.md)
- Tela irmã que fundou o padrão: [`RUNBOOK-bens.md`](RUNBOOK-bens.md)
- Fonte visual: `prototipo-ui/cowork/patrimonio-page.jsx` (aba `config`, `:589`) — **alvo**, não decisão de produto
- Playbook: `prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/11-configuracoes.md`
