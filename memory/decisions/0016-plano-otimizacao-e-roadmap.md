---
# ADR 0016 — Plano de Otimização e Roadmap PontoWR2

**Data:** 2026-04-21
**Status:** Aceita
**Autora:** Eliana (WR2 Sistemas) — sessão 10

---

## Contexto

Após sessões 01–10, o projeto tem: módulo PontoWR2 estruturado (desativado no servidor), servidor com 30 módulos em produção rodando Laravel 9.51, SSH funcionando, git sincronizado. Este ADR define o plano de ação priorizado.

---

## Situação Atual (2026-04-21)

### ✅ Concluído
- Stack atualizada para Laravel 9.51 + PHP 8.3 (ADR 0012)
- PontoWR2 ServiceProvider corrigido (boot error do Laravel 9)
- Factories convertidas para classe-based (Laravel 9)
- `findorfail` → `findOrFail` em 150+ arquivos
- Coluna `price_calculation_type` adicionada ao banco
- Branch `producao` no GitHub com estado do servidor
- SSH configurado (IPv4, hostkey aceita)
- Inventário completo dos 30 módulos (ADR 0013)

### ⚠️ Pendente Imediato
1. **Reativar PontoWR2 no servidor** — os arquivos corrigidos estão prontos
2. **Testar `/sells/create`** — correções aplicadas, cache limpo, aguarda teste
3. **515 chaves de tradução PT faltando** no `lang_v1.php`

---

## Plano de Ação Priorizado

### FASE 1 — Estabilização (próxima sessão)
**Objetivo:** PontoWR2 ativo e funcionando no servidor

| # | Tarefa | Comando/Ação | Estimativa |
|---|--------|--------------|------------|
| 1.1 | Fazer deploy dos arquivos PontoWR2 corrigidos | `pscp` dos arquivos de `D:\oimpresso.com\Modules\PontoWr2\` | 30 min |
| 1.2 | Ativar módulo no servidor | `php artisan module:enable PontoWr2` via SSH | 5 min |
| 1.3 | Executar migrations do PontoWR2 | `php artisan module:migrate PontoWr2` | 10 min |
| 1.4 | Limpar cache | `php artisan cache:clear && view:clear` | 2 min |
| 1.5 | Teste smoke: cadastrar colaborador + batida | Via browser em `/ponto/colaboradores` | 30 min |
| 1.6 | Confirmar `/sells/create` funcionando | Acessar com Chrome logado | 5 min |

### FASE 2 — Traduções PT (pode ser paralelo)
**Objetivo:** Interface 100% em português

| # | Tarefa | Estimativa |
|---|--------|------------|
| 2.1 | Completar 515 chaves faltando em `resources/lang/pt/lang_v1.php` | 2-3 horas |
| 2.2 | Adicionar traduções específicas do PontoWR2 (`lang/pt/`) | 1 hora |
| 2.3 | Deploy das traduções via SSH | 15 min |

### FASE 3 — Pilot Runtime (após Fase 1)
**Objetivo:** Processar um AFD real de 1 colaborador end-to-end

| # | Tarefa |
|---|--------|
| 3.1 | Cadastrar 1 colaborador teste com escala vinculada ao Shift existente |
| 3.2 | Importar AFD real (arquivo do REP-P físico) |
| 3.3 | Executar apuração do mês |
| 3.4 | Gerar espelho de ponto |
| 3.5 | Verificar banco de horas |
| 3.6 | Corrigir bugs encontrados |

### FASE 4 — API + BI (integração Connector)
**Objetivo:** PontoWR2 acessível via API para BI e mobile

| # | Tarefa |
|---|--------|
| 4.1 | Criar `PontoController` no Connector com 6 endpoints (ADR 0015) |
| 4.2 | Testar integração com `ia.oimpresso.com` |
| 4.3 | Documentar autenticação para mobile |

### FASE 5 — Relatórios Completos
**Objetivo:** 7 relatórios legais implementados

Os seguintes relatórios têm stub com `RuntimeException` (gerados em sessão anterior):
- [ ] AFD (Arquivo de Fonte de Dados) — Portaria 671 Anexo I
- [ ] AFDT (Arquivo de Fonte de Dados Tratado)
- [ ] AEJ (Arquivo Eletrônico de Jornada)
- [ ] Horas Extras (relatório analítico)
- [ ] Banco de Horas (extrato completo)
- [ ] Atrasos (relatório de ocorrências)
- [ ] eSocial (eventos S-1300, S-2200)

### FASE 6 — Produção Multi-Empresa
**Objetivo:** Liberação para clientes WR2

| # | Tarefa |
|---|--------|
| 6.1 | Testes com múltiplos `business_id` |
| 6.2 | Documentação de instalação |
| 6.3 | Treinamento Eliana |
| 6.4 | Plano de suporte |

---

## Processo de Deploy Otimizado (a partir desta sessão)

### Antes (manual e arriscado)
- Editar arquivo no servidor via cPanel/FTP
- Sem versionamento
- Sem rollback

### Agora (git + SSH)
```bash
# 1. Desenvolver localmente em D:\oimpresso.com (branch 6.7-bootstrap)
# 2. Testar localmente
# 3. Commitar
git add . && git commit -m "feat: descrição"
git push origin 6.7-bootstrap

# 4. No servidor (via SSH plink):
plink -ssh -4 -P 65002 -l u906587222 -pw "..." -hostkey "SHA256:..." 148.135.133.115 \
  "cd domains/oimpresso.com/public_html && git pull origin producao && php artisan cache:clear"

# OU via pscp para arquivos específicos:
pscp -4 -P 65002 -pw "..." -hostkey "SHA256:..." arquivo.php u906587222@148.135.133.115:domains/.../
```

### Chave SSH salva (usar sempre):
```
Host: 148.135.133.115  Port: 65002  User: u906587222
hostkey: SHA256:LUDtH1mWz7zK9aJjzTPTE6In9WBV+cbuuGrWKtRTcA4
IMPORTANTE: sempre usar flag -4 (IPv4 only) — Hostinger bloqueia IPv6
```

---

## Otimizações de Processo Identificadas

### 1. `.gitattributes` para normalizar line endings
```
# Adicionar em D:\oimpresso.com_Site\.gitattributes:
* text=auto
*.php text eol=lf
```
Evita o problema de BOM/CRLF que ocorreu nesta sessão.

### 2. PowerShell: sempre usar UTF8NoBom
```powershell
# NUNCA usar Set-Content -Encoding UTF8 (adiciona BOM)
# SEMPRE usar:
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($path, $content, $utf8NoBom)
```

### 3. SSH alias no PowerShell
```powershell
# Adicionar no $PROFILE do PowerShell:
function ssh-oimpresso { 
    plink -ssh -4 -P 65002 -l u906587222 -pw "Wscrct*2312" `
    -hostkey "SHA256:LUDtH1mWz7zK9aJjzTPTE6In9WBV+cbuuGrWKtRTcA4" `
    148.135.133.115 @args 
}
```

### 4. Configurar git no servidor
```bash
# No servidor, inicializar git e apontar para branch producao:
cd domains/oimpresso.com/public_html
git init
git remote add origin https://github.com/wagnerra23/oimpresso.com.git
git fetch origin producao
git checkout -t origin/producao
```
Isso permite `git pull` direto no servidor em vez de `pscp` arquivo por arquivo.

---

## Métricas de Sucesso

| Métrica | Meta | Status |
|---------|------|--------|
| PontoWR2 ativo no servidor | Sem erros de boot | ⚠️ Pendente |
| `/sells/create` sem erro 500 | HTTP 200 | ⚠️ Aguarda teste |
| Traduções PT | ≥ 95% das chaves | ⚠️ 54% atual |
| Pilot: 1 colaborador apurado | Espelho gerado | ⏳ Fase 3 |
| API de ponto no Connector | 6 endpoints funcionais | ⏳ Fase 4 |
| Relatórios legais | 7/7 implementados | ⏳ Fase 5 |

---

> **Criado na sessão 10 — 2026-04-21**
> **Revisar após conclusão da Fase 1**
