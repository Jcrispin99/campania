import { useCallback, useEffect, useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { Button, Dialog, Portal, Text, TextInput } from 'react-native-paper';
import { DataTable, type DataTableColumn } from '../../components/data/data-table';
import { ListPagination } from '../../components/data/list-pagination';
import { api } from '../../lib/api';
import { COLORS } from '../../theme/colors';
import { errorMessage, type Patient } from './campania-screen';

type Attention = {
  id: number;
  paciente: Patient;
  valor_1: string;
  diabetes: boolean;
  hipertension: boolean;
  ayuna: boolean;
  otros: string | null;
  fecha: string;
  registrado_por: string;
};
type Page = { items: Attention[]; current_page: number; last_page: number; total: number };
const emptyPage: Page = { items: [], current_page: 1, last_page: 1, total: 0 };

function conditions(item: Attention) {
  const checked = [item.diabetes && 'Diabetes', item.hipertension && 'Hipertensión', item.ayuna && 'Ayuna'].filter(Boolean);
  return checked.length ? checked.join(', ') : '—';
}

export function AttentionHistory() {
  const [query, setQuery] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [revision, setRevision] = useState(0);
  const [attentions, setAttentions] = useState<Page>(emptyPage);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selected, setSelected] = useState<Attention | null>(null);
  const refresh = useCallback(() => setRevision(value => value + 1), []);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError('');
    api.get<{ data: Page }>('/atenciones', { params: { search: search || undefined, page }, signal: controller.signal })
      .then(response => setAttentions(response.data.data))
      .catch(error => { if (!controller.signal.aborted) setError(errorMessage(error)); })
      .finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, [search, page, revision]);

  function applySearch() {
    setSearch(query.trim());
    setPage(1);
  }

  const columns: DataTableColumn<Attention>[] = [
    { key: 'fecha', title: 'Fecha', style: { width: 110 }, renderCell: item => <Text>{item.fecha}</Text> },
    { key: 'dni', title: 'DNI', style: { width: 100 }, renderCell: item => <Text>{item.paciente.dni}</Text> },
    { key: 'paciente', title: 'Paciente', style: { width: 250, paddingRight: 12 }, renderCell: item => <Text>{item.paciente.nombre_completo}</Text> },
    { key: 'edad', title: 'Edad', style: { width: 70 }, renderCell: item => <Text>{item.paciente.edad ?? '—'}</Text> },
    { key: 'correo', title: 'Correo', style: { width: 220, paddingRight: 12 }, renderCell: item => <Text numberOfLines={2}>{item.paciente.correo_electronico ?? '—'}</Text> },
    { key: 'presion', title: 'Presión arterial', style: { width: 150, paddingRight: 12 }, renderCell: item => <Text numberOfLines={3}>{item.valor_1}</Text> },
    { key: 'condiciones', title: 'Diabetes / Hipert. / Ayuna', style: { width: 200, paddingRight: 12 }, renderCell: item => <Text>{conditions(item)}</Text> },
    { key: 'otros', title: 'Otros', style: { width: 240, paddingRight: 12 }, renderCell: item => <Text numberOfLines={3}>{item.otros ?? '—'}</Text> },
    { key: 'usuario', title: 'Registrado por', style: { width: 180 }, renderCell: item => <Text>{item.registrado_por}</Text> },
  ];

  return <View style={styles.screen}>
    <View style={styles.toolbar}>
      <View style={styles.heading}>
        <Text variant="headlineSmall">Historial de atenciones</Text>
        <Text>{attentions.total} registros</Text>
      </View>
    </View>
    <View style={styles.toolbar}>
      <TextInput style={styles.search} mode="outlined" label="Buscar por nombre o DNI" value={query} onChangeText={setQuery} onSubmitEditing={applySearch} autoCapitalize="none" />
      <Button onPress={applySearch}>Buscar</Button>
      <Button onPress={() => { setQuery(''); setSearch(''); setPage(1); }}>Limpiar</Button>
    </View>
    <ScrollView horizontal contentContainerStyle={{ flexGrow: 1 }} style={{ flex: 1 }}>
      <View style={{ width: 1560, flex: 1 }}>
        <DataTable
          columns={columns}
          data={attentions.items}
          keyExtractor={item => String(item.id)}
          loading={loading}
          refreshing={false}
          error={error}
          onRefresh={refresh}
          onRetry={refresh}
          onRowPress={setSelected}
          rowAccessibilityLabel={item => `Ver atención de ${item.paciente.nombre_completo} del ${item.fecha}`}
          emptyIcon="clipboard-text-search-outline"
          emptyTitle="Sin atenciones"
          emptyText={search ? 'No hay atenciones para esta búsqueda.' : 'Aún no se registraron atenciones.'}
        />
      </View>
    </ScrollView>
    <View style={styles.pagination}><ListPagination page={page} pageSize={15} totalItems={attentions.total} onPageChange={setPage} onSearchPress={applySearch} /></View>
    <Portal><Dialog visible={selected !== null} onDismiss={() => setSelected(null)} style={styles.dialog}>
      <Dialog.Title>Detalle de atención</Dialog.Title>
      <Dialog.ScrollArea><ScrollView contentContainerStyle={styles.detail}>
        {selected ? <>
          <Text variant="titleMedium">{selected.paciente.nombre_completo}</Text>
          <Text>DNI: {selected.paciente.dni}</Text>
          {selected.paciente.nombre_area ? <Text>{selected.paciente.nombre_area}</Text> : null}
          <Text>Edad: {selected.paciente.edad ?? '—'}</Text>
          <Text>Correo: {selected.paciente.correo_electronico ?? '—'}</Text>
          <Text>Fecha: {selected.fecha}</Text>
          <Text>Presión arterial: {selected.valor_1}</Text>
          <Text>Diabetes: {selected.diabetes ? 'Sí' : 'No'}</Text>
          <Text>Hipertensión: {selected.hipertension ? 'Sí' : 'No'}</Text>
          <Text>Ayuna: {selected.ayuna ? 'Sí' : 'No'}</Text>
          <Text>Otros: {selected.otros ?? '—'}</Text>
          <Text style={styles.muted}>Registrado por {selected.registrado_por}</Text>
        </> : null}
      </ScrollView></Dialog.ScrollArea>
      <Dialog.Actions><Button onPress={() => setSelected(null)}>Cerrar</Button></Dialog.Actions>
    </Dialog></Portal>
  </View>;
}

const styles = StyleSheet.create({
  screen: { flex: 1, padding: 18, gap: 14 },
  toolbar: { flexDirection: 'row', alignItems: 'center', gap: 10, flexWrap: 'wrap' },
  heading: { flex: 1, minWidth: 150, gap: 4 },
  search: { flex: 1, minWidth: 180 },
  pagination: { alignItems: 'flex-end' },
  dialog: { maxHeight: '90%', width: '92%', maxWidth: 600, alignSelf: 'center' },
  detail: { paddingVertical: 18, gap: 8 },
  muted: { color: COLORS.textMuted },
});
