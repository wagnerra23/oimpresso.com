---
title: "Gap PESSOAS (Firebird Delphi WR Comercial) vs contacts (Laravel oimpresso) — análise pré-migração Larissa/Vargas"
type: session
date: "2026-05-26"
author: Claude (Opus 4.7) sob direção Wagner
status: live
audience: Wagner (decisor) + Felipe (migrar Larissa biz=4) + Maiara (futuras migrações multi-cliente)
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0178-canon-br-fiscal-restaurado-v3-7
  - 0179-drawer-cliente-wave-b-c-cowork
  - 0188-multi-type-flags-aditivas
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0131-tiering-memoria-canonico-local-segredo
  - 0197-extend-contacts-absorcao-pessoas-legacy
  - 0198-hot-cold-tiering-migracao-transacional-legacy
source_files:
  - "memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md §2.5"
  - "memory/research/clientes-legacy-officeimpresso/_MAPPING/TELA-PESSOAS.md (mapping canônico source-first)"
  - "memory/reference/migracao-officeimpresso-pattern.md"
  - "app/Contact.php (Model)"
  - "database/migrations/2017_07_27_075706_create_contacts_table.php (UPOS core)"
  - "database/migrations/2020_06_12_162245_modify_contacts_table.php (UPOS PII split)"
  - "database/migrations/2026_05_*_contacts*.php (8 waves BR Maio/2026)"
  - "scripts/legacy-migration/import-empresas.py (template importer)"
  - "scripts/legacy-migration/import-contacts-from-venda.py (Martinho v1404 sem CLIENTES populada)"
---

# Gap PESSOAS vs contacts — análise pré-migração

> **Constraint Wagner 2026-05-26:** análise antes de codar. Decisão arquitetural primeiro, schema depois, ADR antes de migration. Cliente piloto **Larissa ROTA LIVRE biz=4** (já operando — extensão para cobrir dados Vargas via Delphi WR Comercial). Vargas é fonte canônica do schema legacy.
>
> **Decisões Wagner 2026-05-26 (respondidas):**
> 1. **Bucket B confirmado** — tabela satélite `contact_profile_legacy` 1:1 (não JSON gigante). Mantém pattern UPOS `contact_metadata`.
> 2. **Escopo papéis = TODOS os 4 tipos** (cliente + fornecedor + funcionário + transportador). "Fazer certo, saber os campos e agrupar tem de todos."
> 3. **Dump Vargas** — agente decide. Estratégia: avançar Bucket A com as ~14 cols canon documentadas + entregar script Python schema-only pra Wagner rodar (~15min). Bucket B finalizado depois do dump real.

## 1. Resumo executivo (5 linhas)

**Gap real:** ~30 colunas canônicas em `PESSOAS` (Firebird) faltam em `contacts` (MySQL) — não 250. Das **329 colunas físicas** Firebird, ~285 são pares dinâmicos `IS_<TIPO>` / `SEQUENCIA_<TIPO>` (multi-tipo: já resolvido via ADR 0188 flags aditivas) **+** campos auto/vazaram-pra-schema (`PLACA`, `MARCAMODELO` — vertical oficina, descartar em PESSOAS) **+** campos nulos 100% (legacy debt). Recomendação **top-line:** **Bucket A (~18 colunas) extend `contacts` aditivamente em 1 migration Wave D ADR 0197 + Bucket B (`contact_profile_legacy` tabela 1:1) pra ~12 colunas de retro-rastreabilidade Delphi.** NÃO criar tabela `clientes` paralela (ADR ARQ-0001 Crm + ADR 0179 já decidiram reuso `contacts`). **Esforço estimado** com IA-pair: **6-10h Felipe** (migration + Eloquent fillable/casts + Pest schema test + retest importer Vargas dry-run + ADR 0197 + Wagner aprovação).

## 1.bis Escopo de papéis — roteamento por TIPO Delphi (decidido Wagner 2026-05-26)

`PESSOAS` Firebird é "saco-fundo" — convive cliente + fornecedor + funcionário + transportador (e vários custom-tipos Delphi `OIM`/`AGE`/`LOC`/`OFI` etc.) no mesmo registro com flags `IS_<TIPO>`. No Laravel oimpresso **não unificamos** — cada tipo tem destino canônico. ADR 0197 documenta o roteamento.

| TIPO Delphi | Cardinalidade típica | Destino Laravel | Flag/coluna chave | Importer | Estado |
|---|---|---|---|---|---|
| `CLI` (cliente) | ~70% dos cadastros típicos | `contacts` | `is_customer=1` (ADR 0188) | `import-pessoas-from-firebird.py --tipo CLI` (a criar) | ✅ destino canon — Bucket A cobre |
| `FOR` (fornecedor) | ~25% (overlap parcial com `CLI`) | `contacts` | `is_supplier=1` (ADR 0188) | `import-pessoas-from-firebird.py --tipo FOR` (a criar — mesma tabela) | ✅ destino canon — Bucket A cobre |
| `REP` (representante) | <5% | `contacts` | `is_representative=1` (ADR 0188) | `import-pessoas-from-firebird.py --tipo REP` | ✅ destino canon — usa `sales_rep_contact_id` self-FK |
| `FUN` (funcionário) | ~10-20 cadastros típicos | **`users`** (UPOS canon HRM) | `user_type='user'` + `essentials_department_id` + `essentials_designation_id` + `crm_contact_id` ponte | `import-funcionarios-from-firebird.py` (a criar — SEPARADO) | ⚠️ tabela própria — **NÃO entra em contacts.** Migration extra `users` opcional pra campos PESSOAS específicos (CTPS, PIS, etc — fora Fase 1 ADR 0197) |
| `T` / `TRA` (transportador) | <10 cadastros típicos | **`transportadoras`** (legacy 2017 Brazilian fork v3.7) | tabela própria — `razao_social`, `cnpj_cpf`, `logradouro`, `cidade_id`, `business_id` | `import-transportadoras-from-firebird.py` (a criar — SEPARADO) | ⚠️ tabela própria — **NÃO entra em contacts.** Schema mínimo (5 cols + biz_id) cobre só MDFe básico — Fase 2 pode extender se Vargas usar transportador com endereço/IE completo |
| `OIM`/`AGE`/`LOC`/`OFI` (custom) | varia por cliente | descartar OU flag custom OU `contacts` se virou cliente/fornecedor de fato | analisar caso a caso no dump real | Bucket D (descarte) por default — mapping no `import-pessoas-from-firebird.py` |

**Implicação operacional:**

- 1 PESSOA Delphi com `IS_CLI=1 AND IS_FOR=1` → 1 registro `contacts` com `is_customer=1 AND is_supplier=1` (ADR 0188 — aditivo).
- 1 PESSOA Delphi com `IS_FUN=1 AND IS_CLI=1` (funcionário que também é cliente) → **2 registros** — 1 em `users` (papel funcionário) + 1 em `contacts` (papel cliente) — ligados via `users.crm_contact_id` (FK existente desde Wave 2021-08).
- 1 PESSOA Delphi com `IS_T=1 AND IS_CLI=1` (transportador que também compra) → **2 registros** — 1 em `transportadoras` + 1 em `contacts`. Sem FK ponte hoje (Fase 2 pode adicionar `transportadoras.contact_id` se necessidade real surgir).

**Por que NÃO unificar tudo em `contacts`:**

- `transportadoras` tem schema mínimo MDFe-friendly, criado em 2017 — **não vale a pena merge** (zero ganho, custo alto retest módulos `Modules/NfeBrasil/Http/Controllers/MdfeController.php` etc).
- `users` HRM (Essentials) tem semântica de **login + perfil interno** — RG/CPF de funcionário ≠ CPF de cliente. Misturar quebra LGPD (`users.password`, `users.allow_login` não fazem sentido em `contacts`).
- Wagner regra: "fazer certo, saber os campos" — mapping documentado evita "tudo no contacts" anti-pattern (já visto em Repair sprint S2.5 — reverted).

**Limitação Fase 1 ADR 0197:** só **CLI + FOR + REP** entram (3 tipos × bucket A/B). Importers separados pra **FUN + T** ficam Fase 2 (Wagner decide quando — provavelmente após primeiro cliente Vargas estabilizar). Mapping já documentado pra agente futuro não re-pensar.

## 2. Estado atual de `contacts` (relevantes)

Coluna inicial (2017 UPOS core) + 23 waves até 2026-05-24. **Foco em PII/BR/legacy_id** (não exaustivo):

| Coluna | Tipo | Propósito | Origem |
|---|---|---|---|
| `id` | `bigint AI` | PK | UPOS core 2017 |
| `business_id` | `int unsigned FK` | **Multi-tenant Tier 0 (ADR 0093)** | UPOS core 2017 |
| `type` | `string` (enum text) | `customer`/`supplier`/`employee`/`representative`/`both`/`lead` | UPOS core 2017 |
| `is_customer` / `is_supplier` / `is_employee` / `is_representative` | `bool default 0` | **Flags multi-type aditivas — papéis sobrepostos** (ADR 0188) | Wave 2026-05-24 |
| `supplier_business_name` | `string nullable` | Razão social PJ (alias inicial UPOS) | UPOS core 2017 |
| `name` | `string` | Nome principal (RAZAOSOCIAL ou nome PF) | UPOS core 2017 |
| `prefix` / `first_name` / `middle_name` / `last_name` | `string nullable` | Split PF UPOS 2020 (UltimatePOS world) | UPOS 2020-06 |
| `tax_number` | `string nullable` | CPF/CNPJ legacy UPOS (mascarado via accessor PII) | UPOS core 2017 |
| `cpf_cnpj` | `string(20) nullable INDEX` | **Canon BR (v3.7 original, restaurado pós-regressão UPOS 6.7 — ADR 0178)** | Wave 2026-05-21 |
| `rg` | `string(20) nullable` | RG PF | Wave 2026-05-21 |
| `nome_fantasia` | `string(150) nullable` | Nome fantasia PJ (distinto de razão social) | Wave 2026-05-21 |
| `fantasia` | `string(255) nullable` | **Duplicado** — alias Cowork blueprint (Wave C decide canon) | Wave 2026-05-22 drawer |
| `inscricao_estadual` / `ie` | `string nullable` | IE PJ (canon + alias Cowork — duplicado intencional) | Wave 2026-05-21 + Wave 2026-05-22 |
| `inscricao_municipal` | `string(30) nullable` | IM (NFSe) | Wave 2026-05-21 |
| `indicador_ie` | `tinyint nullable` | NFe SEFAZ 1=contribuinte / 2=isento / 9=não-contrib | Wave 2026-05-21 |
| `ind_ie_dest` | `tinyint nullable` | NFe `<dest>` indIEDest (deriva chain SEFAZ) | Wave 2026-05-23 SEFAZ |
| `consumidor_final` | `bool default 0` | NFe (v3.7 canon, restaurado) | Wave 2026-05-21 |
| `contribuinte` | `bool default 1` | ICMS contribuinte (v3.7 canon) | Wave 2026-05-21 |
| `regime` | `string(30) nullable` | Simples/Presumido/Real/MEI (v3.7) | Wave 2026-05-21 |
| `suframa` | `string(20) nullable` | Zona Franca Manaus | Wave 2026-05-21 |
| `sefaz_cad_sit` / `sefaz_cad_ind_cred_nfe` / `sefaz_cad_consultado_em` | mixed | Cache SEFAZ consulta cadastro (warning UI rejeição) | Wave 2026-05-23 |
| `tipo` | `enum('PF','PJ') nullable` | PF/PJ (drawer Wave C) | Wave 2026-05-22 drawer |
| `address_line_1` / `address_line_2` | `text/string` | Logradouro + complemento | UPOS core 2017 + 2020 |
| `numero` | `string(20) nullable` | **Número endereço BR (v3.7 canon)** | Wave 2026-05-22 |
| `neighborhood` | `string(120) nullable` | Bairro (UPOS legacy não tinha) | Wave 2026-05-22 drawer |
| `city` / `state` / `country` / `zip_code` | `string` | Endereço UPOS | UPOS core 2017 |
| `city_code` | `string(7) nullable` | IBGE (NFe enderEmit/cMun) | Wave 2026-05-22 |
| `mobile` / `landline` / `alternate_number` / `tel2` | `string nullable` | Telefones | UPOS core + Wave drawer |
| `email` | `string nullable` | Email principal | UPOS 2018 |
| `whatsapp_consent` / `email_consent` / `consent_updated_at` | `bool/timestamp nullable` | **LGPD opt-in (NULL=permite — back-compat ROTA LIVRE)** | Wave 2026-05-12 |
| `cargo` | `string(80) nullable` | Cargo contato principal PJ | Wave 2026-05-22 drawer |
| `nascimento` | `date nullable` | DOB drawer (UPOS já tem `dob`) | Wave 2026-05-22 drawer |
| `dob` | `date nullable` | DOB UPOS legacy | UPOS 2020 |
| `canal_preferido` | `enum nullable` | whatsapp/email/telefone/presencial | Wave drawer |
| `tabela_preco_padrao` | `enum default 'padrao'` | varejo/atacado/parceiro | Wave drawer |
| `pgto_padrao` | `enum nullable` | pix/boleto/cartao/dinheiro/transf | Wave drawer |
| `segmento` | `enum nullable` | varejo/atacado/agencia/corporativo/evento/governo | Wave drawer |
| `tags` | `json nullable` | Multi-select chips semânticas (vip/atencao/churn_risk/...) | Wave drawer |
| `vip` | `bool default 0` | Pill VIP (indexado biz_id+vip) | Wave drawer |
| `favorito_users` | `json nullable` | Star pessoal por user_id | Wave drawer |
| `obs_comercial` | `text nullable` | Observação livre | Wave drawer |
| `site_url` | `string(120) nullable` | Site corporativo | Wave drawer |
| `contact_id` | `string nullable` | Display ID UPOS | UPOS 2018 |
| `legacy_id` | `string(32) nullable INDEX (biz_id,legacy_id)` | **Chave natural legacy Delphi (CNPJ ou EMPRESA.CODIGO)** | Wave 2026-05-13 |
| `customer_group_id` | `int FK nullable` | Grupo de cliente UPOS (preço/desconto) | UPOS |
| `credit_limit` | `decimal nullable` | Limite crédito | UPOS 2018-07 |
| `pay_term_number` / `pay_term_type` | `int/enum nullable` | Prazo pagamento | UPOS core 2017 |
| `is_default` / `contact_status` / `is_export` | mixed | UPOS estado/flags | UPOS 2020 |
| `shipping_*` (5 cols) | mixed | Endereço de entrega separado | UPOS 2020-04 |
| `custom_field1..10` | `string/text nullable` | **JSON-like livre UPOS** — disponível pra absorver legacy esparso | UPOS 2018+2020 |
| `created_by` / `created_at` / `updated_at` / `deleted_at` | timestamps | Auditoria + soft delete | UPOS core |

**Total atual estimado:** ~85-95 colunas (não 80 — Wagner subestimou; 8 waves Maio/2026 já adicionaram ~28 colunas).

## 3. Schema `PESSOAS` Firebird

**329 colunas físicas — mas estrutura real:**

- **~24 colunas fixas canônicas** (SQL base `TControllerPessoas.Create`, mapping TELA-PESSOAS.md §2)
- **~285 colunas dinâmicas `IS_<TIPO>` + `SEQUENCIA_<TIPO>`** — geradas a cada `PESSOAS_TIPO` cadastrado. Tipos canônicos vistos: `CLI`/`FOR`/`FUN`/`REP`/`AGE`/`OIM` (6 × 2 = 12 cols base) + tipos custom por cliente (Vargas pode ter `IS_LOC`/`IS_OFI` etc — descobrir no dump real). **Já resolvido em oimpresso via ADR 0188 flags aditivas + ADR 0070 contact_roles seria over-engineering** (Wagner já decidiu — flags aditivas suficientes pros 4 papéis principais).
- **~20 colunas relacionamentos/FKs** (`CODCIDADE`, `CODPRODUTO_TABELA`, `PESSOA_ASSOCIADO_CODIGO`, `PESSOA_REPRESENTANTE_CODIGO`, etc)

### 3.1 Colunas canônicas Vargas (sintetizadas de TELA-PESSOAS.md + OFFICEIMPRESSO-FIREBIRD-SCHEMA.md §2.5 — sem dump real)

| Coluna PESSOAS | Tipo (inferido) | Notas |
|---|---|---|
| `CODIGO` | varchar `N-empresa` | PK Delphi (sufixo `-empresa` multi-tenant Delphi) |
| `TIPO` | char(1) | `F`=Física / `J`=Jurídica |
| `CNPJCPF` | varchar | PII |
| `RAZAOSOCIAL` | varchar | obrigatório |
| `FANTASIA` | varchar | nullable |
| `ENDERECO` | varchar | obrigatório (logradouro) |
| `NUMERO` | varchar | obrigatório |
| `COMPLEMENTO` | varchar | nullable |
| `BAIRRO` | varchar | obrigatório |
| `CEP` | varchar | obrigatório |
| `CODCIDADE` | int FK | FK pra `CIDADES` (IBGE) |
| `UF` | char(2) | obrigatório (denormalizado) |
| `FONE1` | varchar | obrigatório (FONE1 pode ser literal '0' fallback Delphi) |
| `FONE2` / `FAX` | varchar | nullable |
| `EMAIL` | varchar | nullable (placeholder 'Email' Delphi quando vazio) |
| `DATACADASTRO` | timestamp | nativo |
| `DATANASCIMENTO` | date | só PF |
| `ANIVERSARIO` | varchar mm-dd | aniversário comemoração (separado de DOB) |
| `ATIVO` | char `S/N` | soft delete (mapear pra deleted_at OU contact_status) |
| `SITUACAO` | varchar | string livre: "BLACKLIST"/"VIP"/... |
| `BLOQUEADO` | char `S/N` | bloqueia cobrança/venda |
| `PRIORIDADE_PRODUCAO` | smallint 0-5 | rating control (5 estrelas) |
| `LIMITE_DESCONTO` | decimal | % desconto máx 0-100 |
| `OBSERVACAO` | text | livre |
| `INSCIDENT` (IE) | varchar | IE estadual |
| `INSC_MUNICIPAL` | varchar | IM |
| `TIPO_CONTRIBUINTE` | char `1/2/9` | 1=ICMS, 2=isento, 9=não-contrib |
| `CRT` | varchar | "Simples Nacional"/"Lucro Presumido"/... |
| `CONSUMIDOR_FINAL` | char `S/N` | NFe |
| `ISS_RETIDO` | char `1/2` | retenção ISS NFSe |
| `BOLETO_PERC_DESCONTO_PADRAO` | decimal | desconto pontualidade |
| `COBRAR_CUSTO_BOLETO` | char `S/N` | tarifa boleto repassada |
| `FATURA_PREVISAO` | date | previsão próxima fatura |
| `PESSOA_ASSOCIADO_CODIGO` | int FK | rede filiais/matriz |
| `PESSOA_REPRESENTANTE_CODIGO` | int FK | rep responsável |
| `CODPRODUTO_TABELA` | int FK | tabela de preço |
| `TIPO_PADRAO` | varchar | qual papel principal exibir |
| `TELEFONE_CONTATO_PRINCIPAL` / `CONTATO` | varchar | nome+fone contato pessoa-chave |
| `ETIQUETA` | char `S/N` | imprime etiqueta padrão |
| `USUARIO_CADASTRO` / `USUARIO_ALTERACAO` | varchar | auditoria Delphi |
| `DT_ALTERACAO` | timestamp | auditoria Delphi |

⚠️ **Demais ~285 colunas precisam de dump real do Vargas Firebird** (`SELECT * FROM RDB$RELATION_FIELDS WHERE RDB$RELATION_NAME='PESSOAS' ORDER BY RDB$FIELD_POSITION`) — não foram dumpadas ainda. Inclui `IS_<TIPO>` × N tipos, `OBS_*` várias, `URL_*`, `EMAIL_*` múltiplos, campos vazados de oficina (`PLACA`, `MARCAMODELO`, `ANO`), e legacy debt.

## 4. Tabela de gap (núcleo)

### Bucket A — EXTEND `contacts` (Wave D, migration aditiva)

Campos NF-e/cadastro BR ou comerciais que múltiplos módulos (Sells, NfeBrasil, Compras, Financeiro) consultam direto via Eloquent.

| Delphi PESSOAS | Laravel `contacts` | Justificativa |
|---|---|---|
| `COMPLEMENTO` | `complemento` (string 120 null) | Cowork drawer Wave C usa; UPOS hoje empacota em `address_line_2` text. Separar dá clareza pra NF-e + Cadastro UI. |
| `SITUACAO` | `situacao` (string 30 null) ou enum (`vip`/`blacklist`/`atencao`/`prospect`) | Wagner ainda não escolheu — se enum, Wave G já tem `tags` JSON e `vip` bool cobrindo parcial. Preferência: **manter** `tags` JSON + adicionar `situacao` string livre só pro caso Delphi. |
| `BLOQUEADO` | `bloqueado` (bool default 0) | Bloqueia cobrança/venda. Sells + Financeiro consultam. Hoje só `contact_status` enum cobre — não basta (cliente pode estar `active` mas `bloqueado`). |
| `PRIORIDADE_PRODUCAO` | `prioridade_producao` (tinyint 0-5 null) | Modules/ProducaoOficina + Oficinas consultam; UI rating 5 estrelas. |
| `LIMITE_DESCONTO` | `limite_desconto_percentual` (decimal 5,2 null) | Sells/checkout consulta; impede desconto > limite no PDV. |
| `BOLETO_PERC_DESCONTO_PADRAO` | `boleto_desconto_pontualidade_pct` (decimal 5,2 null) | Modules/Financeiro + Asaas Boleto. |
| `COBRAR_CUSTO_BOLETO` | `cobrar_custo_boleto` (bool default 0) | Asaas / boleto config per-cliente. |
| `FATURA_PREVISAO` | `fatura_previsao` (date null) | Modules/Crm/Financeiro forecast. |
| `OBSERVACAO` | usar `obs_comercial` existente (Wave drawer) | **Já existe** — só mapear no importer. |
| `TIPO_CONTRIBUINTE` | usar `indicador_ie` existente | **Já existe** — Wave 2026-05-21. Mapear 1/2/9 idêntico. |
| `CRT` | usar `regime` existente | **Já existe** — Wave 2026-05-21. Normalizar string Delphi pra enum `simples`/`presumido`/`real`/`mei`. |
| `ISS_RETIDO` | `iss_retido` (tinyint 1/2 null) | NFSe — Modules/NfeBrasil. |
| `ANIVERSARIO` | `aniversario_mmdd` (string 5 null `MM-DD`) | UPOS já tem `dob` mas Delphi distingue **comemoração** (sem ano) de **nascimento**. PF tem ambos. |
| `ETIQUETA` | DESCARTAR pra Bucket D (sem uso oimpresso) ou mapear `tags` JSON com chip `imprime_etiqueta` | Marginal. |
| `PESSOA_ASSOCIADO_CODIGO` | `parent_contact_id` (bigint FK nullable INDEX) | Rede filiais/matriz. Crm + Sells. Self-referencing FK. **Importer Vargas precisa 2-pass** (1ª INSERT, 2ª resolve FK via legacy_id). |
| `PESSOA_REPRESENTANTE_CODIGO` | `sales_rep_contact_id` (bigint FK nullable) | Rep responsável. Comissão Sells. Self-referencing FK (apontando pro contact que tem `is_representative=1`). |
| `CODPRODUTO_TABELA` | usar `customer_group_id` existente (FK UPOS) | **Já existe** — UPOS canon. Migrar `PRODUTO_TABELA` Delphi → `selling_price_groups` UPOS. |
| `TIPO_PADRAO` | `primary_role` (enum 'customer'/'supplier'/'employee'/'representative' null) | Qual flag `is_X` exibir como principal na UI. Default = primeira flag=1. |

**Total Bucket A: ~14 colunas novas + 4 mapeamentos pra existentes.** 1 migration `Modules/Crm/Database/Migrations/2026_05_XX_extend_contacts_wave_d_legacy_absorption.php` (ou em raiz dado escopo cross-module — Wagner decide).

### Bucket B — SATÉLITE `contact_profile_legacy` (tabela 1:1 nova) ✅ CONFIRMADO

Campos de retro-rastreabilidade Delphi — auditoria, **não consultados por business logic**. Tabela 1:1 com `contact_id` FK + `business_id` (Tier 0). **Decisão Wagner 2026-05-26:** satélite (não JSON gigante em `contacts.legacy_raw`). Mantém pattern UPOS `contact_metadata` (existente como precedente). Forensics LGPD + queryability "liste cadastros migrados em janeiro/2024 do Delphi" ganham.

| Delphi PESSOAS | `contact_profile_legacy.*` | Justificativa |
|---|---|---|
| `CODIGO` (raw, com sufixo `-empresa`) | `legacy_codigo_raw` varchar(40) | `contacts.legacy_id` já guarda CNPJ normalizado — Delphi `CODIGO` é outra coisa (sequencial-empresa). Manter pra rastreio. |
| `DATACADASTRO` (Delphi) | `legacy_data_cadastro` timestamp null | Distinto de `contacts.created_at` (data migração). Pra "cliente desde 2003" no card. |
| `DT_ALTERACAO` | `legacy_dt_alteracao` timestamp null | Auditoria Delphi (não substituir `updated_at` Laravel). |
| `USUARIO_CADASTRO` / `USUARIO_ALTERACAO` | `legacy_usuario_cadastro` / `legacy_usuario_alteracao` varchar(50) null | Quem cadastrou no Delphi (string livre, pré-multi-user) |
| `SEQUENCIA_CLI` / `_FOR` / `_FUN` / `_REP` | DESCARTAR (Bucket D) | Sequência interna Delphi por papel — não precisa em Laravel (id auto-increment cobre). |
| `IS_<TIPO>` raw | DESCARTAR (Bucket D) | ADR 0188 já cobre via flags aditivas. |
| `URL_COBRANCA` / `URL_SPC` | DESCARTAR (Bucket D) | Configs Delphi por business — vão pra `business_settings` (não em contact). |
| `FONE1`/`FONE2`/`FAX` redundantes | DESCARTAR — UPOS tem `mobile`/`landline`/`tel2`/`alternate_number` | Suficiente. |
| Campos `EMAIL_*` múltiplos (cobrança/financeiro/comercial) | `legacy_emails_extras` json null | Vargas pode ter `EMAIL_COBRANCA`/`EMAIL_FINANC`/`EMAIL_COMERCIAL` separados (Delphi customiza). JSON catch-all. |
| Campos `OBS_*` múltiplos (livres por aba) | `legacy_observacoes` json null | Vargas/Martinho podem ter `OBS_FINANCEIRO`, `OBS_PRODUCAO`, `OBS_INTERNA` — JSON catch-all. |

**Schema satélite proposto:**
```sql
contact_profile_legacy (
  id bigint PK,
  contact_id bigint FK contacts.id UNIQUE ON DELETE CASCADE,
  business_id int FK business.id INDEX,  -- Tier 0 redundante p/ scope direto
  legacy_source enum('wr-comercial-delphi','outro') default 'wr-comercial-delphi',
  legacy_codigo_raw varchar(40) null,
  legacy_data_cadastro timestamp null,
  legacy_dt_alteracao timestamp null,
  legacy_usuario_cadastro varchar(50) null,
  legacy_usuario_alteracao varchar(50) null,
  legacy_emails_extras json null,
  legacy_observacoes json null,
  legacy_raw json null,  -- catch-all do dump bruto pra forensics futura
  created_at, updated_at
)
```

**Total Bucket B: ~7-10 colunas novas em tabela 1:1.** 1 migration. Eloquent `Contact::hasOne(ContactProfileLegacy::class)`.

### Bucket C — JSON `custom_field1..10` ou `tags` (já existentes)

Campos esparsos com baixíssima cardinalidade — usar slots UPOS já existentes (`custom_field1..10`) ou `tags` JSON (Wave drawer).

| Delphi | Destino | Notas |
|---|---|---|
| `ETIQUETA` (`S/N`) | `tags` JSON entry (`["imprime_etiqueta"]`) | 1 bit info. |
| `OBSERVACAO` (livre) | `obs_comercial` (já existe — text) | Já temos. |
| Custom field cliente-específico (ex Martinho: campos oficina vazados) | DESCARTAR ou `custom_field<N>` | Slot escape pra ad-hoc. |

**Total Bucket C: 0 colunas novas — reusa slots.**

### Bucket D — DESCARTAR

Campos que **não entram em produção** (Wagner pode justificar exceção pontual).

| Delphi | Razão |
|---|---|
| `PLACA`, `MARCAMODELO`, `ANO`, `RENAVAM`, `CHASSI` em PESSOAS | **Vazaram de oficina** (mesma pegadinha vista em `MENSALIDADE_FINANCEIRO` — gráficas não usam). Modules/OficinaAuto tem `vehicles` separado (ADR 0137 + 0194). |
| `SEQUENCIA_<TIPO>` (todos) | Resolvido por `id` auto-increment + flags `is_X`. |
| `IS_<TIPO>` raw (já mapeado) | Resolvido por ADR 0188 flags aditivas. Mapping no importer. |
| `URL_COBRANCA` / `URL_SPC` | Configs por business — `business_settings` (não em contact). |
| Campos 100% nulos em todos os cadastros Vargas | Auditar no dump real antes de descartar definitivamente. |
| Campos auto-Delphi (`MODULO`, flags internas UI) | Não fazem sentido fora do Delphi. |

### Bucket E — VAULTWARDEN

**PESSOAS tipicamente NÃO tem secrets** (diferente de `EMPRESA` — onde tem `CERTIFICADO`/`CERTIFICADO_SENHA`/`WEB_SERVICE_SENHA`/`NFCE_*_CSC`/`APP_SENHA`/`NFE_NUMSERIE` — já tratado em `import-empresas.py:54-59` lista `EMPRESA_SECRET_FIELDS`).

**Verificar no dump Vargas:** se houver `SENHA_<X>` qualquer em PESSOAS (raro), redirecionar pra Vaultwarden e **não migrar** pro MySQL. Pattern: `LegacyImporter::redactAndStashSecret($field, $value, $vault_key)`.

## 5. Top 10 campos prioritários (revalidados por TIPO — Wagner 2026-05-26)

Os 10 que mais bloqueiam migração Vargas (impacto × volume × usabilidade UI). Coluna "Aplica a" mostra pra quais tipos o campo é relevante — alguns campos só fazem sentido pra cliente, outros pra fornecedor, outros pra transportador.

| # | Delphi PESSOAS | Laravel destino | Bucket | Aplica a | Razão |
|---|---|---|---|---|---|
| 1 | `BLOQUEADO` | `contacts.bloqueado` (bool) | A | CLI + FOR | **Bloqueia checkout/cobrança** — Sells/Financeiro consultam. Sem isso, cliente bloqueado no legacy vai vender no oimpresso. Fornecedor bloqueado bloqueia Compras. |
| 2 | `LIMITE_DESCONTO` | `contacts.limite_desconto_percentual` (decimal) | A | CLI apenas | PDV impede desconto > limite no cliente. Não tem sentido pra fornecedor/funcionário. |
| 3 | `PESSOA_REPRESENTANTE_CODIGO` | `contacts.sales_rep_contact_id` (FK self) | A | CLI apenas | **Comissão Sells** depende. Sem isso, perde rastreio "venda do João pro cliente Vargas". Critical migrar 2-pass (FK só resolve após contacts populated). Fornecedor não tem rep. |
| 4 | `PESSOA_ASSOCIADO_CODIGO` | `contacts.parent_contact_id` (FK self) | A | CLI + FOR | Rede filial/matriz. Aplica pra cliente (rede varejo) e fornecedor (grupo industrial). 2-pass. |
| 5 | `BOLETO_PERC_DESCONTO_PADRAO` | `contacts.boleto_desconto_pontualidade_pct` (decimal) | A | CLI apenas | Asaas/cobrança bancária — incentivo "paga até dia X ganha 5% desconto". Cliente recebe boleto, não fornecedor. |
| 6 | `PRIORIDADE_PRODUCAO` | `contacts.prioridade_producao` (tinyint 0-5) | A | CLI apenas | Modules/ProducaoOficina prioriza fila pelo cliente. Fornecedor não entra na fila. |
| 7 | `SITUACAO` | usar `contacts.tags` JSON + `contacts.vip` bool (já existe) | C/A | CLI + FOR | Wave drawer já tem `tags` semânticas. Mapear `SITUACAO='VIP'` → `tags:['vip']` + `vip=1`. Casos exóticos viram `tags:['blacklist']` etc. Vale pra cliente e fornecedor (fornecedor VIP = estratégico). |
| 8 | `DATACADASTRO` Delphi | `contact_profile_legacy.legacy_data_cadastro` | B | CLI + FOR + REP | "Cliente desde 2003" no card — diferencia migração (2026) de cadastro real. Crítico pra storytelling cliente fiel. |
| 9 | `OBSERVACAO` (principal) + `OBS_*` múltiplos | `contacts.obs_comercial` (já existe) + `contact_profile_legacy.legacy_observacoes` JSON | A+B | CLI + FOR + REP | Texto livre — `obs_comercial` pro principal (UPOS), JSON catch-all pros adicionais Delphi (`OBS_FINANCEIRO`/`OBS_PRODUCAO`/`OBS_FORNECEDOR`). |
| 10 | `COMPLEMENTO` (endereço) | `contacts.complemento` (string 120) | A | CLI + FOR + REP | NF-e split logradouro/numero/complemento. Hoje UPOS empacota em `address_line_2`. Separar dá clareza fiscal. |

**Campos por tipo (resumo):**
- **CLI-only:** `LIMITE_DESCONTO`, `PESSOA_REPRESENTANTE_CODIGO`, `BOLETO_PERC_DESCONTO_PADRAO`, `PRIORIDADE_PRODUCAO`, `FATURA_PREVISAO`, `COBRAR_CUSTO_BOLETO`.
- **FOR-only candidatos** (revisar no dump real Vargas): `OBSERVACAO_FORNECEDOR` (se existir como col separada), `PRAZO_ENTREGA_PADRAO`, `CONDPGTO_FORNECEDOR`.
- **REP-only** (representante): hoje cabe em `contacts` com `is_representative=1` — campos como `COMISSAO_PERCENTUAL_PADRAO` ficam pra Fase 2 (col `commission_pct` nova ou Modules/Crm pivot table).
- **T-only (transportador):** `ANTT` (registro ANTT), `PLACA_VEICULO_PROPRIO`, `RNTRC`, `CIOT` — campos típicos MDFe. **NÃO entram em `contacts`** — vão pra `transportadoras` (Fase 2 — schema atual mínimo precisa extensão pra absorver isso).
- **FUN-only (funcionário):** `CTPS`, `PIS`, `DATA_ADMISSAO`, `SALARIO_BASE`, `CARGO_INTERNO` — **NÃO entram em `contacts`** — vão pra `users` + extensão Modules/Hrm (Fase 2).

## 6. Proposta de ADR 0197 (outline)

**Número sugerido:** **0197** (próximo livre — 0195 tomado por PR #1714 feedback adaptativo + 0196 tomado por PR #1715 Fase B; 0193 não existe).

**Título:** `Extensão contacts pra absorver schema legacy PESSOAS — Fase 1 (Bucket A + B, roteamento por tipo documentado)`

**Categoria:** arq · `related_adrs:` 0093 (multi-tenant Tier 0), 0178 (canon BR fiscal), 0179 (drawer Wave B+C), 0188 (multi-type flags), 0061 (zero auto-mem), 0131 (tiering), adr-crm-arq-0001-crm-estende-contacts-do-ultimatepos.

**Escopo Fase 1 documentado:** CLI + FOR + REP via `contacts` (Bucket A + B). FUN (→ `users` HRM) e T (→ `transportadoras`) ficam Fase 2 — roteamento por tipo já documentado na seção §1.bis pra agente futuro não re-pensar.

### Contexto (bullets)
- Larissa ROTA LIVRE biz=4 já operando — primeira migração real de cliente legacy WR Comercial pendente (Vargas é fonte canônica do schema).
- `PESSOAS` Firebird tem 329 cols físicas → ~30 canônicas + ~285 dinâmicas (multi-tipo) + ~14 vazadas/legacy debt.
- 8 waves Maio/2026 (ADRs 0178/0179/0188 + city_code + numero + SEFAZ + role flags) já cobriram ~28 cols. Resta **~14 cols Bucket A + tabela `contact_profile_legacy` Bucket B**.
- Importer canônico já existe (`import-empresas.py` 4 entidades próprias OK; `import-contacts-from-venda.py` Martinho 1.4k contacts via VENDA inline OK). Próximo passo é Vargas — schema completo PESSOAS, com FKs `PESSOA_ASSOCIADO_CODIGO` + `PESSOA_REPRESENTANTE_CODIGO`.

### Decisão (bullets)
- **Adotar pattern híbrido A+B+C+D+E** (ver gap tabela §4).
- Bucket A: 1 migration aditiva Wave D adicionando 14 colunas em `contacts` (todas nullable, defaults seguros, idempotente Schema::hasColumn).
- Bucket B: 1 migration nova criando `contact_profile_legacy` 1:1 com `contacts` (FK CASCADE, UNIQUE constraint, `business_id` redundante p/ scope direto Tier 0).
- Bucket C: reuso `tags` JSON + `obs_comercial` + `custom_field1..10` — zero migration.
- Bucket D: descarte explícito documentado (campos vazados oficina, SEQUENCIA_*, IS_* raw, URL_COBRANCA/URL_SPC → business_settings).
- Bucket E: pattern `EMPRESA_SECRET_FIELDS` extendido pra PESSOAS — `PiiRedactor::redactAndStashSecret($value, $vault_key)` (se aparecer campo `SENHA_*`).
- Importer Vargas 2-pass (1ª INSERT contacts, 2ª resolve `parent_contact_id` + `sales_rep_contact_id` via lookup `legacy_id`).
- **Eloquent `App\Contact` $casts atualizado** + relação `hasOne(ContactProfileLegacy::class)`.
- **Pest test** schema check (`tests/Feature/Schema/ContactsBucketALegacyTest.php`) — fillable, casts, FK self-referencing, multi-tenant scope.

### Consequências (bullets)
- **Positivas:** 1 modelo `Contact` único (zero duplicação `clientes` paralelos — ADR ARQ-0001 Crm + 0179 reforçado); retrocompat 100% (todas migrations idempotentes + colunas nullable); auditoria Delphi preservada em satélite sem inchar contacts; importer Vargas pronto pra rodar dry-run com schema completo.
- **Negativas:** +14 colunas em `contacts` (já está em ~95 → ~109) — Wagner pode achar bloated; tabela `contact_profile_legacy` adiciona 1 JOIN quando precisa exibir "cliente desde" (mitigação: lazy-load Eloquent).
- **Neutras:** importer Vargas precisa 2-pass (overhead ~5min — aceitável); decisão `situacao` vs `tags` JSON pode ser revisitada após primeiro cliente em produção.
- **Multi-tenant Tier 0 (ADR 0093):** preservado — `business_id` em `contacts` (já existe + indexado composto via Wave 2026-05-13 e Wave drawer); `contact_profile_legacy.business_id` redundante mas garantido nos query scopes.

## 7. Próximos passos (5 bullets, em ordem)

1. ✅ **Q1 RESOLVIDA Wagner 2026-05-26** — Bucket B `contact_profile_legacy` 1:1 confirmado (não JSON gigante).
2. ✅ **Q2 RESOLVIDA Wagner 2026-05-26** — Escopo Fase 1 = CLI+FOR+REP em `contacts` (Bucket A+B); FUN → `users`, T → `transportadoras` ficam Fase 2 com roteamento documentado §1.bis.
3. **Dump real Firebird Vargas** — Wagner roda `python scripts/legacy-migration/dump-pessoas-schema.py --alias=Vargas` (script novo entregue 2026-05-26 nesta sessão, abaixo) → gera `output/pessoas-schema-{cliente}-{ts}.txt` (329 cols + tipos + cardinalidade por TIPO + nullability real top 30 cols). **15min de execução, ZERO PII** (schema-only por design). Output cola no Claude pra finalizar Bucket B (~7-10 cols satélite confirmadas pelo dump real, talvez +1-2 ajustes).
4. **Felipe escreve ADR 0197 final** (~1h) baseado neste outline + dump real Vargas. PR isolado, sem código junto.
5. **Wagner merge ADR 0197** → Felipe abre 2 PRs separados (commit-discipline Tier A — 1 PR = 1 intent):
   - **PR 1 — Bucket A migration + Eloquent + Pest** (~2-3h IA-pair Felipe; ≤300 linhas)
   - **PR 2 — Bucket B migration + Model `ContactProfileLegacy` + relação + Pest** (~2h IA-pair Felipe; ≤200 linhas)
6. **Felipe roda importer Vargas dry-run** (`import-pessoas-from-firebird.py` — novo, baseado em `import-empresas.py` + `import-contacts-from-venda.py` patterns) → audit JSON em `scripts/legacy-migration/output/audit-pessoas-biz4-vargas-{ts}.json` → Wagner aprova → roda local Laragon → smoke biz=4 → prod canary 24h → cleanup.

### Riscos Tier 0
- ❌ **Quebrar multi-tenant (ADR 0093 IRREVOGÁVEL):** mitigação via `business_id` redundante em `contact_profile_legacy` + Pest test enforcing scope.
- ❌ **PII vazar em log / audit JSON:** mitigação via `PiiRedactor::redact()` em todos campos `CNPJCPF`/`EMAIL`/`FONE*` antes de `json.dumps()` (já pattern obrigatório em `import-contacts-from-venda.py`).
- ❌ **Subir senha/certificado pro MySQL** (campo Delphi inesperado): mitigação via blacklist explícita análoga a `EMPRESA_SECRET_FIELDS` — `PESSOAS_SECRET_FIELDS` lista no importer.
- 🟡 **FK self-referencing 2-pass falhar parcial** (cliente A aponta pra cliente B que aponta pra A — ciclo, ou pra ID inexistente): mitigação via `LEFT JOIN` no SELECT Firebird antes + warning ao invés de hard-fail.

## 8. Pendência única antes de finalizar ADR 0197

**Aguardando dump schema-only Vargas pra finalizar Bucket B** (~7-10 cols satélite confirmados pelo dump real). Wagner roda:

```bash
cd D:/oimpresso.com
python scripts/legacy-migration/dump-pessoas-schema.py --alias=Vargas
```

Output texto em `scripts/legacy-migration/output/pessoas-schema-Vargas-{ts}.txt` — cola no chat com Claude e Bucket B é finalizado em 1 turn. **ZERO PII no output** (schema-only por design — script não permite vazar valores mesmo se rodar errado).

---

**Última atualização:** 2026-05-26 — análise canônica gap PESSOAS→contacts (refinada pós-decisões Wagner). Substitui inferências anteriores sobre tamanho do gap (Wagner achou ">50 campos" — real é ~14 cols Bucket A + tabela satélite + roteamento documentado pra FUN/T fora de `contacts`, dado que 8 waves Maio/2026 já cobriram quase tudo no plano canônico).

---

## Update 2026-05-27 — ADR 0197 + 0198 + RUNBOOK Martinho aceitos

Wagner em 2026-05-27 direcionou execução sem perguntas adicionais ("não me pergunte resolva"). Esta análise virou contrato canon via 3 documentos:

- **[ADR 0197](../decisions/0197-extend-contacts-absorcao-pessoas-legacy.md)** — formaliza Bucket A + B + roteamento por tipo CLI/FOR/REP em `contacts` (Fase 1); FUN→`users` + T→`transportadoras` ficam Fase 2 com mapping documentado. `aceito` 2026-05-27.
- **[ADR 0198](../decisions/0198-hot-cold-tiering-migracao-transacional-legacy.md)** — endereça preocupação Wagner sobre gargalo escala 8-10 clientes × 6-10 GB cada. 5 mitigações: (1) Object Storage XMLs/DANFEs, (2) MySQL partitioning por `(business_id, YEAR(date))`, (3) tabela `transactions_archive` opt-in pra >5 anos, (4) cleanup-first financeiro (não migrar write-off), (5) plano Hostinger upgrade ANTES do 2º cliente. `aceito` 2026-05-27.
- **[RUNBOOK Martinho Fase 3+4](../requisitos/Officeimpresso/RUNBOOK-migracao-martinho-fase3-fase4.md)** — executável Felipe pra migrar VENDA (44.709) + FINANCEIRO (cleanup-first) do biz=164 piloto. Pre-conditions exigem ADR 0197 + 0198 mergeados primeiro. `rascunho` (vira `ativo` no primeiro dry-run aprovado).

**Cliente piloto canônico:** Martinho biz=164 (não Vargas) — Fase 1 + Fase 2 já em prod 2026-05-13, ordem cutover documentada [ANALISE-CROSS-CLIENTE](../research/clientes-legacy-officeimpresso/_ANALISE-CROSS-CLIENTE.md). Larissa biz=4 NÃO entra no escopo de migração legacy (ROTA LIVRE nasceu digital, sem Firebird).

**Pendência única final pra fechar Fase 1 ADR 0197:** dump real Vargas via [`scripts/legacy-migration/dump-pessoas-schema.py`](../../scripts/legacy-migration/dump-pessoas-schema.py) confirma 1-2 cols TBD em `contact_profile_legacy`. Não bloqueia início das migrations Bucket A — Wagner roda quando puder.

**Pendência única final pra fechar Fase 0 ADR 0198:** 3 queries diagnóstico via phpMyAdmin Hostinger (tamanho DB atual + top 10 tabelas + volume biz=164 hoje). Trava o gate "plano Hostinger atual aguenta vs precisa upgrade ANTES da Fase 3 Martinho".
