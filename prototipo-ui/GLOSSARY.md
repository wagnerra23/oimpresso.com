# GLOSSARY.md — termos design ↔ Inertia/shadcn no oimpresso

> Mapa pra Claude Code traduzir protótipo Cowork (genérico) → Inertia/React real sem reinventar.

## Componentes (Cowork → shadcn no oimpresso)

| Cowork (genérico) | oimpresso (shadcn + canon) | Notas |
|---|---|---|
| `<button>` raw | `<Button variant="default \| outline \| ghost \| destructive">` | Tamanho `sm \| default \| lg` |
| `<input type="text">` | `<Input>` | Sempre dentro de `<Label>` |
| `<select>` | `<Select>` (Radix) | Cuidado: `value=""` quebra Radix — usar sentinel |
| `<textarea>` | `<Textarea>` | |
| `<table>` | `<DataTable>` (Components/shared) | Locale pt-BR `language: { url: asset(...) }` |
| `<dialog>` modal | `<Sheet>` (drawer lateral) | Modal full-screen é proibido (regra dura Cockpit V2) |
| Card simples | `<Card><CardHeader><CardContent>` | `bg-background shadow-sm` (não `bg-card flat`) |
| Tabs raw | `<Tabs><TabsList><TabsTrigger>` | `text-xs` em TabsTrigger (canon) |
| Toast/notification | `<Toaster>` + `toast()` (sonner) | |
| Dropdown menu | `<DropdownMenu>` (Radix) | |
| Popover | `<Popover>` | |
| Tooltip | `<Tooltip>` | |
| Combobox/autocomplete | `<Command>` (cmdk) | Ver `Sells/_components/CustomerSearchAutocomplete.tsx` |
| Date picker | `<DatePicker>` ou `<Calendar>` | Cuidado: shift +3h ROTA LIVRE |
| Avatar/initials | `<Avatar><AvatarImage><AvatarFallback>` | |
| Badge | `<Badge variant="default \| secondary \| outline \| destructive">` | |
| Skeleton loading | `<Skeleton>` | |
| Empty state | `<EmptyState>` (Components/shared) | CTA convite, não só mensagem |

## Layout (Cowork → Cockpit V2)

| Cowork | oimpresso |
|---|---|
| Single page sem header | `<AppShellV2>` wrapper (header sticky + sidebar) |
| Header simples | `<PageHeader>` (Components/shared) — título + breadcrumb + ações |
| Footer simples | Footer sticky com action bar (`fixed bottom-0`) |
| Sidebar inventada | Sidebar do `AppShellV2` (DataController por módulo) |
| Topnav inventada | `topnav.php` declarativo do módulo |

## Cores (Cowork → tokens semânticos)

| Cowork hex inline | Token semântico oimpresso |
|---|---|
| `#3b82f6` (azul primário) | `bg-primary` `text-primary-foreground` |
| `#fff` (branco) | `bg-background` |
| `#000` / `#111827` | `text-foreground` |
| `#6b7280` (cinza médio) | `text-muted-foreground` |
| `#f9fafb` (cinza fundo) | `bg-muted` ou `bg-muted/30` |
| `#10b981` (verde sucesso) | `bg-emerald-50 text-emerald-700` |
| `#f59e0b` (amarelo warn) | `bg-amber-50 text-amber-700` |
| `#ef4444` (vermelho erro) | `bg-rose-50 text-rose-700` |
| `#3b82f6` (azul info) | `bg-sky-50 text-sky-700` |

**NÃO traduzir** opacity tricks (`bg-amber-500/10`) — preferimos escala warm semântica.

## Texto (inglês → PT-BR obrigatório)

| Inglês comum no Cowork | PT-BR oimpresso |
|---|---|
| "Sales" | "Vendas" |
| "Customer" / "Client" | "Cliente" |
| "Product" | "Produto" |
| "Save" | "Salvar" |
| "Cancel" | "Cancelar" |
| "Delete" | "Excluir" |
| "Search…" | "Buscar…" |
| "Filter" | "Filtrar" / "Filtros" |
| "Total" | "Total" (igual) |
| "Status" | "Situação" ou "Status" (contexto) |
| "Add new" | "Novo" / "Adicionar" |
| "Loading…" | "Carregando…" |
| "No results" | "Nenhum resultado" |
| "Settings" | "Configurações" / "Ajustes" |
| "Sign in" | "Entrar" |
| "Welcome back" | "Bem-vindo de volta" |
| "Required field" | "Campo obrigatório" |
| "Invoice" | "Nota fiscal" / "NFe" / "NFC-e" (contexto) |
| "Payment" | "Pagamento" |
| "Due date" | "Vencimento" |
| "Paid" | "Pago" |
| "Pending" | "Pendente" |
| "Overdue" | "Atrasado" / "Vencido" |
| "Draft" | "Rascunho" |

## Domínio negócio (manter PT-BR mesmo se Cowork mudou)

| Termo | Tradução fiel |
|---|---|
| "Sale" / "Sell" | "Venda" |
| "Repair order" | "Ordem de Serviço" / "OS" |
| "Time tracking" | "Ponto" |
| "Employee" | "Colaborador" |
| "Tenant" | "Empresa" / "Negócio" (`business`) |
| "Tax" | "Tributo" / "ICMS/PIS/COFINS" (contexto) |
| "Brand" | "Marca" |
| "Stock" | "Estoque" |
| "Variation" | "Variação" |

## Routes (hardcoded → Ziggy)

| Cowork | Inertia oimpresso |
|---|---|
| `href="/sells"` | `href={route('sells.index')}` |
| `fetch('/api/sells')` | `router.post(route('sells.store'), data)` |
| `<Link to="/sells/123">` | `<Link href={route('sells.show', 123)}>` |

## Form handling (mock → Inertia)

| Cowork (mock) | Inertia oimpresso |
|---|---|
| `useState({})` + manual handlers | `useForm({})` (Inertia) |
| `fetch().then()` | `form.post(route('...'), { onSuccess, onError })` |
| `<input value={x}>` | `<input value={data.x} onChange={e => setData('x', e.target.value)}>` |
| Validation client-only | Validation server-side em FormRequest, erros via `errors.x` |

## Money + Date (mock → format BR)

| Cowork | Inertia oimpresso |
|---|---|
| `$1,000.00` | `R$ 1.000,00` via `Intl.NumberFormat('pt-BR', {style:'currency',currency:'BRL'})` |
| `01/15/2026` | `15/01/2026` via `format_date()` server-side (preserva shift +3h ROTA LIVRE) |
| `1234567890` (CPF) | `123.456.789-00` via mask |
| `12345678000123` (CNPJ) | `12.345.678/0001-23` via mask |

## Atalhos (canon Cockpit V2)

| Atalho | Função |
|---|---|
| `J` / `K` | navegar lista (down/up) |
| `E` | editar item selecionado |
| `A` | aprovar (master-detail) |
| `/` | abrir busca |
| `Esc` | fechar drawer/modal |
| `⌘+Enter` / `Ctrl+Enter` | submeter form |
| `?` | abrir cheatsheet |

## Pegadinhas conhecidas

- ❌ **`SelectItem value=""` em Radix** — quebra. Usar sentinel string (`"all"`, `"none"`).
- ❌ **DataTable sem locale pt-BR** — `language: { url: asset('locale/datatables/pt-BR.json') }`.
- ❌ **`route()` antes de Ziggy carregar** — ReferenceError silencioso. Sempre import Ziggy ou usar `useRoute()`.
- ❌ **`format_date()` client-side** — quebra shift +3h. Server-side sempre.
- ❌ **`session('business.x')`** — Eloquent não responde dot-notation. Usar `session('business_timezone')`.
