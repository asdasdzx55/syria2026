import customtkinter as ctk
from tkinter import ttk

# استدعاء ملف قاعدة البيانات
from database import setup_database

# استدعاء الصفحات من مجلد pages
from pages.pos_page import POSPage
from pages.products_page import ProductsPage
from pages.suppliers_page import SuppliersPage  # Replace 'SuppliersPage' with the actual class name if different
from pages.hr_page import HRPage
from pages.expenses_page import ExpensesPage
from pages.returns_page import ReturnsPage
from pages.admin_page import AdminPage
from pages.inventory_page import InventoryPage
import tkinter.messagebox as messagebox

class ERPSystem(ctk.CTk):
    def __init__(self, db_conn):
        super().__init__()
        self.db = db_conn
        self.cursor = self.db.cursor()
        
        # إعداد جدول الإعدادات لحفظ كلمة المرور
        self.cursor.execute("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)")
        self.cursor.execute("INSERT OR IGNORE INTO settings (key, value) VALUES ('admin_password', '1234')")
        self.db.commit()
        
        self.title("سوبر ماركت المنزل السوري - نظام الكاشير والإدارة المتكامل")
        self.geometry("1350x850")
        ctk.set_appearance_mode("dark")
        ctk.set_default_color_theme("blue")

        style = ttk.Style()
        style.theme_use("default")
        style.configure("Treeview", background="#2b2b2b", foreground="white", rowheight=30, fieldbackground="#2b2b2b", font=("Arial", 12))
        style.map('Treeview', background=[('selected', '#1f538d')])
        style.configure("Treeview.Heading", background="#1f538d", foreground="white", font=("Arial", 12, "bold"))

        self.grid_rowconfigure(0, weight=1)
        self.grid_columnconfigure(1, weight=1)

        # ----------------- القائمة الجانبية -----------------
        self.sidebar = ctk.CTkFrame(self, width=220, corner_radius=0, fg_color="#1e272e")
        self.sidebar.grid(row=0, column=0, sticky="nsew")
        self.sidebar.grid_rowconfigure(10, weight=1)

        ctk.CTkLabel(self.sidebar, text="🏪 المنزل السوري", font=ctk.CTkFont(size=20, weight="bold"), text_color="#2ecc71").grid(row=0, column=0, padx=15, pady=(25, 25))

        nav_buttons = [
            ("🛒 شاشة الكاشير", "pos"), 
            ("📦 إدارة المنتجات", "products"), 
            ("📋 الجرد والمخزون", "inventory"),
            ("🤝 الموردين والمشتريات", "suppliers"), 
            ("👥 شؤون العاملين", "hr"), 
            ("💸 المصروفات العامة", "expenses"), 
            ("↩️ المرتجعات والفواتير", "returns"), 
            ("📊 الخزينة والإدارة", "admin")
        ]
        
        self.nav_btns = {}
        for i, (text, name) in enumerate(nav_buttons, start=1):
            btn = ctk.CTkButton(
                self.sidebar, 
                text=text, 
                font=ctk.CTkFont(size=14, weight="bold"),
                fg_color="#2c3e50", 
                hover_color="#34495e", 
                height=40,
                corner_radius=8,
                command=lambda n=name: self.show_frame(n)
            )
            btn.grid(row=i, column=0, padx=15, pady=5, sticky="ew")
            self.nav_btns[name] = btn

        # ----------------- مساحة العمل -----------------
        self.main_container = ctk.CTkFrame(self, corner_radius=0, fg_color="transparent")
        self.main_container.grid(row=0, column=1, sticky="nsew", padx=15, pady=15)
        self.main_container.grid_rowconfigure(0, weight=1)
        self.main_container.grid_columnconfigure(0, weight=1)

        # ----------------- تهيئة المزامنة السحابية المركزية -----------------
        from sync_manager import HybridSyncManager
        self.sync_mgr = HybridSyncManager(self.db, self)

        # ----------------- تهيئة الصفحات -----------------
        self.frames = {}
        
        self.frames["pos"] = POSPage(self.main_container, self.db, self)
        self.frames["products"] = ProductsPage(self.main_container, self.db, self)
        self.frames["inventory"] = InventoryPage(self.main_container, self.db, self)
        self.frames["suppliers"] = SuppliersPage(self.main_container, self.db, self)
        self.frames["hr"] = HRPage(self.main_container, self.db, self)
        self.frames["expenses"] = ExpensesPage(self.main_container, self.db, self)
        self.frames["returns"] = ReturnsPage(self.main_container, self.db, self)
        self.frames["admin"] = AdminPage(self.main_container, self.db, self)

        # إخفاء واجهة البرنامج وإظهار شاشة الدخول أولاً
        self.sidebar.grid_remove()
        self.main_container.grid_remove()
        self.show_login_screen()

    def show_login_screen(self):
        self.login_frame = ctk.CTkFrame(self, corner_radius=15, fg_color="#2c3e50")
        self.login_frame.place(relx=0.5, rely=0.5, anchor="center")
        
        ctk.CTkLabel(self.login_frame, text="🔒 تسجيل الدخول للنظام", font=ctk.CTkFont(size=24, weight="bold"), text_color="white").pack(pady=30, padx=50)
        
        self.pwd_entry = ctk.CTkEntry(self.login_frame, placeholder_text="كلمة المرور (الافتراضية: 1234)", show="*", width=260, height=40, justify="center", font=("Arial", 14))
        self.pwd_entry.pack(pady=10, padx=50)
        self.pwd_entry.bind("<Return>", lambda e: self.check_login())
        
        ctk.CTkButton(self.login_frame, text="دخول 🚀", font=ctk.CTkFont(size=16, weight="bold"), fg_color="#27ae60", hover_color="#1e8449", height=42, width=150, command=self.check_login).pack(pady=30)
        self.pwd_entry.focus()

    def check_login(self):
        pwd = self.pwd_entry.get()
        self.cursor.execute("SELECT value FROM settings WHERE key='admin_password'")
        correct_pwd = self.cursor.fetchone()[0]
        
        if pwd == correct_pwd:
            self.login_frame.destroy()
            self.sidebar.grid(row=0, column=0, sticky="nsew")
            self.main_container.grid(row=0, column=1, sticky="nsew", padx=15, pady=15)
            
            # تشغيل المزامنة اللحظية في الخلفية تلقائياً
            try:
                self.sync_mgr.start_background_sync()
                self.sync_mgr.trigger_instant_sync()
            except Exception as e:
                print(f"Auto-sync start error: {e}")
                
            self.show_frame("pos")
        else:
            messagebox.showerror("خطأ", "كلمة المرور غير صحيحة!")
            self.pwd_entry.delete(0, 'end')

    def show_frame(self, frame_name):
        for name, btn in self.nav_btns.items():
            if name == frame_name:
                btn.configure(fg_color="#1f538d")
            else:
                btn.configure(fg_color="#2c3e50")

        for frame in self.frames.values():
            frame.grid_remove()
        
        self.frames[frame_name].grid(row=0, column=0, sticky="nsew")
        self.frames[frame_name].on_show()


# ==========================================
# تشغيل البرنامج
# ==========================================
if __name__ == "__main__":
    database = setup_database()
    app = ERPSystem(database)
    app.mainloop()