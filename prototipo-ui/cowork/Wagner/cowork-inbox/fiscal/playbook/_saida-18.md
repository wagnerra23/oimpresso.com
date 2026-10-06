---
sessao: "18"
titulo: Apagar regra tributária exige a permissão fiscal (UC-NFRF-04)
autor: "[CL]"
data: 2026-10-06
base: wagnerra23/oimpresso.com@main a8e0624504 (pós-#8806)
prefixo_tocado: TributacaoController@destroy · DestroyRegraTributariaRequest (novo) · TributacaoGatesContratoTest (bloco do UC-NFRF-04 + 3 linhas do docblock) · RegraForm.casos.md · SDD §5.4.1 (nota datada) · este arquivo
---
# _saida-18 · Apagar regra tributária exige a permissão fiscal

**Resposta curta:** o `destroy` passou a exigir `nfe.tributacao.manage`, com o mesmo `authorize()` do store/update. O UC-NFRF-04 saiu de ❌. A prova vermelho→verde rodou no CT 100.

## 1 · Feito

| arquivo | mudança |
|---|---|
| `Modules/NfeBrasil/Http/Requests/DestroyRegraTributariaRequest.php` | novo FormRequest; `authorize()` = `can('nfe.tributacao.manage')`, `rules()` vazio |
| `Modules/NfeBrasil/Http/Controllers/TributacaoController.php` | `destroy(Request …)` → `destroy(DestroyRegraTributariaRequest …)`; o escopo por `business_id` + `firstOrFail` segue igual |
| `Modules/NfeBrasil/Tests/Feature/TributacaoGatesContratoTest.php` | UC-NFRF-04 ganhou controle positivo (com a permissão, a mesma rota apaga e `deleted_at` fica preenchido) |
| `resources/js/Pages/NfeBrasil/Tributacao/RegraForm.casos.md` | UC-NFRF-04 passa de ❌ para 🧪, com recibo do CT 100 |
| `memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md` §5.4.1 | nota datada; a tabela de 2026-07-28 fica como estava |

## 2 · Prova (CT 100, worktree isolado em `/tmp`, branch `0e263d01b`)

`--filter="UC-NFRF|UC-NFTR-04"` sobre `TributacaoGatesContratoTest` + `TributacaoIndexContratoTest`:

| rodada | resultado |
|---|---|
| controller do `main` | 4 passed · **1 failed** — UC-NFRF-04: *"Expected 403 but received 302"* · 30 assertions |
| branch | **5 passed** · 33 assertions |

Só o UC-NFRF-04 muda de estado. O UC-NFTR-04 (o `destroy` com permissão segue apagando, e o de outro business segue 404) ficou verde nas duas.

## 3 · Achado de ambiente (não é da thread, fica registrado)

No staging do CT 100 o usuário semeado do biz 1 tem `Admin#1,Admin#2`, e o `Gate::before` libera toda permissão de business para quem tem `Admin#{business}`. O `revokePermissionTo` do fixture "sem permissão" não tira nada, então o UC-NFRF-01, o UC-NFRF-04 e o UC-NFIM-03 caem vermelhos ali **com ou sem gate**. A thread 19 mediu o mesmo. A rodada acima tirou o papel do usuário 1 só durante os testes e o devolveu em seguida (conferido). No CI o seed (`pest-mysql-setup`) cria o usuário sem papel, então lá o fixture vale.

## 4 · Fica para depois (fora do prefixo)

- **O arquivo `TributacaoGatesContratoTest` entrou na allowlist do `nfebrasil-pest.yml` neste PR** (pedido da sessão da fila: sem lane o PR não prova o teste). O UC-NFIM-04 só fica verde com a thread 19 (#8827), então este PR mergeia **depois** dela; até lá a lane deste PR sai vermelha nesse caso. O filtro de caminhos da lane já cobria `Modules/NfeBrasil/**`.
- **`toggleAutoEmission` e `aplicarTemplate` seguem sem gate** (SDD §5.4.1). Pertencem à `Tributacao/Index`, não a esta thread. A thread 04 mexe no mesmo controller e vem depois desta (índice §2, rev.11).
- **Fixture do staging:** um usuário de teste sem papel admin tornaria o arquivo confiável no CT 100 sem mexer no papel do usuário 1. Toca o helper compartilhado com a thread 19, então não entrou aqui.
