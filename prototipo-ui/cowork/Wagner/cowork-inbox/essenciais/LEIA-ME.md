# Intake — módulo Essenciais (Essentials, aba nav_essentials)

Pacote de **prontidão de aplicação** (onda E7) das 7 telas importadas do blade do `main`.
Origem: `Modules/Essentials/Resources/views/{todo,document,memos,reminder,messages,knowledge_base,settings}`.

## O que tem aqui
- `*.charter.md` + `*.casos.md` — o trio que falta pro `scripts/qa/prototipo-readiness.mjs` marcar ✅ (o .tsx é traduzido pelo [CL] na F3).
- `contrato/*.contract.json` — Contrato de Tela (ADR 0286): seções, copy literal e estados que o CI trava.

## Build correspondente (no Cowork)
`essenciais-data.jsx` · `essenciais-extras.jsx` · `essenciais-page.jsx` · `essenciais-page.css`, montados em `oimpresso.com.html` nas rotas `essenciais`, `ess-tarefa`, `ess-documentos`, `ess-memorandos`, `ess-lembretes`, `ess-mensagens`, `ess-kb`, `ess-config`.

## Decisões pendentes de [W] (onda E9 — não desenhei sem ADR)
1. **Tarefa ↔ OS/cliente:** o blade não liga. Ligar muda o modelo (`essentials_todos` ganharia `transaction_id`/`contact_id`) e abre "tarefas da OS" na tela de produção. Vale ADR.
2. **Versão de documento:** hoje é media única. Versionar pede tabela de versões e regra de quem substitui.
3. **Notificação:** tarefa atribuída e memorando novo avisam por onde — e-mail, mural, Jana ou nada? Sem decisão, a tela só mostra; não promete aviso.
