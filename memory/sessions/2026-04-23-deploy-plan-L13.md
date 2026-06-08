# Deploy Plan — Laravel 9.51 → 13.6 (2026-04-23)

## ⚠️ RISCO: MAJOR VERSION JUMP

Produção está em **Laravel 9.51 + PHP ^8.0** no branch `6.7-bootstrap`. O código novo está em `6.7-react` → L13.6 + Inertia 2 + Pest 4 + 50+ packages bumpados.

**Isto NÃO é um deploy normal.** Aplicar 5 majors de Laravel em produção de uma vez sem staging é risco alto. Abaixo o plano completo.

---

## Pré-requisitos OBRIGATÓRIOS antes de qualquer ação

### 1. Verificar versão do PHP do servidor

Conectar via SSH e checar:
```bash
ssh -4 -p 65002 u906587222@148.135.133.115
php -v   # DEVE ser 8.3+ pra L13
```

**Se PHP < 8.3**: NÃO deploy. Wagner precisa trocar versão PHP no painel Hostinger primeiro.

### 2. Backup completo ANTES de tocar

```bash
cd /home/u906587222/domains/oimpresso.com/public_html

# Arquivos
tar czf ~/backup-pre-L13-$(date +%Y%m%d-%H%M).tar.gz .

# DB
mysqldump -u USUARIO -pSENHA oimpresso_db > ~/backup-db-pre-L13-$(date +%Y%m%d-%H%M).sql
```

### 3. Checar se há arquivos locais não-commited no servidor

```bash
git status   # deve estar clean. Se tiver edits diretos, RESOLVER PRIMEIRO.
```

---

## Gap crítico: branch divergência

- **Deploy atual**: `6.7-bootstrap` (L9.51)
- **Trabalho novo**: `6.7-react` (L13.6) — onde fiz todos os upgrades

**NÃO fazer** `git checkout 6.7-react` direto em produção — é outra branch com histórico independente. Ação correta:

1. **No GitHub**: abrir PR de `6.7-react` → `6.7-bootstrap` → revisar diff → mergear
2. OU: fazer merge local, push pro `6.7-bootstrap`, então servidor pull

Recomendo **opção 1** (PR no GitHub) pra review formal antes de merge.

---

## Staging test (FORTE RECOMENDAÇÃO)

Antes de tocar em produção, criar subdomínio de teste `staging.oimpresso.com` no Hostinger e deploy lá primeiro. 2-3 dias de uso real antes de promover.

Se você decidir pular staging (risco aceito): **seguir o plano de deploy abaixo COM maintenance mode ligado** e monitorar.

---

## Comandos de deploy (execução no servidor após backup)

```bash
cd /home/u906587222/domains/oimpresso.com/public_html

# 1. Maintenance mode (usuários veem página de manutenção)
php artisan down --render="errors::503" --retry=60 --refresh=60

# 2. Pull código novo (após PR mergear)
git fetch origin
git checkout 6.7-bootstrap
git pull origin 6.7-bootstrap

# 3. Dependencies (produção = sem dev deps; Boost só dev)
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Migrations (CUIDADO — pode alterar schema)
php artisan migrate --force

# 5. Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# 6. Re-cache pra produção (performance)
php artisan config:cache
php artisan route:cache
# NÃO rode view:cache se Blade dinâmico é problema

# 7. Storage link (se aplicável)
php artisan storage:link

# 8. Set permissions
chmod -R 775 storage bootstrap/cache

# 9. Maintenance OFF
php artisan up
```

---

## Smoke test pós-deploy (5 min)

No browser:
- [ ] `https://oimpresso.com/login` — tela carrega
- [ ] Login com credencial conhecida
- [ ] `https://oimpresso.com/home` — dashboard OK
- [ ] `https://oimpresso.com/products` — tabela com produtos (reais)
- [ ] `https://oimpresso.com/contacts?type=customer` — tabela
- [ ] `https://oimpresso.com/business/settings` — form renderiza
- [ ] Logout funciona

Se qualquer falhar: **executar rollback IMEDIATO** (próxima seção).

---

## Rollback (se algo der errado)

```bash
cd /home/u906587222/domains/oimpresso.com/public_html
php artisan down

# Restaurar arquivos
cd ~ && tar xzf backup-pre-L13-AAAAMMDD-HHMM.tar.gz -C /home/u906587222/domains/oimpresso.com/public_html/

# Restaurar DB
mysql -u USUARIO -pSENHA oimpresso_db < ~/backup-db-pre-L13-AAAAMMDD-HHMM.sql

cd /home/u906587222/domains/oimpresso.com/public_html
php artisan up
```

---

## Módulos — atenção especial

### PontoWR2

Foi "adaptado pra L9.51" (memory/sessions/2026-04-21-session-09.md). Pode quebrar em L13. Ação:
```bash
php artisan module:disable PontoWr2
# Após smoke test OK, testar reativar:
php artisan module:enable PontoWr2
```

### Outros módulos (Jana, Repair, Project, etc.)

Minha suite de 102 tests só cobre Core + algumas views. **Módulos não foram individualmente testados em L13.** Esperar alguns bugs específicos no primeiro uso real.

---

## Conclusão

Deploy executável mas **não one-shot**. Recomendação:
1. **HOJE**: PR `6.7-react` → `6.7-bootstrap` no GitHub + review
2. **AMANHÃ**: Deploy staging, soak 2-3 dias
3. **DEPOIS**: Deploy produção com backup + rollback pronto

Se Wagner pular staging: aceitar risco, deploy fora de horário de pico, com monitoramento ativo nas primeiras horas.
