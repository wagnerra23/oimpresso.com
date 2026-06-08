---
name: Outbound/sales tracking — markdown canon, sem MCP tasks granulares
description: Wagner prefere tracking de outbound comercial via markdown editado em git ao invés de criar 30 sub-tasks no MCP (atrito governance > valor tracking granular)
type: feedback
---
Em frentes de outbound comercial / prospecção, tracking de status (backlog/doing/done) fica direto no arquivo markdown do plano (tabela editável). Não criar US-mãe + N sub-tasks no MCP.

**Why:** Decidido em 2026-05-10 ao montar Outbound Comunicação Visual Q2 (30 prospects Top 30 destilados de 27 UFs). MCP `tasks-create` exige módulo registrado em `mcp_jira_projects` — `ComunicacaoVisual` tem SPEC.md mas não é canônico ainda, então criar 30 sub-tasks exigiria primeiro habilitar o módulo no canon (governance + DB seed). Wagner escolheu opção C: markdown-only. Justificativa implícita: outbound é atividade comercial, não backlog de feature; task management granular via MCP custa atrito vs valor pra essa frente.

**How to apply:**
- Pra outbound/sales/prospecção: artefato em `memory/sales/<YYYY-MM>/<frente>/00-PLAN.md` com tabela de status editável
- Só criar US no MCP quando lead virar **sinal qualificado** (respondeu, agendou call, virou cliente — então sim, cria US de feature/integração na ComunicacaoVisual via SPEC.md depois de habilitar canon)
- Vale também pra: research compilations, market mapping, comparativo de concorrentes — todos cabem em markdown sem MCP overhead
- NÃO se aplica a: backlog de feature de produto (esse vai pro MCP), bug fix, infra task — esses seguem padrão MCP normal
- Se faltar canon de módulo (ComunicacaoVisual, Vestuario, OficinaAuto): apontar pro Wagner antes de tentar bulldoze
