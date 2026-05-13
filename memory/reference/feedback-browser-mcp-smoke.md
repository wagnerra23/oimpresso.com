---
name: Browser MCP smoke test após cada feature UI
description: Após implementar feature UI Sells/Repair/etc, sempre rodar Chrome MCP smoke test em prod biz=1 antes de reportar como done — testes Pest estruturais (file_get_contents+regex) NÃO substituem validação visual
type: feedback
---
Wagner pediu em 2026-05-12 (sessão musing-hopper, após Wave 3): **"sim faça isso sempre"** em resposta à oferta de rodar browser MCP pra validar Grade Avançada Sells em prod.

**Regra:** Após implementar/mergear feature UI (componente React Inertia novo, alteração visual em Page existente, novo dropdown/toggle/filter), antes de reportar como `done` no MCP ou marcar acceptance criteria fechado, executar smoke test via `mcp__Claude_in_Chrome__*` em prod biz=1:

1. `mcp__Claude_in_Chrome__list_connected_browsers` (verifica conexão extension)
2. `mcp__Claude_in_Chrome__navigate` pra URL da feature (ex `https://oimpresso.com/sells`)
3. `mcp__Claude_in_Chrome__get_page_text` ou `read_page` pra confirmar render
4. `mcp__Claude_in_Chrome__find` + `mcp__Claude_in_Chrome__javascript_tool` (eval seguro) pra interagir com toggle/dropdown sem efeito destrutivo
5. Reportar achados com screenshot via `mcp__Claude_in_Chrome__gif_creator` se houver bug

**Por que:**
- Pest "estrutural" (`file_get_contents + regex` em arquivo `.tsx`) só verifica que a string existe — NÃO renderiza React, não clica, não pega regressão runtime
- CI Vite build só compila — não roda
- Bug recorrente: componente passa CI verde mas quebra em runtime (props errado, hook violation, key duplicada, fetch URL errada). Só visível em browser

**O que NÃO fazer no smoke prod biz=1:**
- Não clicar ações destrutivas (cancelar venda, emitir NFe, refund, deletar contato)
- Não criar entidades reais (venda nova, contact)
- Não submeter forms que persistem em DB
- Limitar a navegação + leitura de DOM + interações UI puras (toggle visual, hover, dropdown abrir)

**Quando pular:**
- Mudança backend-only (Service, Migration, Seeder, Pest unit) — sem impacto UI
- Doc/SPEC/ADR
- Refactor interno sem mudança de comportamento visual

**Quando ESCALAR pra Wagner:**
- Smoke detecta regressão visual em feature crítica (Sells Index, drawer SaleSheet, FSM action panel) — pausar wave, reportar antes de prosseguir
- Componente carrega mas com erro console (verificar via `mcp__Claude_in_Chrome__read_console_messages`)
