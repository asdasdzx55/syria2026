import customtkinter as ctk
import tkinter.messagebox as messagebox
from tkinter import ttk, filedialog
import random
import sqlite3
import csv
from database import generate_next_local_code

class ProductsPage(ctk.CTkFrame):
    def __init__(self, parent, db_conn, app):
        super().__init__(parent, fg_color="transparent")
        self.db = db_conn
        self.cursor = self.db.cursor()
        self.app = app
        self.current_edit_id = None
        self.current_barcodes_list = [] 
        self.all_product_ids = [] 
        
        self.setup_ui()

    def setup_shortcuts(self):
        top = self.winfo_toplevel()
        top.bind("<F3>", self._shortcut_search)

    def _shortcut_search(self, event):
        if self.winfo_ismapped(): 
            self.open_fast_search_popup()


    def setup_ui(self):
        ctk.CTkLabel(self, text="إدارة المنتجات، الباركود المحلي 5 أرقام، والباركود الدولي", font=ctk.CTkFont(size=24, weight="bold")).pack(pady=10)
        
        # ==================================
        # 1. شريط التنقل بين المنتجات (Navigation)
        # ==================================
        nav_frame = ctk.CTkFrame(self, fg_color="#1f538d", corner_radius=10)
        nav_frame.pack(fill="x", pady=5, padx=20)
        
        ctk.CTkLabel(nav_frame, text="التنقل بين المنتجات:", text_color="white", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=15, pady=5)
        ctk.CTkButton(nav_frame, text="⏭️ الأخير (الأحدث)", fg_color="#2980b9", width=120, command=self.nav_last).pack(side="right", padx=5, pady=5)
        ctk.CTkButton(nav_frame, text="▶️ التالي", fg_color="#2980b9", width=80, command=self.nav_next).pack(side="right", padx=5, pady=5)
        ctk.CTkButton(nav_frame, text="◀️ السابق", fg_color="#2980b9", width=80, command=self.nav_prev).pack(side="right", padx=5, pady=5)
        ctk.CTkButton(nav_frame, text="⏮️ الأول (الأقدم)", fg_color="#2980b9", width=120, command=self.nav_first).pack(side="right", padx=5, pady=5)

        form_frame = ctk.CTkFrame(self)
        form_frame.pack(fill="x", pady=10, padx=20)
        
        # ==================================
        # 2. بيانات المنتج الأساسية والباركود المحلي 5 أرقام
        # ==================================
        row1 = ctk.CTkFrame(form_frame, fg_color="transparent")
        row1.pack(fill="x", pady=5, padx=10)
        
        ctk.CTkLabel(row1, text="اسم المنتج:", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=5)
        self.ent_p_name = ctk.CTkEntry(row1, width=180, font=("Arial", 14, "bold"))
        self.ent_p_name.pack(side="right", padx=5)

        ctk.CTkLabel(row1, text="كود محلي (5 أرقام):", font=ctk.CTkFont(weight="bold")).pack(side="right", padx=(10, 2))
        self.ent_p_local_code = ctk.CTkEntry(row1, width=90, justify="center", font=("Arial", 14, "bold"))
        self.ent_p_local_code.pack(side="right", padx=2)

        def generate_local_btn():
            code = generate_next_local_code(self.cursor)
            self.ent_p_local_code.delete(0, 'end')
            self.ent_p_local_code.insert(0, code)

        ctk.CTkButton(row1, text="🎲 5 أرقام", width=70, fg_color="#8e44ad", hover_color="#732d91", command=generate_local_btn).pack(side="right", padx=4)
        
        ctk.CTkLabel(row1, text="سعر البيع:").pack(side="right", padx=5)
        self.ent_p_price = ctk.CTkEntry(row1, width=80, justify="center")
        self.ent_p_price.pack(side="right", padx=5)
        
        ctk.CTkLabel(row1, text="التكلفة:").pack(side="right", padx=5)
        self.ent_p_cost = ctk.CTkEntry(row1, width=80, justify="center")
        self.ent_p_cost.pack(side="right", padx=5)
        
        ctk.CTkLabel(row1, text="الرصيد:").pack(side="right", padx=5)
        self.ent_p_stock = ctk.CTkEntry(row1, width=80, justify="center")
        self.ent_p_stock.pack(side="right", padx=5)

        # ==================================
        # 3. نظام الباركود الدولي والفرعي
        # ==================================
        bc_frame = ctk.CTkFrame(form_frame, fg_color="#2b2b2b", corner_radius=10)
        bc_frame.pack(fill="x", pady=10, padx=10)
        
        row_bc_input = ctk.CTkFrame(bc_frame, fg_color="transparent")
        row_bc_input.pack(fill="x", pady=5, padx=10)
        
        ctk.CTkLabel(row_bc_input, text="الباركود الدولي:", font=ctk.CTkFont(weight="bold", size=14)).pack(side="right", padx=5)
        self.ent_p_barcode = ctk.CTkEntry(row_bc_input, placeholder_text="مرر الباركود الدولي هنا...", width=240, font=("Arial", 16))
        self.ent_p_barcode.pack(side="right", padx=5)
        
        ctk.CTkButton(row_bc_input, text="➕ إضافة للقائمة (Enter)", fg_color="#27ae60", hover_color="#1e8449", command=self.add_barcode_to_list_or_save).pack(side="right", padx=5)
        ctk.CTkButton(row_bc_input, text="🎲 توليد فرعي", fg_color="#8e44ad", hover_color="#732d91", command=self.generate_internal_barcode).pack(side="right", padx=5)
        
        ctk.CTkLabel(row_bc_input, text="* اضغط Enter في خانة الباركود فارغة لحفظ المنتج سريعاً", text_color="#f39c12", font=ctk.CTkFont(size=12)).pack(side="left", padx=10)

        self.bc_list_frame = ctk.CTkScrollableFrame(bc_frame, height=80, fg_color="#1a1a1a")
        self.bc_list_frame.pack(fill="x", pady=5, padx=10)

        # ==================================
        # 4. أزرار التحكم والحالة
        # ==================================
        row_btns = ctk.CTkFrame(form_frame, fg_color="transparent")
        row_btns.pack(fill="x", pady=5)
        
        self.btn_search = ctk.CTkButton(row_btns, text="🔍 بحث عن منتج وتعديله (F3)", fg_color="#f39c12", hover_color="#d68910", width=150, command=self.open_fast_search_popup)
        self.btn_search.pack(side="right", padx=15)

        self.btn_add_prod = ctk.CTkButton(row_btns, text="💾 حفظ المنتج النهائي", fg_color="#27ae60", hover_color="#1e8449", width=120, command=self.prod_add)
        self.btn_add_prod.pack(side="left", padx=5)
        
        self.btn_edit_prod = ctk.CTkButton(row_btns, text="🔄 تعديل وحفظ", fg_color="#2980b9", width=100, state="disabled", command=self.prod_update)
        self.btn_edit_prod.pack(side="left", padx=5)
        
        self.btn_add_stock = ctk.CTkButton(row_btns, text="➕ تزويد رصيد", fg_color="#8e44ad", hover_color="#732d91", width=100, state="disabled", command=self.quick_add_stock)
        self.btn_add_stock.pack(side="left", padx=5)
        
        self.btn_delete_prod = ctk.CTkButton(row_btns, text="❌ حذف", fg_color="#c0392b", hover_color="#922b21", width=80, state="disabled", command=self.prod_delete)
        self.btn_delete_prod.pack(side="left", padx=5)
        
        self.btn_clear_form = ctk.CTkButton(row_btns, text="إفراغ الشاشة", fg_color="gray", width=100, command=self.prod_clear_form)
        self.btn_clear_form.pack(side="left", padx=5)

        self.btn_export_scale = ctk.CTkButton(row_btns, text="⚖️ تصدير للميزان", fg_color="#2980b9", width=120, command=self.export_scale_data)
        self.btn_export_scale.pack(side="left", padx=5)

        self.status_label = ctk.CTkLabel(form_frame, text="", font=ctk.CTkFont(size=16, weight="bold"))
        self.status_label.pack(pady=5)
        
        self.ent_p_name.bind("<Return>", lambda e: self.ent_p_local_code.focus())
        self.ent_p_local_code.bind("<Return>", lambda e: self.ent_p_price.focus())
        self.ent_p_price.bind("<Return>", lambda e: self.ent_p_cost.focus())
        self.ent_p_cost.bind("<Return>", lambda e: self.ent_p_stock.focus())
        self.ent_p_stock.bind("<Return>", lambda e: self.ent_p_barcode.focus())
        self.ent_p_barcode.bind("<Return>", self.add_barcode_to_list_or_save)

    def on_show(self):
        self.setup_shortcuts()
        self.refresh_nav_ids()
        self.prod_clear_form()
        self.ent_p_name.focus() 

    # ==================================
    # دالة البحث المنبثق (Popup Search)
    # ==================================
    def open_fast_search_popup(self):
        win = ctk.CTkToplevel(self)
        win.title("بحث وتعديل المنتجات (F3)")
        win.geometry("750x500")
        win.attributes("-topmost", True)

        search_var = ctk.StringVar()
        ent_search = ctk.CTkEntry(win, textvariable=search_var, placeholder_text="اكتب اسم المنتج، الباركود المحلي (5 أرقام)، أو الباركود الدولي...", font=("Arial", 16))
        ent_search.pack(fill="x", padx=10, pady=10)

        tree_frame = ctk.CTkFrame(win)
        tree_frame.pack(expand=True, fill="both", padx=10, pady=5)
        
        tree_scroll = ttk.Scrollbar(tree_frame)
        tree_scroll.pack(side="left", fill="y")
        
        tree = ttk.Treeview(tree_frame, columns=('id', 'local_code', 'barcode', 'name', 'price', 'cost', 'stock'), show='headings', yscrollcommand=tree_scroll.set)
        tree_scroll.config(command=tree.yview)
        
        tree.heading('id', text='ID')
        tree.heading('local_code', text='كود محلي (5 أرقام)')
        tree.heading('barcode', text='الباركود الدولي')
        tree.heading('name', text='اسم المنتج')
        tree.heading('price', text='سعر البيع')
        tree.heading('cost', text='التكلفة')
        tree.heading('stock', text='المخزن')
        
        tree.column('id', width=40, stretch=False, anchor='center')
        tree.column('local_code', width=110, anchor='center')
        tree.column('barcode', width=130, anchor='center')
        tree.column('name', width=180, anchor='center')
        tree.column('price', width=75, anchor='center')
        tree.column('cost', width=75, anchor='center')
        tree.column('stock', width=75, anchor='center')
        tree.pack(expand=True, fill="both")

        def do_search(*args):
            term = search_var.get().lower()
            for item in tree.get_children(): tree.delete(item)
            query = "SELECT id, local_code, barcode, name, price, cost, stock FROM products WHERE name LIKE ? OR local_code LIKE ? OR barcode LIKE ? OR barcode2 LIKE ? OR barcode3 LIKE ? OR ',' || COALESCE(all_barcodes, '') || ',' LIKE ?"
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
            self.load_product_by_id(p_id)
            win.destroy()

        tree.bind("<Double-1>", select_item)
        tree.bind("<Return>", select_item)

        def move_to_tree(event):
            if tree.get_children():
                tree.focus(tree.get_children()[0])
                tree.selection_set(tree.get_children()[0])
                
        ent_search.bind("<Down>", move_to_tree)
        ent_search.focus()
        
        ctk.CTkLabel(win, text="* للتعديل: اضغط مرتين (أو Enter) على المنتج لسحبه إلى الشاشة الرئيسية.", text_color="gray").pack(pady=5)

    # ==================================
    # دوال الباركود اللانهائي
    # ==================================
    def add_barcode_to_list_or_save(self, event=None):
        bc = self.ent_p_barcode.get().strip()
        if bc:
            if bc not in self.current_barcodes_list:
                self.current_barcodes_list.append(bc)
                self.render_barcodes_list()
            self.ent_p_barcode.delete(0, 'end')
        else:
            if self.current_edit_id:
                self.prod_update()
            else:
                self.prod_add()

    def render_barcodes_list(self):
        for widget in self.bc_list_frame.winfo_children(): widget.destroy()
        for i, bc in enumerate(self.current_barcodes_list):
            tag = ctk.CTkFrame(self.bc_list_frame, corner_radius=15, fg_color="#34495e")
            tag.pack(fill="x", pady=2, padx=5) 
            
            lbl_text = f"⭐ {bc}" if i == 0 else f"🏷️ {bc}"
            ctk.CTkLabel(tag, text=lbl_text, font=("Arial", 14, "bold")).pack(side="right", padx=10, pady=2)
            
            btn = ctk.CTkButton(tag, text="✖ مسح", width=40, height=24, fg_color="#c0392b", hover_color="#922b21",
                                command=lambda b=bc: self.remove_barcode(b))
            btn.pack(side="left", padx=10, pady=2)

    def remove_barcode(self, bc):
        if bc in self.current_barcodes_list:
            self.current_barcodes_list.remove(bc)
            self.render_barcodes_list()

    def generate_internal_barcode(self):
        internal_code = f"99{random.randint(100000, 999999)}"
        if internal_code not in self.current_barcodes_list:
            self.current_barcodes_list.append(internal_code)
            self.render_barcodes_list()

    # ==================================
    # دوال التنقل (Navigation)
    # ==================================
    def refresh_nav_ids(self):
        self.cursor.execute("SELECT id FROM products ORDER BY id ASC")
        self.all_product_ids = [r[0] for r in self.cursor.fetchall()]

    def load_product_by_id(self, p_id):
        self.cursor.execute("SELECT id, barcode, barcode2, barcode3, name, price, cost, stock, all_barcodes, local_code FROM products WHERE id=?", (p_id,))
        row = self.cursor.fetchone()
        if row:
            self.prod_clear_form()
            self.current_edit_id = row[0]
            
            self.ent_p_name.insert(0, str(row[4] or ""))
            self.ent_p_local_code.delete(0, 'end')
            self.ent_p_local_code.insert(0, str(row[9] or ""))
            self.ent_p_price.insert(0, str(row[5]))
            self.ent_p_cost.insert(0, str(row[6]))
            self.ent_p_stock.insert(0, str(row[7]))
            
            all_bcs = row[8]
            self.current_barcodes_list = []
            if all_bcs:
                self.current_barcodes_list = [b.strip() for b in all_bcs.split(",") if b.strip()]
            else:
                for b in [row[9], row[1], row[2], row[3]]:
                    if b and b != 'None' and b not in self.current_barcodes_list:
                        self.current_barcodes_list.append(b)
                        
            self.render_barcodes_list()
            self.btn_add_prod.configure(state="disabled")
            self.btn_edit_prod.configure(state="normal")
            self.btn_add_stock.configure(state="normal") 
            self.btn_delete_prod.configure(state="normal") 
            self.show_status(f"تم عرض بيانات: {row[4]} (كود محلي: {row[9]})", "#3498db")

    def nav_first(self):
        if self.all_product_ids: self.load_product_by_id(self.all_product_ids[0])

    def nav_last(self):
        if self.all_product_ids: self.load_product_by_id(self.all_product_ids[-1])

    def nav_next(self):
        if not self.current_edit_id or not self.all_product_ids: return
        try:
            idx = self.all_product_ids.index(self.current_edit_id)
            if idx < len(self.all_product_ids) - 1: self.load_product_by_id(self.all_product_ids[idx+1])
        except ValueError: pass

    def nav_prev(self):
        if not self.current_edit_id or not self.all_product_ids: return
        try:
            idx = self.all_product_ids.index(self.current_edit_id)
            if idx > 0: self.load_product_by_id(self.all_product_ids[idx-1])
        except ValueError: pass

    # ==================================
    # دوال الحفظ والعمليات (محدثة)
    # ==================================
    def show_status(self, msg, color="green"):
        self.status_label.configure(text=msg, text_color=color)
        self.after(3000, lambda: self.status_label.configure(text="")) 

    def prod_add(self):
        name = self.ent_p_name.get().strip()
        if not name:
            self.show_status("❌ يجب كتابة اسم المنتج!", "#e74c3c")
            self.ent_p_name.focus()
            return
            
        loc_code = self.ent_p_local_code.get().strip()
        if not loc_code:
            loc_code = generate_next_local_code(self.cursor)
            self.ent_p_local_code.insert(0, loc_code)

        # التقاط أي باركود مكتوب في الخانة ولم تضغط Enter له
        bc_text = self.ent_p_barcode.get().strip()
        if bc_text and bc_text not in self.current_barcodes_list:
            self.current_barcodes_list.append(bc_text)
            self.ent_p_barcode.delete(0, 'end')
            self.render_barcodes_list()

        if loc_code not in self.current_barcodes_list:
            self.current_barcodes_list.insert(0, loc_code)

        try:
            p_text = self.ent_p_price.get().strip()
            price = float(p_text) if p_text else 0.0
            
            c_text = self.ent_p_cost.get().strip()
            cost = float(c_text) if c_text else 0.0
            
            s_text = self.ent_p_stock.get().strip()
            stock = float(s_text) if s_text else 0.0
            
            all_bcs_str = ",".join([b for b in self.current_barcodes_list if b])
            b_others = [b for b in self.current_barcodes_list if b != loc_code]
            b1 = b_others[0] if len(b_others) > 0 else None
            b2 = b_others[1] if len(b_others) > 1 else None
            b3 = b_others[2] if len(b_others) > 2 else None
            
            self.cursor.execute("INSERT INTO products (barcode, local_code, barcode2, barcode3, name, price, cost, stock, all_barcodes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                                (b1, loc_code, b2, b3, name, price, cost, stock, all_bcs_str))
            self.db.commit()
            
            self.refresh_nav_ids()
            self.prod_clear_form()
            
            self.show_status(f"✅ تم إضافة ({name}) بكود محلي [{loc_code}] بنجاح!", "#2ecc71")
            self.ent_p_name.focus() 
            
        except sqlite3.IntegrityError:
            self.show_status("❌ أحد الباركودات مسجل بالفعل لمنتج آخر!", "#e74c3c")
        except ValueError: 
            self.show_status("❌ تأكد من إدخال الأرقام بشكل صحيح!", "#e74c3c")
            self.ent_p_price.focus()
        except Exception as e:
            messagebox.showerror("خطأ غير متوقع", f"حدث خطأ أثناء الحفظ:\n{e}")

    def prod_update(self):
        if not self.current_edit_id: return
        name = self.ent_p_name.get().strip()

        loc_code = self.ent_p_local_code.get().strip()
        if not loc_code:
            loc_code = generate_next_local_code(self.cursor)
            self.ent_p_local_code.insert(0, loc_code)
        
        bc_text = self.ent_p_barcode.get().strip()
        if bc_text and bc_text not in self.current_barcodes_list:
            self.current_barcodes_list.append(bc_text)
            self.ent_p_barcode.delete(0, 'end')
            self.render_barcodes_list()

        if loc_code not in self.current_barcodes_list:
            self.current_barcodes_list.insert(0, loc_code)
            
        try:
            p_text = self.ent_p_price.get().strip()
            price = float(p_text) if p_text else 0.0
            
            c_text = self.ent_p_cost.get().strip()
            cost = float(c_text) if c_text else 0.0
            
            s_text = self.ent_p_stock.get().strip()
            stock = float(s_text) if s_text else 0.0
            
            all_bcs_str = ",".join([b for b in self.current_barcodes_list if b])
            b_others = [b for b in self.current_barcodes_list if b != loc_code]
            b1 = b_others[0] if len(b_others) > 0 else None
            b2 = b_others[1] if len(b_others) > 1 else None
            b3 = b_others[2] if len(b_others) > 2 else None
            
            self.cursor.execute("UPDATE products SET barcode=?, local_code=?, barcode2=?, barcode3=?, name=?, price=?, cost=?, stock=?, all_barcodes=? WHERE id=?", 
                                (b1, loc_code, b2, b3, name, price, cost, stock, all_bcs_str, self.current_edit_id))

            self.db.commit()
            self.prod_clear_form()
            self.show_status("✅ تم التعديل بنجاح!", "#2ecc71")
        except sqlite3.IntegrityError:
            messagebox.showerror("خطأ", "أحد الباركودات مستخدم بالفعل لمنتج آخر!")
        except Exception as e: 
            messagebox.showerror("خطأ", f"حدث خطأ أثناء التعديل:\n{e}")

    def prod_delete(self):
        if not self.current_edit_id: return
        name = self.ent_p_name.get().strip()
        confirm = messagebox.askyesno("تحذير خطير", f"هل أنت متأكد من حذف المنتج ({name}) نهائياً؟")
        if confirm:
            try:
                self.cursor.execute("DELETE FROM products WHERE id=?", (self.current_edit_id,))
                self.db.commit()
                self.refresh_nav_ids()
                self.prod_clear_form()
                self.show_status(f"🗑️ تم حذف ({name})!", "#e74c3c")
            except Exception as e:
                messagebox.showerror("خطأ", f"حدث خطأ أثناء الحذف:\n{e}")

    def prod_clear_form(self):
        for ent in [self.ent_p_name, self.ent_p_local_code, self.ent_p_barcode, self.ent_p_price, self.ent_p_cost, self.ent_p_stock]:
            ent.delete(0, 'end')
        
        self.ent_p_local_code.insert(0, generate_next_local_code(self.cursor))
        self.current_barcodes_list = []
        self.render_barcodes_list()
        
        self.btn_add_prod.configure(state="normal")
        self.btn_edit_prod.configure(state="disabled")
        self.btn_add_stock.configure(state="disabled") 
        self.btn_delete_prod.configure(state="disabled") 
        self.current_edit_id = None
        
        self.ent_p_name.focus()


    def quick_add_stock(self):
        if not self.current_edit_id: return
        name = self.ent_p_name.get().strip()
        dialog = ctk.CTkInputDialog(text=f"المنتج: {name}\nأدخل الكمية المراد إضافتها للمخزن الآن:", title="تزويد رصيد سريع")
        val = dialog.get_input()
        if val is None: return 
        
        try:
            qty = float(val)
            if qty == 0: return
            self.cursor.execute("UPDATE products SET stock = stock + ? WHERE id=?", (qty, self.current_edit_id))
            self.db.commit()
            
            current_stock = float(self.ent_p_stock.get() or 0)
            self.ent_p_stock.delete(0, 'end')
            self.ent_p_stock.insert(0, str(current_stock + qty))
            
            self.show_status(f"✅ تم إضافة ({qty:g}) لرصيد {name}!", "#2ecc71")
        except ValueError:
            messagebox.showerror("خطأ", "برجاء إدخال أرقام صحيحة!")

    def export_scale_data(self):
        self.cursor.execute("SELECT barcode, name, price FROM products WHERE LENGTH(barcode) <= 5 AND barcode GLOB '*[0-9]*'")
        scale_products = self.cursor.fetchall()
        
        if not scale_products:
            messagebox.showwarning("تنبيه", "لا توجد منتجات ميزان (باركود أقل من 5 أرقام).")
            return
            
        file_path = filedialog.asksaveasfilename(defaultextension=".csv", initialfile="Scale_Products.csv", title="حفظ ملف الميزان", filetypes=[("CSV Files", "*.csv")])
        if not file_path: return
        
        try:
            with open(file_path, mode='w', newline='', encoding='utf-8-sig') as file:
                writer = csv.writer(file)
                writer.writerow(['PLU_Code', 'Item_Name', 'Unit_Price'])
                for prod in scale_products:
                    writer.writerow([str(prod[0]).zfill(5), prod[1], prod[2]])
            messagebox.showinfo("تم التصدير بنجاح", f"تم تجهيز ملف الميزان بنجاح!\nيحتوي على {len(scale_products)} منتج موزون.")
        except Exception as e:
            messagebox.showerror("خطأ", str(e))