# ADR 0005 · Rastreabilidade tripla: Fluxo ↔ Tela ↔ Teste

- **Status**: proposed
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude

## Contexto

Documentação densa sem loop de validação morre em 2 semanas. Para o DocVault virar artefato vivo (não tumulário), a spec precisa ser:

1. **Estruturada** — ferramental (parsers, IA, CI) lê sem ambiguidade.
2. **Rastreável** — fluxo de negócio ↔ tela React ↔ teste automatizado ↔ ADR.
3. **Testável** — regras em formato executável, validadas a cada commit.

Hoje temos: user stories (`US-*`), regras (`R-*`), ADRs. Falta o tecido conectivo que garante que **cada tela corresponde a uma story, cada regra a um teste, cada decisão a um ADR** — e que esse tecido é automaticamente verificado.

## Decisão (proposta)

Adotar o modelo **Trace Triangle** acoplando três conceitos via metadados e automação:

### 1. Páginas como entidades de primeira classe

Cada tela React (arquivo em `resources/js/Pages/**/*.tsx`) vira um registro:

```
docs_pages:
  - path         /ponto/espelho
  - component    Ponto/Espelho/Show.tsx
  - module       PontoWr2
  - stories      [US-PONTO-003, US-PONTO-004]
  - rules        [R-PONTO-002, R-PONTO-005]
  - adrs         [0001, 0003]
  - tests        [PontoEspelhoTest::test_render, ...]
  - status       planejada | em-dev | implementada | deprecated
```

A relação é declarada no topo do `.tsx` via comentário `@docvault`:

```tsx
// @docvault
//   tela: /ponto/espelho
//   stories: US-PONTO-003, US-PONTO-004
//   rules: R-PONTO-002, R-PONTO-005
//   adrs: 0001, 0003
//   tests: Modules/PontoWr2/Tests/Feature/PontoEspelhoTest
```

Comando `php artisan docvault:sync-pages` varre arquivos, atualiza `docs_pages`.

### 2. ADRs segmentados por categoria

Subpastas em `adr/` organizam as decisões por escopo:

```
memory/requisitos/{Modulo}/adr/
├── arq/       ← arquiteturais macro (stack, persistência, auth)
├── ui/        ← UX/tela (split view, drawer, shortcut)
├── tech/      ← técnicas pontuais (lib X, flag Y)
```

**Páginas importantes podem ter ADR próprio** — ex.: `ui/0002-intercorrencias-usa-split-view.md` explica por que a tela de Intercorrências tem layout dividido em vez de modal.

### 3. Regras com teste obrigatório (Gherkin executável)

Cada `R-*` em `SPEC.md` tem campo `**Testado em:**` que aponta pra arquivo+método:

```
**Testado em:** `Modules/PontoWr2/Tests/Feature/PontoEspelhoTest::test_totalizador_dia`
```

Comando `php artisan docvault:gen-test R-PONTO-002` lê o bloco Gherkin e gera stub PHPUnit/Pest com `$this->markTestIncomplete('implementar')`. Dev preenche o corpo.

CI roda os testes. Resultado atualiza coluna `tested_in_ci` em `docs_requirements`. Teste verde = regra validada; vermelho/ausente = dashboard marca o módulo em débito.

### 4. Validador de saúde da documentação

Comando `php artisan docvault:validate`:

- Story órfã: `US-*` sem página em `docs_pages` → alerta.
- Regra órfã: `R-*` sem `Testado em` → alerta.
- ADR órfão: referenciado por ninguém → alerta.
- Página sem story: tela React sem `@docvault` → alerta.
- Tela planejada há > 30 dias: não virou "implementada" → alerta.

Relatório impresso + persistido em `docs_validation_runs`. Dashboard mostra **"Score de saúde"** por módulo combinando cobertura (formato) + rastreabilidade (trace) + validação (CI).

## Consequências

**Positivas:**
- Documentação só fica "verde" se realmente corresponde ao código + testes.
- `/docs/modulos/{Mod}` vira verdade operacional, não wiki estática.
- Onboarding: dev clica numa story e chega no arquivo + teste + ADRs.
- Débito fica visível: módulo com 80% das stories sem teste → vermelho.

**Negativas:**
- Exige disciplina: todo `.tsx` novo precisa de `@docvault`.
- Comando `sync-pages` precisa rodar em pre-commit ou CI.
- Código fica mais verboso com os metadados.

**Trade-off consciente:** verbosidade controlada no topo dos arquivos em troca de documentação auto-validada. Um comment de 6 linhas por tela economiza horas de arqueologia.

## Plano de implementação (se aprovado)

- **Commit A** (M): migration `docs_pages` + `docs_validation_runs`; entities; comando `docvault:sync-pages` lendo comments `@docvault`.
- **Commit B** (M): parser de `Testado em:` atualiza `docs_requirements.tested_at`; comando `docvault:validate` com 5 checks.
- **Commit C** (M): comando `docvault:gen-test` gerando stub Pest a partir de Gherkin.
- **Commit D** (S): subpastas `adr/{arq,ui,tech}` no reader + UI separa ADRs por categoria.
- **Commit E** (M): Dashboard ganha coluna "Trace" com score de rastreabilidade.
- **Commit F** (S): piloto em 1 módulo ativo (PontoWr2) — aplicar `@docvault` em 3 telas principais.

## Alternativas consideradas

- **Só Storybook + MDX**: descartado — bom pra componentes, ruim pra fluxos de negócio e regras.
- **Cucumber/Behat puro**: descartado — overhead alto pra CRUD simples; Pest já cobre nosso estilo de teste.
- **Documentação só em PR body**: descartado — some quando a feature é mergeada, não agrega.
- **Swagger/OpenAPI para rotas**: complementar, não substituto. OpenAPI descreve HTTP, não fluxo de negócio nem decisão de UX.
