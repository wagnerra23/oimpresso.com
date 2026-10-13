// @covers-us US-NOTIF-004
import React from 'react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
const post = vi.fn();
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <>{children}</> }));
vi.mock('@inertiajs/react', () => ({ Deferred: ({ children }: any) => <>{children}</>, router: { post: (...a: any[]) => post(...a) } }));
import Page, { type Props } from '@/Pages/NotificationTemplate/Index';

const base = { subject: 'Venda {invoice_number}', email_body: '<p>Olá <strong>{contact_name}</strong></p><ul><li>item</li></ul>',
  sms_body: 'SMS', whatsapp_text: 'WA', auto_send: 0, auto_send_sms: 0, auto_send_wa_notif: 0, cc: 'a@b.com', bcc: '' };
const props: Props = {
  general_notifications: {},
  customer_notifications: {
    new_sale: { ...base, name: 'Nova venda', extra_tags: [['{business_name}', '{invoice_number}']] },
    modulo_x: { ...base, name: 'Modelo do módulo', extra_tags: [] },
  },
  supplier_notifications: {},
  padroes: { new_sale: { subject: 'Obrigado — {business_name}', email_body: '<p>Fábrica</p>', sms_body: 'F sms', whatsapp_text: 'F wa' } },
};
beforeEach(() => post.mockReset());
afterEach(cleanup);
const editorVisual = () => (screen.getByTitle('Editor visual do corpo do e-mail') as HTMLIFrameElement).contentDocument!.body;
const previa = () => screen.getByTitle('Prévia do e-mail') as HTMLIFrameElement;
const modo = (nome: 'Visual' | 'HTML') => screen.getByRole('button', { name: nome });

it('UC-NOT-21 Visual mostra o markup salvo e HTML devolve o mesmo markup, editável nos dois sentidos', () => {
  render(<Page {...props} />);
  expect(modo('Visual').getAttribute('aria-pressed')).toBe('true');
  expect(editorVisual().innerHTML).toBe(base.email_body);
  editorVisual().innerHTML = '<p>Novo <em>texto</em></p>';
  fireEvent.input(editorVisual());
  fireEvent.click(modo('HTML'));
  const html = screen.getByLabelText('Corpo do e-mail (HTML)') as HTMLTextAreaElement;
  expect(html.value).toBe('<p>Novo <em>texto</em></p>');
  fireEvent.change(html, { target: { value: '<p>Voltou</p>' } });
  fireEvent.click(modo('Visual'));
  expect(editorVisual().innerHTML).toBe('<p>Voltou</p>');
});

it('UC-NOT-03 trocar de modelo volta o corpo para o modo Visual', () => {
  render(<Page {...props} />);
  fireEvent.click(modo('HTML'));
  fireEvent.click(screen.getByRole('button', { name: /^Modelo do módulo/ }));
  expect(modo('Visual').getAttribute('aria-pressed')).toBe('true');
  expect(screen.queryByLabelText('Corpo do e-mail (HTML)')).toBeNull();
});

it('UC-NOT-09 chip no editor Visual entra no HTML do corpo e a prévia mostra o valor de exemplo', () => {
  render(<Page {...props} />);
  fireEvent.focus(editorVisual());
  fireEvent.click(screen.getByRole('button', { name: '{invoice_number}' }));
  expect(editorVisual().innerHTML).toContain('{invoice_number}');
  expect(previa().getAttribute('srcdoc')).toContain('VD-0001');
  expect(previa().getAttribute('srcdoc')).not.toContain('{invoice_number}');
  expect(screen.getByText('1 modelo alterado')).toBeTruthy();
});

it('UC-NOT-07 restaurar padrão volta ao texto de fábrica, conta como alteração e não grava', () => {
  render(<Page {...props} />);
  fireEvent.click(screen.getByRole('button', { name: 'Restaurar padrão desta' }));
  expect((screen.getByLabelText('Assunto') as HTMLInputElement).value).toBe('Obrigado — {business_name}');
  expect((screen.getByLabelText('Cópia (CC)') as HTMLInputElement).value).toBe('');
  expect(editorVisual().innerHTML).toBe('<p>Fábrica</p>');
  expect(screen.getByText('1 modelo alterado')).toBeTruthy();
  expect(post).not.toHaveBeenCalled();
});

it('UC-NOT-07 modelo sem texto de fábrica deixa Restaurar padrão desabilitado', () => {
  render(<Page {...props} />);
  fireEvent.click(screen.getByRole('button', { name: /^Modelo do módulo/ }));
  expect((screen.getByRole('button', { name: 'Restaurar padrão desta' }) as HTMLButtonElement).disabled).toBe(true);
});

it('prévia e editor Visual rodam em iframe sandbox sem permissão de script', () => {
  render(<Page {...props} />);
  expect(previa().getAttribute('sandbox')).toBe('');
  const sandbox = (screen.getByTitle('Editor visual do corpo do e-mail') as HTMLIFrameElement).getAttribute('sandbox') ?? '';
  expect(sandbox.split(/\s+/)).not.toContain('allow-scripts');
  fireEvent.click(screen.getByRole('button', { name: 'SMS' }));
  expect(screen.queryByTitle('Prévia do e-mail')).toBeNull();
  expect(document.querySelector('[data-contract="previa-texto"]')?.textContent).toBe('SMS');
});
