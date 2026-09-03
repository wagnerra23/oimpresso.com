---
owner: W
last_validated: "2026-08-28"
slug: ponto-runbook-importacoes
title: "Ponto — Runbook das Importações AFD (/ponto/importacoes · Importacoes/Index + Create + Show)"
type: runbook
module: Ponto
tela: Ponto/Importacoes/Index
status: ativo
date: 2026-08-28
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
---

# RUNBOOK — Importações AFD (`Ponto/Importacoes/{Index,Create,Show}`)

> **Este RUNBOOK é RETROATIVO, e isso importa para ler o resto.** As três Pages já estão em
> Inertia/React desde antes deste documento — o F3 do MWART não está pendente, está *feito*.
> Ele nasce porque o hook `block-mwart-violation` ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) §F1)
> barra `Edit` em `Pages/Ponto/Importacoes/*.tsx` enquanto não existir `RUNBOOK-importacoes.md`
> aqui, e o bloqueio **não tem escape**.
>
> Conteúdo **derivado** do SDD, do controller e do `.tsx`. Onde algo não foi medido, está dito
> que não foi. Anti-padrão inventado em RUNBOOK é pior que ausente: parece canon.

---

## 1. O que é a tela

Entrada dos arquivos **AFD/AFDT** gerados pelo REP-A homologado INMETRO. O módulo **importa, não
substitui** o relógio (Non-Goal declarado, SDD §6.6). O arquivo original é **retido como prova de
origem** para auditoria.

| Rota | Método | Controller |
|---|---|---|
| `GET /ponto/importacoes` | `index` | `ImportacaoController@index` |
| `GET /ponto/importacoes/novo` | `create` | idem |
| `POST /ponto/importacoes` | `store` | idem (`ImportacaoAfdRequest`) |
| `GET /ponto/importacoes/{id}` | `show` | idem |
| `GET /ponto/importacoes/{id}/original` | `baixarOriginal` | idem → `Storage::download` |

Títulos: **"Importações AFD · Portaria MTP 671/2021"** (`Index.tsx:65`), **"Nova importação ·
AFD/AFDT"** (`Create.tsx:53`), **"Importação #{id} · AFD"** (`Show.tsx:61`).

**Os 5 passos do `store()`** (medidos no controller, e é a espinha do fluxo — SDD §5.3 F7):

1. `hash_file('sha256')` do upload;
2. **dedup por hash dentro do business** → se já existe, volta com erro citando data + id;
3. `store()` no disco `local` sob `ponto/importacoes/{businessId}` — **storage segregado por tenant**;
4. `Importacao::create(...)`;
5. `ProcessarImportacaoAfdJob::dispatch($businessId, $importacao->id)` — **`$businessId` no
   construtor**, porque fila não tem `session()`
   ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).

O passo 5 é o que faz o processamento ser **assíncrono**: a tela `Show` acompanha o estado, ela não
processa.

---

## 2. Fontes canônicas (ordem de precedência)

Vale a regra-mestre de [proibicoes.md](../../proibicoes.md):
**teste verde citando o UC > `.casos.md` > `.charter.md` > `SPEC.md`**.

| Ordem | Fonte | Papel |
|---|---|---|
| 1 | [`SDD-espelho-e-jornada-v1.0.md`](SDD-espelho-e-jornada-v1.0.md) §5.3 **F7** e §6.4 **CU-PONTO-10/11** | fluxo e casos de uso |
| 2 | `Importacoes/{Index,Create,Show}.casos.md` | contrato executável (UC) |
| 3 | `Importacoes/{Index,Create,Show}.charter.md` — **`status: draft`** | intenção-lei, não ratificada |
| 4 | [`SPEC.md`](SPEC.md) US-PONTO-002 (AFD, idempotência) · US-PONTO-012 | escopo |
| 5 | Lei: **Portaria MTP 671/2021 Anexo I** (rastreabilidade) | contrato de domínio |

---

## 3. A cicatriz que este fluxo carrega — leia antes de mexer

`CU-PONTO-11` está marcado **❌ quebrado** no SDD §6.4, e o conserto está nos comentários do
controller (US-PONTO-012, SDD §9 D-8): o payload lia `linhas_criadas` / `linhas_ignoradas` /
`erro_mensagem`, que **não são coluna nem accessor**. As colunas reais são **`linhas_sucesso`**,
**`linhas_erro`** e **`log`**. O `?? 0` escondia a ausência.

Consequência que durou: **"0 marcações criadas" em TODA importação**, inclusive as 100%
bem-sucedidas — e importação que falhava **não dizia por quê**, porque o
`{i.erro_mensagem && <Alert>}` do `Show.tsx` nunca renderizava.

**As chaves do payload seguem curtas de propósito** (o `.tsx` as consome); o que mudou foi a
*leitura*. Não "padronize" isso renomeando de um lado só.

---

## 4. Estado MEDIDO em 2026-08-28

| Item | Estado |
|---|---|
| As três Pages em Inertia/React sobre `AppShellV2` | ✅ |
| Charters | ✅ existem — os três **`status: draft`** |
| `casos.md` das três | ✅ existem |
| Scorecards | ✅ `ponto-importacoes-{index,create,show}.yaml` |
| Cor crua no `.tsx` | 1 por arquivo (`Index:65`, `Create:53`, `Show:61`), todas `text-stone-400` (padrão do `os-page-h`) |
| `HasBusinessScope` em `Importacao` | ✅ aplicado (`Importacao.php:18`) |

Cobertura **não é restateada aqui** — rode `npm run screen-coverage:report` e `npm run casos:report`.

---

## 5. Verificação

```bash
node .claude/hooks/block-mwart-violation.mjs   # path no stdin: rc=0 após este RUNBOOK
node scripts/memory-schemas/validate.mjs memory/requisitos/Ponto/RUNBOOK-importacoes.md
npm run typecheck:baseline:check               # delta deve ser +0
npm run casos:check
```

Pest e PHPStan **não** rodam local nem no Hostinger — CT 100, sempre
([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## 6. Não fazer

- ❌ **Não remover a dedup por hash.** É a âncora de `CU-PONTO-10` ("importar 2× não duplica
  marcação"). O escopo dela é **por business** de propósito: o mesmo arquivo pode ser importado por
  outro empregador sem colisão.
- ❌ **Não apagar o arquivo original** após processar. Ele é prova de origem sob Portaria 671/2021
  Anexo I, e o `hash_arquivo` exibido só vale se o original existir para conferência.
- ❌ **Não tirar `$businessId` do construtor do Job.** Fila não tem `session()` — sem ele o job
  resolve o tenant errado ou nenhum ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- ❌ **Não processar de forma síncrona no request** para "simplificar" — AFD de mês inteiro é
  arquivo grande, e o desenho assíncrono é o que sustenta a tela `Show`.
- ❌ **Não tratar o módulo como substituto do REP-A homologado** — Non-Goal declarado (SDD §6.6).
- ❌ **Não promover os charters a `status: live`** — depende de [W] aprovar Non-Goals + Anti-hooks.
