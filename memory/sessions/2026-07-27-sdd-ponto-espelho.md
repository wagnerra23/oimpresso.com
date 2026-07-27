---
date: '2026-07-27'
topic: "SDD do módulo Ponto derivado do fonte (chip S3, Onda 1 do passo 5) — 1ª corrida do ramo sem SDD prévio"
authors: [C]
id: sessions-2026-07-27-sdd-ponto-espelho
tipo: session
modulo: Ponto
agente: sdd-from-source
outcomes:
  - "SDD-espelho-e-jornada-v1.0.md criado do zero (§0–§11, 8 fluxos, 14 CU)"
  - "6 casos.md com 22 UC ancorados + 3 Pest ContratoTest + allowlist da lane 1→4"
  - "2 regressões vivas achadas com varredura contada (campo fantasma em Espelho e Importacoes)"
us: [US-PONTO-002, US-PONTO-003, US-PONTO-004, US-PONTO-005, US-PONTO-007, US-PONTO-008]
related_adrs:
  - 0351-sdd-from-source
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0093-multi-tenant-isolation-tier-0
  - 0062-separacao-runtime-hostinger-ct100
---

# Sessão — SDD do módulo Ponto (chip S3, Onda 1 do passo 5)

**1ª corrida do ramo sem precedente** — `SDD não existe → criar §0–§10`. Até hoje o agent
`sdd-from-source` só tinha rodado com o SDD já pronto (Produto, escrito à mão 10 dias antes da ADR 0351).

Plano: [`passo-5-sdd-por-modulo.md`](../requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md).
Entrega: [`SDD-espelho-e-jornada-v1.0.md`](../requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md).

---

## 1. Fontes resolvidas

| # | Fonte | Estado | Nota |
|---|---|---|---|
| 1 | Documentação canon | ✅ | `SPEC.md` (10 US) + 20 charters (todos `status: draft`) |
| 2 | React/Laravel | ✅ | 13 controllers · 20 telas · 10 entities · 8 services |
| 3 | Blade AdminLTE | ✅ | **26 views**, e uma delas está **VIVA** (ver A-3) |
| 4 | Delphi / Office Comercial | ❌ **ausente** | `ANTI-REGRESSAO-*.md` só existe no Produto — **gap declarado, não inventado** |

**Sobre a armadilha da Blade homônima:** aqui ela se apresentou ao contrário. As 26 Blades são
homônimas 1:1 das telas React, mas 25 são **fósseis** — a varredura contada dos renders
(21 = 20 Inertia + 1 Blade) mostrou que só `intercorrencias/edit` continua servida por rota viva.
Resolvi por **rota**, não por nome de arquivo.

---

## 2. Achados (todos com varredura CONTADA + âncora de contrato)

### A-1 🔴 O espelho nunca sinaliza divergência de apuração — regressão Blade→React

`EspelhoController` lê `tem_divergencia`. Varredura contada no repo inteiro: **2 ocorrências, ambas nele**.
Não é coluna (a migration tem `estado` enum com `DIVERGENCIA` + `divergencias` JSON), não é accessor,
não está em `$fillable`. Resolve `null` → contador **0 sempre**, realce **false sempre**.
A Blade legada contava por `estado === 'DIVERGENCIA'` e pintava a linha.

**Efeito legal:** violação de **interjornada (Art. 66)** e **intrajornada (Art. 71 §4º)** — que o
`ApuracaoService` detecta, grava e promove a `ESTADO_DIVERGENCIA` — **nunca aparece pro RH**.

### A-2 🔴 A importação sempre exibe "0 marcações criadas" — mesma classe, outra tela

`ImportacaoController` lê `linhas_criadas`/`linhas_ignoradas`. Varredura contada: **9 ocorrências**
(3 no controller + 6 consumindo no front). A migration tem `linhas_sucesso`/`linhas_erro`.
O `?? 0` do controller **mascara** o campo ausente.

> **A-1 e A-2 são a MESMA falha de forma** — o controller lê atributo que o modelo não tem, e a
> linguagem esconde (`null → false`, `?? 0`). Apareceu em **2 de 8 famílias** de tela.
> **Declarei como padrão a varrer, não como "verificado"**: não auditei `Dashboard`, `Colaboradores`,
> `Escalas`, `Configuracoes`. Está no SDD §9 D-8 como pendência nominal.

### A-3 🟠 A edição de intercorrência ainda é Blade dentro do app React

`Route::resource` expõe `GET /ponto/intercorrencias/{id}/edit` → `view('pontowr2::intercorrencias.edit')`.
**Não existe `Intercorrencias/Edit.tsx`.** O operador sai do shell React e cai no AdminLTE.
Invisível para a porta viva (que conta `.tsx`).

### A-4 🟠 60 referências a uma US que não existe

Medido: **44** ocorrências de `US-PONT-` em `Pages/Ponto/**` (charters) + **16** em
`Modules/Ponto/Tests/**`. O SPEC usa `US-PONTO-` (**0** ocorrências de `US-PONT-`).
Ou seja: o `related_us:` dos 20 charters e o `@covers-us` dos testes legados apontam para **ids
inexistentes** — o trio está linkado a nada. Não corrigi: são 60 pontos em arquivos de terceiros
(charters são lei do [W]) e conserto em massa acorda gates diff-aware ([proibicoes §5] 2026-07-12).

### A-5 🟡 Isolamento por defesa única em vários handlers

`AprovacaoController@{aprovar,rejeitar}` · `IntercorrenciaController@{show,edit,update,submeter,cancelar}` ·
`ImportacaoController@{show,baixarOriginal}` · `BancoHorasController@show` usam `findOrFail`/`firstOrFail`
**sem** `where('business_id')`. Correto **hoje** (global scope `HasBusinessScope`), mas sem teste.
Os UC `[T0]` que escrevi passam a observar isso. `EscalaTurno` é a **única** das 10 entities sem o trait.

---

## 3. Artefatos (só nas áreas permitidas)

| Artefato | O quê |
|---|---|
| `memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md` | **novo** — §0–§11, 8 fluxos no §5.3, **14 CU** no §6, 8 dívidas no §9 |
| 6 × `resources/js/Pages/Ponto/**/*.casos.md` | **novos** — **22 UC ancorados** + 9 `[BACKLOG]` |
| 3 × `Modules/Ponto/Tests/Feature/*ContratoTest.php` | **novos** — 22 testes citando os UC (G-2) |
| `.github/workflows/ponto-pest.yml` | allowlist: 1 → 4 arquivos |

**Não toquei** (e não vou): baseline global, umbrella, `scripts/governance/**`, `proibicoes.md`,
`LICOES_CODE.md`, `08-handoff.md`. Nenhum commit/push/PR (R10).

**SPEC:** as âncoras `Implementado em:` ficam **propostas na devolutiva**, não aplicadas — tocar SPEC
legado acorda o `anchor-lint` diff-aware sobre dívida grandfathered.

---

## 4. Camada 3 — veredito por US

Rodado local (gates node). **Não rodei teste** — CT100/CI ([ADR 0062]); onde digo "vermelho" é **predição**.

| US | CU | UC | casos-gate (G-2) | Veredito |
|---|---|---|---|---|
| US-PONTO-002 | CU-PONTO-10/11 | 4 | 0 órfão | 🧪 **A-2: vermelho esperado** |
| US-PONTO-003 | CU-PONTO-05/06/07 | 7 | 0 órfão | 🧪 sem veredito |
| US-PONTO-004 | CU-PONTO-08 | 3 | 0 órfão | 🧪 sem veredito |
| US-PONTO-005 | CU-PONTO-01/02 | 5 | 0 órfão | 🧪 **A-1: vermelho esperado** |
| US-PONTO-007 | CU-PONTO-12 | 6 `[T0]` | 0 órfão | 🧪 sem veredito |
| US-PONTO-008 | CU-PONTO-09/13 | 2 | 0 órfão | 🧪 sem veredito |

**`casos-coverage-guard --check`**: as 6 violações novas que eu havia introduzido
(`meta:missing-last_run`) foram corrigidas → **zero violação nova minha**.
**`anchor-lint`**: `ANCHOR COVERAGE 100%` · 0 dead · 0 zombie · 0 fora-de-lane.

⚖️ **Força:** a lane `PHP / Pest (Ponto · MySQL)` **NÃO** está em
`governance/required-checks-baseline.json` → **advisory**: reprova visível, **não bloqueia merge**.
(As únicas lanes Pest required são Financeiro, NfeBrasil e Unit.)

### Porta viva — antes → depois

`node scripts/governance/requisitos-status.mjs Ponto`

| Elo | Antes | Depois |
|---|---:|---:|
| US no SPEC | 10 | 10 |
| **CU no SDD** | **0** | **14** |
| Telas com `casos.md` | 0 | **6** |
| **UC declarados** | **0** | **22** |
| UC com teste que os cita | 0 | **0 (leitura da porta — ver L-1)** |

---

## 5. Orçamento da corrida

| Métrica | Valor |
|---|---:|
| Tool calls | **45** |
| Arquivos lidos (integral ou seção) | ~35 |
| Varreduras contadas (sem `head_limit`) | **7** |
| Telas cobertas com contrato REAL | **6 de 20** |
| UC ancorados | **22** |
| `[BACKLOG]` (1 fonte só) | **9** |
| CU criados | 14 |
| Testes escritos | 22, em 3 arquivos |
| Achados | **5** (2 regressões vivas + 1 Blade viva + 1 ref morta ×60 + 1 defesa única) |
| Reuso vs re-varredura | **0% reuso** — ramo sem precedente: sem SDD, sem `casos.md`, sem `ANTI-REGRESSAO`. Cada fluxo foi re-varrido do zero |

**Telas DEIXADAS DE FORA (14) — truncagem declarada, não silenciosa:**

| Tela | Por que ficou fora |
|---|---|
| `Intercorrencias/Index` · `Importacoes/Index` · `BancoHoras/Index` | irmãs de listagem das cobertas; o contrato duro (workflow, idempotência, ledger) já está ancorado no `Show` de cada família |
| `Intercorrencias/Create` · `Importacoes/Create` | formulários — o contrato de escrita mora no `store()`, e cobri-lo exige fixture de upload/validação que não coube |
| `Dashboard/Index` · `Relatorios/Index` | **não varridas** — e `Relatorios` é onde `CU-PONTO-14` ficou sem UC (a porta viva acusa). Ambas são candidatas do padrão A-1/A-2 |
| `Colaboradores/{Index,Edit}` · `Escalas/{Index,Form}` · `Configuracoes/{Index,Reps}` | cadastro/config — menor carga legal; `Escalas` já tem o único teste da lane (`Wave27CrossTenantEscalaTest`) |
| `Welcome` | rota de piloto React (`/ponto/react`), não tela de negócio |

**Gargalo:** a **Camada 1**. ~60% do custo foi triangular as 3 fontes de um módulo sem nenhum
artefato SDD prévio. Os dois achados de campo fantasma só apareceram porque cruzei
**migration × entity × controller × Blade** — nenhum deles é visível lendo só o React.

**Kill-condition do plano:** o chip **não** estourou o piloto do Produto (que levou 4 runs). Uma corrida,
6 telas, 2 regressões achadas. Mas o custo/tela **sobe** sem SDD prévio — o plano espera que a 2ª tela
de um módulo custe bem menos que a 1ª, e aqui **todas** foram 1ª.

---

## 6. Lições de mecanismo (o que atrapalhou)

### L-1 · As duas réguas de "UC tem teste?" discordam — e a que reporta é a mais cega

Medido: `scripts/governance/requisitos-status.mjs::listarTestes()` varre **só `tests/` e `e2e/`**.
O gate **required** `scripts/casos-coverage-guard.mjs` varre `TEST_DIRS = ['Modules','tests','app','e2e']`.

Consequência: meus 22 UC **têm** teste que os cita (o gate required confirma: zero órfão), mas a porta
viva imprime **"UC com teste que os cita: 0"** e lista lacunas que não existem. Em módulo nWidart —
onde o teste **tem** que morar em `Modules/<Mod>/Tests/` (regra do próprio plano) — a porta viva é
**estruturalmente cega**. Quem ler o `_STATUS-GENERATED` vai concluir que o trio não fechou.

**Não consertei** (`scripts/governance/**` é área proibida do chip). **Reporto.**

### L-2 · Rodar o gate global numa worktree compartilhada mistura os deltas das 3 sessões

`git status` mostra **29 arquivos** de **três** módulos: Compras (S1), Fiscal (S2) e Ponto (S3) — todos
na branch `claude/sdds-pendentes-c3a697`. O plano prevê `claude/sdd-<modulo>` **por sessão**; não foi
o que aconteceu. (Colisão de **arquivos**: zero — o isolamento por diretório funcionou.)

Efeito concreto: entre duas rodadas minhas do `casos-coverage-guard --report`, os órfãos saltaram
**47 → 55** sem que eu tivesse tocado em UC nenhum — os 8 novos eram do **Fiscal**. Quase virou achado
meu. O que salvou foi usar `--check` (que atribui violação por arquivo) em vez do total agregado.

**Regra que tiro disso:** em worktree compartilhada, **número global de gate não é medida da minha
corrida** — só o `--check` por arquivo é. Vale registrar no plano do passo 5.

### L-3 · A allowlist da lane é catraca de VERDES, e o trio nasce VERMELHO

O YAML diz, textual: *"a lane roda só os arquivos comprovadamente verdes"*. Mas o agent manda escrever
**failing-first** e adicionar à allowlist (senão é "verde impossível"). As duas instruções colidem.

Resolvi adicionando os 3 arquivos **e** documentando no YAML que 2 UC nascem vermelhos por desenho.
Só é seguro porque a lane é **advisory**. Numa lane **required**, o mesmo procedimento travaria o merge
de todo mundo que tocasse o módulo — **o agent deveria dizer explicitamente o que fazer nesse caso**.

### L-4 · A instrução "documente as 20 telas" compete com "UC órfão trava merge"

O critério de parada do agent (≥2 fontes por UC, `[BACKLOG]` caso contrário) foi o que impediu inflar
20 `casos.md` de fachada. Ele funcionou — mas só porque o chip **também** dizia "cubra menos com
contrato real". Sem essa segunda frase, a pressão do "20 telas" no alvo puxaria para o stub.

---

## 7. O que precisa do [W]

1. **A-1 e A-2 são bugs vivos** — a correção (e qual das duas formas) é decisão de produto, não do agente.
2. **A-3**: portar `Intercorrencias/Edit` para React **ou** aposentar a rota.
3. **A-4**: `US-PONT-` × `US-PONTO-` — reconciliar 60 referências é trabalho de PR próprio.
4. **Charters `draft`**: os 20 pedem aprovação de Non-Goals/Anti-hooks; vários dizem *"(inferência
   pendente de Wagner)"*. **Nenhuma inferência virou lei neste SDD.**
5. **Aplicar as âncoras `Implementado em:` no SPEC?** — propostas na devolutiva, não aplicadas.
6. **L-1 e L-2** são defeitos de mecanismo em área proibida do chip — precisam de dono.
