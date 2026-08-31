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
        # 2.1 التصنيف الأساسي والفرعي
        # ==================================
        row_cat = ctk.CTkFrame(form_frame, fg_color="#1a252f", corner_radius=8)
        row_cat.pack(fill="x", pady=6, padx=10)

        ctk.CTkLabel(row_cat, text="🏷️ التصنيف الأساسي:", font=ctk.CTkFont(weight="bold"), text_color="#2ecc71").pack(side="right", padx=(10, 4), pady=6)
        self.combo_main_cat = ctk.CTkComboBox(row_cat, width=170, command=self._on_main_category_selected)
        self.combo_main_cat.pack(side="right", padx=4, pady=6)

        ctk.CTkLabel(row_cat, text="📂 التصنيف الفرعي:", font=ctk.CTkFont(weight="bold"), text_color="#3498db").pack(side="right", padx=(10, 4), pady=6)
        self.combo_sub_cat = ctk.CTkComboBox(row_cat, width=170)
        self.combo_sub_cat.pack(side="right", padx=4, pady=6)

        ctk.CTkButton(row_cat, text="➕ إضافة / إدارة التصنيفات", width=160, fg_color="#16a085", hover_color="#117864", font=ctk.CTkFont(weight="bold"), command=self.open_categories_manager).pack(side="left", padx=10, pady=6)

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
        self.load_categories_to_combos()
        self.refresh_nav_ids()
        self.prod_clear_form()
        self.ent_p_name.focus() 

    # ==================================
    # دوال إدارة التصنيفات الأساسية والفرعية
    # ==================================
    def load_categories_to_combos(self):
        try:
            self.cursor.execute("SELECT name FROM categories ORDER BY id ASC")
            main_cats = [r[0] for r in self.cursor.fetchall() if r[0]]
            if not main_cats:
                main_cats = ["عام"]
            
            self.combo_main_cat.configure(values=main_cats)
            curr = self.combo_main_cat.get()
            if not curr or curr not in main_cats:
                self.combo_main_cat.set(main_cats[0])
            
            self._on_main_category_selected()
        except Exception as e:
            print(f"Error loading categories: {e}")

    def _on_main_category_selected(self, choice=None):
        main_cat = self.combo_main_cat.get().strip()
        if not main_cat:
            self.combo_sub_cat.configure(values=["عام"])
            self.combo_sub_cat.set("عام")
            return
            
        try:
            self.cursor.execute("""
                SELECT sc.name 
                FROM sub_categories sc 
                JOIN categories c ON sc.main_category_id = c.id 
                WHERE c.name = ? 
                ORDER BY sc.id ASC
            """, (main_cat,))
            sub_cats = [r[0] for r in self.cursor.fetchall() if r[0]]
            if not sub_cats:
                sub_cats = ["عام"]
            
            self.combo_sub_cat.configure(values=sub_cats)
            self.combo_sub_cat.set(sub_cats[0])
        except Exception as e:
            print(f"Error loading sub-categories: {e}")

    def open_categories_manager(self):
        win = ctk.CTkToplevel(self)
        win.title("إدارة التصنيفات الأساسية والفرعية")
        win.geometry("680x560")
        win.attributes("-topmost", True)
        win.resizable(False, False)

        header = ctk.CTkFrame(win, fg_color="#1f538d", corner_radius=10)
        header.pack(fill="x", padx=15, pady=10)
        ctk.CTkLabel(header, text="🏷️ إدارة وتعديل التصنيفات الأساسية والفرعية", font=ctk.CTkFont(size=18, weight="bold"), text_color="white").pack(pady=8)

        # بطاقة إضافة تصنيف أساسي
        card_main = ctk.CTkFrame(win, fg_color="#2c3e50", corner_radius=8)
        card_main.pack(fill="x", padx=15, pady=5)
        
        ctk.CTkLabel(card_main, text="1. إضافة تصنيف أساسي جديد:", font=ctk.CTkFont(weight="bold"), text_color="#2ecc71").pack(side="right", padx=10, pady=8)
        ent_new_main = ctk.CTkEntry(card_main, placeholder_text="اسم التصنيف الأساسي...", width=200, font=("Arial", 13))
        ent_new_main.pack(side="right", padx=5, pady=8)
        
        def add_main_cat():
            name = ent_new_main.get().strip()
            if not name:
                messagebox.showwarning("تنبيه", "برجاء كتابة اسم التصنيف الأساسي!", parent=win)
                return
            try:
                self.cursor.execute("INSERT INTO categories (name) VALUES (?)", (name,))
                cat_id = self.cursor.lastrowid
                self.cursor.execute("INSERT OR IGNORE INTO sub_categories (name, main_category_id) VALUES ('عام', ?)", (cat_id,))
                self.db.commit()
                ent_new_main.delete(0, 'end')
                refresh_mgr_data()
                self.load_categories_to_combos()
                self.combo_main_cat.set(name)
                self._on_main_category_selected()
                messagebox.showinfo("نجاح", f"تمت إضافة التصنيف الأساسي ({name}) بنجاح!", parent=win)
            except sqlite3.IntegrityError:
                messagebox.showerror("خطأ", "هذا التصنيف الأساسي مسجل بالفعل!", parent=win)
            except Exception as ex:
                messagebox.showerror("خطأ", str(ex), parent=win)

        ctk.CTkButton(card_main, text="➕ إضافة أساسي", width=110, fg_color="#27ae60", hover_color="#1e8449", command=add_main_cat).pack(side="left", padx=10, pady=8)

        # بطاقة إضافة تصنيف فرعي
        card_sub = ctk.CTkFrame(win, fg_color="#2c3e50", corner_radius=8)
        card_sub.pack(fill="x", padx=15, pady=5)

        ctk.CTkLabel(card_sub, text="2. إضافة تصنيف فرعي:", font=ctk.CTkFont(weight="bold"), text_color="#3498db").pack(side="right", padx=10, pady=8)
        combo_mgr_main = ctk.CTkComboBox(card_sub, width=170)
        combo_mgr_main.pack(side="right", padx=5, pady=8)
        
        ent_new_sub = ctk.CTkEntry(card_sub, placeholder_text="اسم التصنيف الفرعي...", width=160, font=("Arial", 13))
        ent_new_sub.pack(side="right", padx=5, pady=8)

        def add_sub_cat():
            main_name = combo_mgr_main.get().strip()
            sub_name = ent_new_sub.get().strip()
            if not main_name or not sub_name:
                messagebox.showwarning("تنبيه", "برجاء اختيار التصنيف الأساسي وكتابة التصنيف الفرعي!", parent=win)
                return
            try:
                self.cursor.execute("SELECT id FROM categories WHERE name=?", (main_name,))
                row = self.cursor.fetchone()
                if not row:
                    messagebox.showerror("خطأ", "التصنيف الأساسي غير موجود!", parent=win)
                    return
                main_id = row[0]
                self.cursor.execute("INSERT INTO sub_categories (name, main_category_id) VALUES (?, ?)", (sub_name, main_id))
                self.db.commit()
                ent_new_sub.delete(0, 'end')
                refresh_mgr_data()
                self.load_categories_to_combos()
                self.combo_main_cat.set(main_name)
                self._on_main_category_selected()
                self.combo_sub_cat.set(sub_name)
                messagebox.showinfo("نجاح", f"تمت إضافة التصنيف الفرعي ({sub_name}) تحت ({main_name}) بنجاح!", parent=win)
            except sqlite3.IntegrityError:
                messagebox.showerror("خطأ", "هذا التصنيف الفرعي مسجل بالفعل تحت هذا القسم!", parent=win)
            except Exception as ex:
                messagebox.showerror("خطأ", str(ex), parent=win)

        ctk.CTkButton(card_sub, text="➕ إضافة فرعي", width=110, fg_color="#2980b9", hover_color="#1f618d", command=add_sub_cat).pack(side="left", padx=10, pady=8)

        # جدول عرض التصنيفات
        tree_frame = ctk.CTkFrame(win)
        tree_frame.pack(fill="both", expand=True, padx=15, pady=8)

        tree = ttk.Treeview(tree_frame, columns=('type', 'main_name', 'sub_name', 'id'), show='headings', height=8)
        tree.heading('type', text='النوع')
        tree.heading('main_name', text='التصنيف الأساسي')
        tree.heading('sub_name', text='التصنيف الفرعي')
        tree.heading('id', text='ID')

        tree.column('type', width=100, anchor='center')
        tree.column('main_name', width=200, anchor='center')
        tree.column('sub_name', width=200, anchor='center')
        tree.column('id', width=60, anchor='center')
        tree.pack(side="left", fill="both", expand=True)

        scroll = ttk.Scrollbar(tree_frame, orient="vertical", command=tree.yview)
        tree.configure(yscrollcommand=scroll.set)
        scroll.pack(side="right", fill="y")

        def refresh_mgr_data():
            for item in tree.get_children(): tree.delete(item)
            self.cursor.execute("SELECT name FROM categories ORDER BY id ASC")
            all_mains = [r[0] for r in self.cursor.fetchall() if r[0]]
            combo_mgr_main.configure(values=all_mains if all_mains else ["عام"])
            if all_mains: combo_mgr_main.set(all_mains[0])

            self.cursor.execute("""
                SELECT c.id, c.name, sc.id, sc.name 
                FROM categories c 
                LEFT JOIN sub_categories sc ON sc.main_category_id = c.id 
                ORDER BY c.id ASC, sc.id ASC
            """)
            for c_id, c_name, sc_id, sc_name in self.cursor.fetchall():
                if sc_id:
                    tree.insert("", "end", values=("📂 فرعي", c_name, sc_name or "-", f"sub_{sc_id}"))
                else:
                    tree.insert("", "end", values=("🏷️ أساسي", c_name, "-", f"main_{c_id}"))

        def delete_selected_cat():
            selected = tree.selection()
            if not selected:
                messagebox.showwarning("تنبيه", "اختر تصنيفاً من الجدول لحذفه!", parent=win)
                return
            item = tree.item(selected[0])['values']
            item_type, main_name, sub_name, tag_id = item[0], item[1], item[2], str(item[3])
            
            if "sub_" in tag_id:
                sub_id = int(tag_id.replace("sub_", ""))
                if messagebox.askyesno("تأكيد", f"حذف التصنيف الفرعي ({sub_name})؟", parent=win):
                    self.cursor.execute("DELETE FROM sub_categories WHERE id=?", (sub_id,))
                    self.db.commit()
                    refresh_mgr_data()
                    self.load_categories_to_combos()
            elif "main_" in tag_id:
                main_id = int(tag_id.replace("main_", ""))
                if messagebox.askyesno("تحذير", f"حذف التصنيف الأساسي ({main_name}) وجميع تصنيفاته الفرعية؟", parent=win):
                    self.cursor.execute("DELETE FROM sub_categories WHERE main_category_id=?", (main_id,))
                    self.cursor.execute("DELETE FROM categories WHERE id=?", (main_id,))
                    self.db.commit()
                    refresh_mgr_data()
                    self.load_categories_to_combos()

        btn_row = ctk.CTkFrame(win, fg_color="transparent")
        btn_row.pack(fill="x", padx=15, pady=(0, 10))
        ctk.CTkButton(btn_row, text="❌ حذف التصنيف المحدد", fg_color="#c0392b", hover_color="#922b21", command=delete_selected_cat).pack(side="right", padx=5)
        ctk.CTkButton(btn_row, text="إغلاق", fg_color="#7f8c8d", command=win.destroy).pack(side="left", padx=5)

        refresh_mgr_data()

    # ==================================
    # دالة البحث المنبثق (Popup Search)
    # ==================================
    def open_fast_search_popup(self):
        win = ctk.CTkToplevel(self)
        win.title("بحث وتعديل المنتجات (F3)")
        win.geometry("820x520")
        win.attributes("-topmost", True)

        search_var = ctk.StringVar()
        ent_search = ctk.CTkEntry(win, textvariable=search_var, placeholder_text="اكتب اسم المنتج، التصنيف، الباركود المحلي (5 أرقام)، أو الباركود الدولي...", font=("Arial", 16))
        ent_search.pack(fill="x", padx=10, pady=10)

        tree_frame = ctk.CTkFrame(win)
        tree_frame.pack(expand=True, fill="both", padx=10, pady=5)
        
        tree_scroll = ttk.Scrollbar(tree_frame)
        tree_scroll.pack(side="left", fill="y")
        
        tree = ttk.Treeview(tree_frame, columns=('id', 'local_code', 'name', 'main_cat', 'sub_cat', 'price', 'cost', 'stock'), show='headings', yscrollcommand=tree_scroll.set)
        tree_scroll.config(command=tree.yview)
        
        tree.heading('id', text='ID')
        tree.heading('local_code', text='كود محلي')
        tree.heading('name', text='اسم المنتج')
        tree.heading('main_cat', text='التصنيف الأساسي')
        tree.heading('sub_cat', text='التصنيف الفرعي')
        tree.heading('price', text='سعر البيع')
        tree.heading('cost', text='التكلفة')
        tree.heading('stock', text='المخزن')
        
        tree.column('id', width=40, stretch=False, anchor='center')
        tree.column('local_code', width=80, anchor='center')
        tree.column('name', width=180, anchor='center')
        tree.column('main_cat', width=120, anchor='center')
        tree.column('sub_cat', width=120, anchor='center')
        tree.column('price', width=75, anchor='center')
        tree.column('cost', width=75, anchor='center')
        tree.column('stock', width=75, anchor='center')
        tree.pack(expand=True, fill="both")

        def do_search(*args):
            term = search_var.get().lower()
            for item in tree.get_children(): tree.delete(item)
            query = """
                SELECT id, local_code, name, COALESCE(main_category, category, 'عام'), COALESCE(sub_category, 'عام'), price, cost, stock 
                FROM products 
                WHERE name LIKE ? OR local_code LIKE ? OR barcode LIKE ? OR barcode2 LIKE ? OR barcode3 LIKE ? 
                OR main_category LIKE ? OR sub_category LIKE ? OR category LIKE ? OR ',' || COALESCE(all_barcodes, '') || ',' LIKE ?
            """
            s = f"%{term}%"
            bc_s = f"%,{term},%"
            self.cursor.execute(query, (s, s, s, s, s, s, s, s, bc_s))
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
        self.cursor.execute("SELECT id, barcode, barcode2, barcode3, name, price, cost, stock, all_barcodes, local_code, main_category, sub_category, category FROM products WHERE id=?", (p_id,))
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
            
            main_c = row[10] or row[12] or "عام"
            sub_c = row[11] or "عام"
            
            self.combo_main_cat.set(main_c)
            self._on_main_category_selected()
            self.combo_sub_cat.set(sub_c)
            
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

        main_cat = self.combo_main_cat.get().strip() or "عام"
        sub_cat = self.combo_sub_cat.get().strip() or "عام"

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
            
            self.cursor.execute("""
                INSERT INTO products (barcode, local_code, barcode2, barcode3, name, price, cost, stock, all_barcodes, category, main_category, sub_category, synced) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
            """, (b1, loc_code, b2, b3, name, price, cost, stock, all_bcs_str, main_cat, main_cat, sub_cat))
            new_id = self.cursor.lastrowid
            self.db.commit()
            
            # تشغيل المزامنة الفورية مع المتجر الإلكتروني
            if hasattr(self.app, 'sync_mgr'):
                self.app.sync_mgr.trigger_instant_sync()

            self.refresh_nav_ids()
            self.prod_clear_form()
            
            self.show_status(f"✅ تم إضافة ({name}) في قسم [{main_cat} > {sub_cat}] والمزامنة بنجاح!", "#2ecc71")
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
        
        main_cat = self.combo_main_cat.get().strip() or "عام"
        sub_cat = self.combo_sub_cat.get().strip() or "عام"

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
            
            self.cursor.execute("""
                UPDATE products 
                SET barcode=?, local_code=?, barcode2=?, barcode3=?, name=?, price=?, cost=?, stock=?, all_barcodes=?, category=?, main_category=?, sub_category=?, synced=0 
                WHERE id=?
            """, (b1, loc_code, b2, b3, name, price, cost, stock, all_bcs_str, main_cat, main_cat, sub_cat, self.current_edit_id))

            self.db.commit()
            
            # تشغيل المزامنة الفورية مع المتجر الإلكتروني
            if hasattr(self.app, 'sync_mgr'):
                self.app.sync_mgr.trigger_instant_sync()

            self.prod_clear_form()
            self.show_status("✅ تم التعديل وحفظ التصنيف والمزامنة بنجاح!", "#2ecc71")
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
        
        # إعادة تعيين التصنيفات للوضع الافتراضي
        try:
            mains = self.combo_main_cat.cget("values")
            if mains:
                self.combo_main_cat.set(mains[0])
                self._on_main_category_selected()
        except:
            pass

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
            self.cursor.execute("UPDATE products SET stock = stock + ?, synced=0 WHERE id=?", (qty, self.current_edit_id))
            self.db.commit()
            
            if hasattr(self.app, 'sync_mgr'):
                self.app.sync_mgr.trigger_instant_sync()

            current_stock = float(self.ent_p_stock.get() or 0)
            self.ent_p_stock.delete(0, 'end')
            self.ent_p_stock.insert(0, str(current_stock + qty))
            
            self.show_status(f"✅ تم إضافة ({qty:g}) لرصيد {name} ومزامنته!", "#2ecc71")
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