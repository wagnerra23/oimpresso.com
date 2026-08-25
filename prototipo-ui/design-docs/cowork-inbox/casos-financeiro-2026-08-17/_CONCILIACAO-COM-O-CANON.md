# Conciliação desta pasta com o canon vivo — 2026-08-21

> **Leia antes de copiar qualquer coisa daqui para `resources/js/Pages/Financeiro/`.**
> Esta pasta é **espelho fiel** do projeto Cowork, congelada em 2026-08-17. Os 14 arquivos
> ao lado **não são canon** e, em alguns pontos, contradizem o canon — de propósito, porque
> espelho que "corrige" a fonte deixa de ser espelho.
>
> O canon é `resources/js/Pages/Financeiro/**/*.casos.md`. Ele é **melhor**, e este arquivo
> mostra a medição que sustenta a frase.

## Por que este arquivo existe

[W] em 2026-08-21: *"descer por cima é regressão, concilie"*. Conciliar aqui **não** é
mesclar os dois textos — é (a) descer o espelho fiel, (b) medir o delta contra o canon e
(c) deixar registrado o que **não** se deve reverter. Sem (c), a próxima sessão lê o espelho,
vê 17 `UC-*` que "sumiram" do canon e os restaura — reintroduzindo exatamente o defeito que
o canon já tinha corrigido.

## O que foi medido (não estimado)

14 telas, comparação id-a-id entre espelho e canon:

| Eixo | Resultado |
|---|---|
| UC iguais em id **e** status | **45** |
| UC só no espelho Cowork | **17** |
| └ desses, **sobrevivem no canon** como `[BACKLOG]` | **17** |
| └ **realmente perdidos** | **0** |
| UC só no canon | **0** |
| UC com status divergente | **1** |

### Os 17 não sumiram — foram rebaixados, e isso é a regra

O canon manteve **todo** o comportamento descrito aqui, mas moveu de `## UC-*` para
`## [BACKLOG]` tudo que **não tem prova**. Isso é o G-2 da [ADR 0264]: `UC-*` órfão quebra o
`casos-gate`. Exemplo literal, `AssinaturaAtualizar.casos.md`:

| Espelho Cowork (aqui) | Canon (`resources/js/Pages/Financeiro/`) |
|---|---|
| `## UC-ASS-03 — Sem diff real, sem PATCH`<br>`Status: 🧪 (AssinaturaAtualizarGuardTest — payload sem mudança é bloqueado)` | `## [BACKLOG] Sem diff real, sem PATCH`<br>`Status: ⬜ sem prova — nenhum teste exercita PATCH com payload sem mudança.` |

O espelho **afirma uma prova que não existe**: o `AssinaturaAtualizarGuardTest` tem 2 testes
(render e guest), nenhum exercita PATCH vazio. Promover esses 17 de volta a `UC-*` seria
reinstalar 17 afirmações não provadas numa tela de **cobrança de cliente real**.

### A única divergência real — e é o espelho que erra

`Configuracoes.Contador` · `UC-CTD-05` (CNPJ mascarado):

- espelho: `⬜ (sem prova — front usa advisor_cnpj_mascarado do backend)`
- canon: `🧪 (test_advisor_cnpj_masked_protege_pii)`

**Conferido no repo:** o teste existe — `Modules/Financeiro/Tests/Feature/Advisor/Onda31AdvisorPortalTest.php:76`.
Aqui o espelho **subestima** e o canon está certo. Nada a fazer no canon.

## Veredito

**Nada a portar do espelho para o canon.** O canon é superconjunto em correção: mesmo
conteúdo, status honesto, e o motivo escrito em cada rebaixamento. Os arquivos ao lado ficam
como registro datado do que o Cowork afirmava em 2026-08-17.

## Como refazer esta medição

Ela é derivada, não escrita — se o canon andar, **re-rode** em vez de acreditar nesta tabela:

```bash
# UC id-a-id, espelho x canon, com os status divergentes nomeados
node scripts/design-sync/reconcilia-casos-financeiro.mjs
```

## Trilha do tempo

- 2026-08-21 · [C] conciliação medida e registrada, a pedido de [W]. Espelho descido fiel
  (12 arquivos nesta leva + 2 na anterior); canon **não tocado**.

[ADR 0264]: ../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
