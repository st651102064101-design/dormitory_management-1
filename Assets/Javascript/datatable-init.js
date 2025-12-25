/**
 * Modern DataTable Initialization Helper
 * สำหรับระบบหอพัก - ใช้กับ simple-datatables@9.0.4
 */

// Default DataTable configuration
const defaultDataTableConfig = {
  searchable: true,
  fixedHeight: false,
  perPage: 10,
  perPageSelect: [5, 10, 25, 50, 100],
  labels: {
    placeholder: 'ค้นหา...',
    perPage: 'รายการต่อหน้า',
    noRows: 'ไม่พบข้อมูล',
    info: 'แสดง {start} ถึง {end} จาก {rows} รายการ'
  }
};

/**
 * Initialize a DataTable with modern styling
 * @param {string} tableId - The ID of the table element
 * @param {object} customConfig - Custom configuration to override defaults
 * @returns {object} - The DataTable instance
 */
function initModernDataTable(tableId, customConfig = {}) {
  const table = document.getElementById(tableId);
  if (!table) {
    console.warn(`DataTable: Table with ID "${tableId}" not found`);
    return null;
  }

  // Check if simple-datatables is loaded
  if (typeof simpleDatatables === 'undefined') {
    console.error('DataTable: simple-datatables library not loaded');
    return null;
  }

  // Merge default config with custom config
  const config = { ...defaultDataTableConfig, ...customConfig };

  try {
    const dataTable = new simpleDatatables.DataTable(table, config);
    console.log(`DataTable: Initialized table "${tableId}"`);
    return dataTable;
  } catch (error) {
    console.error(`DataTable: Error initializing table "${tableId}"`, error);
    return null;
  }
}

/**
 * Initialize multiple DataTables
 * @param {Array} tables - Array of {id: string, config?: object}
 * @returns {object} - Object with table IDs as keys and DataTable instances as values
 */
function initMultipleDataTables(tables) {
  const instances = {};
  tables.forEach(({ id, config }) => {
    instances[id] = initModernDataTable(id, config);
  });
  return instances;
}

/**
 * Auto-initialize DataTables on elements with data-datatable attribute
 */
function autoInitDataTables() {
  document.querySelectorAll('[data-datatable]').forEach(table => {
    if (table.id) {
      const configAttr = table.getAttribute('data-datatable-config');
      let config = {};
      if (configAttr) {
        try {
          config = JSON.parse(configAttr);
        } catch (e) {
          console.warn('DataTable: Invalid config JSON', e);
        }
      }
      initModernDataTable(table.id, config);
    }
  });
}

// Auto-initialize on DOM ready
document.addEventListener('DOMContentLoaded', autoInitDataTables);
