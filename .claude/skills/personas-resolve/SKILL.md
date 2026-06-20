---
name: personas-resolve
description: BLOQUEADOR Tier A — ATIVAR ANTES de qualquer Edit/Write/MultiEdit em arquivos de `resources/js/Pages/**/*.tsx` ou criação de tela nova. Resolve a(s) persona(s) alvo da tela em 3 níveis de fallback (1) charter da tela `<Tela>.charter.md` campo `personas_alvo`; (2) mapping módulo em `memory/requisitos/_DesignSystem/personas-por-modulo.yml`; (3) pergunta interativa ao Wagner. Carrega persona YAML completa de `memory/clientes/<cliente>/personas/<slug>.yml` no contexto antes de codar. Sem persona resolvida = NÃO procede com edit visual. Reforça ADR UI-0016 (design contextualizado por persona). NUNCA usar persona hipotética sem cliente real (ADR 0105).
tier: B
---

# personas-resolve — Auto-load persona alvo da tela (Tier A bloqueador)

Quando ativar (auto-trigger description matches):
- Edit/Write/MultiEdit em `resources/js/Pages/**/*.tsx`
- Criação de tela nova (file não existe ainda)
- Wagner pede "cria tela X" / "edita tela Y" / "refator visual tela Z"

NÃO ativar pra:
- Edit em `_components/`, `_form/`, `_show/` (componente isolado — herda persona da tela pai)
- Edit em `Components/ui/*` (shadcn — universal, sem persona)
- Edit em scripts / migrations / controllers / models (não-visual)

## Workflow de resolução (3 níveis)

### Nível 1 — Charter declara persona

Procurar `<Tela>.charter.md` ao lado do `.tsx` editado. Se existir campo:

```yaml
---
personas_alvo:
  - daniela-martinho                    # primary (peso maior)
  - jair-martinho                       # secondary
job_principal: "registrar entrada OS em ≤45s"
fricoes_conhecidas:
  - daniela: "4 tabs até fotos do veículo"
---
```

Use as personas declaradas em ordem. Carregar YAML de cada via `memory/clientes/<cliente-real>/personas/<slug>.yml`.

### Nível 2 — Mapping módulo (fallback se charter sem campo)

Ler `memory/requisitos/_DesignSystem/personas-por-modulo.yml`. Cada módulo tem:

```yaml
sells:
  primary: larissa-rota-livre
  secondary: [daniela-martinho]
```

Pegar `primary` do módulo da tela (ex: `Pages/Sells/Create.tsx` → módulo `sells` → Larissa).

### Nível 3 — Pergunta interativa (fallback se nada acima resolveu)

Mostrar pergunta ao Wagner:

```
Vou editar/criar resources/js/Pages/<Mod>/<Tela>.tsx mas não encontrei persona declarada.

Persona alvo principal?
  1) Larissa (Rota Livre — dona-balconista vestuário)
  2) Daniela (Martinho — gerente operacional oficina)
  3) Jair (Martinho — dono, dashboards)
  4) Kamila (Martinho — admin/fiscal, NF-e + cobrança)
  5) Outra (especifique)
  6) Universal (sem persona específica — raro)
```

Após resposta, **SALVAR no charter** (cria se não existe). Próxima vez não pergunta de novo.

## Anúncio antes de codar

Após resolver, **anunciar pra Wagner**:

```
🎯 Vou codar pra:
  PRIMARY: <persona> (<papel> — <cliente real>)
  SECONDARY: [<persona2>, ...]

Top 3 dimensões priorizadas (framework 15D):
  - <dim> (peso N)
  - <dim> (peso N)
  - <dim> (peso N)

Top JOB: <job_principal>
Fricções conhecidas: <fricoes_conhecidas>

Confirma persona? (s = procedo / outra = trocar / -- = sem persona, faz genérico)
```

Se Wagner confirma com `s` → procede. Se trocar → re-load. Se `--` → procede como genérico (excepcional, perde a vantagem contextual).

## Princípios duros

- **Bloqueador Tier A** — sem persona declarada/resolvida não procede em Pages/
- **Anúncio obrigatório** — Wagner precisa ver persona-alvo ANTES do diff, não depois
- **Charter wins** — declaração explícita no charter sempre prevalece sobre mapping módulo
- **Salvar no charter pós-pergunta** — append-only, não pergunta de novo
- **ADR 0105** — só persona com cliente real paga + reportou
- **ADR UI-0016** — design contextualizado é canon, não improviso

## Integração com outras skills

- **`charter-first`** (Tier A já existe) — esta skill EXTENDE com resolução de persona
- **`charter-write`** — se nível 3 disparou pergunta, append persona resolvida ao charter
- **`design-deep-analysis`** (Tier B) — depois de Wagner confirmar persona, pode rodar análise profunda
- **`mwart-process`** (Tier A) — durante MWART F3 (frontend), usar persona como input
- **`cliente-discovery`** — se persona não existe ainda, sugerir rodar discovery primeiro

## Anti-patterns

❌ Codar tela sem ter declarado/resolvido persona = improviso disfarçado
❌ Pular anúncio "vou codar pra X" → Wagner não sabe contexto da decisão
❌ Inventar persona hipotética sem cliente real (= ficção, ADR 0105 viola)
❌ Ignorar fricoes_conhecidas do charter (= refator regenera fricção velha)
❌ Usar peso default sem checar `pesos_override` do YAML persona

## Output esperado

Sempre que skill ativa, contexto do turno tem:

- 1+ personas YAML completas carregadas
- JTBD principal da tela
- Top 5 fricções persona (combinadas de charter + persona.fricoes)
- Pesos 15D ponderados (override do YAML > default módulo > default papel)

Daí Claude codifica decisões justificadas. Cada commit pode citar "essa escolha foi pra resolver fricção X da Daniela".
