# PROPOSTA — tela de Licenças e equipamentos (WR Comercial / Officeimpresso)

> **De:** [CC] · **Para:** [W] (decisão de escopo) e [CL] (execução, se aprovado) · **Data:** 2026-08-19
> **Origem:** ao importar o Conector, o `connector:health` conta "licenças com acesso em 24 h" — mas **não existe tela** pra consultar licença/equipamento em módulo nenhum. O dado é gravado pela API e só é lido por consulta ao banco.
> **Lido no `main` NESTE turno:** `Modules/Connector/Http/Controllers/Api/LicencaComputadorController.php`, `Http/Requests/StoreLicencaComputadorRequest.php`, `Routes/api.php`, `Services/DelphiSyncService.php`, `Console/Commands/ConnectorHealthCommand.php`.
> **Dono do modelo:** `Modules\Officeimpresso\Entities\Licenca_Computador` — **é outro módulo**. Por isso isto é proposta, não onda do Conector.

## O que existe hoje

O desktop (WR Comercial / Delphi) bate em `connector/api/processa-dados-cliente` no boot. O backend resolve o negócio pelo CNPJ ou pelo serial do HD, grava/atualiza a linha em `licenca_computador` e responde a string literal `S;Cliente e equipamento liberados` ou `N;<motivo>`.

A linha guarda muito mais do que licença: `hd`, `user_win`, `hostname`, `ip_interno`, `sistema`, `sistema_operacional`, `versao_exe`, `versao_banco`, `processador`, `memoria`, `antivirus`, `pasta_instalacao`, `caminho_banco`, `impressora_fiscal`, `leitor_barras`, `paf`, `backup_automatico`, `velocidade_conexao`, `dt_cadastro`, `dt_ultimo_acesso`, `dt_ultima_assistencia`, `dt_validade`, `serial`, `contra_senha`, `valor`, `gera_mensalidade`, `bloqueado`, `liberado`, `motivo`.

Ou seja: **o parque de máquinas dos clientes está inventariado no banco e ninguém consegue olhar.**

## Achados críticos desta leitura (independem da tela)

| # | Achado | Prova | Gravidade |
|---|---|---|---|
| L1 | `index()` faz `Licenca_Computador::all()` sob `auth:api` — **devolve equipamento de todos os negócios** pra qualquer token válido | `LicencaComputadorController::index` | 🔴 Tier 0 (ADR 0093): vazamento cross-tenant |
| L2 | `senha` e `contra_senha` chegam no payload e são gravadas na tabela | `saveEquipamento` | 🔴 credencial de banco do cliente em claro |
| L3 | Equipamento novo nasce `bloqueado = true` / `liberado = 'N'`, e **não existe tela** pra liberar | `saveEquipamento` | 🟠 liberação hoje é UPDATE manual no banco |
| L4 | Um mesmo HD pode estar em N negócios; o fluxo rápido atualiza **todas** as linhas e recusa se qualquer uma estiver bloqueada | `processarApenasHd` | 🟠 regra real, invisível pro suporte |
| L5 | `dt_validade`, `gera_mensalidade` e `valor` existem — licença tem vencimento e cobrança — sem nenhuma tela ou rotina de aviso | colunas do Model | 🟠 receita e vencimento cegos |
| L6 | `update()` exige `licenca_id` existente em `licenca` e `hd` único; o cadastro pela API não valida nada disso | `update` × `saveEquipamento` | 🟡 duas leis pro mesmo registro |
| L7 | `destroy()` é hard delete de equipamento, sem escopo de negócio | `destroy` | 🟠 apaga histórico de qualquer cliente |

L1 e L2 são conserto de segurança e **não deveriam esperar pela tela**.

## Tela proposta (se [W] aprovar o escopo)

Módulo **Officeimpresso** (dono do modelo), rota `licencas`, PT-01 + PT-02:

- **Índice** — uma linha por equipamento: negócio, `user_win`/`hostname`, HD (mono), versão do executável e do banco, último acesso com frescor (recente/fresco/frio), validade e situação (liberado · bloqueado com motivo).
- **KPI-filtros** — em campo hoje · sem acesso há 7 dias · bloqueados · vencendo em 30 dias.
- **Ação que falta hoje**: liberar/bloquear com motivo obrigatório (o L3 sai do banco pra tela).
- **Aviso do HD compartilhado** (L4): quando o mesmo serial aparece em mais de um negócio, a linha diz isso e a ação avisa que vale pra todas as instâncias.
- **Drawer (PT-02)** — ficha da máquina: hardware, sistema, periféricos fiscais, caminho do banco, backup automático, última assistência; e o histórico de acessos.
- **Nunca exibir** `senha`/`contra_senha` — e mais: por decisão de [W] esses campos **saem do payload e da tabela** (L2). A tela declara isso em vez de mascarar.

## Decidido por [W] em 2026-08-19

1. **Escopo:** a tela nasce no **módulo Officeimpresso** — suporte de equipamento é assunto diferente da API. O Conector só recebe o ping.
2. **Permissão:** **permissão própria do suporte**, não superadmin. (Emitir credencial de API continua superadmin; liberar máquina de cliente é trabalho de suporte.)
3. **Segredos:** vale aqui a mesma lei da API ([W] D6) — ninguém vê senha, nem o administrador. `senha` e `contra_senha` **saem do payload e da tabela**; a tela nunca os exibe.

## Ainda em aberto

- **L1 e L2 como conserto imediato**, fora da fila da tela? (recomendo sim — é vazamento cross-tenant e credencial em claro)
- **Cobrança por equipamento** (`gera_mensalidade`, `valor`, `dt_validade`): nesta tela ou no Financeiro/Superadmin?
