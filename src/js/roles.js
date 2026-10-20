// roles.js
// ------------------------------------------------------------------
// Role List + Add/Edit Role + Permission Matrix logic for roles.html
// ------------------------------------------------------------------

const API_BASE = '../../action';

let MODULES = {};   // { module_key: "Module Label" }
let ACTIONS = [];   // ["view","create","edit","delete","print","export"]
let currentMatrix = {}; // working permission state while modal is open

document.getElementById('currentDate') && (document.getElementById('currentDate').textContent =
  new Date().toLocaleDateString("en-US", { weekday: "short", year: "numeric", month: "short", day: "numeric" }));

// -----------------------------------------------------------
// UTILITY
// -----------------------------------------------------------
async function safeFetchJson(url, opts) {
  try {
    // 'no-store' guarantees the browser never serves a cached copy of
    // a role's data - important for GET ?id=X right after a save, so
    // re-opening "Edit" always shows what was actually just persisted.
    const fetchOpts = { cache: 'no-store', ...(opts || {}) };
    const res = await fetch(url, fetchOpts);
    const text = await res.text();
    if (!text) return { success: false, message: `Empty response from ${url}` };
    try { return JSON.parse(text); } catch (e) {
      return { success: false, message: `Invalid JSON: ${text.slice(0, 300)}` };
    }
  } catch (err) {
    return { success: false, message: `Request failed: ${err.message}` };
  }
}

// -----------------------------------------------------------
// LOAD CATALOG (modules + actions) then LOAD ROLES
// -----------------------------------------------------------
async function loadCatalog() {
  const json = await safeFetchJson(`${API_BASE}/roles/roles.php?catalog=1`);
  if (json.success) {
    MODULES = json.data.modules;
    ACTIONS = json.data.actions;
  }
}

async function loadRoles() {
  const tbody = document.getElementById('rolesTbody');
  const noResults = document.getElementById('noResults');
  const json = await safeFetchJson(`${API_BASE}/roles/roles.php`);

  tbody.innerHTML = '';

  if (!json.success || !json.data || !json.data.length) {
    noResults.classList.remove('hidden');
    noResults.textContent = json.success ? 'No roles found.' : (json.message || 'Failed to load roles.');
    return;
  }
  noResults.classList.add('hidden');

  json.data.forEach((r, idx) => {
    const totalPermissions = Object.keys(MODULES).length * ACTIONS.length;
    tbody.innerHTML += `
      <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
        <td class="border dark:border-gray-700 px-4 py-2.5 text-gray-500 dark:text-gray-400">${idx + 1}</td>
        <td class="border dark:border-gray-700 px-4 py-2.5 font-semibold text-gray-800 dark:text-gray-200">${escapeHtml(r.name)}</td>
        <td class="border dark:border-gray-700 px-4 py-2.5 text-gray-500 dark:text-gray-400">${escapeHtml(r.description || '')}</td>
        <td class="border dark:border-gray-700 px-4 py-2.5 text-center">
          ${r.is_system == 1
            ? '<span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Default</span>'
            : '<span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">Custom</span>'}
        </td>
        <td class="border dark:border-gray-700 px-4 py-2.5 text-center text-gray-600 dark:text-gray-300">${r.user_count}</td>
        <td class="border dark:border-gray-700 px-4 py-2.5 text-center text-gray-600 dark:text-gray-300">${r.permission_count} / ${totalPermissions}</td>
        <td class="border dark:border-gray-700 px-4 py-2.5">
          <div class="flex justify-center gap-2">
            <button onclick="openRoleModal(${r.id})" class="text-yellow-600 dark:text-yellow-400 border border-yellow-500 px-2.5 py-1.5 rounded-lg hover:bg-yellow-50 dark:hover:bg-yellow-900/20" title="Edit / assign permissions">
              <i class="fa-regular fa-pen-to-square"></i>
            </button>
            ${r.is_system == 1
              ? `<button disabled title="Default roles cannot be deleted" class="text-gray-300 dark:text-gray-600 border border-gray-200 dark:border-gray-700 px-2.5 py-1.5 rounded-lg cursor-not-allowed"><i class="fa-regular fa-trash-can"></i></button>`
              : `<button onclick="deleteRole(${r.id})" class="text-red-600 dark:text-red-400 border border-red-500 px-2.5 py-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20"><i class="fa-regular fa-trash-can"></i></button>`}
          </div>
        </td>
      </tr>`;
  });
}

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));
}

// -----------------------------------------------------------
// PERMISSION MATRIX RENDERING
// -----------------------------------------------------------
function buildEmptyMatrix() {
  const m = {};
  Object.keys(MODULES).forEach((key) => {
    m[key] = {};
    ACTIONS.forEach((a) => { m[key][a] = false; });
  });
  return m;
}

function renderMatrix() {
  const tbody = document.getElementById('matrixTbody');
  tbody.innerHTML = Object.entries(MODULES).map(([key, label]) => `
    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
      <td class="border dark:border-gray-700 px-3 py-2 font-medium text-gray-700 dark:text-gray-300">${label}</td>
      ${ACTIONS.map((a) => `
        <td class="border dark:border-gray-700 px-3 py-2 text-center">
          <input type="checkbox" class="w-4 h-4 accent-blue-600 cursor-pointer"
            data-module="${key}" data-action="${a}"
            ${currentMatrix[key] && currentMatrix[key][a] ? 'checked' : ''}
            onchange="currentMatrix['${key}']['${a}'] = this.checked" />
        </td>`).join('')}
    </tr>`).join('');
}

function setAllPermissions(value) {
  Object.keys(currentMatrix).forEach((key) => {
    ACTIONS.forEach((a) => { currentMatrix[key][a] = value; });
  });
  renderMatrix();
}

// -----------------------------------------------------------
// MODAL CONTROLS
// -----------------------------------------------------------
async function openRoleModal(id = null) {
  document.getElementById('roleModalTitle').innerHTML = id
    ? '<i class="fa-solid fa-user-shield mr-2"></i>Edit Role'
    : '<i class="fa-solid fa-user-shield mr-2"></i>Add Role';
  document.getElementById('roleId').value = id ?? '';
  document.getElementById('roleNameInput').value = '';
  document.getElementById('roleDescInput').value = '';
  currentMatrix = buildEmptyMatrix();

  const modal = document.getElementById('roleModal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');

  if (id) {
    const json = await safeFetchJson(`${API_BASE}/roles/roles.php?id=${id}`);
    if (json.success) {
      const role = json.data;
      document.getElementById('roleNameInput').value = role.name;
      document.getElementById('roleDescInput').value = role.description || '';
      currentMatrix = role.permissions;

      // Super Admin's grid is locked (always full access, non-editable)
      const isSuperAdmin = role.slug === 'super-admin';
      document.getElementById('roleNameInput').disabled = isSuperAdmin;
    } else {
      alert(json.message || 'Failed to load role.');
    }
  } else {
    document.getElementById('roleNameInput').disabled = false;
  }

  renderMatrix();
}

function closeRoleModal() {
  const modal = document.getElementById('roleModal');
  modal.classList.add('hidden');
  modal.classList.remove('flex');
}

// -----------------------------------------------------------
// SAVE / DELETE
// -----------------------------------------------------------
async function saveRole() {
  const id = document.getElementById('roleId').value;
  const name = document.getElementById('roleNameInput').value.trim();
  const description = document.getElementById('roleDescInput').value.trim();

  if (!name) { alert('Role name is required.'); return; }

  const payload = { name, description, permissions: currentMatrix };
  const url = id ? `${API_BASE}/roles/roles.php?id=${id}` : `${API_BASE}/roles/roles.php`;

  const json = await safeFetchJson(url, {
    method: id ? 'PUT' : 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });

  if (json.success) {
    closeRoleModal();
    loadRoles();
  } else {
    alert(json.message || 'Save failed.');
  }
}

async function deleteRole(id) {
  if (!confirm('Delete this role? Users assigned to it must be reassigned first.')) return;
  const json = await safeFetchJson(`${API_BASE}/roles/roles.php?id=${id}`, { method: 'DELETE' });
  if (json.success) loadRoles();
  else alert(json.message || 'Delete failed.');
}

// -----------------------------------------------------------
// INIT
// -----------------------------------------------------------
(async function init() {
  await loadCatalog();
  await loadRoles();
})();
