(function () {
    "use strict";
    

    // ---- API endpoints ----
    const PARTIES_API = "../../action/raw_material_purchase/get_parties.php";
    const PURCHASE_API = "../../action/raw_material_purchase/add_purchase.php";
    const PURCHASE_HISTORY_API = "../../action/raw_material_purchase/get_purchases.php";
    const INVOICE_API = "../../action/raw_material_purchase/get_purchase_invoice.php";
    const LEDGER_API = "../../action/raw_material_purchase/get_party_ledger.php";

    // ---- DOM refs ----
    const partySelect = document.getElementById("partySelect");
    const purchaseDateInput = document.getElementById("purchaseDate");
    const itemsContainer = document.getElementById("itemsContainer");
    const itemRowTemplate = document.getElementById("itemRowTemplate");
    const grandTotalEl = document.getElementById("grandTotal");
    const paidAmountInput = document.getElementById("paidAmount");
    const remainingBalanceEl = document.getElementById("remainingBalance");
    const statusBadgeEl = document.getElementById("statusBadge");
    const remarksInput = document.getElementById("remarks");
    const purchaseTableBody = document.getElementById("purchaseTableBody");

    const globalLoadingOverlay = document.getElementById("globalLoadingOverlay");
    const invoiceModal = document.getElementById("invoiceModal");
    const invoiceDataContainer = document.getElementById("invoiceDataContainer");
    const ledgerModal = document.getElementById("ledgerModal");
    const ledgerDataContainer = document.getElementById("ledgerDataContainer");

    // ---- State ----
    let parties = [];
    let partyItemsMap = new Map(); // partyId -> items array
    let currentPartyId = null;

    // ---- Helpers ----
    function unwrapList(response) {
        if (Array.isArray(response)) return response;
        if (response && Array.isArray(response.data)) return response.data;
        return [];
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

    function money(value) {
        const num = parseFloat(value);
        return (isNaN(num) ? 0 : num).toFixed(2);
    }

    function statusBadge(status) {
        const map = {
            paid: "status-paid",
            partial: "status-partial",
            unpaid: "status-unpaid",
        };
        const cls = map[status] || "status-unpaid";
        const label = status ? status.charAt(0).toUpperCase() + status.slice(1) : "Unknown";
        return `<span class="status-badge ${cls}">${label}</span>`;
    }

    function showLoading(show) {
        if (show) {
            globalLoadingOverlay.classList.remove("hidden");
        } else {
            globalLoadingOverlay.classList.add("hidden");
        }
    }

    // ---- Load Parties ----
 async function loadParties() {
    showLoading(true);
    const response = await fetchAPI(PARTIES_API);
    showLoading(false);

    if (!response) {
        alert("Failed to load parties.");
        return;
    }

    parties = unwrapList(response);

    populatePartySelect();

    partyItemsMap.clear();

    parties.forEach(p => {
        partyItemsMap.set(
            Number(p.id),
            p.items || []
        );
    });
}

    function populatePartySelect() {
        partySelect.innerHTML = '<option value="">-- Select Party --</option>';
        parties.forEach(p => {
            const opt = document.createElement("option");
            opt.value = p.id;
            opt.textContent = p.party_name || `Party #${p.id}`;
            partySelect.appendChild(opt);
        });
    }

    // ---- Party change ----
    function onPartyChange() {
        const partyId = parseInt(partySelect.value, 10);
        currentPartyId = partyId;
        // Clear all rows and add a new one
        itemsContainer.innerHTML = '';
        addItemRow();
        calculateTotals();
    }

    // ---- Refresh item dropdowns ----
    function refreshAllMaterialDropdowns() {
        document.querySelectorAll(".item-material").forEach((select) => {
            populateMaterialDropdown(select);
        });
    }

    function populateMaterialDropdown(selectEl) {
        const currentVal = selectEl.value;
        selectEl.innerHTML = '<option value="">-- Select --</option>';
        if (!currentPartyId) return;
        const items = partyItemsMap.get(currentPartyId) || [];
        items.forEach((item) => {
            // Flexible key handling
            const id = item.material_id|| item.id;
            const name = item.material_name || item.name || 'Unnamed';
            const unit = item.unit || item.unit_name || '';
            const rate = item.rate || 0;
            const minQty = item.min_quantity || item.minimum_quantity || 0;
            const opt = document.createElement("option");
            opt.value = id;
            opt.textContent = `${name} (${unit}) Rate: ${rate} Min: ${minQty}`;
            opt.dataset.rate = rate;
            opt.dataset.unit = unit;
            opt.dataset.minQty = minQty;
            selectEl.appendChild(opt);
        });
        if (currentVal) {
            const exists = Array.from(selectEl.options).some(o => o.value === currentVal);
            if (exists) selectEl.value = currentVal;
        }
        // Trigger change to fill fields
        selectEl.dispatchEvent(new Event('change'));
    }

    // ---- Item rows ----
    function addItemRow() {
        if (!itemRowTemplate || !itemsContainer) return;
        const fragment = itemRowTemplate.content.cloneNode(true);
        const row = fragment.querySelector(".item-row");

        const materialSelect = row.querySelector(".item-material");
        const qtyInput = row.querySelector(".item-qty");
        const rateInput = row.querySelector(".item-rate");
        const lineTotalSpan = row.querySelector(".item-line-total");
        const unitSpan = row.querySelector(".item-unit");
        const minQtySpan = row.querySelector(".item-min-qty");
        const removeBtn = row.querySelector("button");

        // Populate dropdown
        populateMaterialDropdown(materialSelect);

        // Event: material change
        materialSelect.addEventListener("change", function () {
            const opt = this.options[this.selectedIndex];
            const rate = parseFloat(opt?.dataset?.rate) || 0;
            const unit = opt?.dataset?.unit || '';
            const minQty = opt?.dataset?.minQty || 0;
            rateInput.value = rate;
            unitSpan.textContent = unit;
            minQtySpan.textContent = minQty;
            updateRowTotal(row);
            calculateTotals();
        });

        // Events: qty, rate change
        qtyInput.addEventListener("input", function () {
            updateRowTotal(row);
            calculateTotals();
        });
        rateInput.addEventListener("input", function () {
            updateRowTotal(row);
            calculateTotals();
        });

        // Remove button
        if (removeBtn) {
            removeBtn.addEventListener("click", function () {
                const rows = itemsContainer.querySelectorAll(".item-row");
                if (rows.length > 1) {
                    row.remove();
                } else {
                    // Clear but keep row
                    materialSelect.value = "";
                    qtyInput.value = "";
                    rateInput.value = "";
                    unitSpan.textContent = "-";
                    minQtySpan.textContent = "-";
                    updateRowTotal(row);
                }
                calculateTotals();
            });
        }

        itemsContainer.appendChild(row);
        updateRowTotal(row);
        calculateTotals();
    }

    function updateRowTotal(row) {
        const qty = parseFloat(row.querySelector(".item-qty").value) || 0;
        const rate = parseFloat(row.querySelector(".item-rate").value) || 0;
        const lineTotal = qty * rate;
        const lineTotalEl = row.querySelector(".item-line-total");
        if (lineTotalEl) lineTotalEl.textContent = money(lineTotal);
        return lineTotal;
    }

    function calculateTotals() {
        let total = 0;
        itemsContainer.querySelectorAll(".item-row").forEach((row) => {
            total += updateRowTotal(row);
        });
        const grandTotal = total;
        const paid = parseFloat(paidAmountInput.value) || 0;
        const balance = grandTotal - paid;
        const status = balance <= 0 ? 'paid' : (paid > 0 ? 'partial' : 'unpaid');

        grandTotalEl.textContent = money(grandTotal);
        remainingBalanceEl.textContent = money(balance);
        statusBadgeEl.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        statusBadgeEl.className = `status-badge status-${status}`;
        return grandTotal;
    }

    // ---- Get items data for purchase ----
    function getItemsData() {
        const items = [];
        itemsContainer.querySelectorAll(".item-row").forEach((row) => {
            const materialId = row.querySelector(".item-material").value;
            const qty = parseFloat(row.querySelector(".item-qty").value) || 0;
            const rate = parseFloat(row.querySelector(".item-rate").value) || 0;
            if (!materialId || qty <= 0 || rate <= 0) return;
            items.push({
                raw_material_id: parseInt(materialId, 10),
                quantity: qty,
                rate: rate,
            });
        });
        return items;
    }

    // ---- Validation ----
    function validatePurchase(items) {
        const partyId = parseInt(partySelect.value, 10);
        if (!partyId) {
            alert("Please select a party.");
            return false;
        }
        if (items.length === 0) {
            alert("Please add at least one item with valid quantity and rate.");
            return false;
        }
        for (const item of items) {
            if (!item.raw_material_id) {
                alert("Please select a raw material for every row.");
                return false;
            }
            if (item.quantity <= 0) {
                alert("Quantity must be greater than 0.");
                return false;
            }
            if (item.rate <= 0) {
                alert("Rate must be greater than 0.");
                return false;
            }
        }
        return true;
    }

    // ---- Save Purchase ----
    async function savePurchase() {
        const items = getItemsData();
        if (!validatePurchase(items)) return;

        const party_id = parseInt(partySelect.value, 10);
        const total = parseFloat(grandTotalEl.textContent) || 0;
        const paid = parseFloat(paidAmountInput.value) || 0;
        if (paid > total) {
            alert("Paid amount cannot exceed total amount.");
            return;
        }

        const payload = {
            party_id: party_id,
            purchase_date: purchaseDateInput.value || new Date().toISOString().slice(0, 10),
            items: items,
            paid_amount: paid,
            remarks: remarksInput.value || ""
        };

        showLoading(true);
        try {
            const response = await fetch(PURCHASE_API, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (result.success) {
                alert(result.message);
                resetForm();
                loadPurchaseHistory();
            } else {
                alert(result.message || "Error saving purchase.");
            }
        } catch (err) {
            console.error(err);
            alert("Server error. Please try again.");
        } finally {
            showLoading(false);
        }
    }

    function resetForm() {
        if (purchaseDateInput) purchaseDateInput.value = new Date().toISOString().slice(0, 10);
        if (paidAmountInput) paidAmountInput.value = "0";
        if (remarksInput) remarksInput.value = "";
        if (partySelect) partySelect.value = "";
        currentPartyId = null;
        itemsContainer.innerHTML = "";
        addItemRow();
        calculateTotals();
    }

    // ---- Purchase History ----
async function loadPurchaseHistory() {
    if (!purchaseTableBody) return;

    purchaseTableBody.innerHTML = `
        <tr>
            <td colspan="9" class="text-center py-6">
                Loading...
            </td>
        </tr>
    `;

    showLoading(true);

    try {

        const response = await fetchAPI(PURCHASE_HISTORY_API);

        showLoading(false);

        if (!response || response.success !== true) {
            purchaseTableBody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-6 text-red-500">
                        Failed to load purchases.
                    </td>
                </tr>
            `;
            return;
        }

        const purchases = response.data || [];

        purchaseTableBody.innerHTML = "";

        if (purchases.length === 0) {

            purchaseTableBody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-6 text-gray-500">
                        No Purchase Found
                    </td>
                </tr>
            `;

            return;
        }

        purchases.forEach((p) => {

            const total = Number(p.total_amount || 0);
            const paid = Number(p.paid_amount || 0);
            const balance = Number(p.balance_amount || (total - paid));

            const tr = document.createElement("tr");

            tr.innerHTML = `
                <td class="border px-3 py-2">${p.po_no ?? "-"}</td>

                <td class="border px-3 py-2">
                    ${p.invoice_no ?? "-"}
                </td>

                <td class="border px-3 py-2">
                    ${p.purchase_date ?? "-"}
                </td>

                <td class="border px-3 py-2">
                    ${p.party_name ?? "-"}
                </td>

                <td class="border px-3 py-2 text-end">
                    ${money(total)}
                </td>

                <td class="border px-3 py-2 text-end">
                    ${money(paid)}
                </td>

                <td class="border px-3 py-2 text-end">
                    ${money(balance)}
                </td>

                <td class="border px-3 py-2">
                    ${statusBadge(p.status)}
                </td>

                <td class="border px-3 py-2">

                    <div class="relative inline-block">

                        <button
                            onclick="toggleDropdown(this)"
                            class="px-2 py-1 rounded border hover:bg-gray-100">

                            <i class="fa-solid fa-ellipsis-vertical"></i>

                        </button>

                        <div class="dropdown-menu hidden absolute right-0 mt-2 bg-white shadow rounded border z-50 min-w-[180px]">

                            <button class="block w-full text-left px-4 py-2 hover:bg-gray-100"
                                onclick="viewInvoice(${p.id})">
                                <i class="fa-regular fa-file-lines mr-2"></i>
                                View Invoice
                            </button>

                            <button class="block w-full text-left px-4 py-2 hover:bg-gray-100"
                                onclick="printInvoice(${p.id})">
                                <i class="fa-solid fa-print mr-2"></i>
                                Print Invoice
                            </button>

                            <button class="block w-full text-left px-4 py-2 hover:bg-gray-100"
                                onclick="viewLedger(${p.party_id})">
                                <i class="fa-solid fa-book mr-2"></i>
                                Ledger
                            </button>

                            <button class="block w-full text-left px-4 py-2 hover:bg-gray-100"
                                onclick="editPurchase(${p.id})">
                                <i class="fa-regular fa-pen-to-square mr-2"></i>
                                Edit
                            </button>

                            <button class="block w-full text-left px-4 py-2 text-red-600 hover:bg-red-50"
                                onclick="deletePurchase(${p.id})">
                                <i class="fa-regular fa-trash-can mr-2"></i>
                                Delete
                            </button>

                        </div>

                    </div>

                </td>
            `;

            purchaseTableBody.appendChild(tr);

        });

    } catch (err) {

        console.error(err);

        showLoading(false);

        purchaseTableBody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-6 text-red-500">
                    ${err.message}
                </td>
            </tr>
        `;

    }
}
    // ---- Dropdown toggle ----

   // ---- Dropdown toggle (fixed positioning, overflow-safe) ----
// ---- Dropdown toggle (fixed positioning, overflow-safe) ----
window.toggleDropdown = function (btn) {
    // Menu ka reference hamesha button par store rakho (body mein move hone ke baad bhi milta rahe)
    let menu = btn._dropdownMenu;
    if (!menu) {
        const parent = btn.closest('.relative');
        menu = parent.querySelector('.dropdown-menu');
        btn._dropdownMenu = menu;
    }

    if (menu.dataset.open === 'true') {
        closeAllDropdowns();
        return;
    }

    closeAllDropdowns();

    const rect = btn.getBoundingClientRect();

    document.body.appendChild(menu);
    menu.style.position = 'fixed';
    menu.style.top = rect.bottom + 4 + 'px';
    menu.style.right = (window.innerWidth - rect.right) + 'px';
    menu.style.left = 'auto';
    menu.style.display = 'block';
    menu.dataset.open = 'true';
};

function closeAllDropdowns() {
    document.querySelectorAll('.dropdown-menu').forEach(m => {
        m.style.display = 'none';
        m.dataset.open = 'false';
    });
}

document.addEventListener('click', function (e) {
    if (!e.target.closest('.dropdown-trigger') &&
        !e.target.closest('.dropdown-menu') &&
        !e.target.closest('button[onclick*="toggleDropdown"]')) {
        closeAllDropdowns();
    }
});

window.addEventListener('scroll', closeAllDropdowns, true);
window.addEventListener('resize', closeAllDropdowns);

    // ---- Invoice ----
    window.viewInvoice = async function(purchaseId) {
        showLoading(true);
        const url = `${INVOICE_API}?purchase_id=${purchaseId}`;
        const response = await fetchAPI(url);
        showLoading(false);
        if (!response || !response.success) {
            alert(response?.message || "Failed to load invoice.");
            return;
        }
        const data = response.data || {};
        renderInvoiceModal(data);
        invoiceModal.classList.remove("hidden");
    };

   function renderInvoiceModal(data) {
    // Response flat hai — direct data se values lo
    const items = data.items || [];

    let itemsHtml = '';
    items.forEach(item => {
        itemsHtml += `
            <tr>
                <td class="border px-2 py-1">${item.raw_material_name || '-'}</td>
                <td class="border px-2 py-1 text-right">${money(item.quantity)}</td>
                <td class="border px-2 py-1 text-right">${money(item.rate)}</td>
                <td class="border px-2 py-1 text-right">${money(item.line_total)}</td>
            </tr>
        `;
    });

    const statusCls = {
        paid: 'status-paid',
        partial: 'status-partial',
        unpaid: 'status-unpaid'
    }[data.status] || 'status-unpaid';

    const html = `
        <div class="invoice-print-wrapper">
            <div class="text-center border-b pb-4 mb-4">
                <h2 class="text-2xl font-bold">MARKING BOOSTER</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">123 Main Street, City, Country</p>
                <p class="text-sm text-gray-600 dark:text-gray-300">Phone: +123 456 7890 | Email: info@markingbooster.com</p>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <p><strong>Invoice No:</strong> ${data.invoice_no || '-'}</p>
                    <p><strong>PO No:</strong> ${data.po_no || '-'}</p>
                    <p><strong>Date:</strong> ${data.invoice_date || '-'}</p>
                </div>
                <div>
                    <p><strong>Party:</strong> ${data.party_name || 'Unknown'}</p>
                    <p><strong>Phone:</strong> ${data.phone_number || '-'}</p>
                </div>
            </div>
            <div class="table-wrap mb-4">
                <table class="w-full border text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700">
                        <tr>
                            <th class="border px-2 py-1 text-left">Raw Material</th>
                            <th class="border px-2 py-1 text-right">Quantity</th>
                            <th class="border px-2 py-1 text-right">Rate</th>
                            <th class="border px-2 py-1 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml || '<tr><td colspan="4" class="text-center py-2">No items</td></tr>'}
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end">
                <div class="w-64 space-y-1 text-sm">
                    <div class="flex justify-between"><span>Subtotal:</span><span>${money(data.subtotal)}</span></div>
                    <div class="flex justify-between"><span>Total:</span><span>${money(data.total_amount)}</span></div>
                    <div class="flex justify-between"><span>Paid:</span><span>${money(data.paid_amount)}</span></div>
                    <div class="flex justify-between font-semibold"><span>Balance:</span><span>${money(data.balance_amount)}</span></div>
                    <div class="flex justify-between"><span>Status:</span><span class="status-badge ${statusCls}">${data.status || 'Unknown'}</span></div>
                </div>
            </div>
            ${data.remarks ? `<div class="mt-3 text-sm"><strong>Remarks:</strong> ${data.remarks}</div>` : ''}
        </div>
    `;
    invoiceDataContainer.innerHTML = html;
}

    window.closeInvoiceModal = function() {
        invoiceModal.classList.add("hidden");
    };

    window.printInvoiceFromModal = function() {
        window.print();
    };

    // ---- Print Invoice direct ----
    window.printInvoice = async function(purchaseId) {
        showLoading(true);
        const url = `${INVOICE_API}?purchase_id=${purchaseId}`;
        const response = await fetchAPI(url);
        showLoading(false);
        if (!response || !response.success) {
            alert(response?.message || "Failed to load invoice.");
            return;
        }
        const data = response.data || {};
        renderInvoiceModal(data);
        invoiceModal.classList.remove("hidden");
        setTimeout(() => {
            window.print();
        }, 300);
    };

    // ---- Ledger ----
   window.viewLedger = async function(partyId) {
    partyId = parseInt(partyId, 10);
    if (!partyId || partyId <= 0) {
        alert("Invalid party for this purchase.");
        return;
    }
    showLoading(true);
    const url = `${LEDGER_API}?party_id=${partyId}`;
    const response = await fetchAPI(url);
    showLoading(false);
    if (!response || !response.success) {
        alert(response?.message || "Failed to load ledger.");
        return;
    }
    const transactions = response.data || [];
    renderLedgerModal(transactions, partyId);
    ledgerModal.classList.remove("hidden");
};

    function renderLedgerModal(transactions, partyId) {
    const party = parties.find(p => Number(p.id) === Number(partyId));

    if (!party) {
        ledgerDataContainer.innerHTML = `<div class="text-center py-8 text-gray-500">Party details not found.</div>`;
        return;
    }

    const balanceTypeLabel = party.balance_type === 'receivable' ? 'Receivable' : 'Payable';
    const balanceTypeCls = party.balance_type === 'receivable'
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300'
        : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';

    // ---- Party info card ----
    const partyInfoHtml = `
        <div class="bg-gray-50 dark:bg-gray-700/50 border dark:border-gray-600 rounded-lg p-4 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-lg font-bold dark:text-white">
                    <i class="fa-solid fa-user mr-2 text-blue-500"></i>${party.party_name || '-'}
                </h4>
                <span class="status-badge ${balanceTypeCls}">${balanceTypeLabel}</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs">Phone</p>
                    <p class="font-medium dark:text-gray-200">${party.phone_number || '-'}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs">Opening Balance</p>
                    <p class="font-medium dark:text-gray-200">${money(party.opening_balance)}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs">Receivable Balance</p>
                    <p class="font-medium text-emerald-600 dark:text-emerald-400">${money(party.receivable_balance)}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs">Payable Balance</p>
                    <p class="font-medium text-red-600 dark:text-red-400">${money(party.payable_balance)}</p>
                </div>
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-3">
                <i class="fa-regular fa-clock mr-1"></i>Last Updated: ${party.updated_at || '-'}
            </p>
        </div>
    `;

    // ---- Transactions table ----
    if (!transactions || transactions.length === 0) {
        ledgerDataContainer.innerHTML = partyInfoHtml + `
            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <i class="fa-regular fa-folder-open text-3xl mb-2"></i>
                <p>No transactions found for this party.</p>
            </div>
        `;
        return;
    }

    let rowsHtml = '';
    let totalDebit = 0, totalCredit = 0;
    transactions.forEach(t => {
        const debit = parseFloat(t.debit) || 0;
        const credit = parseFloat(t.credit) || 0;
        totalDebit += debit;
        totalCredit += credit;
        rowsHtml += `
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">
                <td class="border dark:border-gray-600 px-3 py-2">${t.transaction_date || '-'}</td>
                <td class="border dark:border-gray-600 px-3 py-2">${t.reference_no || t.invoice_no || t.po_no || '-'}</td>
                <td class="border dark:border-gray-600 px-3 py-2">${t.description || '-'}</td>
                <td class="border dark:border-gray-600 px-3 py-2 text-right ${debit > 0 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-400'}">${debit > 0 ? money(debit) : '-'}</td>
                <td class="border dark:border-gray-600 px-3 py-2 text-right ${credit > 0 ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-gray-400'}">${credit > 0 ? money(credit) : '-'}</td>
                <td class="border dark:border-gray-600 px-3 py-2 text-right font-semibold dark:text-gray-100">${money(t.balance)}</td>
            </tr>
        `;
    });

    const html = partyInfoHtml + `
        <div class="table-wrap rounded-lg border dark:border-gray-600 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 dark:bg-gray-700 dark:text-white">
                    <tr>
                        <th class="border dark:border-gray-600 px-3 py-2 text-left">Date</th>
                        <th class="border dark:border-gray-600 px-3 py-2 text-left">Reference</th>
                        <th class="border dark:border-gray-600 px-3 py-2 text-left">Description</th>
                        <th class="border dark:border-gray-600 px-3 py-2 text-right">Debit</th>
                        <th class="border dark:border-gray-600 px-3 py-2 text-right">Credit</th>
                        <th class="border dark:border-gray-600 px-3 py-2 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="dark:text-gray-300">
                    ${rowsHtml}
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-800 font-semibold dark:text-gray-100">
                    <tr>
                        <td colspan="3" class="border dark:border-gray-600 px-3 py-2 text-right">Totals</td>
                        <td class="border dark:border-gray-600 px-3 py-2 text-right text-red-600 dark:text-red-400">${money(totalDebit)}</td>
                        <td class="border dark:border-gray-600 px-3 py-2 text-right text-emerald-600 dark:text-emerald-400">${money(totalCredit)}</td>
                        <td class="border dark:border-gray-600 px-3 py-2 text-right"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    `;
    ledgerDataContainer.innerHTML = html;
}

    window.closeLedgerModal = function() {
        ledgerModal.classList.add("hidden");
    };

    // ---- Edit & Delete (placeholders) ----
    window.editPurchase = function(purchaseId) {
        alert("Edit functionality is not yet implemented. (Purchase ID: " + purchaseId + ")");
    };

    window.deletePurchase = function(purchaseId) {
        if (confirm("Are you sure you want to delete purchase #" + purchaseId + "? This action cannot be undone.")) {
            alert("Delete functionality is not yet implemented. (Purchase ID: " + purchaseId + ")");
        }
    };

    // ---- Close modals on overlay click ----
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    });

    // ---- Event listeners ----
    partySelect.addEventListener("change", onPartyChange);
    paidAmountInput.addEventListener("input", calculateTotals);

    // ---- Expose functions ----
    window.addItemRow = addItemRow;
    window.calculateTotals = calculateTotals;
    window.savePurchase = savePurchase;

    // ---- Init ----
    document.addEventListener("DOMContentLoaded", function () {
        if (purchaseDateInput) {
            purchaseDateInput.value = new Date().toISOString().slice(0, 10);
        }
        loadParties().then(() => {
            addItemRow();
        });
        loadPurchaseHistory();
    });
})();