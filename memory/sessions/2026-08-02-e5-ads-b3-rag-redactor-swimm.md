---
id: sessions-2026-08-02-e5-ads-b3-rag-redactor-swimm
type: session
date: "2026-08-02"
topic: "E5 do ADS (archive + drop), auto-sync do Swimm traduzido, B3 no RAG in-place, o redactor que comia recibo de CI, e o ciclo de aprendizado fechado 10/10"
authors: [C]
module: Jana
owner: W
related_adrs:
  - 0363-governance-incorpora-ads-nucleo-sem-receptor
  - 0365-trio-de-tela-fica-colocado-reverte-eixo-0364
  - 0364-trio-de-tela-mora-em-memory-emenda-0264
  - 0344-two-strikes-cobre-processo
pii: false
---

# Sessão 2026-08-02 — E5 do ADS, auto-sync do Swimm, B3 no RAG e o redactor que comia recibo

**TL;DR** — Fechei o E5 da deprecação do ADS (arquivo no CT 100 + drop de 5 tabelas, com smoke real),
traduzi o mecanismo de auto-sync do Swimm para a âncora doc→código, levei o trio de tela para o RAG
sem sair do lugar (B3), corrigi o `PiiRedactor` que confundia run id do GitHub com CPF, e fechei o
ciclo de aprendizado com 4 classes de lição atualizadas. 9 PRs. O que valeu mais não foi o código:
foram três medições desconfiadas.

---

## 1. E5 — o dado do ADS

O plano de deprecação chegou até aqui com o código já removido, mas **as 36.986 linhas de
`mcp_dual_brain_decisions` ainda no banco**. O E5 é o único passo irreversível.

**Arquivei antes de dropar**, e isso é o que torna o resto aceitável: dump no CT 100 (nunca em git),
conferido nas duas pontas por SHA-256 **e** por contagem de INSERT dentro do artefato — usei
`--skip-extended-insert` justamente para a contagem ser verificável sem restaurar. Três artefatos: a
tabela inteira (36.986), o recorte das **41 com decisão humana** que a ADR 0363 mandou preservar, e a
config. Arquivei tudo, não só as 41: 10 MB comprimidos são mais baratos que uma perda mal medida.

**A lista de DROP encolheu pela terceira vez.** As erratas anteriores já tinham tirado
`mcp_projects`/`mcp_project_parts` (E3) e `mcp_decision_links` (C5). Medindo os consumidores vivos,
achei mais duas: `mcp_tool_executions` (o `ToolsController` da Forja faz INSERT a cada execução de
tool) e `mcp_user_module_access` (o `UserScopeService` faz `updateOrInsert`). **As duas alimentam
rotas que o smoke do E6 tinha registrado como vivas (302)** — dropá-las converteria em 500.

Ficou **5 dropam, 6 ficam**. E declarei o dono das 6 no `db_tables_owned` dos SCOPEs (Forja e
Governance), porque sem dono declarado a próxima varredura de deprecação as acha órfãs e repete o erro.

Medi em produção antes: zero FK entrando, zero trigger, zero view citando as 5. Nada bloqueava.

## 2. O auto-sync do Swimm, traduzido

A grade comparativa da manhã tinha dado **2/10** na dimensão auto-sync (contra 9 do Swimm). Fui
implementar — e a parte que importa é o que **recusei**.

O Swimm faz quatro coisas: doc acoplada a trechos, detecção de mudança, **regravar a doc**, e IDE.
Três traduzem. A terceira não: `casos.md` **não é prosa explicativa, é contrato que julga o código**.
Máquina que reescreve a asserção para casar com o código produz contrato tautológico — o erro que o §5
já enterrou em 2026-06-05. Auto-sync de endereço é conserto; de asserção seria anistia.

Então o script mexe **só no ponteiro**. E não inventei token store: o git já é um. Uma ref
`Arquivo.php:443 (verificado@a1b2c3d)` diz onde o trecho estava e em que estado do mundo, então drift
vira comparação, não heurística. Escreve só quando o texto original aparece **uma única vez** hoje;
ambíguo e perdido viram relatório.

Depois desambiguei as âncoras (24 → 3) com três pernas determinísticas — irmão colocado, relativo ao
módulo, e "qual candidato tem essa linha" — e carimbei 63 das 66.

## 3. B3 — o trio no RAG sem sair do lugar

A ADR 0364 queria mover o trio para `memory/` porque o indexador só varre lá. O B3 mostra que mover
não era necessário: glob **aditivo** sobre `resources/js/Pages/**`, por iterador (o `glob()` do PHP não
recursa), com o enum expandido **antes** — porque com `strict => false` o MySQL grava `''` em silêncio,
que foi como os BRIEFINGs ficaram 18 dias sem filtro funcionando.

Em produção: **210 charters + 74 casos = 284 indexados**, `mcp_index_sync_gap` em **0**.

Rodei `--only=charter` e `--only=casos` **antes** do cron pegar tudo — o residual conhecido é "sync
completo falha com deadlock/OOM", e 284 de uma vez é o gatilho. Essa janela existiu por acaso: o
primeiro deploy falhou no pré-check por SSH timeout, **antes de qualquer escrita**.

## 4. O redactor que comia recibo de CI

O sync reportou **32 redactions de PII** em 8 `casos.md`. Fui olhar: eram **run id do GitHub Actions**.

`30366164436` tem 11 dígitos — o tamanho de um CPF sem pontuação — e o redactor não valida dígito
verificador. Resultado: `run 30366164436 (PR #4953)` virava `[REDACTED]` no índice. **Não vazava nada;
apagava a rastreabilidade** que a regra de evidência do projeto exige.

E havia **duas** colisões, não uma — a segunda só apareceu porque testei a primeira: liberado o CPF, o
regex de telefone casava os 10 primeiros dígitos. Meu primeiro fix passou no lint e falhou no smoke.

Desempate por regra de formação, só para dígito cru: **CPF tem DV, telefone tem DDD que existe**.
Formato pontuado continua sendo declaração e é redigido sem exigir prova — CPF digitado errado ainda é
tentativa de PII.

## 5. O achado maior veio de um número que não mexia

Os 6 testes novos não mudavam o total da lane: **33 antes, 33 depois**. Três camadas apareceram:

1. `Modules/Jana/Tests/Unit` **nunca esteve no `phpunit.xml`** — 10 arquivos que o CI jamais rodou,
   incluindo o `PiiRedactorTest`, o teste do redactor de PII do sistema.
2. Registrar não bastou: a lane **lista arquivo por arquivo**, não usa testsuite.
3. O `[added] <arquivo>` no log é listing de arquivos mudados do PR — **não é execução**, e eu quase
   li como se fosse.

Só rodou depois de ligar na lane certa nos dois pontos (`paths:` e comando), incluindo **o arquivo sob
teste** no trigger. Aí o contador virou **38 passed**.

Medindo os 6 módulos com `Tests/Unit`, o buraco era maior: **NfeBrasil e RecurringBilling continuam
fora**. Registrei só a Jana — mexer nos outros seria big-bang sobre dívida de terceiro.

## 6. ADR 0365 — o trio fica colado

[F] tinha cortado o eixo da 0364 no dia anterior (*"eu quero como no fonte"*). [W] autorizou o flip.

A reversão é **parcial** de propósito: supersessão total rebaixaria a 0364 e mataria junto o raciocínio
*"o RAG não exige o move"*, que é o que sustenta a Opção B. E o único gap da Opção B **já estava
fechado em produção** pelo B3 — a cláusula (c) do gate de reversão foi testada, não estimada.

## 7. Os 10 erros meus

[W] pediu a matriz do ciclo e ela expôs o desequilíbrio: 6 entregas fechadas, **8 de 10 erros sem
lápide**. Fechei: LC-08 → 39, LC-13 → 7, e duas classes novas — **LC-16** (reescrita textual sem
âncora: o carimbo comeu `:66-82`, o split/join duplicou o path) e **LC-17** (recuo à mão que virou
estado de branch e voltou em uma hora).

Classifiquei antes de criar: li LC-14 e LC-15 e elas não cobrem — abrir classe por preguiça de procurar
infla o ledger.

## 8. O que aprendi que não é sobre este código

**Prova de identidade em toda reescrita automática de doc.** Reverter a transformação pretendida no
diff e exigir texto byte-idêntico foi o que pegou os dois defeitos do LC-16 antes do commit. Verde do
script não é prova.

**A prova de que um teste rodou é o contador subir.** Nome de arquivo no log não é contador.

**Quando um gate acusa algo que você não fez, meça a distância pro `main` primeiro.** Três vermelhos
desta sessão eram corrida com o repositório, não defeito — um deles parecia que eu revertia uma ADR que
[W] tinha acabado de ratificar.

**Indexar em produção pagou mais que auditar.** As 32 redactions indevidas não apareceriam em nenhuma
leitura estática minha.

## Pendências (chips abertos)

- reindexar `--only=casos` — os 8 docs seguem com `[REDACTED]` até rodar com o redactor corrigido
- E7 do ADS — lápide §5 + BRIEFING final
- `Modules/{NfeBrasil,RecurringBilling}/Tests/Unit` fora do `phpunit.xml`
