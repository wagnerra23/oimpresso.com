---
id: requisitos-configuracoes-codigo-barras-parity
titulo: Paridade de migração — /barcodes (Código de barras) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-07'
tela: /barcodes
related:
  - ../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-codigo-barras.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0320-programa-ondas-regua-correcao'
---

# Paridade — Código de barras (`/barcodes`)

## Metadados

- **Tela React:** `resources/js/Pages/Configuracoes/CodigoBarras/Index.tsx` (F3 — ainda não existe)
- **Blade legado:** `resources/views/barcode/{index,create,edit}.blade.php`
- **Controller (persistência):** `App\Http\Controllers\BarcodeController` — `store()`, `update()`, `destroy()`, `setDefault()`
- **Rota nova / legada:** a mesma `Route::resource('barcodes')` + `GET /barcodes/set_default/{id}`; a Blade segue com a flag desligada
- **Auditado em:** `2026-10-07` · **por:** [CL]

## Mapa campo-a-campo

| # | Feature do Blade | Está no React? | Evidência | Severidade se perdido | Defendido por |
|---|---|---|---|---|---|
| 1 | Lista só as configurações do negócio, com a padrão marcada | ⬜ F3 | `index()` | **alta** | `CodigoBarrasBaselineTest` |
| 2 | Cadastrar grava no negócio da sessão | ⬜ F3 | `store()` | **alta** | `CodigoBarrasBaselineTest` |
| 3 | Rolo contínuo: `is_continuous = 1` e 28 etiquetas por folha | ⬜ F3 | `store()` / `update()` | **alta** | `CodigoBarrasBaselineTest` |
| 4 | Folha: etiquetas por folha e altura do papel vêm do formulário | ⬜ F3 | `store()` / `update()` | **alta** | `CodigoBarrasBaselineTest` |
| 5 | Cadastrar como padrão desmarca a padrão anterior | ⬜ F3 | `store()` | **alta** | `CodigoBarrasBaselineTest` |
| 6 | Editar/excluir/tornar padrão só alcançam o negócio | ⬜ F3 | #8924 | **alta** | `CodigoBarrasTenantTest` |
| 7 | A padrão não pode ser excluída | ⬜ F3 | `destroy()` | **alta** | `CodigoBarrasTenantTest` |
| 8 | Sem `barcode_settings.access` → 403 | ⬜ F3 | todas as ações | **alta** | `CodigoBarrasBaselineTest` |
| 9 | Medidas em polegada (margens, largura, altura, distâncias) | ⬜ F3 | `create.blade.php` (`in`) | **alta** — o protótipo propõe mm (RUNBOOK §10) | UC da F3 |
| 10 | Defaults: margens e distâncias 0 | ⬜ F3 | `create.blade.php` | baixa | — |

`⬜ F3` = a coluna React é preenchida no PR da F3, que cita o UC de cada linha `alta`.
