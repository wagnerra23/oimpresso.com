---
name: feedback-capture
description: ATIVAR quando Wagner colar feedback de cliente real OU disser "Daniela reclamou X", "Larissa pediu Y", "Kamila falou que Z", "Jair quer W", "via WhatsApp <pessoa> reportou", "/feedback ..." OU mencionar reclamação/pedido alteração/sugestão de cliente real do oimpresso. Estrutura captura em 7 campos canônicos (canal, persona, literal, JTBD, workaround, severity NN/g 0-4, frequência) + grava append-only em memory/clientes/<cliente>/feedback/YYYY-MM-DD-<slug>.md + atualiza persona.fricoes + atualiza charter.fricoes_conhecidas + cria MCP task quando severity ≥ 3. Skill Tier B auto-trigger. Refs ADR UI-0016, ADR 0105.
---

# feedback-capture — Captura estruturada de feedback de cliente

Quando ativar (auto-trigger description matches):
- `/feedback ...` no input
- "<persona> reclamou de X"
- "<persona> pediu Y"
- "<persona> falou Z"
- "<persona> não gostou"
- "via WhatsApp <pessoa> reportou"
- "ligaram reclamando"
- Wagner cola texto literal de WhatsApp/email/SMS cliente

NÃO ativar pra:
- Wagner próprio feedback dele (= não é cliente — usar TaskCreate direto)
- Reflexão estratégica sem feedback concreto de cliente real
- Feedback de prospect frio sem persona criada (rodar `cliente-discovery` antes)

## Workflow

### 1. Identifica persona

Skill detecta nome (Daniela, Larissa, Kamila, Jair) OU pergunta se ambíguo. Resolve via `memory/clientes/<cliente>/personas/<slug>.yml`. Se persona não existe → sugerir `cliente-discovery` primeiro.

### 2. Captura 7 campos canon (estruturado)

```yaml
data: 2026-05-27
hora: '14:32'
canal: whatsapp | call | presencial | email | suporte_inbox | inferido
quem:
  persona_slug: kamila-martinho
  cliente_real: martinho-cacambas
  business_id: <int>

o_que_disse:
  literal: '"texto LITERAL entre aspas"'
  contexto: <onde foi dita — ex pós-uso NF-e>

quando_no_produto:
  modulo: <financeiro / oficinaauto / cliente / sells / etc>
  tela: <ex /financeiro/contas-receber>
  acao: <ex emitir-nfe, fechar-os, cobrar-cliente>

job_por_tras:                           # Mom Test reverso — o que ela queria atingir?
  job: <ex: emitir nota sem erro pro contador>
  motivacao: <funcional | emocional | social>

workaround_atual:                       # como ela lida hoje?
  o_que_faz: <ex: liga pro Wagner toda vez>
  custo: <ex: 10min/dia perdido + frustração>

severity_nng: 0-4                       # NN/g 1995
# 0 = não é problema (sugestão wish-list)
# 1 = cosmético (chato mas convive)
# 2 = minor (problema real mas tem workaround)
# 3 = major (impede tarefa frequente)
# 4 = catastrófico (bloqueia uso do sistema)

frequencia:
  primeira_vez: true | false
  recorrente_para_ela: <int — quantas vezes ela reportou isso>
  outros_clientes_tambem: <slug-list ou []>
  pattern_emergente: true | false       # 3+ clientes diferentes mesma reclamação

acao_imediata:
  status: novo                          # novo → triaged → backlog → in_progress → resolved → closed
  responder_cliente: <ação imediata pendente>
  task_mcp_id: <id se severity≥3 — auto-criada>

# Loop de retorno
resolucao:
  data_resolvido: null                  # preenche depois
  pr_link: null                         # PR que resolveu
  cliente_confirmou: null               # cliente disse "agora está bom"?
```

### 3. Grava append-only

Arquivo: `memory/clientes/<cliente-real>/feedback/YYYY-MM-DD-<slug-feedback>.md`

Slug curto descritivo (ex `nfe-erro-ie-vazia`, `osfechar-4tabs-fotos`).

### 4. Atualiza persona.fricoes (append-only)

No YAML da persona (`memory/clientes/<cliente>/personas/<slug>.yml`), adiciona em `fricoes:`:

```yaml
fricoes:
  - <fricção antiga>
  - <fricção antiga>
  # Adicionado feedback YYYY-MM-DD:
  - '<nova fricção sintetizada do feedback>'
```

E em `citacoes:`:

```yaml
citacoes:
  - data: '2026-05-27'
    contexto: <canal — ex WhatsApp pós uso NFE>
    texto: '<literal entre aspas>'
```

### 5. Atualiza charter da tela (se identificada)

No `<Tela>.charter.md` ao lado do `.tsx`:

```yaml
fricoes_conhecidas:
  daniela: <fricção existente>
  kamila: '<NOVA fricção sintetizada>'  # adicionada
```

### 6. Cria MCP task se severity ≥ 3

```
tasks-create
  title: "Resolver fricção <persona> em <tela>"
  description: "<job_por_tras>. Workaround atual: <custo>. Cliente: <slug>. Feedback raw: <link>"
  module: <modulo>
  priority: high (severity=3) | critical (severity=4)
  owner: wagner OR null
  refs:
    - persona: <slug>
    - feedback_file: <path>
```

Retorna ID da task pra preencher campo `task_mcp_id` do YAML.

### 7. Output ao Wagner

```
✅ Feedback capturado:

📋 <persona-nome> @ <cliente> — severity <N>/4
🎯 Job: <job_por_tras>
🔧 Tela: <modulo>/<tela>
🔄 Workaround atual: <custo>

💾 Salvo em: memory/clientes/<cliente>/feedback/<arquivo>.md
👤 Persona atualizada: fricoes + citacoes
🎨 Charter atualizado: <tela>.charter.md fricoes_conhecidas
🎟️ MCP task: <ID> (se severity ≥ 3)

⚡ Pattern detectado: <X clientes diferentes reportaram fricção similar>
   (sugere priorizar acima de outros backlog items)
```

## Anti-patterns

❌ Capturar feedback hipotético ("acho que Daniela ia reclamar") — só real
❌ Pular literal — sempre gravar texto cru entre aspas
❌ Severity arbitrária — usar 4-pt NN/g objetiva
❌ Fechar feedback sem cliente confirmar (campo `cliente_confirmou`)
❌ Skip persona update — feedback é input pra evoluir persona

## Princípios

- **Append-only** — feedback nunca editado/deletado (auditoria + aprendizado)
- **Literal preservado** — texto cru cliente vira ativo (não parafraseia)
- **Linka tudo** — feedback ↔ persona ↔ charter ↔ MCP task (rastreio bidirecional)
- **ADR 0105** — só cliente real paga + reportou (não inventa)
- **LGPD** — PII Tier 0 (texto cru pode conter info sensível)
- **Pattern detection** — sempre checar se outros clientes reportaram similar (priorização)

## Relacionadas

- `cliente-discovery` — quando persona não existe ainda (precede esta skill)
- `feedback-dashboard` — agregado/relatório/RICE ranking
- `design-deep-analysis` — usa fricoes_conhecidas no carregamento
- `personas-resolve` — Tier A que carrega persona em Edit/Write
