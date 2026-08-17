<?php
$f_store_name = htmlspecialchars($settings['store_name'] ?? 'المتجر الإلكتروني');
$f_store_tagline = htmlspecialchars($settings['store_tagline'] ?? '');
$f_store_description = htmlspecialchars($settings['store_description'] ?? 'متجر إلكتروني متكامل يقدم تشكيلة متميزة من أفضل المنتجات بجودة عالية وأسعار منافسة مع تجربة تسوق سهلة وشحن سريع.');
?>
    <!-- ================= بانر تطبيق الهاتف ================= -->
    <div class="bg-royal-charcoal border-t border-b border-royal-gold/20 py-6">
        <div class="container mx-auto px-4 md:px-8 max-w-6xl flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-4 text-right">
                <div class="w-14 h-14 rounded-2xl bg-royal-gold/15 border border-royal-gold/30 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-mobile-screen-button text-royal-gold text-2xl"></i>
                </div>
                <div>
                    <h4 class="text-white font-bold text-sm font-serif">تسوق أسرع عبر تطبيق الهاتف!</h4>
                    <p class="text-gray-400 text-[11px] font-light mt-0.5">تجربة تسوق سلسة مع إشعارات فورية لحالة ومسار طلباتك 📱✨</p>
                </div>
            </div>
            <?php 
            $apk_file = file_exists('store-app.apk') ? 'store-app.apk' : (file_exists('HUDUE-app.apk') ? 'HUDUE-app.apk' : '#'); 
            ?>
            <a href="<?php echo $apk_file; ?>" download class="bg-royal-gold hover:bg-royal-darkgold text-royal-charcoal font-bold py-3 px-8 rounded-xl text-xs uppercase tracking-widest transition-all shadow-lg hover:shadow-xl flex items-center gap-2 shrink-0 btn-shine">
                <i class="fa-solid fa-download"></i> تثبيت التطبيق
            </a>
        </div>
    </div>

    <!-- ================= الفوتر ================= -->
    <footer class="bg-royal-charcoal border-t border-royal-gold/15 pt-16 pb-8 mt-auto text-white">
        <div class="container mx-auto px-4 md:px-8 max-w-6xl">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 mb-12">
                <div class="md:col-span-2">
                    <h2 class="font-serif text-2xl tracking-widest text-royal-gold uppercase mb-4 font-bold"><?php echo $f_store_name; ?></h2>
                    <p class="text-gray-400 text-sm leading-relaxed max-w-sm font-light">
                        <?php echo $f_store_description; ?>
                    </p>
                </div>
                <div>
                    <h4 class="font-bold text-royal-gold mb-4 text-sm uppercase tracking-wider">روابط سريعة</h4>
                    <ul class="space-y-2.5 text-sm text-gray-400">
                        <li><a href="shop.php" class="hover:text-royal-gold transition-colors flex items-center gap-1.5"><i class="fa-solid fa-angle-left text-[8px]"></i> تصفح المنتجات</a></li>
                        <li><a href="track.php" class="hover:text-royal-gold transition-colors flex items-center gap-1.5"><i class="fa-solid fa-angle-left text-[8px]"></i> تتبع حالة الطلب</a></li>
                        <li><a href="policies.php#returns" class="hover:text-royal-gold transition-colors flex items-center gap-1.5"><i class="fa-solid fa-angle-left text-[8px]"></i> سياسة الاستبدال والاسترجاع</a></li>
                        <li><a href="policies.php#shipping" class="hover:text-royal-gold transition-colors flex items-center gap-1.5"><i class="fa-solid fa-angle-left text-[8px]"></i> سياسة الشحن والتوصيل</a></li>
                        <li><a href="policies.php#privacy" class="hover:text-royal-gold transition-colors flex items-center gap-1.5"><i class="fa-solid fa-angle-left text-[8px]"></i> سياسة الخصوصية والشروط</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold text-royal-gold mb-4 text-sm uppercase tracking-wider">تواصل معنا</h4>
                    <ul class="space-y-3 text-sm text-gray-400">
                        <li dir="ltr" class="text-right flex items-center justify-end gap-2">
                            <span><?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?></span>
                            <i class="fa-brands fa-whatsapp text-royal-gold text-base"></i>
                        </li>
                        <li dir="ltr" class="text-right flex items-center justify-end gap-2">
                            <span><?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?></span>
                            <i class="fa-regular fa-envelope text-royal-gold text-base"></i>
                        </li>
                    </ul>
                    <div class="flex gap-4 mt-6 justify-end">
                        <a href="<?php echo htmlspecialchars($settings['social_whatsapp'] ?? '#'); ?>" target="_blank" class="w-8 h-8 rounded-full border border-gray-700 flex items-center justify-center text-gray-400 hover:text-royal-gold hover:border-royal-gold transition-all"><i class="fa-brands fa-whatsapp"></i></a>
                        <a href="<?php echo htmlspecialchars($settings['social_instagram'] ?? '#'); ?>" target="_blank" class="w-8 h-8 rounded-full border border-gray-700 flex items-center justify-center text-gray-400 hover:text-royal-gold hover:border-royal-gold transition-all"><i class="fa-brands fa-instagram"></i></a>
                        <a href="<?php echo htmlspecialchars($settings['social_facebook'] ?? '#'); ?>" target="_blank" class="w-8 h-8 rounded-full border border-gray-700 flex items-center justify-center text-gray-400 hover:text-royal-gold hover:border-royal-gold transition-all"><i class="fa-brands fa-facebook-f"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-800 pt-8 text-center text-xs text-gray-500 font-light flex flex-col md:flex-row justify-between items-center gap-4">
                <p>© <?php echo date('Y'); ?> <?php echo $f_store_name; ?>. جميع الحقوق محفوظة.</p>
                <div class="flex gap-3 mt-4 md:mt-0 opacity-40 text-2xl">
                    <i class="fa-brands fa-cc-visa"></i>
                    <i class="fa-brands fa-cc-mastercard"></i>
                    <i class="fa-solid fa-money-bill-1-wave"></i>
                </div>
            </div>
        </div>
    </footer>

    <!-- ================= ويدجيت المساعد الذكي العائم ================= -->
    <?php if(($settings['ai_chat_enabled'] ?? '1') == '1'): ?>
    <div id="ai-chat-widget" class="fixed bottom-24 left-6 lg:bottom-6 lg:left-6 z-[9999] font-sans">
        <!-- نافذة المحادثة -->
        <div id="ai-chat-window" class="absolute bottom-20 left-0 w-80 max-w-[90vw] bg-white border border-royal-gold/20 shadow-2xl rounded-2xl overflow-hidden hidden flex-col transform-origin-bottom-left transition-all duration-300">
            <!-- الهيدر -->
            <div class="bg-royal-charcoal text-royal-gold px-5 py-4 font-bold flex justify-between items-center border-b border-royal-gold/15">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse"></span>
                    <span class="text-sm font-semibold tracking-wider">المساعد الذكي للمتجر ✨</span>
                </div>
                <button onclick="toggleChat()" class="text-gray-400 hover:text-royal-gold transition-colors"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <!-- الرسائل -->
            <div class="h-80 overflow-y-auto p-4 bg-royal-cream flex flex-col gap-3.5 scroll-smooth" id="chat-body">
                <div class="bg-white text-gray-700 py-2.5 px-4 rounded-2xl rounded-tr-none text-xs leading-relaxed max-w-[85%] self-start shadow-sm border border-gray-100/50">
                    أهلاً بك في متجر <?php echo $f_store_name; ?>! أنا مساعدك الذكي. كيف يمكنني مساعدتك اليوم في اختيار أفضل المنتجات؟
                </div>
                <div class="hidden text-gray-400 text-xs italic p-2 self-start" id="typing-indicator">
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-spinner animate-spin text-royal-gold"></i> جاري الكتابة...</span>
                </div>
            </div>
            <!-- صندوق الإدخال -->
            <div class="p-3 bg-white border-t border-gray-100 flex gap-2 items-center">
                <input type="text" id="chat-input" class="flex-grow border border-gray-200 rounded-full py-2 px-4 text-xs focus:outline-none focus:border-royal-gold focus:ring-1 focus:ring-royal-gold/25" placeholder="اكتب سؤالك هنا..." onkeypress="if(event.key === 'Enter') sendAIMessage()">
                <button class="bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal w-9 h-9 rounded-full flex justify-center items-center cursor-pointer transition-all shadow-md shrink-0" onclick="sendAIMessage()"><i class="fa-solid fa-paper-plane text-xs"></i></button>
            </div>
        </div>
        
        <!-- الزر العائم -->
        <button id="ai-chat-button" onclick="toggleChat()" class="w-14 h-14 rounded-full bg-gold-gradient text-white flex items-center justify-center text-xl shadow-xl cursor-pointer hover:scale-110 transition-all duration-300 relative group animate-bounce">
            <i class="fa-solid fa-headset"></i>
            <span class="absolute right-16 bg-royal-charcoal text-royal-gold text-[10px] py-1 px-3 rounded-lg shadow-lg font-bold opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap border border-royal-gold/15">المساعد الذكي</span>
        </button>
    </div>

    <script>
        function toggleChat() {
            const win = document.getElementById('ai-chat-window');
            win.classList.toggle('hidden');
            win.classList.toggle('flex');
            if (win.classList.contains('flex')) {
                document.getElementById('chat-input').focus();
            }
        }

        function sendAIMessage() {
            var inputField = document.getElementById('chat-input');
            var msg = inputField.value.trim();
            if(!msg) return;

            var chatBody = document.getElementById('chat-body');
            var typingIndicator = document.getElementById('typing-indicator');

            // إضافة رسالة المستخدم
            var userDiv = document.createElement('div');
            userDiv.className = 'bg-royal-charcoal text-white py-2.5 px-4 rounded-2xl rounded-tl-none text-xs leading-relaxed max-w-[85%] self-end shadow-md';
            userDiv.textContent = msg;
            chatBody.insertBefore(userDiv, typingIndicator);
            
            inputField.value = '';
            typingIndicator.classList.remove('hidden');
            chatBody.scrollTop = chatBody.scrollHeight;

            // إرسال الطلب عبر Fetch API
            var formData = new FormData();
            formData.append('message', msg);

            fetch('ajax_chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                typingIndicator.classList.add('hidden');
                var botDiv = document.createElement('div');
                botDiv.className = 'bg-white text-gray-700 py-2.5 px-4 rounded-2xl rounded-tr-none text-xs leading-relaxed max-w-[85%] self-start shadow-sm border border-gray-100/50';
                botDiv.textContent = data.reply;
                chatBody.insertBefore(botDiv, typingIndicator);
                chatBody.scrollTop = chatBody.scrollHeight;
            })
            .catch(error => {
                typingIndicator.classList.add('hidden');
                var errDiv = document.createElement('div');
                errDiv.className = 'bg-red-50 text-red-700 py-2.5 px-4 rounded-2xl rounded-tr-none text-xs leading-relaxed max-w-[85%] self-start border border-red-100';
                errDiv.textContent = 'حدث خطأ أثناء الاتصال بالشبكة، يرجى إعادة المحاولة.';
                chatBody.insertBefore(errDiv, typingIndicator);
                chatBody.scrollTop = chatBody.scrollHeight;
            });
        }
    </script>
    <?php endif; ?>

    <?php
    $current_page = basename($_SERVER['PHP_SELF']);
    $cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'qty')) : 0;
    ?>
    <!-- شريط التنقل السفلي للهواتف (Mobile Bottom Navigation Bar) -->
    <div class="lg:hidden fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-md border-t border-royal-gold/20 shadow-[0_-4px_15px_rgba(0,0,0,0.06)] z-[9998] py-2.5 px-4 flex justify-around items-center">
        <a href="index.php" class="flex flex-col items-center gap-1 transition-all duration-200 <?php echo $current_page == 'index.php' ? 'text-royal-darkgold font-bold scale-105' : 'text-gray-400 hover:text-royal-gold'; ?>">
            <i class="fa-solid fa-house text-base"></i>
            <span class="text-[9px] font-sans">الرئيسية</span>
        </a>
        <a href="shop.php" class="flex flex-col items-center gap-1 transition-all duration-200 <?php echo $current_page == 'shop.php' ? 'text-royal-darkgold font-bold scale-105' : 'text-gray-400 hover:text-royal-gold'; ?>">
            <i class="fa-solid fa-store text-base"></i>
            <span class="text-[9px] font-sans">المتجر</span>
        </a>
        <a href="cart.php" class="relative flex flex-col items-center gap-1 transition-all duration-200 <?php echo $current_page == 'cart.php' ? 'text-royal-darkgold font-bold scale-105' : 'text-gray-400 hover:text-royal-gold'; ?>">
            <i class="fa-solid fa-bag-shopping text-base"></i>
            <?php if ($cart_count > 0): ?>
                <span class="absolute -top-1.5 -right-2.5 bg-royal-darkgold text-white text-[8px] font-extrabold w-4.5 h-4.5 flex items-center justify-center rounded-full border border-white"><?php echo $cart_count; ?></span>
            <?php endif; ?>
            <span class="text-[9px] font-sans">السلة</span>
        </a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="admin_orders.php" class="flex flex-col items-center gap-1 transition-all duration-200 <?php echo in_array($current_page, ['admin_orders.php', 'admin_products.php', 'admin_settings.php', 'admin_info.php', 'admin_shipping.php', 'admin_coupons.php']) ? 'text-royal-darkgold font-bold scale-105' : 'text-gray-400 hover:text-royal-gold'; ?>">
                    <i class="fa-solid fa-crown text-base"></i>
                    <span class="text-[9px] font-sans">الإدارة</span>
                </a>
            <?php else: ?>
                <a href="profile.php" class="flex flex-col items-center gap-1 transition-all duration-200 <?php echo $current_page == 'profile.php' ? 'text-royal-darkgold font-bold scale-105' : 'text-gray-400 hover:text-royal-gold'; ?>">
                    <i class="fa-regular fa-user text-base"></i>
                    <span class="text-[9px] font-sans">حسابي</span>
                </a>
            <?php endif; ?>
        <?php else: ?>
            <a href="login.php" class="flex flex-col items-center gap-1 transition-all duration-200 <?php echo $current_page == 'login.php' ? 'text-royal-darkgold font-bold scale-105' : 'text-gray-400 hover:text-royal-gold'; ?>">
                <i class="fa-regular fa-user text-base"></i>
                <span class="text-[9px] font-sans">حسابي</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- ================= مودال دليل تثبيت آيفون PWA ================= -->
    <div id="pwa-ios-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[99999] hidden flex items-center justify-center p-4">
        <div class="bg-white border border-royal-gold/25 w-full max-w-sm rounded-3xl overflow-hidden shadow-2xl relative text-right flex flex-col">
            <!-- الهيدر -->
            <div class="bg-royal-charcoal text-royal-gold px-6 py-4 font-bold flex justify-between items-center border-b border-royal-gold/15">
                <div class="flex items-center gap-2">
                    <i class="fa-brands fa-apple text-xl"></i>
                    <span class="text-xs font-semibold font-serif">تثبيت تطبيق <?php echo $f_store_name; ?> على الآيفون</span>
                </div>
                <button onclick="closePwaModal()" class="text-gray-400 hover:text-royal-gold transition-colors"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            
            <!-- المحتوى الداخلي للدليل -->
            <div class="p-6 space-y-5 text-gray-700 text-sm leading-relaxed overflow-y-auto max-h-[70vh]">
                <div class="text-center pb-2">
                    <?php if(!empty($settings['store_logo'])): ?>
                        <img src="<?php echo htmlspecialchars($settings['store_logo']); ?>" class="w-16 h-16 rounded-2xl mx-auto shadow-md border border-royal-gold/20 mb-3 object-contain" alt="Store Logo">
                    <?php endif; ?>
                    <p class="text-xs text-gray-500">تمتع بتجربة تسوق سريعة ومثالية ومتابعة فورية لحالة ومسار طلباتك ✨</p>
                </div>
                
                <div class="space-y-4">
                    <div class="flex gap-3 items-start">
                        <span class="w-6 h-6 rounded-full bg-royal-gold/15 border border-royal-gold/30 text-royal-darkgold font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">١</span>
                        <div>
                            <p class="font-bold text-xs text-royal-charcoal">افتح الموقع في متصفح Safari</p>
                            <p class="text-[11px] text-gray-500">تأكد من تصفح الموقع عبر Safari الرسمي لجهاز الآيفون.</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 items-start">
                        <span class="w-6 h-6 rounded-full bg-royal-gold/15 border border-royal-gold/30 text-royal-darkgold font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">٢</span>
                        <div>
                            <p class="font-bold text-xs text-royal-charcoal">اضغط على زر المشاركة</p>
                            <p class="text-[11px] text-gray-500">اضغط على أيقونة المشاركة في شريط سفاري السفلي <i class="fa-solid fa-share-from-square text-royal-gold mx-1 text-sm"></i> (Share).</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 items-start">
                        <span class="w-6 h-6 rounded-full bg-royal-gold/15 border border-royal-gold/30 text-royal-darkgold font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">٣</span>
                        <div>
                            <p class="font-bold text-xs text-royal-charcoal">اضغط "إضافة للشاشة الرئيسية"</p>
                            <p class="text-[11px] text-gray-500">مرر للأسفل قليلاً واختر **"إضافة إلى الشاشة الرئيسية"** (Add to Home Screen) <i class="fa-regular fa-plus-square text-royal-gold mx-1 text-sm"></i>.</p>
                        </div>
                    </div>

                    <div class="flex gap-3 items-start">
                        <span class="w-6 h-6 rounded-full bg-royal-gold/15 border border-royal-gold/30 text-royal-darkgold font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">٤</span>
                        <div>
                            <p class="font-bold text-xs text-royal-charcoal">انقر على "إضافة" (Add)</p>
                            <p class="text-[11px] text-gray-500">انقر على كلمة **"إضافة"** في أعلى اليمين. ستجد أيقونة التطبيق ظهرت على شاشتك كأي تطبيق رسمي!</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- الزر السفلي -->
            <div class="p-4 bg-gray-50 border-t border-gray-100 flex justify-end">
                <button onclick="closePwaModal()" class="bg-royal-charcoal hover:bg-royal-gold hover:text-royal-charcoal text-white font-bold py-2.5 px-6 rounded-xl text-xs transition-all">
                    فهمت ذلك
                </button>
            </div>
        </div>
    </div>

    <!-- Swiper.js (Sliders) -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <!-- PWA Installation and Notifications Script -->
    <script>
        // التحقق من نظام التشغيل (آيفون/آيباد)
        const isIOSDevice = /iPhone|iPad|iPod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const iosModal = document.getElementById('pwa-ios-modal');

        // البحث عن أي أزرار تنزيل وتعديل سلوكها على الآيفون لفتح الدليل
        document.querySelectorAll('a[href="HUDUE-app.apk"], a[href="app.apk"]').forEach(btn => {
            if (isIOSDevice) {
                btn.innerHTML = '<i class="fa-brands fa-apple"></i> تثبيت على الآيفون';
                btn.removeAttribute('download');
                btn.setAttribute('href', '#');
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (iosModal) {
                        iosModal.classList.remove('hidden');
                    }
                });
            }
        });

        function closePwaModal() {
            if (iosModal) {
                iosModal.classList.add('hidden');
            }
        }

        // تسجيل الـ Service Worker للـ PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('service-worker.js')
                    .then(reg => console.log('Service Worker registered!', reg))
                    .catch(err => console.error('Service Worker registration failed', err));
            });
        }

        // تشغيل فحص الإشعارات الدورية داخل الـ PWA
        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
            // طلب إذن الإشعارات من متصفح سفاري
            if (Notification.permission === 'default') {
                Notification.requestPermission();
            }

            // فحص الإشعارات كل 60 ثانية أثناء تصفح التطبيق
            if (Notification.permission === 'granted') {
                setInterval(() => {
                    let lastNotifId = localStorage.getItem('pwa_last_notif_id') || 0;
                    fetch('api_notifications.php?limit=5')
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.notifications.length > 0) {
                                let maxId = lastNotifId;
                                for (let i = data.notifications.length - 1; i >= 0; i--) {
                                    let n = data.notifications[i];
                                    if (n.id > lastNotifId) {
                                        new Notification(n.title, {
                                            body: n.body,
                                            icon: 'uploads/logo_180.png'
                                        });
                                        if (n.id > maxId) {
                                            maxId = n.id;
                                        }
                                    }
                                }
                                localStorage.setItem('pwa_last_notif_id', maxId);
                            }
                        })
                        .catch(err => console.log('Notification sync error:', err));
                }, 60000);
            }
        }
    </script>
</body>
</html>
