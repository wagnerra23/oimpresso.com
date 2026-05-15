---
slug: eduardo
cliente_slug: martinho-cacambas
first_name: Eduardo
nome_completo_real: TBD-perguntar-wagner
relacao: funcionário · vendedor
role_operacional: vendedor-externo
cargo_formal: Vendedor
user_id_oimpresso: 298
username_oimpresso: eduardo-164
papel_canary: continua-legacy
acesso_sistemas:
  - sistema: Office Comercial Delphi (WR Sistemas legacy)
    role: vendedor
  - sistema: Google Form Checklist Mecânica (8 páginas)
    role: preenche-em-campo
preferencias_ux:
  - persona_operacional_campo
sensibilidades:
  - continua_delphi_canary_inicial
pii_vault_ref: vault://martinho-cacambas/eduardo
data_primeiro_contato: 2026-05-14
ultima_atualizacao: 2026-05-14
---

# Eduardo (martinho-cacambas)

Funcionário **vendedor externo** MARTINHO CAÇAMBAS LTDA · pareado funcionalmente com Rodrigo (mesmo papel canary continua-legacy). User `eduardo-164` (id=298) ativo em prod biz=164 via integração Delphi.

## 1. Papel atual

- **Role operacional:** vendedor externo · visita cliente · fecha locação caçamba
- **Responsabilidade primária:** prospecção + vendas em campo (paridade com Rodrigo)
- **Cadeia decisão:** subordinado à operação · reporta resultados pra Kamila/Dani
- **Ferramentas:** Google Form Checklist Mecânica 8 páginas (compartilhada com Rodrigo + 2 vendedores adicionais)

## 2. Acesso a sistemas

| Sistema | role/permissão | user_id/login | observação |
|---|---|---|---|
| Office Comercial Delphi (WR Sistemas legacy) | vendedor | (legacy, não levantado) | Sistema atual · **continua aqui** no canary inicial |
| oimpresso | (criado) biz=164 | id=298 `eduardo-164` | User existe em prod (importado via integração Delphi) · **NÃO entra no canary 19/maio** |
| Google Form Checklist Mecânica | preenche em campo | (Google account) | 8 páginas · usado por 4 vendedores ativos |

## 3. Preferências UX

- **Persona:** operacional de campo · mobile-first (similar Rodrigo)
- **Idioma:** PT-BR
- **Plataforma:** mobile em campo + desktop Delphi escritório
- **Futuro:** Fase 4 inclui PWA mecânico campo (substituir Google Form) — Eduardo + Rodrigo serão usuários alvo

## 4. Sensibilidades

1. **Continua Delphi no canary inicial** — cutover dele depende de validação Lara+Dani primeiro. Sem pressão.

2. **Future-state PWA mecânico campo (Fase 4)** — Eduardo + Rodrigo + 4 mecânicos (Leonardo · Leoni · Arthur · Ramon) são usuários alvo da substituição do Google Form 8 páginas. Não está no canary 19/maio.

3. **Dados pessoais reais TBD** — Wagner pegar com Martinho pré-canary se Eduardo for entrar na Fase 4 ou se precisar resetar senha legacy.

## 5. Histórico de interações

### 2026-05-14 manhã — User eduardo-164 (id=298) ativo em prod biz=164
11 users importados via integração Delphi (incluindo Eduardo).

### 2026-05-14 ~16:30 — Felipe presencial Martinho
Eduardo permanece em Delphi · canary inicial não inclui vendedores externos.

### 2026-05-19 (planejado) — Permanece Delphi · não entra oimpresso
Operação canary cobre apenas Lara (estoque) + Dani (financeiro) inicialmente. Cutover vendedores externos depende de validação champions.

## 6. Refs

- Cliente: [martinho-cacambas](../../clientes/martinho-cacambas.md)
- [CHECKLIST pós-reunião](../../../requisitos/OficinaAuto/demo-martinho-2026-05-13/CHECKLIST-POS-REUNIAO.md) — Eduardo identificado entre vendedores ativos
- [Perfil Martinho legacy](../../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)
- [Handoff 2026-05-14 18:00](../../../handoffs/2026-05-14-1800-martinho-canary-prep-jair-endossou.md)
- Funcionário relacionado: [rodrigo](rodrigo.md) (outro vendedor · mesmo papel canary)
