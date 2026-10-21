import { StyleSheet, View } from 'react-native';
import { Text } from 'react-native-paper';
import { COLORS } from '../../theme/colors';
import type { DateFilterInputProps } from './date-filter-input';

export function DateFilterInput({ label, value, onChange, min, max }: DateFilterInputProps) {
  return <View style={styles.field}>
    <Text style={styles.label}>{label}</Text>
    <input
      type="date"
      aria-label={label}
      value={value}
      min={min}
      max={max}
      onChange={event => onChange(event.target.value)}
      style={{ width: '100%', boxSizing: 'border-box', minWidth: 0, minHeight: 40, padding: '8px 10px', border: `1px solid ${COLORS.border}`, borderRadius: 4, background: COLORS.surface, color: COLORS.text, font: 'inherit' }}
    />
  </View>;
}

const styles = StyleSheet.create({
  field: { gap: 4, minWidth: 0 },
  label: { fontSize: 12, color: COLORS.textMuted },
});
