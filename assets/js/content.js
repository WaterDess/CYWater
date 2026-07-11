/* ==========================================================================
   CYWater · content.js
   Structured data for news articles and event detail pages.
   Each entry is rendered by article.html / detail.html based on ?id= param.
   All content drawn from the official CYWater WeChat channel and the
   association's public records — no fabricated facts.

   Block types (used by ARTICLES, EVENTS, and AWARDS):
     { type: "p",      text: "..." }                paragraph
     { type: "h2",     text: "..." }                section heading
     { type: "h3",     text: "..." }                sub heading
     { type: "quote",  text: "..." }                pull quote (teal border)
     { type: "list",   items: ["...", "..."] }      bullet list
     { type: "figure", src: "article/x.jpg", caption: "..." }   image + caption
     { type: "gallery", items: [{src, caption}, ...] }          multi-image grid
     { type: "table",  head: [...], rows: [[...], ...] }        data table
   ========================================================================== */

const ARTICLES = {
  /* ============ Best Paper Award Results (2020–2025) ============ */

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

  "bpa-2024-result": {
    title: "CYWater Young Scientist Best Paper Award 2024 — Result",
    date: "2024-12-08",
    tag: "Award",
    lead: "Juntai Han & Ziwei Liu receive the 2024 Best Paper Award for their Nature study on streamflow seasonality, selected from 27 applications.",
    blocks: [
      { type: "p", text: "The CYWater 2024 Young Scientist Best Paper Award goes to Juntai Han & Ziwei Liu, for “Streamflow seasonality in a snow-dwindling world,” published in Nature. Selected from 27 applications, they receive a CYWater Award certificate and a $200 cash prize." },
      { type: "p", text: "The Committee also recognizes two Outstanding Papers:" },
      { type: "list", items: [
        "Xinyue Liu — “Unveiling the role of climate in spatially synchronized locust outbreak risks,” Science Advances.",
        "Chenge An — “Autogenic formation of bimodal grain size distributions in rivers and its contribution to gravel-sand transitions,” Geophysical Research Letters."
      ]},
      { type: "p", text: "Given the many high-quality submissions, the Committee also awards two Honorable Mentions:" },
      { type: "list", items: [
        "Hongru Wang — “Occurrence Frequency of Global Atmospheric River (AR) Events,” JGR Atmospheres.",
        "Danghan Xie — “Mangrove removal exacerbates estuarine infilling through landscape-scale bio-morphodynamic feedbacks,” Nature Communications."
      ]},
      { type: "h2", text: "Award ceremony at AGU 2024" },
      { type: "p", text: "December 9, 17:30–18:10 at Salon B (Convention Center), Washington DC, followed by a community dinner at Sichuan Pavilion." },
      { type: "h2", text: "Award Selection Committee" },
      { type: "list", items: [
        "Ximing Cai — University of Illinois, Urbana-Champaign (Chair)",
        "Lei Cheng — Wuhan University",
        "Hongbin Zhan — Texas A&M University",
        "Bo Guo — University of Arizona",
        "Hanbo Yang — Tsinghua University",
        "Hongbo Ma — Tsinghua University",
        "Yi Zheng — Southern University of Science and Technology",
        "Xiaogang He — National University of Singapore",
        "Yang Song — University of Arizona"
      ]}
    ],
    source: "https://mp.weixin.qq.com/s/JBLd1O7gzemcU3yMVuhg_Q"
  },

  "bpa-2023-result": {
    title: "CYWater Young Scientist Best Paper Award 2023 — Result",
    date: "2023-12-10",
    tag: "Award",
    lead: "Laibao Liu receives the 2023 Best Paper Award for his Nature study on tropical water–CO₂ coupling, selected from 33 applications.",
    blocks: [
      { type: "p", text: "The CYWater 2023 Young Scientist Best Paper Award goes to Laibao Liu, for “Increasingly negative tropical water–interannual CO₂ growth rate coupling,” published in Nature. Selected from 33 applications, Laibao receives a CYWater Award certificate and a $200 cash prize." },
      { type: "p", text: "The Committee also recognizes two Outstanding Papers:" },
      { type: "list", items: [
        "Wei Zhi — “Widespread deoxygenation in warming rivers,” Nature Climate Change.",
        "Zexi Shen — “Oceanic climate changes threaten the sustainability of Asia's water tower,” Nature."
      ]},
      { type: "h2", text: "Award Selection Committee" },
      { type: "list", items: [
        "Qingyun Duan — Hohai University (Chair)",
        "Huilin Gao — Texas A&M University",
        "Yanhong Gao — Fudan University",
        "Wenhong Li — Duke University",
        "Peirong Lin — Peking University",
        "Xinyi Shen — University of Wisconsin–Milwaukee",
        "Zhenghui Xie — Institute of Atmospheric Physics, CAS",
        "Yueping Xu — Zhejiang University",
        "Zong-Liang Yang — University of Texas at Austin"
      ]}
    ],
    source: "http://mp.weixin.qq.com/s?__biz=MzI2NDUwODk0Ng==&mid=2247484159"
  },

  "bpa-2022-result": {
    title: "CYWater Young Scientist Best Paper Award 2022 — Result",
    date: "2022-12-10",
    tag: "Award",
    lead: "Shulei Zhang receives the 2022 Best Paper Award for his Nature Climate Change study on global river flood changes, selected from 39 applications.",
    blocks: [
      { type: "p", text: "The CYWater 2022 Young Scientist Best Paper Award goes to Shulei Zhang, for “Reconciling disagreement on global river flood changes in a warming climate,” published in Nature Climate Change. Selected from 39 applications — the largest pool to date — Shulei receives a CYWater Award certificate and a $200 cash prize." },
      { type: "p", text: "The Committee also recognizes two Outstanding Papers:" },
      { type: "list", items: [
        "Xueying Li — “Climate change threatens terrestrial water storage over the Tibetan Plateau,” Nature Climate Change.",
        "Yue Qin — “Snowmelt risk telecouplings for irrigated agriculture,” Nature Climate Change."
      ]},
      { type: "h2", text: "Award Selection Committee" },
      { type: "list", items: [
        "Lu Zhang — CSIRO Land and Water, Australia (Chair)",
        "Adam Wei — University of British Columbia, Canada",
        "Dan Li — Boston University, USA",
        "Ge Sun — USDA Forest Service, USA",
        "John Shi — University of Glasgow, UK",
        "Sha Zhou — Beijing Normal University, China",
        "Yongqiang Zhang — Chinese Academy of Sciences, China",
        "Yuting Yang — Tsinghua University, China"
      ]}
    ],
    source: "https://mp.weixin.qq.com/s/uRSNJhDKj2SezoGV2sDXDw"
  },

  "bpa-2021-result": {
    title: "CYWater Young Scientist Best Paper Award 2021 — Result",
    date: "2021-12-11",
    tag: "Award",
    lead: "Maofeng Liu receives the 2021 Best Paper Award for his Nature Climate Change study on the hydrological cycle and ocean heat uptake, selected from 32 applications.",
    blocks: [
      { type: "p", text: "The CYWater 2021 Young Scientist Best Paper Award goes to Maofeng Liu, for “Enhanced hydrological cycle increases ocean heat uptake and moderates transient climate change,” published in Nature Climate Change. Selected from 32 applications, Maofeng receives a CYWater Award certificate and a $200 cash prize." },
      { type: "p", text: "The Committee also recognizes two Outstanding Papers:" },
      { type: "list", items: [
        "Zhiping Ai — “Global bioenergy with carbon capture and storage potential is largely constrained by sustainable irrigation,” Nature Sustainability.",
        "Xiaogang He — “Climate-informed hydrologic modeling and policy topology to guide managed aquifer recharge,” Science Advances."
      ]},
      { type: "h2", text: "Award Selection Committee" },
      { type: "list", items: [
        "Xingyuan Chen — Pacific Northwest National Laboratory (Chair)",
        "Dong Chen — IGSNRR, CAS",
        "Di Long — Tsinghua University",
        "Lifeng Luo — Michigan State University"
      ]}
    ],
    source: "https://mp.weixin.qq.com/s/c_bn8C4T-MxBOt4YjUJjWQ"
  },

  "bpa-2020-result": {
    title: "CYWater Young Scientist Best Paper Award 2020 — Result",
    date: "2020-12-10",
    tag: "Award",
    lead: "Xingying Huang receives the 2020 Best Paper Award for her Science Advances study on atmospheric river storms, selected from 17 applications.",
    blocks: [
      { type: "p", text: "The CYWater 2020 Young Scientist Best Paper Award goes to Xingying Huang, from Earth Research Institute, University of California Santa Barbara, for “Future precipitation increase from very high resolution ensemble downscaling of extreme atmospheric river storms in California,” published in Science Advances. Selected from 17 applications, Xingying receives a CYWater Award certificate and a $200 cash prize." },
      { type: "p", text: "The Committee also recognizes three Outstanding Papers:" },
      { type: "list", items: [
        "Sha Zhou — “Soil moisture–atmosphere feedbacks mitigate declining water availability in drylands,” Nature Climate Change.",
        "Chong Zhang — “The Effectiveness of the South-to-North Water Diversion Middle Route Project on Water Delivery and Groundwater Recovery in North China Plain,” Water Resources Research.",
        "Qi Huang — “Using Remote Sensing Data-Based Hydrological Model Calibrations for Predicting Runoff in Ungauged or Poorly Gauged Catchments,” Water Resources Research."
      ]},
      { type: "h2", text: "Award ceremony" },
      { type: "p", text: "Held online via Zoom: December 18, 2020, 8:30–9:30 am (Beijing Time), with all four recognized authors presenting their work." },
      { type: "h2", text: "Award Selection Committee" },
      { type: "list", items: [
        "Ming Pan — Princeton University (Chair)",
        "Dong Chen — IGSNRR, CAS",
        "Huilin Gao — Texas A&M University",
        "Dan Li — Boston University",
        "Di Long — Tsinghua University",
        "Lifeng Luo — Michigan State University",
        "Kun Yang — Tsinghua University"
      ]}
    ],
    source: "https://mp.weixin.qq.com/s/HlHLVzBRhsjFH94VJAEZjw"
  },

  /* ============ Summer Meetings ============ */

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

  "12th-summer-xian": {
    title: "12th Summer Meeting — Xi'an, August 2024",
    date: "2024-08-17",
    tag: "Conference",
    lead: "Hosted by Xi'an University of Technology under the theme \"Gathering strength for new quality productive forces in water.\"",
    blocks: [
      { type: "p", text: "The 12th CYWater Summer Meeting was held on August 17–18, 2024 in Xi'an, hosted by the School of Water Resources and Hydroelectric Engineering of Xi'an University of Technology, in collaboration with IGSNRR (CAS), the Institute of Arid Ecology and Water Resources of XAUT, and the State Key Laboratory of Eco-hydraulics in Northwest Arid Region." },
      { type: "quote", text: "凝'新'聚'力'，推动水利新质生产力发展 — Gathering new strength to advance new quality productive forces in water." },
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

  "11th-summer-beijing": {
    title: "11th Summer Meeting — Beijing, August 2023",
    date: "2023-08-05",
    tag: "Conference",
    lead: "Hosted by Capital Normal University and IGSNRR (CAS) in Beijing. Theme: \"Accelerating change for sustainable water resources.\"",
    blocks: [
      { type: "p", text: "The 11th CYWater Summer Meeting was held on August 5–6, 2023 in Beijing, in a hybrid format (in person with online livestream). It was hosted by Capital Normal University (School of Resource Environment and Tourism) and IGSNRR (CAS), with no registration fee." },
      { type: "h2", text: "Theme" },
      { type: "quote", text: "加速变革助力水资源可持续发展 — Accelerating change for sustainable water resources." },
      { type: "h2", text: "Four sessions" },
      { type: "list", items: [
        "Hydroclimate & global change",
        "Ecohydrology & water environment",
        "Novel hydrological observation & modelling methods",
        "Water resources & water disasters"
      ]}
    ],
    source: "https://mp.weixin.qq.com/s/RJ4docc3w9XTYktIYB1cGg"
  },

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
        "Session 1: Hydroclimate & global change",
        "Session 2: Hydrometeorological hazards & early warning",
        "Session 3: Ecohydrology & fluvial geomorphology",
        "Session 4: Novel observation & modelling methods",
        "Session 5: Surface & groundwater resources"
      ]},
      { type: "h2", text: "Recognition" },
      { type: "p", text: "Academician Deliang Chen and Prof. Zong-Liang Yang gave post-meeting commentary, praising the conference as a highly successful gathering that connected young water scholars across borders." }
    ],
    source: "https://mp.weixin.qq.com/s/RJ4docc3w9XTYktIYB1cGg"
  },

  "9th-summer-2021": {
    title: "9th Summer Meeting — Nanjing, July–August 2021",
    date: "2021-07-31",
    tag: "Conference",
    lead: "Hosted by Nanjing University of Information Science and Technology (NUIST) and IGSNRR (CAS). Theme: \"Frontiers of international water science.\"",
    blocks: [
      { type: "p", text: "The 9th CYWater Summer Meeting was held from July 31 to August 1, 2021, in a hybrid format (in person + online), hosted by Nanjing University of Information Science and Technology (NUIST) and the Institute of Atmospheric Physics (CAS). The meeting had no registration fee." },
      { type: "h2", text: "Theme" },
      { type: "quote", text: "国际水科学前沿 — Frontiers of international water science." },
      { type: "h2", text: "Five frontier topics" },
      { type: "list", items: [
        "Hydroclimate & global change",
        "Hydrometeorology & disaster early warning",
        "Ecohydrology & fluvial geomorphology",
        "Novel hydrological observation & modelling methods",
        "Surface & groundwater resources"
      ]}
    ],
    source: "https://mp.weixin.qq.com/s/VIPzHbb3Qn-Rpum05C10WA"
  },

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
  },

  /* ============ Founding story ============ */

  "founding-story": {
    title: "How CYWater began — San Francisco, 2011",
    date: "2011–2013",
    tag: "Community",
    lead: "Initiated during the 2011 AGU Fall Meeting by a group of young Chinese water scientists, CYWater has grown into a network spanning six continents.",
    blocks: [
      { type: "p", text: "In December 2011, during the AGU Fall Meeting in San Francisco, a group of young Chinese professionals in water-related sciences came together to form the International Association of Chinese Youth in Water Sciences (CYWater). Their mission was clear: to promote cooperation in water sciences between Chinese professionals abroad and those in China, and to advance water-science research and education worldwide." },
      { type: "figure", src: "article/founding-2011.jpg", caption: "CYWater was initiated in December 2011, San Francisco." },
      { type: "h2", text: "First AGU Town Hall — 2012" },
      { type: "p", text: "In December 2012, CYWater organized its first AGU Town Hall meeting, titled “Water Security Challenges in East Asia.” The keynote talks were delivered by Prof. Dawen Yang (Tsinghua University) and Prof. Taikan Oki (University of Tokyo), establishing CYWater's presence on the international stage." },
      { type: "h2", text: "First Best Paper Award — 2012" },
      { type: "p", text: "Also in 2012, CYWater established the Young Scientist Best Paper Award — which has been presented every year since, recognising outstanding contributions to water sciences by first authors under 35. The award ceremony is held annually during the AGU Fall Meeting." },
      { type: "h2", text: "First Summer Meeting — 2013" },
      { type: "p", text: "In August 2013, CYWater held its first Summer Meeting in Beijing. Laifang Li received the Best Paper Award at the AGU gathering that December in San Francisco. This established the annual rhythm that continues to this day: a Summer Meeting in China and a winter gathering at AGU." },
      { type: "figure", src: "article/agu-2013-bpa.jpg", caption: "Laifang Li receives the CYWater Best Paper Award, December 12, 2013, San Francisco." },
      { type: "figure", src: "article/agu-2013-dinner.jpg", caption: "CYWater annual gathering, December 12, 2013, San Francisco." },
      { type: "p", text: "Since 2013, the Summer Meeting has been hosted by universities across China — from Beijing to Zhuhai, Nanjing to Xi'an — and has grown from a local gathering into a truly international conference drawing participants from over 30 countries." }
    ],
    source: ""
  },

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
  }
};

const EVENTS = {
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

  "12th-summer": {
    title: "12th Summer Meeting — Xi'an 2024",
    date: "Aug 17–18, 2024",
    location: "Xi'an, China",
    format: "In person",
    attendees: "see official notice",
    lead: "Hosted by Xi'an University of Technology; new AI-for-water theme added.",
    blocks: [
      { type: "p", text: "Hosted by the School of Water Resources and Hydroelectric Engineering, Xi'an University of Technology, with IGSNRR (CAS) and the State Key Laboratory of Eco-hydraulics in Northwest Arid Region." },
      { type: "quote", text: "凝'新'聚'力'，推动水利新质生产力发展 — Gathering new strength to advance new quality productive forces in water." },
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

  "11th-summer": {
    title: "11th Summer Meeting — Beijing 2023",
    date: "Aug 5–6, 2023",
    location: "Beijing, China",
    format: "Hybrid (in person + online)",
    attendees: "No registration fee",
    lead: "Hosted by Capital Normal University and IGSNRR (CAS). Theme: accelerating change for sustainable water resources.",
    blocks: [
      { type: "p", text: "Held at Ziyu Yuli Hotel, Beijing, in a hybrid format with free registration." },
      { type: "h2", text: "Four sessions" },
      { type: "list", items: [
        "Hydroclimate & global change",
        "Ecohydrology & water environment",
        "Novel hydrological observation & modelling methods",
        "Water resources & water disasters"
      ]}
    ],
    upcoming: false,
    source: "https://mp.weixin.qq.com/s/RJ4docc3w9XTYktIYB1cGg"
  },

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
        "Session 1: Hydroclimate & global change",
        "Session 2: Hydrometeorological hazards & early warning",
        "Session 3: Ecohydrology & fluvial geomorphology",
        "Session 4: Novel observation & modelling methods",
        "Session 5: Surface & groundwater resources"
      ]}
    ],
    upcoming: false,
    source: "https://mp.weixin.qq.com/s/RJ4docc3w9XTYktIYB1cGg"
  },

  "9th-summer": {
    title: "9th Summer Meeting — Nanjing 2021",
    date: "Jul 31 – Aug 1, 2021",
    location: "Nanjing, China (hybrid)",
    format: "Hybrid (in person + online)",
    attendees: "No registration fee",
    lead: "Hosted by NUIST and IGSNRR (CAS). Theme: frontiers of international water science.",
    blocks: [
      { type: "p", text: "Hosted by Nanjing University of Information Science and Technology (NUIST) and the Institute of Atmospheric Physics (CAS)." },
      { type: "h2", text: "Five frontier topics" },
      { type: "list", items: [
        "Hydroclimate & global change",
        "Hydrometeorology & disaster early warning",
        "Ecohydrology & fluvial geomorphology",
        "Novel hydrological observation & modelling methods",
        "Surface & groundwater resources"
      ]}
    ],
    upcoming: false,
    source: "https://mp.weixin.qq.com/s/VIPzHbb3Qn-Rpum05C10WA"
  },

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

/* Award yearbook — winners by year for the awards page */
const AWARDS = [
  { year: "2025", bestPaper: { author: "Jiaojiao Gou", title: "Warming climate and water withdrawals threaten river flow connectivity in China", journal: "PNAS" },
    outstanding: [
      { author: "Bo Xu & Zhanwei Liu", title: "Strategizing Renewable Energy Transitions to Preserve Sediment Transport Integrity", journal: "Nature Sustainability" },
      { author: "Yuanlin Qiu", title: "Enhanced heating effect of lakes under global warming", journal: "Nature Communications" }
    ], applications: 30, chair: "Deliang Chen (Tsinghua University)", articleId: "bpa-2025-result" },
  { year: "2024", bestPaper: { author: "Juntai Han & Ziwei Liu", title: "Streamflow seasonality in a snow-dwindling world", journal: "Nature" },
    outstanding: [
      { author: "Xinyue Liu", title: "Unveiling the role of climate in spatially synchronized locust outbreak risks", journal: "Science Advances" },
      { author: "Chenge An", title: "Autogenic formation of bimodal grain size distributions in rivers", journal: "Geophysical Research Letters" }
    ], applications: 27, chair: "Ximing Cai (UIUC)", articleId: "bpa-2024-result" },
  { year: "2023", bestPaper: { author: "Laibao Liu", title: "Increasingly negative tropical water–interannual CO₂ growth rate coupling", journal: "Nature" },
    outstanding: [
      { author: "Wei Zhi", title: "Widespread deoxygenation in warming rivers", journal: "Nature Climate Change" },
      { author: "Zexi Shen", title: "Oceanic climate changes threaten the sustainability of Asia's water tower", journal: "Nature" }
    ], applications: 33, chair: "Qingyun Duan (Hohai University)", articleId: "bpa-2023-result" },
  { year: "2022", bestPaper: { author: "Shulei Zhang", title: "Reconciling disagreement on global river flood changes in a warming climate", journal: "Nature Climate Change" },
    outstanding: [
      { author: "Xueying Li", title: "Climate change threatens terrestrial water storage over the Tibetan Plateau", journal: "Nature Climate Change" },
      { author: "Yue Qin", title: "Snowmelt risk telecouplings for irrigated agriculture", journal: "Nature Climate Change" }
    ], applications: 39, chair: "Lu Zhang (CSIRO)", articleId: "bpa-2022-result" },
  { year: "2021", bestPaper: { author: "Maofeng Liu", title: "Enhanced hydrological cycle increases ocean heat uptake and moderates transient climate change", journal: "Nature Climate Change" },
    outstanding: [
      { author: "Zhiping Ai", title: "Global bioenergy with carbon capture and storage potential is largely constrained by sustainable irrigation", journal: "Nature Sustainability" },
      { author: "Xiaogang He", title: "Climate-informed hydrologic modeling and policy topology to guide managed aquifer recharge", journal: "Science Advances" }
    ], applications: 32, chair: "Xingyuan Chen (PNNL)", articleId: "bpa-2021-result" },
  { year: "2020", bestPaper: { author: "Xingying Huang", title: "Future precipitation increase from very high resolution ensemble downscaling of extreme atmospheric river storms in California", journal: "Science Advances" },
    outstanding: [
      { author: "Sha Zhou", title: "Soil moisture–atmosphere feedbacks mitigate declining water availability in drylands", journal: "Nature Climate Change" },
      { author: "Chong Zhang", title: "The Effectiveness of the South-to-North Water Diversion Middle Route Project on Water Delivery and Groundwater Recovery", journal: "Water Resources Research" },
      { author: "Qi Huang", title: "Using Remote Sensing Data-Based Hydrological Model Calibrations for Predicting Runoff in Ungauged Catchments", journal: "Water Resources Research" }
    ], applications: 17, chair: "Ming Pan (Princeton University)", articleId: "bpa-2020-result" }
];

window.CYWaterContent = { ARTICLES, EVENTS, AWARDS };

/* Ordered lists for the news and events index pages.
   Each item references an id in ARTICLES/EVENTS and supplies display metadata
   (image, which may differ from the article's own hero). */
const NEWS_LIST = [
  { id: "bpa-2025-result", img: "photos/audience-talk.jpg" },
  { id: "13th-annual-3rd", img: "photos/water-aerial.jpg" },
  { id: "12th-summer-xian", img: "photos/underwater-blue.jpg" },
  { id: "bpa-2025-call",   img: "photos/meeting-office.jpg" },
  { id: "11th-summer-beijing", img: "photos/books-library.jpg" },
  { id: "10th-summer-2022",    img: "photos/conference-audience.jpg" },
  { id: "9th-summer-2021",     img: "photos/water-river.jpg" },
  { id: "8th-summer-2020",     img: "photos/water-mountain.jpg" },
  { id: "founding-story",      img: "photos/forest-light.jpg" },
  { id: "bpa-2024-result", img: "photos/meeting-office.jpg" },
  { id: "bpa-2023-result", img: "photos/water-dusk.jpg" },
  { id: "bpa-2022-result", img: "photos/water-aerial.jpg" },
  { id: "bpa-2021-result", img: "photos/underwater-blue.jpg" },
  { id: "bpa-2020-result", img: "photos/conference-room.jpg" }
];

const EVENT_LIST = [
  { id: "13th-annual",  status: "upcoming" },
  { id: "bpa-2025",     status: "upcoming" },
  { id: "12th-summer",  status: "past" },
  { id: "11th-summer",  status: "past" },
  { id: "10th-summer",  status: "past" },
  { id: "9th-summer",   status: "past" },
  { id: "8th-summer",   status: "past" }
];

window.CYWaterContent.NEWS_LIST = NEWS_LIST;
window.CYWaterContent.EVENT_LIST = EVENT_LIST;
