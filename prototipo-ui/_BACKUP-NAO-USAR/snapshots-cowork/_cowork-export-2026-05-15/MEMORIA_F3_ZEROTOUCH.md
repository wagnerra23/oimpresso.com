# Memória — F3 Zero-Touch (validado 2026-05-09)

> Tentativa "outra forma" de F3 que **funcionou de fato**: Wagner cola UM prompt no Claude Code e nada mais.

## O que aprendi

### 1. Não posso commitar — assumir isso de cara
Tools de GitHub aqui são **read-only**. Listar/ler/importar OK. **Nunca** prometer "vou commitar / abrir PR / mergear". Toda entrega que toca repo é via Claude Code.

### 2. Estruturar patches espelhando o repo
Em vez de escrever instruções tipo "cole isso em tal lugar" (anti-pattern — força W a interpretar), eu organizo `prototipo-ui-patch/` com **a mesma árvore do repo**:

```
prototipo-ui-patch/
├── README_F3.md                                          ← contexto
├── Modules/Financeiro/
│   ├── Http/Controllers/UnificadoController.php          ← CREATE
│   └── Routes/web.php.patch.md                           ← EDIT (snippet)
└── resources/js/Pages/Financeiro/Unificado/Index.tsx     ← CREATE
```

Claude Code recebe URLs públicas → `curl -o <path>` direto na estrutura. Zero ambiguidade.

### 3. Antes de escrever TSX/PHP, ler 2 arquivos do repo
- **1 Page existente** (`resources/js/Pages/Financeiro/Dashboard/Index.tsx`) → revela:
  - Layout: `AppShellV2` com `breadcrumbItems`
  - Imports: `@/Components/ui/*` (shadcn) + `@/Components/shared/*` (PageHeader, KpiGrid, KpiCard, StatusBadge)
  - Padrão Inertia: `router.get(url, params, {preserveState, preserveScroll, replace: true})`
  - Tipos: interfaces `Props`, `Filters`, paginated wrapper
  - Header magic comment `// @memcofre tela=... module=... stories=...`
- **1 Routes/web.php** → revela:
  - Middleware stack: `['web','auth','language','timezone','AdminSidebarMenu']`
  - Prefix + name pattern: `prefix('financeiro')->name('financeiro.')`
  - Naming: `unificado.index`, `unificado.baixar`

Pular esses 2 reads = TSX que não compila no repo real.

### 4. URLs públicas ~1h
`get_public_file_url` retorna URL temporária. Se Wagner demorar pra colar, regenero. Sempre dou o conjunto completo de uma vez.

### 5. UM prompt completo, não conversa
Estrutura validada:
```
[CONTEXTO 2 linhas]
[ARQUIVOS A CRIAR + URL pra cada]
[ARQUIVOS A EDITAR + descrição da mudança + URL do snippet]
[COMANDOS git completos: branch + add + commit + push + gh pr create]
[CHECKLIST de validação: composer lint, npm tsc, tests/Feature]
```

Claude Code executa direto. Wagner não toca em nada.

### 6. Anti-patterns que NÃO podem voltar
- ❌ Pedir "qual opção, A ou B?" — Wagner já decidiu o fluxo
- ❌ "Cole esse trecho lá" — interpreta = falha
- ❌ Prometer commit / PR aberto sem ter feito
- ❌ Esquecer disso sob pressão de contexto

### 7. Ordem de produção (template para próxima F3)
1. **Ler protótipo aprovado** no Cowork (Financeiro.html, financeiro-app.jsx, etc).
2. **Ler 2 arquivos do repo:** 1 Page de referência + Routes/web.php do módulo.
3. **Ler `prototipo-ui/CLAUDE_DESIGN_BRIEFING.md §4`** (tokens) — não inventar cor/radius.
4. **Espelhar paths em `prototipo-ui-patch/`** (CREATE = arquivo cheio, EDIT = `.patch.md` com snippet).
5. **Gerar URLs públicas** de cada arquivo.
6. **Escrever README_F3.md** com decisões abertas (DEVE listar pendências de produto, ex.: nome real do model, service existente vs novo, RBAC).
7. **Salvar `MEMORIA_F3_ZEROTOUCH.md`** (este arquivo) caso tenha nova lição.
8. **Entregar UM bloco com prompt completo** pra Claude Code.

### 8. Decisões abertas SEMPRE explicitadas no README
Sem isso, Claude Code chuta nome de model/service/migration e quebra. Lista no README_F3.md sob "Decisões abertas pra [W] confirmar antes de F4". Se Wagner não responde, Code escolhe defaults com TODOs.

## Métricas de sucesso

✅ Wagner cola 1x no Claude Code
✅ Não pergunta opção A/B
✅ Patch espelha estrutura final exata
✅ Page TSX usa imports + padrões reais do repo (não chutes)
✅ Controller estub honesto com TODOs onde precisa do model real
✅ Decisões de produto explicitadas, não silenciadas

## Próxima evolução (futuro)

- **Schema-first**: ler `Modules/Financeiro/Database/migrations/*` antes de escrever Controller pra usar nomes reais de coluna.
- **Service discovery**: ler `Modules/Financeiro/Services/*` pra identificar Service existente reusável (BaixaService? PagamentoService?).
- **Test stub junto**: gerar `Modules/Financeiro/Tests/Feature/UnificadoControllerTest.php` no mesmo PR.
- **Captura visual**: anexar screenshot do protótipo ao README_F3.md como expectativa visual pra Code.
