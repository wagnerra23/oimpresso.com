---
slug: kamila
cliente_slug: martinho-cacambas
first_name: Kamila
nome_completo_real: TBD-perguntar-wagner
relacao: ESPOSA do Jair (dono majoritário) — quem manda depois do Jair · #2 decisora da empresa
role_operacional: operacao-dia-a-dia-pos-vendas
cargo_formal: TBD (sócia-administradora informal?)
user_id_oimpresso: 292
username_oimpresso: kamila-164
papel_canary: continua-legacy-com-poder-decisao-2
acesso_sistemas:
  - sistema: Office Comercial Delphi (WR Sistemas legacy)
    role: operadora-pos-vendas
  - sistema: oimpresso
    role: criado prod biz=164 (planned · uso ainda não iniciado)
  - sistema: Highsoft (concorrente)
    role: avaliacao-pausada-2026-05-14
preferencias_ux:
  - persona_operacional_pos_vendas
  - decide_avaliar_alternativas_propria
  - poder_decisao_paralelo_jair_em_temas_operacionais
sensibilidades:
  - "quem manda depois do jair · NAO esquecer (Wagner explícito 2026-05-14 noite)"
  - estava_avaliando_highsoft_pausou_pelo_oimpresso
  - propos_arquitetura_dual_system_em_whatsapp_15_51
  - prefere_continuar_delphi_que_decorou_operacao
pii_vault_ref: vault://martinho-cacambas/kamila
data_primeiro_contato: 2026-05-14
ultima_atualizacao: 2026-05-14
---

# Kamila (martinho-cacambas)

**ESPOSA do Jair** (dono majoritário) · **#2 decisora da empresa — quem manda depois do Jair** (Wagner explícito 2026-05-14 noite: *"não esqueça ela quem manda depois do Jair"*). Responsável operação dia-a-dia (POS+vendas) em Delphi · estava avaliando Highsoft como concorrente quando Felipe foi presencial 14/maio e ela pausou pelo oimpresso. **Originária do insight estratégico dual-system** (WhatsApp 15:51).

> ⚠️ **Hierarquia decisão Martinho Caçambas LTDA:** Jair (dono majoritário) > **Kamila (esposa Jair · #2)** > Lara (filha do Martinho · estoque) + Dani (financeiro). Wagner reforçou 2026-05-14 noite — Kamila NÃO é filha do Jair, é **esposa** · NÃO é irmã da Lara.

## 1. Papel atual

- **Role operacional:** operação dia-a-dia · POS + vendas em Delphi
- **Responsabilidade primária:** registrar vendas + acompanhar fluxo balcão + **decisões de sistema/fornecedor (avaliação Highsoft autônoma)**
- **Cadeia decisão:** **#2 decisora — quem manda depois do Jair** · poder de veto operacional
- **Família:** esposa do Jair (dono majoritário) · NÃO é filha · NÃO é irmã da Lara · Lara é filha do Martinho (sócio)

## 2. Acesso a sistemas

| Sistema | role/permissão | user_id/login | observação |
|---|---|---|---|
| Office Comercial Delphi (WR Sistemas legacy) | operadora POS+vendas | (legacy, não levantado) | Sistema atual · continua aqui |
| oimpresso | (planned) biz=164 | id=292 `kamila-164` | User criado mas Kamila **continua Delphi** no canary inicial |
| Highsoft (concorrente) | avaliação | (externo) | **Pausou 2026-05-14** pelo oimpresso |

**Estratégia canary:** Kamila continua Delphi (operação) · Lara+Dani entram oimpresso · cutover dela depende da maturidade do oimpresso após Lara+Dani validarem.

## 3. Preferências UX

- **Persona:** operacional POS+vendas — decorou Delphi · resistência a mudar
- **Independência avaliativa:** avaliou Highsoft sozinha (não esperou Jair pedir) — sinaliza autonomia técnica
- **Comunicação:** WhatsApp pra insights estratégicos (mensagem 15:51 propondo dual-system foi via WhatsApp)
- **Idioma:** PT-BR

## 4. Sensibilidades

1. **Estava avaliando Highsoft (concorrente) — pausou pelo oimpresso** em 2026-05-14 quando Felipe foi presencial. Decisão dela. Wagner deve nutrir essa preferência sem dar como certo — Kamila pode reabrir avaliação se oimpresso decepcionar.

2. **Propôs arquitetura dual-system em WhatsApp 2026-05-14 15:51:** *"continuar usando o sistema antigo e colocar o sistema novo para a lara e a dani usar... ele consegue puchar as informações em tempo real do sistema antigo"* — origem da arquitetura **Delphi master + oimpresso viewer**. Insight estratégico que mudou todo o plano de migração.

3. **Decorou operação Delphi** — prefere continuar lá. Migrar Kamila vai exigir paciência + paridade comprovada (similar à Larissa decorou format_date). Não forçar cutover.

4. **Patrocinou Lara+Dani entrarem oimpresso** — Kamila propôs dual-system EXATAMENTE pra Lara (filha do Martinho · enteada de Kamila) + Dani (financeiro) usarem oimpresso enquanto ela continua Delphi. Sinal de confiança nas escolhas das colegas operacionais + delegação calculada (Kamila não migra, mas habilita).

## 5. Histórico de interações

### 2026-05-14 15:51 — WhatsApp insight estratégico dual-system
Kamila mandou WhatsApp pro Wagner: *"continuar usando o sistema antigo e colocar o sistema novo para a lara e a dani usar... ele consegue puchar as informações em tempo real do sistema antigo"*.

**Marco crítico:** essa mensagem deu origem à arquitetura dual-system (Delphi master + oimpresso viewer com sync near-realtime · daemon polling 5min). Ver [ADR proposal dual-sync](../../../decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md).

### 2026-05-14 ~16:30 — Felipe presencial · pausou Highsoft
Felipe foi presencial Martinho. Kamila pausou avaliação **Highsoft** (concorrente) pelo oimpresso. Decisão dela validada pelo Jair endosso.

### 2026-05-19 (planejado) — Continua Delphi no canary
Kamila + 4 vendedores continuam Delphi (operação) · Lara + Dani entram oimpresso. Cutover dela depende de validação Lara+Dani.

## 6. Refs

- Cliente: [martinho-cacambas](../../clientes/martinho-cacambas.md)
- [ADR proposal dual-sync Delphi+oimpresso](../../../decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md) — originária do insight Kamila
- [Session log 2026-05-14](../../../sessions/2026-05-14-martinho-canary-prep-massive.md)
- [Handoff 2026-05-14 18:00](../../../handoffs/2026-05-14-1800-martinho-canary-prep-jair-endossou.md)
- [Perfil Martinho legacy](../../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)
- Funcionários relacionados: [jair](jair.md) (**marido · dono majoritário · #1 decisor**) · [martinho](martinho.md) (sócio · pai da Lara) · [lara](lara.md) (filha do Martinho · enteada · champion-oimpresso · **NÃO é irmã**)

### 2026-05-14 noite — Wagner reforçou hierarquia
Wagner explícito ao Claude: *"Kamila esposa do Jair. não esqueça ela quem manda depois do Jair"*. Perfis corrigidos · estrutura familiar real: Jair (dono) casado com Kamila · Lara é filha do Martinho (sócio), NÃO filha do Jair.
