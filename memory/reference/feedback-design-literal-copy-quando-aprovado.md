---
slug: feedback-design-literal-copy-quando-aprovado
title: Quando Wagner aprova screenshot do prototype → COPIAR literal, não fatiar
type: feedback
category: design-process
date: 2026-05-17
session: stupefied-noether-89f83d
related_adrs: [0104, 0107, 0109, 0114, 0141]
related_docs:
  - prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md
  - prototipo-ui/PROTOCOL.md
---

# Feedback canônico — design literal copy quando aprovado

> **Wagner palavras textuais (2026-05-17, sessão `stupefied-noether-89f83d`, /sells KB-9.75):**
>
> *"acho que tem que copiar vai fazer cagada se tentar fazer diferente"*
>
> *"tentei fazer diferente e não deu certo"* (referindo-se a try anterior adaptando Index.tsx peça-a-peça).

---

## Regra

Quando Wagner apresenta screenshot do prototype Cowork (geralmente `prototipo-ui/prototipos/<modulo>/`) ou aprova visual em F1.5 da [`PROTOCOL.md`](../../prototipo-ui/PROTOCOL.md), o caminho é **cópia integral em 1 PR** — não slicing por refinos, não adaptar peça-a-peça.

## Why

1. **Try anterior catalogado** — Wagner já tentou adaptar peça-a-peça uma tela MWART e perdeu coesão visual do design (caso citado em [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md): 6 meta-anti-padrões + 15 técnicos).
2. **Coesão do design é sistêmica** — KPIs + tabela + drawer + atalhos são feitos pra trabalhar juntos. Slicing em "só R1 SLA pill" deixa metade dos elementos com paleta inconsistente, espaços errados, sem affordance.
3. **Token cost overall menor** — 1 PR cópia = 1 round Wagner-approve. 4 PRs slicing = 4 rounds + retrabalho de coesão a cada slice.
4. **Wagner valoriza economia de crédito** ([proibições.md](../proibicoes.md)) — mas "economia" não é "tente menos primeiro"; é "decida escopo de uma vez e execute reto".

## How to apply

1. **Antes de aprovar screenshot**, Claude pode propor refinos sliced (R1, R2, R3...) — é exploratório saudável.
2. **DEPOIS que Wagner aprovar screenshot** (mesmo informalmente — "isso aí é o resultado esperado"), Claude COMITA com cópia integral em 1 PR. Sem propor slice de novo nessa rodada.
3. **commit-discipline ≤300 linhas vira ≤800-1200 linhas com label `design-literal-copy` justificando** — override registrado no PR body com link pro screenshot aprovado + visual-comparison.md.
4. **Backend deltas necessários** (`sla_kind`, `days_to_due`, `pipeline_step`, `seller_name`, etc) são parte da cópia — adicionar ao JSON do endpoint pra alimentar o visual, não inventar que "frontend pode computar do que já tem".
5. **Plug-points (mock → real) acontecem DURANTE a cópia visual**, não em PR separado. Faço de uma vez: estrutura JSX espelhada + binding ao endpoint real.
6. **Verificação visual final com Brave** ([computer-use](../../.claude/agents/whatsapp-doctor.md) tier `read` ou `claude-in-chrome` MCP) abrindo prototype HTML lado a lado com dev preview localhost — não "Wagner valida visualmente sozinho depois". Claude prova que bate.
7. **NÃO comitar/push sem aprovação explícita pós-implementação** ("ok, abre o PR" ou "manda"). Implementar ≠ shipar. Aprovação de screenshot autoriza implementar; aprovação de PR autoriza push.

## Quando NÃO aplicar (excerções)

- Wagner pediu refator parcial específico ("muda SÓ a coluna pagamento", "trabalho de hoje é só o cheat-sheet") → respeitar escopo. Slicing autorizado.
- Prototype tem violação **Tier 0** (cor crua sem semântica Cockpit V2, sem `business_id` global scope, PII real em mock) → adaptar pra tokens semânticos + tenant scope ANTES de copiar, sem mudar visual.
- Wagner não validou screenshot ainda → continuar em F1 (visual-comparison.md), não pular pra código.
- Visual do prototype usa tecnologia incompatível (ex: React Server Components quando projeto é Inertia) → mapear pra equivalente, registrar gap visual mínimo no charter.

## Sinal de violação (auto-detect)

Se Claude está editando `Pages/<Mod>/<Tela>.tsx` e percebe que está PULANDO elementos visíveis no screenshot aprovado ("vou deixar pipeline column pra próximo PR"), isso é violação dessa regra. Parar, retomar cópia integral.

## Relação com outros docs

- [ADR 0114](../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — Cowork loop formaliza F1.5 critique + F2 screenshot approval
- [ADR 0107](../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — visual-comparison.md como gate
- [ADR 0141](../decisions/0141-migracao-blade-react-skill.md) — migração massiva Blade→React
- [`prototipo-ui/PROTOCOL.md`](../../prototipo-ui/PROTOCOL.md) — 6 papéis + 7 fases
- [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — anti-padrões da era pré-cópia-integral
