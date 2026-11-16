import urllib.request, json, ssl

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

url = 'https://syrianhouse.almagd555.com/api_sync.php?action=get_order_details&order_id=DS-2800'
req = urllib.request.Request(url, headers={
    'User-Agent': 'Mozilla/5.0',
    'X-API-KEY': 'syrian_home_pos_secret_token_2026'
})

with urllib.request.urlopen(req, context=ctx, timeout=15) as resp:
    res = json.loads(resp.read().decode('utf-8'))
    print('Order details success:', res.get('success'))
    if res.get('order'):
        print('Customer:', res['order'].get('customer_name'))
        print('Total:', res['order'].get('total'))
        print('Items count:', len(res['order'].get('items', [])))
        for it in res['order'].get('items', []):
            print(f"  {it.get('name')}: qty={it.get('qty')}, price={it.get('price')}, total={it.get('total')}")
