---
title: "SDD — `status: live` derivado de sinal de prod + covers só conta com teste em lane + frescor do scorecard no merge (fecha o que o adversário pegou, não o gate)"
status: proposed
date: "2026-06-24"
decisores: [Wagner (aprova), Claude Code (autor)]
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0303-anchor-lint-wired-testado-sa-a2-bis
  - 0302-fonte-unica-doneness-anchor-aposenta-status-spec
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0298-teto-de-governanca-anti-proliferacao-gates
  - 0179-cliente-drawer-760px-substitui-show-fullpage
relates_to:
  - proposals/2026-06-23-anchor-gates-arming-baseline-promocao.md
  - proposals/2026-06-23-anchor-covers-check-sa-a2-ter.md
origem: "Sessão 2026-06-24 (reconciliação SDD do Cliente). Em todo ponto que importou, quem pegou o erro foi o ADVERSÁRIO, não a máquina: âncora FAKE (US-073 apontava um teste `@group legacy-quarantine` off-target) e charter `live` INVENTADO (5 telas flag-gated, sem prova de prod). A máquina deu verde nos dois. Pior: pra saber se as telas estavam mesmo live, o agente teve que PERGUNTAR ao Wagner — 'biz=4 está no react?'. Conhecimento tribal, não sinal de máquina."
---

# PROPOSAL — a máquina de âncora não sabe o estado de prod nem distingue cobertura real de fachada

> **Não re-propõe** o arming pra required nem o covers-check — esses já vivem nas duas propostas de
> 2026-06-23 ([arming](2026-06-23-anchor-gates-arming-baseline-promocao.md) · [covers](2026-06-23-anchor-covers-check-sa-a2-ter.md)).
> Esta proposta (a) soma **evidência de campo** pra promovê-las e (b) fecha **o buraco que nenhuma cobre**:
> `status: live` não-verificável + frescor do número central. Tudo **estende** o que existe — zero gate novo (teto [ADR 0298](../0298-teto-de-governanca-anti-proliferacao-gates.md)).

## Contexto — o teste de fogo: a reconciliação do Cliente

Na reconciliação real do módulo Cliente (PRs #3333 mergeado + #3336), a máquina **deixou passar** três coisas que só não viraram mentira no `main` porque tinha adversário + Wagner no loop:

**Gap A — `status: live` é não-verificável (depende de tribal/adversário).**
O check de zumbi do `anchor-lint` (`existsSync` + grafo de render) é **cego a feature-flag**: deu **0-zumbi** enquanto as 5 telas Cliente (`Create/Edit/Ledger/Map/Import`) estavam atrás de `MWART_CLIENTE_*` **default OFF** (`config/mwart.php` + `ContactController::shouldRenderInertiaCliente`, com fallback Blade). A verdade de "qual tenant vê o React" mora no **.env de produção**, invisível ao repo. Resultado: `live` é setado à mão por conhecimento tribal; a 1ª versão promoveu os charters a `live` citando os `Wave1*InertiaTest` como "prova" — que só fazem `grep` do `.tsx`. Quem refutou foi o adversário; quem deu a verdade foi o Wagner respondendo "o 4 está no react".

**Gap B — "coberto" passa com fachada.**
A US-073 ancorou `**Testado em:**` num teste `@group legacy-quarantine` (fora de toda lane de CI) e **off-target** (testava o payload do índice, não as máscaras CPF/CNPJ do DoD). Passou o covers-gate porque ele é **grep do marcador** `@covers-us`. O `--check-verde` (que cruzaria JUnit e exigiria `passed>0, fail=0, skipped≠passed`) **existe** mas dorme: **nenhum** dos 16 testes citados está numa lane de JUnit → o "15/15 covers verde" é artefato de grep, não prova de suite rodando.

**Gap C — o número central atrasa até 1 dia.**
`governance/sdd-scorecard.json` (que alimenta o brief "SDD composta / anchor_coverage") só é reescrito pelo cron diário 07:10 BRT (`sdd-scorecard-publish.yml`). Entre o merge e o cron, o número que o time enxerga **mente por defasagem**.

## Decisão proposta — 3 fechamentos, todos reusando padrão existente

### 1. Sinal de prod no repo → `live` derivável (fecha Gap A — peça nova, maior alavanca)
Um probe de produção publica `governance/prod-flags.json` pelo **mesmo padrão do `nightly-floor.json`** (write-side CT100 → branch órfã → commit-back `[skip ci]`, [ADR 0279]). O `anchor-lint`/`doneness-lint` passa a exigir, pra um charter `status: live` de tela **flag-gated**, que o flag esteja **ON pro tenant vivo** nesse arquivo — senão o teto é `draft`/`canary`. Mata o "tive que perguntar".
- **Variante mais barata** se o probe for caro: `live` exige um **artefato de smoke** datado no PR (HTTP 200 / screenshot como o tenant vivo). O ponto invariante: **`live` = evidência, não palavra.**

### 2. Covers só conta com teste em lane (fecha Gap B — estende a proposta de arming)
Além do `@covers-us` (proposta 2026-06-23), o teste citado precisa estar numa **lane de JUnit** — senão o `--check-verde` nunca confirma (vira `ausente`). Um `@covers-us` em teste **quarantinado/fora de lane** vira `testado_sem_lane` (advisory de nascença, ADR 0271/0275), **não** "coberto". É a mecanização exata do que o adversário pegou à mão na US-073.

### 3. Frescor: scorecard recomputa no merge, não só no cron (fecha Gap C)
Trigger de **push→`main` que toca SPEC** dispara o `sdd-scorecard-publish` (commit-back `[skip ci]`, já é o padrão). O cron diário vira **backstop** pro drift de código-move-sem-SPEC. Janela de mentira por defasagem: ~1 dia → ~minutos.

### (esticado) Heurísticas anti-fachada baratas + âncora assistida
- Heurística no PR (folda no `anchor-lint`, sem gate novo): `**Testado em:**` aponta teste `@group quarantine`? → flag. DoD afirma comportamento mas o teste citado é só `file_get_contents`+`toContain`? → flag. Tira o óbvio antes do adversário.
- Âncora `**Implementado em:**` **proposta** a partir do grafo de rotas/render + índice `@covers-us` (humano confirma) — reduz âncora morta na origem (ex.: citei `_form/EnderecoBRSection.tsx`, que nem existe).

## Por que agora
O time MCP (Felipe/Maiara/Eliana/Luiz) entra em breve — **nem todo PR terá adversário nem o Wagner no loop**. A máquina, como está, deixa passar `live` sem prova e cobertura de fachada; a reconciliação do Cliente só ficou honesta por intervenção humana. Fechar A/B/C torna a máquina **auto-suficiente**: pega sozinha o que hoje depende de gente.

## Não-objetivos
- **NÃO** criar workflow/gate novo (teto [ADR 0298](../0298-teto-de-governanca-anti-proliferacao-gates.md)) — tudo estende `gates-registry`/`anchor-lint`/commit-back existentes.
- **NÃO** re-propor arming/covers — vivem nas propostas de 2026-06-23; aqui só somo evidência + a peça nova (sinal de prod).
- **NÃO** avermelhar `live` legado de uma vez — baseline grandfather (mesmo no-new-lie do arming).

## Teste de fogo (critério de pronto)
Re-rodar a reconciliação do Cliente. A máquina deve, **sozinha**: (a) recusar `live` sem prod-flag/smoke; (b) recusar `@covers-us` em teste fora de lane; (c) refletir o ganho no scorecard em minutos. Hoje: **0/3** (o adversário e o Wagner fizeram os três à mão).

## Status
**proposed** — aguarda Wagner. Pareada com [arming](2026-06-23-anchor-gates-arming-baseline-promocao.md) + [covers](2026-06-23-anchor-covers-check-sa-a2-ter.md) (esta sessão é evidência pra promovê-las).

[ADR 0279]: ../0279-sdd-medir-governar-floor-nightly.md
