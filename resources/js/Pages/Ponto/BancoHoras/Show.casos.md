---
id: resources-js-pages-ponto-banco-horas-show-casos
casos: Extrato de banco de horas do colaborador · /ponto/banco-horas/{colaborador}
irmaos: Show.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §5.3 F6 + §6.3 (contrato)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o extrato é o ledger que prova o saldo — e saldo de banco de horas vira dinheiro na rescisão.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Ponto · MySQL)"
---

# Casos de Uso & Aceite — Extrato de banco de horas

> **Âncora:** `CU-PONTO-08`, `CU-PONTO-09` e `CU-PONTO-12` do
> [SDD §6.3/§6.5](../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md) + **US-PONTO-004**
> e **US-PONTO-008** (append-only) · **CLT Art. 59 §5º**. Fonte 4 (Delphi) **ausente** — SDD §0.1.
>
> 🔴 **`[V0]` — minuto de jornada é valor.** A REGRA MESTRE de
> [proibicoes.md](../../../../memory/proibicoes.md) vale aqui: mexer no cálculo de saldo exige dupla
> confirmação por 2 caminhos + tabela antes→depois + aprovação [W].
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Ponto · MySQL)` — **advisory**: não bloqueia merge.
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-BHSHOW-01 | Movimento gravado não pode ser alterado nem apagado | must `[V0]` `[T0]` | `CU-PONTO-09` + US-PONTO-008 | `BancoHorasImportacaoContratoTest` | 🧪 sem veredito |
| UC-BHSHOW-02 | Ajuste manual exige justificativa e vira movimento novo | must `[V0]` | `CU-PONTO-09` + US-PONTO-004 | `BancoHorasImportacaoContratoTest` | 🧪 sem veredito |
| UC-BHSHOW-03 | Extrato de colaborador de outro empregador → 404 | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `BancoHorasImportacaoContratoTest` | 🧪 sem veredito |

**[BACKLOG]:**

- `[BACKLOG]` Saldo exibido é igual à soma dos movimentos do extrato (reconciliação ledger × saldo).
  É `[V0]` de cálculo: exige dupla confirmação por 2 caminhos antes de virar UC com teste — não se
  escreve assert de valor sem o protocolo da REGRA MESTRE.
- `[BACKLOG]` Expiração de crédito conforme validade acordada (CLT Art. 59 §5º, até 6 meses) — o tipo
  `EXPIRACAO` existe no SPEC, mas nenhuma tela do trio o exercita.
- `[BACKLOG]` **Lacuna Tier 0 conhecida:** `ponto_banco_horas_movimentos` tem override Eloquent mas
  **não tem trigger MySQL** (o SPEC registra a lacuna). SQL cru ainda edita o ledger. Fechar isso é
  migration + decisão [W], não caso de teste de tela (SDD §9 D-6).

---

## UC-BHSHOW-01 · Movimento gravado não pode ser alterado nem apagado · `must` `[V0]` `[T0]`

- **Persona:** auditor / perito em reclamatória. O extrato só vale como prova se for **acumulativo**:
  a possibilidade de reescrever uma linha antiga destrói o valor probatório do documento inteiro.
- **Aceite:** Dado um movimento já registrado no extrato · Quando tento **alterá-lo** ou **removê-lo** ·
  Então a operação **falha** e o movimento permanece exatamente como estava.
- **Teste:** `Modules/Ponto/Tests/Feature/BancoHorasImportacaoContratoTest.php` — `UC-BHSHOW-01`.
- **Contrato:** `CU-PONTO-09` (SDD §6.3) · US-PONTO-008 (*"`BancoHorasMovimento::update()` e `delete()`
  idem — saldo deve ser auditável"*) · US-PONTO-004 (*"registra créditos/débitos — **append-only**"*) ·
  [proibicoes.md](../../../../memory/proibicoes.md) §append-only.
- **Regressão que defende:** a imutabilidade aqui é sustentada **só pelo override Eloquent** — diferente
  de `ponto_marcacoes`, que tem trigger MySQL também. Uma camada só, e nenhum teste apontando pra ela na
  lane de PR. Este UC transforma a única defesa em defesa **observada**.
- **Status: 🧪 sem veredito.**

---

## UC-BHSHOW-02 · Ajuste manual exige justificativa e vira movimento novo · `must` `[V0]`

- **Persona:** RH corrigindo um saldo após acordo com o colaborador. A correção é legítima — o que não
  pode é ser anônima ou silenciosa.
- **Aceite:** Dado um colaborador com saldo · Quando registro um ajuste manual **sem observação** · Então
  o ajuste é **recusado**. E quando registro **com** observação · Então um **novo** movimento aparece no
  extrato, sem que nenhum movimento anterior mude.
- **Teste:** `BancoHorasImportacaoContratoTest.php` — `UC-BHSHOW-02`.
- **Contrato:** `CU-PONTO-09` · `BancoHorasController@ajustarManual`
  (`'minutos' => 'required|integer'`, `'observacao' => 'required|string|max:500'`) ·
  US-PONTO-004 (tipo `AJUSTE` entre os movimentos canon) · CLT Art. 59 §5º.
- **Regressão que defende:** "ajustar saldo" é o caminho natural para alguém implementar como **UPDATE no
  saldo** — o que apagaria a rastreabilidade e contornaria o ledger. O UC fixa que ajuste é **acréscimo**,
  e que a justificativa é parte do contrato, não cortesia.
- **Nota `[V0]`:** este UC prova a **forma** (movimento novo + justificativa obrigatória), **não** o valor
  resultante. Assert sobre o saldo calculado exige o protocolo da REGRA MESTRE (2 caminhos + antes→depois)
  e por isso está no `[BACKLOG]` acima, não aqui.
- **Status: 🧪 sem veredito.**

---

## UC-BHSHOW-03 · Extrato de colaborador de outro empregador → 404 · `must` `[T0]`

- **Persona:** plataforma multi-tenant. Saldo de banco de horas é informação salarial.
- **Aceite:** Dado o id de um colaborador de **outro** business · Quando acesso
  `/ponto/banco-horas/{id}` · Então recebo **404** — nunca o extrato alheio.
- **Teste:** `BancoHorasImportacaoContratoTest.php` — `UC-BHSHOW-03`.
- **Contrato:** `CU-PONTO-12` (SDD §6.5) · US-PONTO-007 ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** `BancoHorasController@show` usa
  `BancoHorasSaldo::where('colaborador_config_id', $id)->firstOrFail()` — **sem** `business_id` explícito.
  O comentário no fonte diz *"já materializado pra findOrFail validar acesso tenant"*, mas quem valida o
  tenant é o **global scope**, não o `firstOrFail`. Comentário que descreve errado a própria defesa é
  como a defesa some no refactor seguinte (SDD §9 D-5).
- **Nota de teste:** biz=1 vs id fictício — **nunca biz=4** ([ADR 0101]).
- **Status: 🧪 sem veredito.**
