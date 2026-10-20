import { useEffect, useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { ActivityIndicator, Button, List, Text, TextInput } from 'react-native-paper';
import { api } from '../../lib/api';
import { COLORS } from '../../theme/colors';
import { errorMessage, localDate, type Patient } from './campania-screen';
import { PatientCreateDialog } from './patient-create-dialog';

type PatientSearchResponse = {
  data: { items: Patient[]; total: number };
};

export function AttentionScreen() {
  const [query, setQuery] = useState('');
  const [matches, setMatches] = useState<Patient[]>([]);
  const [total, setTotal] = useState(0);
  const [patient, setPatient] = useState<Patient | null>(null);
  const [result, setResult] = useState('');
  const [searching, setSearching] = useState(false);
  const [saving, setSaving] = useState(false);
  const [searchError, setSearchError] = useState('');
  const [saveError, setSaveError] = useState('');
  const [notice, setNotice] = useState('');
  const [createVisible, setCreateVisible] = useState(false);
  const [completedQuery, setCompletedQuery] = useState('');

  useEffect(() => {
    const search = query.trim();
    if (patient || search.length < 2) {
      setMatches([]);
      setTotal(0);
      setSearching(false);
      return;
    }

    const controller = new AbortController();
    setSearching(true);
    setSearchError('');
    const timeout = setTimeout(() => {
      api.get<PatientSearchResponse>('/pacientes', {
        params: { search }, signal: controller.signal,
      }).then(response => {
        setCompletedQuery(search);
        setMatches(response.data.data.items);
        setTotal(response.data.data.total);
      }).catch(error => {
        if (!controller.signal.aborted) setSearchError(errorMessage(error));
      }).finally(() => {
        if (!controller.signal.aborted) setSearching(false);
      });
    }, 300);

    return () => {
      clearTimeout(timeout);
      controller.abort();
    };
  }, [query, patient]);

  function changeQuery(value: string) {
    setQuery(value);
    setCompletedQuery('');
    setPatient(null);
    setMatches([]);
    setSearchError('');
    setSaveError('');
    setNotice('');
  }

  function selectPatient(selected: Patient) {
    setPatient(selected);
    setMatches([]);
    setSaveError('');
    setNotice('');
  }

  async function save() {
    if (saving) return;
    if (!patient) {
      setSaveError('Selecciona un paciente.');
      return;
    }
    if (!result.trim()) {
      setSaveError('Ingresa el resultado.');
      return;
    }

    setSaving(true);
    setSaveError('');
    setNotice('');
    try {
      await api.post('/atenciones', {
        dni: patient.dni,
        valor_1: result.trim(),
        fecha: localDate(),
      });
      setNotice(`Atención guardada para ${patient.nombre_completo}.`);
      setResult('');
      setQuery('');
      setPatient(null);
    } catch (error) {
      setSaveError(errorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  return (
    <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={styles.container}>
      <View style={styles.form}>
        <Text variant="headlineSmall">Atención</Text>
        <Text style={styles.help}>Selecciona al paciente, ingresa su resultado y guarda la atención.</Text>
        {notice ? <Text accessibilityLiveRegion="polite" style={styles.success}>{notice}</Text> : null}

        <TextInput
          mode="outlined"
          label="Buscar paciente por nombre o DNI"
          value={query}
          onChangeText={changeQuery}
          disabled={saving || createVisible}
          autoCapitalize="none"
          right={<TextInput.Icon icon="magnify" />}
        />
        {searching ? <ActivityIndicator accessibilityLabel="Buscando pacientes" /> : null}
        {searchError ? <Text accessibilityLiveRegion="polite" style={styles.error}>{searchError}</Text> : null}
        {!patient && query.trim().length >= 2 && completedQuery === query.trim() && !searching && !searchError ? (
          <View style={styles.results}>
            {matches.length ? matches.map(item => (
              <List.Item
                key={item.id}
                title={String(item.nombre_completo)}
                description={`DNI: ${item.dni}${item.nombre_area ? ` · ${item.nombre_area}` : ''}`}
                onPress={() => selectPatient(item)}
                accessibilityLabel={`Seleccionar a ${item.nombre_completo}, DNI ${item.dni}`}
                left={props => <List.Icon {...props} icon="account-outline" />}
                right={props => <List.Icon {...props} icon="chevron-right" />}
              />
            )) : <View style={styles.emptyState}>
              <Text>No se encontró un paciente con “{query.trim()}”.</Text>
              <Button icon="account-plus-outline" mode="outlined" onPress={() => setCreateVisible(true)}>
                Crear paciente
              </Button>
            </View>}
            {total > matches.length ? <Text style={styles.empty}>Hay {total} coincidencias. Escribe más letras o el DNI para precisar la búsqueda.</Text> : null}
          </View>
        ) : null}
        {!patient && query.trim().length < 2 ? <Text style={styles.help}>Escribe al menos 2 letras o dígitos para buscar.</Text> : null}

        {patient ? (
          <View style={styles.selected}>
            <Text variant="titleMedium">{patient.nombre_completo}</Text>
            <Text>DNI: {patient.dni}</Text>
            {patient.nombre_area ? <Text>{patient.nombre_area}</Text> : null}
            <Button disabled={saving} onPress={() => { changeQuery(''); setResult(''); }}>Cambiar paciente</Button>
          </View>
        ) : null}
        <TextInput
          mode="outlined"
          label="Resultado"
          value={result}
          onChangeText={value => { setResult(value); setSaveError(''); }}
          placeholder="Escribe el resultado del paciente"
          multiline
          numberOfLines={4}
          maxLength={255}
          disabled={saving || !patient}
        />
        {saveError ? <Text accessibilityLiveRegion="polite" style={styles.error}>{saveError}</Text> : null}
        <Button mode="contained" icon="content-save-outline" loading={saving} disabled={saving || !patient || !result.trim()} onPress={() => void save()}>
          Guardar atención
        </Button>
      </View>
      <PatientCreateDialog
        visible={createVisible}
        initialQuery={query}
        onDismiss={() => setCreateVisible(false)}
        onCreated={created => {
          selectPatient(created);
          setCreateVisible(false);
          setQuery(String(created.nombre_completo));
          setNotice('Paciente creado. Ya puedes ingresar su resultado.');
        }}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flexGrow: 1, padding: 20, alignItems: 'center' },
  form: { width: '100%', maxWidth: 720, gap: 18 },
  help: { color: COLORS.textMuted, lineHeight: 20 },
  results: { borderWidth: 1, borderColor: COLORS.border, borderRadius: 8, backgroundColor: COLORS.surface },
  empty: { padding: 16, color: COLORS.textMuted },
  emptyState: { padding: 16, gap: 12, alignItems: 'flex-start' },
  selected: { padding: 18, gap: 8, borderRadius: 8, backgroundColor: COLORS.secondaryContainer },
  error: { padding: 12, color: COLORS.error, backgroundColor: COLORS.errorContainer, borderRadius: 8 },
  success: { padding: 12, color: COLORS.success, backgroundColor: COLORS.successContainer, borderRadius: 8 },
});
