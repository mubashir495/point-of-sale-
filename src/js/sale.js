// ============ Configuration ============
const BASE_URL = "../../action";

// ============ Element References ============
const customerSearchInp   = document.getElementById("saleCustomerSearch");
const customerIdInp       = document.getElementById("saleCustomerId");
const customerDropdownEl  = document.getElementById("saleCustomerDropdown");
const quickAddCustomerBtn = document.getElementById("quickAddCustomerBtn");
const prevBalanceInp    = document.getElementById("salePrevBalance");

// Quick Add Customer modal elements
const quickAddModal        = document.getElementById("quickAddCustomerModal");
const quickAddForm         = document.getElementById("quickAddCustomerForm");
const quickAddName         = document.getElementById("quickAddCustomerName");
const quickAddPhone        = document.getElementById("quickAddCustomerPhone");
const quickAddEmail        = document.getElementById("quickAddCustomerEmail");
const quickAddCity         = document.getElementById("quickAddCustomerCity");
const quickAddArea         = document.getElementById("quickAddCustomerArea");
const quickAddOpeningBal   = document.getElementById("quickAddCustomerOpeningBalance");
const quickAddAddress      = document.getElementById("quickAddCustomerAddress");
const quickAddClose        = document.getElementById("quickAddCustomerClose");
const quickAddCancel       = document.getElementById("quickAddCustomerCancel");
const productSelect     = document.getElementById("saleProductSelect");
const qtyInput          = document.getElementById("saleProductQty");
const priceInput        = document.getElementById("saleProductPrice");
const addBtn            = document.getElementById("addSaleItemBtn");
const tbody             = document.getElementById("saleItemsTableBody");
const shiftSelect       = document.getElementById("saleShift");
const employeeSelect    = document.getElementById("saleEmployee");
const summaryTotal      = document.getElementById("summaryTotal");
const summaryDiscount   = document.getElementById("summaryDiscount");
const summaryDiscountPc = document.getElementById("summaryDiscountPercent");
const summaryNet        = document.getElementById("summaryNet");
const summaryPaid       = document.getElementById("summaryPaid");
const summaryRemaining  = document.getElementById("summaryRemaining");
const summaryDate       = document.getElementById("summaryDate");
const summaryPO         = document.getElementById("summaryPO");
const summaryInvoiceNo  = document.getElementById("summaryInvoiceNo");
const saveSaleBtn       = document.getElementById("saveSaleBtn");
const holdSaleBtn       = document.getElementById("holdSaleBtn");
const quotationsList    = document.getElementById("quotationsList");

// ============ State ============
let products = [];
let cart = [];
let customers = []; // cached list of all customers, for client-side search
let currentCustomerId = null;
let currentCustomerData = null; // store full customer object for invoice

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
        customers = Array.isArray(json) ? json : (json.data || []);
    } catch (err) {
        console.error("Failed to load customers:", err);
        customers = [];
    }
}

function customerDisplayText(customer) {
    const code = customer.customer_code || '';
    const name = customer.name || '';
    const phone = customer.phone ? ` (${customer.phone})` : '';
    return `${code ? code + ' - ' : ''}${name}${phone}`;
}

function selectCustomer(customer) {
    currentCustomerId = customer.id;
    currentCustomerData = {
        name: customer.name || "",
        phone: customer.phone || "",
        address: customer.address || "",
        balance: customer.current_balance || 0
    };
    customerIdInp.value = customer.id;
    customerSearchInp.value = customerDisplayText(customer);
    prevBalanceInp.value = customer.current_balance || 0;
    hideCustomerDropdown();
}

function clearSelectedCustomer() {
    currentCustomerId = null;
    currentCustomerData = null;
    customerIdInp.value = "";
    prevBalanceInp.value = "";
}

function renderCustomerDropdown(list) {
    if (!list.length) {
        customerDropdownEl.innerHTML = `<div class="px-3 py-2 text-gray-500">No matching customers</div>`;
    } else {
        customerDropdownEl.innerHTML = list.slice(0, 50).map(c => `
            <div class="customer-option px-3 py-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-100" data-id="${c.id}">
                ${customerDisplayText(c)}
            </div>
        `).join("");
    }
    customerDropdownEl.classList.remove("hidden");
}

function hideCustomerDropdown() {
    customerDropdownEl.classList.add("hidden");
}

customerDropdownEl.addEventListener("mousedown", function (e) {
    // mousedown (not click) fires before the search input's blur event
    const item = e.target.closest(".customer-option");
    if (!item) return;
    const customer = customers.find(c => String(c.id) === item.dataset.id);
    if (customer) selectCustomer(customer);
});

customerSearchInp.addEventListener("focus", function () {
    renderCustomerDropdown(customers);
});

customerSearchInp.addEventListener("input", function () {
    // Any manual edit invalidates the previously selected customer
    clearSelectedCustomer();
    const term = this.value.trim().toLowerCase();
    const filtered = !term
        ? customers
        : customers.filter(c =>
            (c.name || "").toLowerCase().includes(term) ||
            (c.phone || "").toLowerCase().includes(term) ||
            (c.customer_code || "").toLowerCase().includes(term)
          );
    renderCustomerDropdown(filtered);
});

customerSearchInp.addEventListener("blur", function () {
    // slight delay so a click on a dropdown option can register first
    setTimeout(hideCustomerDropdown, 150);
});

document.addEventListener("click", function (e) {
    if (!e.target.closest("#saleCustomerSearch") && !e.target.closest("#saleCustomerDropdown")) {
        hideCustomerDropdown();
    }
});

// ============ Quick Add Customer ============
async function loadQuickAddCities() {
    try {
        const json = await fetchJSON(`${BASE_URL}/customers/get_cities.php`);
        const cities = Array.isArray(json) ? json : (json.data || []);
        quickAddCity.innerHTML = `<option value="">Select City</option>`;
        cities.forEach(city => {
            const opt = document.createElement("option");
            opt.value = city.id;
            opt.textContent = city.city_name;
            quickAddCity.appendChild(opt);
        });
    } catch (err) {
        console.error("Failed to load cities:", err);
    }
}

async function loadQuickAddAreas(cityId) {
    quickAddArea.innerHTML = `<option value="">Select Area</option>`;
    if (!cityId) return;
    try {
        const json = await fetchJSON(`${BASE_URL}/customers/get-area.php?city_id=${cityId}`);
        const areas = Array.isArray(json) ? json : (json.data || []);
        areas.forEach(area => {
            const opt = document.createElement("option");
            opt.value = area.id;
            opt.textContent = area.area_name;
            quickAddArea.appendChild(opt);
        });
    } catch (err) {
        console.error("Failed to load areas:", err);
    }
}

quickAddCity.addEventListener("change", function () {
    loadQuickAddAreas(this.value);
});

function openQuickAddModal() {
    quickAddForm.reset();
    quickAddOpeningBal.value = "0.00";
    quickAddArea.innerHTML = `<option value="">Select Area</option>`;
    loadQuickAddCities();
    quickAddModal.classList.remove("hidden");
    quickAddModal.classList.add("flex");
    quickAddName.focus();
}

function closeQuickAddModal() {
    quickAddModal.classList.add("hidden");
    quickAddModal.classList.remove("flex");
}

quickAddCustomerBtn.addEventListener("click", openQuickAddModal);
quickAddClose.addEventListener("click", closeQuickAddModal);
quickAddCancel.addEventListener("click", closeQuickAddModal);
quickAddModal.addEventListener("click", function (e) {
    if (e.target === quickAddModal) closeQuickAddModal();
});

quickAddForm.addEventListener("submit", async function (e) {
    e.preventDefault();

    if (!quickAddName.value.trim() || !quickAddPhone.value.trim() || !quickAddCity.value || !quickAddArea.value) {
        alert("Please fill in all required fields.");
        return;
    }

    const payload = {
        name: quickAddName.value.trim(),
        phone: quickAddPhone.value.trim(),
        email: quickAddEmail.value.trim(),
        address: quickAddAddress.value.trim(),
        opening_balance: quickAddOpeningBal.value || 0,
        city_id: quickAddCity.value,
        area_id: quickAddArea.value
    };

    try {
        const response = await fetch(`${BASE_URL}/customers/add_customer.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });
        const result = await response.json();

        if (result.success) {
            const newCustomer = {
                id: result.id,
                customer_code: result.customer_code || "",
                name: payload.name,
                phone: payload.phone,
                email: payload.email,
                address: payload.address,
                current_balance: parseFloat(payload.opening_balance) || 0
            };
            customers.push(newCustomer);
            selectCustomer(newCustomer);
            closeQuickAddModal();
        } else {
            alert(result.message || "Error saving customer.");
        }
    } catch (err) {
        console.error("Quick add customer error:", err);
        alert("Server error. Please try again.");
    }
});

// ============ Load Products ============
async function loadProducts() {
    try {
        const json = await fetchJSON(`${BASE_URL}/products/get-product.php`);
        products = Array.isArray(json) ? json : (json.data || []);
        productSelect.innerHTML = `<option value="">Select Product</option>`;
        products.forEach(product => {
            const opt = document.createElement("option");
            opt.value = product.id;
            opt.textContent = product.product_name || product.name || "Unknown";
            productSelect.appendChild(opt);
        });
    } catch (err) {
        console.error("Failed to load products:", err);
        productSelect.innerHTML = `<option value="">Error loading products</option>`;
    }
}

productSelect.addEventListener("change", function () {
    const product = products.find(x => x.id == this.value);
    if (product) {
        priceInput.value = product.sale_price || 0;
    } else {
        priceInput.value = "";
    }
});

// ============ Load Employees (by shift) ============
async function loadEmployees() {
    const shift = shiftSelect.value;
    try {
        const json = await fetchJSON(`${BASE_URL}/orders/get_employees.php?shift=${encodeURIComponent(shift)}`);
        const employees = Array.isArray(json) ? json : (json.data || []);
        employeeSelect.innerHTML = `<option value="">Select Supplier</option>`;
        employees.forEach(emp => {
            const opt = document.createElement("option");
            opt.value = emp.id;
            opt.textContent = emp.full_name || emp.name || `Employee ${emp.id}`;
            employeeSelect.appendChild(opt);
        });
    } catch (err) {
        console.error("Failed to load employees:", err);
        employeeSelect.innerHTML = `<option value="">Error loading suppliers</option>`;
    }
}

shiftSelect.addEventListener("change", loadEmployees);

// ============ Load Quotations / Hold ============
async function loadQuotations() {
    try {
        const json = await fetchJSON(`${BASE_URL}/quotations/get_quotations.php`);
        const quotations = Array.isArray(json) ? json : (json.data || []);
        quotationsList.innerHTML = "";
        if (quotations.length === 0) {
            quotationsList.innerHTML = `<div class="px-5 py-3 text-sm text-gray-500">No quotations</div>`;
        } else {
            quotations.forEach(q => {
                const div = document.createElement("div");
                div.className = "flex items-center justify-between px-5 py-3 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/40 cursor-pointer";
                div.innerHTML = `<span>${q.customer_name || 'Unknown'}</span><span>${q.total || '0.00'}</span>`;
                div.dataset.id = q.id;
                div.addEventListener("click", () => loadQuotation(q.id));
                quotationsList.appendChild(div);
            });
        }
    } catch (err) {
        console.error("Failed to load quotations:", err);
        quotationsList.innerHTML = `<div class="px-5 py-3 text-sm text-red-500">Error loading quotations</div>`;
    }
}

async function loadQuotation(id) {
    try {
        const json = await fetchJSON(`${BASE_URL}/quotations/get_quotation.php?id=${id}`);
        if (!json.success) {
            alert(json.message || "Failed to load quotation.");
            return;
        }
        const data = json.data;
        
        // Populate customer
        if (data.customer_id) {
            const customer = customers.find(c => String(c.id) === String(data.customer_id));
            if (customer) {
                selectCustomer(customer);
            } else {
                // Not in the loaded cache — still trust the id from the quotation,
                // just show the name for display since we don't have full details.
                currentCustomerId = data.customer_id;
                currentCustomerData = { name: data.customer_name || '', phone: '', address: '', balance: 0 };
                customerIdInp.value = data.customer_id;
                customerSearchInp.value = data.customer_name || '';
            }
        }

        // Shift
        if (data.shift) shiftSelect.value = data.shift;
        
        // Supplier/Employee
        if (data.employee_id) {
            const empOpt = employeeSelect.querySelector(`option[value="${data.employee_id}"]`);
            if (empOpt) employeeSelect.value = data.employee_id;
        }
        
        // Date
        if (data.order_date) summaryDate.value = data.order_date;
        
        // Cart items
        cart = (data.items || []).map(item => ({
            product_id: item.product_id,
            product_name: item.product_name || 'Product',
            qty: item.quantity || item.qty || 0,
            price: item.unit_price || item.price || 0,
            total: (item.quantity || item.qty || 0) * (item.unit_price || item.price || 0)
        }));
        renderTable();
        
        // Summary fields
        summaryTotal.value = (data.subtotal || 0).toFixed(2);
        summaryDiscount.value = (data.discount || 0).toFixed(2);
        summaryDiscountPc.checked = false;
        summaryNet.value = (data.total || 0).toFixed(2);
        summaryPaid.value = (data.paid || 0).toFixed(2);
        summaryRemaining.value = ((data.total || 0) - (data.paid || 0)).toFixed(2);
        summaryPO.value = data.quotation_no || '';
        summaryInvoiceNo.value = '';

        alert(`Quotation #${data.quotation_no || id} loaded.`);
    } catch (err) {
        console.error("Error loading quotation:", err);
        alert("Failed to load quotation.");
    }
}
// ============ Add Item to Cart ============
addBtn.addEventListener("click", () => {
    const product = products.find(x => x.id == productSelect.value);
    if (!product) {
        alert("Please select a product.");
        return;
    }

    const qty = parseFloat(qtyInput.value);
    const price = parseFloat(priceInput.value);

    if (!qty || qty <= 0 || isNaN(price) || price <= 0) {
        alert("Enter valid quantity and price.");
        return;
    }

    const total = qty * price;

    cart.push({
        product_id: product.id,
        product_name: product.product_name || product.name,
        qty,
        price,
        total
    });

    renderTable();
    // clear entry row
    productSelect.value = "";
    qtyInput.value = "";
    priceInput.value = "";
    productSelect.focus();
});

// ============ Render Items Table ============
function renderTable() {
    tbody.innerHTML = "";
    let grandTotal = 0;

    cart.forEach((item, index) => {
        grandTotal += item.total;
        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td class="border px-3 py-2">${index + 1}</td>
            <td class="border px-3 py-2">${item.product_name}</td>
            <td class="border px-3 py-2 text-right">${item.price.toFixed(2)}</td>
            <td class="border px-3 py-2 text-right">
                <input type="number" min="0" step="1" value="${item.qty}" 
                       class="qty-edit w-16 border rounded px-1 py-0.5 text-right"
                       data-index="${index}" />
            </td>
            <td class="border px-3 py-2 text-right">${item.total.toFixed(2)}</td>
            <td class="border px-3 py-2 text-center">
                <button type="button" onclick="removeItem(${index})" class="text-red-600 hover:text-red-800">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Attach change events to quantity inputs inside table
    document.querySelectorAll(".qty-edit").forEach(input => {
        input.addEventListener("change", function () {
            const idx = parseInt(this.dataset.index);
            const newQty = parseFloat(this.value) || 0;
            if (newQty <= 0) {
                cart.splice(idx, 1);
            } else {
                cart[idx].qty = newQty;
                cart[idx].total = newQty * cart[idx].price;
            }
            renderTable();
        });
    });

    summaryTotal.value = grandTotal.toFixed(2);
    recalcSummary();
}

// ============ Remove Item (global) ============
function removeItem(index) {
    cart.splice(index, 1);
    renderTable();
}

// ============ Summary Calculations ============
function recalcSummary() {
    const total = parseFloat(summaryTotal.value) || 0;
    let discount = parseFloat(summaryDiscount.value) || 0;

    if (summaryDiscountPc.checked) {
        discount = (total * discount) / 100;
    }

    const net = total - discount;
    summaryNet.value = net.toFixed(2);

    const paid = parseFloat(summaryPaid.value) || 0;
    const remaining = net - paid;
    summaryRemaining.value = remaining.toFixed(2);
}

summaryDiscount.addEventListener("input", recalcSummary);
summaryDiscountPc.addEventListener("change", recalcSummary);
summaryPaid.addEventListener("input", recalcSummary);

// ============ Build Sale Payload ============
function buildSalePayload() {
    const subtotal = parseFloat(summaryTotal.value) || 0;
    const discountInput = parseFloat(summaryDiscount.value) || 0;
    let discountAmount = discountInput;
    if (summaryDiscountPc.checked) {
        discountAmount = (subtotal * discountInput) / 100;
    }
    const net = subtotal - discountAmount;

    return {
        customer_id: currentCustomerId,
        employee_id: employeeSelect.value,
        order_date: summaryDate.value || new Date().toISOString().split("T")[0],
        delivery_date: null,
        shift: shiftSelect.value,
        subtotal: subtotal,
        discount: discountAmount,
        total: net,
        status: 'Delivery Mode',
        remarks: "",
        items: cart.map(item => ({
            product_id: item.product_id,
            quantity: item.qty,
            unit_price: item.price,
            total: item.total
        }))
    };
}

// ============ Reset form ============
function resetSaleForm() {
    cart = [];
    renderTable();
    summaryTotal.value = "0.00";
    summaryDiscount.value = "0";
    summaryNet.value = "0.00";
    summaryPaid.value = "0";
    summaryRemaining.value = "0.00";
    summaryPO.value = "";
    summaryInvoiceNo.value = "";
    productSelect.value = "";
    qtyInput.value = "";
    priceInput.value = "";
}

// ============ Show Invoice Modal ============
function showInvoiceModal(invoiceNo, customer, items, totals) {
    // Remove any existing invoice modal
    const oldModal = document.getElementById("invoicePrintModal");
    if (oldModal) oldModal.remove();

    // Build the modal HTML
    const modal = document.createElement("div");
    modal.id = "invoicePrintModal";
    modal.className = "fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4";
    modal.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-4xl max-h-[95vh] overflow-y-auto">
            <!-- Header with close & print buttons -->
            <div class="flex items-center justify-between px-6 py-3 border-b dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Invoice</h2>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm flex items-center gap-2">
                        <i class="fa-solid fa-print"></i> Print
                    </button>
                    <button onclick="this.closest('#invoicePrintModal').remove()" class="text-gray-400 hover:text-red-500 text-2xl">&times;</button>
                </div>
            </div>

            <!-- Invoice Content -->
            <div class="p-6" id="invoicePrintArea">
                <!-- Company Header -->
                <div class="flex justify-between items-start mb-5 pb-4 border-b dark:border-gray-700">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800 dark:text-white">NOWRUZ DAIRY</h3>
                        <p class="text-sm text-gray-500">Multan</p>
                        <p class="text-sm text-gray-500">Contact: 03114683143</p>
                    </div>
                    <div class="text-right">
                        <h4 class="font-semibold text-gray-700 dark:text-gray-300">Invoice #${invoiceNo}</h4>
                        <p class="text-sm text-gray-500">${new Date().toLocaleDateString()}</p>
                    </div>
                </div>

                <!-- Customer Details -->
                <div class="mb-5">
                    <p class="text-xs text-gray-400 uppercase font-semibold">Bill To</p>
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">${customer.name || ''}</h3>
                    <p class="text-sm text-gray-500">Contact: ${customer.phone || ''}</p>
                    <p class="text-sm text-gray-500">Address: ${customer.address || ''}</p>
                </div>

                <!-- Items Table -->
                <table class="w-full border text-sm mb-4">
                    <thead class="bg-gray-900 text-white">
                        <tr>
                            <th class="px-3 py-2 text-left">SR#</th>
                            <th class="px-3 py-2 text-left">PRODUCT DESCRIPTION</th>
                            <th class="px-3 py-2 text-right">QTY</th>
                            <th class="px-3 py-2 text-right">UNIT PRICE</th>
                            <th class="px-3 py-2 text-right">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody class="dark:text-gray-300">
                        ${items.map((item, idx) => `
                            <tr>
                                <td class="border px-3 py-2">${idx + 1}</td>
                                <td class="border px-3 py-2">${item.product_name}</td>
                                <td class="border px-3 py-2 text-right">${item.qty.toFixed(2)}</td>
                                <td class="border px-3 py-2 text-right">${item.price.toFixed(2)}</td>
                                <td class="border px-3 py-2 text-right">${item.total.toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>

                <!-- Totals -->
                <div class="flex justify-end">
                    <div class="w-72 space-y-1">
                        <div class="flex justify-between text-sm">
                            <span class="font-semibold">Previous Balance:</span>
                            <span>${(totals.prevBalance || 0).toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="font-semibold">Current Bill:</span>
                            <span>${totals.subtotal.toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between text-lg font-bold border-t pt-2">
                            <span>Grand Total:</span>
                            <span>${totals.net.toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="font-semibold">Amount Paid:</span>
                            <span>${totals.paid.toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between text-sm font-bold text-red-600">
                            <span>New Remaining:</span>
                            <span>${totals.remaining.toFixed(2)}</span>
                        </div>
                        <hr class="my-2">
                        <div class="flex justify-between text-sm">
                            <span>Total Bill:</span>
                            <span>${totals.subtotal.toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>Discount:</span>
                            <span>-${(totals.discount || 0).toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between text-base font-bold">
                            <span>Net Bill:</span>
                            <span>${totals.net.toFixed(2)}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
}

// ============ Save Order (Invoice) ============
async function saveOrder() {
    if (!currentCustomerId) {
        alert("Please select a customer.");
        return;
    }
    if (!employeeSelect.value) {
        alert("Please select a supplier.");
        return;
    }
    if (cart.length === 0) {
        alert("Add at least one product.");
        return;
    }

    const payload = buildSalePayload();

    try {
        const response = await fetch(`${BASE_URL}/orders/add_order.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            const invoiceNo = result.invoice_no || `INV-${Date.now()}`;
            // Update summary fields
            summaryInvoiceNo.value = invoiceNo;
            summaryPO.value = result.order_no || '';

            // Prepare customer data for invoice
            const customer = currentCustomerData || {
                name: customerSearchInp.value || 'Walk-in',
                phone: '',
                address: ''
            };

            // Prepare totals
            const totals = {
                subtotal: parseFloat(summaryTotal.value) || 0,
                discount: parseFloat(summaryDiscount.value) || 0,
                net: parseFloat(summaryNet.value) || 0,
                paid: parseFloat(summaryPaid.value) || 0,
                remaining: parseFloat(summaryRemaining.value) || 0,
                prevBalance: parseFloat(prevBalanceInp.value) || 0
            };

            // Show invoice modal
            showInvoiceModal(invoiceNo, customer, cart, totals);

            // Reset form (keep customer and shift)
            resetSaleForm();

            alert(`Order saved successfully!\nInvoice: ${invoiceNo}`);

        } else {
            alert(result.message || "Unknown error occurred.");
        }
    } catch (e) {
        console.error("Save error:", e);
        alert("Server error. Please check console.");
    }
}

saveSaleBtn.addEventListener("click", saveOrder);

// ============ Save Quotation / Hold ============
async function saveQuotation() {
    if (!currentCustomerId) {
        alert("Please select a customer.");
        return;
    }
    if (!employeeSelect.value) {
        alert("Please select a supplier.");
        return;
    }
    if (cart.length === 0) {
        alert("Add at least one product.");
        return;
    }

    const payload = buildSalePayload();

    try {
        const response = await fetch(`${BASE_URL}/quotations/add_quotation.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            alert(`Quotation saved successfully! ID: ${result.id}`);
            // Reset form
            resetSaleForm();
            // Reload quotations list
            loadQuotations();
        } else {
            alert(result.message || "Failed to save quotation.");
        }
    } catch (e) {
        console.error("Quotation save error:", e);
        alert("Server error. Please check console.");
    }
}

holdSaleBtn.addEventListener("click", saveQuotation);

// ============ Init ============
document.addEventListener("DOMContentLoaded", function () {
    if (summaryDate) {
        summaryDate.value = new Date().toISOString().split("T")[0];
    }
    loadCustomers();
    loadProducts();
    loadEmployees();
    loadQuotations();
});