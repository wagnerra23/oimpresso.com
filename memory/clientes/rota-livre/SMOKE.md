---
id: clientes-rota-livre-smoke
cliente: rota-livre
business_id: 4
lifecycle: ativo
---

# ROTA LIVRE (Larissa) — smoke de produção

> O que a Larissa usa vem de [`perfil.yml`](perfil.yml) (`modulos_uso`): vendas, cliente e
> financeiro (contas a receber). Loja de roupa, não gráfica: orçamento por m², ordem de produção
> e Jana/Copiloto **não** fazem parte do uso dela e não estão neste smoke.
>
> **biz=4 nunca é usado em smoke nem em teste** (R6, ADR 0358). O smoke abre as telas com a conta
> fictícia biz=99; o sinal diário só **conta** vendas do biz=4, sem valor e sem cliente.

## O que roda e onde

### 1. Telas em todo deploy — job `smoke-clientes`

- **Onde:** `.github/workflows/screen-smoke-after-merge.yml`, job `smoke-clientes`, depois de todo
  deploy bem-sucedido.
- **Rotas:** as marcadas com `"clientes": ["rota-livre"]` em `scripts/screen-smoke/routes.json`.
  Lista atual: `node -e "const c=require('./scripts/screen-smoke/routes.json');console.log(c.routes.filter(r=>(r.clientes||[]).includes('rota-livre')).map(r=>r.path).join('\n'))"`.
  Em 2026-11-15: `/sells`, `/sells/create`, `/cliente`, `/financeiro/contas-receber`,
  `/financeiro/unificado`, `/financeiro/caixa`.
- **Como mede:** Playwright em 1440 e 1280 (a Larissa usa monitor ~1280), erros de console, 4
  sinais de render e juízo da OpenAI vision. Só leitura garantida por máquina: depois do login,
  qualquer requisição que não seja GET/HEAD é abortada e a tela vira QUEBRADO.

### 2. Sinal diário — `sells:smoke-daily` (06:30, só produção)

- `checkClientesVivos`: `ALERT cliente=rota-livre` se o biz=4 não tiver **nenhuma venda final em
  5 dias**.
- **Por que 5 dias:** medido em 2026-11-15 em produção, contando dias com venda nos 365 dias
  anteriores: 332 dias com venda, maior lacuna sem venda = 3 dias. Com 5 dias, zero alarme falso
  no ano. Em férias ou fechamento prolongado, ajustar `SMOKE_ROTA_LIVRE_JANELA_DIAS`.
- O check antigo de 30 dias (`checkMultiTenantScope`) continua.

## O que é alerta

| Sinal | Onde | Significado |
|---|---|---|
| `ALERT smoke-clientes cliente=rota-livre … <rota>=QUEBRADO` | log do job, job vermelho | tela não renderizou depois do deploy: regressão |
| `… <rota>=NAO-EXERCIDA` | idem | o smoke não abriu a tela (login falhou). Falta de medição, não regressão |
| `ALERT cliente=rota-livre: biz=4 zero vendas em 5 dias` | `storage/logs`, comando FAILURE | a loja parou de vender pelo sistema: pode ser regressão (venda quebrada) ou a loja fechada |

Sem canal externo por decisão do [W] (2026-11-15).

## Se acusar regressão

Não tentar corrigir. Abrir o artifact `screen-smoke-clientes-<run>`, achar o deploy, comentar no
PR mergeado com o link e propor rollback ao [W].

## Rodar à mão

```bash
gh workflow run screen-smoke-after-merge.yml -f rota=__CLIENTES__
```

```bash
php artisan sells:smoke-daily
```

O segundo só avalia os clientes vivos em produção.

## Estado em 2026-11-15

O login da conta biz=99 falhava em produção, então nenhuma tela foi aberta (run 43777165357,
job vermelho com `NAO-EXERCIDA`). Detalhe e conserto em
[`../wr2-sistemas/SMOKE.md`](../wr2-sistemas/SMOKE.md#estado-em-2026-11-15).

PRs de origem: #10925, #10926, #10927 (label `affects/rota-livre`).
