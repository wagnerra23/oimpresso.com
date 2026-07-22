---
date: "2026-07-22"
time: "1430 BRT"
slug: "ciclo-realocacao-documental-profissional"
tldr: "Ciclo profissional fechado: classificador por três camadas, adversário, executor transacional com rollback, recibo consultável no Git, runbook e piloto real preservando histórico. PR #4676 fundida; PR #4677 publicada."
decided_by: [W]
cycle: null
prs: [4675, 4676, 4677]
us: []
next_steps:
  - "Aguardar as catracas e a fusão da PR #4677; depois realocar apenas lotes pequenos aprovados, nunca o corpus inteiro por mecanismo cego."
related_adrs:
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento"
  - "0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio"
---

# Handoff — ciclo profissional de realocação documental

**Data:** 2026-07-22 14:30 BRT
**Branch:** `codex/document-relocation-executor`
**PRs:** [#4676](https://github.com/wagnerra23/oimpresso.com/pull/4676) e [#4677](https://github.com/wagnerra23/oimpresso.com/pull/4677)
**Escopo:** documentação e governança; zero alteração de valor, estoque, banco ou runtime

## TL;DR

O ciclo classificação → contraprova → movimento → relink → validação → recibo ficou executável e documentado. A PR #4676 integrou a classificação pelas três camadas. A PR #4677 contém o executor transacional, o runbook e o primeiro movimento real.

## Resultado

- Classificador determinístico propõe tipo, dono, ciclo de vida, camada ADR 0334, porta-mãe, destino, confiança e rewrites.
- Adversário read-only impede plano stale, baixa confiança, colisão, traversal, backlinks/âncoras omitidos, links de saída quebrados, alteração de histórico e mudança de artefato gerado.
- Executor é dry-run por padrão; `--apply` exige árvore limpa, usa `git mv`, aplica relink exato, aceita apenas regeneradores conhecidos e faz rollback se qualquer pós-check falhar.
- O hash do plano ignora só o timestamp volátil e canonicaliza as chaves; o mesmo plano semântico mantém a mesma identidade.
- Cada commit automático recebe `Document-Plan-SHA256`, `Document-Base-SHA` e `Document-Move`; `npm run docs:relocation:history` consulta esse rastro sem ledger paralelo.
- O [runbook](../governance/REALOCACAO-DOCUMENTAL.md) foi ligado à porta global `README.md`.

## Piloto real

`memory/COMO_PEDIR_NOVA_TELA_OU_MODULO.md` foi classificado como IA-OS e arquivado em `memory/governance/como-pedir-nova-tela-ou-modulo-historico.md` com `git mv`. O documento recebeu aviso de conteúdo histórico; `GUIA-DO-SISTEMA.md` e `INDEX.md` agora levam ao guia vivo `HOW_TO_ASK_CLAUDE.md`; o mapa derivado foi regenerado pelo próprio `system-map`.

O primeiro apply falhou porque o mapa estava desatualizado e foi revertido integralmente. Após declarar o gerador em allowlist, o mesmo plano foi aprovado e aplicado. `git log --follow` alcança o commit de criação de 2026-04 e `docs:relocation:history` devolve origem, destino, data e SHA.

## Provas

- classificador **4/4**;
- adversário **17/17**;
- executor **8/8**;
- gate-selftest **70/70**;
- documentation-loop, onboarding-paths e system-map verdes;
- movimento reconhecido pelo Git como rename e histórico anterior preservado.

## Limite profissional

O ciclo está fechado; o corpus não foi migrado em massa. Isso é deliberado: documentos históricos, gerados e portas canônicas têm regras diferentes, e a normalização mecânica do legado já foi rejeitada pelo canon. A máquina deve operar em lotes pequenos, revisáveis e reversíveis.

## Estado MCP no fechamento

As tools MCP de estado vivo não estavam expostas neste runtime Codex. O estado foi aferido diretamente no Git/GitHub: #4676 fundida em `main`; #4677 aberta e sincronizada; checks locais acima verdes.
