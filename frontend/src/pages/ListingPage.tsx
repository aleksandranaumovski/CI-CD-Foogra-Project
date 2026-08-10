import { useEffect, useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { catalogue } from '../lib/services'
import { RestaurantStrip } from '../components/ui/RestaurantStrip'
import { RestaurantMap } from '../components/ui/RestaurantMap'
import { Pagination } from '../components/ui/Pagination'
import { StripSkeleton, EmptyState, ErrorState } from '../components/ui/States'
import { toApiError } from '../lib/api'
import type { RestaurantFilters } from '../lib/types'

const SORT_OPTIONS = [
  { value: 'popularity', label: 'Sort by Popularity' },
  { value: 'rating', label: 'Sort by Average rating' },
  { value: 'date', label: 'Sort by newness' },
  { value: 'price', label: 'Sort by Price: low to high' },
  { value: 'price-desc', label: 'Sort by Price: high to low' },
]

/** Roughly central London — the origin for the radius slider. */
const CITY_CENTRE = { lat: 51.5074, lng: -0.1278 }

/**
 * Page 2 — a port of `grid-listing-filterscol-full-width.html`.
 *
 * Every filter lives in the URL, so a filtered view is shareable, survives a
 * refresh, and the browser's back button steps through the search history.
 */
export function ListingPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [filtersOpen, setFiltersOpen] = useState(false)
  const [mapOpen, setMapOpen] = useState(false)
  const [searchDraft, setSearchDraft] = useState(searchParams.get('q') ?? '')

  const filters = useMemo(() => parseFilters(searchParams), [searchParams])

  useEffect(() => {
    setSearchDraft(searchParams.get('q') ?? '')
  }, [searchParams])

  const query = useQuery({
    queryKey: ['restaurants', filters],
    queryFn: () => catalogue.restaurants(filters),
    // Keeps the current grid on screen while the next page loads.
    placeholderData: keepPreviousData,
  })

  const categories = useQuery({ queryKey: ['categories'], queryFn: catalogue.categories })

  /** Writes a patch into the URL, resetting to page 1 unless paging. */
  const update = (patch: Record<string, unknown>) => {
    const next = new URLSearchParams(searchParams)

    for (const [key, value] of Object.entries(patch)) {
      next.delete(key)
      next.delete(`${key}[]`)

      if (value === undefined || value === null || value === '' || value === false) continue

      if (Array.isArray(value)) {
        value.forEach((item) => next.append(`${key}[]`, String(item)))
      } else if (value === true) {
        next.set(key, '1')
      } else {
        next.set(key, String(value))
      }
    }

    if (!('page' in patch)) next.delete('page')

    setSearchParams(next, { preventScrollReset: true })
  }

  const toggleCategory = (slug: string) => {
    const current = filters.categories ?? []
    update({ categories: current.includes(slug) ? current.filter((s) => s !== slug) : [...current, slug] })
  }

  const clearAll = () => setSearchParams(new URLSearchParams(), { preventScrollReset: true })

  const activeFilterCount =
    (filters.categories?.length ?? 0) +
    (filters.min_rating ? 1 : 0) +
    (filters.max_price !== undefined ? 1 : 0) +
    (filters.radius ? 1 : 0) +
    (filters.open_now ? 1 : 0) +
    (filters.has_discount ? 1 : 0) +
    (filters.featured ? 1 : 0)

  const total = query.data?.meta.total ?? 0
  const facets = query.data?.facets
  const locationLabel = filters.location ?? filters.q

  return (
    <>
      {/* ------------------------------------------------------- page header */}
      <div className="page_header element_to_stick">
        <div className="container">
          <div className="row">
            <div className="col-xl-8 col-lg-7 col-md-7 d-none d-md-block">
              <div className="breadcrumbs">
                <ul>
                  <li><Link to="/">Home</Link></li>
                  <li><Link to="/restaurants">Restaurants</Link></li>
                  <li>{locationLabel ? `“${locationLabel}”` : 'All restaurants'}</li>
                </ul>
              </div>
              <h1>
                {query.isPending && !query.data ? 'Searching…' : `${total} restaurant${total === 1 ? '' : 's'}`}
                {locationLabel ? ` matching “${locationLabel}”` : ''}
              </h1>
            </div>

            <div className="col-xl-4 col-lg-5 col-md-5">
              <form
                className="search_bar_list"
                onSubmit={(e) => { e.preventDefault(); update({ q: searchDraft.trim() }) }}
              >
                <input
                  type="text"
                  className="form-control"
                  placeholder="Search again..."
                  value={searchDraft}
                  onChange={(e) => setSearchDraft(e.target.value)}
                  aria-label="Search restaurants"
                />
                <input type="submit" value="Search" />
              </form>
            </div>
          </div>
        </div>
      </div>

      {/* -------------------------------------------------------- filters bar */}
      <div className="filters_full clearfix">
        <div className="container">
          <div className="sort_select">
            <select
              name="sort"
              id="sort"
              value={filters.sort ?? 'popularity'}
              onChange={(e) => update({ sort: e.target.value })}
              aria-label="Sort results"
            >
              {SORT_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>
          </div>

          {/* The template's map toggle, sat beside Filters in the same bar. */}
          <a
            href="#collapseMap"
            className="btn_map btn_filters"
            aria-expanded={mapOpen}
            onClick={(e) => { e.preventDefault(); setMapOpen((open) => !open) }}
          >
            <i className="icon_pin_alt" />
            <span>{mapOpen ? 'Hide map' : 'Map'}</span>
          </a>

          <a
            href="#collapseFilters"
            className="btn_filters"
            onClick={(e) => { e.preventDefault(); setFiltersOpen((open) => !open) }}
          >
            <i className="icon_adjust-vert" />
            <span>Filters{activeFilterCount > 0 ? ` (${activeFilterCount})` : ''}</span>
          </a>

          {activeFilterCount > 0 && (
            <a href="#0" className="btn_filters foogra-clear-filters" onClick={(e) => { e.preventDefault(); clearAll() }}>
              <i className="icon_close" /><span>Clear</span>
            </a>
          )}
        </div>
      </div>

      {/* ---------------------------------------------------------------- map */}
      {/*
        Mounted only while open: Leaflet measures its container on creation, and
        a hidden container measures 0px. Unmounting also frees the tile layer
        when the panel is closed.
      */}
      <div className={`collapse${mapOpen ? ' show' : ''}`} id="collapseMap">
        {mapOpen && (
          <RestaurantMap
            restaurants={query.data?.data ?? []}
            centre={filters.lat != null && filters.lng != null ? { lat: filters.lat, lng: filters.lng } : null}
            radiusKm={filters.radius ?? null}
            onPickCentre={(lat, lng) => update({ lat, lng, radius: filters.radius ?? 30 })}
          />
        )}
      </div>

      {/* ------------------------------------------------------- filter panel */}
      <div className={`collapse filters_2${filtersOpen ? ' show' : ''}`} id="collapseFilters">
        <div className="container margin_detail">
          <div className="row">
            <div className="col-lg-3 col-md-6">
              <div className="filter_type">
                <h6>Categories</h6>
                <ul>
                  {categories.data?.map((category) => {
                    const count = facets?.categories[category.slug] ?? 0

                    return (
                      <li key={category.slug}>
                        <label className="container_check">
                          {category.name} <small>{String(count).padStart(2, '0')}</small>
                          <input
                            type="checkbox"
                            checked={filters.categories?.includes(category.slug) ?? false}
                            onChange={() => toggleCategory(category.slug)}
                          />
                          <span className="checkmark" />
                        </label>
                      </li>
                    )
                  })}
                </ul>
              </div>
            </div>

            <div className="col-lg-3 col-md-6">
              <div className="filter_type">
                <h6>Rating</h6>
                <ul>
                  {(facets?.ratings ?? []).map((band) => (
                    <li key={band.min_rating}>
                      <label className="container_check">
                        {band.label} <small>{String(band.count).padStart(2, '0')}</small>
                        <input
                          type="checkbox"
                          checked={filters.min_rating === band.min_rating}
                          // Rating is a floor, so ticking a new band replaces the old one.
                          onChange={() =>
                            update({ min_rating: filters.min_rating === band.min_rating ? undefined : band.min_rating })
                          }
                        />
                        <span className="checkmark" />
                      </label>
                    </li>
                  ))}
                </ul>
              </div>
            </div>

            <div className="col-lg-3 col-md-6">
              <div className="filter_type">
                <h6>Price</h6>
                <ul>
                  {(facets?.prices ?? []).map((band) => {
                    const active = filters.min_price === band.min_price && filters.max_price === band.max_price

                    return (
                      <li key={band.label}>
                        <label className="container_check">
                          {band.label} <small>{String(band.count).padStart(2, '0')}</small>
                          <input
                            type="checkbox"
                            checked={active}
                            onChange={() =>
                              update(
                                active
                                  ? { min_price: undefined, max_price: undefined }
                                  : { min_price: band.min_price, max_price: band.max_price },
                              )
                            }
                          />
                          <span className="checkmark" />
                        </label>
                      </li>
                    )
                  })}
                </ul>
              </div>
            </div>

            <div className="col-lg-3 col-md-6">
              <div className="filter_type">
                <h6>Distance</h6>
                <div className="distance">
                  Radius around central London <span>{filters.radius ?? 30}</span> km
                </div>
                <div className="add_bottom_15">
                  <input
                    type="range"
                    min={5}
                    max={100}
                    step={5}
                    value={filters.radius ?? 30}
                    onChange={(e) =>
                      update({ radius: Number(e.target.value), lat: CITY_CENTRE.lat, lng: CITY_CENTRE.lng })
                    }
                    aria-label="Search radius in kilometres"
                  />
                </div>

                <h6>More</h6>
                <ul>
                  <li>
                    <label className="container_check">
                      Open now
                      <input
                        type="checkbox"
                        checked={Boolean(filters.open_now)}
                        onChange={() => update({ open_now: !filters.open_now })}
                      />
                      <span className="checkmark" />
                    </label>
                  </li>
                  <li>
                    <label className="container_check">
                      Has a discount
                      <input
                        type="checkbox"
                        checked={Boolean(filters.has_discount)}
                        onChange={() => update({ has_discount: !filters.has_discount })}
                      />
                      <span className="checkmark" />
                    </label>
                  </li>
                  <li>
                    <label className="container_check">
                      Featured only
                      <input
                        type="checkbox"
                        checked={Boolean(filters.featured)}
                        onChange={() => update({ featured: !filters.featured })}
                      />
                      <span className="checkmark" />
                    </label>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* --------------------------------------------------------------- grid */}
      <div className="container margin_30_40">
        {query.isError && (
          <ErrorState message={toApiError(query.error).message} onRetry={() => query.refetch()} />
        )}

        {query.isPending && !query.data && (
          <div className="row">
            {Array.from({ length: 9 }).map((_, i) => (
              <div className="col-xl-4 col-lg-6 col-md-6 col-sm-6" key={i}>
                <StripSkeleton />
              </div>
            ))}
          </div>
        )}

        {query.data && query.data.data.length === 0 && (
          <EmptyState title="No restaurants match those filters">
            Try widening the radius, clearing a category, or{' '}
            <a href="#0" onClick={(e) => { e.preventDefault(); clearAll() }}>reset everything</a>.
          </EmptyState>
        )}

        {query.data && query.data.data.length > 0 && (
          <>
            <div className={`row${query.isFetching ? ' foogra-refreshing' : ''}`}>
              {query.data.data.map((restaurant) => (
                <div className="col-xl-4 col-lg-6 col-md-6 col-sm-6" key={restaurant.id}>
                  <RestaurantStrip restaurant={restaurant} />
                </div>
              ))}
            </div>

            <Pagination
              currentPage={query.data.meta.current_page}
              lastPage={query.data.meta.last_page}
              onChange={(page) => {
                update({ page })
                window.scrollTo({ top: 0, behavior: 'smooth' })
              }}
            />
          </>
        )}
      </div>
    </>
  )
}

/** Reads the URL query string back into a typed filter object. */
function parseFilters(params: URLSearchParams): RestaurantFilters {
  const number = (key: string) => {
    const raw = params.get(key)

    return raw !== null && raw !== '' && !Number.isNaN(Number(raw)) ? Number(raw) : undefined
  }

  // Accept both `categories[]=x` (what we write) and `categories=x` (hand-typed).
  const categories = [...params.getAll('categories[]'), ...params.getAll('categories')].filter(Boolean)

  return {
    q: params.get('q') ?? undefined,
    location: params.get('location') ?? undefined,
    categories: categories.length > 0 ? categories : undefined,
    min_rating: number('min_rating'),
    min_price: number('min_price'),
    max_price: number('max_price'),
    lat: number('lat'),
    lng: number('lng'),
    radius: number('radius'),
    open_now: params.get('open_now') === '1' || undefined,
    featured: params.get('featured') === '1' || undefined,
    has_discount: params.get('has_discount') === '1' || undefined,
    sort: params.get('sort') ?? undefined,
    page: number('page'),
    per_page: 12,
  }
}
