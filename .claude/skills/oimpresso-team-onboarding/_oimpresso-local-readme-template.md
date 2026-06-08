# oimpresso-local — zona pessoal/máquina

> **Este diretório é PESSOAL. Não vai pro git, não vai pro MCP, não é compartilhado.**
> Convenção fixada em [ADR 0131](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/0131-tiering-memoria-canonico-local-segredo.md).

## O que mora aqui

- `tasks-pessoais.md` — TODO pessoal (não-MCP)
- `config-maquina.md` — paths, monitor, IDE setup, Herd config
- `vault-refs.md` — ponteiros pro Vaultwarden (NUNCA o segredo)
- `workflow-tips.md` — atalhos, dicas pessoais

## O que NÃO mora aqui

- ❌ Segredos → **Vaultwarden** (`vault.oimpresso.com`)
- ❌ ADRs/decisões/feedback time → git `memory/`
- ❌ Tasks reais do projeto → MCP `tasks-create`

## Critério (1 pergunta)

```
Segredo?           → Vaultwarden
Só meu?            → aqui
Time precisa ver?  → memory/ git
```

Ambíguo → default canônico (git).

## Backup recomendado

OneDrive/Dropbox + symlink. Sem backup, se a máquina morrer, perde tudo.
