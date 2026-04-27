# Sessão 2026-04-27 — Cockpit consolidado, conhecimento antigo depreciado

**Operador:** Claude (Opus 4.7, 1M context)
**Solicitante:** Wagner
**Branch:** `feat/copiloto-cockpit-piloto` (em produção como piloto)

---

## Pedido

Wagner pediu re-análise da memória após implementar o Cockpit, identificando conhecimento antigo conflitante e marcando como depreciado, indicando o novo padrão superior.

## Análise feita

Auditei **5 documentos de memória** com conteúdo de UI/layout:

| Documento | Status anterior | Conflito com Cockpit | Ação |
|---|---|---|---|
| ADR raiz 0008 (sidebar 1-item + tabs) | accepted | ❌ Total | **Superseded by 0039 + UI-0008** |
| ADR UI-0006 (template tela operacional) | accepted | ⚠️ Envelope mudou | **Mantida — conteúdo segue, wrapping migra de AppShell → AppShellV2** |
| ADR UI-0007 (topbar desktop removida) | accepted | ⚠️ Parcial | **Mantida pra AppShell legado · superseded pra Cockpit** |
| ADR UI-0001 a 0005 (Tailwind/shadcn/lucide/dark/shared) | accepted | ✅ Compatível | **Mantidas como base universal** |
| Auto-mem `project_sidebar_groups_2026_04_27` | active | ❌ Posição mudou | **Marcada obsoleta** — superadmin agora no rodapé do Cockpit, não no topo |

## O que foi entregue nesta sessão

### Documentos novos
- [`memory/requisitos/_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md`](../requisitos/_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md) — ADR formal consolidando Cockpit como layout-mãe + mapa "qual layout pra qual tela" + plano de migração 6 fases.

### Documentos atualizados
- [`memory/decisions/0008-sidebar-unica-tabs-horizontais.md`](../decisions/0008-sidebar-unica-tabs-horizontais.md) — banner DEPRECADO no topo, status superseded.
- [`memory/requisitos/_DesignSystem/adr/ui/0007-...`](../requisitos/_DesignSystem/adr/ui/0007-topbar-desktop-removida-breadcrumb-primeira-linha.md) — escopo redefinido (vale só pra AppShell legado).
- [`memory/requisitos/_DesignSystem/adr/ui/0006-...`](../requisitos/_DesignSystem/adr/ui/0006-padrao-tela-operacional.md) — anotada coexistência com Cockpit.
- [`memory/requisitos/_DesignSystem/SPEC.md`](../requisitos/_DesignSystem/SPEC.md) — adicionadas regras **R-DS-009** (Cockpit é envelope), **R-DS-010** (Apps Vinculados), **R-DS-011** (origin badges 5 cores), **R-DS-012** (localStorage namespacing).
- [`memory/requisitos/_DesignSystem/CHANGELOG.md`](../requisitos/_DesignSystem/CHANGELOG.md) — entrada `[0.3.0] - 2026-04-27` com sumário das mudanças.

## Padrão antigo vs novo (resumo executivo)

```
ANTES (até 2026-04-26)
─────────────────────────────────────────────
<AppShell>  (sidebar accordion vertical, dark, 1 item por módulo)
  <ModuleTopNav> (opcional, abas horizontais do módulo)
  [breadcrumb 1ª linha]
  <PageHeader />
  <KpiGrid> (2-6 cards)
  <PageFilters />
  <Card><Table /></Card>
  <BulkActionBar />
</AppShell>

DEPOIS (2026-04-27 em diante)
─────────────────────────────────────────────
<AppShellV2 className="cockpit">  (3 colunas)
  <Sidebar 260px>
    CompanyPicker (dropdown empresas)
    Tabs Chat ↔ Menu
    body-tab Chat: Atalhos + Fixadas + Rotinas + Recentes
    body-tab Menu: shell.menu real
    Footer: superadmin items + user dropdown rico
  </Sidebar>
  <Main 1fr>
    <Topbar>breadcrumb dinâmico + ações contextuais + toggle apps</Topbar>
    <ThreadHeader avatar nome dotOnline actions /> (chat)
      OU <PageHeader /> + KpiGrid + PageFilters + Table (CRUD)
    <Content específico do tipo>
    <Composer /> (chat)
  </Main>
  <AppsVinculados 320px>
    <LBlock OS />
    <LBlock Cliente CRM />
    <LBlock Financeiro />
    <LBlock Anexos />
    <LBlock Historico />
  </AppsVinculados>
  <TweaksPanel> (FAB) — vibe / densidade / accent hue
</AppShellV2>
```

## Por que o novo padrão é superior

| Dimensão | AppShell legado | Cockpit |
|---|---|---|
| **Densidade de informação** | 1 tela por contexto — usuário precisa orbitar | Chat + tarefa + apps vinculados juntos |
| **Cross-módulo** | Cada módulo isolado | LinkedApps reagem à entidade em foco (OS, cliente) |
| **Sidebar** | Vertical accordion AdminLTE | Dual Chat↔Menu — 1 painel pra navegar, outro pra operar |
| **Customização** | Dark mode (UI-0004) | Vibes (3 modos) + Densidade + Accent hue em runtime |
| **Atalhos teclado** | Nenhum padrão | J/K/E/A/⌘K canônicos |
| **Persistência** | Tema só | Aba/conversa/filtros/tweaks/painéis tudo em localStorage |
| **Origem visual** | Sem código de cor por módulo | 5 cores semânticas (OS/CRM/FIN/PNT/MFG) |

## Arquivos não-tocados (continuam válidos)

- `_DesignSystem/adr/ui/0001-tailwind-4-...` — fundação CSS continua Tailwind 4
- `_DesignSystem/adr/ui/0002-shadcn-ui-...` — primitivas continuam shadcn (Button, Card, Badge, Sheet — usadas no Cockpit também)
- `_DesignSystem/adr/ui/0003-lucide-react-...` — única iconografia (Cockpit usa lucide direto)
- `_DesignSystem/adr/ui/0004-dark-mode-...` — coexiste; Cockpit oferece Vibes adicionais
- `_DesignSystem/adr/ui/0005-product-components-...` — `Components/shared/` continua canônico pra telas que usam o template UI-0006
- ADR raiz 0011 (padrão Jana) — continua aplicável pra estrutura interna de cada módulo PHP

## Próximos passos sugeridos

1. **Mergear PR da branch `feat/copiloto-cockpit-piloto`** no `main` (review humano + smoke test) → cockpit estabilizado
2. **ADR UI-0008 fica oficial após merge** (hoje está aprovada mas o código piloto está em branch)
3. **Fase 3 do plano de migração**: plugar chat real do Copiloto (sai do mock, composer envia pra `POST /copiloto/conversas/{id}/mensagens`)
4. **Fase 4**: implementar `TaskProvider` interface + `TaskRegistry` service no backend, primeiro provider piloto em Officeimpresso (`OsAprovarArteTask`)
5. **Fase 5**: switch real de empresa quando "grupo econômico" for modelado (User belongsTo Business 1:1 hoje, precisa M:N pra suportar múltiplos)
6. **Fase 6**: `useV2Shell` flag vira default → AppShell legado fica só pra Showcase/Modulos/standalone

## Refs

- ADR UI-0008 (este consolidado): `memory/requisitos/_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md`
- ADR raiz 0039 (decisão original do Cockpit): `memory/decisions/0039-ui-chat-cockpit-padrao.md`
- CLAUDE.md §10 (regras pro agente): instrução universal pra criar/alterar tela React
- Branch produção: `feat/copiloto-cockpit-piloto` em `https://oimpresso.com/copiloto/cockpit`
- Protótipo de referência: projeto Cowork "Oimpresso ERP Comunicação Visual", arquivo `Oimpresso ERP - Chat.html`
