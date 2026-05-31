#!/usr/bin/env python3
# Garante as 8 keys obrigatórias do charter-gate no frontmatter dos charters
# de design criados em 2026-05-31 (US-TR-309..314). Idempotente: só adiciona o
# que falta, preserva o conteúdo. NÃO mexe em charters já válidos.
import re, sys, pathlib

REQUIRED = ['page', 'component', 'owner', 'status', 'last_validated',
            'parent_module', 'tier', 'charter_version']

# tela .tsx → (page route, parent_module, owner)
TARGETS = {
    'resources/js/Pages/Admin/FeatureFlags/Show.charter.md':
        ('/admin/feature-flags/{key}', 'Admin', 'wagner'),
    'resources/js/Pages/Admin/FeatureFlags/Index.charter.md':
        ('/admin/feature-flags', 'Admin', 'wagner'),
    'resources/js/Pages/superadmin/Usuario360/Index.charter.md':
        ('/superadmin/usuarios', 'Superadmin', 'wagner'),
    'resources/js/Pages/superadmin/Usuario360/Show.charter.md':
        ('/superadmin/usuarios/{id}/360', 'Superadmin', 'wagner'),
    'resources/js/Pages/Settings/PaymentGateways/CnabRetorno.charter.md':
        ('/settings/payment-gateways/{id}/cnab-retorno', 'PaymentGateway', 'wagner'),
    'resources/js/Pages/Financeiro/Advisor/Login.charter.md':
        ('/advisor/login', 'Financeiro', 'eliana'),
    'resources/js/Pages/Financeiro/Advisor/Dashboard.charter.md':
        ('/advisor/dashboard', 'Financeiro', 'eliana'),
    'resources/js/Pages/Financeiro/AssinaturaAtualizar.charter.md':
        ('/financeiro/assinatura/atualizar', 'Financeiro', 'eliana'),
    'resources/js/Pages/Financeiro/Configuracoes/Contador.charter.md':
        ('/financeiro/configuracoes/contador', 'Financeiro', 'eliana'),
}

DEFAULTS = {
    'status': 'draft',
    'last_validated': '"2026-05-31"',
    'tier': 'B',
    'charter_version': '1',
}

def component_for(charter_path):
    return charter_path.replace('.charter.md', '.tsx')

def fix(path_str):
    p = pathlib.Path(path_str)
    if not p.exists():
        print(f'skip (não existe): {path_str}'); return
    text = p.read_text(encoding='utf-8')
    m = re.match(r'^---\n(.*?)\n---\n(.*)$', text, re.S)
    if not m:
        print(f'skip (sem frontmatter): {path_str}'); return
    fm, body = m.group(1), m.group(2)
    lines = fm.split('\n')
    have = {}
    for ln in lines:
        km = re.match(r'^([a-zA-Z_][\w-]*):', ln)
        if km: have[km.group(1)] = True
    page, parent, owner = TARGETS[path_str]
    add = []
    vals = {'page': page, 'component': component_for(path_str),
            'owner': owner, 'parent_module': parent, **DEFAULTS}
    for k in REQUIRED:
        if k not in have:
            add.append(f'{k}: {vals[k]}')
    if not add:
        print(f'ok (já completo): {path_str}'); return
    new_fm = fm.rstrip('\n') + '\n' + '\n'.join(add)
    p.write_text(f'---\n{new_fm}\n---\n{body}', encoding='utf-8', newline='\n')
    print(f'fixed (+{len(add)} keys): {path_str}')

for t in TARGETS:
    fix(t)
