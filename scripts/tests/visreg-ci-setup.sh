#!/usr/bin/env bash
# Preparação do Laravel do gate visual, num lugar só — usada pelos DOIS jobs de
# .github/workflows/visual-regression.yml (`visual-regression` e `tema-escuro-producao`).
# Antes morava inline no YAML; foi extraída LITERAL (sem reescrever linha nenhuma) quando
# a sonda de tema escuro ganhou job próprio (playbook tema-escuro thread 05), para os dois
# jobs medirem o MESMO banco e a MESMA semente. Mexeu aqui, mexeu nos dois.
#
#   bash scripts/tests/visreg-ci-setup.sh laravel   # .env + key + schema-squash migrate
#   bash scripts/tests/visreg-ci-setup.sh seed      # tenant fictício do visreg
#
# `set -e` sem `pipefail`: é o `bash -e {0}` que o GitHub dava ao `run:` inline.
set -e
case "${1:-}" in
laravel)
mkdir -p storage/framework/{cache,views,sessions,testing} bootstrap/cache
cat > .env <<'EOF'
APP_NAME=oimpresso-visreg
APP_KEY=
APP_ENV=testing
APP_DEBUG=true
APP_URL=http://localhost
LOG_CHANNEL=stderr
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=oimpresso_test
DB_USERNAME=root
DB_PASSWORD=root
CACHE_DRIVER=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
MAIL_MAILER=array
BCRYPT_ROUNDS=4
# ADMINISTRATOR_USERNAMES=visreg_admin: a rota /superadmin NAO e gateada por
# permissao Spatie — `App\Http\Middleware\Superadmin` compara o USERNAME com
# esta lista. O admin que o PixelBaselineTest autentica e o `visreg_admin` do
# VisregTenantSeeder; sem o nome aqui, /superadmin da 403 e a baseline nunca
# nasce (medido 2026-08-19: 19 telas passaram, so a do Superadmin caiu no
# assertOk). Mesmo espirito do MWART_CLIENTE_INDEX logo abaixo: flag de
# ambiente pra tela poder ENTRAR no gate, nao pra mudar o que ela mostra.
ADMINISTRATOR_USERNAMES=visreg_admin
TELESCOPE_ENABLED=false
VIEW_COMPILED_PATH=storage/framework/views
# MWART_CLIENTE_INDEX=true: sem o flag, /cliente cai no Blade legacy e dá 500
# (provado por screenshot do artifact #2320) → fica fora do gate. Com o flag,
# renderiza a Page Inertia canônica de Clientes — que é o que o gate deve proteger.
# Notificações: ativa somente na fixture sintética de CI, sem alterar produção.
FEATURE_FLAGS_FORCED_ON=useV2NotificationTemplates
MWART_CLIENTE_INDEX=true
# MWART_BACKUP_INDEX=true: mesmo motivo do de cima — sem o flag, /backup serve o
# Blade legado e a baseline nasceria da tela VELHA, que e pior que nao ter baseline.
# Flag de ambiente pra tela poder ENTRAR no gate, nao pra mudar o que ela mostra.
MWART_BACKUP_INDEX=true
# MWART_CLIENTE_IMPORT/MAP: até 2026-10-27 o /contacts/import e o /contacts/map só
# serviam React sob essas chaves. No cutover F5 de 2026-10-27 o ContactController
# passou a servir só React, e as duas envs saíram daqui.
# MWART_REPAIR_INDEX / MWART_REPAIR_JOB_SHEET_INDEX=true: mesma razão dos de cima,
# medida em 2026-10-02 — RepairController::index e JobSheetController::index só
# renderizam a Page Inertia sob mwartEnabled(...) (config/mwart.php, default false).
# Sem os flags, /repair/repair e /repair/job-sheet servem o Blade e o
# assertInertia(component) do PixelBaselineTest reprova. Entram no gate por decisão
# [W] 2026-10-02 ("gera as fotos de Repair e JobSheet").
# Escopo: ambiente do runner do visreg. Não toca produção.
MWART_REPAIR_INDEX=true
MWART_REPAIR_JOB_SHEET_INDEX=true
# VISREG_FREEZE_CLOCK: congela `new Date()`/`Date.now()` DO NAVEGADOR no mesmo
# instante que as suítes passam pro Carbon::setTestNow (PixelBaselineTest:78 e
# irmãs). Sem isso, tela que renderiza "agora" no browser (JanaAreaHeader.tsx:80,
# JanaCockpit.tsx:356 e :107) fazia a baseline drifar POR MINUTO — o setTestNow
# só congelava o relógio do PHP. Injetado por layouts/inertia.blade.php, guardado
# por config/visreg.php + isProduction. Aspas: o valor tem espaço.
VISREG_FREEZE_CLOCK="2026-06-11 12:00:00"
EOF
php artisan key:generate --force
php artisan package:discover --ansi
php artisan migrate --force
echo "=== schema-squash check: tabelas criadas ==="
php artisan tinker --execute="echo count(DB::select('show tables')).' tabelas';"
;;
seed)
php artisan db:seed --force
php artisan db:seed --class=Database\\Seeders\\VisregTenantSeeder --force
# Título determinístico semeado ANTES do servidor Browser subir. Os fluxos
# Financeiro precisam de linha aberta para drawer, baixa e lote.
php artisan db:seed --class=Database\\Seeders\\VisregFinanceiroFlowSeeder --force
# TENANT-B SENTINELA (gate L3 Tier 0 — Tier0RenderIsolationTest): business 99 +
# admin id=99 + dado com token ZZLEAK99 nos campos visíveis. Sem este segundo
# tenant é IMPOSSÍVEL provar não-vazamento no render (o VisregTenantSeeder é
# mono-tenant). GATE REAL — se quebrar, o job falha. Vide VisregTenantBLeakSeeder.php.
php artisan db:seed --class=Database\\Seeders\\VisregTenantBLeakSeeder --force
# TENANT-VAZIO (gate L2 estado `empty`): business 98 + admin id=98, scaffold-only
# (zero dado de dominio) → as telas-lista renderizam o empty-state REAL e estavel.
# GATE REAL — se quebrar, o job falha. Vide VisregEmptyTenantSeeder.php.
php artisan db:seed --class=Database\\Seeders\\VisregEmptyTenantSeeder --force
# PIPELINE FSM DA OFICINA (gate L2 estados `default`/`dark` do oficina-os): o Quadro de
# OS é data-driven pelas etapas do processo `oficina_mecanica_os` — sem ele a tela cai
# no EmptyProcessState ("Quadro ainda não configurado"), ou seja, o FSM é PRÉ-CONDIÇÃO
# DE RENDER. Até 2026-07-16 nenhum seeder daqui semeava Oficina: o que fazia o kanban
# aparecer era VAZAMENTO do ConformanceProbesTest (que chama OficinaAutoFsmSeeder pra si
# e cujo afterEach nunca limpa o processo). Como aquele step é pulado no workflow_dispatch
# (`if: github.event_name != 'workflow_dispatch'`), o modo UPDATE gerava baseline com a
# tela não-configurada e o modo VERIFY a rejeitava (diff 6.7793% > τ_alto 2%, idêntico
# nos runs 29512533782/29514784095). Este seeder ESTABELECE a pré-condição nos DOIS
# modos em vez de herdar o vazamento. Zero-delta no verify: é o MESMO seeder canônico
# que já rodava de carona. Só biz=1 — o biz=98 fica sem FSM de propósito (estado
# `empty`). GATE REAL — se quebrar, o job falha. Vide VisregOficinaFsmSeeder.php.
php artisan db:seed --class=Database\\Seeders\\VisregOficinaFsmSeeder --force
# DADO do Quadro (estado `default` do oficina-os). O seeder ACIMA dá a pré-condição (as
# 6 etapas); este dá as OS. Sem ele o `default` fotografava o kanban com 0 OS e KPIs
# zerados — medido 2026-07-16 decodificando as .snap commitadas —, enquanto o docblock
# do IsolatedStatesBaselineTest promete que `default` é "o estado SEEDADO de cada tela".
# Quadro vazio => `default` ≡ `empty` a menos da flag process_seeded, e a única coisa
# que podia se mover entre 2 renders era ela (kanban vazio ⇄ "não configurado") — a
# faixa de ~10% que vinha sendo lida como flakiness. O DADO É A SONDA: com 6 cards,
# perder a pré-condição vira diff >> τ_alto e o gate GRITA, em vez de trocar um vazio
# por outro. Só biz=1 (ADR 0101); biz=98 segue sem OS (estado `empty`).
php artisan db:seed --class=Database\\Seeders\\VisregOficinaBoardSeeder --force
# PRÉ-CONDIÇÃO DA TELA Jana/Chat (/ia/conversa). Sem ela, `ChatController::index`
# CRIA a conversa no primeiro acesso e a mensagem nasce com `created_at` = instante
# REAL do render. E a hora da bolha vem do DADO, não do relógio congelado:
# `Chat.tsx:131` faz `new Date(m.created_at)` — COM argumento, caso que o shim
# VISREG_FREEZE_CLOCK deixa real DE PROPÓSITO (resources/visreg/freeze-clock.js:29).
# Resultado sem este seeder: baseline que drifa A CADA MINUTO. Mesma família do
# `oficina-os` acima — a tela depende de um dado que nenhum seeder estabelecia, e
# regravar a foto trataria o sintoma. GATE REAL — se quebrar, o job falha.
php artisan db:seed --class=Database\\Seeders\\VisregJanaChatSeeder --force
php artisan db:seed --class=Database\\Seeders\\VisregPontoSeeder --force
echo "=== seed check ==="
php artisan tinker --execute="echo 'business='.\App\Business::count().' users='.\App\User::count().' roles='.\Spatie\Permission\Models\Role::count().' fin_titulos='.DB::table('fin_titulos')->where('business_id', 1)->count();"
# PROVA da pré-condição do oficina-os (a sonda que o defeito 2 não tinha): conta os
# stages do processo da Oficina no biz=1. Espera-se > 0 nos DOIS modos — se um dispatch
# voltar a imprimir 0, a baseline de `dark`/`default` nasceria errada OUTRA VEZ.
php artisan tinker --execute="echo 'oficina_stages_biz1='.DB::table('sale_process_stages')->join('sale_processes','sale_processes.id','=','sale_process_stages.process_id')->where('sale_processes.business_id',1)->where('sale_processes.key','oficina_mecanica_os')->count();"
# Contagem das OS do fixture do Quadro. Espera-se 6 (1 por coluna) nos DOIS modos. Se
# voltar 0, o `default` regrediu pro quadro vazio — e aí a baseline não deve ser
# regravada, tem que se achar quem comeu o dado. ⚠️ Esta sonda mede o SEED, não o
# RENDER: quem prova o render é a própria baseline (6 cards). A lição do defeito 2 foi
# essa — sonda no momento errado não flagra tela degradada.
php artisan tinker --execute="echo 'oficina_os_fixture_biz1='.DB::table('service_orders')->where('business_id',1)->where('notes','like','VISREG-BOARD%')->count();"
# PROVA da pré-condição do Jana/Chat. Duas contagens, porque uma só mentiria:
#   jana_conversas_ativas_biz1= deve ser 1 — se for 0, o controller vai CRIAR a
#     conversa no render e a hora da bolha vira o relógio real;
#   jana_msg_fora_do_instante= deve ser 0 — conta mensagem cujo `created_at` NÃO é
#     o instante congelado. Qualquer valor > 0 significa que alguém (o controller,
#     um teste anterior) escreveu com o relógio vivo, e a baseline vai drifar. Não
#     regrave a baseline nesse caso: ache quem escreveu.
php artisan tinker --execute="echo 'jana_conversas_ativas_biz1='.DB::table('jana_conversas')->where('business_id',1)->where('status','ativa')->count().' jana_msg_fora_do_instante='.DB::table('jana_mensagens')->join('jana_conversas','jana_conversas.id','=','jana_mensagens.conversa_id')->where('jana_conversas.business_id',1)->whereDate('jana_mensagens.created_at','!=','2026-06-11')->count();"
;;
*)
  echo "uso: $0 laravel|seed" >&2
  exit 2
  ;;
esac
