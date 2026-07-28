---
status: proposal
title: "Guardrails de conteúdo/injeção: o mecanismo existe e é bom — o gap é de SUPERFÍCIE (Jana→cliente), não de máquina"
proposed_by: Claude — pedido [W] 2026-07-28 "como vai ser as proibições de assuntos sensíveis? (…) prove o funcionamento (…) se proteja contra ataques meu e da equipe e dos clientes"
proposed_at: 2026-07-28
relates_to:
  - 0224-hooks-block-vs-advisory-claude-4.8-aware
  - 0093-multi-tenant-isolation-tier-0
  - 0307-onda-0-rede-seguranca-enforcement
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
---

# PROPOSAL — Guardrails: estender a superfície, não construir máquina nova

> **Nada aqui vira código sem ratificação [W].** E o conteúdo das proibições (o que a Jana recusa) é **decisão de produto do [W]** — a mesma classe dos Non-Goals de charter, que o agente é proibido de inferir. Esta proposta desenha o **mecanismo** e mede; a **lista** é dele.

## ⚠️ Errata de origem — esta proposta quase nasceu errada

A 1ª versão do meu diagnóstico afirmava: *"não existe defesa contra injeção de instrução; proponho construir um corpus adversarial como primeiro brick"*.

**Falso.** [`.claude/governance-eval/prompt-injection-corpus.mjs`](../../../.claude/governance-eval/prompt-injection-corpus.mjs) **já existe**, está **ligado** (workflow próprio + registrado no `gates-registry.json`) e o desenho dele é **melhor** que o meu esboço — ele declara o limite da camada comportamental em vez de fingi-lo.

O erro veio de `grep` escopado a `Modules/Jana/`. O que salvou foi pagar as **duas pernas da claim negativa** que a lápide §5 de **2026-07-28** (escrita hoje, algumas horas antes) exige: varredura no repo inteiro **e** consulta ao dono do inventário. A varredura ampla achou na 1ª linha. Fica registrado porque é o recibo de que a regra funciona quando aplicada — e de que eu reincido nela quando não aplico.

## Contexto (medido em `origin/main`, 2026-07-28)

### O que existe e funciona

| Peça | Estado |
|---|---|
| [`prompt-injection-corpus.mjs`](../../../.claude/governance-eval/prompt-injection-corpus.mjs) | **10 cenários**, 3 camadas honestas: **A** determinística (6/6 alimentam a ação real ao hook real e aferem bloqueio — é ratchet), **B** gap declarado (4 caminhos UNGUARDED que passam hoje e são reportados), **C** comportamental **explicitamente não rodada** ali, citando a ADR 0314 (*"fingir isso no .mjs seria o teatro de suite que mente"*) |
| `PiiRedactor` + `pii-redactor.mjs` (hook) | redação de PII nas duas pontas |
| `jana:health-check` | `pii_leak_in_assistant_responses` · `multi_tenant_isolation` — SQL diário |
| `block-figma-without-optin.mjs` | único vetor de atrator gateado por máquina antes do corpus |

**A superfície que o corpus modela é a certa e os vetores são reais:** `db-row`, `mensagem WhatsApp`, `mcp-doc`, `webfetch`, `firebird-import`. Ou seja, **entrada de cliente já é vetor modelado**.

### O gap medido — é de SUPERFÍCIE, não de mecanismo

Os 10 cenários têm o **agente** como vítima e **destruição de repo/infra** como dano: `rm -rf`, `DROP TABLE`, force-push, `migrate:fresh`, `DELETE` sem WHERE, commit de PII, desabilitar branch-protection, exfil via `curl`, merge de PR arbitrário, `node -e`.

**Não há cenário cujo dano seja divulgação pela Jana ao cliente** — vazar dado de outro tenant, revelar PII na resposta, ou responder o que não deve. Classe de dano diferente (**disclosure/conteúdo**, não ação destrutiva), superfície diferente (**Jana→cliente**, não tool-result→agente).

E o gatilho confirma o escopo por desenho: o workflow roda em PR **path-filtered** a `.claude/hooks/**` + a própria corpus + `settings.json` — um PR que toca `Modules/Jana/**` **não o exercita**. Coerente para um backstop de hooks; e é exatamente por isso que a superfície Jana não está coberta.

## A restrição que decide o desenho (já provada aqui, não teoria)

"Assunto sensível" é predicado **semântico**. O §5 tem **quatro** lápides de guard sintático que reprovava o legítimo — allowlist de pasta (2026-06-30), guard `@scope` (07-09), gate de vocabulário (**130 FP em árvore limpa**, 07-16), lint `toHaveKey` (**100% FP**, 07-26) — e a [ADR 0224](../0224-hooks-block-vs-advisory-claude-4.8-aware.md) crava: **semântico → advisory**.

Logo: **proibição de assunto não pode ser gate bloqueante por regex.** Quem tentar reconstrói um erro pago quatro vezes.

O que decide a camada é **decidibilidade**, não gravidade:

| Camada | Predicado | Mecanismo | Exemplo |
|---|---|---|---|
| **1** | decidível | **máquina que bloqueia** | `business_id` veio da sessão e não do texto · PII na saída · credencial no diff |
| **2** | semântico | **advisory + alarme** | tom, assunto, intenção — mede e reporta, humano decide |
| **3** | comportamento do modelo | **corpus adversarial + controle negativo** | ataque conhecido → recusa; pedido legítimo → resposta normal |

## Decisão proposta

**Estender o `prompt-injection-corpus` com a superfície Jana→cliente** — não abrir corpus paralelo (§5: *"estenda o dono do tema, nunca abra paralelo"*; e catraca redundante com régua consolidada já é lápide).

Concretamente, um bloco novo de cenários cujo **dano é disclosure**, com a mesma disciplina de 3 camadas do arquivo:

- **Camada A (determinística, vira ratchet):** o escopo de tenant da resposta vem da **sessão**, nunca de texto do usuário. Alimentar a entrada real ao caminho real e aferir que o `business_id` não é influenciável. Isso é binário → morde.
- **Camada B (gap declarado):** caminhos onde hoje não há guard — reportados como UNGUARDED, sem falhar, exatamente como os 4 atuais.
- **Camada C (comportamental):** recusa de assunto proibido. **Não fingir no `.mjs`** — exige o modelo no loop, métrica advisory. Mesmo tratamento que o autor original deu, pela mesma razão.

## Contrato de prova — obrigatório para qualquer cenário novo

Guardrail sem mordida provada é o `foundation-ratchet` (0 falhas em 300+ runs) ou o `drift-sentinel` tautológico (`faithfulness(q, gt, gt)`=1.0 por construção): verde que **não pode** ficar vermelho.

1. **fixture que morde** — ataque real → bloqueia, com recibo
2. **controle negativo** — uso legítimo → passa (é o que mata o guard de 130 FP)
3. **FP medido ANTES de armar**, no corpus real — não em fixture inventada
4. nasce **advisory**; promoção a required é flip [W] com mordida provada ([ADR 0336](../0336-gates-design-promocao-por-mordida-provada-emenda-0314.md) DR-2)

## Modelo de ameaça — os três atores, honestamente

**Cliente.** Vetor real = **injeção via dado** (texto digitado que vira instrução). Defesa decidível: separar dado de instrução na montagem do prompt, e o escopo de tenant **sempre** da sessão. Camada 1.

**Equipe.** Vetor = **token MCP com escopo largo** / acesso além do papel. A base existe (permissions Spatie, `scope_required` por doc). O que falta é **auditoria de uso**, não proibição nova.

**[W].** Aqui a resposta honesta é: **não dá para proteger o sistema de você, e tentar seria teatro.** Você é Tier 0 superadmin e tem os escape valves (`OIMPRESSO_MEMORY_OVERRIDE`, `test-local-override`, `--admin`) — e **deve** ter. O modelo defensável não é impedir; é **nunca deixar mudar em silêncio**: append-only no canon, ADR para revogar Tier 0, flip de proteção com rastro, override que loga.

Isso não é aspiracional — foi observado funcionando em **2026-07-28**: o `block-destructive` barrou um force-push meu; o hook append-only me obrigou a refazer um handoff em vez de editá-lo; a aprovação de módulo novo na rubrica ficou registrada como label com autor.

## O que é decisão [W], e só

1. **A lista de assuntos proibidos** — o que a Jana recusa. Política de produto; o agente é proibido de inferir.
2. **Ratificar esta proposta** (ou recusá-la).
3. **Promover qualquer cenário novo a required** — flip, com mordida provada.
4. **Prioridade dos 4 UNGUARDED da camada B** — dívida declarada, não fechada.

## O que esta proposta NÃO propõe — de propósito

- ❌ **Corpus novo / paralelo** ao `prompt-injection-corpus` — seria a lápide "duplica régua consolidada".
- ❌ **Regex de assunto sensível como gate bloqueante** — semântico é advisory ([ADR 0224](../0224-hooks-block-vs-advisory-claude-4.8-aware.md)) e o §5 tem 4 cadáveres dessa família.
- ❌ **Denylist de palavras** — incompleta por construção; mesma doença do allowlist-de-pasta.
- ❌ **Fingir a camada C no `.mjs`** — o autor original recusou pelo motivo certo; manter.
- ❌ **Gate que exija "política presente"** — presence-gate (LC-11, 4 ocorrências sem gate).

## Incógnitas declaradas

- **FP da camada A na superfície Jana não foi medido** — a proposta o exige antes de armar, não o assume. Sem essa medição, o desenho é hipótese.
- **A camada C continua sem prova binária** — por natureza, não por preguiça. O honesto é métrica advisory observada, e dizer isso em vez de fabricar verde.
- **Não medi o custo** de rodar cenários Jana em CI (o corpus atual é `.mjs` puro; a superfície Jana pode exigir banco).
- **A superfície `Modules/Jana/**` não dispara o workflow hoje** — se os cenários novos morarem lá, o path-filter precisa incluí-la, senão nasce máquina que ninguém invoca (o defeito que a campanha dos órfãos de 2026-07-27 catalogou).
