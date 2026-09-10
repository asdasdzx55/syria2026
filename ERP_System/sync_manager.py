import sqlite3
import datetime
import json
import threading
import time
import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry
import urllib.parse
import os
import sys

def get_base_dir():
    if getattr(sys, 'frozen', False):
        return os.path.dirname(sys.executable)
    return os.path.dirname(os.path.abspath(__file__))

def get_db_path():
    base_dir = get_base_dir()
    p1 = os.path.join(base_dir, 'my_business_v3.db')
    if os.path.exists(p1): return p1
    p2 = os.path.join(os.getcwd(), 'my_business_v3.db')
    if os.path.exists(p2): return p2
    return p1

class HybridSyncManager:
    def __init__(self, db_conn=None, app=None):
        self.app = app
        self.is_running = False
        self.sync_thread = None
        self._lock = threading.Lock()
        self._session = None

    def _get_db(self):
        """ط¥ظ†ط´ط§ط، ط§طھطµط§ظ„ ط¢ظ…ظ† ظˆظ…ط³طھظ‚ظ„ ط¨ظ‚ط§ط¹ط¯ط© ط§ظ„ط¨ظٹط§ظ†ط§طھ ظ„ظƒظ„ ط®ظٹط· ط¹ظ…ظ„ (Thread-Safe)"""
        db_path = get_db_path()
        conn = sqlite3.connect(db_path, timeout=15)
        return conn

    def _get_session(self):
        if self._session is None:
            self._session = requests.Session()
            retries = Retry(total=3, backoff_factor=0.3, status_forcelist=[500, 502, 503, 504])
            adapter = HTTPAdapter(max_retries=retries)
            self._session.mount('https://', adapter)
            self._session.mount('http://', adapter)
        return self._session

    def get_cloud_settings(self):
        conn = self._get_db()
        cur = conn.cursor()
        try:
            cur.execute("SELECT key, value FROM settings WHERE key LIKE 'cloud_%' OR key LIKE 'hostinger_%' OR key = 'api_secret_key'")
            st = dict(cur.fetchall())
            if not st.get('cloud_api_key'):
                st['cloud_api_key'] = st.get('api_secret_key', 'syrian_home_pos_secret_token_2026')
            if not st.get('cloud_api_url'):
                st['cloud_api_url'] = 'https://syrianhouse.almagd555.com/api_sync.php'
            return st
        finally:
            conn.close()

    def save_cloud_settings(self, api_url, api_key, auto_sync="1", sync_interval="30"):
        clean_url = (api_url or '').strip().rstrip('/')
        if clean_url and not clean_url.endswith('.php') and not clean_url.endswith('/api_sync.php'):
            if '/web_store' in clean_url or 'public_html' in clean_url:
                clean_url = clean_url.rstrip('/') + '/api_sync.php'
            else:
                clean_url = f"{clean_url}/api_sync.php"
        
        settings = {
            'cloud_api_url': clean_url or 'https://syrianhouse.almagd555.com/api_sync.php',
            'cloud_api_key': api_key.strip() if api_key else 'syrian_home_pos_secret_token_2026',
            'cloud_auto_sync': str(auto_sync),
            'cloud_sync_interval': str(sync_interval)
        }
        conn = self._get_db()
        cur = conn.cursor()
        try:
            for k, v in settings.items():
                cur.execute("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)", (k, v))
            conn.commit()
        finally:
            conn.close()

    def add_to_queue(self, action, entity_type, entity_id, payload_dict):
        conn = self._get_db()
        cur = conn.cursor()
        try:
            date_now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            payload_json = json.dumps(payload_dict, ensure_ascii=False)
            cur.execute("INSERT INTO sync_queue (action, entity_type, entity_id, payload, status, created_at) VALUES (?, ?, ?, ?, 'pending', ?)",
                        (action, entity_type, entity_id, payload_json, date_now))
            conn.commit()
        except Exception as e:
            print(f"Error adding to sync queue: {e}")
        finally:
            conn.close()

    def _normalize_url(self, raw_url, action=None):
        url = (raw_url or '').strip().rstrip('/')
        if not url: 
            url = 'https://syrianhouse.almagd555.com/api_sync.php'
        
        if not url.endswith('api_sync.php'):
            if url.endswith('.php'):
                pass
            else:
                url = f"{url}/api_sync.php"
                
        if action:
            sep = '&' if '?' in url else '?'
            url = f"{url}{sep}action={urllib.parse.quote(action)}"
        return url

    def _make_request(self, raw_url, action, payload=None, api_key=None, method='GET', timeout=10):
        target_url = self._normalize_url(raw_url, action)
        headers = {
            'User-Agent': 'SyrianHome-ERP-SyncHub/2.0',
            'Authorization': f"Bearer {api_key or 'syrian_home_pos_secret_token_2026'}",
            'X-API-KEY': api_key or 'syrian_home_pos_secret_token_2026',
            'Connection': 'close'
        }

        session = self._get_session()
        try:
            if method.upper() == 'POST':
                headers['Content-Type'] = 'application/json; charset=utf-8'
                resp = session.post(target_url, json=payload, headers=headers, timeout=timeout)
            else:
                resp = session.get(target_url, headers=headers, timeout=timeout)

            try:
                res_json = resp.json()
                return (resp.status_code in (200, 201)), res_json
            except Exception:
                return (resp.status_code in (200, 201)), resp.text
        except requests.exceptions.RequestException as e:
            return False, f"ط®ط·ط£ ظپظٹ ط§ظ„ط§طھطµط§ظ„ ط¨ط§ظ„ط®ط§ط¯ظ…: {e}"
        except Exception as e:
            return False, f"ط®ط·ط£ ط؛ظٹط± ظ…طھظˆظ‚ط¹: {e}"

    def test_cloud_connection(self, api_url=None, api_key=None):
        settings = self.get_cloud_settings()
        url = api_url or settings.get('cloud_api_url', 'https://syrianhouse.almagd555.com/api_sync.php')
        key = api_key or settings.get('cloud_api_key', 'syrian_home_pos_secret_token_2026')

        ok, res = self._make_request(url, action='ping', api_key=key, method='GET', timeout=8)
        if ok and isinstance(res, dict) and res.get('success'):
            store = res.get('store_name', 'ط³ظˆط¨ط± ظ…ط§ط±ظƒطھ ط§ظ„ظ…ظ†ط²ظ„ ط§ظ„ط³ظˆط±ظٹ')
            cnt = res.get('total_products', 0)
            return True, f"âœ… ظ…طھطµظ„ ط¨ظ†ط¬ط§ط­ ط¨ط³ط­ط§ط¨ط© ({store}) âڑ،\nط¥ط¬ظ…ط§ظ„ظٹ ط§ظ„ظ…ظ†طھط¬ط§طھ ط¨ط§ظ„ط³ط­ط§ط¨ط©: {cnt} طµظ†ظپ"
        elif ok:
            return True, "âœ… طھظ… ط§ظ„ط§طھطµط§ظ„ ط¨ظ†ط¬ط§ط­ ط¨ط§ظ„ط®ط§ط¯ظ… ط§ظ„ظ…ط±ظƒط²ظٹ!"
        else:
            return False, f"ظپط´ظ„ ط§ظ„ط§طھطµط§ظ„: {res}"

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
            try:
                settings = self.get_cloud_settings()
                if settings.get('cloud_auto_sync') == "1":
                    self.run_sync_cycle()
                interval = int(settings.get('cloud_sync_interval', '30'))
            except:
                interval = 30
            time.sleep(max(10, interval))

    def run_sync_cycle(self):
        with self._lock:
            conn = self._get_db()
            cur = conn.cursor()
            try:
                cur.execute("SELECT key, value FROM settings WHERE key LIKE 'cloud_%' OR key LIKE 'hostinger_%' OR key = 'api_secret_key'")
                settings = dict(cur.fetchall())
                url = settings.get('cloud_api_url', 'https://syrianhouse.almagd555.com/api_sync.php')
                key = settings.get('cloud_api_key', 'syrian_home_pos_secret_token_2026')

                if not url: return

                # 1. ط¯ظپط¹ ط§ظ„ظپظˆط§طھظٹط± ط؛ظٹط± ط§ظ„ظ…ط²ط§ظ…ظ†ط© ظ…ظ† sales ط¥ظ„ظ‰ ط§ظ„ظˆظٹط¨ ط³ط§ظٹطھ
                self._sync_pending_sales(url, key, conn, cur)

                # 2. ط¯ظپط¹ ط§ظ„ظ…ظ†طھط¬ط§طھ ط§ظ„ظ…ط­ط¯ط«ط© ظ…ط­ظ„ظٹط§ظ‹ ط¥ظ„ظ‰ ط§ظ„ظˆظٹط¨ ط³ط§ظٹطھ
                self._sync_pending_products(url, key, conn, cur)

                # 3. ظ…ط²ط§ظ…ظ†ط© ط§ظ„ط£ظ‚ط³ط§ظ… ظˆط§ظ„طھطµظ†ظٹظپط§طھ ط§ظ„ط£ط³ط§ط³ظٹط© ظˆط§ظ„ظپط±ط¹ظٹط©
                self._sync_all_categories(url, key, conn, cur)

                # 4. ط¯ظپط¹ ط§ظ„ظ…ظˆط±ط¯ظٹظ† ط؛ظٹط± ط§ظ„ظ…ط²ط§ظ…ظ†ظٹظ† ظ…ط­ظ„ظٹط§ظ‹ ط¥ظ„ظ‰ ط§ظ„ط³ط­ط§ط¨ط©
                self._sync_pending_suppliers(url, key, conn, cur)

                # 5. ط¯ظپط¹ ظپظˆط§طھظٹط± ط§ظ„ظ…ط´طھط±ظٹط§طھ ظˆط§ظ„طھظˆط±ظٹط¯ ط؛ظٹط± ط§ظ„ظ…ط²ط§ظ…ظ†ط© ظ…ط­ظ„ظٹط§ظ‹ ط¥ظ„ظ‰ ط§ظ„ط³ط­ط§ط¨ط©
                self._sync_pending_purchases(url, key, conn, cur)

                # 6. ظ…ط²ط§ظ…ظ†ط© ط·ظٹط§ط±ظٹ ظˆظ…ظ†ط¯ظˆط¨ظٹ ط§ظ„ط¯ظ„ظٹظپط±ظٹ ط«ظ†ط§ط¦ظٹط§ظ‹
                self._sync_delivery_drivers(url, key, conn, cur)
                self._pull_cloud_delivery_drivers(url, key, conn, cur)

                # 7. ظ…ط²ط§ظ…ظ†ط© ظ…ظˆط¸ظپظٹ ظˆط¹ظ…ط§ظ„ ط§ظ„ظ…طھط¬ط± ط«ظ†ط§ط¦ظٹط§ظ‹
                self._sync_pending_employees(url, key, conn, cur)
                self._pull_cloud_employees(url, key, conn, cur)

                # 8. ط³ط­ط¨ ط§ظ„ط·ظ„ط¨ط§طھ ط§ظ„ط¬ط¯ظٹط¯ط© ط§ظ„ظ‚ط§ط¯ظ…ط© ظ…ظ† ط§ظ„ظ…طھط¬ط± ط§ظ„ط¥ظ„ظƒطھط±ظˆظ†ظٹ
                self._pull_online_orders(url, key, conn, cur)

            except Exception as e:
                print(f"Sync cycle exception: {e}")
            finally:
                conn.close()

    def _sync_pending_sales(self, api_url, api_key, conn, cur):
        try:
            cur.execute("SELECT id, total, date, customer, phone, address, delivery_person, status, payment_method, payment_fee, discount, delivery_fee FROM sales WHERE synced = 0 LIMIT 20")
            rows = cur.fetchall()
            for r in rows:
                s_id = r[0]
                cur.execute("""
                    SELECT p.id, p.name, p.barcode, p.local_code, s.qty, p.price, p.remote_id
                    FROM sale_items s 
                    LEFT JOIN products p ON s.product_id = p.id 
                    WHERE s.sale_id = ?
                """, (s_id,))
                
                items = []
                for it in cur.fetchall():
                    items.append({
                        'product_id': it[6] or it[0],
                        'local_product_id': it[0],
                        'name': it[1] or f"ظ…ظ†طھط¬ #{it[0]}",
                        'barcode': it[2] or '',
                        'local_code': it[3] or '',
                        'qty': it[4],
                        'price': it[5] or 0
                    })

                cur.execute("SELECT value FROM settings WHERE key='default_cashier'")
                cashier_res = cur.fetchone()
                cashier_name = cashier_res[0] if cashier_res else 'ظƒط§ط´ظٹط± ظ…ط­ظ„ظٹ'

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
                    cur.execute("UPDATE sales SET synced=1, remote_id=? WHERE id=?", (str(remote_id), s_id))
                    cur.execute("UPDATE sync_queue SET status='synced' WHERE entity_type='sale' AND entity_id=?", (s_id,))
                    conn.commit()
        except Exception as e:
            print(f"Sync sales error: {e}")

    def _sync_pending_products(self, api_url, api_key, conn, cur):
        try:
            cur.execute("""
                SELECT id, barcode, barcode2, barcode3, all_barcodes, local_code, name, price, cost, stock, category, sub_category, remote_id, is_weight_based, unit_type, has_pack, pack_name, pack_barcode, pack_price, pack_qty 
                FROM products 
                WHERE synced = 0 LIMIT 50
            """)
            rows = cur.fetchall()
            for r in rows:
                p_id = r[0]
                is_weight = 1 if (r[13] or (r[14] in ['ظˆط²ظ†', 'weight'])) else 0
                unit_t = r[14] or ('ظˆط²ظ†' if is_weight else 'ظ‚ط·ط¹ط©')
                payload = {
                    'local_product_id': p_id,
                    'remote_id': r[12] or '',
                    'barcode': r[1] or '',
                    'barcode2': r[2] or '',
                    'barcode3': r[3] or '',
                    'all_barcodes': r[4] or '',
                    'local_code': r[5] or '',
                    'name': r[6] or '',
                    'price': r[7] or 0,
                    'cost': r[8] or 0,
                    'stock': r[9] or 0,
                    'category': r[10] or 'ط¹ط§ظ…',
                    'sub_category': r[11] or '',
                    'is_weight_based': is_weight,
                    'unit_type': unit_t,
                    'has_pack': r[15] or 0,
                    'pack_name': r[16] or '',
                    'pack_barcode': r[17] or '',
                    'pack_price': r[18] or 0,
                    'pack_qty': r[19] or 1
                }

                ok, resp = self._make_request(api_url, action='sync_product', payload=payload, api_key=api_key, method='POST')
                if ok and isinstance(resp, dict) and resp.get('success'):
                    rem_id = resp.get('product_id', '')
                    cur.execute("UPDATE products SET synced=1, remote_id=? WHERE id=?", (str(rem_id), p_id))
                    cur.execute("UPDATE sync_queue SET status='synced' WHERE entity_type='product' AND entity_id=?", (p_id,))
                    conn.commit()
        except Exception as e:
            print(f"Sync products error: {e}")

    def _sync_delivery_drivers(self, api_url, api_key, conn, cur):
        """ظ…ط²ط§ظ…ظ†ط© ط·ظٹط§ط±ظٹ ظˆظ…ظ†ط¯ظˆط¨ظٹ ط§ظ„ط¯ظ„ظٹظپط±ظٹ ظپظ‚ط· (ظپطµظ„ ط­ط§ط³ظ… ط¹ظ† ط§ظ„ط¹ظ…ط§ظ„ ظˆط§ظ„ظ…ظˆط¸ظپظٹظ†)"""
        try:
            cur.execute("""
                SELECT id, name, phone, remote_id 
                FROM employees 
                WHERE (role IN ('ط¯ظ„ظٹظپط±ظٹ', 'ط·ظٹط§ط±', 'ط³ط§ط¦ظ‚') OR role LIKE '%ط¯ظ„ظٹظپط±ظٹ%' OR role LIKE '%ط·ظٹط§ط±%' OR role LIKE '%ط³ط§ط¦ظ‚%')
                AND (synced = 0 OR synced IS NULL)
            """)
            rows = cur.fetchall()
            for r in rows:
                payload = {
                    'name': r[1] or '',
                    'phone': r[2] or '',
                    'pin_code': '1234'
                }
                ok, resp = self._make_request(api_url, action='sync_delivery_driver', payload=payload, api_key=api_key, method='POST')
                if ok and isinstance(resp, dict) and resp.get('success'):
                    d_id = resp.get('driver_id', '')
                    cur.execute("UPDATE employees SET synced=1, remote_id=? WHERE id=?", (str(d_id), r[0]))
                    conn.commit()
        except Exception as e:
            print(f"Sync delivery drivers error: {e}")

    def _sync_pending_employees(self, api_url, api_key, conn, cur):
        """ظ…ط²ط§ظ…ظ†ط© ط¹ظ…ط§ظ„ ظˆظ…ظˆط¸ظپظٹ ط§ظ„ظ…طھط¬ط± ط¥ظ„ظ‰ ط§ظ„ط³ط­ط§ط¨ط© ظپظˆط±ط§ظ‹ (ط؛ظٹط± ط·ظٹط§ط±ظٹ ط§ظ„ط¯ظ„ظٹظپط±ظٹ)"""
        try:
            cur.execute("""
                SELECT id, name, role, salary, phone, remote_id 
                FROM employees 
                WHERE (role NOT IN ('ط¯ظ„ظٹظپط±ظٹ', 'ط·ظٹط§ط±', 'ط³ط§ط¦ظ‚') AND role NOT LIKE '%ط¯ظ„ظٹظپط±ظٹ%' AND role NOT LIKE '%ط·ظٹط§ط±%' AND role NOT LIKE '%ط³ط§ط¦ظ‚%')
                AND (synced = 0 OR synced IS NULL)
                LIMIT 50
            """)
            rows = cur.fetchall()
            for r in rows:
                e_id = r[0]
                payload = {
                    'employee_id': r[5] or '',
                    'name': r[1] or '',
                    'role': r[2] or 'ط¹ط§ظ…ظ„',
                    'salary': r[3] or 0,
                    'base_salary': r[3] or 0,
                    'phone': r[4] or '',
                    'is_active': 1
                }
                ok, resp = self._make_request(api_url, action='sync_employee', payload=payload, api_key=api_key, method='POST')
                if ok and isinstance(resp, dict) and resp.get('success'):
                    rem_id = resp.get('employee_id', '')
                    cur.execute("UPDATE employees SET synced=1, remote_id=? WHERE id=?", (str(rem_id), e_id))
                    conn.commit()
        except Exception as e:
            print(f"Sync pending employees error: {e}")

    def _pull_cloud_employees(self, api_url, api_key, conn, cur):
        """ط³ط­ط¨ ط¹ظ…ط§ظ„ ظˆظ…ظˆط¸ظپظٹ ط§ظ„ظ…طھط¬ط± ط§ظ„ظ…ط¶ط§ظپظٹظ† ط£ظˆ ط§ظ„ظ…ط­ط¯ط«ظٹظ† ظ…ظ† ظƒط§ط´ظٹط± ط§ظ„ظˆظٹط¨ ط¥ظ„ظ‰ ط§ظ„ظƒط§ط´ظٹط± ط§ظ„ظ…ظƒطھط¨ظٹ"""
        try:
            ok, resp = self._make_request(api_url, action='get_employees', api_key=api_key, method='GET', timeout=8)
            if ok and isinstance(resp, dict) and resp.get('success'):
                emps = resp.get('employees', [])
                for e in emps:
                    name = (e.get('name') or '').strip()
                    if not name: continue
                    role = (e.get('role') or 'ط¹ط§ظ…ظ„').strip()
                    phone = (e.get('phone') or '').strip()
                    sal = float(e.get('base_salary') or 0)
                    rem_id = str(e.get('id') or '')

                    # طھط®ط·ظٹ ظƒط¨ط§طھظ† ط§ظ„طھظˆطµظٹظ„ ظ‡ظ†ط§ ظ„ط£ظ†ظ‡ظ… ظٹظڈط¯ط§ط±ظˆظ† ظپظٹ ط¯ط§ظ„ط© _pull_cloud_delivery_drivers
                    if role in ('ط¯ظ„ظٹظپط±ظٹ', 'ط·ظٹط§ط±', 'ط³ط§ط¦ظ‚') or 'ط¯ظ„ظٹظپط±ظٹ' in role or 'ط·ظٹط§ط±' in role:
                        continue

                    cur.execute("SELECT id, role FROM employees WHERE remote_id = ? OR name = ? LIMIT 1", (rem_id, name))
                    row = cur.fetchone()
                    if row:
                        cur.execute("UPDATE employees SET name=?, role=?, salary=?, phone=?, synced=1, remote_id=? WHERE id=?", (name, role, sal, phone, rem_id, row[0]))
                    else:
                        cur.execute("INSERT INTO employees (name, role, salary, phone, hours, synced, remote_id) VALUES (?, ?, ?, ?, 8, 1, ?)", (name, role, sal, phone, rem_id))
                conn.commit()
        except Exception as e:
            print(f"Pull cloud employees error: {e}")

    def _pull_cloud_delivery_drivers(self, api_url, api_key, conn, cur):
        """ط³ط­ط¨ ظƒط¨ط§طھظ† ط§ظ„طھظˆطµظٹظ„ ظˆط§ظ„ط¯ظ„ظٹظپط±ظٹ ط§ظ„ظ…ط¶ط§ظپظٹظ† ظ…ظ† ظƒط§ط´ظٹط± ط§ظ„ظˆظٹط¨ ط¥ظ„ظ‰ ط§ظ„ظƒط§ط´ظٹط± ط§ظ„ظ…ظƒطھط¨ظٹ"""
        try:
            ok, resp = self._make_request(api_url, action='get_delivery_drivers', api_key=api_key, method='GET', timeout=8)
            if ok and isinstance(resp, dict) and resp.get('success'):
                drivers = resp.get('delivery_drivers', [])
                for d in drivers:
                    name = (d.get('name') or '').strip()
                    if not name: continue
                    phone = (d.get('phone') or '').strip()
                    rem_id = str(d.get('id') or '')

                    cur.execute("SELECT id FROM employees WHERE remote_id = ? OR name = ? LIMIT 1", (rem_id, name))
                    row = cur.fetchone()
                    if row:
                        cur.execute("UPDATE employees SET role='ط¯ظ„ظٹظپط±ظٹ', phone=?, synced=1, remote_id=? WHERE id=?", (phone, rem_id, row[0]))
                    else:
                        cur.execute("INSERT INTO employees (name, role, salary, phone, hours, synced, remote_id) VALUES (?, 'ط¯ظ„ظٹظپط±ظٹ', 0, ?, 8, 1, ?)", (name, phone, rem_id))
                conn.commit()
        except Exception as e:
            print(f"Pull cloud delivery drivers error: {e}")

    def _sync_pending_suppliers(self, api_url, api_key, conn, cur):
        try:
            cur.execute("SELECT id, name, phone, balance, remote_id FROM suppliers WHERE synced = 0 LIMIT 50")
            rows = cur.fetchall()
            for r in rows:
                s_id = r[0]
                payload = {
                    'local_id': s_id,
                    'remote_id': r[4] or '',
                    'name': r[1] or '',
                    'phone': r[2] or '',
                    'balance': r[3] or 0
                }
                ok, resp = self._make_request(api_url, action='sync_supplier', payload=payload, api_key=api_key, method='POST')
                if ok and isinstance(resp, dict) and resp.get('success'):
                    rem_id = resp.get('supplier_id', '')
                    cur.execute("UPDATE suppliers SET synced=1, remote_id=? WHERE id=?", (str(rem_id), s_id))
                    conn.commit()
        except Exception as e:
            print(f"Sync suppliers error: {e}")

    def _sync_pending_purchases(self, api_url, api_key, conn, cur):
        try:
            cur.execute("""
                SELECT p.id, p.supplier_id, p.total, p.paid, p.date, p.status, p.discount, p.invoice_number, p.payment_method, p.remote_id, s.name 
                FROM purchases p 
                LEFT JOIN suppliers s ON p.supplier_id = s.id 
                WHERE p.synced = 0 LIMIT 20
            """)
            rows = cur.fetchall()
            for r in rows:
                p_id = r[0]
                cur.execute("""
                    SELECT pi.product_id, pi.qty, pi.cost, pi.barcode, pi.name, pi.unit, pi.selling_price, pr.remote_id
                    FROM purchase_items pi
                    LEFT JOIN products pr ON pi.product_id = pr.id
                    WHERE pi.purchase_id = ?
                """, (p_id,))
                
                items = []
                for it in cur.fetchall():
                    items.append({
                        'product_id': it[7] or it[0],
                        'local_product_id': it[0],
                        'qty': it[1],
                        'cost_price': it[2],
                        'barcode': it[3] or '',
                        'name': it[4] or f"ظ…ظ†طھط¬ #{it[0]}",
                        'unit': it[5] or 'ظ‚ط·ط¹ط©',
                        'selling_price': it[6] or 0
                    })

                payload = {
                    'local_purchase_id': p_id,
                    'remote_id': r[9] or '',
                    'supplier_id': r[1] or 0,
                    'supplier_name': r[10] or 'ظ…ظˆط±ط¯ ط¹ط§ظ…',
                    'invoice_number': r[7] or f"INV-{p_id}",
                    'payment_method': r[8] or 'ظ†ظ‚ط¯ظٹ',
                    'total_amount': r[2] or 0,
                    'paid_amount': r[3] or 0,
                    'discount': r[6] or 0,
                    'date': r[4] or '',
                    'status': r[5] or 'ظ…ظƒطھظ…ظ„ط©',
                    'source': 'desktop_pos',
                    'items': items
                }

                ok, resp = self._make_request(api_url, action='push_purchase', payload=payload, api_key=api_key, method='POST')
                if ok and isinstance(resp, dict) and resp.get('success'):
                    remote_id = resp.get('remote_id', resp.get('purchase_id', ''))
                    cur.execute("UPDATE purchases SET synced=1, remote_id=? WHERE id=?", (str(remote_id), p_id))
                    conn.commit()
        except Exception as e:
            print(f"Sync purchases error: {e}")

    def _sync_all_categories(self, api_url, api_key, conn, cur):
        try:
            cur.execute("""
                SELECT c.name, sc.name 
                FROM categories c 
                LEFT JOIN sub_categories sc ON sc.main_category_id = c.id
            """)
            rows = cur.fetchall()
            for main_name, sub_name in rows:
                if main_name:
                    payload = {
                        'main_category': main_name,
                        'sub_category': sub_name or ''
                    }
                    self._make_request(api_url, action='sync_category', payload=payload, api_key=api_key, method='POST')
        except Exception as e:
            print(f"Sync categories error: {e}")

    def delete_product_from_cloud(self, local_id, barcode='', local_code='', name='', remote_id=None):
        """ط­ط°ظپ ظ…ظ†طھط¬ ظ…ظ† ط§ظ„ظ…طھط¬ط± ط§ظ„ط¥ظ„ظƒطھط±ظˆظ†ظٹ ط§ظ„ط³ط­ط§ط¨ظٹ ظپظˆط±ط§ظ‹ ظپظٹ ط§ظ„ط®ظ„ظپظٹط©"""
        try:
            settings = self.get_cloud_settings()
            url = settings.get('cloud_api_url', 'https://syrianhouse.almagd555.com/api_sync.php')
            key = settings.get('cloud_api_key', 'syrian_home_pos_secret_token_2026')
            payload = {
                'product_id': remote_id or '',
                'barcode': barcode or '',
                'local_code': local_code or '',
                'name': name or ''
            }
            def _task(target_url, api_key, pl):
                try:
                    self._make_request(target_url, action='delete_product', payload=pl, api_key=api_key, method='POST')
                except Exception as e:
                    print(f"Delete product from cloud error: {e}")

            threading.Thread(target=_task, args=(url, key, payload), daemon=True).start()
        except Exception as e:
            print(f"Delete product dispatch error: {e}")

    def pull_products_from_cloud(self, api_url=None, api_key=None):
        """ط³ط­ط¨ ظƒط§ظپط© ط§ظ„ظ…ظ†طھط¬ط§طھ ظˆط§ظ„ظ…ط®ط²ظˆظ† ظ…ظ† ط§ظ„ط³ظٹط±ظپط± ط§ظ„ظ…ط±ظƒط²ظٹ ظˆط­ظپط¸ظ‡ط§ ظپظٹ ظ‚ط§ط¹ط¯ط© ط§ظ„ط¨ظٹط§ظ†ط§طھ ط§ظ„ظ…ط­ظ„ظٹط©"""
        settings = self.get_cloud_settings()
        url = api_url or settings.get('cloud_api_url', 'https://syrianhouse.almagd555.com/api_sync.php')
        key = api_key or settings.get('cloud_api_key', 'syrian_home_pos_secret_token_2026')

        ok, resp = self._make_request(url, action='get_products', api_key=key, method='GET', timeout=12)
        if not ok or not isinstance(resp, dict) or not resp.get('success'):
            return False, f"طھط¹ط°ط± ط¬ظ„ط¨ ط§ظ„ظ…ظ†طھط¬ط§طھ ظ…ظ† ط§ظ„ط³ط­ط§ط¨ط©: {resp}"

        cloud_products = resp.get('products', [])
        inserted_count = 0
        updated_count = 0

        conn = self._get_db()
        cur = conn.cursor()
        try:
            for cp in cloud_products:
                p_name = (cp.get('name') or '').strip()
                p_barcode = (cp.get('barcode') or '').strip() or None
                p_bc2 = (cp.get('barcode2') or '').strip() or None
                p_bc3 = (cp.get('barcode3') or '').strip() or None
                p_all_bc = (cp.get('all_barcodes') or '').strip() or (p_barcode if p_barcode else '')
                p_loc = (cp.get('local_code') or '').strip() or None
                p_price = float(cp.get('price', 0))
                p_cost = float(cp.get('cost', 0))
                p_stock = float(cp.get('stock', 100))
                p_cat = (cp.get('category') or 'ط¹ط§ظ…').strip()
                p_sub = (cp.get('sub_category') or '').strip()
                p_rem_id = str(cp.get('id', ''))
                p_is_weight = 1 if (cp.get('is_weight_based') or cp.get('unit_type') in ['weight', 'ظˆط²ظ†']) else 0
                p_unit_type = cp.get('unit_type') or ('ظˆط²ظ†' if p_is_weight else 'ظ‚ط·ط¹ط©')
                p_has_pack = 1 if cp.get('has_pack') else 0
                p_pack_name = (cp.get('pack_name') or '').strip()
                p_pack_barcode = (cp.get('pack_barcode') or '').strip()
                p_pack_price = float(cp.get('pack_price', 0))
                p_pack_qty = float(cp.get('pack_qty', 1))

                # ط§ظ„ط¨ط­ط« ط¹ظ† ط§ظ„ظ…ظ†طھط¬ ظ…ط­ظ„ظٹط§ظ‹ ط¨ط§ظ„ط¨ط§ط±ظƒظˆط¯ ط£ظˆ ط§ظ„ظƒظˆط¯ ط§ظ„ظ…ط­ظ„ظٹ ط£ظˆ ط§ظ„ط§ط³ظ…
                local_row = None
                if p_barcode:
                    cur.execute("SELECT id FROM products WHERE barcode = ? LIMIT 1", (p_barcode,))
                    local_row = cur.fetchone()
                if not local_row and p_pack_barcode:
                    cur.execute("SELECT id FROM products WHERE pack_barcode = ? LIMIT 1", (p_pack_barcode,))
                    local_row = cur.fetchone()
                if not local_row and p_loc:
                    cur.execute("SELECT id FROM products WHERE local_code = ? LIMIT 1", (p_loc,))
                    local_row = cur.fetchone()
                if not local_row:
                    cur.execute("SELECT id FROM products WHERE name = ? LIMIT 1", (p_name,))
                    local_row = cur.fetchone()

                if local_row:
                    loc_id = local_row[0]
                    cur.execute("""
                        UPDATE products SET name=?, price=?, cost=?, stock=?, barcode=?, barcode2=?, barcode3=?, all_barcodes=?, local_code=?, category=?, main_category=?, sub_category=?, is_weight_based=?, unit_type=?, has_pack=?, pack_name=?, pack_barcode=?, pack_price=?, pack_qty=?, synced=1, remote_id=? 
                        WHERE id=?
                    """, (p_name, p_price, p_cost, p_stock, p_barcode, p_bc2, p_bc3, p_all_bc, p_loc, p_cat, p_cat, p_sub, p_is_weight, p_unit_type, p_has_pack, p_pack_name, p_pack_barcode, p_pack_price, p_pack_qty, p_rem_id, loc_id))
                    updated_count += 1
                else:
                    cur.execute("""
                        INSERT INTO products (barcode, barcode2, barcode3, all_barcodes, local_code, name, price, cost, stock, category, main_category, sub_category, is_weight_based, unit_type, has_pack, pack_name, pack_barcode, pack_price, pack_qty, synced, remote_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
                    """, (p_barcode, p_bc2, p_bc3, p_all_bc, p_loc, p_name, p_price, p_cost, p_stock, p_cat, p_cat, p_sub, p_is_weight, p_unit_type, p_has_pack, p_pack_name, p_pack_barcode, p_pack_price, p_pack_qty, p_rem_id))
                    inserted_count += 1

                # طھط³ط¬ظٹظ„ ظˆطھط­ط¯ظٹط« ط§ظ„ط£ظ‚ط³ط§ظ… ظˆط§ظ„طھطµظ†ظٹظپط§طھ طھظ„ظ‚ط§ط¦ظٹط§ظ‹ ظ…ط­ظ„ظٹط§ظ‹
                if p_cat:
                    cur.execute("INSERT OR IGNORE INTO categories (name) VALUES (?)", (p_cat,))
                    if p_sub and p_sub != "ط¹ط§ظ…" and p_sub != "-":
                        cur.execute("SELECT id FROM categories WHERE name = ?", (p_cat,))
                        cat_id_row = cur.fetchone()
                        if cat_id_row:
                            cur.execute("SELECT id FROM sub_categories WHERE name = ? AND main_category_id = ?", (p_sub, cat_id_row[0]))
                            if not cur.fetchone():
                                cur.execute("INSERT INTO sub_categories (name, main_category_id) VALUES (?, ?)", (p_sub, cat_id_row[0]))

            # ط¬ظ„ط¨ ظˆطھط­ط¯ظٹط« ظƒط§ظپط© طھطµظ†ظٹظپط§طھ ط§ظ„ط³ط­ط§ط¨ط©
            try:
                ok_c, resp_c = self._make_request(url, action='get_categories', api_key=key, method='GET')
                if ok_c and isinstance(resp_c, dict) and resp_c.get('success'):
                    for cc in resp_c.get('categories', []):
                        cn = (cc.get('name') or '').strip()
                        cp = cc.get('parent_id')
                        if cn and not cp:
                            cur.execute("INSERT OR IGNORE INTO categories (name) VALUES (?)", (cn,))
            except Exception as ce:
                print(f"Categories pull error: {ce}")

            conn.commit()
            return True, f"âœ… طھظ…طھ ط§ظ„ظ…ط²ط§ظ…ظ†ط© ط¨ظ†ط¬ط§ط­: طھظ… طھط­ط¯ظٹط« {updated_count} طµظ†ظپ ظˆط¥ط¶ط§ظپط© {inserted_count} طµظ†ظپ ط¬ط¯ظٹط¯ ظˆظ…ط²ط§ظ…ظ†ط© ط§ظ„ط£ظ‚ط³ط§ظ… ظ…ظ† ط§ظ„ط³ط­ط§ط¨ط© (ط¥ط¬ظ…ط§ظ„ظٹ {len(cloud_products)} طµظ†ظپ)."
        finally:
            conn.close()

    def reset_cloud_database(self, mode="factory_reset_all", wipe_products=True, api_url=None, api_key=None):
        """طھطµظپظٹط± ظˆط­ط°ظپ ط¬ظ…ظٹط¹ ط¨ظٹط§ظ†ط§طھ ط§ظ„ظ…طھط¬ط± ط§ظ„ط¥ظ„ظƒطھط±ظˆظ†ظٹ ط§ظ„ط³ط­ط§ط¨ظٹ ط§ظ„ظ…ط±ظƒط²ظٹ ط£ظˆ طھطµظپظٹط± ط§ظ„ط­ط³ط§ط¨ط§طھ ظˆط§ظ„ظƒظ…ظٹط§طھ"""
        settings = self.get_cloud_settings()
        url = api_url or settings.get('cloud_api_url', 'https://syrianhouse.almagd555.com/api_sync.php')
        key = api_key or settings.get('cloud_api_key', 'syrian_home_pos_secret_token_2026')
        
        payload = {
            'action': 'system_reset',
            'mode': mode,
            'wipe_products': 1 if wipe_products else 0,
            'confirm_token': 'CONFIRM_RESET_SYRIA_2026',
            'api_key': key
        }
        
        target_url = f"{url}?action=system_reset&confirm_token=CONFIRM_RESET_SYRIA_2026&api_key={urllib.parse.quote(key)}&mode={urllib.parse.quote(mode)}"
        if wipe_products:
            target_url += "&wipe_products=1"

        ok, res = self._make_request(target_url, action='system_reset', payload=payload, api_key=key, method='POST', timeout=15)
        if ok and isinstance(res, dict) and res.get('success'):
            return True, res.get('message', 'طھظ… طھظ†ظپظٹط° ط§ظ„ط¹ظ…ظ„ظٹط© ط§ظ„ط³ط­ط§ط¨ظٹط© ط¨ظ†ط¬ط§ط­.')
        return False, f"ظپط´ظ„ طھطµظپظٹط± ط§ظ„ط³ط­ط§ط¨ط©: {res}"

    def _pull_online_orders(self, api_url, api_key, conn, cur):
        """ط³ط­ط¨ ظˆطھط¬ظ‡ظٹط² ط§ظ„ط·ظ„ط¨ط§طھ ط§ظ„ظˆط§ط±ط¯ط© ط¹ط¨ط± ط§ظ„ظ…طھط¬ط± ط§ظ„ط¥ظ„ظƒطھط±ظˆظ†ظٹ"""
        try:
            ok, resp = self._make_request(api_url, action='get_pending_orders', api_key=api_key, method='GET')
            if ok and isinstance(resp, dict) and resp.get('success'):
                orders = resp.get('orders', [])
                pass
        except Exception as e:
            print(f"Pull orders error: {e}")

