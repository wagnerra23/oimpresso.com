---
sessao: "07"
titulo: O que falta testar para o PROTOCOLO funcionar — os dois lados
autor: "[CC]"
criado: 2026-08-23
tese: "hoje nenhuma falha foi de construção. Todas foram de MEDIÇÃO. Logo, o que falta testar são os instrumentos, não as telas."
---

# Testes do protocolo

## A leitura do dia

| O que falhou hoje | Natureza |
|---|---|
| Eu: "nada chega em produção" | instrumento errado (nota datada lida como estado) |
| Eu: ids `UC-PTPAINEL-` | artefato inválido descoberto **depois** de escrito |
| Eu: "T0 é um move" | transporte inexistente entre os dois lados |
| Eu: 48 vs 54 | dois denominadores sem glossário |
| [CL]: grep sem controle | ausência de saída lida como ausência de fato |
| Repo: 5 testes fora da allowlist | catraca que não executa |
| Repo: assert tautológico Tier 0 | teste que não pode reprovar |

**Nenhuma é bug de tela.** Sete falhas, sete instrumentos. É isso que precisa de teste.

---

## LADO REPO — o que a máquina tem que provar sobre si mesma

| # | Teste do instrumento | Pergunta que responde | Dono |
|---|---|---|---|
| **M.01** | **Cobertura da allowlist**: para cada UC com `Teste:` preenchido no `casos.md`, esse teste está na allowlist da lane? | "o teste que prova o UC **roda** por PR?" — hoje 5 do Dashboard estão fora | [CL] |
| **M.02** | **Controle negativo por catraca**: mudar deliberadamente 1 copy e provar que `contrato-de-tela.mjs` **reprova** | "a catraca sabe reprovar, ou só sabe passar?" | [CL] |
| **M.03** | **Anti-tautologia em Tier 0**: todo teste `[T0]` precisa de um caso que **falha** quando a proteção é removida (controle positivo) | C.09 — assert que nunca reprova | [CL] |
| **M.04** | **Skip não é verde**: teste que deu `skip` não pode contar como coberto no `casos.md` nem no readiness | o skip do MultiTenantIsolation escondia a tautologia | [CL] |
| **M.05** | **Validação de id de UC na escrita**: lint que roda `uc-regex.mjs` no `casos.md` e reprova id inválido **na hora**, não no readiness | meu erro `UC-PTPAINEL-` | [CL] |
| **M.06** | **`alvo` de contrato existe**: os 11 `alvo` apontam para arquivo real | ponteiro podre (já aconteceu 1×) | [CL] |
| **M.07** | **Âncora = seção**: `grep -c data-contract` == nº de seções do contrato, por tela | contrato verde com 0 âncora seria falso-verde | [CL] |
| **M.08** | **Prazo de validade em doc que afirma estado**: doc sem `data:` ou com data velha demais é sinalizado | o `RUNBOOK-dashboard §4` fóssil · o `route-hits` expirado · **meu 05** | [CL]/[CC] |
| **M.09** | **Ledger de uso vivo**: `route-hits.json` com janela expirada deve **falhar barulhento**, não silenciar | hoje ele só envelhece calado (C.06) | [W]/[CL] |
| **M.10** | **Glossário de denominadores**: 48 (charters live) ≠ 54 (telas com protótipo) ≠ 216 (charters) ≠ 114 (com casos) — um lugar só define cada um | eu confundi dois hoje | [CC] |

---

## LADO COWORK (meu) — o que eu tenho que provar antes de colar

> Regra que hoje faltou: **artefato meu não sai daqui sem passar por um pré-flight que eu possa rodar sem o repo.**

| # | Pré-flight | Como eu verifico sozinho |
|---|---|---|
| **N.01** | Id de UC válido | prefixo `[A-Z][A-Z0-9]{0,5}` — **contar os caracteres**, sempre. `PAINEL`=6 ✅, `PTPAINEL`=8 ❌ |
| **N.02** | Frontmatter completo | comparar campo a campo com um `casos.md` vivo do `main` lido **no turno** |
| **N.03** | Ids de seção == contrato | os `id` do `casos`/charter batem com os `secoes[].id` do contrato |
| **N.04** | Nenhum ✅ sem lane | status nasce `⬜`; `last_run_ci` diz a verdade |
| **N.05** | Prefixo respeitado | Lei 1 do arquivo 03 — declarar no `_saida` qual prefixo toquei |
| **N.06** | **Transporte declarado** | todo pedido diz se o arquivo **existe no repo** ou **precisa ser colado**. "Move" só se eu vi o arquivo no `main` |
| **N.07** | **Fato ≠ relato** | afirmação sobre o repo só com leitura **minha**, no turno. Relato do [CL] entra como *"medido por [CL]"*, nunca como fato meu |
| **N.08** | Toda tabela de números com data e fonte | "medido 2026-08-23, por X" — senão é fóssil em gestação |

---

## O teste do PROTOCOLO em si (o que ninguém testou ainda)

| # | Teste | O que prova |
|---|---|---|
| **X.01** | **Round-trip de artefato**: eu escrevo → você cola → [CL] usa **sem reescrever** → volta veredito | que a ponte existe. Hoje: **falhou** (C.10 — ele teve que recriar) |
| **X.02** | **Falha honesta**: injetar de propósito um erro (copy divergente) e verificar que o ciclo **para** no portão certo | que os portões pegam, e qual pega |
| **X.03** | **Tela dupla**: rodar o ciclo em uma **segunda** tela e comparar atrito | que a receita generaliza — 1 tela é anedota |
| **X.04** | **Custo real**: quantas idas-e-voltas por tela? Se for >3, o protocolo não escala pra 25 | se S10 é viável ou fantasia |
| **X.05** | **Sinal de produção sem `route-hits`**: com o ledger expirado, o portão 12 ainda distingue "usada" de "não medida"? | que o oráculo não mente por omissão |

---

## Prioridade honesta

1. **M.01 + M.03 + M.04** — catraca que não executa e teste que não reprova são a mesma doença: **verde falso**. É o pior estado possível, pior que vermelho.
2. **X.01** — enquanto o round-trip não fechar uma vez, tudo que eu escrevo é rascunho caro.
3. **M.05 + N.01** — erro de id é 100% evitável e custou uma rodada hoje.
4. **M.09 + M.08** — o que envelhece calado produz diagnóstico errado. Produziu o meu.
5. **X.04** — antes de abrir 25 ondas, saber o custo de 1.

## A frase

O protocolo não precisa de mais catracas. Precisa que **cada catraca existente prove que sabe reprovar** — e que os dois lados parem de tratar relato como medição. Das sete falhas de hoje, cinco foram minhas, e todas as cinco eram evitáveis com o pré-flight N.

---

# Regime contínuo — nenhum destes é teste de uma vez

> Teste de instrumento rodado **uma vez** é pior que nenhum: cria a crença de que o instrumento está calibrado, e a crença envelhece calada. Foi assim que o `route-hits` e o `RUNBOOK §4` me enganaram.
> **Regra:** todo item das listas M/N/X ganha **cadência** e **modo de falha**. Sem os dois, não entra.

## Cadência

| Cadência | Quem roda | O que roda | Modo de falha |
|---|---|---|---|
| **Por PR** (required) | CI | M.01 cobertura da allowlist · M.05 id de UC · M.06 `alvo` existe · M.07 âncora == seção · M.04 skip não é verde | **bloqueia merge** |
| **Por PR** (advisory→required em 2 semanas) | CI | M.03 anti-tautologia nos `[T0]` tocados pelo PR | vermelho visível, promove quando estável |
| **Diário (cron)** | CI | M.09 janela do `route-hits` · M.08 docs que afirmam estado sem data ou vencidos | **abre issue automática**, não e-mail silencioso |
| **Semanal** | CI | M.02 controle negativo das catracas (muda copy de propósito, exige reprovação) · X.05 sinal de produção sem ledger | falha barulhenta no canal, não em log |
| **Por onda (S10)** | [CC]+máquina | readiness antes→depois · N.01–N.08 pré-flight de cada artefato | onda não fecha sem o par de números |
| **Por tela implantada** | [CL] | X.02 falha honesta (injeta erro, confere qual portão pega) | registra o portão, não só o resultado |
| **Trimestral** | [W] | M.10 glossário de denominadores · revisão das allowlists | dado sem dono definido é removido, não mantido |

## As três leis do regime

**Lei A — verde só vale com data.** Todo veredito carrega `medido_em`. Verde sem data é opinião. (Vale para mim, para o CI e para o `_saida`.)

**Lei B — o que envelhece tem que gritar.** Nenhum artefato de estado pode expirar em silêncio: janela de ledger, doc de estado, allowlist, baseline de checks. Expirou → falha ativa. Foi a ausência disso que produziu meu diagnóstico errado.

**Lei C — catraca sem controle negativo não conta como catraca.** Se não existe um caso provando que ela reprova, ela é decoração até prova em contrário. Aplicada retroativamente: as catracas de hoje precisam do M.02 antes de serem citadas como garantia.

## Sentinela mínima (se só um item entrar em CI, é este)

```
Para cada tela com casos.md:
  para cada UC com "Teste:" preenchido:
    o teste existe?            → senão: vermelho
    roda na lane deste PR?     → senão: vermelho  (M.01 — o buraco de hoje)
    pode reprovar? ([T0])      → senão: vermelho  (M.03)
    o status declarado bate com o último veredito real? → senão: vermelho (Lei A)
```

Isso é **uma** verificação e mata quatro classes de verde falso ao mesmo tempo. É o que eu pediria primeiro.

## Meu lado, continuamente

| Quando | O que eu faço, sempre |
|---|---|
| Antes de **cada** artefato sair daqui | rodar N.01–N.08 e **escrever no `_saida`** que rodei — checklist não declarada é checklist não feita |
| Ao citar qualquer número do repo | dizer **quem mediu e quando**; relato do [CL] entra como relato (N.07) |
| Ao encontrar nota datada | tratar como fato **daquele dia**, nunca como estado — e, se importa, remedir no turno |
| Ao escrever doc de estado | `data:` no frontmatter + validade declarada. Se vencer, ele mesmo se acusa (M.08) |
| Ao fechar onda | reportar readiness antes→depois. Sem o par, a onda não aconteceu |

## Métrica única do regime

```
verde falso detectado por mês  →  objetivo: > 0
```

Contra-intuitivo de propósito: **zero detecção não é saúde, é cegueira.** Enquanto o regime não achar nada, ele não está funcionando — hoje achou dois (allowlist e tautologia) sem nem existir formalmente.
