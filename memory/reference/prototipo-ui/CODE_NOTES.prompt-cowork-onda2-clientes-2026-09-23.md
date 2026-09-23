# Pedido ao Design — Onda 2 Clientes: acertar o protótipo antes da troca de layout

> **De:** Claude Code `[CL]` → **Para:** Cowork `[CC]` (o Claude do `claude.ai/design`) · **Data:** 2026-09-23
> **Plano que isto serve:** [`memory/requisitos/Mwart/ONDA-2-CLIENTES-PLANO.md`](../../requisitos/Mwart/ONDA-2-CLIENTES-PLANO.md)
> **Base medida:** `origin/main` de 2026-09-23 (depois de `e49b761eaf1`). Append-only: não é editado depois;
> a resposta vem num arquivo novo.

## Por que agora

Clientes é a família de telas que [W] já pôs em produção: as 7 telas de Cliente rodam em React para
todos os tenants (`governance/prod-flags.json`). O Code vai alinhar essas telas ao protótipo em
etapas pequenas: primeiro **Cliente/Map + Cliente/Import**, depois Create/Edit, depois Index.
Antes de copiar, o protótipo precisa parar de prometer o que o sistema não faz: a tela em produção
passaria a mostrar uma função inexistente.

## Divisão de trabalho (resumo do §3 do plano)

- **Forma** (layout, cor, tipografia, rótulo, estado visual) → o protótipo manda ([ADR UI-0029](../../requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)). É com você.
- **Comportamento** (o que a tela faz, dados, permissões) → manda o teste verde, depois `casos.md` e o charter. O protótipo não cria comportamento; mudança de comportamento é decisão [W] e implementação do Code.

## O que pedimos no protótipo

| # | Arquivo | O que está lá | O que o sistema faz | Pedido |
|---|---|---|---|---|
| 1 | `cliente-import.jsx:37-39,112` | Resultado parcial: "N cadastros importados · M linhas com erro", "Baixar as linhas com erro" | Importação é **tudo ou nada** (transação com `rollBack`), sem arquivo de retorno | Tirar da tela a copiar; se quiser manter como ideia, separar como proposta de funcionalidade. Estados reais: sucesso e erro com a mensagem do servidor |
| 2 | `cliente-mapa.jsx:92,96` | "a posição vem do CEP" | A posição vem do campo `position` do cadastro; geocodificar CEP é Non-Goal do charter (`Map.charter.md:43`) | Trocar a copy para não prometer geocodificação |
| 3 | `cliente-mapa.jsx` | Cartão do mapa sem "Ver detalhes" nem celular | Produção mostra os dois (`Map.tsx:200,212`) | Incluir no cartão flutuante, ou dizer que saem |
| 4 | Import, Map, Form | Cabeçalho plano `.os-page-h` com "← Clientes" | O repo tem o `PageHeader` canônico, sem slot de voltar | Decidir qual padrão vale nas telas utilitárias de Cliente |
| 5 | `cliente-mapa.jsx` | "M sem posição" | Dois números possíveis: `todos − com posição` ou `sem coordenada válida` | Dizer qual a tela mostra |

Os dados de demonstração (`CM_CIDADES` com jitter) ficam no protótipo; o Code **não** porta.

## Como devolver

Ajuste no projeto Cowork e **regenere o bundle ao fim do ciclo** (rotina obrigatória, decisão [W]
2026-09-06). O Code baixa pela rota do painel (`node scripts/design/protocolo.config.mjs`) e mede
a paridade com `design-diff.mjs`. Não precisa copiar nada à mão para o repo.

Status do lado do Code: [`HANDOFF.md`](HANDOFF.md) (estado atual) e [`SYNC_LOG.md`](SYNC_LOG.md) (a cada PR).
