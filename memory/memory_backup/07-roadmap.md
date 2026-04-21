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

## Legenda

- ✅ Concluído
- 🟡 Em andamento
- ⏳ Pendente / não iniciado
- 🔴 Bloqueado

---

**Última atualização:** 2026-04-18 (sessão 02 — scaffold refatorado para padrão Jana após crash em produção)
