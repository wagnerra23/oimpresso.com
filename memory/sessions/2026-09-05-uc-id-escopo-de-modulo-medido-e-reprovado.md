---
date: "2026-09-05"
topic: "Escopo de modulo no UC-id: medido (100% de FP, 0 bugs capturados) e reprovado — virou lapide, nao gate"
authors: ["C"]
prs: [6828]
outcomes:
  - "Lapide §5 2026-09-05 + LC-08 ocorrencia 139"
  - "Fecha a delegacao da lapide 2026-09-04 (PR 6812)"
  - "Unicidade de UC-id fica aberta: FP 0% medido, nao armada (ADR 0344 two-strikes)"
related_adrs: ["0264-governanca-executavel-trio-dominio-e2e", "0344-two-strikes-cobre-processo"]
---

# Sessão 2026-09-05 — Escopo de módulo no UC-id: medido, reprovado, virou lápide

## O pedido

Chip de medição aberto depois do [#6785](https://github.com/wagnerra23/oimpresso.com/pull/6785): *medir o falso-positivo de dar escopo de módulo ao UC-id, e só então decidir se vale mexer nas 3 camadas que o consomem*. O chip já dizia, na abertura, que podia terminar em "não vale" — e que isso seria resultado legítimo.

Terminou em "não vale", com número.

## O que foi medido

Três coletores independentes (o meu, o do `ciclo-adversary`, e o próprio `casos-coverage-guard.mjs --report`) convergiram nos mesmos totais: **129** `.casos.md` · **875** UC-ids · teto **721** · presos **143** · órfãos **11**.

| Eixo | Resultado |
|---|---|
| Denominador que as 3 camadas consomem | `raizesDePages()` → 129 casos.md / 875 ids — **não** os 187/1020 do repo |
| Colisão dentro do denominador | **0** (bijeção 875/875) |
| Colisão no repo inteiro | 111 ids em >1 arquivo · 103 tocam o denominador · guard×guard **0** |
| FP no G-2 (gate que reprova) | estrito **+371** (42,9% dos 864) · permissivo +98 · case-insensitive +34 · mais generoso **+0** |
| FP no teto (advisory) | 304 / 90 / 34 dos 721 |
| População do bug capturada | **0** — FP = **100% dos disparos** |

**Causa estrutural:** `pageNamespacePath` não é resolvedor de módulo — é de *namespace de tela*. Divergem em 34 UCs: case (`superadmin`/`Superadmin`, `kb`/`KB`, `governance`/`Governance` = 56) e semântica (`team-mcp` é tela da Forja = 18; `Atendimento` é servida pelo Whatsapp = 16). Nenhuma função no repo resolve tela→módulo, e 133 créditos vêm de `tests/Feature`, 52 de `tests/js` — dirs sem módulo no path por desenho.

## Duas correções de premissa

**Qual camada foi enganada.** No pai do #6785, os 4 ids do Ponto estão **só em docblock** do `JornadaWorkflowContratoTest.php` (linhas 17/124/150/207/261); os do Forja estão em título de `it()`. Logo o **G-2 (required) não foi enganado** — foi satisfeito pelo docblock do próprio Ponto; o **G-7 (required) não foi acionado** — o Ponto declarou `Status: 🧪 sem veredito`, e ele só cobra `✅`. Quem foi enganado foi o **teto/`⛓`, que é advisory**. Ficou a um glyph: um `✅` no lugar do `🧪` faria o G-7 carimbar verde com prova do Forja.

**O zero é datado, não estrutural.** A colisão nasceu em [#5456](https://github.com/wagnerra23/oimpresso.com/pull/5456) (08/ago) e morreu no #6785 (04/set) — **27 dias**, sem máquina detectá-la; quem tropeçou foi um humano notando a ausência dos 4 na fila `⛓`. Reservatório medido hoje: 25 UCs já colidem entre `prototipo-ui` e o denominador com nome de tela diferente (famílias `Patrimonio`×`Board` 8 · `Importacao`×`Index` 7 · `Backup`×`Index` 10), e 23 dos 50 `.casos.md` do protótipo não têm contraparte.

## O que fica aberto

A pergunta **vizinha** — unicidade de UC-id — tem perfil de FP **oposto**: 0% no denominador do guard em 8 pontos históricos (24/jun→03/set, de 11 para 129 casos.md), e teria mordido o #6785 no dia. Dono a estender se um dia valer: `scripts/qa/uc-id-lint.mjs` (checa formato, não unicidade). **Não armado** — 1ª ocorrência, ADR 0344; armar é decisão [W].

## Erros meus nesta sessão (registrados, não escondidos)

1. **`tr -d '^'` colidiu dois SHAs no mesmo diretório de extração** — `47b56f9e0f` e `47b56f9e0f^` viraram o mesmo `j_47b56f9e0fx`, e a "medição do pai" era na verdade a do filho. Pego pelo controle; refeito com dirs distintos, e o veredito inverteu (pai → 0 colisões). Sem isso, teria datado o nascimento da colisão errado.
2. **Não inventariei `scripts/qa/uc-id-lint.mjs`** antes de concluir sobre o tema. A claim ("nenhuma máquina checa unicidade") é verdadeira — ele checa formato —, mas deixar de nomear o dono vizinho é a perna (b) da §5 2026-07-28. Apontado pelo `ciclo-adversary`.
3. **Relatei "três números divergentes" no contador do LC-08** (campo 130 · comentário 137 · hook 126). O `126` era da **base stale** — o worktree estava 120 commits atrás quando o SessionStart rodou. Não havia divergência de critério ali; corrigi antes de virar canon.

## Revisão adversarial

`ciclo-adversary` devolveu **REVIEW**, não APPROVE: nenhum número foi refutado, a **redação** foi. Corrigiu 3 imprecisões (denominador de "103" não declarado; FP medido em 721 quando o G-2 vive em 864; `uc-id-lint.mjs` não inventariado) e fechou 3 eixos que eu não tinha medido — G-2 por conteúdo, manifesto plano, substring sem word-boundary: **todos 0**. A objeção que mudou o texto foi: registrar "população = 0" como estado atemporal fabricaria o próximo caso da §5 2026-09-03 (*lápide preserva o fato do dia, nunca o estado de hoje*).

## Coordenação com a sessão irmã

O [#6812](https://github.com/wagnerra23/oimpresso.com/pull/6812) (`claude/ledger-lc11-casos-guard`, aberto 03:08Z) toca os mesmos 3 arquivos e **delega nominalmente esta medição** a este chip. Não é duplicação: ele registra o defeito (LC-11), este registra a medição da correção proposta (LC-08). A lápide desta sessão reconcilia explicitamente o *"provavelmente o conserto certo"* que ele escreveu. Conflito textual garantido nos 3 arquivos — quem mergear depois mantém **as duas** lápides e re-roda `sec5-derive --write`, nunca editando `proibicoes.md` à mão.

## Referências

- PR: [#6828](https://github.com/wagnerra23/oimpresso.com/pull/6828)
- Lápide: `memory/licoes-rejeitadas.md` §2026-09-05 (derivada para `proibicoes.md` §5)
- Ledger: [LC-08](../LICOES_CODE.md) ocorrência 139
