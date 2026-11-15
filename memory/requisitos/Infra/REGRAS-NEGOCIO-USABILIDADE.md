---
slug: regras-negocio-usabilidade
status: proposto
authority: canonical
decided_by: [W]
decided_at: "2026-11-15"
tags: [regras-negocio, usabilidade, multi-tenant, lgpd, uniafibro, wr2, rota-livre, charter]
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0451-associacoes-rede-uniafibro-area-de-membros
  - UI-0013-constituicao-ui-v2-camadas
  - 0059-governanca-memoria-estilo-anthropic-team
---

# Regras de negócio + usabilidade — oimpresso ERP

> Documento vivo das regras canônicas de domínio + padrões de interação com usuário.
> Qualquer mudança nessas regras exige ADR Nygard antes do código.

## 1. Multi-tenancy — Tier 0 (ADR 0093)

**Regra.** Toda consulta/escrita a dados de negócio é filtrada por `business_id` — nunca vaza entre empresas.

**Como tratar.**
- Global scope no Entity (`addGlobalScope(new ScopeByBusiness)`)
- Testes `*IsolationTest.php` obrigatórios em cada módulo
- Jobs/CLI/API sem sessão: bloqueia em vez de liberar (filtro fail-safe — decisão pendente ADR nova pós auditoria RBAC 2026-11)
- Query raw ou `DB::statement` auditada

**Usabilidade.** Transparente — o usuário nunca escolhe empresa; ela vem da sessão/token. UI mostra nome da empresa no topbar como lembrete visual.

## 2. Multi-tenancy hierárquico Uniafibro (ADR 0451)

**Regra.** Uniafibro é 1 empresa só; associações-membro (AGFESC, AFIBROMAR, grupo Capivari) são registros dentro da empresa. Diretoria da rede vê tudo; representante de 1 associação vê só a dele.

**Como tratar.**
- Tabela `associacoes` com `business_id` da Uniafibro + nome/cidade/CNPJ nullable
- `associadas` ligadas a `associacao_id` (não diretamente a `business_id`)
- Permissão `associacoes.representa.{associacao_id}` filtra por associação
- Teste duplo: isolamento entre empresas E isolamento entre associações-membro

**Usabilidade.**
- Diretoria: dashboard rede inteira, filtro por associação
- Representante: tela única da sua associação, resto some
- Associada: não entra no sistema (D4) — vê pelo portal público link+PIN

## 3. Dados sensíveis / LGPD (art. 11)

**Regra.** Fibromialgia = dado de saúde. Consentimento explícito por finalidade. Append-only.

**Como tratar.**
- Tabela `associada_consentimentos` com `finalidade` enum (`ser_associada`, `receber_whatsapp`, `nome_visivel_rede`, etc.) + `versao` + `revoked_at`. Nunca DELETE.
- Mascaramento automático em log (CPF/celular/email via regex)
- Policy de retenção: anonimizar após X dias de revogação
- Sem CPF/diagnóstico/laudo/CID no módulo Associações (ADR 0451 D5)
- Direito de portabilidade: endpoint `/exportar-meus-dados` gera JSON+PDF

**Usabilidade.**
- Checkbox por finalidade no cadastro (não "aceito tudo")
- Página "Meus consentimentos" com toggle on/off
- Confirmação 2-step pra revogar + mostra impacto
- Botão "Esquecer-me" com delay 30d (rollback possível)

## 4. Portal público sem conta — link+PIN (ADR 0451 D6)

**Regra.** Associada acessa por link assinado (token na URL) + PIN 4 dígitos entregue separadamente. Zero conta/senha.

**Como tratar.**
- Token: HMAC assinado com `business_id` + `associada_id` + expira (30d default)
- PIN: 4 dígitos, 5 tentativas, trava de 30min
- Aparelho lembrado via cookie (não LocalStorage — LGPD)
- Rate limit por IP + token
- Nenhum dado da associada antes do PIN aceito (anti-enumeration)

**Usabilidade.**
- Fonte grande (público = dor crônica)
- Input PIN com numpad automático no mobile
- Erro genérico ("link/PIN não encontrado") — anti-enumeration
- Botão "preciso de novo link" encaminha pra representante

## 5. Fiscal / Financeiro (ROTA LIVRE, Martinho, indústrias futuras)

**Regra.** Valores em centavos (int), nunca float. Append-only em movimentação de banco de horas, marcações de ponto, consentimentos. Blocos K SPED pra indústria.

**Como tratar.**
- `bcmath` ou int cents em toda aritmética financeira
- Trigger MySQL de imutabilidade em `ponto_marcacoes` (ADR PontoWr2)
- CNAB 240 contra retorno real do banco (Btg, Itaú, Banrisul, C6, Sicredi, Unicred)
- NFe: CST da regra fiscal (fallback 07) — ver PR #10877

**Usabilidade.**
- Valor sempre em `R$ 1.234,56` com separador BR
- Status fiscal com badge colorido (autorizada/rejeitada/cancelada)
- Prestação de contas em 1 clique (PDF + CSV)

## 6. Requisitos transversais (toda regra nova)

- ADR Nygard documentando contexto/decisão/alternativas/consequências
- Teste automatizado (unit + feature + isolation)
- Documentação em `memory/requisitos/<Mod>/SPEC.md` (US-XXX-NNN)
- Tradução pt-BR do erro user-facing
- `casos-coverage-guard` atualizado

## 7. Requisitos transversais (toda interface nova)

- Charter `.charter.md` ao lado do `.tsx`
- Padrão de tela do ADR UI-0013 (Fundações → Shell → PT → Módulo)
- Dark mode + mobile responsivo (viewport 375x812 + 768x1024)
- WCAG 2.1 AA (axe-core)
- Touch targets ≥44x44px no mobile
- PT-BR em todo label/copy/comentário
- Usar componentes shared antes de criar novo (`PageHeader`, `DataTable`, `PageFilters`, `KpiCard`, `EmptyState`, etc.)

## 8. Usabilidade por público

### Diretoria Uniafibro (3-5 pessoas)
- Desktop-first, mobile ok pra notificações
- Dashboard consolidado da rede
- Lista de pendências 30d (atraso de ata, consentimento vencido)
- Export PDF/CSV pra prestação de contas externa

### Representante de associação (1 por associação)
- Mobile/desktop paridade
- Só vê a sua associação (sem seletor "trocar")
- Lista de associadas ativas, filtro simples por nome/cidade
- Botão "convidar" gera link+PIN

### Associada (público final, fibromiálgica)
- Mobile-first rigoroso (90%+ via celular)
- Fonte ≥18px, modo escuro padrão (fotofobia)
- Texto simples sem jargão
- Links grandes (brain fog)
- 1 ação por tela
- Botão WhatsApp flutuante sempre
- "Baixar PDF" pra levar na consulta

### Público geral `uniafibro.com.br/direitos` (não logado)
- Mobile-first + SEO (busca orgânica "fibromialgia INSS")
- Disclaimer em toda página legal
- Fonte oficial linkada em cada parágrafo
- Sem rastreador / cookie banner

### Admin WR2 (Eliana, PontoWr2)
- Desktop-heavy (relatórios, apuração)
- Dados densos (tabelas grandes server-side)
- Exportação CSV/PDF frequente

## 9. Mapeamento agentes 2026-11-15

| Regra | Agente responsável (rodada 17 sessões) |
|---|---|
| Multi-tenant Tier 0 | Permissões/RBAC + Tests |
| ADR 0451 hierarquia Uniafibro | Associações merge-train |
| LGPD / dado sensível | Security review + Permissões |
| Portal link+PIN | Associações onda 6 + Security + E2E Playwright |
| Fiscal / Financeiro | Gerente de Merge (#10879 NFe) + Smoke Eliana/Larissa |
| Mobile/responsivo | Site + Uniafibro direitos + A11y Lighthouse |
| Disclaimer legal | Uniafibro direitos |
| Captação (Cirlei) | Captação de Recursos |
| Carga/portal com burst | Load test k6 |

---

**Última atualização:** 2026-11-15 (Cowork orchestrator, autorizado por Wagner).
