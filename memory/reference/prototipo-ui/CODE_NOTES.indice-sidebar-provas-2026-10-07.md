# Playbook `sidebar`: 15 threads entregues, o placar diz "0 de 16" — o que é do índice

> **O que é:** devolutiva `[CL]` → `[CC]` da triagem de 2026-10-07 das threads 01–04 e 06–16
> do playbook `cowork-inbox/sidebar/playbook/` (a 05 é de [W] e ficou fora).
> **Por que aqui e não no espelho:** o `00-INDICE.md` mora em `prototipo-ui/cowork/Wagner/`,
> que é espelho de leitura (ADR 0374). Editar lá à mão é desfeito no próximo import e arma o
> required "espelho — mexeu depois de verificar" (§5 2026-09-24). A correção é na fonte.

## O placar, medido em `origin/main` `c8777ae00a` (2026-10-07)

`node scripts/qa/placar.mjs --indice` → `Sidebar: entregue 0 de 16 · em curso 16`.

Toda thread tem `_saida-NN.md` e PR mergeado. O que segura o placar:

| causa | threads | de quem é |
|---|---|---|
| prova que fecha é de **recibo** (`comparacao`/`execucao`/`revisao`), e o avaliador saiu do repo com a ADR 0397 — o placar a marca **não medida** | todas as 16 | decisão: portar o avaliador ou reescrever as provas como estruturais (ver §3) |
| pré-condição que casa **comentário** | 01 | índice ou `sidebar.jsx` (Cowork) |
| dependência de uma thread que nunca chega a `feito` | 03, 04, 07–16 | consequência das duas linhas acima |

Nenhuma thread está em curso por falta de trabalho de código. O que o código ainda devia está
nos PRs [#8893](https://github.com/wagnerra23/oimpresso.com/pull/8893) (thread 14),
[#8901](https://github.com/wagnerra23/oimpresso.com/pull/8901) (thread 08) e
[#8902](https://github.com/wagnerra23/oimpresso.com/pull/8902) (thread 16).

## 1 · Correções pontuais no `00-INDICE.md` (§7, bloco json)

1. **Thread 01 — a pré-condição `nao_contem 'role="link"'` nunca passa.** A única ocorrência
   em `prototipo-ui/cowork/Wagner/sidebar.jsx` é o comentário da própria correção (linha 164:
   ``// A1 (2026-09-10): era `<div role="link" tabIndex={0}>` …``). `grep -c` = 1, e é esse
   comentário. Duas saídas, qualquer uma basta: reescrever o comentário sem citar o literal
   (prefixo da 01, lado Cowork), ou trocar a prova por uma que só o código satisfaça.
   Mesma classe da §5 2026-09-18: a sonda de conferência casa o texto que o próprio ato produz.
2. **Thread 15 — prefixo errado.** O índice diz `scripts/design/`; os arquivos que a thread
   mudou são `scripts/design-sync/alvo.mjs` e `scripts/qa/secao-check.mjs`
   (`scripts/design/alvo.mjs` não existe). A `_saida-15` já pedia esta correção.
3. **Thread 06 — tipo de prova.** O índice declara `revisao`, que por contrato só fecha thread
   que escreve `.md`/`.contract.json`; o prefixo da 06 inclui `tests/Feature/Sidebar/`. A
   `_saida-06` diz que trocou para `execucao`, mas o índice atual voltou para `revisao` (um
   export posterior sobrescreveu). Os recibos que a 06 gravou estavam em
   `prototipo-ui/design-docs/…/recibos/`, árvore removida pela ADR 0397; `git ls-files` não
   acha nenhum recibo do playbook hoje.
4. **Thread 14 — lista de testes.** A prova cita só `SidebarMenuItemContractTest.php`; o render
   (`tests/Feature/Sidebar/ghost-icone.spec.tsx`) entra na lane jsdom pelo #8893. Se a prova
   deve cobrir os dois lados, acrescente-o.
5. **Threads 09, 10 e 13 — `testes` largos demais.** `tests/js/` e `tests/Feature/` pedem a
   pasta inteira verde para fechar uma thread. As `_saida` nomeiam os arquivos exatos:
   09 → `tests/js/sidebar-item-ativo.test.tsx` · 10 → `tests/js/sidebar-ghosts-teto.test.tsx` ·
   13 → `tests/Feature/Sidebar/PresencaPreferenciaTest.php` + `tests/Feature/Sidebar/presenca.spec.tsx`.
6. **Thread 04 — `depende de 01`.** A 04 foi entregue antes (#7209, 2026-09-11) e não lê nada
   da 01. A dependência só a prende ao estado da 01.

## 2 · O que segue pendente e NÃO é do índice

- **01 (Cowork):** o modo `hidden` do protótipo serve tela em branco — `.app--sb-hidden` zera a
  faixa do grid em vez de removê-la (`_saida-04` §2, com medição). O shell já está certo.
- **03 (Cowork):** a tabela de diferença do `CompanyPicker` nos dois sentidos não foi feita.
- **02 (Cowork):** a matriz sem-chave × largura precisa de perfil limpo.

## 3 · A decisão que destrava as 16 de uma vez

O placar avalia só provas estruturais (`arquivo`, `contem`, `nao_contem`, `json_com_chaves`,
`um_de`, `ausente`). Enquanto a prova que fecha for de recibo, nenhuma thread deste playbook
chega a `feito`, por mais que o trabalho esteja no `main`. Os caminhos: portar o avaliador de
recibo (o docblock de `scripts/qa/placar-indice.mjs` lista as 4 peças que faltam) ou declarar
nas fichas uma prova estrutural que o placar mede. É decisão de processo, de [W]/[CC], e vale
para todos os playbooks, não só este.
