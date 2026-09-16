# A conta perdeu 510 linhas de `cowork-inbox/` entre o handoff 18 e o 20 — e o achado mais citado delas já estava morto

> **De:** Claude Code → **Para:** Cowork · **Data:** 2026-09-16
> **O que é:** o handoff 20 desceu **fiel** e o pacote veio `CONFORME` (primeiro ciclo em que a
> recepção não precisou descartar o `sync/` — recibo no `_saida-02.md` da thread `recepcao-pacote`,
> [PR #7414](https://github.com/wagnerra23/oimpresso.com/pull/7414)).
> Mas 17 arquivos de `cowork-inbox/**` vieram **com menos conteúdo** do que tinham no handoff 18.
> Por decisão [W] 2026-09-16 (*"traz os 36"*) o espelho agora carrega a versão da conta — ADR 0398
> D1, *"espelho que muda a forma do original não é espelho"*. Logo o conteúdo perdido existe **só no
> git**, e esta nota diz onde, o que vale restaurar e **o que NÃO restaurar**.

---

## 1 · O tamanho da perda, medido

| arquivo | linhas substituídas | ganhas |
|---|---:|---:|
| `patrimonio/playbook/00-INDICE.md` | **379** | 45 |
| `sidebar/playbook/00-INDICE.md` | 57 | 21 |
| `hrm/hrm-licencas.contract.json` | 31 | **81** |
| `governance/playbook/00-INDICE.md` | 10 | 2 |
| `hrm/playbook/00-INDICE.md` | 8 | 2 |
| `ponto/playbook/00-INDICE.md` | 7 | 3 |
| outros 11 (cms ×4, connector ×2, compras, fiscal, governance.contract, notificacoes, ancora-ds) | 1–3 cada | 1–3 cada |
| **total** | **510** | **171** |

Recuperação, arquivo por arquivo:

```
git show ba8e812d687:prototipo-ui/cowork/Wagner/cowork-inbox/<mod>/playbook/00-INDICE.md
```

`ba8e812d687` é o [#7256](https://github.com/wagnerra23/oimpresso.com/pull/7256) (14/09,
*"o espelho Cowork recebe a árvore da conta"*) — o único commit que esses arquivos tinham antes de
hoje. **Nenhum deles foi editado do lado Code**; é por isso que a sobreposição de hoje não destruiu
trabalho nosso, e é também por isso que a perda é da conta, não nossa.

Só o `hrm-licencas.contract.json` **ganha mais do que perde**: troca de schema inteiro, de chaves pt
(`tela`/`fonte`/`secoes`/`_proibicoes`) para en (`screen`/`route`/`sections`/`forbidden`). Se isso é
deliberado, ótimo — mas os outros 16 não têm essa explicação.

## 2 · ⛔ NÃO restaure o D1 do patrimônio — ele foi consertado no mesmo dia em que a errata foi escrita

A linha mais substancial das 379 é a errata do `D1`:

> ~~**D1 caiu.**~~ ⚠️ **ERRATA [CL] 2026-09-08 — D1 NÃO caiu** … *suas guardas são
> `! ((can('asset.view_all_maintenance') && can('asset.view_own_maintenance')) || …)` em `:63`, `:208`,
> `:243`, `:286`, `:322`. O `&&` exige **as duas** permissões, então quem tem só `view_own_maintenance`
> — o técnico — é bloqueado das próprias manutenções. **D1 vive, em 6 sítios.***

**Medido hoje, 2026-09-16, no `main`:**

```
arquivo ........... 15.724 B (na errata)  →  29.625 B (hoje)
padrão com `&&` ... 6 sítios (na errata)  →  1 ocorrência, e é COMENTÁRIO (`:29`)
string superadmin .. 0 (na errata)        →  7
guardas vivas ...... `! (can('asset.view_all_maintenance') || can('asset.view_own_maintenance'))`
                     em :108, :297, :338, :387, :429, :467 — `||`, não `&&`
controle positivo .. `grep -c function` = 22 (a sonda lê o arquivo)
```

O próprio docblock do arquivo (`:20-50`) registra o conserto, datado **2026-09-08** — o mesmo dia da
errata — e ele **acha um segundo defeito que a errata não viu**:

> *"AUTORIZAÇÃO (corrigida em 2026-09-08 — eram DOIS defeitos no mesmo `if`)."*
>
> **(a)** o `&&` exigia as duas permissões, e elas são `is_radio` com o mesmo `radio_input_name`
> (`view_maintenance`) em `DataController::user_permissions()` — **mutuamente exclusivas na UI de
> papéis**, ou seja, o gate era *insatisfazível por construção*, não só apertado.
>
> **(b)** o `|| subscription` **anulava o gate inteiro**: o segundo operando é verdadeiro para todo
> usuário do business que assina o módulo, então o `if` colapsava em *"o módulo está assinado"*. Por
> isso **(a) nunca apareceu em produção** — e consertar só o `&&` não mudaria nada em runtime.

A forma correta que ficou: permissão de TELA primeiro, gate de assinatura DEPOIS, **dois `if`
sequenciais** — nunca em `OR` um com o outro.

**Consequência para vocês:** restaurar a errata verbatim reintroduz no playbook um pedido para
consertar algo já consertado, descrito de um jeito que subestima o defeito (dizia "bloqueia o
técnico"; era "o gate não existia"). Se restaurarem, restaurem **corrigida**, citando o docblock.

## 3 · O que das 379 linhas vale restaurar

Classificação das linhas perdidas do `patrimonio/00-INDICE.md`:

| categoria | linhas | vale restaurar? |
|---|---:|---|
| blocos `ERRATA` (incl. o D1 acima e a do comando do placar) | 6 | **parcialmente** — ver §2 |
| frente de UI: threads **07–13** abertas pela ADR 0394 | 9 | **sim** — é escopo, não achado |
| `D-ENDERECO` **respondida** em 2026-09-08 (`Pages/Patrimonio/**`, ADR 0394) | 4 | **sim** — decisão viva |
| comando/render do placar (`_scripts/placar-indice.mjs`, não `scripts/qa/`) | 5 | **sim** — o caminho antigo não existe |
| restante (tabela de threads, `05 → NÃO EXECUTAR`, ordem das vagas, prosa) | 355 | **sim, em bloco** |

O maior valor não é o achado — é o **escopo**: a versão do handoff 20 volta a dizer *"Nenhuma tela
React existe — e nenhuma nasce antes da decisão D-ENDERECO"*, quando a D-ENDERECO **foi respondida**
e 7 threads de UI (07–13) já estavam abertas no índice de 14/09.

## 4 · Sete dos 17 trazem path que não existe em árvore nenhuma

O handoff 20 **introduz** referências quebradas em `fonte` / `blueprint_cowork`. Medido nas duas
árvores (o ZIP extraído e o espelho):

```
handoff 20 propõe:  prototipo-ui/cowork/cms/cms-page.jsx        → não existe em lado NENHUM
real (nos dois):    prototipo-ui/cowork/cms-page.jsx            → existe no ZIP E no espelho
```

Mesmo padrão em `connector/connector-page.jsx` e `notificacoes/notificacoes-page.jsx` — o segmento
de módulo foi **inserido** num path plano. E o `ancora-ds/ds-anchor-check.mjs` erra para o outro lado:
**perde** o segmento `Wagner/` (`prototipo-ui/cowork/arquivos-page.jsx`, quando o espelho é
`prototipo-ui/cowork/Wagner/`).

Arquivos afetados: `cms/Index.charter.md`, `cms/SiteDetails.charter.md`, `cms/cms-content.contract.json`,
`cms/cms-site-details.contract.json`, `connector/Index.charter.md`,
`connector/connector-api.contract.json`, `notificacoes/notificacoes.contract.json`, `ancora-ds/ds-anchor-check.mjs`.

Trouxemos fielmente (é conteúdo da conta) e **não morde CI** — o `contrato-de-tela` lê de
`governance/design/contracts/`, e `blueprint_cowork` é uma das duas chaves que a cadeia de âncora
ignora por decisão. Mas custa o tempo de quem abrir a thread e for procurar o arquivo.

## 5 · O pedido

1. **Restaurar, upstream, o conteúdo dos 17** a partir de `ba8e812d687` — **menos** o D1 do
   patrimônio, que sai corrigido ou não sai (§2).
2. **Corrigir os 8 paths** do §4 na conta, para o path plano que de fato existe.
3. **Dizer o que causou a perda.** Deste lado não é observável: os arquivos têm um commit só, então
   não há como distinguir *"a conta editou"* de *"o export pegou outra cópia"*. É a segunda vez que
   o playbook do patrimônio precisa de errata — a primeira é
   [`CODE_NOTES.errata-playbook-patrimonio-2026-09-08.md`](CODE_NOTES.errata-playbook-patrimonio-2026-09-08.md),
   e o conteúdo dela também não sobreviveu.
4. **Sugestão, não pedido:** se a regeneração do bundle por ciclo já é rotina do lado do design
   (decisão [W] 2026-09-06), valeria a mesma disciplina para o `cowork-inbox/` — porque hoje o
   `receber-handoff.mjs` tem guarda de regressão **para o bundle** (`[3b]`, recusa aplicar ZIP
   atrasado) e **não tem** para o `cowork-inbox/`, que viaja fora do fechamento do shell. Foi
   exatamente aí que a perda passou.

---

**Portões deste ciclo** (todos pós-sobreposição): `cowork-ssot-guard` 0 · `mirror-freshness
--absent-local` 0 ausentes · `--check-refs` 0 deleções · `deadlink-gate --check` 0 (*"nenhum arquivo
vivo piorou vs baseline"*) · `ancora-guard --selftest` + guard 0 (223 charters) · `contrato-de-tela
--map --check` 0 · `--anti-tautologia` 0.

**PRs:** [#7414](https://github.com/wagnerra23/oimpresso.com/pull/7414) (recibo do ciclo) ·
[#7422](https://github.com/wagnerra23/oimpresso.com/pull/7422) (os 36).
