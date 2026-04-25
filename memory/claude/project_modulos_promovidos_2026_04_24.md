---
name: 4 módulos promovidos para SPEC formal em 2026-04-24
description: Financeiro, NfeBrasil, RecurringBilling, LaravelAI saíram de _Ideias/ e viraram requisitos/{Modulo}/ com README+SPEC+ARCHITECTURE+GLOSSARY+CHANGELOG+ADRs separados por categoria
type: project
originSessionId: dbbb392d-952f-4d8d-9a4a-c93f6603c171
---
Em 2026-04-24, 4 ideias de `memory/requisitos/_Ideias/` foram promovidas para SPECs formais em `memory/requisitos/{Modulo}/` com estrutura completa (README + SPEC + ARCHITECTURE + GLOSSARY + CHANGELOG + ADRs separados por arq/tech/ui).

**Why:** Wagner pediu "crie os modulos e amadureça com profundo conhecimento do ultimatepos. Faça a melhor frase para o melhor faturamento". Validou pattern de ADRs separados ("acho bom separar em adrs os assuntos"). Aprovou continuar com os 4 ("acho todos benvindo").

**How to apply:**
- Status `spec-ready` em `memory/requisitos/{Modulo}/`. Próximo passo é scaffold de código (não feito ainda).
- Cada módulo tem **frase de posicionamento** + **revenue thesis** no README (Financeiro tier 1A R$ 199-599 + take rate 0,5%; NfeBrasil tier 1B R$ 99-599; RecurringBilling tier 2 R$ 149-999 + take rate 0,8%; LaravelAI tier 3 add-on R$ 199-599).
- ADRs seguem padrão `adr/arq/`, `adr/tech/`, `adr/ui/` com numeração separada por categoria (ARQ-0001, TECH-0001, UI-0001) — NÃO monolítico.
- `_Roadmap_Faturamento.md` em `memory/requisitos/` tem build sequence 24 meses; precisa reconciliação com meta R$ 5mi/ano (ver `project_meta_5mi_ano.md`).
- `_Ideias/{X}/README.md` viraram tombstones apontando pras novas pastas; conteúdo histórico mantido em `evidencias/`.
- INDEX.md atualizado com nova categoria 🚀 spec-ready.
- Escrita feita no main worktree (`D:/oimpresso.com`) branch `6.7-bootstrap` (não no worktree do Claude). Wagner ainda não comitou.

**Conexões:**
- Origem: conversas Claude mobile importadas via `_Ideias/_tools/import_claude_export.py` (97 conversas no ZIP)
- Meta: aproxima de `project_meta_5mi_ano.md` mas precisa diversificar (ROTA LIVRE 99%)
- Pattern de ADRs separados validado em `feedback_adr_separados_por_categoria.md`
