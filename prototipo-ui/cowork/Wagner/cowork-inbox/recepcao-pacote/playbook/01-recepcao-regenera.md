---
thread: "01"
modulo: recepcao-pacote
dono: "[CL]"
prefixo: ["scripts/design-sync", ".github/workflows"]
depende: ["D-RECEPCAO-FALHA", "D-QUEM-REGENERA"]
base: NADA medido no main neste turno — reconfira todo caminho antes de escrever
---
# 01 · A recepção confere o pacote e falha quando ele mente

## Problema (com o caso concreto)
Em 2026-09-07 o pacote foi gerado: **281 arquivos, 43 partes**. Em 10 e 11/09 o build mudou em pelo menos 7 arquivos (`sidebar.jsx`, `app.jsx`, `styles.css`, `icons.jsx`, `perfil-page.{jsx,css}`, `documentacao-page.{jsx,css}`). O `sync/bundle.manifest.json` **não mudou** — e nenhuma guarda reclamou, porque nenhuma tem esse papel.

O resultado é o pior tipo de defeito: **o CI verde afirmando frescor que não existe**. Quem aplica o pacote acha que aplicou o build atual.

## O que fazer
Um verificador de recepção, chamado pelo `GATE` (nome literal a conferir: `.github/workflows/design-memory-gate.yml`), que:

1. Recebe o **projeto desempacotado** e o `sync/bundle.manifest.json`.
2. Compara **por sha256 por arquivo** e classifica em 4 baldes: `iguais` · `divergentes` · `ausentes_no_manifesto` · `orfaos_no_manifesto`.
3. **Invariante dura:** os 4 baldes somam o total de arquivos do projeto. Não somando → **exit 2** com a lista do que não classificou. Número que não fecha é mais útil que número bonito.
4. Grava recibo (`_saida-01.md`): os 4 contadores + o `bundleId`/`manifestSha256` lidos + a data do manifesto.
5. Severidade e autoria: conforme `D-RECEPCAO-FALHA` e `D-QUEM-REGENERA`. **Enquanto estiverem abertas, implemente só a detecção com exit code configurável** — não faça o bot commitar pacote por conta própria.

## Provas (execução, não estrutura)
- Rodar com projeto × manifesto em **paridade** → exit 0, zero divergentes. (Sem este caso, "achei N divergências" pode ser o detector marcando tudo.)
- **Tocar 1 byte** em um arquivo do projeto e rodar → `divergentes` sobe exatamente 1.
- Rodar contra o estado real de hoje → deve **acusar** a defasagem de 07/09. Se passar verde, o verificador não está lendo o que acha que lê.

## Parar se
- Já existir script com esse papel → **estenda aquele** e reporte; não crie um segundo dono de paridade (a causa-raiz que este pedido combate é justamente paridade sem dono).
- A canonicalização de `bundleId`/`manifestSha256` divergir da do gerador → **pare**: a verdade é a do `GERADOR`, e um verificador com canonicalização própria produz falso-positivo em massa (já aconteceu: o predicado absoluto de órfãos dava ~90% de falso-positivo antes de virar delta).

## NÃO é
Não é regenerar o pacote de hoje (isso é humano, com os arquivos em disco). Não é mexer em `prototipo-ui/cowork/**`. Não é criar exceção pro R1.
