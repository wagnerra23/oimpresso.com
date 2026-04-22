# 07 — Roadmap e Status

Este arquivo é o **mapa vivo** do que está feito, o que está em andamento, e o que vem pela frente. Atualize ao fim de cada sessão.

---

## Fase 0 — Concepção e design ✅ CONCLUÍDA (abril 2026)

Produzida fora deste repositório (pasta de outputs temporários do Cowork), mas referenciada aqui.

- [x] **Projeto conceitual** — `projeto_ponto_eletronico_wr2.md` (estado da arte + regras CLT)
- [x] **Especificação técnica Laravel** — `especificacao_tecnica_laravel_wr2.md` (stack + 11 sprints)
- [x] **Especificação UltimatePOS 6 HRM + plano de adaptação** — `ultimatepos6_hrm_especificacao_e_adaptacao.md`
- [x] **Design do projeto** — `design_projeto_ponto_wr2.md` (C4, ERD, wireframes ASCII)

## Fase 1 — Scaffolding do módulo 🟡 QUASE COMPLETA (aguarda validação em runtime)

Iniciada em 2026-04-18, sessão 01. Refatorada para padrão Jana em 2026-04-18, sessão 02 (ADR 0011).

### Completo ✅
- [x] Estrutura de pastas do módulo **alinhada com Modules/Jana** (padrão UltimatePOS)
- [x] `module.json` com `"files": ["start.php"]`, `composer.json`, `package.json`
- [x] `Config/config.php` com regras CLT parametrizadas
- [x] `start.php` na raiz carregando `Http/routes.php`
- [x] **1 ServiceProvider** (`PontoWr2ServiceProvider`) — `RouteServiceProvider` removido no refactor
- [x] 8 migrations das tabelas do domínio, incluindo triggers MySQL de imutabilidade
- [x] 10 Models Eloquent (Colaborador, Escala, EscalaTurno, Marcacao, Intercorrencia, ApuracaoDia, BancoHorasSaldo, BancoHorasMovimento, Rep, Importacao)
- [x] 10 Controllers (um por seção do menu horizontal)
- [x] 5 Services (ApuracaoService, BancoHorasService, AfdParserService, IntercorrenciaService, NsrService) com stubs funcionais
- [x] **Http/routes.php** — arquivo único com 3 Route::group (web, api, install) no estilo Jana
- [x] Layout Blade com menu horizontal de abas (usa nomes `ponto.*`)
- [x] Views stubs para as 10 seções
- [x] Middleware `CheckPontoAccess` aliasado como `ponto.access`
- [x] FormRequests (IntercorrenciaRequest, ImportacaoAfdRequest)
- [x] Console Command `ImportAfdCommand`
- [x] Traduções em `Resources/lang/pt/` (código curto)
- [x] Seeder skeleton
- [x] Sistema de memória do projeto (`/memory/`)
- [x] Protótipo visual do Dashboard Gestor em HTML+Tailwind (arquivo fora deste repo)

### Pendente ⏳
- [ ] **Validar em staging que o módulo dá boot sem erro**
- [ ] Remover fisicamente `Providers/RouteServiceProvider.php` e pasta `Resources/lang/pt-BR/` (neutralizados mas ainda no disco)
- [ ] Factories (`Database/factories/*.php`) para cada Entity
- [ ] Seeders reais (EscalaSeeder, RepSeeder, ColaboradorConfigSeeder)
- [ ] Jobs (`ProcessarImportacaoAfdJob`, `ReapurarDiaJob`)
- [ ] Testes unitários de `ApuracaoService` (regras CLT)
- [ ] Implementação real dos parsers de AFD tipos 1–9
- [ ] Views Blade reais (não apenas stubs) para Espelho, Aprovações, Banco de Horas, etc.

## Fase 2 — Regras de negócio completas ⏳

- [ ] Apuração com todas as regras CLT aplicadas (tolerância, intra, interjornada, HE, noturno, DSR)
- [ ] Banco de horas completo (créditos, débitos, expiração FIFO, pagamento)
- [ ] Intercorrências com state machine validada
- [ ] Reapuração em lote via filas
- [ ] Detecção e marcação de divergências

## Fase 3 — Imports e exports ⏳

- [ ] Parser AFD completo (todos os tipos de registro)
- [ ] Geração de AFD conforme Anexo I Portaria 671/2021
- [ ] Geração de AFDT e AEJ
- [ ] Import de cadastros via CSV
- [ ] Export de espelho de ponto em PDF

## Fase 4 — Assinatura digital ⏳

- [ ] Configuração de certificado ICP-Brasil A1 por empresa
- [ ] Assinatura PKCS#7 de marcações
- [ ] Hash encadeado (cada marcação referencia o hash da anterior)
- [ ] Validação de integridade em auditorias

## Fase 5 — Interface web (produção) ⏳

- [ ] Migrar wireframes HTML → Blade + AdminLTE real
- [ ] Dashboard gestor com KPIs reais
- [ ] Espelho de ponto interativo com edição autorizada
- [ ] Fila de aprovações com ações em lote
- [ ] Wizard de import AFD com preview

## Fase 6 — App mobile do colaborador ⏳

- [ ] Projeto React Native + Expo
- [ ] Autenticação Sanctum
- [ ] Tela de marcação (entrada/saída/almoço) com GPS
- [ ] Histórico de marcações
- [ ] Saldo de banco de horas
- [ ] Solicitação de intercorrência
- [ ] Offline-first com SQLite

## Fase 7 — Relatórios e BI ⏳

- [ ] Biblioteca de relatórios (AFD, AFDT, AEJ, HE, BH, atrasos, eSocial)
- [ ] Export em PDF / XLSX / TXT
- [ ] Agendamento de relatórios recorrentes

## Fase 8 — Integração eSocial ⏳

- [ ] Instalação `nfephp-org/sped-esocial`
- [ ] S-1010 (rubricas)
- [ ] S-2230 (afastamentos)
- [ ] S-2240 (condições ambientais)
- [ ] Monitor de protocolo e retorno

## Fase 9 — RBAC e auditoria ⏳

- [ ] Configurar `spatie/laravel-permission`
- [ ] Perfis: admin, rh, gestor, colaborador
- [ ] Permissões granulares (ponto.access, ponto.aprovar, ponto.configurar, etc.)
- [ ] `spatie/laravel-activitylog` em todas ações críticas

## Fase 10 — Performance e observabilidade ⏳

- [ ] Laravel Horizon configurado
- [ ] Laravel Pulse
- [ ] Índices otimizados
- [ ] Queries analisadas com EXPLAIN
- [ ] Cache de KPIs do dashboard

## Fase 11 — Homologação e piloto ⏳

- [ ] Ambiente de staging espelho do cliente
- [ ] Migração de dados de cliente piloto
- [ ] Fiscalização simulada (auditoria AFD)
- [ ] Treinamento de RH
- [ ] Go-live em 1 cliente

## Fase 12 — Multi-tenancy física (opcional) ⏳

- [ ] Avaliar `stancl/tenancy` para isolamento físico
- [ ] Migração gradual opt-in por cliente
- [ ] Backup per-tenant

---

## Fase 13 — Stack moderna UI (Inertia+React+shadcn+TW4) 🟡 EM ANDAMENTO (2026-04-22)

Meta: preparar o caminho para Laravel 13 + Boost + IA-first. Inertia+React+shadcn+TW4 é exatamente o que Laravel 12 Starter Kit adota como default.

### Completo ✅
- [x] Branch `6.7-react` criada a partir de `6.7-bootstrap` (preserva prod intacta)
- [x] Vue dead code removido (`resources/js/components/{Example,passport}/*.vue`)
- [x] `composer.json` + `inertiajs/inertia-laravel` instalado
- [x] `package.json` atualizado: React 19 + TS 5.5 + Inertia 2 + TW4 + shadcn deps (Radix UI, RHF, zod, lucide, sonner, tanstack/react-table)
- [x] daisyUI 3 + tailwindcss-motion + tailwindcss 3 removidos (conflito TW4)
- [x] `vite.inertia.config.mjs` + `tsconfig.json` (strict) + `components.json` (shadcn)
- [x] Estrutura `resources/js/{app.tsx,ssr.tsx,Layouts,Lib,Types,Hooks,Pages}`
- [x] `resources/css/inertia.css` (TW4 + tokens shadcn)
- [x] `HandleInertiaRequests` middleware — compartilha `auth`, `business` (via session business_id), `ai` flags, `flash`, `csrf_token`
- [x] `resources/views/layouts/inertia.blade.php` (root blade Inertia separado do `layouts.app` legado)
- [x] Rota piloto `/ponto/react` servindo `Pages/Ponto/Welcome.tsx`
- [x] Build validado (778 módulos, 31KB CSS + 423KB JS gzip)

### Completo ✅ (sessão 2026-04-22)
- [x] Setup local completo: Herd (oimpresso.test SSL) + Laragon MySQL 8.4 + dump Hostinger importado (170 tabelas, 72MB)
- [x] Deprecations PHP 8.4 suprimidas (`public/.user.ini` + `public/index.php`)
- [x] PontoWr2 habilitado em `modules_statuses.json`
- [x] Fix Officeimpresso DataController — `action('...@generateQr')` quebrava sidebar inteira; isolado
- [x] Componentes shadcn base: Card, Button, Badge, Alert instalados via CLI
- [x] **Relatórios React** (`/ponto/relatorios`) validado em runtime — grid 4×2 Card/Badge/Button + Lucide
- [x] **Welcome React** (`/ponto/react`) validado — props business+auth via HandleInertiaRequests

### Pendente — Ordem revisada em 2026-04-22 ⏳

**F13.1 — AppShell unificado (FAZER ANTES do Dashboard — alavanca máxima)**
Wagner validou: preocupação com layouts + compatibilidade de módulos ⇒ shell vem primeiro. Cada tela migrada herda dele.

- [ ] `ShellMenuBuilder` service Laravel — agrega menus de módulos ativos (reusa `DataController::modifyAdminMenu()` via nwidart/laravel-menus ou config explícita), filtra por permissões, resolve Inertia × legado AdminLTE
- [ ] Menu compartilhado via `HandleInertiaRequests` (prop `shell.menu`) + branding + business switcher data
- [ ] Componente React `AppShell.tsx`: sidebar colapsável + top bar (logo/business, busca global, notifs, avatar+dropdown) + main area + breadcrumb automático
- [ ] Command palette (Cmd+K) com `cmdk` / shadcn Command — tudo acessível por teclado
- [ ] Try/catch no menu builder — módulo quebrado (como Officeimpresso que bugou hoje) não derruba shell
- [ ] Migrar Welcome + Relatórios para usar `AppShell`
- [ ] Links para telas ainda AdminLTE usam `<a>` externo (recarga completa); quando a tela migrar, vira `<Link>` Inertia (SPA)

**F13.2 — Dashboard** com 6 StatCards + fila aprovações + atividade recente (dentro do shell)

**F13.3 — Intercorrências CRUD com campo IA** (o "wow" demo prometido)
- Textarea livre "Descrição" com botão "IA preencher" → OpenAI classifica tipo + gera justificativa formal PT-BR
- Estrutura JSON mode com zod schema
- Fallback gracioso se `AI_ENABLED=false` ou OpenAI offline
- Cache por hash do prompt (`openai-php/laravel` já no composer)
- Mascarar CPF/PIS antes de enviar pra IA (privacidade)

**F13.4 — Telas restantes (6):**
- Aprovações com DataTable servidor-side + AlertDialog aprovar/rejeitar
- Espelho (index + show com totalizadores + chips de marcação)
- Banco de Horas (index + show + ajuste manual com observação obrigatória)
- Escalas CRUD + gestão de turnos (fase 2)
- Importações + upload AFD com progress + polling status (job assíncrono)
- Colaboradores (busca debounced, form config ponto com switches)
- Configurações read-only + CRUD REPs

**F13.5 — Remover navbar AdminLTE do módulo PontoWR2** — shell cobre sidebar
**F13.6 — Piloto runtime** com cliente real testando end-to-end

## Fase 14 — A+ observabilidade / testes / CI-CD ⏳

Guardado em `~/.claude/projects/.../memory/project_roadmap_a_plus.md`. Resumo:

- [ ] **Sentry** (`sentry/sentry-laravel` + `@sentry/react`) — grátis até 5k eventos/mês
- [ ] **Laravel Pulse** para métricas de request/query/queue
- [ ] **Logs estruturados JSON** facilitando ship para Loki/ELK
- [ ] **Pest v3** migração gradual do PHPUnit
- [ ] **Laravel Dusk ou Playwright** para E2E fluxos críticos (POS, NFe, boleto, ponto)
- [ ] **Vitest + RTL** para componentes React
- [ ] **GitHub Actions**: lint + typecheck + Pest + Vitest + build Vite em cada PR
- [ ] **Deploy automatizado** (Envoyer/Deployer/GH Actions SSH) substituindo `git pull` manual
- [ ] **Semantic versioning** em tags (`v6.7.x`) para rollback dirigido
- [ ] **Proteção de branch main** bloqueando merge sem CI verde

Ordem sugerida: Sentry → CI básico → Pest migração → deploy automatizado → Pulse/logs estruturados.

## Fase 15 — Fiscal: Boleto + NFe padronizados + Tributação por prioridade ⏳

Decisão Wagner (2026-04-22): apagar `Modules/Boleto/` custom (93 arquivos próprios) e usar pacotes padrão. Guardado em `~/.claude/projects/.../memory/project_roadmap_fiscal.md`.

### Pacotes
- [ ] `eduardokum/laravel-boleto` (20+ bancos BR, PIX+QR Code, CNAB 240/400)
- [ ] `nfephp-org/sped-nfe` (NFe/NFCe geração + assinatura + SEFAZ + DANFE)
- [ ] (Opcional) `nfephp-org/sped-nfse-*` habilitar por município quando precisar

### Modelo de dados
- [ ] Nova tabela `perfis_tributacao` (business_id, nome, prioridade, CST/CFOP/alíquotas/origem/NCM)
- [ ] Colunas `perfil_tributacao_id` em `products`, `categories`, `brands`
- [ ] Colunas em `business`: `perfil_tributacao_default_id`, `regime_tributario`, `crt`, `boleto_configs` JSON, `nfe_ambiente`, `nfe_serie`, `nfe_proximo_numero`, `certificado_path`, `certificado_senha` encrypted
- [ ] `TributacaoResolver` service com cascata Produto → Categoria → Marca → Business → Sistema fallback

### Telas (React/Inertia)
- [ ] Wizard 5 passos: regime → certificado → ambiente → primeiro perfil → banco
- [ ] Config bancário com "Testar conexão" (gera boleto R$ 0,01)
- [ ] CRUD perfis de tributação com prioridade draggable
- [ ] Campo "Perfil de tributação" no cadastro de produto com preview resolvido
- [ ] Dialog "Emitir NFe?" pós-POS (Emitir agora / Rascunho / Só cupom) com job assíncrono + polling
- [ ] Geração em lote de boletos (N parcelas → PDF único + email + QR PIX)
- [ ] Central Fiscal dashboard (NFes hoje/mês, boletos KPIs, alertas)
- [ ] Histórico com filtros + ações em lote (remessa, retorno)

### IA
- [ ] Classificador NCM (descrição produto → sugestão NCM)
- [ ] Tradutor rejeições SEFAZ (código → linguagem humana + ação sugerida)
- [ ] Sugeridor perfil de tributação (NCM + regime + histórico)

**Prioridade declarada pelo cliente:** usabilidade do usuário final + fácil configuração + conceito de tributação por prioridade.

---

## Legenda

- ✅ Concluído
- 🟡 Em andamento
- ⏳ Pendente / não iniciado
- 🔴 Bloqueado

---

**Última atualização:** 2026-04-22 (sessão 10 — setup Inertia+React+shadcn+TW4 na branch `6.7-react`; fases 13-15 adicionadas ao roadmap)
