(function () {
  "use strict";
  function on(el, event, handler) {
    if (el) el.addEventListener(event, handler);
  }

  document.addEventListener("DOMContentLoaded", function () {
    const API_BASE = "../../action";

    const searchInput = document.getElementById("searchInput");
    const ledgerListBody = document.getElementById("ledgerListBody");

    const viewLedgerModal = document.getElementById("viewLedgerModal");
    const closeViewLedgerModal = document.getElementById("closeViewLedgerModal");
    const vlName = document.getElementById("vlName");
    const vlDesignation = document.getElementById("vlDesignation");
    const vlContact = document.getElementById("vlContact");
    const vlBalance = document.getElementById("vlBalance");
    const vlTableBody = document.getElementById("vlTableBody");

    const payModal = document.getElementById("payModal");
    const payModalHeader = document.getElementById("payModalHeader");
    const payDueAmount = document.getElementById("payDueAmount");
    const payAmountInput = document.getElementById("payAmountInput");
    const payMethodInput = document.getElementById("payMethodInput");
    const payRemarksInput = document.getElementById("payRemarksInput");
    const payEmployeeId = document.getElementById("payEmployeeId");
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

    function fmt(n) {
      return (parseFloat(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    // ---- Load employee list ----
    async function loadList() {
      const search = searchInput.value.trim();
      let url = `${API_BASE}/salary/get_employees_balance.php`;
      if (search) url += `?search=${encodeURIComponent(search)}`;

      ledgerListBody.innerHTML = `<tr><td colspan="7" class="px-3 py-8 text-center text-gray-400">Loading...</td></tr>`;
      const res = await fetchAPI(url);

      if (!res || !res.success) {
        ledgerListBody.innerHTML = `<tr><td colspan="7" class="px-3 py-8 text-center text-red-500">${(res && res.message) || "Failed to load"}</td></tr>`;
        return;
      }

      ledgerListBody.innerHTML = "";
      if (!res.data || res.data.length === 0) {
        ledgerListBody.innerHTML = `<tr><td colspan="7" class="px-3 py-8 text-center text-gray-400">No employees found</td></tr>`;
        return;
      }

      res.data.forEach((emp, idx) => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td class="border dark:border-gray-700 px-3 py-2">${idx + 1}</td>
          <td class="border dark:border-gray-700 px-3 py-2 font-semibold">${emp.full_name}</td>
          <td class="border dark:border-gray-700 px-3 py-2">${emp.designation || "-"}</td>
          <td class="border dark:border-gray-700 px-3 py-2">${emp.contact || "-"}</td>
          <td class="border dark:border-gray-700 px-3 py-2">${emp.shift || "-"}</td>
          <td class="border dark:border-gray-700 px-3 py-2 text-right font-semibold">${fmt(emp.balance)}</td>
          <td class="border dark:border-gray-700 px-3 py-2">
            <div class="flex gap-1">
              <button class="view-btn border dark:border-gray-600 rounded px-2.5 py-1.5 text-xs text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-900/20" data-id="${emp.id}">
                <i class="fa-solid fa-eye"></i> View
              </button>
              <button class="pay-btn border dark:border-gray-600 rounded px-2.5 py-1.5 text-xs text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20" data-id="${emp.id}" data-name="${emp.full_name}" data-balance="${emp.balance}">
                <i class="fa-solid fa-money-bill-wave"></i> Pay
              </button>
              <button class="print-btn border dark:border-gray-600 rounded px-2.5 py-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" data-id="${emp.id}">
                <i class="fa-solid fa-print"></i>
              </button>
            </div>
          </td>
        `;
        ledgerListBody.appendChild(tr);
      });
    }

    // ---- View ledger modal ----
    async function openViewLedger(employeeId) {
      const res = await fetchAPI(`${API_BASE}/salary/get_employee_ledger.php?employee_id=${employeeId}`);
      if (!res || !res.success) {
        alert((res && res.message) || "Failed to load ledger");
        return;
      }
      const emp = res.employee;
      vlName.textContent = emp.full_name;
      vlDesignation.textContent = emp.designation || "-";
      vlContact.textContent = emp.contact || "-";
      vlBalance.textContent = fmt(res.closing);

      vlTableBody.innerHTML = "";
      if (!res.rows || res.rows.length === 0) {
        vlTableBody.innerHTML = `<tr><td colspan="6" class="border px-3 py-5 text-center text-gray-400">No ledger entries found</td></tr>`;
      } else {
        res.rows.forEach((row) => {
          vlTableBody.innerHTML += `
            <tr>
              <td class="border dark:border-gray-700 px-3 py-2">${row.sr}</td>
              <td class="border dark:border-gray-700 px-3 py-2">${row.date}</td>
              <td class="border dark:border-gray-700 px-3 py-2">${row.description}</td>
              <td class="border dark:border-gray-700 px-3 py-2 text-right text-red-500">${fmt(row.debit)}</td>
              <td class="border dark:border-gray-700 px-3 py-2 text-right text-emerald-600">${fmt(row.credit)}</td>
              <td class="border dark:border-gray-700 px-3 py-2 text-right font-semibold">${fmt(row.balance)}</td>
            </tr>
          `;
        });
      }

      viewLedgerModal.classList.remove("hidden");
      viewLedgerModal.classList.add("flex");
    }

    function closeViewLedger() {
      viewLedgerModal.classList.add("hidden");
      viewLedgerModal.classList.remove("flex");
    }

    // ---- Pay modal ----
    function openPayModal(employeeId, name, balance) {
      payEmployeeId.value = employeeId;
      payModalHeader.textContent = `Pay ${name}`;
      payDueAmount.textContent = fmt(balance);
      payAmountInput.value = balance;
      payMethodInput.value = "Cash";
      payRemarksInput.value = "";
      payModal.classList.remove("hidden");
      payModal.classList.add("flex");
    }

    function closePayModal() {
      payModal.classList.add("hidden");
      payModal.classList.remove("flex");
    }

    async function submitPayment() {
      const employeeId = payEmployeeId.value;
      const amount = parseFloat(payAmountInput.value);
      if (!amount || amount <= 0) {
        alert("Please enter a valid amount.");
        return;
      }
      paySubmitBtn.disabled = true;
      paySubmitBtn.textContent = "Saving...";

      const res = await fetchAPI(`${API_BASE}/salary/add_ledger_payment.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          employee_id: employeeId,
          amount: amount,
          method: payMethodInput.value,
          remarks: payRemarksInput.value,
        }),
      });

      paySubmitBtn.disabled = false;
      paySubmitBtn.textContent = "Submit Payment";

      if (res && res.success) {
        alert(res.message || "Payment recorded.");
        closePayModal();
        loadList();
      } else {
        alert((res && res.message) || "Payment failed.");
      }
    }

    // ---- Event delegation ----
    on(ledgerListBody, "click", function (e) {
      const viewBtn = e.target.closest(".view-btn");
      if (viewBtn) {
        openViewLedger(viewBtn.dataset.id);
        return;
      }
      const payBtn = e.target.closest(".pay-btn");
      if (payBtn) {
        openPayModal(payBtn.dataset.id, payBtn.dataset.name, payBtn.dataset.balance);
        return;
      }
      const printBtn = e.target.closest(".print-btn");
      if (printBtn) {
        window.open(`./print-ledger.html?employee_id=${printBtn.dataset.id}`, "_blank");
      }
    });

    on(closeViewLedgerModal, "click", closeViewLedger);
    on(viewLedgerModal, "click", (e) => { if (e.target === viewLedgerModal) closeViewLedger(); });

    on(payCancelBtn, "click", closePayModal);
    on(payModal, "click", (e) => { if (e.target === payModal) closePayModal(); });
    on(paySubmitBtn, "click", submitPayment);

    on(searchInput, "keyup", loadList);

    loadList();
  });
})();
