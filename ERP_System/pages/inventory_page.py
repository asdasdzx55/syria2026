import customtkinter as ctk
import tkinter.messagebox as messagebox
from tkinter import ttk
import sqlite3

class InventoryPage(ctk.CTkFrame):
    def __init__(self, parent, db_conn, app):
        super().__init__(parent, fg_color="transparent")
        self.db = db_conn
        self.cursor = self.db.cursor()
        self.app = app
        
        self.all_items = []  # قائمة الأصناف المحملة
        self.selected_item = None
        self.modified_items = {} # { product_id: new_stock }
        
        self.setup_ui()

    def on_show(self):
        self.load_inventory_data()
        self.ent_search.focus_set()

    def setup_ui(self):
        # ----------------- 1. رأس الصفحة -----------------
        header_frame = ctk.CTkFrame(self, fg_color="#1e272e", corner_radius=12)
        header_frame.pack(fill="x", padx=15, pady=(10, 5))

        title_lbl = ctk.CTkLabel(
            header_frame, 
            text="📋 نظام جرد المخزون وتعديل الكميات الفعلية", 
            font=ctk.CTkFont(size=22, weight="bold"), 
            text_color="#2ecc71"
        )
        title_lbl.pack(side="right", padx=20, pady=12)

        subtitle_lbl = ctk.CTkLabel(
            header_frame, 
            text="مطابقة المخزون الفعلي بالمحل مع أرصدة السيستم وحساب الفوارق لحظياً", 
            font=ctk.CTkFont(size=12), 
            text_color="#bdc3c7"
        )
        subtitle_lbl.pack(side="right", padx=10, pady=12)

        # ----------------- 2. شريط الإحصائيات السريعة -----------------
        stats_frame = ctk.CTkFrame(self, fg_color="transparent")
        stats_frame.pack(fill="x", padx=15, pady=5)

        self.card_total = self.create_stat_card(stats_frame, "📦 إجمالي الأصناف", "0 صنف", "#2980b9")
        self.card_total.pack(side="right", fill="x", expand=True, padx=4)

        self.card_cost = self.create_stat_card(stats_frame, "💰 قيمة المخزون (بالتكلفة)", "0.00 ج.م", "#27ae60")
        self.card_cost.pack(side="right", fill="x", expand=True, padx=4)

        self.card_zero = self.create_stat_card(stats_frame, "⚠️ نواقص ومنتهية (0)", "0 صنف", "#e67e22")
        self.card_zero.pack(side="right", fill="x", expand=True, padx=4)

        self.card_mod = self.create_stat_card(stats_frame, "🔄 أصناف تم جردها", "0 صنف", "#8e44ad")
        self.card_mod.pack(side="right", fill="x", expand=True, padx=4)

        # ----------------- 3. شريط البحث وقارئ الباركود -----------------
        search_frame = ctk.CTkFrame(self, fg_color="#2c3e50", corner_radius=10)
        search_frame.pack(fill="x", padx=15, pady=6)

        ctk.CTkLabel(search_frame, text="🔍 البحث والمسح:", font=ctk.CTkFont(weight="bold"), text_color="white").pack(side="right", padx=10, pady=8)

        self.ent_search = ctk.CTkEntry(
            search_frame, 
            placeholder_text="امسح الباركود أو ابحث باسم الصنف / الكود المحلي (5 أرقام)...", 
            width=380, 
            font=("Arial", 14)
        )
        self.ent_search.pack(side="right", padx=6, pady=8)
        self.ent_search.bind("<KeyRelease>", lambda e: self.filter_data())
        self.ent_search.bind("<Return>", self.on_barcode_scanned)

        self.cat_filter = ctk.CTkComboBox(
            search_frame, 
            values=["جميع الأقسام"], 
            width=160, 
            command=lambda e: self.filter_data()
        )
        self.cat_filter.pack(side="right", padx=6, pady=8)

        self.diff_filter = ctk.CTkComboBox(
            search_frame, 
            values=["كل الأصناف", "أصناف بها فارق", "عجز فقط 🔴", "زيادة فقط 🔵", "مطابق 🟢", "رصيد صفر ⚠️"], 
            width=150, 
            command=lambda e: self.filter_data()
        )
        self.diff_filter.pack(side="right", padx=6, pady=8)

        ctk.CTkButton(
            search_frame, 
            text="🔄 تحديث", 
            width=80, 
            fg_color="#34495e", 
            hover_color="#1f538d", 
            command=self.load_inventory_data
        ).pack(side="left", padx=10, pady=8)

        # ----------------- 4. صندوق التعديل السريع للصنف المحدد -----------------
        self.quick_box = ctk.CTkFrame(self, fg_color="#1a252f", corner_radius=10, border_width=1, border_color="#34495e")
        self.quick_box.pack(fill="x", padx=15, pady=6)

        self.lbl_selected_title = ctk.CTkLabel(
            self.quick_box, 
            text="حدد صنفاً من الجدول أو امسح باركود الصنف لتعديل رصيد الجرد الفعلي:", 
            font=ctk.CTkFont(size=14, weight="bold"), 
            text_color="#f39c12"
        )
        self.lbl_selected_title.pack(side="right", padx=15, pady=8)

        # أزرار الزيادة السريعة
        btn_box = ctk.CTkFrame(self.quick_box, fg_color="transparent")
        btn_box.pack(side="left", padx=10, pady=6)

        ctk.CTkButton(btn_box, text="حفظ الصنف 💾", fg_color="#27ae60", hover_color="#2ecc71", width=110, font=ctk.CTkFont(weight="bold"), command=self.save_current_item).pack(side="left", padx=4)

        self.ent_actual_stock = ctk.CTkEntry(btn_box, width=90, font=("Arial", 16, "bold"), justify="center")
        self.ent_actual_stock.pack(side="left", padx=4)
        self.ent_actual_stock.bind("<Return>", lambda e: self.save_current_item())

        ctk.CTkLabel(btn_box, text="الكمية بالجرد:", font=ctk.CTkFont(weight="bold"), text_color="white").pack(side="left", padx=4)

        ctk.CTkButton(btn_box, text="+1", width=42, fg_color="#2980b9", command=lambda: self.adjust_current_stock(1)).pack(side="left", padx=2)
        ctk.CTkButton(btn_box, text="+5", width=42, fg_color="#2980b9", command=lambda: self.adjust_current_stock(5)).pack(side="left", padx=2)
        ctk.CTkButton(btn_box, text="+10", width=48, fg_color="#8e44ad", command=lambda: self.adjust_current_stock(10)).pack(side="left", padx=2)
        ctk.CTkButton(btn_box, text="-1", width=42, fg_color="#c0392b", command=lambda: self.adjust_current_stock(-1)).pack(side="left", padx=2)
        ctk.CTkButton(btn_box, text="صفر (0)", width=60, fg_color="#7f8c8d", command=lambda: self.set_current_stock(0)).pack(side="left", padx=2)

        # ----------------- 5. جدول عرض الأصناف والمخزون -----------------
        table_frame = ctk.CTkFrame(self, fg_color="#2b2b2b")
        table_frame.pack(fill="both", expand=True, padx=15, pady=5)

        columns = ("id", "local_code", "name", "category", "cost", "price", "system_stock", "actual_stock", "diff", "barcode")
        self.tree = ttk.Treeview(table_frame, columns=columns, show="headings", height=14)

        self.tree.heading("id", text="#")
        self.tree.heading("local_code", text="كود (5 أرقام)")
        self.tree.heading("name", text="اسم الصنف")
        self.tree.heading("category", text="القسم")
        self.tree.heading("cost", text="سعر التكلفة")
        self.tree.heading("price", text="سعر البيع")
        self.tree.heading("system_stock", text="رصيد السيستم")
        self.tree.heading("actual_stock", text="الجرد الفعلي 📋")
        self.tree.heading("diff", text="الفارق (عجز/زيادة)")
        self.tree.heading("barcode", text="الباركود")

        self.tree.column("id", width=40, anchor="center")
        self.tree.column("local_code", width=85, anchor="center")
        self.tree.column("name", width=260, anchor="e")
        self.tree.column("category", width=110, anchor="center")
        self.tree.column("cost", width=85, anchor="center")
        self.tree.column("price", width=85, anchor="center")
        self.tree.column("system_stock", width=95, anchor="center")
        self.tree.column("actual_stock", width=105, anchor="center")
        self.tree.column("diff", width=120, anchor="center")
        self.tree.column("barcode", width=150, anchor="center")

        tree_scroll = ttk.Scrollbar(table_frame, orient="vertical", command=self.tree.yview)
        self.tree.configure(yscrollcommand=tree_scroll.set)

        self.tree.pack(side="left", fill="both", expand=True)
        tree_scroll.pack(side="right", fill="y")

        self.tree.bind("<<TreeviewSelect>>", self.on_row_selected)
        self.tree.bind("<Double-1>", lambda e: self.ent_actual_stock.focus_set())

        # ----------------- 6. تذييل الصفحة وأزرار الحفظ الشامل -----------------
        footer_frame = ctk.CTkFrame(self, fg_color="transparent")
        footer_frame.pack(fill="x", padx=15, pady=(5, 10))

        self.lbl_count = ctk.CTkLabel(footer_frame, text="عدد الأصناف المعروضة: 0", font=ctk.CTkFont(size=12, weight="bold"))
        self.lbl_count.pack(side="right", padx=10)

        ctk.CTkButton(
            footer_frame, 
            text="💾 تطبيق وحفظ الجرد الشامل ومزامنته سحابياً", 
            fg_color="#27ae60", 
            hover_color="#1e8449", 
            font=ctk.CTkFont(size=14, weight="bold"), 
            height=40,
            command=self.save_all_audit
        ).pack(side="left", padx=8)

        ctk.CTkButton(
            footer_frame, 
            text="🖨️ طباعة كشف الجرد", 
            fg_color="#2980b9", 
            hover_color="#1f538d", 
            font=ctk.CTkFont(size=13, weight="bold"), 
            height=40,
            command=self.print_inventory_report
        ).pack(side="left", padx=8)

    def create_stat_card(self, parent, title, val, color):
        card = ctk.CTkFrame(parent, fg_color="#1e272e", corner_radius=10, border_width=1, border_color=color)
        ctk.CTkLabel(card, text=title, font=ctk.CTkFont(size=11), text_color="#bdc3c7").pack(pady=(6, 1))
        lbl_val = ctk.CTkLabel(card, text=val, font=ctk.CTkFont(size=16, weight="bold"), text_color=color)
        lbl_val.pack(pady=(0, 6))
        card.lbl_val = lbl_val
        return card

    def load_inventory_data(self):
        try:
            self.cursor.execute("SELECT id, name, category, price, cost, stock, barcode, local_code FROM products ORDER BY name ASC")
            rows = self.cursor.fetchall()
            
            self.all_items = []
            categories_set = set()
            total_cost_val = 0.0
            zero_stock_count = 0

            for r in rows:
                p_id, name, cat, price, cost, stock, barcode, local_code = r
                cat = cat or "عام"
                price = float(price or 0)
                cost = float(cost or 0)
                stock = float(stock or 0)
                actual = self.modified_items.get(p_id, stock)

                categories_set.add(cat)
                total_cost_val += (cost * stock)
                if stock <= 0:
                    zero_stock_count += 1

                self.all_items.append({
                    "id": p_id,
                    "name": name,
                    "category": cat,
                    "price": price,
                    "cost": cost,
                    "stock": stock,
                    "actual_stock": actual,
                    "barcode": barcode or "",
                    "local_code": local_code or ""
                })

            # تحديث فئات الأقسام في القائمة
            cats = ["جميع الأقسام"] + sorted(list(categories_set))
            self.cat_filter.configure(values=cats)

            # تحديث بطاقات الإحصاء
            self.card_total.lbl_val.configure(text=f"{len(self.all_items)} صنف")
            self.card_cost.lbl_val.configure(text=f"{total_cost_val:,.2f} ج.م")
            self.card_zero.lbl_val.configure(text=f"{zero_stock_count} صنف")
            self.card_mod.lbl_val.configure(text=f"{len(self.modified_items)} صنف")

            self.filter_data()

        except Exception as e:
            messagebox.showerror("خطأ في تحميل المخزون", str(e))

    def filter_data(self):
        query = self.ent_search.get().strip().lower()
        selected_cat = self.cat_filter.get()
        diff_type = self.diff_filter.get()

        for item in self.tree.get_children():
            self.tree.delete(item)

        count = 0
        for item in self.all_items:
            # فلتر البحث
            if query:
                match_name = query in item["name"].lower()
                match_code = query in item["local_code"]
                match_bc = query in item["barcode"]
                if not (match_name or match_code or match_bc):
                    continue

            # فلتر القسم
            if selected_cat != "جميع الأقسام" and item["category"] != selected_cat:
                continue

            # حساب الفارق
            diff = item["actual_stock"] - item["stock"]

            # فلتر الفوارق
            if diff_type == "أصناف بها فارق" and abs(diff) < 0.001:
                continue
            elif diff_type == "عجز فقط 🔴" and diff >= 0:
                continue
            elif diff_type == "زيادة فقط 🔵" and diff <= 0:
                continue
            elif diff_type == "مطابق 🟢" and abs(diff) >= 0.001:
                continue
            elif diff_type == "رصيد صفر ⚠️" and item["stock"] > 0:
                continue

            diff_str = "مطابق (0)"
            if diff < 0:
                diff_str = f"عجز ({abs(diff):.2f}) 🔴"
            elif diff > 0:
                diff_str = f"زيادة (+{diff:.2f}) 🔵"

            self.tree.insert("", "end", values=(
                item["id"],
                item["local_code"] or "---",
                item["name"],
                item["category"],
                f"{item['cost']:.2f}",
                f"{item['price']:.2f}",
                f"{item['stock']:.2f}",
                f"{item['actual_stock']:.2f}",
                diff_str,
                item["barcode"] or "---"
            ))
            count += 1

        self.lbl_count.configure(text=f"عدد الأصناف المعروضة: {count} من إجمالي {len(self.all_items)}")

    def on_row_selected(self, event):
        selected = self.tree.selection()
        if not selected:
            return

        values = self.tree.item(selected[0], "values")
        p_id = int(values[0])
        item = next((i for i in self.all_items if i["id"] == p_id), None)
        if item:
            self.selected_item = item
            self.lbl_selected_title.configure(
                text=f"الصنف: {item['name']} | الكود: {item['local_code']} | الرصيد الحالي بالسيستم: {item['stock']:.2f}"
            )
            self.ent_actual_stock.delete(0, 'end')
            self.ent_actual_stock.insert(0, str(item["actual_stock"]))

    def on_barcode_scanned(self, event):
        code = self.ent_search.get().strip()
        if not code:
            return

        # البحث عن الصنف بالباركود أو الكود المحلي
        matched = next((i for i in self.all_items if i["barcode"] == code or i["local_code"] == code or code in i["name"].lower()), None)
        if matched:
            matched["actual_stock"] += 1
            self.modified_items[matched["id"]] = matched["actual_stock"]
            self.selected_item = matched
            self.lbl_selected_title.configure(
                text=f"الصنف: {matched['name']} | الكود: {matched['local_code']} | الرصيد الفعلي بعد الزيادة: {matched['actual_stock']:.2f}"
            )
            self.ent_actual_stock.delete(0, 'end')
            self.ent_actual_stock.insert(0, str(matched["actual_stock"]))
            self.card_mod.lbl_val.configure(text=f"{len(self.modified_items)} صنف")
            self.filter_data()
            self.ent_search.delete(0, 'end')
        else:
            messagebox.showwarning("غير موجود", f"لم يتم العثور على صنف بالباركود أو الكود: {code}")

    def adjust_current_stock(self, delta):
        if not self.selected_item:
            messagebox.showinfo("تنبيه", "يرجى تحديد صنف أولاً من الجدول!")
            return

        current = float(self.ent_actual_stock.get() or 0)
        new_val = max(0.0, current + delta)
        self.set_current_stock(new_val)

    def set_current_stock(self, val):
        if not self.selected_item:
            messagebox.showinfo("تنبيه", "يرجى تحديد صنف أولاً من الجدول!")
            return

        self.selected_item["actual_stock"] = float(val)
        self.modified_items[self.selected_item["id"]] = float(val)
        self.ent_actual_stock.delete(0, 'end')
        self.ent_actual_stock.insert(0, str(val))
        self.card_mod.lbl_val.configure(text=f"{len(self.modified_items)} صنف")
        self.filter_data()

    def save_current_item(self):
        if not self.selected_item:
            messagebox.showinfo("تنبيه", "يرجى تحديد صنف أولاً!")
            return

        try:
            new_stock = float(self.ent_actual_stock.get() or 0)
            p_id = self.selected_item["id"]

            self.cursor.execute("UPDATE products SET stock=? WHERE id=?", (new_stock, p_id))
            self.db.commit()

            # تسجيل مزامنة سحابية إن توفرت
            try:
                self.cursor.execute(
                    "INSERT INTO sync_queue (action, table_name, record_id, payload) VALUES (?, ?, ?, ?)",
                    ("update_stock", "products", p_id, f'{{"stock": {new_stock}}}')
                )
                self.db.commit()
            except: pass

            self.selected_item["stock"] = new_stock
            self.selected_item["actual_stock"] = new_stock
            if p_id in self.modified_items:
                del self.modified_items[p_id]

            self.card_mod.lbl_val.configure(text=f"{len(self.modified_items)} صنف")
            self.filter_data()
            messagebox.showinfo("نجاح", f"تم تحديث رصيد ({self.selected_item['name']}) إلى {new_stock} بنجاح ✓")

        except Exception as e:
            messagebox.showerror("خطأ في الحفظ", str(e))

    def save_all_audit(self):
        if not self.modified_items:
            messagebox.showinfo("تنبيه", "لم يتم تعديل كمية أي صنف بالجرد الحالي!")
            return

        if not messagebox.askyesno("تأكيد الجرد الشامل", f"هل أنت متأكد من حفظ وتطبيق الجرد لـ ({len(self.modified_items)}) صنفاً فوراً؟"):
            return

        try:
            for p_id, n_stock in self.modified_items.items():
                self.cursor.execute("UPDATE products SET stock=? WHERE id=?", (n_stock, p_id))
                try:
                    self.cursor.execute(
                        "INSERT INTO sync_queue (action, table_name, record_id, payload) VALUES (?, ?, ?, ?)",
                        ("update_stock", "products", p_id, f'{{"stock": {n_stock}}}')
                    )
                except: pass

            self.db.commit()
            count = len(self.modified_items)
            self.modified_items.clear()
            self.load_inventory_data()
            messagebox.showinfo("تم بنجاح 🎉", f"تم تطبيق وحفظ الجرد الشامل لـ ({count}) صنفاً ومزامنته سحابياً بنجاح!")

        except Exception as e:
            messagebox.showerror("خطأ في تطبيق الجرد", str(e))

    def print_inventory_report(self):
        import webbrowser
        import os
        from datetime import datetime

        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        total_cost = sum(i["cost"] * i["stock"] for i in self.all_items)

        rows_html = ""
        for i in self.all_items:
            diff = i["actual_stock"] - i["stock"]
            diff_color = "#27ae60" if abs(diff) < 0.001 else ("#c0392b" if diff < 0 else "#2980b9")
            rows_html += f"""
            <tr>
                <td style="border:1px solid #ddd; padding:6px; text-align:center;">{i['local_code'] or '-'}</td>
                <td style="border:1px solid #ddd; padding:6px;">{i['name']}</td>
                <td style="border:1px solid #ddd; padding:6px; text-align:center;">{i['category']}</td>
                <td style="border:1px solid #ddd; padding:6px; text-align:center;">{i['cost']:.2f}</td>
                <td style="border:1px solid #ddd; padding:6px; text-align:center;">{i['price']:.2f}</td>
                <td style="border:1px solid #ddd; padding:6px; text-align:center; font-weight:bold;">{i['stock']:.2f}</td>
                <td style="border:1px solid #ddd; padding:6px; text-align:center; font-weight:bold;">{i['actual_stock']:.2f}</td>
                <td style="border:1px solid #ddd; padding:6px; text-align:center; font-weight:bold; color:{diff_color};">{diff:.2f}</td>
            </tr>
            """

        html_content = f"""
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="utf-8">
            <title>كشف جرد المخزون الفعلي</title>
            <style>
                body {{ font-family: 'Segoe UI', Tahoma, sans-serif; padding: 20px; direction: rtl; }}
                h2, h4 {{ text-align: center; margin: 4px 0; }}
                table {{ width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }}
                th {{ background: #2c3e50; color: white; border: 1px solid #444; padding: 8px; }}
                tr:nth-child(even) {{ background: #f9f9f9; }}
                @media print {{ button {{ display: none; }} }}
            </style>
        </head>
        <body>
            <h2>سوبر ماركت المنزل السوري 🇸🇾</h2>
            <h4>كشف الجرد الفعلي للمخزون - التاريخ: {now_str}</h4>
            <p style="text-align:center; font-size:12px;">إجمالي الأصناف: {len(self.all_items)} | إجمالي قيمة المخزون بالتكلفة: {total_cost:,.2f} ج.م</p>
            <table>
                <thead>
                    <tr>
                        <th>كود</th>
                        <th>اسم الصنف</th>
                        <th>القسم</th>
                        <th>التكلفة</th>
                        <th>البيع</th>
                        <th>رصيد السيستم</th>
                        <th>الجرد الفعلي</th>
                        <th>الفارق</th>
                    </tr>
                </thead>
                <tbody>
                    {rows_html}
                </tbody>
            </table>
            <script>window.print();</script>
        </body>
        </html>
        """

        tmp_file = os.path.join(os.path.dirname(__file__), "..", "inventory_report_print.html")
        with open(tmp_file, "w", encoding="utf-8") as f:
            f.write(html_content)

        webbrowser.open("file://" + os.path.abspath(tmp_file))
