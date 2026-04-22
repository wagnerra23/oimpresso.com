# Como pedir nova tela ou módulo — guia do Wagner

> Template prático para garantir que a IA (Claude) entregue **tudo certinho**
> seguindo o padrão do projeto, sem retrabalho e sem esquecer nada.

**Última atualização:** 2026-04-22 (sessão 10)

---

## 🎯 Quando usar este guia

Cada vez que você for pedir:
- Uma **nova tela** (ex.: "quero uma tela de metas por colaborador")
- Um **novo módulo** (ex.: "quero um módulo de frota")
- Uma **funcionalidade nova** em tela existente (ex.: "adicionar filtro de data em Aprovações")
- Uma **integração** (ex.: "conectar com Z-API")

---

## ✅ Passo 1: comece com este prompt curto (2 linhas)

Isso sozinho já faz a IA puxar contexto e não começar do zero:

```
Leia `memory/08-handoff.md`, `memory/07-roadmap.md` e
`memory/COMO_PEDIR_NOVA_TELA_OU_MODULO.md` antes de responder. Quero [o que você quer].
```

A IA vai:
1. Ler o estado atual do projeto
2. Entender a stack, padrões e decisões
3. Perguntar o que for ambíguo antes de codar

---

## 📋 Passo 2: descreva o que você quer (template)

Copie e preencha:

```markdown
## Objetivo
[frase em 1-2 linhas do que você quer — ex.: "Criar tela de Metas por Colaborador"]

## Contexto de negócio (por quê)
[1-3 linhas — ex.: "Cliente precisa definir meta mensal de vendas por vendedor.
Compara com realizado no fim do mês."]

## Escopo
### Telas / rotas
- [ ] `/metas` — lista paginada
- [ ] `/metas/create` — formulário
- [ ] `/metas/{id}` — detalhe

### Dados principais
- Campos da tabela `metas`: [listar]
- Relacionamentos: [ex.: pertence a colaborador, pertence a business]

### Fluxo / regras
1. [regra 1]
2. [regra 2]

## Quem acessa (permissões)
- [ ] Só admin pode criar
- [ ] Colaborador vê só as dele
- [ ] RH vê de todos

## Prioridade
- 🔴 Urgente (bloqueia trabalho de alguém)
- 🟡 Importante (próximas 2 sessões)
- 🟢 Quando der tempo

## Depois de pronto eu quero
- [ ] Testar no browser (IA faz via Claude_in_Chrome)
- [ ] Testes automatizados
- [ ] Commitar em branch 6.7-react
- [ ] Atualizar memory/CHANGELOG.md
```

**Exemplo mínimo viável** (se você tá com preguiça):

```
Leia memory/08-handoff.md. Quero uma tela /ponto/relatorios/atrasos-mensal
que liste colaboradores com atraso acumulado > 30 min no mês. Só RH vê.
Segue o mesmo padrão das outras telas Ponto (React+Inertia+shadcn).
```

Isso já dá pra IA fazer tudo.

---

## 🤖 O que a IA **tem que fazer** automaticamente (checklist interno)

A IA já sabe que deve seguir isso — mas se esquecer, cole este checklist no prompt como reforço.

### Backend (Laravel)
- [ ] Controller com método retornando `Inertia::render('Namespace/Page', [...])`
- [ ] **Sempre escopar por `business_id`** via `session('business.id')` — NUNCA aceitar do cliente
- [ ] Paginação usa `->paginate(N)->withQueryString()`
- [ ] Shape JSON-friendly via `->transform(fn ($m) => [...])` — nunca mandar Model completo
- [ ] Relations usam `select` explícito (`user:id,first_name,last_name`) pra evitar overfetch
- [ ] FormRequest (`app/Http/Requests/XxxRequest.php`) pra validação, não inline
- [ ] Rotas no `Modules/<Modulo>/Http/routes.php` dentro do Route::group com middleware UltimatePOS padrão
- [ ] Permissões Spatie registradas e checadas (`$user->can('modulo.xxx')`)

### Frontend (React + Inertia)
- [ ] Page em `resources/js/Pages/<Namespace>/<Nome>.tsx`
- [ ] Envelopada em `<AppShell title breadcrumb={[...]}>`
- [ ] Componentes de `@/Components/ui/` (shadcn) — não criar do zero
- [ ] Ícones do `lucide-react`
- [ ] Toast via `sonner` em todas mutations (success + error)
- [ ] AlertDialog pra ações destrutivas, Dialog pra forms
- [ ] Paginação preservando filtros
- [ ] Types TypeScript declarados no topo do arquivo (não `any`)
- [ ] Dark mode funciona (usar tokens `bg-background`, `text-foreground`, etc.)
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] `/rota-nova` adicionada ao `LegacyMenuAdapter.php` em `$inertiaPrefixes` se for SPA

### Testes
- [ ] Feature test em `Modules/<Modulo>/Tests/Feature/` estendendo `PontoTestCase` ou similar
- [ ] Valida que rota renderiza component certo (`assertInertiaComponent`)
- [ ] Valida que exige autenticação (guest recebe redirect 302)
- [ ] Valida validação (campos obrigatórios recebem 422)
- [ ] Valida business_id scope (não vaza dados de outro tenant)

### Build e entrega
- [ ] Rodar `npm run build:inertia` — confere que não tem erro TS
- [ ] Testar no browser via Claude_in_Chrome (validar render + interações)
- [ ] Se criou migration: `php artisan migrate`
- [ ] Limpar caches: `php artisan optimize:clear`
- [ ] Restart Herd se mexeu em PHP opcache-cached

### Documentação
- [ ] Atualizar `memory/CHANGELOG.md` (Added/Changed/Fixed)
- [ ] Atualizar `memory/08-handoff.md` se mudou estado estrutural
- [ ] Se mudou módulo existente, regerar spec: `php artisan module:specs <Modulo>`
- [ ] Se decisão arquitetural nova: criar ADR em `memory/decisions/NNNN-slug.md`

### Commit
- [ ] Só commitar se Wagner autorizar explicitamente
- [ ] Mensagem: `feat|fix|docs(escopo): descrição curta` seguindo histórico recente
- [ ] Body explica o porquê + detalha mudanças
- [ ] Sempre inclui `Co-Authored-By: Claude`

---

## 🚩 Red flags — coisas que fazem a IA entregar algo quebrado

Evite pedir:

1. **"Faz uma tela parecida com X"** sem dizer **o que muda**
   - Melhor: "Igual /ponto/aprovacoes, mas pra Metas — campos X, Y, Z"

2. **"Faz tudo de uma vez"** pra >5 telas
   - Melhor: fatiar em 2-3 telas por sessão — IA fica lenta com escopo gigante

3. **"Rápido e simples, depois melhoro"**
   - Acaba virando débito técnico. Melhor pedir **mínimo viável** com o padrão correto desde o início

4. **Sem dizer quem acessa**
   - IA pode criar rota pública por descuido. Sempre diga papel/permissão

5. **Sem dizer onde encaixa** (qual módulo, qual URL, qual menu)
   - IA inventa path e fica inconsistente

6. **Ignorar decisões prévias**
   - Ex.: "Use Tailwind 3" quando já está TW4. IA deve consultar memory/ — mas se você insistir, ela segue

---

## 📝 Exemplos reais (do que já foi feito)

### Pedido bom — 1 linha
> "Leia memory/08-handoff.md. Quero migrar a tela /ponto/aprovacoes pra React
> no mesmo padrão da tela de Relatórios (F13.4 do roadmap)."

Resultado: 1 commit, 1 tela com KPIs clicáveis + filtros + dialogs. 2h de trabalho.

### Pedido bom — detalhado
> "Leia memory/08-handoff.md. Quero tela de Banco de Horas com 2 sub-telas:
> Index (lista saldos com 4 KPIs) e Show (detalhe + form ajuste manual).
> Ledger é append-only — ajustes viram movimento novo. Só RH pode ajustar.
> Exemplo do visual: similar ao Relatórios mas com tabela."

Resultado: 2 telas limpas, form com validação, alert explicativo sobre append-only.

### Pedido ruim (evite)
> "Faz uma tela pra folha de ponto"

Ambíguo. A IA vai fazer 5 perguntas antes de começar. Melhor: "Tela que lista
marcações do dia pra um colaborador específico, com botão Imprimir PDF."

---

## 🎯 Como validar que ficou pronto

Peça pra IA **testar** depois de codar:

```
Depois de fazer, abra no browser via Claude_in_Chrome e confirma que:
1. Renderiza sem erro
2. Dark mode funciona
3. Responsivo no mobile (resize)
4. Ações principais funcionam (ex.: filtro, botão salvar)
5. Submit com dado inválido mostra erro
```

Se a IA pular este passo, peça **"mostra screenshot"**. Ela fará.

---

## 🔧 Comandos úteis pra você rodar se algo quebrar

```bash
# Recompilar frontend depois de mudança no React
cd D:\oimpresso.com
npm run build:inertia

# Limpar todos os caches Laravel (resolve 80% dos bugs pós-deploy)
php artisan optimize:clear

# Restart Herd (limpa opcache — obrigatório se editou PHP)
C:\Users\wagne\.config\herd\bin\herd.bat restart

# Rodar testes PontoWr2
vendor/bin/phpunit --testsuite=PontoWr2

# Regerar specs dos módulos
php artisan module:specs

# Ver logs de erro
tail -50 storage/logs/laravel.log
```

---

## 🗺️ Mapa de onde pôr código novo

Dúvida de onde colocar cada tipo de arquivo:

| Tipo | Onde | Exemplo |
|------|------|---------|
| Controller módulo | `Modules/<Modulo>/Http/Controllers/` | `AprovacaoController.php` |
| Entity/Model módulo | `Modules/<Modulo>/Entities/` | `Intercorrencia.php` |
| Service módulo | `Modules/<Modulo>/Services/` | `IntercorrenciaAIClassifier.php` |
| Job assíncrono | `Modules/<Modulo>/Jobs/` | `ProcessarImportacaoAfdJob.php` |
| Rotas módulo | `Modules/<Modulo>/Http/routes.php` | (arquivo único) |
| Migration módulo | `Modules/<Modulo>/Database/Migrations/` | `2026_..._create_X.php` |
| Page React | `resources/js/Pages/<Namespace>/` | `Ponto/Dashboard/Index.tsx` |
| Componente shared React | `resources/js/Components/` | `Icon.tsx` |
| shadcn/ui | `resources/js/Components/ui/` | `card.tsx` (não criar manual, usar `npx shadcn add`) |
| Hook React | `resources/js/Hooks/` | `useTheme.ts` |
| Tipo TypeScript | `resources/js/Types/index.ts` | interfaces centralizadas |
| Helper JS | `resources/js/Lib/` | `utils.ts` |
| Controller app-wide | `app/Http/Controllers/` | `UserPreferencesController.php` |
| Service app-wide | `app/Services/` | `LegacyMenuAdapter.php` |
| Middleware | `app/Http/Middleware/` | `HandleInertiaRequests.php` |

---

## 📚 Referências internas para consultar

Sempre que quiser reler decisões/padrões:

- **Stack real e versões:** `memory/02-technical-stack.md`
- **Arquitetura geral:** `memory/03-architecture.md`
- **Convenções de código:** `memory/04-conventions.md`
- **Decisões arquiteturais (ADRs):** `memory/decisions/`
- **Roadmap milestones M1-M10:** `memory/07-roadmap.md`
- **Estado atual da sessão:** `memory/08-handoff.md`
- **Changelog cronológico:** `memory/CHANGELOG.md`
- **Spec de cada módulo existente:** `memory/modulos/`
- **Recomendações por módulo (trazer/descartar):** `memory/modulos/RECOMENDACOES.md`
- **Preferências pessoais (usuário):** `~/.claude/projects/D--oimpresso-com/memory/`

---

## 💡 Prompt "copy-paste" final

Quando não souber o que colocar, use este:

```
Leia `memory/08-handoff.md` e `memory/COMO_PEDIR_NOVA_TELA_OU_MODULO.md`.

QUERO: [descreva em 1-2 linhas]

ONDE: /[rota-nova]   MÓDULO: [PontoWr2 / CRM / Modules / ...]

ACESSO: [admin / RH / todos logados / ...]

DEPOIS: commitar na branch atual, atualizar CHANGELOG, testar no browser.
```

Não precisa mais que isso. A IA puxa o resto do contexto.
