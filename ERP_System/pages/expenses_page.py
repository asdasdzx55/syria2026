import customtkinter as ctk
import tkinter.messagebox as messagebox
from tkinter import ttk, filedialog
import sqlite3
import datetime

class ExpensesPage(ctk.CTkFrame):
    def __init__(self, parent, db_conn, app):
        super().__init__(parent, fg_color="transparent")
        self.db = db_conn
        self.cursor = self.db.cursor()
        self.app = app
        
        self.current_expense_id = None
        self.all_expense_ids = []
        self.all_partners_list = []
        self.all_categories_list = []
        
        # التأكد من وجود الأنسجة الأساسية
        self.cursor.execute("CREATE TABLE IF NOT EXISTS expense_categories (id INTEGER PRIMARY KEY, name TEXT UNIQUE)")
        self.cursor.execute("CREATE TABLE IF NOT EXISTS partners (id INTEGER PRIMARY KEY, name TEXT UNIQUE)")
        self.db.commit()
        
        self.setup_ui()

    def setup_shortcuts(self):
        top = self.winfo_toplevel()
        top.bind("<F5>", self._shortcut_save_expense)

    def _shortcut_save_expense(self, event):
        if self.winfo_ismapped():
            self.save_expense()

    def setup_ui(self):
        header_card = ctk.CTkFrame(self, fg_color="#1e272e", corner_radius=12)
        header_card.pack(fill="x", padx=15, pady=(10, 5))
        ctk.CTkLabel(header_card, text="💸 إدارة وتسجيل المصروفات العامة ومسحوبات الشركاء", font=ctk.CTkFont(size=22, weight="bold"), text_color="#2ecc71").pack(pady=12)

        self.exp_tabs = ctk.CTkTabview(self, corner_radius=12)
        self.exp_tabs.pack(expand=True, fill="both", padx=15, pady=(5, 15))

        self.exp_tabs.add("تسجيل وتصفح المصروفات")
        self.exp_tabs.add("سجل المصروفات وتقارير الشركاء")
        self.exp_tabs.add("إدارة الشركاء والتصنيفات")

        self.setup_entry_tab(self.exp_tabs.tab("تسجيل وتصفح المصروفات"))
        self.setup_report_tab(self.exp_tabs.tab("سجل المصروفات وتقارير الشركاء"))
        self.setup_manage_tab(self.exp_tabs.tab("إدارة الشركاء والتصنيفات"))

    def on_show(self):
        self.setup_shortcuts()
        self.load_categories()
        self.load_partners()
        self.refresh_expense_ids()
        self.load_expenses_log()

    # =========================================================
    # 1. تبويب تسجيل وتصفح المصروفات (مع شريط التنقل السابق والتالي)
    # =========================================================
    def setup_entry_tab(self, tab):
        # 1. شريط التنقل العلوي بين إيصالات المصروفات
        nav_card = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        nav_card.pack(fill="x", padx=10, pady=(10, 5))

        row_nav = ctk.CTkFrame(nav_card, fg_color="#1e272e", corner_radius=8)
        row_nav.pack(fill="x", padx=10, pady=8)

        ctk.CTkLabel(row_nav, text="تصفح وتعديل المصروفات:", font=ctk.CTkFont(weight="bold"), text_color="white").pack(side="right", padx=12, pady=5)
        
        ctk.CTkButton(row_nav, text="⏭️ الأخيرة", font=ctk.CTkFont(weight="bold"), width=85, fg_color="#34495e", hover_color="#2c3e50", command=lambda: self.navigate_expense('last')).pack(side="right", padx=3, pady=5)
        ctk.CTkButton(row_nav, text="▶️ التالية", font=ctk.CTkFont(weight="bold"), width=80, fg_color="#34495e", hover_color="#2c3e50", command=lambda: self.navigate_expense('next')).pack(side="right", padx=3, pady=5)
        ctk.CTkButton(row_nav, text="◀️ السابقة", font=ctk.CTkFont(weight="bold"), width=80, fg_color="#34495e", hover_color="#2c3e50", command=lambda: self.navigate_expense('prev')).pack(side="right", padx=3, pady=5)
        ctk.CTkButton(row_nav, text="⏮️ الأولى", font=ctk.CTkFont(weight="bold"), width=85, fg_color="#34495e", hover_color="#2c3e50", command=lambda: self.navigate_expense('first')).pack(side="right", padx=3, pady=5)

        ctk.CTkButton(row_nav, text="🆕 إيصال جديد", font=ctk.CTkFont(weight="bold"), width=110, fg_color="#16a085", hover_color="#117864", command=self.new_expense_form).pack(side="right", padx=10, pady=5)
        self.btn_edit_exp = ctk.CTkButton(row_nav, text="✏️ تعديل", font=ctk.CTkFont(weight="bold"), width=90, fg_color="#e67e22", hover_color="#d35400", state="disabled", command=self.save_expense)
        self.btn_edit_exp.pack(side="right", padx=3, pady=5)
        self.btn_delete_exp = ctk.CTkButton(row_nav, text="🗑️ حذف الإيصال", font=ctk.CTkFont(weight="bold"), width=110, fg_color="#c0392b", hover_color="#922b21", state="disabled", command=self.delete_expense)
        self.btn_delete_exp.pack(side="left", padx=10, pady=5)

        # 2. بطاقة إدخال بيانات المصروف
        form_card = ctk.CTkFrame(tab, corner_radius=12, fg_color="#1e272e")
        form_card.pack(fill="x", padx=10, pady=10)

        header_form = ctk.CTkFrame(form_card, fg_color="#1f538d", corner_radius=8)
        header_form.pack(fill="x", padx=12, pady=(12, 5))
        self.lbl_form_header = ctk.CTkLabel(header_form, text="🧾 إيصال مصروف جديد (خصم مباشر من الخزينة)", font=ctk.CTkFont(size=16, weight="bold"), text_color="white")
        self.lbl_form_header.pack(side="right", padx=15, pady=8)

        grid_frame = ctk.CTkFrame(form_card, fg_color="transparent")
        grid_frame.pack(fill="x", padx=15, pady=10)

        # السطر الأول: بند المصروف والتصنيف
        ctk.CTkLabel(grid_frame, text="بند / تصنيف المصروف:", font=ctk.CTkFont(size=14, weight="bold")).grid(row=0, column=0, padx=10, pady=8, sticky="e")
        self.exp_category = ctk.CTkComboBox(grid_frame, values=[], width=220, font=("Arial", 14), command=self.on_category_changed)
        self.exp_category.grid(row=0, column=1, padx=5, pady=8, sticky="w")

        ctk.CTkButton(grid_frame, text="➕ بند جديد", width=90, fg_color="#8e44ad", hover_color="#732d91", command=self.quick_add_category_popup).grid(row=0, column=2, padx=5, pady=8, sticky="w")

        # السطر الثاني: قسم مسحوبات الشركاء (يظهر تلقائياً عند اختيار مسحوبات الإدارة/الشركاء)
        self.partner_card = ctk.CTkFrame(grid_frame, fg_color="#2c3e50", corner_radius=10)
        self.partner_card.grid(row=1, column=0, columnspan=3, sticky="ew", padx=5, pady=6)
        
        ctk.CTkLabel(self.partner_card, text="👤 اسم الشريك / المالك الساحب:", font=ctk.CTkFont(size=14, weight="bold"), text_color="#f1c40f").pack(side="right", padx=10, pady=8)
        self.exp_partner_combo = ctk.CTkComboBox(self.partner_card, values=[], width=220, font=("Arial", 14, "bold"))
        self.exp_partner_combo.pack(side="right", padx=5, pady=8)
        ctk.CTkButton(self.partner_card, text="➕ شريك جديد", width=100, fg_color="#27ae60", hover_color="#1e8449", command=self.quick_add_partner_popup).pack(side="right", padx=10, pady=8)

        # السطر الثالث: المبلغ المنصرف
        ctk.CTkLabel(grid_frame, text="المبلغ المنصرف (ج.م):", font=ctk.CTkFont(size=15, weight="bold")).grid(row=2, column=0, padx=10, pady=8, sticky="e")
        self.exp_amount = ctk.CTkEntry(grid_frame, placeholder_text="0.00", width=180, justify="center", font=("Arial", 16, "bold"))
        self.exp_amount.grid(row=2, column=1, padx=5, pady=8, sticky="w")

        # السطر الرابع: البيان والملاحظات
        ctk.CTkLabel(grid_frame, text="البيان / ملاحظات التفصيلية:", font=ctk.CTkFont(size=14, weight="bold")).grid(row=3, column=0, padx=10, pady=8, sticky="e")
        self.exp_note = ctk.CTkEntry(grid_frame, placeholder_text="اكتب تفاصيل أو سبب المصروف...", width=380, font=("Arial", 14))
        self.exp_note.grid(row=3, column=1, columnspan=2, padx=5, pady=8, sticky="w")

        # السطر الخامس: التاريخ وساعة الإدخال
        ctk.CTkLabel(grid_frame, text="تاريخ الإيصال:", font=ctk.CTkFont(weight="bold")).grid(row=4, column=0, padx=10, pady=8, sticky="e")
        self.exp_date_ent = ctk.CTkEntry(grid_frame, width=200, justify="center", font=("Arial", 13))
        self.exp_date_ent.insert(0, datetime.datetime.now().strftime("%Y-%m-%d %H:%M"))
        self.exp_date_ent.grid(row=4, column=1, padx=5, pady=8, sticky="w")

        # أزرار الحفظ والحالة
        self.btn_save_exp = ctk.CTkButton(form_card, text="💾 تسجيل المصروف وخصمه من الخزينة (F5)", font=ctk.CTkFont(size=16, weight="bold"), fg_color="#27ae60", hover_color="#1e8449", height=45, command=self.save_expense)
        self.btn_save_exp.pack(fill="x", padx=30, pady=(10, 5))

        self.status_label = ctk.CTkLabel(form_card, text="", font=ctk.CTkFont(size=15, weight="bold"))
        self.status_label.pack(pady=5)

        self.exp_amount.bind("<Return>", lambda e: self.exp_note.focus())
        self.exp_note.bind("<Return>", lambda e: self.save_expense())

    def show_status(self, msg, color="green"):
        self.status_label.configure(text=msg, text_color=color)
        self.after(3000, lambda: self.status_label.configure(text=""))

    def on_category_changed(self, choice=None):
        cat = choice or self.exp_category.get()
        if "مسحوبات" in cat or "إدارة" in cat or "شركاء" in cat:
            self.partner_card.grid()
        else:
            self.partner_card.grid_remove()

    # =========================================================
    # 2. تبويب سجل المصروفات وتقارير الشركاء (مع الفلترة الشاملة)
    # =========================================================
    def setup_report_tab(self, tab):
        filter_card = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        filter_card.pack(fill="x", padx=10, pady=(10, 5))

        row_filter = ctk.CTkFrame(filter_card, fg_color="transparent")
        row_filter.pack(fill="x", padx=10, pady=10)

        ctk.CTkLabel(row_filter, text="فلترة حسب البند:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(5, 2))
        self.rep_cat_combo = ctk.CTkComboBox(row_filter, values=["الكل"], width=160, command=self.load_expenses_log)
        self.rep_cat_combo.pack(side="right", padx=5)

        ctk.CTkLabel(row_filter, text="الشريك الساحب:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(10, 2))
        self.rep_partner_combo = ctk.CTkComboBox(row_filter, values=["جميع الشركاء"], width=170, command=self.load_expenses_log)
        self.rep_partner_combo.pack(side="right", padx=5)

        ctk.CTkLabel(row_filter, text="بحث:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(10, 2))
        self.rep_search_var = ctk.StringVar()
        self.rep_search_var.trace("w", lambda *args: self.load_expenses_log())
        self.rep_search_ent = ctk.CTkEntry(row_filter, textvariable=self.rep_search_var, placeholder_text="🔍 بحث بالبيان...", width=140)
        self.rep_search_ent.pack(side="right", padx=5)

        ctk.CTkButton(row_filter, text="📊 تحديث", width=80, fg_color="#f39c12", hover_color="#d68910", command=self.load_expenses_log).pack(side="left", padx=5)
        ctk.CTkButton(row_filter, text="💾 تصدير التقرير", width=110, fg_color="#27ae60", hover_color="#1e8449", command=self.export_expenses_report).pack(side="left", padx=5)

        # بطاقات ملخص المبالغ والشركاء
        summary_frame = ctk.CTkFrame(tab, fg_color="transparent")
        summary_frame.pack(fill="x", padx=10, pady=5)

        self.lbl_sum_total = ctk.CTkLabel(summary_frame, text="إجمالي المصروفات العام\n0 ج.م", font=ctk.CTkFont(size=16, weight="bold"), fg_color="#c0392b", text_color="white", width=220, height=65, corner_radius=10)
        self.lbl_sum_total.pack(side="left", padx=5, expand=True)

        self.lbl_sum_management = ctk.CTkLabel(summary_frame, text="إجمالي مسحوبات الإدارة/الشركاء\n0 ج.م", font=ctk.CTkFont(size=16, weight="bold"), fg_color="#8e44ad", text_color="white", width=240, height=65, corner_radius=10)
        self.lbl_sum_management.pack(side="left", padx=5, expand=True)

        self.lbl_sum_partner = ctk.CTkLabel(summary_frame, text="مسحوبات الشريك المحدد\n0 ج.م", font=ctk.CTkFont(size=16, weight="bold"), fg_color="#1f538d", text_color="white", width=220, height=65, corner_radius=10)
        self.lbl_sum_partner.pack(side="left", padx=5, expand=True)

        # جدول سجل المصروفات
        tree_card = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        tree_card.pack(expand=True, fill="both", padx=10, pady=(5, 10))

        self.exp_tree = ttk.Treeview(tree_card, columns=('id', 'date', 'category', 'partner', 'amount', 'note'), show='headings')
        self.exp_tree.heading('id', text='رقم الإيصال')
        self.exp_tree.heading('date', text='التاريخ والوقت')
        self.exp_tree.heading('category', text='بند المصروف')
        self.exp_tree.heading('partner', text='اسم الشريك الساحب')
        self.exp_tree.heading('amount', text='المبلغ (ج.م)')
        self.exp_tree.heading('note', text='البيان والملاحظات')

        self.exp_tree.column('id', width=80, anchor='center')
        self.exp_tree.column('date', width=140, anchor='center')
        self.exp_tree.column('category', width=140, anchor='center')
        self.exp_tree.column('partner', width=140, anchor='center')
        self.exp_tree.column('amount', width=110, anchor='center')
        self.exp_tree.column('note', width=260, anchor='center')
        self.exp_tree.pack(expand=True, fill="both", padx=10, pady=10)

        self.exp_tree.bind("<Double-1>", self.on_log_double_click)
        self.exp_tree.bind("<Return>", self.on_log_double_click)

    # =========================================================
    # 3. تبويب إدارة الشركاء وتصنيفات المصروفات
    # =========================================================
    def setup_manage_tab(self, tab):
        panes_frame = ctk.CTkFrame(tab, fg_color="transparent")
        panes_frame.pack(expand=True, fill="both", padx=10, pady=10)

        # قسم إدارة الشركاء (الجهة اليسرى)
        partner_box = ctk.CTkFrame(panes_frame, corner_radius=12, fg_color="#2c3e50")
        partner_box.pack(side="left", expand=True, fill="both", padx=5)

        header_p = ctk.CTkFrame(partner_box, fg_color="#8e44ad", corner_radius=8)
        header_p.pack(fill="x", padx=10, pady=(10, 5))
        ctk.CTkLabel(header_p, text="👥 قائمة شركاء الشركة / المالكين", font=ctk.CTkFont(size=16, weight="bold"), text_color="white").pack(pady=6)

        row_add_p = ctk.CTkFrame(partner_box, fg_color="transparent")
        row_add_p.pack(fill="x", padx=10, pady=8)

        self.ent_new_partner = ctk.CTkEntry(row_add_p, placeholder_text="اسم الشريك الجديد...", width=180, font=("Arial", 14))
        self.ent_new_partner.pack(side="right", padx=5)
        ctk.CTkButton(row_add_p, text="➕ إضافة شريك", font=ctk.CTkFont(weight="bold"), fg_color="#27ae60", hover_color="#1e8449", command=self.add_partner).pack(side="right", padx=5)

        self.partner_tree = ttk.Treeview(partner_box, columns=('id', 'name', 'total_withdrawn'), show='headings', height=8)
        self.partner_tree.heading('id', text='ID')
        self.partner_tree.heading('name', text='اسم الشريك')
        self.partner_tree.heading('total_withdrawn', text='إجمالي المسحوبات')
        self.partner_tree.column('id', width=50, anchor='center')
        self.partner_tree.column('name', width=160, anchor='center')
        self.partner_tree.column('total_withdrawn', width=130, anchor='center')
        self.partner_tree.pack(expand=True, fill="both", padx=10, pady=(10, 5))

        ctk.CTkButton(partner_box, text="🗑️ حذف الشريك المحدد (كلمة المرور مطلوبة)", font=ctk.CTkFont(weight="bold"), fg_color="#c0392b", hover_color="#922b21", command=self.delete_partner).pack(pady=(0, 10), fill="x", padx=10)

        # قسم إدارة تصنيفات المصروفات (الجهة اليمنى)
        cat_box = ctk.CTkFrame(panes_frame, corner_radius=12, fg_color="#2c3e50")
        cat_box.pack(side="right", expand=True, fill="both", padx=5)

        header_c = ctk.CTkFrame(cat_box, fg_color="#1f538d", corner_radius=8)
        header_c.pack(fill="x", padx=10, pady=(10, 5))
        ctk.CTkLabel(header_c, text="🏷️ تصنيفات وبنود المصروفات", font=ctk.CTkFont(size=16, weight="bold"), text_color="white").pack(pady=6)

        row_add_c = ctk.CTkFrame(cat_box, fg_color="transparent")
        row_add_c.pack(fill="x", padx=10, pady=8)

        self.ent_new_cat = ctk.CTkEntry(row_add_c, placeholder_text="اسم البند الجديد...", width=180, font=("Arial", 14))
        self.ent_new_cat.pack(side="right", padx=5)
        ctk.CTkButton(row_add_c, text="➕ إضافة بند", font=ctk.CTkFont(weight="bold"), fg_color="#27ae60", hover_color="#1e8449", command=self.add_category).pack(side="right", padx=5)

        self.cat_tree = ttk.Treeview(cat_box, columns=('id', 'name'), show='headings', height=8)
        self.cat_tree.heading('id', text='ID')
        self.cat_tree.heading('name', text='اسم بند المصروف')
        self.cat_tree.column('id', width=60, anchor='center')
        self.cat_tree.column('name', width=240, anchor='center')
        self.cat_tree.pack(expand=True, fill="both", padx=10, pady=(10, 5))

        ctk.CTkButton(cat_box, text="🗑️ حذف البند المحدد", font=ctk.CTkFont(weight="bold"), fg_color="#c0392b", hover_color="#922b21", command=self.delete_category).pack(pady=(0, 10), fill="x", padx=10)


    # =========================================================
    # دوال تحميل البيانات وتنفيذ العمليات (Core Logic)
    # =========================================================
    def refresh_expense_ids(self):
        self.cursor.execute("SELECT id FROM expenses ORDER BY id ASC")
        self.all_expense_ids = [r[0] for r in self.cursor.fetchall()]

    def load_categories(self):
        self.cursor.execute("SELECT name FROM expense_categories ORDER BY name")
        cats = [row[0] for row in self.cursor.fetchall()]
        self.all_categories_list = cats
        if not cats:
            cats = ["أخرى"]
            
        self.exp_category.configure(values=cats)
        if hasattr(self, 'rep_cat_combo'):
            self.rep_cat_combo.configure(values=["الكل"] + cats)
            
        if not self.exp_category.get() or self.exp_category.get() not in cats:
            self.exp_category.set(cats[0])
            
        self.on_category_changed()
        self.load_categories_tree()

    def load_partners(self):
        self.cursor.execute("SELECT name FROM partners ORDER BY name")
        partners = [row[0] for row in self.cursor.fetchall()]
        self.all_partners_list = partners
        if not partners:
            partners = ["المالك / المدير العام"]
            
        self.exp_partner_combo.configure(values=partners)
        if hasattr(self, 'rep_partner_combo'):
            self.rep_partner_combo.configure(values=["جميع الشركاء"] + partners)
            
        if not self.exp_partner_combo.get() or self.exp_partner_combo.get() not in partners:
            self.exp_partner_combo.set(partners[0])
            
        self.load_partners_tree()

    def load_partners_tree(self):
        if not hasattr(self, 'partner_tree'): return
        for item in self.partner_tree.get_children(): self.partner_tree.delete(item)
        self.cursor.execute("SELECT id, name FROM partners ORDER BY id ASC")
        for p_id, p_name in self.cursor.fetchall():
            self.cursor.execute("SELECT SUM(amount) FROM expenses WHERE partner_name=? OR note LIKE ?", (p_name, f"%{p_name}%"))
            tot = self.cursor.fetchone()[0] or 0.0
            self.partner_tree.insert("", "end", values=(p_id, p_name, f"{tot:g} ج.م"))

    def load_categories_tree(self):
        if not hasattr(self, 'cat_tree'): return
        for item in self.cat_tree.get_children(): self.cat_tree.delete(item)
        self.cursor.execute("SELECT id, name FROM expense_categories ORDER BY id ASC")
        for c_id, c_name in self.cursor.fetchall():
            self.cat_tree.insert("", "end", values=(c_id, c_name))

    def load_expenses_log(self, *args):
        if not hasattr(self, 'exp_tree'): return
        for item in self.exp_tree.get_children(): self.exp_tree.delete(item)

        cat_filter = self.rep_cat_combo.get()
        partner_filter = self.rep_partner_combo.get()
        search_term = self.rep_search_var.get().strip().lower()

        query = "SELECT id, date, category, partner_name, amount, note FROM expenses WHERE 1=1"
        params = []

        if cat_filter and cat_filter != "الكل":
            query += " AND category=?"
            params.append(cat_filter)

        if partner_filter and partner_filter != "جميع الشركاء":
            query += " AND (partner_name=? OR note LIKE ?)"
            params.extend([partner_filter, f"%{partner_filter}%"])

        if search_term:
            query += " AND (LOWER(note) LIKE ? OR LOWER(category) LIKE ? OR LOWER(COALESCE(partner_name,'')) LIKE ?)"
            s = f"%{search_term}%"
            params.extend([s, s, s])

        query += " ORDER BY id DESC"
        self.cursor.execute(query, params)
        rows = self.cursor.fetchall()

        total_exp = 0.0
        total_mgmt = 0.0
        total_selected_partner = 0.0

        for r in rows:
            r_id, r_date, r_cat, r_partner, r_amount, r_note = r
            r_amount = r_amount or 0.0
            total_exp += r_amount
            p_display = r_partner if r_partner else "---"
            
            if "مسحوبات" in (r_cat or "") or "إدارة" in (r_cat or ""):
                total_mgmt += r_amount

            if partner_filter and partner_filter != "جميع الشركاء":
                if r_partner == partner_filter or (r_note and partner_filter in r_note):
                    total_selected_partner += r_amount

            self.exp_tree.insert("", "end", values=(r_id, r_date, r_cat, p_display, f"{r_amount:g}", r_note or ""))

        self.lbl_sum_total.configure(text=f"إجمالي المصروفات العام\n{total_exp:g} ج.م")
        self.lbl_sum_management.configure(text=f"إجمالي مسحوبات الإدارة/الشركاء\n{total_mgmt:g} ج.م")
        
        if partner_filter and partner_filter != "جميع الشركاء":
            self.lbl_sum_partner.configure(text=f"مسحوبات [{partner_filter}]\n{total_selected_partner:g} ج.م")
        else:
            self.lbl_sum_partner.configure(text="مسحوبات الشريك المحدد\n0 ج.م")

    # =========================================================
    # دوال التنقل والتعديل بين الإيصالات (Navigation & Editing)
    # =========================================================
    def navigate_expense(self, direction):
        self.refresh_expense_ids()
        if not self.all_expense_ids:
            return messagebox.showinfo("تنبيه", "لا توجد مصروفات مسجلة للنظام حتى الآن!")

        if direction == 'first':
            self.load_expense_by_id(self.all_expense_ids[0])
        elif direction == 'last':
            self.load_expense_by_id(self.all_expense_ids[-1])
        elif direction == 'prev':
            if self.current_expense_id is None:
                self.load_expense_by_id(self.all_expense_ids[-1])
            else:
                try:
                    idx = self.all_expense_ids.index(self.current_expense_id)
                    if idx > 0:
                        self.load_expense_by_id(self.all_expense_ids[idx - 1])
                    else:
                        messagebox.showinfo("تنبيه", "هذا هو أول إيصال مصروف مسجل!")
                except ValueError:
                    self.load_expense_by_id(self.all_expense_ids[-1])
        elif direction == 'next':
            if self.current_expense_id is None:
                return
            try:
                idx = self.all_expense_ids.index(self.current_expense_id)
                if idx < len(self.all_expense_ids) - 1:
                    self.load_expense_by_id(self.all_expense_ids[idx + 1])
                else:
                    self.new_expense_form()
            except ValueError:
                self.new_expense_form()


    def load_expense_by_id(self, exp_id):
        self.cursor.execute("SELECT id, category, amount, note, date, partner_name FROM expenses WHERE id=?", (exp_id,))
        row = self.cursor.fetchone()
        if not row:
            return messagebox.showerror("خطأ", "لم يتم العثور على الإيصال المطلوب!")

        self.current_expense_id = row[0]
        cat, amount, note, date_str, partner = row[1], row[2], row[3], row[4], row[5]

        if cat in self.all_categories_list:
            self.exp_category.set(cat)
        else:
            self.exp_category.set(cat or "أخرى")
        self.on_category_changed()

        if partner and partner in self.all_partners_list:
            self.exp_partner_combo.set(partner)

        self.exp_amount.delete(0, 'end')
        self.exp_amount.insert(0, f"{amount:g}")

        self.exp_note.delete(0, 'end')
        self.exp_note.insert(0, note or "")

        self.exp_date_ent.delete(0, 'end')
        self.exp_date_ent.insert(0, str(date_str or ""))

        self.lbl_form_header.configure(text=f"🧾 إيصال مصروف رقم #{exp_id} ({date_str})", text_color="#f1c40f")
        self.btn_save_exp.configure(text="🔄 حفظ التعديلات على الإيصال (F5)", fg_color="#2980b9", hover_color="#1f618d")
        self.btn_edit_exp.configure(state="normal")
        self.btn_delete_exp.configure(state="normal")
        self.show_status(f"💡 تم عرض إيصال رقم #{exp_id}", "#3498db")

    def new_expense_form(self):
        self.current_expense_id = None
        self.exp_amount.delete(0, 'end')
        self.exp_note.delete(0, 'end')
        self.exp_date_ent.delete(0, 'end')
        self.exp_date_ent.insert(0, datetime.datetime.now().strftime("%Y-%m-%d %H:%M"))
        
        self.lbl_form_header.configure(text="🧾 إيصال مصروف جديد (خصم مباشر من الخزينة)", text_color="white")
        self.btn_save_exp.configure(text="💾 تسجيل المصروف وخصمه من الخزينة (F5)", fg_color="#27ae60", hover_color="#1e8449")
        self.btn_edit_exp.configure(state="disabled")
        self.btn_delete_exp.configure(state="disabled")
        self.on_category_changed()
        self.exp_amount.focus()

    def save_expense(self):
        try:
            cat = self.exp_category.get().strip()
            if not cat:
                return messagebox.showerror("خطأ", "برجاء اختيار أو كتابة بند المصروف!")

            p_text = self.exp_amount.get().strip()
            amount = float(p_text) if p_text else 0.0
            if amount <= 0:
                return messagebox.showerror("خطأ", "برجاء إدخال مبلغ صحيح أكبر من الصفر!")

            note = self.exp_note.get().strip()
            date_str = self.exp_date_ent.get().strip() or datetime.datetime.now().strftime("%Y-%m-%d %H:%M")
            partner_name = None

            if "مسحوبات" in cat or "إدارة" in cat or "شركاء" in cat:
                partner_name = self.exp_partner_combo.get().strip()
                if partner_name:
                    if partner_name not in note:
                        note = f"[{partner_name}] {note}".strip()

            if self.current_expense_id is not None:
                # تحديث إيصال المصروف الحالي
                self.cursor.execute("UPDATE expenses SET category=?, amount=?, note=?, date=?, partner_name=? WHERE id=?",
                                    (cat, amount, note, date_str, partner_name, self.current_expense_id))
                self.db.commit()
                messagebox.showinfo("نجاح", f"تم تحديث إيصال المصروف رقم #{self.current_expense_id} بنجاح!")
            else:
                # إضافة إيصال مصروف جديد
                self.cursor.execute("INSERT INTO expenses (category, amount, note, date, partner_name) VALUES (?, ?, ?, ?, ?)",
                                    (cat, amount, note, date_str, partner_name))
                self.db.commit()
                new_id = self.cursor.lastrowid
                messagebox.showinfo("نجاح", f"تم تسجيل المصروف رقم #{new_id} بنجاح وخصمه من الخزينة!")

            self.refresh_expense_ids()
            self.load_expenses_log()
            self.load_partners_tree()
            self.new_expense_form()
        except ValueError:
            messagebox.showerror("خطأ", "برجاء كتابة المبلغ كأرقام صحيحة!")
        except Exception as e:
            messagebox.showerror("خطأ غير متوقع", f"حدث خطأ أثناء حفظ المصروف:\n{e}")

    def delete_expense(self):
        if self.current_expense_id is None: return
        confirm = messagebox.askyesno("تأكيد الحذف", f"هل أنت متأكد من حذف إيصال المصروف رقم #{self.current_expense_id} نهائياً؟")
        if confirm:
            try:
                self.cursor.execute("DELETE FROM expenses WHERE id=?", (self.current_expense_id,))
                self.db.commit()
                messagebox.showinfo("نجاح", "تم حذف إيصال المصروف بنجاح!")
                self.refresh_expense_ids()
                self.load_expenses_log()
                self.load_partners_tree()
                self.new_expense_form()
            except Exception as e:
                messagebox.showerror("خطأ", f"حدث خطأ أثناء الحذف:\n{e}")

    def on_log_double_click(self, event=None):
        selected = self.exp_tree.selection()
        if not selected: return
        try:
            exp_id = int(self.exp_tree.item(selected[0])['values'][0])
            self.load_expense_by_id(exp_id)
            self.exp_tabs.set("تسجيل وتصفح المصروفات")
        except (ValueError, IndexError):
            pass

    # =========================================================
    # دوال إدارة وتصنيف الشركاء والبنوود السرعة
    # =========================================================
    def quick_add_category_popup(self):
        dialog = ctk.CTkInputDialog(text="أدخل اسم بند المصروف الجديد:", title="إضافة بند مصروف")
        val = dialog.get_input()
        if val and val.strip():
            c_name = val.strip()
            try:
                self.cursor.execute("INSERT INTO expense_categories (name) VALUES (?)", (c_name,))
                self.db.commit()
                self.load_categories()
                self.exp_category.set(c_name)
                self.on_category_changed()
                messagebox.showinfo("نجاح", f"تم إضافة البند ({c_name}) بنجاح!")
            except sqlite3.IntegrityError:
                messagebox.showwarning("تنبيه", "هذا البند مسجل بالفعل!")

    def quick_add_partner_popup(self):
        dialog = ctk.CTkInputDialog(text="أدخل اسم الشريك / المالك الجديد:", title="إضافة شريك جديد")
        val = dialog.get_input()
        if val and val.strip():
            p_name = val.strip()
            try:
                self.cursor.execute("INSERT INTO partners (name) VALUES (?)", (p_name,))
                self.db.commit()
                self.load_partners()
                self.exp_partner_combo.set(p_name)
                messagebox.showinfo("نجاح", f"تم إضافة الشريك ({p_name}) بنجاح!")
            except sqlite3.IntegrityError:
                messagebox.showwarning("تنبيه", "هذا الشريك مسجل بالفعل!")

    def add_partner(self):
        p_name = self.ent_new_partner.get().strip()
        if not p_name: return
        try:
            self.cursor.execute("INSERT INTO partners (name) VALUES (?)", (p_name,))
            self.db.commit()
            self.ent_new_partner.delete(0, 'end')
            self.load_partners()
            messagebox.showinfo("نجاح", f"تم إضافة الشريك ({p_name}) بنجاح!")
        except sqlite3.IntegrityError:
            messagebox.showwarning("تنبيه", "اسم الشريك مسجل بالفعل!")

    def delete_partner(self):
        selected = self.partner_tree.selection()
        if not selected:
            return messagebox.showwarning("تنبيه", "برجاء تحديد شريك من القائمة أولاً لحذفه!")
            
        p_item = self.partner_tree.item(selected[0])['values']
        p_id = p_item[0]
        p_name = str(p_item[1])

        # 1. المطالبة بكلمة مرور المدير للأهمية الأمنية
        dialog = ctk.CTkInputDialog(text=f"🔒 للتحقق الأمني والمتابعة:\nأدخل كلمة مرور المدير لتأكيد حذف الشريك ({p_name}):", title="تأكيد كلمة المرور للأهمية")
        entered_pwd = dialog.get_input()
        if entered_pwd is None or not entered_pwd.strip():
            return

        self.cursor.execute("SELECT value FROM settings WHERE key='admin_password'")
        res = self.cursor.fetchone()
        admin_pwd = res[0] if res else "1234"

        if entered_pwd.strip() != admin_pwd:
            return messagebox.showerror("خطأ أمني", "❌ كلمة المرور غير صحيحة! تم إلغاء عملية الحذف.")

        confirm = messagebox.askyesno("تحذير نهائي", f"هل أنت متأكد بنسبة 100% من حذف الشريك ({p_name}) من النظام نهائياً؟")
        if confirm:
            try:
                self.cursor.execute("DELETE FROM partners WHERE id=?", (p_id,))
                self.db.commit()
                messagebox.showinfo("نجاح", f"✅ تم حذف الشريك ({p_name}) بنجاح.")
                self.load_partners()
            except Exception as e:
                messagebox.showerror("خطأ", f"حدث خطأ أثناء الحذف:\n{e}")

    def add_category(self):
        c_name = self.ent_new_cat.get().strip()
        if not c_name: return
        try:
            self.cursor.execute("INSERT INTO expense_categories (name) VALUES (?)", (c_name,))
            self.db.commit()
            self.ent_new_cat.delete(0, 'end')
            self.load_categories()
            messagebox.showinfo("نجاح", f"تم إضافة البند ({c_name}) بنجاح!")
        except sqlite3.IntegrityError:
            messagebox.showwarning("تنبيه", "اسم البند مسجل بالفعل!")

    def delete_category(self):
        selected = self.cat_tree.selection()
        if not selected:
            return messagebox.showwarning("تنبيه", "برجاء تحديد بند من القائمة أولاً لحذفه!")
            
        c_item = self.cat_tree.item(selected[0])['values']
        c_id = c_item[0]
        c_name = str(c_item[1])

        confirm = messagebox.askyesno("تأكيد الحذف", f"هل أنت متأكد من حذف البند ({c_name}) من التصنيفات؟")
        if confirm:
            try:
                self.cursor.execute("DELETE FROM expense_categories WHERE id=?", (c_id,))
                self.db.commit()
                messagebox.showinfo("نجاح", f"تم حذف البند ({c_name}) بنجاح.")
                self.load_categories()
            except Exception as e:
                messagebox.showerror("خطأ", f"حدث خطأ أثناء الحذف:\n{e}")


    def export_expenses_report(self):
        file_path = filedialog.asksaveasfilename(defaultextension=".txt", initialfile="تقرير_المصروفات_والشركاء.txt", title="حفظ تقرير المصروفات", filetypes=[("Text Files", "*.txt")])
        if not file_path: return
        try:
            with open(file_path, "w", encoding="utf-8") as file:
                file.write("====================================================\n")
                file.write("              تقرير المصروفات العامة ومسحوبات الشركاء\n")
                file.write(f"              التاريخ: {datetime.datetime.now().strftime('%Y-%m-%d %H:%M')}\n")
                file.write("====================================================\n\n")
                file.write(self.lbl_sum_total.cget("text").replace("\n", ": ") + "\n")
                file.write(self.lbl_sum_management.cget("text").replace("\n", ": ") + "\n")
                file.write(self.lbl_sum_partner.cget("text").replace("\n", ": ") + "\n\n")
                file.write(f"{'رقم':<8} | {'التاريخ':<18} | {'البند':<16} | {'الشريك':<16} | {'المبلغ':<12} | {'البيان':<30}\n")
                file.write("-" * 105 + "\n")
                for item in self.exp_tree.get_children():
                    v = self.exp_tree.item(item)['values']
                    file.write(f"{v[0]:<8} | {v[1]:<18} | {v[2]:<16} | {v[3]:<16} | {v[4]:<12} | {v[5]:<30}\n")
            messagebox.showinfo("نجاح", f"تم تصدير تقرير المصروفات في:\n{file_path}")
        except Exception as e:
            messagebox.showerror("خطأ", f"حدث خطأ أثناء التصدير:\n{e}")