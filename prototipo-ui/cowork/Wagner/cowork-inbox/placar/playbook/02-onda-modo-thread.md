---
sessao: "02"
titulo: /onda modo thread — caminho quebrado e índice ausente vira PARAR
dono: "[CL]"
base: 701f40c6ec66
prefixo: .claude/commands/onda.md
nao_toca: scripts/qa/** · scripts/design-sync/pedido.mjs · qualquer playbook
---
# 02 · `/onda` no modo thread

## O que falhou (sessão [CL] de 2026-09-22, relatada por [W])
`/onda Placar --thread 01` montou o caminho `cowork-inbox/--thread/playbook/00-INDICE.md`, que não existe (ENOENT, exit 2). A sessão improvisou e rodou a thread 01 **de todos os 13 módulos**. O pedido era a thread 01 de **um**.

Há três defeitos no `.claude/commands/onda.md` do modo thread (seção "Modo THREAD"):
1. `--thread $NN` usa uma variável `$NN` que o harness não define. Só existem `$1`, `$2`, `$3` e `$ARGUMENTS`.
2. `$1` é inserido cru no caminho, mas os diretórios dos playbooks são **minúsculos** (`hrm`, `ponto`, `placar`). `Placar` não acha nada no Linux.
3. Quando o índice não existe, o comando **não manda parar**. A sessão improvisa, o que é pior que falhar.

## O que muda (só este arquivo)
1. **Argumentos do modo thread:** `$1` = módulo e `$3` = NN (`$2` é o literal `--thread`). Se o harness entregar tudo em `$ARGUMENTS`, parsear dali. Converter o módulo para minúsculas antes de montar o caminho.
2. **Passo 0 obrigatório:** `test -f prototipo-ui/cowork/Wagner/cowork-inbox/<mod>/playbook/00-INDICE.md`. Se falhar, **PARE** e responda só: *"NÃO MEDI: índice `<mod>` ausente no main — o pacote do Cowork não foi importado. Nada executado."* Não rode outro módulo e não escolha thread.
3. O `ls …/$NN-*.md` passa a usar o NN resolvido no item 1.

## PARAR SE
- A correção exigir mudar `placar.mjs` ou `pedido.mjs`: parar e reportar.

## Prova
- `onda.md` não contém `--thread $NN` e contém a instrução `PARE` do passo 0.
- `_saida-02.md` com os dois testes: (a) `/onda placar --thread 01` abre a thread certa; (b) `/onda inexistente --thread 01` para com NÃO MEDI, sem varrer outro módulo.
