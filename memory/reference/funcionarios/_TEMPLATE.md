---
slug: <first-name-lowercase>                     # ex: larissa, jair, kamila, dani
cliente_slug: <cliente-slug>                     # ex: rotalivre, martinho-cacambas
first_name: <Nome>                                # nome curto · SEM sobrenome em git canônico (LGPD)
nome_completo_real: TBD-perguntar-wagner          # marcar TBD quando incerto · NUNCA escrever PII real em git
relacao: <descritivo>                             # ex: dona/operadora · filha do dono · sócio · funcionário X anos
role_operacional: <responsabilidade primária>    # ex: responsável estoque, financeiro, vendedor, mecânico
cargo_formal: <opcional>                          # ex: Sócia administradora, Vendedor externo
user_id_oimpresso: <int|null>                     # null se ainda não criado em prod biz=N
username_oimpresso: <string|null>                 # username conforme tabela users biz=N
papel_canary: <slug>                              # champion-oimpresso | decisor | continua-legacy | observador | etc
acesso_sistemas:
  - sistema: <Sistema legacy ou novo>
    role: <role no sistema>
preferencias_ux: []                               # lista bullets: persona_nao_tecnica, monitor_1280px, etc
sensibilidades: []                                # lista bullets: pede_co_design_presencial, etc
pii_vault_ref: vault://<cliente-slug>/<funcionario-slug>  # CPF/email/telefone vão pra Vaultwarden
data_primeiro_contato: <YYYY-MM-DD|null>
ultima_atualizacao: <YYYY-MM-DD>
---

# <Nome curto> (<cliente_slug>)

<!-- 1 frase descrevendo: quem é + papel principal + por que importa nessa migração. -->

## 1. Papel atual

<!--
- Role operacional concreto (não cargo formal)
- Responsabilidade primária do dia-a-dia
- Quem reporta pra quem (cadeia decisão)
- Há quanto tempo na empresa (se relevante)
-->

## 2. Acesso a sistemas

<!--
Tabela detalhada:
| Sistema | role/permissão | user_id/login | observação |
| Office Delphi | operadora-estoque | (login Delphi) | continua usando |
| oimpresso | Admin#164 (planned) | criar pré-canary | entra 19/maio |
-->

## 3. Preferências UX

<!--
- Monitor: tamanho/resolução conhecida
- Persona: técnica/não-técnica
- Idioma: PT-BR padrão
- Atalhos preferidos
- Velocidade de aprendizado (rápida/devagar)
- Plataforma (desktop/mobile)
-->

## 4. Sensibilidades

<!--
Pain-points e gostos reportados:
- Pede co-design presencial?
- Decorou comportamento legacy (regressão visual = trauma)?
- Estilo de comunicação (formal/informal)
- Frustrações conhecidas com sistema atual
- O que NÃO mexer sem avisar
-->

## 5. Histórico de interações

<!--
Datado:
### YYYY-MM-DD — Evento curto
Resumo. Cross-link session/handoff/ADR/feedback.
-->

## 6. Refs

<!--
- Cliente: ../../<cliente-slug>.md
- Sessions onde aparece
- Handoffs relacionados
- ADRs envolvidas
- Feedbacks catalogados
- Perfil legacy research/ se aplicável
-->
