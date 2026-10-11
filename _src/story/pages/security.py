"""امنیت (hub): از چیزی که ساخته‌ایم درست محافظت می‌کنیم (problem hunt + assemble).
1 threats are not only outside and not only digital
2 the four protection layers, one per scene, each linking to its page
3 one coherent defence, monitored continuously
"""
from kit import *
import layout as S

WIDE = {'z': 1, 'x': 540, 'y': 470}
ENTRY, SDOOR = (206, 574), (760, 704)
PARTS = [('امنیت شبکه', 'network-security.html', 'کنترل و محافظت از مسیرهای ارتباطی و داده در برابر تهدیدها و دسترسی‌های غیرمجاز.'),
         ('دوربین مداربسته', 'cctv.html', 'نظارت تصویری برای دید کامل و مستند بر محیط سازمان و رویدادهای آن.'),
         ('دسترسی و پایش', 'access-monitoring.html', 'مدیریت اینکه چه کسی به چه چیزی دسترسی دارد، همراه با پایش پیوسته و ثبت رویدادها.'),
         ('کنترل تردد', 'access-control.html', 'مدیریت ورود و خروج افراد به فضاهای سازمان، متناسب با سطح دسترسی هر نفر.')]


def link(name, href):
    return f'<a class="cap-link" href="{href}">جزئیات {name}</a>'


def layers():
    return ''.join([
        group('l-net', *[zone(f, 'zone-l') for f in ('f3', 'f2', 'f1', 'b')], firewall(300, 768)),
        group('l-cam', cone(330, 528, 135, 150, 32), cone(690, 696, 30, 150, 32), cone(760, 186, 50, 150, 32),
              *[group('camx', use('video-camera', x, y, 28)) for x, y in [(330, 520), (690, 688), (760, 178)]]),
        group('l-acc', path('M650 400 C650 560 520 600 520 722', 'acc'), path('M290 230 C290 420 420 600 420 722', 'acc'),
              *[group('rs', use(ic, x, 744, 30)) for ic, x in [('database', 420), ('receipt', 520)]]),
        group('l-door', door(*ENTRY, 86, 'door edoor locked'), door(*SDOOR, 86, 'door sdoor locked', side=-1),
              chip(300, 610, 'ورودی', 'chip', 'identification-card'), chip(SDOOR[0] - 70, SDOOR[1] - 20, 'اتاق سرور', 'chip', 'lock-key')),
    ])


def base():
    return [shell(), group('L-cab', neat_cables()), desks(), aps(), switches(), phone(), rack(), server_icon(), cloud()]


def threats():
    return ''.join([
        pin(CLOUD[0] + 40, CLOUD[1] - 70, 'حملهٔ سایبری', 'pin alert', 'bug', below=False),
        pin(SDOOR[0], SDOOR[1] - 40, 'در باز اتاق سرور', 'pin alert', 'door-open', below=False),
        pin(250, 610, 'نقطهٔ بدون نظارت', 'pin alert', 'eye-slash', below=False),
        pin(650, 402, 'دسترسی کنترل‌نشده', 'pin alert', 'user', below=False),
    ])


def build():
    return {
        'file': 'security.html', 'hub': 'security',
        'title': 'امنیت سازمان: شبکه، دوربین، دسترسی و کنترل تردد | DaranX',
        'description': 'حوزهٔ امنیت DaranX: امنیت شبکه، دوربین مداربسته، دسترسی و پایش و کنترل تردد؛ محافظت هماهنگ از زیرساخت، محیط و دسترسی‌های سازمان از مرحلهٔ طراحی.',
        'keywords': 'امنیت سازمان, امنیت شبکه, دوربین مداربسته, کنترل دسترسی, کنترل تردد, امنیت فیزیکی',
        'crumbs': [('راهکارها', 'solutions.html'), ('امنیت', 'security.html')],
        'badge': 'حوزهٔ امنیت',
        'h1': 'از چیزی که ساخته‌ایم، <em>درست محافظت می‌کنیم</em>',
        'verdict': 'زیرساخت، محیط فیزیکی و دسترسی‌های سازمان را در یک راهکار هماهنگ و از همان مرحلهٔ طراحی محافظت می‌کنیم.',
        'cta2': ('لایه‌های محافظت', '#a2'),
        'service': {'name': 'امنیت سازمان', 'type': 'Organisational security',
                    'desc': 'محافظت هماهنگ از زیرساخت، محیط فیزیکی و دسترسی‌های سازمان: امنیت شبکه، دوربین مداربسته، دسترسی و پایش، کنترل تردد.',
                    'parts': [(n, u) for n, u, _ in PARTS]},
        'acts': [
            {'id': 'a1', 'hero': True, 'len': 300, 'label': 'تهدیدهای سازمان', 'world': world('a1', base() + [threats()]),
             'caps': [(1, 'p', 'تهدید فقط از بیرون نمی‌آید', 'یک دسترسی کنترل‌نشده، یک نقطهٔ بدون نظارت یا در باز اتاق سرور، به‌اندازهٔ یک حملهٔ سایبری هزینه می‌سازد.')],
             'timeline': {'bounds': [.3], 'stops': [0, 1], 'cam': [WIDE]}},
            {'id': 'a2', 'blueprint': True, 'len': 600, 'label': 'چهار لایهٔ محافظت', 'world': world('a2', base() + [layers()]),
             'caps': [(0, 'lead', 'لایه‌های محافظت، هماهنگ با هم', 'چهار بخش که با هم دید کامل و کنترل واقعی بر امنیت سازمان می‌سازند.')] +
                     [(k + 1, 'h3', n, d, link(n, u)) for k, (n, u, d) in enumerate(PARTS)],
             'timeline': {'bounds': [.18, .38, .58, .78], 'stops': [.08, .28, .48, .68, .9], 'cam': [WIDE]}},
            {'id': 'a3', 'blueprint': True, 'len': 300, 'label': 'دفاع منسجم سازمان', 'world': world('a3', base() + [layers(), pulse_line(470, 744, 14, 'mon-p', 'eye')]),
             'caps': [(0, 'lead', 'یک دفاع منسجم، پایش پیوسته', 'امنیت یک وضعیت ثابت نیست؛ پایش، به‌روزرسانی و پاسخ به تهدیدهای تازه ادامه دارد.'),
                      (1, 'h3', 'همه چیز سر جای درستش.', 'ریسک‌های سازمان‌تان را با هم مرور کنیم.', S.final_actions())],
             'timeline': {'bounds': [.6], 'stops': [.3, 1], 'cam': [WIDE]}},
        ],
        'states': [
            ('a1', '.pin', [1], True, 'opacity:1'),
            ('a2', ':is(.l-net,.l-cam,.l-acc,.l-door)', [0], True, 'opacity:1'),
            ('a2', '.l-net', [1], False, 'opacity:1'),
            ('a2', '.l-cam', [2], False, 'opacity:1'),
            ('a2', '.l-acc', [3], False, 'opacity:1'),
            ('a2', '.l-door', [4], False, 'opacity:1'),
            ('a3', ':is(.l-net,.l-cam,.l-acc,.l-door,.mon-p)', [0, 1], True, 'opacity:1'),
            ('a3', ':is(.lane,.flow,.riser,.uplink)', [0, 1], False, 'stroke:var(--brand);stroke-dasharray:4 14;animation:flow 1.4s linear infinite'),
        ],
        'css': """.pin{opacity:0;transition:opacity .5s var(--ease-out)}
#a1 .pin{transition-delay:calc(var(--k,0) * 120ms)}
.l-net,.l-cam,.l-acc,.l-door{opacity:.1;transition:opacity .6s var(--ease-out)}
.camx .ic{fill:var(--brand)}.rs .ic{fill:var(--brand)}
.cap-link{display:inline-flex;margin-top:.7rem;font-weight:800;color:var(--brand);border-bottom:1px solid rgba(127,176,223,.5)}""",
        'summary': {'h2': 'امنیت سازمان در DaranX',
                    'paras': ['حوزهٔ امنیت DaranX شامل امنیت شبکه، دوربین مداربسته، دسترسی و پایش و کنترل تردد است؛ لایه‌هایی که با هم و از همان مرحلهٔ طراحی، از زیرساخت، داده، محیط فیزیکی و دسترسی‌های سازمان محافظت می‌کنند.',
                              'تهدیدها تنها از بیرون نمی‌آیند و تنها دیجیتال نیستند. بدون شناخت دارایی‌ها، ریسک‌ها و اینکه چه کسی به چه چیزی باید دسترسی داشته باشد، هر ابزار امنیتی فقط یک هزینهٔ اضافه است.'],
                    'bullets': ['لایه‌های امنیت هماهنگ با هم طراحی می‌شوند، نه ابزارهای پراکنده.', 'اجرا هماهنگ با زیرساخت انجام می‌شود.',
                                'پایش، به‌روزرسانی و پاسخ به تهدیدهای تازه بخشی از مسئولیت ماست.']},
        'parts': {'h2': 'لایه‌های محافظت، هماهنگ با هم', 'items': PARTS},
        'why': ['کاهش ریسک', 'کنترل دسترسی', 'دید پیوسته بر محیط', 'آرامش خاطر مدیریت'],
        'method_h2': 'امنیت، از طراحی تا پایش پیوسته',
        'method': [('شناخت', 'شناسایی دارایی‌ها، ریسک‌ها و نقاط حساس سازمان.'), ('طراحی', 'طراحی لایه‌های محافظت هماهنگ با زیرساخت.'),
                   ('اجرا', 'پیاده‌سازی کنترل‌ها و سامانه‌های نظارتی.'), ('پشتیبانی', 'پایش پیوسته و به‌روزرسانی در برابر تهدیدهای نو.')],
        'faq': [('حوزهٔ امنیت DaranX شامل چه بخش‌هایی است؟', 'امنیت شبکه، دوربین مداربسته، دسترسی و پایش، و کنترل تردد.'),
                ('امنیت را از کجا شروع می‌کنید؟', 'از شناخت: چه دارایی‌هایی باید محافظت شوند، ریسک‌های واقعی کدام‌اند و چه کسی به چه چیزی باید دسترسی داشته باشد.'),
                ('امنیت فیزیکی هم جزو کار شماست؟', 'بله. دوربین مداربسته و کنترل تردد، محیط فیزیکی و فضاهای حساس سازمان را محافظت می‌کنند.'),
                ('پس از اجرا امنیت به‌روز می‌ماند؟', 'بله. امنیت یک فرآیند پیوسته است؛ پایش، به‌روزرسانی و پاسخ به تهدیدهای تازه ادامه دارد.')],
        'related': {'h2': 'حوزه‌های دیگر', 'links': [('زیرساخت', 'infrastructure.html'), ('هوشمندی', 'intelligence.html'), ('همهٔ راهکارها', 'solutions.html')]},
        'cta': {'h2': 'ریسک‌های سازمان‌تان را با هم مرور کنیم.', 'p': 'پیش از هر تجهیزی، اول نقاط حساس و نیاز واقعی را مشخص می‌کنیم.'},
    }
