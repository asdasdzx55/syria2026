import customtkinter as ctk
import tkinter.messagebox as messagebox
from tkinter import ttk
import datetime
import sqlite3
import os
import tempfile

class POSPage(ctk.CTkFrame):
    def __init__(self, parent, db_conn, app):
        super().__init__(parent, fg_color="transparent")
        self.db = db_conn
        self.cursor = self.db.cursor()
        self.app = app
        
        # تهيئة قاعدة البيانات مركزية عبر setup_database()
        self.grid_columnconfigure(0, weight=2)
        self.grid_columnconfigure(1, weight=1) 
        self.grid_rowconfigure(1, weight=1)
        
        from sync_manager import HybridSyncManager
        self.sync_mgr = HybridSyncManager(self.db, self.app)

        self.pos_cart = {} 
        self.pos_total = 0.0
        self.pos_discount_val = 0.0
        self.current_viewed_invoice = None
        
        self.setup_ui()


    def setup_ui(self):
        # 1. الشريط العلوي: تصفح الفواتير المسجلة + بيانات العميل والدليفري
        top_card = ctk.CTkFrame(self, corner_radius=12, fg_color="#2c3e50")
        top_card.grid(row=0, column=0, columnspan=2, sticky="ew", padx=10, pady=(5, 5))

        # السطر الأول: تصفح وتعديل وطباعة فواتير المبيعات السابقة
        nav_row = ctk.CTkFrame(top_card, fg_color="#1e272e", corner_radius=8)
        nav_row.pack(fill="x", padx=10, pady=(8, 4))

        ctk.CTkButton(nav_row, text="◀️ السابقة", font=ctk.CTkFont(weight="bold"), width=95, fg_color="#34495e", hover_color="#2c3e50", height=32, command=lambda: self.pos_nav_invoice(-1)).pack(side="right", padx=3, pady=4)
        ctk.CTkButton(nav_row, text="▶️ التالية", font=ctk.CTkFont(weight="bold"), width=95, fg_color="#34495e", hover_color="#2c3e50", height=32, command=lambda: self.pos_nav_invoice(1)).pack(side="right", padx=3, pady=4)
        ctk.CTkButton(nav_row, text="🆕 جديدة", font=ctk.CTkFont(weight="bold"), width=90, fg_color="#16a085", hover_color="#117864", height=32, command=lambda: self.clear_cart(ask=False)).pack(side="right", padx=3, pady=4)
        ctk.CTkButton(nav_row, text="✏️ تعديل الفاتورة", font=ctk.CTkFont(weight="bold"), width=120, fg_color="#e67e22", hover_color="#d35400", height=32, command=self.edit_cart_item).pack(side="right", padx=3, pady=4)

        self.pos_view_id = ctk.CTkEntry(nav_row, width=70, justify="center", placeholder_text="رقم #", font=("Arial", 13, "bold"))
        self.pos_view_id.pack(side="right", padx=4, pady=4)
        ctk.CTkButton(nav_row, text="🖨️ طباعة سابقة", font=ctk.CTkFont(weight="bold"), width=110, fg_color="#2980b9", hover_color="#1f618d", height=32, command=lambda: self.pos_show_invoice(self.pos_view_id.get())).pack(side="right", padx=3, pady=4)

        ctk.CTkButton(nav_row, text="⚡ مزامنة الويب", font=ctk.CTkFont(weight="bold"), width=110, fg_color="#27ae60", hover_color="#1e8449", height=32, command=self.quick_sync_cloud).pack(side="right", padx=3, pady=4)

        self.lbl_pos_header = ctk.CTkLabel(nav_row, text="🛒 سوبر ماركت المنزل السوري | الكاشير المباشر", font=ctk.CTkFont(size=15, weight="bold"), text_color="white")
        self.lbl_pos_header.pack(side="left", padx=15, pady=4)

    def quick_sync_cloud(self):
        self.sync_mgr.trigger_instant_sync()
        self.show_status("⚡ جاري المزامنة اللحظية مع السيرفر المركزي بالخلفية...", "#2ecc71")

        # السطر الثاني: بيانات العميل والطيار
        cust_row = ctk.CTkFrame(top_card, fg_color="transparent")
        cust_row.pack(fill="x", padx=10, pady=(2, 8))

        ctk.CTkLabel(cust_row, text="هاتف العميل:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(5, 2))
        self.pos_cust_phone_var = ctk.StringVar()
        self.pos_cust_phone_var.trace("w", self.fetch_customer_data)
        self.pos_cust_phone = ctk.CTkEntry(cust_row, textvariable=self.pos_cust_phone_var, placeholder_text="01...", width=110, justify="center")
        self.pos_cust_phone.pack(side="right", padx=4)

        ctk.CTkLabel(cust_row, text="الاسم:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(8, 2))
        self.pos_cust_name = ctk.CTkEntry(cust_row, placeholder_text="اسم العميل", width=120)
        self.pos_cust_name.pack(side="right", padx=4)

        ctk.CTkLabel(cust_row, text="العنوان:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(8, 2))
        self.pos_cust_address = ctk.CTkEntry(cust_row, placeholder_text="العنوان بالكامل", width=160)
        self.pos_cust_address.pack(side="right", padx=4)

        ctk.CTkLabel(cust_row, text="الطيار:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(8, 2))
        self.pos_delivery_combo = ctk.CTkComboBox(cust_row, values=["بدون توصيل (تيك أواي)"], width=125)
        self.pos_delivery_combo.pack(side="right", padx=2)
        ctk.CTkButton(cust_row, text="➕ طيار", width=55, fg_color="#8e44ad", hover_color="#732d91", command=self.quick_add_delivery_boy).pack(side="right", padx=2)


        ctk.CTkLabel(cust_row, text="التوصيل:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(8, 2))
        self.pos_delivery_fee_var = ctk.StringVar(value="0")
        self.pos_delivery_fee_var.trace("w", self.calculate_change)
        self.pos_delivery_fee = ctk.CTkEntry(cust_row, textvariable=self.pos_delivery_fee_var, width=55, justify="center")
        self.pos_delivery_fee.pack(side="right", padx=4)

        # 2. القسم الأيسر: قراءة الباركود وإدخال الكمية والبحث بالأصناف
        left_panel = ctk.CTkFrame(self, corner_radius=12, fg_color="#2c3e50")
        left_panel.grid(row=1, column=0, sticky="nsew", padx=(10, 5), pady=(0, 10))

        input_frame = ctk.CTkFrame(left_panel, fg_color="#1e272e", corner_radius=10)
        input_frame.pack(fill="x", padx=10, pady=10)

        row_bc = ctk.CTkFrame(input_frame, fg_color="transparent")
        row_bc.pack(fill="x", padx=8, pady=8)

        ctk.CTkLabel(row_bc, text="الباركود:", font=ctk.CTkFont(size=14, weight="bold")).pack(side="right", padx=(5, 2))
        self.pos_barcode = ctk.CTkEntry(row_bc, width=150, justify="center", font=("Arial", 16, "bold"), placeholder_text="امسح الباركود...")
        self.pos_barcode.pack(side="right", padx=5)

        ctk.CTkLabel(row_bc, text="عدد/كغم (F2):", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(8, 2))
        self.pos_qty_whole = ctk.CTkEntry(row_bc, width=60, justify="center", font=("Arial", 14, "bold"))
        self.pos_qty_whole.insert(0, "1")
        self.pos_qty_whole.pack(side="right", padx=2)

        ctk.CTkLabel(row_bc, text="جرام (F3):", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(8, 2))
        self.pos_qty_grams = ctk.CTkEntry(row_bc, width=60, justify="center", font=("Arial", 14, "bold"))
        self.pos_qty_grams.insert(0, "0")
        self.pos_qty_grams.pack(side="right", padx=2)

        # التحديد التلقائي لكافة النصوص في الخانات فور تركيز الماوس أو لوحة المفاتيح
        def _bind_auto_select(entry_widget):
            def _select(e):
                entry_widget.after(10, lambda: entry_widget.select_range(0, 'end'))
            entry_widget.bind("<FocusIn>", _select)

        _bind_auto_select(self.pos_barcode)
        _bind_auto_select(self.pos_qty_whole)
        _bind_auto_select(self.pos_qty_grams)

        # المعاينة المباشرة لاسم المنتج عند كتابة الباركود
        self.pos_barcode.bind("<KeyRelease>", self._on_barcode_key_release)

        # ربط مفاتيح التنقل السلس والذكي
        self.pos_barcode.bind("<Return>", self._on_barcode_return)
        self.pos_barcode.bind("<Down>", lambda e: self.pos_qty_whole.focus())
        self.pos_barcode.bind("<Tab>", lambda e: self.pos_qty_whole.focus())

        self.pos_qty_whole.bind("<Return>", lambda e: self._on_qty_return())
        self.pos_qty_whole.bind("<Down>", lambda e: self.pos_qty_grams.focus())
        self.pos_qty_whole.bind("<Right>", lambda e: self.pos_qty_grams.focus())
        self.pos_qty_whole.bind("<Up>", lambda e: self.pos_barcode.focus())
        self.pos_qty_whole.bind("<Left>", lambda e: self.pos_barcode.focus())

        self.pos_qty_grams.bind("<Return>", lambda e: self._on_qty_return())
        self.pos_qty_grams.bind("<Up>", lambda e: self.pos_qty_whole.focus())
        self.pos_qty_grams.bind("<Left>", lambda e: self.pos_qty_whole.focus())

        def add_quick_grams(g):
            try:
                curr_g = float(self.pos_qty_grams.get() or 0)
                self.pos_qty_grams.delete(0, 'end')
                self.pos_qty_grams.insert(0, str(int(curr_g + g)))
            except ValueError:
                self.pos_qty_grams.delete(0, 'end')
                self.pos_qty_grams.insert(0, str(g))

        ctk.CTkButton(row_bc, text="+250غ", width=48, fg_color="#2980b9", command=lambda: add_quick_grams(250)).pack(side="right", padx=2)
        ctk.CTkButton(row_bc, text="+500غ", width=48, fg_color="#8e44ad", command=lambda: add_quick_grams(500)).pack(side="right", padx=2)


        self.status_label = ctk.CTkLabel(left_panel, text="", font=ctk.CTkFont(size=14, weight="bold"))
        self.status_label.pack(pady=2)

        search_frame = ctk.CTkFrame(left_panel, fg_color="transparent")
        search_frame.pack(fill="x", padx=10, pady=5)
        ctk.CTkLabel(search_frame, text="بحث سريع بالاسم:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=5)
        self.pos_search_var = ctk.StringVar()
        self.pos_search_var.trace("w", self.pos_live_search)
        self.pos_search_entry = ctk.CTkEntry(search_frame, textvariable=self.pos_search_var, width=160, placeholder_text="اكتب اسم المنتج...")
        self.pos_search_entry.pack(side="right", padx=5)
        ctk.CTkButton(search_frame, text="🔍 بحث متقدم (F1)", font=ctk.CTkFont(weight="bold"), fg_color="#f39c12", hover_color="#d68910", command=self.open_advanced_search).pack(side="left", padx=5)

        tree_frame = ctk.CTkFrame(left_panel, fg_color="transparent")
        tree_frame.pack(expand=True, fill="both", padx=10, pady=5)
        self.pos_tree = ttk.Treeview(tree_frame, columns=('id', 'name', 'price', 'stock'), show='headings')
        self.pos_tree.heading('id', text='الكود')
        self.pos_tree.heading('name', text='اسم المنتج')
        self.pos_tree.heading('price', text='السعر')
        self.pos_tree.heading('stock', text='بالمخزن')
        self.pos_tree.column('id', width=60, anchor='center')
        self.pos_tree.column('name', width=220, anchor='center')
        self.pos_tree.column('price', width=80, anchor='center')
        self.pos_tree.column('stock', width=80, anchor='center')
        self.pos_tree.pack(expand=True, fill="both")
        self.pos_tree.bind("<Double-1>", self.pos_add_from_tree)

        # 3. القسم الأيمن: الفاتورة وإجماليات الحساب والدفع والتحكم
        right_panel = ctk.CTkFrame(self, corner_radius=12, fg_color="#2c3e50")
        right_panel.grid(row=1, column=1, sticky="nsew", padx=(5, 10), pady=(0, 10))

        header_frame = ctk.CTkFrame(right_panel, fg_color="#1f538d", corner_radius=8)
        header_frame.pack(fill="x", padx=10, pady=(10, 5))
        ctk.CTkLabel(header_frame, text="🧾 جدول فاتورة العميل الحالية", font=ctk.CTkFont(size=15, weight="bold"), text_color="white").pack(side="right", padx=15, pady=6)
        ctk.CTkButton(header_frame, text="🗑️ إلغاء", width=65, fg_color="#c0392b", hover_color="#922b21", command=self.clear_cart).pack(side="left", padx=5, pady=4)
        ctk.CTkButton(header_frame, text="⏳ تعليق (F7)", width=85, fg_color="#f39c12", hover_color="#d68910", command=self.hold_invoice).pack(side="left", padx=2, pady=4)
        ctk.CTkButton(header_frame, text="📂 المعلقة (F8)", width=85, fg_color="#2980b9", hover_color="#1f618d", command=self.show_held_invoices).pack(side="left", padx=2, pady=4)

        cart_tree_frame = ctk.CTkFrame(right_panel, fg_color="transparent")
        cart_tree_frame.pack(expand=True, fill="both", padx=10, pady=5)
        self.cart_tree = ttk.Treeview(cart_tree_frame, columns=('id', 'name', 'qty', 'price', 'total'), show='headings', height=7)
        self.cart_tree.heading('id', text='ID') 
        self.cart_tree.heading('name', text='المنتج')
        self.cart_tree.heading('qty', text='الكمية')
        self.cart_tree.heading('price', text='السعر')
        self.cart_tree.heading('total', text='الإجمالي')
        self.cart_tree.column('id', width=0, stretch=False) 
        self.cart_tree.column('name', width=160, anchor='center')
        self.cart_tree.column('qty', width=70, anchor='center')
        self.cart_tree.column('price', width=70, anchor='center')
        self.cart_tree.column('total', width=80, anchor='center')
        self.cart_tree.pack(expand=True, fill="both")
        self.cart_tree.bind("<Double-1>", self.edit_cart_item)

        controls_frame = ctk.CTkFrame(right_panel, fg_color="transparent")
        controls_frame.pack(fill="x", padx=10, pady=5)

        ctk.CTkButton(controls_frame, text="✏️ تعديل الكمية/السعر", width=130, fg_color="#f39c12", hover_color="#d68910", command=self.edit_cart_item).pack(side="left", padx=2)
        ctk.CTkButton(controls_frame, text="❌ حذف المحدد", width=95, fg_color="#c0392b", command=self.remove_selected_item).pack(side="left", padx=2)

        row_pay = ctk.CTkFrame(controls_frame, fg_color="transparent")
        row_pay.pack(side="right")
        ctk.CTkLabel(row_pay, text="الدفع:").pack(side="right", padx=2)
        self.pos_payment_combo = ctk.CTkComboBox(row_pay, values=[], width=110, command=self.calculate_change)
        self.pos_payment_combo.pack(side="right", padx=2)
        
        ctk.CTkLabel(row_pay, text="خصم:").pack(side="right", padx=(8, 2))
        self.pos_discount_var = ctk.StringVar(value="0")
        self.pos_discount_var.trace("w", self.apply_discount)
        ctk.CTkEntry(row_pay, textvariable=self.pos_discount_var, width=50, justify="center").pack(side="right", padx=2)

        # Totals & Cash Calculation
        totals_card = ctk.CTkFrame(right_panel, fg_color="#1e272e", corner_radius=10)
        totals_card.pack(fill="x", padx=10, pady=5)

        self.pos_total_lbl = ctk.CTkLabel(totals_card, text="الإجمالي المطلوب: 0 ج.م", font=ctk.CTkFont(size=20, weight="bold"), text_color="#2ecc71")
        self.pos_total_lbl.pack(pady=4)

        calc_frame = ctk.CTkFrame(totals_card, fg_color="transparent")
        calc_frame.pack(fill="x", pady=4, padx=10)
        ctk.CTkLabel(calc_frame, text="المدفوع:", font=ctk.CTkFont(size=14, weight="bold")).pack(side="right", padx=3)
        self.pos_tendered_var = ctk.StringVar(value="")
        self.pos_tendered_var.trace("w", self.calculate_change)
        self.pos_tendered_entry = ctk.CTkEntry(calc_frame, textvariable=self.pos_tendered_var, width=90, justify="center", font=("Arial", 16, "bold"))
        self.pos_tendered_entry.pack(side="right", padx=3)
        self.pos_tendered_entry.bind("<Return>", lambda e: self.pos_checkout())
        
        self.pos_change_lbl = ctk.CTkLabel(calc_frame, text="الباقي: 0 ج.م", font=ctk.CTkFont(size=15, weight="bold"), text_color="#f39c12")
        self.pos_change_lbl.pack(side="left", padx=5)

        # Checkout & Temp Print Action Bar
        action_btns_frame = ctk.CTkFrame(right_panel, fg_color="transparent")
        action_btns_frame.pack(fill="x", padx=10, pady=(5, 10))

        ctk.CTkButton(action_btns_frame, text="🖨️ طباعة مبدئية", font=ctk.CTkFont(weight="bold"), fg_color="#8e44ad", hover_color="#732d91", height=42, command=self.print_temp_receipt).pack(side="right", expand=True, fill="x", padx=(0, 3))
        ctk.CTkButton(action_btns_frame, text="💾 دفع وطباعة (F5)", font=ctk.CTkFont(size=15, weight="bold"), fg_color="#27ae60", hover_color="#1e8449", height=42, command=self.pos_checkout).pack(side="left", expand=True, fill="x", padx=(3, 0))


    def setup_shortcuts(self):
        top = self.winfo_toplevel()
        top.bind("<F1>", self._shortcut_search)
        top.bind("<F2>", lambda e: self.pos_qty_whole.focus() if self.winfo_ismapped() else None)
        top.bind("<F3>", lambda e: self.pos_qty_grams.focus() if self.winfo_ismapped() else None)
        top.bind("<F4>", lambda e: self.pos_tendered_entry.focus() if self.winfo_ismapped() else None)
        top.bind("<F5>", self._shortcut_checkout)
        top.bind("<F7>", self._shortcut_hold)
        top.bind("<F8>", self._shortcut_show_held)


    def _shortcut_checkout(self, event):
        if self.winfo_ismapped(): self.pos_checkout()
    def _shortcut_hold(self, event):
        if self.winfo_ismapped(): self.hold_invoice()
    def _shortcut_search(self, event):
        if self.winfo_ismapped(): self.open_advanced_search()
    def _shortcut_show_held(self, event):
        if self.winfo_ismapped(): self.show_held_invoices()

    def on_show(self):
        self.setup_shortcuts()
        self.pos_load_products_tree()
        self.load_delivery_boys()
        self.load_payment_methods()
        self.pos_barcode.focus() 

    def show_status(self, msg, color="green"):
        self.status_label.configure(text=msg, text_color=color)
        self.after(2000, lambda: self.status_label.configure(text=""))

    def fetch_customer_data(self, *args):
        phone = self.pos_cust_phone_var.get().strip()
        if len(phone) >= 10:
            self.cursor.execute("SELECT name, address FROM customers WHERE phone=?", (phone,))
            res = self.cursor.fetchone()
            if res:
                self.pos_cust_name.delete(0, 'end')
                self.pos_cust_name.insert(0, res[0] if res[0] else "")
                self.pos_cust_address.delete(0, 'end')
                self.pos_cust_address.insert(0, res[1] if res[1] else "")
                self.show_status("💡 تم جلب بيانات العميل المحفوظة", "#3498db")

    def load_delivery_boys(self):
        self.cursor.execute("SELECT name FROM employees WHERE role IN ('دليفري', 'طيار', 'سائق', 'عامل') OR role LIKE '%دليفري%' OR role LIKE '%طيار%'")
        rows = [r[0] for r in self.cursor.fetchall()]
        if not rows:
            self.cursor.execute("SELECT name FROM employees")
            rows = [r[0] for r in self.cursor.fetchall()]
        delivery_boys = ["بدون توصيل (تيك أواي)"] + rows
        self.pos_delivery_combo.configure(values=delivery_boys)
        if not self.pos_delivery_combo.get() or self.pos_delivery_combo.get() not in delivery_boys:
            self.pos_delivery_combo.set("بدون توصيل (تيك أواي)")

    def quick_add_delivery_boy(self):
        dialog = ctk.CTkInputDialog(text="أدخل اسم الطيار / عامل التوصيل الجديد:", title="إضافة طيار جديد")
        val = dialog.get_input()
        if val and val.strip():
            d_name = val.strip()
            try:
                self.cursor.execute("INSERT INTO employees (name, role, salary, hours) VALUES (?, 'دليفري', 0, 0)", (d_name,))
                self.db.commit()
                self.load_delivery_boys()
                self.pos_delivery_combo.set(d_name)
                messagebox.showinfo("نجاح", f"تم إضافة الطيار ({d_name}) بنجاح وتحديده!")
            except Exception as e:
                messagebox.showerror("خطأ", f"حدث خطأ أثناء الإضافة:\n{e}")


    def load_payment_methods(self):
        self.cursor.execute("SELECT name, fee_percent FROM payment_methods")
        pms = self.cursor.fetchall()
        vals = [f"{pm[0]} ({pm[1]}%)" for pm in pms]
        self.pos_payment_combo.configure(values=vals)
        if vals: self.pos_payment_combo.set(vals[0])

    def pos_load_products_tree(self, search_term=""):
        for item in self.pos_tree.get_children(): self.pos_tree.delete(item)
        query = "SELECT id, name, price, stock FROM products WHERE name LIKE ? OR local_code LIKE ? OR ',' || COALESCE(all_barcodes, '') || ',' LIKE ?"
        s = f'%{search_term}%'
        bc_s = f'%,{search_term},%'
        self.cursor.execute(query, (s, s, bc_s))
        for row in self.cursor.fetchall():
            self.pos_tree.insert("", "end", values=row)

    def pos_live_search(self, *args):
        self.pos_load_products_tree(self.pos_search_var.get())

    def open_advanced_search(self):
        win = ctk.CTkToplevel(self)
        win.title("بحث متقدم عن أصناف الكاشير (F1)")
        win.geometry("680x450")
        win.attributes("-topmost", True)

        search_var = ctk.StringVar()
        ent_search = ctk.CTkEntry(win, textvariable=search_var, placeholder_text="ابحث بالاسم، الباركود المحلي 5 أرقام، أو الباركود الدولي...", font=("Arial", 16))
        ent_search.pack(fill="x", padx=10, pady=10)

        tree = ttk.Treeview(win, columns=('id', 'local_code', 'name', 'price', 'stock'), show='headings')
        tree.heading('id', text='ID')
        tree.heading('local_code', text='كود محلي')
        tree.heading('name', text='الاسم')
        tree.heading('price', text='السعر')
        tree.heading('stock', text='المخزن')
        tree.column('id', width=40, anchor='center')
        tree.column('local_code', width=90, anchor='center')
        tree.column('name', width=220, anchor='center')
        tree.column('price', width=80, anchor='center')
        tree.column('stock', width=80, anchor='center')
        tree.pack(expand=True, fill="both", padx=10, pady=5)

        def do_search(*args):
            term = search_var.get().lower()
            for item in tree.get_children(): tree.delete(item)
            query = "SELECT id, local_code, name, price, stock FROM products WHERE name LIKE ? OR local_code LIKE ? OR barcode LIKE ? OR barcode2 LIKE ? OR barcode3 LIKE ? OR ',' || COALESCE(all_barcodes, '') || ',' LIKE ?"
            s = f"%{term}%"
            bc_s = f"%,{term},%"
            self.cursor.execute(query, (s, s, s, s, s, bc_s))
            for row in self.cursor.fetchall():
                tree.insert("", "end", values=row)

        search_var.trace("w", do_search)
        do_search()

        def select_item(event=None):
            selected = tree.selection()
            if not selected: return
            item = tree.item(selected[0])['values']
            p_id = item[0]
            qty = self._get_qty_input()
            self.cursor.execute("SELECT id, name, price, stock FROM products WHERE id=?", (p_id,))
            prod = self.cursor.fetchone()
            if prod: self._add_to_cart_logic(prod, qty)
            win.destroy()

        tree.bind("<Double-1>", select_item)
        tree.bind("<Return>", select_item)
        ent_search.focus()

    def _get_qty_input(self):
        try:
            whole = float(self.pos_qty_whole.get() or 0)
            grams = float(self.pos_qty_grams.get() or 0)
            return whole + (grams / 1000.0)
        except: return 1.0

    def _reset_qty_inputs(self):
        self.pos_qty_whole.delete(0, 'end'); self.pos_qty_whole.insert(0, "1")
        self.pos_qty_grams.delete(0, 'end'); self.pos_qty_grams.insert(0, "0")
        self.pos_barcode.focus() 
        self.after(20, lambda: self.pos_barcode.select_range(0, 'end'))

    def _on_barcode_key_release(self, event=None):
        if event and event.keysym in ("Return", "Tab", "Up", "Down", "Left", "Right", "Escape"):
            return
        code = self.pos_barcode.get().strip()
        if not code: return
        query = "SELECT name, price, stock FROM products WHERE barcode=? OR local_code=? OR barcode2=? OR barcode3=? OR ',' || COALESCE(all_barcodes, '') || ',' LIKE ?"
        bc_s = f'%,{code},%'
        self.cursor.execute(query, (code, code, code, code, bc_s))
        prod = self.cursor.fetchone()
        if prod:
            self.status_label.configure(text=f"📦 {prod[0]} - السعر: {prod[1]:g} ج.م | المخزن: {prod[2]:g}", text_color="#2ecc71")

    def _on_barcode_return(self, event=None):
        scanned_code = self.pos_barcode.get().strip()
        if scanned_code:
            self.pos_add_by_barcode()
        else:
            self.pos_qty_whole.focus()

    def _on_qty_return(self, event=None):
        scanned_code = self.pos_barcode.get().strip()
        if scanned_code:
            self.pos_add_by_barcode()
        else:
            self.show_status("💡 امسح الباركود أو اكتب كود المنتج أولاً", "#3498db")
            self.pos_barcode.focus()

    def _add_to_cart_logic(self, prod, qty):
        p_id, p_name, p_price, p_stock = prod
        current_qty = self.pos_cart.get(p_id, {}).get('qty', 0.0)
        
        if p_stock >= (current_qty + qty): 
            if p_id in self.pos_cart: self.pos_cart[p_id]['qty'] += qty
            else: self.pos_cart[p_id] = {'name': p_name, 'price': p_price, 'qty': qty}
            self.show_status(f"✅ تمت إضافة ({p_name}) - كمية: {qty:g}", "#2ecc71")
        else:
            if p_id in self.pos_cart: self.pos_cart[p_id]['qty'] += qty
            else: self.pos_cart[p_id] = {'name': p_name, 'price': p_price, 'qty': qty}
            self.show_status(f"⚠️ تمت الإضافة (لكن المخزون لا يكفي: {p_stock})", "#f39c12")
            
        self.update_pos_cart()
        self._reset_qty_inputs()


    def pos_add_by_barcode(self, event=None):
        scanned_code = self.pos_barcode.get().strip()
        if not scanned_code: return
        self.pos_barcode.delete(0, 'end')
        
        qty = self._get_qty_input() 
        barcode_to_search = scanned_code
        if len(scanned_code) == 13 and scanned_code.startswith(("20", "21", "22", "23", "24", "25", "26", "27", "28", "29")):
            barcode_to_search = scanned_code[2:7] 
            qty = int(scanned_code[7:12]) / 1000.0 
            
        query = "SELECT id, name, price, stock FROM products WHERE barcode=? OR local_code=? OR barcode2=? OR barcode3=? OR ',' || COALESCE(all_barcodes, '') || ',' LIKE ?"
        exact_bc_search = f'%,{barcode_to_search},%'
        self.cursor.execute(query, (barcode_to_search, barcode_to_search, barcode_to_search, barcode_to_search, exact_bc_search))
        prod = self.cursor.fetchone()


        
        if prod:
            if qty <= 0: 
                self.show_status("❌ الكمية خطأ!", "#e74c3c")
                self.pos_barcode.focus()
                return
            self._add_to_cart_logic(prod, qty)
        else: 
            self.show_status(f"❌ باركود غير مسجل: {barcode_to_search}", "#e74c3c")
            self.pos_barcode.focus()

    def pos_add_from_tree(self, event):
        selected = self.pos_tree.selection()
        if not selected: return
        p_id = self.pos_tree.item(selected[0])['values'][0]
        qty = self._get_qty_input()
        if qty <= 0: return
        self.cursor.execute("SELECT id, name, price, stock FROM products WHERE id=?", (p_id,))
        prod = self.cursor.fetchone()
        if prod: self._add_to_cart_logic(prod, qty)

    def edit_cart_item(self, event=None):
        selected = self.cart_tree.selection()
        if not selected:
            self.show_status("⚠️ اختر صنفاً من الفاتورة أولاً لتعديله", "#f39c12")
            return
        
        try:
            p_id = int(self.cart_tree.item(selected[0])['values'][0])
        except (IndexError, ValueError):
            return

        item = self.pos_cart.get(p_id)
        if not item: return

        curr_qty = float(item['qty'])
        curr_price = float(item['price'])
        
        whole_val = int(curr_qty)
        grams_val = int(round((curr_qty - whole_val) * 1000))

        edit_win = ctk.CTkToplevel(self)
        edit_win.title(f"تعديل الكمية والوزن - {item['name']}")
        edit_win.geometry("460x460")
        edit_win.attributes("-topmost", True)
        edit_win.resizable(False, False)

        header_frame = ctk.CTkFrame(edit_win, fg_color="#1f538d", corner_radius=10)
        header_frame.pack(fill="x", padx=15, pady=15)
        ctk.CTkLabel(header_frame, text=f"📦 {item['name']}", font=ctk.CTkFont(size=18, weight="bold"), text_color="white").pack(pady=10)

        quick_frame = ctk.CTkFrame(edit_win, fg_color="transparent")
        quick_frame.pack(fill="x", padx=15, pady=5)
        ctk.CTkLabel(quick_frame, text="تعديل سريع:").pack(side="right", padx=5)

        grid_frame = ctk.CTkFrame(edit_win, fg_color="#2b2b2b", corner_radius=10)
        grid_frame.pack(fill="x", padx=15, pady=10)

        ctk.CTkLabel(grid_frame, text="عدد / كيلو:", font=ctk.CTkFont(weight="bold")).grid(row=0, column=0, padx=10, pady=10, sticky="e")
        ent_whole = ctk.CTkEntry(grid_frame, width=100, justify="center", font=("Arial", 16, "bold"))
        ent_whole.insert(0, str(whole_val))
        ent_whole.grid(row=0, column=1, padx=10, pady=10)

        ctk.CTkLabel(grid_frame, text="جرام / كسور:", font=ctk.CTkFont(weight="bold")).grid(row=1, column=0, padx=10, pady=10, sticky="e")
        ent_grams = ctk.CTkEntry(grid_frame, width=100, justify="center", font=("Arial", 16, "bold"))
        ent_grams.insert(0, str(grams_val))
        ent_grams.grid(row=1, column=1, padx=10, pady=10)

        ctk.CTkLabel(grid_frame, text="سعر الوحدة (ج.م):", font=ctk.CTkFont(weight="bold")).grid(row=2, column=0, padx=10, pady=10, sticky="e")
        ent_price = ctk.CTkEntry(grid_frame, width=100, justify="center", font=("Arial", 16, "bold"))
        ent_price.insert(0, f"{curr_price:g}")
        ent_price.grid(row=2, column=1, padx=10, pady=10)

        lbl_live_subtotal = ctk.CTkLabel(edit_win, text=f"الإجمالي الصافي: {curr_qty * curr_price:.2f} ج.م", font=ctk.CTkFont(size=18, weight="bold"), text_color="#2ecc71")
        lbl_live_subtotal.pack(pady=10)

        def calc_live(*args):
            try:
                w = float(ent_whole.get() or 0)
                g = float(ent_grams.get() or 0)
                p = float(ent_price.get() or 0)
                tot_q = w + (g / 1000.0)
                tot_p = tot_q * p
                lbl_live_subtotal.configure(text=f"الإجمالي الصافي: {tot_p:.2f} ج.م")
            except ValueError:
                pass

        ent_whole.bind("<KeyRelease>", calc_live)
        ent_grams.bind("<KeyRelease>", calc_live)
        ent_price.bind("<KeyRelease>", calc_live)

        def adj_qty(delta_w, delta_g=0):
            try:
                w = int(ent_whole.get() or 0) + delta_w
                g = int(ent_grams.get() or 0) + delta_g
                if g >= 1000:
                    w += g // 1000
                    g = g % 1000
                elif g < 0:
                    w -= 1
                    g += 1000
                if w < 0: w = 0
                if g < 0: g = 0
                ent_whole.delete(0, 'end'); ent_whole.insert(0, str(w))
                ent_grams.delete(0, 'end'); ent_grams.insert(0, str(g))
                calc_live()
            except ValueError: pass

        ctk.CTkButton(quick_frame, text="+1 كغم", width=55, fg_color="#27ae60", command=lambda: adj_qty(1, 0)).pack(side="right", padx=2)
        ctk.CTkButton(quick_frame, text="-1 كغم", width=55, fg_color="#c0392b", command=lambda: adj_qty(-1, 0)).pack(side="right", padx=2)
        ctk.CTkButton(quick_frame, text="+250غ", width=55, fg_color="#2980b9", command=lambda: adj_qty(0, 250)).pack(side="right", padx=2)
        ctk.CTkButton(quick_frame, text="+500غ", width=55, fg_color="#8e44ad", command=lambda: adj_qty(0, 500)).pack(side="right", padx=2)

        def save_edits(event=None):
            try:
                w = float(ent_whole.get() or 0)
                g = float(ent_grams.get() or 0)
                new_qty = w + (g / 1000.0)
                new_price = float(ent_price.get() or 0)
                if new_qty <= 0 or new_price < 0: raise ValueError
                
                self.pos_cart[p_id]['qty'] = new_qty
                self.pos_cart[p_id]['price'] = new_price
                self.update_pos_cart()
                
                self.show_status(f"🔄 تم تعديل ({item['name']}) -> {new_qty:g}", "#2ecc71")
                edit_win.destroy()
                self.pos_barcode.focus()
            except ValueError:
                messagebox.showerror("خطأ", "برجاء التأكد من إدخال الأرقام بشكل صحيح!", parent=edit_win)

        ent_whole.bind("<Return>", lambda e: ent_grams.focus())
        ent_grams.bind("<Return>", lambda e: ent_price.focus())
        ent_price.bind("<Return>", save_edits)

        ctk.CTkButton(edit_win, text="✔️ حفظ التعديل (Enter)", font=ctk.CTkFont(weight="bold"), fg_color="#27ae60", hover_color="#1e8449", height=40, command=save_edits).pack(pady=10, fill="x", padx=30)


    def remove_selected_item(self):
        selected = self.cart_tree.selection()
        if not selected: return
        p_id = self.cart_tree.item(selected[0])['values'][0] 
        if p_id in self.pos_cart:
            del self.pos_cart[p_id]
            self.update_pos_cart()
        self.pos_barcode.focus()

    def apply_discount(self, *args):
        try: self.pos_discount_val = float(self.pos_discount_var.get() or 0)
        except: self.pos_discount_val = 0.0
        self.calculate_change()

    def update_pos_cart(self):
        for item in self.cart_tree.get_children(): self.cart_tree.delete(item)
        self.pos_total = 0.0
        for p_id, item in self.pos_cart.items():
            subtotal = item['price'] * item['qty']
            self.pos_total += subtotal
            self.cart_tree.insert("", "end", values=(p_id, item['name'], f"{item['qty']:g}", f"{item['price']:g}", f"{subtotal:g}"))
        self.calculate_change()

    def calculate_change(self, *args):
        try:
            tendered_str = self.pos_tendered_var.get()
            tendered = float(tendered_str) if tendered_str else 0.0
            
            # استخراج سعر التوصيل
            try: d_fee = float(self.pos_delivery_fee_var.get() or 0)
            except: d_fee = 0.0
            
            net_total = self.pos_total - self.pos_discount_val
            if net_total < 0: net_total = 0.0
            
            pm_str = self.pos_payment_combo.get()
            fee_percent = 0.0
            if pm_str:
                try: fee_percent = float(pm_str.split("(")[1].replace("%)", ""))
                except: pass
            
            fee_amount = net_total * (fee_percent / 100.0)
            
            shop_total = net_total + fee_amount
            grand_total_for_customer = shop_total + d_fee
            
            if d_fee > 0:
                self.pos_total_lbl.configure(text=f"المطلوب: {grand_total_for_customer:g} ج.م (شامل التوصيل)")
            else:
                self.pos_total_lbl.configure(text=f"الإجمالي المطلوب: {grand_total_for_customer:g} ج.م")
            
            if tendered_str:
                change = tendered - grand_total_for_customer
                if change >= 0: self.pos_change_lbl.configure(text=f"الباقي: {change:g} ج.م", text_color="#e74c3c")
                else: self.pos_change_lbl.configure(text="الباقي: 0 ج.م", text_color="gray")
            else:
                self.pos_change_lbl.configure(text="الباقي: 0 ج.م", text_color="gray")
        except ValueError: pass

    def clear_cart(self, ask=True):
        if ask and self.pos_cart and not messagebox.askyesno("تأكيد", "إلغاء الفاتورة الحالية؟"): return
        self.current_viewed_invoice = None
        self.pos_cart.clear()
        self.pos_discount_var.set("0")
        self.pos_delivery_fee_var.set("0")
        try: self.pos_tendered_var.set("")
        except: pass
        self.pos_cust_name.delete(0, 'end')
        self.pos_cust_phone.delete(0, 'end')
        self.pos_cust_address.delete(0, 'end') 
        self.pos_delivery_combo.set("بدون توصيل (تيك أواي)")
        self._reset_qty_inputs()
        self.update_pos_cart()
        if hasattr(self, 'lbl_pos_header'):
            self.lbl_pos_header.configure(text="🛒 شاشة البيع والكاشير المباشر", text_color="white")


    def pos_checkout(self):
        if not self.pos_cart: 
            self.show_status("❌ الفاتورة فارغة!", "#e74c3c")
            self.pos_barcode.focus()
            return
            
        c_name = self.pos_cust_name.get().strip()
        c_phone = self.pos_cust_phone.get().strip()
        c_address = self.pos_cust_address.get().strip()
        d_person = self.pos_delivery_combo.get()
        
        try: d_fee = float(self.pos_delivery_fee_var.get() or 0)
        except: d_fee = 0.0
        
        date_now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        
        self.cursor.execute("SELECT key, value FROM settings WHERE key IN ('store_name', 'store_phone', 'store_address', 'default_cashier')")
        settings = dict(self.cursor.fetchall())
        cashier_name = settings.get('default_cashier', 'الكاشير')

        net_total = self.pos_total - self.pos_discount_val
        if net_total < 0: net_total = 0.0
        
        pm_str = self.pos_payment_combo.get()
        fee_percent = 0.0
        method_name = "كاش"
        if pm_str:
            try: 
                method_name = pm_str.split(" (")[0]
                fee_percent = float(pm_str.split("(")[1].replace("%)", ""))
            except: pass
            
        fee_amount = net_total * (fee_percent / 100.0)
        shop_final_total = net_total + fee_amount 
        grand_total_for_customer = shop_final_total + d_fee 
        
        try: tendered = float(self.pos_tendered_var.get())
        except: tendered = grand_total_for_customer

        if c_phone:
            self.cursor.execute("INSERT OR REPLACE INTO customers (phone, name, address) VALUES (?, ?, ?)", (c_phone, c_name, c_address))

        if self.current_viewed_invoice is not None:
            # 1. التراجع عن أثر المخزون السابق للفاتورة المعنية للتعديل الآمن
            self.cursor.execute("SELECT product_id, qty FROM sale_items WHERE sale_id=?", (self.current_viewed_invoice,))
            for o_pid, o_qty in self.cursor.fetchall():
                self.cursor.execute("UPDATE products SET stock = stock + ? WHERE id=?", (o_qty, o_pid))

            # 2. تحديث الفاتورة ومسح العناصر القديمة
            self.cursor.execute("UPDATE sales SET total=?, date=?, customer=?, phone=?, address=?, delivery_person=?, status='مكتملة', payment_method=?, payment_fee=?, discount=?, delivery_fee=? WHERE id=?",
                                (shop_final_total, date_now, c_name, c_phone, c_address, d_person, method_name, fee_amount, self.pos_discount_val, d_fee, self.current_viewed_invoice))
            sale_id = self.current_viewed_invoice
            self.cursor.execute("DELETE FROM sale_items WHERE sale_id=?", (sale_id,))
        else:
            # إنشاء فاتورة مبيعات جديدة
            self.cursor.execute("INSERT INTO sales (total, date, customer, phone, address, delivery_person, status, payment_method, payment_fee, discount, delivery_fee) VALUES (?, ?, ?, ?, ?, ?, 'مكتملة', ?, ?, ?, ?)", 
                                (shop_final_total, date_now, c_name, c_phone, c_address, d_person, method_name, fee_amount, self.pos_discount_val, d_fee))
            sale_id = self.cursor.lastrowid
        
        formatted_items = []
        for p_id, item in self.pos_cart.items():
            self.cursor.execute("INSERT INTO sale_items (sale_id, product_id, qty) VALUES (?, ?, ?)", (sale_id, p_id, item['qty']))
            self.cursor.execute("UPDATE products SET stock = stock - ? WHERE id=?", (item['qty'], p_id))
            formatted_items.append({"name": item['name'], "qty": float(item['qty']), "price": float(item['price'])})
            
        self.db.commit()

        # إضافة المعاملة لطابور المزامنة المركزية وتشغيل المزامنة الفورية
        try:
            self.sync_mgr.add_to_queue('push_sale', 'sale', sale_id, {
                'local_sale_id': sale_id,
                'total': shop_final_total,
                'date': date_now,
                'customer': c_name,
                'phone': c_phone,
                'address': c_address,
                'delivery_person': d_person,
                'payment_method': method_name,
                'delivery_fee': d_fee,
                'items': formatted_items
            })
            self.sync_mgr.trigger_instant_sync()
        except Exception as sync_e:
            print(f"Sync trigger error: {sync_e}")

        invoice_data = {
            "shop_name_ar": settings.get('store_name', 'سوبر ماركت المنزل السوري'),
            "shop_name_en": "Salas POS",
            "invoice_id": str(sale_id),
            "pay_type": method_name,
            "payment_fee": fee_amount,
            "cashier_name": cashier_name,
            "customer_name": c_name,
            "customer_phone": c_phone,
            "customer_address": c_address,
            "delivery_person": d_person,
            "shop_address": settings.get('store_address', ''),
            "shop_phone": settings.get('store_phone', ''),
            "delivery_fee": d_fee, 
            "paid": tendered
        }
        
        try:
            from receipt_printer import print_salas_receipt
            print_salas_receipt(invoice_data, formatted_items)
            self.show_status(f"🖨️ تم الدفع/التحديث والطباعة (رقم {sale_id})", "#27ae60")
        except Exception as e:
            self.show_status("⚠️ تم الحفظ لكن الطابعة بها خطأ!", "#f39c12")
        
        self.clear_cart(ask=False)
        self.pos_load_products_tree()
        self.pos_barcode.focus() 

    def print_temp_receipt(self):
        if not self.pos_cart: 
            self.show_status("❌ الفاتورة فارغة!", "#e74c3c")
            self.pos_barcode.focus()
            return
        
        c_name = self.pos_cust_name.get().strip()
        c_phone = self.pos_cust_phone.get().strip()
        c_address = self.pos_cust_address.get().strip()
        
        try: d_fee = float(self.pos_delivery_fee_var.get() or 0)
        except: d_fee = 0.0
        
        self.cursor.execute("SELECT key, value FROM settings WHERE key IN ('store_name', 'store_phone', 'store_address', 'default_cashier')")
        settings = dict(self.cursor.fetchall())
        cashier_name = settings.get('default_cashier', 'الكاشير')

        net_total = self.pos_total - self.pos_discount_val
        if net_total < 0: net_total = 0.0
        
        pm_str = self.pos_payment_combo.get()
        fee_percent = 0.0
        method_name = "كاش"
        if pm_str:
            try: 
                method_name = pm_str.split(" (")[0]
                fee_percent = float(pm_str.split("(")[1].replace("%)", ""))
            except: pass
            
        fee_amount = net_total * (fee_percent / 100.0)
        shop_final_total = net_total + fee_amount 
        grand_total_for_customer = shop_final_total + d_fee 
        
        formatted_items = []
        for p_id, item in self.pos_cart.items():
            formatted_items.append({"name": item['name'], "qty": float(item['qty']), "price": float(item['price'])})

        invoice_data = {
            "shop_name_ar": settings.get('store_name', 'سوبر ماركت'),
            "shop_name_en": "Salas POS",
            "invoice_id": "***", 
            "status": "مؤقتة",   
            "pay_type": method_name,
            "payment_fee": fee_amount,
            "cashier_name": cashier_name,
            "customer_name": c_name,
            "customer_phone": c_phone,
            "customer_address": c_address,
            "delivery_person": self.pos_delivery_combo.get(),
            "shop_address": settings.get('store_address', ''),
            "shop_phone": settings.get('store_phone', ''),
            "delivery_fee": d_fee,
            "paid": grand_total_for_customer 
        }
        
        try:
            from receipt_printer import print_salas_receipt
            print_salas_receipt(invoice_data, formatted_items)
            self.show_status("🖨️ تم طباعة فاتورة المراجعة المبدئية", "#3498db")
        except Exception as e:
            self.show_status("⚠️ خطأ في الطابعة!", "#f39c12")
        
        self.pos_barcode.focus()

    def pos_show_invoice(self, inv_id):
        if not inv_id: return
        try:
            self.cursor.execute("SELECT id, total, date, status, customer, phone, payment_method, payment_fee, discount, address, delivery_fee FROM sales WHERE id=?", (inv_id,))
            sale = self.cursor.fetchone()
        except sqlite3.OperationalError:
            self.cursor.execute("SELECT id, total, date, status, customer, phone FROM sales WHERE id=?", (inv_id,))
            old_sale = self.cursor.fetchone()
            sale = (*old_sale, 'كاش', 0.0, 0.0, '', 0.0) if old_sale else None

        if not sale: return messagebox.showerror("خطأ", "لا توجد فاتورة بهذا الرقم")
        
        self.current_viewed_invoice = sale[0]
        self.pos_view_id.delete(0, 'end')
        self.pos_view_id.insert(0, str(sale[0]))

        # تحميل الأصناف إلى السلة للتصفح والتعديل والطباعة
        self.pos_cart.clear()
        self.cursor.execute("SELECT s.product_id, p.name, s.qty, p.price FROM sale_items s JOIN products p ON s.product_id = p.id WHERE s.sale_id = ?", (sale[0],))
        items = self.cursor.fetchall()
        for item in items:
            self.pos_cart[item[0]] = {
                'name': item[1],
                'qty': float(item[2]),
                'price': float(item[3])
            }

        # ملء بيانات العميل والدليفري
        if sale[5]:
            self.pos_cust_phone_var.set(str(sale[5]))
        if sale[4]:
            self.pos_cust_name.delete(0, 'end')
            self.pos_cust_name.insert(0, str(sale[4]))
        c_addr = sale[9] if len(sale) > 9 and sale[9] else ""
        if c_addr:
            self.pos_cust_address.delete(0, 'end')
            self.pos_cust_address.insert(0, str(c_addr))

        disc = float(sale[8] if len(sale) > 8 and sale[8] else 0.0)
        self.pos_discount_var.set(f"{disc:g}")

        d_fee = float(sale[10] if len(sale) > 10 and sale[10] else 0.0)
        self.pos_delivery_fee_var.set(f"{d_fee:g}")

        self.update_pos_cart()

        if hasattr(self, 'lbl_pos_header'):
            self.lbl_pos_header.configure(text=f"🧾 فاتورة مبيعات مسجلة رقم #{sale[0]} ({sale[2]})", text_color="#f1c40f")

        try:
            self.cursor.execute("SELECT key, value FROM settings WHERE key IN ('store_name', 'store_phone', 'store_address', 'default_cashier')")
            settings = dict(self.cursor.fetchall())
            method_name = sale[6] if len(sale) > 6 and sale[6] else "كاش"
            fee_amount = float(sale[7] if len(sale) > 7 and sale[7] else 0.0)
            shop_tot = sale[1]
            grand_tot = shop_tot + d_fee

            invoice_data = {
                "shop_name_ar": settings.get('store_name', 'سوبر ماركت'),
                "shop_name_en": "Salas POS",
                "invoice_id": str(sale[0]),
                "pay_type": method_name,
                "payment_fee": fee_amount,
                "cashier_name": settings.get('default_cashier', 'الكاشير'),
                "customer_name": sale[4],
                "customer_phone": sale[5],
                "customer_address": c_addr,
                "shop_address": settings.get('store_address', ''),
                "shop_phone": settings.get('store_phone', ''),
                "delivery_fee": d_fee,
                "paid": grand_tot
            }
            formatted_items = [{"name": it[1], "qty": float(it[2]), "price": float(it[3])} for it in items]
            
            from receipt_printer import print_salas_receipt
            print_salas_receipt(invoice_data, formatted_items)
            self.show_status(f"📄 تم فتح وتجهيز الفاتورة رقم #{sale[0]} للتعديل أو الطباعة", "#3498db")
        except Exception as e:
            self.show_status(f"💡 تم عرض الفاتورة رقم #{sale[0]}", "#3498db")

    def pos_nav_invoice(self, direction):
        curr = self.current_viewed_invoice
        if curr is None: self.cursor.execute("SELECT MAX(id) FROM sales")
        else:
            op, order = ("<", "DESC") if direction == -1 else (">", "ASC")
            self.cursor.execute(f"SELECT id FROM sales WHERE id {op} ? ORDER BY id {order} LIMIT 1", (curr,))
        res = self.cursor.fetchone()
        if res and res[0]: self.pos_show_invoice(res[0])

        if curr is None: self.cursor.execute("SELECT MAX(id) FROM sales")
        else:
            op, order = ("<", "DESC") if direction == -1 else (">", "ASC")
            self.cursor.execute(f"SELECT id FROM sales WHERE id {op} ? ORDER BY id {order} LIMIT 1", (curr,))
        res = self.cursor.fetchone()
        if res and res[0]: self.pos_show_invoice(res[0])

    def hold_invoice(self):
        if not self.pos_cart: return self.show_status("❌ لا توجد منتجات لتعليقها!", "#e74c3c")
        
        try: d_fee = float(self.pos_delivery_fee_var.get() or 0)
        except: d_fee = 0.0
        
        note = f"فاتورة للعميل: {self.pos_cust_name.get() or 'غير معروف'}"
        date_now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        self.cursor.execute("INSERT INTO temp_invoices (note, date, customer, phone, address, delivery_person, discount, payment_method, delivery_fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            (note, date_now, self.pos_cust_name.get(), self.pos_cust_phone.get(), self.pos_cust_address.get(), self.pos_delivery_combo.get(), self.pos_discount_val, self.pos_payment_combo.get(), d_fee))
        temp_id = self.cursor.lastrowid
        for p_id, item in self.pos_cart.items():
            self.cursor.execute("INSERT INTO temp_invoice_items (temp_id, product_id, name, price, qty) VALUES (?, ?, ?, ?, ?)", (temp_id, p_id, item['name'], item['price'], item['qty']))
        self.db.commit()
        self.show_status(f"⏳ تم تعليق الفاتورة رقم ({temp_id})", "#f39c12")
        self.clear_cart(ask=False)
        self.pos_barcode.focus()

    def show_held_invoices(self):
        self.cursor.execute("SELECT id, date, note, customer FROM temp_invoices ORDER BY id DESC")
        held_invs = self.cursor.fetchall()
        if not held_invs: return messagebox.showinfo("تنبيه", "لا توجد أي فواتير معلقة حالياً.")
        
        top = ctk.CTkToplevel(self)
        top.title("الفواتير المعلقة")
        top.geometry("600x400")
        top.attributes("-topmost", True)
        
        tree = ttk.Treeview(top, columns=('id', 'date', 'note', 'customer'), show='headings')
        tree.heading('id', text='رقم'); tree.heading('date', text='التاريخ والوقت'); tree.heading('note', text='ملاحظة'); tree.heading('customer', text='العميل')
        tree.column('id', width=50, anchor='center'); tree.column('date', width=150, anchor='center'); tree.column('customer', width=100, anchor='center')
        for inv in held_invs: tree.insert("", "end", values=inv)
        tree.pack(expand=True, fill="both", padx=10, pady=10)
        
        def restore_invoice(event):
            selected = tree.selection()
            if not selected: return
            temp_id = tree.item(selected[0])['values'][0]
            if self.pos_cart and not messagebox.askyesno("تحذير", "الفاتورة الحالية بالكاشير سيتم مسحها لاسترجاع الفاتورة المعلقة. متابعة؟"): return
            self.clear_cart(ask=False)
            
            try:
                self.cursor.execute("SELECT customer, phone, delivery_person, discount, payment_method, address, delivery_fee FROM temp_invoices WHERE id=?", (temp_id,))
                inv_data = self.cursor.fetchone()
                if inv_data:
                    self.pos_cust_name.delete(0, 'end')
                    self.pos_cust_name.insert(0, inv_data[0] if inv_data[0] else "")
                    self.pos_cust_phone.delete(0, 'end')
                    self.pos_cust_phone.insert(0, inv_data[1] if inv_data[1] else "")
                    if inv_data[2]: self.pos_delivery_combo.set(inv_data[2])
                    self.pos_discount_var.set(str(inv_data[3]))
                    if inv_data[4]: self.pos_payment_combo.set(inv_data[4])
                    self.pos_cust_address.delete(0, 'end')
                    self.pos_cust_address.insert(0, inv_data[5] if len(inv_data)>5 and inv_data[5] else "")
                    self.pos_delivery_fee_var.set(str(inv_data[6] if len(inv_data)>6 and inv_data[6] else 0.0))

            except sqlite3.OperationalError: pass
            
            self.cursor.execute("SELECT product_id, name, price, qty FROM temp_invoice_items WHERE temp_id=?", (temp_id,))
            for item in self.cursor.fetchall(): self.pos_cart[item[0]] = {'name': item[1], 'price': item[2], 'qty': item[3]}
            self.update_pos_cart()
            
            self.cursor.execute("DELETE FROM temp_invoices WHERE id=?", (temp_id,))
            self.cursor.execute("DELETE FROM temp_invoice_items WHERE temp_id=?", (temp_id,))
            self.db.commit()
            top.destroy()
            self.pos_barcode.focus()

        tree.bind("<Double-1>", restore_invoice)