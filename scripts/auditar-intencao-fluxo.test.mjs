#!/usr/bin/env node
import { auditar, validarContrato } from './auditar-intencao-fluxo.mjs';

let failures = 0;
function check(label, condition) { console.log(`${condition ? 'OK' : 'X'} ${label}`); if (!condition) failures++; }
const contract = { alvo: ['Page.tsx'], fluxos: [{ id: 'novo-titulo', intencao: 'Criar título após escolher o tipo.', deve_conter: ['Novo título', 'Novo recebimento', 'Novo pagamento'], nao_pode_conter: ['/financeiro/unificado/novo'] }] };
check('contrato válido', validarContrato(contract) === null);
check('fonte coerente passa', auditar(contract, 'Novo título Novo recebimento Novo pagamento').length === 0);
check('evidência ausente falha', auditar(contract, 'Novo título Novo recebimento').some((i) => i.kind === 'ausente' && i.literal === 'Novo pagamento'));
check('rota proibida falha', auditar(contract, 'Novo título Novo recebimento Novo pagamento /financeiro/unificado/novo').some((i) => i.kind === 'proibido'));
check('prosa não mascara falha', auditar({ ...contract, fluxos: [{ ...contract.fluxos[0], justificativa: 'exceção aprovada em prosa' }] }, 'Novo título').length === 2);
console.log(failures ? `X ${failures} regressão(ões).` : 'OK auditor de intenção morde e libera certo.');
process.exit(failures ? 1 : 0);
