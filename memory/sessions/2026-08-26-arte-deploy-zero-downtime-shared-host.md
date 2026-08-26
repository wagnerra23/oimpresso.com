# Estado da arte — deploy sem downtime de PHP/Laravel sob shared hosting (LiteSpeed/CloudLinux, docroot fixo)

**Data:** 2026-08-26 · **Agente:** `estado-da-arte` · **Escopo:** infra/deploy · **Decisão:** nenhuma tomada (é [W])

> **TL;DR:** sob a nossa restrição exata, **zero-downtime completo É alcançável** — mas só por *uma* família de técnicas: **artefato imutável em path próprio + troca da única camada de roteamento que possuímos (`.htaccess`)**. Symlink clássico é a solução errada aqui, não por não funcionar, mas porque cria aliasing de path que o LiteSpeed/LSAPI não sabe invalidar. O piso alcançável é **0s de 503 na troca de código**, com uma janela de *coexistência* de duas versões (0–15 min, a medir) e com **migrations continuando a ser o único ponto que pode exigir janela** — e isso é disciplina (expand/contract), não infra.

---

## 1. PESQUISA — como o mundo resolve (fontes datadas)

| # | Referência | Mecanismo concreto | Por que é referência |
|---|---|---|---|
| **1** | **Heroku** (slug + release + router; Preboot / Rolling Deploys) | Compila um **slug imutável** (tarball versionado), cria um *release*, e o **router** passa a apontar pros dynos novos só depois de eles estarem servindo. `release phase` roda migration **antes** do switch e aborta o release se falhar ([Slug Compiler](https://devcenter.heroku.com/articles/slug-compiler), [Preboot](https://devcenter.heroku.com/articles/preboot), [Rolling Deploys](https://devcenter.heroku.com/articles/rolling-deploys) — Dev Center, vivos em 2025-09 pelo [changelog Router 2.0](https://devcenter.heroku.com/changelog-items/3374)) | É o modelo canônico "artefato imutável + troca de roteamento" que Fly/Railway/Vapor/Laravel Cloud copiam. Define o vocabulário |
| **2** | **Deployer / Capistrano** (`releases/` + `current` symlink) | Deploy num diretório novo por release; shared dirs (`storage`, `.env`) entram por symlink; a troca é um symlink swap; rollback = trocar de volta ([Deployer 7.x — Shared Recipe](https://deployer.org/docs/7.x/recipe/deploy/shared), [Symlink Recipe](https://deployer.org/docs/7.x/recipe/deploy/symlink)) | É o padrão de facto do mundo PHP. **Todos os artefatos caros (composer, caches) nascem no release dir antes de ele ser vivo** — é isso que torna a atomicidade um não-problema |
| **3** | **PHP internals — OPcache é path-keyed, não inode-keyed** | Rasmus: *"OPcache is not inode-based so we can't use the same trick"* (que o APC usava). Fix canônico no nginx: `fastcgi_param DOCUMENT_ROOT $realpath_root` — o **webserver** resolve o symlink e o PHP nunca vê o alias ([ma.ttias.be, 2014-12-17](https://ma.ttias.be/php-opcache-and-symlink-based-deploys/); [thread internals, jun/2015](https://externals.io/message/86756)). A tentativa de resolver no PHP — [RFC `resolve_symlinks`](https://wiki.php.net/rfc/resolve_symlinks) (draft 0.9, **2021-03-27**, alvo PHP 8.1) — **nunca saiu de draft** | É a razão-raiz de todo sofrimento com symlink deploy em PHP. E é a premissa que **não vale** pra LiteSpeed (ver §Q1) |
| **4** | **LiteSpeed — `.lsphp_restart.txt` por usuário** | O usuário `touch`a `~/.lsphp_restart.txt`; o LSWS compara o timestamp e **reinicia o lsphp *daquele* usuário** na próxima request. Também existe versão server-level em `/usr/local/lsws/admin/tmp/` ([docs LiteSpeed — LSPHP Modes](https://docs.litespeedtech.com/lsws/extapp/php/configuration/modes/), [Controlling LSPHP](https://docs.litespeedtech.com/lsws/extapp/php/configuration/control/)) | **É o único mecanismo documentado de reset de estado de PHP sem root em shared host LiteSpeed.** Substitui o `systemctl reload php-fpm` que não temos |
| **5** | **`mv -T` > `ln -sfn`** | `ln -sfn` **não é atômico**: strace mostra `symlink()→EEXIST` → `unlink()` → `symlink()`; entre os dois o `current` não existe e requests pegam `ENOENT`. O certo é criar symlink temporário e `mv -T tmp current`, que chama `rename(2)` ([dev.to "Your Atomic Deploys Probably Aren't Atomic", **2026-01-12**](https://dev.to/mojoatomic/your-atomic-deploys-probably-arent-atomic-3p7a); [Richard Crowley, 2010-01-06](https://rcrowley.org/2010/01/06/things-unix-can-do-atomically.html)) | Metade das receitas de blog que circulam usa `ln -sfn` e chama de atômica. Não é |

---

## Respostas diretas às 5 perguntas

### Q1 — Symlink + LiteSpeed/CloudLinux funciona na prática?

**Relato real encontrado (1, e é ruim):** thread [*"Issue with cache and symlink deployment"* no fórum LiteSpeed](https://www.litespeedtech.com/support/forum/threads/issue-with-cache-and-symlink-deployment.20151/) — usuários fazendo `releases/ + current` relatam que **durante o deploy alguns arquivos carregam duas vezes ou carregam a versão velha**; tentaram limpar realpath cache e `opcache_reset`; a pergunta central deles é *"como dizer ao PHP do LSAPI pra resetar o que ele sabe sobre symlinks"*, notando que o nginx tem `$realpath_root` e **não há equivalente conhecido no LiteSpeed**.
⚠️ **Não achei a resposta oficial da LiteSpeed** — a página está atrás de bot-check e não consegui ler o thread completo. Trato como *relato de usuário sem solução publicada*, não como veredito.

**`Options +FollowSymLinks` é permitido?** Duas camadas:
- **LiteSpeed suporta a diretiva** — `Options +FollowSymLinks` está na lista de suportadas ([Stack Harbor KB — LiteSpeed .htaccess compatibility](https://stackharbor.com/en/knowledge-base/litespeed-htaccess-compatibility/), sem data).
- **cPanel/EA4 força `SymLinksIfOwnerMatch` por baixo** (patch "Jail"/SymlinkProtection) e **o cliente não reabilita `FollowSymLinks` pelo `.htaccess`** ([ResellerClub KB](https://www.resellerclub.com/help/article/SymLinks-Settings-in-cPanel-Linux-Hosting-Explained), [Stack Harbor](https://stackharbor.com/en/knowledge-base/cpweb-apache-symlink-attack-protection/), ambos sem data). **Mas isso não nos bloqueia:** `SymLinksIfOwnerMatch` permite o symlink quando dono do link == dono do alvo, e todos os nossos releases seriam do mesmo usuário (`u906587222`). ⚠️ Hostinger é **hPanel, não cPanel** — a premissa "EA4/Jail" pode não valer. **Medir, não supor.**

**`realpath_cache_ttl` (default 120s) nos morde?** **Provavelmente NÃO — e a razão é boa:** o PHP **desliga o realpath cache inteiro quando `open_basedir` está setado** ([PHP doc bug #77406](https://bugs.php.net/bug.php?id=77406); o [README do realpath_turbo](https://github.com/Whissi/realpath_turbo/blob/master/README.md) existe *só* por causa disso; a decisão vem do fix do CVE-2006-5178). Shared hosting seta `open_basedir` por usuário praticamente sempre. Se a Hostinger seta, **metade do problema de symlink não existe aqui** — sobra só o OPcache. Defaults confirmados: `realpath_cache_ttl=120`, `realpath_cache_size=4M` ([php.net, ini.core](https://www.php.net/manual/en/ini.core.php#ini.realpath-cache-ttl)).

**Como resolvem?** Sem root, as saídas documentadas são: (a) `opcache_reset()` via HTTP — que **nós já temos** (`public/_ops_opcache_reset.php`, ADR 0269); (b) **`touch ~/.lsphp_restart.txt`** — reinicia o lsphp do usuário, matando opcache **e** realpath cache de uma vez, sem root ([docs LiteSpeed](https://docs.litespeedtech.com/lsws/extapp/php/configuration/modes/)). Essa segunda **não está no nosso arsenal e deveria estar**.

> **Tradução de premissa (a regra dura):** a solução canônica do mundo PHP pra symlink deploy é `$realpath_root` do nginx — ela assume **um webserver que você configura**. Não vale aqui. O que sobra pro nosso ambiente é *evitar o aliasing em vez de invalidá-lo*.

---

### Q2 — Quando não dá pra symlinkar o docroot, o que se faz?

Nosso `.htaccess` de raiz (medido no repo, `D:/oimpresso.com/.htaccess`) já faz `RewriteRule ^(.*)$ public/$1 [L]` — ou seja, **o docroot é a raiz do repo e nós já controlamos o roteamento por rewrite**. Isso muda o ranking em relação ao que a literatura assume.

| Opção | Como funciona | Custo | Modo de falha |
|---|---|---|---|
| **(a) Rewrite pra `releases/<sha>/public/$1`** ⭐ | O `.htaccess` da raiz vira o "router". Trocar release = reescrever **1 arquivo** e trocá-lo por `rename()` atômico. **Cada release tem path REAL distinto ⇒ zero aliasing ⇒ OPcache/realpath nunca ficam stale por construção** | Disco × N releases; regras de bloqueio (`app|vendor|...`) precisam cobrir `releases/*/`; obriga podar releases velhos (senão o OPcache enche — ver [One Utility Bill Eng.](https://medium.com/one-utility-bill-engineering/fixing-our-opcache-config-sped-up-our-php-application-by-3x-871c6fe49be1), 403 na leitura, citado pelo título) | `.htaccess` escrito pela metade ⇒ **500 global** (mitigado por temp+`mv`); LiteSpeed servir o parse velho ⇒ **serve o release anterior**, que é benigno (site no ar) |
| **(d) A/B: dois dirs `blue`/`green` alternando o alvo do rewrite** ⭐ | Igual a (a), mas com **exatamente 2** slots | Disco fixo (2×), poda trivial | Idêntico a (a). Perde rollback pra N-2 |
| **(f) Symlink `current` + rewrite fixo pra `current/public/$1`, swap por `mv -T`** | Rewrite nunca muda ⇒ imune ao parse-cache do LiteSpeed | Mesmo disco de (a) | **Reintroduz o aliasing** (Q1): OPcache pode servir bytecode do release velho. Precisa de `_ops_opcache_reset.php` + `.lsphp_restart.txt` como rede |
| **(b) `mv -T` de diretórios** | ❌ **Não funciona.** `rename(2)` recusa substituir diretório não-vazio (`ENOTEMPTY`); só `renameat2(RENAME_EXCHANGE)` troca dois dirs atomicamente ([man renameat2](https://manpages.ubuntu.com/manpages/bionic/man2/rename.2.html)), e o `mv` do coreutils **não expõe essa flag** ([proposta na lista do coreutils](https://www.mail-archive.com/coreutils@gnu.org/msg08976.html)) nem o PHP tem binding | — | 3-step `mv` deixa janela — é o `ln -sfn` com passos extras |
| **(c) rsync in-place com ordem controlada** | Escreve por cima do vivo em ordem escolhida | Baixo | **Nunca atômico.** A janela de truncamento do `autoload_classmap.php`/`config.php` permanece (§Q4). Reduz, não elimina |
| **(e) Janela curta de maintenance** | O que fazemos hoje | Zero | Piso ~10-20s (não 0), porque os passos caros escrevem por cima do vivo |

**Conclusão da Q2:** (a)/(d) são estritamente melhores que (f) **no nosso caso específico**, porque nosso docroot já é roteado por rewrite. A literatura prefere symlink porque assume docroot configurável; nós temos o oposto e isso, por acidente, nos dá a variante *sem aliasing*.

---

### Q3 — Deployer/Capistrano em shared hosting

- **Deployer documenta o caso**: deployar numa pasta **ao lado** do `public_html` e fazer o `public_html` apontar pro release ([docs 7.x](https://deployer.org/docs/7.x/recipe/deploy/shared)). **Premissa: você pode substituir o `public_html` por um symlink.** Não sabemos se hPanel permite — a Hostinger cria/gerencia esse diretório. **Smoke test.**
- **[deployphp/deployer#793](https://github.com/deployphp/deployer/issues/793)** ("Deploying on shared hosting and serving from the webroot") e **[#1567](https://github.com/deployphp/deployer/issues/1567)** (2018-03-14, "how to put code into public_html and not releases/") existem e são exatamente a nossa pergunta — **não consegui ler as respostas** (GitHub devolveu página de erro nas duas). **Não achei receita oficial publicada pra docroot fixo.**
- **[FlorianMoser/plesk-deployer](https://github.com/FlorianMoser/plesk-deployer)** resolve um problema *vizinho e diferente*: chroot onde o root do SSH ≠ root do servidor; a receita deployá pelo path SSH e troca o symlink pro **path absoluto** logo antes do switch. **Premissa: chroot com paths divergentes.** Não é nosso caso (nosso path SSH == path servido).
- **Envoyer / Ploi / Forge:** todos assumem VPS com root ([Ploi requirements](https://ploi.io/requirements) — "run a command as root user"; Forge provisiona VPS). Envoyer roda por SSH mas assume que **ele** controla o symlink do webroot. **Nenhum serve como está.**

---

### Q4 — o caso PHP que nos mordeu (composer + config:cache)

Isto eu **medi no código-fonte**, não inferi:

| Artefato | Como escreve | Medição |
|---|---|---|
| `vendor/composer/autoload_classmap.php` (+7 irmãos) | `Filesystem::filePutContentsIfModified()` → **`file_put_contents` cru**, **8 arquivos em sequência** | [`AutoloadGenerator.php` L437-467](https://github.com/composer/composer/blob/main/src/Composer/Autoload/AutoloadGenerator.php) + [`Util/Filesystem.php` L917-925](https://github.com/composer/composer/blob/main/src/Composer/Util/Filesystem.php) (lidos do `main` hoje) |
| `bootstrap/cache/config.php` (`config:cache`) | `ConfigCacheCommand` → `$this->files->put()` → **`file_put_contents` cru** | lido no **nosso** `vendor/laravel/framework` (`Filesystem::put()`, sem tempnam) |
| Real-time facade cache | **Corrigido pra tempnam+`rename()`** | [laravel/framework#58947](https://github.com/laravel/framework/pull/58947), merged **2026-02-21**, Laravel 12.x, por causa de exatamente esta race |

Ou seja: **o Laravel já tem `Filesystem::replace()` (tempnam + `rename`, com `clearstatcache` e resolução de symlink) e simplesmente não o usa em `config:cache`/`route:cache`.** É gap upstream real e endereçável.

**Como o mundo lida?** Não achei issue/PR no Composer sobre escrita atômica do autoload — e a razão é estrutural, não descuido: **no modelo release-dir esses artefatos nascem num diretório que ainda não é servido**, então atomicidade de arquivo é irrelevante. O mundo não resolveu o problema; **mudou de terreno pra ele não existir**. Essa é a lição transferível mais forte desta pesquisa.

---

### Q5 — o que dos PaaS cabe num shared host sem root

| Da filosofia do PaaS | Premissa que a sustenta | Vale aqui? |
|---|---|---|
| **Artefato imutável versionado (slug)** | build fora do host de produção | ✅ **Já temos metade** — o Vite builda no runner. Falta estender ao lado PHP (vendor + caches) |
| **Trocar o roteamento, não os arquivos** | um router que você controla | ✅ **Temos um: o `.htaccess`.** É o insight central |
| **`release phase` — migrar/validar ANTES do switch, abortar sem tocar em prod** | comando roda contra o artefato novo | ✅ **Totalmente imitável e barato.** `php artisan about` / `migrate --pretend` contra o release novo antes de trocar |
| **Preboot / rolling (25% por vez)** | controle sobre o pool de processos | ❌ **Não vale.** lsphp é spawnado pelo LSWS; não temos handle. A aproximação possível é *coexistência* das duas versões durante o lag do `.htaccess` — o que só funciona se o schema for compatível com as duas |
| **Reset de estado do runtime** | `systemctl reload php-fpm` | 🟡 **Parcial:** temos `opcache_reset()` via HTTP e existe `touch ~/.lsphp_restart.txt` (não usado) |

---

## 2. COMPARA — estado-da-arte × oimpresso hoje

| Dimensão | Estado-da-arte (Fase 1) | oimpresso hoje | Distância |
|---|---|---|---|
| **Build fora do host de prod** | slug compilado no CI | ✅ `deploy.yml` job `build` no runner ubuntu; bundles via tar/ssh ([ADR 0269](../decisions/0269-deploy-automatico-build-no-runner.md)) | **nenhuma — batemos o padrão** |
| **Artefato imutável versionado (lado PHP)** | release dir por sha | ❌ `git reset --hard` **por cima do vivo**; `vendor/` mutado in-place | **longa** |
| **Troca por roteamento** | router/symlink swap | ❌ não existe troca; existe `artisan down` | **longa** |
| **Atomicidade da publicação de bundles** | rename/swap | ✅ `cp -af` mesclando + poda de chunk morto +3d ([sessão 2026-08-12](2026-08-12-deploy-nao-derruba-o-site.md)) — **melhor que o swap ingênuo**, porque preserva a sessão aberta | **nenhuma — superamos a receita padrão** |
| **Coerência fonte ↔ build** | garantida pelo artefato único | 🟡 conhecidamente quebrável ([ADR 0371](../decisions/0371-deploy-git-reset-nao-atomico-com-build.md), incidente 2026-08-07, 2h em 500) — mitigada, não eliminada | **média** |
| **Rollback** | trocar symlink/release | 🟡 backup rotativo (5) + `build-inertia` anterior guardado; rollback de PHP = `git reset` pra trás + composer + caches (minutos) | **média** |
| **Reset de runtime pós-deploy** | `reload php-fpm` | ✅ `_ops_opcache_reset.php` com token fora do webroot + `touch` de mtime — **obrigatório, falha o deploy** (ADR 0269 §4). 🟡 desconhece `.lsphp_restart.txt` | **curta** |
| **Escrita atômica dos caches** | não-problema (release dir) | ❌ `file_put_contents` cru em composer e `config:cache` — **é a causa real de precisar do `down`** | **longa (mas o alvo certo é a topologia, não o arquivo)** |
| **Release phase (validar antes de publicar)** | Heroku release phase | 🟡 temos os gates certos (`artisan about` boot smoke, verificação de classmap autoritativo, smoke de hash de bundle) — mas rodam **depois** de prod já ter mudado, sob `down` | **média** |
| **Caminho leve quando nada de runtime mudou** | — (PaaS não tem análogo) | ✅ job `sync-light`, classificador 17/17 nos controles, 52% dos deploys não derrubam mais nada | **nenhuma — inovação nossa** |
| **Migrations compatíveis com 2 versões (expand/contract)** | pré-requisito do rolling | ❓ não achei disciplina registrada | **longa — e é o gargalo real do zero-downtime** |
| **Downtime medido** | 0s | 503 mediana **78s**, máx 101s, 76×/7d ≈ **12,3 min/dia** | **longa** |

**Onde batemos ou superamos o mercado, sem inflar:** o merge-em-vez-de-swap dos bundles (resolve o chunk-404 que *nenhuma* receita de blog trata), o classificador leve/completo, e o rigor de evidência dos gates de deploy (classmap com controle positivo, boot smoke console antes do migrate). Isso não é folclore — está medido em [ADR 0371](../decisions/0371-deploy-git-reset-nao-atomico-com-build.md) e na [sessão 2026-08-12](2026-08-12-deploy-nao-derruba-o-site.md).

---

## 3. AVALIA — o que falta, rankeado

| # | Gap | Impacto | Esforço (IA-pair, [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) | Pré-req bloqueante? |
|---|---|---|---|---|
| **1** | **Sondar as 5 premissas do ambiente** (open_basedir, disco/quota, `.htaccess` reload lag, `.lsphp_restart.txt`, symlink same-user) | **alto** — cada uma pode matar ou destravar o desenho inteiro | **~30 min IA-pair** (1 sessão SSH + 1 endpoint temporário) | ❌ nenhum. **É o pré-req de todo o resto** |
| **2** | **Release dir + swap por rewrite do `.htaccess`** (opção (d), A/B) | **alto** — leva o 503 de 78s pra 0s | ~4-6 h IA-pair (topologia + workflow + regras de bloqueio + poda + rollback) | ✅ **depende de #1** (disco e reload lag são kill-criteria) |
| **3** | **Expand/contract em migrations** (regra + gate) | **alto** — sem isso, zero-downtime é ilusão: schema quebrado derruba a versão que ainda está servindo | ~2-3 h IA-pair (regra `.claude/rules/migrations.md` já existe — estender + lint de migration destrutiva) | ❌ independente. **Pode começar hoje** |
| **4** | **Release phase**: rodar `about` + `migrate --pretend` contra o release novo **antes** do switch | médio-alto | ~1-2 h IA-pair (reordenar passos que já existem) | ✅ depende de #2 pra fazer sentido pleno |
| **5** | **`config:cache` atômico** (tempnam+`rename`, ou upstream usando `Filesystem::replace()`) | médio | ~1 h IA-pair local; PR upstream ~2 h | ❌ independente. Vira **desnecessário** se #2 landar |
| **6** | **`touch ~/.lsphp_restart.txt`** como rede pós-deploy | baixo-médio | ~15 min IA-pair | ✅ depende de #1 (validar que Hostinger honra) |
| **7** | Poda de releases + teto de `opcache.max_accelerated_files` | baixo (vira alto se #2 landar sem ele) | ~30 min IA-pair | ✅ depende de #2 |

### Os 5 smoke tests que provam/refutam antes de escrever código

Todos baratos, todos read-only ou reversíveis, **nenhum precisa de root**. Fazer **nesta ordem** — os dois primeiros são kill-criteria.

| # | Premissa sob teste | Sonda | Refuta o desenho se… |
|---|---|---|---|
| **S1** | **Cabe em disco.** Release ≈ código + `vendor` (496M) + `build-inertia`, sem `.git`/`node_modules`/`storage`/uploads | `df -h ~ && du -sh public/build-inertia public/uploads vendor .git node_modules storage` + quota do plano | Se o que sobra < ~2× o tamanho de um release ⇒ **(d) A/B morre**; cai pra gap #3+#5 |
| **S2** | **LiteSpeed recarrega o `.htaccess` na mudança de mtime.** Fontes conflitam: [Stack Harbor](https://stackharbor.com/en/knowledge-base/litespeed-htaccess-compatibility/) diz *"invalidado na mudança do arquivo"*; [site.eu](https://site.eu/support/article/why-htaccess-changes-take-time-in-litespeed-90) diz *"até 15 minutos"* | Criar `~/domains/oimpresso.com/public_html/_probe/a.txt` e `_probe/b.txt` com conteúdos distintos; um `.htaccess` que reescreve `/_probe/x` → `a.txt`; `curl` em loop de 1s; reescrever o `.htaccess` por temp+`mv` apontando pra `b.txt`; **cronometrar até o `curl` virar** e **contar quantos requests deram não-200 no meio** | Se aparecer **qualquer** não-200 na virada ⇒ o rewrite swap não é atômico e o desenho cai pra (f) symlink. Se o lag for >15 min ⇒ ainda é viável (é *cutover lag*, não downtime), mas exige a disciplina de #3 com folga maior |
| **S3** | **`open_basedir` está setado ⇒ realpath cache desligado** ([PHP #77406](https://bugs.php.net/bug.php?id=77406)) | `php -r 'var_dump(ini_get("open_basedir"), ini_get("realpath_cache_size"), realpath_cache_size());'` **pelo lsphp (via HTTP), não pelo CLI** — os `php.ini` diferem | Se `open_basedir` for vazio **e** o realpath cache estiver ligado ⇒ opção (f) fica mais arriscada; reforça a escolha de (a)/(d) |
| **S4** | **`touch ~/.lsphp_restart.txt` reinicia o lsphp do usuário** ([docs LiteSpeed](https://docs.litespeedtech.com/lsws/extapp/php/configuration/modes/)) | Endpoint temporário que imprime `getmypid()`; `curl` 3× (mesmo pid); `touch ~/.lsphp_restart.txt`; `curl` de novo | Se o pid não mudar ⇒ Hostinger não honra; ficamos só com `opcache_reset()` HTTP (que já funciona). **Não bloqueia nada — é rede extra** |
| **S5** | **Symlink same-user é seguido pelo LiteSpeed** (só importa se S2 refutar e formos pra (f)) | `ln -s _probe/a.txt _probe/link.txt` e `curl /_probe/link.txt` | 403/404 ⇒ (f) morre de vez; sobra (a)/(d) ou nada |

> ⚠️ Os probes S2/S4/S5 criam arquivos temporários dentro do docroot de **produção**. Fazer sob path `_probe/` bloqueado a terceiros, com remoção no mesmo comando, e **fora de horário comercial da ROTA LIVRE** (biz=4, 99% do volume). Nenhum toca `business_id`, dado ou schema — risco Tier 0 = zero.

---

## Recomendação

**Comece pelo #1 (as 5 sondas) — alto impacto, ~30 min, zero pré-requisito, e é o único item que pode *matar* o plano caro antes de gastá-lo.** Hoje escrevi um plano de 6h (#2) apoiado em duas premissas que **ninguém neste projeto mediu**: quanto disco sobra e quanto tempo o LiteSpeed leva pra reler o `.htaccess`. Escrever o código antes de S1/S2 é construir sobre premissa não-medida — exatamente o que o §5 do projeto cataloga como reincidente.

**Em paralelo, sem esperar nada: #3 (expand/contract nas migrations).** É o único gap que **não depende de sonda nenhuma**, é o gargalo real de qualquer zero-downtime (com schema incompatível, o release velho quebra mesmo com swap perfeito), e hoje não achei disciplina registrada sobre isso.

### O piso honesto de downtime sob a nossa restrição

| Cenário | Piso de 503 | O que continua não sendo zero |
|---|---|---|
| **Hoje** | ~78s mediana (52% dos deploys já em 0s pelo `sync-light`) | tudo |
| **Só encolhendo a janela** (gaps #5 + reordenação) | **~10-20s** | `config:cache` e `dump-autoload` continuam escrevendo por cima do vivo — a janela **não vai a zero** por este caminho |
| **Com release dir + rewrite swap** (#2) | **0s** | (i) *cutover lag* de 0–15 min com duas versões coexistindo — não é downtime, mas exige #3; (ii) migration destrutiva; (iii) o `mv` do `.htaccess` é atômico, mas um `.htaccess` **sintaticamente inválido** ainda é 500 global — precisa validação antes do `mv` |

**Não existe zero-downtime completo sem #3.** A infra entrega 0s de indisponibilidade; a disciplina de schema entrega 0s de *erro*. São coisas diferentes e a segunda é a mais barata e a mais esquecida.

### Próxima ação hoje

Rodar **S1 e S2** numa sessão SSH única (com o warm-up canônico do `hostinger.md`), colar a saída literal aqui, e só então decidir entre (d) A/B e o fallback de encolher janela. S2 tem que reportar **dois números**: o lag de virada em segundos **e** a contagem de não-200 durante a virada — o segundo é o que decide se o rewrite swap é de fato atômico.

---

## Não achei (registrado como tal)

- Resposta **oficial** da LiteSpeed pro problema de symlink + LSAPI (thread do fórum atrás de bot-check).
- Receita publicada de **Deployer pra docroot fixo** — as duas issues que fazem exatamente essa pergunta ([#793](https://github.com/deployphp/deployer/issues/793), [#1567](https://github.com/deployphp/deployer/issues/1567)) não abriram pra leitura.
- Issue/PR no **Composer** sobre escrita atômica dos arquivos de autoload. Suspeito que não exista porque o modelo release-dir torna a pergunta irrelevante — mas isso é **inferência minha**, não achado.
- Relato de alguém rodando `releases/ + current` **na Hostinger especificamente**. Os guias que aparecem ([dudi.dev](https://dudi.dev/zero-downtime-laravel-deployments), [gist markshust](https://gist.github.com/markshust/e47ae1d202b6b44917db7a40274204ea), [Cloudways/Barrameda](https://emmanpbarrameda.github.io/dev-notes/zero-downtime-laravel-deployment-on-cloudways-using-github-actions-atomic-deployment/)) todos assumem **VPS com docroot próprio**.
- O post do One Utility Bill sobre config de OPcache com deploys atômicos devolveu **403** — cito o título/tese pelo resultado de busca, não pelo corpo lido.

---

## Fontes

- [Heroku Slug Compiler](https://devcenter.heroku.com/articles/slug-compiler) · [Preboot](https://devcenter.heroku.com/articles/preboot) · [Rolling Deploys](https://devcenter.heroku.com/articles/rolling-deploys) · [Router 2.0 changelog, 2025-09-08](https://devcenter.heroku.com/changelog-items/3374)
- [Deployer 7.x — Shared Recipe](https://deployer.org/docs/7.x/recipe/deploy/shared) · [Symlink Recipe](https://deployer.org/docs/7.x/recipe/deploy/symlink) · [issue #793](https://github.com/deployphp/deployer/issues/793) · [issue #1567 (2018-03-14)](https://github.com/deployphp/deployer/issues/1567) · [FlorianMoser/plesk-deployer](https://github.com/FlorianMoser/plesk-deployer)
- [ma.ttias.be — PHP's OPcache and Symlink-based Deploys (2014-12-17)](https://ma.ttias.be/php-opcache-and-symlink-based-deploys/) · [externals.io — clear realpath cache on deployment (jun/2015)](https://externals.io/message/86756) · [PHP RFC resolve_symlinks (draft, 2021-03-27)](https://wiki.php.net/rfc/resolve_symlinks)
- [LiteSpeed — LSPHP Modes](https://docs.litespeedtech.com/lsws/extapp/php/configuration/modes/) · [Controlling LSPHP](https://docs.litespeedtech.com/lsws/extapp/php/configuration/control/) · [fórum: Issue with cache and symlink deployment](https://www.litespeedtech.com/support/forum/threads/issue-with-cache-and-symlink-deployment.20151/)
- [Stack Harbor — LiteSpeed .htaccess compatibility](https://stackharbor.com/en/knowledge-base/litespeed-htaccess-compatibility/) · [Stack Harbor — cPanel symlink attack protection](https://stackharbor.com/en/knowledge-base/cpweb-apache-symlink-attack-protection/) · [ResellerClub — SymLinks Settings](https://www.resellerclub.com/help/article/SymLinks-Settings-in-cPanel-Linux-Hosting-Explained) · [site.eu — Why .htaccess changes take time in LiteSpeed](https://site.eu/support/article/why-htaccess-changes-take-time-in-litespeed-90)
- [php.net — realpath_cache_ttl / realpath_cache_size](https://www.php.net/manual/en/ini.core.php#ini.realpath-cache-ttl) · [PHP doc bug #77406 — open_basedir disables the realpath cache](https://bugs.php.net/bug.php?id=77406) · [realpath_turbo README](https://github.com/Whissi/realpath_turbo/blob/master/README.md)
- [composer/composer — AutoloadGenerator.php](https://github.com/composer/composer/blob/main/src/Composer/Autoload/AutoloadGenerator.php) · [Util/Filesystem.php](https://github.com/composer/composer/blob/main/src/Composer/Util/Filesystem.php) · [laravel/framework#58947 (merged 2026-02-21)](https://github.com/laravel/framework/pull/58947)
- [dev.to — Your "Atomic" Deploys Probably Aren't Atomic (2026-01-12)](https://dev.to/mojoatomic/your-atomic-deploys-probably-arent-atomic-3p7a) · [Richard Crowley — Things UNIX can do atomically (2010-01-06)](https://rcrowley.org/2010/01/06/things-unix-can-do-atomically.html) · [man renameat2](https://manpages.ubuntu.com/manpages/bionic/man2/rename.2.html) · [coreutils — RENAME_EXCHANGE proposal](https://www.mail-archive.com/coreutils@gnu.org/msg08976.html)
- [Ploi requirements](https://ploi.io/requirements)

**Canon interno consultado (Fase 2):** [ADR 0269](../decisions/0269-deploy-automatico-build-no-runner.md) · [ADR 0371](../decisions/0371-deploy-git-reset-nao-atomico-com-build.md) · [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md) · [sessão 2026-08-12](2026-08-12-deploy-nao-derruba-o-site.md) · `.github/workflows/deploy.yml` · `.htaccess` (raiz) · `memory/reference/hostinger.md` · `vendor/laravel/framework/.../Filesystem.php`
