import customtkinter as ctk
import tkinter.messagebox as messagebox
from tkinter import ttk
import sqlite3
import datetime

class ReturnsPage(ctk.CTkFrame):
    def __init__(self, parent, db_conn, app):
        super().__init__(parent, fg_color="transparent")
        self.db = db_conn
        self.cursor = self.db.cursor()
        self.app = app
        
        self.ret_selected_id = None
        self.current_invoice_data = None
        self.current_invoice_items = []
        
        self.setup_ui()

    def setup_ui(self):
        # 1. العنوان وشريط البحث والباركود العلوي
        top_frame = ctk.CTkFrame(self, fg_color="#1e272e", corner_radius=12)
        top_frame.pack(fill="x", padx=15, pady=(10, 8))

        ctk.CTkLabel(
            top_frame, 
            text="↩️ نظام فحص واسترجاع فواتير المبيعات (مرتجع بالباركود)", 
            font=ctk.CTkFont(size=20, weight="bold"), 
            text_color="#2ecc71"
        ).pack(side="right", padx=15, pady=10)

        # حقل مسح الباركود السريع
        scan_box = ctk.CTkFrame(top_frame, fg_color="#2c3e50", corner_radius=8)
        scan_box.pack(side="left", padx=15, pady=8)

        ctk.CTkLabel(scan_box, text="📷 مسح باركود الفاتورة:", font=ctk.CTkFont(weight="bold"), text_color="#f39c12").pack(side="right", padx=8, pady=4)
        
        self.ent_barcode_scan = ctk.CTkEntry(
            scan_box, 
            placeholder_text="امسح باركود الفاتورة (INV-...) أو اكتب رقمها واضغط Enter...", 
            width=340, 
            font=("Arial", 14, "bold"),
            justify="center"
        )
        self.ent_barcode_scan.pack(side="right", padx=6, pady=4)
        self.ent_barcode_scan.bind("<Return>", self.on_barcode_scanned)
        
        ctk.CTkButton(scan_box, text="🔍 جلب الفاتورة", width=100, fg_color="#27ae60", hover_color="#1e8449", font=ctk.CTkFont(weight="bold"), command=self.on_barcode_scanned).pack(side="right", padx=6, pady=4)

        self.status_label = ctk.CTkLabel(self, text="", font=ctk.CTkFont(size=14, weight="bold"))
        self.status_label.pack(pady=(0, 4))

        # 2. الحاوية الرئيسية (شاشتان: قائمة الفواتير يميناً + محتويات الفاتورة يساراً)
        main_container = ctk.CTkFrame(self, fg_color="transparent")
        main_container.pack(fill="both", expand=True, padx=15, pady=5)
        main_container.grid_columnconfigure(0, weight=6) # تفاصيل الفاتورة ومحتوياتها
        main_container.grid_columnconfigure(1, weight=5) # جدول الفواتير
        main_container.grid_rowconfigure(0, weight=1)

        # ===============================================
        # الجانب الأيمن: جدول الفواتير السابقة
        # ===============================================
        right_panel = ctk.CTkFrame(main_container, fg_color="#2c3e50", corner_radius=12)
        right_panel.grid(row=0, column=1, sticky="nsew", padx=(5, 0))

        r_header = ctk.CTkFrame(right_panel, fg_color="#1f538d", corner_radius=8)
        r_header.pack(fill="x", padx=10, pady=8)
        ctk.CTkLabel(r_header, text="🧾 قائمة فواتير المبيعات المسجلة", font=ctk.CTkFont(size=15, weight="bold"), text_color="white").pack(side="right", padx=10, pady=6)
        
        ctk.CTkButton(r_header, text="🔄 تحديث", width=70, height=28, fg_color="#34495e", command=self.ret_load_invoices).pack(side="left", padx=8, pady=4)

        # حقل بحث سريع
        r_search = ctk.CTkFrame(right_panel, fg_color="transparent")
        r_search.pack(fill="x", padx=10, pady=(0, 6))
        ctk.CTkLabel(r_search, text="بحث برقم/عميل:").pack(side="right", padx=4)
        self.ret_search_var = ctk.StringVar()
        self.ret_search_var.trace("w", self.ret_live_search)
        ctk.CTkEntry(r_search, textvariable=self.ret_search_var, width=160, placeholder_text="اكتب للبحث...").pack(side="right", padx=4)

        # جدول الفواتير
        inv_tree_frame = ctk.CTkFrame(right_panel, fg_color="transparent")
        inv_tree_frame.pack(fill="both", expand=True, padx=10, pady=(0, 10))

        self.ret_tree = ttk.Treeview(inv_tree_frame, columns=('id', 'date', 'total', 'customer', 'status'), show='headings', height=12)
        self.ret_tree.heading('id', text='رقم الفاتورة')
        self.ret_tree.heading('date', text='التاريخ والوقت')
        self.ret_tree.heading('total', text='الإجمالي')
        self.ret_tree.heading('customer', text='العميل')
        self.ret_tree.heading('status', text='الحالة')

        self.ret_tree.column('id', width=80, anchor='center')
        self.ret_tree.column('date', width=130, anchor='center')
        self.ret_tree.column('total', width=80, anchor='center')
        self.ret_tree.column('customer', width=110, anchor='center')
        self.ret_tree.column('status', width=90, anchor='center')

        r_scroll = ttk.Scrollbar(inv_tree_frame, orient="vertical", command=self.ret_tree.yview)
        self.ret_tree.configure(yscrollcommand=r_scroll.set)
        self.ret_tree.pack(side="left", fill="both", expand=True)
        r_scroll.pack(side="right", fill="y")

        self.ret_tree.bind("<<TreeviewSelect>>", self.ret_on_select)
        self.ret_tree.bind("<Double-1>", lambda e: self.ent_barcode_scan.focus())

        # ===============================================
        # الجانب الأيسر: محتويات وتفاصيل الفاتورة وأزرار الاسترجاع
        # ===============================================
        left_panel = ctk.CTkFrame(main_container, fg_color="#2c3e50", corner_radius=12)
        left_panel.grid(row=0, column=0, sticky="nsew", padx=(0, 5))

        l_header = ctk.CTkFrame(left_panel, fg_color="#1a252f", corner_radius=8)
        l_header.pack(fill="x", padx=10, pady=8)
        ctk.CTkLabel(l_header, text="📦 محتويات الفاتورة المحددة للإرجاع أو الإلغاء", font=ctk.CTkFont(size=15, weight="bold"), text_color="#f39c12").pack(side="right", padx=10, pady=6)

        # بطاقة معلومات الفاتورة
        self.info_card = ctk.CTkFrame(left_panel, fg_color="#1e272e", corner_radius=8)
        self.info_card.pack(fill="x", padx=10, pady=4)
        
        self.lbl_inv_title = ctk.CTkLabel(self.info_card, text="لم يتم تحديد أي فاتورة بعد", font=ctk.CTkFont(size=14, weight="bold"), text_color="#bdc3c7")
        self.lbl_inv_title.pack(pady=8)

        # جدول محتويات الفاتورة
        items_frame = ctk.CTkFrame(left_panel, fg_color="transparent")
        items_frame.pack(fill="both", expand=True, padx=10, pady=6)

        columns_items = ('p_id', 'local_code', 'name', 'qty', 'price', 'total', 'stock')
        self.items_tree = ttk.Treeview(items_frame, columns=columns_items, show='headings', height=9)
        self.items_tree.heading('p_id', text='ID')
        self.items_tree.heading('local_code', text='كود الصنف')
        self.items_tree.heading('name', text='اسم الصنف')
        self.items_tree.heading('qty', text='الكمية المباعة')
        self.items_tree.heading('price', text='سعر الوحدة')
        self.items_tree.heading('total', text='الإجمالي')
        self.items_tree.heading('stock', text='المخزن الآن')

        self.items_tree.column('p_id', width=0, stretch=False)
        self.items_tree.column('local_code', width=75, anchor='center')
        self.items_tree.column('name', width=160, anchor='center')
        self.items_tree.column('qty', width=80, anchor='center')
        self.items_tree.column('price', width=75, anchor='center')
        self.items_tree.column('total', width=80, anchor='center')
        self.items_tree.column('stock', width=80, anchor='center')

        i_scroll = ttk.Scrollbar(items_frame, orient="vertical", command=self.items_tree.yview)
        self.items_tree.configure(yscrollcommand=i_scroll.set)
        self.items_tree.pack(side="left", fill="both", expand=True)
        i_scroll.pack(side="right", fill="y")

        self.items_tree.bind("<Double-1>", lambda e: self.process_item_return())

        # أزرار الإجراءات
        actions_bar = ctk.CTkFrame(left_panel, fg_color="transparent")
        actions_bar.pack(fill="x", padx=10, pady=(5, 10))

        self.btn_return_item = ctk.CTkButton(
            actions_bar, 
            text="🔴 استرجاع الصنف المحدد فقط", 
            fg_color="#e67e22", 
            hover_color="#d35400", 
            font=ctk.CTkFont(weight="bold"), 
            height=38, 
            state="disabled", 
            command=self.process_item_return
        )
        self.btn_return_item.pack(side="right", expand=True, fill="x", padx=(0, 4))

        self.btn_return_all = ctk.CTkButton(
            actions_bar, 
            text="⚠️ استرجاع الفاتورة بالكامل", 
            fg_color="#c0392b", 
            hover_color="#922b21", 
            font=ctk.CTkFont(weight="bold"), 
            height=38, 
            state="disabled", 
            command=self.process_full_return
        )
        self.btn_return_all.pack(side="left", expand=True, fill="x", padx=(4, 0))

    def on_show(self):
        self.ret_load_invoices()
        self.ent_barcode_scan.focus()
        self.after(20, lambda: self.ent_barcode_scan.select_range(0, 'end'))

    def show_status(self, msg, color="green"):
        self.status_label.configure(text=msg, text_color=color)
        self.after(3500, lambda: self.status_label.configure(text=""))

    def on_barcode_scanned(self, event=None):
        raw_val = self.ent_barcode_scan.get().strip()
        if not raw_val:
            return

        # 1. تحويل الأرقام العربية إلى إنجليزية وتوحيد الأحرف
        arabic_digits = "٠١٢٣٤٥٦٧٨٩"
        english_digits = "0123456789"
        trans_table = str.maketrans(arabic_digits, english_digits)
        clean_text = raw_val.translate(trans_table).replace("*", "").strip()

        # 2. استخراج الأرقام من النص (يدعم: INV-15, INV15, هىر-15, #15, 15... إلخ)
        import re
        match = re.search(r'\d+', clean_text)
        
        if match:
            inv_id = int(match.group())
            # محاولة البحث عن الفاتورة برقمها المباشر
            self.cursor.execute("SELECT id FROM sales WHERE id=?", (inv_id,))
            if self.cursor.fetchone():
                self.load_invoice_details(inv_id)
                self.ent_barcode_scan.delete(0, 'end')
                return

        # 3. إذا لم تكن رقم فاتورة مباشر، ربما تم مسح باركود منتج للبحث عن فواتيره
        product_code = clean_text
        if len(product_code) == 13 and product_code.startswith("20"):
            product_code = product_code[2:7]
            
        self.cursor.execute("""
            SELECT s.id 
            FROM sales s 
            JOIN sale_items si ON si.sale_id = s.id 
            JOIN products p ON si.product_id = p.id 
            WHERE p.barcode=? OR p.local_code=? OR p.barcode2=? OR p.barcode3=? OR ',' || COALESCE(p.all_barcodes, '') || ',' LIKE ?
            ORDER BY s.id DESC LIMIT 1
        """, (product_code, product_code, product_code, product_code, f'%,{product_code},%'))
        
        p_sale = self.cursor.fetchone()
        if p_sale:
            found_sale_id = p_sale[0]
            self.load_invoice_details(found_sale_id)
            self.show_status(f"💡 تم العثور على آخر فاتورة #{found_sale_id} تحتوي على هذا المنتج!", "#3498db")
            self.ent_barcode_scan.delete(0, 'end')
            return

        if match:
            self.show_status(f"❌ لم يتم العثور على فاتورة رقم #{match.group()} في النظام!", "#e74c3c")
        else:
            self.show_status(f"❌ تعذر استخراج رقم فاتورة صالح من: {raw_val}", "#e74c3c")
            
        self.ent_barcode_scan.delete(0, 'end')

    def ret_load_invoices(self, search_term=""):
        for item in self.ret_tree.get_children(): self.ret_tree.delete(item)
        query = "SELECT id, date, total, customer, status FROM sales WHERE id LIKE ? OR customer LIKE ? OR phone LIKE ? ORDER BY id DESC"
        s = f'%{search_term}%'
        self.cursor.execute(query, (s, s, s))
        for row in self.cursor.fetchall():
            self.ret_tree.insert("", "end", values=row)

    def ret_live_search(self, *args):
        self.ret_load_invoices(self.ret_search_var.get())

    def ret_on_select(self, event):
        selected = self.ret_tree.selection()
        if selected:
            item = self.ret_tree.item(selected[0])['values']
            inv_id = item[0]
            self.load_invoice_details(inv_id)

    def load_invoice_details(self, inv_id):
        self.cursor.execute("SELECT id, total, date, customer, phone, status, payment_method, discount, delivery_fee FROM sales WHERE id=?", (inv_id,))
        inv_row = self.cursor.fetchone()
        
        if not inv_row:
            self.show_status(f"❌ لم يتم العثور على فاتورة رقم #{inv_id}!", "#e74c3c")
            return

        self.ret_selected_id = inv_row[0]
        self.current_invoice_data = inv_row

        inv_id, total, date, cust, phone, status, pay_method, discount, d_fee = inv_row
        cust_name = cust or "عميل نقدي"
        
        status_color = "#2ecc71" if status == "مكتملة" else ("#f39c12" if "جزئي" in status else "#e74c3c")

        self.lbl_inv_title.configure(
            text=f"🧾 فاتورة #{inv_id} | التاريخ: {date} | العميل: {cust_name} | الإجمالي: {total:g} ج.م | الحالة: {status}",
            text_color=status_color
        )

        # تحميل الأصناف التابعة للفاتورة
        for item in self.items_tree.get_children(): self.items_tree.delete(item)
        
        self.cursor.execute("""
            SELECT p.id, COALESCE(p.local_code, '---'), p.name, si.qty, p.price, p.stock 
            FROM sale_items si 
            JOIN products p ON si.product_id = p.id 
            WHERE si.sale_id = ?
        """, (inv_id,))
        
        self.current_invoice_items = self.cursor.fetchall()

        if self.current_invoice_items:
            for r in self.current_invoice_items:
                p_id, loc_code, p_name, qty, price, stock = r
                subtotal = qty * price
                self.items_tree.insert("", "end", values=(p_id, loc_code, p_name, f"{qty:g}", f"{price:g}", f"{subtotal:g}", f"{stock:g}"))
            
            if status != 'مرتجع بالكامل':
                self.btn_return_item.configure(state="normal")
                self.btn_return_all.configure(state="normal")
            else:
                self.btn_return_item.configure(state="disabled")
                self.btn_return_all.configure(state="disabled")
                
            self.show_status(f"✅ تم تحميل الفاتورة #{inv_id} بنجاح ({len(self.current_invoice_items)} صنف)", "#2ecc71")
        else:
            self.btn_return_item.configure(state="disabled")
            self.btn_return_all.configure(state="disabled")
            self.show_status(f"⚠️ الفاتورة #{inv_id} لا تحتوي على أصناف مسجلة أو تم إرجاعها بالكامل مسبقاً!", "#f39c12")

    def process_item_return(self):
        selected = self.items_tree.selection()
        if not selected:
            messagebox.showwarning("تنبيه", "اختر الصنف المراد إرجاعه من جدول محتويات الفاتورة!")
            return

        item_values = self.items_tree.item(selected[0])['values']
        p_id = int(item_values[0])
        loc_code = str(item_values[1])
        prod_name = str(item_values[2])
        sold_qty = float(item_values[3])
        unit_price = float(item_values[4])

        dialog = ctk.CTkInputDialog(
            text=f"الصنف: {prod_name}\nالكمية المباعة في الفاتورة: {sold_qty:g}\nسعر الوحدة: {unit_price:g} ج.م\n\nأدخل الكمية / الوزن المراد استرجاعه للمخزن:", 
            title="استرجاع صنف محدد"
        )
        val = dialog.get_input()
        if val is None: return

        try:
            return_qty = float(val)
            if return_qty <= 0 or return_qty > sold_qty:
                messagebox.showerror("خطأ", f"الكمية يجب أن تكون أكبر من 0 ولا تزيد عن المباع ({sold_qty:g})!")
                return
        except ValueError:
            messagebox.showerror("خطأ", "برجاء إدخال أرقام صحيحة!")
            return

        refund_amount = round(return_qty * unit_price, 2)
        confirm = messagebox.askyesno(
            "تأكيد الاسترجاع", 
            f"هل تريد استرجاع كمية ({return_qty:g}) من ({prod_name})؟\nالمبلغ المسترد للعميل: {refund_amount:g} ج.م\nسيتم إعادة الكمية فوراً للمخزن."
        )
        if not confirm: return

        try:
            # 1. إعادة الكمية للمخزن
            self.cursor.execute("UPDATE products SET stock = stock + ? WHERE id=?", (return_qty, p_id))

            # 2. تعديل جدول عناصر الفاتورة
            if return_qty >= sold_qty:
                self.cursor.execute("DELETE FROM sale_items WHERE sale_id=? AND product_id=?", (self.ret_selected_id, p_id))
            else:
                self.cursor.execute("UPDATE sale_items SET qty = qty - ? WHERE sale_id=? AND product_id=?", (return_qty, self.ret_selected_id, p_id))

            # 3. فحص العناصر المتبقية وتحديث إجمالي الفاتورة وحالتها
            self.cursor.execute("SELECT COUNT(*) FROM sale_items WHERE sale_id=?", (self.ret_selected_id,))
            remaining_count = self.cursor.fetchone()[0]

            new_status = 'مرتجع جزئي' if remaining_count > 0 else 'مرتجع بالكامل'
            self.cursor.execute("UPDATE sales SET total = MAX(0, total - ?), status = ? WHERE id=?", (refund_amount, new_status, self.ret_selected_id))
            self.db.commit()

            messagebox.showinfo("تم الاسترجاع بنجاح", f"✅ تم استرجاع ({return_qty:g}) من ({prod_name}) بنجاح!\nالمبلغ المسترد: {refund_amount:g} ج.م وتم تحديث المخزن.")
            self.ret_load_invoices()
            self.load_invoice_details(self.ret_selected_id)

        except Exception as e:
            messagebox.showerror("خطأ أثناء الاسترجاع", str(e))

    def process_full_return(self):
        if not self.ret_selected_id: return
        confirm = messagebox.askyesno(
            "تحذير استرجاع كامل", 
            f"هل أنت متأكد من استرجاع الفاتورة رقم #{self.ret_selected_id} بالكامل؟\nسيتم إرجاع جميع محتوياتها للمخزن فوراً وإلغاء الفاتورة."
        )
        if not confirm: return

        try:
            self.cursor.execute("SELECT product_id, qty FROM sale_items WHERE sale_id=?", (self.ret_selected_id,))
            items = self.cursor.fetchall()
            
            for p_id, qty in items:
                self.cursor.execute("UPDATE products SET stock = stock + ? WHERE id=?", (qty, p_id))

            self.cursor.execute("UPDATE sales SET status = 'مرتجع بالكامل', total = 0 WHERE id=?", (self.ret_selected_id,))
            self.cursor.execute("DELETE FROM sale_items WHERE sale_id=?", (self.ret_selected_id,))
            self.db.commit()

            messagebox.showinfo("نجاح", f"✅ تم استرجاع الفاتورة #{self.ret_selected_id} بجميع محتوياتها للمخزن بنجاح!")
            self.ret_load_invoices()
            self.load_invoice_details(self.ret_selected_id)

        except Exception as e:
            messagebox.showerror("خطأ", f"حدث خطأ أثناء الاسترجاع:\n{e}")