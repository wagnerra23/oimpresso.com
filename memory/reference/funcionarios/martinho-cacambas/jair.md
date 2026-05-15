---
slug: jair
cliente_slug: martinho-cacambas
first_name: Jair
nome_completo_real: TBD-perguntar-wagner
relacao: dono majoritário · CASADO com Kamila (esposa · #2 decisora) · sócio com Martinho (sócio · pai da Lara)
role_operacional: decisor-estrategico-empresa
cargo_formal: Sócio majoritário
user_id_oimpresso: null
username_oimpresso: null
papel_canary: decisor-principal
acesso_sistemas:
  - sistema: Office Comercial Delphi (WR Sistemas legacy)
    role: dono/diretor (acesso amplo)
preferencias_ux:
  - persona_estrategico_nao_operacional
  - prefere_endosso_presencial
sensibilidades:
  - endossa_via_pessoas_chave_negocio (Kamila esposa · Martinho sócio)
  - prefere_visao_consolidada_negocio
  - co-design_20km_viavel_preferido
pii_vault_ref: vault://martinho-cacambas/jair
data_primeiro_contato: 2026-05-14
ultima_atualizacao: 2026-05-14
---

# Jair (martinho-cacambas)

**Dono majoritário** MARTINHO CAÇAMBAS LTDA · **casado com Kamila** (esposa · #2 decisora · operação POS Delphi) · sócio com Martinho (que dá nome à empresa · pai da Lara) · endossou explicitamente migração oimpresso em 2026-05-14 noite quando Felipe foi presencial. Decisor formal · não-operacional.

> ⚠️ **Hierarquia decisão Martinho Caçambas LTDA:** Jair (#1 dono) > **Kamila (#2 esposa Jair)** > Lara (filha do Martinho · estoque) + Dani (financeiro). Wagner reforçou 2026-05-14 noite — *"Kamila esposa do Jair. não esqueça ela quem manda depois do Jair"*.

## 1. Papel atual

- **Role operacional:** decisor estratégico (não opera sistema dia-a-dia)
- **Responsabilidade primária:** decisão de migração + investimento + relação comercial com fornecedores ERP
- **Cadeia decisão:** topo da pirâmide · dono majoritário · valida operacional com Kamila (esposa #2)
- **Família/negócio:** marido de Kamila (#2 decisora) · sócio com Martinho (que é pai da Lara) — Lara NÃO é filha do Jair

## 2. Acesso a sistemas

| Sistema | role/permissão | user_id/login | observação |
|---|---|---|---|
| Office Comercial Delphi (WR Sistemas legacy) | dono/diretor (acesso amplo) | (legacy, não levantado) | Sistema atual da empresa |
| oimpresso | **não tem user ainda** | — | Provavelmente não criar (perfil decisor não-operacional · ele endossa, Lara+Dani operam) |

Decisão pendente Wagner: criar user `jair-164` ou manter Jair apenas como decisor sem login? Resposta provavelmente "manter sem login" pois ele não opera.

## 3. Preferências UX

- **Persona:** estratégica não-operacional — endossa via observação das filhas e Dani
- **Co-design presencial 20km viável** — prefere Felipe/Wagner irem pessoalmente (validou em 2026-05-14)
- **Idioma:** PT-BR

## 4. Sensibilidades

1. **Endossa via pessoas-chave da família** — Kamila propôs dual-system em 2026-05-14 15:51, Felipe foi presencial ~16:30, Jair endossou na presença da família. Decisão de migração passa por validação familiar.

2. **Pausou Highsoft (concorrente) pela escolha do oimpresso** — Kamila estava avaliando Highsoft, Jair direcionou pra oimpresso após reunião presencial Felipe. Compromisso explícito.

3. **Co-design presencial > entrega remota** — Jair valida proposta de Wagner ir presencial 20km regular sem hospedagem. Approach "made together" alinha com filhas (Lara/Dani também pediram presencial).

4. **Decisor formal · não operador** — Jair NÃO valida features individuais nem entra em tela. Validação operacional fica com Lara (estoque) + Dani (financeiro). Wagner deve evitar pedir feedback técnico de Jair direto.

## 5. Histórico de interações

### 2026-05-14 ~16:30 — Felipe presencial Martinho · Jair endossou
Felipe foi presencial Martinho. **JAIR (dono majoritário) endossou** migração oimpresso explicitamente · Kamila pausou avaliação **Highsoft** (concorrente) · co-design 20km viável confirmado pelo Jair.

Sessão crítica — marco que mudou status de "qualificado tentativo" pra **piloto-ativo**. Detalhes: [handoff 2026-05-14 18:00](../../../handoffs/2026-05-14-1800-martinho-canary-prep-jair-endossou.md).

## 6. Refs

- Cliente: [martinho-cacambas](../../clientes/martinho-cacambas.md)
- [Handoff 2026-05-14 18:00](../../../handoffs/2026-05-14-1800-martinho-canary-prep-jair-endossou.md)
- [Session log 2026-05-14](../../../sessions/2026-05-14-martinho-canary-prep-massive.md)
- [ADR proposal dual-sync](../../../decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md)
- [CHECKLIST pós-reunião](../../../requisitos/OficinaAuto/demo-martinho-2026-05-13/CHECKLIST-POS-REUNIAO.md)
- [Perfil Martinho legacy (anonimizado)](../../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)
