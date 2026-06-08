# Brief de design — Tab Endereço vira LISTA + seletor na venda (US-CRM-078)

> **Para o loop Cowork / Claude Design** (ADR 0114 + skill `mwart-comparative`).
> Backend **PRONTO e validado no CT-100 MySQL** — design faz só o **visual** das 2 telas.
> Fluxo: protótipo Cowork → `*-visual-comparison.md` → **Wagner aprova o screenshot** → Inertia `.tsx` (MWART, ADR 0104).

## Persona
**Larissa** @ ROTA LIVRE (biz=4, vestuário) — dona de PME, monitor 1280×1024, não-técnica. Cadastra ~30 clientes/dia em pico. Quer registrar **vários endereços** por cliente (matriz/filial/casa/obra) e **escolher na hora da entrega**.

## Backend já entregue (não refazer — só consumir)
- Tabela `contact_addresses` + model `App\ContactAddress` (multi-tenant Tier 0) — PR [#2095](https://github.com/wagnerra23/oimpresso.com/pull/2095).
- `ContactAddressController` + rotas — PR [#2096](https://github.com/wagnerra23/oimpresso.com/pull/2096):
  - `GET    /cliente/{id}/enderecos` → `{ addresses: [...] }`
  - `POST   /cliente/{id}/enderecos` (cria) · `PATCH /…/{addressId}` (edita) · `DELETE /…/{addressId}` (remove)
  - `PATCH  /cliente/{id}/enderecos/{addressId}/padrao` (marca padrão)
- Cada endereço: `{ id, label, zip_code, address_line_1, numero, address_line_2, neighborhood, city, state, city_code, is_default, is_shipping, one_line }`.
- Invariantes garantidas pelo backend: 1 padrão + 1 entrega por contato; o endereço `is_default` é espelhado nos campos inline de `contacts` (compat NFe/Sells).
- Lookup CEP reaproveitável: `GET /cliente/lookup/cep/{cep}` → `{ logradouro, complemento, bairro, cidade, uf }`.

## Tela 1 — Tab "Endereço" do drawer 760 (Cliente)
Hoje: **1 endereço só**, campos soltos (`EnderecoTab.tsx`). Vira **LISTA**:
- **Cards** de endereço: `label` (rótulo) + endereço em 1 linha (`one_line`) + badges **Padrão** (★) e **Entrega** (🚚).
- Ações por card: **editar** · **remover** · **marcar como padrão**.
- Botão **"Adicionar endereço"** → form com os campos + **rótulo**, reusando **Buscar CEP** (ViaCEP).
- Estados: lista vazia ("Nenhum endereço cadastrado"), loading, erro.
- Contexto: drawer 760px (ADR 0179), cabe em 1280px sem scroll horizontal.

## Tela 2 — Seletor de endereço na venda
`resources/js/Pages/Sells/Create.tsx` (~linha 1530, label "Endereço de entrega") + `_components/SaleSheet.tsx`:
- O input livre vira **dropdown** que lista os endereços do cliente selecionado (`one_line` + badge entrega) + opção **"Outro (digitar)"** → grava em `shipping_address`.
- `CustomerSearchResult` já traz `shipping_address`; buscar endereços on-select via `GET /cliente/{id}/enderecos`.

## Referência funcional (lógica, NÃO o visual final)
- Branch **`feat/crm-078-enderecos-frontend-draft`** (commit `85fd76af0`) — uma versão funcional do `EnderecoTab` lista já existe: compila (Vite✓), passou ESLint✓ + **PR UI Judge✓**, mas reprovou **UI Lint** (cores hardcoded tipo `bg-amber-50`). **Design refaz a pele com tokens DS + estilo Cowork** — pode aproveitar a lógica/wiring.

## Restrições
- Constituição UI v2 + tokens DS (sem cores hardcoded — foi o que reprovou no UI Lint).
- Testes/build **no CT-100** (`docker exec -e DB_CONNECTION=mysql oimpresso-staging …`), nunca sqlite (proibicoes §Ambiente · PR #2098).
- Gate: **Wagner aprova o screenshot** antes do merge (R2/R7).

## Refs
- SPEC: `memory/requisitos/Cliente/SPEC.md §US-CRM-078`
- Protótipo Cowork base: `prototipo-ui/prototipos/clientes/` (`clientes-drawer.jsx::SectionEndereco`, `HANDOFF_CLIENTES.md`)
- ADRs: 0179 (drawer 760) · 0104 (MWART) · 0114 (Cowork loop) · 0093 (multi-tenant)
