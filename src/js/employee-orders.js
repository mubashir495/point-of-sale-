(function () {
  "use strict";
  function on(el, event, handler) {
    if (el) el.addEventListener(event, handler);
  }

  document.addEventListener("DOMContentLoaded", function () {
    // ---- DOM refs ----
    const employeeOrdersTableBody = document.getElementById(
      "employeeOrdersTableBody",
    );
    const empSearchInputEl = document.getElementById("empSearchInput");
    const empShiftFilterEl = document.getElementById("empShiftFilter");
    const empStatusFilterEl = document.getElementById("empStatusFilter");

    // ---- API ----
    const API_BASE = "../../action";

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

    function statusClassFor(status) {
      if (status === "Delivered") return "status-delivered";
      if (status === "Confirmed") return "status-confirmed";
      if (status === "Complete" || status === "Completed") return "status-invoiced";
      if (status === "Cancelled") return "status-cancelled";
      return "status-pending";
    }

    // ---- Load Employee Orders ----
    async function loadEmployeeOrders() {
      const search = empSearchInputEl ? empSearchInputEl.value : "";
      const shift = empShiftFilterEl ? empShiftFilterEl.value : "";
      const status = empStatusFilterEl ? empStatusFilterEl.value : "";

      let url = `${API_BASE}/orders/get_employee_orders.php`;
      const params = [];
      if (search) params.push(`search=${encodeURIComponent(search)}`);
      if (shift) params.push(`shift=${shift}`);
      if (status) params.push(`status=${status}`);
      if (params.length) url += "?" + params.join("&");

      const res = await fetchAPI(url);
      const data = res && res.data ? res.data : [];
      if (!employeeOrdersTableBody) return;
      employeeOrdersTableBody.innerHTML = "";

      if (!data || data.length === 0) {
        employeeOrdersTableBody.innerHTML = `<tr><td colspan="11" class="border dark:border-gray-700 px-4 py-8 text-center text-gray-500">No records found</td></tr>`;
        return;
      }

      data.forEach((r, index) => {
        const itemsSummary =
          (r.items || [])
            .map((it) => `${it.product_name} x${it.quantity}`)
            .join(", ") || "-";
        const tr = document.createElement("tr");
        tr.setAttribute("data-order-id", r.order_id || "");
        tr.innerHTML = `
        <td class="border dark:border-gray-700 px-2 py-2">${index + 1}</td>
        <td class="border dark:border-gray-700 px-2 py-2 font-semibold">${r.full_name}</td>
        <td class="border dark:border-gray-700 px-2 py-2">${r.designation || "-"}</td>
        <td class="border dark:border-gray-700 px-2 py-2">${r.employee_shift || "-"}</td>
        <td class="border dark:border-gray-700 px-2 py-2 font-mono text-xs">${r.order_no || "-"}</td>
        <td class="border dark:border-gray-700 px-2 py-2">${r.customer_name || "-"}</td>
        <td class="border dark:border-gray-700 px-2 py-2 text-xs">${itemsSummary}</td>
        <td class="border dark:border-gray-700 px-2 py-2">${r.order_date || "-"}</td>
        <td class="border dark:border-gray-700 px-2 py-2 text-right">${r.total ? parseFloat(r.total).toFixed(2) : "-"}</td>
        <td class="border dark:border-gray-700 px-2 py-2">
          ${r.status ? `<span class="status-badge ${statusClassFor(r.status)}">${r.status}</span>` : "-"}
        </td>
      `;
        employeeOrdersTableBody.appendChild(tr);
      });
    }

    // ---- Status update API ----
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
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });

        const result = await res.json();
        if (!result.success) throw new Error(result.message || "Update failed");
        return result;
      } catch (error) {
        console.error("Status Update Error:", error);
        throw error;
      }
    };

    // ---- Update Order Status modal (dynamic) ----
    async function updateOrderStatus(orderId, customerId) {
      const oldModal = document.getElementById("dynamicStatusModal");
      if (oldModal) oldModal.remove();

      const modalEl = document.createElement("div");
      modalEl.id = "dynamicStatusModal";
      modalEl.className =
        "fixed inset-0 bg-black/50 flex items-center justify-center z-50";

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

          <div class="flex justify-end gap-3 mt-5">
            <button id="cancelStatus" class="px-4 py-2 border rounded-lg">Cancel</button>
            <button id="saveStatus" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Update</button>
          </div>
        </div>
      `;

      document.body.appendChild(modalEl);

      const cancelStatusBtn = document.getElementById("cancelStatus");
      if (cancelStatusBtn) {
        cancelStatusBtn.onclick = () => modalEl.remove();
      }

      const saveStatusBtn = document.getElementById("saveStatus");
      if (saveStatusBtn) {
        saveStatusBtn.onclick = async () => {
          const statusSelect = document.getElementById("statusSelect");
          const status = statusSelect ? statusSelect.value : "";
          saveStatusBtn.disabled = true;
          saveStatusBtn.textContent = "Updating...";

          try {
            if (status === "Complete") {
              try {
                const orderRes = await fetch(
                  `${API_BASE}/orders/get_order.php?id=${orderId}`,
                );
                const orderData = await orderRes.json();

                if (!orderData.success || !orderData.order) {
                  alert("Order data not found");
                  saveStatusBtn.disabled = false;
                  saveStatusBtn.textContent = "Update";
                  return;
                }

                const resolvedCustomerId =
                  orderData.order.customer_id || customerId;
                const totalAmount =
                  orderData.order.grand_total || orderData.order.total || 0;
                const orderItems = (orderData.items || []).map((it) => ({
                  product_id: it.product_id,
                  quantity: it.quantity,
                }));

                modalEl.remove();

                setTimeout(() => {
                  openPaymentModal(
                    orderId,
                    resolvedCustomerId,
                    totalAmount,
                    orderItems,
                  );
                }, 100);
              } catch (error) {
                console.error("Error fetching order:", error);
                alert("Order fetch error: " + error.message);
                saveStatusBtn.disabled = false;
                saveStatusBtn.textContent = "Update";
              }
              return;
            }

            await updateStatusAPI(orderId, status);
            modalEl.remove();
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

    // ---- Payment modal (Complete status Paid/Unpaid flow) ----
    window.openPaymentModal = (orderId, customerId, amount, items = []) => {
      const old = document.getElementById("paymentModal");
      if (old) old.remove();

      const dynamicStatusModal = document.getElementById("dynamicStatusModal");
      if (dynamicStatusModal) dynamicStatusModal.remove();

      const modalEl = document.createElement("div");
      modalEl.id = "paymentModal";
      modalEl.className =
        "fixed inset-0 bg-black/50 flex items-center justify-center z-50";

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

      let isPaid = true;
      const paidBtn = document.getElementById("pmPaidBtn");
      const unpaidBtn = document.getElementById("pmUnpaidBtn");
      const paidFields = document.getElementById("pmPaidFields");

      function refreshToggle() {
        if (isPaid) {
          if (paidBtn)
            paidBtn.className =
              "px-4 py-2 rounded-lg border-2 border-green-500 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 font-semibold transition";
          if (unpaidBtn)
            unpaidBtn.className =
              "px-4 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 font-semibold transition";
          if (paidFields) paidFields.classList.remove("hidden");
        } else {
          if (unpaidBtn)
            unpaidBtn.className =
              "px-4 py-2 rounded-lg border-2 border-red-500 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 font-semibold transition";
          if (paidBtn)
            paidBtn.className =
              "px-4 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 font-semibold transition";
          if (paidFields) paidFields.classList.add("hidden");
        }
      }
      if (paidBtn) paidBtn.textContent = "Paid";
      if (unpaidBtn) unpaidBtn.textContent = "Unpaid";
      refreshToggle();

      if (paidBtn) paidBtn.onclick = () => { isPaid = true; refreshToggle(); };
      if (unpaidBtn) unpaidBtn.onclick = () => { isPaid = false; refreshToggle(); };

      const closePaymentBtn = document.getElementById("closePayment");
      if (closePaymentBtn) {
        closePaymentBtn.onclick = () => {
          modalEl.remove();
          loadEmployeeOrders();
        };
      }

      const savePaymentBtn = document.getElementById("savePayment");
      if (savePaymentBtn) {
        savePaymentBtn.onclick = async () => {
          savePaymentBtn.disabled = true;
          savePaymentBtn.textContent = "Saving...";

          try {
            let enteredAmount = 0;
            if (isPaid) {
              const paymentAmountEl = document.getElementById("paymentAmount");
              enteredAmount =
                parseFloat(paymentAmountEl ? paymentAmountEl.value : amount) ||
                0;

              if (!enteredAmount || enteredAmount <= 0) {
                alert("Please enter a valid amount.");
                savePaymentBtn.disabled = false;
                savePaymentBtn.textContent = "Save";
                return;
              }
            }

            // One atomic call: update_status.php marks the order
            // Complete, deducts stock, and - only when payment_received
            // is true - creates/syncs the order's invoice and applies
            // the payment to it + the customer ledger in the same
            // transaction.
            await updateStatusAPI(orderId, "Complete", {
              items,
              payment_received: isPaid,
              paid_amount: isPaid ? enteredAmount : 0,
            });

            alert(
              isPaid
                ? "Payment recorded and order marked Complete."
                : "Order marked Complete (unpaid).",
            );
            modalEl.remove();

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

    // ---- Event listeners ----
    document.addEventListener("click", function (e) {
      const statusBtn = e.target.closest(".update-status-btn");
      if (statusBtn) {
        updateOrderStatus(statusBtn.dataset.id, statusBtn.dataset.customerId);
      }
    });

    on(empSearchInputEl, "keyup", loadEmployeeOrders);
    on(empShiftFilterEl, "change", loadEmployeeOrders);
    on(empStatusFilterEl, "change", loadEmployeeOrders);

    // ---- Init ----
    loadEmployeeOrders();
  });
})();