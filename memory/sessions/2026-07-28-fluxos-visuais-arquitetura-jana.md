---
date: "2026-07-28"
hour: "16:21 BRT"
duration: "0.5h"
topic: "Auditoria dos HTMLs de IA e incorporação dos fluxos estáveis à arquitetura viva da Jana"
authors: [W, C]
outcomes:
  - "ARCHITECTURE.md ganhou diagramas gerados das camadas, chat SSE, recall e RAG canônico"
  - "Fluxos explicativos passaram a ter marcadores ordenados contra o código dono"
  - "O censo confirmou 22 agentes PHP de produto e zero agentes sem referência de produção"
  - "A ordem de persistência de tokens do streaming passou a aparecer como desconexão medida"
prs: []
us: []
related_adrs:
  - "0035-stack-ai-canonica-wagner-2026-04-26"
  - "0048-framework-agentes-laravel-ai-vizra-rejeitada"
  - "0062-separacao-runtime-hostinger-ct100"
  - "0093-multi-tenant-isolation-tier-0"
---

# Session log 2026-07-28 — fluxos visuais da arquitetura Jana

## TL;DR

Os seis artefatos temporários produzidos na sessão do Claude foram auditados contra o código atual. O melhor material visual foi incorporado ao dono já escolhido, [`Jana/ARCHITECTURE.md`](../requisitos/Jana/ARCHITECTURE.md), sem versionar outro HTML, painel ou gerador paralelo.

O censo derivado confirmou **22 agentes PHP de produto**: Jana 14, ADS 4, Crm 3 e Whatsapp 1. `SystemHealthAuditTool` é **tool MCP**, não agente, e por isso não entra nessa conta. Os 24 agentes de engenharia em `.claude/agents/` são outra camada e também ficam fora do total de produto.

## Destino dos seis artefatos

| Artefato temporário | Decisão | Motivo |
|---|---|---|
| `mapa-ia-oimpresso.html` | conteúdo estável incorporado | melhor base explicativa, mas com números e estados congelados |
| `painel-ia-fluxos.html` | não versionado | auditoria datada, não fonte canônica |
| `_check.js` | não versionado | notas, flags e defeitos manuais envelhecem |
| `_pesos.mjs` | descartado | mutador pontual de pesos, sem papel na máquina matriz |
| `gen.txt` | descartado | snapshot duplicado do painel gerado |
| `painel-gen.md` | descartado | outra cópia do mesmo output gerado |

Nenhum arquivo temporário foi apagado; apenas não foi copiado para o repositório.

## O que entrou na página canônica

- três camadas: acesso aos modelos, agentes do produto e memória/recuperação;
- caminho do chat SSE, incluindo persistência da pergunta, Brief Diário, cache, PII, contexto, recall, modelo e persistência da resposta;
- recall de memória: negative cache, HyDE, busca híbrida, RRF, time-decay, Peso Real e reranker;
- RAG da memória canônica: híbrido/FULLTEXT, autorização, fontes, síntese e fallback;
- tabela explícita do que a árvore conecta e do que só runtime pode provar.

As quantidades permanecem derivadas pelo gerador: **22 agentes**, **15 provedores**, **4 implementações de `MemoriaContrato`** e **4 de `Reranker`** nesta geração.

## O que estava errado ou vencido nos artefatos

- “duas máquinas” confundia zonas operacionais com contagem física;
- o painel alternava **62 tabelas** na nota e **58 tabelas** no próprio inventário;
- a afirmação de que `ARCHITECTURE.md` ainda descrevia abril ficou falsa depois da consolidação anterior;
- notas, flags “ligada/desligada hoje”, modelo atual e estados “em execução” eram recibos temporais apresentados como arquitetura.

Esses dados não foram promovidos ao canon.

## Como os fluxos continuam vivos

`system-map.mjs` agora verifica marcadores na ordem esperada dentro dos arquivos donos. Se o controller, o driver, o recall ou o serviço de RAG perder uma etapa ou trocar a ordem estrutural, a geração falha e obriga revisão da explicação. O self-test prova também o mutante “marcadores presentes, mas fora de ordem”.

O workflow da matriz passou a observar esses arquivos donos no próprio PR, além do cron diário. Assim, uma alteração de fluxo aciona a conferência sem depender de alguém lembrar de tocar a documentação.

O medidor encontrou uma desconexão concreta: o driver do streaming atualiza a “última resposta assistant” antes de `ChatController` criar a resposta do turno atual. A página exibe o alerta automaticamente. O comportamento não foi alterado nesta sessão documental.

## Validação

- `node scripts/governance/system-map-ia.test.mjs`
- `node scripts/governance/system-map.mjs`
- `node scripts/governance/system-map.mjs --check`
- `node scripts/governance/onboarding-paths-check.mjs`
- `node scripts/governance/document-relocation-executor.mjs --selftest`
- `git diff --check`

## Referências

- [Arquitetura viva da Jana](../requisitos/Jana/ARCHITECTURE.md)
- [Sessão da consolidação canônica](2026-07-28-jana-architecture-canonica-viva.md)
