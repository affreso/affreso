# Privacy and child safety: what Affreso supports, and what you must verify

> **Important:** Affreso provides tools. It does not make your church compliant by itself. Obligations differ by state or territory, denomination, organisation size and funding arrangements. **Confirm your obligations with your denomination, insurer or a lawyer before you go live.** The notes below are general information, not legal advice.

## Australian Privacy Principles (APPs)

Many churches with annual turnover under $3 million are not covered by the *Privacy Act 1988*. However, many choose to follow the APPs, and some are required to by state law, denominational policy, grants or contracts. Affreso supports:

| Principle | How Affreso helps |
|---|---|
| APP 1: open and transparent management | Editable privacy policy at `/privacy`; privacy officer contact in settings |
| APP 3 and 5: collection and notification | Connect cards and event forms ask for consent and link to the privacy policy; only necessary fields are required |
| APP 6: use and disclosure | Role-based access; the directory is opt-in; bulk messages only go to people who consented to contact |
| APP 7: direct marketing | Email and SMS opt-outs per person; consent history is recorded |
| APP 10: quality | Members can update their own details |
| APP 11: security, retention and destruction | Encryption of sensitive fields, MFA, audit log, retention review, de-identification tool |
| APP 12 and 13: access and correction | Self-service data download; privacy request register with 30-day due dates; staff export of a person's full record |

### Sensitive information

Health, religious and other sensitive information has a higher bar. Membership of a church can itself reveal religious belief. Keep pastoral notes factual and minimal. Don't create custom fields for health information unless you have a clear need, consent and restricted access.

## Notifiable Data Breaches (NDB)

The **Data-breach register** (Administration → Privacy & compliance) follows the OAIC's four steps:

1. **Contain** the breach.
2. **Assess** it. Affreso sets a 30-day assessment due date.
3. **Notify** affected individuals and the OAIC if serious harm is likely and you are covered by the scheme.
4. **Review** what happened and prevent it happening again.

Record your assessment even if you decide that notification isn't required. Guidance: <https://www.oaic.gov.au/privacy/notifiable-data-breaches>

## Version 3 notes

- **Calendar sync** is opt-in for each person and can be disconnected at any time, with the option to remove the events Affreso added. Only busy start and end times are stored, never what an appointment is. Leaders see "busy in their calendar 9:00–12:00" and nothing more.
- **Self check-in kiosks** never display surnames, allergies or medical details. Families are matched only by the full mobile number of an adult in the household.
- **Imports from Elvanto or Planning Center:** tick the consent box only if those people agreed to the church holding their details. Otherwise consent is recorded as not given, and they're left out of bulk messages until they consent.
- **Attendance headcounts** are numbers only and contain no personal information.

## Version 4 notes

- **Forms** can collect sensitive information (health, allergies, dietary needs). Answers are encrypted, only people with *Build forms and see their responses* can see them, and staff emails contain a link rather than the answers. Every form has a consent line and a link to your privacy policy. When an event is over, use **Delete all responses** on the form — especially for camps, where health details shouldn't be kept longer than needed.
- **Profile updates from forms** (version 4.1) only happen when a form manager has linked a question to a profile field. Staff see and confirm each change, unless the form is set to update a signed-in member's *own* details. Every update is in the audit log, naming the response it came from. Health and care details can't be linked, so they never leave the encrypted care record this way.
- **Gift refunds** change the gift's amount (and its notes record when and why), so the donor's statement stays accurate for tax purposes. Every refund is in the audit log.
- **Refunds** are sent through Stripe using the payment reference Affreso stores; card details are never seen or kept. Each refund is in the audit log.
- **Instalments** are held by Stripe as a subscription. Affreso stores the subscription reference and the amount of each payment, never card details.
- **Pledges** record what someone intends to give. Pledgers can ask to stay anonymous on any public list, and the public campaign page shows totals only, never names.
- **Pre-check-in** stores only which children are expected and an arrival code. These records are deleted when the child arrives or the session closes.
- **Label printing through PrintNode** sends each label (the child's name, class, date and security code, plus allergy details when the leader checking in has children's medical access) to PrintNode's servers to reach your printer. Mention PrintNode in your privacy policy if you use it, as you would Stripe or your email provider.

## Child safety

Affreso supports these child-safe practices:

- **Working With Children Check (or equivalent) records** for each state or territory: NSW WWCC, VIC WWC Check, QLD Blue Card, WA WWC Card, SA WWCC, ACT/TAS WWVP and NT Ochre Card. Clearance numbers are encrypted.
- **Verification workflow**: a check is *pending* until someone with safeguarding permission verifies it with the issuing agency.
- **Automatic eligibility**: positions marked "Requires a WWCC" cannot be rostered without a current, verified check.
- **Expiry management**: reminders 60, 30 and 7 days before expiry, and automatic status change when a check expires.
- **Barred / negative notice**: the person is immediately removed from every child-related position and upcoming roster, and safeguarding officers are alerted.
- **Mandatory training** such as Child Safety Essentials, with renewal periods, quiz pass marks and certificates.
- **Restricted access**: only roles with safeguarding permissions can see check details.
- **Retention**: records with safeguarding history are flagged and protected from routine erasure. Child-safety records often need to be kept for decades (the Royal Commission recommended at least 45 years for records relating to child sexual abuse). Confirm your obligations.

Relevant frameworks to check include the National Principles for Child Safe Organisations, your state's Child Safe Standards, any Reportable Conduct Scheme, mandatory reporting duties, and your denomination's safe ministry requirements.

- **Secure check-in and check-out:**
  - a matching security code is required to release a child
  - authorised collectors are listed, and people who are *not permitted* to collect (for example under a court order) can be flagged
  - allergy and medical alerts are encrypted; leaders without medical access see only "care alert"
  - every check-out override is logged with its reason
- **Leader ratios:** at least two adults per room, and leaders only count if their WWCC is current.
- **Incident reporting:**
  - details are encrypted
  - safeguarding officers are alerted straight away when a child is involved
  - there's a prompt to consider the Reportable Conduct Scheme
  - dates are recorded for notifying police, child protection, the regulator and the insurer
- **Policy acknowledgements:** safe-ministry policies can be required for serving positions, with dated, versioned signatures.

## Retention defaults (change them in Settings → Privacy & retention)

| Data | Default | Behaviour |
|---|---|---|
| Inactive visitor records | review after 24 months | Listed for review, then archived on approval |
| Archived records | review after 7 years | Listed for review; de-identify individually |
| Audit log | 7 years | Deleted automatically |
| Sent message content | 24 months | Message body cleared automatically; the delivery record is kept |
| Login attempts | 90 days | Deleted automatically |
| Safeguarding records | never deleted automatically | Flagged as retain |
| Giving records | kept | Australian tax records are generally kept for 5–7 years; check with your accountant |

## Giving receipts

Only say that gifts are tax deductible if the church or a specific fund holds **Deductible Gift Recipient (DGR)** status. Most church general offerings do not. Set the wording in Settings → Online giving.
