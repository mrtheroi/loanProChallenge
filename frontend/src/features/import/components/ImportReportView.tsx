import type { ImportReport } from '../api/importApi'

const summaryLabels = [
  ['created', 'Created'],
  ['updated', 'Updated'],
  ['unchanged', 'Unchanged'],
  ['failed', 'Failed'],
  ['blank_rows', 'Blank rows'],
] as const

export function ImportReportView({ report }: { report: ImportReport }) {
  return (
    <div className="flex flex-col gap-6">
      <section aria-label="Import summary">
        <dl className="grid grid-cols-5 gap-4">
          {summaryLabels.map(([key, label]) => (
            <div key={key} className="rounded-lg border p-3">
              <dt className="text-sm text-muted-foreground">{label}</dt>
              <dd className="text-2xl font-semibold">{report.summary[key]}</dd>
            </div>
          ))}
        </dl>
      </section>
      {report.new_categories.length > 0 && <p>New categories: {report.new_categories.join(', ')}</p>}
      <table className="w-full text-sm">
        <thead className="text-left text-muted-foreground">
          <tr>
            <th className="py-2">Row</th>
            <th className="py-2">SKU</th>
            <th className="py-2">Status</th>
            <th className="py-2">Details</th>
          </tr>
        </thead>
        <tbody className="divide-y">
          {report.rows.map((row) => (
            <tr key={row.row}>
              <td className="py-2">{row.row}</td>
              <td className="py-2 font-mono">{row.sku ?? '—'}</td>
              <td className="py-2">{row.status}</td>
              <td className="py-2">
                <ul>
                  {Object.entries(row.errors).flatMap(([field, messages]) =>
                    messages.map((message) => (
                      <li key={`${field}:${message}`} className="text-destructive">
                        {field}: {message}
                      </li>
                    )),
                  )}
                  {row.note && <li className="text-muted-foreground">{row.note}</li>}
                </ul>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
