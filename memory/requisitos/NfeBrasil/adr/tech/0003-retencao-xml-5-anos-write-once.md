# ADR TECH-0003 (NfeBrasil) · Retenção XML 5 anos write-once com hash SHA256

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: tech
- **Relacionado**: ARQ-0001, R-NFE-010

## Contexto

CF/88 art. 195 §3º + Lei 8212/1991 + manuais SEFAZ exigem **guarda do XML autorizado por 5 anos contados do exercício seguinte**. Auditor da Receita pode pedir XML específico em fiscalização — falha em apresentar = autuação.

Multi-tenant SaaS = N businesses × 1k NFe/mês × 5 anos = **60.000 XMLs por business** facilmente. Storage cresce ~2GB/business/ano. Stack atual (Hostinger) tem limite de disco.

Riscos:
- **Perda acidental** — admin deleta diretório errado
- **Corrupção silenciosa** — bit flip em disco antigo
- **Modificação maliciosa** — alguém edita XML pra esconder venda
- **Restore incompleto** — backup parcial
- **Storage rotation** — provedor expira arquivos antigos

## Decisão

3 camadas de proteção:

### 1. Write-once permission

Após gravar, definir permissão FS read-only:

```php
Storage::put($path, $xmlString);
chmod(Storage::path($path), 0444);  // r--r--r--
```

App user perde write. Tentativas de overwrite via app retornam erro filesystem.

### 2. Hash SHA256 persistido na tabela

```php
$hash = hash('sha256', $xmlString);
NfeEmissao::find($id)->update(['xml_hash' => $hash, 'xml_path' => $path]);
```

Tabela e disco são fontes redundantes. Desbate: query `WHERE sha256(file) != xml_hash` revela tampering.

### 3. Verificação diária automática

`VerificarIntegridadeXmlJob` roda diariamente:
- Sample 100 XMLs aleatórios + todos os do dia anterior + todos com `last_verified_at < 30d`
- Recalcula hash do disco
- Compara com `xml_hash` da tabela
- Diferença → alerta crítico (Slack/email/Sentry) + marca `integridade_violada=true`
- Match → atualiza `last_verified_at`

## Consequências

**Positivas:**
- Tampering detectado em até 24h
- Compliance: auditor pode requerer XML; sistema entrega + prova de integridade
- Storage volume previsível: planejamento de capacidade simples
- Reduzir custos: XMLs >2 anos podem migrar pra cold storage (ainda imutáveis, lentos pra ler)

**Negativas:**
- Recuperação de erro requer reset manual de permissão (tenant que precisa "corrigir XML" não pode — só re-emitir/cancelar)
- Job de verificação consome IO (mitigado: sample + scheduled fora de pico)
- Storage não tem deduplicação (cada XML é único, tudo bem)

## Schema additions

```sql
ALTER TABLE nfe_emissoes
    ADD COLUMN xml_hash CHAR(64) NULL,
    ADD COLUMN xml_path VARCHAR(255) NULL,
    ADD COLUMN last_verified_at TIMESTAMP NULL,
    ADD COLUMN integridade_violada BOOLEAN NOT NULL DEFAULT false,
    ADD INDEX idx_last_verified (last_verified_at),
    ADD INDEX idx_violada (integridade_violada);
```

## Plano de archive (cold storage)

Quando passar 24 meses:
- XMLs > 24 meses migram pra `storage/cold/nfe-brasil/...` (mais lento, mais barato, ainda imutável)
- Tabela mantém pointer atualizado
- Acesso lazy: download on-demand quando contador requer

S3 Glacier Deep Archive (futuro): 0,00099 USD/GB/mês — ~R$ 0,005/GB/mês. 100GB cold = R$ 0,50/mês. Trivial.

## Tests obrigatórios (R-NFE-010)

- `XmlImutabilidadeTest` — após `Storage::put`, tentar `file_put_contents` falha
- `HashIntegridadeTest` — modificar XML manualmente → job marca violada
- `Verificacao30dRotacaoTest` — XML com `last_verified_at = 31d` é re-checado

## Política de delete

XML **NUNCA** é deletado por código de aplicação:
- Mesmo cancelamento mantém o XML original (cancelamento é evento, não delete)
- Inutilização também — gera evento, mantém XML

Delete só por administrador via SQL direto (após retenção legal expirar 5+ anos), com aprovação documentada.

## Alternativas consideradas

- **Sem write-once** — rejeitado: bug pode sobrescrever XML produção
- **Hash sem verificação periódica** — rejeitado: tampering passaria despercebido até auditoria
- **Imutabilidade via filesystem dedicado (WORM)** — overkill pro MVP; futuro Enterprise
- **Blockchain/IPFS** — overkill marketing; problema é prosaico

## Referências

- CF/88 art. 195 §3º
- Lei 8212/1991
- R-NFE-010 (SPEC)
- ARQ-0003 (cert storage também usa pattern de chave por business)
