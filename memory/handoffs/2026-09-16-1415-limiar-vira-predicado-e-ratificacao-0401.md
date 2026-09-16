---
date: "2026-09-16"
time: "14:15 UTC"
slug: limiar-vira-predicado-e-ratificacao-0401
tldr: "O limiar de fracao do C1 nao era um numero mal escolhido — era a metrica errada, e minha primeira calibracao 'provando' que 0.5 servia foi TAUTOLOGICA. Trocado por predicado `>=1` com FP 0 medido. No caminho achei 2 defeitos em main que nenhum gate via — um crash no `--json` e o C1 nunca sair no modo TEXTO que o CI roda — e ratifiquei a ADR 0401, que bloqueava 6 PRs de tres sessoes."
prs: [7399, 7406, 7409, 7413, 7424]
decided_by: [W]
related_adrs: [0257-adr-status-lifecycle-kind-modelo-canonico, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0344-two-strikes-cobre-processo]
next_steps:
  - "O C1 agora acusa 8 casos REAIS que antes eram invisiveis (o bloco so existia no `--json`, e a lane roda o modo texto). Eles aparecem forward-only, quando alguem tocar o doc. Os 8 precisam de redacao nova pelo autor — `movido`, `parcialmente movido` ou `removido` mesmo. Lista no corpo deste handoff."
  - "PROMOCAO A REQUIRED do `mudou_de_casa` continua NAO recomendada, mas por razao NOVA. A antiga era `margem assimetrica do limiar`; essa morreu junto com o limiar. A que vale agora e o contador `nao_resolvidos` — 117 pares em 35 linhas, das quais 8 sem medicao alguma. `0 acusacoes` com 8 linhas nao medidas nao e `tudo medido` (LC-33)."
  - "NAO re-propor limiar de fracao para este audit, em nenhuma variante (0.5, centro do vao, calibrado por corpus). Medido 2x — a fracao mede QUANTO sobreviveu, a pergunta e SE existe conteudo vivo em outro path. Ela reprova caso coerente (0.188 com 94% num dir) e aprova caso espalhado (0.991 com 49 dirs)."
  - "RESIDUO herdado, nao tocado — o extrator so reconhece paths com prefixo conhecido do repo. Vem do #7392 e segue valido."
  - "Rodar o checklist MCP-first ao abrir sessao nova. NAO pode ser rodado aqui — o servidor `oimpresso` recusa o header de auth (HTTP 401). Este handoff nao carrega snapshot de cycles/my-work."
---

# O limiar nao era um numero errado — era a metrica errada

## TL;DR

Vim para calibrar um limiar e descobri que ele nao deveria existir. No caminho, dois defeitos em main que nenhum gate enxergava, e um bloqueio de repo que travava 6 PRs.

## O que aconteceu, em ordem

### 1. Ratifiquei a ADR 0401 — ela travava 6 PRs de tres sessoes (#7406)

`Governance Gate` (required) estava vermelho em main desde 12:40:48Z. Causa medida com `memory-health.mjs` local: `[L] ADR com status proposto/rascunho JA citada por codigo que roda -> 0401`. Entrou pelo #7394 com `status: proposto`.

**Nao era decisao pendente — era metadata que ficou para tras.** A ADR tem secao `## Decisao` com E1/E2, frontmatter `decided_by: [W]` + `decided_at`, e o #7394 **foi mergeado pela propria [W]**. CLAUDE.md: *merge [W] = ato*. Caso exato da excecao 0257.

Ia **perguntar** qual saida [W] preferia; o hook `block-askq-execution-menu` barrou — era LC-28, devolver decisao que era minha. Apurei o fato e agi. Recibo: `memory-health` de 1 🔴 / exit 1 para 0 🔴 / exit 0.

### 2. Calibrei o limiar — e errei feio (#7399)

Conclui que *"0.5 separa as 92 com 0 erro"*, achando um vao em `(0.455, 0.705)`.

**Era TAUTOLOGICO.** Rotulei as 84 amostras como negativas **porque sao o que o gate nao acusa**, e usei isso para validar o gate. E a lapide §5 2026-07-17 (drift-sentinel) na veia — *quando a distribuicao nao discrimina, o baseline nao e o problema, o MEDIDOR e*.

**Quem me pegou foi a sessao irma**, que sugeriu abolir o limiar por achar que nao haveria separacao. Fui medir para responder e a medicao deu razao a ela — por uma razao mais forte do que a dela: ha separacao, mas em `0 x >0`, nao em torno de fracao nenhuma.

O #7399 foi reescrito com a errata, mantendo os **dois commits** (o errado e a correcao) para a trajetoria nao sumir.

### 3. Re-medi com o extrator novo — e achei um crash (#7409)

O #7402 corrigiu o `pointersOf2`; re-medi contra ele. Numeros mudaram (84->83 medidos, 76->75 zero), **conclusao nao**.

Segunda errata minha, de denominador: eu varria `memory/requisitos` **mais** os `.charter.md` de `Pages/`, e o `--todos` varre so `requisitosDocs()`. Universo maior que o do consumidor e §5 2026-07-27.

Ao rodar o gate para conferir, ele **crashou**. O #7402 trocou o retorno de `auditMudouDeCasa` para `{achados, naoResolvidos}` e deixou os dois early-returns devolvendo `[]` — que e truthy, entao o guard nao pega. **Caso comum**: todo PR que nao toca `memory/requisitos`. Passou no CI porque a lane roda o modo texto, que nao le aquele campo.

### 4. Troquei o limiar pelo predicado (#7413)

| predicado | acusa |
|---|---|
| fracao >= 0.5 | **0** de 83 |
| **`>=1`** | **8** de 83 |

Os 8 sao **verdadeiros, FP 0** — em todos o conteudo vive sob `prototipo-ui/cowork/Wagner`, com concentracao de **13% a 100%**. Essa faixa decidiu o desenho: em vez de outro numero redondo, o achado **carrega** `sobrevivem/total` + `destino_dirs` + `destino_concentracao`, e quem le decide a redacao.

Junto foi o **piso de 200B dentro do tree** ([W] pediu os dois). Medido: nao muda nada hoje. Entra como consequencia — com fracao, 1 blob vazio em 96 nao movia a agulha; com `>=1`, um trivial que colida decide sozinho.

E o achado que fecha o circulo: **o C1 nunca saiu no modo TEXTO**, que e o que a lane roda. O audit achava e nao contava a ninguem. Com `>=1` seriam 8 achados reais invisiveis.

### 5. O fix do crash tinha um fail-open dentro (#7424)

A sessao irma achou, mediu e **nao mexeu no arquivo** (§5 2026-09-05) — avisou. Reproduzi antes de aceitar.

Defeito MEU, do #7409. Ao consertar o crash olhei so a FORMA do retorno e nao o SIGNIFICADO, que difere nos dois early-returns:

| early-return | significado | `medido: true` estava |
|---|---|---|
| `!docs.length` | **medi**, o universo e que estava vazio | correto |
| `!vivos.size` | **nao medi** — sem indice nao ha com o que comparar | mentira |

Devolver o mesmo objeto nos dois fazia o texto imprimir `✓ nenhum.` tendo percorrido ZERO. Nao e hipotetico: `blobsVivosEmMain` e `sh('git ls-tree -r origin/main')` e o `sh()` engole stderr — ref ausente (fetch parcial, depth curto) da Map vazio e check verde. Com o predicado `>=1` do #7413 o custo subiu: os 8 achados reais virariam silencio verde.

Fix: `return null`, o idioma que o `docsDoDiffC1` ja usava. Bite-test `(e6)` com fixture SEM `refs/remotes/origin/main` + `--todos` — a unica combinacao que chega ao `!vivos.size` (sem `--todos` cairia antes no `!base`, dando `medido:false` pelo motivo errado).

⚠️ **DIVIDA MINHA, nao paga:** agora ha DOIS caminhos de `null` e os dois imprimem a MESMA mensagem (`sem base pra comparar`), que so descreve um deles — no outro a base existe, falta o indice, e os dois se consertam diferente. O campo `mudou_de_casa_nao_medido_motivo` que a sessao irma tinha proposto no #7426 resolvia isso; o PR foi fechado em favor do meu e o campo se perdeu. Fica como follow-up para o proximo toque no arquivo.

## Os 8 achados que passam a aparecer

Forward-only — so quando alguem tocar o doc:

| doc | sobreviventes | dirs | conc |
|---|---|---|---|
| `Sells/index-r1-visual-comparison.md:58` e `:364` | 18/96 | 2 | 94% |
| `Sells/Sells-r4-cowork-kb975-*.md:20` e `:103` | 36/178 | 6 | 63% |
| `Sells/Sells-prototipo-vs-prod-*.md:111` | 36/178 | 6 | 63% |
| `KB/CHANGELOG.md:141` | 4/10 | 1 | 100% |
| `_DesignSystem/CHANGELOG.md:487` | 4/15 | 1 | 100% |
| `_DesignSystem/adr/ui/0012-*.md:38` | 15/33 | 8 | 13% |

## O que quase virou um "provado" falso

A 1a mutacao do 3o eixo do bite-test **nao aplicou** — o regex nao casou — e o teste saiu **verde**. Eu ia dar por provado. So peguei porque fui conferir se a mutacao tinha de fato alterado o arquivo (contagem antes/depois). Refeita com replace literal, o assert caiu como devia.

**Mutacao que nao aplica nao prova nada, e o modo de falha dela e sair verde** (§5 2026-08-01).

## Estado MCP no momento do fechamento

⚠️ **NAO consultado** — o servidor `oimpresso` recusa o header de auth (`HTTP 401 / AUTH_HEADER_REJECTED`), e o `laravel-boost` fecha a conexao. `cycles-active`, `my-work` e `sessions-recent` **nao foram rodados**; isto e ausencia de medicao, nao "nada a reportar" (LC-33).

Fallback usado, com o que ele de fato responde:

| pergunta | fonte | resultado |
|---|---|---|
| handoffs de hoje | `ls memory/handoffs/2026-09-16-*` | 2 — nenhum cobre este trabalho |
| ADRs novas hoje | `git log --diff-filter=A -- memory/decisions/` | 0 criadas (a 0401 foi so ratificada) |
| tip de main | `git log -1 origin/main` | `1654526065d` (#7413) |

## Recibos

| PR | o que | merge |
|---|---|---|
| [#7406](https://github.com/wagnerra23/oimpresso.com/pull/7406) | ratifica ADR 0401 — destrava 6 PRs | 13:13:50Z |
| [#7399](https://github.com/wagnerra23/oimpresso.com/pull/7399) | calibracao + errata da conclusao tautologica | 13:26:51Z |
| [#7409](https://github.com/wagnerra23/oimpresso.com/pull/7409) | crash do `--json` + re-medicao | 13:45:26Z |
| [#7413](https://github.com/wagnerra23/oimpresso.com/pull/7413) | predicado `>=1` + piso em tree + C1 no texto | 14:12:25Z |
| [#7424](https://github.com/wagnerra23/oimpresso.com/pull/7424) | fail-open do `!vivos.size` (achado da sessao irma) | 14:42:01Z |

Coordenacao com a sessao irma (`claude/c1-destino-e-sufixo`) por mensagem direta o tempo todo — ela assumiu a 2a familia do GT-G5 a pedido da [W] e eu parei naquele eixo na hora (§5 2026-09-05, dono-e-sessao-viva). Passei a ela o que ja tinha medido para nao refazer, e avisei quando o #7413 mexeu em funcoes dela.
