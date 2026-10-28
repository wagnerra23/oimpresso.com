// @memcofre
//   tela: /contact/contact-profile
//   module: Crm
//   stories: thread Crm/19 (ManageProfileController@getProfile · Blade → Inertia)
//   permissao: contato logado (CheckContactLogin) — não é tela de operador
//
// Perfil do contato no portal. Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: alvo crm--portal-perfil--index (A3) · prototipo-ui/cowork/Wagner/crm-portal.jsx → view `perfil`
//
// Atrás da flag useV2CrmPortalPerfil. Os dois formulários postam nas rotas de hoje
// (contact-profile-update · contact-password-update); o controller reconhece a visita Inertia e grava
// só o que o portal pode mudar. Nome e CNPJ/CPF são do cadastro fiscal: só leitura.

import { useForm } from '@inertiajs/react';
import { type ChangeEvent, type FormEvent, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import PortalContatoLayout from '../_layout/PortalContatoLayout';

interface Contato {
  nome: string;
  documento: string;
  contato: string;
  mobile: string;
  email: string;
  address_line_1: string;
}

function Campo({ id, rotulo, ajuda, erro, span2, children }: {
  id: string; rotulo: string; ajuda?: string; erro?: string; span2?: boolean; children: ReactNode;
}) {
  return (
    <div className={span2 ? 'span2' : undefined}>
      <Label htmlFor={id}>{rotulo}</Label>
      {children}
      {erro ? <p className="cp-help" role="alert" id={`${id}-erro`}>{erro}</p> : ajuda ? <p className="cp-help">{ajuda}</p> : null}
    </div>
  );
}

function Sec({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <section className="cp-sec">
      <div className="cp-sec-h"><h2>{titulo}</h2></div>
      <div className="cp-sec-b">{children}</div>
    </section>
  );
}

export default function PortalPerfil({ contato }: { contato: Contato }) {
  const dados = useForm({
    contato: contato.contato,
    mobile: contato.mobile,
    email: contato.email,
    address_line_1: contato.address_line_1,
  });
  const senha = useForm({ current_password: '', new_password: '', confirm_password: '' });

  const salvarDados = (e: FormEvent) => {
    e.preventDefault();
    dados.post('/contact/contact-profile-update', { preserveScroll: true });
  };
  const salvarSenha = (e: FormEvent) => {
    e.preventDefault();
    senha.post('/contact/contact-password-update', { preserveScroll: true, onSuccess: () => senha.reset() });
  };
  const campo = (f: typeof dados, k: keyof typeof dados.data) => ({
    id: `perfil-${k}`,
    value: f.data[k],
    onChange: (e: ChangeEvent<HTMLInputElement>) => f.setData(k, e.target.value),
    'aria-invalid': f.errors[k] ? true : undefined,
    'aria-describedby': f.errors[k] ? `perfil-${k}-erro` : undefined,
  });

  return (
    <PortalContatoLayout ativa="perfil" titulo="Meu perfil">
      <div>
        <h1 className="cp-h1">Meu perfil</h1>
        <p className="cp-sub">Nome e CNPJ mudam só pelo balcão — é o cadastro fiscal.</p>
      </div>

      <Sec titulo="Meus dados">
        <form onSubmit={salvarDados}>
          <div className="cp-grid2" data-contract="portal-perfil">
            <Campo id="perfil-nome" rotulo="Nome do contato" ajuda="Muda só pelo balcão.">
              <Input id="perfil-nome" value={contato.nome} readOnly />
            </Campo>
            <Campo id="perfil-documento" rotulo="CNPJ / CPF" ajuda="Cadastro fiscal.">
              <Input id="perfil-documento" value={contato.documento} readOnly />
            </Campo>
            <Campo id="perfil-contato" rotulo="Pessoa de contato" erro={dados.errors.contato}>
              <Input {...campo(dados, 'contato')} />
            </Campo>
            <Campo id="perfil-mobile" rotulo="Telefone" erro={dados.errors.mobile}>
              <Input type="tel" {...campo(dados, 'mobile')} />
            </Campo>
            <Campo id="perfil-email" rotulo="E-mail" erro={dados.errors.email} span2>
              <Input type="email" {...campo(dados, 'email')} />
            </Campo>
            <Campo id="perfil-address_line_1" rotulo="Endereço" erro={dados.errors.address_line_1} span2>
              <Input {...campo(dados, 'address_line_1')} />
            </Campo>
          </div>
          <div className="cp-bar"><div className="sp" /><Button type="submit" disabled={dados.processing}>Atualizar perfil</Button></div>
        </form>
      </Sec>

      <Sec titulo="Trocar senha">
        <form onSubmit={salvarSenha}>
          <div className="cp-grid2" data-contract="portal-senha">
            <Campo id="senha-atual" rotulo="Senha atual" erro={senha.errors.current_password} span2>
              <Input id="senha-atual" type="password" autoComplete="current-password" value={senha.data.current_password}
                onChange={(e) => senha.setData('current_password', e.target.value)}
                aria-invalid={senha.errors.current_password ? true : undefined}
                aria-describedby={senha.errors.current_password ? 'senha-atual-erro' : undefined} />
            </Campo>
            <Campo id="senha-nova" rotulo="Nova senha" erro={senha.errors.new_password}>
              <Input id="senha-nova" type="password" autoComplete="new-password" value={senha.data.new_password}
                onChange={(e) => senha.setData('new_password', e.target.value)} />
            </Campo>
            <Campo id="senha-confirma" rotulo="Confirmar nova senha" erro={senha.errors.confirm_password}>
              <Input id="senha-confirma" type="password" autoComplete="new-password" value={senha.data.confirm_password}
                onChange={(e) => senha.setData('confirm_password', e.target.value)} />
            </Campo>
          </div>
          <div className="cp-bar"><div className="sp" /><Button type="submit" disabled={senha.processing}>Atualizar senha</Button></div>
        </form>
      </Sec>
    </PortalContatoLayout>
  );
}
