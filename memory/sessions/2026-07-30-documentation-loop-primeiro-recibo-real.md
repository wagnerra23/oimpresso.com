---
date: "2026-07-30"
hour: "08:04 BRT"
topic: "Primeiro recibo real do ciclo documental e correção do impacto pré-commit vazio"
authors: [W, C]
outcomes:
  - "ID memory-health:link-quebrado:131e73bd28b3 resolvido pelo mesmo detector"
  - "Links quebrados do índice vivo reduziram 28→26 sem piora"
  - "Mapa de impacto passou a incluir worktree e ganhou --require-clean"
prs: []
us: []
related_adrs:
  - "0130-handoff-append-only-mcp-first"
  - "0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento"
---

# Session log 2026-07-30 — primeiro recibo documental real

## TL;DR

Após o merge do ciclo por impacto, a `main` foi medida em worktree isolado. A primeira correção real
removeu um ID de link quebrado com recibo antes→depois; a própria execução encontrou e corrigiu o
falso `changed_files: []` que ocorria enquanto a alteração ainda não estava commitada.

## Evidência antes→depois

| Medida | Antes | Depois |
|---|---:|---:|
| ID esperado presente | sim | não |
| links quebrados no detector dono | 28 | 26 |
| arquivos vistos pelo impacto pré-commit | 0 | 6 |
| selftest | 8/8 | 10/10 |

O dossiê histórico nunca entrou na `main`; ele existe no commit `586a3bca09` da branch
`claude/jolly-kilby-7b3cd3`. As duas referências duplicadas do índice foram apontadas ao blob
imutável desse commit. Nenhum arquivo histórico foi recriado como se fosse cânone atual.

## Aprendizado

Um comparativo `base...HEAD` não enxerga alteração ainda no worktree. A máquina agora une:

- diff commitado;
- arquivo rastreado alterado;
- arquivo novo não rastreado.

O recibo final usa `--require-clean`: se qualquer arquivo continuar fora do commit, sai com código 1.
O bite-test cria um repositório temporário e prova os dois lados: worktree entra quando o head é
`HEAD`; comparação entre SHAs imutáveis não é contaminada.

## Próximos passos

- Abrir PR com o trailer do ID resolvido.
- Aguardar o CI completo.
- Pós-merge: medir a `main` e confirmar que o ID não reapareceu.

## Referências

- Handoff: [2026-07-30-0804-documentation-loop-primeiro-recibo-real.md](../handoffs/2026-07-30-0804-documentation-loop-primeiro-recibo-real.md)
