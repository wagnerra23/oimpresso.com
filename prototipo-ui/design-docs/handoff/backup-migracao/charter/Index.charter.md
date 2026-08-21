---
id: resources-js-pages-backup-index-charter
page: /backup
component: resources/js/Pages/Backup/Index.tsx
related_prototype: prototipo-ui/cowork/backup-page.jsx (rota `backup` do shell Cockpit V2)
status: draft
autor: [CC] 2026-08-19
---

# Charter — Pages/Backup/Index.tsx

> Substitui o Blade `resources/views/backup/index.blade.php` (UltimatePOS legado). Tela de operação de
> infraestrutura dentro do ERP: quem cuida do servidor precisa saber, em um olhar, **se existe backup
> recente e se ele está fora do servidor**.

## Goal único

Wagner abre `/backup` e em **≤2 segundos** responde: *tenho backup de hoje?* — e em **1 clique**
baixa o arquivo do dia ou gera um novo.

## Audience

- **Wagner** (dono/superadmin, 1440px) — uso primário: confere antes de atualização, migração ou mexida no banco.
- **Suporte/técnico da WR2** — baixa o zip para restaurar em staging.
- ❌ **Não é tela de balcão.** Larissa não vê o item na nav (permissão `backup`).

## Data sources (Controller)

`BackUpController@index` → disco `config('backup.backup.destination.disks')[0]`, pasta `config('backup.backup.name')` (`UltimatePOS`).
`@create` → `backup:run` · `@download($file_name)` · `@delete($file_name)` (rota nomeada `delete_backup`).
Props e defer: ver `handoff/HANDOFF-backup-tela.md` §2.

## Layout (Cockpit V2 · PT-01 simplificado)

```
[PageHeader: Backup — N de 5 · último há X h · Y GB]   [Auditoria] [Gerar backup agora]
[KPI row: guardados · último backup · espaço · agendamento]
[Alert warn: destino local (só quando destino.remoto = false)]
[Estado "gerando" (Progress) — só durante o job]
[Tabela: Arquivo · Origem · Tamanho · Data · Idade · [Baixar] [excluir]]
[Card "Backup automático": linha de cron copiável + pasta/retenção/permissão]
```

Sem drawer: um zip não tem detalhe além do que a linha já mostra. Sem filtro/busca: 5 arquivos.

## Tier 0 IRREVOGÁVEIS

- ⛔ `file_name` **sempre** validado contra `^[\w\-]+\.zip$` **e** contra a lista real do disco antes de
  qualquer `readStream`/`delete` — hoje o Blade legado aceita `../` (path traversal, ver handoff §3.1).
- ⛔ Excluir **nunca** pode deixar o disco sem nenhum backup — o último arquivo é irremovível pela UI.
- ⛔ Geração **fora** do request-lifecycle (job) — dump de 260 MB não roda em requisição HTTP.
- ⛔ Permissão `backup` obrigatória nas 4 ações (403), inclusive download.
- ⛔ Em `APP_ENV=demo`: gerar, baixar e excluir desabilitados na UI **e** no servidor.
- ⛔ O frontend **não** infere retenção nem destino: `retencao` e `destino.remoto` vêm do backend.
- ⛔ Nada de nome de arquivo em `dangerouslySetInnerHTML` / URL sem `encodeURIComponent`.

## Decisões F1.5

- **O alerta de destino é a decisão central da tela.** Backup no disco local é o estado default do repo
  (`BACKUP_DISK=local`) e é o cenário em que o backup não salva ninguém. Fica em `Alert warn` fixo,
  acima da lista, até `BACKUP_DISK` apontar para remoto.
- **Confirmação antes de gerar** (não é ação inócua: segura conexões do banco) com o custo declarado em
  tempo — 1 a 3 min — e o lembrete de que o agendado das 03:00 já cobre o dia.
- **Coluna "Origem"** (manual/agendado) não existe no Blade legado: é o que diz se o cron está de fato
  rodando. Deriva do horário do arquivo vs. horário do schedule.
- **Idade em linguagem humana** (`há 6 h`) ao lado da data absoluta — o Blade tinha as duas, mantivemos.
- **Card de cron com botão copiar** em vez do `<code>` cru do Blade.
- **Sem KpiFilterCard** — KPI aqui é leitura, não filtro (nada a filtrar em 5 linhas).

## Estados

| Estado | Tratamento |
|---|---|
| 0 backups | `EmptyState variant="first"` com o motivo (uma falha de servidor não teria volta) |
| gerando | bloco `Progress` + botão desabilitado; com job, "pode fechar a tela" |
| falha ao gerar | `Alert danger` com a mensagem do `\Throwable` (hoje o catch está errado, handoff §3.2) |
| sem permissão | item some da nav; rota 403 |
| demo | ações desabilitadas com motivo visível |
| último backup > 36 h | KPI "Último backup" em tom `warning` |

## Anti-padrões

- ❌ Modal full-screen para detalhe do arquivo (PT-02/PT-04).
- ❌ Botão de gerar sem confirmação (o Blade legado dispara direto num `<a href>`).
- ❌ Botão em gradiente indigo→blue com `rounded-full` (o Blade tem — fora do DS, roxo é o primary).
- ❌ "There are no backups" em inglês dentro de um `.well` (Blade legado).
- ❌ Spinner full-page durante a geração: a lista existente continua útil.

## Referências

- `app/Http/Controllers/BackUpController.php` · `config/backup.php` · `app/Backup/Cleanup/KeepLatestBackups.php`
- `app/Utils/Util.php::getCronJobCommand()` · `routes/web.php:743-746`
- ADR 0190/0235 (primary roxo) · ADR 0286 (contrato de tela) · PT-01/PT-04
- Casos de uso: `Index.casos.md`
