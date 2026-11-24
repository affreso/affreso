# Changelog

## 4.3.0

**New features:**
- **Refunds for gifts:**
  - *Refund* on each paid gift in Giving: back to the card through Stripe (online gifts, one-off or regular), or *record only* for cash, bank or a correction
  - part refunds reduce the gift, so statements, giving reports, the dashboard and campaign totals all show the net amount without any other change; a full refund marks the gift *Refunded*
  - the donor gets an email, and the gift's notes record each refund with its date and reason
  - `charge.refunded` webhooks for gifts use Stripe's running total, so dashboard refunds are recorded once and echoes are ignored
  - each online gift now stores its Stripe PaymentIntent; older gifts are looked up the first time they're refunded
- **Ticket types on forms (family tickets):**
  - up to 8 ticket types per form, each with a price and the number of places it counts as (a Family ticket might be $40 for 5 places)
  - people choose quantities, with a live total; the form refuses bookings over the places limit and says how many are left
  - instalment plans work out each payment from the booking's total
  - the response, the responses list and the CSV export show the tickets and places; the linked event's list counts the extra places as guests
  - event pages and form lists show "From $5.00" (or "Free") for ticketed forms

**Upgrade note:** gifts already marked refunded are recorded as refunded in full. Form responses from before this version count as one place each.

## 4.2.0

**New features:**
- **Refunds for form payments:**
  - *Refund* on the response page: back to the card through Stripe, or recorded as cash or a bank transfer
  - card refunds are spread over the most recent payments first, so a plan paid in instalments can be refunded in one go
  - either cancel the response (which stops monthly payments and frees any event place) or keep it and reduce the amount owed, for a discount or part refund
  - the person gets an email confirming the refund
  - refunds made in the Stripe dashboard are recorded from the `charge.refunded` webhook; Stripe's running total is used, so repeated or echoed webhooks never double-count. A full refund of everything paid cancels the response.
- **Paid event registration through a linked form:**
  - choosing an event as a form's *Linked event* makes the form that event's registration: the public event page and My Affreso → Events show *Register and pay* instead of the built-in sign-up
  - each confirmed response (first payment received, or submitted if the form is free) appears in the event's registration list with a link to the response; cancelling or fully refunding the response cancels the place and promotes anyone on the waiting list
  - the event page has a *Registration form* panel, with a button to create one already linked
  - the built-in registration can't be used to skip payment while a form is linked

**Other changes:**
- Each card payment now stores its Stripe PaymentIntent, so refunds can be matched to it.
- An event place is linked to the respondent's profile only when the names match (a parent registering a child no longer puts the parent on the event list).

**Upgrade note:** older card payments are matched to Stripe the first time they're refunded, so nothing needs re-entering.

## 4.1.0

**New features:**
- **Instalments for paid forms:**
  - form managers allow up to 2, 3, 4, 6, 10 or 12 monthly payments
  - people choose to pay in full or spread the cost; Stripe takes the first payment immediately
  - each paid invoice is recorded once (repeat webhooks are ignored), and the plan is cancelled automatically after the last payment
  - a failed payment marks the response *Overdue*; the next successful payment clears it
  - response page shows every payment, lets staff record cash or bank payments, and stop the remaining payments
  - respondents get an email when the last payment arrives
- **Form answers into profiles:**
  - any question can be linked to a profile field: mobile, street address, suburb, state, postcode, date of birth, first visit, member since, or a custom field
  - on each response, staff pick whose profile to update and see the current and new values side by side before saving; only changed fields are shown
  - optionally, a form updates a signed-in member's own profile as soon as they submit (the demo's *Update your contact details* form does this)
  - every profile update is recorded in the audit log
- **Arabic and Persian on labels:** server-drawn labels now join Arabic-script letters (including Persian letters and lam-alef) and lay out right-to-left text with numbers and Latin words in the right order. Arabic-script text always uses the bundled font, so an uploaded CJK font doesn't break it.

**Other changes:**
- Response lists show the amount *paid so far* and how many people still have money to pay; the CSV export has a *paid* column.
- Browser-printed labels mark names with `dir="auto"` so right-to-left names align correctly.

**Upgrade note:** the database upgrade records existing paid form responses as fully paid, so totals carry over.

## 4.0.0

**New features:**
- **Online forms builder:**
  - question types: short and long answer, multiple choice, checkboxes, drop-down, date, number, email, phone, consent box, heading and information text
  - templates for camp registration, baptism enquiry and general enquiry
  - public or members-only forms, open and close dates, a places limit and one response per person
  - optional payment by card through Stripe, confirmed by the webhook (refunds mark the response cancelled)
  - answers encrypted at rest; staff notification emails carry a link only, and the respondent gets a copy of their own answers
  - responses list with tallies, CSV export, staff notes, and "delete all responses" for when an event is over
  - members see what they have filled in under My Affreso → Forms & sign-ups
- **Pledges and campaigns:**
  - campaigns with a goal, dates, a giving fund and an optional public pledge page
  - pledges once, weekly, fortnightly, monthly, quarterly or yearly, optionally anonymous
  - progress from gifts to the campaign's fund (gifts from anyone in the household count), with "on track" / "behind" for each pledger
  - staff can record paper pledge cards and email every pledger their progress
  - My giving shows each person's pledges and how far through them they are
- **Pre-check-in from the phone:** parents check children in ahead of time and get an arrival code; the desk sees expected families and prints labels on arrival; kiosks recognise pre-checked families.
- **Direct label printing:** server-rendered PDF labels sent silently to a label printer through PrintNode, with a default printer, a printer for each kiosk, a test label, reprinting and an uploadable label font.
- **Translations:** Vietnamese, Arabic and Persian.

**Other changes:**
- Error pages (access denied, page not found and so on) are now translated.
- Right-to-left fixes: the page no longer scrolls sideways in Arabic and Persian, and on phones the menu slides in from the right.
- Dates in Arabic and Persian use the Gregorian calendar and Western digits, to match the rest of the interface.
- New permissions: `forms.manage` (Pastor and Church Administrator) and `campaigns.manage` (Finance Officer).


## 3.0.0

**New features:**
- **Repeating events:** weekly, fortnightly or monthly patterns. Edit one date or this and all following dates, and rooms are booked for every date.
- **Attendance headcounts:**
  - counts per service and campus
  - children's numbers filled in from check-in
  - weekly trend chart, averages and comparison with last year
  - CSV export and a dashboard tile
- **Self check-in kiosk:**
  - device pairing and an exit PIN
  - lookup by mobile number, with first names only on screen
  - rate limiting and an inactivity reset
  - automatic label printing
- **Two-way calendar sync:** Google Calendar and Microsoft 365 / Outlook, using OAuth with PKCE.
  - Rosters and events go out to the person's calendar.
  - Busy times come back, so rostering can warn about clashes.
- **Direct import from Elvanto and Planning Center** (people, families and groups), safe to re-run.
- **Encrypted push notifications** with content (RFC 8291), or content-free if you prefer.

**Other changes:**
- **Translations:** Simplified Chinese, Traditional Chinese and Korean.
- **Dates:** shown in each language's own order.
- **Status badges:** now translated.
- **New permissions:** `attendance.record` and `checkin.kiosk`.
- **Demo site:** repeating services, 20 weeks of attendance, and a kiosk to try.

## 2.0.0

**New modules:**
- children's check-in and check-out
- incident reporting
- service planning and songs (CCLI)
- resource library
- policy acknowledgements and e-signatures
- reimbursements with ABA bank files
- regular (recurring) giving
- giving statements
- bank deposit import
- prayer requests
- multi-campus

**Other additions:**
- **Languages:** a language choice for each person, right-to-left layouts and localised dates.
- **Installable app:** web app manifest, service worker, offline page and push notifications (VAPID, no message content sent through push services).
- **Integrations:**
  - Mailchimp audience sync
  - Elvanto and Planning Center import mapping
  - Xero and MYOB exports
- **Accessibility:** WCAG 2.1 AA audit fixes covering heading order, contrast, labels and landmarks.
- **New roles:** Check-in Volunteer and Prayer Team, plus new permissions for check-in, children's medical information, incidents, services, library and reimbursements.
- **Automatic upgrades:** database migrations run themselves, so there's no manual SQL when upgrading.
- **Demo site:** more sample data, plus Finance Officer and Check-in Volunteer demo logins.

## 1.0.0

First release:
- members and families
- pastoral care
- events and bookings
- volunteers, rosters and safeguarding
- courses and training
- communications
- groups
- giving
- reports
- administration
- privacy tools
- demo mode
