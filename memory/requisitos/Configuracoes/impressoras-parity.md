---
id: requisitos-configuracoes-impressoras-parity
titulo: Paridade de migração — /printers (Impressoras) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-07'
tela: /printers
related:
  - ../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-impressoras.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0320-programa-ondas-regua-correcao'
---

# Paridade — Impressoras (`/printers`)

## Metadados

- **Tela React:** `resources/js/Pages/Configuracoes/Impressoras/Index.tsx` (F3 — ainda não existe)
- **Blade legado:** `resources/views/printer/{index,create,edit}.blade.php`
- **Controller (persistência):** `App\Http\Controllers\PrinterController` — `store()`, `update()`, `destroy()`
- **Rota nova / legada:** a mesma `Route::resource('printers')`; a Blade segue servindo com a flag desligada
- **Auditado em:** `2026-10-07` · **por:** [CL] (leitura das 3 Blades + controller no `main` 7e4a6ebfda)

## Mapa campo-a-campo

| # | Feature do Blade | Está no React? | Evidência | Severidade se perdido | Defendido por |
|---|---|---|---|---|---|
| 1 | Lista só as impressoras do negócio da sessão | ⬜ F3 | `PrinterController::index` (`where business_id`) | **alta** | `ImpressorasBaselineTest` (Blade) · UC da F3 |
| 2 | Colunas nome · conexão · perfil · caracteres/linha · IP · porta · caminho | ⬜ F3 | `printer/index.blade.php` | média | — |
| 3 | Rótulo de conexão e de perfil (não o código do enum) | ⬜ F3 | `Printer::connection_type_str` / `capability_profile_srt` | baixa | — |
| 4 | Cadastrar grava no negócio da sessão, com `created_by` | ⬜ F3 | `store()` | **alta** | `ImpressorasBaselineTest` |
| 5 | `network` zera `path`; `windows`/`linux` zeram IP e porta | ⬜ F3 | `store()` / `update()` | **alta** | `ImpressorasBaselineTest` |
| 6 | Editar só alcança impressora do negócio | ⬜ F3 | `update()` (`findOrFail` com negócio) | **alta** | `ImpressorasBaselineTest` |
| 7 | Excluir só alcança impressora do negócio; confirma antes | ⬜ F3 | `destroy()` + swal no `index` | **alta** | `ImpressorasBaselineTest` |
| 8 | Sem `access_printers` → 403 | ⬜ F3 | todas as ações | **alta** | `ImpressorasBaselineTest` |
| 9 | Defaults do formulário: `char_per_line` 42, porta 9100 | ⬜ F3 | `create.blade.php` | baixa | — |
| 10 | Ajuda dos caminhos Windows (`LPT1`/`COM1`) e Linux (`/dev/usb/lp1`…) | ⬜ F3 | `create.blade.php` (`#path_div`) | baixa | — |
| 11 | "Editar"/"Excluir" sob `@can('printer.update'/'printer.delete')` | 🟡 divergente por desenho | permissões inexistentes no `PermissionCatalog`; o controller só confere `access_printers` | baixa | — |

`⬜ F3` = a coluna React é preenchida no PR da F3, que cita o UC de cada linha `alta`.
