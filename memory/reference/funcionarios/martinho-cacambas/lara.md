---
slug: lara
cliente_slug: martinho-cacambas
first_name: Lara
nome_completo_real: TBD-perguntar-wagner
relacao: filha do Martinho (sócio · dá nome à empresa) · NÃO é filha do Jair · enteada/relacao-familiar de Kamila (esposa Jair) TBD precisar
role_operacional: responsavel-estoque
cargo_formal: TBD
user_id_oimpresso: null
username_oimpresso: null
papel_canary: champion-oimpresso
acesso_sistemas:
  - sistema: Office Comercial Delphi (WR Sistemas legacy)
    role: operadora-estoque
  - sistema: oimpresso
    role: Admin#164 (planned · criar pré-canary)
preferencias_ux:
  - persona_nao_tecnica (assumido por similaridade ROTA LIVRE / Larissa)
  - monitor_1280px (assumido · validar presencial)
  - pede_co_design_presencial
sensibilidades:
  - pediu_ver_wagner_desenvolvendo
  - approach_made_together_obrigatorio
  - "filha do Martinho (sócio), NÃO filha do Jair (dono) — Wagner corrigiu 2026-05-14 noite"
pii_vault_ref: vault://martinho-cacambas/lara
data_primeiro_contato: 2026-05-14
ultima_atualizacao: 2026-05-14
---

# Lara (martinho-cacambas)

**Filha do Martinho** (sócio · dá nome à empresa) · **responsável estoque** MARTINHO CAÇAMBAS LTDA · **champion canary oimpresso** ao lado da Dani (financeiro). Pediu co-design presencial com Wagner — approach "made together" obrigatório. Entra oimpresso semana 19/maio.

> ⚠️ **Estrutura familiar/empresa Martinho Caçambas LTDA:** Jair (dono majoritário) **casado com Kamila** (esposa · #2 decisora · operação POS Delphi) · Martinho (sócio · dá nome à empresa) **pai da Lara** (Lara · estoque · champion oimpresso). Wagner corrigiu 2026-05-14 noite: *"Kamila esposa do Jair. não esqueça ela quem manda depois do Jair"*. Lara NÃO é filha do Jair · NÃO é irmã da Kamila.

> ⚠️ **Nome completo real desconhecido** — Wagner precisa pegar com Martinho/Jair antes de criar user pré-canary. Marcar `nome_completo_real: TBD-perguntar-wagner`.

## 1. Papel atual

- **Role operacional:** responsável estoque (físico + lógico) · controle entrada/saída produtos
- **Responsabilidade primária:** garantir estoque correto · entender movimentações · ajustes
- **Cadeia decisão:** filha do Martinho (sócio) · decisões operacionais autônomas no estoque · hierarquia geral fica abaixo de Jair (dono) > Kamila (esposa #2)
- **Família/negócio:** filha do Martinho (sócio · dá nome à empresa) · NÃO é filha do Jair · NÃO é irmã da Kamila · relação familiar exata com Jair+Kamila TBD-perguntar-wagner (provavelmente colega/conhecida via negócio)

## 2. Acesso a sistemas

| Sistema | role/permissão | user_id/login | observação |
|---|---|---|---|
| Office Comercial Delphi (WR Sistemas legacy) | operadora-estoque | (legacy, não levantado) | Sistema atual · sai pra oimpresso 19/maio |
| oimpresso | **(planned)** Admin#164 (biz=164) | **(criar pré-canary)** | Entra oimpresso 19/maio — Wagner precisa criar user com dados reais |

**Pendência P0 Wagner segunda 19/maio:** pegar dados pessoais Lara (nome completo · CPF · email · telefone — vão pra Vaultwarden) · criar user `lara-164` em prod biz=164 antes do canary.

## 3. Preferências UX

- **Persona:** assumida **não-técnica** por similaridade ROTA LIVRE / Larissa (champion duplo + sem experiência tech prévia mencionada). **Validar presencial.**
- **Monitor:** **assumido 1280px** por padrão pequenas empresas BR. **Validar presencial.**
- **Idioma:** PT-BR
- **Co-design presencial:** PEDIU ver Wagner desenvolvendo · approach "made together" — NÃO entregar versão final pronta sem feedback contínuo dela
- **Distância viável:** 20km Wagner-Martinho · visitas regulares Wagner sem hospedagem

## 4. Sensibilidades

1. **Pediu co-design presencial** — Lara EXPLICITAMENTE pediu ver Wagner desenvolvendo. Não é confiança baixa, é estilo de aprendizado. Wagner deve agendar visitas regulares 20km durante canary.

2. **Approach "made together" obrigatório** — entregar feature pronta sem ela ver o processo = quebra de contrato implícito. Feedback contínuo dela > entrega final perfeita.

3. **Não confundir com Kamila** — Kamila (**esposa do Jair · NÃO irmã da Lara**) é operação POS+vendas em Delphi e prefere continuar lá. Lara é filha do Martinho (sócio) · estoque · entra oimpresso. Perfis operacionais e preferências diferentes · papéis familiares também diferentes.

4. **Champion patrocinada por Kamila** — Kamila (esposa do dono · #2 decisora) propôs dual-system EXATAMENTE pra Lara+Dani usarem oimpresso. Existe rede de confiança Kamila→Lara via negócio → se Lara reclamar, Kamila pode reabrir Highsoft. Manter Lara feliz é estratégico — não só pra ela, mas pra preservar patrocínio Kamila.

5. **Persona não-técnica + monitor 1280px assumidos** — paridade com ROTA LIVRE Larissa. Validar essas hipóteses em primeira visita presencial.

## 5. Histórico de interações

### 2026-05-13 — Lara identificada como champion canary
Reunião Wagner+Martinho 10h decidiu: Champion interno **filha do Martinho** (operação/comercial) + **Dani (financeiro)** — querem usar oimpresso.

Refinamento posterior: Lara (filha do JAIR · responsável estoque) é champion · não filha do Martinho. Estrutura familiar real: Jair dono majoritário · Martinho sócio · Lara+Kamila filhas do Jair.

### 2026-05-14 ~16:30 — Felipe presencial · Lara confirmada champion oimpresso
Kamila pausou Highsoft pelo oimpresso porque Lara+Dani entram lá (via insight WhatsApp 15:51 Kamila). Lara confirmada champion canary estoque.

### 2026-05-14 noite — MWART /products + /stock-history em desenvolvimento BG
Agent `ad2f9e74103193c6a` rodando: MWART /products + /stock-history pra Lara usar. ETA 4-6h. Sidebar Martinho ajustada (estoque visível pra Lara biz=164 via business_id customizada · 26 Pest).

### 2026-05-19 (planejado) — Canary inicia · Lara entra oimpresso
Primeiro dia em prod biz=164. Co-design presencial Wagner. Pendência: criar user `lara-164` com dados reais (TBD-perguntar-wagner).

## 6. Refs

- Cliente: [martinho-cacambas](../../clientes/martinho-cacambas.md)
- [ADR proposal dual-sync Delphi+oimpresso](../../../decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md)
- [CHECKLIST pós-reunião](../../../requisitos/OficinaAuto/demo-martinho-2026-05-13/CHECKLIST-POS-REUNIAO.md)
- [Session log 2026-05-14](../../../sessions/2026-05-14-martinho-canary-prep-massive.md)
- [Handoff 2026-05-14 18:00](../../../handoffs/2026-05-14-1800-martinho-canary-prep-jair-endossou.md)
- [Perfil Martinho legacy](../../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)
- Funcionários relacionados: [martinho](martinho.md) (**pai · sócio**) · [jair](jair.md) (dono majoritário · sócio do pai) · [kamila](kamila.md) (esposa do Jair · #2 decisora · **NÃO é irmã**) · [dani](dani.md) (champion duplo · financeiro)
