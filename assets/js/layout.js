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

  const ICONS = {
    globe: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>',
    chevron: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="m6 9 6 6 6-6"/></svg>',
    mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
  };

  function navItem(key, href, children) {
    const label = `<span data-i18n="nav.${key}"></span>`;
    if (children) {
      return `<li class="has-dropdown">
        <a class="nav-link" href="${href}" data-nav="${key}">${label}${ICONS.chevron}</a>
        <div class="dropdown">${children}</div>
      </li>`;
    }
    return `<li><a class="nav-link" href="${href}" data-nav="${key}">${label}</a></li>`;
  }

  const aboutChildren = `
    <a href="${r}about/index.html" data-i18n="nav.about"></a>
    <a href="${r}about/board.html" data-i18n="nav.board"></a>
    <a href="${r}about/bylaws.html" data-i18n="nav.bylaws"></a>`;
  const headerHTML = `
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="${r}index.html" aria-label="CYWater home">
        <img class="brand-logo" src="${r}assets/img/logo.png" alt="CYWater" width="41" height="50">
      </a>
      <nav class="nav" aria-label="Primary">
        <ul class="nav-list">
          ${navItem("about", `${r}about/index.html`, aboutChildren)}
          ${navItem("membership", `${r}membership/index.html`)}
          ${navItem("events", `${r}events/index.html`)}
          ${navItem("news", `${r}news/index.html`)}
          ${navItem("journal", `${r}journal/index.html`)}
          ${navItem("contact", `${r}contact/index.html`)}
        </ul>
      </nav>
      <div class="header-actions">
        <button class="lang-toggle" data-lang-toggle aria-label="Switch language">
          ${ICONS.globe}<span class="lang-current">EN</span>
        </button>
        <a class="btn btn-ghost" href="${r}membership/dashboard.html" data-i18n="nav.signIn"></a>
        <a class="btn btn-primary" href="${r}membership/index.html" data-i18n="nav.join"></a>
        <button class="nav-toggle" aria-label="Menu" aria-expanded="false"><span></span></button>
      </div>
    </div>
  </header>
  <nav class="nav-mobile" aria-label="Mobile">
    <a href="${r}about/index.html" data-i18n="nav.about"></a>
    <a href="${r}about/board.html" class="sub-link" data-i18n="nav.board"></a>
    <a href="${r}about/bylaws.html" class="sub-link" data-i18n="nav.bylaws"></a>
    <a href="${r}membership/index.html" data-i18n="nav.membership"></a>
    <a href="${r}events/index.html" data-i18n="nav.events"></a>
    <a href="${r}news/index.html" data-i18n="nav.news"></a>
    <a href="${r}journal/index.html" data-i18n="nav.journal"></a>
    <a href="${r}contact/index.html" data-i18n="nav.contact"></a>
    <a class="btn btn-primary btn-block" href="${r}membership/index.html" data-i18n="nav.join"></a>
  </nav>`;

  function buildFooter() {
    const cols = [
      {
        head: "footer.explore",
        links: [
          ["nav.about", `${r}about/index.html`],
          ["nav.membership", `${r}membership/index.html`],
          ["nav.events", `${r}events/index.html`],
          ["nav.journal", `${r}journal/index.html`],
        ],
      },
      {
        head: "footer.association",
        links: [
          ["nav.board", `${r}about/board.html`],
          ["nav.bylaws", `${r}about/bylaws.html`],
          ["nav.news", `${r}news/index.html`],
          ["nav.contact", `${r}contact/index.html`],
        ],
      },
    ];

    const colsHTML = cols
      .map((c) => {
        const links = c.links
          .map(([k, h]) => `<li><a href="${h}" data-i18n="${k}"></a></li>`)
          .join("");
        return `<div class="footer-col"><h4 data-i18n="${c.head}"></h4><ul>${links}</ul></div>`;
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
            <p class="footer-tag" data-i18n="footer.tagline"></p>
            <div class="footer-social" style="margin-top:1rem">
              <a href="#" aria-label="LinkedIn">${ICONS.mail}</a>
              <a href="#" aria-label="WeChat">${ICONS.mail}</a>
              <a href="mailto:info@cywater.org" aria-label="Email">${ICONS.mail}</a>
            </div>
          </div>
          ${colsHTML}
          <div class="footer-col">
            <h4 data-i18n="footer.newsletter"></h4>
            <p class="footer-tag" data-i18n="footer.newsletterHint" style="margin-bottom:.75rem"></p>
            <form class="footer-newsletter" data-proto-form="newsletter">
              <input type="email" required data-i18n-attr="placeholder: footer.emailPlaceholder" aria-label="Email">
              <button class="btn btn-accent" type="submit" data-i18n="footer.subscribe"></button>
            </form>
          </div>
        </div>
        <div class="footer-bottom">
          <span data-i18n="footer.rights"></span>
          <div style="display:flex;gap:1.2rem">
            <a href="#" data-i18n="footer.privacy"></a>
            <a href="#" data-i18n="footer.terms"></a>
            <a href="${r}about/bylaws.html" data-i18n="footer.bylaws"></a>
          </div>
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
    document.querySelectorAll("[data-nav]").forEach((a) => {
      if (a.getAttribute("data-nav") === here) a.setAttribute("aria-current", "true");
    });

    // re-run i18n on freshly injected nodes
    if (window.CYWaterI18N) {
      window.CYWaterI18N.applyTranslations(window.CYWaterI18N.getLang());
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", inject);
  } else {
    inject();
  }
})();
