# Requisitos Funcionais — Módulo PontoWR2

> Review consolidado do que foi prometido vs. o que está implementado.
> Atualizar sempre que uma pendência for resolvida ou um requisito novo entrar.

**Última atualização:** 2026-04-22 (sessão 10)

---

## ✅ Já atende (backend completo + UI React migrada)

| Requisito funcional | Como cumpre |
|---------------------|-------------|
| **Apuração CLT automática** (Art. 58, 59, 66, 71, 73) | `ApuracaoService` com 5 regras + DSR (Lei 605/49 Art. 9º) — backend completo |
| **Marcações imutáveis (Portaria MTP 671/2021)** | Triggers MySQL bloqueiam UPDATE/DELETE + hash SHA-256 encadeado via `MarcacaoService` |
| **Anulação append-only** | Anular = criar nova marcação ANULACAO ligada à original. Nunca delete |
| **Banco de Horas — ledger** | Movimentos append-only, saldo = soma. FIFO para expiração via `BancoHorasService::expirarSaldosAntigos` |
| **Tolerâncias configuráveis** | `config/pontowr2.php` com 7 parâmetros CLT. Visíveis na tela Configurações (read-only) |
| **Parser AFD/AFDT** | `AfdParserService` funcional (tipos 1, 3, 4, 5 da Portaria 671 Anexo I) |
| **Dedup de importação** | SHA-256 do arquivo bloqueia reimport do mesmo conteúdo |
| **Importação assíncrona** | `ProcessarImportacaoAfdJob` (queue Laravel), progresso via polling 3s na UI |
| **Intercorrências — workflow de estados** | RASCUNHO → PENDENTE → APROVADA/REJEITADA → APLICADA / CANCELADA. Motivo obrigatório em rejeição |
| **Espelho PDF mensal** | `ReportService::espelhoPdf` via `barryvdh/laravel-dompdf`, A4 portrait |
| **Multi-empresa (business_id scope)** | Todas queries usam `session('business.id')`. Nunca aceita do cliente |
| **RBAC granular** | 5 permissões Spatie registradas: `ponto.access`, `.colaboradores.manage`, `.aprovacoes.manage`, `.relatorios.view`, `.configuracoes.manage` |
| **UI React no AppShell** | 11 telas Inertia: Dashboard, Welcome, Relatórios, Aprovações, Espelho (index+show), Banco de Horas (index+show), Intercorrências (index+create+show), Escalas (index+form), Importações (index+create+show), Colaboradores (index+edit), Configurações (index+reps) |

## ⚠️ Parcial ou stub — precisa finalizar

| Requisito | Estado atual | O que falta |
|-----------|--------------|-------------|
| **Relatórios AFD/AFDT/AEJ/HE/BH/Atrasos/eSocial** | Stubs retornam `RuntimeException` no `ReportService` | Implementar 7 geradores (formato Portaria 671 Anexo I) |
| **eSocial S-1010/S-2230/S-2240** | Stubs | Integração real com `nfephp-org/sped-esocial` (fase 8 do roadmap) |
| **Certificado ICP-Brasil** | Não implementado | Assinatura PKCS#7 do AFD via sped-common (fase 4) |
| **App REP-P mobile** | Fora do escopo | React Native em repo separado (fase 6) |
| **CRUD de turnos dentro de Escala** | Tabela read-only | Form dinâmico para adicionar/editar turnos por dia da semana |
| **Anexos em Intercorrência** | Campo `anexo` existe mas UI não trata | Upload via `forceFormData` + preview do PDF/JPG/PNG |
| **Aprovação em lote** | Backend existe (`aprovarEmLote`) | Checkboxes na tabela Aprovações + botão "Aprovar selecionadas" |
| **IA classificadora** | Service e endpoint prontos | Ativar no `.env`: `AI_ENABLED=true` + `AI_CLASSIFICACAO_INTERCORRENCIA=true` + `OPENAI_API_KEY` |

## 🔴 Planejado mas não tocado

| Requisito | Próxima ação sugerida |
|-----------|----------------------|
| **Validação de integridade** em auditoria | Botão "Verificar cadeia de hash" (service existe: `MarcacaoService::verificarIntegridade`) — disponibilizar na tela Configurações |
| **Reapuração em lote via queue** | Job existe (`ReapurarDiaJob`), falta UI para disparar (ex.: botão por dia na tela de Espelho) |
| **Alerta automático de divergências** | Tela já exibe, mas poderia mandar email pro gestor quando detecta |
| **Testes unitários CLT** | Só 2 arquivos de teste (`MarcacaoServiceTest`, `ApuracaoServiceTest` com 9 casos). Meta: 70%+ coverage com Pest v3 (fase 14) |
| **Seeder real de teste** | Existe `DevPontoSeeder` idempotente com 5 turnos + 1 REP + 2 colaboradores. Não cria marcações/intercorrências fake para demo |
| **Documentação para RH (usuário final)** | Falta guia interno ou tour Intro.js pra primeiras utilizações |

## 🧪 Requisitos UX novos (após migração React)

Surgiram com a modernização UI. Nem todos estavam previstos originalmente.

| Requisito UX | Estado | Observação |
|--------------|--------|------------|
| **Dark mode por usuário** | ✅ | Coluna `users.ui_theme` + anti-flash no blade + hook `useTheme` |
| **Responsivo mobile** | ✅ | Sheet drawer no mobile, tabelas com scroll-x |
| **Navegação SPA** | ✅ | 10 rotas `/ponto/*` em inertiaPrefixes. 0 reloads entre telas |
| **Toasts em operações** | ✅ | sonner em todos submits |
| **Breadcrumb automático** | ✅ | Em todas as 11 telas |
| **Command palette Cmd+K** | ⏳ | Placeholder no topbar. Falta implementar |
| **Filtros preservados na paginação** | ✅ | `withQueryString()` em todos paginators |
| **Busca debounced** | ✅ | Colaboradores 350ms. Aprovações ainda "em breve" |
| **Sidebar 2 níveis** | ✅ | Col 1 módulos, col 2 sub-páginas do ativo |
| **Polling em long-running** | ✅ | Importações/Show auto-refresh 3s enquanto ESTADO_PROCESSANDO |

## 📋 Requisitos por tela (mapa)

Cada tela abaixo tem um conjunto específico de requisitos. Para detalhes, abrir o arquivo da spec do módulo em `memory/modulos/PontoWr2.md`.

### Dashboard (`/ponto`)
- ✅ 6 KPIs (colaboradores, presentes, atrasos, faltas, HE mês, aprovações pendentes)
- ✅ Chart 7 dias trabalhado + HE
- ✅ Top 5 aprovações pendentes com priority badge
- ✅ Últimas 10 marcações com tipo icon

### Espelho Mensal (`/ponto/espelho`, `/ponto/espelho/{id}`)
- ✅ Lista de colaboradores ativos filtrada por `controla_ponto=true`
- ✅ Seletor mês YYYY-MM
- ✅ Show: 6 totalizadores (trabalhado/atraso/falta/HE diurna/HE noturna/BH)
- ✅ Navegação mês anterior/próximo
- ✅ Imprimir PDF (DomPDF)
- ✅ Tabela dia-a-dia com chips de marcação, destaque weekend + divergências

### Aprovações (`/ponto/aprovacoes`)
- ✅ 6 KPIs por estado clicáveis (filtram)
- ✅ Filtros tipo + prioridade
- ✅ AlertDialog aprovar (avisa se impacta_apuracao)
- ✅ Dialog rejeitar com motivo obrigatório (5-500 chars)
- ⚠️ Falta: checkboxes para aprovar em lote

### Intercorrências (`/ponto/intercorrencias/*`)
- ✅ Index com filtros estado/tipo
- ✅ Create com campo IA (`⚡ Preencher com IA` via OpenAI gpt-4o-mini)
- ✅ Show com ações condicionais por estado (Editar/Submeter/Cancelar)
- ✅ Mascara CPF/PIS/email antes de enviar p/ IA (LGPD)
- ⚠️ Falta: upload de anexo (PDF/JPG/PNG)
- ⚠️ Falta: tela Edit (só Create tem form React)

### Banco de Horas (`/ponto/banco-horas/*`)
- ✅ Index com 4 KPIs + tabela ordenada por saldo
- ✅ Show com saldo destacado + histórico paginado
- ✅ Form de ajuste manual (± minutos + observação obrigatória)
- ✅ Alert "append-only" explicativo

### Escalas (`/ponto/escalas/*`)
- ✅ Index com tipo + carga + turnos_count
- ✅ Form create/edit com 6 campos
- ⚠️ CRUD de turnos por dia da semana (só read-only na edit)

### Importações (`/ponto/importacoes/*`)
- ✅ Index com estado + linhas + tamanho
- ✅ Create: upload file AFD/AFDT, progress bar
- ✅ Show: metadata + progresso com polling 3s
- ✅ Dedup SHA-256 no backend

### Colaboradores (`/ponto/colaboradores/*`)
- ✅ Index com busca debounced
- ✅ Edit com form de config de ponto (matrícula, CPF, PIS, escala, switches)
- ⚠️ Cadastro de novo colaborador ainda depende do HRM (UltimatePOS core)

### Configurações (`/ponto/configuracoes/*`)
- ✅ Index: 4 cards read-only (CLT, BH, REPs, AFD+eSocial) com código das leis
- ✅ REPs: form de cadastro + tabela paginada
- ⚠️ Falta: tela pra editar config CLT direto (hoje só via `config/pontowr2.php`)
- ⚠️ Falta: Toggle de ativação/desativação de REP existente

---

## 🎯 Próximas prioridades sugeridas

1. **Habilitar IA no `.env`** (trivial, 3 linhas) — Wagner precisa fazer
2. **Implementar 7 relatórios stub** (AFD/AFDT/AEJ/HE/BH/Atrasos/eSocial) — fase 3 do roadmap
3. **CRUD de turnos** dentro de Escala — usabilidade de alta alavanca
4. **Upload de anexo** em Intercorrências — completar Create
5. **Aprovação em lote** com checkboxes — produtividade do RH
6. **Tela Edit de Intercorrência** — completar CRUD
7. **Pest v3 + 70% coverage** em services críticos — F14 roadmap A+

## ⚙️ Onde buscar mais

- Especificação original: arquivos em outputs do Cowork fora do repo
- Mapa das 8 migrations + triggers: `memory/modulos/PontoWr2.md` (gerado por `php artisan module:specs`)
- ADRs relevantes: 0003 (append-only), 0006 (business_id), 0007 (BH ledger)
- Estado completo do projeto: `memory/08-handoff.md` seção sessão 10
- Roadmap 10 milestones: `memory/07-roadmap.md` fases 13-15
