/* ==========================================================================
   CYWater · i18n dictionary + controller
   Bilingual (en / zh). All UI strings live here, referenced by data-i18n.
   ========================================================================== */

const I18N = {
  /* ---------- meta ---------- */
  meta: {
    siteName: { en: "CYWater" },
    siteFull: { en: "Int'l Association of Contemporary Young Scholars in Water Sciences" },
    siteFullOrig: { en: "Founded 2011 as the Int'l Association of Chinese Youth in Water Sciences" },
    tagline:  { en: "Advancing water sciences, empowering young scholars" },
  },

  /* ---------- nav ---------- */
  nav: {
    home:       { en: "Home" },
    about:      { en: "About" },
    board:      { en: "Board" },
    bylaws:     { en: "Bylaws" },
    membership: { en: "Membership" },
    events:     { en: "Events" },
    news:       { en: "News" },
    journal:    { en: "Journal" },
    contact:    { en: "Contact" },
    signIn:     { en: "Sign in" },
    join:       { en: "Join CYWater" },
    dashboard:  { en: "Dashboard" },
  },

  /* ---------- footer ---------- */
  footer: {
    explore:    { en: "Explore" },
    association:{ en: "Association" },
    resources:  { en: "Resources" },
    connect:    { en: "Connect" },
    newsletter: { en: "Newsletter" },
    newsletterHint: { en: "Quarterly updates on research, events and opportunities." },
    emailPlaceholder: { en: "you@email.com" },
    subscribe:  { en: "Subscribe" },
    tagline:    { en: "A non-profit, international member-based association advancing water sciences education, research, and professional development through scientific exchange, publications, and conferences. Founded in 2011." },
    rights:     { en: "© 2011–2026 CYWater. All rights reserved." },
    privacy:    { en: "Privacy" },
    terms:      { en: "Terms" },
    bylaws:     { en: "Bylaws" },
  },

  /* ---------- common ---------- */
  common: {
    learnMore:   { en: "Learn more" },
    viewAll:     { en: "View all" },
    readMore:    { en: "Read more" },
    register:    { en: "Register" },
    back:        { en: "Back" },
    submit:      { en: "Submit" },
    next:        { en: "Next" },
    previous:    { en: "Previous" },
    cancel:      { en: "Cancel" },
    continue:    { en: "Continue" },
    sent:        { en: "Message sent — we'll reply within 2 business days." },
    subscribed:  { en: "Subscribed! Check your inbox to confirm." },
    registered:  { en: "[Mock] Registration received — no real sign-up is processed." },
    joined:      { en: "[Mock] Welcome aboard! — no real membership is created." },
    saved:       { en: "[Mock] Saved — no real data is stored." },
    member:      { en: "Member" },
    nonmember:   { en: "Non-member" },
    free:        { en: "Free" },
    online:      { en: "Online" },
    hybrid:      { en: "Hybrid" },
    inperson:    { en: "In person" },
    prototypeNote: { en: "About this site" },
    protoMsg:    { en: "This site presents CYWater's real activities, history and awards. Forms for membership, registration and account are functional prototypes [Mock] — they do not process real payments or create real accounts." },
    protoToast:  { en: "[Mock] Prototype — no file attached." },
    protoSubmit: { en: "[Mock] Prototype — no submission portal yet." },
    mockBadge:   { en: "Mock" },

    /* Common tags / badge text */
    tagResearch:    { en: "Research" },
    tagEvent:       { en: "Event" },
    tagCommunity:   { en: "Community" },
    tagAward:       { en: "Award" },
    tagJournal:     { en: "Journal" },
    tagOpportunity: { en: "Opportunity" },
    tagConference:  { en: "Conference" },
    tagWebinar:     { en: "Webinar" },
    tagWorkshop:    { en: "Workshop" },
    tagSocial:      { en: "Social" },
    tagReview:      { en: "Review" },
    tagPerspective: { en: "Perspective" },
    tagDataPaper:   { en: "Data Paper" },
    tagResearchArt: { en: "Research Article" },
    paid:           { en: "Paid" },
    membersOnly:    { en: "Members only" },

    /* Month abbreviations */
    monJan: { en: "JAN" }, monFeb: { en: "FEB" },
    monMar: { en: "MAR" }, monApr: { en: "APR" },
    monMay: { en: "MAY" }, monJun: { en: "JUN" },
    monJul: { en: "JUL" }, monAug: { en: "AUG" },
    monSep: { en: "SEP" }, monOct: { en: "OCT" },
    monNov: { en: "NOV" }, monDec: { en: "DEC" },

    /* Journal / archive table headers */
    colVolume: { en: "Volume" },
    colIssue:  { en: "Issue" },
    colDate:   { en: "Date" },
    colTheme:  { en: "Theme" },
    colArticles: { en: "Articles" },
  },

  /* ---------- home ---------- */
  home: {
    heroEyebrow: { en: "Contemporary Young Scholars · Water Sciences" },
    heroTitle:   { en: "Advancing water sciences,<br>empowering young scholars." },
    heroLead:    { en: "An international, non-profit association advancing water sciences education, research, and professional development — through scientific exchange, publications, and conferences." },
    heroCta1:    { en: "Upcoming events" },
    heroCta2:    { en: "About CYWater" },

    missionEyebrow: { en: "Our mission" },
    missionTitle:   { en: "Connecting young water scholars across the world." },
    missionLead:    { en: "Since 2011, CYWater has connected early-career Chinese water researchers at home and abroad — advancing water sciences through exchange, conferences, and the annual Young Scientist Best Paper Award." },
    statMembers:    { en: "Years of community" },
    statCountries:  { en: "Countries & regions" },
    statEvents:     { en: "Summer Meetings" },
    statPapers:     { en: "Years of Best Paper Award" },

    pillarsEyebrow: { en: "What we do" },
    pillarsTitle:   { en: "Exchange, conferences, and recognition." },
    pillar1T: { en: "Annual Summer Meeting" },
    pillar1D: { en: "Our flagship gathering since 2013 — 13 editions connecting young water scholars across six continents." },
    pillar2T: { en: "AGU Winter Gathering" },
    pillar2D: { en: "An annual gathering during the AGU Fall Meeting, where CYWater first began in 2011." },
    pillar3T: { en: "Best Paper Award" },
    pillar3D: { en: "The Young Scientist Best Paper Award, recognising outstanding contributions to water sciences since 2012." },
    pillar4T: { en: "Bilingual Community" },
    pillar4D: { en: "A Chinese-English community bridging scholars at home and abroad across career stages." },

    focusEyebrow: { en: "Our themes" },
    focusTitle:   { en: "Frontiers of hydrological sciences." },
    focus1:  { en: "Hydroclimate & Global Change" },
    focus2:  { en: "Hydrological Hazards" },
    focus3:  { en: "Ecohydrology & Geomorphology" },
    focus4:  { en: "Observation & Modelling" },
    focus5:  { en: "Surface & Groundwater" },
    focus6:  { en: "Water Quality" },
    focus7:  { en: "Remote Sensing" },
    focus8:  { en: "AI for Water Science" },
    focus9:  { en: "Climate Adaptation" },
    focus10: { en: "Water Security" },

    statFounded:    { en: "Founded in San Francisco" },
    statFirstSummer:{ en: "First Summer Meeting" },
    statLanguages:  { en: "Languages, always (CN / EN)" },
    statPeakAttend: { en: "Peak online attendees (2022)" },

    latestEyebrow: { en: "Latest" },
    latestTitle:   { en: "Conferences, awards & announcements." },

    ctaEyebrow: { en: "Get involved" },
    ctaTitle: { en: "Join the CYWater community." },
    ctaLead:  { en: "For fifteen years, CYWater has connected young water scholars through bilingual exchange, annual meetings and the Best Paper Award." },
    ctaBtn1: { en: "See our events" },
    ctaBtn2: { en: "Read our story" },
  },

  /* ---------- about ---------- */
  about: {
    heroEyebrow: { en: "About CYWater" },
    heroTitle:   { en: "An international community of young water scholars, since 2011." },
    heroLead:    { en: "Founded in December 2011 in San Francisco, CYWater is a non-profit, international association connecting young Chinese water scholars at home and abroad through scientific exchange, annual meetings, and the Young Scientist Best Paper Award." },

    storyTitle:  { en: "Who we are" },
    storyP1: { en: "CYWater was initiated in December 2011 in San Francisco by a group of Chinese professionals in water-related sciences, during the AGU Fall Meeting. Its mission: to promote cooperation in water sciences between Chinese professionals abroad and those in China, and to advance water-science research and education worldwide." },
    storyP2: { en: "Since then, CYWater has held 13 Summer Meetings across China (2013–2025), an annual winter gathering during the AGU Fall Meeting, and the Young Scientist Best Paper Award every year since 2012. The community now spans more than 30 countries and regions across six continents." },

    valuesTitle: { en: "What we value" },
    v1T: { en: "Young scholars first" },
    v1D: { en: "Early-career researchers are the heart of the community — not just an audience we serve." },
    v2T: { en: "Truly international" },
    v2D: { en: "A network spanning six continents, with bilingual exchange as a founding principle." },
    v3T: { en: "Scientific exchange" },
    v3D: { en: "We advance water sciences through exchange, conferences, and recognition." },
    v4T: { en: "Non-profit & community-driven" },
    v4D: { en: "Run by and for the community of young water scholars, since 2011." },

    timelineTitle: { en: "Milestones" },
    t1: { en: "Association initiated in San Francisco during the AGU Fall Meeting" },
    t2: { en: "First Summer Meeting, Beijing" },
    t3: { en: "8th Summer Meeting went fully online — 630 peak attendees across six continents" },
    t4: { en: "12th Summer Meeting, Xi'an — added AI for Water Science as a new theme" },
    t5: { en: "13th Annual Meeting, co-hosted with the Yangtze Technology & Economy Society" },

    governanceTitle: { en: "Governance" },
    governanceP: { en: "CYWater is led by co-chairs and an executive office, supported by a Young Scientist Best Paper Award committee. The current co-chairs are Prof. Dong Chen and Prof. Qiuhong Tang (IGSNRR, CAS), and Prof. Yang Hong (University of Oklahoma)." },
  },

  /* ---------- board ---------- */
  board: {
    heroEyebrow: { en: "Leadership" },
    heroTitle:   { en: "The people leading CYWater." },
    heroLead:    { en: "CYWater is led by co-chairs and general secretaries, with guidance from honorary members and the Best Paper Award committee. Names below are drawn from the association's published records." },
    execTitle:   { en: "Co-Chairs" },
    boardTitle:  { en: "General Secretaries" },
    advisorsTitle: { en: "Honorary Members" },
    roleChair: { en: "Co-Chair" },
    roleSec:   { en: "General Secretary" },
    nameDC: { en: "Dong Chen" },
    affilDC:{ en: "IGSNRR, Chinese Academy of Sciences" },
    nameYH: { en: "Yang Hong" },
    affilYH:{ en: "University of Oklahoma" },
    nameQT: { en: "Qiuhong Tang" },
    affilQT:{ en: "Tsinghua University" },
    nameXL: { en: "Xingcai Liu" },
    affilXL:{ en: "IGSNRR, Chinese Academy of Sciences" },
    nameYZ: { en: "Yu Zhang" },
    affilYZ:{ en: "University of Oklahoma" },
    nameQD: { en: "Qingyun Duan" },
    affilQD:{ en: "Hohai University" },
    nameZX: { en: "Zhenghui Xie" },
    affilZX:{ en: "Institute of Atmospheric Physics, CAS" },
    nameJY: { en: "Jingjie Yu" },
    affilJY:{ en: "IGSNRR, Chinese Academy of Sciences" },
  },

  /* ---------- bylaws ---------- */
  bylaws: {
    heroEyebrow: { en: "Governance documents [Mock]" },
    heroTitle:   { en: "Bylaws (draft, illustrative)" },
    heroLead:    { en: "The text below is a [Mock] illustrative draft — it does not constitute CYWater's official bylaws. The authoritative bylaws will be published once ratified; until then please contact the secretariat for governance questions." },
    toc:         { en: "Contents" },
    download:    { en: "Download PDF" },
    a1T: { en: "Name and purpose" },
    a2T: { en: "Membership" },
    a3T: { en: "Membership dues" },
    a4T: { en: "Board of directors" },
    a5T: { en: "Officers" },
    a6T: { en: "Meetings" },
    a7T: { en: "Finances" },
    a8T: { en: "Amendments" },

    /* Article I */
    s1Name: { en: "Section 1. Name." },
    s1NameB: { en: "The name of this organization is the International Association of Contemporary Young Scholars in Water Sciences (CYWater)." },
    s1Purp: { en: "Section 2. Purpose." },
    s1PurpB: { en: "The Association is a non-profit, non-governmental, international member-based association advancing water sciences education, research, and professional development through scientific exchange, publications, and conferences." },
    s1Non: { en: "Section 3. Nonpartisan." },
    s1NonB: { en: "The Association shall not participate in any political campaign on behalf of any candidate for public office." },

    /* Article II */
    s2Class: { en: "Section 1. Classes." },
    s2ClassB: { en: "The Association has three classes of membership: Student, Regular, and Sustaining." },
    s2Elig: { en: "Section 2. Eligibility." },
    s2EligB: { en: "Membership is open to any individual with a bona fide interest in the water sciences. Student members must provide evidence of current enrollment." },
    s2Rights: { en: "Section 3. Rights." },
    s2RightsB: { en: "All members in good standing may vote in general elections, stand for office, and access member programs. Each member has one vote." },
    s2Term: { en: "Section 4. Termination." },
    s2TermB: { en: "Membership may be terminated by resignation, non-payment of dues, or by Board action for conduct contrary to the Association’s interests." },

    /* Article III */
    s3Amt: { en: "Section 1. Amount." },
    s3AmtB: { en: "Annual dues are set by the Board and published on the membership page." },
    s3Waive: { en: "Section 2. Waiver." },
    s3WaiveB: { en: "The Board may waive dues for any member upon written request demonstrating financial hardship." },
    s3Fisc: { en: "Section 3. Fiscal year." },
    s3FiscB: { en: "The fiscal year begins January 1." },

    /* Article IV */
    s4Comp: { en: "Section 1. Composition." },
    s4CompB: { en: "The Board of Directors consists of no fewer than seven and no more than fifteen members elected by the membership, including a President, President-Elect, Treasurer, and Directors-at-Large." },
    s4Term: { en: "Section 2. Term." },
    s4TermB: { en: "Directors serve two-year terms and may serve no more than three consecutive terms." },
    s4Duty: { en: "Section 3. Duties." },
    s4DutyB: { en: "The Board manages the property, affairs and policies of the Association." },

    /* Article V */
    s5Off: { en: "Section 1. Officers." },
    s5OffB: { en: "The officers are a President, President-Elect, Treasurer, and Executive Director, elected or appointed by the Board." },
    s5Duty: { en: "Section 2. Duties." },
    s5DutyB: { en: "Each officer’s duties are as prescribed by the Board and consistent with these bylaws." },

    /* Article VI */
    s6Annual: { en: "Section 1. Annual meeting." },
    s6AnnualB: { en: "An annual general meeting of members shall be held each year, with date and notice fixed by the Board." },
    s6Spec: { en: "Section 2. Special meetings." },
    s6SpecB: { en: "Special meetings may be called by the President or by petition of one-third of members." },
    s6Quo: { en: "Section 3. Quorum." },
    s6QuoB: { en: "Ten percent of members in good standing constitutes a quorum." },

    /* Article VII */
    s7Src: { en: "Section 1. Sources." },
    s7SrcB: { en: "The Association’s funds derive from dues, donations, grants and publication fees." },
    s7Aud: { en: "Section 2. Audit." },
    s7AudB: { en: "The Board shall cause an annual review of the Association’s finances to be prepared and made available to members." },

    /* Article VIII */
    s8Amend: { en: "Section 1." },
    s8AmendB: { en: "These bylaws may be amended by a two-thirds vote of the Board, provided the proposed amendment has been circulated to members at least thirty days in advance." },
  },

  /* ---------- membership ---------- */
  membership: {
    heroEyebrow: { en: "Membership" },
    heroTitle:   { en: "How to join the CYWater community." },
    heroLead:    { en: "CYWater is a volunteer-run community of young water scholars. To join, contact the secretariat by email — there is currently no online sign-up. The tiers and payment flows shown below are a [Mock] prototype and do not process real payments." },

    benefitsEyebrow: { en: "Benefits" },
    benefitsTitle:   { en: "What membership offers." },
    b1T: { en: "Annual meetings" },
    b1D: { en: "Priority notice and access to the Summer Meeting and AGU winter gathering." },
    b2T: { en: "Best Paper Award" },
    b2D: { en: "Eligibility for the Young Scientist Best Paper Award (under-35 first authors)." },
    b3T: { en: "Bilingual community" },
    b3D: { en: "A Chinese-English network connecting scholars at home and abroad." },
    b4T: { en: "Newsletter archive" },
    b4D: { en: "Access to CYWater's archive of community newsletters since 2012." },
    b5T: { en: "Career opportunities" },
    b5D: { en: "Job and position notices shared through the community channel." },
    b6T: { en: "Recognition" },
    b6D: { en: "Member recognition in community activities and award ceremonies." },

    tiersEyebrow: { en: "Dues [Mock]" },
    tiersTitle:   { en: "Tiers shown are illustrative only." },
    tiersLead:    { en: "The dues, tiers and online payment flow below are a functional prototype [Mock] — they do not reflect real fees and process no real payment. To actually join, email the secretariat." },
    tierStudent:  { en: "Student [Mock]" },
    tierStudentTag: { en: "With valid student ID" },
    tierRegular:  { en: "Regular [Mock]" },
    tierRegularTag: { en: "Most popular" },
    tierSustaining:{ en: "Sustaining [Mock]" },
    tierSustainingTag: { en: "Support our mission" },
    perYear:      { en: "/ year [Mock]" },
    tierStudentD: { en: "For enrolled students at any level." },
    tierRegularD: { en: "For researchers and practitioners." },
    tierSustainingD: { en: "For those who can give more." },

    faqEyebrow: { en: "How to actually join" },
    faqTitle:   { en: "Contact the secretariat." },
    faq1Q: { en: "How do I join CYWater?" },
    faq1A: { en: "Email the CYWater secretariat to express your interest. There is currently no online sign-up form; membership is handled by the volunteer office." },
    faq2Q: { en: "Who can join?" },
    faq2A: { en: "CYWater is open to young scholars, researchers and students in water-related sciences, at home and abroad." },
    faq3Q: { en: "Is there a membership fee?" },
    faq3A: { en: "CYWater has historically been a free, volunteer-run community. Any future dues structure will be announced through the official WeChat channel." },
    faq4Q: { en: "Where is CYWater based?" },
    faq4A: { en: "CYWater was long hosted by the Institute of Geographic Sciences and Natural Resources Research (IGSNRR), CAS. Please contact the secretariat for the current affiliation." },
  },

  /* ---------- member dashboard ---------- */
  dash: {
    mockBanner: { en: "[Mock] This dashboard is a non-functional prototype. CYWater has no online account system — there is no real login or member data." },
    hello:    { en: "Hello" },
    overview: { en: "Overview" },
    profile:  { en: "Profile" },
    membership: { en: "Membership" },
    events:   { en: "My events" },
    receipts: { en: "Receipts" },
    signOut:  { en: "Sign out" },
    titleOverview: { en: "Your CYWater at a glance" },
    memberSince:   { en: "Member since" },
    expiresOn:     { en: "Expires" },
    daysLeft:      { en: "days left" },
    renew:         { en: "Renew membership" },
    upcomingTitle: { en: "Upcoming for you" },
    receiptsTitle: { en: "Recent receipts" },
    statusActive:  { en: "Active" },
    colDate:   { en: "Date" },
    colDesc:   { en: "Description" },
    colAmount: { en: "Amount" },
    colStatus: { en: "Status" },
    colFile:   { en: "Receipt" },
    download:  { en: "Download" },
    welcomeBack: { en: "Welcome back, Lin." },
    cycleProgress: { en: "62% through cycle" },
    evAnnual: { en: "Annual Meeting 2026" },
    evAnnualLoc: { en: "Boston, MA · Hybrid" },
    evWebinar: { en: "Webinar: Remote sensing of water quality" },
    recDues2026: { en: "Annual dues 2026 — Regular" },
    recReg: { en: "Annual Meeting 2026 registration" },
    recDues2025: { en: "Annual dues 2025 — Regular" },
  },

  /* ---------- events ---------- */
  events: {
    heroEyebrow: { en: "Events" },
    heroTitle:   { en: "Thirteen Summer Meetings, and counting." },
    heroLead:    { en: "Our flagship Summer Meeting has run every year since 2013, hosted by universities across China, with an annual winter gathering during the AGU Fall Meeting. Records below link to the original announcements." },
    filtersTitle: { en: "Filter" },
    fType: { en: "Type" },
    fFormat: { en: "Format" },
    fAll: { en: "All" },
    fWebinar: { en: "Webinar" },
    fWorkshop: { en: "Workshop" },
    fConference: { en: "Conference" },
    fSocial: { en: "Social" },
    upcoming: { en: "Recent & upcoming" },
    past:     { en: "Earlier Summer Meetings" },
    seatsLeft: { en: "seats left" },
    memberRate: { en: "Member rate" },
    tagJoint: { en: "Co-hosted" },
    tagAnnual: { en: "Annual" },
    detailSchedule: { en: "Schedule" },
    detailSpeakers: { en: "Speakers" },
    detailAbout: { en: "About this event" },
    detailOrganizer: { en: "Organizer" },
    detailShare: { en: "Share" },
    detailOtherEvents: { en: "Other events" },
    detailMemberPrice: { en: "Members" },
    detailNonMemberPrice: { en: "Non-members" },
    detailIncludes: { en: "Includes" },

    /* List and detail content */
    e1Title: { en: "13th Annual Meeting (1st round notice)" },
    e1City:  { en: "Co-hosted with the Yangtze Technology & Economy Society" },
    e1Loc:   { en: "China" },
    e1Dur:   { en: "2025" },
    e2Title: { en: "Young Scientist Best Paper Award 2025 — Result" },
    e2Time:  { en: "Announced Dec 2025" },
    e3Title: { en: "12th Summer Meeting — Xi'an University of Technology" },
    e3Loc:   { en: "Xi'an, China" },
    e3Meta:  { en: "Aug 2024" },
    e4Title: { en: "10th Summer Meeting — Online" },
    e4Loc:   { en: "Online · 6 continents, 30+ countries" },
    e5Title: { en: "8th Summer Meeting — Online" },
    e5Meta:  { en: "Aug 2020 · 630 peak attendees · 106 institutions" },

    /* Detail page — content is mock placeholder, title reuses first list item */
    detailMockBanner: { en: "[Mock] The detailed schedule below is a prototype layout. For the real 13th Annual Meeting programme, see the official WeChat announcement." },
    detailBread: { en: "13th Annual Meeting" },
    detailDate:  { en: "2025 (see WeChat notice)" },
    detailExpected: { en: "see official notice" },
    detailLead:  { en: "The 13th CYWater Annual Meeting is co-hosted with the Youth Committee of the Yangtze Technology & Economy Society. The full programme, venue and registration details are published through the official WeChat channel." },
    detailBody:  { en: "[Mock] The day-by-day schedule and speakers shown below are illustrative placeholders, carried over from the prototype layout. They do not represent the real programme." },
    day1: { en: "Day 1 · Sep 18" },
    day2: { en: "Day 2 · Sep 19" },
    day3: { en: "Day 3 · Sep 20" },
    s1T: { en: "Opening & keynote" },
    s1D: { en: "Dr. Lin Zhao — “Water Across Boundaries: the next decade”" },
    s2T: { en: "Session: Climate & the water cycle" },
    s2D: { en: "Five talks, bilingual Q&A." },
    s3T: { en: "Poster session & mentorship meet-up" },
    s3D: { en: "140 posters · structured mentor matching." },
    s4T: { en: "Session: Water quality & health" },
    s4D: { en: "Eight talks across contaminants and treatment." },
    s5T: { en: "Workshops (parallel)" },
    s5D: { en: "Grant writing · Open data · Career pathways." },
    s6T: { en: "Session: Urban water & policy" },
    s6D: { en: "Six talks and a panel discussion." },
    s7T: { en: "Awards & closing" },
    s7D: { en: "Early-career award, mentorship showcases, AGM." },
    inc1: { en: "All sessions, workshops & poster session" },
    inc2: { en: "Bilingual interpretation" },
    inc3: { en: "Networking & mentorship meet-up" },
  },

  /* ---------- registration modal ---------- */
  reg: {
    title: { en: "Register for event" },
    step1Title: { en: "Your details" },
    step2Title: { en: "Ticket" },
    step3Title: { en: "Confirm" },
    firstName: { en: "First name" },
    lastName: { en: "Last name" },
    email: { en: "Email" },
    org: { en: "Affiliation" },
    ticket: { en: "Ticket type" },
    ticketMember: { en: "Member (free)" },
    ticketNonMember: { en: "Non-member" },
    ticketStudent: { en: "Student" },
    reviewNote: { en: "[Mock] Review your details. This is a prototype — no real registration or payment is processed." },
    confirmation: { en: "[Mock] You're registered! No real confirmation is sent — this is a prototype." },
    successTitle: { en: "You're registered!" },
    done: { en: "Done" },
  },

  /* ---------- news ---------- */
  news: {
    heroEyebrow: { en: "News & announcements" },
    heroTitle:   { en: "What's happening at CYWater." },
    heroLead:    { en: "Conference announcements, award results and community news from the official CYWater WeChat channel." },
    popular: { en: "Most read" },
    categories: { en: "Categories" },
    minRead: { en: "min read" },
    by: { en: "Source" },

    /* News items */
    n1Title: { en: "13th Annual Meeting — 3rd round notice" },
    n1Excerpt: { en: "Co-hosted with the Yangtze Technology & Economy Society Youth Committee. Full programme and venue details released." },
    n2Title: { en: "Young Scientist Best Paper Award 2025 — Result & Ceremony" },
    n2Excerpt: { en: "The 2025 Best Paper Award result has been announced, with the ceremony held during the AGU Fall Meeting." },
    n3Title: { en: "12th Summer Meeting — Xi'an, Aug 2024" },
    n3Excerpt: { en: "Hosted by Xi'an University of Technology. Theme: \"Gathering strength for new quality productive forces in water.\"" },
    n4Title: { en: "Young Scientist Best Paper Award 2025 — call open" },
    n4Excerpt: { en: "Applications open for water-science papers by first authors under 35. One Best Paper Award and up to two Outstanding Paper Awards." },
    n5Title: { en: "10th Summer Meeting — Online, Aug 2022" },
    n5Excerpt: { en: "Six continents, 30+ countries, 630 peak attendees. Theme: Water interaction with the Earth system." },
    n6Title: { en: "8th Summer Meeting — Online, Aug 2020" },
    n6Excerpt: { en: "106 institutions, 76 talks. Invited speakers: Aiguo Dai, Kevin Trenberth, Kun Yang, Ge Sun." },

    /* Article body (article.html) — placeholder notice */
    artLead: { en: "This article page is a [Mock] placeholder. Full text is available on the official CYWater WeChat channel — follow the link below to read the original announcement." },
    artP1: { en: "CYWater publishes its announcements through its official WeChat channel (CYWater). The full text of this announcement, including all details and attachments, is preserved there." },
    artMockBadge: { en: "[Mock] Article body not migrated" },
    artReadOriginal: { en: "Read the original on WeChat" },
    artAuthorAffil: { en: "CYWater official channel" },
  },

  /* ---------- journal ---------- */
  journal: {
    heroEyebrow: { en: "Journal" },
    heroTitle:   { en: "Coming soon." },
    heroLead:    { en: "CYWater does not yet publish a journal. This page is reserved for future publication plans." },
    comingBadge: { en: "Coming soon" },
    comingTitle: { en: "A journal is on our roadmap." },
    comingLead:  { en: "For fifteen years, CYWater has shared water-science research through conferences, the Best Paper Award and the WeChat channel. A dedicated open-access publication is part of our next stage." },
    comingLead2: { en: "Until then, please follow our latest research news through the News page and the official WeChat channel." },
    comingCta:   { en: "Read recent news" },
    /* Keys below kept for compatibility but no longer rendered */
    latest: { en: "Current issue" },
    archive: { en: "Archive" },
    submit: { en: "Submit a paper" },
    submitLead: { en: "Submissions are open year-round. Members receive publication-fee waivers." },
    volLabel: { en: "Volume" },
    issueLabel: { en: "Issue" },
    articles: { en: "articles" },
    currentIssue: { en: "—" },
    archTheme1: { en: "—" },
    archInaugural: { en: "—" },
  },

  /* ---------- contact ---------- */
  contact: {
    heroEyebrow: { en: "Contact" },
    heroTitle:   { en: "Get in touch." },
    heroLead:    { en: "CYWater is a volunteer-run community. For the fastest response, follow the official WeChat channel (CYWater). The form below is a [Mock] prototype and sends no real email." },
    formTitle: { en: "Send a message [Mock]" },
    fName: { en: "Full name" },
    fEmail: { en: "Email" },
    fSubject: { en: "Subject" },
    fMembership: { en: "Membership" },
    fEvents: { en: "Events" },
    fPartnership: { en: "Partnership" },
    fOther: { en: "Other" },
    fMessage: { en: "Message" },
    send: { en: "Send message [Mock]" },
    emailLabel: { en: "Email" },
    addressLabel: { en: "Mailing address" },
    responseLabel: { en: "Response time" },
    emailVal: { en: "via official WeChat (CYWater)" },
    addressVal: { en: "To be confirmed — contact the secretariat" },
    responseVal: { en: "Best-effort, volunteer-run" },
    wechatHint: { en: "WeChat is the fastest channel" },
  },
};

/* ---------- controller (English only — bilingual support removed) ---------- */

function t(key) {
  const parts = key.split(".");
  let node = I18N;
  for (const p of parts) {
    node = node?.[p];
    if (!node) return key;
  }
  return node.en || key;
}

function applyTranslations() {
  document.querySelectorAll("[data-i18n]").forEach((el) => {
    const key = el.getAttribute("data-i18n");
    const val = t(key);
    if (val && val !== key) {
      // preserve any child elements that have their own data-i18n
      const child = el.querySelector("[data-i18n]");
      if (!child) el.innerHTML = val;
    }
  });

  // attributes: data-i18n-attr="placeholder:contact.emailPlaceholder"
  document.querySelectorAll("[data-i18n-attr]").forEach((el) => {
    const spec = el.getAttribute("data-i18n-attr");
    spec.split(",").forEach((pair) => {
      const [attr, key] = pair.split(":").map((s) => s.trim());
      if (attr && key) {
        const val = t(key);
        if (val && val !== key) el.setAttribute(attr, val);
      }
    });
  });
}

window.CYWaterI18N = { t, applyTranslations };
document.addEventListener("DOMContentLoaded", applyTranslations);
