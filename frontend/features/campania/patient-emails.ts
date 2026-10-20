export type PatientEmail = { id?: number; correo: string; predeterminado: boolean };

export function emailDrafts(patient?: { correos?: PatientEmail[] }): PatientEmail[] {
  return patient?.correos?.length
    ? patient.correos.map(email => ({ ...email }))
    : [{ correo: '', predeterminado: true }];
}

export function emailPayload(drafts: PatientEmail[]): PatientEmail[] {
  const emails = drafts.filter(email => email.correo.trim()).map(email => ({
    correo: email.correo.trim().toLowerCase(), predeterminado: email.predeterminado,
  }));
  if (emails.length && !emails.some(email => email.predeterminado)) emails[0].predeterminado = true;
  return emails;
}

export function emailError(drafts: PatientEmail[]): string {
  const emails = emailPayload(drafts);
  if (emails.some(email => email.correo.length > 254 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.correo))) {
    return 'Ingresa correos electrónicos válidos o deja el campo vacío.';
  }
  if (new Set(emails.map(email => email.correo)).size !== emails.length) return 'No repitas un correo en el paciente.';
  return '';
}
