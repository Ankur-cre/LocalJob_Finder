<?php
/**
 * Language & Translation Engine
 * Supports: en, hi, ur, bn, ta, te, mr, gu, kn, ml
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DEFAULT_LANG', 'en');
define('SUPPORTED_LANGS', ['en','hi','ur','bn','ta','te','mr','gu','kn','ml']);

define('LANG_NAMES', [
    'en' => 'English',
    'hi' => 'हिन्दी',
    'ur' => 'اردو',
    'bn' => 'বাংলা',
    'ta' => 'தமிழ்',
    'te' => 'తెలుగు',
    'mr' => 'मराठी',
    'gu' => 'ગુજરાતી',
    'kn' => 'ಕನ್ನಡ',
    'ml' => 'മലയാളം',
]);

define('RTL_LANGS', ['ur']);

// ============================================================
// TRANSLATION DICTIONARY
// ============================================================
$GLOBALS['TRANSLATIONS'] = [
    // ===== ENGLISH =====
    'en' => [
        'nav.home' => 'Home',
        'nav.browse_jobs' => 'Browse Jobs',
        'nav.my_apps' => 'My Applications',
        'nav.post_job' => 'Post Job',
        'nav.dashboard' => 'Dashboard',
        'nav.logout' => 'Logout',
        'nav.signin' => 'Sign in',
        'nav.login' => 'Login',
        'nav.get_started' => 'Get Started',
        'nav.for_employers' => 'For Employers',
        'nav.for_jobseekers' => 'For Job Seekers',
        'nav.find_cvs' => 'Find CVs',
        'nav.products' => 'Products',
        'nav.pricing' => 'Pricing',
        'nav.resources' => 'Resources',
        'nav.search_placeholder' => 'Search jobs...',
        'nav.admin_panel' => 'Admin Panel',
        'lang.label' => 'Language',
        
        'seeker.welcome' => 'Welcome back',
        'seeker.new_jobs' => 'New Jobs (7 days)',
        'seeker.total_jobs' => 'Total Active Jobs',
        'seeker.my_apps' => 'My Applications',
        'seeker.shortlisted' => 'Shortlisted',
        'seeker.hired' => 'Hired',
        'seeker.latest_jobs' => 'Latest Jobs',
        'seeker.view_all' => 'View All',
        'seeker.recent_apps' => 'My Recent Applications',
        'seeker.apply_now' => 'Apply Now',
        'seeker.view_details' => 'View Details',
        'seeker.applied' => 'Applied',
        'seeker.job' => 'Job',
        'seeker.company' => 'Company',
        'seeker.applied_date' => 'Applied',
        'seeker.status' => 'Status',
        'seeker.no_jobs' => 'No active jobs right now',
        'seeker.check_back' => 'Check back soon — employers are posting daily.',
        'seeker.browse_all' => 'Browse All Jobs',
        'seeker.new_badge' => 'NEW',
        
        'job.about_role' => 'About the Role',
        'job.overview' => 'Job Overview',
        'job.company_info' => 'About the Company',
        'job.job_type' => 'Job Type',
        'job.category' => 'Category',
        'job.location' => 'Location',
        'job.salary' => 'Salary',
        'job.posted_on' => 'Posted On',
        'job.contact' => 'Contact Phone',
        'job.apply_title' => 'Apply for this Job',
        'job.apply_desc' => 'Upload your resume (optional) and submit.',
        'job.resume' => 'Resume',
        'job.resume_optional' => '(Optional — PDF/DOC/JPG, max 5MB)',
        'job.submit_app' => 'Submit Application',
        'job.already_applied' => "You've already applied!",
        'job.app_status' => 'Your application status',
        'job.view_apps' => 'View My Applications',
        'job.back_to_jobs' => 'Back to Jobs',
        'job.not_found' => 'Job not found',
        'job.not_found_desc' => 'This job has been deleted, closed, or is not active.',
        'job.contact_person' => 'Contact Person',
        'job.address' => 'Address',
        'job.phone' => 'Phone',
        'job.email' => 'Email',
        'job.member_since' => 'Member Since',
        
        'auth.welcome_back' => 'Welcome Back',
        'auth.login_subtitle' => 'Login to continue to your dashboard',
        'auth.email' => 'Email Address',
        'auth.password' => 'Password',
        'auth.login_btn' => 'Login',
        'auth.new_here' => 'New here?',
        'auth.register_seeker' => 'Register as Job Seeker',
        'auth.register_employer' => 'Register as Employer',
        'auth.admin_login' => 'Admin? Login here',
        
        'footer.tagline' => 'Find Real Jobs in Your Town',
        'footer.about' => 'Local Jobs helps job seekers discover employment opportunities in their nearby locations and helps employers connect with suitable candidates.',
        'footer.for_seekers' => 'For Job Seekers',
        'footer.for_employers' => 'For Employers',
        'footer.help_support' => 'Help & Support',
        'footer.legal' => 'Legal',
        'footer.browse_jobs' => 'Browse Jobs',
        'footer.job_categories' => 'Job Categories',
        'footer.how_it_works' => 'How It Works',
        'footer.create_account' => 'Create Account',
        'footer.my_home' => 'My Home',
        'footer.employer_reg' => 'Employer Registration',
        'footer.employer_login' => 'Employer Login',
        'footer.employer_guide' => 'Employer Guidelines',
        'footer.about_us' => 'About Us',
        'footer.faq' => 'Help & FAQ',
        'footer.contact' => 'Contact Us',
        'footer.safety' => 'Safety Guidelines',
        'footer.report' => 'Report a Problem',
        'footer.privacy' => 'Privacy Policy',
        'footer.terms' => 'Terms of Use',
        'footer.cookie' => 'Cookie Policy',
        'footer.refund' => 'Refund Policy',
        'footer.sitemap' => 'Sitemap',
        'footer.rights' => 'All rights reserved',
    ],

    // ===== HINDI =====
    'hi' => [
        'nav.home' => 'होम',
        'nav.browse_jobs' => 'नौकरियां देखें',
        'nav.my_apps' => 'मेरे आवेदन',
        'nav.post_job' => 'नौकरी पोस्ट करें',
        'nav.dashboard' => 'डैशबोर्ड',
        'nav.logout' => 'लॉग आउट',
        'nav.signin' => 'साइन इन',
        'nav.login' => 'लॉगिन',
        'nav.get_started' => 'शुरू करें',
        'nav.for_employers' => 'नियोक्ताओं के लिए',
        'nav.for_jobseekers' => 'नौकरी चाहने वालों के लिए',
        'nav.find_cvs' => 'CV खोजें',
        'nav.products' => 'उत्पाद',
        'nav.pricing' => 'मूल्य',
        'nav.resources' => 'संसाधन',
        'nav.search_placeholder' => 'नौकरियां खोजें...',
        'nav.admin_panel' => 'एडमिन पैनल',
        'lang.label' => 'भाषा',
        
        'seeker.welcome' => 'वापसी पर स्वागत है',
        'seeker.new_jobs' => 'नई नौकरियां (7 दिन)',
        'seeker.total_jobs' => 'कुल सक्रिय नौकरियां',
        'seeker.my_apps' => 'मेरे आवेदन',
        'seeker.shortlisted' => 'शॉर्टलिस्ट',
        'seeker.hired' => 'नियुक्त',
        'seeker.latest_jobs' => 'नवीनतम नौकरियां',
        'seeker.view_all' => 'सभी देखें',
        'seeker.recent_apps' => 'मेरे हाल के आवेदन',
        'seeker.apply_now' => 'अभी आवेदन करें',
        'seeker.view_details' => 'विवरण देखें',
        'seeker.applied' => 'आवेदन किया',
        'seeker.job' => 'नौकरी',
        'seeker.company' => 'कंपनी',
        'seeker.applied_date' => 'आवेदन तिथि',
        'seeker.status' => 'स्थिति',
        'seeker.no_jobs' => 'अभी कोई सक्रिय नौकरी नहीं है',
        'seeker.check_back' => 'जल्द वापस देखें — नियोक्ता रोज़ाना पोस्ट करते हैं।',
        'seeker.browse_all' => 'सभी नौकरियां देखें',
        'seeker.new_badge' => 'नया',
        
        'job.about_role' => 'भूमिका के बारे में',
        'job.overview' => 'नौकरी का अवलोकन',
        'job.company_info' => 'कंपनी के बारे में',
        'job.job_type' => 'नौकरी का प्रकार',
        'job.category' => 'श्रेणी',
        'job.location' => 'स्थान',
        'job.salary' => 'वेतन',
        'job.posted_on' => 'पोस्ट किया गया',
        'job.contact' => 'संपर्क फोन',
        'job.apply_title' => 'इस नौकरी के लिए आवेदन करें',
        'job.apply_desc' => 'अपना रिज़्यूमे अपलोड करें (वैकल्पिक) और सबमिट करें।',
        'job.resume' => 'रिज़्यूमे',
        'job.resume_optional' => '(वैकल्पिक — PDF/DOC/JPG, अधिकतम 5MB)',
        'job.submit_app' => 'आवेदन सबमिट करें',
        'job.already_applied' => 'आपने पहले ही आवेदन कर दिया है!',
        'job.app_status' => 'आपके आवेदन की स्थिति',
        'job.view_apps' => 'मेरे आवेदन देखें',
        'job.back_to_jobs' => 'नौकरियों पर वापस',
        'job.not_found' => 'नौकरी नहीं मिली',
        'job.not_found_desc' => 'यह नौकरी हटा दी गई है, बंद है, या सक्रिय नहीं है।',
        'job.contact_person' => 'संपर्क व्यक्ति',
        'job.address' => 'पता',
        'job.phone' => 'फोन',
        'job.email' => 'ईमेल',
        'job.member_since' => 'सदस्यता से',
        
        'auth.welcome_back' => 'वापसी पर स्वागत',
        'auth.login_subtitle' => 'अपने डैशबोर्ड पर जाने के लिए लॉगिन करें',
        'auth.email' => 'ईमेल पता',
        'auth.password' => 'पासवर्ड',
        'auth.login_btn' => 'लॉगिन',
        'auth.new_here' => 'नए हैं?',
        'auth.register_seeker' => 'नौकरी चाहने वाले के रूप में पंजीकरण',
        'auth.register_employer' => 'नियोक्ता के रूप में पंजीकरण',
        'auth.admin_login' => 'एडमिन? यहां लॉगिन करें',
        
        'footer.tagline' => 'अपने शहर में वास्तविक नौकरियां खोजें',
        'footer.about' => 'लोकल जॉब्स नौकरी चाहने वालों को उनके आस-पास रोजगार के अवसर खोजने में मदद करता है और नियोक्ताओं को उपयुक्त उम्मीदवारों से जोड़ता है।',
        'footer.for_seekers' => 'नौकरी चाहने वालों के लिए',
        'footer.for_employers' => 'नियोक्ताओं के लिए',
        'footer.help_support' => 'सहायता और समर्थन',
        'footer.legal' => 'कानूनी',
        'footer.browse_jobs' => 'नौकरियां देखें',
        'footer.job_categories' => 'नौकरी श्रेणियां',
        'footer.how_it_works' => 'यह कैसे काम करता है',
        'footer.create_account' => 'खाता बनाएं',
        'footer.my_home' => 'मेरा होम',
        'footer.employer_reg' => 'नियोक्ता पंजीकरण',
        'footer.employer_login' => 'नियोक्ता लॉगिन',
        'footer.employer_guide' => 'नियोक्ता दिशानिर्देश',
        'footer.about_us' => 'हमारे बारे में',
        'footer.faq' => 'सहायता और FAQ',
        'footer.contact' => 'संपर्क करें',
        'footer.safety' => 'सुरक्षा दिशानिर्देश',
        'footer.report' => 'समस्या की रिपोर्ट करें',
        'footer.privacy' => 'गोपनीयता नीति',
        'footer.terms' => 'उपयोग की शर्तें',
        'footer.cookie' => 'कुकी नीति',
        'footer.refund' => 'वापसी नीति',
        'footer.sitemap' => 'साइटमैप',
        'footer.rights' => 'सर्वाधिकार सुरक्षित',
    ],

    // ===== URDU =====
    'ur' => [
        'nav.home' => 'ہوم',
        'nav.browse_jobs' => 'نوکریاں دیکھیں',
        'nav.my_apps' => 'میری درخواستیں',
        'nav.post_job' => 'نوکری پوسٹ کریں',
        'nav.dashboard' => 'ڈیش بورڈ',
        'nav.logout' => 'لاگ آؤٹ',
        'nav.signin' => 'سائن ان',
        'nav.login' => 'لاگ ان',
        'nav.get_started' => 'شروع کریں',
        'nav.for_employers' => 'آجرین کے لیے',
        'nav.for_jobseekers' => 'ملازمت کے متلاشیوں کے لیے',
        'nav.find_cvs' => 'سی وی تلاش کریں',
        'nav.search_placeholder' => 'نوکریاں تلاش کریں...',
        'lang.label' => 'زبان',
        'seeker.welcome' => 'خوش آمدید',
        'seeker.new_jobs' => 'نئی نوکریاں (7 دن)',
        'seeker.total_jobs' => 'کل فعال نوکریاں',
        'seeker.my_apps' => 'میری درخواستیں',
        'seeker.shortlisted' => 'شارٹ لسٹ',
        'seeker.hired' => 'بھرتی',
        'seeker.latest_jobs' => 'تازہ ترین نوکریاں',
        'seeker.view_all' => 'سب دیکھیں',
        'seeker.apply_now' => 'ابھی درخواست دیں',
        'seeker.view_details' => 'تفصیلات دیکھیں',
        'auth.email' => 'ای میل',
        'auth.password' => 'پاس ورڈ',
        'auth.login_btn' => 'لاگ ان',
    ],

    // ===== BENGALI =====
    'bn' => [
        'nav.home' => 'হোম',
        'nav.browse_jobs' => 'চাকরি দেখুন',
        'nav.my_apps' => 'আমার আবেদন',
        'nav.post_job' => 'চাকরি পোস্ট করুন',
        'nav.dashboard' => 'ড্যাশবোর্ড',
        'nav.logout' => 'লগ আউট',
        'nav.signin' => 'সাইন ইন',
        'nav.login' => 'লগইন',
        'nav.get_started' => 'শুরু করুন',
        'nav.for_employers' => 'নিয়োগকর্তাদের জন্য',
        'nav.for_jobseekers' => 'চাকরিপ্রার্থীদের জন্য',
        'nav.search_placeholder' => 'চাকরি খুঁজুন...',
        'lang.label' => 'ভাষা',
        'seeker.welcome' => 'স্বাগতম',
        'seeker.new_jobs' => 'নতুন চাকরি (7 দিন)',
        'seeker.total_jobs' => 'মোট সক্রিয় চাকরি',
        'seeker.my_apps' => 'আমার আবেদন',
        'seeker.latest_jobs' => 'সর্বশেষ চাকরি',
        'seeker.view_all' => 'সব দেখুন',
        'seeker.apply_now' => 'এখনই আবেদন করুন',
        'seeker.view_details' => 'বিস্তারিত দেখুন',
        'auth.email' => 'ইমেইল',
        'auth.password' => 'পাসওয়ার্ড',
        'auth.login_btn' => 'লগইন',
    ],

    // ===== TAMIL =====
    'ta' => [
        'nav.home' => 'முகப்பு',
        'nav.browse_jobs' => 'வேலைகளை பார்',
        'nav.my_apps' => 'என் விண்ணப்பங்கள்',
        'nav.post_job' => 'வேலை இடுக',
        'nav.dashboard' => 'டாஷ்போர்டு',
        'nav.logout' => 'வெளியேறு',
        'nav.signin' => 'உள்நுழை',
        'nav.login' => 'உள்நுழைவு',
        'nav.get_started' => 'தொடங்கு',
        'nav.for_employers' => 'வேலை வழங்குபவர்கள்',
        'nav.for_jobseekers' => 'வேலை தேடுபவர்கள்',
        'nav.search_placeholder' => 'வேலை தேடு...',
        'lang.label' => 'மொழி',
        'seeker.welcome' => 'மீண்டும் வரவேற்கிறோம்',
        'seeker.new_jobs' => 'புதிய வேலைகள் (7 நாட்கள்)',
        'seeker.my_apps' => 'என் விண்ணப்பங்கள்',
        'auth.email' => 'மின்னஞ்சல்',
        'auth.password' => 'கடவுச்சொல்',
        'auth.login_btn' => 'உள்நுழைவு',
    ],

    // ===== TELUGU =====
    'te' => [
        'nav.home' => 'హోమ్',
        'nav.browse_jobs' => 'ఉద్యోగాలు చూడండి',
        'nav.my_apps' => 'నా దరఖాస్తులు',
        'nav.post_job' => 'ఉద్యోగం పోస్ట్ చేయండి',
        'nav.dashboard' => 'డాష్‌బోర్డ్',
        'nav.logout' => 'లాగ్ అవుట్',
        'nav.signin' => 'సైన్ ఇన్',
        'nav.login' => 'లాగిన్',
        'nav.get_started' => 'ప్రారంభించండి',
        'nav.for_employers' => 'యజమానుల కోసం',
        'nav.for_jobseekers' => 'ఉద్యోగార్థుల కోసం',
        'nav.search_placeholder' => 'ఉద్యోగాలు వెతకండి...',
        'lang.label' => 'భాష',
        'seeker.welcome' => 'స్వాగతం',
        'seeker.new_jobs' => 'కొత్త ఉద్యోగాలు (7 రోజులు)',
        'seeker.my_apps' => 'నా దరఖాస్తులు',
        'auth.email' => 'ఇమెయిల్',
        'auth.password' => 'పాస్వర్డ్',
        'auth.login_btn' => 'లాగిన్',
    ],

    // ===== MARATHI =====
    'mr' => [
        'nav.home' => 'मुख्यपृष्ठ',
        'nav.browse_jobs' => 'नोकरी पहा',
        'nav.my_apps' => 'माझे अर्ज',
        'nav.post_job' => 'नोकरी पोस्ट करा',
        'nav.dashboard' => 'डॅशबोर्ड',
        'nav.logout' => 'लॉग आउट',
        'nav.signin' => 'साइन इन',
        'nav.login' => 'लॉगिन',
        'nav.get_started' => 'सुरू करा',
        'nav.for_employers' => 'नियोक्त्यांसाठी',
        'nav.for_jobseekers' => 'नोकरी शोधणाऱ्यांसाठी',
        'nav.search_placeholder' => 'नोकरी शोधा...',
        'lang.label' => 'भाषा',
        'seeker.welcome' => 'पुन्हा स्वागत आहे',
        'seeker.new_jobs' => 'नवीन नोकऱ्या (7 दिवस)',
        'seeker.my_apps' => 'माझे अर्ज',
        'auth.email' => 'ईमेल',
        'auth.password' => 'पासवर्ड',
        'auth.login_btn' => 'लॉगिन',
    ],

    // ===== GUJARATI =====
    'gu' => [
        'nav.home' => 'હોમ',
        'nav.browse_jobs' => 'નોકરીઓ જુઓ',
        'nav.my_apps' => 'મારી અરજીઓ',
        'nav.post_job' => 'નોકરી પોસ્ટ કરો',
        'nav.dashboard' => 'ડેશબોર્ડ',
        'nav.logout' => 'લોગ આઉટ',
        'nav.signin' => 'સાઇન ઇન',
        'nav.login' => 'લોગિન',
        'nav.get_started' => 'શરૂ કરો',
        'nav.for_employers' => 'એમ્પ્લોયર્સ માટે',
        'nav.for_jobseekers' => 'નોકરી શોધનારા માટે',
        'nav.search_placeholder' => 'નોકરી શોધો...',
        'lang.label' => 'ભાષા',
        'seeker.welcome' => 'પુનઃ સ્વાગત છે',
        'seeker.new_jobs' => 'નવી નોકરીઓ (7 દિવસ)',
        'seeker.my_apps' => 'મારી અરજીઓ',
        'auth.email' => 'ઇમેઇલ',
        'auth.password' => 'પાસવર્ડ',
        'auth.login_btn' => 'લોગિન',
    ],

    // ===== KANNADA =====
    'kn' => [
        'nav.home' => 'ಮುಖಪುಟ',
        'nav.browse_jobs' => 'ಉದ್ಯೋಗಗಳನ್ನು ನೋಡಿ',
        'nav.my_apps' => 'ನನ್ನ ಅರ್ಜಿಗಳು',
        'nav.post_job' => 'ಉದ್ಯೋಗ ಪೋಸ್ಟ್ ಮಾಡಿ',
        'nav.dashboard' => 'ಡ್ಯಾಶ್‌ಬೋರ್ಡ್',
        'nav.logout' => 'ಲಾಗ್ ಔಟ್',
        'nav.signin' => 'ಸೈನ್ ಇನ್',
        'nav.login' => 'ಲಾಗಿನ್',
        'nav.get_started' => 'ಪ್ರಾರಂಭಿಸಿ',
        'nav.for_employers' => 'ಉದ್ಯೋಗದಾತರಿಗೆ',
        'nav.for_jobseekers' => 'ಉದ್ಯೋಗಾಕಾಂಕ್ಷಿಗಳಿಗೆ',
        'nav.search_placeholder' => 'ಉದ್ಯೋಗಗಳನ್ನು ಹುಡುಕಿ...',
        'lang.label' => 'ಭಾಷೆ',
        'seeker.welcome' => 'ಮತ್ತೆ ಸ್ವಾಗತ',
        'seeker.new_jobs' => 'ಹೊಸ ಉದ್ಯೋಗಗಳು (7 ದಿನ)',
        'seeker.my_apps' => 'ನನ್ನ ಅರ್ಜಿಗಳು',
        'auth.email' => 'ಇಮೇಲ್',
        'auth.password' => 'ಪಾಸ್ವರ್ಡ್',
        'auth.login_btn' => 'ಲಾಗಿನ್',
    ],

    // ===== MALAYALAM =====
    'ml' => [
        'nav.home' => 'ഹോം',
        'nav.browse_jobs' => 'ജോലികൾ കാണുക',
        'nav.my_apps' => 'എന്റെ അപേക്ഷകൾ',
        'nav.post_job' => 'ജോലി പോസ്റ്റ് ചെയ്യുക',
        'nav.dashboard' => 'ഡാഷ്ബോർഡ്',
        'nav.logout' => 'ലോഗ് ഔട്ട്',
        'nav.signin' => 'സൈൻ ഇൻ',
        'nav.login' => 'ലോഗിൻ',
        'nav.get_started' => 'ആരംഭിക്കുക',
        'nav.for_employers' => 'തൊഴിലുടമകൾക്കായി',
        'nav.for_jobseekers' => 'ജോലി അന്വേഷിക്കുന്നവർക്കായി',
        'nav.search_placeholder' => 'ജോലികൾ തിരയുക...',
        'lang.label' => 'ഭാഷ',
        'seeker.welcome' => 'വീണ്ടും സ്വാഗതം',
        'seeker.new_jobs' => 'പുതിയ ജോലികൾ (7 ദിവസം)',
        'seeker.my_apps' => 'എന്റെ അപേക്ഷകൾ',
        'auth.email' => 'ഇമെയിൽ',
        'auth.password' => 'പാസ്വേഡ്',
        'auth.login_btn' => 'ലോഗിൻ',
    ],
];

// ============================================================
// FUNCTIONS
// ============================================================

if (!function_exists('init_language')) {
    function init_language() {
        global $db;

        // 1. URL switch (?lang=hi) — highest priority
        if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS)) {
            $_SESSION['lang'] = $_GET['lang'];
            
            // Save to user DB if logged in (safely — check $db)
            if (isset($_SESSION['uid']) && isset($db) && $db instanceof mysqli) {
                $stmt = $db->prepare("UPDATE users SET preferred_lang=? WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param('si', $_GET['lang'], $_SESSION['uid']);
                    $stmt->execute();
                }
            }

            // Clean redirect (remove ?lang= from URL)
            $url = strtok($_SERVER['REQUEST_URI'], '?');
            $params = $_GET;
            unset($params['lang']);
            if (!empty($params)) $url .= '?' . http_build_query($params);
            header("Location: $url");
            exit;
        }

        // 2. Session (from previous switch)
        if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], SUPPORTED_LANGS)) {
            return $_SESSION['lang'];
        }

        // 3. User DB preference (only if DB available)
        if (isset($_SESSION['uid']) && isset($db) && $db instanceof mysqli) {
            $stmt = $db->prepare("SELECT preferred_lang FROM users WHERE id=?");
            if ($stmt) {
                $stmt->bind_param('i', $_SESSION['uid']);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                if ($res && !empty($res['preferred_lang']) && in_array($res['preferred_lang'], SUPPORTED_LANGS)) {
                    $_SESSION['lang'] = $res['preferred_lang'];
                    return $res['preferred_lang'];
                }
            }
        }

        // 4. Default
        $_SESSION['lang'] = DEFAULT_LANG;
        return DEFAULT_LANG;
    }
}

if (!function_exists('current_lang')) {
    function current_lang() {
        return $_SESSION['lang'] ?? DEFAULT_LANG;
    }
}

if (!function_exists('is_rtl')) {
    function is_rtl() {
        return in_array(current_lang(), RTL_LANGS);
    }
}

if (!function_exists('t')) {
    function t($key, $fallback = null) {
        $lang = current_lang();
        $all = $GLOBALS['TRANSLATIONS'] ?? [];
        
        if (isset($all[$lang][$key])) return $all[$lang][$key];
        if ($lang !== 'en' && isset($all['en'][$key])) return $all['en'][$key];
        if ($fallback !== null) return $fallback;
        return $key;
    }
}

// Auto-init language
init_language();