/**
 * Accounting Master – Balance Sheet + Trial Balance
 * Uses the refactored backend APIs.
 */

// Pages are served from `src/pages/`, while `action/` is at project root.
// Use a path relative to the page: `src/pages/...` -> go up two levels to reach project root.
const API_BASE = "../../action";

document.addEventListener("DOMContentLoaded", () => {
  console.log('accounting-master: DOMContentLoaded');
  const today = new Date().toISOString().split("T")[0];
  document.getElementById("fromDate").value = "2026-01-01";
  document.getElementById("toDate").value = today;

  loadHeads();
  loadCOA();
  loadTrialBalance();
  loadBalanceSheet();

  document.getElementById("applyFilter").addEventListener("click", () => {
    loadTrialBalance();
    loadBalanceSheet();
  });

  document.querySelectorAll(".tab-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll(".tab-btn").forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      document.getElementById("tab-trial").classList.add("hidden");
      document.getElementById("tab-balance").classList.add("hidden");
      document.getElementById("tab-" + btn.dataset.tab).classList.remove("hidden");
    });
  });

  document.getElementById("openHeadModal").addEventListener("click", () => {
    document.getElementById("headModal").classList.remove("hidden");
  });
  document.getElementById("openAccountModal").addEventListener("click", () => {
    populateHeadSelectInModal();
    document.getElementById("accountModal").classList.remove("hidden");
  });

  document.getElementById("headForm").addEventListener("submit", saveHead);
  document.getElementById("accountForm").addEventListener("submit", saveAccount);

  // Transaction modal controls
  txnModal = document.getElementById("txnModal");
  openTxnBtn = document.getElementById("openTxnModal");
  closeTxnModal = document.getElementById("closeTxnModal");
  closeTxnBtn = document.getElementById("closeTxnBtn");
  postTxnBtn = document.getElementById("postTxnBtn");
  console.log('txn elements', { txnModal: !!txnModal, openTxnBtn: !!openTxnBtn, closeTxnModal: !!closeTxnModal, closeTxnBtn: !!closeTxnBtn, postTxnBtn: !!postTxnBtn });

  if (openTxnBtn) openTxnBtn.addEventListener("click", openTransactionModal);
  else console.warn('openTxnModal button not found');

  if (closeTxnModal) closeTxnModal.addEventListener("click", hideTransactionModal);
  if (closeTxnBtn) closeTxnBtn.addEventListener("click", hideTransactionModal);
  if (postTxnBtn) postTxnBtn.addEventListener("click", saveTransaction);
});

const typeTagClass = {
  asset: "Assets",
  liability: "Liability",
  equity: "Owner Equity",
  revenue: "Revenue",
  expense: "Expenses",
};

let cachedHeads = [];
let cachedCOA = [];

// Modal elements for transactions
let txnModal, openTxnBtn, closeTxnModal, closeTxnBtn, postTxnBtn;


async function loadHeads() {
  try {
    const res = await fetch(`${API_BASE}/accounting/get_account_heads.php`);
    const json = await res.json();
    if (!json.success) return;
    cachedHeads = json.data;

    const list = document.getElementById("headsList");
    list.innerHTML = "";
    json.data.forEach((h) => {
      list.innerHTML += `
        <div class="flex justify-between items-center px-2 py-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700">
          <div>
            <div class="font-medium text-sm text-gray-800 dark:text-gray-100">${h.name}</div>
            <div class="text-xs text-gray-400">${typeTagClass[h.type] || h.type}</div>
          </div>
          <div class="flex gap-2 text-gray-400">
            <i class="fa-solid ${h.is_locked == 1 ? "fa-lock" : "fa-shield-halved text-red-400"} text-xs"></i>
            <i class="fa-solid fa-eye text-xs"></i>
          </div>
        </div>`;
    });
  } catch (err) {
    console.error("loadHeads error", err);
  }
}

async function loadCOA() {
  try {
    const res = await fetch(`${API_BASE}/accounting/get_coa.php`);
    const json = await res.json();
    if (!json.success) return;
    cachedCOA = json.data;

    const list = document.getElementById("coaList");
    list.innerHTML = "";
    json.data.forEach((c) => {
      list.innerHTML += `
        <div class="flex justify-between items-center px-2 py-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700">
          <div>
            <div class="font-medium text-sm text-gray-800 dark:text-gray-100">${c.name}</div>
            <div class="text-xs text-blue-400"><i class="fa-solid fa-folder text-[10px] mr-1"></i>${c.head_name}</div>
          </div>
          <div class="flex gap-2 text-gray-400">
            <i class="fa-solid ${c.is_locked == 1 ? "fa-lock" : "fa-shield-halved text-red-400"} text-xs"></i>
            <i class="fa-solid fa-eye text-xs"></i>
          </div>
        </div>`;
    });

    // If transaction modal is present, populate selects
    populateTxnAccountSelects();
  } catch (err) {
    console.error("loadCOA error", err);
  }
}

function populateTxnAccountSelects() {
  const debitSel = document.getElementById("debitAccountSelect");
  const creditSel = document.getElementById("creditAccountSelect");
  if (!debitSel || !creditSel) return;
  debitSel.innerHTML = '<option value="">Select Debit Account</option>';
  creditSel.innerHTML = '<option value="">Select Credit Account</option>';
  cachedCOA.forEach((c) => {
    const opt = `<option value="${c.id}">${c.name} (${c.head_name})</option>`;
    debitSel.innerHTML += opt;
    creditSel.innerHTML += opt;
  });
}

function openTransactionModal() {
  const today = new Date().toISOString().split('T')[0];
  const dateEl = document.getElementById('txnDate');
  if (dateEl) dateEl.value = today;
  populateTxnAccountSelects();
  const amt = document.getElementById('txnAmount'); if (amt) amt.value = '';
  const desc = document.getElementById('txnDescription'); if (desc) desc.value = '';
  console.log('openTransactionModal called, txnModal:', !!txnModal);
  if (txnModal) {
    // Ensure it's visible: remove Tailwind hidden and force flex + high z-index
    txnModal.classList.remove('hidden');
    txnModal.classList.add('flex');
    try {
      txnModal.style.display = 'flex';
      txnModal.style.zIndex = '9999';
    } catch (e) {}
    console.log('txnModal opened; className=', txnModal.className, 'style.zIndex=', txnModal.style.zIndex);
    // focus amount input after opening
    setTimeout(() => {
      const a = document.getElementById('txnAmount'); if (a) a.focus();
    }, 50);
  }
}

function hideTransactionModal() {
  console.log('hideTransactionModal called');
  if (!txnModal) return;
  txnModal.classList.add('hidden'); txnModal.classList.remove('flex');
  try { txnModal.style.display = 'none'; txnModal.style.zIndex = ''; } catch (e) {}
}

async function saveTransaction() {
  const date = document.getElementById('txnDate') ? document.getElementById('txnDate').value : '';
  const debitId = document.getElementById('debitAccountSelect') ? document.getElementById('debitAccountSelect').value : '';
  const creditId = document.getElementById('creditAccountSelect') ? document.getElementById('creditAccountSelect').value : '';
  const amount = parseFloat(document.getElementById('txnAmount') ? document.getElementById('txnAmount').value : 0);
  const description = document.getElementById('txnDescription') ? document.getElementById('txnDescription').value : '';

  if (!date || !debitId || !creditId || !amount || amount <= 0) {
    alert('Please fill date, debit, credit and a valid amount.');
    return;
  }

  try {
    const res = await fetch(`${API_BASE}/accounting/add_entry.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ entry_date: date, debit_account_id: debitId, credit_account_id: creditId, amount: amount, description: description })
    });
    const json = await res.json();
    if (json.success) {
      alert(json.message || 'Transaction recorded');
      hideTransactionModal();
      loadTrialBalance();
      loadBalanceSheet();
    } else {
      alert(json.message || 'Failed to record transaction');
    }
  } catch (err) {
    console.error('saveTransaction error', err);
    alert('Server error. See console.');
  }
}

function populateHeadSelectInModal() {
  const sel = document.getElementById("accountHeadSelect");
  sel.innerHTML = `<option value="">-- Select Head --</option>`;
  cachedHeads.forEach((h) => {
    sel.innerHTML += `<option value="${h.id}">${h.name}</option>`;
  });
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
      loadHeads();
      loadCOA();
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
      loadCOA();
    } else {
      alert(json.message || "Failed to add account");
    }
  } catch (err) {
    console.error("saveAccount error", err);
  }
}

async function loadTrialBalance() {
  const from = document.getElementById("fromDate").value;
  const to = document.getElementById("toDate").value;
  try {
    const res = await fetch(`${API_BASE}/accounting/get_trial_balance.php?from=${from}&to=${to}`);
    const json = await res.json();
    const tbody = document.getElementById("trialBalanceBody");
    tbody.innerHTML = "";

    if (!json.success || json.data.length === 0) {
      tbody.innerHTML = `<tr><td colspan="3" class="text-center py-6 text-gray-400">No transactions in this range</td></tr>`;
      return;
    }

    json.data.forEach((row) => {
      tbody.innerHTML += `
        <tr class="border-b dark:border-gray-700">
          <td class="py-2">${row.account_name}</td>
          <td class="py-2 text-right">${row.debit.toFixed(2)}</td>
          <td class="py-2 text-right">${row.credit.toFixed(2)}</td>
        </tr>`;
    });

    tbody.innerHTML += `
      <tr class="border-t-2 dark:border-gray-600 font-bold">
        <td class="py-2">TOTAL</td>
        <td class="py-2 text-right">${json.total_debit.toFixed(2)}</td>
        <td class="py-2 text-right">${json.total_credit.toFixed(2)}</td>
      </tr>`;

    // Revenue / Expenses / Net Profit summary cards
    let revenue = 0, expenses = 0;
    json.data.forEach((row) => {
      const headType = (row.head_type || "").toLowerCase();
      if (headType === "revenue") revenue += row.credit - row.debit;
      if (headType === "expense") expenses += row.debit - row.credit;
    });
    document.getElementById("sumRevenue").textContent = revenue.toFixed(2);
    document.getElementById("sumExpenses").textContent = expenses.toFixed(2);
    document.getElementById("sumNetProfit").textContent = (revenue - expenses).toFixed(2);
  } catch (err) {
    console.error("loadTrialBalance error", err);
  }
}

// =============================================
// BALANCE SHEET – fully fixed
// =============================================
async function loadBalanceSheet() {
  const from = document.getElementById("fromDate").value;
  const to = document.getElementById("toDate").value;
  try {
    const res = await fetch(`${API_BASE}/accounting/get_balance_sheet.php?from=${from}&to=${to}`);
    const json = await res.json();
    if (!json.success) {
      console.warn("Balance sheet API error:", json.message);
      return;
    }

    const assetsDiv = document.getElementById("bsAssets");
    const liabDiv = document.getElementById("bsLiabEquity");
    assetsDiv.innerHTML = "";
    liabDiv.innerHTML = "";

    // --- Assets ---
    json.assets.forEach((a) => {
      const div = document.createElement("div");
      div.className = "flex justify-between px-3 py-2 border-b border-gray-100 dark:border-gray-700";
      div.innerHTML = `<span>${a.account}</span><span>${a.amount.toFixed(2)}</span>`;
      assetsDiv.appendChild(div);
    });

    // --- Liabilities + Equity (combined) ---
    const allRight = [...json.liabilities, ...json.equity];
    allRight.forEach((a) => {
      const div = document.createElement("div");
      div.className = "flex justify-between px-3 py-2 border-b border-gray-100 dark:border-gray-700";
      div.innerHTML = `<span>${a.account}</span><span>${a.amount.toFixed(2)}</span>`;
      liabDiv.appendChild(div);
    });

    // --- Totals ---
    document.getElementById("bsTotalAssets").textContent = json.total_assets.toFixed(2);
    document.getElementById("bsTotalLiabEquity").textContent = json.total_liabilities_equity.toFixed(2);

    // --- Balanced status (add a small note) ---
    const statusDiv = document.createElement("div");
    statusDiv.className = "text-center text-sm font-medium mt-2";
    if (json.balanced) {
      statusDiv.innerHTML = '<span class="text-green-600 dark:text-green-400">✓ Balanced</span>';
    } else {
      statusDiv.innerHTML = '<span class="text-red-600 dark:text-red-400">✗ Not Balanced</span>';
    }
    liabDiv.appendChild(statusDiv);

  } catch (err) {
    console.error("loadBalanceSheet error", err);
    document.getElementById("bsAssets").innerHTML = '<div class="text-red-500 p-2">Error loading balance sheet</div>';
  }
}