import { useEffect, useState } from 'react';
import { ScrollView, StyleSheet } from 'react-native';
import { Button, Dialog, Portal, Text, TextInput } from 'react-native-paper';
import { api } from '../../lib/api';
import { COLORS } from '../../theme/colors';
import { errorMessage, type Patient } from './campania-screen';

type PatientCreateDialogProps = {
  visible: boolean;
  initialQuery: string;
  onDismiss: () => void;
  onCreated: (patient: Patient) => void;
};

export function PatientCreateDialog({ visible, initialQuery, onDismiss, onCreated }: PatientCreateDialogProps) {
  const [name, setName] = useState('');
  const [dni, setDni] = useState('');
  const [age, setAge] = useState('');
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!visible) return;
    const query = initialQuery.trim();
    const isDocument = /^\d+$/.test(query);
    setName(isDocument ? '' : query);
    setDni(isDocument ? query : '');
    setAge('');
    setEmail('');
    setError('');
  }, [visible, initialQuery]);

  async function createPatient() {
    if (saving) return;
    if (!name.trim()) {
      setError('Ingresa el nombre completo.');
      return;
    }
    if (!/^\d{8}$/.test(dni.trim())) {
      setError('El DNI debe tener 8 dígitos.');
      return;
    }

    if (!/^\d{1,3}$/.test(age.trim()) || Number(age) > 120) {
      setError('Ingresa una edad válida entre 0 y 120 años.');
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
      setError('Ingresa un correo electrónico válido.');
      return;
    }

    setError('');
    setSaving(true);
    try {
      const response = await api.post<{ data: Patient }>('/pacientes', {
        nombre_completo: name.trim(),
        dni: dni.trim(),
        edad: Number(age.trim()),
        correo_electronico: email.trim(),
      });
      onCreated(response.data.data);
    } catch (requestError) {
      setError(errorMessage(requestError));
    } finally {
      setSaving(false);
    }
  }

  return (
    <Portal>
      <Dialog visible={visible} onDismiss={() => { if (!saving) onDismiss(); }} dismissable={!saving} style={styles.dialog}>
        <Dialog.Title>Crear paciente</Dialog.Title>
        <Dialog.ScrollArea>
          <ScrollView contentContainerStyle={styles.form} keyboardShouldPersistTaps="handled">
            <TextInput label="Nombre completo *" mode="outlined" value={name} onChangeText={setName} maxLength={255} disabled={saving} autoCapitalize="words" />
            <TextInput label="DNI *" mode="outlined" value={dni} onChangeText={setDni} maxLength={8} keyboardType="number-pad" disabled={saving} />
            <TextInput label="Edad *" mode="outlined" value={age} onChangeText={setAge} maxLength={3} keyboardType="number-pad" inputMode="numeric" disabled={saving} />
            <TextInput label="Correo electrónico *" mode="outlined" value={email} onChangeText={setEmail} maxLength={255} keyboardType="email-address" inputMode="email" autoCapitalize="none" autoCorrect={false} disabled={saving} />
            {error ? <Text accessibilityLiveRegion="polite" style={styles.error}>{error}</Text> : null}
          </ScrollView>
        </Dialog.ScrollArea>
        <Dialog.Actions>
          <Button disabled={saving} onPress={onDismiss}>Cancelar</Button>
          <Button disabled={saving} loading={saving} onPress={() => void createPatient()}>Crear y seleccionar</Button>
        </Dialog.Actions>
      </Dialog>
    </Portal>
  );
}

const styles = StyleSheet.create({
  dialog: { width: '92%', maxWidth: 600, maxHeight: '90%', alignSelf: 'center' },
  form: { paddingVertical: 18, gap: 16 },
  error: { padding: 12, color: COLORS.error, backgroundColor: COLORS.errorContainer, borderRadius: 8 },
});
