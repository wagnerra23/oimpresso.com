---
id: resources-js-pages-backup-index-charter
page: /backup
component: resources/js/Pages/Backup/Index.tsx
related_prototype: prototipo-ui/cowork/backup-page.jsx
related_runbook: memory/requisitos/Backup/RUNBOOK-index.md
parent_module: Backup
owner: wagner
status: draft
last_validated: "2026-08-20"
tier: B
charter_version: 1
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
---

# Charter — Pages/Backup/Index.tsx

> Substitui o Blade `resources/views/backup/index.blade.php` (UltimatePOS legado). Tela de operação de
> infraestrutura: quem cuida do servidor precisa saber, num olhar, **se existe backup recente e se ele
> está fora do servidor**.

## Goal único

Abrir `/backup` e em **≤2 segundos** responder *tenho backup de hoje?* — e em **1 clique** baixar o
arquivo do dia ou pedir um novo.

## Audience

- **[W] / superadmin** — confere antes de atualização, migração ou mexida no banco.
- **Suporte técnico** — baixa o zip para restaurar em staging.
- ❌ **Não é tela de balcão.** Larissa não vê o item na nav.

## Tier 0 IRREVOGÁVEIS

- ⛔ **O zip é dump do banco INTEIRO — todos os tenants.** Por isso o acesso é de **superadmin**
  (decisão [W] 2026-08-19, [DECISOES-ONDA-0](../../../../memory/requisitos/Backup/DECISOES-ONDA-0.md) §1).
  A tela **declara** isso ao usuário em vez de deixar implícito. [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- ⛔ `file_name` **sempre** validado por padrão **e** contra a listagem real do disco antes de qualquer
  leitura/exclusão. O disco de backup é o `local`, cuja raiz é `public_path('uploads')` — a mesma pasta
  de logos, imagens e documentos de todos os tenants; um `..` alcançava (e o delete **apagava**) arquivo
  de outro tenant. Implementado na Onda 1.
- ⛔ Excluir **nunca** deixa o disco sem nenhum backup — o último arquivo é irremovível pela UI.
- ⛔ Geração **fora** do request-lifecycle (job na fila `backups`). Implementado na Onda 2.
- ⛔ Em `APP_ENV=demo`: gerar, baixar e excluir desabilitados na UI **e** no servidor.
- ⛔ O frontend **não** infere retenção nem destino — `retencao` e `destino` vêm do backend.
- ⛔ Nome de arquivo nunca em `dangerouslySetInnerHTML`; sempre `encodeURIComponent` em URL.

## Non-Goals

> Preenchidos por [W]. O agente não infere Non-Goal — item aqui vira Pest GUARD.

- ❌ **Restaurar backup pela tela.** Restauração é operação de terminal, com o servidor fora do ar.
  A tela entrega o arquivo; quem restaura é gente, com o runbook na mão.
- ❌ **Agendar backup pela tela.** O agendamento é linha de cron no servidor — a tela **mostra** a linha
  e diz se o agendado rodou, mas não a edita.
- ❌ **Lixeira / desfazer exclusão.** Não existe no disco; prometer na UI seria mentira.

## Layout (Cockpit V2 · PT-01 simplificado)

```
[PageHeader: Backup — N de 5 · último há X h · Y GB]      [Gerar backup agora]
[KPI row: guardados · último backup · espaço · agendamento]
[Alert: destino local  (só quando destino.remoto = false)]
[Alert: agendado não rodou  (só quando o cron falhou na janela)]
[Tabela: Arquivo · Origem · Tamanho · Data · Idade · [Baixar] [Excluir]]
[Card "Backup automático": linha de cron copiável + pasta/retenção/permissão]
```

Sem drawer: um zip não tem detalhe além do que a linha mostra. Sem filtro/busca: são 5 arquivos.

## Decisões

- **O alerta de destino é a decisão central da tela.** `BACKUP_DISK=local` é o default do repo, e é o
  cenário em que o backup não salva ninguém. Fica fixo acima da lista enquanto o destino for local.
- **Confirmação antes de gerar.** Não é ação inócua: o dump segura conexões do banco.
- **A geração é assíncrona** (Onda 2) — o banner diz "pode fechar a tela", **nunca** "backup pronto".
- **Coluna Origem** (manual/agendado) não existe no Blade legado: é o que revela se o cron está de fato
  rodando.
- **Idade em linguagem humana** ao lado da data absoluta — o Blade tinha as duas.

## Estados

| Estado | Tratamento |
|---|---|
| 0 backups | `EmptyState` com o motivo, não card vazio |
| falha ao gerar | banner com a mensagem do `\Throwable` — nunca 500 |
| sem permissão | item some da nav; rota 403 |
| demo | ações desabilitadas com motivo visível |
| agendado não rodou na janela | alerta próprio — só existe backup manual recente |

## Anti-padrões

- ❌ Botão de gerar sem confirmação (o Blade legado dispara direto num `<a href>`).
- ❌ Gradiente indigo→blue com `rounded-full` (o Blade tem — fora do DS; o primary é roxo).
- ❌ Texto em inglês ("There are no backups") dentro de um `.well`.
- ❌ Spinner full-page durante a geração: a lista existente continua útil.
- ❌ Prometer "backup pronto" no retorno — a geração é assíncrona.

## Automation Anti-hooks

> Preenchidos por [W].

- ❌ Não cachear a listagem por business: o disco é único e global; cache por tenant daria número errado.
- ❌ Não derivar "agendado rodou" de `config`/cron parseado — derivar do **arquivo** que apareceu.

## Referências

- `app/Http/Controllers/BackUpController.php` · `app/Jobs/RunBackupJob.php` · `app/Console/Kernel.php`
- `config/backup.php` · `app/Backup/Cleanup/KeepLatestBackups.php` · `app/Utils/Util.php::getCronJobCommand()`
- [RUNBOOK-index.md](../../../../memory/requisitos/Backup/RUNBOOK-index.md) · [DECISOES-ONDA-0.md](../../../../memory/requisitos/Backup/DECISOES-ONDA-0.md)
- Casos de uso: [Index.casos.md](Index.casos.md)
