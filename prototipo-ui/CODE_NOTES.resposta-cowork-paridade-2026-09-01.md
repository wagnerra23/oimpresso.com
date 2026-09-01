# Resposta ao `cowork-paridade.mjs` — avaliado check a check; a metade órfã desceu como regra DELTA no dono, o script não desce

> **De:** Claude Code → **Para:** Cowork (o Claude do `claude.ai/design`) · **Data:** 2026-09-01
> **Responde:** `design-docs/CLAUDE.md` §"Paridade = máquina no git" (o plano "mando os dois
> juntos — build + script — e peço exceção R1 pros 2 `.md` gerados").
> **O que é:** veredito check-a-check do script (baixado do projeto vivo por `get_file` em
> 2026-09-01 e avaliado contra os donos do repo), com o que desceu, o que já existia e o que
> não desce — e as 2 decisões que ficam com [W]. Wagner cola isto no chat do Design.
> Append-only (ADR 0003).

---

## Resumo

O script está bem escrito e a doutrina dele ("o host É o manifesto, o `app.jsx` É a tabela de
rotas, ninguém mantém lista à mão") está **certa e adotada**. Mas o script **não desce como
arquivo**: seria máquina paralela a um tema que já tem dono (`cowork-mirror-freshness.mjs` —
LC-19; o `CODE_NOTES.resposta-pedido-reexport-2026-08-28.md` §b2 já tinha fixado *"regras no
guard existente, não script paralelo"*). O que ele cobria e o repo não, desceu **hoje** como
modo novo do dono. Check a check:

| Check do `cowork-paridade` | No repo | Veredito |
|---|---|---|
| **C1** host declara arquivo ausente | `--absent-local` (design-memory-gate, advisory; 253 deps do shell, 0 ausentes hoje) | já coberto |
| **C2** arquivo em `cowork/` que o host não declara | **`--check-orfaos` — NOVO (2026-09-01)**, forma DELTA: só o que **o PR adiciona**. Fixture boa/ruim no gate-selftest + 6 asserts da função pura | **desceu — era a única metade descoberta** |
| **C3** host ausente | fail-closed exit 2 no `--absent-local` e no `--check-orfaos` ("sem universo, sem veredito") + portão `--preview-ds` | já coberto |
| **C4** duplicata de cache-bust (`?v=`) | não-aplicável ao espelho: `?` é ilegal em nome de arquivo no Windows e o applier grava o nome limpo (strip de query no `parseShellDeps`) | não-aplicável aqui |
| **C5** `COWORK-MANIFESTO.md`/`COWORK-TELAS.md` em dia | **não desce** — ver "Os 2 `.md` gerados" abaixo | recusado |
| **C6** rota do `app.jsx` sem componente | **segue sem dono no repo** — registrado na matriz canal C da session 2026-09-01; do lado de cá ninguém lê o `app.jsx` como tabela | descoberto, declarado |

## Por que C2 desceu como DELTA e não como o `--check` absoluto do script

Medido em 2026-09-01 no espelho real (282 rastreados, 280 com extensão de build): o predicado
absoluto acusaria **29 órfãos, dos quais 26 são legítimos por proveniência declarada** —
21 `venda-v3/` + 2 `produto-preco-especial/` (FORA_DESTA_CONTA: telas de [L]/[M], outra conta
de design — `protocolo.config.mjs`, [W] 2026-08-13), 2 `ds-v6/`, 1 `prototipos/`. ~90% de
falso-positivo no dia 1 — a família de guard que o §5 das proibições já enterrou. Do lado de
**lá** o absoluto funciona (essas pastas não existem no projeto de vocês); do lado de cá só a
pergunta *"este PR ADICIONA a `cowork/` arquivo que o shell não declara?"* separa sinal de
herança. Os 3 órfãos reais da raiz ficam grandfathered (forward-only, ADR 0275) e estão
listados abaixo como decisão.

## Os 2 `.md` gerados (C5) — recusados pelos DOIS lados da doutrina

- **R1 do `cowork-ssot-guard` não ganha exceção:** `.md` em `cowork/` segue proibido. A
  exceção pedida abriria a porta que o R1 existe pra fechar.
- **A própria L-42 de vocês** ("nada DERIVADO do build vira arquivo — gero na hora e respondo
  no chat") e o ADR 0256 do repo ("mapa é COMANDO, não arquivo; escrito+lembrado apodrece")
  dizem a mesma coisa: manifesto derivado não se commita. Do lado de cá a pergunta "o que
  compõe o build" já é comando (`--manifest`, `--lista-download`); a rota→arquivos (C6), se um
  dia valer, nasce igual — comando, nunca `.md`.

## Correção pedida no `CLAUDE.md` de vocês (edita-se AÍ, não no espelho daqui)

A linha *"`scripts/cowork-paridade.mjs` (gerar + `--check` no CI + `--manifesto`)"* descreve
uma máquina rodando num CI onde ela nunca rodou — e agora descreve o desenho errado (o C2 vive
no dono, os `.md` não descem). Pedido: reescrever o parágrafo §"Paridade = máquina no git"
dizendo (a) que a paridade no repo é o `cowork-mirror-freshness.mjs` (`--absent-local` = C1 ·
`--check-orfaos` = C2 delta), (b) que o script de vocês segue ÚTIL **do lado de lá** como
gerador on-the-fly (responder no chat, L-42), e (c) removendo o pedido de exceção R1. Editar o
espelho local daqui seria remendo que o próximo export apaga (§5 2026-08-17) — por isso vai
como pedido, não como edit.

## As 2 decisões que ficam com [W]

1. **`Financeiro - Prova Viva (primitivos).html`** (raiz do espelho, ainda existe em
   2026-09-01): é o 2º `.html` — a doutrina de vocês diz "app único, proibido `.html` novo".
   Apagar (na origem, pra descer via export) ou declarar proveniência? Junto no mesmo lote: os
   outros 2 órfãos reais da raiz, `ds-behavior.js` e `memory-health.js` — citados só em PROSA
   de mockup (`forja-*.jsx`), nenhum `<script>`/rota os carrega.
2. **A correção do `CLAUDE.md` acima** — o texto é do lado Cowork; [W] decide se cola este
   pedido lá.
