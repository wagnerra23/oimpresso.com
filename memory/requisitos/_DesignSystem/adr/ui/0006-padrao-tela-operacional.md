# ADR UI-0006 · Padrão de tela operacional (template page)

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner, Claude
- **Categoria**: ui
- **Depende de**: [0005 · Componentes shared](0005-product-components-shared.md)

## Contexto

Com a camada de componentes shared em `Components/shared/` (ADR 0005), emergiu um padrão claro pras telas CRUD/operacionais (aprovações, intercorrências, banco de horas, espelho, colaboradores, escalas, etc.). Formalizamos esse padrão pra:

1. Garantir consistência visual cross-módulo (Ponto, MemCofre, Modules, Sells, etc.)
2. Reduzir tempo de criação de tela nova (copy template + preencher)
3. Facilitar auditoria (C16 futura no ModuleAuditor: "segue template oficial?")

## Decisão

Toda **tela operacional** (listagem filtrada com ações) segue este esqueleto:

```tsx
<Head title="..." />
<div className="mx-auto max-w-7xl p-6 space-y-4">

  {/* 1. Cabeçalho */}
  <PageHeader icon="..." title="..." description="..." action={<Button>Nova</Button>} />

  {/* 2. KPIs (opcional, 2-6 cards clicáveis como filtros ou informativos) */}
  <KpiGrid cols={4|6}>
    <KpiCard ... onClick={toggleFilter} selected={isActive} />
  </KpiGrid>

  {/* 3. Filtros com chips ativos */}
  <PageFilters activeChips={[...]} onReset={resetAll} cols={2|3|4}>
    <Select>Tipo</Select>
    <Select>Status</Select>
    <Input>Busca</Input>
  </PageFilters>

  {/* 4. Conteúdo principal — tabela ou grid */}
  <Card>
    <CardContent className="p-0">
      {data.length === 0 ? (
        <EmptyState
          variant={hasFilters ? 'search' : 'default'}
          icon="..."
          title="..."
          description="..."
          action={hasFilters ? <Button onClick={reset}>Limpar</Button> : undefined}
        />
      ) : (
        <table>...</table>
      )}
      {/* Paginação dentro do card */}
    </CardContent>
  </Card>
</div>

{/* 5. Bulk actions (condicional, aparece só quando selectedCount > 0) */}
<BulkActionBar selectedCount={...} onClear={...}>
  <Button>Ação em lote</Button>
</BulkActionBar>

{/* 6. Dialogs de ações individuais */}
<AlertDialog>...</AlertDialog>
<Dialog>...</Dialog>

{/* 7. Persistent Layout (fora do return) */}
Component.layout = (page) => <AppShell breadcrumb={[...]}>{page}</AppShell>
```

## Regras

- Container: `max-w-7xl p-6 space-y-4` no desktop (consistente com MemCofre).
- **Sempre** `<PageHeader>` no topo — não escrever `<h1>` cru.
- **Sempre** `<EmptyState>` quando `data.length === 0` — não escrever div custom.
- **Sempre** `<StatusBadge kind=... value=...>` — não importar `Badge` cru com variant calculado em runtime.
- Se a tela tem KPIs-como-filtro, usar `<KpiCard onClick selected>` — não re-implementar botão.
- Se a tela tem bulk actions, **NÃO** esconder o backend — expor via `<BulkActionBar>`.
- Breadcrumb sempre via `Component.layout` com `AppShell breadcrumb={[...]}` — estático, nunca dinâmico.

## Consequências

**Positivas:**
- Tela nova vira copy-paste do template `_Showcase/Components.tsx` + preencher dados.
- Auditoria fica simples: checar se import de `@/Components/shared/*` está presente.
- Visual consistente entre módulos — dev que conhece Ponto aprende MemCofre/Sells em minutos.
- Qualquer melhoria no shared (ex: dark mode fix, acessibilidade) aparece em TODAS as telas automaticamente.

**Negativas:**
- Telas "singulares" (ex: `Espelho/Show` com gráfico canvas dia-a-dia) NÃO seguem o template cegamente — precisam mix. Aceitar exceção documentada em ADR per-tela.
- Formulários de criação/edição (`Create`, `Edit`) são outra família — este template é só pra **listagem**. ADR separado.

## Exceções documentadas

| Tela | Motivo |
|---|---|
| `Ponto/Espelho/Show` | Documento legal com gráfico custom + print A4 (ADR PontoWr2 UI-0001) |
| `Ponto/Dashboard/Index` | Dashboard multi-painel (mistura KPIs + atividade recente + próximos) — usa PageHeader + KpiGrid mas não Table |
| `MemCofre/Chat` | Interface conversacional, não listagem |
| `MemCofre/Memoria` | Árvore de arquivos navegável, não listagem tabular |
| Qualquer `Create.tsx`/`Edit.tsx` | Formulário, não listagem — usa padrão separado (ADR futura) |

## Validação

Telas que devem seguir (19 do Ponto + análogas em outros módulos):

**Listagens com filtros (template aplicável):**
- `Ponto/Aprovacoes/Index` ✅ **(refatorada 2026-04-24, prova de conceito)**
- `Ponto/Intercorrencias/Index` ⏳ fase 2
- `Ponto/BancoHoras/Index` ⏳ fase 2
- `Ponto/Espelho/Index` ⏳ fase 2
- `Ponto/Colaboradores/Index` ⏳ fase 2
- `Ponto/Escalas/Index` ⏳ fase 2
- `Ponto/Importacoes/Index` ⏳ fase 2
- `Ponto/Relatorios/Index` ⏳ fase 2 (adaptação — não tem dados listados)
- `MemCofre/Dashboard` ⏳ (já próximo do template, revisar)
- `MemCofre/Inbox` ⏳ (já próximo do template, revisar)

## Como consultar

Ver sempre o **showcase** em [`/showcase/components`](https://oimpresso.test/showcase/components) como referência visual rodando. Se o padrão mudar, atualizar showcase + esta ADR na mesma PR.
