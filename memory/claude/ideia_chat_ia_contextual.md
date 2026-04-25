---
name: Ideia — Chat IA contextual no AppShell
description: Wagner quer um chat IA flutuante que sabe EXATAMENTE em qual tela o usuário está, que dados estão carregados, que módulo, e responde sobre o contexto. Diferente de /memcofre/chat (generic), este é embedded na UI.
type: ideia
originSessionId: 6cbda521-1ac7-4ff2-9419-9acdb42822ac
---
# Chat IA contextual — feature idealizada por Wagner em 2026-04-24

## O que Wagner disse

> "só imagino um chat com IA sabendo exatamente sobre o que está falando"

Contexto: revisando o showcase do design system, aprovou os componentes shared. Diferente do `/memcofre/chat` do MemCofre (que responde sobre a documentação), essa ideia é sobre **embedded UX** — um chat flutuante dentro das telas operacionais.

## Interpretação

O chat deve saber, ao abrir:
- **Tela atual** — URL, módulo (Ponto/MemCofre/Sells/etc), ação (list/show/edit)
- **Dados visíveis** — que registros estão na tabela, quais filtros aplicados, que KPIs
- **Usuário** — role, permissões, business_id, nome
- **Histórico recente** — últimas ações (criou X, editou Y)

Com esse contexto, responde perguntas como:
- "Por que essa venda está com saldo devedor?"
- "Quem aprovou a intercorrência #1234?"
- "Quantas vendas a Larissa fez essa semana?"
- "Qual a diferença entre essas duas escalas?"
- "Como configurar REP-A?"

Sem o usuário precisar repetir o contexto — o chat já sabe.

## Diferença de `/memcofre/chat`

| Aspecto | `/memcofre/chat` (MemCofre) | Chat IA contextual (ideia) |
|---|---|---|
| Escopo | Documentação (README/SPEC/ARCHITECTURE/ADRs) | Dados operacionais + documentação + tela atual |
| Invocação | Tela própria | Botão/atalho flutuante em TODA tela |
| Contexto | Módulo selecionado no sidebar | URL + props Inertia + seleção do user |
| Stream | Offline (keyword) / AI stub | AI-first (streaming real) |
| Persona | "Manual vivo" | "Copiloto da operação" |

## Esboço técnico (não desenhado, só pensamento)

### Frontend
- Botão flutuante no `AppShell` (canto inferior direito), sempre visível
- Click abre drawer lateral ou modal centralizado
- Ao enviar pergunta, anexa ao prompt:
  ```json
  {
    "url": "/ponto/aprovacoes",
    "module": "PontoWr2",
    "page": "Aprovacoes/Index",
    "props": { "aprovacoes": [...] },  // props Inertia visíveis
    "selection": [1234, 1235],           // IDs selecionados se houver
    "user": { "id": 11, "role": "Vendas#4", "business_id": 4 },
    "question": "por que a 1234 tá bloqueada?"
  }
  ```
- Stream da resposta via SSE ou fetch stream

### Backend
- Endpoint `POST /ai/chat/contextual` (guardado por business_id)
- Monta prompt system com:
  - Descrição do módulo (lido de `memory/requisitos/{module}/README.md`)
  - Descrição da tela (lido de `docs_pages` via `@memcofre`)
  - Regras Gherkin relevantes (`memory/requisitos/{module}/SPEC.md`)
  - Schema do DB (`reference_db_schema.md`)
- Resolve dados se o LLM pedir (tool use): `SELECT * FROM transactions WHERE id=1234`
- Retorna resposta + citações (link pra ADR/story/doc que fundamentou)

### Privacidade
- NUNCA enviar dados de outros businesses
- NUNCA enviar senhas/tokens/API keys
- User pode ver histórico dele (`docs_chat_messages` já existe no MemCofre)
- Flag `DOCVAULT_AI_ENABLED` + `OPENAI_API_KEY` no `.env`

## Como isso conecta com o que já existe

- `Modules/MemCofre/Services/ChatAssistant.php` — já tem o esqueleto offline/AI
- `Modules/MemCofre/Entities/DocChatMessage.php` — já persiste chat
- `docs_pages` — já mapeia `{tela → stories/rules/adrs}` via `@memcofre`
- `memory/requisitos/_DesignSystem/adr/ui/0005-*` — camada de componentes shared
- Faltaria: `ContextualChatButton` component + `/ai/chat/contextual` endpoint + tool-use layer

## Prioridade

Não imediato. É uma feature "incrível" mas requer:
- `DOCVAULT_AI_ENABLED=true` em produção
- `OPENAI_API_KEY` configurada
- Evaluation prompt engineering (evitar alucinação sobre dados reais)
- Decidir privacy controls (dados de venda podem ir pra OpenAI?)

**Status atual:** idea capture. Implementar depois que Fase 1-3 do redesign Ponto estiverem feitas. Talvez antes da Fase 4 (Polish) porque o chat é "polish definitivo".

## Quem lembra disso

- Quem está retomando: leia também `reference_db_schema.md` (tabelas), `cliente_rotalivre.md` (caso de uso real), e docs do MemCofre `/memory/requisitos/MemCofre/` (arquitetura de chat existente).
