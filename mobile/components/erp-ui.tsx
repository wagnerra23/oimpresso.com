import React, { useState } from 'react';
import { View, Text, Pressable, TextInput, ScrollView, FlatList, Modal, StyleSheet } from 'react-native';
import { cn } from '@/lib/utils';
import { useColors } from '@/hooks/use-colors';

// Toast Component
export function Toast({ tipo, mensagem }: { tipo: 'sucesso' | 'erro' | 'info'; mensagem: string }) {
  const colors = useColors();
  const bgColor = tipo === 'sucesso' ? '#EAF3DE' : tipo === 'erro' ? '#FCEBEB' : '#E6F1FB';
  const textColor = tipo === 'sucesso' ? '#27500A' : tipo === 'erro' ? '#791F1F' : '#0C447C';
  const icon = tipo === 'sucesso' ? '✓' : tipo === 'erro' ? '✗' : 'ℹ';

  return (
    <View className="flex-row items-center gap-3 px-4 py-3 rounded-lg mb-2" style={{ backgroundColor: bgColor }}>
      <Text style={{ color: textColor, fontSize: 18, fontWeight: 'bold' }}>{icon}</Text>
      <Text style={{ color: textColor, flex: 1 }} className="text-sm">{mensagem}</Text>
    </View>
  );
}

// Badge Component
export function Badge({ count, label }: { count: number; label: string }) {
  return (
    <View className="flex-row items-center gap-1">
      <Text className="text-sm font-medium text-foreground">{label}</Text>
      <View className="bg-primary rounded-full px-2 py-0.5">
        <Text className="text-xs font-bold text-white">{count}</Text>
      </View>
    </View>
  );
}

// Metric Card Component
export function MetricCard({ label, value, icon }: { label: string; value: string | number; icon?: string }) {
  const colors = useColors();
  return (
    <View className="bg-surface rounded-lg p-4 flex-1">
      <Text className="text-xs font-medium text-muted mb-2">{label}</Text>
      <Text className="text-2xl font-bold text-foreground">{value}</Text>
    </View>
  );
}

// Form Input Component
interface FormInputProps {
  label: string;
  placeholder: string;
  value: string;
  onChangeText: (text: string) => void;
  error?: string;
  required?: boolean;
  keyboardType?: 'default' | 'numeric' | 'email-address';
  // F1-06: enable closing the keyboard from the form. Defaults to "done"
  // so any form input that doesn't explicitly opt out gets the right key.
  returnKeyType?: 'done' | 'next' | 'search' | 'go' | 'send' | 'default';
  onSubmitEditing?: () => void;
  secureTextEntry?: boolean;
  autoCapitalize?: 'none' | 'sentences' | 'words' | 'characters';
}

export function FormInput({
  label,
  placeholder,
  value,
  onChangeText,
  error,
  required,
  keyboardType = 'default',
  returnKeyType = 'done',
  onSubmitEditing,
  secureTextEntry = false,
  autoCapitalize,
}: FormInputProps) {
  const colors = useColors();
  return (
    <View className="mb-4">
      <View className="flex-row items-center gap-1 mb-2">
        <Text className="text-sm font-medium text-foreground">{label}</Text>
        {required && <Text className="text-error text-sm">*</Text>}
      </View>
      <TextInput
        placeholder={placeholder}
        value={value}
        onChangeText={onChangeText}
        keyboardType={keyboardType}
        returnKeyType={returnKeyType}
        onSubmitEditing={onSubmitEditing}
        secureTextEntry={secureTextEntry}
        autoCapitalize={autoCapitalize ?? (keyboardType === 'email-address' || secureTextEntry ? 'none' : undefined)}
        autoCorrect={!secureTextEntry && keyboardType !== 'email-address'}
        className={cn(
          'border rounded-lg px-3 py-2 text-foreground',
          error ? 'border-error bg-error/10' : 'border-border'
        )}
        placeholderTextColor={colors.muted}
      />
      {error && <Text className="text-error text-xs mt-1">{error}</Text>}
    </View>
  );
}

// Select Component
interface SelectOption {
  label: string;
  value: string;
}

interface SelectProps {
  label: string;
  options: SelectOption[];
  value: string;
  onValueChange: (value: string) => void;
  error?: string;
  required?: boolean;
}

export function Select({ label, options, value, onValueChange, error, required }: SelectProps) {
  const [open, setOpen] = useState(false);
  const colors = useColors();

  return (
    <View className="mb-4">
      <View className="flex-row items-center gap-1 mb-2">
        <Text className="text-sm font-medium text-foreground">{label}</Text>
        {required && <Text className="text-error text-sm">*</Text>}
      </View>
      <Pressable
        onPress={() => setOpen(!open)}
        className={cn(
          'border rounded-lg px-3 py-2 flex-row justify-between items-center',
          error ? 'border-error bg-error/10' : 'border-border'
        )}
      >
        <Text className="text-foreground">{options.find(o => o.value === value)?.label || 'Selecione...'}</Text>
        <Text className="text-muted">▼</Text>
      </Pressable>
      {open && (
        <View className="border border-border rounded-lg mt-2 bg-surface">
          {options.map(option => (
            <Pressable
              key={option.value}
              onPress={() => {
                onValueChange(option.value);
                setOpen(false);
              }}
              className="px-3 py-2 border-b border-border"
            >
              <Text className="text-foreground">{option.label}</Text>
            </Pressable>
          ))}
        </View>
      )}
      {error && <Text className="text-error text-xs mt-1">{error}</Text>}
    </View>
  );
}

// Button Component
interface ButtonProps {
  title: string;
  onPress: () => void;
  variant?: 'primary' | 'secondary' | 'danger';
  disabled?: boolean;
}

export function Button({ title, onPress, variant = 'primary', disabled = false }: ButtonProps) {
  const colors = useColors();
  const bgColor = variant === 'primary' ? colors.primary : variant === 'danger' ? colors.error : colors.surface;
  const textColor = variant === 'primary' ? 'white' : colors.foreground;

  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      className="py-3 px-4 rounded-lg"
      style={({ pressed }) => [
        { backgroundColor: bgColor, opacity: pressed || disabled ? 0.7 : 1 }
      ]}
    >
      <Text className="text-center font-semibold" style={{ color: textColor }}>{title}</Text>
    </Pressable>
  );
}

// Modal Dialog Component
interface ModalDialogProps {
  visible: boolean;
  title: string;
  children: React.ReactNode;
  onClose: () => void;
  onConfirm?: () => void;
  confirmText?: string;
  cancelText?: string;
  showConfirmation?: boolean;
}

export function ModalDialog({
  visible,
  title,
  children,
  onClose,
  onConfirm,
  confirmText = 'Salvar',
  cancelText = 'Cancelar',
  showConfirmation = true
}: ModalDialogProps) {
  const colors = useColors();

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={onClose}
    >
      <Pressable
        className="flex-1 bg-black/50 justify-end"
        onPress={onClose}
      >
        <Pressable
          onPress={(e) => e.stopPropagation()}
          className="bg-background rounded-t-2xl p-6"
        >
          <Text className="text-lg font-bold text-foreground mb-4">{title}</Text>
          <ScrollView className="mb-4 max-h-96">
            {children}
          </ScrollView>
          {showConfirmation && (
            <View className="flex-row gap-3">
              <Button
                title={cancelText}
                onPress={onClose}
                variant="secondary"
              />
              {onConfirm && (
                <Button
                  title={confirmText}
                  onPress={onConfirm}
                  variant="primary"
                />
              )}
            </View>
          )}
        </Pressable>
      </Pressable>
    </Modal>
  );
}

// Kanban Card Component
interface KanbanCardProps {
  id: string;
  title: string;
  subtitle: string;
  status: string;
  onPress?: () => void;
  onDelete?: () => void;
  originInfo?: string;
}

export function KanbanCard({ id, title, subtitle, status, onPress, onDelete, originInfo }: KanbanCardProps) {
  const colors = useColors();
  const pillColors: Record<string, { bg: string; text: string }> = {
    'novo': { bg: '#E6F1FB', text: '#0C447C' },
    'aprovado': { bg: '#EEEDFE', text: '#3C3489' },
    'execucao': { bg: '#FAEEDA', text: '#633806' },
    'entregue': { bg: '#EAF3DE', text: '#27500A' },
    'fila': { bg: '#E6F1FB', text: '#0C447C' },
    'andamento': { bg: '#FAEEDA', text: '#633806' },
    'revisao': { bg: '#EEEDFE', text: '#3C3489' },
    'concluido': { bg: '#EAF3DE', text: '#27500A' },
  };

  const pill = pillColors[status] || pillColors['novo'];

  return (
    <Pressable
      onPress={onPress}
      className="bg-surface rounded-lg p-3 mb-2 border border-border"
    >
      <View className="flex-row justify-between items-start mb-2">
        <View className="flex-1">
          <Text className="font-semibold text-foreground text-sm">{title}</Text>
          <Text className="text-xs text-muted mt-1">{subtitle}</Text>
        </View>
        {onDelete && (
          <Pressable onPress={onDelete} className="p-1">
            <Text className="text-error text-lg">×</Text>
          </Pressable>
        )}
      </View>
      <View className="flex-row items-center justify-between">
        <View style={{ backgroundColor: pill.bg }} className="px-2 py-1 rounded">
          <Text style={{ color: pill.text }} className="text-xs font-medium">{status}</Text>
        </View>
        {originInfo && (
          <Text className="text-xs text-muted">{originInfo}</Text>
        )}
      </View>
    </Pressable>
  );
}

// Table Component
interface TableColumn {
  key: string;
  label: string;
  width?: number;
  sortable?: boolean;
}

interface TableProps {
  columns: TableColumn[];
  data: any[];
  onSort?: (key: string) => void;
  renderCell?: (key: string, value: any, row: any) => React.ReactNode;
}

export function Table({ columns, data, onSort, renderCell }: TableProps) {
  const colors = useColors();

  return (
    <ScrollView horizontal>
      <View>
        {/* Header */}
        <View className="flex-row border-b border-border bg-surface">
          {columns.map(col => (
            <Pressable
              key={col.key}
              onPress={() => col.sortable && onSort?.(col.key)}
              style={{ width: col.width || 100 }}
              className="px-3 py-2"
            >
              <Text className="font-semibold text-foreground text-xs">{col.label}</Text>
            </Pressable>
          ))}
        </View>
        {/* Rows */}
        {data.map((row, idx) => (
          <View key={idx} className="flex-row border-b border-border">
            {columns.map(col => (
              <View
                key={col.key}
                style={{ width: col.width || 100 }}
                className="px-3 py-2 justify-center"
              >
                <Text className="text-foreground text-sm">
                  {renderCell ? renderCell(col.key, row[col.key], row) : row[col.key]}
                </Text>
              </View>
            ))}
          </View>
        ))}
      </View>
    </ScrollView>
  );
}
