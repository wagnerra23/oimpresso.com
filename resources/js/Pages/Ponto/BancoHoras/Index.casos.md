---
id: resources-js-pages-ponto-banco-horas-index-casos
casos: Saldos de banco de horas por colaborador · /ponto/banco-horas
irmaos: Index.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §5.3 F6 + §6.3/§6.5 (contrato)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a porta de entrada do banco de horas — e o KPI agregado desta tela é o único lugar do módulo onde saldo de vários colaboradores é somado num número só.
owner: wagner
last_run: "2026-08-02"
last_run_ci: "4 UC executados na lane (run 30778424885): UC-BHIDX-01 e -04 pass; -02 e -03 morreram no setup por FK biz=99 sem stub (defeito de fixture, corrigido no mesmo PR). Veredito oficial vem do manifesto, não desta linha."
---

# Casos de Uso & Aceite — Saldos de banco de horas

> **Âncora:** `CU-PONTO-08` (§6.3) e `CU-PONTO-12` (§6.5) do
> [SDD](../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md) + **US-PONTO-004**
> (banco de horas com saldo + créditos/débitos) · **CLT Art. 59 §5º**. Fonte 4 (Delphi)
> **ausente** — SDD §0.1.
>
> 🔴 **`[V0]` — minuto de jornada é valor.** A REGRA MESTRE de
> [proibicoes.md](../../../../memory/proibicoes.md) vale aqui: mexer no cálculo de saldo exige
> dupla confirmação por 2 caminhos + tabela antes→depois + aprovação [W].
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Ponto · MySQL)` — **advisory**: fica vermelha
> visível, não bloqueia merge (SDD §8.1, medido em `governance/required-checks-baseline.json`).
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.
>
> 🔎 **Recibo da 1ª corrida** (run [30778424885](https://github.com/wagnerra23/oimpresso.com/actions/runs/30778424885),
> 2026-08-02 — medição datada, não afirmação atemporal): os **4 UC chegaram ao `name` do
> `<testcase>` com o hífen** (`it UC-BHIDX-01 · …`), que é o que o manifesto G-7 exige.
> `UC-BHIDX-01` e `-04` passaram; `-02` e `-03` **morreram no setup** com
> `SQLSTATE 1452 ponto_colaborador_config_business_id_foreign` — biz=99 não existe na lane
> e a FK é real. Era **defeito de fixture, não de produto**: corrigido no mesmo PR com o
> stub do business (padrão do `Wave27CrossTenantEscalaTest`). O status abaixo segue 🧪 —
> quem carimba ✅ é o manifesto via cron, nunca esta leitura.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-BHIDX-01 | A lista traz os colaboradores do meu empregador com o saldo deles | must | `CU-PONTO-08` + US-PONTO-004 | `BancoHorasIndexContratoTest` | 🧪 sem veredito |
| UC-BHIDX-02 | Saldo de outro empregador não aparece na lista | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `BancoHorasIndexContratoTest` | 🧪 sem veredito |
| UC-BHIDX-03 | Saldo de outro empregador não entra nos totais agregados | must `[T0]` `[V0]` | `CU-PONTO-12` + ADR 0093 | `BancoHorasIndexContratoTest` | 🧪 sem veredito |
| UC-BHIDX-04 | Lista e totais são carregados sob demanda, não no primeiro response | should | charter §Automation hooks + RUNBOOK-inertia-defer | `BancoHorasIndexContratoTest` | 🧪 sem veredito |

**[BACKLOG]:**

- `[BACKLOG]` Os totais exibidos batem com a soma dos saldos listados (reconciliação KPI × lista).
  É `[V0]` **de cálculo**: exige dupla confirmação por 2 caminhos + antes→depois antes de virar UC
  com teste — não se escreve assert de valor sem o protocolo da REGRA MESTRE. Os UCs abaixo provam
  **isolamento** do agregado, nunca o número apurado.
- `[BACKLOG]` Ordenação por saldo desc e paginação 30/pág (charter §Goals) — é contrato de
  apresentação sem âncora em lei nem US; vira UC quando [W] confirmar que a ordem é parte do
  contrato e não escolha de implementação.
- `[BACKLOG]` A tela não expõe a **causa** do débito: `saida_antecipada_minutos` é debitada do banco
  de horas pelo `ApuracaoService` e não tem superfície em tela nenhuma (SDD §9 D-2). O gestor vê o
  débito no saldo sem ver de onde veio. Fechar isso é decisão de produto [W], não caso de teste.

---

## UC-BHIDX-01 · A lista traz os colaboradores do meu empregador com o saldo deles · `must`

- **Persona:** gestor/RH abrindo o banco de horas para saber quem está credor e quem está devedor
  antes do fechamento do mês.
- **Aceite:** Dado um colaborador do meu business com saldo registrado · Quando abro
  `/ponto/banco-horas` e a lista carrega · Então o colaborador aparece com o saldo que está no
  ledger.
- **Teste:** `Modules/Ponto/Tests/Feature/BancoHorasIndexContratoTest.php` — `UC-BHIDX-01`.
- **Contrato:** `CU-PONTO-08` (SDD §6.3, *"saldo do colaborador + ledger de movimentos"*) ·
  US-PONTO-004 · charter §Mission (*"o gestor vê o saldo consolidado por colaborador"*).
- **Regressão que defende:** `buildSaldosPagina()` monta a linha por `transform()` com `optional()`
  encadeado em `colaborador.user`. Um eager-load removido ou um rename de relação não quebra a
  query — devolve linha com `nome: '—'` e segue verde. Este UC observa que a linha chega **com
  identidade**, não só que a rota responde 200.
- **Status: 🧪 sem veredito.**

---

## UC-BHIDX-02 · Saldo de outro empregador não aparece na lista · `must` `[T0]`

- **Persona:** plataforma multi-tenant. Saldo de banco de horas é informação salarial de pessoa
  identificada — vazamento aqui é LGPD + sigilo trabalhista, não bug de UI.
- **Aceite:** Dado um colaborador com saldo em **outro** business · Quando abro
  `/ponto/banco-horas` do meu · Então esse colaborador **não** aparece na lista.
- **Teste:** `BancoHorasIndexContratoTest.php` — `UC-BHIDX-02`.
- **Contrato:** `CU-PONTO-12` (SDD §6.5) · US-PONTO-007 ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** aqui a defesa é **dupla** — `where('business_id', $businessId)`
  explícito **e** o global scope `HasBusinessScope`. Justamente por ser dupla, remover uma das duas
  não quebra nada visível: o teste continua verde porque a outra segura. Este UC não distingue as
  duas camadas (nem deve) — ele fixa o **comportamento**, para que a remoção da última defesa
  apareça.
- **Nota de teste:** biz=1 (WR2 interno) vs `business_id` fictício — **nunca biz=4**
  ([ADR 0101](../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)).
- **Status: 🧪 sem veredito.**

---

## UC-BHIDX-03 · Saldo de outro empregador não entra nos totais agregados · `must` `[T0]` `[V0]`

- **Persona:** a mesma do UC-02, mas o vetor é outro. Uma lista escopada certo e um `sum()` escopado
  errado convivem sem sintoma: a linha alheia não aparece, e mesmo assim o número no topo da tela
  a contém.
- **Aceite:** Dado o total de crédito exibido para o meu business · Quando passa a existir um saldo
  credor em **outro** business · Então o total do meu business **não muda**.
- **Teste:** `BancoHorasIndexContratoTest.php` — `UC-BHIDX-03`.
- **Contrato:** `CU-PONTO-12` · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) ·
  `BancoHorasController::buildTotaisSaldos()` (4 agregações `sum`/`count`).
- **Regressão que defende:** os 4 agregados são queries **separadas** da lista, cada uma repetindo
  `where('business_id', …)` à mão. Repetição à mão é onde uma cópia esquece o filtro — e agregado
  não tem linha para alguém notar que sobrou. Este é o único ponto do módulo onde saldo de várias
  pessoas vira um número só; se ele vaza, vaza em silêncio.
- **Nota `[V0]`:** este UC compara o total **consigo mesmo** antes e depois de criar o registro
  alheio — prova **isolamento**, não valor apurado. Não afirma que o total está certo (isso é o
  `[BACKLOG]` de reconciliação, que exige o protocolo da REGRA MESTRE). Escrito assim de propósito:
  um assert de valor absoluto aqui dependeria do estado do banco compartilhado da lane e ficaria
  vermelho por dado alheio, não por defeito.
- **Status: 🧪 sem veredito.**

---

## UC-BHIDX-04 · Lista e totais são carregados sob demanda, não no primeiro response · `should`

- **Persona:** gestor em máquina modesta (1280px, rede de escritório). A tela precisa pintar antes
  de a paginação e as 4 agregações terminarem.
- **Aceite:** Dado que abro `/ponto/banco-horas` · Quando chega o **primeiro** response Inertia ·
  Então ele **não** carrega `saldos` nem `totais` — os dois vêm em requisição posterior.
- **Teste:** `BancoHorasIndexContratoTest.php` — `UC-BHIDX-04`.
- **Contrato:** charter §Automation hooks (*"`saldos` e `totais` vêm via `Inertia::defer`"*) ·
  [RUNBOOK-inertia-defer-pattern](../../../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md) ·
  [proibicoes.md](../../../../memory/proibicoes.md) §"Sempre fazer" (*"`Inertia::defer()` DEFAULT em
  props caras"*) · SDD §4.
- **Regressão que defende:** `defer` é invisível quando some. Trocar
  `Inertia::defer(fn () => $this->buildSaldosPagina(…))` por `$this->buildSaldosPagina(…)` deixa a
  tela **idêntica** — só mais lenta, e a lentidão só aparece em base grande, em produção. É a classe
  de regressão que nenhum smoke visual pega.
- **Nota:** a ausência da chave no primeiro response **é** o contrato aqui (props deferred não
  viajam), então assert de ausência de chave é o assert certo — não é proxy de valor.
- **Status: 🧪 sem veredito.**
