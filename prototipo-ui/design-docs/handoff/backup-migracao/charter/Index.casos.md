---
id: resources-js-pages-backup-index-casos
casos: Backup do sistema · /backup
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — "não dá para excluir o único backup" e "download só aceita .zip da pasta" valem em qualquer refactor.
owner: wagner
autor: "[CC] 2026-08-19"
last_run: "—"
---

# Casos de Uso & Aceite — Backup do sistema

> Escritos a partir da leitura do `main` (BackUpController + Blade legado). **Nenhum teste existe ainda** —
> todos entram como ⬜ e viram 🧪/✅ quando o [CL] escrever a suíte do handoff §4.
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.

---

## UC-BKP-01 · Saber em um olhar se existe backup de hoje
- **Persona:** Wagner vai aplicar uma atualização e precisa ter certeza de que dá para voltar atrás.
- **Aceite:** Dado que existem backups no disco · Quando faço `GET /backup` · Então a tela renderiza Inertia `Backup/Index`, o KPI "Último backup" mostra hora + idade relativa, e o tom fica `warning` quando o último tem mais de 36 h.
- **Teste:** `BackupInertiaTest` (props) + `Index.test.tsx` (tom do KPI).
- **Regressão que defende:** lista ordenada ao contrário faria o topo mostrar o backup mais velho como "o último".
- **Status: ⬜**

## UC-BKP-02 · Ver que o backup está no mesmo servidor
- **Persona:** Wagner acha que está protegido; o disco de destino é o `local`.
- **Aceite:** Dado `BACKUP_DISK=local` · Quando abro `/backup` · Então aparece o alerta "Backup no mesmo servidor não é backup" com a pasta `storage/app/UltimatePOS/`; Dado um disco remoto (`s3`) · Então o alerta **não** aparece.
- **Teste:** `Index.test.tsx` (`destino.remoto` false/true).
- **Regressão que defende:** ninguém descobrir, no dia do incidente, que backup e banco morreram juntos.
- **Status: ⬜**

## UC-BKP-03 · Gerar backup sob demanda, com o custo declarado
- **Persona:** Wagner, antes de migrar o servidor, quer um backup agora.
- **Aceite:** Dado permissão `backup` · Quando clico "Gerar backup agora" · Então abre confirmação dizendo quanto tempo leva e que o agendado das 03:00 já cobre o dia; Quando confirmo · Então um job é despachado (`Queue::assertPushed(RunBackupJob)`) e a tela mostra o estado "Gerando backup…"; Quando termina · Então a lista traz o arquivo novo no topo e um aviso de que a retenção apagou o mais antigo.
- **Teste:** `BackupInertiaTest` (queue) + `Index.test.tsx` (confirm antes do request).
- **Regressão que defende:** o Blade legado dispara `backup:run` **na requisição** por um `<a href>` sem confirmação — timeout de 504 no meio do dump.
- **Status: ⬜**

## UC-BKP-04 · Baixar o backup do dia
- **Persona:** técnico da WR2 vai restaurar em staging.
- **Aceite:** Dado um arquivo listado · Quando clico "Baixar" · Então recebo `200` com `Content-Disposition: attachment; filename="<arquivo>.zip"`; Dado `APP_ENV=demo` · Então a ação volta com `status.success = 0` e a UI mostra o motivo.
- **Teste:** `BackupInertiaTest`.
- **Status: ⬜**

## UC-BKP-05 · Não conseguir apagar o único backup
- **Persona:** Wagner faz limpeza de disco às 23h e apaga demais.
- **Aceite:** Dado que existe **1** backup no disco · Quando peço para excluí-lo · Então a UI não oferece a ação e o servidor recusa (`status.success = 0`, motivo "o disco ficaria sem backup"); Dado ≥2 backups · Quando confirmo a exclusão · Então o arquivo sai do disco e a lista atualiza.
- **Teste:** `BackupInertiaTest` + `Index.test.tsx` (ação ausente com 1 item).
- **Regressão que defende:** o legado deixa zerar o disco em dois cliques, sem lixeira.
- **Status: ⬜**

## UC-BKP-06 · Nome de arquivo não sai da pasta de backup (Tier 0)
- **Persona:** atacante autenticado com permissão `backup`.
- **Aceite:** Dado `GET /backup/download/..%2F..%2F..%2F.env` (ou `x.php`, ou nome que não está na lista do disco) · Então responde `404` e **nada** é lido/streamado; o mesmo vale para `GET /backup/{nome}/delete`.
- **Teste:** `BackupInertiaTest` (`Storage::fake` + assert de que o arquivo fora da pasta continua existindo).
- **Regressão que defende:** hoje o controller concatena `config('backup.backup.name').'/'.$file_name` sem validar — path traversal de leitura **e** de exclusão.
- **Status: ⬜ (bug aberto — handoff §3.1)**

## UC-BKP-07 · Sem permissão, a tela não existe
- **Persona:** Larissa (atendente).
- **Aceite:** Dado usuário sem a permissão `backup` · Quando acessa `/backup`, `backup/create`, `backup/download/x.zip` ou `backup/x.zip/delete` · Então recebe `403` nas quatro; e o item "Backup" não aparece na nav.
- **Teste:** `BackupInertiaTest` (4 asserts) + `BackupPermissaoTest`.
- **Status: ⬜**

## UC-BKP-08 · A retenção guarda os 5 últimos e nunca o zero
- **Persona:** o sistema, às 03:00.
- **Aceite:** Dado 6 backups no disco · Quando `KeepLatestBackups::deleteOldBackups` roda · Então sobram os 5 mais novos; Dado 1 backup · Então nada é apagado.
- **Teste:** `BackupRetencaoTest`.
- **Status: ⬜**

## UC-BKP-09 · Falha na geração aparece como aviso, não como 500
- **Persona:** Wagner com o disco cheio.
- **Aceite:** Dado que `backup:run` lança exceção (`mysqldump` ausente, disco cheio) · Quando gero o backup · Então a tela mostra `Alert danger` com a mensagem e a lista antiga continua visível — nunca tela branca.
- **Teste:** `BackupInertiaTest` (mock que lança `\RuntimeException`).
- **Regressão que defende:** `catch (Exception $e)` sem barra invertida dentro do namespace não captura nada (handoff §3.2).
- **Status: ⬜ (bug aberto)**

## UC-BKP-10 · A instrução de cron é copiável e some em demo
- **Persona:** Wagner configurando o servidor novo.
- **Aceite:** Dado `cron` não vazio · Quando abro a tela · Então o card "Backup automático" mostra a linha `* * * * * … artisan schedule:run` com botão copiar; Dado `APP_ENV=demo` (`cron` vazio) · Então o card não renderiza.
- **Teste:** `Index.test.tsx`.
- **Status: ⬜**

---

## Backlog de casos (sem id — entram quando tiverem teste)

- **[BACKLOG] Coluna "Origem" (manual/agendado)** — depende de gravar a origem (hoje é inferência pelo horário).
- **[BACKLOG] Verificar integridade do zip** (abrir e listar o dump antes de oferecer download).
- **[BACKLOG] Restaurar a partir da tela** — perigoso; exige decisão de [W] e ambiente de destino.
- **[BACKLOG] Alerta quando o agendado falha 2 dias seguidos** (health check do spatie).
- **[BACKLOG] Escopo multi-tenant do zip** — o dump é do banco inteiro; ver handoff §6.1 (bloqueado em [W]).

## Rastreabilidade (UC → CU do SDD → US do SPEC)

| UC | CU (SDD) | US (SPEC) |
|---|---|---|
| UC-BKP-01..10 | — (sem SDD de Backup) | — |

> Sem SDD/SPEC próprios: a tela é herança do UltimatePOS. Se [W] quiser, estes UCs são a semente do SPEC.

## Como rodar a suíte
1. **Pest:** `docker exec oimpresso-staging php artisan test --filter=Backup` no CT100 (nunca local/Hostinger).
2. **Vitest:** `npm run test -- Backup`.
3. **Contrato:** `node scripts/contrato-de-tela.mjs --contract prototipo-ui/contrato/backup.contract.json`.
4. **Cadência:** ao fim de toda mexida em `Backup/Index.tsx` ou `BackUpController`.

## Trilha do tempo
- 2026-08-19 · [CC] criado junto com o protótipo `backup-page.jsx`; 10 UCs derivados da leitura do `main`, 2 deles cobrindo bugs abertos (path traversal, catch sem namespace). Nenhum teste escrito ainda.
