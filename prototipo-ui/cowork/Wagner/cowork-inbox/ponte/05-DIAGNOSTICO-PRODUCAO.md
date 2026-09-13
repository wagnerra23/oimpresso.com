---
sessao: "05"
titulo: Por que nenhuma tela chegou em produção — diagnóstico e lista de pesquisa profunda
autor: "[CC]"
criado: 2026-08-23
limite_honesto: "NÃO revisei PRs. Não tenho ferramenta que liste, leia ou compare Pull Requests — só leio árvore, arquivos e busca no código. Tudo abaixo vem de LEITURA DO MAIN nesta sessão. Onde é hipótese, está escrito hipótese."
---

> ⚠️ **SUPERADO por `06-DIAGNOSTICO-REVISADO.md`.** A medição do [CL] falsificou H1, H2, H4 e P.19/P.20:
> há **54 charters `live`**, **46 contexts required** e **deploy contínuo**. Só H3 sobreviveu.
> Mantido como registro de um diagnóstico feito sem número — não use as conclusões.

# Por que nenhuma tela chegou em produção

> ## ⛔ DOCUMENTO REFUTADO POR MEDIÇÃO — 2026-08-23
> A premissa deste arquivo ("nenhuma tela chegou em produção") **é falsa**. Sessão [CL] rodou os blocos A–E e mediu: **54 charters `live`, 48 com sinal de prod, 0 sem · 46 checks required com `enforce_admins` · deploy contínuo (success 15:36) · 5.811 PRs merged**.
> **H1 e H4 estão refutadas.** A hipótese que sobrevive é **H3** (o trio não fecha), que este documento já rankeava em 3º.
> Leia o veredito em `06-CORRECAO-MEDIDA.md`. O texto abaixo fica como registro do erro, não como diagnóstico.

## 0. O que eu NÃO fiz (para você não confiar demais nisto)

Você pediu "revise todos os PRs". **Não consigo.** Minhas ferramentas de GitHub leem árvore, arquivo e código — não listam PRs, não leem review, não veem check status, não veem quem aprovou o quê. Qualquer coisa que eu dissesse sobre "os PRs" seria invenção.

O que eu consigo — e fiz — é ler o `main` e achar as **travas estruturais**. Elas são suficientes para uma hipótese forte.

---

## 1. O achado central: o pipeline não tem trava técnica. Tem trava humana.

Isso é contra-intuitivo, então vem com prova. Cada catraca que li **se declara não-bloqueante**:

| Catraca | O que o próprio arquivo diz | Bloqueia merge? |
|---|---|---|
| `contrato-de-tela.yml` | "Roda no CI **advisory**; promove a required quando estável" (`caixa-unificada`) | **não** |
| Lane Pest (Ponto) | "**advisory**: reprova visível, **não bloqueia merge**" (`Espelho/Index.casos.md`) | **não** |
| Intenção de fluxo | `modo: "enforcing"` mas "hoje always-run porém **AINDA NÃO-required**, flip [W] pendente" (ADR 0261) | **não** |
| `prototipo-readiness.mjs` | mede prontidão | **não** |
| `cowork-ssot-guard.mjs` | dá erro no export do Cowork | não é gate de tela |

**Se nada trava tecnicamente, o merge não é o problema.** Então onde para?

### Os três gates humanos, todos terminando na mesma pessoa

| Gate | Onde está escrito | Quem fecha |
|---|---|---|
| Charter `draft` → `live` | "**Wagner aprova Non-Goals + Anti-hooks ANTES** de virar `status: live`" — literal no charter do Ponto/Dashboard | **[W]** |
| Screenshot 1280/1440 | "Smoke visual 1280/1440 (screenshot)" nas Pendências do charter | **[W2]** |
| Flip para required | "o dono é `governance/required-checks-baseline.json`" | **[W]** |

E na contagem da própria ponte: **50 dos 124 processos são [W]** — 40%.

> **Hipótese central (H1):** as telas não chegam em produção porque **todo caminho termina em uma aprovação humana única e nenhuma delas tem prazo, fila ou default**. Não é um gargalo de engenharia; é um gargalo de serialização em uma pessoa. Sintoma que confirma: charters criados em **2026-07-11** ainda `status: draft` em **2026-08-23** — seis semanas, três pendências não marcadas.

---

## 2. As outras cinco hipóteses (ranqueadas, com o que provaria cada uma)

| # | Hipótese | Evidência que já tenho | O que provaria |
|---|---|---|---|
| **H1** | Aprovação humana sem fila nem default | charter `draft` de 11/07 · 50/124 processos [W] | contar charters `status: draft` vs `live` no `main` inteiro |
| **H2** | "Pronto" nunca foi definido de forma binária — 9 portões, nenhum com dono de fechamento | portões espalhados entre charter, contrato, readiness, workflow | nenhum arquivo único diz "esta tela está em produção" |
| **H3** | O trio nunca fecha: 103 telas sem `casos.md` | medido: 53% de cobertura | readiness reprova por trio, não por qualidade |
| **H4** | Advisory eterno: catraca que não trava também não *libera* — ninguém sabe quando pode ir | 3 lanes advisory, flip pendente desde ADR 0261 | ver há quantos dias o baseline não muda |
| **H5** | Rota/alvo podre: contrato aponta pra arquivo que não existe | provado 1×: `ponto-painel` apontava `Ponto/Index.tsx` inexistente, corrigido só na descida | validar os 11 `alvo` contra o `main` |
| **H6** | O protótipo não desce: trabalho fica no Cowork | `superadmin-dashboard`: F1 "**era INVISÍVEL ao repositório**" desde 18/08, detector só olhava .jsx/.html/.css/.js (#6019) | achar quantos F1 ainda são live-only |

---

## 3. Lista de pesquisa profunda — o que rodar no `main` para fechar o diagnóstico

> Cada item é **verificável** e devolve número, não opinião. Ordenados por poder de diagnóstico.

### Bloco A — provar ou matar H1 (aprovação humana)
| # | Pesquisa | Como | O que responde |
|---|---|---|---|
| P.01 | Contar `status: draft` vs `status: live` nos 216 charters | busca por `^status:` em `**/*.charter.md` | se draft for maioria, H1 confirmada |
| P.02 | Data de `last_validated` mais antiga ainda em draft | frontmatter | mede a idade da fila |
| P.03 | Quantos charters têm "Pendências antes de `status: live`" com caixa não marcada | busca `- [ ]` | o tamanho real da fila do [W] |
| P.04 | Existe ALGUM charter `status: live`? | busca | **se zero, o gate nunca foi atravessado uma vez** — e aí H1 é a causa, não hipótese |

### Bloco B — provar H4 (advisory eterno)
| # | Pesquisa | Como |
|---|---|---|
| P.05 | Conteúdo de `governance/required-checks-baseline.json` — quais checks são required hoje | leitura direta |
| P.06 | Quais workflows em `.github/workflows/` são `required` vs advisory | leitura |
| P.07 | O step "Intenção de fluxo" já foi flipado? (ADR 0261 diz que não) | `contrato-de-tela.yml` |
| P.08 | Existe branch protection documentada no repo? | busca por `required_status_checks` |

### Bloco C — provar H2 (o "pronto" não existe)
| # | Pesquisa | Como |
|---|---|---|
| P.09 | `scripts/qa/prototipo-readiness.mjs` — ler o código e extrair o critério EXATO de ✅ | leitura |
| P.10 | Rodar (ou ler o último output de) readiness: quantas telas ✅ hoje? | script |
| P.11 | Existe algum registro de "tela em produção"? (`memory/`, ADR, changelog) | busca |
| P.12 | `ADR 0107` (gate de charter) — o texto exato do que ele exige | leitura |

### Bloco D — provar H5/H6 (ponteiro podre e protótipo preso)
| # | Pesquisa | Como |
|---|---|---|
| P.13 | Validar os 11 `alvo` de contrato contra a árvore | existe / não existe |
| P.14 | `grep -c data-contract` em cada um dos 11 alvos | quantas telas têm âncora de verdade |
| P.15 | `prototipo-ui/cowork/` — quantos `.jsx` e quais telas cobrem | o que desceu |
| P.16 | Comparar com as telas que têm `related_prototype` no charter | achar os live-only restantes |

### Bloco E — a verdade sobre os PRs (só VOCÊ ou o [CL] consegue)
| # | Pesquisa | Quem |
|---|---|---|
| P.17 | Quantos PRs abertos hoje? Há quanto tempo o mais antigo? | [W]/[CL] |
| P.18 | Dos PRs fechados, quantos foram **merged** vs **closed sem merge**? | [W]/[CL] |
| P.19 | Nos merged: algum chegou a deploy? Existe ambiente de produção com o `main` atual? | [W]/[CL] |
| P.20 | O cliente piloto (ROTA LIVRE / biz=164) roda o `main` ou uma versão antiga? | [W] |

> **P.19 e P.20 são as perguntas mais importantes desta lista.** É possível que o merge aconteça e o **deploy** não — nesse caso o problema nunca esteve no processo de tela, e toda a ponte que desenhei ataca o lugar errado. Preciso saber isso antes de mais qualquer coisa.

---

## 4. O que significa "lista completa testada"

Uma tela só pode ser declarada **em produção** quando existir, em um único lugar, esta linha preenchida:

| Campo | Prova |
|---|---|
| charter `status: live` | frontmatter |
| casos com UC ✅ | `last_run_ci` com veredito real |
| contrato verde | exit code 0 |
| âncoras | `grep -c` = nº de seções |
| readiness | ✅ |
| PR | merged, com sha |
| deploy | data + ambiente |
| uso real | primeira ação do usuário registrada |

**Hoje não existe esse lugar.** Nenhum arquivo do `main` reúne essas 8 provas por tela. Enquanto não existir, "em produção" é opinião — e opinião não se testa.

**Proposta:** `prototipo-ui/PRODUCAO.md` — uma linha por tela, oito colunas, atualizada por máquina onde der. É a peça que falta, e é pequena.

---

## 5. Ordem que eu recomendo

1. **P.04** (existe algum charter `live`?) — 1 busca. Se a resposta for zero, o diagnóstico acabou aqui e H1 é a causa.
2. **P.19/P.20** (existe deploy? o piloto roda o main?) — só você responde, e muda tudo.
3. **P.05** (o que é required hoje) — define se alguma catraca já tranca algo.
4. **P.09/P.10** (critério de ✅ e quantas passam) — define se "pronto" é alcançável.
5. O resto.

**Custo:** blocos A–D são ~16 buscas no `main`, cabem em **uma sessão fresca** minha. Bloco E é seu, e é o que decide se a ponte inteira está apontada pro lugar certo.
