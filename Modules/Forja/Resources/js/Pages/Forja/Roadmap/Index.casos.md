---
id: resources-js-pages-forja-roadmap-index-casos
casos: Roadmap por quarter (epics) · /project-mgmt/roadmap
irmaos: Index.charter.md (lei) · Gantt.casos.md (a outra leitura do roadmap, por task)
tecnica: Caso de uso = narrativa de quem usa + critério de aceite verificável (Dado/Quando/Então)
por_que: "quem enxerga o planejamento" e "como um epic vira coluna e progresso" não mudam num refactor de layout
owner: wagner
last_run: "2026-10-07"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Forja · MySQL)"
---

# Casos de Uso & Aceite — Roadmap por quarter (Forja)

> Os UC derivam do [`Index.charter.md`](Index.charter.md) (Mission, Goals, Non-Goals), da
> **US-TR-203** ([`memory/requisitos/TaskRegistry/SPEC.md`](../../../../../../../memory/requisitos/TaskRegistry/SPEC.md):
> *"epics agrupados por target_quarter"*) e da [ADR 0367 D7](../../../../../../../memory/decisions/0367-cockpit-unico-forja-project-mgmt-morre.md)
> (*"5 epics vivos agrupados por trimestre com progresso real"*). Conferidos no
> `RoadmapController@index` — **nunca** derivados do `.tsx` (§5 2026-06-05).

> ⚖️ **A tela convive com o Gantt, e isso está decidido.** A ADR 0367 (aceita) D7 manteve o
> quarter view vivo como *"segunda leitura do roadmap"* até o Gantt (`/forja/roadmap-gantt`)
> provar que substitui (filtro por cycle efetivo + volume domado). O `Modules/Forja/Http/routes.php`
> registra isso no próprio bloco que sobreviveu à Onda 11. Se [W] reverter a D7, estes casos saem
> junto com a tela. Os UC do Gantt estão em [`Gantt.casos.md`](Gantt.casos.md); nenhum UC daqui
> repete os de lá.

> ⚖️ **Onde roda.** Teste: [`Modules/Forja/Tests/Feature/Roadmap/RoadmapQuarterViewContratoTest.php`](../../../../../Tests/Feature/Roadmap/RoadmapQuarterViewContratoTest.php),
> MySQL-only (pula no SQLite), na allowlist da lane `PHP / Pest (Forja · MySQL)`
> (`.github/workflows/forja-pest.yml`) — **advisory**: reprova visível, não bloqueia merge
> (não consta em `governance/required-checks-baseline.json`). Tenant 98 (ADR 0358), nunca biz=4.

> **Status:** ✅ passa (provado no manifesto G-7) · 🧪 teste cita o UC, sem veredito ainda ·
> ⬜ não verificado · ❌ quebrou.

---

## UC-RQV-01 · Quem não é do time não vê o roadmap `[must]` `[T0]`
Status: 🧪 sem veredito
- **Persona:** usuário do ERP que não faz parte do time interno — e visitante sem login.
- **Aceite:** Dado um visitante anônimo · Quando abre `/project-mgmt/roadmap` · Então vai pro login (302) ou recebe 401. Dado um usuário logado **sem** `jana.mcp.usage.all` · Quando abre a tela · Então recebe **403**. Com a permissão, a mesma rota abre (controle positivo).
- **Teste:** `Modules/Forja/Tests/Feature/Roadmap/RoadmapQuarterViewContratoTest.php` — `UC-RQV-01 · quem não é do time não vê o roadmap …`
- **Regressão que defende:** `mcp_epics`/`mcp_tasks` não têm `business_id` — o charter diz que a tela *"não é multi-tenant por business_id"* e é *"gated por jana.mcp.usage.all"*. Então a permissão **é** o isolamento (ADR 0093 §exceções): se ela cair, qualquer business enxerga o planejamento do time.

## UC-RQV-02 · Os epics chegam agrupados por quarter; sem quarter vira "Sem quarter" `[must]`
Status: 🧪 sem veredito
- **Persona:** [W] planejando o médio prazo — *"o que está planejado, ativo e concluído em cada trimestre?"* (charter, Mission).
- **Aceite:** Dado um projeto com 2 epics em `Q1-2031`, 1 em `Q3-2031` e 1 sem `target_quarter` · Quando a tela pede as props deferidas `quarters`/`kpis` · Então vêm três colunas — `Q1-2031` com os 2, `Q3-2031` com 1, `Sem quarter` com o que não tem quarter — e cada epic aparece numa coluna só.
- **Teste:** `RoadmapQuarterViewContratoTest.php` — `UC-RQV-02 · os epics chegam agrupados por quarter …`
- **Regressão que defende:** epic sem quarter sumir da tela (cair fora de toda coluna) — o charter promete o bucket *"Sem quarter"*.

## UC-RQV-03 · O progresso do epic é done/total das tasks dele `[must]`
Status: 🧪 sem veredito
- **Persona:** [W] lendo *"quanto andou"* cada iniciativa (ADR 0367 D7: *"Memória & KB 8%, Token Economy 60%"*).
- **Aceite:** Dado um epic com 4 tasks (1 done, 1 cancelada, 2 abertas) e outro epic com 2 de 2 done · Quando a tela carrega · Então o primeiro mostra `1/4` e **25%**, e o segundo `2/2` e **100%**.
- **Teste:** `RoadmapQuarterViewContratoTest.php` — `UC-RQV-03 · o progresso do epic é done/total …`
- **Regressão que defende:** progresso calculado sem o vínculo epic→task (os dois epics dariam o mesmo número). Os valores diferem de propósito para o teste poder reprovar isso.

---

## Backlog de casos (sem id — entram quando tiverem teste e ≥2 fontes)

- **[BACKLOG] KPIs de cabeçalho (total, ativos, em planning, concluídos)** — só o charter (Goals) os descreve; nenhuma outra fonte canônica.
- **[BACKLOG] Projeto sem epic mostra estado vazio apontando `epics-create` via MCP** — só o charter. É asserção de cliente (texto renderizado).
- **[BACKLOG] A tela é só leitura (nenhuma escrita em GET; mover quarter é `epics-update` via MCP)** — charter (Non-Goals + Anti-hooks). Fonte única.
- **[BACKLOG] Epic `cancelled` aparece ou não?** — o charter (Goals) lista o ícone de *cancelado* no card, mas a Mission fala só de *"planejado, ativo e concluído"*, e o controller filtra `planning/active/done`. Divergência de intenção: **pergunta ao [W]**, não UC.
- **[BACKLOG] Ordem das colunas entre anos** — a US-TR-203 diz *"visão temporal"*, e o controller ordena as chaves como texto (`ksort`): no formato `Q1-2027`/`Q2-2026` o texto põe `Q1-2027` antes de `Q2-2026`. Nenhum UC afirma ordem até [W] dizer se a ordem cronológica é contrato (os UC acima usam um ano só e não asserem posição).
