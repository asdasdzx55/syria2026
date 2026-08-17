import customtkinter as ctk
import tkinter.messagebox as messagebox
from tkinter import ttk, filedialog
import datetime
import sqlite3

class HRPage(ctk.CTkFrame):
    def __init__(self, parent, db_conn, app):
        super().__init__(parent, fg_color="transparent")
        self.db = db_conn
        self.cursor = self.db.cursor()
        self.app = app
        self.current_edit_emp_id = None
        
        # تحديث الجداول تلقائياً لإضافة الوظيفة والخصومات
        try:
            self.cursor.execute("ALTER TABLE employees ADD COLUMN role TEXT DEFAULT 'عامل'")
            self.cursor.execute("ALTER TABLE employees ADD COLUMN deductions REAL DEFAULT 0")
            self.db.commit()
        except sqlite3.OperationalError:
            pass # الأعمدة موجودة بالفعل
            
        self.setup_ui()

    def setup_ui(self):
        header_card = ctk.CTkFrame(self, fg_color="#1e272e", corner_radius=12)
        header_card.pack(fill="x", padx=15, pady=(10, 5))
        ctk.CTkLabel(header_card, text="👥 شؤون العاملين وتصفية حسابات طيارين الدليفري", font=ctk.CTkFont(size=22, weight="bold"), text_color="#3498db").pack(pady=12)
        
        self.hr_tabs = ctk.CTkTabview(self, corner_radius=12)
        self.hr_tabs.pack(expand=True, fill="both", padx=15, pady=(5, 15))
        
        self.hr_tabs.add("إدارة الموظفين (تسجيل وتعديل)")
        self.hr_tabs.add("🛵 تصفية ومسحوبات طيارين الدليفري")
        self.hr_tabs.add("السلف والجزاءات")
        self.hr_tabs.add("صرف الرواتب")
        
        self.setup_manage_tab(self.hr_tabs.tab("إدارة الموظفين (تسجيل وتعديل)"))
        self.setup_delivery_settlement_tab(self.hr_tabs.tab("🛵 تصفية ومسحوبات طيارين الدليفري"))
        self.setup_advances_tab(self.hr_tabs.tab("السلف والجزاءات"))
        self.setup_payroll_tab(self.hr_tabs.tab("صرف الرواتب"))

    def on_show(self):
        self.load_employees()
        self.load_delivery_drivers_combo()
        self.load_delivery_settlement_report()

    # ==========================================
    # 1. إدارة الموظفين
    # ==========================================
    def setup_manage_tab(self, tab):
        form_frame = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        form_frame.pack(fill="x", pady=10, padx=10)
        
        grid_f = ctk.CTkFrame(form_frame, fg_color="transparent")
        grid_f.pack(fill="x", padx=10, pady=10)

        ctk.CTkLabel(grid_f, text="اسم الموظف/الطيار:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=5)
        self.hr_name = ctk.CTkEntry(grid_f, placeholder_text="اسم الموظف الكامل", width=180, font=("Arial", 14))
        self.hr_name.pack(side="right", padx=5)
        
        ctk.CTkLabel(grid_f, text="الوظيفة:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=5)
        self.hr_role = ctk.CTkComboBox(grid_f, values=["عامل", "دليفري", "كاشير", "مدير"], width=130, font=("Arial", 14))
        self.hr_role.pack(side="right", padx=5)
        
        ctk.CTkLabel(grid_f, text="الراتب الأساسي:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=5)
        self.hr_salary = ctk.CTkEntry(grid_f, placeholder_text="0.00", width=110, justify="center", font=("Arial", 14))
        self.hr_salary.pack(side="right", padx=5)
        
        ctk.CTkLabel(grid_f, text="ساعات العمل:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=5)
        self.hr_hours = ctk.CTkEntry(grid_f, placeholder_text="8", width=70, justify="center", font=("Arial", 14))
        self.hr_hours.pack(side="right", padx=5)
        
        self.btn_add_emp = ctk.CTkButton(grid_f, text="➕ تسجيل موظف", font=ctk.CTkFont(weight="bold"), fg_color="#27ae60", hover_color="#1e8449", command=self.add_employee)
        self.btn_add_emp.pack(side="left", padx=5)
        
        self.btn_edit_emp = ctk.CTkButton(grid_f, text="🔄 تحديث البيانات", font=ctk.CTkFont(weight="bold"), fg_color="#f39c12", hover_color="#d68910", state="disabled", command=self.update_employee)
        self.btn_edit_emp.pack(side="left", padx=5)

        # جدول الموظفين
        tree_frame = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        tree_frame.pack(expand=True, fill="both", padx=10, pady=10)
        
        self.emp_tree = ttk.Treeview(tree_frame, columns=('id', 'name', 'role', 'salary', 'hours', 'advances', 'deductions'), show='headings')
        self.emp_tree.heading('id', text='الكود')
        self.emp_tree.heading('name', text='الاسم')
        self.emp_tree.heading('role', text='الوظيفة')
        self.emp_tree.heading('salary', text='الراتب الأساسي')
        self.emp_tree.heading('hours', text='ساعات العمل')
        self.emp_tree.heading('advances', text='إجمالي السلف')
        self.emp_tree.heading('deductions', text='إجمالي الجزاءات')
        
        for col in self.emp_tree['columns']: self.emp_tree.column(col, anchor='center')
        
        self.emp_tree.pack(expand=True, fill="both", padx=10, pady=10)
        self.emp_tree.bind("<Double-1>", self.on_employee_double_click)
        ctk.CTkLabel(tab, text="💡 نكوز: اضغط مرتين على أي موظف لتعديل بياناته (الراتب، الوظيفة، إلخ)", text_color="gray").pack(pady=3)

    # =========================================================
    # 2. تبويب تصفية وحسابات طيارين الدليفري (Delivery Settlement)
    # =========================================================
    def setup_delivery_settlement_tab(self, tab):
        filter_card = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        filter_card.pack(fill="x", padx=10, pady=(10, 5))

        row_f = ctk.CTkFrame(filter_card, fg_color="transparent")
        row_f.pack(fill="x", padx=10, pady=10)

        ctk.CTkLabel(row_f, text="اختر الطيار / عامل التوصيل:", font=ctk.CTkFont(size=14, weight="bold")).pack(side="right", padx=(5, 2))
        self.deliv_driver_combo = ctk.CTkComboBox(row_f, values=["جميع الطيارين"], width=200, font=("Arial", 14, "bold"), command=self.load_delivery_settlement_report)
        self.deliv_driver_combo.pack(side="right", padx=5)

        ctk.CTkLabel(row_f, text="حالة الأوردرات:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(10, 2))
        self.deliv_status_combo = ctk.CTkComboBox(row_f, values=["⏳ المعلقة (في ذمة الطيار)", "✅ المصفاة والموردة للخزينة", "الكل"], width=200, command=self.load_delivery_settlement_report)
        self.deliv_status_combo.pack(side="right", padx=5)

        ctk.CTkButton(row_f, text="📊 تحديث الجرد", width=100, fg_color="#f39c12", hover_color="#d68910", command=self.load_delivery_settlement_report).pack(side="left", padx=5)

        # كروت مؤشرات حساب الطيار
        summary_frame = ctk.CTkFrame(tab, fg_color="transparent")
        summary_frame.pack(fill="x", padx=10, pady=5)

        self.lbl_deliv_count = ctk.CTkLabel(summary_frame, text="عدد الطلبات\n0 طلب", font=ctk.CTkFont(size=15, weight="bold"), fg_color="#34495e", text_color="white", width=160, height=60, corner_radius=10)
        self.lbl_deliv_count.pack(side="left", padx=4, expand=True)

        self.lbl_deliv_goods = ctk.CTkLabel(summary_frame, text="إجمالي مبيعات البضاعة\n0 ج.م", font=ctk.CTkFont(size=15, weight="bold"), fg_color="#2980b9", text_color="white", width=180, height=60, corner_radius=10)
        self.lbl_deliv_goods.pack(side="left", padx=4, expand=True)

        self.lbl_deliv_fees = ctk.CTkLabel(summary_frame, text="خدمة/مصاريف التوصيل\n0 ج.م", font=ctk.CTkFont(size=15, weight="bold"), fg_color="#8e44ad", text_color="white", width=170, height=60, corner_radius=10)
        self.lbl_deliv_fees.pack(side="left", padx=4, expand=True)

        self.lbl_deliv_due = ctk.CTkLabel(summary_frame, text="المطلوب توريده للخزينة\n0 ج.م", font=ctk.CTkFont(size=15, weight="bold"), fg_color="#2ecc71", text_color="white", width=190, height=60, corner_radius=10)
        self.lbl_deliv_due.pack(side="left", padx=4, expand=True)

        self.lbl_deliv_pending = ctk.CTkLabel(summary_frame, text="المتبقي بذمة الطيار\n0 ج.م", font=ctk.CTkFont(size=15, weight="bold"), fg_color="#c0392b", text_color="white", width=190, height=60, corner_radius=10)
        self.lbl_deliv_pending.pack(side="left", padx=4, expand=True)

        # جدول فواتير الطيار التفصيلية
        tree_card = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        tree_card.pack(expand=True, fill="both", padx=10, pady=(5, 5))

        self.deliv_tree = ttk.Treeview(tree_card, columns=('id', 'date', 'customer', 'phone', 'address', 'total', 'fee', 'grand_total', 'status'), show='headings')
        self.deliv_tree.heading('id', text='رقم الفاتورة')
        self.deliv_tree.heading('date', text='التاريخ والوقت')
        self.deliv_tree.heading('customer', text='العميل (الطيار)')
        self.deliv_tree.heading('phone', text='هاتف العميل')
        self.deliv_tree.heading('address', text='عنوان التوصيل')
        self.deliv_tree.heading('total', text='البضاعة')
        self.deliv_tree.heading('fee', text='خدمة التوصيل')
        self.deliv_tree.heading('grand_total', text='المبلغ المحصل')
        self.deliv_tree.heading('status', text='حالة التصفية')

        self.deliv_tree.column('id', width=75, anchor='center')
        self.deliv_tree.column('date', width=135, anchor='center')
        self.deliv_tree.column('customer', width=160, anchor='center')
        self.deliv_tree.column('phone', width=110, anchor='center')
        self.deliv_tree.column('address', width=180, anchor='center')
        self.deliv_tree.column('total', width=90, anchor='center')
        self.deliv_tree.column('fee', width=90, anchor='center')
        self.deliv_tree.column('grand_total', width=105, anchor='center')
        self.deliv_tree.column('status', width=140, anchor='center')
        self.deliv_tree.pack(expand=True, fill="both", padx=10, pady=10)

        # شريط إجراءات التصفية
        action_bar = ctk.CTkFrame(tab, fg_color="transparent")
        action_bar.pack(fill="x", padx=10, pady=(5, 10))

        ctk.CTkButton(action_bar, text="💵 توريد وتصفية حساب الطيار بالكامل للخزينة", font=ctk.CTkFont(size=15, weight="bold"), fg_color="#27ae60", hover_color="#1e8449", height=42, command=self.settle_all_driver_invoices).pack(side="right", padx=5)
        ctk.CTkButton(action_bar, text="💵 تصفية الفواتير المحددة فقط", font=ctk.CTkFont(weight="bold"), fg_color="#2980b9", hover_color="#1f618d", height=42, command=self.settle_selected_invoices).pack(side="right", padx=5)
        ctk.CTkButton(action_bar, text="🖨️ طباعة تقرير تصفية الطيار (Z-Driver)", font=ctk.CTkFont(weight="bold"), fg_color="#8e44ad", hover_color="#732d91", height=42, command=self.print_driver_settlement_receipt).pack(side="left", padx=5)

    def load_delivery_drivers_combo(self):
        self.cursor.execute("SELECT name FROM employees WHERE role IN ('دليفري', 'طيار', 'سائق', 'عامل') OR role LIKE '%دليفري%' OR role LIKE '%طيار%' ORDER BY name")
        drivers = [row[0] for row in self.cursor.fetchall()]
        if not drivers:
            self.cursor.execute("SELECT name FROM employees ORDER BY name")
            drivers = [row[0] for row in self.cursor.fetchall()]
            
        vals = ["جميع الطيارين"] + drivers
        if hasattr(self, 'deliv_driver_combo'):
            self.deliv_driver_combo.configure(values=vals)
            if not self.deliv_driver_combo.get() or self.deliv_driver_combo.get() not in vals:
                self.deliv_driver_combo.set(vals[0])

    def load_delivery_settlement_report(self, *args):
        if not hasattr(self, 'deliv_tree'): return
        for item in self.deliv_tree.get_children(): self.deliv_tree.delete(item)

        driver = self.deliv_driver_combo.get()
        status_filter = self.deliv_status_combo.get()

        query = "SELECT id, date, customer, phone, address, total, delivery_fee, delivery_settled, delivery_person FROM sales WHERE delivery_person IS NOT NULL AND delivery_person != '' AND delivery_person != 'بدون توصيل (تيك أواي)'"
        params = []

        if driver and driver != "جميع الطيارين":
            query += " AND delivery_person = ?"
            params.append(driver)

        if status_filter == "⏳ المعلقة (في ذمة الطيار)":
            query += " AND (delivery_settled = 0 OR delivery_settled IS NULL)"
        elif status_filter == "✅ المصفاة والموردة للخزينة":
            query += " AND delivery_settled = 1"

        query += " ORDER BY id DESC"
        self.cursor.execute(query, params)
        rows = self.cursor.fetchall()

        count_orders = 0
        total_goods = 0.0
        total_fees = 0.0
        total_due = 0.0
        total_pending = 0.0

        for r in rows:
            s_id, s_date, cust, phone, addr, total, fee, settled, d_person = r
            total = total or 0.0
            fee = fee or 0.0
            grand = total + fee

            count_orders += 1
            total_goods += total
            total_fees += fee
            total_due += grand

            if not settled:
                total_pending += grand
                st_text = "⏳ معلقة في ذمة الطيار"
                tag = 'pending'
            else:
                st_text = "✅ مصفاة وموردة"
                tag = 'settled'

            cust_display = f"{cust or 'عميل نقدي'} ({d_person})"
            self.deliv_tree.insert("", "end", values=(s_id, s_date, cust_display, phone or "---", addr or "---", f"{total:g}", f"{fee:g}", f"{grand:g}", st_text), tags=(tag,))

        self.deliv_tree.tag_configure('pending', foreground='#e74c3c')
        self.deliv_tree.tag_configure('settled', foreground='#2ecc71')

        self.lbl_deliv_count.configure(text=f"عدد الطلبات\n{count_orders} طلب")
        self.lbl_deliv_goods.configure(text=f"إجمالي المبيعات\n{total_goods:g} ج.م")
        self.lbl_deliv_fees.configure(text=f"رسوم التوصيل\n{total_fees:g} ج.م")
        self.lbl_deliv_due.configure(text=f"المطلوب للتوريد\n{total_due:g} ج.م")
        self.lbl_deliv_pending.configure(text=f"المتبقي بذمة الطيار\n{total_pending:g} ج.م")

    def settle_all_driver_invoices(self):
        driver = self.deliv_driver_combo.get()
        if not driver or driver == "جميع الطيارين":
            return messagebox.showwarning("تنبيه", "برجاء اختيار طيار محدد أولاً لتصفية حسابه بالكامل!")

        self.cursor.execute("SELECT COUNT(*), SUM(total + COALESCE(delivery_fee,0)) FROM sales WHERE delivery_person=? AND (delivery_settled = 0 OR delivery_settled IS NULL)", (driver,))
        res = self.cursor.fetchone()
        cnt = res[0] or 0
        amt = res[1] or 0.0

        if cnt == 0:
            return messagebox.showinfo("تنبيه", f"لا توجد فواتير معلقة حالياً في ذمة الطيار ({driver})!")

        confirm = messagebox.askyesno("تأكيد التصفية والتوريد", f"هل تأكدت من استلام مبلغ ({amt:g} ج.م) نقداً بالدرج من الطيار ({driver}) لتصفية عدد ({cnt}) أوردر؟")
        if confirm:
            date_now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M")
            self.cursor.execute("UPDATE sales SET delivery_settled=1, delivery_settled_at=? WHERE delivery_person=? AND (delivery_settled = 0 OR delivery_settled IS NULL)", (date_now, driver))
            self.db.commit()
            messagebox.showinfo("نجاح", f"✅ تم تصفية حساب الطيار ({driver}) بنجاح وتوريد مبلغ ({amt:g} ج.م) للخزينة!")
            self.load_delivery_settlement_report()

    def settle_selected_invoices(self):
        selected = self.deliv_tree.selection()
        if not selected:
            return messagebox.showwarning("تنبيه", "برجاء تحديد الفواتير المراد تصفيتها من الجدول أولاً!")

        invoice_ids = []
        for item in selected:
            vals = self.deliv_tree.item(item)['values']
            invoice_ids.append(vals[0])

        confirm = messagebox.askyesno("تأكيد تصفية الفواتير", f"هل تأكدت من تصفية الفواتير المحددة بعدد ({len(invoice_ids)}) أوردر؟")
        if confirm:
            date_now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M")
            for inv_id in invoice_ids:
                self.cursor.execute("UPDATE sales SET delivery_settled=1, delivery_settled_at=? WHERE id=?", (date_now, inv_id))
            self.db.commit()
            messagebox.showinfo("نجاح", "✅ تم تصفية الفواتير المحددة بنجاح وتوريد مبالغها للخزينة!")
            self.load_delivery_settlement_report()

    def print_driver_settlement_receipt(self):
        driver = self.deliv_driver_combo.get()
        file_path = filedialog.asksaveasfilename(defaultextension=".txt", initialfile=f"تصفية_طيار_{driver}_{datetime.date.today()}.txt", title="حفظ تقرير تصفية الطيار", filetypes=[("Text Files", "*.txt")])
        if not file_path: return
        try:
            with open(file_path, "w", encoding="utf-8") as file:
                file.write("====================================================\n")
                file.write("           تقرير تصفية حساب طيار دليفري (Z-Driver)\n")
                file.write(f"           اسم الطيار: {driver}\n")
                file.write(f"           التاريخ: {datetime.datetime.now().strftime('%Y-%m-%d %H:%M')}\n")
                file.write("====================================================\n\n")
                file.write(self.lbl_deliv_count.cget("text").replace("\n", ": ") + "\n")
                file.write(self.lbl_deliv_goods.cget("text").replace("\n", ": ") + "\n")
                file.write(self.lbl_deliv_fees.cget("text").replace("\n", ": ") + "\n")
                file.write(self.lbl_deliv_due.cget("text").replace("\n", ": ") + "\n")
                file.write(self.lbl_deliv_pending.cget("text").replace("\n", ": ") + "\n\n")
                file.write(f"{'رقم':<8} | {'التاريخ':<18} | {'العميل والطيار':<22} | {'الهاتف والعنوان':<25} | {'المبلغ':<10} | {'الخدمة':<8} | {'الإجمالي':<10}\n")
                file.write("-" * 110 + "\n")
                for item in self.deliv_tree.get_children():
                    v = self.deliv_tree.item(item)['values']
                    file.write(f"{v[0]:<8} | {v[1]:<18} | {v[2]:<22} | {v[3]} - {v[4]:<25} | {v[5]:<10} | {v[6]:<8} | {v[7]:<10}\n")
            messagebox.showinfo("نجاح", f"تم تصدير وطباعة كشف تصفية الطيار بنجاح في:\n{file_path}")
        except Exception as e:
            messagebox.showerror("خطأ", f"حدث خطأ أثناء التصدير:\n{e}")

    # ==========================================
    # 3. السلف والجزاءات
    # ==========================================
    def setup_advances_tab(self, tab):
        tab.grid_columnconfigure(0, weight=1)
        tab.grid_columnconfigure(1, weight=1)
        
        # قسم السلف
        adv_frame = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        adv_frame.grid(row=0, column=0, sticky="nsew", padx=10, pady=10)
        ctk.CTkLabel(adv_frame, text="تسجيل سلفة (تخصم من الخزينة)", font=ctk.CTkFont(size=18, weight="bold"), text_color="#3498db").pack(pady=10)
        
        self.adv_emp_combo = ctk.CTkComboBox(adv_frame, values=[], width=220, font=("Arial", 14))
        self.adv_emp_combo.pack(pady=10)
        
        self.hr_advance = ctk.CTkEntry(adv_frame, placeholder_text="مبلغ السلفة", width=220, justify="center", font=("Arial", 14))
        self.hr_advance.pack(pady=10)
        
        ctk.CTkButton(adv_frame, text="💰 صرف سلفة", font=ctk.CTkFont(weight="bold"), fg_color="#27ae60", hover_color="#1e8449", command=self.add_advance).pack(pady=20)

        # قسم الجزاءات/الخصومات
        ded_frame = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        ded_frame.grid(row=0, column=1, sticky="nsew", padx=10, pady=10)
        ctk.CTkLabel(ded_frame, text="توقيع جزاء/خصم", font=ctk.CTkFont(size=18, weight="bold"), text_color="#e74c3c").pack(pady=10)
        
        self.ded_emp_combo = ctk.CTkComboBox(ded_frame, values=[], width=220, font=("Arial", 14))
        self.ded_emp_combo.pack(pady=10)
        
        self.hr_deduction = ctk.CTkEntry(ded_frame, placeholder_text="مبلغ الخصم/الجزاء", width=220, justify="center", font=("Arial", 14))
        self.hr_deduction.pack(pady=10)
        
        ctk.CTkButton(ded_frame, text="⚠️ توقيع الخصم", font=ctk.CTkFont(weight="bold"), fg_color="#c0392b", hover_color="#922b21", command=self.add_deduction).pack(pady=20)

    # ==========================================
    # 4. صرف الرواتب
    # ==========================================
    def setup_payroll_tab(self, tab):
        pay_frame = ctk.CTkFrame(tab, corner_radius=12, fg_color="#2c3e50")
        pay_frame.pack(fill="x", pady=20, padx=50)
        
        ctk.CTkLabel(pay_frame, text="اختر الموظف لصرف راتبه:", font=ctk.CTkFont(size=16, weight="bold")).grid(row=0, column=0, padx=10, pady=20)
        self.pay_emp_combo = ctk.CTkComboBox(pay_frame, values=[], width=250, font=("Arial", 14))
        self.pay_emp_combo.grid(row=0, column=1, padx=10, pady=20)
        
        ctk.CTkButton(pay_frame, text="💵 حساب وصرف الراتب", font=ctk.CTkFont(size=15, weight="bold"), fg_color="#27ae60", hover_color="#1e8449", command=self.pay_salary).grid(row=0, column=2, padx=20)

    # ==========================================
    # دوال العمليات
    # ==========================================
    def load_employees(self):
        for item in self.emp_tree.get_children(): self.emp_tree.delete(item)
        self.cursor.execute("SELECT id, name, role, salary, hours, advances, deductions FROM employees")
        emps = self.cursor.fetchall()
        
        emp_list = [f"{e[0]} - {e[1]} ({e[2]})" for e in emps]
        if hasattr(self, 'adv_emp_combo'):
            self.adv_emp_combo.configure(values=emp_list)
            self.ded_emp_combo.configure(values=emp_list)
            self.pay_emp_combo.configure(values=emp_list)

        for e in emps:
            self.emp_tree.insert("", "end", values=(e[0], e[1], e[2], f"{e[3]:g}", e[4], f"{e[5]:g}", f"{e[6]:g}"))

    def on_employee_double_click(self, event):
        selected = self.emp_tree.selection()
        if not selected: return
        item = self.emp_tree.item(selected[0])['values']
        
        self.current_edit_emp_id = item[0]
        self.hr_name.delete(0, 'end')
        self.hr_name.insert(0, item[1])
        self.hr_role.set(item[2])
        self.hr_salary.delete(0, 'end')
        self.hr_salary.insert(0, str(item[3]))
        self.hr_hours.delete(0, 'end')
        self.hr_hours.insert(0, str(item[4]))
        
        self.btn_add_emp.configure(state="disabled")
        self.btn_edit_emp.configure(state="normal")

    def add_employee(self):
        name = self.hr_name.get().strip()
        role = self.hr_role.get()
        if not name: return
        try:
            salary = float(self.hr_salary.get() or 0)
            hours = int(self.hr_hours.get() or 0)
            self.cursor.execute("INSERT INTO employees (name, role, salary, hours) VALUES (?, ?, ?, ?)", (name, role, salary, hours))
            self.db.commit()
            messagebox.showinfo("نجاح", f"تم تسجيل {name} كوظيفة ({role}) بنجاح!")
            self.load_employees()
            self.load_delivery_drivers_combo()
            self._clear_form()
        except:
            messagebox.showerror("خطأ", "تأكد من إدخال الأرقام بشكل صحيح.")

    def update_employee(self):
        if not self.current_edit_emp_id: return
        name = self.hr_name.get().strip()
        role = self.hr_role.get()
        try:
            salary = float(self.hr_salary.get() or 0)
            hours = int(self.hr_hours.get() or 0)
            self.cursor.execute("UPDATE employees SET name=?, role=?, salary=?, hours=? WHERE id=?", 
                                (name, role, salary, hours, self.current_edit_emp_id))
            self.db.commit()
            messagebox.showinfo("نجاح", "تم تحديث بيانات الموظف بنجاح.")
            self.load_employees()
            self.load_delivery_drivers_combo()
            self._clear_form()
            self.btn_add_emp.configure(state="normal")
            self.btn_edit_emp.configure(state="disabled")
            self.current_edit_emp_id = None
        except:
            messagebox.showerror("خطأ", "تأكد من إدخال الأرقام بشكل صحيح.")

    def _clear_form(self):
        self.hr_name.delete(0, 'end')
        self.hr_salary.delete(0, 'end')
        self.hr_hours.delete(0, 'end')
        self.hr_role.set("عامل")

    def add_advance(self):
        emp_str = self.adv_emp_combo.get()
        if not emp_str: return
        e_id = int(emp_str.split(" - ")[0])
        try:
            adv = float(self.hr_advance.get())
            if adv <= 0: raise ValueError
            self.cursor.execute("UPDATE employees SET advances = advances + ? WHERE id=?", (adv, e_id))
            date_now = datetime.datetime.now().strftime("%Y-%m-%d")
            self.cursor.execute("INSERT INTO expenses (category, amount, note, date) VALUES (?, ?, ?, ?)", 
                                ("سلف عاملين", adv, f"سلفة للعامل رقم {e_id}", date_now))
            self.db.commit()
            messagebox.showinfo("نجاح", "تم تسجيل السلفة وخصمها من الخزينة آلياً.")
            self.hr_advance.delete(0, 'end')
            self.load_employees()
        except:
            messagebox.showerror("خطأ", "برجاء إدخال مبلغ صحيح.")

    def add_deduction(self):
        emp_str = self.ded_emp_combo.get()
        if not emp_str: return
        e_id = int(emp_str.split(" - ")[0])
        try:
            ded = float(self.hr_deduction.get())
            if ded <= 0: raise ValueError
            self.cursor.execute("UPDATE employees SET deductions = deductions + ? WHERE id=?", (ded, e_id))
            self.db.commit()
            messagebox.showinfo("نجاح", "تم تسجيل الجزاء بنجاح (سيتم خصمه وقت صرف الراتب).")
            self.hr_deduction.delete(0, 'end')
            self.load_employees()
        except:
            messagebox.showerror("خطأ", "برجاء إدخال مبلغ صحيح.")

    def pay_salary(self):
        emp_str = self.pay_emp_combo.get()
        if not emp_str: return
        e_id = int(emp_str.split(" - ")[0])
        try:
            self.cursor.execute("SELECT name, salary, advances, deductions FROM employees WHERE id=?", (e_id,))
            emp = self.cursor.fetchone()
            if emp:
                name, salary, advances, deductions = emp
                net_pay = salary - advances - deductions
                
                msg = f"بيانات صرف راتب: {name}\n\n"
                msg += f"الراتب الأساسي: {salary:g} ج.م\n"
                msg += f"إجمالي السلف: {advances:g} ج.م\n"
                msg += f"إجمالي الجزاءات: {deductions:g} ج.م\n"
                msg += "-" * 20 + "\n"
                msg += f"الصافي المستحق للصرف: {net_pay:g} ج.م\n\nتأكيد الصرف من الخزينة؟"
                
                if messagebox.askyesno("تأكيد صرف الراتب", msg):
                    # تصفير السلف والجزاءات
                    self.cursor.execute("UPDATE employees SET advances = 0, deductions = 0 WHERE id=?", (e_id,))
                    
                    if net_pay > 0:
                        date_now = datetime.datetime.now().strftime("%Y-%m-%d")
                        self.cursor.execute("INSERT INTO expenses (category, amount, note, date) VALUES (?, ?, ?, ?)", 
                                            ("رواتب عاملين", net_pay, f"صرف راتب العامل {name}", date_now))
                    self.db.commit()
                    messagebox.showinfo("نجاح", "تم صرف الراتب وتصفير السلف والجزاءات وتحديث الخزينة.")
                    self.load_employees()
            else:
                messagebox.showerror("خطأ", "الموظف غير موجود.")
        except Exception as e:
            messagebox.showerror("خطأ", f"حدث خطأ: {e}")