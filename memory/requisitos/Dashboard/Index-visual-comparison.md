---
tela: Home/Index
rota: /dashboard-legacy
modulo: Dashboard
ancora: prototipo-ui/cowork/dash-legacy-page.jsx
ancora_frescor: "verificado 2026-08-27T21:56:54Z — MAS fidelidade da origem NAO PROVADA (ver §0)"
comparado_em: "2026-08-28"
metodo: sonda-DOM-identica-nos-dois-lados
status: divergente
---

# Inventário `Home/Index` × `dash-legacy-page.jsx`

## Como foi medido (e por que assim)

A **mesma sonda JS** foi injetada nos **dois renders**, e o veredito sai da medição, não do olho:

- **Âncora**: `prototipo-ui/cowork/` servido em `127.0.0.1:5599`, tela `dash-legacy`
  (o portão `cowork-mirror-freshness --preview-ds` passou: 10 dependências repostas, bundle OK).
- **Produção**: `oimpresso.com/dashboard-legacy?aba=venc-venda`, sessão real, commit `01bee7581e`.

Comparar por screenshot foi tentado e **descartado**: iframe de produção não carrega logado
(cookie `SameSite`), e o olho já falhou duas vezes nesta classe de tarefa — é a razão de o
`design-diff.mjs` existir.

## §0 — A cadeia de origem tem um elo fraco DECLARADO

O arquivo-âncora entrou no espelho por **transcrição**, não por download fiel:

| Elo | Fato | Recibo |
|---|---|---|
| Fonte | projeto Cowork `019dcfd3-…`, via `DesignSync.get_file` | — |
| Transporte | **TRANSCRIÇÃO** (nenhum arquivo persistiu no `get_file`) | commit `77f9a28ce7`, 2026-08-21, PR #6123 |
| Fidelidade | **"NAO PROVADA"** | `scripts/design-sync/transcribed-provenance.json` → `sha256 dcf4d23c…`, `parse_esbuild: OK`, `fidelidade: "NAO PROVADA"` |
| Frescor | verificado 2026-08-27T21:56Z | `node prototipo-ui/ancora.mjs Home/Index` |

**Frescor ≠ fidelidade.** Frescor diz "não mudou desde então"; fidelidade diz "é igual à fonte".
Toda divergência abaixo é contra um espelho cuja fidelidade a origem nunca provou.

## Inventário medido — camada a camada

| # | Camada | Âncora | Produção | Veredito |
|---|---|---|---|---|
| 1 | Título `h1` | "Visão geral" | "Visão geral" | ✅ |
| 2 | Painel Contrapartidas | presente | presente | ✅ |
| 3 | Gráfico Vendas por dia | presente | presente | ✅ |
| 4 | Gráfico Vendas por mês | presente | presente | ✅ |
| 5 | Colunas da grade | Nota · Cliente · Vencimento · Situação · Devido | idem | ✅ |
| 6 | **Ordenação de coluna** | **4 colunas com `↕`** (`sortable: true`) | **0** — todo `th` com `botao:0`, `cursor:auto` | ❌ **funcional some** |
| 7 | **Busca na grade** | **0** (não existe) | **1** (`input[placeholder*=Buscar]`) | ❌ **adição indevida — e MORTA: o controller não trata `?q=`** |
| 8 | **Painel Pendências** | presente (5 itens com badge) | **ausente** | ❌ |
| 9 | **Contagem por aba** | **9 abas com número** (7·5·12·4·6·3·2·8·9) | 5 abas, sem número | ❌ contagem · ⚠️ abas: ver nota |
| 10 | **Sparkline no KPI Líquido** | presente | ausente | ❌ (2 evidências: `grep spark` 1×0 + render) |
| 11 | **Rodapé da grade** ("N de M · clique para abrir") | presente | ausente | ❌ **é a única affordance do drawer** |
| 12 | **Alert de rodapé** | "Herança do Blade que não foi portada" | "Precisa dos gráficos de vendas…" | ❌ texto trocado **e factualmente falso** (os gráficos estão logo acima) |
| 13 | Tom semântico dos KPI | `success` (Vendas) · `warning` · `info` (Despesas) | só `warning` | ❌ 2 de 3 |
| 14 | Densidade/altura da tabela | `density="compact"` + `height={300}` | `shared/DataTable` **não tem** essas props | ⚠️ limite do componente canon |
| 15 | Coluna "Loja" em Lotes a vencer | não existe | **existe** | ⚠️ adição minha |
| 16 | "Setor solicitante" (Requisições) | "Setor solicitante" | "Solicitante" | ⚠️ copy encurtada |
| 17 | Simular falha / Papel simulado | presentes | ausentes | ✅ correto (instrumentos de protótipo) |
| 18 | Filtro de loja | "Todas as lojas" | ausente | ✅ correto (o negócio tem 1 loja; a UI exige > 1) |

**Nota sobre o #9 (abas):** as 4 abas a menos **não** são falta de implementação — 3 estão atrás
de setting do negócio (`enable_product_expiry`, `enable_purchase_order`,
`enable_purchase_requisition`, desligados neste tenant) e "Fluxo de caixa" não tem fonte no
Blade. O que falta de verdade ali é a **contagem**.

## O que isso soma

- **6 regressões de fidelidade** não declaradas: #6, #7, #10, #11, #12, #13
- **1 controle morto em produção**: a busca (#7) — o usuário digita e nada acontece
- **3 divergências menores**: #14, #15, #16
- **1 texto factualmente errado na tela**: #12

Os itens #6, #7, #11 e #12 são os que mudam o **comportamento** percebido, não só a aparência.

## Por que os gates não pegaram nada disso

O `visual-regression` e o `contrato-de-tela` passaram **verdes** no PR que entregou esta tela.
Eles não cobrem esta classe: o primeiro compara a tela **contra ela mesma** (baseline anterior),
não contra a âncora; o segundo confere copy e ordem declaradas no contrato — e esta tela **não
tem** `## Contrato visual` no charter, então não havia o que conferir.

A comparação contra a âncora **não tem gate**: ela depende de alguém rodar a sonda, e a sonda
depende de sessão autenticada em produção. É o buraco estrutural que este inventário expõe.
