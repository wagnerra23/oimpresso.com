---
date: "2026-06-02"
topic: "Governança [CC] × Jana × Champion + Riscos — conclusões da análise Cowork (metricas.html fica Cowork-local)"
authors: ["C", "W"]
related_adrs:
  - 0092-tabela-rename-copiloto-para-jana
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0050-metricas-obrigatorias-memoria-table
  - 0053-mcp-server-governanca-como-produto
  - 0035-stack-ai-canonica-wagner-2026-04-26
---

# Governança [CC] × Jana × Champion + Riscos — conclusões

> Origem: sessão Cowork 2026-06-02 (chat "Sync code IA comparison"). A análise viveu na view
> `rep-cc-vs-jana` do `metricas.html` — **working-doc Cowork-local, NÃO-canon** (decisão chat33 +
> `CODE_NOTES`). O dashboard **não** vai pro repo nem vira página. Desta sessão vão só: estas **conclusões**
> (este doc), o **mecanismo (e)** — que **já foi implementado e mergeado no `main` via #2131** (ledger
> `Modules/Jana/LICOES-OPERACAO.md` + graduação no `jana:health-check`) — e a **segurança (f)** (grep
> `copiloto_`, §7). Notas % são **estimadas / não-canon** (auto-avaliação Cowork) — hipótese, não placar oficial.
> Dashboard de governança DENTRO do produto = decisão Tier 0 separada, não port pixel-perfect deste working-doc.

---

## 1. O reframe (as duas não correm a mesma prova)

- **[CC] (Claude Code / governança de processo)** governa **juízo e memória**: como decisão vira
  registro imutável (ADR append-only), como erro vira regra (LIÇÕES), quando o humano entra (Tier 0).
- **Jana (IA interna do ERP, `Modules/Jana`)** governa **sistema em produção**: isolamento
  multi-tenant, custo de LLM, qualidade de RAG, LGPD, auditoria observável.

A impressão "o CC parece melhor que a IA local" é **meio certa** — mas mede eixos diferentes.

## 2. Placar (8 eixos) — estimado

- **[CC] lidera 3:** aprendizado-com-erro (erro→regra→check), registro de decisão (ADR append-only),
  tiering de risco/soberania. *Raro até no mercado — é daqui que vem a impressão de "melhor".*
- **Jana lidera 5:** enforcement **executável** (RAGAS bloqueante + `jana:health-check` diário),
  observabilidade (audit triplo + OTel), auto-medição (rubrica D1–D9, ~96/100 saturado),
  privacidade/LGPD, memória auto-regenerável (`ProfileDistiller`). *Porque ela **tem** que: roda servindo cliente.*

## 3. Polinização cruzada (a P1 de cada lado)

1. **[CC] → Jana:** dar à Jana uma **LIÇÕES própria de operação** — ledger dos **erros de comportamento**
   dela virando gate. Hoje ela só pegava alucinação de **saída** (golden/RAGAS), não os próprios erros de
   operação. → **mecanismo (e), JÁ ENTREGUE no `main` via #2131**: ledger `Modules/Jana/LICOES-OPERACAO.md`
   + check de graduação no `jana:health-check` + extensão das skills `incident-done-checklist`/`feedback-capture`.
2. **Jana → [CC]:** **graduar lição em gate** — as regras do CC esperam leitura; as da Jana bloqueiam.
   O cano já existe: os checks de design do CC rodam *dentro* do `jana:health-check`.

## 4. vs Champion (estado-da-arte 2025–26)

Pesquisa: Reflexion · Voyager · Policy-as-Prompt/CONSECA · Zep/Graphiti · Letta/MemGPT · EU AI Act/NIST/RSP · Atlan.

As duas perdem pro champion em ~6 eixos, mas o **padrão é único**:

- **Aprendizado:** já fazem *Reflexion* (LIÇÕES = log do erro→regra), mas **em prosa**. Voyager provou que
  skill em **código executável + verificável** vence prosa de longe (remover auto-verificação ≈ −73%).
  → destino de toda lição = **check**, não parágrafo. *(É o que o #2131 fez na Jana.)*
- **Enforcement:** ADR/constituição é **estática**; champion usa **guardrails contextuais em runtime que
  emitem rationale auditável** (Policy-as-Prompt).
- **Memória/lineage:** falta **bi-temporalidade** (Zep/Graphiti: "quando foi verdade" × "quando soubemos").
- **Memória auto-gerida:** Letta/MemGPT deixa o agente **editar a própria memória**; aqui re-lê/regenera.
- **Tiering de risco:** champion gradua por contexto; o Tier 0 é binário.
- **Observabilidade:** champion mantém LLM-obs sempre ligado; o collector CT 100 está **off**.

**Onde JÁ são champion:** os 8 frameworks de memória líderes **não têm lineage de governança** —
o ADR append-only + lápide é exatamente o que falta a eles. **Diferencial a defender.**

## 5. Por que o champion se destaca (a camada do porquê)

Tirou o elo mais fraco — **memória/disciplina humana** — do caminho crítico. A inteligência dele mora
no **sistema que roda**, não nos **documentos sobre o sistema**. O gap até ~9.5 **não é "mais máquina"**:
é tornar **regra/lição executável** (não prosa), **contextual com rationale**, e **ligar o que já existe**.

## 6. Riscos / o que está no escuro (✓ verificado no `main` · ⚠ inferido/working-doc)

⏰ **Relógio correndo**
1. `✓` **Drop das views legadas `copiloto_*` marcado pra 2026-06-05** (ADR 0092). Mitigado nesta sessão —
   ver §7 (deliverable f): **zero consumidor de runtime** (verificado no `main`), drop é seguro do lado do código.

🌑 **Construído e no escuro / falhando**
2. `✓` **4 gaps P0 da auditoria IA-OS (68/100, 29/mai) seguem `done:false`**: RAGAS gate em CI, drift
   sentinel, LGPD purge real, observability. *Prep* mergeado (#2073); **ENABLE parado em decisão de orçamento [W]**.
3. `⚠` **`profile_distiller_drift` falhando + Brain A sem rodar nas últimas 24h** — possível job morto;
   ninguém é avisado porque a observabilidade está desligada.
4. `✓` **Collector OTel/CT 100 desligado** — 40+ services emitem spans que caem no vazio.

🧱 **Estrutural**
5. `✓` **Meilisearch = ponto único de falha** (memória + RAG num CT 100, 🔴 no BRIEFING). HA espera $.
6. `✓` **Custo escala com cliente** (Brain A horário ≈ R$ [redacted Tier 0]/dia × N businesses) e a **Larissa (biz=4)
   ainda não está live** — esforço de design mira persona que não usa o sistema ainda.
7. `✓` **~17 ADRs represados na fila Tier 0** — [W] é gargalo; parte o git já responde sozinho.
8. `✓` **Regra anti-duplicação (`docs/design-no-dup-trilha`, L-21/L-22) aguardando merge.**
9. `✓` **Mobile é universo de token separado** (Inter / vinho `#7A0B7E` / radius 16) vs desktop
   (IBM Plex / roxo / radius 6–8). Fork consciente, **sem ADR de convergência** → 2 design systems.

🪞 **Meta-risco (o que mais importa)**
10. O sistema é **mais forte em construir do que em decidir/ligar**. O risco não é construir de menos —
    é o **estoque crescente de ferramentas de elite no escuro** + [W] como gargalo único pra ligá-las.
11. `⚠` **Notas (8.2 / 8.5 / 96–100) são auto-avaliadas** — ninguém de fora audita. "Quem verifica o
    verificador" segue aberto; risco real de inflar a própria nota.
12. `✓` **Memória cresce sem limite** (LIÇÕES ~36KB, COWORK_NOTES ~70KB, ~190 ADRs); índice temático
    defasado → recall degrada; "ler tudo no início" não escala (origem do FRESCOR-DE-TELA).

**Fio condutor:** não falta máquina — falta **ligar, esvaziar a fila e consertar o drift**.

## 7. O que desta sessão foi pro repo (e o que NÃO foi)

| # | Item | Forma no repo | Status |
|---|------|---------------|--------|
| — | `metricas.html` (dashboard 7 views) | **nenhum** — Cowork-local não-canon | ❌ não vai (decisão [W]) |
| 1 | Conclusões da análise | este `memory/sessions/*.md` | ✅ feito (este doc) |
| e | Ledger de auto-reflexão da Jana (Reflexion runtime) | `Modules/Jana/LICOES-OPERACAO.md` + check no `jana:health-check` + skills estendidas | ✅ **JÁ no `main` via #2131** (o [CL] processou a ponte) |
| f | Segurança: grep `copiloto_` antes do drop | resultado registrado abaixo | ✅ feito — **drop seguro (verificado no `main`)** |

### Deliverable (f) — grep `copiloto_` (Passo 0, código `main`, 2026-06-02)

**Verdito: SEGURO dropar as views `copiloto_*` em 2026-06-05 do ponto de vista de consumidor de código.**

- **Zero query de runtime** aponta pras views legadas — verificado tanto na linha `feat/staging-ct100`
  quanto no `main`. Todos os ~30 `DB::table('copiloto_*')` já foram migrados pra `jana_*` (confirma handoff
  2026-05-10). Verificado vivo:
  - `Services/Metricas/MetricasApurador.php` → `DB::table('jana_mensagens')` / `DB::table('jana_memoria_facts')`.
  - `Mcp/Tools/MemoriaSearchTool.php` → `DB::table('jana_memoria_facts')`.
  - `Services/ContextSnapshotService.php` → defensivo (trata ausência das tabelas).
  - `Entities/MemoriaFato.php` → tabela `jana_memoria_facts`.
  - `app/Console/Kernel.php`, `Services/Ai/OpenAiDirectDriver.php` → sem query a `copiloto_`.
- **Resíduos cosméticos (não quebram, mas vale higienizar depois):** comentários/docstrings em vários
  arquivos; **strings de proveniência** em `MetricasApurador.php` (`'db:copiloto_mensagens'`, `'db:copiloto_memoria_facts'`);
  a `description` do MCP tool `MemoriaSearchTool` ainda cita `copiloto_memoria_facts`.
- **Caveat (limites do grep estático):** não cobre (a) clientes MCP externos que consultem por nome
  antigo, (b) SQL cru no repo do **MCP server** (separado), (c) BI/relatório externo. O go/no-go final
  é um check de runtime em prod confirmando que nada bate nas views — mas **risco de consumidor in-repo = zero**.

## 8. Caveats da sessão

- Working-doc Cowork: notas estimadas, **não-canon**, nada commitado pelo Cowork.
- Mecanismo (e) **já entregue no `main` via #2131** (o [CL] processou a ponte). Este doc **não** duplica
  o ledger — aponta pra ele (`Modules/Jana/LICOES-OPERACAO.md`), respeitando a regra anti-reprocessamento.
- ADRs novas não cunham número fora da soberania [W].
