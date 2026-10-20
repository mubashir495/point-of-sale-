// ============ Configuration ============
const BASE_URL = "../../action";

// ============ Element References ============
const customerSelect    = document.getElementById("salePartySelect");
const summaryInvoiceNo  = document.getElementById("summaryInvoiceNo");
const summaryDate       = document.getElementById("summaryDate");
const summaryTotal      = document.getElementById("summaryTotal");
const summaryDiscount   = document.getElementById("summaryDiscount");
const summaryNet        = document.getElementById("summaryNet");
const summaryPaid       = document.getElementById("summaryPaid");
const saveSaleBtn       = document.getElementById("saveSaleBtn");

const productSearchInput= document.getElementById("productSearchInput");
const productGrid       = document.getElementById("productGrid");
const categoryContainer  = document.getElementById("categoryContainer");
const tbody             = document.getElementById("saleItemsTableBody");
const cartCountBadge    = document.getElementById("cartCountBadge");

// Sales Summary modal
const salesSummaryBtn      = document.getElementById("salesSummaryBtn");
const salesSummaryModal    = document.getElementById("salesSummaryModal");
const closeSalesSummaryBtn = document.getElementById("closeSalesSummaryBtn");
const salesSummaryBody     = document.getElementById("salesSummaryBody");

// Sale Return modal
const openReturnBtn      = document.getElementById("openReturnBtn");
const returnModal        = document.getElementById("returnModal");
const closeReturnBtn     = document.getElementById("closeReturnBtn");
const returnInvoiceInput = document.getElementById("returnInvoiceInput");
const findInvoiceBtn     = document.getElementById("findInvoiceBtn");
const returnLookupMsg    = document.getElementById("returnLookupMsg");
const returnItemsWrap    = document.getElementById("returnItemsWrap");
const returnInvoiceMeta  = document.getElementById("returnInvoiceMeta");
const returnItemsTableBody = document.getElementById("returnItemsTableBody");
const returnRemarks      = document.getElementById("returnRemarks");
const returnTotalDisplay = document.getElementById("returnTotalDisplay");
const submitReturnBtn    = document.getElementById("submitReturnBtn");

let currentReturnOrder = null; // { order_id, invoice_no, items: [...] }

// ============ State ============
let products = [];
let categories = [];
let activeCategory = "All";
let cart = [];
let currentCustomerData = { name: "Walk-in", phone: "", address: "", balance: 0 };
let currentCustomerId = null; // store customer ID as integer or null

// ============ Utility: Safe fetch ============
async function fetchJSON(url, options = {}) {
    try {
        const res = await fetch(url, options);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return await res.json();
    } catch (err) {
        console.error("Fetch error:", err);
        throw err;
    }
}

// ============ Load Customers ============
async function loadCustomers() {
    try {
        const json = await fetchJSON(`${BASE_URL}/customers/get_customers.php`);
        const customers = Array.isArray(json) ? json : (json.data || []);
        
        customerSelect.innerHTML = `<option value="walk-in">Walk-in</option>`;
        customers.forEach(customer => {
            const opt = document.createElement("option");
            opt.value = customer.id;
            opt.dataset.balance = customer.current_balance || 0;
            opt.dataset.name = customer.name || "";
            opt.dataset.phone = customer.phone || "";
            opt.dataset.address = customer.address || "";
            opt.textContent = `${customer.customer_code ? customer.customer_code + ' - ' : ''}${customer.name || ''}`;
            customerSelect.appendChild(opt);
        });
    } catch (err) {
        console.error("Failed to load customers:", err);
    }
}

if (customerSelect) {
    customerSelect.addEventListener("change", function () {
        const option = this.options[this.selectedIndex];
        if (this.value === "walk-in") {
            currentCustomerId = null;
            currentCustomerData = { name: "Walk-in", phone: "", address: "", balance: 0 };
        } else {
            currentCustomerId = parseInt(this.value);
            currentCustomerData = {
                id: this.value,
                name: option.dataset.name || "",
                phone: option.dataset.phone || "",
                address: option.dataset.address || "",
                balance: parseFloat(option.dataset.balance || 0)
            };
        }
    });
}

// ============ Load Products & Categories ============
async function loadProducts() {
    try {
        const json = await fetchJSON(`${BASE_URL}/products/get-product.php`);
        products = Array.isArray(json) ? json : (json.data || []);
        
        // Extract Categories dynamically
        const catSet = new Set();
        products.forEach(p => {
            if (p.category_name) catSet.add(p.category_name);
            else if (p.category) catSet.add(p.category);
        });
        categories = ["All", ...Array.from(catSet)];

        renderCategories();
        renderProducts();
    } catch (err) {
        console.error("Failed to load products:", err);
        if (productGrid) {
            productGrid.innerHTML = `<div class="col-span-full text-center text-red-500 py-4 font-semibold">Error loading products</div>`;
        }
    }
}

// ============ Render Category Sidebar ============
function renderCategories() {
    if (!categoryContainer) return;
    categoryContainer.innerHTML = "";

    categories.forEach(cat => {
        const btn = document.createElement("button");
        const isActive = activeCategory === cat;
        btn.type = "button";
        btn.className = `w-full flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm transition-all ${
            isActive 
            ? "bg-white text-[#0C3B73] shadow" 
            : "bg-white/10 text-white hover:bg-white/20"
        }`;
        
        btn.innerHTML = `
            <i class="fa-solid ${cat === 'All' ? 'fa-cart-shopping' : 'fa-box'} text-base"></i>
            <span>${cat === 'All' ? 'All Products' : cat}</span>
        `;

        btn.addEventListener("click", () => {
            activeCategory = cat;
            renderCategories();
            renderProducts();
        });

        categoryContainer.appendChild(btn);
    });
}

// ============ Render Product Grid ============
function renderProducts() {
    if (!productGrid) return;
    productGrid.innerHTML = "";

    const searchTerm = (productSearchInput?.value || "").toLowerCase().trim();

    const filtered = products.filter(p => {
        const pName = (p.product_name || p.name || "").toLowerCase();
        const pCat = p.category_name || p.category || "General";
        
        const matchesCategory = activeCategory === "All" || pCat === activeCategory;
        const matchesSearch = !searchTerm || pName.includes(searchTerm);
        
        return matchesCategory && matchesSearch;
    });

    if (filtered.length === 0) {
        productGrid.innerHTML = `<div class="col-span-full text-center text-gray-400 py-8 text-sm">No products found</div>`;
        return;
    }

    filtered.forEach(p => {
        const pId = p.id;
        const pName = p.product_name || p.name || "Unknown Item";
        const pPrice = parseFloat(p.sale_price || p.price || 0).toFixed(2);
        const pCat = p.category_name || p.category || "General";
        const pStock = p.stock !== undefined ? p.stock : (p.quantity || 0);

        const card = document.createElement("div");
        card.className = "bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-4 text-center shadow-sm hover:shadow-md cursor-pointer transition-all active:scale-95 select-none";
        card.innerHTML = `
            <h3 class="font-bold text-gray-800 dark:text-gray-100 text-sm leading-tight min-h-[36px] flex items-center justify-center">${pName}</h3>
            <div class="text-xs font-black text-[#001F4D] dark:text-blue-400 mt-2">Rs</div>
            <div class="text-xl font-black text-[#001F4D] dark:text-blue-400 mb-2">${pPrice}</div>
            <span class="inline-block px-3 py-0.5 bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-300 rounded-full text-[10px] font-bold mb-2">${pCat}</span>
            <div class="text-[11px] text-gray-400">Stock: ${parseFloat(pStock).toFixed(3)}</div>
        `;

        card.addEventListener("click", () => addToCart(p));
        productGrid.appendChild(card);
    });
}

if (productSearchInput) {
    productSearchInput.addEventListener("input", renderProducts);
}

// ============ Cart Operations ============
function addToCart(product) {
    const existingIndex = cart.findIndex(item => item.product_id == product.id);
    const price = parseFloat(product.sale_price || product.price || 0);

    if (existingIndex > -1) {
        cart[existingIndex].qty += 1;
        cart[existingIndex].total = cart[existingIndex].qty * cart[existingIndex].price;
    } else {
        cart.push({
            product_id: product.id,
            product_name: product.product_name || product.name || "Item",
            qty: 1,
            price: price,
            total: price
        });
    }

    renderCart();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
}

function renderCart() {
    if (!tbody) return;
    tbody.innerHTML = "";
    let grandTotal = 0;
    let totalQtyCount = 0;

    cart.forEach((item, index) => {
        grandTotal += item.total;
        totalQtyCount += item.qty;

        const tr = document.createElement("tr");
        tr.className = "hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors";
        tr.innerHTML = `
            <td class="py-3 font-bold text-gray-800 dark:text-gray-200 pr-1">${item.product_name}</td>
            <td class="py-3 text-center">
                <input type="number" min="1" step="1" value="${item.qty}" 
                       class="cart-qty-input w-12 text-center font-bold bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded py-0.5 text-xs text-gray-800 dark:text-white outline-none focus:ring-1 focus:ring-blue-500" 
                       data-index="${index}" />
            </td>
            <td class="py-3 text-right">${item.price.toFixed(2)}</td>
            <td class="py-3 text-right font-bold text-blue-600 dark:text-blue-400">${item.total.toFixed(2)}</td>
            <td class="py-3 text-center">
                <button type="button" class="remove-cart-item text-red-400 hover:text-red-600 font-bold text-base px-1" data-index="${index}">×</button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Event listeners for Quantity changes inside Cart
    document.querySelectorAll(".cart-qty-input").forEach(input => {
        input.addEventListener("change", function () {
            const idx = parseInt(this.dataset.index);
            const newQty = parseFloat(this.value) || 1;
            if (newQty <= 0) {
                removeFromCart(idx);
            } else {
                cart[idx].qty = newQty;
                cart[idx].total = newQty * cart[idx].price;
                renderCart();
            }
        });
    });

    // Event listeners for Item Deletion
    document.querySelectorAll(".remove-cart-item").forEach(btn => {
        btn.addEventListener("click", function () {
            const idx = parseInt(this.dataset.index);
            removeFromCart(idx);
        });
    });

    if (cartCountBadge) {
        cartCountBadge.textContent = `${cart.length} Item${cart.length !== 1 ? 's' : ''}`;
    }

    if (summaryTotal) summaryTotal.value = grandTotal.toFixed(2);
    recalcSummary();
}

// ============ Summary Calculations ============
function recalcSummary() {
    const total = parseFloat(summaryTotal?.value) || 0;
    const discount = parseFloat(summaryDiscount?.value) || 0;

    const net = Math.max(0, total - discount);
    if (summaryNet) summaryNet.value = net.toFixed(2);

    // Default paid amount mirrors Net bill
    if (summaryPaid && (!summaryPaid.value || parseFloat(summaryPaid.value) === 0)) {
        summaryPaid.value = net.toFixed(2);
    }
}

if (summaryDiscount) summaryDiscount.addEventListener("input", recalcSummary);

// ============ Build Sale Payload for orders table ============
function buildSalePayload() {
    const subtotal = parseFloat(summaryTotal?.value) || 0;
    const discountAmount = parseFloat(summaryDiscount?.value) || 0;
    const net = parseFloat(summaryNet?.value) || 0;

    // Get selected customer ID (null for walk-in)
    const customerId = (customerSelect.value === "walk-in") ? null : parseInt(customerSelect.value);

    return {
        customer_id: customerId,
        employee_id: null,              // we don't track employee
        order_date: summaryDate?.value || new Date().toISOString().split("T")[0],
        delivery_date: null,            // optional
        shift: null,                    // we don't track shift
        subtotal: subtotal,
        discount: discountAmount,
        total: net,
        status: 'Completed',
        remarks: "",
        items: cart.map(item => ({
            product_id: item.product_id,
            quantity: item.qty,
            unit_price: item.price,
            total: item.total
        }))
    };
}

// ============ Reset POS Form ============
function resetSaleForm() {
    cart = [];
    renderCart();
    if (summaryTotal) summaryTotal.value = "0.00";
    if (summaryDiscount) summaryDiscount.value = "0";
    if (summaryNet) summaryNet.value = "0.00";
    if (summaryPaid) summaryPaid.value = "0.00";
    if (summaryInvoiceNo) summaryInvoiceNo.value = "";
    if (customerSelect) customerSelect.value = "walk-in";
    currentCustomerId = null;
    currentCustomerData = { name: "Walk-in", phone: "", address: "", balance: 0 };
}

// ============ Printable Invoice Modal ============
function showInvoiceModal(invoiceNo, customer, items, totals) {
    const oldModal = document.getElementById("invoicePrintModal");
    if (oldModal) oldModal.remove();

    const modal = document.createElement("div");
    modal.id = "invoicePrintModal";
    modal.className = "fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4";
    modal.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-3 border-b dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Sale Receipt</h2>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-1.5 rounded-lg text-xs font-bold flex items-center gap-2">
                        <i class="fa-solid fa-print"></i> Print
                    </button>
                    <button onclick="this.closest('#invoicePrintModal').remove()" class="text-gray-400 hover:text-red-500 text-xl font-bold px-2">&times;</button>
                </div>
            </div>

            <div class="p-6" id="invoicePrintArea">
                <div class="flex justify-between items-start mb-4 pb-3 border-b dark:border-gray-700">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white">NOWRUZ DAIRY</h3>
                        <p class="text-xs text-gray-500">Contact: 03114683143</p>
                    </div>
                    <div class="text-right">
                        <h4 class="font-bold text-sm text-gray-700 dark:text-gray-300">Invoice #${invoiceNo}</h4>
                        <p class="text-xs text-gray-500">${summaryDate?.value || new Date().toLocaleDateString()}</p>
                    </div>
                </div>

                <div class="mb-4 text-xs">
                    <span class="text-gray-400 font-bold uppercase">Customer:</span>
                    <span class="font-bold text-gray-800 dark:text-white ml-1">${customer.name || 'Walk-in'}</span>
                </div>

                <table class="w-full text-xs mb-4 border dark:border-gray-700">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                        <tr>
                            <th class="p-2 text-left">SR#</th>
                            <th class="p-2 text-left">ITEM</th>
                            <th class="p-2 text-center">QTY</th>
                            <th class="p-2 text-right">PRICE</th>
                            <th class="p-2 text-right">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                        ${items.map((item, idx) => `
                            <tr>
                                <td class="p-2">${idx + 1}</td>
                                <td class="p-2 font-medium">${item.product_name}</td>
                                <td class="p-2 text-center">${item.qty}</td>
                                <td class="p-2 text-right">${item.price.toFixed(2)}</td>
                                <td class="p-2 text-right font-bold">${item.total.toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>

                <div class="flex justify-end text-xs">
                    <div class="w-56 space-y-1.5 border-t dark:border-gray-700 pt-3">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Subtotal:</span>
                            <span class="font-bold">${totals.subtotal.toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Discount:</span>
                            <span class="font-bold">-${totals.discount.toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between text-sm font-bold border-t dark:border-gray-700 pt-1">
                            <span>Net Bill:</span>
                            <span class="text-blue-600 dark:text-blue-400">${totals.net.toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Paid Amount:</span>
                            <span class="font-bold">${totals.paid.toFixed(2)}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
}

// ============ Save Order ============
async function saveOrder() {
    if (cart.length === 0) {
        alert("Cart is empty! Please add products first.");
        return;
    }

    const payload = buildSalePayload();

    try {
        const response = await fetch(`${BASE_URL}/orders/add_counter_sale.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            // Use order_no as invoice number (or invoice_no if your backend returns it)
            const invoiceNo = result.order_no || result.invoice_no || `ORD-${Date.now()}`;
            if (summaryInvoiceNo) summaryInvoiceNo.value = invoiceNo;

            const totals = {
                subtotal: parseFloat(summaryTotal?.value) || 0,
                discount: parseFloat(summaryDiscount?.value) || 0,
                net: parseFloat(summaryNet?.value) || 0,
                paid: parseFloat(summaryPaid?.value) || 0
            };

            showInvoiceModal(invoiceNo, currentCustomerData, [...cart], totals);
            resetSaleForm();
            // Optionally show success message
            // alert("Order saved successfully!");
        } else {
            alert(result.message || "Failed to process sale.");
        }
    } catch (e) {
        console.error("Save error:", e);
        alert("Server communication error. Check console.");
    }
}

if (saveSaleBtn) {
    saveSaleBtn.addEventListener("click", saveOrder);
}

// ============ Sales Summary (Daily / Overall) ============
function formatMoney(n) {
    return (parseFloat(n) || 0).toLocaleString("en-PK", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function renderSalesSummary(data) {
    if (!salesSummaryBody) return;
    const t = data.today, o = data.overall;
    salesSummaryBody.innerHTML = `
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-lg bg-blue-50 dark:bg-blue-950/40 p-3">
                <div class="text-[10px] font-bold uppercase text-blue-500">Today's Sale</div>
                <div class="text-xl font-black text-blue-700 dark:text-blue-300">Rs ${formatMoney(t.net_sale)}</div>
                <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">${t.orders} invoice(s)</div>
                ${t.returns > 0 ? `<div class="text-[11px] text-red-500 mt-1">- Rs ${formatMoney(t.return_amount)} returned (${t.returns})</div>` : ""}
            </div>
            <div class="rounded-lg bg-emerald-50 dark:bg-emerald-950/40 p-3">
                <div class="text-[10px] font-bold uppercase text-emerald-500">Overall Sale</div>
                <div class="text-xl font-black text-emerald-700 dark:text-emerald-300">Rs ${formatMoney(o.net_sale)}</div>
                <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">${o.orders} invoice(s)</div>
                ${o.returns > 0 ? `<div class="text-[11px] text-red-500 mt-1">- Rs ${formatMoney(o.return_amount)} returned (${o.returns})</div>` : ""}
            </div>
        </div>
    `;
}

async function loadSalesSummary() {
    if (!salesSummaryBody) return;
    salesSummaryBody.innerHTML = `<div class="text-center text-sm text-gray-400 py-6">Loading...</div>`;
    try {
        const json = await fetchJSON(`${BASE_URL}/orders/get_counter_sale_stats.php`);
        if (json.success) {
            renderSalesSummary(json);
        } else {
            salesSummaryBody.innerHTML = `<div class="text-center text-sm text-red-500 py-6">${json.message || "Failed to load summary"}</div>`;
        }
    } catch (err) {
        console.error("Failed to load sales summary:", err);
        salesSummaryBody.innerHTML = `<div class="text-center text-sm text-red-500 py-6">Server communication error.</div>`;
    }
}

function openSalesSummaryModal() {
    if (!salesSummaryModal) return;
    salesSummaryModal.classList.remove("hidden");
    loadSalesSummary();
}
function closeSalesSummaryModal() {
    if (salesSummaryModal) salesSummaryModal.classList.add("hidden");
}

if (salesSummaryBtn) salesSummaryBtn.addEventListener("click", openSalesSummaryModal);
if (closeSalesSummaryBtn) closeSalesSummaryBtn.addEventListener("click", closeSalesSummaryModal);
if (salesSummaryModal) {
    salesSummaryModal.addEventListener("click", (e) => {
        if (e.target === salesSummaryModal) closeSalesSummaryModal();
    });
}

// ============ Sale Return ============
function openReturnModal() {
    if (!returnModal) return;
    returnModal.classList.remove("hidden");
    returnInvoiceInput.value = "";
    returnItemsWrap.classList.add("hidden");
    returnLookupMsg.classList.add("hidden");
    currentReturnOrder = null;
    setTimeout(() => returnInvoiceInput?.focus(), 50);
}
function closeReturnModal() {
    if (returnModal) returnModal.classList.add("hidden");
    currentReturnOrder = null;
}

function showReturnMsg(msg, isError = true) {
    if (!returnLookupMsg) return;
    returnLookupMsg.textContent = msg;
    returnLookupMsg.className = `text-xs font-semibold ${isError ? "text-red-500" : "text-emerald-600"}`;
}

function renderReturnItems(items) {
    if (!returnItemsTableBody) return;
    returnItemsTableBody.innerHTML = "";
    items.forEach((item) => {
        const row = document.createElement("tr");
        const disabled = item.returnable_qty <= 0;
        row.innerHTML = `
            <td class="py-2 font-bold text-gray-800 dark:text-gray-200">${item.product_name}</td>
            <td class="py-2 text-center">${item.quantity}</td>
            <td class="py-2 text-center ${disabled ? "text-gray-400" : "text-emerald-600 font-bold"}">${item.returnable_qty}</td>
            <td class="py-2 text-center">
                <input type="number" min="0" max="${item.returnable_qty}" step="1" value="0" ${disabled ? "disabled" : ""}
                    data-order-item-id="${item.order_item_id}" data-unit-price="${item.unit_price}"
                    class="return-qty-input w-16 text-center border border-gray-300 dark:border-gray-600 rounded-md px-1 py-1 bg-white dark:bg-gray-700 text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-red-400" />
            </td>
            <td class="py-2 text-right font-bold return-line-total">0.00</td>
        `;
        returnItemsTableBody.appendChild(row);
    });

    returnItemsTableBody.querySelectorAll(".return-qty-input").forEach((input) => {
        input.addEventListener("input", updateReturnTotal);
    });
    updateReturnTotal();
}

function updateReturnTotal() {
    let total = 0;
    returnItemsTableBody.querySelectorAll(".return-qty-input").forEach((input) => {
        const qty = Math.max(0, parseFloat(input.value) || 0);
        const max = parseFloat(input.max) || 0;
        const clamped = Math.min(qty, max);
        if (clamped !== qty) input.value = clamped;

        const price = parseFloat(input.dataset.unitPrice) || 0;
        const lineTotal = clamped * price;
        const cell = input.closest("tr").querySelector(".return-line-total");
        if (cell) cell.textContent = lineTotal.toFixed(2);
        total += lineTotal;
    });
    if (returnTotalDisplay) returnTotalDisplay.textContent = formatMoney(total);
}

async function findInvoiceForReturn() {
    const invoiceNo = (returnInvoiceInput?.value || "").trim();
    if (!invoiceNo) {
        showReturnMsg("Please enter an invoice number.");
        returnLookupMsg.classList.remove("hidden");
        return;
    }

    showReturnMsg("Searching...", false);
    returnLookupMsg.classList.remove("hidden");
    returnItemsWrap.classList.add("hidden");

    try {
        const json = await fetchJSON(`${BASE_URL}/orders/get_sale_for_return.php?invoice_no=${encodeURIComponent(invoiceNo)}`);
        if (!json.success) {
            showReturnMsg(json.message || "Invoice not found.");
            currentReturnOrder = null;
            return;
        }

        currentReturnOrder = json.order;
        returnLookupMsg.classList.add("hidden");
        returnInvoiceMeta.textContent = `Invoice: ${json.order.invoice_no}  |  Customer: ${json.order.customer_name}  |  Date: ${json.order.order_date}  |  Total: Rs ${formatMoney(json.order.total)}`;
        renderReturnItems(json.items);
        returnItemsWrap.classList.remove("hidden");
    } catch (err) {
        console.error("Return lookup error:", err);
        showReturnMsg("Server communication error.");
    }
}

async function submitReturn() {
    if (!currentReturnOrder) return;

    const items = [];
    returnItemsTableBody.querySelectorAll(".return-qty-input").forEach((input) => {
        const qty = parseFloat(input.value) || 0;
        if (qty > 0) {
            items.push({
                order_item_id: parseInt(input.dataset.orderItemId, 10),
                quantity: qty
            });
        }
    });

    if (items.length === 0) {
        alert("Please enter a return quantity for at least one item.");
        return;
    }

    submitReturnBtn.disabled = true;
    submitReturnBtn.textContent = "Processing...";

    try {
        const response = await fetch(`${BASE_URL}/orders/add_sale_return.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                order_id: currentReturnOrder.order_id,
                items: items,
                remarks: returnRemarks?.value || ""
            })
        });
        const result = await response.json();

        if (result.success) {
            alert(`Return ${result.return_no} processed successfully. Amount: Rs ${formatMoney(result.total)}`);
            closeReturnModal();
            loadProducts(); // refresh stock figures shown on product cards
        } else {
            alert(result.message || "Failed to process return.");
        }
    } catch (err) {
        console.error("Return submit error:", err);
        alert("Server communication error. Check console.");
    } finally {
        submitReturnBtn.disabled = false;
        submitReturnBtn.textContent = "Process Return";
    }
}

if (openReturnBtn) openReturnBtn.addEventListener("click", openReturnModal);
if (closeReturnBtn) closeReturnBtn.addEventListener("click", closeReturnModal);
if (returnModal) {
    returnModal.addEventListener("click", (e) => {
        if (e.target === returnModal) closeReturnModal();
    });
}
if (findInvoiceBtn) findInvoiceBtn.addEventListener("click", findInvoiceForReturn);
if (returnInvoiceInput) {
    returnInvoiceInput.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
            e.preventDefault();
            findInvoiceForReturn();
        }
    });
}
if (submitReturnBtn) submitReturnBtn.addEventListener("click", submitReturn);

// ============ Global Hotkeys (F2 to Save) ============
document.addEventListener("keydown", function (e) {
    if (e.key === "F2") {
        e.preventDefault();
        saveOrder();
    }
});

// ============ Init ============
document.addEventListener("DOMContentLoaded", function () {
    loadCustomers();
    loadProducts();
});