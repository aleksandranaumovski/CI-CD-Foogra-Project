import { useEffect, useState } from 'react'

/** The template's #toTop button, driven by scroll position. */
export function BackToTop() {
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    const onScroll = () => setVisible(window.scrollY > 300)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })

    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  return (
    <div
      id="toTop"
      className={visible ? 'visible' : undefined}
      onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
    />
  )
}
