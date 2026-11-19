---
sessao: "02"
titulo: Correções do protótipo de Cobrança Recorrente (auditoria 2026-11-19, f1–f6)
dono: "[CL]"
base: bedeeb0096
pedido_por: "[W] 2026-11-19 — b4 \"Fonte de design\" · b5 \"Entra\" · edição por PR autorizada"
---
# 02 · Correções do protótipo

O protótipo `cobranca-recorrente-page.jsx` é **fonte de design** (b4) e o cartão recorrente **fica** (b5).
A auditoria do módulo RecurringBilling (2026-11-19, eixo 6) achou seis grupos de defeito no próprio protótipo;
copiá-los para a Page levaria o erro junto. Esta thread corrige o arquivo do protótipo; a Page segue depois,
pelos contratos e pelo alvo (threads A1/01), quando forem refeitos.

## Prefixo
- `prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx`
- `prototipo-ui/cowork/Wagner/cobranca-recorrente-page.css`
- `prototipo-ui/cowork/Wagner/oimpresso.com.html` (só o `?v=` dos dois arquivos)

Não toca: `resources/js/Pages/`, `Modules/`, `governance/design/`.

## O que corrigir
- **f1 — valor.** Campos de valor eram preenchidos com `String(n)` (ponto decimal) e sem máscara, o caminho do
  incidente `num_uf`. Passam a mostrar pt-BR com 2 casas e máscara de centavos (digita só números).
- **f2 — estados.** Gateway com credencial inválida ou certificado vencido, nenhum gateway configurado, webhook
  falhando e último evento recebido; carregando, erro e sem permissão (`?estado=`); vazio de busca em Planos
  separado de "nenhum cadastrado"; vazio inicial de Faturas; link "docs" morto removido.
- **f3 — faturas e gateways.** Fatura estornada, reembolsada, chargeback e paga parcial, com a ação Estornar em
  fatura paga; C6 Bank em Configurações e em Nova assinatura; um nome por gateway; régua por forma de pagamento
  (boleto e cartão), com a pill de retentativa contando pela régua da forma.
- **f4 — acessibilidade.** Abrir assinatura vira botão; drawers e confirmações viram diálogo modal com foco
  inicial, Tab preso, Esc e foco devolvido; fechar com nome; `aria-pressed` nas alternâncias.
- **f5 — vocabulário e DS.** Um vocabulário de status (pill e filtro); cor de fatura por sentido (pendente
  neutra); `oklch` cru inline trocado por tokens (`--pos`, `--neg`, `--warn`, `--accent`), nota fixada com tema
  escuro; CSS morto de placeholder e sub-nav removido; emoji fora da UI.
- **f6 — números e dados.** Churn só do mês; MRR e "ativas" só do que está cobrando (sem pausada e falhou);
  ciclo customizado pelos dias; histórico de pagamentos derivado do contrato; contato e CNPJ mascarados com
  "Mostrar"; Cancelar assinatura com confirmação e motivo.

## Fora desta thread
- Trocar os componentes reinventados pelos do DS (Drawer, StatusBadge, KpiCard, EmptyState, Switch, Timeline,
  Skeleton, Modal, Pagination, DataTable): é reescrita de estrutura, thread própria.
- O gradiente do avatar segue gerado por nome (`oklch` com matiz calculado) — não há token para cor por nome.
- Atalhos de tecla única (N e /) e os dois desenhos em `cowork/Felipe/`: higiene, não entraram.
- Contratos `recorrente-*.contract.json` e alvos `recorrente--*.alvo.json` guardam copy antiga do protótipo
  ("Retentado falhos", "Ticket médio · MRR potencial", "NFe-de-boleto-pago"). Eles acompanham a Page, que ainda
  não mudou; refazer alvo e contrato é do lote L6 e da onda que levar estas correções à Page.

## Provas
- `esbuild` compila o `.jsx`; `ds-guard`, `conformance-gate --all`, `cowork-ssot-guard` e
  `contrato-de-tela --contract` dos 4 contratos limpos.
- Render local: MRR de assinaturas e de planos batem; drawer abre por teclado, prende foco, Esc fecha a
  confirmação e depois o drawer, e o foco volta para a linha; máscara converte `45050` em `450,50`.
