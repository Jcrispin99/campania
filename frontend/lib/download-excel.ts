import { api } from './api';

export async function downloadExcel(endpoint: string, params: Record<string, string | undefined>) {
  const response = await api.get<Blob>(endpoint, { params, responseType: 'blob' });
  const disposition = String(response.headers['content-disposition'] ?? '');
  const filename = /filename="?([^";]+)/i.exec(disposition)?.[1] ?? 'atenciones.xlsx';
  const url = URL.createObjectURL(response.data);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
