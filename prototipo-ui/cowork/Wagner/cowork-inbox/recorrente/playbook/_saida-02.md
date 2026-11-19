---
sessao: "02"
titulo: "Correções do protótipo de Cobrança Recorrente — saída da thread"
autor: "[CL]"
data: 2026-11-19
base: origin/main bedeeb0096
thread: 02-correcoes-prototipo.md
veredito: "entregue no protótipo — f1 a f6 corrigidos, exceto troca pelos componentes do DS (thread própria). A Page não mudou."
---
# _saida-02 · Correções do protótipo

## Entregue (`cobranca-recorrente-page.jsx` + `.css`)
| item | o que mudou |
|---|---|
| f1 valor | `valorCampo` (pt-BR, 2 casas) no preenchimento de Editar cobrança, Nova assinatura e Plano; `mascaraValor` (centavos da direita) nos 4 campos de valor, incluindo o novo Estornar. |
| f2 estados | Gateways: conectado, credencial inválida, certificado vencido (com ação), nenhum configurado (`?estado=vazio`). Webhooks: "recebendo" ou "N falhas em 24h", último evento, aviso do erro; link "docs" morto removido. Página: `?estado=carregando`, `erro`, `sem_permissao`. Planos: busca sem resultado ≠ nenhum cadastrado. Faturas: vazio inicial. |
| f3 faturas | Status estornada, reembolsada, chargeback e paga parcial (com pago/falta), botão Estornar em paga e parcial (valor e motivo). C6 Bank em Configurações e Nova assinatura; nome único por gateway. Régua de boleto e de cartão; a pill conta retentativas pela régua da forma. |
| f4 a11y | Linha abre por botão; `useDialogo` em drawer, formulários e confirmações (modal, foco inicial, Tab preso, Esc, foco devolvido); fechar com nome; `aria-pressed` nos filtros, chips, favorito e "Mostrar". |
| f5 DS | Filtros com o mesmo vocabulário da pill; fatura pendente neutra; plano/gateway inativo e estorno em pill neutra; `oklch` inline trocado por `--pos`/`--neg`/`--warn`/`--accent`; nota fixada com variante escura; CSS de placeholder e sub-nav removido; "✦"/"⚠️" fora da UI. |
| f6 números | Churn = canceladas no mês ÷ (cobrando + canceladas no mês); MRR, "ativas" e contagem por plano só com em dia/retentando; ciclo customizado pelos dias; histórico de 12 meses derivado de desde/ciclo/cancelamento/falhas, legenda contando a janela; contato e CNPJ mascarados com "Mostrar"; Cancelar assinatura com confirmação e motivo obrigatório. |

## Medido no render local (`http.server` sobre o espelho, tema escuro)
- Assinaturas: MRR conferido à mão com as 6 assinaturas que estão cobrando; Planos mostra o mesmo MRR.
- Drawer aberto por teclado: `role=dialog`, `aria-modal`, foco dentro; Cancelar abre `alertdialog` com o botão
  bloqueado até haver motivo; Esc fecha a confirmação, depois o drawer, e o foco volta para a linha.
- Faturas: 15 linhas com os 4 status novos; Estornar na paga parcial abre com o valor pago; `45050` vira `450,50`.
- `?estado=erro` mostra o aviso e esconde "Novo plano".

## Fora (motivo)
- Componentes do DS no lugar dos reinventados: reescrita de estrutura, merece thread própria.
- Avatar com matiz por nome: não existe token para isso.
- Contratos e alvos `recorrente-*` ainda citam a copy antiga do protótipo. Acompanham a Page, que não mudou; o
  lote L6 (alvo/gap/map) e a onda que levar estas correções à Page refazem os dois.
