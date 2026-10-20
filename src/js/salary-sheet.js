(function () {
  "use strict";
  function on(el, event, handler) {
    if (el) el.addEventListener(event, handler);
  }

  document.addEventListener("DOMContentLoaded", function () {
    const API_BASE = "../../action";

    const searchInput = document.getElementById("searchInput");
    const periodInput = document.getElementById("periodInput");
    const periodLabel = document.getElementById("periodLabel");
    const filterBtn = document.getElementById("filterBtn");
    const printSheetBtn = document.getElementById("printSheetBtn");
    const salarySheetBody = document.getElementById("salarySheetBody");

    const payModal = document.getElementById("payModal");
    const payDueAmount = document.getElementById("payDueAmount");
    const payAmountInput = document.getElementById("payAmountInput");
    const payMethodInput = document.getElementById("payMethodInput");
    const paySalaryId = document.getElementById("paySalaryId");
    const payCancelBtn = document.getElementById("payCancelBtn");
    const paySubmitBtn = document.getElementById("paySubmitBtn");

    async function fetchAPI(url, options = {}) {
      try {
        const res = await fetch(url, options);
        return await res.json();
      } catch (e) {
        console.error("API Error:", e);
        return { success: false, message: "Network error" };
      }
    }

    function currentPeriod() {
      const now = new Date();
      return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
    }

    function periodDisplay(period) {
      if (!period) return "-";
      const [y, m] = period.split("-");
      const d = new Date(parseInt(y), parseInt(m) - 1, 1);
      return d.toLocaleString("en-US", { month: "long", year: "numeric" });
    }

    function statusClass(status) {
      if (status === "Paid") return "status-paid";
      if (status === "Partial") return "status-partial";
      return "status-pending";
    }

    function fmt(n) {
      return (parseFloat(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    async function loadSheet() {
      const period = periodInput.value || currentPeriod();
      const search = searchInput.value.trim();
      periodLabel.textContent = periodDisplay(period);

      let url = `${API_BASE}/salary/get_salary_sheet.php?period=${period}`;
      if (search) url += `&search=${encodeURIComponent(search)}`;

      salarySheetBody.innerHTML = `<tr><td colspan="9" class="px-3 py-8 text-center text-gray-400">Loading...</td></tr>`;
      const res = await fetchAPI(url);

      if (!res || !res.success) {
        salarySheetBody.innerHTML = `<tr><td colspan="9" class="px-3 py-8 text-center text-red-500">${(res && res.message) || "Failed to load"}</td></tr>`;
        return;
      }

      salarySheetBody.innerHTML = "";

      if (!res.data || res.data.length === 0) {
        salarySheetBody.innerHTML = `<tr><td colspan="9" class="px-3 py-8 text-center text-gray-400">No salary records found for this period.</td></tr>`;
      } else {
        res.data.forEach((r, idx) => {
          const due = parseFloat(r.net_payable) - parseFloat(r.paid_amount);
          const tr = document.createElement("tr");
          tr.className = "border-b dark:border-gray-700";
          tr.innerHTML = `
            <td class="px-3 py-2">${idx + 1}</td>
            <td class="px-3 py-2">
              <div class="font-semibold text-gray-800 dark:text-gray-200">${r.full_name}</div>
              <div class="text-xs text-gray-400">#${r.employee_id}</div>
            </td>
            <td class="px-3 py-2 text-right">${fmt(r.basic)}</td>
            <td class="px-3 py-2">
              <span class="text-red-500">${fmt(r.deduction)}</span>
              <div class="text-xs text-gray-400">${r.deduction_reason || "-"}</div>
            </td>
            <td class="px-3 py-2 text-right text-emerald-600">${fmt(r.incentive)}</td>
            <td class="px-3 py-2 text-right font-semibold">${fmt(r.net_payable)}</td>
            <td class="px-3 py-2 text-right text-blue-600">${fmt(r.paid_amount)}</td>
            <td class="px-3 py-2"><span class="status-badge ${statusClass(r.status)}">${r.status.toUpperCase()}</span></td>
            <td class="px-3 py-2">
              <div class="flex gap-1">
                ${
                  r.status !== "Paid"
                    ? `<button class="pay-btn border dark:border-gray-600 rounded px-3 py-1.5 text-xs text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20" data-id="${r.id}" data-due="${due}">Pay</button>`
                    : ""
                }
                <button class="print-btn border dark:border-gray-600 rounded px-3 py-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" data-id="${r.id}">
                  <i class="fa-solid fa-print"></i>
                </button>
              </div>
            </td>
          `;
          salarySheetBody.appendChild(tr);
        });
      }

      if (res.totals) {
        document.getElementById("totalBasic").textContent = fmt(res.totals.basic);
        document.getElementById("totalDeduction").textContent = fmt(res.totals.deduction);
        document.getElementById("totalIncentive").textContent = fmt(res.totals.incentive);
        document.getElementById("totalGross").textContent = fmt(res.totals.gross);
        document.getElementById("totalPaid").textContent = fmt(res.totals.paid);
      }
    }

    // ---- Pay modal ----
    function openPayModal(salaryId, due) {
      paySalaryId.value = salaryId;
      payDueAmount.textContent = fmt(due);
      payAmountInput.value = due;
      payMethodInput.value = "Cash";
      payModal.classList.remove("hidden");
      payModal.classList.add("flex");
    }

    function closePayModal() {
      payModal.classList.add("hidden");
      payModal.classList.remove("flex");
    }

    async function submitPayment() {
      const salaryId = paySalaryId.value;
      const amount = parseFloat(payAmountInput.value);
      if (!amount || amount <= 0) {
        alert("Please enter a valid amount.");
        return;
      }
      paySubmitBtn.disabled = true;
      paySubmitBtn.textContent = "Saving...";

      const res = await fetchAPI(`${API_BASE}/salary/pay_salary.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          salary_id: salaryId,
          amount: amount,
          method: payMethodInput.value,
        }),
      });

      paySubmitBtn.disabled = false;
      paySubmitBtn.textContent = "Submit Payment";

      if (res && res.success) {
        alert(res.message || "Payment recorded.");
        closePayModal();
        loadSheet();
      } else {
        alert((res && res.message) || "Payment failed.");
      }
    }

    // ---- Table action delegation ----
    on(salarySheetBody, "click", function (e) {
      const payBtn = e.target.closest(".pay-btn");
      if (payBtn) {
        openPayModal(payBtn.dataset.id, payBtn.dataset.due);
        return;
      }
      const printBtn = e.target.closest(".print-btn");
      if (printBtn) {
        window.open(`./print-salary-slip.html?id=${printBtn.dataset.id}`, "_blank");
      }
    });

    on(payCancelBtn, "click", closePayModal);
    on(payModal, "click", (e) => { if (e.target === payModal) closePayModal(); });
    on(paySubmitBtn, "click", submitPayment);

    on(filterBtn, "click", loadSheet);
    on(periodInput, "change", loadSheet);
    on(searchInput, "keyup", loadSheet);
    on(printSheetBtn, "click", () => {
      const period = periodInput.value || currentPeriod();
      window.print();
    });

    periodInput.value = currentPeriod();
    loadSheet();
  });
})();
