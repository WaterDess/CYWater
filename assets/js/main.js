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
    toggle.addEventListener("click", () => {
      document.body.classList.toggle("nav-open");
      const open = document.body.classList.contains("nav-open");
      toggle.setAttribute("aria-expanded", String(open));
      document.body.style.overflow = open ? "hidden" : "";
    });
    // close on link click
    document.querySelectorAll(".nav-mobile a").forEach((a) => {
      a.addEventListener("click", () => {
        document.body.classList.remove("nav-open");
        document.body.style.overflow = "";
        toggle.setAttribute("aria-expanded", "false");
      });
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

  /* ---------- FAQ accordion ---------- */
  document.querySelectorAll(".faq-item").forEach((item) => {
    const q = item.querySelector(".faq-q");
    if (!q) return;
    q.setAttribute("role", "button");
    q.setAttribute("tabindex", "0");
    const toggleFaq = () => {
      const open = item.classList.toggle("is-open");
      q.setAttribute("aria-expanded", String(open));
    };
    q.addEventListener("click", toggleFaq);
    q.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        toggleFaq();
      }
    });
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
