"""دیتاسنتر و سرور: قلب پردازش (zoom-in + before/after).
1 the heart: every floor's work runs in the server room; one tremor shows everywhere
2 wrong size: too much (idle capital) / too little (stalls at peak)
3 method (blueprint): real load, virtualisation, redundancy, power+cooling+space, backup
4 result: one host fails, services continue; right-sized with room to grow
"""
from kit import *
import layout as S

WIDE = {'z': 1, 'x': 540, 'y': 470}
ROOM = {'z': 1.65, 'x': 640, 'y': 728}
ROOM_W = {'z': 1.3, 'x': 600, 'y': 745}
BASE_LBL = {'b': 'اتاق سرور'}


def floors_only():
    return ''.join([desks(), aps(), switches(), cameras(), phone()])


def a1():
    return world('a1', [
        shell(), group('L-cab', neat_cables(uplink=False)), floors_only(),
        srack(520, cls='srack s1'), srack(610, cls='srack s2'), srack(700, cls='srack s3'),
        ripple(645, 744),
        floor_warnings({'f3': 'کند', 'f2': 'قطع سرویس', 'f1': 'خطا'}),
        group('reqs', *[use(ic, x, y, 24) for ic, x, y in [('database', 420, 218), ('envelope-simple', 650, 388), ('file-text', 650, 558)]]),
    ])


def a2():
    over = group('over', *[srack(400 + k * 90, idle=(0, 1, 2) if k else (1, 2, 3)) for k in range(6)],
                 chip(625, 840, 'بیشتر ظرفیت، بی‌استفاده', 'chip alert', 'warning'))
    under = group('under', srack(580, cls='srack hotr'),
                  *[f'<circle class="q" cx="{560 - k * 16}" cy="{744}" r="5" style="--k:{k}"/>' for k in range(7)],
                  chip(615, 840, 'ساعت اوج کار', 'chip alert', 'clock'))
    return world('a2', [shell(), neat_cables(uplink=False), floors_only(), over, under,
                        floor_warnings({'f3': 'کند', 'f2': 'منتظر', 'f1': 'توقف'})])


def a3():
    smalls = group('smalls', *[srack(420 + k * 80, y=712, w=56, h=80, units=3, idle=(1, 2)) for k in range(4)])
    return world('a3', [
        shell(), group('L-cab', neat_cables(lanes=True, uplink=False)), floors_only(),
        asks([('f3', 'چه سرویس‌هایی؟'), ('f2', 'با چه حجمی؟'), ('f1', 'رشد دو سال؟'), ('b', 'کدام نباید قطع شود؟')]), hot(('b',)),
        smalls,
        host(430, 694, 230, 100, cls='host h1'),
        host(700, 694, 170, 100, cls='host h2'),
        f'<line class="sync" x1="664" y1="744" x2="696" y2="744"/>',
        group('infra', chip(330, 842, 'برق پایدار', 'chip', 'lightning'), chip(560, 842, 'خنک‌کاری', 'chip', 'snowflake'),
              chip(780, 842, 'فضای رک', 'chip', 'ruler')),
        group('bkp', '<rect class="rk" x="244" y="712" width="104" height="80" rx="8"/>', use('database', 296, 750, 40),
              label(296, 830, 'نسخهٔ پشتیبان', 'lbl sm')),
        f'<g class="bk-arrows">{arrow("M426 730 H356", "arw b-out", "a3-bout")}{arrow("M356 774 H426", "arw b-in", "a3-bin")}</g>',
    ])


def a4():
    return world('a4', [
        shell(), group('L-cab', neat_cables(lanes=True, uplink=False)), floors_only(),
        host(430, 694, 230, 100, cls='host h1'),
        host(700, 694, 170, 100, cls='host h2', vm_cls='vm2'),
        f'<line class="sync" x1="664" y1="744" x2="696" y2="744"/>',
        xmark(545, 744, 26),
        chip(700, 842, 'سرویس‌ها ادامه دارند', 'chip ok-b', 'check-circle'),
        group('capg', gauge(300, 830, 560, .58, 'gauge g1', id='a4-g1'), label(714, 884, 'ظرفیت امروز', 'lbl sm'), label(372, 884, 'جای رشد', 'lbl sm')),
    ])


def build():
    return {
        'file': 'datacenter-server.html', 'hub': 'infrastructure',
        'title': 'طراحی دیتاسنتر و سرور سازمانی | DaranX',
        'description': 'DaranX مرکز داده و سرورهای سازمان را متناسب با بار واقعی طراحی، پیکربندی و پشتیبانی می‌کند؛ با مجازی‌سازی، افزونگی، پشتیبان‌گیری و آمادگی برای رشد.',
        'keywords': 'طراحی دیتاسنتر, سرور سازمانی, مجازی‌سازی سرور, افزونگی سرور, پشتیبان‌گیری و بازیابی, اتاق سرور',
        'crumbs': [('راهکارها', 'solutions.html'), ('زیرساخت', 'infrastructure.html'), ('دیتاسنتر و سرور', 'datacenter-server.html')],
        'badge': 'زیرساخت · دیتاسنتر و سرور',
        'h1': 'قلب پردازش سازمان، <em>درست‌اندازه و قابل‌اتکا</em>',
        'verdict': 'مرکز داده و سرورها را نه بیش از حد و نه ناکافی، بلکه متناسب با بار واقعی سازمان طراحی و اجرا می‌کنیم.',
        'cta2': ('چرا اندازه مهم است؟', '#a2'),
        'service': {'name': 'طراحی و پیکربندی دیتاسنتر و سرور', 'type': 'Data center and server design',
                    'desc': 'طراحی مرکز داده و انتخاب و پیکربندی سرورها بر اساس نیاز واقعی پردازشی و مسیر توسعهٔ سازمان.'},
        'acts': [
            {'id': 'a1', 'hero': True, 'len': 400, 'label': 'اتاق سرور، قلب پردازش سازمان', 'world': a1(),
             'caps': [(1, 'p', 'پردازش سازمان این‌جا اتفاق می‌افتد', 'نرم‌افزارهای داخلی، پایگاه داده و سرویس‌هایی که کاربران هر روز به آن‌ها تکیه می‌کنند.'),
                      (2, 'p', 'هر لرزش، همه‌جا دیده می‌شود', 'مشکل سرور در اتاق سرور نمی‌ماند؛ کندی و توقف در همهٔ طبقه‌ها پیدا می‌شود.')],
             'timeline': {'bounds': [.22, .6], 'stops': [0, .42, 1],
                          'cam': [WIDE, [.14, .38, ROOM], [.62, .86, WIDE]]}},
            {'id': 'a2', 'len': 320, 'label': 'سرور بیش از نیاز یا کمتر از نیاز', 'world': a2(),
             'caps': [(0, 'lead', 'بیش از نیاز، سرمایهٔ خوابیده', 'سرورهایی که بیش از نیاز خریده شده‌اند، سرمایه‌ای بی‌استفاده روی دست سازمان می‌گذارند.'),
                      (1, 'h3', 'کمتر از نیاز، توقف در اوج کار', 'سرور ناکافی درست در پرکارترین لحظه‌ها کند می‌شود یا از کار می‌افتد.')],
             'timeline': {'bounds': [.62], 'stops': [.36, 1],
                          'cam': [WIDE, [.04, .3, ROOM_W]]}},
            {'id': 'a3', 'blueprint': True, 'len': 600, 'label': 'روش DaranX در طراحی دیتاسنتر و سرور', 'world': a3(),
             'caps': [(0, 'lead', 'اول بار واقعی را می‌شناسیم', 'چه سرویس‌هایی، با چه حجمی و چه اهمیتی؟ رشد یکی دو سال آینده چه فشاری اضافه می‌کند؟'),
                      (1, 'h3', 'مجازی‌سازی به‌جای سرورهای کم‌بار', 'به‌جای چند سرور کم‌بار، بستری منعطف و جمع‌وجور که منابع را بهینه مصرف می‌کند.'),
                      (2, 'h3', 'افزونگی برای سرویس‌های حیاتی', 'میزبان دوم آماده است تا یک خرابی، کل سازمان را زمین نزند.'),
                      (3, 'h3', 'برق، خنک‌کاری و فضا', 'آمادگی اتاق سرور از نظر برق، خنک‌کاری و فضای فیزیکی از همان طراحی سنجیده می‌شود.'),
                      (4, 'h3', 'پشتیبان‌گیری و بازیابی از روز اول', 'مسیر روشن پشتیبان‌گیری و بازیابی، پیش از آنکه به آن نیاز پیدا کنید.')],
             'timeline': {'bounds': [.22, .4, .58, .76], 'stops': [.1, .31, .49, .67, .9],
                          'cam': [WIDE, [.24, .36, ROOM_W]],
                          'draws': [['a3-bout', .78, .88], ['a3-bin', .86, .96]]}},
            {'id': 'a4', 'blueprint': True, 'len': 420, 'label': 'یک خرابی در دیتاسنتر طراحی‌شده', 'world': a4(),
             'caps': [(0, 'lead', 'یک خرابی، بدون توقف', 'میزبان اول از کار افتاده، اما سرویس‌ها روی میزبان دوم ادامه پیدا می‌کنند.'),
                      (1, 'h3', 'درست‌اندازه و آمادهٔ رشد', 'ظرفیت امروز با نیاز امروز جور است و برای رشد فردا جا دارد.'),
                      (2, 'h3', 'همه چیز سر جای درستش.', 'دیتاسنتر و سرور را برای مسئلهٔ واقعی شما طراحی کنیم.', S.final_actions())],
             'timeline': {'bounds': [.45, .75], 'stops': [.3, .6, 1],
                          'cam': [ROOM_W, [.78, .92, WIDE]],
                          'vars': [['a4-g1', 't', .46, .6]]}},
        ],
        'states': [
            ('a1', '.flow', [1], True, 'stroke:var(--brand);stroke-dasharray:4 14;animation:flow 1.1s linear infinite'),
            ('a1', '.riser', [1], True, 'stroke:var(--brand);stroke-dasharray:4 14;animation:flow .9s linear infinite'),
            ('a1', '.reqs', [1], True, 'opacity:1'),
            ('a1', '.ripple circle', [2], False, 'animation:rip 1.6s var(--ease-out) infinite;animation-delay:calc(var(--k) * .5s)'),
            ('a1', '.s2 .unit', [2], True, 'fill:var(--alert)'),
            ('a1', '.room:not(.room-b)', [2], True, 'fill:rgba(242,112,94,.10)'),
            ('a1', '.warn', [2], True, 'opacity:1'),
            ('a2', '.under', [0], False, 'opacity:0'),
            ('a2', '.over', [1], False, 'opacity:0'),
            ('a2', '.over .chip', [0], True, 'opacity:1'),
            ('a2', '.under .chip,.under .q', [1], True, 'opacity:1'),
            ('a2', '.hotr .unit', [1], True, 'fill:var(--alert)'),
            ('a2', '.warn', [1], True, 'opacity:1'),
            ('a2', '.room:not(.room-b)', [1], True, 'fill:rgba(242,112,94,.08)'),
            ('a3', '.L-cab', [0], False, 'opacity:.15'),
            ('a3', ':is(.ask,.hot,.hot-t)', [0], True, 'opacity:1'),
            ('a3', '.smalls', [1, 2, 3, 4], True, 'opacity:0'),
            ('a3', '.h1', [1, 2, 3, 4], True, 'opacity:1'),
            ('a3', ':is(.h2,.sync)', [2, 3, 4], True, 'opacity:1'),
            ('a3', '.infra', [3], True, 'opacity:1'),
            ('a3', ':is(.bkp,.bk-arrows)', [4], True, 'opacity:1'),
            ('a4', ':is(.h1,.h2,.sync)', [0, 1, 2], True, 'opacity:1'),
            ('a4', '.h1 .vm', [0, 1, 2], True, 'opacity:.25'),
            ('a4', '.brk', [0], True, 'opacity:1'),
            ('a4', '.ok-b', [0], True, 'opacity:1'),
            ('a4', '.h2 .vm2', [0, 1, 2], True, 'opacity:1'),
            ('a4', '.capg', [1, 2], True, 'opacity:1'),
            ('a4', ':is(.lane,.riser)', [0, 1, 2], False, 'animation:flow 1.4s linear infinite'),
        ],
        'css': """.srack,.host,.sync,.over,.under{transition:opacity .5s var(--ease-out)}
.smalls{transition:opacity .4s ease}
.h1,.h2,.sync,.infra,.bkp,.bk-arrows,.capg,.reqs,.ok-b,.brk,.q,.over .chip,.under .chip{opacity:0}
.sync{stroke:var(--brand);stroke-width:4;stroke-dasharray:4 6}
.h2 .vm2:nth-child(n+4){opacity:.25}
#a4 .h2 .vm2{transition-delay:calc(300ms + var(--k) * 90ms)}
#a1 .room-f2{transition-delay:150ms}#a1 .room-f3{transition-delay:300ms}#a1 .room-f1{transition-delay:220ms}""",
        'summary': {'h2': 'طراحی دیتاسنتر و سرور سازمانی در DaranX',
                    'paras': ['DaranX مرکز داده و سرورهای سازمان را بر اساس بار واقعی پردازشی و مسیر توسعهٔ سازمان طراحی، انتخاب، پیکربندی و پشتیبانی می‌کند؛ نه بیش از نیاز و نه ناکافی.',
                              'دیتاسنتر و سرور جایی است که نرم‌افزارهای داخلی، پایگاه داده و سرویس‌های روزمرهٔ کاربران اجرا می‌شوند. سرور بیش از نیاز سرمایه را بی‌استفاده می‌گذارد و سرور ناکافی در پرکارترین لحظه‌ها کند می‌شود یا متوقف می‌شود.'],
                    'bullets': ['شناخت بار واقعی: سرویس‌ها، حجم، اهمیت و رشد یکی دو سال آینده.', 'مجازی‌سازی برای استفادهٔ بهینه از منابع به‌جای چند سرور کم‌بار.',
                                'افزونگی برای بخش‌های حیاتی تا یک خرابی کل سازمان را زمین نزند.', 'سنجش آمادگی برق، خنک‌کاری و فضای فیزیکی.',
                                'مسیر روشن پشتیبان‌گیری و بازیابی، و پیکربندی و مستندسازی منظم.']},
        'why': ['کارایی متناسب با نیاز', 'کنترل هزینه در خرید و بهره‌برداری', 'آمادگی برای توسعهٔ آینده'],
        'method': [('شناخت', 'سرویس‌ها، حجم و اهمیت آن‌ها و رشد آینده را می‌شناسیم.'), ('طراحی', 'پردازنده، حافظه، معماری، مجازی‌سازی و افزونگی را طراحی می‌کنیم.'),
                   ('اجرا', 'پیکربندی و مستندسازی منظم سرورها و اتاق سرور.'), ('پشتیبانی', 'نگهداری و توسعهٔ بستر پردازشی پس از تحویل.')],
        'faq': [('اندازهٔ درست سرور را چطور مشخص می‌کنید؟', 'با شناخت بار واقعی: چه سرویس‌هایی با چه حجم و اهمیتی اجرا می‌شوند، رشد یکی دو سال آینده چقدر است و کدام سرویس‌ها نباید حتی یک لحظه از دسترس خارج شوند. پردازنده، حافظه و معماری از همین پاسخ‌ها درمی‌آیند.'),
                ('مجازی‌سازی چه فایده‌ای دارد؟', 'به‌جای چند سرور کم‌بار، بستری منعطف و جمع‌وجور می‌سازد که از منابع به‌شکل بهینه استفاده می‌کند.'),
                ('برای خرابی سرور چه پیش‌بینی می‌کنید؟', 'برای بخش‌های حیاتی افزونگی در نظر می‌گیریم و مسیر پشتیبان‌گیری و بازیابی را از همان ابتدا طراحی می‌کنیم.'),
                ('اتاق سرور را هم بررسی می‌کنید؟', 'بله. آمادگی از نظر برق، خنک‌کاری و فضای فیزیکی در طراحی سنجیده می‌شود.'),
                ('بعد از تحویل چه می‌شود؟', 'سرورها را مستند و مرتب تحویل می‌دهیم و پشتیبانی از همان مرحلهٔ طراحی در نظر گرفته می‌شود تا توسعه و عیب‌یابی ساده بماند.')],
        'related': {'h2': 'بقیهٔ حوزهٔ زیرساخت', 'links': [('شبکه', 'network.html'), ('ذخیره‌سازی', 'storage.html'), ('برق و UPS', 'power-ups.html'),
                                                          ('زیرساخت پسیو', 'passive.html'), ('همهٔ حوزهٔ زیرساخت', 'infrastructure.html')]},
        'cta': {'h2': 'دیتاسنتر و سرور را برای مسئلهٔ واقعی شما طراحی کنیم.', 'p': 'اول نیاز شما را مشخص می‌کنیم، بعد می‌گوییم چه چیزی واقعاً لازم دارید.'},
    }
