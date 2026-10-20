(function () {
  "use strict";

  // ---- API endpoints ----
  const GET_PARTIES_API = "../../action/parties/get_party.php";
  const UPDATE_BALANCE_API = "../../action/parties/update_balance.php";

  // ---- DOM refs ----
  const categorySelect = document.getElementById("category");
  const partySelect = document.getElementById("partySelect");
  const payDateInput = document.getElementById("payDate");
  const actionSelect = document.getElementById("action");
  const amountInput = document.getElementById("amount");
  const descriptionInput = document.getElementById("description");
  const closingBalanceEl = document.getElementById("closingBalance");
  const ledgerBalanceEl = document.getElementById("ledgerBalance");
  const saveBtn = document.getElementById("saveBtn");
  const globalLoadingOverlay = document.getElementById("globalLoadingOverlay");
  const themeToggle = document.getElementById("themeToggle");
  const themeIcon = document.getElementById("themeIcon");

  // ---- State ----
  let parties = [];

  // ---- Helpers ----
  function money(value) {
    const num = parseFloat(value);
    return (isNaN(num) ? 0 : num).toFixed(2);
  }

  function showLoading(show) {
    globalLoadingOverlay.classList.toggle("hidden", !show);
  }

  async function fetchAPI(url, options = {}) {
    try {
      const res = await fetch(url, options);
      if (!res.ok) throw new Error("Network error: " + res.status);
      return await res.json();
    } catch (err) {
      console.error("API Error:", url, err);
      return null;
    }
  }

  function unwrapList(response) {
    if (Array.isArray(response)) return response;
    if (response && Array.isArray(response.data)) return response.data;
    return [];
  }

  // ---- Theme (dark/light) ----
  function applyTheme(isDark) {
    document.documentElement.classList.toggle("dark", isDark);
    themeIcon.className = isDark ? "fa-solid fa-moon" : "fa-solid fa-sun";
    localStorage.setItem("pp_theme", isDark ? "dark" : "light");
  }

  function initTheme() {
    const saved = localStorage.getItem("pp_theme");
    const isDark = saved ? saved === "dark" : true; // default dark, matches screenshot's class="dark" base
    applyTheme(isDark);
  }

  themeToggle.addEventListener("click", function () {
    const isDark = !document.documentElement.classList.contains("dark");
    applyTheme(isDark);
  });

  // ---- Load Parties ----
  async function loadParties() {
    showLoading(true);
    const response = await fetchAPI(GET_PARTIES_API);
    showLoading(false);

    if (!response) {
      alert("Failed to load parties.");
      return;
    }

    parties = unwrapList(response);
    populatePartySelect();
  }

  function populatePartySelect() {
    partySelect.innerHTML = '<option value="">-- Select --</option>';
    parties.forEach((p) => {
      const opt = document.createElement("option");
      opt.value = p.id;
      opt.textContent = p.party_name || `Party #${p.id}`;
      partySelect.appendChild(opt);
    });
  }

  // ---- Ledger balance display ----
  function getSelectedParty() {
    const id = parseInt(partySelect.value, 10);
    if (!id) return null;
    return parties.find((p) => Number(p.id) === id) || null;
  }

  function getCurrentLedgerBalance() {
    const party = getSelectedParty();
    if (!party) return 0;
    const balanceType = categorySelect.value; // 'receivable' | 'payable'
    return parseFloat(party[balanceType + "_balance"]) || 0;
  }

  function updateLedgerDisplay() {
    const current = getCurrentLedgerBalance();
    ledgerBalanceEl.textContent = money(current);
    updateClosingBalancePreview();
  }

  function updateClosingBalancePreview() {
    const current = getCurrentLedgerBalance();
    const amount = parseFloat(amountInput.value) || 0;
    const operation = actionSelect.value; // 'add' | 'subtract'
    const closing = operation === "add" ? current + amount : current - amount;
    closingBalanceEl.value = money(closing);
  }

  // ---- Save Transaction ----
  async function saveTransaction() {
    const party = getSelectedParty();
    if (!party) {
      alert("Please select a party.");
      return;
    }

    const amount = parseFloat(amountInput.value);
    if (!amount || amount <= 0) {
      alert("Please enter a valid amount.");
      return;
    }

    const payload = {
      id: party.id,
      balance_type: categorySelect.value, // 'receivable' | 'payable'
      amount: amount,
      operation: actionSelect.value, // 'add' | 'subtract'
    };

    showLoading(true);
    try {
      const response = await fetch(UPDATE_BALANCE_API, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const result = await response.json();
      showLoading(false);

      if (result.error) {
        alert(result.error);
        return;
      }

      alert(result.message || "Balance updated successfully.");

      // Update local party record with fresh balance from server
      if (result.party) {
        const idx = parties.findIndex((p) => Number(p.id) === Number(result.party.id));
        if (idx !== -1) parties[idx] = result.party;
      }

      resetForm();
      updateLedgerDisplay();
    } catch (err) {
      showLoading(false);
      console.error(err);
      alert("Server error. Please try again.");
    }
  }

  function resetForm() {
    amountInput.value = "";
    descriptionInput.value = "";
  }

  // ---- Event listeners ----
  categorySelect.addEventListener("change", updateLedgerDisplay);
  partySelect.addEventListener("change", updateLedgerDisplay);
  actionSelect.addEventListener("change", updateClosingBalancePreview);
  amountInput.addEventListener("input", updateClosingBalancePreview);
  saveBtn.addEventListener("click", saveTransaction);

  // ---- Init ----
  document.addEventListener("DOMContentLoaded", function () {
    initTheme();
    payDateInput.value = new Date().toISOString().slice(0, 10);
    loadParties();
  });
})();