---
id: requisitos-project-mgmt-inventario
---

# ⚰️ LÁPIDE — este arquivo não é o inventário da Forja

> **O inventário canônico é [`CAPTERRA-INVENTARIO.md`](CAPTERRA-INVENTARIO.md).** Não edite este arquivo.

## Por que existia, e por que saiu de cena (medido em 2026-08-04)

| Nome | Módulos que usam | Máquinas que referenciam |
|---|---|---|
| `CAPTERRA-INVENTARIO.md` | **11** | skill `comparativo-do-modulo` (8×) + comando `/comparativo` (2×) |
| `INVENTARIO.md` (este) | **1 — só a Forja** | **nenhuma** |

Comandos que produziram os números (re-rode em vez de editar — [proibicoes §5 2026-07-17](../../proibicoes.md)):

```bash
git ls-files 'memory/requisitos/*/CAPTERRA-INVENTARIO.md' | wc -l
git ls-files 'memory/requisitos/*/INVENTARIO.md' | wc -l
grep -rno 'CAPTERRA-INVENTARIO\.md' .claude/skills/comparativo-do-modulo/ .claude/commands/comparativo.md
```

A Forja era o **único** módulo com os dois arquivos, cada um contando uma história diferente do mesmo
módulo. O custo não foi teórico: na sessão de 2026-08-04 uma reauditoria completa (24 capacidades,
9 itens recreditados) foi escrita **neste arquivo órfão** — o que nenhuma máquina lê — enquanto o
`CAPTERRA-INVENTARIO.md`, que a skill lê, seguia afirmando que a Triage estava ausente com a tela
em produção desde junho. **Ambiguidade de nome custou uma reauditoria inteira no arquivo errado.**

## O que foi feito

O conteúdo reauditado foi **movido** para `CAPTERRA-INVENTARIO.md` (que preservou o próprio frontmatter,
com `type: inventario` e `slug`), e os 8 links de entrada vivos — em `SPEC.md`, `CAPTERRA-FICHA.md` e
`CHARTER-board.md` — foram redirecionados. Nada foi perdido; o histórico está no git.

> ⚠️ **Links quebrados que NÃO dá pra consertar:** a [ADR 0100](../../decisions/0100-projectmgmt-ui-redesign.md)
> aponta 3× para `../requisitos/ProjectMgmt/INVENTARIO.md` — caminho que já morreu no rename
> `ProjectMgmt → Forja` (2026-07-30), antes desta consolidação. ADR é **append-only**: fica registrado
> aqui em vez de editado lá.

## Remoção definitiva

Apagar este arquivo é subtração de artefato — decisão [W]. Enquanto a lápide existir, o redirecionamento
está explícito e nenhum agente escreve no lugar errado por engano.
