(function () {
    "use strict";

    // ========== DOM REFS ==========
    const modal = document.getElementById("partyModal");
    const modalTitle = document.getElementById("modalTitle");
    const partyForm = document.getElementById("partyForm");
    const partyId = document.getElementById("partyId");
    const partyName = document.getElementById("partyName");
    const phoneNumber = document.getElementById("phoneNumber");
    const receivableBalance = document.getElementById("receivableBalance");
    const payableBalance = document.getElementById("payableBalance");
    const itemsContainer = document.getElementById("itemsContainer");
    const tableBody = document.getElementById("partiesTableBody");
    const searchInput = document.getElementById("searchInput");
    const noResults = document.getElementById("noResults");
    const paginationInfo = document.getElementById("paginationInfo");
    const paginationControls = document.getElementById("paginationControls");
    const toastContainer = document.getElementById("toastContainer");

    // Pay modal refs
    const payModal = document.getElementById("payModal");
    const payPartyId = document.getElementById("payPartyId");
    const payBalanceType = document.getElementById("payBalanceType");
    const payOperation = document.getElementById("payOperation");
    const payAmount = document.getElementById("payAmount");
    const payForm = document.getElementById("payForm");

    // ========== STATE ==========
    let allParties = [];
    let filteredParties = [];
    let currentPage = 1;
    let pageSize = 10;
    let searchTerm = "";
    let materials = [];

    // ========== API ENDPOINTS ==========
    const API_BASE = "../../action/parties";
    const MATERIALS_API = "../../action/raw_materials/get_raw_materials.php";
    const LIST_API = `${API_BASE}/get_party.php`;
    const ADD_API = `${API_BASE}/add_party.php`;
    const UPDATE_API = `${API_BASE}/update_party.php`;
    const DELETE_API = `${API_BASE}/delete_party.php`;
    const BALANCE_API = "../../action/parties/update_balance.php";

    // ========== HELPERS ==========
    function escapeHtml(str) {
        if (!str) return "";
        const div = document.createElement("div");
        div.textContent = str;
        return div.innerHTML;
    }

    function showToast(message, type = "success") {
        if (!toastContainer) { alert(message); return; }
        const toast = document.createElement("div");
        const bg = type === "error" ? "bg-red-600" : "bg-green-600";
        toast.className = `${bg} text-white text-sm px-4 py-2.5 rounded-lg shadow-lg mb-2 transition-opacity duration-300`;
        toast.textContent = message;
        toastContainer.appendChild(toast);
        setTimeout(() => {
            toast.classList.add("opacity-0");
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    async function fetchAPI(url, options = {}) {
        try {
            const res = await fetch(url, options);
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || "Request failed");
            return data;
        } catch (e) {
            showToast(e.message, "error");
            return null;
        }
    }

    // ========== FETCH MATERIALS ==========
    async function loadMaterials() {
        const result = await fetchAPI(MATERIALS_API);
        if (result && result.success) {
            materials = result.data || [];
        } else {
            materials = [];
        }
    }

    // ========== RENDER ITEMS DROPDOWN ==========
    function getMaterialOptions(selectedId = null) {
        let html = `<option value="">Select Material</option>`;
        materials.forEach(m => {
            html += `<option value="${m.id}" ${selectedId == m.id ? 'selected' : ''}>${escapeHtml(m.name)} (${escapeHtml(m.unit || '')})</option>`;
        });
        return html;
    }

    // ========== ITEM ROW MANAGEMENT ==========
    function addItemRow(material_id = "", rate = "", min_qty = "") {
        const row = document.createElement("div");
        row.className = "item-row grid grid-cols-4 gap-2 items-center p-2 border dark:border-gray-600 rounded";
        row.innerHTML = `
            <select class="material-select w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1 text-sm">
                ${getMaterialOptions(material_id)}
            </select>
            <input type="number" step="0.01" placeholder="Rate" value="${rate}" class="rate-input w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1 text-sm" />
            <input type="number" step="0.01" placeholder="Min Qty" value="${min_qty}" class="qty-input w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1 text-sm" />
            <button type="button" onclick="this.closest('.item-row').remove()" class="text-red-400 hover:text-red-600 text-lg"><i class="fa-solid fa-trash-can"></i></button>
        `;
        itemsContainer.appendChild(row);
    }

    // ========== COLLECT ITEMS FROM FORM ==========
    function getItemsFromForm() {
        const rows = itemsContainer.querySelectorAll(".item-row");
        const items = [];
        rows.forEach(row => {
            const material_id = row.querySelector(".material-select").value;
            const rate = parseFloat(row.querySelector(".rate-input").value) || 0;
            const min_qty = parseFloat(row.querySelector(".qty-input").value) || 0;
            if (material_id) {
                items.push({ material_id, rate, min_quantity: min_qty });
            }
        });
        return items;
    }

    // ========== POPULATE FORM FOR EDIT ==========
    function populateForm(party) {
        partyId.value = party.id;
        partyName.value = party.party_name || "";
        phoneNumber.value = party.phone_number || "";
        receivableBalance.value = party.receivable_balance || 0;
        payableBalance.value = party.payable_balance || 0;
        itemsContainer.innerHTML = "";
        if (party.items && party.items.length) {
            party.items.forEach(item => {
                addItemRow(item.material_id, item.rate, item.min_quantity);
            });
        } else {
            addItemRow();
        }
    }

    // ========== OPEN/CLOSE MODALS ==========
    function openModal(title, party = null) {
        modalTitle.textContent = title;
        if (party) {
            populateForm(party);
        } else {
            partyId.value = 0;
            partyForm.reset();
            receivableBalance.value = "0.00";
            payableBalance.value = "0.00";
            itemsContainer.innerHTML = "";
            addItemRow();
        }
        modal.classList.add("open");
    }

    function closeModal() {
        modal.classList.remove("open");
    }

    function openPayModal(partyIdVal) {
        payPartyId.value = partyIdVal;
        payForm.reset();
        payModal.classList.add("open");
    }

    function closePayModal() {
        payModal.classList.remove("open");
    }

    // ========== SAVE PARTY ==========
    async function saveParty(e) {
        e.preventDefault();
        const id = partyId.value;
        const isEdit = id && id !== "0";

        const payload = {
            party_name: partyName.value.trim(),
            phone_number: phoneNumber.value.trim(),
            receivable_balance: parseFloat(receivableBalance.value) || 0,
            payable_balance: parseFloat(payableBalance.value) || 0,
            items: getItemsFromForm()
        };
        if (isEdit) payload.id = id;

        const url = isEdit ? UPDATE_API : ADD_API;
        const method = isEdit ? "PUT" : "POST";

        const result = await fetchAPI(url, {
            method,
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        if (result && result.success) {
            showToast(result.message || "Party saved.");
            closeModal();
            loadParties();
        }
    }

    // ========== PAY (Update Balance) ==========
    async function handlePaySubmit(e) {
        e.preventDefault();
        const id = payPartyId.value;
        const balance_type = payBalanceType.value;
        const operation = payOperation.value;
        const amount = parseFloat(payAmount.value);

        if (!amount || amount <= 0) {
            showToast("Please enter a valid amount greater than zero.", "error");
            return;
        }

        const payload = { id, balance_type, operation, amount };
        const result = await fetchAPI(BALANCE_API, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        if (result && result.message) {
            showToast(result.message);
            closePayModal();
            loadParties();
        }
    }

    // ========== LOAD PARTIES ==========
    async function loadParties() {
        const params = new URLSearchParams();
        if (searchTerm) params.append("search", searchTerm);

        const url = `${LIST_API}?${params.toString()}`;
        const result = await fetchAPI(url);
        if (result && result.success) {
            allParties = result.data || [];
        } else {
            allParties = [];
        }
        applyFiltersAndRender();
    }

    // ========== FILTER & RENDER ==========
    function applyFiltersAndRender() {
        let data = [...allParties];
        if (searchTerm) {
            const s = searchTerm.toLowerCase();
            data = data.filter(p => 
                p.party_name.toLowerCase().includes(s) || 
                (p.phone_number && p.phone_number.includes(s))
            );
        }
        filteredParties = data;
        renderTable();
    }

    // ========== RENDER TABLE (with dropdown actions) ==========
    function renderTable() {
        const total = filteredParties.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * pageSize;
        const pageData = filteredParties.slice(start, start + pageSize);

        if (pageData.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-gray-400">No parties found</td></tr>`;
            noResults.classList.remove("hidden");
        } else {
            noResults.classList.add("hidden");
            let html = "";
            pageData.forEach((p, idx) => {
                const rowNum = start + idx + 1;
                let itemsStr = "";
                if (p.items && p.items.length) {
                    itemsStr = p.items.map(item => 
                        `${escapeHtml(item.material_name)} (Rate: ${parseFloat(item.rate || 0).toFixed(2)}, Min Qty: ${parseFloat(item.min_quantity || 0).toFixed(0)})`
                    ).join(", ");
                } else {
                    itemsStr = "—";
                }

                html += `
                    <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-3 py-2">${rowNum}</td>
                        <td class="px-3 py-2 font-medium">
                            ${escapeHtml(p.party_name)}
                            ${p.status === 'inactive' ? '<span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-gray-200 text-gray-500 dark:bg-gray-600 dark:text-gray-300">Inactive</span>' : ''}
                        </td>
                        <td class="px-3 py-2">${escapeHtml(p.phone_number || '—')}</td>
                        <td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-400">${escapeHtml(itemsStr)}</td>
                        <td class="px-3 py-2 text-right text-emerald-600 dark:text-emerald-400">${parseFloat(p.receivable_balance || 0).toFixed(2)}</td>
                        <td class="px-3 py-2 text-right text-red-600 dark:text-red-400">${parseFloat(p.payable_balance || 0).toFixed(2)}</td>
                        <td class="px-3 py-2 text-center relative">
                            <button onclick="toggleDropdown(${p.id})" class="action-btn text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 border rounded px-3 py-1 text-sm">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <div id="dropdown-${p.id}" class="action-dropdown hidden">
                                <div class="action-dropdown-item" onclick="editParty(${p.id})"><i class="fa-regular fa-pen-to-square"></i> Edit</div>
                                <div class="action-dropdown-item" onclick="openPayModal(${p.id})"><i class="fa-regular fa-money-bill-1"></i> Pay</div>
                                <div class="action-dropdown-item" onclick="viewInvoices(${p.id})"><i class="fa-regular fa-file-invoice"></i> Invoices</div>
                                <div class="action-dropdown-item" onclick="viewLedger(${p.id})"><i class="fa-regular fa-book"></i> Ledger</div>
                                <div class="action-dropdown-item" onclick="toggleStatus(${p.id})"><i class="fa-regular fa-${p.status === 'inactive' ? 'circle-check' : 'circle-xmark'}"></i> ${p.status === 'inactive' ? 'Activate' : 'Deactivate'}</div>
                                <div class="action-dropdown-item danger" onclick="deleteParty(${p.id})"><i class="fa-regular fa-trash-can"></i> Delete</div>
                            </div>
                        </td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        }

        // Update stats
        const totalReceivable = allParties.reduce((sum, p) => sum + parseFloat(p.receivable_balance || 0), 0);
        const totalPayable = allParties.reduce((sum, p) => sum + parseFloat(p.payable_balance || 0), 0);
        document.getElementById("totalParties").textContent = allParties.length;
        document.getElementById("totalReceivable").textContent = totalReceivable.toFixed(2);
        document.getElementById("totalPayable").textContent = totalPayable.toFixed(2);

        // Pagination
        const startItem = total === 0 ? 0 : start + 1;
        const endItem = Math.min(start + pageSize, total);
        paginationInfo.textContent = `Showing ${startItem}–${endItem} of ${total}`;
        let pgHtml = `<button onclick="changePage('prev')" class="px-3 py-1 border rounded ${currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100'}">Prev</button>`;
        for (let i = 1; i <= totalPages; i++) {
            pgHtml += `<button onclick="changePage(${i})" class="px-3 py-1 border rounded ${i === currentPage ? 'bg-slate-800 text-white' : 'hover:bg-gray-100'}">${i}</button>`;
        }
        pgHtml += `<button onclick="changePage('next')" class="px-3 py-1 border rounded ${currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100'}">Next</button>`;
        paginationControls.innerHTML = pgHtml;
    }

    // ========== DROPDOWN TOGGLE ==========
    function toggleDropdown(id) {
        const dropdown = document.getElementById(`dropdown-${id}`);
        if (dropdown) {
            // Close all other dropdowns
            document.querySelectorAll('.action-dropdown').forEach(d => {
                if (d !== dropdown) d.classList.add('hidden');
            });
            dropdown.classList.toggle('hidden');
        }
    }

    // ========== CLOSE DROPDOWN ON OUTSIDE CLICK ==========
    document.addEventListener('click', function(e) {
        // Close dropdown if clicking outside the button and dropdown
        if (!e.target.closest('.action-btn') && !e.target.closest('.action-dropdown')) {
            document.querySelectorAll('.action-dropdown').forEach(d => d.classList.add('hidden'));
        }
    });

    // ========== PAGINATION ==========
    function changePage(dir) {
        const totalPages = Math.max(1, Math.ceil(filteredParties.length / pageSize));
        if (dir === 'prev') currentPage = Math.max(1, currentPage - 1);
        else if (dir === 'next') currentPage = Math.min(totalPages, currentPage + 1);
        else if (typeof dir === 'number') currentPage = Math.min(totalPages, Math.max(1, dir));
        renderTable();
    }

    // ========== ACTIONS ==========
    async function editParty(id) {
        const result = await fetchAPI(`${LIST_API}?id=${id}`);
        if (result && !result.error) {
            openModal("Edit Party", result);
        } else {
            showToast("Failed to load party details", "error");
        }
        // Close dropdown
        document.querySelectorAll('.action-dropdown').forEach(d => d.classList.add('hidden'));
    }

    async function deleteParty(id) {
        if (!confirm("Are you sure you want to delete this party?")) return;
        const result = await fetchAPI(`${DELETE_API}?id=${id}`, { method: "DELETE" });
        if (result && result.success) {
            showToast("Party deleted");
            loadParties();
        } else if (result === null) {
            // fetchAPI already showed the specific error toast (e.g. linked
            // orders/purchases/payments). Offer the safe alternative.
            if (confirm("This party can't be deleted because it has linked records. Deactivate it instead? (Keeps its history, hides it from new transactions.)")) {
                await toggleStatus(id, true);
            }
        }
        document.querySelectorAll('.action-dropdown').forEach(d => d.classList.add('hidden'));
    }

    async function toggleStatus(id, skipDropdownClose) {
        const result = await fetchAPI(`${API_BASE}/toggle_party_status.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id })
        });
        if (result && result.success) {
            showToast(result.message);
            loadParties();
        }
        if (!skipDropdownClose) {
            document.querySelectorAll('.action-dropdown').forEach(d => d.classList.add('hidden'));
        }
    }

    function viewInvoices(id) {
        alert(`Invoices for party ID: ${id} (Coming soon)`);
        document.querySelectorAll('.action-dropdown').forEach(d => d.classList.add('hidden'));
    }

    function viewLedger(id) {
        alert(`Ledger for party ID: ${id} (Coming soon)`);
        document.querySelectorAll('.action-dropdown').forEach(d => d.classList.add('hidden'));
    }

    // ========== INIT ==========
    async function init() {
        await loadMaterials();
        await loadParties();
        // Event listeners
        partyForm.addEventListener("submit", saveParty);
        payForm.addEventListener("submit", handlePaySubmit);
        searchInput.addEventListener("input", function() {
            searchTerm = this.value.trim();
            currentPage = 1;
            loadParties();
        });
        // Global functions for inline onclick
        window.openAddModal = () => openModal("Add Party", null);
        window.closeModal = closeModal;
        window.editParty = editParty;
        window.deleteParty = deleteParty;
        window.toggleStatus = toggleStatus;
        window.addItemRow = addItemRow;
        window.changePage = changePage;
        window.loadParties = loadParties;
        window.toggleDropdown = toggleDropdown;
        window.openPayModal = openPayModal;
        window.closePayModal = closePayModal;
        window.viewInvoices = viewInvoices;
        window.viewLedger = viewLedger;
    }

    init();
})();