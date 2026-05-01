# Runbook · NFSe

> **Owner**: Eliana[E]
> **Cliente**: empresa **oimpresso** (Wagner) — **NÃO** ROTA LIVRE
> **Cidade**: Tubarão-SC
> **Status do módulo**: 0% implementado (scaffolds vazios em `Modules/NFSe/` — ainda nem criado)
> **SPEC**: [`SPEC.md`](SPEC.md) · **ADR canônica**: [`adr/arq/0001-cliente-oimpresso-modulo-standalone.md`](adr/arq/0001-cliente-oimpresso-modulo-standalone.md)

Operação dia-a-dia, debug e procedimentos pra implementar e manter o módulo NFSe.

---

## 0. Antes de começar — checklist 1ª vez

Eliana, leia na ordem antes de tocar código:

1. [`README.md`](README.md) — onboarding (5 min)
2. [`SPEC.md`](SPEC.md) — visão + 14 user stories US-NFSE-001..014 (10 min)
3. [`adr/arq/0001-*.md`](adr/arq/0001-cliente-oimpresso-modulo-standalone.md) — por que NFSe é standalone (5 min)
4. `MANUAL_CLAUDE_CODE.md` (raiz do repo) — padrão UI Cockpit + bug do build push
5. `TEAM.md` — seu escopo + WIP máx + permissões

**Sua primeira ação concreta = US-NFSE-001 (pesquisa fiscal Tubarão).** Tudo o resto depende dela.

---

## 1. Pesquisar emissor de Tubarão (US-NFSE-001) — 1ª task obrigatória

```bash
# 1. Pesquise se Tubarão-SC está no Sistema Nacional NFSe (LC 214/2025)
#    → https://www.gov.br/nfse/pt-br/municipios-aderentes
#    → Se SIM: integração federal direta, sem provider terceiro, custo $0/emissão
#    → Se NÃO: confirme webservice ABRASF municipal (consulte prefeitura.tubarao.sc.gov.br)

# 2. Documente o resultado em memory/requisitos/NFSe/PESQUISA_TUBARAO.md
#    Campos obrigatórios:
#    - Sistema (SN-NFSe ou ABRASF)
#    - Endpoint webservice (URL)
#    - Versão do layout
#    - Provider recomendado se aplicável (Focus NFe / NFE.io / PlugNotas)
#    - CNAE oimpresso + código LC 116/2003 sugerido (1.05 ou 1.07 pra software)
#    - Alíquota ISS Tubarão pro código de serviço

# 3. Pareie 30min com Wagner pra fechar provider escolhido + cadastrar conta sandbox
```

**Sem PESQUISA_TUBARAO.md, NÃO comece US-NFSE-002 em diante** — vai retrabalhar.

---

## 2. Setup local (US-NFSE-002+003)

```bash
# Após pesquisa fechada, instale lib do provider (exemplo Focus NFe):
composer require rafwell/laravel-focusnfe

# Variáveis de ambiente (sandbox primeiro, sempre):
echo "NFSE_PROVIDER=focusnfe" >> .env
echo "NFSE_TOKEN_SANDBOX=seu_token_aqui" >> .env
echo "NFSE_AMBIENTE=sandbox" >> .env

# Crie módulo nWidart standalone (NÃO dentro de RecurringBilling):
php artisan module:make NFSe

# Migrations base:
php artisan module:make-migration CreateNfeCertificadosTable NFSe
php artisan module:make-migration CreateNfseEmissoesTable NFSe
php artisan module:make-migration CreateNfseProviderConfigsTable NFSe

# Após escrever schemas (ver SPEC §"Inputs"):
php artisan migrate --module=NFSe
```

---

## 3. Estrutura canônica do módulo

```
Modules/NFSe/
├── Adapters/
│   ├── NfseProvider.php              # interface: emitir/consultar/cancelar
│   ├── NfseNacionalAdapter.php       # se SN-NFSe federal
│   └── FocusNFeAdapter.php           # fallback municipal/ABRASF
├── Services/
│   └── NfseEmissaoService.php        # orquestração + idempotência + retry
├── Models/
│   ├── NfseEmissao.php
│   └── NfseProviderConfig.php
├── Listeners/
│   └── EmitirNfseAposRecurringInvoice.php  # bridge UPOS recurring nativo
├── Http/Controllers/
│   └── NfseController.php
├── Database/Migrations/
│   ├── ...nfe_certificados.php
│   ├── ...nfse_emissoes.php
│   └── ...nfse_provider_configs.php
└── Tests/Feature/
    └── NfseEmissaoServiceTest.php
```

Frontend Inertia (em `resources/js/Pages/Nfse/`):
- `Index.tsx` — listagem com `AppShellV2` + `DataTable` + `StatusBadge` + `PageFilters`
- `Emitir.tsx` — form tomador + serviço + valor

---

## 4. Comandos artisan que você vai criar

```bash
# Emissão manual via CLI (pra testes sem UI)
php artisan nfse:emitir --tomador=CNPJ --valor=100 --cod-servico=1.05 --descricao="..."

# Consultar status pelo protocolo
php artisan nfse:consultar --protocolo=XXX

# Reprocessar fila travada
php artisan nfse:reprocessar --status=erro
```

---

## 5. Problema: provider devolve erro 4xx — XML rejeitado

**Sintoma**: `NfseEmissao` fica com `status=erro`, `erro_mensagem` populada.

**Causas comuns**:

| Erro | Causa | Correção |
|---|---|---|
| `Tomador não encontrado` | CNPJ tomador inválido ou inativo SEFAZ | Validar CNPJ antes via `cnpja.io` ou cadastro fiscal |
| `Código serviço inválido` | LC 116 fora da lista municipal Tubarão | Confira lista municipal — Tubarão pode ter restrições |
| `IM beneficiário não existe` | IM oimpresso errada no cadastro | Confira `nfse_provider_configs.inscricao_municipal` |
| `Cert digital expirado` | A1 do oimpresso passou validade | Renovar com contador, atualizar `nfe_certificados.cert_pfx_encrypted` |
| `Alíquota ISS divergente` | Alíquota config diferente da municipal | Atualize seeder `nfse_provider_configs.aliquota_iss` |

**Debug**:
```bash
# Vê últimas 10 emissões com erro
php artisan tinker
>>> \Modules\NFSe\Models\NfseEmissao::where('status', 'erro')->latest()->limit(10)->get();

# Logs detalhados (XML enviado + resposta provider)
tail -100 storage/logs/nfse.log
```

---

## 6. Problema: NFSe emitida mas tomador reclama que não recebeu

**Sintoma**: status=emitida, mas cliente não vê.

**Causa**: provider envia email automático mas pode estar caindo em spam, ou config de email do provider está desabilitada.

**Correção**:
```bash
# Pegar URL do PDF DANFSE (provider devolve em emissão.metadata.pdf_url)
>>> $emissao = \Modules\NFSe\Models\NfseEmissao::find(N);
>>> $emissao->metadata['pdf_url']

# Reenviar manualmente:
php artisan nfse:reenviar-email --id=N --email=destinatario@cliente.com

# Ou ofereça baixa direta via tela /nfse/{id}/pdf
```

---

## 7. Problema: cancelamento rejeitado

**Sintoma**: tentativa cancelar NFSe → erro `Prazo de cancelamento expirado`.

**Causa**: NFSe Tubarão tem janela legal pra cancelamento (geralmente 7 dias úteis após emissão; confirme na lei municipal).

**Correção**:
- Não tem como forçar cancelamento legal após prazo.
- Solução: emitir **NFSe substitutiva** (modelo legal pra correção pós-prazo).
- Ainda **NÃO está no MVP** (ver SPEC §"Não-objetivos") — escalar pro Wagner se aparecer demanda.

---

## 8. Problema: idempotência falhou — NFSe duplicada

**Sintoma**: 2 NFSe emitidas pro mesmo invoice.

**Causa**: `idempotency_key` não foi gerada ou colidiu.

**Diagnóstico**:
```bash
>>> \Modules\NFSe\Models\NfseEmissao::groupBy('idempotency_key')->havingRaw('COUNT(*) > 1')->get();
```

**Correção**:
- Cancelar a duplicata (a mais recente, geralmente)
- Investigar por que `EmitirNfseJob` rodou 2x — provavelmente queue retry sem ter chave única
- Garantir que `idempotency_key = hash(business_id + tomador + valor + descricao + data + recurring_invoice_id?)`

---

## 9. Problema: ROTA LIVRE (biz=4) recebeu opção de emitir NFSe

**Sintoma**: tela `/nfse` aparece pra Larissa (ROTA LIVRE).

**Causa**: flag `nfse_habilitado` não está OFF no business 4, OU permission `nfse.view` foi atribuída pro role da ROTA LIVRE.

**Correção** (CRÍTICA — viola decisão Wagner 2026-04-30):
```bash
>>> \DB::table('nfse_provider_configs')->where('business_id', 4)->update(['nfse_habilitado' => false]);
>>> \Spatie\Permission\Models\Permission::findByName('nfse.view')->roles()->where('name', 'like', '%#4')->detach();
```

Ver ADR ARQ-0001 (NFSe) §"Cliente alvo".

---

## 10. Bridge com recurring_invoice nativo UPOS (US-NFSE-007)

UltimatePOS já tem recorrência via `recurring_invoice` em `app/Http/Controllers/SellPosController.php`. O listener:

```php
// Modules/NFSe/Listeners/EmitirNfseAposRecurringInvoice.php
class EmitirNfseAposRecurringInvoice {
    public function handle(RecurringInvoiceGeradoEvent $evt): void {
        if (! $this->businessTemNfseHabilitado($evt->businessId)) return;
        if ($evt->businessId === 4) return; // ROTA LIVRE OFF (defesa em profundidade)
        dispatch(new EmitirNfseJob($evt->invoice));
    }
}
```

**Atenção**: o evento `RecurringInvoiceGeradoEvent` **não existe ainda no UPOS core** — você vai precisar emitir via observer customizado em `Transaction` model, OU pareando com Wagner pra adicionar o event no fork do UPOS.

---

## 11. Antes do primeiro deploy produção (US-NFSE-013)

Checklist de smoke test:

- [ ] Cert A1 produção encriptado em `nfe_certificados` (KMS / Vault, NÃO em plaintext)
- [ ] `.env.production`: `NFSE_AMBIENTE=producao` + token produção
- [ ] Emite 1 NFSe REAL de teste (oimpresso → oimpresso, valor R$ 0.01)
- [ ] PDF DANFSE abre e está válido pelo verificador da prefeitura
- [ ] Cancela essa NFSe de teste antes do prazo
- [ ] Logs sem erros em `storage/logs/nfse.log`
- [ ] Métrica `nfse_emissao_total` apareceu em `copiloto_memoria_metricas`
- [ ] Wagner valida + confirma com contador
- [ ] Documenta no session log `memory/sessions/YYYY-MM-DD-nfse-primeira-emissao-real.md`

---

## 12. Como sincronizar mudanças desta SPEC com o time

Sempre que alterar qualquer arquivo em `memory/requisitos/NFSe/`, rode:

```bash
/sync-mem
```

Ou manualmente:
```bash
git add memory/requisitos/NFSe/
git commit -m "docs(nfse): <o que mudou>"
git push origin main
# Webhook GitHub → MCP propaga em <60s
# Wagner/Felipe enxergam via tools MCP (decisions-search nfse)
```

Ver `MANUAL_CLAUDE_CODE.md` §5 (bug recorrente do push).

---

## 13. Quando travar

| Bloqueio | Quem resolve |
|---|---|
| Decisão fiscal (cert, regime, código LC 116, alíquota) | Wagner + contador |
| Decisão UI (tela emitir/listagem) | Wagner |
| Bug Inertia/AppShellV2 (skill `memory-sync` cobre) | Pareie com Claude |
| Bug provider (Focus/SN-NFSe) | Suporte do provider + sandbox |
| Erro deploy Hostinger | Wagner (acesso SSH) |

---

## Refs

- [SPEC](SPEC.md)
- [ADR ARQ-0001](adr/arq/0001-cliente-oimpresso-modulo-standalone.md)
- [ADR-0002 RecurringBilling](../RecurringBilling/adr/arq/0002-nfse-submodulo-vs-nfebrasil.md) (parcialmente superseded)
- TEAM.md → Eliana[E]
- LC 116/2003 (lista de serviços) · LC 214/2025 (NFSe Nacional federal)
- https://www.gov.br/nfse · https://www.tubarao.sc.gov.br
