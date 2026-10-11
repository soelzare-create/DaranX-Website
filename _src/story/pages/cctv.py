"""دوربین مداربسته: دید کامل و مستند (problem hunt + before/after).
1 the always-awake eye; many cameras, still blind spots
2 method (blueprint): sensitive points, coverage without blind spots, light and quality, storage, secure remote access
3 when it matters it was recorded; integrated with access control
"""
from kit import *
import layout as S

WIDE = {'z': 1, 'x': 540, 'y': 470}
NAMES = {'f1': 'پذیرش'}
ENTRY = (206, 574)          # entrance door in the left wall of f1
SDOOR = (760, 704)          # server-room door in the basement
VAULT = (862, 262)          # valuables on f3


def cam(x, y, cls='camx', s=28):
    return group(cls, use('video-camera', x, y, s))


def base():
    return [shell(names=NAMES), group('L-cab', neat_cables()), desks(), aps(), switches(), phone(), rack(), server_icon(), cloud(),
            door(*ENTRY, 86, 'door edoor'), door(*SDOOR, 86, 'door sdoor', side=-1), group('vault', use('vault', *VAULT, 34))]


def wrong_cams():
    """cameras that look at desks: lots of coverage, none on what matters"""
    pts = [(300, 178, 90), (700, 348, 90), (505, 518, 90), (830, 518, 90)]
    return group('wrong', *[cone(x, y + 12, d, 120, 30, 'cone') for x, y, d in pts], *[cam(x, y) for x, y, _ in pts])


def key_cams():
    pts = [(330, 520, 135, 'c-entry'), (690, 688, 30, 'c-server'), (760, 178, 50, 'c-vault')]
    return group('right', *[cone(x, y + 8, d, 150, 32, f'cone {c}') for x, y, d, c in pts], *[cam(x, y) for x, y, _, _ in pts])


def a1():
    return world('a1', base() + [
        group('all', *[cone(x, y + 12, 90, 120, 30, 'cone') for x, y in [(300, 178), (700, 178), (300, 348), (700, 348), (505, 518), (830, 518)]],
              *[cam(x, y) for x, y in [(300, 178), (700, 178), (300, 348), (700, 348), (505, 518), (830, 518)]]),
        wrong_cams(),
        f'<path class="blind" d="M210 560 H340 V656 H210 Z"/><path class="blind" d="M700 700 H786 V792 H700 Z"/>',
        pin(275, 610, 'نقطهٔ کور', 'pin alert bl1', 'eye-slash', below=False), pin(742, 744, 'نقطهٔ کور', 'pin alert bl2', 'eye-slash', below=False),
    ])


def a2():
    return world('a2', base() + [
        asks([('f3', 'چه چیزی ثبت شود؟'), ('f2', 'چند وقت نگهداری شود؟'), ('f1', 'چه کسی تصاویر را ببیند؟')], x=720),
        pin(250, 610, 'ورودی', 'pin p1', 'door-open', below=False), pin(742, 744, 'اتاق سرور', 'pin p2', 'hard-drives', below=False),
        pin(VAULT[0], VAULT[1] + 4, 'دارایی', 'pin p3', 'vault', below=False),
        key_cams(),
        group('light', chip(300, 470, 'دید در شب', 'chip', 'moon'), chip(560, 470, 'کیفیت متناسب با نقطه', 'chip', 'eye')),
        group('store', disks(430, 712, cols=3, rows=2), chip(500, 842, 'مدت نگهداری متناسب با نیاز', 'chip', 'clock-counter-clockwise')),
        group('remote', use('device-mobile', 70, 420, 40), use('lock-key', 98, 400, 20), path('M86 440 C100 480 108 500 112 506', 'acc'),
              chip(115, 360, 'فقط افراد مجاز', 'chip', 'user-check')),
    ])


def a3():
    ticks = ''.join(f'<line class="tick" x1="{320 + k * 40}" y1="824" x2="{320 + k * 40}" y2="852"/>' for k in range(13))
    return world('a3', base() + [
        key_cams(), person(150, 618, 'who bad'),
        group('tl', '<rect class="panel" x="300" y="812" width="520" height="50" rx="10"/>', ticks, '<rect class="clip" x="560" y="818" width="52" height="38" rx="6"/>',
              chip(586, 896, 'رویداد پیدا شد', 'chip ok-b', 'check-circle')),
        path(f'M{ENTRY[0] + 18} {ENTRY[1] + 36} C260 540 300 530 324 524', 'acc link-ac'),
        chip(330, 470, 'یکپارچه با کنترل تردد', 'chip integ', 'identification-card'),
    ])


def build():
    return {
        'file': 'cctv.html', 'hub': 'security',
        'title': 'دوربین مداربسته و نظارت تصویری سازمانی | DaranX',
        'description': 'DaranX نظارت تصویری سازمان را متناسب با نقاط حساس واقعی طراحی و اجرا می‌کند؛ پوشش بدون نقطهٔ کور، ذخیرهٔ متناسب، دسترسی امن از راه دور و یکپارچگی با کنترل تردد.',
        'keywords': 'دوربین مداربسته سازمانی, نظارت تصویری, طراحی CCTV, نقطه کور دوربین, ذخیره تصاویر, دسترسی از راه دور',
        'crumbs': [('راهکارها', 'solutions.html'), ('امنیت', 'security.html'), ('دوربین مداربسته', 'cctv.html')],
        'badge': 'امنیت · دوربین مداربسته',
        'h1': 'دید کامل و مستند <em>بر محیط سازمان</em>',
        'verdict': 'نظارت تصویری را متناسب با نقاط حساس واقعی سازمان طراحی می‌کنیم، نه صرفاً نصب چند دوربین بیشتر.',
        'cta2': ('نقطهٔ کور کجاست؟', '#a2'),
        'service': {'name': 'طراحی و اجرای دوربین مداربسته و نظارت تصویری', 'type': 'CCTV and video surveillance',
                    'desc': 'طراحی و اجرای سامانهٔ نظارت تصویری با پوشش درست نقاط کلیدی و امکان بازبینی رویدادها.'},
        'acts': [
            {'id': 'a1', 'hero': True, 'len': 400, 'label': 'نظارت تصویری و نقطه‌های کور', 'world': a1(),
             'caps': [(1, 'p', 'چشم همیشه‌بیدار سازمان', 'دوربین مداربسته هم بازدارنده است و هم در زمان مشکل، مرجعی مستند برای فهمیدن آنچه رخ داده.'),
                      (2, 'p', 'دوربین زیاد، نقطهٔ کور', 'ارزش نظارت در تعداد دوربین‌ها نیست؛ در پوشش درست نقاط حساس است.')],
             'timeline': {'bounds': [.22, .6], 'stops': [0, .42, 1], 'cam': [WIDE, [.62, .86, {'z': 1.05, 'x': 500, 'y': 560}]]}},
            {'id': 'a2', 'blueprint': True, 'len': 600, 'label': 'روش DaranX در طراحی نظارت تصویری', 'world': a2(),
             'caps': [(0, 'lead', 'اول نقاط حساس را می‌شناسیم', 'کدام نقاط از نظر امنیت، تردد یا ارزش دارایی حساس‌اند؟ چه چیزی، چه مدت و برای چه کسی ثبت شود؟'),
                      (1, 'h3', 'پوشش بدون نقطهٔ کور', 'نوع، تعداد و جای هر دوربین از نقاط حساس درمی‌آید، نه برعکس.'),
                      (2, 'h3', 'نور و کیفیت هر نقطه', 'شرایط نور و کیفیت لازم برای هر نقطه جداگانه سنجیده می‌شود.'),
                      (3, 'h3', 'ذخیره به‌اندازهٔ نیاز', 'ظرفیت و مدت نگهداری تصاویر متناسب با نیاز واقعی طراحی می‌شود.'),
                      (4, 'h3', 'دسترسی امن، از راه دور', 'افراد مجاز از هر جا به تصاویر دسترسی دارند؛ دیگران نه.')],
             'timeline': {'bounds': [.22, .4, .58, .76], 'stops': [.1, .31, .49, .67, .9],
                          'cam': [WIDE, [.6, .68, {'z': 1.3, 'x': 520, 'y': 720}], [.78, .86, {'z': 1.05, 'x': 420, 'y': 470}]]}},
            {'id': 'a3', 'blueprint': True, 'len': 420, 'label': 'رویدادی که ثبت شده است', 'world': a3(),
             'caps': [(0, 'lead', 'وقتی لازم است، ثبت شده', 'در زمان بروز مشکل، دقیقاً همان چیزی که لازم است ثبت شده و پاسخ روشن می‌دهد.'),
                      (1, 'h3', 'هماهنگ با کنترل تردد', 'نظارت تصویری با کنترل تردد، دسترسی و پایش هماهنگ می‌شود، نه یک سامانهٔ جدا.'),
                      (2, 'h3', 'همه چیز سر جای درستش.', 'دوربین مداربسته را برای مسئلهٔ واقعی شما طراحی کنیم.', S.final_actions())],
             'timeline': {'bounds': [.45, .75], 'stops': [.3, .6, 1],
                          'cam': [{'z': 1.05, 'x': 470, 'y': 640}, [.46, .58, {'z': 1.2, 'x': 380, 'y': 560}], [.78, .9, WIDE]]}},
        ],
        'states': [
            ('a1', '.all', [1], True, 'opacity:1'),
            ('a1', '.wrong', [2], False, 'opacity:1'),
            ('a1', ':is(.blind,.bl1,.bl2)', [2], True, 'opacity:1'),
            ('a2', ':is(.ask,.p1,.p2,.p3)', [0], True, 'opacity:1'),
            ('a2', '.right', [1, 2, 3, 4], True, 'opacity:1'),
            ('a2', '.light', [2], True, 'opacity:1'),
            ('a2', '.store', [3], True, 'opacity:1'),
            ('a2', '.remote', [4], True, 'opacity:1'),
            ('a3', '.right', [0, 1, 2], True, 'opacity:1'),
            ('a3', ':is(.who,.tl)', [0], True, 'opacity:1'),
            ('a3', ':is(.link-ac,.integ)', [1], True, 'opacity:1'),
            ('a3', '.edoor .rd-led', [1], True, 'fill:var(--brand)'),
        ],
        'css': """.all,.wrong,.blind,.pin,.right,.light,.store,.remote,.who,.tl,.link-ac,.integ{opacity:0;transition:opacity .5s var(--ease-out)}
.camx .ic{fill:#AEC4DB}.right .camx .ic{fill:var(--brand)}
.vault .ic{fill:#AEC4DB}
.remote .ic{fill:var(--brand)}
.tick{stroke:rgba(127,176,223,.4);stroke-width:2}.clip{fill:rgba(127,176,223,.35);stroke:var(--brand);stroke-width:2}""",
        'summary': {'h2': 'دوربین مداربسته و نظارت تصویری در DaranX',
                    'paras': ['DaranX سامانهٔ نظارت تصویری سازمان را با پوشش درست نقاط کلیدی و امکان بازبینی رویدادها طراحی، اجرا و پشتیبانی می‌کند.',
                              'دوربین مداربسته هم بازدارنده است و هم در زمان بروز مشکل، مرجعی مستند برای فهمیدن آنچه رخ داده. ارزش واقعی آن نه در تعداد دوربین‌ها، بلکه در پوشش درست نقاط حساس است.'],
                    'bullets': ['شناخت نقاط حساس از نظر امنیت، تردد و ارزش دارایی.', 'پوشش بدون نقطهٔ کور برای نقاط کلیدی.', 'سنجش کیفیت و شرایط نور هر نقطه.',
                                'ظرفیت و مدت ذخیره‌سازی تصاویر متناسب با نیاز.', 'دسترسی امن و از راه دور برای افراد مجاز، و هماهنگی با کنترل تردد.']},
        'why': ['بازدارندگی در برابر تخلف', 'مستندسازی رویدادها', 'نظارت پیوسته بر محیط'],
        'method': [('شناخت', 'نقاط حساس، آنچه باید ثبت شود و افراد مجاز را مشخص می‌کنیم.'), ('طراحی', 'نوع، تعداد و جای دوربین‌ها، ذخیره‌سازی و دسترسی امن.'),
                   ('اجرا', 'نصب تمیز و قابل نگهداری.'), ('پشتیبانی', 'نگهداری از ابتدا در نظر گرفته می‌شود تا سامانه روز نیاز از کار نیفتد.')],
        'faq': [('تعداد دوربین لازم را چطور مشخص می‌کنید؟', 'از نقاط حساس: کدام نقاط از نظر امنیت، تردد یا ارزش دارایی مهم‌اند و چه چیزی باید دیده و ثبت شود. نوع، تعداد و جای دوربین‌ها از همین پاسخ‌ها مشخص می‌شود.'),
                ('تصاویر چه مدت نگهداری می‌شوند؟', 'ظرفیت و مدت ذخیره‌سازی تصاویر متناسب با نیاز واقعی شما طراحی می‌شود.'),
                ('امکان دیدن تصاویر از راه دور هست؟', 'بله، به‌صورت امن و فقط برای افراد مجاز.'),
                ('با کنترل تردد هماهنگ می‌شود؟', 'بله. نظارت تصویری بخشی از یک راهکار امنیتی یکپارچه است و با کنترل تردد، دسترسی و پایش هماهنگ می‌شود.')],
        'related': {'h2': 'بقیهٔ حوزهٔ امنیت', 'links': [('امنیت شبکه', 'network-security.html'), ('دسترسی و پایش', 'access-monitoring.html'), ('کنترل تردد', 'access-control.html'),
                                                        ('همهٔ حوزهٔ امنیت', 'security.html')]},
        'cta': {'h2': 'دوربین مداربسته را برای مسئلهٔ واقعی شما طراحی کنیم.', 'p': 'اول نیاز شما را مشخص می‌کنیم، بعد می‌گوییم چه چیزی واقعاً لازم دارید.'},
    }
