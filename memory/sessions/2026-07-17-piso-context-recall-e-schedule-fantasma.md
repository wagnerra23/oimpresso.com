---
date: "2026-07-17"
hour: "13:30"
topic: "Piso de context_recall (US-COPI-136) + os evals da Jana eram schedule fantasma (US-COPI-140)"
authors: [C]
us: [US-COPI-136, US-COPI-140, US-COPI-137]
prs: [4412, 4426]
related_adrs: [0318-ragas-eval-real-mata-tautologia-ct100-staging, 0302-doneness-lint, 0273-anchor-spec-codigo-formato-canonico-fluxo-novo, 0062-separacao-runtime-hostinger-ct100]
---

# Piso de context_recall + schedule fantasma dos evals da Jana

Sessão da dimensão **qualidade-drift-ia-producao** (grade 2026-07-17, nota 4,0/10). Entrada: piso de `context_recall` (US-COPI-136). Saída: o piso **e** a descoberta de que os evals de qualidade da Jana **nunca rodam sozinhos** no CT 100.

## O diagnóstico está pior que 4,0

A grade dizia "zero eval no tráfego real". Medindo, o buraco é maior: **também não há eval offline confiável**.

- **`jana:ragas-real-eval`** e **`jana:recall-eval --mode=real`** (os 2 schedules `environments(['staging'])`) **nunca dispararam sozinhos**. O gate de ambiente casa (`APP_ENV` do container **é** staging), mas **nada invoca o scheduler**: `schedule:run` = 0 em todo cron do CT 100; container sem cron/supervisord; `/etc/periodic/*` vazios. Todo número do baseline honesto (`governance/jana-ragas-real-baseline.json`) veio de run **manual**. O transporte (`ct100-ragas-publish.sh`, dom 08:30) está vivo e relê o mesmo report velho toda semana — por isso a órfã `governance/ragas-real-trend` tem 1 semana só desde 04/07.
- **`jana:drift-sentinel`** é o **irmão de controle**: `environments(['live'])`, **roda** em prod toda semana (log `copiloto-ai` 05/07 06:01:29 e 12/07 06:01:27, `mock_mode:false`). Prova que o defeito é **só o invocador do CT 100** — não o Kernel, não o comando, não o gate. MAS ele é **cego por construção**: compara contra baseline mock `0.85` (× 50 perguntas, de 16/05, nunca regravado) com tolerância `0.25` → só dispara se o real sair de `[0.60, 1.10]`; o real é ~0,69–0,73, nunca sai. Verde eterno = falsa segurança. (Chip C3 sobe de higiene pra conserto.)

## O que entregou

### US-COPI-136 — piso de context_recall (MERGED #4412, na main)
O comando **media** `context_recall` e **jogava fora** (impresso `(info)`, piso `—`, fora do `gatePass`). Podia cair de 0,3839 pra 0,20 sem nada avermelhar; o comando tinha **zero testes** ("a suite mente"). Piso **0.36** = `min(0.3839, 0.3951, 0.3939) × (1 − 6%)`, mesma margem relativa dos pisos irmãos, folga = 2,1× o spread. Baseline virou **dono único** dos pisos (`resolveThresholds()` lê `thresholds_regressao` em runtime) — fecha o follow-up que a ADR 0318 registrou; antes era decorativo, com os pisos duplicados no signature (`0.80`, acima do medido, fabricava `fail`) e no Kernel. Bite-test (`RagasRealEvalGateTest`, `gateVerdict()` função pura): **7 passed** no Pest real do CT 100, mordida provada (recall 0,20 + faith/rel bons → derruba).

### US-COPI-140 — invocador dos evals (MERGED #4426, código feito · DoD domingo)
Caminho A (decisão [W]): `scripts/tests/ct100-jana-evals.sh` + sync anti-drift no `self-update.sh` + cron `0 6 * * 0` no host CT 100. **Provado disparando sozinho** (cron de teste one-shot, 11:27:01 → report escrito pelo cron). Selftest hermético (`ct100-jana-evals.test.sh`, docker mock) registrado no `governance-script-tests.yml`, mordida provada por mutação. `drift-sentinel` **fora** do script (já roda em prod). **DoD pendente por construção**: "semana nova na órfã que ninguém rodou à mão" só existe **domingo 2026-07-19 06:00** → status `doing`/`_pendente_`.

### US-COPI-137 — eval online 5% (NÃO feito — provado VIÁVEL)
Verifiquei que **prod tem worker de fila** (`QUEUE_CONNECTION=database`, `jobs` pendentes = 0 — prova empírica) e scheduler (via hPanel). Então um job amostrando 5% dos traces reais **rodaria de fato**, não seria fantasma nº 2. Próximo passo natural (Tier 0: `PiiRedactor` antes do juiz, biz≠1).

## Onde eu errei (registrado no §5 das proibições)

1. **Contei schedules parseando `Kernel.php` — errei 3× seguidas.** "3 evals" (são 2), "7 perigosos" (estão em `if ($env==='live')`, nem registram), "1 colateral" (são 3). O número errado **embasou a pergunta A/B ao [W]**. Acertei na 1ª vez que perguntei ao **runtime** (`Event::runsInEnvironment()`): `82 registrados · 77 filtrados · 5 rodariam`. 82 > 65 porque **módulos registram schedules próprios** — eu analisava 1 arquivo num sistema multi-fonte. Lição: *"varri o arquivo" não é "varri o sistema"*; quando o runtime sabe, perguntar não é opcional.
2. **Quase reportei achado falso sobre prod** ("Hostinger não roda scheduler") porque `crontab -l` deu vazio — mas o binário **nem existe** lá (`command not found`), e o `|| echo` imprimiu ausência. Salvou-me evidência contrária (brief gerado há 196 min). Lição: saída vazia de comando-que-pode-não-existir ≠ ausência; medir pela **consequência**.
3. **Path mangling do MSYS** (`git show origin/main:<path>` → stdout vazio) quase me fez reportar que o step do CI não aterrissou. `git cat-file -p <blob>` confirmou que estava lá. (Lição já catalogada em memória, aplicada.)

## Meta-observação

O #4412 foi **mergeado (squash) enquanto eu ainda trabalhava**, levando meus números errados pra main junto com o piso. O #4426 reconciliou (errata + invocador + selftest + 2 lições). Toda declaração de "verde" foi verificada rodando o gate localmente antes — inclusive o `doneness-lint` que me pegou (âncora `_parcial_` × status aberto) e resolvi com `_pendente_`.

## Aberto pro próximo

- **US-COPI-137** (eval online 5%) — viável, não feito.
- **Chip C3** — regravar baseline do `drift-sentinel` (`--update-baseline` real); hoje é alarme cego. Não depende de infra (ele já roda em prod).
- **US-COPI-140 DoD** — confirmar domingo 19/07 que a órfã ganhou semana nova sem run manual.
- **Chip drift staging** — checkout do CT 100 com correção aplicada à mão, 4d atrás da main (spawn_task feito).
