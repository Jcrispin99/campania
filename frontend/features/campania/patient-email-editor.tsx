import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { HelperText, IconButton, Text, TextInput } from 'react-native-paper';
import { COLORS } from '../../theme/colors';
import { EMAIL_PATTERN, type PatientEmail } from './patient-emails';

type Props = {
  value: PatientEmail[];
  onChange: (emails: PatientEmail[]) => void;
  disabled?: boolean;
};

export function PatientEmailEditor({ value, onChange, disabled = false }: Props) {
  const [editing, setEditing] = useState<number | null>(null);
  const [error, setError] = useState('');
  const rows = value.filter(email => !email.draft);
  const draft = value.find(email => email.draft)?.correo ?? '';

  // Lo que se está escribiendo viaja en `value` como borrador, así no se pierde si guardan sin pulsar "+".
  function update(nextRows: PatientEmail[], nextDraft = draft) {
    onChange(nextDraft ? [...nextRows, { correo: nextDraft, predeterminado: false, draft: true }] : nextRows);
  }

  function problem(correo: string, index: number | null) {
    if (!EMAIL_PATTERN.test(correo)) return 'Ingresa un correo electrónico válido.';
    if (rows.some((row, position) => position !== index && row.correo.trim().toLowerCase() === correo)) return 'Este correo ya está en la lista.';
    return '';
  }

  function add() {
    const correo = draft.trim().toLowerCase();
    if (!correo) return;
    const message = problem(correo, null);
    setError(message);
    if (!message) update([...rows, { correo, predeterminado: !rows.some(row => row.predeterminado) }], '');
  }

  function remove(index: number) {
    const remaining = rows.filter((_, position) => position !== index);
    if (remaining.length && !remaining.some(row => row.predeterminado)) remaining[0] = { ...remaining[0], predeterminado: true };
    setEditing(null);
    setError('');
    update(remaining);
  }

  function finishEditing(index: number) {
    const correo = rows[index].correo.trim().toLowerCase();
    if (!correo) { remove(index); return; }
    const message = problem(correo, index);
    setError(message);
    if (message) return;
    setEditing(null);
    update(rows.map((row, position) => position === index ? { ...row, correo } : row));
  }

  return <View style={styles.container}>
    <View style={styles.table}>
      <View style={styles.header}>
        <Text style={styles.headerText}>Correos electrónicos</Text>
        <Text style={styles.headerText}>{rows.length}</Text>
      </View>
      {rows.length ? rows.map((email, index) => <View key={email.id ?? `nuevo-${index}`} style={[styles.row, index > 0 && styles.divider]}>
        <IconButton
          icon={email.predeterminado ? 'star' : 'star-outline'}
          iconColor={email.predeterminado ? COLORS.primary : COLORS.textSubtle}
          size={20}
          style={styles.icon}
          accessibilityLabel={email.predeterminado ? `${email.correo} es el correo predeterminado` : `Usar ${email.correo} como predeterminado`}
          disabled={disabled}
          onPress={() => update(rows.map((row, position) => ({ ...row, predeterminado: position === index })))}
        />
        {editing === index ? <TextInput
          mode="outlined"
          dense
          autoFocus
          style={styles.inlineInput}
          accessibilityLabel={`Editar correo ${index + 1}`}
          value={email.correo}
          onChangeText={correo => { setError(''); update(rows.map((row, position) => position === index ? { ...row, correo } : row)); }}
          onSubmitEditing={() => finishEditing(index)}
          maxLength={254}
          keyboardType="email-address"
          inputMode="email"
          autoCapitalize="none"
          autoCorrect={false}
          disabled={disabled}
          right={<TextInput.Icon icon="check" accessibilityLabel="Listo" onPress={() => finishEditing(index)} />}
        /> : <Pressable
          style={styles.email}
          accessibilityRole="button"
          accessibilityLabel={`Editar ${email.correo}`}
          disabled={disabled}
          onPress={() => { setError(''); setEditing(index); }}
        >
          <Text numberOfLines={1}>{email.correo}</Text>
          {email.predeterminado ? <Text style={styles.badge}>Predeterminado</Text> : null}
        </Pressable>}
        <IconButton icon="close" size={18} style={styles.icon} accessibilityLabel={`Quitar ${email.correo || `correo ${index + 1}`}`} disabled={disabled} onPress={() => remove(index)} />
      </View>) : <Text style={styles.empty}>Sin correos registrados</Text>}
    </View>
    <TextInput
      mode="outlined"
      dense
      label="Agregar correo"
      placeholder="nombre@correo.com"
      value={draft}
      onChangeText={correo => { setError(''); update(rows, correo); }}
      onSubmitEditing={add}
      maxLength={254}
      keyboardType="email-address"
      inputMode="email"
      autoCapitalize="none"
      autoCorrect={false}
      disabled={disabled || rows.length >= 50}
      right={<TextInput.Icon icon="plus" accessibilityLabel="Agregar correo" disabled={disabled || !draft.trim()} onPress={add} />}
    />
    {error ? <HelperText type="error" visible style={styles.error}>{error}</HelperText> : null}
  </View>;
}

const styles = StyleSheet.create({
  container: { gap: 8 },
  table: { borderWidth: 1, borderColor: COLORS.border, borderRadius: 8, backgroundColor: COLORS.surface, overflow: 'hidden' },
  header: { flexDirection: 'row', justifyContent: 'space-between', paddingHorizontal: 12, paddingVertical: 8, backgroundColor: COLORS.surfaceSubtle },
  headerText: { color: COLORS.textMuted, fontSize: 10, fontWeight: '800', letterSpacing: 0.5, textTransform: 'uppercase' },
  row: { minHeight: 48, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 2 },
  divider: { borderTopWidth: 1, borderTopColor: COLORS.border },
  icon: { margin: 0 },
  email: { flex: 1, minWidth: 0, paddingVertical: 8, paddingHorizontal: 4, justifyContent: 'center' },
  badge: { color: COLORS.textMuted, fontSize: 12 },
  inlineInput: { flex: 1, minWidth: 0, marginVertical: 6, backgroundColor: COLORS.surface },
  empty: { paddingHorizontal: 12, paddingVertical: 14, color: COLORS.textMuted },
  error: { paddingHorizontal: 0 },
});
