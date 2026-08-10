import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useUiStore } from '../../context/UiContext'

/** The Foogra footer, with the accordion behaviour handled in React. */
export function Footer() {
  const [openPanel, setOpenPanel] = useState<string | null>(null)
  const [email, setEmail] = useState('')
  const { notify } = useUiStore()

  const toggle = (key: string) => setOpenPanel((current) => (current === key ? null : key))
  const panelClass = (key: string) => `collapse dont-collapse-sm${openPanel === key ? ' show' : ''}`

  return (
    <footer>
      <div className="container">
        <div className="row">
          <div className="col-lg-3 col-md-6">
            <h3 onClick={() => toggle('links')}>Quick Links</h3>
            <div className={`${panelClass('links')} links`}>
              <ul>
                <li><Link to="/restaurants">Browse restaurants</Link></li>
                <li><Link to="/bookings">My bookings</Link></li>
                <li><Link to="/booking-lookup">Find a booking</Link></li>
                <li><Link to="/account">My account</Link></li>
                <li><Link to="/wishlist">Wishlist</Link></li>
              </ul>
            </div>
          </div>

          <div className="col-lg-3 col-md-6">
            <h3 onClick={() => toggle('categories')}>Categories</h3>
            <div className={`${panelClass('categories')} links`}>
              <ul>
                <li><Link to="/restaurants?sort=popularity">Top Categories</Link></li>
                <li><Link to="/restaurants?sort=rating">Best Rated</Link></li>
                <li><Link to="/restaurants?sort=price">Best Price</Link></li>
                <li><Link to="/restaurants?sort=date">Latest Submissions</Link></li>
              </ul>
            </div>
          </div>

          <div className="col-lg-3 col-md-6">
            <h3 onClick={() => toggle('contacts')}>Contacts</h3>
            <div className={`${panelClass('contacts')} contacts`}>
              <ul>
                <li><i className="icon_house_alt" />97845 Baker st. 567<br />Los Angeles - US</li>
                <li><i className="icon_mobile" />+94 423-23-221</li>
                <li><i className="icon_mail_alt" /><a href="mailto:info@foogra.test">info@foogra.test</a></li>
              </ul>
            </div>
          </div>

          <div className="col-lg-3 col-md-6">
            <h3 onClick={() => toggle('newsletter')}>Keep in touch</h3>
            <div className={panelClass('newsletter')}>
              <div id="newsletter">
                <form
                  onSubmit={(e) => {
                    e.preventDefault()
                    notify(`Thanks — ${email} is on the list.`)
                    setEmail('')
                  }}
                >
                  <div className="form-group">
                    <input
                      type="email"
                      required
                      className="form-control"
                      placeholder="Your email"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                    />
                    <button type="submit"><i className="arrow_carrot-right" /></button>
                  </div>
                </form>
              </div>
              <div className="follow_us">
                <h5>Follow Us</h5>
                <ul>
                  <li><a href="#0"><i className="bi bi-facebook" /></a></li>
                  <li><a href="#0"><i className="bi bi-twitter-x" /></a></li>
                  <li><a href="#0"><i className="bi bi-instagram" /></a></li>
                  <li><a href="#0"><i className="bi bi-tiktok" /></a></li>
                  <li><a href="#0"><i className="bi bi-whatsapp" /></a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <hr />

        <div className="row add_bottom_25">
          <div className="col-lg-6">
            <ul className="footer-selector clearfix">
              <li>
                <div className="styled-select lang-selector">
                  <select defaultValue="English">
                    <option value="English">English</option>
                    <option value="French">French</option>
                    <option value="Spanish">Spanish</option>
                  </select>
                </div>
              </li>
              <li>
                <div className="styled-select currency-selector">
                  <select defaultValue="US Dollars">
                    <option value="US Dollars">US Dollars</option>
                    <option value="Euro">Euro</option>
                  </select>
                </div>
              </li>
              <li><img src="/img/cards_all.svg" alt="Accepted cards" width={198} height={30} /></li>
            </ul>
          </div>
          <div className="col-lg-6">
            <ul className="additional_links">
              <li><a href="#0">Terms and conditions</a></li>
              <li><a href="#0">Privacy</a></li>
              <li><span>© Foogra</span></li>
            </ul>
          </div>
        </div>
      </div>
    </footer>
  )
}
