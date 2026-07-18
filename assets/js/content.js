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
      { type: "p", text: "CYWater, founded in 2011, has presented the Young Scientist Best Paper Award annually since 2012. The award recognizes an author “for outstanding contributions to water sciences,” and the ceremony is held each year during the AGU Fall Meeting." },
      { type: "p", text: "Selected from 30 applications by the Award Selection Committee, Jiaojiao receives a CYWater Award certificate and a $200 cash prize. The Committee also recognizes two Outstanding Papers:" },
      { type: "list", items: [
        "Bo Xu & Zhanwei Liu — “Strategizing Renewable Energy Transitions to Preserve Sediment Transport Integrity,” Nature Sustainability.",
        "Yuanlin Qiu — “Enhanced heating effect of lakes under global warming,” Nature Communications."
      ]},
      { type: "h2", text: "Award ceremony & community dinner" },
      { type: "p", text: "The 2025 Award Ceremony was held during the AGU Fall Meeting in New Orleans: December 18 (Thursday), 17:45–18:10 at Room 228–230 (NOLA CC), following session H44A. A community dinner followed at 18:30 at Good Catch | Thai Urban Bistro, 828 Gravier St, New Orleans, LA 70112." },
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
      { type: "p", text: "Given the many high-quality submissions, the Committee also awards two Honorable Mentions — a recognition category introduced for the first time in 2024:" },
      { type: "list", items: [
        "Hongru Wang — “Occurrence Frequency of Global Atmospheric River (AR) Events: A Data Fusion Analysis of 12 Identification Data Sets,” JGR Atmospheres.",
        "Danghan Xie — “Mangrove removal exacerbates estuarine infilling through landscape-scale bio-morphodynamic feedbacks,” Nature Communications."
      ]},
      { type: "h2", text: "Award ceremony & community dinner" },
      { type: "p", text: "The ceremony took place during the AGU Fall Meeting in Washington, DC: December 9 (Monday), 17:30–18:10 at Salon B (Convention Center). A community dinner followed at 18:30 at Sichuan Pavilion, 1814 K St NW, Washington, DC 20006." },
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
      { type: "h2", text: "Award ceremony" },
      { type: "p", text: "The ceremony was held in person during the AGU Fall Meeting, marking the return to an on-site format after three years of online ceremonies (2020–2022)." },
      { type: "h2", text: "Award Selection Committee" },
      { type: "p", text: "Chaired by Prof. Qingyun Duan (Hohai University), the 2023 Committee brought together nine hydrologists and climate scientists spanning institutions in China, the United States and Europe:" },
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
      { type: "h2", text: "Award ceremony" },
      { type: "p", text: "Held online via Zoom on December 16, 2022, 9:00–10:30 am (Beijing), with all recognized authors invited to present their work — the final year of the pandemic-era online ceremony format." },
      { type: "h2", text: "Award Selection Committee" },
      { type: "p", text: "Chaired by Prof. Lu Zhang (CSIRO Land and Water, Australia), the committee spanned Australia, Canada, the USA, the UK and China:" },
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
      { type: "h2", text: "Award ceremony" },
      { type: "p", text: "Held online via Zoom on December 19, 2021, 9:00–10:30 am (Beijing), with all three recognized authors presenting their work and taking questions." },
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
      { type: "p", text: "The CYWater 2020 Young Scientist Best Paper Award goes to Xingying Huang, from the Earth Research Institute, University of California Santa Barbara, for “Future precipitation increase from very high resolution ensemble downscaling of extreme atmospheric river storms in California,” published in Science Advances. Selected from 17 applications, Xingying receives a CYWater Award certificate and a $200 cash prize." },
      { type: "p", text: "The Committee also recognizes three Outstanding Papers:" },
      { type: "list", items: [
        "Sha Zhou — “Soil moisture–atmosphere feedbacks mitigate declining water availability in drylands,” Nature Climate Change.",
        "Chong Zhang — “The Effectiveness of the South-to-North Water Diversion Middle Route Project on Water Delivery and Groundwater Recovery in North China Plain,” Water Resources Research.",
        "Qi Huang — “Using Remote Sensing Data-Based Hydrological Model Calibrations for Predicting Runoff in Ungauged or Poorly Gauged Catchments,” Water Resources Research."
      ]},
      { type: "h2", text: "Award ceremony" },
      { type: "p", text: "Held online via Zoom on December 18, 2020, 8:30–9:30 am (Beijing Time). All four recognized authors presented their work, each with a 10-minute talk followed by Q&A. Presenters and affiliations:" },
      { type: "list", items: [
        "Xingying Huang [Best Paper Award] — University of California, Santa Barbara",
        "Sha Zhou [Outstanding Paper] — Columbia University",
        "Chong Zhang [Outstanding Paper] — Beijing Normal University",
        "Qi Huang [Outstanding Paper] — Sichuan University"
      ]},
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
    title: "13th Annual Meeting — Wuhan, November 2025",
    date: "2025-10-29",
    tag: "Conference",
    lead: "The 13th CYWater Annual Meeting, co-hosted with the Yangtze Technology & Economy Society Youth Committee in Wuhan, with eight themed sessions and the full programme released.",
    blocks: [
      { type: "p", text: "The 13th CYWater Annual Meeting was held jointly with the 2nd Annual Meeting of the Youth Working Committee of the Yangtze River Technology and Economy Society (CTES-YWC). The meeting took place on November 1–2, 2025 (registration October 31) at the Wuhan Chutian Yuehai International Hotel, 181 East Lake Road, Wuchang, with no registration fee." },
      { type: "p", text: "This partnership was a milestone: CTES-YWC, established in December 2021, is a secondary branch of CTES with over 150 members, hosted at the School of Water Resources and Hydropower Engineering of Wuhan University. Co-hosting the meeting broadened CYWater's dialogue into integrated Yangtze basin water, technology and economy questions." },
      { type: "quote", text: "Digital intelligence empowering water science innovation and water security." },
      { type: "h2", text: "Eight themed sessions" },
      { type: "p", text: "The 2025 edition expanded the programme to eight tracks — the largest scope in the meeting's history — reflecting the rapid integration of AI and digital-twin methods into water science:" },
      { type: "list", items: [
        "Water cycle evolution under changing environments",
        "Hydrometeorological forecasting & water disasters",
        "Ecohydrology & green watersheds",
        "River ecosystem evolution & regulation",
        "Multi-dimensional water balance & coordinated dispatch",
        "AI + water science",
        "Digital-twin basins & smart decision-making",
        "Safe, resilient water-network construction & control"
      ]},
      { type: "h2", text: "Organizers" },
      { type: "p", text: "Hosted by CYWater and CTES; organized by the School of Water Resources and Hydropower Engineering (Wuhan University), the Institute of Geographic Sciences and Natural Resources Research (CAS), and the Water Resources Department of the Changjiang River Scientific Research Institute." },
      { type: "p", text: "Three rounds of notice were issued through the official CYWater WeChat channel (Sep–Oct 2025), progressively releasing the session conveners, venue logistics, and the full programme." }
    ],
    source: "https://mp.weixin.qq.com/s/zCKWquUgUmreV3fzPFtsxQ"
  },

  "12th-summer-xian": {
    title: "12th Summer Meeting — Xi'an, August 2024",
    date: "2024-08-17",
    tag: "Conference",
    lead: "Hosted by Xi'an University of Technology under the theme \"Gathering strength for new quality productive forces in water,\" with a new AI-for-water track.",
    blocks: [
      { type: "p", text: "The 12th CYWater Summer Meeting was held on August 17–18, 2024 in Xi'an, in a fully in-person format with no registration fee. Registration exceeded expectations, prompting the organizers to move the venue from the originally announced hotel to the Xi'an Empress Hotel, 45 Xingqing Road, Beilin District." },
      { type: "quote", text: "Gathering strength to advance new quality productive forces in water." },
      { type: "p", text: "Hosted by the School of Water Resources and Hydroelectric Engineering of Xi'an University of Technology, in collaboration with CYWater, IGSNRR (CAS), the State Key Laboratory of Eco-hydraulics in Northwest Arid Region, and the Key Laboratory of Northwest Water Resources and Environmental Ecology (Ministry of Education)." },
      { type: "h2", text: "Five themed sessions" },
      { type: "list", items: [
        "Hydroclimate & global change — trends and mechanisms in precipitation, evaporation, infiltration and runoff; impacts of climate change and human activity on water, agriculture and vegetation.",
        "Hydrological forecasting & flood/drought disasters — forecasting theory and technology, uncertainty analysis, extreme-event case studies, risk management and adaptation.",
        "Ecohydrology & sediment processes — vegetation–hydrology interactions, multi-scale water–carbon–energy coupling, soil erosion, sediment flux and fluvial geomorphology.",
        "Water resources evolution & regulation — natural–artificial dual water-cycle modelling, surface–groundwater conjunctive use, reservoir operation, drought and flood dispatch.",
        "AI for water science (new in 2024) — satellite remote sensing and low-altitude sensing, physics- and data-driven AI modelling, digital-twin basins, cross-sphere coupling."
      ]},
      { type: "p", text: "The 2024 edition notably added “AI for water science” as a new thematic track, reflecting the community's growing engagement with machine-learning methods, digital twins and multi-modal Earth observation in hydrology." }
    ],
    source: "https://mp.weixin.qq.com/s/as1-T-vfeAywEoYh-AQ2pA"
  },

  "11th-summer-beijing": {
    title: "11th Summer Meeting — Beijing, August 2023",
    date: "2023-08-05",
    tag: "Conference",
    lead: "Hosted by Capital Normal University and IGSNRR (CAS) in Beijing. Theme: \"Accelerating change for sustainable water resources.\"",
    blocks: [
      { type: "p", text: "The 11th CYWater Summer Meeting was held on August 5–6, 2023 in Beijing, in a hybrid format (in person with online livestream via Tencent Meeting), with no registration fee. The venue was the Ziyu Yuli Hotel, 55 Zengguang Road, Haidian District, with registration on August 4." },
      { type: "p", text: "Hosted by Capital Normal University (School of Resource Environment and Tourism) and IGSNRR (CAS), the meeting continued CYWater's annual rhythm: a domestic summer meeting each year since 2013, paired with an overseas gathering during the AGU Fall Meeting." },
      { type: "h2", text: "Theme" },
      { type: "quote", text: "Accelerating change for sustainable water resources." },
      { type: "h2", text: "Four sessions" },
      { type: "list", items: [
        "Hydroclimate & global change",
        "Ecohydrology & water environment",
        "Novel hydrological observation & modelling methods",
        "Water resources & water disasters"
      ]},
      { type: "h2", text: "Organizing committee" },
      { type: "p", text: "The 2023 meeting was organized by a community committee of around 30 scholars from institutions across China, the United States, the United Kingdom, Singapore and Japan, including members from Tsinghua University, Peking University, IGSNRR, Capital Normal University, Nanjing University of Information Science and Technology, Sun Yat-sen University, Fudan University, Michigan State University, PNNL, Boston University, University of Glasgow, NUS, the University of Tokyo, and UC San Diego." }
    ],
    source: "https://mp.weixin.qq.com/s/RJ4docc3w9XTYktIYB1cGg"
  },

  "10th-summer-2022": {
    title: "10th Summer Meeting — Online, August 2022",
    date: "2022-08-24",
    tag: "Conference",
    lead: "Six continents, 106 institutions, 630 peak attendees. Five keynote lectures and 340+ contributed presentations across five sessions.",
    blocks: [
      { type: "p", text: "The 10th CYWater Summer Meeting was held online from August 24–28, 2022 — the largest edition to date. It was co-hosted by the Institute of Geographic Sciences and Natural Resources Research (CAS) and Michigan State University, and chaired by Lifeng Luo (MSU) and Qiuhong Tang (IGSNRR)." },
      { type: "p", text: "The meeting gathered participants from 106 institutions across six continents — with 31 institutions (around 30%) based outside China — and peaked at 630 concurrent attendees. The programme featured five invited keynote lectures and over 340 contributed presentations across five themed sessions, delivered through Zoom webinars." },
      { type: "h2", text: "Theme" },
      { type: "quote", text: "Frontiers of International Hydrological Sciences." },
      { type: "h2", text: "Five themed sessions" },
      { type: "list", items: [
        "Session 1: Hydroclimate & global change",
        "Session 2: Hydrometeorological hazards & early warning",
        "Session 3: Ecohydrology & fluvial geomorphology",
        "Session 4: Novel observation & modelling methods",
        "Session 5: Surface & groundwater resources"
      ]},
      { type: "h2", text: "Presentation formats" },
      { type: "list", items: [
        "Keynote lecture — 60 minutes (50 min talk + 10 min Q&A)",
        "Invited talk — 15 minutes (12 min talk + 3 min Q&A)",
        "Contributed talk — 10 minutes (8 min talk + 2 min Q&A)",
        "Flash talk — 2 minutes, 2–3 slides"
      ]},
      { type: "h2", text: "Recognition" },
      { type: "p", text: "Academician Deliang Chen and Prof. Zong-Liang Yang gave post-meeting commentary, praising the conference as a highly successful gathering that connected young water scholars across borders and advanced understanding of water-science frontiers." }
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
      { type: "quote", text: "Frontiers of international water science." },
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
    lead: "106 institutions across five continents, 630 peak attendees, 76 oral presentations. Keynote speakers: Aiguo Dai, Kevin Trenberth, Kun Yang, Ge Sun.",
    blocks: [
      { type: "p", text: "From August 11–14, 2020, CYWater successfully held its 8th Summer Meeting online, its format adapted to the pandemic. The meeting was hosted by the Institute of Geographic Sciences and Natural Resources Research (CAS) and Michigan State University, with co-organizing support from Tsinghua University, Nanjing University of Information Science and Technology, Sun Yat-sen University, Fudan University, Capital Normal University, Stanford University, Princeton University, Duke University, and Texas A&M University." },
      { type: "p", text: "Despite the pandemic-driven shift to fully virtual, the meeting drew 630 peak attendees from 106 institutions across Asia, the Americas, Europe, Africa and Oceania — 31 of those institutions (about 30%) based overseas. The programme included 76 oral presentations across four sessions, spanning a cross-disciplinary scope from hydrology and global change to ecology, geomorphology, AI and new technology, engineering, and socio-economics." },
      { type: "h2", text: "Theme" },
      { type: "quote", text: "Frontiers of International Hydrological Sciences." },
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
      { type: "h2", text: "Post-meeting commentary" },
      { type: "p", text: "Academician Deliang Chen and Prof. Zong-Liang Yang delivered post-meeting commentary, praising the conference highly. They considered it a gathering that provided scholars worldwide with opportunities for exchange and collaboration, advanced understanding and consensus on water-science frontiers, stimulated young researchers' enthusiasm, and played a clear driving role in elevating China's water-science research capacity and development potential." },
      { type: "quote", text: "This conference provided scholars from around the world with opportunities for academic exchange and collaboration, deepened understanding and consensus on frontier water-science questions, and stimulated the research enthusiasm of young scholars at home and abroad — playing a clear driving role in elevating the research strength and development potential of China's water sciences." }
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
  },

  /* ============ International outreach ============ */

  "cop27-2022": {
    title: "COP27 \"China Corner\" Side Event: Climate Change & Flood Disasters",
    date: "2022-11-06",
    tag: "Outreach",
    lead: "At the UN Climate Change Conference COP27 in Sharm El Sheikh, IGSNRR hosted a China Corner side event on addressing climate change and flood disaster challenges.",
    blocks: [
      { type: "p", text: "On November 6, 2022, during the 27th UN Climate Change Conference (COP27) in Sharm El Sheikh, Egypt, the Institute of Geographic Sciences and Natural Resources Research (CAS) — CYWater's secretariat base — hosted a side event at the China Corner titled “Addressing Climate Change and Flood Disaster Challenges.”" },
      { type: "p", text: "The session brought together experts from the University of Birmingham, Beijing Normal University, North China University of Water Resources and Electric Power, Sun Yat-sen University, and Lanzhou University to share the latest research and discuss climate-change response strategies and flood-risk management. The discussion explored how new scientific understanding can support disaster-prevention and mitigation measures at all levels of government." },
      { type: "h2", text: "Programme (Egypt time, UTC+2)" },
      { type: "list", items: [
        "9:00 — Welcome speech: Jun Xia (Wuhan University / IGSNRR, CAS)",
        "9:10 — Urban flood resilience: a comparison of approaches internationally — Nigel Wright (University of Birmingham)",
        "9:30 — Urban flooding/waterlogging under climate change: a great challenge in China — Zongxue Xu (Beijing Normal University)",
        "9:50 — Flood risk in China under climate change: the critical role of vegetation dynamics — Guoyong Leng (IGSNRR, CAS)",
        "10:00 — Flood monitoring service in the Ganges-Brahmaputra-Meghna River Basin — Ximeng Xu (IGSNRR, CAS)",
        "10:20 — Water security and integrated management for the Lancang-Mekong River Basin — Junguo Liu (North China University of Water Resources and Electric Power)",
        "10:40 — Climate change impacts on flood risk from global to local scales — Huan Wu (Sun Yat-sen University)",
        "11:00 — A flood and drought monitoring and forecasting system over China — Qiuhong Tang (IGSNRR, CAS)",
        "11:10 — Flood hazards and mitigation measures in the Ganges-Brahmaputra-Meghna River Basin — Li He (IGSNRR, CAS)",
        "11:20 — Improved hydrological-hydrodynamic model-based flood simulation: a case study in the Lancang-Mekong — Jie Wang (Lanzhou University)"
      ]},
      { type: "p", text: "Moderators: Qiuhong Tang and Guoyong Leng (IGSNRR, CAS). The event was held both in person at the COP27 China Corner and online via Zoom." }
    ],
    source: "https://mp.weixin.qq.com/s/_Nx_34pDxROJH-atyBrTzA"
  }
};

const EVENTS = {
  "annual-2026": {
    title: "CYWater Annual Meeting 2026",
    date: "Oct 16–18, 2026",
    location: "Nanjing, China",
    format: "In person",
    attendees: "Registration opens in August",
    lead: "The International Association of Contemporary Young Scholars in Water Sciences Annual Meeting 2026 will take place in Nanjing, China, from 16 to 18 October.",
    blocks: [
      { type: "p", text: "Registration will open in August. Program, venue, abstract submission, and registration details will be published after confirmation." },
      { type: "p", text: "This preview does not accept registrations or payments." }
    ],
    upcoming: true,
    category: "meeting"
  },

  "13th-annual": {
    title: "13th Annual Meeting — Wuhan 2025",
    date: "Nov 1–2, 2025",
    location: "Wuhan, China",
    format: "In person",
    attendees: "Eight themed sessions",
    image: "events/meeting-2025.jpg",
    imageAlt: "Participants at the 13th CYWater Annual Meeting in Wuhan",
    lead: "The 13th CYWater Annual Meeting was held jointly with the Youth Working Committee of the Yangtze River Technology and Economy Society.",
    blocks: [
      { type: "p", text: "The meeting took place in Wuhan on November 1–2, 2025, with registration on October 31." },
      { type: "quote", text: "Digital intelligence empowering water science innovation and water security." },
      { type: "h2", text: "Eight themed sessions" },
      { type: "list", items: [
        "Water cycle evolution under changing environments",
        "Hydrometeorological forecasting and water disasters",
        "Ecohydrology and green watersheds",
        "River ecosystem evolution and regulation",
        "Multi-dimensional water balance and coordinated dispatch",
        "AI and water science",
        "Digital-twin basins and smart decision-making",
        "Safe and resilient water-network construction"
      ]}
    ],
    upcoming: false,
    category: "meeting"
  },

  "12th-summer": {
    title: "12th Annual Meeting — Xi'an 2024",
    date: "Aug 17–18, 2024",
    location: "Xi'an, China",
    format: "In person",
    attendees: "Five sessions",
    image: "events/meeting-2024.jpg",
    imageAlt: "Participants at the 12th CYWater Annual Meeting in Xi'an",
    lead: "Hosted by Xi'an University of Technology, the meeting introduced a dedicated AI for water science theme.",
    blocks: [
      { type: "p", text: "The meeting was hosted by the School of Water Resources and Hydroelectric Engineering at Xi'an University of Technology." },
      { type: "quote", text: "Gathering new strength to advance innovation in water sciences." },
      { type: "h2", text: "Five themed sessions" },
      { type: "list", items: [
        "Hydroclimate and global change",
        "Hydrological forecasting and flood and drought disasters",
        "Ecohydrology and sediment processes",
        "Water resources evolution and regulation",
        "AI for water science"
      ]}
    ],
    upcoming: false,
    category: "meeting"
  },

  "11th-summer": {
    title: "11th Annual Meeting — Beijing 2023",
    date: "Aug 5–6, 2023",
    location: "Beijing, China",
    format: "Hybrid",
    attendees: "Four sessions",
    image: "events/meeting-2023.jpg",
    imageAlt: "Participants at the 11th CYWater Annual Meeting in Beijing",
    lead: "Hosted by Capital Normal University and IGSNRR, the meeting focused on sustainable water resources under accelerating change.",
    blocks: [
      { type: "p", text: "The meeting was held in Beijing in a hybrid format." },
      { type: "h2", text: "Four sessions" },
      { type: "list", items: [
        "Hydroclimate and global change",
        "Ecohydrology and water environment",
        "Novel hydrological observation and modelling methods",
        "Water resources and water disasters"
      ]}
    ],
    upcoming: false,
    category: "meeting"
  },

  "10th-summer": {
    title: "10th Annual Meeting — Online 2022",
    date: "Aug 24–28, 2022",
    location: "Online",
    format: "Online",
    attendees: "Participants from 106 institutions",
    image: "events/meeting-2022.png",
    imageAlt: "Online participants at the 10th CYWater Annual Meeting",
    lead: "The online meeting brought together participants across six continents for five keynote lectures and more than 340 contributed presentations.",
    blocks: [
      { type: "p", text: "The meeting was organized online and connected participants from 106 institutions across six continents." },
      { type: "h2", text: "Five themed sessions" },
      { type: "list", items: [
        "Hydroclimate and global change",
        "Hydrometeorological hazards and early warning",
        "Ecohydrology and fluvial geomorphology",
        "Novel observation and modelling methods",
        "Surface and groundwater resources"
      ]}
    ],
    upcoming: false,
    category: "meeting"
  },

  "9th-summer": {
    title: "9th Annual Meeting — Online 2021",
    date: "Jul 31–Aug 1, 2021",
    location: "Online",
    format: "Online",
    attendees: "Five frontier topics",
    image: "events/meeting-2021.jpg",
    imageAlt: "Online participants at the 9th CYWater Annual Meeting",
    lead: "Organized with Nanjing University of Information Science and Technology, the meeting focused on frontiers of international water science.",
    blocks: [
      { type: "p", text: "The meeting was organized online during the COVID-19 pandemic." },
      { type: "h2", text: "Five frontier topics" },
      { type: "list", items: [
        "Hydroclimate and global change",
        "Hydrometeorology and disaster early warning",
        "Ecohydrology and fluvial geomorphology",
        "Novel hydrological observation and modelling methods",
        "Surface and groundwater resources"
      ]}
    ],
    upcoming: false,
    category: "meeting"
  },

  "8th-summer": {
    title: "8th Annual Meeting — Online 2020",
    date: "Aug 11–14, 2020",
    location: "Online",
    format: "Online",
    attendees: "76 oral presentations",
    image: "events/meeting-2020.jpg",
    imageAlt: "Online participants at the 8th CYWater Annual Meeting",
    lead: "The four-day online meeting connected 106 institutions and featured 76 oral presentations across four sessions.",
    blocks: [
      { type: "p", text: "The 2020 meeting moved online during the COVID-19 pandemic and connected participants across multiple continents." },
      { type: "h2", text: "Invited keynote speakers" },
      { type: "list", items: [
        "Aiguo Dai — University at Albany, SUNY",
        "Kevin Trenberth — NCAR",
        "Kun Yang — Tsinghua University",
        "Ge Sun — USDA Forest Service"
      ]}
    ],
    upcoming: false,
    category: "meeting"
  },

  "7th-summer": {
    title: "7th Annual Meeting — Zhuhai 2019",
    date: "Jul 14–16, 2019",
    location: "Zhuhai, China",
    format: "In person",
    image: "events/meeting-2019.jpg",
    imageAlt: "Participants at the 7th CYWater Annual Meeting in Zhuhai",
    lead: "Hosted by the School of Atmospheric Sciences at Sun Yat-sen University, the meeting examined challenges and opportunities in interdisciplinary hydrological science.",
    blocks: [{ type: "p", text: "The 7th Annual Meeting was held in Zhuhai and hosted by Sun Yat-sen University." }],
    upcoming: false,
    category: "meeting"
  },

  "6th-summer": {
    title: "6th Annual Meeting — Beijing 2018",
    date: "Jul 31–Aug 2, 2018",
    location: "Beijing, China",
    format: "In person",
    image: "events/meeting-2018.jpg",
    imageAlt: "Participants at the 6th CYWater Annual Meeting in Beijing",
    lead: "The 6th CYWater Annual Meeting was held in Beijing.",
    blocks: [{ type: "p", text: "The meeting continued CYWater's annual scientific exchange program for early-career water scholars." }],
    upcoming: false,
    category: "meeting"
  },

  "5th-summer": {
    title: "5th Annual Meeting — Beijing 2017",
    date: "Aug 13, 2017",
    location: "Beijing, China",
    format: "In person",
    image: "events/meeting-2017.jpg",
    imageAlt: "Participants at the 5th CYWater Annual Meeting in Beijing",
    lead: "The 5th CYWater Annual Meeting was held in Beijing.",
    blocks: [{ type: "p", text: "The meeting continued the Association's annual program of scientific exchange." }],
    upcoming: false,
    category: "meeting"
  },

  "4th-summer": {
    title: "4th Annual Meeting — Beijing 2016",
    date: "Aug 6, 2016",
    location: "Beijing, China",
    format: "In person",
    image: "events/meeting-2016.jpg",
    imageAlt: "Participants at the 4th CYWater Annual Meeting in Beijing",
    lead: "The 4th CYWater Annual Meeting was held in Beijing.",
    blocks: [{ type: "p", text: "The meeting brought together water-science researchers for presentations and community exchange." }],
    upcoming: false,
    category: "meeting"
  },

  "3rd-summer": {
    title: "3rd Annual Meeting — Beijing 2015",
    date: "Jul 11, 2015",
    location: "Beijing, China",
    format: "In person",
    image: "events/meeting-2015.jpg",
    imageAlt: "Participants at the 3rd CYWater Annual Meeting in Beijing",
    lead: "The 3rd CYWater Annual Meeting was held in Beijing.",
    blocks: [{ type: "p", text: "The meeting continued the Association's annual program of research presentations and professional exchange." }],
    upcoming: false,
    category: "meeting"
  },

  "2nd-summer": {
    title: "2nd Annual Meeting — Beijing 2014",
    date: "Jul 12, 2014",
    location: "Beijing, China",
    format: "In person",
    image: "events/meeting-2014.jpg",
    imageAlt: "Participants at the 2nd CYWater Annual Meeting in Beijing",
    lead: "The 2nd CYWater Annual Meeting was held in Beijing.",
    blocks: [{ type: "p", text: "The meeting built on the first annual gathering and expanded scientific exchange within the CYWater community." }],
    upcoming: false,
    category: "meeting"
  },

  "1st-summer": {
    title: "1st Annual Meeting — Beijing 2013",
    date: "Aug 3, 2013",
    location: "Beijing, China",
    format: "In person",
    image: "events/meeting-2013.jpg",
    imageAlt: "Participants at the 1st CYWater Annual Meeting in Beijing",
    lead: "The first CYWater Annual Meeting established a continuing forum for scientific exchange in water sciences.",
    blocks: [{ type: "p", text: "The first Annual Meeting was held in Beijing on August 3, 2013." }],
    upcoming: false,
    category: "meeting"
  },

  "annual-gathering-2013": {
    title: "CYWater Annual Gathering — San Francisco 2013",
    date: "Dec 12, 2013",
    location: "San Francisco, USA",
    format: "Community gathering during the AGU Fall Meeting",
    image: "article/agu-2013-dinner.jpg",
    imageAlt: "CYWater annual gathering in San Francisco on December 12, 2013",
    lead: "CYWater members gathered in San Francisco during the 2013 AGU Fall Meeting for community exchange and the Best Paper Award presentation.",
    blocks: [
      { type: "p", text: "The gathering took place on December 12, 2013, in San Francisco." },
      { type: "figure", src: "article/agu-2013-bpa.jpg", caption: "Laifang Li receives the CYWater Best Paper Award during the 2013 gathering." }
    ],
    upcoming: false,
    category: "gathering"
  }
};

/* Award yearbook — winners by year for the awards page */
const AWARDS = [
  { year: "2025", bestPaper: { author: "Jiaojiao Gou", title: "Warming climate and water withdrawals threaten river flow connectivity in China", journal: "PNAS", doi: "10.1073/pnas.2421046122" },
    outstanding: [
      { author: "Bo Xu & Zhanwei Liu", title: "Strategizing Renewable Energy Transitions to Preserve Sediment Transport Integrity", journal: "Nature Sustainability", doi: "10.1038/s41893-025-01626-5" },
      { author: "Yuanlin Qiu", title: "Enhanced heating effect of lakes under global warming", journal: "Nature Communications", doi: "10.1038/s41467-025-59291-3" }
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
    ], applications: 17, chair: "Ming Pan (Princeton University)", articleId: "bpa-2020-result" },
  { year: "2019", bestPaper: { author: "Hongbo Ma", title: "Universal relation with regime transition for sediment transport in fine-grained rivers", journal: "Proceedings of the National Academy of Sciences" },
    outstanding: [
      { author: "Xiaogang He", title: "Solar and wind energy enhances drought resilience and groundwater sustainability", journal: "Nature Communications" },
      { author: "Mengfei Mu", title: "A water-electricity nexus model to analyze thermoelectricity supply reliability under environmental regulations and economic penalties during drought events", journal: "Environmental Modelling & Software" }
    ] },
  { year: "2018", bestPaper: { author: "Qi Huang", title: "Discharge estimation in high-mountain regions with improved methods using multisource remote sensing: A case study of the Upper Brahmaputra River", journal: "Remote Sensing of Environment" },
    outstanding: [
      { author: "Hongxiang Yan", title: "Next-Generation Intensity-Duration-Frequency Curves for Hydrologic Design in Snow-Dominated Environments", journal: "Water Resources Research" },
      { author: "Qiaohong Sun", title: "A review of global precipitation datasets: data sources, estimation, and intercomparisons", journal: "Reviews of Geophysics" }
    ] },
  { year: "2017", bestPaper: null, outstanding: [], note: "The 2017 award citation is pending confirmation in the supplied records." },
  { year: "2016", bestPaper: { author: "Yujin Zeng, Zhenghui Xie & Jing Zhou", title: "Hydrologic and Climatic Responses to Global Anthropogenic Groundwater Extraction", journal: "" }, outstanding: [] },
  { year: "2015", bestPaper: { author: "Dongqin Yin, Michael L. Roderick, Guy Leech, Fubao Sun & Yuefei Huang", title: "The contribution of reduction in evaporative cooling to higher surface air temperatures during drought", journal: "" }, outstanding: [] },
  { year: "2014", bestPaper: { author: "Lishan Ran, X. X. Lu & Z. Xin", title: "Erosion-induced massive organic carbon burial and carbon emission in the Yellow River basin, China", journal: "" }, outstanding: [] },
  { year: "2013", bestPaper: { author: "Laifang Li, Wenhong Li & Yochanan Kushnir", title: "Variation of the North Atlantic subtropical high western ridge and its implication to Southeastern US summer precipitation", journal: "" }, outstanding: [] },
  { year: "2012", bestPaper: { author: "Huimin Lei, Dawen Yang, Jianfeng Cai & Fengjiao Wang", title: "Long-term variability of the carbon balance in a large irrigated area along the lower Yellow River from 1984 to 2006", journal: "" }, outstanding: [] }
];

window.CYWaterContent = { ARTICLES, EVENTS, AWARDS };

/* Ordered lists for the news and events index pages.
   Each item references an id in ARTICLES/EVENTS and supplies display metadata
   (image, which may differ from the article's own hero). */
const NEWS_LIST = [
  { id: "bpa-2025-result", img: "photos/audience-talk.jpg" },
  { id: "13th-annual-3rd", img: "events/meeting-2025.jpg" },
  { id: "12th-summer-xian", img: "events/meeting-2024.jpg" },
  { id: "bpa-2025-call",   img: "photos/meeting-office.jpg" },
  { id: "11th-summer-beijing", img: "events/meeting-2023.jpg" },
  { id: "10th-summer-2022",    img: "events/meeting-2022.png" },
  { id: "9th-summer-2021",     img: "events/meeting-2021.jpg" },
  { id: "8th-summer-2020",     img: "events/meeting-2020.jpg" },
  { id: "cop27-2022",          img: "photos/water-dusk.jpg" },
  { id: "founding-story",      img: "article/founding-2011.jpg" },
  { id: "bpa-2024-result", img: "photos/meeting-office.jpg" },
  { id: "bpa-2023-result", img: "photos/water-dusk.jpg" },
  { id: "bpa-2022-result", img: "photos/water-aerial.jpg" },
  { id: "bpa-2021-result", img: "photos/underwater-blue.jpg" },
  { id: "bpa-2020-result", img: "photos/conference-room.jpg" }
];

const EVENT_LIST = [
  { id: "annual-2026", status: "upcoming", category: "meeting" },
  { id: "13th-annual", status: "past", category: "meeting" },
  { id: "12th-summer", status: "past", category: "meeting" },
  { id: "11th-summer", status: "past", category: "meeting" },
  { id: "10th-summer", status: "past", category: "meeting" },
  { id: "9th-summer", status: "past", category: "meeting" },
  { id: "8th-summer", status: "past", category: "meeting" },
  { id: "7th-summer", status: "past", category: "meeting" },
  { id: "6th-summer", status: "past", category: "meeting" },
  { id: "5th-summer", status: "past", category: "meeting" },
  { id: "4th-summer", status: "past", category: "meeting" },
  { id: "3rd-summer", status: "past", category: "meeting" },
  { id: "2nd-summer", status: "past", category: "meeting" },
  { id: "1st-summer", status: "past", category: "meeting" },
  { id: "annual-gathering-2013", status: "past", category: "gathering" }
];

window.CYWaterContent.NEWS_LIST = NEWS_LIST;
window.CYWaterContent.EVENT_LIST = EVENT_LIST;
