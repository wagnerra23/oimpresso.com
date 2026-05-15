---
slug: martinho
cliente_slug: martinho-cacambas
first_name: Martinho
nome_completo_real: TBD-perguntar-wagner
relacao: sócio · dá nome à empresa · PAI da Lara (champion-oimpresso estoque) · sócio com Jair (dono majoritário casado com Kamila)
role_operacional: decisor-secundario-comercial
cargo_formal: Sócio
user_id_oimpresso: null
username_oimpresso: null
papel_canary: decisor-secundario
acesso_sistemas:
  - sistema: Office Comercial Delphi (WR Sistemas legacy)
    role: sócio (acesso amplo)
preferencias_ux:
  - persona_comercial_relacionamento
  - reuniao_presencial_topou_imediato
sensibilidades:
  - aprovou_piloto_na_primeira_reuniao_13_maio
  - prometeu_migrar_tudo
pii_vault_ref: vault://martinho-cacambas/martinho
data_primeiro_contato: 2026-05-13
ultima_atualizacao: 2026-05-14
---

# Martinho (martinho-cacambas)

Sócio MARTINHO CAÇAMBAS LTDA · dá nome à empresa · **pai da Lara** (champion-oimpresso estoque) · primeiro a topar testar oimpresso (reunião 2026-05-13 10h Wagner+Martinho). Decisor comercial complementar ao Jair (dono majoritário · casado com Kamila #2 decisora).

> ⚠️ **Hierarquia decisão Martinho Caçambas LTDA:** Jair (#1 dono) > **Kamila (#2 esposa Jair)** > Lara (filha do Martinho · estoque) + Dani (financeiro). Martinho é sócio + pai da Lara, mas validação formal final passa pelo Jair (corrigido 2026-05-14 noite).

## 1. Papel atual

- **Role operacional:** decisor comercial/relacionamento (não opera sistema dia-a-dia)
- **Responsabilidade primária:** aprovação de piloto + relação com fornecedores ERP
- **Cadeia decisão:** sócio · complementa Jair (dono majoritário · #1) + Kamila (esposa Jair · #2) · validação formal final fica com Jair
- **Família/negócio:** **pai da Lara** (responsável estoque · champion oimpresso) · sócio com Jair (que é casado com Kamila)
- **Onde aparece o nome:** razão social MARTINHO CAÇAMBAS LTDA

## 2. Acesso a sistemas

| Sistema | role/permissão | user_id/login | observação |
|---|---|---|---|
| Office Comercial Delphi (WR Sistemas legacy) | sócio (acesso amplo) | (legacy, não levantado) | Sistema atual da empresa |
| oimpresso | **não tem user ainda** | — | Decisão pendente Wagner: criar ou manter sem login (perfil decisor não-operacional) |

## 3. Preferências UX

- **Persona:** comercial/relacionamento — topa testar fácil em reunião presencial
- **Idioma:** PT-BR
- **Modo de avaliação:** confiança no relacionamento + endosso Jair > análise técnica detalhada

## 4. Sensibilidades

1. **Aprovou piloto na primeira reunião (2026-05-13 10h)** — Wagner foi presencial, Martinho topou testar oimpresso · "promessa de migrar tudo". Decisão rápida baseada em relacionamento.

2. **Endosso final passa pelo Jair (dono majoritário)** — Martinho aprovou conceito 13/maio, mas validação formal veio do Jair 14/maio noite quando Felipe foi presencial. Wagner deve respeitar essa hierarquia.

3. **Não opera sistema** — feedback técnico fica com Lara (estoque) + Dani (financeiro). Wagner deve evitar pedir input técnico de Martinho direto.

## 5. Histórico de interações

### 2026-05-13 10h — Reunião Wagner + Martinho
Wagner foi presencial Martinho · apresentou oimpresso · **Martinho topou testar · "promessa de migrar tudo"**.

Decisões P0 fechadas nesta reunião:
- Paridade preço Delphi (R$ 830/m baseline)
- Escopo Fase 1: Cadastro cliente + Financeiro AR+AP + Produtos + Compra + Manifestação destinatário + Estoque + OS
- Cutover canary 7d tela por tela com filha+Dani validando
- Champions: LARA (estoque) + DANI (financeiro)

Detalhes: [CHECKLIST pós-reunião](../../../requisitos/OficinaAuto/demo-martinho-2026-05-13/CHECKLIST-POS-REUNIAO.md).

### 2026-05-13 13h — Import vehicles aplicado prod biz=164
91 caçambas + 91 service_orders importados após reunião · biz=164 MARTINHO CAÇAMBAS LTDA criado em prod Hostinger.

### 2026-05-14 — Endosso formal Jair (validação familiar)
Endosso Martinho ratificado pelo Jair (dono majoritário) presencial Felipe ~16:30. Migração entra como **piloto ativo**.

## 6. Refs

- Cliente: [martinho-cacambas](../../clientes/martinho-cacambas.md)
- [CHECKLIST pós-reunião](../../../requisitos/OficinaAuto/demo-martinho-2026-05-13/CHECKLIST-POS-REUNIAO.md)
- [Discovery Martinho](../../../requisitos/OficinaAuto/demo-martinho-2026-05-13/discovery-martinho.md)
- [Demo script](../../../requisitos/OficinaAuto/demo-martinho-2026-05-13/demo-script.md)
- [Session log 2026-05-14](../../../sessions/2026-05-14-martinho-canary-prep-massive.md)
- [Handoff 2026-05-14 18:00](../../../handoffs/2026-05-14-1800-martinho-canary-prep-jair-endossou.md)
- Funcionários relacionados: [lara](lara.md) (**filha · champion oimpresso estoque**) · [jair](jair.md) (dono majoritário · sócio · validou formalmente) · [kamila](kamila.md) (esposa do Jair · #2 decisora)
