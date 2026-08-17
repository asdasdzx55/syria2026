import sqlite3
import datetime
import json
import threading
import time
import urllib.request
import urllib.parse
import urllib.error

class HybridSyncManager:
    def __init__(self, db_conn, app=None):
        self.db = db_conn
        self.cursor = self.db.cursor()
        self.app = app
        self.is_running = False
        self.sync_thread = None
        self._lock = threading.Lock()

    def get_cloud_settings(self):
        self.cursor.execute("SELECT key, value FROM settings WHERE key LIKE 'cloud_%' OR key LIKE 'hostinger_%' OR key = 'api_secret_key'")
        st = dict(self.cursor.fetchall())
        if not st.get('cloud_api_key'):
            st['cloud_api_key'] = st.get('api_secret_key', 'syrian_home_pos_secret_token_2026')
        return st

    def save_cloud_settings(self, api_url, api_key, auto_sync="1", sync_interval="30"):
        # تنظيف وضبط الرابط
        clean_url = api_url.strip().rstrip('/')
        if not clean_url.endswith('.php') and not clean_url.endswith('/api_sync.php'):
            if '/web_store' in clean_url or 'public_html' in clean_url:
                clean_url = clean_url.rstrip('/') + '/api_sync.php'
        
        settings = {
            'cloud_api_url': clean_url,
            'cloud_api_key': api_key.strip() if api_key else 'syrian_home_pos_secret_token_2026',
            'cloud_auto_sync': str(auto_sync),
            'cloud_sync_interval': str(sync_interval)
        }
        for k, v in settings.items():
            self.cursor.execute("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)", (k, v))
        self.db.commit()

    def add_to_queue(self, action, entity_type, entity_id, payload_dict):
        try:
            date_now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            payload_json = json.dumps(payload_dict, ensure_ascii=False)
            self.cursor.execute("INSERT INTO sync_queue (action, entity_type, entity_id, payload, status, created_at) VALUES (?, ?, ?, ?, 'pending', ?)",
                                (action, entity_type, entity_id, payload_json, date_now))
            self.db.commit()
        except Exception as e:
            print(f"Error adding to sync queue: {e}")

    def _normalize_url(self, raw_url, action=None):
        url = (raw_url or '').strip().rstrip('/')
        if not url: return ''
        
        if not url.endswith('api_sync.php'):
            if url.endswith('.php'):
                pass
            else:
                url = f"{url}/api_sync.php"
                
        if action:
            sep = '&' if '?' in url else '?'
            url = f"{url}{sep}action={urllib.parse.quote(action)}"
        return url

    def _make_request(self, raw_url, action, payload=None, api_key=None, method='GET', timeout=8):
        target_url = self._normalize_url(raw_url, action)
        if not target_url:
            return False, "رابط السيرفر / الويب غير محدد"

        headers = {
            'User-Agent': 'SyrianHome-ERP-SyncHub/2.0',
            'Authorization': f"Bearer {api_key or 'syrian_home_pos_secret_token_2026'}",
            'X-API-KEY': api_key or 'syrian_home_pos_secret_token_2026',
            'Content-Type': 'application/json; charset=utf-8'
        }

        data_bytes = None
        if payload is not None and method == 'POST':
            data_bytes = json.dumps(payload, ensure_ascii=False).encode('utf-8')

        req = urllib.request.Request(target_url, data=data_bytes, headers=headers, method=method)
        try:
            with urllib.request.urlopen(req, timeout=timeout) as resp:
                status_code = resp.status
                resp_text = resp.read().decode('utf-8', errors='ignore')
                try:
                    res_json = json.loads(resp_text)
                    return True, res_json
                except json.JSONDecodeError:
                    return (status_code in (200, 201)), resp_text
        except urllib.error.HTTPError as e:
            err_msg = e.read().decode('utf-8', errors='ignore')
            return False, f"خطأ من الخادم ({e.code}): {err_msg or e.reason}"
        except urllib.error.URLError as e:
            return False, f"تعذر الوصول للسيرفر: {e.reason}"
        except Exception as e:
            return False, f"خطأ في الاتصال: {e}"

    def test_cloud_connection(self, api_url=None, api_key=None):
        settings = self.get_cloud_settings()
        url = api_url or settings.get('cloud_api_url', '')
        key = api_key or settings.get('cloud_api_key', 'syrian_home_pos_secret_token_2026')

        if not url:
            return False, "رابط السيرفر / المتجر الإلكتروني غير محدد!"

        ok, res = self._make_request(url, action='ping', api_key=key, method='GET', timeout=5)
        if ok and isinstance(res, dict) and res.get('success'):
            store = res.get('store_name', 'سوبر ماركت المنزل السوري')
            return True, f"✅ متصل بنجاح بمركز بيانات ({store}) ⚡"
        elif ok:
            return True, "✅ تم الاتصال بنجاح بالخادم المركزي!"
        else:
            return False, f"فشل الاتصال: {res}"

    def trigger_instant_sync(self):
        t = threading.Thread(target=self.run_sync_cycle, daemon=True)
        t.start()

    def start_background_sync(self):
        if self.is_running: return
        self.is_running = True
        self.sync_thread = threading.Thread(target=self._background_loop, daemon=True)
        self.sync_thread.start()

    def stop_background_sync(self):
        self.is_running = False

    def _background_loop(self):
        while self.is_running:
            settings = self.get_cloud_settings()
            if settings.get('cloud_auto_sync') == "1":
                self.run_sync_cycle()
            try:
                interval = int(settings.get('cloud_sync_interval', '30'))
            except:
                interval = 30
            time.sleep(max(10, interval))

    def run_sync_cycle(self):
        with self._lock:
            settings = self.get_cloud_settings()
            url = settings.get('cloud_api_url', '')
            key = settings.get('cloud_api_key', 'syrian_home_pos_secret_token_2026')

            if not url: return

            # 1. دفع الفواتير غير المزامنة من sales إلى الويب سايت
            self._sync_pending_sales(url, key)

            # 2. دفع المنتجات المحدثة محلياً إلى الويب سايت
            self._sync_pending_products(url, key)

            # 3. سحب الطلبات الجديدة القادمة من المتجر الإلكتروني
            self._pull_online_orders(url, key)

    def _sync_pending_sales(self, api_url, api_key):
        try:
            self.cursor.execute("SELECT id, total, date, customer, phone, address, delivery_person, status, payment_method, payment_fee, discount, delivery_fee FROM sales WHERE synced = 0 LIMIT 20")
            rows = self.cursor.fetchall()
            for r in rows:
                s_id = r[0]
                self.cursor.execute("""
                    SELECT p.id, p.name, p.barcode, p.local_code, s.qty, p.price 
                    FROM sale_items s 
                    LEFT JOIN products p ON s.product_id = p.id 
                    WHERE s.sale_id = ?
                """, (s_id,))
                
                items = []
                for it in self.cursor.fetchall():
                    items.append({
                        'product_id': it[0],
                        'name': it[1] or f"منتج #{it[0]}",
                        'barcode': it[2] or '',
                        'local_code': it[3] or '',
                        'qty': it[4],
                        'price': it[5] or 0
                    })

                self.cursor.execute("SELECT value FROM settings WHERE key='default_cashier'")
                cashier_res = self.cursor.fetchone()
                cashier_name = cashier_res[0] if cashier_res else 'كاشير محلي'

                payload = {
                    'local_sale_id': s_id,
                    'total': r[1],
                    'date': r[2],
                    'customer': r[3],
                    'phone': r[4],
                    'address': r[5],
                    'delivery_person': r[6],
                    'status': r[7],
                    'payment_method': r[8],
                    'payment_fee': r[9],
                    'discount': r[10],
                    'delivery_fee': r[11],
                    'cashier_name': cashier_name,
                    'source': 'desktop_pos',
                    'items': items
                }

                ok, resp = self._make_request(api_url, action='push_sale', payload=payload, api_key=api_key, method='POST')
                if ok and isinstance(resp, dict) and resp.get('success'):
                    remote_id = resp.get('remote_id', '')
                    self.cursor.execute("UPDATE sales SET synced=1, remote_id=? WHERE id=?", (str(remote_id), s_id))
                    self.cursor.execute("UPDATE sync_queue SET status='synced' WHERE entity_type='sale' AND entity_id=?", (s_id,))
                else:
                    # في حالة عدم توفر اتصال بالإنترنت نتركها بالانتظار
                    pass
            self.db.commit()
        except Exception as e:
            print(f"Sync sales error: {e}")

    def _sync_pending_products(self, api_url, api_key):
        try:
            self.cursor.execute("SELECT id, barcode, barcode2, barcode3, all_barcodes, local_code, name, price, cost, stock FROM products WHERE synced = 0 LIMIT 30")
            rows = self.cursor.fetchall()
            for r in rows:
                p_id = r[0]
                payload = {
                    'local_product_id': p_id,
                    'barcode': r[1] or '',
                    'barcode2': r[2] or '',
                    'barcode3': r[3] or '',
                    'all_barcodes': r[4] or '',
                    'local_code': r[5] or '',
                    'name': r[6] or '',
                    'price': r[7] or 0,
                    'cost': r[8] or 0,
                    'stock': r[9] or 0
                }

                ok, resp = self._make_request(api_url, action='sync_product', payload=payload, api_key=api_key, method='POST')
                if ok and isinstance(resp, dict) and resp.get('success'):
                    self.cursor.execute("UPDATE products SET synced=1 WHERE id=?", (p_id,))
                    self.cursor.execute("UPDATE sync_queue SET status='synced' WHERE entity_type='product' AND entity_id=?", (p_id,))
            self.db.commit()
        except Exception as e:
            print(f"Sync products error: {e}")

    def pull_products_from_cloud(self, api_url=None, api_key=None):
        """سحب كافة المنتجات والمخزون من السيرفر المركزي وحفظها في قاعدة البيانات المحلية"""
        settings = self.get_cloud_settings()
        url = api_url or settings.get('cloud_api_url', '')
        key = api_key or settings.get('cloud_api_key', 'syrian_home_pos_secret_token_2026')

        if not url: return False, "رابط السيرفر غير محدد!"

        ok, resp = self._make_request(url, action='get_products', api_key=key, method='GET')
        if not ok or not isinstance(resp, dict) or not resp.get('success'):
            return False, f"تعذر جلب المنتجات من السحابة: {resp}"

        cloud_products = resp.get('products', [])
        inserted_count = 0
        updated_count = 0

        for cp in cloud_products:
            p_name = cp.get('name', '').strip()
            p_barcode = cp.get('barcode', '').strip()
            p_bc2 = cp.get('barcode2', '').strip()
            p_bc3 = cp.get('barcode3', '').strip()
            p_all_bc = cp.get('all_barcodes', '').strip() or p_barcode
            p_loc = cp.get('local_code', '').strip()
            p_price = float(cp.get('price', 0))
            p_cost = float(cp.get('cost', 0))
            p_stock = float(cp.get('stock', 100))

            # البحث عن المنتج محلياً بالباركود أو الكود المحلي أو الاسم
            self.cursor.execute("SELECT id FROM products WHERE (barcode != '' AND barcode = ?) OR (local_code != '' AND local_code = ?) OR name = ? LIMIT 1", (p_barcode, p_loc, p_name))
            local_row = self.cursor.fetchone()

            if local_row:
                loc_id = local_row[0]
                self.cursor.execute("""
                    UPDATE products SET name=?, price=?, cost=?, stock=?, barcode=?, barcode2=?, barcode3=?, all_barcodes=?, local_code=?, synced=1 
                    WHERE id=?
                """, (p_name, p_price, p_cost, p_stock, p_barcode, p_bc2, p_bc3, p_all_bc, p_loc, loc_id))
                updated_count += 1
            else:
                self.cursor.execute("""
                    INSERT INTO products (barcode, barcode2, barcode3, all_barcodes, local_code, name, price, cost, stock, synced) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                """, (p_barcode, p_bc2, p_bc3, p_all_bc, p_loc, p_name, p_price, p_cost, p_stock))
                inserted_count += 1

        self.db.commit()
        return True, f"✅ تمت المزامنة بنجاح: تم تحديث {updated_count} صنف وإضافة {inserted_count} صنف جديد من السحابة."

    def _pull_online_orders(self, api_url, api_key):
        """سحب وتجهيز الطلبات الواردة عبر المتجر الإلكتروني"""
        try:
            ok, resp = self._make_request(api_url, action='get_pending_orders', api_key=api_key, method='GET')
            if ok and isinstance(resp, dict) and resp.get('success'):
                orders = resp.get('orders', [])
                # يمكن دمجها أو حفظها كفواتير تحت الطلب
                pass
        except Exception as e:
            print(f"Pull orders error: {e}")
