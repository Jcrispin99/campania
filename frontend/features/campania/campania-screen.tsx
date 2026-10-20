import axios from 'axios';
import { useCallback, useEffect, useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { Button, Dialog, Portal, Text, TextInput } from 'react-native-paper';
import { DataTable, type DataTableColumn } from '../../components/data/data-table';
import { ListPagination } from '../../components/data/list-pagination';
import { api } from '../../lib/api';
import { localDate } from '../../lib/date';
import { PatientEmailEditor } from './patient-email-editor';
import { emailDrafts, emailError, emailPayload, type PatientEmail } from './patient-emails';

export { localDate };

const fields = [
  ['numero', 'N°'], ['nombre_completo', 'Nombre completo'], ['dni', 'Nº de documento'],
  ['nombre_posicion', 'Nombre posición'], ['nombre_area', 'Nombre de área'],
  ['nombre_unidad_org', 'Nombre unidad org.'],
  ['correo_electronico', 'Correo electrónico'], ['asistencia', 'Asistencia'],
  ['presion_arterial', 'Presión arterial'], ['fecha', 'Fecha'],
] as const;
type Field = typeof fields[number][0];
export type Patient = { id: number; edad?: number | null; correos?: PatientEmail[] } & Record<Field, string | number | null>;
type Attention = { id: number; paciente: Patient; valor_1: string | null; fecha: string; registrado_por: string | null };
type Page<T> = { items: T[]; current_page: number; last_page: number; total: number };
const emptyPage = { items: [], current_page: 1, last_page: 1, total: 0 };
const emptyPatient = Object.fromEntries(fields.map(([key]) => [key, ''])) as Record<Field, string>;

export function errorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    if (error.response?.status === 401) return 'Tu sesión expiró. Vuelve a iniciar sesión.';
    if (error.response?.status === 404) return 'No se encontró un paciente con este DNI.';
    const errors = error.response?.data?.errors;
    const first = errors ? Object.values(errors).flat()[0] : null;
    return typeof first === 'string' ? first : error.response?.data?.message ?? 'No se pudo completar la operación.';
  }
  return 'No se pudo completar la operación.';
}

export function CampaniaScreen({ section }: { section: 'pacientes' | 'atenciones' }) {
  const [query, setQuery] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [revision, setRevision] = useState(0);
  const [patients, setPatients] = useState<Page<Patient>>(emptyPage);
  const [attentions, setAttentions] = useState<Page<Attention>>(emptyPage);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [dialog, setDialog] = useState<'paciente' | 'atencion' | null>(null);
  const [editing, setEditing] = useState<number | null>(null);
  const [emails, setEmails] = useState(emailDrafts);
  const [form, setForm] = useState<Record<Field, string>>(emptyPatient);
  const [dni, setDni] = useState('');
  const [patient, setPatient] = useState<Patient | null>(null);
  const [measurement, setMeasurement] = useState('');
  const [date, setDate] = useState(localDate);
  const [saving, setSaving] = useState(false);
  const [lookingUp, setLookingUp] = useState(false);
  const [formError, setFormError] = useState('');
  const refresh = useCallback(() => setRevision(value => value + 1), []);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError('');
    const params = section === 'pacientes' ? { search, page } : { dni: search || undefined, page };
    api.get(`/${section}`, { params, signal: controller.signal })
      .then(response => {
        if (section === 'pacientes') setPatients(response.data.data);
        else setAttentions(response.data.data);
      })
      .catch(error => { if (!controller.signal.aborted) setError(errorMessage(error)); })
      .finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, [section, search, page, revision]);

  function openPatient(item?: Patient) {
    setForm(item ? Object.fromEntries(fields.map(([key]) => [key, String(item[key] ?? '')])) as Record<Field, string> : { ...emptyPatient });
    setEmails(emailDrafts(item));
    setEditing(item?.id ?? null);
    setFormError('');
    setDialog('paciente');
  }

  function openAttention() {
    setDni(''); setPatient(null); setMeasurement(''); setDate(localDate());
    setFormError(''); setDialog('atencion');
  }

  async function lookup() {
    setFormError(''); setPatient(null);
    if (!/^\d{8}$/.test(dni)) { setFormError('El DNI debe tener 8 dígitos.'); return; }
    setLookingUp(true);
    try {
      const response = await api.get(`/pacientes/dni/${dni}`);
      setPatient(response.data.data);
    } catch (error) { setFormError(errorMessage(error)); }
    finally { setLookingUp(false); }
  }

  async function save() {
    setFormError(''); setSaving(true);
    try {
      if (dialog === 'paciente') {
        if (emailError(emails)) { setFormError(emailError(emails)); return; }
        const payload = { ...Object.fromEntries(fields.filter(([key]) => key !== 'correo_electronico').map(([key]) => [key, key === 'numero' ? (form[key] ? Number(form[key]) : null) : form[key].trim() || null])), correos: emailPayload(emails) };
        if (editing) await api.put(`/pacientes/${editing}`, payload);
        else await api.post('/pacientes', payload);
      } else {
        if (!patient || patient.dni !== dni) { setFormError('Busca y confirma primero el paciente por DNI.'); return; }
        await api.post('/atenciones', { dni, valor_1: measurement.trim() || null, fecha: date.trim() });
      }
      setDialog(null); setNotice(dialog === 'paciente' ? 'Paciente guardado.' : 'Atención registrada.'); refresh();
    } catch (error) { setFormError(errorMessage(error)); }
    finally { setSaving(false); }
  }

  const patientColumns: DataTableColumn<Patient>[] = fields.map(([key, title]) => ({
    key, title, style: { width: key === 'nombre_completo' ? 250 : key === 'numero' ? 60 : 170, paddingRight: 12 },
    renderCell: item => <Text>{String(item[key] ?? '—')}</Text>,
  }));
  const attentionColumns: DataTableColumn<Attention>[] = [
    { key: 'fecha', title: 'Fecha', style: { width: 120 }, renderCell: item => <Text>{item.fecha}</Text> },
    { key: 'dni', title: 'DNI', style: { width: 110 }, renderCell: item => <Text>{item.paciente.dni}</Text> },
    { key: 'nombre', title: 'Paciente', style: { width: 280 }, renderCell: item => <Text>{item.paciente.nombre_completo}</Text> },
    { key: 'valor', title: 'Medición', style: { width: 200 }, renderCell: item => <Text>{item.valor_1 ?? '—'}</Text> },
    { key: 'usuario', title: 'Registrado por', style: { width: 200 }, renderCell: item => <Text>{item.registrado_por ?? '—'}</Text> },
  ];
  const current = section === 'pacientes' ? patients : attentions;

  return <View style={styles.screen}>
    <View style={styles.toolbar}>
      <View style={styles.heading}>
        <Text variant="headlineSmall">{section === 'pacientes' ? 'Pacientes' : 'Atenciones'}</Text>
        <Text>{current.total} registros</Text>
      </View>
      <Button mode="contained" icon="plus" onPress={section === 'pacientes' ? () => openPatient() : openAttention}>
        {section === 'pacientes' ? 'Nuevo paciente' : 'Nueva atención'}
      </Button>
    </View>
    <View style={styles.toolbar}>
      <TextInput style={styles.search} mode="outlined" label={section === 'pacientes' ? 'Buscar por nombre o DNI' : 'Filtrar por DNI completo'} value={query} onChangeText={setQuery} onSubmitEditing={() => { setSearch(query.trim()); setPage(1); }} />
      <Button onPress={() => { setSearch(query.trim()); setPage(1); }}>Buscar</Button>
      <Button onPress={() => { setQuery(''); setSearch(''); setPage(1); }}>Limpiar</Button>
    </View>
    {notice ? <Text accessibilityLiveRegion="polite" style={styles.notice}>{notice}</Text> : null}
    <ScrollView horizontal contentContainerStyle={{ flexGrow: 1 }} style={{ flex: 1 }}>
      <View style={{ width: section === 'pacientes' ? 1840 : 950, flex: 1 }}>
        {section === 'pacientes' ? <DataTable columns={patientColumns} data={patients.items} keyExtractor={item => String(item.id)} loading={loading} refreshing={false} error={error} onRefresh={refresh} onRetry={refresh} emptyIcon="account-search" emptyTitle="Sin pacientes" emptyText="Registra un paciente o modifica la búsqueda." onRowPress={openPatient} />
          : <DataTable columns={attentionColumns} data={attentions.items} keyExtractor={item => String(item.id)} loading={loading} refreshing={false} error={error} onRefresh={refresh} onRetry={refresh} emptyIcon="clipboard-pulse-outline" emptyTitle="Sin atenciones" emptyText="Busca un paciente por DNI y registra su medición." />}
      </View>
    </ScrollView>
    <View style={styles.pagination}><ListPagination page={page} pageSize={15} totalItems={current.total} onPageChange={setPage} onSearchPress={() => { setSearch(query.trim()); setPage(1); }} /></View>
    <Portal><Dialog visible={dialog !== null} onDismiss={() => { if (!saving && !lookingUp) setDialog(null); }} style={styles.dialog}>
      <Dialog.Title>{dialog === 'paciente' ? (editing ? 'Editar paciente' : 'Nuevo paciente') : 'Registrar atención'}</Dialog.Title>
      <Dialog.ScrollArea><ScrollView contentContainerStyle={styles.form} keyboardShouldPersistTaps="handled">
        {dialog === 'paciente' ? <>{fields.filter(([key]) => key !== 'correo_electronico').map(([key, title]) => <TextInput key={key} mode="outlined" label={`${title}${key === 'nombre_completo' || key === 'dni' ? ' *' : key === 'fecha' ? ' (AAAA-MM-DD)' : ''}`} value={form[key]} onChangeText={value => setForm(current => ({ ...current, [key]: value }))} keyboardType={key === 'dni' || key === 'numero' ? 'number-pad' : key === 'correo_electronico' ? 'email-address' : 'default'} maxLength={key === 'dni' ? 8 : 255} autoCapitalize={key === 'correo_electronico' ? 'none' : 'sentences'} disabled={saving} />)}<PatientEmailEditor value={emails} onChange={setEmails} disabled={saving} /></> : <>
          <TextInput mode="outlined" label="DNI del paciente" value={dni} onChangeText={value => { setDni(value); setPatient(null); }} keyboardType="number-pad" maxLength={8} disabled={lookingUp || saving} />
          <Button mode="outlined" loading={lookingUp} disabled={lookingUp || saving} onPress={() => void lookup()}>Buscar paciente</Button>
          {patient ? <View style={styles.patient}><Text variant="titleMedium">{patient.nombre_completo}</Text><Text>{patient.nombre_area ?? 'Sin área registrada'}</Text><Text>DNI: {patient.dni}</Text></View> : null}
          <TextInput mode="outlined" label="Presión arterial (opcional)" placeholder="Ej. 120/80" value={measurement} onChangeText={setMeasurement} maxLength={255} disabled={saving} />
          <TextInput mode="outlined" label="Fecha (AAAA-MM-DD)" value={date} onChangeText={setDate} disabled={saving} />
        </>}
        {formError ? <Text accessibilityLiveRegion="polite" style={styles.error}>{formError}</Text> : null}
      </ScrollView></Dialog.ScrollArea>
      <Dialog.Actions><Button disabled={saving || lookingUp} onPress={() => setDialog(null)}>Cancelar</Button><Button loading={saving} disabled={saving || lookingUp || (dialog === 'atencion' && !patient)} onPress={() => void save()}>Guardar</Button></Dialog.Actions>
    </Dialog></Portal>
  </View>;
}

const styles = StyleSheet.create({
  screen: { flex: 1, padding: 18, gap: 14 },
  toolbar: { flexDirection: 'row', alignItems: 'center', gap: 10, flexWrap: 'wrap' },
  heading: { flex: 1, minWidth: 150, gap: 4 },
  search: { flex: 1, minWidth: 180 },
  pagination: { alignItems: 'flex-end' },
  dialog: { maxHeight: '90%', width: '92%', maxWidth: 650, alignSelf: 'center' },
  form: { paddingVertical: 18, gap: 16 },
  patient: { padding: 16, backgroundColor: '#D9F8F3', borderRadius: 8, gap: 6 },
  error: { color: '#8F1D2C', padding: 10, backgroundColor: '#FCE8EA' },
  notice: { color: '#247451' },
});
