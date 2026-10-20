// js/customers.js
(function () {
  "use strict";

  // ---- DOM refs ----
  const modal = document.getElementById("modalOverlay");
  const viewModal = document.getElementById("viewModal");
  const openModalBtn = document.getElementById("openModal");
  const closeModalBtn = document.getElementById("closeModal");
  const closeBtn = document.getElementById("closeBtn");
  const closeViewModal = document.getElementById("closeViewModal");
  const modalTitle = document.getElementById("modalTitle");
  const customerForm = document.getElementById("customerForm");
  const customerIdInput = document.getElementById("customerId");

  const customerName = document.getElementById("customerName");
  const customerPhone = document.getElementById("customerPhone");
  const customerEmail = document.getElementById("customerEmail");
  const customerAddress = document.getElementById("customerAddress");

  const openingBalance = document.getElementById("openingBalance");
  const citySelect = document.getElementById("citySelect");
  const areaSelect = document.getElementById("areaSelect");

  const tableBody = document.getElementById("customersTableBody");
  const totalOpeningBalanceEl = document.getElementById("totalOpeningBalance");
  const totalCurrentBalanceEl = document.getElementById("totalCurrentBalance");

  let editingCustomerId = 0;
  const API_BASE = "../../action/customers";

  // ---- Improved API Helper ----
  async function fetchAPI(url, options = {}) {
    try {
      const res = await fetch(url, options);
      if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
      const json = await res.json();
      console.log(`API Response from ${url.split('/').pop()}:`, json);
      return json;
    } catch (err) {
      console.error(`Fetch API Error (${url}):`, err);
      // Return a consistent error object
      return { error: true, message: err.message };
    }
  }

  function showModal(title = "Add Customer", customerId = 0) {
    editingCustomerId = customerId;
    modalTitle.textContent = title;
    customerIdInput.value = customerId;
    modal.classList.remove("hidden");
    modal.classList.add("flex");
    if (customerId === 0) {
      customerForm.reset();
      openingBalance.value = "0.00";
      citySelect.value = "";
      areaSelect.innerHTML = '<option value="">Select Area</option>';
    }

    loadCities();
    if (customerId > 0) {
      loadCustomerForEdit(customerId);
    }
  }

  function hideModal() {
    modal.classList.add("hidden");
    modal.classList.remove("flex");
  }

  function showViewModal(customerId) {
    viewModal.classList.remove("hidden");
    viewModal.classList.add("flex");
    loadCustomerDetails(customerId);
  }

  function hideViewModal() {
    viewModal.classList.add("hidden");
    viewModal.classList.remove("flex");
  }

  // ---- Load Cities ----
  async function loadCities() {
    const response = await fetchAPI(`${API_BASE}/get_cities.php`);
    if (!response || response.error) return;

    // Handle both raw arrays or { status: true, data: [...] } formats
    const data = Array.isArray(response) ? response : (response.data || []);

    citySelect.innerHTML = '<option value="">Select City</option>';
    if (data && data.length > 0) {
      data.forEach((city) => {
        const opt = document.createElement("option");
        opt.value = city.id;
        opt.textContent = city.city_name;
        citySelect.appendChild(opt);
      });
    }

    // Also load city filter
    const cityFilter = document.getElementById("cityFilter");
    if (cityFilter) {
      cityFilter.innerHTML = '<option value="">All Cities</option>';
      data.forEach((city) => {
        const opt = document.createElement("option");
        opt.value = city.id;
        opt.textContent = city.city_name;
        cityFilter.appendChild(opt);
      });
    }
  }

  // ---- Load Areas based on City ----
  async function loadAreas(cityId) {
    if (!cityId) {
      areaSelect.innerHTML = '<option value="">Select Area</option>';
      return;
    }
    const response = await fetchAPI(`${API_BASE}/get-area.php?city_id=${cityId}`);
    if (!response || response.error) return;

    const data = Array.isArray(response) ? response : (response.data || []);

    areaSelect.innerHTML = '<option value="">Select Area</option>';
    if (data && data.length > 0) {
      data.forEach((area) => {
        const opt = document.createElement("option");
        opt.value = area.id;
        opt.textContent = area.area_name;
        areaSelect.appendChild(opt);
      });
    } else {
      areaSelect.innerHTML = '<option value="">No areas found for this city</option>';
    }
  }

  // ---- Load Customer for Edit ----
  async function loadCustomerForEdit(customerId) {
    const response = await fetchAPI(`${API_BASE}/get_customer.php?id=${customerId}`);
    if (!response || response.error || !response.customer) {
      console.error("Could not find customer object in response:", response);
      return;
    }

    const c = response.customer;
    customerName.value = c.name || "";
    customerPhone.value = c.phone || "";
    customerEmail.value = c.email || "";
    customerAddress.value = c.address || "";
    openingBalance.value = c.opening_balance || "0.00";
    citySelect.value = c.city_id || "";
    
    // Load areas based on selected city
    await loadAreas(c.city_id);
    areaSelect.value = c.area_id || "";
  }

  // ---- Load Customer Details for View ----
  async function loadCustomerDetails(customerId) {
    const response = await fetchAPI(`${API_BASE}/get_customer.php?id=${customerId}`);
    if (!response || response.error || !response.customer) return;

    const c = response.customer;
    document.getElementById("viewContent").innerHTML = `
            <div class="grid md:grid-cols-2 gap-4">
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Customer Code</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200">${c.customer_code || "N/A"}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Name</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200">${c.name || "N/A"}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Phone</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200">${c.phone || "N/A"}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Email</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200">${c.email || "N/A"}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Category</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200">${c.category_name || "N/A"}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Area</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200">${c.area_name || "N/A"}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Opening Balance</p>
                    <p class="font-semibold text-green-600 dark:text-green-400">${parseFloat(c.opening_balance || 0).toFixed(2)}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Current Balance</p>
                    <p class="font-semibold ${parseFloat(c.current_balance || 0) >= 0 ? "text-green-600 dark:text-green-400" : "text-red-600 dark:text-red-400"}">${parseFloat(c.current_balance || 0).toFixed(2)}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Address</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200">${c.address || "N/A"}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
                    <p class="font-semibold ${c.status === "active" ? "text-green-600 dark:text-green-400" : "text-red-600 dark:text-red-400"}">${c.status || "N/A"}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg md:col-span-2">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Created At</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200">${c.created_at || "N/A"}</p>
                </div>
            </div>
        `;
  }

  
  async function loadCustomers() {
    const search = document.getElementById("searchInput").value;
    const area = document.getElementById("areaFilter").value;
    const city = document.getElementById("cityFilter").value;

    let url = `${API_BASE}/get_customers.php`;
    const params = [];
    if (search) params.push(`search=${encodeURIComponent(search)}`);
    if (area) params.push(`area=${area}`);
    if (city) params.push(`city=${city}`);
    if (params.length) url += "?" + params.join("&");

    let data = [];
    let rawText = "";
    try {
      const res = await fetch(url);
      rawText = await res.text();

      // Detect PHP warnings/errors mixed before JSON
      const jsonStart = rawText.indexOf("{");
      const jsonStr = jsonStart !== -1 ? rawText.slice(jsonStart) : rawText;

      if (jsonStart > 0) {
        console.warn("PHP output before JSON detected:", rawText.slice(0, jsonStart));
      }

      const json = JSON.parse(jsonStr);
      console.log("API response for customers:", json);

      if (json && json.data && Array.isArray(json.data)) {
        data = json.data;
      } else if (Array.isArray(json)) {
        data = json;
      } else {
        console.warn("Unexpected API response format:", json);
      }
    } catch (err) {
      console.error("Failed to load customers from API:", err);
      console.error("Raw server response:", rawText);
    }

    // Ensure tableBody exists before modifying it
    if (!tableBody) {
      console.error("HTML element 'customersTableBody' not found on this page!");
      return;
    }

    // Safety check — ensure data is always an array
    if (!Array.isArray(data)) {
      data = [];
    }

    tableBody.innerHTML = "";
    let totalOpening = 0;
    let totalCurrent = 0;

    if (data.length === 0) {
      tableBody.innerHTML = `<tr><td colspan="11" class="border dark:border-gray-700 px-4 py-8 text-center text-gray-500 dark:text-gray-400">No customers found</td></tr>`;
      if (totalOpeningBalanceEl) totalOpeningBalanceEl.textContent = "0.00";
      if (totalCurrentBalanceEl) totalCurrentBalanceEl.textContent = "0.00";
      return;
    }

    console.log("Data loaded:", data.length, "customers");

    data.forEach((c, index) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
                <td class="border dark:border-gray-700 px-2 py-2">${index + 1}</td>
                <td class="border dark:border-gray-700 px-2 py-2 font-mono text-xs">${c.customer_code || "-"}</td>
                <td class="border dark:border-gray-700 px-2 py-2 font-semibold">${c.name || "-"}</td>
                <td class="border dark:border-gray-700 px-2 py-2">${c.phone || "-"}</td>
                <td class="border dark:border-gray-700 px-2 py-2">${c.email || "-"}</td>
                
                <td class="border dark:border-gray-700 px-2 py-2">${c.area_name || "-"}</td>
                <td class="border dark:border-gray-700 px-2 py-2">${parseFloat(c.opening_balance || 0).toFixed(2)}</td>
                <td class="border dark:border-gray-700 px-2 py-2 ${parseFloat(c.current_balance || 0) >= 0 ? "text-green-600 dark:text-green-400" : "text-red-600 dark:text-red-400"}">${parseFloat(c.current_balance || 0).toFixed(2)}</td>
                
                <td class="border dark:border-gray-700 px-2 py-2 relative">
                    <button class="action-btn border dark:border-gray-600 rounded px-3 py-1 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-1" data-customer-id="${c.id}">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <div class="action-dropdown hidden min-w-[200px]">
                        <button class="action-dropdown-item w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-3" data-action="view" data-id="${c.id}">
                            <i class="fa-solid fa-eye text-blue-500 w-4"></i> View
                        </button>
                        <button class="action-dropdown-item w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-3" data-action="edit" data-id="${c.id}">
                            <i class="fa-solid fa-pen text-green-500 w-4"></i> Edit
                        </button>
                        <button class="action-dropdown-item w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-3" data-action="orders" data-id="${c.id}">
                            <i class="fa-solid fa-shopping-cart text-purple-500 w-4"></i> Orders
                        </button>
                        <button class="action-dropdown-item w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-3" data-action="invoices" data-id="${c.id}">
                            <i class="fa-solid fa-file-invoice text-indigo-500 w-4"></i> Invoices
                        </button>
                        <button class="action-dropdown-item w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-3" data-action="ledger" data-id="${c.id}">
                            <i class="fa-solid fa-book text-amber-500 w-4"></i> Ledger
                        </button>
                        <button class="action-dropdown-item w-full text-left px-4 py-2.5 text-sm hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-3 text-red-600 dark:text-red-400" data-action="delete" data-id="${c.id}">
                            <i class="fa-solid fa-trash w-4"></i> Delete
                        </button>
                    </div>
                </td>
            `;
      tableBody.appendChild(tr);

      totalOpening += parseFloat(c.opening_balance || 0);
      totalCurrent += parseFloat(c.current_balance || 0);
    });

    if (totalOpeningBalanceEl) totalOpeningBalanceEl.textContent = totalOpening.toFixed(2);
    if (totalCurrentBalanceEl) totalCurrentBalanceEl.textContent = totalCurrent.toFixed(2);
  }
  // ---- Save Customer ----
  async function saveCustomer(e) {
    e.preventDefault();

    const requiredFields = [
      customerName,
      customerPhone,
      citySelect,
      areaSelect,
    ];
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

    const customerId = customerIdInput.value;
    const url =
      customerId && customerId !== "0"
        ? `${API_BASE}/update_customer.php`
        : `${API_BASE}/add_customer.php`;
    const method = customerId && customerId !== "0" ? "PUT" : "POST";

    const payload = {
      id: customerId,
      name: customerName.value,
      phone: customerPhone.value,
      email: customerEmail.value,
      address: customerAddress.value,
      opening_balance: openingBalance.value,
      city_id: citySelect.value,
      area_id: areaSelect.value,
    };

    try {
      const res = await fetch(url, {
        method: method,
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const result = await res.json();
      if (result.success) {
        alert(result.message || "Customer saved successfully!");
        hideModal();
        loadCustomers();
      } else {
        alert(result.message || "Error saving customer.");
      }
    } catch (err) {
      alert("Server error. Please try again.");
      console.error(err);
    }
  }

  // ---- Delete Customer ----
  async function deleteCustomer(customerId) {
    if (
      !confirm(
        "Are you sure you want to delete this customer? This action cannot be undone.",
      )
    ) return;
    try {
      const res = await fetch(
        `${API_BASE}/delete_customer.php?id=${customerId}`,
        {
          method: "DELETE",
        },
      );
      const result = await res.json();
      if (result.success) {
        alert(result.message);
        loadCustomers();
      } else {
        alert(result.message || "Error deleting customer.");
      }
    } catch (err) {
      alert("Server error.");
      console.error(err);
    }
  }

  // ---- Load Area Filter ----
  async function loadAreaFilter() {
    const response = await fetchAPI(`${API_BASE}/get-area.php`);
    const areaFilter = document.getElementById("areaFilter");
    if (areaFilter && response && !response.error) {
      const data = Array.isArray(response) ? response : (response.data || []);
      areaFilter.innerHTML = '<option value="">All Areas</option>';
      data.forEach((area) => {
        const opt = document.createElement("option");
        opt.value = area.id;
        opt.textContent = area.area_name;
        areaFilter.appendChild(opt);
      });
    }
  }

  // ======================== EVENT LISTENERS ========================
  if (openModalBtn) {
    openModalBtn.addEventListener("click", () => {
      showModal("Add Customer", 0);
    });
  }
  if (closeModalBtn) closeModalBtn.addEventListener("click", hideModal);
  if (closeBtn) closeBtn.addEventListener("click", hideModal);
  if (modal) {
    modal.addEventListener("click", (e) => {
      if (e.target === modal) hideModal();
    });
  }
  if (closeViewModal) closeViewModal.addEventListener("click", hideViewModal);
  if (viewModal) {
    viewModal.addEventListener("click", (e) => {
      if (e.target === viewModal) hideViewModal();
    });
  }
  // ---- City Select Change ----
  if (citySelect) {
    citySelect.addEventListener("change", function () {
      const cityId = this.value;
      loadAreas(cityId);
    });
  }

  // ---- Form Submit ----
  if (customerForm) {
    customerForm.addEventListener("submit", saveCustomer);
  }

  // ---- Action Dropdowns (Global Click Handler) ----
  document.addEventListener("click", function (e) {
    const actionBtn = e.target.closest(".action-btn");
    if (actionBtn) {
      const dropdown = actionBtn.nextElementSibling;
      const allDropdowns = document.querySelectorAll(".action-dropdown");
      allDropdowns.forEach((d) => {
        if (d !== dropdown) d.classList.add("hidden");
      });
      if (dropdown) dropdown.classList.toggle("hidden");
      return;
    }

    const actionItem = e.target.closest(".action-dropdown-item");
    if (actionItem) {
      const action = actionItem.dataset.action;
      const id = actionItem.dataset.id;

      const dropdown = actionItem.closest(".action-dropdown");
      if (dropdown) dropdown.classList.add("hidden");

      switch (action) {
        case "view":
          showViewModal(id);
          break;
        case "edit":
          showModal("Edit Customer", id);
          break;
        case "orders":
          window.location.href = `orders.html?customer_id=${id}`;
          break;
        case "invoices":
          window.location.href = `invoices.html?customer_id=${id}`;
          break;
        case "ledger":
          window.location.href = `ledger.html?customer_id=${id}`;
          break;
        case "delete":
          deleteCustomer(id);
          break;
      }
    }

    // Close dropdowns when clicking outside
    if (
      !e.target.closest(".action-dropdown") &&
      !e.target.closest(".action-btn")
    ) {
      document
        .querySelectorAll(".action-dropdown")
        .forEach((d) => d.classList.add("hidden"));
    }
  });

  // ---- Filter Event Listeners ----
  const areaFilter = document.getElementById("areaFilter");
  const cityFilter = document.getElementById("cityFilter");
  const searchInput = document.getElementById("searchInput");

  if (areaFilter) areaFilter.addEventListener("change", loadCustomers);
  if (cityFilter) cityFilter.addEventListener("change", loadCustomers);
  if (searchInput) {
    searchInput.addEventListener("keyup", function () {
      loadCustomers();
    });
  }

  // ---- Filter Cascading: Update area filters when city filter changes ----
  if (cityFilter && areaFilter) {
    cityFilter.addEventListener("change", async function () {
      const selectedCityId = this.value;
      
      // Clear area dropdown
      areaFilter.innerHTML = '<option value="">All Areas</option>';
      
      let url = `${API_BASE}/get-area.php`;
      if (selectedCityId) {
        url += `?city_id=${selectedCityId}`;
      }
      
      const response = await fetchAPI(url);
      if (response && !response.error) {
        const data = Array.isArray(response) ? response : (response.data || []);
        data.forEach((area) => {
          const opt = document.createElement("option");
          opt.value = area.id;
          opt.textContent = area.area_name;
          areaFilter.appendChild(opt);
        });
      }
      
      // Trigger customer reload
      loadCustomers();
    });
  }

  // ======================== INITIALIZATION ========================
  loadAreaFilter();
  loadCustomers();

})();