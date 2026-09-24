---
sessao: "_saida-09"
thread: "09 · Presença SAI do HRM → Ponto dono da jornada"
dono: "[C]"
data: 2026-09-24
base_lida: wagnerra23/oimpresso.com@main 66677a491
prefixo_tocado: "prefixo da thread (ADR 0014 · Essentials/Routes/web.php · EssentialsServiceProvider · Ponto/Config/config.php) + FORA do prefixo, por decisão [W] 2026-09-24 (ver abaixo)"
---
# _saida-09

## Pedido literal
`/onda Hrm --thread 09` + [W] 2026-09-24: *"execute a thread 09 junto com a migração das chaves"* e
*"aposenta as chaves e inclui o botão do cabeçalho e a API"*.

## Feito
1. **ADR 0014 emendada**, não uma ADR nova (LC-19): `lifecycle: arquivado → ativo`, com a seção
   datada "Emenda 2026-09-05 / 2026-09-24". Ela registra a D1, a D3 (como pedido ao dono do Ponto), a
   folha lendo o Ponto, a aposentadoria das 5 chaves com o motivo legal e a tabela de rotas → destino.
   O índice `_INDEX-GENERATED.md` foi regerado; a única mudança nele é o 0014.
2. **As 11 rotas de presença do HRM viraram 301** (`Routes/web.php`):
   - `/attendance`, `/attendance/{…}` e as 4 de consulta → `/ponto/espelho`;
   - `import-attendance` → `/ponto/importacoes`;
   - `clock-in-clock-out` e `validate-clock-in-clock-out` → `/ponto`.
3. **O cron `pos:autoClockOutUser` foi desagendado** (`EssentialsServiceProvider`). O command continua
   registrado e roda sob demanda.
4. **Removido o ponteiro morto** `essentials_user_model` do `Modules/Ponto/Config/config.php`, junto
   com a entrada correspondente do `phpstan-baseline.neon`, que perdeu o motivo.
5. **Fora do prefixo, por decisão [W]:**
   - o botão de entrada/saída do cabeçalho (`header_part` + o modal e o JS no `footer_part` + o view
     composer que consultava `essentials_attendances` em toda página);
   - os 3 endpoints de presença da API do Connector (`get-attendance`, `clock-in`, `clock-out`), que
     agora respondem **410** com `destino: /ponto` (método `cedidoAoPonto`). `holidays` continua
     como estava;
   - o link "Presença" no `nav_hrm`, no `sidebar_hrm` e nos ghosts do `DataController`. Sem essa
     remoção, o `action([AttendanceController::class,'index'])` quebraria toda tela Blade do HRM,
     porque a rota deixou de apontar para o controller.
6. **As 5 chaves de presença foram aposentadas nas Configurações** (`grace_*` ×4 + `is_location_required`):
   saíram do `edit()`, do `validate()`, da Page e do charter. **Elas não migram para o Ponto:** lá a
   lei já fixa as duas coisas (tolerância do Art. 58 §1º da CLT, 5/10 min, em `config.php`;
   geolocalização obrigatória no REP-P, Portaria 671). Uma janela por negócio seria ilegal.
7. **Testes:**
   - novo `Modules/Essentials/Tests/Feature/HrmPresencaCedeAoPontoTest.php`, com UC-HRM-PRES-01..05:
     301 · nada grava · 410 com controle negativo em `holidays` · cron ausente mesmo com `app.env=live` ·
     Configurações sem as chaves, com controle positivo;
   - o caso HTTP do `AttendanceImportMultiTenantTest` agora prova que a mesma planilha pelo caminho
     antigo não grava nada;
   - o teste novo entrou na lane `essentials-pest.yml`, e o trigger passou a incluir os 2 arquivos do
     Connector.
8. `BRIEFING.md` do Essentials atualizado (linha Attendance).

## Não feito, e por quê
- **Dado de `essentials_attendances`:** não foi migrado para o Ponto. O `PARAR SE (b)` da thread
  manda fazer isso em outro PR, com dupla prova.
- **`AttendanceController` e `EssentialsUtil`:** não foram apagados. A thread diz que o código não se
  apaga aqui.
- **Guard da D3 no Ponto** (licença aprovada bloqueia a criação da marcação): o prefixo é de outro
  dono, então fica como pedido. O MCP estava fora do ar (429) e não consegui abrir a task; registrado
  aqui e no corpo do PR.
- **Uso atual em produção:** a contagem de `essentials_attendances` por negócio não foi medida (o SSH
  foi bloqueado pelo modo automático). [W] mandou seguir assim mesmo.
- **Nada rodado localmente:** a worktree não tem `vendor`, e testes rodam no CI/CT 100. Foi feito
  `php -l` nos 13 `.php` tocados, sem erro. Quem prova é a lane `essentials-pest`.
- **Aba Presença do protótipo:** já saiu pela thread 01.

## Descobertas
1. O **worker de fila `attendance-import`** em `app/Console/Kernel.php` fica órfão: ninguém mais despacha
   o job pela web. É inofensivo, mas a remoção dele deve ir junto do PR de migração do dado.
2. O composer do `clock_in_clock_out_modal` no `EssentialsServiceProvider` ainda lê
   `is_location_required`. Ele só alimenta views que deixaram de ser alcançáveis; sai com o
   `AttendanceController`.
3. A emenda muda uma consequência da versão original da 0014: *"usuários do Essentials que não
   precisam de REP-P continuam funcionando sem o Ponto"* deixa de valer para a presença. Quem tem
   Essentials sem o pacote do Ponto perde o registro de presença.
