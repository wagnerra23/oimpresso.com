---
id: resources-js-pages-nfse-index-casos
casos: NFS-e emitidas · /nfse
irmaos: Index.charter.md (lei) · Index.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
related_us: [US-NFSE-008]
last_run: "2026-11-16"
last_run_ci: "UC-NFSL-01..03 na lane sqlite do ci.yml; UC-NFSL-04/05 na lane nfse-pest (MySQL) — veredito pendente deste PR"
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
| UC-NFSL-02 | Filtros aceitam só status conhecido, competência `AAAA-MM` e busca curta | must | charter Goals (filtros status/período/busca) | `NfseTelasContratoTest` | 🧪 |
| UC-NFSL-03 | A listagem exige sessão autenticada e é só leitura | must | charter Anti-hooks (*"NÃO grava nada no backend em GET"*) | `NfseTelasContratoTest` | 🧪 |
| UC-NFSL-04 | O filtro de competência da tela filtra a lista pelo mês | must | charter Goals (*"período (de/ate por competência)"*) · US-NFSE-008 | `NfseIndexTelaTest` | 🧪 |
| UC-NFSL-05 | A lista não leva o XML da nota | must | charter Non-Goals (*"NÃO mostra XML/RPS bruto na listagem"*) | `NfseIndexTelaTest` | 🧪 |

## UC-NFSL-01 · A listagem só mostra notas do business da sessão · `must` `[T0]`

- **Persona:** operador fiscal do business 98; um segundo business (97) como contraparte.
- **Aceite:** Dado um operador logado no business 98 · Quando a listagem consulta `NfseEmissao` ·
  Então a consulta sai com `nfse_emissoes.business_id = 98` — e trocar o business da sessão troca
  o filtro.
- **Teste:** `NfseTelasContratoTest` — *"UC-NFSL-01 · a consulta da listagem sai filtrada pelo business da sessao"*.
- **Regressão que defende:** remover o trait `NfseBusinessScope` do model (o controller não aplica
  `where business_id` próprio na listagem — depende inteiramente do scope).
- **Status: 🧪**

## UC-NFSL-02 · Filtros aceitam só status conhecido, competência AAAA-MM e busca curta · `must`

- **Aceite:** Dado um filtro de status fora dos 5 que a coluna aceita (rascunho, processando,
  emitida, cancelada, erro), competência fora do formato `AAAA-MM`, `ate` antes de `de`,
  ou busca com mais de 120 caracteres · Quando a listagem é pedida · Então o filtro é recusado; e
  a combinação válida (e a ausência de filtro) passa.
- **Teste:** `NfseTelasContratoTest` — *"UC-NFSL-02 · filtros da lista aceitam so status conhecido…"*.
- **Status: 🧪**

## UC-NFSL-03 · A listagem exige sessão autenticada e é só leitura · `must`

- **Aceite:** Dado a rota `nfse.index` · Então ela passa pelo middleware `auth` e responde só a
  `GET`/`HEAD`.
- **Teste:** `NfseTelasContratoTest` — *"UC-NFSL-03 · a listagem exige sessao autenticada e e somente leitura"*.
- **Status: 🧪**

## UC-NFSL-04 · O filtro de competência da tela filtra a lista pelo mês · `must`

- **Persona:** operador fiscal do business 98; o business 99 como contraparte.
- **Aceite:** Dado notas de setembro e outubro no business 98 e uma de setembro no 99 · Quando a
  tela pede `/nfse?de=2026-09&ate=2026-09` (o valor do `<input type="month">`) · Então a lista
  responde 200 com a nota de setembro do 98, sem a de outubro e sem a do 99; e outubro traz só a
  de outubro.
- **Teste:** `NfseIndexTelaTest` — *"UC-NFSL-04 · o filtro de competencia da tela (AAAA-MM) filtra a lista pelo mes"* (lane `nfse-pest`, MySQL).
- **Regressão que defende:** até 2026-11-16 a regra pedia `Y-m-d` e todo filtro de competência
  voltava com erro de validação (auditoria NFSe, lote 3).
- **Status: 🧪**

## UC-NFSL-05 · A lista não leva o XML da nota · `must`

- **Aceite:** Dado uma nota com `xml_envio` e `xml_retorno` gravados · Quando a lista é carregada ·
  Então a linha da nota não tem `xml_envio`, `xml_retorno` nem `descricao`, e a competência vem
  como `MM/AAAA`.
- **Teste:** `NfseIndexTelaTest` — *"UC-NFSL-05 · a lista nao leva o XML da nota e mostra a competencia como mes/ano"*.
- **Regressão que defende:** a prop `notas` serializava o model inteiro, com a DPS e a resposta
  da Receita de cada nota da página.
- **Status: 🧪**

## Fora do contrato por enquanto

- `[BACKLOG]` atalhos de teclado (`N`/`J`/`K`/`/`/`Enter`) e filtros persistidos em
  `localStorage` são comportamento de cliente — sem teste de browser nesta lane; viram UC quando
  ganharem spec Playwright que os cite.
- `[BACKLOG]` a permissão `nfse.view` no `index()` é checada por `$this->authorize()` no
  controller; provar isso exige a pilha de middleware com banco (lane MySQL). Fica fora até a
  lane NFSe existir.
