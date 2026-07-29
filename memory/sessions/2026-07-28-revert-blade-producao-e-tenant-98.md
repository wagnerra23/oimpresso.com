---
date: "2026-07-28"
hour: "22:38 BRT"
topic: "Blade de produção mergeado sem smoke, revertido — e o tenant de teste sai do biz=1 para o 98"
authors: [C]
prs: [4943, 4974, 4986, 4992, 4994]
related_adrs: [0101-tests-business-id-1-nunca-cliente, 0093-multi-tenant-isolation-tier-0, 0062-separacao-runtime-hostinger-ct100]
---

# Blade de produção revertido; tenant de teste vai para o 98

**TL;DR** — Mergeei o [#4943](https://github.com/wagnerra23/oimpresso.com/pull/4943), que alterava `resources/views/product/edit.blade.php` — **tela viva de produção** — sem o smoke em biz=1 que o próprio PR declarava obrigatório. [W] cortou (*"Blade não deve ser alterado está em produção"*). Revertido pelo [#4994](https://github.com/wagnerra23/oimpresso.com/pull/4994): o arquivo voltou **byte-a-byte**, verificado por `git diff` vazio contra `d773e3da^`. No mesmo turno, o tenant canônico de teste saiu de `biz=1` (WR2 Sistemas, empresa real de [W]) para **`biz=98`** — e **não** para o 99 como o PR propunha, porque o 99 já era o cliente do Modo Suporte e a colisão tornaria o teste de isolamento cross-tenant tautológico.

## O erro que pagou a sessão

O #4943 corrigia um defeito real: `enable_stock` sendo zerado ao salvar. Mas o defeito **só se manifesta pela tela React**, e o próprio PR mediu que **ninguém alcança a tela React hoje** (sidebar usa `<a href>` puro, sem header `X-Inertia` → roda o Blade). O par writer+Blade era tecnicamente necessário — `spatie/laravel-html::checkbox()` não emite `hidden`, então "preservar ausência" sem o `hidden 0` tornaria impossível desmarcar na tela que roda em produção.

Ou seja: **mexeu-se numa tela viva para viabilizar a correção de uma tela inalcançável.** Risco hoje, benefício só no cutover. Eu tratei o Blade como pendência de rodapé ("falta o smoke") quando ele era o ponto que exigia decisão de [W] **antes** do merge.

Em produção os dois estados são equivalentes — desmarcar mandava ausência (writer zerava) × desmarcar manda `0` explícito (writer grava 0). O que decidiu o revert não foi um bug provado: foi que **o antigo tem anos de uso e o novo tem zero smoke**, sem benefício hoje.

## O achado que evitou um problema maior

O [#4974](https://github.com/wagnerra23/oimpresso.com/pull/4974) apontava `SEEDED_TENANT_ID` para **99**. Só que 99 já era `SUPPORT_CLIENT_TENANT_ID` — a empresa-CLIENTE que a suíte do Modo Suporte acessa a partir do tenant do AGENTE.

A proposta afirmava que o 99 tinha *"zero consumidores, zero materialização"*. **Falso, e medido:** `seededSupportClientTenant()` — o helper que embrulha a constante — tem **~33 call-sites em 6 arquivos** e materializa o 99 sob demanda desde o #3563. A varredura original procurou o **nome da constante**, não o **helper**.

Com os dois em 99, agente e cliente virariam a mesma empresa e a suíte de Modo Suporte ficaria **verde sem provar isolamento**. Agravante: essa suíte **não está em nenhuma lane que reprova**, então o CI verde **não desmentiria**.

Correção: `SEEDED_TENANT_ID = 98` (livre — prod tem 82 businesses, nenhum entre 95 e 105), `SUPPORT_CLIENT_TENANT_ID = 99` intacto. Sincronizado nos **4** lugares que carregam o valor, incluindo a cópia obrigatória em `EstoqueFixture` (`Trait::CONST` direto é fatal no PHP) — que eu quase deixei dessincronizada.

## O bug de arranjo que a troca de tenant revelou

As 2 falhas de `ComprasListagemNPlusUmTest` **não vinham do tenant novo**. O teste fixava `Business::find(1)` e só funcionava **por acidente**: o seed criava `business_locations` justamente para o tenant seedado, que era o 1. Com o tenant fora do 1, o ramo de insert passou a rodar e estourou `1452` em `business_locations_invoice_scheme_id_foreign` — a coluna é `int unsigned NOT NULL` **com FK**, então omitir deixa o default `0` (inexistente na tabela pai) e `NULL` não é opção.

Corrigido só no arranjo: tenant pela constante + `invoice_schemes` resolvido/criado antes. Zero assert, zero código de produção.

## Erros meus de medição — todos da classe LC-08

1. **Declarei o #4986 "redundante"** medindo o gate no meu checkout (`ba23ffab`) enquanto o main já tinha andado. E o `exit=$?` que eu li era do **`tail`**, não do `node` (pipe). Refeito em worktree limpa em `origin/main` e sem pipe, o veredito **se inverteu**: o gate estava vermelho, e o número certo era 568, não 566. O #4986 não era redundante — era **defasado**.
2. **Quase usei o #4986 como controle** para afirmar que as lanes `Ponto`/`KB`/`Compras` passam quando executadas. Elas passavam **por `skip-as-pass`** do `paths-filter` (40s, nem rodaram). Peguei pela **duração** do job antes de escrever a conclusão errada.
3. **Deduzi** que merge do #4974 pós-revert não ressuscitaria o Blade. A dedução estava certa, mas só virou fato depois de **simular** o merge (`git merge --no-commit`) e olhar os dois arquivos.

## Dívida deixada, nomeada

`KB` e `Ponto` seguem **vermelhas e não diagnosticadas** no #4974, mergeado por decisão de [W] com o estado do CI na mesa. Essas lanes só rodam quando arquivos de teste mudam — ficam invisíveis até o próximo PR que toque teste. Podem ser fallout do tenant, que é o sinal previsto pelo próprio PR.

> **↓ Diagnosticadas depois, no check-in agendado — ver §"Pós-merge" no fim deste log.** A hipótese "fallout do tenant" **caiu para as duas**.

Pendência de [W], não minha: o fix do Produto só re-landa com **aprovação explícita do diff do Blade + smoke em biz=1 antes do merge**.

## Regra que fica

Não tocar `.blade.php`, Controller ou tela sem [W] aprovar o diff **antes**. PR que traga isso junto: parar e mostrar, mesmo com o resto impecável.

---

## Pós-merge — o que o check-in agendado mediu (2026-07-29, 11:44 UTC)

A dívida acima ficou registrada como *"podem ser fallout do tenant"*. **Medido, a hipótese caiu nas duas.**

### `KB` — não era o tenant, e já está verde

Runs de `kb-pest.yml` na branch `main`: vermelha em `b46597d0`, `80dd82bf`, `6fe585b9` — e **verde em `0ec1a92e`**, que é justamente o merge do tenant. A falha vista no #4974 vinha da **base velha da branch**, não da mudança. Nada a fazer; não reabrir.

### `Ponto` — vermelha, e a causa é concreta

`11 failed / 18 passed` (run `30414793692`). Três falhas têm a mesma assinatura, literal no log:

```
FK ponto_importacoes_business_id_foreign        → values (99, AFD, SDD-BH-IMP-CONTRATO-alheio-show...)
FK ponto_colaborador_config_business_id_foreign → values (99, 23, SDD-ESPELHO-CONTRATO-ALHEIO-...)
FK ponto_colaborador_config_business_id_foreign → values (99, 25, SDD-ESPELHO-CONTRATO-ALHEIO-...)
```

Os testes inserem com `business_id = 99` — o "business **alheio**" do cenário cross-tenant — e **nada cria esse business**. A FK estoura.

Isso encaixa na peça que esta sessão mexeu: a versão original do #4974 fazia o seed criar o **99** e teria feito as 3 passarem **por efeito colateral**. Ao mover o tenant principal para 98 (para não colidir com o `SUPPORT_CLIENT_TENANT_ID`), o 99 voltou a não existir nessa lane.

**Conserto canônico** (não aplicado — não autorizado): chamar `seededSupportClientTenant()`, o helper que já cria o 99 na ordem correta (`user → business → backfill`).

As demais falhas da lane são de outra natureza — a principal é o **bug de compliance CLT declarado** (`EspelhoContratoTest`: apuração em `DIVERGENCIA` não contada; a Blade contava por `estado`, o React lê `tem_divergencia`, que não existe — `SDD §9 D-1`), failing-first por desenho do `fab0d5ec`.

⚠️ **NÃO verificado:** se essas 3 FKs-no-99 são as mesmas de antes da troca de tenant. `Ponto` já estava vermelha em 27/07 e 28/07 — o histórico de runs mostra isso — mas os **logs daquelas datas não foram lidos**. Segue como **provável**, não provado.

### Regressão de required no main: nenhuma

30 runs completos em `main`, filtrados por `conclusion=failure`, com o nome do job cruzado contra os 34 contexts de `governance/required-checks-baseline.json`. **Uma** falha, e **não-required**: `event: schedule` — o único job 0216 na lista de required é o *PR scan*, e o que falhou foi o *daily health-check*.

### Achado colateral — `Secrets audit --auto-pr` detecta e não entrega

O job que falhou tem defeito próprio, da família "mede e não reporta":

```
[secrets:audit] 2 drift(s) detectado(s)
Switched to a new branch 'chore/secrets-drift-2026-07-29-124048'
nothing to commit, working tree clean
exit code 1
```

Detecta o drift, abre a branch, **não tem o que commitar e morre** — o drift nunca chega a um PR. Não corrigido (não autorizado, não é required).

Dentro do relatório dele, item **pré-existente e de [W]**: a *Meilisearch master key* marcada 🔴 **COMPROMETIDA desde 2026-05-28** (está no histórico do git, append-only, não removível), aguardando **rotação**.

### Nota de ferramenta

O `send_later` (MCP `claude-code-remote`) **desapareceu no meio da sessão** — servidor desconectado. O check-in seguinte foi armado por `CronCreate`, que é **session-only**: morre quando a sessão encerrar, ao contrário do anterior, que era durável.
