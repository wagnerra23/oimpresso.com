---
name: cliente-funcionario-collector
description: BLOQUEADOR Tier A — quando detectar cliente novo (business_id ≠ {1, 4, 99}), funcionário novo (nome próprio + role + cliente), decisão arquitetural envolvendo cliente, ou marco datável (endossou/aprovou/pausou/canary/cutover), carrega template e força registro em `memory/reference/clientes/<slug>.md` ou `funcionarios/<cliente>/<slug>.md`. Wagner pediu 2026-05-14 — "isso é ouro, crie a regra, eu nem preciso avisar". ADR 0144 proposed.
tier: A
type: skill
trust_level: L1
owner: wagner
parent_adr: 0095
related_adrs: [0061, 0094, 0105, 0131]
proposal: cliente-funcionario-perfis-coleta-sistematica
status: proposed
---

# cliente-funcionario-collector — Tier A bloqueador

> **Origem:** Wagner reagiu ao perfil Martinho atualizado em 2026-05-14 noite — *"isso tem que ter regra eu nem preciso avisar que tem que ser assim, colete de cada cliente e cada funcionário. isso é ouro"*. Skill nasce pra cumprir esse pedido sem depender da memória de Wagner. ADR 0144 proposed (proposal em [memory/decisions/proposals/cliente-funcionario-perfis-coleta-sistematica.md](../../../memory/decisions/proposals/cliente-funcionario-perfis-coleta-sistematica.md)).

## Quando ativa (matchers)

Skill dispara SEM Wagner pedir quando Claude detectar QUALQUER um dos triggers abaixo na conversa atual ou em Edit/Write paths.

### Trigger 1 — Menção a business_id novo

Regex: `business_id\s*[=:]?\s*\d+` OU `biz\s*[=:]?\s*\d+` OU `B\d+` (formato curto).

| `business_id` | Ação |
|---|---|
| `1` (Wagner próprio) | NÃO ativar — caso especial dono |
| `4` (ROTA LIVRE) | NÃO ativar — perfil maduro existente |
| `99` (sandbox) | NÃO ativar — test fixture |
| **qualquer outro** | **ATIVAR** — verificar perfil + criar stub se ausente |

Exemplo positivo: `"vamos importar dados pro biz=164 do Martinho"` → ativa.

### Trigger 2 — Nome próprio + role operacional + cliente

Heurística (PT-BR): nome capitalizado (`[A-ZÁÊÇ][a-záêç]+`) seguido por verbo de papel:
- `cuida (de|do|da)` · `opera` · `vende` · `compra` · `trabalha em` · `responsável por`
- `é (a |o )?(dona|dono|gerente|operador|operadora|financeiro|vendedor|champion)`

Exemplo positivo: `"Lara cuida do estoque do Martinho"` → ativa criar `memory/reference/funcionarios/martinho-cacambas/lara.md` se ausente.

Falso positivo a evitar: `"Wagner cuida do oimpresso"` (Wagner = dono próprio, não funcionário cliente).

### Trigger 3 — Decisão arquitetural envolvendo cliente

Dispara em paralelo a `commit-discipline` quando:
- ADR/proposal sendo criada em `memory/decisions/**/*.md` cita razão social, slug cliente ou business_id de cliente real
- PR description menciona "canary biz=N" / "piloto biz=N" / "produção biz=N"

Ação: append em `## Histórico` do perfil cliente + cross-link ADR.

### Trigger 4 — Incidente / marco datável

Palavras-gatilho na conversa (case-insensitive):
- `endossou` · `aprovou` · `reclamou` · `pausou` · `retomou` · `churned` · `cancelou contrato`
- `assinou contrato` · `início canary` · `cutover` · `mudou de status`

Exemplo: `"Jair endossou 14/maio"` → append `## Histórico` em `martinho-cacambas.md` + `funcionarios/martinho-cacambas/jair.md`.

### Trigger 5 — Status mudança detectada

Mudança em frontmatter de perfil cliente:
- `qualificado → piloto-ativo` · `piloto-ativo → producao` · `* → churned`

Ação: bloquear Edit/Write sem entrada `## Histórico` datada justificando transição.

### Trigger 6 — Palavra-chave Wagner

Frases-trigger explícitas:
- `"salve no perfil"` · `"anota no cliente X"` · `"isso é ouro"` · `"registra no perfil do funcionário"` · `"perfil do <Nome>"` · `"cliente <Nome>"`

Ação: force update imediato. Perguntar só se ambíguo (qual cliente / qual funcionário).

## Pré-vôo automático (passos obrigatórios na ordem)

```
PASSO 1 — Determinar slug alvo
   Cliente:    slug = kebab(razao_social ou nome curto)  ex: "martinho-cacambas"
   Funcionário: slug = lowercase(first_name)             ex: "lara"

PASSO 2 — Glob perfil existente
   Cliente:     memory/reference/clientes/<slug>.md
   Funcionário: memory/reference/funcionarios/<cliente>/<slug>.md

PASSO 3 — Bifurcação
   SE perfil existe →
     a. Ler frontmatter + section Histórico
     b. Adicionar entrada `## Histórico` datada (append-only)
     c. Bumpar `ultima_atualizacao: YYYY-MM-DD` no frontmatter
     d. NUNCA reescrever sections existentes

   SE perfil NÃO existe →
     a. Ler template (clientes/_TEMPLATE.md ou funcionarios/_TEMPLATE.md)
     b. Validar signal real (cliente paga OU reportou OU métrica detectou drift — ADR 0105)
     c. Se sem signal → PARAR + perguntar Wagner se deve criar stub
     d. Se signal OK → criar stub preenchendo só o que sabe + frontmatter
     e. Marcar PII fields como `pii_vault_ref: vault://<cliente-slug>/<funcionario-slug>` (não escrever PII real)

PASSO 4 — Cross-link bidirecional
   Cliente recebe ref ao funcionário em `## Stakeholders`
   Funcionário tem `cliente_slug: <slug>` no frontmatter

PASSO 5 — Validar LGPD (PII guard)
   Antes de Write/Edit, scan do diff:
     /\b\d{3}\.\d{3}\.\d{3}-?\d{2}\b/   (CPF)
     /\b\d{2}\.\d{3}\.\d{3}\/\d{4}-?\d{2}\b/   (CNPJ — permitido se razao_social pública)
     /\b[\w._-]+@[\w._-]+\.\w+\b/   (email — permitido só corporativo @cliente.com.br; pessoais bloqueia)
     /\b\(?\d{2}\)?\s?9?\d{4}-?\d{4}\b/   (telefone BR)
   Se match → BLOQUEAR commit + alerta "PII real detectada → Vaultwarden vault://<path>"
```

## Templates (referência)

Skill NÃO duplica templates inline — depende de F2 ter criado:

- `memory/reference/clientes/_TEMPLATE.md` (skeleton 10 sections — Identificação · Stakeholders · Saúde financeira · Sistema atual · Arquitetura migração · Pricing · Sensibilidades · Estado prod · Histórico · Refs)
- `memory/reference/funcionarios/_TEMPLATE.md` (skeleton 6 sections — Papel · Acesso sistemas · Preferências UX · Sensibilidades · Histórico · Refs)

Se template ausente quando skill dispara → alertar Wagner + apontar para F2 incompleta.

## Governança LGPD (proposta §7)

### Permitido em git canônico

- Razão social (público registro)
- CNPJ (público)
- Endereço comercial (público)
- `first_name` funcionário (nome curto)
- Role operacional
- Preferências UX agregadas
- Sensibilidades descritas sem nome real

### Obrigatório em Vaultwarden (`vault.oimpresso.com`)

- CPF / RG funcionário
- Email pessoal (não corporativo)
- Telefone/WhatsApp pessoal
- Endereço residencial
- Senhas iniciais oimpresso (até reset)

Cross-link via `pii_vault_ref: vault://<cliente-slug>/<funcionario-slug>` no frontmatter funcionário. Skill **valida** que este campo está populado quando funcionário criado · alerta passivo se ausente (não bloqueia, mas avisa).

## Anti-patterns proibidos

- ❌ **Criar perfil de cliente prospect sem signal real** (LGPD + ruído — ver [ADR 0105](../../../memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)). Hipótese sem sinal vira ADR feature-wish, não perfil.
- ❌ **Duplicar info entre `_INDEX.md` e `<slug>.md`** — single source of truth: índice tem só tabela de navegação curta; detalhes vivem em `<slug>.md`
- ❌ **Inflar perfil com features wish não realizadas** — só fatos verificáveis (canary começou? entrou em prod? assinou contrato?)
- ❌ **PII real em git canônico** — CPF/email pessoal/telefone vão para Vaultwarden via `pii_vault_ref`. Hook de validação bloqueia commit
- ❌ **Esquecer cross-link com perfil legacy** em `research/clientes-legacy-officeimpresso/`. Frontmatter campo `perfil_legacy:` é obrigatório quando cliente tem histórico Office Comercial Delphi
- ❌ **Reescrever sections existentes** — `## Histórico` é append-only. Datas anteriores nunca apagadas
- ❌ **Disparar pra Wagner próprio (biz=1)** — caso especial dono, perfil mora em `clientes/wagner-wr2.md` se existir, mas skill não auto-cria

## Sempre fazer

- ✅ **Cross-link bidirecional** cliente↔funcionário (cliente lista funcionários · funcionário aponta `cliente_slug`)
- ✅ **Bump `ultima_atualizacao: YYYY-MM-DD`** no frontmatter em qualquer mudança
- ✅ **Adicionar entrada `## Histórico` datada** em qualquer trigger 3/4/5 (decisão · marco · status)
- ✅ **Validar signal real** antes de criar stub novo cliente (ADR 0105)
- ✅ **Perguntar Wagner antes de criar** se ambíguo (qual slug · qual cliente · qual funcionário)
- ✅ **PT-BR em todo conteúdo** (texto, sections, frontmatter values exceto chaves técnicas)
- ✅ **Brevidade** — perfil cliente típico ≤300 linhas; funcionário típico ≤80 linhas

## Quando NÃO ativar

Skill **NÃO** dispara em:

1. **Wagner como sujeito** (biz=1) — caso especial dono; perfil mora em outro lugar
2. **ROTA LIVRE biz=4** mencionado SEM novidade — perfil maduro existente, só atualiza em trigger 4/5
3. **Sandbox biz=99** — test fixture, ignorar
4. **Hipotético / feature-wish** — ADR 0105 manda: só cliente que paga + reporta OU métrica detecta drift gera perfil
5. **Conversa sobre time interno** (Wagner / Felipe / Maiara / Luiz / Eliana[E]) — eles têm `TEAM.md` raiz, não `funcionarios/`
6. **Eliana(WR2) cliente externa** — desambiguação: `Eliana[E]` esposa ≠ `Eliana(WR2)`; perfil WR2 é cliente externo com tratamento separado, não dispara skill funcionários (criar via `comparativo-do-modulo` se for novo cliente externo)

## Como desligar manualmente

Em casos raros (debug, sessão exploratória, refactor de templates), Wagner pode comentar:

```
/no-collector <razão curta>
```

Skill respeita o flag por turno corrente. Próximo turno volta a operar normal.

## Métricas (telemetria)

`mcp_skill_telemetry` tracking:
- `trigger_count` por sessão → deve crescer quando cliente novo entra
- `stubs_created` → quantos perfis criados via skill (vs ad-hoc por Wagner)
- `pii_blocks` → quantas vezes regex bloqueou PII real em git
- `wagner_overrides` → quantas vezes `/no-collector` usado

Meta 90 dias (proposta §12):
- 100% clientes piloto+ com perfil completo
- 100% funcionários champion com perfil
- 0 PII em git canônico
- 0 pedidos manuais Wagner "salve no perfil"

## Referências

- **Proposal:** [`memory/decisions/proposals/cliente-funcionario-perfis-coleta-sistematica.md`](../../../memory/decisions/proposals/cliente-funcionario-perfis-coleta-sistematica.md) — ADR 0144 candidato
- [ADR 0061](../../../memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — Conhecimento canônico git + MCP zero auto-mem
- [ADR 0094](../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípios §1 Context as a product · §3 Charter > Spec · §5 SoC brutal · §7 Transparência)
- [ADR 0095](../../../memory/decisions/0095-skills-tiers-convencao-interna.md) — Skills tiers (esta vira Tier A)
- [ADR 0105](../../../memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado
- [ADR 0131](../../../memory/decisions/0131-tiering-memoria-canonico-local-segredo.md) — Tiering memória (segredo Vaultwarden · canônico git · local pessoal)
- Skill [`brief-first`](../brief-first/SKILL.md) — pattern Tier A auto-trigger referência
- Skill [`multi-tenant-patterns`](../multi-tenant-patterns/SKILL.md) — pattern Tier A com proibições/sempre fazer referência
- Skill [`commit-discipline`](../commit-discipline/SKILL.md) — coordena PII guard (regex CPF/email/telefone)
- RUNBOOK uso: [`memory/requisitos/_Skills/RUNBOOK-cliente-funcionario-collector.md`](../../../memory/requisitos/_Skills/RUNBOOK-cliente-funcionario-collector.md)

## Status promoção Tier A

- **2026-05-14:** criada como `tier: A status: proposed` aguardando ADR 0144 accepted
- **Pendente Wagner segunda 2026-05-19:** mover proposal → `0144-perfis-cliente-funcionario.md` accepted
- **Após accepted:** atualizar `status: live` neste frontmatter + adicionar à matriz Tier A canônica em [03-skills-audit.md](../../../memory/sprints/s3-constituicao/03-skills-audit.md) + sincronizar [`CLAUDE.md`](../../../CLAUDE.md) §"Skills Tier A" + [`tier-a-banner.ps1`](../../hooks/tier-a-banner.ps1) (regra F.1 da auditoria — sincronizar 3 fontes)
