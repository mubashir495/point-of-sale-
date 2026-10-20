(function () {
  "use strict";
  function on(el, event, handler) {
    if (el) el.addEventListener(event, handler);
  }

  document.addEventListener("DOMContentLoaded", function () {
    // ---- DOM refs ----
    const modal = document.getElementById("modalOverlay");
    const customerModal = document.getElementById("customerModal");
    const openModalBtn = document.getElementById("openModal");
    const closeModalBtn = document.getElementById("closeModal");
    const closeBtn = document.getElementById("closeBtn");
    const closeCustomerModal = document.getElementById("closeCustomerModal");
    const closeCustomerBtn = document.getElementById("closeCustomerBtn");
    const modalTitle = document.getElementById("modalTitle");
    const orderForm = document.getElementById("orderForm");
    const orderIdInput = document.getElementById("orderId");

    const customerSearch = document.getElementById("customerSearch");
    const customerSearchResults = document.getElementById(
      "customerSearchResults",
    );
    const selectedCustomerId = document.getElementById("selectedCustomerId");
    const selectedCustomerInfo = document.getElementById("selectedCustomerInfo");
    const displayCustomerName = document.getElementById("displayCustomerName");
    const displayCustomerPhone = document.getElementById("displayCustomerPhone");
    const displayCustomerArea = document.getElementById("displayCustomerArea");
    const displayCustomerBalance = document.getElementById(
      "displayCustomerBalance",
    );
    const displayCustomerCity = document.getElementById("displayCustomerCity");
    const displayCustomerAddress = document.getElementById(
      "displayCustomerAddress",
    );

    // Order fields
    const orderDate = document.getElementById("orderDate");
    const deliveryDate = document.getElementById("deliveryDate");
    const orderShift = document.getElementById("orderShift");
    const employeeSelect = document.getElementById("employeeSelect");
    const orderRemarks = document.getElementById("orderRemarks");
    const categorySelect = document.getElementById("categorySelect");

    // Product container
    const productContainer = document.getElementById("productContainer");
    const addProductBtn = document.getElementById("addProductBtn");
    const productError = document.getElementById("productError");

    const subtotalInput = document.getElementById("subtotal");
    const discountInput = document.getElementById("discount");
    const totalAmountInput = document.getElementById("totalAmount");

    const customerForm = document.getElementById("customerForm");
    const newCustomerName = document.getElementById("newCustomerName");
    const newCustomerPhone = document.getElementById("newCustomerPhone");
    const newCustomerEmail = document.getElementById("newCustomerEmail");
    const newCustomerCity = document.getElementById("newCustomerCity");
    const newCustomerArea = document.getElementById("newCustomerArea");
    const newCustomerBalance = document.getElementById("newCustomerBalance");
    const newCustomerAddress = document.getElementById("newCustomerAddress");

    // Table
    const tableBody = document.getElementById("ordersTableBody");

    // Tabs
    const tabCustomerOrders = document.getElementById("tabCustomerOrders");
    const tabEmployeeOrders = document.getElementById("tabEmployeeOrders");
    const customerOrdersPanel = document.getElementById("customerOrdersPanel");
    const employeeOrdersPanel = document.getElementById("employeeOrdersPanel");
    const employeeOrdersTableBody = document.getElementById(
      "employeeOrdersTableBody",
    );

    // View Order modal
    const viewOrderModal = document.getElementById("viewOrderModal");
    const closeViewOrderModal = document.getElementById("closeViewOrderModal");
    const closeViewOrderBtn = document.getElementById("closeViewOrderBtn");
    const printOrderBtn = document.getElementById("printOrderBtn");

    // Pay modal
    const payModal = document.getElementById("payModal");
    const closePayModal = document.getElementById("closePayModal");
    const closePayBtn = document.getElementById("closePayBtn");
    const postTxnBtn = document.getElementById("postTxnBtn");
    const payCustomerId = document.getElementById("payCustomerId");
    const txnType = document.getElementById("txnType");
    const txnAmount = document.getElementById("txnAmount");
    const txnRemarks = document.getElementById("txnRemarks");

    // Invoice modal
    const invoiceModal = document.getElementById("invoiceModal");
    const closeInvoiceModal = document.getElementById("closeInvoiceModal");
    const invLoadBtn = document.getElementById("invLoadBtn");
    const invPrintBtn = document.getElementById("invPrintBtn");
    const invDateFrom = document.getElementById("invDateFrom");
    const invDateTo = document.getElementById("invDateTo");

    // Ledger modal
    const ledgerModal = document.getElementById("ledgerModal");
    const closeLedgerModal = document.getElementById("closeLedgerModal");
    const ledLoadBtn = document.getElementById("ledLoadBtn");
    const ledPrintBtn = document.getElementById("ledPrintBtn");
    const ledDateFrom = document.getElementById("ledDateFrom");
    const ledDateTo = document.getElementById("ledDateTo");

    // Filters (customer tab)
    const statusFilterEl = document.getElementById("statusFilter");
    const customerFilterEl = document.getElementById("customerFilter");
    const searchInputEl = document.getElementById("searchInput");
    const dateFromEl = document.getElementById("dateFrom");
    const dateToEl = document.getElementById("dateTo");

    // Filters (employee tab)
    const empSearchInputEl = document.getElementById("empSearchInput");
    const empShiftFilterEl = document.getElementById("empShiftFilter");

    // ---- State ----
    let editingOrderId = 0;
    let selectedCustomer = null;
    let allProducts = [];
    let allCategories = [];
    let activeCustomerIdForModals = null;

    // ---- API URLs ----
    const API_BASE = "../../action";

    // ================= Helpers =================
    function showModal(title = "New Order", orderId = 0) {
      editingOrderId = orderId;
      if (modalTitle) modalTitle.textContent = title;
      if (orderIdInput) orderIdInput.value = orderId;
      if (modal) {
        modal.classList.remove("hidden");
        modal.classList.add("flex");
      }
      if (orderId === 0) {
        if (orderForm) orderForm.reset();
        if (productContainer) productContainer.innerHTML = "";
        addProductRow();
        updateSummary();
        if (selectedCustomerId) selectedCustomerId.value = "";
        if (selectedCustomerInfo) selectedCustomerInfo.classList.add("hidden");
        if (customerSearch) customerSearch.value = "";
        const today = new Date().toISOString().split("T")[0];
        if (orderDate) orderDate.value = today;
      }
      if (productError) productError.classList.add("hidden");
      loadCategories();
      loadEmployees(orderShift ? orderShift.value : "");
      if (orderId > 0) loadOrderForEdit(orderId);
    }

    function hideModal() {
      if (!modal) return;
      modal.classList.add("hidden");
      modal.classList.remove("flex");
    }

    function showCustomerModal() {
      if (!customerModal) return;
      customerModal.classList.remove("hidden");
      customerModal.classList.add("flex");
      loadCitiesForCustomer();
    }

    function hideCustomerModal() {
      if (customerModal) {
        customerModal.classList.add("hidden");
        customerModal.classList.remove("flex");
      }
      if (customerForm) customerForm.reset();
    }

    // ---- API Calls ----
    async function fetchAPI(url, options = {}) {
      try {
        const res = await fetch(url, options);
        if (!res.ok) throw new Error("Network error: " + res.status);
        return await res.json();
      } catch (e) {
        console.error("API Error:", e);
        return [];
      }
    }

    // ---- Load Categories ----
    async function loadCategories() {
      const data = await fetchAPI(`${API_BASE}/category/get-category.php`);
      allCategories = data;
      if (categorySelect) {
        categorySelect.innerHTML = '<option value="">All Categories</option>';
        if (data && data.length > 0) {
          data.forEach((cat) => {
            const opt = document.createElement("option");
            opt.value = cat.id;
            opt.textContent = cat.name;
            categorySelect.appendChild(opt);
          });
        }
      }
      loadProducts(categorySelect ? categorySelect.value : "");
    }

    // ---- Load Products (Category Based) ----
    async function loadProducts(categoryId) {
      try {
        const url = `${API_BASE}/orders/get_products.php?category_id=${categoryId}`;
        const data = await fetchAPI(url);
        allProducts = Array.isArray(data) ? data : [];
        document.querySelectorAll(".product-select").forEach((select) => {
          populateProductDropdown(select);
        });
      } catch (error) {
        console.error("Error loading products:", error);
        allProducts = [];
      }
    }

    function populateProductDropdown(selectEl) {
      if (!selectEl) return;
      const currentVal = selectEl.value;
      selectEl.innerHTML = '<option value="">-- Select Product --</option>';
      if (allProducts && allProducts.length > 0) {
        allProducts.forEach((p) => {
          const opt = document.createElement("option");
          opt.value = p.id;
          opt.textContent = p.product_name;
          opt.dataset.price = p.sale_price || 0;
          selectEl.appendChild(opt);
        });
      }
      if (currentVal) selectEl.value = currentVal;
    }

    // ---- Load Employees (Shift Based) ----
    async function loadEmployees(shift = "") {
      if (!employeeSelect) return;
      const url = shift
        ? `${API_BASE}/orders/get_employees.php?shift=${shift}`
        : `${API_BASE}/employees/get_employees.php`;
      const data = await fetchAPI(url);
      employeeSelect.innerHTML = '<option value="">Select Employee</option>';
      if (data && data.length > 0) {
        data.forEach((emp) => {
          const opt = document.createElement("option");
          opt.value = emp.id;
          opt.textContent = emp.full_name + " (" + emp.shift + ")";
          employeeSelect.appendChild(opt);
        });
      } else {
        employeeSelect.innerHTML =
          '<option value="">No employees found for this shift</option>';
      }
    }

    // ---- Load Cities / Areas for Customer ----
    async function loadCitiesForCustomer() {
      if (!newCustomerCity) return;
      const data = await fetchAPI(`${API_BASE}/cities/get_cities.php`);
      newCustomerCity.innerHTML = '<option value="">Select City</option>';
      if (data && data.length > 0) {
        data.forEach((city) => {
          const opt = document.createElement("option");
          opt.value = city.id;
          opt.textContent = city.city_name;
          newCustomerCity.appendChild(opt);
        });
      }
    }

    async function loadAreasForCustomer(cityId) {
      if (!newCustomerArea) return;
      newCustomerArea.innerHTML = '<option value="">Select Area</option>';
      if (!cityId) return;
      const data = await fetchAPI(
        `${API_BASE}/area-zone/get-area.php?city_id=${cityId}`,
      );
      const areas = Array.isArray(data) ? data : data.data || [];
      if (areas && areas.length > 0) {
        areas.forEach((area) => {
          const option = document.createElement("option");
          option.value = area.id;
          option.textContent = area.area_name;
          newCustomerArea.appendChild(option);
        });
      }
    }

    // ---- Customer Search / Select ----
    function renderCustomerSuggestions(customers) {
      if (!customerSearchResults) return;
      customerSearchResults.innerHTML = "";
      const customerList = Array.isArray(customers)
        ? customers
        : customers.data || [];
      if (!customerList || customerList.length === 0) {
        customerSearchResults.classList.add("hidden");
        return;
      }
      customerSearchResults.classList.remove("hidden");
      customerList.forEach((customer) => {
        const div = document.createElement("div");
        div.className =
          "customer-search-result px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 border-b border-gray-100 dark:border-gray-600 cursor-pointer";
        div.innerHTML = `
                <div class="flex justify-between items-center">
                    <div>
                        <span class="font-semibold">${customer.name}</span>
                        <span class="text-xs text-gray-500 ml-2">${customer.phone}</span>
                    </div>
                    <span class="text-xs text-gray-400">${customer.customer_code || ""}</span>
                </div>
            `;
        div.addEventListener("click", function () {
          selectCustomer(customer);
        });
        customerSearchResults.appendChild(div);
      });
    }

    async function searchCustomers(query) {
      if (!query || query.trim().length < 2) {
        renderCustomerSuggestions([]);
        return;
      }
      try {
        const response = await fetch(
          `${API_BASE}/customers/get_customers.php?search=${encodeURIComponent(query)}`,
        );
        const customers = await response.json();
        const customerList = Array.isArray(customers)
          ? customers
          : customers.data || [];
        renderCustomerSuggestions(customerList);
      } catch (err) {
        console.error(err);
        renderCustomerSuggestions([]);
      }
    }

    function selectCustomer(customer) {
      selectedCustomer = customer;
      if (selectedCustomerId) selectedCustomerId.value = customer.id;
      if (customerSearch) customerSearch.value = customer.name;
      if (customerSearchResults) customerSearchResults.classList.add("hidden");
      if (selectedCustomerInfo) selectedCustomerInfo.classList.remove("hidden");
      if (displayCustomerName) displayCustomerName.textContent = customer.name || "-";
      if (displayCustomerPhone) displayCustomerPhone.textContent = customer.phone || "-";
      if (displayCustomerCity) displayCustomerCity.textContent = customer.city_name || "-";
      if (displayCustomerArea) displayCustomerArea.textContent = customer.area_name || "-";
      if (displayCustomerAddress)
        displayCustomerAddress.textContent =
          customer.full_address || customer.address || "-";
      if (displayCustomerBalance)
        displayCustomerBalance.textContent = parseFloat(
          customer.current_balance || 0,
        ).toFixed(2);
    }

    // ---- Product Row ----
    function createProductRow(productId = "", qty = 1) {
      const row = document.createElement("div");
      row.className =
        "product-row grid grid-cols-1 sm:grid-cols-12 gap-3 items-end bg-gray-50/70 dark:bg-gray-700/50 p-3 rounded-lg border border-gray-100 dark:border-gray-700";

      row.innerHTML = `
            <div class="sm:col-span-4">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Product</label>
                <select class="product-select w-full border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-2.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition bg-white">
                    <option value="">-- Select Product --</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Quantity</label>
                <input type="number" class="product-qty w-full border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-2.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition bg-white" value="${qty}" min="1" step="1" />
            </div>
            <div class="sm:col-span-3">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Unit Price</label>
                <input type="number" class="product-price w-full border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-2.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition bg-white" value="0" step="0.01" />
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Total</label>
                <input type="number" class="product-total w-full border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-lg px-2.5 py-2 text-sm bg-gray-100 dark:bg-gray-600 cursor-not-allowed" value="0" step="0.01" readonly />
            </div>
            <div class="sm:col-span-1 flex justify-end">
                <button type="button" class="remove-product-btn text-red-400 hover:text-red-600 transition p-1.5 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>
        `;

      const select = row.querySelector(".product-select");
      populateProductDropdown(select);
      if (productId) select.value = productId;

      select.addEventListener("change", function () {
        const selectedOpt = this.options[this.selectedIndex];
        const price = selectedOpt.dataset.price || 0;
        row.querySelector(".product-price").value = price;
        calculateRowTotal(row);
        updateSummary();
      });

      const qtyInput = row.querySelector(".product-qty");
      const priceInput = row.querySelector(".product-price");

      qtyInput.addEventListener("input", function () {
        calculateRowTotal(row);
        updateSummary();
      });
      priceInput.addEventListener("input", function () {
        calculateRowTotal(row);
        updateSummary();
      });

      setTimeout(() => {
        calculateRowTotal(row);
        updateSummary();
      }, 100);

      const removeBtn = row.querySelector(".remove-product-btn");
      if (removeBtn) {
        removeBtn.addEventListener("click", function () {
          const rows = productContainer
            ? productContainer.querySelectorAll(".product-row")
            : [];
          if (rows.length > 1) {
            row.remove();
            updateSummary();
            validateProducts();
          } else {
            alert("At least one product is required.");
          }
        });
      }

      return row;
    }

    function calculateRowTotal(row) {
      const qty = parseFloat(row.querySelector(".product-qty").value) || 0;
      const price = parseFloat(row.querySelector(".product-price").value) || 0;
      const total = qty * price;
      row.querySelector(".product-total").value = total.toFixed(2);
      return total;
    }

    function addProductRow(productId = "", qty = 1) {
      const row = createProductRow(productId, qty);
      if (productContainer) productContainer.appendChild(row);
      validateProducts();
      updateSummary();
    }

    function validateProducts() {
      if (!productContainer) return true;
      const rows = productContainer.querySelectorAll(".product-row");
      const hasProduct = rows.length > 0;
      if (productError) productError.classList.toggle("hidden", hasProduct);
      return hasProduct;
    }

    function getProductRowsData() {
      if (!productContainer) return [];
      const rows = productContainer.querySelectorAll(".product-row");
      const data = [];
      rows.forEach((row) => {
        const productId = row.querySelector(".product-select").value;
        const qty = parseFloat(row.querySelector(".product-qty").value) || 0;
        const price = parseFloat(row.querySelector(".product-price").value) || 0;
        if (productId && qty > 0) {
          data.push({
            product_id: productId,
            quantity: qty,
            unit_price: price,
            total: qty * price,
          });
        }
      });
      return data;
    }

    function updateSummary() {
      const products = getProductRowsData();
      const subtotal = products.reduce((sum, p) => sum + p.total, 0);
      const discount = parseFloat(discountInput ? discountInput.value : 0) || 0;
      const total = subtotal - discount;
      if (subtotalInput) subtotalInput.value = subtotal.toFixed(2);
      if (totalAmountInput) totalAmountInput.value = total.toFixed(2);
    }

    // ---- Load Order for Edit ----
    async function loadOrderForEdit(orderId) {
      const data = await fetchAPI(
        `${API_BASE}/orders/get_order.php?id=${orderId}`,
      );
      if (!data || !data.order) return;
      const o = data.order;

      if (data.customer) {
        selectCustomer({
          id: o.customer_id,
          name: data.customer.name,
          phone: data.customer.phone,
          city_name: data.customer.city_name,
          area_name: data.customer.area_name,
          full_address: data.customer.complete_address,
          current_balance: data.customer.current_balance,
        });
      }

      if (orderDate) orderDate.value = o.order_date || "";
      if (deliveryDate)
        deliveryDate.value =
          o.delivery_date && o.delivery_date !== "N/A" ? o.delivery_date : "";
      if (orderShift) orderShift.value = o.shift || "";
      await loadEmployees(o.shift);
      if (employeeSelect) employeeSelect.value = o.employee_id || "";
      if (orderRemarks) orderRemarks.value = o.remarks || "";
      if (discountInput) discountInput.value = o.discount || "0.00";

      if (productContainer) productContainer.innerHTML = "";
      if (data.items && data.items.length > 0) {
        data.items.forEach((item) => {
          addProductRow(item.product_id, item.quantity);
          const rows = productContainer
            ? productContainer.querySelectorAll(".product-row")
            : [];
          const lastRow = rows[rows.length - 1];
          if (lastRow) {
            lastRow.querySelector(".product-price").value = item.unit_price;
            calculateRowTotal(lastRow);
          }
        });
      } else {
        addProductRow();
      }
      updateSummary();
      validateProducts();
    }

    // ---- View Order (modal + print) ----
    async function viewOrder(id) {
      const data = await fetchAPI(`${API_BASE}/orders/get_order.php?id=${id}`);
      if (!data || !data.order) {
        alert("Could not load order details.");
        return;
      }
      const o = data.order;
      const c = data.customer || {};

      const voOrderNo = document.getElementById("voOrderNo");
      if (voOrderNo) voOrderNo.textContent = o.order_no || "-";
      const badge = document.getElementById("voStatusBadge");
      if (badge) {
        badge.textContent = o.status || "Pending";
        badge.className = "status-badge " + statusClassFor(o.status);
      }

      setText("voCustomerName", c.name || "-");
      setText("voCustomerPhone", c.phone || "-");
      setText("voCustomerAddress", c.complete_address || "-");

      setText("voOrderDate", o.order_date || "-");
      setText("voDeliveryDate", o.delivery_date || "-");
      setText("voShift", o.shift || "-");
      setText("voEmployee", o.employee_name || "-");

      const itemsBody = document.getElementById("voItemsBody");
      if (itemsBody) {
        itemsBody.innerHTML = "";
        (data.items || []).forEach((item) => {
          const tr = document.createElement("tr");
          tr.innerHTML = `
        <td class="border dark:border-gray-600 px-2 py-1.5">${item.product_name}</td>
        <td class="border dark:border-gray-600 px-2 py-1.5 text-right">${item.quantity}</td>
        <td class="border dark:border-gray-600 px-2 py-1.5 text-right">${parseFloat(item.unit_price).toFixed(2)}</td>
        <td class="border dark:border-gray-600 px-2 py-1.5 text-right">${parseFloat(item.total).toFixed(2)}</td>
      `;
          itemsBody.appendChild(tr);
        });
      }

      setText("voSubtotal", parseFloat(o.subtotal || 0).toFixed(2));
      setText("voDiscount", parseFloat(o.discount || 0).toFixed(2));
      setText("voTotal", parseFloat(o.grand_total || o.total || 0).toFixed(2));
      setText("voRemarks", o.remarks || "-");

      if (viewOrderModal) {
        viewOrderModal.classList.remove("hidden");
        viewOrderModal.classList.add("flex");
      }
    }

    function setText(id, text) {
      const el = document.getElementById(id);
      if (el) el.textContent = text;
    }

    function statusClassFor(status) {
      if (status === "Delivered") return "status-delivered";
      if (status === "Confirmed") return "status-confirmed";
      if (status === "Complete") return "status-invoiced";
      if (status === "Cancelled") return "status-cancelled";
      return "status-pending";
    }

    function printOrderView() {
      window.print();
    }

    // ---- Pay Modal (New Transaction) ----
    function openPayModal(customerId) {
      if (payCustomerId) payCustomerId.value = customerId;
      if (txnType) txnType.value = "in";
      if (txnAmount) txnAmount.value = "";
      if (txnRemarks) txnRemarks.value = "";
      if (payModal) {
        payModal.classList.remove("hidden");
        payModal.classList.add("flex");
      }
    }

    function hidePayModal() {
      if (!payModal) return;
      payModal.classList.add("hidden");
      payModal.classList.remove("flex");
    }

    async function postTransaction() {
      const customerId = payCustomerId ? payCustomerId.value : "";
      const amount = parseFloat(txnAmount ? txnAmount.value : NaN);
      if (!amount || amount <= 0) {
        alert("Please enter a valid amount.");
        return;
      }
      try {
        const res = await fetch(`${API_BASE}/orders/add_transaction.php`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            customer_id: customerId,
            type: txnType ? txnType.value : "in",
            amount: amount,
            remarks: txnRemarks ? txnRemarks.value : "",
          }),
        });
        const result = await res.json();
        if (result.success) {
          alert(result.message || "Transaction posted successfully!");
          hidePayModal();
          loadOrders();
        } else {
          alert(result.message || "Error posting transaction.");
        }
      } catch (err) {
        alert("Server error. Please try again.");
        console.error(err);
      }
    }

    // ---- Invoice Modal (Party Invoices Report) ----
    function openInvoiceModal(customerId) {
      activeCustomerIdForModals = customerId;
      const today = new Date().toISOString().split("T")[0];
      const weekAgo = new Date(Date.now() - 7 * 86400000)
        .toISOString()
        .split("T")[0];
      if (invDateFrom) invDateFrom.value = weekAgo;
      if (invDateTo) invDateTo.value = today;
      if (invoiceModal) {
        invoiceModal.classList.remove("hidden");
        invoiceModal.classList.add("flex");
      }
      loadInvoiceReport();
    }

    function hideInvoiceModal() {
      if (!invoiceModal) return;
      invoiceModal.classList.add("hidden");
      invoiceModal.classList.remove("flex");
    }

    async function loadInvoiceReport() {
      const customerId = activeCustomerIdForModals;

      if (!customerId) return;

      const url = `${API_BASE}/invoices/get_invoices.php?customer_id=${customerId}&date_from=${invDateFrom ? invDateFrom.value : ""}&date_to=${invDateTo ? invDateTo.value : ""}`;

      const data = await fetchAPI(url);

      if (!data || !data.success) return;

      const c = data.customer || {};

      // Customer Details
      setText("invCustomerName", c.name || "-");
      setText("invCustomerPhone", c.phone || "-");
      setText(
        "invCustomerAddress",
        [c.address, c.area_name, c.city_name].filter(Boolean).join(", ") || "-",
      );
      setText("invCurrentBalance", Number(c.current_balance || 0).toFixed(2));
      setText("invDateFromLabel", invDateFrom ? invDateFrom.value || "-" : "-");
      setText("invDateToLabel", invDateTo ? invDateTo.value || "-" : "-");

      const body = document.getElementById("invoiceTableBody");
      if (!body) return;

      body.innerHTML = "";

      if (!data.invoices || data.invoices.length === 0) {
        body.innerHTML = `
        <tr>
            <td colspan="8"
            class="text-center py-5 text-gray-500">
                No invoices found
            </td>
        </tr>`;

        setText("invTotalAmount", "0.00");
        setText("invTotalPaid", "0.00");
        setText("invTotalDue", "0.00");

        return;
      }

      let totalInvoice = 0;
      let totalPaid = 0;
      let totalDue = 0;

      data.invoices.forEach((inv) => {
        totalInvoice += Number(inv.net_bill || 0);

        totalPaid += Number(inv.paid || 0);

        totalDue += Number(inv.new_bal || 0);

        const tr = document.createElement("tr");

        tr.innerHTML = `

        <td class="border px-3 py-2 font-semibold text-blue-600">
            ${inv.invoice_no}
        </td>


        <td class="border px-3 py-2">
            ${inv.date}
        </td>


        <td class="border px-3 py-2 text-right">
            ${Number(inv.bill_amt).toFixed(2)}
        </td>
        <td class="border px-3 py-2 text-right text-red-500">
            ${Number(inv.discount).toFixed(2)}
        </td>
        <td class="border px-3 py-2 text-right font-semibold">
            ${Number(inv.net_bill).toFixed(2)}
        </td>
        <td class="border px-3 py-2 text-right">
            ${Number(inv.prev_bal).toFixed(2)}
        </td>
        <td class="border px-3 py-2 text-right text-green-600">
            ${Number(inv.paid).toFixed(2)}
        </td>
        <td class="border px-3 py-2 text-right text-red-600 font-semibold">
            ${Number(inv.new_bal).toFixed(2)}
        </td>
        `;
        body.appendChild(tr);
      });

      setText("invTotalAmount", totalInvoice.toFixed(2));
      setText("invTotalPaid", totalPaid.toFixed(2));
      setText("invTotalDue", totalDue.toFixed(2));
    }

    // ---- Ledger Modal (Party Ledger) ----
    async function openLedgerModal(customerId) {
      activeCustomerIdForModals = customerId;

      const today = new Date().toISOString().split("T")[0];
      const weekAgo = new Date(Date.now() - 7 * 86400000)
        .toISOString()
        .split("T")[0];

      if (ledDateFrom) ledDateFrom.value = weekAgo;
      if (ledDateTo) ledDateTo.value = today;

      if (ledgerModal) {
        ledgerModal.classList.remove("hidden");
        ledgerModal.classList.add("flex");
      }

      // Data load hone ka wait karega
      await loadLedgerReport();
    }

    function hideLedgerModal() {
      if (!ledgerModal) return;
      ledgerModal.classList.add("hidden");
      ledgerModal.classList.remove("flex");
    }

    async function loadLedgerReport() {
      const customerId = activeCustomerIdForModals;
      if (!customerId) return;

      const url = `${API_BASE}/ledger/get_leger.php?customer_id=${customerId}`;

      const data = await fetchAPI(url);

      if (!data || !data.success) return;

      // Customer Info
      const c = data.customer || {};

      setText("ledCustomerName", c.name || "-");
      setText("ledCustomerPhone", c.phone || "-");
      setText("ledCustomerAddress", c.address || "-");
      setText("ledCustomerBalance", Number(c.current_balance || 0).toFixed(2));

      // Table
      const body = document.getElementById("ledgerTableBody");
      if (!body) return;
      body.innerHTML = "";

      if (!data.rows || data.rows.length === 0) {
        body.innerHTML = `
      <tr>
        <td colspan="7" class="border px-3 py-5 text-center">
          No Ledger Found
        </td>
      </tr>
    `;
        return;
      }

      data.rows.forEach((row) => {
        body.innerHTML += `
      <tr>
        <td class="border px-3 py-2">${row.sr}</td>
        <td class="border px-3 py-2">${row.date}</td>
        <td class="border px-3 py-2">${row.description}</td>
        <td class="border px-3 py-2 text-end">
          ${Number(row.opening).toFixed(2)}
        </td>
        <td class="border px-3 py-2 text-end">
          ${Number(row.debit).toFixed(2)}
        </td>
        <td class="border px-3 py-2 text-end text-green-600">
          ${Number(row.credit).toFixed(2)}
        </td>
        <td class="border px-3 py-2 text-end fw-bold">
          ${Number(row.balance).toFixed(2)}
        </td>
      </tr>
    `;
      });

      // Totals
      setText("ledTotalDebit", Number(data.total_debit).toFixed(2));
      setText("ledTotalCredit", Number(data.total_credit).toFixed(2));
      setText("ledClosingBalance", Number(data.closing).toFixed(2));
    }

    // ---- Save Order ----
    async function saveOrder(e) {
      e.preventDefault();
      if (!selectedCustomerId || !selectedCustomerId.value) {
        alert("Please select a customer.");
        if (customerSearch) customerSearch.focus();
        return;
      }
      const requiredFields = [orderDate, orderShift, employeeSelect].filter(Boolean);
      let valid = true;
      requiredFields.forEach((field) => {
        if (!field.value) {
          field.classList.add("border-red-500");
          valid = false;
        } else {
          field.classList.remove("border-red-500");
        }
      });
      if (!valid) {
        alert("Please fill in all required fields.");
        return;
      }
      const productData = getProductRowsData();
      if (productData.length === 0) {
        if (productError) productError.classList.remove("hidden");
        alert("Please add at least one product.");
        return;
      }

      const orderId = orderIdInput ? orderIdInput.value : "0";
      const url =
        orderId && orderId !== "0"
          ? `${API_BASE}/orders/update_order.php`
          : `${API_BASE}/orders/add_order.php`;
      const method = orderId && orderId !== "0" ? "PUT" : "POST";

      const subtotal = parseFloat(subtotalInput ? subtotalInput.value : 0) || 0;
      const discount = parseFloat(discountInput ? discountInput.value : 0) || 0;
      const total = subtotal - discount;
      const payload = {
        id: orderId,
        customer_id: selectedCustomerId.value,
        employee_id: employeeSelect ? employeeSelect.value : "",
        order_date: orderDate ? orderDate.value : "",
        delivery_date: deliveryDate && deliveryDate.value ? deliveryDate.value : null,
        shift: orderShift ? orderShift.value : "",
        subtotal: subtotal,
        discount: discount,
        total: total,
        remarks: orderRemarks ? orderRemarks.value : "",
        items: productData,
      };

      try {
        const res = await fetch(url, {
          method: method,
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });
        const result = await res.json();
        if (result.success) {
          alert(result.message || "Order saved successfully!");
          hideModal();
          loadOrders();
        } else {
          alert(result.message || "Error saving order.");
        }
      } catch (err) {
        alert("Server error. Please try again.");
        console.error(err);
      }
    }

    // ---- Save New Customer ----
    async function saveNewCustomer(e) {
      e.preventDefault();
      const required = [
        newCustomerName,
        newCustomerPhone,
        newCustomerCity,
        newCustomerArea,
      ].filter(Boolean);
      let valid = true;
      required.forEach((field) => {
        if (!field.value) {
          field.classList.add("border-red-500");
          valid = false;
        } else {
          field.classList.remove("border-red-500");
        }
      });
      if (!valid) {
        alert("Please fill in all required fields.");
        return;
      }
      const payload = {
        name: newCustomerName ? newCustomerName.value : "",
        phone: newCustomerPhone ? newCustomerPhone.value : "",
        email: newCustomerEmail ? newCustomerEmail.value || "" : "",
        city_id: newCustomerCity ? newCustomerCity.value : "",
        area_id: newCustomerArea ? newCustomerArea.value : "",
        opening_balance: newCustomerBalance ? newCustomerBalance.value || "0.00" : "0.00",
        address: newCustomerAddress ? newCustomerAddress.value || "" : "",
        status: "active",
      };
      try {
        const res = await fetch(`${API_BASE}/customers/add_customer.php`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });
        const result = await res.json();
        if (result.success) {
          alert("Customer added successfully!");
          hideCustomerModal();
          const customerData = await fetchAPI(
            `${API_BASE}/customers/get_customer.php?id=${result.customer_id}`,
          );
          if (customerData && customerData.customer)
            selectCustomer(customerData.customer);
          loadCustomerFilter();
        } else {
          alert(result.message || "Error adding customer.");
        }
      } catch (err) {
        alert("Server error. Please try again.");
        console.error(err);
      }
    }

    // ---- Load Orders Table (Customer tab) ----
    async function loadOrders() {
      const search = searchInputEl ? searchInputEl.value : "";
      const status = statusFilterEl ? statusFilterEl.value : "";
      const customer = customerFilterEl ? customerFilterEl.value : "";
      const dateFrom = dateFromEl ? dateFromEl.value : "";
      const dateTo = dateToEl ? dateToEl.value : "";

      let url = `${API_BASE}/orders/get_orders.php`;
      const params = [];
      if (search) params.push(`search=${encodeURIComponent(search)}`);
      if (status) params.push(`status=${status}`);
      if (customer) params.push(`customer=${customer}`);
      if (dateFrom) params.push(`date_from=${dateFrom}`);
      if (dateTo) params.push(`date_to=${dateTo}`);
      if (params.length) url += "?" + params.join("&");

      const data = await fetchAPI(url);
      if (!tableBody) return;
      tableBody.innerHTML = "";

      if (!data || data.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="13" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400"><i class="fa-regular fa-folder-open text-2xl mb-2 block opacity-40"></i>No orders found</td></tr>`;
        return;
      }

      data.forEach((o, index) => {
        const tr = document.createElement("tr");
        tr.setAttribute("data-order-id", o.id);
        tr.className = "order-row";
        const statusClass = statusClassFor(o.status);

        tr.innerHTML = `
          <td class="px-3 py-3 text-center font-semibold text-slate-500 dark:text-slate-400">${index + 1}</td>
          <td class="px-3 py-3 font-mono text-xs font-semibold text-slate-600 dark:text-slate-300">#${o.id}</td>
          <td class="px-3 py-3">${o.product_names || "-"}</td>
          <td class="px-3 py-3">
              <div class="font-semibold text-slate-800 dark:text-slate-100">${o.customer_name || "-"}</div>
              ${o.customer_code ? `<div class="text-xs text-slate-400">${o.customer_code}</div>` : ""}
          </td>
          <td class="px-3 py-3 whitespace-nowrap">${o.customer_phone || "-"}</td>
          <td class="px-3 py-3">${o.customer_address || "-"}</td>
          <td class="px-3 py-3">
              <span class="area-pill">${o.area_name || "-"}</span>
          </td>
          <td class="px-3 py-3 text-right">
              <div class="font-semibold">${parseFloat(o.current_balance || 0).toFixed(2)}</div>
              ${o.opening_balance !== undefined ? `<div class="text-[11px] text-slate-400">Opening: ${parseFloat(o.opening_balance || 0).toFixed(2)}</div>` : ""}
          </td>
          <td class="px-3 py-3 text-right">${o.product_rates || "0.00"}</td>
          <td class="px-3 py-3 text-right font-semibold">${o.total_qty || 0}</td>
          <td class="px-3 py-3">${o.shift || "-"}</td>
          <td class="px-3 py-3">
              <span class="status-badge ${statusClass}">${o.status || "Pending"}</span>
          </td>
          <td class="px-3 py-3">
              <div class="flex items-center justify-center gap-1.5">
                  <button class="action-dropdown-item icon-btn icon-btn-blue" data-action="view" data-id="${o.id}" data-customer-id="${o.customer_id}" title="View">
                      <i class="fa-solid fa-eye"></i>
                  </button>
                  <button class="action-dropdown-item icon-btn icon-btn-amber" data-action="edit" data-id="${o.id}" data-customer-id="${o.customer_id}" title="Edit">
                      <i class="fa-solid fa-pen"></i>
                  </button>
                  <button class="action-dropdown-item icon-btn icon-btn-green" data-action="pay" data-id="${o.id}" data-customer-id="${o.customer_id}" title="Pay">
                      <i class="fa-solid fa-money-bill-wave"></i>
                  </button>
                  <button class="action-dropdown-item icon-btn icon-btn-purple" data-action="invoices" data-id="${o.id}" data-customer-id="${o.customer_id}" title="Invoices">
                      <i class="fa-solid fa-file-invoice"></i>
                  </button>
                  <button class="action-dropdown-item icon-btn icon-btn-indigo" data-action="ledger" data-id="${o.id}" data-customer-id="${o.customer_id}" title="Ledger">
                      <i class="fa-solid fa-book"></i>
                  </button>
                  <button class="action-dropdown-item icon-btn icon-btn-amber" data-action="update-status" data-id="${o.id}" data-customer-id="${o.customer_id}" title="Update Status">
                      <i class="fa-solid fa-circle-check"></i>
                  </button>
                  <button class="action-dropdown-item icon-btn icon-btn-red" data-action="delete" data-id="${o.id}" title="Delete">
                      <i class="fa-solid fa-trash"></i>
                  </button>
              </div>
          </td>
      `;
        tableBody.appendChild(tr);
      });
    }

    // ---- Employee Orders Tab ----
    async function loadEmployeeOrders() {
      const search = empSearchInputEl ? empSearchInputEl.value : "";
      const shift = empShiftFilterEl ? empShiftFilterEl.value : "";
      let url = `${API_BASE}/orders/get_employee_orders.php`;
      const params = [];
      if (search) params.push(`search=${encodeURIComponent(search)}`);
      if (shift) params.push(`shift=${shift}`);
      if (params.length) url += "?" + params.join("&");

      const res = await fetchAPI(url);
      const data = res && res.data ? res.data : [];
      if (!employeeOrdersTableBody) return;
      employeeOrdersTableBody.innerHTML = "";

      if (!data || data.length === 0) {
        employeeOrdersTableBody.innerHTML = `<tr><td colspan="11" class="px-4 py-10 text-center text-gray-500"><i class="fa-regular fa-folder-open text-2xl mb-2 block opacity-40"></i>No records found</td></tr>`;
        return;
      }

      data.forEach((r, index) => {
        const itemsSummary =
          (r.items || [])
            .map((it) => `${it.product_name} x${it.quantity}`)
            .join(", ") || "-";
        const tr = document.createElement("tr");
        tr.className = "order-row";
        tr.innerHTML = `
        <td class="px-3 py-3 text-center font-semibold text-slate-500 dark:text-slate-400">${index + 1}</td>
        <td class="px-3 py-3 font-semibold text-slate-800 dark:text-slate-100">${r.full_name}</td>
        <td class="px-3 py-3">${r.designation || "-"}</td>
        <td class="px-3 py-3">${r.employee_shift || "-"}</td>
        <td class="px-3 py-3 font-mono text-xs">${r.order_no || "-"}</td>
        <td class="px-3 py-3">${r.customer_name || "-"}</td>
        <td class="px-3 py-3 text-xs">${itemsSummary}</td>
        <td class="px-3 py-3">${r.order_date || "-"}</td>
        <td class="px-3 py-3 text-right font-semibold">${r.total ? parseFloat(r.total).toFixed(2) : "-"}</td>
        <td class="px-3 py-3">
          ${r.status ? `<span class="status-badge ${statusClassFor(r.status)}">${r.status}</span>` : "-"}
        </td>
        <td class="px-3 py-3 text-center">
          ${
            r.order_id
              ? `<button class="update-status-btn icon-btn icon-btn-amber" data-id="${r.order_id}" data-customer-id="${r.customer_id || ""}" title="Update Status">
                  <i class="fa-solid fa-circle-check"></i>
                </button>`
              : "-"
          }
        </td>
      `;
        employeeOrdersTableBody.appendChild(tr);
      });
    }

    // ---- UPDATE STATUS API - FIXED ----
    window.updateStatusAPI = async (orderId, status, extra = {}) => {
      try {
        const payload = Object.assign(
          {
            order_id: orderId,
            status: status,
            payment_received: status === "Delivered" ? 1 : null,
          },
          extra,
        );

        const res = await fetch(`${API_BASE}/orders/update_status.php`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify(payload),
        });

        const result = await res.json();

        if (!result.success) {
          throw new Error(result.message || "Update failed");
        }

        return result;
      } catch (error) {
        console.error("Status Update Error:", error);
        throw error;
      }
    };

    // ---- UPDATE ORDER STATUS - FIXED ----
    async function updateOrderStatus(orderId, customerId) {
      const oldModal = document.getElementById("dynamicStatusModal");
      if (oldModal) oldModal.remove();

      const modalEl = document.createElement("div");
      modalEl.id = "dynamicStatusModal";
      modalEl.className = "fixed inset-0 bg-black/50 flex items-center justify-center z-50";

      modalEl.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-96 p-6 max-w-[90vw]">
          <h2 class="text-xl font-semibold mb-5 dark:text-white">Update Order Status</h2>
          
          <select id="statusSelect" class="w-full border rounded-lg px-3 py-2 mb-4 dark:bg-gray-700 dark:text-white">
            <option value="Pending">Pending</option>
            <option value="Confirmed">Confirmed</option>
            <option value="Delivered">Delivered</option>
            <option value="Complete">Complete</option>
            <option value="Cancelled">Cancelled</option>
          </select>
          
          <div id="paymentBox" class="hidden">
            <label class="text-sm dark:text-white">Payment Status</label>
            <select id="paymentStatus" class="w-full border rounded-lg px-3 py-2 mb-3 dark:bg-gray-700 dark:text-white">
              <option value="Not Paid">Not Paid</option>
              <option value="Paid">Paid</option>
            </select>
            
            <div id="paymentAmountBox" class="hidden">
              <label class="text-sm dark:text-white">Payment Amount</label>
              <input id="paymentAmount" type="number" placeholder="Enter amount" class="w-full border rounded-lg px-3 py-2">
            </div>
          </div>
          
          <div class="flex justify-end gap-3 mt-5">
            <button id="cancelStatus" class="px-4 py-2 border rounded-lg">Cancel</button>
            <button id="saveStatus" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Update</button>
          </div>
        </div>
      `;

      document.body.appendChild(modalEl);

      const statusSelect = document.getElementById("statusSelect");
      const paymentBox = document.getElementById("paymentBox");
      const paymentStatus = document.getElementById("paymentStatus");
      const paymentAmountBox = document.getElementById("paymentAmountBox");

      if (statusSelect) {
        statusSelect.onchange = () => {
          if (!paymentBox) return;
          if (statusSelect.value === "Complete") {
            paymentBox.classList.remove("hidden");
          } else {
            paymentBox.classList.add("hidden");
          }
        };
      }

      if (paymentStatus) {
        paymentStatus.onchange = () => {
          if (!paymentAmountBox) return;
          if (paymentStatus.value === "Paid") {
            paymentAmountBox.classList.remove("hidden");
          } else {
            paymentAmountBox.classList.add("hidden");
          }
        };
      }

      const cancelStatusBtn = document.getElementById("cancelStatus");
      if (cancelStatusBtn) {
        cancelStatusBtn.onclick = () => {
          modalEl.remove();
        };
      }

      const saveStatusBtn = document.getElementById("saveStatus");
      if (saveStatusBtn) {
        saveStatusBtn.onclick = async () => {
          const status = statusSelect ? statusSelect.value : "";
          saveStatusBtn.disabled = true;
          saveStatusBtn.textContent = "Updating...";

          try {
            // Handle Complete status with payment
            if (status === "Complete") {
              try {
                const orderRes = await fetch(
                  `${API_BASE}/orders/get_order.php?id=${orderId}`
                );
                const orderData = await orderRes.json();

                if (!orderData.success || !orderData.order) {
                  alert("Order data not found");
                  saveStatusBtn.disabled = false;
                  saveStatusBtn.textContent = "Update";
                  return;
                }

                const resolvedCustomerId = orderData.order.customer_id || customerId;
                const totalAmount = orderData.order.grand_total || orderData.order.total || 0;
                const orderItems = (orderData.items || []).map((it) => ({
                  product_id: it.product_id,
                  quantity: it.quantity,
                }));

                // ✅ FIX: Close modal FIRST
                modalEl.remove();
                
                // ✅ FIX: Small delay then open payment modal
                setTimeout(() => {
                  openPaymentModal(orderId, resolvedCustomerId, totalAmount, orderItems);
                }, 100);
                
              } catch (error) {
                console.error("Error fetching order:", error);
                alert("Order fetch error: " + error.message);
                saveStatusBtn.disabled = false;
                saveStatusBtn.textContent = "Update";
              }
              return;
            }

            // Other statuses use the API
            await updateStatusAPI(orderId, status);
            modalEl.remove();
            
            // Reload both tables
            await loadOrders();
            await loadEmployeeOrders();
            
            alert(`Order status updated to ${status}`);
            
          } catch (error) {
            console.error("ERROR:", error);
            alert(error.message || "Status update failed.");
            saveStatusBtn.disabled = false;
            saveStatusBtn.textContent = "Update";
          }
        };
      }
    }

    // ---- OPEN PAYMENT MODAL - FIXED (Paid / Unpaid flow for Complete status) ----
    window.openPaymentModal = (orderId, customerId, amount, items = []) => {
      // Remove any existing modals first
      const old = document.getElementById("paymentModal");
      if (old) old.remove();

      // Also close any open status modals
      const statusModal = document.getElementById("statusModal");
      if (statusModal) {
        statusModal.classList.add("hidden");
        statusModal.classList.remove("flex");
      }

      const dynamicStatusModal = document.getElementById("dynamicStatusModal");
      if (dynamicStatusModal) {
        dynamicStatusModal.remove();
      }

      const modalEl = document.createElement("div");
      modalEl.id = "paymentModal";
      modalEl.className = "fixed inset-0 bg-black/50 flex items-center justify-center z-50";

      modalEl.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-96 max-w-[90vw]">
          <h2 class="text-lg font-bold mb-1 dark:text-white">Complete Order #${orderId}</h2>
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Has payment been received for this order?</p>

          <div class="grid grid-cols-2 gap-2 mb-4">
            <button id="pmPaidBtn" type="button" class="px-4 py-2 rounded-lg border-2 font-semibold transition"></button>
            <button id="pmUnpaidBtn" type="button" class="px-4 py-2 rounded-lg border-2 font-semibold transition"></button>
          </div>

          <div id="pmPaidFields">
            <label class="text-sm dark:text-gray-300">Amount</label>
            <input
              id="paymentAmount"
              value="${amount}"
              type="number"
              step="0.01"
              class="w-full border rounded px-3 py-2 mb-3 dark:bg-gray-700 dark:text-white">

            <label class="text-sm dark:text-gray-300">Payment Method</label>
            <select
              id="paymentMethod"
              class="w-full border rounded px-3 py-2 mb-4 dark:bg-gray-700 dark:text-white">
              <option value="Cash">Cash</option>
              <option value="Bank">Bank</option>
              <option value="Online">Online</option>
            </select>
          </div>

          <div class="flex justify-end gap-3">
            <button id="closePayment" class="px-4 py-2 border rounded">Cancel</button>
            <button id="savePayment" class="px-4 py-2 bg-green-600 text-white rounded">Save</button>
          </div>
        </div>
      `;

      document.body.appendChild(modalEl);

      // Paid / Unpaid toggle state (defaults to Paid, the more common path)
      let isPaid = true;
      const paidBtn = document.getElementById("pmPaidBtn");
      const unpaidBtn = document.getElementById("pmUnpaidBtn");
      const paidFields = document.getElementById("pmPaidFields");

      function refreshToggle() {
        if (isPaid) {
          if (paidBtn) paidBtn.className = "px-4 py-2 rounded-lg border-2 border-green-500 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 font-semibold transition";
          if (unpaidBtn) unpaidBtn.className = "px-4 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 font-semibold transition";
          if (paidFields) paidFields.classList.remove("hidden");
        } else {
          if (unpaidBtn) unpaidBtn.className = "px-4 py-2 rounded-lg border-2 border-red-500 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 font-semibold transition";
          if (paidBtn) paidBtn.className = "px-4 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 font-semibold transition";
          if (paidFields) paidFields.classList.add("hidden");
        }
      }
      if (paidBtn) paidBtn.textContent = "Paid";
      if (unpaidBtn) unpaidBtn.textContent = "Unpaid";
      refreshToggle();

      if (paidBtn) {
        paidBtn.onclick = () => {
          isPaid = true;
          refreshToggle();
        };
      }
      if (unpaidBtn) {
        unpaidBtn.onclick = () => {
          isPaid = false;
          refreshToggle();
        };
      }

      const closePaymentBtn = document.getElementById("closePayment");
      if (closePaymentBtn) {
        closePaymentBtn.onclick = () => {
          modalEl.remove();
          // Reload data after closing without saving anything
          loadOrders();
          loadEmployeeOrders();
        };
      }

      const savePaymentBtn = document.getElementById("savePayment");
      if (savePaymentBtn) {
        savePaymentBtn.onclick = async () => {
          savePaymentBtn.disabled = true;
          savePaymentBtn.textContent = "Saving...";

          try {
            if (isPaid) {
              const paymentAmountEl = document.getElementById("paymentAmount");
              const enteredAmount =
                parseFloat(paymentAmountEl ? paymentAmountEl.value : amount) || 0;

              if (!enteredAmount || enteredAmount <= 0) {
                alert("Please enter a valid amount.");
                savePaymentBtn.disabled = false;
                savePaymentBtn.textContent = "Save";
                return;
              }

              // Paid -> post the transaction first
              const res = await fetch(`${API_BASE}/orders/add_transaction.php`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                  customer_id: customerId,
                  type: "in",
                  amount: enteredAmount,
                  remarks: `Payment for Order #${orderId}`,
                }),
              });

              const result = await res.json();

              if (!result.success) {
                alert(result.message || "Payment failed");
                savePaymentBtn.disabled = false;
                savePaymentBtn.textContent = "Save";
                return;
              }
            }

            // Either way, mark the order Complete. Items go along so the
            // backend (update_status.php) can deduct stock when status
            // becomes Complete. When unpaid, no transaction is posted above.
            await updateStatusAPI(orderId, "Complete", { items });

            alert(
              isPaid
                ? "Payment recorded and order marked Complete."
                : "Order marked Complete (unpaid)."
            );
            modalEl.remove();

            await loadOrders();
            await loadEmployeeOrders();
          } catch (error) {
            console.error(error);
            alert("Error: " + error.message);
            savePaymentBtn.disabled = false;
            savePaymentBtn.textContent = "Save";
          }
        };
      }
    };

    // ---- Delete Order ----
    async function deleteOrder(orderId) {
      if (!confirm("Are you sure you want to delete this order?")) return;
      try {
        const res = await fetch(
          `${API_BASE}/orders/delete_order.php?id=${orderId}`,
          { method: "DELETE" },
        );
        const result = await res.json();
        if (result.success) {
          alert("Order deleted successfully.");
          loadOrders();
        } else {
          alert(result.message || "Error deleting order.");
        }
      } catch (err) {
        alert("Server error.");
        console.error(err);
      }
    }

    // ---- Load Customers for Filter ----
    async function loadCustomerFilter() {
      const data = await fetchAPI(`${API_BASE}/customers/get_customers.php`);
      if (customerFilterEl) {
        customerFilterEl.innerHTML = '<option value="">All Customers</option>';
        if (data && data.length > 0) {
          data.forEach((c) => {
            const opt = document.createElement("option");
            opt.value = c.id;
            opt.textContent = c.name + " (" + c.phone + ")";
            customerFilterEl.appendChild(opt);
          });
        }
      }
    }

    // ================= Tabs =================
    function switchTab(tab) {
      if (tab === "customer") {
        if (tabCustomerOrders) tabCustomerOrders.classList.add("active");
        if (tabEmployeeOrders) tabEmployeeOrders.classList.remove("active");
        if (customerOrdersPanel) customerOrdersPanel.classList.remove("hidden");
        if (employeeOrdersPanel) employeeOrdersPanel.classList.add("hidden");
        loadOrders();
      } else {
        if (tabEmployeeOrders) tabEmployeeOrders.classList.add("active");
        if (tabCustomerOrders) tabCustomerOrders.classList.remove("active");
        if (employeeOrdersPanel) employeeOrdersPanel.classList.remove("hidden");
        if (customerOrdersPanel) customerOrdersPanel.classList.add("hidden");
        loadEmployeeOrders();
      }
    }

    // ================= Event Listeners =================
    on(tabCustomerOrders, "click", () => switchTab("customer"));
    on(tabEmployeeOrders, "click", () => switchTab("employee"));

    on(openModalBtn, "click", () => showModal("New Order", 0));
    on(closeModalBtn, "click", hideModal);
    on(closeBtn, "click", hideModal);
    on(modal, "click", (e) => {
      if (e.target === modal) hideModal();
    });

    on(closeCustomerModal, "click", hideCustomerModal);
    on(closeCustomerBtn, "click", hideCustomerModal);
    on(customerModal, "click", (e) => {
      if (e.target === customerModal) hideCustomerModal();
    });

    on(document.getElementById("addNewCustomerBtn"), "click", showCustomerModal);

    let searchTimeout;
    on(customerSearch, "input", function () {
      clearTimeout(searchTimeout);
      const query = this.value.trim();
      searchTimeout = setTimeout(() => searchCustomers(query), 300);
    });

    document.addEventListener("click", function (e) {
      if (
        !e.target.closest("#customerSearch") &&
        !e.target.closest("#customerSearchResults")
      ) {
        if (customerSearchResults) customerSearchResults.classList.add("hidden");
      }
    });

    on(newCustomerCity, "change", function () {
      loadAreasForCustomer(this.value);
    });

    on(categorySelect, "change", function () {
      loadProducts(this.value);
    });

    on(orderShift, "change", function () {
      loadEmployees(this.value);
    });

    on(addProductBtn, "click", function () {
      addProductRow();
    });

    on(discountInput, "input", updateSummary);
    on(orderForm, "submit", saveOrder);
    on(customerForm, "submit", saveNewCustomer);

    // View Order modal events
    on(closeViewOrderModal, "click", () => {
      if (viewOrderModal) {
        viewOrderModal.classList.add("hidden");
        viewOrderModal.classList.remove("flex");
      }
    });
    on(closeViewOrderBtn, "click", () => {
      if (viewOrderModal) {
        viewOrderModal.classList.add("hidden");
        viewOrderModal.classList.remove("flex");
      }
    });
    on(printOrderBtn, "click", printOrderView);

    // Pay modal events
    on(closePayModal, "click", hidePayModal);
    on(closePayBtn, "click", hidePayModal);
    on(postTxnBtn, "click", postTransaction);

    // Invoice modal events
    on(closeInvoiceModal, "click", hideInvoiceModal);
    on(invLoadBtn, "click", loadInvoiceReport);
    on(invPrintBtn, "click", () => {
      const printAreaEl = document.getElementById("invoicePrintArea");
      if (!printAreaEl) return;
      const printArea = printAreaEl.innerHTML;
      const printWindow = window.open("", "", "width=1000,height=800");
      printWindow.document.write(`
        <html>
        <head>
            <title>Invoice Report</title>
            <style>
                body{
                    font-family: Arial, sans-serif;
                    padding:20px;
                }
                table{
                    width:100%;
                    border-collapse:collapse;
                    margin-top:20px;
                }
                th, td{
                    border:1px solid #ddd;
                    padding:8px;
                    font-size:14px;
                }
                th{
                    background:#222;
                    color:white;
                }
                .grid{
                    display:flex;
                    gap:15px;
                    margin-bottom:20px;
                }
                .border{
                    border:1px solid #ddd;
                }
                .p-4{
                    padding:15px;
                }
            </style>
        </head>
        <body>
            ${printArea}
        </body>
        </html>
    `);
      printWindow.document.close();
      printWindow.onload = function () {
        printWindow.print();
        printWindow.close();
      };
    });

    // Ledger modal events
    on(closeLedgerModal, "click", hideLedgerModal);
    on(ledLoadBtn, "click", loadLedgerReport);
    on(ledPrintBtn, "click", () => window.print());

    // ---- Static Status Modal Close Handlers - FIXED ----
    function closeStaticStatusModal() {
      const statusModal = document.getElementById("statusModal");
      if (statusModal) {
        statusModal.classList.add("hidden");
        statusModal.classList.remove("flex");
      }
    }

    const closeStatusModal = document.getElementById("closeStatusModal");
    const stCancelBtn = document.getElementById("stCancelBtn");
    const statusModal = document.getElementById("statusModal");
    
    if (closeStatusModal) {
      closeStatusModal.addEventListener("click", closeStaticStatusModal);
    }
    
    if (stCancelBtn) {
      stCancelBtn.addEventListener("click", closeStaticStatusModal);
    }
    
    if (statusModal) {
      statusModal.addEventListener("click", function(e) {
        if (e.target === statusModal) {
          closeStaticStatusModal();
        }
      });
    }

    // Action button handlers (Customer Orders tab) — icon buttons, no dropdown
    document.addEventListener("click", function (e) {
      const actionItem = e.target.closest(".action-dropdown-item");
      if (actionItem) {
        const action = actionItem.dataset.action;
        const id = actionItem.dataset.id;
        const customerId = actionItem.dataset.customerId;

        switch (action) {
          case "view":
            viewOrder(id);
            break;
          case "edit":
            showModal("Edit Order", id);
            break;
          case "pay":
            openPayModal(customerId);
            break;
          case "invoices":
            openInvoiceModal(customerId);
            break;
          case "ledger":
            openLedgerModal(customerId);
            break;
          case "delete":
            deleteOrder(id);
            break;
          case "update-status":
            if (window.openStatusModal) {
              const row = document.querySelector(`tr[data-order-id="${id}"]`);
              if (row) {
                const cells = row.querySelectorAll("td");
                const orderNo = id;
                const customerName = cells[3]?.textContent?.trim() || "";
                const statusBadge = cells[11]?.querySelector(".status-badge");
                const currentStatus = statusBadge?.textContent || "Pending";
                window.openStatusModal(id, orderNo, customerName, currentStatus);
              } else {
                window.openStatusModal(id, id, "", "Pending");
              }
            }
            break;
        }
      }

      // Update status button (Employee Orders tab)
      const statusBtn = e.target.closest(".update-status-btn");
      if (statusBtn) {
        updateOrderStatus(statusBtn.dataset.id, statusBtn.dataset.customerId);
        return;
      }
    });

    // ---- Filters ----
    on(statusFilterEl, "change", loadOrders);
    on(customerFilterEl, "change", loadOrders);
    on(searchInputEl, "keyup", loadOrders);
    on(dateFromEl, "change", loadOrders);
    on(dateToEl, "change", loadOrders);

    on(empSearchInputEl, "keyup", loadEmployeeOrders);
    on(empShiftFilterEl, "change", loadEmployeeOrders);

    // ---- Init ----
    loadOrders();
    loadCustomerFilter();
  });
})();