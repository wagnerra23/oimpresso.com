---
id: resources-js-pages-ponto-importacoes-index-casos
casos: Histórico de importações AFD/AFDT · /ponto/importacoes
irmaos: Index.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §5.3 F7 + §6.4/§6.5 (contrato)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é o registro de origem das marcações vindas de REP-A — a rastreabilidade que a fiscalização pede começa aqui.
owner: wagner
last_run: "2026-08-08"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Ponto · MySQL)"
---

# Casos de Uso & Aceite — Histórico de importações

> **Âncora:** `CU-PONTO-11` (§6.4) e `CU-PONTO-12` (§6.5) do
> [SDD](../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md) + **US-PONTO-002**
> (*"registra arquivo + checksum + linhas processadas + erros"*) · **Portaria MTP 671/2021
> Anexo I** (rastreabilidade). Fonte 4 (Delphi) **ausente** — SDD §0.1.
>
> 🔗 **Não duplica a tela irmã.** `Importacoes/Show.casos.md` já cobre `UC-IMPSHOW-01..04`
> (dedup por hash, dedup escopada ao business, 404 cross-tenant e as contagens do detalhe).
> Aqui só entra o que é **da lista**.
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Ponto · MySQL)` — **advisory**: fica vermelha
> visível, não bloqueia merge (SDD §8.1).
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-IMPIDX-01 | O histórico traz as importações do meu empregador, recentes primeiro | must | `CU-PONTO-11` + US-PONTO-002 | `ImportacaoIndexContratoTest` | 🧪 sem veredito |
| UC-IMPIDX-02 | Importação de outro empregador não aparece no histórico | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `ImportacaoIndexContratoTest` | 🧪 sem veredito |
| UC-IMPIDX-03 | A contagem exibida na lista reflete o que foi processado | must | `CU-PONTO-11` + SDD §9 D-8 | `ImportacaoIndexContratoTest` | 🧪 **vermelho ESPERADO** (predição) |

**[BACKLOG]:**

- `[BACKLOG]` **Ampliação medida do D-8 (2026-08-02), pertence à tela `Show`:** além de
  `linhas_criadas`/`linhas_ignoradas`, o controller lê **`erro_mensagem`** — que também não é
  coluna nem está no `$fillable` (as reais são `log` e `erros_amostra`). O `Show.tsx:82` faz
  `{i.erro_mensagem && <Alert>…}`, logo **o alerta de erro nunca renderiza**: uma importação
  que falhou não mostra o motivo. É consequência mais séria que "exibe 0", e o SDD §5.3 F7
  lista `erro_mensagem` entre os campos acompanhados **sem notar que é fantasma**. Vira
  `UC-IMPSHOW-05` quando a tela `Show` for tocada por trabalho real — não abro aqui porque o
  caso é dela, e varrer em lote acorda gate diff-aware sobre dívida alheia
  ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-12).
- `[BACKLOG]` Ordenação por `created_at` desc e paginação 20/pág — contrato de apresentação
  sem âncora em lei nem US; vira UC quando [W] confirmar que a ordem é parte do contrato.

---

## UC-IMPIDX-01 · O histórico traz as importações do meu empregador, recentes primeiro · `must`

- **Persona:** RH que acabou de subir o arquivo do relógio e quer confirmar que ele entrou —
  e, meses depois, o auditor que precisa achar de onde veio uma marcação específica.
- **Aceite:** Dado que importei um arquivo · Quando abro `/ponto/importacoes` · Então a
  importação aparece na lista, com nome do arquivo e estado.
- **Teste:** `Modules/Ponto/Tests/Feature/ImportacaoIndexContratoTest.php` — `UC-IMPIDX-01`.
- **Contrato:** `CU-PONTO-11` (SDD §6.4) · US-PONTO-002 · F7 (§5.3) · Portaria 671/2021
  Anexo I (rastreabilidade).
- **Regressão que defende:** a lista é a **única** superfície que liga marcação a arquivo de
  origem. Se ela some ou some um registro, a cadeia de rastreabilidade quebra em silêncio —
  e só se descobre na fiscalização, que é o pior momento possível.
- **Status: 🧪 sem veredito.**

---

## UC-IMPIDX-02 · Importação de outro empregador não aparece no histórico · `must` `[T0]`

- **Persona:** plataforma multi-tenant. O nome do arquivo e o hash identificam o relógio e a
  operação de outro empregador.
- **Aceite:** Dado um arquivo importado por **outro** business · Quando abro
  `/ponto/importacoes` do meu · Então ele **não** aparece na lista.
- **Teste:** `ImportacaoIndexContratoTest.php` — `UC-IMPIDX-02`.
- **Contrato:** `CU-PONTO-12` (SDD §6.5) · US-PONTO-007 ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** complementa `UC-IMPSHOW-03` (que prova o **404 no detalhe**) pelo
  outro lado: um `show` blindado não impede uma **lista** vazada, e a lista é onde o nome do
  arquivo alheio ficaria visível sem ninguém precisar adivinhar um id.
- **Nota de teste:** biz=1 vs stub biz=99 — **nunca biz=4** ([ADR 0101]). O stub precisa
  existir: sem ele o INSERT morre na FK e o caso não exerce isolamento (medido na run
  30778424885).
- **Status: 🧪 sem veredito.**

---

## UC-IMPIDX-03 · A contagem exibida na lista reflete o que foi processado · `must`

- **Persona:** o RH pergunta *"entrou tudo?"*. A lista responde com um par `criadas/processadas`
  — é a resposta mais rápida disponível, e a que ele usa para decidir se precisa reimportar.
- **Aceite:** Dada uma importação concluída que processou N linhas com sucesso · Quando vejo a
  linha dela no histórico · Então a contagem exibida **não** é zero.
- **Teste:** `ImportacaoIndexContratoTest.php` — `UC-IMPIDX-03`.
- **Contrato:** `CU-PONTO-11` · US-PONTO-002 (*"registra … linhas processadas + erros"*) ·
  SDD §9 **D-8**.
- **Regressão que defende:** é o **D-8 na superfície da lista**. `ImportacaoController@index`
  monta `'linhas_criadas' => (int) ($i->linhas_criadas ?? 0)` — atributo que não é coluna nem
  está no `$fillable` (as reais são `linhas_sucesso`/`linhas_erro`), e o `?? 0` **esconde** a
  ausência. O `Index.tsx:118` renderiza `{i.linhas_criadas}/{i.linhas_processadas}`, então a
  lista mostra `0/N` para toda importação, inclusive as 100% bem-sucedidas.
- **Por que um UC separado do `UC-IMPSHOW-04`:** é a **mesma raiz em duas superfícies**. Se a
  correção for no modelo (accessor/`$appends`), os dois ficam verdes juntos; se for só no
  controller do `Show`, este segue vermelho — **e é exatamente esse o sinal que se quer**.
  Um único UC não distinguiria a correção parcial da completa.
- **Nota de escrita:** o assert é *"a contagem não é zero quando houve sucesso"*, não uma
  igualdade com o número exato — assim ele vale para qualquer correção (renomear a leitura,
  criar accessor, ou passar a expor `linhas_sucesso`).
- **PREDIÇÃO: vermelho.** O veredito real vem da lane, não desta leitura (G-7).
- **Status: 🧪 vermelho ESPERADO.**

## Trilha do tempo
- 2026-08-08 · [CC] revalidado (bump `last_run`): migração do primary "Nova importação" do shim
  DEPRECATED `PontoPrimaryButton` pro canon `<PageHeaderPrimary>` (ADR 0190). O shim emitia
  `.os-btn primary`, cuja única família de regras no CSS servido é escopada `.sells-cowork` →
  nenhuma casava e o botão rendia nu (medido em prod: padding 0, radius 0, texto em 2 linhas).
  Só o chrome do header mudou — `label` e `onClick` idênticos; nenhum UC descreve o botão e
  nenhum `Status:` foi promovido. O bump afirma "trio reconciliado com a tela nesta data",
  não "testes rodados" (rodam no CT 100).
