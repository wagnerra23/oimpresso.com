---
id: requisitos-configuracoes-tipos-servico-parity
titulo: Paridade de migração — /types-of-service (Tipos de serviço) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-07'
tela: /types-of-service
related:
  - ../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-tipos-servico.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0320-programa-ondas-regua-correcao'
---

# Paridade — Tipos de serviço (`/types-of-service`)

## Metadados

- **Tela React:** `resources/js/Pages/Configuracoes/TiposServico/Index.tsx` (F3 — ainda não existe)
- **Blade legado:** `resources/views/types_of_service/{index,create,edit}.blade.php`
- **Controller (persistência):** `App\Http\Controllers\TypesOfServiceController` — `store()`, `update()`, `destroy()`
- **Rota nova / legada:** a mesma `Route::resource('types-of-service')`; a Blade segue com a flag desligada
- **Auditado em:** `2026-10-07` · **por:** [CL]

## Mapa campo-a-campo

| # | Feature do Blade | Está no React? | Evidência | Severidade se perdido | Defendido por |
|---|---|---|---|---|---|
| 1 | Lista só os tipos do negócio | ⬜ F3 | `index()` | **alta** | `TiposServicoBaselineTest` |
| 2 | Taxa de embalagem gravada pelo `num_uf` do texto pt-BR; vazio = 0 | ⬜ F3 | `store()`/`update()` | **alta** (valor) | `TiposServicoBaselineTest` (dois caminhos) |
| 3 | Tipo da taxa: fixa × percentual | ⬜ F3 | `packing_charge_type` | **alta** (valor) | `TiposServicoBaselineTest` |
| 4 | Tabela de preço por local (`location_price_group`) | ⬜ F3 | `store()` (cast) · `update()` (`json_encode`) | **alta** (preço) | `TiposServicoBaselineTest` |
| 5 | "Campos personalizados" na venda (`enable_custom_fields`) | ⬜ F3 | `store()`/`update()` | média | `TiposServicoBaselineTest` |
| 6 | Editar/excluir só alcançam tipo do negócio | ⬜ F3 | `update()`/`destroy()` | **alta** | `TiposServicoBaselineTest` |
| 7 | Sem `access_types_of_service` → 403 | ⬜ F3 | todas as ações | **alta** | `TiposServicoBaselineTest` |

`⬜ F3` = a coluna React é preenchida no PR da F3, que cita o UC de cada linha `alta`.
