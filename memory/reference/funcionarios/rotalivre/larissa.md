---
slug: larissa
cliente_slug: rotalivre
first_name: Larissa
nome_completo_real: TBD-perguntar-wagner
relacao: dona/operadora principal (sócia única ME)
role_operacional: dona-operadora-vendas-balcao
cargo_formal: Sócia administradora
user_id_oimpresso: 10
username_oimpresso: larissa-04
papel_canary: cliente-piloto-vivo
acesso_sistemas:
  - sistema: oimpresso
    role: Admin#4 (biz=4)
preferencias_ux:
  - persona_nao_tecnica
  - monitor_1280px
  - locale_pt_br_datatables
  - opera_diariamente_14h_17h_SP
sensibilidades:
  - decorou_horarios_shift_3h_format_date_adr_0066
  - transaction_date_retroativo_e_normal_fluxo_balcao
  - regressao_visual_datetime_e_trauma
  - 21_colunas_estouravam_1280px_pre_2026_04_24
pii_vault_ref: vault://rotalivre/larissa
data_primeiro_contato: 2021-02-01
ultima_atualizacao: 2026-05-14
---

# Larissa (rotalivre)

Dona e operadora principal ROTA LIVRE (business_id=4) · sócia única ME · opera diariamente desde 2021 · cliente piloto vivo do oimpresso novo. Decorou comportamento legacy — regressões visuais viram trauma.

## 1. Papel atual

- **Role operacional:** dona/operadora principal — registra vendas balcão, controla estoque, fecha caixa, emite NFCe
- **Responsabilidade primária:** operação diária loja em Termas do Gravatal/SC
- **Cadeia decisão:** decide tudo sozinha (sem cadeia hierárquica — sócia única ME)
- **Tempo na empresa:** desde fundação (2021-02-01 cadastro oimpresso · primeira venda 2021-05-13)

## 2. Acesso a sistemas

| Sistema | role/permissão | user_id/login | observação |
|---|---|---|---|
| oimpresso | `Admin#4` (biz=4) | id=10 `larissa-04` | Permissão `location.4` ou `permitted_locations='all'` |

Único sistema de gestão · sem Delphi legacy paralelo · operação 100% no oimpresso desde 2021.

## 3. Preferências UX

- **Monitor:** ~1280px (tela compacta — telas com 21 colunas estouram layout)
- **Persona:** **não-técnica** — não pesquisa documentação, opera por hábito
- **Idioma:** PT-BR (DataTables locale pt-BR aplicado em 2026-04-24)
- **Velocidade de aprendizado:** estável — decorou estado atual e prefere preservar
- **Plataforma:** desktop (operação balcão fixa)

## 4. Sensibilidades

1. **Decorou shift +3h `format_date`** — bug histórico que ela usa como referência mental dos horários. Corrigir = regressão percebida ("vendas antigas mudaram 3h"). [ADR 0066](../../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md) formaliza preservação intencional.

2. **`transaction_date` retroativo é fluxo dela** — digita hora passada (até 17h diff do `created_at`) em vendas balcão lote final do dia. NÃO é bug. Não tentar "corrigir" por algoritmo.

3. **Monitor 1280px** — qualquer feature nova precisa caber. Tela `/sells` precisou `columnDefs: { targets: [11,12,21,22,23], visible: false }` em 2026-04-24 pra ficar usável.

4. **Fluxo depende de `default_location` preenchido** — `/sells/create` só libera busca de produto depois disso. Role `Vendas#4` precisa de `location.4` (corrigido em 2026-04-24 via SSH direto).

5. **Comunicação via Wagner** — Larissa NÃO contata Claude/equipe direto. Sempre passa pelo Wagner que filtra+repassa. Mudanças significativas precisam ser comunicadas a Wagner ANTES pra ele preparar Larissa.

## 5. Histórico de interações

### 2021-02-01 — Cadastro inicial
business_id=4 criado · user id=10 `larissa-04` ativado.

### 2021-05-13 — Primeira venda
Operação real começa.

### 2026-04-24 — Bateria de bugs em `/sells` reportada via Wagner
Wagner reportou queixas acumuladas da Larissa. 5 bugs resolvidos em sequência (lang + DataTables locale · 1280px columnDefs · timezone frontend · format_date revertido por trauma · disabled false Form shim + location role).

Aprendizado: **revertimos `format_date` no mesmo dia** porque Larissa decorou horários errados. ADR 0066 formaliza.

### 2026-05-14 — Não viu o incidente cross-business
Import-estoque Martinho contaminou 5 VLDs ROTA LIVRE (CARDIGAN M/G · JAQUETA P/M · BLUSA P/G — total 3 unidades). Recovery via backup Hostinger antes de Larissa notar. Detalhes: [feedback-importer-cross-business-bug.md](../../feedback-importer-cross-business-bug.md).

## 6. Refs

- Cliente: [rotalivre](../../clientes/rotalivre.md)
- [ADR 0066 — format_date shift +3h preservado](../../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)
- [ADR 0093 — Multi-tenant isolation Tier 0](../../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [feedback-importer-cross-business-bug.md](../../feedback-importer-cross-business-bug.md) — incidente 2026-05-14
- Sessions: `memory/sessions/2026-04-24-*.md` (bateria de bugs)
