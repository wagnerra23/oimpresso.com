---
id: requisitos-governanca-programa-ondas-onda-7-paridade-prototipo-7a-inventario-e-metodo
titulo: Onda 7a — Paridade protótipo↔produção: inventário derivado e método
status: proposto
owner: W
criado: '2026-09-08'
etapa: onda-7-paridade-prototipo
related: ../PLANO-MESTRE.md
---

# Onda 7a — Paridade protótipo↔produção (inventário + método)

> Status vivo deste programa: [PLANO-MESTRE.md](../PLANO-MESTRE.md) §Status vivo (1 plano = 1 registro · ADR 0294).

## Por que existe, e o que NÃO é

[W] 2026-09-08, textual: *"na real todos módulos deveriam estar sincronizado"* e *"minha obrigação
agora é colocar a paridade do protótipo em produção"*. O objetivo é levar **todas** as telas à
paridade com o protótipo Cowork, não uma amostra.

**Não confundir com a [Onda 0d](../onda-0-fundacao/0d-paridade-migracao.md).** Aquela é paridade
**Blade↔React** (a migração preservou função?). Esta é **protótipo↔produção** (a tela renderizada
bate com o design?). Eixos diferentes, artefatos diferentes (`-parity.md` lá, `-visual-comparison.md`
aqui), donos diferentes. Nenhuma substitui a outra.

## Estado medido (2026-09-08)

Tudo abaixo é **derivado por comando**, não escrito à mão — re-rode antes de citar (§5 2026-07-17).

| medida | valor | porta viva |
|---|---|---|
| charters de página | **221** | `node scripts/qa/design-coverage.mjs` |
| com fonte de design declarada | **210 (95%)** | idem |
| silenciosas (sem fonte declarada) | **11 (5%)** | idem |
| `n/a` com fonte candidata JÁ no espelho | **36** (report-only — decisão datada, a fonte desceu depois) | idem |
| páginas `.tsx` sem charter algum | **41** — o gap mais fundo | idem |
| inventários de paridade existentes | **84** | `git ls-files "memory/requisitos/**/*visual-comparison*.md"` |

Pré-condições do protocolo, conferidas nesta data (todas verdes):

| pré-condição | resultado | comando |
|---|---|---|
| espelho do design fresco | bundle 2026-09-07T21:00Z · 281 arquivos · `missing: 0` | `scripts/design-sync/state/active-bundle.json` |
| fundação (tokens DS) sem drift | **0** nos 4 temas (light/dark/cockpit-light/cockpit-dark) | `node scripts/governance/ds-mirror-drift.mjs` |
| portão fail-closed do preview | **PREVIEW COMPLETO** | `node scripts/governance/cowork-mirror-freshness.mjs --preview-ds` |

⚠️ **O `--sla` reporta um número velho.** Ele diz "157 arquivos existem no VIVO e não estão no
espelho", mas o próprio texto carrega `(medido em 2026-09-01)` — **anterior** ao bundle de 07/09.
Quem citar esse 157 como estado de hoje está citando um retrato vencido.

## O buraco de método que esta onda precisa fechar primeiro

Não existe hoje porta que responda **"quais telas estão em paridade?"**. O que existe:

- `design-coverage.mjs` responde *"a tela DECLARA de onde vem seu design?"* — cobertura de
  **fonte declarada**, não de paridade medida.
- `design-diff.mjs --probe/--compare` mede paridade **de uma tela por vez**, no runtime.
- Os 84 `-visual-comparison.md` carregam veredito **auto-declarado** (`status:` no frontmatter):
  35 `approved` · 24 sem campo `status` · 8 aguardando [W] · 8 draft/pending · 1 `medido` ·
  1 `divergente`. Auto-declarado apodrece — não prova paridade de hoje.

E o casamento tela↔inventário **não é 1:1**: contando por nome de módulo, `Sells` aparece com 13
inventários para 10 charters. Qualquer fila derivada de `charters − inventários` por módulo é
aproximação grosseira e **não deve virar número de plano**.

**Primeiro entregável desta onda, portanto, é o casamento correto** — e ele se resolve
**estendendo `design-coverage.mjs`** (que já é o dono do eixo design por tela), nunca abrindo
medidor paralelo (LC-19 · §5 2026-07-09 "duplica régua consolidada").

## Método por tela (o que já é canon, não se reinventa)

1. `node scripts/design/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork` — resolve a âncora.
   A porta **per-tela**, nunca o `--list`, que lê metade da regra (§5 2026-08-28).
2. `node scripts/design/design-diff.mjs --probe` nos DOIS lados, **mesma sonda, mesmo tema, mesmo
   navegador** → `--compare prod.json design.json --check`. Medida, nunca olho (PROTOCOLO-COMPARACAO-RUNTIME).
3. Registrar em `memory/requisitos/<Mod>/<Tela>-visual-comparison.md` com veredito **por item**.
4. Verificar cada divergência acusada **antes** de virar trabalho. No piloto (`Compras/Index`,
   [#6951](https://github.com/wagnerra23/oimpresso.com/pull/6951)) foram **4 acusadas → 1 real**:
   duas eram artefato de seletor e de markup do protótipo, uma segue a investigar. **Comparação
   medida é o começo do trabalho, não o veredito.**
5. Aplicar só o que sobreviveu. Onde o protótipo estiver errado, o conserto é **no Cowork**
   (classificar DERIVA), não copiar o defeito para produção.

## Ordem proposta (decisão [W] sobre a fila)

A ordem **não** é técnica — é qual módulo destrava cliente saindo do Delphi. Registrar aqui a
decisão de [W] quando ela sair. Sinal disponível hoje: o ciclo de design de 07/09 tocou
`26 added · 101 modified`, incluindo `app.jsx` (shell) e um bloco novo `forja-*` (15+ arquivos).

## Critério de pronto

- [ ] `design-coverage.mjs` estendido para reportar paridade por tela (casamento 1:1 resolvido)
- [ ] fila derivada publicada, com denominador declarado junto da nota (§5 2026-07-27)
- [ ] cada tela da fila com `-visual-comparison.md` medido no runtime
- [ ] divergências verificadas uma a uma antes de virar trabalho
- [ ] o que for defeito do protótipo volta ao Cowork como DERIVA, não desce para produção

## Não medido, declarado

- Se as 36 telas `n/a`-com-fonte-candidata devem passar a apontar para o protótipo é **decisão
  humana por tela** — a fonte ter descido depois não revoga sozinha uma decisão datada
  (§5 2026-08-28: promover `bundle_source` a âncora em leva é proibido; 5 dos 8 hubs se declaram
  porte REVERSO do código vivo).
- As 41 páginas sem charter são o gap mais fundo e **não** se fecham nesta onda: charter é
  `charter-write` + Non-Goals que só [W] preenche.
