# DaranX — وب‌سایت

وب‌سایت شرکتی **DaranX** و لندینگ‌های سه حوزهٔ فعالیت. صفحات، استاتیک و
خودکفا هستند (HTML/CSS/JS تک‌فایلی، بدون build و بدون وابستگی) و فارسی/RTL.

> Core Brand Idea: **همه چیز سر جای درستش.**
> روش: شناخت → طراحی → اجرا → پشتیبانی.

## صفحه‌ها

| فایل | صفحه |
|---|---|
| `index.html` | سایت اصلی شرکتی (هیرو، روش کار، خدمات، درباره، PlanoGram، تماس) |
| `solutions.html` | صفحهٔ راهکارها (هاب سه حوزه با ناوبری داخلی) |
| `infrastructure.html` | لندینگ حوزهٔ **زیرساخت** |
| `security.html` | لندینگ حوزهٔ **امنیت** |
| `intelligence.html` | لندینگ حوزهٔ **هوشمندی** |
| `assets/daranx-logo.svg` | لوگوی برند |

### زیرمجموعه‌ها (۱۳ لندینگ)

هر حوزه، لندینگ اختصاصی زیرمجموعه‌های خود را دارد (محتوای خلاصه و برندبوک‌محور):

- **زیرساخت:** `network.html`، `datacenter-server.html`، `storage.html`، `power-ups.html`، `passive.html`
- **امنیت:** `network-security.html`، `cctv.html`، `access-monitoring.html`، `access-control.html`
- **هوشمندی:** `ai-solutions.html`، `data-analytics.html`، `automation.html`، `software-models.html`

کارت‌های هر صفحهٔ حوزه به این لندینگ‌ها لینک شده‌اند.

صفحه‌ها به هم لینک شده‌اند (نسبی)، پس مجموعه به‌صورت یکپارچه کار می‌کند.

## اجرای محلی

چون همه‌چیز استاتیک است، کافی است فایل‌ها را با یک وب‌سرور سرو کنید:

```bash
# با پایتون
python -m http.server 8080
# سپس مرورگر: http://localhost:8080/
```

باز کردن مستقیم فایل (`file://`) هم کار می‌کند، ولی سرو کردن با http توصیه می‌شود.

## میزبانی (Hosting)

هر میزبان استاتیک کار می‌کند: **GitHub Pages**، Netlify، Cloudflare Pages، یا
هر وب‌سرور.

### GitHub Pages
از تنظیمات ریپازیتوری → **Pages** → منبع را روی برنچ `main` و پوشهٔ ریشه
(`/root`) قرار دهید. فایل `.nojekyll` باعث می‌شود دارایی‌ها بدون پردازش Jekyll
سرو شوند. (برای ریپازیتوریِ خصوصی، GitHub Pages نیازمند پلن مناسب است.)


## پیش از انتشار: تنظیم دامنه

متاتگ‌های SEO/OpenGraph و `sitemap.xml`/`robots.txt` با دامنهٔ نمونهٔ `https://www.daranx.com` نوشته شده‌اند. پیش از انتشار، آن را با دامنهٔ واقعی جایگزین کنید:

```bash
grep -rl "www.daranx.com" . | xargs sed -i "s#https://www.daranx.com#https://YOUR-DOMAIN#g"
```

فایل‌های SEO: هر صفحه شامل `description`، Open Graph و Twitter Card است؛ تصویر اشتراک‌گذاری در `assets/og.png` و آیکون‌ها در `assets/favicon.svg` و `assets/favicon-32.png` و `assets/apple-touch-icon.png`.

## هویت بصری

- پالت: `#153A62` (نِیوی)، `#D9D9D9` (نقره‌ای)، `#FFFFFF` (سفید) + تینت فولادیِ برگرفته از نِیوی برای لهجه‌ها.
- تایپ: Vazirmatn (Google Fonts).
- تم روشن/تیره، موشن‌گرافیک با احترام به `prefers-reduced-motion`.

هر تغییری در محتوا یا برند باید با Brand Skill داران ایکس هماهنگ بماند.
