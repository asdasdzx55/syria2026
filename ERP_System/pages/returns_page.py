import customtkinter as ctk
import tkinter.messagebox as messagebox
from tkinter import ttk

class ReturnsPage(ctk.CTkFrame):
    def __init__(self, parent, db_conn, app):
        super().__init__(parent, fg_color="transparent")
        self.db = db_conn
        self.cursor = self.db.cursor()
        self.app = app
        self.setup_ui()

    def setup_ui(self):
        ctk.CTkLabel(self, text="سجل الفواتير والمرتجعات", font=ctk.CTkFont(size=24, weight="bold")).pack(pady=10)
        
        search_frame = ctk.CTkFrame(self)
        search_frame.pack(fill="x", pady=10)
        ctk.CTkLabel(search_frame, text="ابحث برقم الفاتورة أو العميل:").pack(side="right", padx=10)
        self.ret_search_var = ctk.StringVar()
        self.ret_search_var.trace("w", self.ret_live_search)
        ctk.CTkEntry(search_frame, textvariable=self.ret_search_var, width=200).pack(side="right", padx=10)
        
        self.btn_return_action = ctk.CTkButton(search_frame, text="استرجاع الفاتورة المحددة", fg_color="#c0392b", state="disabled", command=self.process_return)
        self.btn_return_action.pack(side="left", padx=20)

        tree_frame = ctk.CTkFrame(self)
        tree_frame.pack(expand=True, fill="both", pady=10)
        self.ret_tree = ttk.Treeview(tree_frame, columns=('id', 'date', 'total', 'customer', 'status'), show='headings')
        for col, text in zip(self.ret_tree['columns'], ['رقم', 'التاريخ', 'الإجمالي', 'العميل', 'الحالة']):
            self.ret_tree.heading(col, text=text)
        self.ret_tree.pack(expand=True, fill="both")
        self.ret_tree.bind("<<TreeviewSelect>>", self.ret_on_select)
        self.ret_selected_id = None

    def on_show(self):
        self.ret_load_invoices()

    def ret_load_invoices(self, search_term=""):
        for item in self.ret_tree.get_children(): self.ret_tree.delete(item)
        self.cursor.execute("SELECT id, date, total, customer, status FROM sales WHERE id LIKE ? OR customer LIKE ? ORDER BY id DESC", (f'%{search_term}%', f'%{search_term}%'))
        for row in self.cursor.fetchall(): self.ret_tree.insert("", "end", values=row)

    def ret_live_search(self, *args):
        self.ret_load_invoices(self.ret_search_var.get())

    def ret_on_select(self, event):
        selected = self.ret_tree.selection()
        if selected:
            item = self.ret_tree.item(selected[0])['values']
            self.ret_selected_id = item[0]
            status = item[4]
            if status == 'مكتملة': self.btn_return_action.configure(state="normal")
            else: self.btn_return_action.configure(state="disabled")

    def process_return(self):
        if not self.ret_selected_id: return
        if messagebox.askyesno("تأكيد", f"استرجاع فاتورة رقم {self.ret_selected_id} بجميع محتوياتها للمخزن؟"):
            self.cursor.execute("UPDATE sales SET status = 'مرتجع' WHERE id=?", (self.ret_selected_id,))
            self.cursor.execute("SELECT product_id, qty FROM sale_items WHERE sale_id=?", (self.ret_selected_id,))
            for item in self.cursor.fetchall():
                self.cursor.execute("UPDATE products SET stock = stock + ? WHERE id=?", (item[1], item[0]))
            self.db.commit()
            messagebox.showinfo("نجاح", "تم الاسترجاع بنجاح وإرجاع البضاعة للمخزن.")
            self.ret_load_invoices(self.ret_search_var.get())
            self.btn_return_action.configure(state="disabled")