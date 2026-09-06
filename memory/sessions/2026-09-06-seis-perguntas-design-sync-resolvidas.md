---
date: "2026-09-06"
topic: "As 6 perguntas abertas pelo #6892 resolvidas em 6 PRs: baseline RAGAS re-curado, guard LC-20 no recorder, 3 telas Fiscal testadas, 18 órfãos com destino, 7 âncoras medidas no espelho, 11 gap.md com mapa — e o smoke bloqueado no login"
authors: ["C"]
related_adrs: ["0384-design-sync-recibos-executaveis-por-tela", "0374-emenda-0315-espelho-cowork-e-rota-prevista", "0317-cron-watchdog-generaliza-auto-canario", "0344-two-strikes-cobre-processo"]
prs: [6893, 6894, 6895, 6896, 6897, 6898]
outcomes:
  - "Q4 · baseline RAGAS: re-cura datada com evidência do trend; watchdog rc 0 (#6894)"
  - "Q5 · LC-20: aviso pré-recibo medido no instante do --mark-*/--run-test/--record-smoke, bite-test 4/4 (#6896)"
  - "Q2 · Fiscal Config/Eventos/Sped: applied + tested via job CI que carrega os testes (#6898); smoke pendente de login biz=1"
  - "Q3 · 18 órfãos → bloqueadas 0 (1 charter com bundle_source + ALIAS Suporte + 15 A_CRIAR) (#6895)"
  - "Q1 · 7 âncoras que persistem no get_file medidas; 3 divergiam e desceram do vivo (#6893)"
  - "Q6 · 10 gap.md com tabela derivada + 11 map.json; cobertura 12/23 → 23/23, 0 DRIFT (#6897)"
---

# Seis perguntas, seis PRs — o que cada uma custou de verdade

> Continuação de [2026-09-05-integridade-prototipo-producao-sha-dupla.md](2026-09-05-integridade-prototipo-producao-sha-dupla.md). [W] leu as 6 perguntas e disse *"pode resolver todas autorizado"* + *"use o computador se necessário"*. Cada item virou PR próprio (1 intent), todos sobre `origin/main` fresco; o Q2 empilha sobre o Q3 porque a projeção depende do detector novo.

## O que foi medido antes de mexer (por item)

| Q | medida que mudou a ação |
|---|---|
| Q4 | O watchdog lê só as chaves `gerado_em`/`atualizado_em`/… — o baseline tinha 19 datas em prosa e `gerado_em: 2026-07-01`. Trend na branch órfã: jun–jul válidas perto dos pisos, ago colapso real (F0.30/R0.41/CR0.04), 2 semanas `skipped` com `no_context=51`. **Pisos mantidos**; só a data e a nota de revisão entraram. |
| Q5 | O guard de SessionStart mede uma vez; o vetor é pós-início. `git log HEAD..origin/main -- <path>` é o critério certo ("main andou neste path depois da base"), não "arquivo difere" — feature branch editando o alvo fica em silêncio. FP no bite-test: 0. |
| Q2 | Nenhuma lane "Fiscal": os testes do módulo rodam **dentro** de `PHP / Pest (NfeBrasil · MySQL)` por lista explícita. CT 100 está em `c1abe9548` (26/08) com 42 sujos — 2 falhas de ambiente, não é oráculo. Só 3 mapas dizem `fechado`/`paridade`; os outros 4 são `gap-parcial` e ficaram `compared`. |
| Q3 | Dos 18: 4 têm tela viva; 15 são Blade/legado ou não existem. Suporte tem SPEC **sem nenhuma US** → `bundle_source` no charter acordaria o `charter-us-lint` sem related_us honesto → ALIAS do detector (porta prevista). ComunicacaoVisual é a US-COMVIS-001. |
| Q1 | 32 âncoras; 9 acima do piso de persistência do `get_file` (7 novas nesta rodada). 3 divergiam — diff mostra a mesma migração do diário (nav bespoke → `CliTabs`, `.fin-seg` → `CliSeg`), vivo à frente. |
| Q6 | Só 1 dos 11 gap.md parseava. Tabela **derivada** da prosa (não reescrita): Parte = seção, Ação = "Decidir." + gap já escrito, ou "Nada". 2 protótipos expurgados → `prototipo: TODO` com a razão em `prototipo_nota`. |

## O que os gates pegaram (e o que ensinou)

- **`charter-us-lint` (no-new-lie)** mordeu ao tocar 3 charters. Certo: tocar legado acorda dívida diff-aware (§5 2026-07-12/27). Suporte não tinha US pra declarar → **reverter o toque**, não inventar slug (o lint só valida forma; passaria mentindo).
- **Comentário inline em YAML de frontmatter** (`chave: valor  # nota`) quebra dois consumidores diferentes: `gerar-map` leu `TODO  # …` como caminho (17 DRIFT) e `charter-us-lint` leu `[US-…]  # …` como slug inválido. Regra prática: nota vai em chave própria (`*_nota`), nunca inline.
- **BRL scan** mordeu nas tabelas derivadas: copiaram um valor `R$ [redacted Tier 0]` da prosa antiga (mock do protótipo, mas a proibição é cega ao contexto). Redigido só nas linhas novas.
- **`git checkout -- <path>`** restaura do índice, não do main — quando o toque já está commitado no branch, reverter exige `git checkout origin/main -- <path>`.
- **`if cmd | tail` avalia o `tail`**, não o `cmd`: o merge conflitado passou como "limpo". Use `PIPESTATUS` ou não canalize o comando que decide (§5 2026-08-13 já dizia).

## Bloqueado (declarado)

- **Smoke D-6 → `validated`**: produção redirecionou para `/login`; não digito senha (regra dura). Tab aberta em `https://oimpresso.com/fiscal/config` aguardando [W] logar como biz=1; então `--record-smoke` com deploy `26ac293f46`.
- **23 âncoras abaixo do piso** seguem sem veredito; **bundle** só do lado Cowork (Q1 tem um limite físico daqui).
- **Dfe**: sem teste na lane (`DfeControllerTest`/`AcoesDfe*` não estão na lista explícita) — candidato ao próximo ratchet-up da `nfebrasil-pest.yml`.

## Pós-merge — o lote #6897 sob refutação GT-G5, e o incidente LC-12

- **Refutação adversarial (9 rodadas, uma instância fresca por rodada):** 13,48% (19/141) → 2,84% → 5,71% → 6,32% → 8,91% → 3,23% → 4,48% → 3,23% → **1,35% (1/74), aprovado**. Defeito de fundo da r1: a derivação mecânica copiava a coluna de DESCRIÇÃO do gap e carimbava "Decidir." sem ler a coluna de VEREDITO. O lote encolheu de 11 para 5 telas no caminho — fundação (`gerar-map` só resolve `Pages/`), OficinaAuto (âncora revogada como MIS-ANCHOR no charter), Crm (o gap diz "análise, não geração"), Sells/Index (mapa duplicado) e Sells/Create (âncora sem charter) saíram porque não tinham como ser honestos. Cobertura final 17/23. Entry no ledger `governance/sdd-verification-ledger.json`; evidências `sessions/2026-09-06-refutacao-gt-g5-lote-6897{,-r2..-r9}.md`.
- **Incidente meu (LC-12, 3ª ocorrência):** ao limpar os branches remotos dos 7 PRs, um `for b in $(git ls-remote --heads origin claude/q*)` → `git push origin --delete "$b"` apagou **4 branches de outras sessões** (`quick-sync-lock-cleanup`, `quizzical-chaum`, `quizzical-tharp`, `quizzical-yalow`). O glob presumia posse; o nome vinha de variável e eu não li os nomes resolvidos. Restauradas em minutos pelo head dos PRs (#291 · #88/#87 · #820 mergeados; #5765 fechado → `e16230377d`; o ref local `645acfb69a` da yalow diverge e ficou registrado, não sobrescrito). Dono do tema estendido no PR #6900: `avisoPushDelete()` em `block-destructive.mjs` (advisory; só nome não-literal), bite-test 6 + controles 4.
