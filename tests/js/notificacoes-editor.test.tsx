// @covers-us US-NOTIF-004
import React from 'react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
const post = vi.fn();
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <>{children}</> }));
vi.mock('@inertiajs/react', () => ({ Deferred: ({ children }: any) => <>{children}</>, router: { post: (...a: any[]) => post(...a) } }));
import Page, { type Props } from '@/Pages/NotificationTemplate/Index';
import { segmentosSms, payload, valoresDe } from '@/Pages/NotificationTemplate/_components/editor-modelo';

const base = { subject: 'Assunto', email_body: '<p>Olá</p>', sms_body: 'SMS', whatsapp_text: 'WA',
  auto_send: 0, auto_send_sms: 0, auto_send_wa_notif: 0, cc: '', bcc: '' };
const tagsVenda = [['{business_name}'], ['{invoice_number}'],
  ['{contact_name}', ...Array.from({ length: 10 }, (_, i) => `{contact_custom_field_${i + 1}}`)]];
const props: Props = {
  general_notifications: {},
  customer_notifications: {
    new_sale: { ...base, name: 'Nova venda', subject: 'Venda {invoice_number}', extra_tags: tagsVenda },
    payment_reminder: { ...base, name: 'Lembrete de pagamento', extra_tags: [['{business_name}']],
      auto_send: 1, auto_send_sms: 1, auto_send_wa_notif: 1 },
    payment_received: { ...base, name: 'Pagamento recebido', extra_tags: [['{business_name}']] },
  },
  supplier_notifications: {},
};
beforeEach(() => post.mockReset());
afterEach(cleanup);
const assunto = () => screen.getByLabelText('Assunto') as HTMLInputElement;
const abrir = (nome: string) => fireEvent.click(screen.getByRole('button', { name: new RegExp(`^${nome}`) }));

it('UC-NOT-04 salvar envia template_data do modelo alterado num POST', () => {
  render(<Page {...props} />);
  fireEvent.change(assunto(), { target: { value: 'Novo assunto' } });
  fireEvent.click(screen.getByRole('button', { name: 'Salvar' }));
  expect(post).toHaveBeenCalledTimes(1);
  const [url, corpo] = post.mock.calls[0];
  expect(url).toBe('/notification-templates');
  expect(Object.keys(corpo.template_data)).toEqual(['new_sale']);
  expect(corpo.template_data.new_sale.subject).toBe('Novo assunto');
  expect(corpo.template_data.new_sale).toHaveProperty('email_body', '<p>Olá</p>');
});

it('UC-NOT-05 três modelos alterados saem num único POST e o cabeçalho conta 3', () => {
  render(<Page {...props} />);
  fireEvent.change(assunto(), { target: { value: 'A' } });
  abrir('Lembrete de pagamento'); fireEvent.change(assunto(), { target: { value: 'B' } });
  abrir('Pagamento recebido'); fireEvent.change(assunto(), { target: { value: 'C' } });
  expect(screen.getByText('3 modelos alterados')).toBeTruthy();
  fireEvent.click(screen.getByRole('button', { name: 'Salvar' }));
  expect(post).toHaveBeenCalledTimes(1);
  expect(Object.keys(post.mock.calls[0][1].template_data).sort()).toEqual(['new_sale', 'payment_received', 'payment_reminder']);
});

it('UC-NOT-06 descartar volta ao estado salvo e zera o contador', () => {
  render(<Page {...props} />);
  fireEvent.change(assunto(), { target: { value: 'Rascunho' } });
  fireEvent.click(screen.getByRole('button', { name: 'Descartar alterações' }));
  expect(assunto().value).toBe('Venda {invoice_number}');
  expect(screen.queryByText(/modelo alterado/)).toBeNull();
  expect((screen.getByRole('button', { name: 'Salvar' }) as HTMLButtonElement).disabled).toBe(true);
});

it('UC-NOT-08 tag entra na posição do cursor do assunto', () => {
  render(<Page {...props} />);
  const el = assunto();
  fireEvent.focus(el); el.setSelectionRange(6, 6);
  fireEvent.click(screen.getByRole('button', { name: '{business_name}' }));
  expect(assunto().value).toBe('Venda {business_name}{invoice_number}');
});

it('UC-NOT-10 tag desconhecida avisa e salvar continua permitido', () => {
  render(<Page {...props} />);
  fireEvent.click(screen.getByRole('button', { name: 'HTML' }));
  fireEvent.change(screen.getByLabelText('Corpo do e-mail (HTML)'), { target: { value: 'Oi {foo_bar}' } });
  expect(screen.getByText('tag não reconhecida: {foo_bar}')).toBeTruthy();
  expect((screen.getByRole('button', { name: 'Salvar' }) as HTMLButtonElement).disabled).toBe(false);
});

it('UC-NOT-11 personalizados ficam recolhidos e expandem os dez', () => {
  render(<Page {...props} />);
  expect(screen.queryByRole('button', { name: '{contact_custom_field_1}' })).toBeNull();
  fireEvent.click(screen.getByRole('button', { name: '+ 10 personalizados' }));
  expect(screen.getByRole('button', { name: '{contact_custom_field_10}' })).toBeTruthy();
});

it('UC-NOT-12 UC-NOT-13 segmentos do SMS: 160 sem acento, 70 com acento', () => {
  expect(segmentosSms('a'.repeat(200))).toEqual({ limite: 160, segmentos: 2, acento: false });
  expect(segmentosSms('ação')).toEqual({ limite: 70, segmentos: 1, acento: true });
  render(<Page {...props} />);
  fireEvent.click(screen.getByRole('button', { name: 'SMS' }));
  fireEvent.change(screen.getByLabelText('Texto do SMS'), { target: { value: 'b'.repeat(200) } });
  expect(screen.getByText('2 SMS · 160 por segmento')).toBeTruthy();
});

it('UC-NOT-16 UC-NOT-17 automáticos: liga no new_sale e desliga os três gravando zero', () => {
  render(<Page {...props} />);
  fireEvent.click(screen.getByRole('switch', { name: 'E-mail' }));
  abrir('Lembrete de pagamento');
  for (const n of ['E-mail', 'SMS', 'WhatsApp']) fireEvent.click(screen.getByRole('switch', { name: n }));
  fireEvent.click(screen.getByRole('button', { name: 'Salvar' }));
  const td = post.mock.calls[0][1].template_data;
  expect(td.new_sale.auto_send).toBe(1);
  expect([td.payment_reminder.auto_send, td.payment_reminder.auto_send_sms, td.payment_reminder.auto_send_wa_notif]).toEqual([0, 0, 0]);
  abrir('Pagamento recebido');
  expect(screen.queryByRole('switch', { name: 'E-mail' })).toBeNull();
});

it('paridade Blade: modelo sem automáticos sai com os três zerados', () => {
  const v = { ...valoresDe({ auto_send: 1, auto_send_sms: 1, auto_send_wa_notif: 1 }) };
  const td = payload({ payment_received: v }).template_data.payment_received;
  expect([td.auto_send, td.auto_send_sms, td.auto_send_wa_notif]).toEqual([0, 0, 0]);
});

it('UC-NOT-22 teste usa a rota existente só com a versão gravada', () => {
  render(<Page {...props} />);
  fireEvent.click(screen.getByRole('button', { name: 'Enviar teste pra mim' }));
  expect(post).toHaveBeenCalledWith('/notification-templates/test', { template_for: 'new_sale', canal: 'email' }, expect.anything());
  fireEvent.change(assunto(), { target: { value: 'x' } });
  expect((screen.getByRole('button', { name: 'Enviar teste pra mim' }) as HTMLButtonElement).disabled).toBe(true);
});
