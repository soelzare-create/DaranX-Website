"""دسترسی و پایش: چه کسی، به چه چیزی، چه زمانی (before/after).
1 the three questions; too much access is as risky as none
2 method (blueprint): sensitive resources and roles, exactly the access needed, every event logged, unusual access alerts, audit-ready
3 a role changes, access follows; integrated with network security and access control
"""
from kit import *
import layout as S

WIDE = {'z': 1, 'x': 540, 'y': 470}
LOW = {'z': .92, 'x': 540, 'y': 560}
PEOPLE = {'boss': (290, 212), 'fin': (650, 212), 'sal1': (410, 382), 'sal2': (650, 382), 'rec': (530, 552)}
ROLE = {'boss': 'مدیریت', 'fin': 'مالی', 'sal1': 'فروش', 'sal2': 'فروش', 'rec': 'پذیرش'}
RES = {'db': ('database', 420, 'پایگاه داده'), 'fin': ('receipt', 520, 'اسناد مالی'), 'mail': ('envelope-simple', 620, 'ایمیل'), 'files': ('files', 720, 'فایل‌ها')}
NEED = {'boss': ('fin', 'files'), 'fin': ('fin', 'db'), 'sal1': ('files', 'mail'), 'sal2': ('files', 'mail'), 'rec': ('mail',)}


def line(p, r, cls):
    px, py = PEOPLE[p]
    rx = RES[r][1]
    return path(f'M{px} {py + 18} C{px} {py + 160} {rx} {600} {rx} {722}', f'acc {cls}')


def people():
    return group('people', *[group(f'pp pp-{k}', person(x, y, 'who', 30)) for k, (x, y) in PEOPLE.items()])


def resources():
    return group('res', *[group(f'rs rs-{k}', use(ic, x, 744, 34), label(x, 790, name, 'lbl sm rs-t')) for k, (ic, x, name) in RES.items()])


def roles():
    return group('roles', *[chip(x + 62, y, ROLE[k], 'chip role') for k, (x, y) in PEOPLE.items()])


def base():
    return [shell(), desks(), aps(), switches(), cameras(), phone(), people(), resources()]


def a1():
    allx = ''.join(line(p, r, 'bad all-l') for p in PEOPLE for r in RES)
    return world('a1', base() + [
        group('qs', chip(290, 120, 'چه کسی؟', 'chip', 'user'), chip(540, 120, 'به چه چیزی؟', 'chip', 'database'), chip(790, 120, 'چه زمانی؟', 'chip', 'clock')),
        group('allg', allx), chip(560, 842, 'همه به همه‌چیز دسترسی دارند', 'chip alert over-c', 'warning'),
    ])


def a2():
    need = ''.join(line(p, r, 'need-l') for p, rs in NEED.items() for r in rs)
    rows = [('ورود مالی به اسناد مالی', False), ('ورود فروش به فایل‌ها', False), ('دسترسی در ساعت غیرعادی', True)]
    return world('a2', base() + [
        hot(('b',)), roles(), group('needg', need),
        log_panel(300, 812, 480, rows), chip(160, 842, 'هشدار', 'chip alert anom', 'bell-ringing'),
        group('audit', chip(540, 120, 'آمادهٔ ممیزی و انطباق', 'chip', 'clipboard-text')),
    ])


def a3():
    need = ''.join(line(p, r, 'need-l') for p, rs in NEED.items() for r in rs if p != 'sal1')
    return world('a3', base() + [
        group('needg', need), path('M410 400 C410 560 720 600 720 722', 'acc old-l'), path('M470 230 C470 420 520 600 520 722', 'acc new-l'),
        f'<g class="mvp" id="a3-mv" transform="translate(0 0)">{person(0, 0, "who mover", 30)}</g>',
        chip(470, 120, 'نقش عوض شد: از فروش به مالی', 'chip role-c', 'arrows-clockwise'),
        group('integ', chip(330, 842, 'امنیت شبکه', 'chip', 'shield-check'), chip(560, 842, 'کنترل تردد', 'chip', 'identification-card'),
              chip(780, 842, 'دوربین مداربسته', 'chip', 'video-camera')),
    ])


def build():
    return {
        'file': 'access-monitoring.html', 'hub': 'security',
        'title': 'مدیریت دسترسی و پایش رویدادها در سازمان | DaranX',
        'description': 'DaranX دسترسی‌های سازمان را بر اساس نقش طراحی می‌کند و رویدادها را پیوسته ثبت و پایش می‌کند؛ کنترل دقیق، ردیابی، هشدار رفتار غیرعادی و آمادگی برای ممیزی.',
        'keywords': 'مدیریت دسترسی, کنترل دسترسی بر اساس نقش, پایش رویداد, ثبت لاگ, ممیزی امنیت, انطباق',
        'crumbs': [('راهکارها', 'solutions.html'), ('امنیت', 'security.html'), ('دسترسی و پایش', 'access-monitoring.html')],
        'badge': 'امنیت · دسترسی و پایش',
        'h1': 'چه کسی، به چه چیزی، <em>چه زمانی؟</em>',
        'verdict': 'دسترسی‌ها را دقیق و بر اساس نقش طراحی می‌کنیم و رویدادها را طوری ثبت و پایش می‌کنیم که همیشه قابل‌ردیابی بمانند.',
        'cta2': ('دسترسی بیش از حد چه می‌کند؟', '#a2'),
        'service': {'name': 'مدیریت دسترسی و پایش رویدادها', 'type': 'Access management and monitoring',
                    'desc': 'مدیریت سطوح دسترسی همراه با پایش و ثبت پیوستهٔ رویدادها برای ردیابی و پاسخ‌گویی.'},
        'acts': [
            {'id': 'a1', 'hero': True, 'len': 400, 'label': 'دسترسی‌ها در سازمان', 'world': a1(),
             'caps': [(1, 'p', 'سه پرسش ساده', 'چه کسی، به چه چیزی، چه زمانی دسترسی داشته است؟ کنترل و شفافیت دسترسی، پایهٔ امنیت است.'),
                      (2, 'p', 'دسترسی بیش از حد، به‌اندازهٔ بی‌کنترلی خطرناک', 'وقتی همه به همه‌چیز دسترسی دارند، هیچ ابزاری نمی‌داند از چه چیزی محافظت می‌کند.')],
             'timeline': {'bounds': [.22, .6], 'stops': [0, .42, 1], 'cam': [WIDE, [.62, .86, LOW]]}},
            {'id': 'a2', 'blueprint': True, 'len': 600, 'label': 'روش DaranX در دسترسی و پایش', 'world': a2(),
             'caps': [(0, 'lead', 'منابع حساس و نقش‌ها', 'اول مشخص می‌کنیم چه منابعی حساس‌اند و هر نقش به چه سطحی از دسترسی نیاز دارد.'),
                      (1, 'h3', 'دقیقاً همان دسترسی لازم', 'نه بیشتر و نه کمتر؛ هر نقش فقط به منابعی می‌رسد که برای کارش لازم است.'),
                      (2, 'h3', 'ثبت هر رویداد', 'رویدادها طوری ثبت و نگهداری می‌شوند که در زمان بررسی قابل‌اتکا باشند.'),
                      (3, 'h3', 'هشدار در رفتار غیرعادی', 'پایش پیوسته، دسترسی غیرعادی را زود نشان می‌دهد.'),
                      (4, 'h3', 'آماده برای ممیزی و انطباق', 'وقتی مشکلی پیش می‌آید، به‌جای حدس زدن، می‌دانید چه شده است.')],
             'timeline': {'bounds': [.22, .4, .58, .76], 'stops': [.1, .31, .49, .67, .9],
                          'cam': [WIDE, [.42, .52, LOW], [.78, .86, WIDE]]}},
            {'id': 'a3', 'blueprint': True, 'len': 420, 'label': 'تغییر نقش و به‌روز شدن دسترسی', 'world': a3(),
             'caps': [(0, 'lead', 'نقش عوض شد، دسترسی هم', 'نقش‌ها و افراد تغییر می‌کنند؛ دسترسی‌ها باید همیشه همراه آن‌ها به‌روز بمانند.'),
                      (1, 'h3', 'هماهنگ با بقیهٔ لایه‌های امنیت', 'دسترسی و پایش با امنیت شبکه و کنترل تردد با هم طراحی می‌شوند.'),
                      (2, 'h3', 'همه چیز سر جای درستش.', 'دسترسی و پایش را برای مسئلهٔ واقعی شما طراحی کنیم.', S.final_actions())],
             'timeline': {'bounds': [.45, .75], 'stops': [.3, .6, 1],
                          'cam': [WIDE, [.46, .58, LOW], [.78, .9, WIDE]],
                          'moves': [['a3-mv', [[410, 382], [430, 300], [470, 212]], .06, .26]]}},
        ],
        'states': [
            ('a1', '.qs', [1], True, 'opacity:1'),
            ('a1', ':is(.allg,.over-c)', [2], True, 'opacity:1'),
            ('a1', '.who .ic', [2], False, 'fill:#FFB4A8'),
            ('a2', ':is(.hot,.hot-t,.roles)', [0], True, 'opacity:1'),
            ('a2', '.needg', [1, 2, 3, 4], True, 'opacity:1'),
            ('a2', '.logp', [2, 3, 4], True, 'opacity:1'),
            ('a2', '.lrow:not(.alert)', [2, 3, 4], True, 'opacity:1'),
            ('a2', '.lrow.alert', [3, 4], True, 'opacity:1'),
            ('a2', '.anom', [3], True, 'opacity:1'),
            ('a2', '.audit', [4], True, 'opacity:1'),
            ('a3', '.needg', [0, 1, 2], True, 'opacity:1'),
            ('a3', '.pp-sal1', [0, 1, 2], True, 'opacity:0'),
            ('a3', ':is(.mvp,.role-c,.new-l)', [0], True, 'opacity:1'),
            ('a3', '.old-l', [0], False, 'opacity:0'),
            ('a3', '.new-l', [1, 2], True, 'opacity:1'),
            ('a3', '.mvp', [1, 2], True, 'opacity:1'),
            ('a3', '.integ', [1], True, 'opacity:1'),
        ],
        'css': """.qs,.allg,.over-c,.roles,.needg,.logp,.anom,.audit,.mvp,.role-c,.new-l,.integ,.hot,.hot-t{opacity:0;transition:opacity .5s var(--ease-out)}
.old-l{opacity:.7;stroke:var(--alert);transition:opacity .5s ease}
.all-l{stroke-width:1.6;opacity:.55}.need-l{stroke:var(--brand);stroke-width:2.5}
.rs .ic{fill:var(--brand)}.lbl.rs-t{font-size:15px}
.role rect{stroke:rgba(127,176,223,.6)}.lbl.chip-t{font-size:17px}
.mover .ic{fill:var(--brand)}
#a3 .pp-sal1{transition:opacity .3s ease}""",
        'summary': {'h2': 'مدیریت دسترسی و پایش رویدادها در DaranX',
                    'paras': ['دسترسی و پایش به یک پرسش ساده پاسخ می‌دهد: چه کسی، به چه چیزی، چه زمانی دسترسی داشته است؟ DaranX سطوح دسترسی را بر اساس نقش طراحی می‌کند و رویدادها را پیوسته ثبت و پایش می‌کند.',
                              'دسترسی بیش از حد به‌اندازهٔ نبود کنترل خطرناک است؛ هدف، دادن دقیقاً همان دسترسی لازم است، نه بیشتر و نه کمتر. نتیجه، کنترل دقیق، امکان ردیابی رویدادها و آمادگی برای ممیزی و انطباق است.'],
                    'bullets': ['شناخت منابع حساس و سطح دسترسی لازم برای هر نقش.', 'تعریف سطوح دسترسی بر اساس نقش.', 'ثبت و نگهداری قابل‌اتکای رویدادها.',
                                'پایش پیوسته و هشدار در موارد غیرعادی.', 'به‌روز نگه داشتن دسترسی‌ها همراه با تغییر نقش‌ها و افراد.']},
        'why': ['کنترل دقیق سطوح دسترسی', 'ردیابی و ثبت رویداد', 'آمادگی برای انطباق و ممیزی'],
        'method': [('شناخت', 'منابع حساس، نقش‌ها و رویدادهایی که باید ثبت شوند.'), ('طراحی', 'سطوح دسترسی، ثبت رویداد، پایش و هشدار.'),
                   ('اجرا', 'پیاده‌سازی هماهنگ با بقیهٔ لایه‌های امنیت.'), ('پشتیبانی', 'به‌روز نگه داشتن دسترسی‌ها همراه با تغییرات سازمان.')],
        'faq': [('دسترسی بر اساس نقش یعنی چه؟', 'هر نقش در سازمان فقط به منابعی دسترسی دارد که برای کارش لازم است؛ دسترسی‌ها به‌جای افراد، برای نقش‌ها تعریف می‌شوند.'),
                ('چه رویدادهایی ثبت می‌شوند؟', 'رویدادهایی که در شناخت اولیه لازم تشخیص داده می‌شوند؛ طوری ثبت و نگهداری می‌شوند که در زمان بررسی قابل‌اتکا باشند.'),
                ('اگر نقش کسی عوض شود چه می‌شود؟', 'دسترسی‌ها همراه با تغییر نقش‌ها و افراد به‌روز می‌شوند؛ مدیریت این تغییرات بخشی از پشتیبانی ماست.'),
                ('برای ممیزی هم آماده است؟', 'بله. ثبت رویدادها و تعریف دقیق دسترسی‌ها، سازمان را برای ممیزی و انطباق آماده می‌کند.')],
        'related': {'h2': 'بقیهٔ حوزهٔ امنیت', 'links': [('امنیت شبکه', 'network-security.html'), ('دوربین مداربسته', 'cctv.html'), ('کنترل تردد', 'access-control.html'),
                                                        ('همهٔ حوزهٔ امنیت', 'security.html')]},
        'cta': {'h2': 'دسترسی و پایش را برای مسئلهٔ واقعی شما طراحی کنیم.', 'p': 'اول نیاز شما را مشخص می‌کنیم، بعد می‌گوییم چه چیزی واقعاً لازم دارید.'},
    }
