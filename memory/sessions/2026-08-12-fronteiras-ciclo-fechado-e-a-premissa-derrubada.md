---
date: "2026-08-12"
topic: "Fronteiras de módulo — o 3º eixo ganhou catraca, e 7 auditorias derrubaram a premissa da mesa que eu tinha acabado de mergear"
authors: [C, W]
module: governance
tags: [fronteira-modulo, catraca, acoplamento, auditoria-adversarial, lc-22]
prs: [5702, 5708, 5713, 5716]
pii: false
---

# Fronteiras de módulo — ciclo fechado no mecanismo, derrubado na norma

> **Pedido de [W]:** *"pode fechar o ciclo das fronteiras? com escopo e testar as máquinas
> para ver se tudo está funcionando? ciclo de ponta a ponta"* — e, no meio,
> *"pode mandar um especialista checar individualmente cada opção"* / *"vai gerar mais confiança"*.

## O que fechou

**O ciclo do mecanismo.** Os 3 eixos de fronteira agora têm catraca, todas com bite-test e
controle negativo:

| eixo | catraca | congelado |
|---|---|---|
| `app/` ↛ `Modules/` | `DependencyDirectionTest` (já existia) | 19 arquivos |
| módulo→módulo por `use` | `catalog-graph --catraca` (já existia) | 23 pares |
| **módulo→módulo por tabela** | **[#5702](https://github.com/wagnerra23/oimpresso.com/pull/5702)** — `DB::table` + (depois) `Schema::table` | 20 → 21 pares |

E as 3 baselines entraram no `GUARDED` do `baseline-tamper-guard` — antes, qualquer uma podia
ser afrouxada no mesmo PR que metia o acoplamento, que é o Gap 2 literal.

**Calibração ([#5716](https://github.com/wagnerra23/oimpresso.com/pull/5716)):** o eixo tabela
passou a enxergar `Schema::table` (ALTER em tabela alheia) — invisível aos dois eixos por
construção, e escondia `NfeBrasil>NFSe`, par que **nenhuma baseline conhecia**. Ganhou selo
`alter=N · mig=N runtime=M`. E o `allowlist_razoes` estreou: 0 → 1 entrada, em ~2 meses de
baseline.

## O que NÃO fechou — e por quê

**A norma por par.** A mesa que eu escrevi ([#5708](https://github.com/wagnerra23/oimpresso.com/pull/5708),
mergeada) recomendava declarar `depends_on` em 18 pares. Sete auditorias adversariais
independentes voltaram com **correção material em 7 de 7 grupos**, e o instrumento estava
errado. Errata em [#5713](https://github.com/wagnerra23/oimpresso.com/pull/5713) (mergeada).

Lápide completa no §5 (`memory/licoes-rejeitadas.md`, 2026-08-12) e contador em `LC-22`. O
resumo: declarar `depends_on` faz a catraca reportar o par como *"JÁ FORAM CURADOS — remova
do JSON"* **sem nada ter sido curado**. A prova custou uma linha, e eu nunca a tinha rodado.

## O que as 7 auditorias acharam (além de me derrubar)

Dois achados **vivos** caíram no colo dos auditores enquanto conferiam a mesa — nenhum era a
pergunta feita. Os dois viraram tarefa própria, iniciadas por [W] em sessões separadas:

1. **`SyncBankStatementsJob` (agendado, `dailyAt('07:00')`, `environments(['live'])`) itera
   conta bancária de TODOS os tenants sem filtro.** `BusinessScopeImpl::apply()` sai cedo sem
   sessão (*"Caller responsável por filtrar"*) e o caller não filtra. **Não é vazamento** —
   cada iteração usa a credencial da própria conta — mas viola a regra escrita (*"Job assíncrono
   SEMPRE passa `$businessId`"*) sem o `// SUPERADMIN:` que o canon exige, e decripta credencial
   de todos os clientes num processo só.
2. **O importer Firebird grava título `quitado` com `valor_aberto` cheio.** `mapFinStatus()`
   devolve `quitado` para `PAGA`/`RECEBIDA` e o INSERT grava `valor_aberto => $valor`
   incondicional — contra o invariante escrito no docblock do `Titulo` (*"quitado
   (valor_aberto = 0)"*). Alimenta Dashboard/ContaReceber/DRE/Fluxo. Cai na REGRA MESTRE de valor.

E três achados de máquina, dos quais 2 entraram no #5716 e 1 virou item aberto:

- **3º sub-eixo invisível** (`Schema::table`) — resolvido.
- **Falso-positivo da catraca que eu acabei de armar**: `Governance→Whatsapp` no eixo tabela é
  100% `failed_jobs`, a **um módulo** do limiar de infra — resolvido via `allowlist_razoes`.
- **Defeito ativo, aberto:** `Modules/Crm/Routes/web.php:159-161` afirma em presente um gate de
  degradação que o `ClienteVeiculosController` **não tem** (zero `class_exists`/`isModuleInstalled`).
  Só não quebra porque `vehicles` viaja no `mysql-schema.sql`. É LC-10 em produção.

## Uma calibração DESCARTADA por medição (registro pra não voltar)

Baixar o limiar de infra de 3→2 pra calar o FP do `failed_jobs`. Medido, levaria junto:
`fin_contas_bancarias` (PaymentGateway, RecurringBilling) — **tabela de dinheiro** —, mais
`subscriptions`, `mcp_tokens`, `mcp_actors`. Pra matar 1 falso-positivo eu cegaria o detector
no caminho do dinheiro. A razão ficou gravada no `_regra` da própria baseline.

É o exemplo limpo do critério **C2 — FP medido ANTES de instalar**: custou 30 segundos e
derrubou uma ideia que parecia obviamente certa.

## Os critérios que sobreviveram (para a próxima máquina)

| # | critério | por que |
|---|---|---|
| C1 | acha algo que nenhuma régua existente acha | senão duplica (§5 2026-07-09) |
| C2 | **FP medido no corpus real ANTES** | 4 lápides de guard sintático |
| C3 | bite-test com controle **positivo e negativo** | verde sem CN = não-execução (LC-13) |
| C4 | estende o dono, não abre paralelo | §5 2026-08-03 / LC-19 |
| C5 | se muda veredito, **cada par movido é nomeado** | §5 2026-08-10 |
| C6 | nasce advisory + forward-only | ADR 0275 / 0336 |

## Erros meus nesta sessão (6, todos registrados)

1. Bite-test com fixture **untracked** → falso verde (o `git grep` não vê). Peguei no CN.
2. Regex por **heredoc de bash** → barras halvadas → **0 imports com rc=0** contra 19 reais.
   Pegou o controle positivo que eu tinha embutido no script.
3. `git checkout --` em arquivo **untracked** → "reverti" lixo que ficou e foi commitado.
   Pegou o `baseline-tamper-guard` — o mecanismo que este trabalho ligou achou o defeito do
   PR que o ligou.
4. Claim **falsa** em doc canônico: *"NfeBrasil importa `ArquivosService` nos mesmos arquivos
   onde insere"* — zero ocorrências nos dois. Pegou o auditor.
5. **LC-22** (novo): propor mudança em artefato que a máquina lê sem rodar a máquina.
6. `git checkout <branch> -- memory/` pra **mover trabalho não-commitado** entre branches — o
   checkout sobrescreve o working tree e o que não estava commitado morre sem aviso. Perdi a
   primeira escrita da própria lápide da LC-22 e tive que reescrever.

Os 4 primeiros são LC-08. O 5º é classe nova. O 6º fecha uma família com o 1º e o 3º:
**três acidentes de estado git na mesma sessão** — a regra que os cobre é *commite antes de
trocar de branch, e pra mover entre branches use `cherry-pick`, nunca `checkout -- <path>`*.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**.
- `sessions-recent limit:3` → devolve logs de **maio/junho** indexados hoje; o índice do MCP
  parece atrasado em relação ao disco (mesmo sintoma registrado no handoff de 2026-08-12 16:17).
- Nada foi registrado em `mcp_tasks` — os 2 achados vivos foram encaminhados como tarefa de
  sessão, não como task MCP.
