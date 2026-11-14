<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="حلقة، مساحة لتنظيم حلقات القرآن الكريم ومتابعة الطلاب والحضور والحفظ والمراجعة.">
    <meta name="theme-color" content="#185b4c">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/halaka-icon.svg') }}">
    <title>حلقة — لكل آية خطوة</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=tajawal:400,500,700,800&display=swap" rel="stylesheet">
    <style>
        :root{--green:#185b4c;--deep:#123f36;--gold:#b89958;--ink:#203e35;--muted:#58695e;--paper:#f8f7f2;--line:#dedfd3}
        *{box-sizing:border-box}html{scroll-behavior:smooth;scroll-padding-top:28px}body{margin:0;background:var(--paper);color:var(--ink);font-family:Tajawal,Arial,sans-serif;line-height:1.7}a{color:inherit;text-decoration:none}a:focus-visible{outline:3px solid var(--gold);outline-offset:6px}svg{display:block} .wrap{width:min(1200px,calc(100% - 80px));margin-inline:auto}
        .skip{position:absolute;top:-100px;padding:12px;background:white;z-index:10}.skip:focus{top:8px}header{height:112px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--line)}.logo{display:block;width:116px;height:auto;aspect-ratio:1015/350}.nav{display:flex;align-items:center;gap:36px;font-size:15px;font-weight:500}.nav-link{padding-block:10px}.nav-link:hover{color:var(--green)}.login{display:inline-flex;align-items:center;justify-content:center;gap:16px;padding:11px 22px;border:1px solid var(--green);border-radius:8px;font-weight:700;font-size:14px}.login:hover{background:#eaece3}.arrow{font-family:Arial,sans-serif;font-size:21px}
        .hero{display:grid;grid-template-columns:1.05fr 1fr;gap:72px;align-items:center;padding-block:76px 80px}.eyebrow{display:flex;align-items:center;gap:10px;color:var(--green);font-size:14px;font-weight:700}.eyebrow::before{content:'';width:7px;height:7px;border-radius:50%;background:var(--gold)}h1{font-size:clamp(38px,4.6vw,64px);line-height:1.65;letter-spacing:0;margin:18px 0 20px;font-weight:800}.headline-line{display:block;white-space:nowrap}h1 em{color:var(--green);font-style:normal;text-decoration:underline;text-decoration-color:#d8c795;text-decoration-thickness:4px;text-underline-offset:12px}.intro{color:var(--muted);font-size:18px;max-width:460px;margin:24px 0 30px}.cta{display:inline-flex;align-items:center;gap:28px;padding:15px 26px;border-radius:8px;background:var(--green);color:#fff;font-weight:700;box-shadow:0 5px 10px #185b4c0a}.cta:hover{background:var(--deep)}
        .art{position:relative;min-height:462px;display:flex;align-items:center;justify-content:center;background:#eaece2;border-radius:180px 180px 16px 16px;isolation:isolate;overflow:hidden}.art::before{content:'';position:absolute;width:330px;height:330px;border:1px solid #c9cfbe;border-radius:50%;top:42px;left:50%;transform:translateX(-50%)}.art::after{content:'';position:absolute;width:390px;height:390px;border:1px solid #d3d7c8;border-radius:50%;top:12px;left:50%;transform:translateX(-50%)}.illustration{width:90%;z-index:1;margin-top:12px}.art-caption{position:absolute;bottom:30px;font-size:12px;color:var(--green);z-index:2}.art-caption span{color:var(--gold);padding-inline:14px}
        .section-top{display:flex;justify-content:space-between;align-items:end;gap:24px;border-top:1px solid var(--line);padding-top:36px;margin-bottom:28px}.section-top h2{margin:5px 0 0;font-size:28px;line-height:1.5}.features{display:grid;grid-template-columns:repeat(3,1fr);gap:32px;margin-bottom:70px}.feature{padding:24px 0}.feature+.feature{border-right:1px solid var(--line);padding-right:32px}.feature-top{display:flex;justify-content:space-between;align-items:center}.icon{width:44px;height:44px;border:1px solid #d3d9ca;border-radius:12px;display:grid;place-items:center;color:var(--green)}.icon svg{width:22px;height:22px}.feature h3{font-size:19px;margin:20px 0 10px}.feature p{font-size:14px;color:var(--muted);margin:0;max-width:300px}footer{border-top:1px solid var(--line);padding-block:24px;display:flex;justify-content:space-between;gap:16px;color:var(--muted);font-size:12px}footer strong{color:var(--green)}
        @media(min-width:1500px){.hero{padding-block:100px}.art{min-height:510px}}@media(max-width:900px){.wrap{width:calc(100% - 48px)}.hero{gap:32px}.art{min-height:390px}.nav{gap:22px}.intro{font-size:16px}.features{gap:20px}.feature+.feature{padding-right:20px}}@media(max-width:640px){.wrap{width:calc(100% - 40px)}header{height:88px}.logo{width:110px;height:auto}.nav-link{display:none}.login{padding:8px 13px;font-size:13px;gap:10px;min-height:44px}.hero{grid-template-columns:1fr;padding-block:32px 40px;gap:30px}h1{font-size:clamp(30px,8.4vw,44px);margin-block:18px;line-height:1.7}.intro{font-size:17px;margin-block:18px 24px;max-width:340px} .cta{width:100%;justify-content:space-between;min-height:52px}.art{min-height:300px;max-width:440px;width:100%;margin-inline:auto}.illustration{max-width:310px}.art::before{width:270px;height:270px}.art::after{width:330px;height:330px}.art-caption{bottom:20px}.section-top{display:block;padding-top:30px}.section-top h2{font-size:23px;max-width:300px}.features{grid-template-columns:1fr;gap:0;margin-bottom:35px}.feature{padding-block:24px}.feature+.feature{border-right:0;border-top:1px solid var(--line);padding-right:0}.feature h3{margin-top:14px}.feature p{max-width:none}footer{flex-wrap:wrap}}
        @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
    </style>
</head>
<body>
<a class="skip" href="#main">انتقل إلى المحتوى</a>
<div class="wrap">
    <header>
        <a href="{{ url('/') }}" aria-label="حلقة — الصفحة الرئيسية">
            <img class="logo" src="{{ asset('images/halaka-wordmark.svg') }}" alt="حلقة" width="116" height="40">
        </a>
        <nav class="nav" aria-label="القائمة الرئيسية">
            <a class="nav-link" href="#about">عن حلقة</a>
            <a class="nav-link" href="#features">ماذا تقدّم حلقة؟</a>
            <a class="login" href="{{ route('filament.admin.pages.dashboard') }}">دخول الإدارة <span class="arrow" aria-hidden="true">↗</span></a>
        </nav>
    </header>
    <main id="main" tabindex="-1">
        <section class="hero" id="about" aria-labelledby="hero-title">
            <div>
                <div class="eyebrow">مساحة لحلقات القرآن الكريم</div>
                <h1 id="hero-title"><span class="headline-line">لكلّ آيةٍ خطوة،</span><span class="headline-line">ولكلّ طالبٍ <em>عناية.</em></span></h1>
                <p class="intro">نظّم حلقات القرآن، وتابع حضور الطلاب ورحلتهم في الحفظ والمراجعة، في مكان واحد.</p>
                <a class="cta" href="{{ route('filament.admin.pages.dashboard') }}">الدخول إلى لوحة الإدارة <span class="arrow" aria-hidden="true">←</span></a>
            </div>
            <div class="art" aria-hidden="true">
                <svg class="illustration" viewBox="0 0 480 390" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <ellipse cx="240" cy="329" rx="153" ry="15" fill="#d3d8c8"/>
                    <path d="M115 285L332 216L371 255L155 326Z" fill="#b89958"/>
                    <path d="M150 226L367 291L337 326L119 261Z" fill="#967a44"/>
                    <path d="M130 252L339 314L349 302L141 241Z" fill="#c7ae72"/>
                    <path d="M107 159L237 201L373 156V235L239 283L107 239Z" fill="#123f36"/>
                    <path d="M108 152L238 191L372 149V226L239 271L108 228Z" fill="#e4d8b9"/>
                    <path d="M116 144C157 134 194 145 239 168C282 143 324 131 364 140V218C324 209 282 224 239 249C195 224 153 212 116 221Z" fill="#fffdf4"/>
                    <path d="M239 168V249" stroke="#d0c5a6" stroke-width="2"/>
                    <path d="M127 159C158 153 192 164 224 179M127 173C158 168 192 177 224 192M127 187C158 183 192 192 224 207M127 201C158 198 192 207 224 222M254 179C285 161 321 152 351 155M254 193C285 176 321 167 351 170M254 207C285 190 321 181 351 184M254 222C285 204 321 195 351 198" stroke="#cec6af" stroke-width="3" stroke-linecap="round"/>
                    <path d="M246 171L255 168V221L250 217L246 223Z" fill="#b89958"/>
                    <path d="M80 264C65 240 64 210 74 185C85 206 88 236 80 264Z" fill="#708971"/>
                    <path d="M79 263C53 259 36 245 30 225C54 228 72 241 79 263Z" fill="#8d9f81"/>
                    <path d="M80 265L87 291" stroke="#708971" stroke-width="2"/>
                    <path d="M389 226C400 207 417 199 435 201C424 218 407 227 389 226Z" fill="#708971"/>
                    <path d="M390 226C385 204 390 186 404 173C408 196 404 214 390 226Z" fill="#8d9f81"/>
                    <path d="M390 224L384 247" stroke="#708971" stroke-width="2"/>
                    <path d="M240 62V80M231 71H249" stroke="#b89958" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="130" cy="98" r="3" fill="#b89958"/><circle cx="354" cy="102" r="3" fill="#b89958"/>
                    <path d="M181 82L184 88L190 91L184 94L181 100L178 94L172 91L178 88Z" fill="#b89958" opacity=".55"/>
                </svg>
                <div class="art-caption">حفظ <span>✦</span> مراجعة <span>✦</span> إتقان</div>
            </div>
        </section>
        <section id="features" aria-labelledby="features-title">
            <div class="section-top">
                <h2 id="features-title">كل ما تحتاجه الحلقة، في مكان واحد.</h2>
            </div>
            <div class="features">
                <article class="feature">
                    <div class="feature-top"><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0112 0v2M16 5a3 3 0 010 6M18 14a5 5 0 013 4v2"/></svg></span></div>
                    <h3>طلاب ومجموعات منظّمة</h3><p>ملفات الطلاب، ومجموعاتهم، وحسابات الأساتذة.</p>
                </article>
                <article class="feature">
                    <div class="feature-top"><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4M17 3v4M3 11h18M8 16l3 3 5-5"/></svg></span></div>
                    <h3>حصص وحضور واضحان</h3><p>تنظيم الحصص ومتابعة الحضور والغياب.</p>
                </article>
                <article class="feature">
                    <div class="feature-top"><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 5C8 2 4 3 2 4v15c3-1 7-1 10 1 3-2 7-2 10-1V4c-2-1-6-2-10 1Zm0 0v15M5 8l4 1M5 12l4 1M15 9l4-1M15 13l4-1"/></svg></span></div>
                    <h3>متابعة الحفظ والإتقان</h3><p>واجبات واضحة وسجل لكل محاولة تسميع.</p>
                </article>
            </div>
        </section>
    </main>
    <footer><span><strong>حلقة</strong> · عناية بالطالب، وخطوة نحو الإتقان.</span><span>© {{ now()->year }} حلقة. جميع الحقوق محفوظة.</span></footer>
</div>
</body>
</html>
