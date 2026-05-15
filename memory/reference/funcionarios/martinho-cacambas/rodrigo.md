---
slug: rodrigo
cliente_slug: martinho-cacambas
first_name: Rodrigo
nome_completo_real: Rodrigo da Silva (parcial · confirmar com Wagner)
relacao: funcionário · vendedor
role_operacional: vendedor-externo
cargo_formal: Vendedor
user_id_oimpresso: 294
username_oimpresso: rodrigo da silva-164
papel_canary: continua-legacy
acesso_sistemas:
  - sistema: Office Comercial Delphi (WR Sistemas legacy)
    role: vendedor
  - sistema: Google Form Checklist Mecânica (8 páginas)
    role: preenche-em-campo
preferencias_ux:
  - persona_operacional_campo
  - usa_google_form_8_paginas_em_campo
sensibilidades:
  - username_com_espaco_rodrigo_da_silva_164_quirk_legacy
  - continua_delphi_canary_inicial
pii_vault_ref: vault://martinho-cacambas/rodrigo
data_primeiro_contato: 2026-05-14
ultima_atualizacao: 2026-05-14
---

# Rodrigo (martinho-cacambas)

Funcionário **vendedor externo** MARTINHO CAÇAMBAS LTDA · usa Google Form Checklist Mecânica 8 páginas em campo · continua Delphi no canary inicial. User `rodrigo da silva-164` (id=294) ativo em prod biz=164 via integração Delphi.

## 1. Papel atual

- **Role operacional:** vendedor externo · visita cliente · fecha locação caçamba
- **Responsabilidade primária:** prospecção + vendas em campo
- **Cadeia decisão:** subordinado à operação · reporta resultados pra Kamila/Dani
- **Ferramentas:** Google Form Checklist Mecânica 8 páginas (preenche em campo via celular)

## 2. Acesso a sistemas

| Sistema | role/permissão | user_id/login | observação |
|---|---|---|---|
| Office Comercial Delphi (WR Sistemas legacy) | vendedor | (legacy, não levantado) | Sistema atual · **continua aqui** no canary inicial |
| oimpresso | (criado) biz=164 | id=294 `rodrigo da silva-164` | User existe em prod (importado via integração Delphi) · **NÃO entra no canary 19/maio** |
| Google Form Checklist Mecânica | preenche em campo | (Google account) | 8 páginas · usado por 4 vendedores ativos |

**Quirk:** username `rodrigo da silva-164` contém **espaço** (não kebab-case padrão). Quirk legacy import Delphi. Pode causar problemas em URLs ou queries case-sensitive — investigar se virar bug.

## 3. Preferências UX

- **Persona:** operacional de campo · mobile-first (Google Form via celular)
- **Idioma:** PT-BR
- **Plataforma:** mobile em campo + desktop Delphi escritório
- **Futuro:** Fase 4 inclui PWA mecânico campo (substituir Google Form) — Rodrigo + Eduardo serão usuários alvo

## 4. Sensibilidades

1. **Continua Delphi no canary inicial** — cutover dele depende de validação Lara+Dani primeiro. Sem pressão.

2. **Username `rodrigo da silva-164` com espaço** — quirk import legacy Delphi. Se aparecer bug de URL/route/query, suspeitar disso primeiro.

3. **Future-state PWA mecânico campo (Fase 4)** — Rodrigo + Eduardo + 4 mecânicos (Leonardo · Leoni · Arthur · Ramon) são usuários alvo da substituição do Google Form 8 páginas. Não está no canary 19/maio.

## 5. Histórico de interações

### 2026-05-14 manhã — User rodrigo da silva-164 (id=294) ativo em prod biz=164
11 users importados via integração Delphi (incluindo Rodrigo).

### 2026-05-14 ~16:30 — Felipe presencial Martinho
Rodrigo permanece em Delphi · canary inicial não inclui vendedores externos.

### 2026-05-19 (planejado) — Permanece Delphi · não entra oimpresso
Operação canary cobre apenas Lara (estoque) + Dani (financeiro) inicialmente. Cutover vendedores externos depende de validação champions.

## 6. Refs

- Cliente: [martinho-cacambas](../../clientes/martinho-cacambas.md)
- [CHECKLIST pós-reunião](../../../requisitos/OficinaAuto/demo-martinho-2026-05-13/CHECKLIST-POS-REUNIAO.md) — Rodrigo identificado entre vendedores ativos
- [Perfil Martinho legacy](../../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)
- [Handoff 2026-05-14 18:00](../../../handoffs/2026-05-14-1800-martinho-canary-prep-jair-endossou.md)
- Funcionário relacionado: [eduardo](eduardo.md) (outro vendedor · mesmo papel canary)
