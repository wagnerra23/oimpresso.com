# TEAM.md — Equipe oimpresso e atribuição de tasks

> **Pra quem é:** todo agente IA (Claude/Cursor/outro) que vai sugerir/atribuir tarefas E todo humano do time que vai pegar uma task de [`TASKS.md`](TASKS.md) ou [`CURRENT.md`](CURRENT.md).
>
> **Regra base:** toda task tem dono **antes** de virar ativa. Sem dono = fica em on-deck. Donos só puxam tasks compatíveis com seu perfil (matriz §3).

---

## 1. Perfis (5 pessoas)

### Wagner [W] — Líder / Administrador
- **Responsabilidade primária:** decisões estratégicas (roadmap, ADRs, posicionamento), comercial, contato com cliente focal (Larissa do ROTA LIVRE)
- **Pode mexer em:** qualquer arquivo do repo, mas idealmente delega execução
- **Não deve fazer:** tasks de execução pura quando equipe consegue (gargalo do líder)
- **WIP máximo:** **2 tasks ativas** (1 estratégica + 1 técnica). Acima disso vira gargalo do projeto inteiro
- **Hora ativa:** alta agilidade — pode fechar tasks técnicas em <1 dia
- **Decisão final em:** ADRs, política de eval, posicionamento, cobrança, deploy produção

### Maíra [M] — Suporte + Desenvolvimento
- **Responsabilidade primária:** suporte direto a clientes ativos (ROTA LIVRE / Larissa em primeira linha) + dev de complexidade média
- **Pode mexer em:** Cms, Financeiro (módulo, tela, view), UI Inertia, suporte triage
- **Não deve fazer:** decisões de arquitetura, ADRs, sprints de Copiloto LGPD-críticos
- **WIP máximo:** **2 tasks ativas** (1 suporte + 1 dev típico)
- **Hora ativa:** média — fecha task de complexidade média em 1-3 dias
- **Decisão final em:** suporte tier 1, refactor de UI dentro do padrão Chat Cockpit (ADR 0039)

### Felipe [F] — Desenvolvedor + Suporte (backup)
- **Responsabilidade primária:** dev de complexidade alta (Copiloto sprints 7-9, infra, integrações), backup de suporte
- **Pode mexer em:** qualquer módulo técnico, prefere Copiloto/Infra/Integrations
- **Não deve fazer:** decisão final de ADR (propõe e Wagner aprova), suporte cliente direto exceto urgência
- **WIP máximo:** **2 tasks ativas** (1 sprint + 1 paralelo)
- **Hora ativa:** alta — pode fechar tasks complexas em 2-5 dias
- **Decisão final em:** implementação técnica dentro de ADR já aprovado, code review de Maíra/Luiz

### Luiz [L] — Suporte iniciante + Dev com IA pair
- **Responsabilidade primária:** triagem de suporte tier 1 + tasks dev **SIMPLES emparelhado com Claude/IA**
- **Pode mexer em:** frontend Inertia (Pages novas baseadas em padrão existente), copy/i18n, refactors guiados, blade views legadas, tests Pest simples
- **NÃO deve fazer (zona vermelha):**
  - Tasks com risco LGPD (PII redactor, golden set com dados reais)
  - Eval de IA (judge prompts, métricas)
  - Payment / cobrança (gateway, boleto CNAB)
  - Deploy SSH produção
  - Migrations destrutivas
  - ADRs novos
- **WIP máximo:** **1 task ativa** (com Claude/Cursor pareado)
- **Hora ativa:** baixa-média — fecha task simples em 2-4 dias com pair
- **Plano de evolução:** após 3 cycles bem (sem hotfix em prod por task dele), promove WIP=2 e libera tasks de complexidade média
- **Sempre revisado por:** Felipe ou Wagner antes de PR mergear

### Eliana [E] — Financeiro + Dev com IA (Esposa Wagner)
- **Responsabilidade primária:** faturamento e cobrança da empresa oimpresso, ops financeiro, validação de cliente WR2 (PontoWr2 — Eliana é cliente, não confundir com Eliana-time)
- **Aviso semântico:** o time tem **2 Elianas distintas** — **Eliana[E]** é a esposa do Wagner (time interno) e **Eliana-WR2** é a cliente (externa). Em commits/notas usar `Eliana[E]` interno e `Eliana(WR2)` externa
- **Pode mexer em:** Modulo Financeiro (tela, relatórios, configuração), CMS copy revisão, validação UX como usuária real
- **Não deve fazer:** Copiloto sprints técnicos, ADRs, deploy produção
- **WIP máximo:** **1 task ativa** (financeiro principal + opcional 1 task IA-pareada)
- **Hora ativa:** baixa (compartilha agenda com financeiro real do casal/empresa)
- **Diferencial:** **única que vivencia o produto como usuária final** — feedback dela vale ouro pra UX

---

## 2. Capacidade do time por cycle (10 dias úteis = 2 semanas)

| Pessoa | WIP | Tasks/cycle realista | Carga horária assumida |
|---|---|---|---|
| Wagner [W] | 2 | 4-6 (2 estratégicas + 4 técnicas curtas) | 4-6h/dia |
| Felipe [F] | 2 | 5-8 (2 grandes + 3-6 médias) | 6-8h/dia |
| Maíra [M] | 2 | 4-6 (suporte contínuo + 2-3 dev) | 6-8h/dia |
| Luiz [L] | 1 | 2-3 (com pair Claude) | 4-6h/dia |
| Eliana [E] | 1 | 1-2 (foco financeiro + opcional IA) | 2-4h/dia |
| **TOTAL** | **8** | **16-25 tasks/cycle** | — |

**Pressuposto:** se for time funcionando bem, **20 tasks fechadas em 2 semanas é meta agressiva mas factível**. Se ficar abaixo de 12, gargalo está em capacidade individual ou WIP mal calibrado.

---

## 3. Matriz de quem-pode-fazer-o-quê

**Legenda:** ✅ owner típico · 🟢 pode pegar · 🟡 com supervisão · ❌ não pegar (risco)

| Tipo de task | W | M | F | L | E |
|---|---|---|---|---|---|
| **Decisão / ADR** | ✅ | 🟡 | 🟢 (propõe) | ❌ | ❌ |
| **Copiloto sprints 7-9 (LGPD, eval, judge)** | 🟢 | 🟡 (acompanha) | ✅ | ❌ | ❌ |
| **Copiloto features comum (drivers, jobs)** | 🟢 | 🟡 | ✅ | 🟡 (pair) | ❌ |
| **PII redactor BR (LGPD)** | 🟢 | ❌ | ✅ | ❌ | ❌ |
| **Frontend Inertia (Page nova)** | 🟢 | ✅ | ✅ | 🟢 (pair) | 🟡 (UX feedback) |
| **Frontend Inertia (refactor existente)** | 🟢 | ✅ | 🟢 | ✅ (pair) | 🟡 |
| **Blade views legacy (UltimatePOS)** | 🟢 | ✅ | 🟢 | ✅ (pair) | ❌ |
| **CMS copy / blog / landing** | 🟢 | ✅ | 🟢 | ✅ (pair) | ✅ |
| **Modulo Financeiro (relatório, tela)** | 🟢 | ✅ | 🟢 | 🟡 (pair) | ✅ |
| **Modulo Financeiro (boleto CNAB, gateway)** | 🟢 | 🟡 | ✅ | ❌ | 🟡 |
| **PontoWr2 Tier A** | 🟢 | 🟢 | ✅ | 🟡 | ❌ |
| **MemCofre (UI evidência)** | 🟢 | ✅ | 🟢 | 🟢 (pair) | ❌ |
| **Suporte tier 1 (triage cliente)** | 🟢 | ✅ | ✅ (backup) | ✅ | ❌ |
| **Suporte tier 2 (incident, hotfix)** | ✅ | 🟢 | ✅ | ❌ | ❌ |
| **Validação UX (cliente final)** | 🟢 | ✅ | 🟡 | 🟡 | ✅ (chave) |
| **Cleanup workflows / YAML** | 🟢 | ✅ | ✅ | 🟢 (pair) | ❌ |
| **Deploy SSH Hostinger** | ✅ | 🟡 (supervisão) | ✅ | ❌ | ❌ |
| **Migration destrutiva (DROP, ALTER prod)** | ✅ | 🟡 | ✅ | ❌ | ❌ |
| **Eval / RAGAS / golden set** | 🟢 | 🟡 | ✅ | ❌ | ❌ |
| **Memory consolidation (skill)** | ✅ (aprova) | 🟡 | 🟢 | 🟡 (pair) | ❌ |
| **Pricing / cobrança / Stripe** | ✅ | ❌ | 🟡 | ❌ | ✅ |
| **Code review PR técnico** | 🟢 | 🟢 | ✅ | ❌ | 🟡 |

**Regras duras (não-negociáveis):**

1. **Luiz NÃO mergeia PR sozinho.** Sempre Felipe ou Wagner aprovam.
2. **Eliana[E] NÃO mexe em Copiloto sprints LGPD.** Risco regulatório.
3. **Maíra NÃO faz deploy produção sozinha.** Sempre supervisão Wagner ou Felipe.
4. **Wagner deve evitar virar bottleneck** — delegar code review pra Felipe quando puder.
5. **PIIs reais (CPF/CNPJ de clientes) NUNCA aparecem em PR ou commit.** Logs de teste com `[REDACTED]` mesmo em dev.

---

## 4. Convenção de identificação em commits / PRs / TASKS.md

Use **iniciais entre colchetes** sempre que mencionar dono:

```
[W]   Wagner
[M]   Maíra
[F]   Felipe
[L]   Luiz
[E]   Eliana (esposa, time interno)
[W+F] Wagner pareado com Felipe
[L+C] Luiz pareado com Claude
[F+C] Felipe pareado com Claude
[E+C] Eliana pareado com Claude (modo IA-assistido)
```

**Em commits:**
```
feat(copiloto): PII redactor BR regex CPF/CNPJ [F]
fix(financeiro): relatorio DRE coluna ordenada [M]
chore(memcofre): copy do botão "Anexar evidência" [E+C]
```

**Em ADR final / decisão registrada:**
- Autor declarado: sempre nome completo + papel (`Wagner Líder`, `Felipe Dev`)
- Aprovador: Wagner (default) — exceto se ADR delegada (raro)

---

## 5. Onboarding pra nova task (checklist mental)

Quando alguém vai pegar uma task de `CURRENT.md` ou `TASKS.md`:

1. Olhe a coluna "Pode pegar?" da matriz §3 — sua inicial está em ✅ ou 🟢?
2. Olhe seu WIP atual — está abaixo do máximo (§1)?
3. **Bloqueio?** Se task depende de algo de outro dono, marque ⛔ e mova pra On-deck até desbloquear
4. **Compreensão?** Se a task tem palavra que você não entende (ex.: "faithfulness", "shadow deployment"), pergunta antes de começar — NUNCA googla por 30 min sem checar com Wagner/Felipe
5. **Pair com Claude/Cursor?** Default sim pra Luiz e Eliana. Felipe/Maíra/Wagner usam quando quiser acelerar
6. **Definition of Done explícito?** Cada task em `TASKS.md` deveria ter DoD em 1 frase. Se não tem, pergunta antes de começar

---

## 6. Anti-padrões (não fazer no time)

- ❌ **Pegar 5 tasks de uma vez "pra fazer aos poucos"** — viola WIP, contexto switching come 20-40% do tempo (research)
- ❌ **"Vou só dar uma olhada nessa também"** sem mover do On-deck pro Active
- ❌ **Mergear PR sem code review por estar com pressa** — Wagner/Felipe fazem review same-day se for urgente
- ❌ **Pular daily-async** — atualizar status no `TASKS.md` toma 30s, não economiza nada não fazer
- ❌ **Luiz aceitar task fora da zona** verde dele "pra aprender" — aprender em PR de produção é ruim, melhor pair-program em task verde
- ❌ **Eliana[E] aceitar task técnica complexa "porque o Wagner pediu"** — defender o WIP=1 dela é responsabilidade do Wagner
- ❌ **Trabalhar em múltiplos cycles ao mesmo tempo** — só o cycle ativo, resto fica On-deck/Backlog

---

## 7. Quando o time cresce

Quando entrar 6ª pessoa, atualizar este arquivo. Padrão de novo perfil:

```
### Nome [Iniciais] — Papel principal / Papel secundário
- **Responsabilidade primária:** ...
- **Pode mexer em:** ...
- **Não deve fazer:** ...
- **WIP máximo:** N
- **Hora ativa:** baixa/média/alta
- **Decisão final em:** ...
```

E atualizar a matriz §3 + capacidade §2.

---

> **Última atualização:** 2026-04-28 (Cycle 01 — criação inicial do TEAM.md, time de 5)
> **Próxima revisão:** após Cycle 01 fechar (12-mai-2026) — ajustar WIP/matriz com base em o que funcionou
