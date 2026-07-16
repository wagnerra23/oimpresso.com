#!/usr/bin/env node
import { adversariar } from './adversario-intencao-fluxo.mjs';
let failures = 0;
function check(label, value) { console.log(`${value ? 'OK' : 'X'} ${label}`); if (!value) failures++; }
const contract = { charter: 'Page.charter.md', cobertura_minima: 2, fluxos: [{ id: 'novo', nao_pode_conter: ['/novo'] }] };
check('rota legada é crítica', adversariar(contract, "router.visit('/financeiro/unificado/novo')", 'Index.tsx').some((f) => f.severity === 'critical' && f.line === 1));
check('cobertura menor que mínimo é média', adversariar(contract, '', 'Index.tsx').some((f) => f.code === 'cobertura-insuficiente'));
check('contraprova ausente é média', adversariar({ ...contract, fluxos: [{ id: 'novo' }] }, '', 'Index.tsx').some((f) => f.code === 'sem-contraprova'));
check('fonte limpa não inventa rota', !adversariar({ ...contract, cobertura_minima: 1 }, "setCreateTipo('receber')", 'Index.tsx').some((f) => f.severity === 'critical'));
process.exit(failures ? 1 : 0);
