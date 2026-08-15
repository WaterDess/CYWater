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
    const pagination = section?.querySelector("[data-carousel-pagination]");
    const sourceCards = Array.from(carousel.querySelectorAll(".upcoming-event-card"));
    const templates = sourceCards.map((card) => card.cloneNode(true));
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
    let activeIndex = 0;
    let desiredIndex = 0;
    let rotationTimer;
    let touchStartX = null;
    let moving = false;
    let movingStep = 0;
    let transitionFallback;
    const track = document.createElement("div");
    track.className = "event-carousel-track";

    const normalizeIndex = (index) => (index + sourceCards.length) % sourceCards.length;

    const directionTo = (target) => {
      const forward = normalizeIndex(target - activeIndex);
      const backward = normalizeIndex(activeIndex - target);
      return forward <= backward ? 1 : -1;
    };

    const createSlot = (index, position) => {
      const card = templates[normalizeIndex(index)].cloneNode(true);
      card.classList.remove(
        "is-active",
        "is-previous",
        "is-next",
        "is-far-previous",
        "is-far-next"
      );
      card.classList.add(`is-${position}`);
      card.setAttribute("aria-hidden", position === "active" ? "false" : "true");
      card.tabIndex = position === "active" ? 0 : -1;
      if (position === "active") card.removeAttribute("inert");
      else card.setAttribute("inert", "");
      return card;
    };

    const renderSlots = () => {
      track.classList.remove("is-moving-previous", "is-moving-next");
      if (sourceCards.length === 1) {
        track.classList.add("has-single-event");
        track.replaceChildren(createSlot(0, "active"));
      } else {
        track.classList.remove("has-single-event");
        track.replaceChildren(
          createSlot(activeIndex - 2, "far-previous"),
          createSlot(activeIndex - 1, "previous"),
          createSlot(activeIndex, "active"),
          createSlot(activeIndex + 1, "next"),
          createSlot(activeIndex + 2, "far-next")
        );
      }

      pagination?.querySelectorAll(".event-carousel-dot").forEach((dot, index) => {
        const isActive = index === activeIndex;
        dot.classList.toggle("is-active", isActive);
        dot.setAttribute("aria-current", isActive ? "true" : "false");
      });
      if (previous) previous.disabled = sourceCards.length < 2;
      if (next) next.disabled = sourceCards.length < 2;
    };

    const stopRotation = () => window.clearTimeout(rotationTimer);
    const isInteracting = () => section?.matches(":hover") || section?.contains(document.activeElement);
    const startRotation = () => {
      stopRotation();
      if (sourceCards.length > 1 && !reduceMotion.matches && !document.hidden && !moving && !isInteracting()) {
        rotationTimer = window.setTimeout(() => requestStep(1), 6500);
      }
    };

    const continueToDesired = () => {
      if (desiredIndex !== activeIndex) {
        animateStep(directionTo(desiredIndex));
      } else {
        startRotation();
      }
    };

    const finishMove = () => {
      if (!moving) return;
      window.clearTimeout(transitionFallback);
      activeIndex = normalizeIndex(activeIndex + movingStep);
      moving = false;
      movingStep = 0;
      carousel.classList.add("is-resetting");
      renderSlots();
      // The five-slot buffer already contains the incoming side preview, so
      // this transition-free recenter is visually identical to the completed
      // frame. Motion resumes only after the stable slots are back at rest.
      window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
        carousel.classList.remove("is-resetting");
        continueToDesired();
      }));
    };

    const animateStep = (delta) => {
      if (moving || sourceCards.length < 2 || desiredIndex === activeIndex) return;
      stopRotation();
      if (reduceMotion.matches) {
        activeIndex = desiredIndex;
        renderSlots();
        startRotation();
        return;
      }
      moving = true;
      movingStep = delta < 0 ? -1 : 1;
      const direction = movingStep < 0 ? "previous" : "next";
      track.classList.add(`is-moving-${direction}`);
      const complete = (event) => {
        if (event.target !== track || event.propertyName !== "transform") return;
        track.removeEventListener("transitionend", complete);
        finishMove();
      };
      track.addEventListener("transitionend", complete);
      transitionFallback = window.setTimeout(() => {
        track.removeEventListener("transitionend", complete);
        finishMove();
      }, 700);
    };

    const requestIndex = (index) => {
      const target = normalizeIndex(index);
      desiredIndex = target;
      stopRotation();
      if (!moving) {
        if (target === activeIndex) startRotation();
        else animateStep(directionTo(target));
      }
    };

    const requestStep = (delta) => {
      const base = moving ? desiredIndex : activeIndex;
      requestIndex(base + (delta < 0 ? -1 : 1));
    };

    if (pagination) {
      pagination.replaceChildren();
      sourceCards.forEach((card, index) => {
        const dot = document.createElement("button");
        dot.type = "button";
        dot.className = "event-carousel-dot";
        dot.setAttribute("aria-label", `Show ${card.querySelector("h3")?.textContent || `upcoming event ${index + 1}`}`);
        dot.addEventListener("click", () => {
          requestIndex(index);
        });
        pagination.append(dot);
      });
    }

    carousel.classList.add("is-initializing");
    carousel.replaceChildren(track);
    renderSlots();

    previous?.addEventListener("click", () => requestStep(-1));
    next?.addEventListener("click", () => requestStep(1));
    carousel.addEventListener("keydown", (event) => {
      if (event.key === "ArrowLeft" || event.key === "ArrowRight") {
        event.preventDefault();
        requestStep(event.key === "ArrowLeft" ? -1 : 1);
      }
    });
    carousel.addEventListener("touchstart", (event) => { touchStartX = event.changedTouches[0]?.clientX ?? null; }, { passive: true });
    carousel.addEventListener("touchend", (event) => {
      if (touchStartX === null) return;
      const distance = (event.changedTouches[0]?.clientX ?? touchStartX) - touchStartX;
      if (Math.abs(distance) > 45) requestStep(distance < 0 ? 1 : -1);
      touchStartX = null;
    }, { passive: true });
    section?.addEventListener("mouseenter", stopRotation);
    section?.addEventListener("mouseleave", startRotation);
    section?.addEventListener("focusin", stopRotation);
    section?.addEventListener("focusout", startRotation);
    document.addEventListener("visibilitychange", startRotation);
    reduceMotion.addEventListener?.("change", startRotation);
    window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
      carousel.classList.remove("is-initializing");
      startRotation();
    }));
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
