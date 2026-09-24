---
slug: 0414-patrimonio-auditoria-deep-link-e-formularios-em-drawer-react
number: 414
title: "Patrimônio: a Auditoria é um deep-link para o Modules/Auditoria, e os formulários migram para drawers React"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-24"
module: null
tags: [patrimonio, assetmanagement, auditoria, activity-log, formularios, drawer, mwart]
supersedes: []
superseded_by: []
supersedes_partially: []
related:
  - 0394-endereco-de-ui-do-patrimonio-pages-patrimonio
  - 0127-modules-auditoria-undo-activity-log
  - 0104-processo-mwart-canonico-unico-caminho
---

# ADR 0414 — Patrimônio: Auditoria por deep-link, formulários em drawer React

## Contexto

Duas perguntas do playbook do Patrimônio
(`prototipo-ui/cowork/Wagner/cowork-inbox/patrimonio/playbook/00-INDICE.md`, §6 itens 5 e o
json §7) seguravam cinco threads. Estado medido em `origin/main` `723d2b1e6` (2026-09-24):

**D-AUDITORIA (thread 13).** O protótipo desenhou uma aba "Auditoria" dentro do Patrimônio.
Só que a trilha por registro já tem dono, que é o `Modules/Auditoria` ([ADR 0127](0127-modules-auditoria-undo-activity-log.md),
módulo mantido pela lápide §5 2026-07-30). A rota `GET /auditoria` desse módulo já filtra por
`subject_type` (`AuditEntryService::ALLOWED_FILTERS`, coluna indexada), e os quatro models do
módulo (`Asset`, `AssetTransaction`, `AssetMaintenance`, `AssetWarranty`) já gravam no
`activity_log`. Uma aba própria criaria um segundo dono do mesmo tema.

**D-FORMS (threads 17 a 20).** A pergunta oferecia "manter o Blade como Non-Goal". Essa opção
não existe mais: o charter de `Patrimonio/Bens` registra, medido em prod (biz=1), que as views
`create`/`edit` do Blade devolvem **200 com 0 bytes** numa navegação direta. São fragmentos de
modal da lista jQuery que já foi substituída, e os botões que apontavam para elas foram
removidos. O cadastro de bem já voltou em React no #7832 (drawer do `patrimonio-forms.jsx`, com
dupla prova de valor e quantidade). A escolha real era entre editar em React e não editar pela UI.

## Decisão

[W], 2026-09-24, na sessão de decisões do playbook:

1. **D-AUDITORIA → deep-link.** A aba "Auditoria" do Patrimônio **não** é tela deste módulo.
   Ela leva à tela do `Modules/Auditoria` já filtrada por `subject_type` dos models do
   Patrimônio. Nada de tabela, listagem ou `RevertService` duplicados aqui.
2. **D-FORMS → drawers React.** Edição de bem, alocação/revogação, manutenção e configurações
   migram para drawers React na mesma Page do índice (PT-02), usando o `patrimonio-forms.jsx`
   como fonte de layout, pelo mesmo caminho do cadastro do #7832.

## Consequências

- A thread 13 encolhe para um link. Medido: o `AuditEntryService::list()` aplica
  `where($key, $valor)`, então aceita **um** `subject_type` por vez. O link aponta para
  `Modules\AssetManagement\Entities\Asset`. Se [W] quiser a trilha de alocação, manutenção e
  garantia na mesma lista, o filtro multi-valor é PR do `Modules/Auditoria`, nunca réplica aqui.
- As threads 17 a 20 destravam, com **1 PR por thread** (≤300 linhas). A 18 continua atrás da 16
  (fusão de revogações na aba de Alocações). Quando o formulário escreve valor ou quantidade,
  vale a REGRA MESTRE (dupla prova + antes→depois). Quando o drawer entra, o Non-Goal "não
  edita" de cada charter é revogado com data.
- As views Blade `create`/`edit` saem quando o drawer correspondente entra, sem chamador
  (`git grep` = 0).
- A marcação `respondida: true` no índice do playbook é do Cowork, e o espelho recebe no
  próximo import. Este repositório não edita o `00-INDICE.md`.
