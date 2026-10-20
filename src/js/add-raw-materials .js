const API_BASE = "../../action/raw_materials";

document.addEventListener("DOMContentLoaded", () => {
  loadMaterials();

  document.getElementById("materialForm").addEventListener("submit", saveMaterial);
  document.getElementById("searchInput").addEventListener("input", (e) => {
    loadMaterials(e.target.value);
  });
});

async function saveMaterial(e) {
  e.preventDefault();

  const payload = {
    name: document.getElementById("materialName").value.trim(),
    unit: document.getElementById("materialUnit").value,
    reorder_level: document.getElementById("materialReorderLevel").value || 0,
  };

  const msgEl = document.getElementById("formMsg");

  try {
    const res = await fetch(`${API_BASE}/add_raw_material.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const json = await res.json();

    msgEl.classList.remove("hidden", "text-green-600", "text-red-600");

    if (json.success) {
      msgEl.textContent = json.message || "Raw material added";
      msgEl.classList.add("text-green-600");
      document.getElementById("materialForm").reset();
      loadMaterials();
    } else {
      msgEl.textContent = json.message || "Failed to add raw material";
      msgEl.classList.add("text-red-600");
    }
  } catch (err) {
    console.error("saveMaterial error", err);
    msgEl.classList.remove("hidden");
    msgEl.textContent = "Server error while saving";
    msgEl.classList.add("text-red-600");
  }
}

async function loadMaterials(search = "") {
  const params = new URLSearchParams();
  if (search) params.append("search", search);

  try {
    const res = await fetch(`${API_BASE}/get_raw_materials.php?${params.toString()}`);
    const json = await res.json();
    const tbody = document.getElementById("materialTableBody");
    tbody.innerHTML = "";

    if (!json.success || json.data.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-center py-6 text-gray-400">No raw materials found</td></tr>`;
      return;
    }

    json.data.forEach((m) => {
      const low = parseFloat(m.current_stock) <= parseFloat(m.reorder_level) && parseFloat(m.reorder_level) > 0;
      tbody.innerHTML += `
        <tr class="border-b dark:border-gray-700">
          <td class="py-2">${m.name}</td>
          <td class="py-2">${m.unit}</td>
          <td class="py-2 text-right ${low ? "text-red-500 font-semibold" : ""}">${parseFloat(m.current_stock).toFixed(2)}</td>
          <td class="py-2 text-right">${parseFloat(m.reorder_level).toFixed(2)}</td>
          <td class="py-2 text-right">${parseFloat(m.last_purchase_rate).toFixed(2)}</td>
        </tr>`;
    });
  } catch (err) {
    console.error("loadMaterials error", err);
  }
}