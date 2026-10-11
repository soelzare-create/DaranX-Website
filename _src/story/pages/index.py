"""Homepage: همه چیز، سر جای درستش (the DaranX philosophy as a story).
1 equipment next to equipment is not a solution; the precise execution of a wrong design is still wrong
2 method (blueprint): design before execution: understand, design, execute, support
3 three domains on one shared design layer, then the conversation starts (contact form + map below)
"""
from kit import *
import layout as S
from solutions import layers as domain_layers, design as design_layer, DOMAINS

WIDE = {'z': 1, 'x': 540, 'y': 470}


def base():
    return [shell(), desks(), phone()]


def mess():
    """equipment dropped anywhere: each piece tilted and out of place"""
    items = [('hard-drives', 820, 110, -14), ('video-camera', 420, 455, 18), ('wifi-high', 470, 742, -20),
             ('chart-bar', 700, 600, 12), ('battery-charging', 1060, 610, -10), ('shield-check', 300, 225, 16)]
    return group('mess', *[f'<g transform="rotate({r} {x} {y})">{use(ic, x, y, 60, "ic messy")}</g>' for ic, x, y, r in items])


def a1():
    return world('a1', base() + [cloud(), mess(), group('wrong', tangle(11), node(queue=False),
                                                       chip(600, 842, 'دقیق اجرا شد، اما اشتباه طراحی شده بود', 'chip alert', 'warning'))])


def a2():
    plan = group('plan', neat_cables(lanes=True), f'<rect class="plan-rk" x="{RACK[0]}" y="{RACK[1]}" width="{RACK[2]}" height="{RACK[3]}" rx="5"/>',
                 chip(600, 110, 'جای هر جزء، پیش از اجرا', 'chip', 'pencil-ruler'))
    build = group('built', neat_cables(lanes=True), switches(), aps(), cameras(), rack())
    return world('a2', base() + [cloud(),
                                 asks([('f3', 'مسئلهٔ واقعی چیست؟'), ('f2', 'امروز چه لازم است؟'), ('f1', 'فردا چه لازم می‌شود؟')], x=720),
                                 hot(('f3', 'b'), 'نقطهٔ حساس'), plan, build,
                                 group('care', pulse_line(250, 744, 10, 'mon-p', 'headset'), label(320, 790, 'پشتیبانی', 'lbl sm'))])


def a3():
    return world('a3', base() + [domain_layers(), design_layer()])


def link(name, href):
    return f'<a class="cap-link" href="{href}">دیدن حوزهٔ {name}</a>'


VENTURE = '''<section class="sec" id="venture" aria-labelledby="h-venture"><div class="wrap">
  <div class="venture-box" data-reveal>
    <span class="kicker">Venture تحت DaranX</span>
    <h2 id="h-venture">PlanoGram</h2>
    <p>راهکار نرم‌افزاری در حوزهٔ چیدمان فروشگاهی، مدیریت قفسه‌ها و Planogram؛ استفاده از داده و نرم‌افزار برای تصمیم‌گیری بهتر دربارهٔ فضای فروش.</p>
    <ul><li>طراحی و مدیریت چیدمان فروشگاه</li><li>مدیریت قفسه‌ها و استانداردسازی چیدمان</li><li>تصمیم‌گیری داده‌محور دربارهٔ فضای فروش</li></ul>
  </div>
</div></section>'''

CONTACT = f'''<section class="sec" id="contact" aria-labelledby="h-contact"><div class="wrap contact-grid">
  <div class="contact-info" data-reveal>
    <span class="kicker">تماس با ما</span>
    <h2 id="h-contact">شروع از یک گفتگو</h2>
    <p>اول مسئلهٔ شما را می‌فهمیم، بعد می‌گوییم چه چیزی واقعاً لازم دارید. برای شروع کافی است تماس بگیرید یا به دفتر ما سر بزنید.</p>
    <dl class="ci-list">
      <div><dt>تلفن ثابت</dt><dd><a href="tel:+982188964116">{S.TEL}</a> · <a href="tel:+982188966904"><bdi dir="ltr">۰۲۱-۸۸۹۶۶۹۰۴</bdi></a></dd></div>
      <div><dt>موبایل</dt><dd><a href="tel:+989359370910"><bdi dir="ltr">۰۹۳۵-۹۳۷۰۹۱۰</bdi></a></dd></div>
      <div><dt>آدرس دفتر</dt><dd>تهران، میدان فاطمی، نبش چهلستون، ساختمان چهلستون، طبقه ۲، واحد ۲۰۲</dd></div>
    </dl>
  </div>
  <div class="contact-form" data-reveal>
    <h3>مسئلهٔ سازمان‌تان را با ما در میان بگذارید.</h3>
    <p>یک گفتگو دربارهٔ وضعیت فعلی و اهداف، اولین قدم طراحی راهکاری است که سر جای درستش قرار می‌گیرد.</p>
    <form class="cform" id="contactForm" method="post" action="contact.php" novalidate>
      <div class="cf-row">
        <div class="cf-field"><label for="cf-name">نام و نام خانوادگی <span class="req" aria-hidden="true">*</span></label>
          <input id="cf-name" name="name" type="text" autocomplete="name" required maxlength="80"></div>
        <div class="cf-field"><label for="cf-phone">شماره تماس <span class="req" aria-hidden="true">*</span></label>
          <input id="cf-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required maxlength="25" dir="ltr"></div>
      </div>
      <div class="cf-row">
        <div class="cf-field"><label for="cf-email">ایمیل <span class="opt">(اختیاری)</span></label>
          <input id="cf-email" name="email" type="email" autocomplete="email" maxlength="120" dir="ltr"></div>
        <div class="cf-field"><label for="cf-company">سازمان یا موضوع <span class="opt">(اختیاری)</span></label>
          <input id="cf-company" name="company" type="text" maxlength="100"></div>
      </div>
      <div class="cf-field"><label for="cf-message">پیام شما <span class="req" aria-hidden="true">*</span></label>
        <textarea id="cf-message" name="message" rows="4" required maxlength="2000" placeholder="کوتاه بگویید با چه مسئله‌ای درگیر هستید"></textarea></div>
      <!-- honeypot: must stay empty; bots fill it -->
      <div class="cf-hp" aria-hidden="true"><label>این فیلد را خالی بگذارید<input name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="hero-actions"><button type="submit" class="btn btn-solid cf-submit">ارسال پیام</button><a href="tel:+982188964116" class="btn btn-line">یا تماس بگیرید</a></div>
      <p class="cf-status" id="cfStatus" role="status" aria-live="polite" hidden></p>
      <p class="cf-note">اطلاعات شما فقط برای پاسخ‌گویی استفاده می‌شود و در اختیار کسی قرار نمی‌گیرد.</p>
    </form>
  </div>
</div></section>
<section class="sec" id="location" aria-labelledby="h-loc"><div class="wrap">
  <div class="map-head" data-reveal>
    <div><span class="kicker">موقعیت ما</span><h2 id="h-loc">روی نقشه پیدایمان کنید</h2>
      <p>تهران، میدان فاطمی، نبش چهلستون، ساختمان چهلستون، طبقه ۲، واحد ۲۰۲</p></div>
    <div class="hero-actions"><a class="btn btn-solid" id="mapDir" href="https://www.google.com/maps/dir/?api=1&amp;destination=35.7206225,51.4062148" target="_blank" rel="noopener">مسیریابی با گوگل‌مپ</a>
      <a class="btn btn-line" id="mapNeshan" href="https://neshan.org/maps/@35.7206225,51.4062148,17z" target="_blank" rel="noopener">نشان</a></div>
  </div>
  <div id="map" class="map" role="region" aria-label="نقشهٔ موقعیت دفتر DaranX"></div>
  <p class="map-hint">برای بزرگ‌نمایی، روی نقشه کلیک کنید و سپس با اسکرول زوم کنید.</p>
</div></section>'''

SCRIPTS = '''<script src="assets/leaflet/leaflet.js" defer></script>
<script>
(function(){
  var form=document.getElementById('contactForm'); if(!form) return;
  var statusEl=document.getElementById('cfStatus'), btn=form.querySelector('.cf-submit'), openedAt=Date.now();
  function show(kind,msg){statusEl.className='cf-status '+kind;statusEl.textContent=msg;statusEl.hidden=false;}
  var OK='پیام شما با موفقیت ارسال شد. به‌زودی با شما تماس می‌گیریم.', ERR='ارسال ناموفق بود. لطفاً دوباره تلاش کنید یا تماس بگیرید.';
  form.addEventListener('submit',function(e){
    e.preventDefault();
    if(!form.checkValidity()){form.reportValidity();return;}
    var data=new FormData(form);
    data.append('el',String(Math.round((Date.now()-openedAt)/1000)));   // seconds the form was open (bot check in contact.php)
    btn.disabled=true; var original=btn.textContent; btn.textContent='در حال ارسال'; statusEl.hidden=true;
    fetch(form.action,{method:'POST',body:data,headers:{'Accept':'application/json','X-Requested-With':'fetch'}})
      .then(function(r){return r.json().catch(function(){return {ok:r.ok};});})
      .then(function(res){if(res&&res.ok){show('ok',OK);form.reset();}else{show('err',(res&&res.error)||ERR);}})
      .catch(function(){show('err','ارتباط برقرار نشد. لطفاً اتصال اینترنت را بررسی کنید یا تماس بگیرید.');})
      .finally(function(){btn.disabled=false;btn.textContent=original;});
  });
  try{var q=new URLSearchParams(location.search);if(q.get('sent')==='1')show('ok',OK);else if(q.get('sent')==='0')show('err',ERR);}catch(_){}
})();
window.addEventListener('load',function(){
  var el=document.getElementById('map'); if(!el||!window.L) return;
  var LAT=35.7206225,LNG=51.4062148,ZOOM=17;
  var map=L.map('map',{scrollWheelZoom:false}).setView([LAT,LNG],ZOOM);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'}).addTo(map);
  var icon=L.icon({iconUrl:'assets/leaflet/images/marker-icon.png',iconRetinaUrl:'assets/leaflet/images/marker-icon-2x.png',shadowUrl:'assets/leaflet/images/marker-shadow.png',
    iconSize:[25,41],iconAnchor:[12,41],popupAnchor:[1,-34],shadowSize:[41,41]});
  L.marker([LAT,LNG],{icon:icon}).addTo(map).bindPopup('<b>DaranX</b><br>میدان فاطمی، نبش چهلستون<br>ساختمان چهلستون، طبقه ۲، واحد ۲۰۲');
  map.on('focus',function(){map.scrollWheelZoom.enable();}); map.on('blur',function(){map.scrollWheelZoom.disable();});
  setTimeout(function(){map.invalidateSize();},200);
});
</script>
'''


def build():
    return {
        'file': 'index.html', 'hub': None,
        'title': 'DaranX | شریک فناوری سازمان‌ها و کسب‌وکارها',
        'description': 'DaranX شریک فناوری سازمان‌ها و کسب‌وکارهاست: زیرساخت، امنیت و هوشمندی را از شناخت و طراحی تا اجرا و پشتیبانی، به‌عنوان یک راهکار یکپارچه طراحی می‌کند.',
        'keywords': 'DaranX, داران ایکس, شریک فناوری, زیرساخت شبکه و سرور, امنیت سازمان, هوش مصنوعی, فروش و نصب تجهیزات شبکه, مشاوره فناوری',
        'crumbs': [],
        'badge': 'شریک فناوری سازمان‌ها',
        'h1': 'همه چیز،<br><em>سر جای درستش.</em>',
        'verdict': 'زیرساخت، امنیت و هوشمندی کسب‌وکار شما؛ از شناخت و طراحی تا اجرا و پشتیبانی، به‌عنوان یک راهکار یکپارچه.',
        'cta2': ('روش کار ما', '#a2'),
        'service': {'name': 'راهکارهای یکپارچهٔ فناوری DaranX', 'type': 'Technology partner: infrastructure, security and intelligence',
                    'desc': 'طراحی، اجرا و پشتیبانی راهکار یکپارچه در سه حوزهٔ زیرساخت، امنیت و هوشمندی، و فروش، نصب و راه‌اندازی تجهیزات.',
                    'parts': [(n, u) for n, u, _ in DOMAINS]},
        'acts': [
            {'id': 'a1', 'hero': True, 'len': 420, 'label': 'وقتی همه چیز سر جایش نیست', 'world': a1(),
             'caps': [(1, 'p', 'تجهیزات کنار هم، راهکار نیست', 'مجموعه‌ای از تجهیزات که کنار هم چیده شده‌اند، هنوز یک راهکار نیست.'),
                      (2, 'p', 'دقیق، اما اشتباه', 'اجرای دقیق یک طراحی اشتباه، همچنان یک نتیجهٔ اشتباه است.')],
             'timeline': {'bounds': [.22, .6], 'stops': [0, .42, 1], 'cam': [WIDE]}},
            {'id': 'a2', 'blueprint': True, 'len': 600, 'label': 'روش کار DaranX', 'world': a2(),
             'caps': [(0, 'lead', 'طراحی، مقدم بر اجراست', 'پیش از انتخاب هر تجهیزی، اول مسئلهٔ واقعی سازمان را می‌فهمیم.'),
                      (1, 'h3', 'شناخت', 'مسئله، نیاز، شرایط موجود و اهداف آینده؛ پیش از هر تصمیم فنی.'),
                      (2, 'h3', 'طراحی', 'جایگاه، ارتباط و نقش هر جزء پیش از اجرا مشخص می‌شود.'),
                      (3, 'h3', 'اجرا', 'طراحی با تجهیزات و روش اجرایی مناسب، به راهکاری واقعی تبدیل می‌شود.'),
                      (4, 'h3', 'پشتیبانی', 'پروژه با تحویل تمام نمی‌شود؛ نگاه پشتیبانی از همان طراحی آغاز می‌شود.')],
             'timeline': {'bounds': [.18, .38, .58, .78], 'stops': [.08, .28, .48, .68, .9], 'cam': [WIDE]}},
            {'id': 'a3', 'blueprint': True, 'len': 520, 'label': 'سه حوزهٔ DaranX', 'world': a3(),
             'caps': [(0, 'lead', 'سه حوزه، یک لایهٔ مشترک', 'زیرساخت را می‌سازیم، از آن محافظت می‌کنیم و آن را هوشمند می‌کنیم.'),
                      (1, 'h3', 'زیرساخت: ساختن بستر درست', DOMAINS[0][2], link(*DOMAINS[0][:2])),
                      (2, 'h3', 'امنیت: محافظت از آنچه ساخته‌ایم', DOMAINS[1][2], link(*DOMAINS[1][:2])),
                      (3, 'h3', 'هوشمندی: ارزش‌آفرینی از داده', DOMAINS[2][2], link(*DOMAINS[2][:2])),
                      (4, 'h3', 'همه چیز سر جای درستش.', 'خدمات را کنار هم نمی‌گذاریم؛ با هم طراحی می‌کنیم. شروع، یک گفتگوست.',
                       S.final_actions())],
             'timeline': {'bounds': [.16, .38, .6, .8], 'stops': [.06, .28, .5, .7, .92], 'cam': [WIDE]}},
        ],
        'states': [
            ('a1', '.mess', [1], True, 'opacity:1'),
            ('a1', '.wrong', [2], True, 'opacity:1'),
            ('a1', '.mess', [2], False, 'opacity:0'),
            ('a2', ':is(.ask,.hot,.hot-t)', [0, 1], True, 'opacity:1'),
            ('a2', '.plan', [2], True, 'opacity:1'),
            ('a2', '.built', [3, 4], True, 'opacity:1'),
            ('a2', ':is(.care,.care .mon-p)', [4], True, 'opacity:1'),
            ('a2', ':is(.flow,.riser,.uplink,.lane)', [4], False, 'stroke:var(--brand);stroke-dasharray:4 14;animation:flow 1.1s linear infinite'),
            ('a3', '.l-infra', [1, 2, 3, 4], True, 'opacity:1'),
            ('a3', '.l-sec', [2, 3, 4], True, 'opacity:1'),
            ('a3', '.l-int', [3, 4], True, 'opacity:1'),
            ('a3', '.l-int .bar', [3, 4], True, 'transform:none'),
            ('a3', ':is(.dk-f3-3,.dk-f3-4)', [3, 4], True, 'opacity:0'),
            ('a3', '.dsg', [4], True, 'opacity:1'),
            ('a3', ':is(.flow,.riser,.uplink)', [3, 4], False, 'stroke:var(--brand);stroke-dasharray:4 14;animation:flow 1.1s linear infinite'),
        ],
        'css': """.mess,.wrong,.plan,.built,.care,.l-infra,.l-sec,.l-int,.dsg{opacity:0;transition:opacity .6s var(--ease-out)}
.messy{fill:#E2A35A}
.plan .cab{stroke:var(--brand);stroke-dasharray:6 8;stroke-width:2;opacity:.75}
.plan-rk{fill:none;stroke:var(--brand);stroke-width:2;stroke-dasharray:6 6}
.care .ic{fill:var(--brand)}
.desk{transition:opacity .4s ease}
.talk{fill:var(--brand)}
.dsg-b{fill:none;stroke:var(--brand);stroke-width:2.5;stroke-dasharray:3 9}
.cap-link{display:inline-flex;margin-top:.7rem;font-weight:800;color:var(--brand);border-bottom:1px solid rgba(127,176,223,.5)}""",
        'summary': {'h2': 'دربارهٔ DaranX',
                    'paras': ['DaranX یک شریک فناوری برای سازمان‌ها و کسب‌وکارهاست. زیرساخت، امنیت و فناوری‌های هوشمند را از مرحلهٔ شناخت و طراحی تا اجرا و پشتیبانی، به‌عنوان اجزای یک راهکار واحد می‌بینیم، نه مجموعه‌ای از تجهیزات که کنار هم چیده شده‌اند. باور اصلی ما ساده است: همه چیز باید سر جای درستش باشد.',
                              'برای ما طراحی مقدم بر اجراست. پیش از انتخاب تجهیز، نصب سرور یا پیاده‌سازی هر راهکار، اول مسئلهٔ واقعی سازمان را می‌فهمیم: نیاز واقعی چیست، چه چیزی امروز لازم است و چه چیزی ممکن است فردا لازم شود.',
                              'روش کار ما یک زنجیرهٔ پیوسته است: شناخت، طراحی، اجرا و پشتیبانی. نگاه پشتیبانی از همان مرحلهٔ طراحی آغاز می‌شود، چون اگر قرار است فردا نگهدار چیزی باشیم، امروز آن را طوری می‌سازیم که دردسر نسازد.',
                              'توانمندی‌های ما در سه حوزه سازمان می‌یابند: زیرساخت برای ساختن بستر درست، امنیت برای محافظت از آنچه ساخته‌ایم، و هوشمندی برای ارزش‌آفرینی از داده. طراحی لایهٔ مشترکی است که هر سه را به هم متصل می‌کند.',
                              'مخاطب ما الزاماً متخصص فناوری نیست؛ برای همین ارزش فناوری را با زبان کسب‌وکار توضیح می‌دهیم: هزینه، ریسک، پایداری، بهره‌وری، امنیت و توسعه. DaranX یکی از مجموعه‌های هلدینگ بهسرما است.']},
        'parts': {'h2': 'سه حوزه، یک لایهٔ مشترک', 'items': DOMAINS},
        'why_h2': 'شریک فناوری، نه فروشندهٔ تجهیزات',
        'why': ['سیستم درست را طراحی می‌کند', 'مسئولیت نتیجه را به عهده می‌گیرد', 'از آن پشتیبانی می‌کند', 'ارزش فناوری را با زبان کسب‌وکار می‌گوید'],
        'method_h2': 'یک مسیر، از مسئله تا پایداری',
        'method': [('شناخت', 'بررسی مسئله، نیاز، شرایط موجود و اهداف آینده؛ پیش از هر تصمیم فنی.'), ('طراحی', 'راهکار متناسب با نیاز واقعی و جایگاه، ارتباط و نقش هر جزء.'),
                   ('اجرا', 'تبدیل طراحی به راهکاری واقعی با تجهیزات و روش اجرایی مناسب.'), ('پشتیبانی', 'ادامهٔ مسئولیت نگهداری، توسعه و عملکرد راهکار.')],
        'faq': [('DaranX چه کاری انجام می‌دهد؟', 'DaranX شریک فناوری سازمان‌ها و کسب‌وکارهاست و زیرساخت، امنیت و هوشمندی را از شناخت و طراحی تا اجرا و پشتیبانی، به‌عنوان یک راهکار یکپارچه طراحی می‌کند.'),
                ('آیا DaranX تجهیزات هم می‌فروشد و نصب می‌کند؟', 'بله. فروش، نصب و راه‌اندازی شبکه، سرور، سوئیچ و روتر، کامپیوتر و لپ‌تاپ و UPS و برق اضطراری، متناسب با نیاز واقعی سازمان.'),
                ('روش کار DaranX چیست؟', 'چهار مرحلهٔ پیوسته: شناخت، طراحی، اجرا و پشتیبانی. پیش از هر تصمیم فنی، مسئلهٔ واقعی سازمان را می‌فهمیم.'),
                ('دفتر DaranX کجاست؟', 'تهران، میدان فاطمی، نبش چهلستون، ساختمان چهلستون، طبقه ۲، واحد ۲۰۲. تلفن: ۰۲۱-۸۸۹۶۴۱۱۶.'),
                ('DaranX عضو کدام مجموعه است؟', 'DaranX یکی از مجموعه‌های هلدینگ بهسرما است.')],
        'after_sections': VENTURE + CONTACT,
        'head_extra': '<link rel="stylesheet" href="assets/leaflet/leaflet.css">\n',
        'scripts': SCRIPTS,
    }
