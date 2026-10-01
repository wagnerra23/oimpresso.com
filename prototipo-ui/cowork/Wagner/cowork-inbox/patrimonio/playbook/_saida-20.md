---
sessao: "20"
titulo: "Saída da thread 20 — remoção, não migração: as 5 ações mortas de /asset/settings saem do ar"
dono: "[CL]"
medido_em: 2026-09-30
base_medida: 65e6c99c7 (origin/main fresco)
arquivos_de_producao_tocados: 2
executada_fora_do_placar: "sim — decisão [W] 2026-09-30 no chat: executar agora, sem esperar a 16, e tirar update/destroy junto"
---

# 20 · Remoção, não migração

**Esta thread é de remoção, não de migração.** A tela de Configurações já é um formulário React
único (`Patrimonio/Configuracoes`), então não havia sub-tela para migrar: havia 5 ações mortas
para tirar.

## Por que é remoção
- Configuração é **um registro por empresa**: o JSON `business.asset_settings` mais 2
  `NotificationTemplate`. A Page do índice já é o formulário e grava por `POST /asset/settings`
  (`Configuracoes.tsx:271` → `store()`).
- A `_saida-15` mediu os 3 sítios desta thread: `create` (:150), `show` (:228) e `edit` (:239)
  devolviam views inexistentes (`View::exists = false`) e quebravam com `View [x] not found`.
  Nenhuma interface chegava neles.
- `update()` e `destroy()` tinham corpo vazio (`//`): `PUT`/`DELETE /asset/settings/{id}`
  respondiam sem fazer nada. O [W] mandou tirar os dois junto (2026-09-30).
- A ADR 0414 (D-FORMS) manda a edição para drawers na Page do índice. Para configurações, a
  Page do índice **já é** a edição. A remoção cumpre a ADR; não há drawer a criar.

## O que mudou
| arquivo | antes | depois |
|---|---|---|
| `Modules/AssetManagement/Routes/web.php` | `Route::resource('settings', …, ['as' => 'asset'])` (7 ações) | `->only(['index', 'store'])` |
| `Modules/AssetManagement/Http/Controllers/AssetSettingsController.php` | `create`/`show`/`edit`/`update`/`destroy` | removidos; `index` e `store` intactos |
| `Modules/AssetManagement/Tests/Feature/SmokeRoutesTest.php` | o CONTROLE fixava `asset.settings.show` registrado ("depende da D-FORMS") | o CONTROLE fixa `asset.settings.index` e `.store` |
| `Modules/AssetManagement/Tests/Feature/ConfiguracoesContratoTest.php` | UC-CFG-01..04 | + 2 `it()` do UC-CFG-05 |
| `Configuracoes.casos.md` / `.charter.md` | — | UC-CFG-05 declarado; Non-Goal "NÃO cria rota" com nota datada |

`Modules/AssetManagement/Config/retention.php` (`nao_toca`) **não foi tocado**, e nenhuma UI de
retenção foi adicionada.

## Os campos que salvar continua gravando (a lista do UC-CFG-05)
O `store()` não foi tocado. O teste compara as chaves por **igualdade**, não por presença:

**`business.asset_settings` (JSON, 7 chaves):**
1. `asset_code_prefix`
2. `allocation_code_prefix`
3. `revoke_code_prefix`
4. `asset_maintenance_prefix`
5. `send_for_maintenence_recipients` (o typo é contrato gravado — não se corrige)
6. `enable_asset_send_for_maintenance_email` (só quando a chave vem no payload)
7. `enable_asset_assigned_for_maintenance_email` (idem)

**`notification_templates` (por `business_id` + `template_for`):**
- `send_for_maintenance` → `subject`, `email_body`
- `assigned_for_maintenance` → `subject`, `email_body`

## Como foi medido antes de editar
- **Consumidores das 5 ações:** `git grep` de `asset.settings.(create|show|edit|update|destroy)`,
  de `asset/settings/(create|{id})` e de `AssetSettingsController…'(create|show|edit|update|destroy)'`
  no repo inteiro: **0** fora do próprio `SmokeRoutesTest` (rc=1). Controle positivo: o mesmo
  padrão com `'index'` acha o `nav.blade.php:52` (rc=0).
- **Rota de fallback** que mudasse o 404 esperado: nenhuma `Route::fallback` no repo.
- **Lint:** `php -l` dos 4 arquivos PHP no CT 100, por stdin, sem tocar o checkout de lá: 4 × "No
  syntax errors". Controle positivo da mesma via: PHP quebrado → `Parse error`, rc=255.

## O que NÃO foi medido aqui
- **O Pest.** Ele roda na lane `assetmanagement-pest` do PR. O veredito vale pelo número de
  **assertions** no log, não pelo `success`: `workflow_dispatch` sai verde sem rodar teste.
- **O status HTTP em produção.** O 404 é afirmado pelo teste, com o espelho de 200.

## Placar
A thread 20 aparecia como `pendente` porque o índice a põe atrás da 16. A remoção não depende
de nada da 16, e o [W] mandou executar fora da ordem (2026-09-30). O índice é do Cowork e não
foi editado no espelho.
