---
date: "2026-09-02"
time: "15:33 BRT"
slug: "forja-1280-medida-mcp-fora"
tldr: "Produção a 1280 finalmente medida: com a sidebar no default (260px) o 6º destino da Forja (Integrador) nasce FORA da viewport — 38px de overflow no .main-body. Causa é o shell (cockpit.css não tem auto-rail por largura), não a Forja: vira decisão [W]. Tarefa B (tasks no MCP) parada — servidor inalcançável, medido com controle positivo."
---

# Handoff 2026-09-02 15:33 BRT — Forja a 1280 medida: o "Integrador" nasce fora da tela; MCP fora, Tarefa B parada

> Sessão `eslint-ds-inconsistencias-b0d52a` · [C]. Duas tarefas independentes do pedido do [W].
> Session log: [2026-09-02-forja-paridade-medida-espelho.md](../sessions/2026-09-02-forja-paridade-medida-espelho.md) (seção **Noite** — editei o log de hoje em vez de criar outro, anti-duplicação).

## Estado em uma frase

A 1280 (monitor do [W]), com a sidebar no default `expanded`, **1 dos 6 destinos do topnav da Forja fica fora da viewport** (`Integrador`, borda direita em 1315 contra 1280) — e a causa é **shell/fundação**, não CSS da Forja, então é decisão [W] e não PR de código.

## Tarefa A — produção a 1280 (FEITA, fecha o "não medido" da Onda 2.1)

Registrado por append em [`forja-cockpit-visual-comparison.md`](../requisitos/TeamMcp/forja-cockpit-visual-comparison.md) (§ "2026-09-02 (noite)").

| campo | protótipo (registro) | prod 1280 `expanded` | prod 1280 `rail` (toggle) |
|---|---|---|---|
| grid do `.cockpit` | rail 56 (automático) | **`260px 1020px 0px`** | `56px 1224px 0px` |
| altura / linhas do header | 174,4px · **3** | **136,4px · 2** | 136,4px · 2 |
| overflow-x do `.main-body` | — | **38px** (1058 × 1020) | **0** |
| destinos fora da viewport | — | **1 de 6** | **0 de 6** |

**Causa, verificada no CSS de `origin/main`:** o `@media (max-width:1280px)` do `cockpit.css` (L57-59) **dispara** e colapsa a coluna direita (320→0, batendo com o `0px` medido), mas mantém 260px de sidebar; rail só sob `[data-sidebar="rail"]` (L55). **Não há auto-rail por largura.** O `.os-page-h` é idêntico dos dois lados — nenhum CSS da Forja participa.

**Como a viewport foi obtida (as duas tentativas anteriores falharam por motivos diferentes do registrado):** `resize_window` devolve **"Successfully resized"** e o `innerWidth` **fica em 2560** — instrumento afirmando sucesso sem ter feito; só peguei porque conferi o `innerWidth`, não a mensagem. E **não há Chrome**: a extensão roda no **Brave** (2 janelas, nenhuma com a Forja em foco). Caminho que funcionou: **iframe same-origin de 1280px** na própria aba autenticada, com `innerWidth === 1280` conferido antes de medir e **781/781** nós estáveis após a montagem do Inertia.

**Limite declarado:** o protótipo **não** foi re-medido. O JSON dele a 1280 **não existe** nas fontes que o pedido citava — corpo do [#6563](https://github.com/wagnerra23/oimpresso.com/pull/6563), seus **72** comentários, review-comments (0) e o session log: zero ocorrência de `1280`/`174`/`rail`. A coluna do protótipo é o registro narrativo da própria página.

## Tarefa B — tasks da Forja no MCP: NÃO FEITA (servidor fora)

Medido com **controle positivo**, não suposto: `oimpresso.com/login` **200 em 1,07s** · DNS resolve (177.74.67.30) · **ICMP responde, 1ms, 0% perda** · `/api/mcp` **000/rc=28** em 21s · **TCP 443 falha** (rc=124). Diagnóstico: **host de pé, serviço HTTPS não aceita conexão.**

Nenhuma task atualizada; **nenhum arquivo de task em markdown criado** ([ADR 0070](../decisions/0070-jira-style-task-management-current-md-removed.md)) — era a instrução do pedido: registrar e parar.

⚠️ **Quando o MCP voltar, NÃO criar as tasks 3–11 às cegas.** `list_sessions` mostra **8 sessões rodando agora** exatamente nessas ondas (`busy-swartz`=Onda 3, `forja-onda4-trabalho-lista`, `forja-ondas-5-6-quadro-gantt`, `forja-onda8-mcp-handoffs`, `forja-onda9-changelog`, `forja-onda10-integrador-tabs`, `forja-onda-11-revogacao`, `forja-saude-view`). O passo certo é `tasks-list module:Forja` **antes** de qualquer `tasks-create`, conferindo contra essas sessões — senão duplica trabalho em curso.

## Decisões [W] pendentes (não são do agente)

1. **Auto-rail a ≤1280** — o protótipo raila sozinho; produção exige toggle manual, e sem ele todo usuário a 1280 no default perde o "Integrador". Mexer no `@media` do `cockpit.css` altera o shell de **todos** os módulos, por isso não fiz.
2. **Wrap 2 linhas × 3 linhas no header** — divergência de estratégia, **sem defeito medido** (nada cortado quando o overflow é 0). Só vira trabalho se [W] quiser paridade literal de altura.
3. Herdadas e ainda abertas: `--accent` dark na fundação (0,55 × 0,70, hoje escopado à Forja) e o bundle v2 do Cowork.

## Estado MCP no momento do fechamento

**Sem snapshot** — ADR 0130 **não cumprida**, e declarado em vez de omitido. `cycles-active`, `my-work`, `sessions-recent` e `decisions-search` não puderam rodar: as tools `mcp__oimpresso__*` não estão carregadas nesta sessão **e** o servidor está inalcançável (tabela de sondas acima). O substituto local foi `list_sessions` (sessões CCD), que cobre paralelismo mas **não** cobre cycle/goals/tasks.

## Higiene desta sessão

Trabalhei a partir de `origin/main` **fresco**: a branch que a sessão abriu (`claude/forja-1280-mcp-tasks-a65b72`) estava **144 commits atrás** e carregava um commit não-mergeado de outra sessão (`0543136e7f`, Fiscal/Design Sync de 28/08) — o arquivo alvo ali **antecedia** as medições de 02/09. Criei `claude/forja-1280-prod-medida` de `origin/main` (0/0); o commit alheio segue intacto na branch antiga. Nenhuma outra sessão usa esta worktree (a homônima é `...-814ea7`, outro diretório).
