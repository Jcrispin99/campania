import { TextInput } from 'react-native-paper';

export type DateFilterInputProps = {
  label: string;
  value: string;
  onChange: (value: string) => void;
  min?: string;
  max?: string;
};

export function DateFilterInput({ label, value, onChange }: DateFilterInputProps) {
  return <TextInput mode="outlined" label={label} value={value} onChangeText={onChange} placeholder="AAAA-MM-DD" />;
}
