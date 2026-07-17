---
slug: 0090-nfe-replace-gradual-app-services
number: 90
title: "NFe replace gradual: app/Services → Modules/NfeBrasil"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_at: "2026-05-06"
decided_by: [W]
module: core
quarter: 2026-Q2
---

# 0090 — NFe replace gradual: app/Services → Modules/NfeBrasil

- **Status**: accepted
- **Data**: 2026-05-06
- **Decisores**: Wagner
- **Contexto**: descoberta durante US-NFE-041 de que sistema legado já existe e está em uso
- **Relacionado**: ADR 0089 (Capterra-driven Module Evolution), US-NFE-040, US-NFE-041

## Contexto

Durante implementação de `Modules/NfeBrasil` (US-NFE-040 + US-NFE-041), Wagner avisou que **o sistema já emite NFe via outro caminho**:

- `app/Services/NFeService.php` (NFCeService, NFeEntradaService, CTeService, DevolucaoService, DFeService) — services raíz da app, fora de qualquer módulo
- `app/Http/Controllers/NfeController.php` — controller correspondente
- Cert + senha armazenados em **colunas da tabela `business`**:
  - `certificado` BLOB (binary do .pfx em DB)
  - `senha_certificado` VARCHAR(100) — `base64_encode` apenas, **não criptografada**
  - `ultimo_numero_nfe`, `numero_serie_nfe` — sequência fiscal em uso
- Wagner emitiu ≥1 NFe real com sucesso

Em paralelo, o módulo novo `Modules/NfeBrasil` está sendo construído com:
- Cert encrypted-at-rest (`Crypt::encrypt(file_contents)`)
- Senha encrypted (`Crypt::encryptString`)
- Histórico de emissões em `nfe_emissoes`
- Eventos SEFAZ append-only em `nfe_eventos`
- Multi-tenant strict + cobertura Pest

**Risco se mantiver os dois:** divergência de schema, dupla manutenção, bugs por duplicação.
**Risco se substituir abruptamente:** quebrar a emissão atual de produção.

## Decisão

**Replace gradual em 4 fases** com migração automática + fallback transparente:

### Fase 1 — Coexistência segura (esta sessão, US-NFE-041 estendida)

1. **Comando artisan `nfe:migrate-cert-business {biz?}`** — lê `business.certificado` + `business.senha_certificado` (base64) → grava em `nfe_certificados` com encryption real (Crypt::encrypt do .pfx + Crypt::encryptString da senha). **Idempotente** (rerun não duplica).
2. **`CertificadoService::carregarParaSefaz()`** com fallback transparente: se `nfe_certificados` vazia pro business, lê do `business.*` legado. Emissão atual continua funcionando.
3. ADR este documento.

### Fase 2 — NfeService novo escreve em ambos

`Modules/NfeBrasil/Services/NfeService::emitir()` **escreve** em `nfe_emissoes` (rastro fiscal completo) E **incrementa** `business.ultimo_numero_nfe` (1 fonte da verdade fiscal). Wagner valida que próxima emissão real funciona pelo caminho novo.

### Fase 3 — UI deprecada

Tela legada de NFe (se houver dentro de `app/Http/Controllers/NfeController.php`) é substituída pela UI Inertia do `Modules/NfeBrasil`. Wagner usa só a nova.

### Fase 4 — Remoção do legado

Após ≥3 meses operando exclusivamente no NfeBrasil novo:
- `app/Services/{NFe,NFCe,NFeEntrada,CTe,Devolucao,DFe}Service.php` movidos pra `Modules/NfeBrasil/Services/Legacy/` (preservados pra audit) ou deletados
- `app/Http/Controllers/NfeController.php` removido
- Migration drop nas colunas `business.{certificado,senha_certificado}` apenas após Wagner confirmar que `nfe_certificados` tem o cert válido (com backup do BLOB).

## Por que essa estratégia

**Correção gratuita de bug LGPD:** senha do cert hoje em `base64` (reversível) → migração automática upgrade pra `Crypt::encryptString` (chave Laravel). Quem tinha acesso ao DB lia senha em texto. Após migração, precisa de `APP_KEY`.

**Não quebra produção:** legado continua escrevendo em `business.*` e o fallback do CertificadoService permite emissão pelo caminho novo COM os dados antigos. Migração é uma única corrida que upgrada segurança sem mudar comportamento.

**Provável caminho final é simétrico:** ambos os sistemas usam `nfephp-org/sped-nfe` como engine — só muda onde o cert mora. Não precisa reescrever lógica de XML/SEFAZ.

## Consequências

### Positivas
- Bug LGPD da senha em base64 corrigido automaticamente na 1ª migração
- Zero downtime: legado continua funcionando até NfeBrasil estar provado
- Histórico fiscal completo (`nfe_emissoes` + `nfe_eventos`) que o legado não tinha
- Cobertura Pest desde o dia 1 do novo
- Caminho de remoção claro (Fase 4)

### Negativas / custos
- 3 meses de coexistência = janela de divergência se não mantida disciplina
- Comando de migração é "lazy" — Wagner precisa rodar 1× por business
- Fallback adiciona 1 query extra em cada `carregarParaSefaz()` quando `nfe_certificados` ainda vazia (aceitável — uma vez por sessão de emissão)

### Mitigações
- Comando `nfe:migrate-cert-business` aceita `--all` pra migrar em batch todos os businesses com cert legado
- Logging em `carregarParaSefaz`: cada hit no fallback loga "FALLBACK_LEGACY usado business={biz} — rode nfe:migrate-cert-business" pra alertar Wagner
- Telescope monitora as queries fallback — fácil ver quais businesses ainda não migraram

## Plano de execução (Fase 1)

- [x] ADR este documento
- [ ] `CertificadoService::carregarParaSefaz()` com fallback legado + log warning
- [ ] `php artisan nfe:migrate-cert-business {biz?} [--all]` (idempotente)
- [ ] Tests Pest cobrindo migração + fallback
- [ ] Commit + push + run no Hostinger

## Riscos a monitorar (Fase 2-4)

- **Sequência fiscal divergente:** `business.ultimo_numero_nfe` não pode descontinuar de `nfe_emissoes.numero` — TestArtigos pra travar (US-NFE-042 quando criar NfeService).
- **Emissão duplicada durante coexistência:** se Wagner clicar emitir no caminho velho E no novo, gera 2 NFes. Solução: sumir botão antigo na UI assim que NfeBrasil estiver pronto (Fase 3).
- **Cert corrompido na migração:** comando deve validar `openssl_pkcs12_read` no .pfx do legado antes de gravar no novo. Se inválido, abortar e relatar (não corromper estado).

## Validação

- [ ] Wagner roda `nfe:migrate-cert-business` no business da ROTA LIVRE (biz=4) e produz row em `nfe_certificados`
- [ ] Próxima emissão NFe real (Wagner manualmente) funciona via fallback OU caminho novo
- [ ] Após Fase 2, Wagner emite 5+ NFes via `Modules/NfeBrasil` sem incidente
- [ ] Após 3 meses, Fase 4 remove legado sem quebrar nada (precisa ADR de Fase 4 pra confirmar)

## Referências

- ADR 0089 (Capterra-driven Module Evolution)
- `Modules/NfeBrasil/Services/CertificadoService.php`
- `app/Services/NFeService.php` (legado, a ser deprecado)
- Sessão 2026-05-06 — Wagner alerta dois sistemas paralelos
