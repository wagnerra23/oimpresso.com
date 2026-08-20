---
id: resources-js-pages-backup-index-casos
casos: Backup do sistema · /backup
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + criterio de aceite verificavel (Dado/Quando/Entao)
por_que: comportamento e duravel — "nao da para excluir o unico backup" e "download so aceita .zip da pasta" valem em qualquer refactor
owner: wagner
autor: "[C] 2026-08-20"
last_run: "2026-08-20"
---

# Casos de Uso & Aceite — Backup do sistema

> Derivados do [RUNBOOK-index.md](../../../../memory/requisitos/Backup/RUNBOOK-index.md) e das
> [decisões da Onda 0](../../../../memory/requisitos/Backup/DECISOES-ONDA-0.md) — não do `.tsx`.
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Correção de fato herdada do protótipo:** a pasta de backup **não** é `storage/app/UltimatePOS/`.
> O disco `local` tem raiz em `public_path('uploads')` (`config/filesystems.php`), então os zips ficam em
> `public/uploads/UltimatePOS/` — a mesma pasta de logos, imagens e documentos de **todos os tenants**.
> É exatamente por isso que a validação de nome de arquivo é Tier 0, e não higiene.

---

## UC-BKP-01 · Saber em um olhar se existe backup de hoje
- **Persona:** [W] vai aplicar uma atualização e precisa ter certeza de que dá para voltar atrás.
- **Aceite:** Dado que existem backups no disco · Quando faço `GET /backup` · Então a tela lista do mais
  novo para o mais velho e o KPI "Último backup" mostra hora + idade relativa.
- **Regressão que defende:** lista ordenada ao contrário mostraria o backup mais VELHO como "o último".
- **Teste:** `tests/Feature/Backup/BackupInertiaTest.php` — ordem, filtro `.zip` e origem derivada do arquivo.
- **Status: ⬜** _(teste existe e cita o UC; ainda não rodou — Pest só no CT 100)_

## UC-BKP-02 · Ver que o backup está no mesmo servidor
- **Persona:** [W] acha que está protegido; o destino é o disco local.
- **Aceite:** Dado `BACKUP_DISK=local` · Quando abro `/backup` · Então aparece o alerta
  "Backup no mesmo servidor não é backup" citando `public/uploads/UltimatePOS/`; Dado destino remoto
  · Então o alerta **não** aparece.
- **Teste:** `BackupInertiaTest` (props `destino.remoto`) + `tests/js/backup-index.test.tsx` (o aviso renderizado, com controle negativo).
- **Status: ⬜** _(teste existe e cita o UC; ainda não rodou)_

## UC-BKP-03 · Gerar backup sem travar a tela
- **Persona:** [W] pede um backup antes de mexer no banco.
- **Aceite:** Dado permissão e ambiente não-demo · Quando confirmo "Gerar backup agora" · Então
  `RunBackupJob` é despachado **na fila `backups`** e a resposta volta com aviso de enfileiramento —
  o `backup:run` **não** roda dentro da requisição.
- **Regressão que defende:** voltar a rodar na requisição traz o 504 em base grande.
- **Teste:** `tests/Feature/Backup/BackupJobTest.php` — *"gerar backup despacha o job"* + *"o job vai para a fila backups"*.
- **Status: 🧪**

## UC-BKP-04 · O backup despachado tem quem o execute
- **Persona:** ninguém — este caso existe porque a falha é **silenciosa**.
- **Aceite:** Dado o job na fila `backups` · Quando consulto o scheduler · Então existe comando agendado
  drenando essa fila. Sem isso o job encalha na tabela `jobs` e a tela mente dizendo "na fila".
- **Regressão que defende:** despachar para a fila `default`, que só é drenada com
  `queue.backlog_worker_enabled` (desligado por padrão).
- **Teste:** `tests/Feature/Backup/BackupJobTest.php` — *"existe worker agendado drenando a fila backups"*.
- **Status: 🧪**

## UC-BKP-05 · Não conseguir apagar o único backup
- **Persona:** [W] limpando espaço, sem perceber que sobrou um só.
- **Aceite:** Dado exatamente 1 backup no disco · Quando peço excluir · Então a exclusão é **recusada**
  com motivo e o arquivo continua lá; Dado 2 ou mais · Então excluir funciona.
- **Regressão que defende:** deixar o disco sem nenhum backup, sem lixeira e sem volta.
- **Teste:** `tests/Feature/Backup/BackupSegurancaTest.php` — *"nao exclui quando e o unico backup do disco"* + *"exclui quando ha mais de um"*.
- **Status: 🧪**

## UC-BKP-06 · Nome de arquivo não sai da pasta de backup (Tier 0)
- **Persona:** atacante autenticado, ou engano com nome colado.
- **Aceite:** Dado um nome fora do padrão (`../vizinho.png`, `x.php`, `sub/a.zip`) · Quando peço download
  ou exclusão · Então **404**, nada é lido e nada é apagado — inclusive arquivo **de outro tenant** na
  raiz do disco.
- **Regressão que defende:** o legado concatenava sem validar; como a raiz do disco é `public/uploads`,
  um `..` alcançava — e o delete **apagava** — arquivo de qualquer outro tenant.
- **Nota de precisão:** o `.env` do projeto nunca foi alcançável (Flysystem lança `PathTraversalDetected`
  ao escapar a raiz). O dano real era dentro de `public/uploads`.
- **Teste:** `tests/Feature/Backup/BackupSegurancaTest.php` — dataset *"nomes recusados"* × download/delete.
- **Status: 🧪**

## UC-BKP-07 · Sem permissão, a tela não existe
- **Persona:** operador de balcão que não deve ver dump do banco.
- **Aceite:** Dado usuário sem a permissão · Quando acesso qualquer das 4 rotas · Então **403**.
- **Teste:** `tests/Feature/Backup/BackupSegurancaTest.php` — *"as quatro rotas devolvem 403 sem a permissao backup"*.
- **Status: 🧪**

## UC-BKP-08 · A tela declara que o zip é do banco inteiro
- **Persona:** quem for baixar precisa saber o que está levando embora.
- **Aceite:** Dado qualquer estado · Quando abro `/backup` · Então a tela diz, em texto visível, que o
  arquivo contém os dados de **todos os negócios** — não só do tenant atual.
- **Por que:** decisão [W] de 2026-08-19 restringiu o acesso a superadmin **e** mandou a tela declarar
  o escopo em vez de deixá-lo implícito.
- **Teste:** `tests/js/backup-index.test.tsx` — o texto precisa estar RENDERIZADO, não só nas props.
- **Status: ⬜** _(teste existe e cita o UC; ainda não rodou)_

## UC-BKP-09 · Falha na geração vira aviso, não 500
- **Persona:** [W] com disco cheio.
- **Aceite:** Dado que a geração falha · Quando peço um backup · Então volto para a tela com aviso
  legível e `status.success = 0` — nunca uma tela de erro 500.
- **Regressão que defende:** o legado usava `catch (Exception)` sem barra dentro do namespace: o catch
  nunca casava e a falha estourava.
- **Teste:** `BackupInertiaTest` — em demo as ações vêm desabilitadas com motivo e sem 500.
- **Status: ⬜** _(teste existe e cita o UC; ainda não rodou)_

## UC-BKP-10 · Em demo, nada dispara
- **Persona:** ambiente de demonstração público.
- **Aceite:** Dado `APP_ENV=demo` · Quando peço gerar, baixar ou excluir · Então a ação é bloqueada com
  motivo visível e **nenhum job é despachado**.
- **Teste:** `tests/Feature/Backup/BackupJobTest.php` — *"em demo nao despacha job nenhum"*;
  `tests/Feature/Backup/BackupSegurancaTest.php` — *"em demo o download e bloqueado sem 500"*.
- **Status: 🧪**

---

## Rastreabilidade

| UC | Defendido por | Onda |
|---|---|---|
| 03, 04, 10 | `BackupJobTest` | 2 |
| 05, 06, 07, 10 | `BackupSegurancaTest` | 1 |
| 01, 09 | `BackupInertiaTest` | 3 |
| 02, 08 | `BackupInertiaTest` + `tests/js/backup-index.test.tsx` | 3 |

Os testes rodam no CT 100, nunca local ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).
