# Production Checklist

## Prepared In This Branch

- [x] Static GitHub Pages preview remains separate from WordPress work
- [x] Custom theme preserves the current CYWater visual system
- [x] News, Events, Awards, Board roles, and pages are editable in WordPress
- [x] Initial import protects later editorial changes by default
- [x] PMPro membership levels and direct checkout links are defined
- [x] Essential registration fields and optional post-registration profile exist
- [x] Public member profile is explicit opt-in with per-field controls
- [x] Payment mode and live-key safety gates are isolated from membership logic
- [x] Local Mailpit routing is isolated from production SMTP
- [x] CI validates Node dependencies, JSON, JavaScript, and PHP syntax

## Must Pass Before Staging Approval

- [ ] Board approves legal entity, operating country, prices, calendar-year
      proration/grace policy, recurring billing policy, refund policy, privacy
      notice, terms, and retention policy
- [ ] Managed host plan and renewal price fit the budget and required features
- [ ] Cloudflare, staging DNS, strict HTTPS, SMTP sandbox, and off-site backup exist
- [ ] Stripe Sandbox keys and PMPro webhook are configured outside Git
- [ ] Successful, failed, cancelled, duplicate, refund, expiry, and renewal tests pass
- [ ] Registration, password reset, receipt, failure, welcome, expiry, and renewal
      emails pass content and deliverability review
- [ ] Editor, membership manager, administrator, and ordinary member permissions pass
- [ ] Desktop/mobile visual, accessibility, form, overflow, and browser tests pass
- [ ] Backup restore is performed and verified on staging

## Must Pass Before Production

- [ ] Legal entity and bank account pass Stripe live verification
- [ ] Production domain ownership and DNS change window are approved
- [ ] Production secrets are installed through the host/GitHub environment
- [ ] Small live payment and refund are reconciled end to end
- [ ] Monitoring, incident contacts, rollback artifact, and handover document are signed
- [ ] GitHub Pages transition/redirect decision is approved
