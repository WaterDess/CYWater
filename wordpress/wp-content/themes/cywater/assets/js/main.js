/* ==========================================================================
   CYWater · main.js
   Header behaviour, mobile nav, reveal-on-scroll, toast, FAQ, and scroll-spy.
   ========================================================================== */

(function () {
  "use strict";

  /* ---------- Header: shadow on scroll ---------- */
  const header = document.querySelector(".site-header");
  const onScroll = () => {
    if (!header) return;
    header.classList.toggle("is-scrolled", window.scrollY > 8);
  };
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  /* ---------- Mobile nav ---------- */
  const toggle = document.querySelector(".nav-toggle");
  if (toggle) {
    const closeMobileNav = () => {
      document.body.classList.remove("nav-open");
      document.body.style.overflow = "";
      toggle.setAttribute("aria-expanded", "false");
    };
    toggle.addEventListener("click", () => {
      document.body.classList.toggle("nav-open");
      const open = document.body.classList.contains("nav-open");
      toggle.setAttribute("aria-expanded", String(open));
      document.body.style.overflow = open ? "hidden" : "";
      if (open) document.querySelector(".nav-mobile a")?.focus();
    });
    // close on link click
    document.querySelectorAll(".nav-mobile a").forEach((a) => {
      a.addEventListener("click", () => {
        closeMobileNav();
      });
    });
    document.addEventListener("keydown", (event) => {
      if (event.key !== "Escape" || !document.body.classList.contains("nav-open")) return;
      closeMobileNav();
      toggle.focus();
    });
  }

  /* ---------- Reveal on scroll ---------- */
  const revealObserver = "IntersectionObserver" in window
    ? new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add("is-visible");
            revealObserver.unobserve(entry.target);
          });
        },
        { threshold: 0.12, rootMargin: "0px 0px -40px 0px" }
      )
    : null;

  function refreshReveals(root = document) {
    root.querySelectorAll("[data-reveal]:not(.is-visible)").forEach((el) => {
      if (revealObserver) revealObserver.observe(el);
      else el.classList.add("is-visible");
    });
  }

  window.CYWaterReveal = { refresh: refreshReveals };
  refreshReveals();

  /* ---------- Toast helper ---------- */
  function toast(message) {
    let el = document.querySelector(".toast");
    if (!el) {
      el = document.createElement("div");
      el.className = "toast";
      el.innerHTML =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 6 9 17l-5-5"/></svg><span></span>';
      document.body.appendChild(el);
    }
    el.querySelector("span").textContent = message;
    el.classList.add("is-visible");
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove("is-visible"), 3200);
  }
  window.CYWaterToast = toast;

  /* ---------- Events category navigation and upcoming carousel ---------- */
  const eventNavLinks = Array.from(document.querySelectorAll(".event-index-nav a[href^='#']"));
  if (eventNavLinks.length && "IntersectionObserver" in window) {
    const eventSections = eventNavLinks
      .map((link) => document.querySelector(link.getAttribute("href")))
      .filter(Boolean);
    const eventSpy = new IntersectionObserver(
      (entries) => {
        const visible = entries
          .filter((entry) => entry.isIntersecting)
          .sort((left, right) => left.boundingClientRect.top - right.boundingClientRect.top)[0];
        if (!visible) return;
        eventNavLinks.forEach((link) => {
          link.classList.toggle("is-active", link.getAttribute("href") === `#${visible.target.id}`);
        });
      },
      { rootMargin: "-20% 0px -65% 0px", threshold: 0 }
    );
    eventSections.forEach((section) => eventSpy.observe(section));
  }

  document.querySelectorAll("[data-event-carousel]").forEach((carousel) => {
    const section = carousel.closest(".event-upcoming");
    const previous = section?.querySelector("[data-carousel-previous]");
    const next = section?.querySelector("[data-carousel-next]");
    const step = () => Math.max(carousel.clientWidth * 0.78, 280);
    const updateControls = () => {
      if (!previous || !next) return;
      previous.disabled = carousel.scrollLeft <= 2;
      next.disabled = carousel.scrollLeft + carousel.clientWidth >= carousel.scrollWidth - 2;
    };
    previous?.addEventListener("click", () => carousel.scrollBy({ left: -step(), behavior: "smooth" }));
    next?.addEventListener("click", () => carousel.scrollBy({ left: step(), behavior: "smooth" }));
    carousel.addEventListener("scroll", updateControls, { passive: true });
    window.addEventListener("resize", updateControls, { passive: true });
    updateControls();
  });

  /* ---------- FAQ accordion ---------- */
  document.querySelectorAll(".faq-item").forEach((item) => {
    const q = item.querySelector(".faq-q");
    if (!q) return;
    const toggleFaq = () => {
      const open = item.classList.toggle("is-open");
      q.setAttribute("aria-expanded", String(open));
      item.querySelector(".faq-a")?.setAttribute("aria-hidden", String(!open));
    };
    q.addEventListener("click", toggleFaq);
  });

  /* ---------- Scroll-spy for bylaws TOC ---------- */
  const tocLinks = document.querySelectorAll(".toc a[data-target]");
  if (tocLinks.length && "IntersectionObserver" in window) {
    const headings = Array.from(tocLinks)
      .map((l) => document.getElementById(l.getAttribute("data-target")))
      .filter(Boolean);
    const spy = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting) {
            const id = e.target.id;
            tocLinks.forEach((l) =>
              l.classList.toggle("is-active", l.getAttribute("data-target") === id)
            );
          }
        });
      },
      { rootMargin: "-30% 0px -60% 0px" }
    );
    headings.forEach((h) => spy.observe(h));
  }

})();
