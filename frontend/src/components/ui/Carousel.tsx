import { useCallback, useEffect, useState } from 'react'
import type { ReactNode } from 'react'
import useEmblaCarousel from 'embla-carousel-react'

interface CarouselProps {
  children: ReactNode[]
  /** Slides visible at each breakpoint, mirroring the template's owl settings. */
  slides?: { base: number; sm?: number; md?: number; lg?: number; xl?: number }
  className?: string
}

/**
 * Replaces the template's jQuery Owl Carousel. Embla handles the dragging and
 * breakpoints; the arrows and dots are styled by the theme's `.owl-*` classes,
 * so it looks identical without shipping jQuery.
 */
export function Carousel({ children, slides = { base: 1, sm: 2, md: 3, xl: 4 }, className = '' }: CarouselProps) {
  const [emblaRef, embla] = useEmblaCarousel({ align: 'start', loop: false, slidesToScroll: 1 })
  const [canPrev, setCanPrev] = useState(false)
  const [canNext, setCanNext] = useState(false)
  const [selected, setSelected] = useState(0)
  const [snaps, setSnaps] = useState<number[]>([])

  const onSelect = useCallback(() => {
    if (!embla) return

    setCanPrev(embla.canScrollPrev())
    setCanNext(embla.canScrollNext())
    setSelected(embla.selectedScrollSnap())
  }, [embla])

  useEffect(() => {
    if (!embla) return

    setSnaps(embla.scrollSnapList())
    onSelect()
    embla.on('select', onSelect).on('reInit', onSelect)
  }, [embla, onSelect])

  // Per-breakpoint slide widths, expressed as CSS custom properties.
  const style = {
    '--slides-base': slides.base,
    '--slides-sm': slides.sm ?? slides.base,
    '--slides-md': slides.md ?? slides.sm ?? slides.base,
    '--slides-lg': slides.lg ?? slides.md ?? slides.sm ?? slides.base,
    '--slides-xl': slides.xl ?? slides.lg ?? slides.md ?? slides.sm ?? slides.base,
  } as React.CSSProperties

  return (
    <div className={`foogra-carousel ${className}`} style={style}>
      <div className="foogra-carousel-viewport" ref={emblaRef}>
        <div className="foogra-carousel-track">
          {children.map((child, index) => (
            <div className="foogra-carousel-slide" key={index}>
              {child}
            </div>
          ))}
        </div>
      </div>

      {snaps.length > 1 && (
        <>
          <button
            type="button"
            className="foogra-carousel-nav prev"
            onClick={() => embla?.scrollPrev()}
            disabled={!canPrev}
            aria-label="Previous"
          >
            <i className="arrow_carrot-left" />
          </button>
          <button
            type="button"
            className="foogra-carousel-nav next"
            onClick={() => embla?.scrollNext()}
            disabled={!canNext}
            aria-label="Next"
          >
            <i className="arrow_carrot-right" />
          </button>

          <div className="foogra-carousel-dots">
            {snaps.map((_, index) => (
              <button
                key={index}
                type="button"
                className={index === selected ? 'active' : undefined}
                onClick={() => embla?.scrollTo(index)}
                aria-label={`Go to slide ${index + 1}`}
              />
            ))}
          </div>
        </>
      )}
    </div>
  )
}
