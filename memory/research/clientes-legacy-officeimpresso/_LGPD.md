---
title: Protocolo LGPD — análise de clientes legacy OfficeImpresso
status: live
date: 2026-05-11
audience: time interno + IA-pair + auditoria externa (ANPD se necessário)
lei: Lei 13.709/2018 (LGPD)
revisor: Eliana [E] (advogada do time) — recomendado revisar antes de exposição externa
---

# Protocolo LGPD — análise de clientes legacy OfficeImpresso

> Fundamentação legal e operacional pra trabalho de inteligência sobre os 38 clientes WR Sistemas legacy. Garante que coleta/uso/armazenamento de dados cumprem Lei 13.709/2018.

## 1. Papéis (Art. 5º LGPD)

| Papel | Quem | Responsabilidade |
|-------|------|------------------|
| **Controlador** | Wagner (WR Sistemas / Office Impresso Ltda) | Decide *o que* coletar e *por quê*. Responde por incidente. |
| **Operador** | Claude (IA-pair) + scripts Python rodados localmente | Executa coleta sob instrução do controlador. NÃO decide escopo. |
| **Titular** | Cliente WR Sistemas legacy (CNPJ pessoa jurídica E pessoas físicas mencionadas em razão social/contato) | Tem direitos do Capítulo III |
| **Encarregado (DPO)** | Eliana [E] — designação informal, aguarda decisão Wagner 2026-05-09 ([memory/regras-time.md](../../regras-time.md)) | Ponto de contato pra ANPD/titulares — quando designada |

## 2. Base legal pra tratamento (Art. 7º LGPD)

Tratamento (= análise interna) tem base **Art. 7º, IX — legítimo interesse**: a WR Sistemas tem interesse legítimo em entender seus próprios clientes pra:
- Migrar de tecnologia (Delphi → Laravel) sem prejuízo aos clientes
- Decidir ordem de cutover, preço, customização
- Detectar inadimplência/churn

**Mas legítimo interesse não é cheque em branco** — exige:
- [x] **Necessidade** — só coleta o que é essencial pra decisão de migração (heatmap UI + financeiro). NÃO coleta dados pessoais dos clientes-DOS-clientes (ex: vendas individuais com CPF do consumidor final)
- [x] **Balanceamento** — interesse da WR > impacto no titular? Sim — análise interna, sem compartilhamento externo, dados já estão sob custódia da WR via Firebird hospedado em servidor próprio
- [x] **Transparência** — este documento + README explicam o quê e o porquê. Cliente que pedir esclarecimento via [_OPT-OUT.md](_OPT-OUT.md) recebe resposta
- [x] **Possibilidade de oposição** — cliente pode pedir não-análise → adicionado em [_OPT-OUT.md](_OPT-OUT.md), removido da fila

## 3. Princípios aplicados (Art. 6º LGPD)

| Princípio | Como cumprimos |
|-----------|----------------|
| **Finalidade** | Cada análise tem propósito declarado em README/skill descrição |
| **Adequação** | Skills (financial-snapshot, sells-grade-heatmap) coletam só métricas — não fotos/conteúdo de mensagens |
| **Necessidade** | Queries são `COUNT(*) GROUP BY` — não `SELECT *` em tabelas com PII |
| **Livre acesso** | Cliente que pedir pode receber seu perfil completo |
| **Qualidade** | Dados vêm direto do Firebird do cliente — fonte autoritativa, sem transformação |
| **Transparência** | Este protocolo + relatórios anonimizados em git público (interno) |
| **Segurança** | COM-NOMES gitignored + .gitignore por pasta + senha SYSDBA local na LAN |
| **Prevenção** | Skill `officeimpresso-financial-snapshot` proíbe INSERT/UPDATE/DELETE — só SELECT |
| **Não discriminação** | Análise não classifica cliente em "preferência política/religiosa/etc" — só métricas comerciais |
| **Responsabilização** | Este documento + audit trail via git commits |

## 4. Anonimização canônica

Antes de **qualquer** commit em arquivo público (mesmo que repo interno), aplicar:

### 4.1 Texto livre
- **Razão social** → `Cliente_HASH6` onde `HASH6 = sha1(razao_social.lower())[:6].upper()`
- **CNPJ** → `XX.XXX.XXX/XXXX-XX` (mascarado totalmente)
- **CPF** (raro em B2B) → `XXX.XXX.XXX-XX`
- **Endereço completo** → cidade/UF apenas; nunca rua/número/CEP
- **Telefone/email** → não documentar
- **Nome de pessoa física** (decisor/contato) → função apenas (ex: "diretor financeiro")

### 4.2 Dados agregados
- Receita 12m, MRR, # vendas → OK commitar (números agregados não identificam diretamente)
- Top 30 clientes-do-cliente (skill financial-snapshot) → mascarar nomes via mesmo hash
- Status/situação custom (`SITUACAO` field) → ofuscar via `_situacao_redacted_HASH4_` se texto livre

### 4.3 Códigos internos
- `business_id`, `CODIGO` Firebird, IDs de transação → OK commitar (não vinculam diretamente a pessoa)

### 4.4 Implementação canônica

```python
import hashlib
def anonimize(razao_social: str) -> str:
    if not razao_social:
        return "Cliente_NULL"
    h = hashlib.sha1(razao_social.encode("utf-8")).hexdigest()[:6].upper()
    return f"Cliente_{h}"
```

## 5. Armazenamento

| Local | O que vai | Backup | Retenção |
|-------|-----------|--------|----------|
| Git interno (este repo) | versão anonimizada | git history | indefinido (auditável) |
| `*-COM-NOMES.md` local (gitignored) | versão com nomes | máquina do Wagner | indefinido até cliente migrar OU pedir delete |
| `raw-*.json` local (gitignored) | output bruto Firebird | máquina do Wagner | descartável (regenerável) |
| Vaultwarden | credenciais Firebird (`SYSDBA/masterkey`) | encrypted at rest | até trocar credencial |

**Não armazenamos** em:
- ❌ Cloud público (S3, Google Drive) sem encryption
- ❌ Chat tools (WhatsApp, Slack) — dados sensíveis nunca em ferramenta de mensagem
- ❌ Email — perfil anonimizado pode ir; COM-NOMES nunca

## 6. Direitos do titular (Art. 18 LGPD)

Cliente legacy pode pedir:

| Direito | Como respondemos |
|---------|------------------|
| Confirmação de tratamento | Sim, dizer "fazemos análise interna pra migração" |
| Acesso aos dados | Enviar versão COM-NOMES do perfil + heatmap |
| Correção | Atualizar perfil se cliente apontar erro factual |
| Anonimização | Já feita em commit; podemos hash o nome real localmente também |
| Eliminação | Deletar pasta `NN-slug/` + adicionar a [_OPT-OUT.md](_OPT-OUT.md) |
| Portabilidade | Não aplicável (dado é do cliente, vive no Firebird dele) |
| Revogação de consentimento | Não usamos consentimento como base legal (usamos legítimo interesse) — mas se cliente se opuser explicitamente, paramos e adicionamos a [_OPT-OUT.md](_OPT-OUT.md) |

## 7. Compartilhamento externo

| Cenário | Permitido? | Condição |
|---------|------------|----------|
| Deck investidor com receita agregada | sim | Eliana revisa antes |
| Blog/case study mencionando cliente | só com **consentimento escrito** do cliente | Wagner pede formalmente |
| Dashboard SaaS (feature comercial futura) | sim, **só dados do próprio cliente pro próprio cliente** | autenticação obrigatória |
| Compartilhar perfil COM-NOMES com Felipe/Maiara | sim, contratualmente parte do time | Vaultwarden share |
| Compartilhar com terceiro (consultor, parceiro) | **NÃO** | sem exceção sem ADR |

## 8. Incidente — protocolo

Se algum dado COM-NOMES vazar (commit acidental, compartilhamento errado, etc):

1. **Wagner avisado imediatamente** (não esperar batch end-of-day)
2. **Reverter commit** (git reset / force-push se ainda não pulled por terceiro)
3. **Notificar ANPD** se vazamento é significativo (Art. 48 LGPD — 72h)
4. **Notificar cliente afetado** (Art. 48 LGPD)
5. **Adicionar a [_INCIDENTES-LGPD.md](_INCIDENTES-LGPD.md)** (criar arquivo se ainda não existe)
6. **Retro** — o que falhou no gitignore/processo? PR com fix.

## 9. Auditoria interna trimestral

Wagner + Eliana revisam a cada 3 meses:
- [ ] Quantos perfis novos foram adicionados
- [ ] Algum opt-out chegou? Foi respeitado?
- [ ] Algum incidente?
- [ ] .gitignore continua protegendo? (testar: `git check-ignore` num COM-NOMES)
- [ ] Versão anonimizada está realmente anonimizada? (sample 3 perfis)
- [ ] Skill `officeimpresso-financial-snapshot` SKILL.md ainda diz "apenas SELECT"?

Resultado vai em [_AUDIT-LGPD-YYYY-Qx.md](.) (criar a cada trimestre).

## 10. Treinamento da IA-pair

Claude (IA-pair) é "operador" no sentido LGPD. Antes de qualquer trabalho com dados deste projeto:

- [x] Skill `officeimpresso-financial-snapshot` carregada → conhece as restrições
- [x] CLAUDE.md tem regra "PIIs reais NUNCA em PR ou commit" ([memory/proibicoes.md](../../proibicoes.md))
- [x] Hook `block-automem.ps1` impede commit em paths sensíveis
- [x] Este protocolo é referenciado em todo perfil cliente
- [ ] **Pendente:** Eliana revisar formalmente quando assumir DPO (decisão Wagner 2026-05-09 — não assume ainda)

## 11. Casos especiais

### 11.1 Wagner é cliente de si mesmo (`01-wr-sistemas/`)
Wagner pode falar livremente do próprio negócio — não tem opt-out por consentimento próprio. Mas perfil deve ser anonimizado mesmo assim por consistência (e porque WR Sistemas tem funcionários que poderiam ter direito a privacidade).

### 11.2 Cliente que cancela o serviço OfficeImpresso
Mantemos o perfil por **6 meses** após cancelamento (período de retenção razoável pra reativação, garantias, suporte residual). Depois, ou:
- Cliente **migra pra oimpresso.com** → dados continuam no oimpresso.com novo (governance separada)
- Cliente **vai pra concorrente / fecha empresa** → deletar `NN-slug/` da pasta após 6m + adicionar a [_OPT-OUT.md](_OPT-OUT.md)

### 11.3 Cliente que fala publicamente sobre nossa relação
Se cliente posta no LinkedIn "uso o sistema da WR Sistemas há 20 anos", **isso NÃO autoriza** nós publicarmos perfil dele. Direito de cada parte expressar separadamente.

---

**Última atualização:** 2026-05-11 — protocolo criado em sessão Wagner. Aguarda revisão Eliana quando assumir DPO. Versão preliminar suficiente pra trabalho interno do time.
