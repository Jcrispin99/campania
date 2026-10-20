import { useEffect, useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { ActivityIndicator, Button, Checkbox, List, Text, TextInput } from 'react-native-paper';
import { api } from '../../lib/api';
import { COLORS } from '../../theme/colors';
import { errorMessage, localDate, type Patient } from './campania-screen';
import { PatientCreateDialog } from './patient-create-dialog';
import { PatientEmailEditor } from './patient-email-editor';
import { emailDrafts, emailError, emailPayload } from './patient-emails';

type PatientSearchResponse = {
  data: { items: Patient[]; total: number };
};

const conditionOptions = [
  ['diabetes', 'Diabetes'], ['hipertension', 'Hipertensión'], ['ayuna', 'Ayuna'],
] as const;
type Condition = typeof conditionOptions[number][0];
const noConditions: Record<Condition, boolean> = { diabetes: false, hipertension: false, ayuna: false };

export function AttentionScreen() {
  const [query, setQuery] = useState('');
  const [matches, setMatches] = useState<Patient[]>([]);
  const [total, setTotal] = useState(0);
  const [patient, setPatient] = useState<Patient | null>(null);
  const [age, setAge] = useState('');
  const [emails, setEmails] = useState(emailDrafts);
  const [savingEmails, setSavingEmails] = useState(false);
  const [pressure, setPressure] = useState('');
  const [conditions, setConditions] = useState(noConditions);
  const [others, setOthers] = useState('');
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
    setAge(selected.edad == null ? '' : String(selected.edad));
    setEmails(emailDrafts(selected));
    setSaveError('');
    setNotice('');
  }

  function clearAttention() {
    setAge('');
    setEmails(emailDrafts());
    setPressure('');
    setConditions(noConditions);
    setOthers('');
  }

  async function saveEmails() {
    if (!patient || saving || savingEmails) return;
    const validation = emailError(emails);
    if (validation) { setSaveError(validation); return; }
    setSavingEmails(true);
    setSaveError('');
    setNotice('');
    try {
      const response = await api.put<{ data: Patient }>(`/pacientes/${patient.id}/correos`, { correos: emailPayload(emails) });
      setPatient(response.data.data);
      setEmails(emailDrafts(response.data.data));
      setNotice('Correos guardados.');
    } catch (error) {
      setSaveError(errorMessage(error));
    } finally {
      setSavingEmails(false);
    }
  }

  async function save() {
    if (saving || savingEmails) return;
    if (!patient) {
      setSaveError('Selecciona un paciente.');
      return;
    }
    if (!/^\d{1,3}$/.test(age.trim()) || Number(age) > 120) {
      setSaveError('Ingresa una edad válida entre 0 y 120 años.');
      return;
    }
    if (emailError(emails)) {
      setSaveError(emailError(emails));
      return;
    }
    if (!pressure.trim()) {
      setSaveError('Ingresa la presión arterial.');
      return;
    }

    setSaving(true);
    setSaveError('');
    setNotice('');
    try {
      await api.post('/atenciones', {
        dni: patient.dni,
        edad: Number(age.trim()),
        correos: emailPayload(emails),
        valor_1: pressure.trim(),
        ...conditions,
        otros: others.trim() || null,
        fecha: localDate(),
      });
      setNotice(`Atención guardada para ${patient.nombre_completo}.`);
      clearAttention();
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
        <Text style={styles.help}>Selecciona al paciente, completa sus datos y guarda la atención.</Text>
        {notice ? <Text accessibilityLiveRegion="polite" style={styles.success}>{notice}</Text> : null}

        <TextInput
          mode="outlined"
          label="Buscar paciente por nombre o DNI"
          value={query}
          onChangeText={changeQuery}
          disabled={saving || savingEmails || createVisible}
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
            <PatientEmailEditor value={emails} onChange={value => { setEmails(value); setSaveError(''); }} disabled={saving || savingEmails} />
            <Button icon="content-save-outline" mode="outlined" loading={savingEmails} disabled={saving || savingEmails} onPress={() => void saveEmails()}>
              Guardar correos
            </Button>
            <Button disabled={saving || savingEmails} onPress={() => { changeQuery(''); clearAttention(); }}>Cambiar paciente</Button>
          </View>
        ) : null}
        <TextInput
          mode="outlined"
          label="Edad *"
          value={age}
          onChangeText={value => { setAge(value); setSaveError(''); }}
          placeholder="Años"
          maxLength={3}
          keyboardType="number-pad"
          inputMode="numeric"
          disabled={saving || !patient}
        />
        <TextInput
          mode="outlined"
          label="Presión arterial *"
          value={pressure}
          onChangeText={value => { setPressure(value); setSaveError(''); }}
          placeholder="Ej. 120/80"
          maxLength={255}
          disabled={saving || !patient}
        />
        <View style={styles.conditions}>
          <Text variant="titleSmall">El paciente:</Text>
          {conditionOptions.map(([key, label]) => (
            <Checkbox.Item
              key={key}
              label={label}
              mode="android"
              position="leading"
              status={conditions[key] ? 'checked' : 'unchecked'}
              onPress={() => setConditions(current => ({ ...current, [key]: !current[key] }))}
              disabled={saving || !patient}
              style={styles.condition}
              labelStyle={styles.conditionLabel}
            />
          ))}
        </View>
        <TextInput
          mode="outlined"
          label="Otros"
          value={others}
          onChangeText={setOthers}
          placeholder="Otras enfermedades, medicamentos u observaciones"
          multiline
          numberOfLines={3}
          maxLength={1000}
          disabled={saving || !patient}
        />
        {saveError ? <Text accessibilityLiveRegion="polite" style={styles.error}>{saveError}</Text> : null}
        <Button mode="contained" icon="content-save-outline" loading={saving} disabled={saving || savingEmails || !patient || !age.trim() || !pressure.trim()} onPress={() => void save()}>
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
          setNotice('Paciente creado. Ya puedes registrar su atención.');
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
  conditions: { paddingVertical: 12, paddingHorizontal: 16, gap: 4, borderWidth: 1, borderColor: COLORS.border, borderRadius: 8, backgroundColor: COLORS.surface },
  condition: { paddingHorizontal: 0, paddingVertical: 2 },
  conditionLabel: { textAlign: 'left' },
  error: { padding: 12, color: COLORS.error, backgroundColor: COLORS.errorContainer, borderRadius: 8 },
  success: { padding: 12, color: COLORS.success, backgroundColor: COLORS.successContainer, borderRadius: 8 },
});
