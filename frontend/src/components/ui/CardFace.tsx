import { cn } from '@/lib/cn'
import type { CardBrand } from '@/types/api'

interface Props {
  nickname: string
  lastFour: string
  brand: CardBrand
  /** Cor escolhida no cadastro. Vira o matiz do cartão, nunca a cor crua. */
  color: string
  width?: number
  isMuted?: boolean
  className?: string
}

/**
 * O cartão desenhado como cartão.
 *
 * Proporção 1,586 — a do cartão físico (ISO/IEC 7810 ID-1). É o que faz a
 * miniatura ser reconhecida como cartão antes de qualquer texto ser lido, e o
 * que permite o usuário achar o dele na lista pela cor e pela bandeira.
 *
 * Tudo dentro é dimensionado em `em` sobre o `font-size` da raiz, calculado a
 * partir da largura: uma medida só governa a miniatura da lista e a maior do
 * formulário, sem um segundo jogo de tamanhos para manter em sincronia.
 */
export function CardFace({
  nickname,
  lastFour,
  brand,
  color,
  width = 116,
  isMuted = false,
  className,
}: Props) {
  const hue = toHue(color)

  return (
    <div
      aria-hidden="true"
      className={cn(
        'relative shrink-0 overflow-hidden rounded-[0.9em] transition-[filter,opacity]',
        isMuted && 'opacity-55 saturate-50',
        className,
      )}
      style={{
        width,
        height: Math.round(width / 1.586),
        fontSize: width / 11.6,
        background: hue
          // A cor do cadastro define o matiz; a luminosidade é fixa e baixa,
          // senão um cartão claro (branco, laranja) deixaria o número ilegível.
          ? `linear-gradient(135deg, hsl(${hue.h} ${hue.s}% 33%) 0%, hsl(${hue.h} ${hue.s}% 21%) 54%, hsl(${hue.h} ${hue.s}% 12%) 100%)`
          : 'linear-gradient(135deg,#2C3038,#15181D)',
        boxShadow: '0 0.5em 1.2em rgba(0,0,0,0.42), inset 0 0 0 1px rgba(255,255,255,0.09)',
      }}
    >
      {/* Brilho diagonal e o halo da cor: é o que dá volume ao retângulo. */}
      <span className="pointer-events-none absolute inset-0 bg-[linear-gradient(118deg,rgba(255,255,255,0.18)_0%,rgba(255,255,255,0.04)_34%,transparent_58%)]" />
      <span
        className="pointer-events-none absolute -bottom-[40%] -right-[22%] size-[95%] rounded-full"
        style={{ background: `radial-gradient(circle, ${color}4D, transparent 68%)` }}
      />

      <div className="relative flex h-full flex-col p-[0.85em]">
        <div className="flex items-start">
          <Chip />
          <Contactless />
        </div>

        <span className="mt-auto font-mono text-[0.95em] font-semibold leading-none tracking-[0.04em] text-white/90">
          •••• {lastFour}
        </span>

        <div className="mt-[0.5em] flex items-end gap-[0.5em]">
          <span className="min-w-0 flex-1 truncate text-[0.62em] font-bold uppercase leading-none tracking-[0.06em] text-white/70">
            {nickname}
          </span>
          <BrandMark brand={brand} />
        </div>
      </div>
    </div>
  )
}

/** O chip dourado com os contatos. */
function Chip() {
  return (
    <svg viewBox="0 0 24 18" className="h-[1.25em] w-[1.65em]" role="presentation">
      <rect width="24" height="18" rx="3" fill="url(#chip-gold)" />
      <g stroke="rgba(0,0,0,0.32)" strokeWidth="1">
        <path d="M0 6h7M17 6h7M0 12h7M17 12h7M9 0v18M15 0v18" />
      </g>
      <rect x="7" y="4" width="10" height="10" rx="1.6" fill="none" stroke="rgba(0,0,0,0.32)" />
      <defs>
        <linearGradient id="chip-gold" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#F3DFA4" />
          <stop offset="52%" stopColor="#CFAE63" />
          <stop offset="100%" stopColor="#EBD69B" />
        </linearGradient>
      </defs>
    </svg>
  )
}

/** As ondas de aproximação, que todo cartão traz hoje. */
function Contactless() {
  return (
    <svg
      viewBox="0 0 16 16"
      className="ml-auto h-[1.15em] w-[1.15em] text-white/55"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
      role="presentation"
    >
      <path d="M4 4.6a6.5 6.5 0 0 1 0 6.8M7.2 3a9.6 9.6 0 0 1 0 10M10.4 1.6a12.6 12.6 0 0 1 0 12.8" />
    </svg>
  )
}

/**
 * A marca da bandeira, desenhada em vez de carregada.
 *
 * São formas simples e vetoriais: nenhuma imagem externa para o app buscar, e
 * a miniatura continua nítida em qualquer tamanho e no modo privacidade.
 */
function BrandMark({ brand }: { brand: CardBrand }) {
  if (brand === 'mastercard') {
    return (
      <svg viewBox="0 0 37 24" className="h-[1.15em] w-[1.77em] shrink-0" role="presentation">
        <circle cx="12" cy="12" r="11" fill="#EB001B" />
        <circle cx="25" cy="12" r="11" fill="#F79E1B" fillOpacity="0.88" />
      </svg>
    )
  }

  if (brand === 'elo') {
    return (
      <span className="flex shrink-0 items-center gap-[0.22em]">
        <span className="size-[0.34em] rounded-full bg-[#FFCB05]" />
        <span className="size-[0.34em] rounded-full bg-[#EF4123]" />
        <span className="size-[0.34em] rounded-full bg-[#00A4E0]" />
        <Wordmark className="tracking-[0.02em]">elo</Wordmark>
      </span>
    )
  }

  if (brand === 'amex') {
    return (
      <span className="shrink-0 rounded-[0.22em] bg-[#2E77BC] px-[0.34em] py-[0.14em] text-[0.5em] font-extrabold uppercase leading-none tracking-[0.08em] text-white">
        Amex
      </span>
    )
  }

  if (brand === 'hipercard') {
    return <Wordmark className="tracking-[0.01em]">hipercard</Wordmark>
  }

  return <Wordmark className="italic tracking-[0.06em]">VISA</Wordmark>
}

function Wordmark({ children, className }: { children: string; className?: string }) {
  return (
    <span
      className={cn(
        'shrink-0 text-[0.62em] font-extrabold leading-none text-white/90',
        className,
      )}
    >
      {children}
    </span>
  )
}

/**
 * Matiz e saturação da cor escolhida. A luminosidade é descartada de
 * propósito: quem define o quanto o cartão é escuro é o gradiente, não o
 * usuário — do contrário a cor branca da paleta apagaria o texto.
 */
function toHue(hex: string): { h: number; s: number } | null {
  const value = hex.replace('#', '')
  const full = value.length === 3 ? value.replace(/./g, (char) => char + char) : value

  if (!/^[0-9a-fA-F]{6}$/.test(full)) {
    return null
  }

  const [r, g, b] = [0, 2, 4].map(
    (offset) => Number.parseInt(full.slice(offset, offset + 2), 16) / 255,
  ) as [number, number, number]

  const max = Math.max(r, g, b)
  const min = Math.min(r, g, b)
  const delta = max - min

  // Cinza puro não tem matiz; um azul bem dessaturado evita o cartão chapado.
  if (delta === 0) {
    return { h: 220, s: 6 }
  }

  const hue =
    max === r
      ? ((g - b) / delta + (g < b ? 6 : 0)) * 60
      : max === g
        ? ((b - r) / delta + 2) * 60
        : ((r - g) / delta + 4) * 60

  const lightness = (max + min) / 2
  const saturation = delta / (1 - Math.abs(2 * lightness - 1))

  // Saturação com teto: acima disso o gradiente vira neon e briga com o texto.
  return { h: Math.round(hue), s: Math.min(Math.round(saturation * 100), 62) }
}
