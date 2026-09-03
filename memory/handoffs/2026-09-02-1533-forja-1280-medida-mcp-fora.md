---
date: "2026-09-02"
time: "15:33 BRT"
slug: "forja-1280-medida-mcp-fora"
tldr: "Produção a 1280 medida: com a sidebar expanded, 1 de 6 destinos do topnav (Integrador) nasce fora da viewport — 38px de overflow. A conclusão que tirei disso (divergência de shell) estava ERRADA e vai corrigida: a ERRATA da mesma noite prova que o protótipo a 1280 também é 260px/1020px. O que sobra é a medição de produção, que quantifica o defeito que a ADR UI-0030 conserta. Tarefa das tasks no MCP parada: servidor inalcançável."
---

# Handoff 2026-09-02 15:33 BRT — Forja a 1280: o "Integrador" nasce fora da tela (e a conclusão que a ERRATA corrigiu)

> Sessão `eslint-ds-inconsistencias-b0d52a` · [C]. Duas tarefas independentes do pedido do [W].
> Session log: [2026-09-02-forja-paridade-medida-espelho.md](../sessions/2026-09-02-forja-paridade-medida-espelho.md) (seção **Noite (sessão paralela)**).
> ⚠️ **Publicado em 2026-09-03 já corrigido** — o trabalho é de 02/09, mas a conclusão original foi derrubada por uma ERRATA que entrou no `main` antes deste texto. O erro fica registrado, não apagado.

## Estado em uma frase

A 1280 (monitor do [W]), com a sidebar no default `expanded`, **1 dos 6 destinos do topnav da Forja fica fora da viewport** (`Integrador`, borda direita em 1315 contra 1280) — defeito real, medido, que a [ADR UI-0030](../requisitos/_DesignSystem/adr/ui/0030-sidebar-auto-rail-responsivo.md) conserta.

## O erro que cometi, e que a ERRATA pegou

Concluí *"o protótipo raila a 1280 e produção não ⇒ divergência de shell ⇒ decisão [W]"*. **Falso.** Tomei o **registro narrativo** antigo (`rail 56 · 3 linhas · 174,4px`) como se fosse medição do protótipo. A errata da mesma noite prova que aquele retrato vinha de `localStorage` poluído por um `innerWidth: 0` do Browser pane: com a chave limpa, o protótipo a **1280** dá `260px 1020px` — **igual à produção**. O rail é a **≤1279**. Logo **não havia divergência de shell a 1280**, e a decisão que eu abriria já estava tomada e `accepted` ([W]: *"apenas faça"*). É **LC-08 no mesmo vetor que a errata descreve** — duas sessões caíram nele na mesma noite, por caminhos diferentes.

## O que sobreviveu, e por isso este handoff existe

A medição do lado **produção**, que ninguém tinha feito, e que **quantifica** o defeito:

| | `expanded` (comportamento antigo) | `rail` (o que a UI-0030 faz a ≤1280) |
|---|---|---|
| grid do `.cockpit` | `260px 1020px 0px` | `56px 1224px 0px` |
| `overflow-x` do `.main-body` | **38px** | **0** |
| destinos fora da viewport | **1 de 6** (`Integrador`, borda 1315) | **0 de 6** |
| header | 136,4px · 2 linhas | 136,4px · 2 linhas |

Sem scroll de página (`scrollWidth === clientWidth === 1280`); o `.main-body` absorve, dentro de um `.cockpit` com `overflow:hidden`. O padding `12px 24px` da Onda 2.1 **resistiu** a 1280. A 2ª linha do header **não** é defeito e não é o que a UI-0030 resolve.

## Método — três armadilhas de medição de largura, todas medidas

1. **`resize_window` mente**: devolve `"Successfully resized window ... to 1280x900"` e o `innerWidth` **fica em 2560**. O veredito só sobreviveu porque conferi o `innerWidth`, não a mensagem da tool.
2. **Não há Chrome**: a extensão roda no **Brave** (2 janelas, nenhuma com a Forja em foco) — redimensionar "a janela do Chrome" não acertaria o alvo.
3. **`innerWidth: 0`** no Browser pane sem resize prévio (documentado pela errata) foi o que poluiu o `localStorage` e fabricou o retrato errado do protótipo.

Regra que sai daí: **medição de largura prova a largura antes de medir qualquer outra coisa.** Caminho que funcionou: **iframe same-origin de 1280px** na aba autenticada, `innerWidth === 1280` conferido antes, **781/781** nós estáveis pós-montagem do Inertia.

## Tarefa B — tasks da Forja no MCP: NÃO feita (servidor fora)

Medido com **controle positivo**: `oimpresso.com/login` **200 em 1,07s** · DNS resolve (177.74.67.30) · **ICMP responde, 1ms, 0% perda** · `/api/mcp` **000/rc=28** em 21s · **TCP 443 falha** (rc=124). Host de pé, **serviço HTTPS não aceita conexão**.

Zero task atualizada e **zero markdown de task** ([ADR 0070](../decisions/0070-jira-style-task-management-current-md-removed.md)) — era a instrução do pedido: registrar e parar.

⚠️ **Quando o MCP voltar, NÃO criar as tasks 3–11 às cegas.** `list_sessions` mostrava **8 sessões rodando** exatamente nessas ondas. `tasks-list module:Forja` **antes** de qualquer `tasks-create`.

## Limites declarados

- **Não medi o protótipo a 1280 com `localStorage` limpo.** Pela errata ele tem os mesmos 1020px de conteúdo, então é de se esperar que corte um destino também — **não verificado**, não citar como medido.
- O `@media (max-width:1280px)` do `cockpit.css` L57-59 que inspecionei (colapsa a coluna direita, sem auto-rail) é retrato da base **anterior** à UI-0030. Quem conferir depois dela deve **re-medir**, não citar aquela linha.

## Estado MCP no momento do fechamento

**Sem snapshot** — ADR 0130 **não cumprida**, declarado em vez de omitido. `cycles-active`, `my-work`, `sessions-recent` e `decisions-search` não rodaram: as tools `mcp__oimpresso__*` não estão carregadas **e** o servidor está inalcançável (sondas acima). Substituto local: `list_sessions` (sessões CCD), que cobre paralelismo mas **não** cycle/goals/tasks.

## Higiene desta sessão

Base `origin/main` fresca: a branch que a sessão abriu estava **144 commits atrás** e carregava um commit não-mergeado de outra sessão (`0543136e7f`) — o arquivo alvo ali antecedia as medições de 02/09; criei `claude/forja-1280-prod-medida` e o commit alheio segue intacto. O `## TL;DR` do session log entrou porque **meu toque acordou o gate diff-aware** que já reprovava o arquivo (§5 2026-07-12) — bite test `erros: 1 → 0`. Dois conflitos de conteúdo com sessões irmãs (visual-comparison e session log) foram resolvidos **preservando os dois lados**; o `push --force-with-lease` foi barrado pelo hook `block-destructive` e a sincronização saiu por **merge**, sem força, com teste de identidade de árvore.
