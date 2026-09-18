# Errata aos 12 playbooks do `cowork-inbox` — as `prova:` citam endereços pré-ADR 0397

> **De:** Claude Code → **Para:** Cowork · **Data:** 2026-09-17
> **O que é:** os `00-INDICE.md` desceram **fiéis** — nada do corpo foi editado aqui
> (append-only, mesma doutrina da errata do lote Governança de 2026-09-08). Esta errata registra
> um defeito de **dado** que a máquina encontrou, o conserto que coube ao lado de cá, e o que
> só a FONTE pode corrigir. A correção no playbook é decisão [W].

> ⚠️ **ERRATA DO PRÓPRIO AUTOR — 2026-09-17, mesmo dia:** a §3 abaixo devolvia **dois** itens a
> [W] como "decisão sua". **Os dois eram determináveis por medição, e a devolução foi indevida**
> ([LC-28](../../LICOES_CODE.md)). O texto da §3 fica como está — o que ele afirma sobre os blobs
> é verdade —, mas o veredito dele está **substituído pela §7**, no fim deste documento. Quem ler
> a §3 e parar ali vai achar que há decisão pendente onde não há.

---

## 1 · O defeito, medido

Os playbooks chegaram ao repo pelo import da árvore da conta ([#7256](https://github.com/wagnerra23/oimpresso.com/pull/7256) 14/09,
[#7422](https://github.com/wagnerra23/oimpresso.com/pull/7422) e [#7445](https://github.com/wagnerra23/oimpresso.com/pull/7445) 16/09)
carregando `prova.path` do mundo **anterior à [ADR 0397](../../decisions/0397-prototipo-minimo-por-dono-e-ds-direto.md)**
(#7224, 2026-09-11), que reorganizou `prototipo-ui/` inteiro. Medido em 2026-09-17 nos 12 índices vivos:
**18 paths distintos** apontam para endereços que aquela ADR aposentou.

O placar da lista (`scripts/qa/placar-indice.mjs`, PR-A8) respondia **"arquivo ausente"** para todos —
que o leitor entende como *"a thread não entregou"*. O caso que expõe o erro é `Fiscal/03`
("Aferição read-only (FEITA)"): o `_saida-03.md` dela **existe**, com 5.748 B, só que em
`cowork/Wagner/cowork-inbox/`, e não no `design-docs/` que a prova cita. A thread estava entregue
e o placar a contava como pendente.

Efeito agregado: `entregue 0 de 61` nos 12 módulos — um placar que **não podia sair de zero**.

## 2 · O que a FONTE precisa corrigir — 17 paths com sucessor claro

Cada linha sai de um item da própria ADR 0397, não de palpite. O destino foi verificado por
existência, nunca por basename (a **D6** proíbe: *"heurística de basename não pode trocar dono,
subdiretório ou âncora"*).

| citado no playbook | endereço vigente | item | threads |
|---|---|---|---|
| `prototipo-ui/ancora.mjs` | `scripts/design/ancora.mjs` | D3 máquinas | ancora/01, 02, 03 |
| `prototipo-ui/contrato/<x>.contract.json` (9) | `governance/design/contracts/<x>.contract.json` | D3 contratos | Compras/01, Fiscal/01, Hrm/02, Hrm/03, Hrm/05, Ponto/06, Ponto/07, Sidebar/06 |
| `prototipo-ui/cowork/<x>.jsx` (5) | `prototipo-ui/cowork/Wagner/<x>.jsx` | D2 dono no endereço | ancora/01, Hrm/01, Ponto/10, Sidebar/01, 02, 03 |
| `prototipo-ui/design-docs/cowork-inbox/…` (2) | `prototipo-ui/cowork/Wagner/cowork-inbox/…` | D5 cowork-inbox | Fiscal/03, Ponto/11 |

## 3 · O que **não** dá para corrigir daqui — 1 path sem sucessor, e 1 referência que nunca existiu

**(a) `prototipo-ui/design-docs/contrato-cowork/governance.contract.json`** (thread `Governanca/01`).
A D5 aposentou `design-docs/`, mas só o `cowork-inbox/` tem sucessor fixado pela ADR. O candidato
óbvio pelo nome — `cowork-inbox/governance/governance.contract.json` — **não é o mesmo arquivo**:
blob `144d03c5` (o removido) contra `156ff30a` (o de hoje), bytes diferentes. Casá-los pelo basename
seria exatamente a heurística que a D6 proíbe. Além disso, a thread pede *"descer o contrato pra
`contrato-cowork`"*, e esse destino não existe mais. **Decisão [W]:** qual é o endereço vigente do
alvo dessa thread.

**(b) `memory/decisions/0180-sidebar-contrato-v2.md`** (thread `Sidebar/05`). Não é endereço
aposentado — é um slug que **nunca existiu**. As ADRs 0180 reais são
`0180-sidebar-v3-5-grupos-ghosts-header.md` e `0180-drift-numero-adr-0178-conflito-paralelo.md`.
A prova precisa citar uma delas, ou a thread declarar outro alvo.

## 4 · Um falso VERDE que o mesmo defeito escondia — `Ponto/11`

A prova `{"tipo":"ausente","path":"prototipo-ui/design-docs/cowork-inbox/ponto-dashboard/Index.casos.md"}`
pede que um resíduo **não** exista. Apontada para o endereço aposentado, ela passava **trivialmente**
(lá nada existe). No endereço vigente o arquivo **existe**, com 7.817 B — a limpeza que a thread pede
não foi feita, e o placar dizia que sim. O defeito de endereço errava nas duas direções: acusava o
inocente em 17 provas e absolvia em 1.

## 5 · O que o lado Code fez — e por que **não** editou o playbook

O conserto foi no **consumidor** (`scripts/qa/placar-indice.mjs`), não no espelho:

- provas em endereço aposentado **com** sucessor fixado pela ADR são medidas no endereço vigente,
  e o relato **diz** que migrou (a dívida da fonte fica visível, não é varrida);
- endereço aposentado **sem** sucessor sai **NÃO MEDIDA** — não fecha e não morde o `--check`
  (acusar por falta de endereço seria LC-33);
- endereço vivo passa intacto, com controle negativo no bite-test.

**Por que não editar o `00-INDICE.md` daqui:** `prototipo-ui/cowork/Wagner/**` é espelho de leitura
([ADR 0374](../../decisions/0374-emenda-0315-espelho-cowork-e-rota-prevista.md)) e o import sincroniza
com `robocopy … /S /PURGE` (`scripts/design/importar-bundle.mjs`), cujo próprio docblock diz que *"o
SSOT é ESPELHO do último handoff, não união"*. Uma edição nossa seria desfeita no próximo pacote de
árvore completa — e os três últimos vieram em 3 dias. O durável nasce na fonte e desce.

Enquanto a fonte não corrige, o placar continua honesto: mede no endereço certo **e** reporta
`fonte desatualizada: N prova(s)`, para que esta dívida não fique muda.

## 6 · Recibos

```
node scripts/qa/placar.mjs --todos          # antes: 0 de 61 · depois: 1 de 61 + 29 migradas
node scripts/qa/placar-indice.test.mjs      # 56 casos, inclui os controles negativos
git rev-parse 4f51a9ec781^:prototipo-ui/design-docs/contrato-cowork/governance.contract.json
git rev-parse HEAD:prototipo-ui/cowork/Wagner/cowork-inbox/governance/governance.contract.json
```

---

## 7 · Os dois "casos [W]" da §3 — medidos e RESOLVIDOS (mesmo dia)

[W] devolveu o pedido com uma palavra: *"Conserte"*. Estava certo — nenhum dos dois era decisão
dele. Os dois se resolvem medindo, e o que faltou foi eu **procurar direito** antes de escalar.

### 7.1 · `design-docs/contrato-cowork/governance.contract.json` — havia sucessor, sim

A §3 concluiu "sem sucessor" porque o único candidato que eu olhei
(`cowork-inbox/governance/governance.contract.json`) tem blob diferente. **O erro foi parar num
arquivo só.** Aquele diretório tinha **4** arquivos antes de `4f51a9ec781`, e os outros três estão
hoje em `prototipo-ui/cowork/Wagner/contrato/` com o **mesmo blob**:

| arquivo | blob antes | blob no estágio | |
|---|---|---|---|
| `configuracoes.contract.json` | `19f8caf2` | `19f8caf2` | idêntico |
| `patrimonio.contract.json` | `0d7556b7` | `0d7556b7` | idêntico |
| `venda-menu.contract.json` | `e103d2a7` | `e103d2a7` | idêntico |
| `governance.contract.json` | `144d03c5` | — | **não desceu** |

O estágio de contratos do Cowork migrou para sob o dono (D5 + D2), e isso está **provado por
blob**, não por basename. O `governance.contract.json` é o único que ficou para trás — que é
**exatamente o que a thread `Governanca/01` pede e ainda não entregou**. Não era ambiguidade:
era a thread fazendo o trabalho dela.

O placar ganhou a regra e agora reporta o caso como ausente legítimo, no endereço vigente:

```
01 [proximo] Descer o contrato de governanca pra contrato-cowork …
   — prototipo-ui/cowork/Wagner/contrato/governance.contract.json (arquivo ausente)
```

**Regra que fica:** procurar sucessor é olhar o **diretório inteiro**, nunca um arquivo só — um
arquivo que não migrou não refuta a migração dos outros.

### 7.2 · `0180-sidebar-contrato-v2.md` — é a `0180-sidebar-v3-5-grupos-ghosts-header.md`

A thread `Sidebar/05` é *"Ghosts × ADR 0180 — emenda ou reversão"*. Das duas ADRs 0180 reais,
uma menciona "ghost" **53 vezes** e a outra **zero**:

```
memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md        53
memory/decisions/0180-drift-numero-adr-0178-conflito-paralelo.md   0
```

Não há escolha a fazer. O slug `0180-sidebar-contrato-v2` nunca existiu; o alvo da thread é a
primeira. **A fonte precisa trocar o slug** — isto continua sendo edição de playbook, e playbook
é do Cowork. O que muda é que não há mais pergunta pendurada: a resposta é esta.

### 7.3 · O que ainda é da fonte, e o que não é

Segue com o Cowork **a edição** dos `00-INDICE.md` (os 17 paths da §2 + o slug da §7.2) — pelo
motivo da §5, não por indecisão. **Não** segue com [W] nenhuma decisão: as duas que a §3
levantava estão respondidas acima.
