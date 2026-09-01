import customtkinter as ctk
import tkinter.messagebox as messagebox
from tkinter import ttk, filedialog
import datetime
import sqlite3
import os
from receipt_printer import print_purchase_receipt

class SuppliersPage(ctk.CTkFrame):
    def __init__(self, parent, db_conn, app):
        super().__init__(parent, fg_color="transparent")
        self.db = db_conn
        self.cursor = self.db.cursor()
        self.app = app
        
        self.buy_cart = {}
        self.buy_total = 0.0
        self.buy_discount_val = 0.0
        self.current_edit_sup_id = None
        self.current_purchase_id = None
        self.all_suppliers_list = []
        
        self.setup_ui()


    def setup_shortcuts(self):
        top = self.winfo_toplevel()
        top.bind("<F2>", self._shortcut_add_new_product)
        top.bind("<F3>", self._shortcut_fast_search)
        top.bind("<F7>", self._shortcut_edit_price)
        top.bind("<F5>", self._shortcut_save_purchase)

    def setup_ui(self):
        header_card = ctk.CTkFrame(self, fg_color="#1e272e", corner_radius=12)
        header_card.pack(fill="x", padx=15, pady=(10, 5))
        ctk.CTkLabel(header_card, text="🤝 إدارة الموردين والمشتريات والتقارير", font=ctk.CTkFont(size=22, weight="bold"), text_color="#3498db").pack(pady=12)
        
        self.sup_tabs = ctk.CTkTabview(self, corner_radius=12)
        self.sup_tabs.pack(expand=True, fill="both", padx=15, pady=(5, 15))
        
        self.sup_tabs.add("فاتورة مشتريات (استلام بضاعة)")
        self.sup_tabs.add("إدارة الموردين والسداد")
        self.sup_tabs.add("تقرير حسابات الموردين")
        self.sup_tabs.add("مرتجعات المشتريات (إلغاء فاتورة)")
        
        self.setup_purchase_tab(self.sup_tabs.tab("فاتورة مشتريات (استلام بضاعة)"))
        self.setup_manage_suppliers_tab(self.sup_tabs.tab("إدارة الموردين والسداد"))
        self.setup_report_tab(self.sup_tabs.tab("تقرير حسابات الموردين"))
        self.setup_returns_tab(self.sup_tabs.tab("مرتجعات المشتريات (إلغاء فاتورة)"))

    def on_show(self):
        self.setup_shortcuts()
        self.load_suppliers()

    # ==========================================
    # إعدادات الاختصارات (Shortcuts)
    # ==========================================
    def setup_shortcuts(self):
        top = self.winfo_toplevel()
        top.bind("<F2>", self._shortcut_add_new_product)
        top.bind("<F3>", self._shortcut_fast_search)
        top.bind("<F7>", self._shortcut_edit_price)
        top.bind("<F5>", self._shortcut_save_purchase)

    def _shortcut_fast_search(self, event):
        if self.winfo_ismapped(): 
            self.open_fast_search_popup()

    def _shortcut_add_new_product(self, event):
        if self.winfo_ismapped(): 
            self.popup_add_new_product()

    def _shortcut_edit_price(self, event):
        if self.winfo_ismapped(): 
            self.popup_edit_price()

    def _shortcut_save_purchase(self, event):
        if self.winfo_ismapped():
            self.buy_checkout()

    # ==========================================
    # 1. كاشير استلام البضاعة (المطور بجدول تفاعلي)
    # ==========================================
    # 1. كاشير استلام البضاعة (المطور فائق السرعة)
    # ==========================================
    def setup_purchase_tab(self, tab):
        # 1. الشريط العلوي: تصفح الفواتير + المورد + الأزرار الإجرائية السريعة
        top_card = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        top_card.pack(fill="x", padx=10, pady=(10, 5))

        # السطر الأول: تصفح وتعديل وطباعة فواتير المشتريات
        row_nav = ctk.CTkFrame(top_card, fg_color="#1e272e", corner_radius=8)
        row_nav.pack(fill="x", padx=10, pady=(8, 4))

        ctk.CTkButton(row_nav, text="◀️ السابقة", font=ctk.CTkFont(weight="bold"), width=90, fg_color="#34495e", hover_color="#2c3e50", height=32, command=lambda: self.navigate_purchase_invoice('prev')).pack(side="right", padx=3, pady=4)
        ctk.CTkButton(row_nav, text="▶️ التالية", font=ctk.CTkFont(weight="bold"), width=90, fg_color="#34495e", hover_color="#2c3e50", height=32, command=lambda: self.navigate_purchase_invoice('next')).pack(side="right", padx=3, pady=4)
        ctk.CTkButton(row_nav, text="🆕 جديدة", font=ctk.CTkFont(weight="bold"), width=90, fg_color="#16a085", hover_color="#117864", height=32, command=self.new_purchase_invoice).pack(side="right", padx=3, pady=4)
        ctk.CTkButton(row_nav, text="✏️ تعديل الفاتورة", font=ctk.CTkFont(weight="bold"), width=115, fg_color="#e67e22", hover_color="#d35400", height=32, command=self.edit_loaded_purchase_invoice).pack(side="right", padx=3, pady=4)
        ctk.CTkButton(row_nav, text="🖨️ طباعة الفاتورة", font=ctk.CTkFont(weight="bold"), width=120, fg_color="#2980b9", hover_color="#1f618d", height=32, command=self.print_current_purchase_invoice).pack(side="left", padx=5, pady=4)

        # السطر الثاني: اختيار المورد وأزرار الأصناف
        row_top = ctk.CTkFrame(top_card, fg_color="transparent")
        row_top.pack(fill="x", padx=12, pady=(2, 8))

        ctk.CTkLabel(row_top, text="المورد:", font=ctk.CTkFont(size=14, weight="bold")).pack(side="right", padx=5)
        
        self.buy_sup_search_var = ctk.StringVar()
        self.buy_sup_search_var.trace("w", self.filter_buy_suppliers)
        self.buy_sup_search = ctk.CTkEntry(row_top, textvariable=self.buy_sup_search_var, placeholder_text="🔍 بحث مورد...", width=120)
        self.buy_sup_search.pack(side="right", padx=5)
        
        self.sup_buy_combo = ctk.CTkComboBox(row_top, values=[], width=220)
        self.sup_buy_combo.pack(side="right", padx=5)

        ctk.CTkButton(row_top, text="➕ منتج جديد (F2)", font=ctk.CTkFont(weight="bold"), width=120, fg_color="#8e44ad", hover_color="#732d91", height=35, command=self.popup_add_new_product).pack(side="left", padx=4)
        ctk.CTkButton(row_top, text="📝 تعديل صنف (F7)", font=ctk.CTkFont(weight="bold"), width=120, fg_color="#f39c12", hover_color="#d68910", height=35, command=self.popup_edit_price).pack(side="left", padx=4)
        ctk.CTkButton(row_top, text="🔍 بحث ذكي (F3)", font=ctk.CTkFont(weight="bold"), width=115, fg_color="#2980b9", hover_color="#1f618d", height=35, command=self.open_fast_search_popup).pack(side="left", padx=4)

        # 2. شريط إدخال الأصناف الأفقي فائق السرعة
        entry_card = ctk.CTkFrame(tab, corner_radius=12, fg_color="#1e272e")
        entry_card.pack(fill="x", padx=10, pady=5)

        row_entry = ctk.CTkFrame(entry_card, fg_color="transparent")
        row_entry.pack(fill="x", padx=12, pady=10)

        ctk.CTkLabel(row_entry, text="الباركود:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(5, 2))
        self.buy_barcode = ctk.CTkEntry(row_entry, placeholder_text="امسح أو اكتب...", width=140, justify="center", font=("Arial", 14))
        self.buy_barcode.pack(side="right", padx=5)
        self.buy_barcode.bind("<Return>", self.buy_fetch_product)

        self.lbl_selected_prod = ctk.CTkLabel(row_entry, text="لم يتم اختيار منتج", text_color="#f39c12", font=ctk.CTkFont(size=14, weight="bold"), width=180, anchor="center")
        self.lbl_selected_prod.pack(side="right", padx=10)

        ctk.CTkLabel(row_entry, text="التكلفة:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(10, 2))
        self.buy_cost = ctk.CTkEntry(row_entry, width=80, justify="center", font=("Arial", 14, "bold"))
        self.buy_cost.pack(side="right", padx=5)

        ctk.CTkLabel(row_entry, text="عدد/كغم:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(10, 2))
        self.buy_qty = ctk.CTkEntry(row_entry, width=70, justify="center", font=("Arial", 14, "bold"))
        self.buy_qty.pack(side="right", padx=5)

        ctk.CTkLabel(row_entry, text="جرام:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(10, 2))
        self.buy_grams = ctk.CTkEntry(row_entry, width=65, justify="center", font=("Arial", 14, "bold"), placeholder_text="0")
        self.buy_grams.pack(side="right", padx=5)

        def add_quick_grams(g):
            try:
                curr_g = float(self.buy_grams.get() or 0)
                self.buy_grams.delete(0, 'end')
                self.buy_grams.insert(0, str(int(curr_g + g)))
            except ValueError:
                self.buy_grams.delete(0, 'end')
                self.buy_grams.insert(0, str(g))

        ctk.CTkButton(row_entry, text="+250غ", width=48, fg_color="#2980b9", command=lambda: add_quick_grams(250)).pack(side="right", padx=2)
        ctk.CTkButton(row_entry, text="+500غ", width=48, fg_color="#8e44ad", command=lambda: add_quick_grams(500)).pack(side="right", padx=2)

        ctk.CTkButton(row_entry, text="➕ إضافة (Enter)", font=ctk.CTkFont(weight="bold"), fg_color="#27ae60", hover_color="#1e8449", height=38, width=120, command=self.buy_add_item).pack(side="left", padx=5)

        self.buy_cost.bind("<Return>", lambda e: self.buy_qty.focus())
        self.buy_qty.bind("<Return>", lambda e: self.buy_grams.focus())
        self.buy_grams.bind("<Return>", lambda e: self.buy_add_item())

        # 3. جدول فاتورة المشتريات المباشر
        cart_card = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        cart_card.pack(expand=True, fill="both", padx=10, pady=5)

        header_cart = ctk.CTkFrame(cart_card, fg_color="#1f538d", corner_radius=8)
        header_cart.pack(fill="x", padx=10, pady=(10, 5))
        self.lbl_cart_header = ctk.CTkLabel(header_cart, text="🧾 فاتورة المشتريات (استلام بضاعة جديد)", font=ctk.CTkFont(size=15, weight="bold"), text_color="white")
        self.lbl_cart_header.pack(side="right", padx=15, pady=6)


        tree_frame = ctk.CTkFrame(cart_card, fg_color="transparent")
        tree_frame.pack(expand=True, fill="both", padx=10, pady=5)

        self.buy_tree = ttk.Treeview(tree_frame, columns=('id', 'name', 'cost', 'qty', 'total'), show='headings', height=7)
        self.buy_tree.heading('id', text='ID')
        self.buy_tree.heading('name', text='المنتج')
        self.buy_tree.heading('cost', text='التكلفة للوحدة')
        self.buy_tree.heading('qty', text='الكمية / الوزن')
        self.buy_tree.heading('total', text='الإجمالي الصافي')

        self.buy_tree.column('id', width=60, anchor='center')
        self.buy_tree.column('name', width=260, anchor='center')
        self.buy_tree.column('cost', width=120, anchor='center')
        self.buy_tree.column('qty', width=120, anchor='center')
        self.buy_tree.column('total', width=140, anchor='center')
        self.buy_tree.pack(expand=True, fill="both")
        self.buy_tree.bind("<Double-1>", self.edit_buy_cart_item)

        controls_frame = ctk.CTkFrame(cart_card, fg_color="transparent")
        controls_frame.pack(fill="x", padx=10, pady=(5, 10))

        ctk.CTkButton(controls_frame, text="🗑️ تفريغ الكل", width=100, fg_color="#c0392b", hover_color="#922b21", command=self.clear_buy_cart).pack(side="left", padx=4)
        ctk.CTkButton(controls_frame, text="❌ حذف المحدد", width=100, fg_color="#e67e22", hover_color="#d35400", command=self.buy_remove_selected).pack(side="left", padx=4)
        ctk.CTkButton(controls_frame, text="✏️ تعديل الكمية/الوزن", width=130, fg_color="#f39c12", hover_color="#d68910", command=self.edit_buy_cart_item).pack(side="left", padx=4)

        self.buy_discount_var = ctk.StringVar(value="0")
        self.buy_discount_var.trace("w", self.apply_buy_discount)
        ctk.CTkEntry(controls_frame, textvariable=self.buy_discount_var, width=80, justify="center", font=("Arial", 13, "bold")).pack(side="right", padx=5)
        ctk.CTkLabel(controls_frame, text="خصم المورد (ج.م):", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=5)

        # 4. شريط الدفع والحفظ المالي السفلي
        checkout_card = ctk.CTkFrame(tab, corner_radius=12, fg_color="#1e272e")
        checkout_card.pack(fill="x", padx=10, pady=(5, 10))

        row_checkout = ctk.CTkFrame(checkout_card, fg_color="transparent")
        row_checkout.pack(fill="x", padx=12, pady=10)

        ctk.CTkLabel(row_checkout, text="المدفوع كاش الآن (من الخزينة):", font=ctk.CTkFont(size=14, weight="bold")).pack(side="right", padx=5)
        self.buy_paid = ctk.CTkEntry(row_checkout, width=130, justify="center", font=("Arial", 16, "bold"))
        self.buy_paid.pack(side="right", padx=5)
        self.buy_paid.bind("<Return>", lambda e: self.buy_checkout())

        ctk.CTkButton(row_checkout, text="💾 حفظ الفاتورة وتحديث المخزن والمديونية (F5)", font=ctk.CTkFont(size=15, weight="bold"), fg_color="#27ae60", hover_color="#1e8449", height=45, command=self.buy_checkout).pack(side="left", padx=5, expand=True, fill="x")

        self.buy_total_lbl = ctk.CTkLabel(row_checkout, text="الإجمالي المطلوب: 0 ج.م", font=ctk.CTkFont(size=20, weight="bold"), text_color="#2ecc71")
        self.buy_total_lbl.pack(side="right", padx=20)



    # ==========================================
    # دالة البحث الذكي المنبثق (الجديدة)
    # ==========================================
    def open_fast_search_popup(self):
        win = ctk.CTkToplevel(self)
        win.title("بحث ذكي عن المنتجات (F3)")
        win.geometry("650x420")
        win.attributes("-topmost", True)

        search_var = ctk.StringVar()
        ent_search = ctk.CTkEntry(win, textvariable=search_var, placeholder_text="اكتب اسم المنتج أو كود محلي (5 أرقام) أو باركود...", font=("Arial", 16))
        ent_search.pack(fill="x", padx=10, pady=10)

        tree = ttk.Treeview(win, columns=('id', 'local_code', 'barcode', 'name', 'cost', 'stock'), show='headings')
        tree.heading('id', text='ID')
        tree.heading('local_code', text='كود محلي (5 أرقام)')
        tree.heading('barcode', text='باركود دولي')
        tree.heading('name', text='الاسم')
        tree.heading('cost', text='التكلفة')
        tree.heading('stock', text='المخزن')
        tree.column('id', width=0, stretch=False)
        tree.column('local_code', width=110, anchor='center')
        tree.column('barcode', width=110, anchor='center')
        tree.column('name', width=180, anchor='center')
        tree.column('cost', width=75, anchor='center')
        tree.column('stock', width=75, anchor='center')
        tree.pack(expand=True, fill="both", padx=10, pady=5)

        def do_search(*args):
            term = search_var.get().lower()
            for item in tree.get_children(): tree.delete(item)
            query = "SELECT id, local_code, barcode, name, cost, stock FROM products WHERE name LIKE ? OR local_code LIKE ? OR barcode LIKE ? OR barcode2 LIKE ? OR barcode3 LIKE ? OR ',' || COALESCE(all_barcodes, '') || ',' LIKE ?"
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
            
            self.buy_curr_product_id = item[0]
            self.buy_curr_product_name = str(item[3])
            
            self.lbl_selected_prod.configure(text=f"{self.buy_curr_product_name} [{item[1]}]", text_color="#3498db")
            self.buy_cost.delete(0, 'end')
            self.buy_cost.insert(0, str(item[4]))
            
            self.buy_qty.delete(0, 'end')
            self.buy_qty.focus()
            win.destroy()

        tree.bind("<Double-1>", select_item)
        tree.bind("<Return>", select_item)

        def move_to_tree(event):
            if tree.get_children():
                tree.focus(tree.get_children()[0])
                tree.selection_set(tree.get_children()[0])
                
        ent_search.bind("<Down>", move_to_tree)
        ent_search.focus()

    # ==========================================
    # دوال إضافة منتج وتعديل الصنف الشامل (الاختصارات)
    # ==========================================
    def popup_add_new_product(self):
        from database import generate_next_local_code
        win = ctk.CTkToplevel(self)
        win.title("إضافة منتج جديد شامل (F2)")
        win.geometry("520x610")
        win.attributes("-topmost", True)
        win.resizable(False, False)

        header_frame = ctk.CTkFrame(win, fg_color="#8e44ad", corner_radius=10)
        header_frame.pack(fill="x", padx=15, pady=12)
        ctk.CTkLabel(header_frame, text="📦 إضافة صنف جديد شامل للنظام", font=ctk.CTkFont(size=18, weight="bold"), text_color="white").pack(pady=8)

        grid_frame = ctk.CTkFrame(win, fg_color="#2b2b2b", corner_radius=10)
        grid_frame.pack(fill="x", padx=15, pady=5)

        ctk.CTkLabel(grid_frame, text="اسم المنتج:", font=ctk.CTkFont(weight="bold")).grid(row=0, column=0, padx=10, pady=7, sticky="e")
        ent_name = ctk.CTkEntry(grid_frame, width=280, font=("Arial", 14))
        ent_name.grid(row=0, column=1, padx=10, pady=7)

        ctk.CTkLabel(grid_frame, text="الباركود المحلي (5 أرقام):", font=ctk.CTkFont(weight="bold")).grid(row=1, column=0, padx=10, pady=7, sticky="e")
        ent_loc = ctk.CTkEntry(grid_frame, width=280, justify="center", font=("Arial", 14, "bold"))
        ent_loc.insert(0, generate_next_local_code(self.cursor))
        ent_loc.grid(row=1, column=1, padx=10, pady=7)

        ctk.CTkLabel(grid_frame, text="الباركود الدولي (1):", font=ctk.CTkFont(weight="bold")).grid(row=2, column=0, padx=10, pady=7, sticky="e")
        ent_b1 = ctk.CTkEntry(grid_frame, width=280, justify="center", font=("Arial", 14), placeholder_text="اختياري")
        ent_b1.grid(row=2, column=1, padx=10, pady=7)

        ctk.CTkLabel(grid_frame, text="باركود فرعي (2):", font=ctk.CTkFont(weight="bold")).grid(row=3, column=0, padx=10, pady=7, sticky="e")
        ent_b2 = ctk.CTkEntry(grid_frame, width=280, justify="center", font=("Arial", 14), placeholder_text="اختياري")
        ent_b2.grid(row=3, column=1, padx=10, pady=7)

        ctk.CTkLabel(grid_frame, text="باركود (3) / ميزان:", font=ctk.CTkFont(weight="bold")).grid(row=4, column=0, padx=10, pady=7, sticky="e")
        ent_b3 = ctk.CTkEntry(grid_frame, width=280, justify="center", font=("Arial", 14), placeholder_text="اختياري")
        ent_b3.grid(row=4, column=1, padx=10, pady=7)

        ctk.CTkLabel(grid_frame, text="سعر الشراء / التكلفة (ج.م):", font=ctk.CTkFont(weight="bold")).grid(row=5, column=0, padx=10, pady=7, sticky="e")
        ent_cost = ctk.CTkEntry(grid_frame, width=150, justify="center", font=("Arial", 14, "bold"))
        ent_cost.insert(0, "0")
        ent_cost.grid(row=5, column=1, padx=10, pady=7, sticky="w")

        ctk.CTkLabel(grid_frame, text="سعر البيع للجمهور (ج.م):", font=ctk.CTkFont(weight="bold")).grid(row=6, column=0, padx=10, pady=7, sticky="e")
        ent_price = ctk.CTkEntry(grid_frame, width=150, justify="center", font=("Arial", 14, "bold"))
        ent_price.insert(0, "0")
        ent_price.grid(row=6, column=1, padx=10, pady=7, sticky="w")

        ctk.CTkLabel(grid_frame, text="الرصيد الابتدائي للمخزن:", font=ctk.CTkFont(weight="bold")).grid(row=7, column=0, padx=10, pady=7, sticky="e")
        ent_stock = ctk.CTkEntry(grid_frame, width=150, justify="center", font=("Arial", 14, "bold"))
        ent_stock.insert(0, "0")
        ent_stock.grid(row=7, column=1, padx=10, pady=7, sticky="w")

        def save_new():
            n = ent_name.get().strip()
            loc_c = ent_loc.get().strip() or generate_next_local_code(self.cursor)
            b1 = ent_b1.get().strip() or None
            b2 = ent_b2.get().strip() or None
            b3 = ent_b3.get().strip() or None
            
            try:
                p = float(ent_price.get() or 0)
                c = float(ent_cost.get() or 0)
                stk = float(ent_stock.get() or 0)
                if not n:
                    messagebox.showerror("خطأ", "برجاء كتابة اسم المنتج على الأقل!", parent=win)
                    return
                if p < 0 or c < 0 or stk < 0: raise ValueError
            except ValueError:
                messagebox.showerror("خطأ", "برجاء التأكد من كتابة الأسعار والكمية كأرقام صحيحة!", parent=win)
                return
            
            all_list = [x for x in [loc_c, b1, b2, b3] if x]
            all_b_str = ",".join(all_list)

            try:
                self.cursor.execute(
                    "INSERT INTO products (barcode, local_code, barcode2, barcode3, name, price, cost, stock, all_barcodes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    (b1 or loc_c, loc_c, b2, b3, n, p, c, stk, all_b_str)
                )
                self.db.commit()
                
                # استدعاء المنتج المضاف للتو إلى شاشة المورد
                self.buy_barcode.delete(0, 'end')
                self.buy_barcode.insert(0, loc_c)
                self.buy_fetch_product(None)
                
                messagebox.showinfo("نجاح", f"تم إضافة المنتج ({n}) بكود محلي [{loc_c}] بنجاح!", parent=win)
                win.destroy()
            except sqlite3.IntegrityError:
                messagebox.showerror("خطأ", "الباركود المحلي أو الدولي المدخل مسجل بالفعل لمنتج آخر!", parent=win)

        ent_name.focus()
        ctk.CTkButton(win, text="💾 حفظ المنتج الجديد (Enter)", font=ctk.CTkFont(weight="bold"), fg_color="#27ae60", hover_color="#1e8449", height=42, command=save_new).pack(pady=12, fill="x", padx=30)

    def popup_edit_product(self):
        prod_id = None
        if hasattr(self, 'buy_curr_product_id'):
            prod_id = self.buy_curr_product_id
        else:
            selected = self.buy_tree.selection()
            if selected:
                try:
                    prod_id = int(self.buy_tree.item(selected[0])['values'][0])
                except (ValueError, IndexError):
                    pass

        if not prod_id:
            messagebox.showwarning("تنبيه", "برجاء اختيار أو استدعاء منتج أولاً من الفاتورة أو البحث لتعديله.")
            return

        self.cursor.execute("SELECT id, barcode, barcode2, barcode3, name, price, cost, stock, all_barcodes, local_code FROM products WHERE id=?", (prod_id,))
        prod = self.cursor.fetchone()
        if not prod:
            messagebox.showerror("خطأ", "لم يتم العثور على بيانات المنتج!")
            return

        win = ctk.CTkToplevel(self)
        win.title(f"تعديل صنف شامل (F7) - {prod[4]}")
        win.geometry("520x610")
        win.attributes("-topmost", True)
        win.resizable(False, False)

        header_frame = ctk.CTkFrame(win, fg_color="#f39c12", corner_radius=10)
        header_frame.pack(fill="x", padx=15, pady=12)
        ctk.CTkLabel(header_frame, text=f"📝 تعديل الصنف: {prod[4]}", font=ctk.CTkFont(size=18, weight="bold"), text_color="white").pack(pady=8)

        grid_frame = ctk.CTkFrame(win, fg_color="#2b2b2b", corner_radius=10)
        grid_frame.pack(fill="x", padx=15, pady=5)

        ctk.CTkLabel(grid_frame, text="اسم المنتج:", font=ctk.CTkFont(weight="bold")).grid(row=0, column=0, padx=10, pady=7, sticky="e")
        ent_name = ctk.CTkEntry(grid_frame, width=280, font=("Arial", 14))
        ent_name.insert(0, str(prod[4] or ""))
        ent_name.grid(row=0, column=1, padx=10, pady=7)

        ctk.CTkLabel(grid_frame, text="الباركود المحلي (5 أرقام):", font=ctk.CTkFont(weight="bold")).grid(row=1, column=0, padx=10, pady=7, sticky="e")
        ent_loc = ctk.CTkEntry(grid_frame, width=280, justify="center", font=("Arial", 14, "bold"))
        ent_loc.insert(0, str(prod[9] or ""))
        ent_loc.grid(row=1, column=1, padx=10, pady=7)

        ctk.CTkLabel(grid_frame, text="الباركود الدولي (1):", font=ctk.CTkFont(weight="bold")).grid(row=2, column=0, padx=10, pady=7, sticky="e")
        ent_b1 = ctk.CTkEntry(grid_frame, width=280, justify="center", font=("Arial", 14))
        ent_b1.insert(0, str(prod[1] or ""))
        ent_b1.grid(row=2, column=1, padx=10, pady=7)

        ctk.CTkLabel(grid_frame, text="باركود فرعي (2):", font=ctk.CTkFont(weight="bold")).grid(row=3, column=0, padx=10, pady=7, sticky="e")
        ent_b2 = ctk.CTkEntry(grid_frame, width=280, justify="center", font=("Arial", 14), placeholder_text="اختياري")
        ent_b2.insert(0, str(prod[2] or ""))
        ent_b2.grid(row=3, column=1, padx=10, pady=7)

        ctk.CTkLabel(grid_frame, text="باركود (3) / ميزان:", font=ctk.CTkFont(weight="bold")).grid(row=4, column=0, padx=10, pady=7, sticky="e")
        ent_b3 = ctk.CTkEntry(grid_frame, width=280, justify="center", font=("Arial", 14), placeholder_text="اختياري")
        ent_b3.insert(0, str(prod[3] or ""))
        ent_b3.grid(row=4, column=1, padx=10, pady=7)

        ctk.CTkLabel(grid_frame, text="سعر الشراء / التكلفة (ج.م):", font=ctk.CTkFont(weight="bold")).grid(row=5, column=0, padx=10, pady=7, sticky="e")
        ent_cost = ctk.CTkEntry(grid_frame, width=150, justify="center", font=("Arial", 14, "bold"))
        ent_cost.insert(0, f"{prod[6]:g}")
        ent_cost.grid(row=5, column=1, padx=10, pady=7, sticky="w")

        ctk.CTkLabel(grid_frame, text="سعر البيع للجمهور (ج.م):", font=ctk.CTkFont(weight="bold")).grid(row=6, column=0, padx=10, pady=7, sticky="e")
        ent_price = ctk.CTkEntry(grid_frame, width=150, justify="center", font=("Arial", 14, "bold"))
        ent_price.insert(0, f"{prod[5]:g}")
        ent_price.grid(row=6, column=1, padx=10, pady=7, sticky="w")

        ctk.CTkLabel(grid_frame, text="الرصيد في المخزن:", font=ctk.CTkFont(weight="bold")).grid(row=7, column=0, padx=10, pady=7, sticky="e")
        ent_stock = ctk.CTkEntry(grid_frame, width=150, justify="center", font=("Arial", 14, "bold"))
        ent_stock.insert(0, f"{prod[7]:g}")
        ent_stock.grid(row=7, column=1, padx=10, pady=7, sticky="w")

        def update_product():
            n = ent_name.get().strip()
            loc_c = ent_loc.get().strip()
            b1 = ent_b1.get().strip() or None
            b2 = ent_b2.get().strip() or None
            b3 = ent_b3.get().strip() or None
            
            try:
                p = float(ent_price.get() or 0)
                c = float(ent_cost.get() or 0)
                stk = float(ent_stock.get() or 0)
                if not n:
                    messagebox.showerror("خطأ", "برجاء كتابة اسم المنتج على الأقل!", parent=win)
                    return
                if p < 0 or c < 0 or stk < 0: raise ValueError
            except ValueError:
                messagebox.showerror("خطأ", "برجاء التأكد من كتابة الأسعار والكمية كأرقام صحيحة!", parent=win)
                return

            all_list = [x for x in [loc_c, b1, b2, b3] if x]
            all_b_str = ",".join(all_list)

            try:
                self.cursor.execute(
                    "UPDATE products SET barcode=?, local_code=?, barcode2=?, barcode3=?, name=?, price=?, cost=?, stock=?, all_barcodes=? WHERE id=?",
                    (b1 or loc_c, loc_c, b2, b3, n, p, c, stk, all_b_str, prod_id)
                )
                self.db.commit()

                self.buy_curr_product_id = prod_id
                self.buy_curr_product_name = n
                self.lbl_selected_prod.configure(text=f"{n} [{loc_c}]", text_color="#3498db")
                self.buy_cost.delete(0, 'end')
                self.buy_cost.insert(0, f"{c:g}")

                if prod_id in self.buy_cart:
                    self.buy_cart[prod_id]['name'] = n
                    self.buy_cart[prod_id]['cost'] = c
                    self.update_buy_cart()

                messagebox.showinfo("نجاح", f"تم تحديث بيانات الصنف ({n}) بنجاح!", parent=win)
                win.destroy()
            except sqlite3.IntegrityError:
                messagebox.showerror("خطأ", "أحد الباركودات المدخلة مسجل بالفعل لمنتج آخر!", parent=win)

        ent_name.focus()
        ctk.CTkButton(win, text="🔄 حفظ البيانات والتأكيد (Enter)", font=ctk.CTkFont(weight="bold"), fg_color="#f39c12", hover_color="#d68910", height=42, command=update_product).pack(pady=15, fill="x", padx=30)


    def popup_edit_price(self):
        self.popup_edit_product()


    # ==========================================
    # دوال التحميل والفلترة 
    # ==========================================
    def load_suppliers(self):
        for item in self.sup_tree.get_children(): self.sup_tree.delete(item)
        self.cursor.execute("SELECT id, name, balance FROM suppliers")
        sups = self.cursor.fetchall()
        
        self.all_suppliers_list = [f"{s[0]} - {s[1]}" for s in sups]
        
        if hasattr(self, 'sup_buy_combo'):
            self.sup_buy_combo.configure(values=self.all_suppliers_list)
            self.sup_pay_combo.configure(values=self.all_suppliers_list)
            self.rep_sup_combo.configure(values=self.all_suppliers_list)
            
            if self.all_suppliers_list:
                curr_buy = self.sup_buy_combo.get()
                if not curr_buy or curr_buy == "CTkComboBox" or curr_buy not in self.all_suppliers_list:
                    self.sup_buy_combo.set(self.all_suppliers_list[0])

                curr_pay = self.sup_pay_combo.get()
                if not curr_pay or curr_pay == "CTkComboBox" or curr_pay not in self.all_suppliers_list:
                    self.sup_pay_combo.set(self.all_suppliers_list[0])
                    self.on_supplier_selected_for_pay(self.all_suppliers_list[0])

                curr_rep = self.rep_sup_combo.get()
                if not curr_rep or curr_rep == "CTkComboBox" or curr_rep not in self.all_suppliers_list:
                    self.rep_sup_combo.set(self.all_suppliers_list[0])
            else:
                self.sup_buy_combo.set("")
                self.sup_pay_combo.set("")
                self.rep_sup_combo.set("")


        total_debts = 0.0
        for s in sups:
            total_debts += s[2]
            self.sup_tree.insert("", "end", values=(s[0], s[1], f"{s[2]:g} ج.م"))
        self.lbl_total_debts.configure(text=f"إجمالي الديون المستحقة للسوق: {total_debts:g} ج.م")

    def filter_buy_suppliers(self, *args):
        term = self.buy_sup_search_var.get().lower()
        matches = [s for s in self.all_suppliers_list if term in s.lower()] if term else self.all_suppliers_list
        self.sup_buy_combo.configure(values=matches)
        if matches: self.sup_buy_combo.set(matches[0])
        else: self.sup_buy_combo.set("")

    def filter_pay_suppliers(self, *args):
        term = self.pay_sup_search_var.get().lower()
        matches = [s for s in self.all_suppliers_list if term in s.lower()] if term else self.all_suppliers_list
        self.sup_pay_combo.configure(values=matches)
        if matches: 
            self.sup_pay_combo.set(matches[0])
            self.on_supplier_selected_for_pay(matches[0])
        else: 
            self.sup_pay_combo.set("")
            self.sup_pay_amount.delete(0, 'end')

    def filter_rep_suppliers(self, *args):
        term = self.rep_sup_search_var.get().lower()
        matches = [s for s in self.all_suppliers_list if term in s.lower()] if term else self.all_suppliers_list
        self.rep_sup_combo.configure(values=matches)
        if matches: 
            self.rep_sup_combo.set(matches[0])
            self.load_supplier_report()
        else: 
            self.rep_sup_combo.set("")

    # ==========================================
    # دوال إدارة سلة المشتريات
    # ==========================================
    def buy_fetch_product(self, event=None):
        barcode = self.buy_barcode.get().strip()
        if not barcode: return
        query = "SELECT id, name, cost, local_code FROM products WHERE barcode=? OR local_code=? OR barcode2=? OR barcode3=? OR ',' || COALESCE(all_barcodes, '') || ',' LIKE ?"
        bc_s = f'%,{barcode},%'
        self.cursor.execute(query, (barcode, barcode, barcode, barcode, bc_s))
        prod = self.cursor.fetchone()
        if prod:
            self.buy_curr_product_id = prod[0]
            self.buy_curr_product_name = prod[1]
            
            self.lbl_selected_prod.configure(text=f"{prod[1]} [{prod[3] or ''}]", text_color="#3498db")
            self.buy_cost.delete(0, 'end')
            self.buy_cost.insert(0, str(prod[2]))
            
            self.buy_qty.delete(0, 'end')
            self.buy_qty.focus()
            self.buy_barcode.delete(0, 'end')
        else:
            messagebox.showerror("خطأ", "باركود غير صحيح، يمكنك الضغط على F2 لإضافته كمنتج جديد.")
            self.buy_barcode.delete(0, 'end')


    def buy_add_item(self):
        if not hasattr(self, 'buy_curr_product_id'): 
            messagebox.showwarning("تنبيه", "برجاء اختيار منتج أولاً.")
            return
            
        p_id = self.buy_curr_product_id
        name = self.buy_curr_product_name
        
        try:
            cost = float(self.buy_cost.get() or 0)
            whole = float(self.buy_qty.get() or 0)
            grams = float(self.buy_grams.get() or 0)
            qty = whole + (grams / 1000.0)
            if qty <= 0 or cost < 0: raise ValueError
        except:
            messagebox.showerror("خطأ", "برجاء إدخال الكمية/الوزن والتكلفة بشكل صحيح")
            return
            
        if p_id in self.buy_cart:
            self.buy_cart[p_id]['qty'] += qty
            self.buy_cart[p_id]['cost'] = cost 
        else:
            self.buy_cart[p_id] = {'name': name, 'cost': cost, 'qty': qty}
            
        self.update_buy_cart()
        
        self.buy_cost.delete(0, 'end')
        self.buy_qty.delete(0, 'end')
        self.buy_grams.delete(0, 'end')
        self.lbl_selected_prod.configure(text="لم يتم اختيار منتج", text_color="#f39c12")
        
        delattr(self, 'buy_curr_product_id')
        delattr(self, 'buy_curr_product_name')
        
        self.buy_barcode.focus()

    def edit_buy_cart_item(self, event=None):
        selected = self.buy_tree.selection()
        if not selected:
            messagebox.showwarning("تنبيه", "برجاء تحديد منتج من الفاتورة لتعديله.")
            return
        
        try:
            p_id = int(self.buy_tree.item(selected[0])['values'][0])
        except (IndexError, ValueError):
            return

        item = self.buy_cart.get(p_id)
        if not item: return

        curr_qty = float(item['qty'])
        curr_cost = float(item['cost'])
        
        whole_val = int(curr_qty)
        grams_val = int(round((curr_qty - whole_val) * 1000))

        edit_win = ctk.CTkToplevel(self)
        edit_win.title(f"تعديل الكمية والوزن والتكلفة - {item['name']}")
        edit_win.geometry("450x420")
        edit_win.attributes("-topmost", True)
        edit_win.resizable(False, False)

        header_frame = ctk.CTkFrame(edit_win, fg_color="#1f538d", corner_radius=10)
        header_frame.pack(fill="x", padx=15, pady=15)
        ctk.CTkLabel(header_frame, text=f"📦 {item['name']}", font=ctk.CTkFont(size=18, weight="bold"), text_color="white").pack(pady=10)

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

        ctk.CTkLabel(grid_frame, text="تكلفة الوحدة/الكغم:", font=ctk.CTkFont(weight="bold")).grid(row=2, column=0, padx=10, pady=10, sticky="e")
        ent_cost = ctk.CTkEntry(grid_frame, width=100, justify="center", font=("Arial", 16, "bold"))
        ent_cost.insert(0, f"{curr_cost:g}")
        ent_cost.grid(row=2, column=1, padx=10, pady=10)

        def save_buy_edits(event=None):
            try:
                w = float(ent_whole.get() or 0)
                g = float(ent_grams.get() or 0)
                new_qty = w + (g / 1000.0)
                new_cost = float(ent_cost.get() or 0)
                if new_qty <= 0 or new_cost < 0: raise ValueError
                
                self.buy_cart[p_id]['qty'] = new_qty
                self.buy_cart[p_id]['cost'] = new_cost
                self.update_buy_cart()
                
                edit_win.destroy()
            except ValueError:
                messagebox.showerror("خطأ", "برجاء إدخال الأرقام بشكل صحيح!", parent=edit_win)

        ent_whole.bind("<Return>", lambda e: ent_grams.focus())
        ent_grams.bind("<Return>", lambda e: ent_cost.focus())
        ent_cost.bind("<Return>", save_buy_edits)

        ctk.CTkButton(edit_win, text="✔️ حفظ التعديل (Enter)", font=ctk.CTkFont(weight="bold"), fg_color="#27ae60", hover_color="#1e8449", height=40, command=save_buy_edits).pack(pady=15, fill="x", padx=30)


    def buy_remove_selected(self):
        selected = self.buy_tree.selection()
        if not selected:
            messagebox.showwarning("تنبيه", "برجاء تحديد منتج من الفاتورة لحذفه.")
            return
            
        item = self.buy_tree.item(selected[0])['values']
        p_id = item[0] 
        
        if p_id in self.buy_cart:
            del self.buy_cart[p_id]
            self.update_buy_cart()

    def apply_buy_discount(self, *args):
        try:
            val = self.buy_discount_var.get()
            self.buy_discount_val = float(val) if val else 0.0
        except ValueError:
            self.buy_discount_val = 0.0
        self.update_buy_cart()

    def update_buy_cart(self):
        for item in self.buy_tree.get_children(): self.buy_tree.delete(item)
        
        self.buy_total = 0.0
        for p_id, item in self.buy_cart.items():
            subtotal = item['cost'] * item['qty']
            self.buy_total += subtotal
            self.buy_tree.insert("", "end", values=(p_id, item['name'], f"{item['cost']:g}", f"{item['qty']:g}", f"{subtotal:g}"))

        
        net_total = self.buy_total - self.buy_discount_val
        if net_total < 0: net_total = 0.0
        self.buy_total_lbl.configure(text=f"الإجمالي المطلوب: {net_total:g} ج.م")

    def clear_buy_cart(self):
        if not self.buy_cart: return
        if messagebox.askyesno("تأكيد", "مسح الفاتورة الحالية بالكامل؟"):
            self.buy_cart.clear()
            self.buy_discount_var.set("0")
            self.update_buy_cart()
            self.buy_paid.delete(0, 'end')
            self.buy_barcode.focus()

    def buy_checkout(self):
        if not self.buy_cart: return messagebox.showwarning("تنبيه", "الفاتورة فارغة!")
            
        sup_str = self.sup_buy_combo.get()
        if not sup_str or sup_str == "CTkComboBox" or " - " not in sup_str:
            return messagebox.showerror("خطأ", "برجاء اختيار المورد من القائمة أولاً!")
            
        try:
            sup_id = int(sup_str.split(" - ")[0])
        except (ValueError, IndexError):
            return messagebox.showerror("خطأ", "اختيار المورد غير صحيح!")

        try: paid = float(self.buy_paid.get() or 0)
        except: return messagebox.showerror("خطأ", "المبلغ المدفوع غير صحيح")
        
        net_total = self.buy_total - self.buy_discount_val
        if net_total < 0: net_total = 0.0
            
        if paid > net_total: return messagebox.showerror("خطأ", "المبلغ المدفوع كاش أكبر من إجمالي الفاتورة المطلوب!")
            
        date_now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")

        try:
            if self.current_purchase_id is not None:
                # 1. التراجع عن الأثر السابق للفاتورة المعنية للتعديل الآمن
                self.cursor.execute("SELECT product_id, qty FROM purchase_items WHERE purchase_id=?", (self.current_purchase_id,))
                for o_pid, o_qty in self.cursor.fetchall():
                    self.cursor.execute("UPDATE products SET stock = stock - ? WHERE id=?", (o_qty, o_pid))
                    
                self.cursor.execute("SELECT supplier_id, total, paid FROM purchases WHERE id=?", (self.current_purchase_id,))
                old_p = self.cursor.fetchone()
                if old_p:
                    old_sup_id, old_tot, old_pd = old_p[0], old_p[1], old_p[2]
                    old_rem = old_tot - old_pd
                    self.cursor.execute("UPDATE suppliers SET balance = balance - ? WHERE id=?", (old_rem, old_sup_id))

                # 2. تحديث سجل الفاتورة الحالي
                self.cursor.execute("UPDATE purchases SET supplier_id=?, total=?, paid=?, discount=?, date=? WHERE id=?",
                                    (sup_id, net_total, paid, self.buy_discount_val, date_now, self.current_purchase_id))
                purch_id = self.current_purchase_id
                
                # مسح عناصر الفاتورة القديمة وإدخال الجديدة
                self.cursor.execute("DELETE FROM purchase_items WHERE purchase_id=?", (purch_id,))
            else:
                # إنشاء فاتورة جديدة
                inv_no = f"INV-{datetime.datetime.now().strftime('%Y%m%d%H%M%S')}"
                self.cursor.execute("INSERT INTO purchases (supplier_id, total, paid, date, status, discount, invoice_number, synced) VALUES (?, ?, ?, ?, 'مكتملة', ?, ?, 0)",
                                    (sup_id, net_total, paid, date_now, self.buy_discount_val, inv_no))
                purch_id = self.cursor.lastrowid

            # إضافة العناصر وتطبيق الكميات والأسعار الجديدة
            for p_id, item in self.buy_cart.items():
                self.cursor.execute("INSERT INTO purchase_items (purchase_id, product_id, qty, cost) VALUES (?, ?, ?, ?)",
                                    (purch_id, p_id, item['qty'], item['cost']))
                self.cursor.execute("UPDATE products SET stock = stock + ?, cost = ? WHERE id=?", 
                                    (item['qty'], item['cost'], p_id))
                                    
            remaining = net_total - paid
            self.cursor.execute("UPDATE suppliers SET balance = balance + ? WHERE id=?", (remaining, sup_id))
            
            if paid > 0:
                date_only = datetime.datetime.now().strftime("%Y-%m-%d")
                self.cursor.execute("INSERT INTO expenses (category, amount, note, date) VALUES (?, ?, ?, ?)",
                                    ("مشتريات بضاعة", paid, f"دفع نقدي لفاتورة مورد رقم {purch_id}", date_only))
                                    
            self.db.commit()
            messagebox.showinfo("نجاح", f"تم حفظ/تحديث الفاتورة #{purch_id} بنجاح!\nالمديونية المتبقية للمورد: {remaining:g} ج.م")
            
            self.new_purchase_invoice()
            self.load_suppliers()
        except Exception as e:
            messagebox.showerror("خطأ", f"حدث خطأ أثناء حفظ الفاتورة: {str(e)}")

    # ==========================================
    # دوال التصفح والطباعة والتعديل للفواتير
    # ==========================================
    def navigate_purchase_invoice(self, direction):
        if direction == 'prev':
            if self.current_purchase_id is None:
                self.cursor.execute("SELECT id FROM purchases ORDER BY id DESC LIMIT 1")
            else:
                self.cursor.execute("SELECT id FROM purchases WHERE id < ? ORDER BY id DESC LIMIT 1", (self.current_purchase_id,))
            row = self.cursor.fetchone()
            if not row:
                return messagebox.showinfo("تنبيه", "هذه هي أول فاتورة مسجلة للنظام!")
            self.load_purchase_invoice_by_id(row[0])
        elif direction == 'next':
            if self.current_purchase_id is None:
                return
            self.cursor.execute("SELECT id FROM purchases WHERE id > ? ORDER BY id ASC LIMIT 1", (self.current_purchase_id,))
            row = self.cursor.fetchone()
            if not row:
                self.new_purchase_invoice()
                return
            self.load_purchase_invoice_by_id(row[0])

    def load_purchase_invoice_by_id(self, p_id):
        self.cursor.execute("SELECT p.id, p.supplier_id, p.total, p.paid, p.date, p.status, p.discount, s.name FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.id=?", (p_id,))
        p_row = self.cursor.fetchone()
        if not p_row:
            return messagebox.showerror("خطأ", "لم يتم العثور على الفاتورة المطلوب عرضها!")

        self.current_purchase_id = p_id
        sup_id, total, paid, date_str, status, discount, sup_name = p_row[1], p_row[2], p_row[3], p_row[4], p_row[5], p_row[6] or 0.0, p_row[7] or "مورد عام"

        self.buy_cart.clear()
        self.cursor.execute("SELECT pi.product_id, prod.name, pi.cost, pi.qty FROM purchase_items pi JOIN products prod ON pi.product_id = prod.id WHERE pi.purchase_id=?", (p_id,))
        items = self.cursor.fetchall()
        for item in items:
            self.buy_cart[item[0]] = {
                'name': item[1],
                'cost': float(item[2]),
                'qty': float(item[3])
            }

        if hasattr(self, 'sup_buy_combo'):
            sup_target = f"{sup_id} - {sup_name}"
            if sup_target in self.all_suppliers_list:
                self.sup_buy_combo.set(sup_target)

        self.buy_discount_var.set(f"{discount:g}")
        self.buy_paid.delete(0, 'end')
        self.buy_paid.insert(0, f"{paid:g}")
        self.update_buy_cart()

        if hasattr(self, 'lbl_cart_header'):
            self.lbl_cart_header.configure(text=f"🧾 فاتورة مشتريات مسجلة رقم #{p_id} ({date_str})", text_color="#f1c40f")

    def new_purchase_invoice(self):
        self.current_purchase_id = None
        self.buy_cart.clear()
        self.buy_discount_var.set("0")
        self.buy_paid.delete(0, 'end')
        self.update_buy_cart()
        if hasattr(self, 'lbl_cart_header'):
            self.lbl_cart_header.configure(text="🧾 فاتورة المشتريات (استلام بضاعة جديد)", text_color="white")
        if self.all_suppliers_list and hasattr(self, 'sup_buy_combo'):
            self.sup_buy_combo.set(self.all_suppliers_list[0])
        self.buy_barcode.focus()

    def edit_loaded_purchase_invoice(self):
        if self.current_purchase_id is None:
            return messagebox.showwarning("تنبيه", "برجاء استخدام زر (◀️ السابقة) لتصفح واختيار الفاتورة المراد تعديلها.")
        messagebox.showinfo("تعديل فاتورة", f"✏️ الفاتورة رقم #{self.current_purchase_id} مجهزة للتعديل الآن.\nيمكنك إضافة أو تعديل الأصناف ثم اضغط (F5) لحفظ التعديلات والتحديث.")

    def print_current_purchase_invoice(self):
        if not self.buy_cart:
            return messagebox.showwarning("تنبيه", "لا توجد فاتورة محملة حالياً لطباعتها!")
            
        sup_str = self.sup_buy_combo.get()
        sup_name = sup_str.split(" - ")[1] if " - " in sup_str else "مورد عام"
        inv_id_str = str(self.current_purchase_id) if self.current_purchase_id else "جديدة (معاينة)"
        
        inv_data = {
            "shop_name_ar": "نظام الكاشير والمخازن",
            "invoice_id": inv_id_str,
            "supplier_name": sup_name,
            "total": self.buy_total - self.buy_discount_val,
            "discount": self.buy_discount_val,
            "paid": float(self.buy_paid.get() or 0),
            "date": datetime.datetime.now().strftime("%Y-%m-%d %H:%M")
        }
        
        items_list = []
        for p_id, item in self.buy_cart.items():
            items_list.append({
                "name": item['name'],
                "cost": item['cost'],
                "qty": item['qty']
            })
            
        print_purchase_receipt(inv_data, items_list)

    # ==========================================
    # 2. إدارة الموردين والسداد
    # ==========================================
    def setup_manage_suppliers_tab(self, tab):
        add_frame = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        add_frame.pack(fill="x", pady=10, padx=20)
        
        header_add = ctk.CTkFrame(add_frame, fg_color="#1f538d", corner_radius=8)
        header_add.pack(fill="x", padx=10, pady=(10, 5))
        ctk.CTkLabel(header_add, text="👤 إضافة / تعديل بيانات مورد", font=ctk.CTkFont(size=14, weight="bold"), text_color="white").pack(pady=4)

        row_input = ctk.CTkFrame(add_frame, fg_color="transparent")
        row_input.pack(pady=10, padx=10)
        
        ctk.CTkLabel(row_input, text="اسم المورد:", font=ctk.CTkFont(weight="bold")).grid(row=0, column=0, padx=5, pady=5)
        self.sup_name = ctk.CTkEntry(row_input, placeholder_text="اسم المورد...", width=160)
        self.sup_name.grid(row=0, column=1, padx=5, pady=5)
        
        ctk.CTkLabel(row_input, text="الرصيد الافتتاحي (له):", font=ctk.CTkFont(weight="bold")).grid(row=0, column=2, padx=5, pady=5)
        self.sup_balance = ctk.CTkEntry(row_input, placeholder_text="0", width=100, justify="center")
        self.sup_balance.grid(row=0, column=3, padx=5, pady=5)
        
        self.btn_add_sup = ctk.CTkButton(row_input, text="➕ حفظ مورد جديد", font=ctk.CTkFont(weight="bold"), fg_color="#27ae60", hover_color="#1e8449", command=self.add_supplier)
        self.btn_add_sup.grid(row=0, column=4, padx=8, pady=5)
        
        self.btn_edit_sup = ctk.CTkButton(row_input, text="✏️ تحديث البيانات", font=ctk.CTkFont(weight="bold"), fg_color="#f39c12", hover_color="#d68910", state="disabled", command=self.update_supplier)
        self.btn_edit_sup.grid(row=0, column=5, padx=8, pady=5)

        pay_frame = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        pay_frame.pack(fill="x", pady=10, padx=20)

        header_pay = ctk.CTkFrame(pay_frame, fg_color="#c0392b", corner_radius=8)
        header_pay.pack(fill="x", padx=10, pady=(10, 5))
        ctk.CTkLabel(header_pay, text="💸 سداد دفعة حساب لمورد (خصم من الخزينة)", font=ctk.CTkFont(size=14, weight="bold"), text_color="white").pack(pady=4)

        row_pay = ctk.CTkFrame(pay_frame, fg_color="transparent")
        row_pay.pack(pady=10, padx=10)

        ctk.CTkLabel(row_pay, text="بحث:", font=ctk.CTkFont(weight="bold")).grid(row=0, column=0, padx=5)
        self.pay_sup_search_var = ctk.StringVar()
        self.pay_sup_search_var.trace("w", self.filter_pay_suppliers)
        self.pay_sup_search = ctk.CTkEntry(row_pay, textvariable=self.pay_sup_search_var, placeholder_text="🔍 بحث...", width=100)
        self.pay_sup_search.grid(row=0, column=1, padx=5)
        
        self.sup_pay_combo = ctk.CTkComboBox(row_pay, values=[], width=200, command=self.on_supplier_selected_for_pay)
        self.sup_pay_combo.grid(row=0, column=2, padx=5)
        
        ctk.CTkLabel(row_pay, text="المبلغ المنصرف:", font=ctk.CTkFont(weight="bold")).grid(row=0, column=3, padx=5)
        self.sup_pay_amount = ctk.CTkEntry(row_pay, placeholder_text="0.0", width=110, justify="center", font=("Arial", 14, "bold"))
        self.sup_pay_amount.grid(row=0, column=4, padx=5)
        self.sup_pay_amount.bind("<Return>", lambda e: self.pay_supplier())
        
        ctk.CTkButton(row_pay, text="💸 صرف وتأكيد السداد", font=ctk.CTkFont(weight="bold"), fg_color="#c0392b", hover_color="#922b21", command=self.pay_supplier).grid(row=0, column=5, padx=10)

        debt_card = ctk.CTkFrame(tab, fg_color="#1e272e", corner_radius=10)
        debt_card.pack(fill="x", padx=20, pady=5)
        self.lbl_total_debts = ctk.CTkLabel(debt_card, text="إجمالي الديون المستحقة للسوق: 0 ج.م", font=ctk.CTkFont(size=18, weight="bold"), text_color="#e74c3c")
        self.lbl_total_debts.pack(pady=10)

        tree_frame = ctk.CTkFrame(tab, corner_radius=10, fg_color="#2c3e50")
        tree_frame.pack(expand=True, fill="both", padx=20, pady=(5, 10))
        self.sup_tree = ttk.Treeview(tree_frame, columns=('id', 'name', 'balance'), show='headings')
        self.sup_tree.heading('id', text='كود المورد')
        self.sup_tree.heading('name', text='اسم المورد')
        self.sup_tree.heading('balance', text='الرصيد المستحق (له)')
        self.sup_tree.column('id', width=90, anchor='center')
        self.sup_tree.column('name', anchor='center')
        self.sup_tree.column('balance', anchor='center')
        self.sup_tree.pack(expand=True, fill="both", padx=10, pady=10)

        self.sup_tree.bind("<Double-1>", self.on_supplier_double_click)

    def add_supplier(self):
        name = self.sup_name.get().strip()
        if not name: return
        balance = float(self.sup_balance.get() or 0)
        self.cursor.execute("INSERT INTO suppliers (name, balance, synced) VALUES (?, ?, 0)", (name, balance))
        self.db.commit()
        if hasattr(self.app, 'sync_mgr'):
            self.app.sync_mgr.trigger_instant_sync()
        messagebox.showinfo("نجاح", f"تم إضافة المورد {name} بنجاح!")
        self.load_suppliers()
        self.sup_name.delete(0, 'end')
        self.sup_balance.delete(0, 'end')

    def on_supplier_double_click(self, event):
        selected = self.sup_tree.selection()
        if not selected: return
        item = self.sup_tree.item(selected[0])['values']
        self.current_edit_sup_id = item[0]
        self.sup_name.delete(0, 'end')
        self.sup_name.insert(0, item[1])
        self.sup_balance.delete(0, 'end')
        self.sup_balance.insert(0, str(item[2]).replace(' ج.م', ''))
        self.btn_add_sup.configure(state="disabled")
        self.btn_edit_sup.configure(state="normal")

    def update_supplier(self):
        if not self.current_edit_sup_id: return
        name = self.sup_name.get().strip()
        try:
            balance = float(self.sup_balance.get() or 0)
            self.cursor.execute("UPDATE suppliers SET name=?, balance=?, synced=0 WHERE id=?", (name, balance, self.current_edit_sup_id))
            self.db.commit()
            if hasattr(self.app, 'sync_mgr'):
                self.app.sync_mgr.trigger_instant_sync()
            messagebox.showinfo("نجاح", "تم تحديث بيانات المورد بنجاح.")
            self.load_suppliers()
            self.sup_name.delete(0, 'end')
            self.sup_balance.delete(0, 'end')
            self.btn_add_sup.configure(state="normal")
            self.btn_edit_sup.configure(state="disabled")
            self.current_edit_sup_id = None
        except ValueError:
            messagebox.showerror("خطأ", "برجاء كتابة الرصيد بشكل صحيح.")

    def on_supplier_selected_for_pay(self, choice):
        if not choice or choice == "CTkComboBox" or " - " not in choice: return
        try:
            sup_id = int(choice.split(" - ")[0])
            self.cursor.execute("SELECT balance FROM suppliers WHERE id=?", (sup_id,))
            balance = self.cursor.fetchone()
            if balance:
                self.sup_pay_amount.delete(0, 'end')
                self.sup_pay_amount.insert(0, str(balance[0]))
        except (ValueError, IndexError):
            pass

    def pay_supplier(self):
        sup_str = self.sup_pay_combo.get()
        if not sup_str or sup_str == "CTkComboBox" or " - " not in sup_str:
            return messagebox.showerror("خطأ", "برجاء اختيار المورد أولاً!")
            
        try:
            s_id = int(sup_str.split(" - ")[0])
            amount = float(self.sup_pay_amount.get())
            if amount <= 0: raise ValueError
            self.cursor.execute("UPDATE suppliers SET balance = balance - ?, synced = 0 WHERE id=?", (amount, s_id))
            date_now = datetime.datetime.now().strftime("%Y-%m-%d")
            self.cursor.execute("INSERT INTO expenses (category, amount, note, date) VALUES (?, ?, ?, ?)", 
                                ("سداد موردين", amount, f"سداد دفعة نقدية لمورد", date_now))
            self.db.commit()
            if hasattr(self.app, 'sync_mgr'):
                self.app.sync_mgr.trigger_instant_sync()
            messagebox.showinfo("نجاح", f"تم سداد {amount:g} ج.م للمورد.")
            self.sup_pay_amount.delete(0, 'end')
            self.pay_sup_search.delete(0, 'end')
            self.load_suppliers() 
            self.load_supplier_report() 
        except ValueError:
            messagebox.showerror("خطأ", "تأكد من إدخال مبلغ صحيح.")

    # ==========================================
    # 3. تقرير حسابات الموردين 
    # ==========================================
    def setup_report_tab(self, tab):
        top_frame = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        top_frame.pack(fill="x", pady=10, padx=20)
        
        ctk.CTkButton(top_frame, text="📊 تحديث التقرير", font=ctk.CTkFont(weight="bold"), fg_color="#f39c12", hover_color="#d68910", command=self.load_supplier_report).pack(side="left", padx=10, pady=10)
        ctk.CTkButton(top_frame, text="💾 تصدير كشف حساب", font=ctk.CTkFont(weight="bold"), fg_color="#27ae60", hover_color="#1e8449", command=self.export_supplier_report).pack(side="left", padx=10, pady=10)

        self.rep_sup_combo = ctk.CTkComboBox(top_frame, values=[], width=240, command=lambda e: self.load_supplier_report())
        self.rep_sup_combo.pack(side="right", padx=10, pady=10)
        
        self.rep_sup_search_var = ctk.StringVar()
        self.rep_sup_search_var.trace("w", self.filter_rep_suppliers)
        self.rep_sup_search = ctk.CTkEntry(top_frame, textvariable=self.rep_sup_search_var, placeholder_text="🔍 بحث عن مورد...", width=110)
        self.rep_sup_search.pack(side="right", padx=5, pady=10)
        ctk.CTkLabel(top_frame, text="اختر المورد لطباعة تقريره:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=5, pady=10)
        
        summary_frame = ctk.CTkFrame(tab, fg_color="transparent")
        summary_frame.pack(fill="x", pady=10, padx=20)
        
        self.rep_lbl_debt = ctk.CTkLabel(summary_frame, text="المديونية (الباقي له)\n0 ج.م", font=ctk.CTkFont(size=18, weight="bold"), fg_color="#c0392b", text_color="white", width=220, height=75, corner_radius=12)
        self.rep_lbl_debt.pack(side="left", padx=10, expand=True)

        self.rep_lbl_paid = ctk.CTkLabel(summary_frame, text="إجمالي المدفوعات\n0 ج.م", font=ctk.CTkFont(size=18, weight="bold"), fg_color="#27ae60", text_color="white", width=220, height=75, corner_radius=12)
        self.rep_lbl_paid.pack(side="left", padx=10, expand=True)

        self.rep_lbl_total = ctk.CTkLabel(summary_frame, text="إجمالي قيمة المشتريات\n0 ج.م", font=ctk.CTkFont(size=18, weight="bold"), fg_color="#1f538d", text_color="white", width=220, height=75, corner_radius=12)
        self.rep_lbl_total.pack(side="left", padx=10, expand=True)
        
        tree_frame = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        tree_frame.pack(expand=True, fill="both", padx=20, pady=(5, 10))
        self.rep_tree = ttk.Treeview(tree_frame, columns=('id', 'date', 'total', 'discount', 'paid', 'rem', 'status'), show='headings')
        self.rep_tree.heading('id', text='رقم الفاتورة')
        self.rep_tree.heading('date', text='التاريخ')
        self.rep_tree.heading('total', text='الإجمالي بعد الخصم')
        self.rep_tree.heading('discount', text='الخصم')
        self.rep_tree.heading('paid', text='المدفوع كاش')
        self.rep_tree.heading('rem', text='الآجل (الباقي)')
        self.rep_tree.heading('status', text='الحالة')
        for col in self.rep_tree['columns']: self.rep_tree.column(col, anchor='center')
        self.rep_tree.pack(expand=True, fill="both", padx=10, pady=10)
        
        self.rep_tree.bind("<Double-1>", self.open_selected_purchase_from_report)
        self.rep_tree.bind("<Return>", self.open_selected_purchase_from_report)

        actions_bar = ctk.CTkFrame(tree_frame, fg_color="transparent")
        actions_bar.pack(fill="x", padx=10, pady=(0, 10))

        ctk.CTkButton(actions_bar, text="👁️✏️ فتح الفاتورة في شاشة المشتريات للتعديل / الطباعة (Double Click)", font=ctk.CTkFont(weight="bold"), fg_color="#e67e22", hover_color="#d35400", command=self.open_selected_purchase_from_report).pack(side="right", padx=5)
        ctk.CTkButton(actions_bar, text="🖨️ إعادة طباعة الفاتورة المحددة فوراً", font=ctk.CTkFont(weight="bold"), fg_color="#2980b9", hover_color="#1f618d", command=self.print_selected_purchase_from_report).pack(side="right", padx=5)
        ctk.CTkLabel(actions_bar, text="💡 اضغط دبل كليك على أي فاتورة لفتحها مباشرة في شاشة المشتريات والتعديل (✏️) أو الطباعة (🖨️)", text_color="#f1c40f", font=ctk.CTkFont(size=12, weight="bold")).pack(side="left", padx=10)


    def load_supplier_report(self):
        sup_str = self.rep_sup_combo.get()
        if not sup_str or sup_str == "CTkComboBox" or " - " not in sup_str: return
        try:
            sup_id = int(sup_str.split(" - ")[0])
        except (ValueError, IndexError):
            return

        
        self.cursor.execute("SELECT balance FROM suppliers WHERE id=?", (sup_id,))
        balance_row = self.cursor.fetchone()
        balance = balance_row[0] if balance_row else 0.0
        
        self.cursor.execute("SELECT SUM(total), SUM(paid) FROM purchases WHERE supplier_id=? AND status='مكتملة'", (sup_id,))
        totals_row = self.cursor.fetchone()
        total_bought = totals_row[0] if totals_row[0] else 0.0
        total_paid = totals_row[1] if totals_row[1] else 0.0
        
        self.rep_lbl_total.configure(text=f"إجمالي قيمة المشتريات\n{total_bought:g} ج.م")
        self.rep_lbl_paid.configure(text=f"إجمالي المدفوعات\n{total_paid:g} ج.م")
        self.rep_lbl_debt.configure(text=f"المديونية (الباقي له)\n{balance:g} ج.م")
        
        for item in self.rep_tree.get_children(): self.rep_tree.delete(item)
        self.cursor.execute("SELECT id, date, total, discount, paid, status FROM purchases WHERE supplier_id=? ORDER BY id DESC", (sup_id,))
        for row in self.cursor.fetchall():
            disc = row[3] if row[3] else 0.0
            rem = row[2] - row[4]
            self.rep_tree.insert("", "end", values=(row[0], row[1], f"{row[2]:g}", f"{disc:g}", f"{row[4]:g}", f"{rem:g}", row[5]))

    def open_selected_purchase_from_report(self, event=None):
        selected = self.rep_tree.selection()
        if not selected:
            return messagebox.showwarning("تنبيه", "برجاء تحديد فاتورة من الجدول أولاً!")
        try:
            p_id = int(self.rep_tree.item(selected[0])['values'][0])
            self.load_purchase_invoice_by_id(p_id)
            self.sup_tabs.set("فاتورة مشتريات (استلام بضاعة)")
        except (ValueError, IndexError) as e:
            messagebox.showerror("خطأ", f"حدث خطأ أثناء تحميل بيانات الفاتورة:\n{e}")

    def print_selected_purchase_from_report(self):
        selected = self.rep_tree.selection()
        if not selected:
            return messagebox.showwarning("تنبيه", "برجاء تحديد فاتورة من الجدول أولاً لطباعتها!")
        try:
            p_id = int(self.rep_tree.item(selected[0])['values'][0])
            self.load_purchase_invoice_by_id(p_id)
            self.print_current_purchase_invoice()
        except (ValueError, IndexError) as e:
            messagebox.showerror("خطأ", f"حدث خطأ أثناء طباعة الفاتورة:\n{e}")

    def show_purchase_details(self, event):
        self.open_selected_purchase_from_report(event)


    def export_supplier_report(self):
        sup_str = self.rep_sup_combo.get()
        if not sup_str: return messagebox.showwarning("تنبيه", "برجاء اختيار مورد للتصدير.")
        file_path = filedialog.asksaveasfilename(defaultextension=".txt", initialfile=f"كشف_حساب_{sup_str}.txt", title="حفظ كشف الحساب", filetypes=[("Text Files", "*.txt")])
        if not file_path: return
        
        try:
            with open(file_path, "w", encoding="utf-8") as file:
                file.write("====================================================\n")
                file.write(f"                كشف حساب المورد: {sup_str}\n")
                file.write(f"                التاريخ: {datetime.datetime.now().strftime('%Y-%m-%d %H:%M')}\n")
                file.write("====================================================\n\n")
                file.write("--- الملخص المالي ---\n")
                file.write(self.rep_lbl_total.cget("text").replace("\n", ": ") + "\n")
                file.write(self.rep_lbl_paid.cget("text").replace("\n", ": ") + "\n")
                file.write(self.rep_lbl_debt.cget("text").replace("\n", ": ") + "\n\n")
                file.write("--- سجل الفواتير ---\n")
                file.write(f"{'رقم':<10} | {'التاريخ':<20} | {'الإجمالي':<10} | {'الخصم':<10} | {'المدفوع':<10} | {'الآجل':<10} | {'الحالة':<10}\n")
                file.write("-" * 90 + "\n")
                for item in self.rep_tree.get_children():
                    v = self.rep_tree.item(item)['values']
                    file.write(f"{v[0]:<10} | {v[1]:<20} | {v[2]:<10} | {v[3]:<10} | {v[4]:<10} | {v[5]:<10} | {v[6]:<10}\n")
            messagebox.showinfo("تم التصدير", f"تم حفظ كشف الحساب في:\n{file_path}")
        except Exception as e:
            messagebox.showerror("خطأ", f"فشل الحفظ:\n{e}")

    # ==========================================
    # 4. المرتجعات
    # ==========================================
    def setup_returns_tab(self, tab):
        search_frame = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        search_frame.pack(fill="x", pady=10, padx=20)
        
        ctk.CTkLabel(search_frame, text="أدخل رقم فاتورة المشتريات المراد إرجاعها:", font=ctk.CTkFont(size=15, weight="bold")).pack(side="right", padx=10, pady=12)
        self.ret_invoice_id = ctk.CTkEntry(search_frame, width=150, justify="center", font=("Arial", 14, "bold"))
        self.ret_invoice_id.pack(side="right", padx=10, pady=12)
        ctk.CTkButton(search_frame, text="🔍 بحث وعرض", font=ctk.CTkFont(weight="bold"), fg_color="#1f538d", hover_color="#194271", command=self.load_purchase_invoice).pack(side="right", padx=10, pady=12)
        
        self.ret_info_frame = ctk.CTkFrame(tab, fg_color="#1e272e", corner_radius=12)
        self.ret_info_frame.pack(fill="x", padx=20, pady=10)
        
        ctk.CTkLabel(self.ret_info_frame, text="المورد:", font=ctk.CTkFont(weight="bold")).grid(row=0, column=3, padx=15, pady=10, sticky="e")
        self.ret_inv_sup = ctk.CTkLabel(self.ret_info_frame, text="---", text_color="#3498db", font=ctk.CTkFont(size=15, weight="bold"))
        self.ret_inv_sup.grid(row=0, column=2, padx=15, pady=10, sticky="e")
        
        ctk.CTkLabel(self.ret_info_frame, text="التاريخ:", font=ctk.CTkFont(weight="bold")).grid(row=0, column=1, padx=15, pady=10, sticky="e")
        self.ret_inv_date = ctk.CTkLabel(self.ret_info_frame, text="---", font=ctk.CTkFont(size=14))
        self.ret_inv_date.grid(row=0, column=0, padx=15, pady=10, sticky="e")
        
        ctk.CTkLabel(self.ret_info_frame, text="الإجمالي:", font=ctk.CTkFont(weight="bold")).grid(row=1, column=3, padx=15, pady=10, sticky="e")
        self.ret_inv_total = ctk.CTkLabel(self.ret_info_frame, text="0 ج.م", font=ctk.CTkFont(size=15, weight="bold"), text_color="#2ecc71")
        self.ret_inv_total.grid(row=1, column=2, padx=15, pady=10, sticky="e")
        
        ctk.CTkLabel(self.ret_info_frame, text="الحالة:", font=ctk.CTkFont(weight="bold")).grid(row=1, column=1, padx=15, pady=10, sticky="e")
        self.ret_inv_status = ctk.CTkLabel(self.ret_info_frame, text="---", font=ctk.CTkFont(size=14))
        self.ret_inv_status.grid(row=1, column=0, padx=15, pady=10, sticky="e")

        items_tree_frame = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        items_tree_frame.pack(expand=True, fill="both", padx=20, pady=5)
        
        self.ret_items_tree = ttk.Treeview(items_tree_frame, columns=('name', 'qty', 'cost', 'total'), show='headings', height=5)
        self.ret_items_tree.heading('name', text='اسم المنتج')
        self.ret_items_tree.heading('qty', text='الكمية المستلمة')
        self.ret_items_tree.heading('cost', text='التكلفة للوحدة')
        self.ret_items_tree.heading('total', text='الإجمالي')
        for col in self.ret_items_tree['columns']: self.ret_items_tree.column(col, anchor='center')
        self.ret_items_tree.pack(expand=True, fill="both", padx=10, pady=10)

        self.btn_process_return = ctk.CTkButton(tab, text="⚠️ تأكيد إرجاع الفاتورة بالكامل للمورد", font=ctk.CTkFont(size=16, weight="bold"), fg_color="#c0392b", hover_color="#922b21", state="disabled", height=45, corner_radius=10, command=self.process_purchase_return)
        self.btn_process_return.pack(pady=15, padx=20, fill="x")
        self.current_return_inv = None


    def load_purchase_invoice(self):
        p_id = self.ret_invoice_id.get().strip()
        if not p_id: return
        for item in self.ret_items_tree.get_children(): self.ret_items_tree.delete(item)
            
        try:
            self.cursor.execute("SELECT p.date, s.name, p.total, p.paid, p.status, p.supplier_id FROM purchases p JOIN suppliers s ON p.supplier_id = s.id WHERE p.id=?", (p_id,))
            inv = self.cursor.fetchone()
            if inv:
                self.ret_inv_date.configure(text=inv[0])
                self.ret_inv_sup.configure(text=inv[1])
                self.ret_inv_total.configure(text=f"{inv[2]:g} ج.م")
                status_color = "#e74c3c" if inv[4] == 'مرتجع' else "#2ecc71"
                self.ret_inv_status.configure(text=inv[4], text_color=status_color)
                self.current_return_inv = (p_id, inv[2], inv[3], inv[4], inv[5]) 
                
                self.cursor.execute("SELECT p.name, pi.qty, pi.cost FROM purchase_items pi JOIN products p ON pi.product_id = p.id WHERE pi.purchase_id=?", (p_id,))
                for it in self.cursor.fetchall():
                    self.ret_items_tree.insert("", "end", values=(it[0], f"{it[1]:g}", f"{it[2]:g}", f"{(it[1]*it[2]):g}"))
                
                if inv[4] == 'مكتملة': self.btn_process_return.configure(state="normal")
                else: self.btn_process_return.configure(state="disabled")
            else: messagebox.showerror("خطأ", "لا توجد فاتورة مشتريات بهذا الرقم.")
        except Exception as e: messagebox.showerror("خطأ", f"حدث خطأ: {e}")

    def process_purchase_return(self):
        if not self.current_return_inv: return
        p_id, total, paid, status, sup_id = self.current_return_inv
        
        msg = f"تأكيد استرجاع فاتورة {p_id}؟\n- سحب البضاعة من المخزن\n- تقليل المديونية"
        if paid > 0: msg += f"\n- استرداد ({paid:g} ج.م) للخزينة"
            
        if messagebox.askyesno("تأكيد المرتجع", msg):
            self.cursor.execute("UPDATE purchases SET status = 'مرتجع', synced = 0 WHERE id=?", (p_id,))
            self.cursor.execute("SELECT product_id, qty FROM purchase_items WHERE purchase_id=?", (p_id,))
            for item in self.cursor.fetchall():
                self.cursor.execute("UPDATE products SET stock = stock - ?, synced = 0 WHERE id=?", (item[1], item[0]))
                
            rem = total - paid
            self.cursor.execute("UPDATE suppliers SET balance = balance - ?, synced = 0 WHERE id=?", (rem, sup_id))
            
            if paid > 0:
                date_now = datetime.datetime.now().strftime("%Y-%m-%d")
                self.cursor.execute("INSERT INTO expenses (category, amount, note, date) VALUES (?, ?, ?, ?)", 
                                    ("استرداد مشتريات", -paid, f"استرداد نقدي لمرتجع فاتورة مورد {p_id}", date_now))
                                    
            self.db.commit()
            if hasattr(self.app, 'sync_mgr'):
                self.app.sync_mgr.trigger_instant_sync()
            messagebox.showinfo("نجاح", "تم استرجاع الفاتورة بنجاح.")
            self.load_purchase_invoice() 
            self.load_suppliers() 
            self.btn_process_return.configure(state="disabled")