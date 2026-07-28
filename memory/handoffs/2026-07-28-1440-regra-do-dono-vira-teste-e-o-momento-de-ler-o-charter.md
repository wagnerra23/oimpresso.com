# Handoff 2026-07-28 14:40 — a regra do dono virou teste, e o momento de ler o charter virou hook

> **Delta** do [handoff das 13:10](2026-07-28-1310-varredura-do-nao-salvo-porta-cega.md).
> Dois PRs: [#4948](https://github.com/wagnerra23/oimpresso.com/pull/4948) · [#4954](https://github.com/wagnerra23/oimpresso.com/pull/4954). Ambos MERGED.

## O corte do [W] — e por que ele importa mais que o código

Eu havia reportado, como 🔴, que `toggleAutoEmission` *"liga a emissão automática de
documento fiscal sem gate nenhum"*. [W] cortou, textual:

> *"As notas não podem sair automáticas em todos os clientes. Não é assim que funciona.
>  O cliente escolhe se quer emitir ou não. E **tem configuração por empresa** se isso é
>  automático."*

**Ele estava certo e o código estava certo.** Medido: `NfeBusinessConfig` é **por business**,
o `business_id` vem da **sessão** (não do request — sem risco cross-tenant), a ação recusa
se não houver tributação configurada e grava `activity()`. O toggle **grava a escolha da
empresa**; quem emite é o listener de venda finalizada.

**E o charter da tela JÁ DIZIA ISSO**, literal, em §Automation Anti-hooks:

> `❌ Não dispara Job de emissão quando toggleAutoEmission=true (Job é disparado por listener de venda finalizada)`

O SDD do módulo também estava correto (*"não é cross-tenant, é RBAC dentro do tenant"*).
**Fui eu que resumi sem ter lido o charter.** Classe LC-08.

## O que virou máquina

### 1. A regra estava escrita e INDEFESA → [#4948](https://github.com/wagnerra23/oimpresso.com/pull/4948)

O mesmo charter promete *"cada item vira Pest GUARD test"* — e este item **não tinha
guard**. `UC-NFTR-07` fecha: prova que ligar o toggle **grava** a escolha (pré-condição
anti-vácuo, porque o caso afirma uma AUSÊNCIA) e que **nenhum** `EmitirNfceJob`/
`EmitirNFSeJob` é despachado (`Bus::fake` + `assertNotDispatched`).

Agora, se alguém "otimizar" emitindo no toggle, **quebra**.

### 2. O momento de ler o charter → [#4954](https://github.com/wagnerra23/oimpresso.com/pull/4954)

[W]: *"não deveria ser sempre esse processo antes de tentar resolver algo?"* — deveria, e
já é canon ([how-trabalhar](../how-trabalhar.md) §ordem-de-fonte: doc canon ANTES do código).
Faltava o lembrete **no momento**.

**Medição que decidiu o desenho** (corpus: 448 sessões em `~/.claude/projects`):

| tool que tocou controller-com-charter, sem ter lido o charter | casos |
|---|---:|
| **`Read` puro** | **27 (59%)** |
| `Read`+`Edit` | 16 |
| `Glob`+`Read` | 2 |
| `Edit` puro | 1 (2%) |

Estender o `modulo-preflight-warning` (matcher `Write|Edit`) pegaria **17 de 46** e perderia
a maioria — o caso do incidente, que foi **leitura para diagnosticar**. Daí matcher `Read`.

**Precisão:**

| | |
|---|---:|
| leu o charter **ANTES** do controller | **0** ← em 448 sessões |
| leu **depois** (ia ler sozinho → redundante) | 18 |
| **nunca** leu | **46** |
| **precisão** | **71,9%** · ~1 disparo a cada 7 sessões |

⚠️ **Os 28% "redundantes" NÃO são a família dos guards mortos do §5.** O `toHaveKey`
(100% FP) acusava assert **correto** de defeito; aqui o pior caso é dizer algo que o agente
ia descobrir sozinho — 1 linha, sem veredito falso. Por isso nasce **advisory** (`exit 0`
sempre): bloquear um `Read` seria hostil.

**Âncora determinística:** a tela sai do `Inertia::render('Mod/Tela')` que o **próprio
controller declara** — 138 dos 140 resolvem charter. Nada de adivinhar por nome ou pasta
(§5 2026-06-30).

## A grade que o [W] pediu — onde somos fortes e fracos

Comparação do **processo de "ler o contrato antes de agir"** (denominador declarado; nota
por dimensão, porque somar heterogêneo esconde o buraco):

| Dimensão | oimpresso | spec-driven 2026 | Augment Cosmos |
|---|:--:|:--:|:--:|
| Contrato por unidade de trabalho | 9 | 8 | 8 |
| Non-Goals explícitos | 10 | 7 | 6 |
| Autoridade de quem preenche | 10 | 5 | 6 |
| Enforcement automático | 8 | 5 | 7 |
| **Momento — quando o agente é obrigado a ler** | **4** ⚠️ | 7 | **9** |
| Registro de refutação | 9 | 2 | 2 |

**Somos fortes onde se ESCREVE contrato e fracos onde se LÊ.** O `charter-first` dispara em
`Edit` de `.tsx`; eu estava diagnosticando um controller. O #4954 ataca exatamente a
dimensão 5. (A nota 4 é auto-avaliação desta sessão, não medição de corpus — declarado.)

## ⚠️ Limite honesto do que foi entregue

O hook cobre **"abriu o controller"**. NÃO cobre *"vai afirmar algo sobre a tela sem ter
aberto nada"* — esse predicado é **semântico**, e o §5 tem 4 lápides de guard sintático que
reprovava o legítimo. Não construí, e não deve ser construído sem FP medido antes.

## Estado no fechamento

- **14 PRs da sessão, todos MERGED** (#4904 #4905 #4906 #4911 #4913 #4914 #4918 #4927
  #4932 #4937 #4940 #4941 #4948 #4954)
- **SDD: 1 → 14 módulos** no main
- Worktree varrida arquivo a arquivo antes de arquivar: **44 idênticos ao main · 34 versões
  antigas · 1 derivado regenerável · 0 conteúdo único**. O `_STATUS-GENERATED.md` do
  NfeBrasil é o único ausente do main e é **melhor descartar**: foi gerado com o script
  ANTES do fix do namespace (o que enxergava "0 telas"). Regenera com
  `node scripts/governance/requisitos-status.mjs NfeBrasil --write`.

## Aberto — segue com [W]

**Assinatura com valor negociado nunca vira fatura** (RecurringBilling §9.1): o `store()`
casa plano por `ciclo` **e** `valor` exatos; sem match, `plan_id=null` e o gerador descarta
com **uma linha de log, sem alarme**. Três remédios que se anulam entre si.

> O outro item que eu vinha listando como 🔴 — `toggleAutoEmission` — **saiu da lista**:
> era caracterização minha errada, corrigida por [W] nesta sessão e agora defendida por
> `UC-NFTR-07`.
