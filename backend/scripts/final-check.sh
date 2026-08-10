#!/usr/bin/env bash
# A last sanity pass over the running stack: both servers, the docs, the mail
# catcher, and a couple of representative filtered queries.
set -u
API="http://127.0.0.1:8000/api/v1"

# Evaluates a Python expression against the piped JSON, bound to `d`.
j() { python3 -c 'import sys,json;d=json.load(sys.stdin);print(eval(sys.argv[1]))' "$1"; }

printf "%-42s %s\n" "Vite dev server"      "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:5173/)"
printf "%-42s %s\n" "Laravel API health"   "$(curl -s -o /dev/null -w '%{http_code}' $API/health)"
printf "%-42s %s\n" "OpenAPI docs UI"      "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8000/docs/api)"
printf "%-42s %s\n" "Mailpit UI"           "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8025/)"
printf "%-42s %s\n" "Vite -> API proxy"    "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:5173/api/v1/health)"

echo
echo "-- catalogue --"
printf "%-42s %s\n" "published restaurants" "$(curl -s "$API/restaurants?per_page=1" | j "d['meta']['total']")"
printf "%-42s %s\n" "categories"            "$(curl -s "$API/categories" | j "len(d['data'])")"
printf "%-42s %s\n" "featured"              "$(curl -s "$API/restaurants/featured" | j "len(d['data'])")"
printf "%-42s %s\n" "deals"                 "$(curl -s "$API/restaurants/deals" | j "len(d['data'])")"

echo
echo "-- filters --"
printf "%-42s %s\n" "pizza + rating 8+ sorted by rating" \
  "$(curl -s "$API/restaurants?categories\[\]=pizza-italian&min_rating=8&sort=rating" | j "d['meta']['total']")"
printf "%-42s %s\n" "  top result" \
  "$(curl -s "$API/restaurants?categories\[\]=pizza-italian&min_rating=8&sort=rating" | j "d['data'][0]['name'] + ' (' + str(d['data'][0]['rating']['score']) + ')'")"
printf "%-42s %s\n" "within 10km of central London" \
  "$(curl -s "$API/restaurants?lat=51.5074&lng=-0.1278&radius=10&sort=distance" | j "d['meta']['total']")"
printf "%-42s %s\n" "  nearest" \
  "$(curl -s "$API/restaurants?lat=51.5074&lng=-0.1278&radius=10&sort=distance" | j "d['data'][0]['name'] + ' at ' + str(d['data'][0]['distance_km']) + ' km'")"
printf "%-42s %s\n" "facet: rating bands" \
  "$(curl -s "$API/restaurants" | j "[b['label'] + '=' + str(b['count']) for b in d['facets']['ratings']]")"

echo
echo "-- detail page payload --"
DETAIL=$(curl -s "$API/restaurants/pizzeria-da-alfredo")
printf "%-42s %s\n" "name"          "$(echo "$DETAIL" | j "d['data']['name']")"
printf "%-42s %s\n" "score"         "$(echo "$DETAIL" | j "str(d['data']['rating']['score']) + ' ' + d['data']['rating']['label']")"
printf "%-42s %s\n" "menu sections" "$(echo "$DETAIL" | j "[s['name'] + ' (' + str(len(s['dishes'])) + ')' for s in d['data']['menu_sections']]")"
printf "%-42s %s\n" "gallery"       "$(echo "$DETAIL" | j "len(d['data']['images']['gallery'])")"
printf "%-42s %s\n" "open now"      "$(echo "$DETAIL" | j "d['data']['is_open_now']")"

echo
echo "-- availability (3 days out) --"
DATE=$(date -d '+3 days' +%F)
printf "%-42s %s\n" "lunch slots" \
  "$(curl -s "$API/restaurants/pizzeria-da-alfredo/availability?date=$DATE" | j "d['services'][0]['times'][:5]")"
