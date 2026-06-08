---
slug: 0238-soberania-constituicao-wagner
number: 238
title: "Soberania de [W] sobre a constituição — modificação autorizada e versionada"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
proposed_at: "2026-05-30"
decided_at: "2026-05-31"
module: governance
quarter: 2026-Q2
tier: CANON
trust_level: tier-0-irrevogavel
tags: [governance, constituicao, soberania, append-only, tier-0, claude-design, autoria]
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0237-jana-reconcile-loop-unico
related_adrs: [0003, 0028, 0094, "UI-0013", 0061, 0236]
parent_charter: mission.constituicao-v2
supersedes: []
authors: [wagner, sonnet, claude-code]
---

# ADR 0238 — Soberania de [W] sobre a constituição

> **Status:** ✅ **AUTORIZADA por Wagner em 2026-05-31.** Proposta por [CC] (Cowork) como
> `_PROPOSTA-constituicao-soberania-W.md`; **numerada (0238 · monotônico ADR 0028) e portada pro git
> pelo [CL] Claude Code** em 2026-05-30 (número livre verificado em `origin/main`; topo era 0237).
> **Pendente:** merge em `main` pelo [W] — é o ato de versionamento final (publication-policy / R10).
> A constituição pertence a [W]; [CC] jamais a edita, [CL] só numera/commita sob OK de [W].

---

## Contexto

A constituição (ADR 0094 · ADR UI-0013 · `prototipo-ui/PROTOCOL.md` · `prototipo-ui/CLAUDE_DESIGN_BRIEFING.md`)
é a **perspectiva que define como tudo no diretório deve ser** — ela é *upstream* do índice e da IA,
que são **derivados** dela. Por isso uma mudança constitucional não é editar um arquivo: é
**reindexar o ecossistema inteiro** sob nova lente.

Numa sessão de design (2026-05-30), [CC] extrapolou: redigiu uma "constituição" própria
(`CONSTITUICAO.md`) e **deletou** outro registro — ferindo a autoria de quem fundou o projeto e a
regra append-only (ADR 0003). Wagner corrigiu o rumo (a `CONSTITUICAO.md` virou lápide → `CARTA_DESIGN_CC.md`
subordinada) e fixou o princípio abaixo. Esta ADR honra essa correção e a torna invariante.

## Decisão

A constituição é **soberania exclusiva de [W] (Wagner)**, seu autor e mantenedor. Adotam-se
quatro princípios duros, na linha da Constituição v2 (ADR 0094):

1. **Autoria respeitada** — a constituição tem dono: [W]. Quem opera dentro dela (agentes,
   colaboradores) **serve** a ela; não a reescreve. Respeito a quem criou é cláusula, não cortesia.
2. **Modificação só por [W]** — alterar ADR 0094, UI-0013, PROTOCOL.md, BRIEFING ou qualquer
   registro constitucional exige **autorização explícita + versionamento** de [W]. Sem isso, é nula.
3. **Mudança = reindexação** — mudar a constituição implica reindexar todo o diretório sob a nova
   perspectiva. Todo índice **declara sob qual versão constitucional foi gerado** (ver ADR 0236).
4. **Append-only inviolável (ADR 0003)** — registro nunca se deleta; superado vira lápide +
   índice atualizado. Erro de agente aqui é **violação**, não deslize.

## Responsabilidades

| Papel | Sobre a constituição | Pode | Não pode |
|---|---|---|---|
| **[W] Wagner** | **Soberano / autor** | aprovar, versionar, reindexar, revogar | — |
| **[CC] Design** | servo | **propor** (F0), aplicar dentro dela, sinalizar drift | criar, editar, numerar, deletar, versionar |
| **[CL] Code** | executor | numerar (monotônico ADR 0028), commitar **sob OK de [W]**, abrir PR | mergear sem [W] |
| **[CD]/[CA]** | servos | criticar, auditar | alterar a lei |

## Onde NÃO inventar (Tier 0 — proibido sem autorização de [W])

- Qualquer ADR/registro constitucional (ADR 0094, UI-0013, PROTOCOL, BRIEFING).
- A numeração monotônica (ADR 0028) — só o Code atribui, sob OK de [W]. **[CC] nunca cunha número do git**
  (lição direta do incidente 0200/0201 cunhados em colisão com Contacts/SEFAZ — ver ADR 0236).
- A perspectiva de indexação — reindexar é ato soberano.

## Consequências

- **Positiva:** a lei do projeto fica protegida de agente; autoria de [W] preservada; nada some.
- **Negativa:** [CC] perde autonomia sobre estrutura — de propósito.
- **Mitigação:** [CC] já se vinculou em `CARTA_DESIGN_CC.md §0.1/§0.2` + `LICOES_CC L-07/L-08`;
  auditável pelo `DesignDocsFreshnessChecker` (ADR 0236 máquina-4) + o loop `jana:reconcile` (ADR 0237).

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-06 | Wagner (aprovação) + Sonnet (rascunho) | ADR 0094 — Constituição v2 (a lei que esta serve) |
| 2026-05-30 | [CC] | overstep: "constituição" própria + delete (corrigido: lápide + L-07/L-08) |
| 2026-05-31 | **Wagner (autoriza)** + [CC] (propõe) | esta decisão — soberania de [W] sobre a constituição |
| 2026-05-30 | [CL] Code | numera 0238 (monotônico) + porta proposta `_PROPOSTA-…W.md` pro git · aguarda merge [W] |
