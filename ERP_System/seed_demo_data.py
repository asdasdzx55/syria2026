import sqlite3
import datetime
import os
from database import setup_database, get_db_path

def seed_database(db_path=None):
    if db_path is None:
        db_path = get_db_path()

    # التأكد أولاً من تهيئة الهيكل والجداول بالكامل
    conn = setup_database()
    cursor = conn.cursor()

    # 0. مسح كافة البيانات القديمة المتروكة لمنع التضارب (Clean Reset)
    tables = [
        'products', 'sales', 'sale_items', 'expenses', 'suppliers', 
        'purchases', 'purchase_items', 'employees', 'temp_invoices', 
        'temp_invoice_items', 'customers', 'payment_methods', 'expense_categories', 'settings'
    ]
    for tbl in tables:
        try:
            cursor.execute(f"DELETE FROM {tbl}")
        except Exception:
            pass
            
    try:
        cursor.execute("DELETE FROM sqlite_sequence")
    except Exception:
        pass

    # 1. إعداد الإعدادات والمنشأة
    settings_data = [
        ('admin_password', '1234'),
        ('store_name', 'سوبر ماركت المنزل السوري'),
        ('store_phone', '01012345678'),
        ('store_address', 'الفرع الرئيسي - سوبر ماركت المنزل السوري للمنتجات الغذائية والمؤونة الشامية'),
        ('default_cashier', 'أحمد الحمصي'),
        ('cloud_api_url', 'http://localhost/web_store/public_html (7)/api_sync.php'),
        ('cloud_api_key', 'syrian_home_pos_secret_token_2026'),
        ('cloud_auto_sync', '1'),
        ('cloud_sync_interval', '30')
    ]
    cursor.executemany("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)", settings_data)

    # 2. طرق الدفع
    pm_data = [
        ("كاش", 0.0),
        ("فيزا", 2.0),
        ("انستا باي", 0.0),
        ("شام كاش", 0.0)
    ]
    cursor.executemany("INSERT OR IGNORE INTO payment_methods (name, fee_percent) VALUES (?, ?)", pm_data)

    # 3. تصنيفات المصروفات
    cat_data = [
        ("نثريات",), ("إيجار",), ("فواتير (كهرباء/مياه)",), ("صيانة",), 
        ("رواتب عاملين",), ("سلف عاملين",), ("سداد موردين",), ("مشتريات بضاعة",), 
        ("مسحوبات الإدارة",), ("أخرى",)
    ]
    cursor.executemany("INSERT OR IGNORE INTO expense_categories (name) VALUES (?)", cat_data)

    # 4. الموظفين والعاملين
    emp_data = [
        (1, "سامر الحلبي", "دليفري", 5000.0, 8, 200.0, 50.0),
        (2, "أحمد الحمصي", "كاشير", 6000.0, 8, 0.0, 0.0),
        (3, "يوسف الشامي", "عامل", 4500.0, 10, 150.0, 0.0),
        (4, "بلال المرادي", "دليفري", 5000.0, 8, 0.0, 0.0),
        (5, "أبو النور السوري", "مدير الفرع", 8500.0, 8, 0.0, 0.0)
    ]
    cursor.executemany("INSERT OR REPLACE INTO employees (id, name, role, salary, hours, advances, deductions) VALUES (?, ?, ?, ?, ?, ?, ?)", emp_data)

    # 5. الموردين
    sup_data = [
        (1, "شركة الخيرات الشامية للمؤونة", 4500.0),
        (2, "مؤسسة زيتون إدلب للزيوت والمخللات", 3200.0),
        (3, "معامل دمشق للأجبان والألبان البلدية", 6800.0),
        (4, "مطاحن ومحامص حلب للبهارات والبن", 1800.0),
        (5, "شركة الفرات للمواد الغذائية والتموين", 2900.0)
    ]
    cursor.executemany("INSERT OR REPLACE INTO suppliers (id, name, balance) VALUES (?, ?, ?)", sup_data)

    # 6. المنتجات الشامية ومواد السوبرماركت (أصناف متنوعة: أوزان، باركود عادي، باركود متعدد، باركود ميزان)
    prod_data = [
        (1, "6221000123456", "6221000123457", None, "جبنة حلوم بلدية سورية فاخرة 500جم", 95.0, 75.0, 80.0, "6221000123456,6221000123457"),
        (2, "6222000234567", None, None, "جبنة شلل سورية حبة بركة 400جم", 110.0, 88.0, 65.0, "6222000234567"),
        (3, "6223000345678", None, None, "مكدوس سوري بلدي بالجوز وزيت الزيتون 1 كجم", 160.0, 125.0, 50.0, "6223000345678"),
        (4, "6224000456789", None, None, "زيت زيتون بكر ممتاز إدلبي عصرة أولى 1 لتر", 260.0, 210.0, 90.0, "6224000456789"),
        (5, "6225000567890", None, None, "زعتر حلبي اكسترا بالمكسرات والسمسم 500جم", 75.0, 55.0, 110.0, "6225000567890"),
        (6, "6226000678901", None, None, "دبس رمان شامي طبيعي مركز 500 مل", 85.0, 65.0, 70.0, "6226000678901"),
        (7, "00101", None, None, "لبنة بلدية شامية مكبوسة بالزيت (بالوزن/كجم)", 220.0, 175.0, 30.0, "00101"),
        (8, "00102", None, None, "زيتون عطون حلبي مخلل فاخر (بالوزن/كجم)", 130.0, 95.0, 45.0, "00102"),
        (9, "00103", None, None, "جبنة قشقوان سورية مبشورة (بالوزن/كجم)", 280.0, 225.0, 25.0, "00103"),
        (10, "00104", None, None, "راحة دمشقية بالفستق الحلبي والمستكة (بالوزن/كجم)", 320.0, 250.0, 20.0, "00104"),
        (11, "6227000789012", None, None, "بن شامي فاخر بالهيل والمسك 250جم", 120.0, 95.0, 60.0, "6227000789012"),
        (12, "6228000890123", None, None, "حلاوة طحينية شامية بالفستق الحلبي 500جم", 90.0, 70.0, 75.0, "6228000890123"),
        (13, "6229000901234", None, None, "ملوخية شامية يابسة ورقة كاملة 400جم", 65.0, 48.0, 85.0, "6229000901234"),
        (14, "6230000012345", None, None, "فريك حوراني بلدي منقى 1 كجم", 80.0, 60.0, 100.0, "6230000012345"),
        (15, "6231000112233", None, None, "أرز بسمتي هندي سيلا 1 كجم", 75.0, 58.0, 120.0, "6231000112233"),
        (16, "6232000223344", None, None, "سمن بقري حماوي بلدي نقي 800جم", 290.0, 240.0, 40.0, "6232000223344"),
        (17, "6233000334455", None, None, "بهارات شاورما سورية مشكلة 200جم", 45.0, 32.0, 90.0, "6233000334455"),
        (18, "6234000445566", None, None, "قمر الدين غوطاني دمشقي فاخر 400جم", 55.0, 40.0, 130.0, "6234000445566")
    ]
    cursor.executemany("INSERT OR REPLACE INTO products (id, barcode, barcode2, barcode3, name, price, cost, stock, all_barcodes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", prod_data)

    # ترقيم الأكواد المحلية 5 أرقام تلقائياً
    for p_id in range(1, len(prod_data) + 1):
        loc_code = str(10000 + p_id)
        cursor.execute("UPDATE products SET local_code = ? WHERE id = ?", (loc_code, p_id))

    # 7. العملاء
    cust_data = [
        ("01099887766", "أحمد فتحي", "شارع المعادي - عمارة 12 - شقة 4"),
        ("01122334455", "عمر السوري", "مدينة نصر - الحي السابع - عمارة 15"),
        ("01234567890", "سارة الشامي", "التجمع الخامس - النرجس فيلا 9"),
        ("01555443322", "محمود توفيق", "الدقي - شارع مصدق")
    ]
    cursor.executemany("INSERT OR REPLACE INTO customers (phone, name, address) VALUES (?, ?, ?)", cust_data)

    # 8. فواتير مبيعات سابقة وعناصرها
    sales_data = [
        (1, 355.0, "2026-08-16 11:15:00", "عمر السوري", "01122334455", "مدينة نصر - الحي السابع - عمارة 15", "سامر الحلبي", "مكتملة", "كاش", 0.0, 0.0, 15.0),
        (2, 480.0, "2026-08-16 15:30:00", "أحمد فتحي", "01099887766", "شارع المعادي - عمارة 12 - شقة 4", "بدون توصيل (تيك أواي)", "مكتملة", "فيزا", 9.6, 20.0, 0.0),
        (3, 275.0, "2026-08-16 19:45:00", "سارة الشامي", "01234567890", "التجمع الخامس - النرجس فيلا 9", "بلال المرادي", "مكتملة", "انستا باي", 0.0, 0.0, 25.0),
        (4, 580.0, "2026-08-17 01:20:00", "عميل نقدي", "", "", "بدون توصيل (تيك أواي)", "مكتملة", "كاش", 0.0, 0.0, 0.0)
    ]
    cursor.executemany("INSERT OR REPLACE INTO sales (id, total, date, customer, phone, address, delivery_person, status, payment_method, payment_fee, discount, delivery_fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", sales_data)

    sale_items_data = [
        (1, 1, 1.0),
        (1, 4, 1.0),
        (2, 3, 2.0),
        (2, 5, 2.0),
        (2, 11, 1.0),
        (3, 2, 1.0),
        (3, 6, 1.0),
        (3, 12, 1.0),
        (4, 7, 1.5),
        (4, 10, 0.8)
    ]
    cursor.executemany("INSERT INTO sale_items (sale_id, product_id, qty) VALUES (?, ?, ?)", sale_items_data)

    # 9. المصروفات العامة
    expenses_data = [
        (1, "إيجار", 4500.0, "إيجار المحل شهر أغسطس", "2026-08-01 10:00"),
        (2, "فواتير (كهرباء/مياه)", 1200.0, "فاتورة كهرباء ثلاجات التبريد والعرض", "2026-08-03 12:00"),
        (3, "نثريات", 180.0, "أدوات تغليف وأكياس مطبوعة", "2026-08-05 15:30"),
        (4, "سلف عاملين", 250.0, "سلفة للموظف سامر الحلبي", "2026-08-06 11:00"),
        (5, "سداد موردين", 2500.0, "سداد دفعة لمؤسسة زيتون إدلب", "2026-08-06 17:00")
    ]
    cursor.executemany("INSERT OR REPLACE INTO expenses (id, category, amount, note, date) VALUES (?, ?, ?, ?, ?)", expenses_data)

    # 10. فواتير المشتريات
    purchases_data = [
        (1, 1, 8000.0, 6000.0, "2026-08-02 11:00:00", "مكتملة", 0.0),
        (2, 3, 5500.0, 5500.0, "2026-08-04 14:00:00", "مكتملة", 150.0)
    ]
    cursor.executemany("INSERT OR REPLACE INTO purchases (id, supplier_id, total, paid, date, status, discount) VALUES (?, ?, ?, ?, ?, ?, ?)", purchases_data)

    purchase_items_data = [
        (1, 1, 50.0, 75.0),
        (1, 4, 30.0, 210.0),
        (2, 2, 40.0, 88.0),
        (2, 3, 25.0, 125.0)
    ]
    cursor.executemany("INSERT INTO purchase_items (purchase_id, product_id, qty, cost) VALUES (?, ?, ?, ?)", purchase_items_data)

    # 11. فاتورة معلقة تجريبية
    temp_inv_data = [
        (1, "طلب عميل للتجهيز - سوبر ماركت المنزل السوري", "2026-08-17 03:00:00", "عمر السوري", "01122334455", "سامر الحلبي", 0.0, "كاش (0.0%)", "مدينة نصر - الحي السابع - عمارة 15", 15.0)
    ]
    cursor.executemany("INSERT OR REPLACE INTO temp_invoices (id, note, date, customer, phone, delivery_person, discount, payment_method, address, delivery_fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", temp_inv_data)

    temp_items_data = [
        (1, 1, "جبنة حلوم بلدية سورية فاخرة 500جم", 95.0, 2.0),
        (1, 3, "مكدوس سوري بلدي بالجوز وزيت الزيتون 1 كجم", 160.0, 1.0)
    ]
    cursor.executemany("INSERT INTO temp_invoice_items (temp_id, product_id, name, price, qty) VALUES (?, ?, ?, ?, ?)", temp_items_data)

    conn.commit()
    conn.close()
    print("Database seeded with Syrian Supermarket data successfully!")

if __name__ == "__main__":
    seed_database()
