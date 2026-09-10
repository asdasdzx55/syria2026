import sqlite3
import os
import sys

def get_base_dir():
    if getattr(sys, 'frozen', False):
        return os.path.dirname(sys.executable)
    return os.path.dirname(os.path.abspath(__file__))

def get_db_path():
    base_dir = get_base_dir()
    return os.path.join(base_dir, 'my_business_v3.db')

def setup_database():
    db_path = get_db_path()
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()

    
    # 1. ط¬ط¯ظˆظ„ ط§ظ„ظ…ظ†طھط¬ط§طھ
    cursor.execute('''CREATE TABLE IF NOT EXISTS products
                      (id INTEGER PRIMARY KEY, barcode TEXT UNIQUE, barcode2 TEXT, barcode3 TEXT, name TEXT, price REAL, cost REAL, stock REAL, all_barcodes TEXT)''')
    
    # 2. ط¬ط¯ظˆظ„ ط§ظ„ظ…ط¨ظٹط¹ط§طھ
    cursor.execute('''CREATE TABLE IF NOT EXISTS sales
                      (id INTEGER PRIMARY KEY, total REAL, date TEXT, customer TEXT, phone TEXT, address TEXT, delivery_person TEXT, status TEXT DEFAULT 'ظ…ظƒطھظ…ظ„ط©', payment_method TEXT DEFAULT 'ظƒط§ط´', payment_fee REAL DEFAULT 0, discount REAL DEFAULT 0, delivery_fee REAL DEFAULT 0)''')
    
    # 3. ط¬ط¯ظˆظ„ ط¹ظ†ط§طµط± ط§ظ„ظ…ط¨ظٹط¹ط§طھ
    cursor.execute('''CREATE TABLE IF NOT EXISTS sale_items
                      (sale_id INTEGER, product_id INTEGER, qty REAL)''')
    
    # 4. ط¬ط¯ظˆظ„ ط§ظ„ظ…طµط±ظˆظپط§طھ
    cursor.execute('''CREATE TABLE IF NOT EXISTS expenses
                      (id INTEGER PRIMARY KEY, category TEXT, amount REAL, note TEXT, date TEXT)''')
                      
    # 5. ط¬ط¯ظˆظ„ ط§ظ„ظ…ظˆط±ط¯ظٹظ†
    cursor.execute('''CREATE TABLE IF NOT EXISTS suppliers
                      (id INTEGER PRIMARY KEY, name TEXT, balance REAL DEFAULT 0)''')
                      
    # 6. ط¬ط¯ظˆظ„ ط§ظ„ظ…ط´طھط±ظٹط§طھ
    cursor.execute('''CREATE TABLE IF NOT EXISTS purchases
                      (id INTEGER PRIMARY KEY, supplier_id INTEGER, total REAL, paid REAL, date TEXT, status TEXT DEFAULT 'ظ…ظƒطھظ…ظ„ط©', discount REAL DEFAULT 0)''')
                      
    # 7. ط¬ط¯ظˆظ„ ط¹ظ†ط§طµط± ط§ظ„ظ…ط´طھط±ظٹط§طھ
    cursor.execute('''CREATE TABLE IF NOT EXISTS purchase_items
                      (purchase_id INTEGER, product_id INTEGER, qty REAL, cost REAL)''')
                      
    # 8. ط¬ط¯ظˆظ„ ط§ظ„ظ…ظˆط¸ظپظٹظ†
    cursor.execute('''CREATE TABLE IF NOT EXISTS employees
                      (id INTEGER PRIMARY KEY, name TEXT, role TEXT DEFAULT 'ط¹ط§ظ…ظ„', salary REAL, hours INTEGER, advances REAL DEFAULT 0, deductions REAL DEFAULT 0)''')
    
    # 9. ط¬ط¯ظˆظ„ ط·ط±ظ‚ ط§ظ„ط¯ظپط¹
    cursor.execute('''CREATE TABLE IF NOT EXISTS payment_methods 
                      (id INTEGER PRIMARY KEY, name TEXT UNIQUE, fee_percent REAL)''')
    
    # 10. ط¬ط¯ظˆظ„ طھطµظ†ظٹظپط§طھ ط§ظ„ظ…طµط±ظˆظپط§طھ
    cursor.execute('''CREATE TABLE IF NOT EXISTS expense_categories 
                      (id INTEGER PRIMARY KEY, name TEXT UNIQUE)''')
    
    # 11. ط¬ط¯ظˆظ„ ط§ظ„ط¥ط¹ط¯ط§ط¯ط§طھ
    cursor.execute('''CREATE TABLE IF NOT EXISTS settings 
                      (key TEXT PRIMARY KEY, value TEXT)''')
    
    # 12. ط¬ط¯ظˆظ„ ط§ظ„ط¹ظ…ظ„ط§ط،
    cursor.execute('''CREATE TABLE IF NOT EXISTS customers 
                      (phone TEXT PRIMARY KEY, name TEXT, address TEXT)''')
    
    # 13. ط¬ط¯ط§ظˆظ„ ط§ظ„ظپظˆط§طھظٹط± ط§ظ„ظ…ط¹ظ„ظ‚ط©
    cursor.execute('''CREATE TABLE IF NOT EXISTS temp_invoices 
                      (id INTEGER PRIMARY KEY, note TEXT, date TEXT, customer TEXT, phone TEXT, delivery_person TEXT, discount REAL, payment_method TEXT DEFAULT 'ظƒط§ط´ (0.0%)', address TEXT, delivery_fee REAL DEFAULT 0)''')
    cursor.execute('''CREATE TABLE IF NOT EXISTS temp_invoice_items 
                      (temp_id INTEGER, product_id INTEGER, name TEXT, price REAL, qty REAL)''')

    # 14. ط¬ط¯ظˆظ„ ط§ظ„ط´ط±ظƒط§ط، / ط§ظ„ظ…ط§ظ„ظƒظٹظ†
    cursor.execute('''CREATE TABLE IF NOT EXISTS partners 
                      (id INTEGER PRIMARY KEY, name TEXT UNIQUE)''')

    # 15. ط¬ط¯ظˆظ„ ط·ط§ط¨ظˆط± ط§ظ„ظ…ط²ط§ظ…ظ†ط© ط§ظ„ظ‡ط¬ظٹظ†ط© ط§ظ„ط³ط­ط§ط¨ظٹط© (Hybrid Sync Queue)
    cursor.execute('''CREATE TABLE IF NOT EXISTS sync_queue 
                      (id INTEGER PRIMARY KEY, action TEXT, entity_type TEXT, entity_id INTEGER, payload TEXT, status TEXT DEFAULT 'pending', created_at TEXT)''')

    # ==========================================
    # ظپط­طµ ظˆط¥ط¶ط§ظپط© ط§ظ„ط£ط¹ظ…ط¯ط© ط§ظ„ط¬ط¯ظٹط¯ط© طھظ„ظ‚ط§ط¦ظٹط§ظ‹ ظ„ظ‚ظˆط§ط¹ط¯ ط§ظ„ط¨ظٹط§ظ†ط§طھ ط§ظ„ط­ط§ظ„ظٹط© (Auto Migration)
    # ==========================================
    migrations = [
        ("products", "barcode2 TEXT"),
        ("products", "barcode3 TEXT"),
        ("products", "all_barcodes TEXT"),
        ("products", "local_code TEXT"),
        ("products", "synced INTEGER DEFAULT 0"),
        ("products", "remote_id TEXT"),
        ("expenses", "partner_name TEXT"),
        ("sales", "delivery_person TEXT"),
        ("sales", "payment_method TEXT DEFAULT 'ظƒط§ط´'"),
        ("sales", "payment_fee REAL DEFAULT 0"),
        ("sales", "discount REAL DEFAULT 0"),
        ("sales", "address TEXT"),
        ("sales", "delivery_fee REAL DEFAULT 0"),
        ("sales", "delivery_settled INTEGER DEFAULT 0"),
        ("sales", "delivery_settled_at TEXT"),
        ("sales", "synced INTEGER DEFAULT 0"),
        ("sales", "remote_id TEXT"),
        ("purchases", "status TEXT DEFAULT 'ظ…ظƒطھظ…ظ„ط©'"),
        ("purchases", "discount REAL DEFAULT 0"),
        ("employees", "role TEXT DEFAULT 'ط¹ط§ظ…ظ„'"),
        ("employees", "deductions REAL DEFAULT 0"),
        ("temp_invoices", "payment_method TEXT DEFAULT 'ظƒط§ط´ (0.0%)'"),
        ("temp_invoices", "address TEXT"),
        ("temp_invoices", "delivery_fee REAL DEFAULT 0"),
        ("products", "category TEXT DEFAULT 'ط¹ط§ظ…'"),
        ("products", "main_category TEXT DEFAULT 'ط¹ط§ظ…'"),
        ("products", "sub_category TEXT DEFAULT 'ط¹ط§ظ…'"),
        ("suppliers", "phone TEXT"),
        ("suppliers", "synced INTEGER DEFAULT 0"),
        ("suppliers", "remote_id TEXT"),
        ("purchases", "invoice_number TEXT"),
        ("purchases", "payment_method TEXT DEFAULT 'ظ†ظ‚ط¯ظٹ'"),
        ("purchases", "source TEXT DEFAULT 'desktop_pos'"),
        ("purchases", "synced INTEGER DEFAULT 0"),
        ("purchases", "remote_id TEXT"),
        ("purchase_items", "barcode TEXT"),
        ("purchase_items", "name TEXT"),
        ("purchase_items", "unit TEXT DEFAULT 'ظ‚ط·ط¹ط©'"),
        ("purchase_items", "selling_price REAL DEFAULT 0"),
        ("products", "is_weight_based INTEGER DEFAULT 0"),
        ("products", "unit_type TEXT DEFAULT 'ظ‚ط·ط¹ط©'"),
        ("products", "weight_unit TEXT DEFAULT 'ظƒط¬ظ…'"),
        ("products", "has_pack INTEGER DEFAULT 0"),
        ("products", "pack_name TEXT DEFAULT ''"),
        ("products", "pack_barcode TEXT DEFAULT ''"),
        ("products", "pack_price REAL DEFAULT 0.0"),
        ("products", "pack_qty REAL DEFAULT 1.0"),
        ("employees", "phone TEXT"),
        ("employees", "synced INTEGER DEFAULT 0"),
        ("employees", "remote_id TEXT"),
        ("sale_items", "unit_price REAL DEFAULT 0"),
        ("sale_items", "item_name TEXT DEFAULT ''"),
        ("sale_items", "pack_multiplier REAL DEFAULT 1.0"),
        ("sale_items", "deduct_qty REAL DEFAULT 0"),
        ("sale_items", "is_pack INTEGER DEFAULT 0"),
        ("temp_invoice_items", "pack_multiplier REAL DEFAULT 1.0"),
        ("temp_invoice_items", "is_pack INTEGER DEFAULT 0")
    ]

    for table, col_def in migrations:
        col_name = col_def.split()[0]
        try:
            cursor.execute(f"ALTER TABLE {table} ADD COLUMN {col_def}")
        except sqlite3.OperationalError:
            pass # ط§ظ„ط¹ظ…ظˆط¯ ظ…ظˆط¬ظˆط¯ ط¨ط§ظ„ظپط¹ظ„

    # 16. ط¬ط¯ط§ظˆظ„ ط§ظ„طھطµظ†ظٹظپط§طھ ط§ظ„ط£ط³ط§ط³ظٹط© ظˆط§ظ„ظپط±ط¹ظٹط© (Categories & Sub-Categories)
    cursor.execute('''CREATE TABLE IF NOT EXISTS categories 
                      (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE NOT NULL)''')
    cursor.execute('''CREATE TABLE IF NOT EXISTS sub_categories 
                      (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, main_category_id INTEGER, 
                       UNIQUE(name, main_category_id),
                       FOREIGN KEY(main_category_id) REFERENCES categories(id) ON DELETE CASCADE)''')

    # ط§ظ„طھط±ظ‚ظٹظ… ط§ظ„طھظ„ظ‚ط§ط¦ظٹ ط¨ط§ظ„ط¨ط§ط±ظƒظˆط¯ ط§ظ„ظ…ط­ظ„ظٹ (5 ط£ط±ظ‚ط§ظ…) ظ„ظ„ظ…ظ†طھط¬ط§طھ ط§ظ„ظ‚ط¯ظٹظ…ط© ط§ظ„طھظٹ ظ„ط§ طھظ…ظ„ظƒ ط¨ط§ط±ظƒظˆط¯ ظ…ط­ظ„ظٹ
    cursor.execute("SELECT id, barcode, all_barcodes FROM products WHERE local_code IS NULL OR local_code = ''")
    missing_prods = cursor.fetchall()
    if missing_prods:
        cursor.execute("SELECT local_code FROM products WHERE local_code IS NOT NULL AND local_code != ''")
        existing_codes = [r[0] for r in cursor.fetchall() if r[0] and r[0].isdigit() and len(r[0]) == 5]
        current_num = max([int(c) for c in existing_codes], default=10000)
        
        for p_id, main_bc, all_bcs in missing_prods:
            current_num += 1
            new_loc_code = str(current_num)
            
            bcs_list = [b.strip() for b in (all_bcs or "").split(",") if b.strip()]
            if new_loc_code not in bcs_list:
                bcs_list.insert(0, new_loc_code)
            if main_bc and main_bc not in bcs_list:
                bcs_list.append(main_bc)
            
            new_all_bcs = ",".join(bcs_list)
            cursor.execute("UPDATE products SET local_code = ?, all_barcodes = ? WHERE id = ?", (new_loc_code, new_all_bcs, p_id))
        conn.commit()

    # ط¨ظٹط§ظ†ط§طھ ط§ظپطھط±ط§ط¶ظٹط© ط¥ط°ط§ ظƒط§ظ†طھ ط§ظ„ط¬ط¯ط§ظˆظ„ ظپط§ط±ط؛ط©
    cursor.execute("SELECT COUNT(*) FROM payment_methods")
    if cursor.fetchone()[0] == 0:
        cursor.executemany("INSERT OR IGNORE INTO payment_methods (name, fee_percent) VALUES (?, ?)", 
                           [("ظƒط§ط´", 0.0), ("ظپظٹط²ط§", 2.0), ("ط§ظ†ط³طھط§ ط¨ط§ظٹ", 0.0)])

    cursor.execute("SELECT COUNT(*) FROM expense_categories")
    if cursor.fetchone()[0] == 0:
        defaults = [("ظ†ط«ط±ظٹط§طھ",), ("ط¥ظٹط¬ط§ط±",), ("ظپظˆط§طھظٹط± (ظƒظ‡ط±ط¨ط§ط،/ظ…ظٹط§ظ‡)",), ("طµظٹط§ظ†ط©",), ("ط±ظˆط§طھط¨ ط¹ط§ظ…ظ„ظٹظ†",), ("ط³ظ„ظپ ط¹ط§ظ…ظ„ظٹظ†",), ("ط³ط¯ط§ط¯ ظ…ظˆط±ط¯ظٹظ†",), ("ظ…ط´طھط±ظٹط§طھ ط¨ط¶ط§ط¹ط©",), ("ظ…ط³ط­ظˆط¨ط§طھ ط§ظ„ط¥ط¯ط§ط±ط©",), ("ط£ط®ط±ظ‰",)]
        cursor.executemany("INSERT OR IGNORE INTO expense_categories (name) VALUES (?)", defaults)

    cursor.execute("SELECT COUNT(*) FROM partners")
    if cursor.fetchone()[0] == 0:
        p_defaults = [("ط§ظ„ظ…ط§ظ„ظƒ / ط§ظ„ظ…ط¯ظٹط± ط§ظ„ط¹ط§ظ…",), ("ط§ظ„ط´ط±ظٹظƒ ط§ظ„ط£ظˆظ„",), ("ط§ظ„ط´ط±ظٹظƒ ط§ظ„ط«ط§ظ†ظٹ",)]
        cursor.executemany("INSERT OR IGNORE INTO partners (name) VALUES (?)", p_defaults)

    # طھظ‡ظٹط¦ط© ط§ظ„طھطµظ†ظٹظپط§طھ ط§ظ„ط£ط³ط§ط³ظٹط© ظˆط§ظ„ظپط±ط¹ظٹط© ط§ظ„ط§ظپطھط±ط§ط¶ظٹط©
    cursor.execute("SELECT COUNT(*) FROM categories")
    if cursor.fetchone()[0] == 0:
        default_categories = {
            "ط­ظ„ظˆظٹط§طھ ظˆط´ظˆظƒظˆظ„ط§طھط©": ["ظ†ظˆظƒط§ ظˆظ…ظƒط³ط±ط§طھ", "ط¨ظ‚ظ„ط§ظˆط© ظˆظ…ط¹ظ…ظˆظ„", "ظ…ظ„ط¨ظ† ظˆط±ط§ط­ط©", "ط´ظˆظƒظˆظ„ط§طھط© ظˆطھظˆظپظٹ", "ط­ظ„ظˆظٹط§طھ ط´ط±ظ‚ظٹط©"],
            "ط¹ط·ط§ط±ط© ظˆط¨ظ‡ط§ط±ط§طھ": ["ط¨ظ‡ط§ط±ط§طھ ظˆطھظˆط§ط¨ظ„", "ط£ط¹ط´ط§ط¨ ط·ط¨ظٹط¹ظٹط©", "ط­ط¨ظˆط¨ ظˆط¨ظ‚ظˆظ„ظٹط§طھ", "ظ…ظƒط³ط±ط§طھ ظˆطھط³ط§ظ„ظٹ"],
            "ط£ظ„ط¨ط§ظ† ظˆط£ط¬ط¨ط§ظ†": ["ط£ط¬ط¨ط§ظ† ط³ظˆط±ظٹط© ظˆظ…ط­ظ„ظٹط©", "ط£ظ„ط¨ط§ظ† ظˆط²ط¨ط§ط¯ظٹ", "ظ‚ط´ط·ط© ظˆط²ط¨ط¯ط©"],
            "ط²ظٹظˆطھ ظˆط³ظ…ظ†": ["ط²ظٹطھ ط²ظٹطھظˆظ†", "ط³ظ…ظ† ط¨ظ„ط¯ظٹ ظˆط³ظˆط±ظٹ", "ط²ظٹظˆطھ ظ†ط¨ط§طھظٹط©"],
            "ظ…ط¹ظ„ط¨ط§طھ ظˆظ…ظˆط§ط¯ ط؛ط°ط§ط¦ظٹط©": ["ط¨ظ‚ظˆظ„ظٹط§طھ ظˆظ…ط¹ظ„ط¨ط§طھ", "طµظ„طµط§طھ ظˆط¯ط¨ط³", "ظ…ط±ط¨ظٹط§طھ ظˆط¹ط³ظ„", "ط£ط±ط² ظˆظ…ظƒط±ظˆظ†ط©"],
            "ظ…ط´ط±ظˆط¨ط§طھ ظˆط¹طµط§ط¦ط±": ["ط´ط§ظٹ ظˆظ‚ظ‡ظˆط© ظˆظ…طھظ‡", "ظ…ط´ط±ظˆط¨ط§طھ ط¨ط§ط±ط¯ط©", "ظ…ظٹط§ظ‡ ط؛ط§ط²ظٹط© ظˆظ…ط¹ط¯ظ†ظٹط©"],
            "ظ…ظ†ط¸ظپط§طھ ظˆط¹ظ†ط§ظٹط© ظ…ظ†ط²ظ„ظٹط©": ["ظ…ظ†ط¸ظپط§طھ ط£ط·ط¨ط§ظ‚ ظˆظ…ظ„ط§ط¨ط³", "ظ…ط¹ظ‚ظ…ط§طھ ظˆظ…ط·ظ‡ط±ط§طھ", "ط¹ظ†ط§ظٹط© ط´ط®طµظٹط©"],
            "ط¹ط§ظ…": ["ظ…ظ†طھط¬ط§طھ ط¹ط§ظ…ط©"]
        }
        for cat_name, sub_list in default_categories.items():
            cursor.execute("INSERT OR IGNORE INTO categories (name) VALUES (?)", (cat_name,))
            cursor.execute("SELECT id FROM categories WHERE name=?", (cat_name,))
            cat_id = cursor.fetchone()[0]
            for sub_name in sub_list:
                cursor.execute("INSERT OR IGNORE INTO sub_categories (name, main_category_id) VALUES (?, ?)", (sub_name, cat_id))
        conn.commit()

    cursor.execute("INSERT OR IGNORE INTO settings (key, value) VALUES ('admin_password', '1234')")
    cursor.execute("INSERT OR IGNORE INTO settings (key, value) VALUES ('cloud_api_url', 'https://syrianhouse.almagd555.com/api_sync.php')")
    cursor.execute("INSERT OR IGNORE INTO settings (key, value) VALUES ('cloud_api_key', 'syrian_home_pos_secret_token_2026')")

    conn.commit()
    return conn

def generate_next_local_code(cursor):
    cursor.execute("SELECT local_code FROM products WHERE local_code IS NOT NULL AND local_code != ''")
    existing_codes = [r[0] for r in cursor.fetchall() if r[0] and r[0].isdigit() and len(r[0]) == 5]
    current_num = max([int(c) for c in existing_codes], default=10000)
    
    while True:
        current_num += 1
        candidate = str(current_num)
        cursor.execute(
            "SELECT COUNT(*) FROM products WHERE barcode=? OR local_code=? OR barcode2=? OR barcode3=? OR ',' || COALESCE(all_barcodes, '') || ',' LIKE ?",
            (candidate, candidate, candidate, candidate, f'%,{candidate},%')
        )
        if cursor.fetchone()[0] == 0:
            return candidate
