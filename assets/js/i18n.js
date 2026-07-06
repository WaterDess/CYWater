/* ==========================================================================
   CYWater · i18n dictionary + controller
   Bilingual (en / zh). All UI strings live here, referenced by data-i18n.
   ========================================================================== */

const I18N = {
  /* ---------- meta ---------- */
  meta: {
    siteName: { en: "CYWater", zh: "CYWater" },
    siteFull: { en: "Int'l Association of Contemporary Young Scholars in Water Sciences", zh: "国际水科学青年学者协会" },
    tagline:  { en: "Advancing water sciences, empowering young scholars", zh: "推动水科学发展，赋能青年学者" },
  },

  /* ---------- nav ---------- */
  nav: {
    home:       { en: "Home",       zh: "首页" },
    about:      { en: "About",      zh: "关于我们" },
    board:      { en: "Board",      zh: "理事会" },
    bylaws:     { en: "Bylaws",     zh: "章程" },
    membership: { en: "Membership", zh: "会员" },
    events:     { en: "Events",     zh: "活动" },
    news:       { en: "News",       zh: "新闻" },
    journal:    { en: "Journal",    zh: "期刊" },
    contact:    { en: "Contact",    zh: "联系我们" },
    signIn:     { en: "Sign in",    zh: "登录" },
    join:       { en: "Join CYWater", zh: "加入 CYWater" },
    dashboard:  { en: "Dashboard",  zh: "会员中心" },
  },

  /* ---------- footer ---------- */
  footer: {
    explore:    { en: "Explore",      zh: "浏览" },
    association:{ en: "Association",  zh: "协会" },
    resources:  { en: "Resources",    zh: "资源" },
    connect:    { en: "Connect",      zh: "联系" },
    newsletter: { en: "Newsletter",   zh: "订阅简报" },
    newsletterHint: { en: "Quarterly updates on research, events and opportunities.",
                      zh: "每季度推送研究、活动与机会动态。" },
    emailPlaceholder: { en: "you@email.com", zh: "你的邮箱" },
    subscribe:  { en: "Subscribe", zh: "订阅" },
    tagline:    { en: "A non-profit, non-governmental, international member-based association advancing water sciences education, research, and professional development through scientific exchange, publications, and conferences.",
                  zh: "一个非营利、非政府、国际性会员制协会，通过学术交流、出版与会议，推动水科学的教育、研究与职业发展。" },
    rights:     { en: "© 2026 CYWater. All rights reserved.", zh: "© 2026 CYWater. 保留所有权利。" },
    privacy:    { en: "Privacy",   zh: "隐私" },
    terms:      { en: "Terms",     zh: "条款" },
    bylaws:     { en: "Bylaws",    zh: "章程" },
  },

  /* ---------- common ---------- */
  common: {
    learnMore:   { en: "Learn more",   zh: "了解更多" },
    viewAll:     { en: "View all",     zh: "查看全部" },
    readMore:    { en: "Read more",    zh: "阅读全文" },
    register:    { en: "Register",     zh: "立即报名" },
    back:        { en: "Back",         zh: "返回" },
    submit:      { en: "Submit",       zh: "提交" },
    next:        { en: "Next",         zh: "下一步" },
    previous:    { en: "Previous",     zh: "上一步" },
    cancel:      { en: "Cancel",       zh: "取消" },
    continue:    { en: "Continue",     zh: "继续" },
    sent:        { en: "Message sent — we'll reply within 2 business days.",
                   zh: "已收到，我们将在 2 个工作日内回复。" },
    subscribed:  { en: "Subscribed! Check your inbox to confirm.", zh: "订阅成功！请查收邮件确认。" },
    registered:  { en: "Registration received (prototype).", zh: "报名已收到（原型演示）。" },
    joined:      { en: "Welcome aboard! (prototype)", zh: "欢迎加入！（原型演示）" },
    saved:       { en: "Saved (prototype).", zh: "已保存（原型演示）。" },
    member:      { en: "Member",  zh: "会员" },
    nonmember:   { en: "Non-member", zh: "非会员" },
    free:        { en: "Free", zh: "免费" },
    online:      { en: "Online", zh: "线上" },
    hybrid:      { en: "Hybrid", zh: "线上线下" },
    inperson:    { en: "In person", zh: "线下" },
    prototypeNote: { en: "Prototype notice", zh: "原型说明" },
    protoMsg:    { en: "This is a functional prototype. Payment, membership and account features are simulated and do not process real transactions.",
                   zh: "本站为功能原型。支付、会员与账户功能均为模拟，不会发生真实交易。" },
    protoToast:  { en: "Prototype — no file attached.", zh: "原型演示 — 无附件。" },
    protoSubmit: { en: "Prototype — no submission portal.", zh: "原型演示 — 暂无投稿入口。" },

    /* 通用标签 / badge 文字 */
    tagResearch:    { en: "Research",    zh: "研究" },
    tagEvent:       { en: "Event",       zh: "活动" },
    tagCommunity:   { en: "Community",   zh: "共同体" },
    tagAward:       { en: "Award",       zh: "奖项" },
    tagJournal:     { en: "Journal",     zh: "期刊" },
    tagOpportunity: { en: "Opportunity", zh: "机会" },
    tagConference:  { en: "Conference",  zh: "会议" },
    tagWebinar:     { en: "Webinar",     zh: "网络讲座" },
    tagWorkshop:    { en: "Workshop",    zh: "工作坊" },
    tagSocial:      { en: "Social",      zh: "社交" },
    tagReview:      { en: "Review",      zh: "综述" },
    tagPerspective: { en: "Perspective", zh: "观点" },
    tagDataPaper:   { en: "Data Paper",  zh: "数据论文" },
    tagResearchArt: { en: "Research Article", zh: "研究论文" },
    paid:           { en: "Paid",        zh: "已支付" },
    membersOnly:    { en: "Members only", zh: "仅限会员" },

    /* 月份缩写 */
    monJan: { en: "JAN", zh: "1月" }, monFeb: { en: "FEB", zh: "2月" },
    monMar: { en: "MAR", zh: "3月" }, monApr: { en: "APR", zh: "4月" },
    monMay: { en: "MAY", zh: "5月" }, monJun: { en: "JUN", zh: "6月" },
    monJul: { en: "JUL", zh: "7月" }, monAug: { en: "AUG", zh: "8月" },
    monSep: { en: "SEP", zh: "9月" }, monOct: { en: "OCT", zh: "10月" },
    monNov: { en: "NOV", zh: "11月" }, monDec: { en: "DEC", zh: "12月" },

    /* 期刊/归档表头 */
    colVolume: { en: "Volume", zh: "卷" },
    colIssue:  { en: "Issue",  zh: "期" },
    colDate:   { en: "Date",   zh: "日期" },
    colTheme:  { en: "Theme",  zh: "主题" },
    colArticles: { en: "Articles", zh: "文章数" },
  },

  /* ---------- home ---------- */
  home: {
    heroEyebrow: { en: "Contemporary Young Scholars · Water Sciences", zh: "青年学者 · 水科学" },
    heroTitle:   { en: "Advancing water sciences,<br>empowering young scholars.", zh: "推动水科学发展，<br>赋能青年学者。" },
    heroLead:    { en: "An international, non-profit association advancing water sciences education, research, and professional development — through scientific exchange, publications, and conferences.",
                    zh: "一个国际性、非营利协会，通过学术交流、出版与会议，推动水科学教育、研究与职业发展。" },
    heroCta1:    { en: "Join CYWater", zh: "加入 CYWater" },
    heroCta2:    { en: "Explore research", zh: "了解我们的研究" },

    missionEyebrow: { en: "Our mission", zh: "我们的使命" },
    missionTitle:   { en: "Empowering the next generation of water scientists.",
                      zh: "赋能新一代水科学人。" },
    missionLead:    { en: "Since 2011, CYWater has connected early-career researchers, educators and practitioners across the world — advancing water sciences through exchange, publications, and conferences that bridge languages and career stages.",
                      zh: "自 2011 年起，CYWater 连结全球的青年研究者、教育者与从业者，通过跨越语言与职业阶段的交流、出版与会议，推动水科学发展。" },
    statMembers:    { en: "Members worldwide", zh: "全球会员" },
    statCountries:  { en: "Countries & regions", zh: "国家与地区" },
    statEvents:     { en: "Events / year", zh: "年度活动" },
    statPapers:     { en: "Papers published", zh: "已发表论文" },

    pillarsEyebrow: { en: "What we do", zh: "我们做什么" },
    pillarsTitle:   { en: "Education, research & professional development.", zh: "教育、研究与职业发展。" },
    pillar1T: { en: "Research & Journal", zh: "研究与期刊" },
    pillar1D: { en: "Peer-reviewed publications that advance water sciences and give young scholars a platform.", zh: "同行评审的出版成果，推动水科学发展，为青年学者提供舞台。" },
    pillar2T: { en: "Conferences & Events", zh: "会议与活动" },
    pillar2D: { en: "International conferences, workshops and exchanges that bridge languages and disciplines.", zh: "国际会议、工作坊与学术交流，跨越语言与学科边界。" },
    pillar3T: { en: "Community", zh: "共同体" },
    pillar3D: { en: "A global network connecting students, early-career researchers and senior scientists.", zh: "一个全球网络，连结学生、青年研究者与资深学者。" },
    pillar4T: { en: "Career Development", zh: "职业发展" },
    pillar4D: { en: "Mentorship, awards and resources that empower young water professionals.", zh: "师友计划、奖项与资源，赋能青年水领域从业者。" },

    focusEyebrow: { en: "Focus areas", zh: "关注领域" },
    focusTitle:   { en: "Where we focus our energy.", zh: "我们聚焦的方向。" },
    focus1:  { en: "Hydrology", zh: "水文学" },
    focus2:  { en: "Water Quality", zh: "水质" },
    focus3:  { en: "Climate & Water", zh: "气候与水" },
    focus4:  { en: "Urban Water", zh: "城市水" },
    focus5:  { en: "Aquatic Ecosystems", zh: "水生生态系统" },
    focus6:  { en: "Water Policy", zh: "水政策" },
    focus7:  { en: "Remote Sensing", zh: "遥感" },
    focus8:  { en: "Water & Health", zh: "水与健康" },
    focus9:  { en: "Wastewater", zh: "废水" },
    focus10: { en: "Groundwater", zh: "地下水" },

    statFounded:    { en: "Founded", zh: "成立年份" },
    statMemberRun:  { en: "Member-run", zh: "会员自治" },
    statLanguages:  { en: "Languages, always", zh: "双语为本" },
    statOpenAccess: { en: "Open access", zh: "开放获取" },

    latestEyebrow: { en: "Latest", zh: "最新动态" },
    latestTitle:   { en: "News, research & events.", zh: "新闻、研究与活动。" },

    ctaTitle: { en: "Become part of CYWater.", zh: "成为 CYWater 的一员。" },
    ctaLead:  { en: "Membership is open to researchers, practitioners and students in any water-related field.",
                zh: "会员面向所有水相关领域的研究者、从业者与学生开放。" },
  },

  /* ---------- about ---------- */
  about: {
    heroEyebrow: { en: "About CYWater", zh: "关于 CYWater" },
    heroTitle:   { en: "A non-profit, international association for young water scholars.", zh: "面向青年水科学人的非营利国际协会。" },
    heroLead:    { en: "Established in 2011, CYWater is a non-profit, non-governmental, international member-based association advancing water sciences education, research, and professional development worldwide.",
                    zh: "CYWater 成立于 2011 年，是一个非营利、非政府、国际性会员制协会，致力于在全球推动水科学的教育、研究与职业发展。" },

    storyTitle:  { en: "Who we are", zh: "我们是谁" },
    storyP1: { en: "CYWater was established in 2011 as the International Association of Chinese Youth in Water Sciences, founded to connect young Chinese water researchers with the international community. Over fifteen years it grew into a global network spanning many countries and disciplines.",
               zh: "CYWater 于 2011 年以“国际华人水科学青年学者协会”之名成立，初衷是连结中国青年水研究者与国际学术共同体。十五年间，它成长为跨越多个国家与学科的全球网络。" },
    storyP2: { en: "In 2026, the association was renamed to the International Association of Contemporary Young Scholars in Water Sciences (CYWater) — reflecting its broadened, truly international scope while keeping its founding commitment to early-career scholars. We advance water sciences through scientific exchange, publications, and conferences.",
               zh: "2026 年，协会更名为“国际水科学青年学者协会”（CYWater），彰显其更宽广、真正国际化的视野，同时坚守服务青年学者的创立初心。我们通过学术交流、出版与会议推动水科学发展。" },

    valuesTitle: { en: "What we value", zh: "我们的价值观" },
    v1T: { en: "Young scholars first", zh: "青年学者为先" },
    v1D: { en: "Early-career researchers are our core — not just an audience we serve.", zh: "青年研究者是我们的核心——而非仅仅是被服务的受众。" },
    v2T: { en: "Truly international", zh: "真正的国际化" },
    v2D: { en: "A global network that spans countries, languages and disciplines.", zh: "一个跨越国家、语言与学科的全球网络。" },
    v3T: { en: "Scientific exchange", zh: "学术交流" },
    v3D: { en: "We advance water sciences through exchange, publications, and conferences.", zh: "我们通过交流、出版与会议推动水科学进步。" },
    v4T: { en: "Non-profit & member-driven", zh: "非营利与会员共建" },
    v4D: { en: "Governed by and for our members, since 2011.", zh: "自 2011 年起，由会员共建、为会员服务。" },

    timelineTitle: { en: "Milestones", zh: "重要节点" },
    t1: { en: "Association established", zh: "协会成立" },
    t2: { en: "First international conferences", zh: "首批国际会议" },
    t3: { en: "Global network across many countries", zh: "覆盖多国的全球网络" },
    t4: { en: "Renamed CYWater — broader international scope", zh: "更名 CYWater，国际化升级" },
    t5: { en: "Continued growth of publications & events", zh: "出版与活动持续增长" },

    governanceTitle: { en: "Governance", zh: "治理结构" },
    governanceP: { en: "CYWater is governed by an elected Board of Directors, including a President, President-Elect, Treasurer, Directors-at-Large and an Executive Director. Board terms run two years; the current term is January 2026 – December 2027.",
                   zh: "CYWater 由选举产生的理事会治理，设主席、候任主席、司库、理事及执行主任。理事任期两年，本届任期自 2026 年 1 月至 2027 年 12 月。" },
  },

  /* ---------- board ---------- */
  board: {
    heroEyebrow: { en: "Board of Directors", zh: "理事会" },
    heroTitle:   { en: "The Board of Directors, 2026–2027.", zh: "理事会（2026–2027 届）。" },
    heroLead:    { en: "CYWater is governed by an elected Board, including a President, President-Elect, Treasurer, Directors-at-Large, and an Executive Director. The current term runs January 2026 – December 2027.",
                    zh: "CYWater 由选举产生的理事会治理，设主席、候任主席、司库、理事及执行主任。本届任期自 2026 年 1 月至 2027 年 12 月。" },
    execTitle:   { en: "Officers", zh: "执委" },
    boardTitle:  { en: "Directors-at-Large", zh: "理事" },
    advisorsTitle: { en: "Executive office", zh: "执行机构" },
  },

  /* ---------- bylaws ---------- */
  bylaws: {
    heroEyebrow: { en: "Governance documents", zh: "治理文件" },
    heroTitle:   { en: "Bylaws of CYWater", zh: "CYWater 章程" },
    heroLead:    { en: "Established 2011 · Renamed 2026. The authoritative text is the English version.",
                    zh: "2011 年成立 · 2026 年更名。以英文版本为准。" },
    toc:         { en: "Contents", zh: "目录" },
    download:    { en: "Download PDF", zh: "下载 PDF" },
    a1T: { en: "Name and purpose", zh: "名称与宗旨" },
    a2T: { en: "Membership", zh: "会员" },
    a3T: { en: "Membership dues", zh: "会费" },
    a4T: { en: "Board of directors", zh: "理事会" },
    a5T: { en: "Officers", zh: "职员" },
    a6T: { en: "Meetings", zh: "会议" },
    a7T: { en: "Finances", zh: "财务" },
    a8T: { en: "Amendments", zh: "修订" },

    /* Article I */
    s1Name: { en: "Section 1. Name.", zh: "第一条 名称。" },
    s1NameB: { en: "The name of this organization is the International Association of Contemporary Young Scholars in Water Sciences (CYWater).",
               zh: "本组织名称为“国际水科学青年学者协会”（CYWater）。" },
    s1Purp: { en: "Section 2. Purpose.", zh: "第二条 宗旨。" },
    s1PurpB: { en: "The Association is a non-profit, non-governmental, international member-based association advancing water sciences education, research, and professional development through scientific exchange, publications, and conferences.",
               zh: "本协会是一个非营利、非政府、国际性会员制协会，通过学术交流、出版与会议，推动水科学的教育、研究与职业发展。" },
    s1Non: { en: "Section 3. Nonpartisan.", zh: "第三条 非党派。" },
    s1NonB: { en: "The Association shall not participate in any political campaign on behalf of any candidate for public office.",
              zh: "本协会不得为任何公职候选人参与任何政治竞选活动。" },

    /* Article II */
    s2Class: { en: "Section 1. Classes.", zh: "第一条 类别。" },
    s2ClassB: { en: "The Association has three classes of membership: Student, Regular, and Sustaining.",
                zh: "本协会设三类会员：学生会员、普通会员与赞助会员。" },
    s2Elig: { en: "Section 2. Eligibility.", zh: "第二条 入会资格。" },
    s2EligB: { en: "Membership is open to any individual with a bona fide interest in the water sciences. Student members must provide evidence of current enrollment.",
               zh: "凡对水科学有真实兴趣者均可入会。学生会员须提供在读证明。" },
    s2Rights: { en: "Section 3. Rights.", zh: "第三条 权利。" },
    s2RightsB: { en: "All members in good standing may vote in general elections, stand for office, and access member programs. Each member has one vote.",
                 zh: "所有信誉良好之会员均可参加选举投票、参选职务并使用会员项目。每位会员一票。" },
    s2Term: { en: "Section 4. Termination.", zh: "第四条 终止。" },
    s2TermB: { en: "Membership may be terminated by resignation, non-payment of dues, or by Board action for conduct contrary to the Association’s interests.",
               zh: "会员资格可因辞职、未缴纳会费，或理事会因有损协会利益之行为而终止。" },

    /* Article III */
    s3Amt: { en: "Section 1. Amount.", zh: "第一条 金额。" },
    s3AmtB: { en: "Annual dues are set by the Board and published on the membership page.",
              zh: "年度会费由理事会设定，并公布于会员页面。" },
    s3Waive: { en: "Section 2. Waiver.", zh: "第二条 减免。" },
    s3WaiveB: { en: "The Board may waive dues for any member upon written request demonstrating financial hardship.",
                zh: "理事会可应书面申请，对经济困难之会员减免会费。" },
    s3Fisc: { en: "Section 3. Fiscal year.", zh: "第三条 财政年度。" },
    s3FiscB: { en: "The fiscal year begins January 1.", zh: "财政年度自 1 月 1 日起。" },

    /* Article IV */
    s4Comp: { en: "Section 1. Composition.", zh: "第一条 构成。" },
    s4CompB: { en: "The Board of Directors consists of no fewer than seven and no more than fifteen members elected by the membership, including a President, President-Elect, Treasurer, and Directors-at-Large.",
               zh: "理事会由不少于七人、不多于十五人组成，由会员选举产生，设主席、候任主席、司库与理事。" },
    s4Term: { en: "Section 2. Term.", zh: "第二条 任期。" },
    s4TermB: { en: "Directors serve two-year terms and may serve no more than three consecutive terms.",
               zh: "理事任期两年，连续任职不超过三届。" },
    s4Duty: { en: "Section 3. Duties.", zh: "第三条 职责。" },
    s4DutyB: { en: "The Board manages the property, affairs and policies of the Association.",
               zh: "理事会管理本协会的财产、事务与政策。" },

    /* Article V */
    s5Off: { en: "Section 1. Officers.", zh: "第一条 职员。" },
    s5OffB: { en: "The officers are a President, President-Elect, Treasurer, and Executive Director, elected or appointed by the Board.",
              zh: "职员包括主席、候任主席、司库与执行主任，由理事会选举或任命。" },
    s5Duty: { en: "Section 2. Duties.", zh: "第二条 职责。" },
    s5DutyB: { en: "Each officer’s duties are as prescribed by the Board and consistent with these bylaws.",
               zh: "各职员之职责由理事会规定，并与本章程一致。" },

    /* Article VI */
    s6Annual: { en: "Section 1. Annual meeting.", zh: "第一条 年度会议。" },
    s6AnnualB: { en: "An annual general meeting of members shall be held each year, with date and notice fixed by the Board.",
                zh: "每年应召开一次会员年度大会，日期与通知由理事会确定。" },
    s6Spec: { en: "Section 2. Special meetings.", zh: "第二条 特别会议。" },
    s6SpecB: { en: "Special meetings may be called by the President or by petition of one-third of members.",
               zh: "特别会议可由主席或三分之一会员联署要求召开。" },
    s6Quo: { en: "Section 3. Quorum.", zh: "第三条 法定人数。" },
    s6QuoB: { en: "Ten percent of members in good standing constitutes a quorum.",
              zh: "信誉良好之会员的百分之十构成法定人数。" },

    /* Article VII */
    s7Src: { en: "Section 1. Sources.", zh: "第一条 收入来源。" },
    s7SrcB: { en: "The Association’s funds derive from dues, donations, grants and publication fees.",
              zh: "本协会的资金来源于会费、捐赠、资助与出版费。" },
    s7Aud: { en: "Section 2. Audit.", zh: "第二条 审计。" },
    s7AudB: { en: "The Board shall cause an annual review of the Association’s finances to be prepared and made available to members.",
              zh: "理事会应安排对本协会财务进行年度审查，并向会员公开。" },

    /* Article VIII */
    s8Amend: { en: "Section 1.", zh: "第一条。" },
    s8AmendB: { en: "These bylaws may be amended by a two-thirds vote of the Board, provided the proposed amendment has been circulated to members at least thirty days in advance.",
                zh: "本章程可经理事会三分之二多数票修订，但拟议修正案须至少提前三十天向会员公布。" },
  },

  /* ---------- membership ---------- */
  membership: {
    heroEyebrow: { en: "Membership", zh: "会员" },
    heroTitle:   { en: "Join a community that takes your work seriously.", zh: "加入一个认真对待你工作的共同体。" },
    heroLead:    { en: "Membership is annual and open to anyone in a water-related field — students, researchers and practitioners alike.",
                    zh: "会员按年缴纳，面向所有水相关领域的人士开放——学生、研究者与从业者皆可加入。" },

    benefitsEyebrow: { en: "Benefits", zh: "会员权益" },
    benefitsTitle:   { en: "What you get as a member.", zh: "会员能获得什么。" },
    b1T: { en: "Members-only events", zh: "会员专享活动" },
    b1D: { en: "Small-group mentorship sessions and member rate at all paid events.", zh: "小组师友交流，所有付费活动享受会员价。" },
    b2T: { en: "Publication support", zh: "发表支持" },
    b2D: { en: "Fee waivers and peer-review mentoring for the CYWater Journal.", zh: "CYWater 期刊免版面费及同行评审辅导。" },
    b3T: { en: "Directory & forums", zh: "名录与论坛" },
    b3D: { en: "Access to the member directory and topical discussion forums.", zh: "访问会员名录与专题讨论区。" },
    b4T: { en: "Grants & awards", zh: "资助与奖项" },
    b4D: { en: "Eligibility for travel grants and the early-career research award.", zh: "可申请差旅资助与青年研究奖。" },
    b5T: { en: "Voting rights", zh: "表决权" },
    b5D: { en: "A voice in Board elections and association decisions.", zh: "在理事会选举与协会事务中的表决权。" },
    b6T: { en: "Receipt & recognition", zh: "收据与认可" },
    b6D: { en: "Annual tax-deductible receipt and member verification.", zh: "年度可抵税收据与会员认证。" },

    tiersEyebrow: { en: "Dues", zh: "会费" },
    tiersTitle:   { en: "Choose your tier.", zh: "选择你的会员类型。" },
    tiersLead:    { en: "Dues are annual and help fund our programs. Payment methods include international cards, PayPal, Alipay and WeChat Pay.",
                    zh: "会费按年缴纳，用于支持协会项目。支持国际信用卡、PayPal、支付宝与微信支付。" },
    tierStudent:  { en: "Student", zh: "学生会员" },
    tierStudentTag: { en: "With valid student ID", zh: "需有效学生身份" },
    tierRegular:  { en: "Regular", zh: "普通会员" },
    tierRegularTag: { en: "Most popular", zh: "最受欢迎" },
    tierSustaining:{ en: "Sustaining", zh: "赞助会员" },
    tierSustainingTag: { en: "Support our mission", zh: "支持协会使命" },
    perYear:      { en: "/ year", zh: "/ 年" },
    tierStudentD: { en: "For enrolled students at any level.", zh: "面向各级在读学生。" },
    tierRegularD: { en: "For researchers and practitioners.", zh: "面向研究者与从业者。" },
    tierSustainingD: { en: "For those who can give more.", zh: "面向愿意多予支持者。" },

    faqEyebrow: { en: "FAQ", zh: "常见问题" },
    faqTitle:   { en: "Before you join.", zh: "加入之前。" },

    faq1Q: { en: "How do I pay? Can I use Alipay or WeChat Pay?", zh: "如何支付？可以用支付宝或微信支付吗？" },
    faq1A: { en: "Dues can be paid by international credit card, PayPal, Alipay or WeChat Pay. We use one-time annual payments rather than auto-renewal, which is friendlier to Chinese payment methods.",
              zh: "可使用国际信用卡、PayPal、支付宝或微信支付。我们采用一次性年度付款而非自动续费，对中国支付方式更友好。" },
    faq2Q: { en: "Is my membership tax-deductible?", zh: "会员费可以抵税吗？" },
    faq2A: { en: "CYWater is a registered U.S. nonprofit. Members receive an annual receipt that may be deductible depending on your jurisdiction; consult a tax advisor.",
              zh: "CYWater 是在美国注册的非营利组织。会员将收到年度收据，能否抵税视所在司法管辖区而定，请咨询税务顾问。" },
    faq3Q: { en: "Can I switch tiers later?", zh: "之后可以更换会员等级吗？" },
    faq3A: { en: "Yes. You can upgrade or downgrade at renewal. Mid-cycle upgrades are prorated.",
              zh: "可以。续费时可升级或降级。周期内升级会按比例计算。" },
    faq4Q: { en: "Do you offer institutional membership?", zh: "提供机构会员吗？" },
    faq4A: { en: "Institutional membership is on the roadmap for phase two. For now, individual membership covers most needs.",
              zh: "机构会员在第二阶段规划中。目前个人会员已能满足大部分需求。" },
  },

  /* ---------- member dashboard ---------- */
  dash: {
    hello:    { en: "Hello", zh: "你好" },
    overview: { en: "Overview", zh: "概览" },
    profile:  { en: "Profile", zh: "个人资料" },
    membership: { en: "Membership", zh: "会员资格" },
    events:   { en: "My events", zh: "我的活动" },
    receipts: { en: "Receipts", zh: "收据" },
    signOut:  { en: "Sign out", zh: "退出登录" },
    titleOverview: { en: "Your CYWater at a glance", zh: "你的 CYWater 概览" },
    memberSince:   { en: "Member since", zh: "加入时间" },
    expiresOn:     { en: "Expires", zh: "到期时间" },
    daysLeft:      { en: "days left", zh: "天后到期" },
    renew:         { en: "Renew membership", zh: "续费会员" },
    upcomingTitle: { en: "Upcoming for you", zh: "你的近期安排" },
    receiptsTitle: { en: "Recent receipts", zh: "近期收据" },
    statusActive:  { en: "Active", zh: "有效" },
    colDate:   { en: "Date", zh: "日期" },
    colDesc:   { en: "Description", zh: "项目" },
    colAmount: { en: "Amount", zh: "金额" },
    colStatus: { en: "Status", zh: "状态" },
    colFile:   { en: "Receipt", zh: "收据" },
    download:  { en: "Download", zh: "下载" },
    welcomeBack: { en: "Welcome back, Lin.", zh: "欢迎回来，林。" },
    cycleProgress: { en: "62% through cycle", zh: "周期已过 62%" },
    evAnnual: { en: "Annual Meeting 2026", zh: "2026 年年会" },
    evAnnualLoc: { en: "Boston, MA · Hybrid", zh: "美国波士顿 · 线上线下" },
    evWebinar: { en: "Webinar: Remote sensing of water quality", zh: "网络讲座：水质遥感" },
    recDues2026: { en: "Annual dues 2026 — Regular", zh: "2026 年度会费 —— 普通会员" },
    recReg: { en: "Annual Meeting 2026 registration", zh: "2026 年年会报名" },
    recDues2025: { en: "Annual dues 2025 — Regular", zh: "2025 年度会费 —— 普通会员" },
  },

  /* ---------- events ---------- */
  events: {
    heroEyebrow: { en: "Events", zh: "活动" },
    heroTitle:   { en: "Where the community meets.", zh: "共同体相聚之处。" },
    heroLead:    { en: "Webinars, workshops and our annual meeting — most are free and all are bilingual.",
                    zh: "网络讲座、工作坊与我们的年会——大多免费，且均为双语。" },
    filtersTitle: { en: "Filter", zh: "筛选" },
    fType: { en: "Type", zh: "类型" },
    fFormat: { en: "Format", zh: "形式" },
    fAll: { en: "All", zh: "全部" },
    fWebinar: { en: "Webinar", zh: "网络讲座" },
    fWorkshop: { en: "Workshop", zh: "工作坊" },
    fConference: { en: "Conference", zh: "会议" },
    fSocial: { en: "Social", zh: "社交" },
    upcoming: { en: "Upcoming", zh: "即将举行" },
    past:     { en: "Past events", zh: "往期活动" },
    seatsLeft: { en: "seats left", zh: "席位剩余" },
    memberRate: { en: "Member rate", zh: "会员价" },
    detailSchedule: { en: "Schedule", zh: "日程" },
    detailSpeakers: { en: "Speakers", zh: "演讲嘉宾" },
    detailAbout: { en: "About this event", zh: "活动介绍" },
    detailOrganizer: { en: "Organizer", zh: "主办方" },
    detailShare: { en: "Share", zh: "分享" },
    detailOtherEvents: { en: "Other events", zh: "其他活动" },
    detailMemberPrice: { en: "Members", zh: "会员" },
    detailNonMemberPrice: { en: "Non-members", zh: "非会员" },
    detailIncludes: { en: "Includes", zh: "包含" },

    /* 列表与详情内容 */
    e1Title: { en: "Annual Meeting 2026 — Water Across Boundaries", zh: "2026 年年会 —— 跨越边界的水" },
    e1City:  { en: "Boston, MA", zh: "美国波士顿" },
    e1Loc:   { en: "Boston, MA · Hybrid", zh: "美国波士顿 · 线上线下" },
    e1Dur:   { en: "3 days", zh: "为期 3 天" },
    e2Title: { en: "Remote sensing of water quality in lakes and reservoirs", zh: "湖泊与水库水质的遥感监测" },
    e2Time:  { en: "19:00 UTC+8", zh: "19:00（东八区）" },
    e3Title: { en: "Writing a competitive water-science grant proposal", zh: "撰写有竞争力的水科学基金申请书" },
    e3Price: { en: "Members: $0 / Non-members: $15", zh: "会员：$0 / 非会员：$15" },
    e4Title: { en: "Member mixer — Shanghai chapter", zh: "会员联谊 —— 上海分会" },
    e4Loc:   { en: "Shanghai", zh: "上海" },
    e5Title: { en: "Groundwater recharge under changing climate", zh: "气候变化下的地下水补给" },
    e5Meta:  { en: "Online · 312 attendees", zh: "线上 · 312 人参与" },

    /* 详情页 */
    detailBread: { en: "Annual Meeting 2026", zh: "2026 年年会" },
    detailDate:  { en: "Sep 18–20, 2026", zh: "2026 年 9 月 18–20 日" },
    detailExpected: { en: "~400 expected", zh: "预计约 400 人" },
    detailLead:  { en: "Our flagship gathering brings together the full CYWater community for three days of talks, working sessions and mentorship. Talks are delivered in English or Chinese with live interpretation; posters are bilingual by default.",
                   zh: "我们的旗舰盛会汇聚整个 CYWater 共同体，进行为期三天的演讲、研讨与师友活动。演讲以中英文进行并配有实时传译；海报默认双语。" },
    detailBody:  { en: "This year’s theme — Water Across Boundaries — explores how water science crosses disciplinary, geographic and generational lines. We welcome submissions across hydrology, water quality, climate, urban water and policy.",
                   zh: "今年的主题“跨越边界的水”探讨水科学如何跨越学科、地理与代际的边界。我们欢迎水文、水质、气候、城市水与政策等方向的投稿。" },
    day1: { en: "Day 1 · Sep 18", zh: "第一天 · 9 月 18 日" },
    day2: { en: "Day 2 · Sep 19", zh: "第二天 · 9 月 19 日" },
    day3: { en: "Day 3 · Sep 20", zh: "第三天 · 9 月 20 日" },
    s1T: { en: "Opening & keynote", zh: "开幕与主题演讲" },
    s1D: { en: "Dr. Lin Zhao — “Water Across Boundaries: the next decade”", zh: "林钊博士 ——“跨越边界的水：下一个十年”" },
    s2T: { en: "Session: Climate & the water cycle", zh: "分会：气候与水循环" },
    s2D: { en: "Five talks, bilingual Q&A.", zh: "五场报告，双语问答。" },
    s3T: { en: "Poster session & mentorship meet-up", zh: "海报展示与师友交流" },
    s3D: { en: "140 posters · structured mentor matching.", zh: "140 张海报 · 结构化师友匹配。" },
    s4T: { en: "Session: Water quality & health", zh: "分会：水质与健康" },
    s4D: { en: "Eight talks across contaminants and treatment.", zh: "八场报告，涵盖污染物与处理。" },
    s5T: { en: "Workshops (parallel)", zh: "工作坊（并行）" },
    s5D: { en: "Grant writing · Open data · Career pathways.", zh: "基金撰写 · 开放数据 · 职业发展。" },
    s6T: { en: "Session: Urban water & policy", zh: "分会：城市水与政策" },
    s6D: { en: "Six talks and a panel discussion.", zh: "六场报告与一场圆桌讨论。" },
    s7T: { en: "Awards & closing", zh: "颁奖典礼与闭幕" },
    s7D: { en: "Early-career award, mentorship showcases, AGM.", zh: "青年学者奖、师友成果展示、会员大会。" },
    inc1: { en: "All sessions, workshops & poster session", zh: "全部议程、工作坊与海报展示" },
    inc2: { en: "Bilingual interpretation", zh: "双语同传" },
    inc3: { en: "Networking & mentorship meet-up", zh: "社交与师友交流" },
  },

  /* ---------- registration modal ---------- */
  reg: {
    title: { en: "Register for event", zh: "活动报名" },
    step1Title: { en: "Your details", zh: "你的信息" },
    step2Title: { en: "Ticket", zh: "票种" },
    step3Title: { en: "Confirm", zh: "确认" },
    firstName: { en: "First name", zh: "名" },
    lastName: { en: "Last name", zh: "姓" },
    email: { en: "Email", zh: "邮箱" },
    org: { en: "Affiliation", zh: "所属机构" },
    ticket: { en: "Ticket type", zh: "票种" },
    ticketMember: { en: "Member (free)", zh: "会员（免费）" },
    ticketNonMember: { en: "Non-member", zh: "非会员" },
    ticketStudent: { en: "Student", zh: "学生" },
    reviewNote: { en: "Review your details. This is a prototype — no payment will be processed.",
                  zh: "请核对信息。本站为原型，不会发生真实支付。" },
    confirmation: { en: "You're registered! A confirmation has been sent to your email (prototype).",
                    zh: "报名成功！确认邮件已发送至你的邮箱（原型演示）。" },
    successTitle: { en: "You're registered!", zh: "报名成功！" },
    done: { en: "Done", zh: "完成" },
  },

  /* ---------- news ---------- */
  news: {
    heroEyebrow: { en: "News & stories", zh: "新闻与故事" },
    heroTitle:   { en: "What's happening at CYWater.", zh: "CYWater 的最新动态。" },
    heroLead:    { en: "Announcements, member stories and field reports from across the community.",
                    zh: "公告、会员故事与来自共同体的现场报道。" },
    popular: { en: "Most read", zh: "热门阅读" },
    categories: { en: "Categories", zh: "分类" },
    minRead: { en: "min read", zh: "分钟阅读" },
    by: { en: "By", zh: "作者：" },

    /* 新闻条目 */
    n1Title: { en: "Mapping seasonal groundwater depletion across the North China Plain", zh: "绘制华北平原季节性地下水亏损图" },
    n1Excerpt: { en: "New satellite-derived storage estimates reveal a sharper decline than previously reported.", zh: "最新的卫星储水量估算显示，地下水亏损比此前报告更为严峻。" },
    n2Title: { en: "Annual Meeting 2026 — registration now open", zh: "2026 年年会 —— 报名现已开放" },
    n2Excerpt: { en: "Three days of talks, workshops and mentorship, Sep 18–20 in Boston and online.", zh: "为期三天的演讲、工作坊与师友活动，9 月 18–20 日于波士顿及线上举行。" },
    n3Title: { en: "Meet the 2026 mentorship cohort", zh: "认识 2026 届师友计划学员" },
    n3Excerpt: { en: "Forty early-career members paired with senior researchers across nine countries.", zh: "四十位青年会员与九个国家的资深学者结对。" },
    n4Title: { en: "Early-career research award — applications open", zh: "青年学者研究奖 —— 现已开放申请" },
    n4Excerpt: { en: "Two awards of $2,000 for outstanding student-led water research.", zh: "两项 $2,000 奖金，表彰优秀的学生主导水研究。" },
    n5Title: { en: "Volume 3, Issue 2 is out", zh: "第 3 卷第 2 期已发布" },
    n5Excerpt: { en: "Twelve new bilingual papers spanning hydrology, quality and policy.", zh: "十二篇全新双语论文，涵盖水文、水质与政策。" },
    n6Title: { en: "Travel grants for the annual meeting", zh: "年会差旅资助" },
    n6Excerpt: { en: "Need-based travel support for students presenting at AM2026.", zh: "面向在 2026 年年会作报告的学生的按需差旅支持。" },

    /* 文章正文（article.html） */
    artLead: { en: "New satellite-derived storage estimates reveal a sharper decline than previously reported — and a path toward more sustainable management.", zh: "最新的卫星储水量估算显示，地下水亏损比此前报告更为严峻——同时也指明了更可持续的管理路径。" },
    artP1: { en: "The North China Plain is one of the most intensively cultivated and groundwater-dependent regions on Earth. For decades, GRACE and GRACE-FO satellites have tracked a steady decline in total water storage across the region. But aggregated trends mask sharp seasonal swings that matter for both farmers and policymakers.",
              zh: "华北平原是全球耕作最密集、最依赖地下水的地区之一。数十年来，GRACE 与 GRACE-FO 卫星持续追踪该地区总储水量的稳步下降。但汇总趋势掩盖了对农民与决策者都至关重要的剧烈季节性波动。" },
    artP2: { en: "In a new study published in the CYWater Journal, a team led by early-career researchers at Tsinghua University decomposes GRACE-derived terrestrial water storage into seasonal and long-term components at sub-basin resolution. Their finding: the depletion signal is roughly 18% stronger than earlier regional averages suggested once irrigation withdrawals are properly attributed.",
              zh: "在发表于 CYWater 期刊的一项新研究中，由清华大学青年学者领衔的团队将 GRACE 反演的陆地储水量在子流域尺度上分解为季节性与长期分量。他们发现：一旦正确归因灌溉取水，亏损信号比此前的区域平均值强约 18%。" },
    artH1: { en: "Why the seasonal lens matters", zh: "为何季节视角至关重要" },
    artP3: { en: "Annual storage trends flatten the spring drawdown — when irrigation demand peaks — into a single line. By isolating intra-annual cycles, the team shows that recovery during the summer monsoon no longer compensates for spring losses, a shift that began around 2014 and has accelerated since.",
              zh: "年度储水量趋势将春季灌溉高峰期的抽取压平成一条单线。通过分离年内周期，团队表明夏季季风期的回补已无法弥补春季亏损——这一转变约始于 2014 年，并在此后加速。" },
    artQuote: { en: "“We’ve been averaging away the signal that matters most,” said the lead author. “The spring deficit is where sustainability is won or lost.”",
                zh: "“我们一直把最重要的信号平均掉了，”第一作者说，“春季亏缺才是决定可持续性的关键。”" },
    artH2: { en: "A bilingual, open dataset", zh: "双语开放数据集" },
    artP4: { en: "In keeping with CYWater’s open-access mission, the team released both the seasonal decomposition code and a fully bilingual dataset of sub-basin storage anomalies. The dataset has already been picked up by provincial water resource bureaus testing early-warning indices.",
              zh: "秉承 CYWater 的开放获取使命，团队同时发布了季节性分解代码与一份完整的双语子流域储水量异常数据集。该数据集已被试用于早期预警指数的省级水资源部门采用。" },
    artH3: { en: "What’s next", zh: "下一步" },
    artP5: { en: "The team is now extending the method to the Hai River basin and exploring links between seasonal deficits and rural drinking-water reliability. A follow-up workshop at CYWater’s 2026 Annual Meeting will walk members through reproducing the analysis.",
              zh: "团队目前正将该方法的适用范围拓展至海河流域，并探索季节性亏缺与农村饮水可靠性之间的关联。CYWater 2026 年年会的一场后续工作坊将带领会员复现该分析。" },
    artAuthorAffil: { en: "Nanjing University · Hydrology", zh: "南京大学 · 水文学" },
  },

  /* ---------- journal ---------- */
  journal: {
    heroEyebrow: { en: "CYWater Journal", zh: "CYWater 期刊" },
    heroTitle:   { en: "Open, bilingual, peer-reviewed.", zh: "开放、双语、同行评审。" },
    heroLead:    { en: "The CYWater Journal publishes original research and review articles across the water sciences. All articles appear in both English and Chinese.",
                    zh: "CYWater 期刊发表水科学领域的原创研究与综述。所有文章均以中英双语呈现。" },
    latest: { en: "Current issue", zh: "当期文章" },
    archive: { en: "Archive", zh: "往期" },
    submit: { en: "Submit a paper", zh: "投稿" },
    submitLead: { en: "Submissions are open year-round. Members receive publication-fee waivers.",
                  zh: "全年开放投稿。会员免收发表费。" },
    volLabel: { en: "Volume", zh: "第" },
    issueLabel: { en: "Issue", zh: "卷" },
    articles: { en: "articles", zh: "篇文章" },

    currentIssue: { en: "Volume 3 · Issue 2 — Jun 2026", zh: "第 3 卷 · 第 2 期 — 2026 年 6 月" },
    j1Title: { en: "Seasonal groundwater depletion across the North China Plain", zh: "华北平原的季节性地下水亏损" },
    j1Excerpt: { en: "Sub-basin GRACE decomposition reveals an 18% stronger depletion signal than regional averages.", zh: "子流域 GRACE 分解显示，亏损信号比区域平均值强 18%。" },
    j2Title: { en: "Remote sensing of chlorophyll-a in inland waters: a decade of progress", zh: "内陆水体叶绿素 a 的遥感：十年的进展" },
    j2Excerpt: { en: "A synthesis of algorithm development, validation gaps and emerging satellite missions.", zh: "对算法发展、验证空白与新兴卫星任务的综合评述。" },
    j3Title: { en: "Sponge city performance under extreme rainfall in the Yangtze Delta", zh: "长三角极端降雨下海绵城市的表现" },
    j3Excerpt: { en: "A coupled model shows where green infrastructure holds up — and where it doesn’t.", zh: "耦合模型揭示了绿色基础设施何处有效、何处失效。" },
    j4Title: { en: "Water science needs bilingual publishing — here’s how", zh: "水科学需要双语出版——这里是如何做到的" },
    j4Excerpt: { en: "Why dual-language dissemination is infrastructure, not decoration.", zh: "为何双语传播是基础设施，而非装饰。" },
    j5Title: { en: "Drinking water reliability and rural spring deficits", zh: "饮水可靠性与农村春季亏缺" },
    j5Excerpt: { en: "Linking seasonal storage anomalies to household water access in northern villages.", zh: "将季节性储水量异常与北方村庄的家庭用水获取相联系。" },
    j6Title: { en: "An open bilingual dataset of sub-basin storage anomalies", zh: "开放的子流域储水量异常双语数据集" },
    j6Excerpt: { en: "Reproducible code and data for seasonal GRACE decomposition.", zh: "可复现的季节性 GRACE 分解代码与数据。" },
    archTheme1: { en: "Seasonal extremes", zh: "季节性极端" },
    archTheme2: { en: "Urban water", zh: "城市水" },
    archTheme3: { en: "Water & climate", zh: "水与气候" },
    archTheme4: { en: "Water quality", zh: "水质" },
    archInaugural: { en: "Inaugural issue", zh: "创刊号" },
  },

  /* ---------- contact ---------- */
  contact: {
    heroEyebrow: { en: "Contact", zh: "联系我们" },
    heroTitle:   { en: "Get in touch.", zh: "与我们联系。" },
    heroLead:    { en: "Questions about membership, events or partnerships? We usually reply within two business days.",
                    zh: "关于会员、活动或合作的问题？我们通常在两个工作日内回复。" },
    formTitle: { en: "Send a message", zh: "发送消息" },
    fName: { en: "Full name", zh: "姓名" },
    fEmail: { en: "Email", zh: "邮箱" },
    fSubject: { en: "Subject", zh: "主题" },
    fMembership: { en: "Membership", zh: "会员" },
    fEvents: { en: "Events", zh: "活动" },
    fPartnership: { en: "Partnership", zh: "合作" },
    fOther: { en: "Other", zh: "其他" },
    fMessage: { en: "Message", zh: "留言内容" },
    send: { en: "Send message", zh: "发送" },
    emailLabel: { en: "Email", zh: "邮箱" },
    addressLabel: { en: "Mailing address", zh: "通讯地址" },
    responseLabel: { en: "Response time", zh: "回复时间" },
    emailVal: { en: "info@cywater.org", zh: "info@cywater.org" },
    addressVal: { en: "CYWater, 1200 Waterway Blvd, Suite 200, Boston, MA 02115, USA", zh: "美国马萨诸塞州波士顿水道大道 1200 号 200 室 CYWater（邮编 02115）" },
    responseVal: { en: "Within 2 business days", zh: "2 个工作日内" },
  },
};

/* ---------- controller ---------- */
const LANG_KEY = "cywater-lang";
let currentLang = "en";

function getLang() {
  return currentLang;
}

function t(key) {
  const parts = key.split(".");
  let node = I18N;
  for (const p of parts) {
    node = node?.[p];
    if (!node) return key;
  }
  return node[currentLang] || node.en || key;
}

function applyTranslations(lang) {
  currentLang = lang;
  document.documentElement.lang = lang === "zh" ? "zh-CN" : "en";

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

  // active nav lang toggle label
  document.querySelectorAll(".lang-toggle .lang-current").forEach((el) => {
    el.textContent = lang === "zh" ? "中文" : "EN";
  });

  // persist
  try { localStorage.setItem(LANG_KEY, lang); } catch (e) {}

  // notify any listeners
  document.dispatchEvent(new CustomEvent("languagechange", { detail: { lang } }));
}

function toggleLang() {
  applyTranslations(currentLang === "en" ? "zh" : "en");
}

function initLang() {
  let saved = null;
  try { saved = localStorage.getItem(LANG_KEY); } catch (e) {}
  // default to browser language
  const browser = (navigator.language || "en").toLowerCase();
  const initial = saved || (browser.startsWith("zh") ? "zh" : "en");
  applyTranslations(initial);
}

window.CYWaterI18N = { t, getLang, applyTranslations, toggleLang, initLang };
document.addEventListener("DOMContentLoaded", initLang);
