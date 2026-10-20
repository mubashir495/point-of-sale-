(function () {
  "use strict";
  function on(el, event, handler) {
    if (el) el.addEventListener(event, handler);
  }

  document.addEventListener("DOMContentLoaded", function () {
    const API_BASE = "../../action";
    const periodInput = document.getElementById("periodInput");
    const payrollTableBody = document.getElementById("payrollTableBody");
    const generateBtn = document.getElementById("generateBtn");
    const generateBtnText = document.getElementById("generateBtnText");

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

    function calcRowNet(row) {
      const basic = parseFloat(row.querySelector(".basic-val").dataset.basic) || 0;
      const incentive = parseFloat(row.querySelector(".incentive-input").value) || 0;
      const deduction = parseFloat(row.querySelector(".deduction-input").value) || 0;
      const net = basic + incentive - deduction;
      row.querySelector(".net-val").textContent = net.toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
      });
      return net;
    }

    function renderRow(emp) {
      const tr = document.createElement("tr");
      tr.className = "border-b dark:border-gray-700";
      tr.dataset.employeeId = emp.id;
      const basic = parseFloat(emp.basic) || 0;

      tr.innerHTML = `
        <td class="py-3 pr-3">
          <div class="font-semibold text-gray-800 dark:text-gray-200">${emp.full_name}</div>
          <div class="text-xs text-gray-400">#${emp.id} — ${emp.designation || "-"}</div>
        </td>
        <td class="py-3 pr-3 basic-val font-medium" data-basic="${basic}">${basic.toLocaleString()}</td>
        <td class="py-3 pr-3">
          <input type="number" class="incentive-input w-24 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1.5 text-sm" value="0" min="0" step="0.01" />
        </td>
        <td class="py-3 pr-3">
          <input type="number" class="deduction-input w-24 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1.5 text-sm" value="0" min="0" step="0.01" />
        </td>
        <td class="py-3 pr-3">
          <input type="text" class="reason-input w-32 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1.5 text-sm" placeholder="..." />
        </td>
        <td class="py-3 pr-3 font-bold text-blue-600 dark:text-blue-400 net-val">${basic.toLocaleString()}</td>
        <td class="py-3 pr-3">
          <input type="text" class="remarks-input w-32 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1.5 text-sm" placeholder="..." />
        </td>
      `;

      const incentiveInput = tr.querySelector(".incentive-input");
      const deductionInput = tr.querySelector(".deduction-input");
      on(incentiveInput, "input", () => calcRowNet(tr));
      on(deductionInput, "input", () => calcRowNet(tr));

      return tr;
    }

    async function loadPeriodEmployees() {
      const period = periodInput.value || currentPeriod();
      payrollTableBody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-gray-400">Loading...</td></tr>`;

      const res = await fetchAPI(`${API_BASE}/salary/get_period_employees.php?period=${period}`);
      payrollTableBody.innerHTML = "";

      if (!res || !res.success) {
        payrollTableBody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-red-500">${res.message || "Failed to load employees"}</td></tr>`;
        return;
      }

      if (!res.data || res.data.length === 0) {
        payrollTableBody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-gray-400">All employees already have a salary record for this period.</td></tr>`;
        return;
      }

      res.data.forEach((emp) => {
        payrollTableBody.appendChild(renderRow(emp));
      });
    }

    async function generateSalaries() {
      const period = periodInput.value || currentPeriod();
      const rows = payrollTableBody.querySelectorAll("tr[data-employee-id]");
      if (rows.length === 0) {
        alert("noting emmployees have been processed.");
        return;
      }

      const employees = [];
      rows.forEach((row) => {
        const basic = parseFloat(row.querySelector(".basic-val").dataset.basic) || 0;
       employees.push({
    employee_id: Number(row.dataset.employeeId),
    basic: basic,
    incentive: parseFloat(row.querySelector(".incentive-input").value) || 0,
    deduction: parseFloat(row.querySelector(".deduction-input").value) || 0,
    deduction_reason: row.querySelector(".reason-input").value || "",
    remarks: row.querySelector(".remarks-input").value || "",
});
      });

      generateBtn.disabled = true;
      generateBtnText.textContent = "Generating...";

      const res = await fetchAPI(`${API_BASE}/salary/generate_salary.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ period, employees }),
      });

      generateBtn.disabled = false;
      generateBtnText.textContent = "Generate & Save All";

      if (res && res.success) {
        alert(res.message || "Salaries generated successfully.");
        loadPeriodEmployees();
      } else {
        alert((res && res.message) || "Failed to generate salaries.");
      }
    }

    on(periodInput, "change", loadPeriodEmployees);
    on(generateBtn, "click", generateSalaries);

    periodInput.value = currentPeriod();
    loadPeriodEmployees();
  });
})();
