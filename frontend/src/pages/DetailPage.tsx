import { useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { catalogue } from '../lib/services'
import { toApiError } from '../lib/api'
import { Loader, ErrorState } from '../components/ui/States'
import { Lightbox } from '../components/ui/Lightbox'
import { WishlistButton } from '../components/ui/WishlistButton'
import { BookingWidget } from '../components/detail/BookingWidget'
import { ReviewsTab } from '../components/detail/ReviewsTab'
import type { MenuSection, OpeningHour } from '../lib/types'

/**
 * Page 3 — a port of `detail-restaurant.html`.
 *
 * The hero, Information/Reviews tabs, photo gallery, menu and booking sidebar
 * all come from a single `/restaurants/{slug}` call, which eager-loads the
 * category, owner, gallery, opening hours, menu and first page of reviews.
 */
export function DetailPage() {
  const { slug = '' } = useParams()
  const [tab, setTab] = useState<'info' | 'reviews'>('info')
  const [lightboxIndex, setLightboxIndex] = useState<number | null>(null)

  const query = useQuery({
    queryKey: ['restaurant', slug],
    queryFn: () => catalogue.restaurant(slug),
    enabled: Boolean(slug),
  })

  const restaurant = query.data?.data
  const gallery = useMemo(
    () => (restaurant?.images.gallery ?? []).map((image) => ({ url: image.url, caption: image.caption })),
    [restaurant],
  )

  if (query.isPending) {
    return <div className="container margin_60_40"><Loader label="Loading restaurant…" /></div>
  }

  if (query.isError || !restaurant) {
    const error = toApiError(query.error)

    return (
      <div className="container margin_60_40">
        <ErrorState
          message={error.status === 404 ? 'We could not find that restaurant.' : error.message}
          onRetry={error.status === 404 ? undefined : () => query.refetch()}
        />
        <p className="text-center"><Link to="/restaurants" className="btn_1">Browse all restaurants</Link></p>
      </div>
    )
  }

  const breakdown = query.data.rating_breakdown
  const { location, contact } = restaurant
  const specialOffers = restaurant.menu_sections?.filter((s) => s.is_special_offers) ?? []
  const regularSections = restaurant.menu_sections?.filter((s) => !s.is_special_offers) ?? []

  return (
    <>
      {/* --------------------------------------------------------------- hero */}
      <div
        className="hero_in detail_page background-image"
        style={{ backgroundImage: `url(${restaurant.images.hero ?? '/img/restaurant_detail_hero.jpg'})` }}
      >
        <div className="wrapper opacity-mask" style={{ background: 'rgba(0,0,0,0.5)' }}>
          <div className="container">
            <div className="main_info">
              <div className="row">
                <div className="col-xl-6 col-lg-7 col-md-8">
                  <div className="head">
                    <div className="score">
                      <span>
                        {restaurant.rating.label}
                        <em>{restaurant.rating.reviews_count} Reviews</em>
                      </span>
                      <strong>{restaurant.rating.score.toFixed(1)}</strong>
                    </div>
                  </div>

                  <h1>{restaurant.name}</h1>

                  <div className="foogra-hero-meta">
                    {restaurant.category?.name?.toUpperCase()} — {location.address}
                    {location.postal_code ? `, ${location.postal_code}` : ''}
                    {location.directions_url && (
                      <>
                        {' — '}
                        <a href={location.directions_url} target="_blank" rel="noreferrer">Get directions</a>
                      </>
                    )}
                    {restaurant.is_open_now !== undefined && (
                      <span className={restaurant.is_open_now ? 'loc_open' : 'loc_closed'}>
                        {restaurant.is_open_now ? 'Now Open' : 'Now Closed'}
                      </span>
                    )}
                  </div>
                </div>

                <div className="col-xl-6 col-lg-5 col-md-4 position-relative">
                  <div className="buttons clearfix">
                    {gallery.length > 0 && (
                      <a
                        href="#0"
                        className="btn_hero"
                        onClick={(e) => { e.preventDefault(); setLightboxIndex(0) }}
                      >
                        <i className="icon_image" />View photos
                      </a>
                    )}
                    <WishlistButton
                      slug={restaurant.slug}
                      saved={Boolean(restaurant.is_wishlisted)}
                      variant="hero"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="container margin_detail">
        <div className="row">
          <div className="col-lg-8">
            <div className="tabs_detail">
              <ul className="nav nav-tabs" role="tablist">
                <li className="nav-item">
                  <a
                    href="#pane-A"
                    className={`nav-link${tab === 'info' ? ' active' : ''}`}
                    onClick={(e) => { e.preventDefault(); setTab('info') }}
                    role="tab"
                  >
                    Information
                  </a>
                </li>
                <li className="nav-item">
                  <a
                    href="#pane-B"
                    className={`nav-link${tab === 'reviews' ? ' active' : ''}`}
                    onClick={(e) => { e.preventDefault(); setTab('reviews') }}
                    role="tab"
                  >
                    Reviews ({restaurant.rating.reviews_count})
                  </a>
                </li>
              </ul>

              <div className="tab-content">
                {/* ------------------------------------------- information */}
                {tab === 'info' && (
                  <div className="card tab-pane fade show active" role="tabpanel">
                    <div className="card-body info_content">
                      {restaurant.description
                        ?.split('\n\n')
                        .map((paragraph, index) => <p key={index}>{paragraph}</p>)}

                      {gallery.length > 0 && (
                        <>
                          <div className="add_bottom_25" />
                          <h2>Pictures from our users</h2>
                          <div className="pictures clearfix">
                            {gallery.slice(0, 5).map((image, index) => (
                              <figure key={index}>
                                <a
                                  href="#0"
                                  onClick={(e) => { e.preventDefault(); setLightboxIndex(index) }}
                                >
                                  {index === 4 && gallery.length > 5 && (
                                    <span className="d-flex align-items-center justify-content-center">
                                      +{gallery.length - 5}
                                    </span>
                                  )}
                                  <img
                                    src={restaurant.images.gallery?.[index].thumbnail_url}
                                    alt={image.caption ?? ''}
                                    loading="lazy"
                                  />
                                </a>
                              </figure>
                            ))}
                          </div>
                        </>
                      )}

                      {/* ------------------------------------------- menu */}
                      {regularSections.length > 0 && (
                        <>
                          <h2>{restaurant.name} Menu</h2>
                          {regularSections.map((section, index) => (
                            <MenuBlock key={section.id} section={section} withRule={index > 0} />
                          ))}
                        </>
                      )}

                      {specialOffers.map((section) => (
                        <div className="special_offers add_bottom_45" key={section.id}>
                          <h2>{section.name}</h2>
                          {section.dishes?.map((dish) => (
                            <div className="menu_item" key={dish.id}>
                              <em>£{dish.price.toFixed(2)}</em>
                              <h4>{dish.name}</h4>
                              <p>{dish.description}</p>
                            </div>
                          ))}
                        </div>
                      ))}

                      {/* --------------------------------------- other info */}
                      <div className="other_info">
                        <h2>How to get to {restaurant.name}</h2>
                        <div className="row">
                          <div className="col-md-4">
                            <h3>Address</h3>
                            <p>
                              {location.address}
                              {location.city ? <><br />{location.city}</> : null}
                              {location.postal_code ? ` ${location.postal_code}` : ''}
                              {location.directions_url && (
                                <>
                                  <br />
                                  <a href={location.directions_url} target="_blank" rel="noreferrer">
                                    <strong>Get directions</strong>
                                  </a>
                                </>
                              )}
                            </p>

                            {contact.phone && <p><strong>Phone</strong><br />{contact.phone}</p>}
                            {contact.website && (
                              <p>
                                <strong>Website</strong><br />
                                <a href={contact.website} target="_blank" rel="noreferrer">
                                  {contact.website.replace(/^https?:\/\//, '')}
                                </a>
                              </p>
                            )}

                            {(restaurant.social_links.facebook ||
                              restaurant.social_links.instagram ||
                              restaurant.social_links.twitter) && (
                              <>
                                <strong>Follow Us</strong><br />
                                <p className="follow_us_detail">
                                  {restaurant.social_links.facebook && (
                                    <a href={restaurant.social_links.facebook} target="_blank" rel="noreferrer">
                                      <i className="social_facebook_square" />
                                    </a>
                                  )}
                                  {restaurant.social_links.instagram && (
                                    <a href={restaurant.social_links.instagram} target="_blank" rel="noreferrer">
                                      <i className="social_instagram_square" />
                                    </a>
                                  )}
                                  {restaurant.social_links.twitter && (
                                    <a href={restaurant.social_links.twitter} target="_blank" rel="noreferrer">
                                      <i className="social_twitter_square" />
                                    </a>
                                  )}
                                </p>
                              </>
                            )}
                          </div>

                          <div className="col-md-4">
                            <h3>Opening Time</h3>
                            <OpeningHours hours={restaurant.opening_hours ?? []} />
                          </div>

                          <div className="col-md-4">
                            <h3>Services</h3>
                            {restaurant.payment_methods.length > 0 && (
                              <p><strong>Credit Cards</strong><br />{restaurant.payment_methods.join(', ')}</p>
                            )}
                            {restaurant.services.length > 0 && (
                              <p><strong>Other</strong><br />{restaurant.services.join(', ')}</p>
                            )}
                            <p><strong>Average price</strong><br />£{restaurant.average_price.toFixed(2)} per person</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                )}

                {/* ----------------------------------------------- reviews */}
                {tab === 'reviews' && (
                  <div className="card tab-pane fade show active" role="tabpanel">
                    <ReviewsTab restaurant={restaurant} breakdown={breakdown} />
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* ------------------------------------------------------- sidebar */}
          <div className="col-lg-4" id="sidebar_fixed">
            <BookingWidget restaurant={restaurant} />

            <ul className="share-buttons">
              <li>
                <a
                  className="fb-share"
                  href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(window.location.href)}`}
                  target="_blank"
                  rel="noreferrer"
                >
                  <i className="social_facebook" /> Share
                </a>
              </li>
              <li>
                <a
                  className="twitter-share"
                  href={`https://x.com/intent/tweet?url=${encodeURIComponent(window.location.href)}&text=${encodeURIComponent(restaurant.name)}`}
                  target="_blank"
                  rel="noreferrer"
                >
                  <i className="social_twitter" /> Share
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <Lightbox
        images={gallery}
        index={lightboxIndex}
        onClose={() => setLightboxIndex(null)}
        onNavigate={setLightboxIndex}
      />
    </>
  )
}

function MenuBlock({ section, withRule }: { section: MenuSection; withRule: boolean }) {
  if (!section.dishes || section.dishes.length === 0) return null

  return (
    <>
      {withRule && <hr />}
      <h3>{section.name}</h3>
      {section.description && <p className="foogra-muted">{section.description}</p>}
      {section.dishes.map((dish) => (
        <div className="menu_item" key={dish.id}>
          <em>£{dish.price.toFixed(2)}</em>
          <h4>
            {dish.name}
            {dish.is_vegetarian && <span className="foogra-veg" title="Vegetarian">V</span>}
          </h4>
          <p>{dish.description}</p>
          {dish.allergens.length > 0 && (
            <small className="foogra-allergens">Contains: {dish.allergens.join(', ')}</small>
          )}
        </div>
      ))}
    </>
  )
}

/** Collapses the 14 timetable rows into "Mon. to Sat." style lines. */
function OpeningHours({ hours }: { hours: OpeningHour[] }) {
  if (hours.length === 0) return <p className="foogra-muted">Opening times not published.</p>

  const services: ('lunch' | 'dinner')[] = ['lunch', 'dinner']
  const closedDays = hours
    .filter((h) => h.is_closed && h.service === 'lunch')
    .map((h) => h.day_name)

  return (
    <>
      {services.map((service) => {
        const open = hours.filter((h) => h.service === service && !h.is_closed)

        if (open.length === 0) return null

        const days = open.map((h) => h.day_name.slice(0, 3))
        const range = days.length > 1 ? `${days[0]}. to ${days[days.length - 1]}.` : `${days[0]}.`

        return (
          <p key={service}>
            <strong>{service === 'lunch' ? 'Lunch' : 'Dinner'}</strong><br />
            {range} {open[0].opens_at} - {open[0].closes_at}
          </p>
        )
      })}

      {closedDays.length > 0 && (
        <p><span className="loc_closed">{closedDays.join(', ')} Closed</span></p>
      )}
    </>
  )
}
