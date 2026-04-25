# Claude memory (mirror)

Este diretório é um **espelho read-only** da memória persistente do Claude Code
em `~/.claude/projects/D--oimpresso-com/memory/`.

Sincronizado automaticamente por:

```
php artisan memcofre:sync-memories
```

Agendado pra rodar todo dia às 23:00 via Laravel Scheduler (ver
`App\Console\Kernel::schedule`).

**NÃO edite arquivos aqui diretamente** — mudanças são sobrescritas na
próxima sync. Edite a fonte original em `~/.claude/projects/...` (ou
deixe o Claude escrever nela durante a conversa).

Por que commitar isso no git?
- Backup — memória do Claude só vive no `C:\Users\wagne` por padrão
- Outros agentes (ChatGPT, Cursor) ganham contexto pessoal ao ler o repo
- Histórico de evolução via `git log memory/claude/`
