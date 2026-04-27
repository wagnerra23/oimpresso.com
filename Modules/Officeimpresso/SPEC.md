# Modules/Officeimpresso — SPEC (resumo legado)

> **CONTRATO DELPHI IMUTÁVEL** (`feedback_delphi_contrato_imutavel`).
> Endpoints e payloads consumidos pelo cliente offline em Delphi NÃO
> podem mudar sem migração coordenada com a base instalada. Ver ADRs
> 0017–0021 e `project_officeimpresso_modulo`.

## Propósito

Sistema offline (Delphi/desktop) que sincroniza licenças por
computador/empresa com o oimpresso.com. Cada cliente tem N licenças
amarradas ao `business_id`; superadmin (WR2) administra todas.

## Superfícies relevantes

### Web (`web + auth + SetSessionData + AdminSidebarMenu`, prefixo `/officeimpresso`)

- `resource('client', ClientController)` + `GET /regenerate`
- `resource('licenca_computador', LicencaComputadorController)`
  - `GET /licenca_computador` → lista por `session.user.business_id`
  - `GET /computadores`
  - `GET /licenca_computador/{id}/toggle-block` (named `licenca_computador.toggleBlock`)
  - `POST /licenca_computador/businessupdate/{id}`
  - `GET /licenca_computador/businessbloqueado/{id}`
  - `GET /licenca_computado/licencas/{id}` (sic — typo legado, NÃO corrigir, Delphi consome esta URL)
  - `GET /businessall` (lista global — superadmin)
- `resource('licenca_log', LicencaLogController)`
- `superadmin.docs` iframe → https://docs.officeimpresso.com.br

### Install (`web + authh + auth + CheckUserLogin`)

`GET/POST /officeimpresso/install`, `/install/uninstall`, `/install/update`.

## Regras de negócio

1. **Tenancy**: lista de licenças filtrada por `business_id` da sessão.
2. **Acesso superadmin**: rotas globais (`businessall`, `viewLicencas`)
   só pra admin WR2 (controle a nível de menu/sidebar — não há middleware
   `superadmin` aplicado às rotas; é vulnerabilidade conhecida documentada
   em ADR 0019). **Não corrigir agora** — Delphi depende do shape atual.
3. **Imutabilidade Delphi**: shape do JSON e nomes de URL não mudam.

## Cobertura de testes (batch 7)

- `tests/Feature/Modules/Officeimpresso/OfficeimpressoAccessTest.php`

Filtro: `vendor/bin/pest --filter=Officeimpresso`

## Recomendação

**MANTER (com ressalva).** Módulo legítimo, em produção, com clientes
ativos. **NÃO REFATORAR sem coordenar com WR2 + base Delphi instalada.**
A vulnerabilidade de autorização (qualquer user logado vê `businessall`)
deve ser tratada via mascaramento de menu hoje, e endurecida em uma v2
do contrato Delphi (sprint dedicado, não tarefa solta).
