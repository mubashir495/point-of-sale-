const API_BASE = "../../action";

let allHeads = [];
let allAccounts = [];

document.addEventListener("DOMContentLoaded", () => {
  const today = new Date().toISOString().split("T")[0];
  document.getElementById("expenseDate").value = today;

  const firstOfMonth = new Date();
  firstOfMonth.setDate(1);
  document.getElementById("filterFrom").value = firstOfMonth.toISOString().split("T")[0];
  document.getElementById("filterTo").value = today;

  loadHeads();
  loadAccounts();
  loadExpenses();
console.log({
    head_id: document.getElementById("expenseHead").value,
    dr_account_id: document.getElementById("expenseAccount").value,
    cr_account_id: document.getElementById("cashBankAccount").value
});
  document.getElementById("expenseForm").addEventListener("submit", saveExpense);
  document.getElementById("headForm").addEventListener("submit", saveHead);
  document.getElementById("accountForm").addEventListener("submit", saveAccount);
  document.getElementById("searchBtn").addEventListener("click", loadExpenses);

  document.getElementById("openHeadModal").addEventListener("click", () => {
    document.getElementById("headModal").classList.remove("hidden");
  });
  document.getElementById("openAccountModal").addEventListener("click", () => {
    populateHeadSelectInModal();
    document.getElementById("accountModal").classList.remove("hidden");
  });

  // Expense Head dropdown -> filter Expense Account dropdown to that head
  document.getElementById("expenseHead").addEventListener("change", (e) => {
    populateAccountDropdown(e.target.value);
  });
});

async function loadHeads() {
  try {
    const res = await fetch(`${API_BASE}/accounting/get_account_heads.php`);
    const json = await res.json();
    if (!json.success) return;
    allHeads = json.data;

    const headSelect = document.getElementById("expenseHead");
    const filterHead = document.getElementById("filterHead");
    headSelect.innerHTML = `<option value="">Select Category</option>`;
    filterHead.innerHTML = `<option value="">All Heads</option>`;

    allHeads.forEach((h) => {
      headSelect.innerHTML += `<option value="${h.id}">${h.name}</option>`;
      filterHead.innerHTML += `<option value="${h.id}">${h.name}</option>`;
    });
  } catch (err) {
    console.error("loadHeads error", err);
  }
}

async function loadAccounts() {
  try {
    const res = await fetch(`${API_BASE}/accounting/get_coa.php`);
    const json = await res.json();
    if (!json.success) return;
    allAccounts = json.data;

    const filterAccount = document.getElementById("filterAccount");
    filterAccount.innerHTML = `<option value="">All Accounts</option>`;
    allAccounts.forEach((a) => {
      filterAccount.innerHTML += `<option value="${a.id}">${a.name}</option>`;
    });

    // Cash/Bank dropdown -> only accounts flagged is_cash_bank
    const cashBankSelect = document.getElementById("cashBankAccount");
    cashBankSelect.innerHTML = `<option value="">-- Choose Account --</option>`;
    allAccounts
      .filter((a) => a.is_cash_bank == 1)
      .forEach((a) => {
        cashBankSelect.innerHTML += `<option value="${a.id}">${a.name}</option>`;
      });
  } catch (err) {
    console.error("loadAccounts error", err);
  }
}

function populateAccountDropdown(headId) {
  const accountSelect = document.getElementById("expenseAccount");
  accountSelect.innerHTML = `<option value="">-- Choose Account --</option>`;
  allAccounts
    .filter((a) => String(a.head_id) === String(headId))
    .forEach((a) => {
      accountSelect.innerHTML += `<option value="${a.id}">${a.name}</option>`;
    });
}

function populateHeadSelectInModal() {
  const sel = document.getElementById("accountHeadSelect");
  sel.innerHTML = `<option value="">-- Select Head --</option>`;
  allHeads.forEach((h) => {
    sel.innerHTML += `<option value="${h.id}">${h.name}</option>`;
  });
}

async function saveExpense(e) {
  alert("saveExpense called");
    e.preventDefault();

    console.log("Function called");
  const payload = {
    expense_date: document.getElementById("expenseDate").value,
    head_id: document.getElementById("expenseHead").value,
    dr_account_id: document.getElementById("expenseAccount").value,
    cr_account_id: document.getElementById("cashBankAccount").value,
    amount: document.getElementById("expenseAmount").value,
    description: document.getElementById("expenseDescription").value,
};

console.log(payload);

  try {
    const res = await fetch(`${API_BASE}/expenses/add_expense.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const json = await res.json();
    if (json.success) {
      document.getElementById("expenseForm").reset();
      document.getElementById("expenseDate").value = new Date().toISOString().split("T")[0];
      loadExpenses();
    } else {
      alert(json.message || "Failed to save expense");
    }
  } catch (err) {
    console.error("saveExpense error", err);
    alert("Server error while saving expense");
  }
}

async function saveHead(e) {
  e.preventDefault();
  const payload = {
    name: document.getElementById("headName").value,
    type: document.getElementById("headType").value,
  };
  try {
    const res = await fetch(`${API_BASE}/accounting/add_account_head.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const json = await res.json();
    if (json.success) {
      document.getElementById("headForm").reset();
      document.getElementById("headModal").classList.add("hidden");
      await loadHeads();
      await loadAccounts();
    } else {
      alert(json.message || "Failed to add head");
    }
  } catch (err) {
    console.error("saveHead error", err);
  }
}

async function saveAccount(e) {
  e.preventDefault();
  const payload = {
    head_id: document.getElementById("accountHeadSelect").value,
    name: document.getElementById("accountName").value,
    is_cash_bank: document.getElementById("isCashBank").checked,
  };
  try {
    const res = await fetch(`${API_BASE}/accounting/add_coa.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const json = await res.json();
    if (json.success) {
      document.getElementById("accountForm").reset();
      document.getElementById("accountModal").classList.add("hidden");
      await loadAccounts();
    } else {
      alert(json.message || "Failed to add account");
    }
  } catch (err) {
    console.error("saveAccount error", err);
  }
}

async function loadExpenses() {
  const from = document.getElementById("filterFrom").value;
  const to = document.getElementById("filterTo").value;
  const headId = document.getElementById("filterHead").value;
  const accId = document.getElementById("filterAccount").value;

  const params = new URLSearchParams({ from, to });
  if (headId) params.append("head_id", headId);
  if (accId) params.append("account_id", accId);

  try {
    const res = await fetch(`${API_BASE}/expenses/get_expenses.php?${params.toString()}`);
    const json = await res.json();
    const tbody = document.getElementById("expenseTableBody");
    tbody.innerHTML = "";

    if (!json.success || json.data.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-center py-6 text-gray-400">No expenses found</td></tr>`;
      document.getElementById("totalExpenses").textContent = "0.00";
      return;
    }

    json.data.forEach((exp) => {
      tbody.innerHTML += `
        <tr class="border-b dark:border-gray-700">
          <td class="py-2">${formatDate(exp.expense_date)}</td>
          <td class="py-2">${exp.dr_account_name}</td>
          <td class="py-2">${exp.description || "-"}</td>
          <td class="py-2 text-right">${parseFloat(exp.amount).toFixed(2)}</td>
          <td class="py-2 text-center">
            <button onclick="deleteExpense(${exp.id})" class="text-red-500 hover:text-red-700">
              <i class="fa-solid fa-trash"></i>
            </button>
          </td>
        </tr>`;
    });

    document.getElementById("totalExpenses").textContent = parseFloat(json.total_expenses).toFixed(2);
  } catch (err) {
    console.error("loadExpenses error", err);
  }
}

async function deleteExpense(id) {
  if (!confirm("Delete this expense?")) return;
  try {
    const res = await fetch(`${API_BASE}/expenses/delete_expense.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id }),
    });
    const json = await res.json();
    if (json.success) loadExpenses();
    else alert(json.message || "Failed to delete");
  } catch (err) {
    console.error("deleteExpense error", err);
  }
}

function formatDate(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleDateString("en-GB"); // DD/MM/YYYY
}
