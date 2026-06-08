# EvolutionAgent

Ferramenta meta — ajuda Claude Code a propor o **próximo PR de maior ROI** dentro do oimpresso.

> Status: spec-ready · sem código · aguardando merge L13 em 6.7-bootstrap

- **[SPEC.md](SPEC.md)** — visão, ROI por componente, user stories, métricas
- **adr/arq/** — decisões arquiteturais (4 ADRs)
- **adr/tech/** — decisões técnicas (3 ADRs)

## Pitch em 3 linhas

1. Claude Code permanece como UX primária (Wagner não muda nada).
2. Vizra ADK roda no Laravel como backend — memory vetorial + evals em CI.
3. CC chama Vizra via `php artisan evolution:*` quando precisa.

## Não confunda com

- **Copiloto** ([../Copiloto/](../Copiloto/)) — módulo SaaS pra cliente final, não meta-tool.
- **LaravelAI** ([../LaravelAI/](../LaravelAI/)) — adapter genérico de IA; aqui usamos Prism PHP via Vizra.
