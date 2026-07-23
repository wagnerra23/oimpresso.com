---
name: comparativo-do-modulo
mission: "Substituir auditoria manual de gap módulo × estado da arte por inventário governado e backlog priorizado."
description: ATIVAR quando user pedir "comparar módulo X com mercado", "auditar escopo do módulo Y", "o que falta no módulo Z vs estado da arte", "inventário aprovado/reprovado de {módulo}", "/comparativo {módulo}". Cruza CAPTERRA-FICHA.md (concorrentes + capacidades baseline + score P0-P3) + SPEC.md (US-XXX-NNN) + código real em Modules/{X}/ → produz CAPTERRA-INVENTARIO.md em 3 buckets (✅ APROVADO / 🟡 PARCIAL / ❌ AUSENTE) → propõe batch de tasks-create no MCP pros gaps priorizados → Wagner aprova → cria tasks + apenda US ao SPEC. NÃO cria tasks sem confirmação humana (publication-policy).
type: process-skill
status: active
version: 2.0.0
trust_level: L2
owner: wagner
created_at: 2026-05-06
updated_at: 2026-05-08
charter_adr: 0089
extends_adr: 0101  # v2.0 adiciona 3 eixos (features + UX + automação)
parent_mission: "Toda skill substitui trabalho humano repetitivo com ROI provado, rumo ao ERP autônomo de R$ [redacted Tier 0]M em 24 meses."
triggers_on:
  - "/comparativo"
  - "/comparativo {modulo}"
  - "comparar {modulo} com mercado"
  - "comparar {modulo} com estado da arte"
  - "auditar escopo {modulo}"
  - "o que falta no {modulo}"
  - "inventário {modulo}"
  - "capterra {modulo}"
does_not_trigger_on:
  - editar SPEC.md à mão (use editor direto)
  - criar comparativo Capterra novo (use template em memory/research/comparativos/)
  - criar tasks ad-hoc (use tasks-create direto)
  - comparar 2 produtos genéricos sem módulo do oimpresso (use brainstorm)
roi_metric:
  type: time
  baseline: "Wagner audita escopo de módulo manual em ~2h (lê SPEC + lê comparativo Capterra + cruza com Modules/{X}/ + decide gaps)"
  target: "/comparativo {modulo} reduz pra ~10min — ler inventário gerado + aprovar gaps em batch"
metrics:
  inventarios_gerados: 0
  gaps_aprovados_total: 0
  gaps_rejeitados_total: 0
  modulos_cobertos: []
artefatos_governados:
  - "memory/requisitos/{Modulo}/CAPTERRA-FICHA.md (input curado)"
  - "memory/requisitos/{Modulo}/CAPTERRA-INVENTARIO.md (output gerado)"
  - "memory/requisitos/{Modulo}/SPEC.md (apêndice de US aprovadas)"
  - "mcp_tasks via tasks-create (backlog priorizado)"
tier: B
parent_adr: 0095
---

# comparativo-modulo-arte (v2.0 — 3 eixos)

Skill genérica para auditar qualquer módulo do oimpresso contra estado da arte do mercado em **3 eixos** (features + UX + automação). Pattern canônico: [ADR 0089](../../../memory/decisions/0089-capterra-driven-module-evolution.md) + extensão v2 em [ADR 0101](../../../memory/decisions/0101-sistema-charter-capterra-governanca-escopo.md).

## v2.0 vs v1.0 — o que mudou

v1 mediu só **features** (`capacidades:`). v2 mede 3 eixos com mesma escala ✅🟡❌:

| Eixo | YAML key na FICHA | Pergunta |
|---|---|---|
| **Features** (v1) | `capacidades:` | O concorrente faz X? |
| **Usabilidade** (v2) | `ux_heuristics:` | Como faz? Cliques, tempo, erro recuperável? |
| **Automação** (v2) | `automation_targets:` | Faz sem humano? Listener? Cron? Webhook? |

Inventário v2 (`CAPTERRA-INVENTARIO.md`) tem 3 tabelas (1 por eixo) em vez de 1. Skill regenera tudo.

Fichas existentes apendam `ux_heuristics:` + `automation_targets:` vazios (TODO Wagner curate). Skill **não falha** com placeholder — só pula o eixo no inventário com nota "TODO".

## Quando ativar

Wagner (ou outro dev) pede análise de gaps de um módulo específico. Triggers explícitos:
- `/comparativo {modulo}` (slash command)
- "compare RecurringBilling com mercado"
- "auditar escopo do Financeiro vs estado da arte"
- "inventário Capterra do NfeBrasil"

**Nunca** executar para módulo que não tem `CAPTERRA-FICHA.md`. Se faltar, **parar** e instruir Wagner a criar a partir do template.

## Os 7 passos do ciclo

### 1. Validar pré-condições

```
- memory/requisitos/{Modulo}/CAPTERRA-FICHA.md existe?
  - SE NÃO: parar, instruir "Crie a ficha primeiro: copy memory/requisitos/_TEMPLATE_capterra_ficha.md → memory/requisitos/{Modulo}/CAPTERRA-FICHA.md e curate"
- memory/requisitos/{Modulo}/SPEC.md existe?
  - SE NÃO: avisar mas continuar (módulo pode ser novo)
- Modules/{Modulo}/ existe no filesystem?
  - SE NÃO: parar, instruir "Módulo não existe ainda. Use skill criar-modulo primeiro"
```

### 2. Ler artefatos canônicos

```
Read memory/requisitos/{Modulo}/CAPTERRA-FICHA.md
Read memory/requisitos/{Modulo}/SPEC.md (se existir)
Glob Modules/{Modulo}/Models/*.php → lista classes
Glob Modules/{Modulo}/Http/Controllers/*.php → lista controllers
Glob Modules/{Modulo}/Services/**/*.php → lista services
Glob Modules/{Modulo}/Database/Migrations/*.php → lista migrations
Glob Modules/{Modulo}/Tests/**/*.php → lista testes (cobertura)
Glob resources/js/Pages/{Modulo}/**/*.tsx → lista telas Inertia
```

Não ler conteúdo completo — só listar arquivos. Conteúdo só pra capacidades específicas no passo 3.

### 2.5. Carregar a etapa de auditoria específica do módulo

A FICHA do módulo declara uma seção `## Como auditar este módulo` com instruções customizadas — porque cada módulo tem evidências diferentes de "está pronto":

- RecurringBilling — capacidade "Boleto Inter API" exige: driver instanciável + cert válido + teste com sandbox response mockada
- Financeiro — capacidade "Conciliação OFX" exige: parser + tela de match + cobertura de transações reconciliadas em prod >70%
- NfeBrasil — capacidade "NFe modelo 55" exige: certificado A1 carregado + autorização SEFAZ + DANFE renderizada

Ler essa seção antes do passo 3. Se a FICHA não tiver, usar critério genérico (Model + Controller + Test + UI) — mas avisar Wagner que a auditoria seria mais precisa com seção customizada.

### 3. Classificar cada capacidade da FICHA

Para cada capacidade declarada em `CAPTERRA-FICHA.md` na seção `capacidades:`, decidir bucket:

```
✅ APROVADO se TODAS as condições:
  - Existe Model/Service/Migration que materializa a capacidade
  - Existe rota/controller que expõe (web, API ou job)
  - Existe pelo menos 1 teste cobrindo o caminho feliz (Pest)
  - SPEC.md tem US correspondente marcada como "implementada"

🟡 PARCIAL se:
  - Código existe mas falta UI (resources/js/Pages/...) OU
  - Código existe mas sem teste OU
  - Existe MVP mas SPEC.md tem US "implementada-com-bug" / "incompleta"

❌ AUSENTE se:
  - Nenhuma evidência no código + SPEC.md sem US correspondente
```

Critério de evidência: cada classificação precisa **citar arquivos específicos** que motivaram. Se não conseguir achar evidência clara, perguntar a Wagner antes de classificar.

### 4. Escrever CAPTERRA-INVENTARIO.md

Sobrescrever (não apender) o arquivo:

```markdown
# CAPTERRA-INVENTÁRIO — {Modulo}

> Gerado por skill `comparativo-modulo-arte` em {YYYY-MM-DD HH:MM}.
> Fontes: CAPTERRA-FICHA.md + SPEC.md + Modules/{Modulo}/ + resources/js/Pages/{Modulo}/.
> ADR: [0089](../../decisions/0089-capterra-driven-module-evolution.md).

## Resumo

- ✅ APROVADO: {N} de {Total}
- 🟡 PARCIAL: {N}
- ❌ AUSENTE: {N}
- Score médio dos gaps: P{X}

## Inventário detalhado

| Capacidade | Score | Status | Evidência | Próximo passo |
|---|---|---|---|---|
| Boleto registrado API | P0 | ✅ APROVADO | Modules/RB/Services/Boleto/Drivers/InterDriver.php + tests | — |
| PIX recorrente | P1 | ❌ AUSENTE | Sem código nem US | Criar US-RB-PIX-REC |
| Split payment | P3 | 🟡 PARCIAL | Modelo SplitConfig.php existe, falta UI e teste | US-RB-SPLIT-UI |

## Tasks propostas (aguardando aprovação Wagner)

1. [P0] Implementar **{capacidade}** — `module:{X} priority:P0` — _evidência: {lista de fontes}_
2. [P1] ...

> Use `/comparativo {Modulo} aprovar 1,3,5` ou aprove manualmente em texto.
```

### 5. Apresentar batch e perguntar aprovação

Mostrar para Wagner em texto curto:

```
Inventário gerado: memory/requisitos/{Modulo}/CAPTERRA-INVENTARIO.md
Resumo: ✅{N} 🟡{N} ❌{N}

Tasks propostas (priorizadas P0→P3):
1. [P0] {título} — {evidência curta}
2. [P1] {título} — ...
N. [P3] {título} — ...

Quais aprovo? (todos / nenhum / "1,3,5" / "P0+P1")
```

**Não criar tasks ainda.** Aguardar Wagner.

### 6. Criar tasks aprovadas no MCP

Para cada task aprovada, chamar `tasks-create` (tool MCP) com:
- `module: {Modulo}`
- `priority: P{score}`
- `title: "{capacidade da FICHA}"`
- `description: "Gap detectado pelo /comparativo em {data}. Evidência: {fontes}. Score da FICHA: P{N} ({razão})."`
- `tags: ["capterra-gap", "from-skill"]`
- `cycle: current` (se houver cycle ativo)

**Em batch:** uma chamada por task. Reportar ID retornado de cada uma.

### 7. Apender US ao SPEC.md + commit + push

Para cada task criada:
- Apender bloco `### US-{Modulo}-{ID curto}` em `memory/requisitos/{Modulo}/SPEC.md` (seção "Backlog vindo do Capterra-Inventário")
- Bloco contém: título, score, evidência, link pro task ID

Após processar tudo:
- `git add memory/requisitos/{Modulo}/CAPTERRA-INVENTARIO.md memory/requisitos/{Modulo}/SPEC.md`
- `git commit -m "feat({modulo,lower}): inventário Capterra + N tasks aprovadas via /comparativo"`
- `git push origin main` (ou branch atual se em worktree)

Webhook GitHub→MCP propaga em <60s.

## Saída final pro Wagner

```
✅ Concluído.
- Inventário: memory/requisitos/{Modulo}/CAPTERRA-INVENTARIO.md (commit {sha})
- Tasks criadas: {N} ({P0:N P1:N P2:N P3:N})
  - TASK-RB-001: {título}
  - TASK-RB-002: ...
- SPEC.md atualizado: {N} US apendadas
- Push: {sha} → main
- MCP sync: ~60s

Próximo passo sugerido: cycles-active pra ver onde encaixar essas tasks.
```

## Reprovações (não fazer)

- ❌ Criar task sem aprovação explícita do Wagner
- ❌ Editar `CAPTERRA-FICHA.md` (curadoria humana, não automatizar)
- ❌ Inventar capacidade que não está na FICHA (escopo é a FICHA)
- ❌ Classificar como ✅ sem evidência de teste OU UI (cobertura parcial = 🟡)
- ❌ Rodar para módulo sem FICHA (parar e instruir criar)
- ❌ Sobrescrever ADRs ou tasks existentes — sempre criar novas
- ❌ Pular `git commit + push` no final (artefatos têm que sincronizar com MCP)

## Métricas a popular

A cada execução bem-sucedida, atualizar `metrics:` no frontmatter desta skill:
- `inventarios_gerados` += 1
- `gaps_aprovados_total` += N (aprovados na sessão)
- `gaps_rejeitados_total` += N (propostos mas rejeitados)
- `modulos_cobertos` adicionar `{Modulo}` se ainda não estiver na lista

Isso vira input para retro de cycle e para validação do ROI da skill (ADR 0089 §Validação).

## Integração futura (Fase 2 — tela admin)

Quando US `srs-admin-page` for implementada, esta skill será reaproveitada como **backend** da tela:
- Tela renderiza CAPTERRA-INVENTARIO.md
- Botão [Aprovar gap] chama mesma rotina do passo 6 desta skill via endpoint
- Wagner aprova com 1 click em vez de "1,3,5" em texto

A lógica de classificação e geração de tasks **não muda**.
