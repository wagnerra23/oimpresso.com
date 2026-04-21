# Logs de Sessões

Este diretório guarda registros das sessões de trabalho (humanos ou assistentes de IA). Cada sessão que altera o projeto deve deixar um log aqui.

## Formato do arquivo

Nome: `YYYY-MM-DD-session-NN.md` (ex.: `2026-04-18-session-01.md`)

Conteúdo mínimo:

```md
# Sessão NN — YYYY-MM-DD

## Contexto
Como a sessão começou. O que o usuário pediu.

## O que foi feito
Lista cronológica das ações/arquivos alterados.

## Decisões
Decisões tomadas durante a sessão. Se arquitetural, link para ADR.

## Problemas encontrados e soluções
Erros, como foram resolvidos, o que aprendemos.

## Estado ao final
Resumo do estado (ou link para `memory/08-handoff.md` se é a mesma coisa).

## Próximos passos sugeridos
Deixar claro para quem vier depois.
```

## Por que manter

- Reconstituir contexto quando a memória formal (ADRs, handoff) não cobrir um detalhe
- Ver a evolução: o que a gente pensou que seria simples e virou difícil
- Rastrear regressões: "quando começou esse bug?"
- Onboarding: quem chega vê o histórico real de construção

## Regra

**Nunca edite um session log antigo.** Ele é um snapshot imutável. Para corrigir algo, crie novo log ou atualize o arquivo canônico (handoff, ADR, roadmap).

---

## Sessões

- [2026-04-18 — Sessão 01](./2026-04-18-session-01.md) — scaffolding inicial
- [2026-04-18 — Sessão 02](./2026-04-18-session-02.md) — crash em produção + refactor para padrão Jana

---

**Última atualização:** 2026-04-18 (sessão 02)
