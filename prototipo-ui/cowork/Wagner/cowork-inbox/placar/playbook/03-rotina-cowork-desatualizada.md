---
sessao: "03"
titulo: COWORK-ESTRUTURA-E-TELAS.md — 3 regras mortas saem da ROTINA
dono: "[CL]"
base: 701f40c6ec66
prefixo: memory/reference/prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md
nao_toca: scripts/** · .github/** · prototipo-ui/**
---
# 03 · A rotina do Cowork contradiz o repo

## Por quê
É o **1º documento** do read-order de toda sessão de design. Lido no `main` em 2026-09-22, ele afirma três coisas que o próprio repo já derrubou. Quem segue o documento erra, e foi o que o Cowork fez nos últimos ciclos.

| o doc diz (ROTINA §4 e "A máquina que protege isso") | o repo faz hoje | fonte |
|---|---|---|
| "AO FECHAR O CICLO, REGENERE O PACOTE" com `gerar-payload-partes`, e "é você, não uma máquina do repo" | o pacote é gerado no CI a cada push em `cowork/Wagner/**` | `.github/workflows/cowork-bundle.yml` |
| "`.md` não viaja no pacote" / o guard "dá erro se `.md` no `cowork/`" | `.md` é permitido dentro de um dono (R3, decisão [W] 2026-09-13) | `scripts/governance/cowork-ssot-guard.mjs` (cabeçalho R3) |
| o loop é "Você exporta → cowork/", sem dizer como | a descida é o zip do projeto importado pelo Code, com `/PURGE` | `scripts/design-sync/receber-handoff.mjs --zip … --conta w [--apply]` |

## O que muda (só este arquivo)
1. **ROTINA §4** vira: *"Ao fechar o ciclo, avise [W] para baixar o zip do projeto. O Code importa com `receber-handoff --zip <arq> --conta w` (mede) e, se passar, com `--apply` + PR. O pacote em partes é gerado no CI (`cowork-bundle.yml`). O import sincroniza com `/PURGE`: o que não estiver no zip some do espelho."*
2. **"A máquina que protege isso"**: trocar a lista de erros pelas regras atuais do guard (R1 raiz · R2 donos · R3 `.md` só dentro de um dono · R4 zero bytes duplicados por dono).
3. **Tabela "Roteamento"**: a linha *"process docs … já são canon em `prototipo-ui/` root"* passa a apontar `memory/reference/prototipo-ui/` (ADR 0397 D3).
4. Rodapé: 1 linha datada dizendo o que mudou e por quê. **Não apagar** o histórico do rodapé.

## PARAR SE
- Alguma afirmação da tabela acima não bater com o `main` no seu turno: corrigir só o que bater e listar o resto no `_saida`.

## Prova
- O doc contém `cowork-bundle.yml` e `receber-handoff`, e não contém mais a frase "é você, e não uma máquina do repo".
- `_saida-03.md`.
