---
date: "2026-09-13"
topic: Thread 03a do playbook Governança — Policies ganha casos.md, e o contrato que a âncora mandava usar não existe
authors: [C]
us: [US-GOV-002]
related_adrs: [0264-governanca-executavel-trio-dominio-e2e]
---

# Governança · thread 03a — `Policies.casos.md`

## TL;DR

`governance/Policies` ganhou `casos.md` com **9 UCs** derivados do GAP-SPEC canon e do protótipo — o
módulo sai de 1 para 2 `casos.md` em 9 telas. O contrato JSON que a âncora da thread mandava usar
**não existe** (a errata do Code de 08/09 já previa; a pasta saiu na #7224), então a copy veio do
protótipo, que é o dono da forma. Nenhum UC nasceu `✅`: a lane que roda os testes não emite JUnit,
então o manifesto do G-7 não os carimba — o teto honesto é `🧪`, declarado no cabeçalho. Dois
perdedores foram corrigidos no mesmo PR (charter prometendo dois Pest inexistentes; um `test.fixme`
com motivo caducado), e quatro achados ficaram **declarados e não consertados**, com o motivo de cada
um. casos-gate verde nos 4 modos, débito −9.

Execução da thread **03a** do playbook `governance` (Cowork→Code). Objetivo declarado: abrir a frente
do trio pela menor tela do módulo, provando o **formato** que as outras 7 seguem, uma por PR.

## O que mudou no terreno desde que o playbook foi escrito (medido, não recordado)

O playbook é de **2026-09-08** e a base dele caducou em três pontos. Os três foram medidos contra
`origin/main` fresco antes da primeira linha escrita.

| o playbook diz | medido em 2026-09-13 | consequência |
|---|---|---|
| âncora de copy = `governance-cockpit.contract.json` | **não existe** — `governance/design/contracts/` tem 38 contratos, **zero** de governança | a copy saiu do protótipo, que é o dono da forma |
| `Policies.tsx` = 4.889 B | **8.184 B** (#7089, 2026-09-09) | a tela ganhou busca local + aviso sem-rastro; os UCs cobrem a tela viva, não a de 08/09 |
| `casos.md` 1 de 9 no módulo | idem (só `DsRollout`) | segue sendo a maior lacuna de máquina do módulo — agora 2 de 9 |

A errata do Code de 08/09 (§6) já previa a primeira: a thread 01 retargeou o contrato pra uma pasta
de estágio e a 03a ficou apontando pro caminho antigo. A pasta de estágio saiu na #7224, então o
arquivo **nunca vai existir** naquele endereço. A fonte de copy usada foi
`prototipo-ui/cowork/Wagner/governance-page.jsx` (faixa `:296-372`), que o GAP-SPEC canon
`governance-policies-gap.md` já havia mapeado item a item — ou seja, a âncora não sumiu, mudou de dono.

## Os 9 UCs, e por que 9 e não 7

O playbook lista 7 invariantes a cobrir. Dois se somaram porque a tela mudou depois dele: a **busca
local** (UC-GPOL-08) e o **vazio de catálogo** (UC-GPOL-09) — ambos decididos por escrito no GAP-SPEC
de 2026-09-09, ambos com teste que morde. Cobri-los era a opção honesta; ignorá-los deixaria contrato
defasado no dia em que nasceu.

Nenhum UC nasceu `✅`. O G-7 deriva o verde do manifesto `scripts/casos-test-results.json`, e o
manifesto só carimba UC colhido de JUnit — a lane `governance-filtros-gate.yml` **não emite
`--reporter=junit` nem sobe artifact**, então o veredito dela não chega ao painel. O teto honesto é
`🧪`. Isso está declarado no cabeçalho do `casos.md`, não escondido.

## Dois perdedores corrigidos no mesmo PR (regra de precedência)

1. **O charter prometia dois testes que não existem.** `tests/Feature/Governance/PoliciesToggleTest.php`
   e `.../ActionGateTest.php` — varredura por nome no repo inteiro devolveu zero, e o único hit de
   "ActionGate" é o middleware. Promessa de Pest GUARD inexistente é instrução ativa pra regressão:
   quem lê acha que a tela está defendida onde ela não está. Bloco revogado com data, substituído
   pelos três testes que de fato mordem.
2. **Um `test.fixme` do e2e afirmava um motivo que caducou.** Ele dizia, em presente, que o aviso
   "não deixa rastro" era pendente "porque a copy NÃO EXISTE" — verdade em 08/09, falsa desde 09/09.
   O motivo foi reescrito: o que mantém o fixme hoje é a ausência de seed, não a de copy. O fato
   datado de 08/09 ficou preservado.

## Achados que NÃO consertei, e por quê

- **SPEC × rota divergem na permissão de leitura.** A tabela §Permissões do `SPEC.md` dá a listagem a
  `governance.policies.view`; a rota usa `can:governance.dashboard.view`. Pela precedência o teste e a
  rota ganham — mas trocar a permission da rota revogaria acesso em silêncio, então alinhar é decisão
  [W], não conserto silencioso. Registrado dentro do UC-GPOL-06.
- **UC-GPOL-07 (throttle) não tem oráculo.** O docblock do e2e afirma que o `GovernanceRotasCanGateTest`
  prova `can:` **e** `throttle:10,1`. Medido: `grep -c throttle` naquele arquivo = **0**. O UC nasce
  `⬜` com o motivo escrito, e o lugar barato de fechá-lo fica nomeado.
- **UC-GPOL-05 (sem bulk toggle) não tem teste nenhum.** O playbook antecipou este caso e mandou marcar
  BACKLOG sem escrever teste novo. O id ficou **citado em comentário** no spec e2e — ausência declarada,
  para a lacuna aparecer na fila em vez de sumir.
- **`Modules/Governance/` é `nao_toca` da thread.** Por isso o id do UC-GPOL-06 não foi ancorado no
  título do Pest que de fato o prova. Consequência honesta e declarada: o UC fica `⛓` — provado, mas
  inalcançável pelo G-7 até alguém ancorar lá.

## Recibos

| porta | comando | resultado |
|---|---|---|
| casos-gate (os **4** modos que o job roda) | `--selftest-diff-aware` · `--report` · sem-arg · `--check-baseline-shrink` | **exit 0** nos quatro · débito −9 vs baseline (era −8) |
| mordida do gate | — | antes do conserto ele **reprovou** com `🆕 uc-orphan:…#UC-GPOL-05` — o gate mordeu onde devia |
| vitest ancorado | `npx vitest run tests/js/governance-filtros.test.tsx` | **16 passed** (sem alterar assert nenhum) |
| e2e (sintaxe/coleta) | `npx playwright test --list e2e/governance-policies.spec.ts` | 5 testes listados com UC-id |
| charter vs schema (gate required) | `validate.mjs --schema charter.schema.json` | **OK** |
| formato de UC-id | `uc-id-lint.mjs` | 0 fora do formato |
| UC citando teste fora de lane | `uc-lane-coverage.mjs --check --baseline` | **exit 0** — nenhuma citação órfã de lane |

Pest não rodou aqui por regra: teste PHP é CT 100, nunca local.

## O endereço do recibo do playbook está em aberto — decisão [W]

O playbook manda escrever `_saida-03a.md` em
`prototipo-ui/design-docs/cowork-inbox/governance/playbook/`. Essa árvore **foi removida** do `main`
pela #7224 (ADR 0397 D5), e o `cowork-ssot-guard` R3 impede recriá-la. Enquanto não houver endereço
canônico para o retorno de thread, o recibo vive no corpo do PR e neste log. Quem for definir o novo
endereço decide por todas as threads seguintes, não só por esta.

## Lição de formato para as 7 telas restantes

A ordem barata é: **medir o terreno antes de ler o playbook como verdade** — três das premissas dele
tinham caducado em cinco dias, e duas delas mudariam o conteúdo do contrato. Depois, ancorar os UCs
nos testes **que já existem** (título, não docblock, quando o arquivo não for `nao_toca`) antes de
rodar o gate: o `uc-orphan` é a violação que aparece, e ela se resolve com citação honesta, nunca com
teste novo nem com `--write-baseline`.
