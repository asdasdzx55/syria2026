import customtkinter as ctk
import tkinter.messagebox as messagebox
from tkinter import ttk, filedialog
import sqlite3
import datetime
import shutil
import os
import csv 
import sys # تم إضافته لاستخدامه في إعادة تشغيل البرنامج

class AdminPage(ctk.CTkFrame):
    def __init__(self, parent, db_conn, app):
        super().__init__(parent, fg_color="transparent")
        self.db = db_conn
        self.cursor = self.db.cursor()
        self.app = app
        
        self.cursor.execute("CREATE TABLE IF NOT EXISTS payment_methods (id INTEGER PRIMARY KEY, name TEXT UNIQUE, fee_percent REAL)")
        self.cursor.execute("CREATE TABLE IF NOT EXISTS expense_categories (id INTEGER PRIMARY KEY, name TEXT UNIQUE)")
        self.cursor.execute("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)")
        
        self.cursor.execute("SELECT COUNT(*) FROM expense_categories")
        if self.cursor.fetchone()[0] == 0:
            defaults = [("نثريات",), ("إيجار",), ("فواتير (كهرباء/مياه)",), ("صيانة",), ("رواتب عاملين",), ("سلف عاملين",), ("سداد موردين",), ("مشتريات بضاعة",), ("مسحوبات الإدارة",), ("أخرى",)]
            self.cursor.executemany("INSERT OR IGNORE INTO expense_categories (name) VALUES (?)", defaults)
            
        self.db.commit()
        
        from sync_manager import HybridSyncManager
        self.sync_mgr = HybridSyncManager(self.db, self.app)
        
        self.admin_emps_data = {}
        self.current_edit_pm_id = None 
        self.setup_ui()

    def setup_ui(self):
        ctk.CTkLabel(self, text="لوحة الإدارة، التحليلات، وإعدادات النظام", font=ctk.CTkFont(size=24, weight="bold")).pack(pady=10)
        
        self.admin_tabs = ctk.CTkTabview(self)
        self.admin_tabs.pack(expand=True, fill="both", padx=10, pady=10)
        
        self.admin_tabs.add("الملخص المالي والخزينة")
        self.admin_tabs.add("جرد وتقارير مفصلة") 
        self.admin_tabs.add("سجلات الخزينة والموظفين")
        self.admin_tabs.add("التحليلات والتقارير الذكية")
        self.admin_tabs.add("🌐 المتجر الإلكتروني والمزامنة الهجينة")
        self.admin_tabs.add("الإعدادات (الدفع والمصروفات)")
        self.admin_tabs.add("إعدادات النظام والفاتورة")
        
        self.setup_dashboard_tab(self.admin_tabs.tab("الملخص المالي والخزينة"))
        self.setup_advanced_reports_tab(self.admin_tabs.tab("جرد وتقارير مفصلة")) 
        self.setup_logs_and_employees_tab(self.admin_tabs.tab("سجلات الخزينة والموظفين"))
        self.setup_analytics_tab(self.admin_tabs.tab("التحليلات والتقارير الذكية"))
        self.setup_cloud_sync_tab(self.admin_tabs.tab("🌐 المتجر الإلكتروني والمزامنة الهجينة"))
        self.setup_financial_settings_tab(self.admin_tabs.tab("الإعدادات (الدفع والمصروفات)"))
        self.setup_security_tab(self.admin_tabs.tab("إعدادات النظام والفاتورة"))

    def on_show(self):
        self.refresh_admin_dashboard()
        self.refresh_analytics()
        self.load_payment_methods()
        self.load_expense_categories()
        self.load_partners_for_withdraw()
        self.load_logs_and_employees()
        self.load_store_settings() 
        self.load_suppliers_for_reports()
        self.load_cloud_settings_ui()

    def load_partners_for_withdraw(self):
        self.cursor.execute("CREATE TABLE IF NOT EXISTS partners (id INTEGER PRIMARY KEY, name TEXT UNIQUE)")
        self.cursor.execute("SELECT name FROM partners ORDER BY name")
        partners = [row[0] for row in self.cursor.fetchall()]
        if not partners:
            partners = ["المالك / المدير العام"]
        if hasattr(self, 'partner_withdraw_combo'):
            self.partner_withdraw_combo.configure(values=partners)
            if not self.partner_withdraw_combo.get() or self.partner_withdraw_combo.get() not in partners:
                self.partner_withdraw_combo.set(partners[0])

    def withdraw_cash(self):
        amount_str = self.ent_withdraw_amount.get().strip()
        partner_name = self.partner_withdraw_combo.get().strip() if hasattr(self, 'partner_withdraw_combo') else "المالك / المدير العام"
        note_text = self.ent_withdraw_note.get().strip() if hasattr(self, 'ent_withdraw_note') else ""
        if not partner_name:
            partner_name = "المالك / المدير العام"

        try:
            amount = float(amount_str)
            if amount <= 0:
                return messagebox.showerror("خطأ", "برجاء إدخال مبلغ سحب صحيح أكبر من الصفر!")
            
            date_now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M")
            full_note = f"[{partner_name}] {note_text}".strip()
            self.cursor.execute("INSERT INTO expenses (category, amount, note, date, partner_name) VALUES (?, ?, ?, ?, ?)",
                                ("مسحوبات الإدارة", amount, full_note, date_now, partner_name))
            self.db.commit()
            self.ent_withdraw_amount.delete(0, 'end')
            if hasattr(self, 'ent_withdraw_note'):
                self.ent_withdraw_note.delete(0, 'end')
            messagebox.showinfo("نجاح", f"تم تسجيل سحب مبلغ {amount:g} ج.م للشريك ({partner_name}) وخصمه من الخزينة بنجاح!")
            self.refresh_admin_dashboard()
            self.load_logs_and_employees()
        except ValueError:
            messagebox.showerror("خطأ", "برجاء إدخال المبلغ كأرقام صحيحة!")


    # ==========================================
    # دالة مساعدة لإنشاء قوائم التاريخ والوقت
    # ==========================================
    def build_datetime_picker(self, parent, def_hour, def_min):
        frame = ctk.CTkFrame(parent, fg_color="transparent")
        now = datetime.datetime.now()

        y_var = ctk.StringVar(value=str(now.year))
        m_var = ctk.StringVar(value=f"{now.month:02d}")
        d_var = ctk.StringVar(value=f"{now.day:02d}")
        h_var = ctk.StringVar(value=def_hour)
        min_var = ctk.StringVar(value=def_min)

        cb_d = ctk.CTkComboBox(frame, values=[f"{i:02d}" for i in range(1, 32)], variable=d_var, width=60)
        cb_m = ctk.CTkComboBox(frame, values=[f"{i:02d}" for i in range(1, 13)], variable=m_var, width=60)
        cb_y = ctk.CTkComboBox(frame, values=[str(i) for i in range(2023, 2035)], variable=y_var, width=75)

        cb_h = ctk.CTkComboBox(frame, values=[f"{i:02d}" for i in range(0, 24)], variable=h_var, width=60)
        cb_min = ctk.CTkComboBox(frame, values=[f"{i:02d}" for i in range(0, 60)], variable=min_var, width=60)

        # ترتيب عربي من اليمين لليسار (اليوم / الشهر / السنة)
        cb_d.pack(side="right", padx=2)
        ctk.CTkLabel(frame, text="/").pack(side="right")
        cb_m.pack(side="right", padx=2)
        ctk.CTkLabel(frame, text="/").pack(side="right")
        cb_y.pack(side="right", padx=2)

        ctk.CTkLabel(frame, text="   |   الساعة: ").pack(side="right")
        cb_h.pack(side="right", padx=2)
        ctk.CTkLabel(frame, text=":").pack(side="right")
        cb_min.pack(side="right", padx=2)

        # دالة لإرجاع التاريخ المجمع
        getter = lambda: f"{y_var.get()}-{m_var.get()}-{d_var.get()} {h_var.get()}:{min_var.get()}:00"
        return frame, getter

    # ==========================================
    # 1. تبويب الملخص المالي
    # ==========================================
    def setup_dashboard_tab(self, tab):
        cards_frame = ctk.CTkFrame(tab, fg_color="transparent")
        cards_frame.pack(fill="x", pady=20)
        
        self.lbl_total_sales = ctk.CTkLabel(cards_frame, text="المبيعات\n0 ج.م", font=ctk.CTkFont(size=18, weight="bold"), fg_color="#3498db", corner_radius=10, width=200, height=100)
        self.lbl_total_sales.pack(side="right", padx=10)
        
        self.lbl_total_exp = ctk.CTkLabel(cards_frame, text="المنصرفات\n0 ج.م", font=ctk.CTkFont(size=18, weight="bold"), fg_color="#e67e22", corner_radius=10, width=200, height=100)
        self.lbl_total_exp.pack(side="right", padx=10)

        self.lbl_net_cash = ctk.CTkLabel(cards_frame, text="السيولة بالخزينة\n0 ج.م", font=ctk.CTkFont(size=18, weight="bold"), fg_color="#2ecc71", corner_radius=10, width=200, height=100)
        self.lbl_net_cash.pack(side="right", padx=10)

        self.lbl_net_profit = ctk.CTkLabel(cards_frame, text="صافي الربح\n0 ج.م\n(0%)", font=ctk.CTkFont(size=18, weight="bold"), fg_color="#9b59b6", corner_radius=10, width=200, height=100)
        self.lbl_net_profit.pack(side="right", padx=10)

        withdraw_card = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        withdraw_card.pack(fill="x", pady=20, padx=20)

        header_w = ctk.CTkFrame(withdraw_card, fg_color="#c0392b", corner_radius=8)
        header_w.pack(fill="x", padx=12, pady=(12, 5))
        ctk.CTkLabel(header_w, text="💰 لوحة سحب أرباح / مسحوبات شخصية للشركاء والمالكين", font=ctk.CTkFont(size=16, weight="bold"), text_color="white").pack(pady=8)

        row_w = ctk.CTkFrame(withdraw_card, fg_color="transparent")
        row_w.pack(fill="x", padx=15, pady=15)

        ctk.CTkLabel(row_w, text="اختر الشريك / المالك الساحب:", font=ctk.CTkFont(size=14, weight="bold")).pack(side="right", padx=(10, 2))
        self.partner_withdraw_combo = ctk.CTkComboBox(row_w, values=[], width=220, font=("Arial", 14, "bold"))
        self.partner_withdraw_combo.pack(side="right", padx=5)

        ctk.CTkLabel(row_w, text="المبلغ (ج.م):", font=ctk.CTkFont(size=14, weight="bold")).pack(side="right", padx=(15, 2))
        self.ent_withdraw_amount = ctk.CTkEntry(row_w, placeholder_text="0.00", width=140, justify="center", font=("Arial", 16, "bold"))
        self.ent_withdraw_amount.pack(side="right", padx=5)

        ctk.CTkLabel(row_w, text="البيان / السبب:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(15, 2))
        self.ent_withdraw_note = ctk.CTkEntry(row_w, placeholder_text="سبب السحب...", width=180, font=("Arial", 13))
        self.ent_withdraw_note.pack(side="right", padx=5)

        ctk.CTkButton(row_w, text="💸 صرف وتأكيد مسحوبات الشريك", font=ctk.CTkFont(weight="bold"), fg_color="#c0392b", hover_color="#922b21", height=40, command=self.withdraw_cash).pack(side="left", padx=10)
        
        ctk.CTkButton(tab, text="🔄 تحديث البيانات والأرقام", font=ctk.CTkFont(weight="bold"), fg_color="#2980b9", hover_color="#1f618d", command=self.on_show).pack(pady=10)


    # ==========================================
    # 2. تبويب الجرد والتقارير المفصلة
    # ==========================================
    def setup_advanced_reports_tab(self, tab):
        filter_frame = ctk.CTkFrame(tab)
        filter_frame.pack(fill="x", pady=10, padx=20)
        
        row0 = ctk.CTkFrame(filter_frame, fg_color="transparent")
        row0.pack(fill="x", pady=5, padx=5)
        
        ctk.CTkLabel(row0, text="نوع الجرد:").pack(side="right", padx=5)
        self.report_type_combo = ctk.CTkComboBox(row0, values=["المبيعات والأرباح", "المصروفات", "المشتريات العامة", "حساب مورد محدد"], command=self.toggle_supplier_combo, width=150)
        self.report_type_combo.pack(side="right", padx=5)

        self.rep_sup_label = ctk.CTkLabel(row0, text="المورد:", text_color="gray")
        self.rep_sup_label.pack(side="right", padx=10)
        self.report_sup_combo = ctk.CTkComboBox(row0, values=[], state="disabled", width=150)
        self.report_sup_combo.pack(side="right", padx=5)

        ctk.CTkButton(row0, text="🔍 عرض الجرد", font=ctk.CTkFont(weight="bold"), fg_color="#2980b9", width=120, command=self.generate_advanced_report).pack(side="left", padx=20)

        row1 = ctk.CTkFrame(filter_frame, fg_color="transparent")
        row1.pack(fill="x", pady=5, padx=5)
        ctk.CTkLabel(row1, text="بداية الجرد (من):", width=100, anchor="e").pack(side="right", padx=5)
        self.start_dt_frame, self.get_start_dt = self.build_datetime_picker(row1, "00", "00")
        self.start_dt_frame.pack(side="right", padx=10)

        row2 = ctk.CTkFrame(filter_frame, fg_color="transparent")
        row2.pack(fill="x", pady=5, padx=5)
        ctk.CTkLabel(row2, text="نهاية الجرد (إلى):", width=100, anchor="e").pack(side="right", padx=5)
        self.end_dt_frame, self.get_end_dt = self.build_datetime_picker(row2, "23", "59")
        self.end_dt_frame.pack(side="right", padx=10)

        summary_frame = ctk.CTkFrame(tab, fg_color="transparent")
        summary_frame.pack(fill="x", pady=5, padx=20)
        
        self.rep_sum_1 = ctk.CTkLabel(summary_frame, text="---", font=ctk.CTkFont(size=16, weight="bold"), fg_color="#34495e", corner_radius=8, width=200, height=60)
        self.rep_sum_1.pack(side="right", padx=10)
        
        self.rep_sum_2 = ctk.CTkLabel(summary_frame, text="---", font=ctk.CTkFont(size=16, weight="bold"), fg_color="#34495e", corner_radius=8, width=200, height=60)
        self.rep_sum_2.pack(side="right", padx=10)

        self.rep_sum_3 = ctk.CTkLabel(summary_frame, text="---", font=ctk.CTkFont(size=16, weight="bold"), fg_color="#34495e", corner_radius=8, width=200, height=60)
        self.rep_sum_3.pack(side="right", padx=10)

        tree_frame = ctk.CTkFrame(tab)
        tree_frame.pack(expand=True, fill="both", padx=20, pady=5)
        
        tree_scroll = ttk.Scrollbar(tree_frame)
        tree_scroll.pack(side="right", fill="y")
        
        self.report_tree = ttk.Treeview(tree_frame, show='headings', yscrollcommand=tree_scroll.set)
        tree_scroll.config(command=self.report_tree.yview)
        self.report_tree.pack(expand=True, fill="both", padx=5, pady=5)

        ctk.CTkButton(tab, text="📊 تصدير الجرد لشيت إكسل (Excel)", font=ctk.CTkFont(weight="bold"), fg_color="#27ae60", hover_color="#1e8449", height=40, command=self.export_excel_report).pack(pady=10)

    def load_suppliers_for_reports(self):
        self.cursor.execute("SELECT id, name FROM suppliers")
        sups = self.cursor.fetchall()
        self.report_suppliers_list = [f"{s[0]} - {s[1]}" for s in sups]
        self.report_sup_combo.configure(values=self.report_suppliers_list)

    def toggle_supplier_combo(self, choice):
        if choice == "حساب مورد محدد":
            self.report_sup_combo.configure(state="normal")
            self.rep_sup_label.configure(text_color="white")
            if self.report_suppliers_list: self.report_sup_combo.set(self.report_suppliers_list[0])
        else:
            self.report_sup_combo.configure(state="disabled")
            self.rep_sup_label.configure(text_color="gray")
            self.report_sup_combo.set("")

    def generate_advanced_report(self):
        rep_type = self.report_type_combo.get()
        start_d = self.get_start_dt() 
        end_d = self.get_end_dt()

        self.report_tree.delete(*self.report_tree.get_children())
        self.report_tree["columns"] = ()
        
        try:
            if rep_type == "المبيعات والأرباح":
                self.report_tree["columns"] = ("id", "date", "customer", "total", "cost", "profit")
                self.report_tree.heading("id", text="رقم الفاتورة")
                self.report_tree.heading("date", text="التاريخ والوقت")
                self.report_tree.heading("customer", text="العميل/الدليفري")
                self.report_tree.heading("total", text="الإجمالي (مبيعات)")
                self.report_tree.heading("cost", text="التكلفة (رأس المال)")
                self.report_tree.heading("profit", text="صافي الربح")

                query = """
                    SELECT s.id, s.date, COALESCE(s.customer, '') || ' ' || COALESCE(s.delivery_person, ''), 
                           s.total, COALESCE(SUM(si.qty * p.cost), 0) as total_cost
                    FROM sales s
                    LEFT JOIN sale_items si ON s.id = si.sale_id
                    LEFT JOIN products p ON si.product_id = p.id
                    WHERE s.status = 'مكتملة' AND s.date >= ? AND s.date <= ?
                    GROUP BY s.id ORDER BY s.id DESC
                """
                self.cursor.execute(query, (start_d, end_d))
                rows = self.cursor.fetchall()
                
                sum_sales, sum_cost, sum_profit = 0, 0, 0
                for row in rows:
                    profit = row[3] - row[4]
                    sum_sales += row[3]
                    sum_cost += row[4]
                    sum_profit += profit
                    self.report_tree.insert("", "end", values=(row[0], row[1], row[2], f"{row[3]:g}", f"{row[4]:g}", f"{profit:g}"))
                
                self.rep_sum_1.configure(text=f"إجمالي المبيعات\n{sum_sales:g} ج.م", fg_color="#3498db")
                self.rep_sum_2.configure(text=f"إجمالي التكلفة\n{sum_cost:g} ج.م", fg_color="#e67e22")
                self.rep_sum_3.configure(text=f"صافي الربح\n{sum_profit:g} ج.م", fg_color="#2ecc71")

            elif rep_type == "المصروفات":
                self.report_tree["columns"] = ("id", "date", "cat", "amount", "note")
                self.report_tree.heading("id", text="رقم")
                self.report_tree.heading("date", text="التاريخ والوقت")
                self.report_tree.heading("cat", text="بند المصروف")
                self.report_tree.heading("amount", text="المبلغ")
                self.report_tree.heading("note", text="الملاحظة")

                query = "SELECT id, date, category, amount, note FROM expenses WHERE date >= ? AND date <= ? ORDER BY id DESC"
                self.cursor.execute(query, (start_d, end_d))
                rows = self.cursor.fetchall()
                
                sum_exp = 0
                for row in rows:
                    sum_exp += row[3]
                    self.report_tree.insert("", "end", values=(row[0], row[1], row[2], f"{row[3]:g}", row[4]))
                
                self.rep_sum_1.configure(text=f"إجمالي المصروفات\n{sum_exp:g} ج.م", fg_color="#e74c3c")
                self.rep_sum_2.configure(text="---", fg_color="#34495e")
                self.rep_sum_3.configure(text="---", fg_color="#34495e")

            elif rep_type in ["المشتريات العامة", "حساب مورد محدد"]:
                self.report_tree["columns"] = ("id", "date", "sup", "total", "discount", "paid", "rem")
                self.report_tree.heading("id", text="رقم الفاتورة")
                self.report_tree.heading("date", text="التاريخ والوقت")
                self.report_tree.heading("sup", text="المورد")
                self.report_tree.heading("total", text="الإجمالي")
                self.report_tree.heading("discount", text="الخصم")
                self.report_tree.heading("paid", text="المدفوع")
                self.report_tree.heading("rem", text="الآجل")

                if rep_type == "المشتريات العامة":
                    query = """
                        SELECT p.id, p.date, s.name, p.total, p.discount, p.paid 
                        FROM purchases p JOIN suppliers s ON p.supplier_id = s.id 
                        WHERE p.status = 'مكتملة' AND p.date >= ? AND p.date <= ? ORDER BY p.id DESC
                    """
                    self.cursor.execute(query, (start_d, end_d))
                else:
                    sup_str = self.report_sup_combo.get()
                    if not sup_str: return messagebox.showerror("خطأ", "اختر مورداً أولاً.")
                    sup_id = int(sup_str.split(" - ")[0])
                    query = """
                        SELECT p.id, p.date, s.name, p.total, p.discount, p.paid 
                        FROM purchases p JOIN suppliers s ON p.supplier_id = s.id 
                        WHERE p.supplier_id = ? AND p.status = 'مكتملة' AND p.date >= ? AND p.date <= ? ORDER BY p.id DESC
                    """
                    self.cursor.execute(query, (sup_id, start_d, end_d))

                rows = self.cursor.fetchall()
                sum_tot, sum_paid, sum_rem = 0, 0, 0
                for row in rows:
                    disc = row[4] if row[4] else 0
                    rem = row[3] - row[5]
                    sum_tot += row[3]
                    sum_paid += row[5]
                    sum_rem += rem
                    self.report_tree.insert("", "end", values=(row[0], row[1], row[2], f"{row[3]:g}", f"{disc:g}", f"{row[5]:g}", f"{rem:g}"))
                
                self.rep_sum_1.configure(text=f"إجمالي المشتريات\n{sum_tot:g} ج.م", fg_color="#8e44ad")
                self.rep_sum_2.configure(text=f"المدفوع كاش\n{sum_paid:g} ج.م", fg_color="#2980b9")
                self.rep_sum_3.configure(text=f"الديون (الآجل)\n{sum_rem:g} ج.م", fg_color="#c0392b")

            for col in self.report_tree["columns"]:
                self.report_tree.column(col, anchor="center")

        except Exception as e:
            messagebox.showerror("خطأ", f"حدث خطأ أثناء الجرد:\n{e}")

    def export_excel_report(self):
        if not self.report_tree.get_children():
            messagebox.showwarning("تنبيه", "الجدول فارغ! قم بعمل جرد أولاً لطباعته.")
            return
            
        rep_type = self.report_type_combo.get()
        file_path = filedialog.asksaveasfilename(
            defaultextension=".csv", 
            initialfile=f"شيت_جرد_{rep_type}_{datetime.date.today().strftime('%Y-%m-%d')}.csv", 
            title="حفظ كملف إكسل", 
            filetypes=[("Excel CSV Files", "*.csv"), ("All Files", "*.*")]
        )
        if not file_path: return
        
        try:
            with open(file_path, mode="w", newline="", encoding="utf-8-sig") as file:
                writer = csv.writer(file)
                columns = [self.report_tree.heading(c)['text'] for c in self.report_tree["columns"]]
                writer.writerow(columns)
                for item in self.report_tree.get_children():
                    v = self.report_tree.item(item)['values']
                    writer.writerow(v)
            messagebox.showinfo("تم بنجاح", f"تم حفظ شيت الإكسل بنجاح!\nيمكنك فتحه الآن من:\n{file_path}")
        except Exception as e:
            messagebox.showerror("خطأ", f"فشل حفظ الإكسل:\n{e}")

    # ==========================================
    # 3. سجلات الخزينة والموظفين 
    # ==========================================
    def setup_logs_and_employees_tab(self, tab):
        log_frame = ctk.CTkFrame(tab)
        log_frame.pack(fill="both", expand=True, padx=20, pady=5)
        
        header_log = ctk.CTkFrame(log_frame, fg_color="transparent")
        header_log.pack(fill="x", padx=10, pady=5)
        ctk.CTkLabel(header_log, text="سجل حركات الدرج والمصروفات", font=ctk.CTkFont(size=18, weight="bold"), text_color="#f39c12").pack(side="left")
        
        self.exp_filter_combo = ctk.CTkComboBox(header_log, values=["الكل"], width=150, command=self.filter_expenses)
        self.exp_filter_combo.pack(side="right")
        ctk.CTkLabel(header_log, text="تصفية السجل:").pack(side="right", padx=5)
        
        tree_scroll = ttk.Scrollbar(log_frame)
        tree_scroll.pack(side="right", fill="y")
        self.exp_tree = ttk.Treeview(log_frame, columns=('id', 'cat', 'amount', 'note', 'date'), show='headings', height=8, yscrollcommand=tree_scroll.set)
        tree_scroll.config(command=self.exp_tree.yview)
        self.exp_tree.heading('id', text='رقم')
        self.exp_tree.heading('cat', text='التصنيف')
        self.exp_tree.heading('amount', text='المبلغ (ج.م)')
        self.exp_tree.heading('note', text='البيان/الملاحظة')
        self.exp_tree.heading('date', text='التاريخ')
        self.exp_tree.column('id', width=50, anchor='center')
        self.exp_tree.column('cat', width=120, anchor='center')
        self.exp_tree.column('amount', width=100, anchor='center')
        self.exp_tree.column('note', width=250, anchor='center')
        self.exp_tree.column('date', width=120, anchor='center')
        self.exp_tree.pack(fill="both", expand=True, padx=10, pady=5)
        
        add_exp_frame = ctk.CTkFrame(tab)
        add_exp_frame.pack(fill="x", padx=20, pady=5)
        ctk.CTkLabel(add_exp_frame, text="إضافة مصروف سريع:").grid(row=0, column=0, padx=10, pady=10)
        self.quick_exp_cat = ctk.CTkComboBox(add_exp_frame, values=[], width=150)
        self.quick_exp_cat.grid(row=0, column=1, padx=5)
        self.quick_exp_amount = ctk.CTkEntry(add_exp_frame, placeholder_text="المبلغ", width=100)
        self.quick_exp_amount.grid(row=0, column=2, padx=5)
        self.quick_exp_note = ctk.CTkEntry(add_exp_frame, placeholder_text="البيان (اختياري)", width=250)
        self.quick_exp_note.grid(row=0, column=3, padx=5)
        ctk.CTkButton(add_exp_frame, text="تسجيل المصروف", fg_color="#27ae60", hover_color="#1e8449", command=self.add_quick_expense).grid(row=0, column=4, padx=10)
        
        emp_frame = ctk.CTkFrame(tab)
        emp_frame.pack(fill="x", padx=20, pady=5)
        ctk.CTkLabel(emp_frame, text="تعديل راتب موظف:").grid(row=0, column=0, padx=10, pady=10)
        self.admin_emp_combo = ctk.CTkComboBox(emp_frame, values=[], width=200, command=self.on_admin_emp_select)
        self.admin_emp_combo.grid(row=0, column=1, padx=5)
        self.admin_emp_salary = ctk.CTkEntry(emp_frame, placeholder_text="الراتب الجديد", width=120)
        self.admin_emp_salary.grid(row=0, column=2, padx=5)
        ctk.CTkButton(emp_frame, text="تحديث الراتب", fg_color="#2980b9", hover_color="#1f618d", command=self.update_emp_salary).grid(row=0, column=3, padx=10)

    # ==========================================
    # 4. تبويب التحليلات
    # ==========================================
    def setup_analytics_tab(self, tab):
        top_frame = ctk.CTkFrame(tab, fg_color="transparent")
        top_frame.pack(fill="x", pady=10)
        ctk.CTkButton(top_frame, text="🧠 تحديث التحليلات الذكية", fg_color="#f39c12", hover_color="#d68910", command=self.refresh_analytics).pack(pady=10)

        content_frame = ctk.CTkFrame(tab, fg_color="transparent")
        content_frame.pack(expand=True, fill="both", padx=10)
        
        best_frame = ctk.CTkFrame(content_frame)
        best_frame.pack(side="right", expand=True, fill="both", padx=10)
        ctk.CTkLabel(best_frame, text="🌟 المنتجات الأبطال (الأعلى ربحاً ومبيعاً)", font=ctk.CTkFont(size=18, weight="bold"), text_color="#f1c40f").pack(pady=10)
        self.tree_best = ttk.Treeview(best_frame, columns=('name', 'qty', 'profit'), show='headings', height=8)
        self.tree_best.heading('name', text='المنتج')
        self.tree_best.heading('qty', text='الكمية المباعة')
        self.tree_best.heading('profit', text='إجمالي الربح منه')
        for col in self.tree_best['columns']: self.tree_best.column(col, anchor='center', width=120)
        self.tree_best.pack(expand=True, fill="both", padx=10, pady=10)

        worst_frame = ctk.CTkFrame(content_frame)
        worst_frame.pack(side="left", expand=True, fill="both", padx=10)
        ctk.CTkLabel(worst_frame, text="📉 المنتجات الراكدة (تحتاج تسويق أو إيقاف)", font=ctk.CTkFont(size=18, weight="bold"), text_color="#e74c3c").pack(pady=10)
        self.tree_worst = ttk.Treeview(worst_frame, columns=('name', 'qty', 'stock'), show='headings', height=8)
        self.tree_worst.heading('name', text='المنتج')
        self.tree_worst.heading('qty', text='الكمية المباعة')
        self.tree_worst.heading('stock', text='المتبقي بالمخزن')
        for col in self.tree_worst['columns']: self.tree_worst.column(col, anchor='center', width=120)
        self.tree_worst.pack(expand=True, fill="both", padx=10, pady=10)

    # ==========================================
    # 5. الإعدادات المالية (دفع ومصروفات)
    # ==========================================
    def setup_financial_settings_tab(self, tab):
        tab.grid_columnconfigure(0, weight=1)
        tab.grid_columnconfigure(1, weight=1)
        
        pm_frame = ctk.CTkFrame(tab)
        pm_frame.grid(row=0, column=0, sticky="nsew", padx=10, pady=10)
        ctk.CTkLabel(pm_frame, text="إدارة طرق الدفع بالمبيعات", font=ctk.CTkFont(size=18, weight="bold")).pack(pady=10)
        input_pm = ctk.CTkFrame(pm_frame, fg_color="transparent")
        input_pm.pack(fill="x", pady=5)
        self.pm_name = ctk.CTkEntry(input_pm, placeholder_text="الاسم (مثال: فيزا)", width=150)
        self.pm_name.pack(side="right", padx=5)
        self.pm_fee = ctk.CTkEntry(input_pm, placeholder_text="الرسوم %", width=80)
        self.pm_fee.pack(side="right", padx=5)
        btn_pm = ctk.CTkFrame(pm_frame, fg_color="transparent")
        btn_pm.pack(fill="x", pady=5)
        self.btn_add_pm = ctk.CTkButton(btn_pm, text="حفظ كجديد", fg_color="#27ae60", width=100, command=self.add_payment_method)
        self.btn_add_pm.pack(side="right", padx=5)
        self.btn_update_pm = ctk.CTkButton(btn_pm, text="تحديث المحدد", fg_color="#f39c12", state="disabled", width=100, command=self.update_payment_method)
        self.btn_update_pm.pack(side="right", padx=5)
        self.pm_tree = ttk.Treeview(pm_frame, columns=('id', 'name', 'fee'), show='headings', height=5)
        self.pm_tree.heading('id', text='ID')
        self.pm_tree.heading('name', text='طريقة الدفع')
        self.pm_tree.heading('fee', text='الرسوم %')
        self.pm_tree.column('id', width=30, anchor='center')
        self.pm_tree.column('name', anchor='center')
        self.pm_tree.column('fee', width=80, anchor='center')
        self.pm_tree.pack(expand=True, fill="both", padx=10, pady=5)
        self.pm_tree.bind("<Double-1>", self.on_pm_double_click)
        bot_pm = ctk.CTkFrame(pm_frame, fg_color="transparent")
        bot_pm.pack(fill="x", pady=5)
        ctk.CTkButton(bot_pm, text="حذف المحدد", fg_color="#c0392b", width=100, command=self.delete_payment_method).pack(side="right", padx=10)
        
        ctk.CTkButton(bot_pm, text="📊 تقرير المدفوعات (إكسل)", fg_color="#8e44ad", width=120, command=self.export_payment_report_excel).pack(side="left", padx=10)

        cat_frame = ctk.CTkFrame(tab)
        cat_frame.grid(row=0, column=1, sticky="nsew", padx=10, pady=10)
        ctk.CTkLabel(cat_frame, text="إدارة بنود المصروفات (التصنيفات)", font=ctk.CTkFont(size=18, weight="bold")).pack(pady=10)
        input_cat = ctk.CTkFrame(cat_frame, fg_color="transparent")
        input_cat.pack(fill="x", pady=5)
        self.cat_name = ctk.CTkEntry(input_cat, placeholder_text="اسم البند (مثال: بنزين)", width=200)
        self.cat_name.pack(side="right", padx=5)
        self.cat_name.bind("<Return>", lambda e: self.add_expense_category())
        ctk.CTkButton(input_cat, text="حفظ البند", fg_color="#27ae60", width=100, command=self.add_expense_category).pack(side="right", padx=5)
        self.cat_tree = ttk.Treeview(cat_frame, columns=('id', 'name'), show='headings', height=5)
        self.cat_tree.heading('id', text='ID')
        self.cat_tree.heading('name', text='بند المصروفات')
        self.cat_tree.column('id', width=40, anchor='center')
        self.cat_tree.column('name', anchor='center')
        self.cat_tree.pack(expand=True, fill="both", padx=10, pady=5)
        ctk.CTkButton(cat_frame, text="حذف البند المحدد", fg_color="#c0392b", command=self.delete_expense_category).pack(pady=10)

    # ==========================================
    # 5. تبويب المتجر الإلكتروني والمزامنة الهجينة
    # ==========================================
    def setup_cloud_sync_tab(self, tab):
        header_card = ctk.CTkFrame(tab, corner_radius=12, fg_color="#1e272e")
        header_card.pack(fill="x", padx=10, pady=(10, 5))
        ctk.CTkLabel(header_card, text="🌐 ربط المتجر الإلكتروني على Hostinger والمزامنة الهجينة (Offline-First Sync)", font=ctk.CTkFont(size=18, weight="bold"), text_color="#2ecc71").pack(pady=10)

        # بطاقة إعدادات السيرفر
        config_card = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        config_card.pack(fill="x", padx=10, pady=5)

        row1 = ctk.CTkFrame(config_card, fg_color="transparent")
        row1.pack(fill="x", padx=15, pady=10)

        ctk.CTkLabel(row1, text="رابط المتجر / API:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=5)
        self.ent_cloud_url = ctk.CTkEntry(row1, placeholder_text="https://your-store.com/api", width=250, font=("Arial", 13))
        self.ent_cloud_url.pack(side="right", padx=5)

        ctk.CTkLabel(row1, text="مفتاح الأمان (API Key):", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(15, 5))
        self.ent_cloud_key = ctk.CTkEntry(row1, placeholder_text="Hostinger Secret Token...", show="*", width=200, font=("Arial", 13))
        self.ent_cloud_key.pack(side="right", padx=5)

        row2 = ctk.CTkFrame(config_card, fg_color="transparent")
        row2.pack(fill="x", padx=15, pady=(0, 10))

        self.sw_auto_sync = ctk.CTkSwitch(row2, text="تفعيل المزامنة التلقائية بالخلفية", font=ctk.CTkFont(weight="bold"))
        self.sw_auto_sync.pack(side="right", padx=10)

        ctk.CTkLabel(row2, text="فترة التحديث:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(15, 5))
        self.combo_sync_interval = ctk.CTkComboBox(row2, values=["15 ثانية", "30 ثانية", "60 ثانية", "300 ثانية"], width=110)
        self.combo_sync_interval.set("30 ثانية")
        self.combo_sync_interval.pack(side="right", padx=5)

        ctk.CTkButton(row2, text="⚡ اختبار الاتصال بالسيرفر", font=ctk.CTkFont(weight="bold"), fg_color="#8e44ad", hover_color="#732d91", command=self.test_cloud_connection_ui).pack(side="left", padx=5)
        ctk.CTkButton(row2, text="💾 حفظ إعدادات السحابة", font=ctk.CTkFont(weight="bold"), fg_color="#27ae60", hover_color="#1e8449", command=self.save_cloud_settings_ui).pack(side="left", padx=5)

        # كروت الحالة والمزامنة الفورية
        status_card = ctk.CTkFrame(tab, corner_radius=12, fg_color="#1e272e")
        status_card.pack(fill="x", padx=10, pady=5)

        self.lbl_cloud_status = ctk.CTkLabel(status_card, text="🟠 نمط الأوفلاين السريع (مزامنة هجينة جاهزة بالخلفية)", font=ctk.CTkFont(size=15, weight="bold"), text_color="#f1c40f")
        self.lbl_cloud_status.pack(pady=8)

        btns_row = ctk.CTkFrame(status_card, fg_color="transparent")
        btns_row.pack(pady=(0, 10))

        ctk.CTkButton(btns_row, text="🔄 مزامنة فورية كاملة", font=ctk.CTkFont(size=13, weight="bold"), fg_color="#2980b9", hover_color="#1f618d", height=38, command=self.trigger_manual_sync_ui).pack(side="right", padx=6)
        ctk.CTkButton(btns_row, text="📦 سحب وتحديث المنتجات من السحابة", font=ctk.CTkFont(size=13, weight="bold"), fg_color="#8e44ad", hover_color="#732d91", height=38, command=self.pull_cloud_products_ui).pack(side="right", padx=6)
        ctk.CTkButton(btns_row, text="📥 سحب طلبات الويب الجديدة", font=ctk.CTkFont(size=13, weight="bold"), fg_color="#16a085", hover_color="#117864", height=38, command=self.pull_web_orders_ui).pack(side="right", padx=6)

        # جدول طابور المزامنة
        queue_card = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        queue_card.pack(expand=True, fill="both", padx=10, pady=(5, 10))

        ctk.CTkLabel(queue_card, text="📋 سجل وطابور المعاملات بانتظار المزامنة (Sync Queue)", font=ctk.CTkFont(size=14, weight="bold"), text_color="white").pack(pady=6)

        self.sync_queue_tree = ttk.Treeview(queue_card, columns=('id', 'date', 'action', 'entity', 'status'), show='headings', height=6)
        self.sync_queue_tree.heading('id', text='رقم العملية')
        self.sync_queue_tree.heading('date', text='تاريخ الإضافة')
        self.sync_queue_tree.heading('action', text='نوع الإجراء')
        self.sync_queue_tree.heading('entity', text='الكيان (فاتورة/منتج)')
        self.sync_queue_tree.heading('status', text='حالة المزامنة')

        for c in self.sync_queue_tree['columns']: self.sync_queue_tree.column(c, anchor='center')
        self.sync_queue_tree.pack(expand=True, fill="both", padx=10, pady=10)

    def load_cloud_settings_ui(self):
        if not hasattr(self, 'sync_mgr'):
            from sync_manager import HybridSyncManager
            self.sync_mgr = HybridSyncManager(self.db, self.app)

        st = self.sync_mgr.get_cloud_settings()
        if hasattr(self, 'ent_cloud_url'):
            self.ent_cloud_url.delete(0, 'end')
            self.ent_cloud_url.insert(0, st.get('cloud_api_url', ''))

            self.ent_cloud_key.delete(0, 'end')
            self.ent_cloud_key.insert(0, st.get('cloud_api_key', ''))

            if st.get('cloud_auto_sync') == "1":
                self.sw_auto_sync.select()
            else:
                self.sw_auto_sync.deselect()

            interval = st.get('cloud_sync_interval', '30')
            self.combo_sync_interval.set(f"{interval} ثانية")

        self.load_sync_queue_tree()

    def save_cloud_settings_ui(self):
        url = self.ent_cloud_url.get().strip()
        key = self.ent_cloud_key.get().strip()
        auto_sync = "1" if self.sw_auto_sync.get() == 1 else "0"
        interval = self.combo_sync_interval.get().replace(" ثانية", "").strip()

        self.sync_mgr.save_cloud_settings(url, key, auto_sync, interval)
        messagebox.showinfo("نجاح", "تم حفظ إعدادات الربط السحابي والمتجر الإلكتروني بنجاح!")
        self.load_cloud_settings_ui()

    def test_cloud_connection_ui(self):
        url = self.ent_cloud_url.get().strip()
        key = self.ent_cloud_key.get().strip()
        ok, msg = self.sync_mgr.test_cloud_connection(url, key)
        if ok:
            messagebox.showinfo("نجاح الاتصال", msg)
            self.lbl_cloud_status.configure(text="🟢 متصل بالمتجر السحابي على Hostinger بنجاح ⚡", text_color="#2ecc71")
        else:
            messagebox.showwarning("تنبيه الاتصال", msg)
            self.lbl_cloud_status.configure(text="🟠 نمط الأوفلاين السريع (مزامنة هجينة جاهزة بالخلفية)", text_color="#f1c40f")

    def trigger_manual_sync_ui(self):
        self.sync_mgr.trigger_instant_sync()
        messagebox.showinfo("مزامنة", "تم بدء المزامنة الفورية مع المتجر الإلكتروني بالخلفية بنجاح!")
        self.after(1500, self.load_sync_queue_tree)

    def pull_cloud_products_ui(self):
        ok, msg = self.sync_mgr.pull_products_from_cloud()
        if ok:
            messagebox.showinfo("نجاح سحب المنتجات", msg)
            if hasattr(self.app, 'frames') and 'products' in self.app.frames:
                self.app.frames['products'].load_products_tree()
            if hasattr(self.app, 'frames') and 'pos' in self.app.frames:
                self.app.frames['pos'].pos_load_products_tree()
        else:
            messagebox.showwarning("تنبيه السحب", msg)
        self.load_sync_queue_tree()

    def pull_web_orders_ui(self):
        messagebox.showinfo("سحب الطلبات", "جاري التنسيق مع المتجر السحابي وسحب الأوردرات الجديدة...")
        self.load_sync_queue_tree()

    def load_sync_queue_tree(self):
        if not hasattr(self, 'sync_queue_tree'): return
        for item in self.sync_queue_tree.get_children(): self.sync_queue_tree.delete(item)
        
        self.cursor.execute("SELECT id, created_at, action, entity_type, status FROM sync_queue ORDER BY id DESC LIMIT 50")
        rows = self.cursor.fetchall()
        for r in rows:
            self.sync_queue_tree.insert("", "end", values=(r[0], r[1], r[2], r[3], r[4]))


    # ==========================================
    # 6. الأمان وإعدادات النظام والفاتورة
    # ==========================================
    def setup_security_tab(self, tab):
        store_frame = ctk.CTkFrame(tab)
        store_frame.pack(fill="x", pady=10, padx=20)
        ctk.CTkLabel(store_frame, text="بيانات المنشأة (تُطبع على الفاتورة):", font=ctk.CTkFont(size=18, weight="bold"), text_color="#f39c12").grid(row=0, column=0, columnspan=4, pady=10)
        
        ctk.CTkLabel(store_frame, text="اسم المحل/المنشأة:").grid(row=1, column=0, padx=10, pady=10)
        self.ent_store_name = ctk.CTkEntry(store_frame, width=200)
        self.ent_store_name.grid(row=1, column=1, padx=5)
        
        ctk.CTkLabel(store_frame, text="رقم الهاتف:").grid(row=1, column=2, padx=10, pady=10)
        self.ent_store_phone = ctk.CTkEntry(store_frame, width=200)
        self.ent_store_phone.grid(row=1, column=3, padx=5)
        
        ctk.CTkLabel(store_frame, text="العنوان:").grid(row=2, column=0, padx=10, pady=10)
        self.ent_store_address = ctk.CTkEntry(store_frame, width=450)
        self.ent_store_address.grid(row=2, column=1, columnspan=3, padx=5, sticky="w")
        
        # --- التعديل هنا: إضافة حقل اسم الكاشير ---
        ctk.CTkLabel(store_frame, text="اسم الكاشير:").grid(row=3, column=0, padx=10, pady=10)
        self.ent_cashier_name = ctk.CTkEntry(store_frame, width=200, placeholder_text="مثال: شيفت صباحي")
        self.ent_cashier_name.grid(row=3, column=1, padx=5, sticky="w")

        ctk.CTkButton(store_frame, text="💾 حفظ بيانات الفاتورة", fg_color="#27ae60", hover_color="#1e8449", command=self.save_store_settings).grid(row=4, column=0, columnspan=4, pady=15)

        backup_frame = ctk.CTkFrame(tab)
        backup_frame.pack(fill="x", pady=10, padx=20)
        ctk.CTkLabel(backup_frame, text="النسخ الاحتياطي للبيانات:", font=ctk.CTkFont(size=16, weight="bold"), text_color="#2980b9").pack(pady=5)
        
        btn_frame = ctk.CTkFrame(backup_frame, fg_color="transparent")
        btn_frame.pack(pady=10)
        ctk.CTkButton(btn_frame, text="💾 أخذ نسخة احتياطية", font=ctk.CTkFont(weight="bold"), fg_color="#2980b9", hover_color="#1f618d", command=self.backup_database).pack(side="right", padx=10)
        ctk.CTkButton(btn_frame, text="🔄 استعادة نسخة سابقة", font=ctk.CTkFont(weight="bold"), fg_color="#e67e22", hover_color="#d35400", command=self.restore_database).pack(side="right", padx=10)
        ctk.CTkButton(btn_frame, text="🌱 تحميل بيانات تجريبية", font=ctk.CTkFont(weight="bold"), fg_color="#27ae60", hover_color="#1e8449", command=self.load_demo_data).pack(side="right", padx=10)


        pwd_frame = ctk.CTkFrame(tab)
        pwd_frame.pack(fill="x", pady=10, padx=20)
        ctk.CTkLabel(pwd_frame, text="تغيير كلمة مرور المدير:").grid(row=0, column=0, padx=10, pady=20)
        self.old_pwd = ctk.CTkEntry(pwd_frame, placeholder_text="المرور الحالية", show="*")
        self.old_pwd.grid(row=0, column=1, padx=5)
        self.new_pwd = ctk.CTkEntry(pwd_frame, placeholder_text="المرور الجديدة", show="*")
        self.new_pwd.grid(row=0, column=2, padx=5)
        ctk.CTkButton(pwd_frame, text="تأكيد التغيير", command=self.change_password).grid(row=0, column=3, padx=10)

        danger_frame = ctk.CTkFrame(tab, fg_color="transparent")
        danger_frame.pack(fill="x", pady=10, padx=20)
        ctk.CTkButton(danger_frame, text="⚠️ حذف جميع البيانات (فورمات)", font=ctk.CTkFont(weight="bold"), fg_color="#c0392b", hover_color="#922b21", command=self.delete_all_data).pack()

    # ==========================================
    # دوال الإعدادات والعمليات الأساسية
    # ==========================================
    def load_store_settings(self):
        self.cursor.execute("SELECT key, value FROM settings WHERE key IN ('store_name', 'store_phone', 'store_address', 'default_cashier')")
        settings_dict = {row[0]: row[1] for row in self.cursor.fetchall()}
        
        self.ent_store_name.delete(0, 'end')
        self.ent_store_name.insert(0, settings_dict.get('store_name', 'سوبر ماركت'))
        
        self.ent_store_phone.delete(0, 'end')
        self.ent_store_phone.insert(0, settings_dict.get('store_phone', ''))
        
        self.ent_store_address.delete(0, 'end')
        self.ent_store_address.insert(0, settings_dict.get('store_address', ''))
        
        self.ent_cashier_name.delete(0, 'end')
        self.ent_cashier_name.insert(0, settings_dict.get('default_cashier', 'أحمد عبد الوهاب'))

    def save_store_settings(self):
        s_name = self.ent_store_name.get()
        s_phone = self.ent_store_phone.get()
        s_addr = self.ent_store_address.get()
        s_cashier = self.ent_cashier_name.get()
        
        for k, v in [('store_name', s_name), ('store_phone', s_phone), ('store_address', s_addr), ('default_cashier', s_cashier)]:
            self.cursor.execute("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)", (k, v))
        self.db.commit()
        messagebox.showinfo("نجاح", "تم حفظ بيانات المنشأة واسم الكاشير بنجاح!")

    def load_expense_categories(self):
        for item in self.cat_tree.get_children(): self.cat_tree.delete(item)
        self.cursor.execute("SELECT id, name FROM expense_categories ORDER BY name")
        cats = self.cursor.fetchall()
        cat_names = [c[1] for c in cats]
        for c in cats: self.cat_tree.insert("", "end", values=(c[0], c[1]))
        if hasattr(self, 'exp_filter_combo'):
            self.exp_filter_combo.configure(values=["الكل"] + cat_names)
            self.quick_exp_cat.configure(values=cat_names)
            if cat_names: self.quick_exp_cat.set(cat_names[0])

    def add_expense_category(self):
        name = self.cat_name.get().strip()
        if not name: return
        try:
            self.cursor.execute("INSERT INTO expense_categories (name) VALUES (?)", (name,))
            self.db.commit()
            self.cat_name.delete(0, 'end')
            self.load_expense_categories()
        except sqlite3.IntegrityError: messagebox.showerror("خطأ", "هذا البند موجود بالفعل!")

    def delete_expense_category(self):
        selected = self.cat_tree.selection()
        if not selected: return
        item = self.cat_tree.item(selected[0])['values']
        if messagebox.askyesno("تأكيد", f"مسح بند '{item[1]}'؟"):
            self.cursor.execute("DELETE FROM expense_categories WHERE id=?", (item[0],))
            self.db.commit()
            self.load_expense_categories()

    def load_payment_methods(self):
        for item in self.pm_tree.get_children(): self.pm_tree.delete(item)
        self.cursor.execute("SELECT id, name, fee_percent FROM payment_methods")
        for row in self.cursor.fetchall(): self.pm_tree.insert("", "end", values=(row[0], row[1], f"{row[2]:g}%"))

    def add_payment_method(self):
        name = self.pm_name.get().strip()
        if not name: return
        try:
            fee = float(self.pm_fee.get().strip() or 0)
            self.cursor.execute("INSERT INTO payment_methods (name, fee_percent) VALUES (?, ?)", (name, fee))
            self.db.commit()
            self.pm_name.delete(0, 'end')
            self.pm_fee.delete(0, 'end')
            self.load_payment_methods()
        except Exception: pass

    def on_pm_double_click(self, event):
        selected = self.pm_tree.selection()
        if not selected: return
        item = self.pm_tree.item(selected[0])['values']
        self.current_edit_pm_id = item[0]
        self.pm_name.delete(0, 'end'); self.pm_name.insert(0, str(item[1]))
        self.pm_fee.delete(0, 'end'); self.pm_fee.insert(0, str(item[2]).replace('%', ''))
        self.btn_add_pm.configure(state="disabled"); self.btn_update_pm.configure(state="normal")

    def update_payment_method(self):
        if not self.current_edit_pm_id: return
        name = self.pm_name.get().strip()
        try:
            fee = float(self.pm_fee.get().strip() or 0)
            self.cursor.execute("UPDATE payment_methods SET name=?, fee_percent=? WHERE id=?", (name, fee, self.current_edit_pm_id))
            self.db.commit()
            self.pm_name.delete(0, 'end'); self.pm_fee.delete(0, 'end')
            self.current_edit_pm_id = None
            self.btn_add_pm.configure(state="normal"); self.btn_update_pm.configure(state="disabled")
            self.load_payment_methods()
        except Exception: pass

    def delete_payment_method(self):
        selected = self.pm_tree.selection()
        if not selected: return
        item = self.pm_tree.item(selected[0])['values']
        if item[1] == "كاش": return
        if messagebox.askyesno("تأكيد", f"حذف ({item[1]}) نهائياً؟"):
            self.cursor.execute("DELETE FROM payment_methods WHERE id=?", (item[0],))
            self.db.commit()
            self.load_payment_methods()

    def export_payment_report_excel(self):
        self.cursor.execute("""
            SELECT payment_method, COUNT(id), SUM(total - payment_fee), SUM(payment_fee), SUM(total)
            FROM sales WHERE status='مكتملة' GROUP BY payment_method
        """)
        report_data = self.cursor.fetchall()
        if not report_data: return messagebox.showwarning("تنبيه", "لا توجد مبيعات مكتملة.")
        
        file_path = filedialog.asksaveasfilename(
            defaultextension=".csv", 
            initialfile=f"تقرير_المدفوعات_{datetime.date.today().strftime('%Y-%m-%d')}.csv", 
            title="حفظ تقرير كملف إكسل", 
            filetypes=[("Excel CSV Files", "*.csv")]
        )
        if not file_path: return
        
        try:
            with open(file_path, "w", newline="", encoding="utf-8-sig") as file:
                writer = csv.writer(file)
                writer.writerow(["طريقة الدفع", "عدد الفواتير", "صافي المبيعات", "الرسوم المخصومة", "الإجمالي المحصل كلياً"])
                
                gt = 0
                for r in report_data:
                    pm, cnt, net, fee, tot = str(r[0] or "كاش"), r[1], r[2] or 0, r[3] or 0, r[4] or 0
                    gt += tot
                    writer.writerow([pm, cnt, net, fee, tot])
                    
                writer.writerow(["", "", "", "الإجمالي النهائي:", gt])
                
            messagebox.showinfo("نجاح", "تم حفظ التقرير بصيغة إكسل بنجاح.")
        except Exception as e: messagebox.showerror("خطأ", str(e))

    def load_logs_and_employees(self):
        if hasattr(self, 'exp_filter_combo'): self.filter_expenses(self.exp_filter_combo.get())
        self.cursor.execute("SELECT id, name, salary FROM employees")
        emps = self.cursor.fetchall()
        self.admin_emps_data = {f"{e[0]} - {e[1]}": e[2] for e in emps}
        if hasattr(self, 'admin_emp_combo'):
            vals = list(self.admin_emps_data.keys())
            self.admin_emp_combo.configure(values=vals)
            if vals: 
                self.admin_emp_combo.set(vals[0])
                self.on_admin_emp_select(vals[0])

    def filter_expenses(self, choice):
        for item in self.exp_tree.get_children(): self.exp_tree.delete(item)
        if choice == "الكل": self.cursor.execute("SELECT id, category, amount, note, date FROM expenses ORDER BY id DESC")
        else: self.cursor.execute("SELECT id, category, amount, note, date FROM expenses WHERE category=? ORDER BY id DESC", (choice,))
        for row in self.cursor.fetchall(): self.exp_tree.insert("", "end", values=(row[0], row[1], f"{row[2]:g}", row[3], row[4]))

    def add_quick_expense(self):
        try:
            cat = self.quick_exp_cat.get()
            amount = float(self.quick_exp_amount.get())
            note = self.quick_exp_note.get().strip()
            if amount <= 0: raise ValueError
            date_now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M")
            self.cursor.execute("INSERT INTO expenses (category, amount, note, date) VALUES (?, ?, ?, ?)", (cat, amount, note, date_now))
            self.db.commit()
            self.quick_exp_amount.delete(0, 'end'); self.quick_exp_note.delete(0, 'end')
            self.load_logs_and_employees(); self.refresh_admin_dashboard()
        except ValueError: pass

    def on_admin_emp_select(self, choice):
        if not choice: return
        salary = self.admin_emps_data.get(choice, "")
        self.admin_emp_salary.delete(0, 'end'); self.admin_emp_salary.insert(0, str(salary))

    def update_emp_salary(self):
        choice = self.admin_emp_combo.get()
        if not choice: return
        try:
            emp_id = int(choice.split(" - ")[0])
            new_salary = float(self.admin_emp_salary.get())
            self.cursor.execute("UPDATE employees SET salary=? WHERE id=?", (new_salary, emp_id))
            self.db.commit()
            self.load_logs_and_employees() 
        except ValueError: pass

    def refresh_admin_dashboard(self):
        self.cursor.execute("SELECT SUM(total) FROM sales WHERE status = 'مكتملة'")
        sales = self.cursor.fetchone()[0] or 0.0
        
        # تم تصحيح الخطأ في السطر التالي
        self.cursor.execute("SELECT SUM(amount) FROM expenses")
        expenses = self.cursor.fetchone()[0] or 0.0
        
        net_cash = sales - expenses
        self.cursor.execute('''SELECT SUM(si.qty * p.cost) FROM sale_items si JOIN products p ON si.product_id = p.id JOIN sales s ON si.sale_id = s.id WHERE s.status = 'مكتملة' ''')
        cost_of_goods_sold = self.cursor.fetchone()[0] or 0.0
        net_profit = sales - cost_of_goods_sold
        profit_margin = (net_profit / sales) * 100 if sales > 0 else 0.0
        
        self.lbl_total_sales.configure(text=f"إجمالي المبيعات\n{sales:g} ج.م")
        self.lbl_total_exp.configure(text=f"إجمالي المنصرفات\n{expenses:g} ج.م")
        if net_cash < 0: self.lbl_net_cash.configure(text=f"عجز بالخزينة\n{net_cash:g} ج.م", fg_color="#c0392b")
        else: self.lbl_net_cash.configure(text=f"السيولة بالخزينة\n{net_cash:g} ج.م", fg_color="#2ecc71")
        self.lbl_net_profit.configure(text=f"صافي الربح\n{net_profit:g} ج.م\n({profit_margin:.1f}%)")


    def refresh_analytics(self):
        for item in self.tree_best.get_children(): self.tree_best.delete(item)
        self.cursor.execute('''SELECT p.name, SUM(si.qty) as total_qty, SUM(si.qty * (p.price - p.cost)) as total_profit FROM sale_items si JOIN products p ON si.product_id = p.id JOIN sales s ON si.sale_id = s.id WHERE s.status = 'مكتملة' GROUP BY p.id ORDER BY total_profit DESC, total_qty DESC LIMIT 5''')
        for row in self.cursor.fetchall(): self.tree_best.insert("", "end", values=(row[0], f"{row[1]:g}", f"{row[2]:g} ج.م"))
        for item in self.tree_worst.get_children(): self.tree_worst.delete(item)
        self.cursor.execute('''SELECT p.name, COALESCE(SUM(si.qty), 0) as total_qty, p.stock FROM products p LEFT JOIN sale_items si ON p.id = si.product_id LEFT JOIN sales s ON si.sale_id = s.id AND s.status = 'مكتملة' GROUP BY p.id ORDER BY total_qty ASC LIMIT 5''')
        for row in self.cursor.fetchall(): self.tree_worst.insert("", "end", values=(row[0], f"{row[1]:g}", f"{row[2]:g}"))

    def load_demo_data(self):
        if messagebox.askyesno("تأكيد تحميل البيانات التجريبية", "هل تريد إضافة ملء البرنامج بالبيانات التجريبية للتجربة والتصفح؟"):
            try:
                from seed_demo_data import seed_database
                seed_database("my_business_v3.db")
                self.on_show()
                messagebox.showinfo("نجاح", "✅ تم تحميل كافة البيانات التجريبية بنجاح!")
            except Exception as e:
                messagebox.showerror("خطأ", f"حدث خطأ أثناء تحميل البيانات:\n{e}")

    def backup_database(self):

        db_filename = "my_business_v3.db" 
        if not os.path.exists(db_filename): return
        file_path = filedialog.asksaveasfilename(defaultextension=".db", initialfile=f"Backup_{datetime.date.today().strftime('%Y-%m-%d')}.db", title="حفظ نسخة", filetypes=[("DB", "*.db")])
        if file_path: shutil.copy2(db_filename, file_path)

    # --- دالة استعادة النسخة الاحتياطية الجديدة ---
    def restore_database(self):
        msg = "⚠️ تحذير هام ⚠️\n\nاستعادة نسخة احتياطية سيؤدي إلى مسح كل البيانات الحالية (الأصناف، الفواتير، الحسابات) واستبدالها بالبيانات الموجودة في ملف النسخة الاحتياطية.\n\nهل أنت متأكد بنسبة 100% أنك تريد المتابعة؟"
        if not messagebox.askyesno("تحذير مسح بيانات", msg, icon="warning"):
            return
            
        backup_file_path = filedialog.askopenfilename(
            title="اختر ملف النسخة الاحتياطية",
            filetypes=[("Database Files", "*.db"), ("All Files", "*.*")]
        )
        
        if not backup_file_path:
            return 
            
        try:
            self.db.close()
        except Exception:
            pass
            
        try:
            current_db_path = "my_business_v3.db" 
            shutil.copy2(backup_file_path, current_db_path)
            messagebox.showinfo("نجاح", "✅ تم استعادة النسخة الاحتياطية بنجاح!\n\nسيتم إغلاق البرنامج الآن لتطبيق التغييرات. يرجى إعادة فتحه.")
            self.winfo_toplevel().destroy()
            sys.exit(0)
        except Exception as e:
            messagebox.showerror("خطأ", f"حدث خطأ أثناء الاستعادة:\n{e}\n\nيرجى إعادة تشغيل البرنامج وحاول مرة أخرى.")

    def change_password(self):
        old_p, new_p = self.old_pwd.get(), self.new_pwd.get()
        self.cursor.execute("SELECT value FROM settings WHERE key='admin_password'")
        res = self.cursor.fetchone()
        current_p = res[0] if res else "1234"
        if old_p != current_p: return messagebox.showerror("خطأ", "كلمة المرور القديمة خطأ!")
        self.cursor.execute("UPDATE settings SET value=? WHERE key='admin_password'", (new_p,))
        self.db.commit()
        self.old_pwd.delete(0, 'end'); self.new_pwd.delete(0, 'end')
        messagebox.showinfo("نجاح", "تم التغيير بنجاح.")

    def delete_all_data(self):
        # نافذة حوارية تتيح الاختيار بين تصفير الكميات والحسابات، أو التصفير المحلي، أو التصفير الشامل
        dialog_win = ctk.CTkToplevel(self)
        dialog_win.title("⚠️ خيارات تصفير وحذف البيانات")
        dialog_win.geometry("540x440")
        dialog_win.attributes("-topmost", True)
        dialog_win.resizable(False, False)

        header = ctk.CTkFrame(dialog_win, fg_color="#c0392b", corner_radius=10)
        header.pack(fill="x", padx=15, pady=12)
        ctk.CTkLabel(header, text="⚠️ خيارات تصفير وحذف البيانات", font=ctk.CTkFont(size=17, weight="bold"), text_color="white").pack(pady=8)

        ctk.CTkLabel(dialog_win, text="يرجى اختيار نوع العملية المطلوبة بدقة:", font=ctk.CTkFont(size=14, weight="bold")).pack(pady=4)

        def do_zero_quantities_and_balances():
            msg = "هل أنت متأكد من تصفير الكميات والأرصدة؟\n\n- سيتم تصفير مخزون جميع المنتجات إلى (0).\n- سيتم تصفير أرصدة الموردين وحسابات الدليفري إلى (0).\n- سيتم الحفاظ على الأصناف والأسعار والباركود والعملاء كما هي دون مسحها.\n- سيتم تطبيق التصفير محلياً وعلى المتجر السحابي."
            if not messagebox.askyesno("تأكيد تصفير الحسابات والكميات", msg, icon="warning"):
                return
            dialog_win.destroy()
            try:
                self.cursor.execute("UPDATE products SET stock = 0")
                try: self.cursor.execute("UPDATE suppliers SET balance = 0")
                except: pass
                try: self.cursor.execute("UPDATE employees SET advances = 0, deductions = 0")
                except: pass
                try: self.cursor.execute("DELETE FROM sync_queue")
                except: pass
                self.db.commit()
            except Exception as e:
                messagebox.showerror("خطأ", f"حدث خطأ أثناء التصفير المحلي: {e}")
                return

            cloud_msg = ""
            if hasattr(self, 'sync_mgr'):
                ok, cloud_res = self.sync_mgr.reset_cloud_database(mode="zero_quantities_and_balances")
                cloud_msg = f"\nالسحابة: {cloud_res}"

            self.on_show()
            if hasattr(self.app, 'frames') and 'products' in self.app.frames:
                self.app.frames['products'].prod_clear_form()
            if hasattr(self.app, 'frames') and 'pos' in self.app.frames:
                self.app.frames['pos'].pos_load_products_tree()

            messagebox.showinfo("تم التصفير", f"✅ تم تصفير جميع الكميات (المخزون = 0) وتصفير الحسابات بنجاح محلياً وسحابياً!{cloud_msg}")

        def do_local_reset():
            if not messagebox.askyesno("تأكيد التصفير المحلي", "هل أنت متأكد من مسح كافة المنتجات والمبيعات من هذا الجهاز المحلي فقط؟\n(ستبقى بيانات السحابة محفوظة ويمكنك سحبها لاحقاً بضغطة زر)"):
                return
            dialog_win.destroy()
            for table in ['products', 'sales', 'sale_items', 'expenses', 'suppliers', 'purchases', 'purchase_items', 'employees', 'temp_invoices', 'temp_invoice_items', 'sync_queue']:
                try: self.cursor.execute(f"DELETE FROM {table}")
                except: pass
            self.db.commit()
            self.on_show()
            if hasattr(self.app, 'frames') and 'products' in self.app.frames:
                self.app.frames['products'].prod_clear_form()
            if hasattr(self.app, 'frames') and 'pos' in self.app.frames:
                self.app.frames['pos'].pos_load_products_tree()
            messagebox.showinfo("تم التصفير", "✅ تم تصفير البيانات المحلية بنجاح.\n(يمكنك في أي وقت سحب المنتجات من السحابة بضغطة زر).")

        def do_full_reset_everywhere():
            msg = "🚨 تحذير بالغ الخطورة 🚨\n\nأنت على وشك تصفير وحذف البيانات من:\n1. برنامج الكاشير المحلي على هذا الجهاز.\n2. المتجر الإلكتروني السحابي على Hostinger بالكامل.\n\nهل أنت متأكد بنسبة 100% أنك تريد الحذف من كل مكان؟"
            if not messagebox.askyesno("تأكيد الحذف الشامل", msg, icon="warning"):
                return
            dialog_win.destroy()
            
            # 1. تصفير محلي
            for table in ['products', 'sales', 'sale_items', 'expenses', 'suppliers', 'purchases', 'purchase_items', 'employees', 'temp_invoices', 'temp_invoice_items', 'sync_queue']:
                try: self.cursor.execute(f"DELETE FROM {table}")
                except: pass
            self.db.commit()

            # 2. تصفير السحابة
            cloud_msg = ""
            if hasattr(self, 'sync_mgr'):
                ok, cloud_res = self.sync_mgr.reset_cloud_database(mode="factory_reset_all")
                cloud_msg = f"\nالسحابة: {cloud_res}"

            self.on_show()
            if hasattr(self.app, 'frames') and 'products' in self.app.frames:
                self.app.frames['products'].prod_clear_form()
            if hasattr(self.app, 'frames') and 'pos' in self.app.frames:
                self.app.frames['pos'].pos_load_products_tree()

            messagebox.showinfo("تم التصفير الشامل", f"✅ تم تصفير وحذف كافة البيانات من الكاشير المحلي ومن المتجر الإلكتروني السحابي معاً بنجاح!{cloud_msg}")

        btn_f0 = ctk.CTkFrame(dialog_win, fg_color="transparent")
        btn_f0.pack(fill="x", padx=30, pady=6)
        ctk.CTkButton(btn_f0, text="1. ⚖️ تصفير الحسابات والكميات فقط (المخزون 0 والأرصدة 0 مع بقاء الأصناف)", font=ctk.CTkFont(size=13, weight="bold"), fg_color="#2980b9", hover_color="#1f618d", height=42, command=do_zero_quantities_and_balances).pack(fill="x")

        btn_f1 = ctk.CTkFrame(dialog_win, fg_color="transparent")
        btn_f1.pack(fill="x", padx=30, pady=6)
        ctk.CTkButton(btn_f1, text="2. 💻 تصفير محلي فقط (مسح البيانات محلياً مع الحفاظ على السحابة)", font=ctk.CTkFont(size=13, weight="bold"), fg_color="#d35400", hover_color="#ba4a00", height=42, command=do_local_reset).pack(fill="x")

        btn_f2 = ctk.CTkFrame(dialog_win, fg_color="transparent")
        btn_f2.pack(fill="x", padx=30, pady=6)
        ctk.CTkButton(btn_f2, text="3. 🌐 تصفير شامل من الكل (محلي + المتجر السحابي)", font=ctk.CTkFont(size=13, weight="bold"), fg_color="#c0392b", hover_color="#922b21", height=42, command=do_full_reset_everywhere).pack(fill="x")

        btn_f3 = ctk.CTkFrame(dialog_win, fg_color="transparent")
        btn_f3.pack(fill="x", padx=30, pady=10)
        ctk.CTkButton(btn_f3, text="إلغاء وتراجع", font=ctk.CTkFont(size=12), fg_color="#7f8c8d", hover_color="#626567", height=35, command=dialog_win.destroy).pack(fill="x")