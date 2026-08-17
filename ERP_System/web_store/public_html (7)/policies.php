<?php
require_once 'config.php';

$page_title = 'سياسات وشروط المتجر';
include 'header.php';

// نصوص السياسات من قاعدة البيانات مع نصوص افتراضية جاهزة
$p_return = !empty($settings['policy_return']) ? $settings['policy_return'] : "• يحق للعميل طلب الاستبدال أو الاسترجاع خلال 14 يوماً من تاريخ استلام الشحنة وفقاً لأحكام حماية المستهلك.\n• يشترط أن يكون المنتج في حالته الأصلية وبغلافه الأصلي غير المفتوح أو المستخدم مع كامل ملحقاته وفاتورة الشراء.\n• في حالة وجود عيب مصنعي أو خطأ في المنتج المستلم، يتحمل المتجر كافة تكاليف الشحن والإرجاع بالكامل دون أي رسوم على العميل.\n• لا تقبل المنتجات ذات الاستخدام الشخصي أو المخصصة بطلب خاص بعد فتح غلافها المغلق حفاظاً على الصحة العامة.\n• يتم فحص المنتج فور وصوله لمستودعاتنا ويتم استرداد المبلغ خلال 3 إلى 5 أيام عمل عبر نفس وسيلة الدفع أو التحويل البنكي / المحافظ الإلكترونية.";

$p_shipping = !empty($settings['policy_shipping']) ? $settings['policy_shipping'] : "• نوفر خدمات الشحن السريع والتوصيل المباشر لكافة المحافظات والمدن والدول العربية المعتمدة.\n• يستغرق التوصيل عادةً من 24 إلى 72 ساعة داخل المدن الرئيسية، ومن 3 إلى 5 أيام عمل لباقي المناطق أو الشحن الدولي.\n• يتم تزويد العميل برقم تتبع فور تجهيز الشحنة مع إمكانية متابعة خط سير الطلب لحظياً عبر صفحة 'تتبع طلبك'.\n• يرجى التأكد من كتابة العنوان بالتفصيل ورقم هاتف متاح لضمان سرعة وسهولة تواصل مندوب التوصيل.";

$p_payment = !empty($settings['policy_payment']) ? $settings['policy_payment'] : "• نوفر وسائل دفع متعددة وآمنة تناسب جميع الدول: الدفع نقداً عند الاستلام (كاش)، المحافظ الإلكترونية، إنستا باي، محفظة شام كاش، باي بال، وبطاقات الدفع الإلكتروني (فيزا / ماستركارد / مدى).\n• جميع المعاملات البنكية والمدفوعات مشفرة ومحمية بأعلى بروتوكولات الأمان والحماية المصرفية والتشفير العالمي SSL.\n• لا نقوم بحفظ أو تخزين أي بيانات للبطاقات الائتمانية أو الحسابات البنكية على خوادمنا نهائياً.";

$p_privacy = !empty($settings['policy_privacy']) ? $settings['policy_privacy'] : "• نلتزم التزاماً تاماً بحماية خصوصية وأمان بياناتك الشخصية والحفاظ على سريتها المطلقة.\n• تُستخدم بياناتك المسجلة (الاسم، الهاتف، العنوان، البريد) فقط لغرض معالجة وتجهيز وتوصيل طلباتك وتقديم الدعم الفني.\n• نتعهد بعدم بيع أو تأجير أو مشاركة بياناتك الشخصية مع أي جهة خارجية أو أطراف ثالثة لأغراض إعلانية.";

$p_terms = !empty($settings['policy_terms']) ? $settings['policy_terms'] : "• باستخدامك لهذا المتجر وإتمام أي طلب شراء، فإنك توافق على الالتزام بكافة الشروط والأحكام والسياسات المعلنة.\n• نحتفظ بالحق في تعديل أو تحديث أسعار المنتجات والعروض الترويجية ومواصفات السلع وفقاً لحالة المخزون المتاح.\n• كافة المحتويات والتصميمات والعلامات التجارية المنشورة على المتجر محمية بموجب حقوق الملكية الفكرية.";

// دالة مساعدة لتنسيق النصوص مع النقاط والقوائم
function formatPolicyContent($rawText) {
    $lines = explode("\n", trim($rawText));
    $output = '<ul class="space-y-3.5">';
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // إذا كان السطر يبدأ بنقطة أو شرطة
        $cleanLine = ltrim($line, "•-—* ");
        $output .= '<li class="flex items-start gap-3 leading-relaxed text-gray-700 text-xs sm:text-sm">
            <span class="w-2 h-2 rounded-full bg-royal-gold shrink-0 mt-2"></span>
            <span>' . htmlspecialchars($cleanLine) . '</span>
        </li>';
    }
    $output .= '</ul>';
    return $output;
}
?>

<!-- هيدر الصفحة والترويسة -->
<div class="bg-gradient-to-b from-royal-sand/60 to-white py-14 border-b border-royal-gold/10 text-center">
    <div class="container mx-auto px-4 max-w-4xl animate-fade-in">
        <span class="bg-royal-gold/15 text-royal-darkgold border border-royal-gold/30 text-[11px] font-bold px-3.5 py-1 rounded-full uppercase tracking-wider inline-block mb-3">
            <i class="fa-solid fa-shield-halved ml-1"></i> الشفافية والأمان
        </span>
        <h1 class="text-3xl sm:text-4xl font-serif font-bold text-royal-dark mb-3">
            سياسات وشروط المتجر
        </h1>
        <p class="text-xs sm:text-sm text-gray-500 max-w-xl mx-auto font-light leading-relaxed">
            نلتزم بتقديم تجربة تسوق موثوقة وآمنة وفقاً لأعلى معايير حماية المستهلك والسرية التامة.
        </p>
    </div>
</div>

<div class="container mx-auto px-4 md:px-8 py-12 max-w-6xl animate-fade-in">
    <div class="flex flex-col lg:flex-row gap-8">
        
        <!-- القائمة الجانبية للتنقل السريع (Desktop Sidebar) -->
        <aside class="w-full lg:w-1/4">
            <div class="bg-white p-5 rounded-2xl border border-royal-gold/15 shadow-sm sticky top-28 space-y-2">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 px-2">فهرس السياسات</h3>
                
                <a href="#returns" class="policy-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold text-gray-700 hover:bg-royal-sand/40 hover:text-royal-darkgold transition-all">
                    <i class="fa-solid fa-arrow-rotate-left text-royal-darkgold w-5 text-center"></i>
                    <span>الاستبدال والاسترجاع</span>
                </a>
                
                <a href="#shipping" class="policy-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold text-gray-700 hover:bg-royal-sand/40 hover:text-royal-darkgold transition-all">
                    <i class="fa-solid fa-truck-fast text-royal-darkgold w-5 text-center"></i>
                    <span>الشحن والتوصيل</span>
                </a>

                <a href="#payments" class="policy-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold text-gray-700 hover:bg-royal-sand/40 hover:text-royal-darkgold transition-all">
                    <i class="fa-solid fa-credit-card text-royal-darkgold w-5 text-center"></i>
                    <span>طرق الدفع والأمان</span>
                </a>

                <a href="#privacy" class="policy-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold text-gray-700 hover:bg-royal-sand/40 hover:text-royal-darkgold transition-all">
                    <i class="fa-solid fa-user-shield text-royal-darkgold w-5 text-center"></i>
                    <span>الخصوصية والسرية</span>
                </a>

                <a href="#terms" class="policy-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold text-gray-700 hover:bg-royal-sand/40 hover:text-royal-darkgold transition-all">
                    <i class="fa-solid fa-scale-balanced text-royal-darkgold w-5 text-center"></i>
                    <span>الشروط والأحكام العامة</span>
                </a>

                <div class="pt-4 border-t border-gray-100 mt-4">
                    <div class="bg-royal-cream/40 p-3.5 rounded-xl text-center space-y-2">
                        <p class="text-[11px] font-bold text-royal-dark">هل لديك استفسار خاص؟</p>
                        <p class="text-[10px] text-gray-500 font-light">فريق الدعم الفني جاهز لمساعدتك على مدار الساعة.</p>
                        <?php if (!empty($settings['social_whatsapp']) || !empty($settings['contact_phone'])): 
                            $wa = !empty($settings['social_whatsapp']) ? $settings['social_whatsapp'] : 'https://wa.me/' . preg_replace('/[^0-9]/', '', $settings['contact_phone']);
                        ?>
                            <a href="<?php echo htmlspecialchars($wa); ?>" target="_blank" class="inline-flex items-center justify-center gap-1.5 w-full bg-[#25D366] text-white text-xs font-bold py-2 rounded-lg shadow-sm hover:scale-105 transition-all">
                                <i class="fa-brands fa-whatsapp text-sm"></i> محادثة واتساب
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </aside>

        <!-- المحتوى الرئيسي للسياسات (Main Content) -->
        <main class="w-full lg:w-3/4 space-y-8">
            
            <!-- 1. قسم سياسة الاستبدال والاسترجاع -->
            <section id="returns" class="bg-white p-6 sm:p-8 rounded-2xl border border-royal-gold/15 shadow-sm scroll-mt-28">
                <div class="flex items-center justify-between border-b border-royal-gold/10 pb-4 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center text-lg shadow-xs">
                            <i class="fa-solid fa-arrow-rotate-left"></i>
                        </div>
                        <div>
                            <h2 class="text-base sm:text-lg font-serif font-bold text-royal-dark">سياسة الاستبدال والاسترجاع</h2>
                            <p class="text-[11px] text-gray-400 font-light">حقوق الإرجاع واسترداد المبالغ المالية</p>
                        </div>
                    </div>
                    <span class="bg-green-50 text-green-700 text-[10px] font-bold px-2.5 py-1 rounded-full border border-green-200">
                        ضمان 14 يوم
                    </span>
                </div>

                <div class="prose max-w-none">
                    <?php echo formatPolicyContent($p_return); ?>
                </div>

                <div class="mt-6 bg-amber-50/60 p-4 rounded-xl border border-amber-200/60 text-amber-900 text-xs flex items-start gap-3">
                    <i class="fa-solid fa-circle-info text-amber-600 text-base shrink-0 mt-0.5"></i>
                    <p class="leading-relaxed">
                        لبدء طلب استرجاع أو استبدال، يرجى التواصل مع فريق خدمة العملاء مع تجهيز رقم الطلب وصورة للمنتج المستلم.
                    </p>
                </div>
            </section>

            <!-- 2. قسم سياسة الشحن والتوصيل -->
            <section id="shipping" class="bg-white p-6 sm:p-8 rounded-2xl border border-royal-gold/15 shadow-sm scroll-mt-28">
                <div class="flex items-center justify-between border-b border-royal-gold/10 pb-4 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center text-lg shadow-xs">
                            <i class="fa-solid fa-truck-fast"></i>
                        </div>
                        <div>
                            <h2 class="text-base sm:text-lg font-serif font-bold text-royal-dark">سياسة الشحن والتوصيل</h2>
                            <p class="text-[11px] text-gray-400 font-light">مواعيد الشحن والتسليم للمحافظات والدول</p>
                        </div>
                    </div>
                    <a href="track.php" class="bg-royal-sand hover:bg-royal-gold hover:text-white text-royal-dark text-[11px] font-bold px-3 py-1.5 rounded-xl transition shadow-sm flex items-center gap-1.5">
                        <i class="fa-solid fa-location-crosshairs text-royal-darkgold"></i> تتبع شحنتك
                    </a>
                </div>

                <div class="prose max-w-none">
                    <?php echo formatPolicyContent($p_shipping); ?>
                </div>
            </section>

            <!-- 3. قسم سياسة وطرق الدفع والأمان -->
            <section id="payments" class="bg-white p-6 sm:p-8 rounded-2xl border border-royal-gold/15 shadow-sm scroll-mt-28">
                <div class="flex items-center justify-between border-b border-royal-gold/10 pb-4 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-lg shadow-xs">
                            <i class="fa-solid fa-credit-card"></i>
                        </div>
                        <div>
                            <h2 class="text-base sm:text-lg font-serif font-bold text-royal-dark">سياسة وطرق الدفع والأمان</h2>
                            <p class="text-[11px] text-gray-400 font-light">خيارات السداد وحماية المعاملات المالية</p>
                        </div>
                    </div>
                    <span class="bg-emerald-50 text-emerald-700 text-[10px] font-bold px-2.5 py-1 rounded-full border border-emerald-200 flex items-center gap-1">
                        <i class="fa-solid fa-lock text-[9px]"></i> تشفير SSL 256-bit
                    </span>
                </div>

                <div class="prose max-w-none">
                    <?php echo formatPolicyContent($p_payment); ?>
                </div>
            </section>

            <!-- 4. قسم سياسة الخصوصية وسرية المعلومات -->
            <section id="privacy" class="bg-white p-6 sm:p-8 rounded-2xl border border-royal-gold/15 shadow-sm scroll-mt-28">
                <div class="flex items-center justify-between border-b border-royal-gold/10 pb-4 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center text-lg shadow-xs">
                            <i class="fa-solid fa-user-shield"></i>
                        </div>
                        <div>
                            <h2 class="text-base sm:text-lg font-serif font-bold text-royal-dark">سياسة الخصوصية وحماية البيانات</h2>
                            <p class="text-[11px] text-gray-400 font-light">سرية بياناتك الشخصية وحقوق الخصوصية</p>
                        </div>
                    </div>
                    <span class="bg-purple-50 text-purple-700 text-[10px] font-bold px-2.5 py-1 rounded-full border border-purple-200">
                        سرية تامة 100%
                    </span>
                </div>

                <div class="prose max-w-none">
                    <?php echo formatPolicyContent($p_privacy); ?>
                </div>
            </section>

            <!-- 5. قسم الشروط والأحكام العامة -->
            <section id="terms" class="bg-white p-6 sm:p-8 rounded-2xl border border-royal-gold/15 shadow-sm scroll-mt-28">
                <div class="flex items-center justify-between border-b border-royal-gold/10 pb-4 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gray-100 text-gray-700 flex items-center justify-center text-lg shadow-xs">
                            <i class="fa-solid fa-scale-balanced"></i>
                        </div>
                        <div>
                            <h2 class="text-base sm:text-lg font-serif font-bold text-royal-dark">الشروط والأحكام العامة للمتجر</h2>
                            <p class="text-[11px] text-gray-400 font-light">قواعد الاستخدام والاتفاقية القانونية</p>
                        </div>
                    </div>
                </div>

                <div class="prose max-w-none">
                    <?php echo formatPolicyContent($p_terms); ?>
                </div>
            </section>

        </main>
    </div>
</div>

<?php
include 'footer.php';
?>
