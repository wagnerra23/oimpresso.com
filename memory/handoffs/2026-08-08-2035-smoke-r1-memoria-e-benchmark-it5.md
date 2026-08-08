---
date: "2026-08-08"
time: "20:35 UTC"
slug: smoke-r1-memoria-e-benchmark-it5
tldr: "Fecha os 2 pendentes do handoff das 19:36 — o smoke R1 de /ia/memoria e o IT5 STALE. O smoke pagou o próprio custo: achou o primary sem estilo em 11 telas (Jana/Financeiro/Ponto), vivo desde 2026-05-22 e invisível pra toda defesa. E a premissa do IT5 estava errada: ele nunca avermelhou PR nenhum."
prs: [5433]
decided_by: [W]
related_adrs:
  - 0190-primary-button-roxo-universal-295
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
next_steps:
  - "Corrigir `.os-btn` em 11 telas — migrar os 3 shims DEPRECATED pra `<PageHeaderPrimary>` (chip já rodando em sessão separada; 3 módulos ⇒ ~3 PRs)"
  - "Smoke da Memória em 1280px real e com fato existente — o de hoje rodou em 1440 e em biz=1 vazio, então o fluxo vivo segue NÃO exercitado"
  - "Avaliar se `.os-btn` fora de escopo merece trava (MEDIR o FP antes — regra LIGUE A MÁQUINA item 4)"
---

# Smoke R1 da Memória + o Benchmark que destravou o IT5

> Horário: **20:35 UTC = 17:35 BRT**. Ver a última lição sobre o rótulo de hora.

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ATIVO em COPI**
- `my-work` (@wagner): 10 tasks, **todas em REVIEW** — nenhuma desta sessão (o trabalho veio de `next_steps` de handoff, não de task)
- Handoff-pai: [2026-08-08 19:36 — fatia D da Jana](2026-08-08-1936-jana-memoria-fatia-d-lgpd-motivo.md) (fecho os 2 pendentes dele)

## O que aconteceu

**R1 cumprido.** `/ia/memoria` em prod (biz=1): renderiza, console **limpo** (zero mensagens após reload com tracking ativo), e o banner mostra a fatia D na cara do usuário — *"Toda alteração registra autor e motivo no log de auditoria"*.

**Dois limites declarados, não maquiados:** o viewport real era **1440**, não os 1280 do RUNBOOK (o `resize` pegou a janela, não o viewport); e biz=1 está no **empty state**, então não havia fato pra editar — a edição-com-motivo segue provada em CI, **não** ponta-a-ponta. biz=4 é proibido em smoke (R6). Continua validação estática, como o RUNBOOK já declarava.

**IT5 fechado** logando a linha do Benchmark (§11, append — nunca reescreve). Recibos: `integrity-check.mjs` **exit 1 → 0**; `design-memory-gate.test.mjs` **13/13 PASS**, incluindo o `T7 integrity-check sai 0` — que era um **segundo consumidor silenciosamente vermelho** pelo mesmo staleness, sem ninguém olhando.

## O achado que não era o pedido — 11 telas com o primary sem estilo

| o quê | prova |
|---|---|
| Os 3 shims DEPRECATED (`Jana`/`Financeiro`/`Ponto` `PrimaryButton`) emitem `.os-btn primary` | `git grep -l -E "import (Jana\|Financeiro\|Ponto)PrimaryButton" -- 'resources/js/**/*.tsx'` ⇒ **11** (varredura contada, sem truncar) |
| No CSS **embarcado** só existem regras `.sells-cowork .os-btn` | CSSOM de prod: 36 regras `.os-btn`, escopos = `.sells-cowork*` apenas. `grep` no `resources/css/*.css`: **zero** `.os-btn` sem wrapper |
| Render real | `padding: 0px` · `border-radius: 0px` · `white-space: normal` ⇒ texto quebra em 2 linhas dentro do retângulo roxo |
| Por que ninguém viu em ~2,5 meses | o `style` **inline** aplica só a cor (roxo 295 · ADR 0190). Parece botão; só o padding denuncia |
| Idade | `JanaPrimaryButton` nasceu **2026-05-22** (#1385); os 3 viraram shim roxo em **2026-05-25** (#1462) |

**Uma hipótese minha morreu no caminho, e é a parte que vale carregar.** Pela **fonte**, os 6 do Financeiro tinham wrapper `.fin-cowork` e estariam salvos — eu ia reportar **5** telas. O runtime refutou: o botão *está dentro* do wrapper e mesmo assim `NENHUMA regra .os-btn casa com o botao`, porque o bundle do Financeiro **não chega ao CSS servido**. São **11**, não 5. Fonte não é oráculo de render.

Verificado com controle **positivo** (`/ponto/escalas`, sem wrapper, quebrado) e **negativo** (`/financeiro/plano-contas`, com wrapper, quebrado do mesmo jeito) — foi o negativo que derrubou a hipótese.

## Correção de premissa (o handoff-pai registrou errado)

O handoff das 19:36 diz que o IT5 STALE *"avermelha QUALQUER PR de design-memory"*. **Não avermelha.** O step é `continue-on-error: true` ([design-memory-gate.yml:187](../../.github/workflows/design-memory-gate.yml)) e as **8 últimas runs** do workflow deram `success` — incluindo as duas da própria branch da fatia D. O item era real pelo invariante §13 nº1 do método ("medir é inegociável"), **não** pelo CI.

Junto, dois falso-positivos meus barrados por consultar o dono da verdade: `briefing-code-staleness` **não** é required (meu teste casava nome de arquivo, não nome de check), e o baseline tem **40** contexts — meu primeiro parse leu a estrutura errada e disse "2".

## Artefatos

| arquivo | o quê |
|---|---|
| [`prototipo-ui/PROCESSO_MEMORIA_CC.md`](../../prototipo-ui/PROCESSO_MEMORIA_CC.md) §11 | **+1 linha** no log de tendência (append-only). Único arquivo do PR |
| [PR #5433](https://github.com/wagnerra23/oimpresso.com/pull/5433) | mergeado 20:31Z · **97 checks pass, 0 falhas** · squash `c098f751e5c` |
| chip `task_745b04bd` | correção das 11 telas — **rodando em sessão separada**, com a varredura contada e as provas reproduzíveis no prompt |

## Próximos passos pra retomar

```
brief-fetch && node prototipo-ui/integrity-check.mjs && gh pr view 5433 --json mergedAt
```

## Lições catalogadas

- **O smoke R1 é que pegou, e ele estava pendente.** O defeito das 11 telas viveu ~2,5 meses e **nenhuma defesa disparou** — nem `ds-guard`, nem `visual-regression`. Quem fechou o buraco foi o passo manual que ficou devendo e o [W] cobrou. Isso é dado sobre a régua, não sobre o botão.
- **Fonte ≠ runtime, de novo** (família LC-08). A leitura do wrapper diria 5 telas; a medição disse 11. O controle **negativo** foi o que derrubou a hipótese — sem ele eu teria publicado o número menor com ar de rigor.
- **`continue-on-error` engole IT duro.** Um step pode sair 1, o método chamar a estrutura de "COMPROMETIDA", e o PR seguir verde. Ler o YAML **e** a conclusão real das runs, nunca só um dos dois.
- **Hora do handoff:** o irmão das "19:36 BRT" foi commitado às **19:59 UTC / 16:59 BRT** — o número é UTC e o rótulo BRT está errado. Este declara `20:35 UTC` (= 17:35 BRT) pra não propagar a ambiguidade.
