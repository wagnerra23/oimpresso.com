---
para: "[CL] Claude Code"
de: "[CC]"
data: 2026-08-23
tipo: pedido colável — TESTE DO CICLO COMPLETO em uma tela
tese: "provar o ciclo ponta a ponta numa tela só, com número em cada portão. Não é feature; é aferição do processo."
cobaia: Ponto/Dashboard/Index
---

# Pedido [CL] — testar o CICLO COMPLETO numa tela

> **O que este pedido é:** levar **uma** tela dos 8 portões de artefato até o **sinal de produção**, registrando o número de cada passo. O produto disso não é a tela — é saber **onde o ciclo trava de verdade**, com evidência.
> **Por que Ponto/Dashboard:** contrato já descido com alvo corrigido, charter existe, `casos.md` escrito e esperando move, e **zero lane executada**. Menor distância entre "contrato ativo" e "veredito real" no repo.
> **Contexto medido (sessão de hoje):** readiness **29/54** · 54 charters `live`, 48 com sinal, 0 sem · **46 contexts required** com `enforce_admins` · deploy contínuo · `route-hits.json` **expirado desde 25/07**.

---

## ⚠️ VERIFICADO NO `main` — 2026-08-23 16:55Z (leitura minha, não relato)

**Os 3 caminhos de script estão certos** — confirmados pelos workflows que os invocam:
- `scripts/contrato-de-tela.mjs` → `.github/workflows/contrato-de-tela.yml` (`--preflight`, `--contract`, `--map --check`)
- `scripts/governance/charter-live-signal.mjs` → `.github/workflows/anchor-drift.yml` (`--check <changed>` no PR · full-tree no cron; **required desde 2026-06-30**, promoção conjunta com `anchor-lint`/`doneness-lint`)
- `scripts/qa/prototipo-readiness.mjs` → `.github/workflows/governance-script-tests.yml` (tem `.test.mjs` próprio)

**O critério de ✅ está nomeado no workflow:** *"contrato de prontidão · **casos/UC/scorecard/âncora**"* — quatro exigências, não duas. Ainda assim: **leia o código, não esta linha.**

## ⛔ NÃO RECRIE O QUE JÁ EXISTE (regra do `CLAUDE.md`)

`Modules/Ponto/Tests/Feature/` tem **37 testes**, e pelo menos quatro tocam o território dos meus UC:

| Já existe | Provável sobreposição |
|---|---|
| `DashboardDeferredContractTest.php` (3,1 KB) | **UC-PAINEL-05** (defer/polling) — quase certo |
| `DashboardTest.php` (1,8 KB) | UC-01 / UC-03 / UC-04 |
| `MultiTenantIsolationTest.php` (5,0 KB) | **UC-PAINEL-02** `[T0]` |
| `MultiTenantAppendOnlyTest.php` · `CrossTenantMarcacaoTest.php` | UC-02 e append-only |
| `PontoTestCase.php` (8,4 KB) | **base/factory — estender esta, não criar fixture nova** |

**Tarefa 1 vira, nesta ordem:** (a) ler os quatro + `PontoTestCase`; (b) mapear **qual UC já está coberto** e por qual asserção; (c) só então escrever o que falta — **estendendo** os arquivos existentes onde couber, e criando `PontoDashboardContratoTest.php` apenas para os UC órfãos. Se um UC já está provado por teste existente, o `casos.md` recebe **o nome desse teste**, não um teste novo.

> Eu escrevi "crie os 6 testes" sem ter olhado a pasta. Errado — e é exatamente a proibição "não reinventar o decidido". A entrega correta pode ser 2 testes novos e 4 referências.

---

## FASE 0 — baseline (medir ANTES de tocar em nada)

Sem baseline, nenhum número depois significa coisa alguma.

```bash
# B1 — readiness global e o status desta tela
node scripts/qa/prototipo-readiness.mjs | tee /tmp/readiness-antes.txt
grep -i 'Ponto/Dashboard' /tmp/readiness-antes.txt

# B2 — o critério de ✅, do código (não de descrição de terceiro)
sed -n '1,80p' scripts/qa/prototipo-readiness.mjs

# B3 — o oráculo de produção e seu critério
sed -n '1,60p' scripts/governance/charter-live-signal.mjs
node scripts/governance/charter-live-signal.mjs; echo "exit=$?"

# B4 — âncoras hoje
grep -c data-contract resources/js/Pages/Ponto/Dashboard/Index.tsx

# B5 — a catraca de copy hoje
node scripts/contrato-de-tela.mjs --contract prototipo-ui/contrato/ponto-painel.contract.json; echo "exit=$?"

# B6 — o trio existe?
ls -la resources/js/Pages/Ponto/Dashboard/
```

**Reporte B1–B6 antes de seguir.** Se B5 já der 0 com 0 âncoras (B4), isso é uma descoberta sobre a catraca, não sobre a tela — diga.

---

## FASE 1 — fechar os artefatos

```
T0  mover  cowork-inbox/ponto-dashboard/Index.casos.md
        →  resources/js/Pages/Ponto/Dashboard/Index.casos.md
```

**T1 — `Modules/Ponto/Tests/Feature/PontoDashboardContratoTest.php`**, um teste por UC, com o id no nome:

| UC | Provar | Como |
|---|---|---|
| UC-PAINEL-01 | 6 KPIs na ordem contratada, copy literal | ordem: "Colaboradores ativos" → "Presentes agora" → "Atrasos hoje" → "Faltas hoje" → "HE do mês" → "Aprovações pendentes" |
| UC-PAINEL-02 `[T0]` | zero dado de outro business em KPI, presença, feed, fila | seed 2 business · **biz=1 vs fictício, nunca biz=4** (ADR 0101) |
| UC-PAINEL-03 | fila vazia visível com "Nenhuma intercorrência aguardando decisão." | seção presente **e** frase presente |
| UC-PAINEL-04 | read-only: nenhuma escrita parte da tela | nenhuma rota de mutação alcançável do `DashboardController@index` |
| UC-PAINEL-05 | polling só props de leitura | `only: ['kpis','presenca_agora','atividade_recente','alertas','server_time']` |
| UC-PAINEL-06 | nota de divergência ACIMA dos KPIs, ausente quando não há | 2 cenários · ordem no DOM |

**T2 — query count** de um ciclo de reload com `Inertia::defer`. **Medir e reportar**, não otimizar. Se passar de um teto que você julgue razoável, diga o teto e por quê — otimizar é decisão [W].

**T3 — âncoras:** esperado **4** (`painel-nota-fechamento`, `painel-kpis`, `painel-fila-aprovacoes`, `painel-atividade`). Adicionar o que faltar. **Âncora é wiring seu; copy e ordem são lei [W] — não altere nenhuma das duas.**

**T4 — scorecard**, se o readiness o exigir (B2 diz). Se exigir: **cole o critério** que leu, e então preencha.

**T5 —** preencher no `casos.md`: coluna `Teste` = `PontoDashboardContratoTest`, `Status` = resultado real, `last_run` e `last_run_ci`. **Nenhum ✅ sem lane executada.**

---

## FASE 2 — reaferir (o mesmo comando da fase 0)

```bash
node scripts/qa/prototipo-readiness.mjs | tee /tmp/readiness-depois.txt
diff /tmp/readiness-antes.txt /tmp/readiness-depois.txt
node scripts/contrato-de-tela.mjs --contract prototipo-ui/contrato/ponto-painel.contract.json; echo "exit=$?"
grep -c data-contract resources/js/Pages/Ponto/Dashboard/Index.tsx
vendor/bin/pest --filter=PontoDashboardContratoTest
```

**A pergunta única desta fase:** o readiness **subiu de 29 para 30**?
- **Subiu** → o ciclo de artefato funciona, e as outras 24 são a mesma receita.
- **Não subiu** → **é a descoberta mais valiosa deste pedido.** Diga exatamente qual critério faltou. Não insista, não force o número.

---

## FASE 3 — os portões que NÃO são meus (medir e reportar, não executar)

| Portão | Comando / verificação | Dono |
|---|---|---|
| CI required | quais dos 46 contexts rodam neste PR, e o veredito de cada | CI |
| charter `draft`→`live` | **não mexer.** Reportar quais itens de "Pendências antes de `status: live`" ainda estão `- [ ]` | [W] |
| a11y | rodar se houver lane; senão dizer que não há | [CA] |
| screenshot 1280/1440 | **não fazer.** É [W2] | [W2] |
| sinal de produção | `charter-live-signal.mjs` **antes e depois** do deploy: esta tela ganha sinal? por qual das 3 vias (flag / hits / smoke)? | máquina |

> ⚠️ Se o sinal vier **só** por `prod-flags.json` porque `route-hits.json` está expirado (25/07), **diga isso explicitamente**. É a diferença entre "não tem uso" e "não tem medição de uso" — e foi exatamente o que me levou a um diagnóstico errado hoje.

---

## FASE 4 — o mapa do ciclo (a entrega de verdade)

Devolva **esta tabela preenchida**. É o produto do pedido:

| # | Portão | Antes | Depois | Quem travou | Tempo/atrito |
|---|---|---|---|---|---|
| 1 | charter existe | | | | |
| 2 | casos.md com UC | | | | |
| 3 | contrato no schema, alvo existe | | | | |
| 4 | âncoras `data-contract` | | | | |
| 5 | catraca de copy (exit code) | | | | |
| 6 | readiness | 29/54 | ?/54 | | |
| 7 | scorecard | | | | |
| 8 | lane Pest (por UC) | | | | |
| 9 | 46 checks required | | | | |
| 10 | charter `live` | draft | | [W] | |
| 11 | deploy | | | | |
| 12 | sinal de produção (via) | | | | |

E, em uma frase: **qual portão é o gargalo real desta tela** — e se ele é artefato, máquina ou pessoa.

---

## Regras que este PR não negocia

- **Não alterar copy nem ordem** do contrato. Divergência = veredito vermelho, que é o sinal honesto.
- **Não marcar UC ✅** sem lane executada.
- **Não mexer** em `status:` de charter, em `route-hits.json`, nem em `governance/required-checks-baseline.json`.
- **Não criar `prototipo-ui/PRODUCAO.md`** — o `charter-live-signal.mjs` já é o dono e é required. Segundo dono = drift.
- Prefixo: `resources/js/Pages/Ponto/**` + `Modules/Ponto/Tests/**`. Nada fora.
- Lição instrutiva vai em `memory/LICOES_CC.md` como **proposta no PR**, nunca commit direto.

## Recusa legítima (só nestes casos)
1. Fazer passar exigiria mudar copy/ordem → pare e reporte a divergência.
2. UC-PAINEL-02 revelar **vazamento real entre tenants** → pare tudo: é Tier 0, vira incidente, não PR.
3. Precisar escrever fora do prefixo → reporte o que falta e por quê.

**Falhar e reportar é entrega. Recusar sem número não é.**


---

## Nota de handoff (V.01 — validador da ponte)

O `casos.md` citado acima **não existe no repo**: ele vive no Cowork. Para colar, use
**`cowork-inbox/ponte/COLAR-NO-CODE-ponto.md`**, que é este mesmo pedido **com o conteúdo
integral do arquivo anexado** (ANEXO A, entre linhas de corte). Colar este arquivo sozinho
faz o [CL] procurar um caminho que ele não vê — e parar com razão.
