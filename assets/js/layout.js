/* ==========================================================================
   CYWater · shared layout injector
   Renders the site header + footer + mobile nav into placeholders so every
   page stays in sync. Active link is detected from <body data-page>.
   ========================================================================== */

(function () {
  "use strict";

  // Site-root prefix: read from <html data-root="..."> set per page, else "./"
  const ROOT = document.documentElement.getAttribute("data-root") || "./";
  const r = ROOT;

  const CHEVRON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="m6 9 6 6 6-6"/></svg>';

  function navItem(page, label, href, children = "") {
    if (children) {
      return `<li class="has-dropdown">
        <a class="nav-link" href="${href}" data-nav="${page}"><span>${label}</span>${CHEVRON}</a>
        <div class="dropdown">${children}</div>
      </li>`;
    }
    return `<li><a class="nav-link" href="${href}" data-nav="${page}"><span>${label}</span></a></li>`;
  }

  const aboutChildren = `
    <a href="${r}about/index.html">About CYWater</a>
    <a href="${r}about/board.html">Board</a>
    <a href="${r}about/bylaws.html">Bylaws</a>`;
  const headerHTML = `
  <header class="site-header">
    <div class="header-inner">
      <a class="brand" href="${r}index.html" aria-label="CYWater home">
        <img class="brand-logo" src="${r}assets/img/logo.png" alt="CYWater" width="41" height="50">
      </a>
      <nav class="nav" aria-label="Primary">
        <ul class="nav-list">
          ${navItem("about", "About", `${r}about/index.html`, aboutChildren)}
          ${navItem("membership", "Membership", `${r}membership/index.html`)}
          ${navItem("events", "Events", `${r}events/index.html`)}
          ${navItem("news", "News", `${r}news/index.html`)}
          ${navItem("awards", "Awards", `${r}awards/index.html`)}
          ${navItem("contact", "Contact", `${r}contact/index.html`)}
        </ul>
      </nav>
      <div class="header-actions">
        <a class="btn btn-ghost" href="${r}membership/dashboard.html">Sign in</a>
        <a class="btn btn-primary" href="${r}membership/index.html">Join CYWater</a>
        <button class="nav-toggle" aria-label="Menu" aria-expanded="false"><span></span></button>
      </div>
    </div>
  </header>
  <nav class="nav-mobile" aria-label="Mobile">
    <a href="${r}about/index.html">About</a>
    <a href="${r}about/board.html" class="sub-link">Board</a>
    <a href="${r}about/bylaws.html" class="sub-link">Bylaws</a>
    <a href="${r}membership/index.html">Membership</a>
    <a href="${r}events/index.html">Events</a>
    <a href="${r}news/index.html">News</a>
    <a href="${r}awards/index.html">Awards</a>
    <a href="${r}contact/index.html">Contact</a>
    <a class="btn btn-primary btn-block" href="${r}membership/index.html">Join CYWater</a>
  </nav>`;

  function buildFooter() {
    const cols = [
      {
        head: "Explore",
        links: [
          ["About", `${r}about/index.html`],
          ["Membership", `${r}membership/index.html`],
          ["Events", `${r}events/index.html`],
          ["Awards", `${r}awards/index.html`],
        ],
      },
      {
        head: "Association",
        links: [
          ["Board", `${r}about/board.html`],
          ["Bylaws", `${r}about/bylaws.html`],
          ["News", `${r}news/index.html`],
          ["Contact", `${r}contact/index.html`],
        ],
      },
    ];

    const colsHTML = cols
      .map((c) => {
        const links = c.links
          .map(([label, href]) => `<li><a href="${href}">${label}</a></li>`)
          .join("");
        return `<div class="footer-col"><h4>${c.head}</h4><ul>${links}</ul></div>`;
      })
      .join("");

    return `
    <footer class="site-footer">
      <div class="container">
        <div class="footer-grid">
          <div class="footer-brand">
            <a class="brand" href="${r}index.html">
              <img class="brand-logo brand-logo--footer" src="${r}assets/img/logo.png" alt="CYWater" width="41" height="50">
            </a>
            <p class="footer-tag">A non-profit international association advancing water sciences education, research, and professional development. Founded in 2011.</p>
          </div>
          ${colsHTML}
          <div class="footer-col">
            <h4>Mailing address</h4>
            <p class="footer-tag">202 E. Green St. Suite 2<br>Champaign, IL 61820, USA</p>
            <a class="link footer-contact-link" href="${r}contact/index.html">Contact CYWater</a>
          </div>
        </div>
        <div class="footer-bottom">
          <span>&copy; 2011–2026 CYWater. All rights reserved.</span>
          <a href="${r}about/bylaws.html">Bylaws</a>
        </div>
      </div>
    </footer>`;
  }

  function inject() {
    const h = document.getElementById("site-header");
    const f = document.getElementById("site-footer");
    if (h) h.outerHTML = headerHTML;
    if (f) f.outerHTML = buildFooter();

    // active nav highlighting
    const here = document.body.getAttribute("data-page") || "";
    const activePage = { board: "about", bylaws: "about", dashboard: "membership" }[here] || here;
    document.querySelectorAll("[data-nav]").forEach((a) => {
      if (a.getAttribute("data-nav") === activePage) a.setAttribute("aria-current", "true");
    });

  }

  inject();
})();
