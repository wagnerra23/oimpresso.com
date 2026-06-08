---
name: feedback-drawer-760-canon-style
description: Wagner preferência forte — Drawer 760 é o estilo canônico de edição/cadastro de entidades cadastrais do oimpresso. "Eu adoro esse estilo" 2026-05-22.
metadata:
  type: feedback
---

# Feedback canon — Drawer 760 é o estilo preferido do Wagner pra entidades cadastrais

**Regra:** Pattern Drawer 760 lateral ([ADR 0179](../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md) + [ADR 0185](../decisions/0185-drawer-760-canon-entidades-cadastrais.md)) é **canon definitivo** pra todas as entidades cadastrais do oimpresso. Substitui Edit.tsx + Create.tsx separados. Drawer 760px **fixo** lateral dentro do Index.tsx + N tabs + autosave on blur + redirect 302 das URLs legacy.

**Why:** Wagner aprovou explicitamente em 3 momentos progressivos da sessão 2026-05-22 `frosty-greider-83ab2f`:

1. **Decisão arquitetural inicial** ("Cliente Drawer 760 pra TUDO — escalar ADR 0179"): Wagner escolheu Cenário A entre 4 opções de AskUserQuestion. Custo declarado ~80-120h IA-pair, aceito.

2. **Aceite formal ADR 0185** ("eu gostei pode salvar"): após ler ADR draft 275 linhas + matriz 7 entidades + plano F0-F4 + métricas loop fechado. Status promovido `proposed` → `accepted`.

3. **Registro canon formal** ("registre o padrão eu adoro esse estilo. salve tudo formalize o melhor"): após auditoria estado-da-arte 76,4/100 — Wagner reafirmou preferência apesar dos gaps técnicos identificados (concorrência, performance lazy, mobile).

**How to apply:** Sub-agents Claude/Codex que pegarem qualquer wave de entidade cadastral (Produto/ServiceOrders/Vehicles/DeviceModels/Planos/TransactionPayment + futuras tipo Fornecedor/Funcionário) DEVEM:

1. **NÃO PROPOR alternativas** ao Drawer 760 quando entidade é cadastral. Wagner já decidiu — debate de paradigma está fechado.
2. **NUNCA reverter** pra Edit.tsx + Create.tsx separados em entidade cadastral. Sunset zero declarado em ADR 0179 §Q1.
3. **NUNCA propor** modal centralizado, drawer expansível, drawer responsivo per-viewport, ou drawer largura ≠ 760. Tudo Tier 0 PROIBIDO.
4. **APLICAR Wave H primeiro** (15h IA-pair pré-replicação) — gaps P0 da auditoria 76,4/100 (optimistic locking + lazy load + popstate + focus trap) antes de replicar nas 6 entidades. Senão escala 7×.
5. **COPIAR e adaptar** as 4 tabs reutilizáveis do Cliente (Identificacao/IA/Auditoria/Endereco) — 90% reuso, ~10h adaptação per entidade.
6. **MIGRAR** Edit/Create existentes pra redirect 302 → drawer; não deletar arquivos sem confirmar redirect funcionando.

**Quando questionar Wagner (raro):**
- Se entidade NÃO é cadastral conforme matriz (workflow transacional com >20 campos ou wizard multi-step) → propor FOCO V2
- Se nova plataforma cliente reportar drawer 760 não cabe (ex tablet 1024×768) → trazer dados pra revisão
- Se custo Brain B Tab IA escalar acima do baseline em N entidades → propor gate quota (review trigger já registrado na ADR 0185)
- Se gap concorrência (#1 optimistic locking) gerar perda de dados em prod biz=1 → ESCALAR P0 imediato

**Quando NÃO questionar (Tier 0):**
- "Drawer poderia ser maior?" — Não, 760 fixo (geometry Larissa biz=4 1280×1024 validada)
- "Salvar com botão é mais explícito" — Não, autosave on blur Wagner aprovou (Notion/Linear pattern)
- "Edit.tsx separado é mais simples" — Não, substituído (Wave A-G Cliente provou em ~3h elapsed)
- "Modal centralizado é mais popular" — Não, drawer lateral é canon (paridade Cowork blueprint)

**Memórias relacionadas:**
- [[drawer-760-pattern-canon]] — 1-pager executivo do pattern
- [[feedback-modulo-mexeu-registra-sempre]] — Wagner Tier 0
- [[feedback-cowork-bundle-aplicar-inteiro]] — Wagner Tier 0
- [[feedback-nunca-publicar-credenciais]] — Wagner Tier 0

**Não confundir com:**
- **FOCO mode** ([skill pageheader-canon](../../.claude/skills/pageheader-canon/SKILL.md) Fase 4-bis V1/V2/V3) — usado pra workflow transacional ou cadastro técnico simples. NÃO substitui Drawer 760 em entidade cadastral.
- **Sells/Create.tsx POS** — workflow transacional, mantém FOCO V2 (matriz elegibilidade clara em ADR 0185).
- **Show.tsx** — pode ainda existir pra read-only de workflows ou casos especiais; entidade cadastral NÃO tem Show (substituído por drawer modo edit/view unificado).
