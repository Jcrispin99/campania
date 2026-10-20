import { StyleSheet, View } from 'react-native';
import { Button, Checkbox, IconButton, Text, TextInput } from 'react-native-paper';
import { type PatientEmail } from './patient-emails';

type Props = {
  value: PatientEmail[];
  onChange: (emails: PatientEmail[]) => void;
  disabled?: boolean;
};

export function PatientEmailEditor({ value, onChange, disabled = false }: Props) {
  return <View style={styles.container}>
    <Text variant="titleSmall">Correos electrónicos</Text>
    {value.map((email, index) => <View key={index} style={styles.row}>
      <TextInput
        mode="outlined"
        label={`Correo electrónico ${index + 1}`}
        accessibilityLabel={`Correo electrónico ${index + 1}`}
        value={email.correo}
        onChangeText={correo => onChange(value.map((item, position) => position === index ? { ...item, correo } : item))}
        placeholder="Sin correo registrado"
        maxLength={254}
        keyboardType="email-address"
        inputMode="email"
        autoCapitalize="none"
        autoCorrect={false}
        disabled={disabled}
      />
      <View style={styles.actions}>
        <Checkbox.Item
          label="Predeterminado"
          accessibilityLabel={`Usar correo ${index + 1} como predeterminado`}
          status={email.predeterminado ? 'checked' : 'unchecked'}
          onPress={() => onChange(value.map((item, position) => ({ ...item, predeterminado: position === index })))}
          disabled={disabled}
          position="leading"
          mode="android"
          style={styles.default}
        />
        <IconButton icon="close" accessibilityLabel={`Quitar correo ${index + 1}`} disabled={disabled} onPress={() => {
          const remaining = value.filter((_, position) => position !== index);
          if (!remaining.length) remaining.push({ correo: '', predeterminado: true });
          if (!remaining.some(item => item.predeterminado)) remaining[0].predeterminado = true;
          onChange(remaining);
        }} />
      </View>
    </View>)}
    <Button icon="plus" mode="outlined" disabled={disabled || value.length >= 50} onPress={() => onChange([...value, { correo: '', predeterminado: false }])}>
      Agregar correo
    </Button>
  </View>;
}

const styles = StyleSheet.create({
  container: { gap: 12 },
  row: { gap: 2 },
  actions: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  default: { paddingLeft: 0, flexShrink: 1 },
});
