import { useEffect } from 'react'
import { Outlet, useLocation } from 'react-router-dom'
import { Header } from './Header'
import { Footer } from './Footer'
import { SignInModal } from './SignInModal'
import { Toasts } from './Toasts'
import { BackToTop } from './BackToTop'

/** The public site shell. Only the home page gets the transparent header. */
export function Layout() {
  const { pathname } = useLocation()
  const isHome = pathname === '/'

  // Fresh page, fresh scroll position.
  useEffect(() => {
    window.scrollTo(0, 0)
  }, [pathname])

  return (
    <>
      <Header transparent={isHome} />
      <main>
        <Outlet />
      </main>
      <Footer />
      <BackToTop />
      <SignInModal />
      <Toasts />
    </>
  )
}
