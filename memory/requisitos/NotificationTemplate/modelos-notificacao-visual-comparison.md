---
slug: notificationtemplate-modelos-notificacao-visual-comparison
title: "Modelos de notificação — comparativo visual do primeiro recorte F3"
type: visual-comparison
module: NotificationTemplate
status: draft
date: '2026-10-09'
canon_reference: prototipo-ui/cowork/Wagner/notificacoes-page.jsx
blade_source: resources/views/notification_template/index.blade.php
inertia_target: resources/js/Pages/NotificationTemplate/Index.tsx
---

# Comparativo visual — Modelos de notificação

Configuração com rail e painel. O artefato foi registrado retrospectivamente após o primeiro recorte da #9115; F1.5 deveria ter antecedido o código. Não foi atribuída aprovação visual a uma auditoria estática. A captura canônica foi solicitada no run 37984378351 e a flag permaneceu OFF em produção.

Fontes lidas: [Blade](../../../resources/views/notification_template/index.blade.php) e partial tabs; [referência Cowork](../../../prototipo-ui/cowork/Wagner/notificacoes-page.jsx) e CSS; [DESIGN.md](../../../DESIGN.md); [RUNBOOK](RUNBOOK-modelos-notificacao.md); [SPEC](SPEC.md). O antigo os-page.jsx mencionado pela skill foi removido em 28/08; DESIGN.md §6 apontou para a fonte Cowork viva.

| Dimensão | Blade legado | Referência Cowork / canon atual | Decisão MWART do recorte |
|---|---|---|---|
| 1. Layout | Três widgets verticais, abas por grupo e formulário em lote | Rail + painel; editor + prévia de 340px | AppShellV2 persistente; rail 236px a partir de lg; uma coluna abaixo; prévia na US-NOTIF-004 |
| 2. Hierarquia | Título e Salvar no fim do formulário | Modelo selecionado, canais e Salvar alterações | PageHeader; ação única Editar modelos abre o editor existente por HTML com legacy=1 |
| 3. Densidade | Form Bootstrap, textarea com seis linhas | Painel com espaços de 16px e padding de 24px | Rail p4/16px; painel p6/24px; Stack gap4/16px |
| 4. Iconografia | Sem ícones na seleção | Ícones do shell/DS | Shell herdado; labels textuais nos canais; sem emoji nem nova biblioteca |
| 5. Estados | Primeira aba ativa em cada grupo | Seleção, vazio, editado, carregamento e erro | Deferred/loading; vazio de busca/modelos/canal; aria-pressed e disabled; gravação/erro de POST permanecem no Blade |
| 6. Teclado | Tab padrão; sem busca | / busca, Esc e comandos do editor | / e Esc testados, sem capturar atalhos dentro de campos; Tab nativo; listener com cleanup |
| 7. Persistência | Campos salvos no banco; aba transitória | Rascunho e alterações do editor | Apenas consulta; seleção/busca transitórias; nenhum rascunho local nem sessionStorage; persistência do editor na US-NOTIF-004 |
| 8. Shared | Widget Bootstrap e TinyMCE | Shell, rail e componentes do bundle DS | PageHeader, Button, Input, Badge, EmptyState e primitivos de layout; sem DataTable porque configuração não é PT-01 |
| 9. Tipografia numérica | h1 text-xl/md:text-3xl; pesos bold; corpo herdado | Modelo usa fs6/600; HTML 11,5px/1,6 no CSS | Modelo text-lg/18px semibold; campos text-sm/14px; sem KPIs numéricos; título herdado de PageHeader |
| 10. Espaçamento numérico | row/col-md-12; mt-10 nas tags | Cabeçalho 16px 24px 12px; editor 18px 24px | Rail 16px, painel 24px, grupos gap12px e canais gap8px; não copiar bundle CSS do protótipo |
| 11. Cores | box-primary; callout-warning; botão error | Tokens text/surface/border/warn | Tokens DS muted/secondary/outline; nenhum HEX nem paleta própria |
| 12. Microinterações | Abas jQuery, editor TinyMCE | Seleção e estados de botão/tooltip | Estados dos componentes DS; troca de modelo volta ao e-mail; sem animação própria |
| 13. Referência | index.blade.php + tabs.blade.php | notificacoes-page.jsx/.css recebido no pacote F1 | Consumir essa referência; não criar novo protótipo nem alegar igualdade do editor ainda ausente |
| 14. Benchmark | Formulário tradicional de configuração | Referência específica do pacote F1 | Comparação externa não realizada; não atribuir qualidade equivalente a SaaS sem medição; prioridade ao pacote recebido |
| 15. Persona | Permissão send_notification | F1: Wagner configura, Eliana confere; escritório 1440px | Priorizar encontrar modelo, conferir canais e acessar edição; não transformar configuração em dashboard |

## Crítica e limitações

Auditoria cockpit-runbook estática: 89/100 (DS 38/40, ADR 30/30, UX 21/30), sem CRITICAL no recorte. Pontos fortes: dados dinâmicos por tenant, conteúdo escapado e componentes compartilhados. Prioridades: editor React em lote; prévia/tags; contagem SMS. Esse resultado não substituiu critique sobre pixels nem conferência WCAG.

O HTML salvo apareceu como texto escapado; não foi anunciado como prévia do e-mail. Testar envio, restaurar padrão e traduzir seed continuaram decisões de produto pendentes no pacote F1. Não houve alteração desses mecanismos nem de valores/estoque.

## Acessibilidade e evidência

Cinco testes React mediram seleção, busca, atalhos, reset de canal e extrato somente por e-mail. Oito testes dual e três controles R4 passaram no CT100 (42 verificações). Inputs foram rotulados, canais usaram grupo nomeado e aria-pressed; botões nativos preservaram Tab. Contraste medido, área dos alvos em browser, ordem de foco completa e leitor de tela permaneceram pendentes. Não foi declarado WCAG AA nem status approved.

A baseline inicial foi solicitada somente para NotificationTemplate, no runner Ubuntu canônico. Sells/Index dark permaneceu com divergência 8,6694% registrada separadamente; nenhum snapshot existente foi regenerado para silenciar esse resultado. Smoke das Configurações e recibos no DesignSync ficaram em seus handoffs próprios.
