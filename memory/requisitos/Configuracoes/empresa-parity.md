---
id: requisitos-configuracoes-empresa-parity
titulo: Paridade de migração — /business/settings (Configuração da empresa) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-13'
tela: /business/settings
related:
  - ../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-empresa.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
---

# Paridade — Configuração da empresa (`/business/settings`)

## Metadados

- **Tela React:** `resources/js/Pages/Configuracoes/Empresa/Index.tsx` (F3 — ainda não existe)
- **Blade legado:** `resources/views/business/settings.blade.php` + 17 parciais `business/partials/settings_*.blade.php`
- **Controller:** `App\Http\Controllers\BusinessController` — `getBusinessSettings()`, `postBusinessSettings()`
- **Rota:** `GET /business/settings` · `POST /business/update` (a mesma nas duas telas)
- **Auditado em:** `2026-10-13` · **por:** [CL]

## Mapa campo-a-campo

| # | Feature do Blade | Está no React? | Evidência | Severidade se perdido | Defendido por |
|---|---|---|---|---|---|
| 1 | Sem `business_settings.access` → 403 na tela e no salvar | ✅ mesmo método | as duas ações | **alta** | `EmpresaBaselineTest` |
| 2 | Lê e grava só o negócio da sessão | ✅ F3-1 (UC-EMP-03) | `user.business_id` | **alta** | `EmpresaBaselineTest` |
| 3 | Salvar manda o formulário inteiro (substituição total) | ✅ F3-1 (UC-EMP-04/05) | `postBusinessSettings` | **alta** | `EmpresaBaselineTest` |
| 4 | Negócio: nome, início, moeda, posição do símbolo, fuso, mês fiscal, método contábil, formatos de data/hora, dias de edição, margem padrão, precisões, códigos 1/2, logo, exportação | ✅ F3-1 | `settings_business` | **alta** | UC da F3 |
| 5 | Impostos: rótulos e números 1/2, imposto em linha | ⬜ F3 | `settings_tax` | média | UC da F3 |
| 6 | Produtos: prefixo SKU, unidade padrão, marca/categoria/subcategoria, validade, prateleiras, subunidades, garantia, 2ª unidade, imagem obrigatória | ⬜ F3 | `settings_product` | **alta** | UC da F3 |
| 7 | Contatos: limite de crédito padrão | ⬜ F3 | `settings_contact` | média | UC da F3 |
| 8 | Vendas: desconto e imposto padrão, adição de item, comissionado, `pos_settings` de venda | ⬜ F3 | `settings_sales` | **alta** | UC da F3 |
| 9 | PDV: chaves `pos_settings[disable_*]`, balança, atalhos de teclado | ⬜ F3 | `settings_pos` · `settings_weighing_scale` | média | UC da F3 |
| 10 | Compras: status, lote, edição de produto, moeda de compra + câmbio, pedido/requisição | ⬜ F3 | `settings_purchase` | média | UC da F3 |
| 11 | Pagamento: denominação de dinheiro | ⬜ F3 | `settings_payment` | baixa | UC da F3 |
| 12 | Painel: dias de alerta de validade | ⬜ F3 | `settings_dashboard` | baixa | UC da F3 |
| 13 | Sistema: cor do tema, dica, linhas por tabela | ⬜ F3 | `settings_system` | baixa | UC da F3 |
| 14 | Prefixos de numeração (`ref_no_prefixes[*]`) | ⬜ F3 | `settings_prefixes` | média | UC da F3 |
| 15 | E-mail e SMS sem expor senha/segredo nas props | ✅ F3-1 (UC-EMP-01: fora do corpo, preservados) | `settings_email` · `settings_sms` | **alta** | UC da F3 |
| 16 | Pontos de fidelidade (`enable_rp`, `rp_*`) | ⬜ F3 | `settings_reward_point` | média | UC da F3 |
| 17 | Módulos habilitados (`enabled_modules[]`) | ⬜ F3 | `settings_modules` | **alta** | UC da F3 |
| 18 | Rótulos personalizados (`custom_labels[*]`) | ⬜ F3 | `settings_custom_labels` | média | UC da F3 |

`⬜ F3` = a coluna React é preenchida no PR da F3 de cada grupo, que cita o UC das linhas `alta`.
