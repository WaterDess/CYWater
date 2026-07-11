/* ==========================================================================
   CYWater · main.js
   Header behaviour, mobile nav, reveal-on-scroll, toast, scroll-spy,
   and shared prototype interactions.
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
  const revealEls = document.querySelectorAll("[data-reveal]");
  if ("IntersectionObserver" in window && revealEls.length) {
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting) {
            e.target.classList.add("is-visible");
            io.unobserve(e.target);
          }
        });
      },
      { threshold: 0.12, rootMargin: "0px 0px -40px 0px" }
    );
    revealEls.forEach((el) => io.observe(el));
  } else {
    revealEls.forEach((el) => el.classList.add("is-visible"));
  }

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

  /* ---------- Newsletter / contact / generic prototype forms ---------- */
  document.addEventListener("submit", (e) => {
    const form = e.target;
    if (!form.matches("[data-proto-form]")) return;
    e.preventDefault();

    const type = form.getAttribute("data-proto-form");

    // special: multistep registration handled separately
    if (type === "register") return;

    const msgMap = {
      newsletter: () => window.CYWaterI18N?.t("common.subscribed") || "Subscribed!",
      contact:    () => window.CYWaterI18N?.t("common.sent") || "Message sent.",
      join:       () => window.CYWaterI18N?.t("common.joined") || "Welcome!",
      profile:    () => window.CYWaterI18N?.t("common.saved") || "Saved.",
      default:    () => "Done.",
    };
    const msg = (msgMap[type] || msgMap.default)();

    form.reset();
    toast(msg);
  });

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

  /* ---------- Modal: open / close ---------- */
  document.addEventListener("click", (e) => {
    const opener = e.target.closest("[data-modal-open]");
    if (opener) {
      e.preventDefault();
      const id = opener.getAttribute("data-modal-open");
      const modal = document.getElementById(id);
      if (modal) {
        modal.classList.add("is-open");
        document.body.style.overflow = "hidden";
      }
      return;
    }
    const closer = e.target.closest("[data-modal-close]");
    if (closer) {
      const modal = closer.closest(".modal-overlay");
      if (modal) {
        modal.classList.remove("is-open");
        document.body.style.overflow = "";
      }
      return;
    }
    // click on backdrop
    if (e.target.classList.contains("modal-overlay")) {
      e.target.classList.remove("is-open");
      document.body.style.overflow = "";
    }
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      document.querySelectorAll(".modal-overlay.is-open").forEach((m) => {
        m.classList.remove("is-open");
        document.body.style.overflow = "";
      });
    }
  });

  /* ---------- Schedule tabs ---------- */
  document.querySelectorAll("[data-tabs]").forEach((group) => {
    const tabs = group.querySelectorAll(".schedule-tab");
    const panels = group.querySelectorAll("[data-panel]");
    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        const target = tab.getAttribute("data-tab");
        tabs.forEach((t) => t.classList.toggle("is-active", t === tab));
        panels.forEach((p) =>
          p.classList.toggle("is-active", p.getAttribute("data-panel") === target)
        );
      });
    });
  });
})();
