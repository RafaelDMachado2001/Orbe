/**
 * Fonte unica dos tipos da API. Espelha os API Resources do Laravel — quando
 * um Resource muda, este arquivo muda junto, e o TypeScript aponta cada tela
 * afetada.
 */

export interface User {
  id: number
  name: string
  email: string
  timezone: string
  currency: string
  initials: string
}

export interface AuthResponse {
  user: User
  token: string
}

export type MovementDirection = 'entrada' | 'saida'

export type TransactionStatus = 'previsto' | 'confirmado' | 'cancelado'

export type InvoiceStatus = 'aberta' | 'fechada' | 'paga'

export interface CategoryTag {
  name: string
  color: string
}

export interface ChartPoint {
  month: string
  label: string
  income: number
  expense: number
  balance: number
  is_forecast: boolean
}

export interface AccountSummary {
  id: number
  nickname: string
  type: string
  type_label: string
  bank: { id: number; name: string; color: string }
  balance: number
}

export interface CardInvoice {
  id: number | null
  reference_month: string
  total: number
  paid_amount: number
  remaining: number
  status: InvoiceStatus
  status_label: string
  closing_date: string
  due_date: string
  days_to_close: number
  days_to_due: number
}

export interface CardSummary {
  id: number
  nickname: string
  brand: CardBrand
  last_four: string
  color: string
  bank: string
  limit_amount: number
  used_amount: number
  available_amount: number
  usage_percent: number
  current_invoice: CardInvoice
}

export interface CategorySlice {
  category_id: number | null
  name: string
  color: string
  total: number
  percentage: number
}

export interface Movement {
  id: string
  description: string
  amount: number
  direction: MovementDirection
  date: string
  status: TransactionStatus
  category: CategoryTag | null
  source: string
  installment: string | null
  is_recurring: boolean
}

export interface BudgetStatus {
  category_id: number
  category: string
  color: string
  limit_amount: number
  spent: number
  percentage: number
  is_exceeded: boolean
}

export type AlertLevel = 'alerta' | 'atencao'

export interface DashboardAlert {
  level: AlertLevel
  title: string
  message: string
}

export interface Dashboard {
  month: string
  month_label: string
  generated_at: string
  kpis: {
    consolidated_balance: {
      value: number
      delta_percent: number | null
      comparison_label: string
    }
    income: {
      value: number
      sources_count: number
      sources: string[]
    }
    expense: {
      value: number
      budget_usage_percent: number | null
    }
    projection: {
      value: number | null
      month: string | null
      label: string | null
      confidence: number | null
    }
  }
  chart: ChartPoint[]
  accounts: AccountSummary[]
  cards: CardSummary[]
  categories: CategorySlice[]
  latest_movements: Movement[]
  budgets: BudgetStatus[]
  forecast: {
    committed_amount: number | null
    leftover: number | null
    commitment_rate: number | null
    predicted_income: number | null
    goal_progress: number | null
    confidence: number | null
    description: string
  }
  alerts: DashboardAlert[]
}

export type EntryKind = 'receita' | 'despesa' | 'cartao' | 'emprestimo' | 'transferencia'

/** Se o valor digitado é o do parcelamento inteiro ou o de cada prestação. */
export type AmountMode = 'total' | 'parcela'

export type CategoryType = 'receita' | 'despesa'

export type MovementOrigin = 'conta' | 'cartao'

export type PaymentMethod =
  | 'pix'
  | 'debito'
  | 'credito'
  | 'dinheiro'
  | 'boleto'
  | 'transferencia'

/**
 * Uma linha do extrato. Uma parcela de cartao e uma linha propria: `id` a
 * identifica na lista, `transaction_id` aponta a compra inteira, que e o que
 * as acoes de editar e excluir alcancam.
 */
export interface TransactionEntry {
  id: string
  transaction_id: number
  origin: MovementOrigin
  origin_label: string
  description: string
  amount: number
  direction: MovementDirection
  type: 'receita' | 'despesa' | 'transferencia'
  status: TransactionStatus
  status_label: string
  method: PaymentMethod | null
  method_label: string | null
  date: string
  paid_date: string | null
  category: { id: number; name: string; color: string } | null
  account_id: number | null
  credit_card_id: number | null
  source: string
  installment: string | null
  installment_number: number | null
  installment_total: number | null
  is_recurring: boolean
  is_paid: boolean
  is_transfer: boolean
  is_invoice_payment: boolean
  is_editable: boolean
}

export interface TransactionSummary {
  income: number
  expense: number
  net: number
  entries: number
}

export interface TransactionMeta {
  page: number
  per_page: number
  total: number
  last_page: number
  has_more: boolean
}

export interface TransactionPage {
  items: TransactionEntry[]
  summary: TransactionSummary
  meta: TransactionMeta
}

/** O lancamento como o formulario o edita — a compra inteira, nao a parcela. */
export interface TransactionForm {
  id: number
  kind: EntryKind
  description: string
  amount: number
  competence_date: string
  status: TransactionStatus
  method: PaymentMethod | null
  notes: string | null
  category_id: number | null
  account_id: number | null
  credit_card_id: number | null
  installments: number | null
  amount_mode: AmountMode
  /** Preenchidos quando o lançamento é uma parcela de um plano em conta. */
  installment_number: number | null
  installment_total: number | null
  lender: string | null
  from_account_id: number | null
  to_account_id: number | null
  is_editable: boolean
  lock_reason: string | null
}

export interface EnumOption {
  value: string
  label: string
}

export interface TransactionOptions {
  accounts: { id: number; nickname: string; type_label: string; bank: string; color: string }[]
  cards: {
    id: number
    nickname: string
    brand_label: string
    last_four: string
    color: string
    closing_day: number
  }[]
  categories: {
    id: number
    name: string
    type: CategoryType
    color: string
    parent_id: number | null
  }[]
  /** Categoria que a aba de empréstimo marca sozinha, quando existe. */
  loan_category_id: number | null
  kinds: EnumOption[]
  types: EnumOption[]
  statuses: EnumOption[]
  methods: EnumOption[]
  origins: EnumOption[]
}

export type CardBrand = 'visa' | 'mastercard' | 'elo' | 'amex' | 'hipercard'

/** O cartão como a tela de Cartões o vê: o resumo do dashboard, mais o ciclo. */
export interface CardDetail extends CardSummary {
  brand_label: string
  is_active: boolean
  closing_day: number
  due_day: number
  payment_account: { id: number; nickname: string } | null
}

export interface CardsSummary {
  limit_total: number
  used_total: number
  available_total: number
  usage_percent: number
  outstanding_total: number
  active_count: number
  archived_count: number
  next_due: {
    card_id: number
    card: string
    invoice_id: number
    remaining: number
    due_date: string
    days_to_due: number
  } | null
}

export interface CardsPage {
  summary: CardsSummary
  cards: CardDetail[]
}

/** Uma linha da linha do tempo de faturas de um cartão. */
export interface InvoiceRow {
  id: number
  reference_month: string
  total: number
  paid_amount: number
  remaining: number
  status: InvoiceStatus
  status_label: string
  closing_date: string
  due_date: string
  items_count: number
}

export interface InvoiceItem {
  id: number
  transaction_id: number
  description: string
  amount: number
  purchase_date: string
  installment: string
  installment_number: number
  installment_total: number
  status: TransactionStatus
  is_cancelled: boolean
  is_paid: boolean
  category: { id: number; name: string; color: string } | null
}

export interface InvoicePayment {
  id: number
  amount: number
  paid_date: string
  account: string
}

export interface InvoiceDetail {
  id: number
  credit_card_id: number
  card: string
  reference_month: string
  total: number
  paid_amount: number
  remaining: number
  status: InvoiceStatus
  status_label: string
  closing_date: string
  due_date: string
  paid_at: string | null
  items: InvoiceItem[]
  payments: InvoicePayment[]
  can_pay: boolean
  can_close: boolean
  /** Fechar agora seria antecipar o corte: a data de fechamento não chegou. */
  closes_early: boolean
  can_undo: boolean
}

/** O cartão como o formulário o edita. */
export interface CreditCardRecord {
  id: number
  bank_id: number
  payment_account_id: number | null
  nickname: string
  brand: CardBrand
  brand_label: string
  last_four: string
  limit_amount: number
  closing_day: number
  due_day: number
  color: string
  is_active: boolean
}

export interface CardOptions {
  banks: { id: number; name: string; color: string; kind_label: string }[]
  accounts: { id: number; nickname: string; bank: string }[]
  brands: EnumOption[]
}

export type RecurrenceFrequency = 'diaria' | 'semanal' | 'mensal' | 'anual'

export type RecurrenceSource = 'conta' | 'cartao'

/** Uma regra fixa vista na tela, já com o estado dela no mês consultado. */
export interface RecurrenceRow {
  id: number
  description: string
  amount: number
  /** Valor equivalente por mês, para somar frequências diferentes. */
  monthly_amount: number
  type: 'receita' | 'despesa'
  frequency: RecurrenceFrequency
  frequency_label: string
  interval: number
  day_of_month: number | null
  schedule_label: string
  starts_on: string
  ends_on: string | null
  last_materialized_on: string | null
  is_active: boolean
  source: string
  source_kind: RecurrenceSource
  account_id: number | null
  credit_card_id: number | null
  method: PaymentMethod | null
  category: { id: number; name: string; color: string } | null
  occurrences: number
  launched: number
  pending: number
  next_date: string | null
}

export interface RecurrencesSummary {
  expense_total: number
  income_total: number
  net_total: number
  active_count: number
  paused_count: number
  pending_count: number
  pending_amount: number
}

export interface RecurrencesPage {
  summary: RecurrencesSummary
  recurrences: RecurrenceRow[]
}

/** A regra como o formulário a edita. */
export interface RecurrenceRecord {
  id: number
  description: string
  amount: number
  type: 'receita' | 'despesa'
  frequency: RecurrenceFrequency
  interval: number
  day_of_month: number | null
  starts_on: string
  ends_on: string | null
  category_id: number | null
  account_id: number | null
  credit_card_id: number | null
  source_kind: RecurrenceSource
  method: PaymentMethod | null
  is_active: boolean
}

export interface RecurrenceOptions {
  accounts: TransactionOptions['accounts']
  cards: TransactionOptions['cards']
  categories: TransactionOptions['categories']
  frequencies: EnumOption[]
  methods: EnumOption[]
  types: EnumOption[]
}

export type ImportFormat = 'ofx' | 'csv'

export type ImportTarget = 'conta' | 'cartao'

/**
 * De qual coluna do CSV sai cada campo. Vem preenchido com o palpite do
 * parser; a tela mostra o palpite e deixa corrigir.
 */
export interface CsvMapping {
  date_column: number
  description_column: number
  amount_column: number
  /** Preenchido só quando o arquivo separa débito de crédito em duas colunas. */
  inflow_column: number | null
  delimiter: string
  has_header: boolean
}

export interface ImportPreviewRow {
  index: number
  date: string
  description: string
  amount: number
  direction: MovementDirection
  direction_label: string
  category_id: number | null
  /** Já existe um lançamento igual: vem desmarcada, mas dá para importar. */
  is_duplicate: boolean
  /** O destino escolhido não aceita esta linha: nem marcada ela entra. */
  is_importable: boolean
  skip_reason: string | null
  selected: boolean
}

export interface ImportPreviewSummary {
  total: number
  selected: number
  duplicates: number
  blocked: number
  income: number
  expense: number
}

/** O arquivo lido pela API, antes de qualquer coisa ser gravada. */
export interface ImportPreview {
  format: ImportFormat
  format_label: string
  period_start: string | null
  period_end: string | null
  summary: ImportPreviewSummary
  mapping: CsvMapping | null
  headers: string[]
  /** Quantas colunas o CSV tem, para montar os seletores de mapeamento. */
  column_count: number
  rows: ImportPreviewRow[]
}

export interface ImportBatch {
  id: number
  filename: string
  format: ImportFormat
  format_label: string
  target: ImportTarget
  target_label: string
  destination: string
  imported_count: number
  skipped_count: number
  /** Quantos lançamentos do lote ainda existem no extrato. */
  remaining_count: number
  period_start: string | null
  period_end: string | null
  created_at: string
  can_undo: boolean
  undo_block_reason: string | null
}

/** Envelope padrao dos API Resources do Laravel. */
export interface ResourceEnvelope<T> {
  data: T
}

export interface ApiValidationError {
  message: string
  errors?: Record<string, string[]>
}

/** Uma categoria como o formulário de categorias a edita. */
export interface CategoryRecord {
  id: number
  parent_id: number | null
  name: string
  type: CategoryType
  type_label: string
  color: string
  icon: string | null
  is_system: boolean
}

/** Um nó da árvore de categorias, com o que ele movimentou no mês. */
export interface CategoryNode extends CategoryRecord {
  entries_count: number
  month_total: number
  /** O total da categoria somado ao das subcategorias dela. */
  total_with_children: number
  children: CategoryNode[]
}

export interface CategoriesSummary {
  total: number
  expense_count: number
  income_count: number
  in_use_count: number
  unused_count: number
}

export interface CategoriesPage {
  month: string
  summary: CategoriesSummary
  categories: CategoryNode[]
  types: EnumOption[]
}
