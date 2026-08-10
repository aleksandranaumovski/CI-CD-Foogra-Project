#!/usr/bin/env bash
#
# End-to-end smoke test against a running `php artisan serve` on :8000.
#
# NOTE: the auth endpoints are rate-limited to 10 requests/minute per IP, and
# one pass of this script uses 7 of them. Running it twice inside the same
# minute will (correctly) return 429 on the second pass and cascade into
# ~13 downstream failures. Wait a minute between runs.
#
set -u
API="http://127.0.0.1:8000/api/v1"
PASS=0; FAIL=0

check() { # check <label> <expected-status> <actual-status> [body]
  if [ "$2" = "$3" ]; then
    printf "  \033[32mok\033[0m   %-52s %s\n" "$1" "$3"; PASS=$((PASS+1))
  else
    printf "  \033[31mFAIL\033[0m %-52s expected %s got %s\n" "$1" "$2" "$3"
    [ -n "${4:-}" ] && echo "       ${4:0:300}"
    FAIL=$((FAIL+1))
  fi
}

req() { # req <method> <path> <token> [data] -> sets STATUS and BODY
  local method=$1 path=$2 token=$3 data=${4:-}
  local args=(-s -o /tmp/body.json -w '%{http_code}' -X "$method" "$API$path"
              -H 'Accept: application/json')
  [ -n "$token" ] && args+=(-H "Authorization: Bearer $token")
  [ -n "$data" ] && args+=(-H 'Content-Type: application/json' -d "$data")
  STATUS=$(curl "${args[@]}")
  BODY=$(cat /tmp/body.json)
}

jqv() { echo "$BODY" | python3 -c 'import sys,json;d=json.load(sys.stdin);print(eval("d"+sys.argv[1]))' "$1" 2>/dev/null; }

echo "=== public catalogue ==="
req GET "/health" ""                                   ; check "health" 200 "$STATUS"
req GET "/categories" ""                               ; check "categories index" 200 "$STATUS"
req GET "/restaurants?per_page=5" ""                   ; check "restaurants index" 200 "$STATUS"
TOTAL=$(jqv "['meta']['total']")
echo "       total published: $TOTAL"
req GET "/restaurants/featured" ""                     ; check "featured" 200 "$STATUS"
req GET "/restaurants/deals" ""                        ; check "deals" 200 "$STATUS"
req GET "/restaurants/suggestions?q=sushi" ""          ; check "suggestions" 200 "$STATUS"
req GET "/restaurants/pizzeria-da-alfredo" ""          ; check "restaurant show" 200 "$STATUS" "$BODY"
req GET "/restaurants/pizzeria-da-alfredo/menu" ""     ; check "restaurant menu" 200 "$STATUS"
req GET "/restaurants/pizzeria-da-alfredo/reviews" ""  ; check "restaurant reviews" 200 "$STATUS"
req GET "/restaurants/pizzeria-da-alfredo/availability?date=$(date -d '+3 days' +%F)" ""
check "availability" 200 "$STATUS" "$BODY"
echo "       slots: $(jqv "['services'][0]['times'][:4]")"
req GET "/restaurants/does-not-exist" ""               ; check "404 unknown slug" 404 "$STATUS"

echo
echo "=== filtering & sorting ==="
req GET "/restaurants?categories[]=pizza-italian&per_page=50" ""; check "filter by category" 200 "$STATUS"
echo "       pizza results: $(jqv "['meta']['total']")"
req GET "/restaurants?min_rating=9" ""                 ; check "filter min_rating" 200 "$STATUS"
echo "       rating 9+: $(jqv "['meta']['total']")"
req GET "/restaurants?min_price=0&max_price=25" ""     ; check "filter price band" 200 "$STATUS"
req GET "/restaurants?lat=51.5074&lng=-0.1278&radius=5&sort=distance" ""; check "radius + distance sort" 200 "$STATUS"
req GET "/restaurants?q=sushi" ""                      ; check "full-text search" 200 "$STATUS"
req GET "/restaurants?open_now=1" ""                   ; check "open now" 200 "$STATUS"
req GET "/restaurants?has_discount=1&sort=price-desc" ""; check "discount + sort" 200 "$STATUS"
req GET "/restaurants?sort=bogus" ""                   ; check "422 invalid sort" 422 "$STATUS"
req GET "/restaurants?lat=51.5" ""                     ; check "422 lat without lng" 422 "$STATUS"
req GET "/restaurants?per_page=999" ""                 ; check "422 per_page too big" 422 "$STATUS"

echo
echo "=== auth ==="
req POST "/auth/login" "" '{"email":"admin@foogra.test","password":"password"}'
check "admin login" 200 "$STATUS" "$BODY"
ADMIN=$(jqv "['token']")
req POST "/auth/login" "" '{"email":"owner@foogra.test","password":"password"}'
check "owner login" 200 "$STATUS"; OWNER=$(jqv "['token']")
req POST "/auth/login" "" '{"email":"customer@foogra.test","password":"password"}'
check "customer login" 200 "$STATUS"; CUST=$(jqv "['token']")
req POST "/auth/login" "" '{"email":"admin@foogra.test","password":"wrong"}'
check "422 bad credentials" 422 "$STATUS"
req GET "/auth/me" "$ADMIN"                            ; check "me (admin)" 200 "$STATUS"
echo "       role: $(jqv "['user']['role']")"
req GET "/auth/me" ""                                  ; check "401 me without token" 401 "$STATUS"

NEWMAIL="tester$RANDOM@foogra.test"
req POST "/auth/register" "" "{\"name\":\"Test Diner\",\"email\":\"$NEWMAIL\",\"password\":\"secret123\",\"password_confirmation\":\"secret123\"}"
check "register" 201 "$STATUS" "$BODY"; NEWTOK=$(jqv "['token']")
req POST "/auth/register" "" "{\"name\":\"Dup\",\"email\":\"$NEWMAIL\",\"password\":\"secret123\",\"password_confirmation\":\"secret123\"}"
check "422 duplicate email" 422 "$STATUS"
req POST "/auth/register" "" '{"name":"X","email":"bad@f.test","password":"short","password_confirmation":"short","role":"admin"}'
check "422 self-assign admin blocked" 422 "$STATUS"

echo
echo "=== wishlist ==="
req GET "/wishlist" "$CUST"                            ; check "wishlist index" 200 "$STATUS"
req POST "/wishlist/best-burghers/toggle" "$CUST" '{}' ; check "wishlist toggle on" 201 "$STATUS" "$BODY"
req POST "/wishlist/best-burghers/toggle" "$CUST" '{}' ; check "wishlist toggle off" 200 "$STATUS"
req GET "/wishlist" ""                                 ; check "401 wishlist anon" 401 "$STATUS"

echo
echo "=== reviews ==="
req GET "/reviews/mine" "$CUST"                        ; check "my reviews" 200 "$STATUS"
REVBODY='{"title":"Smoke test review","body":"This is a smoke test review body that comfortably exceeds twenty characters.","rating_food":9,"rating_service":8.5,"rating_location":8,"rating_price":7.5}'
req POST "/restaurants/vego-life/reviews" "$NEWTOK" "$REVBODY"
check "create review" 201 "$STATUS" "$BODY"; REVID=$(jqv "['data']['id']")
req POST "/restaurants/vego-life/reviews" "$NEWTOK" "$REVBODY"
check "422 duplicate review" 422 "$STATUS"
req POST "/restaurants/vego-life/reviews" "$NEWTOK" '{"title":"x","body":"too short","rating_food":11,"rating_service":8,"rating_location":8,"rating_price":8}'
check "422 invalid ratings" 422 "$STATUS"
req PATCH "/reviews/$REVID" "$NEWTOK" '{"title":"Smoke test review, edited"}'
check "update own review" 200 "$STATUS" "$BODY"
req PATCH "/reviews/$REVID" "$CUST" '{"title":"hijack"}'
check "403 edit someone else review" 403 "$STATUS"
req POST "/reviews/$REVID/vote" "$CUST" '{"is_helpful":true}'
check "vote helpful" 200 "$STATUS" "$BODY"
req POST "/reviews/$REVID/vote" "$NEWTOK" '{"is_helpful":true}'
check "403 vote on own review" 403 "$STATUS"
req DELETE "/reviews/$REVID/vote" "$CUST"              ; check "remove vote" 200 "$STATUS"

echo
echo "=== bookings ==="
DATE=$(date -d '+5 days' +%F)
req POST "/bookings" "" "{\"restaurant_id\":1,\"guest_name\":\"Guest Person\",\"guest_email\":\"guest$RANDOM@foogra.test\",\"booking_date\":\"$DATE\",\"booking_time\":\"20:00\",\"party_size\":2}"
check "guest booking" 201 "$STATUS" "$BODY"; REF=$(jqv "['data']['reference']"); GEMAIL=$(jqv "['data']['guest']['email']")
req GET "/bookings/$REF?email=$GEMAIL" ""              ; check "guest lookup w/ email" 200 "$STATUS"
req GET "/bookings/$REF" ""                            ; check "403 lookup without email" 403 "$STATUS"
req POST "/bookings" "" "{\"restaurant_id\":1,\"guest_name\":\"X\",\"guest_email\":\"x@f.test\",\"booking_date\":\"$DATE\",\"booking_time\":\"04:00\",\"party_size\":2}"
check "422 booking outside hours" 422 "$STATUS"
req POST "/bookings" "" "{\"restaurant_id\":1,\"guest_name\":\"X\",\"guest_email\":\"x@f.test\",\"booking_date\":\"2020-01-01\",\"booking_time\":\"20:00\",\"party_size\":2}"
check "422 booking in the past" 422 "$STATUS"
req GET "/bookings" "$CUST"                            ; check "my bookings" 200 "$STATUS"

echo
echo "=== admin dashboard ==="
req GET "/admin/stats" "$ADMIN"                        ; check "stats (admin)" 200 "$STATUS" "$BODY"
echo "       scope=$(jqv "['scope']") restaurants=$(jqv "['restaurants']['total']") bookings=$(jqv "['bookings']['total']")"
req GET "/admin/stats" "$OWNER"                        ; check "stats (owner)" 200 "$STATUS"
echo "       scope=$(jqv "['scope']") restaurants=$(jqv "['restaurants']['total']")"
req GET "/admin/stats" "$CUST"                         ; check "403 stats as customer" 403 "$STATUS"
req GET "/admin/restaurants" "$ADMIN"                  ; check "admin restaurants (all)" 200 "$STATUS"
ADMIN_TOTAL=$(jqv "['meta']['total']")
req GET "/admin/restaurants" "$OWNER"                  ; check "admin restaurants (owned)" 200 "$STATUS"
OWNER_TOTAL=$(jqv "['meta']['total']")
echo "       admin sees $ADMIN_TOTAL, owner sees $OWNER_TOTAL"
req GET "/admin/reviews?status=pending" "$ADMIN"       ; check "moderation queue" 200 "$STATUS"
req GET "/admin/bookings" "$OWNER"                     ; check "reservation book" 200 "$STATUS"
req GET "/admin/users" "$ADMIN"                        ; check "users (admin)" 200 "$STATUS"
req GET "/admin/users" "$OWNER"                        ; check "403 users as owner" 403 "$STATUS"

echo
echo "=== admin CRUD lifecycle ==="
req POST "/admin/restaurants" "$OWNER" '{"name":"Smoke Test Kitchen","category_id":1,"address":"1 Test Street","city":"London","average_price":33,"description":"Created by the smoke test."}'
check "owner creates restaurant" 201 "$STATUS" "$BODY"
SLUG=$(jqv "['data']['slug']"); RESTID=$(jqv "['data']['id']")
echo "       slug: $SLUG (id $RESTID)"
req PATCH "/admin/restaurants/$SLUG" "$OWNER" '{"average_price":41,"status":"published"}'
check "owner updates own" 200 "$STATUS" "$BODY"
req PATCH "/admin/restaurants/$SLUG" "$OWNER" '{"is_featured":true}'
check "422 owner cannot feature" 422 "$STATUS"
req PATCH "/admin/restaurants/$SLUG" "$ADMIN" '{"is_featured":true}'
check "admin can feature" 200 "$STATUS"
echo "       (NEWTOK=${NEWTOK:0:14}...)"
req PATCH "/admin/restaurants/pizzeria-da-alfredo" "$NEWTOK" '{"name":"hijack"}'
check "403 non-staff cannot edit" 403 "$STATUS" "$BODY"

req POST "/admin/menu-sections" "$OWNER" "{\"restaurant_id\":$RESTID,\"name\":\"Smoke Starters\",\"sort_order\":1}"
check "create menu section" 201 "$STATUS" "$BODY"; SECID=$(jqv "['data']['id']")
req POST "/admin/dishes" "$OWNER" "{\"menu_section_id\":$SECID,\"name\":\"Smoke Soup\",\"price\":6.5,\"description\":\"A test dish.\"}"
check "create dish" 201 "$STATUS" "$BODY"; DISHID=$(jqv "['data']['id']")
req PATCH "/admin/dishes/$DISHID/availability" "$OWNER" '{}'
check "toggle dish availability" 200 "$STATUS"
req PATCH "/admin/dishes/$DISHID" "$OWNER" '{"price":7.25}'
check "update dish" 200 "$STATUS"

req PUT "/admin/restaurants/$SLUG/opening-hours" "$OWNER" '{"hours":[{"day_of_week":1,"service":"lunch","is_closed":false,"opens_at":"11:00","closes_at":"15:00"},{"day_of_week":1,"service":"dinner","is_closed":false,"opens_at":"18:00","closes_at":"23:00"},{"day_of_week":0,"service":"lunch","is_closed":true}]}'
check "sync opening hours" 200 "$STATUS" "$BODY"

req POST "/admin/categories" "$ADMIN" '{"name":"Smoke Cuisine","icon":"icon-food_icon_pizza","average_price":42}'
check "admin creates category" 201 "$STATUS" "$BODY"; CATSLUG=$(jqv "['data']['slug']"); CATID=$(jqv "['data']['id']")
req POST "/admin/categories" "$OWNER" '{"name":"Owner Cuisine"}'
check "403 owner cannot create category" 403 "$STATUS"
req DELETE "/admin/categories/pizza-italian" "$ADMIN"
check "409 delete category in use" 409 "$STATUS"
req DELETE "/admin/categories/$CATSLUG" "$ADMIN"       ; check "delete empty category" 200 "$STATUS"

req DELETE "/admin/dishes/$DISHID" "$OWNER"            ; check "delete dish" 200 "$STATUS"
req DELETE "/admin/menu-sections/$SECID" "$OWNER"      ; check "delete menu section" 200 "$STATUS"
req DELETE "/admin/restaurants/$SLUG" "$OWNER"         ; check "soft delete restaurant" 200 "$STATUS"
req GET "/restaurants/$SLUG" ""                        ; check "404 after soft delete" 404 "$STATUS"

echo
echo "=== moderation affects public score ==="
req GET "/admin/reviews?status=pending&per_page=1" "$ADMIN"
PENDING_ID=$(jqv "['data'][0]['id']")
if [ -n "$PENDING_ID" ] && [ "$PENDING_ID" != "None" ]; then
  req PATCH "/admin/reviews/$PENDING_ID/status" "$ADMIN" '{"status":"approved"}'
  check "approve pending review" 200 "$STATUS" "$BODY"
else
  echo "       (no pending reviews to moderate)"
fi

echo
echo "==============================================="
printf "  passed: \033[32m%s\033[0m   failed: \033[31m%s\033[0m\n" "$PASS" "$FAIL"
echo "==============================================="
[ "$FAIL" -eq 0 ]
