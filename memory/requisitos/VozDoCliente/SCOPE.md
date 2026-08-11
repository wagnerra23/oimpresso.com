---
module: VozDoCliente
purpose: "Recebe e guarda o relato de quem opera o ERP, dentro do login: texto literal (nunca reescrito), a URL da tela onde a dor aconteceu, severidade auto-declarada e autor, deduplicado por texto e escopado ao business. Hoje entrega só isso — a caixa é leitura sem ação de triagem, o roteamento por módulo não está ligado, e ainda não existe chamador do endpoint de gravação no frontend."
migracao_ui: "pendente — tem Blade servido, sem duvida de escopo; fila em module-surface --migracao"
contains:
  - "SinalController — grava o sinal (store) e mostra a caixa de triagem (index)"
  - "DataController — 3 hooks UltimatePOS (pacote superadmin, permissões, sidebar)"
  - "InstallController — instalação 1-clique (ADR 0024)"
  - "Entities/Sinal — global scope business_id Tier 0 + dedup por hash + limiar de triagem"
  - "Database/Migrations — tabela voz_sinais"
excludes:
  - "Captura automática de erro do browser → sinal (é o wire da US-INFRA-003, ainda não construído)"
  - "Roteamento automático ao módulo pelo dicionário de domínio (coluna existe, preenchimento é PR seguinte)"
  - "Tela Inertia da caixa de triagem (hoje Blade; migração exige charter + Padrão de Tela + gate visual)"
  - "Tools MCP client-signals-* (previstas na US-INFRA-002, PR seguinte)"
  - "Contagem no brief diário (previsto na US-INFRA-002, PR seguinte)"
owner: wagner
status: em construção
---

# Modules/VozDoCliente

## Por que existe

A [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) estabelece que
o backlog **só recebe item se cliente paga e reporta, ou se métrica detecta desvio**. A regra
estava escrita e sem máquina: Larissa (ROTA LIVRE, biz=4) relata dor por WhatsApp, o relato vira
backlog mental de uma pessoa só, e nada disso é contável, rastreável ou auditável.

Este módulo é onde o sinal passa a existir como dado.

## O que ele faz hoje

1. **Relatar** — quem está logado descreve o que aconteceu; a URL da tela vai junto, capturada,
   não digitada. O texto é gravado **literal** e nunca reescrito (é a prova do que foi dito).
2. **Deduplicar** — mesmo business + mesmo texto (normalizado) = um sinal só. Clicar duas vezes
   não polui a caixa.
3. **Ver** — quem tem `vozdocliente.triar` abre a caixa e vê pendente primeiro.

## Decisões que valem registrar

- **Canal dentro do login** ([W], 2026-07-28). A US-INFRA-002 previa portal público com token por
  business expirando em 30 dias. A decisão mudou para autenticado — e simplificou: `business_id`
  volta a vir da sessão (padrão canônico do global scope), morrem a expiração, o endpoint anônimo
  e a superfície de spam. A US foi corrigida no mesmo PR (regra de precedência).
- **Tabela `voz_sinais`, não `mcp_client_signals`.** O prefixo `mcp_` é do MCP server (governança
  interna). Isto é dado de tenant num módulo de produto, então segue o padrão de prefixo por
  módulo, como `fin_*` no Financeiro.
- **Add-on vendável.** `default => false` no pacote do superadmin: o business só passa a ter
  depois de contratar.
- **Sem expurgo por tempo.** O texto pode conter dado pessoal e não é apagado — decisão [W]
  2026-07-27: num ERP não se apaga PII; o controle é por permissão de acesso.

## O que falta (ordem sugerida)

| # | O quê | Depende de |
|---|---|---|
| 1 | Botão de relatar acessível de qualquer tela | definir onde mora no shell |
| 2 | Roteamento ao módulo pelo vocabulário do dicionário de domínio | medir taxa de erro antes de ligar |
| 3 | Triagem: sinal vira US com um clique | 1 e 2 |
| 4 | Contagem no brief diário | tabela com volume real |
| 5 | Captura de erro do browser → sinal automático | US-INFRA-003 |
| 6 | Caixa em Inertia | charter + Padrão de Tela + gate visual |

## Referências

- [ADR 0105 — cliente como sinal, guiar sem mandar](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- [ADR 0093 — multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0024 — instalação 1-clique](../../decisions/0024-instalacao-1-clique-modulos.md)
- [SPEC Infra — US-INFRA-002](../../requisitos/Infra/SPEC.md)
