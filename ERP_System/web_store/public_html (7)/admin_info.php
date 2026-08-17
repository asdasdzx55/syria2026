<?php
require_once 'config.php';

// التحقق من رتبة المدير
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

// معالجة حفظ وتحديث معلومات المتجر والسياسات والذكاء الاصطناعي
if (isset($_POST['update_store_info'])) {
    $keys = [
        'contact_phone', 'contact_email', 'social_whatsapp', 'social_facebook', 'social_instagram', 
        'policy_return', 'policy_shipping', 'policy_payment', 'policy_privacy', 'policy_terms',
        'groq_api_key', 'ai_chat_enabled'
    ];
    
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key_name = ?");
    $update_stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE key_name = ?");
    $insert_stmt = $pdo->prepare("INSERT INTO settings (key_name, setting_value) VALUES (?, ?)");

    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            $val = trim($_POST[$k]);
            $check_stmt->execute([$k]);
            if ($check_stmt->fetchColumn() > 0) {
                $update_stmt->execute([$val, $k]);
            } else {
                $insert_stmt->execute([$k, $val]);
            }
        }
    }
    header("Location: admin_info.php?msg=info_updated");
    exit;
}

// إعادة جلب الإعدادات المحدثة
$settings = [];
$stmt = $pdo->query("SELECT * FROM settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $settings[$row['key_name']] = $row['setting_value']; }

include 'header.php';
include 'admin_nav.php';
?>

<div class="container mx-auto px-4 md:px-8 py-10 max-w-5xl animate-fade-in">
    <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-royal-gold/10 pb-4">
        <div>
            <h2 class="text-2xl font-serif font-bold text-royal-dark">📜 سياسات المتجر والذكاء الاصطناعي</h2>
            <p class="text-xs text-gray-400 mt-1 font-light">تخصيص سياسات الاستبدال والاسترجاع، الشحن، الدفع، الخصوصية، والشروط العامة للمتجر.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="policies.php" target="_blank" class="bg-royal-sand/80 hover:bg-royal-gold hover:text-white text-royal-darkgold text-xs font-bold px-4 py-2.5 rounded-xl transition shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> معاينة صفحة السياسات (المتجر)
            </a>
        </div>
    </div>

    <!-- رسائل التنبيه والنجاح -->
    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'info_updated'): ?>
        <div class="bg-green-50 text-green-700 p-4 mb-6 rounded-xl border border-green-200 text-xs font-bold animate-fade-in">
            <i class="fa-solid fa-circle-check mr-1 text-sm"></i> تم تحديث سياسات المتجر وإعدادات التواصل بنجاح!
        </div>
    <?php endif; ?>

    <form method="POST" action="admin_info.php" class="space-y-8">
        
        <!-- نصوص وسياسات المتجر الرسمية الشاملة -->
        <div class="bg-white p-6 md:p-8 border border-royal-gold/10 shadow-sm rounded-2xl space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-royal-gold/15 pb-4">
                <div>
                    <h3 class="font-serif font-bold text-base text-royal-dark flex items-center gap-2">
                        <i class="fa-solid fa-file-contract text-royal-darkgold"></i> بنود ونصوص سياسات المتجر (Store Policies)
                    </h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">تظهر هذه البنود للعملاء في صفحة السياسات الموحدة وفي تذييل المتجر وصفحات الدفع.</p>
                </div>
                <button type="button" onclick="loadDefaultPoliciesPreset()" class="bg-royal-sand hover:bg-royal-gold hover:text-white text-royal-dark text-[11px] font-bold px-3.5 py-1.5 rounded-xl transition shadow-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-wand-magic-sparkles text-royal-darkgold"></i> استعادة النصوص النموذجية الاحترافية
                </button>
            </div>
            
            <div class="grid grid-cols-1 gap-6 text-xs">
                
                <!-- 1. سياسة الاستبدال والاسترجاع -->
                <div class="bg-royal-sand/15 p-5 rounded-xl border border-royal-gold/15 space-y-2">
                    <label class="block font-bold text-royal-dark flex items-center gap-2">
                        <i class="fa-solid fa-arrow-rotate-left text-royal-darkgold"></i>
                        1. سياسة الاستبدال والاسترجاع (Return & Refund Policy) *
                    </label>
                    <textarea id="inp-policy-return" name="policy_return" rows="5" required class="w-full p-4 border border-gray-200 outline-none focus:border-royal-gold leading-relaxed rounded-xl text-xs bg-white focus:ring-1 focus:ring-royal-gold" placeholder="اكتب شروط الاسترجاع، المدة الزمنية، وطريقة إعادة المبلغ للعميل..."><?php echo htmlspecialchars($settings['policy_return'] ?? ''); ?></textarea>
                    <p class="text-[10px] text-gray-400">توضح للعميل شروط إرجاع المنتجات واسترداد أمواله وحماية حقوقه.</p>
                </div>

                <!-- 2. سياسة الشحن والتوصيل -->
                <div class="bg-royal-sand/15 p-5 rounded-xl border border-royal-gold/15 space-y-2">
                    <label class="block font-bold text-royal-dark flex items-center gap-2">
                        <i class="fa-solid fa-truck-fast text-royal-darkgold"></i>
                        2. سياسة الشحن والتوصيل (Shipping & Delivery Policy) *
                    </label>
                    <textarea id="inp-policy-shipping" name="policy_shipping" rows="4" required class="w-full p-4 border border-gray-200 outline-none focus:border-royal-gold leading-relaxed rounded-xl text-xs bg-white focus:ring-1 focus:ring-royal-gold" placeholder="اكتب مواعيد التسليم، شركات الشحن المعتمدة، وتتبع الشحنات..."><?php echo htmlspecialchars($settings['policy_shipping'] ?? ''); ?></textarea>
                    <p class="text-[10px] text-gray-400">توضح للعميل مدد التسليم بالمحافظات والدول وخطوات متابعة الشحنة.</p>
                </div>

                <!-- 3. سياسة وطرق الدفع والأمان -->
                <div class="bg-royal-sand/15 p-5 rounded-xl border border-royal-gold/15 space-y-2">
                    <label class="block font-bold text-royal-dark flex items-center gap-2">
                        <i class="fa-solid fa-credit-card text-royal-darkgold"></i>
                        3. سياسة وطرق الدفع والأمان (Payment & Security Policy)
                    </label>
                    <textarea id="inp-policy-payment" name="policy_payment" rows="4" class="w-full p-4 border border-gray-200 outline-none focus:border-royal-gold leading-relaxed rounded-xl text-xs bg-white focus:ring-1 focus:ring-royal-gold" placeholder="اكتب تفاصيل وسائل الدفع المقبولة وحماية المعاملات والتشفير..."><?php echo htmlspecialchars($settings['policy_payment'] ?? ''); ?></textarea>
                    <p class="text-[10px] text-gray-400">تطمئن العميل بخصوص أمان بياناته البنكية وخيارات الدفع المتاحة له.</p>
                </div>

                <!-- 4. سياسة الخصوصية وسرية المعلومات -->
                <div class="bg-royal-sand/15 p-5 rounded-xl border border-royal-gold/15 space-y-2">
                    <label class="block font-bold text-royal-dark flex items-center gap-2">
                        <i class="fa-solid fa-user-shield text-royal-darkgold"></i>
                        4. سياسة الخصوصية وحماية البيانات (Privacy Policy)
                    </label>
                    <textarea id="inp-policy-privacy" name="policy_privacy" rows="4" class="w-full p-4 border border-gray-200 outline-none focus:border-royal-gold leading-relaxed rounded-xl text-xs bg-white focus:ring-1 focus:ring-royal-gold" placeholder="اكتب التزام المتجر بحماية بيانات العميل وعدم مشاركتها..."><?php echo htmlspecialchars($settings['policy_privacy'] ?? ''); ?></textarea>
                    <p class="text-[10px] text-gray-400">تؤكد على سرية بيانات العملاء وحمايتها وفق المعايير العالمية.</p>
                </div>

                <!-- 5. الشروط والأحكام العامة -->
                <div class="bg-royal-sand/15 p-5 rounded-xl border border-royal-gold/15 space-y-2">
                    <label class="block font-bold text-royal-dark flex items-center gap-2">
                        <i class="fa-solid fa-scale-balanced text-royal-darkgold"></i>
                        5. الشروط والأحكام العامة للمتجر (Terms of Service)
                    </label>
                    <textarea id="inp-policy-terms" name="policy_terms" rows="4" class="w-full p-4 border border-gray-200 outline-none focus:border-royal-gold leading-relaxed rounded-xl text-xs bg-white focus:ring-1 focus:ring-royal-gold" placeholder="اكتب شروط الاستخدام، حقوق الملكية الفكرية، وضوابط الطلبات..."><?php echo htmlspecialchars($settings['policy_terms'] ?? ''); ?></textarea>
                    <p class="text-[10px] text-gray-400">تحدد الإطار القانوني لاستخدام المتجر وحقوق المتجر والعميل.</p>
                </div>

            </div>
        </div>

        <!-- وسائل التواصل الاجتماعي والهاتف (الفوتر) -->
        <div class="bg-white p-6 md:p-8 border border-royal-gold/10 shadow-sm rounded-2xl">
            <h3 class="font-serif font-bold text-base text-royal-dark mb-5 border-b pb-2.5 flex items-center gap-1.5">
                <i class="fa-regular fa-address-book text-royal-darkgold"></i> قنوات الاتصال والروابط (التذييل)
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-xs">
                <div>
                    <label class="block text-gray-500 font-bold mb-2">رقم الهاتف (الواتس اب)</label>
                    <input type="text" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>" class="w-full p-3 border border-gray-200 outline-none focus:border-royal-gold rounded-xl font-semibold text-royal-dark" dir="ltr" style="text-align: right;">
                </div>
                <div>
                    <label class="block text-gray-500 font-bold mb-2">البريد الإلكتروني الرسمي</label>
                    <input type="email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>" class="w-full p-3 border border-gray-200 outline-none focus:border-royal-gold rounded-xl font-serif text-royal-dark" dir="ltr">
                </div>
                <div>
                    <label class="block text-gray-500 font-bold mb-2">رابط الواتساب المباشر</label>
                    <input type="text" name="social_whatsapp" value="<?php echo htmlspecialchars($settings['social_whatsapp'] ?? ''); ?>" class="w-full p-3 border border-gray-200 outline-none focus:border-royal-gold rounded-xl font-serif text-royal-dark" dir="ltr">
                </div>
                <div>
                    <label class="block text-gray-500 font-bold mb-2">رابط صفحة الفيسبوك</label>
                    <input type="text" name="social_facebook" value="<?php echo htmlspecialchars($settings['social_facebook'] ?? ''); ?>" class="w-full p-3 border border-gray-200 outline-none focus:border-royal-gold rounded-xl font-serif text-royal-dark" dir="ltr">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-gray-500 font-bold mb-2">رابط حساب الإنستجرام</label>
                    <input type="text" name="social_instagram" value="<?php echo htmlspecialchars($settings['social_instagram'] ?? ''); ?>" class="w-full p-3 border border-gray-200 outline-none focus:border-royal-gold rounded-xl font-serif text-royal-dark" dir="ltr">
                </div>
            </div>
        </div>

        <!-- قسم إعدادات المساعد الذكي (AI Chatbot) -->
        <div class="bg-purple-50/50 p-6 md:p-8 border border-purple-200 rounded-2xl shadow-sm">
            <h3 class="font-serif font-bold text-base text-purple-950 mb-4 border-b border-purple-200 pb-2.5 flex items-center gap-2">
                <i class="fa-solid fa-robot text-purple-600 animate-pulse"></i> إعدادات المساعد الذكي (AI Chatbot)
            </h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-purple-900 mb-2">مفتاح الاتصال بـ Groq API Key</label>
                    <input type="text" name="groq_api_key" value="<?php echo htmlspecialchars($settings['groq_api_key'] ?? ''); ?>" class="w-full p-3 border border-purple-200 outline-none focus:border-purple-500 rounded-xl font-mono text-sm bg-white" dir="ltr">
                    <p class="text-[10px] text-purple-600/80 mt-1.5 font-medium">💡 هذا المفتاح يُستخدم للربط بخوادم Groq السريعة وتشغيل نموذج الذكاء الاصطناعي (مثل Llama 3) للرد على استفسارات العملاء.</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                    <div>
                        <label class="block text-xs font-bold text-purple-900 mb-2">تفعيل المساعد في الموقع</label>
                        <select name="ai_chat_enabled" class="w-full p-3 border border-purple-200 outline-none rounded-xl bg-white text-xs font-bold">
                            <option value="1" <?php echo ($settings['ai_chat_enabled'] ?? '1') == '1' ? 'selected' : ''; ?>>مفعل ومتاح للعملاء (أيقونة عائمة)</option>
                            <option value="0" <?php echo ($settings['ai_chat_enabled'] ?? '0') == '0' ? 'selected' : ''; ?>>معطل ومخفي تماماً</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" name="update_store_info" class="w-full md:w-auto bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal font-bold px-12 py-4 tracking-widest text-xs rounded-xl shadow-md btn-shine transition-all">
            حفظ وتطبيق جميع السياسات والإعدادات
        </button>
    </form>
</div>

<script>
function loadDefaultPoliciesPreset() {
    if (!confirm('هل تريد استعادة وتعبئة النصوص النموذجية الاحترافية للسياسات والشروط؟')) return;

    document.getElementById('inp-policy-return').value = `• يحق للعميل طلب الاستبدال أو الاسترجاع خلال 14 يوماً من تاريخ استلام الشحنة وفقاً لأحكام حماية المستهلك.
• يشترط أن يكون المنتج في حالته الأصلية وبغلافه الأصلي غير المفتوح أو المستخدم مع كامل ملحقاته وفاتورة الشراء.
• في حالة وجود عيب مصنعي أو خطأ في المنتج المستلم، يتحمل المتجر كافة تكاليف الشحن والإرجاع بالكامل دون أي رسوم على العميل.
• لا تقبل المنتجات ذات الاستخدام الشخصي أو المخصصة بطلب خاص بعد فتح غلافها المغلق حفاظاً على الصحة العامة.
• يتم فحص المنتج فور وصوله لمستودعاتنا ويتم استرداد المبلغ خلال 3 إلى 5 أيام عمل عبر نفس وسيلة الدفع أو التحويل البنكي / المحافظ الإلكترونية.`;

    document.getElementById('inp-policy-shipping').value = `• نوفر خدمات الشحن السريع والتوصيل المباشر لكافة المحافظات والمدن والدول العربية المعتمدة.
• يستغرق التوصيل عادةً من 24 إلى 72 ساعة داخل المدن الرئيسية، ومن 3 إلى 5 أيام عمل لباقي المناطق أو الشحن الدولي.
• يتم تزويد العميل برقم تتبع فور تجهيز الشحنة مع إمكانية متابعة خط سير الطلب لحظياً عبر صفحة 'تتبع طلبك'.
• يرجى التأكد من كتابة العنوان بالتفصيل ورقم هاتف متاح لضمان سرعة وسهولة تواصل مندوب التوصيل.`;

    document.getElementById('inp-policy-payment').value = `• نوفر وسائل دفع متعددة وآمنة تناسب جميع الدول: الدفع نقداً عند الاستلام (كاش)، المحافظ الإلكترونية، إنستا باي، محفظة شام كاش، باي بال، وبطاقات الدفع الإلكتروني (فيزا / ماستركارد / مدى).
• جميع المعاملات البنكية والمدفوعات مشفرة ومحمية بأعلى بروتوكولات الأمان والحماية المصرفية والتشفير العالمي SSL.
• لا نقوم بحفظ أو تخزين أي بيانات للبطاقات الائتمانية أو الحسابات البنكية على خوادمنا نهائياً.`;

    document.getElementById('inp-policy-privacy').value = `• نلتزم التزاماً تاماً بحماية خصوصية وأمان بياناتك الشخصية والحفاظ على سريتها المطلقة.
• تُستخدم بياناتك المسجلة (الاسم، الهاتف، العنوان، البريد) فقط لغرض معالجة وتجهيز وتوصيل طلباتك وتقديم الدعم الفني.
• نتعهد بعدم بيع أو تأجير أو مشاركة بياناتك الشخصية مع أي جهة خارجية أو أطراف ثالثة لأغراض إعلانية.`;

    document.getElementById('inp-policy-terms').value = `• باستخدامك لهذا المتجر وإتمام أي طلب شراء، فإنك توافق على الالتزام بكافة الشروط والأحكام والسياسات المعلنة.
• نحتفظ بالحق في تعديل أو تحديث أسعار المنتجات والعروض الترويجية ومواصفات السلع وفقاً لحالة المخزون المتاح.
• كافة المحتويات والتصميمات والعلامات التجارية المنشورة على المتجر محمية بموجب حقوق الملكية الفكرية.`;
}
</script>

<?php
include 'footer.php';
?>

