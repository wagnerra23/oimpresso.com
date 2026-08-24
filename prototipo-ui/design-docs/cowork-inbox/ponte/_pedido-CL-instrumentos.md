---
para: "[CL] Claude Code"
de: "[CC]"
data: 2026-08-23
tipo: pedido colável — instrumentos do protocolo (3 rodar + 5 criar)
tese: "as falhas de hoje foram de MEDIÇÃO, não de construção. Este pedido calibra os instrumentos."
origem: aferição do ciclo em Ponto/Dashboard (mesma sessão) — achados C.08 (allowlist) e C.09 (assert tautológico)
---

# Pedido [CL] — calibrar os instrumentos do protocolo

> **Contexto medido hoje, por você:** readiness 29/54 · 54 charters `live` (48 no gate) · 46 contexts required com `enforce_admins` · deploy contínuo · `route-hits.json` expirado desde 25/07 · lane do Ponto roda **11 de 37** testes · `MultiTenantIsolationTest` tem assert que nunca reprova.
> **O que este pedido NÃO é:** feature. É calibração. Cada item devolve um número ou um script com controle negativo.
> **Padrão do repo a seguir:** script em `scripts/**` + `*.test.mjs` irmão, rodado por `.github/workflows/governance-script-tests.yml`. **Não invente estrutura nova** — estenda a que existe.

---

# PARTE 1 — RODAR (existe, ninguém está olhando)

## R.01 · Readiness completo, tela por tela
```bash
node scripts/qa/prototipo-readiness.mjs > /tmp/readiness-full.txt; echo "exit=$?"
```
**Entregar:** a **lista nominal das 25 não-prontas**, e para cada uma **qual das 4 exigências falta** (`prototipoReal · temTsx · temCasosComUC · temScorecard`). Formato: tabela `tela | falta`.
**Por que:** é a fila de trabalho do S10 e hoje ninguém a tem escrita.

## R.02 · Os 5 testes órfãos da allowlist
```bash
vendor/bin/pest Modules/Ponto/Tests/Feature/DashboardTest.php \
  Modules/Ponto/Tests/Feature/DashboardDeferredContractTest.php \
  Modules/Ponto/Tests/Feature/MultiTenantIsolationTest.php \
  Modules/Ponto/Tests/Feature/MultiTenantAppendOnlyTest.php \
  Modules/Ponto/Tests/Feature/CrossTenantMarcacaoTest.php
```
**Entregar:** passou/falhou/**skipou** por arquivo e por caso.
**Por que:** nunca correram por PR. Pode haver vermelho parado há meses — ou, pior, **skip em massa** (o skip foi o que escondeu a tautologia do C.09). **Reporte skip como categoria própria, nunca como verde.**

## R.03 · Sinal de produção, por via
```bash
node scripts/governance/charter-live-signal.mjs   # full-tree
```
**Entregar:** das 48 com sinal, **quantas por `prod-flags.json`, quantas por `route-hits.json`, quantas por `smoke:` datado**.
**Por que:** se muitas dependem do ledger, o número **cai** quando ele expira — e ele já expirou (25/07). Precisamos saber se 48 é um número real ou um número herdado.

---

# PARTE 2 — CRIAR (5 instrumentos)

> Cada um: script + `.test.mjs` irmão + **controle negativo** (um caso que o faz reprovar) + cadência declarada. **Sem controle negativo, não entra** — é a Lei C do `07-TESTES-DO-PROTOCOLO.md`.

## C.01 · Sentinela de allowlist — `scripts/qa/uc-lane-coverage.mjs`
**A mais importante. Mata quatro classes de verde falso numa verificação.**

Para cada `casos.md` do repo, para cada UC com `Teste:` preenchido:
1. o arquivo de teste **existe**?
2. ele **roda** na lane que cobre esse módulo (ler a allowlist do workflow, não presumir)?
3. o `Status` declarado no `casos.md` **bate** com o último veredito conhecido?
4. o teste **skipa** sempre? (skip ≠ verde)

**Saída:** tabela `tela · UC · teste · na-lane? · status-declarado · veredito-real` + exit≠0 se houver divergência.
**Controle negativo:** fixture com UC apontando teste fora da allowlist → **tem que reprovar**.
**Cadência:** por PR, **required** (começa advisory por 1 semana).
**Achado que motiva:** os 5 testes do Dashboard estão fora da lane; o `casos.md` os citaria como prova.

## C.02 · Controle negativo das catracas — `scripts/qa/catraca-selftest.mjs`
Prova que as catracas **sabem reprovar**:
- copia a tela para um tmp, **altera 1 copy literal** do contrato → `contrato-de-tela.mjs` **tem que dar exit≠0**
- remove 1 âncora `data-contract` → **tem que reprovar**
- embaralha a `ordem` → **tem que reprovar**

**Saída:** 3 asserções, todas exigindo vermelho.
**Cadência:** semanal (cron) + em qualquer PR que toque `scripts/contrato-de-tela.mjs`.
**Por que:** hoje "exit 0" é citado como garantia sem que ninguém tenha visto a catraca reprovar.

## C.03 · Anti-tautologia Tier 0 — `scripts/qa/t0-mutation-check.mjs`
Para cada teste marcado `[T0]`:
- **mutação**: remover/neutralizar o filtro `business_id` (ou o scope) no alvo, em worktree descartável
- o teste **tem que ficar vermelho**. Se continuar verde, ele é decoração → reprovar

**Começar por:** `MultiTenantIsolationTest` (C.09 — `assertNotEquals($a+$b, $b)` só falha se `$a==0`, e `$a==0` já deu skip).
**Cadência:** por PR que toque teste `[T0]`, + cron semanal no conjunto.
**Cuidado:** mutação roda em worktree isolada; **nunca** no working tree do PR.

## C.04 · Lint de id de UC — `scripts/qa/uc-id-lint.mjs`
Roda `scripts/lib/uc-regex.mjs` (fonte única) sobre todo `casos.md`; id que não casa → exit≠0 **com a mensagem dizendo o limite** (`[A-Z][A-Z0-9]{0,5}` — máx. 6 chars de prefixo) e sugerindo o corte.
**Controle negativo:** fixture com `UC-PAINEL-01` → tem que reprovar (é o meu erro de hoje, literal).
**Cadência:** por PR, required. Barato e 100% determinístico.
**Por que:** id inválido não falha na escrita — falha lá adiante no readiness, e o autor conclui "faltou critério". Custou uma rodada hoje.

## C.05 · Alarme de ledger e de fóssil — `scripts/governance/staleness-alarm.mjs`
Duas verificações:
- **`route-hits.json`**: se `max(ultima_data)` estiver fora da janela de 30d → **falha ativa** (hoje envelhece calado; venceu em 25/07)
- **docs que afirmam estado** (`RUNBOOK-*.md`, `memory/**` com dados medidos): exigir `data:` no frontmatter e sinalizar acima de N dias

**Saída:** issue automática no cron diário, não log silencioso.
**Semente conhecida:** `RUNBOOK-dashboard.md §4` ("12 falhas") é fóssil de 21/08 — `divergencias_mes` existe e a nota está acima dos KPIs.
**Cadência:** cron diário.
**Por que:** o que envelhece em silêncio produz diagnóstico errado. Produziu o meu, hoje, por inteiro.

---

# Wiring

| Instrumento | Onde entra | Cadência | Gate |
|---|---|---|---|
| C.01 uc-lane-coverage | `governance-script-tests.yml` + lane por PR | por PR | advisory 1 semana → **required** |
| C.02 catraca-selftest | cron semanal + PR que toca a catraca | semanal | advisory |
| C.03 t0-mutation | PR que toca `[T0]` + cron | por PR | advisory 2 semanas → required |
| C.04 uc-id-lint | `governance-script-tests.yml` | por PR | **required desde já** (determinístico) |
| C.05 staleness-alarm | cron diário | diário | abre issue |

**Promoção a required:** só depois de o instrumento ter **reprovado ao menos uma vez de verdade** (não em fixture). Catraca que nunca reprovou não vira gate — é a Lei C.

---

# Regras deste PR

- Um PR por instrumento, ou um PR para a Parte 1 (só medição, sem código) e um por instrumento. **Não empacote os cinco juntos.**
- **Não conserte** o `MultiTenantIsolationTest` neste PR: primeiro o C.03 tem que **provar** que ele é tautológico. Conserto sem prova vira "melhoria" e perde o registro.
- **Não mexa** na allowlist do `ponto-pest.yml` — o C.01 mede; **por que a allowlist existe** é decisão [W] (custo de CI? teste instável escondido?).
- **Não crie** `prototipo-ui/PRODUCAO.md` — `charter-live-signal.mjs` já é o dono e é required.
- Lição instrutiva → **proposta** em `memory/LICOES_CC.md`, nunca commit direto.

# Entrega esperada

1. Parte 1: três saídas (a lista das 25 · o veredito dos 5 órfãos com skip separado · as 48 por via)
2. Parte 2: cinco scripts com `.test.mjs` e controle negativo
3. Para cada instrumento: **a primeira vez que ele reprovou algo real** — é o que autoriza promover a required
4. O que você achou que eu não previ

**Falhar e reportar é entrega. Verde sem controle negativo não é.**
