# Patrimônio — casos de uso

> Destino no SSOT: `resources/js/Pages/AssetManagement/Patrimonio.casos.md`. Par do `Patrimonio.charter.md`.
> Formato: UC numerado, ator, pré, passos, pós, e o que a tela mostra quando dá errado.

---

## UC-01 · Cadastrar um bem
**Ator:** Wagner (`gestor`) · **Pré:** permissão `asset.create`.
1. Header → "Adicionar recurso".
2. Código PAT- já vem preenchido pela sequência da empresa.
3. Preenche nome, categoria, local, valor unitário, quantidade, data da compra.
4. Marca "é atribuível?" se for equipamento de mão.
5. Informa período de garantia em meses + início → o fim é calculado.
6. "Cadastrar bem".

**Pós:** bem na lista; auditoria registra "Bem criado".
**Erro:** nome vazio, valor ≤ 0 ou quantidade < 1 → erro inline no campo, foco preservado, nada é salvo.

---

## UC-02 · Alocar equipamento a um colaborador
**Ator:** Larissa (`operador`) · **Pré:** bem atribuível com saldo livre em local permitido.
1. Header → "Alocar recurso" (ou Ações → Alocar, na linha).
2. Lista mostra só o que tem saldo, com o número de unidades livres.
3. Escolhe colaborador, quantidade, data de início, data-limite (ou indeterminado) e **razão**.
4. "Alocar".

**Pós:** ALO- criado; saldo livre cai; auditoria registra quem recebeu.
**Erro:** quantidade acima do saldo → "Só há N unidade(s) livre(s)". Razão vazia bloqueia — é ela que dá rastro.
**Vazio:** nenhum bem elegível → a tela explica os três motivos (não atribuível, sem saldo, local não permitido).

---

## UC-03 · Revogar uma alocação
**Ator:** Wagner (`gestor`) · **Pré:** alocação ativa, permissão `asset.revoke`.
1. Alocações → "Revogar" na linha (ou pelo drawer do bem).
2. Confirma quantidade (padrão: tudo), data e razão.

**Pós:** REV- vinculado à alocação original — que **não é apagada**; unidade volta ao saldo.
**Sem permissão:** Larissa vê "—" no lugar do botão.

---

## UC-04 · Enviar bem pra manutenção e concluir
**Ator:** Wagner ou Larissa · **Pré:** `asset.maintenance.create`.
1. Ações → "Enviar pra manutenção".
2. Prestador, responsável, data de envio, previsão de devolução, custo adicional, nota.
3. Data futura → **agendada**; hoje ou passada → **em manutenção**.
4. Depois: Manutenções → "Concluir".

**Pós:** situação do bem vira "manutenção" na lista; ao concluir, cria **título a pagar** no Financeiro (TIT-) clicável.
**Restrição:** Larissa só vê as manutenções em que é responsável.

---

## UC-05 · Descobrir o que está sem cobertura de garantia
**Ator:** Wagner.
1. Painel → chip "Garantia crítica" (ou análise "Situação da garantia" → drill).
2. Cai na lista já filtrada; abre o bem → aba Garantia.

**Pós:** decide contrato de suporte antes do próximo conserto.
**Regra visível:** bem sem garantia registrada aparece como "sem garantia", nunca como "vencida".

---

## UC-06 · Excluir um bem
**Ator:** Wagner · **Pré:** `asset.delete`.
1. Ações → Excluir → confirmação nomeia o bem e o valor.
2. Confirma.

**Pós:** sai da lista; auditoria mantém o registro.
**Bloqueio:** com alocação ativa o botão fica desabilitado e a tela manda revogar antes.

---

## UC-07 · Prestar conta do patrimônio
**Ator:** Eliana (`financeiro`).
1. Bens → escolhe colunas → "↓ CSV" (baixa o que está filtrado, nas colunas visíveis).
2. Confere valor de aquisição × residual no rodapé.

**Restrição:** não aloca, não edita, não abre Configurações — a tela diz por quê.

---

## UC-08 · Devolver ferramenta no tablet
**Ator:** Marcos (técnico), em pé, na oficina.
1. Tweak "Alvo de toque: tablet" → todo alvo ≥44px.
2. Alocações → Revogar.

**Nota:** nenhuma ação depende de hover.

---

## UC-09 · Auditar quem mexeu no quê
**Ator:** Wagner, depois de uma divergência.
1. Aba Auditoria (ou drawer do bem → Histórico).
2. Lê evento, quem, quando e o de→para dos campos.

**Regra:** só campos da whitelist. A descrição não aparece — por decisão de LGPD, não por falha.

---

## UC-10 · Tela em erro
**Ator:** qualquer um.
1. Consulta falha (tweak "Estado: erro").
2. A tela diz que **nada foi perdido**, sugere reapurar e cita a causa provável (assinatura sem `assetmanagement_module`).
