/* ==========================================================================
   CYWater · i18n dictionary + controller
   Bilingual (en / zh). All UI strings live here, referenced by data-i18n.
   ========================================================================== */

const I18N = {
  /* ---------- meta ---------- */
  meta: {
    siteName: { en: "CYWater", zh: "CYWater" },
    siteFull: { en: "Int'l Association of Contemporary Young Scholars in Water Sciences", zh: "国际水科学青年学者协会" },
    siteFullOrig: { en: "Founded 2011 as the Int'l Association of Chinese Youth in Water Sciences",
                    zh: "2011 年以「国际华人青年水科学协会」之名成立" },
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
    tagline:    { en: "A non-profit, international member-based association advancing water sciences education, research, and professional development through scientific exchange, publications, and conferences. Founded in 2011.",
                  zh: "一个非营利、国际性会员制协会，通过学术交流、出版与会议，推动水科学的教育、研究与职业发展。成立于 2011 年。" },
    rights:     { en: "© 2011–2026 CYWater. All rights reserved.", zh: "© 2011–2026 CYWater. 保留所有权利。" },
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
    registered:  { en: "[Mock] Registration received — no real sign-up is processed.", zh: "【模拟】报名已收到 —— 不会发生真实报名。" },
    joined:      { en: "[Mock] Welcome aboard! — no real membership is created.", zh: "【模拟】欢迎加入！—— 不会建立真实会员关系。" },
    saved:       { en: "[Mock] Saved — no real data is stored.", zh: "【模拟】已保存 —— 不会存储真实数据。" },
    member:      { en: "Member",  zh: "会员" },
    nonmember:   { en: "Non-member", zh: "非会员" },
    free:        { en: "Free", zh: "免费" },
    online:      { en: "Online", zh: "线上" },
    hybrid:      { en: "Hybrid", zh: "线上线下" },
    inperson:    { en: "In person", zh: "线下" },
    prototypeNote: { en: "About this site", zh: "关于本站" },
    protoMsg:    { en: "This site presents CYWater's real activities, history and awards. Forms for membership, registration and account are functional prototypes [Mock] — they do not process real payments or create real accounts.",
                   zh: "本站展示 CYWater 的真实活动、历史与奖项。会员、报名与账户相关表单为功能原型【模拟】—— 不会发生真实支付，也不会建立真实账户。" },
    protoToast:  { en: "[Mock] Prototype — no file attached.", zh: "【模拟】原型 —— 无附件。" },
    protoSubmit: { en: "[Mock] Prototype — no submission portal yet.", zh: "【模拟】原型 —— 暂无投稿入口。" },
    mockBadge:   { en: "Mock", zh: "模拟" },

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
    heroCta1:    { en: "Upcoming events", zh: "近期活动" },
    heroCta2:    { en: "About CYWater", zh: "关于 CYWater" },

    missionEyebrow: { en: "Our mission", zh: "我们的使命" },
    missionTitle:   { en: "Connecting young water scholars across the world.",
                      zh: "连结全球青年水科学人。" },
    missionLead:    { en: "Since 2011, CYWater has connected early-career Chinese water researchers at home and abroad — advancing water sciences through exchange, conferences, and the annual Young Scientist Best Paper Award.",
                      zh: "自 2011 年起，CYWater 连结海内外华人青年水研究者，通过学术交流、年度会议与青年学者最佳论文奖，推动水科学发展。" },
    statMembers:    { en: "Years of community", zh: "年学术共同体" },
    statCountries:  { en: "Countries & regions", zh: "国家与地区" },
    statEvents:     { en: "Summer Meetings", zh: "届夏季会议" },
    statPapers:     { en: "Years of Best Paper Award", zh: "年最佳论文奖" },

    pillarsEyebrow: { en: "What we do", zh: "我们做什么" },
    pillarsTitle:   { en: "Exchange, conferences, and recognition.", zh: "交流、会议与表彰。" },
    pillar1T: { en: "Annual Summer Meeting", zh: "年度夏季会议" },
    pillar1D: { en: "Our flagship gathering since 2013 — 13 editions connecting young water scholars across six continents.", zh: "自 2013 年起的旗舰盛会——13 届连结六大洲的青年水科学人。" },
    pillar2T: { en: "AGU Winter Gathering", zh: "AGU 冬季聚会" },
    pillar2D: { en: "An annual gathering during the AGU Fall Meeting, where CYWater first began in 2011.", zh: "每年 AGU 秋季年会期间的聚会——CYWater 2011 年即诞生于此。" },
    pillar3T: { en: "Best Paper Award", zh: "最佳论文奖" },
    pillar3D: { en: "The Young Scientist Best Paper Award, recognising outstanding contributions to water sciences since 2012.", zh: "青年学者最佳论文奖，自 2012 年起表彰水科学领域的杰出贡献。" },
    pillar4T: { en: "Bilingual Community", zh: "双语共同体" },
    pillar4D: { en: "A Chinese-English community bridging scholars at home and abroad across career stages.", zh: "一个中英双语共同体，连结海内外、各职业阶段的学者。" },

    focusEyebrow: { en: "Our themes", zh: "会议主题" },
    focusTitle:   { en: "Frontiers of hydrological sciences.", zh: "水文科学前沿。" },
    focus1:  { en: "Hydroclimate & Global Change", zh: "水文气候与全球变化" },
    focus2:  { en: "Hydrological Hazards", zh: "水文灾害与预警" },
    focus3:  { en: "Ecohydrology & Geomorphology", zh: "生态水文与流水地貌" },
    focus4:  { en: "Observation & Modelling", zh: "水文观测与模拟" },
    focus5:  { en: "Surface & Groundwater", zh: "地表与地下水资源" },
    focus6:  { en: "Water Quality", zh: "水质" },
    focus7:  { en: "Remote Sensing", zh: "遥感" },
    focus8:  { en: "AI for Water Science", zh: "AI 赋能水科学" },
    focus9:  { en: "Climate Adaptation", zh: "气候适应" },
    focus10: { en: "Water Security", zh: "水安全" },

    statFounded:    { en: "Founded in San Francisco", zh: "于旧金山成立" },
    statFirstSummer:{ en: "First Summer Meeting", zh: "首届夏季会议" },
    statLanguages:  { en: "Languages, always (CN / EN)", zh: "中英双语为本" },
    statPeakAttend: { en: "Peak online attendees (2022)", zh: "线上参会峰值（2022）" },

    latestEyebrow: { en: "Latest", zh: "最新动态" },
    latestTitle:   { en: "Conferences, awards & announcements.", zh: "会议、奖项与公告。" },

    ctaEyebrow: { en: "Get involved", zh: "参与进来" },
    ctaTitle: { en: "Join the CYWater community.", zh: "加入 CYWater 共同体。" },
    ctaLead:  { en: "For fifteen years, CYWater has connected young water scholars through bilingual exchange, annual meetings and the Best Paper Award.",
                zh: "十五年来，CYWater 通过双语交流、年度会议与最佳论文奖，连结青年水科学人。" },
    ctaBtn1: { en: "See our events", zh: "查看我们的活动" },
    ctaBtn2: { en: "Read our story", zh: "了解我们的故事" },
  },

  /* ---------- about ---------- */
  about: {
    heroEyebrow: { en: "About CYWater", zh: "关于 CYWater" },
    heroTitle:   { en: "An international community of young water scholars, since 2011.", zh: "自 2011 年起的青年水科学人国际共同体。" },
    heroLead:    { en: "Founded in December 2011 in San Francisco, CYWater is a non-profit, international association connecting young Chinese water scholars at home and abroad through scientific exchange, annual meetings, and the Young Scientist Best Paper Award.",
                    zh: "CYWater 于 2011 年 12 月在旧金山成立，是一个非营利国际性协会，通过学术交流、年度会议与青年学者最佳论文奖，连结海内外华人青年水科学人。" },

    storyTitle:  { en: "Who we are", zh: "我们是谁" },
    storyP1: { en: "CYWater was initiated in December 2011 in San Francisco by a group of Chinese professionals in water-related sciences, during the AGU Fall Meeting. Its mission: to promote cooperation in water sciences between Chinese professionals abroad and those in China, and to advance water-science research and education worldwide.",
               zh: "CYWater 于 2011 年 12 月在旧金山 AGU 秋季年会期间，由一批海外华人水科学青年学者发起成立。其使命是：促进海外华人与国内水科学学者的合作，推动全球水科学研究与教育。" },
    storyP2: { en: "Since then, CYWater has held 13 Summer Meetings across China (2013–2025), an annual winter gathering during the AGU Fall Meeting, and the Young Scientist Best Paper Award every year since 2012. The community now spans more than 30 countries and regions across six continents.",
               zh: "此后，CYWater 在国内举办了 13 届夏季会议（2013–2025），每年 AGU 秋季年会期间举办冬季聚会，并自 2012 年起每年评选青年学者最佳论文奖。学术共同体如今遍及六大洲 30 余个国家与地区。" },

    valuesTitle: { en: "What we value", zh: "我们的价值观" },
    v1T: { en: "Young scholars first", zh: "青年学者为先" },
    v1D: { en: "Early-career researchers are the heart of the community — not just an audience we serve.", zh: "青年研究者是这个共同体的核心——而非仅仅是被服务的受众。" },
    v2T: { en: "Truly international", zh: "真正的国际化" },
    v2D: { en: "A network spanning six continents, with bilingual exchange as a founding principle.", zh: "一个跨越六大洲的网络，双语交流是立会之本。" },
    v3T: { en: "Scientific exchange", zh: "学术交流" },
    v3D: { en: "We advance water sciences through exchange, conferences, and recognition.", zh: "我们通过交流、会议与表彰推动水科学进步。" },
    v4T: { en: "Non-profit & community-driven", zh: "非营利与共建共享" },
    v4D: { en: "Run by and for the community of young water scholars, since 2011.", zh: "自 2011 年起，由青年水科学人共建、为共同体服务。" },

    timelineTitle: { en: "Milestones", zh: "重要节点" },
    t1: { en: "Association initiated in San Francisco during the AGU Fall Meeting", zh: "于旧金山 AGU 秋季年会期间发起成立" },
    t2: { en: "First Summer Meeting, Beijing", zh: "首届夏季会议，北京" },
    t3: { en: "8th Summer Meeting went fully online — 630 peak attendees across six continents", zh: "第八届夏季会议全程线上——六大洲、峰值 630 人参会" },
    t4: { en: "12th Summer Meeting, Xi'an — added AI for Water Science as a new theme", zh: "第十二届夏季会议，西安——新增「AI 赋能水科学」主题" },
    t5: { en: "13th Annual Meeting, co-hosted with the Yangtze Technology & Economy Society", zh: "第十三届年会，与长江技术经济学会联合举办" },

    governanceTitle: { en: "Governance", zh: "治理结构" },
    governanceP: { en: "CYWater is led by co-chairs and an executive office, supported by a Young Scientist Best Paper Award committee. The current co-chairs are Prof. Dong Chen and Prof. Qiuhong Tang (IGSNRR, CAS), and Prof. Yang Hong (University of Oklahoma).",
                   zh: "CYWater 由共同主席与执行机构领导，并设青年学者最佳论文奖评审委员会。现任共同主席为陈东研究员、汤秋鸿研究员（中科院地理所）与洪阳教授（俄克拉荷马大学）。" },
  },

  /* ---------- board ---------- */
  board: {
    heroEyebrow: { en: "Leadership", zh: "领导团队" },
    heroTitle:   { en: "The people leading CYWater.", zh: "带领 CYWater 的人。" },
    heroLead:    { en: "CYWater is led by co-chairs and general secretaries, with guidance from honorary members and the Best Paper Award committee. Names below are drawn from the association's published records.",
                    zh: "CYWater 由共同主席与秘书长领导，并由荣誉会员及最佳论文奖评审委员会提供指导。以下名单依据协会公开记录整理。" },
    execTitle:   { en: "Co-Chairs", zh: "共同主席" },
    boardTitle:  { en: "General Secretaries", zh: "秘书长" },
    advisorsTitle: { en: "Honorary Members", zh: "荣誉会员" },
    roleChair: { en: "Co-Chair", zh: "共同主席" },
    roleSec:   { en: "General Secretary", zh: "秘书长" },
    nameDC: { en: "Dong Chen", zh: "陈东" },
    affilDC:{ en: "IGSNRR, Chinese Academy of Sciences", zh: "中科院地理科学与资源研究所" },
    nameYH: { en: "Yang Hong", zh: "洪阳" },
    affilYH:{ en: "University of Oklahoma", zh: "俄克拉荷马大学" },
    nameQT: { en: "Qiuhong Tang", zh: "汤秋鸿" },
    affilQT:{ en: "Tsinghua University", zh: "清华大学" },
    nameXL: { en: "Xingcai Liu", zh: "刘星才" },
    affilXL:{ en: "IGSNRR, Chinese Academy of Sciences", zh: "中科院地理科学与资源研究所" },
    nameYZ: { en: "Yu Zhang", zh: "张宇" },
    affilYZ:{ en: "University of Oklahoma", zh: "俄克拉荷马大学" },
    nameQD: { en: "Qingyun Duan", zh: "段青云" },
    affilQD:{ en: "Hohai University", zh: "河海大学" },
    nameZX: { en: "Zhenghui Xie", zh: "谢正辉" },
    affilZX:{ en: "Institute of Atmospheric Physics, CAS", zh: "中科院大气物理研究所" },
    nameJY: { en: "Jingjie Yu", zh: "于静洁" },
    affilJY:{ en: "IGSNRR, Chinese Academy of Sciences", zh: "中科院地理科学与资源研究所" },
  },

  /* ---------- bylaws ---------- */
  bylaws: {
    heroEyebrow: { en: "Governance documents [Mock]", zh: "治理文件【模拟】" },
    heroTitle:   { en: "Bylaws (draft, illustrative)", zh: "章程（草案，示意）" },
    heroLead:    { en: "The text below is a [Mock] illustrative draft — it does not constitute CYWater's official bylaws. The authoritative bylaws will be published once ratified; until then please contact the secretariat for governance questions.",
                    zh: "以下文本为【模拟】示意草案——并非 CYWater 的正式章程。正式章程经批准后将另行公布；在此之前，治理相关问题请联系秘书处。" },
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
    heroTitle:   { en: "How to join the CYWater community.", zh: "如何加入 CYWater 共同体。" },
    heroLead:    { en: "CYWater is a volunteer-run community of young water scholars. To join, contact the secretariat by email — there is currently no online sign-up. The tiers and payment flows shown below are a [Mock] prototype and do not process real payments.",
                    zh: "CYWater 是由青年水科学人志愿运营的共同体。如需加入，请通过邮件联系秘书处——目前暂无在线入会入口。下方展示的会员等级与支付流程为【模拟】原型，不会发生真实支付。" },

    benefitsEyebrow: { en: "Benefits", zh: "会员权益" },
    benefitsTitle:   { en: "What membership offers.", zh: "会员可享有。" },
    b1T: { en: "Annual meetings", zh: "年度会议" },
    b1D: { en: "Priority notice and access to the Summer Meeting and AGU winter gathering.", zh: "夏季会议与 AGU 冬季聚会的优先通知与参与。" },
    b2T: { en: "Best Paper Award", zh: "最佳论文奖" },
    b2D: { en: "Eligibility for the Young Scientist Best Paper Award (under-35 first authors).", zh: "符合条件者可参评青年学者最佳论文奖（35 岁以下第一作者）。" },
    b3T: { en: "Bilingual community", zh: "双语共同体" },
    b3D: { en: "A Chinese-English network connecting scholars at home and abroad.", zh: "一个连结海内外学者的中英双语网络。" },
    b4T: { en: "Newsletter archive", zh: "简报存档" },
    b4D: { en: "Access to CYWater's archive of community newsletters since 2012.", zh: "访问 CYWater 自 2012 年起的共同体简报存档。" },
    b5T: { en: "Career opportunities", zh: "职业机会" },
    b5D: { en: "Job and position notices shared through the community channel.", zh: "通过共同体渠道分享的招聘与职位信息。" },
    b6T: { en: "Recognition", zh: "认可" },
    b6D: { en: "Member recognition in community activities and award ceremonies.", zh: "在共同体活动与颁奖典礼中的会员认可。" },

    tiersEyebrow: { en: "Dues [Mock]", zh: "会费【模拟】" },
    tiersTitle:   { en: "Tiers shown are illustrative only.", zh: "所示等级仅为示意。" },
    tiersLead:    { en: "The dues, tiers and online payment flow below are a functional prototype [Mock] — they do not reflect real fees and process no real payment. To actually join, email the secretariat.",
                    zh: "下方的会费、等级与在线支付流程为功能原型【模拟】——并非真实费用，也不会发生真实支付。如需真实入会，请邮件联系秘书处。" },
    tierStudent:  { en: "Student [Mock]", zh: "学生会员【模拟】" },
    tierStudentTag: { en: "With valid student ID", zh: "需有效学生身份" },
    tierRegular:  { en: "Regular [Mock]", zh: "普通会员【模拟】" },
    tierRegularTag: { en: "Most popular", zh: "最受欢迎" },
    tierSustaining:{ en: "Sustaining [Mock]", zh: "赞助会员【模拟】" },
    tierSustainingTag: { en: "Support our mission", zh: "支持协会使命" },
    perYear:      { en: "/ year [Mock]", zh: "/ 年【模拟】" },
    tierStudentD: { en: "For enrolled students at any level.", zh: "面向各级在读学生。" },
    tierRegularD: { en: "For researchers and practitioners.", zh: "面向研究者与从业者。" },
    tierSustainingD: { en: "For those who can give more.", zh: "面向愿意多予支持者。" },

    faqEyebrow: { en: "How to actually join", zh: "真实入会方式" },
    faqTitle:   { en: "Contact the secretariat.", zh: "联系秘书处。" },
    faq1Q: { en: "How do I join CYWater?", zh: "如何加入 CYWater？" },
    faq1A: { en: "Email the CYWater secretariat to express your interest. There is currently no online sign-up form; membership is handled by the volunteer office.",
             zh: "请发送邮件至 CYWater 秘书处表达意向。目前暂无在线入会表单；会员事务由志愿秘书处处理。" },
    faq2Q: { en: "Who can join?", zh: "谁可以加入？" },
    faq2A: { en: "CYWater is open to young scholars, researchers and students in water-related sciences, at home and abroad.",
             zh: "CYWater 面向海内外水科学及相关领域的青年学者、研究者与学生开放。" },
    faq3Q: { en: "Is there a membership fee?", zh: "需要缴纳会费吗？" },
    faq3A: { en: "CYWater has historically been a free, volunteer-run community. Any future dues structure will be announced through the official WeChat channel.",
             zh: "CYWater 历史上是免费、志愿运营的共同体。任何未来的会费体系将通过官方微信公众号公布。" },
    faq4Q: { en: "Where is CYWater based?", zh: "CYWater 设在哪里？" },
    faq4A: { en: "CYWater was long hosted by the Institute of Geographic Sciences and Natural Resources Research (IGSNRR), CAS. Please contact the secretariat for the current affiliation.",
             zh: "CYWater 长期挂靠中国科学院地理科学与资源研究所。当前挂靠单位请向秘书处确认。" },
  },

  /* ---------- member dashboard ---------- */
  dash: {
    mockBanner: { en: "[Mock] This dashboard is a non-functional prototype. CYWater has no online account system — there is no real login or member data.",
                  zh: "【模拟】本会员中心为非功能性原型。CYWater 暂无在线账户系统——不存在真实登录或会员数据。" },
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
    heroTitle:   { en: "Thirteen Summer Meetings, and counting.", zh: "十三届夏季会议，仍在继续。" },
    heroLead:    { en: "Our flagship Summer Meeting has run every year since 2013, hosted by universities across China, with an annual winter gathering during the AGU Fall Meeting. Records below link to the original announcements.",
                    zh: "我们的旗舰夏季会议自 2013 年起每年举办，由国内多所高校承办，每年 AGU 秋季年会期间另有冬季聚会。下方记录可跳转至原始通知。" },
    filtersTitle: { en: "Filter", zh: "筛选" },
    fType: { en: "Type", zh: "类型" },
    fFormat: { en: "Format", zh: "形式" },
    fAll: { en: "All", zh: "全部" },
    fWebinar: { en: "Webinar", zh: "网络讲座" },
    fWorkshop: { en: "Workshop", zh: "工作坊" },
    fConference: { en: "Conference", zh: "会议" },
    fSocial: { en: "Social", zh: "社交" },
    upcoming: { en: "Recent & upcoming", zh: "近期活动" },
    past:     { en: "Earlier Summer Meetings", zh: "更早的夏季会议" },
    seatsLeft: { en: "seats left", zh: "席位剩余" },
    memberRate: { en: "Member rate", zh: "会员价" },
    tagJoint: { en: "Co-hosted", zh: "联合举办" },
    tagAnnual: { en: "Annual", zh: "年度评选" },
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
    e1Title: { en: "13th Annual Meeting (1st round notice)", zh: "第十三届年会（第一轮通知）" },
    e1City:  { en: "Co-hosted with the Yangtze Technology & Economy Society", zh: "与长江技术经济学会联合举办" },
    e1Loc:   { en: "China", zh: "中国" },
    e1Dur:   { en: "2025", zh: "2025 年" },
    e2Title: { en: "Young Scientist Best Paper Award 2025 — Result", zh: "2025 青年学者最佳论文奖 —— 评选结果" },
    e2Time:  { en: "Announced Dec 2025", zh: "2025 年 12 月公布" },
    e3Title: { en: "12th Summer Meeting — Xi'an University of Technology", zh: "第十二届夏季会议 —— 西安理工大学" },
    e3Loc:   { en: "Xi'an, China", zh: "中国西安" },
    e3Meta:  { en: "Aug 2024", zh: "2024 年 8 月" },
    e4Title: { en: "10th Summer Meeting — Online", zh: "第十届夏季会议 —— 线上" },
    e4Loc:   { en: "Online · 6 continents, 30+ countries", zh: "线上 · 六大洲 30+ 国" },
    e5Title: { en: "8th Summer Meeting — Online", zh: "第八届夏季会议 —— 线上" },
    e5Meta:  { en: "Aug 2020 · 630 peak attendees · 106 institutions", zh: "2020 年 8 月 · 峰值 630 人 · 106 家机构" },

    /* 详情页 — 内容为 mock 占位，标题沿用列表第一条 */
    detailMockBanner: { en: "[Mock] The detailed schedule below is a prototype layout. For the real 13th Annual Meeting programme, see the official WeChat announcement.",
                        zh: "【模拟】下方详细日程为原型排版示意。第十三届年会的真实议程，请见官方微信公众号通知。" },
    detailBread: { en: "13th Annual Meeting", zh: "第十三届年会" },
    detailDate:  { en: "2025 (see WeChat notice)", zh: "2025 年（见微信通知）" },
    detailExpected: { en: "see official notice", zh: "见官方通知" },
    detailLead:  { en: "The 13th CYWater Annual Meeting is co-hosted with the Youth Committee of the Yangtze Technology & Economy Society. The full programme, venue and registration details are published through the official WeChat channel.",
                   zh: "第十三届 CYWater 年会与长江技术经济学会青年工作委员会联合举办。完整议程、地点与报名详情通过官方微信公众号公布。" },
    detailBody:  { en: "[Mock] The day-by-day schedule and speakers shown below are illustrative placeholders, carried over from the prototype layout. They do not represent the real programme.",
                   zh: "【模拟】下方所示的逐日日程与演讲嘉宾为示意占位，沿用原型排版，并非真实议程。" },
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
    reviewNote: { en: "[Mock] Review your details. This is a prototype — no real registration or payment is processed.",
                  zh: "【模拟】请核对信息。本站为原型，不会发生真实报名或支付。" },
    confirmation: { en: "[Mock] You're registered! No real confirmation is sent — this is a prototype.",
                    zh: "【模拟】报名成功！不会发送真实确认——本站为原型。" },
    successTitle: { en: "You're registered!", zh: "报名成功！" },
    done: { en: "Done", zh: "完成" },
  },

  /* ---------- news ---------- */
  news: {
    heroEyebrow: { en: "News & announcements", zh: "新闻与公告" },
    heroTitle:   { en: "What's happening at CYWater.", zh: "CYWater 的最新动态。" },
    heroLead:    { en: "Conference announcements, award results and community news from the official CYWater WeChat channel.",
                    zh: "来自 CYWater 官方微信公众号的会议通知、奖项结果与共同体动态。" },
    popular: { en: "Most read", zh: "热门阅读" },
    categories: { en: "Categories", zh: "分类" },
    minRead: { en: "min read", zh: "分钟阅读" },
    by: { en: "Source", zh: "来源：" },

    /* 新闻条目 */
    n1Title: { en: "13th Annual Meeting — 3rd round notice", zh: "第十三届年会 —— 第三轮通知" },
    n1Excerpt: { en: "Co-hosted with the Yangtze Technology & Economy Society Youth Committee. Full programme and venue details released.", zh: "与长江技术经济学会青年工作委员会联合举办。完整议程与会议地点已公布。" },
    n2Title: { en: "Young Scientist Best Paper Award 2025 — Result & Ceremony", zh: "2025 青年学者最佳论文奖 —— 结果与颁奖" },
    n2Excerpt: { en: "The 2025 Best Paper Award result has been announced, with the ceremony held during the AGU Fall Meeting.", zh: "2025 年最佳论文奖结果已公布，颁奖典礼于 AGU 秋季年会期间举行。" },
    n3Title: { en: "12th Summer Meeting — Xi'an, Aug 2024", zh: "第十二届夏季会议 —— 2024 年 8 月，西安" },
    n3Excerpt: { en: "Hosted by Xi'an University of Technology. Theme: \"Gathering strength for new quality productive forces in water.\"", zh: "由西安理工大学承办。主题：\"凝新聚力，推动水利新质生产力发展\"。" },
    n4Title: { en: "Young Scientist Best Paper Award 2025 — call open", zh: "2025 青年学者最佳论文奖 —— 开放申请" },
    n4Excerpt: { en: "Applications open for water-science papers by first authors under 35. One Best Paper Award and up to two Outstanding Paper Awards.", zh: "面向 35 岁以下第一作者的水科学论文开放申请。设最佳论文奖一项、杰出论文奖最多两项。" },
    n5Title: { en: "10th Summer Meeting — Online, Aug 2022", zh: "第十届夏季会议 —— 2022 年 8 月，线上" },
    n5Excerpt: { en: "Six continents, 30+ countries, 630 peak attendees. Theme: Water interaction with the Earth system.", zh: "六大洲、30+ 国、峰值 630 人。主题：水与地球系统的相互作用。" },
    n6Title: { en: "8th Summer Meeting — Online, Aug 2020", zh: "第八届夏季会议 —— 2020 年 8 月，线上" },
    n6Excerpt: { en: "106 institutions, 76 talks. Invited speakers: Aiguo Dai, Kevin Trenberth, Kun Yang, Ge Sun.", zh: "106 家机构，76 场报告。邀请报告人：戴爱国、Kevin Trenberth、阳坤、孙阁。" },

    /* 文章正文（article.html） — 占位说明 */
    artLead: { en: "This article page is a [Mock] placeholder. Full text is available on the official CYWater WeChat channel — follow the link below to read the original announcement.",
               zh: "本文章页为【模拟】占位。完整正文请见 CYWater 官方微信公众号——点击下方链接阅读原始通知。" },
    artP1: { en: "CYWater publishes its announcements through its official WeChat channel (公众号: CYWater). The full text of this announcement, including all details and attachments, is preserved there.",
             zh: "CYWater 通过其官方微信公众号（公众号：CYWater）发布通知。本通知的完整正文，包括全部细节与附件，均保留在该渠道。" },
    artMockBadge: { en: "[Mock] Article body not migrated", zh: "【模拟】文章正文未迁移" },
    artReadOriginal: { en: "Read the original on WeChat", zh: "在微信阅读原文" },
    artAuthorAffil: { en: "CYWater official channel", zh: "CYWater 官方渠道" },
  },

  /* ---------- journal ---------- */
  journal: {
    heroEyebrow: { en: "Journal", zh: "期刊" },
    heroTitle:   { en: "Coming soon.", zh: "敬请期待。" },
    heroLead:    { en: "CYWater does not yet publish a journal. This page is reserved for future publication plans.",
                    zh: "CYWater 目前尚未出版期刊。本页面为未来出版计划预留。" },
    comingBadge: { en: "Coming soon", zh: "敬请期待" },
    comingTitle: { en: "A journal is on our roadmap.", zh: "期刊已在规划之中。" },
    comingLead:  { en: "For fifteen years, CYWater has shared water-science research through conferences, the Best Paper Award and the WeChat channel. A dedicated open-access publication is part of our next stage.",
                   zh: "十五年来，CYWater 通过会议、最佳论文奖与微信公众号分享水科学研究。专门的开放获取出版物将是我们下一阶段的一部分。" },
    comingLead2: { en: "Until then, please follow our latest research news through the News page and the official WeChat channel.",
                   zh: "在此之前，请通过「新闻」页面与官方微信公众号关注我们的最新研究动态。" },
    comingCta:   { en: "Read recent news", zh: "查看近期新闻" },
    /* 以下键保留以兼容现有引用，但不再渲染 */
    latest: { en: "Current issue", zh: "当期文章" },
    archive: { en: "Archive", zh: "往期" },
    submit: { en: "Submit a paper", zh: "投稿" },
    submitLead: { en: "Submissions are open year-round. Members receive publication-fee waivers.",
                  zh: "全年开放投稿。会员免收发表费。" },
    volLabel: { en: "Volume", zh: "第" },
    issueLabel: { en: "Issue", zh: "卷" },
    articles: { en: "articles", zh: "篇文章" },
    currentIssue: { en: "—", zh: "—" },
    archTheme1: { en: "—", zh: "—" },
    archInaugural: { en: "—", zh: "—" },
  },

  /* ---------- contact ---------- */
  contact: {
    heroEyebrow: { en: "Contact", zh: "联系我们" },
    heroTitle:   { en: "Get in touch.", zh: "与我们联系。" },
    heroLead:    { en: "CYWater is a volunteer-run community. For the fastest response, follow the official WeChat channel (公众号: CYWater). The form below is a [Mock] prototype and sends no real email.",
                    zh: "CYWater 是由志愿者运营的共同体。如需最快回复，请关注官方微信公众号（公众号：CYWater）。下方表单为【模拟】原型，不会发送真实邮件。" },
    formTitle: { en: "Send a message [Mock]", zh: "发送消息【模拟】" },
    fName: { en: "Full name", zh: "姓名" },
    fEmail: { en: "Email", zh: "邮箱" },
    fSubject: { en: "Subject", zh: "主题" },
    fMembership: { en: "Membership", zh: "会员" },
    fEvents: { en: "Events", zh: "活动" },
    fPartnership: { en: "Partnership", zh: "合作" },
    fOther: { en: "Other", zh: "其他" },
    fMessage: { en: "Message", zh: "留言内容" },
    send: { en: "Send message [Mock]", zh: "发送【模拟】" },
    emailLabel: { en: "Email", zh: "邮箱" },
    addressLabel: { en: "Mailing address", zh: "通讯地址" },
    responseLabel: { en: "Response time", zh: "回复时间" },
    emailVal: { en: "via official WeChat (公众号: CYWater)", zh: "经官方微信公众号（公众号：CYWater）" },
    addressVal: { en: "To be confirmed — contact the secretariat", zh: "待确认 —— 请联系秘书处" },
    responseVal: { en: "Best-effort, volunteer-run", zh: "尽力回复，志愿运营" },
    wechatHint: { en: "WeChat is the fastest channel", zh: "微信是最快渠道" },
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
