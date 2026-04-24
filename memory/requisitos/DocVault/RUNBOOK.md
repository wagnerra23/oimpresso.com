# Runbook · DocVault

Procedimentos operacionais e troubleshooting.

## Problema: `/docs` retorna 404 / Inertia não carrega

**Sintoma**: Navegador mostra "Not Found" ou tela Blade antiga em `/docs`.

**Causa possível #1**: LegacyMenuAdapter não tem `/docs` em `inertiaPrefixes`.

**Correção**: verifique `app/Services/LegacyMenuAdapter.php` linha ~252. Deve conter `'/docs'`.

**Causa possível #2**: build Inertia desatualizada.

**Correção**: `npm run build:inertia` e atualize o `public/build-inertia/manifest.json`.

**Causa possível #3**: `DocVault: false` em `modules_statuses.json`.

**Correção**: abra o JSON e ative, ou rode `php artisan module:enable DocVault`.

## Problema: `docvault:sync-memories` falha "Fonte não existe"

**Sintoma**:
```
Fonte não existe: C:\Users\X\.claude\projects\...\memory
```

**Causa**: Claude nunca escreveu memória pro projeto (perfil ausente), ou usuário diferente, ou Windows env var `USERPROFILE` resolvida errado.

**Correção**: adicione no `.env`:
```
CLAUDE_MEMORY_DIR="/caminho/explicito/memory"
```

## Problema: `docvault:sync-pages` detecta 0 telas com @docvault

**Sintoma**: Sync reporta "Com bloco @docvault: 0" mesmo depois de adicionar blocos.

**Causa**: formato do bloco errado. Parser espera:
```tsx
// @docvault
//   tela: /caminho
//   module: NomeDoModulo
//   stories: US-XXX-001, US-XXX-002
```

**Checklist**:
- Primeira linha é exatamente `// @docvault` (ou com espaço)
- Linhas seguintes começam com `//` (não `/*`)
- Formato `campo: valor`
- Não tem linha em branco entre o `// @docvault` e os campos

## Problema: `docvault:validate` trava em "Undefined array key"

**Sintoma**:
```
Undefined array key "stories" at DocValidator.php:38
```

**Causa**: `listModules()` retorna metadata leve sem `stories`, `rules`. Validator precisa `readModule()` explicito.

**Correção**: já corrigido em commit `e6eeda42`. Se reaparecer, verificar se o check usa `isset($m['stories']) && is_array(...)`.

## Problema: Chat retorna "desabilitado" mesmo com AI ligada

**Sintoma**: Chat mostra `[MODO AI STUB]` em vez de resposta real da OpenAI.

**Causa**: `askWithAi()` é stub — integração OpenAI ainda não implementada (ADR 0006 item C1).

**Workaround**: usar modo offline (desligar `DOCVAULT_AI_ENABLED`). Implementação real aguardando.

## Problema: Tela /docs/modulos/X em 404

**Sintoma**: URL aponta módulo que não existe em `memory/requisitos/`.

**Diagnóstico**:
```bash
ls memory/requisitos/ | grep -i NomeDoModulo
```

**Correção**:
- Nome é case-sensitive — `/docs/modulos/pontowr2` não funciona, use `/docs/modulos/PontoWr2`.
- Se o arquivo existe mas ainda é formato plano (`PontoWr2.md`), o viewer funciona normalmente — não há 404.
- Se nada existe: crie via `php artisan docvault:migrate-module NomeModulo` (ou manualmente copiando template).

## Comandos úteis

```bash
# Sincronização diária (automática às 23h via Scheduler)
php artisan docvault:sync-memories

# Migrar módulo plano → pasta
php artisan docvault:migrate-module NomeDoModulo

# Sync docs_pages (ler @docvault das telas)
php artisan docvault:sync-pages

# Validar um módulo
php artisan docvault:validate --module=PontoWr2

# Auditar (mais detalhado que validate)
php artisan docvault:audit-module DocVault --save

# Gerar stub de teste a partir de Gherkin
php artisan docvault:gen-test R-PONT-002
```

## Problema: auditoria reporta score < 70 sem findings claros

**Sintoma**: `docvault:audit-module NomeModulo` retorna score baixo mas a lista de findings parece genérica.

**Diagnóstico**: auditoria tem 15 checks. Score é ponderado; um único `C06 RULES_HAVE_TEST` falhando com 10 regras derruba 10+ pontos. Rode:
```bash
php artisan docvault:audit-module NomeModulo --save
```
E leia o arquivo gerado em `memory/requisitos/NomeModulo/audits/YYYY-MM-DD.md` — lá vem a tabela detalhada com cada regra faltando teste.

## Problema: `docvault:validate` não encontra telas mesmo com `@docvault`

**Sintoma**: findings `PAGE_NO_META` listam páginas que JÁ têm o bloco.

**Causa**: `docs_pages` está desatualizada. O validate não varre filesystem — usa o cache.

**Correção**: rodar `php artisan docvault:sync-pages` antes de `docvault:validate`. Ou instalar hook:
```bash
php artisan docvault:install-hooks
```
Que configura pre-commit para rodar sync-pages + validate automaticamente.

## Problema: modo AI do chat sempre retorna stub

Ver seção anterior "Chat retorna 'desabilitado' mesmo com AI ligada". Resumo: `ChatAssistant::askWithAi()` é stub — esperado, implementação real fica pendente.

## Contatos em caso de incidente

- **Dono/decisor**: Wagner — wagnerra@gmail.com
- **Repo**: branch `6.7-bootstrap` em `D:\oimpresso.com` (Wagner commita direto nela; `main` está atrás)
- **Servidor produção**: Hostinger (ver `reference_hostinger_server.md` na memória Claude)
- **Deploy**: `git pull origin 6.7-bootstrap && php artisan view:clear && php artisan config:clear`
