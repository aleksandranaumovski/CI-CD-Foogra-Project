import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { catalogue } from '../lib/services'
import { Carousel } from '../components/ui/Carousel'
import { RestaurantStrip } from '../components/ui/RestaurantStrip'
import { StripSkeleton, ErrorState } from '../components/ui/States'
import type { RestaurantCard } from '../lib/types'

/** The hero's still fallback, and the video's poster frame. */
const HERO_POSTER = '/img/hero_general.jpg'

/**
 * Whether to play the hero video.
 *
 * The template ships *placeholder* clips — `intro.mp4` is a static grey card
 * reading "4K VIDEO PLACEHOLDER" (3840x2160, 30s, but only ~470 kbps, where
 * real 4K needs 20,000+). Playing it looks broken, so the hero shows the still
 * image instead.
 *
 * Flip this to `true` once a real clip is in `frontend/public/video/intro.mp4`.
 * `frontend/check-hero-video.py` will tell you whether what you dropped in is
 * real footage or another placeholder.
 */
const HERO_VIDEO_READY = false

/**
 * Page 1 — a port of `index-13.html` (Parallax Video Fullscreen).
 *
 * The template's jarallax plugin injected a background <video>; here it is a
 * real element behind the same `.hero_single.fullscreen.video_bg` wrapper, so
 * the original CSS and opacity mask apply unchanged. Everything below the hero
 * is fed by the API instead of the hard-coded markup.
 */
export function HomePage() {
  const navigate = useNavigate()
  const [what, setWhat] = useState('')
  const [where, setWhere] = useState('')

  const categories = useQuery({ queryKey: ['categories'], queryFn: catalogue.categories })
  const featured = useQuery({ queryKey: ['restaurants', 'featured'], queryFn: () => catalogue.featured(8) })
  const deals = useQuery({ queryKey: ['restaurants', 'deals'], queryFn: () => catalogue.deals(6) })

  const search = (e: React.FormEvent) => {
    e.preventDefault()
    const params = new URLSearchParams()
    if (what.trim()) params.set('q', what.trim())
    if (where.trim()) params.set('location', where.trim())
    navigate(`/restaurants?${params.toString()}`)
  }

  const scrollToContent = (e: React.MouseEvent) => {
    e.preventDefault()
    document.getElementById('first_section')?.scrollIntoView({ behavior: 'smooth' })
  }

  return (
    <>
      {/* ------------------------------------------------ hero: fullscreen video */}
      <div
        className="hero_single fullscreen video_bg foogra-hero-video"
        style={{
          // Doubles as the video's backdrop while it buffers, and is the whole
          // hero while HERO_VIDEO_READY is false.
          backgroundImage: `url(${HERO_POSTER})`,
          backgroundSize: 'cover',
          backgroundPosition: 'center center',
        }}
      >
        {HERO_VIDEO_READY && (
          <video
            className="foogra-hero-media"
            autoPlay
            muted
            loop
            playsInline
            poster={HERO_POSTER}
          >
            {/*
              MP4/H.264 first: every current browser plays it, so it is what
              actually gets used. WebM stays as a secondary. The template's
              third source was Ogg Theora, dropped — no browser needs it any
              more, and keeping it risked serving the placeholder clip to
              something that preferred it.
            */}
            <source src="/video/intro.mp4" type="video/mp4" />
            <source src="/video/intro.webm" type="video/webm" />
          </video>
        )}

        <div className="opacity-mask foogra-hero-mask">
          <div className="container">
            <div className="row justify-content-center">
              <div className="col-xl-9 col-lg-10 col-md-8">
                <h1>Discover &amp; Book</h1>
                <p>The best restaurants at the best price</p>

                <form onSubmit={search}>
                  <div className="row g-0 custom-search-input">
                    <div className="col-lg-4">
                      <div className="form-group">
                        <input
                          className="form-control"
                          type="text"
                          placeholder="What are you looking for..."
                          value={what}
                          onChange={(e) => setWhat(e.target.value)}
                          aria-label="What are you looking for"
                        />
                        <i className="icon_search" />
                      </div>
                    </div>
                    <div className="col-lg-6">
                      <div className="form-group">
                        <input
                          className="form-control no_border_r"
                          type="text"
                          placeholder="Address, neighborhood..."
                          value={where}
                          onChange={(e) => setWhere(e.target.value)}
                          aria-label="Address or neighbourhood"
                        />
                        <i className="icon_pin_alt" />
                      </div>
                    </div>
                    <div className="col-lg-2">
                      <input type="submit" value="Search" />
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>

        <a href="#first_section" className="btn_explore" onClick={scrollToContent}>
          <span className="pulse"><i className="arrow_down" /></span>
        </a>
      </div>

      {/* -------------------------------------------------- popular categories */}
      <div className="bg_gray" id="first_section">
        <div className="container margin_60_40">
          <div className="main_title center">
            <span><em /></span>
            <h2>Popular Categories</h2>
            <p>Browse by cuisine and find your next favourite table</p>
          </div>

          {categories.isPending && (
            <div className="row">
              {Array.from({ length: 5 }).map((_, i) => (
                <div className="col" key={i}><StripSkeleton /></div>
              ))}
            </div>
          )}

          {categories.isError && (
            <ErrorState message="Categories could not be loaded." onRetry={() => categories.refetch()} />
          )}

          {/*
            `owl-theme` is not decorative: home.css scopes the whole tile
            treatment to `.owl-theme.categories_carousel .item a`.
          */}
          {categories.data && categories.data.length > 0 && (
            <div className="owl-theme categories_carousel">
              <Carousel slides={{ base: 2, sm: 3, md: 4, lg: 5, xl: 6 }}>
                {categories.data.map((category) => (
                  <div className="item" key={category.id}>
                    <Link to={`/restaurants?categories[]=${category.slug}`}>
                      <span>{category.restaurants_count ?? 0}</span>
                      <i className={category.icon} />
                      <h3>{category.name}</h3>
                      <small>Avg price ${Math.round(category.average_price)}</small>
                    </Link>
                  </div>
                ))}
              </Carousel>
            </div>
          )}
        </div>
      </div>

      <div className="container margin_60_40">
        {/* ------------------------------------------------ popular restaurants */}
        <div className="main_title">
          <span><em /></span>
          <h2>Popular Restaurants</h2>
          <p>Hand-picked venues, ranked by what diners actually scored them</p>
          <Link to="/restaurants?featured=1">View All</Link>
        </div>

        {featured.isPending && (
          <div className="row">
            {Array.from({ length: 4 }).map((_, i) => (
              <div className="col-xl-3 col-lg-4 col-md-6" key={i}><StripSkeleton /></div>
            ))}
          </div>
        )}

        {featured.isError && (
          <ErrorState message="Restaurants could not be loaded." onRetry={() => featured.refetch()} />
        )}

        {featured.data && featured.data.length > 0 && (
          <Carousel slides={{ base: 1, sm: 2, md: 3, xl: 4 }}>
            {featured.data.map((restaurant) => (
              <RestaurantStrip key={restaurant.id} restaurant={restaurant} footer="status" />
            ))}
          </Carousel>
        )}

        {/* ------------------------------------------------------------ banner */}
        <div className="banner" style={{ backgroundImage: 'url(/img/banner_bg_desktop.jpg)' }}>
          {/*
            The copy is white and left-aligned, over the lit wine crates on the
            left of the photo. A flat 20% mask left it marginal, so the mask is
            weighted to the left: dark enough to read against, while the chefs
            and the laid table on the right stay visible.
          */}
          <div
            className="wrapper d-flex align-items-center opacity-mask"
            style={{
              background:
                'linear-gradient(90deg, rgba(0,0,0,0.75) 0%, rgba(0,0,0,0.55) 40%, rgba(0,0,0,0.25) 100%)',
            }}
          >
            <div>
              <small>foogra</small>
              <h3>More than 3000 Restaurants</h3>
              <p>Book a table easily at the best price</p>
              <Link to="/restaurants" className="btn_1">View All</Link>
            </div>
          </div>
        </div>

        {/* --------------------------------------------------- our very best deals */}
        <div className="row">
          <div className="col-12">
            <div className="main_title version_2">
              <span><em /></span>
              <h2>Our Very Best Deals</h2>
              <p>The biggest discounts running across the directory right now</p>
              <Link to="/restaurants?has_discount=1&sort=price">View All</Link>
            </div>
          </div>

          {deals.isPending && (
            <div className="col-12"><StripSkeleton /></div>
          )}

          {deals.isError && (
            <div className="col-12">
              <ErrorState message="Deals could not be loaded." onRetry={() => deals.refetch()} />
            </div>
          )}

          {deals.data && splitInHalf(deals.data).map((column, columnIndex) => (
            <div className="col-md-6" key={columnIndex}>
              <div className="list_home">
                <ul>
                  {column.map((restaurant) => (
                    <li key={restaurant.id}>
                      <Link to={`/restaurants/${restaurant.slug}`}>
                        <figure>
                          <img
                            src={restaurant.thumbnail ?? '/img/location_list_placeholder.png'}
                            alt={restaurant.name}
                            loading="lazy"
                          />
                        </figure>
                        <div className="score"><strong>{restaurant.rating.score.toFixed(1)}</strong></div>
                        <em>{restaurant.category?.name ?? 'Restaurant'}</em>
                        <h3>{restaurant.name}</h3>
                        <small>{restaurant.address}</small>
                        <ul>
                          {restaurant.discount_percent ? (
                            <li><span className="ribbon off">-{restaurant.discount_percent}%</span></li>
                          ) : null}
                          <li>Average price ${Math.round(restaurant.average_price)}</li>
                        </ul>
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          ))}
        </div>

        <p className="text-center d-block d-md-block d-lg-none">
          <Link to="/restaurants" className="btn_1">View All</Link>
        </p>
      </div>

      {/* ------------------------------------------------------- call to action */}
      <div className="call_section" style={{ backgroundImage: 'url(/img/bg_call_section.jpg)' }}>
        <div className="container clearfix">
          <div className="col-lg-5 col-md-6 float-end wow">
            <div className="box_1">
              <h3>Are you a Restaurant Owner?</h3>
              <p>
                Join us to increase your online visibility. You will reach more customers looking to
                enjoy your dishes, and manage bookings, menus and reviews from one dashboard.
              </p>
              <Link to="/register-owner" className="btn_1">Read more</Link>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}

/** Splits the deals list into the template's two side-by-side columns. */
function splitInHalf(items: RestaurantCard[]): RestaurantCard[][] {
  const midpoint = Math.ceil(items.length / 2)

  return [items.slice(0, midpoint), items.slice(midpoint)]
}
