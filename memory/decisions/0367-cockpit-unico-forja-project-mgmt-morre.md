---
slug: 0367-cockpit-unico-forja-project-mgmt-morre
number: 367
title: "Cockpit único do time: /project-mgmt morre, /forja e /team-mcp/tasks ficam — cinco vitrines sobre a mesma tabela viram duas"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-04"
module: projectmgmt
quarter: 2026-Q3
tags: [forja, project-mgmt, deprecacao, cockpit, sobreposicao, urls-legadas, permissions-spatie, roadmap-gantt]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0070-jira-style-task-management-current-md-removed
  - 0087-drift-resolution-sem-mover-url
  - 0088-module-rename-php-only
  - 0093-multi-tenant-isolation-tier-0
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0363-governance-incorpora-ads-nucleo-sem-receptor
  - 0366-fronteira-jana-forja-governance-kb
---

# ADR 0367 — Cockpit único do time: `/project-mgmt` morre, `/forja` e `/team-mcp/tasks` ficam

## Contexto

[W] percebeu, navegando, que seis URLs pareciam a mesma coisa e pediu explicação:

`/forja` · `/forja/backlog` · `/forja/quadro` · `/forja/changelog` · `/team-mcp/tasks` · `/ia/admin/roadmap` — comparadas com `/project-mgmt/board`.

**Medido em `origin/main` (2026-08-04), não inferido:**

| URL | Controller | Componente React | Escopo do dado |
|---|---|---|---|
| `/project-mgmt/board` | `Forja\BoardController` | `Forja/Board/Index` | projeto configurável (`?project=`, default `projectmgmt.default_project_key`) |
| `/forja` · `/backlog` · `/quadro` · `/changelog` | `Forja\ForjaController` (4 métodos) | **`team-mcp/Forja/Cockpit` — o mesmo nas 4**, muda a prop `tab` | travado em `project=FORJA` (`const PROJECT_KEY`) |
| `/team-mcp/tasks` | `Forja\TasksAdminController` | `team-mcp/Tasks/Index` | mesma tabela |
| `/ia/admin/roadmap` | `Jana\Admin\RoadmapController` | `Jana/Admin/Roadmap` | mesma tabela, em Gantt (`@svar-ui/react-gantt`) |

Dois fatos explicam a confusão:

1. **As 4 URLs de `/forja/*` são uma tela só** — mesmo componente, muda a aba. É desenho, não bug.
2. **Todas as 5 telas leem `mcp_jira_tasks` (`McpTask`).** É o mesmo backlog em cinco vitrines; o que difere é o recorte (FORJA fixo × projeto configurável) e a forma (kanban × abas × Gantt).

**Causa histórica:** em 2026-07-31 o `Modules/TeamMcp` foi deletado e suas capacidades **movidas** pra Forja (PR #5120 trouxe o cockpit `/forja` de 6 abas). O módulo já tinha as telas nativas herdadas do ex-`ProjectMgmt`. O [`SCOPE.md`](../requisitos/Forja/SCOPE.md) registrou a dívida no ato, textual: *"⚠️ MOVIDO, NÃO FUNDIDO: as abas triagem/backlog/quadro/changelog sobrepõem Triage/Backlog/Board/Activity deste módulo. Fundir = deletar uma implementação = decisão [W]"*. O `BRIEFING.md` repetiu como "próxima ação verificável". Esta ADR é essa decisão.

**Nomenclatura:** o módulo passou a se chamar Forja em 2026-07-30 (data registrada no [BRIEFING](../requisitos/Forja/BRIEFING.md); o rename seguiu o padrão PHP-only da [ADR 0088](0088-module-rename-php-only.md), que preserva a fachada legacy) mas a URL nativa continua `/project-mgmt/*` por compatibilidade ([ADR 0087](0087-drift-resolution-sem-mover-url.md)). Convivem hoje: módulo `Forja`, URL nativa `/project-mgmt`, e uma segunda UI em `/forja` vinda de outro módulo.

## Decisão

**D1 — `/project-mgmt/*` morre.** As 8 telas (`Activity`, `Backlog`, `Board`, `Burndown`, `Inbox`, `MyWork`, `Roadmap`, `Triage`) e as 32 rotas do prefixo saem. Decisão [W] 2026-08-04.

**D2 — `/team-mcp/tasks` sobrevive** e recebe o papel de quadro/kanban do time.

**D3 — `/forja/quadro` é substituído por `/team-mcp/tasks`.** Duas implementações de kanban não sobrevivem à consolidação.

**D4 — O Gantt vira aba da Forja.** `/ia/admin/roadmap` (hoje em `Modules/Jana`) migra para o cockpit `/forja`. Coerente com a [ADR 0366](0366-fronteira-jana-forja-governance-kb.md): planejamento de trabalho do time é Forja, não Jana.

**D5 — `MyWork`, `Inbox` e `Burndown` morrem sem receptor.** Perda consciente, decisão [W]. O time passa a usar as tools MCP equivalentes (`my-work`, `my-inbox`). ⚠️ Duas US estavam **em review** quando esta decisão foi tomada (`US-TR-305` Inbox marcar lido, `US-TR-306` Inbox deep-link) — morrem junto, e isso é o custo aceito, não um esquecimento. Segue o precedente da [ADR 0363](0363-governance-incorpora-ads-nucleo-sem-receptor.md): capacidade sem receptor é perda declarada, nunca acidente.

**D6 — A triagem sobrevive no `/forja`, não como filtro puro.** [W] perguntou se ela poderia morrer, "sendo apenas um filtro antes do backlog". **A medição diz que metade da premissa procede:** a *lista* é literalmente um filtro (`McpTask::triage()` scope — sem owner **ou** sem priority **ou** `status=backlog`), mas o `TriageController` tem **5 endpoints além do index** — `assign` (owner/prioridade/cycle/epic inline), `dossier`, `aprovar`, `rejeitar`, `fundir`. Isso é fluxo de decisão, não recorte de lista. A aba `/forja` já tem a segunda cópia desses verbos (`forja.aprovar`/`rejeitar`/`fundir`/`dossier`), então o receptor **já existe** e nada precisa ser portado: morre a tela `/project-mgmt/triage`, fica a aba.

**D7 — O quarter view NÃO morre agora. Decisão delegada.** [W] havia escolhido matá-lo e em seguida disse textual: *"pode decidir por mim eu não sei o que é"*. Decidido por [CC] com evidência de tela em produção:

- `/project-mgmt/roadmap` (quarter): **5 epics vivos** agrupados por trimestre com progresso real (`Memória & KB 8%`, `Token Economy 60%`, `LGPD & Observability 40%`). Cabe numa tela; responde *"o que entregamos neste trimestre e quanto andou"*.
- `/ia/admin/roadmap` (Gantt): `500 tasks no filtro atual` · `Timeline (531 linhas)`; as barras não se localizam na viewport e o topo mostra tasks de maio (`Accounting`, `Listar Budget`). Hoje é despejo cronológico, não tela de decisão.

Trocar a visão curada pelo dump seria regressão. O quarter view sobrevive como segunda leitura do roadmap e **só sai quando o Gantt provar que substitui** (filtro por cycle efetivo + volume domado). Reversível numa linha se [W] discordar.

## Consequências

### Gap medido: `/team-mcp/tasks` ainda não cobre o Board (D2/D3 dependem de fechar isto)

| capacidade | Board | Tasks |
|---|---|---|
| kanban + drag + KPIs + drawer | ✅ | ✅ (`TaskDrawer`) |
| atalhos `J`/`K`/`Enter`/`/` | ✅ | ✅ (+ `X` bulk, que o Board não tem) |
| **`E`/`A` — mudar status por teclado** | ✅ | ❌ |
| **overlay `?`** | ✅ (PMG-008, 2026-08-03) | ❌ |
| **filtros cycle / epic / owner** | ✅ | ❌ (zero ocorrências no `.tsx`) |

O `/team-mcp/tasks` carrega uma **segunda implementação de teclado**, copiada e mais pobre. O hook [`useBoardShortcuts`](../../resources/js/Pages/Forja/Board/_components/useBoardShortcuts.ts) foi extraído em 2026-08-03 justamente pra ser compartilhável: portá-lo fecha `E`/`A` + `?` e mata a duplicação num movimento só. O spec [`tests/forjaBoardShortcuts.spec.tsx`](../../tests/forjaBoardShortcuts.spec.tsx) e a lane `forja-shortcuts-gate` acompanham o hook para onde ele for.

### Custo de execução

- **32 rotas** sob `/project-mgmt`, **8 telas**, **8 charters**, **0 `casos.md`**, **4 arquivos Pest** citando as rotas.
- ⛔ **Permissions Spatie vivem por id de linha** — o motivo forte pelo qual a [ADR 0087](0087-drift-resolution-sem-mover-url.md) congelou URLs. Remover rota ≠ remover permission; mexer em prefixo **revoga acesso em silêncio**, sem erro e sem log. As permissions `projectmgmt.*` só saem com migração explícita e verificada.
- O `jana.mcp.usage.all` (permission legada herdada) gateia quase tudo — não criar permission nova da Forja no caminho.

### O que melhora

Um lugar por pergunta: quadro em `/team-mcp/tasks`, triagem/backlog/changelog no `/forja`, roadmap (Gantt + quarter) no `/forja`. Some a pergunta "em qual das cinco eu olho?".

### O que piora

Perde-se MyWork, Inbox e Burndown (D5) e o histórico de trabalho recente nelas. O time passa a depender das tools MCP pra essas três leituras.

## Plano de etapas

Cada etapa é PR próprio, com gate [W] entre elas. Ordem é topológica — nada morre antes de o receptor existir e ser verificado.

- **E1** — Portar `useBoardShortcuts` + `ShortcutsOverlay` pro `/team-mcp/tasks`, removendo o `keydown` duplicado de lá. Fecha `E`/`A` + `?`. A lane `forja-shortcuts-gate` passa a cobrir as duas telas.
- **E2** — Levar filtros cycle/epic/owner pro `/team-mcp/tasks`. Ao fim, `/team-mcp/tasks` ≥ Board em capacidade (é a pré-condição de D1/D3).
- **E3** — Migrar o Gantt de `Modules/Jana` pro cockpit `/forja` como aba (D4). Quarter view segue vivo (D7).
- **E4** — Aposentar `/forja/quadro` (D3), com redirect pra `/team-mcp/tasks`.
- **E5** — Aposentar `/project-mgmt/*` (D1/D5): redirects 301 das rotas que têm receptor, remoção das que não têm, e **auditoria das permissions `projectmgmt.*`** antes de qualquer remoção.
- **E6** — Smoke real em produção de cada URL sobrevivente + atualizar `SCOPE.md` (§cockpit deixa de dizer "MOVIDO, NÃO FUNDIDO") e `BRIEFING.md` (sai de "próxima ação verificável").

## Gate de reversão

- Se `/team-mcp/tasks` não alcançar paridade em E1+E2, **D1/D3 não executam** — o Board fica.
- Se o Gantt não domar as 531 linhas em E3, D4 fica só como aba adicional e o quarter view segue como leitura primária (já é o estado de D7).
- Reverter D7 (matar o quarter view) é decisão [W] de uma linha, a qualquer momento.

## Referências

- [`memory/requisitos/Forja/SCOPE.md`](../requisitos/Forja/SCOPE.md) §cockpit — a dívida declarada no ato da absorção
- [`memory/requisitos/Forja/BRIEFING.md`](../requisitos/Forja/BRIEFING.md) — "próxima ação verificável"
- [ADR 0087](0087-drift-resolution-sem-mover-url.md) — URLs congeladas / permissions por id
- [ADR 0363](0363-governance-incorpora-ads-nucleo-sem-receptor.md) — precedente de perda consciente sem receptor
- [ADR 0366](0366-fronteira-jana-forja-governance-kb.md) — fronteira Jana × Forja (sustenta D4)
