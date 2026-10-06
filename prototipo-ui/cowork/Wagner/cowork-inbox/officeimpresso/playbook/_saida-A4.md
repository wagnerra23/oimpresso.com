---
thread: "A4"
titulo: Timeline do log com vista própria
dono: "[CC]"
data: 2026-10-05
veredito: "entregue — rota oi-log-timeline, view \"timeline\", licença fixa (1ª ativa); o atalho 'Timeline no log' da lista de licenças leva pra ela."
---
# _saida-A4

- `officeimpresso-page.jsx`: `ViewTimeline` nova (DS `Timeline` agrupado por dia; fallback lista). Mapa no cabeçalho do arquivo corrigido: `licenca_log/timeline/{id}` → `view "timeline"`.
- `app.jsx`: rota `oi-log-timeline` (prova do índice: `"oi-log-timeline"`). Não entrou no submenu — é tela de detalhe parametrizada, a aba ativa fica "Log de acesso".
- **Destrava:** a medida da Timeline deixa de sair igual à do Index. A A3 continua bloqueada pelo lado prod (shell AdminLTE, CSS vazio) — isto não resolve aquilo.
