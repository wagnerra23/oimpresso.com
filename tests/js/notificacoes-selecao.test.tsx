// @covers-us US-NOTIF-003
import React from 'react';
import { afterEach, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <>{children}</> }));
vi.mock('@inertiajs/react', () => ({ Deferred: ({ children }: any) => <>{children}</> }));
import Page, { type Props } from '@/Pages/NotificationTemplate/Index';
const base = { extra_tags: {}, subject: 'Assunto salvo', email_body: '<p>Olá</p>', sms_body: 'Mensagem SMS',
  whatsapp_text: 'Mensagem WhatsApp', auto_send: 0, auto_send_sms: 0, auto_send_wa_notif: 0, cc: '', bcc: '' };
const props: Props = { general_notifications: { send_ledger: { ...base, name: 'Extrato do cliente' } },
  customer_notifications: { new_sale: { ...base, name: 'Nova venda' } },
  supplier_notifications: { custom_module: { ...base, name: 'Pagamento do módulo' } } };
afterEach(cleanup);
it('UC-NOT-02 seleciona o primeiro modelo e preserva assunto e HTML sem executá-lo', () => {
  render(<Page {...props} />);
  expect(screen.getByRole('heading', { name: 'Extrato do cliente' })).toBeTruthy();
  expect(screen.getByText('Assunto salvo')).toBeTruthy();
  expect(screen.getByText('<p>Olá</p>')).toBeTruthy();
  expect(document.querySelector('pre p')).toBeNull();
});
it('UC-NOT-03 volta ao e-mail ao trocar o modelo depois de abrir SMS', () => {
  render(<Page {...props} />);
  fireEvent.click(screen.getByRole('button', { name: 'Nova venda' }));
  fireEvent.click(screen.getByRole('button', { name: 'SMS' }));
  expect(screen.getByText('Mensagem SMS')).toBeTruthy();
  fireEvent.click(screen.getByRole('button', { name: 'Pagamento do módulo' }));
  expect(screen.getByRole('button', { name: 'E-mail' }).getAttribute('aria-pressed')).toBe('true');
  expect(screen.queryByText('Mensagem SMS')).toBeNull();
});
it('UC-NOT-19 busca por nome sem lista fixa e esconde grupos sem resultados', () => {
  render(<Page {...props} />);
  fireEvent.change(screen.getByRole('textbox', { name: 'Buscar modelos' }), { target: { value: 'pagamento' } });
  expect(screen.getByRole('button', { name: 'Pagamento do módulo' })).toBeTruthy();
  expect(screen.queryByRole('button', { name: 'Nova venda' })).toBeNull();
  expect(screen.queryByRole('heading', { name: 'Cliente' })).toBeNull();
});
it('UC-NOT-20 barra foca a busca, Escape limpa e desfoca, sem roubar digitação', () => {
  const view = render(<Page {...props} />);
  const input = screen.getByRole('textbox', { name: 'Buscar modelos' });
  const outside = document.createElement('input'); document.body.append(outside); outside.focus();
  fireEvent.keyDown(outside, { key: '/' }); expect(document.activeElement).toBe(outside); outside.remove();
  fireEvent.keyDown(document.body, { key: '/' });
  expect(document.activeElement).toBe(input);
  fireEvent.change(input, { target: { value: 'xyz' } });
  expect(screen.getByText('Nenhum modelo encontrado')).toBeTruthy();
  fireEvent.keyDown(input, { key: 'Escape' });
  expect((input as HTMLInputElement).value).toBe('');
  expect(document.activeElement).not.toBe(input);
  view.unmount(); fireEvent.keyDown(document.body, { key: '/' });
  expect(document.activeElement).not.toBe(input);
});
it('UC-NOT-14 extrato recusa SMS/WhatsApp e Editar abre o formulário existente', () => {
  render(<Page {...props} />);
  expect((screen.getByRole('button', { name: 'SMS' }) as HTMLButtonElement).disabled).toBe(true);
  expect((screen.getByRole('button', { name: 'WhatsApp' }) as HTMLButtonElement).disabled).toBe(true);
  expect(screen.getByRole('link', { name: 'Editar modelos' }).getAttribute('href')).toBe('/notification-templates');
});
