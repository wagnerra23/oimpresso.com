# ADR 0028 — ADRs com numeração monotônica e formato Nygard

**Status:** ✅ Aceita
**Data decisão:** 2026-04-26
**Autor:** Wagner
**Registrado por:** Claude (sessão `dazzling-lichterman-e59b61`)
**Relacionado:** ADR 0010 (sistema de memória), ADR 0027 (gestão de memória)

---

## Contexto

Inventário em 2026-04-26 revelou:

- **ADR 0024 duplicado:** existem `0024-instalacao-1-clique-modulos.md` E `0024-padrao-inertia-react-ultimatepos.md`. Quem chega depois não sabe qual é o "verdadeiro" 0024.
- **ADR 0012 ausente:** numeração pulou de 0011 → 0013. Causa esquecida.
- ADRs por módulo (`memory/requisitos/{Mod}/adr/{arq,tech,ui}/NNNN.md`) usam **numeração separada por categoria** — convenção diferente da raiz, válida e registrada em `feedback_adr_separados_por_categoria.md`.

Sem regra explícita, o próximo agente vai adivinhar.

## Decisão

**ADRs em `memory/decisions/` (raiz do projeto, decisões transversais):**
- Formato Nygard: Status + Data + Autor + Contexto + Decisão + Consequências + Alternativas
- Numeração **monotônica crescente sem buracos**: NNNN-slug-kebab.md (4 dígitos, padding zero)
- Slug em PT-BR, kebab-case, descritivo
- Buracos históricos (0012) **não são preenchidos** — fica como evidência do passado
- Duplicatas resolvem-se renomeando a mais recente pro próximo número livre

**ADRs por módulo (`memory/requisitos/{Mod}/adr/{arq,tech,ui}/`):**
- Numeração **independente por categoria** (ARQ-0001, TECH-0001, UI-0001 podem coexistir)
- Mesma estrutura Nygard
- Decisão local do módulo, não transversal

**Quando subir um ADR de módulo pra raiz:** se a decisão extrapolar o módulo (ex.: convenção de tenancy híbrida), deve haver ADR raiz que aponte pro ADR de módulo (não duplicar conteúdo).

## Ações imediatas

1. Renomear `0024-instalacao-1-clique-modulos.md` → mantém (foi o primeiro 0024)
2. ✅ **Executado em 2026-04-27** — `0024-padrao-inertia-react-ultimatepos.md` renomeado para `0029-padrao-inertia-react-ultimatepos.md`. Referências cruzadas atualizadas em: `memory/sessions/2026-04-25-maratona-financeiro.md`, `memory/sessions/2026-04-26-deploy-hero-fix-e-conflitos-memoria.md`, `memory/requisitos/Financeiro/PLANO_DETALHADO.md`, `memory/requisitos/Financeiro/DOC_TELAS_E_SCORE.md`, e 5 arquivos em `Modules/Financeiro/Http/`.

## Consequências

✅ Numeração determinística — agente sabe qual é o próximo ADR (last+1).
✅ Duplicatas detectáveis com `ls memory/decisions/ | sort | uniq -c -w 4`.
✅ ADRs de módulo independentes evitam acoplamento desnecessário com a raiz.

## Alternativas consideradas

- **UUIDs em vez de NNNN:** rejeitado — não-humano, perde ordenação cronológica.
- **Datas em vez de número:** rejeitado — múltiplos ADRs no mesmo dia ficariam ambíguos.
- **Numeração compartilhada entre raiz e módulos:** rejeitado — Wagner já validou separação por categoria em 2026-04-24 (`feedback_adr_separados_por_categoria.md`).
