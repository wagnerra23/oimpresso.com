---
id: requisitos-nfe-brasil-adr-tech-0002-contingencia-epec-fsda-retentativa-ordenada
---

# ADR TECH-0002 (NfeBrasil) · Contingência EPEC/FS-DA com retentativa ordenada

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: tech
- **Relacionado**: ARQ-0002, US-NFE-006, R-NFE-009

## Contexto

SEFAZ tem **uptime ~99%** mas downtimes acontecem (manutenção SP, ataques DDoS, fim de mês concorrido). Quando SEFAZ está fora, varejo NÃO PARA — venda continua, mas precisa virar nota fiscal **em algum momento**.

Modos de contingência oficiais SEFAZ:

| Modo | Para que doc | Como funciona |
|---|---|---|
| **EPEC** (Evento Prévio Emissão em Contingência) | NF-e modelo 55 e NFC-e modelo 65 (a critério da UF) | `tpEmis=4` · autoriza imediato em SVC-AN; depois manda XML completo |
| **FS-DA** (Formulário de Segurança - DANFE Auxiliar) | NF-e modelo 55 | `tpEmis=5` · imprime DANFE em papel-segurança; envia XML quando SEFAZ volta |
| **SVC** (SEFAZ Virtual Contingência) | NF-e modelo 55 | `tpEmis=6` (SVC-AN) / `tpEmis=7` (SVC-RS) · autoriza em SVAN/SVRS quando UF principal está fora |
| **OFFLINE NFC-e** | NFC-e modelo 65 | `tpEmis=9` · obrigatório transmitir em até 24h após volta |

> ⚠️ **Correção 2026-09-01 — a tabela acima estava errada em duas células.** Até esta data ela
> atribuía ao **FS-DA** o par `tpEmis=9` + `NFC-e modelo 65`; os dois estão errados. FS-DA é
> **`tpEmis=5` e vale só para NF-e modelo 55** — quem implementasse esta ADR ao pé da letra
> marcaria NFC-e mod. 65 com `tpEmis=9` rotulado FS-DA e cairia na família da **rejeição 714**
> ("NFC-e com opção de contingência inválida"). `tpEmis=9` é a contingência **off-line da NFC-e**,
> que já tinha linha própria e correta. Fonte da correção: [`sped-nfe/docs/Contingency.md`](https://github.com/nfephp-org/sped-nfe/blob/master/docs/Contingency.md)
> — a lib que este projeto usa (`nfephp-org/sped-nfe` no `composer.json`) — corroborada pelos
> [Padrões Técnicos de Contingência Off-line NFC-e](https://www.nfe.fazenda.gov.br/portal/exibirArquivo.aspx?conteudo=fMhAfsQfE+M%3D) do Portal NF-e.
> A **decisão** desta ADR (detecção híbrida, persistência antes da SEFAZ, retentativa FIFO) não
> muda — só os valores de `tpEmis` que ela cita. Os outros 5 sites do mesmo erro no canon
> (`GLOSSARY`, `ARCHITECTURE`, `CHANGELOG`, `SPEC` ×2) foram corrigidos no mesmo PR.

Implementação tem 3 desafios:

1. **Detecção** — quando ativar contingência? Manualmente? Automaticamente?
2. **Persistência** — XMLs em contingência têm que ser persistidos rigorosamente (perda = problema fiscal)
3. **Retentativa** — quando SEFAZ volta, em que ordem reenviar? Pode reenviar em paralelo?

## Decisão

### Detecção: híbrida (auto-sugestão + ativação manual)

- `SefazHealthCheckJob` roda a cada 30s em queue `nfe-health` (separada)
- 3 falhas consecutivas → grava `sefaz_status.degraded=true` por UF
- UI exibe banner "SEFAZ-SP está fora — ativar contingência?"
- Tenant clica "Ativar contingência" → `ContingenciaService::ativar()` muda config business

Auto-ativação: rejeitada (tenant tem que entender o trade-off).

### Persistência: XML em contingência tem prioridade

XML em contingência é gravado **antes de qualquer call SEFAZ**:

```php
class EmitirNfceJob {
    public function handle() {
        $emissao = NfeEmissao::create([
            'tp_emis' => $this->business->em_contingencia ? 9 : 1,
            'status' => $this->business->em_contingencia ? 'contingencia' : 'pendente',
            // ...
        ]);
        Storage::put($emissao->xml_path, $xmlString);  // sempre persiste

        if (! $this->business->em_contingencia) {
            $this->enviarSefaz($emissao);  // pode falhar
        }
    }
}
```

DANFE imprime com indicador "DANFE em contingência - sujeita a transmissão".

### Retentativa: ordenada FIFO por business

Quando SEFAZ volta:
- `RetentarContingenciaJob` lê emissões `status=contingencia` ordenadas por `numero` ASC
- Re-envia 1 por 1 (não paralelo) — preserva ordem cronológica para o auditor
- Cada sucesso: atualiza `status=autorizada`, persiste protocolo
- Cada falha: incrementa `retry_count`; após 5 falhas, marca `status=rejeitada` e alerta gestor

Por que FIFO? SEFAZ aceita ordem qualquer mas auditor questiona se NFe nº 105 foi autorizada antes de 100 (parece "buraco"). Manter ordem facilita análise.

## Consequências

**Positivas:**
- Caixa não para nunca (venda offline com contingência)
- Conformidade: XML preservado mesmo se worker crashar pós-INSERT
- Ordem temporal mantida — auditoria facilitada
- Tenant tem controle (manual on/off)

**Negativas:**
- Throughput reentrega ~5/s (FIFO) — backlog de 5000 NFC-e leva ~17 min após SEFAZ voltar
- Storage cresce em contingência (XMLs sem protocolo ainda)
- Risco operacional: se tenant esquecer de desativar contingência, todas as próximas emissões ficam em modo offline (mitigado por banner persistente "Contingência ATIVA há 2 dias — desativar?")

## Schema additions

```sql
ALTER TABLE nfe_emissoes ADD COLUMN retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE nfe_emissoes ADD COLUMN last_retry_at TIMESTAMP NULL;
ALTER TABLE nfe_business_configs ADD COLUMN em_contingencia BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE nfe_business_configs ADD COLUMN contingencia_ativada_em TIMESTAMP NULL;
ALTER TABLE nfe_business_configs ADD COLUMN contingencia_motivo VARCHAR(255) NULL;

CREATE TABLE nfe_sefaz_status (
    uf CHAR(2) NOT NULL,
    status ENUM('verde', 'amarelo', 'vermelho') NOT NULL,
    last_check_at TIMESTAMP NOT NULL,
    last_response_ms INT UNSIGNED NULL,
    consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (uf)
);
```

## Tests obrigatórios

- `ContingenciaAtivacaoTest` — manual + auto-sugestão (não auto-ativação)
- `ContingenciaPersistenciaTest` — XML salvo mesmo com SEFAZ down
- `RetentarFifoTest` — re-envio em ordem `numero` ASC
- `RetentarBackoffTest` — falha 5x → status=rejeitada + alerta

## Decisões em aberto

- [ ] Cert separado pra SVAN/SVRS contingência? (homologação separada SEFAZ-AN)
- [ ] Auto-desativação após X horas SEFAZ voltar? Risco de ativar/desativar em flapping
- [ ] Notificação pro tenant via push/email quando contingência ativada?

## Alternativas consideradas

- **Apenas modo manual** — rejeitado: tenant pode demorar pra perceber SEFAZ caiu; venda fica em fila errada
- **Auto-ativação** — rejeitado: pode ativar em falsa-detecção (rede do servidor caiu, não SEFAZ)
- **Retentativa paralela** — rejeitado: preserva ordem é mais importante que velocidade
- **Persistir só após SEFAZ confirmar** — rejeitado: perde XML em crash; viola compliance

## Referências

- US-NFE-006, R-NFE-009 (SPEC)
- Manual SEFAZ — Contingência
- ARQ-0002 (lib sped-nfe suporta tpEmis 4/9)
