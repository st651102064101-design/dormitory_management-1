import re

with open('/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Reports/report_repairs.php', 'r', encoding='utf-8') as f:
    content = f.read()

new_styles = """      .reports-container { width: 100%; max-width: 100%; padding: 0; }
      .reports-container .container { max-width: 100%; width: 100%; padding: 0 1.5rem 1.5rem; }
      
      .repair-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
      .stat-card { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); transition: transform 0.2s, box-shadow 0.2s; }
      .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1); border-color: #e2e8f0; }
      .stat-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
      .stat-label { font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 0.2rem; }
      .stat-value { font-size: 2.25rem; font-weight: 800; color: #0f172a; margin: 0.5rem 0; letter-spacing: -0.025em; }
      
      .view-toggle { display: flex; gap: 0.5rem; }
      .view-toggle-btn { padding: 0.75rem 1.5rem; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; color: #64748b; cursor: pointer; transition: all 0.2s; font-weight: 600; }
      .view-toggle-btn.active { background: #60a5fa; border-color: #60a5fa; color: #fff; }
      .view-toggle-btn:hover:not(.active) { background: #f1f5f9; color: #1f2937; }
      
      .repair-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 1.5rem; }
      .repair-card { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 16px; padding: 1.5rem; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); }
      .repair-card:hover { transform: translateY(-2px); border-color: #bae6fd; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1); }
      .repair-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb; }
      
      .repair-status { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-align: center; }
      .status-pending { background: rgba(251, 191, 36, 0.15); color: #d97706; border: 1px solid rgba(251, 191, 36, 0.3); }
      .status-progress { background: rgba(96, 165, 250, 0.15); color: #0284c7; border: 1px solid rgba(96, 165, 250, 0.3); }
      .status-completed { background: rgba(34, 197, 94, 0.15); color: #16a34a; border: 1px solid rgba(34, 197, 94, 0.3); }
      
      .repair-info { margin-bottom: 1rem; background: #f8fafc; padding: 1rem; border-radius: 8px; }
      .repair-desc { background: #f8fafc; padding: 1rem; border-radius: 8px; margin: 1rem 0; border-left: 3px solid #60a5fa; color: #334155; }
      
      .repair-image-preview { width: 100%; height: 200px; object-fit: cover; border-radius: 8px; margin-top: 1rem; cursor: pointer; transition: transform 0.2s; }
      .repair-image-preview:hover { transform: scale(1.02); }
      
      .repair-table { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); }
      .repair-table table { width: 100%; border-collapse: collapse; }
      .repair-table th, .repair-table td { padding: 1rem 1.25rem; text-align: left; border-bottom: 1px solid #f1f5f9; }
      .repair-table th { background: #f8fafc; color: #475569; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; }
      .repair-table td { color: #334155; font-weight: 500; }
      .repair-table tbody tr { transition: background-color 0.2s; }
      .repair-table tbody tr:hover { background: #f8fafc; }
      
      #table-view .datatable-wrapper { background: #ffffff !important; color: #0f172a !important; border: none !important; }
      #table-view .datatable-wrapper .datatable-input,
      #table-view .datatable-wrapper .datatable-selector { background: #ffffff !important; border: 1px solid #e2e8f0 !important; color: #334155 !important; border-radius: 8px !important; }
      #table-view .datatable-wrapper .datatable-input::placeholder { color: #94a3b8 !important; }
      #table-view .datatable-wrapper table thead { background: #f8fafc !important; }
      #table-view .datatable-wrapper table thead th { color: #475569 !important; border-bottom: 2px solid #e2e8f0 !important; font-weight: 600 !important; font-size: 0.8rem !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; }
      #table-view .datatable-wrapper table tbody tr { background: #ffffff !important; border-bottom: 1px solid #f1f5f9 !important; transition: background-color 0.2s; }
      #table-view .datatable-wrapper table tbody td { color: #334155 !important; font-weight: 500 !important; border-bottom: 1px solid #f1f5f9 !important; }
      #table-view .datatable-wrapper table tbody tr:hover { background: #f8fafc !important; }
      #table-view .datatable-wrapper .datatable-info { color: #64748b !important; }
      #table-view .datatable-wrapper .datatable-pagination-list-item a,
      #table-view .datatable-wrapper .datatable-pagination-list-item button,
      #table-view .datatable-wrapper .datatable-pagination-list-item .datatable-pagination-list-item-link { background: #ffffff !important; border: 1px solid #e2e8f0 !important; color: #64748b !important; border-radius: 6px !important; }
      #table-view .datatable-wrapper .datatable-pagination-list-item a:hover,
      #table-view .datatable-wrapper .datatable-pagination-list-item button:hover,
      #table-view .datatable-wrapper .datatable-pagination-list-item .datatable-pagination-list-item-link:hover { background: #f1f5f9 !important; color: #0ea5e9 !important; }

      .filter-section { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); }
      .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.25rem; }
      .filter-item label { display: block; color: #475569; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; letter-spacing: 0.025em; }
      .filter-item select { width: 100%; padding: 0.75rem 2.5rem 0.75rem 1rem; background: #f8fafc url('data:image/svg+xml;utf8,<svg xmlns=\\'http://www.w3.org/2000/svg\\' fill=\\'none\\' viewBox=\\'0 0 24 24\\' stroke=\\'%2364748b\\'><path stroke-linecap=\\'round\\' stroke-linejoin=\\'round\\' stroke-width=\\'2\\' d=\\'M19 9l-7 7-7-7\\'/></svg>') no-repeat right 0.75rem center/16px 16px; border: 1px solid #e2e8f0; border-radius: 8px; color: #334155; font-size: 0.95rem; font-weight: 500; appearance: none; -webkit-appearance: none; cursor: pointer; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05); transition: border-color 0.2s, box-shadow 0.2s; }
      .filter-item select:focus { outline: none; border-color: #7dd3fc; background-color: #ffffff; box-shadow: 0 0 0 3px rgba(125, 211, 252, 0.3); }
      
      .filter-btn { padding: 0.75rem 1.5rem; background: #60a5fa; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.9rem; }
      .filter-btn:hover { background: #3b82f6; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(96, 165, 250, 0.4); }
      .filter-btn:active { transform: translateY(0); }
      .clear-btn { padding: 0.75rem 1.5rem; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.2s; text-align: center; }
      .clear-btn:hover { background: rgba(239, 68, 68, 0.25); }
      
      .image-modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.9); justify-content: center; align-items: center; }
      .image-modal img { max-width: 90%; max-height: 90%; border-radius: 12px; }
      .image-modal.show { display: flex; }"""

pattern = re.compile(r'      \.reports-container \{ width: 100%.*?\.image-modal\.show \{ display: flex; \}', flags=re.DOTALL)
content = pattern.sub(new_styles, content)

# Also remove the whole block of !important overrides from lines 169 to 276
imp_pattern = re.compile(r'      \.stat-card,\s*\.repair-card,\s*\.repair-table,\s*\.filter-section \{.*?      /\* ===== MOBILE RESPONSIVE ===== \*/', flags=re.DOTALL)
content = imp_pattern.sub('      /* ===== MOBILE RESPONSIVE ===== */', content)

with open('/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Reports/report_repairs.php', 'w', encoding='utf-8') as f:
    f.write(content)

