const API_BASE = "../../action/raw_materials";

let allMaterials = [];

document.addEventListener("DOMContentLoaded", () => {
  loadMaterials();

  document.getElementById("searchInput").addEventListener("input", (e) => {
    const term = e.target.value.toLowerCase();
    const filtered = allMaterials.filter((m) => m.name.toLowerCase().includes(term));
    renderTable(filtered);
  });
});

async function loadMaterials() {
  try {
    const res = await fetch(`${API_BASE}/get_raw_materials.php`);
    const json = await res.json();
    if (json.success) {
      allMaterials = json.data;
      renderTable(allMaterials);
    }
  } catch (err) {
    console.error("Failed to load raw materials:", err);
  }
}

function renderTable(materials) {
  const tbody = document.getElementById("materialsTableBody");
  tbody.innerHTML = "";

  if (materials.length === 0) {
    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-6 text-gray-400">No raw materials found</td></tr>`;
    return;
  }

  materials.forEach((m, index) => {
    const lowStockBadge = m.low_stock
      ? `<span class="ml-2 text-[10px] bg-red-100 text-red-700 px-2 py-0.5 rounded-full dark:bg-red-900 dark:text-red-300">LOW STOCK</span>`
      : "";

    const statusBadge =
      m.status === "active"
        ? `<span class="status-badge status-delivered">Active</span>`
        : `<span class="status-badge status-cancelled">Inactive</span>`;

    const row = document.createElement("tr");
    row.innerHTML = `
      <td class="border dark:border-gray-600 px-2 py-2">${index + 1}</td>
      <td class="border dark:border-gray-600 px-2 py-2">${m.name}</td>
      <td class="border dark:border-gray-600 px-2 py-2">${m.unit}</td>
      <td class="border dark:border-gray-600 px-2 py-2 text-right font-medium">${parseFloat(m.current_stock).toFixed(2)}${lowStockBadge}</td>
      <td class="border dark:border-gray-600 px-2 py-2 text-right">${parseFloat(m.reorder_level).toFixed(2)}</td>
      <td class="border dark:border-gray-600 px-2 py-2 text-right">${parseFloat(m.last_purchase_rate).toFixed(2)}</td>
      <td class="border dark:border-gray-600 px-2 py-2">${statusBadge}</td>
      <td class="border dark:border-gray-600 px-2 py-2">
        <button onclick="openEditModal(${m.id})" class="text-blue-600 hover:underline text-xs mr-2">Edit</button>
        <button onclick="deleteMaterial(${m.id})" class="text-red-600 hover:underline text-xs">Delete</button>
      </td>
    `;
    tbody.appendChild(row);
  });
}

function openAddModal() {
  document.getElementById("modalTitle").textContent = "Add Raw Material";
  document.getElementById("materialId").value = "";
  document.getElementById("materialName").value = "";
  document.getElementById("materialUnit").value = "piece";
  document.getElementById("openingStock").value = 0;
  document.getElementById("reorderLevel").value = 0;
  document.getElementById("openingStockWrap").classList.remove("hidden");
  document.getElementById("materialModal").classList.remove("hidden");
}

function openEditModal(id) {
  const m = allMaterials.find((x) => x.id == id);
  if (!m) return;

  document.getElementById("modalTitle").textContent = "Edit Raw Material";
  document.getElementById("materialId").value = m.id;
  document.getElementById("materialName").value = m.name;
  document.getElementById("materialUnit").value = m.unit;
  document.getElementById("reorderLevel").value = m.reorder_level;
  document.getElementById("openingStockWrap").classList.add("hidden"); // stock only changes via purchase/usage
  document.getElementById("materialModal").classList.remove("hidden");
}

function closeAddModal() {
  document.getElementById("materialModal").classList.add("hidden");
}

async function saveMaterial() {
  const id = document.getElementById("materialId").value;
  const name = document.getElementById("materialName").value.trim();
  const unit = document.getElementById("materialUnit").value;
  const reorder_level = document.getElementById("reorderLevel").value;
  const opening_stock = document.getElementById("openingStock").value;

  if (!name) {
    alert("Material name is required");
    return;
  }

  const isEdit = !!id;
  const url = isEdit ? `${API_BASE}/update_raw_material.php` : `${API_BASE}/add_raw_material.php`;
  const payload = isEdit
    ? { id, name, unit, reorder_level, status: "active" }
    : { name, unit, reorder_level, opening_stock };

  try {
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const json = await res.json();
    if (json.success) {
      closeAddModal();
      loadMaterials();
    } else {
      alert(json.message || "Something went wrong");
    }
  } catch (err) {
    alert("Request failed: " + err.message);
  }
}

async function deleteMaterial(id) {
  if (!confirm("Delete this raw material?")) return;

  try {
    const res = await fetch(`${API_BASE}/delete_raw_material.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id }),
    });
    const json = await res.json();
    if (json.success) {
      loadMaterials();
    } else {
      alert(json.message || "Cannot delete");
    }
  } catch (err) {
    alert("Request failed: " + err.message);
  }
}
