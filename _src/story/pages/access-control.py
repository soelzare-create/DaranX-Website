"""کنترل تردد: مدیریت ورود و خروج (before/after).
1 a secure network but an open server-room door: anyone walks in
2 method (blueprint): zones and hours, access level per role, every entry logged, integrated with CCTV
3 smooth for the authorised, closed for everyone else; reliable at the critical moment
"""
from kit import *
import layout as S

WIDE = {'z': 1, 'x': 540, 'y': 470}
LOW = {'z': 1.15, 'x': 560, 'y': 640}
ENTRY, FDOOR, SDOOR = (206, 574), (700, 234), (760, 704)


def base():
    return [shell(), group('L-cab', neat_cables()), desks(), aps(), switches(), phone(), rack(), server_icon(), cloud(),
            '<line class="part" x1="700" y1="150" x2="700" y2="234"/>', door(*ENTRY, 86, 'door edoor'), door(*FDOOR, 86, 'door fdoor', side=-1),
            door(*SDOOR, 86, 'door sdoor', side=-1)]


def a1():
    return world('a1', base() + [
        group('safe', shield_box(), chip(560, 120, 'شبکه امن است', 'chip', 'shield-check')),
        f'<g class="mvp" id="a1-mv" transform="translate(600 744)">{person(0, 0, "who bad", 30)}</g>',
        pin(SDOOR[0], SDOOR[1] - 40, 'در اتاق سرور باز است', 'pin alert opn', 'door-open', below=False),
    ])


def zones():
    return group('zones',
                 '<rect class="z-pub" x="208" y="328" width="784" height="324" rx="8"/>',
                 '<rect class="z-res" x="704" y="158" width="288" height="154" rx="8"/>',
                 '<rect class="z-sen" x="764" y="668" width="228" height="124" rx="8"/>',
                 chip(400, 470, 'عمومی', 'chip zc'), chip(846, 200, 'محدود', 'chip zc'), chip(846, 640, 'حساس', 'chip zc'))


def a2():
    rows = [('ورود به بخش مالی', False), ('ورود به اتاق سرور', False), ('تلاش ناموفق برای ورود', True)]
    return world('a2', base() + [
        zones(), chip(330, 120, 'چه کسی؟', 'chip ask', 'user'), chip(560, 120, 'به کدام فضا؟', 'chip ask', 'door'), chip(790, 120, 'چه ساعتی؟', 'chip ask', 'clock'),
        group('badges', chip(300, 842, 'کارکنان', 'chip', 'identification-card'), chip(520, 842, 'مالی', 'chip', 'identification-card'),
              chip(740, 842, 'فنی و اتاق سرور', 'chip', 'identification-card')),
        log_panel(300, 812, 480, rows),
        group('cctvl', use('video-camera', 690, 690, 28), cone(690, 698, 20, 120, 28, 'cone'), path(f'M{SDOOR[0] - 18} {SDOOR[1] + 36} C730 720 710 706 700 700', 'acc')),
    ])


def a3():
    return world('a3', base() + [
        zones(),
        f'<g class="mvp" id="a3-ok" transform="translate(110 618)">{person(0, 0, "who ok", 30)}</g>',
        f'<g class="mvp2" id="a3-no" transform="translate(600 744)">{person(0, 0, "who bad", 30)}</g>',
        group('deny', use('prohibit', SDOOR[0] - 30, SDOOR[1] - 24, 30)), group('allow', use('check-circle', ENTRY[0] + 34, ENTRY[1] - 24, 26)),
        chip(560, 842, 'باز برای مجاز، بسته برای غیرمجاز', 'chip ok-b', 'check-circle'),
        group('stable', chip(330, 120, 'در لحظهٔ حساس کار می‌کند', 'chip', 'check-circle'), chip(720, 120, 'پشتیبانی مداوم', 'chip', 'wrench')),
    ])


def build():
    return {
        'file': 'access-control.html', 'hub': 'security',
        'title': 'کنترل تردد سازمانی و امنیت فیزیکی | DaranX',
        'description': 'DaranX کنترل تردد سازمان را متناسب با نقشه و سطح دسترسی هر نقش طراحی و اجرا می‌کند؛ ثبت و گزارش ترددها، یکپارچگی با دوربین و پایداری در لحظهٔ حساس.',
        'keywords': 'کنترل تردد, اکسس کنترل, امنیت فیزیکی, کارت تردد, سطح دسترسی, ثبت تردد',
        'crumbs': [('راهکارها', 'solutions.html'), ('امنیت', 'security.html'), ('کنترل تردد', 'access-control.html')],
        'badge': 'امنیت · کنترل تردد',
        'h1': 'مدیریت ورود و خروج <em>به فضاهای سازمان</em>',
        'verdict': 'امنیت فیزیکی را با کنترل تردد هوشمند و متناسب با سطح مجاز هر فرد تأمین می‌کنیم؛ امن برای سازمان و بی‌دردسر برای افراد مجاز.',
        'cta2': ('در باز چه می‌کند؟', '#a2'),
        'service': {'name': 'طراحی و اجرای کنترل تردد', 'type': 'Physical access control',
                    'desc': 'کنترل تردد افراد به مناطق مختلف بر اساس سطح دسترسی، همراه با ثبت و گزارش ترددها.'},
        'acts': [
            {'id': 'a1', 'hero': True, 'len': 300, 'label': 'شبکهٔ امن و درِ باز اتاق سرور', 'world': a1(),
             'caps': [(1, 'p', 'شبکه امن، اما در باز', 'چه فایده که شبکه امن باشد اما هر کسی بتواند وارد اتاق سرور شود؟ امنیت فیزیکی مکمل امنیت دیجیتال است.')],
             'timeline': {'bounds': [.3], 'stops': [0, 1], 'cam': [WIDE, [.3, .7, LOW]], 'moves': [['a1-mv', [[600, 744], [700, 744], [840, 744]], .45, .95]]}},
            {'id': 'a2', 'blueprint': True, 'len': 500, 'label': 'روش DaranX در طراحی کنترل تردد', 'world': a2(),
             'caps': [(0, 'lead', 'کدام فضا، چه کسی، چه ساعتی؟', 'اول مشخص می‌کنیم کدام فضاها حساس‌اند، چه کسانی باید به آن‌ها دسترسی داشته باشند و در چه ساعاتی.'),
                      (1, 'h3', 'سطح دسترسی هر نقش', 'هر نقش به همان مناطقی دسترسی دارد که کارش لازم دارد؛ جریان کار روزمره روان می‌ماند.'),
                      (2, 'h3', 'ثبت و گزارش هر تردد', 'هر ورود و خروج ثبت می‌شود و سابقه‌ای روشن از ترددها می‌ماند.'),
                      (3, 'h3', 'یکپارچه با دوربین مداربسته', 'رویداد هر در با نظارت تصویری هماهنگ است، نه یک قفل جدا روی یک در.')],
             'timeline': {'bounds': [.25, .5, .75], 'stops': [.1, .37, .62, .9],
                          'cam': [WIDE, [.52, .62, {'z': .92, 'x': 540, 'y': 560}], [.76, .86, LOW]]}},
            {'id': 'a3', 'blueprint': True, 'len': 420, 'label': 'کنترل تردد در عمل', 'world': a3(),
             'caps': [(0, 'lead', 'برای مجاز روان، برای غیرمجاز بسته', 'فرد مجاز بی‌دردسر عبور می‌کند و جلوی دسترسی غیرمجاز گرفته می‌شود.'),
                      (1, 'h3', 'پایدار در لحظهٔ حساس', 'دری که در لحظهٔ حساس باز نشود یا بسته نماند، خودش مشکل است؛ پایداری اولویت ماست.'),
                      (2, 'h3', 'همه چیز سر جای درستش.', 'کنترل تردد را برای مسئلهٔ واقعی شما طراحی کنیم.', S.final_actions())],
             'timeline': {'bounds': [.45, .75], 'stops': [.3, .6, 1],
                          'cam': [{'z': .95, 'x': 520, 'y': 600}, [.78, .9, WIDE]],
                          'moves': [['a3-ok', [[110, 618], [206, 618], [320, 618]], .04, .24], ['a3-no', [[600, 744], [700, 744], [712, 744]], .06, .24]]}},
        ],
        'states': [
            ('a1', ':is(.safe,.safe .shield-box,.safe .shield)', [1], True, 'opacity:1'),
            ('a1', ':is(.mvp,.opn)', [1], True, 'opacity:1'),
            ('a1', '.sdoor .dr', [1], True, 'fill:var(--alert);transform:scaleX(.3)'),
            ('a2', '.ask', [0], True, 'opacity:1'),
            ('a2', '.zones', [0, 1], True, 'opacity:1'),
            ('a2', '.badges', [1], True, 'opacity:1'),
            ('a2', '.door .rd-led', [1, 2, 3], True, 'fill:var(--brand)'),
            ('a2', ':is(.logp,.lrow)', [2], True, 'opacity:1'),
            ('a2', '.cctvl', [3], True, 'opacity:1'),
            ('a3', ':is(.mvp,.mvp2,.deny,.allow,.ok-b)', [0], True, 'opacity:1'),
            ('a3', '.zones', [0], True, 'opacity:.6'),
            ('a3', '.door .rd-led', [0, 1, 2], True, 'fill:var(--brand)'),
            ('a3', '.stable', [1], True, 'opacity:1'),
        ],
        'css': """.safe,.mvp,.mvp2,.pin,.zones,.ask,.badges,.logp,.cctvl,.deny,.allow,.ok-b,.stable{opacity:0;transition:opacity .5s var(--ease-out)}
.part{stroke:rgba(238,243,249,.32);stroke-width:2.5}
.z-pub{fill:rgba(127,176,223,.05);stroke:rgba(127,176,223,.4);stroke-width:2;stroke-dasharray:6 8}
.z-res{fill:rgba(127,176,223,.12);stroke:var(--brand);stroke-width:2;stroke-dasharray:6 8}
.z-sen{fill:rgba(242,112,94,.08);stroke:var(--alert);stroke-width:2;stroke-dasharray:6 8}
.deny .ic{fill:var(--alert)}.allow .ic{fill:#7FD1A8}""",
        'summary': {'h2': 'کنترل تردد و امنیت فیزیکی در DaranX',
                    'paras': ['کنترل تردد، مدیریت ورود و خروج افراد به فضاهای سازمان است؛ اینکه هر کس فقط به جایی که مجاز است دسترسی داشته باشد و هر تردد ثبت شود. DaranX کنترل تردد را متناسب با نقشه و نیاز واقعی سازمان طراحی، اجرا و پشتیبانی می‌کند.',
                              'امنیت فیزیکی مکمل امنیت دیجیتال است و بدون آن بقیهٔ لایه‌ها ناقص می‌مانند. کنترل تردد خوب هم امن است و هم برای افراد مجاز بی‌دردسر.'],
                    'bullets': ['شناخت فضاهای حساس، افراد مجاز و ساعات دسترسی.', 'تعریف مناطق و سطوح دسترسی بر اساس نقش.', 'روش‌های شناسایی و صدور مجوز.',
                                'ثبت و گزارش ترددها.', 'یکپارچگی با دوربین مداربسته و دسترسی و پایش، و پایداری در لحظهٔ حساس.']},
        'why': ['امنیت فیزیکی مناطق حساس', 'کنترل ورود بر اساس نقش', 'گزارش و سابقهٔ تردد'],
        'method': [('شناخت', 'فضاهای حساس، افراد مجاز و ساعات دسترسی را مشخص می‌کنیم.'), ('طراحی', 'مناطق، سطوح دسترسی، روش شناسایی و ثبت ترددها.'),
                   ('اجرا', 'اجرای تمیز و قابل‌اتکا.'), ('پشتیبانی', 'نگهداری مداوم تا درها در لحظهٔ حساس درست کار کنند.')],
        'faq': [('سطح دسترسی افراد را چطور تعیین می‌کنید؟', 'بر اساس نقش: کدام فضاها حساس‌اند، چه کسانی و در چه ساعاتی باید به آن‌ها دسترسی داشته باشند.'),
                ('ترددها ثبت می‌شوند؟', 'بله. هر ورود و خروج ثبت می‌شود و گزارش و سابقهٔ ترددها در دسترس است.'),
                ('با دوربین مداربسته یکپارچه می‌شود؟', 'بله. کنترل تردد بخشی از یک راهکار امنیتی کامل است که با دوربین مداربسته و دسترسی و پایش هماهنگ می‌شود.'),
                ('اگر در در لحظهٔ حساس کار نکند چه؟', 'پایداری برای ما اولویت است؛ اجرا قابل‌اتکا انجام می‌شود و پشتیبانی ادامه دارد تا درها همیشه درست کار کنند.')],
        'related': {'h2': 'بقیهٔ حوزهٔ امنیت', 'links': [('امنیت شبکه', 'network-security.html'), ('دوربین مداربسته', 'cctv.html'), ('دسترسی و پایش', 'access-monitoring.html'),
                                                        ('همهٔ حوزهٔ امنیت', 'security.html')]},
        'cta': {'h2': 'کنترل تردد را برای مسئلهٔ واقعی شما طراحی کنیم.', 'p': 'اول نیاز شما را مشخص می‌کنیم، بعد می‌گوییم چه چیزی واقعاً لازم دارید.'},
    }
