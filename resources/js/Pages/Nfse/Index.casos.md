---
id: resources-js-pages-nfse-index-casos
casos: NFS-e emitidas · /nfse
irmaos: Index.charter.md (lei) · Index.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
related_us: [US-NFSE-008]
last_run: "2026-09-30"
last_run_ci: "0 UC executado — casos nascem neste PR; veredito pendente da lane PHP / Pest (Unit)"
---

# Casos de Uso & Aceite — NFS-e emitidas (`/nfse`)

> **Âncora:** os UC derivam do [charter](Index.charter.md) (Goals · Non-Goals · Anti-hooks) e da
> [US-NFSE-008](../../../../memory/requisitos/NFSe/SPEC.md) — **nunca do `Index.tsx`**
> ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05). Não há SDD do módulo NFSe.

⚖️ **FORÇA DO VEREDITO — leia antes de confiar no status.** O teste que cita estes UC
([`NfseTelasContratoTest`](../../../../tests/Feature/Modules/NFSe/NfseTelasContratoTest.php)) roda na lane sqlite per-PR `PHP / Pest (Unit)` do `ci.yml`
(registrado em `.github/ci-sqlite-pest.list` neste mesmo PR — **registrar o arquivo no repo não é
a lane executá-lo**, §5 2026-08-02). É **DB-less de propósito**: a lane não migra o schema, então
nenhum caso toca tabela — isolamento Tier 0 é provado no SQL que o Eloquent monta, regra fiscal no
`Validator` com as rules reais do FormRequest, autorização no `authorize()` do FormRequest com um
usuário-dublê. Nenhum desses caminhos vira skip-as-pass. Os 10 arquivos de `Modules/NFSe/Tests/`
**não rodam em lane nenhuma** (medido 2026-09-30 por `test-lane-coverage.mjs --json`: 10 de 10
órfãos) — por isso nenhum UC daqui se apoia neles.

Tenant fictício **98** ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
Nunca biz=4.

**Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC, veredito pendente da lane ·
⬜ não verificado · ❌ quebrou. Este PR **não executou teste** (CT 100/CI — ADR 0062).

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-NFSL-01 | A listagem só mostra notas do business da sessão | must `[T0]` | charter Non-Goals (*"NÃO pagina além do escopo do tenant"*) · ADR 0093 | `NfseTelasContratoTest` | 🧪 |
| UC-NFSL-02 | Filtros aceitam só status conhecido, data `Y-m-d` e busca curta | must | charter Goals (filtros status/período/busca) | `NfseTelasContratoTest` | 🧪 |
| UC-NFSL-03 | A listagem exige sessão autenticada e é só leitura | must | charter Anti-hooks (*"NÃO grava nada no backend em GET"*) | `NfseTelasContratoTest` | 🧪 |

## UC-NFSL-01 · A listagem só mostra notas do business da sessão · `must` `[T0]`

- **Persona:** operador fiscal do business 98; um segundo business (97) como contraparte.
- **Aceite:** Dado um operador logado no business 98 · Quando a listagem consulta `NfseEmissao` ·
  Então a consulta sai com `nfse_emissoes.business_id = 98` — e trocar o business da sessão troca
  o filtro.
- **Teste:** `NfseTelasContratoTest` — *"UC-NFSL-01 · a consulta da listagem sai filtrada pelo business da sessao"*.
- **Regressão que defende:** remover o trait `NfseBusinessScope` do model (o controller não aplica
  `where business_id` próprio na listagem — depende inteiramente do scope).
- **Status: 🧪**

## UC-NFSL-02 · Filtros aceitam só status conhecido, data Y-m-d e busca curta · `must`

- **Aceite:** Dado um filtro de status fora da lista, data em outro formato, `ate` antes de `de`,
  ou busca com mais de 120 caracteres · Quando a listagem é pedida · Então o filtro é recusado; e
  a combinação válida (e a ausência de filtro) passa.
- **Teste:** `NfseTelasContratoTest` — *"UC-NFSL-02 · filtros da lista aceitam so status conhecido…"*.
- **Status: 🧪**

## UC-NFSL-03 · A listagem exige sessão autenticada e é só leitura · `must`

- **Aceite:** Dado a rota `nfse.index` · Então ela passa pelo middleware `auth` e responde só a
  `GET`/`HEAD`.
- **Teste:** `NfseTelasContratoTest` — *"UC-NFSL-03 · a listagem exige sessao autenticada e e somente leitura"*.
- **Status: 🧪**

## Fora do contrato por enquanto

- `[BACKLOG]` atalhos de teclado (`N`/`J`/`K`/`/`/`Enter`) e filtros persistidos em
  `localStorage` são comportamento de cliente — sem teste de browser nesta lane; viram UC quando
  ganharem spec Playwright que os cite.
- `[BACKLOG]` a permissão `nfse.view` no `index()` é checada por `$this->authorize()` no
  controller; provar isso exige a pilha de middleware com banco (lane MySQL). Fica fora até a
  lane NFSe existir.
