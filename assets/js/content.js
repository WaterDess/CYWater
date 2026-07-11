/* ==========================================================================
   CYWater · content.js
   Structured data for news articles and event detail pages.
   Each entry is rendered by article.html / detail.html based on ?id= param.
   All content drawn from the official CYWater WeChat channel and the
   association's public records — no fabricated facts.
   ==========================================================================

   Block types (used by both ARTICLES and EVENTS):
     { type: "p",      text: "..." }                paragraph
     { type: "h2",     text: "..." }                section heading
     { type: "h3",     text: "..." }                sub heading
     { type: "quote",  text: "..." }                pull quote (teal border)
     { type: "list",   items: ["...", "..."] }      bullet list
     { type: "figure", src: "<path>", caption: "..." }                image + caption
     { type: "gallery", items: [{src, caption}, ...] }          multi-image grid
   ========================================================================== */

const ARTICLES = {
  /* ---- 2025 Best Paper Award Result (news n2) ---- */
  "bpa-2025-result": {
    title: "CYWater Young Scientist Best Paper Award 2025 — Result",
    date: "2025-12-14",
    tag: "Award",
    lead: "Jiaojiao Gou receives the 2025 Best Paper Award for her PNAS study on river flow connectivity in China, selected from 30 applications.",
    blocks: [
      { type: "p", text: "We are pleased to announce that the CYWater 2025 Young Scientist Best Paper Award goes to Jiaojiao Gou, for the paper “Warming climate and water withdrawals threaten river flow connectivity in China,” published in Proceedings of the National Academy of Sciences (PNAS)." },
      { type: "p", text: "Selected from 30 applications by the Award Selection Committee, Jiaojiao receives a CYWater Award certificate and a $200 cash prize. The Committee also recognizes two Outstanding Papers:" },
      { type: "list", items: [
        "Bo Xu & Zhanwei Liu — “Strategizing Renewable Energy Transitions to Preserve Sediment Transport Integrity,” Nature Sustainability.",
        "Yuanlin Qiu — “Enhanced heating effect of lakes under global warming,” Nature Communications."
      ]},
      { type: "h2", text: "Award ceremony at AGU 2025" },
      { type: "p", text: "The 2025 Award Ceremony was held during the AGU Fall Meeting in New Orleans: December 18 (Thursday), 17:45–18:10 at Room 228–230 (NOLA CC), following session H44A. A community dinner followed at Good Catch | Thai Urban Bistro." },
      { type: "h2", text: "Award Selection Committee" },
      { type: "list", items: [
        "Deliang Chen — Tsinghua University (Chair)",
        "Lifeng Luo — Michigan State University",
        "Kaicun Wang — Peking University",
        "Aihui Wang — Institute of Atmospheric Physics, CAS",
        "Chaopeng Shen — Pennsylvania State University",
        "Jian Peng — Helmholtz Centre for Environmental Research",
        "Yuting Yang — Tsinghua University"
      ]}
    ],
    source: "http://mp.weixin.qq.com/s?__biz=MzI2NDUwODk0Ng==&mid=2247484260"
  },

  /* ---- 13th Annual Meeting 3rd notice (news n1) ---- */
  "13th-annual-3rd": {
    title: "13th Annual Meeting — 3rd round notice",
    date: "2025-10-29",
    tag: "Conference",
    lead: "The 13th CYWater Annual Meeting, co-hosted with the Yangtze Technology & Economy Society Youth Committee, with the full programme and venue details released.",
    blocks: [
      { type: "p", text: "The 13th CYWater Annual Meeting is held jointly with the 2nd Annual Meeting of the Youth Committee of the Yangtze Technology & Economy Society. This partnership expands the association's reach into integrated Yangtze basin water–technology–economy dialogues." },
      { type: "h2", text: "Themes" },
      { type: "list", items: [
        "Hydroclimate & global change",
        "Hydrological forecasting & flood/drought disasters",
        "Ecohydrology & fluvial processes",
        "Water resources evolution & regulation",
        "AI for water science"
      ]},
      { type: "p", text: "The full programme, venue and registration details were released through the official CYWater WeChat channel in three rounds of notice (Sep–Oct 2025). For the complete schedule and attachments, please refer to the original announcement." }
    ],
    source: "https://mp.weixin.qq.com/s/zCKWquUgUmreV3fzPFtsxQ"
  },

  /* ---- 12th Summer Meeting Xi'an 2024 (news n3) ---- */
  "12th-summer-xian": {
    title: "12th Summer Meeting — Xi'an, August 2024",
    date: "2024-08-17",
    tag: "Conference",
    lead: "Hosted by Xi'an University of Technology under the theme \"Gathering strength for new quality productive forces in water.\"",
    blocks: [
      { type: "p", text: "The 12th CYWater Summer Meeting was held on August 17–18, 2024 in Xi'an, hosted by the School of Water Resources and Hydroelectric Engineering of Xi'an University of Technology, in collaboration with IGSNRR (CAS), the Institute of Arid Ecology and Water Resources of XAUT, and the State Key Laboratory of Eco-hydraulics in Northwest Arid Region." },
      { type: "quote", text: "Gathering new strength to advance new quality productive forces in water." },
      { type: "h2", text: "Themes" },
      { type: "list", items: [
        "Hydroclimate & global change",
        "Hydrological forecasting & flood/drought disasters",
        "Ecohydrology & sediment processes",
        "Water resources evolution & regulation",
        "AI for water science"
      ]},
      { type: "p", text: "The 2024 edition notably added “AI for water science” as a new thematic track, reflecting the community's growing engagement with machine-learning methods in hydrology." }
    ],
    source: "https://mp.weixin.qq.com/s/as1-T-vfeAywEoYh-AQ2pA"
  },

  /* ---- 2025 Best Paper Award call (news n4) ---- */
  "bpa-2025-call": {
    title: "CYWater Young Scientist Best Paper Award 2025 — Call",
    date: "2025-10-16",
    tag: "Award",
    lead: "Applications open for water-science papers by first authors under 35. One Best Paper Award and up to two Outstanding Paper Awards.",
    blocks: [
      { type: "p", text: "CYWater, founded in 2011, has awarded the Young Scientist Best Paper Award annually since 2012. The award recognizes an author “for outstanding contributions to water sciences.”" },
      { type: "h2", text: "Eligibility" },
      { type: "list", items: [
        "Applicant must be under 35 years of age by December 31 of the award year.",
        "Each applicant may submit one accepted or published paper from the last 12 months.",
        "Former awardees are not eligible."
      ]},
      { type: "h2", text: "Awards" },
      { type: "list", items: [
        "1 Best Paper Award — $200 prize and a CYWater certificate.",
        "Up to 2 Outstanding Paper Awards — CYWater certificates."
      ]},
      { type: "h2", text: "Submission" },
      { type: "p", text: "Applicants submit (1) the paper PDF and (2) a curriculum vitae to the CYWater office by email. Awards are presented during the AGU Fall Meeting." }
    ],
    source: "https://mp.weixin.qq.com/s/aKUQI18eVuUgp_iQDyiFrw"
  },

  /* ---- 10th Summer Meeting 2022 (news n5) ---- */
  "10th-summer-2022": {
    title: "10th Summer Meeting — Online, August 2022",
    date: "2022-08-24",
    tag: "Conference",
    lead: "Six continents, 30+ countries, 630 peak attendees. Theme: Water interaction with the Earth system.",
    blocks: [
      { type: "p", text: "The 10th CYWater Summer Meeting was held online from August 24–28, 2022, co-hosted by the Institute of Geographic Sciences and Natural Resources Research (CAS) and Michigan State University, and chaired by Lifeng Luo (MSU) and Qiuhong Tang (IGSNRR)." },
      { type: "p", text: "The meeting gathered participants from 106 institutions across six continents — with 31 institutions (around 30%) based outside China — and peaked at 630 concurrent attendees. The programme featured 16 invited talks and over 340 contributed presentations across five themed sessions, plus 40 posters." },
      { type: "h2", text: "Five themed sessions" },
      { type: "list", items: [
        "Hydroclimate & global change",
        "Hydrometeorological hazards & early warning",
        "Ecohydrology & fluvial geomorphology",
        "Novel observation & modelling methods",
        "Surface & groundwater resources"
      ]},
      { type: "h2", text: "Recognition" },
      { type: "p", text: "Academician Deliang Chen and Prof. Zong-Liang Yang gave post-meeting commentary, praising the conference as a highly successful gathering that connected young water scholars across borders." }
    ],
    source: "https://mp.weixin.qq.com/s/RJ4docc3w9XTYktIYB1cGg"
  },

  /* ---- 8th Summer Meeting 2020 (news n6) ---- */
  "8th-summer-2020": {
    title: "8th Summer Meeting — Online, August 2020",
    date: "2020-08-11",
    tag: "Conference",
    lead: "106 institutions, 76 talks. Invited speakers: Aiguo Dai, Kevin Trenberth, Kun Yang, Ge Sun.",
    blocks: [
      { type: "p", text: "The 8th CYWater Summer Meeting was held online from August 11–14, 2020, hosted by IGSNRR (CAS) and Michigan State University, with co-organizing support from Tsinghua University, Nanjing University of Information Science and Technology, Sun Yat-sen University, Fudan University, Capital Normal University, Stanford, Princeton, Duke, and Texas A&M." },
      { type: "p", text: "Despite the pandemic-driven shift to fully virtual, the meeting drew 630 peak attendees from 106 institutions across Asia, the Americas, Europe, Africa and Oceania — 31 of those institutions (about 30%) based overseas. The programme included 76 oral presentations across four sessions." },
      { type: "h2", text: "Invited keynote speakers" },
      { type: "list", items: [
        "Aiguo Dai — University at Albany, SUNY",
        "Kevin Trenberth — National Center for Atmospheric Research (NCAR)",
        "Kun Yang — Tsinghua University",
        "Ge Sun — USDA Forest Service"
      ]},
      { type: "figure", src: "article/summer-2020-trenberth.jpg", caption: "Invited keynote speaker Prof. Kevin Trenberth (NCAR) presenting at the 8th Summer Meeting." },
      { type: "h2", text: "Four parallel sessions" },
      { type: "list", items: [
        "Terrestrial water cycle & global change",
        "Hydrometeorological extremes & disasters",
        "Hydrological observation, modelling & new methods",
        "Ecohydrology & geomorphology"
      ]},
      { type: "p", text: "Academician Deliang Chen and Prof. Zong-Liang Yang provided post-meeting commentary, noting the conference's role in connecting scholars worldwide during a constrained year." }
    ],
    source: "https://mp.weixin.qq.com/s/vaTPoxDd-wVdoM9O0IqvJw"
  }
};

const EVENTS = {
  /* ---- 13th Annual Meeting (events e1) ---- */
  "13th-annual": {
    title: "13th Annual Meeting 2025",
    date: "2025",
    location: "China (see WeChat notice)",
    format: "Co-hosted with the Yangtze Technology & Economy Society",
    attendees: "see official notice",
    lead: "The 13th CYWater Annual Meeting, co-hosted with the Youth Committee of the Yangtze Technology & Economy Society.",
    blocks: [
      { type: "p", text: "The 13th CYWater Annual Meeting is co-hosted with the 2nd Annual Meeting of the Youth Committee of the Yangtze Technology & Economy Society, broadening the dialogue to integrated Yangtze basin water, technology and economy questions." },
      { type: "h2", text: "Themes" },
      { type: "list", items: [
        "Hydroclimate & global change",
        "Hydrological forecasting & flood/drought disasters",
        "Ecohydrology & fluvial processes",
        "Water resources evolution & regulation",
        "AI for water science"
      ]}
    ],
    upcoming: true,
    source: "https://mp.weixin.qq.com/s/zCKWquUgUmreV3fzPFtsxQ"
  },

  /* ---- 2025 Best Paper Award (events e2) ---- */
  "bpa-2025": {
    title: "Young Scientist Best Paper Award 2025",
    date: "Dec 2025",
    location: "AGU Fall Meeting, New Orleans",
    format: "Award ceremony",
    attendees: "~30 applicants",
    lead: "Annual award recognising outstanding contributions to water sciences by first authors under 35.",
    blocks: [
      { type: "p", text: "The CYWater Young Scientist Best Paper Award 2025 goes to Jiaojiao Gou for her PNAS paper on river flow connectivity in China, with two Outstanding Paper awards to Bo Xu/Zhanwei Liu (Nature Sustainability) and Yuanlin Qiu (Nature Communications)." },
      { type: "h2", text: "Award Selection Committee" },
      { type: "list", items: [
        "Deliang Chen — Tsinghua University (Chair)",
        "Lifeng Luo — Michigan State University",
        "Kaicun Wang — Peking University",
        "Aihui Wang — Institute of Atmospheric Physics, CAS",
        "Chaopeng Shen — Pennsylvania State University",
        "Jian Peng — Helmholtz Centre for Environmental Research",
        "Yuting Yang — Tsinghua University"
      ]}
    ],
    upcoming: false,
    source: "http://mp.weixin.qq.com/s?__biz=MzI2NDUwODk0Ng==&mid=2247484260"
  },

  /* ---- 12th Summer Meeting (events e3) ---- */
  "12th-summer": {
    title: "12th Summer Meeting — Xi'an 2024",
    date: "Aug 17–18, 2024",
    location: "Xi'an, China",
    format: "In person",
    attendees: "see official notice",
    lead: "Hosted by Xi'an University of Technology; new AI-for-water theme added.",
    blocks: [
      { type: "p", text: "Hosted by the School of Water Resources and Hydroelectric Engineering, Xi'an University of Technology, with IGSNRR (CAS) and the State Key Laboratory of Eco-hydraulics in Northwest Arid Region." },
      { type: "quote", text: "Gathering new strength to advance new quality productive forces in water." },
      { type: "h2", text: "Themes" },
      { type: "list", items: [
        "Hydroclimate & global change",
        "Hydrological forecasting & flood/drought disasters",
        "Ecohydrology & sediment processes",
        "Water resources evolution & regulation",
        "AI for water science (new in 2024)"
      ]}
    ],
    upcoming: false,
    source: "https://mp.weixin.qq.com/s/as1-T-vfeAywEoYh-AQ2pA"
  },

  /* ---- 10th Summer Meeting (events e4) ---- */
  "10th-summer": {
    title: "10th Summer Meeting — Online 2022",
    date: "Aug 24–28, 2022",
    location: "Online",
    format: "Online",
    attendees: "630 peak · 6 continents · 106 institutions",
    lead: "Co-hosted by IGSNRR (CAS) and Michigan State University; 16 invited talks, 340+ contributed.",
    blocks: [
      { type: "p", text: "Co-chaired by Lifeng Luo (MSU) and Qiuhong Tang (IGSNRR), the 10th edition was the largest to date, themed “Water interaction with the Earth system.”" },
      { type: "h2", text: "Five themed sessions" },
      { type: "list", items: [
        "Hydroclimate & global change",
        "Hydrometeorological hazards & early warning",
        "Ecohydrology & fluvial geomorphology",
        "Novel observation & modelling methods",
        "Surface & groundwater resources"
      ]}
    ],
    upcoming: false,
    source: "https://mp.weixin.qq.com/s/RJ4docc3w9XTYktIYB1cGg"
  },

  /* ---- 8th Summer Meeting (events e5) ---- */
  "8th-summer": {
    title: "8th Summer Meeting — Online 2020",
    date: "Aug 11–14, 2020",
    location: "Online",
    format: "Online",
    attendees: "630 peak · 106 institutions · 5 continents",
    lead: "Hosted by IGSNRR (CAS) and Michigan State University; 76 oral presentations.",
    blocks: [
      { type: "p", text: "Despite the pandemic, the 8th edition drew 630 peak attendees from 106 institutions, including 31 overseas (about 30%)." },
      { type: "h2", text: "Invited keynote speakers" },
      { type: "list", items: [
        "Aiguo Dai — University at Albany, SUNY",
        "Kevin Trenberth — NCAR",
        "Kun Yang — Tsinghua University",
        "Ge Sun — USDA Forest Service"
      ]},
      { type: "figure", src: "article/summer-2020-trenberth.jpg", caption: "Invited keynote speaker Prof. Kevin Trenberth (NCAR) presenting." }
    ],
    upcoming: false,
    source: "https://mp.weixin.qq.com/s/vaTPoxDd-wVdoM9O0IqvJw"
  }
};

window.CYWaterContent = { ARTICLES, EVENTS };
