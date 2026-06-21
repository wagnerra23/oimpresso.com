---
date: "2026-06-15"
topic: "Prompts paralelos prontos pra disparar as lanes B (KL-E2 fusões/renames/lápides/portas) e C (KL-E3 BRIEFINGs destilados) das Semanas 1-2/2-4 do plano SDD — partição por módulo-alvo, gate E1 embutido, refutador G5 + tail serializado (ghost-fix --write + re-seed Meilisearch)"
authors: [W, C]
related_adrs: ["0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento", "0274-referencia-adr-por-slug-alias-map-13-colisoes", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"]
prs: []
---

# Prompts paralelos — KL-E2 (lane B) + KL-E3 (lane C) do plano SDD

> ✅ **E1 JÁ DECIDIDO por Wagner — o CANÔNICO vive FORA desta branch.** Decisões oficiais em `origin/main`
> (#2743, defaults) e fechadas 100% em `sdd/kl-identidade-decisoes` (`fea48e0a4`, 2026-06-15). Este pack
> **CONSOME** esse E1 — não o redefine (a tabela tem fonte única de verdade). A branch
> `feat/governance-ds-rollout-ledger` onde este doc nasceu é STALE — NÃO preencher E1 aqui.
>
> ⚠️ **Progresso já em curso — re-derive de `origin/main` ANTES de disparar:** o codemod de TEXTO já rodou
> (#2603 + #2693, `ghost_count 27→15`) e PARTE das **lápides** já foi aplicada. O que de fato FALTA são as
> **fusões de pasta (git mv)** — os órfãos seguem inteiros em `origin/main`. Cada prompt tem PASSO 0 que relê o E1 e PARA se divergir.
>
> 🔧 **2 correções pós-reconciliação (2026-06-15) — leia ANTES de disparar B7 e B6:**
> 1. **Cluster Estoque = ADIADO no E1 real** (Inventory · Produto · Purchase · StockAdjustment · StockTransfer):
>    fica pra onda futura; agora SÓ se cria a porta leve do Estoque. **Thread B7 está PAUSADO.**
> 2. **TaskRegistry→TeamMcp: emenda E1 APLICADA** (PR #2747, branch `sdd/kl-identidade-decisoes`). O thread B6
>    funde TaskRegistry→TeamMcp. (Enquanto #2747 não mergear no main, re-confirme o E1 no PASSO 0.)

> Origem: [plano de reestruturação SDD](2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md) §4
> (Semanas 1-2 = E1→E2→E2b; Semanas 2-4 = E3 BRIEFINGs). Cada bloco é auto-contido pra colar numa
> sessão fresca de OUTRA conta. Toda sessão começa com `brief-fetch` (skill `brief-first`).

## Mapa de threads (1 cluster = 1 thread; partição por MÓDULO-ALVO = zero colisão de escrita)

**Lane B — KL-E2 (fusões `git mv` + redirect stub · lápides · portas):**

| Thread | Alvo(s) que ESTE thread possui | Órfãos que entram | Ação |
|---|---|---|---|
| **B1** | `Jana` | Copiloto · LaravelAI(→HISTORICAL) · MemoriaAutonoma · Chat | fundir |
| **B2** | `Financeiro` | FinanceiroAvancado (vira `ROADMAP-avancado.md`) | fundir |
| **B3** | `Sells` | Orcamento | fundir |
| **B4** | `Ponto` | PontoWr2 (13 docs, inclui `adr/` + `audits/`) | fundir |
| **B5** | `Cms` + `_DesignSystem` | Site→Cms · _Showcase→_DesignSystem | fundir + porta `_DesignSystem` |
| **B6** | `Admin` + `Whatsapp` + `Mwart` + `TeamMcp` | Modules→Admin · Atendimento→Whatsapp · _processo→Mwart · TaskRegistry→TeamMcp | fundir |
| **B7** ⏸️ | `Estoque` (só porta leve) | repartição ADIADA: Inventory · Produto · Purchase · StockAdjustment · StockTransfer | **PAUSADO** — E1 real adiou o cluster; só criar a porta do Estoque agora |
| **B8** | (cemitério) BI · Grow · EvolutionAgent · Tarefas + porta `_Ideias` | — | lápides |
| **B9** | (wishes) Autopecas · Comissao · Garantia · Marketplaces · Pcp | — | portas-thin `status: wish` |

**Lane C — KL-E3 (BRIEFINGs destilados por IA, refutador G5):**

| Thread | Módulos (todos GENUÍNOS e fora dos alvos de B) | Lote |
|---|---|---|
| **C1** | ComunicacaoVisual(10) · ConsultaOs(4) · Officeimpresso(9) · PaymentGateway(4) | 1 lote de 4 |
| **C1-R** | refutador G5 do lote C1 — **sessão FRESCA, outra conta, modelo ≥ gerador** | obrigatório antes do merge |

> ⚠️ A lista C1 é o que SOBRA dos "22 órfãos" depois do E1 reclassificar a maioria como FUNDIR/MATAR/ADIADO.
> Ficam **fora de C**: `Produto`/`Estoque` (cluster ADIADO — só porta leve do Estoque, sem distilação agora) ·
> `_DesignSystem` (porta criada pelo B5) · `TaskRegistry` (emenda E1 #2747 → fusão B6→TeamMcp, fora de C).
> **RE-CONFIRME contra o E1 canônico antes de disparar** (anti-stale).

**Serializado no fim (KL-E2b) — 1 thread, DEPOIS de todos os B mergeados:**

| Thread | Faz |
|---|---|
| **TAIL** | `ghost-fix.mjs --write` (4 renames Classe A em texto) → re-seed Meilisearch → re-check `knowledge-drift` + `sdd-scorecard --ratchet` |

## Regras compartilhadas (já embutidas em cada prompt)

- **Gate E1:** PASSO 0 relê `_TRIAGEM-IDENTIDADE-2026-06.md`; só age em linhas com decisão preenchida; em branco → PARA.
- **Anti-colisão de escrita:** cada thread só toca seu(s) alvo(s) + os órfãos do seu cluster. NUNCA o alvo de outro thread. Lane C nunca toca pasta que um thread B funde/mata.
- **Tier 0 / anti-regressão** ([proibicoes](../proibicoes.md)): NUNCA criar arquivo sem checar duplicação; ESTENDER o canon, nunca abrir paralelo; ADR é append-only; respeitar `business_id`. Pré-flight: ler SPEC/BRIEFING do alvo antes de tocar (skill `preflight-modulo`).
- **PII (repo PÚBLICO):** scan diff-only obrigatório (CPF/CNPJ/telefone/e-mail/nome de cliente CRM) antes de cada PR. Hits = 0.
- **Anti-stale:** re-derivar TODO número/lista de `origin/main` no momento da execução — não confiar nos números deste doc nem do plano.
- **Commit discipline:** 1 PR = 1 intent, ≤300 linhas, conventional, PT-BR, `Refs: SDD KL-E2 <thread>` (ou `KL-E3`). Não tocar CT 100/prod; não rodar teste local.
- **Padrão redirect stub (FUNDIR):** após `git mv` dos docs únicos pro alvo, a pasta órfã fica com um `BRIEFING.md` curto apontando pro alvo (modelo: [MemCofre/BRIEFING.md](../requisitos/MemCofre/BRIEFING.md) — "Pare aqui / verdade viva → X"). Se a pasta não puder esvaziar, congele o resto como proveniência e troque só o BRIEFING.
- **Padrão lápide (MATAR):** `BRIEFING.md` "(planejado — não existe)" apontando o sucessor; sem `git mv` de código (não há).

---

## LANE B — bloco-base (cole, troque só o cabeçalho «CLUSTER»)

Este é o template das fusões simples (B1-B6). Para cada thread, troque o bloco «CLUSTER» pelos
valores da tabela. B7/B8/B9 têm bloco próprio (estrutura diferente) mais abaixo.

```
Você está numa sessão paralela das Semanas 1-2 do plano de reestruturação SDD do oimpresso (frente KL-E2).
Contexto: memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md (frente KL, §4 Semanas 1-2)
+ memory/sessions/2026-06-15-sdd-kl-e2-e3-prompts-paralelos.md (este pack, regras compartilhadas).

«CLUSTER»
  THREAD: B1
  ALVO (você possui só este): Jana
  ÓRFÃOS a fundir: Copiloto, LaravelAI, MemoriaAutonoma, Chat
  NOTA: LaravelAI entra marcado HISTORICAL (pré-história do Jana). Jana JÁ TEM BRIEFING — não sobrescreva;
        no máximo acrescente 1 linha "absorveu Copiloto/LaravelAI/MemoriaAutonoma/Chat (2026-06)".
«/CLUSTER»

PASSO 0 — GATE E1 (obrigatório, antes de qualquer escrita):
Leia memory/requisitos/_TRIAGEM-IDENTIDADE-2026-06.md. Para CADA órfão do seu cluster, ache a linha na
Tabela A (e o par na Tabela B) e confira a coluna "Decisão Wagner". Só prossiga nas linhas com decisão
preenchida e compatível com FUNDIR. Linha em branco ou divergente (ex: virou MATAR/GENUÍNO) → NÃO toque
nesse órfão e reporte no fim. Se TODAS estiverem em branco, PARE e diga "E1 não preenchido para o cluster B1".

PASSO 1 — pré-flight: leia o BRIEFING.md + SPEC.md do ALVO. Liste os docs únicos de cada pasta órfã
(o que NÃO é duplicata do que já existe no alvo).

PASSO 2 — fusão por órfão (git mv preservando história):
- `git mv` os docs únicos da pasta órfã pra dentro da pasta do ALVO. Se houver risco de colisão de nome,
  namespace sob subpasta (ex: Jana/_historico-laravelai/...). NÃO duplique conteúdo já existente no alvo.
- Deixe na pasta órfã um BRIEFING.md = REDIRECT STUB curto (modelo MemCofre/BRIEFING.md): "Pare aqui,
  esta pasta foi fundida em <ALVO> em 2026-06; verdade viva → memory/requisitos/<ALVO>/BRIEFING.md".

PASSO 3 — scan PII diff-only (CPF/CNPJ/telefone/e-mail/nome cliente). Hits=0 ou conserte antes do PR.

PASSO 4 — PR: 1 PR por cluster (ou 1 por órfão se passar de 300 linhas), conventional,
Refs: SDD KL-E2 <thread>. NÃO rode ghost-fix.mjs --write nem re-seed Meilisearch (é o TAIL serializado).
NÃO toque o alvo de outro thread. Não toque CT 100/prod.
```

### Parametrização B2-B6 (mesmo template, troque o bloco «CLUSTER»)

```
B2 · ALVO: Financeiro · ÓRFÃOS: FinanceiroAvancado
     NOTA: vira memory/requisitos/Financeiro/ROADMAP-avancado.md (é wish avançado, não estado vigente).

B3 · ALVO: Sells · ÓRFÃOS: Orcamento
     NOTA: Orcamento tem só 1 doc (charter draft órfão). quotation = Transaction type:sell status:draft.

B4 · ALVO: Ponto · ÓRFÃOS: PontoWr2
     NOTA: 13 docs incluindo adr/ e audits/ — namespace sob Ponto/_historico-pontowr2/ se colidir. Mais pesado.

B5 · ALVOS: Cms, _DesignSystem · ÓRFÃOS: Site→Cms, _Showcase→_DesignSystem
     NOTA: _DesignSystem ainda não tem BRIEFING — crie a porta leve dele aqui (62 docs; índice, não distilação).
     Site = 7 telas públicas Login/Register/Blog/Pricing → Cms.

B6 · ALVOS: Admin, Whatsapp, Mwart [, TeamMcp*] · ÓRFÃOS: Modules→Admin, Atendimento→Whatsapp, _processo→Mwart [, TaskRegistry→TeamMcp*]
     NOTA: órfãos de 1 doc fundindo em módulo existente. "Modules" é isca de ghost-name (tela core
     ModuleManagementController). Atendimento→Whatsapp (E1: NÃO renomear o módulo Whatsapp).
     *TaskRegistry→TeamMcp: emenda E1 APLICADA (PR #2747). TeamMcp JÁ TEM BRIEFING — não sobrescreva,
     acrescente "absorveu TaskRegistry (2026-06)". (Se #2747 ainda não mergeou, re-confirme o E1 no PASSO 0.)
     Compras NÃO entra aqui — Purchase faz parte do cluster Estoque ADIADO (B7 pausado).
```

---

## B7 — ⏸️ cluster Estoque ADIADO no E1 real; LIBERADO só a porta leve do Estoque

> 🛑 **O E1 canônico (`fea48e0a4`) marcou TODO o cluster Estoque como ADIADO** (Inventory · Produto · Purchase ·
> StockAdjustment · StockTransfer — P5/P6/P7). NÃO reparta nem mova nada disso agora. O ÚNICO trabalho liberado
> é criar a porta leve (BRIEFING) do Estoque. A receita de repartição fica CONGELADA abaixo até o cluster destravar.

```
TAREFA LIBERADA (única): criar a porta leve memory/requisitos/Estoque/BRIEFING.md a partir do DOC-RAIZ
canônico (2026-06-04). NÃO repartir Inventory, NÃO mover Produto/Purchase/Stock* — ADIADO no E1.
PASSO 0 — GATE E1: confirme Estoque = "ok — só criar porta" e Inventory/Produto/Purchase/Stock* = ADIADO.
PASSO 1 — pré-flight no SPEC/DOC-RAIZ do Estoque. PASSO 2 — escreva a porta (1 página: o que é o domínio
Estoque hoje, status cross-cutting, link pro SPEC). PASSO 3 — scan PII. 1 PR, Refs: SDD KL-E2 B7-porta.

[CONGELADO — só executar quando Wagner destravar o cluster Estoque]
Repartir Inventory (29 docs): produto→Produto · purchase→Compras · stock*→Estoque; StockAdjustment/
StockTransfer→Estoque; Purchase→Compras; portas de Produto; redirect stubs. ProductCatalogue INTOCADO.
```

---

## B8 — Lápides (MATAR) + porta de _Ideias

```
Você está numa sessão paralela das Semanas 1-2 do plano SDD do oimpresso (frente KL-E2, cemitério).
Contexto: 2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md + este pack.

PASSO 0 — GATE E1: confirme em _TRIAGEM-IDENTIDADE-2026-06.md que BI, Grow, EvolutionAgent e Tarefas
estão marcados MATAR (todos "ok" no E1 canônico). Em branco/divergente → não toque, reporte.
⚠️ PARTE das lápides já foi aplicada (#2693, ghost_count 27→15). RE-DERIVE quais das 4 AINDA faltam em
origin/main antes de escrever — não duplique lápide já existente (regressão Tier 0).

PARA CADA pasta abaixo, troque o BRIEFING.md por uma LÁPIDE curta "(planejado — não existe / morto)"
apontando o sucessor; NÃO apague os docs (viram proveniência congelada):
- BI → lápide "nunca construído; só comparativo Capterra". Arquive o comparativo em memory/requisitos/_Ideias/.
- Grow → idem BI (comparativo → _Ideias/).
- EvolutionAgent → lápide "(planejado — não existe; absorvido por ADS)". Aponta Modules/ADS.
- Tarefas → lápide: tarefas do time = MCP (ADR 0070); tarefas de cliente = ProjectMgmt/Essentials.

Crie também a PORTA LEVE de _Ideias (índice da incubadora — 12 docs; é só um sumário, não distilação).

scan PII. 1 PR, conventional, Refs: SDD KL-E2 B8. Sem ghost-fix --write, sem reseed. Sem CT 100/prod.
```

---

## B9 — Portas-thin para wishes (status: wish)

```
Você está numa sessão paralela das Semanas 1-2 do plano SDD do oimpresso (frente KL-E2, wishes).
Contexto: 2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md + este pack.

PASSO 0 — GATE E1: confirme em _TRIAGEM-IDENTIDADE-2026-06.md que Autopecas, Comissao, Garantia,
Marketplaces e Pcp estão marcados GENUÍNO/wish. Em branco/divergente → não toque, reporte.

Para CADA uma, crie um BRIEFING.md MÍNIMO (1 parágrafo, NÃO distilação — isto é E2 mecânico):
"<Módulo> — feature-wish formal, status: wish. Aguarda sinal qualificado (ADR 0105). Não há código.
Detalhe em SPEC.md / discovery." Use front-matter leve com `status: wish`.
NÃO funda Marketplaces em Woocommerce (escopos distintos — P15; confirme no E1).

scan PII. 1 PR, Refs: SDD KL-E2 B9. Sem ghost-fix --write, sem reseed. Sem CT 100/prod.
```

---

## C1 — BRIEFINGs destilados (KL-E3 · gerador Sonnet)

```
Você está numa sessão paralela das Semanas 2-4 do plano SDD do oimpresso (frente KL-E3 — BRIEFINGs destilados).
Contexto: 2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md (§4 Semanas 2-4) + este pack
+ memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md (G5 — seu lote vai ser refutado).

PASSO 0 — GATE E1 + anti-colisão: leia _TRIAGEM-IDENTIDADE-2026-06.md. Os módulos deste lote SÓ entram se
estiverem GENUÍNO no E1 e NÃO forem alvo de fusão/lápide de nenhum thread B. Em branco → PARE.

LOTE C1 (4 módulos; se passar de 300 linhas no PR, quebre em 2 sessões):
ComunicacaoVisual, ConsultaOs, Officeimpresso, PaymentGateway.
(TaskRegistry SAIU desta lista — Wagner decidiu FUNDIR em TeamMcp = vira fusão do thread B6, não distilação.)
RE-DERIVE a lista do E1 preenchido — não confie nesta cópia (anti-stale).

PARA CADA módulo: leia o corpus da pasta (SPEC + docs) + confira o código real em Modules/<X> + git log,
e destile UM BRIEFING.md executivo (1 página): o que o módulo É hoje (1 parágrafo de verdade), estado
(ativo/wish), capacidades reais vs planejadas, sucessores/dependências, link pro SPEC. Modelo de qualidade:
memory/requisitos/Jana/BRIEFING.md e memory/requisitos/Financeiro/BRIEFING.md.

REGRAS DURAS:
- NÃO invente capacidade que o código não tem. Na dúvida, escreva "planejado, não construído".
- scan PII diff-only (CPF/CNPJ/telefone/e-mail/nome cliente). Hits=0.
- Abra o PR como DRAFT e PARE: o lote PRECISA passar pelo refutador G5 (prompt C1-R, sessão fresca) +
  entry no ledger governance/sdd-verification-ledger.json ANTES do merge (PROTOCOLO-REFUTADOR-BACKFILL §2).
Conventional, ≤300 linhas, Refs: SDD KL-E3 C1. Sem CT 100/prod.
```

## C1-R — Refutador G5 do lote C1 (sessão FRESCA, outra conta, modelo ≥ Sonnet)

```
Você é o REFUTADOR adversarial (GT-G5) do lote de BRIEFINGs do PR #<N> (frente KL-E3 do plano SDD oimpresso).
Você está numa SESSÃO FRESCA — zero contexto do gerador. NÃO leia a conversa do gerador. Modelo ≥ Sonnet.
Protocolo: memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md (siga §2-§4 à risca).

TAREFA: prove que cada BRIEFING destilado do PR está ERRADO. Para CADA afirmação de capacidade, busque
evidência no CÓDIGO REAL em origin/main (paths, git log, testes) — NÃO no texto do PR. Veredito por item:
CONFIRMADO ou REFUTADO + evidência (path:linha/commit + porquê).

AMOSTRA: prosa destilada → ≥30% dos arquivos do lote, seleção aleatória com SEED declarada na evidência.
CHECKLIST (copie pro artefato): sessão fresca ✓ · modelo ≥ gerador ✓ · amostra ≥30% c/ seed ✓ ·
verificado contra código real ✓ · cada REFUTADO com evidência ✓ · SCAN PII no diff (hits=0) ✓ ·
error_rate_pct = erros_confirmados/itens_verificados < 2 ✓.

SAÍDA: entry append-only em governance/sdd-verification-ledger.json (schema §4: pr, lote_id "KL-E3-C1",
tipo "prosa", gerador/refutador, sessao_fresca:true, amostra_pct, itens/erros, error_rate_pct, pii_scan:true,
pii_hits:0, evidencia, veredito). error_rate ≥2% → veredito "reprovado", lote VOLTA inteiro pro gerador.
Commit a entry no MESMO PR do lote. Refs: SDD KL-E3 G5.
```

---

## TAIL — KL-E2b serializado (1 thread, DEPOIS de todos os B mergeados)

```
Você está fechando a frente KL-E2 do plano SDD do oimpresso. Rode SÓ depois que TODOS os PRs dos threads
B (B1-B9) estiverem mergeados em origin/main. Contexto: este pack + 2026-06-13-kl-semana0-ja-entregue-conciliacao.md.

PASSO 1 — codemod de texto: ⚠️ JÁ RODOU (#2603 + #2693, ghost_count 27→15). NÃO re-aplique cego.
- `node scripts/governance/ghost-fix.mjs` (dry-run) SÓ pra CONFERIR — espere ~0 ocorrências mapeáveis novas
  (idempotente). Se as fusões de pasta dos threads B introduziram referência nova a um nome morto, aí sim
  rode `node scripts/governance/ghost-fix.mjs --write` num PR isolado (Refs: SDD KL-E2b codemod). Senão, pule.

PASSO 2 — re-seed do índice de busca (senão o recall busca nomes mortos pós-rename):
- Localize o comando de rebuild do corpus (provável: artisan scout:import OU rebuild em Modules/Jana
  /Modules/KB — ver Modules/Jana/Services/Memoria/MeilisearchDriver.php e Modules/KB/Services/KbCorpusBuilder.php).
- O índice Meilisearch vive no CT 100 se remoto — confirme onde roda antes (regra runtime Hostinger≠CT100).
- Rode 1× serializado, DEPOIS do codemod.

PASSO 3 — verificação de fechamento (read-only):
- `node scripts/governance/knowledge-drift.mjs --json` → ghost_count deve ter CAÍDO (meta 27→0) e
  front_door_coverage SUBIDO (meta 62%→100%).
- `node scripts/governance/sdd-scorecard.mjs --ratchet` → exit 0 (sem regressão).
Reporte os números antes/depois. Refs: SDD KL-E2b verificação.
```

---

## Emenda E1 — TaskRegistry → TeamMcp ✅ APLICADA (PR #2747)

O E1 canônico (`fea48e0a4`) tinha decidido **TaskRegistry = porta própria**. Wagner reabriu em 2026-06-15 e
mudou pra **FUNDIR em TeamMcp** (TaskRegistry roda dentro do MCP server). Aplicado em
`sdd/kl-identidade-decisoes` (commit `6c89e9b3b`, **[PR #2747](https://github.com/wagnerra23/oimpresso.com/pull/2747)** → main):
Tabela A (TaskRegistry) + Tabela B (P9) + nota de rodapé. Efeito: TaskRegistry sai da Lane C e entra no thread B6 (fusão→TeamMcp).

---

## Ordem de disparo sugerida

0. **E1 decidido + resolvido + emendado** — resolução 100% + emenda TaskRegistry→TeamMcp na
   `sdd/kl-identidade-decisoes` (PR #2747 → main). Quando #2747 mergear, o E1 do main fica completo.
1. **Onda B paralela (fusões de pasta — o que de fato falta):** B1-B6 simultâneos (alvos disjuntos) + B8 + B9.
   **B7 está PAUSADO** (cluster Estoque adiado) — só a porta leve do Estoque, se quiser.
2. **TAIL** depois que os B mergearem. O codemod de texto JÁ rodou — o TAIL fica em re-seed Meilisearch +
   verificação (ghost_count/front_door). Re-derive antes.
3. **Lane C (E3)** em paralelo com a onda B (módulos disjuntos); cada lote DRAFT até o refutador C1-R + ledger.

> Throughput Wagner é o risco #1 do plano (§6): não dispare tudo de uma vez. E há MUITA frente SDD paralela
> em voo (dezenas de worktrees `sdd/*`) — confira `git worktree list` + PRs abertos antes de abrir nova lane,
> pra não colidir com fusão já em andamento.
<!-- schema-allowlist: salvo de feat/governance-ds-rollout-ledger (branch shallow-orfanada 2026-06-20); output de subagente/legacy, schema estrito de secao nao se aplica -->
