---
name: Persistent Layouts (Inertia) é o padrão para pages Inertia
description: Todas as pages Inertia do projeto usam Component.layout persistente — AppShell não remonta entre navegações
type: feedback
originSessionId: d094ef8e-0702-4f49-b9b9-c6fc2996415c
---
**Regra:** Toda page Inertia React nova no OI Impresso deve usar o padrão Persistent Layout — não envolver o conteúdo em `<AppShell>...</AppShell>` diretamente, e sim usar `Component.layout = (page) => <AppShell>{page}</AppShell>`.

**Why:** Wagner pediu explicitamente ("quando clicar no menu não pode fazer reload, no minimo manter o menu travado imagino") depois que cada clique recriava o `<aside>`, piscava a UI, e perdia estado (accordion aberto no sidebar). Resolvido em 2026-04-23 convertendo ~40 pages (Ponto/MemCofre/Essentials/Modules). Verificado: `sameNode=true` para o `<aside>` em navegações consecutivas.

**How to apply:**

1. Imports:
   ```tsx
   import AppShell from '@/Layouts/AppShell';
   import { Head, Link, ... } from '@inertiajs/react';
   import type { ReactNode } from 'react';
   ```

2. No return, use Fragment + Head — NÃO use `<AppShell ...>`:
   ```tsx
   return (
     <>
       <Head title="Titulo da Página" />
       <div>...conteúdo...</div>
     </>
   );
   ```

3. Depois do `export default function ComponentName(...)`, adicione:
   ```tsx
   ComponentName.layout = (page: ReactNode) => (
     <AppShell breadcrumb={[{ label: 'Módulo' }, { label: 'Tela' }]}>
       {page}
     </AppShell>
   );
   ```

4. NÃO passe `moduleNav` prop — `AppShell` detecta o topnav automaticamente pelo root da URL via `useAutoModuleNav()` lendo `shell.topnavs` compartilhado no Inertia.

5. Breadcrumb é ESTÁTICO (não tem acesso a props/state do componente). Para info dinâmica use `<Head title="...">` e `<h1>` na página.

6. `useModuleNav` NÃO deve ser usado em pages novas — ele existe como legado mas foi substituído pelo `useAutoModuleNav` interno no AppShell.

**Armadilha conhecida:** se a página ainda usa o padrão antigo (`<AppShell title="..." moduleNav={...}>`), a sidebar vai piscar a cada clique e o estado visual (accordion) se perde. Se encontrar pages assim, converter pro padrão Persistent Layout.
