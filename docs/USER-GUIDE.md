# Affreso user guide

## Roles at a glance

| Role | Typical person | Can do |
|---|---|---|
| Super Administrator | IT volunteer or operations manager | Everything, including users, roles, settings and the audit log |
| Pastor / Elder | Senior and associate pastors | Members, pastoral notes, care, groups, events, volunteer approvals, course approval, communications, reports |
| Church Administrator | Office staff | Members, households, events, rooms, rosters, communications, training assignment, reports |
| Safeguarding Officer | Child-safety lead | WWCC records, volunteer suitability, training compliance |
| Finance Officer | Treasurer | Giving records, receipts, giving reports |
| Ministry Leader | Worship, Welcome, Kids or AV leaders | Rosters and onboarding **for the teams they lead** (set by making them *Leader* of a group) |
| Course Creator | Trainers | Draft the courses they are assigned |
| Course Approver | Pastors or training lead | Review and publish courses |
| Finance Officer | Treasurer | Giving, statements, bank imports, paying reimbursements |
| Check-in Volunteer | Kids' welcome desk | Checking children in and out |
| Prayer Team | Prayer ministry | Team-level prayer requests |
| Volunteer / Member | Everyone else | Their own profile, availability, rosters, training, groups, events and giving |

Roles and permissions can be changed in **Administration → Roles & permissions**.

## Getting started as an administrator

1. **Settings**: add church details, email and SMS.
2. **Ministries & groups**: create teams (Worship, Welcome, Children's Ministry, AV) and small groups. Tick **Child-related** for children's and youth ministries.
3. **Courses**: publish your induction and child-safety courses.
4. **Positions**: in each team, add positions (for example Vocals, Greeter, Kids Leader), with their required courses and WWCC rules.
5. **People**: import members from CSV (Members & families → Import), or add them one by one.
6. **Leaders**: add ministry leaders to their teams with the *Leader* role, and invite them (Profile → Invite to Affreso).
7. **Volunteers**: approve people for positions, or let them apply from *My Affreso → Volunteer*.
8. **Cron**: make sure the scheduled task is running.

## Volunteer onboarding

1. **The volunteer applies**: *Volunteer with us*, choosing positions and giving their availability.
2. **A leader reviews**: Volunteers → application → *Start review*. Arrange any WWCC or police check.
3. **Training is assigned**: *Suitable — assign training* enrols them in every course their positions require.
4. **The volunteer completes training**: Affreso records results and alerts the reviewer and team leaders.
5. **The leader approves**: *Approve eligible positions*. Positions with outstanding requirements stay blocked.
6. **Rostering begins**: the volunteer gets invitations, reminders and access to team resources.

## Building a roster

1. Rosters → **New roster** → choose the team, date and time, how many people per position, and optionally repeat weekly.
2. **Suggest volunteers** fills open slots. It only offers eligible people (trained, WWCC where needed, available, not double-booked), favours preferred roles and people who have served less, and avoids anyone at their monthly limit.
3. Adjust slots by hand if needed. *Why these suggestions?* explains each person's status.
4. **Approve & publish** sends invitations by email and SMS. Volunteers accept or decline.
5. Declines and swap requests notify the leader. Other approved volunteers can cover a swap with one click.
6. Reminders go out automatically before the service.

## Pastoral care

- Use **Care & follow-up** for tasks: welcome calls, baptism enquiries, prayer requests and hospital visits.
- Put confidential matters in **Confidential pastoral notes** on the person's profile. They are encrypted, restricted, and every view is logged. Don't put sensitive information in *General notes*.

## Children's check-in (Sunday morning)

1. **Children's check-in → Open check-in** for the session. Set up classes by age first.
2. Search the family by surname or mobile. Tick the children, confirm their classes and choose who dropped them off.
3. Print the labels. The child wears theirs, and the parent keeps the tag with the 4-character code.
4. Leaders check themselves in to their room. Only people with a current WWCC count towards the ratio.
5. At pick-up, enter the code from the parent tag and choose who is collecting. Anyone not authorised needs a leader's approval, and the reason is recorded.

Parents can update allergies, medical information and pick-up permissions themselves in **My Affreso → My children**.

## Repeating events

When creating an event, choose **Repeats**: weekly (pick the days), monthly on the same date, or monthly on the same weekday (e.g. 2nd Sunday). Then choose when it ends.

When editing one date of a series:
- **Change this date only** changes just that date.
- **Change this and all following dates** applies to the rest of the series. If you change the pattern, the series is split from this date.
- **Remove just this date** takes one date out, for example on a public holiday. People who already registered keep their place, and you'll be asked to cancel instead so they are told.

## Attendance

**Attendance → Record a count** after each service. Choose the date and service, then enter adults, youth, children, online and first-time visitors. The children's number is filled in from check-in. Use the same service name each week so trends line up. **Trends & report** shows the weekly chart, averages and the same period last year.

## Self check-in kiosk

Families tap their mobile number into the foyer tablet, tick their children and choose who dropped them off. Labels print, and the parent gets the pick-up code. Pick-up still happens with a leader using the code. Set kiosks up under **Children's check-in → Self check-in kiosks**. Staff leave kiosk mode with the exit PIN, using the small **Staff** link at the bottom of the screen.

## Pre-check-in from the phone

Parents open **My Affreso → My children** before they leave home. When a check-in session is open, they tick the children who are coming, choose who is dropping them off and press **Pre-check in**. They get a six-character arrival code. At church they give that code (or their mobile number) at the welcome desk or kiosk, and the labels print straight away. On the check-in desk screen, expected families are listed at the top with an **Arrived — print labels** button.

## Printing labels

If your church has set up PrintNode (see INSTALL.md), labels print silently to the label printer as each family checks in, with no print dialog. **Print again** reprints a family's labels. Without PrintNode, labels open in the browser's print dialog as before.

## Forms and sign-ups

**Forms → New form** and pick a template (camp registration, baptism enquiry, general enquiry) or start blank. Add questions, set who can fill it in (anyone with the link, or signed-in members only), open and close dates, a places limit, and a price if people should pay by card. Then press **Open for responses** and share the link on your website, newsletter or email. Responses appear under the form with totals for each choice; export them to CSV, add staff notes, or delete them once the event is over. Members see the forms they've filled in under **My Affreso → Forms & sign-ups**.

**Paying in instalments.** For a paid form, choose *Paying in instalments → Allow up to 3 monthly payments* (or 2, 4, 6, 10 or 12). People then choose on the form whether to pay in full or spread the cost. The first payment is taken straight away and the rest on the same day each month; Affreso stops the plan after the last payment. Each response shows the payments so far. Use **Record a payment** for cash or bank transfers, and **Stop monthly payments** if someone withdraws (refunds are made in the Stripe dashboard).

**Refunds.** On a response, open **Refund**, enter the amount and choose how: *Back to their card (Stripe)* sends the money back straight away (it reaches them in 5–10 business days), while *Cash* or *Bank transfer* just records money you've returned yourself. Leave *Cancel the response too* ticked when someone withdraws; untick it for a discount or part refund, and the amount they owe goes down instead. Refunds you make in the Stripe dashboard appear in Affreso on their own.

**Ticket types and family tickets.** In a form's settings, open *Ticket types* and add a row for each kind of ticket: its name, price and how many places it uses (for example Adult $15 · 1 place, Child $5 · 1 place, Family $40 · 5 places). People then choose how many of each they want and see the total before paying. The places limit counts places, not responses, so a family ticket takes five; the linked event's list shows the family as one registration with four guests.

**Charging for an event.** Open the event and press **Create a registration form** (or choose the event as *Linked event* in any form's settings). Set the price, instalments and places limit on the form, then open it. The event's public page and My Affreso → Events show *Register and pay*, which goes to the form. Each confirmed response appears in the event's registration list with a link to the response, so you can take attendance there as usual. Cancelling or fully refunding a response removes the place.

**Updating profiles from answers.** When adding or editing a question, choose *Save the answer to a profile field* — for example, link *Date of birth* to the profile's date of birth, or *Mobile* to the mobile number. On each response, **Update a profile** lets you choose whose profile it is (the parent who filled it in, or the child going to camp) and shows the current and new value of each field; untick anything you don't want to change. To let members update their own details directly, tick *When signed-in people fill this in, update their own profile* in the form settings. Health and allergy details can't be linked: those stay in the child's care record.

## Refunding a gift

In **Giving**, find the gift and press **Refund**. Enter the amount (part refunds are fine) and choose *Back to their card (Stripe)* for gifts made online, or *Record only* for cash, bank transfers or a gift entered by mistake. The gift is reduced by the refund, so the donor's statement and your reports show only what the church kept, and a gift refunded in full disappears from the statement. The donor gets an email; if you've already sent this year's statement, send an updated one from **Giving → Statements**.

## Pledges and campaigns

**Campaigns & pledges → New campaign**: give it a name, a goal, dates and the giving fund that gifts go to. Tick *Show a public page* to let people pledge online, or use **Record a pledge card** for paper cards. Progress comes automatically from gifts to that fund. Each pledger is shown as on track or behind (compared with an even spread over the campaign), and **Email progress to pledgers** sends everyone a personal update. Members see their pledges and how much they've given on **My giving**.

## Moving from Elvanto or Planning Center

Go to **Members & families → Import → Import directly**:
1. Paste your Elvanto API key, or a Planning Center application ID and secret.
2. Press **Check connection** to see how many people and groups will come across.
3. Press **Import now**.

Running it again later only adds people who are new, and tick *Update people already imported* to refresh their details.

## Policies and signatures

Publish policies in the **Resource library** with "People must read and sign this" ticked. Then require them for positions in **Ministries & groups → position settings**. Volunteers who haven't signed the current version can't be rostered. Use **Request signatures** to remind them.

## Reimbursements

Volunteers submit claims with a receipt photo. The team's ministry leader approves the claim, and nobody can approve their own. The treasurer then downloads an **ABA file** to pay everyone at once through online banking, and marks the claims as paid.

## For members and volunteers

- **My rosters**: accept or decline, request a swap, or cover someone else's swap.
- **Availability**: add holidays so you aren't rostered while you're away.
- **My training**: complete courses and download certificates.
- **Privacy & consent**: choose whether you're listed in the directory and how you're contacted, and download your data.
- **Account & security**: change your password, turn on two-step verification, choose your language, connect your Google or Outlook calendar (your rosters appear in it automatically), and turn on phone notifications.
- **My children**: keep allergy and pick-up details up to date, and pre-check-in before Sunday.
- **Forms & sign-ups**: register for camps and events, and see what you've filled in.
- **My giving**: see your gifts, regular giving and pledges.
- **Prayer**: share requests and pray for others.
- **Reimbursements**: claim back money you spent on ministry.
- **Install the app**: on iPhone, open Affreso in Safari, tap Share, then Add to Home Screen. On Android, open it in Chrome, tap ⋮, then Install app.
