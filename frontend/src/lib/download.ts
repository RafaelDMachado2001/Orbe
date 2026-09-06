/**
 * Salva um blob vindo da API como arquivo. O link some do DOM logo depois do
 * clique — só existe para disparar o download do navegador.
 */
export function downloadBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()

  URL.revokeObjectURL(url)
}

/** Extrai o nome de arquivo de um cabeçalho Content-Disposition, se houver. */
export function filenameFromDisposition(disposition: string | undefined): string | null {
  if (!disposition) {
    return null
  }

  const match = /filename="?([^";]+)"?/.exec(disposition)

  return match?.[1] ?? null
}
