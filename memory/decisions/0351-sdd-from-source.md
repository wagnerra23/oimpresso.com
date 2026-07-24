---
slug: 0351-sdd-from-source
number: 351
title: "sdd-from-source — agent das 3 camadas (analisa 3 fontes → documenta no padrão → confere por gate) + religa o distiller com venue in-session"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: meta
decided_by: [W]
decided_at: "2026-07-24"
module: jana
tags: [sdd, distiller, memoria, mwart, paridade, agent, governanca, ct100, anti-apodrecimento, triangulacao]
supersedes: []
superseded_by: []
related:
  - 0291-distiller-modulo-verdade-contrato-emenda-0270-f3
  - 0292-errata-0291-distiller-freshness-scorecard-deterministico
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0345-topicos-vivos-aprendizado-por-critica-revisada
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0062-separacao-runtime-hostinger-ct100
  - 0105-cliente-como-sinal-guiar-sem-mandar
pii: false
---

> **Proposta [`2026-07-24-sdd-from-source`](proposals/2026-07-24-sdd-from-source.md)** escrita por [CC] e
> **ratificada por [W] em 2026-07-24** (chat: "religar o distiller, não aposentar; as 3 fontes React+Blade+Delphi
> são fundamentais"). O número **0351** foi alocado pelo `next-id.mjs` (ADR 0304, ciente de PRs+branches).
> Ratificação formal = merge deste PR (R10). O corpo desta ADR é append-only ([ADR 0257]).

# ADR 0351 — `sdd-from-source`: analisa o fonte → documenta no padrão → confere

## Contexto (medido, não suposto)

O programa SDD tem **2 das 3 camadas** que o melhor do ramo separa (analisar o fluxo correto do fonte ·
documentar no padrão · poder conferir se ficou certo) — e as duas que existem estão **fragmentadas**, faltando
a **1ª (análise automática do fluxo)** e a **costura das três num fluxo repetível**. O
[scorecard adversarial SDD de 2026-07-23](../sessions/2026-07-23-sdd-avaliacao-adversarial.md) (76/100) mediu o
custo dessa lacuna: o **distiller kill-switched** (0/76 portas git-backed — [ADR 0291]) e o **piloto Produto
empacado** (charter 7/7, casos 3/7, anchor do SPEC 11,1% — 8 de 9 US `sem_campo`). Escrever SDD à mão **não
escala**; foi por isso que o piloto travou. A dor de [W]: *"módulos gigantes onde os arquivos ficam grandes — a
máquina que obriga preencher tem que se adaptar."*

Documentar **só o React atual reproduz a regressão da migração**: o rewrite Blade→React já perdeu features em
silêncio (o menu de Ações da lista de vendas sumiu no #1032). Sem triangular as fontes legadas, o agente carimba
a perda como se fosse o correto.

## Decisão

Criar um **agent** `.claude/agents/sdd-from-source.md` (padrão do projeto pra análise — como
`capterra-senior`/`wagner-understand`) que **orquestra as 3 camadas** e **não cria máquina de análise nem tipo
de doc novos**. Aponta pra um `<Mod>/<Tela>` e entrega o SDD/casos preenchidos + veredito dos gates.

### D-A — Camada 1: ANÁLISE triangula 3 fontes (requisito [W] FUNDAMENTAL)

A análise lê **as 3 fontes na ordem-de-fonte canônica** ([how-trabalhar §ordem de fonte](../how-trabalhar.md)),
nunca só o presente:

| Fonte | Onde | Pra quê |
|---|---|---|
| **React/Laravel atual** | `.tsx` + Controller + Service + Model | o fluxo vivo (o que existe hoje) — vira o §5 do SDD |
| **Blade AdminLTE legada** | `resources/views/<x>/**` | migração MWART ([ADR 0104]) — o que a tela antiga fazia que o React precisa manter |
| **Delphi / Office Comercial** | `ANTI-REGRESSAO-*.md` (destilado do manual WR Comercial) | **contrato de paridade** — feature não some sem Non-Goal explícito |

Sem as fontes 2 e 3, o agente documenta um React que **pode já ter perdido features**. Isso **não é opcional** —
é o que fecha a migração.

### D-B — Camada 2: DOCUMENTAÇÃO preenche o que já tem dono e gate (ZERO tipos novos)

Só preenche o que a [taxonomia ADR 0345] define:
- `SDD-tela-<x>.md` — **§5 fluxo** (do React) + **§6 CU** (paridade contra Blade+Delphi);
- `<Tela>.casos.md` — via [`criar-tela.mjs`](../../scripts/governance/criar-tela.mjs) pra tela nova; ao lado do
  charter existente pra tela que já existe (o UC deriva do **SDD/CU**, nunca do `.tsx` — precedência
  [proibicoes §Precedência]);
- linhas `**Implementado em:**` propostas pro `SPEC.md` (formato [ADR 0273]);
- os docs de migração `ANTI-REGRESSAO-<tela>-legacy.md` (paridade Delphi→React) + `PARIDADE-charter-vs-legado.md`
  (gaps do cutover) — **derivados/atualizados**, não re-transcritos a cada tela.

**Regra dura anti-duplicação:** o output é SEMPRE um arquivo que já tem dono e gate. Gerar um
`ANALISE-*.md`/`FLUXO-*.md` novo é **bug** — o fluxo mora no §5 do SDD, não num arquivo paralelo.

### D-C — Camada 3: CONFERÊNCIA devolve veredito por US

Roda `casos-gate` ([ADR 0264]) + `anchor-lint` ([ADR 0273]) e devolve ✓/🧪/❌ **por US**. Humano confere e
corrige — não escreve do zero. O agente é **re-rodável** (deriva do fonte — lei [ADR 0256]): ~70% do SDD fica
auto-conferível; os ~30% curados (§0 benchmark, §2 personas) levam **badge derivado/curado** e seguem foto que
envelhece (honesto).

### D-D — Religa o distiller como MOTOR, com venue in-session (decisão [W] 2026-07-24)

O distiller ([ADR 0291]/[0292], `DistillerModuloVerdade.php`, 40% da visão) é **religado** — mas o venue muda
pra fechar o bug do kill-switch (*write na árvore DEPLOYADA do Hostinger via cron, perdido a cada redeploy →
0/76 portas com `distilled_at` no git*):

- **Venue = o agente roda NA sessão/worktree** (git-backed por construção — `base_path()` é o worktree, não o
  deploy). Invoca `jana:distill-module-truth --module=X --dry-run` pra **capturar** o BRIEFING destilado
  (zero write na árvore viva), grava os artefatos no worktree e abre **PR**. A camada 3 (gates) gateia o merge.
- **O cron do [`Kernel.php`](../../app/Console/Kernel.php) FICA comentado** (kill-switch preservado). O venue
  autônomo `clone + auto-PR bot` (precedente #3442/#3485) é **follow-up**, como a proposta escopa — esta ADR
  religa **só a destilação sob-demanda pelo agent**.
- A execução real da destilação (chamada LLM) valida no **CT100** ([ADR 0062]); em prod segue gate [W]
  (smoke skim). Este contrato nasce sem rodar destilação em prod.

### D-E — Guardas Tier 0 (inegociáveis)

- **ZERO tipos de arquivo novos** — só preenche a taxonomia [ADR 0345] (D-B).
- **Linkagem por `id` estável** (`doc-id-index`) + `related_adrs`/`related_us` + rastro append-only de
  `sessions/`+`handoffs/` — **não inventa "mapa de conversas" à mão** (proposal irmã `referencia-id-estavel`).
- **Multi-tenant [ADR 0093]** — todo caso de uso `[T0]` carimba o `business_id` scope; teste biz=1 nunca biz=4.
- **REGRA MESTRE valor/estoque** — CU que toca preço/custo/estoque nasce `[V0]` (dupla-confirmação + antes→depois).
- **Anti-tautologia** — o UC deriva do **contrato** (SDD/CU/SPEC), nunca da implementação ([proibicoes §5]); o
  agente **PERGUNTA** quando falta fonte, não inventa (anti-padrão inventado é pior que ausente).
- **PT-BR**, `--dry-run` como default de análise, PII redactada antes de qualquer write (herda o PiiRedactor do
  distiller).

## Consequências

- **Positivo:** o SDD deixa de ser artesanato de sessão; módulo gigante vira viável (a máquina gera o rascunho
  lendo o código, o humano confere); o piloto Produto destrava; a migração para de perder feature em silêncio
  (triangula Blade+Delphi); o distiller volta a rodar **sem** reintroduzir o write-loss.
- **Custo/risco:** implementar o agent (peça média, Tier 0 — costura o coração do SDD). **Nenhuma tecnologia/
  dependência nova.** LLM pode alucinar → contido pela camada 3 (gates reprovam antes do merge) + gate humano.
- **Não muda** os gates required nem cria tipo de doc; usa `criar-tela` + os gates que já mordem; segue o
  calendário de promoções [ADR 0275].

## Rollback

Se o agent gerar SDD ruim, o veredito da camada 3 reprova antes do merge — o dano é contido pelo próprio
mecanismo que ele orquestra. Reverter o agent = remover `.claude/agents/sdd-from-source.md`; o distiller volta ao
estado kill-switched (nenhuma regressão, o cron nunca foi religado).

## O que esta ADR NÃO decide

- O formato do `SDD-tela` (já é o do [Produto](../requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) — não reabrir).
- A automação por **cron** do distiller (venue `clone + auto-PR bot` = follow-up).
- Promoção de qualquer gate a required (segue [ADR 0275]).

## Referências

- Proposta [`2026-07-24-sdd-from-source`](proposals/2026-07-24-sdd-from-source.md) (o blueprint ratificado)
- [scorecard adversarial SDD 2026-07-23](../sessions/2026-07-23-sdd-avaliacao-adversarial.md) (76/100 — a evidência)
- [ADR 0291]/[0292] distiller-módulo-verdade (o motor religado) · [ADR 0104] MWART (as 3 fontes)
- [ADR 0264] trio/casos-gate · [ADR 0273] anchor-lint · [ADR 0345] taxonomia · [ADR 0256] derivado>escrito
- [ADR 0093] multi-tenant Tier 0 · [ADR 0062] CT100 · [ADR 0105] cliente como sinal · [ADR 0275] calendário de promoções

[ADR 0257]: 0257-adr-status-lifecycle-kind-modelo-canonico.md
[ADR 0291]: 0291-distiller-modulo-verdade-contrato-emenda-0270-f3.md
[0292]: 0292-errata-0291-distiller-freshness-scorecard-deterministico.md
[ADR 0292]: 0292-errata-0291-distiller-freshness-scorecard-deterministico.md
[ADR 0104]: 0104-processo-mwart-canonico-unico-caminho.md
[ADR 0264]: 0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0273]: 0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md
[ADR 0345]: 0345-topicos-vivos-aprendizado-por-critica-revisada.md
[taxonomia ADR 0345]: 0345-topicos-vivos-aprendizado-por-critica-revisada.md
[ADR 0256]: 0256-knowledge-survival-meia-vida-catraca-sentinela.md
[ADR 0093]: 0093-multi-tenant-isolation-tier-0.md
[ADR 0062]: 0062-separacao-runtime-hostinger-ct100.md
[ADR 0105]: 0105-cliente-como-sinal-guiar-sem-mandar.md
[ADR 0275]: 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md
[ADR 0304]: 0304-alocacao-numero-ciente-trabalho-em-voo.md
[proibicoes §5]: ../proibicoes.md
[proibicoes §Precedência]: ../proibicoes.md
