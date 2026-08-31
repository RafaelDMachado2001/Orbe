import type { ReactNode } from 'react'

interface Props {
  title: string
  subtitle: string
  children: ReactNode
  footer: ReactNode
}

export function AuthLayout({ title, subtitle, children, footer }: Props) {
  return (
    <div className="grid min-h-screen bg-app lg:grid-cols-[1fr_1.05fr]">
      <section className="flex items-center justify-center px-6 py-12">
        <div className="w-full max-w-[380px]">
          <div className="mb-8 flex items-center gap-[11px]">
            <div className="grid size-[34px] place-items-center rounded-[11px] bg-[linear-gradient(140deg,#35D68A,#1E9E63)] text-[15px] font-extrabold text-[#04140C]">
              O
            </div>
            <div className="flex flex-col gap-px">
              <span className="text-[14.5px] font-bold tracking-[-0.2px]">Orbe</span>
              <span className="text-[11px] font-medium text-ink-muted">Controle financeiro</span>
            </div>
          </div>

          <h1 className="text-[24px] font-bold tracking-[-0.5px]">{title}</h1>
          <p className="mt-1.5 text-[12.5px] font-medium leading-relaxed text-ink-soft">
            {subtitle}
          </p>

          <div className="mt-7">{children}</div>

          <div className="mt-6 text-[12px] font-medium text-ink-muted">{footer}</div>
        </div>
      </section>

      <aside className="relative hidden overflow-hidden border-l border-hairline bg-[linear-gradient(160deg,#0C0E12,#08090C)] lg:block">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute -right-32 top-10 size-[420px] rounded-full bg-[radial-gradient(circle,rgba(160,124,255,0.22),transparent_65%)]"
        />
        <div
          aria-hidden="true"
          className="pointer-events-none absolute -left-20 bottom-0 size-[380px] rounded-full bg-[radial-gradient(circle,rgba(53,214,138,0.16),transparent_65%)]"
        />

        <div className="relative flex h-full flex-col justify-center gap-5 px-16">
          <p className="max-w-[26ch] text-[40px] font-extrabold leading-[1.15] tracking-[-1px]">
            Onde seu dinheiro está <span className="text-purple">hoje</span>
          </p>
          <p className="max-w-[42ch] text-[13px] leading-relaxed text-ink-soft">
            Saldo consolidado, faturas de cartão, parcelas em aberto e uma projeção construída a
            partir das suas recorrências e do seu histórico.
          </p>

          <dl className="mt-4 grid max-w-[420px] grid-cols-3 gap-3">
            {[
              { label: 'Contas', value: 'Todos os bancos' },
              { label: 'Cartões', value: 'Faturas e parcelas' },
              { label: 'Previsão', value: 'Até 12 meses' },
            ].map((item) => (
              <div
                key={item.label}
                className="rounded-[14px] border border-hairline bg-surface/60 p-3.5"
              >
                <dt className="text-[10.5px] font-bold uppercase tracking-[0.08em] text-ink-faint">
                  {item.label}
                </dt>
                <dd className="mt-1.5 text-[12px] font-semibold text-ink">{item.value}</dd>
              </div>
            ))}
          </dl>
        </div>
      </aside>
    </div>
  )
}
