---
tela: Clientes (Index + Drawer 760px)
prototipo: prototipo-ui/prototipos/clientes/ (HTML + 13 .jsx · KB-9.75 9,4/10 · 2026-05-22)
tela_viva: resources/js/Pages/Cliente/ (Index.tsx 114KB + Show.tsx + _drawer/* + _show/* + _components/*)
paridade_atual: 100% (tela viva À FRENTE do protótipo)
veredito: À FRENTE — protótipo é catch-up backlog, NUNCA fonte
gerado_em: '2026-06-23'
governanca:
  read_only: true
  fase: 1 (mapeamento · skill aplicar-prototipo)
  charter: resources/js/Pages/Cliente/Index.charter.md (v10 · 2026-06-13 · status live)
  spec: memory/requisitos/Crm/SPEC.md + SPEC-us-063-078.md
  adrs: ['0104', '0107', '0114', '0149', '0179', '0188', '0246', '0093']
  acao_recomendada: NENHUMA aplicação de protótipo. Tela viva supera o blueprint em todas as partes.
---

# GAP-SPEC · Clientes — protótipo Cowork vs tela viva

> **Veredito de uma linha:** a tela viva está **decisivamente à frente** do protótipo.
> O protótipo (`clientes-975.jsx` & cia, 2026-05-22) já foi inteiramente portado
> (Wave C/D/E/F + Slices US-063..078) e a tela viva **expandiu muito além** dele
> (multi-type contatos, "Outros", 8 sub-abas Operações com dados reais, KPIs reais
> de backend, soft-delete pela tela, US-078 multi-endereços já integrado).
> **NÃO aplicar o protótipo.** Ele serve apenas como referência histórica de intenção.

## Confirmação "já passou o protótipo" (RUNBOOK)

- Charter `Index.charter.md` está em **v10** (2026-06-13), `status: live`, com `mwart_pattern_reuse.blueprint_screenshot_approval: "Wagner 2026-05-21 aprovou opção A (drawer 760px)"`.
- `SPEC-us-063-078.md`: US-CRM-063..076 todas **done** (PRs #1298-1319, 2026-05). KB-9.75 Slice A (⌘K + cheat-sheet + J/K) done (PR #1309).
- Protótipo é drawer **540px mock** com `window.claude.complete` client-side, dados `window.CLIENTES` (32 mocks), favoritos/anotações em `localStorage`. A tela viva é **760px** (ADR 0179), backend real, autosave on blur com rollback, ViaCEP/BrasilAPI proxy server-side.

## Tabela de partes

| Parte | O que mudou/falta | Por quê | Esforço | Risco | Ação |
|---|---|---|---|---|---|
| **Header / PageHeader** | Protótipo: H1 "Clientes" + count + Buscar/Importar/Exportar/Novo. Viva: tudo isso **+ SLOT2_TABS multi-type** (Clientes/Fornecedores/Funcionários/Repr./Outros/Todos) + `ROLE_TITLE` dinâmico + Importar/Exportar em overflow menu (PageHeader canon). | Viva implementou ADR 0188 (multi-type) + ADR 0246 ("Outros") + PageHeader canon — nada disso existe no protótipo. | — | — | **Nenhuma** (viva à frente) |
| **KPIs** | Protótipo: **não tem KPI strip**. Viva: `KpiStripClickable` 5 cards-filtro com **counts reais do backend** (Onda 3, 2026-06-12). | Feature PTDP Onda 2/3 nasceu depois do protótipo. | — | — | **Nenhuma** |
| **Filtros** | Protótipo: 6 filtros (Tipo/Status/UF/Tags/Sem compra/Com saldo) + ActiveChips. Viva: **mesmos 6 `FilterDropdown`** (espelho fiel, `clientes-listagem.jsx`) + reset page=1. | Paridade total — viva já portou o componente. | — | — | **Nenhuma** |
| **Tabela / listagem** | Protótipo: tabela densa + avatar HSL + FrescorPill + saldo vermelho + tags + star pessoal; **+ layouts cards/split** e `density`. Viva: tabela densa equivalente com `FrescorPill`, `Pills`, `SaldoCell`, avatar. | Núcleo idêntico. Layouts **cards/split** e toggle `density` do protótipo **não foram portados** — decisão de produto (drawer 760 substituiu o split; ADR 0179). | P (se Wagner quiser cards/split) | visual | **Opcional/Backlog** — só se sinal de cliente pedir; não é regressão |
| **Abas (drawer)** | Protótipo: 8 tabs (Identificação/Contato/Endereço/Comercial/Classificação/OSs/IA/Auditoria). Viva: 6 tabs principais + chip IA + **Operações com 8 sub-abas reais** (Extrato/Vendas/Pagamentos/Docs/Pessoas/Assinaturas/Pontos/Auditoria). | Viva consolidou "OSs" do protótipo numa aba **Operações** com 8 sub-abas de dados REAIS (`_show/*`), bem além do `OssTab` mock. | — | — | **Nenhuma** (viva muito à frente) |
| **Drawer de detalhe** | Protótipo: 540px, mock, `onSaved(form)` full-form. Viva: 760px (ADR 0179), **autosave on blur** por campo, debounce 800ms, optimistic + rollback 4xx/5xx, máscaras+mod11 reais. | Arquitetura viva é estado-da-arte; protótipo é mock. | — | — | **Nenhuma** |
| **Endereços (US-078)** | Protótipo: **1 endereço só** (`SectionEndereco`, campos soltos). Viva: `EnderecoTab.tsx` já integra **`EnderecosEntregaList`** (lista multi-endereço: cards label + one_line + badges Padrão★/Entrega🚚 + add/editar/remover/marcar padrão) + canon EN schema + complemento ViaCEP. | **Catch-up legítimo do protótipo JÁ FOI FEITO** na viva. O `BRIEF-DESIGN-enderecos-lista-us078.md` é o pedido que originou e a viva já o atende (Tela 1). | — | — | **Nenhuma na tela Cliente**. Ver nota US-078 abaixo |
| **Vínculos / linked** | Protótipo: `clientes-linked.jsx` (Apps Vinculados). HANDOFF §8 diz **NÃO portar** ("painel direito exclusivo do Copiloto/Inbox", decisão PO 2026-05-21). | Explicitamente fora de escopo da tela Clientes pelo próprio handoff. | — | governança | **Nenhuma** (proibido portar) |
| **Footer** | Protótipo: footer drawer "Tudo válido / N pendências" + Cancelar/Salvar. Viva: autosave (sem botão Salvar global — pattern superior) + FieldStatus inline por campo. | Viva trocou "salvar manual" por autosave — evolução intencional. | — | — | **Nenhuma** |
| **IA (4 cards)** | Protótipo: `window.claude.complete` client-side (proibido em prod). Viva: `IATab.tsx` via Modules/Jana server-side (Resumo/Reavaliar/Próxima ação/Risco determinístico). | Viva respeita ADR (sem `window.claude.complete`, quota Copiloto). | — | — | **Nenhuma** |
| **Atalhos / ⌘K / cheat-sheet** | Paridade: viva tem `CommandPalette` ⌘K, `CheatSheet`, J/K nav, `/` foco busca (PR #1309). | Slice A portado fiel. | — | — | **Nenhuma** |

## Itens do protótipo NÃO portados (com decisão consciente — não são gaps)

1. **Layouts cards/split + toggle density** (`ClientesCards`/`ClientesSplit`) — substituídos pelo drawer 760px lateral (ADR 0179). Backlog só se cliente pedir (ADR 0105 sinal qualificado).
2. **Apps Vinculados** (`clientes-linked.jsx`) — HANDOFF §8 proíbe portar.
3. **`tweaks-panel.jsx`** — ferramenta só do mockup (BUNDLE diz "NÃO portar").
4. **KBScore badge "KB-9.75 9,4/10"** — meta-elemento do método, não vai pra produção.
5. **Favoritos/anotações em localStorage** — HANDOFF §8 proíbe em prod (deve ser banco; viva não usa localStorage hack).

## Catch-up legítimo (o que o protótipo introduziu de NOVO) — JÁ ABSORVIDO

- **US-078 endereços-lista** (BRIEF-DESIGN-enderecos-lista-us078.md): a *única* novidade real do bundle vs o que já existia. **Status na tela Cliente: FEITO** — `EnderecoTab.tsx:547` renderiza `EnderecosEntregaList` (cards, badges, add/edit/remove/marcar-padrão).
- **Nota / pendência fora desta tela:** US-078 **Tela 2** (seletor dropdown de endereço em `Sells/Create.tsx` + `_components/SaleSheet.tsx`) é o único item do BRIEF que **não é da página Cliente**. Não verificado nesta análise (escopo = Clientes). `_pendente_` — recomendo um GAP-SPEC separado pra Sells/Create se quiser confirmar. O `SPEC-us-063-078.md` ainda lista PR2/PR3 como "atrás de gate visual" (doc defasado vs código — a Tela 1 já está integrada).

## Ordem (se, e somente se, Wagner pedir algo daqui)

1. (Opcional/baixo) Confirmar US-078 Tela 2 (seletor na venda) — **fora da tela Cliente**, GAP-SPEC próprio em Sells.
2. (Opcional/produto) Layouts cards/split + density — só com sinal de cliente.
3. Nada mais. Tela viva não recebe nada do protótipo.

## Veredito final

**À FRENTE (paridade 100%, viva supera o blueprint).** O protótipo Cowork de Clientes
foi 100% portado e a tela viva evoluiu muito além dele em arquitetura (760px, autosave,
backend real), escopo (multi-type, "Outros", 8 sub-abas Operações, soft-delete) e
governança (IA server-side, sem localStorage hack). **Aplicar o protótipo seria
REGRESSÃO.** Ação: nenhuma. Manter o protótipo apenas como referência histórica de
intenção/design. O único trabalho remanescente associado ao bundle (US-078 Tela 2 –
seletor na venda) pertence a `Sells/Create.tsx`, não a esta tela.
