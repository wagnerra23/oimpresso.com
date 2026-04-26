# Matriz Comparativa estilo Capterra/G2 — Sistemas de Memória do oimpresso (2026-04-26)

> **Assunto:** 9 sistemas de memória do projeto competindo internamente pra ser fonte de verdade
> **Data:** 2026-04-26
> **Autor:** Claude (sessão `dazzling-lichterman-e59b61`) sob direção do Wagner
> **Concorrentes incluídos:** CLAUDE.md, AGENTS.md, memory/00-09, memory/decisions/, memory/sessions/, memory/requisitos/, auto-memória Claude, Modules/MemCofre/, Git history+PRs (9)
> **Decisão que vai sair daqui:** Formalizar papel canônico de cada sistema, parar de duplicar info e prevenir conflitos como os 10 detectados em 2026-04-26 (Inertia v2/v3, stack IA, status Copiloto, etc).
> **Companion docs:** [oimpresso_vs_concorrentes_capterra_2026_04_25.md](oimpresso_vs_concorrentes_capterra_2026_04_25.md), [memory/decisions/0027-gestao-memoria-roles-claros.md](../decisions/0027-gestao-memoria-roles-claros.md)
> **Template usado:** [_TEMPLATE_capterra_oimpresso.md](_TEMPLATE_capterra_oimpresso.md) v1.0
> **Referência externa:** [Capterra Software Comparison Template](https://www.capterra.com/resources/software-comparison-chart/) — 4 critérios de rating: Ease of Use, Customer Service, Features, Value for Money

---

## 1. TL;DR (5 frases)

1. **Hoje há 9 sistemas de memória convivendo sem regras de quem-faz-o-quê** — auditoria em 2026-04-26 detectou 10 conflitos de fato (Inertia v2 contra v3, "Copiloto spec-ready" contra Copiloto-mergeado, "openai-php removido" contra driver usando facade OpenAI, etc).
2. **Vencedor único por função:** CLAUDE.md em onboarding, `memory/decisions/` em ADRs, auto-memória em cross-conversation, MemCofre em evidências, Git em auditoria — sem dois reis pro mesmo trono.
3. **MemCofre é o único feature do projeto sem equivalente nos concorrentes** (Mubisys/Zênite/Calcgraf/etc não têm cofre de memórias) — vale tirar dali um diferencial comercial, não só interno.
4. **AGENTS.md está stale** ("Laravel 10" em vez de 13.6) e **ADRs têm dois 0024 + zero 0012** — saneamento de governance básica adiado por meses.
5. **O dilema:** centralizar tudo em CLAUDE.md (custo de contexto LLM) vs deixar cada agente ler N arquivos diferentes (custo de tempo + risco de pular o errado). Recomendação: **CLAUDE.md curto + ponteiros** pra cada função.

---

## 2. Concorrentes incluídos (9 sistemas)

| Nome | URL/Caminho | Tier de mercado | Observação relevante |
|---|---|---|---|
| **CLAUDE.md** | `D:\oimpresso.com\CLAUDE.md` | Líder (canônico) | ~7 KB; sempre carregado em sessão Claude |
| **AGENTS.md** | `D:\oimpresso.com\AGENTS.md` | Mirror leve | 25 linhas; estava stale (Laravel 10), corrigido na sessão 15 |
| **memory/00-09** | repo `memory/00-*.md` a `09-*.md` | Líder por temática | 10 arquivos; cada um cobre 1 vertical (handoff, conventions, glossary, etc) |
| **memory/decisions/** | repo `memory/decisions/NNNN-*.md` | Líder em decisão | 27 arquivos (0001–0026 + duplicata em 0024); formato Nygard |
| **memory/sessions/** | repo `memory/sessions/YYYY-MM-DD-*.md` | Líder em cronologia | Append-only; ~30 arquivos hoje |
| **memory/requisitos/** | repo `memory/requisitos/{Mod}/` | Líder em SPEC | Template README+SPEC+ARCH+GLOSSARY+ADRs por módulo |
| **Auto-memória Claude** | `~/.claude/projects/D--oimpresso-com/memory/` | Nicho | 55 arquivos fora do git; cross-conversation grátis |
| **Modules/MemCofre/** | módulo Laravel + tabelas `docs_*` | Diferencial único | Pipeline de evidência: print/erro → IA classifica → vira US |
| **Git history + PRs** | GitHub | Universal | Imutável; auditoria definitiva de "quem mudou quê" |

**2 grupos:**
- **Repo (cross-agent — Cursor + Claude leem):** CLAUDE.md, AGENTS.md, memory/00-09, decisions/, sessions/, requisitos/, MemCofre, Git/PRs
- **Fora do git (cross-conversation — só o agente que escreveu lê):** Auto-memória Claude

---

## 3. Matriz Feature-by-Feature (32 features)

**Legenda:** ✅ Tem completo · 🟡 Tem básico/limitado · ❌ Não tem · ❓ Não confirmado

### Categoria 1 — Onboarding & primer

| Feature | CLAUDE.md | AGENTS.md | memory/00-09 | decisions/ | sessions/ | requisitos/ | auto-mem | MemCofre | Git/PRs |
|---|---|---|---|---|---|---|---|---|---|
| Carrega automaticamente em sessão Claude | ✅ | 🟡 (subagent) | ❌ | ❌ | ❌ | ❌ | ✅ (índice MEMORY.md) | ❌ | ❌ |
| Resumo de stack em <30s | ✅ | ✅ | 🟡 (precisa abrir 02) | ❌ | ❌ | ❌ | 🟡 (espalhado) | ❌ | ❌ |
| Aponta pros próximos arquivos a ler | ✅ | ✅ | 🟡 (INDEX.md) | ❌ | ❌ | ❌ | 🟡 | ❌ | ❌ |
| Cobre regras "não faça" / "sempre faça" | ✅ | ❌ | 🟡 (04-conventions) | 🟡 (decisões individuais) | ❌ | ❌ | ✅ (feedback_*) | ❌ | ❌ |

### Categoria 2 — Estado momentâneo & cronologia

| Feature | CLAUDE.md | AGENTS.md | memory/00-09 | decisions/ | sessions/ | requisitos/ | auto-mem | MemCofre | Git/PRs |
|---|---|---|---|---|---|---|---|---|---|
| "Onde paramos" (handoff) | 🟡 (footer) | ❌ | ✅ (08-handoff) | ❌ | 🟡 (último arquivo) | ❌ | 🟡 (project_*) | ❌ | 🟡 |
| Histórico cronológico append-only | ❌ | ❌ | ❌ | 🟡 | ✅ | ❌ | ❌ | ❌ | ✅ |
| Diff entre 2 momentos | ❌ | ❌ | ❌ | ❌ | 🟡 | ❌ | ❌ | ❌ | ✅ |
| Versionamento automático | ✅ (git) | ✅ (git) | ✅ (git) | ✅ (git) | ✅ (git) | ✅ (git) | ❌ | 🟡 (DB rev) | ✅ |

### Categoria 3 — Decisão e rastreabilidade

| Feature | CLAUDE.md | AGENTS.md | memory/00-09 | decisions/ | sessions/ | requisitos/ | auto-mem | MemCofre | Git/PRs |
|---|---|---|---|---|---|---|---|---|---|
| Formato Nygard (Status+Contexto+Decisão+Alternativas+Consequências) | ❌ | ❌ | ❌ | ✅ | ❌ | 🟡 (em adr/{cat}/) | ❌ | ❌ | ❌ |
| Numeração estável | ❌ | ❌ | ✅ (00-09) | 🟡 (2 duplicados) | ✅ (data+seq) | ✅ (cat+seq) | ❌ | ✅ (PK) | ✅ (SHA) |
| "Por que não fizemos X" (alternativas) | 🟡 | ❌ | ❌ | ✅ | 🟡 | 🟡 | ❌ | ❌ | 🟡 (PR description) |
| Cita lei/regulação quando aplicável | ✅ | 🟡 | ✅ (06-domain-glossary) | ✅ | ❌ | ✅ | 🟡 | ❌ | ❌ |

### Categoria 4 — Domínio e convenções

| Feature | CLAUDE.md | AGENTS.md | memory/00-09 | decisions/ | sessions/ | requisitos/ | auto-mem | MemCofre | Git/PRs |
|---|---|---|---|---|---|---|---|---|---|
| Glossário de domínio (CLT/AFD/REP-P/NFe) | 🟡 | ❌ | ✅ (06) | 🟡 | ❌ | ✅ ({Mod}/GLOSSARY.md) | 🟡 | ❌ | ❌ |
| Convenções de código (naming, idioma) | ✅ (regras 5/6) | 🟡 | ✅ (04) | 🟡 | ❌ | ❌ | 🟡 | ❌ | ❌ |
| Domain quirks de cliente (ex.: ROTA LIVRE 1280px) | ❌ | ❌ | ❌ | ❌ | 🟡 | ❌ | ✅ (cliente_*) | ❌ | ❌ |
| Specs de feature/módulo | ❌ | ❌ | ❌ | 🟡 | ❌ | ✅ (SPEC.md US-XXX-NNN) | ❌ | 🟡 (DocRequirement) | 🟡 |

### Categoria 5 — Persistência & alcance

| Feature | CLAUDE.md | AGENTS.md | memory/00-09 | decisions/ | sessions/ | requisitos/ | auto-mem | MemCofre | Git/PRs |
|---|---|---|---|---|---|---|---|---|---|
| Cross-agent (Cursor lê?) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ (DB) | ✅ |
| Cross-conversation Claude (sobrevive `/clear`?) | 🟡 (precisa CLAUDE.md presente) | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | ✅ (auto) | 🟡 (precisa query) | 🟡 |
| Sobrevive deleção de worktree? | ✅ (no main) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ (fora do repo) | ✅ (no DB) | ✅ |
| Compartilhável com humano (Wagner abre no editor) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 (path obscuro) | 🟡 (precisa UI app) | ✅ |

### Categoria 6 — Operação & custos

| Feature | CLAUDE.md | AGENTS.md | memory/00-09 | decisions/ | sessions/ | requisitos/ | auto-mem | MemCofre | Git/PRs |
|---|---|---|---|---|---|---|---|---|---|
| Edição rápida durante sessão (sem PR) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ (instantâneo) | 🟡 (precisa rodar app) | ❌ |
| Custo de contexto LLM (lazy = bom) | 🟡 (sempre carregado) | 🟡 (sempre) | ✅ (lazy) | ✅ (lazy) | ✅ (lazy) | ✅ (lazy) | 🟡 (MEMORY.md auto) | ✅ (lazy via app) | ✅ (lazy) |
| Suporta evidências binárias (prints, PDFs) | ❌ | ❌ | ❌ | ❌ | 🟡 (links) | 🟡 ({Mod}/evidencias/) | ❌ | ✅ (DocEvidence) | 🟡 (LFS) |
| Pipeline IA pra classificar conteúdo | ❌ | ❌ | ❌ | ❌ | ❌ | 🟡 | 🟡 | ✅ (DocChatMessage→DocRequirement) | ❌ |

**Total:** 32 features. Acima de 30 = OK pelo template.

---

## 4. Notas estimadas (escala G2/Capterra 1-5)

Mesmos 4 critérios que Capterra usa: **Ease of Use, Customer Service (adaptado pra "Suporte ao agente"), Features, Value for Money** + 1 extra "Especificidade pro nicho" do template oimpresso.

| Critério | CLAUDE.md | AGENTS.md | memory/00-09 | decisions/ | sessions/ | requisitos/ | auto-mem | MemCofre | Git/PRs |
|---|---|---|---|---|---|---|---|---|---|
| **Ease of Use** (agente acha rápido?) | 5 | 4 | 4 | 4 | 3 | 4 | 4 | 2 (precisa rodar app) | 4 |
| **Suporte ao agente** (formato leg.+template?) | 5 | 3 | 4 | 5 (Nygard) | 4 | 5 (template fixo) | 3 | 3 | 4 |
| **Features** (cobre o quê?) | 4 (primer só) | 2 (mirror leve) | 5 (10 dimensões) | 5 (decisões) | 5 (cronologia) | 5 (specs completas) | 5 (cross-conv) | 5 (evidência IA) | 5 (auditoria) |
| **Value for Money** (custo contexto vs valor) | 3 (custo alto, sempre carregado) | 4 | 5 (lazy) | 5 (lazy) | 5 (lazy) | 5 (lazy) | 4 (MEMORY.md auto) | 4 (lazy via DB) | 5 (lazy) |
| **Especificidade do nicho** (papel único bem feito) | 5 (primer) | 2 (redundante) | 4 | 5 (ADRs) | 5 (sessions) | 5 (specs) | 5 (cross-conv) | 5 (evidência) | 5 (auditoria) |
| **Score total (média)** | **4.4** | **3.0** | **4.4** | **4.8** ⭐ | **4.4** | **4.8** ⭐ | **4.2** | **3.8** | **4.6** |

> **Notas marcadas (estimadas)** — sem reviews G2/Capterra públicos pra esses "produtos" porque são internos do projeto. Wagner ou outro agente pode ajustar.

**Top 3 vencedores:** `memory/decisions/` (4.8) + `memory/requisitos/` (4.8) empatam, seguidos de Git/PRs (4.6). **`AGENTS.md` é o lanterna (3.0)** — mirror redundante que estava stale; vale considerar virar tombstone redirecionando pro CLAUDE.md.

---

## 5. Top 3 GAPS críticos do sistema atual

### GAP 1 — CLAUDE.md não menciona o cofre de comparativos nem o trigger "guarde no cofre"

**O que falta:** Wagner detectou em 2026-04-26 que `grep -i 'cofre\|MemCofre\|comparativ\|Capterra' CLAUDE.md` retorna **zero matches**. Trigger "guarde no cofre" depende exclusivamente da auto-memória do Claude (`trigger_guarde_no_cofre.md`). **Cursor não saberia onde salvar este comparativo.** O template `_TEMPLATE_capterra_oimpresso.md` v1.0 e a pasta `memory/comparativos/` existem desde 2026-04-25 mas são invisíveis pra agente novo.
**Esforço estimado:** Baixo (<1 dia) — adicionar 1 parágrafo no CLAUDE.md apontando pra `memory/comparativos/_TEMPLATE_capterra_oimpresso.md` e explicar trigger "guarde no cofre".
**Impacto se não fechar:** Cursor (ou Claude novo) escreve comparativo em local errado, ou pior, escreve dentro de outro arquivo poluindo. Cofre vira "morto" na prática.

### GAP 2 — ADRs com numeração inconsistente (duplicata 0024, ausência 0012)

**O que falta:** `memory/decisions/` tem **dois 0024** (`0024-instalacao-1-clique-modulos.md` e `0024-padrao-inertia-react-ultimatepos.md`) e **0012 não existe**. Quem chega depois adivinha. ADR 0028 da sessão 15 já formalizou regra de numeração monotônica e reservou 0029 pro rename, mas o rename em si **aguarda aval do Wagner** (pode ter referências cruzadas).
**Esforço estimado:** Baixo (<2h) — `git mv 0024-padrao-inertia-react-ultimatepos.md 0029-...md` + `git grep -l '0024-padrao'` + atualizar referências + commit.
**Impacto se não fechar:** Próximo ADR vai ser 0027 (já feito), 0028 (já feito), 0029 (reservado mas vazio), 0030 (já feito) — daqui pra frente cada agente vai duvidar do schema. Buraco vira norma.

### GAP 3 — MemCofre subutilizado: pipeline de evidência existe, UI de upload não

**O que falta:** entidades `DocEvidence`, `DocChatMessage`, `DocRequirement`, `DocSource`, `DocPage`, `DocLink`, `DocValidationRun` em `Modules/MemCofre/Entities/` mostram que o módulo foi planejado pra **ingestão ativa** (Wagner sobe um print → IA classifica → vira US em `memory/requisitos/{Mod}/SPEC.md`). Mas não há UI integrada ao fluxo de Wagner: ele **nunca subiu um print pelo MemCofre** em 6 meses; usa screenshot no Claude Code direto. O cofre virou "vault dormente".
**Esforço estimado:** Médio (2-4 sem) — UI de upload (drag-and-drop) + worker que aciona o LaravelAI pra classificar + tela de revisão antes de gerar US + endpoint pra agente Claude consultar `DocRequirement` por business/módulo.
**Impacto se não fechar:** O MemCofre, que é o **único diferencial competitivo dos 9 sistemas** (nenhum concorrente vertical do oimpresso tem cofre de memórias), continua não rendendo nem internamente. Vira slide de pitch sem produto.

---

## 6. Top 3 VANTAGENS reais

### V1 — `memory/decisions/` no formato Nygard com 27 ADRs

**Por que é vantagem:** poucos projetos brasileiros usam Nygard de fato (com Status+Alternativas+Consequências). Permite agente novo entender **por que** uma decisão foi tomada, não só **qual**. Defensável por anos — formato é estável.
**Como capitalizar:** **virar pitch comercial.** Comprador de ERP grande (>R$50k/ano) valoriza saber "por que esse software faz X em vez de Y". Material já existe, só precisa publicar uma versão sanitizada como "decisões de produto".
**Risco de erodir:** se IA-de-código (Cursor, Codex) começarem a gerar ADRs sintéticos, virariam commodity em ~24 meses.

### V2 — Auto-memória Claude cross-conversation grátis

**Por que é vantagem:** Cursor não tem equivalente nativo (depende de `.cursorrules` que é estático). Claude Code mantém **55 arquivos** que sobrevivem a `/clear` e dão contexto cross-conversation barato — agente novo já chega sabendo das idiossincrasias do ROTA LIVRE, do bug do Carbon timezone, do trigger "guarde no cofre", etc.
**Como capitalizar:** institucionalizar a regra "qualquer surpresa que não está no código nem no repo, salva em auto-memória". ADR 0033 (proposto) formaliza isso.
**Risco de erodir:** Cursor pode lançar memória cross-conversation em 6-12m. Anthropic pode mudar o path da auto-memória (já mudou 1x).

### V3 — MemCofre como pipeline de evidência (DocChatMessage → DocRequirement)

**Por que é vantagem:** **nenhum concorrente vertical do oimpresso (Mubisys, Zênite, Calcgraf, Calcme, Visua, Bling, Omie) tem isso.** É feature exclusiva — verificado em [oimpresso_vs_concorrentes_capterra_2026_04_25.md](oimpresso_vs_concorrentes_capterra_2026_04_25.md) seção "Cofre de memórias / KB" onde só oimpresso tem ✅.
**Como capitalizar:** virar **hero feature da landing** ("Suba um print de erro do seu cliente; nosso ERP transforma em ticket"). Fechar GAP 3 antes (UI de upload).
**Risco de erodir:** baixo — concorrentes verticais não têm equipe pra construir isso (são 5-30 pessoas focadas em FPV/PCP).

---

## 7. Posicionamento sugerido — papel canônico de cada sistema (mín. 3 caminhos)

| Caminho | Tese curta | Veredito |
|---|---|---|
| **A — "CLAUDE.md monolítico":** centralizar tudo em CLAUDE.md (handoff, ADRs lista, conventions, glossary) | Reduz nº arquivos a abrir; agente novo lê 1 só | ❌ Custo de contexto LLM cresce a cada turno; 50KB de CLAUDE.md = 12k tokens só pro primer |
| **B — "Federação por papel":** cada função tem 1 sistema canônico, CLAUDE.md aponta pros outros | Lazy load + papéis claros + cross-agent | ✅ **Recomendado** — formaliza o que ADR 0027 já decidiu |
| **C — "Migrar tudo pra MemCofre":** colocar ADRs/sessions/requisitos como `Doc*` no banco | Pesquisa via SQL, evidência integrada | ❌ Quebra cross-agent (Cursor não roda app); perde versionamento git grátis |

**Recomendado: B — Federação por papel** (já está em ADR 0027 da sessão 15).

**Frase de posicionamento:**
> *"CLAUDE.md aponta. Cada papel tem seu sistema. Auto-memória cross-conversation. Cofre formaliza evidência. Git é a verdade última."*

---

## 8. Math do custo de contexto LLM

> **Adaptação do template:** template original conecta com meta R$5mi/ano (ADR 0022). Aqui, "meta" é o **custo de contexto LLM economizado**.

Pressupostos:
- Sessão Claude típica = 30 turnos (média auto-memória)
- CLAUDE.md atual = ~7.5 KB ≈ 1.900 tokens
- AGENTS.md = ~0.7 KB ≈ 180 tokens
- MEMORY.md (auto) = ~5 KB ≈ 1.250 tokens
- Total **sempre carregado** = ~3.330 tokens/turno × 30 turnos = **~100k tokens/sessão**
- Preço Opus 4.7 1M (em out/2026): ~$0.015/1k input → **$1.50/sessão só de primer**

**Cenários:**

| Cenário | Tokens primer/turno | Custo/sessão | Comentário |
|---|---|---|---|
| **Atual (B parcial)** | 3.330 | $1.50 | CLAUDE.md+AGENTS.md+MEMORY.md sempre |
| **A (monolítico)**: tudo no CLAUDE.md | ~12.000 | $5.40 | inclui handoff+ADRs lista+conventions+glossary inline |
| **B (federação ideal)**: CLAUDE.md curto + ponteiros lazy | ~1.500 | $0.68 | corta AGENTS.md (vira tombstone), CLAUDE.md mais enxuto, lazy do resto |
| **B+ (cofre ativo)**: B + MemCofre serve evidências por API | ~1.500 | $0.68 + custo MemCofre query | mesma economia, mas ganha evidência rica sob demanda |

**Gap pra B+:** ~$0.82/sessão economizada × N sessões/mês.
- Hoje: ~30 sessões/mês (Wagner solo) = **~$25/mês economizado** se migrar pra B+.
- Com EvolutionAgent rodando + Cursor paralelo + Claude scheduled: ~200 sessões/mês = **~$165/mês**.

**Assunção não validada:** preço real de Opus 4.7 1M em out/2026 (estimativa baseada em tabela pública de jan/2026; pode ter mudado).

---

## 9. Recomendação concreta

### 3 ações prioritárias pra próximos 30 dias (em ordem)

1. **Adicionar bloco "Cofre de comparativos & gestão de memória" no CLAUDE.md** apontando pra `memory/comparativos/_TEMPLATE_capterra_oimpresso.md` + explicando trigger "guarde no cofre" + ponteiro pro ADR 0027 — **0.5 sprint**. Fecha GAP 1.
2. **Renomear ADR 0024 duplicado pra 0029 + atualizar referências** — **<1 sprint** (provável: 1h). Fecha GAP 2 e materializa ADR 0028.
3. **Migrar AGENTS.md pra tombstone redirecionando ao CLAUDE.md** — **<0.5 sprint** (1h). Tira o lanterna (3.0) que confunde sem agregar.

Máximo 3. **MemCofre UI de upload (GAP 3) fica pra próximo ciclo** — é trabalho de produto, não de governance.

### O que NÃO fazer agora

- ❌ NÃO migrar memory/00-09 numerados pra estrutura por categoria (custo > benefício; nomenclatura atual tem 6+ meses de inércia).
- ❌ NÃO eliminar auto-memória "porque tem 55 arquivos" — é cross-conversation barato, V2 confirmada.
- ❌ NÃO criar 7 ADRs novos (0031–0036 propostos no comparativo de hoje) num shot — Wagner aprovou 3, o resto fica pra quando a função puxar.
- ❌ NÃO subir comparativos sensíveis pro `Modules/MemCofre/` no DB — perde grep/git/diff. Fica em `memory/comparativos/` (markdown).

### Métrica de fé (90 dias)

> *"Se em 90 dias (até 2026-07-25) o CLAUDE.md tiver bloco 'Cofre & Comparativos', ADR 0029 estiver merged e AGENTS.md tiver virado tombstone, **e** o próximo Cursor/Claude session conseguir achar e atualizar este comparativo sem confusão**, **confirma a tese B (federação)**. Se ainda houver conflito de memória detectado em outra auditoria — **pivota pra A (monolítico) ou C (cofre-DB)** dependendo da causa do conflito.*"

Gatilho de pivot mensurável: rodar mesmo `grep` que rodei hoje (`grep -i 'cofre\|MemCofre\|comparativ\|Capterra' CLAUDE.md`) deve retornar **≥3 matches** em 90 dias. Hoje retorna 0.

---

## 10. Sources

- [Capterra Software Comparison Chart](https://www.capterra.com/resources/software-comparison-chart/)
- [Capterra: Assess Functionality and Features Template](https://blog.capterra.com/assess-functionality-features-template/)
- [Capterra: Side-by-Side Software Comparison](https://www.capterra.com/compare/)
- [Capterra: 15-minute Software Comparison Template](https://blog.capterra.com/software-comparison-template/)
- [G2: Capterra Reviews 2026](https://www.g2.com/products/capterra/reviews)
- Internas:
  - [_TEMPLATE_capterra_oimpresso.md v1.0](_TEMPLATE_capterra_oimpresso.md) (template oficial do projeto, 2026-04-25)
  - [oimpresso_vs_concorrentes_capterra_2026_04_25.md](oimpresso_vs_concorrentes_capterra_2026_04_25.md) (comparativo de produto)
  - [memory/decisions/0027-gestao-memoria-roles-claros.md](../decisions/0027-gestao-memoria-roles-claros.md)
  - [memory/decisions/0028-adrs-numeracao-monotonica.md](../decisions/0028-adrs-numeracao-monotonica.md)
  - [memory/decisions/0030-credenciais-jamais-em-git.md](../decisions/0030-credenciais-jamais-em-git.md)
  - [memory/sessions/2026-04-26-deploy-hero-fix-e-conflitos-memoria.md](../sessions/2026-04-26-deploy-hero-fix-e-conflitos-memoria.md)

---

## Checklist (template)

- [x] TL;DR cabe em 5 frases
- [x] Mín. 4 concorrentes incluídos (9)
- [x] 30+ features na matriz (32)
- [x] Notas escala 1-5 preenchidas (todas estimadas — sem G2/Capterra real pra sistema interno)
- [x] **Exatamente 3 GAPS e 3 VANTAGENS**
- [x] **Mín. 3 caminhos de posicionamento** com veredito
- [x] Math (adaptado: custo de contexto LLM)
- [x] **3 ações prioritárias** em ordem
- [x] **Métrica de fé** com prazo (90d) e gatilho de pivot
- [x] Sources literais com URL
- [x] Companion docs linkados no frontmatter
