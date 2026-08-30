import os
import webbrowser
from datetime import datetime
import subprocess

CODE128_PATTERNS = [
    '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
    '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
    '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
    '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
    '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
    '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
    '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
    '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
    '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
    '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
    '114131', '311141', '411131', '211412', '211214', '211232', '2331112'
]

def generate_code128_svg(text, height=42, bar_width=1.3):
    text_clean = "".join([c for c in str(text) if 32 <= ord(c) <= 126])
    if not text_clean:
        text_clean = "INV-000"
    values = [ord(c) - 32 for c in text_clean]
    checksum = (104 + sum((i + 1) * v for i, v in enumerate(values))) % 103
    pattern_indices = [104] + values + [checksum, 106]
    
    widths = []
    for idx in pattern_indices:
        pattern = CODE128_PATTERNS[idx]
        for p in pattern:
            widths.append(int(p))
            
    total_modules = sum(widths)
    svg_width = total_modules * bar_width
    
    rects = []
    x = 0
    draw = True
    for w in widths:
        rect_w = w * bar_width
        if draw:
            rects.append(f'<rect x="{x:.1f}" y="0" width="{rect_w:.1f}" height="{height}" fill="#000" />')
        x += rect_w
        draw = not draw
        
    rects_str = "".join(rects)
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="{svg_width:.1f}" height="{height + 15}" viewBox="0 0 {svg_width:.1f} {height + 15}" style="margin: 0 auto; display: block;">
{rects_str}
<text x="{svg_width/2:.1f}" y="{height + 13}" font-family="Arial, monospace" font-size="12" font-weight="bold" text-anchor="middle" fill="#000">*{text_clean}*</text>
</svg>'''
    return svg

def print_salas_receipt(invoice_data, items_list):
    
    shop_ar = invoice_data.get('shop_name_ar', 'اسم المحل')
    shop_en = invoice_data.get('shop_name_en', 'Salas POS')
    inv_id = invoice_data.get('invoice_id', '000')
    pay_type = invoice_data.get('pay_type', 'نقدي')
    date_str = datetime.now().strftime('%Y/%m/%d %I:%M:%S %p')
    cashier = invoice_data.get('cashier_name', 'أحمد عبد الوهاب')
    
    # استخراج حالة الفاتورة واسم الطيار لتحديد العنوان المناسب
    status = invoice_data.get('status', 'مكتملة')
    delivery_person = invoice_data.get('delivery_person', '')
    
    if status == 'مؤقتة':
        if delivery_person and delivery_person != "بدون توصيل (تيك أواي)":
            invoice_title = "فـاتـورة ديـلـيـفـري (مـبـدئـيـة)"
        else:
            invoice_title = "فـاتـورة مـبـدئـيـة"
    elif status == 'مرتجع':
        invoice_title = "فـاتـورة مـرتـجـع"
    else:
        invoice_title = "فـاتـورة بـيـع"

    customer = invoice_data.get('customer_name', '')
    if not customer: customer = "عميل طياري"
        
    c_phone = invoice_data.get('customer_phone', '')
    c_address = invoice_data.get('customer_address', '')
    payment_fee = float(invoice_data.get('payment_fee', 0.0))
    delivery_fee = float(invoice_data.get('delivery_fee', 0.0)) # سعر التوصيل من الكاشير
    
    address = invoice_data.get('shop_address', '')
    phone = invoice_data.get('shop_phone', '')
    
    html_content = f"""
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>فاتورة رقم {inv_id}</title>
        <style>
            @page {{
                margin: 0;
                size: 72mm auto;
            }}
            * {{
                box-sizing: border-box; 
            }}
            body {{
                font-family: 'Arial', Tahoma, sans-serif;
                width: 65mm; 
                margin: 0 auto;
                padding: 5mm 5mm 5mm 2mm; 
                color: #000;
                font-size: 13px;
            }}
            .center {{ text-align: center; }}
            
            h1 {{ font-size: 26px; margin: 5px 0; font-weight: 900; letter-spacing: 1px; }}
            h2 {{ font-size: 18px; margin: 5px auto; font-weight: bold; border-bottom: 2px dashed #000; display: inline-block; padding-bottom: 5px; }}
            h3 {{ font-size: 16px; margin: 5px 0; font-weight: bold; }}
            
            .info-table {{ width: 100%; font-size: 13px; margin: 10px 0; font-weight: bold; }}
            .info-table td {{ padding: 2px 0; }}
            
            .items-table {{
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 10px;
                border: 2px solid #000;
                font-weight: bold;
                text-align: center;
                font-size: 12px;
            }}
            .items-table th, .items-table td {{
                border: 1px solid #000;
                padding: 4px 1px;
            }}
            .items-table th {{ background-color: #e0e0e0; -webkit-print-color-adjust: exact; }}
            
            .totals-table {{ width: 100%; font-size: 15px; font-weight: bold; margin-bottom: 15px; }}
            .totals-table td {{ padding: 4px 0; }}
            
            .footer {{ text-align: center; font-size: 13px; font-weight: bold; border-top: 2px solid #000; padding-top: 10px; margin-top: 10px; }}
            .footer p {{ margin: 3px 0; }}
            
            .dev-signature {{ font-size: 11px; font-weight: normal; margin-top: 15px; }}
            
            @media print {{ .no-print {{ display: none; }} }}
        </style>
    </head>
    <body onload="setTimeout(function(){{ window.print(); }}, 500);" onafterprint="window.close();">
        
        <div class="center">
            <h1>{shop_ar}</h1>
            <h2>{invoice_title}</h2>
            <h3>{shop_en}</h3>
        </div>

        <table class="info-table">
            <tr><td style="width: 40%;">رقم الفاتورة:</td><td style="font-size: 15px;">*{inv_id}*</td></tr>
            <tr><td>نوع الدفع:</td><td>{pay_type}</td></tr>
            <tr><td>التاريخ:</td><td style="font-size: 11px;">{date_str}</td></tr>
            <tr><td>الكاشير:</td><td>{cashier}</td></tr>
            <tr><td>العميل:</td><td>{customer}</td></tr>
"""

    if c_phone: html_content += f"<tr><td>هاتف:</td><td>{c_phone}</td></tr>"
    if c_address: html_content += f"<tr><td>عنوان:</td><td>{c_address}</td></tr>"
    # طباعة اسم الطيار إذا كان الأوردر توصيل
    if delivery_person and delivery_person != "بدون توصيل (تيك أواي)": 
        html_content += f"<tr><td>الطيار:</td><td style='font-size: 14px; font-weight: 900;'>{delivery_person}</td></tr>"

    html_content += """
        </table>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 42%;">الصنف</th>
                    <th style="width: 14%;">الكمية</th>
                    <th style="width: 22%;">السعر</th>
                    <th style="width: 22%;">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
    """
    
    total_qty = 0
    total_amount = 0.0
    
    for item in items_list:
        name = str(item.get('name', ''))[:15]
        qty = float(item.get('qty', 1))
        price = float(item.get('price', 0.0))
        subtotal = qty * price
        
        total_qty += qty
        total_amount += subtotal
        
        html_content += f"""
                <tr>
                    <td style="text-align: right; padding-right: 2px;">{name}</td>
                    <td>{qty:g}</td>
                    <td>{price:.2f}</td>
                    <td>{subtotal:.2f}</td>
                </tr>
        """
        
    shop_final = total_amount + payment_fee
    grand_total = shop_final + delivery_fee # حق المحل + الدليفري
    
    if status == 'مؤقتة':
        paid = 0.0
        remain = grand_total
    else:
        paid = float(invoice_data.get('paid', grand_total))
        remain = paid - grand_total
    
    html_content += f"""
            </tbody>
        </table>
        <table class="totals-table">
            <tr><td colspan="2" style="text-align: center; border-bottom: 1px solid #000; padding-bottom: 5px; margin-bottom: 5px;">عدد الأصناف: {int(total_qty)}</td></tr>
            <tr><td style="text-align: right; padding-top: 10px;">إجمالي الأصناف</td><td style="text-align: left; padding-top: 10px;">{total_amount:.2f}</td></tr>
    """
    
    if payment_fee > 0:
        html_content += f'<tr><td style="text-align: right;">رسوم خدمة ({pay_type})</td><td style="text-align: left;">{payment_fee:.2f}</td></tr>'
        
    if delivery_fee > 0:
        html_content += f'<tr><td style="text-align: right; color: #555;">خدمة التوصيل</td><td style="text-align: left; color: #555;">{delivery_fee:.2f}</td></tr>'
        
    html_content += f"""
            <tr><td style="text-align: right; border-bottom: 1px dashed #000; padding-bottom: 5px;">المطلوب كلياً</td><td style="text-align: left; border-bottom: 1px dashed #000; padding-bottom: 5px;">{grand_total:.2f}</td></tr>
    """

    if status == 'مؤقتة':
        html_content += f"""
            <tr><td style="text-align: right; padding-top: 5px; color: #c0392b;">حالة الدفع</td><td style="text-align: left; padding-top: 5px; color: #c0392b;">غير مدفوعة (مبدئية)</td></tr>
        """
    else:
        html_content += f"""
            <tr><td style="text-align: right; padding-top: 5px;">المـدفـوع</td><td style="text-align: left; padding-top: 5px;">{paid:.2f}</td></tr>
            <tr><td style="text-align: right;">المـتـبـقـي</td><td style="text-align: left;">{remain:.2f}</td></tr>
        """

    barcode_text = f"INV-{inv_id}"
    barcode_svg = generate_code128_svg(barcode_text, height=38, bar_width=1.2)

    html_content += f"""
        </table>
        <div class="footer">
            <div style="margin: 8px 0; text-align: center;">
                {barcode_svg}
                <div style="font-size: 10px; font-weight: bold; margin-top: 3px; color: #333;">* امسح الباركود في شاشة المرتجعات *</div>
            </div>
            <p>{address}</p>
            <p>TEL: {phone}</p>
            <div class="dev-signature">
                <strong>Salas POS</strong><br>
                تم تطوير النظام من قبل: أحمد عبد الوهاب
            </div>
        </div>
    </body>
    </html>
    """

    
    file_name = "invoice_print.html"
    file_path = os.path.abspath(file_name)
    
    with open(file_path, "w", encoding="utf-8-sig") as f:
        f.write(html_content)
        
    try:
        print_cmd = f'msedge --headless --disable-gpu --print-to-default-printer "file:///{file_path}"'
        result = subprocess.run(print_cmd, shell=True, capture_output=True)
        if result.returncode != 0:
            webbrowser.open('file://' + file_path)
    except Exception:
        webbrowser.open('file://' + file_path)


def print_purchase_receipt(invoice_data, items_list):
    shop_ar = invoice_data.get('shop_name_ar', 'نظام إدارة الكاشير والمخازن')
    inv_id = invoice_data.get('invoice_id', '---')
    supplier_name = invoice_data.get('supplier_name', 'مورد عام')
    date_str = invoice_data.get('date', datetime.now().strftime('%Y/%m/%d %H:%M'))
    
    total = float(invoice_data.get('total', 0.0))
    discount = float(invoice_data.get('discount', 0.0))
    paid = float(invoice_data.get('paid', 0.0))
    remain = total - paid
    if remain < 0: remain = 0.0

    items_rows = ""
    for idx, item in enumerate(items_list, 1):
        name = item.get('name', 'منتج')
        cost = float(item.get('cost', 0.0))
        qty = float(item.get('qty', 1.0))
        subtotal = cost * qty
        qty_str = f"{qty:g} كغم" if qty % 1 != 0 else f"{int(qty)}"
        items_rows += f"""
        <tr>
            <td>{idx}</td>
            <td style="text-align: right;">{name}</td>
            <td>{cost:g} ج.م</td>
            <td>{qty_str}</td>
            <td>{subtotal:g} ج.م</td>
        </tr>
        """

    html_content = f"""
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>فاتورة مشتريات رقم {inv_id}</title>
        <style>
            body {{
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                width: 190mm;
                margin: 10mm auto;
                padding: 15px;
                color: #2c3e50;
                background: #fff;
                direction: rtl;
            }}
            .header-box {{
                text-align: center;
                border-bottom: 3px solid #1f538d;
                padding-bottom: 10px;
                margin-bottom: 15px;
            }}
            .header-box h1 {{ margin: 0; color: #1f538d; font-size: 24px; }}
            .header-box h2 {{ margin: 5px 0; color: #7f8c8d; font-size: 16px; }}
            .info-grid {{
                display: flex;
                justify-content: space-between;
                background: #f8f9fa;
                padding: 10px 15px;
                border-radius: 8px;
                margin-bottom: 15px;
                font-weight: bold;
            }}
            .items-table {{
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px;
            }}
            .items-table th {{
                background-color: #1f538d;
                color: white;
                padding: 8px;
                border: 1px solid #1f538d;
                text-align: center;
            }}
            .items-table td {{
                padding: 8px;
                border: 1px solid #ddd;
                text-align: center;
            }}
            .items-table tr:nth-child(even) {{ background-color: #f9f9f9; }}
            .summary-box {{
                width: 300px;
                float: left;
                background: #eef2f7;
                padding: 12px;
                border-radius: 8px;
                font-weight: bold;
                margin-top: 10px;
            }}
            .summary-row {{
                display: flex;
                justify-content: space-between;
                margin-bottom: 6px;
            }}
            .summary-row.total {{
                border-top: 2px solid #1f538d;
                padding-top: 6px;
                color: #27ae60;
                font-size: 16px;
            }}
            .clearfix {{ clear: both; }}
            @media print {{
                body {{ width: 100%; margin: 0; padding: 0; }}
                .no-print {{ display: none; }}
            }}
        </style>
    </head>
    <body onload="setTimeout(function(){{ window.print(); }}, 400);">
        <div class="header-box">
            <h1>📦 {shop_ar}</h1>
            <h2>🧾 فاتورة توريد ومشتريات بضاعة (استلام مخزن)</h2>
        </div>

        <div class="info-grid">
            <div>رقم الفاتورة: <span style="color:#c0392b;">#{inv_id}</span></div>
            <div>المورد: <span style="color:#1f538d;">{supplier_name}</span></div>
            <div>التاريخ: <span>{date_str}</span></div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th width="40">م</th>
                    <th>اسم المنتج / الصنف</th>
                    <th width="110">سعر التكلفة</th>
                    <th width="100">الكمية/الوزن</th>
                    <th width="120">الإجمالي الصافي</th>
                </tr>
            </thead>
            <tbody>
                {items_rows}
            </tbody>
        </table>

        <div class="summary-box">
            <div class="summary-row"><span>الخصم الممنوح:</span> <span>{discount:g} ج.م</span></div>
            <div class="summary-row total"><span>الإجمالي المطلوب:</span> <span>{total:g} ج.م</span></div>
            <div class="summary-row"><span>المدفوع كاش:</span> <span>{paid:g} ج.م</span></div>
            <div class="summary-row"><span>المتبقي آجل:</span> <span>{remain:g} ج.م</span></div>
        </div>
        <div class="clearfix"></div>

        <div style="text-align: center; margin-top: 30px; font-size: 12px; color: #7f8c8d;">
            توقيع المستلم / أخصائي المخزن: ____________________ &nbsp;&nbsp;&nbsp;&nbsp; توقيع المورد / المندوب: ____________________
        </div>
    </body>
    </html>
    """

    file_name = "purchase_invoice_print.html"
    file_path = os.path.abspath(file_name)
    
    with open(file_path, "w", encoding="utf-8-sig") as f:
        f.write(html_content)
        
    try:
        webbrowser.open('file://' + file_path)
    except Exception:
        pass


if __name__ == "__main__":
    # تجربة الفاتورة المبدئية للدليفري
    data = {
        "shop_name_ar": "سوبر ماركت",
        "shop_name_en": "Salas POS",
        "invoice_id": "***",
        "status": "مؤقتة",
        "pay_type": "كاش",
        "cashier_name": "أحمد عبد الوهاب",
        "customer_name": "عميل تجريبي",
        "shop_address": "الفرع الرئيسي",
        "shop_phone": "01110000000",
        "delivery_person": "محمود دليفري",
        "delivery_fee": 15.0, 
        "paid": 115.0
    }
    items = [{"name": "مكرونة", "qty": 5, "price": 20.00}]
    print_salas_receipt(data, items)