---
id: resources-js-pages-ponto-espelho-show-casos
casos: Espelho de ponto mensal · /ponto/espelho/{colaborador}
irmaos: Show.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §5.3 F2 + §6.1 (contrato)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o espelho é o documento que sustenta fechamento de folha e defesa em fiscalização — o que ele deixa de mostrar vira verba trabalhista.
owner: wagner
last_run: "2026-08-21"
last_run_ci: "0 UC executado por mim. O bump de last_run é REVALIDAÇÃO DE LEITURA do F3 do contrato de tela (2026-08-21), não veredito: reli os 5 UC contra o diff e nenhum aceite mudou de sentido — 02/03/04 têm o código intocado, 05 segue com `totais`/`linhas` diferidos, e 01 ficou mais forte (o realce da linha continua E ganhou coluna `Estado` explícita). O veredito segue com a lane PHP / Pest (Ponto · MySQL), que roda no CT100/CI (ADR 0062)."
---

# Casos de Uso & Aceite — Espelho de ponto mensal

> **Âncora:** `CU-PONTO-01`, `CU-PONTO-02`, `CU-PONTO-03`, `CU-PONTO-13` do
> [SDD §6.1](../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md), cruzados com a **lei**
> (CLT Art. 66/71/74 §2º · Portaria MTP 671/2021) e com a **Blade legada** `espelho/show.blade.php`.
> Os UC derivam do **contrato**, nunca do `Show.tsx` — teste derivado do código é tautológico
> ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05).
>
> **Fonte 4 (Delphi) ausente neste módulo** — declarado no SDD §0.1, não inventado. A paridade aqui é
> **lei + Blade**, não manual legado.
>
> ⚖️ **Força do veredito:** a lane `PHP / Pest (Ponto · MySQL)` é **advisory** — reprova visível,
> **não bloqueia merge** (medido em `governance/required-checks-baseline.json`: só Financeiro, NfeBrasil
> e Unit são lanes Pest required).
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-ESPSHOW-01 | Dia com divergência de apuração aparece sinalizado | must `[V0]` | `CU-PONTO-02` + CLT Art. 66/71 | `EspelhoContratoTest` | 🧪 **vermelho ESPERADO** (predição) |
| UC-ESPSHOW-02 | Espelho cobre todos os dias do mês, não só os com marcação | must | `CU-PONTO-01` + Blade | `EspelhoContratoTest` | 🧪 sem veredito |
| UC-ESPSHOW-03 | Marcação anulada não conta como jornada | must | `CU-PONTO-13` + Portaria 671/2021 | `EspelhoContratoTest` | 🧪 sem veredito |
| UC-ESPSHOW-04 | Espelho de colaborador de outro empregador → 404 | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `EspelhoContratoTest` | 🧪 sem veredito |
| UC-ESPSHOW-05 | Totais e linhas chegam sob demanda, sem quebrar o contrato | should | `CU-PONTO-01` + charter §Automation hooks | `EspelhoContratoTest` | 🧪 sem veredito |

**[BACKLOG]** (contrato em 1 fonte só — vira UC quando ganhar 2ª âncora e teste):

- `[BACKLOG]` O PDF impresso mostra os mesmos números da tela (`CU-PONTO-03`; hoje só o charter afirma,
  §Pendências: *"Confirmar comportamento do PDF impresso vs tela (paridade de números)"*).
- `[BACKLOG]` Saída antecipada aparece nos totalizadores — a Blade somava, o React não (SDD §9 D-2).
- `[BACKLOG]` Minutos de violação de interjornada (Art. 66) e intrajornada (Art. 71) têm superfície
  própria no espelho (SDD §9 D-3) — hoje são calculados, gravados e **nunca exibidos**.

---

## UC-ESPSHOW-01 · Dia com divergência de apuração aparece sinalizado · `must` `[V0]`

- **Persona:** RH/DP (P2) fechando a folha do mês. Se um dia violou a interjornada de 11h (Art. 66) ou
  não concedeu a intrajornada (Art. 71 §4º), o RH **precisa ver** antes de fechar — depois vira passivo.
- **Aceite:** Dado um dia do mês cuja apuração foi gravada em estado de divergência (o `ApuracaoService`
  registrou pelo menos uma entrada em `divergencias[]`) · Quando abro o espelho daquele colaborador
  naquele mês · Então o espelho **informa que existe pelo menos 1 dia divergente** e **marca aquele dia**
  na tabela dia-a-dia.
- **Teste:** `Modules/Ponto/Tests/Feature/EspelhoContratoTest.php` — `UC-ESPSHOW-01`.
- **Contrato:** `CU-PONTO-02` (SDD §6.1) · US-PONTO-005 (aceitação cita Art. 66 e Art. 71 §4º) ·
  `ApuracaoService::addDivergencia()` → `estado = ESTADO_DIVERGENCIA` · Blade legada
  `espelho/show.blade.php` contava por `$ap->estado === 'DIVERGENCIA'` e pintava a linha `bg-warning`.
- **Regressão que defende:** **a regressão JÁ ACONTECEU** e este UC é o que a torna visível. Varredura
  contada (repo inteiro, sem corte): `tem_divergencia` aparece **2 vezes, ambas no `EspelhoController`**;
  **não existe** como coluna (a migration tem `estado` + `divergencias` JSON), **não existe** como
  accessor nem em `$appends`. Logo resolve `null` → contador **0 sempre**, realce **false sempre**.
  A Blade mostrava; o React perdeu em silêncio.
- **Por que o assert NÃO cita a chave do payload:** há **duas correções legítimas** — (a) criar o
  accessor `tem_divergencia` derivado de `estado`, ou (b) o controller passar a ler `estado`. Um assert
  em `toHaveKey('tem_divergencia')` reprovaria a correção (b) arbitrariamente. O contrato é *"o dia
  divergente aparece sinalizado"*, não *"a chave se chama X"*.
- **Nota do F3 de 2026-08-21 (contrato de tela):** a tabela dia-a-dia passou a conviver com uma visão
  em grade, atrás do seletor `espelho-modo-visao`. O aceite **não muda**: `tabela` é o default e é o
  documento, então "marca aquele dia na tabela dia-a-dia" continua verificável no primeiro render. O
  realce da linha (`bg-warning`, paridade com a Blade) foi **mantido** e ganhou ao lado uma coluna
  `Estado` explícita, que mostra `divergencia` em letra em vez de só cor — cor sozinha não é acessível.
- **Status: 🧪 vermelho ESPERADO** — **predição**, não veredito. Eu não rodei teste (CT100/CI, [ADR 0062]).
  O status real vem da lane.

---

## UC-ESPSHOW-02 · Espelho cobre todos os dias do mês, não só os com marcação · `must`

- **Persona:** RH conferindo ausências. Um dia **sem** marcação é informação — é falta, folga ou feriado.
  Se a tabela pula o dia vazio, a ausência fica invisível.
- **Aceite:** Dado um mês de referência · Quando abro o espelho · Então a tabela dia-a-dia traz **uma
  linha para cada dia do mês** (28/29/30/31 conforme o mês), inclusive dias sem marcação e sem apuração.
- **Teste:** `EspelhoContratoTest.php` — `UC-ESPSHOW-02`.
- **Contrato:** `CU-PONTO-01` · charter §Goals (*"Tabela dia-a-dia (todos os dias do mês)"*) ·
  paridade Blade (a legada iterava o mês inteiro).
- **Regressão que defende:** trocar o loop de calendário por um `foreach` sobre as apurações existentes
  faria o mês "encolher" silenciosamente — e dias de falta sumiriam do documento legal.
- **Status: 🧪 sem veredito.**

---

## UC-ESPSHOW-03 · Marcação anulada não conta como jornada · `must`

- **Persona:** auditor MTE (P4) / RH. A correção legal de uma marcação errada **não apaga** o registro —
  cria uma marcação de anulação. Mas a anulada não pode continuar contando como se fosse jornada.
- **Aceite:** Dado um colaborador com marcação de origem de **anulação** no mês · Quando abro o espelho ·
  Então essa marcação **não** aparece entre as marcações do dia.
- **Teste:** `EspelhoContratoTest.php` — `UC-ESPSHOW-03`.
- **Contrato:** `CU-PONTO-13` (SDD §6.5) · US-PONTO-008 (*"para corrigir: criar marcação com
  `origem=ANULACAO`"*) · Portaria MTP 671/2021 (imutabilidade) ·
  [proibicoes.md](../../../../memory/proibicoes.md) (*"append-only por força de lei"*).
- **Regressão que defende:** remover o filtro de anulação faz o espelho somar registro corrigido **e**
  correção — inflando a jornada. É o oposto do que a lei quer.
- **Status: 🧪 sem veredito.**

---

## UC-ESPSHOW-04 · Espelho de colaborador de outro empregador → 404 · `must` `[T0]`

- **Persona:** plataforma multi-tenant. Jornada é dado sensível (LGPD Art. 7º II + sigilo trabalhista).
- **Aceite:** Dado um id de colaborador que **não** pertence ao meu business · Quando acesso
  `/ponto/espelho/{id}` · Então recebo **404** — nunca 200 com dado de outro empregador, nunca 500.
- **Teste:** `EspelhoContratoTest.php` — `UC-ESPSHOW-04`.
- **Contrato:** `CU-PONTO-12` · US-PONTO-007 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) ·
  charter §Non-Goals (*"Não expõe colaborador de outro tenant"*).
- **Regressão que defende:** `EspelhoController@show` escapa por `Colaborador::where('business_id')->findOrFail()`.
  Se alguém "simplificar" para `Colaborador::findOrFail()` confiando só no global scope, a defesa vira
  única — e some junto com o trait.
- **Nota de teste:** biz=1 (WR2 interno) contra id inexistente/fictício — **nunca biz=4**
  ([ADR 0101](../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)).
- **Status: 🧪 sem veredito.**

---

## UC-ESPSHOW-05 · Totais e linhas chegam sob demanda, sem quebrar o contrato · `should`

- **Persona:** RH em conexão lenta. O cabeçalho do colaborador aparece na hora; os números pesados
  (9 agregações + até 31 linhas) chegam logo depois — mas **chegam**.
- **Aceite:** Dado o espelho de um colaborador · Quando faço a primeira requisição · Então o cabeçalho do
  colaborador já vem resolvido; e quando peço explicitamente os dados diferidos · Então os totalizadores
  e as linhas do mês chegam completos.
- **Teste:** `EspelhoContratoTest.php` — `UC-ESPSHOW-05`.
- **Contrato:** `CU-PONTO-01` · charter §Automation hooks (*"`Inertia::defer` nas props `totais` e
  `linhas`"*) · [RUNBOOK-inertia-defer-pattern](../../../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md).
- **Regressão que defende:** `defer` mal aplicado degrada de duas formas opostas — ou tudo volta eager
  (perde o ganho de p95), ou a prop diferida nunca resolve (tela fica em skeleton eterno). O UC prova que
  o dado **chega** quando pedido.
- **Status: 🧪 sem veredito.**

## Pendências sem id (prosa — viram UC quando ganharem teste que as cite)

Entram como `[BACKLOG]` de propósito: são comportamento que o F3 de 2026-08-21 introduziu e que
**ninguém testou ainda**. Criar `## UC-XX` sem teste só avermelharia o G-2 e fabricaria cobertura.

- **[BACKLOG]** A folha de impressão (`espelho-folha-impressao`) só existe em `@media print`. Nenhum
  teste alcança papel, e **verde de gate não prova impressão**: o que precisa ser verificado por
  humano é que a folha sai com cabeçalho, apuração, totais, as duas assinaturas e a citação da
  Portaria MTP 671/2021 Art. 85 — e que ela cabe na página.
- **[BACKLOG]** O seletor `espelho-modo-visao` não persiste escolha entre visitas (é `useState`, não
  `localStorage`). Deliberado por ora: o documento deve abrir SEMPRE na tabela, e persistir "grade"
  faria o RH abrir o espelho numa visão que não é o documento.
- **[BACKLOG]** `CPF` e `PIS` passaram a aparecer no cabeçalho legal. Falta decidir com [W] se a
  tela deve mascará-los para papéis não-RH — hoje quem acessa a rota vê inteiro.

