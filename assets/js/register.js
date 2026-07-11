/* ==========================================================================
   CYWater · registration modal — multistep
   Drives the 3-step event registration prototype (details → ticket → confirm).
   ========================================================================== */

(function () {
  "use strict";

  let step = 1;
  const total = 3;
  let formData = {};

  function ready(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else fn();
  }

  function setStep(n) {
    step = n;
    // panels
    document.querySelectorAll("#regModal .form-step").forEach((p) => {
      p.classList.toggle("is-active", Number(p.dataset.step) === n);
    });
    // progress
    document.querySelectorAll("#regModal .form-progress span").forEach((s, i) => {
      s.classList.toggle("is-active", i + 1 === n);
      s.classList.toggle("is-done", i + 1 < n);
    });
    // nav buttons
    const prev = document.querySelector("#regModal .js-prev");
    const next = document.querySelector("#regModal .js-next");
    const done = document.querySelector("#regModal .js-done");
    if (prev) prev.style.visibility = n === 1 ? "hidden" : "visible";
    if (next) next.style.display = n < total ? "inline-flex" : "none";
    if (done) done.style.display = n > total ? "inline-flex" : "none";
  }

  function collect() {
    document.querySelectorAll("#regModal [name]").forEach((el) => {
      formData[el.name] = el.value;
    });
  }

  function validateStep(n) {
    const panel = document.querySelector(`#regModal .form-step[data-step="${n}"]`);
    if (!panel) return true;
    let ok = true;
    panel.querySelectorAll("[required]").forEach((el) => {
      const valid = el.checkValidity();
      el.style.borderColor = valid ? "" : "#B23A2E";
      if (!valid) ok = false;
    });
    return ok;
  }

  function renderConfirm() {
    const name = (formData.firstName || "") + " " + (formData.lastName || "");
    const ticketMap = {
      member: window.CYWaterI18N?.t("reg.ticketMember") || "Member",
      nonmember: window.CYWaterI18N?.t("reg.ticketNonMember") || "Non-member",
      student: window.CYWaterI18N?.t("reg.ticketStudent") || "Student",
    };
    const ticketLabel = ticketMap[formData.ticket] || formData.ticket || "—";
    const c = document.querySelector("#regModal .js-confirm-summary");
    if (!c) return;
    c.innerHTML = `
      <div class="summary-row"><span data-i18n="reg.firstName"></span><strong>${escapeHtml(formData.firstName || "—")}</strong></div>
      <div class="summary-row"><span data-i18n="reg.lastName"></span><strong>${escapeHtml(formData.lastName || "—")}</strong></div>
      <div class="summary-row"><span data-i18n="reg.email"></span><strong>${escapeHtml(formData.email || "—")}</strong></div>
      <div class="summary-row"><span data-i18n="reg.org"></span><strong>${escapeHtml(formData.org || "—")}</strong></div>
      <div class="summary-row"><span data-i18n="reg.ticket"></span><strong>${escapeHtml(ticketLabel)}</strong></div>
    `;
    if (window.CYWaterI18N) window.CYWaterI18N.applyTranslations();
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])
    );
  }

  function resetWizard() {
    step = 1;
    formData = {};
    const form = document.querySelector("#regModal form");
    if (form) form.reset();
    document.querySelectorAll("#regModal [required]").forEach((el) => (el.style.borderColor = ""));
    document.querySelector("#regModal .js-success")?.classList.remove("is-shown");
    document.querySelector("#regModal .js-wizard")?.classList.remove("is-hidden");
    setStep(1);
  }

  ready(function () {
    const modal = document.getElementById("regModal");
    if (!modal) return;

    const next = modal.querySelector(".js-next");
    const prev = modal.querySelector(".js-prev");
    const done = modal.querySelector(".js-done");

    if (next) {
      next.addEventListener("click", () => {
        if (!validateStep(step)) return;
        collect();
        if (step < total) {
          setStep(step + 1);
          if (step === total) renderConfirm();
        }
      });
    }
    if (prev) prev.addEventListener("click", () => step > 1 && setStep(step - 1));
    if (done) {
      done.addEventListener("click", () => {
        collect();
        modal.querySelector(".js-wizard")?.classList.add("is-hidden");
        modal.querySelector(".js-success")?.classList.add("is-shown");
        const msg = window.CYWaterI18N?.t("reg.confirmation") || "You're registered!";
        window.CYWaterToast?.(msg);
      });
    }

    // reset wizard whenever the modal is opened (observe class changes)
    const obs = new MutationObserver(() => {
      if (modal.classList.contains("is-open")) resetWizard();
    });
    obs.observe(modal, { attributes: true, attributeFilter: ["class"] });
  });
})();
