"""امنیت شبکه: محافظت از مسیرهای داده (problem hunt + before/after).
1 the data paths; threats do not only come from outside
2 a flat network: one intrusion reaches everywhere
3 method (blueprint): assets and risks, segmentation, least privilege, traffic monitoring, known threats
4 the same intrusion, contained; kept up to date
"""
from kit import *
import layout as S

WIDE = {'z': 1, 'x': 540, 'y': 470}
IN_D = f'M{160} {CLOUD[1] + 40} V{ROUTER_Y + 8} H{RACK[0] + 8}'          # internet -> router (reverse of the uplink)


def base(lanes=False):
    return [shell(), group('L-cab', neat_cables(lanes=lanes)), furniture()]


def threats():
    return ''.join([
        pin(CLOUD[0] + 40, CLOUD[1] - 70, 'حملهٔ بیرونی', 'pin alert t1', 'bug', below=False),
        pin(650, 402, 'دسترسی نادرست', 'pin alert t2', 'user', below=False),
        pin(845, 650, 'پیکربندی بازمانده', 'pin alert t3', 'gear', below=False),
        pin(870, 390, 'شبکهٔ بخش‌بندی‌نشده', 'pin alert t4', 'tree-structure', below=False),
    ])


def a1():
    return world('a1', base() + [threats(), mover('a1-in', IN_D, 'a1-bad', 8, 'pk bad')])


def spread_path():
    px, py = desk_port('f2', 3)
    return f'M{px} {py} V{fb("f2") - 8} H{RISER - 18} V{fb("f2") - 34} H{RISER} V{fb("f3") - 34} H{RISER - 18} V{fb("f3") - 8} H{300}'


def a2():
    return world('a2', base() + [
        pin(650, 402, 'یک نفوذ', 'pin alert t2', 'bug', below=False),
        mover('a2-sp', spread_path(), 'a2-dot', 8, 'pk bad'),
        floor_warnings({'f3': 'آلوده', 'f2': 'آلوده', 'f1': 'آلوده'}),
        chip(560, 842, 'شبکهٔ تخت: نفوذ به همه‌جا می‌رسد', 'chip alert spread-c', 'warning'),
    ])


def access_lines():
    return ''.join([
        path('M412 418 C470 340 600 300 690 238', 'acc bad acc-x'), group('noacc', use('prohibit', 552, 316, 28)),
        path('M412 418 C500 430 580 420 650 404', 'acc acc-ok'), group('okacc', use('check-circle', 534, 432, 24)),
        group('fin', use('receipt', 690, 222, 28)), group('sal', use('files', 650, 392, 26)),
    ])


def a3():
    return world('a3', base(lanes=True) + [
        asks([('f3', 'چه چیزی محافظت شود؟'), ('f2', 'چه کسی به چه چیزی؟'), ('f1', 'کدام مسیر حساس‌تر؟')], x=730), hot(('f3', 'b')),
        group('zones', *[zone(f, 'zone-l') for f in ('f3', 'f2', 'f1', 'b')]),
        access_lines(),
        group('watch', pulse_line(470, 744, 14, 'mon-p', 'eye'), pin(700, 600, 'رفتار غیرعادی', 'pin alert anom', 'warning', below=False)),
        firewall(300, 768), mover('a3-in', IN_D, 'a3-bad', 8, 'pk bad'), xmark(318, 768, 12),
        chip(300, 842, 'تهدید شناخته‌شده متوقف شد', 'chip ok-b', 'shield-check'),
    ])


def a4():
    return world('a4', base(lanes=True) + [
        group('zones', *[zone(f, 'zone-l') for f in ('f3', 'f2', 'f1', 'b')]),
        firewall(300, 768),
        pin(650, 402, 'نفوذ مهار شد', 'pin alert t2', 'bug', below=False),
        chip(700, 230, 'سالم', 'chip ok-b z3', 'check-circle'), chip(700, 570, 'سالم', 'chip ok-b z1', 'check-circle'),
        chip(560, 842, 'به‌روزرسانی و پایش پیوسته', 'chip upd', 'arrows-clockwise'),
    ])


def build():
    return {
        'file': 'network-security.html', 'hub': 'security',
        'title': 'امنیت شبکهٔ سازمانی: جداسازی، پایش و کنترل دسترسی | DaranX',
        'description': 'DaranX امنیت شبکهٔ سازمان را از مرحلهٔ طراحی می‌سازد؛ با جداسازی بخش‌ها، کمترین دسترسی لازم، پایش ترافیک و محافظت در برابر تهدیدهای شناخته‌شده.',
        'keywords': 'امنیت شبکه سازمانی, جداسازی شبکه, کنترل دسترسی, پایش ترافیک شبکه, فایروال, کاهش ریسک نفوذ',
        'crumbs': [('راهکارها', 'solutions.html'), ('امنیت', 'security.html'), ('امنیت شبکه', 'network-security.html')],
        'badge': 'امنیت · امنیت شبکه',
        'h1': 'محافظت از <em>مسیرهای ارتباطی و داده</em>',
        'verdict': 'امنیت را یک افزودنی پایانی نمی‌بینیم؛ آن را از همان مرحلهٔ طراحی شبکه و به‌عنوان بخشی جدانشدنی از آن می‌سازیم.',
        'cta2': ('تهدیدها از کجا می‌آیند؟', '#a2'),
        'service': {'name': 'امنیت شبکهٔ سازمانی', 'type': 'Network security',
                    'desc': 'کنترل دسترسی، جداسازی و پایش ترافیک برای جلوگیری از نفوذ و سوءاستفاده در بستر شبکه.'},
        'acts': [
            {'id': 'a1', 'hero': True, 'len': 400, 'label': 'مسیرهای داده و تهدیدهایی که از آن‌ها عبور می‌کنند', 'world': a1(),
             'caps': [(1, 'p', 'داده از این مسیرها عبور می‌کند', 'ایمیل، فایل، نرم‌افزارهای داخلی و اینترنت؛ همهٔ ارتباط سازمان روی همین مسیرهاست.'),
                      (2, 'p', 'تهدید فقط از بیرون نمی‌آید', 'یک دسترسی نادرست، شبکهٔ بخش‌بندی‌نشده یا پیکربندی بازمانده، به‌اندازهٔ حملهٔ بیرونی خطرناک است.')],
             'timeline': {'bounds': [.22, .6], 'stops': [0, .42, 1], 'cam': [WIDE], 'dots': [['a1-in', 'a1-bad', .62, .9]]}},
            {'id': 'a2', 'len': 300, 'label': 'نفوذ در شبکهٔ بخش‌بندی‌نشده', 'world': a2(),
             'caps': [(0, 'lead', 'یک نفوذ، همه‌جا', 'در شبکه‌ای که بخش‌بندی نشده، نفوذ از یک نقطه به همهٔ طبقه‌ها سرایت می‌کند.')],
             'timeline': {'bounds': [], 'stops': [.6], 'cam': [WIDE], 'dots': [['a2-sp', 'a2-dot', .05, .55]]}},
            {'id': 'a3', 'blueprint': True, 'len': 600, 'label': 'روش DaranX در امنیت شبکه', 'world': a3(),
             'caps': [(0, 'lead', 'اول دارایی‌ها و ریسک‌ها', 'چه چیزی باید محافظت شود، چه کسی به چه چیزی دسترسی دارد و کدام مسیرها حساس‌ترند؟'),
                      (1, 'h3', 'جداسازی منطقی بخش‌ها', 'هر بخش در محدودهٔ خودش؛ تا یک نفوذ به همه‌جا سرایت نکند.'),
                      (2, 'h3', 'کمترین دسترسی لازم', 'هر کس فقط به همان چیزی دسترسی دارد که برای کارش لازم است.'),
                      (3, 'h3', 'پایش ترافیک و رفتار غیرعادی', 'ترافیک پیوسته پایش می‌شود و رفتار غیرعادی زود شناسایی می‌شود.'),
                      (4, 'h3', 'محافظت در برابر تهدید شناخته‌شده', 'تهدیدهای شناخته‌شده پیش از رسیدن به شبکهٔ داخلی متوقف می‌شوند.')],
             'timeline': {'bounds': [.22, .4, .58, .76], 'stops': [.1, .31, .49, .67, .9],
                          'cam': [WIDE, [.78, .88, {'z': 1.35, 'x': 470, 'y': 700}]],
                          'dots': [['a3-in', 'a3-bad', .78, .9]]}},
            {'id': 'a4', 'blueprint': True, 'len': 420, 'label': 'همان نفوذ در شبکهٔ امن‌شده', 'world': a4(),
             'caps': [(0, 'lead', 'همان نفوذ، این بار مهار شده', 'نفوذ در همان بخش می‌ماند و بقیهٔ سازمان کارش را ادامه می‌دهد.'),
                      (1, 'h3', 'امنیت، یک فرآیند پیوسته', 'تهدیدها ثابت نمی‌مانند؛ پشتیبانی، به‌روزرسانی و پایش ادامه دارد.'),
                      (2, 'h3', 'همه چیز سر جای درستش.', 'امنیت شبکه را برای مسئلهٔ واقعی شما طراحی کنیم.', S.final_actions())],
             'timeline': {'bounds': [.45, .75], 'stops': [.3, .6, 1], 'cam': [WIDE]}},
        ],
        'states': [
            ('a1', ':is(.flow,.riser,.uplink)', [1], True, 'stroke:var(--brand);stroke-dasharray:4 14;animation:flow 1.1s linear infinite'),
            ('a1', '.pin', [2], True, 'opacity:1'),
            ('a1', '.pk', [2], False, 'opacity:1'),
            ('a2', '.pin', [0], True, 'opacity:1'),
            ('a2', '.pk', [0], False, 'opacity:1'),
            ('a2', '.room:not(.room-b)', [0], True, 'fill:rgba(242,112,94,.12)'),
            ('a2', '.cab', [0], True, 'stroke:rgba(242,112,94,.55)'),
            ('a2', ':is(.warn,.spread-c)', [0], True, 'opacity:1'),
            ('a3', '.L-cab', [0], False, 'opacity:.15'),
            ('a3', ':is(.ask,.hot,.hot-t)', [0], True, 'opacity:1'),
            ('a3', '.zones', [1, 2, 3, 4], True, 'opacity:1'),
            ('a3', ':is(.acc,.noacc,.okacc,.fin,.sal)', [2], True, 'opacity:1'),
            ('a3', ':is(.watch,.watch .mon-p,.watch .pin)', [3], True, 'opacity:1'),
            ('a3', ':is(.fw,.ok-b)', [4], True, 'opacity:1'),
            ('a3', '.pk', [4], False, 'opacity:1'),
            ('a3', '.brk', [4], False, 'opacity:1'),
            ('a4', ':is(.zones,.fw)', [0, 1, 2], True, 'opacity:1'),
            ('a4', '.room-f2', [0], True, 'fill:rgba(242,112,94,.12)'),
            ('a4', '.zone-f2', [0], True, 'stroke:var(--alert)'),
            ('a4', ':is(.pin,.z3,.z1)', [0], True, 'opacity:1'),
            ('a4', '.upd', [1], True, 'opacity:1'),
            ('a4', ':is(.lane,.riser,.uplink)', [1, 2], False, 'animation:flow 1.4s linear infinite'),
        ],
        'css': """.pin,.zones,.acc,.noacc,.okacc,.fin,.sal,.watch,.fw,.ok-b,.spread-c,.upd,.brk{opacity:0;transition:opacity .5s var(--ease-out)}
.pk.bad{fill:var(--alert);stroke:var(--alert)}
.noacc .ic{fill:var(--alert)}.okacc .ic{fill:#7FD1A8}.fin .ic,.sal .ic{fill:var(--brand)}
#a1 .pin{transition-delay:calc(var(--k,0) * 120ms)}
#a2 .room-f2{transition-delay:0ms}#a2 .room-f3{transition-delay:400ms}#a2 .room-f1{transition-delay:700ms}""",
        'summary': {'h2': 'امنیت شبکهٔ سازمانی در DaranX',
                    'paras': ['امنیت شبکه یعنی محافظت از مسیرهایی که داده و ارتباط سازمان از آن‌ها عبور می‌کند. DaranX امنیت را از همان مرحلهٔ طراحی شبکه و به‌عنوان بخشی جدانشدنی از آن در نظر می‌گیرد.',
                              'تهدیدها لزوماً از بیرون نمی‌آیند؛ یک دسترسی نادرست، شبکهٔ بخش‌بندی‌نشده یا یک پیکربندی بازمانده می‌تواند به‌اندازهٔ یک حملهٔ بیرونی خطرناک باشد. بدون شناخت دارایی‌ها و ریسک‌ها، هر ابزار امنیتی فقط یک هزینهٔ اضافه است.'],
                    'bullets': ['شناخت دارایی‌ها، ریسک‌ها و مسیرهای حساس.', 'جداسازی منطقی بخش‌ها تا یک نفوذ به همه‌جا سرایت نکند.',
                                'کنترل و محدودسازی دسترسی‌ها بر پایهٔ اصل کمترین دسترسی لازم.', 'پایش ترافیک و شناسایی رفتار غیرعادی.',
                                'محافظت در برابر تهدیدهای شناخته‌شده، و پشتیبانی و به‌روزرسانی پیوسته.']},
        'why': ['کاهش ریسک نفوذ', 'کنترل دقیق دسترسی', 'حفاظت داده در حال انتقال'],
        'method': [('شناخت', 'دارایی‌ها، ریسک‌ها و دسترسی‌های لازم را مشخص می‌کنیم.'), ('طراحی', 'جداسازی، کنترل دسترسی، پایش و لایه‌های محافظت.'),
                   ('اجرا', 'پیاده‌سازی هماهنگ با زیرساخت شبکه.'), ('پشتیبانی', 'پایش و به‌روزرسانی در برابر تهدیدهای تازه.')],
        'faq': [('امنیت شبکه را از کجا شروع می‌کنید؟', 'از شناخت دارایی‌ها و ریسک‌ها: چه چیزی باید محافظت شود، چه کسی به چه چیزی باید دسترسی داشته باشد و کدام مسیرها و سرویس‌ها حساس‌ترند.'),
                ('جداسازی شبکه چه فایده‌ای دارد؟', 'با جداسازی منطقی بخش‌ها، یک نفوذ به همهٔ شبکه سرایت نمی‌کند.'),
                ('کمترین دسترسی لازم یعنی چه؟', 'هر کاربر یا سیستم فقط به همان منابعی دسترسی دارد که برای کارش لازم است، نه بیشتر.'),
                ('بعد از اجرا هم امنیت را به‌روز نگه می‌دارید؟', 'بله. تهدیدها ثابت نمی‌مانند؛ پشتیبانی، پایش و به‌روزرسانی بخشی از مسئولیت ماست.')],
        'related': {'h2': 'بقیهٔ حوزهٔ امنیت', 'links': [('دوربین مداربسته', 'cctv.html'), ('دسترسی و پایش', 'access-monitoring.html'), ('کنترل تردد', 'access-control.html'),
                                                        ('شبکه', 'network.html'), ('همهٔ حوزهٔ امنیت', 'security.html')]},
        'cta': {'h2': 'امنیت شبکه را برای مسئلهٔ واقعی شما طراحی کنیم.', 'p': 'اول نیاز شما را مشخص می‌کنیم، بعد می‌گوییم چه چیزی واقعاً لازم دارید.'},
    }
