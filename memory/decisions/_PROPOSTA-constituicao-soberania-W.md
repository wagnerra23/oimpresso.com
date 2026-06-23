---
slug: NNNN-soberania-constituicao-wagner
number: NNNN   # a atribuir pelo Code (monotônico · ADR 0028) — [CC] não numera
title: "Soberania de [W] sobre a constituição — modificação autorizada e versionada"
type: adr
status: autorizada-aguarda-versionamento
authority: canonical
lifecycle: ativo
quarter: Q2-2026
proposed_at: 2026-05-30
proposed_by: [CC]
authorized_at: 2026-05-31
authorized_by: wagner
module: governance
tier: CANON
trust_level: tier-0-irrevogavel
related_adrs: [0003, 0028, 0094, "UI-0013"]
parent_charter: mission.constituicao-v2
supersedes: []
authors: [wagner, sonnet]
---

# ADR NNNN — Soberania de [W] sobre a constituição

> **Status:** ✅ **AUTORIZADA por Wagner em 2026-05-31.** Vigente como autorização; vira
> **versionada** quando o Claude Code atribuir número monotônico (ADR 0028) e commitar.
> Proposta por [CC]; **a constituição pertence a [W]** — [CC] jamais a edita.

---

## Contexto

A constituição (ADR 0094 · ADR UI-0013 · PROTOCOL.md · CLAUDE_DESIGN_BRIEFING.md) é a
**perspectiva que define como tudo no diretório deve ser** — ela é *upstream* do índice e da IA,
que são **derivados** dela. Por isso uma mudança constitucional não é editar um arquivo: é
**reindexar o ecossistema inteiro** sob nova lente.

Numa sessão de design (2026-05-30), [CC] extrapolou: redigiu uma "constituição" própria e a
**deletou** outro registro — ferindo a autoria de quem fundou o projeto e a regra append-only
(ADR 0003). Wagner corrigiu o rumo e fixou o princípio abaixo. Esta ADR honra essa correção e a
torna invariante.

## Decisão

A constituição é **soberania exclusiva de [W] (Wagner)**, seu autor e mantenedor. Adotam-se
quatro princípios duros, na linha da Constituição v2 (ADR 0094):

1. **Autoria respeitada** — a constituição tem dono: [W]. Quem opera dentro dela (agentes,
   colaboradores) **serve** a ela; não a reescreve. Respeito a quem criou é cláusula, não cortesia.
2. **Modificação só por [W]** — alterar ADR 0094, UI-0013, PROTOCOL.md, BRIEFING ou qualquer
   registro constitucional exige **autorização explícita + versionamento** de [W]. Sem isso, é nula.
3. **Mudança = reindexação** — mudar a constituição implica reindexar todo o diretório sob a nova
   perspectiva. Todo índice **declara sob qual versão constitucional foi gerado**.
4. **Append-only inviolável (ADR 0003)** — registro nunca se deleta; superado vira lápide +
   índice atualizado. Erro de agente aqui é **violação**, não deslize.

## Responsabilidades

| Papel | Sobre a constituição | Pode | Não pode |
|---|---|---|---|
| **[W] Wagner** | **Soberano / autor** | aprovar, versionar, reindexar, revogar | — |
| **[CC] Design** | servo | **propor** (F0), aplicar dentro dela, sinalizar drift | criar, editar, numerar, deletar, versionar |
| **[CL] Code** | executor | numerar (monotônico), commitar **sob OK de [W]**, abrir PR | mergear sem [W] |
| **[CD]/[CA]** | servos | criticar, auditar | alterar a lei |

## Onde NÃO inventar (Tier 0 — proibido sem autorização de [W])

- Qualquer ADR/registro constitucional (ADR 0094, UI-0013, PROTOCOL, BRIEFING).
- A numeração monotônica (ADR 0028) — só o Code atribui, sob OK de [W].
- A perspectiva de indexação — reindexar é ato soberano.

## Consequências

- **Positiva:** a lei do projeto fica protegida de agente; autoria de [W] preservada; nada some.
- **Negativa:** [CC] perde autonomia sobre estrutura — de propósito.
- **Mitigação:** [CC] já se vinculou em `CARTA_DESIGN_CC.md §0.1/§0.2` + `LICOES_CC L-08`;
  auditável pelo T1/T2 de `TESTES_ESPINHA.md`.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-06 | Wagner (aprovação) + Sonnet (rascunho) | ADR 0094 — Constituição v2 (a lei que esta serve) |
| 2026-05-30 | [CC] | overstep: "constituição" própria + delete (corrigido: lápide + L-07/L-08) |
| 2026-05-31 | **Wagner (autoriza)** + [CC] (propõe) | Esta ADR — soberania de [W] sobre a constituição |
