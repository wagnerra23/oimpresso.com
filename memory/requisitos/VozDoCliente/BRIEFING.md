---
module: VozDoCliente
status: em-construcao
status_nota: "Fundação entregue (entidade + canal autenticado + caixa). Sem uso em produção ainda — nenhum business tem o pacote habilitado."
updated_at: "2026-07-28"
owner: W
---

# BRIEFING — Voz do Cliente

## O que é

O lugar onde a dor relatada por quem usa o ERP **vira dado**. Quem está logado descreve o
que aconteceu, na tela em que aconteceu; o sinal é gravado com contexto e fica numa caixa
até ser triado.

Implementa a [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md), que
diz que o backlog só recebe item com sinal de cliente — regra que existia escrita e sem
máquina desde maio de 2026.

## Por que existe

Larissa (ROTA LIVRE, biz=4, 99% do volume) relata problema por WhatsApp. O relato vira
backlog mental de uma pessoa. Não é contável, não é rastreável, não vira US, e quando vira,
ninguém sabe dizer de onde veio. O módulo fecha esse buraco na origem.

## Estado hoje

| Capacidade | Estado |
|---|---|
| Relatar (autenticado, com URL da tela) | entregue |
| Dedup de relato idêntico | entregue |
| Caixa de triagem (Blade) | entregue |
| Isolamento cross-tenant Tier 0 | entregue + teste |
| Roteamento ao módulo dono | não construído |
| Triagem que vira US | não construído |
| Contagem no brief diário | não construído |
| Tools MCP | não construído |
| Captura de erro do browser → sinal | depende de US-INFRA-003 |

## Como se vende

Add-on: nasce `default => false` no pacote do superadmin. Business só passa a ter depois
de contratar. A categoria de mercado é *Voice of Customer* — o que Enterpret, Unwrap e o
Insights do Productboard vendem. O diferencial aqui é que o sinal já nasce dentro do ERP
onde a dor aconteceu, com a tela junto, em vez de num formulário separado que alguém
precisa lembrar de abrir.

## Decisões que valem lembrar

- **Canal dentro do login** ([W], 2026-07-28) — a US previa portal público com token;
  autenticado é mais simples e mais seguro (`business_id` vem da sessão).
- **`voz_sinais`, não `mcp_client_signals`** — `mcp_` é do MCP server; isto é dado de tenant.
- **Texto nunca reescrito** — é a prova do que a pessoa disse. Correção vira sinal novo.
- **Sem expurgo por tempo** ([W], 2026-07-27) — num ERP não se apaga PII; controle é por
  permissão de acesso.

## Riscos conhecidos

- **Caixa vazia por falta de porta de entrada.** O botão de relatar ainda não vive em
  nenhuma tela — sem isso o módulo existe e ninguém usa.
- **Caixa que ninguém abre.** Enquanto não houver contagem no brief, a triagem depende de
  alguém lembrar de entrar. É o mesmo modo de falha que o mercado chama de dashboard morto.

## Referências

- [ADR 0105 — cliente como sinal](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- [SPEC Infra — US-INFRA-002](../Infra/SPEC.md)
- [SCOPE do módulo](../../../Modules/VozDoCliente/SCOPE.md)
- [SUPERFICIE.md](SUPERFICIE.md) (derivado — `module-surface`)
