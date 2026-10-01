---
id: resources-js-pages-nfse-show-casos
casos: Detalhe da NFS-e · /nfse/{id}
irmaos: Show.charter.md (lei) · Show.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
related_us: [US-NFSE-006, US-NFSE-010]
last_run: "2026-09-30"
last_run_ci: "0 UC executado — casos nascem neste PR; veredito pendente da lane PHP / Pest (Unit)"
---

# Casos de Uso & Aceite — Detalhe da NFS-e (`/nfse/{id}`)

> **Âncora:** os UC derivam do [charter](Show.charter.md) e de
> [US-NFSE-006 / US-NFSE-010](../../../../memory/requisitos/NFSe/SPEC.md) — **nunca do `Show.tsx`**.

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
| UC-NFSD-01 | O id da URL só resolve nota do business da sessão | must `[T0]` | charter Non-Goals (*"NÃO acessa nota de outro tenant"*) · ADR 0093 | `NfseTelasContratoTest` | 🧪 |
| UC-NFSD-02 | Cancelar exige motivo de 15 a 255 caracteres | must | charter Non-Goals (*"NÃO cancela sem motivo"*) · US-NFSE-006 | `NfseTelasContratoTest` | 🧪 |
| UC-NFSD-03 | Só quem tem `nfse.cancel` cancela, e só por POST | must `[T0]` | charter (*"Gates … `nfse.cancel`"*) · Anti-hooks | `NfseTelasContratoTest` | 🧪 |
| UC-NFSD-04 | Emitida, cancelada e erro têm rótulo e cor distintos | should | charter Goals (*"`StatusBadge`"*) · UX targets | `NfseTelasContratoTest` | 🧪 |

## UC-NFSD-01 · O id da URL só resolve nota do business da sessão · `must` `[T0]`

- **Aceite:** Dado o operador do business 98 abrindo `/nfse/12345` · Quando o route-model-binding
  resolve a nota · Então a consulta exige `id = 12345` **e** `business_id = 98` — nota de outro
  business não é encontrada (404), em vez de exibida.
- **Teste:** `NfseTelasContratoTest` — *"UC-NFSD-01 · o id da URL so resolve nota do business da sessao"*.
- **Regressão que defende:** `show()`, `cancelar()` e `pdf()` recebem a nota só por binding — sem
  o scope, qualquer id de outro tenant abriria, cancelaria ou baixaria.
- **Status: 🧪**

## UC-NFSD-02 · Cancelar exige motivo de 15 a 255 caracteres · `must`

- **Aceite:** Dado motivo ausente, com 14 ou com 256 caracteres · Então o cancelamento é recusado;
  com 15 e com 255 · Então passa.
- **Teste:** `NfseTelasContratoTest` — *"UC-NFSD-02 · cancelar exige motivo entre 15 e 255 caracteres"*.
- **Status: 🧪**

## UC-NFSD-03 · Só quem tem `nfse.cancel` cancela, e só por POST · `must` `[T0]`

- **Aceite:** Dado request sem usuário ou com só `nfse.view` · Então o cancelamento é recusado; com
  `nfse.cancel` · Então é aceito. A rota `nfse.cancelar` é só `POST` e passa por `auth`.
- **Teste:** `NfseTelasContratoTest` — *"UC-NFSD-03 · so quem tem a permissao nfse.cancel cancela…"*.
- **Status: 🧪**

## UC-NFSD-04 · Emitida, cancelada e erro têm rótulo e cor distintos · `should`

- **Aceite:** Dado uma nota `emitida`, uma `cancelada` e uma `erro` · Então cada uma tem rótulo
  próprio e as três cores são diferentes.
- **Teste:** `NfseTelasContratoTest` — *"UC-NFSD-04 · emitida, cancelada e erro tem rotulo e cor distintos"*.
- **Status: 🧪**

## Fora do contrato por enquanto

- `[BACKLOG]` cancelamento mantém o registro (status `cancelada`, sem `forceDelete` — charter
  Anti-hooks) — exige banco + provider; entra com a lane NFSe MySQL.
- `[BACKLOG]` reemitir só quando `status === 'erro'` — decisão de cliente (`Show.tsx`).
- `[BACKLOG]` observação, **não corrigida aqui**: o filtro da lista aceita `autorizada`,
  `pendente` e `rejeitada`, mas `statusLabel()` não tem rótulo para eles (cai no valor cru). Fica
  registrado; se é defeito ou dado que nunca ocorre é decisão do dono do módulo.
