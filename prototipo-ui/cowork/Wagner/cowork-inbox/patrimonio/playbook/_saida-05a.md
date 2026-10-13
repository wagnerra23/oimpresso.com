---
sessao: "05a"
titulo: "ADR sucessora + chaves do retention.php — saída"
executor: "[CL]"
data: 2026-10-13
base: wagnerra23/oimpresso.com@main (lido 2026-10-13)
veredito: "ADR 0430 proposta (merge = [W]); as chaves do retention.php passam a ser as tabelas reais; asset_warranties sai por não ter campo pessoal."
---
# _saida 05a · ADR sucessora e chaves do retention.php

## 1 · ADR

**ADR 0430** — `memory/decisions/0430-retencao-lgpd-patrimonio-anonimiza-campo-pessoal.md`,
`status: proposto`. Registra a D-LGPD-REABRE ([W] 2026-10-07): no Patrimônio, retenção anonimiza o
vínculo pessoal e mantém a linha; `activity_log` e `asset_transactions` nunca perdem linha; nasce
`enabled=false`; ligar é D-CANARY-LGPD; o executor roda por empresa.

A decisão de 27/07 não é uma ADR, é uma lápide do §5 (`memory/licoes-rejeitadas.md`). Por isso a ADR
não usa `supersedes:`; o recorte vai numa **emenda** da lápide, no mesmo PR, e o §5 de
`memory/proibicoes.md` foi rederivado (`sec5-derive --check`: 257 limites, 0 perdidos).

**Fora do recorte, declarado na ADR:** a Jana (`jana:retention-purge`, item #6 do loop IA-OS). A
D-LGPD-REABRE fala do descarte da 05 do Patrimônio; estender à Jana pede decisão [W] própria.

## 2 · Chaves do `retention.php`

| antes | depois | campo pessoal na tabela |
|---|---|---|
| `am_assets` | `assets` | `created_by` |
| `am_asset_transactions` | `asset_transactions` | `receiver`, `created_by`, `reason` (nota livre) |
| `am_maintenance_logs` | `asset_maintenances` | `created_by`, `assigned_to`, `details`, `maintenance_note` |
| `am_warranties` | **sai** | nenhum: só `asset_id`, datas, custo e nota |

Colunas lidas de `database/schema/mysql-schema.sql`, sem inventar coluna (C7). As janelas (3650 /
1825 / 1825 dias) não mudaram.

O `LgpdComplianceTest` e o `AssetManagementHealthCommand` leem as mesmas chaves e mudaram junto;
sem isso o teste e o health check ficariam vermelhos/WARN com o config certo.

## 3 · Prova

- Caso novo no `LgpdComplianceTest`: *"cada chave de retention.php é uma tabela que o schema cria"*.
  O caso antigo só conferia que a chave estava digitada, e foi assim que as 4 `am_*` passaram.
  Predicado exercido fora do runner: chaves novas → 0 sem tabela; chaves antigas → 4 de 4 sem tabela.
- `php -l` limpo nos 3 PHP. Pest não rodado nesta sessão (regra do repo: CT 100 ou CI); a lane
  `assetmanagement-pest.yml` roda o diretório de testes do módulo.

## 4 · Não feito e por quê

- **`UpdateAssetMaintenanceRequest.php:49-50`** ainda consulta `am_maintenance_logs`/`am_assets`
  (medido no `_saida-04` §2b e §5: o Request é órfão, por isso não dá 500 hoje). Está em `Http/`, fora
  do prefixo desta thread.
- **O comando de retenção** é a thread 05, como a ficha manda.
- **Merge da ADR** é do [W] (R10).
