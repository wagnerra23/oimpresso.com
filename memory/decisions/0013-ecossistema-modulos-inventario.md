---
# ADR 0013 — Ecossistema de Módulos: Inventário, Categorias e Padrões

**Data:** 2026-04-21
**Status:** Aceita
**Autora:** Eliana (WR2 Sistemas) — levantamento sessão 10

---

## Contexto

O servidor `oimpresso.com` roda UltimatePOS v6.7 (Laravel 9.51 + PHP 8.3) com **30 módulos nWidart** ativos. Este ADR documenta o inventário completo, as categorias funcionais, os padrões arquiteturais comuns e as dependências entre módulos — base para decisões de integração com PontoWR2.

---

## Inventário Completo

### Categoria: Core Extension (estende o UltimatePOS core)
| Módulo | Descrição | Entidades principais | Status |
|--------|-----------|----------------------|--------|
| **Essentials** | HRM: turnos, frequência, férias, folha | Shift, EssentialsAttendance, EssentialsLeave, PayrollGroup | ✅ Ativo |
| **Dashboard** | Widgets configuráveis por usuário/perfil | DashboardConfiguration | ✅ Ativo |
| **Superadmin** | Gestão multi-tenant, licenças | Business, Subscription | ✅ Ativo |
| **Connector** | API REST para integrações externas | 40+ controllers de API | ✅ Ativo |
| **Crm** | CRM: leads, campanhas, agenda | CrmContact, Campaign, Proposal, Schedule | ✅ Ativo |
| **Project** | Gestão de projetos, tarefas, tempo | Project, Task, ProjectTimeLog | ✅ Ativo |
| **Repair** | Ordens de serviço técnico | JobSheet, DeviceModel, RepairStatus | ✅ Ativo |
| **ProductCatalogue** | Catálogo público de produtos | — | ✅ Ativo |
| **Woocommerce** | Sync bidirecional com WooCommerce | WoocommerceSettings | ✅ Ativo |

### Categoria: Fiscal / Legal (obrigações legais BR)
| Módulo | Descrição | Entidades principais | Status |
|--------|-----------|----------------------|--------|
| **Fiscal** | Emissão NF-e, NFC-e | NfNaturezaOperacao, NfChave, NfXml | ✅ Ativo |
| **Boleto** | Geração e remessa de boletos bancários | Boleto, BoletoRemessa | ✅ Ativo |
| **PontoWr2** | Controle ponto eletrônico (Portaria 671/2021) | Colaborador, Marcacao, Escala, BancoHoras | ⚠️ Desativado* |

*Desativado aguardando deploy das correções de compatibilidade Laravel 9 (sessão 09/10).

### Categoria: Operações / Produção
| Módulo | Descrição | Entidades principais | Status |
|--------|-----------|----------------------|--------|
| **Manufacturing** | Produção industrial, receitas | Recipe, Production | ✅ Ativo |
| **IProduction** | Produção integrada (variante) | — | ✅ Ativo |
| **Producao** | Controle de produção WR2 | — | ✅ Ativo |
| **AssetManagement** | Gestão de ativos físicos | Asset, AssetAllocation | ✅ Ativo |
| **Accounting** | Contabilidade: plano de contas, lançamentos | 70+ entidades (ChartOfAccount, JournalEntry, etc.) | ✅ Ativo |

### Categoria: Analytics / BI
| Módulo | Descrição | Entidades principais | Status |
|--------|-----------|----------------------|--------|
| **BI** | Business Intelligence, dashboards avançados | — (usa views/queries) | ✅ Ativo |
| **Grow** | Growth analytics, métricas de crescimento | — | ✅ Ativo |
| **Spreadsheet** | Export/import de planilhas | — | ✅ Ativo |

### Categoria: Comunicação / Conteúdo
| Módulo | Descrição | Entidades principais | Status |
|--------|-----------|----------------------|--------|
| **Chat** | Chat interno + integração videoconferência | Mensagem, Meeting, Group | ✅ Ativo |
| **Cms** | Páginas e conteúdo web | CmsPage | ✅ Ativo |
| **Knowledgebase** | Base de conhecimento interna | KnowledgeBase | ✅ Ativo |
| **Help** | Central de ajuda e suporte | — | ✅ Ativo |
| **AiAssistance** | Assistente IA integrado | — | ✅ Ativo |
| **Writebot** | IA para geração de textos | — | ✅ Ativo |

### Categoria: Custom WR2 (desenvolvido pela WR2 Sistemas)
| Módulo | Descrição | Entidades principais | Status |
|--------|-----------|----------------------|--------|
| **Officeimpresso** | Gestão licenças Office/impressoras | LicencaComputador | ✅ Ativo |
| **Officeimpresso1** | Versão alternativa do Officeimpresso | LicencaComputador | ✅ Ativo |
| **Jana** | **Módulo de referência canônica** | — | ✅ Ativo |

### Categoria: Terceiros (CodeCanyon)
| Módulo | Descrição | Status |
|--------|-----------|--------|
| **codecanyon-32094844 (PerfectSupport)** | Tickets de suporte + docs | ✅ Ativo |

---

## Padrões Arquiteturais Comuns

Todos os módulos seguem o padrão nWidart/laravel-modules v9:

```
Modules/{NomeModulo}/
├── module.json              # Metadados, providers, requires
├── start.php               # Aliases, helper functions (opcional)
├── composer.json
├── Config/                 # config/{alias}.php
├── Console/Commands/       # Artisan commands
├── Database/
│   ├── Migrations/         # Migrations do módulo
│   ├── Seeders/
│   └── factories/
├── Entities/               # Models Eloquent
├── Http/
│   ├── Controllers/        # Web + API controllers
│   ├── Middleware/
│   ├── Requests/           # Form Requests
│   └── routes.php          # OU Routes/web.php + api.php
├── Providers/
│   ├── {Nome}ServiceProvider.php  # Obrigatório
│   └── RouteServiceProvider.php   # nWidart v9
├── Resources/
│   ├── views/              # Blade templates
│   ├── lang/pt/            # Traduções PT
│   └── assets/
├── Services/               # Lógica de domínio (alguns módulos)
└── Tests/
```

**Stack de middlewares web (todos os módulos):**
```php
['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']
```

**Scope multi-empresa:** Todo query usa `business_id` da sessão.

---

## Mapa de Dependências Relevante para PontoWR2

```
PontoWr2
 ├── depende de → Essentials (Shift, EssentialsAttendance — referência de turnos)
 ├── depende de → core UltimatePOS (users, business, employees)
 ├── integra com → Connector (API de frequência para BI/mobile)
 ├── integra com → Accounting (lançamentos de HE e banco de horas)
 └── alimenta → BI (relatórios de horas trabalhadas)
```

---

## Problema Transversal Identificado (sessão 10)

**`findorfail` bug:** 150+ arquivos em `app/` e `Modules/` usavam `->findorfail()` (lowercase) — inválido no Laravel 9. Corrigido globalmente em 2026-04-21. Ver commit `1cf9cd2dd` no branch `producao`.

**Coluna `price_calculation_type` ausente:** Upgrade v6.7 adicionou esta coluna na tabela `customer_groups` sem migration. Adicionada via SQL direto em 2026-04-21.

---

## Consequências

- PontoWR2 deve evitar duplicar entidades existentes no Essentials (especialmente `Shift` e `EssentialsAttendance`)
- Connector é o gateway natural para expor API de ponto ao BI e aplicativo mobile
- Accounting pode receber lançamentos automáticos de HE calculadas pelo PontoWR2
- O módulo Jana é o padrão de referência estrutural para todos os novos módulos

---

> **Próxima revisão:** após PontoWR2 reativado e integração com Essentials definida
