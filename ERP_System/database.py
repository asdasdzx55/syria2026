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

    
    # 1. جدول المنتجات
    cursor.execute('''CREATE TABLE IF NOT EXISTS products
                      (id INTEGER PRIMARY KEY, barcode TEXT UNIQUE, barcode2 TEXT, barcode3 TEXT, name TEXT, price REAL, cost REAL, stock REAL, all_barcodes TEXT)''')
    
    # 2. جدول المبيعات
    cursor.execute('''CREATE TABLE IF NOT EXISTS sales
                      (id INTEGER PRIMARY KEY, total REAL, date TEXT, customer TEXT, phone TEXT, address TEXT, delivery_person TEXT, status TEXT DEFAULT 'مكتملة', payment_method TEXT DEFAULT 'كاش', payment_fee REAL DEFAULT 0, discount REAL DEFAULT 0, delivery_fee REAL DEFAULT 0)''')
    
    # 3. جدول عناصر المبيعات
    cursor.execute('''CREATE TABLE IF NOT EXISTS sale_items
                      (sale_id INTEGER, product_id INTEGER, qty REAL)''')
    
    # 4. جدول المصروفات
    cursor.execute('''CREATE TABLE IF NOT EXISTS expenses
                      (id INTEGER PRIMARY KEY, category TEXT, amount REAL, note TEXT, date TEXT)''')
                      
    # 5. جدول الموردين
    cursor.execute('''CREATE TABLE IF NOT EXISTS suppliers
                      (id INTEGER PRIMARY KEY, name TEXT, balance REAL DEFAULT 0)''')
                      
    # 6. جدول المشتريات
    cursor.execute('''CREATE TABLE IF NOT EXISTS purchases
                      (id INTEGER PRIMARY KEY, supplier_id INTEGER, total REAL, paid REAL, date TEXT, status TEXT DEFAULT 'مكتملة', discount REAL DEFAULT 0)''')
                      
    # 7. جدول عناصر المشتريات
    cursor.execute('''CREATE TABLE IF NOT EXISTS purchase_items
                      (purchase_id INTEGER, product_id INTEGER, qty REAL, cost REAL)''')
                      
    # 8. جدول الموظفين
    cursor.execute('''CREATE TABLE IF NOT EXISTS employees
                      (id INTEGER PRIMARY KEY, name TEXT, role TEXT DEFAULT 'عامل', salary REAL, hours INTEGER, advances REAL DEFAULT 0, deductions REAL DEFAULT 0)''')
    
    # 9. جدول طرق الدفع
    cursor.execute('''CREATE TABLE IF NOT EXISTS payment_methods 
                      (id INTEGER PRIMARY KEY, name TEXT UNIQUE, fee_percent REAL)''')
    
    # 10. جدول تصنيفات المصروفات
    cursor.execute('''CREATE TABLE IF NOT EXISTS expense_categories 
                      (id INTEGER PRIMARY KEY, name TEXT UNIQUE)''')
    
    # 11. جدول الإعدادات
    cursor.execute('''CREATE TABLE IF NOT EXISTS settings 
                      (key TEXT PRIMARY KEY, value TEXT)''')
    
    # 12. جدول العملاء
    cursor.execute('''CREATE TABLE IF NOT EXISTS customers 
                      (phone TEXT PRIMARY KEY, name TEXT, address TEXT)''')
    
    # 13. جداول الفواتير المعلقة
    cursor.execute('''CREATE TABLE IF NOT EXISTS temp_invoices 
                      (id INTEGER PRIMARY KEY, note TEXT, date TEXT, customer TEXT, phone TEXT, delivery_person TEXT, discount REAL, payment_method TEXT DEFAULT 'كاش (0.0%)', address TEXT, delivery_fee REAL DEFAULT 0)''')
    cursor.execute('''CREATE TABLE IF NOT EXISTS temp_invoice_items 
                      (temp_id INTEGER, product_id INTEGER, name TEXT, price REAL, qty REAL)''')

    # 14. جدول الشركاء / المالكين
    cursor.execute('''CREATE TABLE IF NOT EXISTS partners 
                      (id INTEGER PRIMARY KEY, name TEXT UNIQUE)''')

    # 15. جدول طابور المزامنة الهجينة السحابية (Hybrid Sync Queue)
    cursor.execute('''CREATE TABLE IF NOT EXISTS sync_queue 
                      (id INTEGER PRIMARY KEY, action TEXT, entity_type TEXT, entity_id INTEGER, payload TEXT, status TEXT DEFAULT 'pending', created_at TEXT)''')

    # ==========================================
    # فحص وإضافة الأعمدة الجديدة تلقائياً لقواعد البيانات الحالية (Auto Migration)
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
        ("sales", "payment_method TEXT DEFAULT 'كاش'"),
        ("sales", "payment_fee REAL DEFAULT 0"),
        ("sales", "discount REAL DEFAULT 0"),
        ("sales", "address TEXT"),
        ("sales", "delivery_fee REAL DEFAULT 0"),
        ("sales", "delivery_settled INTEGER DEFAULT 0"),
        ("sales", "delivery_settled_at TEXT"),
        ("sales", "synced INTEGER DEFAULT 0"),
        ("sales", "remote_id TEXT"),
        ("purchases", "status TEXT DEFAULT 'مكتملة'"),
        ("purchases", "discount REAL DEFAULT 0"),
        ("employees", "role TEXT DEFAULT 'عامل'"),
        ("employees", "deductions REAL DEFAULT 0"),
        ("temp_invoices", "payment_method TEXT DEFAULT 'كاش (0.0%)'"),
        ("temp_invoices", "address TEXT"),
        ("temp_invoices", "delivery_fee REAL DEFAULT 0")
    ]

    for table, col_def in migrations:
        col_name = col_def.split()[0]
        try:
            cursor.execute(f"ALTER TABLE {table} ADD COLUMN {col_def}")
        except sqlite3.OperationalError:
            pass # العمود موجود بالفعل

    # الترقيم التلقائي بالباركود المحلي (5 أرقام) للمنتجات القديمة التي لا تملك باركود محلي
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

    # بيانات افتراضية إذا كانت الجداول فارغة
    cursor.execute("SELECT COUNT(*) FROM payment_methods")
    if cursor.fetchone()[0] == 0:
        cursor.executemany("INSERT OR IGNORE INTO payment_methods (name, fee_percent) VALUES (?, ?)", 
                           [("كاش", 0.0), ("فيزا", 2.0), ("انستا باي", 0.0)])

    cursor.execute("SELECT COUNT(*) FROM expense_categories")
    if cursor.fetchone()[0] == 0:
        defaults = [("نثريات",), ("إيجار",), ("فواتير (كهرباء/مياه)",), ("صيانة",), ("رواتب عاملين",), ("سلف عاملين",), ("سداد موردين",), ("مشتريات بضاعة",), ("مسحوبات الإدارة",), ("أخرى",)]
        cursor.executemany("INSERT OR IGNORE INTO expense_categories (name) VALUES (?)", defaults)

    cursor.execute("SELECT COUNT(*) FROM partners")
    if cursor.fetchone()[0] == 0:
        p_defaults = [("المالك / المدير العام",), ("الشريك الأول",), ("الشريك الثاني",)]
        cursor.executemany("INSERT OR IGNORE INTO partners (name) VALUES (?)", p_defaults)

    cursor.execute("INSERT OR IGNORE INTO settings (key, value) VALUES ('admin_password', '1234')")
    cursor.execute("INSERT OR IGNORE INTO settings (key, value) VALUES ('cloud_api_url', 'https://supermarkrt.almagd555.com/api_sync.php')")
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