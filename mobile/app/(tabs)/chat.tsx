import React, { useState, useRef } from 'react';
import { View, Text, ScrollView, Pressable, TextInput, KeyboardAvoidingView, Platform } from 'react-native';
import { OiHeader, OiScreen } from '@/components/oi';
import { Toast } from '@/components/erp-ui';
import { useERP } from '@/lib/erp-context';

interface Mensagem {
  id: string;
  texto: string;
  remetente: 'usuario' | 'sistema';
  data: string;
  hora: string;
}

export default function ChatScreen() {
  const { ui, addToast } = useERP();
  const [mensagens, setMensagens] = useState<Mensagem[]>([
    {
      id: '1',
      texto: 'Olá! Bem-vindo ao Como Oimpresso Mobile. Como posso ajudá-lo?',
      remetente: 'sistema',
      data: new Date().toLocaleDateString('pt-BR'),
      hora: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
    },
  ]);
  const [novaMsg, setNovaMsg] = useState('');
  const scrollViewRef = useRef<ScrollView>(null);

  const handleEnviarMensagem = () => {
    if (!novaMsg.trim()) return;

    const agora = new Date();
    const novaMensagem: Mensagem = {
      id: Date.now().toString(),
      texto: novaMsg.trim(),
      remetente: 'usuario',
      data: agora.toLocaleDateString('pt-BR'),
      hora: agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
    };

    setMensagens([...mensagens, novaMensagem]);
    setNovaMsg('');
    addToast('sucesso', 'Mensagem enviada!');

    // Simular resposta do sistema após 1 segundo
    setTimeout(() => {
      const respostaAgora = new Date();
      const respostaMensagem: Mensagem = {
        id: (Date.now() + 1).toString(),
        texto: getRespostaAutomatica(novaMsg),
        remetente: 'sistema',
        data: respostaAgora.toLocaleDateString('pt-BR'),
        hora: respostaAgora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
      };
      setMensagens(prev => [...prev, respostaMensagem]);
    }, 1000);

    // Scroll automático para a última mensagem
    setTimeout(() => {
      scrollViewRef.current?.scrollToEnd({ animated: true });
    }, 100);
  };

  const getRespostaAutomatica = (texto: string): string => {
    const textoLower = texto.toLowerCase();

    if (textoLower.includes('venda') || textoLower.includes('pedido')) {
      return 'Para gerenciar vendas, acesse a aba "Vendas". Lá você pode criar novos pedidos, acompanhar o status e atualizar informações.';
    }
    if (textoLower.includes('produto')) {
      return 'Na aba "Produtos" você pode cadastrar novos produtos, editar informações e gerenciar o estoque.';
    }
    if (textoLower.includes('produção') || textoLower.includes('op')) {
      return 'A aba "Produção" permite gerar OPs a partir de pedidos aprovados e acompanhar o status de cada operação.';
    }
    if (textoLower.includes('financeiro') || textoLower.includes('receita') || textoLower.includes('despesa')) {
      return 'No "Financeiro" você pode registrar receitas e despesas, visualizar o saldo líquido e filtrar transações por tipo.';
    }
    if (textoLower.includes('ajuda') || textoLower.includes('help')) {
      return 'Posso ajudá-lo com: Vendas, Produtos, Produção, Financeiro. Digite uma dessas palavras para mais informações!';
    }
    if (textoLower.includes('olá') || textoLower.includes('oi')) {
      return 'Olá! Tudo bem? Como posso ajudá-lo hoje?';
    }

    return 'Entendi sua mensagem. Para mais informações, digite "ajuda" ou escolha uma das abas do aplicativo.';
  };

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Chat de Suporte"
        eyebrow="Assistente disponível 24/7"
      />
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} className="flex-1">

        {/* Toasts */}
        <View className="px-4 pt-2">
          {ui.toasts.map((toast) => (
            <Toast key={toast.id} tipo={toast.tipo} mensagem={toast.mensagem} />
          ))}
        </View>

        {/* Mensagens */}
        <ScrollView
          ref={scrollViewRef}
          className="flex-1 px-4 py-4"
          contentContainerStyle={{ paddingBottom: 10 }}
          onContentSizeChange={() => scrollViewRef.current?.scrollToEnd({ animated: true })}
        >
          {mensagens.map(msg => (
            <View
              key={msg.id}
              className={`mb-3 flex-row ${msg.remetente === 'usuario' ? 'justify-end' : 'justify-start'}`}
            >
              <View
                className={`max-w-xs px-4 py-2 rounded-lg ${
                  msg.remetente === 'usuario'
                    ? 'bg-primary rounded-br-none'
                    : 'bg-surface border border-border rounded-bl-none'
                }`}
              >
                <Text className={`text-sm ${msg.remetente === 'usuario' ? 'text-white' : 'text-foreground'}`}>
                  {msg.texto}
                </Text>
                <Text className={`text-xs mt-1 ${msg.remetente === 'usuario' ? 'text-white/70' : 'text-muted'}`}>
                  {msg.hora}
                </Text>
              </View>
            </View>
          ))}
        </ScrollView>

        {/* Input de Mensagem */}
        <View className="px-4 py-4 border-t border-border bg-surface">
          <View className="flex-row gap-2 items-center">
            <TextInput
              className="flex-1 bg-background border border-border rounded-lg px-3 py-2 text-foreground text-sm"
              placeholder="Digite sua mensagem..."
              placeholderTextColor="#9BA1A6"
              value={novaMsg}
              onChangeText={setNovaMsg}
              multiline
              maxLength={500}
              returnKeyType="send"
              onSubmitEditing={handleEnviarMensagem}
            />
            <Pressable
              onPress={handleEnviarMensagem}
              className="bg-primary px-4 py-2 rounded-lg justify-center items-center"
              disabled={!novaMsg.trim()}
            >
              <Text className="text-white font-bold text-lg">→</Text>
            </Pressable>
          </View>
          <Text className="text-xs text-muted mt-2">
            {novaMsg.length}/500
          </Text>
        </View>
      </KeyboardAvoidingView>
    </OiScreen>
  );
}
