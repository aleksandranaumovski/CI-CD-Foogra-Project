import { useCallback, useEffect } from 'react'

interface LightboxProps {
  images: { url: string; caption?: string | null }[]
  index: number | null
  onClose: () => void
  onNavigate: (index: number) => void
}

/** Replaces the template's magnific-popup gallery. Arrow keys and Esc work. */
export function Lightbox({ images, index, onClose, onNavigate }: LightboxProps) {
  const step = useCallback(
    (delta: number) => {
      if (index === null || images.length === 0) return

      onNavigate((index + delta + images.length) % images.length)
    },
    [index, images.length, onNavigate],
  )

  useEffect(() => {
    if (index === null) return

    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
      if (e.key === 'ArrowRight') step(1)
      if (e.key === 'ArrowLeft') step(-1)
    }

    window.addEventListener('keydown', onKey)
    document.body.style.overflow = 'hidden'

    return () => {
      window.removeEventListener('keydown', onKey)
      document.body.style.overflow = ''
    }
  }, [index, onClose, step])

  if (index === null || !images[index]) return null

  const image = images[index]

  return (
    <div className="foogra-lightbox" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <button type="button" className="foogra-lightbox-close" onClick={onClose} aria-label="Close">
        <i className="icon_close" />
      </button>

      {images.length > 1 && (
        <button type="button" className="foogra-lightbox-nav prev" onClick={() => step(-1)} aria-label="Previous">
          <i className="arrow_carrot-left" />
        </button>
      )}

      <figure>
        <img src={image.url} alt={image.caption ?? ''} />
        {image.caption && <figcaption>{image.caption}</figcaption>}
        <span className="foogra-lightbox-count">
          {index + 1} / {images.length}
        </span>
      </figure>

      {images.length > 1 && (
        <button type="button" className="foogra-lightbox-nav next" onClick={() => step(1)} aria-label="Next">
          <i className="arrow_carrot-right" />
        </button>
      )}
    </div>
  )
}
