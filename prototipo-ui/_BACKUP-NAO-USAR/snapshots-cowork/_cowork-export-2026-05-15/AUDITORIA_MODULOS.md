# Auditoria dos módulos — `wagnerra23/oimpresso.com@main` (2026-05-13)

> Sincroniza com `MOCK.MENU` em `data.jsx` e `MIGRATION_INFO` em `app.jsx`.
> Origem: `Modules/` no repo, listado via GitHub Connector em 2026-05-13.

## Resumo

| Total no repo | No menu antes | No menu agora | Migrados (React) | Em iteração |
|---|---|---|---|---|
| **37** | 14 | **36** | 4 (Financeiro, MemCofre, Copiloto, Cms/Site) | 3 (Ponto WR2, NFSe, NfeBrasil) parciais |

> **+1 desde 2026-05-13:** `Modules/PaymentGateway` adicionado em 2026-05-19 (Onda 0 — docs only, ADR 0170). Ainda não no menu (não habilitado).

## Por grupo no shell

### OFFICEIMPRESSO (8 itens — núcleo do ERP)
| Id | Módulo no repo | Status | Fase | Prioridade |
|---|---|---|---|---|
| `os` | `Officeimpresso` (escopo OS) | next | F2 | **Piloto P0** |
| `clientes` | `Officeimpresso` (escopo clientes) | queue | F3 | Alta freq. |
| `produtos` | `Officeimpresso` (escopo produtos) | queue | F3 | Alta freq. |
| `orcamentos` | `Officeimpresso` (escopo orcamentos) | queue | F3 | Alta freq. |
| `vendas` | `Officeimpresso` (escopo vendas) | queue | F3 | **Piloto P0 (Sells/Create)** |
| `cv` | `ComunicacaoVisual` | later | F1 | Núcleo CV |
| `catalogue` | `ProductCatalogue` | later | F1 | Suporte |
| `portalos` | `ConsultaOs` | later | F1 | Externo |

### COMERCIAL (4)
| Id | Módulo no repo | Status | Fase |
|---|---|---|---|
| `crm` | `Crm` | later | F1 |
| `ads` | `ADS` | later | F1 |
| `grow` | `Grow` | later | F1 |
| `whatsapp` | `Whatsapp` | partial | F1 |

### PRODUÇÃO (6)
| Id | Módulo no repo | Status | Fase |
|---|---|---|---|
| `fila` | `Officeimpresso/producao/fila` | later | F4 |
| `acabamento` | `Officeimpresso/producao/acabamento` | later | F4 |
| `expedicao` | `Officeimpresso/producao/expedicao` | later | F4 |
| `manufacturing` | `Manufacturing` | later | F1 |
| `iproduction` | `IProduction` | later | F1 |
| `brief` | `Brief` | later | F1 |

### VERTICAIS (3)
| Id | Módulo no repo | Status | Fase |
|---|---|---|---|
| `repair` | `Repair` | later | F1 |
| `oficinaauto` | `OficinaAuto` | later | F1 |
| `vestuario` | `Vestuario` | later | F1 |

### PESSOAS (2)
| Id | Módulo no repo | Status | Fase |
|---|---|---|---|
| `ponto` | `Ponto` (PontoWr2) | partial | F4 |
| `equipes` | `Officeimpresso/equipes` | later | F5 |

### FINANCEIRO (7)
| Id | Módulo no repo | Status | Fase |
|---|---|---|---|
| `financeiro` | `Financeiro` | **done** | F1 ✅ |
| `relatorios` | `Officeimpresso/relatorios` | later | F5 |
| `nfse` | `NFSe` | partial | F1 |
| `nfe` | `NfeBrasil` | partial | F1 |
| `accounting` | `Accounting` | later | F1 |
| `recurring` | `RecurringBilling` | later | F1 |
| `paymentgateway` | `PaymentGateway` | later | F1 (Onda 0 — ADR 0170 proposto, só docs) |

### PROJETOS & GESTÃO (6)
| Id | Módulo no repo | Status | Fase |
|---|---|---|---|
| `projects` | `ProjectMgmt` | later | F1 |
| `assets` | `AssetManagement` | later | F1 |
| `auditoria` | `Auditoria` | later | F1 |
| `governance` | `Governance` | later | F1 |
| `kb` | `KB` | later | F1 |
| `spreadsheet` | `Spreadsheet` | later | F1 |

### OUTROS (4)
| Id | Módulo no repo | Status | Fase |
|---|---|---|---|
| `memcofre` | `Essentials/memcofre` | **done** | F1 ✅ |
| `copiloto` | `Copiloto` (DRY_RUN, ADRs 0035/0036) | **done** | F1 ✅ |
| `site` | `Cms` | **done** | F1 ✅ |
| `arquivos` | `Arquivos` | later | F5 |

### INTEGRAÇÕES (4)
| Id | Módulo no repo | Status | Fase |
|---|---|---|---|
| `connector` | `Connector` | later | F1 |
| `woocommerce` | `Woocommerce` | later | F1 |
| `teammcp` | `TeamMcp` | later | F1 |
| `srs` | `SRS` | later | F1 |

### CONFIGURAÇÕES (5)
| Id | Módulo no repo | Status | Fase |
|---|---|---|---|
| `prefs` | `Essentials/prefs` | later | F5 |
| `users` | `Essentials/users` | later | F5 |
| `admin` | `Admin` | later | F1 |
| `superadmin` | `Superadmin` | later | F1 |
| `jana` | `Jana` (módulo ref. — ADR 0011) | later | F1 |

## Cobertura — 36/36

✅ Cobertos no menu:
ADS, Accounting, Admin, Arquivos, AssetManagement, Auditoria, Brief, Cms, ComunicacaoVisual, Connector, ConsultaOs, Crm, Essentials (prefs+users+memcofre), Financeiro, Governance, Grow, IProduction, Jana, KB, Manufacturing, NFSe, NfeBrasil, Officeimpresso (OS/Clientes/Produtos/Orçamentos/Vendas/Fila/Acabamento/Expedição/Equipes/Relatórios), OficinaAuto, Ponto, ProductCatalogue, ProjectMgmt, RecurringBilling, Repair, SRS, Spreadsheet, Superadmin, TeamMcp, Vestuario, Whatsapp, Woocommerce.

Copiloto entra como item próprio embora não tenha pasta `Modules/Copiloto/` no repo atual (vive sob outro módulo segundo `LARAVEL_REPO_CONTEXT.md`).

## Como usar este protótipo

Cada item do menu abre uma tela de stub (`ModuleStub` em `app.jsx`) que mostra:
- Fase MWART do módulo
- Ações típicas
- Stack atual (Blade vs React)
- Rotas e origem Blade
- Roadmap visual de 4 etapas

Para **transformar um stub em tela completa** (caminho usado com OS/Clientes/Produtos/Orçamentos/Vendas/Produção):
1. Crie `<modulo>-page.jsx` com o componente da tela
2. Importe em `Oimpresso ERP - Chat.html`
3. Adicione `else if (route === "<id>") content = <NomeDaPage/>;` em `App()` em `app.jsx`

## Roadmap de telas P0 (fila atual)

Da `TELAS_REVIEW_QUEUE.md` do repo (PR #295):
- `vendas` → Sells/Create (em F1 com Wagner)
- `vendas` → Sells/Index
- ... (próximas P1–P3 conforme fila do Cowork)

> **Próxima ação:** Wagner adiciona o próximo pedido em `COWORK_NOTES.md` no repo; eu (CC) produzo F1 aqui no Cowork e Claude Design roda F1.5.
