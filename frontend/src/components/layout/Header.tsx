import { useEffect, useState } from 'react'
import { Link, NavLink, useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { useUiStore } from '../../context/UiContext'
import { Logo } from './Logo'

interface HeaderProps {
  /** The home page overlays a transparent header on the hero video. */
  transparent?: boolean
}

/**
 * The Foogra header. The template drove the sticky state and the mobile drawer
 * with jQuery; here both are React state applying the same class names, so the
 * original stylesheet keeps working untouched.
 */
export function Header({ transparent = false }: HeaderProps) {
  const [stuck, setStuck] = useState(false)
  const [menuOpen, setMenuOpen] = useState(false)
  const [openSubmenu, setOpenSubmenu] = useState<string | null>(null)
  const { user, isAuthenticated, isStaff, logout } = useAuth()
  const { openSignIn } = useUiStore()
  const navigate = useNavigate()

  useEffect(() => {
    if (!transparent) {
      setStuck(true)

      return
    }

    const onScroll = () => setStuck(window.scrollY > 1)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })

    return () => window.removeEventListener('scroll', onScroll)
  }, [transparent])

  // The drawer locks the page behind it, exactly as the template's layer did.
  useEffect(() => {
    document.body.classList.toggle('nav-open', menuOpen)

    return () => document.body.classList.remove('nav-open')
  }, [menuOpen])

  const handleSignOut = async () => {
    await logout()
    navigate('/')
  }

  const toggleSubmenu = (key: string) => setOpenSubmenu((current) => (current === key ? null : key))

  /*
   * The theme ships two header variants and they are not interchangeable:
   *
   *   .header       — position:fixed and transparent, so it floats over the
   *                   home page's hero video and turns solid on scroll.
   *   .header_in    — position:relative and white, used on every inner page so
   *                   the page content is not hidden underneath it.
   *
   * Rendering .header everywhere is what buried the listing page's title bar.
   */
  const headerClass = transparent
    ? `header clearfix element_to_stick${stuck ? ' sticky' : ''}`
    : 'header_in clearfix'

  return (
    <>
      <header className={headerClass}>
        <div className="container">
          <div id="logo">
            <Link to="/" className={`foogra-logo${stuck ? ' is-stuck' : ''}`}>
              <Logo />
            </Link>
          </div>

          <ul id="top_menu">
            {isAuthenticated ? (
              <>
                <li>
                  <Link to="/account" className="login" title={user?.name}>
                    {user?.name?.split(' ')[0] ?? 'Account'}
                  </Link>
                </li>
                <li>
                  <Link to="/wishlist" className="wishlist_bt_top" title="Your wishlist">
                    Your wishlist
                  </Link>
                </li>
              </>
            ) : (
              <>
                <li>
                  <a href="#sign-in-dialog" id="sign-in" className="login" onClick={(e) => { e.preventDefault(); openSignIn() }}>
                    Sign In
                  </a>
                </li>
                <li>
                  <Link to="/wishlist" className="wishlist_bt_top" title="Your wishlist">
                    Your wishlist
                  </Link>
                </li>
              </>
            )}
          </ul>

          <a href="#0" className="open_close" onClick={(e) => { e.preventDefault(); setMenuOpen(true) }}>
            <i className="icon_menu" />
            <span>Menu</span>
          </a>

          <nav className={`main-menu${menuOpen ? ' show' : ''}`}>
            <div id="header_menu">
              <a href="#0" className="open_close" onClick={(e) => { e.preventDefault(); setMenuOpen(false) }}>
                <i className="icon_close" />
                <span>Menu</span>
              </a>
              <Link to="/" className="foogra-logo is-stuck">
                <Logo />
              </Link>
            </div>

            <ul onClick={() => setMenuOpen(false)}>
              <li>
                <NavLink to="/" end>
                  Home
                </NavLink>
              </li>
              <li>
                <NavLink to="/restaurants">Restaurants</NavLink>
              </li>
              <li className={`submenu${openSubmenu === 'account' ? ' show_normal' : ''}`}>
                <a
                  href="#0"
                  className="show-submenu"
                  onClick={(e) => { e.preventDefault(); e.stopPropagation(); toggleSubmenu('account') }}
                >
                  My Foogra
                </a>
                <ul style={openSubmenu === 'account' ? { display: 'block' } : undefined}>
                  <li><Link to="/wishlist">Wishlist</Link></li>
                  <li><Link to="/bookings">My bookings</Link></li>
                  <li><Link to="/reviews">My reviews</Link></li>
                  <li><Link to="/account">Account settings</Link></li>
                  <li><Link to="/booking-lookup">Find a booking</Link></li>
                </ul>
              </li>
              {isStaff && (
                <li>
                  <NavLink to="/admin">Dashboard</NavLink>
                </li>
              )}
              {isAuthenticated ? (
                <li>
                  <a href="#0" onClick={(e) => { e.preventDefault(); void handleSignOut() }}>
                    Sign out
                  </a>
                </li>
              ) : (
                <li>
                  <a href="#0" onClick={(e) => { e.preventDefault(); openSignIn() }}>
                    Sign in
                  </a>
                </li>
              )}
            </ul>
          </nav>
        </div>
      </header>

      {/* Opacity mask behind the mobile drawer. */}
      <div className={`layer${menuOpen ? ' layer-is-visible' : ''}`} onClick={() => setMenuOpen(false)} />
    </>
  )
}
